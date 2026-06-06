<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Visit_billing_resolver_model extends CI_Model
{
	const DECISION_APPLY = 'APPLY';
	const DECISION_WAIVE = 'WAIVE';
	const DECISION_SKIP = 'SKIP';
	const DECISION_ERROR = 'ERROR';

	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/smart_billing_model');
		$this->load->model('app/billing_model');
		$this->load->model('app/Price_engine_model', 'price_engine');
		$this->load->model('app/patient_review_authorization_model');
		$this->load->model('app/visit_billing_decision_audit_model');
	}

	public function preview_visit_fee_decisions($patient_no, $iop_id = null, $visit_date = null)
	{
		$patient_no = trim((string)$patient_no);
		$iop_id = $iop_id !== null ? trim((string)$iop_id) : null;
		$visit_date = $visit_date ? date('Y-m-d', strtotime((string)$visit_date)) : date('Y-m-d');
		if ($patient_no === '') {
			return array('ok' => false, 'error' => 'Missing patient_no');
		}

		$payer_type = 'CASH';
		$CI = get_instance();
		if (isset($CI->billing_model) && is_object($CI->billing_model) && method_exists($CI->billing_model, 'determine_payer_type')) {
			$payer_type = strtoupper(trim((string)$CI->billing_model->determine_payer_type($patient_no)));
		}
		if ($payer_type === '') { $payer_type = 'CASH'; }

		$visit_info = array(
			'visit_type' => 'WALK_IN',
			'appointment_id' => null,
			'consultation_waived' => false,
			'waiver_reason' => null,
		);
		if ($iop_id !== null && $iop_id !== '' && isset($CI->smart_billing_model) && is_object($CI->smart_billing_model) && method_exists($CI->smart_billing_model, 'detect_visit_type')) {
			$detected = $CI->smart_billing_model->detect_visit_type($patient_no, $iop_id, $visit_date);
			if (is_array($detected) && !empty($detected['visit_type'])) {
				$visit_info = array_merge($visit_info, $detected);
			}
		}
		$visit_type = strtoupper(trim((string)$visit_info['visit_type']));
		if ($visit_type === '') { $visit_type = 'WALK_IN'; }

		$cfg_auto = $this->_cfg_bool('auto_bill_visit_fees', true);
		$cfg_reg_enabled = $this->_cfg_bool('enable_registration_fee', true);
		$cfg_cons_enabled = $this->_cfg_bool('enable_consultation_fee', true);

		$registration = array(
			'decision_type' => 'REGISTRATION_FEE',
			'enabled' => $cfg_auto && $cfg_reg_enabled,
			'decision' => self::DECISION_SKIP,
			'reason' => null,
			'matched_rule' => null,
			'amount' => 0.0,
			'item_id' => null,
			'item_name' => null,
		);
		$consultation = array(
			'decision_type' => 'CONSULTATION_FEE',
			'enabled' => $cfg_auto && $cfg_cons_enabled,
			'decision' => self::DECISION_SKIP,
			'reason' => null,
			'matched_rule' => null,
			'amount' => 0.0,
			'item_id' => null,
			'item_name' => null,
			'waived_by_review_authorization' => false,
			'review_authorization_id' => null,
		);

		if (!$cfg_auto) {
			$registration['reason'] = 'Auto-billing disabled by configuration';
			$consultation['reason'] = 'Auto-billing disabled by configuration';
			return array('ok' => true, 'payer_type' => $payer_type, 'visit_date' => $visit_date, 'visit_info' => $visit_info, 'registration' => $registration, 'consultation' => $consultation);
		}

		if (!$cfg_reg_enabled) {
			$registration['reason'] = 'Registration fee disabled by configuration';
			$registration['matched_rule'] = 'CFG_DISABLE_REGISTRATION';
		} else {
			if ($visit_type === 'FIRST_VISIT') {
				// GHS: First-time patients ALWAYS pay registration fee
				$registration['decision'] = self::DECISION_APPLY;
				$registration['reason'] = 'First-time patient - registration fee applies';
				$registration['matched_rule'] = 'FIRST_VISIT_REGISTRATION_FEE';
				$price = $this->_resolve_fee_price('registration', $patient_no, $payer_type);
				if ($price['ok']) {
					$registration['amount'] = (float)$price['amount'];
					$registration['item_id'] = $price['item_id'];
					$registration['item_name'] = $price['item_name'];
					if ((float)$price['amount'] <= 0.009) {
						$registration['decision'] = self::DECISION_SKIP;
						$registration['reason'] = 'Registration fee configured as free for ' . $payer_type;
						$registration['matched_rule'] = 'CONFIGURED_FREE_REGISTRATION';
					}
				} else {
					$registration['decision'] = self::DECISION_ERROR;
					$registration['reason'] = $price['error'];
					$registration['matched_rule'] = 'PRICE_RESOLUTION_FAILED';
				}
			} else {
				$registration['decision'] = self::DECISION_SKIP;
				$registration['reason'] = 'Returning patient - registration is a one-time fee';
				$registration['matched_rule'] = 'RETURNING_PATIENT';
			}
		}

		if (!$cfg_cons_enabled) {
			$consultation['reason'] = 'Consultation fee disabled by configuration';
			$consultation['matched_rule'] = 'CFG_DISABLE_CONSULTATION';
		} else {
			if ($payer_type === 'NHIS') {
				$consultation['decision']     = self::DECISION_WAIVE;
				$consultation['reason']       = 'Consultation is free for NHIS patients';
				$consultation['matched_rule'] = 'NHIS_FREE_CONSULTATION';
			} else {
				$auth = $this->patient_review_authorization_model->get_active_authorization_for_date($patient_no, $visit_date);
				if ($visit_type === 'FIRST_VISIT') {
					// GHS: First-time patients ALWAYS pay consultation fee
					$consultation['decision'] = self::DECISION_APPLY;
					$consultation['reason'] = 'First-time patient - consultation fee applies';
					$consultation['matched_rule'] = 'FIRST_VISIT_CONSULTATION_FEE';
					$price = $this->_resolve_fee_price('consultation', $patient_no, $payer_type);
					if ($price['ok']) {
						$consultation['amount'] = (float)$price['amount'];
						$consultation['item_id'] = $price['item_id'];
						$consultation['item_name'] = $price['item_name'];
						if ((float)$price['amount'] <= 0.009) {
							$consultation['decision'] = self::DECISION_SKIP;
							$consultation['reason'] = 'Consultation fee configured as free for ' . $payer_type;
							$consultation['matched_rule'] = 'CONFIGURED_FREE_CONSULTATION';
						}
					} else {
						$consultation['decision'] = self::DECISION_ERROR;
						$consultation['reason'] = $price['error'];
						$consultation['matched_rule'] = 'PRICE_RESOLUTION_FAILED';
					}
				} elseif (!empty($visit_info['consultation_waived'])) {
					// REVIEW or doctor-authorized FOLLOW_UP → waive consultation
					$consultation['decision'] = self::DECISION_WAIVE;
					$consultation['reason'] = !empty($visit_info['waiver_reason']) ? (string)$visit_info['waiver_reason'] : 'Consultation waived by visit rule';
					if ($visit_type === 'FOLLOW_UP') {
						$consultation['matched_rule'] = 'FOLLOW_UP_WINDOW';
					} else {
						$consultation['matched_rule'] = 'REVIEW_VISIT';
					}
					if ($auth && isset($auth->id)) {
						$consultation['waived_by_review_authorization'] = true;
						$consultation['review_authorization_id'] = (int)$auth->id;
						$consultation['matched_rule'] = 'REVIEW_AUTH_ACTIVE';
					}
				} else {
					// WALK_IN or any non-waived visit → ALWAYS bill consultation
					$consultation['decision'] = self::DECISION_APPLY;
					$consultation['reason'] = 'Consultation fee applies - standard visit charge';
					$consultation['matched_rule'] = ($visit_type === 'WALK_IN') ? 'WALK_IN_CONSULTATION_FEE' : 'NO_REVIEW_AUTH';
					$price = $this->_resolve_fee_price('consultation', $patient_no, $payer_type);
					if ($price['ok']) {
						$consultation['amount'] = (float)$price['amount'];
						$consultation['item_id'] = $price['item_id'];
						$consultation['item_name'] = $price['item_name'];
						if ((float)$price['amount'] <= 0.009) {
							$consultation['decision'] = self::DECISION_SKIP;
							$consultation['reason'] = 'Consultation fee configured as free for ' . $payer_type;
							$consultation['matched_rule'] = 'CONFIGURED_FREE_CONSULTATION';
						}
					} else {
						$consultation['decision'] = self::DECISION_ERROR;
						$consultation['reason'] = $price['error'];
						$consultation['matched_rule'] = 'PRICE_RESOLUTION_FAILED';
					}
				}
			}
		}

		return array('ok' => true, 'payer_type' => $payer_type, 'visit_date' => $visit_date, 'visit_info' => $visit_info, 'registration' => $registration, 'consultation' => $consultation);
	}

	public function auto_bill_visit_fees($iop_id, $patient_no, $actor_user_id)
	{
		$this->load->model('app/billing_transaction_model');
		$this->load->model('app/billing_disposition_model');
		log_message('error', 'ENTER auto_bill_visit_fees: iop_id=' . (string)$iop_id . ' patient_no=' . (string)$patient_no . ' actor_user_id=' . (string)$actor_user_id);

		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		$actor_user_id = $actor_user_id !== null ? trim((string)$actor_user_id) : null;
		if ($iop_id === '' || $patient_no === '') {
			return array('ok' => false, 'error' => 'Missing iop_id or patient_no');
		}

		$cid = 'visit_auto_bill:' . $iop_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
		$preview = $this->preview_visit_fee_decisions($patient_no, $iop_id, $this->_get_visit_date($iop_id));
		if (!is_array($preview) || empty($preview['ok'])) {
			return array('ok' => false, 'error' => 'Decision preview failed');
		}

		$out = array('ok' => true, 'correlation_id' => $cid, 'created' => array(), 'skipped' => array(), 'errors' => array(), 'decisions' => $preview);
		$applyTypes = array();
		$satisfiedTypes = array();

		foreach (array('registration', 'consultation') as $k) {
			$dec = isset($preview[$k]) ? $preview[$k] : null;
			if (!is_array($dec)) continue;
			$type = isset($dec['decision_type']) ? (string)$dec['decision_type'] : strtoupper($k);
			$this->visit_billing_decision_audit_model->log_decision($iop_id, $patient_no, $type, (string)$dec['decision'], $dec['reason'], $dec['matched_rule'], array(
				'amount' => isset($dec['amount']) ? (float)$dec['amount'] : 0.0,
				'item_id' => isset($dec['item_id']) ? $dec['item_id'] : null,
			), $actor_user_id, $cid);

			if (!$dec['enabled']) {
				$out['skipped'][] = array('type' => $type, 'reason' => 'Disabled');
				continue;
			}
			if ($dec['decision'] === self::DECISION_WAIVE) {
				$item_ref = ($k === 'registration') ? ('visit_registration:' . $iop_id) : ('visit_consultation:' . $iop_id);
				$this->_cancel_pending_visit_fee_transaction($iop_id, $patient_no, $item_ref, $dec['reason'], $actor_user_id);
				$out['skipped'][] = array('type' => $type, 'reason' => $dec['reason']);
				if ($k === 'consultation' && !empty($dec['review_authorization_id'])) {
					$this->patient_review_authorization_model->mark_used((int)$dec['review_authorization_id']);
				}
				continue;
			}
			if ($dec['decision'] !== self::DECISION_APPLY) {
				$item_ref = ($k === 'registration') ? ('visit_registration:' . $iop_id) : ('visit_consultation:' . $iop_id);
				$this->_cancel_pending_visit_fee_transaction($iop_id, $patient_no, $item_ref, $dec['reason'], $actor_user_id);
				$out['skipped'][] = array('type' => $type, 'reason' => $dec['reason']);
				continue;
			}

			$item_id = isset($dec['item_id']) ? (int)$dec['item_id'] : 0;
			$amount = isset($dec['amount']) ? (float)$dec['amount'] : 0.0;
			$item_name = isset($dec['item_name']) ? (string)$dec['item_name'] : '';
			$applyTypes[] = $type;
			if ($amount <= 0.009) {
				$out['errors'][] = $type . ': amount is zero';
				continue;
			}
			$item_ref = ($k === 'registration') ? ('visit_registration:' . $iop_id) : ('visit_consultation:' . $iop_id);
			if ($this->_txn_exists_for_item_ref($iop_id, $patient_no, $item_ref)) {
				$this->_sync_existing_visit_fee_transaction($iop_id, $patient_no, $item_ref, $item_id, $item_name, $amount, isset($preview['payer_type']) ? $preview['payer_type'] : 'CASH', $actor_user_id);
				$out['skipped'][] = array('type' => $type, 'reason' => 'Already billed');
				$satisfiedTypes[] = $type;
				continue;
			}

			$tx = $this->billing_transaction_model->create_transaction(array(
				'patient_no' => $patient_no,
				'encounter_id' => $iop_id,
				'encounter_type' => 'OPD',
				'department' => 'OPD',
				'item_type' => 'SERVICE',
				'item_id' => $item_id > 0 ? $item_id : null,
				'item_ref' => $item_ref,
				'item_name' => $item_name !== '' ? $item_name : $type,
				'quantity' => 1,
				'unit_price' => $amount,
				'payer_type' => isset($preview['payer_type']) ? $preview['payer_type'] : 'CASH',
				'notes' => $dec['reason'],
			), $actor_user_id);
			if (!is_array($tx) || empty($tx['ok'])) {
				$errMsg = (is_array($tx) && isset($tx['error'])) ? (string)$tx['error'] : 'billing transaction create failed';
				$out['errors'][] = $type . ': ' . $errMsg;
				log_message('error', 'Visit fee auto-bill txn create failed: iop_id=' . $iop_id . ' patient_no=' . $patient_no . ' item_ref=' . $item_ref . ' type=' . $type . ' error=' . $errMsg);
				continue;
			}
			$txn_id = (int)$tx['txn_id'];
			$out['created'][] = array('type' => $type, 'txn_id' => $txn_id);
			$satisfiedTypes[] = $type;
			$CI = get_instance();
			if (isset($CI->billing_disposition_model) && is_object($CI->billing_disposition_model) && method_exists($CI->billing_disposition_model, 'bootstrap_if_missing')) {
				$CI->billing_disposition_model->bootstrap_if_missing($txn_id, $actor_user_id, 'OPD_VISIT_FEE_AUTO_BILL', $item_ref, $cid);
			}
		}

		// If any APPLY decisions were present but we could not either create
		// a transaction or confirm an existing one (Already billed), treat
		// the operation as failed so callers can abort higher-level flows.
		if (!empty($applyTypes)) {
			$unsatisfied = array_diff($applyTypes, $satisfiedTypes);
			if (!empty($unsatisfied)) {
				$out['ok'] = false;
				if (!isset($out['error']) || $out['error'] === null || $out['error'] === '') {
					$out['error'] = 'Visit fee transactions missing for: ' . implode(', ', $unsatisfied);
				}
			}
		}

		return $out;
	}

	private function _txn_exists_for_item_ref($iop_id, $patient_no, $item_ref)
	{
		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		$item_ref = trim((string)$item_ref);
		if ($item_ref === '') return false;
		if (!$this->_table_exists('billing_transactions')) return false;
		$this->db->select('txn_id');
		$this->db->where('InActive', 0);
		$this->db->where('encounter_id', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->where('item_ref', $item_ref);
		$this->db->limit(1);
		$r = $this->db->get('billing_transactions')->row();
		return ($r && isset($r->txn_id));
	}

	private function _cfg_bool($key, $default)
	{
		$key = trim((string)$key);
		$default = $default ? '1' : '0';
		$val = null;
		$CI = get_instance();
		$hasSb = isset($CI->smart_billing_model) && is_object($CI->smart_billing_model);
		$hasGet = $hasSb && method_exists($CI->smart_billing_model, 'get_config');
		if ($hasGet) {
			$val = $CI->smart_billing_model->get_config($key, $default);
		}
		$raw = (string)$val;
		$norm = strtoupper(trim($raw));
		$out = ($norm === 'YES' || $norm === 'TRUE') ? true : (((int)$norm) === 1);
		if ($key === 'auto_bill_visit_fees' || $key === 'enable_registration_fee' || $key === 'enable_consultation_fee') {
			log_message('error', 'CFG_BOOL: key=' . $key . ' default=' . $default . ' hasSb=' . ($hasSb ? '1' : '0') . ' hasGet=' . ($hasGet ? '1' : '0') . ' sbClass=' . ($hasSb ? get_class($CI->smart_billing_model) : '-') . ' raw=' . $raw . ' norm=' . $norm . ' out=' . ($out ? '1' : '0'));
		}
		return $out;
	}

	private function _resolve_fee_price($fee_type, $patient_no, $payer_type)
	{
		$fee_type = strtolower(trim((string)$fee_type));
		$key_item = $fee_type === 'registration' ? 'registration_fee_item_id' : 'consultation_fee_item_id';
		$item_id = (int)$this->smart_billing_model->get_config($key_item, '0');
		$key_amt = null;
		if ($fee_type === 'registration') {
			$key_amt = (strtoupper($payer_type) === 'NHIS') ? 'registration_fee_nhis' : 'registration_fee_cash';
		} else {
			$key_amt = (strtoupper($payer_type) === 'NHIS') ? 'consultation_fee_nhis' : 'consultation_fee_cash';
		}
		$amount = (float)$this->smart_billing_model->get_config($key_amt, '0');
		$item_name = null;

		if ($item_id > 0) {
			$pr = $this->price_engine->resolve(array(
				'item_type' => 'SERVICE',
				'item_id' => $item_id,
				'quantity' => 1,
				'patient_no' => $patient_no,
				'payer_type' => $payer_type,
			));
			if (is_array($pr) && !empty($pr['ok'])) {
				$item_name = isset($pr['item_name']) ? (string)$pr['item_name'] : null;
			}
		}

		return array(
			'ok' => true,
			'amount' => $amount,
			'item_id' => $item_id > 0 ? $item_id : null,
			'item_name' => $item_name,
			'pricing_source' => 'smart_billing_config.' . $key_amt,
		);
	}

	private function _sync_existing_visit_fee_transaction($iop_id, $patient_no, $item_ref, $item_id, $item_name, $amount, $payer_type, $user_id = null)
	{
		if (!$this->_table_exists('billing_transactions')) return false;
		$this->db->where('InActive', 0);
		$this->db->where('encounter_id', (string)$iop_id);
		$this->db->where('patient_no', (string)$patient_no);
		$this->db->where('item_ref', (string)$item_ref);
		$this->db->where("(invoice_no IS NULL OR invoice_no = '')", null, false);
		$this->db->where("UPPER(COALESCE(payment_status,'')) NOT IN ('CANCELLED','PAID')", null, false);
		$this->db->limit(1);
		$row = $this->db->get('billing_transactions')->row();
		if (!$row || !isset($row->txn_id)) return false;

		$qty = isset($row->quantity) ? (float)$row->quantity : 1.0;
		if ($qty <= 0) { $qty = 1.0; }
		$unit = round((float)$amount, 2);
		$gross = round($unit * $qty, 2);
		$upd = array(
			'item_id' => $item_id > 0 ? (int)$item_id : null,
			'item_name' => trim((string)$item_name) !== '' ? (string)$item_name : (string)$row->item_name,
			'unit_price' => $unit,
			'gross_amount' => $gross,
			'net_amount' => $gross,
			'balance_amount' => $gross,
			'payer_type' => strtoupper(trim((string)$payer_type)) !== '' ? strtoupper(trim((string)$payer_type)) : 'CASH',
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $user_id,
		);
		if ($this->db->field_exists('total_amount', 'billing_transactions')) {
			$upd['total_amount'] = $gross;
		}
		$this->db->where('txn_id', (int)$row->txn_id);
		$this->db->update('billing_transactions', $upd);

		if ($this->_table_exists('billing_queue')) {
			$qUpd = array(
				'item_id' => $item_id > 0 ? (string)(int)$item_id : (isset($row->item_id) ? (string)$row->item_id : null),
				'item_name' => trim((string)$item_name) !== '' ? (string)$item_name : (string)$row->item_name,
				'unit_price' => $unit,
				'total_amount' => $gross,
				'payer_type' => strtoupper(trim((string)$payer_type)) !== '' ? strtoupper(trim((string)$payer_type)) : 'CASH',
			);
			$this->db->where('InActive', 0);
			$this->db->where('iop_id', (string)$iop_id);
			$this->db->where('patient_no', (string)$patient_no);
			$this->db->where('source_ref', (string)$item_ref);
			$this->db->where('status', 'PENDING');
			$this->db->update('billing_queue', $qUpd);
		}
		return true;
	}

	private function _cancel_pending_visit_fee_transaction($iop_id, $patient_no, $item_ref, $reason, $user_id = null)
	{
		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		$item_ref = trim((string)$item_ref);
		if ($iop_id === '' || $patient_no === '' || $item_ref === '') return false;
		$now = date('Y-m-d H:i:s');
		$notes = trim((string)$reason);
		if ($this->_table_exists('billing_transactions')) {
			$upd = array(
				'payment_status' => 'CANCELLED',
				'order_status' => 'CANCELLED',
				'InActive' => 1,
				'updated_at' => $now,
				'updated_by' => $user_id,
			);
			if ($this->db->field_exists('notes', 'billing_transactions')) {
				$upd['notes'] = $notes !== '' ? $notes : 'Visit fee not billable';
			}
			$this->db->where('InActive', 0);
			$this->db->where('encounter_id', $iop_id);
			$this->db->where('patient_no', $patient_no);
			$this->db->where('item_ref', $item_ref);
			$this->db->where("(invoice_no IS NULL OR invoice_no = '')", null, false);
			$this->db->where("UPPER(COALESCE(payment_status,'')) NOT IN ('PAID','CANCELLED')", null, false);
			$this->db->update('billing_transactions', $upd);
		}
		if ($this->_table_exists('billing_queue')) {
			$this->db->where('InActive', 0);
			$this->db->where('iop_id', $iop_id);
			$this->db->where('patient_no', $patient_no);
			$this->db->where('source_ref', $item_ref);
			$this->db->where('status', 'PENDING');
			$this->db->update('billing_queue', array('status' => 'CANCELLED', 'InActive' => 1));
		}
		return true;
	}

	private function _patient_requires_registration_fee($patient_no, $current_iop_id = null)
	{
		$patient_no = trim((string)$patient_no);
		$current_iop_id = $current_iop_id !== null ? trim((string)$current_iop_id) : null;
		if ($patient_no === '') {
			return array('ok' => false, 'error' => 'Missing patient_no');
		}
		if (!$this->_table_exists('patient_details_iop')) {
			return array('ok' => false, 'error' => 'Missing patient_details_iop');
		}

		// Prefer workflow engine completion when available.
		if ($this->_table_exists('iop_opd_workflow')) {
			$sql = "SELECT V.IO_ID
				FROM patient_details_iop V
				INNER JOIN iop_opd_workflow W ON W.iop_id = V.IO_ID
				WHERE V.patient_no = ? AND V.InActive = 0 AND W.InActive = 0
				  AND UPPER(TRIM(W.status)) IN ('FINAL_CLEARED','COMPLETED','CLINICALLY_CLEARED')";
			$params = array($patient_no);
			if ($current_iop_id !== null && $current_iop_id !== '') {
				$sql .= " AND V.IO_ID <> ?";
				$params[] = $current_iop_id;
			}
			$sql .= " LIMIT 1";
			$q = $this->db->query($sql, $params);
			if ($q && $q->num_rows() > 0) {
				return array('ok' => true, 'requires' => false);
			}
		}

		// Fallback: legacy nStatus check.
		$this->db->select('IO_ID');
		$this->db->where('patient_no', $patient_no);
		$this->db->where('InActive', 0);
		if ($current_iop_id !== null && $current_iop_id !== '') {
			$this->db->where('IO_ID <>', $current_iop_id);
		}
		if ($this->db->field_exists('nStatus', 'patient_details_iop')) {
			$this->db->where("(nStatus IS NOT NULL AND TRIM(nStatus) <> '' AND UPPER(TRIM(nStatus)) NOT IN ('PENDING','ACTIVE'))", null, false);
		}
		$this->db->limit(1);
		$q2 = $this->db->get('patient_details_iop');
		if ($q2 && $q2->num_rows() > 0) {
			return array('ok' => true, 'requires' => false);
		}
		return array('ok' => true, 'requires' => true);
	}

	private function _table_exists($table)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$table));
		return ($q && $q->num_rows() > 0);
	}

	private function _get_visit_date($iop_id)
	{
		if (!$this->_table_exists('patient_details_iop')) {
			return date('Y-m-d');
		}
		$this->db->select('date_visit');
		$this->db->where('IO_ID', (string)$iop_id);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$row = $this->db->get('patient_details_iop')->row();
		if ($row && isset($row->date_visit) && trim((string)$row->date_visit) !== '') {
			return date('Y-m-d', strtotime((string)$row->date_visit));
		}
		return date('Y-m-d');
	}
}
