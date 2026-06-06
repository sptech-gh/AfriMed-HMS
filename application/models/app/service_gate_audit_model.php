<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Service_gate_audit_model extends CI_Model
{
	private $audit_db;

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->audit_db = $this->load->database('', true);
	}

	private function _index_exists($table, $index)
	{
		try {
			$db = isset($this->audit_db) ? $this->audit_db : $this->db;
			$q = $db->query(
				"SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1",
				array((string)$table, (string)$index)
			);
			$row = $q ? $q->row_array() : null;
			return !empty($row);
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function _ensure_column($table, $column, $ddl)
	{
		$db = isset($this->audit_db) ? $this->audit_db : $this->db;
		if (!$db->table_exists($table)) {
			return false;
		}
		if ($db->field_exists($column, $table)) {
			return true;
		}
		$db->query("ALTER TABLE `{$table}` ADD COLUMN {$ddl}");
		return $db->field_exists($column, $table);
	}

	public function ensure_schema()
	{
		$db = isset($this->audit_db) ? $this->audit_db : $this->db;
		if (!$db->table_exists('service_gate_audit')) {
			$db->query("CREATE TABLE IF NOT EXISTS `service_gate_audit` (
				`id` bigint(20) NOT NULL AUTO_INCREMENT,
				`event_code` varchar(50) NOT NULL,
				`module` varchar(20) DEFAULT NULL,
				`item_ref` varchar(100) DEFAULT NULL,
				`action` varchar(100) DEFAULT NULL,
				`user_id` int(11) DEFAULT NULL,
				`blocked_reason` varchar(50) DEFAULT NULL,
				`allowed` tinyint(1) DEFAULT NULL,
				`gate_version` varchar(10) DEFAULT 'v1',
				`payload_json` json DEFAULT NULL,
				`reason` varchar(50) DEFAULT NULL,
				`payload` json DEFAULT NULL,
				`ip` varchar(45) DEFAULT NULL,
				`created_at` datetime NOT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_item_ref` (`item_ref`),
				KEY `idx_event_code` (`event_code`),
				KEY `idx_user_id` (`user_id`),
				KEY `idx_created_at` (`created_at`),
				KEY `idx_module_event` (`module`,`event_code`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}

		if (!$db->table_exists('service_gate_audit')) {
			return false;
		}

		$this->_ensure_column('service_gate_audit', 'module', "`module` varchar(20) DEFAULT NULL AFTER `event_code`");
		$this->_ensure_column('service_gate_audit', 'blocked_reason', "`blocked_reason` varchar(50) DEFAULT NULL AFTER `user_id`");
		$this->_ensure_column('service_gate_audit', 'allowed', "`allowed` tinyint(1) DEFAULT NULL AFTER `blocked_reason`");
		$this->_ensure_column('service_gate_audit', 'gate_version', "`gate_version` varchar(10) DEFAULT 'v1' AFTER `allowed`");
		$this->_ensure_column('service_gate_audit', 'payload_json', "`payload_json` json DEFAULT NULL AFTER `gate_version`");

		if (!$this->_index_exists('service_gate_audit', 'idx_item_ref')) {
			$db->query('CREATE INDEX `idx_item_ref` ON `service_gate_audit` (`item_ref`)');
		}
		if (!$this->_index_exists('service_gate_audit', 'idx_event_code')) {
			$db->query('CREATE INDEX `idx_event_code` ON `service_gate_audit` (`event_code`)');
		}
		if (!$this->_index_exists('service_gate_audit', 'idx_created_at')) {
			$db->query('CREATE INDEX `idx_created_at` ON `service_gate_audit` (`created_at`)');
		}
		if (!$this->_index_exists('service_gate_audit', 'idx_user_id')) {
			$db->query('CREATE INDEX `idx_user_id` ON `service_gate_audit` (`user_id`)');
		}
		if (!$this->_index_exists('service_gate_audit', 'idx_module_event')) {
			$db->query('CREATE INDEX `idx_module_event` ON `service_gate_audit` (`module`,`event_code`)');
		}

		return true;
	}

	public function log_event($event)
	{
		$db = isset($this->audit_db) ? $this->audit_db : $this->db;
		$this->ensure_schema();
		if (!$db->table_exists('service_gate_audit')) {
			return false;
		}

		$event_code = isset($event['event_code']) ? trim((string)$event['event_code']) : '';
		if ($event_code === '') {
			return false;
		}

		$module = array_key_exists('module', $event) ? $event['module'] : null;
		$module = ($module === null) ? null : strtoupper(trim((string)$module));
		if ($module === '') {
			$module = null;
		}

		$item_ref = array_key_exists('item_ref', $event) ? $event['item_ref'] : null;
		$item_ref = ($item_ref === null) ? null : trim((string)$item_ref);
		if ($item_ref === '') {
			$item_ref = null;
		}

		$user_id = array_key_exists('user_id', $event) ? $event['user_id'] : null;
		$user_id = ($user_id === null || $user_id === '') ? null : (int)$user_id;

		$action = array_key_exists('action', $event) ? $event['action'] : null;
		$action = ($action === null) ? null : trim((string)$action);
		if ($action === '') {
			$action = null;
		}

		$blocked_reason = array_key_exists('blocked_reason', $event) ? $event['blocked_reason'] : null;
		$blocked_reason = ($blocked_reason === null) ? null : trim((string)$blocked_reason);
		if ($blocked_reason === '') {
			$blocked_reason = null;
		}

		$allowed = array_key_exists('allowed', $event) ? $event['allowed'] : null;
		if ($allowed !== null) {
			$allowed = ((int)(bool)$allowed);
		}

		$gate_version = array_key_exists('gate_version', $event) ? $event['gate_version'] : 'v1';
		$gate_version = trim((string)$gate_version);
		if ($gate_version === '') {
			$gate_version = 'v1';
		}

		$payload_json = array_key_exists('payload_json', $event) ? $event['payload_json'] : (array_key_exists('payload', $event) ? $event['payload'] : null);
		if ($payload_json !== null && !is_string($payload_json)) {
			$payload_json = json_encode($payload_json);
		}

		$reason = array_key_exists('reason', $event) ? $event['reason'] : null;
		$reason = ($reason === null) ? null : trim((string)$reason);
		if ($reason === '') {
			$reason = null;
		}

		$ip = array_key_exists('ip', $event) ? $event['ip'] : null;
		$ip = ($ip === null) ? null : trim((string)$ip);
		if ($ip === '') {
			$ip = null;
		}

		$insert = array(
			'event_code' => $event_code,
			'module' => $module,
			'item_ref' => $item_ref,
			'action' => $action,
			'user_id' => $user_id,
			'blocked_reason' => $blocked_reason,
			'allowed' => $allowed,
			'gate_version' => $gate_version,
			'payload_json' => $payload_json,
			'reason' => $reason !== null ? $reason : $blocked_reason,
			'payload' => $payload_json,
			'ip' => $ip,
			'created_at' => date('Y-m-d H:i:s'),
		);

		$fields = $db->list_fields('service_gate_audit');
		foreach (array_keys($insert) as $k) {
			if (!in_array($k, $fields, true)) {
				unset($insert[$k]);
			}
		}

		$db->insert('service_gate_audit', $insert);

		return $db->affected_rows() > 0;
	}
}
