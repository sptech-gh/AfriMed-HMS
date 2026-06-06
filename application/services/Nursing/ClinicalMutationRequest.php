<?php

class ClinicalMutationRequest
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->data['request_id'] = $this->stringValue('request_id') !== '' ? $this->stringValue('request_id') : $this->newRequestId();
        $this->data['requested_at_utc'] = $this->stringValue('requested_at_utc') !== '' ? $this->stringValue('requested_at_utc') : gmdate('c');
        $this->data['payload'] = isset($this->data['payload']) && is_array($this->data['payload']) ? $this->data['payload'] : array();
        $this->data['client_context'] = isset($this->data['client_context']) && is_array($this->data['client_context']) ? $this->data['client_context'] : array();
        $this->data['actor_capabilities'] = isset($this->data['actor_capabilities']) && is_array($this->data['actor_capabilities']) ? $this->data['actor_capabilities'] : array();
        $this->data['truth_state_reference'] = isset($this->data['truth_state_reference']) && is_array($this->data['truth_state_reference']) ? $this->data['truth_state_reference'] : array();
        $this->data['payload_hash'] = $this->stringValue('payload_hash') !== '' ? $this->stringValue('payload_hash') : $this->generatePayloadHash();
    }

    public static function fromArray(array $data)
    {
        return new self($data);
    }

    public function toArray()
    {
        return $this->data;
    }

    public function requestId()
    {
        return $this->stringValue('request_id');
    }

    public function idempotencyKey()
    {
        return $this->stringValue('idempotency_key');
    }

    public function actionType()
    {
        return $this->stringValue('action_type');
    }

    public function patientNo()
    {
        return $this->stringValue('patient_no');
    }

    public function encounterId()
    {
        return $this->stringValue('encounter_id');
    }

    public function actorUserId()
    {
        return $this->stringValue('actor_user_id');
    }

    public function actorRole()
    {
        return $this->stringValue('actor_role');
    }

    public function payload()
    {
        return isset($this->data['payload']) && is_array($this->data['payload']) ? $this->data['payload'] : array();
    }

    public function payloadHash()
    {
        return $this->stringValue('payload_hash');
    }

    public function truthHash()
    {
        $truth = isset($this->data['truth_state_reference']) && is_array($this->data['truth_state_reference']) ? $this->data['truth_state_reference'] : array();
        return isset($truth['truth_hash']) ? trim((string)$truth['truth_hash']) : '';
    }

    public function sourceController()
    {
        return $this->stringValue('source_controller');
    }

    public function sourceMethod()
    {
        return $this->stringValue('source_method');
    }

    public function operationFingerprint()
    {
        return hash('sha256', json_encode(array(
            'action_type' => $this->actionType(),
            'patient_no' => $this->patientNo(),
            'encounter_id' => $this->encounterId(),
            'actor_user_id' => $this->actorUserId(),
            'payload_hash' => $this->payloadHash(),
            'truth_hash' => $this->truthHash(),
        )));
    }

    public function validateEnvelope()
    {
        $errors = array();
        if ($this->idempotencyKey() === '') {
            $errors[] = array('field' => 'idempotency_key', 'code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'Missing idempotency key.');
        }
        if ($this->actionType() === '') {
            $errors[] = array('field' => 'action_type', 'code' => 'ACTION_TYPE_REQUIRED', 'message' => 'Missing action type.');
        }
        if ($this->patientNo() === '') {
            $errors[] = array('field' => 'patient_no', 'code' => 'PATIENT_NO_REQUIRED', 'message' => 'Missing patient number.');
        }
        if ($this->encounterId() === '') {
            $errors[] = array('field' => 'encounter_id', 'code' => 'ENCOUNTER_ID_REQUIRED', 'message' => 'Missing encounter id.');
        }
        if ($this->actorUserId() === '') {
            $errors[] = array('field' => 'actor_user_id', 'code' => 'ACTOR_USER_ID_REQUIRED', 'message' => 'Missing actor user id.');
        }
        return $errors;
    }

    protected function stringValue($key)
    {
        return isset($this->data[$key]) ? trim((string)$this->data[$key]) : '';
    }

    protected function generatePayloadHash()
    {
        return hash('sha256', json_encode($this->normalizedPayload($this->payload())));
    }

    protected function normalizedPayload(array $payload)
    {
        ksort($payload);
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->normalizedPayload($value);
            } elseif (is_string($value)) {
                $payload[$key] = trim($value);
            }
        }
        return $payload;
    }

    protected function newRequestId()
    {
        if (function_exists('random_bytes')) {
            return bin2hex(random_bytes(16));
        }
        return str_replace('.', '', uniqid('cmr_', true));
    }
}
