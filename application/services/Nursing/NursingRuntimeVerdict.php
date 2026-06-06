<?php

class NursingRuntimeVerdict
{
    public function classify(array $result, array $artifacts = array())
    {
        $status = isset($result['status']) ? strtolower((string)$result['status']) : '';
        $code = isset($result['code']) ? strtoupper((string)$result['code']) : '';
        $warnings = isset($result['warnings']) && is_array($result['warnings']) ? $result['warnings'] : array();
        $errors = isset($result['errors']) && is_array($result['errors']) ? $result['errors'] : array();

        if ($status === 'success') {
            return array(
                'verdict' => count($warnings) > 0 ? 'EXECUTION_ALLOWED_WITH_WARNING' : 'EXECUTION_ALLOWED',
                'status' => 'ALLOW',
                'code' => $code,
                'warning_count' => count($warnings),
                'error_count' => count($errors),
            );
        }

        $verdict = 'EXECUTION_BLOCKED_PRECONDITION';
        if (strpos($code, 'AUTHORIZATION') !== false || strpos($code, 'AUTHORIZED') !== false) {
            $verdict = 'EXECUTION_BLOCKED_AUTHORIZATION';
        } elseif (strpos($code, 'SAFETY') !== false) {
            $verdict = 'EXECUTION_BLOCKED_SAFETY';
        } elseif (strpos($code, 'LOCK') !== false || strpos($code, 'LEASE') !== false) {
            $verdict = 'EXECUTION_RECOVERY_REQUIRED';
        } elseif (strpos($code, 'IDEMPOTENCY') !== false || strpos($code, 'DUPLICATE') !== false || strpos($code, 'COLLISION') !== false) {
            $verdict = 'EXECUTION_QUARANTINED';
        } elseif (strpos($code, 'TRUTH') !== false) {
            $verdict = 'EXECUTION_BLOCKED_PRECONDITION';
        }

        return array(
            'verdict' => $verdict,
            'status' => 'BLOCK',
            'code' => $code,
            'warning_count' => count($warnings),
            'error_count' => count($errors),
        );
    }
}
