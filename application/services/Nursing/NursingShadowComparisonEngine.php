<?php

class NursingShadowComparisonEngine
{
    public function compareVitalsPlan(array $mutationPlan, array $actualEffect)
    {
        $predicted = $this->normalizePrediction($mutationPlan);
        $actual = $this->normalizeActualEffect($actualEffect);
        $divergences = array();

        $this->compareSet('insert_tables', $predicted['insert_tables'], $actual['insert_tables'], $divergences, 'MISSING_INSERT', 'UNEXPECTED_INSERT');
        $this->compareSet('update_fields', $predicted['update_fields'], $actual['update_fields'], $divergences, 'MISSING_UPDATE', 'UNEXPECTED_UPDATE');
        $this->compareAssociative('normalized_values', $predicted['normalized_values'], $actual['normalized_values'], $divergences, 'VALUE_MISMATCH');
        $this->compareScalar('patient_no', $predicted['patient_no'], $actual['patient_no'], $divergences, 'PATIENT_MISMATCH');
        $this->compareScalar('encounter_id', $predicted['encounter_id'], $actual['encounter_id'], $divergences, 'ENCOUNTER_MISMATCH');
        $this->compareScalar('actor_user_id', $predicted['actor_user_id'], $actual['actor_user_id'], $divergences, 'ACTOR_ATTRIBUTION_MISMATCH');
        $this->compareScalar('vitals_status', $predicted['vitals_status'], $actual['vitals_status'], $divergences, 'STATUS_TRANSITION_MISMATCH');

        $timestampDrift = $this->timestampDriftSeconds($predicted['timestamp'], $actual['timestamp']);
        if ($timestampDrift !== null && $timestampDrift > 120) {
            $divergences[] = array(
                'class' => 'TIMESTAMP_DRIFT',
                'field' => 'timestamp',
                'predicted' => $predicted['timestamp'],
                'actual' => $actual['timestamp'],
                'drift_seconds' => $timestampDrift,
            );
        }

        $matchRate = $this->matchRate($divergences, 7);
        return array(
            'schema' => 'SPRINT2D_NURSING_SHADOW_COMPARISON_V1',
            'status' => count($divergences) === 0 ? 'PASS' : 'WARN',
            'decision' => count($divergences) === 0 ? 'SHADOW_PARITY_MATCH' : 'SHADOW_PARITY_DIVERGENCE_DETECTED',
            'shadow_only' => true,
            'authoritative' => false,
            'mutation_performed_by_shadow' => false,
            'prediction_match_rate' => $matchRate,
            'predicted' => $predicted,
            'actual' => $actual,
            'divergences' => $divergences,
            'metrics' => $this->metrics($divergences, $matchRate),
        );
    }

    protected function normalizePrediction(array $mutationPlan)
    {
        $updates = isset($mutationPlan['would_update']) && is_array($mutationPlan['would_update']) ? $mutationPlan['would_update'] : array();
        $normalizedValues = isset($mutationPlan['normalized_values']) && is_array($mutationPlan['normalized_values']) ? $mutationPlan['normalized_values'] : array();
        return array(
            'insert_tables' => $this->normalizeList(isset($mutationPlan['would_insert']) && is_array($mutationPlan['would_insert']) ? $mutationPlan['would_insert'] : array()),
            'update_fields' => $this->normalizeList($updates),
            'normalized_values' => $this->canonicalize($normalizedValues),
            'patient_no' => isset($mutationPlan['patient_no']) ? (string)$mutationPlan['patient_no'] : '',
            'encounter_id' => isset($mutationPlan['encounter_id']) ? (string)$mutationPlan['encounter_id'] : '',
            'actor_user_id' => isset($mutationPlan['actor_user_id']) ? (string)$mutationPlan['actor_user_id'] : '',
            'vitals_status' => in_array('patient_details_iop.vitals_status', $updates, true) ? 'RECORDED' : '',
            'timestamp' => isset($mutationPlan['predicted_timestamp']) ? (string)$mutationPlan['predicted_timestamp'] : '',
        );
    }

    protected function normalizeActualEffect(array $actualEffect)
    {
        return array(
            'insert_tables' => $this->normalizeList(isset($actualEffect['insert_tables']) && is_array($actualEffect['insert_tables']) ? $actualEffect['insert_tables'] : array()),
            'update_fields' => $this->normalizeList(isset($actualEffect['update_fields']) && is_array($actualEffect['update_fields']) ? $actualEffect['update_fields'] : array()),
            'normalized_values' => $this->canonicalize(isset($actualEffect['normalized_values']) && is_array($actualEffect['normalized_values']) ? $actualEffect['normalized_values'] : array()),
            'patient_no' => isset($actualEffect['patient_no']) ? (string)$actualEffect['patient_no'] : '',
            'encounter_id' => isset($actualEffect['encounter_id']) ? (string)$actualEffect['encounter_id'] : '',
            'actor_user_id' => isset($actualEffect['actor_user_id']) ? (string)$actualEffect['actor_user_id'] : '',
            'vitals_status' => isset($actualEffect['vitals_status']) ? (string)$actualEffect['vitals_status'] : '',
            'timestamp' => isset($actualEffect['timestamp']) ? (string)$actualEffect['timestamp'] : '',
        );
    }

    protected function compareSet($field, array $predicted, array $actual, array &$divergences, $missingClass, $unexpectedClass)
    {
        foreach (array_diff($predicted, $actual) as $missing) {
            $divergences[] = array('class' => $missingClass, 'field' => $field, 'predicted' => $missing, 'actual' => null);
        }
        foreach (array_diff($actual, $predicted) as $unexpected) {
            $divergences[] = array('class' => $unexpectedClass, 'field' => $field, 'predicted' => null, 'actual' => $unexpected);
        }
    }

    protected function compareAssociative($field, array $predicted, array $actual, array &$divergences, $class)
    {
        $keys = array_unique(array_merge(array_keys($predicted), array_keys($actual)));
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            $p = array_key_exists($key, $predicted) ? $predicted[$key] : null;
            $a = array_key_exists($key, $actual) ? $actual[$key] : null;
            if ((string)$p !== (string)$a) {
                $divergences[] = array('class' => $class, 'field' => $field . '.' . $key, 'predicted' => $p, 'actual' => $a);
            }
        }
    }

    protected function compareScalar($field, $predicted, $actual, array &$divergences, $class)
    {
        if ((string)$predicted !== (string)$actual) {
            $divergences[] = array('class' => $class, 'field' => $field, 'predicted' => $predicted, 'actual' => $actual);
        }
    }

    protected function timestampDriftSeconds($predicted, $actual)
    {
        if (trim((string)$predicted) === '' || trim((string)$actual) === '') {
            return null;
        }
        $p = strtotime((string)$predicted);
        $a = strtotime((string)$actual);
        if ($p === false || $a === false) {
            return null;
        }
        return abs($a - $p);
    }

    protected function matchRate(array $divergences, $checks)
    {
        $checks = max(1, (int)$checks);
        return round(max(0, $checks - count($divergences)) / $checks, 4);
    }

    protected function metrics(array $divergences, $matchRate)
    {
        $classes = array();
        foreach ($divergences as $divergence) {
            $class = isset($divergence['class']) ? $divergence['class'] : 'UNKNOWN';
            if (!isset($classes[$class])) {
                $classes[$class] = 0;
            }
            $classes[$class]++;
        }
        return array(
            'prediction_match_rate' => $matchRate,
            'divergence_count' => count($divergences),
            'divergence_classes' => $classes,
            'missing_side_effects' => isset($classes['MISSING_INSERT']) || isset($classes['MISSING_UPDATE']),
            'unexpected_writes' => isset($classes['UNEXPECTED_INSERT']) || isset($classes['UNEXPECTED_UPDATE']),
            'divergent_status_transitions' => isset($classes['STATUS_TRANSITION_MISMATCH']),
            'timestamp_drift' => isset($classes['TIMESTAMP_DRIFT']),
            'actor_attribution_mismatch' => isset($classes['ACTOR_ATTRIBUTION_MISMATCH']),
        );
    }

    protected function normalizeList(array $values)
    {
        $out = array();
        foreach ($values as $value) {
            $out[] = (string)$value;
        }
        $out = array_values(array_unique($out));
        sort($out, SORT_STRING);
        return $out;
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
}
