<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Unified Billing Model
 * 
 * Single Source of Truth for all billing operations in HMS.
 * Consolidates: billing_model, cashier_model, smart_billing_model, service_billing_model
 * 
 * Core Tables:
 * - iop_billing (invoice headers) - EXISTING
 * - iop_billing_t (invoice items) - EXISTING  
 * - iop_receipt (payments) - EXISTING
 * - billing_queue (pending billable items) - NEW
 * - financial_ledger (double-entry accounting) - NEW
 * - chart_of_accounts (account definitions) - NEW
 * 
 * @author HMS Refactoring Team
 * @version 2.0
 */
class Unified_billing_model extends CI_Model
{
	private $_table_exists_cache = array();
	private $_field_exists_cache = array();

	// Billing Status Constants
	const STATUS_PENDING = 'PENDING';
	const STATUS_BILLED = 'BILLED';
	const STATUS_PARTIAL = 'PARTIAL';
	const STATUS_PAID = 'PAID';
	const STATUS_CANCELLED = 'CANCELLED';
	const STATUS_REFUNDED = 'REFUNDED';
	const STATUS_COVERED = 'COVERED';
	const QUEUE_CLAIMED = 'CLAIMED';
	const QUEUE_INVOICED = 'INVOICED';
	const QUEUE_FAILED = 'FAILED';

	// Payment Method Constants
	const PAY_CASH = 'CASH';
	const PAY_MOMO = 'MOMO';
	const PAY_CARD = 'CARD';
	const PAY_BANK = 'BANK';
	const PAY_NHIS = 'NHIS';
	const PAY_INSURANCE = 'INSURANCE';
	const PAY_COMPANY = 'COMPANY';

	// Account Types for Chart of Accounts
	const ACCT_ASSET = 'ASSET';
	const ACCT_LIABILITY = 'LIABILITY';
	const ACCT_EQUITY = 'EQUITY';
	const ACCT_REVENUE = 'REVENUE';
	const ACCT_EXPENSE = 'EXPENSE';

	public function __construct()
	{
		parent::__construct();
	}

	/**
	 * Check if a column exists in a table
	 */
	public function column_exists($table, $column)
	{
		$query = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($column));
		return ($query && $query->num_rows() > 0);
	}

	/**
	 * Check if a table exists
	 */
	public function table_exists($table)
	{
		$table = (string)$table;
		if (array_key_exists($table, $this->_table_exists_cache)) {
			return $this->_table_exists_cache[$table];
		}
		$this->_table_exists_cache[$table] = $this->db->table_exists($table);
		return $this->_table_exists_cache[$table];
	}

	private function field_exists_cached($field, $table)
	{
		$field = (string)$field;
		$table = (string)$table;
		$cache_key = $table . '.' . $field;
		if (array_key_exists($cache_key, $this->_field_exists_cache)) {
			return $this->_field_exists_cache[$cache_key];
		}
		$this->_field_exists_cache[$cache_key] = $this->db->field_exists($field, $table);
		return $this->_field_exists_cache[$cache_key];
	}

	private function index_exists($table, $index_name)
	{
		$table = trim((string)$table);
		$index_name = trim((string)$index_name);
		if ($table === '' || $index_name === '') {
			return false;
		}
		if (!$this->db->table_exists($table)) {
			return false;
		}
		try {
			$q = $this->db->query("SHOW INDEX FROM `" . $this->db->escape_str($table) . "` WHERE Key_name = " . $this->db->escape($index_name));
			return ($q && $q->num_rows() > 0);
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function _get_discount_column($table)
	{
		if (!$this->db->table_exists($table)) {
			return null;
		}
		if ($this->db->field_exists('discount', $table)) {
			return 'discount';
		}
		if ($this->db->field_exists('discount_amount', $table)) {
			return 'discount_amount';
		}
		if ($this->db->field_exists('discountAmount', $table)) {
			return 'discountAmount';
		}
		return null;
	}

	private function _discount_expr($table, $alias)
	{
		$col = $this->_get_discount_column($table);
		if ($col) {
			return "COALESCE({$alias}.{$col}, 0)";
		}
		return '0';
	}

	/* ================================================================== */
	/*  SCHEMA MIGRATION - Creates all required tables                     */
	/* ================================================================== */

	/**
	 * Ensure all unified billing schema exists
	 * Safe to call multiple times (idempotent)
	 */
	public function ensure_unified_billing_schema()
	{
		$this->load->helper('schema_guard');
		$this->_ensure_pharmacy_pricing_provenance_schema();
		$this->_ensure_subject_billing_nullable_identifiers();
		if (schema_already_run('unified_billing_schema')) {
			return true;
		}
		$this->_ensure_billing_queue_table();
		$this->_ensure_chart_of_accounts();
		$this->_ensure_financial_ledger();
		$this->_ensure_billing_enhancements();
		$this->_seed_default_accounts();
		mark_schema_run('unified_billing_schema');
		return true;
	}

	private function _ensure_subject_billing_nullable_identifiers()
	{
		static $checked = false;
		if ($checked) {
			return;
		}
		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) { $this->db->db_debug = false; }
		try {
			if ($this->db->table_exists('iop_billing')) {
				if ($this->db->field_exists('iop_id', 'iop_billing')) {
					$this->db->query("ALTER TABLE `iop_billing` MODIFY COLUMN `iop_id` VARCHAR(25) DEFAULT NULL");
				}
				if ($this->db->field_exists('patient_no', 'iop_billing')) {
					$this->db->query("ALTER TABLE `iop_billing` MODIFY COLUMN `patient_no` VARCHAR(25) DEFAULT NULL");
				}
			}
			if ($this->db->table_exists('iop_billing_t') && $this->db->field_exists('iop_id', 'iop_billing_t')) {
				$this->db->query("ALTER TABLE `iop_billing_t` MODIFY COLUMN `iop_id` VARCHAR(25) DEFAULT NULL");
			}
			if ($this->db->table_exists('iop_receipt')) {
				if ($this->db->field_exists('iop_id', 'iop_receipt')) {
					$this->db->query("ALTER TABLE `iop_receipt` MODIFY COLUMN `iop_id` VARCHAR(25) DEFAULT NULL");
				}
				if ($this->db->field_exists('patient_no', 'iop_receipt')) {
					$this->db->query("ALTER TABLE `iop_receipt` MODIFY COLUMN `patient_no` VARCHAR(25) DEFAULT NULL");
				}
			}
		} catch (\Throwable $e) {
			log_message('error', 'Unified Billing: subject nullable identifier schema ensure failed: ' . $e->getMessage());
		}
		if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }
		$checked = true;
	}

	private function _ensure_pharmacy_pricing_provenance_schema()
	{
		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) { $this->db->db_debug = false; }
		try {
			foreach (array(
				'billing_queue' => array(
					'pricing_source' => "ALTER TABLE `billing_queue` ADD COLUMN `pricing_source` VARCHAR(30) DEFAULT NULL",
					'pricing_source_id' => "ALTER TABLE `billing_queue` ADD COLUMN `pricing_source_id` VARCHAR(64) DEFAULT NULL",
					'resolved_drug_id' => "ALTER TABLE `billing_queue` ADD COLUMN `resolved_drug_id` INT(11) DEFAULT NULL",
					'resolved_stock_id' => "ALTER TABLE `billing_queue` ADD COLUMN `resolved_stock_id` INT(11) DEFAULT NULL",
					'substitution_flag' => "ALTER TABLE `billing_queue` ADD COLUMN `substitution_flag` TINYINT(1) NOT NULL DEFAULT 0",
				),
				'iop_billing_line_meta' => array(
					'pricing_source' => "ALTER TABLE `iop_billing_line_meta` ADD COLUMN `pricing_source` VARCHAR(30) DEFAULT NULL",
					'pricing_source_id' => "ALTER TABLE `iop_billing_line_meta` ADD COLUMN `pricing_source_id` VARCHAR(64) DEFAULT NULL",
					'resolved_drug_id' => "ALTER TABLE `iop_billing_line_meta` ADD COLUMN `resolved_drug_id` INT(11) DEFAULT NULL",
					'resolved_stock_id' => "ALTER TABLE `iop_billing_line_meta` ADD COLUMN `resolved_stock_id` INT(11) DEFAULT NULL",
					'substitution_flag' => "ALTER TABLE `iop_billing_line_meta` ADD COLUMN `substitution_flag` TINYINT(1) NOT NULL DEFAULT 0",
				),
				'iop_billing_t' => array(
					'pricing_source_id' => "ALTER TABLE `iop_billing_t` ADD COLUMN `pricing_source_id` VARCHAR(64) DEFAULT NULL",
					'resolved_drug_id' => "ALTER TABLE `iop_billing_t` ADD COLUMN `resolved_drug_id` INT(11) DEFAULT NULL",
					'resolved_stock_id' => "ALTER TABLE `iop_billing_t` ADD COLUMN `resolved_stock_id` INT(11) DEFAULT NULL",
					'substitution_flag' => "ALTER TABLE `iop_billing_t` ADD COLUMN `substitution_flag` TINYINT(1) NOT NULL DEFAULT 0",
				),
			) as $_table => $_cols) {
				if (!$this->db->table_exists($_table)) { continue; }
				foreach ($_cols as $_col => $_sql) {
					if (!$this->db->field_exists($_col, $_table)) {
						$this->db->query($_sql);
					}
				}
			}
		} catch (\Throwable $e) {
			log_message('error', 'Unified Billing: pricing provenance schema ensure failed: ' . $e->getMessage());
		}
		if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }
	}

	/**
	 * Billing Queue - All billable items flow through here before invoicing
	 */
	private function _ensure_billing_queue_table()
	{
		if (!$this->db->table_exists('billing_queue')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS billing_queue (
					queue_id INT AUTO_INCREMENT PRIMARY KEY,
					iop_id VARCHAR(25) DEFAULT NULL,
					patient_no VARCHAR(25) DEFAULT NULL,
					billing_subject_type VARCHAR(32) DEFAULT NULL,
					billing_subject_id VARCHAR(64) DEFAULT NULL,
					item_type ENUM('CONSULTATION','REGISTRATION','LAB','PHARMACY','SONOGRAPHY','RADIOLOGY','PROCEDURE','ADMISSION','SURGERY','ROOM','SUPPLY','OTHER') NOT NULL,
					item_id VARCHAR(50) NOT NULL COMMENT 'Reference to source table record',
					item_name VARCHAR(255) NOT NULL,
					-- Service Gate: Release service only after billing/payment
					service_gate_status ENUM('BLOCKED','RELEASED','EXPIRED') NOT NULL DEFAULT 'BLOCKED',
					released_at DATETIME DEFAULT NULL,
					released_by VARCHAR(25) DEFAULT NULL,
					quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
					unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					net_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					payer_type ENUM('CASH','NHIS','INSURANCE','COMPANY') NOT NULL DEFAULT 'CASH',
					coverage_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount covered by payer',
					patient_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Amount patient pays',
					status ENUM('PENDING','CLAIMED','INVOICED','BILLED','CANCELLED','FAILED') NOT NULL DEFAULT 'PENDING',
					invoice_no VARCHAR(50) DEFAULT NULL,
					source_module VARCHAR(50) DEFAULT NULL,
					source_ref VARCHAR(100) DEFAULT NULL,
					requested_by VARCHAR(25) DEFAULT NULL,
					requested_at DATETIME DEFAULT NULL,
					billed_by VARCHAR(25) DEFAULT NULL,
					billed_at DATETIME DEFAULT NULL,
					claim_token VARCHAR(64) DEFAULT NULL,
					claimed_at DATETIME DEFAULT NULL,
					claimed_by VARCHAR(25) DEFAULT NULL,
					idempotency_key VARCHAR(64) DEFAULT NULL,
					notes TEXT,
					InActive TINYINT(1) NOT NULL DEFAULT 0,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_bq_iop (iop_id),
					INDEX idx_bq_patient (patient_no),
					INDEX idx_bq_subject_status (billing_subject_type, billing_subject_id, status),
					INDEX idx_bq_claim_token (claim_token),
					INDEX idx_bq_status (status),
					INDEX idx_bq_invoice (invoice_no),
					INDEX idx_bq_type (item_type),
					UNIQUE KEY uq_bq_idem (idempotency_key),
					UNIQUE KEY uq_bq_source (source_module, source_ref)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}
		// Patch older schemas where billing_queue exists but enum values are incomplete
		try {
			$q = $this->db->query("SHOW COLUMNS FROM `billing_queue` LIKE 'item_type'");
			$row = $q ? $q->row() : null;
			$type = ($row && isset($row->Type)) ? (string)$row->Type : '';
			if ($type !== '' && (stripos($type, "'SONOGRAPHY'") === false || stripos($type, "'RADIOLOGY'") === false || stripos($type, "'SUPPLY'") === false)) {
				$this->db->query("ALTER TABLE billing_queue MODIFY item_type ENUM('CONSULTATION','REGISTRATION','LAB','PHARMACY','SONOGRAPHY','RADIOLOGY','PROCEDURE','ADMISSION','SURGERY','ROOM','SUPPLY','OTHER') NOT NULL");
			}
		} catch (\Throwable $e) {
		}

		// Patch older schemas where billing_queue exists but status enum values are incomplete
		try {
			$q = $this->db->query("SHOW COLUMNS FROM `billing_queue` LIKE 'status'");
			$row = $q ? $q->row() : null;
			$type = ($row && isset($row->Type)) ? (string)$row->Type : '';
			if ($type !== '' && stripos($type, "'CLAIMED'") === false) {
				$this->db->query("ALTER TABLE billing_queue MODIFY status ENUM('PENDING','CLAIMED','INVOICED','BILLED','CANCELLED','FAILED') NOT NULL DEFAULT 'PENDING'");
			}
		} catch (\Throwable $e) {
		}

		// Subject-aware / queue ownership fields (additive, best-effort)
		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) { $this->db->db_debug = false; }
		try {
			if ($this->db->table_exists('billing_queue')) {
				if ($this->db->field_exists('iop_id', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue MODIFY COLUMN iop_id VARCHAR(25) DEFAULT NULL");
				}
				if ($this->db->field_exists('patient_no', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue MODIFY COLUMN patient_no VARCHAR(25) DEFAULT NULL");
				}
				if (!$this->db->field_exists('billing_subject_type', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue ADD COLUMN billing_subject_type VARCHAR(32) DEFAULT NULL AFTER patient_no");
				}
				if (!$this->db->field_exists('billing_subject_id', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue ADD COLUMN billing_subject_id VARCHAR(64) DEFAULT NULL AFTER billing_subject_type");
				}
				if (!$this->db->field_exists('claim_token', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue ADD COLUMN claim_token VARCHAR(64) DEFAULT NULL");
				}
				if (!$this->db->field_exists('claimed_at', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue ADD COLUMN claimed_at DATETIME DEFAULT NULL");
				}
				if (!$this->db->field_exists('claimed_by', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue ADD COLUMN claimed_by VARCHAR(25) DEFAULT NULL");
				}
				if (!$this->db->field_exists('idempotency_key', 'billing_queue')) {
					$this->db->query("ALTER TABLE billing_queue ADD COLUMN idempotency_key VARCHAR(64) DEFAULT NULL");
				}
				try {
					$this->db->query("CREATE INDEX idx_bq_subject_status ON billing_queue (billing_subject_type, billing_subject_id, status)");
				} catch (\Throwable $e) {
				}
				try {
					$this->db->query("CREATE INDEX idx_bq_claim_token ON billing_queue (claim_token)");
				} catch (\Throwable $e) {
				}
				try {
					$this->db->query("CREATE UNIQUE INDEX uq_bq_idem ON billing_queue (idempotency_key)");
				} catch (\Throwable $e) {
				}
			}
		} catch (\Throwable $e) {
		}
		if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }
		return true;
	}

	public function add_to_billing_queue_by_subject($data)
	{
		$this->ensure_unified_billing_schema();
		if (!is_array($data)) {
			return array('success' => false, 'error' => 'Invalid payload');
		}
		$bst = isset($data['billing_subject_type']) ? strtoupper(trim((string)$data['billing_subject_type'])) : '';
		$bsid = isset($data['billing_subject_id']) ? trim((string)$data['billing_subject_id']) : '';
		if ($bst === '' || $bsid === '') {
			return array('success' => false, 'error' => 'billing_subject_type and billing_subject_id are required');
		}
		$data['billing_subject_type'] = $bst;
		$data['billing_subject_id'] = $bsid;

		if ($bst === 'PATIENT_VISIT') {
			if (!isset($data['iop_id']) || trim((string)$data['iop_id']) === '') {
				return array('success' => false, 'error' => 'Missing required field: iop_id');
			}
			if (!isset($data['patient_no']) || trim((string)$data['patient_no']) === '') {
				return array('success' => false, 'error' => 'Missing required field: patient_no');
			}
			return $this->add_to_billing_queue($data);
		}

		if ($bst === 'WALKIN_ORDER' && strtoupper(trim((string)(isset($res['payment_status']) ? $res['payment_status'] : ''))) === 'PAID') {
			if (isset($data['iop_id']) && trim((string)$data['iop_id']) !== '') {
				return array('success' => false, 'error' => 'WALKIN_ORDER forbids iop_id');
			}
			if (isset($data['patient_no']) && trim((string)$data['patient_no']) !== '') {
				return array('success' => false, 'error' => 'WALKIN_ORDER forbids patient_no');
			}
			return $this->_insert_subject_queue_row($data);
		}

		return array('success' => false, 'error' => 'Unsupported billing_subject_type');
	}

	private function _insert_subject_queue_row(array $data)
	{
		$required = array('item_type', 'item_id', 'item_name', 'unit_price', 'billing_subject_type', 'billing_subject_id');
		foreach ($required as $field) {
			if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
				return array('success' => false, 'error' => "Missing required field: $field");
			}
		}

		$bst = strtoupper(trim((string)$data['billing_subject_type']));
		$bsid = trim((string)$data['billing_subject_id']);
		if ($bst === '' || $bsid === '') {
			return array('success' => false, 'error' => 'Invalid billing subject');
		}

		// Duplicate prevention: idempotency_key or source_module/source_ref
		if (isset($data['idempotency_key']) && trim((string)$data['idempotency_key']) !== '') {
			$this->db->where('idempotency_key', (string)$data['idempotency_key']);
			$this->db->where('InActive', 0);
			$existing = $this->db->get('billing_queue')->row();
			if ($existing && isset($existing->queue_id)) {
				return array('success' => true, 'queue_id' => (int)$existing->queue_id);
			}
		} elseif (isset($data['source_module']) && isset($data['source_ref'])) {
			$this->db->where('source_module', $data['source_module']);
			$this->db->where('source_ref', $data['source_ref']);
			$this->db->where('InActive', 0);
			$existing = $this->db->get('billing_queue')->row();
			if ($existing && isset($existing->queue_id)) {
				$existing_id = (int)$existing->queue_id;
				$existing_status = isset($existing->status) ? strtoupper(trim((string)$existing->status)) : '';
				if ($existing_id > 0 && ($existing_status === '' || $existing_status === self::STATUS_PENDING)) {
					return array('success' => true, 'queue_id' => $existing_id);
				}
				return array('success' => false, 'error' => 'Item already in billing queue');
			}
		}

		$quantity = isset($data['quantity']) ? (float)$data['quantity'] : 1.0;
		if ($quantity <= 0) { $quantity = 1.0; }
		$unit_price = (float)$data['unit_price'];
		if (strtoupper(trim((string)$data['item_type'])) === 'PHARMACY' && $unit_price <= 0.009 && empty($data['allow_zero_price'])) {
			return array('success' => false, 'error' => 'Pharmacy pricing resolution failed. Workflow blocked to prevent zero-value billing.');
		}
		$discount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0.0;
		$total = $quantity * $unit_price;
		$net = $total - $discount;
		$payer_type = isset($data['payer_type']) ? strtoupper(trim((string)$data['payer_type'])) : 'CASH';
		if ($payer_type === '') { $payer_type = 'CASH'; }
		$coverage = isset($data['coverage_amount']) ? (float)$data['coverage_amount'] : 0.0;
		$patient_amount = $net - $coverage;

		$now = date('Y-m-d H:i:s');
		$insert = array(
			'iop_id' => null,
			'patient_no' => null,
			'billing_subject_type' => $bst,
			'billing_subject_id' => $bsid,
			'item_type' => $data['item_type'],
			'item_id' => $data['item_id'],
			'item_name' => $data['item_name'],
			'quantity' => $quantity,
			'unit_price' => $unit_price,
			'total_amount' => $total,
			'discount_amount' => $discount,
			'net_amount' => $net,
			'payer_type' => $payer_type,
			'coverage_amount' => $coverage,
			'patient_amount' => $patient_amount,
			'status' => self::STATUS_PENDING,
			'claim_token' => null,
			'claimed_at' => null,
			'claimed_by' => null,
			'source_module' => isset($data['source_module']) ? $data['source_module'] : null,
			'source_ref' => isset($data['source_ref']) ? $data['source_ref'] : null,
			'requested_by' => isset($data['requested_by']) ? $data['requested_by'] : null,
			'requested_at' => $now,
			'idempotency_key' => isset($data['idempotency_key']) ? $data['idempotency_key'] : null,
			'notes' => isset($data['notes']) ? $data['notes'] : null,
			'InActive' => 0,
		);
		if ($this->column_exists('billing_queue', 'created_at')) {
			$insert['created_at'] = $now;
		}
		if ($this->column_exists('billing_queue', 'updated_at')) {
			$insert['updated_at'] = $now;
		}
		foreach (array('pricing_source','pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag') as $_prov_col) {
			if ($this->column_exists('billing_queue', $_prov_col) && array_key_exists($_prov_col, $data)) {
				$insert[$_prov_col] = $data[$_prov_col];
			}
		}

		$ok = $this->db->insert('billing_queue', $insert);
		if (!$ok) {
			$err = $this->db->error();
			$msg = (isset($err['message']) && $err['message'] !== '') ? (string)$err['message'] : 'Insert failed';
			log_message('error', 'Unified Billing: add_to_billing_queue_by_subject insert failed: ' . $msg);
			return array('success' => false, 'error' => $msg);
		}
		return array('success' => true, 'queue_id' => (int)$this->db->insert_id());
	}

	public function generate_invoice_by_subject($billing_subject_type, $billing_subject_id, $queue_ids = null, $created_by = null)
	{
		$this->ensure_unified_billing_schema();
		$bst = strtoupper(trim((string)$billing_subject_type));
		$bsid = trim((string)$billing_subject_id);
		if ($bst === '' || $bsid === '') {
			return array('success' => false, 'error' => 'Invalid billing subject');
		}

		// Claim / own queue rows transactionally to prevent double-invoicing
		$token = sha1($bst . '|' . $bsid . '|' . microtime(true) . '|' . uniqid('', true));
		$now = date('Y-m-d H:i:s');
		$created_by = $created_by !== null ? (string)$created_by : null;
		$this->_ensure_invoice_source_tables();
		if ($bst === 'PATIENT_VISIT' && $queue_ids === null) {
			$parts = explode('|', $bsid, 2);
			if (count($parts) === 2) {
				$this->_ensure_visit_fee_queue_for_invoice($parts[0], $parts[1], $created_by);
			}
		}

		$this->db->trans_begin();
		try {
			$this->db->where('billing_subject_type', $bst);
			$this->db->where('billing_subject_id', $bsid);
			$this->db->where('status', 'PENDING');
			$this->db->where('InActive', 0);
			if ($queue_ids && is_array($queue_ids)) {
				$this->db->where_in('queue_id', $queue_ids);
			}
			$this->db->group_start();
			$this->db->where('claim_token IS NULL', null, false);
			$this->db->or_where('claim_token', '');
			$this->db->group_end();
			$this->db->update('billing_queue', array(
				'claim_token' => $token,
				'claimed_at' => $now,
				'claimed_by' => $created_by,
				'status' => self::QUEUE_CLAIMED,
				'updated_at' => $now,
			));
			$claimed = (int)$this->db->affected_rows();
			if ($claimed <= 0) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'No pending items available to claim');
			}
			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Claim failed');
			}
			$this->db->trans_commit();
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => $e->getMessage());
		}

		$this->db->trans_begin();
		try {
			$this->db->where('billing_subject_type', $bst);
			$this->db->where('billing_subject_id', $bsid);
			$this->db->where('status', self::QUEUE_CLAIMED);
			$this->db->where('InActive', 0);
			$this->db->where('claim_token', $token);
			if ($queue_ids && is_array($queue_ids)) {
				$this->db->where_in('queue_id', $queue_ids);
			}
			$items = $this->db->get('billing_queue')->result();
			if (empty($items)) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Claim failed');
			}
			foreach ($items as $item) {
				$item_type = isset($item->item_type) ? strtoupper(trim((string)$item->item_type)) : '';
				$unit = isset($item->unit_price) ? (float)$item->unit_price : 0.0;
				if (($item_type === 'LAB' || $item_type === 'PHARMACY') && $unit <= 0.009 && empty($item->allow_zero_price)) {
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'Missing pricing for: ' . (isset($item->item_name) ? (string)$item->item_name : $item_type));
				}
			}

			// Compute totals (re-using existing logic style)
			$total = 0;
			$discount = 0;
			$coverage = 0;
			$patient_total = 0;
			$payer_type = 'CASH';
			foreach ($items as $item) {
				$total += (float)$item->net_amount;
				$discount += (float)$item->discount_amount;
				$coverage += (float)$item->coverage_amount;
				$patient_total += (float)$item->patient_amount;
				if (isset($item->payer_type) && (string)$item->payer_type !== 'CASH') {
					$payer_type = (string)$item->payer_type;
				}
			}

			$invoice_no = $this->_generate_invoice_no();
			$bill_disc_col = $this->_get_discount_column('iop_billing');
			$billt_disc_col = $this->_get_discount_column('iop_billing_t');
			$hasSubTotal = $this->column_exists('iop_billing_t', 'sub_total');

			// Use iop_id/patient_no from the first item when present; otherwise NULL
			$first = $items[0];
			$iop_id = isset($first->iop_id) ? trim((string)$first->iop_id) : '';
			$patient_no = isset($first->patient_no) ? trim((string)$first->patient_no) : '';
			if ($bst === 'WALKIN_ORDER') {
				foreach ($items as $it) {
					$vi = isset($it->iop_id) ? trim((string)$it->iop_id) : '';
					$vp = isset($it->patient_no) ? trim((string)$it->patient_no) : '';
					if ($vi !== '' || $vp !== '') {
						$this->db->trans_rollback();
						return array('success' => false, 'error' => 'WALKIN_ORDER invoice cannot consume visit/patient identifiers');
					}
				}
				$iop_id = '';
				$patient_no = '';
			}
			if ($bst === 'PATIENT_VISIT') {
				if ($iop_id === '' || $patient_no === '') {
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'PATIENT_VISIT invoice requires iop_id and patient_no');
				}
			}

			$header = array(
				'invoice_no' => $invoice_no,
				'iop_id' => ($iop_id !== '' ? $iop_id : null),
				'patient_no' => ($patient_no !== '' ? $patient_no : null),
				'total_amount' => $total,
				'payment_status' => 'UNPAID',
				'balance_due' => $patient_total,
				'payer_type' => $payer_type,
				'dDate' => date('Y-m-d H:i:s'),
				'InActive' => 0,
			);
			if ($bill_disc_col) {
				$header[$bill_disc_col] = $discount;
			}
			if ($this->column_exists('iop_billing', 'sub_total')) {
				$header['sub_total'] = (float)$total + (float)$discount;
			}
			if ($this->column_exists('iop_billing', 'total_purchased')) {
				$header['total_purchased'] = count($items);
			}
			if ($created_by) {
				$header['updated_by'] = $created_by;
			}
			if ($this->column_exists('iop_billing', 'billing_subject_type')) {
				$header['billing_subject_type'] = $bst;
			}
			if ($this->column_exists('iop_billing', 'billing_subject_id')) {
				$header['billing_subject_id'] = $bsid;
			}
			$this->db->insert('iop_billing', $header);

			$line_no = 0;
			foreach ($items as $item) {
				$line_no++;
				$line = array(
					'invoice_no' => $invoice_no,
					'iop_id' => ($iop_id !== '' ? $iop_id : null),
					'bill_name' => $item->item_name,
					'qty' => $item->quantity,
					'rate' => $item->unit_price,
					'amount' => $item->net_amount,
					'InActive' => 0,
				);
				if ($billt_disc_col) {
					$line[$billt_disc_col] = $item->discount_amount;
				}
				if ($hasSubTotal) {
					$line['sub_total'] = isset($item->total_amount) ? (float)$item->total_amount : (float)$item->net_amount;
				}
				$this->db->insert('iop_billing_t', $line);
				$detail_id = (int)$this->db->insert_id();
				$this->_persist_queue_invoice_source($invoice_no, $detail_id, $item, $iop_id, $patient_no, $created_by);

				$this->db->where('queue_id', (int)$item->queue_id);
				$this->db->where('claim_token', $token);
				$this->db->where('status', self::QUEUE_CLAIMED);
				$this->db->where('InActive', 0);
				$this->db->group_start();
				$this->db->where('invoice_no IS NULL', null, false);
				$this->db->or_where('invoice_no', '');
				$this->db->group_end();
				$this->db->update('billing_queue', array(
					'status' => self::QUEUE_INVOICED,
					'invoice_no' => $invoice_no,
					'billed_by' => $created_by,
					'billed_at' => $now,
					'updated_at' => $now,
				));
				if ((int)$this->db->affected_rows() <= 0) {
					throw new Exception('Queue state transition failed');
				}
			}

			// Ledger entry — patient_no may be null for walk-ins; ledger table is tolerant.
			$this->_record_invoice_ledger($invoice_no, ($patient_no !== '' ? $patient_no : null), $total, $payer_type, $created_by);

			if ($this->db->trans_status() === false) {
				throw new Exception('Database error during invoice creation');
			}
			$this->db->trans_commit();
			return array('success' => true, 'invoice_no' => $invoice_no, 'total' => $total, 'items_count' => count($items), 'claim_token' => $token);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			try {
				$this->db->where('claim_token', $token);
				$this->db->where('status', self::QUEUE_CLAIMED);
				$this->db->where('InActive', 0);
				$this->db->group_start();
				$this->db->where('invoice_no IS NULL', null, false);
				$this->db->or_where('invoice_no', '');
				$this->db->group_end();
				$this->db->update('billing_queue', array(
					'status' => self::QUEUE_FAILED,
					'updated_at' => date('Y-m-d H:i:s'),
				));
			} catch (\Throwable $e2) {
			}
			return array('success' => false, 'error' => $e->getMessage());
		}
	}

	public function recover_stale_claims($timeout_seconds = 600, $limit = 200, $actor = null)
	{
		$this->ensure_unified_billing_schema();
		$timeout_seconds = (int)$timeout_seconds;
		if ($timeout_seconds <= 0) { $timeout_seconds = 600; }
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 200; }
		$cutoff = date('Y-m-d H:i:s', time() - $timeout_seconds);
		$actor = $actor !== null ? (string)$actor : 'SYSTEM';
		$now = date('Y-m-d H:i:s');

		$tokens = array();
		try {
			$this->db->select('claim_token');
			$this->db->from('billing_queue');
			$this->db->where('status', self::QUEUE_CLAIMED);
			$this->db->where('InActive', 0);
			$this->db->where('claimed_at <', $cutoff);
			$this->db->where('claim_token IS NOT NULL', null, false);
			$this->db->where('claim_token <>', '');
			$this->db->group_by('claim_token');
			$this->db->limit($limit);
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$tk = isset($r->claim_token) ? trim((string)$r->claim_token) : '';
				if ($tk !== '') { $tokens[] = $tk; }
			}
		} catch (\Throwable $e) {
			return array('success' => false, 'error' => 'Query failed');
		}

		if (empty($tokens)) {
			return array('success' => true, 'recovered' => 0, 'tokens' => array());
		}

		$recovered = 0;
		$recovered_tokens = array();
		foreach ($tokens as $tk) {
			try {
				$this->db->where('claim_token', $tk);
				$this->db->where('InActive', 0);
				$this->db->group_start();
				$this->db->where('invoice_no IS NOT NULL', null, false);
				$this->db->where('invoice_no <>', '');
				$this->db->group_end();
				$hasInvoice = ((int)$this->db->count_all_results('billing_queue') > 0);
				if ($hasInvoice) {
					continue;
				}

				$this->db->where('claim_token', $tk);
				$this->db->where('status', self::QUEUE_CLAIMED);
				$this->db->where('InActive', 0);
				$this->db->update('billing_queue', array(
					'status' => self::STATUS_PENDING,
					'claim_token' => null,
					'claimed_at' => null,
					'claimed_by' => null,
					'updated_at' => $now,
				));
				$aff = (int)$this->db->affected_rows();
				if ($aff > 0) {
					$recovered += $aff;
					$recovered_tokens[] = $tk;
				}
			} catch (\Throwable $e) {
				continue;
			}
		}

		return array('success' => true, 'recovered' => $recovered, 'tokens' => $recovered_tokens, 'actor' => $actor);
	}

	public function retry_failed_queue_by_subject($billing_subject_type, $billing_subject_id, $queue_ids = null, $actor = null)
	{
		$this->ensure_unified_billing_schema();
		$bst = strtoupper(trim((string)$billing_subject_type));
		$bsid = trim((string)$billing_subject_id);
		if ($bst === '' || $bsid === '') {
			return array('success' => false, 'error' => 'Invalid billing subject');
		}
		$actor = $actor !== null ? (string)$actor : 'SYSTEM';
		$now = date('Y-m-d H:i:s');

		$this->db->where('billing_subject_type', $bst);
		$this->db->where('billing_subject_id', $bsid);
		$this->db->where('status', self::QUEUE_FAILED);
		$this->db->where('InActive', 0);
		if ($queue_ids && is_array($queue_ids)) {
			$this->db->where_in('queue_id', $queue_ids);
		}
		$this->db->update('billing_queue', array(
			'status' => self::STATUS_PENDING,
			'claim_token' => null,
			'claimed_at' => null,
			'claimed_by' => null,
			'updated_at' => $now,
		));

		return array('success' => true, 'retried' => (int)$this->db->affected_rows(), 'actor' => $actor);
	}

	public function cancel_queue_by_subject($billing_subject_type, $billing_subject_id, $queue_ids = null, $actor = null)
	{
		$this->ensure_unified_billing_schema();
		$bst = strtoupper(trim((string)$billing_subject_type));
		$bsid = trim((string)$billing_subject_id);
		if ($bst === '' || $bsid === '') {
			return array('success' => false, 'error' => 'Invalid billing subject');
		}
		$actor = $actor !== null ? (string)$actor : 'SYSTEM';
		$now = date('Y-m-d H:i:s');

		$this->db->where('billing_subject_type', $bst);
		$this->db->where('billing_subject_id', $bsid);
		$this->db->where('InActive', 0);
		$this->db->group_start();
		$this->db->where('status', self::STATUS_PENDING);
		$this->db->or_where('status', self::QUEUE_FAILED);
		$this->db->group_end();
		if ($queue_ids && is_array($queue_ids)) {
			$this->db->where_in('queue_id', $queue_ids);
		}
		$this->db->update('billing_queue', array(
			'status' => self::STATUS_CANCELLED,
			'updated_at' => $now,
		));

		return array('success' => true, 'cancelled' => (int)$this->db->affected_rows(), 'actor' => $actor);
	}

	public function process_payment_by_subject($billing_subject_type, $billing_subject_id, $invoice_no, $amount, $payment_method = 'CASH', $cashier_id = null, $reference = null, $notes = null, $receipt_no = null)
	{
		$this->ensure_unified_billing_schema();
		$bst = strtoupper(trim((string)$billing_subject_type));
		$bsid = trim((string)$billing_subject_id);
		$invoice_no = trim((string)$invoice_no);
		$amount = (float)$amount;
		if ($bst === '' || $bsid === '' || $invoice_no === '' || $amount <= 0) {
			return array('success' => false, 'error' => 'Invalid payment request');
		}

		// Verify invoice subject ownership (defense-in-depth)
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$inv = $this->db->get('iop_billing')->row();
		if (!$inv) {
			return array('success' => false, 'error' => 'Invoice not found');
		}
		$inv_bst = $this->column_exists('iop_billing', 'billing_subject_type') && isset($inv->billing_subject_type) ? trim((string)$inv->billing_subject_type) : '';
		$inv_bsid = $this->column_exists('iop_billing', 'billing_subject_id') && isset($inv->billing_subject_id) ? trim((string)$inv->billing_subject_id) : '';
		if ($inv_bst !== '' && $inv_bsid !== '' && ($inv_bst !== $bst || $inv_bsid !== $bsid)) {
			return array('success' => false, 'error' => 'Invoice subject mismatch');
		}

		// Route to facade (canonical chokepoint) so SSOT sync remains inside the payment transaction.
		$this->load->model('app/Billing_facade_model', 'billing_facade');
		$res = $this->billing_facade->record_payment(array(
			'invoice_no' => $invoice_no,
			'amount' => $amount,
			'payment_method' => $payment_method,
			'reference' => $reference,
			'notes' => $notes,
			'cashier_id' => $cashier_id,
			'receipt_no' => $receipt_no,
			'billing_subject_type' => $bst,
			'billing_subject_id' => $bsid,
			'source' => 'SUBJECT',
		));
		if (!is_array($res) || empty($res['ok'])) {
			return array('success' => false, 'error' => is_array($res) && isset($res['error']) ? $res['error'] : 'Payment failed');
		}

		$rcp = isset($res['receipt_no']) ? (string)$res['receipt_no'] : '';
		if ($bst === 'WALKIN_ORDER') {
			try {
				$CI =& get_instance();
				$CI->load->model('app/walkin_order_model');
				if (isset($CI->walkin_order_model) && method_exists($CI->walkin_order_model, 'mark_order_paid_authorized')) {
					$sync = $CI->walkin_order_model->mark_order_paid_authorized($bsid, $rcp, $cashier_id);
					if (!is_array($sync) || empty($sync['success'])) {
						$err = is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : 'unknown sync failure';
						log_message('error', 'process_payment_by_subject WALKIN_ORDER paid but fulfillment authorization sync failed: order=' . $bsid . ' invoice=' . $invoice_no . ' receipt=' . $rcp . ' error=' . $err);
					}
				} else {
					log_message('error', 'process_payment_by_subject WALKIN_ORDER paid but walkin_order_model::mark_order_paid_authorized unavailable: order=' . $bsid . ' invoice=' . $invoice_no . ' receipt=' . $rcp);
				}
			} catch (\Throwable $e) {
				log_message('error', 'process_payment_by_subject WALKIN_ORDER sync exception: order=' . $bsid . ' invoice=' . $invoice_no . ' receipt=' . $rcp . ' error=' . $e->getMessage());
			}
		}
		return array('success' => true, 'receipt_no' => $rcp, 'payment_status' => isset($res['payment_status']) ? $res['payment_status'] : null, 'ssot_sync' => isset($res['ssot_sync']) ? $res['ssot_sync'] : null);
	}

	/**
	 * Chart of Accounts - Standard accounting structure
	 */
	private function _ensure_chart_of_accounts()
	{
		if (!$this->db->table_exists('chart_of_accounts')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS chart_of_accounts (
					account_id INT AUTO_INCREMENT PRIMARY KEY,
					account_code VARCHAR(20) NOT NULL,
					account_name VARCHAR(100) NOT NULL,
					account_type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE') NOT NULL,
					parent_id INT DEFAULT NULL,
					description VARCHAR(255) DEFAULT NULL,
					is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System accounts cannot be deleted',
					is_active TINYINT(1) NOT NULL DEFAULT 1,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					UNIQUE KEY uq_coa_code (account_code),
					INDEX idx_coa_type (account_type),
					INDEX idx_coa_parent (parent_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}
		return true;
	}

	/**
	 * Financial Ledger - Double-entry accounting
	 */
	private function _ensure_financial_ledger()
	{
		if (!$this->db->table_exists('financial_ledger')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS financial_ledger (
					ledger_id BIGINT AUTO_INCREMENT PRIMARY KEY,
					transaction_id VARCHAR(50) NOT NULL COMMENT 'Groups related entries',
					transaction_date DATE NOT NULL,
					account_id INT NOT NULL,
					debit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					credit_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					reference_type VARCHAR(50) DEFAULT NULL COMMENT 'INVOICE, RECEIPT, REFUND, etc',
					reference_no VARCHAR(50) DEFAULT NULL,
					patient_no VARCHAR(25) DEFAULT NULL,
					billing_subject_type VARCHAR(32) DEFAULT NULL,
					billing_subject_id VARCHAR(64) DEFAULT NULL,
					description VARCHAR(255) DEFAULT NULL,
					created_by VARCHAR(25) DEFAULT NULL,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					INDEX idx_fl_txn (transaction_id),
					INDEX idx_fl_date (transaction_date),
					INDEX idx_fl_account (account_id),
					INDEX idx_fl_ref (reference_type, reference_no),
					INDEX idx_fl_patient (patient_no),
					INDEX idx_fl_subject (billing_subject_type, billing_subject_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}
		if ($this->db->table_exists('financial_ledger')) {
			$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($prev_debug !== null) { $this->db->db_debug = false; }
			try {
				if (!$this->db->field_exists('billing_subject_type', 'financial_ledger')) {
					$this->db->query("ALTER TABLE financial_ledger ADD COLUMN billing_subject_type VARCHAR(32) DEFAULT NULL");
				}
				if (!$this->db->field_exists('billing_subject_id', 'financial_ledger')) {
					$this->db->query("ALTER TABLE financial_ledger ADD COLUMN billing_subject_id VARCHAR(64) DEFAULT NULL");
				}
				try {
					if (!$this->index_exists('financial_ledger', 'idx_fl_subject')) {
						$this->db->query("CREATE INDEX idx_fl_subject ON financial_ledger (billing_subject_type, billing_subject_id)");
					}
				} catch (\Throwable $e) {
				}
			} catch (\Throwable $e) {
			}
			if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }
		}
		return true;
	}

	/**
	 * Ensure enhancements to existing billing tables
	 */
	private function _ensure_billing_enhancements()
	{
		// Add payment_status to iop_billing if not exists
		if ($this->db->table_exists('iop_billing')) {
			if (!$this->db->field_exists('payment_status', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN payment_status VARCHAR(20) DEFAULT 'UNPAID' AFTER total_amount");
			}
			if (!$this->db->field_exists('balance_due', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN balance_due DECIMAL(12,2) DEFAULT 0.00 AFTER payment_status");
			}
			if (!$this->db->field_exists('payer_type', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN payer_type VARCHAR(20) DEFAULT 'CASH' AFTER balance_due");
			}
			if (!$this->db->field_exists('billing_subject_type', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN billing_subject_type VARCHAR(32) DEFAULT NULL");
			}
			if (!$this->db->field_exists('billing_subject_id', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN billing_subject_id VARCHAR(64) DEFAULT NULL");
			}
			// Invoice immutability fields
			if (!$this->db->field_exists('is_locked', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN is_locked TINYINT(1) DEFAULT 0 COMMENT 'Prevents modification after finalization'");
			}
			if (!$this->db->field_exists('locked_at', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN locked_at DATETIME DEFAULT NULL");
			}
			if (!$this->db->field_exists('finalized_at', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN finalized_at DATETIME DEFAULT NULL COMMENT 'When invoice was made immutable'");
			}
			if (!$this->db->field_exists('invoice_status', 'iop_billing')) {
				$this->db->query("ALTER TABLE iop_billing ADD COLUMN invoice_status VARCHAR(20) DEFAULT 'DRAFT' COMMENT 'DRAFT, FINALIZED, VOID'");
			}
		}

		// Add cashier_id to iop_receipt if not exists
		if ($this->db->table_exists('iop_receipt')) {
			if (!$this->db->field_exists('cashier_id', 'iop_receipt')) {
				$this->db->query("ALTER TABLE iop_receipt ADD COLUMN cashier_id VARCHAR(25) DEFAULT NULL AFTER patient_no");
			}
			if (!$this->db->field_exists('billing_subject_type', 'iop_receipt')) {
				$this->db->query("ALTER TABLE iop_receipt ADD COLUMN billing_subject_type VARCHAR(32) DEFAULT NULL");
			}
			if (!$this->db->field_exists('billing_subject_id', 'iop_receipt')) {
				$this->db->query("ALTER TABLE iop_receipt ADD COLUMN billing_subject_id VARCHAR(64) DEFAULT NULL");
			}
		}
		
		// Add service gate fields to billing_queue if table exists but fields don't
		if ($this->db->table_exists('billing_queue')) {
			if (!$this->db->field_exists('service_gate_status', 'billing_queue')) {
				$this->db->query("ALTER TABLE billing_queue ADD COLUMN service_gate_status ENUM('BLOCKED','RELEASED','EXPIRED') DEFAULT 'BLOCKED'");
			}
			if (!$this->db->field_exists('released_at', 'billing_queue')) {
				$this->db->query("ALTER TABLE billing_queue ADD COLUMN released_at DATETIME DEFAULT NULL");
			}
			if (!$this->db->field_exists('released_by', 'billing_queue')) {
				$this->db->query("ALTER TABLE billing_queue ADD COLUMN released_by VARCHAR(25) DEFAULT NULL");
			}
		}

		return true;
	}

	/**
	 * Seed default chart of accounts
	 */
	private function _seed_default_accounts()
	{
		$accounts = array(
			// Assets (1xxx)
			array('1000', 'Cash on Hand', self::ACCT_ASSET, null, 'Physical cash'),
			array('1010', 'Bank Account', self::ACCT_ASSET, null, 'Bank deposits'),
			array('1100', 'Accounts Receivable', self::ACCT_ASSET, null, 'Patient receivables'),
			array('1110', 'NHIS Receivable', self::ACCT_ASSET, null, 'NHIS claims receivable'),
			array('1120', 'Insurance Receivable', self::ACCT_ASSET, null, 'Insurance claims receivable'),
			array('1130', 'Company Receivable', self::ACCT_ASSET, null, 'Corporate account receivables'),
			
			// Liabilities (2xxx)
			array('2000', 'Accounts Payable', self::ACCT_LIABILITY, null, 'Amounts owed to suppliers'),
			array('2100', 'Patient Deposits', self::ACCT_LIABILITY, null, 'Advance payments from patients'),
			
			// Revenue (4xxx)
			array('4000', 'Medical Revenue', self::ACCT_REVENUE, null, 'General medical revenue'),
			array('4100', 'Consultation Revenue', self::ACCT_REVENUE, null, 'Doctor consultation fees'),
			array('4110', 'Registration Revenue', self::ACCT_REVENUE, null, 'Patient registration fees'),
			array('4200', 'Laboratory Revenue', self::ACCT_REVENUE, null, 'Lab test revenue'),
			array('4300', 'Pharmacy Revenue', self::ACCT_REVENUE, null, 'Medication sales'),
			array('4400', 'Imaging Revenue', self::ACCT_REVENUE, null, 'X-ray, Ultrasound, CT revenue'),
			array('4500', 'Procedure Revenue', self::ACCT_REVENUE, null, 'Medical procedures'),
			array('4600', 'Admission Revenue', self::ACCT_REVENUE, null, 'Room and admission charges'),
			array('4700', 'Surgery Revenue', self::ACCT_REVENUE, null, 'Surgical procedures'),
			
			// Expenses (5xxx)
			array('5000', 'Cost of Goods Sold', self::ACCT_EXPENSE, null, 'Direct costs'),
			array('5100', 'Pharmacy COGS', self::ACCT_EXPENSE, null, 'Cost of medications sold'),
		);

		foreach ($accounts as $acct) {
			$this->db->where('account_code', $acct[0]);
			if (!$this->db->get('chart_of_accounts')->row()) {
				$this->db->insert('chart_of_accounts', array(
					'account_code' => $acct[0],
					'account_name' => $acct[1],
					'account_type' => $acct[2],
					'parent_id' => $acct[3],
					'description' => $acct[4],
					'is_system' => 1,
					'is_active' => 1
				));
			}
		}
		return true;
	}

	/* ================================================================== */
	/*  BILLING QUEUE OPERATIONS                                          */
	/* ================================================================== */

	/**
	 * Add item to billing queue
	 * All billable services must go through this function
	 * 
	 * @param array $data Item data
	 * @return array Result with queue_id or error
	 */
	public function add_to_billing_queue($data)
	{
		$this->ensure_unified_billing_schema();
		$this->load->helper('quantity_semantics');

		$required = array('iop_id', 'patient_no', 'item_type', 'item_id', 'item_name', 'unit_price');
		foreach ($required as $field) {
			if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
				return array('success' => false, 'error' => "Missing required field: $field");
			}
		}

		try {
			$item_type_norm = strtoupper(trim((string)$data['item_type']));
			if ($item_type_norm === 'SONOGRAPHY' && isset($data['source_ref']) && isset($data['source_module'])) {
				$sr = trim((string)$data['source_ref']);
				$sm = strtoupper(trim((string)$data['source_module']));
				if ($sr !== '' && ctype_digit($sr) && ($sm === 'SONOGRAPHY' || $sm === 'SONOGRAPHY' || $sm === 'IMAGING')) {
					$sr_i = (int)$sr;
					$resolved_charge_id = 0;
					if ($this->table_exists('iop_sonography_charge')) {
						$this->db->select('charge_id');
						$this->db->from('iop_sonography_charge');
						$this->db->where('InActive', 0);
						$this->db->group_start();
						$this->db->where('charge_id', $sr_i);
						$this->db->or_where('io_lab_id', $sr_i);
						$this->db->group_end();
						if (isset($data['iop_id']) && trim((string)$data['iop_id']) !== '' && $this->field_exists_cached('iop_id', 'iop_sonography_charge')) {
							$this->db->where('iop_id', (string)$data['iop_id']);
						}
						if (isset($data['patient_no']) && trim((string)$data['patient_no']) !== '' && $this->field_exists_cached('patient_no', 'iop_sonography_charge')) {
							$this->db->where('patient_no', (string)$data['patient_no']);
						}
						$this->db->order_by('charge_id', 'DESC');
						$this->db->limit(1);
						$row = $this->db->get()->row();
						if ($row && isset($row->charge_id) && (int)$row->charge_id > 0) {
							$resolved_charge_id = (int)$row->charge_id;
						}
					}
					if ($resolved_charge_id > 0) {
						$data['source_ref'] = 'sono_charge_id:' . (int)$resolved_charge_id;
					}
				}
			}
		} catch (\Throwable $e) {
		}

		$item_type = strtoupper(trim((string)$data['item_type']));
		$item_id_i = (int)$data['item_id'];
		$patient_no = isset($data['patient_no']) ? (string)$data['patient_no'] : '';

		if ($item_type === 'PHARMACY') {
			try {
				$drug_id = 0;
				$resolved_name = '';
				$substitution_flag = 0;
				if ($item_id_i > 0 && $this->db->table_exists('iop_medication')) {
					$rxSelect = 'medicine_id, medicine_text';
					if ($this->db->field_exists('original_medicine_id', 'iop_medication')) { $rxSelect .= ', original_medicine_id'; }
					if ($this->db->field_exists('substituted_medicine_id', 'iop_medication')) { $rxSelect .= ', substituted_medicine_id'; }
					$this->db->select($rxSelect, false);
					$this->db->where('iop_med_id', $item_id_i);
					$this->db->where('InActive', 0);
					$rx = $this->db->get('iop_medication')->row();
					if ($rx) {
						$drug_id = isset($rx->medicine_id) ? (int)$rx->medicine_id : 0;
						$resolved_name = isset($rx->medicine_text) ? trim((string)$rx->medicine_text) : '';
						$substitution_flag = (!empty($rx->original_medicine_id) || !empty($rx->substituted_medicine_id)) ? 1 : 0;
					}
				}
				if ($drug_id <= 0 && $item_id_i > 0) {
					$drug_id = $item_id_i;
					if ($resolved_name === '' && $this->db->table_exists('medicine_drug_name')) {
						$this->db->select('drug_name', false);
						$this->db->where('drug_id', $drug_id);
						if ($this->db->field_exists('InActive', 'medicine_drug_name')) {
							$this->db->where('InActive', 0);
						}
						$dr = $this->db->get('medicine_drug_name')->row();
						if ($dr && isset($dr->drug_name) && trim((string)$dr->drug_name) !== '') {
							$resolved_name = trim((string)$dr->drug_name);
						}
					}
				}
				if ($drug_id > 0) {
					$this->load->model('app/Price_engine_model', 'price_engine');
					if (isset($this->price_engine) && method_exists($this->price_engine, 'resolve')) {
						$res = $this->price_engine->resolve(array(
							'item_type' => 'PHARMACY',
							'item_id' => $drug_id,
							'quantity' => isset($data['quantity']) ? (float)$data['quantity'] : 1.0,
							'patient_no' => $patient_no,
							'payer_type' => isset($data['payer_type']) ? (string)$data['payer_type'] : '',
							'submitted_unit_price' => isset($data['unit_price']) ? (float)$data['unit_price'] : null,
							'require_positive_price' => true,
							'context' => array(
								'source_module' => isset($data['source_module']) ? (string)$data['source_module'] : 'PHARMACY',
								'source_ref' => isset($data['source_ref']) ? (string)$data['source_ref'] : '',
							),
						));
						if (is_array($res) && !empty($res['ok'])) {
							$data['unit_price'] = isset($res['unit_price']) ? (float)$res['unit_price'] : (float)$data['unit_price'];
							if (isset($res['payer_type']) && trim((string)$res['payer_type']) !== '') {
								$data['payer_type'] = (string)$res['payer_type'];
							}
							if ((!isset($data['item_name']) || trim((string)$data['item_name']) === '' || (string)$data['item_name'] === 'Medication') && isset($res['item_name']) && trim((string)$res['item_name']) !== '') {
								$data['item_name'] = (string)$res['item_name'];
							} elseif ((string)$data['item_name'] === 'Medication' && $resolved_name !== '') {
								$data['item_name'] = $resolved_name;
							}
							$data['pricing_source'] = isset($res['price_source']) ? (string)$res['price_source'] : null;
							$data['pricing_source_id'] = isset($res['pricing_source_id']) ? (string)$res['pricing_source_id'] : (isset($res['source_id']) ? (string)$res['source_id'] : null);
							$data['resolved_drug_id'] = $drug_id;
							$data['resolved_stock_id'] = isset($res['resolved_stock_id']) ? $res['resolved_stock_id'] : null;
							if (!isset($data['substitution_flag'])) {
								$data['substitution_flag'] = $substitution_flag;
							}
						} else {
							$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'PRICE_NOT_FOUND';
							log_message('error', 'UNIFIED_BILLING_PHARMACY_PRICE_FAILED item_id=' . (string)$data['item_id'] . ' drug_id=' . $drug_id . ' err=' . $err);
							return array('success' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: ' . (string)$drug_id . '. Workflow blocked to prevent zero-value billing.');
						}
					}
				} else {
					return array('success' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: 0. Workflow blocked to prevent zero-value billing.');
				}
			} catch (\Throwable $e) {
				log_message('error', 'UNIFIED_BILLING_PHARMACY_PRICE_EXCEPTION item_id=' . (string)$data['item_id'] . ' err=' . $e->getMessage());
				return array('success' => false, 'error' => 'Pharmacy pricing resolution failed: ' . $e->getMessage());
			}
		}
		if ($item_type === 'LAB') {
			try {
				if (isset($data['unit_price']) && (float)$data['unit_price'] <= 0.009) {
					$catalog_id = isset($data['item_id']) ? (int)$data['item_id'] : 0;
					if ($catalog_id > 0) {
						$this->load->model('app/Price_engine_model', 'price_engine');
						if (isset($this->price_engine) && method_exists($this->price_engine, 'resolve')) {
							$res = $this->price_engine->resolve(array(
								'item_type' => 'LAB',
								'item_id' => $catalog_id,
								'quantity' => isset($data['quantity']) ? (float)$data['quantity'] : 1.0,
								'patient_no' => $patient_no,
								'payer_type' => isset($data['payer_type']) ? (string)$data['payer_type'] : '',
								'submitted_unit_price' => isset($data['unit_price']) ? (float)$data['unit_price'] : null,
							));
							if (is_array($res) && !empty($res['ok']) && isset($res['unit_price']) && (float)$res['unit_price'] > 0.009) {
								$data['unit_price'] = (float)$res['unit_price'];
								if (isset($res['payer_type']) && trim((string)$res['payer_type']) !== '') {
									$data['payer_type'] = (string)$res['payer_type'];
								}
								if ((!isset($data['item_name']) || trim((string)$data['item_name']) === '' || (string)$data['item_name'] === 'Laboratory Test') && isset($res['item_name']) && trim((string)$res['item_name']) !== '') {
									$data['item_name'] = (string)$res['item_name'];
								}
							}
						}
					}
				}
			} catch (\Throwable $e) {
			}
		}

		// Check for duplicate (same source_module + source_ref)
		if ($item_type === 'PHARMACY' && (float)$data['unit_price'] <= 0.009 && empty($data['allow_zero_price'])) {
			log_message('error', 'UNIFIED_BILLING_PHARMACY_ZERO_PRICE_BLOCKED item_id=' . (string)$data['item_id'] . ' source_ref=' . (isset($data['source_ref']) ? (string)$data['source_ref'] : ''));
			return array('success' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: ' . (isset($data['resolved_drug_id']) ? (string)(int)$data['resolved_drug_id'] : (string)(int)$data['item_id']) . '. Workflow blocked to prevent zero-value billing.');
		}

		// Check for duplicate (same source_module + source_ref)
		if (isset($data['source_module']) && isset($data['source_ref'])) {
			$this->db->where('source_module', $data['source_module']);
			$this->db->where('source_ref', $data['source_ref']);
			$this->db->where('InActive', 0);
			$existing = $this->db->get('billing_queue')->row();
			if ($existing) {
				$existing_id = isset($existing->queue_id) ? (int)$existing->queue_id : 0;
				$existing_status = isset($existing->status) ? strtoupper(trim((string)$existing->status)) : '';
				if ($existing_id > 0 && ($existing_status === '' || $existing_status === self::STATUS_PENDING)) {
					$qty_u = isset($data['quantity']) ? (float)$data['quantity'] : (isset($existing->quantity) ? (float)$existing->quantity : 1.0);
					if ($qty_u <= 0) { $qty_u = 1.0; }
					$unit_u = isset($data['unit_price']) ? (float)$data['unit_price'] : (isset($existing->unit_price) ? (float)$existing->unit_price : 0.0);
					$disc_u = isset($data['discount_amount']) ? (float)$data['discount_amount'] : (isset($existing->discount_amount) ? (float)$existing->discount_amount : 0.0);
					$total_u = $qty_u * $unit_u;
					$net_u = $total_u - $disc_u;
					$cov_u = isset($data['coverage_amount']) ? (float)$data['coverage_amount'] : (isset($existing->coverage_amount) ? (float)$existing->coverage_amount : 0.0);
					$pat_u = $net_u - $cov_u;

					$upd = array(
						'quantity' => $qty_u,
						'unit_price' => $unit_u,
						'total_amount' => $total_u,
						'discount_amount' => $disc_u,
						'net_amount' => $net_u,
						'coverage_amount' => $cov_u,
						'patient_amount' => $pat_u,
					);
					if ($this->column_exists('billing_queue', 'quantity_semantics_version')) {
						$sem = qs_flag_enabled('ENABLE_DECIMAL_INVOICE_QTY', false) ? qs_decimal_semantics_version() : qs_default_semantics_version();
						$upd['quantity_semantics_version'] = (int)$sem;
					}
					if (isset($data['payer_type']) && trim((string)$data['payer_type']) !== '') {
						$upd['payer_type'] = strtoupper(trim((string)$data['payer_type']));
					}
					if (isset($data['item_name']) && trim((string)$data['item_name']) !== '') {
						$upd['item_name'] = (string)$data['item_name'];
					}
					foreach (array('pricing_source','pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag') as $_prov_col) {
						if ($this->column_exists('billing_queue', $_prov_col) && array_key_exists($_prov_col, $data)) {
							$upd[$_prov_col] = $data[$_prov_col];
						}
					}
					if ($this->column_exists('billing_queue', 'updated_at')) {
						$upd['updated_at'] = date('Y-m-d H:i:s');
					}
					$this->db->where('queue_id', $existing_id);
					$this->db->update('billing_queue', $upd);
					return array('success' => true, 'queue_id' => $existing_id);
				}
				return array('success' => false, 'error' => 'Item already in billing queue');
			}
		}

		$quantity = isset($data['quantity']) ? (float)$data['quantity'] : 1;
		$unit_price = (float)$data['unit_price'];
		$discount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0;
		$total = $quantity * $unit_price;
		$net = $total - $discount;

		$payer_type = isset($data['payer_type']) ? strtoupper(trim((string)$data['payer_type'])) : 'CASH';
		if ($payer_type === '' || $payer_type === 'CASH' || $payer_type === 'NHIS') {
			try {
				$this->load->model('app/billing_model');
				if (isset($data['patient_no']) && trim((string)$data['patient_no']) !== '' && isset($this->billing_model) && method_exists($this->billing_model, 'determine_payer_type')) {
					$pt = strtoupper(trim((string)$this->billing_model->determine_payer_type((string)$data['patient_no'])));
					if ($pt === 'NHIS') {
						$payer_type = 'NHIS';
					} elseif ($pt === 'CASH') {
						$payer_type = 'CASH';
					}
				}
			} catch (\Throwable $e) {
			}
		}
		$coverage = isset($data['coverage_amount']) ? (float)$data['coverage_amount'] : 0;
		$patient_amount = $net - $coverage;

		$now = date('Y-m-d H:i:s');
		$insert = array(
			'iop_id' => $data['iop_id'],
			'patient_no' => $data['patient_no'],
			'item_type' => $data['item_type'],
			'item_id' => $data['item_id'],
			'item_name' => $data['item_name'],
			'quantity' => $quantity,
			'unit_price' => $unit_price,
			'total_amount' => $total,
			'discount_amount' => $discount,
			'net_amount' => $net,
			'payer_type' => $payer_type,
			'coverage_amount' => $coverage,
			'patient_amount' => $patient_amount,
			'status' => self::STATUS_PENDING,
			'source_module' => isset($data['source_module']) ? $data['source_module'] : null,
			'source_ref' => isset($data['source_ref']) ? $data['source_ref'] : null,
			'requested_by' => isset($data['requested_by']) ? $data['requested_by'] : null,
			'requested_at' => $now,
			'notes' => isset($data['notes']) ? $data['notes'] : null,
			'InActive' => 0
		);
		if ($this->column_exists('billing_queue', 'quantity_semantics_version')) {
			$sem = qs_flag_enabled('ENABLE_DECIMAL_INVOICE_QTY', false) ? qs_decimal_semantics_version() : qs_default_semantics_version();
			$insert['quantity_semantics_version'] = (int)$sem;
		}
		if ($this->column_exists('billing_queue', 'billing_subject_type') && isset($data['billing_subject_type']) && trim((string)$data['billing_subject_type']) !== '') {
			$insert['billing_subject_type'] = strtoupper(trim((string)$data['billing_subject_type']));
		}
		if ($this->column_exists('billing_queue', 'billing_subject_id') && isset($data['billing_subject_id']) && trim((string)$data['billing_subject_id']) !== '') {
			$insert['billing_subject_id'] = trim((string)$data['billing_subject_id']);
		}
		if ($this->column_exists('billing_queue', 'idempotency_key') && isset($data['idempotency_key']) && trim((string)$data['idempotency_key']) !== '') {
			$insert['idempotency_key'] = trim((string)$data['idempotency_key']);
		}
		foreach (array('pricing_source','pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag') as $_prov_col) {
			if ($this->column_exists('billing_queue', $_prov_col) && array_key_exists($_prov_col, $data)) {
				$insert[$_prov_col] = $data[$_prov_col];
			}
		}
		if ($this->column_exists('billing_queue', 'created_at')) {
			$insert['created_at'] = $now;
		}
		if ($this->column_exists('billing_queue', 'updated_at')) {
			$insert['updated_at'] = $now;
		}

		$ok = $this->db->insert('billing_queue', $insert);
		if (!$ok) {
			$err = $this->db->error();
			$msg = (isset($err['message']) && $err['message'] !== '') ? (string)$err['message'] : 'Insert failed';
			log_message('error', 'Unified Billing: add_to_billing_queue insert failed: ' . $msg);
			return array('success' => false, 'error' => $msg);
		}
		$queue_id = $this->db->insert_id();

		return array('success' => true, 'queue_id' => $queue_id);
	}

	/**
	 * Get pending items in billing queue for a patient/visit
	 */
	public function get_billing_queue($iop_id = null, $patient_no = null, $status = 'PENDING', $opts = array())
	{
		$runGlobalBackfills = true;
		if (is_array($opts) && array_key_exists('run_global_backfills', $opts)) {
			$runGlobalBackfills = (bool)$opts['run_global_backfills'];
		}
		if ($runGlobalBackfills) {
			$this->backfill_pending_sonography_charges_to_queue();
			$this->backfill_pending_lab_transactions_to_queue();
			$this->backfill_pending_opd_visit_fee_transactions_to_queue();
		}
		if ($iop_id && $patient_no) {
			try {
				$this->backfill_visit_fees_for_queue((string)$iop_id, (string)$patient_no);
				$this->backfill_pending_transactions_to_queue((string)$iop_id, (string)$patient_no);
				$this->backfill_verified_prescriptions_to_queue((string)$iop_id, (string)$patient_no);
				$this->_self_heal_pharmacy_queue_for_visit((string)$iop_id, (string)$patient_no);
			} catch (\Throwable $e) {
			}
		}
		$this->db->where('InActive', 0);
		if ($iop_id) $this->db->where('iop_id', $iop_id);
		if ($patient_no) $this->db->where('patient_no', $patient_no);
		if ($status) $this->db->where('status', $status);
		$this->db->order_by('created_at', 'ASC');
		return $this->db->get('billing_queue')->result();
	}

	public function backfill_pending_opd_visit_fee_transactions_to_queue($limit = 200)
	{
		try {
			$limit = (int)$limit;
			if ($limit <= 0) { $limit = 200; }

			if (schema_already_run('billing_core_tables_schema')) {
			} else {
				if (!$this->db->table_exists('billing_queue') || !$this->db->table_exists('billing_transactions')) {
					return 0;
				}
				mark_schema_run('billing_core_tables_schema');
			}

			$sql = "
				SELECT bt.*
				FROM billing_transactions bt
				LEFT JOIN billing_queue q
					ON q.InActive = 0
					AND (
						q.source_ref = CONCAT('txn_id:', bt.txn_id)
						OR (bt.item_ref IS NOT NULL AND bt.item_ref <> '' AND q.source_ref = bt.item_ref)
					)
				WHERE bt.InActive = 0
				  AND bt.department = 'OPD'
				  AND bt.item_ref IS NOT NULL
				  AND bt.item_ref <> ''
				  AND (
					bt.item_ref LIKE 'visit_registration:%'
					OR bt.item_ref LIKE 'visit_consultation:%'
				  )
				  AND (bt.invoice_no IS NULL OR bt.invoice_no = '')
				  AND UPPER(COALESCE(bt.payment_status,'')) NOT IN ('CANCELLED','PAID')
				  AND q.queue_id IS NULL
				ORDER BY bt.txn_id DESC
				LIMIT " . (int)$limit;

			$rows = $this->db->query($sql)->result();
			if (!$rows) { return 0; }

			$inserted = 0;
			foreach ($rows as $bt) {
				$mapped = $this->_map_billing_transaction_to_queue_item($bt);
				if (!$mapped) { continue; }
				$res = $this->add_to_billing_queue($mapped);
				if ($res && isset($res['success']) && $res['success']) {
					$inserted++;
				}
			}
			return $inserted;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	public function backfill_verified_prescriptions_to_queue($iop_id, $patient_no, $limit = 200)
	{
		try {
			$this->load->helper('quantity_semantics');
			$iop_id = (string)$iop_id;
			$patient_no = (string)$patient_no;
			$limit = (int)$limit;
			if ($limit <= 0) { $limit = 200; }
			if ($iop_id === '' || $patient_no === '') { return 0; }
			if (schema_already_run('billing_core_tables_schema')) {
			} else {
				if (!$this->db->table_exists('billing_queue') || !$this->db->table_exists('iop_medication')) { return 0; }
				mark_schema_run('billing_core_tables_schema');
			}

			$has_status = $this->column_exists('iop_medication', 'prescription_status');
			if (!$has_status) { return 0; }

			$srPrefix = 'iop_id:' . $iop_id . ':iop_medication:';
			$sel = 'm.iop_med_id, m.medicine_id, m.medicine_text, m.total_qty, m.prescription_status';
			if ($this->column_exists('iop_medication', 'prescribed_qty')) {
				$sel .= ', m.prescribed_qty';
			}
			$this->db->select($sel, false);
			$this->db->from('iop_medication m');
			$has_patient_no = $this->column_exists('iop_medication', 'patient_no');
			if ($has_patient_no) {
				$this->db->where('m.patient_no', $patient_no);
			} else {
				if (schema_already_run('billing_core_tables_schema') && $this->db->table_exists('patient_details_iop')) {
					$this->db->join('patient_details_iop i', 'i.IO_ID = m.iop_id AND i.InActive = 0', 'left');
					$this->db->where('i.patient_no', $patient_no);
				}
			}
			$this->db->join('billing_queue q', "q.source_module = 'PHARMACY' AND q.source_ref = CONCAT('" . $srPrefix . "', m.iop_med_id) AND q.InActive = 0", 'left', false);
			$this->db->where('m.InActive', 0);
			$this->db->where('m.iop_id', $iop_id);
			$this->db->where('m.prescription_status', 'VERIFIED');
			$this->db->where('q.queue_id IS NULL', null, false);
			$this->db->order_by('m.iop_med_id', 'DESC');
			$this->db->limit($limit);
			$rows = $this->db->get()->result();
			if (!$rows) { return 0; }

			$payer_type = 'CASH';
			try {
				$this->load->model('app/billing_model');
				if (isset($this->billing_model) && method_exists($this->billing_model, 'determine_payer_type')) {
					$payer_type = (string)$this->billing_model->determine_payer_type($patient_no);
				}
			} catch (\Throwable $e) {
			}

			$inserted = 0;
			foreach ($rows as $rx) {
				$iop_med_id = isset($rx->iop_med_id) ? (int)$rx->iop_med_id : 0;
				if ($iop_med_id <= 0) { continue; }
				$name = isset($rx->medicine_text) ? trim((string)$rx->medicine_text) : '';
				if ($name === '') { $name = 'Medication'; }
				$qty = qs_pick_prescribed_qty($rx, 1.0);
				if ($qty <= 0) { $qty = 1.0; }
				$res = $this->add_to_billing_queue(array(
					'iop_id' => $iop_id,
					'patient_no' => $patient_no,
					'item_type' => 'PHARMACY',
					'item_id' => (string)$iop_med_id,
					'item_name' => $name,
					'quantity' => $qty,
					'unit_price' => 0,
					'payer_type' => $payer_type !== '' ? (string)$payer_type : 'CASH',
					'source_module' => 'PHARMACY',
					'source_ref' => $srPrefix . (string)$iop_med_id,
				));
				if ($res && isset($res['success']) && $res['success']) {
					$inserted++;
				}
			}
			return $inserted;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	public function backfill_pending_lab_transactions_to_queue($limit = 200)
	{
		try {
			$limit = (int)$limit;
			if ($limit <= 0) { $limit = 200; }
			if (schema_already_run('billing_core_tables_schema')) {
			} else {
				if (!$this->db->table_exists('billing_queue') || !$this->db->table_exists('billing_transactions')) {
					return 0;
				}
				mark_schema_run('billing_core_tables_schema');
			}

			$this->db->select('bt.txn_id, bt.encounter_id, bt.patient_no, bt.item_id, bt.item_name, bt.quantity, bt.unit_price, bt.payer_type, bt.created_by');
			$this->db->from('billing_transactions bt');
			$this->db->join('billing_queue q', "q.source_module = 'LAB' AND (q.source_ref = CONCAT('txn_id:', bt.txn_id) OR (bt.item_ref IS NOT NULL AND bt.item_ref <> '' AND q.source_ref = bt.item_ref)) AND q.InActive = 0", 'left', false);
			$this->db->where('bt.InActive', 0);
			$this->db->where('bt.department', 'LABORATORY');
			$this->db->group_start();
			$this->db->where('bt.invoice_no IS NULL', null, false);
			$this->db->or_where('bt.invoice_no', '');
			$this->db->group_end();
			$this->db->where_not_in('bt.payment_status', array('CANCELLED'));
			$this->db->where('q.queue_id IS NULL', null, false);
			$this->db->order_by('bt.txn_id', 'DESC');
			$this->db->limit($limit);
			$rows = $this->db->get()->result();
			if (!$rows) {
				return 0;
			}

			$inserted = 0;
			foreach ($rows as $r) {
				$txnId = isset($r->txn_id) ? (int)$r->txn_id : 0;
				$visitId = isset($r->encounter_id) ? (string)$r->encounter_id : '';
				$patientNo = isset($r->patient_no) ? (string)$r->patient_no : '';
				if ($txnId <= 0 || $visitId === '' || $patientNo === '') {
					continue;
				}
				$itemName = isset($r->item_name) ? trim((string)$r->item_name) : '';
				if ($itemName === '') { $itemName = 'Laboratory Test'; }
				$catalogId = isset($r->item_id) ? (int)$r->item_id : 0;
				if ($catalogId <= 0) { $catalogId = $txnId; }
				$qty = isset($r->quantity) ? (float)$r->quantity : 1.0;
				if ($qty <= 0) { $qty = 1.0; }
				$unit = isset($r->unit_price) ? (float)$r->unit_price : 0.0;
				$payer = isset($r->payer_type) ? strtoupper(trim((string)$r->payer_type)) : 'CASH';
				$reqBy = isset($r->created_by) ? (string)$r->created_by : null;

				if ($unit <= 0.009) {
					try {
						$this->load->model('app/billing_transaction_model');
						$rp = $this->billing_transaction_model->reprice_pending_transaction_if_zero($txnId, $reqBy);
						if (is_array($rp) && !empty($rp['ok']) && isset($rp['unit_price'])) {
							$unit = (float)$rp['unit_price'];
						}
					} catch (\Throwable $e) {
					}
				}

				$res = $this->add_to_billing_queue(array(
					'iop_id' => $visitId,
					'patient_no' => $patientNo,
					'item_type' => 'LAB',
					'item_id' => (string)$catalogId,
					'item_name' => $itemName,
					'quantity' => $qty,
					'unit_price' => $unit,
					'payer_type' => $payer !== '' ? $payer : 'CASH',
					'source_module' => 'LAB',
					'source_ref' => 'txn_id:' . $txnId,
					'requested_by' => $reqBy,
				));
				if ($res && isset($res['success']) && $res['success']) {
					$inserted++;
				}
			}
			return $inserted;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	public function backfill_visit_fees_for_queue($iop_id, $patient_no)
	{
		try {
			$iop_id = trim((string)$iop_id);
			$patient_no = trim((string)$patient_no);
			if ($iop_id === '' || $patient_no === '') {
				return 0;
			}
			if (schema_already_run('billing_core_tables_schema')) {
			} else {
				if (!$this->db->table_exists('billing_transactions')) {
					return 0;
				}
				mark_schema_run('billing_core_tables_schema');
			}

			// Only run auto-bill if required visit fee transaction(s) are missing.
			// Avoid the previous "any OPD transaction exists" early return, which could
			// leave the system in a partial state (e.g., registration exists but
			// consultation missing).
			$refs = array('visit_registration:' . $iop_id, 'visit_consultation:' . $iop_id);
			$this->db->select('item_ref');
			$this->db->where('InActive', 0);
			$this->db->where('encounter_id', $iop_id);
			$this->db->where('patient_no', $patient_no);
			$this->db->where('department', 'OPD');
			$this->db->where_in('item_ref', $refs);
			$rows = $this->db->get('billing_transactions')->result();
			$seen = array();
			if ($rows) {
				foreach ($rows as $r) {
					$k = isset($r->item_ref) ? trim((string)$r->item_ref) : '';
					if ($k !== '') { $seen[$k] = true; }
				}
			}
			if (isset($seen[$refs[0]]) && isset($seen[$refs[1]])) {
				return 0;
			}

			$this->load->model('app/visit_billing_resolver_model');
			if (!isset($this->visit_billing_resolver_model) || !method_exists($this->visit_billing_resolver_model, 'auto_bill_visit_fees')) {
				return 0;
			}
			$res = $this->visit_billing_resolver_model->auto_bill_visit_fees($iop_id, $patient_no, null);
			return (is_array($res) && isset($res['created']) && is_array($res['created'])) ? count($res['created']) : 0;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	public function backfill_pending_transactions_to_queue($iop_id, $patient_no, $limit = 200)
	{
		try {
			$iop_id = trim((string)$iop_id);
			$patient_no = trim((string)$patient_no);
			$limit = (int)$limit;
			if ($limit <= 0) { $limit = 200; }
			if ($iop_id === '' || $patient_no === '') { return 0; }
			if (schema_already_run('billing_core_tables_schema')) {
			} else {
				if (!$this->db->table_exists('billing_queue') || !$this->db->table_exists('billing_transactions')) {
					return 0;
				}
				mark_schema_run('billing_core_tables_schema');
			}

			try {
				if ($this->db->table_exists('iop_room_charge')) {
					$this->load->model('app/billing_transaction_model');
					$this->billing_transaction_model->ensure_billing_transaction_schema();
					$this->db->select('charge_id');
					$this->db->where(array('iop_id' => $iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
					if ($this->db->field_exists('status', 'iop_room_charge')) {
						$this->db->where('status', 'PENDING');
					}
					$this->db->group_start();
					$this->db->where('invoice_no IS NULL', null, false);
					$this->db->or_where('invoice_no', '');
					$this->db->group_end();
					$pending = $this->db->get('iop_room_charge')->result();
					if ($pending && isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_ipd_room_charge')) {
						foreach ($pending as $p) {
							$cid = isset($p->charge_id) ? (int)$p->charge_id : 0;
							if ($cid > 0) {
								$this->billing_transaction_model->sync_ipd_room_charge($cid, null);
							}
						}
					}
				}
			} catch (\Throwable $e) {
				log_message('error', 'Unified Billing: room charge reconcile failed iop_id=' . $iop_id . ' patient_no=' . $patient_no . ': ' . $e->getMessage());
			}

			$sql = "
				SELECT bt.*
				FROM billing_transactions bt
				LEFT JOIN billing_queue q
					ON q.InActive = 0
					AND (
						q.source_ref = CONCAT('txn_id:', bt.txn_id)
						OR (bt.item_ref IS NOT NULL AND bt.item_ref <> '' AND q.source_ref = bt.item_ref)
					)
				WHERE bt.InActive = 0
				  AND bt.encounter_id = ?
				  AND bt.patient_no = ?
				  AND (bt.invoice_no IS NULL OR bt.invoice_no = '')
				  AND UPPER(COALESCE(bt.payment_status,'')) NOT IN ('CANCELLED','PAID')
				  AND q.queue_id IS NULL
				ORDER BY bt.txn_id ASC
				LIMIT " . (int)$limit;
			$rows = $this->db->query($sql, array($iop_id, $patient_no))->result();
			if (!$rows) { return 0; }

			$inserted = 0;
			foreach ($rows as $bt) {
				$mapped = $this->_map_billing_transaction_to_queue_item($bt);
				if (!$mapped) { continue; }
				$res = $this->add_to_billing_queue($mapped);
				if ($res && isset($res['success']) && $res['success']) {
					$inserted++;
				}
			}
			return $inserted;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	private function _map_billing_transaction_to_queue_item($bt)
	{
		$txnId = isset($bt->txn_id) ? (int)$bt->txn_id : 0;
		$iopId = isset($bt->encounter_id) ? trim((string)$bt->encounter_id) : '';
		$patientNo = isset($bt->patient_no) ? trim((string)$bt->patient_no) : '';
		if ($txnId <= 0 || $iopId === '' || $patientNo === '') {
			return null;
		}

		$itemRef = isset($bt->item_ref) ? trim((string)$bt->item_ref) : '';
		$dept = isset($bt->department) ? strtoupper(trim((string)$bt->department)) : '';
		$itemType = 'OTHER';
		$sourceModule = $dept !== '' ? $dept : 'BILLING';
		if ($dept === 'LABORATORY') {
			$itemType = 'LAB';
			$sourceModule = 'LAB';
		} elseif ($dept === 'IMAGING') {
			$itemType = (strpos($itemRef, 'radiology_order_id:') === 0) ? 'RADIOLOGY' : 'SONOGRAPHY';
			$sourceModule = $itemType;
		} elseif ($dept === 'OPD') {
			if (strpos($itemRef, 'visit_registration:') === 0) {
				$itemType = 'REGISTRATION';
				$sourceModule = 'REGISTRATION';
			} elseif (strpos($itemRef, 'visit_consultation:') === 0) {
				$itemType = 'CONSULTATION';
				$sourceModule = 'CONSULTATION';
			} else {
				$itemType = 'CONSULTATION';
				$sourceModule = 'OPD';
			}
		} elseif ($dept === 'PHARMACY') {
			$itemType = 'PHARMACY';
			$sourceModule = 'PHARMACY';
		}

		$itemId = isset($bt->item_id) ? (int)$bt->item_id : 0;
		$itemName = isset($bt->item_name) ? trim((string)$bt->item_name) : '';
		$unit = isset($bt->unit_price) ? (float)$bt->unit_price : 0.0;
		$payer = isset($bt->payer_type) ? strtoupper(trim((string)$bt->payer_type)) : 'CASH';
		$qty = isset($bt->quantity) ? (float)$bt->quantity : 1.0;
		if ($qty <= 0) { $qty = 1.0; }

		if ($itemType === 'RADIOLOGY' && strpos($itemRef, 'radiology_order_id:') === 0) {
			$rad = $this->_resolve_radiology_order_for_queue((int)substr($itemRef, strlen('radiology_order_id:')), $patientNo, $payer);
			if ($rad) {
				$itemId = (int)$rad['item_id'];
				$itemName = (string)$rad['item_name'];
				$unit = (float)$rad['unit_price'];
				$this->_update_pending_transaction_price($txnId, $itemName, $unit, $qty, $payer);
			}
		} elseif (($itemType === 'LAB' || $itemType === 'PHARMACY' || $itemType === 'SONOGRAPHY') && $unit <= 0.009) {
			try {
				$this->load->model('app/billing_transaction_model');
				$rp = $this->billing_transaction_model->reprice_pending_transaction_if_zero($txnId, null);
				if (is_array($rp) && !empty($rp['ok']) && isset($rp['unit_price']) && (float)$rp['unit_price'] > 0.009) {
					$unit = (float)$rp['unit_price'];
					if (isset($rp['item_name']) && trim((string)$rp['item_name']) !== '') {
						$itemName = (string)$rp['item_name'];
					}
					if (isset($rp['payer_type']) && trim((string)$rp['payer_type']) !== '') {
						$payer = strtoupper(trim((string)$rp['payer_type']));
					}
				}
			} catch (\Throwable $e) {
			}
		}

		if ($itemName === '') {
			$itemName = $itemType;
		}
		if ($itemId <= 0) {
			$itemId = $txnId;
		}

		return array(
			'iop_id' => $iopId,
			'patient_no' => $patientNo,
			'item_type' => $itemType,
			'item_id' => (string)$itemId,
			'item_name' => $itemName,
			'quantity' => $qty,
			'unit_price' => $unit,
			'payer_type' => $payer !== '' ? $payer : 'CASH',
			'source_module' => $sourceModule,
			'source_ref' => $itemRef !== '' ? $itemRef : ('txn_id:' . $txnId),
			'requested_by' => isset($bt->created_by) ? (string)$bt->created_by : null,
		);
	}

	private function _resolve_radiology_order_for_queue($order_id, $patient_no, $payer_type)
	{
		$order_id = (int)$order_id;
		if ($order_id <= 0 || !$this->db->table_exists('radiology_orders')) {
			return null;
		}
		$ord = $this->db->get_where('radiology_orders', array('id' => $order_id, 'InActive' => 0))->row();
		if (!$ord || !isset($ord->test_id) || (int)$ord->test_id <= 0) {
			return null;
		}
		$testId = (int)$ord->test_id;
		$this->load->model('app/Price_engine_model', 'price_engine');
		if (!isset($this->price_engine) || !method_exists($this->price_engine, 'resolve')) {
			return null;
		}
		$pr = $this->price_engine->resolve(array(
			'item_type' => 'RADIOLOGY',
			'item_id' => $testId,
			'quantity' => 1,
			'patient_no' => $patient_no,
			'payer_type' => $payer_type,
		));
		if (!is_array($pr) || empty($pr['ok'])) {
			return null;
		}
		return array(
			'item_id' => $testId,
			'item_name' => isset($pr['item_name']) ? (string)$pr['item_name'] : 'Radiology',
			'unit_price' => isset($pr['unit_price']) ? (float)$pr['unit_price'] : 0.0,
		);
	}

	private function _update_pending_transaction_price($txn_id, $item_name, $unit_price, $qty, $payer_type)
	{
		$txn_id = (int)$txn_id;
		if ($txn_id <= 0 || !$this->db->table_exists('billing_transactions')) {
			return;
		}
		$qty = (float)$qty;
		if ($qty <= 0) { $qty = 1.0; }
		$unit_price = round((float)$unit_price, 2);
		$gross = round($qty * $unit_price, 2);
		$upd = array(
			'item_name' => (string)$item_name,
			'unit_price' => $unit_price,
			'gross_amount' => $gross,
			'total_amount' => $gross,
			'net_amount' => $gross,
			'balance_amount' => $gross,
			'payer_type' => $payer_type !== '' ? strtoupper((string)$payer_type) : 'CASH',
			'updated_at' => date('Y-m-d H:i:s'),
		);
		$this->db->where('txn_id', $txn_id);
		$this->db->where('InActive', 0);
		$this->db->group_start();
		$this->db->where('invoice_no IS NULL', null, false);
		$this->db->or_where('invoice_no', '');
		$this->db->group_end();
		$this->db->update('billing_transactions', $upd);
	}

	private function _self_heal_pharmacy_queue_for_visit($iop_id, $patient_no, $limit = 200)
	{
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 200; }
		if ($iop_id === '' || $patient_no === '') { return 0; }
		if (schema_already_run('billing_core_tables_schema')) {
		} else {
			if (!$this->db->table_exists('billing_queue') || !$this->db->table_exists('iop_medication')) { return 0; }
			mark_schema_run('billing_core_tables_schema');
		}

		$this->db->select('queue_id, source_ref, item_id, unit_price, quantity, status');
		$this->db->from('billing_queue');
		$this->db->where('InActive', 0);
		$this->db->where('iop_id', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->where('item_type', 'PHARMACY');
		$this->db->where('status', self::STATUS_PENDING);
		$this->db->order_by('queue_id', 'DESC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		if (!$rows) { return 0; }

		$item_ids = array();
		foreach ($rows as $r) {
			$iid = isset($r->item_id) ? (int)$r->item_id : 0;
			if ($iid > 0) { $item_ids[] = $iid; }
		}
		$item_ids = array_values(array_unique($item_ids));

		$iop_to_drug = array();
		$drug_to_iop = array();
		if (!empty($item_ids)) {
			$this->db->select('iop_med_id, medicine_id', false);
			$this->db->from('iop_medication');
			$this->db->where_in('iop_med_id', $item_ids);
			$this->db->where('InActive', 0);
			$rxs = $this->db->get()->result();
			if ($rxs) {
				foreach ($rxs as $rx) {
					$im = isset($rx->iop_med_id) ? (int)$rx->iop_med_id : 0;
					$dr = isset($rx->medicine_id) ? (int)$rx->medicine_id : 0;
					if ($im > 0 && $dr > 0) {
						$iop_to_drug[$im] = $dr;
						if (!isset($drug_to_iop[$dr])) {
							$drug_to_iop[$dr] = $im;
						} elseif ($drug_to_iop[$dr] > 0 && $drug_to_iop[$dr] !== $im) {
							$drug_to_iop[$dr] = -1;
						}
					}
				}
			}
		}

		$seen = array();
		$deactivated = 0;
		foreach ($rows as $r) {
			$sr = isset($r->source_ref) ? trim((string)$r->source_ref) : '';
			$key = ($sr !== '') ? ('sr:' . $sr) : '';
			if ($key === '') {
				$iid = isset($r->item_id) ? (int)$r->item_id : 0;
				if ($iid > 0 && isset($iop_to_drug[$iid])) {
					$key = 'iop_med_id:' . $iid;
				} elseif ($iid > 0 && isset($drug_to_iop[$iid]) && (int)$drug_to_iop[$iid] > 0) {
					$key = 'iop_med_id:' . (int)$drug_to_iop[$iid];
				} elseif ($iid > 0) {
					$qty_k = isset($r->quantity) ? (float)$r->quantity : 1.0;
					if ($qty_k <= 0) { $qty_k = 1.0; }
					$key = 'drug_id:' . $iid . ':qty:' . (string)$qty_k;
				}
			}
			if ($key === '') { continue; }
			if (isset($seen[$key])) {
				$qid = isset($r->queue_id) ? (int)$r->queue_id : 0;
				if ($qid > 0) {
					$this->db->where('queue_id', $qid);
					$this->db->update('billing_queue', array('InActive' => 1));
					$deactivated++;
				}
				continue;
			}
			$seen[$key] = true;
		}

		// Reprice 0.00 pending items
		try {
			$this->load->model('app/Price_engine_model', 'price_engine');
			if (!isset($this->price_engine) || !method_exists($this->price_engine, 'resolve')) {
				return $deactivated;
			}
		} catch (\Throwable $e) {
			return $deactivated;
		}

		foreach ($rows as $r) {
			$qid = isset($r->queue_id) ? (int)$r->queue_id : 0;
			if ($qid <= 0) { continue; }
			$unit = isset($r->unit_price) ? (float)$r->unit_price : 0.0;
			if ($unit > 0.0) { continue; }

			$iid = isset($r->item_id) ? (int)$r->item_id : 0;
			if ($iid <= 0) { continue; }
			$drug_id = isset($iop_to_drug[$iid]) ? (int)$iop_to_drug[$iid] : $iid;
			if ($drug_id <= 0) { continue; }
			$qty = isset($r->quantity) ? (float)$r->quantity : 1.0;
			if ($qty <= 0) { $qty = 1.0; }

			$res = $this->price_engine->resolve(array(
				'item_type' => 'PHARMACY',
				'item_id' => $drug_id,
				'quantity' => $qty,
				'patient_no' => $patient_no,
				'require_positive_price' => true,
			));
			if (!is_array($res) || empty($res['ok'])) { continue; }
			$new_unit = isset($res['unit_price']) ? (float)$res['unit_price'] : 0.0;
			if ($new_unit <= 0) { continue; }
			$new_total = $qty * $new_unit;
			$upd = array(
				'unit_price' => $new_unit,
				'total_amount' => $new_total,
				'net_amount' => $new_total,
				'patient_amount' => $new_total,
			);
			if (isset($res['payer_type']) && trim((string)$res['payer_type']) !== '') {
				$upd['payer_type'] = strtoupper(trim((string)$res['payer_type']));
			}
			if (isset($res['item_name']) && trim((string)$res['item_name']) !== '') {
				$upd['item_name'] = (string)$res['item_name'];
			}
			foreach (array(
				'pricing_source' => isset($res['price_source']) ? (string)$res['price_source'] : null,
				'pricing_source_id' => isset($res['pricing_source_id']) ? (string)$res['pricing_source_id'] : (isset($res['source_id']) ? (string)$res['source_id'] : null),
				'resolved_drug_id' => $drug_id,
				'resolved_stock_id' => isset($res['resolved_stock_id']) ? $res['resolved_stock_id'] : null,
			) as $_prov_col => $_prov_val) {
				if ($this->column_exists('billing_queue', $_prov_col)) {
					$upd[$_prov_col] = $_prov_val;
				}
			}
			if ($this->column_exists('billing_queue', 'updated_at')) {
				$upd['updated_at'] = date('Y-m-d H:i:s');
			}
			$this->db->where('queue_id', $qid);
			$this->db->update('billing_queue', $upd);
		}

		return $deactivated;
	}

	public function backfill_pending_sonography_charges_to_queue($limit = 200)
	{
		try {
			$limit = (int)$limit;
			if ($limit <= 0) { $limit = 200; }
			if (schema_already_run('billing_core_tables_schema')) {
			} else {
				if (!$this->table_exists('billing_queue') || !$this->table_exists('iop_sonography_charge')) {
					return 0;
				}
				mark_schema_run('billing_core_tables_schema');
			}
			$this->load->model('app/billing_model');

			$this->db->select('c.charge_id, c.iop_id, c.patient_no, c.item_name, c.rate_amount, c.quantity, c.created_by, c.bill_particular_id, c.scan_item_id');
			$this->db->from('iop_sonography_charge c');
			$this->db->join('billing_queue q', "q.source_module = 'SONOGRAPHY' AND q.source_ref = CONCAT('sono_charge_id:', c.charge_id) AND q.InActive = 0", 'left', false);
			$this->db->where('c.InActive', 0);
			$this->db->where('q.queue_id IS NULL', null, false);
			$this->db->order_by('c.charge_id', 'DESC');
			$this->db->limit($limit);
			$rows = $this->db->get()->result();
			if (!$rows) {
				return 0;
			}
			$inserted = 0;
			foreach ($rows as $r) {
				$patientNo = isset($r->patient_no) ? (string)$r->patient_no : '';
				$iopId = isset($r->iop_id) ? (string)$r->iop_id : '';
				if ($patientNo === '' || $iopId === '') {
					continue;
				}
				$payer_type = 'CASH';
				if (isset($this->billing_model) && method_exists($this->billing_model, 'determine_payer_type')) {
					$pt = strtoupper(trim((string)$this->billing_model->determine_payer_type($patientNo)));
					if ($pt === 'NHIS' || $pt === 'CASH') {
						$payer_type = $pt;
					}
				}
				$itemName = isset($r->item_name) ? trim((string)$r->item_name) : '';
				if ($itemName === '') { $itemName = 'Sonography'; }
				$qty = isset($r->quantity) ? (float)$r->quantity : 1.0;
				if ($qty <= 0) { $qty = 1.0; }
				$rate = isset($r->rate_amount) ? (float)$r->rate_amount : 0.0;
				if ($rate <= 0.0 && $this->db->table_exists('bill_particular')) {
					$bpId = isset($r->bill_particular_id) ? (int)$r->bill_particular_id : 0;
					$scanId = isset($r->scan_item_id) ? (int)$r->scan_item_id : 0;
					if ($bpId <= 0 && $scanId > 0) {
						try {
							if ($this->db->table_exists('ghs_sonography_tests')) {
								$this->db->select('particular_id');
								$this->db->where(array('test_id' => $scanId, 'InActive' => 0));
								$this->db->limit(1);
								$g = $this->db->get('ghs_sonography_tests')->row();
								if ($g && isset($g->particular_id) && (int)$g->particular_id > 0) {
									$bpId = (int)$g->particular_id;
								}
							}
							if ($bpId <= 0 && $this->db->table_exists('sonography_items')) {
								$this->db->select('bill_particular_id');
								$this->db->where(array('item_id' => $scanId, 'InActive' => 0));
								$this->db->limit(1);
								$si = $this->db->get('sonography_items')->row();
								if ($si && isset($si->bill_particular_id) && (int)$si->bill_particular_id > 0) {
									$bpId = (int)$si->bill_particular_id;
								}
							}
						} catch (\Throwable $e) {
						}
					}
					if ($bpId > 0) {
						$this->db->select('charge_amount, particular_name');
						if ($this->db->field_exists('InActive', 'bill_particular')) {
							$this->db->where('InActive', 0);
						}
						$this->db->where('particular_id', $bpId);
						$this->db->limit(1);
						$bp = $this->db->get('bill_particular')->row();
						if ($bp) {
							if (isset($bp->charge_amount)) {
								$rate = (float)$bp->charge_amount;
							}
							if ($itemName === 'Sonography' && isset($bp->particular_name) && trim((string)$bp->particular_name) !== '') {
								$itemName = (string)$bp->particular_name;
							}
						}
					}
				}
				$res = $this->add_to_billing_queue(array(
					'iop_id' => $iopId,
					'patient_no' => $patientNo,
					'item_type' => 'SONOGRAPHY',
					'item_id' => (string)$r->charge_id,
					'item_name' => $itemName,
					'quantity' => $qty,
					'unit_price' => $rate,
					'payer_type' => $payer_type,
					'source_module' => 'SONOGRAPHY',
					'source_ref' => 'sono_charge_id:' . (int)$r->charge_id,
					'requested_by' => isset($r->created_by) ? (string)$r->created_by : null
				));
				if ($res && isset($res['success']) && $res['success']) {
					$inserted++;
				}
			}
			return $inserted;
		} catch (\Throwable $e) {
			return 0;
		}
	}

	/**
	 * Get billing queue summary for cashier dashboard
	 */
	public function get_billing_queue_summary()
	{
		$today = date('Y-m-d');
		$start = $today . ' 00:00:00';
		$end = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';
		
		// Pending items count
		$this->db->where('status', 'PENDING');
		$this->db->where('InActive', 0);
		$pending = $this->db->count_all_results('billing_queue');

		// Pending amount
		$this->db->select('COALESCE(SUM(net_amount), 0) as total');
		$this->db->where('status', 'PENDING');
		$this->db->where('InActive', 0);
		$pending_amount = (float)$this->db->get('billing_queue')->row()->total;

		// Today's billed
		$this->db->select('COALESCE(SUM(net_amount), 0) as total');
		$this->db->where('status', 'BILLED');
		$this->db->where('billed_at >=', $start);
		$this->db->where('billed_at <', $end);
		$this->db->where('InActive', 0);
		$billed_today = (float)$this->db->get('billing_queue')->row()->total;

		return array(
			'pending_count' => $pending,
			'pending_amount' => $pending_amount,
			'billed_today' => $billed_today
		);
	}

	/* ================================================================== */
	/*  INVOICE GENERATION - Single Entry Point                           */
	/* ================================================================== */

	/**
	 * Generate invoice from billing queue items
	 * This is THE ONLY function that should create invoices
	 * 
	 * @param string $iop_id Visit ID
	 * @param string $patient_no Patient number
	 * @param array $queue_ids Array of queue_id to include (null = all pending)
	 * @param string $created_by User creating invoice
	 * @return array Result with invoice_no or error
	 */
	public function generate_invoice($iop_id, $patient_no, $queue_ids = null, $created_by = null)
	{
		$this->ensure_unified_billing_schema();
		$this->_ensure_invoice_source_tables();
		if ($queue_ids === null) {
			$this->_ensure_visit_fee_queue_for_invoice($iop_id, $patient_no, $created_by);
		}

		// Get items from queue
		$this->db->where('iop_id', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->where('status', 'PENDING');
		$this->db->where('InActive', 0);
		if ($queue_ids && is_array($queue_ids)) {
			$this->db->where_in('queue_id', $queue_ids);
		}
		$items = $this->db->get('billing_queue')->result();

		if (empty($items)) {
			return array('success' => false, 'error' => 'No pending items to bill');
		}

		// Guardrail: reprice zero-priced LAB/PHARMACY items from authoritative catalog before invoicing
		try {
			$this->load->model('app/Price_engine_model', 'price_engine');
			foreach ($items as $item) {
				$item_type = isset($item->item_type) ? strtoupper(trim((string)$item->item_type)) : '';
				$qty = isset($item->quantity) ? (float)$item->quantity : 1.0;
				if ($qty <= 0) { $qty = 1.0; }
				$unit = isset($item->unit_price) ? (float)$item->unit_price : 0.0;
				if (($item_type === 'LAB' || $item_type === 'PHARMACY') && $unit <= 0.009 && isset($this->price_engine) && method_exists($this->price_engine, 'resolve')) {
					$ctx_type = $item_type;
					$ctx_id = isset($item->item_id) ? (int)$item->item_id : 0;
					if ($ctx_type === 'PHARMACY') {
						// billing_queue.item_id may be iop_med_id; resolve to medicine_id when possible
						$drug_id = 0;
						if ($this->db->table_exists('iop_medication') && $ctx_id > 0) {
							$rx = $this->db->select('medicine_id')
								->get_where('iop_medication', array('iop_med_id' => $ctx_id, 'InActive' => 0))
								->row();
							if ($rx && isset($rx->medicine_id)) {
								$drug_id = (int)$rx->medicine_id;
							}
						}
						if ($drug_id > 0) {
							$ctx_id = $drug_id;
						}
					}
					if ($ctx_id > 0) {
						$pr = $this->price_engine->resolve(array(
							'item_type' => $ctx_type,
							'item_id' => $ctx_id,
							'quantity' => $qty,
							'patient_no' => $patient_no,
							'payer_type' => isset($item->payer_type) ? (string)$item->payer_type : '',
							'submitted_unit_price' => $unit,
							'require_positive_price' => true,
						));
						if (is_array($pr) && !empty($pr['ok']) && isset($pr['unit_price']) && (float)$pr['unit_price'] > 0.009) {
							$unit = (float)$pr['unit_price'];
							$item->unit_price = $unit;
							if (isset($pr['payer_type']) && trim((string)$pr['payer_type']) !== '') {
								$item->payer_type = (string)$pr['payer_type'];
							}
							if (isset($pr['item_name']) && trim((string)$pr['item_name']) !== '' && (isset($item->item_name) && ((string)$item->item_name === 'Laboratory Test' || (string)$item->item_name === 'Medication' || trim((string)$item->item_name) === ''))) {
								$item->item_name = (string)$pr['item_name'];
							}

							$disc = isset($item->discount_amount) ? (float)$item->discount_amount : 0.0;
							$cov = isset($item->coverage_amount) ? (float)$item->coverage_amount : 0.0;
							$total_amount = round($qty * $unit, 2);
							$net_amount = round($total_amount - $disc, 2);
							$patient_amount = round($net_amount - $cov, 2);
							$item->total_amount = $total_amount;
							$item->net_amount = $net_amount;
							$item->patient_amount = $patient_amount;
							$item->pricing_source = isset($pr['price_source']) ? (string)$pr['price_source'] : null;
							$item->pricing_source_id = isset($pr['pricing_source_id']) ? (string)$pr['pricing_source_id'] : (isset($pr['source_id']) ? (string)$pr['source_id'] : null);
							$item->resolved_drug_id = ($item_type === 'PHARMACY' && $ctx_id > 0) ? (int)$ctx_id : null;
							$item->resolved_stock_id = isset($pr['resolved_stock_id']) ? $pr['resolved_stock_id'] : null;

							$this->db->where('queue_id', (int)$item->queue_id);
							$priceUpdate = array(
								'unit_price' => $unit,
								'total_amount' => $total_amount,
								'net_amount' => $net_amount,
								'patient_amount' => $patient_amount,
								'payer_type' => isset($item->payer_type) ? (string)$item->payer_type : 'CASH',
							);
							foreach (array('pricing_source','pricing_source_id','resolved_drug_id','resolved_stock_id') as $_prov_col) {
								if ($this->column_exists('billing_queue', $_prov_col) && isset($item->{$_prov_col})) {
									$priceUpdate[$_prov_col] = $item->{$_prov_col};
								}
							}
							$this->db->update('billing_queue', $priceUpdate);
						}
					}
				}
				if (($item_type === 'LAB' || $item_type === 'PHARMACY') && ((float)$item->unit_price) <= 0.009) {
					return array('success' => false, 'error' => 'Missing pricing for: ' . (isset($item->item_name) ? (string)$item->item_name : $item_type));
				}
			}
		} catch (\Throwable $e) {
			return array('success' => false, 'error' => 'Invoice pricing validation failed: ' . $e->getMessage());
		}

		// Calculate totals
		$total = 0;
		$discount = 0;
		$coverage = 0;
		$patient_total = 0;
		$payer_type = 'CASH';

		foreach ($items as $item) {
			$total += (float)$item->net_amount;
			$discount += (float)$item->discount_amount;
			$coverage += (float)$item->coverage_amount;
			$patient_total += (float)$item->patient_amount;
			if ($item->payer_type !== 'CASH') {
				$payer_type = $item->payer_type;
			}
		}

		// Generate invoice number
		$invoice_no = $this->_generate_invoice_no();

		$this->db->trans_begin();

		try {
			$bill_disc_col = $this->_get_discount_column('iop_billing');
			$billt_disc_col = $this->_get_discount_column('iop_billing_t');

			// Insert invoice header
			$header = array(
				'invoice_no' => $invoice_no,
				'iop_id' => $iop_id,
				'patient_no' => $patient_no,
				'total_amount' => $total,
				'payment_status' => 'UNPAID',
				'balance_due' => $patient_total,
				'payer_type' => $payer_type,
				'dDate' => date('Y-m-d H:i:s'),
				'InActive' => 0
			);
			if ($bill_disc_col) {
				$header[$bill_disc_col] = $discount;
			}
			if ($this->column_exists('iop_billing', 'sub_total')) {
				$header['sub_total'] = (float)$total + (float)$discount;
			}
			if ($this->column_exists('iop_billing', 'total_purchased')) {
				$header['total_purchased'] = count($items);
			}
			if ($created_by) $header['updated_by'] = $created_by;
			
			$this->db->insert('iop_billing', $header);

			$hasSubTotal = $this->column_exists('iop_billing_t', 'sub_total');
			// Insert invoice line items
			$line_no = 0;
			foreach ($items as $item) {
				$line_no++;
				$line = array(
					'invoice_no' => $invoice_no,
					'iop_id' => $iop_id,
					'bill_name' => $item->item_name,
					'qty' => $item->quantity,
					'rate' => $item->unit_price,
					'amount' => $item->net_amount,
					'InActive' => 0
				);
				if ($billt_disc_col) {
					$line[$billt_disc_col] = $item->discount_amount;
				}
				if ($hasSubTotal) {
					$line['sub_total'] = isset($item->total_amount) ? (float)$item->total_amount : (float)$item->net_amount;
				}
				if ($this->column_exists('iop_billing_t', 'price_source') && isset($item->pricing_source)) {
					$line['price_source'] = (string)$item->pricing_source;
				}
				foreach (array('pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag') as $_prov_col) {
					if ($this->column_exists('iop_billing_t', $_prov_col) && isset($item->{$_prov_col})) {
						$line[$_prov_col] = $item->{$_prov_col};
					}
				}
				$this->db->insert('iop_billing_t', $line);
				$detail_id = (int)$this->db->insert_id();
				$this->_persist_queue_invoice_source($invoice_no, $detail_id, $item, $iop_id, $patient_no, $created_by);

				// Update queue item status
				$this->db->where('queue_id', $item->queue_id);
				$this->db->update('billing_queue', array(
					'status' => 'BILLED',
					'invoice_no' => $invoice_no,
					'billed_by' => $created_by,
					'billed_at' => date('Y-m-d H:i:s')
				));
			}

			// Record in financial ledger (double-entry)
			$this->_record_invoice_ledger($invoice_no, $patient_no, $total, $payer_type, $created_by);

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Database error during invoice creation');
			}

			$this->db->trans_commit();

			return array(
				'success' => true,
				'invoice_no' => $invoice_no,
				'total' => $total,
				'items_count' => count($items)
			);

		} catch (Exception $e) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => $e->getMessage());
		}
	}

	private function _ensure_invoice_source_tables()
	{
		try {
			$this->load->model('app/billing_model');
			if (isset($this->billing_model) && method_exists($this->billing_model, 'install_billing_meta_tables')) {
				$this->billing_model->install_billing_meta_tables();
			}
		} catch (\Throwable $e) {
			log_message('error', 'Unified Billing: invoice source table ensure failed: ' . $e->getMessage());
		}
	}

	private function _ensure_visit_fee_queue_for_invoice($iop_id, $patient_no, $created_by = null)
	{
		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		if ($iop_id === '' || $patient_no === '') {
			return false;
		}
		try {
			$this->load->model('app/visit_billing_resolver_model');
			if (isset($this->visit_billing_resolver_model) && method_exists($this->visit_billing_resolver_model, 'auto_bill_visit_fees')) {
				$this->visit_billing_resolver_model->auto_bill_visit_fees($iop_id, $patient_no, $created_by);
			}
		} catch (\Throwable $e) {
			log_message('error', 'Unified Billing: visit fee auto-bill failed iop_id=' . $iop_id . ' patient_no=' . $patient_no . ': ' . $e->getMessage());
		}
		try {
			$this->backfill_visit_fees_for_queue($iop_id, $patient_no);
			$this->backfill_pending_transactions_to_queue($iop_id, $patient_no);
		} catch (\Throwable $e) {
			log_message('error', 'Unified Billing: visit fee queue backfill failed iop_id=' . $iop_id . ' patient_no=' . $patient_no . ': ' . $e->getMessage());
		}
		return true;
	}

	private function _persist_queue_invoice_source($invoice_no, $detail_id, $item, $iop_id = null, $patient_no = null, $created_by = null)
	{
		$invoice_no = trim((string)$invoice_no);
		$detail_id = (int)$detail_id;
		if ($invoice_no === '' || $detail_id <= 0 || !is_object($item)) {
			return false;
		}
		$source_module = isset($item->source_module) ? trim((string)$item->source_module) : '';
		$source_ref = isset($item->source_ref) ? trim((string)$item->source_ref) : '';
		if ($source_module === '' || $source_ref === '') {
			return false;
		}
		$iop_id = $iop_id !== null ? trim((string)$iop_id) : (isset($item->iop_id) ? trim((string)$item->iop_id) : '');
		$patient_no = $patient_no !== null ? trim((string)$patient_no) : (isset($item->patient_no) ? trim((string)$item->patient_no) : '');
		$now = date('Y-m-d H:i:s');
		try {
			if ($this->table_exists('iop_billing_line_meta')) {
				$exists = $this->db->get_where('iop_billing_line_meta', array(
					'invoice_no' => $invoice_no,
					'detail_id' => $detail_id,
					'InActive' => 0
				))->row();
				if (!$exists) {
					$meta = array(
						'invoice_no' => $invoice_no,
						'detail_id' => $detail_id,
						'source_module' => $source_module,
						'source_ref' => $source_ref,
						'created_at' => $now,
						'created_by' => $created_by !== null ? (string)$created_by : null,
						'InActive' => 0
					);
					foreach (array('pricing_source','pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag') as $_prov_col) {
						if ($this->column_exists('iop_billing_line_meta', $_prov_col) && isset($item->{$_prov_col})) {
							$meta[$_prov_col] = $item->{$_prov_col};
						}
					}
					$this->db->insert('iop_billing_line_meta', $meta);
				}
			}
			if ($this->table_exists('iop_billable_item_lock')) {
				$this->db->where(array('source_module' => $source_module, 'source_ref' => $source_ref, 'InActive' => 0));
				$this->db->limit(1);
				$lock = $this->db->get('iop_billable_item_lock')->row();
				if (!$lock) {
					$this->db->insert('iop_billable_item_lock', array(
						'source_module' => $source_module,
						'source_ref' => $source_ref,
						'invoice_no' => $invoice_no,
						'detail_id' => $detail_id,
						'iop_id' => $iop_id !== '' ? $iop_id : null,
						'patient_no' => $patient_no !== '' ? $patient_no : null,
						'status' => 'INVOICED',
						'locked_at' => $now,
						'locked_by' => $created_by !== null ? (string)$created_by : null,
						'InActive' => 0
					));
				}
			}
			$this->_link_queue_source_transaction_to_invoice($invoice_no, $detail_id, $source_module, $source_ref, $iop_id, $patient_no, $created_by);
			try {
				$itemType = isset($item->item_type) ? strtoupper(trim((string)$item->item_type)) : '';
				$bst = isset($item->billing_subject_type) ? strtoupper(trim((string)$item->billing_subject_type)) : '';
				$bsid = isset($item->billing_subject_id) ? trim((string)$item->billing_subject_id) : '';
				if ($source_module === 'WALKIN_ORDER_ITEM' && $itemType === 'PHARMACY' && $bst !== '' && $bsid !== '' && $this->table_exists('billing_transactions')) {
					$hasSubject = $this->column_exists('billing_transactions', 'billing_subject_type') && $this->column_exists('billing_transactions', 'billing_subject_id');
					$this->db->where('InActive', 0);
					$this->db->where('item_ref', $source_ref);
					if ($hasSubject) {
						$this->db->where('billing_subject_type', $bst);
						$this->db->where('billing_subject_id', $bsid);
					}
					$ex = $this->db->get('billing_transactions')->row();
					if ($ex) {
						$inv0 = isset($ex->invoice_no) ? trim((string)$ex->invoice_no) : '';
						if ($inv0 === '') {
							$upd = array(
								'invoice_no' => $invoice_no,
								'updated_at' => $now,
								'updated_by' => $created_by !== null ? (string)$created_by : null,
							);
							if ($this->column_exists('billing_transactions', 'detail_id')) {
								$upd['detail_id'] = (int)$detail_id;
							}
							$this->db->where(array('txn_id' => (int)$ex->txn_id, 'InActive' => 0));
							$this->db->update('billing_transactions', $upd);
						}
					} else {
						$drug_id = 0;
						if ($this->table_exists('walkin_order_items')) {
							$wid = isset($item->item_id) ? (int)$item->item_id : 0;
							if ($wid > 0) {
								$r = $this->db->get_where('walkin_order_items', array('internal_id' => $wid, 'InActive' => 0))->row();
								$cat = $r && isset($r->catalog_ref) ? trim((string)$r->catalog_ref) : '';
								if (preg_match('/^drug_id\s*:\s*(\d+)$/i', $cat, $m)) {
									$drug_id = (int)$m[1];
								}
							}
						}
						$this->load->model('app/billing_transaction_model');
						if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'create_transaction')) {
							$tx = $this->billing_transaction_model->create_transaction(array(
								'billing_subject_type' => $bst,
								'billing_subject_id' => $bsid,
								'patient_no' => null,
								'encounter_id' => null,
								'encounter_type' => 'OPD',
								'department' => 'PHARMACY',
								'item_type' => 'DRUG',
								'item_id' => $drug_id > 0 ? $drug_id : null,
								'item_ref' => $source_ref,
								'item_name' => isset($item->item_name) ? (string)$item->item_name : 'Medication',
								'quantity' => isset($item->quantity) ? (float)$item->quantity : 1.0,
								'unit_price' => isset($item->unit_price) ? (float)$item->unit_price : 0.0,
								'payer_type' => isset($item->payer_type) ? (string)$item->payer_type : 'CASH',
								'invoice_no' => $invoice_no,
							), $created_by);
							if (is_array($tx) && !empty($tx['ok']) && $this->column_exists('billing_transactions', 'detail_id')) {
								$this->db->where(array('txn_id' => (int)$tx['txn_id'], 'InActive' => 0));
								$this->db->update('billing_transactions', array('detail_id' => (int)$detail_id));
							}
						}
					}
				}
			} catch (Throwable $e) {
			}
			try {
				if (strtoupper(trim((string)$source_module)) === 'PHARMACY' && $this->table_exists('billing_transactions')) {
					$matches = array();
					if (preg_match_all('/(?:iop_medication_id|iop_medication|iop_med_id)\s*:\s*(\d+)/i', $source_ref, $matches) && !empty($matches[1])) {
						$mid = (int)$matches[1][count($matches[1]) - 1];
						if ($mid > 0) {
							$this->load->model('app/billing_transaction_model');
							if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_pharmacy_medication')) {
								$this->billing_transaction_model->sync_pharmacy_medication($mid, $created_by, $invoice_no, $detail_id);
							}
						}
					}
				}
			} catch (Throwable $e) {
			}
			return true;
		} catch (\Throwable $e) {
			log_message('error', 'Unified Billing: queue invoice source persist failed invoice_no=' . $invoice_no . ' source_module=' . $source_module . ' source_ref=' . $source_ref . ': ' . $e->getMessage());
			return false;
		}
	}

	private function _link_queue_source_transaction_to_invoice($invoice_no, $detail_id, $source_module, $source_ref, $iop_id = '', $patient_no = '', $created_by = null)
	{
		if (!$this->table_exists('billing_transactions')) {
			return 0;
		}
		$invoice_no = trim((string)$invoice_no);
		$source_module = strtoupper(trim((string)$source_module));
		$source_ref = trim((string)$source_ref);
		if ($invoice_no === '' || $source_ref === '') {
			return 0;
		}
		$refs = array($source_ref);
		if ($source_module === 'PHARMACY') {
			if (strpos($source_ref, 'iop_medication:') === 0) {
				$mid = (int)substr($source_ref, strlen('iop_medication:'));
				if ($mid > 0) { $refs[] = 'iop_med_id:' . $mid; }
			} elseif (preg_match('/iop_medication:(\d+)/', $source_ref, $m)) {
				$mid = (int)$m[1];
				if ($mid > 0) { $refs[] = 'iop_med_id:' . $mid; $refs[] = 'iop_medication:' . $mid; }
			}
		} elseif ($source_module === 'PROCEDURE' && strpos($source_ref, 'opd_procedure_request:') === 0) {
			$pid = (int)substr($source_ref, strlen('opd_procedure_request:'));
			if ($pid > 0) { $refs[] = 'opd_procedure_request_id:' . $pid; }
		}
		$refs = array_values(array_unique(array_filter($refs, function($v) {
			return trim((string)$v) !== '';
		})));
		$upd = array(
			'invoice_no' => $invoice_no,
			'updated_at' => date('Y-m-d H:i:s'),
			'updated_by' => $created_by !== null ? (string)$created_by : null
		);
		if ($this->column_exists('billing_transactions', 'detail_id')) {
			$upd['detail_id'] = (int)$detail_id;
		}
		$this->db->where('InActive', 0);
		if ($iop_id !== '') { $this->db->where('encounter_id', (string)$iop_id); }
		if ($patient_no !== '') { $this->db->where('patient_no', (string)$patient_no); }
		$this->db->where_in('item_ref', $refs);
		$this->db->group_start();
		$this->db->where('invoice_no IS NULL', null, false);
		$this->db->or_where('invoice_no', '');
		$this->db->group_end();
		$this->db->update('billing_transactions', $upd);
		$affected = max(0, (int)$this->db->affected_rows());
		if (($source_module === 'REGISTRATION' || $source_module === 'CONSULTATION') && $affected > 0) {
			$this->_mark_smart_billing_ledger_if_visit_fees_billed($iop_id, $patient_no, $invoice_no, $created_by);
		}
		return $affected;
	}

	private function _mark_smart_billing_ledger_if_visit_fees_billed($iop_id, $patient_no, $invoice_no, $created_by = null)
	{
		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		if ($iop_id === '' || $patient_no === '' || !$this->table_exists('smart_billing_ledger') || !$this->table_exists('billing_transactions')) {
			return false;
		}
		$refs = array('visit_registration:' . $iop_id, 'visit_consultation:' . $iop_id);
		$this->db->select('COUNT(*) AS c', false);
		$this->db->where('InActive', 0);
		$this->db->where('encounter_id', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->where('invoice_no', (string)$invoice_no);
		$this->db->where_in('item_ref', $refs);
		$row = $this->db->get('billing_transactions')->row();
		if (!$row || (int)$row->c < 2) {
			return false;
		}
		$this->db->where(array('iop_id' => $iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
		$this->db->update('smart_billing_ledger', array(
			'status' => 'BILLED',
			'billed_by' => $created_by !== null ? (string)$created_by : null,
			'billed_at' => date('Y-m-d H:i:s')
		));
		return ((int)$this->db->affected_rows() > 0);
	}

	/**
	 * Generate unique invoice number
	 */
	private function _generate_invoice_no()
	{
		$prefix = 'SI-' . date('Ymd');
		$this->db->select('MAX(invoice_no) as max_no');
		$this->db->like('invoice_no', $prefix, 'after');
		$row = $this->db->get('iop_billing')->row();

		if ($row && $row->max_no) {
			$num = (int)substr($row->max_no, -4) + 1;
		} else {
			$num = 1;
		}

		return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
	}

	/* ================================================================== */
	/*  PAYMENT PROCESSING - Single Entry Point                           */
	/* ================================================================== */

	/**
	 * Process payment for an invoice
	 * This is THE ONLY function that should record payments
	 * 
	 * @param string $invoice_no Invoice number
	 * @param float $amount Payment amount
	 * @param string $payment_method Payment method code
	 * @param string $reference Reference number (for non-cash)
	 * @param string $cashier_id Cashier user ID
	 * @param string $notes Optional notes
	 * @return array Result with receipt_no or error
	 */
	public function process_payment($invoice_no, $amount, $payment_method = 'CASH', $reference = null, $cashier_id = null, $notes = null)
	{
		// Phase 4 / Step 4 — optional facade route. Default OFF.
		$this->load->model('app/Billing_facade_model', 'billing_facade_model');
		if ($this->billing_facade_model->is_receipt_route_enabled()) {
			$res = $this->billing_facade_model->record_payment(array(
				'invoice_no'     => $invoice_no,
				'amount'         => $amount,
				'payment_method' => $payment_method,
				'reference'      => $reference,
				'notes'          => $notes,
				'cashier_id'     => $cashier_id,
				'source'         => 'UNIFIED',
			));
			if (!empty($res['ok'])) {
				$this->load->model('app/billing_model');
				$this->billing_model->apply_post_payment_side_effects($invoice_no, $res['receipt_no'], $cashier_id);
				return array(
					'success'       => true,
					'receipt_no'    => $res['receipt_no'],
					'ssot_sync'     => $res['ssot_sync'],
					'amount_paid'   => $amount,
					'balance'       => $res['balance_after'],
					'payment_status'=> $res['payment_status'],
				);
			}
			return array('success' => false, 'error' => $res['error']);
		}

		$this->ensure_unified_billing_schema();

		// Get invoice
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$invoice = $this->db->get('iop_billing')->row();

		if (!$invoice) {
			return array('success' => false, 'error' => 'Invoice not found');
		}

		// Calculate current balance
		$balance = $this->get_invoice_balance($invoice_no);

		if ($balance <= 0) {
			return array('success' => false, 'error' => 'Invoice is already fully paid');
		}

		if ($amount > $balance) {
			return array('success' => false, 'error' => 'Payment amount exceeds balance of GHS ' . number_format($balance, 2));
		}

		$this->db->trans_begin();

		try {
			// Generate receipt number
			$receipt_no = $this->_generate_receipt_no();

			// Insert receipt
			// Legacy convention: total_amount = invoice total, amountPaid = actual payment
			$inv_disc = 0.0;
			if (isset($invoice->discount)) {
				$inv_disc = (float)$invoice->discount;
			} elseif (isset($invoice->discount_amount)) {
				$inv_disc = (float)$invoice->discount_amount;
			} elseif (isset($invoice->discountAmount)) {
				$inv_disc = (float)$invoice->discountAmount;
			}
			$invoice_total = round(((float)$invoice->total_amount - (float)$inv_disc), 2);
			$receipt = array(
				'receipt_no' => $receipt_no,
				'invoice_no' => $invoice_no,
				'iop_id' => $invoice->iop_id,
				'patient_no' => $invoice->patient_no,
				'total_amount' => round($invoice_total, 2),
				'amountPaid' => round((float)$amount, 2),
				'change' => 0,
				'payment_type' => $payment_method,
				'dDate' => date('Y-m-d H:i:s'),
				'InActive' => 0
			);
			if ($cashier_id && $this->db->field_exists('cashier_id', 'iop_receipt')) {
				$receipt['cashier_id'] = $cashier_id;
			}
			$this->db->insert('iop_receipt', $receipt);

			// Update invoice balance and status
			$new_balance = round(((float)$balance - (float)$amount), 2);
			$new_status = ($new_balance <= 0.01) ? self::STATUS_PAID : self::STATUS_PARTIAL;

			$this->db->where('invoice_no', $invoice_no);
			$this->db->update('iop_billing', array(
				'balance_due' => round(max(0, $new_balance), 2),
				'payment_status' => $new_status
			));

			// Record in financial ledger (double-entry)
			$this->_record_payment_ledger($receipt_no, $invoice_no, $invoice->patient_no, $amount, $payment_method, $cashier_id);

			// Log to audit trail
			$this->_log_payment_audit($receipt_no, $invoice_no, $invoice->patient_no, $amount, $payment_method, $cashier_id, $balance, $new_balance);
			
			// Auto-release service gates if invoice is now paid
			if ($new_status === self::STATUS_PAID) {
				$this->auto_release_gates_for_invoice($invoice_no);
			}

			// Sync receipt payment into billing_transactions SSOT
			$ssot_sync = null;
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_receipt_payment')) {
				$ssot_sync = $this->billing_transaction_model->sync_receipt_payment($receipt_no, $cashier_id);
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Database error during payment');
			}

			$this->db->trans_commit();

			return array(
				'success' => true,
				'receipt_no' => $receipt_no,
				'ssot_sync' => $ssot_sync,
				'amount_paid' => $amount,
				'new_balance' => $new_balance,
				'status' => $new_status
			);

		} catch (Exception $e) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => $e->getMessage());
		}
	}

	/**
	 * Generate unique receipt number
	 */
	private function _generate_receipt_no()
	{
		$prefix = 'RCP' . date('Ymd');
		$this->db->select('MAX(receipt_no) as max_no');
		$this->db->like('receipt_no', $prefix, 'after');
		$row = $this->db->get('iop_receipt')->row();

		if ($row && $row->max_no) {
			$num = (int)substr($row->max_no, -4) + 1;
		} else {
			$num = 1;
		}

		return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Get current balance for an invoice
	 */
	public function get_invoice_balance($invoice_no)
	{
		// Get invoice total
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$this->db->select('b.total_amount, ' . $discExpr . ' as discount', false);
		$this->db->from('iop_billing b');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$invoice = $this->db->get()->row();

		if (!$invoice) return 0;

		$invoice_total = round(((float)$invoice->total_amount - (float)$invoice->discount), 2);

		// Get total paid (amountPaid = actual payment amount per legacy convention)
		$this->db->select('COALESCE(SUM(amountPaid), 0) as paid');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$paid = (float)$this->db->get('iop_receipt')->row()->paid;
		$paid = round($paid, 2);

		return round(max(0, ($invoice_total - $paid)), 2);
	}

	/* ================================================================== */
	/*  FINANCIAL LEDGER - Double Entry Accounting                        */
	/* ================================================================== */

	/**
	 * Record invoice in financial ledger
	 * Debit: Accounts Receivable
	 * Credit: Revenue Account
	 */
	private function _record_invoice_ledger($invoice_no, $patient_no, $amount, $payer_type, $created_by)
	{
		if (!$this->db->table_exists('financial_ledger')) return;

		$bst = null;
		$bsid = null;
		try {
			if ($this->column_exists('financial_ledger', 'billing_subject_type') && $this->column_exists('financial_ledger', 'billing_subject_id')
				&& $this->db->table_exists('iop_billing')
				&& $this->column_exists('iop_billing', 'billing_subject_type')
				&& $this->column_exists('iop_billing', 'billing_subject_id'))
			{
				$this->db->select('billing_subject_type, billing_subject_id');
				$this->db->where('invoice_no', $invoice_no);
				$this->db->where('InActive', 0);
				$this->db->limit(1);
				$inv = $this->db->get('iop_billing')->row();
				if ($inv) {
					$bst = isset($inv->billing_subject_type) && trim((string)$inv->billing_subject_type) !== '' ? (string)$inv->billing_subject_type : null;
					$bsid = isset($inv->billing_subject_id) && trim((string)$inv->billing_subject_id) !== '' ? (string)$inv->billing_subject_id : null;
				}
			}
		} catch (\Throwable $e) {
		}

		$txn_id = 'INV-' . $invoice_no;
		$today = date('Y-m-d');

		// Determine receivable account based on payer type
		$receivable_code = '1100'; // Default: Accounts Receivable
		if ($payer_type === 'NHIS') $receivable_code = '1110';
		elseif ($payer_type === 'INSURANCE') $receivable_code = '1120';
		elseif ($payer_type === 'COMPANY') $receivable_code = '1130';

		$receivable_id = $this->_get_account_id($receivable_code);
		$revenue_id = $this->_get_account_id('4000'); // Medical Revenue

		if (!$receivable_id || !$revenue_id) return;

		// Debit Receivable
		$debit = array(
			'transaction_id' => $txn_id,
			'transaction_date' => $today,
			'account_id' => $receivable_id,
			'debit_amount' => $amount,
			'credit_amount' => 0,
			'reference_type' => 'INVOICE',
			'reference_no' => $invoice_no,
			'patient_no' => $patient_no,
			'description' => 'Invoice generated',
			'created_by' => $created_by
		);
		if ($bst !== null) { $debit['billing_subject_type'] = $bst; }
		if ($bsid !== null) { $debit['billing_subject_id'] = $bsid; }
		$this->db->insert('financial_ledger', $debit);

		// Credit Revenue
		$credit = array(
			'transaction_id' => $txn_id,
			'transaction_date' => $today,
			'account_id' => $revenue_id,
			'debit_amount' => 0,
			'credit_amount' => $amount,
			'reference_type' => 'INVOICE',
			'reference_no' => $invoice_no,
			'patient_no' => $patient_no,
			'description' => 'Revenue recognized',
			'created_by' => $created_by
		);
		if ($bst !== null) { $credit['billing_subject_type'] = $bst; }
		if ($bsid !== null) { $credit['billing_subject_id'] = $bsid; }
		$this->db->insert('financial_ledger', $credit);
	}

	/**
	 * Public method to record payment to financial ledger
	 * Can be called from external controllers (POS, Cashier)
	 */
	public function record_payment_to_ledger($receipt_no, $invoice_no, $patient_no, $amount, $payment_method, $cashier_id)
	{
		return $this->_record_payment_ledger($receipt_no, $invoice_no, $patient_no, $amount, $payment_method, $cashier_id);
	}

	public function void_receipt_in_ledger($receipt_no, $voided_by = null, $reason = null)
	{
		$this->ensure_unified_billing_schema();
		$receipt_no = trim((string)$receipt_no);
		if ($receipt_no === '' || !$this->db->table_exists('financial_ledger')) {
			return false;
		}

		$pay_txn_id = 'PAY-' . $receipt_no;
		$void_txn_id = 'VOID-PAY-' . $receipt_no;

		$this->db->where('transaction_id', $void_txn_id);
		$this->db->limit(1);
		$exists = $this->db->get('financial_ledger')->row();
		if ($exists) {
			return true;
		}

		$this->db->where('transaction_id', $pay_txn_id);
		$rows = $this->db->get('financial_ledger')->result();
		if (empty($rows)) {
			return false;
		}

		$today = date('Y-m-d');
		$by = $voided_by !== null ? (string)$voided_by : null;
		$reason = $reason !== null ? trim((string)$reason) : '';

		foreach ($rows as $r) {
			$acct = isset($r->account_id) ? (int)$r->account_id : 0;
			if ($acct <= 0) {
				continue;
			}
			$this->db->insert('financial_ledger', array(
				'transaction_id' => $void_txn_id,
				'transaction_date' => $today,
				'account_id' => $acct,
				'debit_amount' => isset($r->credit_amount) ? (float)$r->credit_amount : 0.0,
				'credit_amount' => isset($r->debit_amount) ? (float)$r->debit_amount : 0.0,
				'reference_type' => 'VOID_RECEIPT',
				'reference_no' => $receipt_no,
				'patient_no' => isset($r->patient_no) ? (string)$r->patient_no : null,
				'description' => 'Void receipt' . ($reason !== '' ? (': ' . $reason) : ''),
				'created_by' => $by,
			));
		}

		// Best-effort: also log to financial_audit_log (Unified Billing ledger)
		$this->void_receipt_audit($receipt_no, $by, $reason);

		return true;
	}

	public function refund_receipt_in_ledger($refund_receipt_no, $original_receipt_no, $invoice_no, $patient_no, $amount, $payment_method, $refunded_by = null, $reason = null)
	{
		$this->ensure_unified_billing_schema();
		$refund_receipt_no = trim((string)$refund_receipt_no);
		$original_receipt_no = trim((string)$original_receipt_no);
		$invoice_no = trim((string)$invoice_no);
		$patient_no = trim((string)$patient_no);
		$amount = (float)$amount;
		$payment_method = strtoupper(trim((string)$payment_method));
		$refunded_by = $refunded_by !== null ? (string)$refunded_by : null;
		$reason = $reason !== null ? trim((string)$reason) : '';
		if ($refund_receipt_no === '' || $amount <= 0 || !$this->db->table_exists('financial_ledger')) {
			return false;
		}

		$txn_id = 'REFUND-' . $refund_receipt_no;
		$this->db->where('transaction_id', $txn_id);
		$this->db->limit(1);
		$exists = $this->db->get('financial_ledger')->row();
		if ($exists) {
			return true;
		}

		$cash_code = '1000';
		if (in_array($payment_method, array('BANK', 'CARD', 'MOMO'), true)) {
			$cash_code = '1010';
		}
		$cash_id = $this->_get_account_id($cash_code);
		$receivable_id = $this->_get_account_id('1100');
		if (!$cash_id || !$receivable_id) {
			return false;
		}

		$today = date('Y-m-d');
		$desc = 'Refund issued';
		if ($original_receipt_no !== '') {
			$desc .= ' for receipt ' . $original_receipt_no;
		}
		if ($invoice_no !== '') {
			$desc .= ' (invoice ' . $invoice_no . ')';
		}
		if ($reason !== '') {
			$desc .= ': ' . $reason;
		}

		// Debit Receivable (patient now owes again)
		$this->db->insert('financial_ledger', array(
			'transaction_id' => $txn_id,
			'transaction_date' => $today,
			'account_id' => $receivable_id,
			'debit_amount' => $amount,
			'credit_amount' => 0,
			'reference_type' => 'REFUND',
			'reference_no' => $refund_receipt_no,
			'patient_no' => $patient_no !== '' ? $patient_no : null,
			'description' => $desc,
			'created_by' => $refunded_by
		));

		// Credit Cash/Bank (cash paid out)
		$this->db->insert('financial_ledger', array(
			'transaction_id' => $txn_id,
			'transaction_date' => $today,
			'account_id' => $cash_id,
			'debit_amount' => 0,
			'credit_amount' => $amount,
			'reference_type' => 'REFUND',
			'reference_no' => $refund_receipt_no,
			'patient_no' => $patient_no !== '' ? $patient_no : null,
			'description' => $desc,
			'created_by' => $refunded_by
		));

		return true;
	}

	public function void_receipt_audit($receipt_no, $voided_by = null, $reason = null)
	{
		$this->ensure_unified_billing_schema();
		$receipt_no = trim((string)$receipt_no);
		if ($receipt_no === '' || !$this->db->table_exists('financial_audit_log')) {
			return false;
		}
		$voided_by = $voided_by !== null ? (string)$voided_by : null;
		$reason = $reason !== null ? trim((string)$reason) : '';
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';

		// Idempotency: do not insert duplicate VOID audit for the same receipt
		$this->db->where(array('action_type' => 'VOID', 'receipt_no' => $receipt_no));
		$this->db->limit(1);
		$exists = $this->db->get('financial_audit_log')->row();
		if ($exists) {
			return true;
		}

		$this->db->insert('financial_audit_log', array(
			'action_type' => 'VOID',
			'receipt_no' => $receipt_no,
			'invoice_no' => null,
			'patient_no' => null,
			'amount' => 0,
			'balance_before' => null,
			'balance_after' => null,
			'description' => 'Payment voided' . ($reason !== '' ? (': ' . $reason) : ''),
			'performed_by' => $voided_by,
			'performed_at' => date('Y-m-d H:i:s'),
			'ip_address' => $ip,
		));
		return true;
	}

	/**
	 * Record payment in financial ledger
	 * Debit: Cash/Bank
	 * Credit: Accounts Receivable
	 */
	private function _record_payment_ledger($receipt_no, $invoice_no, $patient_no, $amount, $payment_method, $cashier_id)
	{
		if (!$this->db->table_exists('financial_ledger')) return;

		$bst = null;
		$bsid = null;
		try {
			if ($this->column_exists('financial_ledger', 'billing_subject_type') && $this->column_exists('financial_ledger', 'billing_subject_id')
				&& $this->db->table_exists('iop_billing')
				&& $this->column_exists('iop_billing', 'billing_subject_type')
				&& $this->column_exists('iop_billing', 'billing_subject_id'))
			{
				$this->db->select('billing_subject_type, billing_subject_id');
				$this->db->where('invoice_no', $invoice_no);
				$this->db->where('InActive', 0);
				$this->db->limit(1);
				$inv = $this->db->get('iop_billing')->row();
				if ($inv) {
					$bst = isset($inv->billing_subject_type) && trim((string)$inv->billing_subject_type) !== '' ? (string)$inv->billing_subject_type : null;
					$bsid = isset($inv->billing_subject_id) && trim((string)$inv->billing_subject_id) !== '' ? (string)$inv->billing_subject_id : null;
				}
			}
		} catch (\Throwable $e) {
		}

		$txn_id = 'PAY-' . $receipt_no;
		$today = date('Y-m-d');

		// Determine cash account based on payment method
		$cash_code = '1000'; // Default: Cash on Hand
		if (in_array($payment_method, array('BANK', 'CARD', 'MOMO'))) {
			$cash_code = '1010'; // Bank Account
		}

		$cash_id = $this->_get_account_id($cash_code);
		$receivable_id = $this->_get_account_id('1100');

		if (!$cash_id || !$receivable_id) return;

		// Debit Cash/Bank
		$debit = array(
			'transaction_id' => $txn_id,
			'transaction_date' => $today,
			'account_id' => $cash_id,
			'debit_amount' => $amount,
			'credit_amount' => 0,
			'reference_type' => 'RECEIPT',
			'reference_no' => $receipt_no,
			'patient_no' => $patient_no,
			'description' => 'Payment received via ' . $payment_method,
			'created_by' => $cashier_id
		);
		if ($bst !== null) { $debit['billing_subject_type'] = $bst; }
		if ($bsid !== null) { $debit['billing_subject_id'] = $bsid; }
		$this->db->insert('financial_ledger', $debit);

		// Credit Receivable
		$credit = array(
			'transaction_id' => $txn_id,
			'transaction_date' => $today,
			'account_id' => $receivable_id,
			'debit_amount' => 0,
			'credit_amount' => $amount,
			'reference_type' => 'RECEIPT',
			'reference_no' => $receipt_no,
			'patient_no' => $patient_no,
			'description' => 'Receivable reduced',
			'created_by' => $cashier_id
		);
		if ($bst !== null) { $credit['billing_subject_type'] = $bst; }
		if ($bsid !== null) { $credit['billing_subject_id'] = $bsid; }
		$this->db->insert('financial_ledger', $credit);
	}

	/**
	 * Get account ID by code
	 */
	private function _get_account_id($code)
	{
		$this->db->select('account_id');
		$this->db->where('account_code', $code);
		$row = $this->db->get('chart_of_accounts')->row();
		return $row ? $row->account_id : null;
	}

	/* ================================================================== */
	/*  AUDIT LOGGING                                                     */
	/* ================================================================== */

	/**
	 * Log payment to audit trail
	 */
	private function _log_payment_audit($receipt_no, $invoice_no, $patient_no, $amount, $method, $cashier_id, $balance_before, $balance_after)
	{
		if (!$this->db->table_exists('financial_audit_log')) {
			// Create table if not exists
			$this->db->query("
				CREATE TABLE IF NOT EXISTS financial_audit_log (
					audit_id INT AUTO_INCREMENT PRIMARY KEY,
					action_type VARCHAR(20) NOT NULL,
					receipt_no VARCHAR(50),
					invoice_no VARCHAR(50),
					patient_no VARCHAR(25),
					amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					balance_before DECIMAL(12,2),
					balance_after DECIMAL(12,2),
					description TEXT,
					performed_by VARCHAR(25),
					performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					ip_address VARCHAR(45),
					INDEX idx_action (action_type),
					INDEX idx_invoice (invoice_no),
					INDEX idx_date (performed_at)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}

		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';

		$this->db->insert('financial_audit_log', array(
			'action_type' => 'PAYMENT',
			'receipt_no' => $receipt_no,
			'invoice_no' => $invoice_no,
			'patient_no' => $patient_no,
			'amount' => $amount,
			'balance_before' => $balance_before,
			'balance_after' => $balance_after,
			'description' => 'Payment via ' . $method,
			'performed_by' => $cashier_id,
			'ip_address' => $ip
		));
	}

	/* ================================================================== */
	/*  RECONCILIATION                                                    */
	/* ================================================================== */

	/**
	 * Daily reconciliation check
	 * Compares services created vs billed vs paid
	 */
	public function get_daily_reconciliation($date = null)
	{
		if (!$date) $date = date('Y-m-d');

		$result = array(
			'date' => $date,
			'services_created' => 0,
			'services_billed' => 0,
			'services_pending' => 0,
			'invoices_created' => 0,
			'invoices_paid' => 0,
			'invoices_partial' => 0,
			'invoices_unpaid' => 0,
			'total_billed' => 0,
			'total_collected' => 0,
			'total_outstanding' => 0,
			'discrepancies' => array(),
			'lab_governance_shadow' => array(
				'open_total' => 0,
				'by_type' => array(),
				'recent' => array(),
			),
			'lab_governance_invariants' => array(
				'scanned' => 0,
				'violations' => 0,
				'by_type' => array(),
				'sample' => array(),
			)
		);

		// Services in queue
		if ($this->db->table_exists('billing_queue')) {
			$this->db->where('DATE(created_at)', $date);
			$this->db->where('InActive', 0);
			$result['services_created'] = $this->db->count_all_results('billing_queue');

			$this->db->where('DATE(billed_at)', $date);
			$this->db->where('status', 'BILLED');
			$this->db->where('InActive', 0);
			$result['services_billed'] = $this->db->count_all_results('billing_queue');

			$this->db->where('status', 'PENDING');
			$this->db->where('InActive', 0);
			$result['services_pending'] = $this->db->count_all_results('billing_queue');
		}

		// Invoices
		$this->db->where('DATE(dDate)', $date);
		$this->db->where('InActive', 0);
		$result['invoices_created'] = $this->db->count_all_results('iop_billing');

		$this->db->select('COALESCE(SUM(total_amount), 0) as total');
		$this->db->where('DATE(dDate)', $date);
		$this->db->where('InActive', 0);
		$result['total_billed'] = (float)$this->db->get('iop_billing')->row()->total;

		// Payments (amountPaid = actual payment amount)
		$this->db->select('COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('DATE(dDate)', $date);
		$this->db->where('InActive', 0);
		$result['total_collected'] = (float)$this->db->get('iop_receipt')->row()->total;

		// Outstanding
		$result['total_outstanding'] = $this->get_total_outstanding();

		// Lab governance invariants (cross-module consistency): compute for the day and optionally emit telemetry.
		if ($this->db->table_exists('billing_transactions') && $this->db->table_exists('billing_dispositions')) {
			try {
				$limit = 500;
				$sql = "SELECT 
					bt.txn_id,
					bt.item_ref,
					bt.invoice_no,
					bt.payment_status,
					bt.net_amount,
					(
						SELECT bd.to_state
						FROM billing_dispositions bd
						WHERE bd.txn_id = bt.txn_id AND bd.InActive = 0
						ORDER BY bd.created_at DESC, bd.disp_id DESC
						LIMIT 1
					) AS disp_state
				FROM billing_transactions bt
				WHERE bt.InActive = 0
				AND bt.department = 'LABORATORY'
				AND DATE(bt.created_at) = ?
				ORDER BY bt.txn_id DESC
				LIMIT " . (int)$limit;
				$rows = $this->db->query($sql, array($date))->result_array();
				$result['lab_governance_invariants']['scanned'] = is_array($rows) ? count($rows) : 0;

				$today = date('Y-m-d');
				$canLog = ($date === $today && $this->db->table_exists('billing_reconciliation_log'));
				$existing = array();
				if ($canLog) {
					$ex = $this->db->query(
						"SELECT issue_type, record_ref FROM billing_reconciliation_log WHERE recon_date = ? AND resolved = 0 AND issue_type LIKE 'LAB_GOV_%'",
						array($today)
					)->result_array();
					if (is_array($ex)) {
						foreach ($ex as $e) {
							$k = (string)$e['issue_type'] . '|' . (string)$e['record_ref'];
							$existing[$k] = true;
						}
					}
				}

				// Lazy-load disposition logger only if we intend to log.
				if ($canLog) {
					try {
						$this->load->model('app/billing_disposition_model');
					} catch (Exception $e) {
						$canLog = false;
					}
				}

				$byType = array();
				$sample = array();
				$viol = 0;
				if (is_array($rows)) {
					foreach ($rows as $r) {
						$txnId = isset($r['txn_id']) ? (int)$r['txn_id'] : 0;
						if ($txnId <= 0) continue;
						$recordRef = 'txn_id:' . $txnId;
						$disp = isset($r['disp_state']) ? strtoupper(trim((string)$r['disp_state'])) : '';
						$inv = isset($r['invoice_no']) ? trim((string)$r['invoice_no']) : '';
						$pay = isset($r['payment_status']) ? strtoupper(trim((string)$r['payment_status'])) : '';
						$net = isset($r['net_amount']) ? (float)$r['net_amount'] : 0.0;

						$issues = array();
						if ($disp === '') {
							$issues[] = 'LAB_GOV_MISSING_DISPOSITION';
						}
						if ($disp === 'EXTERNAL_NON_BILLABLE' && $inv !== '') {
							$issues[] = 'LAB_GOV_INVOICE_ON_NONBILLABLE';
						}
						if (in_array($disp, array('EXTERNAL_NON_BILLABLE','WRITE_OFF','APPROVED_WAIVER'), true) && in_array($pay, array('PAID','PARTIAL'), true)) {
							$issues[] = 'LAB_GOV_PAID_ON_NONPAYABLE';
						}
						if ($pay === 'WAIVED' && $disp !== 'APPROVED_WAIVER') {
							$issues[] = 'LAB_GOV_WAIVED_WITHOUT_DISPOSITION';
						}
						if ($disp === 'APPROVED_WAIVER' && $pay !== '' && $pay !== 'WAIVED') {
							$issues[] = 'LAB_GOV_DISPOSITION_WAIVER_NOT_MIRRORED';
						}
						if ($disp === 'EXTERNAL_NON_BILLABLE' && $net > 0) {
							$issues[] = 'LAB_GOV_NONBILLABLE_NET_AMOUNT';
						}

						if (empty($issues)) continue;
						$viol++;
						foreach ($issues as $it) {
							$byType[$it] = isset($byType[$it]) ? ((int)$byType[$it] + 1) : 1;
							if (count($sample) < 10) {
								$sample[] = array(
									'issue_type' => $it,
									'record_ref' => $recordRef,
									'disp_state' => $disp !== '' ? $disp : null,
									'invoice_no' => $inv !== '' ? $inv : null,
									'payment_status' => $pay !== '' ? $pay : null,
									'net_amount' => $net,
								);
							}

							if ($canLog) {
								$key = $it . '|' . $recordRef;
								if (!isset($existing[$key])) {
									$existing[$key] = true;
									$this->billing_disposition_model->log_shadow_issue($it, $recordRef, array(
										'schema_version' => 1,
										'recon_date' => $today,
										'txn_id' => $txnId,
										'disp_state' => $disp !== '' ? $disp : null,
										'invoice_no' => $inv !== '' ? $inv : null,
										'payment_status' => $pay !== '' ? $pay : null,
										'net_amount' => $net,
									), 'SYSTEM');
								}
							}
						}
					}
				}
				$result['lab_governance_invariants']['violations'] = $viol;
				$result['lab_governance_invariants']['by_type'] = $byType;
				$result['lab_governance_invariants']['sample'] = $sample;
			} catch (Exception $e) {
			}
		}

		if ($this->db->table_exists('billing_reconciliation_log')) {
			try {
				$this->db->select('issue_type, COUNT(*) as c');
				$this->db->from('billing_reconciliation_log');
				$this->db->where('recon_date', $date);
				$this->db->where('resolved', 0);
				$this->db->like('issue_type', 'LAB_', 'after');
				$this->db->group_by('issue_type');
				$q = $this->db->get();
				$rows = $q ? $q->result_array() : array();
				$openTotal = 0;
				$byType = array();
				foreach ($rows as $r) {
					$t = isset($r['issue_type']) ? (string)$r['issue_type'] : '';
					$c = isset($r['c']) ? (int)$r['c'] : 0;
					if ($t === '' || $c <= 0) continue;
					$byType[$t] = $c;
					$openTotal += $c;
				}
				$result['lab_governance_shadow']['open_total'] = $openTotal;
				$result['lab_governance_shadow']['by_type'] = $byType;

				$this->db->select('recon_id, issue_type, record_ref, created_at');
				$this->db->from('billing_reconciliation_log');
				$this->db->where('recon_date', $date);
				$this->db->like('issue_type', 'LAB_', 'after');
				$this->db->order_by('recon_id', 'DESC');
				$this->db->limit(10);
				$q2 = $this->db->get();
				$result['lab_governance_shadow']['recent'] = $q2 ? $q2->result_array() : array();
			} catch (Exception $e) {
			}
		}

		return $result;
	}

	/**
	 * Get total outstanding across all invoices
	 */
	public function get_total_outstanding()
	{
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$sql = "
			SELECT COALESCE(SUM(
				b.total_amount - {$discExpr} - COALESCE((
					SELECT SUM(r.amountPaid) FROM iop_receipt r
					WHERE r.invoice_no = b.invoice_no AND r.InActive = 0
				), 0)
			), 0) AS outstanding
			FROM iop_billing b
			WHERE b.InActive = 0
			AND (b.total_amount - {$discExpr}) > COALESCE((
				SELECT SUM(r2.amountPaid) FROM iop_receipt r2
				WHERE r2.invoice_no = b.invoice_no AND r2.InActive = 0
			), 0)
		";
		$row = $this->db->query($sql)->row();
		return $row ? (float)$row->outstanding : 0;
	}

	/* ================================================================== */
	/*  DASHBOARD QUERIES                                                 */
	/* ================================================================== */

	/**
	 * Get cashier dashboard summary
	 */
	public function get_cashier_dashboard_summary()
	{
		$today = date('Y-m-d');

		// Today's invoices
		$this->db->where('DATE(dDate)', $today);
		$this->db->where('InActive', 0);
		$invoices_today = $this->db->count_all_results('iop_billing');

		// Today's collections (amountPaid = actual payment amount)
		$this->db->select('COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('DATE(dDate)', $today);
		$this->db->where('InActive', 0);
		$collections_today = (float)$this->db->get('iop_receipt')->row()->total;

		// Unpaid invoices count
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$sql = "
			SELECT COUNT(*) as cnt FROM iop_billing b
			WHERE b.InActive = 0
			AND (b.total_amount - {$discExpr}) > COALESCE((
				SELECT SUM(r.amountPaid) FROM iop_receipt r
				WHERE r.invoice_no = b.invoice_no AND r.InActive = 0
			), 0)
		";
		$unpaid_count = (int)$this->db->query($sql)->row()->cnt;

		// Total outstanding
		$outstanding = $this->get_total_outstanding();

		// Pending queue items
		$pending_queue = 0;
		if ($this->db->table_exists('billing_queue')) {
			$this->db->where('status', 'PENDING');
			$this->db->where('InActive', 0);
			$pending_queue = $this->db->count_all_results('billing_queue');
		}

		return array(
			'invoices_today' => $invoices_today,
			'collections_today' => $collections_today,
			'unpaid_count' => $unpaid_count,
			'outstanding' => $outstanding,
			'pending_queue' => $pending_queue
		);
	}

	/**
	 * Get recent invoices for dashboard
	 */
	public function get_recent_invoices($limit = 20, $date = null)
	{
		if (!$date) $date = date('Y-m-d');
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$sql = "
			SELECT 
				b.invoice_no,
				b.iop_id,
				b.patient_no,
				b.total_amount,
				{$discExpr} as discount,
				(b.total_amount - {$discExpr}) as net_amount,
				COALESCE((
					SELECT SUM(r.amountPaid) FROM iop_receipt r
					WHERE r.invoice_no = b.invoice_no AND r.InActive = 0
				), 0) as amount_paid,
				b.dDate as invoice_date,
				b.payment_status,
				b.payer_type,
				CONCAT(p.firstname, ' ', p.lastname) as patient_name
			FROM iop_billing b
			LEFT JOIN patient_personal_info p ON p.patient_no = b.patient_no
			WHERE b.InActive = 0 AND DATE(b.dDate) = ?
			ORDER BY b.dDate DESC
			LIMIT ?
		";

		return $this->db->query($sql, array($date, $limit))->result();
	}

	/**
	 * Get unpaid invoices
	 */
	public function get_unpaid_invoices($limit = 50)
	{
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$sql = "
			SELECT 
				b.invoice_no,
				b.iop_id,
				b.patient_no,
				b.total_amount,
				{$discExpr} as discount,
				(b.total_amount - {$discExpr}) as net_amount,
				COALESCE((
					SELECT SUM(r.amountPaid) FROM iop_receipt r
					WHERE r.invoice_no = b.invoice_no AND r.InActive = 0
				), 0) as amount_paid,
				((b.total_amount - {$discExpr}) - COALESCE((
					SELECT SUM(r2.amountPaid) FROM iop_receipt r2
					WHERE r2.invoice_no = b.invoice_no AND r2.InActive = 0
				), 0)) as balance,
				b.dDate as invoice_date,
				b.payer_type,
				CONCAT(p.firstname, ' ', p.lastname) as patient_name
			FROM iop_billing b
			LEFT JOIN patient_personal_info p ON p.patient_no = b.patient_no
			WHERE b.InActive = 0
			AND (b.total_amount - {$discExpr}) > COALESCE((
				SELECT SUM(r3.amountPaid) FROM iop_receipt r3
				WHERE r3.invoice_no = b.invoice_no AND r3.InActive = 0
			), 0)
			ORDER BY b.dDate DESC
			LIMIT ?
		";

		return $this->db->query($sql, array($limit))->result();
	}

	/* ================================================================== */
	/*  SERVICE INTEGRATION - Auto-queue from service modules            */
	/* ================================================================== */

	/**
	 * Queue laboratory test for billing
	 * Called when lab test is ordered
	 */
	public function queue_lab_test($iop_id, $patient_no, $lab_id, $test_name, $rate, $payer_type = 'CASH', $requested_by = null)
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'LAB',
			'item_id' => $lab_id,
			'item_name' => $test_name,
			'quantity' => 1,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'source_module' => 'laboratory',
			'source_ref' => 'iop_id:' . (string)$iop_id . ':LAB:' . $lab_id,
			'requested_by' => $requested_by
		));
	}

	/**
	 * Queue medication for billing
	 * Called when medication is dispensed
	 */
	public function queue_medication($iop_id, $patient_no, $med_id, $drug_name, $qty, $rate, $payer_type = 'CASH', $requested_by = null)
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'PHARMACY',
			'item_id' => $med_id,
			'item_name' => $drug_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'source_module' => 'PHARMACY',
			'source_ref' => 'iop_id:' . (string)$iop_id . ':iop_medication:' . $med_id,
			'requested_by' => $requested_by
		));
	}

	/**
	 * Queue sonography/imaging for billing
	 */
	public function queue_sonography($iop_id, $patient_no, $sono_id, $item_name, $rate, $payer_type = 'CASH', $requested_by = null)
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'SONOGRAPHY',
			'item_id' => $sono_id,
			'item_name' => $item_name,
			'quantity' => 1,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'source_module' => 'sonography',
			'source_ref' => 'SONO-' . $sono_id,
			'requested_by' => $requested_by
		));
	}

	/**
	 * Queue procedure for billing
	 */
	public function queue_procedure($iop_id, $patient_no, $proc_id, $proc_name, $rate, $payer_type = 'CASH', $requested_by = null)
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'PROCEDURE',
			'item_id' => $proc_id,
			'item_name' => $proc_name,
			'quantity' => 1,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'source_module' => 'procedure',
			'source_ref' => 'PROC-' . $proc_id,
			'requested_by' => $requested_by
		));
	}

	/**
	 * Queue consultation fee
	 */
	public function queue_consultation($iop_id, $patient_no, $fee, $doctor_name = '', $payer_type = 'CASH')
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'CONSULTATION',
			'item_id' => $iop_id,
			'item_name' => 'Consultation' . ($doctor_name ? ' - ' . $doctor_name : ''),
			'quantity' => 1,
			'unit_price' => $fee,
			'payer_type' => $payer_type,
			'source_module' => 'consultation',
			'source_ref' => 'CONS-' . $iop_id
		));
	}

	/**
	 * Queue registration fee
	 */
	public function queue_registration($iop_id, $patient_no, $fee, $payer_type = 'CASH')
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'REGISTRATION',
			'item_id' => $iop_id,
			'item_name' => 'Registration Fee',
			'quantity' => 1,
			'unit_price' => $fee,
			'payer_type' => $payer_type,
			'source_module' => 'registration',
			'source_ref' => 'REG-' . $iop_id
		));
	}

	public function queue_detention($iop_id, $patient_no, $detention_start_at = null)
	{
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		if ($iop_id === '' || $patient_no === '') {
			return array('success' => false, 'error' => 'Missing iop_id or patient_no');
		}

		// Resolve payer type using the same logic as visit fee resolvers
		$payer_type = 'CASH';
		try {
			$this->load->model('app/billing_model');
			if (isset($this->billing_model) && method_exists($this->billing_model, 'determine_payer_type')) {
				$pt = strtoupper(trim((string)$this->billing_model->determine_payer_type($patient_no)));
				if ($pt !== '') {
					$payer_type = $pt;
				}
			}
		} catch (\Throwable $e) {
			$payer_type = 'CASH';
		}
		$payer_type_norm = strtoupper(trim((string)$payer_type));
		if ($payer_type_norm === '') { $payer_type_norm = 'CASH'; }

		// Resolve detention fee from Smart Billing configuration
		$this->load->model('app/smart_billing_model');
		if (!isset($this->smart_billing_model) || !method_exists($this->smart_billing_model, 'get_config')) {
			return array('success' => false, 'error' => 'Smart billing configuration not available');
		}
		$item_id = (int)$this->smart_billing_model->get_config('detention_fee_item_id', '0');
		$key_amt = ($payer_type_norm === 'NHIS') ? 'detention_fee_nhis' : 'detention_fee_cash';
		$amount = (float)$this->smart_billing_model->get_config($key_amt, '0');
		if ($amount <= 0.009) {
			// Configured as free for this payer type; treat as successful no-op
			return array('success' => true, 'skipped' => true, 'reason' => 'Detention fee configured as free for ' . $payer_type_norm);
		}

		$item_name = 'Detention / Observation';
		if ($item_id > 0) {
			try {
				$this->load->model('app/Price_engine_model', 'price_engine');
				if (isset($this->price_engine) && method_exists($this->price_engine, 'resolve')) {
					$pr = $this->price_engine->resolve(array(
						'item_type' => 'SERVICE',
						'item_id' => $item_id,
						'quantity' => 1,
						'patient_no' => $patient_no,
						'payer_type' => $payer_type_norm,
					));
					if (is_array($pr) && !empty($pr['ok']) && isset($pr['item_name']) && trim((string)$pr['item_name']) !== '') {
						$item_name = (string)$pr['item_name'];
					}
				}
			} catch (\Throwable $e) {
				// Fallback to default item_name
			}
		}

		$source_ref = 'DET-OPD-' . $iop_id;
		$data = array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'DETENTION',
			'item_id' => $item_id > 0 ? $item_id : $iop_id,
			'item_name' => $item_name,
			'quantity' => 1,
			'unit_price' => $amount,
			'payer_type' => $payer_type_norm,
			'source_module' => 'detention',
			'source_ref' => $source_ref,
		);
		if ($detention_start_at !== null && trim((string)$detention_start_at) !== '') {
			$data['service_datetime'] = (string)$detention_start_at;
		}
		return $this->add_to_billing_queue($data);
	}

	/**
	 * Queue room/admission charge
	 */
	public function queue_room_charge($iop_id, $patient_no, $room_id, $room_name, $days, $rate_per_day, $payer_type = 'CASH')
	{
		return $this->add_to_billing_queue(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'item_type' => 'ROOM',
			'item_id' => $room_id,
			'item_name' => 'Room Charge - ' . $room_name,
			'quantity' => $days,
			'unit_price' => $rate_per_day,
			'payer_type' => $payer_type,
			'source_module' => 'admission',
			'source_ref' => 'ROOM-' . $iop_id . '-' . $room_id
		));
	}

	/**
	 * Check if item is already in billing queue
	 */
	public function is_item_queued($source_module, $source_ref)
	{
		$this->db->where('source_module', $source_module);
		$this->db->where('source_ref', $source_ref);
		$this->db->where('InActive', 0);
		return $this->db->count_all_results('billing_queue') > 0;
	}

	/**
	 * Cancel queued item
	 */
	public function cancel_queued_item($queue_id, $reason = null)
	{
		$this->db->where('queue_id', $queue_id);
		$this->db->where('status', 'PENDING');
		return $this->db->update('billing_queue', array(
			'status' => 'CANCELLED',
			'notes' => $reason
		));
	}

	/* ================================================================== */
	/*  DUPLICATE PREVENTION                                              */
	/* ================================================================== */

	/**
	 * Check if invoice already exists for visit
	 */
	public function has_invoice($iop_id)
	{
		$this->db->where('iop_id', $iop_id);
		$this->db->where('InActive', 0);
		return $this->db->count_all_results('iop_billing') > 0;
	}

	/**
	 * Get existing invoice for visit
	 */
	public function get_visit_invoice($iop_id)
	{
		$this->db->where('iop_id', $iop_id);
		$this->db->where('InActive', 0);
		$this->db->order_by('dDate', 'DESC');
		$this->db->limit(1);
		return $this->db->get('iop_billing')->row();
	}

	/**
	 * Check if item is already billed (on any invoice)
	 */
	public function is_item_billed($source_module, $source_ref)
	{
		// Check billing queue
		$this->db->where('source_module', $source_module);
		$this->db->where('source_ref', $source_ref);
		$this->db->where('status', 'BILLED');
		$this->db->where('InActive', 0);
		if ($this->db->count_all_results('billing_queue') > 0) {
			return true;
		}

		// Also check iop_billable_item_lock if exists
		if ($this->db->table_exists('iop_billable_item_lock')) {
			$this->db->where('source_module', $source_module);
			$this->db->where('source_ref', $source_ref);
			$this->db->where('InActive', 0);
			if ($this->db->count_all_results('iop_billable_item_lock') > 0) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Lock item to prevent duplicate billing
	 */
	public function lock_billed_item($invoice_no, $source_module, $source_ref)
	{
		if (!$this->db->table_exists('iop_billable_item_lock')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS iop_billable_item_lock (
					lock_id INT AUTO_INCREMENT PRIMARY KEY,
					invoice_no VARCHAR(50) NOT NULL,
					source_module VARCHAR(50) NOT NULL,
					source_ref VARCHAR(100) NOT NULL,
					locked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					locked_by VARCHAR(25),
					InActive TINYINT(1) DEFAULT 0,
					UNIQUE KEY uq_src_active (source_module, source_ref, InActive),
					INDEX idx_invoice (invoice_no)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}

		// Best-effort: ensure unique constraint is enforced as (source_module, source_ref, InActive)
		if ($this->db->table_exists('iop_billable_item_lock')) {
			$prevDebug = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($prevDebug !== null) { $this->db->db_debug = false; }
			try {
				$idxOld = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_lock'");
				if ($idxOld && $idxOld->num_rows() > 0) {
					$this->db->query("ALTER TABLE `iop_billable_item_lock` DROP INDEX `uq_lock`");
				}
				$idxOld2 = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_src_inv_active'");
				if ($idxOld2 && $idxOld2->num_rows() > 0) {
					$this->db->query("ALTER TABLE `iop_billable_item_lock` DROP INDEX `uq_src_inv_active`");
				}
				$idxOld3 = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_src_inv'");
				if ($idxOld3 && $idxOld3->num_rows() > 0) {
					$this->db->query("ALTER TABLE `iop_billable_item_lock` DROP INDEX `uq_src_inv`");
				}
				$idxNew = $this->db->query("SHOW INDEX FROM `iop_billable_item_lock` WHERE Key_name = 'uq_src_active'");
				if (!$idxNew || $idxNew->num_rows() === 0) {
					$this->db->query("ALTER TABLE `iop_billable_item_lock` ADD UNIQUE KEY `uq_src_active` (`source_module`,`source_ref`,`InActive`)");
				}
			} catch (\Throwable $e) {
			}
			if ($prevDebug !== null) { $this->db->db_debug = $prevDebug; }
		}

		// Check if already locked
		$this->db->where('source_module', $source_module);
		$this->db->where('source_ref', $source_ref);
		$this->db->where('InActive', 0);
		if ($this->db->get('iop_billable_item_lock')->row()) {
			return false; // Already locked
		}

		return $this->db->insert('iop_billable_item_lock', array(
			'invoice_no' => $invoice_no,
			'source_module' => $source_module,
			'source_ref' => $source_ref,
			'locked_by' => $this->session->userdata('user_id')
		));
	}

	/* ================================================================== */
	/*  PHASE 16-20: AUDIT LOGS & PERFORMANCE                            */
	/* ================================================================== */

	/**
	 * Ensure performance indexes exist on billing tables
	 */
	public function ensure_billing_performance_indexes()
	{
		// Add indexes to iop_billing for faster queries
		$indexes = array(
			array('iop_billing', 'idx_billing_date', 'dDate'),
			array('iop_billing', 'idx_billing_patient', 'patient_no'),
			array('iop_billing', 'idx_billing_status', 'payment_status'),
			array('iop_billing', 'idx_billing_payer', 'payer_type'),
			array('iop_receipt', 'idx_receipt_date', 'dDate'),
			array('iop_receipt', 'idx_receipt_invoice', 'invoice_no'),
			array('iop_receipt', 'idx_receipt_patient', 'patient_no'),
			array('iop_billing_t', 'idx_billingt_invoice', 'invoice_no'),
			array('iop_billing_t', 'idx_billingt_iop', 'iop_id')
		);

		foreach ($indexes as $idx) {
			$table = $idx[0];
			$index_name = $idx[1];
			$column = $idx[2];

			if (!$this->db->table_exists($table)) continue;

			// Check if index exists
			$check = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", array($index_name));
			if ($check->num_rows() == 0) {
				// Check if column exists before adding index
				$col_check = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE ?", array($column));
				if ($col_check->num_rows() > 0) {
					$this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$column}`)");
				}
			}
		}
	}

	/**
	 * Ensure billing_audit_log table has all required columns
	 */
	public function ensure_audit_log_schema()
	{
		static $checked = false;
		if ($checked) return;
		$checked = true;

		if (!$this->db->table_exists('billing_audit_log')) {
			return; // Table doesn't exist, will be created by log_billing_audit
		}

		// Add missing columns if they don't exist
		$columns_to_add = array(
			'action_type' => "ALTER TABLE billing_audit_log ADD COLUMN action_type VARCHAR(50) DEFAULT 'UNKNOWN' AFTER audit_id",
			'entity_type' => "ALTER TABLE billing_audit_log ADD COLUMN entity_type VARCHAR(50) NULL AFTER action_type",
			'entity_id' => "ALTER TABLE billing_audit_log ADD COLUMN entity_id VARCHAR(100) NULL AFTER entity_type",
			'invoice_no' => "ALTER TABLE billing_audit_log ADD COLUMN invoice_no VARCHAR(50) NULL AFTER entity_id",
			'patient_no' => "ALTER TABLE billing_audit_log ADD COLUMN patient_no VARCHAR(25) NULL AFTER invoice_no",
			'amount' => "ALTER TABLE billing_audit_log ADD COLUMN amount DECIMAL(12,2) NULL AFTER patient_no",
			'old_value' => "ALTER TABLE billing_audit_log ADD COLUMN old_value TEXT NULL AFTER amount",
			'new_value' => "ALTER TABLE billing_audit_log ADD COLUMN new_value TEXT NULL AFTER old_value",
			'description' => "ALTER TABLE billing_audit_log ADD COLUMN description TEXT NULL AFTER new_value",
			'performed_by' => "ALTER TABLE billing_audit_log ADD COLUMN performed_by VARCHAR(25) NULL AFTER description",
			'performed_at' => "ALTER TABLE billing_audit_log ADD COLUMN performed_at DATETIME DEFAULT CURRENT_TIMESTAMP AFTER performed_by",
			'ip_address' => "ALTER TABLE billing_audit_log ADD COLUMN ip_address VARCHAR(45) NULL AFTER performed_at",
			'user_agent' => "ALTER TABLE billing_audit_log ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip_address"
		);

		foreach ($columns_to_add as $col => $sql) {
			if (!$this->column_exists('billing_audit_log', $col)) {
				$this->db->query($sql);
			}
		}
	}

	/**
	 * Log billing action to audit trail
	 */
	public function log_billing_audit($action, $data = array())
	{
		if (!$this->db->table_exists('billing_audit_log')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS billing_audit_log (
					audit_id INT AUTO_INCREMENT PRIMARY KEY,
					action_type VARCHAR(50) NOT NULL,
					entity_type VARCHAR(50),
					entity_id VARCHAR(100),
					invoice_no VARCHAR(50),
					patient_no VARCHAR(25),
					amount DECIMAL(12,2),
					old_value TEXT,
					new_value TEXT,
					description TEXT,
					performed_by VARCHAR(25),
					performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					ip_address VARCHAR(45),
					user_agent VARCHAR(255),
					INDEX idx_action (action_type),
					INDEX idx_entity (entity_type, entity_id),
					INDEX idx_invoice (invoice_no),
					INDEX idx_date (performed_at),
					INDEX idx_user (performed_by)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		} else {
			// Ensure schema is up to date
			$this->ensure_audit_log_schema();
		}

		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
		$ua = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : '';

		$insert = array(
			'action_type' => $action,
			'entity_type' => isset($data['entity_type']) ? $data['entity_type'] : null,
			'entity_id' => isset($data['entity_id']) ? $data['entity_id'] : null,
			'invoice_no' => isset($data['invoice_no']) ? $data['invoice_no'] : null,
			'patient_no' => isset($data['patient_no']) ? $data['patient_no'] : null,
			'amount' => isset($data['amount']) ? $data['amount'] : null,
			'old_value' => isset($data['old_value']) ? (is_array($data['old_value']) ? json_encode($data['old_value']) : $data['old_value']) : null,
			'new_value' => isset($data['new_value']) ? (is_array($data['new_value']) ? json_encode($data['new_value']) : $data['new_value']) : null,
			'description' => isset($data['description']) ? $data['description'] : null,
			'performed_by' => $this->session->userdata('user_id'),
			'ip_address' => $ip,
			'user_agent' => $ua
		);

		return $this->db->insert('billing_audit_log', $insert);
	}

	/**
	 * Get audit log entries
	 */
	public function get_audit_log($filters = array(), $limit = 100, $offset = 0)
	{
		if (!$this->db->table_exists('billing_audit_log')) {
			return array();
		}

		// Ensure required columns exist, add them if missing
		$this->ensure_audit_log_schema();

		$this->db->select('a.*, u.firstname, u.lastname');
		$this->db->from('billing_audit_log a');
		$this->db->join('users u', 'u.user_id = a.performed_by', 'left');

		if (!empty($filters['action_type'])) {
			$this->db->where('a.action_type', $filters['action_type']);
		}
		if (!empty($filters['invoice_no'])) {
			$this->db->where('a.invoice_no', $filters['invoice_no']);
		}
		if (!empty($filters['patient_no'])) {
			$this->db->where('a.patient_no', $filters['patient_no']);
		}
		if (!empty($filters['date_from'])) {
			$this->db->where('DATE(a.performed_at) >=', $filters['date_from']);
		}
		if (!empty($filters['date_to'])) {
			$this->db->where('DATE(a.performed_at) <=', $filters['date_to']);
		}
		if (!empty($filters['performed_by'])) {
			$this->db->where('a.performed_by', $filters['performed_by']);
		}

		$this->db->order_by('a.performed_at', 'DESC');
		$this->db->limit($limit, $offset);

		return $this->db->get()->result();
	}

	/**
	 * Get billing statistics for dashboard
	 */
	public function get_billing_statistics($date_from = null, $date_to = null)
	{
		if (!$date_from) $date_from = date('Y-m-01');
		if (!$date_to) $date_to = date('Y-m-d');

		$stats = array(
			'period' => array('from' => $date_from, 'to' => $date_to),
			'invoices' => array('count' => 0, 'total' => 0),
			'payments' => array('count' => 0, 'total' => 0),
			'outstanding' => 0,
			'by_payer_type' => array(),
			'by_payment_method' => array(),
			'daily_trend' => array()
		);

		// Invoice stats
		$this->db->select('COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total');
		$this->db->where('DATE(dDate) >=', $date_from);
		$this->db->where('DATE(dDate) <=', $date_to);
		$this->db->where('InActive', 0);
		$inv = $this->db->get('iop_billing')->row();
		$stats['invoices']['count'] = (int)$inv->cnt;
		$stats['invoices']['total'] = (float)$inv->total;

		// Payment stats (amountPaid = actual payment amount)
		$this->db->select('COUNT(*) as cnt, COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('DATE(dDate) >=', $date_from);
		$this->db->where('DATE(dDate) <=', $date_to);
		$this->db->where('InActive', 0);
		$pay = $this->db->get('iop_receipt')->row();
		$stats['payments']['count'] = (int)$pay->cnt;
		$stats['payments']['total'] = (float)$pay->total;

		// Outstanding
		$stats['outstanding'] = $this->get_total_outstanding();

		// By payer type
		if ($this->column_exists('iop_billing', 'payer_type')) {
			$this->db->select('payer_type, COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total');
			$this->db->where('DATE(dDate) >=', $date_from);
			$this->db->where('DATE(dDate) <=', $date_to);
			$this->db->where('InActive', 0);
			$this->db->group_by('payer_type');
			$payers = $this->db->get('iop_billing')->result();
			foreach ($payers as $p) {
				$stats['by_payer_type'][$p->payer_type ?: 'CASH'] = array(
					'count' => (int)$p->cnt,
					'total' => (float)$p->total
				);
			}
		}

		// By payment method (amountPaid = actual payment amount)
		$this->db->select('payment_type, COUNT(*) as cnt, COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('DATE(dDate) >=', $date_from);
		$this->db->where('DATE(dDate) <=', $date_to);
		$this->db->where('InActive', 0);
		$this->db->group_by('payment_type');
		$methods = $this->db->get('iop_receipt')->result();
		foreach ($methods as $m) {
			$stats['by_payment_method'][$m->payment_type ?: 'CASH'] = array(
				'count' => (int)$m->cnt,
				'total' => (float)$m->total
			);
		}

		// Daily trend (last 7 days)
		$this->db->select('DATE(dDate) as day, COUNT(*) as invoices, COALESCE(SUM(total_amount), 0) as billed');
		$this->db->where('DATE(dDate) >=', date('Y-m-d', strtotime('-7 days')));
		$this->db->where('InActive', 0);
		$this->db->group_by('DATE(dDate)');
		$this->db->order_by('day', 'ASC');
		$trend = $this->db->get('iop_billing')->result();
		foreach ($trend as $t) {
			$stats['daily_trend'][$t->day] = array(
				'invoices' => (int)$t->invoices,
				'billed' => (float)$t->billed
			);
		}

		return $stats;
	}

	/**
	 * Detect billing discrepancies
	 */
	public function detect_discrepancies()
	{
		$issues = array();

		// 1. Invoices with negative balance (overpaid)
		$sql = "
			SELECT b.invoice_no, b.patient_no, b.total_amount,
				COALESCE((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0) as paid
			FROM iop_billing b
			WHERE b.InActive = 0
			HAVING paid > b.total_amount
			LIMIT 50
		";
		$overpaid = $this->db->query($sql)->result();
		foreach ($overpaid as $o) {
			$issues[] = array(
				'type' => 'OVERPAID',
				'severity' => 'HIGH',
				'invoice_no' => $o->invoice_no,
				'patient_no' => $o->patient_no,
				'message' => 'Invoice overpaid by GHS ' . number_format($o->paid - $o->total_amount, 2)
			);
		}

		// 2. Orphan receipts (no matching invoice)
		$sql = "
			SELECT r.receipt_no, r.invoice_no, r.total_amount
			FROM iop_receipt r
			LEFT JOIN iop_billing b ON b.invoice_no = r.invoice_no
			WHERE r.InActive = 0 AND b.invoice_no IS NULL
			LIMIT 50
		";
		$orphans = $this->db->query($sql)->result();
		foreach ($orphans as $o) {
			$issues[] = array(
				'type' => 'ORPHAN_RECEIPT',
				'severity' => 'MEDIUM',
				'receipt_no' => $o->receipt_no,
				'invoice_no' => $o->invoice_no,
				'message' => 'Receipt has no matching invoice'
			);
		}

		// 3. Duplicate invoice numbers
		$sql = "
			SELECT invoice_no, COUNT(*) as cnt
			FROM iop_billing
			WHERE InActive = 0
			GROUP BY invoice_no
			HAVING cnt > 1
			LIMIT 50
		";
		$dupes = $this->db->query($sql)->result();
		foreach ($dupes as $d) {
			$issues[] = array(
				'type' => 'DUPLICATE_INVOICE',
				'severity' => 'HIGH',
				'invoice_no' => $d->invoice_no,
				'message' => 'Invoice number appears ' . $d->cnt . ' times'
			);
		}

		// 4. Zero-amount invoices
		$this->db->where('total_amount', 0);
		$this->db->where('InActive', 0);
		$this->db->where('DATE(dDate) >=', date('Y-m-d', strtotime('-30 days')));
		$zeros = $this->db->get('iop_billing')->result();
		foreach ($zeros as $z) {
			$issues[] = array(
				'type' => 'ZERO_INVOICE',
				'severity' => 'LOW',
				'invoice_no' => $z->invoice_no,
				'patient_no' => isset($z->patient_no) ? $z->patient_no : '',
				'message' => 'Invoice has zero total amount'
			);
		}

		// 5. Header total doesn't match line items sum (CRITICAL)
		$sql = "
			SELECT b.invoice_no, b.patient_no, b.total_amount as header_total,
				COALESCE((SELECT SUM(t.amount) FROM iop_billing_t t WHERE t.invoice_no = b.invoice_no AND t.InActive = 0), 0) as line_total
			FROM iop_billing b
			WHERE b.InActive = 0
			HAVING ABS(header_total - line_total) > 0.01
			LIMIT 50
		";
		$mismatches = $this->db->query($sql)->result();
		foreach ($mismatches as $m) {
			$issues[] = array(
				'type' => 'HEADER_TOTAL_MISMATCH',
				'severity' => 'CRITICAL',
				'invoice_no' => $m->invoice_no,
				'patient_no' => $m->patient_no,
				'message' => 'Header total (GHS ' . number_format($m->header_total, 2) . ') does not match line items sum (GHS ' . number_format($m->line_total, 2) . ')'
			);
		}

		return $issues;
	}

	/**
	 * Fix common discrepancies automatically
	 * 
	 * IMPORTANT: Fix order matters!
	 * 1. HEADER_TOTAL_MISMATCH first - sync header with line items (source of truth)
	 * 2. PAYMENT_STATUS - recalculate based on corrected totals
	 * 3. OVERPAID - handle after totals are correct
	 */
	public function auto_fix_discrepancies($type = null)
	{
		$this->load->model('app/billing_model');
		$fixed = 0;

		// Fix HEADER_TOTAL_MISMATCH - header total doesn't match line items sum
		// Line items are the SOURCE OF TRUTH - header must match line items sum
		if ($type === null || $type === 'HEADER_TOTAL_MISMATCH') {
			$sql = "
				SELECT b.invoice_no, b.patient_no, b.total_amount as header_total,
					COALESCE((SELECT SUM(t.amount) FROM iop_billing_t t WHERE t.invoice_no = b.invoice_no AND t.InActive = 0), 0) as line_total
				FROM iop_billing b
				WHERE b.InActive = 0
				HAVING ABS(header_total - line_total) > 0.01
			";
			$mismatches = $this->db->query($sql)->result();

			foreach ($mismatches as $m) {
				$settlement = $this->billing_model->get_invoice_settlement((string)$m->invoice_no);
				if (!empty($settlement['is_settled'])) {
					continue;
				}
				// Update header total to match line items sum
				$this->db->where('invoice_no', $m->invoice_no);
				$this->db->update('iop_billing', array('total_amount' => $m->line_total));

				$this->log_billing_audit('AUTO_FIX', array(
					'entity_type' => 'INVOICE',
					'entity_id' => $m->invoice_no,
					'invoice_no' => $m->invoice_no,
					'patient_no' => isset($m->patient_no) ? $m->patient_no : null,
					'amount' => $m->line_total,
					'old_value' => number_format($m->header_total, 2),
					'new_value' => number_format($m->line_total, 2),
					'description' => 'Synced header total (' . number_format($m->header_total, 2) . ') to line items sum (' . number_format($m->line_total, 2) . ')'
				));

				// Also update payment status for this invoice
				$this->update_payment_status($m->invoice_no);

				$fixed++;
			}
		}

		// Fix payment_status mismatches
		if ($type === null || $type === 'PAYMENT_STATUS') {
			$sql = "
				SELECT b.invoice_no, b.total_amount, b.payment_status,
					COALESCE((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0) as paid
				FROM iop_billing b
				WHERE b.InActive = 0
			";
			$invoices = $this->db->query($sql)->result();

			foreach ($invoices as $inv) {
				$settlement = $this->billing_model->get_invoice_settlement((string)$inv->invoice_no);
				if (!empty($settlement['is_settled'])) {
					continue;
				}
				$correct_status = 'UNPAID';
				if ($inv->paid >= $inv->total_amount && $inv->total_amount > 0) {
					$correct_status = 'PAID';
				} elseif ($inv->paid > 0) {
					$correct_status = 'PARTIAL';
				}

				if ($inv->payment_status !== $correct_status) {
					$this->db->where('invoice_no', $inv->invoice_no);
					$this->db->update('iop_billing', array('payment_status' => $correct_status));

					$this->log_billing_audit('AUTO_FIX', array(
						'entity_type' => 'INVOICE',
						'entity_id' => $inv->invoice_no,
						'invoice_no' => $inv->invoice_no,
						'old_value' => $inv->payment_status,
						'new_value' => $correct_status,
						'description' => 'Auto-corrected payment status'
					));

					$fixed++;
				}
			}
		}

		// Fix OVERPAID invoices - deactivate excess receipt amounts
		// This preserves line items as source of truth and marks excess payments as refundable
		if ($type === null || $type === 'OVERPAID') {
			$sql = "
				SELECT b.invoice_no, b.patient_no, b.total_amount,
					COALESCE((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0) as paid
				FROM iop_billing b
				WHERE b.InActive = 0
				HAVING paid > b.total_amount
			";
			$overpaid = $this->db->query($sql)->result();

			foreach ($overpaid as $o) {
				$settlement = $this->billing_model->get_invoice_settlement((string)$o->invoice_no);
				if (!empty($settlement['is_settled'])) {
					continue;
				}
				$overpayment = $o->paid - $o->total_amount;
				$amount_to_keep = $o->total_amount;
				
				// Get all receipts for this invoice, ordered by date (keep oldest)
				$this->db->where('invoice_no', $o->invoice_no);
				$this->db->where('InActive', 0);
				$this->db->order_by('dDate', 'ASC');
				$receipts = $this->db->get('iop_receipt')->result();
				
				$running_total = 0;
				foreach ($receipts as $receipt) {
					$receipt_amount = isset($receipt->amountPaid)
						? (float)$receipt->amountPaid
						: (float)$receipt->total_amount;
					
					if ($receipt_amount > 0 && $running_total >= $amount_to_keep - 0.01) {
						$this->db->where('receipt_no', $receipt->receipt_no);
						$this->db->update('iop_receipt', array(
							'InActive' => 1,
							'remarks' => 'AUTO-DEACTIVATED: Overpayment. Refund of GHS ' . number_format($receipt_amount, 2) . ' pending.'
						));
						
						$this->log_billing_audit('AUTO_FIX', array(
							'entity_type' => 'RECEIPT',
							'entity_id' => $receipt->receipt_no,
							'invoice_no' => $o->invoice_no,
							'patient_no' => $o->patient_no,
							'amount' => $receipt_amount,
							'description' => 'Deactivated excess receipt. Refund of GHS ' . number_format($receipt_amount, 2) . ' pending for patient.'
						));
						continue;
					}

					$running_total += $receipt_amount;
				}
				
				$this->update_payment_status($o->invoice_no);

				$this->log_billing_audit('AUTO_FIX', array(
					'entity_type' => 'INVOICE',
					'entity_id' => $o->invoice_no,
					'invoice_no' => $o->invoice_no,
					'patient_no' => $o->patient_no,
					'amount' => $overpayment,
					'old_value' => 'Overpaid by ' . number_format($overpayment, 2),
					'new_value' => 'PAID - excess receipts deactivated',
					'description' => 'Fixed overpayment of GHS ' . number_format($overpayment, 2) . '. Excess receipts deactivated for refund processing.'
				));

				$fixed++;
			}
		}

		return $fixed;
	}

	/**
	 * Detect header/line item total mismatches
	 */
	public function detect_total_mismatches()
	{
		$sql = "
			SELECT b.invoice_no, b.patient_no, b.total_amount as header_total,
				COALESCE((SELECT SUM(t.amount) FROM iop_billing_t t WHERE t.invoice_no = b.invoice_no AND t.InActive = 0), 0) as line_total,
				COALESCE((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0) as paid
			FROM iop_billing b
			WHERE b.InActive = 0
			HAVING ABS(header_total - line_total) > 0.01
		";
		return $this->db->query($sql)->result();
	}

	/**
	 * Sync invoice header total with line items
	 * Call this after adding/removing line items
	 */
	public function sync_invoice_total($invoice_no)
	{
		$this->load->model('app/billing_model');
		$settlement = $this->billing_model->get_invoice_settlement((string)$invoice_no);
		if (!empty($settlement['is_settled'])) {
			return false;
		}
		// Calculate sum of line items
		$this->db->select('COALESCE(SUM(amount), 0) as total');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$line_total = (float)$this->db->get('iop_billing_t')->row()->total;

		// Get current header total
		$this->db->select('total_amount');
		$this->db->where('invoice_no', $invoice_no);
		$header = $this->db->get('iop_billing')->row();
		
		if (!$header) return false;

		$old_total = (float)$header->total_amount;

		// Update if different
		if (abs($old_total - $line_total) > 0.01) {
			$this->db->where('invoice_no', $invoice_no);
			$this->db->update('iop_billing', array('total_amount' => $line_total));

			$this->log_billing_audit('SYNC_TOTAL', array(
				'entity_type' => 'INVOICE',
				'entity_id' => $invoice_no,
				'invoice_no' => $invoice_no,
				'old_value' => $old_total,
				'new_value' => $line_total,
				'description' => 'Synced header total with line items'
			));

			// Also update payment status
			$this->update_payment_status($invoice_no);

			return true;
		}

		return false;
	}

	/**
	 * Update payment status based on actual payments
	 */
	public function update_payment_status($invoice_no)
	{
		// Get invoice total
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$this->db->select('b.total_amount, ' . $discExpr . ' as discount', false);
		$this->db->from('iop_billing b');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$invoice = $this->db->get()->row();

		if (!$invoice) return false;

		$net_total = (float)$invoice->total_amount - (float)$invoice->discount;

		// Get total paid (amountPaid = actual payment amount per legacy convention)
		$this->db->select('COALESCE(SUM(amountPaid), 0) as paid');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$paid = (float)$this->db->get('iop_receipt')->row()->paid;

		// Determine correct status
		$status = 'UNPAID';
		$balance = $net_total - $paid;
		
		if ($balance <= 0.01 && $net_total > 0) {
			$status = 'PAID';
			$balance = 0;
		} elseif ($paid > 0) {
			$status = 'PARTIAL';
		}

		// Update
		$this->db->where('invoice_no', $invoice_no);
		$this->db->update('iop_billing', array(
			'payment_status' => $status,
			'balance_due' => $balance
		));

		return $status;
	}

	/* ================================================================== */
	/*  SERVICE GATE - Payment Before Service Enforcement               */
	/* ================================================================== */

	/**
	 * Check if service is released for a specific item
	 * Used by Laboratory, Pharmacy, Sonography to enforce payment gate
	 * 
	 * @param string $source_module Module name (LAB, PHARMACY, etc)
	 * @param string $source_ref Reference to source record ID
	 * @return array Gate status: allowed (bool), status (BLOCKED/RELEASED/EXPIRED), reason (string)
	 */
	public function check_service_gate($source_module, $source_ref)
	{
		if (!$this->db->table_exists('billing_queue')) {
			return array('allowed' => false, 'status' => 'BLOCKED', 'reason' => 'Gate unavailable - billing queue not available', 'blocked_reason' => 'SCHEMA');
		}
		if (!$this->db->field_exists('service_gate_status', 'billing_queue')) {
			return array('allowed' => false, 'status' => 'BLOCKED', 'reason' => 'Gate unavailable - billing queue schema incomplete', 'blocked_reason' => 'SCHEMA');
		}

		$this->db->where('source_module', $source_module);
		$this->db->where('source_ref', $source_ref);
		$this->db->where('InActive', 0);
		$item = $this->db->get('billing_queue')->row();

		if (!$item) {
			// Item not in queue - may be emergency or already processed
			return array('allowed' => true, 'status' => 'RELEASED', 'reason' => 'Item not in billing queue');
		}

		// Check service gate status
		$gate_status = isset($item->service_gate_status) ? $item->service_gate_status : 'BLOCKED';
		
		if ($gate_status === 'RELEASED') {
			return array(
				'allowed' => true,
				'status' => 'RELEASED',
				'queue_id' => $item->queue_id,
				'invoice_no' => $item->invoice_no,
				'reason' => 'Service released for processing'
			);
		}

		if ($gate_status === 'EXPIRED') {
			return array(
				'allowed' => false,
				'status' => 'EXPIRED',
				'queue_id' => $item->queue_id,
				'reason' => 'Service release has expired - re-billing required'
			);
		}

		// BLOCKED - Check if invoice exists and is paid
		if ($item->invoice_no) {
			// Item has been invoiced - check payment
			$this->db->where('invoice_no', $item->invoice_no);
			$this->db->where('InActive', 0);
			$invoice = $this->db->get('iop_billing')->row();

			if ($invoice) {
				// Check if invoice is paid or has sufficient coverage
				$balance = $this->get_invoice_balance($item->invoice_no);
				$invPay = isset($invoice->payment_status) ? strtoupper(trim((string)$invoice->payment_status)) : '';
				if ($balance <= 0.01 || $invoice->payer_type !== 'CASH' || $invPay === 'PARTIAL') {
					// Auto-release if paid or covered
					$this->release_service_gate($item->queue_id, 'SYSTEM');
					return array(
						'allowed' => true,
						'status' => 'RELEASED',
						'queue_id' => $item->queue_id,
						'invoice_no' => $item->invoice_no,
						'reason' => ($invPay === 'PARTIAL') ? 'Partial payment confirmed - service auto-released' : 'Payment confirmed - service auto-released'
					);
				}

				return array(
					'allowed' => false,
					'status' => 'BLOCKED',
					'queue_id' => $item->queue_id,
					'invoice_no' => $item->invoice_no,
					'reason' => 'Payment pending - balance due: GHS ' . number_format($balance, 2)
				);
			}
		}

		// Not yet invoiced
		return array(
			'allowed' => false,
			'status' => 'BLOCKED',
			'queue_id' => $item->queue_id,
			'reason' => 'Not yet billed - please send patient to billing/cashier'
		);
	}

	/**
	 * Release service gate for a billing queue item
	 * Called when payment is confirmed
	 * 
	 * @param int $queue_id Billing queue ID
	 * @param string $released_by User releasing the gate
	 * @return bool Success
	 */
	public function release_service_gate($queue_id, $released_by = 'SYSTEM')
	{
		if (!$this->db->table_exists('billing_queue')) return false;
		
		if (!$this->db->field_exists('service_gate_status', 'billing_queue')) {
			// Schema not updated yet
			return true;
		}

		$this->db->where('queue_id', $queue_id);
		$this->db->update('billing_queue', array(
			'service_gate_status' => 'RELEASED',
			'released_at' => date('Y-m-d H:i:s'),
			'released_by' => $released_by
		));

		// Log the gate release
		$this->db->where('queue_id', $queue_id);
		$item = $this->db->get('billing_queue')->row();
		if ($item) {
			$this->log_billing_audit('GATE_RELEASE', array(
				'entity_type' => 'BILLING_QUEUE',
				'entity_id' => $queue_id,
				'queue_id' => $queue_id,
				'i' => $item->iop_id,
				'patient_no' => $item->patient_no,
				'amount' => $item->net_amount,
				'reference_type' => $item->item_type,
				'old_value' => 'BLOCKED',
				'new_value' => 'RELEASED',
				'description' => "Service gate released by $released_by for {$item->item_type}"
			));
		}

		return true;
	}

	/**
	 * Auto-release service gates for an invoice after payment
	 * Called from process_payment after successful payment
	 * 
	 * @param string $invoice_no Invoice number
	 */
	public function auto_release_gates_for_invoice($invoice_no)
	{
		if (!$this->db->table_exists('billing_queue')) return;
		if (!$this->db->field_exists('service_gate_status', 'billing_queue')) return;

		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('status', 'BILLED');
		$this->db->where('service_gate_status', 'BLOCKED');
		$this->db->where('InActive', 0);
		$items = $this->db->get('billing_queue')->result();

		foreach ($items as $item) {
			$this->release_service_gate($item->queue_id, 'SYSTEM_AUTO');
		}

		return count($items);
	}

	/**
	 * Finalize an invoice - make it immutable
	 * Once finalized, invoice cannot be modified (only payments/refunds allowed)
	 * 
	 * @param string $invoice_no Invoice number
	 * @param string $finalized_by User finalizing
	 * @return array Result
	 */
	public function finalize_invoice($invoice_no, $finalized_by = null)
	{
		if (!$this->db->field_exists('is_locked', 'iop_billing')) {
			return array('success' => false, 'error' => 'Schema not upgraded for invoice finalization');
		}

		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$invoice = $this->db->get('iop_billing')->row();

		if (!$invoice) {
			return array('success' => false, 'error' => 'Invoice not found');
		}

		if (isset($invoice->is_locked) && $invoice->is_locked) {
			return array('success' => false, 'error' => 'Invoice already finalized');
		}

		$this->db->where('invoice_no', $invoice_no);
		$this->db->update('iop_billing', array(
			'is_locked' => 1,
			'locked_at' => date('Y-m-d H:i:s'),
			'finalized_at' => date('Y-m-d H:i:s'),
			'invoice_status' => 'FINALIZED'
		));

		// Log the finalization
		$this->log_billing_audit('INVOICE_FINALIZE', array(
			'entity_type' => 'INVOICE',
			'entity_id' => $invoice_no,
			'invoice_no' => $invoice_no,
			'patient_no' => $invoice->patient_no,
			'old_value' => isset($invoice->invoice_status) ? $invoice->invoice_status : 'DRAFT',
			'new_value' => 'FINALIZED',
			'description' => 'Invoice finalized and made immutable'
		));

		return array('success' => true, 'invoice_no' => $invoice_no);
	}

	/**
	 * Check if invoice is locked (immutable)
	 * 
	 * @param string $invoice_no Invoice number
	 * @return bool True if locked
	 */
	public function is_invoice_locked($invoice_no)
	{
		if (!$this->db->field_exists('is_locked', 'iop_billing')) {
			return false; // Legacy behavior
		}

		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$invoice = $this->db->get('iop_billing')->row();

		return $invoice && isset($invoice->is_locked) && $invoice->is_locked;
	}
}
