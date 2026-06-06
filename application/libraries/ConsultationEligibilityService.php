<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class ConsultationEligibilityService
{
	const STATUS_BLOCKED = 'BLOCKED';
	const STATUS_PARTIALLY_PAID = 'PARTIALLY_PAID';
	const STATUS_VERIFIED = 'VERIFIED';
	const STATUS_EXEMPT = 'EXEMPT';
	const STATUS_OVERRIDE_APPROVED = 'OVERRIDE_APPROVED';
	const STATUS_UNKNOWN = 'UNKNOWN';

	protected $CI;

	public function __construct()
	{
		$this->CI = &get_instance();
	}

	public function evaluate_visit_consultation_gate($iop_id)
	{
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') {
			return array('ok' => false, 'error' => 'Missing iop_id');
		}

		$visit = $this->get_visit_row($iop_id);
		if (!$visit) {
			return array('ok' => false, 'error' => 'Visit not found');
		}

		$patient_no = isset($visit->patient_no) ? trim((string)$visit->patient_no) : '';
		$visit_date = isset($visit->date_visit) && trim((string)$visit->date_visit) !== ''
			? date('Y-m-d', strtotime((string)$visit->date_visit))
			: date('Y-m-d');

		$override = $this->get_active_override($iop_id);
		if ($override) {
			$payload = array(
				'ok' => true,
				'iop_id' => $iop_id,
				'patient_no' => $patient_no,
				'status' => self::STATUS_OVERRIDE_APPROVED,
				'can_start' => true,
				'blocking_reasons' => array(),
				'outstanding_balance' => 0.0,
				'fees' => array(),
				'exemptions' => array(),
				'payment_url' => base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id),
				'override' => $override,
				'computed_at' => date('Y-m-d H:i:s'),
			);
			$payload['ui'] = $this->build_ui_payload_from_gate($payload);
			$this->write_cache_snapshot($payload, null, 'OVERRIDE');
			return $payload;
		}

		$this->CI->load->model('app/visit_billing_resolver_model', 'visit_billing_resolver');
		$dec = $this->CI->visit_billing_resolver->preview_visit_fee_decisions($patient_no, $iop_id, $visit_date);
		if (!is_array($dec) || empty($dec['ok'])) {
			return array('ok' => false, 'error' => 'Unable to resolve visit fee decisions');
		}

		$regDec = isset($dec['registration']) && is_array($dec['registration']) ? $dec['registration'] : array();
		$conDec = isset($dec['consultation']) && is_array($dec['consultation']) ? $dec['consultation'] : array();

		$reg = $this->resolve_fee_gate_item($iop_id, $patient_no, 'REGISTRATION', $regDec);
		$con = $this->resolve_fee_gate_item($iop_id, $patient_no, 'CONSULTATION', $conDec);

		$fees = array('registration' => $reg, 'consultation' => $con);

		$blocking = array();
		$outstanding = 0.0;
		foreach ($fees as $k => $f) {
			if (!empty($f['required']) && !empty($f['blocked'])) {
				if (!empty($f['reason'])) {
					$blocking[] = (string)$f['reason'];
				}
				$outstanding += isset($f['balance']) ? (float)$f['balance'] : 0.0;
			}
		}
		$outstanding = round((float)$outstanding, 2);

		$consult_exempt = !empty($con['exempt']);
		$has_required = (!empty($reg['required']) || !empty($con['required']));

		$status = self::STATUS_UNKNOWN;
		$can_start = false;
		if (!empty($blocking)) {
			$status = self::STATUS_BLOCKED;
			foreach ($fees as $f) {
				if (!empty($f['required']) && isset($f['paid']) && (float)$f['paid'] > 0.009 && isset($f['balance']) && (float)$f['balance'] > 0.009) {
					$status = self::STATUS_PARTIALLY_PAID;
					break;
				}
			}
			$can_start = false;
		} else {
			if ($consult_exempt) {
				$status = self::STATUS_EXEMPT;
				$can_start = true;
			} elseif ($has_required) {
				$status = self::STATUS_VERIFIED;
				$can_start = true;
			} else {
				$status = self::STATUS_EXEMPT;
				$can_start = true;
			}
		}

		$exemptions = array();
		if (!empty($reg['exempt']) && !empty($reg['reason'])) {
			$exemptions[] = array('type' => 'REGISTRATION', 'reason' => (string)$reg['reason']);
		}
		if (!empty($con['exempt']) && !empty($con['reason'])) {
			$exemptions[] = array('type' => 'CONSULTATION', 'reason' => (string)$con['reason']);
		}

		$payload = array(
			'ok' => true,
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'status' => $status,
			'can_start' => $can_start,
			'blocking_reasons' => $blocking,
			'outstanding_balance' => $outstanding,
			'fees' => $fees,
			'exemptions' => $exemptions,
			'payment_url' => base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id),
			'override' => null,
			'visit_date' => $visit_date,
			'payer_type' => isset($dec['payer_type']) ? (string)$dec['payer_type'] : null,
			'computed_at' => date('Y-m-d H:i:s'),
		);
		$payload['ui'] = $this->build_ui_payload_from_gate($payload);
		$this->write_cache_snapshot($payload, null, 'EVAL');
		return $payload;
	}

	public function can_start_consultation($iop_id)
	{
		$gate = $this->evaluate_visit_consultation_gate($iop_id);
		return (!empty($gate['ok']) && !empty($gate['can_start']));
	}

	public function get_gate_ui_payload($iop_id, $opts = array())
	{
		$iop_id = trim((string)$iop_id);
		$opts = is_array($opts) ? $opts : array();
		$allowRecompute = array_key_exists('allow_recompute', $opts) ? (bool)$opts['allow_recompute'] : true;
		if ($iop_id === '') {
			return array(
				'ok' => false,
				'status' => self::STATUS_UNKNOWN,
				'badge_class' => 'label-default',
				'label' => 'Gate Unknown',
				'tooltip' => 'Missing visit ID',
				'action_url' => base_url() . 'app/pos/pos_visit',
				'outstanding_balance' => null,
				'can_start' => false,
				'computed_at' => null,
			);
		}

		$cache = null;
		try {
			$this->CI->load->model('app/opd_consultation_gate_cache_model', 'opd_gate_cache');
			$cache = $this->CI->opd_gate_cache->get_cache($iop_id);
		} catch (Throwable $e) {
			$cache = null;
		}

		$maxAge = 180;
		if ($cache && isset($cache->computed_at) && trim((string)$cache->computed_at) !== '') {
			$age = time() - strtotime((string)$cache->computed_at);
			if ($age >= 0 && $age <= $maxAge) {
				return array(
					'ok' => true,
					'status' => isset($cache->status) ? (string)$cache->status : self::STATUS_UNKNOWN,
					'badge_class' => isset($cache->badge_class) ? (string)$cache->badge_class : 'label-default',
					'label' => isset($cache->label) ? (string)$cache->label : 'Gate',
					'tooltip' => isset($cache->tooltip) ? (string)$cache->tooltip : '',
					'action_url' => isset($cache->action_url) ? (string)$cache->action_url : (base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id)),
					'outstanding_balance' => isset($cache->outstanding_balance) ? (float)$cache->outstanding_balance : null,
					'can_start' => isset($cache->can_start) ? ((int)$cache->can_start === 1) : false,
					'computed_at' => (string)$cache->computed_at,
				);
			}
		}

		if (!$allowRecompute) {
			return array(
				'ok' => true,
				'status' => $cache && isset($cache->status) ? (string)$cache->status : self::STATUS_UNKNOWN,
				'badge_class' => $cache && isset($cache->badge_class) ? (string)$cache->badge_class : 'label-default',
				'label' => $cache && isset($cache->label) ? (string)$cache->label : 'Gate',
				'tooltip' => $cache && isset($cache->tooltip) ? (string)$cache->tooltip : '',
				'action_url' => $cache && isset($cache->action_url) && trim((string)$cache->action_url) !== ''
					? (string)$cache->action_url
					: (base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id)),
				'outstanding_balance' => $cache && isset($cache->outstanding_balance) ? (float)$cache->outstanding_balance : null,
				'can_start' => $cache && isset($cache->can_start) ? ((int)$cache->can_start === 1) : false,
				'computed_at' => $cache && isset($cache->computed_at) ? (string)$cache->computed_at : null,
			);
		}

		$gate = $this->evaluate_visit_consultation_gate($iop_id);
		if (empty($gate['ok'])) {
			return array(
				'ok' => false,
				'status' => self::STATUS_UNKNOWN,
				'badge_class' => 'label-default',
				'label' => 'Gate Unknown',
				'tooltip' => isset($gate['error']) ? (string)$gate['error'] : 'Unknown',
				'action_url' => base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id),
				'outstanding_balance' => null,
				'can_start' => false,
				'computed_at' => null,
			);
		}
		return $this->build_ui_payload_from_gate($gate);
	}

	protected function build_ui_payload_from_gate($gate)
	{
		$st = isset($gate['status']) ? (string)$gate['status'] : self::STATUS_UNKNOWN;
		$cls = 'label-default';
		$label = $st;
		$tooltip = '';

		if ($st === self::STATUS_VERIFIED) {
			$cls = 'label-success';
			$label = 'Payment Verified';
		} elseif ($st === self::STATUS_EXEMPT) {
			$cls = 'label-info';
			$label = 'Payment Exempt';
		} elseif ($st === self::STATUS_PARTIALLY_PAID) {
			$cls = 'label-warning';
			$label = 'Partially Paid';
		} elseif ($st === self::STATUS_BLOCKED) {
			$cls = 'label-danger';
			$label = 'Payment Required';
		} elseif ($st === self::STATUS_OVERRIDE_APPROVED) {
			$cls = 'label-primary';
			$label = 'Override Approved';
		}

		$reasons = isset($gate['blocking_reasons']) && is_array($gate['blocking_reasons']) ? $gate['blocking_reasons'] : array();
		if (!empty($reasons)) {
			$tooltip = implode(' | ', array_map('trim', $reasons));
		}
		if ($tooltip === '' && isset($gate['exemptions']) && is_array($gate['exemptions']) && !empty($gate['exemptions'])) {
			$parts = array();
			foreach ($gate['exemptions'] as $ex) {
				$parts[] = isset($ex['reason']) ? (string)$ex['reason'] : '';
			}
			$parts = array_values(array_filter($parts, function ($v) { return trim((string)$v) !== ''; }));
			$tooltip = !empty($parts) ? implode(' | ', $parts) : '';
		}

		$iop_id = isset($gate['iop_id']) ? (string)$gate['iop_id'] : '';
		$outstanding = isset($gate['outstanding_balance']) ? (float)$gate['outstanding_balance'] : null;
		if (($st === self::STATUS_BLOCKED || $st === self::STATUS_PARTIALLY_PAID) && $outstanding !== null && $outstanding > 0.009) {
			$seg = 'Outstanding: ' . number_format($outstanding, 2);
			$tooltip = $tooltip !== '' ? ($tooltip . ' | ' . $seg) : $seg;
		}
		if ($st === self::STATUS_OVERRIDE_APPROVED) {
			$seg = 'Emergency override approved';
			$tooltip = $tooltip !== '' ? ($tooltip . ' | ' . $seg) : $seg;
		}
		return array(
			'ok' => true,
			'status' => $st,
			'badge_class' => $cls,
			'label' => $label,
			'tooltip' => $tooltip,
			'action_url' => isset($gate['payment_url']) ? (string)$gate['payment_url'] : (base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id)),
			'outstanding_balance' => isset($gate['outstanding_balance']) ? (float)$gate['outstanding_balance'] : null,
			'can_start' => !empty($gate['can_start']),
			'computed_at' => isset($gate['computed_at']) ? (string)$gate['computed_at'] : null,
		);
	}

	protected function get_visit_row($iop_id)
	{
		$this->CI->db->where('IO_ID', (string)$iop_id);
		$this->CI->db->where('InActive', 0);
		$this->CI->db->limit(1);
		return $this->CI->db->get('patient_details_iop')->row();
	}

	protected function resolve_fee_gate_item($iop_id, $patient_no, $type, $decision)
	{
		$type = strtoupper(trim((string)$type));
		$decision_type = isset($decision['decision_type']) ? (string)$decision['decision_type'] : $type;
		$dec = isset($decision['decision']) ? strtoupper(trim((string)$decision['decision'])) : '';
		$enabled = array_key_exists('enabled', $decision) ? (bool)$decision['enabled'] : true;
		$reason = isset($decision['reason']) ? (string)$decision['reason'] : null;
		$amount = isset($decision['amount']) ? (float)$decision['amount'] : 0.0;

		$item_ref = ($type === 'REGISTRATION') ? ('visit_registration:' . $iop_id) : ('visit_consultation:' . $iop_id);

		$required = ($enabled && $dec === 'APPLY');
		$exempt = (!$required);
		if (!$enabled && $dec === 'SKIP') {
			$exempt = true;
		}
		$blocked = false;
		$txn_state = array('found' => false, 'net' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'payment_status' => null, 'txn_ids' => array());

		if ($required) {
			$txn_state = $this->get_fee_txn_state($iop_id, $item_ref);
			if (empty($txn_state['found'])) {
				$blocked = true;
				$reason = $reason ? $reason : ($decision_type . ' not billed');
			} else {
				$bal = isset($txn_state['balance']) ? (float)$txn_state['balance'] : 0.0;
				if ($bal > 0.009) {
					$blocked = true;
					if ($reason === null || $reason === '') {
						$reason = $decision_type . ' unpaid';
					}
				}
			}
		}

		return array(
			'type' => $type,
			'decision_type' => $decision_type,
			'decision' => $dec,
			'enabled' => $enabled,
			'required' => $required,
			'exempt' => $exempt,
			'reason' => $reason,
			'amount' => round((float)$amount, 2),
			'item_ref' => $item_ref,
			'found_txn' => !empty($txn_state['found']),
			'net' => round((float)$txn_state['net'], 2),
			'paid' => round((float)$txn_state['paid'], 2),
			'balance' => round((float)$txn_state['balance'], 2),
			'payment_status' => isset($txn_state['payment_status']) ? $txn_state['payment_status'] : null,
			'txn_ids' => isset($txn_state['txn_ids']) ? $txn_state['txn_ids'] : array(),
			'blocked' => $blocked,
		);
	}

	protected function get_fee_txn_state($iop_id, $item_ref)
	{
		$this->CI->db->select('txn_id, net_amount, paid_amount, balance_amount, payment_status');
		$this->CI->db->from('billing_transactions');
		$this->CI->db->where('InActive', 0);
		$this->CI->db->where('encounter_id', (string)$iop_id);
		$this->CI->db->where('department', 'OPD');
		$this->CI->db->where('item_ref', (string)$item_ref);
		$rows = $this->CI->db->get()->result();

		if (empty($rows)) {
			return array('found' => false, 'net' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'payment_status' => null, 'txn_ids' => array());
		}

		$net = 0.0;
		$paid = 0.0;
		$bal = 0.0;
		$st = null;
		$ids = array();
		foreach ($rows as $r) {
			$ids[] = isset($r->txn_id) ? (int)$r->txn_id : 0;
			$net += isset($r->net_amount) ? (float)$r->net_amount : 0.0;
			$paid += isset($r->paid_amount) ? (float)$r->paid_amount : 0.0;
			$bal += isset($r->balance_amount) ? (float)$r->balance_amount : 0.0;
			if ($st === null && isset($r->payment_status)) {
				$st = strtoupper(trim((string)$r->payment_status));
			}
		}
		return array('found' => true, 'net' => $net, 'paid' => $paid, 'balance' => $bal, 'payment_status' => $st, 'txn_ids' => $ids);
	}

	protected function get_active_override($iop_id)
	{
		$this->CI->load->model('app/opd_consultation_gate_cache_model', 'opd_gate_cache');
		return $this->CI->opd_gate_cache->get_active_override($iop_id);
	}

	protected function write_cache_snapshot($payload, $actor_user_id = null, $trigger = null)
	{
		try {
			$this->CI->load->model('app/opd_consultation_gate_cache_model', 'opd_gate_cache');
			$this->CI->opd_gate_cache->upsert_snapshot($payload, $actor_user_id, $trigger);
		} catch (Throwable $e) {
		}
	}
}
