<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class NHISClaimXMLGenerator
{
    /** @var CI_Controller|null */
    private $CI;
    /** @var CI_DB_query_builder|null */
    private $db;
    /** @var NHISClaimPreValidator */
    private $preValidator;
    /** @var bool */
    protected $skipPreValidation = false;

    public function __construct()
    {
        $ci = function_exists('get_instance') ? get_instance() : null;
        $this->CI = $ci;
        $this->db = ($ci && isset($ci->db)) ? $ci->db : null;

        if ($ci && isset($ci->load)) {
            $ci->load->helper('nhis_formatter');
            $ci->load->model('app/Nhis_reference_model');
            $ci->load->model('app/nhis_model');
            $ci->load->model('app/Nhis_validation_model', 'nhis_validation');
            $ci->load->library('session');
        }

        // Pre-validator service
        $this->preValidator = new NHISClaimPreValidator();
    }

    /**
     * Generate NHIA batch XML for a given batch id.
     *
     * @param int $batch_id
     * @return string XML string (UTF-8)
     * @throws NHISValidationException
     * @throws NHISXMLSchemaException
     */
    public function generate(int $batch_id): string
    {
        if ($batch_id <= 0) {
            throw new NHISValidationException('Invalid batch_id provided');
        }

        // Run pre-validation first (unless explicitly skipped in fixture context)
        if (!$this->skipPreValidation) {
            if (!$this->preValidator instanceof NHISClaimPreValidator) {
                $this->preValidator = new NHISClaimPreValidator();
            }
            $validation = $this->preValidator->validate($batch_id);
            if (empty($validation['is_valid']) || empty($validation['can_export'])) {
                throw new NHISValidationException('Batch failed pre-validation; cannot generate XML');
            }
        }

        // Load context (production can hit DB; tests override this method)
        $ctx = $this->loadContext($batch_id);

        $batch     = $ctx['batch'] ?? null;
        $facility  = $ctx['facility_credentials'] ?? null;
        $versions  = $ctx['version_info'] ?? [];
        $patients  = $ctx['patients'] ?? [];
        $claims    = $ctx['claims'] ?? [];

        if (!$batch) {
            throw new NHISValidationException('Batch not found');
        }
        if (!$facility) {
            throw new NHISValidationException('Active facility credentials not found');
        }
        if (empty($versions['XMLFormatVersion']['version_value'])) {
            throw new NHISValidationException('XMLFormatVersion not configured in nhis_version_info');
        }
        if (empty($claims)) {
            throw new NHISValidationException('No canonical claims found for batch');
        }

        // Build DOM
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $batchEl = $dom->createElement('Batch');
        $dom->appendChild($batchEl);

        // GeneralInformation
        $generalInfoEl = $dom->createElement('GeneralInformation');
        $batchEl->appendChild($generalInfoEl);

        // VersionInformation
        $versionInfoEl = $dom->createElement('VersionInformation');
        $generalInfoEl->appendChild($versionInfoEl);

        $this->appendTextElement($dom, $versionInfoEl, 'XMLFormatVersion', $versions['XMLFormatVersion']['version_value']);

        $this->appendOptionalVersion($dom, $versionInfoEl, 'MedicineVersion', $versions, 'MedicineVersion');
        $this->appendOptionalVersion($dom, $versionInfoEl, 'GDRGVersion', $versions, 'GDRGVersion');
        $this->appendOptionalVersion($dom, $versionInfoEl, 'TariffVersion', $versions, 'TariffVersion');
        $this->appendOptionalVersion($dom, $versionInfoEl, 'ICDVersion', $versions, 'ICDVersion');
        // OpenHDDVersion intentionally omitted unless later required

        // BatchInformation
        $batchInfoEl = $dom->createElement('BatchInformation');
        $generalInfoEl->appendChild($batchInfoEl);

        $this->appendTextElement($dom, $batchInfoEl, 'BatchNumber', $batch['batch_number']);

        // BatchAmount and ClaimsCount are recomputed
        $totalBatchAmount = 0.0;
        $claimsCount = 0;

        // We'll calculate after building all Claim elements; for now placeholder
        // BatchCurrency
        $currency = $batch['batch_currency'] ?? 'GHS';

        // CreationDate, ServiceYear, ServiceMonth
        $this->appendTextElement($dom, $batchInfoEl, 'BatchCurrency', $currency);
        // ClaimsCount placeholder (will overwrite text node later)
        $claimsCountEl = $dom->createElement('ClaimsCount', '0');
        $batchInfoEl->appendChild($claimsCountEl);

        $this->appendTextElement($dom, $batchInfoEl, 'CreationDate', nhis_date($batch['creation_date']));
        $this->appendTextElement($dom, $batchInfoEl, 'ServiceYear', $batch['service_year']);
        $this->appendTextElement($dom, $batchInfoEl, 'ServiceMonth', $batch['service_month']);
        // IDPayer omitted (unknown)

        // ProviderInformation
        $providerInfoEl = $dom->createElement('ProviderInformation');
        $generalInfoEl->appendChild($providerInfoEl);

        $this->appendTextElement($dom, $providerInfoEl, 'ProviderAccreditationNumber', $facility['provider_accreditation_number']);
        if (!empty($facility['eclaim_authorization_number'])) {
            $this->appendTextElement($dom, $providerInfoEl, 'eClaimAuthorizationNumber', (int)$facility['eclaim_authorization_number']);
        }

        // Patients
        $patientsEl = $dom->createElement('Patients');
        $batchEl->appendChild($patientsEl);

        // Group claims by patient
        $claimsByPatient = [];
        foreach ($claims as $claim) {
            $pno = $claim['patient_no'] ?? null;
            if (!$pno) {
                continue;
            }
            $claimsByPatient[$pno][] = $claim;
        }

        foreach ($claimsByPatient as $patientNo => $patientClaims) {
            if (!isset($patients[$patientNo])) {
                continue;
            }
            $patientRow = $patients[$patientNo];

            $patientDataEl = $dom->createElement('PatientData');
            $patientsEl->appendChild($patientDataEl);

            // Patient mapping
            $surname = strtoupper(trim((string)($patientRow['lastname'] ?? $patientRow['surname'] ?? '')));
            $this->appendTextElement($dom, $patientDataEl, 'Surname', $surname);

            $first = trim((string)($patientRow['firstname'] ?? ''));
            $middle = trim((string)($patientRow['middlename'] ?? ''));
            $otherName = trim($first . ' ' . $middle);
            $this->appendTextElement($dom, $patientDataEl, 'OtherName', $otherName);

            if (!empty($patientRow['is_infant']) && (int)$patientRow['is_infant'] === 1) {
                $this->appendTextElement($dom, $patientDataEl, 'Infant', nhis_bool(true));
            }

            $dob = $patientRow['birthday'] ?? $patientRow['date_of_birth'] ?? null;
            if ($dob) {
                $this->appendTextElement($dom, $patientDataEl, 'DateOfBirth', nhis_date($dob));
            }

            $memberNumber = trim((string)($patientRow['nhis_member_number'] ?? ''));
            $tempCard = trim((string)($patientRow['nhis_temporary_card_number'] ?? ''));
            if ($memberNumber !== '') {
                $this->appendTextElement($dom, $patientDataEl, 'MemberNumber', $memberNumber);
            } elseif ($tempCard !== '') {
                $this->appendTextElement($dom, $patientDataEl, 'TemporaryCardNumber', $tempCard);
            }

            if (!empty($patientRow['patient_no'])) {
                $this->appendTextElement($dom, $patientDataEl, 'HospitalRecordNumber', $patientRow['patient_no']);
            }

            if (!empty($patientRow['nhis_card_serial_number'])) {
                $this->appendTextElement($dom, $patientDataEl, 'CardSerialNumber', $patientRow['nhis_card_serial_number']);
            }

            if (!empty($patientRow['gender'])) {
                $this->appendTextElement($dom, $patientDataEl, 'Gender', nhis_gender($patientRow['gender']));
            }

            // Claims for this patient
            $claimsWrapperEl = $dom->createElement('Claims');
            $patientDataEl->appendChild($claimsWrapperEl);

            foreach ($patientClaims as $claim) {
                $claimId = (int)$claim['id'];
                $claimEl = $dom->createElement('Claim');
                $claimsWrapperEl->appendChild($claimEl);

                // Canonical claim mapping
                $this->appendTextElement($dom, $claimEl, 'ClaimIdentificationNumber', $claim['claim_number']);
                $this->appendTextElement($dom, $claimEl, 'ServiceType', $claim['service_type']);
                $this->appendTextElement($dom, $claimEl, 'PharmacyIncluded', nhis_bool($claim['pharmacy_included']));
                $this->appendTextElement($dom, $claimEl, 'AllInclusive', nhis_bool($claim['all_inclusive']));
                $this->appendTextElement($dom, $claimEl, 'OutcomeType', $claim['outcome_type']);

                if (!empty($claim['duration_length']) && $claim['service_type'] === 'INP') {
                    $this->appendTextElement($dom, $claimEl, 'DurationLength', (int)$claim['duration_length']);
                }

                $this->appendTextElement($dom, $claimEl, 'AdmissionType', $claim['admission_type']);
                $this->appendTextElement($dom, $claimEl, 'SpecialityCode', $claim['speciality_code']);
                $this->appendTextElement($dom, $claimEl, 'AdmissionDate', nhis_date($claim['admission_date']));

                if (!empty($claim['discharge_date'])) {
                    $this->appendTextElement($dom, $claimEl, 'DischargeDate', nhis_date($claim['discharge_date']));
                }

                if (!empty($claim['in_patient_code'])) {
                    $this->appendTextElement($dom, $claimEl, 'InPatientCode', $claim['in_patient_code']);
                }
                if (!empty($claim['out_patient_code'])) {
                    $this->appendTextElement($dom, $claimEl, 'OutPatientCode', $claim['out_patient_code']);
                }
                if (!empty($claim['investigation_code'])) {
                    $this->appendTextElement($dom, $claimEl, 'InvestigationCode', $claim['investigation_code']);
                }

                if (!is_null($claim['out_patient_tariff'] ?? null)) {
                    $this->appendTextElement($dom, $claimEl, 'OutPatientTariffAmount', nhis_decimal($claim['out_patient_tariff']));
                }
                if (!is_null($claim['in_patient_tariff'] ?? null)) {
                    $this->appendTextElement($dom, $claimEl, 'InPatientTariffAmount', nhis_decimal($claim['in_patient_tariff']));
                }

                if (!empty($claim['referral_number'])) {
                    $this->appendTextElement($dom, $claimEl, 'ReferralNo', $claim['referral_number']);
                }

                // Treatments & Medicines
                $treatmentsEl = $dom->createElement('Treatments');
                $medicinesEl = $dom->createElement('Medicines');
                $claimEl->appendChild($treatmentsEl);
                $claimEl->appendChild($medicinesEl);

                $treatmentCount = 0;
                $medicineCount = 0;
                $treatmentTotal = 0.0;
                $medicineTotal  = 0.0;

                $claimTreatments = $claim['treatments'] ?? [];
                $claimMedicines  = $claim['medicines'] ?? [];

                foreach ($claimTreatments as $item) {
                    $treatEl = $dom->createElement('Treatment');
                    $treatmentsEl->appendChild($treatEl);

                    if (!empty($item['treatment_date'])) {
                        $this->appendTextElement($dom, $treatEl, 'Date', nhis_date($item['treatment_date']));
                    }

                    $this->appendTextElement($dom, $treatEl, 'Type', $item['treatment_type']);
                    $this->appendTextElement($dom, $treatEl, 'TreatmentCode', $item['gdrg_code']);

                    if (!empty($item['icd10_code'])) {
                        $this->appendTextElement($dom, $treatEl, 'ICDCode', $item['icd10_code']);
                    }

                    if (!is_null($item['tariff_amount'] ?? null)) {
                        $this->appendTextElement($dom, $treatEl, 'Tariff', nhis_decimal($item['tariff_amount']));
                    }

                    $treatmentCount++;
                    $treatmentTotal += (float)($item['tariff_amount'] ?? 0.0);
                }

                foreach ($claimMedicines as $item) {
                    $medicineEl = $dom->createElement('Medicine');
                    $medicinesEl->appendChild($medicineEl);

                    $this->appendTextElement($dom, $medicineEl, 'MedicineCode', $item['nhis_medicine_code']);
                    $this->appendTextElement($dom, $medicineEl, 'Quantity', nhis_decimal($item['quantity']));
                    $this->appendTextElement($dom, $medicineEl, 'UnitPrice', nhis_decimal($item['unit_price']));
                    $this->appendTextElement($dom, $medicineEl, 'MedicineTotal', nhis_decimal($item['medicine_total']));

                    $medicineCount++;
                    $medicineTotal += (float)$item['medicine_total'];
                }

                // Always keep Medicines element present; Treatments may be empty

                // TotalCost and counts recomputed
                $totalCost = $treatmentTotal + $medicineTotal;
                $totalBatchAmount += $totalCost;
                $claimsCount++;

                $this->appendTextElement($dom, $claimEl, 'TotalCost', nhis_decimal($totalCost));
                $this->appendTextElement($dom, $claimEl, 'TreatmentsCount', $treatmentCount);
                $this->appendTextElement($dom, $claimEl, 'MedicinesCount', $medicineCount);
            }
        }

        // Now that totals are known, append BatchAmount and update ClaimsCount
        $batchAmountEl = $dom->createElement('BatchAmount', nhis_decimal($totalBatchAmount));
        $batchInfoEl->insertBefore($batchAmountEl, $batchInfoEl->getElementsByTagName('BatchCurrency')->item(0));

        $claimsCountEl->nodeValue = (string)$claimsCount;

        // Validate against XSD
        $xsdPath = APPPATH . '../tests/_support/nhia_claims.xsd';
        libxml_use_internal_errors(true);
        $isValid = $dom->schemaValidate($xsdPath);
        if (!$isValid) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            $messages = array_map(function ($e) {
                return trim($e->message);
            }, $errors);
            throw new NHISXMLSchemaException(
                'Generated XML failed XSD validation: ' . implode('; ', $messages)
            );
        }

        return $dom->saveXML();
    }

    private function appendTextElement(DOMDocument $dom, DOMElement $parent, string $name, $value): void
    {
        if ($value === null) {
            return;
        }
        $value = (string)$value;
        if ($value === '') {
            return;
        }
        $el = $dom->createElement($name);
        $el->appendChild($dom->createTextNode($value));
        $parent->appendChild($el);
    }

    private function appendOptionalVersion(DOMDocument $dom, DOMElement $parent, string $xmlName, array $versions, string $componentKey): void
    {
        if (!empty($versions[$componentKey]['version_value'])) {
            $this->appendTextElement($dom, $parent, $xmlName, $versions[$componentKey]['version_value']);
        }
    }

    /**
     * Load all batch-related context required for XML generation.
     *
     * Production uses the live database; tests can override this method to
     * return an in-memory fixture.
     */
    protected function loadContext(int $batch_id): array
    {
        if (!$this->db || !method_exists($this->db, 'where')) {
            return [
                'batch'                => null,
                'facility_credentials' => null,
                'version_info'         => [],
                'patients'             => [],
                'claims'               => [],
            ];
        }

        $ctx = [
            'batch'                => null,
            'facility_credentials' => null,
            'version_info'         => [],
            'patients'             => [],
            'claims'               => [],
        ];

        // Batch header
        $batch = $this->db->where('id', $batch_id)
            ->get('nhis_batches')
            ->row_array();
        $ctx['batch'] = $batch ?: null;

        // Facility credentials
        $facility = $this->db->where('is_active', 1)
            ->order_by('id', 'DESC')
            ->limit(1)
            ->get('nhis_facility_credentials')
            ->row_array();
        $ctx['facility_credentials'] = $facility ?: null;

        // Version info (component keyed)
        $versions = [];
        if ($this->db->table_exists('nhis_version_info')) {
            $rows = $this->db->get('nhis_version_info')->result_array();
            foreach ($rows as $row) {
                $component = isset($row['component']) ? (string)$row['component'] : '';
                if ($component !== '') {
                    $versions[$component] = $row;
                }
            }
        }
        $ctx['version_info'] = $versions;

        // Batch-claim links
        $batchClaims = $this->db->where('batch_id', $batch_id)
            ->get('nhis_batch_claims')
            ->result_array();
        $ctx['batch_claims'] = $batchClaims;

        $visitIds = array_unique(array_filter(array_column($batchClaims, 'visit_id')));

        // Canonical claims keyed by id
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

        // Patients for all claims
        $patientNos = array_unique(array_filter(array_column($claims, 'patient_no')));
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

        return $ctx;
    }
}
