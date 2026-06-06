<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing Audit Model
 * Financial Audit Trail System
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class Billing_audit_model extends CI_Model
{
    // Action constants
    const ACTION_INVOICE_CREATED = 'INVOICE_CREATED';
    const ACTION_INVOICE_UPDATED = 'INVOICE_UPDATED';
    const ACTION_INVOICE_DELETED = 'INVOICE_DELETED';
    const ACTION_INVOICE_CANCELLED = 'INVOICE_CANCELLED';
    const ACTION_PAYMENT_COLLECTED = 'PAYMENT_COLLECTED';
    const ACTION_PAYMENT_EDITED = 'PAYMENT_EDITED';
    const ACTION_PAYMENT_DELETED = 'PAYMENT_DELETED';
    const ACTION_REFUND_ISSUED = 'REFUND_ISSUED';
    const ACTION_REFUND_APPROVED = 'REFUND_APPROVED';
    const ACTION_REFUND_REJECTED = 'REFUND_REJECTED';
    const ACTION_DISCOUNT_APPLIED = 'DISCOUNT_APPLIED';
    const ACTION_DISCOUNT_APPROVED = 'DISCOUNT_APPROVED';
    const ACTION_DISCOUNT_REJECTED = 'DISCOUNT_REJECTED';
    const ACTION_RECONCILIATION_COMPLETED = 'RECONCILIATION_COMPLETED';
    const ACTION_GATE_BYPASSED = 'GATE_BYPASSED';
    const ACTION_SETTINGS_CHANGED = 'SETTINGS_CHANGED';
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('schema_guard');
        if (!schema_already_run('billing_audit_schema')) {
            $this->ensure_schema();
            mark_schema_run('billing_audit_schema');
        }
    }
    
    /**
     * Ensure audit tables exist
     */
    public function ensure_schema()
    {
        // Main audit log table
        if (!$this->db->table_exists('billing_audit_log')) {
            $sql = "CREATE TABLE `billing_audit_log` (
                `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
                `user_id` INT(11) NOT NULL,
                `username` VARCHAR(100) NULL,
                `user_role` VARCHAR(50) NULL,
                `action` VARCHAR(50) NOT NULL,
                `entity_type` VARCHAR(50) NULL COMMENT 'invoice, payment, refund, etc.',
                `entity_id` VARCHAR(50) NULL,
                `invoice_id` INT(11) NULL,
                `invoice_no` VARCHAR(50) NULL,
                `patient_no` VARCHAR(20) NULL,
                `amount` DECIMAL(15,2) DEFAULT 0,
                `previous_amount` DECIMAL(15,2) NULL,
                `new_amount` DECIMAL(15,2) NULL,
                `payment_method` VARCHAR(50) NULL,
                `description` TEXT NULL,
                `metadata` JSON NULL COMMENT 'Additional context data',
                `ip_address` VARCHAR(45) NULL,
                `user_agent` VARCHAR(255) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`),
                KEY `action` (`action`),
                KEY `entity_type` (`entity_type`),
                KEY `invoice_id` (`invoice_id`),
                KEY `patient_no` (`patient_no`),
                KEY `created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
        
        // Department revenue tracking table
        if (!$this->db->table_exists('billing_department_revenue')) {
            $sql = "CREATE TABLE `billing_department_revenue` (
                `id` BIGINT(20) NOT NULL AUTO_INCREMENT,
                `department` VARCHAR(100) NOT NULL,
                `department_code` VARCHAR(50) NULL,
                `invoice_id` INT(11) NULL,
                `txn_id` INT(11) NULL,
                `patient_no` VARCHAR(20) NULL,
                `amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `payment_method` VARCHAR(50) NULL,
                `revenue_date` DATE NOT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `department` (`department`),
                KEY `revenue_date` (`revenue_date`),
                KEY `invoice_id` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
        
        // Payment breakdown table for split payments
        if (!$this->db->table_exists('payment_breakdown')) {
            $sql = "CREATE TABLE `payment_breakdown` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `payment_id` INT(11) NOT NULL,
                `invoice_id` INT(11) NOT NULL,
                `payment_method` VARCHAR(50) NOT NULL,
                `amount` DECIMAL(15,2) NOT NULL,
                `reference_no` VARCHAR(100) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `payment_id` (`payment_id`),
                KEY `invoice_id` (`invoice_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
        
        // Billing refunds table
        if (!$this->db->table_exists('billing_refunds')) {
            $sql = "CREATE TABLE `billing_refunds` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `refund_no` VARCHAR(50) NOT NULL,
                `invoice_id` INT(11) NOT NULL,
                `invoice_no` VARCHAR(50) NULL,
                `payment_id` INT(11) NULL,
                `patient_no` VARCHAR(20) NOT NULL,
                `amount` DECIMAL(15,2) NOT NULL,
                `reason` TEXT NOT NULL,
                `refund_method` VARCHAR(50) DEFAULT 'CASH',
                `status` ENUM('PENDING', 'APPROVED', 'REJECTED', 'COMPLETED') DEFAULT 'PENDING',
                `requested_by` INT(11) NOT NULL,
                `approved_by` INT(11) NULL,
                `approved_at` TIMESTAMP NULL,
                `rejection_reason` TEXT NULL,
                `completed_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `refund_no` (`refund_no`),
                KEY `invoice_id` (`invoice_id`),
                KEY `patient_no` (`patient_no`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
        
        // Billing discounts table
        if (!$this->db->table_exists('billing_discounts')) {
            $sql = "CREATE TABLE `billing_discounts` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `invoice_id` INT(11) NOT NULL,
                `invoice_no` VARCHAR(50) NULL,
                `patient_no` VARCHAR(20) NOT NULL,
                `discount_type` ENUM('PERCENTAGE', 'FIXED') DEFAULT 'FIXED',
                `discount_value` DECIMAL(15,2) NOT NULL,
                `discount_amount` DECIMAL(15,2) NOT NULL,
                `original_amount` DECIMAL(15,2) NOT NULL,
                `final_amount` DECIMAL(15,2) NOT NULL,
                `reason` TEXT NOT NULL,
                `status` ENUM('PENDING', 'APPROVED', 'REJECTED') DEFAULT 'PENDING',
                `requested_by` INT(11) NOT NULL,
                `approved_by` INT(11) NULL,
                `approved_at` TIMESTAMP NULL,
                `rejection_reason` TEXT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `invoice_id` (`invoice_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
        
        // Billing reconciliation table
        if (!$this->db->table_exists('billing_reconciliation')) {
            $sql = "CREATE TABLE `billing_reconciliation` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `reconciliation_date` DATE NOT NULL,
                `reconciliation_type` ENUM('DAILY', 'CASHIER', 'DEPARTMENT') DEFAULT 'DAILY',
                `user_id` INT(11) NULL COMMENT 'For cashier reconciliation',
                `department` VARCHAR(100) NULL COMMENT 'For department reconciliation',
                `expected_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `actual_amount` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `difference` DECIMAL(15,2) NOT NULL DEFAULT 0,
                `cash_expected` DECIMAL(15,2) DEFAULT 0,
                `cash_actual` DECIMAL(15,2) DEFAULT 0,
                `momo_expected` DECIMAL(15,2) DEFAULT 0,
                `momo_actual` DECIMAL(15,2) DEFAULT 0,
                `card_expected` DECIMAL(15,2) DEFAULT 0,
                `card_actual` DECIMAL(15,2) DEFAULT 0,
                `status` ENUM('PENDING', 'BALANCED', 'SHORTAGE', 'OVERAGE') DEFAULT 'PENDING',
                `notes` TEXT NULL,
                `reconciled_by` INT(11) NOT NULL,
                `approved_by` INT(11) NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `reconciliation_date` (`reconciliation_date`),
                KEY `user_id` (`user_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
        
        // Billing notifications table
        if (!$this->db->table_exists('billing_notifications')) {
            $sql = "CREATE TABLE `billing_notifications` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `notification_type` VARCHAR(50) NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `message` TEXT NOT NULL,
                `severity` ENUM('INFO', 'WARNING', 'CRITICAL') DEFAULT 'INFO',
                `entity_type` VARCHAR(50) NULL,
                `entity_id` INT(11) NULL,
                `target_role` VARCHAR(50) NULL COMMENT 'Which role should see this',
                `target_user_id` INT(11) NULL COMMENT 'Specific user',
                `is_read` TINYINT(1) DEFAULT 0,
                `read_by` INT(11) NULL,
                `read_at` TIMESTAMP NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `notification_type` (`notification_type`),
                KEY `target_role` (`target_role`),
                KEY `target_user_id` (`target_user_id`),
                KEY `is_read` (`is_read`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->db->query($sql);
        }
    }
    
    /**
     * Log a billing action
     * 
     * @param string $action Action constant
     * @param array $data Additional data
     * @return int|bool Insert ID or false
     */
    public function log($action, $data = [])
    {
        $user_id = $this->session->userdata('user_id') ?? 0;
        $username = $this->session->userdata('username') ?? 'SYSTEM';
        
        // Get user role
        $user_role = null;
        if ($this->load->is_loaded('billingauth')) {
            $user_role = $this->billingauth->get_role();
        }
        
        $log_data = [
            'user_id' => $user_id,
            'username' => $username,
            'user_role' => $user_role,
            'action' => $action,
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'invoice_no' => $data['invoice_no'] ?? null,
            'patient_no' => $data['patient_no'] ?? null,
            'amount' => $data['amount'] ?? 0,
            'previous_amount' => $data['previous_amount'] ?? null,
            'new_amount' => $data['new_amount'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'description' => $data['description'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr($this->input->user_agent(), 0, 255)
        ];
        
        $this->db->insert('billing_audit_log', $log_data);
        return $this->db->insert_id();
    }
    
    /**
     * Get audit logs with filters
     * 
     * @param array $filters
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function get_logs($filters = [], $limit = 100, $offset = 0)
    {
        $this->db->select('a.*, u.firstname, u.lastname');
        $this->db->from('billing_audit_log a');
        $this->db->join('users u', 'u.user_id = a.user_id', 'left');
        
        if (!empty($filters['action'])) {
            $this->db->where('a.action', $filters['action']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('a.user_id', $filters['user_id']);
        }
        if (!empty($filters['invoice_id'])) {
            $this->db->where('a.invoice_id', $filters['invoice_id']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('a.patient_no', $filters['patient_no']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(a.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(a.created_at) <=', $filters['date_to']);
        }
        
        $this->db->order_by('a.created_at', 'DESC');
        $this->db->limit($limit, $offset);
        
        return $this->db->get()->result();
    }
    
    /**
     * Get audit log count
     */
    public function count_logs($filters = [])
    {
        $this->db->from('billing_audit_log a');
        
        if (!empty($filters['action'])) {
            $this->db->where('a.action', $filters['action']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('a.user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(a.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(a.created_at) <=', $filters['date_to']);
        }
        
        return $this->db->count_all_results();
    }
    
    /**
     * Track department revenue
     */
    public function track_department_revenue($department, $amount, $data = [])
    {
        $revenue_data = [
            'department' => $department,
            'department_code' => $data['department_code'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'txn_id' => $data['txn_id'] ?? null,
            'patient_no' => $data['patient_no'] ?? null,
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? null,
            'revenue_date' => $data['revenue_date'] ?? date('Y-m-d')
        ];
        
        $this->db->insert('billing_department_revenue', $revenue_data);
        return $this->db->insert_id();
    }
    
    /**
     * Get department revenue summary
     */
    public function get_department_revenue($date_from, $date_to)
    {
        $sql = "SELECT 
                    department,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_revenue,
                    AVG(amount) as avg_transaction
                FROM billing_department_revenue
                WHERE revenue_date BETWEEN ? AND ?
                GROUP BY department
                ORDER BY total_revenue DESC";
        
        return $this->db->query($sql, [$date_from, $date_to])->result();
    }
    
    /**
     * Create a refund request
     */
    public function create_refund_request($data)
    {
        // Generate refund number
        $refund_no = 'REF-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        $refund_data = [
            'refund_no' => $refund_no,
            'invoice_id' => $data['invoice_id'],
            'invoice_no' => $data['invoice_no'] ?? null,
            'payment_id' => $data['payment_id'] ?? null,
            'patient_no' => $data['patient_no'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'refund_method' => $data['refund_method'] ?? 'CASH',
            'status' => 'PENDING',
            'requested_by' => $this->session->userdata('user_id')
        ];
        
        $this->db->insert('billing_refunds', $refund_data);
        $refund_id = $this->db->insert_id();
        
        // Log the action
        $this->log(self::ACTION_REFUND_ISSUED, [
            'entity_type' => 'refund',
            'entity_id' => $refund_id,
            'invoice_id' => $data['invoice_id'],
            'patient_no' => $data['patient_no'],
            'amount' => $data['amount'],
            'description' => 'Refund requested: ' . $data['reason']
        ]);
        
        // Create notification
        $this->create_notification([
            'type' => 'REFUND_REQUEST',
            'title' => 'New Refund Request',
            'message' => "Refund of " . number_format($data['amount'], 2) . " requested for invoice " . ($data['invoice_no'] ?? $data['invoice_id']),
            'severity' => 'WARNING',
            'entity_type' => 'refund',
            'entity_id' => $refund_id,
            'target_role' => 'finance_manager'
        ]);
        
        return $refund_id;
    }
    
    /**
     * Approve refund
     */
    public function approve_refund($refund_id)
    {
        $this->db->where('id', $refund_id);
        $this->db->update('billing_refunds', [
            'status' => 'APPROVED',
            'approved_by' => $this->session->userdata('user_id'),
            'approved_at' => date('Y-m-d H:i:s')
        ]);
        
        $refund = $this->db->get_where('billing_refunds', ['id' => $refund_id])->row();
        
        $this->log(self::ACTION_REFUND_APPROVED, [
            'entity_type' => 'refund',
            'entity_id' => $refund_id,
            'invoice_id' => $refund->invoice_id,
            'amount' => $refund->amount,
            'description' => 'Refund approved'
        ]);
        
        return true;
    }
    
    /**
     * Reject refund
     */
    public function reject_refund($refund_id, $reason)
    {
        $this->db->where('id', $refund_id);
        $this->db->update('billing_refunds', [
            'status' => 'REJECTED',
            'approved_by' => $this->session->userdata('user_id'),
            'approved_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason
        ]);
        
        $refund = $this->db->get_where('billing_refunds', ['id' => $refund_id])->row();
        
        $this->log(self::ACTION_REFUND_REJECTED, [
            'entity_type' => 'refund',
            'entity_id' => $refund_id,
            'invoice_id' => $refund->invoice_id,
            'amount' => $refund->amount,
            'description' => 'Refund rejected: ' . $reason
        ]);
        
        return true;
    }
    
    /**
     * Get pending refunds
     */
    public function get_pending_refunds()
    {
        $this->db->select('r.*, p.firstname, p.lastname, u.username as requested_by_name');
        $this->db->from('billing_refunds r');
        $this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
        $this->db->join('users u', 'u.user_id = r.requested_by', 'left');
        $this->db->where('r.status', 'PENDING');
        $this->db->order_by('r.created_at', 'DESC');
        
        return $this->db->get()->result();
    }
    
    /**
     * Create discount request
     */
    public function create_discount_request($data)
    {
        $discount_data = [
            'invoice_id' => $data['invoice_id'],
            'invoice_no' => $data['invoice_no'] ?? null,
            'patient_no' => $data['patient_no'],
            'discount_type' => $data['discount_type'] ?? 'FIXED',
            'discount_value' => $data['discount_value'],
            'discount_amount' => $data['discount_amount'],
            'original_amount' => $data['original_amount'],
            'final_amount' => $data['final_amount'],
            'reason' => $data['reason'],
            'status' => 'PENDING',
            'requested_by' => $this->session->userdata('user_id')
        ];
        
        $this->db->insert('billing_discounts', $discount_data);
        $discount_id = $this->db->insert_id();
        
        $this->log(self::ACTION_DISCOUNT_APPLIED, [
            'entity_type' => 'discount',
            'entity_id' => $discount_id,
            'invoice_id' => $data['invoice_id'],
            'patient_no' => $data['patient_no'],
            'amount' => $data['discount_amount'],
            'description' => 'Discount requested: ' . $data['reason']
        ]);
        
        // Create notification for large discounts
        if ($data['discount_amount'] > 100) {
            $this->create_notification([
                'type' => 'LARGE_DISCOUNT',
                'title' => 'Large Discount Request',
                'message' => "Discount of " . number_format($data['discount_amount'], 2) . " requested",
                'severity' => 'WARNING',
                'entity_type' => 'discount',
                'entity_id' => $discount_id,
                'target_role' => 'finance_manager'
            ]);
        }
        
        return $discount_id;
    }
    
    /**
     * Create billing notification
     */
    public function create_notification($data)
    {
        $notification = [
            'notification_type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'severity' => $data['severity'] ?? 'INFO',
            'entity_type' => $data['entity_type'] ?? null,
            'entity_id' => $data['entity_id'] ?? null,
            'target_role' => $data['target_role'] ?? null,
            'target_user_id' => $data['target_user_id'] ?? null
        ];
        
        $this->db->insert('billing_notifications', $notification);
        return $this->db->insert_id();
    }
    
    /**
     * Get unread notifications for current user
     */
    public function get_notifications($user_id = null, $role = null, $limit = 20)
    {
        $this->db->from('billing_notifications');
        $this->db->where('is_read', 0);
        
        if ($user_id) {
            $this->db->group_start();
            $this->db->where('target_user_id', $user_id);
            $this->db->or_where('target_user_id IS NULL');
            $this->db->group_end();
        }
        
        if ($role) {
            $this->db->group_start();
            $this->db->where('target_role', $role);
            $this->db->or_where('target_role IS NULL');
            $this->db->group_end();
        }
        
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }
    
    /**
     * Mark notification as read
     */
    public function mark_notification_read($notification_id)
    {
        $this->db->where('id', $notification_id);
        return $this->db->update('billing_notifications', [
            'is_read' => 1,
            'read_by' => $this->session->userdata('user_id'),
            'read_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Create reconciliation record
     */
    public function create_reconciliation($data)
    {
        $recon_data = [
            'reconciliation_date' => $data['date'] ?? date('Y-m-d'),
            'reconciliation_type' => $data['type'] ?? 'DAILY',
            'user_id' => $data['user_id'] ?? null,
            'department' => $data['department'] ?? null,
            'expected_amount' => $data['expected_amount'] ?? 0,
            'actual_amount' => $data['actual_amount'] ?? 0,
            'difference' => ($data['actual_amount'] ?? 0) - ($data['expected_amount'] ?? 0),
            'cash_expected' => $data['cash_expected'] ?? 0,
            'cash_actual' => $data['cash_actual'] ?? 0,
            'momo_expected' => $data['momo_expected'] ?? 0,
            'momo_actual' => $data['momo_actual'] ?? 0,
            'card_expected' => $data['card_expected'] ?? 0,
            'card_actual' => $data['card_actual'] ?? 0,
            'notes' => $data['notes'] ?? null,
            'reconciled_by' => $this->session->userdata('user_id')
        ];
        
        // Determine status
        $diff = $recon_data['difference'];
        if ($diff == 0) {
            $recon_data['status'] = 'BALANCED';
        } elseif ($diff < 0) {
            $recon_data['status'] = 'SHORTAGE';
        } else {
            $recon_data['status'] = 'OVERAGE';
        }
        
        $this->db->insert('billing_reconciliation', $recon_data);
        $recon_id = $this->db->insert_id();
        
        $this->log(self::ACTION_RECONCILIATION_COMPLETED, [
            'entity_type' => 'reconciliation',
            'entity_id' => $recon_id,
            'amount' => $recon_data['actual_amount'],
            'previous_amount' => $recon_data['expected_amount'],
            'description' => "Reconciliation completed. Status: {$recon_data['status']}, Difference: {$diff}"
        ]);
        
        // Alert on shortage
        if ($recon_data['status'] === 'SHORTAGE' && abs($diff) > 50) {
            $this->create_notification([
                'type' => 'CASHIER_SHORTAGE',
                'title' => 'Cashier Shortage Alert',
                'message' => "Shortage of " . number_format(abs($diff), 2) . " detected",
                'severity' => 'CRITICAL',
                'entity_type' => 'reconciliation',
                'entity_id' => $recon_id,
                'target_role' => 'finance_manager'
            ]);
        }
        
        return $recon_id;
    }
    
    /**
     * Get reconciliation history
     */
    public function get_reconciliation_history($filters = [], $limit = 50)
    {
        $this->db->select('r.*, u.firstname, u.lastname, u.username');
        $this->db->from('billing_reconciliation r');
        $this->db->join('users u', 'u.user_id = r.reconciled_by', 'left');
        
        if (!empty($filters['type'])) {
            $this->db->where('r.reconciliation_type', $filters['type']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('r.user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('r.reconciliation_date >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('r.reconciliation_date <=', $filters['date_to']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('r.status', $filters['status']);
        }
        
        $this->db->order_by('r.reconciliation_date', 'DESC');
        $this->db->limit($limit);
        
        return $this->db->get()->result();
    }
}
