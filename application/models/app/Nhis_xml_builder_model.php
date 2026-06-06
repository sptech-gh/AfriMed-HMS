<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_xml_builder_model extends CI_Model
{
    public function build_claim_xml($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return array('success' => false, 'error' => 'INVALID_CLAIM_ID');
        }

        $this->load->model('app/Nhis_model', 'nhis');

        if ($this->nhis->is_claim_quarantined($claim_id)) {
            log_message('error', '[NHIS_XML_BLOCKED] claim_id=' . $claim_id);
            return array('success' => false, 'error' => 'CLAIM_QUARANTINED');
        }

        $claim = $this->_get_claim_with_items($claim_id);
        if (!$claim) {
            if ($this->nhis->is_claim_quarantined($claim_id)) {
                log_message('error', '[NHIS_XML_BLOCKED] claim_id=' . $claim_id);
                return array('success' => false, 'error' => 'CLAIM_QUARANTINED');
            }
            return array('success' => false, 'error' => 'CLAIM_NOT_FOUND');
        }

        try {
            $xml = $this->_build_xml_document($claim);
        } catch (Exception $e) {
            log_message('error', '[NHIS_XML_BUILD_FAIL] claim_id=' . $claim_id . ' err=' . $e->getMessage());
            return array('success' => false, 'error' => 'XML_BUILD_FAILED');
        }

        if (!$xml) {
            return array('success' => false, 'error' => 'XML_BUILD_FAILED');
        }

        $this->load->model('app/Nhis_xml_validator_model', 'validator');
        $validation = $this->validator->validate_xml($xml);
        if (empty($validation['valid'])) {
            log_message('error', '[NHIS_XML_VALIDATION_FAIL] errors=' . json_encode(isset($validation['errors']) ? $validation['errors'] : array()));
            return array(
                'success' => false,
                'errors' => isset($validation['errors']) ? $validation['errors'] : array(),
            );
        }

        return array(
            'success' => true,
            'xml' => $xml,
        );
    }

    public function export_claim_xml($claim_id)
    {
        $result = $this->build_claim_xml($claim_id);
        if (empty($result['success'])) {
            return $result;
        }

        $file = APPPATH . 'exports/nhis_claim_' . (int)$claim_id . '.xml';
        $written = @file_put_contents($file, $result['xml']);
        if ($written === false) {
            return array('success' => false, 'error' => 'FILE_WRITE_FAILED');
        }

        return array(
            'success' => true,
            'file' => $file,
        );
    }

    private function _get_claim_with_items($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $obj = $this->nhis->get_claim_details($claim_id);
        if (!$obj) {
            return null;
        }

        $facility_code = isset($obj->facility_code) ? trim((string)$obj->facility_code) : '';
        $nhis_number = isset($obj->nhis_member_id) ? trim((string)$obj->nhis_member_id) : '';
        $firstname = isset($obj->firstname) ? trim((string)$obj->firstname) : '';
        $lastname = isset($obj->lastname) ? trim((string)$obj->lastname) : '';
        $patient_name = trim($firstname . ' ' . $lastname);
        $encounter_id = isset($obj->encounter_id) ? (int)$obj->encounter_id : 0;
        $created_at = isset($obj->created_at) ? (string)$obj->created_at : '';

        if ($facility_code === '' || $nhis_number === '' || $patient_name === '' || $encounter_id <= 0 || $created_at === '') {
            return null;
        }

        $ts = strtotime($created_at);
        if ($ts === false || $ts <= 0) {
            return null;
        }
        $visit_date = date('Y-m-d', $ts);

        $diagnoses = $this->_extract_diagnoses(isset($obj->diagnosis_codes) ? $obj->diagnosis_codes : null);
        if (empty($diagnoses)) {
            return null;
        }

        if (!isset($obj->items) || !is_array($obj->items) || empty($obj->items)) {
            return null;
        }

        $items = array();
        foreach ($obj->items as $it) {
            $code = isset($it->nhis_code) ? trim((string)$it->nhis_code) : '';
            $cost = isset($it->total_amount) ? $it->total_amount : null;
            if ($code === '' || $cost === null || !is_numeric($cost)) {
                return null;
            }
            $items[] = array(
                'nhis_code' => $code,
                'tariff' => (float)$cost,
            );
        }

        $total = isset($obj->total_amount) ? $obj->total_amount : null;
        if ($total === null || !is_numeric($total)) {
            return null;
        }

        return array(
            'facility_code' => $facility_code,
            'nhis_number' => $nhis_number,
            'patient_name' => $patient_name,
            'encounter_id' => $encounter_id,
            'visit_date' => $visit_date,
            'diagnoses' => $diagnoses,
            'items' => $items,
            'total' => (float)$total,
        );
    }

    private function _extract_diagnoses($diagnosis_codes)
    {
        $codes = array();
        if ($diagnosis_codes === null || $diagnosis_codes === '') {
            return $codes;
        }

        $decoded = json_decode((string)$diagnosis_codes, true);
        if (!is_array($decoded)) {
            return $codes;
        }

        foreach ($decoded as $d) {
            if (is_array($d) && isset($d['code']) && trim((string)$d['code']) !== '') {
                $codes[] = trim((string)$d['code']);
            } elseif (is_string($d) && trim($d) !== '') {
                $codes[] = trim($d);
            }
        }

        $codes = array_values(array_unique($codes));
        return $codes;
    }

    private function _build_xml_document($claim)
    {
        if (!is_array($claim)) {
            return null;
        }

        $required = array('facility_code', 'nhis_number', 'patient_name', 'encounter_id', 'visit_date', 'diagnoses', 'items', 'total');
        foreach ($required as $k) {
            if (!array_key_exists($k, $claim)) {
                return null;
            }
        }

        if (!is_array($claim['diagnoses']) || empty($claim['diagnoses'])) {
            return null;
        }
        if (!is_array($claim['items']) || empty($claim['items'])) {
            return null;
        }

        $xml = new SimpleXMLElement('<Claim/>');

        $facility = $xml->addChild('Facility');
        $facility->addChild('FacilityCode', (string)$claim['facility_code']);

        $patient = $xml->addChild('Patient');
        $patient->addChild('NHISNumber', (string)$claim['nhis_number']);
        $patient->addChild('Name', (string)$claim['patient_name']);

        $encounter = $xml->addChild('Encounter');
        $encounter->addChild('EncounterID', (string)$claim['encounter_id']);
        $encounter->addChild('Date', (string)$claim['visit_date']);

        $dxNode = $xml->addChild('Diagnoses');
        foreach ($claim['diagnoses'] as $dx) {
            $dx = trim((string)$dx);
            if ($dx === '') {
                return null;
            }
            $d = $dxNode->addChild('Diagnosis');
            $d->addChild('ICD10', $dx);
        }

        $servicesNode = $xml->addChild('Services');
        foreach ($claim['items'] as $item) {
            if (!is_array($item)) {
                return null;
            }
            if (!isset($item['nhis_code']) || trim((string)$item['nhis_code']) === '') {
                return null;
            }
            if (!isset($item['tariff']) || !is_numeric($item['tariff'])) {
                return null;
            }

            $service = $servicesNode->addChild('Service');
            $service->addChild('Code', trim((string)$item['nhis_code']));
            $service->addChild('Cost', number_format((float)$item['tariff'], 2, '.', ''));
        }

        if (!is_numeric($claim['total'])) {
            return null;
        }

        $totals = $xml->addChild('Totals');
        $totals->addChild('TotalCost', number_format((float)$claim['total'], 2, '.', ''));

        return $xml->asXML();
    }
}
