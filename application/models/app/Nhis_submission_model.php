<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_submission_model extends CI_Model
{
    private $nhis_audit_pk_column = null;
    private $submission_timeout_minutes = 20;

    public function submit_claim($claim_id)
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

        $activeStateRow = $this->_get_active_submission_state_row($claim_id);
        $state = isset($activeStateRow['state']) ? $activeStateRow['state'] : null;
        if ($state === 'ACCEPTED') {
            log_message('error', '[NHIS_DUPLICATE_SUBMISSION_BLOCKED] claim_id=' . $claim_id . ' state=' . $state);
            return array('success' => false, 'error' => 'DUPLICATE_SUBMISSION_BLOCKED');
        }

        if ($state === 'SUBMITTED') {
            if (!$this->_is_submission_timeout_expired($activeStateRow)) {
                log_message('error', '[NHIS_DUPLICATE_SUBMISSION_BLOCKED] claim_id=' . $claim_id . ' state=' . $state);
                return array('success' => false, 'error' => 'DUPLICATE_SUBMISSION_BLOCKED');
            }
            if (!empty($activeStateRow['created_at']) && $this->_has_claim_response_after($claim_id, (string)$activeStateRow['created_at'])) {
                log_message('error', '[NHIS_DUPLICATE_SUBMISSION_BLOCKED] claim_id=' . $claim_id . ' state=' . $state);
                return array('success' => false, 'error' => 'DUPLICATE_SUBMISSION_BLOCKED');
            }
            log_message('error', '[NHIS_RESUBMIT_ALLOWED_TIMEOUT] claim_id=' . $claim_id);
        }

        $artifact = $this->_get_latest_generated_artifact($claim_id);
        $regenAllowed = $this->_is_regeneration_allowed($claim_id);

        if ($state === 'SUBMITTED') {
            if (empty($artifact['file'])) {
                return array('success' => false, 'error' => 'SUBMITTED_NO_ARTIFACT');
            }

            $fileHash = $this->_compute_file_hash($artifact['file']);
            $expectedHash = isset($artifact['hash']) ? $artifact['hash'] : null;
            if (!$this->_verify_file_integrity($artifact['file'], $expectedHash, $fileHash)) {
                return array('success' => false, 'error' => 'FILE_TAMPERED');
            }

            $snap = $this->_build_payload_snapshot_from_file($artifact['file']);
            $attempt = $this->_get_next_attempt($claim_id);
            $this->_set_submission_state($claim_id, 'SUBMITTED', array(
                'attempt' => $attempt,
                'file' => $artifact['file'],
                'hash' => $fileHash,
                'payload_snapshot' => $snap,
            ));

            return array(
                'success' => true,
                'file' => $artifact['file'],
            );
        }

        if ($state === 'REJECTED' && !$regenAllowed && $this->_has_prior_xml_generation($claim_id)) {
            return array('success' => false, 'error' => 'REVALIDATION_REQUIRED');
        }

        if (!$regenAllowed && empty($artifact['file']) && $this->_has_prior_xml_generation($claim_id)) {
            return array('success' => false, 'error' => 'XML_REGENERATION_BLOCKED');
        }

        if (!empty($artifact['file']) && !$regenAllowed) {
            $fileHash = $this->_compute_file_hash($artifact['file']);
            $expectedHash = isset($artifact['hash']) ? $artifact['hash'] : null;
            if (!$this->_verify_file_integrity($artifact['file'], $expectedHash, $fileHash)) {
                return array('success' => false, 'error' => 'FILE_TAMPERED');
            }

            $snap = $this->_build_payload_snapshot_from_file($artifact['file']);
            $attempt = $this->_get_next_attempt($claim_id);
            if ($state === 'SUBMITTED') {
                $this->_set_submission_state($claim_id, 'SUBMITTED', array(
                    'attempt' => $attempt,
                    'file' => $artifact['file'],
                    'hash' => $fileHash,
                    'payload_snapshot' => $snap,
                ));
            } else {
                if ($state === null) {
                    $this->_set_submission_state($claim_id, 'GENERATED', array(
                        'file' => $artifact['file'],
                        'hash' => $fileHash,
                        'payload_snapshot' => $snap,
                    ));
                }

                $this->_set_submission_state($claim_id, 'VALIDATED', array(
                    'validation_hash' => $fileHash,
                    'file' => $artifact['file'],
                    'hash' => $fileHash,
                    'payload_snapshot' => $snap,
                ));

                if (!$this->_is_validation_hash_current($claim_id, $artifact['file'], $fileHash)) {
                    return array('success' => false, 'error' => 'FORCE_REVALIDATION');
                }

                $this->_set_submission_state($claim_id, 'SUBMITTED', array(
                    'attempt' => $attempt,
                    'file' => $artifact['file'],
                    'hash' => $fileHash,
                    'payload_snapshot' => $snap,
                ));
            }
            return array(
                'success' => true,
                'file' => $artifact['file'],
            );
        }

        $this->load->model('app/Nhis_xml_builder_model', 'builder');

        $result = $this->builder->build_claim_xml($claim_id);
        if (empty($result['success'])) {
            return $result;
        }

        $filePath = $this->_save_xml_file($claim_id, $result['xml']);
        if (!$filePath) {
            return array('success' => false, 'error' => 'XML_SAVE_FAILED');
        }

        $hash = sha1((string)$result['xml']);
        $snap = $this->_build_payload_snapshot($result['xml']);

        if ($state === null) {
            $this->_set_submission_state($claim_id, 'GENERATED', array(
                'file' => $filePath,
                'hash' => $hash,
                'payload_snapshot' => $snap,
            ));
        }

        $this->_log_submission($claim_id, $filePath, $hash);

        $this->_set_submission_state($claim_id, 'VALIDATED', array(
            'validation_hash' => $hash,
            'file' => $filePath,
            'hash' => $hash,
            'payload_snapshot' => $snap,
        ));

        $attempt = $this->_get_next_attempt($claim_id);
        if (!$this->_is_validation_hash_current($claim_id, $filePath, $hash)) {
            return array('success' => false, 'error' => 'FORCE_REVALIDATION');
        }
        $this->_set_submission_state($claim_id, 'SUBMITTED', array(
            'attempt' => $attempt,
            'file' => $filePath,
            'hash' => $hash,
            'payload_snapshot' => $snap,
        ));

        return array(
            'success' => true,
            'file' => $filePath,
        );
    }

    public function record_claim_response($claim_id, $status, $message = null, $code = null)
    {
        $claim_id = (int)$claim_id;

        $payload = null;
        if (is_array($status)) {
            $payload = $status;
        } else {
            $payload = array(
                'status' => (string)$status,
                'code' => $code !== null ? (string)$code : null,
                'message' => $message !== null ? (string)$message : null,
            );
        }

        $st = isset($payload['status']) ? $this->_normalize_status((string)$payload['status']) : '';
        $cd = isset($payload['code']) ? trim((string)$payload['code']) : '';
        $msg = isset($payload['message']) ? (string)$payload['message'] : '';
        $cat = isset($payload['category']) ? trim((string)$payload['category']) : '';
        $sev = isset($payload['severity']) ? trim((string)$payload['severity']) : '';

        if ($claim_id <= 0) {
            return array('success' => false, 'error' => 'INVALID_CLAIM_ID');
        }

        if ($st === '') {
            return array('success' => false, 'error' => 'INVALID_STATUS');
        }

        $ok = $this->_insert_audit(
            'claim_response',
            'claim',
            $claim_id,
            $this->_get_claim_patient_no($claim_id),
            json_encode(array(
                'status' => $st,
                'code' => $cd !== '' ? $cd : null,
                'category' => $cat !== '' ? strtoupper($cat) : null,
                'severity' => $sev !== '' ? strtoupper($sev) : null,
                'message' => $msg,
                'timestamp' => date('Y-m-d H:i:s'),
            )),
            null,
            null,
            'active',
            null
        );

        if (!empty($ok)) {
            if ($st === 'ACCEPTED') {
                $this->_set_submission_state($claim_id, 'ACCEPTED');
            } elseif ($st === 'REJECTED') {
                $this->_set_submission_state($claim_id, 'REJECTED');
            }
        }

        return array('success' => (bool)$ok);
    }

    private function _save_xml_file($claim_id, $xml)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $dir = APPPATH . 'exports/nhis/';
        if (!is_dir($dir)) {
            $mk = @mkdir($dir, 0777, true);
            if (!$mk && !is_dir($dir)) {
                return null;
            }
        }

        $file = $dir . 'claim_' . $claim_id . '_' . time() . '.xml';
        $written = @file_put_contents($file, $xml);
        if ($written === false) {
            return null;
        }

        return $file;
    }

    private function _log_submission($claim_id, $file, $hash)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return;
        }

        $this->_insert_audit(
            'xml_generated',
            'claim',
            $claim_id,
            $this->_get_claim_patient_no($claim_id),
            json_encode(array(
                'file' => (string)$file,
                'hash' => $hash !== null ? (string)$hash : null,
            )),
            null,
            null,
            'active',
            null
        );

        return true;
    }

    private function _set_submission_state($claim_id, $state, $meta = array())
    {
        $claim_id = (int)$claim_id;
        $state = strtoupper(trim((string)$state));
        if ($claim_id <= 0 || $state === '') {
            return;
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $row = $this->db->select($pk . ', new_value')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'submission_state')
            ->where('status', 'active')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        $current = null;
        $current_payload = array();
        if ($row && isset($row->new_value)) {
            $decoded = json_decode((string)$row->new_value, true);
            if (is_array($decoded)) {
                $current_payload = $decoded;
                if (!empty($decoded['state'])) {
                    $current = strtoupper(trim((string)$decoded['state']));
                }
            }
        }

        if (!$this->_is_valid_transition($current, $state)) {
            log_message('error', '[NHIS_INVALID_STATE_TRANSITION] from=' . ($current !== null ? $current : 'null') . ' to=' . $state);
            return false;
        }

        $payload = array('state' => $state);
        if (is_array($meta)) {
            foreach ($meta as $k => $v) {
                if ($k === 'state') {
                    continue;
                }
                $payload[$k] = $v;
            }
        }

        if ($current !== null && $current === $state && !empty($row) && isset($row->{$pk})) {
            $this->db->where($pk, (int)$row->{$pk})->update('nhis_audit_log', array('new_value' => json_encode($payload)));
            return true;
        }

        $this->db->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'submission_state')
            ->where('status', 'active')
            ->update('nhis_audit_log', array('status' => 'superseded'));

        $this->_insert_audit(
            'submission_state',
            'claim',
            $claim_id,
            $this->_get_claim_patient_no($claim_id),
            json_encode($payload),
            null,
            null,
            'active',
            null
        );
    }

    private function _get_latest_submission_state($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $row = $this->db->select('new_value')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'submission_state')
            ->where('status', 'active')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        if (!$row || !isset($row->new_value)) {
            return null;
        }

        $decoded = json_decode((string)$row->new_value, true);
        if (!is_array($decoded) || empty($decoded['state'])) {
            return null;
        }

        return strtoupper(trim((string)$decoded['state']));
    }

    private function _get_active_submission_state_row($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return array('state' => null, 'created_at' => null, 'payload' => array());
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $row = $this->db->select('new_value, created_at')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'submission_state')
            ->where('status', 'active')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        if (!$row) {
            return array('state' => null, 'created_at' => null, 'payload' => array());
        }

        $payload = array();
        if (isset($row->new_value)) {
            $decoded = json_decode((string)$row->new_value, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $st = null;
        if (!empty($payload['state'])) {
            $st = strtoupper(trim((string)$payload['state']));
        }

        return array(
            'state' => $st,
            'created_at' => isset($row->created_at) ? (string)$row->created_at : null,
            'payload' => $payload,
        );
    }

    private function _get_latest_generated_artifact($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $row = $this->db->select('new_value')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'xml_generated')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        if (!$row || !isset($row->new_value)) {
            return null;
        }

        $decoded = json_decode((string)$row->new_value, true);
        if (!is_array($decoded) || empty($decoded['file'])) {
            return array('file' => null, 'hash' => null);
        }

        $file = (string)$decoded['file'];
        if ($file === '' || !is_file($file)) {
            return array('file' => null, 'hash' => isset($decoded['hash']) ? (string)$decoded['hash'] : null);
        }

        return array(
            'file' => $file,
            'hash' => isset($decoded['hash']) ? (string)$decoded['hash'] : null,
        );
    }

    private function _verify_file_integrity($file, $expected_hash, $computed_hash)
    {
        $file = (string)$file;
        $expected_hash = $expected_hash !== null ? (string)$expected_hash : '';
        if ($file === '' || !is_file($file)) {
            return false;
        }
        if ($computed_hash === null || $computed_hash === '') {
            return false;
        }
        if ($expected_hash === '') {
            return false;
        }
        return $computed_hash === $expected_hash;
    }

    private function _compute_file_hash($file)
    {
        $file = (string)$file;
        if ($file === '' || !is_file($file)) {
            return null;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        return sha1($content);
    }

    private function _build_payload_snapshot($xml)
    {
        libxml_use_internal_errors(true);
        $x = simplexml_load_string((string)$xml);
        if (!$x) {
            libxml_clear_errors();
            return null;
        }

        $service_count = 0;
        $diagnosis_count = 0;
        $total = (float)$x->Totals->TotalCost;

        if (!empty($x->Services->Service)) {
            foreach ($x->Services->Service as $s) {
                $service_count++;
            }
        }

        if (!empty($x->Diagnoses->Diagnosis)) {
            foreach ($x->Diagnoses->Diagnosis as $d) {
                $diagnosis_count++;
            }
        }

        libxml_clear_errors();

        return array(
            'total' => round($total, 2),
            'service_count' => (int)$service_count,
            'diagnosis_count' => (int)$diagnosis_count,
        );
    }

    private function _build_payload_snapshot_from_file($file)
    {
        $file = (string)$file;
        if ($file === '' || !is_file($file)) {
            return null;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        return $this->_build_payload_snapshot($content);
    }

    private function _is_validation_hash_current($claim_id, $file, $fileHash)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return false;
        }
        $file = (string)$file;
        if ($file === '' || !is_file($file)) {
            return false;
        }
        if ($fileHash === null || $fileHash === '') {
            return false;
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $row = $this->db->select('new_value')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'submission_state')
            ->where('status', 'active')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        if (!$row || !isset($row->new_value)) {
            return false;
        }

        $decoded = json_decode((string)$row->new_value, true);
        if (!is_array($decoded)) {
            return false;
        }

        if (empty($decoded['state']) || strtoupper(trim((string)$decoded['state'])) !== 'VALIDATED') {
            return false;
        }

        $vh = isset($decoded['validation_hash']) ? (string)$decoded['validation_hash'] : '';
        if ($vh === '') {
            return false;
        }

        return $vh === $fileHash;
    }

    private function _is_submission_timeout_expired($activeStateRow)
    {
        if (!is_array($activeStateRow) || empty($activeStateRow['created_at'])) {
            return false;
        }

        $ts = strtotime((string)$activeStateRow['created_at']);
        if ($ts === false) {
            return false;
        }

        $mins = (int)$this->submission_timeout_minutes;
        if ($mins <= 0) {
            $mins = 20;
        }

        return (time() - $ts) >= ($mins * 60);
    }

    private function _has_claim_response_after($claim_id, $since)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return false;
        }

        $sinceTs = strtotime((string)$since);
        if ($sinceTs === false) {
            return false;
        }

        $this->db->from('nhis_audit_log');
        $this->db->where('reference_type', 'claim');
        $this->db->where('reference_id', $claim_id);
        $this->db->where('action_type', 'claim_response');
        $this->db->where('created_at >=', date('Y-m-d H:i:s', $sinceTs));
        $this->db->limit(1);
        $row = $this->db->get()->row();
        return !empty($row);
    }

    private function _is_valid_transition($from, $to)
    {
        $from = $from !== null ? strtoupper(trim((string)$from)) : null;
        $to = strtoupper(trim((string)$to));

        $allowed = array(
            null => array('GENERATED'),
            'GENERATED' => array('VALIDATED'),
            'VALIDATED' => array('SUBMITTED'),
            'SUBMITTED' => array('ACCEPTED', 'REJECTED'),
            'REJECTED' => array('VALIDATED'),
            'ACCEPTED' => array(),
        );

        if ($from !== null && $from === $to) {
            return true;
        }

        $list = isset($allowed[$from]) ? $allowed[$from] : array();
        return in_array($to, $list, true);
    }

    private function _is_regeneration_allowed($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return false;
        }

        $pk = $this->_get_nhis_audit_pk_column();

        $gen = $this->db->select('created_at')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'xml_generated')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        if (!$gen || empty($gen->created_at)) {
            return true;
        }

        $fix = $this->db->select('created_at')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where_in('action_type', array('revalidation_passed', 'enforcement_release'))
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get()->row();

        if (!$fix || empty($fix->created_at)) {
            return false;
        }

        $genTs = strtotime((string)$gen->created_at);
        $fixTs = strtotime((string)$fix->created_at);
        if ($genTs === false || $fixTs === false) {
            return false;
        }

        return $fixTs > $genTs;
    }

    private function _has_prior_xml_generation($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return false;
        }

        $this->db->from('nhis_audit_log');
        $this->db->where('reference_type', 'claim');
        $this->db->where('reference_id', $claim_id);
        $this->db->where('action_type', 'xml_generated');
        $this->db->limit(1);
        $row = $this->db->get()->row();
        return !empty($row);
    }

    private function _get_next_attempt($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return 1;
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $rows = $this->db->select('new_value')
            ->from('nhis_audit_log')
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'submission_state')
            ->order_by($pk, 'DESC')
            ->limit(25)
            ->get()->result();

        if (empty($rows)) {
            return 1;
        }

        foreach ($rows as $row) {
            if (!$row || !isset($row->new_value)) {
                continue;
            }
            $decoded = json_decode((string)$row->new_value, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (empty($decoded['state']) || strtoupper(trim((string)$decoded['state'])) !== 'SUBMITTED') {
                continue;
            }
            if (empty($decoded['attempt'])) {
                return 1;
            }
            $a = (int)$decoded['attempt'];
            if ($a <= 0) {
                return 1;
            }
            return $a + 1;
        }

        return 1;
    }

    private function _normalize_status($status)
    {
        $s = strtoupper(trim((string)$status));
        if ($s === '') {
            return '';
        }
        if (in_array($s, array('APPROVED', 'SUCCESS', 'OK'), true)) {
            return 'ACCEPTED';
        }
        if (in_array($s, array('FAILED', 'ERROR', 'DECLINED'), true)) {
            return 'REJECTED';
        }
        if ($s === 'ACCEPTED' || $s === 'REJECTED') {
            return $s;
        }
        return $s;
    }

    private function _get_claim_patient_no($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $row = $this->db->select('patient_no')->where('id', $claim_id)->get('nhis_claims')->row();
        if ($row && isset($row->patient_no) && (string)$row->patient_no !== '') {
            return (string)$row->patient_no;
        }

        return null;
    }

    private function _insert_audit($action, $ref_type, $ref_id, $patient_no = null, $new_value = null, $request = null, $response = null, $status = 'success', $error = null)
    {
        $CI = &get_instance();
        if (isset($CI->config) && method_exists($CI->config, 'item')) {
            if (!$CI->config->item('nhis_audit_enabled')) {
                return true;
            }
        }

        $performed_by = null;
        try {
            if (isset($CI->session) && method_exists($CI->session, 'userdata')) {
                $performed_by = $CI->session->userdata('user_id');
            }
        } catch (Exception $e) {
        }

        $ip = null;
        try {
            if (isset($CI->input) && method_exists($CI->input, 'ip_address')) {
                $ip = $CI->input->ip_address();
            }
        } catch (Exception $e) {
        }

        return $this->db->insert('nhis_audit_log', array(
            'action_type' => $action,
            'reference_type' => $ref_type,
            'reference_id' => $ref_id,
            'patient_no' => $patient_no,
            'new_value' => $new_value,
            'api_request' => $request,
            'api_response' => $response,
            'status' => $status,
            'error_message' => $error,
            'performed_by' => $performed_by,
            'ip_address' => $ip,
        ));
    }

    private function _get_nhis_audit_pk_column()
    {
        if ($this->nhis_audit_pk_column !== null) {
            return $this->nhis_audit_pk_column;
        }

        $pk = 'id';
        try {
            if (method_exists($this->db, 'field_exists')) {
                if ($this->db->field_exists('id', 'nhis_audit_log')) {
                    $pk = 'id';
                } elseif ($this->db->field_exists('audit_id', 'nhis_audit_log')) {
                    $pk = 'audit_id';
                }
            }
        } catch (Exception $e) {
        }

        $this->nhis_audit_pk_column = $pk;
        return $this->nhis_audit_pk_column;
    }
}
