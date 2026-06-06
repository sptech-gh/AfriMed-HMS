<?php

class NursingLockManager
{
    protected $db;
    protected $streamLockTable = 'clinical_stream_locks';
    protected $leaseSeconds;
    protected $leaseOwner;
    protected $dryRun;

    public function __construct($db, $leaseSeconds = 60, $leaseOwner = null, $dryRun = true)
    {
        $this->db = $db;
        $this->leaseSeconds = (int)$leaseSeconds > 0 ? (int)$leaseSeconds : 60;
        $this->leaseOwner = $leaseOwner !== null && $leaseOwner !== '' ? (string)$leaseOwner : $this->defaultLeaseOwner();
        $this->dryRun = (bool)$dryRun;
    }

    public function buildVitalsLockPlan(ClinicalMutationRequest $request)
    {
        $truthHash = $request->truthHash() !== '' ? $request->truthHash() : 'TRUTH_HASH_NOT_SUPPLIED';
        $windowBucket = gmdate('Y-m-d\TH:i');
        return array(
            array(
                'lock_scope' => 'patient_encounter_lock',
                'lock_key' => $request->patientNo() . '|' . $request->encounterId(),
                'strategy' => 'encounter_stream_lock',
            ),
            array(
                'lock_scope' => 'vitals_window_lock',
                'lock_key' => $request->patientNo() . '|' . $request->encounterId() . '|' . $windowBucket,
                'strategy' => 'duplicate_window_detection',
            ),
            array(
                'lock_scope' => 'truth_context_lock',
                'lock_key' => $request->patientNo() . '|' . $request->encounterId() . '|' . $truthHash,
                'strategy' => 'truth_hash_compare_before_mutation',
            ),
        );
    }

    public function acquireEncounterLock(ClinicalMutationRequest $request)
    {
        $decision = array(
            'lock_scope' => 'patient_encounter_lock',
            'lock_key' => $request->patientNo() . '|' . $request->encounterId(),
            'status' => 'NOT_ACQUIRED',
            'decision' => 'DRY_RUN_LOCK_NOT_ACQUIRED',
            'lease_owner' => $this->leaseOwner,
            'lease_seconds' => $this->leaseSeconds,
            'dry_run' => $this->dryRun,
            'table' => $this->streamLockTable,
            'errors' => array(),
        );

        if (!$this->tableExists($this->streamLockTable)) {
            $decision['status'] = 'BLOCKED';
            $decision['decision'] = 'LOCK_TABLE_MISSING';
            $decision['errors'][] = 'lock_table_missing:' . $this->streamLockTable;
            return $decision;
        }

        $existing = $this->readEncounterLock($request->encounterId());
        $decision['existing_lock'] = $existing;
        if ($this->isActiveForeignLease($existing)) {
            $decision['status'] = 'CONFLICT';
            $decision['decision'] = 'ACTIVE_FOREIGN_LEASE';
            return $decision;
        }

        if ($this->dryRun) {
            $decision['status'] = 'PASS';
            $decision['decision'] = 'DRY_RUN_WOULD_ACQUIRE';
            return $decision;
        }

        $now = date('Y-m-d H:i:s');
        $leaseUntil = date('Y-m-d H:i:s', time() + $this->leaseSeconds);
        if (is_array($existing)) {
            $ok = $this->db->where('iop_id', $request->encounterId())->update($this->streamLockTable, array(
                'lease_owner' => $this->leaseOwner,
                'lease_until' => $leaseUntil,
                'updated_at' => $now,
            ));
        } else {
            $ok = $this->db->insert($this->streamLockTable, array(
                'iop_id' => $request->encounterId(),
                'lease_owner' => $this->leaseOwner,
                'lease_until' => $leaseUntil,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        $decision['status'] = $ok ? 'ACQUIRED' : 'FAILED';
        $decision['decision'] = $ok ? 'LOCK_ACQUIRED' : 'LOCK_ACQUIRE_FAILED';
        return $decision;
    }

    public function acquireVitalsWindowLock(ClinicalMutationRequest $request)
    {
        return array(
            'lock_scope' => 'vitals_window_lock',
            'lock_key' => $request->patientNo() . '|' . $request->encounterId() . '|' . gmdate('Y-m-d\TH:i'),
            'status' => 'PASS',
            'decision' => $this->dryRun ? 'DRY_RUN_WOULD_EVALUATE_DUPLICATE_WINDOW' : 'EVALUATE_DUPLICATE_WINDOW_IN_ENGINE',
            'dry_run' => $this->dryRun,
        );
    }

    public function acquireTruthContextLock(ClinicalMutationRequest $request)
    {
        return array(
            'lock_scope' => 'truth_context_lock',
            'lock_key' => $request->patientNo() . '|' . $request->encounterId() . '|' . ($request->truthHash() !== '' ? $request->truthHash() : 'TRUTH_HASH_NOT_SUPPLIED'),
            'status' => 'PASS',
            'decision' => $this->dryRun ? 'DRY_RUN_WOULD_VALIDATE_TRUTH_HASH' : 'VALIDATE_TRUTH_HASH_BEFORE_MUTATION',
            'dry_run' => $this->dryRun,
        );
    }

    public function acquireVitalsLocks(ClinicalMutationRequest $request)
    {
        return array(
            $this->acquireEncounterLock($request),
            $this->acquireVitalsWindowLock($request),
            $this->acquireTruthContextLock($request),
        );
    }

    public function releaseLocks(ClinicalMutationRequest $request)
    {
        $decision = array(
            'status' => 'NOT_RELEASED',
            'decision' => 'DRY_RUN_LOCK_NOT_RELEASED',
            'encounter_id' => $request->encounterId(),
            'dry_run' => $this->dryRun,
        );
        if ($this->dryRun) {
            $decision['status'] = 'PASS';
            return $decision;
        }
        if (!$this->tableExists($this->streamLockTable)) {
            $decision['status'] = 'BLOCKED';
            $decision['decision'] = 'LOCK_TABLE_MISSING';
            return $decision;
        }
        $ok = $this->db->where('iop_id', $request->encounterId())->update($this->streamLockTable, array(
            'lease_until' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
        $decision['status'] = $ok ? 'RELEASED' : 'FAILED';
        $decision['decision'] = $ok ? 'LOCK_RELEASED' : 'LOCK_RELEASE_FAILED';
        return $decision;
    }

    public function recoverExpiredLocks($encounterId = '')
    {
        $decision = array(
            'status' => 'NOT_RUN',
            'decision' => 'DRY_RUN_RECOVERY_NOT_MUTATED',
            'encounter_id' => (string)$encounterId,
            'dry_run' => $this->dryRun,
            'recoverable_count' => 0,
        );
        if (!$this->tableExists($this->streamLockTable)) {
            $decision['status'] = 'BLOCKED';
            $decision['decision'] = 'LOCK_TABLE_MISSING';
            return $decision;
        }
        $this->db->from($this->streamLockTable);
        $this->db->where('lease_until <', date('Y-m-d H:i:s'));
        if ($encounterId !== '') {
            $this->db->where('iop_id', (string)$encounterId);
        }
        $decision['recoverable_count'] = (int)$this->db->count_all_results();
        $decision['status'] = 'PASS';
        if (!$this->dryRun) {
            $decision['decision'] = 'EXPIRED_LOCKS_IDENTIFIED_FOR_RECOVERY';
        }
        return $decision;
    }

    protected function readEncounterLock($encounterId)
    {
        if (!$this->tableExists($this->streamLockTable)) {
            return null;
        }
        $q = $this->db->get_where($this->streamLockTable, array('iop_id' => (string)$encounterId), 1);
        $row = $q ? $q->row_array() : null;
        return is_array($row) ? $row : null;
    }

    protected function isActiveForeignLease($row)
    {
        if (!is_array($row)) {
            return false;
        }
        $owner = isset($row['lease_owner']) ? (string)$row['lease_owner'] : '';
        $until = isset($row['lease_until']) ? (string)$row['lease_until'] : '';
        if ($owner === '' || $owner === $this->leaseOwner || $until === '') {
            return false;
        }
        $ts = strtotime($until);
        return $ts !== false && $ts > time();
    }

    protected function tableExists($table)
    {
        return $this->db && method_exists($this->db, 'table_exists') && $this->db->table_exists($table);
    }

    protected function defaultLeaseOwner()
    {
        $host = function_exists('gethostname') ? gethostname() : 'unknown-host';
        return 'hms-nursing-lock:' . $host . ':' . getmypid();
    }
}
