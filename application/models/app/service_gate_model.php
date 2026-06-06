<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Service Gate Model
 * 
 * Enforces payment-before-service rules across all clinical modules.
 * Acts as a security gate that blocks services until payment is confirmed.
 * 
 * Integration Points:
 * - Laboratory: check before processing lab requests
 * - Pharmacy: check before dispensing medications  
 * - Sonography: check before imaging procedures
 * - Radiology: check before X-ray/CT scans
 * - Procedures: check before surgical procedures
 * 
 * @author HMS Unified Billing Team
 * @version 1.0
 */
class Service_gate_model extends CI_Model
{
	// Gate Status Constants
	const GATE_BLOCKED = 'BLOCKED';
	const GATE_RELEASED = 'RELEASED';
	const GATE_EXPIRED = 'EXPIRED';
	const GATE_BYPASSED = 'BYPASSED';

	// Exception Types
	const EX_EMERGENCY = 'EMERGENCY';
	const EX_WAIVER = 'WAIVER';
	const EX_NHIS = 'NHIS';
	const EX_INSURANCE = 'INSURANCE';
	const EX_STAFF = 'STAFF';
	const EX_DEFERRED = 'DEFERRED';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/unified_billing_model', 'unified_billing');
		$this->load->model('app/billing_transaction_model', 'billing_txn');
		$this->load->model('app/Billing_canonical_model', 'billing_canonical');
	}

	/* ================================================================== */
	/*  CORE GATE CHECK - Primary Method for All Modules                  */
	/* ================================================================== */

	/**
	 * Check if a service can be performed
	 * Primary method used by all clinical modules
	 * 
	 * @param string $module Module code: LAB, PHARMACY, SONOGRAPHY, RADIOLOGY, PROCEDURE
	 * @param string $source_ref Reference to source record (e.g., lab request ID)
	 * @param string $iop_id Visit ID (optional - for additional validation)
	 * @param string $patient_no Patient number (optional)
	 * @return array Gate result with:
	 *   - allowed (bool): Can service proceed?
	 *   - status (string): BLOCKED, RELEASED, EXPIRED, BYPASSED
	 *   - reason (string): Human-readable explanation
	 *   - action_required (string): What user needs to do
	 *   - queue_id (int): Billing queue ID if applicable
	 *   - invoice_no (string): Invoice number if billed
	 *   - payment_url (string): URL to billing page
	 *   - exception (array): Exception details if bypass applies
	 */
	public function check_service($module, $source_ref, $iop_id = null, $patient_no = null)
	{
		// SSOT-first gate check (billing_transactions)
		$ssot_gate = $this->_check_ssot_gate($module, $source_ref, false, $iop_id, $patient_no);
		if ($ssot_gate !== null) {
			$gate_result = $ssot_gate;
		} else {
		// First check NEW unified billing system (billing_master + billing_items)
		$unified_gate = $this->_check_unified_billing_gate($module, $source_ref);
		if ($unified_gate !== null) {
			$gate_result = $unified_gate;
		} else {
			// Fallback to legacy unified billing gate
			$gate_result = $this->unified_billing->check_service_gate($module, $source_ref);
		}
		}

		// Build comprehensive response
		$result = array(
			'allowed' => $gate_result['allowed'],
			'status' => $gate_result['status'],
			'reason' => $gate_result['reason'],
			'blocked_reason' => isset($gate_result['blocked_reason']) ? $gate_result['blocked_reason'] : null,
			'queue_id' => isset($gate_result['queue_id']) ? $gate_result['queue_id'] : null,
			'invoice_no' => isset($gate_result['invoice_no']) ? $gate_result['invoice_no'] : null,
			'item_ref' => isset($gate_result['item_ref']) ? $gate_result['item_ref'] : null,
			'action_required' => null,
			'payment_url' => null,
			'exception' => null,
			'bypass_available' => false
		);

		$audit_drift = null;
		$audit_flags = array();
		$audit_sev = 'NONE';
		$audit_ref = '';
		$drift_level = 0;
		try {
			$drift_level = (int)$this->get_drift_level();
		} catch (\Throwable $e) {
			$drift_level = 0;
		}

		try {
			$dm = strtoupper(trim((string)$module));
			if ($dm === 'LAB' || $dm === 'LABORATORY' || $dm === 'SONOGRAPHY' || $dm === 'RADIOLOGY' || $dm === 'RAD') {
				if ($drift_level >= 1) {
					$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
					$audit_drift = $this->diag_fin_state->audit_state_drift($dm, (int)$source_ref);
					$audit_flags = (isset($audit_drift['drift_flags']) && is_array($audit_drift['drift_flags'])) ? $audit_drift['drift_flags'] : array();
					$audit_sev = isset($audit_drift['severity']) ? (string)$audit_drift['severity'] : 'NONE';
					$audit_ref = isset($audit_drift['resolved_item_ref']) ? (string)$audit_drift['resolved_item_ref'] : '';
				}
				if ($drift_level >= 2 && $audit_sev === 'BLOCK') {
					log_message('debug', '[DIAG_DRIFT] module=' . $dm . ' id=' . (int)$source_ref . ' resolved_ref=' . $audit_ref . ' audit_severity=' . $audit_sev . ' audit_flags=' . implode('|', $audit_flags) . ' drift_severity=NONE drift_types= policy_reason=');
					$result['allowed'] = false;
					$result['status'] = 'BLOCKED_DRIFT';
					$result['reason'] = implode(',', $audit_flags);
					$result['action_required'] = 'Resolve billing drift before proceeding';
					$result['bypass_available'] = false;
					return $result;
				}
			}
		} catch (\Throwable $e) {
		}

		$hard_blocked = false;
		try {
			if ($this->_drift_block_critical_enabled()) {
				$dm = strtoupper(trim((string)$module));
				if ($dm === 'LAB' || $dm === 'LABORATORY' || $dm === 'SONOGRAPHY' || $dm === 'RADIOLOGY' || $dm === 'RAD') {
					$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
					$dr = $this->diag_fin_state->detect_drift($dm, (int)$source_ref);
					$types = (isset($dr['drift_types']) && is_array($dr['drift_types'])) ? $dr['drift_types'] : array();
					if (in_array('MISSING_SSOT', $types, true) || in_array('MULTIPLE_MATCH', $types, true)) {
						$hard_blocked = true;
						$result['allowed'] = false;
						$result['status'] = self::GATE_BLOCKED;
						$result['reason'] = 'Critical drift detected';
						$result['bypass_available'] = false;
						$dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : '';
						log_message('error', '[DIAG_BLOCK] reason=CRITICAL_DRIFT module=' . $dm . ' id=' . (int)$source_ref . ' resolved_ref=' . $dref . ' drift=' . implode('|', $types));
					}
				}
			}
		} catch (\Throwable $e) {
		}

		// Set action required based on status
		if (!$result['allowed']) {
			if ($hard_blocked) {
				$result['action_required'] = 'Resolve critical billing drift before proceeding';
			} else {
				switch ($result['status']) {
					case self::GATE_BLOCKED:
						if (strpos($result['reason'], 'Not yet billed') !== false) {
							$result['action_required'] = 'Send patient to billing/cashier for invoicing';
							if ($iop_id) {
								$result['payment_url'] = base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id);
							}
						} else {
							$result['action_required'] = 'Collect payment before proceeding';
							if ($iop_id) {
								$result['payment_url'] = base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id);
							}
						}
						// Check for available exceptions
						$result['bypass_available'] = $this->can_bypass($module, $source_ref, $iop_id, $patient_no);
						break;

					case self::GATE_EXPIRED:
						$result['action_required'] = 'Re-billing required - payment window expired';
						break;
				}
			}
		}
		try {
			$dm = strtoupper(trim((string)$module));
			if ($dm === 'LAB' || $dm === 'LABORATORY' || $dm === 'SONOGRAPHY' || $dm === 'RADIOLOGY' || $dm === 'RAD') {
				$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
				$det = $this->diag_fin_state->get_financial_state_detail($dm, (int)$source_ref);
				$ssot_state = isset($det['state']) ? (string)$det['state'] : '';
				$ref = isset($det['resolved_item_ref']) ? (string)$det['resolved_item_ref'] : '';

				$drift_seg = '';
				$dr = null;
				if ($drift_level >= 1) {
					try {
						$dr = $this->diag_fin_state->detect_drift($dm, (int)$source_ref, $result['allowed']);
					} catch (\Throwable $e2) {
					}
				}

				$drift_types = array();
				$drift_sev = 'NONE';
				$policy_reason = '';
				$dref = $ref;
				if (isset($dr) && is_array($dr)) {
					if (isset($dr['drift_types']) && is_array($dr['drift_types'])) {
						$drift_types = $dr['drift_types'];
					}
					$drift_sev = isset($dr['severity']) ? (string)$dr['severity'] : 'NONE';
					$dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : $ref;
					$policy_reason = isset($dr['policy_reason']) ? (string)$dr['policy_reason'] : '';
				}
				if (!empty($drift_types)) {
					$drift_seg = ' drift=' . implode('|', $drift_types) . ' drift_severity=' . $drift_sev;
				}
				$resolved_for_log = ($audit_ref !== '') ? $audit_ref : $dref;
				if ($drift_level >= 1) {
					log_message('debug', '[DIAG_DRIFT] module=' . $dm . ' id=' . (int)$source_ref . ' resolved_ref=' . $resolved_for_log . ' audit_severity=' . $audit_sev . ' audit_flags=' . implode('|', $audit_flags) . ' drift_severity=' . $drift_sev . ' drift_types=' . implode('|', $drift_types) . ' policy_reason=' . $policy_reason);
				}

				log_message('debug', '[DIAG_GATE] module=' . $dm . ' id=' . (int)$source_ref . ' resolved_ref=' . $ref . ' gate_allowed=' . ($result['allowed'] ? '1' : '0') . ' gate_status=' . (isset($result['status']) ? (string)$result['status'] : '') . ' ssot_state=' . $ssot_state . $drift_seg);

				$paid_amount = array_key_exists('paid_amount', $det) ? $det['paid_amount'] : null;
				$net_amount = array_key_exists('net_amount', $det) ? $det['net_amount'] : null;
				$payer_type = array_key_exists('payer_type', $det) ? (string)$det['payer_type'] : '';
				$policy = $this->evaluate_payment_policy($dm, $ssot_state, $paid_amount, $net_amount, $payer_type);

				$ratio = '';
				if (strtoupper(trim((string)$ssot_state)) === 'PARTIAL') {
					$paid_val = (float)$paid_amount;
					$net_val = (float)$net_amount;
					if ($net_val > 0.0) {
						$ratio = (string)($paid_val / $net_val);
					}
				}

				log_message('debug', '[DIAG_POLICY] module=' . $dm . ' id=' . (int)$source_ref . ' resolved_ref=' . $ref . ' policy=' . (isset($policy['reason']) ? (string)$policy['reason'] : '') . ' ratio=' . $ratio . ' allowed=' . ((isset($policy['allowed']) && $policy['allowed']) ? 'true' : 'false') . ' payer_type=' . $payer_type . ' paid_amount=' . (($paid_amount === null) ? '' : (string)$paid_amount) . ' net_amount=' . (($net_amount === null) ? '' : (string)$net_amount));
			}
		} catch (\Throwable $e) {
		}

		return $result;
	}

	/**
	 * Raw gate check (no override/bypass layer).
	 *
	 * This is intended for observability/parity checks, where override state
	 * (e.g. exceptions/emergency flags) must not affect the gate truth.
	 */
	public function check_service_raw($module, $source_ref, $iop_id = null, $patient_no = null)
	{
		$ssot_gate = $this->_check_ssot_gate($module, $source_ref, true, $iop_id, $patient_no);
		if ($ssot_gate !== null) {
			$gate_result = $ssot_gate;
		} else {
			$unified_gate = $this->_check_unified_billing_gate($module, $source_ref);
			if ($unified_gate !== null) {
				$gate_result = $unified_gate;
			} else {
				$gate_result = $this->unified_billing->check_service_gate($module, $source_ref);
			}
		}

		return array(
			'allowed' => $gate_result['allowed'],
			'status' => $gate_result['status'],
			'reason' => $gate_result['reason'],
			'blocked_reason' => isset($gate_result['blocked_reason']) ? $gate_result['blocked_reason'] : null,
			'queue_id' => isset($gate_result['queue_id']) ? $gate_result['queue_id'] : null,
			'invoice_no' => isset($gate_result['invoice_no']) ? $gate_result['invoice_no'] : null,
			'item_ref' => isset($gate_result['item_ref']) ? $gate_result['item_ref'] : null,
			'action_required' => null,
			'payment_url' => null,
			'exception' => null,
			'bypass_available' => false
		);
	}

	public function check_service_by_item_ref_raw($item_ref)
	{
		return $this->check_service_by_item_ref($item_ref, true);
	}

	public function check_service_by_item_ref($item_ref, $raw = false)
	{
		$item_ref = trim((string)$item_ref);
		if ($item_ref === '') {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Missing item_ref', 'blocked_reason' => 'INVALID_REF', 'item_ref' => $item_ref);
		}

		$module = '';
		$source_ref = '';
		if (strpos($item_ref, 'io_lab_id:') === 0) {
			$module = 'LAB';
			$source_ref = (string)(int)substr($item_ref, strlen('io_lab_id:'));
		} elseif (strpos($item_ref, 'radiology_order_id:') === 0) {
			$module = 'RADIOLOGY';
			$source_ref = (string)(int)substr($item_ref, strlen('radiology_order_id:'));
		} elseif (strpos($item_ref, 'sono_charge_id:') === 0) {
			$module = 'SONOGRAPHY';
			$source_ref = (string)(int)substr($item_ref, strlen('sono_charge_id:'));
		} elseif (strpos($item_ref, 'iop_med_id:') === 0) {
			$module = 'PHARMACY';
			$source_ref = (string)(int)substr($item_ref, strlen('iop_med_id:'));
		} elseif (strpos($item_ref, 'opd_procedure_request_id:') === 0) {
			$module = 'PROCEDURE';
			$source_ref = (string)(int)substr($item_ref, strlen('opd_procedure_request_id:'));
		}

		if ($module !== '' && $source_ref !== '' && (int)$source_ref > 0) {
			if ($raw && method_exists($this, 'check_service_raw')) {
				return $this->check_service_raw($module, $source_ref, null, null);
			}
			return $this->check_service($module, $source_ref, null, null);
		}

		if (strpos($item_ref, 'walkin_order_item_id:') !== 0) {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Unsupported item_ref format', 'blocked_reason' => 'UNSUPPORTED_REF', 'item_ref' => $item_ref);
		}

		$internal_id = (int)substr($item_ref, strlen('walkin_order_item_id:'));
		if ($internal_id <= 0) {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Invalid item_ref', 'blocked_reason' => 'INVALID_REF', 'item_ref' => $item_ref);
		}

		$walkin_order_id = null;
		$dept = null;
		try {
			if (!$this->db->table_exists('walkin_order_items')) {
				return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Walk-in schema not ready', 'blocked_reason' => 'SCHEMA', 'item_ref' => $item_ref);
			}
			$row = $this->db->get_where('walkin_order_items', array('internal_id' => $internal_id, 'InActive' => 0), 1)->row();
			if ($row && isset($row->walkin_order_id)) {
				$walkin_order_id = (string)$row->walkin_order_id;
			}
			if ($row && isset($row->department)) {
				$dept = strtoupper(trim((string)$row->department));
			}
		} catch (\Throwable $e) {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Gate check failed', 'blocked_reason' => 'ERROR', 'item_ref' => $item_ref);
		}
		if ($walkin_order_id === null || $walkin_order_id === '') {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Order item not found', 'blocked_reason' => 'NOT_FOUND', 'item_ref' => $item_ref);
		}

		$txn = null;
		try {
			if (isset($this->billing_txn) && method_exists($this->billing_txn, 'ensure_billing_transaction_schema')) {
				$this->billing_txn->ensure_billing_transaction_schema();
			}
			if ($this->db->table_exists('billing_transactions')) {
				$this->db->where('InActive', 0);
				$this->db->where('item_ref', $item_ref);
				if ($this->db->field_exists('billing_subject_type', 'billing_transactions') && $this->db->field_exists('billing_subject_id', 'billing_transactions')) {
					$this->db->where('billing_subject_type', 'WALKIN_ORDER');
					$this->db->where('billing_subject_id', $walkin_order_id);
				}
				$this->db->order_by('txn_id', 'DESC');
				$this->db->limit(2);
				$txns = $this->db->get('billing_transactions')->result();
				if (is_array($txns) && count($txns) > 1) {
					return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Multiple billing records exist', 'blocked_reason' => 'MULTIPLE_SSOT', 'item_ref' => $item_ref);
				}
				if (is_array($txns) && count($txns) === 1) {
					$txn = $txns[0];
				}
			}
		} catch (\Throwable $e) {
			$txn = null;
		}

		if ($txn) {
			$module2 = ($dept === 'PHARMACY') ? 'PHARMACY' : 'WALKIN';
			$invoice_no = (isset($txn->invoice_no) && trim((string)$txn->invoice_no) !== '') ? (string)$txn->invoice_no : null;

			$pay = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
			$payer = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : 'CASH';
			$bal = isset($txn->balance_amount) ? (float)$txn->balance_amount : 0.0;
			$net_amt = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
			$paid_amt = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
			list($authStatus, $authCode) = $this->_resolve_ssot_auth_fields($txn);
			$enforce_auth = $this->_get_runtime_bool('enforce_insurance_auth', false);

			if (!$raw) {
				try {
					if ($this->has_active_exception($module2, (string)$internal_id)) {
						$ex = $this->get_active_exception($module2, (string)$internal_id);
						$exType = ($ex && isset($ex->exception_type)) ? (string)$ex->exception_type : 'EXCEPTION';
						$exReason = ($ex && isset($ex->reason)) ? (string)$ex->reason : 'Active exception';
						return array(
							'allowed' => true,
							'status' => self::GATE_BYPASSED,
							'reason' => 'Bypass allowed - active exception (' . $exType . ')',
							'blocked_reason' => 'ALLOWED',
							'invoice_no' => $invoice_no,
							'item_ref' => $item_ref,
							'exception' => $ex ? array('type' => $exType, 'reason' => $exReason) : null,
						);
					}
				} catch (\Throwable $e) {
				}
			}

			$rule = $this->_resolve_ssot_gate_rule(false, $payer, $pay, $net_amt, $paid_amt, $bal, $authStatus, $authCode, $enforce_auth);
			$blocked_reason = isset($rule['blocked_reason']) ? (string)$rule['blocked_reason'] : null;
			$allowed = isset($rule['allowed']) ? (bool)$rule['allowed'] : false;
			if ($allowed) {
				if ($payer !== '' && $payer !== 'CASH') {
					return array('allowed' => true, 'status' => self::GATE_RELEASED, 'reason' => $payer . ' covered', 'blocked_reason' => 'ALLOWED', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
				}
				return array('allowed' => true, 'status' => self::GATE_RELEASED, 'reason' => 'Payment cleared', 'blocked_reason' => 'ALLOWED', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			if ($blocked_reason === 'ZERO_PRICE') {
				return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Pricing not configured (0.00). Contact admin to set price.', 'blocked_reason' => $blocked_reason, 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			if ($blocked_reason === 'AUTH_REQUIRED') {
				return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => $payer . ' authorization required', 'blocked_reason' => $blocked_reason, 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			if ($blocked_reason === 'PARTIAL_NOT_ALLOWED') {
				return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Full payment required before processing', 'blocked_reason' => $blocked_reason, 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Payment required. Balance: ' . number_format($bal, 2), 'blocked_reason' => 'PAYMENT_PENDING', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
		}

		if (!$this->db->table_exists('billing_queue')) {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Not yet billed - please send to billing/cashier', 'blocked_reason' => 'NOT_BILLED', 'item_ref' => $item_ref);
		}
		$this->db->where('source_module', 'WALKIN_ORDER_ITEM');
		$this->db->where('source_ref', $item_ref);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$q = $this->db->get('billing_queue')->row();
		if (!$q) {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Not yet billed - please send to billing/cashier', 'blocked_reason' => 'NOT_BILLED', 'item_ref' => $item_ref);
		}

		$gate = $this->unified_billing->check_service_gate('WALKIN_ORDER_ITEM', $item_ref);
		if (!is_array($gate)) {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Gate check failed', 'blocked_reason' => 'ERROR', 'item_ref' => $item_ref);
		}
		$gate['item_ref'] = $item_ref;
		return $gate;
	}

	private function get_drift_level()
	{
		$level = 0;
		try {
			if (!isset($this->db) || !$this->db->table_exists('system_option') || !$this->db->field_exists('cCode', 'system_option') || !$this->db->field_exists('cValue', 'system_option')) {
				return 0;
			}

			$row = $this->db->get_where('system_option', array('cCode' => 'diagnostic_drift_enforcement_level', 'InActive' => 0), 1)->row();
			if (!$row) {
				$row = $this->db->get_where('system_option', array('cCode' => 'DIAGNOSTIC_DRIFT_ENFORCEMENT_LEVEL', 'InActive' => 0), 1)->row();
			}
			if ($row && isset($row->cValue)) {
				$raw = $row->cValue;
				if (is_numeric($raw)) {
					$level = (int)$raw;
				} else {
					$level = (int)trim((string)$raw);
				}
			}
		} catch (\Throwable $e) {
			$level = 0;
		}
		if ($level < 0) { $level = 0; }
		return $level;
	}

	public function evaluate_payment_policy($module, $ssot_state, $paid_amount, $net_amount, $payer_type)
	{
		$module = strtoupper(trim((string)$module));
		$ssot_state = strtoupper(trim((string)$ssot_state));
		$payer_type = strtoupper(trim((string)$payer_type));
		$paid_amount = (float)$paid_amount;
		$net_amount = (float)$net_amount;

		$threshold = $this->_get_partial_payment_threshold();

		if ($ssot_state === 'PAID') {
			return array('allowed' => true, 'reason' => 'PAID');
		}

		if ($ssot_state === 'PARTIAL') {
			$ratio = 0.0;
			if ($net_amount > 0.0) {
				$ratio = $paid_amount / $net_amount;
			}
			if ($ratio >= $threshold) {
				return array('allowed' => true, 'reason' => 'PARTIAL_THRESHOLD');
			}
			return array('allowed' => false, 'reason' => 'BLOCKED');
		}

		if ($payer_type === 'NHIS') {
			return array('allowed' => true, 'reason' => 'NHIS_ALLOWED');
		}

		if ($payer_type === 'INSURANCE' || $payer_type === 'COMPANY') {
			return array('allowed' => true, 'reason' => 'INSURANCE_ALLOWED');
		}

		return array('allowed' => false, 'reason' => 'BLOCKED');
	}

	private function _resolve_ssot_auth_fields($txn)
	{
		$authStatus = '';
		$authCode = '';
		try {
			if (isset($txn->authorization_status)) {
				$authStatus = strtoupper(trim((string)$txn->authorization_status));
			} elseif (isset($txn->auth_status)) {
				$authStatus = strtoupper(trim((string)$txn->auth_status));
			}
			if (isset($txn->authorization_code)) {
				$authCode = trim((string)$txn->authorization_code);
			} elseif (isset($txn->auth_code)) {
				$authCode = trim((string)$txn->auth_code);
			}
		} catch (\Throwable $e) {
		}
		return array($authStatus, $authCode);
	}

	private function _resolve_ssot_gate_rule($force_ssot, $payer, $pay, $net_amt, $paid_amt, $bal, $authStatus, $authCode, $enforce_auth)
	{
		$payer = strtoupper(trim((string)$payer));
		$pay = strtoupper(trim((string)$pay));
		$authStatus = strtoupper(trim((string)$authStatus));
		$authCode = trim((string)$authCode);

		$net_amt = (float)$net_amt;
		$paid_amt = (float)$paid_amt;
		$bal = (float)$bal;

		if ($net_amt <= 0.0) {
			return array('allowed' => false, 'blocked_reason' => 'ZERO_PRICE');
		}

		if ($enforce_auth && $force_ssot) {
			$payerKey = strtoupper(trim((string)$payer));
			if (in_array($payerKey, array('INSURANCE', 'COMPANY', 'NHIS'), true)) {
				$isAuthorized = ($authCode !== '' || $authStatus === 'APPROVED');
				if (!$isAuthorized) {
					return array('allowed' => false, 'blocked_reason' => 'AUTH_REQUIRED');
				}
			}
		}

		if ($pay === 'PARTIAL') {
			if ($force_ssot) {
				return array('allowed' => false, 'blocked_reason' => 'PARTIAL_NOT_ALLOWED');
			}
			return array('allowed' => false, 'blocked_reason' => 'PAYMENT_PENDING');
		}

		if ($payer !== '' && $payer !== 'CASH') {
			return array('allowed' => true, 'blocked_reason' => 'ALLOWED');
		}

		if (in_array($pay, array('PAID', 'WAIVED', 'NHIS'), true)) {
			return array('allowed' => true, 'blocked_reason' => 'ALLOWED');
		}

		if ($bal > 0.0) {
			return array('allowed' => false, 'blocked_reason' => 'PAYMENT_PENDING');
		}

		if ($paid_amt >= $net_amt && $net_amt > 0.0) {
			return array('allowed' => true, 'blocked_reason' => 'ALLOWED');
		}

		return array('allowed' => false, 'blocked_reason' => 'PAYMENT_PENDING');
	}

	/**
	 * SSOT Gate Check (billing_transactions)
	 * Returns null if SSOT cannot make a deterministic decision.
	 */
	private function _check_ssot_gate($module, $source_ref, $raw = false, $iop_id = null, $patient_no = null)
	{
		if (!isset($this->billing_txn) || !method_exists($this->billing_txn, 'ensure_billing_transaction_schema')) {
			return null;
		}
		$this->billing_txn->ensure_billing_transaction_schema();
		if (!$this->db->table_exists('billing_transactions')) {
			return null;
		}

		$module = strtoupper(trim((string)$module));
		$source_ref = trim((string)$source_ref);
		if ($module === '' || $source_ref === '') {
			return null;
		}

		$resolved_ref = $this->billing_canonical->resolve_service_reference($module, $source_ref, array('iop_id' => $iop_id, 'patient_no' => $patient_no));
		if (!$resolved_ref) {
			return null;
		}
		$dept = $resolved_ref['department'];
		$item_ref = $resolved_ref['item_ref'];
		$source_module = $resolved_ref['source_module'];
		$lock_source_ref = $resolved_ref['lock_source_ref'];
		$source_ref = (string)$resolved_ref['canonical_id'];

		$force_ssot = in_array($module, array('LAB', 'LABORATORY', 'SONO', 'SONOGRAPHY', 'RADIOLOGY', 'RAD'), true);
		$ssot_missing = false;
		if ($item_ref === null || trim((string)$item_ref) === '') {
			$ssot_missing = true;
		}
		if ($ssot_missing) {
			if ($force_ssot || $this->is_ssot_enforcement_enabled()) {
				if (!$raw && $this->_ssot_missing_bypass_allowed($module, $source_ref)) {
					$this->_log_diag_ssot($module, $source_ref, $item_ref, true, true);
					return array('allowed' => true, 'status' => self::GATE_BYPASSED, 'reason' => 'Bypass allowed - SSOT missing', 'item_ref' => $item_ref);
				}
				$this->_log_diag_ssot($module, $source_ref, $item_ref, true, false);
				log_message('warning', '[DIAG_GATE_BLOCK] module=' . $module . ' id=' . (int)$source_ref . ' reason=NO_SSOT');
				return array('allowed' => false, 'status' => 'BLOCKED_NO_SSOT', 'reason' => 'No billing record exists', 'blocked_reason' => 'NO_SSOT', 'item_ref' => $item_ref);
			}
			return null;
		}

		// Best-effort: if this item is invoiced, link SSOT transactions to invoice
		$invoice_no = null;
		if ($this->db->table_exists('iop_billable_item_lock')) {
			$this->db->select('invoice_no');
			$this->db->from('iop_billable_item_lock');
			$this->db->where(array('source_module' => $source_module, 'source_ref' => $lock_source_ref, 'InActive' => 0));
			$this->db->order_by('lock_id', 'DESC');
			$this->db->limit(1);
			$row = $this->db->get()->row();
			if ($row && isset($row->invoice_no) && trim((string)$row->invoice_no) !== '') {
				$invoice_no = (string)$row->invoice_no;
				if (method_exists($this->billing_txn, 'link_transactions_to_invoice')) {
					$this->billing_txn->link_transactions_to_invoice($invoice_no, null);
				}
			}
		}

		// Fetch SSOT txn
		$this->db->where('InActive', 0);
		$this->db->where('department', $dept);
		$this->db->where('item_ref', $item_ref);
		$this->db->limit(1);
		$txn = $this->db->get('billing_transactions')->row();
		if (!$txn) {
			$ssot_missing = true;
			if ($force_ssot || $this->is_ssot_enforcement_enabled()) {
				if (!$raw && $this->_ssot_missing_bypass_allowed($module, $source_ref)) {
					$this->_log_diag_ssot($module, $source_ref, $item_ref, true, true);
					return array('allowed' => true, 'status' => self::GATE_BYPASSED, 'reason' => 'Bypass allowed - SSOT missing', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
				}
				$this->_log_diag_ssot($module, $source_ref, $item_ref, true, false);
				log_message('warning', '[DIAG_GATE_BLOCK] module=' . $module . ' id=' . (int)$source_ref . ' reason=NO_BILLING_TXN');
				return array('allowed' => false, 'status' => 'BLOCKED_NO_SSOT', 'reason' => 'No billing record exists', 'blocked_reason' => 'NO_SSOT', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			return null;
		}

		if (!$raw) {
			try {
				if ($this->has_active_exception($module, $source_ref)) {
					$ex = $this->get_active_exception($module, $source_ref);
					$exType = ($ex && isset($ex->exception_type)) ? (string)$ex->exception_type : 'EXCEPTION';
					$exReason = ($ex && isset($ex->reason)) ? (string)$ex->reason : 'Active exception';
					return array(
						'allowed' => true,
						'status' => self::GATE_BYPASSED,
						'reason' => 'Bypass allowed - active exception (' . $exType . ')',
						'blocked_reason' => 'ALLOWED',
						'invoice_no' => $invoice_no,
						'item_ref' => $item_ref,
						'exception' => $ex ? array('type' => $exType, 'reason' => $exReason) : null,
					);
				}
			} catch (\Throwable $e) {
			}
		}

		$pay = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
		$payer = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : 'CASH';
		$order_status = isset($txn->order_status) ? strtoupper(trim((string)$txn->order_status)) : '';
		$bal = isset($txn->balance_amount) ? (float)$txn->balance_amount : 0.0;
		$net_amt = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
		$paid_amt = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
		list($authStatus, $authCode) = $this->_resolve_ssot_auth_fields($txn);
		$enforce_auth = $this->_get_runtime_bool('enforce_insurance_auth', false);

		if ($pay === 'PARTIAL' && !$force_ssot) {
			$balance = $net_amt - $paid_amt;
			$ratio = 0.0;
			$threshold = $this->get_partial_threshold_percent() / 100;
			$floor = $this->get_partial_balance_floor();
			$allow = false;

			if ($net_amt <= 0.0) {
				$allow = true;
			} else {
				$ratio = $paid_amt / $net_amt;
				if ($ratio >= $threshold || $balance <= $floor) {
					$allow = true;
				}
			}

			if (in_array($module, array('LAB', 'LABORATORY', 'SONOGRAPHY', 'RADIOLOGY', 'RAD'), true)) {
				log_message(
					'debug',
					'[DIAG_PARTIAL] module=' . $module .
					' id=' . (int)$source_ref .
					' paid=' . $paid_amt .
					' net=' . $net_amt .
					' ratio=' . $ratio .
					' threshold=' . $threshold .
					' balance=' . $balance .
					' floor=' . $floor .
					' allowed=' . (int)$allow
				);
			}

			if ($allow) {
				$this->_log_diag_ssot($module, $source_ref, $item_ref, false, true);
				return array('allowed' => true, 'status' => self::GATE_RELEASED, 'reason' => 'Partial payment threshold met. Balance: ' . number_format($balance, 2), 'blocked_reason' => 'ALLOWED', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			$this->_log_diag_ssot($module, $source_ref, $item_ref, false, false);
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Payment below required threshold. Balance: ' . number_format($balance, 2), 'blocked_reason' => 'PAYMENT_PENDING', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
		}

		$rule = $this->_resolve_ssot_gate_rule($force_ssot, $payer, $pay, $net_amt, $paid_amt, $bal, $authStatus, $authCode, $enforce_auth);
		$blocked_reason = isset($rule['blocked_reason']) ? (string)$rule['blocked_reason'] : null;
		$allowed = isset($rule['allowed']) ? (bool)$rule['allowed'] : false;
		if ($allowed) {
			$this->_log_diag_ssot($module, $source_ref, $item_ref, false, true);
			if ($payer !== '' && $payer !== 'CASH') {
				return array('allowed' => true, 'status' => self::GATE_RELEASED, 'reason' => $payer . ' covered', 'blocked_reason' => 'ALLOWED', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
			}
			return array('allowed' => true, 'status' => self::GATE_RELEASED, 'reason' => 'Payment cleared', 'blocked_reason' => 'ALLOWED', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
		}

		$this->_log_diag_ssot($module, $source_ref, $item_ref, false, false);
		if ($blocked_reason !== null && $blocked_reason !== '') {
			log_message('warning', '[DIAG_GATE_BLOCK] module=' . $module . ' id=' . (int)$source_ref . ' reason=' . $blocked_reason);
		}
		if ($blocked_reason === 'ZERO_PRICE') {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Pricing not configured (0.00). Contact admin to set price.', 'blocked_reason' => $blocked_reason, 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
		}
		if ($blocked_reason === 'AUTH_REQUIRED') {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => $payer . ' authorization required', 'blocked_reason' => $blocked_reason, 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
		}
		if ($blocked_reason === 'PARTIAL_NOT_ALLOWED') {
			return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Full payment required before processing', 'blocked_reason' => $blocked_reason, 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
		}
		return array('allowed' => false, 'status' => self::GATE_BLOCKED, 'reason' => 'Payment required. Balance: ' . number_format($bal, 2), 'blocked_reason' => 'PAYMENT_PENDING', 'invoice_no' => $invoice_no, 'item_ref' => $item_ref);
	}

	private function is_ssot_enforcement_enabled()
	{
		try {
			return $this->_get_runtime_bool('diagnostic_ssot_enforcement', false) ? true : false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function _log_diag_ssot($module, $source_ref, $item_ref, $ssot_missing, $allowed)
	{
		try {
			if (!in_array($module, array('LAB', 'LABORATORY', 'SONOGRAPHY', 'RADIOLOGY', 'RAD'), true)) {
				return;
			}
			log_message(
				'debug',
				'[DIAG_SSOT] module=' . $module .
				' id=' . (int)$source_ref .
				' resolved_ref=' . (string)$item_ref .
				' ssot_exists=' . (int)!$ssot_missing .
				' allowed=' . (int)$allowed
			);
		} catch (\Throwable $e) {
		}
	}

	private function _ssot_missing_bypass_allowed($module, $source_ref)
	{
		try {
			if ($this->has_active_exception($module, $source_ref)) {
				return true;
			}
		} catch (\Throwable $e) {
		}

		try {
			$mid = strtoupper(trim((string)$module));
			$id = (int)$source_ref;
			if ($id > 0 && ($mid === 'LAB' || $mid === 'LABORATORY' || $mid === 'SONOGRAPHY')) {
				if (isset($this->db) && $this->db->table_exists('iop_laboratory') && $this->db->field_exists('io_lab_id', 'iop_laboratory') && $this->db->field_exists('emergency_flag', 'iop_laboratory')) {
					$this->db->where(array('io_lab_id' => $id, 'emergency_flag' => 1, 'InActive' => 0));
					$this->db->limit(1);
					$row = $this->db->get('iop_laboratory')->row();
					if ($row) {
						return true;
					}
				}
			}
		} catch (\Throwable $e) {
		}

		try {
			$id = (int)$source_ref;
			if ($id > 0 && isset($this->db) && $this->db->table_exists('imaging_billing_bypass')) {
				if ($this->db->field_exists('io_lab_id', 'imaging_billing_bypass') && $this->db->field_exists('is_active', 'imaging_billing_bypass')) {
					$this->db->where(array('io_lab_id' => $id, 'is_active' => 1, 'InActive' => 0));
					$this->db->limit(1);
					$row = $this->db->get('imaging_billing_bypass')->row();
					if ($row) {
						return true;
					}
				}
			}
		} catch (\Throwable $e) {
		}

		return false;
	}

	private function _get_partial_payment_threshold()
	{
		$val = $this->_get_runtime_float('partial_payment_threshold', 0.5);
		if ($val < 0.0) { $val = 0.0; }
		if ($val > 1.0) { $val = 1.0; }
		return $val;
	}

	private function get_partial_threshold_percent()
	{
		$val = $this->_get_runtime_float('partial_threshold_percent', 100.0);
		if ($val < 0.0) { $val = 0.0; }
		if ($val > 100.0) { $val = 100.0; }
		return $val;
	}

	private function get_partial_balance_floor()
	{
		$val = $this->_get_runtime_float('partial_balance_floor', 0.0);
		if ($val < 0.0) { $val = 0.0; }
		return $val;
	}

	private function _get_runtime_float($key, $default)
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
		if ($cfg === null) { return (float)$default; }
		if (is_numeric($cfg)) { return (float)$cfg; }
		return (float)trim((string)$cfg);
	}

	private function _get_runtime_bool($key, $default)
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

	/**
	 * Quick check - returns boolean only
	 * Use when you just need to know if service can proceed
	 * 
	 * @param string $module Module code
	 * @param string $source_ref Reference ID
	 * @return bool True if service can proceed
	 */
	public function can_proceed($module, $source_ref)
	{
		$result = $this->unified_billing->check_service_gate($module, $source_ref);
		return $result['allowed'];
	}

	/* ================================================================== */
	/*  EXCEPTION HANDLING - Emergency & Authorized Bypasses              */
	/* ================================================================== */

	/**
	 * Check if service can be bypassed with an exception
	 * 
	 * @param string $module Module code
	 * @param string $source_ref Reference ID
	 * @param string $iop_id Visit ID
	 * @param string $patient_no Patient number
	 * @return bool True if bypass is available
	 */
	public function can_bypass($module, $source_ref, $iop_id = null, $patient_no = null)
	{
		// Check for existing exception
		if ($this->has_active_exception($module, $source_ref)) {
			return true;
		}

		// Check if user has bypass authority
		$ci = &get_instance();
		$role_id = $ci->session->userdata('user_role');
		
		// Only doctors and above can authorize emergency bypass
		if (in_array($role_id, array(2, 5, 9))) { // doctor, admin, medical director
			return true;
		}

		return false;
	}

	/**
	 * Create an exception to bypass payment gate
	 * 
	 * @param string $module Module code
	 * @param string $source_ref Reference ID
	 * @param string $exception_type Type of exception (EMERGENCY, WAIVER, etc)
	 * @param string $reason Justification for bypass
	 * @param string $authorized_by User ID authorizing bypass
	 * @param string $patient_no Patient number
	 * @param string $iop_id Visit ID
	 * @return array Result with success status
	 */
	public function create_exception($module, $source_ref, $exception_type, $reason, $authorized_by, $patient_no = null, $iop_id = null)
	{
		// Ensure exception log table exists
		$this->_ensure_exception_table();

		// Validate exception type
		$valid_types = array(self::EX_EMERGENCY, self::EX_WAIVER, self::EX_NHIS, self::EX_INSURANCE, self::EX_STAFF, self::EX_DEFERRED);
		if (!in_array($exception_type, $valid_types)) {
			return array('success' => false, 'error' => 'Invalid exception type');
		}

		// Check authorization level
		$ci = &get_instance();
		$role_id = $ci->session->userdata('user_role');
		
		// Only doctors and above can authorize exceptions
		if (!in_array($role_id, array(2, 5, 9))) {
			return array('success' => false, 'error' => 'Insufficient authorization - doctor or admin required');
		}

		$insert = array(
			'module' => $module,
			'source_ref' => $source_ref,
			'patient_no' => $patient_no,
			'iop_id' => $iop_id,
			'exception_type' => $exception_type,
			'reason' => $reason,
			'authorized_by' => $authorized_by,
			'status' => 'ACTIVE',
			'created_at' => date('Y-m-d H:i:s')
		);
		if ($this->db->field_exists('InActive', 'service_gate_exceptions')) {
			$insert['InActive'] = 0;
		}
		$this->db->insert('service_gate_exceptions', $insert);

		$exception_id = $this->db->insert_id();

		// If there's a billing queue item, also release the gate
		$this->db->where('source_module', $module);
		$this->db->where('source_ref', $source_ref);
		$item = $this->db->get('billing_queue')->row();
		if ($item) {
			$this->unified_billing->release_service_gate($item->queue_id, 'EXCEPTION_' . $exception_type);
		}

		return array(
			'success' => true,
			'exception_id' => $exception_id,
			'message' => 'Service gate bypass authorized via ' . $exception_type
		);
	}

	public function create_exception_system($module, $source_ref, $exception_type, $reason, $authorized_by, $patient_no = null, $iop_id = null)
	{
		$this->_ensure_exception_table();

		$module = strtoupper(trim((string)$module));
		$source_ref = trim((string)$source_ref);
		$exception_type = strtoupper(trim((string)$exception_type));
		$reason = trim((string)$reason);
		$authorized_by = trim((string)$authorized_by);
		$patient_no = ($patient_no !== null) ? trim((string)$patient_no) : null;
		$iop_id = ($iop_id !== null) ? trim((string)$iop_id) : null;

		if ($module === '' || $source_ref === '' || $exception_type === '' || $reason === '' || $authorized_by === '') {
			return array('success' => false, 'error' => 'Missing required fields');
		}

		$insert = array(
			'module' => $module,
			'source_ref' => $source_ref,
			'patient_no' => $patient_no,
			'iop_id' => $iop_id,
			'exception_type' => $exception_type,
			'reason' => $reason,
			'authorized_by' => $authorized_by,
			'status' => 'ACTIVE',
			'created_at' => date('Y-m-d H:i:s')
		);
		if ($this->db->field_exists('InActive', 'service_gate_exceptions')) {
			$insert['InActive'] = 0;
		}
		$this->db->insert('service_gate_exceptions', $insert);
		$exception_id = $this->db->insert_id();
		return array('success' => true, 'exception_id' => $exception_id);
	}

	/**
	 * Check if an active exception exists
	 * 
	 * @param string $module Module code
	 * @param string $source_ref Reference ID
	 * @return bool True if active exception exists
	 */
	public function has_active_exception($module, $source_ref)
	{
		if (!$this->db->table_exists('service_gate_exceptions')) {
			return false;
		}

		$this->db->where('module', $module);
		$this->db->where('source_ref', $source_ref);
		$this->db->where('status', 'ACTIVE');
		$this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours'))); // 24-hour window
		
		return $this->db->count_all_results('service_gate_exceptions') > 0;
	}

	/**
	 * Get active exception details
	 * 
	 * @param string $module Module code
	 * @param string $source_ref Reference ID
	 * @return object Exception record or null
	 */
	public function get_active_exception($module, $source_ref)
	{
		if (!$this->db->table_exists('service_gate_exceptions')) {
			return null;
		}

		$this->db->where('module', $module);
		$this->db->where('source_ref', $source_ref);
		$this->db->where('status', 'ACTIVE');
		$this->db->where('created_at >=', date('Y-m-d H:i:s', strtotime('-24 hours')));
		$this->db->order_by('created_at', 'DESC');
		
		return $this->db->get('service_gate_exceptions')->row();
	}

	/* ================================================================== */
	/*  MODULE-SPECIFIC HELPERS                                           */
	/* ================================================================== */

	/**
	 * Check laboratory test gate
	 * Convenience method for laboratory module
	 * 
	 * @param int $lab_request_id Lab request ID
	 * @param string $iop_id Visit ID
	 * @return array Gate check result
	 */
	public function check_lab_gate($lab_request_id, $iop_id = null)
	{
		return $this->check_service('LAB', (string)$lab_request_id, $iop_id);
	}

	/**
	 * Check pharmacy dispense gate
	 * Convenience method for pharmacy module
	 * 
	 * @param int $medication_id Medication/prescription ID
	 * @param string $iop_id Visit ID
	 * @param string $patient_no Patient number
	 * @return array Gate check result
	 */
	public function check_pharmacy_gate($medication_id, $iop_id = null, $patient_no = null)
	{
		return $this->check_service('PHARMACY', (string)$medication_id, $iop_id, $patient_no);
	}

	/**
	 * Check sonography gate
	 * Convenience method for sonography module
	 * 
	 * @param int $sono_request_id Sonography request ID
	 * @param string $iop_id Visit ID
	 * @return array Gate check result
	 */
	public function check_sonography_gate($sono_request_id, $iop_id = null)
	{
		return $this->check_service('SONOGRAPHY', (string)$sono_request_id, $iop_id);
	}

	/**
	 * Check radiology gate
	 * Convenience method for radiology module
	 * 
	 * @param int $rad_request_id Radiology request ID
	 * @param string $iop_id Visit ID
	 * @return array Gate check result
	 */
	public function check_radiology_gate($rad_request_id, $iop_id = null)
	{
		return $this->check_service('RADIOLOGY', (string)$rad_request_id, $iop_id);
	}

	/**
	 * Convenience: IPD room charge gate (advisory only — meant for discharge
	 * dashboards / outstanding awareness, not for blocking room services).
	 */
	public function check_room_gate($charge_id, $iop_id = null)
	{
		return $this->check_service('IPD_ROOM', (string)$charge_id, $iop_id);
	}

	/**
	 * Aggregate IPD outstanding for a visit using SSOT billing_transactions.
	 * Returns array(charges, billed, paid, balance).
	 */
	public function get_visit_room_outstanding($iop_id)
	{
		$result = array('charges' => 0, 'billed' => 0.0, 'paid' => 0.0, 'balance' => 0.0);
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '' || !$this->db->table_exists('billing_transactions')) {
			return $result;
		}
		$this->db->select('COUNT(*) as charges, COALESCE(SUM(net_amount),0) as billed, COALESCE(SUM(paid_amount),0) as paid, COALESCE(SUM(balance_amount),0) as balance', false);
		$this->db->from('billing_transactions');
		$this->db->where(array(
			'encounter_id' => $iop_id,
			'department' => 'IPD',
			'item_type' => 'ROOM',
			'InActive' => 0
		));
		$row = $this->db->get()->row();
		if ($row) {
			$result['charges'] = (int)$row->charges;
			$result['billed'] = (float)$row->billed;
			$result['paid'] = (float)$row->paid;
			$result['balance'] = (float)$row->balance;
		}
		return $result;
	}

	/* ================================================================== */
	/*  BATCH OPERATIONS                                                  */
	/* ================================================================== */

	/**
	 * Check gate status for multiple items
	 * Used when loading worklists to show payment status
	 * 
	 * @param string $module Module code
	 * @param array $source_refs Array of reference IDs
	 * @return array Map of source_ref => gate_result
	 */
	public function check_multiple($module, array $source_refs)
	{
		if (empty($source_refs)) return array();

		$results = array();
		foreach ($source_refs as $ref) {
			$results[$ref] = $this->can_proceed($module, $ref);
		}

		return $results;
	}

	/**
	 * Get all pending (blocked) items for a visit
	 * Useful for showing "pending payments" on patient view
	 * 
	 * @param string $iop_id Visit ID
	 * @return array Blocked billing queue items
	 */
	public function get_pending_for_visit($iop_id)
	{
		if (!$this->db->table_exists('billing_queue')) {
			return array();
		}

		$this->db->where('iop_id', $iop_id);
		$this->db->where('status', 'PENDING');
		$this->db->where('InActive', 0);
		
		if ($this->db->field_exists('service_gate_status', 'billing_queue')) {
			$this->db->where('service_gate_status', 'BLOCKED');
		}

		return $this->db->get('billing_queue')->result();
	}

	/* ================================================================== */
	/*  SCHEMA MANAGEMENT                                                 */
	/* ================================================================== */

	/**
	 * Ensure exception log table exists
	 */
	private function _ensure_exception_table()
	{
		if (!$this->db->table_exists('service_gate_exceptions')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS service_gate_exceptions (
					exception_id INT AUTO_INCREMENT PRIMARY KEY,
					module VARCHAR(50) NOT NULL,
					source_ref VARCHAR(50) NOT NULL,
					patient_no VARCHAR(25) DEFAULT NULL,
					iop_id VARCHAR(25) DEFAULT NULL,
					exception_type ENUM('EMERGENCY','WAIVER','NHIS','INSURANCE','STAFF','DEFERRED') NOT NULL,
					reason TEXT NOT NULL,
					authorized_by VARCHAR(25) NOT NULL,
					status ENUM('ACTIVE','EXPIRED','REVOKED') DEFAULT 'ACTIVE',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					expires_at DATETIME DEFAULT NULL,
					InActive INT(1) NOT NULL DEFAULT 0,
					INDEX idx_sge_module_ref (module, source_ref),
					INDEX idx_sge_patient (patient_no),
					INDEX idx_sge_status (status)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			return;
		}

		if (!$this->db->field_exists('InActive', 'service_gate_exceptions')) {
			$this->db->query("ALTER TABLE service_gate_exceptions ADD COLUMN InActive INT(1) NOT NULL DEFAULT 0");
		}
	}

	/**
	 * Check unified billing gate using billing_master and billing_items tables
	 * 
	 * @param string $module Module code
	 * @param string $source_ref Source reference ID
	 * @return array|null Gate result or null if no record found
	 */
	private function _check_unified_billing_gate($module, $source_ref)
	{
		// Check if billing_items table exists
		if (!$this->db->table_exists('billing_items')) {
			return null;
		}
		
		// Map module to source_module/service_type used in billing_items
		$module_map = array(
			'LAB' => 'LABORATORY',
			'LABORATORY' => 'LABORATORY',
			'PHARMACY' => 'PHARMACY',
			'SONOGRAPHY' => 'SONOGRAPHY',
			'RADIOLOGY' => 'RADIOLOGY',
			'PROCEDURE' => 'PROCEDURE'
		);
		
		$service_type = isset($module_map[strtoupper($module)]) ? $module_map[strtoupper($module)] : strtoupper($module);
		
		// Look for billing item
		$this->db->select('bi.*, bm.bill_no, bm.payment_status AS bill_status, bm.balance_due');
		$this->db->from('billing_items bi');
		$this->db->join('billing_master bm', 'bm.bill_id = bi.bill_id', 'left');
		$this->db->where('bi.service_type', $service_type);
		$this->db->where('bi.source_ref_id', $source_ref);
		$this->db->where('bi.InActive', 0);
		$this->db->limit(1);
		
		$item = $this->db->get()->row();
		
		if (!$item) {
			// No billing record found - might be pre-unified or not yet billed
			return null;
		}
		
		// Check gate_status on billing_items
		$gate_status = isset($item->gate_status) ? strtoupper($item->gate_status) : 'BLOCKED';
		
		// Determine if allowed based on gate_status and payment_status
		$allowed = false;
		$reason = '';
		
		if ($gate_status === 'RELEASED') {
			$allowed = true;
			$reason = 'Payment verified - proceed';
		} elseif ($gate_status === 'WAIVED') {
			$allowed = true;
			$reason = 'Payment waived by admin';
		} elseif ($gate_status === 'BLOCKED') {
			// Check if bill is paid
			$bill_status = isset($item->bill_status) ? strtoupper($item->bill_status) : 'PENDING';
			if ($bill_status === 'PAID') {
				$allowed = true;
				$reason = 'Bill fully paid';
			} elseif ($bill_status === 'PARTIAL') {
				// For partial, check if this item was paid
				$allowed = false;
				$reason = 'Partial payment - item not yet released';
			} else {
				$allowed = false;
				$reason = 'Payment required (Status: ' . $bill_status . ')';
			}
		}
		
		return array(
			'allowed' => $allowed,
			'status' => $allowed ? self::GATE_RELEASED : self::GATE_BLOCKED,
			'reason' => $reason,
			'queue_id' => isset($item->item_id) ? $item->item_id : null,
			'invoice_no' => isset($item->bill_no) ? $item->bill_no : null,
			'bill_id' => isset($item->bill_id) ? $item->bill_id : null
		);
	}

	/**
	 * Get gate statistics for dashboard
	 * 
	 * @return array Statistics
	 */
	public function get_gate_statistics()
	{
		if (!$this->db->table_exists('billing_queue')) {
			return array(
				'blocked_count' => 0,
				'released_count' => 0,
				'expired_count' => 0,
				'total_pending_amount' => 0
			);
		}

		$stats = array();

		// Blocked items
		$this->db->where('status', 'PENDING');
		$this->db->where('InActive', 0);
		if ($this->db->field_exists('service_gate_status', 'billing_queue')) {
			$this->db->where('service_gate_status', 'BLOCKED');
		}
		$stats['blocked_count'] = $this->db->count_all_results('billing_queue');

		// Released items (today)
		$this->db->where('status', 'BILLED');
		$this->db->where('InActive', 0);
		if ($this->db->field_exists('service_gate_status', 'billing_queue')) {
			$this->db->where('service_gate_status', 'RELEASED');
			$this->db->where('DATE(released_at)', date('Y-m-d'));
		} else {
			$this->db->where('DATE(billed_at)', date('Y-m-d'));
		}
		$stats['released_count'] = $this->db->count_all_results('billing_queue');

		// Total pending amount
		$this->db->select('COALESCE(SUM(net_amount), 0) as total');
		$this->db->where('status', 'PENDING');
		$this->db->where('InActive', 0);
		$stats['total_pending_amount'] = (float)$this->db->get('billing_queue')->row()->total;

		return $stats;
	}

	private function _drift_block_critical_enabled()
	{
		$default = null;
		if (isset($this->config)) {
			$default = $this->config->item('drift_block_critical');
			if ($default === null) {
				$default = $this->config->item('DRIFT_BLOCK_CRITICAL');
			}
		}

		$cfg = null;
		$env = getenv('drift_block_critical');
		if ($env === false) { $env = getenv('DRIFT_BLOCK_CRITICAL'); }
		if ($env !== false) { $cfg = $env; }

		if ($cfg === null && isset($this->db) && $this->db->table_exists('system_option') && $this->db->field_exists('cCode', 'system_option') && $this->db->field_exists('cValue', 'system_option')) {
			$row = $this->db->get_where('system_option', array('cCode' => 'drift_block_critical', 'InActive' => 0), 1)->row();
			if (!$row) {
				$row = $this->db->get_where('system_option', array('cCode' => 'DRIFT_BLOCK_CRITICAL', 'InActive' => 0), 1)->row();
			}
			if ($row && isset($row->cValue)) {
				$cfg = $row->cValue;
			}
		}

		if ($cfg === null) {
			$cfg = $default;
		}

		if (is_bool($cfg)) {
			return $cfg;
		}
		if ($cfg === null) {
			return false;
		}
		if (is_numeric($cfg)) {
			return ((int)$cfg) !== 0;
		}
		$val = filter_var($cfg, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		return ($val === null) ? false : (bool)$val;
	}
}
