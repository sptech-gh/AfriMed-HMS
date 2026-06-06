<?php

class NursingPreconditionEvaluator
{
    protected $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function evaluateVitals(ClinicalMutationRequest $request)
    {
        $errors = array();
        $warnings = array();
        $requiredTables = array('iop_vital_parameters', 'patient_details_iop', 'clinical_idempotency_records', 'clinical_stream_locks');

        foreach ($requiredTables as $table) {
            if (!$this->db || !$this->db->table_exists($table)) {
                $errors[] = array('field' => 'table', 'code' => 'REQUIRED_TABLE_MISSING', 'message' => $table);
            }
        }

        if (count($request->payload()) === 0) {
            $errors[] = array('field' => 'payload', 'code' => 'PAYLOAD_REQUIRED', 'message' => 'Vitals payload is required.');
        }

        if ($request->truthHash() === '') {
            $warnings[] = array('field' => 'truth_state_reference.truth_hash', 'code' => 'TRUTH_HASH_NOT_SUPPLIED', 'message' => 'Truth hash was not supplied for dry-run validation.');
        }

        return array(
            'status' => count($errors) > 0 ? 'BLOCK' : 'PASS',
            'decision' => count($errors) > 0 ? 'PRECONDITION_BLOCKED' : 'PRECONDITIONS_SATISFIED',
            'required_tables' => $requiredTables,
            'errors' => $errors,
            'warnings' => $warnings,
        );
    }

    public function evaluateTruthContext(ClinicalMutationRequest $request)
    {
        $data = $request->toArray();
        $truth = isset($data['truth_state_reference']) && is_array($data['truth_state_reference']) ? $data['truth_state_reference'] : array();
        $status = isset($truth['status']) ? strtoupper(trim((string)$truth['status'])) : '';
        $errors = array();
        $warnings = array();

        if (!in_array($status, array('PASS', 'PASS_WITH_WARNINGS'), true)) {
            $errors[] = array('field' => 'truth_state_reference', 'code' => 'TRUTH_CONTEXT_NOT_CERTIFIED', 'message' => 'Truth context must be PASS or PASS_WITH_WARNINGS.');
        }

        if (isset($truth['patient_no']) && trim((string)$truth['patient_no']) !== '' && trim((string)$truth['patient_no']) !== $request->patientNo()) {
            $errors[] = array('field' => 'truth_state_reference.patient_no', 'code' => 'TRUTH_PATIENT_MISMATCH', 'message' => 'Truth patient does not match mutation request.');
        }

        if (isset($truth['encounter_id']) && trim((string)$truth['encounter_id']) !== '' && trim((string)$truth['encounter_id']) !== $request->encounterId()) {
            $errors[] = array('field' => 'truth_state_reference.encounter_id', 'code' => 'TRUTH_ENCOUNTER_MISMATCH', 'message' => 'Truth encounter does not match mutation request.');
        }

        if ($status === 'PASS_WITH_WARNINGS') {
            $warnings[] = array('field' => 'truth_state_reference.status', 'code' => 'TRUTH_PASS_WITH_WARNINGS', 'message' => 'Truth context passed with warnings.');
        }

        return array(
            'status' => count($errors) > 0 ? 'BLOCK' : 'PASS',
            'decision' => count($errors) > 0 ? 'TRUTH_BLOCKED' : 'TRUTH_ACCEPTED',
            'truth_status' => $status,
            'truth_hash' => $request->truthHash(),
            'errors' => $errors,
            'warnings' => $warnings,
        );
    }
}
