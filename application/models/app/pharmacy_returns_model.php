<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pharmacy Returns Model
 * 
 * Handles drug returns workflow:
 * - Return request creation
 * - Approval/rejection workflow
 * - Stock adjustment on approval
 * - Audit logging
 * - Batch-level returns
 * - Financial reversal
 * - Return window validation
 * 
 * Part of Phase 2 Clinical Safety Fixes + Hardening.
 * 
 * @author HMS Enterprise Architect
 * @version 2.0
 */
class Pharmacy_returns_model extends CI_Model
{
    private static $_schema_done = false;
    
    const STATUS_PENDING = 'PENDING';
    const STATUS_APPROVED = 'APPROVED';
    const STATUS_REJECTED = 'REJECTED';
    
    const RETURN_TYPE_PATIENT = 'PATIENT_RETURN';
    const RETURN_TYPE_WARD = 'WARD_RETURN';
    const RETURN_TYPE_INTERNAL = 'INTERNAL_CORRECTION';
    
    const DEFAULT_RETURN_WINDOW_HOURS = 48;
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('schema_guard');
        if (!schema_already_run('pharmacy_returns_schema')) {
            $this->ensure_returns_schema();
            mark_schema_run('pharmacy_returns_schema');
        }
    }
    
    // =========================================================================
    // SCHEMA MANAGEMENT
    // =========================================================================
    
    /**
     * Ensure returns tables exist
     */
    public function ensure_returns_schema()
    {
        if (self::$_schema_done) return;
        self::$_schema_done = true;
        
        // Create pharmacy_returns table
        if (!$this->table_exists('pharmacy_returns')) {
            $this->db->query("
                CREATE TABLE `pharmacy_returns` (
                    `return_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `return_number` VARCHAR(25) UNIQUE NOT NULL,
                    `admin_id` INT NOT NULL,
                    `iop_med_id` INT NOT NULL,
                    `patient_no` VARCHAR(25) NOT NULL,
                    `drug_id` INT NOT NULL,
                    `quantity_dispensed` DECIMAL(10,2) NOT NULL,
                    `quantity_returned` DECIMAL(10,2) NOT NULL,
                    `return_reason` VARCHAR(50) NOT NULL,
                    `return_notes` TEXT,
                    `return_date` DATE NOT NULL,
                    `status` ENUM('PENDING','APPROVED','REJECTED') DEFAULT 'PENDING',
                    `return_type` ENUM('PATIENT_RETURN','WARD_RETURN','INTERNAL_CORRECTION') DEFAULT 'PATIENT_RETURN',
                    `batch_no` VARCHAR(50) DEFAULT NULL,
                    `expiry_date` DATE DEFAULT NULL,
                    `stock_location` VARCHAR(50) DEFAULT 'MAIN',
                    `requested_by` VARCHAR(25) NOT NULL,
                    `approved_by` VARCHAR(25) DEFAULT NULL,
                    `approved_date` DATETIME DEFAULT NULL,
                    `rejection_reason` TEXT DEFAULT NULL,
                    `stock_adjusted` TINYINT(1) DEFAULT 0,
                    `billing_reversed` TINYINT(1) DEFAULT 0,
                    `claim_reversed` TINYINT(1) DEFAULT 0,
                    `reversal_amount` DECIMAL(18,2) DEFAULT 0,
                    `reversal_date` DATETIME DEFAULT NULL,
                    `window_override` TINYINT(1) DEFAULT 0,
                    `window_override_by` VARCHAR(25) DEFAULT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                    `InActive` TINYINT(1) DEFAULT 0,
                    KEY `idx_patient` (`patient_no`),
                    KEY `idx_drug` (`drug_id`),
                    KEY `idx_status` (`status`),
                    KEY `idx_date` (`return_date`),
                    KEY `idx_admin` (`admin_id`),
                    KEY `idx_batch` (`batch_no`),
                    KEY `idx_return_type` (`return_type`),
                    KEY `idx_created_at` (`created_at`),
                    KEY `idx_status_date` (`status`, `return_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Create pharmacy_return_audit table
        if (!$this->table_exists('pharmacy_return_audit')) {
            $this->db->query("
                CREATE TABLE `pharmacy_return_audit` (
                    `audit_id` INT AUTO_INCREMENT PRIMARY KEY,
                    `return_id` INT NOT NULL,
                    `action` VARCHAR(30) NOT NULL,
                    `user_id` VARCHAR(25) NOT NULL,
                    `previous_status` VARCHAR(20),
                    `new_status` VARCHAR(20),
                    `previous_values` JSON,
                    `new_values` JSON,
                    `notes` TEXT,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    KEY `idx_return` (`return_id`),
                    KEY `idx_user` (`user_id`),
                    KEY `idx_action` (`action`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
        
        // Phase 2 Hardening: Add new columns if table exists
        $this->ensure_hardening_columns();
    }
    
    /**
     * Phase 2 Hardening: Add new columns safely
     */
    private function ensure_hardening_columns()
    {
        if (!$this->table_exists('pharmacy_returns')) return;
        
        // Task 2: Batch-level returns
        if (!$this->column_exists('pharmacy_returns', 'batch_no')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `batch_no` VARCHAR(50) DEFAULT NULL AFTER `stock_location`");
        }
        if (!$this->column_exists('pharmacy_returns', 'expiry_date')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `expiry_date` DATE DEFAULT NULL AFTER `batch_no`");
        }
        if (!$this->column_exists('pharmacy_returns', 'stock_location')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `stock_location` VARCHAR(50) DEFAULT 'MAIN' AFTER `expiry_date`");
        }
        
        // Task 3: Return type classification
        if (!$this->column_exists('pharmacy_returns', 'return_type')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `return_type` ENUM('PATIENT_RETURN','WARD_RETURN','INTERNAL_CORRECTION') DEFAULT 'PATIENT_RETURN' AFTER `status`");
        }
        
        // Task 6: Financial reversal
        if (!$this->column_exists('pharmacy_returns', 'billing_reversed')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `billing_reversed` TINYINT(1) DEFAULT 0 AFTER `stock_adjusted`");
        }
        if (!$this->column_exists('pharmacy_returns', 'claim_reversed')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `claim_reversed` TINYINT(1) DEFAULT 0 AFTER `billing_reversed`");
        }
        if (!$this->column_exists('pharmacy_returns', 'reversal_amount')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `reversal_amount` DECIMAL(18,2) DEFAULT 0 AFTER `claim_reversed`");
        }
        if (!$this->column_exists('pharmacy_returns', 'reversal_date')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `reversal_date` DATETIME DEFAULT NULL AFTER `reversal_amount`");
        }
        
        // Task 7: Return window override
        if (!$this->column_exists('pharmacy_returns', 'window_override')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `window_override` TINYINT(1) DEFAULT 0 AFTER `reversal_date`");
        }
        if (!$this->column_exists('pharmacy_returns', 'window_override_by')) {
            $this->db->query("ALTER TABLE `pharmacy_returns` ADD COLUMN `window_override_by` VARCHAR(25) DEFAULT NULL AFTER `window_override`");
        }
        
        // Task 8: Performance indexes (safe creation)
        $this->ensure_index('pharmacy_returns', 'idx_batch', 'batch_no');
        $this->ensure_index('pharmacy_returns', 'idx_return_type', 'return_type');
        $this->ensure_index('pharmacy_returns', 'idx_created_at', 'created_at');
    }
    
    /**
     * Check if column exists
     */
    private function column_exists($table, $column)
    {
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $result && $result->num_rows() > 0;
    }
    
    /**
     * Ensure index exists
     */
    private function ensure_index($table, $index_name, $column)
    {
        $result = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index_name}'");
        if (!$result || $result->num_rows() == 0) {
            $this->db->query("CREATE INDEX `{$index_name}` ON `{$table}` (`{$column}`)");
        }
    }
    
    /**
     * Check if table exists
     */
    private function table_exists($table)
    {
        return $this->db->table_exists($table);
    }
    
    // =========================================================================
    // RETURN NUMBER GENERATION
    // =========================================================================
    
    /**
     * Generate unique return number (Thread-safe)
     * Format: RET-YYYYMMDD-XXXXX (5 digits, daily reset)
     * Phase 2 Hardening: Task 1
     */
    public function generate_return_number()
    {
        $prefix = 'RET-' . date('Ymd') . '-';
        
        // Use SELECT FOR UPDATE for thread-safe generation
        $this->db->trans_start();
        
        // Get last return number for today with row lock
        $sql = "SELECT return_number FROM pharmacy_returns 
                WHERE return_number LIKE '{$prefix}%' 
                ORDER BY return_id DESC 
                LIMIT 1 
                FOR UPDATE";
        $q = $this->db->query($sql);
        $row = $q ? $q->row() : null;
        
        if ($row && !empty($row->return_number)) {
            $parts = explode('-', $row->return_number);
            $seq = isset($parts[2]) ? (int)$parts[2] : 0;
            $seq++;
        } else {
            $seq = 1;
        }
        
        $this->db->trans_complete();
        
        // 5-digit sequence for enterprise scale (up to 99,999 returns/day)
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }
    
    // =========================================================================
    // RETURN REASONS
    // =========================================================================
    
    /**
     * Get return reasons dropdown
     */
    public function get_return_reasons()
    {
        return array(
            'OVER_DISPENSED'        => 'Over-dispensed',
            'PATIENT_REFUSED'       => 'Patient refused',
            'WRONG_DRUG'            => 'Wrong drug dispensed',
            'EXPIRED_DRUG'          => 'Expired drug',
            'DAMAGED_DRUG'          => 'Damaged drug',
            'PRESCRIPTION_CANCELLED'=> 'Prescription cancelled',
            'ADVERSE_REACTION'      => 'Adverse reaction',
            'OTHER'                 => 'Other'
        );
    }
    
    /**
     * Get return types dropdown
     * Phase 2 Hardening: Task 3
     */
    public function get_return_types()
    {
        return array(
            self::RETURN_TYPE_PATIENT  => 'Patient Return',
            self::RETURN_TYPE_WARD     => 'Ward Return',
            self::RETURN_TYPE_INTERNAL => 'Internal Correction'
        );
    }
    
    /**
     * Get stock locations dropdown
     * Phase 2 Hardening: Task 2
     */
    public function get_stock_locations()
    {
        return array(
            'MAIN'      => 'Main Pharmacy',
            'EMERGENCY' => 'Emergency Pharmacy',
            'OPD'       => 'OPD Pharmacy',
            'IPD'       => 'IPD Pharmacy',
            'STORE'     => 'Central Store'
        );
    }
    
    // =========================================================================
    // DISPENSE RECORD QUERIES
    // =========================================================================
    
    /**
     * Get dispensed drugs available for return
     */
    public function get_dispensed_drugs_for_return($patient_no = '', $search = '')
    {
        $this->db->select('
            a.admin_id,
            a.iop_med_id,
            a.iop_id,
            a.dose_given,
            a.dDateTime as dispense_date,
            a.cPreparedBy as dispensed_by,
            m.medicine_id as drug_id,
            m.total_qty as prescribed_qty,
            m.patient_no,
            d.drug_name,
            d.generic_name,
            p.patient_name,
            COALESCE(r.total_returned, 0) as already_returned
        ', false);
        $this->db->from('iop_medication_administration a');
        $this->db->join('iop_medication m', 'm.iop_med_id = a.iop_med_id', 'left');
        $this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = m.patient_no', 'left');
        $this->db->join('(SELECT admin_id, SUM(quantity_returned) as total_returned FROM pharmacy_returns WHERE status != "REJECTED" AND InActive = 0 GROUP BY admin_id) r', 'r.admin_id = a.admin_id', 'left');
        
        $this->db->where('a.InActive', 0);
        $this->db->where('a.status', 'DISPENSED');
        $this->db->where('a.dose_given >', 0);
        
        if (!empty($patient_no)) {
            $this->db->where('m.patient_no', $patient_no);
        }
        
        if (!empty($search)) {
            $search = $this->db->escape_like_str($search);
            $this->db->group_start();
            $this->db->like('d.drug_name', $search);
            $this->db->or_like('p.patient_name', $search);
            $this->db->or_like('m.patient_no', $search);
            $this->db->group_end();
        }
        
        $this->db->having('dose_given > COALESCE(r.total_returned, 0)');
        $this->db->order_by('a.dDateTime', 'DESC');
        $this->db->limit(100);
        
        $q = $this->db->get();
        return $q ? $q->result() : array();
    }
    
    /**
     * Get specific dispense record
     */
    public function get_dispense_record($admin_id)
    {
        $this->db->select('
            a.admin_id,
            a.iop_med_id,
            a.iop_id,
            a.dose_given,
            a.dDateTime as dispense_date,
            a.cPreparedBy as dispensed_by,
            a.batch_no,
            m.medicine_id as drug_id,
            m.total_qty as prescribed_qty,
            m.patient_no,
            d.drug_name,
            d.generic_name,
            d.strength,
            d.nStock as current_stock,
            p.patient_name,
            COALESCE(r.total_returned, 0) as already_returned
        ', false);
        $this->db->from('iop_medication_administration a');
        $this->db->join('iop_medication m', 'm.iop_med_id = a.iop_med_id', 'left');
        $this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = m.patient_no', 'left');
        $this->db->join('(SELECT admin_id, SUM(quantity_returned) as total_returned FROM pharmacy_returns WHERE status != "REJECTED" AND InActive = 0 GROUP BY admin_id) r', 'r.admin_id = a.admin_id', 'left');
        
        $this->db->where('a.admin_id', (int)$admin_id);
        $this->db->where('a.InActive', 0);
        
        $q = $this->db->get();
        return $q ? $q->row() : null;
    }
    
    // =========================================================================
    // RETURN CRUD OPERATIONS
    // =========================================================================
    
    /**
     * Save new return request
     * Phase 2 Hardening: Added return_type, batch_no, expiry_date, stock_location, window validation
     */
    public function save_return_request($data)
    {
        // Validate dispense record
        $dispense = $this->get_dispense_record($data['admin_id']);
        if (!$dispense) {
            return array('success' => false, 'error' => 'Dispense record not found');
        }
        
        // Task 7: Validate return window
        $user_role = isset($data['user_role']) ? $data['user_role'] : '';
        $window_check = $this->validate_return_window($dispense->dispense_date, $user_role);
        if (!$window_check['allowed']) {
            return array('success' => false, 'error' => $window_check['message'], 'window_exceeded' => true);
        }
        
        // Calculate returnable quantity
        $returnable = (float)$dispense->dose_given - (float)$dispense->already_returned;
        if ($data['quantity_returned'] > $returnable) {
            return array('success' => false, 'error' => "Cannot return more than {$returnable} units");
        }
        
        if ($data['quantity_returned'] <= 0) {
            return array('success' => false, 'error' => 'Return quantity must be greater than 0');
        }
        
        // Generate return number
        $return_number = $this->generate_return_number();
        
        $insert_data = array(
            'return_number'      => $return_number,
            'admin_id'           => (int)$data['admin_id'],
            'iop_med_id'         => (int)$dispense->iop_med_id,
            'patient_no'         => $dispense->patient_no,
            'drug_id'            => (int)$dispense->drug_id,
            'quantity_dispensed' => (float)$dispense->dose_given,
            'quantity_returned'  => (float)$data['quantity_returned'],
            'return_reason'      => $data['return_reason'],
            'return_notes'       => isset($data['return_notes']) ? $data['return_notes'] : '',
            'return_date'        => date('Y-m-d'),
            'status'             => self::STATUS_PENDING,
            'requested_by'       => $data['user_id'],
            'created_at'         => date('Y-m-d H:i:s')
        );
        
        // Task 3: Return type classification
        if (isset($data['return_type']) && in_array($data['return_type'], array(self::RETURN_TYPE_PATIENT, self::RETURN_TYPE_WARD, self::RETURN_TYPE_INTERNAL))) {
            $insert_data['return_type'] = $data['return_type'];
        } else {
            $insert_data['return_type'] = self::RETURN_TYPE_PATIENT;
        }
        
        // Task 2: Batch-level returns
        if (!empty($data['batch_no'])) {
            $insert_data['batch_no'] = $data['batch_no'];
        } elseif (!empty($dispense->batch_no)) {
            $insert_data['batch_no'] = $dispense->batch_no;
        }
        
        if (!empty($data['expiry_date'])) {
            $insert_data['expiry_date'] = $data['expiry_date'];
        }
        
        if (!empty($data['stock_location'])) {
            $insert_data['stock_location'] = $data['stock_location'];
        }
        
        // Task 7: Window override tracking
        if ($window_check['override']) {
            $insert_data['window_override'] = 1;
            $insert_data['window_override_by'] = $data['user_id'];
        }
        
        $this->db->insert('pharmacy_returns', $insert_data);
        $return_id = $this->db->insert_id();
        
        if ($return_id > 0) {
            // Log audit
            $audit_notes = $window_check['override'] ? 'Admin override: Return window exceeded' : null;
            $this->log_return_audit($return_id, 'CREATE', $data['user_id'], array(
                'previous_status' => null,
                'new_status' => self::STATUS_PENDING,
                'new_values' => $insert_data,
                'notes' => $audit_notes
            ));
            
            return array('success' => true, 'return_id' => $return_id, 'return_number' => $return_number);
        }
        
        return array('success' => false, 'error' => 'Failed to create return request');
    }
    
    // =========================================================================
    // RETURN WINDOW VALIDATION (Task 7)
    // =========================================================================
    
    /**
     * Validate return window
     * Phase 2 Hardening: Task 7
     */
    public function validate_return_window($dispense_date, $user_role = '')
    {
        $window_hours = $this->get_return_window_hours();
        $dispense_time = strtotime($dispense_date);
        $current_time = time();
        $hours_elapsed = ($current_time - $dispense_time) / 3600;
        
        if ($hours_elapsed > $window_hours) {
            // Admin can override
            if (strtolower($user_role) === 'admin') {
                return array(
                    'allowed' => true, 
                    'override' => true, 
                    'message' => "Return window of {$window_hours} hours exceeded. Admin override applied.",
                    'hours_elapsed' => round($hours_elapsed, 1)
                );
            }
            return array(
                'allowed' => false, 
                'override' => false,
                'message' => "Return window of {$window_hours} hours exceeded. Dispensed " . round($hours_elapsed, 1) . " hours ago. Admin approval required.",
                'hours_elapsed' => round($hours_elapsed, 1)
            );
        }
        
        return array(
            'allowed' => true, 
            'override' => false, 
            'message' => 'Within return window',
            'hours_elapsed' => round($hours_elapsed, 1)
        );
    }
    
    /**
     * Get return window hours from config
     */
    public function get_return_window_hours()
    {
        // Check nhis_billing_config table
        if ($this->table_exists('nhis_billing_config')) {
            $q = $this->db->get_where('nhis_billing_config', array('config_key' => 'return_window_hours'));
            if ($q && $q->num_rows() > 0) {
                return (int)$q->row()->config_value;
            }
        }
        return self::DEFAULT_RETURN_WINDOW_HOURS;
    }
    
    /**
     * Set return window hours
     */
    public function set_return_window_hours($hours)
    {
        if (!$this->table_exists('nhis_billing_config')) return false;
        
        $exists = $this->db->get_where('nhis_billing_config', array('config_key' => 'return_window_hours'))->num_rows();
        if ($exists > 0) {
            $this->db->where('config_key', 'return_window_hours');
            return $this->db->update('nhis_billing_config', array('config_value' => (int)$hours));
        } else {
            return $this->db->insert('nhis_billing_config', array(
                'config_key' => 'return_window_hours',
                'config_value' => (int)$hours,
                'description' => 'Hours allowed for drug returns after dispensing'
            ));
        }
    }
    
    /**
     * Get returns list with filters
     */
    public function get_returns_list($filters = array())
    {
        $this->db->select('
            r.*,
            d.drug_name,
            d.generic_name,
            p.patient_name,
            CONCAT(u1.firstname, " ", u1.lastname) as requested_by_name,
            CONCAT(u2.firstname, " ", u2.lastname) as approved_by_name
        ', FALSE);
        $this->db->from('pharmacy_returns r');
        $this->db->join('medicine_drug_name d', 'd.drug_id = r.drug_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
        $this->db->join('users u1', 'u1.user_id = r.requested_by', 'left');
        $this->db->join('users u2', 'u2.user_id = r.approved_by', 'left');
        
        $this->db->where('r.InActive', 0);
        
        // Apply filters
        if (!empty($filters['status'])) {
            $this->db->where('r.status', $filters['status']);
        }
        
        // Task 3: Return type filter
        if (!empty($filters['return_type'])) {
            $this->db->where('r.return_type', $filters['return_type']);
        }
        
        if (!empty($filters['date_from'])) {
            $this->db->where('r.return_date >=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $this->db->where('r.return_date <=', $filters['date_to']);
        }
        
        if (!empty($filters['search'])) {
            $search = $this->db->escape_like_str($filters['search']);
            $this->db->group_start();
            $this->db->like('r.return_number', $search);
            $this->db->or_like('d.drug_name', $search);
            $this->db->or_like('p.patient_name', $search);
            $this->db->or_like('r.patient_no', $search);
            $this->db->or_like('r.batch_no', $search);
            $this->db->group_end();
        }
        
        $this->db->order_by('r.created_at', 'DESC');
        
        // Pagination
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 50;
        $offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
        $this->db->limit($limit, $offset);
        
        $q = $this->db->get();
        return $q ? $q->result() : array();
    }
    
    /**
     * Count returns for pagination
     */
    public function count_returns($filters = array())
    {
        $this->db->from('pharmacy_returns r');
        $this->db->join('medicine_drug_name d', 'd.drug_id = r.drug_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
        
        $this->db->where('r.InActive', 0);
        
        if (!empty($filters['status'])) {
            $this->db->where('r.status', $filters['status']);
        }
        
        // Task 3: Return type filter
        if (!empty($filters['return_type'])) {
            $this->db->where('r.return_type', $filters['return_type']);
        }
        
        if (!empty($filters['date_from'])) {
            $this->db->where('r.return_date >=', $filters['date_from']);
        }
        
        if (!empty($filters['date_to'])) {
            $this->db->where('r.return_date <=', $filters['date_to']);
        }
        
        if (!empty($filters['search'])) {
            $search = $this->db->escape_like_str($filters['search']);
            $this->db->group_start();
            $this->db->like('r.return_number', $search);
            $this->db->or_like('d.drug_name', $search);
            $this->db->or_like('p.patient_name', $search);
            $this->db->or_like('r.patient_no', $search);
            $this->db->or_like('r.batch_no', $search);
            $this->db->group_end();
        }
        
        return $this->db->count_all_results();
    }
    
    /**
     * Get single return detail
     */
    public function get_return_detail($return_id)
    {
        $this->db->select('
            r.*,
            d.drug_name,
            d.generic_name,
            d.strength,
            d.nStock as current_stock,
            p.patient_name,
            CONCAT(u1.firstname, " ", u1.lastname) as requested_by_name,
            CONCAT(u2.firstname, " ", u2.lastname) as approved_by_name,
            a.dDateTime as dispense_date,
            a.batch_no
        ', FALSE);
        $this->db->from('pharmacy_returns r');
        $this->db->join('medicine_drug_name d', 'd.drug_id = r.drug_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left');
        $this->db->join('users u1', 'u1.user_id = r.requested_by', 'left');
        $this->db->join('users u2', 'u2.user_id = r.approved_by', 'left');
        $this->db->join('iop_medication_administration a', 'a.admin_id = r.admin_id', 'left');
        
        $this->db->where('r.return_id', (int)$return_id);
        $this->db->where('r.InActive', 0);
        
        $q = $this->db->get();
        return $q ? $q->row() : null;
    }
    
    // =========================================================================
    // APPROVAL WORKFLOW
    // =========================================================================
    
    /**
     * Approve return request
     * Phase 2 Hardening: Added financial reversal (Task 6)
     */
    public function approve_return_request($return_id, $user_id, $options = array())
    {
        $return = $this->get_return_detail($return_id);
        if (!$return) {
            return array('success' => false, 'error' => 'Return not found');
        }
        
        if ($return->status !== self::STATUS_PENDING) {
            return array('success' => false, 'error' => 'Return is not in pending status');
        }
        
        if ($return->stock_adjusted == 1) {
            return array('success' => false, 'error' => 'Stock already adjusted for this return');
        }
        
        // Start transaction
        $this->db->trans_start();
        
        // Update return status
        $update_data = array(
            'status' => self::STATUS_APPROVED,
            'approved_by' => $user_id,
            'approved_date' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->where('return_id', (int)$return_id);
        $this->db->update('pharmacy_returns', $update_data);
        
        // Adjust stock (Task 2: Batch-level support)
        $stock_result = $this->adjust_stock_on_approval($return);
        
        if ($stock_result['success']) {
            // Mark stock as adjusted
            $this->db->where('return_id', (int)$return_id);
            $this->db->update('pharmacy_returns', array('stock_adjusted' => 1));
        }
        
        // Task 6: Financial reversal handling
        $reversal_result = array('billing_reversed' => false, 'claim_reversed' => false, 'amount' => 0);
        $do_reversal = isset($options['reverse_billing']) ? $options['reverse_billing'] : true;
        
        if ($do_reversal) {
            $reversal_result = $this->process_financial_reversal($return, $user_id);
            
            if ($reversal_result['billing_reversed'] || $reversal_result['claim_reversed']) {
                $this->db->where('return_id', (int)$return_id);
                $this->db->update('pharmacy_returns', array(
                    'billing_reversed' => $reversal_result['billing_reversed'] ? 1 : 0,
                    'claim_reversed' => $reversal_result['claim_reversed'] ? 1 : 0,
                    'reversal_amount' => $reversal_result['amount'],
                    'reversal_date' => date('Y-m-d H:i:s')
                ));
            }
        }

        // Reverse dispensing truth (audit-friendly): insert negative administration row
        if ($this->table_exists('iop_medication_administration')) {
            $qty_returned = (float)$return->quantity_returned;
            if ($qty_returned > 0) {
                $noteTag = 'RETURN_ID:' . (int)$return_id;
                $this->db->select('admin_id');
                $this->db->from('iop_medication_administration');
                $this->db->where(array(
                    'iop_med_id' => (int)$return->iop_med_id,
                    'InActive' => 0,
                    'status' => 'RETURN'
                ));
                $this->db->like('notes', $noteTag);
                $this->db->limit(1);
                $exists = $this->db->get()->row();
                if (!$exists) {
                    $ins = array(
                        'iop_med_id' => (int)$return->iop_med_id,
                        'iop_id' => isset($return->iop_id) ? (string)$return->iop_id : null,
                        'status' => 'RETURN',
                        'dose_given' => (string)(0 - $qty_returned),
                        'notes' => $noteTag,
                        'dDateTime' => date('Y-m-d H:i:s'),
                        'cPreparedBy' => (string)$user_id,
                        'InActive' => 0
                    );
                    $this->db->insert('iop_medication_administration', $ins);
                    $revAdminId = (int)$this->db->insert_id();
                    if ($revAdminId > 0 && $this->column_exists('iop_medication_administration', 'pharmacist_id')) {
                        $upd = array('pharmacist_id' => (string)$user_id);
                        if (isset($return->batch_no) && trim((string)$return->batch_no) !== '' && $this->column_exists('iop_medication_administration', 'batch_no')) {
                            $upd['batch_no'] = trim((string)$return->batch_no);
                        }
                        $this->db->where('admin_id', $revAdminId);
                        $this->db->update('iop_medication_administration', $upd);
                    }
                }
            }
        }
        
        $this->db->trans_complete();
        
        if ($this->db->trans_status() === FALSE) {
            return array('success' => false, 'error' => 'Transaction failed');
        }
        
        // Log audit
        $this->log_return_audit($return_id, 'APPROVE', $user_id, array(
            'previous_status' => self::STATUS_PENDING,
            'new_status' => self::STATUS_APPROVED,
            'stock_before' => $stock_result['stock_before'],
            'stock_after' => $stock_result['stock_after'],
            'billing_reversed' => $reversal_result['billing_reversed'],
            'claim_reversed' => $reversal_result['claim_reversed'],
            'reversal_amount' => $reversal_result['amount']
        ));
        
        $message = 'Return approved and stock updated';
        if ($reversal_result['billing_reversed']) {
            $message .= '. Billing reversed: GHS ' . number_format($reversal_result['amount'], 2);
        }
        
        return array('success' => true, 'message' => $message, 'reversal' => $reversal_result);
    }
    
    /**
     * Reject return request
     */
    public function reject_return_request($return_id, $user_id, $rejection_reason = '')
    {
        $return = $this->get_return_detail($return_id);
        if (!$return) {
            return array('success' => false, 'error' => 'Return not found');
        }
        
        if ($return->status !== self::STATUS_PENDING) {
            return array('success' => false, 'error' => 'Return is not in pending status');
        }
        
        // Update return status
        $this->db->where('return_id', (int)$return_id);
        $this->db->update('pharmacy_returns', array(
            'status' => self::STATUS_REJECTED,
            'approved_by' => $user_id,
            'approved_date' => date('Y-m-d H:i:s'),
            'rejection_reason' => $rejection_reason,
            'updated_at' => date('Y-m-d H:i:s')
        ));
        
        // Log audit
        $this->log_return_audit($return_id, 'REJECT', $user_id, array(
            'previous_status' => self::STATUS_PENDING,
            'new_status' => self::STATUS_REJECTED,
            'rejection_reason' => $rejection_reason
        ));
        
        return array('success' => true, 'message' => 'Return rejected');
    }
    
    // =========================================================================
    // STOCK ADJUSTMENT (Task 2: Batch-Level Support)
    // =========================================================================
    
    /**
     * Adjust stock on approval - supports batch-level returns
     * Phase 2 Hardening: Task 2
     */
    public function adjust_stock_on_approval($return)
    {
        $drug_id = (int)$return->drug_id;
        $qty_returned = (float)$return->quantity_returned;
        $batch_no = isset($return->batch_no) ? $return->batch_no : null;
        
        $stock_before = 0;
        $stock_after = 0;
        $batch_updated = false;
        
        // Task 2: Try batch-level update first if batch_no exists
        if (!empty($batch_no) && $this->table_exists('medication_stock')) {
            $batch = $this->db->get_where('medication_stock', array(
                'medication_id' => $drug_id,
                'batch_number' => $batch_no
            ))->row();
            
            if ($batch) {
                $stock_before = (float)$batch->quantity_remaining;
                $stock_after = $stock_before + $qty_returned;
                
                $this->db->where('stock_id', $batch->stock_id);
                $this->db->update('medication_stock', array(
                    'quantity_remaining' => $stock_after,
                    'updated_at' => date('Y-m-d H:i:s')
                ));
                
                $batch_updated = true;
                
                // Log batch adjustment
                $this->log_stock_adjustment($drug_id, 'RETURN_BATCH', $qty_returned, $stock_before, $stock_after, 
                    'Batch return: ' . $return->return_number . ' (Batch: ' . $batch_no . ')', 
                    'pharmacy_returns', $return->return_id);
            }
        }
        
        // Fallback: Update master stock (always sync)
        $this->db->select('nStock');
        $this->db->where('drug_id', $drug_id);
        $q = $this->db->get('medicine_drug_name');
        $drug = $q ? $q->row() : null;
        
        $master_stock_before = $drug ? (float)$drug->nStock : 0;
        $master_stock_after = $master_stock_before + $qty_returned;
        
        $this->db->where('drug_id', $drug_id);
        $this->db->update('medicine_drug_name', array('nStock' => $master_stock_after));
        
        // Log master stock adjustment
        $this->log_stock_adjustment($drug_id, 'RETURN', $qty_returned, $master_stock_before, $master_stock_after,
            'Drug return: ' . $return->return_number, 'pharmacy_returns', $return->return_id);
        
        return array(
            'success' => true,
            'stock_before' => $batch_updated ? $stock_before : $master_stock_before,
            'stock_after' => $batch_updated ? $stock_after : $master_stock_after,
            'batch_updated' => $batch_updated,
            'batch_no' => $batch_no
        );
    }
    
    /**
     * Legacy method - calls new batch-aware method
     */
    public function update_stock_after_return($return)
    {
        return $this->adjust_stock_on_approval($return);
    }
    
    /**
     * Log stock adjustment to audit table
     */
    private function log_stock_adjustment($drug_id, $type, $qty, $before, $after, $reason, $ref_type, $ref_id)
    {
        if ($this->table_exists('pharmacy_stock_adjustment')) {
            $this->db->insert('pharmacy_stock_adjustment', array(
                'drug_id' => $drug_id,
                'adjustment_type' => $type,
                'qty_change' => $qty,
                'stock_before' => $before,
                'stock_after' => $after,
                'reason' => $reason,
                'reference_type' => $ref_type,
                'reference_id' => $ref_id,
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->session->userdata('user_id')
            ));
        }
    }
    
    // =========================================================================
    // FINANCIAL REVERSAL (Task 6)
    // =========================================================================
    
    /**
     * Process financial reversal on return approval
     * Phase 2 Hardening: Task 6
     */
    public function process_financial_reversal($return, $user_id)
    {
        $result = array(
            'billing_reversed' => false,
            'claim_reversed' => false,
            'amount' => 0,
            'invoice_no' => null
        );
        
        // Find billing line item for this medication
        $iop_med_id = (int)$return->iop_med_id;
        $qty_returned = (float)$return->quantity_returned;
        
        // Check if billing_model is loaded
        if (!isset($this->billing_model)) {
            $this->load->model('app/billing_model');
        }
        
        // Find invoice item for this medication
        $invoice_item = $this->find_medication_invoice_item($iop_med_id);
        
        if ($invoice_item) {
            // Calculate reversal amount (proportional to qty returned)
            $total_qty = (float)$invoice_item->qty > 0 ? (float)$invoice_item->qty : 1;
            $unit_price = (float)$invoice_item->rate;
            $reversal_amount = $unit_price * $qty_returned;
            
            // Create credit note / reversal entry
            $reversal_success = $this->create_billing_reversal($invoice_item, $reversal_amount, $qty_returned, $return, $user_id);
            
            if ($reversal_success) {
                $result['billing_reversed'] = true;
                $result['amount'] = $reversal_amount;
                $result['invoice_no'] = $invoice_item->invoice_no;
                
                // Log billing reversal audit
                $this->log_return_audit($return->return_id, 'BILLING_REVERSED', $user_id, array(
                    'notes' => "Billing reversed: GHS {$reversal_amount} for {$qty_returned} units",
                    'invoice_no' => $invoice_item->invoice_no
                ));
            }
            
            // Check if NHIS patient - reverse claim if applicable
            if ($this->is_nhis_patient($return->patient_no)) {
                $claim_reversed = $this->reverse_nhis_claim_item($return, $reversal_amount, $user_id);
                if ($claim_reversed) {
                    $result['claim_reversed'] = true;
                    
                    $this->log_return_audit($return->return_id, 'CLAIM_REVERSED', $user_id, array(
                        'notes' => "NHIS claim item reversed: GHS {$reversal_amount}"
                    ));
                }
            }
        }
        
        return $result;
    }
    
    /**
     * Find invoice item for medication
     */
    private function find_medication_invoice_item($iop_med_id)
    {
        // Look in invoice_items for this medication
        if ($this->table_exists('invoice_items')) {
            $q = $this->db->query("
                SELECT ii.*, i.invoice_no, i.patient_no 
                FROM invoice_items ii
                JOIN invoice i ON i.invoice_id = ii.invoice_id
                WHERE ii.reference_id = ? AND ii.item_type = 'MEDICATION'
                ORDER BY ii.item_id DESC
                LIMIT 1
            ", array($iop_med_id));
            
            if ($q && $q->num_rows() > 0) {
                return $q->row();
            }
        }
        
        // Fallback: Check iop_medication for billing info
        $med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id))->row();
        if ($med && !empty($med->invoice_id)) {
            return (object) array(
                'invoice_id' => $med->invoice_id,
                'invoice_no' => '',
                'qty' => $med->total_qty,
                'rate' => isset($med->rate) ? $med->rate : 0,
                'patient_no' => $med->patient_no
            );
        }
        
        return null;
    }
    
    /**
     * Create billing reversal entry
     */
    private function create_billing_reversal($invoice_item, $amount, $qty, $return, $user_id)
    {
        // Insert reversal record if table exists
        if ($this->table_exists('billing_reversals')) {
            $this->db->insert('billing_reversals', array(
                'invoice_id' => $invoice_item->invoice_id,
                'original_item_id' => isset($invoice_item->item_id) ? $invoice_item->item_id : 0,
                'reversal_type' => 'DRUG_RETURN',
                'reversal_amount' => $amount,
                'quantity_reversed' => $qty,
                'reference_type' => 'pharmacy_returns',
                'reference_id' => $return->return_id,
                'reversed_by' => $user_id,
                'reversed_at' => date('Y-m-d H:i:s'),
                'notes' => 'Drug return: ' . $return->return_number
            ));
            return true;
        }
        
        // Fallback: Update invoice total if possible
        if (isset($invoice_item->invoice_id) && $invoice_item->invoice_id > 0) {
            $this->db->query("
                UPDATE invoice 
                SET total_amount = total_amount - ?, 
                    updated_at = NOW()
                WHERE invoice_id = ?
            ", array($amount, $invoice_item->invoice_id));
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if patient is NHIS
     */
    private function is_nhis_patient($patient_no)
    {
        $q = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no));
        if ($q && $q->num_rows() > 0) {
            $patient = $q->row();
            return isset($patient->payer_type) && strtoupper($patient->payer_type) === 'NHIS';
        }
        return false;
    }
    
    /**
     * Reverse NHIS claim item
     */
    private function reverse_nhis_claim_item($return, $amount, $user_id)
    {
        // Check if claim has been submitted
        if ($this->table_exists('nhis_claims')) {
            $claim = $this->db->query("
                SELECT c.* FROM nhis_claims c
                JOIN nhis_claim_items ci ON ci.claim_id = c.claim_id
                WHERE ci.reference_id = ? AND ci.item_type = 'MEDICATION'
                AND c.status NOT IN ('SUBMITTED', 'APPROVED', 'PAID')
                LIMIT 1
            ", array($return->iop_med_id))->row();
            
            if ($claim) {
                // Mark claim item as reversed
                $this->db->query("
                    UPDATE nhis_claim_items 
                    SET status = 'REVERSED', 
                        reversed_amount = ?,
                        reversed_at = NOW(),
                        reversed_by = ?
                    WHERE reference_id = ? AND item_type = 'MEDICATION'
                ", array($amount, $user_id, $return->iop_med_id));
                
                return true;
            }
        }
        
        return false;
    }
    
    // =========================================================================
    // AUDIT LOGGING
    // =========================================================================
    
    /**
     * Log return audit trail
     */
    public function log_return_audit($return_id, $action, $user_id, $data = array())
    {
        $audit_data = array(
            'return_id' => (int)$return_id,
            'action' => $action,
            'user_id' => $user_id,
            'previous_status' => isset($data['previous_status']) ? $data['previous_status'] : null,
            'new_status' => isset($data['new_status']) ? $data['new_status'] : null,
            'previous_values' => isset($data['previous_values']) ? json_encode($data['previous_values']) : null,
            'new_values' => isset($data['new_values']) ? json_encode($data['new_values']) : null,
            'notes' => isset($data['notes']) ? $data['notes'] : null,
            'created_at' => date('Y-m-d H:i:s')
        );
        
        $this->db->insert('pharmacy_return_audit', $audit_data);
    }
    
    /**
     * Get audit trail for a return
     */
    public function get_return_audit($return_id)
    {
        $this->db->select('a.*, CONCAT(u.firstname, " ", u.lastname) as user_name');
        $this->db->from('pharmacy_return_audit a');
        $this->db->join('users u', 'u.user_id = a.user_id', 'left');
        $this->db->where('a.return_id', (int)$return_id);
        $this->db->order_by('a.created_at', 'ASC');
        
        $q = $this->db->get();
        return $q ? $q->result() : array();
    }
    
    // =========================================================================
    // SUMMARY COUNTS
    // =========================================================================
    
    /**
     * Get returns summary counts
     */
    public function get_returns_summary()
    {
        return array(
            'pending' => $this->count_by_status(self::STATUS_PENDING),
            'approved' => $this->count_by_status(self::STATUS_APPROVED),
            'rejected' => $this->count_by_status(self::STATUS_REJECTED),
            'today' => $this->count_today_returns()
        );
    }
    
    /**
     * Count returns by status
     */
    public function count_by_status($status)
    {
        $this->db->where('status', $status);
        $this->db->where('InActive', 0);
        return $this->db->count_all_results('pharmacy_returns');
    }
    
    /**
     * Count today's returns
     */
    public function count_today_returns()
    {
        $this->db->where('return_date', date('Y-m-d'));
        $this->db->where('InActive', 0);
        return $this->db->count_all_results('pharmacy_returns');
    }
}
