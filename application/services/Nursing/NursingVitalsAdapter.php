<?php

class NursingVitalsAdapter
{
	protected $db;
	protected $nurseEnhancementModel;

	public function __construct($db, $nurseEnhancementModel = null)
	{
		$this->db = $db;
		$this->nurseEnhancementModel = $nurseEnhancementModel;
	}

	public function record($iopId, $patientNo, $actorUserId, $idempotencyKey, array $input, $transactions)
	{
		$normalized = $this->normalize($input);
		$validation = $this->validate($iopId, $patientNo, $actorUserId, $idempotencyKey, $normalized);
		if ($validation['status'] !== 'ok') {
			return $validation;
		}

		$result = $transactions->execute($iopId, $idempotencyKey, function () use ($iopId, $patientNo, $actorUserId, $normalized) {
			return $this->persist($iopId, $patientNo, $actorUserId, $normalized);
		});

		if (!is_array($result)) {
			$result = array('status' => 'success', 'result' => $result);
		}
		$result['warnings'] = isset($validation['warnings']) ? $validation['warnings'] : array();
		$result['normalized_values'] = $normalized;
		return $result;
	}

	protected function normalize(array $input)
	{
		$out = array();
		$out['bp'] = $this->stringOrNull($input, 'bp');
		$out['temperature'] = $this->numericOrNull($input, 'temperature');
		$out['pulse_rate'] = $this->numericOrNull($input, 'pulse_rate');
		$out['respiration'] = $this->numericOrNull($input, 'respiration');
		$out['weight'] = $this->numericOrNull($input, 'weight');
		$out['height'] = $this->numericOrNull($input, 'height');
		$out['spo2'] = $this->numericOrNull($input, 'spo2');
		$out['blood_sugar'] = $this->numericOrNull($input, 'blood_sugar');
		$out['pain_score'] = $this->numericOrNull($input, 'pain_score');
		return $out;
	}

	protected function validate($iopId, $patientNo, $actorUserId, $idempotencyKey, array &$normalized)
	{
		$errors = array();
		$warnings = array();

		if (trim((string)$iopId) === '' || trim((string)$patientNo) === '') {
			$errors[] = array('field' => 'context', 'code' => 'MISSING_PATIENT_CONTEXT', 'message' => 'Missing patient context.');
		}
		if (trim((string)$actorUserId) === '') {
			$errors[] = array('field' => 'context', 'code' => 'MISSING_USER_CONTEXT', 'message' => 'Missing user context.');
		}
		if (trim((string)$idempotencyKey) === '') {
			$errors[] = array('field' => 'idempotency_key', 'code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'Missing idempotency key.');
		}

		foreach (array('temperature','pulse_rate','respiration','weight','height','spo2','blood_sugar','pain_score') as $f) {
			if ($normalized[$f] === false) {
				$errors[] = array('field' => $f, 'code' => 'NON_NUMERIC_OR_NEGATIVE', 'message' => 'Value must be numeric and non-negative.');
			}
		}

		$corePresent = false;
		if ($normalized['bp'] !== null) $corePresent = true;
		if ($normalized['temperature'] !== null && $normalized['temperature'] !== false) $corePresent = true;
		if ($normalized['pulse_rate'] !== null && $normalized['pulse_rate'] !== false) $corePresent = true;
		if ($normalized['respiration'] !== null && $normalized['respiration'] !== false) $corePresent = true;
		if ($normalized['weight'] !== null && $normalized['weight'] !== false) $corePresent = true;
		if (!$corePresent) {
			$errors[] = array('field' => 'core', 'code' => 'NO_VITALS_PROVIDED', 'message' => 'At least one vital sign is required.');
		}

		if ($normalized['bp'] !== null) {
			$bp = trim((string)$normalized['bp']);
			if (!preg_match('/^\s*(\d{1,3})\s*\/\s*(\d{1,3})\s*$/', $bp, $m)) {
				$errors[] = array('field' => 'bp', 'code' => 'BP_FORMAT_INVALID', 'message' => 'Blood pressure must be in systolic/diastolic format.' );
			} else {
				$sys = (int)$m[1];
				$dia = (int)$m[2];
				$normalized['bp'] = $sys . '/' . $dia;
				if ($sys < 30 || $sys > 300) {
					$errors[] = array('field' => 'bp', 'code' => 'BP_SYSTOLIC_OUT_OF_RANGE', 'message' => 'Systolic BP is outside allowed entry limits.');
				}
				if ($dia < 20 || $dia > 200) {
					$errors[] = array('field' => 'bp', 'code' => 'BP_DIASTOLIC_OUT_OF_RANGE', 'message' => 'Diastolic BP is outside allowed entry limits.');
				}
				if ($sys < 90 || $sys >= 180) {
					$warnings[] = array('field' => 'bp', 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this blood pressure value before saving.');
				}
				if ($dia < 60 || $dia >= 120) {
					$warnings[] = array('field' => 'bp', 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this blood pressure value before saving.');
				}
			}
		}

		$this->numericGuardrails('temperature', $normalized['temperature'], 20, 45, 35, 39, $errors, $warnings);
		$this->numericGuardrails('pulse_rate', $normalized['pulse_rate'], 0, 300, 40, 130, $errors, $warnings);
		$this->numericGuardrails('respiration', $normalized['respiration'], 0, 100, 8, 30, $errors, $warnings);
		$this->numericGuardrails('spo2', $normalized['spo2'], 0, 100, 90, null, $errors, $warnings);
		$this->numericGuardrails('weight', $normalized['weight'], 0, 500, null, null, $errors, $warnings);
		$this->numericGuardrails('height', $normalized['height'], 0, 250, null, null, $errors, $warnings);
		$this->numericGuardrails('blood_sugar', $normalized['blood_sugar'], 0, 1000, null, null, $errors, $warnings);
		$this->numericGuardrails('pain_score', $normalized['pain_score'], 0, 10, null, 8, $errors, $warnings);

		if (count($errors) > 0) {
			return array(
				'status' => 'validation_error',
				'errors' => $errors,
				'warnings' => $warnings
			);
		}

		return array('status' => 'ok', 'warnings' => $warnings);
	}

	protected function persist($iopId, $patientNo, $actorUserId, array $normalized)
	{
		if (!$this->db->table_exists('iop_vital_parameters') || !$this->db->table_exists('patient_details_iop')) {
			throw new RuntimeException('vitals_tables_missing');
		}

		$now = date('Y-m-d H:i:s');
		$dDate = date('Y-m-d');

		$row = array(
			'iop_id' => (string)$iopId,
			'dDate' => $dDate,
			'dDateTime' => $now,
			'pulse_rate' => $normalized['pulse_rate'] === false ? null : $normalized['pulse_rate'],
			'temperature' => $normalized['temperature'] === false ? null : $normalized['temperature'],
			'height' => $normalized['height'] === false ? null : $normalized['height'],
			'bp' => $normalized['bp'],
			'respiration' => $normalized['respiration'] === false ? null : $normalized['respiration'],
			'weight' => $normalized['weight'] === false ? null : $normalized['weight'],
			'cPreparedBy' => (string)$actorUserId,
			'InActive' => 0
		);

		if (!$this->db->insert('iop_vital_parameters', $row)) {
			throw new RuntimeException('vitals_insert_failed');
		}
		$vitalId = (int)$this->db->insert_id();
		if ($vitalId <= 0) {
			throw new RuntimeException('vitals_insert_id_missing');
		}

		if ($this->nurseEnhancementModel && method_exists($this->nurseEnhancementModel, 'tables_ready') && $this->nurseEnhancementModel->tables_ready()) {
			if (method_exists($this->nurseEnhancementModel, 'save_vital_extra')) {
				$this->nurseEnhancementModel->save_vital_extra(
					$vitalId,
					$normalized['spo2'] === false ? null : $normalized['spo2'],
					$normalized['blood_sugar'] === false ? null : $normalized['blood_sugar'],
					$normalized['pain_score'] === false ? null : $normalized['pain_score'],
					$actorUserId
				);
			}
		}

		$this->db->where('IO_ID', (string)$iopId);
		if (!$this->db->update('patient_details_iop', array(
			'vitals_status' => 'DONE',
			'vitals_nurse_id' => (string)$actorUserId,
			'vitals_at' => $now
		))) {
			throw new RuntimeException('vitals_status_update_failed');
		}

		return array(
			'status' => 'success',
			'vital_id' => $vitalId,
			'refresh_required' => true,
			'governance_observed' => true
		);
	}

	protected function numericGuardrails($field, $value, $min, $max, $warnLow, $warnHigh, array &$errors, array &$warnings)
	{
		if ($value === null || $value === false) {
			return;
		}
		$v = (float)$value;
		if ($min !== null && $v < $min) {
			$errors[] = array('field' => $field, 'code' => 'NUMERIC_VALUE_OUT_OF_RANGE', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' value is outside allowed entry limits.');
			return;
		}
		if ($max !== null && $v > $max) {
			$errors[] = array('field' => $field, 'code' => 'NUMERIC_VALUE_OUT_OF_RANGE', 'message' => ucfirst(str_replace('_', ' ', $field)) . ' value is outside allowed entry limits.');
			return;
		}
		if ($warnLow !== null && $v < $warnLow) {
			$warnings[] = array('field' => $field, 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this value before saving.');
		}
		if ($warnHigh !== null && $v >= $warnHigh) {
			$warnings[] = array('field' => $field, 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this value before saving.');
		}
	}

	protected function stringOrNull(array $input, $key)
	{
		if (!isset($input[$key])) {
			return null;
		}
		$v = trim((string)$input[$key]);
		return $v === '' ? null : $v;
	}

	protected function numericOrNull(array $input, $key)
	{
		if (!isset($input[$key])) {
			return null;
		}
		$v = trim((string)$input[$key]);
		if ($v === '') {
			return null;
		}
		if (!is_numeric($v)) {
			return false;
		}
		if ((float)$v < 0) {
			return false;
		}
		return $v;
	}
}
