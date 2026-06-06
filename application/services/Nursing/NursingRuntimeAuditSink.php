<?php

class NursingRuntimeAuditSink
{
    protected $auditRoot;

    public function __construct($auditRoot = null)
    {
        $this->auditRoot = $auditRoot !== null && trim((string)$auditRoot) !== ''
            ? rtrim((string)$auditRoot, DIRECTORY_SEPARATOR)
            : $this->defaultAuditRoot();
    }

    public function appendRuntimeAudit(ClinicalMutationRequest $request, array $result, array $verdict, array $evidenceSummary)
    {
        if (!$this->ensureDirectory($this->auditRoot)) {
            return array(
                'status' => 'SKIPPED',
                'decision' => 'AUDIT_ROOT_NOT_WRITABLE',
                'audit_root' => $this->auditRoot,
            );
        }

        $previous = $this->latestAuditRecord();
        $parentHash = isset($previous['runtime_chain_hash']) ? $previous['runtime_chain_hash'] : null;
        $record = array(
            'schema' => 'SPRINT2D_NURSING_RUNTIME_AUDIT_RECORD_V1',
            'audit_record_id' => $this->auditRecordId($request, $result),
            'action_type' => $request->actionType(),
            'request_id' => $request->requestId(),
            'idempotency_key_hash' => hash('sha256', $request->idempotencyKey()),
            'operation_fingerprint' => $request->operationFingerprint(),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'actor_user_id' => $request->actorUserId(),
            'actor_role' => $request->actorRole(),
            'payload_hash' => $request->payloadHash(),
            'truth_hash' => $request->truthHash(),
            'mode' => isset($result['mode']) ? $result['mode'] : null,
            'mutation_performed' => false,
            'result_status' => isset($result['status']) ? $result['status'] : null,
            'result_code' => isset($result['code']) ? $result['code'] : null,
            'runtime_verdict' => $verdict,
            'evidence' => $evidenceSummary,
            'runtime_parent_hash' => $parentHash,
            'created_at_utc' => gmdate('c'),
        );
        $record['runtime_audit_hash'] = $this->hashPayload($record);
        $record['runtime_chain_hash'] = hash('sha256', (string)$parentHash . '|' . $record['runtime_audit_hash']);

        $path = $this->auditRoot . DIRECTORY_SEPARATOR . $record['audit_record_id'] . '.json';
        $this->writeJson($path, $record);
        $this->writeJson($this->auditRoot . DIRECTORY_SEPARATOR . 'latest_runtime_audit.json', $record);

        return array(
            'status' => 'PASS',
            'decision' => 'RUNTIME_AUDIT_APPENDED',
            'audit_root' => $this->auditRoot,
            'audit_record_path' => $path,
            'runtime_audit_hash' => $record['runtime_audit_hash'],
            'runtime_parent_hash' => $record['runtime_parent_hash'],
            'runtime_chain_hash' => $record['runtime_chain_hash'],
        );
    }

    protected function latestAuditRecord()
    {
        $path = $this->auditRoot . DIRECTORY_SEPARATOR . 'latest_runtime_audit.json';
        if (!is_file($path)) {
            return array();
        }
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : array();
    }

    protected function auditRecordId(ClinicalMutationRequest $request, array $result)
    {
        $timestamp = gmdate('Ymd_His');
        $status = isset($result['code']) ? $this->safeSegment($result['code']) : 'UNKNOWN';
        $fingerprint = substr(hash('sha256', $request->operationFingerprint() . '|' . microtime(true)), 0, 12);
        return $timestamp . '_' . $this->safeSegment($request->patientNo()) . '_' . $this->safeSegment($request->encounterId()) . '_' . $status . '_' . $fingerprint;
    }

    protected function hashPayload(array $payload)
    {
        $copy = $payload;
        unset($copy['runtime_audit_hash']);
        unset($copy['runtime_chain_hash']);
        return hash('sha256', json_encode($this->canonicalize($copy), JSON_UNESCAPED_SLASHES));
    }

    protected function canonicalize($value)
    {
        if (!is_array($value)) {
            return $value;
        }
        $keys = array_keys($value);
        $isList = $keys === range(0, count($value) - 1);
        if (!$isList) {
            sort($keys, SORT_STRING);
            $sorted = array();
            foreach ($keys as $key) {
                $sorted[$key] = $this->canonicalize($value[$key]);
            }
            return $sorted;
        }
        $out = array();
        foreach ($value as $item) {
            $out[] = $this->canonicalize($item);
        }
        return $out;
    }

    protected function defaultAuditRoot()
    {
        $repoRoot = dirname(dirname(dirname(dirname(__FILE__))));
        return $repoRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'sprint2d' . DIRECTORY_SEPARATOR . 'evidence' . DIRECTORY_SEPARATOR . 'nursing_runtime_audit';
    }

    protected function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return is_writable($path);
        }
        return mkdir($path, 0775, true);
    }

    protected function writeJson($path, array $payload)
    {
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function safeSegment($value)
    {
        $segment = preg_replace('/[^A-Za-z0-9_\-]/', '_', trim((string)$value));
        return $segment === '' ? 'NA' : $segment;
    }
}
