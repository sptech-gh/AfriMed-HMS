<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Procedure_unit_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	public function ensure_schema()
	{
		if (!$this->db->table_exists('iop_procedure_request')) {
			return false;
		}

		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			if (!$this->db->field_exists('performed_at', 'iop_procedure_request')) {
				$this->db->query("ALTER TABLE `iop_procedure_request` ADD COLUMN `performed_at` DATETIME DEFAULT NULL AFTER `requested_at`");
			}
			if (!$this->db->field_exists('performed_by', 'iop_procedure_request')) {
				$this->db->query("ALTER TABLE `iop_procedure_request` ADD COLUMN `performed_by` VARCHAR(25) DEFAULT NULL AFTER `performed_at`");
			}
			if (!$this->db->field_exists('cancelled_at', 'iop_procedure_request')) {
				$this->db->query("ALTER TABLE `iop_procedure_request` ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL AFTER `performed_by`");
			}
			if (!$this->db->field_exists('cancelled_by', 'iop_procedure_request')) {
				$this->db->query("ALTER TABLE `iop_procedure_request` ADD COLUMN `cancelled_by` VARCHAR(25) DEFAULT NULL AFTER `cancelled_at`");
			}
			if (!$this->db->field_exists('cancel_reason', 'iop_procedure_request')) {
				$this->db->query("ALTER TABLE `iop_procedure_request` ADD COLUMN `cancel_reason` TEXT DEFAULT NULL AFTER `cancelled_by`");
			}
		} catch (\Throwable $e) {
			log_message('error', 'Procedure_unit_model ensure_schema: ' . $e->getMessage());
		}
		if ($prev !== null) { $this->db->db_debug = $prev; }

		try {
			if ($this->db->table_exists('department')) {
				$exists = $this->db->query("SELECT department_id FROM department WHERE InActive = 0 AND (dept_code = 'PROC' OR dept_name = 'Procedure Unit') LIMIT 1")->row();
				if (!$exists) {
					$this->db->insert('department', array(
						'dept_code' => 'PROC',
						'dept_name' => 'Procedure Unit',
						'InActive' => 0,
					));
				}
			}
		} catch (\Throwable $e) {
		}

		return true;
	}

	private function get_runtime_bool($key, $default)
	{
		$cfg = null;
		$env = getenv((string)$key);
		if ($env === false) { $env = getenv(strtoupper((string)$key)); }
		if ($env !== false) { $cfg = $env; }

		if ($cfg === null && isset($this->db) && $this->db->table_exists('system_option') && $this->db->field_exists('cCode', 'system_option') && $this->db->field_exists('cValue', 'system_option')) {
			$row = $this->db->get_where('system_option', array('cCode' => (string)$key, 'InActive' => 0), 1)->row();
			if (!$row) {
				$row = $this->db->get_where('system_option', array('cCode' => strtoupper((string)$key), 'InActive' => 0), 1)->row();
			}
			if ($row && isset($row->cValue)) {
				$cfg = $row->cValue;
			}
		}

		if ($cfg === null && isset($this->config)) {
			$tmp = $this->config->item((string)$key);
			if ($tmp === null) { $tmp = $this->config->item(strtoupper((string)$key)); }
			if ($tmp !== null) { $cfg = $tmp; }
		}

		if ($cfg === null) { $cfg = $default; }
		if (is_bool($cfg)) { return $cfg; }
		if ($cfg === null) { return (bool)$default; }
		if (is_numeric($cfg)) { return ((int)$cfg) !== 0; }
		$val = filter_var($cfg, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		return ($val === null) ? (bool)$default : (bool)$val;
	}

	private function gate_sql_exprs($authStatusExpr, $authCodeExpr)
	{
		$authStatusExpr = trim((string)$authStatusExpr) !== '' ? (string)$authStatusExpr : "''";
		$authCodeExpr = trim((string)$authCodeExpr) !== '' ? (string)$authCodeExpr : "''";

		$enforceAuth = $this->get_runtime_bool('enforce_insurance_auth', false);
		$enforceAuthSql = $enforceAuth ? '1' : '0';

		$blockedReason = "(CASE
			WHEN BT.txn_id IS NULL THEN 'NO_SSOT'
			WHEN COALESCE(BT.net_amount,0) <= 0 THEN 'ZERO_PRICE'
			WHEN UPPER(COALESCE(BT.payer_type,'')) = 'NHIS' OR UPPER(COALESCE(BT.payment_status,'')) = 'NHIS' THEN 'ALLOWED'
			WHEN UPPER(COALESCE(BT.payer_type,'')) IN ('INSURANCE','COMPANY') AND {$enforceAuthSql} = 1 AND (
				TRIM(COALESCE({$authCodeExpr},'')) = '' AND UPPER(COALESCE({$authStatusExpr},'')) = 'PENDING'
			) THEN 'AUTH_REQUIRED'
			WHEN UPPER(COALESCE(BT.payer_type,'')) IN ('INSURANCE','COMPANY') THEN 'ALLOWED'
			WHEN UPPER(COALESCE(BT.payment_status,'')) = 'PAID' THEN 'ALLOWED'
			WHEN UPPER(COALESCE(BT.payment_status,'')) = 'PARTIAL' AND COALESCE(BT.balance_amount,0) <= 0 THEN 'ALLOWED'
			ELSE 'PAYMENT_PENDING'
		END)";

		$canProceed = "CASE WHEN ({$blockedReason}) = 'ALLOWED' THEN 1 ELSE 0 END";

		return array(
			'blocked_reason' => $blockedReason,
			'can_proceed' => $canProceed,
		);
	}

	public function get_worklist($filters = array())
	{
		$this->ensure_schema();
		if (!$this->db->table_exists('iop_procedure_request')) {
			return array();
		}

		$status = isset($filters['status']) ? strtoupper(trim((string)$filters['status'])) : 'PENDING';
		$search = isset($filters['search']) ? trim((string)$filters['search']) : '';
		$date_from = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
		$date_to = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
		if ($limit <= 0) { $limit = 200; }
		if ($limit > 1000) { $limit = 1000; }

		$hasAuthStatus = $this->db->table_exists('billing_transactions') && ($this->db->field_exists('authorization_status', 'billing_transactions') || $this->db->field_exists('auth_status', 'billing_transactions'));
		$hasAuthCode = $this->db->table_exists('billing_transactions') && ($this->db->field_exists('authorization_code', 'billing_transactions') || $this->db->field_exists('auth_code', 'billing_transactions'));
		$authStatusExpr = "''";
		$authCodeExpr = "''";
		if ($hasAuthStatus) {
			$authStatusExpr = $this->db->field_exists('authorization_status', 'billing_transactions') ? "COALESCE(BT.authorization_status,'')" : "COALESCE(BT.auth_status,'')";
		}
		if ($hasAuthCode) {
			$authCodeExpr = $this->db->field_exists('authorization_code', 'billing_transactions') ? "COALESCE(BT.authorization_code,'')" : "COALESCE(BT.auth_code,'')";
		}
		$gate = $this->gate_sql_exprs($authStatusExpr, $authCodeExpr);

		$where = "PR.InActive = 0";
		$bind = array();

		if ($status === 'PENDING') {
			$where .= " AND UPPER(COALESCE(PR.status,'')) IN ('REQUESTED','ORDERED','PENDING')";
		} elseif ($status === 'COMPLETED') {
			$where .= " AND UPPER(COALESCE(PR.status,'')) IN ('PERFORMED','COMPLETED')";
		} elseif ($status === 'CANCELLED') {
			$where .= " AND UPPER(COALESCE(PR.status,'')) IN ('CANCELLED')";
		}

		if ($search !== '') {
			$where .= " AND (PR.patient_no LIKE ? OR PR.procedure_name LIKE ? OR COALESCE(BP.particular_name,'') LIKE ? OR CONCAT(COALESCE(PPI.firstname,''),' ',COALESCE(PPI.lastname,'')) LIKE ?)";
			$like = '%' . $search . '%';
			$bind[] = $like;
			$bind[] = $like;
			$bind[] = $like;
			$bind[] = $like;
		}
		if ($date_from !== '') {
			$where .= " AND PR.requested_at >= ?";
			$bind[] = $date_from . ' 00:00:00';
		}
		if ($date_to !== '') {
			$where .= " AND PR.requested_at <= ?";
			$bind[] = $date_to . ' 23:59:59';
		}

		$bind[] = (int)$limit;

		$sql = "SELECT
			PR.request_id,
			PR.iop_id,
			PR.patient_no,
			CONCAT(COALESCE(PPI.firstname,''),' ',COALESCE(PPI.lastname,'')) AS patient_name,
			COALESCE(BP.particular_name, PR.procedure_name, 'Procedure') AS procedure_name,
			PR.procedure_id,
			PR.qty,
			PR.notes,
			PR.status,
			PR.requested_at,
			PR.requested_by,
			PR.performed_at,
			PR.performed_by,
			PR.cancelled_at,
			PR.cancelled_by,
			PR.cancel_reason,
			BT.payer_type,
			BT.payment_status AS ssot_payment_status,
			BT.net_amount,
			BT.paid_amount,
			BT.balance_amount,
			{$authStatusExpr} AS ssot_auth_status,
			{$authCodeExpr} AS ssot_auth_code,
			{$gate['blocked_reason']} AS blocked_reason,
			{$gate['can_proceed']} AS can_proceed
		FROM iop_procedure_request PR
		LEFT JOIN patient_details_iop PD ON PD.IO_ID = PR.iop_id AND PD.InActive = 0
		LEFT JOIN patient_personal_info PPI ON PPI.patient_no = PR.patient_no
		LEFT JOIN bill_particular BP ON BP.particular_id = PR.procedure_id
		LEFT JOIN billing_transactions BT
			ON BT.InActive = 0 AND BT.department = 'OPD' AND BT.item_ref = CONCAT('opd_procedure_request_id:', PR.request_id)
		WHERE {$where}
		ORDER BY PR.requested_at DESC
		LIMIT ?";

		$q = $this->db->query($sql, $bind);
		return $q ? $q->result_array() : array();
	}

	public function get_request($request_id)
	{
		$this->ensure_schema();
		if (!$this->db->table_exists('iop_procedure_request')) {
			return null;
		}
		$this->db->where(array('request_id' => (int)$request_id, 'InActive' => 0));
		$this->db->limit(1);
		return $this->db->get('iop_procedure_request')->row_array();
	}

	public function mark_performed($request_id, $user_id)
	{
		$this->ensure_schema();
		if (!$this->db->table_exists('iop_procedure_request')) {
			return array('ok' => false, 'error' => 'Schema not ready');
		}

		$this->db->where(array('request_id' => (int)$request_id, 'InActive' => 0));
		$this->db->limit(1);
		$row = $this->db->get('iop_procedure_request')->row();
		if (!$row) {
			return array('ok' => false, 'error' => 'Request not found');
		}

		$st = isset($row->status) ? strtoupper(trim((string)$row->status)) : '';
		if (in_array($st, array('PERFORMED', 'COMPLETED'), true)) {
			return array('ok' => true);
		}
		if ($st === 'CANCELLED') {
			return array('ok' => false, 'error' => 'Request is cancelled');
		}

		$now = date('Y-m-d H:i:s');
		$update = array(
			'status' => 'PERFORMED',
			'performed_at' => $now,
			'performed_by' => (string)$user_id,
			'updated_at' => $now,
		);

		$this->db->where('request_id', (int)$request_id);
		$this->db->update('iop_procedure_request', $update);

		return array('ok' => true);
	}

	public function cancel_request($request_id, $user_id, $reason)
	{
		$this->ensure_schema();
		if (!$this->db->table_exists('iop_procedure_request')) {
			return array('ok' => false, 'error' => 'Schema not ready');
		}

		$this->db->where(array('request_id' => (int)$request_id, 'InActive' => 0));
		$this->db->limit(1);
		$row = $this->db->get('iop_procedure_request')->row();
		if (!$row) {
			return array('ok' => false, 'error' => 'Request not found');
		}

		$st = isset($row->status) ? strtoupper(trim((string)$row->status)) : '';
		if (in_array($st, array('PERFORMED', 'COMPLETED'), true)) {
			return array('ok' => false, 'error' => 'Cannot cancel a performed request');
		}

		$now = date('Y-m-d H:i:s');
		$update = array(
			'status' => 'CANCELLED',
			'cancelled_at' => $now,
			'cancelled_by' => (string)$user_id,
			'cancel_reason' => $reason,
			'updated_at' => $now,
		);

		$this->db->where('request_id', (int)$request_id);
		$this->db->update('iop_procedure_request', $update);

		return array('ok' => true);
	}
}
