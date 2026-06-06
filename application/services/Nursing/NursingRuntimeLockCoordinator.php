<?php

class NursingRuntimeLockCoordinator
{
    protected $lockRoot;
    protected $leaseSeconds;
    protected $namespace;

    public function __construct($lockRoot = null, $leaseSeconds = 120, $namespace = 'SPRINT2D_SHADOW_LOCK')
    {
        $this->lockRoot = $lockRoot !== null && trim((string)$lockRoot) !== ''
            ? rtrim((string)$lockRoot, DIRECTORY_SEPARATOR)
            : $this->defaultLockRoot();
        $this->leaseSeconds = (int)$leaseSeconds > 0 ? (int)$leaseSeconds : 120;
        $this->namespace = trim((string)$namespace) !== '' ? trim((string)$namespace) : 'SPRINT2D_SHADOW_LOCK';
    }

    public function buildOrderedVitalsLockPlan(ClinicalMutationRequest $request)
    {
        $truthHash = $request->truthHash() !== '' ? $request->truthHash() : 'TRUTH_HASH_NOT_SUPPLIED';
        $windowBucket = gmdate('Y-m-d\TH:i');
        return array(
            array('order' => 1, 'lock_scope' => 'patient_encounter_lock', 'lock_key' => $request->patientNo() . '|' . $request->encounterId(), 'strategy' => 'shadow_encounter_lock'),
            array('order' => 2, 'lock_scope' => 'vitals_window_lock', 'lock_key' => $request->patientNo() . '|' . $request->encounterId() . '|' . $windowBucket, 'strategy' => 'shadow_vitals_window_lock'),
            array('order' => 3, 'lock_scope' => 'truth_context_lock', 'lock_key' => $request->patientNo() . '|' . $request->encounterId() . '|' . $truthHash, 'strategy' => 'shadow_truth_context_lock'),
        );
    }

    public function acquireVitalsLocks(ClinicalMutationRequest $request, array $reservationDecision)
    {
        $ownerCheck = $this->assertReservationOwner($request, $reservationDecision);
        if ($ownerCheck['status'] === 'BLOCKED') {
            return array($ownerCheck);
        }

        $acquired = array();
        $results = array();
        foreach ($this->buildOrderedVitalsLockPlan($request) as $lock) {
            $decision = $this->acquireLock($request, $lock, $reservationDecision);
            $results[] = $decision;
            if ($decision['status'] === 'PASS') {
                $acquired[] = $lock;
            } else {
                $this->releaseVitalsLocks($request, $reservationDecision, $acquired);
                return $results;
            }
        }
        return $results;
    }

    public function releaseVitalsLocks(ClinicalMutationRequest $request, array $reservationDecision, array $locks = null)
    {
        $plan = $locks === null ? $this->buildOrderedVitalsLockPlan($request) : $locks;
        $released = array();
        for ($i = count($plan) - 1; $i >= 0; $i--) {
            $released[] = $this->releaseLock($request, $plan[$i], $reservationDecision);
        }
        return array(
            'status' => 'PASS',
            'decision' => 'SHADOW_LOCKS_RELEASED',
            'namespace' => $this->namespace,
            'released' => $released,
        );
    }

    protected function acquireLock(ClinicalMutationRequest $request, array $lock, array $reservationDecision)
    {
        if (!$this->ensureDirectory($this->lockRoot)) {
            return array('status' => 'BLOCKED', 'decision' => 'LOCK_CONFLICT_BLOCKED', 'reason' => 'SHADOW_LOCK_ROOT_NOT_WRITABLE', 'lock' => $lock, 'namespace' => $this->namespace);
        }

        $path = $this->lockPath($lock);
        $guardPath = $path . '.guard';
        $guard = fopen($guardPath, 'c+');
        if (!$guard || !flock($guard, LOCK_EX)) {
            return array('status' => 'BLOCKED', 'decision' => 'LOCK_BUSY_RETRYABLE', 'reason' => 'SHADOW_LOCK_GUARD_BUSY', 'lock' => $lock, 'namespace' => $this->namespace);
        }

        $existing = is_file($path) ? $this->readJson($path) : array();
        $owner = $request->requestId();
        $now = time();
        $existingOwner = isset($existing['lock_owner']) ? (string)$existing['lock_owner'] : '';
        $existingState = isset($existing['lock_state']) ? strtoupper((string)$existing['lock_state']) : '';
        $existingExpiresAt = isset($existing['lock_expires_at']) ? strtotime((string)$existing['lock_expires_at']) : false;
        $active = $existingState === 'ACTIVE' && $existingExpiresAt !== false && $existingExpiresAt > $now;

        if ($active && $existingOwner !== '' && $existingOwner !== $owner) {
            flock($guard, LOCK_UN);
            fclose($guard);
            return array('status' => 'BLOCKED', 'decision' => 'LOCK_BUSY_RETRYABLE', 'reason' => 'ACTIVE_FOREIGN_SHADOW_LOCK', 'lock' => $lock, 'namespace' => $this->namespace, 'existing_lock' => $this->redactLock($existing));
        }

        $decision = 'LOCK_GRANTED';
        if ($existingState === 'ACTIVE' && !$active && $existingOwner !== '' && $existingOwner !== $owner) {
            $decision = 'LOCK_STALE_RECOVERABLE';
        }

        $record = array(
            'schema' => 'SPRINT2D_NURSING_SHADOW_LOCK_V1',
            'namespace' => $this->namespace,
            'lock_id' => $this->lockId($lock),
            'lock_scope' => $lock['lock_scope'],
            'lock_key_hash' => hash('sha256', $lock['lock_key']),
            'lock_order' => $lock['order'],
            'lock_owner' => $owner,
            'reservation_owner' => $this->reservationOwner($reservationDecision),
            'reservation_id' => $this->reservationId($reservationDecision),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'action_type' => $request->actionType(),
            'lock_state' => 'ACTIVE',
            'lock_started_at' => gmdate('c', $now),
            'lock_expires_at' => gmdate('c', $now + $this->leaseSeconds),
            'lease_seconds' => $this->leaseSeconds,
            'previous_lock' => $this->redactLock($existing),
            'created_at_utc' => isset($existing['created_at_utc']) ? $existing['created_at_utc'] : gmdate('c', $now),
            'updated_at_utc' => gmdate('c', $now),
        );
        $record['lock_hash'] = $this->hashLock($record);
        $this->writeJson($path, $record);

        flock($guard, LOCK_UN);
        fclose($guard);

        return array('status' => 'PASS', 'decision' => $decision, 'reason' => $decision === 'LOCK_STALE_RECOVERABLE' ? 'EXPIRED_FOREIGN_LOCK_RECLAIMED' : 'SHADOW_LOCK_ACQUIRED', 'lock' => $lock, 'namespace' => $this->namespace, 'lock_path' => $path, 'record' => $this->redactLock($record));
    }

    protected function releaseLock(ClinicalMutationRequest $request, array $lock, array $reservationDecision)
    {
        $path = $this->lockPath($lock);
        if (!is_file($path)) {
            return array('status' => 'SKIPPED', 'decision' => 'SHADOW_LOCK_NOT_FOUND', 'lock' => $lock, 'namespace' => $this->namespace);
        }
        $record = $this->readJson($path);
        if (!is_array($record) || count($record) === 0) {
            return array('status' => 'SKIPPED', 'decision' => 'SHADOW_LOCK_UNREADABLE', 'lock' => $lock, 'namespace' => $this->namespace);
        }
        if (isset($record['lock_owner']) && (string)$record['lock_owner'] !== $request->requestId()) {
            return array('status' => 'BLOCKED', 'decision' => 'LOCK_CONFLICT_BLOCKED', 'reason' => 'RELEASE_OWNER_MISMATCH', 'lock' => $lock, 'namespace' => $this->namespace, 'existing_lock' => $this->redactLock($record));
        }
        $record['lock_state'] = 'RELEASED';
        $record['released_by'] = $request->requestId();
        $record['released_at_utc'] = gmdate('c');
        $record['updated_at_utc'] = gmdate('c');
        $record['lock_hash'] = $this->hashLock($record);
        $this->writeJson($path, $record);
        return array('status' => 'PASS', 'decision' => 'SHADOW_LOCK_RELEASED', 'lock' => $lock, 'namespace' => $this->namespace, 'record' => $this->redactLock($record));
    }

    protected function assertReservationOwner(ClinicalMutationRequest $request, array $reservationDecision)
    {
        $owner = $this->reservationOwner($reservationDecision);
        if ($owner === '' || $owner !== $request->requestId()) {
            return array('status' => 'BLOCKED', 'decision' => 'LOCK_CONFLICT_BLOCKED', 'reason' => 'RESERVATION_OWNER_MISMATCH', 'namespace' => $this->namespace, 'request_id' => $request->requestId(), 'reservation_owner' => $owner);
        }
        return array('status' => 'PASS', 'decision' => 'RESERVATION_OWNER_MATCHED', 'namespace' => $this->namespace);
    }

    protected function reservationOwner(array $reservationDecision)
    {
        if (isset($reservationDecision['reservation']) && is_array($reservationDecision['reservation']) && isset($reservationDecision['reservation']['lease_owner'])) {
            return (string)$reservationDecision['reservation']['lease_owner'];
        }
        return '';
    }

    protected function reservationId(array $reservationDecision)
    {
        if (isset($reservationDecision['reservation']) && is_array($reservationDecision['reservation']) && isset($reservationDecision['reservation']['reservation_id'])) {
            return (string)$reservationDecision['reservation']['reservation_id'];
        }
        return '';
    }

    protected function lockPath(array $lock)
    {
        return $this->lockRoot . DIRECTORY_SEPARATOR . $this->namespace . '_' . $this->safeSegment($lock['lock_scope']) . '_' . substr(hash('sha256', $lock['lock_key']), 0, 24) . '.json';
    }

    protected function lockId(array $lock)
    {
        return 'LCK_' . substr(hash('sha256', $this->namespace . '|' . $lock['lock_scope'] . '|' . $lock['lock_key']), 0, 32);
    }

    protected function redactLock(array $record)
    {
        if (count($record) === 0) {
            return null;
        }
        $allowed = array('namespace', 'lock_id', 'lock_scope', 'lock_key_hash', 'lock_order', 'lock_owner', 'reservation_owner', 'reservation_id', 'patient_no', 'encounter_id', 'action_type', 'lock_state', 'lock_started_at', 'lock_expires_at', 'lease_seconds', 'lock_hash', 'updated_at_utc');
        $out = array();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $record)) {
                $out[$key] = $record[$key];
            }
        }
        return $out;
    }

    protected function hashLock(array $record)
    {
        $copy = $record;
        unset($copy['lock_hash']);
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

    protected function defaultLockRoot()
    {
        $repoRoot = dirname(dirname(dirname(dirname(__FILE__))));
        return $repoRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'sprint2d' . DIRECTORY_SEPARATOR . 'evidence' . DIRECTORY_SEPARATOR . 'nursing_shadow_locks';
    }

    protected function ensureDirectory($path)
    {
        if (is_dir($path)) {
            return is_writable($path);
        }
        return mkdir($path, 0775, true);
    }

    protected function readJson($path)
    {
        $decoded = json_decode((string)file_get_contents($path), true);
        return is_array($decoded) ? $decoded : array();
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
