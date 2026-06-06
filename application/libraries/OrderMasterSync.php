<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class OrderMasterSync
{
	protected $CI;
	protected $cool_off_seconds = 10;
	protected static $field_change_cache = array();
	protected $clinical_order = array('REQUESTED', 'IN_PROGRESS', 'COMPLETED');
	protected $financial_order = array('PENDING', 'BILLED', 'PAID');
	protected $payment_order = array('UNPAID', 'NO_BILL_REQUIRED', 'PAID');
	protected static $seen_anomaly_fingerprints = array();

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->database();
		$this->CI->load->model('app/Order_master_model');
		$this->CI->load->library('OrderStateMachine');
		$this->CI->load->helper('rbac');
	}

	private function new_trace_id()
	{
		return uniqid('ssot_', true);
	}

	private function normalize_last_sync_at($v)
	{
		$v = (string)$v;
		if ($v === '') return null;
		$ts = strtotime($v);
		if ($ts === false) return null;
		return date('Y-m-d H:i:s', $ts);
	}

	private function allow_event_override($existing, $incoming)
	{
		if (!is_array($existing) || empty($existing)) return true;
		$old_ts = isset($existing['last_sync_at']) ? $this->normalize_last_sync_at($existing['last_sync_at']) : null;
		$new_ts = isset($incoming['last_sync_at']) ? $this->normalize_last_sync_at($incoming['last_sync_at']) : null;
		if ($new_ts === null) return true;
		if ($old_ts === null) return true;
		if ($new_ts > $old_ts) return true;
		if ($new_ts < $old_ts) return false;

		$old_v = isset($existing['event_version']) ? (float)$existing['event_version'] : 0.0;
		$new_v = isset($incoming['event_version']) ? (float)$incoming['event_version'] : 0.0;
		return ($new_v > $old_v);
	}

	private function field_changed_recently($module, $source_id, $patient_no, $visit_id, $field, $seconds)
	{
		$seconds = (int)$seconds;
		if ($seconds <= 0) return false;
		$key = strtoupper(trim((string)$module)).'|'.(int)$source_id.'|'.(string)$patient_no.'|'.(string)$visit_id.'|'.(string)$field;
		$now = microtime(true);
		if (isset(self::$field_change_cache[$key]) && ($now - (float)self::$field_change_cache[$key]) < $seconds) {
			return true;
		}
		self::$field_change_cache[$key] = $now;
		return false;
	}

	private function is_cli_env()
	{
		if (function_exists('is_cli')) {
			return is_cli();
		}
		return (php_sapi_name() === 'cli');
	}

	private function current_user_is_admin()
	{
		if (function_exists('is_admin_role')) {
			return is_admin_role();
		}
		if (function_exists('has_role')) {
			return has_role('admin');
		}
		return false;
	}

	private function force_allowed()
	{
		return ($this->is_cli_env() || $this->current_user_is_admin());
	}

	private function normalize_key($v)
	{
		return strtoupper(trim((string)$v));
	}

	private function is_idempotent_noop($existing, $final_row)
	{
		if (!$existing || !is_array($existing) || !$final_row || !is_array($final_row)) return false;
		$old_c = isset($existing['clinical_status']) ? $this->normalize_key($existing['clinical_status']) : '';
		$old_f = isset($existing['financial_status']) ? $this->normalize_key($existing['financial_status']) : '';
		$old_p = isset($existing['payment_status']) ? $this->normalize_key($existing['payment_status']) : '';

		$new_c = isset($final_row['clinical_status']) ? $this->normalize_key($final_row['clinical_status']) : $old_c;
		$new_f = isset($final_row['financial_status']) ? $this->normalize_key($final_row['financial_status']) : $old_f;
		$new_p = isset($final_row['payment_status']) ? $this->normalize_key($final_row['payment_status']) : $old_p;

		return ($old_c === $new_c && $old_f === $new_f && $old_p === $new_p);
	}

	private function compute_audit_diff($existing, $final_row)
	{
		$diff = array();
		if (!$existing || !is_array($existing) || !$final_row || !is_array($final_row)) return $diff;
		$keys = array('status', 'clinical_status', 'financial_status', 'payment_status');
		foreach ($keys as $k) {
			$old = isset($existing[$k]) ? (string)$existing[$k] : '';
			$new = isset($final_row[$k]) ? (string)$final_row[$k] : '';
			if ($old !== $new && $new !== '') {
				$diff[$k] = array('from' => $old, 'to' => $new);
			}
		}
		return $diff;
	}

	private function _rank($state, $order)
	{
		$state = strtoupper(trim((string)$state));
		$idx = array_search($state, $order, true);
		return ($idx === false) ? null : (int)$idx;
	}

	private function is_forward_transition($old, $new, $order)
	{
		$old_rank = $this->_rank($old, $order);
		$new_rank = $this->_rank($new, $order);
		if ($old_rank === null || $new_rank === null) {
			return true;
		}
		return $new_rank >= $old_rank;
	}

	private function get_existing_row($module, $source_id, $patient_no, $visit_id)
	{
		if (!$this->CI->db->table_exists('order_master')) return null;
		$module = strtoupper(trim((string)$module));
		$source_id = (int)$source_id;
		if ($source_id > 0 && $module !== '') {
			$q = $this->CI->db->get_where('order_master', array(
				'module' => $module,
				'source_id' => $source_id,
				'InActive' => 0
			), 1);
			return $q ? $q->row_array() : null;
		}
		if ((string)$patient_no !== '' && (string)$visit_id !== '') {
			$q = $this->CI->db->get_where('order_master', array(
				'patient_no' => (string)$patient_no,
				'visit_id' => (string)$visit_id,
				'InActive' => 0
			), 1);
			return $q ? $q->row_array() : null;
		}
		return null;
	}

	public function derive_global_status($clinical_status, $payment_status)
	{
		static $cache = array();
		$clinical_in = (string)$clinical_status;
		$payment_in = (string)$payment_status;
		$key = $clinical_in.'|'.$payment_in;
		if (isset($cache[$key])) {
			return $cache[$key];
		}
		$clinical = $this->CI->orderstatemachine->normalize($clinical_in);
		$payment = strtoupper(trim($payment_in));
		if ($clinical !== 'COMPLETED') {
			$cache[$key] = $clinical;
			return $clinical;
		}
		if ($payment !== 'PAID') {
			$cache[$key] = 'AWAITING_PAYMENT';
			return 'AWAITING_PAYMENT';
		}
		$cache[$key] = 'COMPLETED';
		return 'COMPLETED';
	}

	private function should_skip_due_to_timestamp($existing, $incoming_last_sync_at, $force)
	{
		if ($force) return false;
		if (!$existing || !is_array($existing)) return false;
		$existing_ts = isset($existing['last_sync_at']) ? strtotime((string)$existing['last_sync_at']) : false;
		$incoming_ts = $incoming_last_sync_at ? strtotime((string)$incoming_last_sync_at) : false;
		if (!$existing_ts || !$incoming_ts) return false;
		return $incoming_ts < $existing_ts;
	}

	private function log_anomalies($final_row, $trace_id = null)
	{
		if (!is_array($final_row)) return;
		$clinical = isset($final_row['clinical_status']) ? strtoupper(trim((string)$final_row['clinical_status'])) : '';
		$financial = isset($final_row['financial_status']) ? strtoupper(trim((string)$final_row['financial_status'])) : '';
		$payment = isset($final_row['payment_status']) ? strtoupper(trim((string)$final_row['payment_status'])) : '';
		$global = $this->derive_global_status($clinical, $payment);

		$final_row['global_status'] = $global;

		$fingerprint = md5(
			(string)(isset($final_row['patient_no']) ? $final_row['patient_no'] : '').
			(string)(isset($final_row['visit_id']) ? $final_row['visit_id'] : '').
			(string)$clinical.
			(string)$financial.
			(string)$payment
		);
		$now = time();
		if (isset(self::$seen_anomaly_fingerprints[$fingerprint]) && ($now - (int)self::$seen_anomaly_fingerprints[$fingerprint]) < 600) {
			return;
		}
		self::$seen_anomaly_fingerprints[$fingerprint] = $now;

		$should_log = true;
		if (isset($this->CI) && isset($this->CI->db) && is_object($this->CI->db)) {
			if ($this->CI->db->table_exists('order_reconciliation_log')) {
				$cutoff = date('Y-m-d H:i:s', $now - 600);
				$this->CI->db->where('issue_type', 'SSOT_ANOMALY');
				$this->CI->db->where('detected_at >=', $cutoff);
				$this->CI->db->like('resolution_note', '"fp":"'.$fingerprint.'"');
				$seen = $this->CI->db->get('order_reconciliation_log', 1)->row();
				if ($seen) {
					$should_log = false;
				} else {
					$this->CI->db->insert('order_reconciliation_log', array(
						'patient_no' => isset($final_row['patient_no']) ? (string)$final_row['patient_no'] : null,
						'visit_id' => isset($final_row['visit_id']) ? (string)$final_row['visit_id'] : null,
						'issue_type' => 'SSOT_ANOMALY',
						'severity' => 'LOW',
						'clinical_status' => $clinical,
						'financial_status' => $financial,
						'payment_status' => $payment,
						'resolved' => 1,
						'resolution_note' => json_encode(array('fp' => $fingerprint, 'global_status' => $global, 'trace_id' => $trace_id))
					));
				}
			}
		}
		if (!$should_log) {
			return;
		}

		if ($clinical === 'COMPLETED' && $payment === 'UNPAID' && ($financial === 'PENDING' || $financial === 'BILLED')) {
			log_message('warning', 'SSOT anomaly detected trace_id='.(string)$trace_id.' fp='.$fingerprint.' ' . json_encode($final_row));
			return;
		}
		if ($payment === 'PAID' && $financial !== 'PAID') {
			log_message('warning', 'SSOT anomaly detected trace_id='.(string)$trace_id.' fp='.$fingerprint.' ' . json_encode($final_row));
			return;
		}
	}

	private function guard_regressions($existing, $payload, $force)
	{
		if ($force) return array('ok' => true, 'reason' => 'forced');
		if (!$existing || !is_array($existing)) return array('ok' => true, 'reason' => 'no-existing');

		if (isset($payload['clinical_status'])) {
			$old = isset($existing['clinical_status']) ? $existing['clinical_status'] : (isset($existing['status']) ? $existing['status'] : '');
			$new = $payload['clinical_status'];
			if (!$this->is_forward_transition($old, $new, $this->clinical_order)) {
				return array('ok' => false, 'reason' => 'clinical-regression');
			}
		}
		if (isset($payload['status'])) {
			$old = isset($existing['status']) ? $existing['status'] : '';
			$new = $payload['status'];
			if (!$this->is_forward_transition($old, $new, $this->clinical_order)) {
				return array('ok' => false, 'reason' => 'status-regression');
			}
		}
		if (isset($payload['financial_status'])) {
			$old = isset($existing['financial_status']) ? $existing['financial_status'] : '';
			$new = $payload['financial_status'];
			if (!$this->is_forward_transition($old, $new, $this->financial_order)) {
				return array('ok' => false, 'reason' => 'financial-regression');
			}
		}
		if (isset($payload['payment_status'])) {
			$old = isset($existing['payment_status']) ? $existing['payment_status'] : '';
			$new = $payload['payment_status'];
			if (!$this->is_forward_transition($old, $new, $this->payment_order)) {
				return array('ok' => false, 'reason' => 'payment-regression');
			}
		}

		return array('ok' => true, 'reason' => 'ok');
	}

	public function sync_lab_order($order, $force = false)
	{
		if (!is_array($order)) return false;
		$force = (bool)$force;
		if ($force && !$this->force_allowed()) {
			log_message('error', 'Unauthorized SSOT force attempt (lab_order)');
			return false;
		}
		$trace_id = (isset($order['trace_id']) && trim((string)$order['trace_id']) !== '')
			? (string)$order['trace_id']
			: $this->new_trace_id();
		$is_backfill = isset($order['is_backfill']) && $order['is_backfill'] ? true : false;
		$status = isset($order['status']) ? (string)$order['status'] : 'REQUESTED';
		$normalized_status = $this->CI->orderstatemachine->normalize($status);
		$now = date('Y-m-d H:i:s');
		$last_sync_at = isset($order['last_sync_at']) ? (string)$order['last_sync_at'] : $now;
		$meta = array('clinical_source' => 'LAB', 'financial_source' => 'BILLING', 'payment_source' => 'PAYMENT');

		$payload = array(
			'patient_no' => isset($order['patient_no']) ? (string)$order['patient_no'] : '',
			'visit_id' => isset($order['iop_id']) ? (string)$order['iop_id'] : (isset($order['visit_id']) ? (string)$order['visit_id'] : ''),
			'status' => $normalized_status,
			'clinical_status' => $normalized_status,
			'source_module' => isset($order['source_module']) ? (string)$order['source_module'] : 'LAB',
			'module' => isset($order['module']) ? (string)$order['module'] : (isset($order['source_module']) ? (string)$order['source_module'] : 'LAB'),
			'source_id' => isset($order['source_id']) ? (int)$order['source_id'] : 0,
			'item_id' => isset($order['item_id']) ? (int)$order['item_id'] : null,
			'last_sync_at' => $last_sync_at,
			'event_version' => isset($order['_event_version']) ? (float)$order['_event_version'] : (float)microtime(true),
			'updated_at' => $now,
		);

		$existing = $this->get_existing_row($payload['module'], $payload['source_id'], $payload['patient_no'], $payload['visit_id']);
		if (!$force && !$this->allow_event_override($existing, $payload)) {
			return false;
		}
		$defer_noise = false;
		if (!$force && isset($existing['clinical_status']) && (string)$existing['clinical_status'] !== (string)$payload['clinical_status']) {
			if ($this->field_changed_recently($payload['module'], $payload['source_id'], $payload['patient_no'], $payload['visit_id'], 'clinical_status', 3)) {
				$defer_noise = true;
			}
		}
		$final_row = $existing ? array_merge($existing, $payload) : $payload;
		if ($this->is_idempotent_noop($existing, $final_row)) {
			return true;
		}
		if ($is_backfill && $existing && isset($existing['last_sync_at']) && (string)$existing['last_sync_at'] !== '' && !$force) {
			return false;
		}
		if ($this->should_skip_due_to_timestamp($existing, $payload['last_sync_at'], $force)) {
			return false;
		}
		$guard = $this->guard_regressions($existing, $payload, $force);
		if (!$guard['ok']) {
			log_message('warning', 'SSOT regression blocked (lab_order): ' . json_encode(array('reason' => $guard['reason'], 'payload' => $payload, 'existing' => $existing)));
			return false;
		}
		if (!$defer_noise) {
			$this->log_anomalies($final_row, $trace_id);
		}
		$diff = $this->compute_audit_diff($existing, $final_row);
		if (!empty($diff)) {
			log_message('info', 'SSOT update: ' . json_encode(array(
				'patient_no' => $payload['patient_no'],
				'visit_id' => $payload['visit_id'],
				'module' => $payload['module'],
				'source_id' => $payload['source_id'],
				'trace_id' => $trace_id,
				'meta' => $meta,
				'changes' => $diff
			)));
		}

		$ok = $this->CI->Order_master_model->upsert($payload);
		if ($ok && !$defer_noise) {
			$this->CI->load->library('OrderReconciliationEngine');
			$latest = null;
			if (method_exists($this->CI->Order_master_model, 'get_by_module_source') && (int)$payload['source_id'] > 0) {
				$latest = $this->CI->Order_master_model->get_by_module_source($payload['module'], $payload['source_id']);
			}
			if (!$latest) {
				$latest = $this->CI->Order_master_model->get($payload['patient_no'], $payload['visit_id']);
			}
			$issues = $this->CI->orderreconciliationengine->analyze($latest);
			if (empty($issues)) {
				$this->CI->orderreconciliationengine->auto_resolve_normalized($latest, $trace_id);
			} else {
				for ($i = 0; $i < count($issues); $i++) {
					if (is_array($issues[$i])) {
						$issues[$i]['_meta_sources'] = $meta;
						$issues[$i]['_trace_id'] = $trace_id;
					}
				}
				$this->CI->orderreconciliationengine->log_issues($issues);
				$this->CI->orderreconciliationengine->attempt_repair($latest);
			}
		}
		return $ok;
	}

	public function sync_billing($order, $financial_state, $force = false)
	{
		if (!is_array($order)) return false;
		$force = (bool)$force;
		if ($force && !$this->force_allowed()) {
			log_message('error', 'Unauthorized SSOT force attempt (billing)');
			return false;
		}
		$trace_id = (isset($order['trace_id']) && trim((string)$order['trace_id']) !== '')
			? (string)$order['trace_id']
			: $this->new_trace_id();
		$is_backfill = isset($order['is_backfill']) && $order['is_backfill'] ? true : false;
		$now = date('Y-m-d H:i:s');
		$last_sync_at = isset($order['last_sync_at']) ? (string)$order['last_sync_at'] : $now;
		$meta = array('clinical_source' => 'LAB', 'financial_source' => 'BILLING', 'payment_source' => 'PAYMENT');
		$payload = array(
			'patient_no' => isset($order['patient_no']) ? (string)$order['patient_no'] : '',
			'visit_id' => isset($order['iop_id']) ? (string)$order['iop_id'] : (isset($order['visit_id']) ? (string)$order['visit_id'] : ''),
			'financial_status' => (string)$financial_state,
			'source_module' => isset($order['source_module']) ? (string)$order['source_module'] : 'LAB',
			'module' => isset($order['module']) ? (string)$order['module'] : (isset($order['source_module']) ? (string)$order['source_module'] : 'LAB'),
			'source_id' => isset($order['source_id']) ? (int)$order['source_id'] : 0,
			'item_id' => isset($order['item_id']) ? (int)$order['item_id'] : null,
			'last_sync_at' => $last_sync_at,
			'event_version' => isset($order['_event_version']) ? (float)$order['_event_version'] : (float)microtime(true),
			'updated_at' => $now,
		);

		$existing = $this->get_existing_row($payload['module'], $payload['source_id'], $payload['patient_no'], $payload['visit_id']);
		if (!$force && !$this->allow_event_override($existing, $payload)) {
			return false;
		}
		$defer_noise = false;
		if (!$force && isset($existing['financial_status']) && (string)$existing['financial_status'] !== (string)$payload['financial_status']) {
			if ($this->field_changed_recently($payload['module'], $payload['source_id'], $payload['patient_no'], $payload['visit_id'], 'financial_status', 3)) {
				$defer_noise = true;
			}
		}
		$final_row = $existing ? array_merge($existing, $payload) : $payload;
		if ($this->is_idempotent_noop($existing, $final_row)) {
			return true;
		}
		if ($is_backfill && $existing && isset($existing['last_sync_at']) && (string)$existing['last_sync_at'] !== '' && !$force) {
			return false;
		}
		if ($this->should_skip_due_to_timestamp($existing, $payload['last_sync_at'], $force)) {
			return false;
		}
		$guard = $this->guard_regressions($existing, $payload, $force);
		if (!$guard['ok']) {
			log_message('warning', 'SSOT regression blocked (billing): ' . json_encode(array('reason' => $guard['reason'], 'payload' => $payload, 'existing' => $existing)));
			return false;
		}
		if (!$defer_noise) {
			$this->log_anomalies($final_row, $trace_id);
		}
		$diff = $this->compute_audit_diff($existing, $final_row);
		if (!empty($diff)) {
			log_message('info', 'SSOT update: ' . json_encode(array(
				'patient_no' => $payload['patient_no'],
				'visit_id' => $payload['visit_id'],
				'module' => $payload['module'],
				'source_id' => $payload['source_id'],
				'trace_id' => $trace_id,
				'meta' => $meta,
				'changes' => $diff
			)));
		}

		$ok = $this->CI->Order_master_model->upsert($payload);
		if ($ok && !$defer_noise) {
			$this->CI->load->library('OrderReconciliationEngine');
			$latest = null;
			if (method_exists($this->CI->Order_master_model, 'get_by_module_source') && (int)$payload['source_id'] > 0) {
				$latest = $this->CI->Order_master_model->get_by_module_source($payload['module'], $payload['source_id']);
			}
			if (!$latest) {
				$latest = $this->CI->Order_master_model->get($payload['patient_no'], $payload['visit_id']);
			}
			$issues = $this->CI->orderreconciliationengine->analyze($latest);
			if (empty($issues)) {
				$this->CI->orderreconciliationengine->auto_resolve_normalized($latest, $trace_id);
			} else {
				for ($i = 0; $i < count($issues); $i++) {
					if (is_array($issues[$i])) {
						$issues[$i]['_meta_sources'] = $meta;
						$issues[$i]['_trace_id'] = $trace_id;
					}
				}
				$this->CI->orderreconciliationengine->log_issues($issues);
				$this->CI->orderreconciliationengine->attempt_repair($latest);
			}
		}
		return $ok;
	}

	public function sync_payment($order, $payment_state, $force = false)
	{
		if (!is_array($order)) return false;
		$force = (bool)$force;
		if ($force && !$this->force_allowed()) {
			log_message('error', 'Unauthorized SSOT force attempt (payment)');
			return false;
		}
		$trace_id = (isset($order['trace_id']) && trim((string)$order['trace_id']) !== '')
			? (string)$order['trace_id']
			: $this->new_trace_id();
		$is_backfill = isset($order['is_backfill']) && $order['is_backfill'] ? true : false;
		$now = date('Y-m-d H:i:s');
		$last_sync_at = isset($order['last_sync_at']) ? (string)$order['last_sync_at'] : $now;
		$meta = array('clinical_source' => 'LAB', 'financial_source' => 'BILLING', 'payment_source' => 'PAYMENT');
		$payload = array(
			'patient_no' => isset($order['patient_no']) ? (string)$order['patient_no'] : '',
			'visit_id' => isset($order['iop_id']) ? (string)$order['iop_id'] : (isset($order['visit_id']) ? (string)$order['visit_id'] : ''),
			'payment_status' => (string)$payment_state,
			'source_module' => isset($order['source_module']) ? (string)$order['source_module'] : 'LAB',
			'module' => isset($order['module']) ? (string)$order['module'] : (isset($order['source_module']) ? (string)$order['source_module'] : 'LAB'),
			'source_id' => isset($order['source_id']) ? (int)$order['source_id'] : 0,
			'item_id' => isset($order['item_id']) ? (int)$order['item_id'] : null,
			'last_sync_at' => $last_sync_at,
			'event_version' => isset($order['_event_version']) ? (float)$order['_event_version'] : (float)microtime(true),
			'updated_at' => $now,
		);

		$existing = $this->get_existing_row($payload['module'], $payload['source_id'], $payload['patient_no'], $payload['visit_id']);
		if (!$force && !$this->allow_event_override($existing, $payload)) {
			return false;
		}
		$defer_noise = false;
		if (!$force && isset($existing['payment_status']) && (string)$existing['payment_status'] !== (string)$payload['payment_status']) {
			if ($this->field_changed_recently($payload['module'], $payload['source_id'], $payload['patient_no'], $payload['visit_id'], 'payment_status', 3)) {
				$defer_noise = true;
			}
		}

		$payment_up = strtoupper(trim((string)$payload['payment_status']));
		if ($payment_up === 'PAID') {
			$payload['financial_status'] = 'PAID';
		}
		$final_row = $existing ? array_merge($existing, $payload) : $payload;
		if ($this->is_idempotent_noop($existing, $final_row)) {
			return true;
		}
		if ($is_backfill && $existing && isset($existing['last_sync_at']) && (string)$existing['last_sync_at'] !== '' && !$force) {
			return false;
		}
		if ($this->should_skip_due_to_timestamp($existing, $payload['last_sync_at'], $force)) {
			return false;
		}
		$guard = $this->guard_regressions($existing, $payload, $force);
		if (!$guard['ok']) {
			log_message('warning', 'SSOT regression blocked (payment): ' . json_encode(array('reason' => $guard['reason'], 'payload' => $payload, 'existing' => $existing)));
			return false;
		}

		if (!$defer_noise) {
			$this->log_anomalies($final_row, $trace_id);
		}
		$diff = $this->compute_audit_diff($existing, $final_row);
		if (!empty($diff)) {
			log_message('info', 'SSOT update: ' . json_encode(array(
				'patient_no' => $payload['patient_no'],
				'visit_id' => $payload['visit_id'],
				'module' => $payload['module'],
				'source_id' => $payload['source_id'],
				'trace_id' => $trace_id,
				'meta' => $meta,
				'changes' => $diff
			)));
		}
		$ok = $this->CI->Order_master_model->upsert($payload);
		if ($ok && !$defer_noise) {
			$this->CI->load->library('OrderReconciliationEngine');
			$latest = null;
			if (method_exists($this->CI->Order_master_model, 'get_by_module_source') && (int)$payload['source_id'] > 0) {
				$latest = $this->CI->Order_master_model->get_by_module_source($payload['module'], $payload['source_id']);
			}
			if (!$latest) {
				$latest = $this->CI->Order_master_model->get($payload['patient_no'], $payload['visit_id']);
			}
			$issues = $this->CI->orderreconciliationengine->analyze($latest);
			if (empty($issues)) {
				$this->CI->orderreconciliationengine->auto_resolve_normalized($latest, $trace_id);
			} else {
				for ($i = 0; $i < count($issues); $i++) {
					if (is_array($issues[$i])) {
						$issues[$i]['_meta_sources'] = $meta;
						$issues[$i]['_trace_id'] = $trace_id;
					}
				}
				$this->CI->orderreconciliationengine->log_issues($issues);
				$this->CI->orderreconciliationengine->attempt_repair($latest);
			}
		}
		return $ok;
	}
}
