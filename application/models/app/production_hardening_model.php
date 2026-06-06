<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Production Hardening Model
 * 
 * Handles all database migrations, schema hardening, and production readiness tasks
 */
class Production_hardening_model extends CI_Model {
    
    public function __construct(){
        parent::__construct();
    }
    
    /**
     * Run all production hardening migrations
     */
    public function run_all_migrations(){
        $results = array();
        $results['billing_gates'] = $this->install_billing_gates();
        $results['price_override_audit'] = $this->install_price_override_audit();
        $results['consent_tracking'] = $this->install_consent_tracking();
        $results['data_retention'] = $this->install_data_retention_policy();
        $results['reconciliation'] = $this->install_reconciliation_engine();
        $results['central_audit'] = $this->install_central_audit_system();
		$results['nhis_audit_enforcement'] = $this->ensure_nhis_audit_enforcement_schema();
        $results['performance_indexes'] = $this->add_performance_indexes();
        return $results;
    }
    
    /**
     * Install billing gates to prevent service before billing
     */
    public function install_billing_gates(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `billing_service_gates` (
            `gate_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `patient_no` varchar(25) NOT NULL,
            `encounter_id` varchar(25) NOT NULL,
            `department` varchar(30) NOT NULL,
            `service_type` varchar(50) NOT NULL,
            `service_ref` varchar(100) NOT NULL,
            `billing_required` tinyint(1) NOT NULL DEFAULT 1,
            `billing_status` varchar(20) NOT NULL DEFAULT 'PENDING',
            `invoice_no` varchar(50) DEFAULT NULL,
            `gate_passed` tinyint(1) NOT NULL DEFAULT 0,
            `gate_passed_at` datetime DEFAULT NULL,
            `gate_passed_by` varchar(25) DEFAULT NULL,
            `override_reason` varchar(255) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `created_by` varchar(25) DEFAULT NULL,
            `InActive` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`gate_id`),
            UNIQUE KEY `uq_service` (`department`, `service_ref`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_encounter` (`encounter_id`),
            KEY `idx_status` (`billing_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    }

	public function ensure_nhis_audit_enforcement_schema(){
		if (!$this->_table_exists('nhis_audit_log')) {
			return false;
		}

		$this->_add_column_if_not_exists('nhis_audit_log', 'action_type', "varchar(50) DEFAULT NULL");
		$this->_add_column_if_not_exists('nhis_audit_log', 'reference_type', "varchar(30) DEFAULT NULL");
		$this->_add_column_if_not_exists('nhis_audit_log', 'reference_id', "varchar(50) DEFAULT NULL");
		$this->_add_column_if_not_exists('nhis_audit_log', 'api_request', "text DEFAULT NULL");
		$this->_add_column_if_not_exists('nhis_audit_log', 'api_response', "text DEFAULT NULL");
		$this->_add_column_if_not_exists('nhis_audit_log', 'status', "varchar(20) DEFAULT 'success'");
		$this->_add_column_if_not_exists('nhis_audit_log', 'error_message', "text DEFAULT NULL");
		$this->_add_column_if_not_exists('nhis_audit_log', 'performed_by', "varchar(25) DEFAULT NULL");

		return true;
	}
    
    /**
     * Install price override audit logging
     */
    public function install_price_override_audit(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `billing_price_override_log` (
            `override_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `invoice_no` varchar(50) NOT NULL,
            `line_item_id` int(11) DEFAULT NULL,
            `item_name` varchar(255) DEFAULT NULL,
            `original_price` decimal(18,2) NOT NULL,
            `override_price` decimal(18,2) NOT NULL,
            `price_difference` decimal(18,2) NOT NULL,
            `override_reason` varchar(255) DEFAULT NULL,
            `requires_approval` tinyint(1) NOT NULL DEFAULT 0,
            `approved` tinyint(1) DEFAULT NULL,
            `approved_by` varchar(25) DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `encounter_id` varchar(25) DEFAULT NULL,
            `created_by` varchar(25) NOT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`override_id`),
            KEY `idx_invoice` (`invoice_no`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_approval` (`requires_approval`, `approved`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        return true;
    }
    
    /**
     * Install patient consent tracking for Ghana Data Protection Act
     */
    public function install_consent_tracking(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `patient_consent` (
            `consent_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `patient_no` varchar(25) NOT NULL,
            `consent_type` varchar(50) NOT NULL,
            `consent_given` tinyint(1) NOT NULL DEFAULT 0,
            `consent_date` datetime DEFAULT NULL,
            `consent_method` varchar(30) DEFAULT NULL,
            `witness_name` varchar(100) DEFAULT NULL,
            `witness_id` varchar(50) DEFAULT NULL,
            `document_ref` varchar(100) DEFAULT NULL,
            `revoked` tinyint(1) NOT NULL DEFAULT 0,
            `revoked_date` datetime DEFAULT NULL,
            `revoked_reason` varchar(255) DEFAULT NULL,
            `created_by` varchar(25) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`consent_id`),
            UNIQUE KEY `uq_patient_consent` (`patient_no`, `consent_type`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_type` (`consent_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Add consent columns to patient_personal_info if not exists
        $this->_add_column_if_not_exists('patient_personal_info', 'data_consent', "tinyint(1) DEFAULT 0");
        $this->_add_column_if_not_exists('patient_personal_info', 'data_consent_date', "datetime DEFAULT NULL");
        $this->_add_column_if_not_exists('patient_personal_info', 'nhis_consent', "tinyint(1) DEFAULT 0");
        
        return true;
    }
    
    /**
     * Install data retention policy tracking
     */
    public function install_data_retention_policy(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `data_retention_policy` (
            `policy_id` int(11) NOT NULL AUTO_INCREMENT,
            `data_category` varchar(50) NOT NULL,
            `table_name` varchar(100) NOT NULL,
            `retention_years` int(11) NOT NULL DEFAULT 7,
            `archive_after_years` int(11) DEFAULT 5,
            `delete_after_years` int(11) DEFAULT NULL,
            `requires_audit` tinyint(1) NOT NULL DEFAULT 1,
            `last_cleanup_date` date DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`policy_id`),
            UNIQUE KEY `uq_table` (`table_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `data_archive_log` (
            `archive_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `policy_id` int(11) NOT NULL,
            `table_name` varchar(100) NOT NULL,
            `records_archived` int(11) NOT NULL DEFAULT 0,
            `records_deleted` int(11) NOT NULL DEFAULT 0,
            `archive_date` date NOT NULL,
            `archive_file` varchar(255) DEFAULT NULL,
            `performed_by` varchar(25) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`archive_id`),
            KEY `idx_policy` (`policy_id`),
            KEY `idx_date` (`archive_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Seed default retention policies for Ghana health regulations
        $this->_seed_retention_policies();
        
        return true;
    }
    
    /**
     * Install reconciliation engine tables
     */
    public function install_reconciliation_engine(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `reconciliation_issues` (
            `issue_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `issue_date` date NOT NULL,
            `issue_type` varchar(50) NOT NULL,
            `department` varchar(30) NOT NULL,
            `severity` varchar(20) NOT NULL DEFAULT 'MEDIUM',
            `patient_no` varchar(25) DEFAULT NULL,
            `encounter_id` varchar(25) DEFAULT NULL,
            `source_table` varchar(100) DEFAULT NULL,
            `source_ref` varchar(100) DEFAULT NULL,
            `expected_value` varchar(255) DEFAULT NULL,
            `actual_value` varchar(255) DEFAULT NULL,
            `amount_discrepancy` decimal(18,2) DEFAULT NULL,
            `description` text,
            `auto_detected` tinyint(1) NOT NULL DEFAULT 1,
            `resolved` tinyint(1) NOT NULL DEFAULT 0,
            `resolved_by` varchar(25) DEFAULT NULL,
            `resolved_at` datetime DEFAULT NULL,
            `resolution_notes` text,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`issue_id`),
            KEY `idx_date` (`issue_date`),
            KEY `idx_type` (`issue_type`),
            KEY `idx_department` (`department`),
            KEY `idx_severity` (`severity`),
            KEY `idx_resolved` (`resolved`),
            KEY `idx_patient` (`patient_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `reconciliation_runs` (
            `run_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `run_date` date NOT NULL,
            `run_type` varchar(30) NOT NULL DEFAULT 'DAILY',
            `departments_checked` varchar(255) DEFAULT NULL,
            `issues_found` int(11) NOT NULL DEFAULT 0,
            `issues_resolved` int(11) NOT NULL DEFAULT 0,
            `total_discrepancy` decimal(18,2) DEFAULT NULL,
            `run_duration_seconds` int(11) DEFAULT NULL,
            `run_by` varchar(25) DEFAULT NULL,
            `run_status` varchar(20) NOT NULL DEFAULT 'COMPLETED',
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`run_id`),
            KEY `idx_date` (`run_date`),
            KEY `idx_type` (`run_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        return true;
    }
    
    /**
     * Install central audit system
     */
    public function install_central_audit_system(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `system_audit_log` (
            `audit_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `audit_type` varchar(50) NOT NULL,
            `module` varchar(50) NOT NULL,
            `action` varchar(50) NOT NULL,
            `table_name` varchar(100) DEFAULT NULL,
            `record_id` varchar(50) DEFAULT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `encounter_id` varchar(25) DEFAULT NULL,
            `old_values` text,
            `new_values` text,
            `change_summary` varchar(500) DEFAULT NULL,
            `user_id` varchar(25) DEFAULT NULL,
            `username` varchar(100) DEFAULT NULL,
            `user_role` varchar(50) DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `session_id` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`audit_id`),
            KEY `idx_type` (`audit_type`),
            KEY `idx_module` (`module`),
            KEY `idx_action` (`action`),
            KEY `idx_table` (`table_name`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_user` (`user_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        return true;
    }
    
    /**
     * Add performance indexes to key tables
     */
    public function add_performance_indexes(){
        $indexes = array(
            array('patient_personal_info', 'idx_perf_name', 'lastname, firstname'),
            array('patient_personal_info', 'idx_perf_phone', 'mobile_no'),
            array('patient_details_iop', 'idx_perf_date', 'date_visit'),
            array('patient_details_iop', 'idx_perf_type_date', 'patient_type, date_visit'),
            array('iop_billing', 'idx_perf_date', 'dDate'),
            array('iop_billing', 'idx_perf_patient_date', 'patient_no, dDate'),
            array('iop_billing_t', 'idx_perf_invoice', 'invoice_no'),
            array('iop_medication', 'idx_perf_iop_status', 'iop_id, dispensing_status'),
            array('iop_laboratory', 'idx_perf_iop_result', 'iop_id, result(191)'),
            array('iop_receipt', 'idx_perf_date', 'dDate'),
            array('medicine_drug_name', 'idx_perf_name', 'drug_name'),
            array('medicine_drug_name', 'idx_perf_stock', 'nStock, re_order_level'),
            array('nhis_audit_log', 'idx_nhis_audit_enforcement', 'reference_type, reference_id, action_type, status')
        );
        
        foreach ($indexes as $idx) {
            $this->_add_index_if_not_exists($idx[0], $idx[1], $idx[2]);
        }
        
        return true;
    }
    
    /**
     * Convert tables to InnoDB
     */
    public function convert_tables_to_innodb(){
        $tables = array(
            'patient_personal_info',
            'patient_details_iop',
            'iop_billing',
            'iop_billing_t',
            'iop_receipt',
            'iop_medication',
            'iop_laboratory',
            'medicine_drug_name',
            'bill_particular',
            'users',
            'user_roles'
        );
        
        $converted = array();
        foreach ($tables as $table) {
            if ($this->_table_exists($table)) {
                $result = $this->db->query("ALTER TABLE `{$table}` ENGINE=InnoDB");
                $converted[$table] = $result ? 'converted' : 'failed';
            }
        }
        
        return $converted;
    }
    
    /**
     * Standardize charset to utf8mb4
     */
    public function standardize_charset(){
        $tables = $this->db->query("SHOW TABLES")->result_array();
        $converted = 0;
        
        foreach ($tables as $row) {
            $table = array_values($row)[0];
            $this->db->query("ALTER TABLE `{$table}` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $converted++;
        }
        
        return $converted;
    }
    
    /**
     * Run daily reconciliation check
     */
    public function run_daily_reconciliation(){
        $start_time = microtime(true);
        $today = date('Y-m-d');
        $issues_found = 0;
        
        // Check: Dispensed medications not billed
        $issues_found += $this->_check_dispensed_not_billed();
        
        // Check: Lab results entered but not billed
        $issues_found += $this->_check_lab_not_billed();
        
        // Check: Billed but not paid (overdue)
        $issues_found += $this->_check_overdue_payments();
        
        // Check: NHIS claims not submitted
        $issues_found += $this->_check_nhis_claims_pending();
        
        // Log the run
        $duration = round(microtime(true) - $start_time);
        $this->db->insert('reconciliation_runs', array(
            'run_date' => $today,
            'run_type' => 'DAILY',
            'departments_checked' => 'PHARMACY,LABORATORY,BILLING,NHIS',
            'issues_found' => $issues_found,
            'run_duration_seconds' => $duration,
            'run_status' => 'COMPLETED',
            'created_at' => date('Y-m-d H:i:s')
        ));
        
        return array('issues_found' => $issues_found, 'duration' => $duration);
    }
    
    // ==================== PRIVATE HELPERS ====================
    
    private function _table_exists($table){
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table));
        return ($q && $q->num_rows() > 0);
    }
    
    private function _column_exists($table, $column){
        if (!$this->_table_exists($table)) return false;
        $q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($column));
        return ($q && $q->num_rows() > 0);
    }
    
    private function _add_column_if_not_exists($table, $column, $definition){
        if (!$this->_column_exists($table, $column)) {
            $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
            return true;
        }
        return false;
    }
    
    private function _add_index_if_not_exists($table, $index_name, $columns){
        if (!$this->_table_exists($table)) return false;
        $q = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index_name));
        if ($q && $q->num_rows() > 0) return false;
        
        // Verify columns exist
        $cols = explode(',', $columns);
        foreach ($cols as $col) {
            $col = trim((string)$col);
            if ($col === '') return false;
            $paren = strpos($col, '(');
            if ($paren !== false) {
                $col = substr($col, 0, $paren);
            }
            $col = trim($col, " `\t\n\r\0\x0B");
            if ($col === '') return false;
            if (!$this->_column_exists($table, $col)) return false;
        }
        
        $this->db->query("ALTER TABLE `{$table}` ADD KEY `{$index_name}` ({$columns})");
        return true;
    }
    
    private function _seed_retention_policies(){
        $policies = array(
            array('PATIENT_RECORDS', 'patient_personal_info', 10, 7, null),
            array('BILLING', 'iop_billing', 7, 5, 10),
            array('BILLING_DETAILS', 'iop_billing_t', 7, 5, 10),
            array('RECEIPTS', 'iop_receipt', 7, 5, 10),
            array('LABORATORY', 'iop_laboratory', 7, 5, 10),
            array('PHARMACY', 'iop_medication', 7, 5, 10),
            array('ENCOUNTERS', 'patient_details_iop', 10, 7, null),
            array('AUDIT_LOGS', 'system_audit_log', 5, 3, 7)
        );
        
        foreach ($policies as $p) {
            $this->db->where('table_name', $p[1]);
            if ($this->db->get('data_retention_policy')->num_rows() == 0) {
                $this->db->insert('data_retention_policy', array(
                    'data_category' => $p[0],
                    'table_name' => $p[1],
                    'retention_years' => $p[2],
                    'archive_after_years' => $p[3],
                    'delete_after_years' => $p[4],
                    'created_at' => date('Y-m-d H:i:s')
                ));
            }
        }
    }
    
    private function _check_dispensed_not_billed(){
        $issues = 0;
        $sql = "SELECT m.iop_med_id, m.iop_id, p.patient_no, d.drug_name
                FROM iop_medication m
                JOIN patient_details_iop p ON p.IO_ID = m.iop_id
                JOIN medicine_drug_name d ON d.drug_id = m.medicine_id
                LEFT JOIN iop_billable_item_lock l ON l.source_ref = CONCAT('iop_medication:', m.iop_med_id)
                WHERE m.dispensing_status = 'DISPENSED' 
                AND m.InActive = 0 
                AND l.lock_id IS NULL
                AND m.dDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        
        $result = $this->db->query($sql);
        if ($result && $result->num_rows() > 0) {
            foreach ($result->result() as $row) {
                $this->_log_reconciliation_issue(
                    'DISPENSED_NOT_BILLED',
                    'PHARMACY',
                    'HIGH',
                    $row->patient_no,
                    $row->iop_id,
                    'iop_medication',
                    'iop_medication:' . $row->iop_med_id,
                    "Medication '{$row->drug_name}' dispensed but not billed"
                );
                $issues++;
            }
        }
        return $issues;
    }
    
    private function _check_lab_not_billed(){
        $issues = 0;
        $sql = "SELECT l.io_lab_id, l.iop_id, p.patient_no, COALESCE(bp.particular_name, l.laboratory_text) as test_name
                FROM iop_laboratory l
                JOIN patient_details_iop p ON p.IO_ID = l.iop_id
                LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id
                LEFT JOIN iop_billable_item_lock k ON k.source_ref = CONCAT('iop_laboratory:', l.io_lab_id)
                WHERE l.result IS NOT NULL AND l.result != ''
                AND l.InActive = 0 
                AND k.lock_id IS NULL
                AND l.dDate >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
        
        $result = $this->db->query($sql);
        if ($result && $result->num_rows() > 0) {
            foreach ($result->result() as $row) {
                $this->_log_reconciliation_issue(
                    'LAB_NOT_BILLED',
                    'LABORATORY',
                    'HIGH',
                    $row->patient_no,
                    $row->iop_id,
                    'iop_laboratory',
                    'iop_laboratory:' . $row->io_lab_id,
                    "Lab test '{$row->test_name}' completed but not billed"
                );
                $issues++;
            }
        }
        return $issues;
    }
    
    private function _check_overdue_payments(){
        $issues = 0;
        if (!$this->_column_exists('iop_billing', 'payment_status')) return 0;
        
        $sql = "SELECT b.invoice_no, b.patient_no, b.iop_id, b.total_amount, b.dDate
                FROM iop_billing b
                WHERE b.payment_status IN ('UNPAID', 'PARTIAL')
                AND b.InActive = 0
                AND b.dDate < DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        $result = $this->db->query($sql);
        if ($result && $result->num_rows() > 0) {
            foreach ($result->result() as $row) {
                $this->_log_reconciliation_issue(
                    'PAYMENT_OVERDUE',
                    'BILLING',
                    'MEDIUM',
                    $row->patient_no,
                    $row->iop_id,
                    'iop_billing',
                    'invoice:' . $row->invoice_no,
                    "Invoice {$row->invoice_no} unpaid for over 30 days. Amount: GHS " . number_format($row->total_amount, 2)
                );
                $issues++;
            }
        }
        return $issues;
    }
    
    private function _check_nhis_claims_pending(){
        $issues = 0;
        if (!$this->_table_exists('nhis_claims')) return 0;
        
        $sql = "SELECT c.claim_id, c.claim_number, c.patient_no, c.encounter_id, c.total_amount
                FROM nhis_claims c
                WHERE c.status = 'PENDING'
                AND c.InActive = 0
                AND c.created_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)";
        
        $result = $this->db->query($sql);
        if ($result && $result->num_rows() > 0) {
            foreach ($result->result() as $row) {
                $this->_log_reconciliation_issue(
                    'NHIS_CLAIM_PENDING',
                    'NHIS',
                    'MEDIUM',
                    $row->patient_no,
                    $row->encounter_id,
                    'nhis_claims',
                    'claim:' . $row->claim_number,
                    "NHIS claim {$row->claim_number} pending for over 48 hours"
                );
                $issues++;
            }
        }
        return $issues;
    }
    
    private function _log_reconciliation_issue($type, $dept, $severity, $patient_no, $encounter_id, $table, $ref, $desc){
        // Check if issue already exists and not resolved
        $this->db->where(array(
            'issue_type' => $type,
            'source_ref' => $ref,
            'resolved' => 0
        ));
        if ($this->db->get('reconciliation_issues')->num_rows() > 0) {
            return; // Already logged
        }
        
        $this->db->insert('reconciliation_issues', array(
            'issue_date' => date('Y-m-d'),
            'issue_type' => $type,
            'department' => $dept,
            'severity' => $severity,
            'patient_no' => $patient_no,
            'encounter_id' => $encounter_id,
            'source_table' => $table,
            'source_ref' => $ref,
            'description' => $desc,
            'auto_detected' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ));
    }
    
    /**
     * Log to central audit system
     */
    public function log_audit($type, $module, $action, $data = array()){
        $CI =& get_instance();
        
        $this->db->insert('system_audit_log', array(
            'audit_type' => $type,
            'module' => $module,
            'action' => $action,
            'table_name' => isset($data['table']) ? $data['table'] : null,
            'record_id' => isset($data['record_id']) ? $data['record_id'] : null,
            'patient_no' => isset($data['patient_no']) ? $data['patient_no'] : null,
            'encounter_id' => isset($data['encounter_id']) ? $data['encounter_id'] : null,
            'old_values' => isset($data['old']) ? json_encode($data['old']) : null,
            'new_values' => isset($data['new']) ? json_encode($data['new']) : null,
            'change_summary' => isset($data['summary']) ? $data['summary'] : null,
            'user_id' => $CI->session->userdata('user_id'),
            'username' => $CI->session->userdata('username'),
            'user_role' => $CI->session->userdata('rbac_module'),
            'ip_address' => $CI->input->ip_address(),
            'user_agent' => substr($CI->input->user_agent(), 0, 255),
            'session_id' => session_id(),
            'created_at' => date('Y-m-d H:i:s')
        ));
    }
    
    /**
     * Check billing gate before service
     */
    public function check_billing_gate($department, $service_ref, $patient_no, $encounter_id){
        $this->install_billing_gates();
        
        $this->db->where(array(
            'department' => $department,
            'service_ref' => $service_ref,
            'InActive' => 0
        ));
        $gate = $this->db->get('billing_service_gates')->row();
        
        if (!$gate) {
            return array('allowed' => true, 'reason' => 'No gate configured');
        }
        
        if ($gate->gate_passed) {
            return array('allowed' => true, 'reason' => 'Gate already passed');
        }
        
        if ($gate->billing_status === 'PAID' || $gate->billing_status === 'NHIS') {
            return array('allowed' => true, 'reason' => 'Billing completed');
        }
        
        return array(
            'allowed' => false, 
            'reason' => 'Billing required before service',
            'gate_id' => $gate->gate_id
        );
    }
    
    /**
     * Log price override
     */
    public function log_price_override($invoice_no, $item_data, $user_id){
        $this->install_price_override_audit();
        
        $difference = abs($item_data['override_price'] - $item_data['original_price']);
        $requires_approval = ($difference > 50); // Require approval for >50 GHS difference
        
        $this->db->insert('billing_price_override_log', array(
            'invoice_no' => $invoice_no,
            'line_item_id' => isset($item_data['line_id']) ? $item_data['line_id'] : null,
            'item_name' => isset($item_data['item_name']) ? $item_data['item_name'] : null,
            'original_price' => $item_data['original_price'],
            'override_price' => $item_data['override_price'],
            'price_difference' => $difference,
            'override_reason' => isset($item_data['reason']) ? $item_data['reason'] : null,
            'requires_approval' => $requires_approval ? 1 : 0,
            'patient_no' => isset($item_data['patient_no']) ? $item_data['patient_no'] : null,
            'encounter_id' => isset($item_data['encounter_id']) ? $item_data['encounter_id'] : null,
            'created_by' => $user_id,
            'created_at' => date('Y-m-d H:i:s')
        ));
        
        // Log to central audit
        $this->log_audit('FINANCIAL', 'BILLING', 'PRICE_OVERRIDE', array(
            'table' => 'iop_billing_t',
            'record_id' => $invoice_no,
            'patient_no' => isset($item_data['patient_no']) ? $item_data['patient_no'] : null,
            'old' => array('price' => $item_data['original_price']),
            'new' => array('price' => $item_data['override_price']),
            'summary' => "Price override: {$item_data['original_price']} → {$item_data['override_price']}"
        ));
        
        return $requires_approval;
    }
}
