<?php

class NursingSafetyEvaluator
{
    public function evaluateVitals(ClinicalMutationRequest $request)
    {
        $payload = $this->normalizeVitalsPayload($request->payload());
        $errors = array();
        $warnings = array();

        foreach (array('temperature','pulse_rate','respiration','weight','height','spo2','blood_sugar','pain_score') as $field) {
            if ($payload[$field] === false) {
                $errors[] = array('field' => $field, 'code' => 'NON_NUMERIC_OR_NEGATIVE', 'message' => 'Value must be numeric and non-negative.');
            }
        }

        $corePresent = false;
        foreach (array('bp','temperature','pulse_rate','respiration','weight') as $field) {
            if (isset($payload[$field]) && $payload[$field] !== null && $payload[$field] !== false) {
                $corePresent = true;
            }
        }

        if (!$corePresent) {
            $errors[] = array('field' => 'core', 'code' => 'NO_VITALS_PROVIDED', 'message' => 'At least one vital sign is required.');
        }

        if ($payload['bp'] !== null) {
            $bp = trim((string)$payload['bp']);
            if (!preg_match('/^\s*(\d{1,3})\s*\/\s*(\d{1,3})\s*$/', $bp, $bpParts)) {
                $errors[] = array('field' => 'bp', 'code' => 'BP_FORMAT_INVALID', 'message' => 'Blood pressure must be in systolic/diastolic format.');
            } else {
                $sys = (int)$bpParts[1];
                $dia = (int)$bpParts[2];
                $payload['bp'] = $sys . '/' . $dia;
                if ($sys < 30 || $sys > 300) {
                    $errors[] = array('field' => 'bp', 'code' => 'BP_SYSTOLIC_OUT_OF_RANGE', 'message' => 'Systolic BP is outside allowed entry limits.');
                }
                if ($dia < 20 || $dia > 200) {
                    $errors[] = array('field' => 'bp', 'code' => 'BP_DIASTOLIC_OUT_OF_RANGE', 'message' => 'Diastolic BP is outside allowed entry limits.');
                }
                if ($sys < 90 || $sys >= 180 || $dia < 60 || $dia >= 120) {
                    $warnings[] = array('field' => 'bp', 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this blood pressure value before saving.');
                }
            }
        }

        $this->numericGuardrails('temperature', $payload['temperature'], 20, 45, 35, 39, $errors, $warnings);
        $this->numericGuardrails('pulse_rate', $payload['pulse_rate'], 0, 300, 40, 130, $errors, $warnings);
        $this->numericGuardrails('respiration', $payload['respiration'], 0, 100, 8, 30, $errors, $warnings);
        $this->numericGuardrails('spo2', $payload['spo2'], 0, 100, 90, null, $errors, $warnings);
        $this->numericGuardrails('weight', $payload['weight'], 0, 500, null, null, $errors, $warnings);
        $this->numericGuardrails('height', $payload['height'], 0, 250, null, null, $errors, $warnings);
        $this->numericGuardrails('blood_sugar', $payload['blood_sugar'], 0, 1000, null, null, $errors, $warnings);
        $this->numericGuardrails('pain_score', $payload['pain_score'], 0, 10, null, 8, $errors, $warnings);

        return array(
            'status' => count($errors) > 0 ? 'BLOCK' : 'PASS',
            'decision' => count($warnings) > 0 ? 'ALLOW_WITH_CONFIRMATION' : 'ALLOW',
            'normalized_values' => $payload,
            'errors' => $errors,
            'warnings' => $warnings,
        );
    }

    protected function normalizeVitalsPayload(array $input)
    {
        return array(
            'bp' => $this->stringOrNull($input, 'bp'),
            'temperature' => $this->numericOrNull($input, 'temperature'),
            'pulse_rate' => $this->numericOrNull($input, 'pulse_rate'),
            'respiration' => $this->numericOrNull($input, 'respiration'),
            'weight' => $this->numericOrNull($input, 'weight'),
            'height' => $this->numericOrNull($input, 'height'),
            'spo2' => $this->numericOrNull($input, 'spo2'),
            'blood_sugar' => $this->numericOrNull($input, 'blood_sugar'),
            'pain_score' => $this->numericOrNull($input, 'pain_score'),
        );
    }

    protected function stringOrNull(array $input, $key)
    {
        if (!isset($input[$key])) {
            return null;
        }
        $value = trim((string)$input[$key]);
        return $value === '' ? null : $value;
    }

    protected function numericOrNull(array $input, $key)
    {
        if (!isset($input[$key]) || trim((string)$input[$key]) === '') {
            return null;
        }
        if (!is_numeric($input[$key])) {
            return false;
        }
        $value = (float)$input[$key];
        if ($value < 0) {
            return false;
        }
        return $value;
    }

    protected function numericGuardrails($field, $value, $absoluteMin, $absoluteMax, $warningMin, $warningMax, array &$errors, array &$warnings)
    {
        if ($value === null || $value === false) {
            return;
        }
        $v = (float)$value;
        if ($absoluteMin !== null && $v < (float)$absoluteMin) {
            $errors[] = array('field' => $field, 'code' => strtoupper($field) . '_OUT_OF_RANGE', 'message' => 'Value is outside allowed entry limits.');
        }
        if ($absoluteMax !== null && $v > (float)$absoluteMax) {
            $errors[] = array('field' => $field, 'code' => strtoupper($field) . '_OUT_OF_RANGE', 'message' => 'Value is outside allowed entry limits.');
        }
        if ($warningMin !== null && $v < (float)$warningMin) {
            $warnings[] = array('field' => $field, 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this vital value before saving.');
        }
        if ($warningMax !== null && $v >= (float)$warningMax) {
            $warnings[] = array('field' => $field, 'code' => 'UNUSUAL_VALUE_CONFIRM', 'message' => 'Please confirm this vital value before saving.');
        }
    }
}
