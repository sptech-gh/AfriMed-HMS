<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_xml_validator_model extends CI_Model
{
    public function validate_xml($xml_string)
    {
        $errors = array();

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string);
        if (!$xml) {
            libxml_clear_errors();
            return array(
                'valid' => false,
                'errors' => array('INVALID_XML_STRUCTURE'),
            );
        }

        $errors = array_merge($errors, $this->_validate_structure($xml));
        $errors = array_merge($errors, $this->_validate_business_rules($xml));

        libxml_clear_errors();

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
        );
    }

    private function _validate_structure($xml)
    {
        $errors = array();

        if (empty((string)$xml->Facility->FacilityCode)) {
            $errors[] = 'MISSING_FACILITY_CODE';
        }

        if (empty((string)$xml->Patient->NHISNumber)) {
            $errors[] = 'MISSING_NHIS_NUMBER';
        }

        if (empty($xml->Diagnoses->Diagnosis)) {
            $errors[] = 'MISSING_DIAGNOSIS';
        }

        if (empty($xml->Services->Service)) {
            $errors[] = 'MISSING_SERVICES';
        }

        if (empty((string)$xml->Totals->TotalCost)) {
            $errors[] = 'MISSING_TOTAL';
        }

        if (empty((string)$xml->Encounter->Date)) {
            $errors[] = 'MISSING_VISIT_DATE';
        }

        return $errors;
    }

    private function _validate_business_rules($xml)
    {
        $errors = array();

        $total = 0.0;

        $nhisNo = trim((string)$xml->Patient->NHISNumber);
        if ($nhisNo !== '' && !preg_match('/^[A-Z0-9\-\/]{6,30}$/i', $nhisNo)) {
            $errors[] = 'INVALID_NHIS_NUMBER_FORMAT';
        }

        $visitDateStr = trim((string)$xml->Encounter->Date);
        if ($visitDateStr !== '') {
            $ts = strtotime($visitDateStr);
            if ($ts === false || $ts <= 0) {
                $errors[] = 'INVALID_VISIT_DATE';
            } else {
                $today = strtotime(date('Y-m-d'));
                if ($ts > ($today + 86400)) {
                    $errors[] = 'INVALID_VISIT_DATE';
                }
                $min = strtotime(date('Y-m-d', strtotime('-90 days')));
                if ($ts < $min) {
                    $errors[] = 'INVALID_VISIT_DATE';
                }
            }
        }

        $dxCodes = array();
        if (!empty($xml->Diagnoses->Diagnosis)) {
            foreach ($xml->Diagnoses->Diagnosis as $dxNode) {
                $code = trim((string)$dxNode->ICD10);
                if ($code !== '') {
                    $dxCodes[] = strtoupper($code);
                }
            }
        }

        $serviceSeen = array();

        if (!empty($xml->Services->Service)) {
            foreach ($xml->Services->Service as $service) {
                $code = trim((string)$service->Code);
                $costRaw = (string)$service->Cost;
                $cost = (float)$costRaw;

                if ($code === '') {
                    $errors[] = 'EMPTY_SERVICE_CODE';
                }

                if ($cost == 0.0) {
                    $errors[] = 'INVALID_SERVICE_COST_ZERO';
                }

                if ($cost < 0) {
                    $errors[] = 'INVALID_SERVICE_COST';
                }

                if ($code !== '' && is_numeric($costRaw)) {
                    $k = strtoupper($code) . '|' . number_format((float)$cost, 2, '.', '');
                    if (!isset($serviceSeen[$k])) {
                        $serviceSeen[$k] = 0;
                    }
                    $serviceSeen[$k]++;
                }

                $total += $cost;
            }
        }

        foreach ($serviceSeen as $k => $cnt) {
            if ((int)$cnt > 1) {
                $errors[] = 'DUPLICATE_SERVICE_CODE';
                break;
            }
        }

        $xmlTotalRaw = (string)$xml->Totals->TotalCost;
        $xmlTotal = (float)$xmlTotalRaw;

        if (round($total, 2) !== round($xmlTotal, 2)) {
            $errors[] = 'TOTAL_MISMATCH';
        }

        if (abs($total - $xmlTotal) > 0.01) {
            $errors[] = 'TOTAL_MISMATCH_STRICT';
        }

        if (!empty($dxCodes) && !empty($serviceSeen)) {
            $allMalaria = true;
            $hasMalaria = false;
            foreach ($dxCodes as $dx) {
                if (preg_match('/^B5[0-4]/', $dx)) {
                    $hasMalaria = true;
                    continue;
                }
                $allMalaria = false;
            }

            if ($hasMalaria && $allMalaria) {
                foreach (array_keys($serviceSeen) as $svcKey) {
                    $svcCode = explode('|', $svcKey, 2);
                    $svcCode = isset($svcCode[0]) ? (string)$svcCode[0] : '';
                    if ($svcCode !== '' && preg_match('/(MRI|\bCT\b|XRAY|X\-RAY|SCAN|RAD)/i', $svcCode)) {
                        $errors[] = 'SERVICE_DIAGNOSIS_MISMATCH';
                        break;
                    }
                }
            }
        }

        return $errors;
    }
}
