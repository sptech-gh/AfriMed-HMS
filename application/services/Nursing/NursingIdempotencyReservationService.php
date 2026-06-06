<?php

class NursingIdempotencyReservationService
{
    protected $reservationRoot;
    protected $leaseSeconds;

    public function __construct($reservationRoot = null, $leaseSeconds = 120)
    {
        $this->reservationRoot = $reservationRoot !== null && trim((string)$reservationRoot) !== ''
            ? rtrim((string)$reservationRoot, DIRECTORY_SEPARATOR)
            : $this->defaultReservationRoot();
        $this->leaseSeconds = (int)$leaseSeconds > 0 ? (int)$leaseSeconds : 120;
    }

    public function reserve(ClinicalMutationRequest $request, array $idempotency)
    {
        if (!$this->ensureDirectory($this->reservationRoot)) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'RESERVATION_ROOT_NOT_WRITABLE',
                'shadow_reservation' => true,
                'errors' => array(array('field' => 'reservation_root', 'code' => 'RESERVATION_ROOT_NOT_WRITABLE', 'message' => $this->reservationRoot)),
            );
        }

        $path = $this->reservationPath($request);
        $lockPath = $path . '.lock';
        $lock = fopen($lockPath, 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'RESERVATION_LOCK_UNAVAILABLE',
                'shadow_reservation' => true,
                'errors' => array(array('field' => 'reservation_lock', 'code' => 'RESERVATION_LOCK_UNAVAILABLE', 'message' => $lockPath)),
            );
        }

        $existing = is_file($path) ? $this->readJson($path) : array();
        $now = time();
        $fingerprint = $request->operationFingerprint();
        $existingState = isset($existing['lease_state']) ? strtoupper((string)$existing['lease_state']) : '';
        $existingFingerprint = isset($existing['operation_fingerprint']) ? (string)$existing['operation_fingerprint'] : '';
        $existingExpiresAt = isset($existing['lease_expires_at']) ? strtotime((string)$existing['lease_expires_at']) : false;
        $active = $existingState === 'ACTIVE' && $existingExpiresAt !== false && $existingExpiresAt > $now;

        if ($active && $existingFingerprint !== '' && $existingFingerprint !== $fingerprint) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return array(
                'status' => 'BLOCK',
                'decision' => 'RESERVATION_COLLISION',
                'shadow_reservation' => true,
                'reservation_path' => $path,
                'existing_reservation' => $this->redactReservation($existing),
                'errors' => array(array('field' => 'idempotency_key', 'code' => 'RESERVATION_COLLISION', 'message' => 'Active reservation fingerprint differs from this request.')),
            );
        }

        if ($active && isset($existing['lease_owner']) && (string)$existing['lease_owner'] !== $request->requestId()) {
            flock($lock, LOCK_UN);
            fclose($lock);
            return array(
                'status' => 'BLOCK',
                'decision' => 'RESERVATION_LEASE_ACTIVE',
                'shadow_reservation' => true,
                'reservation_path' => $path,
                'existing_reservation' => $this->redactReservation($existing),
                'errors' => array(array('field' => 'idempotency_key', 'code' => 'RESERVATION_LEASE_ACTIVE', 'message' => 'Another request currently owns the idempotency reservation lease.')),
            );
        }

        $epoch = isset($existing['execution_epoch']) ? ((int)$existing['execution_epoch'] + 1) : 1;
        $leaseExpiresAt = gmdate('c', $now + $this->leaseSeconds);
        $reservation = array(
            'schema' => 'SPRINT2D_NURSING_IDEMPOTENCY_RESERVATION_V1',
            'reservation_id' => $this->reservationId($request, $epoch),
            'idempotency_key_hash' => hash('sha256', $request->idempotencyKey()),
            'operation_fingerprint' => $fingerprint,
            'payload_hash' => $request->payloadHash(),
            'truth_hash' => $request->truthHash(),
            'patient_no' => $request->patientNo(),
            'encounter_id' => $request->encounterId(),
            'action_type' => $request->actionType(),
            'lease_owner' => $request->requestId(),
            'lease_state' => 'ACTIVE',
            'lease_started_at' => gmdate('c', $now),
            'lease_expires_at' => $leaseExpiresAt,
            'lease_seconds' => $this->leaseSeconds,
            'recovery_token' => hash('sha256', $request->requestId() . '|' . $fingerprint . '|' . $epoch),
            'execution_epoch' => $epoch,
            'source_classification' => isset($idempotency['classification']) ? $idempotency['classification'] : null,
            'previous_reservation' => $this->redactReservation($existing),
            'shadow_reservation' => true,
            'created_at_utc' => isset($existing['created_at_utc']) ? $existing['created_at_utc'] : gmdate('c', $now),
            'updated_at_utc' => gmdate('c', $now),
        );
        $reservation['reservation_hash'] = $this->hashReservation($reservation);
        $this->writeJson($path, $reservation);

        flock($lock, LOCK_UN);
        fclose($lock);

        return array(
            'status' => 'PASS',
            'decision' => $existingState === 'ACTIVE' && !$active ? 'RESERVATION_RECOVERED_EXPIRED_LEASE' : 'RESERVATION_ACQUIRED',
            'shadow_reservation' => true,
            'reservation_path' => $path,
            'reservation' => $this->redactReservation($reservation),
            'errors' => array(),
            'warnings' => array(),
        );
    }

    public function release(ClinicalMutationRequest $request, array $reservationDecision, $releaseState = 'RELEASED')
    {
        $path = isset($reservationDecision['reservation_path']) ? (string)$reservationDecision['reservation_path'] : $this->reservationPath($request);
        if (!is_file($path)) {
            return array('status' => 'SKIPPED', 'decision' => 'RESERVATION_RECORD_NOT_FOUND', 'shadow_reservation' => true);
        }

        $record = $this->readJson($path);
        if (!is_array($record) || count($record) === 0) {
            return array('status' => 'SKIPPED', 'decision' => 'RESERVATION_RECORD_UNREADABLE', 'shadow_reservation' => true);
        }

        $record['lease_state'] = strtoupper((string)$releaseState) === 'EXPIRED' ? 'EXPIRED' : 'RELEASED';
        $record['released_by'] = $request->requestId();
        $record['released_at_utc'] = gmdate('c');
        $record['updated_at_utc'] = gmdate('c');
        $record['reservation_hash'] = $this->hashReservation($record);
        $this->writeJson($path, $record);

        return array(
            'status' => 'PASS',
            'decision' => 'RESERVATION_RELEASED',
            'shadow_reservation' => true,
            'reservation_path' => $path,
            'reservation' => $this->redactReservation($record),
        );
    }

    public function verifyRecoveryContinuation(ClinicalMutationRequest $request, $recoveryToken, $executionEpoch)
    {
        $path = $this->reservationPath($request);
        if (!is_file($path)) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'LEASE_RECOVERY_BLOCKED',
                'reason' => 'RESERVATION_RECORD_NOT_FOUND',
                'shadow_reservation' => true,
            );
        }

        $record = $this->readJson($path);
        if (!is_array($record) || count($record) === 0) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'LEASE_RECOVERY_BLOCKED',
                'reason' => 'RESERVATION_RECORD_UNREADABLE',
                'shadow_reservation' => true,
            );
        }

        $currentEpoch = isset($record['execution_epoch']) ? (int)$record['execution_epoch'] : 0;
        $requestedEpoch = (int)$executionEpoch;
        if ($requestedEpoch < $currentEpoch) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'STALE_EXECUTION_REJECTED',
                'reason' => 'REQUESTED_EPOCH_BEHIND_CURRENT_RESERVATION',
                'current_epoch' => $currentEpoch,
                'requested_epoch' => $requestedEpoch,
                'shadow_reservation' => true,
            );
        }

        if (!isset($record['recovery_token']) || (string)$record['recovery_token'] !== (string)$recoveryToken) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'LEASE_RECOVERY_BLOCKED',
                'reason' => 'RECOVERY_TOKEN_MISMATCH',
                'current_epoch' => $currentEpoch,
                'requested_epoch' => $requestedEpoch,
                'shadow_reservation' => true,
            );
        }

        $state = isset($record['lease_state']) ? strtoupper((string)$record['lease_state']) : '';
        $expiresAt = isset($record['lease_expires_at']) ? strtotime((string)$record['lease_expires_at']) : false;
        if ($state === 'ACTIVE' && $expiresAt !== false && $expiresAt > time()) {
            return array(
                'status' => 'BLOCK',
                'decision' => 'LEASE_RECOVERY_BLOCKED',
                'reason' => 'LEASE_STILL_ACTIVE',
                'current_epoch' => $currentEpoch,
                'requested_epoch' => $requestedEpoch,
                'shadow_reservation' => true,
            );
        }

        return array(
            'status' => 'PASS',
            'decision' => $state === 'ACTIVE' ? 'LEASE_RECOVERABLE' : 'RECOVERY_CONTINUATION_ALLOWED',
            'reason' => $state === 'ACTIVE' ? 'LEASE_EXPIRED' : 'NON_ACTIVE_RESERVATION_CAN_CONTINUE_WITH_TOKEN',
            'current_epoch' => $currentEpoch,
            'requested_epoch' => $requestedEpoch,
            'reservation' => $this->redactReservation($record),
            'shadow_reservation' => true,
        );
    }

    protected function reservationPath(ClinicalMutationRequest $request)
    {
        return $this->reservationRoot . DIRECTORY_SEPARATOR . $this->safeSegment($request->encounterId()) . '_' . substr(hash('sha256', $request->idempotencyKey()), 0, 24) . '.json';
    }

    protected function reservationId(ClinicalMutationRequest $request, $epoch)
    {
        return 'RSV_' . substr(hash('sha256', $request->encounterId() . '|' . $request->idempotencyKey() . '|' . $request->requestId() . '|' . $epoch), 0, 32);
    }

    protected function redactReservation(array $reservation)
    {
        if (count($reservation) === 0) {
            return null;
        }
        $allowed = array('reservation_id', 'idempotency_key_hash', 'operation_fingerprint', 'patient_no', 'encounter_id', 'action_type', 'lease_owner', 'lease_state', 'lease_started_at', 'lease_expires_at', 'lease_seconds', 'recovery_token', 'execution_epoch', 'source_classification', 'shadow_reservation', 'reservation_hash', 'updated_at_utc');
        $out = array();
        foreach ($allowed as $key) {
            if (array_key_exists($key, $reservation)) {
                $out[$key] = $reservation[$key];
            }
        }
        return $out;
    }

    protected function hashReservation(array $reservation)
    {
        $copy = $reservation;
        unset($copy['reservation_hash']);
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

    protected function defaultReservationRoot()
    {
        $repoRoot = dirname(dirname(dirname(dirname(__FILE__))));
        return $repoRoot . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'sprint2d' . DIRECTORY_SEPARATOR . 'evidence' . DIRECTORY_SEPARATOR . 'nursing_idempotency_reservations';
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
