<?php

class NursingIdempotencyService
{
    const NEW_REQUEST = 'NEW_REQUEST';
    const SAFE_RETRY = 'SAFE_RETRY';
    const DUPLICATE_SUBMISSION = 'DUPLICATE_SUBMISSION';
    const IDEMPOTENCY_COLLISION = 'IDEMPOTENCY_COLLISION';
    const RECOVERY_CONTINUATION = 'RECOVERY_CONTINUATION';
    const LEASE_ACTIVE = 'LEASE_ACTIVE';
    const LEASE_EXPIRED_RECOVERABLE = 'LEASE_EXPIRED_RECOVERABLE';

    protected $db;
    protected $idempotencyTable = 'clinical_idempotency_records';
    protected $dryRun;

    public function __construct($db, $dryRun = true)
    {
        $this->db = $db;
        $this->dryRun = (bool)$dryRun;
    }

    public function generateOperationFingerprint(ClinicalMutationRequest $request)
    {
        return $request->operationFingerprint();
    }

    public function classifyRequest(ClinicalMutationRequest $request)
    {
        $decision = array(
            'classification' => self::NEW_REQUEST,
            'decision' => 'ALLOW_NEW_REQUEST',
            'idempotency_key' => $request->idempotencyKey(),
            'operation_fingerprint' => $this->generateOperationFingerprint($request),
            'payload_hash' => $request->payloadHash(),
            'truth_hash' => $request->truthHash(),
            'dry_run' => $this->dryRun,
            'existing_record' => null,
            'decoded_result' => null,
            'warnings' => array(),
            'errors' => array(),
        );

        $envelopeErrors = $request->validateEnvelope();
        if (count($envelopeErrors) > 0) {
            $decision['classification'] = 'INVALID_REQUEST_ENVELOPE';
            $decision['decision'] = 'BLOCK';
            $decision['errors'] = $envelopeErrors;
            return $decision;
        }

        if (!$this->tableExists($this->idempotencyTable)) {
            $decision['classification'] = 'IDEMPOTENCY_TABLE_MISSING';
            $decision['decision'] = 'BLOCK';
            $decision['errors'][] = array('field' => 'idempotency_table', 'code' => 'IDEMPOTENCY_TABLE_MISSING', 'message' => 'Clinical idempotency table is missing.');
            return $decision;
        }

        $record = $this->findByKeyAndEncounter($request->idempotencyKey(), $request->encounterId());
        if (!is_array($record)) {
            $duplicate = $this->detectDuplicateSubmission($request);
            if ($duplicate['duplicate_detected']) {
                $decision['classification'] = self::DUPLICATE_SUBMISSION;
                $decision['decision'] = 'ALLOW_WITH_DUPLICATE_WINDOW_WARNING';
                $decision['warnings'][] = $duplicate;
                return $decision;
            }
            return $decision;
        }

        $decision['existing_record'] = $this->redactRecord($record);
        $decision['decoded_result'] = $this->decodeStoredResult(isset($record['result_json']) ? $record['result_json'] : null);

        if ($this->hasFingerprintColumn() && isset($record['operation_fingerprint']) && trim((string)$record['operation_fingerprint']) !== '') {
            if (trim((string)$record['operation_fingerprint']) !== $decision['operation_fingerprint']) {
                $decision['classification'] = self::IDEMPOTENCY_COLLISION;
                $decision['decision'] = 'BLOCK_COLLISION';
                return $decision;
            }
        }

        $status = isset($record['status']) ? strtoupper(trim((string)$record['status'])) : '';
        if ($status === 'SUCCESS') {
            $decision['classification'] = self::SAFE_RETRY;
            $decision['decision'] = 'RETURN_PRIOR_RESULT';
            return $decision;
        }

        if ($status === 'PROCESSING') {
            if ($this->isLeaseActive($record)) {
                $decision['classification'] = self::LEASE_ACTIVE;
                $decision['decision'] = 'BLOCK_ACTIVE_LEASE';
                return $decision;
            }
            $decision['classification'] = self::LEASE_EXPIRED_RECOVERABLE;
            $decision['decision'] = 'ALLOW_RECOVERY_CONTINUATION';
            return $decision;
        }

        if ($status === 'FAILED') {
            $decision['classification'] = self::RECOVERY_CONTINUATION;
            $decision['decision'] = 'ALLOW_RECOVERY_RETRY';
            return $decision;
        }

        $decision['classification'] = self::RECOVERY_CONTINUATION;
        $decision['decision'] = 'REQUIRE_REVIEW_UNKNOWN_IDEMPOTENCY_STATE';
        return $decision;
    }

    public function detectReplay(ClinicalMutationRequest $request)
    {
        $decision = $this->classifyRequest($request);
        return in_array($decision['classification'], array(self::SAFE_RETRY, self::LEASE_ACTIVE, self::LEASE_EXPIRED_RECOVERABLE, self::RECOVERY_CONTINUATION), true);
    }

    public function detectCollision(ClinicalMutationRequest $request)
    {
        $decision = $this->classifyRequest($request);
        return $decision['classification'] === self::IDEMPOTENCY_COLLISION;
    }

    public function detectDuplicateSubmission(ClinicalMutationRequest $request)
    {
        $result = array(
            'duplicate_detected' => false,
            'decision' => 'NO_DUPLICATE_DETECTED',
            'window_seconds' => 60,
            'matching_rows' => 0,
        );

        if (!$this->db || !$this->db->table_exists('iop_vital_parameters')) {
            $result['decision'] = 'VITALS_TABLE_UNAVAILABLE_FOR_DUPLICATE_WINDOW_CHECK';
            return $result;
        }

        $since = date('Y-m-d H:i:s', time() - $result['window_seconds']);
        $this->db->from('iop_vital_parameters');
        $this->db->where('iop_id', $request->encounterId());
        $this->db->where('InActive', 0);
        $this->db->where('dDateTime >=', $since);
        $count = (int)$this->db->count_all_results();
        $result['matching_rows'] = $count;
        if ($count > 0) {
            $result['duplicate_detected'] = true;
            $result['decision'] = 'POSSIBLE_DUPLICATE_VITALS_WINDOW';
        }
        return $result;
    }

    protected function findByKeyAndEncounter($idempotencyKey, $encounterId)
    {
        $q = $this->db->get_where($this->idempotencyTable, array(
            'idempotency_key' => (string)$idempotencyKey,
            'iop_id' => (string)$encounterId,
        ), 1);
        $row = $q ? $q->row_array() : null;
        return is_array($row) ? $row : null;
    }

    protected function decodeStoredResult($json)
    {
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function isLeaseActive(array $record)
    {
        $leaseUntil = isset($record['lease_until']) ? trim((string)$record['lease_until']) : '';
        if ($leaseUntil === '') {
            return false;
        }
        $ts = strtotime($leaseUntil);
        return $ts !== false && $ts > time();
    }

    protected function redactRecord(array $record)
    {
        $allowed = array('id', 'idempotency_key', 'iop_id', 'status', 'lease_owner', 'lease_until', 'created_at', 'updated_at');
        $out = array();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $record)) {
                $out[$key] = $record[$key];
            }
        }
        if (isset($record['operation_fingerprint'])) {
            $out['operation_fingerprint'] = $record['operation_fingerprint'];
        }
        return $out;
    }

    protected function hasFingerprintColumn()
    {
        if (!$this->tableExists($this->idempotencyTable)) {
            return false;
        }
        $fields = $this->db->list_fields($this->idempotencyTable);
        return is_array($fields) && in_array('operation_fingerprint', $fields, true);
    }

    protected function tableExists($table)
    {
        return $this->db && method_exists($this->db, 'table_exists') && $this->db->table_exists($table);
    }
}
