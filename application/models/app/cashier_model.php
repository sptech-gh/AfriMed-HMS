<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Cashier_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->align_cashier_collation();
	}

	/**
	 * Align cashier tables to match legacy collation (utf8mb4_unicode_ci)
	 */
	public function align_cashier_collation()
	{
		if ($this->table_exists('cashier_payment_log')) {
			$col_info = $this->db->query("
				SELECT COLLATION_NAME 
				FROM information_schema.COLUMNS 
				WHERE TABLE_SCHEMA = DATABASE() 
				  AND TABLE_NAME = 'cashier_payment_log' 
				  AND COLUMN_NAME = 'invoice_no'
			")->row();
			if ($col_info && isset($col_info->COLLATION_NAME) && $col_info->COLLATION_NAME !== 'utf8mb4_unicode_ci') {
				$this->db->query("ALTER TABLE cashier_payment_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				if ($this->table_exists('payment_methods')) {
					$this->db->query("ALTER TABLE payment_methods CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				}
				if ($this->table_exists('financial_audit_log')) {
					$this->db->query("ALTER TABLE financial_audit_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				}
				if ($this->table_exists('cashier_refund_log')) {
					$this->db->query("ALTER TABLE cashier_refund_log CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				}
				if ($this->table_exists('payment_dispatch_notifications')) {
					$this->db->query("ALTER TABLE payment_dispatch_notifications CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				}
			}
		}
	}

	/**
	 * Ensure required schema exists
	 */
	public function ensure_cashier_schema()
	{
		if (schema_already_run('cashier_schema')) {
			$this->_ensure_cashier_performance_indexes();
			return;
		}

		// Ensure payment_log table exists for tracking all payments
		if (!$this->table_exists('cashier_payment_log')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS cashier_payment_log (
					payment_id INT AUTO_INCREMENT PRIMARY KEY,
					receipt_no VARCHAR(50) NOT NULL,
					invoice_no VARCHAR(50) NOT NULL,
					patient_no VARCHAR(25),
					amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					payment_method VARCHAR(50) DEFAULT 'CASH',
					reference_no VARCHAR(100),
					notes TEXT,
					cashier_id VARCHAR(25),
					payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
					voided TINYINT(1) DEFAULT 0,
					void_reason TEXT,
					voided_by VARCHAR(25),
					voided_at DATETIME,
					InActive TINYINT(1) DEFAULT 0,
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					INDEX idx_receipt (receipt_no),
					INDEX idx_invoice (invoice_no),
					INDEX idx_patient (patient_no),
					INDEX idx_date (payment_date),
					INDEX idx_cashier (cashier_id)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		// Ensure payment_methods table exists
		if (!$this->table_exists('payment_methods')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS payment_methods (
					method_id INT AUTO_INCREMENT PRIMARY KEY,
					method_name VARCHAR(50) NOT NULL,
					method_code VARCHAR(20) NOT NULL,
					requires_reference TINYINT(1) DEFAULT 0,
					is_active TINYINT(1) DEFAULT 1,
					sort_order INT DEFAULT 0
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");

			// Insert default payment methods
			$this->db->query("INSERT INTO payment_methods (method_name, method_code, requires_reference, sort_order) VALUES 
				('Cash', 'CASH', 0, 1),
				('Mobile Money', 'MOMO', 1, 2),
				('Bank Transfer', 'BANK', 1, 3),
				('Card Payment', 'CARD', 1, 4),
				('Cheque', 'CHEQUE', 1, 5),
				('NHIS', 'NHIS', 1, 6)
			");
		}
		
		// Ensure financial_audit_log table exists for tracking all financial changes
		if (!$this->table_exists('financial_audit_log')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS financial_audit_log (
					audit_id INT AUTO_INCREMENT PRIMARY KEY,
					action_type ENUM('PAYMENT', 'VOID', 'REFUND', 'ADJUSTMENT', 'CORRECTION') NOT NULL,
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
					INDEX idx_date (performed_at),
					INDEX idx_user (performed_by)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		if (!$this->table_exists('cashier_refund_log')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS cashier_refund_log (
					refund_id INT AUTO_INCREMENT PRIMARY KEY,
					original_receipt_no VARCHAR(50) NOT NULL,
					refund_receipt_no VARCHAR(50) NOT NULL,
					invoice_no VARCHAR(50) NOT NULL,
					patient_no VARCHAR(25),
					amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
					reason TEXT,
					refunded_by VARCHAR(25),
					refunded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					InActive TINYINT(1) DEFAULT 0,
					UNIQUE KEY uq_refund_receipt (refund_receipt_no),
					INDEX idx_orig_receipt (original_receipt_no),
					INDEX idx_invoice (invoice_no),
					INDEX idx_patient (patient_no),
					INDEX idx_date (refunded_at)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		if (!$this->table_exists('payment_dispatch_notifications')) {
			$this->db->query("
				CREATE TABLE IF NOT EXISTS payment_dispatch_notifications (
					notification_id INT AUTO_INCREMENT PRIMARY KEY,
					invoice_no VARCHAR(50) NOT NULL,
					receipt_no VARCHAR(50) NOT NULL,
					patient_no VARCHAR(25) NOT NULL,
					patient_name VARCHAR(255),
					department VARCHAR(50) NOT NULL,
					item_details TEXT,
					status ENUM('PENDING', 'DISPATCHED', 'CANCELLED') DEFAULT 'PENDING',
					created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
					dispatched_at DATETIME,
					dispatched_by VARCHAR(25),
					InActive TINYINT(1) DEFAULT 0,
					INDEX idx_invoice (invoice_no),
					INDEX idx_receipt (receipt_no),
					INDEX idx_patient (patient_no),
					INDEX idx_dept_status (department, status)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
			");
		}

		$this->_ensure_cashier_performance_indexes();

		mark_schema_run('cashier_schema');
	}

	private function _ensure_cashier_performance_indexes()
	{
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) {
			$this->db->db_debug = false;
		}
		try {
			if ($this->table_exists('iop_billing') && $this->column_exists('iop_billing', 'invoice_no')) {
				$has = false;
				$q = $this->db->query("SHOW INDEX FROM `iop_billing` WHERE Column_name = 'invoice_no'");
				if ($q && $q->num_rows() > 0) {
					$has = true;
				}
				if (!$has) {
					try {
						$this->db->query("CREATE INDEX idx_invoice_no ON iop_billing (invoice_no)");
					} catch (\Throwable $e) {
					}
				}
			}
		} catch (\Throwable $e) {
		}
		if ($prev !== null) {
			$this->db->db_debug = $prev;
		}
	}

	private function table_exists($table)
	{
		return $this->db->table_exists($table);
	}

	private function column_exists($table, $column)
	{
		return $this->db->field_exists($column, $table);
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

	/**
	 * Get invoices with optional search and status filter
	 */
	public function get_invoices($limit = 20, $offset = 0, $search = '', $status = 'unpaid')
	{
		// Check if columns exist
		$hasPhone = $this->column_exists('patient_personal_info', 'phone');
		
		$phoneSelect = $hasPhone ? ", p.phone" : "";
		
		// Calculate balance as total - discount - sum of receipts
		$discExpr = $this->_discount_expr('iop_billing', 'h');
		$receiptAgg = "(SELECT invoice_no, SUM(amountPaid) AS amount_paid FROM iop_receipt WHERE InActive = 0 GROUP BY invoice_no) rsum";
		$amountPaidExpr = "COALESCE(rsum.amount_paid, 0)";
		$balanceExpr = "(h.total_amount - {$discExpr} - {$amountPaidExpr})";
		
		$this->db->select("h.invoice_no, h.patient_no, h.iop_id, h.total_amount, {$discExpr} as discount, h.dDate,
			" . $amountPaidExpr . " as amount_paid,
			" . $balanceExpr . " as balance,
			CONCAT(p.lastname, ' ', p.firstname, ' ', COALESCE(p.middlename, '')) as patient_name" . $phoneSelect, false);
		$this->db->from('iop_billing h');
		$this->db->join('patient_personal_info p', 'p.patient_no = h.patient_no', 'left');
		$this->db->join($receiptAgg, 'rsum.invoice_no = h.invoice_no', 'left', false);
		$this->db->where('h.InActive', 0);

		if ($status === 'unpaid') {
			$this->db->where($balanceExpr . ' >', 0, false);
		} elseif ($status === 'paid') {
			$this->db->where($balanceExpr . ' <=', 0, false);
		}

		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('h.invoice_no', $search);
			$this->db->or_like('h.patient_no', $search);
			$this->db->or_like('p.lastname', $search);
			$this->db->or_like('p.firstname', $search);
			if ($hasPhone) {
				$this->db->or_like('p.phone', $search);
			}
			$this->db->group_end();
		}

		$this->db->order_by('h.dDate', 'DESC');
		$this->db->limit($limit, $offset);

		return $this->db->get()->result();
	}

	/**
	 * Count invoices
	 */
	public function count_invoices($search = '', $status = 'unpaid')
	{
		// Calculate balance as total - discount - sum of receipts
		$discExpr = $this->_discount_expr('iop_billing', 'h');
		$receiptAgg = "(SELECT invoice_no, SUM(amountPaid) AS amount_paid FROM iop_receipt WHERE InActive = 0 GROUP BY invoice_no) rsum";
		$amountPaidExpr = "COALESCE(rsum.amount_paid, 0)";
		$balanceExpr = "(h.total_amount - {$discExpr} - {$amountPaidExpr})";
		
		$this->db->from('iop_billing h');
		$this->db->join('patient_personal_info p', 'p.patient_no = h.patient_no', 'left');
		$this->db->join($receiptAgg, 'rsum.invoice_no = h.invoice_no', 'left', false);
		$this->db->where('h.InActive', 0);

		if ($status === 'unpaid') {
			$this->db->where($balanceExpr . ' >', 0, false);
		} elseif ($status === 'paid') {
			$this->db->where($balanceExpr . ' <=', 0, false);
		}

		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('h.invoice_no', $search);
			$this->db->or_like('h.patient_no', $search);
			$this->db->or_like('p.lastname', $search);
			$this->db->or_like('p.firstname', $search);
			$this->db->group_end();
		}

		return $this->db->count_all_results();
	}

	/**
	 * Get invoice details
	 */
	public function get_invoice_details($invoice_no)
	{
		$hasPhone   = $this->column_exists('patient_personal_info', 'phone');
		$hasAddress = $this->column_exists('patient_personal_info', 'address');
		$hasBirthday = $this->column_exists('patient_personal_info', 'birthday');
		
		$extraFields = "";
		if ($hasPhone)   { $extraFields .= ", p.phone"; }
		if ($hasAddress) { $extraFields .= ", p.address"; }
		if ($hasBirthday){ $extraFields .= ", p.birthday"; }
		
		// Calculate paid/balance using receipts as source of truth.
		// Use a correlated SUM() subquery filtered by invoice_no to avoid
		// scanning and grouping the entire iop_receipt table for a single
		// invoice view.
		$discExpr       = $this->_discount_expr('iop_billing', 'h');
		$amountPaidExpr = "(SELECT COALESCE(SUM(r.amountPaid),0) FROM iop_receipt r WHERE r.InActive = 0 AND r.invoice_no = h.invoice_no)";
		
		$this->db->select(
			"h.*, " .
			"CONCAT(p.lastname, ' ', p.firstname, ' ', COALESCE(p.middlename, '')) AS patient_name" .
			$extraFields . ", " .
			$discExpr . " AS discount, " .
			$amountPaidExpr . " AS amount_paid, " .
			"ROUND((h.total_amount - " . $discExpr . " - " . $amountPaidExpr . "), 2) AS balance",
			false
		);
		$this->db->from('iop_billing h');
		$this->db->join('patient_personal_info p', 'p.patient_no = h.patient_no', 'left');
		$this->db->where('h.invoice_no', $invoice_no);
		$this->db->where('h.InActive', 0);

		return $this->db->get()->row();
	}

	/**
	 * Get invoice line items
	 */
	public function get_invoice_items($invoice_no)
	{
		$this->db->select('*');
		$this->db->from('iop_billing_t');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);

		return $this->db->get()->result();
	}

	/**
	 * Get payments for an invoice
	 */
	public function get_invoice_payments($invoice_no)
	{
		if (!$this->table_exists('cashier_payment_log')) {
			return array();
		}

		$this->db->select('p.*, u.username as cashier_name');
		$this->db->from('cashier_payment_log p');
		$this->db->join('users u', 'u.user_id = p.cashier_id', 'left');
		$this->db->where('p.invoice_no', $invoice_no);
		$this->db->where('p.InActive', 0);
		$this->db->order_by('p.payment_date', 'DESC');

		return $this->db->get()->result();
	}

	public function get_invoice_refunds($invoice_no)
	{
		if (!$this->table_exists('cashier_refund_log')) {
			return array();
		}
		$this->db->select('r.*, u.username as refunded_by_name');
		$this->db->from('cashier_refund_log r');
		$this->db->join('users u', 'u.user_id = r.refunded_by', 'left');
		$this->db->where('r.invoice_no', $invoice_no);
		$this->db->where('r.InActive', 0);
		$this->db->order_by('r.refunded_at', 'DESC');
		return $this->db->get()->result();
	}

	public function get_invoice_refund_totals($invoice_no)
	{
		if (!$this->table_exists('cashier_refund_log')) {
			return array();
		}
		$this->db->select('original_receipt_no, COALESCE(SUM(amount),0) AS total_refunded', false);
		$this->db->from('cashier_refund_log');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$this->db->group_by('original_receipt_no');
		$rows = $this->db->get()->result();
		$out = array();
		if ($rows) {
			foreach ($rows as $r) {
				if (isset($r->original_receipt_no)) {
					$out[(string)$r->original_receipt_no] = isset($r->total_refunded) ? (float)$r->total_refunded : 0.0;
				}
			}
		}
		return $out;
	}

	/**
	 * Get payment methods
	 */
	public function get_payment_methods()
	{
		// Always return default methods - payment_methods table may not exist or have different schema
		return array(
			(object)array('method_code' => 'CASH', 'method_name' => 'Cash', 'requires_reference' => 0),
			(object)array('method_code' => 'MOMO', 'method_name' => 'Mobile Money', 'requires_reference' => 1),
			(object)array('method_code' => 'BANK', 'method_name' => 'Bank Transfer', 'requires_reference' => 1),
			(object)array('method_code' => 'CARD', 'method_name' => 'Card Payment', 'requires_reference' => 1),
			(object)array('method_code' => 'NHIS', 'method_name' => 'NHIS', 'requires_reference' => 1),
		);
	}

	/**
	 * Process a payment with duplicate prevention
	 * Single source of truth: iop_receipt table is the authoritative record for all payments
	 */
	public function process_payment($invoice_no, $amount, $payment_method, $reference, $notes, $cashier_id)
	{
		// Phase 4 / Step 4 — optional facade route.
		// Default OFF; flip $config['BILLING_FACADE_RECEIPT_ROUTE'] = TRUE to enable.
		$this->load->model('app/Billing_facade_model', 'billing_facade_model');
		if ($this->billing_facade_model->is_receipt_route_enabled()) {
			$res = $this->billing_facade_model->record_payment(array(
				'invoice_no'     => $invoice_no,
				'amount'         => $amount,
				'payment_method' => $payment_method,
				'reference'      => $reference,
				'notes'          => $notes,
				'cashier_id'     => $cashier_id,
				'source'         => 'CASHIER',
			));
			if (!empty($res['ok'])) {
				$this->load->model('app/billing_model');
				$this->billing_model->apply_post_payment_side_effects($invoice_no, $res['receipt_no'], $cashier_id);
				return array('success' => true, 'receipt_no' => $res['receipt_no'], 'ssot_sync' => $res['ssot_sync']);
			}
			return array('success' => false, 'error' => $res['error']);
		}

		$invoice = $this->get_invoice_details($invoice_no);
		if (!$invoice) {
			return array('success' => false, 'error' => 'Invoice not found');
		}

		// CRITICAL: Re-calculate balance at payment time to prevent race conditions
		// Single source of truth: SUM(amountPaid) from iop_receipt (legacy convention)
		$this->db->select('COALESCE(SUM(amountPaid), 0) as total_paid');
		$this->db->where('invoice_no', $invoice_no);
		$this->db->where('InActive', 0);
		$paid_result = $this->db->get('iop_receipt')->row();
		$total_paid = round((float)$paid_result->total_paid, 2);
		
		$inv_disc = 0.0;
		if (isset($invoice->discount)) {
			$inv_disc = (float)$invoice->discount;
		} elseif (isset($invoice->discount_amount)) {
			$inv_disc = (float)$invoice->discount_amount;
		} elseif (isset($invoice->discountAmount)) {
			$inv_disc = (float)$invoice->discountAmount;
		}
		$invoice_total = round(((float)$invoice->total_amount - (float)$inv_disc), 2);
		$actual_balance = round(($invoice_total - $total_paid), 2);
		
		// Prevent overpayment
		if ($actual_balance <= 0) {
			return array('success' => false, 'error' => 'Invoice is already fully paid. Balance: GHS 0.00');
		}
		
		if ($amount > $actual_balance + 0.005) {
			return array('success' => false, 'error' => 'Payment amount (GHS ' . number_format($amount, 2) . ') exceeds balance of GHS ' . number_format($actual_balance, 2));
		}

		$hasBalanceDue = $this->column_exists('iop_billing', 'balance_due');
		$hasCashierId = $this->column_exists('iop_receipt', 'cashier_id');

		$this->db->trans_begin();

		try {
			// Generate receipt number
			$receipt_no = $this->generate_receipt_no();
			
			// Double-check for duplicate receipt number (should never happen, but safety first)
			$this->db->where('receipt_no', $receipt_no);
			if ($this->db->count_all_results('iop_receipt') > 0) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Receipt number collision. Please try again.');
			}

			// Insert into iop_receipt (main payment record)
			// Legacy convention: total_amount = invoice total, amountPaid = actual payment
			$receiptData = array(
				'receipt_no' => $receipt_no,
				'invoice_no' => $invoice_no,
				'patient_no' => $invoice->patient_no,
				'iop_id' => isset($invoice->iop_id) ? $invoice->iop_id : null,
				'total_amount' => round($invoice_total, 2),
				'amountPaid' => round($amount, 2),
				'change' => 0,
				'payment_type' => $payment_method,
				'dDate' => date('Y-m-d H:i:s'),
				'InActive' => 0
			);
			if ($hasCashierId) {
				$receiptData['cashier_id'] = $cashier_id;
			}
			$this->db->insert('iop_receipt', $receiptData);

			// Update invoice balance_due if column exists
			if ($hasBalanceDue) {
				$current_balance = isset($invoice->balance_due) ? round((float)$invoice->balance_due, 2) : $actual_balance;
				$new_balance = round(max(0, ($current_balance - (float)$amount)), 2);
				$this->db->where('invoice_no', $invoice_no);
				$this->db->update('iop_billing', array('balance_due' => $new_balance));
			}

			// Log to cashier_payment_log if table exists
			if ($this->table_exists('cashier_payment_log')) {
				$this->db->insert('cashier_payment_log', array(
					'receipt_no' => $receipt_no,
					'invoice_no' => $invoice_no,
					'patient_no' => $invoice->patient_no,
					'amount' => $amount,
					'payment_method' => $payment_method,
					'reference_no' => $reference,
					'notes' => $notes,
					'cashier_id' => $cashier_id,
					'payment_date' => date('Y-m-d H:i:s')
				));
			}
			
			// Log to financial audit trail
			$new_balance = round(($actual_balance - (float)$amount), 2);
			$this->log_financial_audit('PAYMENT', $receipt_no, $invoice_no, $invoice->patient_no, 
				$amount, $actual_balance, $new_balance, 
				'Payment received via ' . $payment_method . ($reference ? ' (Ref: ' . $reference . ')' : ''), 
				$cashier_id);

			// Record to financial ledger via unified billing model if available
			$CI =& get_instance();
			if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'record_payment_to_ledger')) {
				$CI->unified_billing_model->record_payment_to_ledger(
					$receipt_no, $invoice_no, $invoice->patient_no, $amount, strtoupper($payment_method), $cashier_id
				);
			}

			// Best-effort sync: reflect receipt payment into billing_transactions SSOT if available
			$ssot_sync = null;
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_receipt_payment')) {
				$ssot_sync = $this->billing_transaction_model->sync_receipt_payment($receipt_no, $cashier_id);
			}

			$this->load->model('app/billing_model');
			$this->billing_model->apply_post_payment_side_effects($invoice_no, $receipt_no, $cashier_id, isset($invoice->iop_id) ? $invoice->iop_id : null, isset($invoice->patient_no) ? $invoice->patient_no : null);

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Database error occurred');
			}

			$this->db->trans_commit();
			return array('success' => true, 'receipt_no' => $receipt_no, 'ssot_sync' => $ssot_sync);

		} catch (Exception $e) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => $e->getMessage());
		}
	}

	/**
	 * Generate unique receipt number
	 */
	private function generate_receipt_no()
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
	 * Get payment summary for dashboard
	 */
	public function get_payment_summary()
	{
		$today = date('Y-m-d');
		$start = $today . ' 00:00:00';
		$end = date('Y-m-d', strtotime($today . ' +1 day')) . ' 00:00:00';

		// Today's collections from receipts (amountPaid = actual payment amount)
		$this->db->select('COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('dDate >=', $start);
		$this->db->where('dDate <', $end);
		$this->db->where('InActive', 0);
		$today_total = $this->db->get('iop_receipt')->row()->total;

		// Total unpaid invoices - use subquery to calculate balance
		$discExpr = $this->_discount_expr('iop_billing', 'h');
		$sql = "SELECT COUNT(*) as count, COALESCE(SUM(
			h.total_amount - {$discExpr} - 
			COALESCE((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = h.invoice_no AND r.InActive = 0), 0)
		), 0) as total
		FROM iop_billing h 
		WHERE h.InActive = 0 
		AND (h.total_amount - {$discExpr} - 
			COALESCE((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = h.invoice_no AND r.InActive = 0), 0)) > 0";
		$unpaid = $this->db->query($sql)->row();

		// Today's invoice count
		$this->db->where('dDate >=', $start);
		$this->db->where('dDate <', $end);
		$this->db->where('InActive', 0);
		$today_invoices = $this->db->count_all_results('iop_billing');

		return array(
			'today_collections' => (float)$today_total,
			'unpaid_count' => (int)$unpaid->count,
			'unpaid_total' => (float)$unpaid->total,
			'today_invoices' => (int)$today_invoices
		);
	}

	/**
	 * Get daily collections
	 */
	public function get_daily_collections($date, $cashier_id = null)
	{
		$hasCashierId = $this->column_exists('iop_receipt', 'cashier_id');
		$start = $date . ' 00:00:00';
		$end = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
		
		$selectFields = "r.receipt_no, r.invoice_no, r.patient_no, r.amountPaid, r.payment_type, r.dDate,
			CONCAT(p.lastname, ' ', p.firstname) as patient_name";
		
		if ($hasCashierId) {
			$selectFields .= ", u.username as cashier_name";
		}
		
		$this->db->select($selectFields, false);
		$this->db->from('iop_receipt r');
		$this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
		
		if ($hasCashierId) {
			$this->db->join('users u', 'u.user_id = r.cashier_id', 'left');
		}
		
		$this->db->where('r.dDate >=', $start);
		$this->db->where('r.dDate <', $end);
		$this->db->where('r.InActive', 0);

		if ($hasCashierId && $cashier_id !== null && $cashier_id !== '') {
			$this->db->where('r.cashier_id', $cashier_id);
		}

		$this->db->order_by('r.dDate', 'DESC');

		return $this->db->get()->result();
	}

	/**
	 * Get daily summary
	 */
	public function get_daily_summary($date, $cashier_id = null)
	{
		$hasCashierId = $this->column_exists('iop_receipt', 'cashier_id');
		
		$this->db->select('COUNT(*) as count, COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('DATE(dDate)', $date);
		$this->db->where('InActive', 0);

		if ($hasCashierId && $cashier_id !== null && $cashier_id !== '') {
			$this->db->where('cashier_id', $cashier_id);
		}

		return $this->db->get('iop_receipt')->row();
	}

	/**
	 * Get collections grouped by payment method
	 */
	public function get_collections_by_method($date, $cashier_id = null)
	{
		$hasCashierId = $this->column_exists('iop_receipt', 'cashier_id');
		
		$this->db->select('payment_type, COUNT(*) as count, COALESCE(SUM(amountPaid), 0) as total');
		$this->db->where('DATE(dDate)', $date);
		$this->db->where('InActive', 0);

		if ($hasCashierId && $cashier_id !== null && $cashier_id !== '') {
			$this->db->where('cashier_id', $cashier_id);
		}

		$this->db->group_by('payment_type');
		$this->db->order_by('total', 'DESC');

		return $this->db->get('iop_receipt')->result();
	}

	/**
	 * Get list of cashiers (for admin filter)
	 */
	public function get_cashiers()
	{
		$this->db->select('u.user_id, u.username, CONCAT(u.firstname, " ", u.lastname) as fullname');
		$this->db->from('users u');
		$this->db->join('user_roles r', 'r.role_id = u.user_role', 'left');
		$this->db->where('u.InActive', 0);
		$this->db->group_start();
		$this->db->like('r.role_name', 'cashier', 'both', false);
		$this->db->or_like('r.role_name', 'billing', 'both', false);
		$this->db->group_end();
		$this->db->group_by('u.user_id');

		return $this->db->get()->result();
	}

	/**
	 * Get receipt details
	 */
	public function get_receipt($receipt_no)
	{
		$hasPhone = $this->column_exists('patient_personal_info', 'phone');
		$hasAddress = $this->column_exists('patient_personal_info', 'address');
		$hasCashierId = $this->column_exists('iop_receipt', 'cashier_id');
		
		$extraFields = "";
		if ($hasPhone) $extraFields .= ", p.phone";
		if ($hasAddress) $extraFields .= ", p.address";
		$userSelect = $hasCashierId ? ", u.username as cashier_name, CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,'')) as cashier_fullname" : "";
		
		$this->db->select("r.*, 
			CONCAT(p.lastname, ' ', p.firstname, ' ', COALESCE(p.middlename, '')) as patient_name" . $extraFields . $userSelect . ",
			h.total_amount as invoice_total", false);
		$this->db->from('iop_receipt r');
		$this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
		if ($hasCashierId) {
			$this->db->join('users u', 'u.user_id = r.cashier_id', 'left');
		}
		$this->db->join('iop_billing h', 'h.invoice_no = r.invoice_no', 'left');
		$this->db->where('r.receipt_no', $receipt_no);

		return $this->db->get()->row();
	}

	/**
	 * Void a payment (admin only)
	 */
	public function void_payment($receipt_no, $reason, $voided_by)
	{
		$receipt = $this->get_receipt($receipt_no);
		if (!$receipt) {
			return array('success' => false, 'error' => 'Receipt not found');
		}

		$hasAmountPaid = $this->column_exists('iop_billing', 'amount_paid');
		$void_amount = isset($receipt->amountPaid) ? (float)$receipt->amountPaid : 0.0;
		if ($void_amount <= 0 && isset($receipt->total_amount)) {
			$void_amount = (float)$receipt->total_amount;
		}

		$strict_reversal = false;
		try {
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'ensure_billing_transaction_schema')) {
				$this->billing_transaction_model->ensure_billing_transaction_schema();
			}
			$CI =& get_instance();
			if (!isset($CI->unified_billing_model)) {
				$CI->load->model('app/unified_billing_model');
			}
			if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'ensure_unified_billing_schema')) {
				$CI->unified_billing_model->ensure_unified_billing_schema();
			}
			$strict_reversal = $this->db->table_exists('billing_transactions')
				&& $this->db->table_exists('patient_financial_ledger')
				&& $this->db->table_exists('financial_ledger');
		} catch (Exception $e) {
			$strict_reversal = false;
		}

		$this->db->trans_begin();

		try {
			$reversal_desc = null;
			// Mark receipt as inactive
			$this->db->where('receipt_no', $receipt_no);
			$this->db->update('iop_receipt', array('InActive' => 1));

			$ssot_ok = null;
			$ssot_err = '';
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'void_receipt_payment')) {
				try {
					$ssot_res = $this->billing_transaction_model->void_receipt_payment($receipt_no, $voided_by, $reason);
					if (is_array($ssot_res) && array_key_exists('ok', $ssot_res)) {
						$ssot_ok = (bool)$ssot_res['ok'];
						if (!$ssot_ok && isset($ssot_res['error'])) {
							$ssot_err = (string)$ssot_res['error'];
						}
					} else {
						$ssot_ok = true;
					}
				} catch (Exception $e) {
					$ssot_ok = false;
					$ssot_err = $e->getMessage();
				}
			}

			$ledger_ok = null;
			$ledger_err = '';
			$CI =& get_instance();
			if (!isset($CI->unified_billing_model)) {
				$CI->load->model('app/unified_billing_model');
			}
			if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'void_receipt_in_ledger')) {
				try {
					$ledger_ok = (bool)$CI->unified_billing_model->void_receipt_in_ledger($receipt_no, $voided_by, $reason);
					if (!$ledger_ok) {
						$ledger_err = 'Ledger reversal returned false';
					}
				} catch (Exception $e) {
					$ledger_ok = false;
					$ledger_err = $e->getMessage();
				}
			}

			if ($ssot_ok === false || $ledger_ok === false) {
				$desc = 'Void completed but reversal failed.';
				if ($ssot_ok === false) {
					$desc .= ' SSOT=' . ($ssot_err !== '' ? $ssot_err : 'FAILED') . '.';
				}
				if ($ledger_ok === false) {
					$desc .= ' LEDGER=' . ($ledger_err !== '' ? $ledger_err : 'FAILED') . '.';
				}
				log_message('error', 'VOID reversal failure: receipt_no=' . $receipt_no . ' invoice_no=' . (string)$receipt->invoice_no . ' ' . $desc);
				if ($strict_reversal) {
					$reversal_desc = $desc;
					throw new Exception($desc);
				}
				$this->log_financial_audit('CORRECTION', $receipt_no, $receipt->invoice_no, $receipt->patient_no, $void_amount, null, null, $desc, $voided_by);
			}

			// Update cashier_payment_log if exists
			if ($this->table_exists('cashier_payment_log')) {
				$this->db->where('receipt_no', $receipt_no);
				$this->db->update('cashier_payment_log', array(
					'voided' => 1,
					'void_reason' => $reason,
					'voided_by' => $voided_by,
					'voided_at' => date('Y-m-d H:i:s')
				));
			}

			// Reduce amount_paid on invoice if column exists
			if ($hasAmountPaid) {
				$this->db->set('amount_paid', 'amount_paid - ' . (float)$void_amount, FALSE);
				$this->db->where('invoice_no', $receipt->invoice_no);
				$this->db->update('iop_billing');
			}

			// Recompute invoice payment status + balance from receipts SSOT
			$this->load->model('app/billing_model');
			$newStatus = $this->billing_model->update_payment_status((string)$receipt->invoice_no, $voided_by);
			if ($newStatus) {
				if (method_exists($this->billing_model, 'reconcile_invoice_operational_side_effects')) {
					$this->billing_model->reconcile_invoice_operational_side_effects((string)$receipt->invoice_no, $newStatus, $voided_by);
				}
			}
			if ($this->column_exists('iop_billing', 'receipt_no')) {
				$this->db->where(array('invoice_no' => (string)$receipt->invoice_no, 'InActive' => 0));
				$this->db->limit(1);
				$hdr = $this->db->get('iop_billing')->row();
				if ($hdr) {
					$last = $this->billing_model->OR_num((string)$receipt->invoice_no);
					$this->db->where(array('invoice_no' => (string)$receipt->invoice_no, 'InActive' => 0));
					$this->db->update('iop_billing', array('receipt_no' => ($last && isset($last->receipt_no)) ? (string)$last->receipt_no : null));
				}
			}

			// Log to financial audit
			$this->log_financial_audit('VOID', $receipt_no, $receipt->invoice_no, $receipt->patient_no, 
				$void_amount, null, null, 'Payment voided: ' . $reason, $voided_by);

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Database error occurred');
			}

			$this->db->trans_commit();
			return array('success' => true);

		} catch (Exception $e) {
			$this->db->trans_rollback();
			if (isset($reversal_desc) && $reversal_desc !== null) {
				$this->log_financial_audit('CORRECTION', $receipt_no, $receipt->invoice_no, $receipt->patient_no, $void_amount, null, null, $reversal_desc, $voided_by);
			}
			return array('success' => false, 'error' => $e->getMessage());
		}
	}

	public function refund_payment($receipt_no, $amount, $reason, $refunded_by, $refund_receipt_no = null)
	{
		$this->ensure_cashier_schema();
		$receipt_no = trim((string)$receipt_no);
		$amount = (float)$amount;
		$reason = trim((string)$reason);
		$refunded_by = (string)$refunded_by;
		$refund_receipt_no = ($refund_receipt_no !== null) ? trim((string)$refund_receipt_no) : '';
		if ($receipt_no === '' || $amount <= 0 || $reason === '') {
			return array('success' => false, 'error' => 'Receipt number, amount, and reason are required');
		}

		if ($refund_receipt_no !== '') {
			if (!preg_match('/^[A-Za-z0-9_-]{6,50}$/', $refund_receipt_no)) {
				return array('success' => false, 'error' => 'Invalid refund reference');
			}
			$this->db->where(array('refund_receipt_no' => $refund_receipt_no, 'InActive' => 0));
			$existing = $this->db->get('cashier_refund_log')->row();
			if ($existing) {
				if (isset($existing->original_receipt_no) && (string)$existing->original_receipt_no === $receipt_no) {
					return array('success' => true, 'refund_receipt_no' => (string)$refund_receipt_no);
				}
				return array('success' => false, 'error' => 'Refund reference already used');
			}
		}

		$receipt = $this->get_receipt($receipt_no);
		if (!$receipt) {
			return array('success' => false, 'error' => 'Receipt not found');
		}
		if (isset($receipt->InActive) && (int)$receipt->InActive === 1) {
			return array('success' => false, 'error' => 'Receipt is inactive/voided');
		}
		$orig_amount = isset($receipt->amountPaid) ? (float)$receipt->amountPaid : 0.0;
		if ($orig_amount <= 0) {
			return array('success' => false, 'error' => 'Receipt amount is invalid');
		}

		$this->db->select('COALESCE(SUM(amount),0) AS total', false);
		$this->db->where(array('original_receipt_no' => $receipt_no, 'InActive' => 0));
		$rr = $this->db->get('cashier_refund_log')->row();
		$already_refunded = ($rr && isset($rr->total)) ? (float)$rr->total : 0.0;
		$max_refund = max(0.0, $orig_amount - $already_refunded);
		if ($amount > ($max_refund + 0.0001)) {
			return array('success' => false, 'error' => 'Refund amount exceeds refundable balance. Max: GHS ' . number_format($max_refund, 2));
		}

		if ($refund_receipt_no === '') {
			$refund_receipt_no = 'RF' . date('YmdHis') . (string)mt_rand(100, 999);
		}
		$now = date('Y-m-d H:i:s');
		$invoice_no = isset($receipt->invoice_no) ? (string)$receipt->invoice_no : '';
		$patient_no = isset($receipt->patient_no) ? (string)$receipt->patient_no : '';
		$payment_method = isset($receipt->payment_type) ? strtoupper(trim((string)$receipt->payment_type)) : 'CASH';
		if ($payment_method === '') {
			$payment_method = 'CASH';
		}

		$strict_reversal = false;
		try {
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'ensure_billing_transaction_schema')) {
				$this->billing_transaction_model->ensure_billing_transaction_schema();
			}
			$CI =& get_instance();
			if (!isset($CI->unified_billing_model)) {
				$CI->load->model('app/unified_billing_model');
			}
			if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'ensure_unified_billing_schema')) {
				$CI->unified_billing_model->ensure_unified_billing_schema();
			}
			$strict_reversal = $this->db->table_exists('billing_transactions')
				&& $this->db->table_exists('patient_financial_ledger')
				&& $this->db->table_exists('financial_ledger');
		} catch (Exception $e) {
			$strict_reversal = false;
		}

		$this->db->trans_begin();
		try {
			$reversal_desc = null;
			$ins = array(
				'receipt_no' => $refund_receipt_no,
				'invoice_no' => $invoice_no,
				'patient_no' => $patient_no,
				'amountPaid' => (0 - $amount),
				'InActive' => 0,
			);
			if ($this->column_exists('iop_receipt', 'payment_type')) {
				$ins['payment_type'] = 'REFUND';
			}
			if ($this->column_exists('iop_receipt', 'dDate')) {
				$ins['dDate'] = $now;
			}
			if ($this->column_exists('iop_receipt', 'cashier_id')) {
				$ins['cashier_id'] = $refunded_by;
			}
			if ($this->column_exists('iop_receipt', 'remarks')) {
				$ins['remarks'] = 'Refund for receipt ' . $receipt_no . ': ' . $reason;
			}
			if ($this->column_exists('iop_receipt', 'total_amount')) {
				$ins['total_amount'] = (0 - $amount);
			}
			$this->db->insert('iop_receipt', $ins);

			if ($this->table_exists('cashier_payment_log')) {
				$this->db->insert('cashier_payment_log', array(
					'receipt_no' => $refund_receipt_no,
					'invoice_no' => $invoice_no,
					'patient_no' => $patient_no,
					'amount' => (0 - $amount),
					'payment_method' => 'REFUND',
					'reference_no' => $receipt_no,
					'notes' => 'Refund for receipt ' . $receipt_no . ': ' . $reason,
					'cashier_id' => $refunded_by,
					'payment_date' => $now,
					'voided' => 0,
					'InActive' => 0,
				));
			}

			$this->db->insert('cashier_refund_log', array(
				'original_receipt_no' => $receipt_no,
				'refund_receipt_no' => $refund_receipt_no,
				'invoice_no' => $invoice_no,
				'patient_no' => $patient_no,
				'amount' => $amount,
				'reason' => $reason,
				'refunded_by' => $refunded_by,
				'refunded_at' => $now,
				'InActive' => 0,
			));

			$ssot_ok = null;
			$ssot_err = '';
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'refund_receipt_payment')) {
				try {
					$ssot_res = $this->billing_transaction_model->refund_receipt_payment($receipt_no, $refund_receipt_no, $amount, $refunded_by, $reason);
					if (is_array($ssot_res) && array_key_exists('ok', $ssot_res)) {
						$ssot_ok = (bool)$ssot_res['ok'];
						if (!$ssot_ok && isset($ssot_res['error'])) {
							$ssot_err = (string)$ssot_res['error'];
						}
					} else {
						$ssot_ok = true;
					}
				} catch (Exception $e) {
					$ssot_ok = false;
					$ssot_err = $e->getMessage();
				}
			}

			$ledger_ok = null;
			$ledger_err = '';
			$CI =& get_instance();
			if (!isset($CI->unified_billing_model)) {
				$CI->load->model('app/unified_billing_model');
			}
			if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'refund_receipt_in_ledger')) {
				try {
					$ledger_ok = (bool)$CI->unified_billing_model->refund_receipt_in_ledger($refund_receipt_no, $receipt_no, $invoice_no, $patient_no, $amount, $payment_method, $refunded_by, $reason);
					if (!$ledger_ok) {
						$ledger_err = 'Ledger reversal returned false';
					}
				} catch (Exception $e) {
					$ledger_ok = false;
					$ledger_err = $e->getMessage();
				}
			}

			if ($ssot_ok === false || $ledger_ok === false) {
				$desc = 'Refund completed but reversal failed.';
				if ($ssot_ok === false) {
					$desc .= ' SSOT=' . ($ssot_err !== '' ? $ssot_err : 'FAILED') . '.';
				}
				if ($ledger_ok === false) {
					$desc .= ' LEDGER=' . ($ledger_err !== '' ? $ledger_err : 'FAILED') . '.';
				}
				log_message('error', 'REFUND reversal failure: refund_receipt_no=' . $refund_receipt_no . ' original_receipt_no=' . $receipt_no . ' invoice_no=' . (string)$invoice_no . ' ' . $desc);
				if ($strict_reversal) {
					$reversal_desc = $desc;
					throw new Exception($desc);
				}
				$this->log_financial_audit('CORRECTION', $refund_receipt_no, $invoice_no, $patient_no, $amount, null, null, $desc, $refunded_by);
			}

			$this->load->model('app/billing_model');
			$newStatus = $this->billing_model->update_payment_status((string)$invoice_no, $refunded_by);
			if ($newStatus) {
				if (method_exists($this->billing_model, 'reconcile_invoice_operational_side_effects')) {
					$this->billing_model->reconcile_invoice_operational_side_effects((string)$invoice_no, $newStatus, $refunded_by);
				}
			}
			if ($this->column_exists('iop_billing', 'receipt_no')) {
				$this->db->where(array('invoice_no' => (string)$invoice_no, 'InActive' => 0));
				$this->db->update('iop_billing', array('receipt_no' => $refund_receipt_no));
			}

			$this->log_financial_audit('REFUND', $refund_receipt_no, $invoice_no, $patient_no, $amount, null, null, 'Refund issued for receipt ' . $receipt_no . ': ' . $reason, $refunded_by);

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Database error occurred');
			}
			$this->db->trans_commit();
			return array('success' => true, 'refund_receipt_no' => $refund_receipt_no);
		} catch (Exception $e) {
			$this->db->trans_rollback();
			if (isset($reversal_desc) && $reversal_desc !== null) {
				$this->log_financial_audit('CORRECTION', $refund_receipt_no, $invoice_no, $patient_no, $amount, null, null, $reversal_desc, $refunded_by);
			}
			return array('success' => false, 'error' => $e->getMessage());
		}
	}

	/**
	 * Log financial audit entry
	 * Single source of truth for all financial activities
	 */
	public function log_financial_audit($action_type, $receipt_no, $invoice_no, $patient_no, $amount, $balance_before, $balance_after, $description, $performed_by)
	{
		static $schema_done = false;
		static $has_action_type = false;
		static $has_description = false;
		static $has_notes = false;
		static $has_event_type = false;
		static $has_iop_id = false;

		if (!$schema_done) {
			// Ensure table exists before attempting insert
			if (!$this->table_exists('financial_audit_log')) {
				// Try to create it
				$this->ensure_cashier_schema();
			}
			
			// Check if action_type column exists (for older table versions)
			if ($this->table_exists('financial_audit_log') && !$this->column_exists('financial_audit_log', 'action_type')) {
				$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
				$this->db->db_debug = false;
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `action_type` ENUM('PAYMENT', 'VOID', 'REFUND', 'ADJUSTMENT', 'CORRECTION') DEFAULT 'PAYMENT'");
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `receipt_no` VARCHAR(50)");
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `invoice_no` VARCHAR(50)");
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `balance_before` DECIMAL(12,2)");
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `balance_after` DECIMAL(12,2)");
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `description` TEXT");
				$this->db->query("ALTER TABLE `financial_audit_log` ADD COLUMN `ip_address` VARCHAR(45)");
				if ($this->column_exists('financial_audit_log', 'event_type')) {
					$this->db->query("ALTER TABLE `financial_audit_log` MODIFY COLUMN `event_type` VARCHAR(50) NULL");
				}
				if ($old_debug !== null) { $this->db->db_debug = $old_debug; }
			}

			$has_action_type = $this->column_exists('financial_audit_log', 'action_type');
			$has_description = $this->column_exists('financial_audit_log', 'description');
			$has_notes = $this->column_exists('financial_audit_log', 'notes');
			$has_event_type = $this->column_exists('financial_audit_log', 'event_type');
			$has_iop_id = $this->column_exists('financial_audit_log', 'iop_id');

			$schema_done = true;
		}

		if (!$has_action_type) {
			return false;
		}
		
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';
		
		$insert_data = array(
			'action_type' => $action_type,
			'receipt_no' => $receipt_no,
			'invoice_no' => $invoice_no,
			'patient_no' => $patient_no,
			'amount' => $amount,
			'balance_before' => $balance_before,
			'balance_after' => $balance_after,
			'performed_by' => $performed_by,
			'performed_at' => date('Y-m-d H:i:s'),
			'ip_address' => $ip
		);

		if ($has_description) {
			$insert_data['description'] = $description;
		}
		if ($has_notes) {
			$insert_data['notes'] = $description;
		}
		if ($has_event_type) {
			$insert_data['event_type'] = $action_type;
		}
		if ($has_iop_id) {
			$iop_id_resolved = '';
			if (isset($_POST['opd_no']) && trim((string)$_POST['opd_no']) !== '') {
				$iop_id_resolved = trim((string)$_POST['opd_no']);
			} elseif (isset($_POST['iop_id']) && trim((string)$_POST['iop_id']) !== '') {
				$iop_id_resolved = trim((string)$_POST['iop_id']);
			}
			if ($iop_id_resolved === '' && $invoice_no !== '') {
				$inv = $this->db->get_where('iop_billing', array('invoice_no' => $invoice_no))->row();
				if ($inv) {
					$iop_id_resolved = isset($inv->iop_id) ? (string)$inv->iop_id : '';
				}
			}
			if ($iop_id_resolved !== '') {
				$insert_data['iop_id'] = $iop_id_resolved;
			}
		}

		try {
			return $this->db->insert('financial_audit_log', $insert_data);
		} catch (Exception $e) {
			// Log error silently - don't break payment flow for audit logging issues
			log_message('error', 'Financial audit log failed: ' . $e->getMessage());
			return false;
		}
	}

	/**
	 * Get financial reconciliation report
	 * Compares iop_receipt totals with iop_billing to detect discrepancies
	 */
	public function get_financial_reconciliation($date_from = null, $date_to = null)
	{
		if (!$date_from) $date_from = date('Y-m-01');
		if (!$date_to) $date_to = date('Y-m-d');
		
		// Get all invoices with payment discrepancies
		$discExpr = $this->_discount_expr('iop_billing', 'b');
		$sql = "
			SELECT 
				b.invoice_no,
				b.patient_no,
				b.total_amount as invoice_total,
				{$discExpr} as discount,
				(b.total_amount - {$discExpr}) as net_amount,
				COALESCE(r.total_paid, 0) as total_paid,
				((b.total_amount - {$discExpr}) - COALESCE(r.total_paid, 0)) as balance,
				COALESCE(r.receipt_count, 0) as receipt_count,
				CONCAT(p.lastname, ' ', p.firstname) as patient_name
			FROM iop_billing b
			LEFT JOIN (
				SELECT invoice_no, 
					SUM(amountPaid) as total_paid,
					COUNT(*) as receipt_count
				FROM iop_receipt 
				WHERE InActive = 0 
				GROUP BY invoice_no
			) r ON r.invoice_no = b.invoice_no
			LEFT JOIN patient_personal_info p ON p.patient_no = b.patient_no
			WHERE b.InActive = 0
			AND DATE(b.dDate) BETWEEN ? AND ?
			HAVING balance < 0 OR (total_paid > net_amount)
			ORDER BY balance ASC
		";
		
		$query = $this->db->query($sql, array($date_from, $date_to));
		return $query->result();
	}

	/**
	 * Get today's payments
	 */
	public function get_today_payments($limit = 20)
	{
		$today = date('Y-m-d');
		
		$this->db->select('r.*, p.firstname, p.lastname, p.patient_no');
		$this->db->from('iop_receipt r');
		$this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
		$this->db->where('DATE(r.dDate)', $today);
		$this->db->where('r.InActive', 0);
		$this->db->order_by('r.dDate', 'DESC');
		$this->db->limit($limit);
		
		return $this->db->get()->result();
	}

	/**
	 * Get audit log entries
	 */
	public function get_audit_log($limit = 100, $offset = 0, $filters = array())
	{
		if (!$this->table_exists('financial_audit_log')) {
			return array();
		}
		
		$this->db->select("a.*, CONCAT(COALESCE(u.firstname,''), ' ', COALESCE(u.lastname,'')) as user_name", false);
		$this->db->from('financial_audit_log a');
		$this->db->join('users u', 'u.user_id = a.performed_by', 'left');
		
		if (!empty($filters['action_type'])) {
			$this->db->where('a.action_type', $filters['action_type']);
		}
		if (!empty($filters['invoice_no'])) {
			$this->db->where('a.invoice_no', $filters['invoice_no']);
		}
		if (!empty($filters['date_from'])) {
			$this->db->where('DATE(a.performed_at) >=', $filters['date_from']);
		}
		if (!empty($filters['date_to'])) {
			$this->db->where('DATE(a.performed_at) <=', $filters['date_to']);
		}
		
		$this->db->order_by('a.performed_at', 'DESC');
		$this->db->limit($limit, $offset);
		
		return $this->db->get()->result();
	}

	/**
	 * Trigger dispatch notifications for an invoice payment
	 */
	public function trigger_dispatch_notifications($invoice_no, $receipt_no, $cashier_id)
	{
		$this->ensure_cashier_schema();

		// Fetch invoice details
		$invoice = $this->get_invoice_details($invoice_no);
		if (!$invoice) return false;

		$patient_no = $invoice->patient_no;
		$patient_name = $invoice->patient_name;

		// Fetch invoice line items
		$this->load->model('app/billing_model');
		$items = $this->billing_model->detailsInv2($invoice_no);
		if (empty($items)) return false;

		// Group items by department
		$groups = array();
		foreach ($items as $item) {
			$raw_mod = isset($item->source_module) ? strtoupper(trim((string)$item->source_module)) : '';
			
			// Try to deduce from bill_name or categories if source_module is empty
			if ($raw_mod === '') {
				$bill_name_lower = strtolower($item->bill_name);
				if (strpos($bill_name_lower, 'lab') !== false || strpos($bill_name_lower, 'test') !== false) {
					$raw_mod = 'LABORATORY';
				} elseif (strpos($bill_name_lower, 'drug') !== false || strpos($bill_name_lower, 'tablet') !== false || strpos($bill_name_lower, 'syrup') !== false || strpos($bill_name_lower, 'capsule') !== false) {
					$raw_mod = 'PHARMACY';
				} elseif (strpos($bill_name_lower, 'scan') !== false || strpos($bill_name_lower, 'ultrasound') !== false || strpos($bill_name_lower, 'sono') !== false) {
					$raw_mod = 'SONOGRAPHY';
				} elseif (strpos($bill_name_lower, 'xray') !== false || strpos($bill_name_lower, 'x-ray') !== false || strpos($bill_name_lower, 'radiology') !== false) {
					$raw_mod = 'RADIOLOGY';
				} elseif (strpos($bill_name_lower, 'procedure') !== false) {
					$raw_mod = 'PROCEDURE';
				} elseif (strpos($bill_name_lower, 'consultation') !== false || strpos($bill_name_lower, 'opd fee') !== false) {
					$raw_mod = 'CONSULTATION';
				} else {
					$raw_mod = 'OTHER';
				}
			}

			// Normalize department name
			$dept = 'OTHER';
			if (in_array($raw_mod, array('LAB', 'LABORATORY'))) {
				$dept = 'LABORATORY';
			} elseif ($raw_mod === 'PHARMACY') {
				$dept = 'PHARMACY';
			} elseif ($raw_mod === 'SONOGRAPHY') {
				$dept = 'SONOGRAPHY';
			} elseif ($raw_mod === 'RADIOLOGY') {
				$dept = 'RADIOLOGY';
			} elseif ($raw_mod === 'PROCEDURE') {
				$dept = 'PROCEDURE';
			} elseif ($raw_mod === 'CONSULTATION') {
				$dept = 'CONSULTATION';
			}

			$groups[$dept][] = $item->bill_name . ' (Qty: ' . $item->qty . ')';
		}

		// Insert notifications for each department group
		foreach ($groups as $dept => $itemList) {
			// Check if already exists for this invoice and department
			$this->db->where(array(
				'invoice_no' => $invoice_no,
				'department' => $dept,
				'InActive'   => 0
			));
			$existing = $this->db->get('payment_dispatch_notifications')->row();

			if (!$existing) {
				$this->db->insert('payment_dispatch_notifications', array(
					'invoice_no'   => $invoice_no,
					'receipt_no'   => $receipt_no,
					'patient_no'   => $patient_no,
					'patient_name' => $patient_name,
					'department'   => $dept,
					'item_details' => implode(', ', $itemList),
					'status'       => 'PENDING',
					'created_at'   => date('Y-m-d H:i:s'),
					'InActive'     => 0
				));
			} else {
				// Update with the latest receipt no
				$this->db->where('notification_id', $existing->notification_id);
				$this->db->update('payment_dispatch_notifications', array(
					'receipt_no' => $receipt_no
				));
			}
		}

		return true;
	}

	/**
	 * Get dispatch notifications for an invoice
	 */
	public function get_dispatch_notifications($invoice_no)
	{
		$this->ensure_cashier_schema();
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$this->db->order_by('created_at', 'ASC');
		return $this->db->get('payment_dispatch_notifications')->result();
	}

	/**
	 * Mark a notification as dispatched (completed/acknowledged by dept staff)
	 */
	public function mark_notification_dispatched($notification_id, $user_id)
	{
		$this->ensure_cashier_schema();
		$this->db->where('notification_id', $notification_id);
		return $this->db->update('payment_dispatch_notifications', array(
			'status'        => 'DISPATCHED',
			'dispatched_at' => date('Y-m-d H:i:s'),
			'dispatched_by' => $user_id
		));
	}

	/**
	 * Get pending dispatch notifications for a specific department
	 */
	public function get_pending_dept_notifications($department, $limit = 50)
	{
		$this->ensure_cashier_schema();
		$this->db->where(array(
			'department' => $department,
			'status'     => 'PENDING',
			'InActive'   => 0
		));
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		return $this->db->get('payment_dispatch_notifications')->result();
	}
}
