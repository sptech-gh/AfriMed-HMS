<?php

class CiClinicalTransactionManager implements ClinicalTransactionManagerInterface
{
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_FAILED = 'FAILED';

    protected $db;
    protected $leaseSeconds;
    protected $leaseOwner;
    protected $idempotencyTable = 'clinical_idempotency_records';
    protected $streamLockTable = 'clinical_stream_locks';

    public function __construct($db = null, $leaseSeconds = 60, $leaseOwner = null)
    {
        if ($db !== null) {
            $this->db = $db;
        } else {
            $CI = &get_instance();
            $this->db = $CI->db;
        }

        $this->leaseSeconds = (int)$leaseSeconds > 0 ? (int)$leaseSeconds : 60;
        $this->leaseOwner = $leaseOwner !== null && $leaseOwner !== '' ? (string)$leaseOwner : $this->defaultLeaseOwner();
    }

    public function run($iopId, $idempotencyKey, callable $operation)
    {
        return $this->execute($iopId, $idempotencyKey, $operation);
    }

    public function execute($iopId, $idempotencyKey, callable $operation)
    {
        $iopId = trim((string)$iopId);
        $idempotencyKey = trim((string)$idempotencyKey);
        if ($iopId === '') {
            throw new InvalidArgumentException('ctm_iop_id_required');
        }
        if ($idempotencyKey === '') {
            throw new InvalidArgumentException('ctm_idempotency_key_required');
        }

        $this->assertRequiredTables();
        $now = date('Y-m-d H:i:s');
        $leaseUntil = date('Y-m-d H:i:s', time() + $this->leaseSeconds);

        $this->db->trans_begin();
        try {
            $record = $this->lockIdempotencyRecord($iopId, $idempotencyKey);
            if (is_array($record) && isset($record['status']) && $record['status'] === self::STATUS_SUCCESS) {
                $this->db->trans_commit();
                return $this->decodeStoredResult(isset($record['result_json']) ? $record['result_json'] : null);
            }

            if (is_array($record) && isset($record['status']) && $record['status'] === self::STATUS_PROCESSING) {
                $owner = isset($record['lease_owner']) ? (string)$record['lease_owner'] : '';
                $until = isset($record['lease_until']) ? (string)$record['lease_until'] : '';
                if ($owner !== $this->leaseOwner && $until !== '' && strtotime($until) !== false && strtotime($until) > time()) {
                    throw new RuntimeException('ctm_idempotency_lease_active');
                }
            }

            if (!is_array($record)) {
                $this->insertIdempotencyRecord($iopId, $idempotencyKey, $now, $leaseUntil);
                $this->lockIdempotencyRecord($iopId, $idempotencyKey);
            } else {
                $this->markIdempotencyProcessing($idempotencyKey, $leaseUntil);
            }

            $this->acquireStreamLock($iopId, $now, $leaseUntil);
            $result = $operation();
            $this->releaseStreamLock($iopId);
            $this->markIdempotencySuccess($idempotencyKey, $result);

            if ($this->db->trans_status() === false) {
                throw new RuntimeException('ctm_transaction_failed');
            }

            $this->db->trans_commit();
            return $result;
        } catch (Exception $e) {
            if ($this->db->trans_status() !== false) {
                $this->markIdempotencyFailed($idempotencyKey, $e->getMessage());
            }
            $this->db->trans_rollback();
            throw $e;
        }
    }

    protected function assertRequiredTables(): void
    {
        $required = array($this->idempotencyTable, $this->streamLockTable, 'clinical_events');
        foreach ($required as $table) {
            if (!$this->db->table_exists($table)) {
                throw new RuntimeException('ctm_table_missing:' . $table);
            }
        }
    }

    protected function lockIdempotencyRecord($iopId, $idempotencyKey)
    {
        $q = $this->db->query(
            "SELECT * FROM `{$this->idempotencyTable}` WHERE idempotency_key = ? AND iop_id = ? LIMIT 1 FOR UPDATE",
            array($idempotencyKey, $iopId)
        );
        $row = $q ? $q->row_array() : null;
        return is_array($row) ? $row : null;
    }

    protected function insertIdempotencyRecord($iopId, $idempotencyKey, $now, $leaseUntil): void
    {
        $ok = $this->db->insert($this->idempotencyTable, array(
            'idempotency_key' => $idempotencyKey,
            'iop_id' => $iopId,
            'status' => self::STATUS_PROCESSING,
            'lease_owner' => $this->leaseOwner,
            'lease_until' => $leaseUntil,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        if (!$ok) {
            throw new RuntimeException('ctm_idempotency_insert_failed');
        }
    }

    protected function markIdempotencyProcessing($idempotencyKey, $leaseUntil): void
    {
        $this->db->where('idempotency_key', $idempotencyKey)->update($this->idempotencyTable, array(
            'status' => self::STATUS_PROCESSING,
            'lease_owner' => $this->leaseOwner,
            'lease_until' => $leaseUntil,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    protected function markIdempotencySuccess($idempotencyKey, $result): void
    {
        $this->db->where('idempotency_key', $idempotencyKey)->update($this->idempotencyTable, array(
            'status' => self::STATUS_SUCCESS,
            'result_json' => $this->encodeResult($result),
            'error_message' => null,
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    protected function markIdempotencyFailed($idempotencyKey, $message): void
    {
        $this->db->where('idempotency_key', $idempotencyKey)->update($this->idempotencyTable, array(
            'status' => self::STATUS_FAILED,
            'error_message' => substr((string)$message, 0, 1000),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    protected function acquireStreamLock($iopId, $now, $leaseUntil): void
    {
        $q = $this->db->query(
            "SELECT * FROM `{$this->streamLockTable}` WHERE iop_id = ? LIMIT 1 FOR UPDATE",
            array($iopId)
        );
        $row = $q ? $q->row_array() : null;
        if (is_array($row)) {
            $owner = isset($row['lease_owner']) ? (string)$row['lease_owner'] : '';
            $until = isset($row['lease_until']) ? (string)$row['lease_until'] : '';
            if ($owner !== $this->leaseOwner && $until !== '' && strtotime($until) !== false && strtotime($until) > time()) {
                throw new RuntimeException('ctm_stream_lease_active');
            }
            $this->db->where('iop_id', $iopId)->update($this->streamLockTable, array(
                'lease_owner' => $this->leaseOwner,
                'lease_until' => $leaseUntil,
                'updated_at' => $now,
            ));
            return;
        }

        $ok = $this->db->insert($this->streamLockTable, array(
            'iop_id' => $iopId,
            'lease_owner' => $this->leaseOwner,
            'lease_until' => $leaseUntil,
            'created_at' => $now,
            'updated_at' => $now,
        ));
        if (!$ok) {
            throw new RuntimeException('ctm_stream_lock_insert_failed');
        }
    }

    protected function releaseStreamLock($iopId): void
    {
        $this->db->where('iop_id', $iopId)->update($this->streamLockTable, array(
            'lease_until' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    protected function encodeResult($result)
    {
        if (is_object($result) && method_exists($result, 'toArray')) {
            return json_encode($result->toArray());
        }
        return json_encode($result);
    }

    protected function decodeStoredResult($json)
    {
        $decoded = json_decode((string)$json, true);
        return is_array($decoded) ? $decoded : $decoded;
    }

    protected function defaultLeaseOwner()
    {
        $host = function_exists('gethostname') ? gethostname() : 'unknown-host';
        return $host . ':' . getmypid();
    }
}
