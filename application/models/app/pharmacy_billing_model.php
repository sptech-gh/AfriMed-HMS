<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . 'models/app/pharmacy_base_model.php');

/**
 * Pharmacy Billing Model
 * 
 * Handles all billing-related operations:
 * - GHS billing queue
 * - Payment gates
 * - Billing status management
 * - Financial audit
 * 
 * Part of Phase 4 Performance Optimization.
 */
class Pharmacy_billing_model extends Pharmacy_base_model
{
	private static $_schema_done = false;
	
	public function __construct()
	{
		parent::__construct();
	}

	private function _pbq_pk_col()
	{
		if ($this->table_exists('pharmacy_billing_queue')) {
			$q = $this->db->query("SHOW KEYS FROM `pharmacy_billing_queue` WHERE Key_name = 'PRIMARY'");
			if ($q && $q->num_rows() > 0) {
				$row = $q->row();
				if ($row && isset($row->Column_name) && trim((string)$row->Column_name) !== '') {
					return (string)$row->Column_name;
				}
			}
			if ($this->column_exists('pharmacy_billing_queue', 'id')) return 'id';
			if ($this->column_exists('pharmacy_billing_queue', 'bill_id')) return 'bill_id';
		}
		return 'id';
	}
	
	private function _pbq_total_col()
	{
		if ($this->table_exists('pharmacy_billing_queue') && $this->column_exists('pharmacy_billing_queue', 'total_amount')) {
			return 'total_amount';
		}
		return 'total';
	}
	
	// =========================================================================
	// SCHEMA MANAGEMENT
	// =========================================================================
	
	public function ensure_billing_schema()
	{
		if (self::$_schema_done) return;
		self::$_schema_done = true;
		
		// Create pharmacy_billing_queue table
		if (!$this->table_exists('pharmacy_billing_queue')) {
			$this->db->query("
				CREATE TABLE `pharmacy_billing_queue` (
					`id` int(11) NOT NULL AUTO_INCREMENT,
					`bill_id` int(11) DEFAULT NULL,
					`iop_med_id` int(11) NOT NULL,
					`prescription_no` varchar(20) DEFAULT NULL,
					`iop_id` varchar(25) NOT NULL,
					`patient_no` varchar(25) DEFAULT NULL,
					`drug_id` int(11) DEFAULT NULL,
					`drug_name` varchar(255) DEFAULT NULL,
					`quantity` decimal(11,2) NOT NULL DEFAULT 0,
					`quantity_semantics_version` tinyint(1) DEFAULT NULL,
					`unit_price` decimal(18,2) NOT NULL DEFAULT 0,
					`total` decimal(18,2) NOT NULL DEFAULT 0,
					`total_amount` decimal(18,2) NOT NULL DEFAULT 0,
					`payment_status` varchar(20) DEFAULT 'PENDING',
					`dispense_status` varchar(20) DEFAULT 'PENDING',
					`extended_status` varchar(30) NOT NULL DEFAULT 'PENDING',
					`payer_type` varchar(20) DEFAULT 'CASH',
					`nhis_covered` tinyint(1) DEFAULT 0,
					`is_nhis_covered` tinyint(1) NOT NULL DEFAULT 0,
					`emergency_flag` tinyint(1) DEFAULT 0,
					`waiver_requested` tinyint(1) DEFAULT 0,
					`waiver_approved` tinyint(1) DEFAULT 0,
					`waiver_reason` text,
					`notes` text,
					`flex_notes` text,
					`cancelled_by` varchar(25) DEFAULT NULL,
					`cancelled_reason` text DEFAULT NULL,
					`external_flag` tinyint(1) DEFAULT 0,
					`unable_to_pay_flag` tinyint(1) DEFAULT 0,
					`deferred_flag` tinyint(1) DEFAULT 0,
					`waiver_flag` tinyint(1) DEFAULT 0,
					`outstanding_balance` decimal(15,2) NOT NULL DEFAULT 0,
					`deferred_until` datetime DEFAULT NULL,
					`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
					`billed_by` varchar(25) DEFAULT NULL,
					`created_by` varchar(25) DEFAULT NULL,
					`updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
					`paid_at` datetime DEFAULT NULL,
					`paid_by` varchar(25) DEFAULT NULL,
					`waiver_approved_by` varchar(25) DEFAULT NULL,
					`waiver_approved_at` datetime DEFAULT NULL,
					`InActive` tinyint(1) DEFAULT 0,
					PRIMARY KEY (`id`),
					KEY `idx_iop_med_id` (`iop_med_id`),
					KEY `idx_bill_id` (`bill_id`),
					KEY `idx_iop_id` (`iop_id`),
					KEY `idx_patient_no` (`patient_no`),
					UNIQUE KEY `uq_iop_med` (`iop_med_id`),
					KEY `idx_payment_status` (`payment_status`),
					KEY `idx_dispense_status` (`dispense_status`),
					KEY `idx_created_at` (`created_at`),
					KEY `idx_active_payment` (`InActive`, `payment_status`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			// Invalidate cached schema lookups (table_exists() is cached for 1 hour)
			$this->cache_invalidate('table_exists_pharmacy_billing_queue');
			$this->cache_invalidate('column_exists_pharmacy_billing_queue');
			$this->cache_invalidate('index_exists_pharmacy_billing_queue');
		}

		if ($this->table_exists('pharmacy_billing_queue')) {
			$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($old !== null) { $this->db->db_debug = false; }

			$this->add_column_if_not_exists('pharmacy_billing_queue', 'drug_id', "int(11) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'drug_name', "varchar(255) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'quantity', "decimal(11,2) NOT NULL DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'quantity_semantics_version', "tinyint(1) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'unit_price', "decimal(18,2) NOT NULL DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'bill_id', "int(11) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'total', "decimal(18,2) NOT NULL DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'prescription_no', "varchar(20) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'payment_status', "varchar(20) NOT NULL DEFAULT 'PENDING'");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'dispense_status', "varchar(20) NOT NULL DEFAULT 'PENDING'");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'extended_status', "varchar(30) NOT NULL DEFAULT 'PENDING'");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'payer_type', "varchar(20) DEFAULT 'CASH'");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'nhis_covered', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'is_nhis_covered', "tinyint(1) NOT NULL DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'emergency_flag', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'waiver_requested', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'waiver_approved', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'waiver_reason', "text");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'notes', "text");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'flex_notes', "text");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'cancelled_by', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'cancelled_reason', "text DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'external_flag', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'unable_to_pay_flag', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'deferred_flag', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'waiver_flag', "tinyint(1) DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'outstanding_balance', "decimal(15,2) NOT NULL DEFAULT 0");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'deferred_until', "datetime DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'created_at', "datetime DEFAULT CURRENT_TIMESTAMP");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'updated_at', "datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'paid_at', "datetime DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'paid_by', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'billed_by', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'waiver_approved_by', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'waiver_approved_at', "datetime DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'created_by', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('pharmacy_billing_queue', 'InActive', "tinyint(1) DEFAULT 0");

			$col = $this->db->query("SHOW COLUMNS FROM `pharmacy_billing_queue` LIKE 'deferred_until'")->row();
			if ($col && isset($col->Type) && strtolower((string)$col->Type) === 'date') {
				$this->db->query("ALTER TABLE `pharmacy_billing_queue` MODIFY COLUMN `deferred_until` datetime DEFAULT NULL");
				$this->cache_invalidate('column_exists_pharmacy_billing_queue');
			}

			$col = $this->db->query("SHOW COLUMNS FROM `pharmacy_billing_queue` LIKE 'extended_status'")->row();
			if ($col && isset($col->Null) && strtoupper((string)$col->Null) === 'YES') {
				$this->db->query("UPDATE `pharmacy_billing_queue` SET `extended_status` = 'PENDING' WHERE `extended_status` IS NULL");
				$this->db->query("ALTER TABLE `pharmacy_billing_queue` MODIFY COLUMN `extended_status` varchar(30) NOT NULL DEFAULT 'PENDING'");
				$this->cache_invalidate('column_exists_pharmacy_billing_queue');
			}

			$has_total_amount = $this->column_exists('pharmacy_billing_queue', 'total_amount');
			$has_total = $this->column_exists('pharmacy_billing_queue', 'total');
			if (!$has_total_amount && $has_total) {
				$this->db->query("ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `total_amount` decimal(18,2) NOT NULL DEFAULT 0");
				$this->db->query("UPDATE `pharmacy_billing_queue` SET `total_amount` = `total` WHERE `total_amount` = 0 AND `total` > 0");
				$this->cache_invalidate('column_exists_pharmacy_billing_queue');
			}

			$has_total_amount = $this->column_exists('pharmacy_billing_queue', 'total_amount');
			$has_total = $this->column_exists('pharmacy_billing_queue', 'total');
			if ($has_total_amount && $has_total) {
				$this->db->query("UPDATE `pharmacy_billing_queue` SET `total` = `total_amount` WHERE `total` = 0 AND `total_amount` > 0");
			}

			$has_bill_id = $this->column_exists('pharmacy_billing_queue', 'bill_id');
			$has_id = $this->column_exists('pharmacy_billing_queue', 'id');
			if ($has_bill_id && $has_id) {
				$this->db->query("UPDATE `pharmacy_billing_queue` SET `bill_id` = `id` WHERE (`bill_id` IS NULL OR `bill_id` = 0) AND `id` > 0");
			}

			// Best-effort de-duplication on iop_med_id (keep most recent row active)
			$has_iop_med = $this->column_exists('pharmacy_billing_queue', 'iop_med_id');
			if ($has_iop_med) {
				$dups = $this->db->query("SELECT `iop_med_id`, COUNT(*) AS `c` FROM `pharmacy_billing_queue` GROUP BY `iop_med_id` HAVING `c` > 1");
				if ($dups && $dups->num_rows() > 0) {
					$order_col = $has_id ? 'id' : ($has_bill_id ? 'bill_id' : 'created_at');
					$now = date('Y-m-d H:i:s');
					foreach ($dups->result() as $d) {
						$mid = (int)(isset($d->iop_med_id) ? $d->iop_med_id : 0);
						if ($mid <= 0) continue;

						$keep = $this->db->query(
							"SELECT `{$order_col}` AS `k` FROM `pharmacy_billing_queue` WHERE `iop_med_id` = ? ORDER BY `{$order_col}` DESC LIMIT 1",
							array($mid)
						)->row();
						$keep_k = $keep && isset($keep->k) ? $keep->k : null;
						if ($keep_k === null) continue;

						$this->db->query(
							"UPDATE `pharmacy_billing_queue` SET `InActive` = 1, `updated_at` = ? WHERE `iop_med_id` = ? AND `{$order_col}` <> ?",
							array($now, $mid, $keep_k)
						);
					}
				}

				$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_iop_med_active', array('iop_med_id', 'InActive'));
			}

			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_iop_id', 'iop_id');
			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_patient_no', 'patient_no');
			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_iop_med_id', 'iop_med_id');
			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_bill_id', 'bill_id');
			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_payment_status', 'payment_status');
			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_dispense_status', 'dispense_status');
			$this->add_index_if_not_exists('pharmacy_billing_queue', 'idx_created_at', 'created_at');

			if ($old !== null) { $this->db->db_debug = $old; }
		}
		
		// Create pharmacy_audit_log table
		if (!$this->table_exists('pharmacy_audit_log')) {
			$this->db->query("
				CREATE TABLE `pharmacy_audit_log` (
					`audit_id` int(11) NOT NULL AUTO_INCREMENT,
					`audit_table` varchar(50) NOT NULL,
					`record_id` int(11) NOT NULL,
					`event_type` varchar(50) NOT NULL,
					`old_value` text,
					`new_value` text,
					`performed_by` varchar(25) DEFAULT NULL,
					`performed_at` datetime DEFAULT CURRENT_TIMESTAMP,
					`notes` text,
					`ip_address` varchar(45) DEFAULT NULL,
					PRIMARY KEY (`audit_id`),
					KEY `idx_table_record` (`audit_table`, `record_id`),
					KEY `idx_performed_at` (`performed_at`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			$this->cache_invalidate('table_exists_pharmacy_audit_log');
			$this->cache_invalidate('column_exists_pharmacy_audit_log');
			$this->cache_invalidate('index_exists_pharmacy_audit_log');
		}
		
		// Create outstanding_balances table
		if (!$this->table_exists('pharmacy_outstanding_balances')) {
			$this->db->query("
				CREATE TABLE `pharmacy_outstanding_balances` (
					`outstanding_id` int(11) NOT NULL AUTO_INCREMENT,
					`patient_no` varchar(25) NOT NULL,
					`iop_id` varchar(25) NOT NULL,
					`bill_id` int(11) DEFAULT NULL,
					`amount` decimal(15,2) NOT NULL DEFAULT 0,
					`reason` varchar(50) DEFAULT NULL,
					`notes` text,
					`status` varchar(20) DEFAULT 'PENDING',
					`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
					`created_by` varchar(25) DEFAULT NULL,
					`settled_at` datetime DEFAULT NULL,
					`settled_by` varchar(25) DEFAULT NULL,
					`InActive` tinyint(1) DEFAULT 0,
					PRIMARY KEY (`outstanding_id`),
					KEY `idx_patient_no` (`patient_no`),
					KEY `idx_status` (`status`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			$this->cache_invalidate('table_exists_pharmacy_outstanding_balances');
			$this->cache_invalidate('column_exists_pharmacy_outstanding_balances');
			$this->cache_invalidate('index_exists_pharmacy_outstanding_balances');
		}
	}
	
	// =========================================================================
	// BILLING QUEUE MANAGEMENT
	// =========================================================================
	
	/**
	 * Create or update pharmacy bill entry
	 */
	public function create_or_update_pharmacy_bill($iop_med_id, $user_id = null)
	{
		$this->ensure_billing_schema();
		$this->load->helper('quantity_semantics');
		$pk_col = $this->_pbq_pk_col();
		$total_col = $this->_pbq_total_col();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0) return null;
		
		// Get medication details
		$this->db->select('m.*, d.drug_name, d.nPrice, d.is_nhis_covered, d.nhis_price, i.patient_no, p.Insurance_comp');
		$this->db->from('iop_medication m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
		$this->db->join('patient_details_iop i', 'i.IO_ID = m.iop_id', 'left');
		$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
		$this->db->where('m.iop_med_id', $iop_med_id);
		$q = $this->db->get();
		$med = $q ? $q->row() : null;
		
		if (!$med) return null;
		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return null;
			}
		}
		
		// Determine payer type
		$payer_type = 'CASH';
		$ins = strtoupper(trim($med->Insurance_comp ?? ''));
		if ($ins === 'NHIS' || $this->is_nhis_patient($med->patient_no)) {
			$payer_type = 'NHIS';
		}
		
		// Calculate price
		$unit_price = (float)$med->nPrice;
		if ($payer_type === 'NHIS' && isset($med->nhis_price) && (float)$med->nhis_price > 0) {
			$unit_price = (float)$med->nhis_price;
		}
		$qty = qs_pick_prescribed_qty($med, 1.0);
		$total = $unit_price * (float)$qty;
		
		// Check if exists
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$existing = $this->db->get('pharmacy_billing_queue')->row();
		
		$data = array(
			'iop_med_id' => $iop_med_id,
			'iop_id' => $med->iop_id,
			'patient_no' => $med->patient_no,
			'drug_id' => $med->medicine_id,
			'drug_name' => $med->drug_name,
			'quantity' => $qty,
			'unit_price' => $unit_price,
			$total_col => $total,
			'payer_type' => $payer_type,
			'nhis_covered' => ($med->is_nhis_covered ?? 0) ? 1 : 0,
			'updated_at' => date('Y-m-d H:i:s')
		);
		if ($this->column_exists('pharmacy_billing_queue', 'quantity_semantics_version')) {
			$sem = qs_flag_enabled('ENABLE_DECIMAL_PRESCRIBED_QTY', false) ? qs_decimal_semantics_version() : qs_default_semantics_version();
			$data['quantity_semantics_version'] = (int)$sem;
		}
		if ($total_col !== 'total_amount' && $this->column_exists('pharmacy_billing_queue', 'total_amount')) {
			$data['total_amount'] = $total;
		}
		if ($total_col !== 'total' && $this->column_exists('pharmacy_billing_queue', 'total')) {
			$data['total'] = $total;
		}
		
		if ($existing) {
			$this->db->where($pk_col, (int)$existing->{$pk_col});
			$this->db->update('pharmacy_billing_queue', $data);
			return (int)$existing->{$pk_col};
		} else {
			$data['payment_status'] = ($payer_type === 'NHIS') ? 'PAID' : 'PENDING';
			$data['created_at'] = date('Y-m-d H:i:s');
			$data['created_by'] = $user_id;
			$this->db->insert('pharmacy_billing_queue', $data);
			$insert_id = (int)$this->db->insert_id();
			if ($insert_id > 0) {
				$sync = array();
				if ($this->column_exists('pharmacy_billing_queue', 'bill_id') && $pk_col === 'id') {
					$sync['bill_id'] = $insert_id;
				}
				if (!empty($sync)) {
					$this->db->where($pk_col, $insert_id);
					$this->db->update('pharmacy_billing_queue', $sync);
				}
			}
			return $insert_id;
		}
	}
	
	/**
	 * Ensure billing queue entries exist for a visit
	 */
	public function ensure_billing_queue_for_visit($iop_id, $user_id = null)
	{
		$this->ensure_billing_schema();
		
		// Get all medications for visit
		$this->db->select('iop_med_id');
		$this->db->from('iop_medication');
		$this->db->where('iop_id', $iop_id);
		$this->db->where('InActive', 0);
		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$this->db->where('prescription_status', 'VERIFIED');
		}
		$q = $this->db->get();
		$meds = $q ? $q->result() : array();
		
		foreach ($meds as $med) {
			$this->create_or_update_pharmacy_bill($med->iop_med_id, $user_id);
		}
	}
	
	// =========================================================================
	// PAYMENT GATES
	// =========================================================================
	
	/**
	 * Check GHS payment gate
	 */
	public function check_payment_gate($iop_med_id)
	{
		$this->ensure_billing_schema();
		$total_col = $this->_pbq_total_col();
		
		// Get billing queue entry
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$q = $this->db->get('pharmacy_billing_queue');
		$bill = $q ? $q->row() : null;
		
		if (!$bill) {
			// No billing entry - check if NHIS patient
			$this->db->select('i.patient_no, p.Insurance_comp');
			$this->db->from('iop_medication m');
			$this->db->join('patient_details_iop i', 'i.IO_ID = m.iop_id', 'left');
			$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
			$this->db->where('m.iop_med_id', $iop_med_id);
			$q = $this->db->get();
			$med = $q ? $q->row() : null;
			
			if ($med && $this->is_nhis_patient($med->patient_no)) {
				return array('allowed' => true, 'reason' => 'NHIS patient');
			}
			
			return array('allowed' => false, 'reason' => 'No billing entry found');
		}
		
		// Check payment status
		$status = strtoupper(trim($bill->payment_status));
		$extended = strtoupper(trim($bill->extended_status ?? ''));
		$payer = strtoupper(trim($bill->payer_type));
		
		// NHIS auto-allowed
		if ($payer === 'NHIS') {
			return array('allowed' => true, 'reason' => 'NHIS covered');
		}
		
		// Paid
		if ($status === 'PAID') {
			return array('allowed' => true, 'reason' => 'Payment received');
		}
		
		// Exception statuses
		$exceptions = array('EXTERNAL_PURCHASE', 'EXTERNAL', 'UNABLE_TO_PAY', 'DEFERRED', 'WAIVED', 'EMERGENCY', 'WAIVER_APPROVED');
		if (in_array($extended, $exceptions) || in_array($status, $exceptions)) {
			return array('allowed' => true, 'reason' => 'Exception: ' . ($extended ?: $status));
		}
		
		// Emergency flag
		if (!empty($bill->emergency_flag)) {
			return array('allowed' => true, 'reason' => 'Emergency override');
		}
		
		// Waiver approved
		if (!empty($bill->waiver_approved)) {
			return array('allowed' => true, 'reason' => 'Waiver approved');
		}
		
		$amt = 0;
		if ($bill && isset($bill->{$total_col})) {
			$amt = (float)$bill->{$total_col};
		} elseif ($bill && isset($bill->total_amount)) {
			$amt = (float)$bill->total_amount;
		} elseif ($bill && isset($bill->total)) {
			$amt = (float)$bill->total;
		}
		return array('allowed' => false, 'reason' => 'Payment required', 'amount' => $amt);
	}
	
	// =========================================================================
	// BILLING STATUS UPDATES
	// =========================================================================
	
	/**
	 * Mark pharmacy bill as paid
	 */
	public function mark_bill_paid($bill_id, $user_id)
	{
		$this->ensure_billing_schema();
		$pk_col = $this->_pbq_pk_col();
		
		$this->db->set(array(
			'payment_status' => 'PAID',
			'dispense_status' => 'READY',
			'paid_at' => date('Y-m-d H:i:s'),
			'paid_by' => $user_id
		));
		$this->db->where($pk_col, $bill_id);
		$this->db->update('pharmacy_billing_queue');
		
		$this->log_audit('pharmacy_billing_queue', $bill_id, 'MARK_PAID', 'PENDING', 'PAID', $user_id);
		
		// Invalidate cache
		$this->cache_invalidate('count_awaiting');
		$this->cache_invalidate('count_ready');
		
		return true;
	}
	
	/**
	 * Cancel pharmacy bill
	 */
	public function cancel_bill($bill_id, $user_id, $reason = '')
	{
		$this->ensure_billing_schema();
		$pk_col = $this->_pbq_pk_col();
		
		$this->db->set(array(
			'payment_status' => 'CANCELLED',
			'notes' => $reason,
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where($pk_col, $bill_id);
		$this->db->update('pharmacy_billing_queue');
		
		$this->log_audit('pharmacy_billing_queue', $bill_id, 'CANCEL', null, 'CANCELLED', $user_id, $reason);
		
		return true;
	}
	
	/**
	 * Sync dispensing status to billing queue.
	 * Returns true if PBQ row was updated, false if no row found or table missing.
	 */
	public function sync_dispense_status($iop_med_id, $new_status, $user_id = null)
	{
		$this->ensure_billing_schema();
		$iop_med_id = (int)$iop_med_id;
		$new_status = strtoupper(trim((string)$new_status));
		if ($iop_med_id <= 0 || $new_status === '') return false;

		if (!$this->table_exists('pharmacy_billing_queue')) return false;

		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$existing = $this->db->get('pharmacy_billing_queue')->row();
		if (!$existing) return false;

		$old_status = isset($existing->dispense_status) ? (string)$existing->dispense_status : '';
		$this->db->set(array(
			'dispense_status' => $new_status,
			'updated_at'      => date('Y-m-d H:i:s'),
		));
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$ok = (bool)$this->db->update('pharmacy_billing_queue');

		if ($ok && $user_id !== null) {
			$this->log_audit('pharmacy_billing_queue', $iop_med_id, 'DISPENSE_STATUS_SYNC', $old_status, $new_status, $user_id);
		}
		return $ok;
	}
	
	// =========================================================================
	// FLEXIBLE WORKFLOW (GHS)
	// =========================================================================
	
	/**
	 * Mark as external purchase
	 */
	public function mark_external_purchase($iop_med_id, $user_id, $reason = '')
	{
		$this->ensure_billing_schema();
		
		$this->db->set(array(
			'extended_status' => 'EXTERNAL_PURCHASE',
			'payment_status' => 'CANCELLED',
			'notes' => $reason,
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$this->db->update('pharmacy_billing_queue');
		
		// Update medication status
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->set('dispensing_status', 'EXTERNAL');
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('iop_medication');
		}
		
		$this->log_audit('pharmacy_billing_queue', $iop_med_id, 'EXTERNAL_PURCHASE', null, 'EXTERNAL_PURCHASE', $user_id, $reason);
		
		return true;
	}
	
	/**
	 * Mark as unable to pay
	 */
	public function mark_unable_to_pay($iop_med_id, $user_id, $reason = '')
	{
		$this->ensure_billing_schema();
		$pk_col = $this->_pbq_pk_col();
		$total_col = $this->_pbq_total_col();
		
		// Get bill info
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$bill = $this->db->get('pharmacy_billing_queue')->row();
		
		if (!$bill) return false;
		
		$this->db->set(array(
			'extended_status' => 'UNABLE_TO_PAY',
			'notes' => $reason,
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where($pk_col, (int)$bill->{$pk_col});
		$this->db->update('pharmacy_billing_queue');
		
		$amt = 0;
		if (isset($bill->{$total_col})) {
			$amt = (float)$bill->{$total_col};
		} elseif (isset($bill->total_amount)) {
			$amt = (float)$bill->total_amount;
		} elseif (isset($bill->total)) {
			$amt = (float)$bill->total;
		}
		
		// Create outstanding balance
		$this->db->insert('pharmacy_outstanding_balances', array(
			'patient_no' => $bill->patient_no,
			'iop_id' => $bill->iop_id,
			'bill_id' => (int)$bill->{$pk_col},
			'amount' => $amt,
			'reason' => 'UNABLE_TO_PAY',
			'notes' => $reason,
			'created_by' => $user_id
		));
		
		$this->log_audit('pharmacy_billing_queue', (int)$bill->{$pk_col}, 'UNABLE_TO_PAY', null, 'UNABLE_TO_PAY', $user_id, $reason);
		
		return true;
	}
	
	/**
	 * Mark as deferred payment
	 */
	public function mark_deferred($iop_med_id, $user_id, $reason = '', $defer_until = null)
	{
		$this->ensure_billing_schema();
		$pk_col = $this->_pbq_pk_col();
		$total_col = $this->_pbq_total_col();
		
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$bill = $this->db->get('pharmacy_billing_queue')->row();
		
		if (!$bill) return false;
		
		$this->db->set(array(
			'extended_status' => 'DEFERRED',
			'notes' => $reason . ($defer_until ? " (Until: {$defer_until})" : ''),
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where($pk_col, (int)$bill->{$pk_col});
		$this->db->update('pharmacy_billing_queue');
		
		$amt = 0;
		if (isset($bill->{$total_col})) {
			$amt = (float)$bill->{$total_col};
		} elseif (isset($bill->total_amount)) {
			$amt = (float)$bill->total_amount;
		} elseif (isset($bill->total)) {
			$amt = (float)$bill->total;
		}
		
		// Create outstanding balance
		$this->db->insert('pharmacy_outstanding_balances', array(
			'patient_no' => $bill->patient_no,
			'iop_id' => $bill->iop_id,
			'bill_id' => (int)$bill->{$pk_col},
			'amount' => $amt,
			'reason' => 'DEFERRED',
			'notes' => $reason,
			'created_by' => $user_id
		));
		
		$this->log_audit('pharmacy_billing_queue', (int)$bill->{$pk_col}, 'DEFERRED', null, 'DEFERRED', $user_id, $reason);
		
		return true;
	}
	
	/**
	 * Mark as emergency override
	 */
	public function mark_emergency_override($iop_med_id, $user_id, $reason = '')
	{
		$this->ensure_billing_schema();
		
		$this->db->set(array(
			'extended_status' => 'EMERGENCY',
			'emergency_flag' => 1,
			'notes' => $reason,
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$this->db->update('pharmacy_billing_queue');
		
		$this->log_audit('pharmacy_billing_queue', $iop_med_id, 'EMERGENCY_OVERRIDE', null, 'EMERGENCY', $user_id, $reason);
		
		return true;
	}
	
	/**
	 * Request waiver
	 */
	public function request_waiver($iop_med_id, $user_id, $reason = '')
	{
		$this->ensure_billing_schema();
		
		$this->db->set(array(
			'extended_status' => 'WAIVER_REQUESTED',
			'waiver_requested' => 1,
			'waiver_reason' => $reason,
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$this->db->update('pharmacy_billing_queue');
		
		$this->log_audit('pharmacy_billing_queue', $iop_med_id, 'WAIVER_REQUESTED', null, 'WAIVER_REQUESTED', $user_id, $reason);
		
		return true;
	}
	
	/**
	 * Approve waiver
	 */
	public function approve_waiver($bill_id, $admin_id, $notes = '')
	{
		$this->ensure_billing_schema();
		$pk_col = $this->_pbq_pk_col();
		
		$this->db->set(array(
			'extended_status' => 'WAIVED',
			'waiver_approved' => 1,
			'payment_status' => 'WAIVED',
			'notes' => $notes,
			'updated_at' => date('Y-m-d H:i:s')
		));
		$this->db->where($pk_col, $bill_id);
		$this->db->update('pharmacy_billing_queue');
		
		$this->log_audit('pharmacy_billing_queue', $bill_id, 'WAIVER_APPROVED', 'WAIVER_REQUESTED', 'WAIVED', $admin_id, $notes);
		
		return true;
	}
	
	// =========================================================================
	// BILLING QUERIES
	// =========================================================================
	
	/**
	 * Get pending pharmacy bills
	 */
	public function get_pending_bills($filters = array())
	{
		$this->ensure_billing_schema();
		
		$this->db->select('b.*, p.firstname, p.lastname');
		$this->db->from('pharmacy_billing_queue b');
		$this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
		$this->db->where('b.InActive', 0);
		$this->db->where('b.payment_status', 'PENDING');
		
		if (isset($filters['date_from']) && $filters['date_from']) {
			$this->db->where('DATE(b.created_at) >=', $filters['date_from']);
		}
		if (isset($filters['date_to']) && $filters['date_to']) {
			$this->db->where('DATE(b.created_at) <=', $filters['date_to']);
		}
		
		$this->db->order_by('b.created_at', 'DESC');
		$this->db->limit(isset($filters['limit']) ? (int)$filters['limit'] : 200);
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
	
	/**
	 * Get payment status map for IOPs (batch fetch)
	 */
	public function get_payment_status_map($iop_ids)
	{
		if (empty($iop_ids)) return array();
		
		$this->ensure_billing_schema();
		$total_col = $this->_pbq_total_col();
		
		$this->db->select('iop_id, payment_status, extended_status, payer_type, SUM(' . $total_col . ') as total_amount', false);
		$this->db->from('pharmacy_billing_queue');
		$this->db->where_in('iop_id', $iop_ids);
		$this->db->where('InActive', 0);
		$this->db->group_by('iop_id');
		
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		
		$map = array();
		foreach ($rows as $r) {
			$map[$r->iop_id] = array(
				'payment_status' => $r->payment_status,
				'extended_status' => $r->extended_status,
				'payer_type' => $r->payer_type,
				'total_amount' => (float)$r->total_amount
			);
		}
		
		return $map;
	}
	
	// =========================================================================
	// SUMMARY COUNTS
	// =========================================================================
	
	/**
	 * Count awaiting payment
	 */
	public function count_awaiting_payment()
	{
		$this->ensure_billing_schema();
		
		return $this->cache_get('count_awaiting_payment', function() {
			$this->db->where('payment_status', 'PENDING');
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('pharmacy_billing_queue');
		}, 30);
	}
	
	/**
	 * Count ready to dispense
	 */
	public function count_ready_to_dispense()
	{
		$this->ensure_billing_schema();
		
		return $this->cache_get('count_ready_dispense', function() {
			$this->db->where('payment_status', 'PAID');
			$this->db->where('dispense_status', 'READY');
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('pharmacy_billing_queue');
		}, 30);
	}
	
	/**
	 * Count external purchases
	 */
	public function count_external_purchases()
	{
		$this->ensure_billing_schema();
		
		return $this->cache_get('count_external', function() {
			$this->db->where('extended_status', 'EXTERNAL_PURCHASE');
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('pharmacy_billing_queue');
		}, 60);
	}
	
	/**
	 * Count deferred
	 */
	public function count_deferred()
	{
		$this->ensure_billing_schema();
		
		return $this->cache_get('count_deferred', function() {
			$this->db->where_in('extended_status', array('DEFERRED', 'UNABLE_TO_PAY'));
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('pharmacy_billing_queue');
		}, 60);
	}
	
	/**
	 * Count pending waivers
	 */
	public function count_pending_waivers()
	{
		$this->ensure_billing_schema();
		
		return $this->cache_get('count_pending_waivers', function() {
			$this->db->where('waiver_requested', 1);
			$this->db->where('waiver_approved', 0);
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('pharmacy_billing_queue');
		}, 60);
	}
}
