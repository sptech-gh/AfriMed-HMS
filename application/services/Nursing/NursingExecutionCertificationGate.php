<?php

class NursingExecutionCertificationGate
{
    protected $thresholds;

    public function __construct(array $thresholds = array())
    {
        $defaults = array(
            'authorization_confidence' => 1.0,
            'safety_confidence' => 1.0,
            'parity_confidence' => 0.95,
            'idempotency_stability_score' => 1.0,
        );
        $this->thresholds = array_merge($defaults, $thresholds);
    }

    public function certifyVitalsExecution(array $runtimeEvidence)
    {
        $scores = $this->score($runtimeEvidence);
        $failures = array();
        foreach ($this->thresholds as $name => $threshold) {
            if (!array_key_exists($name, $scores) || $scores[$name] === null) {
                $failures[] = array('score' => $name, 'decision' => 'NO_SCORE_NO_WRITE', 'threshold' => $threshold, 'actual' => null);
                continue;
            }
            if ((float)$scores[$name] < (float)$threshold) {
                $failures[] = array('score' => $name, 'decision' => 'THRESHOLD_NOT_MET', 'threshold' => $threshold, 'actual' => $scores[$name]);
            }
        }

        $blocked = count($failures) > 0;
        return array(
            'schema' => 'SPRINT2D_NURSING_EXECUTION_CERTIFICATION_GATE_V1',
            'status' => $blocked ? 'BLOCK' : 'PASS',
            'decision' => $blocked ? 'CERTIFICATION_BLOCKED_FAIL_CLOSED' : 'CERTIFICATION_ELIGIBLE_FOR_FUTURE_COMMIT_REVIEW',
            'action_type' => 'NURSING_VITALS_RECORDED',
            'authoritative' => false,
            'certification_only' => true,
            'mutation_allowed_now' => false,
            'commit_path_enabled' => false,
            'no_score_no_write' => true,
            'thresholds' => $this->thresholds,
            'scores' => $scores,
            'failures' => $failures,
            'rollback_semantics' => $this->rollbackSemantics($blocked),
            'created_at_utc' => gmdate('c'),
        );
    }

    protected function score(array $runtimeEvidence)
    {
        return array(
            'authorization_confidence' => $this->authorizationConfidence($runtimeEvidence),
            'safety_confidence' => $this->safetyConfidence($runtimeEvidence),
            'parity_confidence' => $this->parityConfidence($runtimeEvidence),
            'idempotency_stability_score' => $this->idempotencyStabilityScore($runtimeEvidence),
        );
    }

    protected function authorizationConfidence(array $evidence)
    {
        $authorization = $this->field($evidence, 'authorization');
        if (!is_array($authorization)) {
            return null;
        }
        $status = strtoupper((string)$this->field($authorization, 'status'));
        $decision = strtoupper((string)$this->field($authorization, 'decision'));
        if ($status === 'PASS' || strpos($decision, 'AUTHORIZED') !== false) {
            return 1.0;
        }
        return 0.0;
    }

    protected function safetyConfidence(array $evidence)
    {
        $safety = $this->field($evidence, 'safety');
        if (!is_array($safety)) {
            return null;
        }
        $status = strtoupper((string)$this->field($safety, 'status'));
        if ($status === 'PASS') {
            return 1.0;
        }
        if ($status === 'WARN') {
            return 0.8;
        }
        return 0.0;
    }

    protected function parityConfidence(array $evidence)
    {
        $parity = $this->field($evidence, 'shadow_parity');
        if (!is_array($parity)) {
            return null;
        }
        $metrics = $this->field($parity, 'metrics');
        if (is_array($metrics) && array_key_exists('prediction_match_rate', $metrics)) {
            return (float)$metrics['prediction_match_rate'];
        }
        if (array_key_exists('prediction_match_rate', $parity)) {
            return (float)$parity['prediction_match_rate'];
        }
        return null;
    }

    protected function idempotencyStabilityScore(array $evidence)
    {
        $idempotency = $this->field($evidence, 'idempotency');
        $reservation = $this->field($evidence, 'reservation');
        $locks = $this->field($evidence, 'locks');
        if (!is_array($idempotency) || !is_array($reservation) || !is_array($locks)) {
            return null;
        }

        $classification = strtoupper((string)$this->field($idempotency, 'classification'));
        $reservationStatus = strtoupper((string)$this->field($reservation, 'status'));
        $lockDecision = strtoupper((string)$this->field($locks, 'decision'));
        $stableClassifications = array('NEW_REQUEST', 'SAFE_RETRY', 'DUPLICATE_SUBMISSION', 'RECOVERY_CONTINUATION', 'LEASE_EXPIRED_RECOVERABLE');
        if (!in_array($classification, $stableClassifications, true)) {
            return 0.0;
        }
        if ($reservationStatus !== 'PASS') {
            return 0.0;
        }
        if (strpos($lockDecision, 'LOCK_PLAN_BUILT') === false && strpos($lockDecision, 'SHADOW_LOCK') === false) {
            return 0.5;
        }
        return 1.0;
    }

    protected function rollbackSemantics($blocked)
    {
        if ($blocked) {
            return array(
                'engine_level_state' => 'FAIL_CLOSED_NO_COMMIT_ELIGIBILITY',
                'db_rollback_required' => false,
                'reservation_policy' => 'RELEASE_OR_EXPIRE_SHADOW_RESERVATION',
                'lock_policy' => 'RELEASE_SHADOW_LOCKS_ONLY',
                'audit_policy' => 'CERTIFICATION_BLOCK_RECORDED_AS_EVIDENCE',
            );
        }
        return array(
            'engine_level_state' => 'ELIGIBLE_FOR_FUTURE_COMMIT_REVIEW_ONLY',
            'db_rollback_required' => false,
            'reservation_policy' => 'KEEP_SHADOW_EVIDENCE_ONLY',
            'lock_policy' => 'NO_PRODUCTION_LOCKS_HELD',
            'audit_policy' => 'CERTIFICATION_ELIGIBILITY_RECORDED_AS_EVIDENCE',
        );
    }

    protected function field($value, $field)
    {
        if (is_array($value) && array_key_exists($field, $value)) {
            return $value[$field];
        }
        return null;
    }
}
