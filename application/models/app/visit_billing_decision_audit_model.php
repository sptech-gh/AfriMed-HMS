<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Visit_billing_decision_audit_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	public function ensure_schema()
	{
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `visit_billing_decision_audit` (
				`id` bigint(20) NOT NULL AUTO_INCREMENT,
				`iop_id` varchar(25) NOT NULL,
				`patient_no` varchar(25) NOT NULL,
				`decision_type` varchar(40) NOT NULL,
				`decision` varchar(20) NOT NULL,
				`reason` varchar(255) DEFAULT NULL,
				`matched_rule` varchar(120) DEFAULT NULL,
				`context_json` text DEFAULT NULL,
				`actor_user_id` varchar(25) DEFAULT NULL,
				`correlation_id` varchar(80) DEFAULT NULL,
				`created_at` datetime NOT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_iop` (`iop_id`,`InActive`),
				KEY `idx_patient` (`patient_no`,`InActive`),
				KEY `idx_decision_type` (`decision_type`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} catch (Throwable $e) {
		}
		if ($prev !== null) { $this->db->db_debug = $prev; }
	}

	public function log_decision($iop_id, $patient_no, $decision_type, $decision, $reason = null, $matched_rule = null, array $context = array(), $actor_user_id = null, $correlation_id = null)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		if ($iop_id === '' || $patient_no === '') {
			return array('ok' => false, 'error' => 'Missing iop_id or patient_no');
		}
		$row = array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'decision_type' => trim((string)$decision_type),
			'decision' => trim((string)$decision),
			'reason' => $reason !== null ? substr(trim((string)$reason), 0, 255) : null,
			'matched_rule' => $matched_rule !== null ? substr(trim((string)$matched_rule), 0, 120) : null,
			'context_json' => !empty($context) ? json_encode($context) : null,
			'actor_user_id' => $actor_user_id !== null ? trim((string)$actor_user_id) : null,
			'correlation_id' => $correlation_id !== null ? substr(trim((string)$correlation_id), 0, 80) : null,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive' => 0,
		);
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->insert('visit_billing_decision_audit', $row);
			return array('ok' => true, 'id' => (int)$this->db->insert_id());
		} catch (Throwable $e) {
			return array('ok' => false, 'error' => 'Insert failed');
		} finally {
			if ($prev !== null) { $this->db->db_debug = $prev; }
		}
	}
}
