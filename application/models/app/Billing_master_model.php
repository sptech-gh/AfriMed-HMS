<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing Master Model - SINGLE SOURCE OF TRUTH
 * Unified billing system for entire HMS
 * 
 * This is the ONLY model that should handle:
 * - Invoice creation
 * - Payment recording
 * - Service gates
 * - Billing status
 * 
 * @author HMS Enterprise Architect
 * @version 3.0 - Unified Billing
 */
class Billing_master_model extends CI_Model
{
    // Payment Status Constants
    const STATUS_PENDING = 'PENDING';
    const STATUS_PARTIAL = 'PARTIAL';
    const STATUS_PAID = 'PAID';
    const STATUS_OVERPAID = 'OVERPAID';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REFUNDED = 'REFUNDED';
    const STATUS_CREDIT = 'CREDIT';
    const STATUS_INSURANCE_PENDING = 'INSURANCE_PENDING';
    const STATUS_NHIS_PENDING = 'NHIS_PENDING';
    
    // Service Gate Status
    const GATE_BLOCKED = 'BLOCKED';
    const GATE_RELEASED = 'RELEASED';
    const GATE_EXPIRED = 'EXPIRED';
    
    // Service Types
    const SERVICE_REGISTRATION = 'REGISTRATION';
    const SERVICE_CONSULTATION = 'CONSULTATION';
    const SERVICE_LABORATORY = 'LABORATORY';
    const SERVICE_RADIOLOGY = 'RADIOLOGY';
    const SERVICE_SONOGRAPHY = 'SONOGRAPHY';
    const SERVICE_PHARMACY = 'PHARMACY';
    const SERVICE_PROCEDURE = 'PROCEDURE';
    const SERVICE_SURGERY = 'SURGERY';
    const SERVICE_ROOM = 'ROOM';
    const SERVICE_SUPPLY = 'SUPPLY';
    const SERVICE_ADMISSION = 'ADMISSION';
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('schema_guard');
        if (!schema_already_run('billing_master_schema')) {
            $this->ensure_schema();
            mark_schema_run('billing_master_schema');
        }
		$this->_ensure_legacy_billing_indexes();
    }

	private function _ensure_legacy_billing_indexes()
	{
		static $done = false;
		if ($done) {
			return;
		}
		$done = true;

		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) {
			$this->db->db_debug = false;
		}
		try {
			if ($this->db->table_exists('iop_receipt')) {
				$idx = $this->db->query("SHOW INDEX FROM `iop_receipt` WHERE Key_name = 'idx_receipt_active_invoice'");
				if (!$idx || $idx->num_rows() === 0) {
					try {
						$this->db->query("ALTER TABLE `iop_receipt` ADD INDEX `idx_receipt_active_invoice` (`InActive`, `invoice_no`)");
					} catch (\Throwable $e) {
					}
				}

				$idx2 = $this->db->query("SHOW INDEX FROM `iop_receipt` WHERE Key_name = 'idx_receipt_active_date'");
				if (!$idx2 || $idx2->num_rows() === 0) {
					try {
						$this->db->query("ALTER TABLE `iop_receipt` ADD INDEX `idx_receipt_active_date` (`InActive`, `dDate`)");
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

	private function _resolve_legacy_invoice_no_for_bill($bill)
	{
		if (!$bill || !isset($bill->visit_id) || !isset($bill->patient_no)) {
			return null;
		}
		$visit_id = trim((string)$bill->visit_id);
		$patient_no = trim((string)$bill->patient_no);
		if ($visit_id === '' || $patient_no === '' || !$this->db->table_exists('iop_billing')) {
			return null;
		}
		$this->db->where('iop_id', $visit_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->where('InActive', 0);
		$this->db->order_by('bill_id', 'DESC');
		$this->db->limit(1);
		$row = $this->db->get('iop_billing')->row();
		return ($row && isset($row->invoice_no)) ? (string)$row->invoice_no : null;
	}
    
    /* ================================================================== */
    /*  SCHEMA CREATION                                                    */
    /* ================================================================== */
    
    public function ensure_schema()
    {
        $this->_create_billing_master_table();
        $this->_create_billing_items_table();
        $this->_create_billing_payments_table();
        $this->_create_billing_payment_links_table();
        $this->_create_billing_audit_log();
        $this->_create_billing_adjustments_table();
    }
    
    private function _create_billing_master_table()
    {
        if (!$this->db->table_exists('billing_master')) {
            $this->db->query("CREATE TABLE billing_master (
                bill_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bill_no VARCHAR(50) UNIQUE NOT NULL,
                patient_no VARCHAR(25) NOT NULL,
                visit_id VARCHAR(25) NOT NULL,
                visit_type ENUM('OPD','IPD','EMERGENCY','PHARMACY','WALK_IN') DEFAULT 'OPD',
                total_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                tax_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                net_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                paid_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                balance_due DECIMAL(18,2) NOT NULL DEFAULT 0.00,
                payment_status ENUM('PENDING','PARTIAL','PAID','OVERPAID','CANCELLED','REFUNDED','PARTIAL_REFUND','CREDIT','INSURANCE_PENDING','NHIS_PENDING') DEFAULT 'PENDING',
                payer_type ENUM('CASH','NHIS','INSURANCE','COMPANY','STAFF') DEFAULT 'CASH',
                insurance_id VARCHAR(50) NULL,
                coverage_amount DECIMAL(18,2) DEFAULT 0.00,
                patient_liability DECIMAL(18,2) DEFAULT 0.00,
                created_by VARCHAR(25) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_by VARCHAR(25) NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                billed_by VARCHAR(25) NULL,
                billed_at DATETIME NULL,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_patient (patient_no),
                INDEX idx_visit (visit_id),
                INDEX idx_status (payment_status),
                INDEX idx_date (created_at),
                INDEX idx_bill_no (bill_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

	private function _create_billing_payment_links_table()
	{
		if (!$this->db->table_exists('billing_payment_links')) {
			$this->db->query("CREATE TABLE billing_payment_links (
				link_id BIGINT AUTO_INCREMENT PRIMARY KEY,
				payment_id BIGINT NOT NULL,
				bill_id BIGINT NOT NULL,
				invoice_no VARCHAR(50) NULL,
				receipt_no VARCHAR(50) NULL,
				created_by VARCHAR(25) NULL,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				InActive TINYINT(1) DEFAULT 0,
				UNIQUE KEY uq_payment (payment_id),
				INDEX idx_bill (bill_id),
				INDEX idx_invoice (invoice_no),
				INDEX idx_receipt (receipt_no)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
	}
    
    private function _create_billing_items_table()
    {
        if (!$this->db->table_exists('billing_items')) {
            $this->db->query("CREATE TABLE billing_items (
                item_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bill_id BIGINT NOT NULL,
                service_type ENUM('REGISTRATION','CONSULTATION','LABORATORY','RADIOLOGY','SONOGRAPHY','PHARMACY','PROCEDURE','SURGERY','ROOM','SUPPLY','ADMISSION','OTHER') NOT NULL,
                service_id VARCHAR(50) NULL,
                service_code VARCHAR(50) NULL,
                service_name VARCHAR(255) NOT NULL,
                department VARCHAR(100) NOT NULL,
                requested_by VARCHAR(25) NULL,
                requested_at DATETIME NULL,
                quantity DECIMAL(10,2) DEFAULT 1.00,
                unit_price DECIMAL(18,2) NOT NULL,
                gross_amount DECIMAL(18,2) NOT NULL,
                discount_amount DECIMAL(18,2) DEFAULT 0.00,
                discount_reason VARCHAR(255) NULL,
                discount_approved_by VARCHAR(25) NULL,
                net_amount DECIMAL(18,2) NOT NULL,
                -- Company Pricing Audit Fields
                base_price DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Original price before company adjustment',
                adjusted_price DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Price after company percentage adjustment',
                company_id INT DEFAULT NULL COMMENT 'Company that pricing adjustment was applied for',
                adjustment_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage applied for this line item',
                gate_status ENUM('BLOCKED','RELEASED','EXPIRED') DEFAULT 'BLOCKED',
                released_at DATETIME NULL,
                released_by VARCHAR(25) NULL,
                source_module VARCHAR(50) NULL,
                source_ref_id VARCHAR(50) NULL,
                source_ref_table VARCHAR(50) NULL,
                InActive TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bill (bill_id),
                INDEX idx_service (service_type, service_id),
                INDEX idx_gate (gate_status),
                INDEX idx_department (department),
                INDEX idx_company (company_id),
                FOREIGN KEY (bill_id) REFERENCES billing_master(bill_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        // Ensure company pricing columns exist on existing tables
        $this->_ensure_billing_items_company_pricing_columns();
    }

    /**
     * Ensure company pricing audit columns exist on billing_items (for existing installations)
     */
    private function _ensure_billing_items_company_pricing_columns()
    {
        if ($this->db->table_exists('billing_items')) {
            // Use direct SQL to check column existence (more reliable than list_fields cache)
            $columns_to_add = array(
                'base_price' => "ADD COLUMN base_price DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Original price before company adjustment' AFTER net_amount",
                'adjusted_price' => "ADD COLUMN adjusted_price DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Price after company percentage adjustment' AFTER base_price",
                'company_id' => "ADD COLUMN company_id INT DEFAULT NULL COMMENT 'Company that pricing adjustment was applied for' AFTER adjusted_price",
                'adjustment_percentage' => "ADD COLUMN adjustment_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Percentage applied for this line item' AFTER company_id"
            );
            
            foreach ($columns_to_add as $column => $alter_sql) {
                if (!$this->_column_exists('billing_items', $column)) {
                    try {
                        $this->db->query("ALTER TABLE billing_items " . $alter_sql);
                    } catch (Exception $e) {
                        // Column might already exist - ignore duplicate column errors
                        log_message('debug', "billing_items column {$column}: " . $e->getMessage());
                    }
                }
            }
        }
    }
    
    /**
     * Check if a column exists in a table using INFORMATION_SCHEMA
     */
    private function _column_exists($table, $column)
    {
        $result = $this->db->query("
            SELECT COUNT(*) as cnt 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = ? 
            AND COLUMN_NAME = ?
        ", array($table, $column))->row();
        return ($result && $result->cnt > 0);
    }
    
    private function _create_billing_payments_table()
    {
        if (!$this->db->table_exists('billing_payments')) {
            $this->db->query("CREATE TABLE billing_payments (
                payment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bill_id BIGINT NOT NULL,
                payment_no VARCHAR(50) UNIQUE NOT NULL,
                amount DECIMAL(18,2) NOT NULL,
                payment_method ENUM('CASH','MOMO','BANK_TRANSFER','CARD','NHIS','INSURANCE','CHEQUE','STAFF_CREDIT') NOT NULL,
                reference_no VARCHAR(100) NULL,
                allocated_amount DECIMAL(18,2) NOT NULL,
                collected_by VARCHAR(25) NOT NULL,
                collected_at DATETIME NOT NULL,
                notes TEXT NULL,
                is_reconciled TINYINT(1) DEFAULT 0,
                reconciled_at DATETIME NULL,
                reconciled_by VARCHAR(25) NULL,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_bill (bill_id),
                INDEX idx_payment_no (payment_no),
                INDEX idx_date (collected_at),
                INDEX idx_method (payment_method),
                FOREIGN KEY (bill_id) REFERENCES billing_master(bill_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
    
    private function _create_billing_audit_log()
    {
        if (!$this->db->table_exists('billing_audit_log')) {
            $this->db->query("CREATE TABLE billing_audit_log (
                log_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bill_id BIGINT NULL,
                item_id BIGINT NULL,
                payment_id BIGINT NULL,
                action ENUM('CREATE','UPDATE','DELETE','PAYMENT','REFUND','DISCOUNT','GATE_RELEASE','CANCEL','RESTORE') NOT NULL,
                description TEXT NOT NULL,
                old_values JSON NULL,
                new_values JSON NULL,
                performed_by VARCHAR(25) NOT NULL,
                performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL,
                INDEX idx_bill (bill_id),
                INDEX idx_action (action),
                INDEX idx_date (performed_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
    
    private function _create_billing_adjustments_table()
    {
        if (!$this->db->table_exists('billing_adjustments')) {
            $this->db->query("CREATE TABLE billing_adjustments (
                adjustment_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                bill_id BIGINT NOT NULL,
                item_id BIGINT NULL,
                adjustment_type ENUM('DISCOUNT','WAIVER','REFUND','CORRECTION','WRITE_OFF') NOT NULL,
                amount DECIMAL(18,2) NOT NULL,
                reason VARCHAR(255) NOT NULL,
                approved_by VARCHAR(25) NOT NULL,
                adjusted_by VARCHAR(25) NOT NULL,
                adjusted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_bill (bill_id),
                FOREIGN KEY (bill_id) REFERENCES billing_master(bill_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }
    
    /* ================================================================== */
    /*  CORE BILLING OPERATIONS                                          */
    /* ================================================================== */
    
    /**
     * Create a new bill from service items
     * SINGLE ENTRY POINT for all billing
     */
    public function create_bill($data)
    {
        $required = ['patient_no', 'visit_id', 'items', 'created_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Missing required field: {$field}"];
            }
        }
        
        $this->db->trans_begin();
        
        try {
            // Generate bill number
            $bill_no = $this->_generate_bill_no();
            
            // Calculate totals
            $totals = $this->_calculate_totals($data['items']);
            
            // Determine payer type and coverage
            $payer_info = $this->_get_payer_info($data['patient_no']);
            
            // Insert bill header
            $bill_data = [
                'bill_no' => $bill_no,
                'patient_no' => $data['patient_no'],
                'visit_id' => $data['visit_id'],
                'visit_type' => $data['visit_type'] ?? 'OPD',
                'total_amount' => $totals['total'],
                'discount_amount' => $totals['discount'],
                'tax_amount' => $totals['tax'],
                'net_amount' => $totals['net'],
                'paid_amount' => 0.00,
                'balance_due' => $totals['net'],
                'payment_status' => self::STATUS_PENDING,
                'payer_type' => $payer_info['payer_type'],
                'insurance_id' => $payer_info['insurance_id'] ?? null,
                'coverage_amount' => 0.00,
                'patient_liability' => $totals['net'],
                'created_by' => $data['created_by'],
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $this->db->insert('billing_master', $bill_data);
            $bill_id = $this->db->insert_id();
            
            // Insert line items with gates and company pricing
            $company_id = $payer_info['insurance_id'] ?? null;
            foreach ($data['items'] as $item) {
                $this->_add_bill_item($bill_id, $item, $data['created_by'], $company_id);
            }
            
            // Log audit
            $this->_log_audit($bill_id, null, null, 'CREATE', 
                "Bill {$bill_no} created for {$data['patient_no']}", 
                null, $bill_data, $data['created_by']);
            
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Database error during bill creation'];
            }
            
            $this->db->trans_commit();
            
            return [
                'success' => true,
                'bill_id' => $bill_id,
                'bill_no' => $bill_no,
                'total' => $totals['net'],
                'items_count' => count($data['items'])
            ];
            
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Record a payment against a bill
     * SINGLE ENTRY POINT for all payments
     */
    public function record_payment($data)
    {
        $required = ['bill_id', 'amount', 'payment_method', 'collected_by'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "Missing required field: {$field}"];
            }
        }
        
        $this->db->trans_begin();
        
        try {
            // Get bill details
            $bill = $this->db->get_where('billing_master', 
                ['bill_id' => $data['bill_id'], 'InActive' => 0])->row();
            
            if (!$bill) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Bill not found'];
            }
            
            if ($bill->payment_status === self::STATUS_CANCELLED) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Cannot pay a cancelled bill'];
            }
            
            // Validate amount
            if ($data['amount'] <= 0) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Payment amount must be positive'];
            }
            
            if ($data['amount'] > $bill->balance_due) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 
                    'Payment amount exceeds balance due: ' . number_format($bill->balance_due, 2)];
            }
            
            // Generate payment number
            $payment_no = $this->_generate_payment_no();
            
            // Insert payment
            $payment_data = [
                'bill_id' => $data['bill_id'],
                'payment_no' => $payment_no,
                'amount' => $data['amount'],
                'payment_method' => $data['payment_method'],
                'reference_no' => $data['reference_no'] ?? null,
                'allocated_amount' => $data['amount'],
                'collected_by' => $data['collected_by'],
                'collected_at' => date('Y-m-d H:i:s'),
                'notes' => $data['notes'] ?? null
            ];
            
            $this->db->insert('billing_payments', $payment_data);
            $payment_id = $this->db->insert_id();

			$linked_invoice_no = null;
			$linked_receipt_no = null;
			try {
				$this->load->model('app/Billing_facade_model', 'billing_facade_model');
				if (isset($this->billing_facade_model) && method_exists($this->billing_facade_model, 'is_receipt_route_enabled') && $this->billing_facade_model->is_receipt_route_enabled()) {
					$linked_invoice_no = $this->_resolve_legacy_invoice_no_for_bill($bill);
					if ($linked_invoice_no !== null && trim((string)$linked_invoice_no) !== '') {
						$pm = strtoupper(trim((string)$data['payment_method']));
						if ($pm === 'BANK_TRANSFER') {
							$pm = 'BANK';
						}
						$res = $this->billing_facade_model->record_payment(array(
							'invoice_no'     => (string)$linked_invoice_no,
							'amount'         => (float)$data['amount'],
							'payment_method' => $pm,
							'reference'      => isset($data['reference_no']) ? (string)$data['reference_no'] : null,
							'notes'          => isset($data['notes']) ? (string)$data['notes'] : null,
							'cashier_id'     => (string)$data['collected_by'],
							'source'         => 'BILLING_MASTER',
						));
						if (empty($res['ok'])) {
							$this->db->trans_rollback();
							return ['success' => false, 'error' => 'Legacy receipt recording failed: ' . (isset($res['error']) ? $res['error'] : 'Unknown error')];
						}
						$linked_receipt_no = isset($res['receipt_no']) ? (string)$res['receipt_no'] : null;
					}
				}
			} catch (Exception $e) {
				$this->db->trans_rollback();
				return ['success' => false, 'error' => $e->getMessage()];
			}

			if ($this->db->table_exists('billing_payment_links')) {
				$this->db->insert('billing_payment_links', array(
					'payment_id' => $payment_id,
					'bill_id'    => $data['bill_id'],
					'invoice_no' => $linked_invoice_no,
					'receipt_no' => $linked_receipt_no,
					'created_by' => $data['collected_by'],
					'created_at' => date('Y-m-d H:i:s'),
					'InActive'   => 0,
				));
			}
            
            // Update bill totals and status
            $new_paid = $bill->paid_amount + $data['amount'];
            $new_balance = $bill->net_amount - $new_paid;
            
            if ($new_balance <= 0) {
                $new_status = ($new_balance < 0) ? self::STATUS_OVERPAID : self::STATUS_PAID;
            } else {
                $new_status = ($new_paid > 0) ? self::STATUS_PARTIAL : self::STATUS_PENDING;
            }
            
            $this->db->where('bill_id', $data['bill_id']);
            $this->db->update('billing_master', [
                'paid_amount' => $new_paid,
                'balance_due' => max(0, $new_balance),
                'payment_status' => $new_status,
                'updated_by' => $data['collected_by'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Release service gates for paid items
            if ($new_status === self::STATUS_PAID || $data['payment_method'] === 'NHIS') {
                $this->_release_service_gates($data['bill_id'], $data['collected_by']);
            }
            
            // Log audit
            $this->_log_audit($data['bill_id'], null, $payment_id, 'PAYMENT',
                "Payment of " . number_format($data['amount'], 2) . " recorded",
                ['balance_due' => $bill->balance_due, 'status' => $bill->payment_status],
                ['balance_due' => max(0, $new_balance), 'status' => $new_status],
                $data['collected_by']);
            
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Database error during payment'];
            }
            
            $this->db->trans_commit();
            
            return [
                'success' => true,
                'payment_id' => $payment_id,
                'payment_no' => $payment_no,
                'receipt_no' => $linked_receipt_no,
                'invoice_no' => $linked_invoice_no,
                'bill_status' => $new_status,
                'balance_remaining' => max(0, $new_balance)
            ];
            
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Add a service item to an existing bill
     * Used when doctor orders additional services
     */
    public function add_service_to_bill($bill_id, $service_data, $user_id)
    {
        $this->db->trans_begin();
        
        try {
            $bill = $this->db->get_where('billing_master', 
                ['bill_id' => $bill_id, 'InActive' => 0])->row();
            
            if (!$bill) {
                return ['success' => false, 'error' => 'Bill not found'];
            }
            
            if ($bill->payment_status === self::STATUS_PAID) {
                return ['success' => false, 'error' => 'Cannot add items to a fully paid bill'];
            }
            
            // Add the item
            $item_id = $this->_add_bill_item($bill_id, $service_data, $user_id);
            
            // Recalculate bill totals
            $this->_recalculate_bill($bill_id);
            
            // Log audit
            $this->_log_audit($bill_id, $item_id, null, 'UPDATE',
                "Added service: {$service_data['service_name']}",
                null, $service_data, $user_id);
            
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Database error'];
            }
            
            $this->db->trans_commit();
            
            return ['success' => true, 'item_id' => $item_id];
            
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Apply discount to a bill or item
     */
    public function apply_discount($data)
    {
        $this->db->trans_begin();
        
        try {
            $bill = $this->db->get_where('billing_master',
                ['bill_id' => $data['bill_id'], 'InActive' => 0])->row();
            
            if (!$bill) {
                return ['success' => false, 'error' => 'Bill not found'];
            }
            
            $old_values = [
                'discount_amount' => $bill->discount_amount,
                'net_amount' => $bill->net_amount,
                'balance_due' => $bill->balance_due
            ];
            
            if (!empty($data['item_id'])) {
                // Item-level discount
                $this->db->where('item_id', $data['item_id']);
                $this->db->update('billing_items', [
                    'discount_amount' => $data['amount'],
                    'discount_reason' => $data['reason'],
                    'discount_approved_by' => $data['approved_by'],
                    'net_amount' => $data['new_net_amount']
                ]);
            }
            
            // Update bill totals
            $new_discount = $bill->discount_amount + $data['amount'];
            $new_net = $bill->total_amount - $new_discount;
            $new_balance = $new_net - $bill->paid_amount;
            
            $this->db->where('bill_id', $data['bill_id']);
            $this->db->update('billing_master', [
                'discount_amount' => $new_discount,
                'net_amount' => $new_net,
                'balance_due' => max(0, $new_balance),
                'updated_by' => $data['adjusted_by'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            // Record adjustment
            $this->db->insert('billing_adjustments', [
                'bill_id' => $data['bill_id'],
                'item_id' => $data['item_id'] ?? null,
                'adjustment_type' => 'DISCOUNT',
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'approved_by' => $data['approved_by'],
                'adjusted_by' => $data['adjusted_by']
            ]);
            
            // Log audit
            $new_values = [
                'discount_amount' => $new_discount,
                'net_amount' => $new_net,
                'balance_due' => max(0, $new_balance)
            ];
            
            $this->_log_audit($data['bill_id'], $data['item_id'] ?? null, null, 'DISCOUNT',
                "Discount of " . number_format($data['amount'], 2) . " applied: {$data['reason']}",
                $old_values, $new_values, $data['adjusted_by']);
            
            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return ['success' => false, 'error' => 'Database error'];
            }
            
            $this->db->trans_commit();
            
            return ['success' => true];
            
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /* ================================================================== */
    /*  SERVICE GATE CONTROL                                             */
    /* ================================================================== */
    
    /**
     * Release service gate - allows department to proceed
     */
    public function release_service_gate($item_id, $user_id)
    {
        $this->db->where('item_id', $item_id);
        $this->db->update('billing_items', [
            'gate_status' => self::GATE_RELEASED,
            'released_at' => date('Y-m-d H:i:s'),
            'released_by' => $user_id
        ]);
        
        $this->_log_audit(null, $item_id, null, 'GATE_RELEASE',
            'Service gate released', 
            ['gate_status' => self::GATE_BLOCKED],
            ['gate_status' => self::GATE_RELEASED],
            $user_id);
        
        return ['success' => true];
    }
    
    /**
     * Check if service is accessible (gate released)
     */
    public function can_access_service($item_id)
    {
        $item = $this->db->get_where('billing_items', ['item_id' => $item_id])->row();
        
        if (!$item) return false;
        
        // Gate is released or item has no gate (e.g., registration)
        if ($item->gate_status === self::GATE_RELEASED) return true;
        
        // Some services don't need gates
        $no_gate_services = [self::SERVICE_REGISTRATION, self::SERVICE_CONSULTATION];
        if (in_array($item->service_type, $no_gate_services)) return true;
        
        return false;
    }
    
    /* ================================================================== */
    /*  QUERIES & REPORTS                                                */
    /* ================================================================== */
    
    public function get_bill($bill_id)
    {
        $this->db->select('b.*, CONCAT(p.lastname, " ", p.firstname) as patient_name');
        $this->db->from('billing_master b');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        $this->db->where('b.bill_id', $bill_id);
        $this->db->where('b.InActive', 0);
        return $this->db->get()->row();
    }
    
    public function get_bill_by_no($bill_no)
    {
        $this->db->select('b.*, CONCAT(p.lastname, " ", p.firstname) as patient_name');
        $this->db->from('billing_master b');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        $this->db->where('b.bill_no', $bill_no);
        $this->db->where('b.InActive', 0);
        return $this->db->get()->row();
    }
    
    public function get_bill_items($bill_id, $include_inactive = false)
    {
        $this->db->where('bill_id', $bill_id);
        if (!$include_inactive) {
            $this->db->where('InActive', 0);
        }
        return $this->db->get('billing_items')->result();
    }
    
    public function get_bill_payments($bill_id)
    {
		if ($this->db->table_exists('billing_payment_links')) {
			$this->db->select('p.*, L.invoice_no as legacy_invoice_no, L.receipt_no as legacy_receipt_no');
			$this->db->from('billing_payments p');
			$this->db->join('billing_payment_links L', 'L.payment_id = p.payment_id AND L.InActive = 0', 'left');
			$this->db->where('p.bill_id', $bill_id);
			$this->db->where('p.InActive', 0);
			$this->db->order_by('p.collected_at', 'DESC');
			$q = $this->db->get();
			return $q ? $q->result() : array();
		}
		$this->db->where('bill_id', $bill_id);
		$this->db->where('InActive', 0);
		$this->db->order_by('collected_at', 'DESC');
		return $this->db->get('billing_payments')->result();
    }
    
    public function get_patient_bills($patient_no, $status = null)
    {
        $this->db->select('b.*, CONCAT(p.lastname, " ", p.firstname) as patient_name');
        $this->db->from('billing_master b');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        $this->db->where('b.patient_no', $patient_no);
        $this->db->where('b.InActive', 0);
        
        if ($status) {
            $this->db->where('b.payment_status', $status);
        }
        
        $this->db->order_by('b.created_at', 'DESC');
        return $this->db->get()->result();
    }
    
    public function get_latest_payments_for_bills($bill_ids)
    {
        if (empty($bill_ids) || !is_array($bill_ids)) {
            return array();
        }
        $ids = array();
        foreach ($bill_ids as $id) {
            $id = (int)$id;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        if (empty($ids)) {
            return array();
        }

        $sub = $this->db
            ->select('bill_id, MAX(payment_id) as payment_id')
            ->from('billing_payments')
            ->where('InActive', 0)
            ->where_in('bill_id', $ids)
            ->group_by('bill_id')
            ->get_compiled_select();

        $this->db->select('p.*');
        if ($this->db->table_exists('billing_payment_links')) {
            $this->db->select('L.invoice_no as legacy_invoice_no, L.receipt_no as legacy_receipt_no');
        }
        $this->db->from('billing_payments p');
        $this->db->join("($sub) t", 't.payment_id = p.payment_id', 'inner');
        if ($this->db->table_exists('billing_payment_links')) {
            $this->db->join('billing_payment_links L', 'L.payment_id = p.payment_id AND L.InActive = 0', 'left');
        }
        $q = $this->db->get();
        $rows = $q ? $q->result() : array();
        $out = array();
        foreach ($rows as $r) {
            if (isset($r->bill_id)) {
                $out[(string)$r->bill_id] = $r;
            }
        }
        return $out;
    }
    
    public function get_pending_bills($date = null, $department = null)
    {
        $results = array();
        
        // 1. Query PENDING LAB BILLS from iop_lab_billing (most common pending items)
        if ($this->db->table_exists('iop_lab_billing')) {
            $sql = "SELECT 
                        lb.io_lab_id AS invoice_no,
                        lb.iop_id,
                        lb.patient_no,
                        COALESCE(NULLIF(lb.rate_amount, 0), bp.charge_amount, 0) AS total_amount,
                        0 AS amount_paid,
                        COALESCE(NULLIF(lb.rate_amount, 0), bp.charge_amount, 0) AS balance_due,
                        lb.created_at,
                        'Lab Test' AS payment_type,
                        CONCAT(p.firstname, ' ', p.lastname) AS patient_name,
                        p.mobile_no AS phone,
                        pd.patient_type AS visit_type,
                        bp.particular_name AS item_name,
                        'LAB' AS bill_type
                    FROM iop_lab_billing lb
                    LEFT JOIN patient_details_iop pd ON pd.IO_ID = lb.iop_id AND pd.InActive = 0
                    LEFT JOIN patient_personal_info p ON p.patient_no = lb.patient_no
                    LEFT JOIN bill_particular bp ON bp.particular_id = lb.laboratory_id
                    WHERE lb.InActive = 0 
                    AND lb.payment_status = 'PENDING'
                    AND lb.billing_generated = 1
                    ORDER BY lb.created_at DESC
                    LIMIT 30";
            $labBills = $this->db->query($sql)->result();
            $results = array_merge($results, $labBills);
        }
        
        // 2. Query UNPAID INVOICES from iop_billing (generated invoices not fully paid)
        if ($this->db->table_exists('iop_billing')) {
            $sql = "SELECT 
                        b.invoice_no,
                        b.iop_id,
                        b.patient_no,
                        b.total_amount,
                        IFNULL(rsum.amount_paid, 0) AS amount_paid,
                        (b.total_amount - IFNULL(rsum.amount_paid, 0)) AS balance_due,
                        b.dDate AS created_at,
                        b.payment_type,
                        CONCAT(p.firstname, ' ', p.lastname) AS patient_name,
                        p.mobile_no AS phone,
                        pd.patient_type AS visit_type,
                        'Invoice' AS item_name,
                        'INVOICE' AS bill_type
                    FROM iop_billing b
                    LEFT JOIN (
                        SELECT invoice_no, SUM(amountPaid) AS amount_paid
                        FROM iop_receipt
                        WHERE InActive = 0
                        GROUP BY invoice_no
                    ) rsum ON rsum.invoice_no = b.invoice_no
                    LEFT JOIN patient_details_iop pd ON pd.IO_ID = b.iop_id AND pd.InActive = 0
                    LEFT JOIN patient_personal_info p ON p.patient_no = b.patient_no
                    WHERE b.InActive = 0 
                    AND b.total_amount > IFNULL(rsum.amount_paid, 0)
                    ORDER BY b.dDate DESC
                    LIMIT 20";
            $invoiceBills = $this->db->query($sql)->result();
            $results = array_merge($results, $invoiceBills);
        }
        
        return $results;
    }
    
    public function get_dashboard_summary($date = null)
    {
        $date = $date ?? date('Y-m-d');
		$fromDate = $date;
		$toDate = date('Y-m-d', strtotime($date . ' +1 day'));
		$fromDt = $fromDate . ' 00:00:00';
		$toDt = $toDate . ' 00:00:00';
        
        // Query from LEGACY tables (actual data source)
        $stats = [
            'total_bills' => 0,
            'total_amount' => 0,
            'total_paid' => 0,
            'total_pending' => 0,
            'by_status' => []
        ];
        
        // Check if legacy tables exist
        if (!$this->db->table_exists('iop_billing')) {
            return $stats;
        }
        
        // Total bills today from iop_billing
        $q = $this->db->query("SELECT COUNT(*) AS cnt, IFNULL(SUM(total_amount), 0) AS total 
							   FROM iop_billing WHERE dDate >= " . $this->db->escape($fromDate) . " AND dDate < " . $this->db->escape($toDate) . " AND InActive = 0");
        $row = $q->row();
        $stats['total_bills'] = $row ? (int)$row->cnt : 0;
        $stats['total_amount'] = $row ? (float)$row->total : 0;
        
        // Total collected today from iop_receipt
        if ($this->db->table_exists('iop_receipt')) {
            $q = $this->db->query("SELECT IFNULL(SUM(amountPaid), 0) AS collected 
							   FROM iop_receipt WHERE dDate >= " . $this->db->escape($fromDt) . " AND dDate < " . $this->db->escape($toDt) . " AND InActive = 0");
            $row = $q->row();
            $stats['total_paid'] = $row ? (float)$row->collected : 0;
        }
        
        // Outstanding balance (ALL TIME - total unpaid across all bills)
        $q = $this->db->query("
            SELECT 
                COUNT(*) AS pending_count,
                IFNULL(SUM(b.total_amount - IFNULL(rsum.amount_paid, 0)), 0) AS outstanding
            FROM iop_billing b
            LEFT JOIN (
                SELECT invoice_no, SUM(amountPaid) AS amount_paid
                FROM iop_receipt
                WHERE InActive = 0
                GROUP BY invoice_no
            ) rsum ON rsum.invoice_no = b.invoice_no
            WHERE b.InActive = 0
            AND b.total_amount > IFNULL(rsum.amount_paid, 0)
        ");
        $row = $q->row();
        $stats['total_pending'] = $row ? (float)$row->outstanding : 0;
        $stats['pending_count'] = $row ? (int)$row->pending_count : 0;
        
        // Pending lab bills count and amount
        if ($this->db->table_exists('iop_lab_billing')) {
            $q = $this->db->query("SELECT 
                                       COUNT(*) AS cnt,
                                       IFNULL(SUM(COALESCE(NULLIF(lb.rate_amount, 0), bp.charge_amount, 0)), 0) AS total
                                   FROM iop_lab_billing lb
                                   LEFT JOIN bill_particular bp ON bp.particular_id = lb.laboratory_id
                                   WHERE lb.payment_status = 'PENDING' AND lb.billing_generated = 1 AND lb.InActive = 0");
            $row = $q->row();
            $stats['pending_lab_bills'] = $row ? (int)$row->cnt : 0;
            $stats['pending_lab_amount'] = $row ? (float)$row->total : 0;
            
            // Add lab bills to pending totals
            $stats['pending_count'] = ($stats['pending_count'] ?? 0) + ($row ? (int)$row->cnt : 0);
            $stats['total_pending'] = ($stats['total_pending'] ?? 0) + ($row ? (float)$row->total : 0);
        }
        
        return $stats;
    }
    
    /* ================================================================== */
    /*  PRIVATE HELPERS                                                  */
    /* ================================================================== */
    
    private function _generate_bill_no()
    {
        $prefix = 'BILL-' . date('Ymd');
        $this->db->select('MAX(CAST(SUBSTRING(bill_no, -4) AS UNSIGNED)) as max_num');
        $this->db->like('bill_no', $prefix, 'after');
        $result = $this->db->get('billing_master')->row();
        
        $num = ($result && $result->max_num) ? $result->max_num + 1 : 1;
        return $prefix . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
    
    private function _generate_payment_no()
    {
        $prefix = 'RCP-' . date('Ymd');
        $this->db->select('MAX(CAST(SUBSTRING(payment_no, -4) AS UNSIGNED)) as max_num');
        $this->db->like('payment_no', $prefix, 'after');
        $result = $this->db->get('billing_payments')->row();
        
        $num = ($result && $result->max_num) ? $result->max_num + 1 : 1;
        return $prefix . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
    
    private function _calculate_totals($items)
    {
        $total = 0;
        $discount = 0;
        $tax = 0;
        
        foreach ($items as $item) {
            $gross = ($item['quantity'] ?? 1) * ($item['unit_price'] ?? 0);
            $item_discount = $item['discount_amount'] ?? 0;
            $item_tax = $item['tax_amount'] ?? 0;
            
            $total += $gross;
            $discount += $item_discount;
            $tax += $item_tax;
        }
        
        return [
            'total' => $total,
            'discount' => $discount,
            'tax' => $tax,
            'net' => $total - $discount + $tax
        ];
    }
    
    private function _add_bill_item($bill_id, $item, $user_id, $company_id = null)
    {
        $quantity = $item['quantity'] ?? 1;
        $base_unit_price = $item['unit_price'] ?? 0;
        $discount = $item['discount_amount'] ?? 0;
        
        // Apply company pricing if applicable
        $pricing = $this->apply_company_pricing($base_unit_price, $company_id);
        $unit_price = $pricing['adjusted_amount'];
        $adjustment_percentage = $pricing['percentage_applied'];
        
        $gross = $quantity * $unit_price;
        $net = $gross - $discount;
        
        // Determine if this service needs a gate
        $needs_gate = in_array($item['service_type'], [
            self::SERVICE_LABORATORY,
            self::SERVICE_RADIOLOGY,
            self::SERVICE_SONOGRAPHY,
            self::SERVICE_PHARMACY,
            self::SERVICE_PROCEDURE
        ]);
        
        $item_data = [
            'bill_id' => $bill_id,
            'service_type' => $item['service_type'],
            'service_id' => $item['service_id'] ?? null,
            'service_code' => $item['service_code'] ?? null,
            'service_name' => $item['service_name'],
            'department' => $item['department'],
            'requested_by' => $item['requested_by'] ?? $user_id,
            'requested_at' => $item['requested_at'] ?? date('Y-m-d H:i:s'),
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'gross_amount' => $gross,
            'discount_amount' => $discount,
            'discount_reason' => $item['discount_reason'] ?? null,
            'discount_approved_by' => $item['discount_approved_by'] ?? null,
            'net_amount' => $net,
            // Company Pricing Audit Fields
            'base_price' => $base_unit_price,
            'adjusted_price' => $unit_price,
            'company_id' => $company_id,
            'adjustment_percentage' => $adjustment_percentage,
            'gate_status' => $needs_gate ? self::GATE_BLOCKED : self::GATE_RELEASED,
            'source_module' => $item['source_module'] ?? null,
            'source_ref_id' => $item['source_ref_id'] ?? null,
            'source_ref_table' => $item['source_ref_table'] ?? null
        ];
        
        $this->db->insert('billing_items', $item_data);
        return $this->db->insert_id();
    }

    /**
     * Centralized Company Pricing Service
     * Applies company pricing percentage to base amount
     * 
     * @param float $base_amount The original price
     * @param int|null $company_id The insurance/company ID
     * @return array Pricing breakdown with base, adjusted, percentage, difference
     */
    public function apply_company_pricing($base_amount, $company_id = null)
    {
        $base_amount = (float)$base_amount;
        if (empty($company_id) || empty($base_amount)) {
            return [
                'base_amount' => $base_amount,
                'adjusted_amount' => $base_amount,
                'percentage_applied' => 0.00,
                'difference' => 0.00,
                'company_id' => $company_id
            ];
        }
        // Delegate to Price_engine_model (Single Source of Truth for pricing math).
        $this->load->model('app/Price_engine_model', 'price_engine_model');
        return $this->price_engine_model->apply_company_pricing($base_amount, (int)$company_id);
    }
    
    private function _recalculate_bill($bill_id)
    {
        $this->db->select('SUM(net_amount) as total, SUM(discount_amount) as discount');
        $this->db->where('bill_id', $bill_id);
        $this->db->where('InActive', 0);
        $result = $this->db->get('billing_items')->row();
        
        $total = $result->total ?? 0;
        $discount = $result->discount ?? 0;
        
        // Get current paid amount
        $bill = $this->db->get_where('billing_master', ['bill_id' => $bill_id])->row();
        $paid = $bill->paid_amount ?? 0;
        $balance = $total - $paid;
        
        // Determine new status
        if ($balance <= 0) {
            $status = ($balance < 0) ? self::STATUS_OVERPAID : self::STATUS_PAID;
        } else {
            $status = ($paid > 0) ? self::STATUS_PARTIAL : self::STATUS_PENDING;
        }
        
        $this->db->where('bill_id', $bill_id);
        $this->db->update('billing_master', [
            'total_amount' => $total + $discount,
            'discount_amount' => $discount,
            'net_amount' => $total,
            'balance_due' => max(0, $balance),
            'payment_status' => $status
        ]);
    }
    
    private function _release_service_gates($bill_id, $user_id)
    {
        $this->db->where('bill_id', $bill_id);
        $this->db->where('gate_status', self::GATE_BLOCKED);
        $this->db->where('InActive', 0);
        $blocked_items = $this->db->get('billing_items')->result();
        
        foreach ($blocked_items as $item) {
            $this->release_service_gate($item->item_id, $user_id);
        }
    }
    
    private function _get_payer_info($patient_no)
    {
        $this->db->select('patient_type, Insurance_comp');
        $patient = $this->db->get_where('patient_personal_info', ['patient_no' => $patient_no])->row();
        
        if (!$patient) {
            return ['payer_type' => 'CASH'];
        }
        
        $type = strtoupper($patient->patient_type ?? 'CASH');
        
        if ($type === 'NHIS') {
            return ['payer_type' => 'NHIS'];
        } elseif (in_array($type, ['INSURANCE', 'CORPORATE', 'COMPANY'])) {
            return [
                'payer_type' => 'INSURANCE',
                'insurance_id' => $patient->Insurance_comp
            ];
        }
        
        return ['payer_type' => 'CASH'];
    }
    
    private function _log_audit($bill_id, $item_id, $payment_id, $action, $description, $old_values, $new_values, $user_id)
    {
        $this->db->insert('billing_audit_log', [
            'bill_id' => $bill_id,
            'item_id' => $item_id,
            'payment_id' => $payment_id,
            'action' => $action,
            'description' => $description,
            'old_values' => $old_values ? json_encode($old_values) : null,
            'new_values' => $new_values ? json_encode($new_values) : null,
            'performed_by' => $user_id,
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->input->ip_address()
        ]);
    }
}
