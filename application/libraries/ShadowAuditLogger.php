<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowAuditLogger
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function log($data)
	{
		if (!is_array($data)) {
			return false;
		}
		if (!isset($this->CI->db) || !is_object($this->CI->db)) {
			return false;
		}
		if (!isset($this->CI->config)) {
			return false;
		}

		$this->CI->config->load('shadow_audit', true);
		$enabled = (bool)$this->CI->config->item('shadow_audit_enabled', 'shadow_audit');
		if (!$enabled) {
			return false;
		}
		$table = (string)$this->CI->config->item('shadow_audit_table', 'shadow_audit');
		if ($table === '') {
			$table = 'shadow_audit_log';
		}

		$domain = isset($data['domain']) ? (string)$data['domain'] : '';
		$intent = isset($data['intent']) ? (string)$data['intent'] : '';
		$parity = (isset($data['parity']) && is_array($data['parity'])) ? $data['parity'] : array();
		$proof = (isset($data['proof']) && is_array($data['proof'])) ? $data['proof'] : null;
		$severity = isset($data['severity']) ? (string)$data['severity'] : 'INFO';

		$payload_json = json_encode($data);
		if ($payload_json === false) {
			$payload_json = '{}';
		}

		$row = array(
			'created_at' => date('Y-m-d H:i:s'),
			'domain' => $domain,
			'intent' => $intent,
			'parity_status' => isset($parity['status']) ? (string)$parity['status'] : 'UNPROVABLE',
			'parity_code' => isset($parity['code']) ? (string)$parity['code'] : 'PARITY_MISSING',
			'proof_status' => (is_array($proof) && isset($proof['status'])) ? (string)$proof['status'] : 'UNPROVABLE',
			'proof_code' => (is_array($proof) && isset($proof['code'])) ? (string)$proof['code'] : 'PROOF_MISSING',
			'severity' => $severity,
			'request_id' => isset($data['request_id']) ? (string)$data['request_id'] : null,
			'user_id' => isset($data['user_id']) ? $data['user_id'] : null,
			'payload' => $payload_json
		);

		try {
			$this->beginInternalWrite();
			$prev_db_debug = null;
			try {
				if (isset($this->CI->db) && is_object($this->CI->db) && property_exists($this->CI->db, 'db_debug')) {
					$prev_db_debug = $this->CI->db->db_debug;
					$this->CI->db->db_debug = false;
				}
				$ok = $this->CI->db->insert($table, $row);
			} finally {
				if ($prev_db_debug !== null) {
					$this->CI->db->db_debug = $prev_db_debug;
				}
				$this->endInternalWrite();
			}
			return (bool)$ok;
		} catch (Exception $e) {
			log_message('error', '[SHADOW_AUDIT_FAIL] ' . $e->getMessage());
			return false;
		}
	}

	protected function beginInternalWrite()
	{
		if (isset($this->CI->db) && is_object($this->CI->db) && method_exists($this->CI->db, 'shadow_begin_internal_write')) {
			$this->CI->db->shadow_begin_internal_write();
		}
	}

	protected function endInternalWrite()
	{
		if (isset($this->CI->db) && is_object($this->CI->db) && method_exists($this->CI->db, 'shadow_end_internal_write')) {
			$this->CI->db->shadow_end_internal_write();
		}
	}
}
