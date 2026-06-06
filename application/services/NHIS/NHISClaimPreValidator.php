<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class NHISClaimPreValidator
{
    /** @var CI_Controller */
    private $CI;
    /** @var CI_DB_query_builder|null */
    private $db;

    public function __construct()
    {
        // In production this will be a real CI instance; in tests
        // fixture subclasses do NOT call parent::__construct().
        $ci = function_exists('get_instance') ? get_instance() : null;
        $this->CI = $ci;
        $this->db = $ci && isset($ci->db) ? $ci->db : null;

        if ($ci && isset($ci->load)) {
            $ci->load->model('app/nhis_model');
            $ci->load->model('app/Nhis_reference_model');
            $ci->load->model('app/Nhis_validation_model', 'nhis_validation');
            $ci->load->helper('nhis_formatter');
        }
    }

    /**
     * Validate an NHIS batch and all related entities.
     *
     * @param int $batch_id
     * @return array
     */
    public function validate(int $batch_id): array
    {
        $batch_id = (int)$batch_id;

        $result = [
            'is_valid'   => false,
            'can_export' => false,
            'errors'     => [],
            'warnings'   => [],
            'summary'    => [
                'total_claims'                 => 0,
                'error_count'                  => 0,
                'warning_count'                => 0,
                'claims_requiring_attachments' => [],
            ],
        ];

        if ($batch_id <= 0) {
            $result['errors'][] = $this->makeError(
                'batch',
                'batch:' . $batch_id,
                'BatchID',
                101,
                'Invalid batch_id provided',
                'error'
            );
            $this->finalizeFlags($result);
            return $result;
        }

        // Load all data (production). Tests override loadContext().
        $context = $this->loadContext($batch_id);

        $claims = isset($context['claims']) && is_array($context['claims'])
            ? $context['claims']
            : [];
        $result['summary']['total_claims'] = count($claims);

        // Grouped validations
        $this->validateBatchGroup($context, $result['errors'], $result['warnings']);
        $this->validatePatientGroup($context, $result['errors'], $result['warnings']);
        $this->validateClaimGroup($context, $result['errors'], $result['warnings']);
        $this->validateTreatmentGroup($context, $result['errors'], $result['warnings']);
        $this->validateMedicineGroup(
            $context,
            $result['errors'],
            $result['warnings'],
            $result['summary']['claims_requiring_attachments']
        );
        $this->validateWarningPatterns($context, $result['errors'], $result['warnings']);

        $this->finalizeFlags($result);
        return $result;
    }

    /**
     * Standard error/warning record.
     */
    private function makeError(
        string $level,
        string $reference,
        string $field,
        ?int $claimit_code,
        string $message,
        string $severity = 'error'
    ): array {
        return [
            'level'        => $level,
            'reference'    => $reference,
            'field'        => $field,
            'claimit_code' => $claimit_code,
            'message'      => $message,
            'severity'     => $severity,
        ];
    }

    /**
     * Recompute summary counters and export flags.
     */
    private function finalizeFlags(array &$result): void
    {
        $errorCount = 0;
        $warningCount = 0;

        foreach ($result['errors'] as $e) {
            if (isset($e['severity']) && $e['severity'] === 'warning') {
                $warningCount++;
            } else {
                $errorCount++;
            }
        }

        foreach ($result['warnings'] as $w) {
            $warningCount++;
        }

        $result['summary']['error_count'] = $errorCount;
        $result['summary']['warning_count'] = $warningCount;

        $result['is_valid'] = ($errorCount === 0);
        $result['can_export'] = ($errorCount === 0);
    }

    // ---------------------------------------------------------------------
    // Context loading (production). Tests override this via subclass.
    // ---------------------------------------------------------------------

    /**
     * Load batch-related data into an in-memory context.
     *
     * Keys:
     *  - batch
     *  - batch_claims[]
     *  - claims[claim_id] (each with treatments[] and medicines[])
     *  - patients[patient_no]
     *  - facility_credentials
     *  - version_info[artifact]
     *  - speciality_codes[code]
     *  - ref_medicines[code] (tests only for now)
     *  - ref_gdrg[], ref_icd10[] (tests only for now)
     *  - gdrg_icd_mapping_count (int)
     */
    protected function loadContext(int $batch_id): array
    {
        // In tests, db is null and this method is overridden.
        if (!$this->db || !method_exists($this->db, 'where')) {
            return [
                'batch'                    => null,
                'batch_claims'             => [],
                'claims'                   => [],
                'patients'                 => [],
                'facility_credentials'     => null,
                'version_info'             => [],
                'speciality_codes'         => [],
                'ref_medicines'            => [],
                'ref_gdrg'                 => [],
                'ref_icd10'                => [],
                'gdrg_icd_mapping_count'   => 0,
            ];
        }

        $ctx = [
            'batch'                    => null,
            'batch_claims'             => [],
            'claims'                   => [],
            'patients'                 => [],
            'facility_credentials'     => null,
            'version_info'             => [],
            'speciality_codes'         => [],
            'ref_medicines'            => [],
            'ref_gdrg'                 => [],
            'ref_icd10'                => [],
            'gdrg_icd_mapping_count'   => 0,
        ];

        // NOTE: This is structured to stay under ~10 queries for loadContext itself.
        // 1) Batch header
        $batch = $this->db->where('id', $batch_id)
            ->get('nhis_batches')
            ->row_array();
        $ctx['batch'] = $batch ?: null;

        // 2) nhis_batch_claims rows
        $batchClaims = $this->db->where('batch_id', $batch_id)
            ->get('nhis_batch_claims')
            ->result_array();
        $ctx['batch_claims'] = $batchClaims;

        // Extract visit_ids (not claim_ids)
        $visitIds = array_unique(array_filter(
            array_column($batchClaims, 'visit_id')
        ));

        // 3) Canonical claims via encounter_id = visit_id
        $claims = [];
        if (!empty($visitIds)) {
            $rows = $this->db->where_in('encounter_id', $visitIds)
                ->get('nhis_claims')
                ->result_array();
            foreach ($rows as $row) {
                $id = (int)$row['id'];
                $row['treatments'] = [];
                $row['medicines']  = [];
                $claims[$id] = $row;
            }
        }
        $ctx['claims'] = $claims;

        // Extract patient_nos from the canonical claims
        $patientNos = array_unique(array_filter(
            array_column($claims, 'patient_no')
        ));

        // 4) Items (split into treatments vs medicines)
        if (!empty($claims)) {
            $claimIds = array_keys($claims);
            $items = $this->db->where_in('claim_id', $claimIds)
                ->get('nhis_claim_items')
                ->result_array();
            foreach ($items as $item) {
                $cid = (int)$item['claim_id'];
                if (!isset($ctx['claims'][$cid])) {
                    continue;
                }
                $type = strtolower((string)$item['item_type']);
                if ($type === 'drug' || $type === 'medicine') {
                    $ctx['claims'][$cid]['medicines'][] = $item;
                } else {
                    $ctx['claims'][$cid]['treatments'][] = $item;
                }
            }
        }

        // 5) Patients
        $patients = [];
        if (!empty($patientNos)) {
            $rows = $this->db->where_in('patient_no', $patientNos)
                ->get('patient_personal_info')
                ->result_array();
            foreach ($rows as $row) {
                $patients[$row['patient_no']] = $row;
            }
        }
        $ctx['patients'] = $patients;

        // 6) Active facility credentials
        $cred = $this->db->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('nhis_facility_credentials')
            ->row_array();
        $ctx['facility_credentials'] = $cred ?: null;

        // 7) Version info (is_current = 1)
        $versions = $this->db->where('is_current', 1)
            ->get('nhis_version_info')
            ->result_array();
        foreach ($versions as $v) {
            $component = isset($v['component']) ? (string)$v['component'] : '';
            if ($component !== '') {
                $ctx['version_info'][$component] = $v;
            }
        }

        // 8) Speciality codes
        if ($this->db->table_exists('nhis_speciality_codes')) {
            $specs = $this->db->where('is_active', 1)
                ->get('nhis_speciality_codes')
                ->result_array();
            $map = [];
            foreach ($specs as $s) {
                $code = isset($s['code']) ? (string)$s['code'] : '';
                if ($code !== '') {
                    $map[$code] = $s;
                }
            }
            $ctx['speciality_codes'] = $map;
        }

        // 9) GDRG-ICD mapping count
        if ($this->db->table_exists('nhis_gdrg_icd_mappings')) {
            $ctx['gdrg_icd_mapping_count'] = (int)$this->db->count_all('nhis_gdrg_icd_mappings');
        }

        // NOTE: For now, ref_medicines/ref_gdrg/ref_icd10 are left empty in
        // production and are populated in PHPUnit fixtures. Existence checks
        // for GDRG/ICD are delegated to Nhis_validation_model.

        return $ctx;
    }

    // ---------------------------------------------------------------------
    // Group validations
    // ---------------------------------------------------------------------

    private function validateBatchGroup(array $ctx, array &$errors, array &$warnings): void
    {
        $batch       = $ctx['batch'] ?? null;
        $facility    = $ctx['facility_credentials'] ?? null;
        $versions    = $ctx['version_info'] ?? [];
        $batchClaims = $ctx['batch_claims'] ?? [];

        $batchRef = ($batch && isset($batch['id'])) ? 'batch:' . $batch['id'] : 'batch:unknown';

        // Active nhis_facility_credentials
        if (!$facility || empty($facility['is_active'])) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'FacilityCredentials',
                101,
                'Active facility credentials not found',
                'error'
            );
        }

        // ProviderAccreditationNumber not empty
        if (
            !$facility ||
            trim((string)($facility['provider_accreditation_number'] ?? '')) === ''
        ) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'ProviderAccreditationNumber',
                102,
                'Provider accreditation number is required',
                'error'
            );
        }

        // eClaimAuthorizationNumber positive integer
        if ($facility) {
            $eclaim    = $facility['eclaim_authorization_number'] ?? null;
            $eclaimStr = trim((string)$eclaim);
            if ($eclaimStr === '' || !ctype_digit($eclaimStr) || (int)$eclaimStr <= 0) {
                $errors[] = $this->makeError(
                    'batch',
                    $batchRef,
                    'eClaimAuthorizationNumber',
                    103,
                    'eClaimAuthorizationNumber must be a positive integer',
                    'error'
                );
            }
        }

        // XMLFormatVersion
        $xmlVersion = $versions['XMLFormatVersion'] ?? null;
        if (
            !$xmlVersion ||
            empty($xmlVersion['is_current']) ||
            ($xmlVersion['version_value'] ?? null) === null
        ) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'XMLFormatVersion',
                104,
                'XMLFormatVersion not configured, export a claim from CLAIM-it to find the value',
                'error'
            );
        }

        // ServiceYear/ServiceMonth
        $serviceYear  = $batch['service_year'] ?? null;
        $serviceMonth = $batch['service_month'] ?? null;
        if (!is_string($serviceYear) || !preg_match('/^[0-9]{4}$/', $serviceYear)) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'ServiceYear',
                105,
                'ServiceYear must be 4-digit YYYY',
                'error'
            );
        }
        if (!is_string($serviceMonth) || !preg_match('/^(0[1-9]|1[0-2])$/', $serviceMonth)) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'ServiceMonth',
                106,
                'ServiceMonth must be MM between 01 and 12',
                'error'
            );
        }

        // Batch contains at least one claim
        if (empty($batchClaims)) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'ClaimsCount',
                107,
                'Batch must contain at least one claim',
                'error'
            );
        }

        // ClaimsCount matches
        $expectedCount = (int)($batch['claims_count'] ?? 0);
        $actualCount   = count($batchClaims);
        if ($expectedCount !== 0 && $expectedCount !== $actualCount) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'ClaimsCount',
                108,
                'ClaimsCount does not match number of batch claims',
                'error'
            );
        }

        // BatchAmount matches SUM(total_cost) within 0.01
        $expectedAmount = isset($batch['batch_amount']) ? (float)$batch['batch_amount'] : 0.0;
        $sum = 0.0;
        foreach ($batchClaims as $bc) {
            $sum += isset($bc['total_cost']) ? (float)$bc['total_cost'] : 0.0;
        }
        if (abs($expectedAmount - $sum) > 0.01) {
            $errors[] = $this->makeError(
                'batch',
                $batchRef,
                'BatchAmount',
                109,
                'BatchAmount does not match sum of claim totals',
                'error'
            );
        }
    }

    private function validatePatientGroup(array $ctx, array &$errors, array &$warnings): void
    {
        $patients = $ctx['patients'] ?? [];
        $claims   = $ctx['claims'] ?? [];

        $claimsByPatient = [];
        foreach ($claims as $claim) {
            $pno = $claim['patient_no'] ?? null;
            if ($pno === null) {
                continue;
            }
            $claimsByPatient[$pno] = ($claimsByPatient[$pno] ?? 0) + 1;
        }

        foreach ($patients as $patientNo => $p) {
            $ref = 'patient:' . $patientNo;

            $surname = trim((string)($p['surname'] ?? ''));
            if ($surname === '') {
                $errors[] = $this->makeError(
                    'patient',
                    $ref,
                    'Surname',
                    200,
                    'Surname is required',
                    'error'
                );
            }

            $other = trim((string)($p['other_name'] ?? ($p['othernames'] ?? '')));
            if ($other === '') {
                $errors[] = $this->makeError(
                    'patient',
                    $ref,
                    'OtherName',
                    201,
                    'OtherName is required',
                    'error'
                );
            }

            $dob = $p['date_of_birth'] ?? null;
            if (!$dob || strtotime($dob) === false || strtotime($dob) >= time()) {
                $errors[] = $this->makeError(
                    'patient',
                    $ref,
                    'DateOfBirth',
                    202,
                    'DateOfBirth must be a valid past date',
                    'error'
                );
            }

            $gender = strtoupper(trim((string)($p['gender'] ?? '')));
            if (!in_array($gender, ['M', 'F'], true)) {
                $errors[] = $this->makeError(
                    'patient',
                    $ref,
                    'Gender',
                    203,
                    'Gender must be M or F',
                    'error'
                );
            }

            $memberNo = trim((string)($p['nhis_member_number'] ?? ''));
            $tempNo   = trim((string)($p['nhis_temporary_card_number'] ?? ''));
            if ($memberNo === '' && $tempNo === '') {
                $errors[] = $this->makeError(
                    'patient',
                    $ref,
                    'MemberNumber',
                    204,
                    'Either nhis_member_number or nhis_temporary_card_number is required',
                    'error'
                );
            }

            if (empty($claimsByPatient[$patientNo])) {
                $errors[] = $this->makeError(
                    'patient',
                    $ref,
                    'Claims',
                    205,
                    'Patient has at least one row in this batch',
                    'error'
                );
            }
        }
    }

    private function validateClaimGroup(array $ctx, array &$errors, array &$warnings): void
    {
        $claims       = $ctx['claims'] ?? [];
        $specialities = $ctx['speciality_codes'] ?? [];

        foreach ($claims as $claim) {
            $cid = $claim['id'] ?? null;
            $ref = 'claim:' . ($cid !== null ? $cid : 'unknown');

            $serviceType = strtoupper(trim((string)($claim['service_type'] ?? '')));
            if (!in_array($serviceType, ['OUT', 'INP', 'DIA', 'CAP'], true)) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'ServiceType',
                    206,
                    'ServiceType must be one of OUT, INP, DIA, CAP',
                    'error'
                );
            }

            $outcomeType = strtoupper(trim((string)($claim['outcome_type'] ?? '')));
            if (!in_array($outcomeType, ['DIS', 'DIE', 'TFR', 'ABS', 'DAA'], true)) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'OutcomeType',
                    207,
                    'OutcomeType must be one of DIS, DIE, TFR, ABS, DAA',
                    'error'
                );
            }

            $admissionType = strtoupper(trim((string)($claim['admission_type'] ?? '')));
            if (!in_array($admissionType, ['CRO', 'EME', 'ACU'], true)) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'AdmissionType',
                    208,
                    'AdmissionType is required and must be one of CRO, EME, ACU',
                    'error'
                );
            }

            $specCode = trim((string)($claim['speciality_code'] ?? ''));
            if ($specCode === '' || empty($specialities[$specCode]['is_active'])) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'SpecialityCode',
                    209,
                    'SpecialityCode must reference an active NHIS speciality',
                    'error'
                );
            }

            $admDate = $claim['admission_date'] ?? null;
            if (!$admDate || strtotime($admDate) === false) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'AdmissionDate',
                    210,
                    'AdmissionDate must be a valid date',
                    'error'
                );
            }

            $dischargeDate = $claim['discharge_date'] ?? null;
            $duration      = isset($claim['duration_length'])
                ? (int)$claim['duration_length']
                : null;

            if ($serviceType === 'INP') {
                if (
                    !$dischargeDate ||
                    strtotime($dischargeDate) === false ||
                    ($admDate && strtotime($dischargeDate) < strtotime($admDate))
                ) {
                    $errors[] = $this->makeError(
                        'claim',
                        $ref,
                        'DischargeDate',
                        211,
                        'DischargeDate must be present and >= AdmissionDate for INP',
                        'error'
                    );
                }

                if ($admDate && $dischargeDate && $duration !== null) {
                    $days = (int)floor((strtotime($dischargeDate) - strtotime($admDate)) / 86400) + 1;
                    if ($days !== $duration) {
                        $errors[] = $this->makeError(
                            'claim',
                            $ref,
                            'DurationLength',
                            212,
                            'DurationLength must equal (DischargeDate - AdmissionDate) in days',
                            'error'
                        );
                    }
                }
            }

            if ($serviceType === 'OUT') {
                $opc = (string)($claim['out_patient_code'] ?? '');
                if (strlen($opc) !== 5) {
                    $errors[] = $this->makeError(
                        'claim',
                        $ref,
                        'OutPatientCode',
                        213,
                        'OutPatientCode is required for OUT claims and must be exactly 5 characters',
                        'error'
                    );
                }
            }

            if ($serviceType === 'INP') {
                $ipc = trim((string)($claim['in_patient_code'] ?? ''));
                if ($ipc === '') {
                    $errors[] = $this->makeError(
                        'claim',
                        $ref,
                        'InPatientCode',
                        214,
                        'InPatientCode is required for INP claims',
                        'error'
                    );
                }
            }

            if ($serviceType === 'DIA') {
                $inv = trim((string)($claim['investigation_code'] ?? ''));
                if ($inv === '') {
                    $errors[] = $this->makeError(
                        'claim',
                        $ref,
                        'InvestigationCode',
                        215,
                        'InvestigationCode is required for DIA claims',
                        'error'
                    );
                }
            }

            $totalCost = isset($claim['total_cost']) ? (float)$claim['total_cost'] : 0.0;
            if ($totalCost <= 0) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'TotalCost',
                    216,
                    'TotalCost must be greater than zero',
                    'error'
                );
            }

            $treatments = $claim['treatments'] ?? [];
            $medicines  = $claim['medicines'] ?? [];

            $expectedTreatments = isset($claim['treatments_count'])
                ? (int)$claim['treatments_count']
                : count($treatments);
            if ($expectedTreatments !== count($treatments)) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'TreatmentsCount',
                    217,
                    'TreatmentsCount does not match number of treatments',
                    'error'
                );
            }

            $expectedMeds = isset($claim['medicines_count'])
                ? (int)$claim['medicines_count']
                : count($medicines);
            if ($expectedMeds !== count($medicines)) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'MedicinesCount',
                    218,
                    'MedicinesCount does not match number of medicines',
                    'error'
                );
            }

            $treatTotal = 0.0;
            foreach ($treatments as $t) {
                if (isset($t['total_cost'])) {
                    $treatTotal += (float)$t['total_cost'];
                } elseif (isset($t['tariff_amount'])) {
                    $treatTotal += (float)$t['tariff_amount'];
                }
            }

            $medTotal = 0.0;
            foreach ($medicines as $m) {
                if (isset($m['total'])) {
                    $medTotal += (float)$m['total'];
                } elseif (isset($m['medicine_total'])) {
                    $medTotal += (float)$m['medicine_total'];
                }
            }

            $sumItems = $treatTotal + $medTotal;
            if ($sumItems > 0 && abs($totalCost - $sumItems) > 0.01) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'TotalCost',
                    219,
                    'TotalCost does not equal sum of treatments and medicines',
                    'error'
                );
            }

            if (!empty($claim['in_other_active_batch'])) {
                $errors[] = $this->makeError(
                    'claim',
                    $ref,
                    'BatchStatus',
                    221,
                    'Visit already exists in a different active batch',
                    'error'
                );
            }
        }
    }

    private function validateTreatmentGroup(array $ctx, array &$errors, array &$warnings): void
    {
        $claims           = $ctx['claims'] ?? [];
        $gdrgMappingCount = (int)($ctx['gdrg_icd_mapping_count'] ?? 0);
        $pairingWarned    = false;

        foreach ($claims as $claim) {
            $cid     = $claim['id'] ?? null;
            $refBase = 'claim:' . ($cid !== null ? $cid : 'unknown');
            $admDate = $claim['admission_date'] ?? null;
            $disDate = $claim['discharge_date'] ?? null;

            $treatments = $claim['treatments'] ?? [];
            foreach ($treatments as $idx => $t) {
                $ref = $refBase . ':treatment:' . $idx;

                // Delegate reference existence checks
                foreach ($this->validateTreatmentReference($t, $ctx, $ref) as $err) {
                    $errors[] = $err;
                }

                $type = strtolower(trim((string)($t['type'] ?? '')));
                $icd  = strtoupper(trim((string)($t['icd_code'] ?? '')));

                if ($type === 'diagnosis' && $icd === '') {
                    $errors[] = $this->makeError(
                        'treatment',
                        $ref,
                        'ICDCode',
                        223,
                        'ICDCode is required for diagnosis treatments',
                        'error'
                    );
                }

                if ($gdrgMappingCount === 0) {
                    if (!$pairingWarned) {
                        $warnings[] = $this->makeError(
                            'treatment',
                            $refBase,
                            'GDRG-ICD pairing',
                            225,
                            'GDRG-ICD mapping table is empty — pairing cannot be validated. Load NHIA mapping CSV to enable this check.',
                            'warning'
                        );
                        $pairingWarned = true;
                    }
                } else {
                    // When mappings exist, pair validation would go here.
                }

                $tDate = $t['treatment_date'] ?? ($t['date'] ?? null);
                if ($tDate && $admDate && $disDate && strtotime($tDate) !== false) {
                    $ts = strtotime($tDate);
                    if ($ts < strtotime($admDate) || $ts > strtotime($disDate)) {
                        $errors[] = $this->makeError(
                            'treatment',
                            $ref,
                            'TreatmentDate',
                            226,
                            'Treatment date must fall within admission and discharge dates',
                            'error'
                        );
                    }
                }
            }
        }
    }

    private function validateMedicineGroup(
        array $ctx,
        array &$errors,
        array &$warnings,
        array &$claimsRequiringAttachments
    ): void {
        $claims  = $ctx['claims'] ?? [];
        $refMeds = $ctx['ref_medicines'] ?? [];

        foreach ($claims as $claim) {
            $cid     = $claim['id'] ?? null;
            $refBase = 'claim:' . ($cid !== null ? $cid : 'unknown');
            $admDate = $claim['admission_date'] ?? null;
            $disDate = $claim['discharge_date'] ?? null;

            $medicines = $claim['medicines'] ?? [];
            foreach ($medicines as $idx => $m) {
                $ref  = $refBase . ':medicine:' . $idx;
                $code = strtoupper(trim((string)($m['medicine_code'] ?? $m['code'] ?? '')));

                $medRow = $refMeds[$code] ?? null;
                if (!$medRow || (isset($medRow['is_active']) && !$medRow['is_active'])) {
                    $errors[] = $this->makeError(
                        'medicine',
                        $ref,
                        'MedicineCode',
                        227,
                        'MedicineCode must reference an active NHIS medicine',
                        'error'
                    );
                }

                $qty = isset($m['quantity'])
                    ? (float)$m['quantity']
                    : (isset($m['qty']) ? (float)$m['qty'] : 0.0);
                if ($qty <= 0) {
                    $errors[] = $this->makeError(
                        'medicine',
                        $ref,
                        'Quantity',
                        228,
                        'Quantity must be greater than zero',
                        'error'
                    );
                }

                $unitPrice = isset($m['unit_price']) ? (float)$m['unit_price'] : 0.0;
                if ($unitPrice <= 0) {
                    $errors[] = $this->makeError(
                        'medicine',
                        $ref,
                        'UnitPrice',
                        229,
                        'UnitPrice must be greater than zero',
                        'error'
                    );
                }

                $medTotal      = isset($m['total'])
                    ? (float)$m['total']
                    : (isset($m['medicine_total']) ? (float)$m['medicine_total'] : 0.0);
                $expectedTotal = $qty * $unitPrice;
                if ($expectedTotal > 0 && abs($expectedTotal - $medTotal) > 0.01) {
                    $errors[] = $this->makeError(
                        'medicine',
                        $ref,
                        'MedicineTotal',
                        230,
                        'MedicineTotal must equal Quantity × UnitPrice',
                        'error'
                    );
                }

                $dispDate = $m['dispensed_date'] ?? ($m['service_date'] ?? null);
                if ($dispDate && $admDate && $disDate && strtotime($dispDate) !== false) {
                    $ts = strtotime($dispDate);
                    if ($ts < strtotime($admDate) || $ts > strtotime($disDate)) {
                        $errors[] = $this->makeError(
                            'medicine',
                            $ref,
                            'DispensedDate',
                            231,
                            'Dispensed date must fall within admission and discharge dates',
                            'error'
                        );
                    }
                }

                // Warning: requires_lab_result
                if ($medRow && !empty($medRow['requires_lab_result'])) {
                    $claimsRequiringAttachments[] = $refBase;
                    $warnings[] = $this->makeError(
                        'medicine',
                        $ref,
                        'requiresLabResult',
                        null,
                        'Medicine requires laboratory result attachment',
                        'warning'
                    );
                }
            }
        }

        $claimsRequiringAttachments = array_values(array_unique($claimsRequiringAttachments));
    }

    private function validateWarningPatterns(array $ctx, array &$errors, array &$warnings): void
    {
        $claims       = $ctx['claims'] ?? [];
        $batch        = $ctx['batch'] ?? [];
        $serviceYear  = $batch['service_year'] ?? null;
        $serviceMonth = $batch['service_month'] ?? null;

        // Same patient + admission date + service type >1
        $byKey = [];
        foreach ($claims as $claim) {
            $pno   = $claim['patient_no'] ?? null;
            $adm   = $claim['admission_date'] ?? null;
            $stype = strtoupper(trim((string)($claim['service_type'] ?? '')));
            if (!$pno || !$adm || !$stype) {
                continue;
            }
            $key           = $pno . '|' . $adm . '|' . $stype;
            $byKey[$key][] = $claim;
        }
        foreach ($byKey as $list) {
            if (count($list) > 1) {
                foreach ($list as $claim) {
                    $cid = $claim['id'] ?? null;
                    $ref = 'claim:' . ($cid !== null ? $cid : 'unknown');
                    $warnings[] = $this->makeError(
                        'claim',
                        $ref,
                        'DuplicateVisit',
                        null,
                        'Same patient, admission date and service type appear more than once in this batch',
                        'warning'
                    );
                }
            }
        }

        // Same patient, ACU admission, more than once in same service month
        $acuByPatientMonth = [];
        foreach ($claims as $claim) {
            $pno     = $claim['patient_no'] ?? null;
            $admType = strtoupper(trim((string)($claim['admission_type'] ?? '')));
            if ($pno && $admType === 'ACU' && $serviceYear && $serviceMonth) {
                $key                     = $pno . '|' . $serviceYear . '-' . $serviceMonth;
                $acuByPatientMonth[$key][] = $claim;
            }
        }
        foreach ($acuByPatientMonth as $list) {
            if (count($list) > 1) {
                foreach ($list as $claim) {
                    $cid = $claim['id'] ?? null;
                    $ref = 'claim:' . ($cid !== null ? $cid : 'unknown');
                    $warnings[] = $this->makeError(
                        'claim',
                        $ref,
                        'AdmissionType',
                        null,
                        'Patient has multiple ACU admissions in the same service month',
                        'warning'
                    );
                }
            }
        }
    }

    // ---------------------------------------------------------------------
    // Treatment reference delegation hook (Nhis_validation_model)
    // ---------------------------------------------------------------------

    /**
     * Validate treatment codes against NHIS reference tables.
     *
     * Production delegates to Nhis_validation_model; test subclasses override
     * this and return an empty array.
     */
    protected function validateTreatmentReference(array $treatment, array $ctx, string $ref): array
    {
        $errors = [];

        if (!isset($this->CI) || !isset($this->CI->nhis_validation)) {
            if (isset($this->CI) && isset($this->CI->load)) {
                $this->CI->load->model('app/Nhis_validation_model', 'nhis_validation');
            }
        }

        if (!isset($this->CI) || !isset($this->CI->nhis_validation)) {
            return $errors;
        }

        $validator = $this->CI->nhis_validation;

        $gdrg = strtoupper(trim((string)($treatment['treatment_code'] ?? ($treatment['gdrg_code'] ?? ''))));
        if ($gdrg !== '') {
            $res = $validator->validate_procedure($gdrg);
            if (empty($res['valid'])) {
                $errors[] = $this->makeError(
                    'treatment',
                    $ref,
                    'TreatmentCode',
                    222,
                    'TreatmentCode is not a valid active GDRG code',
                    'error'
                );
            }
        }

        $icd = strtoupper(trim((string)($treatment['icd_code'] ?? '')));
        if ($icd !== '') {
            $res = $validator->validate_diagnosis($icd);
            if (empty($res['valid'])) {
                $errors[] = $this->makeError(
                    'treatment',
                    $ref,
                    'ICDCode',
                    224,
                    'ICDCode is not a valid active ICD-10 code',
                    'error'
                );
            }
        }

        return $errors;
    }
}