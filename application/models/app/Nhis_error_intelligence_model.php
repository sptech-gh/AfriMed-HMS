<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_error_intelligence_model extends CI_Model
{
    public function analyze_errors(array $errors): array
    {
        $results = array();

        foreach ($errors as $error) {
            $context = is_array($error) ? $error : array('error' => (string)$error);
            $code = 'UNKNOWN';
            if (is_array($error) && isset($error['error'])) {
                $code = strtoupper((string)$error['error']);
            }

            if (is_array($context) && isset($context['nhis_code']) && !isset($context['code'])) {
                $context['code'] = (string)$context['nhis_code'];
            }

            switch ($code) {
                case 'MAPPING_MISSING':
                    $results[] = $this->_build_response(
                        $code,
                        'FIXABLE',
                        'CREATE_MAPPING',
                        0.9,
                        'Service is not mapped to NHIS code',
                        'MEDIUM',
                        $context
                    );
                    break;

                case 'INVALID_SERVICE_CODE':
                    $results[] = $this->_build_response(
                        $code,
                        'REQUIRES_REVIEW',
                        'VERIFY_CODE',
                        0.6,
                        'Service code appears invalid or unrecognized',
                        'HIGH',
                        $context
                    );
                    break;

                case 'TARIFF_MISSING':
                    $results[] = $this->_build_response(
                        $code,
                        'DATA_ISSUE',
                        'IMPORT_TARIFF',
                        0.95,
                        'Tariff entry is missing for the referenced code',
                        'HIGH',
                        $context
                    );
                    break;

                case 'ICD10_INVALID':
                    $results[] = $this->_build_response(
                        $code,
                        'REQUIRES_REVIEW',
                        'FIX_DIAGNOSIS',
                        0.7,
                        'Diagnosis code is not a valid ICD-10 entry',
                        'HIGH',
                        $context
                    );
                    break;

                case 'GDRG_INVALID':
                    $results[] = $this->_build_response(
                        $code,
                        'REQUIRES_REVIEW',
                        'FIX_PROCEDURE',
                        0.7,
                        'Procedure code is not a valid G-DRG entry',
                        'HIGH',
                        $context
                    );
                    break;

                case 'TOTAL_MISMATCH':
                    $results[] = $this->_build_response(
                        $code,
                        'CRITICAL',
                        'INVESTIGATE',
                        1.0,
                        'Claim totals do not match itemized services',
                        'CRITICAL',
                        $context
                    );
                    break;

                case 'MISSING_SERVICES':
                    $results[] = $this->_build_response(
                        $code,
                        'CRITICAL',
                        'INVESTIGATE',
                        1.0,
                        'Claim has no services; cannot submit',
                        'CRITICAL',
                        $context
                    );
                    break;

                case 'MISSING_DIAGNOSIS':
                    $results[] = $this->_build_response(
                        $code,
                        'CRITICAL',
                        'INVESTIGATE',
                        1.0,
                        'Claim has no diagnosis; cannot submit',
                        'CRITICAL',
                        $context
                    );
                    break;

                default:
                    $results[] = $this->_build_response(
                        $code,
                        'UNKNOWN',
                        'INVESTIGATE',
                        0.5,
                        'Unknown validation error',
                        'MEDIUM',
                        $context
                    );
                    break;
            }
        }

        log_message('debug', '[NHIS_ERROR_INTEL] analyzed=' . count($results));

        return $results;
    }

    private function _build_response($error_code, $type, $action, $confidence, $message, $severity, $context)
    {
        $error_code = strtoupper(trim((string)$error_code));
        $c = (float)$confidence;
        if ($c < 0.0) {
            $c = 0.0;
        }
        if ($c > 1.0) {
            $c = 1.0;
        }

        $action_key = strtolower(trim((string)$action));
        $action_key = preg_replace('/[^a-z0-9]+/', '_', $action_key);
        $action_key = trim($action_key, '_');

        $sev = strtoupper(trim((string)$severity));
        if ($sev === '') {
            $sev = 'MEDIUM';
        }

        return array(
            'error_code' => $error_code !== '' ? $error_code : 'UNKNOWN',
            'type' => (string)$type,
            'action' => (string)$action,
            'action_key' => $action_key !== '' ? $action_key : 'investigate',
            'confidence' => $c,
            'message' => (string)$message,
            'severity' => $sev,
            'context' => is_array($context) ? $context : array(),
        );
    }
}
