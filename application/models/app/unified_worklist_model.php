<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Unified_worklist_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	private function gate_sql_exprs($authStatusExpr, $authCodeExpr, $ssotMissingBypassExpr = '0', $overrideBypassExpr = '0')
	{
		$authStatusExpr = (string)$authStatusExpr;
		$authCodeExpr = (string)$authCodeExpr;
		$ssotMissingBypassExpr = (string)$ssotMissingBypassExpr;
		$overrideBypassExpr = (string)$overrideBypassExpr;
		$enforceAuth = $this->get_runtime_bool('enforce_insurance_auth', false);
		$enforceAuthSql = $enforceAuth ? '1' : '0';
		$authStatusExpr = trim((string)$authStatusExpr) !== '' ? $authStatusExpr : "''";
		$authCodeExpr = trim((string)$authCodeExpr) !== '' ? $authCodeExpr : "''";
		$ssotMissingBypassExpr = trim((string)$ssotMissingBypassExpr) !== '' ? $ssotMissingBypassExpr : '0';

		$blockedReason = "(CASE
			WHEN ({$overrideBypassExpr}) THEN 'ALLOWED'
			WHEN BT.txn_id IS NULL THEN (CASE WHEN ({$ssotMissingBypassExpr}) THEN 'ALLOWED' ELSE 'NO_SSOT' END)
			WHEN COALESCE(BT.net_amount,0) <= 0 THEN 'ZERO_PRICE'
			WHEN UPPER(COALESCE(BT.payer_type,'')) IN ('NHIS','INSURANCE') AND {$enforceAuthSql} = 1 AND UPPER(COALESCE({$authStatusExpr},'')) = 'PENDING' THEN 'AUTH_PENDING'
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

	public function get_item_by_ref($item_ref)
	{
		$item_ref = trim((string)$item_ref);
		if ($item_ref === '') {
			return null;
		}

		if (strpos($item_ref, 'io_lab_id:') === 0) {
			$id = (int)substr($item_ref, strlen('io_lab_id:'));
			$items = $this->get_lab_items('ANY', 1, $id);
			return !empty($items) ? $items[0] : null;
		}
		if (strpos($item_ref, 'radiology_order_id:') === 0) {
			$id = (int)substr($item_ref, strlen('radiology_order_id:'));
			$items = $this->get_radiology_items('ANY', 1, $id);
			return !empty($items) ? $items[0] : null;
		}
		if (strpos($item_ref, 'sono_charge_id:') === 0) {
			$id = (int)substr($item_ref, strlen('sono_charge_id:'));
			$items = $this->get_sonography_items('ANY', 1, $id);
			return !empty($items) ? $items[0] : null;
		}

		return null;
	}

	public function get_item_by_ref_raw($item_ref)
	{
		$item_ref = trim((string)$item_ref);
		if ($item_ref === '') { return null; }

		if (strpos($item_ref, 'io_lab_id:') === 0) {
			$id = (int)substr($item_ref, strlen('io_lab_id:'));
			$rows = $this->get_lab_items('ALL', 1, $id, true);
			return (count($rows) > 0) ? $rows[0] : null;
		}
		if (strpos($item_ref, 'radiology_order_id:') === 0) {
			$id = (int)substr($item_ref, strlen('radiology_order_id:'));
			$rows = $this->get_radiology_items('ALL', 1, $id, true);
			return (count($rows) > 0) ? $rows[0] : null;
		}
		if (strpos($item_ref, 'sono_charge_id:') === 0) {
			$id = (int)substr($item_ref, strlen('sono_charge_id:'));
			$rows = $this->get_sonography_items('ALL', 1, $id, true);
			return (count($rows) > 0) ? $rows[0] : null;
		}

		return $this->get_item_by_ref($item_ref);
	}

	public function get_worklist($filters = array())
	{
		$modules = isset($filters['modules']) && is_array($filters['modules']) ? $filters['modules'] : array('LAB', 'RADIOLOGY', 'SONOGRAPHY');
		$status = isset($filters['status']) ? strtoupper(trim((string)$filters['status'])) : 'PENDING';
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
		if ($limit <= 0) { $limit = 200; }
		if ($limit > 1000) { $limit = 1000; }

		$rows = array();
		if (in_array('LAB', $modules, true)) {
			$rows = array_merge($rows, $this->get_lab_items($status, $limit));
		}
		if (in_array('RADIOLOGY', $modules, true)) {
			$rows = array_merge($rows, $this->get_radiology_items($status, $limit));
		}
		if (in_array('SONOGRAPHY', $modules, true)) {
			$rows = array_merge($rows, $this->get_sonography_items($status, $limit));
		}

		usort($rows, function ($a, $b) {
			$ta = isset($a['created_at']) ? strtotime((string)$a['created_at']) : 0;
			$tb = isset($b['created_at']) ? strtotime((string)$b['created_at']) : 0;
			if ($ta === $tb) { return 0; }
			return ($ta < $tb) ? 1 : -1;
		});

		if (count($rows) > $limit) {
			$rows = array_slice($rows, 0, $limit);
		}

		return $rows;
	}

	private function get_lab_items($status, $limit, $only_id = null, $raw = false)
	{
		$status = strtoupper(trim((string)$status));
		if (!$this->db->table_exists('iop_laboratory')) {
			return array();
		}

		$emergencyExpr = '0';
		if ($this->db->field_exists('emergency_flag', 'iop_laboratory')) {
			$emergencyExpr = 'COALESCE(L.emergency_flag,0)';
		}
		$exExpr = '0';
		if ($this->db->table_exists('service_gate_exceptions')) {
			$exExpr = "EXISTS(SELECT 1 FROM service_gate_exceptions EX WHERE EX.InActive = 0 AND EX.status = 'ACTIVE' AND EX.module IN ('LAB','LABORATORY') AND EX.source_ref = L.io_lab_id AND EX.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))";
		}
		$imgBypassExpr = '0';
		if ($this->db->table_exists('imaging_billing_bypass') && $this->db->field_exists('io_lab_id', 'imaging_billing_bypass') && $this->db->field_exists('is_active', 'imaging_billing_bypass')) {
			$imgBypassExpr = "EXISTS(SELECT 1 FROM imaging_billing_bypass IB WHERE IB.InActive = 0 AND IB.is_active = 1 AND IB.io_lab_id = L.io_lab_id)";
		}
		$overrideBypassExpr = "({$exExpr} OR {$emergencyExpr} = 1 OR {$imgBypassExpr})";
		$ssotMissingBypassExpr = $overrideBypassExpr;
		if ($raw) {
			$overrideBypassExpr = '0';
			$ssotMissingBypassExpr = '0';
		}

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

		$wfJoin = '';
		$wfCol = "'PENDING'";
		$wfWhere = '';
		$createdExpr = 'L.dDate';
		if ($this->db->table_exists('iop_laboratory_workflow')) {
			$wfJoin = " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 ";
			$wfCol = "COALESCE(NULLIF(UPPER(TRIM(W.status)),''),'PENDING')";
			if ($this->db->field_exists('requested_at', 'iop_laboratory_workflow')) {
				$createdExpr = "COALESCE(NULLIF(W.requested_at,'0000-00-00 00:00:00'), L.dDate)";
			}
		}

		if ($status === 'PENDING') {
			$wfWhere = " AND (L.result = '' OR L.result IS NULL) ";
		} elseif ($status === 'COMPLETED') {
			$wfWhere = " AND (L.result <> '' AND L.result IS NOT NULL) ";
		} else {
			$wfWhere = " ";
		}
		$only = '';
		$bind = array((int)$limit);
		if ($only_id !== null) {
			$only = ' AND L.io_lab_id = ? ';
			$bind = array((int)$only_id, (int)$limit);
		}
		$gate = $this->gate_sql_exprs($authStatusExpr, $authCodeExpr, $ssotMissingBypassExpr, $overrideBypassExpr);

		$sql = "SELECT
			L.io_lab_id AS item_id,
			CONCAT('io_lab_id:', L.io_lab_id) AS item_ref,
			L.iop_id,
			PD.patient_no,
			CONCAT(PPI.firstname,' ',PPI.lastname) AS patient_name,
			COALESCE(BP.particular_name, L.laboratory_text, 'Unknown') AS service_name,
			COALESCE(L.category_id, 0) AS category_id,
			{$wfCol} AS workflow_status,
			{$createdExpr} AS created_at,
			BT.payer_type,
			BT.payment_status AS ssot_payment_status,
			BT.net_amount,
			BT.paid_amount,
			BT.balance_amount,
			{$authStatusExpr} AS ssot_auth_status,
			{$authCodeExpr} AS ssot_auth_code,
			{$gate['blocked_reason']} AS blocked_reason,
			{$gate['can_proceed']} AS can_proceed
		FROM iop_laboratory L
		LEFT JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0
		LEFT JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no
		LEFT JOIN bill_particular BP ON BP.particular_id = L.laboratory_id
		{$wfJoin}
		LEFT JOIN billing_transactions BT
			ON BT.InActive = 0 AND BT.department = 'LABORATORY' AND BT.item_ref = CONCAT('io_lab_id:', L.io_lab_id)
		WHERE L.InActive = 0 AND (L.category_id IS NULL OR L.category_id <> 18)
		{$wfWhere}
		{$only}
		ORDER BY {$createdExpr} DESC
		LIMIT ?";

		$q = $this->db->query($sql, $bind);
		$rows = $q ? $q->result_array() : array();

		$out = array();
		foreach ($rows as $r) {
			$out[] = $this->normalize_row('LAB', $r);
		}
		return $out;
	}

	private function get_radiology_items($status, $limit, $only_id = null, $raw = false)
	{
		$status = strtoupper(trim((string)$status));
		if (!$this->db->table_exists('radiology_orders')) {
			return array();
		}

		$exExpr = '0';
		if ($this->db->table_exists('service_gate_exceptions')) {
			$exExpr = "EXISTS(SELECT 1 FROM service_gate_exceptions EX WHERE EX.InActive = 0 AND EX.status = 'ACTIVE' AND EX.module IN ('RADIOLOGY','RAD') AND EX.source_ref = o.id AND EX.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))";
		}

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

		$where = "WHERE o.InActive = 0";
		if ($status === 'PENDING') {
			$where .= " AND o.status IN ('pending','in_progress')";
		} elseif ($status === 'COMPLETED') {
			$where .= " AND o.status = 'completed'";
		}
		$bind = array((int)$limit);
		if ($only_id !== null) {
			$where .= ' AND o.id = ?';
			$bind = array((int)$only_id, (int)$limit);
		}
		$ssotMissingBypassExpr = $raw ? '0' : $exExpr;
		$overrideBypassExpr = $raw ? '0' : $exExpr;
		$gate = $this->gate_sql_exprs($authStatusExpr, $authCodeExpr, $ssotMissingBypassExpr, $overrideBypassExpr);

		$sql = "SELECT
			o.id AS item_id,
			CONCAT('radiology_order_id:', o.id) AS item_ref,
			o.iop_id,
			o.patient_no,
			CONCAT(COALESCE(p.firstname,''),' ',COALESCE(p.lastname,'')) AS patient_name,
			COALESCE(t.test_name,'Radiology') AS service_name,
			0 AS category_id,
			UPPER(o.status) AS workflow_status,
			o.ordered_at AS created_at,
			BT.payer_type,
			BT.payment_status AS ssot_payment_status,
			BT.net_amount,
			BT.paid_amount,
			BT.balance_amount,
			{$authStatusExpr} AS ssot_auth_status,
			{$authCodeExpr} AS ssot_auth_code,
			{$gate['blocked_reason']} AS blocked_reason,
			{$gate['can_proceed']} AS can_proceed
		FROM radiology_orders o
		LEFT JOIN radiology_test_master t ON t.id = o.test_id
		LEFT JOIN patient_personal_info p ON p.patient_no = o.patient_no
		LEFT JOIN billing_transactions BT
			ON BT.InActive = 0 AND BT.department = 'IMAGING' AND BT.item_ref = CONCAT('radiology_order_id:', o.id)
		{$where}
		ORDER BY o.ordered_at DESC
		LIMIT ?";

		$q = $this->db->query($sql, $bind);
		$rows = $q ? $q->result_array() : array();

		$out = array();
		foreach ($rows as $r) {
			$out[] = $this->normalize_row('RADIOLOGY', $r);
		}
		return $out;
	}

	private function get_sonography_items($status, $limit, $only_id = null, $raw = false)
	{
		$status = strtoupper(trim((string)$status));
		if (!$this->db->table_exists('iop_sonography_charge')) {
			return array();
		}

		$emergencyExpr = '0';
		if ($this->db->table_exists('iop_laboratory') && $this->db->field_exists('emergency_flag', 'iop_laboratory')) {
			$emergencyExpr = 'COALESCE(L.emergency_flag,0)';
		}
		$exExpr = '0';
		if ($this->db->table_exists('service_gate_exceptions')) {
			$exExpr = "EXISTS(SELECT 1 FROM service_gate_exceptions EX WHERE EX.InActive = 0 AND EX.status = 'ACTIVE' AND EX.module IN ('SONOGRAPHY') AND EX.source_ref = C.charge_id AND EX.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR))";
		}
		$imgBypassExpr = '0';
		if ($this->db->table_exists('imaging_billing_bypass') && $this->db->field_exists('io_lab_id', 'imaging_billing_bypass') && $this->db->field_exists('is_active', 'imaging_billing_bypass')) {
			$imgBypassExpr = "EXISTS(SELECT 1 FROM imaging_billing_bypass IB WHERE IB.InActive = 0 AND IB.is_active = 1 AND IB.io_lab_id = C.io_lab_id)";
		}
		$ssotMissingBypassExpr = "({$exExpr} OR {$emergencyExpr} = 1 OR {$imgBypassExpr})";
		$overrideBypassExpr = $ssotMissingBypassExpr;
		if ($raw) {
			$overrideBypassExpr = '0';
			$ssotMissingBypassExpr = '0';
		}

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

		$wfJoin = '';
		$wfCol = "'PENDING'";
		$createdExpr = 'C.created_at';
		if ($this->db->table_exists('iop_laboratory_workflow')) {
			$wfJoin = " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = C.io_lab_id AND W.InActive = 0 ";
			$wfCol = "COALESCE(NULLIF(UPPER(TRIM(W.status)),''),'PENDING')";
			if ($this->db->field_exists('requested_at', 'iop_laboratory_workflow')) {
				$createdExpr = "COALESCE(NULLIF(W.requested_at,'0000-00-00 00:00:00'), C.created_at)";
			}
		}

		$where = "WHERE C.InActive = 0";
		if ($status === 'PENDING') {
			$where .= " AND (L.result = '' OR L.result IS NULL)";
		} elseif ($status === 'COMPLETED') {
			$where .= " AND (L.result <> '' AND L.result IS NOT NULL)";
		}
		$bind = array((int)$limit);
		if ($only_id !== null) {
			$where .= ' AND C.charge_id = ?';
			$bind = array((int)$only_id, (int)$limit);
		}
		$gate = $this->gate_sql_exprs($authStatusExpr, $authCodeExpr, $ssotMissingBypassExpr, $overrideBypassExpr);

		$sql = "SELECT
			C.charge_id AS item_id,
			CONCAT('sono_charge_id:', C.charge_id) AS item_ref,
			C.iop_id,
			C.patient_no,
			CONCAT(COALESCE(PPI.firstname,''),' ',COALESCE(PPI.lastname,'')) AS patient_name,
			COALESCE(C.item_name, BP.particular_name, L.laboratory_text, 'Sonography') AS service_name,
			18 AS category_id,
			{$wfCol} AS workflow_status,
			{$createdExpr} AS created_at,
			C.io_lab_id AS source_id,
			BT.payer_type,
			BT.payment_status AS ssot_payment_status,
			BT.net_amount,
			BT.paid_amount,
			BT.balance_amount,
			{$authStatusExpr} AS ssot_auth_status,
			{$authCodeExpr} AS ssot_auth_code,
			{$gate['blocked_reason']} AS blocked_reason,
			{$gate['can_proceed']} AS can_proceed
		FROM iop_sonography_charge C
		LEFT JOIN iop_laboratory L ON L.io_lab_id = C.io_lab_id AND L.InActive = 0
		LEFT JOIN patient_details_iop PD ON PD.IO_ID = C.iop_id AND PD.InActive = 0
		LEFT JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no
		LEFT JOIN bill_particular BP ON BP.particular_id = L.laboratory_id
		{$wfJoin}
		LEFT JOIN billing_transactions BT
			ON BT.InActive = 0 AND BT.department = 'IMAGING' AND BT.item_ref = CONCAT('sono_charge_id:', C.charge_id)
		{$where}
		ORDER BY {$createdExpr} DESC
		LIMIT ?";

		$q = $this->db->query($sql, $bind);
		$rows = $q ? $q->result_array() : array();

		$out = array();
		foreach ($rows as $r) {
			$out[] = $this->normalize_row('SONOGRAPHY', $r);
		}
		return $out;
	}

	private function normalize_row($module, $row)
	{
		$module = strtoupper(trim((string)$module));
		$item_ref = isset($row['item_ref']) ? (string)$row['item_ref'] : '';
		$payer_type = isset($row['payer_type']) ? strtoupper(trim((string)$row['payer_type'])) : '';
		$pss = isset($row['ssot_payment_status']) ? strtoupper(trim((string)$row['ssot_payment_status'])) : '';
		$net = isset($row['net_amount']) ? (float)$row['net_amount'] : null;
		$paid = isset($row['paid_amount']) ? (float)$row['paid_amount'] : null;
		$bal = isset($row['balance_amount']) ? (float)$row['balance_amount'] : null;
		$authStatus = isset($row['ssot_auth_status']) ? strtoupper(trim((string)$row['ssot_auth_status'])) : '';
		$authCode = isset($row['ssot_auth_code']) ? trim((string)$row['ssot_auth_code']) : '';

		$payment_status = $this->resolve_payment_status($payer_type, $pss, $net, $paid, $bal, $item_ref);
		$gate = $this->resolve_can_proceed($module, $payer_type, $pss, $net, $paid, $bal, $item_ref, $authStatus, $authCode);
		$canProceedSql = array_key_exists('can_proceed', $row) ? (int)$row['can_proceed'] : null;
		$blockedReasonSql = array_key_exists('blocked_reason', $row) ? (string)$row['blocked_reason'] : null;
		$canProceed = ($canProceedSql !== null) ? ((int)$canProceedSql === 1) : (bool)$gate['allowed'];
		$blockedReason = ($canProceedSql !== null) ? $blockedReasonSql : $gate['reason'];

		$out = array(
			'module' => $module,
			'item_id' => isset($row['item_id']) ? (string)$row['item_id'] : null,
			'item_ref' => $item_ref,
			'iop_id' => isset($row['iop_id']) ? (string)$row['iop_id'] : null,
			'patient_no' => isset($row['patient_no']) ? (string)$row['patient_no'] : null,
			'patient_name' => isset($row['patient_name']) ? trim((string)$row['patient_name']) : null,
			'service_name' => isset($row['service_name']) ? (string)$row['service_name'] : null,
			'category_id' => isset($row['category_id']) ? (int)$row['category_id'] : 0,
			'workflow_status' => isset($row['workflow_status']) ? (string)$row['workflow_status'] : 'PENDING',
			'payment_status' => $payment_status,
			'payer_type' => $payer_type !== '' ? $payer_type : null,
			'net_amount' => $net,
			'paid_amount' => $paid,
			'balance_amount' => $bal,
			'can_proceed' => $canProceed,
			'blocked_reason' => $canProceed ? null : $blockedReason,
			'created_at' => isset($row['created_at']) ? (string)$row['created_at'] : null,
		);

		if (isset($row['source_id'])) {
			$out['source_id'] = $row['source_id'];
		}

		return $out;
	}

	private function resolve_payment_status($payer_type, $ssot_payment_status, $net, $paid, $bal, $item_ref)
	{
		if ($item_ref === '' || $ssot_payment_status === '') {
			return 'UNBILLED';
		}
		if ($net !== null && $net <= 0.0) {
			return 'ERROR';
		}
		if ($ssot_payment_status === 'PARTIAL') {
			return 'PARTIAL';
		}
		if ($bal !== null && $bal > 0.0) {
			return 'PENDING';
		}
		if ($payer_type === 'NHIS' || $ssot_payment_status === 'NHIS') {
			return 'PAID';
		}
		if (in_array($ssot_payment_status, array('PAID', 'WAIVED'), true)) {
			return 'PAID';
		}
		return 'PENDING';
	}

	private function resolve_can_proceed($module, $payer_type, $ssot_payment_status, $net, $paid, $bal, $item_ref, $authStatus = '', $authCode = '')
	{
		if ($item_ref === '' || $ssot_payment_status === '') {
			return array('allowed' => false, 'reason' => 'NO_SSOT');
		}

		$module = strtoupper(trim((string)$module));
		$forceFull = in_array($module, array('LAB', 'LABORATORY', 'SONOGRAPHY', 'RADIOLOGY', 'RAD'), true);

		if ($net !== null && $net <= 0.0) {
			return array('allowed' => false, 'reason' => 'ZERO_PRICE');
		}

		if ($payer_type === 'NHIS' || $ssot_payment_status === 'NHIS') {
			return array('allowed' => true, 'reason' => null);
		}

		if ($ssot_payment_status === 'PARTIAL' && $forceFull) {
			return array('allowed' => false, 'reason' => 'PARTIAL_NOT_ALLOWED');
		}

		if ($payer_type !== '' && $payer_type !== 'CASH') {
			if ($payer_type === 'INSURANCE' || $payer_type === 'COMPANY') {
				$enforceAuth = $this->get_runtime_bool('enforce_insurance_auth', false);
				if ($enforceAuth) {
					$authOk = false;
					if ($authCode !== '') { $authOk = true; }
					if (!$authOk && $authStatus !== '' && $authStatus !== 'PENDING' && $authStatus !== 'REJECTED') {
						$authOk = true;
					}
					if (!$authOk) {
						return array('allowed' => false, 'reason' => 'INSURANCE_AUTH_REQUIRED');
					}
				}
			}
			return array('allowed' => true, 'reason' => null);
		}

		if (in_array($ssot_payment_status, array('PAID', 'WAIVED'), true)) {
			return array('allowed' => true, 'reason' => null);
		}

		if ($bal !== null && $bal <= 0.0 && $net !== null && $net > 0.0 && $paid !== null && $paid >= $net) {
			return array('allowed' => true, 'reason' => null);
		}

		return array('allowed' => false, 'reason' => 'PAYMENT_PENDING');
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
}
