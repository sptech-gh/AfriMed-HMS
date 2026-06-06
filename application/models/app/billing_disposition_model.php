<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Billing_disposition_model extends CI_Model
{
	private $_shadow_enabled_cache = null;
	private $_shadow_enabled_source = null;
	private $_enforcement_enabled_cache = null;
	private $_enforcement_enabled_source = null;

	public function __construct()
	{
		parent::__construct();
	}

	public function is_shadow_enabled()
	{
		if ($this->_shadow_enabled_cache !== null) return (bool)$this->_shadow_enabled_cache;
		$env = getenv('BILLING_DISPOSITION_SHADOW');
		if ($env !== false && trim((string)$env) !== '') {
			$parsed = $this->_parse_bool($env, null);
			if ($parsed !== null) {
				$this->_shadow_enabled_cache = (bool)$parsed;
				$this->_shadow_enabled_source = 'env';
				return (bool)$this->_shadow_enabled_cache;
			}
		}
		$cfg = $this->config->item('BILLING_DISPOSITION_SHADOW');
		$this->_shadow_enabled_cache = (bool)$cfg;
		$this->_shadow_enabled_source = 'config';
		return (bool)$this->_shadow_enabled_cache;
	}

	public function is_enforcement_enabled()
	{
		if ($this->_enforcement_enabled_cache !== null) return (bool)$this->_enforcement_enabled_cache;
		$env = getenv('BILLING_DISPOSITION_ENFORCEMENT');
		if ($env !== false && trim((string)$env) !== '') {
			$parsed = $this->_parse_bool($env, null);
			if ($parsed !== null) {
				$this->_enforcement_enabled_cache = (bool)$parsed;
				$this->_enforcement_enabled_source = 'env';
				return (bool)$this->_enforcement_enabled_cache;
			}
		}
		$cfg = $this->config->item('BILLING_DISPOSITION_ENFORCEMENT');
		$this->_enforcement_enabled_cache = (bool)$cfg;
		$this->_enforcement_enabled_source = 'config';
		return (bool)$this->_enforcement_enabled_cache;
	}

	private function _parse_bool($raw, $default)
	{
		if ($raw === null) return $default;
		$raw = strtolower(trim((string)$raw));
		if ($raw === '') return $default;
		if (in_array($raw, array('1','true','on','yes','y'), true)) return true;
		if (in_array($raw, array('0','false','off','no','n'), true)) return false;
		$val = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($val === null) return $default;
		return (bool)$val;
	}

	public function ensure_billing_disposition_schema()
	{
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `billing_dispositions` (
				`disp_id` bigint(20) NOT NULL AUTO_INCREMENT,
				`txn_id` bigint(20) NOT NULL,
				`from_state` varchar(50) DEFAULT NULL,
				`to_state` varchar(50) NOT NULL,
				`reason` varchar(255) DEFAULT NULL,
				`actor_user_id` varchar(25) DEFAULT NULL,
				`source_module` varchar(50) DEFAULT NULL,
				`source_ref` varchar(120) DEFAULT NULL,
				`correlation_id` varchar(80) DEFAULT NULL,
				`approved_by` varchar(25) DEFAULT NULL,
				`approved_at` datetime DEFAULT NULL,
				`created_at` datetime NOT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`disp_id`),
				KEY `idx_txn_created` (`txn_id`,`created_at`),
				KEY `idx_txn_disp` (`txn_id`,`disp_id`),
				KEY `idx_to_state` (`to_state`),
				KEY `idx_correlation` (`correlation_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} catch (Exception $e) {
		}
		if ($prev !== null) { $this->db->db_debug = $prev; }
	}

	public function append_event(array $payload)
	{
		$this->ensure_billing_disposition_schema();
		$out = array(
			'ok' => true,
			'inserted' => false,
			'disp_id' => null,
			'compliance_status' => 'UNPROVABLE',
			'escalate_shadow' => false,
			'errors' => array(),
		);

		$txn_id = isset($payload['txn_id']) ? (int)$payload['txn_id'] : 0;
		$to_state = isset($payload['to_state']) ? strtoupper(trim((string)$payload['to_state'])) : '';
		$from_state = isset($payload['from_state']) ? strtoupper(trim((string)$payload['from_state'])) : '';
		$from_state_source = isset($payload['from_state_source']) ? trim((string)$payload['from_state_source']) : '';
		$actor_user_id = isset($payload['actor_user_id']) ? trim((string)$payload['actor_user_id']) : '';
		$reason = isset($payload['reason']) ? trim((string)$payload['reason']) : '';
		$source_module = isset($payload['source_module']) ? strtoupper(trim((string)$payload['source_module'])) : '';
		$source_ref = isset($payload['source_ref']) ? trim((string)$payload['source_ref']) : '';
		$correlation_id = isset($payload['correlation_id']) ? trim((string)$payload['correlation_id']) : '';

		$this->_auto_bootstrap_if_missing($txn_id, $actor_user_id);

		if ($txn_id <= 0) {
			$out['errors'][] = 'missing_txn_id';
		}
		if ($to_state === '') {
			$out['errors'][] = 'missing_to_state';
		}
		if ($from_state_source === '') { $from_state_source = ($from_state !== '') ? 'payload' : 'missing'; }
		if ($from_state === '') {
			$cur = null;
			try { $cur = $this->resolve_current_state($txn_id); } catch (Exception $e) { $cur = null; }
			if (is_array($cur) && isset($cur['to_state']) && trim((string)$cur['to_state']) !== '') {
				$from_state = strtoupper(trim((string)$cur['to_state']));
				$from_state_source = 'resolved_current';
			}
		}

		$auditMissing = array();
		if ($actor_user_id === '') $auditMissing[] = 'actor_user_id';
		if ($source_module === '') $auditMissing[] = 'source_module';
		if ($source_ref === '') $auditMissing[] = 'source_ref';
		if ($correlation_id === '') $auditMissing[] = 'correlation_id';
		if (!empty($auditMissing)) {
			$out['compliance_status'] = 'MISSING_AUDIT';
			$out['escalate_shadow'] = true;
			$out['errors'][] = 'missing_audit_fields:' . implode(',', $auditMissing);
		}

		if ($out['compliance_status'] !== 'MISSING_AUDIT') {
			if ($to_state === '' || $txn_id <= 0) {
				$out['compliance_status'] = 'VIOLATION';
				$out['escalate_shadow'] = true;
			} else if ($from_state === '') {
				$out['compliance_status'] = 'UNPROVABLE';
				$out['escalate_shadow'] = true;
			} else {
				$chk = $this->validate_transition($from_state, $to_state);
				if (!is_array($chk) || empty($chk['ok'])) {
					$out['compliance_status'] = 'VIOLATION';
					$out['escalate_shadow'] = true;
					if (is_array($chk) && isset($chk['error']) && trim((string)$chk['error']) !== '') {
						$out['errors'][] = (string)$chk['error'];
					}
				} else {
					$out['compliance_status'] = 'PASS';
				}
			}
		}

		$insertOk = ($txn_id > 0 && $to_state !== '' && $out['compliance_status'] === 'PASS');
		if ($insertOk && $this->table_exists('billing_dispositions')) {
			$row = array(
				'txn_id' => $txn_id,
				'from_state' => $from_state !== '' ? $from_state : null,
				'to_state' => $to_state,
				'reason' => $reason !== '' ? $reason : null,
				'actor_user_id' => $actor_user_id !== '' ? $actor_user_id : null,
				'source_module' => $source_module !== '' ? $source_module : null,
				'source_ref' => $source_ref !== '' ? $source_ref : null,
				'correlation_id' => $correlation_id !== '' ? $correlation_id : null,
				'created_at' => date('Y-m-d H:i:s'),
				'InActive' => 0,
			);
			$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($prev !== null) { $this->db->db_debug = false; }
			try {
				$this->db->insert('billing_dispositions', $row);
				$out['inserted'] = true;
				$out['disp_id'] = (int)$this->db->insert_id();
			} catch (Exception $e) {
				$out['errors'][] = 'insert_failed';
				$out['escalate_shadow'] = true;
			} finally {
				if ($prev !== null) { $this->db->db_debug = $prev; }
			}
		}

		if ($this->is_shadow_enabled()) {
			$details = array(
				'schema_version' => 1,
				'mode' => $this->is_enforcement_enabled() ? 'ENFORCEMENT' : 'SHADOW',
				'txn_id' => $txn_id,
				'from_state' => $from_state !== '' ? $from_state : null,
				'from_state_source' => $from_state_source,
				'to_state' => $to_state !== '' ? $to_state : null,
				'inserted' => (bool)$out['inserted'],
				'disp_id' => $out['disp_id'],
				'compliance_status' => (string)$out['compliance_status'],
				'escalate_shadow' => (bool)$out['escalate_shadow'],
				'correlation_id' => $correlation_id !== '' ? $correlation_id : null,
				'source_module' => $source_module !== '' ? $source_module : null,
				'source_ref' => $source_ref !== '' ? $source_ref : null,
				'errors' => $out['errors'],
			);
			$issue = $out['escalate_shadow'] ? 'LAB_DISPOSITION_ESCALATE_SHADOW' : 'LAB_DISPOSITION_SHADOW';
			$this->log_reconciliation($issue, $source_ref !== '' ? $source_ref : ('txn_id:' . $txn_id), null, null, $details, $actor_user_id !== '' ? $actor_user_id : null);
		}

		if ($this->is_enforcement_enabled() && $out['compliance_status'] !== 'PASS') {
			$out['ok'] = false;
			throw new RuntimeException('billing_disposition_enforcement:' . (string)$out['compliance_status'] . ':' . implode('|', $out['errors']));
		}

		return $out;
	}

	private function _auto_bootstrap_if_missing($txn_id, $actor_user_id)
	{
		$txn_id = (int)$txn_id;
		if ($txn_id <= 0) return;
		if (!$this->table_exists('billing_dispositions')) return;
		$actor = trim((string)$actor_user_id);
		if ($actor === '') $actor = 'SYSTEM';
		$source_module = 'SYSTEM_BOOTSTRAP';
		$source_ref = 'AUTO_INIT';
		$reason = 'AUTO_INITIALIZATION';
		$cid = 'autobootstrap:' . $txn_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
		$now = date('Y-m-d H:i:s');

		$chk = $this->validate_transition('BOOTSTRAP', 'NORMAL_BILLABLE');
		if (!is_array($chk) || empty($chk['ok'])) return;

		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$sql = "INSERT INTO billing_dispositions (txn_id, from_state, to_state, reason, actor_user_id, source_module, source_ref, correlation_id, created_at, InActive)\n\t\t\t\tSELECT ?, 'BOOTSTRAP', 'NORMAL_BILLABLE', ?, ?, ?, ?, ?, ?, 0\n\t\t\t\tFROM DUAL\n\t\t\t\tWHERE NOT EXISTS (SELECT 1 FROM billing_dispositions WHERE txn_id = ? AND InActive = 0 LIMIT 1)";
			$this->db->query($sql, array(
				$txn_id,
				$reason,
				$actor,
				$source_module,
				$source_ref,
				$cid,
				$now,
				$txn_id,
			));

			if ($this->is_shadow_enabled() && $this->db->affected_rows() > 0) {
				$this->log_reconciliation('LAB_DISPOSITION_BOOTSTRAP', 'txn_id:' . $txn_id, null, null, array(
					'schema_version' => 1,
					'mode' => $this->is_enforcement_enabled() ? 'ENFORCEMENT' : 'SHADOW',
					'txn_id' => $txn_id,
					'from_state' => 'BOOTSTRAP',
					'to_state' => 'NORMAL_BILLABLE',
					'source_module' => $source_module,
					'source_ref' => $source_ref,
					'correlation_id' => $cid,
				), $actor);
			}
		} catch (Exception $e) {
			if ($this->is_shadow_enabled()) {
				$this->log_reconciliation('LAB_DISPOSITION_BOOTSTRAP_FAILED', 'txn_id:' . $txn_id, null, null, array(
					'schema_version' => 1,
					'mode' => $this->is_enforcement_enabled() ? 'ENFORCEMENT' : 'SHADOW',
					'txn_id' => $txn_id,
					'source_module' => $source_module,
					'source_ref' => $source_ref,
					'correlation_id' => $cid,
					'error' => $e->getMessage(),
				), $actor);
			}
		} finally {
			if ($prev !== null) { $this->db->db_debug = $prev; }
		}
	}

	public function bootstrap_missing_lab_dispositions($limit = 500, $actor_user_id = null)
	{
		$this->ensure_billing_disposition_schema();
		$limit = (int)$limit;
		if ($limit <= 0) $limit = 500;
		$actor = $actor_user_id !== null ? trim((string)$actor_user_id) : '';
		if ($actor === '') $actor = 'SYSTEM';
		$out = array('ok' => true, 'scanned' => 0, 'bootstrapped' => 0, 'errors' => array());
		if (!$this->table_exists('billing_transactions') || !$this->table_exists('billing_dispositions')) return $out;
		try {
			$sql = "SELECT bt.txn_id FROM billing_transactions bt\n\t\t\t\tLEFT JOIN billing_dispositions bd ON bd.txn_id = bt.txn_id AND bd.InActive = 0\n\t\t\t\tWHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bd.disp_id IS NULL\n\t\t\t\tORDER BY bt.txn_id ASC\n\t\t\t\tLIMIT " . (int)$limit;
			$rows = $this->db->query($sql)->result_array();
			$out['scanned'] = is_array($rows) ? count($rows) : 0;
			if (!is_array($rows) || empty($rows)) return $out;
			$chk = $this->validate_transition('BOOTSTRAP', 'NORMAL_BILLABLE');
			if (!is_array($chk) || empty($chk['ok'])) return $out;
			foreach ($rows as $r) {
				$txn_id = isset($r['txn_id']) ? (int)$r['txn_id'] : 0;
				if ($txn_id <= 0) continue;
				try {
					$cid = 'bootstrap:' . $txn_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
					$now = date('Y-m-d H:i:s');
					$ins = "INSERT INTO billing_dispositions (txn_id, from_state, to_state, reason, actor_user_id, source_module, source_ref, correlation_id, created_at, InActive)\n\t\t\t\t\tSELECT ?, 'BOOTSTRAP', 'NORMAL_BILLABLE', 'AUTO_INITIALIZATION', ?, 'SYSTEM_BOOTSTRAP', 'AUTO_INIT', ?, ?, 0\n\t\t\t\t\tFROM DUAL\n\t\t\t\t\tWHERE NOT EXISTS (SELECT 1 FROM billing_dispositions WHERE txn_id = ? AND InActive = 0 LIMIT 1)";
					$this->db->query($ins, array($txn_id, $actor, $cid, $now, $txn_id));
					if ($this->db->affected_rows() > 0) {
						$out['bootstrapped']++;
					}
				} catch (\Throwable $e) {
					$out['errors'][] = 'txn_id:' . $txn_id . ':' . $e->getMessage();
				}
			}
		} catch (Exception $e) {
			$out['ok'] = false;
			$out['errors'][] = $e->getMessage();
		}
		return $out;
	}

	public function log_shadow_issue($issue_type, $record_ref, array $details, $actor_user_id = null)
	{
		if (!$this->is_shadow_enabled()) return;
		$this->log_reconciliation((string)$issue_type, (string)$record_ref, null, null, $details, $actor_user_id);
	}

	public function bootstrap_if_missing($txn_id, $actor_user_id = null, $source_module = 'SYSTEM_BOOTSTRAP', $source_ref = 'AUTO_INIT', $correlation_id = null)
	{
		$this->ensure_billing_disposition_schema();
		$txn_id = (int)$txn_id;
		if ($txn_id <= 0) {
			return array('ok' => false, 'error' => 'invalid_txn_id');
		}
		if (!$this->table_exists('billing_dispositions')) {
			return array('ok' => false, 'error' => 'missing_table');
		}
		$actor = trim((string)$actor_user_id);
		if ($actor === '') { $actor = 'SYSTEM'; }
		$source_module = trim((string)$source_module);
		if ($source_module === '') { $source_module = 'SYSTEM_BOOTSTRAP'; }
		$source_ref = trim((string)$source_ref);
		if ($source_ref === '') { $source_ref = 'AUTO_INIT'; }
		$correlation_id = $correlation_id !== null ? trim((string)$correlation_id) : '';
		if ($correlation_id === '') {
			$correlation_id = 'bootstrap:' . $txn_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
		}

		$chk = $this->validate_transition('BOOTSTRAP', 'NORMAL_BILLABLE');
		if (!is_array($chk) || empty($chk['ok'])) {
			return array('ok' => false, 'error' => 'transition_not_allowed');
		}

		$now = date('Y-m-d H:i:s');
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$sql = "INSERT INTO billing_dispositions (txn_id, from_state, to_state, reason, actor_user_id, source_module, source_ref, correlation_id, created_at, InActive)\n"
				. "SELECT ?, 'BOOTSTRAP', 'NORMAL_BILLABLE', 'AUTO_INITIALIZATION', ?, ?, ?, ?, ?, 0\n"
				. "FROM DUAL\n"
				. "WHERE NOT EXISTS (SELECT 1 FROM billing_dispositions WHERE txn_id = ? AND InActive = 0 LIMIT 1)";
			$this->db->query($sql, array(
				$txn_id,
				$actor,
				$source_module,
				$source_ref,
				$correlation_id,
				$now,
				$txn_id,
			));
			return array('ok' => true, 'inserted' => ((int)$this->db->affected_rows() > 0));
		} catch (Exception $e) {
			return array('ok' => false, 'error' => 'bootstrap_insert_failed');
		} finally {
			if ($prev !== null) { $this->db->db_debug = $prev; }
		}
	}

	public function validate_transition(string $fromState, string $toState)
	{
		$from = strtoupper(trim((string)$fromState));
		$to = strtoupper(trim((string)$toState));
		if ($from === '' || $to === '') {
			return array('ok' => false, 'error' => 'missing_state');
		}
		if ($from === $to) {
			return array('ok' => false, 'error' => 'no_op_transition');
		}

		$terminal = array('APPROVED_WAIVER','WRITE_OFF','EXTERNAL_NON_BILLABLE');
		if (in_array($from, $terminal, true)) {
			return array('ok' => false, 'error' => 'terminal_state_locked');
		}

		$allowed = array(
			'BOOTSTRAP' => array('NORMAL_BILLABLE'),
			'NORMAL_BILLABLE' => array('DEFERRED_RECEIVABLE','EMERGENCY_PENDING_BILLING','UNABLE_TO_PAY_REVIEW','EXTERNAL_REFERRAL','EXTERNAL_NON_BILLABLE'),
			'UNABLE_TO_PAY_REVIEW' => array('APPROVED_WAIVER','WRITE_OFF'),
			'DEFERRED_RECEIVABLE' => array(),
			'EMERGENCY_PENDING_BILLING' => array(),
			'EXTERNAL_REFERRAL' => array('EXTERNAL_NON_BILLABLE'),
		);

		if (!isset($allowed[$from])) {
			return array('ok' => false, 'error' => 'unknown_from_state');
		}
		if (!in_array($to, $allowed[$from], true)) {
			return array('ok' => false, 'error' => 'transition_not_allowed');
		}
		return array('ok' => true);
	}

	public function resolve_current_state(int $txnId)
	{
		$txnId = (int)$txnId;
		if ($txnId <= 0) return null;
		if (!$this->table_exists('billing_dispositions')) return null;
		$this->db->where(array('txn_id' => $txnId, 'InActive' => 0));
		$this->db->order_by('created_at', 'DESC');
		$this->db->order_by('disp_id', 'DESC');
		$this->db->limit(1);
		$row = $this->db->get('billing_dispositions')->row_array();
		return $row ? $row : null;
	}

	private function log_reconciliation($issue_type, $record_ref, $patient_no, $encounter_id, array $details, $user_id = null)
	{
		if (!$this->table_exists('billing_reconciliation_log')) return;
		if (!isset($details['schema_version'])) { $details['schema_version'] = 1; }
		$details = $this->_sanitize_details($details);
		$row = array();
		if ($this->column_exists('billing_reconciliation_log', 'recon_date')) $row['recon_date'] = date('Y-m-d');
		if ($this->column_exists('billing_reconciliation_log', 'department')) $row['department'] = 'LABORATORY';
		if ($this->column_exists('billing_reconciliation_log', 'issue_type')) $row['issue_type'] = (string)$issue_type;
		if ($this->column_exists('billing_reconciliation_log', 'record_ref')) $row['record_ref'] = (string)$record_ref;
		if ($this->column_exists('billing_reconciliation_log', 'patient_no')) $row['patient_no'] = $patient_no !== null ? (string)$patient_no : null;
		if ($this->column_exists('billing_reconciliation_log', 'encounter_id')) $row['encounter_id'] = $encounter_id !== null ? (string)$encounter_id : null;
		if ($this->column_exists('billing_reconciliation_log', 'details')) $row['details'] = json_encode($details);
		if ($this->column_exists('billing_reconciliation_log', 'resolved')) $row['resolved'] = 0;
		if ($this->column_exists('billing_reconciliation_log', 'created_at')) $row['created_at'] = date('Y-m-d H:i:s');
		if (empty($row)) return;
		if ($this->db->field_exists('performed_by', 'billing_reconciliation_log')) {
			$row['performed_by'] = $user_id !== null ? (string)$user_id : null;
		}
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) $this->db->db_debug = false;
		try {
			$this->db->insert('billing_reconciliation_log', $row);
		} catch (Exception $e) {
		}
		if ($prev !== null) $this->db->db_debug = $prev;
	}

	private function table_exists($table_name)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$table_name));
		return ($q && $q->num_rows() > 0);
	}

	private function column_exists($table_name, $column_name)
	{
		if (!$this->table_exists($table_name)) return false;
		return $this->db->field_exists((string)$column_name, (string)$table_name);
	}

	private function _sanitize_details($value, $depth = 0)
	{
		if ($depth > 3) return null;
		if (is_array($value)) {
			$out = array();
			$deny = array('notes','note','instruction','instructions','clinical_notes','clinical_note','remarks','remark');
			$limit = 0;
			foreach ($value as $k => $v) {
				$limit++;
				if ($limit > 60) break;
				$key = is_string($k) ? strtolower(trim($k)) : $k;
				if (is_string($key) && in_array($key, $deny, true)) continue;
				$out[$k] = $this->_sanitize_details($v, $depth + 1);
			}
			return $out;
		}
		if (is_string($value)) {
			$v = trim($value);
			if (strlen($v) > 256) { $v = substr($v, 0, 256); }
			return $v;
		}
		if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
			return $value;
		}
		return null;
	}
}
