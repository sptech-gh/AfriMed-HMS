<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Diagnostic_finance_state_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->model('app/Billing_canonical_model', 'billing_canonical');
	}

	public function normalize_source_ref($module, $id)
	{
		return $this->billing_canonical->item_ref_candidates($module, $id);
	}

	public function get_financial_state_detail($source_module, $source_id)
	{
		$item_refs = $this->normalize_source_ref($source_module, $source_id);
		if (empty($item_refs)) {
			return array('state' => 'REQUESTED', 'resolved_item_ref' => null, 'paid_amount' => null, 'net_amount' => null, 'payer_type' => null, 'payment_status' => null, 'order_status' => null);
		}

		if (!$this->db->table_exists('billing_transactions')) {
			return array('state' => 'REQUESTED', 'resolved_item_ref' => null, 'paid_amount' => null, 'net_amount' => null, 'payer_type' => null, 'payment_status' => null, 'order_status' => null);
		}
		if (!$this->db->field_exists('item_ref', 'billing_transactions')) {
			return array('state' => 'REQUESTED', 'resolved_item_ref' => null, 'paid_amount' => null, 'net_amount' => null, 'payer_type' => null, 'payment_status' => null, 'order_status' => null);
		}

		$select = 'item_ref, net_amount, paid_amount, payment_status, order_status';
		if ($this->db->field_exists('payer_type', 'billing_transactions')) {
			$select = 'item_ref, net_amount, paid_amount, payer_type, payment_status, order_status';
		}
		$this->db->select($select);
		$this->db->from('billing_transactions');
		$this->db->where('InActive', 0);
		$this->db->where_in('item_ref', $item_refs);
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();

		if (empty($rows)) {
			return array('state' => 'REQUESTED', 'resolved_item_ref' => null, 'paid_amount' => null, 'net_amount' => null, 'payer_type' => null, 'payment_status' => null, 'order_status' => null);
		}

		$by_ref = array();
		foreach ($rows as $r) {
			$ref = isset($r->item_ref) ? (string)$r->item_ref : '';
			if ($ref !== '' && !isset($by_ref[$ref])) {
				$by_ref[$ref] = $r;
			}
		}

		$txn = null;
		$resolved_ref = null;
		foreach ($item_refs as $ref) {
			if (isset($by_ref[$ref])) {
				$txn = $by_ref[$ref];
				$resolved_ref = $ref;
				break;
			}
		}
		if (!$txn) {
			return array('state' => 'REQUESTED', 'resolved_item_ref' => null, 'paid_amount' => null, 'net_amount' => null, 'payer_type' => null, 'payment_status' => null, 'order_status' => null);
		}

		$net = isset($txn->net_amount) ? (float)$txn->net_amount : null;
		$paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : null;
		$payer = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : null;

		$ps = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : '';
		$os = isset($txn->order_status) ? strtoupper(trim((string)$txn->order_status)) : '';
		if ($ps === 'FAILED' || $os === 'FAILED') {
			return array('state' => 'FAILED_BILLING', 'resolved_item_ref' => $resolved_ref, 'paid_amount' => $paid, 'net_amount' => $net, 'payer_type' => $payer, 'payment_status' => $ps, 'order_status' => $os);
		}

		$net_val = ($net === null) ? 0.0 : (float)$net;
		$paid_val = ($paid === null) ? 0.0 : (float)$paid;

		if ($paid_val <= 0.0 && $net_val > 0.0) {
			return array('state' => 'PENDING_PAYMENT', 'resolved_item_ref' => $resolved_ref, 'paid_amount' => $paid, 'net_amount' => $net, 'payer_type' => $payer, 'payment_status' => $ps, 'order_status' => $os);
		}
		if ($paid_val > 0.0 && $paid_val < $net_val) {
			return array('state' => 'PARTIAL', 'resolved_item_ref' => $resolved_ref, 'paid_amount' => $paid, 'net_amount' => $net, 'payer_type' => $payer, 'payment_status' => $ps, 'order_status' => $os);
		}
		if ($paid_val >= $net_val) {
			return array('state' => 'PAID', 'resolved_item_ref' => $resolved_ref, 'paid_amount' => $paid, 'net_amount' => $net, 'payer_type' => $payer, 'payment_status' => $ps, 'order_status' => $os);
		}

		return array('state' => 'REQUESTED', 'resolved_item_ref' => $resolved_ref, 'paid_amount' => $paid, 'net_amount' => $net, 'payer_type' => $payer, 'payment_status' => $ps, 'order_status' => $os);
	}

	public function get_financial_state($source_module, $source_id)
	{
		$detail = $this->get_financial_state_detail($source_module, $source_id);
		return isset($detail['state']) ? (string)$detail['state'] : 'REQUESTED';
	}

	public function audit_state_drift($source_module, $source_id)
	{
		$module = strtoupper(trim((string)$source_module));
		$source_id = (int)$source_id;
		$ssot_detail = $this->get_financial_state_detail($module, $source_id);
		$ssot = isset($ssot_detail['state']) ? (string)$ssot_detail['state'] : 'REQUESTED';
		$resolved_ref = isset($ssot_detail['resolved_item_ref']) ? $ssot_detail['resolved_item_ref'] : null;

		$local = 'REQUESTED';

		if (($module === 'LAB' || $module === 'LABORATORY') && $this->db->table_exists('iop_lab_billing')) {
			$this->db->where(array('io_lab_id' => $source_id, 'InActive' => 0));
			$row = $this->db->get('iop_lab_billing')->row();
			if ($row) {
				$ps = isset($row->payment_status) ? strtoupper(trim((string)$row->payment_status)) : '';
				$gen = isset($row->billing_generated) ? (int)$row->billing_generated : 0;
				$rate = isset($row->rate_amount) ? (float)$row->rate_amount : 0.0;
				if ($ps === 'FAILED') {
					$local = 'FAILED_BILLING';
				} elseif ($ps === 'PAID' || $ps === 'NHIS' || $ps === 'WAIVED') {
					$local = 'PAID';
				} elseif ($ps === 'PARTIAL') {
					$local = 'PARTIAL';
				} elseif ($gen === 1 && $rate > 0.0) {
					$local = 'PENDING_PAYMENT';
				} else {
					$local = 'REQUESTED';
				}
			}
		}

		if (($module === 'SONO' || $module === 'SONOGRAPHY') && $this->db->table_exists('iop_sonography_charge')) {
			$row = null;
			if ($this->db->field_exists('charge_id', 'iop_sonography_charge')) {
				$this->db->where(array('charge_id' => $source_id, 'InActive' => 0));
				$row = $this->db->get('iop_sonography_charge')->row();
			}
			if (!$row && $this->db->field_exists('io_lab_id', 'iop_sonography_charge')) {
				$this->db->where(array('io_lab_id' => $source_id, 'InActive' => 0));
				$row = $this->db->get('iop_sonography_charge')->row();
			}
			if ($row) {
				$st = isset($row->status) ? strtoupper(trim((string)$row->status)) : '';
				$rate = isset($row->rate_amount) ? (float)$row->rate_amount : 0.0;
				if ($st === 'FAILED') {
					$local = 'FAILED_BILLING';
				} elseif ($st === 'PAID' || $st === 'NHIS' || $st === 'WAIVED') {
					$local = 'PAID';
				} elseif ($st === 'PARTIAL') {
					$local = 'PARTIAL';
				} elseif ($st === 'PENDING' && $rate > 0.0) {
					$local = 'PENDING_PAYMENT';
				} else {
					$local = 'REQUESTED';
				}
			}
		}

		if (($module === 'RAD' || $module === 'RADIOLOGY') && $this->db->table_exists('radiology_orders')) {
			$this->db->where(array('id' => $source_id, 'InActive' => 0));
			$row = $this->db->get('radiology_orders')->row();
			if ($row) {
				$inv = isset($row->invoice_no) ? trim((string)$row->invoice_no) : '';
				$billed = isset($row->billed) ? (int)$row->billed : 0;
				if ($inv === 'BILLING_FAILED') {
					$local = 'FAILED_BILLING';
				} elseif ($billed === 1) {
					$local = 'BILLED';
				} else {
					$local = 'REQUESTED';
				}
			}
		}

		$drift = false;
		if ($local === 'FAILED_BILLING' && $ssot !== 'FAILED_BILLING') {
			$drift = true;
		} elseif ($local !== 'FAILED_BILLING' && $ssot === 'FAILED_BILLING') {
			$drift = true;
		} elseif ($module === 'RADIOLOGY' || $module === 'RAD') {
			if ($local === 'BILLED' && $ssot === 'REQUESTED') {
				$drift = true;
			} elseif ($local === 'REQUESTED' && $ssot !== 'REQUESTED') {
				$drift = true;
			}
		} else {
			if ($local === 'REQUESTED' && $ssot !== 'REQUESTED') {
				$drift = true;
			} elseif ($local !== 'REQUESTED' && $ssot === 'REQUESTED') {
				$drift = true;
			} elseif ($local !== $ssot) {
				$drift = true;
			}
		}

		$ssot_match_count = 0;
		try {
			$candidates = $this->normalize_source_ref($module, $source_id);
			if (!empty($candidates) && $this->db->table_exists('billing_transactions') && $this->db->field_exists('item_ref', 'billing_transactions')) {
				$this->db->select('COUNT(*) AS c');
				$this->db->from('billing_transactions');
				$this->db->where('InActive', 0);
				$this->db->where_in('item_ref', $candidates);
				$row = $this->db->get()->row();
				if ($row && isset($row->c)) {
					$ssot_match_count = (int)$row->c;
				}
			}
		} catch (\Throwable $e) {
		}

		$detail_for_classify = array(
			'state' => $ssot,
			'resolved_item_ref' => $resolved_ref,
			'net_amount' => array_key_exists('net_amount', $ssot_detail) ? $ssot_detail['net_amount'] : null,
			'payer_type' => array_key_exists('payer_type', $ssot_detail) ? $ssot_detail['payer_type'] : null,
			'payment_status' => array_key_exists('payment_status', $ssot_detail) ? $ssot_detail['payment_status'] : null,
			'order_status' => array_key_exists('order_status', $ssot_detail) ? $ssot_detail['order_status'] : null,
			'ssot_state' => $ssot,
			'local_state' => $local,
			'ssot_match_count' => $ssot_match_count
		);
		$class = $this->classify_drift($detail_for_classify);
		$drift_flags = isset($class['drift_flags']) ? $class['drift_flags'] : array();
		$severity = isset($class['severity']) ? (string)$class['severity'] : 'NONE';

		return array(
			'state' => $ssot,
			'source_id' => $source_id,
			'module' => $module,
			'resolved_item_ref' => $resolved_ref,
			'ssot_state' => $ssot,
			'local_state' => $local,
			'drift' => (bool)$drift,
			'drift_flags' => $drift_flags,
			'severity' => $severity
		);
	}

	private function classify_drift($detail)
	{
		$flags = array();
		$resolved = isset($detail['resolved_item_ref']) ? $detail['resolved_item_ref'] : null;
		if ($resolved === null || trim((string)$resolved) === '') {
			$flags[] = 'NO_SSOT_RECORD';
		}

		$net = isset($detail['net_amount']) ? (float)$detail['net_amount'] : null;
		$payer = isset($detail['payer_type']) ? strtoupper(trim((string)$detail['payer_type'])) : '';
		$ps = isset($detail['payment_status']) ? strtoupper(trim((string)$detail['payment_status'])) : '';
		$os = isset($detail['order_status']) ? strtoupper(trim((string)$detail['order_status'])) : '';
		$is_nhis = ($payer === 'NHIS' || $ps === 'NHIS' || $os === 'NHIS' || $ps === 'NHIS_APPROVED' || $os === 'NHIS_APPROVED');
		if ($net !== null && (float)$net == 0.0 && !$is_nhis) {
			$flags[] = 'ZERO_AMOUNT';
		}

		$mc = isset($detail['ssot_match_count']) ? (int)$detail['ssot_match_count'] : 0;
		if ($mc > 1) {
			$flags[] = 'MULTIPLE_SSOT';
		}

		$ssot_state = isset($detail['ssot_state']) ? strtoupper(trim((string)$detail['ssot_state'])) : '';
		$local_state = isset($detail['local_state']) ? strtoupper(trim((string)$detail['local_state'])) : '';
		if ($ssot_state !== '' && $local_state !== '' && $ssot_state !== $local_state) {
			$flags[] = 'STATE_MISMATCH';
		}

		$severity = 'NONE';
		if (in_array('NO_SSOT_RECORD', $flags, true) || in_array('ZERO_AMOUNT', $flags, true)) {
			$severity = 'BLOCK';
		} elseif (in_array('MULTIPLE_SSOT', $flags, true) || in_array('STATE_MISMATCH', $flags, true)) {
			$severity = 'WARN';
		}

		return array('drift_flags' => $flags, 'severity' => $severity);
	}

	public function detect_drift($source_module, $source_id, $gate_allowed = null)
	{
		$module = strtoupper(trim((string)$source_module));
		$source_id = (int)$source_id;
		$detail = $this->get_financial_state_detail($module, $source_id);
		$ssot_state = isset($detail['state']) ? (string)$detail['state'] : 'REQUESTED';
		$resolved_ref = isset($detail['resolved_item_ref']) ? $detail['resolved_item_ref'] : null;
		$drift_types = array();
		$policy_reason = null;

		$candidates = $this->normalize_source_ref($module, $source_id);
		$matches = array();
		$resolved_row = null;

		if (!empty($candidates) && $this->db->table_exists('billing_transactions') && $this->db->field_exists('item_ref', 'billing_transactions')) {
			$select = 'item_ref, net_amount, paid_amount, payment_status, order_status';
			if ($this->db->field_exists('payer_type', 'billing_transactions')) {
				$select = 'item_ref, net_amount, paid_amount, payment_status, payer_type, order_status';
			}
			$this->db->select($select);
			$this->db->from('billing_transactions');
			$this->db->where('InActive', 0);
			$this->db->where_in('item_ref', $candidates);
			$q = $this->db->get();
			$rows = $q ? $q->result() : array();

			foreach ($rows as $r) {
				$ref = isset($r->item_ref) ? (string)$r->item_ref : '';
				if ($ref !== '' && !isset($matches[$ref])) {
					$matches[$ref] = $r;
				}
			}

			if ($resolved_ref !== null && $resolved_ref !== '' && isset($matches[(string)$resolved_ref])) {
				$resolved_row = $matches[(string)$resolved_ref];
			}
		}

		if ($resolved_ref === null || $resolved_ref === '' || empty($matches)) {
			$drift_types[] = 'MISSING_SSOT';
		}

		if (count($matches) > 1) {
			$drift_types[] = 'MULTIPLE_MATCH';
		}

		if ($resolved_row && isset($resolved_row->net_amount) && (float)$resolved_row->net_amount == 0.0) {
			$drift_types[] = 'ZERO_PRICING';
		}

		if ($ssot_state === 'PENDING_PAYMENT' && $resolved_row) {
			$ps = isset($resolved_row->payment_status) ? strtoupper(trim((string)$resolved_row->payment_status)) : '';
			$payer = isset($resolved_row->payer_type) ? strtoupper(trim((string)$resolved_row->payer_type)) : '';
			if ($ps !== 'NHIS' && $payer !== 'NHIS' && $ps !== 'WAIVED') {
				$drift_types[] = 'GATE_MISMATCH';
			}
		}

		$paid_amount = array_key_exists('paid_amount', $detail) ? $detail['paid_amount'] : null;
		$net_amount = array_key_exists('net_amount', $detail) ? $detail['net_amount'] : null;
		$payer_type = array_key_exists('payer_type', $detail) ? strtoupper(trim((string)$detail['payer_type'])) : '';
		if ($resolved_row) {
			if (isset($resolved_row->paid_amount)) { $paid_amount = (float)$resolved_row->paid_amount; }
			if (isset($resolved_row->net_amount)) { $net_amount = (float)$resolved_row->net_amount; }
			if (isset($resolved_row->payer_type)) { $payer_type = strtoupper(trim((string)$resolved_row->payer_type)); }
		}

		$threshold = null;
		$env = getenv('partial_payment_threshold');
		if ($env === false) { $env = getenv('PARTIAL_PAYMENT_THRESHOLD'); }
		if ($env !== false) { $threshold = $env; }
		if ($threshold === null && isset($this->db) && $this->db->table_exists('system_option') && $this->db->field_exists('cCode', 'system_option') && $this->db->field_exists('cValue', 'system_option')) {
			$row = $this->db->get_where('system_option', array('cCode' => 'partial_payment_threshold', 'InActive' => 0), 1)->row();
			if (!$row) {
				$row = $this->db->get_where('system_option', array('cCode' => 'PARTIAL_PAYMENT_THRESHOLD', 'InActive' => 0), 1)->row();
			}
			if ($row && isset($row->cValue)) {
				$threshold = $row->cValue;
			}
		}
		if ($threshold === null && isset($this->config)) {
			$threshold = $this->config->item('partial_payment_threshold');
			if ($threshold === null) {
				$threshold = $this->config->item('PARTIAL_PAYMENT_THRESHOLD');
			}
		}
		$threshold = ($threshold === null) ? 1.0 : (float)$threshold;
		if ($threshold < 0.0) { $threshold = 0.0; }
		if ($threshold > 1.0) { $threshold = 1.0; }

		$policy_allowed = null;
		$ratio = null;
		$ss = strtoupper(trim((string)$ssot_state));
		if ($ss === 'PAID') {
			$policy_allowed = true;
			$policy_reason = 'PAID';
		} elseif ($ss === 'PARTIAL') {
			$paid_val = (float)$paid_amount;
			$net_val = (float)$net_amount;
			if ($net_val > 0.0) {
				$ratio = $paid_val / $net_val;
			} else {
				$ratio = 0.0;
			}
			if ($ratio >= $threshold) {
				$policy_allowed = true;
				$policy_reason = 'PARTIAL_THRESHOLD';
			} else {
				$policy_allowed = false;
				$policy_reason = 'BLOCKED';
			}
		} elseif ($payer_type === 'NHIS') {
			$policy_allowed = true;
			$policy_reason = 'NHIS_ALLOWED';
		} elseif ($payer_type === 'INSURANCE' || $payer_type === 'COMPANY') {
			$policy_allowed = true;
			$policy_reason = 'INSURANCE_ALLOWED';
		} else {
			$policy_allowed = false;
			$policy_reason = 'BLOCKED';
		}

		if ($gate_allowed !== null) {
			$ga = (bool)$gate_allowed;
			if ($ga && $policy_allowed === false) {
				if ($ss === 'PARTIAL' && $ratio !== null && $ratio < $threshold) {
					$drift_types[] = 'UNDERPAID_RELEASE';
				} else {
					$drift_types[] = 'POLICY_VIOLATION';
				}
			}
		}

		$severity = 'NONE';
		if (in_array('MISSING_SSOT', $drift_types, true) || in_array('MULTIPLE_MATCH', $drift_types, true) || in_array('UNDERPAID_RELEASE', $drift_types, true)) {
			$severity = 'CRITICAL';
		} elseif (in_array('GATE_MISMATCH', $drift_types, true) || in_array('POLICY_VIOLATION', $drift_types, true)) {
			$severity = 'HIGH';
		} elseif (in_array('ZERO_PRICING', $drift_types, true)) {
			$severity = 'MEDIUM';
		}

		return array(
			'drift_types' => $drift_types,
			'severity' => $severity,
			'resolved_item_ref' => $resolved_ref,
			'ssot_state' => $ssot_state,
			'policy_reason' => $policy_reason
		);
	}
}
