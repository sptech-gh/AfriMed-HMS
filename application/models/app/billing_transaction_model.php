<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Billing Transaction Model - SINGLE SOURCE OF TRUTH
 * 
 * This model provides unified billing and medication status management.
 * All departments (Pharmacy, Lab, IPD, OPD) read from this single source.
 * 
 * Status Flow:
 *   ORDERED → APPROVED → DISPENSED → COMPLETED
 *   
 * Payment Status:
 *   PENDING → PARTIAL → PAID → REFUNDED | CANCELLED
 */
class Billing_transaction_model extends CI_Model {

    // ==================== STATUS CONSTANTS ====================
    // Order Status
    const STATUS_ORDERED    = 'ORDERED';
    const STATUS_APPROVED   = 'APPROVED';
    const STATUS_DISPENSED  = 'DISPENSED';
    const STATUS_COMPLETED  = 'COMPLETED';
    const STATUS_CANCELLED  = 'CANCELLED';
    
    // Payment Status
    const PAY_PENDING   = 'PENDING';
    const PAY_PARTIAL   = 'PARTIAL';
    const PAY_PAID      = 'PAID';
    const PAY_REFUNDED  = 'REFUNDED';
    const PAY_CANCELLED = 'CANCELLED';
    const PAY_WAIVED    = 'WAIVED';
    const PAY_NHIS      = 'NHIS';
    
    // Department Types
    const DEPT_PHARMACY = 'PHARMACY';
    const DEPT_LAB      = 'LABORATORY';
    const DEPT_IPD      = 'IPD';
    const DEPT_OPD      = 'OPD';
    const DEPT_IMAGING  = 'IMAGING';
    
    // Item Types
    const ITEM_DRUG     = 'DRUG';
    const ITEM_LAB      = 'LAB_TEST';
    const ITEM_SERVICE  = 'SERVICE';
    const ITEM_ROOM     = 'ROOM';
    const ITEM_CONSULT  = 'CONSULTATION';
    const ITEM_IMAGING  = 'IMAGING';

    public function __construct(){
        parent::__construct();
    }

    // ==================== SCHEMA MANAGEMENT ====================
    
    /**
     * Ensure all required tables exist (idempotent)
     */
    public function ensure_billing_transaction_schema(){
        // Main transaction table - SINGLE SOURCE OF TRUTH
        $this->db->query("CREATE TABLE IF NOT EXISTS `billing_transactions` (
            `txn_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `txn_ref` varchar(30) NOT NULL COMMENT 'Unique transaction reference',
            `patient_no` varchar(25) DEFAULT NULL,
            `encounter_id` varchar(25) DEFAULT NULL COMMENT 'iop_id or admission_id',
            `encounter_type` varchar(10) NOT NULL DEFAULT 'OPD' COMMENT 'OPD, IPD',
            `department` varchar(20) NOT NULL COMMENT 'PHARMACY, LABORATORY, IPD, OPD, IMAGING',
            `item_type` varchar(20) NOT NULL COMMENT 'DRUG, LAB_TEST, SERVICE, ROOM, CONSULTATION, IMAGING',
            `item_id` int(11) DEFAULT NULL COMMENT 'FK to source item (medicine_id, lab_id, etc)',
            `item_ref` varchar(50) DEFAULT NULL COMMENT 'Source reference (iop_med_id, lab_request_id)',
            `item_name` varchar(255) NOT NULL,
            `item_code` varchar(50) DEFAULT NULL,
            `quantity` decimal(10,2) NOT NULL DEFAULT 1.00,
            `unit_price` decimal(18,2) NOT NULL DEFAULT 0.00,
            `gross_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `discount_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `discount_reason` varchar(100) DEFAULT NULL,
            `tax_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `net_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `paid_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `balance_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `payer_type` varchar(20) NOT NULL DEFAULT 'CASH' COMMENT 'CASH, NHIS, INSURANCE',
            `insurance_claim_id` int(11) DEFAULT NULL,
            `payment_status` varchar(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, PARTIAL, PAID, REFUNDED, CANCELLED, WAIVED, NHIS',
            `order_status` varchar(20) NOT NULL DEFAULT 'ORDERED' COMMENT 'ORDERED, APPROVED, DISPENSED, COMPLETED, CANCELLED',
            `invoice_no` varchar(50) DEFAULT NULL,
            `receipt_no` varchar(50) DEFAULT NULL,
            `notes` text,
            `created_by` varchar(25) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `approved_by` varchar(25) DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `dispensed_by` varchar(25) DEFAULT NULL,
            `dispensed_at` datetime DEFAULT NULL,
            `completed_by` varchar(25) DEFAULT NULL,
            `completed_at` datetime DEFAULT NULL,
            `cancelled_by` varchar(25) DEFAULT NULL,
            `cancelled_at` datetime DEFAULT NULL,
            `cancel_reason` varchar(255) DEFAULT NULL,
            `updated_at` datetime NOT NULL,
            `updated_by` varchar(25) DEFAULT NULL,
            `InActive` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`txn_id`),
            UNIQUE KEY `uq_txn_ref` (`txn_ref`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_encounter` (`encounter_id`),
            KEY `idx_department` (`department`),
            KEY `idx_payment_status` (`payment_status`),
            KEY `idx_order_status` (`order_status`),
            KEY `idx_item_ref` (`item_ref`),
            KEY `idx_invoice` (`invoice_no`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Backward-compatible schema upgrade: some installs have an older
		// billing_transactions table (transaction_id/transaction_ref) without item_ref.
		// This causes runtime SQL errors like "Unknown column 'item_ref'".
		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) { $this->db->db_debug = false; }
		try {
			if ($this->table_exists('billing_transactions')) {
				// Walk-in subject support: allow NULL patient_no/encounter_id
				if ($this->column_exists('billing_transactions', 'patient_no')) {
					$this->db->query("ALTER TABLE `billing_transactions` MODIFY COLUMN `patient_no` VARCHAR(25) DEFAULT NULL");
				}
				if ($this->column_exists('billing_transactions', 'encounter_id')) {
					$this->db->query("ALTER TABLE `billing_transactions` MODIFY COLUMN `encounter_id` VARCHAR(25) DEFAULT NULL");
				}

				// 1) Rename legacy id/ref columns to SSOT names
				if ($this->column_exists('billing_transactions', 'transaction_id') && !$this->column_exists('billing_transactions', 'txn_id')) {
					$this->db->query("ALTER TABLE `billing_transactions` CHANGE COLUMN `transaction_id` `txn_id` BIGINT NOT NULL AUTO_INCREMENT");
				}
				if ($this->column_exists('billing_transactions', 'transaction_ref') && !$this->column_exists('billing_transactions', 'txn_ref')) {
					$this->db->query("ALTER TABLE `billing_transactions` CHANGE COLUMN `transaction_ref` `txn_ref` VARCHAR(50) NOT NULL");
				}

				// 2) Add missing SSOT columns used by POS / service-gate queries
				if (!$this->column_exists('billing_transactions', 'encounter_type')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `encounter_type` VARCHAR(10) NOT NULL DEFAULT 'OPD' AFTER `encounter_id`");
				}
				if (!$this->column_exists('billing_transactions', 'item_ref')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `item_ref` VARCHAR(50) DEFAULT NULL AFTER `item_id`");
				}
				if (!$this->column_exists('billing_transactions', 'item_code')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `item_code` VARCHAR(50) DEFAULT NULL AFTER `item_name`");
				}
				if (!$this->column_exists('billing_transactions', 'gross_amount')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `gross_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER `unit_price`");
					if ($this->column_exists('billing_transactions', 'total_amount')) {
						$this->db->query("UPDATE `billing_transactions` SET `gross_amount` = COALESCE(`total_amount`, `net_amount`, 0) WHERE `gross_amount` = 0");
					}
				}
				if (!$this->column_exists('billing_transactions', 'discount_reason')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `discount_reason` VARCHAR(100) DEFAULT NULL AFTER `discount_amount`");
				}
				if (!$this->column_exists('billing_transactions', 'paid_amount')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `paid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER `net_amount`");
				}
				if (!$this->column_exists('billing_transactions', 'balance_amount')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `balance_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00 AFTER `paid_amount`");
					if ($this->column_exists('billing_transactions', 'net_amount')) {
						$this->db->query("UPDATE `billing_transactions` SET `balance_amount` = COALESCE(`net_amount`, 0) WHERE `balance_amount` = 0");
					}
				}
				if (!$this->column_exists('billing_transactions', 'order_status')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `order_status` VARCHAR(20) NOT NULL DEFAULT 'ORDERED' AFTER `payment_status`");
				}
				if (!$this->column_exists('billing_transactions', 'invoice_no')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `invoice_no` VARCHAR(50) DEFAULT NULL AFTER `order_status`");
				}
				if (!$this->column_exists('billing_transactions', 'receipt_no')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `receipt_no` VARCHAR(50) DEFAULT NULL AFTER `invoice_no`");
				}
				if (!$this->column_exists('billing_transactions', 'approved_by')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `approved_by` VARCHAR(25) DEFAULT NULL AFTER `created_at`");
				}
				if (!$this->column_exists('billing_transactions', 'approved_at')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `approved_at` DATETIME DEFAULT NULL AFTER `approved_by`");
				}
				if (!$this->column_exists('billing_transactions', 'dispensed_by')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `dispensed_by` VARCHAR(25) DEFAULT NULL AFTER `approved_at`");
				}
				if (!$this->column_exists('billing_transactions', 'dispensed_at')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `dispensed_at` DATETIME DEFAULT NULL AFTER `dispensed_by`");
				}
				if (!$this->column_exists('billing_transactions', 'completed_by')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `completed_by` VARCHAR(25) DEFAULT NULL AFTER `dispensed_at`");
				}
				if (!$this->column_exists('billing_transactions', 'completed_at')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `completed_at` DATETIME DEFAULT NULL AFTER `completed_by`");
				}
				if (!$this->column_exists('billing_transactions', 'cancelled_by')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `cancelled_by` VARCHAR(25) DEFAULT NULL AFTER `completed_at`");
				}
				if (!$this->column_exists('billing_transactions', 'cancelled_at')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL AFTER `cancelled_by`");
				}
				if (!$this->column_exists('billing_transactions', 'cancel_reason')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `cancel_reason` VARCHAR(255) DEFAULT NULL AFTER `cancelled_at`");
				}
				if (!$this->column_exists('billing_transactions', 'updated_by')) {
					$this->db->query("ALTER TABLE `billing_transactions` ADD COLUMN `updated_by` VARCHAR(25) DEFAULT NULL AFTER `updated_at`");
				}

				// 3) Add missing indexes (best-effort; ignore if they already exist)
				$this->db->query("ALTER TABLE `billing_transactions` ADD INDEX `idx_item_ref` (`item_ref`)");
				if ($this->column_exists('billing_transactions', 'department') && $this->column_exists('billing_transactions', 'item_ref')) {
					try {
						$this->db->query("ALTER TABLE `billing_transactions` ADD INDEX `idx_item_ref_department` (`item_ref`, `department`)");
					} catch (Throwable $e) {
					}
				}
				if ($this->column_exists('billing_transactions', 'billing_subject_type') && $this->column_exists('billing_transactions', 'billing_subject_id')) {
					try {
						$this->db->query("ALTER TABLE `billing_transactions` ADD INDEX `idx_subject` (`billing_subject_type`, `billing_subject_id`)");
					} catch (Throwable $e) {
					}
				}
				$this->db->query("ALTER TABLE `billing_transactions` ADD INDEX `idx_invoice` (`invoice_no`)");
				$this->db->query("ALTER TABLE `billing_transactions` ADD INDEX `idx_order_status` (`order_status`)");
			}
		} catch (Throwable $e) {
			// Never break app flow on schema upgrade.
		}
		if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }

        // Patient Financial Ledger - Running balance per patient
        $this->db->query("CREATE TABLE IF NOT EXISTS `patient_financial_ledger` (
            `ledger_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `patient_no` varchar(25) DEFAULT NULL,
            `txn_id` bigint(20) DEFAULT NULL COMMENT 'FK to billing_transactions',
            `reference_type` varchar(30) NOT NULL COMMENT 'CHARGE, PAYMENT, REFUND, ADJUSTMENT, WAIVER',
            `reference_no` varchar(50) DEFAULT NULL,
            `description` varchar(255) DEFAULT NULL,
            `debit_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `credit_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
            `running_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
            `created_by` varchar(25) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`ledger_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_txn` (`txn_id`),
            KEY `idx_ref` (`reference_type`, `reference_no`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Walk-in subject support: allow NULL patient_no in patient_financial_ledger
		if ($this->table_exists('patient_financial_ledger') && $this->column_exists('patient_financial_ledger', 'patient_no')) {
			$prev_debug2 = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($prev_debug2 !== null) { $this->db->db_debug = false; }
			try {
				$this->db->query("ALTER TABLE `patient_financial_ledger` MODIFY COLUMN `patient_no` VARCHAR(25) DEFAULT NULL");
			} catch (Throwable $e) {
			}
			if ($prev_debug2 !== null) { $this->db->db_debug = $prev_debug2; }
		}

        // System Audit Log - All status changes
        $this->db->query("CREATE TABLE IF NOT EXISTS `billing_audit_log` (
            `audit_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `txn_id` bigint(20) DEFAULT NULL,
            `table_name` varchar(50) NOT NULL,
            `record_id` varchar(50) NOT NULL,
            `action` varchar(30) NOT NULL COMMENT 'CREATE, UPDATE, DELETE, STATUS_CHANGE, PAYMENT, REFUND',
            `field_name` varchar(50) DEFAULT NULL,
            `old_value` text,
            `new_value` text,
            `patient_no` varchar(25) DEFAULT NULL,
            `encounter_id` varchar(25) DEFAULT NULL,
            `performed_by` varchar(25) DEFAULT NULL,
            `performed_at` datetime NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            PRIMARY KEY (`audit_id`),
            KEY `idx_txn` (`txn_id`),
            KEY `idx_table_record` (`table_name`, `record_id`),
            KEY `idx_action` (`action`),
            KEY `idx_performed` (`performed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Reconciliation Log - Track mismatches
        $this->db->query("CREATE TABLE IF NOT EXISTS `billing_reconciliation_log` (
            `recon_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `recon_date` date NOT NULL,
            `department` varchar(20) NOT NULL,
            `issue_type` varchar(50) NOT NULL COMMENT 'DISPENSED_NOT_BILLED, BILLED_NOT_DISPENSED, PAID_NOT_COMPLETED, etc',
            `record_ref` varchar(100) NOT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `encounter_id` varchar(25) DEFAULT NULL,
            `details` text,
            `resolved` tinyint(1) NOT NULL DEFAULT 0,
            `resolved_by` varchar(25) DEFAULT NULL,
            `resolved_at` datetime DEFAULT NULL,
            `resolution_notes` text,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`recon_id`),
            KEY `idx_date` (`recon_date`),
            KEY `idx_department` (`department`),
            KEY `idx_issue` (`issue_type`),
            KEY `idx_resolved` (`resolved`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return true;
    }

    // ==================== TRANSACTION MANAGEMENT ====================

    /**
     * Generate unique transaction reference
     */
    public function generate_txn_ref($department = 'GEN'){
        $prefix = strtoupper(substr($department, 0, 3));
        $date = date('ymd');
        $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
        return $prefix . $date . $random;
    }

    /**
     * Create a new billing transaction (with DB transaction support)
     */
    public function create_transaction($data, $user_id = null){
        $this->ensure_billing_transaction_schema();
        
        $now = date('Y-m-d H:i:s');
        $txn_ref = $this->generate_txn_ref(isset($data['department']) ? $data['department'] : 'GEN');
        
        // Calculate amounts
        $quantity = isset($data['quantity']) ? (float)$data['quantity'] : 1;
        $unit_price = isset($data['unit_price']) ? (float)$data['unit_price'] : 0;
        $department = isset($data['department']) ? strtoupper(trim((string)$data['department'])) : '';
        if ($department === self::DEPT_PHARMACY && $unit_price <= 0.009 && empty($data['allow_zero_price'])) {
            return array('ok' => false, 'error' => 'Pharmacy pricing resolution failed. Workflow blocked to prevent zero-value billing.');
        }
        $gross = $quantity * $unit_price;
        $discount = isset($data['discount_amount']) ? (float)$data['discount_amount'] : 0;
        $tax = isset($data['tax_amount']) ? (float)$data['tax_amount'] : 0;
        $net = $gross - $discount + $tax;
        
        // Determine initial payment status based on payer type
        $payer_type = isset($data['payer_type']) ? strtoupper(trim($data['payer_type'])) : 'CASH';
        $payment_status = self::PAY_PENDING;
        if ($payer_type === 'NHIS' || strpos($payer_type, 'NHIS') !== false) {
            $payment_status = self::PAY_NHIS;
            $payer_type = 'NHIS';
        }
        
        $insert = array(
            'txn_ref'         => $txn_ref,
            'patient_no'      => isset($data['patient_no']) ? (string)$data['patient_no'] : '',
            'encounter_id'    => isset($data['encounter_id']) ? (string)$data['encounter_id'] : '',
            'encounter_type'  => isset($data['encounter_type']) ? strtoupper($data['encounter_type']) : 'OPD',
            'department'      => $department,
            'item_type'       => isset($data['item_type']) ? strtoupper($data['item_type']) : '',
            'item_id'         => isset($data['item_id']) ? (int)$data['item_id'] : null,
            'item_ref'        => isset($data['item_ref']) ? (string)$data['item_ref'] : null,
            'item_name'       => isset($data['item_name']) ? (string)$data['item_name'] : '',
            'item_code'       => isset($data['item_code']) ? (string)$data['item_code'] : null,
            'quantity'        => $quantity,
            'unit_price'      => $unit_price,
            'gross_amount'    => $gross,
            'discount_amount' => $discount,
            'discount_reason' => isset($data['discount_reason']) ? (string)$data['discount_reason'] : null,
            'tax_amount'      => $tax,
            'net_amount'      => $net,
            'paid_amount'     => 0,
            'balance_amount'  => $net,
            'payer_type'      => $payer_type,
            'payment_status'  => $payment_status,
            'order_status'    => self::STATUS_ORDERED,
            'invoice_no'      => isset($data['invoice_no']) && trim((string)$data['invoice_no']) !== '' ? (string)$data['invoice_no'] : null,
            'notes'           => isset($data['notes']) ? (string)$data['notes'] : null,
            'created_by'      => $user_id,
            'created_at'      => $now,
            'updated_at'      => $now,
            'updated_by'      => $user_id,
            'InActive'        => 0
        );
        if ($this->column_exists('billing_transactions', 'billing_subject_type') && $this->column_exists('billing_transactions', 'billing_subject_id')) {
            $insert['billing_subject_type'] = isset($data['billing_subject_type']) && trim((string)$data['billing_subject_type']) !== '' ? strtoupper(trim((string)$data['billing_subject_type'])) : null;
            $insert['billing_subject_id'] = isset($data['billing_subject_id']) && trim((string)$data['billing_subject_id']) !== '' ? trim((string)$data['billing_subject_id']) : null;
        }
        if ($this->column_exists('billing_transactions', 'detail_id') && isset($data['detail_id']) && (int)$data['detail_id'] > 0) {
            $insert['detail_id'] = (int)$data['detail_id'];
        }
        if ($this->column_exists('billing_transactions', 'transaction_type') && !isset($insert['transaction_type'])) {
            $insert['transaction_type'] = isset($data['transaction_type']) && trim((string)$data['transaction_type']) !== '' ? strtoupper(trim((string)$data['transaction_type'])) : 'CHARGE';
        }
        foreach (array('price_source','pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag','pricing_pct','original_unit_price') as $_prov_col) {
            if ($this->column_exists('billing_transactions', $_prov_col) && array_key_exists($_prov_col, $data)) {
                $insert[$_prov_col] = $data[$_prov_col];
            }
        }
        
        // Start transaction
        $this->db->trans_start();
        
        $insertOk = $this->db->insert('billing_transactions', $insert);
        $txn_id = (int)$this->db->insert_id();
        if (!$insertOk || $txn_id <= 0) {
            $err = $this->db->error();
            $msg = (is_array($err) && isset($err['message']) && trim((string)$err['message']) !== '') ? trim((string)$err['message']) : 'Insert failed';
            $this->db->trans_complete();
            return array('ok' => false, 'error' => $msg);
        }
        
        // Create ledger entry (debit = charge to patient)
        $this->create_ledger_entry(array(
            'patient_no'     => $insert['patient_no'],
            'txn_id'         => $txn_id,
            'reference_type' => 'CHARGE',
            'reference_no'   => $txn_ref,
            'description'    => $insert['department'] . ': ' . $insert['item_name'],
            'debit_amount'   => $net,
            'credit_amount'  => 0
        ), $user_id);
        
        // Log audit
        $this->log_audit(array(
            'txn_id'       => $txn_id,
            'table_name'   => 'billing_transactions',
            'record_id'    => $txn_id,
            'action'       => 'CREATE',
            'new_value'    => json_encode($insert),
            'patient_no'   => $insert['patient_no'],
            'encounter_id' => $insert['encounter_id']
        ), $user_id);
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            $err = $this->db->error();
            $msg = (is_array($err) && isset($err['message']) && trim((string)$err['message']) !== '') ? trim((string)$err['message']) : 'Transaction failed';
            return array('ok' => false, 'error' => $msg);
        }
        
        return array('ok' => true, 'txn_id' => $txn_id, 'txn_ref' => $txn_ref);
    }

	public function reprice_pending_transaction_if_zero($txn_id, $user_id = null)
	{
		$this->ensure_billing_transaction_schema();
		$txn_id = (int)$txn_id;
		if ($txn_id <= 0) {
			return array('ok' => false, 'error' => 'Invalid transaction');
		}
		$txn = $this->db->get_where('billing_transactions', array('txn_id' => $txn_id, 'InActive' => 0))->row();
		if (!$txn) {
			return array('ok' => false, 'error' => 'Transaction not found');
		}
		$invoice_no = isset($txn->invoice_no) ? trim((string)$txn->invoice_no) : '';
		if ($invoice_no !== '') {
			return array('ok' => false, 'error' => 'Already invoiced');
		}
		$paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
		if ($paid > 0.009) {
			return array('ok' => false, 'error' => 'Already paid');
		}
		$qty = isset($txn->quantity) ? (float)$txn->quantity : 1.0;
		if ($qty <= 0) { $qty = 1.0; }
		$old_unit = isset($txn->unit_price) ? (float)$txn->unit_price : 0.0;
		if ($old_unit > 0.009) {
			return array('ok' => true, 'txn_id' => $txn_id, 'unit_price' => $old_unit, 'already_priced' => true);
		}
		$item_id = isset($txn->item_id) ? (int)$txn->item_id : 0;
		if ($item_id <= 0) {
			return array('ok' => false, 'error' => 'Missing item_id');
		}
		$patient_no = isset($txn->patient_no) ? (string)$txn->patient_no : '';
		if ($patient_no === '') {
			return array('ok' => false, 'error' => 'Missing patient_no');
		}
		$dept = isset($txn->department) ? strtoupper(trim((string)$txn->department)) : '';
		$item_type = 'SERVICE';
		if ($dept === self::DEPT_LAB) {
			$item_type = 'LAB';
		} elseif ($dept === self::DEPT_PHARMACY) {
			$item_type = 'PHARMACY';
		} elseif ($dept === self::DEPT_IMAGING) {
			$item_type = 'SONOGRAPHY';
		}
		$payer_type = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : '';
		if ($payer_type === '') { $payer_type = 'CASH'; }

		$this->load->model('app/Price_engine_model', 'price_engine');
		$pr = $this->price_engine->resolve(array(
			'item_type' => $item_type,
			'item_id' => $item_id,
			'quantity' => $qty,
			'patient_no' => $patient_no,
			'payer_type' => $payer_type,
			'submitted_unit_price' => $old_unit,
			'require_positive_price' => ($dept === self::DEPT_PHARMACY),
		));
		if (!is_array($pr) || empty($pr['ok'])) {
			return array('ok' => false, 'error' => 'Price resolution failed');
		}
		$new_unit = isset($pr['unit_price']) ? (float)$pr['unit_price'] : 0.0;
		if ($new_unit <= 0.009) {
			return array('ok' => false, 'error' => 'Resolved price is zero');
		}
		$new_gross = round($qty * $new_unit, 2);
		$disc = isset($txn->discount_amount) ? (float)$txn->discount_amount : 0.0;
		$tax = isset($txn->tax_amount) ? (float)$txn->tax_amount : 0.0;
		$new_net = round($new_gross - $disc + $tax, 2);
		$old_net = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
		$delta = round($new_net - $old_net, 2);
		$now = date('Y-m-d H:i:s');
		$upd = array(
			'unit_price' => round($new_unit, 2),
			'gross_amount' => $new_gross,
			'net_amount' => $new_net,
			'balance_amount' => $new_net,
			'updated_at' => $now,
			'updated_by' => $user_id,
		);
		if ($this->column_exists('billing_transactions', 'total_amount')) {
			$upd['total_amount'] = $new_net;
		}
		if ($this->column_exists('billing_transactions', 'price_source') && isset($pr['price_source'])) {
			$upd['price_source'] = (string)$pr['price_source'];
		}
		if ($this->column_exists('billing_transactions', 'pricing_pct') && isset($pr['pricing_pct'])) {
			$upd['pricing_pct'] = (float)$pr['pricing_pct'];
		}
		if ($this->column_exists('billing_transactions', 'original_unit_price') && isset($pr['original_unit_price'])) {
			$upd['original_unit_price'] = (float)$pr['original_unit_price'];
		}

		$this->db->trans_start();
		$this->db->where(array('txn_id' => $txn_id, 'InActive' => 0));
		$this->db->update('billing_transactions', $upd);
		if (abs($delta) > 0.009) {
			$this->create_ledger_entry(array(
				'patient_no' => $patient_no,
				'txn_id' => $txn_id,
				'reference_type' => 'ADJUSTMENT',
				'reference_no' => 'REPRICE:' . $txn_id,
				'description' => 'Reprice correction: ' . $dept . ': ' . (isset($txn->item_name) ? (string)$txn->item_name : ''),
				'debit_amount' => ($delta > 0) ? $delta : 0.0,
				'credit_amount' => ($delta < 0) ? abs($delta) : 0.0,
			), $user_id);
		}
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			return array('ok' => false, 'error' => 'Update failed');
		}
		return array(
			'ok' => true,
			'txn_id' => $txn_id,
			'unit_price' => round($new_unit, 2),
			'net_amount' => $new_net,
			'delta' => $delta,
			'item_name' => isset($pr['item_name']) ? (string)$pr['item_name'] : (isset($txn->item_name) ? (string)$txn->item_name : ''),
			'payer_type' => isset($pr['payer_type']) ? (string)$pr['payer_type'] : $payer_type,
		);
	}

    private function _determine_payer_type($patient_no){
        $patient_no = (string)$patient_no;
        if ($patient_no === '') {
            return 'CASH';
        }
        $payer_type = null;
        if (method_exists($this, 'table_exists') && $this->table_exists('patient_personal_info')) {
            $patient = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no))->row();
            if ($patient && isset($patient->Insurance_comp)) {
                $ins = strtoupper(trim((string)$patient->Insurance_comp));
                if (strpos($ins, 'NHIS') !== false) {
                    $payer_type = 'NHIS';
                } elseif ($ins !== '' && $ins !== 'NONE') {
                    $payer_type = 'INSURANCE';
                }
            }
        }
        if ($payer_type === null) {
            $this->load->model('app/billing_model');
            if (isset($this->billing_model) && method_exists($this->billing_model, 'determine_payer_type')) {
                $payer_type = strtoupper(trim((string)$this->billing_model->determine_payer_type($patient_no)));
            }
        }
        if ($payer_type !== 'NHIS' && $payer_type !== 'INSURANCE') {
            $payer_type = 'CASH';
        }
        return $payer_type;
    }

    private function _get_invoice_no_from_lock($source_module, $source_ref){
        $source_module = strtoupper(trim((string)$source_module));
        $source_ref = trim((string)$source_ref);
        if ($source_module === '' || $source_ref === '' || !$this->table_exists('iop_billable_item_lock')) {
            return null;
        }
        $this->db->select('invoice_no');
        $this->db->from('iop_billable_item_lock');
        $this->db->where(array('source_module' => $source_module, 'source_ref' => $source_ref, 'InActive' => 0));
        $this->db->order_by('lock_id', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get()->row();
        if ($row && isset($row->invoice_no) && trim((string)$row->invoice_no) !== '') {
            return (string)$row->invoice_no;
        }

		// Fallback: support legacy/prefixed lock refs, e.g. iop_id:OP00001:iop_medication:123
		$this->db->select('invoice_no');
		$this->db->from('iop_billable_item_lock');
		$this->db->where(array('source_module' => $source_module, 'InActive' => 0));
		$this->db->like('source_ref', $source_ref, 'before');
		$this->db->order_by('lock_id', 'DESC');
		$this->db->limit(1);
		$row2 = $this->db->get()->row();
		if ($row2 && isset($row2->invoice_no) && trim((string)$row2->invoice_no) !== '') {
			return (string)$row2->invoice_no;
		}
        return null;
    }

    private function _get_invoice_line_amounts($invoice_no, $detail_id){
        $invoice_no = trim((string)$invoice_no);
        $detail_id = (int)$detail_id;
        if ($invoice_no === '' || $detail_id <= 0 || !$this->table_exists('iop_billing_t')) {
            return null;
        }
        $this->db->select('qty, rate, amount, bill_name');
        $this->db->from('iop_billing_t');
        $this->db->where(array('invoice_no' => $invoice_no, 'id' => $detail_id, 'InActive' => 0));
        $this->db->limit(1);
        $row = $this->db->get()->row();
        if (!$row) return null;
        return array(
            'qty' => isset($row->qty) ? (float)$row->qty : 1,
            'rate' => isset($row->rate) ? (float)$row->rate : 0,
            'amount' => isset($row->amount) ? (float)$row->amount : 0,
            'bill_name' => isset($row->bill_name) ? (string)$row->bill_name : null
        );
    }

	public function table_exists($table_name){
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table_name, $column_name){
		if (!$this->table_exists($table_name)) return false;
		$q = $this->db->query("SHOW COLUMNS FROM `" . $table_name . "` LIKE " . $this->db->escape((string)$column_name));
		return ($q && $q->num_rows() > 0);
	}

    /**
     * Get transaction by ID
     */
    public function get_transaction($txn_id){
        $this->ensure_billing_transaction_schema();
        return $this->db->get_where('billing_transactions', array('txn_id' => (int)$txn_id, 'InActive' => 0))->row();
    }

    /**
     * Get transaction by item reference (e.g., iop_med_id)
     */
    public function get_transaction_by_item_ref($item_ref, $department = null){
        $this->ensure_billing_transaction_schema();
        $this->db->where(array('item_ref' => (string)$item_ref, 'InActive' => 0));
        if ($department) {
            $this->db->where('department', strtoupper($department));
        }
        return $this->db->get('billing_transactions')->row();
    }

    /**
     * Get all transactions for an encounter
     */
    public function get_encounter_transactions($encounter_id, $department = null){
        $this->ensure_billing_transaction_schema();
        $this->db->where(array('encounter_id' => (string)$encounter_id, 'InActive' => 0));
        if ($department) {
            $this->db->where('department', strtoupper($department));
        }
        $this->db->order_by('created_at', 'ASC');
        return $this->db->get('billing_transactions')->result();
    }

    // ==================== STATUS MANAGEMENT ====================

    /**
     * Update order status with validation
     */
    public function update_order_status($txn_id, $new_status, $user_id = null, $notes = null){
        $this->ensure_billing_transaction_schema();
        
        $txn = $this->get_transaction($txn_id);
        if (!$txn) {
            return array('ok' => false, 'error' => 'Transaction not found');
        }
        
        $old_status = $txn->order_status;
        $new_status = strtoupper(trim($new_status));
        
        // Validate status transition
        $valid_transitions = array(
            self::STATUS_ORDERED   => array(self::STATUS_APPROVED, self::STATUS_CANCELLED),
            self::STATUS_APPROVED  => array(self::STATUS_DISPENSED, self::STATUS_CANCELLED),
            self::STATUS_DISPENSED => array(self::STATUS_COMPLETED),
            self::STATUS_COMPLETED => array(), // Terminal state
            self::STATUS_CANCELLED => array()  // Terminal state
        );
        
        if (!isset($valid_transitions[$old_status]) || !in_array($new_status, $valid_transitions[$old_status])) {
            return array('ok' => false, 'error' => "Invalid status transition: $old_status → $new_status");
        }
        
        $now = date('Y-m-d H:i:s');
        $update = array(
            'order_status' => $new_status,
            'updated_at'   => $now,
            'updated_by'   => $user_id
        );
        
        // Set timestamp fields based on status
        switch ($new_status) {
            case self::STATUS_APPROVED:
                $update['approved_by'] = $user_id;
                $update['approved_at'] = $now;
                break;
            case self::STATUS_DISPENSED:
                $update['dispensed_by'] = $user_id;
                $update['dispensed_at'] = $now;
                break;
            case self::STATUS_COMPLETED:
                $update['completed_by'] = $user_id;
                $update['completed_at'] = $now;
                break;
            case self::STATUS_CANCELLED:
                $update['cancelled_by'] = $user_id;
                $update['cancelled_at'] = $now;
                $update['cancel_reason'] = $notes;
                break;
        }
        
        $this->db->trans_start();
        
        $this->db->where('txn_id', (int)$txn_id);
        $this->db->update('billing_transactions', $update);
        
        // If cancelled, reverse the ledger entry
        if ($new_status === self::STATUS_CANCELLED && $txn->payment_status === self::PAY_PENDING) {
            $this->create_ledger_entry(array(
                'patient_no'     => $txn->patient_no,
                'txn_id'         => $txn_id,
                'reference_type' => 'REVERSAL',
                'reference_no'   => $txn->txn_ref,
                'description'    => 'Cancelled: ' . $txn->item_name,
                'debit_amount'   => 0,
                'credit_amount'  => $txn->net_amount
            ), $user_id);
            
            // Also update payment status
            $this->db->where('txn_id', (int)$txn_id);
            $this->db->update('billing_transactions', array('payment_status' => self::PAY_CANCELLED));
        }
        
        // Log audit
        $this->log_audit(array(
            'txn_id'       => $txn_id,
            'table_name'   => 'billing_transactions',
            'record_id'    => $txn_id,
            'action'       => 'STATUS_CHANGE',
            'field_name'   => 'order_status',
            'old_value'    => $old_status,
            'new_value'    => $new_status,
            'patient_no'   => $txn->patient_no,
            'encounter_id' => $txn->encounter_id
        ), $user_id);
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return array('ok' => false, 'error' => 'Transaction failed');
        }
        
        return array('ok' => true, 'old_status' => $old_status, 'new_status' => $new_status);
    }

    /**
     * Record payment against a transaction
     */
    public function record_payment($txn_id, $amount, $receipt_no = null, $user_id = null){
        $this->ensure_billing_transaction_schema();
        
        $txn = $this->get_transaction($txn_id);
        if (!$txn) {
            return array('ok' => false, 'error' => 'Transaction not found');
        }
        
        $amount = (float)$amount;
        if ($amount <= 0) {
            return array('ok' => false, 'error' => 'Invalid payment amount');
        }
        
        $old_paid = (float)$txn->paid_amount;
        $new_paid = $old_paid + $amount;
        $net = (float)$txn->net_amount;
        $new_balance = $net - $new_paid;
        
        // Determine new payment status
        $old_status = $txn->payment_status;
        if ($new_paid >= $net) {
            $new_status = self::PAY_PAID;
            $new_balance = 0;
        } else {
            $new_status = self::PAY_PARTIAL;
        }
        
        $now = date('Y-m-d H:i:s');
        
        $this->db->trans_start();
        
        $this->db->where('txn_id', (int)$txn_id);
        $this->db->update('billing_transactions', array(
            'paid_amount'    => $new_paid,
            'balance_amount' => $new_balance,
            'payment_status' => $new_status,
            'receipt_no'     => $receipt_no,
            'updated_at'     => $now,
            'updated_by'     => $user_id
        ));
        
        // Create ledger entry (credit = payment from patient)
        $this->create_ledger_entry(array(
            'patient_no'     => $txn->patient_no,
            'txn_id'         => $txn_id,
            'reference_type' => 'PAYMENT',
            'reference_no'   => $receipt_no,
            'description'    => 'Payment for: ' . $txn->item_name,
            'debit_amount'   => 0,
            'credit_amount'  => $amount
        ), $user_id);
        
        // Log audit
        $this->log_audit(array(
            'txn_id'       => $txn_id,
            'table_name'   => 'billing_transactions',
            'record_id'    => $txn_id,
            'action'       => 'PAYMENT',
            'field_name'   => 'paid_amount',
            'old_value'    => $old_paid,
            'new_value'    => $new_paid,
            'patient_no'   => $txn->patient_no,
            'encounter_id' => $txn->encounter_id
        ), $user_id);
        
        // If fully paid and dispensed, auto-complete
        if ($new_status === self::PAY_PAID && $txn->order_status === self::STATUS_DISPENSED) {
            $this->update_order_status($txn_id, self::STATUS_COMPLETED, $user_id);
        }
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return array('ok' => false, 'error' => 'Transaction failed');
        }
        
        return array('ok' => true, 'old_status' => $old_status, 'new_status' => $new_status, 'balance' => $new_balance);
    }

    /**
     * Process refund for a transaction
     */
    public function process_refund($txn_id, $amount, $reason = null, $user_id = null){
        $this->ensure_billing_transaction_schema();
        
        $txn = $this->get_transaction($txn_id);
        if (!$txn) {
            return array('ok' => false, 'error' => 'Transaction not found');
        }
        
        $amount = (float)$amount;
        $paid = (float)$txn->paid_amount;
        
        if ($amount <= 0 || $amount > $paid) {
            return array('ok' => false, 'error' => 'Invalid refund amount');
        }
        
        $new_paid = $paid - $amount;
        $net = (float)$txn->net_amount;
        $new_balance = $net - $new_paid;
        
        // Determine new payment status
        $new_status = self::PAY_REFUNDED;
        if ($new_paid > 0 && $new_paid < $net) {
            $new_status = self::PAY_PARTIAL;
        } elseif ($new_paid <= 0) {
            $new_status = self::PAY_REFUNDED;
        }
        
        $now = date('Y-m-d H:i:s');
        
        $this->db->trans_start();
        
        $this->db->where('txn_id', (int)$txn_id);
        $this->db->update('billing_transactions', array(
            'paid_amount'    => $new_paid,
            'balance_amount' => $new_balance,
            'payment_status' => $new_status,
            'updated_at'     => $now,
            'updated_by'     => $user_id
        ));
        
        // Create ledger entry (debit = refund to patient reduces their credit)
        $this->create_ledger_entry(array(
            'patient_no'     => $txn->patient_no,
            'txn_id'         => $txn_id,
            'reference_type' => 'REFUND',
            'reference_no'   => 'REF-' . $txn->txn_ref,
            'description'    => 'Refund: ' . ($reason ? $reason : $txn->item_name),
            'debit_amount'   => $amount,
            'credit_amount'  => 0
        ), $user_id);
        
        // Log audit
        $this->log_audit(array(
            'txn_id'       => $txn_id,
            'table_name'   => 'billing_transactions',
            'record_id'    => $txn_id,
            'action'       => 'REFUND',
            'old_value'    => $paid,
            'new_value'    => $new_paid,
            'patient_no'   => $txn->patient_no,
            'encounter_id' => $txn->encounter_id
        ), $user_id);
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return array('ok' => false, 'error' => 'Transaction failed');
        }
        
        return array('ok' => true, 'refund_amount' => $amount, 'new_balance' => $new_balance);
    }

	// ==================== SYNC WITH LEGACY TABLES ====================

	public function sync_pharmacy_medication($iop_med_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0 || !$this->table_exists('iop_medication')) {
			return array('ok' => false, 'error' => 'Medication not found');
		}

		$item_ref = 'iop_med_id:' . $iop_med_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_PHARMACY);
		if ($existing) {
			$upd = array();
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$upd['invoice_no'] = (string)$invoice_no;
			}
			if ($detail_id !== null && (int)$detail_id > 0 && $this->column_exists('billing_transactions', 'detail_id')) {
				$exDid = isset($existing->detail_id) ? (int)$existing->detail_id : 0;
				if ($exDid <= 0) {
					$upd['detail_id'] = (int)$detail_id;
				}
			}
			if (!empty($upd)) {
				$upd['updated_at'] = date('Y-m-d H:i:s');
				$upd['updated_by'] = $user_id;
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', $upd);
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) {
			return array('ok' => false, 'error' => 'Medication not found');
		}
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription not VERIFIED');
			}
		}

		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('PHARMACY', 'iop_medication:' . $iop_med_id);
			if ($invoice_no === null || trim((string)$invoice_no) === '') {
				$invoice_no = $this->_get_invoice_no_from_lock('PHARMACY', 'iop_med_id:' . $iop_med_id);
			}
		}

		$qty = isset($med->total_qty) ? (float)$med->total_qty : 1.0;
		$rate = 0.0;
		$item_name = null;
		$patient_no = '';
		$visit_type = 'OPD';
		$iop_id = isset($med->iop_id) ? (string)$med->iop_id : '';
		if ($iop_id === '' && isset($med->IO_ID)) {
			$iop_id = (string)$med->IO_ID;
		}
		if ($this->table_exists('patient_details_iop') && $iop_id !== '') {
			$visit = $this->db->select('patient_no, patient_type')->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
			if ($visit) {
				if (isset($visit->patient_no)) $patient_no = (string)$visit->patient_no;
				if (isset($visit->patient_type) && trim((string)$visit->patient_type) !== '') $visit_type = (string)$visit->patient_type;
			}
		}
		if ($patient_no === '' && isset($med->patient_no)) {
			$patient_no = (string)$med->patient_no;
		}

		$drug_id = isset($med->medicine_id) ? (int)$med->medicine_id : 0;
		$drug = null;
		if ($drug_id > 0 && $this->table_exists('medicine_drug_name')) {
			$drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
		}
		if ($drug && isset($drug->drug_name) && trim((string)$drug->drug_name) !== '') {
			$item_name = (string)$drug->drug_name;
		}
		if (($item_name === null || $item_name === '') && isset($med->medicine_text) && trim((string)$med->medicine_text) !== '') {
			$item_name = (string)$med->medicine_text;
		}
		if ($item_name === null || $item_name === '') {
			$item_name = 'Medication';
		}

		$payer_type = $this->_determine_payer_type($patient_no);
		if ($drug) {
			if (strtoupper($payer_type) === 'NHIS' && isset($drug->nhis_price) && (float)$drug->nhis_price > 0) {
				$rate = (float)$drug->nhis_price;
			} elseif (isset($drug->cash_price) && (float)$drug->cash_price > 0) {
				$rate = (float)$drug->cash_price;
			} elseif (isset($drug->nPrice) && (float)$drug->nPrice > 0) {
				$rate = (float)$drug->nPrice;
			}
		}
		$rate = round((float)$rate, 2);
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) {
				$qty = $line['qty'];
				$rate = $line['rate'];
				if ($line['bill_name'] !== null && $line['bill_name'] !== '') {
					$item_name = $line['bill_name'];
				}
			}
		}

		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => $visit_type,
			'department' => self::DEPT_PHARMACY,
			'item_type' => self::ITEM_DRUG,
			'item_id' => $drug_id > 0 ? $drug_id : null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no,
			'detail_id' => $detail_id !== null ? (int)$detail_id : null
		), $user_id);
	}

	public function sync_pending_pharmacy_transaction_from_rx($iop_med_id, $user_id = null){
		$this->ensure_billing_transaction_schema();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0 || !$this->table_exists('iop_medication')) {
			return array('ok' => false, 'error' => 'Medication not found');
		}

		$item_ref = 'iop_med_id:' . $iop_med_id;
		$txn = $this->get_transaction_by_item_ref($item_ref, self::DEPT_PHARMACY);
		if (!$txn) {
			return array('ok' => true, 'skipped' => true, 'reason' => 'no_txn');
		}
		$invoice_no = isset($txn->invoice_no) ? trim((string)$txn->invoice_no) : '';
		if ($invoice_no !== '') {
			return array('ok' => false, 'error' => 'Already invoiced');
		}
		$paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
		if ($paid > 0.009) {
			return array('ok' => false, 'error' => 'Already paid');
		}
		$payStatus = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : self::PAY_PENDING;
		if ($payStatus !== '' && $payStatus !== self::PAY_PENDING) {
			return array('ok' => false, 'error' => 'Payment status is ' . $payStatus);
		}

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) {
			return array('ok' => false, 'error' => 'Medication not found');
		}
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => true, 'skipped' => true, 'reason' => 'rx_not_verified');
			}
		}

		$qty = isset($med->total_qty) ? (float)$med->total_qty : 1.0;
		if ($qty <= 0) { $qty = 1.0; }
		$drug_id = isset($med->medicine_id) ? (int)$med->medicine_id : 0;
		$item_name = isset($med->medicine_text) ? trim((string)$med->medicine_text) : '';
		if ($item_name === '') {
			try {
				if ($drug_id > 0 && $this->table_exists('medicine_drug_name')) {
					$d = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
					if ($d && isset($d->drug_name) && trim((string)$d->drug_name) !== '') {
						$item_name = trim((string)$d->drug_name);
					}
				}
			} catch (Throwable $e) {
			}
		}
		if ($item_name === '') { $item_name = 'Medication'; }

		$patient_no = isset($txn->patient_no) ? trim((string)$txn->patient_no) : '';
		if ($patient_no === '') {
			$iop_id = isset($med->iop_id) ? (string)$med->iop_id : '';
			if ($this->table_exists('patient_details_iop') && $iop_id !== '') {
				$visit = $this->db->select('patient_no')->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
				if ($visit && isset($visit->patient_no)) { $patient_no = (string)$visit->patient_no; }
			}
			if ($patient_no === '' && isset($med->patient_no)) {
				$patient_no = (string)$med->patient_no;
			}
		}
		if ($patient_no === '') {
			return array('ok' => false, 'error' => 'Missing patient_no');
		}

		$payer_type = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : '';
		if ($payer_type === '') { $payer_type = $this->_determine_payer_type($patient_no); }
		$old_unit = isset($txn->unit_price) ? (float)$txn->unit_price : 0.0;
		$disc = isset($txn->discount_amount) ? (float)$txn->discount_amount : 0.0;
		$tax = isset($txn->tax_amount) ? (float)$txn->tax_amount : 0.0;
		$old_net = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;

		$this->load->model('app/Price_engine_model', 'price_engine');
		$pr = $this->price_engine->resolve(array(
			'item_type' => 'PHARMACY',
			'item_id' => $drug_id,
			'quantity' => $qty,
			'patient_no' => $patient_no,
			'payer_type' => $payer_type,
			'submitted_unit_price' => $old_unit,
			'require_positive_price' => true,
		));
		if (!is_array($pr) || empty($pr['ok'])) {
			return array('ok' => false, 'error' => 'Price resolution failed');
		}
		$new_unit = isset($pr['unit_price']) ? (float)$pr['unit_price'] : 0.0;
		if ($new_unit <= 0.009) {
			return array('ok' => false, 'error' => 'Resolved price is zero');
		}
		$new_gross = round($qty * $new_unit, 2);
		$new_net = round($new_gross - $disc + $tax, 2);
		$delta = round($new_net - $old_net, 2);
		$now = date('Y-m-d H:i:s');
		$resolvedPayer = isset($pr['payer_type']) && trim((string)$pr['payer_type']) !== '' ? strtoupper(trim((string)$pr['payer_type'])) : $payer_type;
		$resolvedName = isset($pr['item_name']) && trim((string)$pr['item_name']) !== '' ? (string)$pr['item_name'] : $item_name;

		$upd = array(
			'item_id' => ($drug_id > 0) ? $drug_id : null,
			'item_name' => $resolvedName,
			'quantity' => $qty,
			'unit_price' => round($new_unit, 2),
			'gross_amount' => $new_gross,
			'net_amount' => $new_net,
			'balance_amount' => $new_net,
			'payer_type' => $resolvedPayer,
			'updated_at' => $now,
			'updated_by' => $user_id,
		);
		if ($this->column_exists('billing_transactions', 'total_amount')) {
			$upd['total_amount'] = $new_net;
		}
		if ($this->column_exists('billing_transactions', 'price_source') && isset($pr['price_source'])) {
			$upd['price_source'] = (string)$pr['price_source'];
		}
		if ($this->column_exists('billing_transactions', 'pricing_pct') && isset($pr['pricing_pct'])) {
			$upd['pricing_pct'] = (float)$pr['pricing_pct'];
		}
		if ($this->column_exists('billing_transactions', 'original_unit_price') && isset($pr['original_unit_price'])) {
			$upd['original_unit_price'] = (float)$pr['original_unit_price'];
		}
		foreach (array(
			'pricing_source_id' => isset($pr['pricing_source_id']) ? (string)$pr['pricing_source_id'] : (isset($pr['source_id']) ? (string)$pr['source_id'] : null),
			'resolved_drug_id' => $drug_id > 0 ? $drug_id : null,
			'resolved_stock_id' => isset($pr['resolved_stock_id']) ? $pr['resolved_stock_id'] : null,
			'substitution_flag' => (!empty($med->original_medicine_id) || !empty($med->substituted_medicine_id)) ? 1 : 0,
		) as $_col => $_val) {
			if ($this->column_exists('billing_transactions', $_col)) {
				$upd[$_col] = $_val;
			}
		}

		$this->db->trans_start();
		$this->db->where(array('txn_id' => (int)$txn->txn_id, 'InActive' => 0));
		$this->db->update('billing_transactions', $upd);
		if (abs($delta) > 0.009) {
			$this->create_ledger_entry(array(
				'patient_no' => $patient_no,
				'txn_id' => (int)$txn->txn_id,
				'reference_type' => 'ADJUSTMENT',
				'reference_no' => 'RX_SYNC:' . (int)$txn->txn_id,
				'description' => 'Rx sync adjustment: PHARMACY: ' . $resolvedName,
				'debit_amount' => ($delta > 0) ? $delta : 0.0,
				'credit_amount' => ($delta < 0) ? abs($delta) : 0.0,
			), $user_id);
		}
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			return array('ok' => false, 'error' => 'Update failed');
		}

		return array(
			'ok' => true,
			'txn_id' => (int)$txn->txn_id,
			'unit_price' => round($new_unit, 2),
			'net_amount' => $new_net,
			'delta' => $delta,
			'item_name' => $resolvedName,
			'payer_type' => $resolvedPayer,
		);
	}

	public function sync_lab_request($io_lab_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0 || !$this->table_exists('iop_laboratory')) {
			return array('ok' => false, 'error' => 'Lab request not found');
		}

		$item_ref = 'io_lab_id:' . $io_lab_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_LAB);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) {
			return array('ok' => false, 'error' => 'Lab request not found');
		}

		$iop_id = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$patient_no = '';
		if ($this->table_exists('patient_details_iop') && $iop_id !== '') {
			$v = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
			if ($v && isset($v->patient_no)) { $patient_no = (string)$v->patient_no; }
		}
		if ($patient_no === '' && isset($lab->patient_no)) {
			$patient_no = (string)$lab->patient_no;
		}

		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('LAB', 'iop_laboratory:' . $io_lab_id);
		}

		$qty = 1.0;
		$rate = 0.0;
		$item_name = null;
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) { $qty = $line['qty']; $rate = $line['rate']; $item_name = $line['bill_name']; }
		}
		if (($item_name === null || $item_name === '') && isset($lab->laboratory_text) && trim((string)$lab->laboratory_text) !== '') {
			$item_name = (string)$lab->laboratory_text;
		}
		if ($this->table_exists('bill_particular') && isset($lab->laboratory_id)) {
			$bp = $this->db->get_where('bill_particular', array('particular_id' => (int)$lab->laboratory_id, 'InActive' => 0))->row();
			if (($item_name === null || $item_name === '') && $bp && isset($bp->particular_name)) {
				$item_name = (string)$bp->particular_name;
			}
			if ($rate <= 0 && $bp && isset($bp->charge_amount)) {
				$rate = (float)$bp->charge_amount;
			}
		}
		if ($item_name === null || $item_name === '') {
			$item_name = 'Laboratory';
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => 'OPD',
			'department' => self::DEPT_LAB,
			'item_type' => self::ITEM_LAB,
			'item_id' => isset($lab->laboratory_id) ? (int)$lab->laboratory_id : null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function sync_sonography_request($io_lab_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0 || !$this->table_exists('iop_laboratory')) {
			return array('ok' => false, 'error' => 'Sonography request not found');
		}
		$has_charge_table = $this->table_exists('iop_sonography_charge');
		if ($has_charge_table) {
			$this->db->select('charge_id');
			$this->db->from('iop_sonography_charge');
			$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
			$this->db->order_by('charge_id', 'DESC');
			$this->db->limit(1);
			$ch = $this->db->get()->row();
			if ($ch && isset($ch->charge_id) && (int)$ch->charge_id > 0) {
				return $this->sync_sonography_charge((int)$ch->charge_id, $user_id, $invoice_no, $detail_id);
			}
		}
		$item_ref = 'sono_req_io_lab_id:' . $io_lab_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_IMAGING);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}
		if ($has_charge_table) {
			log_message('debug', '[SONO_LEGACY_SYNC_SKIPPED] io_lab_id=' . (int)$io_lab_id . ' reason=no_charge_no_existing_txn');
			return array('ok' => false, 'error' => 'Sonography charge not found');
		}
		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) {
			return array('ok' => false, 'error' => 'Sonography request not found');
		}
		$iop_id = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$patient_no = '';
		if ($this->table_exists('patient_details_iop') && $iop_id !== '') {
			$v = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
			if ($v && isset($v->patient_no)) { $patient_no = (string)$v->patient_no; }
		}
		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('SONOGRAPHY', 'iop_sonography_request:' . $io_lab_id);
		}
		$qty = 1.0;
		$rate = 0.0;
		$item_name = null;
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) {
				$qty = $line['qty'];
				$rate = $line['rate'];
				if ($line['bill_name'] !== null && $line['bill_name'] !== '') { $item_name = $line['bill_name']; }
			}
		}
		if (($item_name === null || $item_name === '') && isset($lab->laboratory_text) && trim((string)$lab->laboratory_text) !== '') {
			$item_name = 'Sonography - ' . (string)$lab->laboratory_text;
		}
		if ($item_name === null || $item_name === '') {
			$item_name = 'Sonography';
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => 'OPD',
			'department' => self::DEPT_IMAGING,
			'item_type' => self::ITEM_IMAGING,
			'item_id' => isset($lab->laboratory_id) ? (int)$lab->laboratory_id : null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function sync_sonography_charge($charge_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$charge_id = (int)$charge_id;
		if ($charge_id <= 0 || !$this->table_exists('iop_sonography_charge')) {
			return array('ok' => false, 'error' => 'Sonography charge not found');
		}
		$item_ref = 'sono_charge_id:' . $charge_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_IMAGING);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}
		$ch = $this->db->get_where('iop_sonography_charge', array('charge_id' => $charge_id, 'InActive' => 0))->row();
		if (!$ch) {
			return array('ok' => false, 'error' => 'Sonography charge not found');
		}
		$iop_id = isset($ch->iop_id) ? (string)$ch->iop_id : '';
		$patient_no = isset($ch->patient_no) ? (string)$ch->patient_no : '';
		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('SONOGRAPHY', 'iop_sonography_charge:' . $charge_id);
		}
		$qty = isset($ch->quantity) ? (float)$ch->quantity : 1.0;
		$rate = isset($ch->rate_amount) ? (float)$ch->rate_amount : 0.0;
		$item_name = isset($ch->item_name) ? (string)$ch->item_name : 'Sonography';
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) {
				$qty = $line['qty'];
				$rate = $line['rate'];
				if ($line['bill_name'] !== null && $line['bill_name'] !== '') { $item_name = $line['bill_name']; }
			}
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => 'OPD',
			'department' => self::DEPT_IMAGING,
			'item_type' => self::ITEM_IMAGING,
			'item_id' => $charge_id,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function sync_ipd_room_charge($charge_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$charge_id = (int)$charge_id;
		if ($charge_id <= 0 || !$this->table_exists('iop_room_charge')) {
			return array('ok' => false, 'error' => 'Room charge not found');
		}
		$item_ref = 'iop_room_charge_id:' . $charge_id;

		$lock_name = null;
		$lock_acquired = false;
		try {
			$ch = $this->db->get_where('iop_room_charge', array('charge_id' => $charge_id, 'InActive' => 0))->row();
			if (!$ch) {
				return array('ok' => false, 'error' => 'Room charge not found');
			}
			$iop_id = isset($ch->iop_id) ? (string)$ch->iop_id : '';
			$patient_no = isset($ch->patient_no) ? (string)$ch->patient_no : '';

			$lock_name = 'bt:IPD_ROOM:' . $iop_id . ':' . $charge_id;
			if ($this->db->dbdriver === 'mysqli') {
				$q = $this->db->query('SELECT GET_LOCK(?, 5) AS lck', array($lock_name));
				$row = $q ? $q->row() : null;
				$lock_acquired = ($row && isset($row->lck) && (int)$row->lck === 1);
			}

			$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_IPD);
			if ($existing) {
				if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
					$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
					$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
				}
				return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
			}

			if ($invoice_no === null || trim((string)$invoice_no) === '') {
				$invoice_no = $this->_get_invoice_no_from_lock('IPD_ROOM', 'iop_room_charge:' . $charge_id);
			}
			$qty = isset($ch->quantity) ? (float)$ch->quantity : 1.0;
			$rate = isset($ch->rate_amount) ? (float)$ch->rate_amount : 0.0;
			$item_name = 'Room Charge';
			if ($invoice_no !== null && $detail_id !== null) {
				$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
				if ($line) { $qty = $line['qty']; $rate = $line['rate']; if ($line['bill_name']) $item_name = $line['bill_name']; }
			}
			$payer_type = $this->_determine_payer_type($patient_no);
			return $this->create_transaction(array(
				'patient_no' => $patient_no,
				'encounter_id' => $iop_id,
				'encounter_type' => 'IPD',
				'department' => self::DEPT_IPD,
				'item_type' => self::ITEM_ROOM,
				'item_id' => $charge_id,
				'item_ref' => $item_ref,
				'item_name' => $item_name,
				'quantity' => $qty,
				'unit_price' => $rate,
				'payer_type' => $payer_type,
				'invoice_no' => $invoice_no
			), $user_id);
		} finally {
			if ($lock_acquired && $lock_name !== null && $this->db->dbdriver === 'mysqli') {
				$this->db->query('SELECT RELEASE_LOCK(?)', array($lock_name));
			}
		}
	}

	public function sync_bed_side_procedure($bed_pro_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$bed_pro_id = (int)$bed_pro_id;
		if ($bed_pro_id <= 0 || !$this->table_exists('iop_bed_side_procedure')) {
			return array('ok' => false, 'error' => 'Bedside procedure not found');
		}
		$item_ref = 'iop_bed_side_procedure_id:' . $bed_pro_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_IPD);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}
		$row = $this->db->get_where('iop_bed_side_procedure', array('bed_pro_id' => $bed_pro_id, 'InActive' => 0))->row();
		if (!$row) {
			return array('ok' => false, 'error' => 'Bedside procedure not found');
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$patient_no = '';
		if ($this->table_exists('patient_details_iop') && $iop_id !== '') {
			$v = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
			if ($v && isset($v->patient_no)) { $patient_no = (string)$v->patient_no; }
		}
		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('IPD_BED_SIDE', 'iop_bed_side_procedure:' . $bed_pro_id);
		}
		$qty = isset($row->qty) ? (float)$row->qty : 1.0;
		$rate = 0.0;
		$item_name = 'Bedside Procedure';
		$catalog_id = isset($row->cItem_id) ? (int)$row->cItem_id : 0;
		if ($catalog_id > 0 && $this->table_exists('bill_particular')) {
			$bp = $this->db->get_where('bill_particular', array('particular_id' => $catalog_id, 'InActive' => 0))->row();
			if ($bp) {
				if (isset($bp->charge_amount)) { $rate = (float)$bp->charge_amount; }
				if (isset($bp->particular_name) && trim((string)$bp->particular_name) !== '') { $item_name = (string)$bp->particular_name; }
			}
		}
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) {
				$qty = $line['qty'];
				$rate = $line['rate'];
				if ($line['bill_name'] !== null && $line['bill_name'] !== '') { $item_name = $line['bill_name']; }
			}
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => 'IPD',
			'department' => self::DEPT_IPD,
			'item_type' => self::ITEM_SERVICE,
			'item_id' => $catalog_id > 0 ? $catalog_id : null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function sync_operation_theater($operation_id, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$operation_id = (int)$operation_id;
		if ($operation_id <= 0 || !$this->table_exists('iop_operation_theater')) {
			return array('ok' => false, 'error' => 'OT event not found');
		}
		$item_ref = 'iop_operation_theater_id:' . $operation_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_IPD);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}
		$row = $this->db->get_where('iop_operation_theater', array('operation_id' => $operation_id, 'InActive' => 0))->row();
		if (!$row) {
			return array('ok' => false, 'error' => 'OT event not found');
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$patient_no = '';
		if ($this->table_exists('patient_details_iop') && $iop_id !== '') {
			$v = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
			if ($v && isset($v->patient_no)) { $patient_no = (string)$v->patient_no; }
		}
		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('IPD_OT', 'iop_operation_theater:' . $operation_id);
		}
		$qty = 1.0;
		$rate = 0.0;
		$item_name = 'Operation Theater';
		$surgery_id = isset($row->operation_name) ? (int)$row->operation_name : 0;
		if ($surgery_id > 0 && $this->table_exists('surgical_package')) {
			$sp = $this->db->get_where('surgical_package', array('surgery_id' => $surgery_id, 'InActive' => 0))->row();
			if ($sp) {
				if (isset($sp->total_costs)) { $rate = (float)$sp->total_costs; }
				if (isset($sp->surgery_name) && trim((string)$sp->surgery_name) !== '') { $item_name = (string)$sp->surgery_name; }
			}
		}
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) {
				$qty = $line['qty'];
				$rate = $line['rate'];
				if ($line['bill_name'] !== null && $line['bill_name'] !== '') { $item_name = $line['bill_name']; }
			}
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => 'IPD',
			'department' => self::DEPT_IPD,
			'item_type' => self::ITEM_SERVICE,
			'item_id' => $surgery_id > 0 ? $surgery_id : null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function sync_radiology_order($order_id, $user_id = null, $invoice_no = null, $detail_id = null, $visit_type = 'OPD'){
		$this->ensure_billing_transaction_schema();
		$order_id = (int)$order_id;
		if ($order_id <= 0 || !$this->table_exists('radiology_orders')) {
			return array('ok' => false, 'error' => 'Radiology order not found');
		}
		$item_ref = 'radiology_order_id:' . $order_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_IMAGING);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}
		$ord = $this->db->get_where('radiology_orders', array('id' => $order_id, 'InActive' => 0))->row();
		if (!$ord) {
			return array('ok' => false, 'error' => 'Radiology order not found');
		}
		$iop_id = isset($ord->iop_id) ? (string)$ord->iop_id : '';
		$patient_no = isset($ord->patient_no) ? (string)$ord->patient_no : '';
		$visit_type = strtoupper(trim((string)$visit_type));
		if ($visit_type !== 'IPD') {
			$visit_type = 'OPD';
		}
		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock('RADIOLOGY', 'radiology_order:' . $order_id);
		}
		$qty = 1.0;
		$rate = 0.0;
		$item_name = 'Radiology';
		$test_id = isset($ord->test_id) ? (int)$ord->test_id : 0;
		if ($test_id > 0) {
			// Centralized pricing: resolve via Price_engine_model instead of reading catalog prices directly.
			// Preserve legacy behavior here: only choose CASH vs NHIS (avoid applying company percentage twice).
			$this->load->model('app/Price_engine_model', 'price_engine_model');
			$payer_type_pre = $this->_determine_payer_type($patient_no);
			$payer_for_price = ($payer_type_pre === 'NHIS') ? 'NHIS' : 'CASH';
			$resolved = $this->price_engine_model->resolve(array(
				'item_type'  => 'RADIOLOGY',
				'item_id'    => $test_id,
				'patient_no' => $patient_no,
				'payer_type' => $payer_for_price,
				'quantity'   => 1,
			));
			if (!empty($resolved) && !empty($resolved['ok'])) {
				$item_name = isset($resolved['item_name']) ? (string)$resolved['item_name'] : $item_name;
				$rate = isset($resolved['unit_price']) ? (float)$resolved['unit_price'] : 0.0;
			} else {
				$err = isset($resolved['error']) ? (string)$resolved['error'] : 'Unknown price engine error';
				log_message('error', 'Billing Transaction: Price engine resolve failed for radiology test_id=' . (int)$test_id . ' patient_no=' . (string)$patient_no . ' error=' . $err);
			}
		}
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) {
				$qty = $line['qty'];
				$rate = $line['rate'];
				if ($line['bill_name'] !== null && $line['bill_name'] !== '') { $item_name = $line['bill_name']; }
			}
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => $visit_type,
			'department' => self::DEPT_IMAGING,
			'item_type' => self::ITEM_IMAGING,
			'item_id' => $test_id > 0 ? $test_id : null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function sync_visit_fee($iop_id, $fee_type, $user_id = null, $invoice_no = null, $detail_id = null){
		$this->ensure_billing_transaction_schema();
		$iop_id = trim((string)$iop_id);
		$fee_type = strtoupper(trim((string)$fee_type));
		if ($iop_id === '' || ($fee_type !== 'REGISTRATION' && $fee_type !== 'CONSULTATION') || !$this->table_exists('patient_details_iop')) {
			return array('ok' => false, 'error' => 'Invalid visit fee');
		}
		$item_ref = 'visit_' . strtolower($fee_type) . ':' . $iop_id;
		$existing = $this->get_transaction_by_item_ref($item_ref, self::DEPT_OPD);
		if ($existing) {
			if (($existing->invoice_no === null || trim((string)$existing->invoice_no) === '') && $invoice_no) {
				$this->db->where(array('txn_id' => (int)$existing->txn_id, 'InActive' => 0));
				$this->db->update('billing_transactions', array('invoice_no' => (string)$invoice_no, 'updated_at' => date('Y-m-d H:i:s'), 'updated_by' => $user_id));
			}
			return array('ok' => true, 'txn_id' => $existing->txn_id, 'already_exists' => true);
		}
		$visit = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
		if (!$visit || !isset($visit->patient_no)) {
			return array('ok' => false, 'error' => 'Visit not found');
		}
		$patient_no = (string)$visit->patient_no;
		if ($invoice_no === null || trim((string)$invoice_no) === '') {
			$invoice_no = $this->_get_invoice_no_from_lock($fee_type, $item_ref);
		}
		$qty = 1.0;
		$rate = 0.0;
		$item_name = ($fee_type === 'REGISTRATION') ? 'Registration' : 'Consultation';
		if ($invoice_no !== null && $detail_id !== null) {
			$line = $this->_get_invoice_line_amounts($invoice_no, $detail_id);
			if ($line) { $qty = $line['qty']; $rate = $line['rate']; if ($line['bill_name']) $item_name = $line['bill_name']; }
		}
		$payer_type = $this->_determine_payer_type($patient_no);
		$item_type = ($fee_type === 'CONSULTATION') ? self::ITEM_CONSULT : self::ITEM_SERVICE;
		return $this->create_transaction(array(
			'patient_no' => $patient_no,
			'encounter_id' => $iop_id,
			'encounter_type' => 'OPD',
			'department' => self::DEPT_OPD,
			'item_type' => $item_type,
			'item_id' => null,
			'item_ref' => $item_ref,
			'item_name' => $item_name,
			'quantity' => $qty,
			'unit_price' => $rate,
			'payer_type' => $payer_type,
			'invoice_no' => $invoice_no
		), $user_id);
	}

	public function link_transactions_to_invoice($invoice_no, $user_id = null){
		$this->ensure_billing_transaction_schema();
		$invoice_no = trim((string)$invoice_no);
		if ($invoice_no === '' || !$this->table_exists('iop_billable_item_lock')) {
			return array('ok' => false, 'error' => 'Missing invoice or lock table');
		}
		$this->db->select('source_module, source_ref, detail_id, iop_id, patient_no');
		$this->db->from('iop_billable_item_lock');
		$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
		$locks = $this->db->get()->result();
		if (empty($locks)) {
			return array('ok' => true, 'updated' => 0);
		}
		$updated = 0;
		$now = date('Y-m-d H:i:s');
		foreach ($locks as $l) {
			$srcMod = isset($l->source_module) ? strtoupper(trim((string)$l->source_module)) : '';
			$srcRef = isset($l->source_ref) ? trim((string)$l->source_ref) : '';
			$detail_id = isset($l->detail_id) ? (int)$l->detail_id : null;
			if ($srcMod === '' || $srcRef === '') continue;

			if ($srcMod === 'PHARMACY') {
				$mid = 0;
				$pos = strrpos($srcRef, 'iop_medication:');
				if ($pos !== false) {
					$mid = (int)substr($srcRef, $pos + strlen('iop_medication:'));
				} else {
					$pos2 = strrpos($srcRef, 'iop_med_id:');
					if ($pos2 !== false) {
						$mid = (int)substr($srcRef, $pos2 + strlen('iop_med_id:'));
					}
				}
				if ($mid > 0) {
					$this->sync_pharmacy_medication($mid, $user_id, $invoice_no, $detail_id);
					$this->db->where(array('InActive' => 0, 'department' => self::DEPT_PHARMACY, 'item_ref' => 'iop_med_id:' . $mid));
					$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
					$updated += max(0, (int)$this->db->affected_rows());
				}
				continue;
			}

			if ($srcMod === 'LAB' && strpos($srcRef, 'iop_laboratory:') === 0) {
				$lid = (int)substr($srcRef, strlen('iop_laboratory:'));
				if ($lid > 0) {
					$this->sync_lab_request($lid, $user_id, $invoice_no, $detail_id);
					$this->db->where(array('InActive' => 0, 'department' => self::DEPT_LAB, 'item_ref' => 'io_lab_id:' . $lid));
					$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
					$updated += max(0, (int)$this->db->affected_rows());
				}
				continue;
			}

			if ($srcMod === 'SONOGRAPHY') {
				if (strpos($srcRef, 'iop_sonography_request:') === 0) {
					$id = (int)substr($srcRef, strlen('iop_sonography_request:'));
					if ($id > 0) {
						$charge_id = 0;
						if ($this->table_exists('iop_sonography_charge')) {
							$this->db->select('charge_id');
							$this->db->from('iop_sonography_charge');
							$this->db->where(array('io_lab_id' => $id, 'InActive' => 0));
							$this->db->order_by('charge_id', 'DESC');
							$this->db->limit(1);
							$ch = $this->db->get()->row();
							if ($ch && isset($ch->charge_id)) {
								$charge_id = (int)$ch->charge_id;
							}
						}
						if ($charge_id > 0) {
							$this->sync_sonography_charge($charge_id, $user_id, $invoice_no, $detail_id);
							$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IMAGING, 'item_ref' => 'sono_charge_id:' . $charge_id));
							$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
							$updated += max(0, (int)$this->db->affected_rows());
						} else {
							$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IMAGING, 'item_ref' => 'sono_req_io_lab_id:' . $id));
							$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
							$updated += max(0, (int)$this->db->affected_rows());
							if ((int)$this->db->affected_rows() <= 0) {
								log_message('debug', '[SONO_LEGACY_LINK_SKIPPED] invoice_no=' . (string)$invoice_no . ' io_lab_id=' . (int)$id . ' reason=no_charge_no_existing_txn');
							}
						}
					}
				} elseif (strpos($srcRef, 'iop_sonography_charge:') === 0) {
					$id = (int)substr($srcRef, strlen('iop_sonography_charge:'));
					if ($id > 0) {
						$this->sync_sonography_charge($id, $user_id, $invoice_no, $detail_id);
						$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IMAGING, 'item_ref' => 'sono_charge_id:' . $id));
						$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
						$updated += max(0, (int)$this->db->affected_rows());
					}
				}
				continue;
			}

			if ($srcMod === 'IPD_BED_SIDE' && strpos($srcRef, 'iop_bed_side_procedure:') === 0) {
				$id = (int)substr($srcRef, strlen('iop_bed_side_procedure:'));
				if ($id > 0 && $this->table_exists('iop_bed_side_procedure')) {
					// Only treat as event-PK if a row actually exists with that PK; otherwise it's legacy/orphan
					$exists = $this->db->get_where('iop_bed_side_procedure', array('bed_pro_id' => $id, 'InActive' => 0))->row();
					if ($exists) {
						$this->sync_bed_side_procedure($id, $user_id, $invoice_no, $detail_id);
						$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IPD, 'item_ref' => 'iop_bed_side_procedure_id:' . $id));
						$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
						$updated += max(0, (int)$this->db->affected_rows());
					}
				}
				continue;
			}

			if ($srcMod === 'IPD_OT' && strpos($srcRef, 'iop_operation_theater:') === 0) {
				$id = (int)substr($srcRef, strlen('iop_operation_theater:'));
				if ($id > 0 && $this->table_exists('iop_operation_theater')) {
					$exists = $this->db->get_where('iop_operation_theater', array('operation_id' => $id, 'InActive' => 0))->row();
					if ($exists) {
						$this->sync_operation_theater($id, $user_id, $invoice_no, $detail_id);
						$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IPD, 'item_ref' => 'iop_operation_theater_id:' . $id));
						$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
						$updated += max(0, (int)$this->db->affected_rows());
					}
				}
				continue;
			}

			if ($srcMod === 'RADIOLOGY' && strpos($srcRef, 'radiology_order:') === 0) {
				$id = (int)substr($srcRef, strlen('radiology_order:'));
				if ($id > 0) {
					$vt = 'OPD';
					try {
						if ($this->table_exists('radiology_orders') && $this->table_exists('patient_details_iop')) {
							$ord = $this->db->select('iop_id')->get_where('radiology_orders', array('id' => $id, 'InActive' => 0))->row();
							$iop_id = ($ord && isset($ord->iop_id)) ? (string)$ord->iop_id : '';
							if ($iop_id !== '') {
								$enc = $this->db->select('patient_type')->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
								$t = $enc && isset($enc->patient_type) ? strtoupper(trim((string)$enc->patient_type)) : '';
								if ($t === 'IPD') { $vt = 'IPD'; }
							}
						}
					} catch (Throwable $e) { }
					$this->sync_radiology_order($id, $user_id, $invoice_no, $detail_id, $vt);
					$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IMAGING, 'item_ref' => 'radiology_order_id:' . $id));
					$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
					$updated += max(0, (int)$this->db->affected_rows());
				}
				continue;
			}

			if ($srcMod === 'IPD_ROOM' && strpos($srcRef, 'iop_room_charge:') === 0) {
				$id = (int)substr($srcRef, strlen('iop_room_charge:'));
				if ($id > 0) {
					$this->sync_ipd_room_charge($id, $user_id, $invoice_no, $detail_id);
					$this->db->where(array('InActive' => 0, 'department' => self::DEPT_IPD, 'item_ref' => 'iop_room_charge_id:' . $id));
					$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
					$updated += max(0, (int)$this->db->affected_rows());
				}
				continue;
			}

			if (($srcMod === 'REGISTRATION' && strpos($srcRef, 'visit_registration:') === 0) || ($srcMod === 'CONSULTATION' && strpos($srcRef, 'visit_consultation:') === 0)) {
				$type = $srcMod;
				$parts = explode(':', $srcRef, 2);
				$vid = (count($parts) === 2) ? trim((string)$parts[1]) : '';
				if ($vid !== '') {
					$this->sync_visit_fee($vid, $type, $user_id, $invoice_no, $detail_id);
					$this->db->where(array('InActive' => 0, 'department' => self::DEPT_OPD, 'item_ref' => $srcRef));
					$this->db->update('billing_transactions', array('invoice_no' => $invoice_no, 'updated_at' => $now, 'updated_by' => $user_id));
					$updated += max(0, (int)$this->db->affected_rows());
				}
				continue;
			}
		}
		return array('ok' => true, 'updated' => $updated);
	}

	public function sync_receipt_payment($receipt_no, $user_id = null){
		$this->ensure_billing_transaction_schema();
		if (!$this->table_exists('iop_receipt')) {
			return array('ok' => false, 'error' => 'Receipt table not found');
		}
		$receipt = $this->db->get_where('iop_receipt', array('receipt_no' => (string)$receipt_no, 'InActive' => 0))->row();
		if (!$receipt) {
			return array('ok' => false, 'error' => 'Receipt not found');
		}
		$invoice_no = isset($receipt->invoice_no) ? trim((string)$receipt->invoice_no) : '';
		if ($invoice_no === '') {
			return array('ok' => false, 'error' => 'No invoice linked to receipt');
		}

		$this->link_transactions_to_invoice($invoice_no, $user_id);
		try {
			if ($this->table_exists('iop_billing')
				&& $this->column_exists('billing_transactions', 'billing_subject_type')
				&& $this->column_exists('billing_transactions', 'billing_subject_id')
				&& $this->column_exists('iop_billing', 'billing_subject_type')
				&& $this->column_exists('iop_billing', 'billing_subject_id'))
			{
				$inv = $this->db->get_where('iop_billing', array('invoice_no' => $invoice_no, 'InActive' => 0))->row();
				$bst = $inv && isset($inv->billing_subject_type) ? trim((string)$inv->billing_subject_type) : '';
				$bsid = $inv && isset($inv->billing_subject_id) ? trim((string)$inv->billing_subject_id) : '';
				if ($bst !== '' && $bsid !== '') {
					$this->db->where('invoice_no', $invoice_no);
					$this->db->where('InActive', 0);
					$this->db->group_start();
					$this->db->where('billing_subject_type IS NULL', null, false);
					$this->db->or_where('billing_subject_type', '');
					$this->db->group_end();
					$this->db->update('billing_transactions', array(
						'billing_subject_type' => $bst,
						'billing_subject_id' => $bsid,
					));
				}
			}
		} catch (Throwable $e) {
		}
		$txns = $this->db->get_where('billing_transactions', array('invoice_no' => $invoice_no, 'InActive' => 0))->result();
		if (empty($txns)) {
			return array('ok' => false, 'error' => 'No transactions found for invoice');
		}

		$receipt_amount = isset($receipt->amountPaid) ? (float)$receipt->amountPaid : 0.0;
		if ($receipt_amount <= 0) {
			return array('ok' => true, 'results' => array());
		}

		$already_synced = 0.0;
		$this->db->select('COALESCE(SUM(credit_amount), 0) as total', false);
		$this->db->where('reference_type', 'PAYMENT');
		$this->db->where('reference_no', (string)$receipt_no);
		$this->db->where('InActive', 0);
		$row = $this->db->get('patient_financial_ledger')->row();
		$already_synced = ($row && isset($row->total)) ? (float)$row->total : 0.0;

		$amount = $receipt_amount - $already_synced;
		if ($amount <= 0.0001) {
			return array('ok' => true, 'results' => array());
		}

		$results = array();
		foreach ($txns as $txn) {
			if ($amount <= 0) break;
			$balance = isset($txn->balance_amount) ? (float)$txn->balance_amount : 0.0;
			if ($balance <= 0) continue;
			$pay_amount = min((float)$balance, $amount);
			$results[] = $this->record_payment($txn->txn_id, $pay_amount, (string)$receipt_no, $user_id);
			$amount -= $pay_amount;
		}

		return array('ok' => true, 'results' => $results);
	}

	public function refund_receipt_payment($receipt_no, $refund_receipt_no, $refund_amount, $user_id = null, $reason = null)
	{
		$this->ensure_billing_transaction_schema();
		$receipt_no = trim((string)$receipt_no);
		$refund_receipt_no = trim((string)$refund_receipt_no);
		$refund_amount = (float)$refund_amount;
		if ($receipt_no === '' || $refund_receipt_no === '' || $refund_amount <= 0) {
			return array('ok' => false, 'error' => 'Invalid refund request');
		}
		if (!$this->table_exists('patient_financial_ledger') || !$this->table_exists('billing_transactions')) {
			return array('ok' => false, 'error' => 'SSOT ledger tables not found');
		}

		// Idempotency: only act if this receipt has active PAYMENT ledger rows.
		$this->db->where(array(
			'reference_type' => 'PAYMENT',
			'reference_no' => $receipt_no,
			'InActive' => 0,
		));
		$ledger_rows = $this->db->get('patient_financial_ledger')->result();
		if (empty($ledger_rows)) {
			return array('ok' => true, 'results' => array());
		}

		$by_txn = array();
		foreach ($ledger_rows as $lr) {
			$txn_id = isset($lr->txn_id) ? (int)$lr->txn_id : 0;
			$amt = isset($lr->credit_amount) ? (float)$lr->credit_amount : 0.0;
			if ($txn_id <= 0 || $amt <= 0) {
				continue;
			}
			if (!isset($by_txn[$txn_id])) {
				$by_txn[$txn_id] = 0.0;
			}
			$by_txn[$txn_id] += $amt;
		}
		if (empty($by_txn)) {
			return array('ok' => true, 'results' => array());
		}

		$remaining = $refund_amount;
		$now = date('Y-m-d H:i:s');
		$results = array();
		$reason = $reason !== null ? trim((string)$reason) : '';

		$this->db->trans_start();

		// Retag original ledger rows so a future re-activation/resync can work.
		foreach ($ledger_rows as $lr) {
			$lid = isset($lr->ledger_id) ? (int)$lr->ledger_id : 0;
			if ($lid <= 0) continue;
			$this->db->where('ledger_id', $lid);
			$this->db->where('InActive', 0);
			$this->db->update('patient_financial_ledger', array(
				'reference_type' => 'VOIDED_PAYMENT',
			));
		}

		foreach ($by_txn as $txn_id => $void_amount) {
			if ($remaining <= 0) {
				break;
			}
			$apply = min((float)$void_amount, $remaining);
			if ($apply <= 0) {
				continue;
			}

			$txn = $this->get_transaction($txn_id);
			if (!$txn) {
				$results[] = array('ok' => false, 'txn_id' => $txn_id, 'error' => 'Transaction not found');
				continue;
			}
			$old_paid = (float)$txn->paid_amount;
			$net = (float)$txn->net_amount;
			$new_paid = max(0.0, $old_paid - $apply);
			$new_balance = max(0.0, $net - $new_paid);

			$new_status = self::PAY_PENDING;
			$eps = 0.0001;
			if ($new_paid <= $eps) {
				$new_status = self::PAY_PENDING;
			} elseif ($new_paid + $eps >= $net) {
				$new_status = self::PAY_PAID;
				$new_balance = 0.0;
			} else {
				$new_status = self::PAY_PARTIAL;
			}

			$upd = array(
				'paid_amount' => $new_paid,
				'balance_amount' => $new_balance,
				'payment_status' => $new_status,
				'updated_at' => $now,
				'updated_by' => $user_id,
			);
			if (isset($txn->receipt_no) && (string)$txn->receipt_no === $receipt_no) {
				$upd['receipt_no'] = null;
			}
			$this->db->where('txn_id', (int)$txn_id);
			$this->db->update('billing_transactions', $upd);

			// Compensating ledger entry (debit increases patient balance)
			$desc = 'Refund for receipt ' . $receipt_no;
			if ($reason !== '') {
				$desc .= ' (' . $reason . ')';
			}
			$this->create_ledger_entry(array(
				'patient_no' => $txn->patient_no,
				'txn_id' => $txn_id,
				'reference_type' => 'REFUND',
				'reference_no' => $refund_receipt_no,
				'description' => $desc,
				'debit_amount' => $apply,
				'credit_amount' => 0,
			), $user_id);

			$this->log_audit(array(
				'txn_id' => $txn_id,
				'table_name' => 'billing_transactions',
				'record_id' => $txn_id,
				'action' => 'REFUND_PAYMENT',
				'field_name' => 'paid_amount',
				'old_value' => $old_paid,
				'new_value' => $new_paid,
				'patient_no' => $txn->patient_no,
				'encounter_id' => $txn->encounter_id,
			), $user_id);

			$results[] = array(
				'ok' => true,
				'txn_id' => $txn_id,
				'refund_amount' => $apply,
				'old_paid' => $old_paid,
				'new_paid' => $new_paid,
				'new_balance' => $new_balance,
				'new_status' => $new_status,
			);
			$remaining -= $apply;
		}
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			return array('ok' => false, 'error' => 'Refund sync transaction failed');
		}

		return array('ok' => true, 'results' => $results, 'refunded' => ($refund_amount - max(0.0, $remaining)));
	}

	public function void_receipt_payment($receipt_no, $user_id = null, $reason = null)
	{
		$this->ensure_billing_transaction_schema();
		$receipt_no = trim((string)$receipt_no);
		if ($receipt_no === '') {
			return array('ok' => false, 'error' => 'Invalid receipt_no');
		}
		if (!$this->table_exists('patient_financial_ledger') || !$this->table_exists('billing_transactions')) {
			return array('ok' => false, 'error' => 'SSOT ledger tables not found');
		}

		// Idempotency: only act if this receipt has active PAYMENT ledger rows.
		$this->db->where(array(
			'reference_type' => 'PAYMENT',
			'reference_no' => $receipt_no,
			'InActive' => 0,
		));
		$ledger_rows = $this->db->get('patient_financial_ledger')->result();
		if (empty($ledger_rows)) {
			return array('ok' => true, 'results' => array());
		}

		$by_txn = array();
		foreach ($ledger_rows as $lr) {
			$txn_id = isset($lr->txn_id) ? (int)$lr->txn_id : 0;
			$amt = isset($lr->credit_amount) ? (float)$lr->credit_amount : 0.0;
			if ($txn_id <= 0 || $amt <= 0) {
				continue;
			}
			if (!isset($by_txn[$txn_id])) {
				$by_txn[$txn_id] = 0.0;
			}
			$by_txn[$txn_id] += $amt;
		}
		if (empty($by_txn)) {
			return array('ok' => true, 'results' => array());
		}

		$now = date('Y-m-d H:i:s');
		$results = array();
		$reason = $reason !== null ? trim((string)$reason) : '';

		$this->db->trans_start();

		// Retag original ledger rows so a future re-activation/resync can work.
		foreach ($ledger_rows as $lr) {
			$lid = isset($lr->ledger_id) ? (int)$lr->ledger_id : 0;
			if ($lid <= 0) continue;
			$this->db->where('ledger_id', $lid);
			$this->db->where('InActive', 0);
			$this->db->update('patient_financial_ledger', array(
				'reference_type' => 'VOIDED_PAYMENT',
			));
		}

		foreach ($by_txn as $txn_id => $void_amount) {
			if ($void_amount <= 0) {
				continue;
			}

			$txn = $this->get_transaction($txn_id);
			if (!$txn) {
				$results[] = array('ok' => false, 'txn_id' => $txn_id, 'error' => 'Transaction not found');
				continue;
			}
			$old_paid = (float)$txn->paid_amount;
			$net = (float)$txn->net_amount;
			$new_paid = max(0.0, $old_paid - $void_amount);
			$new_balance = max(0.0, $net - $new_paid);

			$new_status = self::PAY_PENDING;
			$eps = 0.0001;
			if ($new_paid <= $eps) {
				$new_status = self::PAY_PENDING;
			} elseif ($new_paid + $eps >= $net) {
				$new_status = self::PAY_PAID;
				$new_balance = 0.0;
			} else {
				$new_status = self::PAY_PARTIAL;
			}

			$upd = array(
				'paid_amount' => $new_paid,
				'balance_amount' => $new_balance,
				'payment_status' => $new_status,
				'updated_at' => $now,
				'updated_by' => $user_id,
			);
			if (isset($txn->receipt_no) && (string)$txn->receipt_no === $receipt_no) {
				$upd['receipt_no'] = null;
			}
			$this->db->where('txn_id', (int)$txn_id);
			$this->db->update('billing_transactions', $upd);

			// Compensating ledger entry (debit increases patient balance)
			$desc = 'Void payment for: ' . (string)$txn->item_name;
			if ($reason !== '') {
				$desc .= ' (' . $reason . ')';
			}
			$this->create_ledger_entry(array(
				'patient_no' => $txn->patient_no,
				'txn_id' => $txn_id,
				'reference_type' => 'VOID',
				'reference_no' => $receipt_no,
				'description' => $desc,
				'debit_amount' => $void_amount,
				'credit_amount' => 0,
			), $user_id);

			$this->log_audit(array(
				'txn_id' => $txn_id,
				'table_name' => 'billing_transactions',
				'record_id' => $txn_id,
				'action' => 'VOID_PAYMENT',
				'field_name' => 'paid_amount',
				'old_value' => $old_paid,
				'new_value' => $new_paid,
				'patient_no' => $txn->patient_no,
				'encounter_id' => $txn->encounter_id,
			), $user_id);

			$results[] = array(
				'ok' => true,
				'txn_id' => $txn_id,
				'void_amount' => $void_amount,
				'old_paid' => $old_paid,
				'new_paid' => $new_paid,
				'new_balance' => $new_balance,
				'new_status' => $new_status,
			);
		}

		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			return array('ok' => false, 'error' => 'Void sync transaction failed');
		}

		return array('ok' => true, 'results' => $results);
	}

	// ==================== COMPATIBILITY SUMMARIES & RECON ====================

	public function get_encounter_summary($encounter_id){
		$this->ensure_billing_transaction_schema();
		$encounter_id = (string)$encounter_id;
		$this->db->select('COALESCE(SUM(net_amount),0) as total_amount, COALESCE(SUM(paid_amount),0) as paid_amount, COALESCE(SUM(balance_amount),0) as balance_amount', false);
		$this->db->where(array('encounter_id' => $encounter_id, 'InActive' => 0));
		$row = $this->db->get('billing_transactions')->row();
		return $row ? $row : (object)array('total_amount' => 0, 'paid_amount' => 0, 'balance_amount' => 0);
	}

	public function get_department_daily_summary($department, $date = null){
		$this->ensure_billing_transaction_schema();
		$department = strtoupper(trim((string)$department));
		$date = $date ? (string)$date : date('Y-m-d');
		$this->db->select('COUNT(*) as total_transactions, COALESCE(SUM(net_amount), 0) as total_amount, COALESCE(SUM(paid_amount), 0) as collected_amount, COALESCE(SUM(balance_amount), 0) as outstanding_amount', false);
		$this->db->from('billing_transactions');
		$this->db->where('InActive', 0);
		$this->db->where('department', $department);
		$this->db->where('DATE(created_at)', $date);
		return $this->db->get()->row();
	}

	public function get_reconciliation_issues($filters = array()){
		$this->ensure_billing_transaction_schema();
		if (!$this->table_exists('billing_reconciliation_log')) {
			return array();
		}
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
		if ($limit <= 0) { $limit = 50; }
		$this->db->from('billing_reconciliation_log');
		$this->db->where('InActive', 0);
		if (isset($filters['resolved'])) {
			$this->db->where('resolved', (int)$filters['resolved']);
		} else {
			$this->db->where('resolved', 0);
		}
		if (isset($filters['department']) && trim((string)$filters['department']) !== '') {
			$this->db->where('department', strtoupper(trim((string)$filters['department'])));
		}
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function run_reconciliation($department = null, $date = null){
		// Safe minimal implementation: ensure schema and return existing unresolved issues.
		$this->ensure_billing_transaction_schema();
		$filters = array('limit' => 200, 'resolved' => 0);
		if ($department) { $filters['department'] = $department; }
		return $this->get_reconciliation_issues($filters);
	}

    // ==================== LEDGER MANAGEMENT ====================

    /**
     * Create a ledger entry and update running balance
     */
    public function create_ledger_entry($data, $user_id = null){
        $this->ensure_billing_transaction_schema();
        
        $patient_no = isset($data['patient_no']) ? (string)$data['patient_no'] : '';
        if ($patient_no === '') {
            return false;
        }
        
        // Get current balance
        $current_balance = $this->get_patient_balance($patient_no);
        
        $debit = isset($data['debit_amount']) ? (float)$data['debit_amount'] : 0;
        $credit = isset($data['credit_amount']) ? (float)$data['credit_amount'] : 0;
        $new_balance = $current_balance + $debit - $credit;
        
        $now = date('Y-m-d H:i:s');
        
        $this->db->insert('patient_financial_ledger', array(
            'patient_no'      => $patient_no,
            'txn_id'          => isset($data['txn_id']) ? (int)$data['txn_id'] : null,
            'reference_type'  => isset($data['reference_type']) ? (string)$data['reference_type'] : 'CHARGE',
            'reference_no'    => isset($data['reference_no']) ? (string)$data['reference_no'] : null,
            'description'     => isset($data['description']) ? (string)$data['description'] : null,
            'debit_amount'    => $debit,
            'credit_amount'   => $credit,
            'running_balance' => $new_balance,
            'created_by'      => $user_id,
            'created_at'      => $now,
            'InActive'        => 0
        ));
        
        return $this->db->insert_id();
    }

    /**
     * Get patient's current balance
     */
    public function get_patient_balance($patient_no){
        $this->ensure_billing_transaction_schema();
        
        $this->db->select('running_balance');
        $this->db->where(array('patient_no' => (string)$patient_no, 'InActive' => 0));
        $this->db->order_by('ledger_id', 'DESC');
        $this->db->limit(1);
        $row = $this->db->get('patient_financial_ledger')->row();
        
        return $row ? (float)$row->running_balance : 0;
    }

    /**
     * Get patient's ledger history
     */
    public function get_patient_ledger($patient_no, $limit = 100){
        $this->ensure_billing_transaction_schema();
        
        $this->db->where(array('patient_no' => (string)$patient_no, 'InActive' => 0));
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get('patient_financial_ledger')->result();
    }


    /**
     * Get NHIS vs Cash breakdown
     */
    public function get_payer_type_breakdown($start_date, $end_date){
        $this->ensure_billing_transaction_schema();
        
        $this->db->select("
            payer_type,
            COUNT(*) as transaction_count,
            SUM(net_amount) as total_amount,
            SUM(paid_amount) as paid_amount,
            SUM(balance_amount) as outstanding_amount
        ", false);
        $this->db->from('billing_transactions');
        $this->db->where('InActive', 0);
        $this->db->where('DATE(created_at) >=', $start_date);
        $this->db->where('DATE(created_at) <=', $end_date);
        $this->db->group_by('payer_type');
        
        return $this->db->get()->result();
    }

    /**
     * Get pharmacy consumption report from unified transactions
     */
    public function get_pharmacy_consumption_report($start_date, $end_date){
        $this->ensure_billing_transaction_schema();
        
        $this->db->select("
            item_name,
            item_code,
            SUM(quantity) as total_dispensed,
            SUM(net_amount) as total_value,
            SUM(CASE WHEN payer_type = 'NHIS' THEN quantity ELSE 0 END) as nhis_qty,
            SUM(CASE WHEN payer_type = 'CASH' THEN quantity ELSE 0 END) as cash_qty,
            COUNT(DISTINCT patient_no) as patient_count
        ", false);
        $this->db->from('billing_transactions');
        $this->db->where('InActive', 0);
        $this->db->where('department', 'PHARMACY');
        $this->db->where('DATE(created_at) >=', $start_date);
        $this->db->where('DATE(created_at) <=', $end_date);
        $this->db->group_by('item_code, item_name');
        $this->db->order_by('total_dispensed', 'DESC');
        
        return $this->db->get()->result();
    }

    /**
     * Get outstanding balances report
     */
    public function get_outstanding_balances($limit = 100){
        $this->ensure_billing_transaction_schema();
        
        $this->db->select("
            patient_no,
            COUNT(*) as transaction_count,
            SUM(net_amount) as total_billed,
            SUM(paid_amount) as total_paid,
            SUM(balance_amount) as total_outstanding
        ", false);
        $this->db->from('billing_transactions');
        $this->db->where('InActive', 0);
        $this->db->where('balance_amount >', 0);
        $this->db->group_by('patient_no');
        $this->db->order_by('total_outstanding', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }

    /**
     * Get collection efficiency report
     */
    public function get_collection_efficiency($start_date, $end_date){
        $this->ensure_billing_transaction_schema();
        
        $this->db->select("
            department,
            SUM(net_amount) as total_billed,
            SUM(paid_amount) as total_collected,
            ROUND(SUM(paid_amount) / NULLIF(SUM(net_amount), 0) * 100, 2) as collection_rate
        ", false);
        $this->db->from('billing_transactions');
        $this->db->where('InActive', 0);
        $this->db->where('DATE(created_at) >=', $start_date);
        $this->db->where('DATE(created_at) <=', $end_date);
        $this->db->group_by('department');
        
        return $this->db->get()->result();
    }

    /**
     * Get financial summary for dashboard
     */
    public function get_financial_dashboard_summary($date = null){
        $this->ensure_billing_transaction_schema();
        
        $date = $date ?: date('Y-m-d');
        
        $result = array(
            'today_billed' => 0,
            'today_collected' => 0,
            'today_outstanding' => 0,
            'total_outstanding' => 0,
            'nhis_today' => 0,
            'cash_today' => 0,
            'transactions_today' => 0
        );
        
        // Today's summary
        $this->db->select("
            COUNT(*) as txn_count,
            COALESCE(SUM(net_amount), 0) as billed,
            COALESCE(SUM(paid_amount), 0) as collected,
            COALESCE(SUM(balance_amount), 0) as outstanding,
            COALESCE(SUM(CASE WHEN payer_type = 'NHIS' THEN net_amount ELSE 0 END), 0) as nhis,
            COALESCE(SUM(CASE WHEN payer_type = 'CASH' THEN net_amount ELSE 0 END), 0) as cash
        ", false);
        $this->db->from('billing_transactions');
        $this->db->where('InActive', 0);
        $this->db->where('DATE(created_at)', $date);
        $today = $this->db->get()->row();
        
        if ($today) {
            $result['today_billed'] = (float)$today->billed;
            $result['today_collected'] = (float)$today->collected;
            $result['today_outstanding'] = (float)$today->outstanding;
            $result['nhis_today'] = (float)$today->nhis;
            $result['cash_today'] = (float)$today->cash;
            $result['transactions_today'] = (int)$today->txn_count;
        }
        
        // Total outstanding
        $this->db->select('COALESCE(SUM(balance_amount), 0) as total', false);
        $this->db->from('billing_transactions');
        $this->db->where('InActive', 0);
        $this->db->where('balance_amount >', 0);
        $total = $this->db->get()->row();
        $result['total_outstanding'] = $total ? (float)$total->total : 0;
        
        return $result;
    }

    public function log_audit($data, $user_id = null){
        try {
            if (!$this->table_exists('billing_audit_log')) {
                try {
                    $this->load->model('app/Billing_audit_model');
                    if (isset($this->billing_audit_model) && method_exists($this->billing_audit_model, 'ensure_schema')) {
                        $this->billing_audit_model->ensure_schema();
                    }
                } catch (Throwable $e) {
                    return false;
                }
            }
            if (!$this->table_exists('billing_audit_log')) {
                return false;
            }

            $uid = ($user_id !== null) ? (int)$user_id : 0;
            $username = 'SYSTEM';
            try {
                if (isset($this->session) && method_exists($this->session, 'userdata')) {
                    $u = $this->session->userdata('username');
                    if (is_string($u) && $u !== '') { $username = $u; }
                }
            } catch (Throwable $e) { }

            $ip = null;
            $ua = null;
            try {
                if (isset($this->input) && method_exists($this->input, 'ip_address')) {
                    $ip = $this->input->ip_address();
                }
                if (isset($this->input) && method_exists($this->input, 'user_agent')) {
                    $ua = substr((string)$this->input->user_agent(), 0, 255);
                }
            } catch (Throwable $e) { }

            $action = isset($data['action']) ? (string)$data['action'] : 'UNKNOWN';
            $entityType = isset($data['table_name']) ? (string)$data['table_name'] : null;
            $entityId = isset($data['record_id']) ? (string)$data['record_id'] : (isset($data['txn_id']) ? (string)$data['txn_id'] : null);

            $payload = json_encode($data);
            $insert = array();
            if ($this->column_exists('billing_audit_log', 'user_id')) { $insert['user_id'] = $uid; }
            if ($this->column_exists('billing_audit_log', 'username')) { $insert['username'] = $username; }
            if ($this->column_exists('billing_audit_log', 'user_role')) { $insert['user_role'] = null; }
            if ($this->column_exists('billing_audit_log', 'action')) { $insert['action'] = $action; }
            if ($this->column_exists('billing_audit_log', 'action_type')) { $insert['action_type'] = $action; }
            if ($this->column_exists('billing_audit_log', 'entity_type')) { $insert['entity_type'] = $entityType; }
            if ($this->column_exists('billing_audit_log', 'entity_id')) { $insert['entity_id'] = $entityId; }
            if ($this->column_exists('billing_audit_log', 'invoice_id')) { $insert['invoice_id'] = isset($data['invoice_id']) ? (int)$data['invoice_id'] : null; }
            if ($this->column_exists('billing_audit_log', 'invoice_no')) { $insert['invoice_no'] = isset($data['invoice_no']) ? (string)$data['invoice_no'] : null; }
            if ($this->column_exists('billing_audit_log', 'service_order_id')) { $insert['service_order_id'] = isset($data['service_order_id']) ? (int)$data['service_order_id'] : null; }
            if ($this->column_exists('billing_audit_log', 'patient_no')) { $insert['patient_no'] = isset($data['patient_no']) ? (string)$data['patient_no'] : null; }
            if ($this->column_exists('billing_audit_log', 'amount')) { $insert['amount'] = isset($data['amount']) ? (float)$data['amount'] : 0; }
            if ($this->column_exists('billing_audit_log', 'previous_amount')) { $insert['previous_amount'] = null; }
            if ($this->column_exists('billing_audit_log', 'new_amount')) { $insert['new_amount'] = null; }
            if ($this->column_exists('billing_audit_log', 'amount_before')) { $insert['amount_before'] = null; }
            if ($this->column_exists('billing_audit_log', 'amount_after')) { $insert['amount_after'] = isset($data['amount']) ? (float)$data['amount'] : 0; }
            if ($this->column_exists('billing_audit_log', 'payment_method')) { $insert['payment_method'] = null; }
            if ($this->column_exists('billing_audit_log', 'description')) { $insert['description'] = isset($data['description']) ? (string)$data['description'] : null; }
            if ($this->column_exists('billing_audit_log', 'old_value')) { $insert['old_value'] = null; }
            if ($this->column_exists('billing_audit_log', 'new_value')) { $insert['new_value'] = $payload; }
            if ($this->column_exists('billing_audit_log', 'metadata')) { $insert['metadata'] = $payload; }
            if ($this->column_exists('billing_audit_log', 'performed_by')) { $insert['performed_by'] = $uid; }
            if ($this->column_exists('billing_audit_log', 'performed_at')) { $insert['performed_at'] = date('Y-m-d H:i:s'); }
            if ($this->column_exists('billing_audit_log', 'created_at')) { $insert['created_at'] = date('Y-m-d H:i:s'); }
            if ($this->column_exists('billing_audit_log', 'ip_address')) { $insert['ip_address'] = $ip; }
            if ($this->column_exists('billing_audit_log', 'user_agent')) { $insert['user_agent'] = $ua; }
            if (empty($insert)) { return false; }

            $prevDebug = isset($this->db->db_debug) ? $this->db->db_debug : null;
            if ($prevDebug !== null) { $this->db->db_debug = false; }
            $ok = $this->db->insert('billing_audit_log', $insert);
            if ($prevDebug !== null) { $this->db->db_debug = $prevDebug; }
            return $ok ? $this->db->insert_id() : false;
        } catch (Throwable $e) {
            return false;
        }
    }
}
