<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Unified OPD Status Engine
 * 
 * Single source of truth for all OPD visit status management.
 * Replaces fragmented status updates across multiple controllers/models.
 * 
 * @author HMS System Architect
 * @version 1.0
 * @date April 2026
 */
class Opd_status_engine extends CI_Model
{
    // ========================================================================
    // STATUS CONSTANTS
    // ========================================================================
    
    const STATUS_REGISTERED        = 'REGISTERED';
    const STATUS_WAITING           = 'WAITING';
    const STATUS_IN_CONSULTATION   = 'IN_CONSULTATION';
    const STATUS_LAB_PENDING       = 'LAB_PENDING';
    const STATUS_LAB_COMPLETED     = 'LAB_COMPLETED';
    const STATUS_PHARMACY_PENDING  = 'PHARMACY_PENDING';
    const STATUS_PHARMACY_COMPLETED= 'PHARMACY_COMPLETED';
    const STATUS_CLINICALLY_CLEARED= 'CLINICALLY_CLEARED';
    const STATUS_BILLING_PENDING   = 'BILLING_PENDING';
    const STATUS_FINAL_CLEARED     = 'FINAL_CLEARED';
    const STATUS_COMPLETED         = 'COMPLETED';
    const STATUS_ADMITTED          = 'ADMITTED';
    const STATUS_CANCELLED         = 'CANCELLED';
    const STATUS_REOPENED          = 'REOPENED';

    // ========================================================================
    // STATE MACHINE TRANSITIONS
    // ========================================================================
    
    /**
     * Valid state transitions
     * Key = current status, Value = array of allowed next statuses
     */
    private $transitions = [
        'REGISTERED'         => ['WAITING', 'IN_CONSULTATION', 'CANCELLED'],
        'WAITING'            => ['IN_CONSULTATION', 'CANCELLED'],
        'IN_CONSULTATION'    => ['LAB_PENDING', 'PHARMACY_PENDING', 'CLINICALLY_CLEARED', 'ADMITTED', 'CANCELLED', 'WAITING'],
        'LAB_PENDING'        => ['LAB_COMPLETED', 'IN_CONSULTATION', 'PHARMACY_PENDING', 'CLINICALLY_CLEARED'],
        'LAB_COMPLETED'      => ['IN_CONSULTATION', 'PHARMACY_PENDING', 'CLINICALLY_CLEARED', 'LAB_PENDING'],
        'PHARMACY_PENDING'   => ['PHARMACY_COMPLETED', 'IN_CONSULTATION', 'LAB_PENDING', 'CLINICALLY_CLEARED'],
        'PHARMACY_COMPLETED' => ['CLINICALLY_CLEARED', 'BILLING_PENDING', 'IN_CONSULTATION', 'PHARMACY_PENDING'],
        'PENDING_LAB'        => ['IN_CONSULTATION', 'CLINICALLY_CLEARED', 'PENDING_PHARMACY', 'COMPLETED'],
        'PENDING_PHARMACY'   => ['CLINICALLY_CLEARED', 'PENDING_LAB', 'COMPLETED'],
        'CLINICALLY_CLEARED' => ['BILLING_PENDING', 'FINAL_CLEARED', 'COMPLETED', 'ADMITTED', 'REOPENED'],
        'BILLING_PENDING'    => ['FINAL_CLEARED', 'CLINICALLY_CLEARED'],
        'FINAL_CLEARED'      => ['REOPENED'], // Can reopen
        'COMPLETED'          => ['REOPENED'],
        'ADMITTED'           => [], // Terminal - transfers to IPD
        'CANCELLED'          => ['REOPENED'], // Can reopen
        'REOPENED'           => ['WAITING', 'IN_CONSULTATION'], // Back to active
    ];

    /**
     * Status labels for display
     */
    private $status_labels = [
        'REGISTERED'         => 'Registered',
        'WAITING'            => 'Waiting',
        'IN_CONSULTATION'    => 'In Consultation',
        'LAB_PENDING'        => 'Lab Pending',
        'LAB_COMPLETED'      => 'Lab Completed',
        'PHARMACY_PENDING'   => 'Pharmacy Pending',
        'PHARMACY_COMPLETED' => 'Pharmacy Completed',
        'PENDING_LAB'        => 'Pending Lab',
        'PENDING_PHARMACY'   => 'Pending Pharmacy',
        'CLINICALLY_CLEARED' => 'Clinically Cleared',
        'BILLING_PENDING'    => 'Billing Pending',
        'FINAL_CLEARED'      => 'Discharged',
        'COMPLETED'          => 'Completed',
        'ADMITTED'           => 'Admitted (IPD)',
        'CANCELLED'          => 'Cancelled',
        'REOPENED'           => 'Reopened',
    ];

    /**
     * Status badge CSS classes
     */
    private $status_badges = [
        'REGISTERED'         => 'label-default',
        'WAITING'            => 'label-info',
        'IN_CONSULTATION'    => 'label-warning',
        'LAB_PENDING'        => 'label-danger',
        'LAB_COMPLETED'      => 'label-info',
        'PHARMACY_PENDING'   => 'label-primary',
        'PHARMACY_COMPLETED' => 'label-info',
        'PENDING_LAB'        => 'label-danger',
        'PENDING_PHARMACY'   => 'label-primary',
        'CLINICALLY_CLEARED' => 'label-success',
        'BILLING_PENDING'    => 'label-warning',
        'FINAL_CLEARED'      => 'label-default',
        'COMPLETED'          => 'label-default',
        'ADMITTED'           => 'label-danger',
        'CANCELLED'          => 'label-default',
        'REOPENED'           => 'label-warning',
    ];

    /**
     * Legacy field mapping for backward compatibility
     */
    private $legacy_field_map = [
        'REGISTERED'         => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'WAITING'            => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'IN_CONSULTATION'    => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'LAB_PENDING'        => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'LAB_COMPLETED'      => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'PHARMACY_PENDING'   => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'PHARMACY_COMPLETED' => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'PENDING_LAB'        => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'PENDING_PHARMACY'   => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'CLINICALLY_CLEARED' => ['nStatus' => 'Pending', 'clinical_clearance_status' => 1, 'visit_status' => 'closed'],
        'BILLING_PENDING'    => ['nStatus' => 'Pending', 'clinical_clearance_status' => 1, 'visit_status' => 'closed'],
        'FINAL_CLEARED'      => ['nStatus' => 'Discharged', 'clinical_clearance_status' => 1, 'visit_status' => 'closed'],
        'COMPLETED'          => ['nStatus' => 'Completed', 'clinical_clearance_status' => 1, 'visit_status' => 'closed'],
        'ADMITTED'           => ['nStatus' => 'Admitted', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
        'CANCELLED'          => ['nStatus' => 'Cancelled', 'clinical_clearance_status' => 0, 'visit_status' => 'closed'],
        'REOPENED'           => ['nStatus' => 'Pending', 'clinical_clearance_status' => 0, 'visit_status' => 'active'],
    ];

    // ========================================================================
    // CONSTRUCTOR
    // ========================================================================

    public function __construct()
    {
        parent::__construct();
        $this->load->helper('schema_guard');
        if (!schema_already_run('opd_status_engine_schema')) {
            $this->ensure_schema();
            mark_schema_run('opd_status_engine_schema');
        }
    }

    // ========================================================================
    // SCHEMA MANAGEMENT
    // ========================================================================

    /**
     * Ensure all required tables and columns exist
     */
    public function ensure_schema()
    {
        // Ensure iop_opd_workflow table exists
        if (!$this->db->table_exists('iop_opd_workflow')) {
            $this->_create_workflow_table();
        }

        // Ensure opd_status_audit table exists
        if (!$this->db->table_exists('opd_status_audit')) {
            $this->_create_audit_table();
        }

        // Ensure required columns exist
        $this->_ensure_workflow_columns();
        $this->_ensure_audit_columns();
        $this->_ensure_legacy_columns();
    }

    private function _create_workflow_table()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `iop_opd_workflow` (
            `wf_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `iop_id` VARCHAR(25) NOT NULL,
            `patient_no` VARCHAR(25) DEFAULT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'REGISTERED',
            `prev_status` VARCHAR(30) DEFAULT NULL,
            `sub_status` VARCHAR(50) DEFAULT NULL,
            `pending_lab_count` INT(11) DEFAULT 0,
            `pending_pharmacy_count` INT(11) DEFAULT 0,
            `billing_balance` DECIMAL(12,2) DEFAULT 0.00,
            `assigned_doctor_id` VARCHAR(25) DEFAULT NULL,
            `waiting_at` DATETIME DEFAULT NULL,
            `in_consultation_at` DATETIME DEFAULT NULL,
            `clinically_cleared_at` DATETIME DEFAULT NULL,
            `final_cleared_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `updated_by` VARCHAR(25) DEFAULT NULL,
            `InActive` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`wf_id`),
            UNIQUE KEY `uq_iop_active` (`iop_id`, `InActive`),
            KEY `idx_status` (`status`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_doctor` (`assigned_doctor_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->query($sql);
    }

    private function _create_audit_table()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `opd_status_audit` (
            `audit_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
            `iop_id` VARCHAR(25) NOT NULL,
            `patient_no` VARCHAR(25) DEFAULT NULL,
            `old_status` VARCHAR(30) DEFAULT NULL,
            `new_status` VARCHAR(30) NOT NULL,
            `trigger_source` VARCHAR(50) DEFAULT NULL,
            `trigger_module` VARCHAR(30) DEFAULT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `changed_by` VARCHAR(25) DEFAULT NULL,
            `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `extra_data` TEXT DEFAULT NULL,
            PRIMARY KEY (`audit_id`),
            KEY `idx_iop` (`iop_id`),
            KEY `idx_changed_at` (`changed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->db->query($sql);
    }

    private function _ensure_workflow_columns()
    {
        $columns = [
            'prev_status'            => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `prev_status` VARCHAR(30) DEFAULT NULL AFTER `status`",
            'sub_status'             => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `sub_status` VARCHAR(50) DEFAULT NULL AFTER `prev_status`",
            'pending_lab_count'      => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `pending_lab_count` INT(11) DEFAULT 0",
            'pending_pharmacy_count' => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `pending_pharmacy_count` INT(11) DEFAULT 0",
            'billing_balance'        => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `billing_balance` DECIMAL(12,2) DEFAULT 0.00",
            'assigned_doctor_id'     => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `assigned_doctor_id` VARCHAR(25) DEFAULT NULL",
            'patient_no'             => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `patient_no` VARCHAR(25) DEFAULT NULL AFTER `iop_id`",
            'waiting_at'             => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `waiting_at` DATETIME DEFAULT NULL",
            'in_consultation_at'     => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `in_consultation_at` DATETIME DEFAULT NULL",
            'clinically_cleared_at'  => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `clinically_cleared_at` DATETIME DEFAULT NULL",
            'final_cleared_at'       => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `final_cleared_at` DATETIME DEFAULT NULL",
            'created_at'             => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
            'updated_at'             => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `updated_at` DATETIME DEFAULT NULL",
            'updated_by'             => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `updated_by` VARCHAR(25) DEFAULT NULL",
        ];

        foreach ($columns as $col => $sql) {
            if (!$this->_column_exists('iop_opd_workflow', $col)) {
                $old_debug = $this->db->db_debug;
                $this->db->db_debug = false;
                $this->db->query($sql);
                $this->db->db_debug = $old_debug;
            }
        }
    }

    private function _ensure_audit_columns()
    {
        $columns = [
            'patient_no'     => "ALTER TABLE `opd_status_audit` ADD COLUMN `patient_no` VARCHAR(25) DEFAULT NULL",
            'old_status'     => "ALTER TABLE `opd_status_audit` ADD COLUMN `old_status` VARCHAR(30) DEFAULT NULL",
            'trigger_source' => "ALTER TABLE `opd_status_audit` ADD COLUMN `trigger_source` VARCHAR(50) DEFAULT NULL",
            'trigger_module' => "ALTER TABLE `opd_status_audit` ADD COLUMN `trigger_module` VARCHAR(30) DEFAULT NULL",
            'reason'         => "ALTER TABLE `opd_status_audit` ADD COLUMN `reason` VARCHAR(255) DEFAULT NULL",
            'changed_by'     => "ALTER TABLE `opd_status_audit` ADD COLUMN `changed_by` VARCHAR(25) DEFAULT NULL",
            'changed_at'     => "ALTER TABLE `opd_status_audit` ADD COLUMN `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP",
            'ip_address'     => "ALTER TABLE `opd_status_audit` ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL",
            'extra_data'     => "ALTER TABLE `opd_status_audit` ADD COLUMN `extra_data` TEXT DEFAULT NULL",
        ];

        foreach ($columns as $col => $sql) {
            if (!$this->_column_exists('opd_status_audit', $col)) {
                $old_debug = $this->db->db_debug;
                $this->db->db_debug = false;
                $this->db->query($sql);
                $this->db->db_debug = $old_debug;
            }
        }
    }

    private function _ensure_legacy_columns()
    {
        // Ensure patient_details_iop has required columns for backward compat
        $columns = [
            'clinical_clearance_status' => "ALTER TABLE `patient_details_iop` ADD COLUMN `clinical_clearance_status` TINYINT(1) DEFAULT 0",
            'clinically_cleared_by'     => "ALTER TABLE `patient_details_iop` ADD COLUMN `clinically_cleared_by` VARCHAR(25) DEFAULT NULL",
            'clinically_cleared_at'     => "ALTER TABLE `patient_details_iop` ADD COLUMN `clinically_cleared_at` DATETIME DEFAULT NULL",
            'visit_status'              => "ALTER TABLE `patient_details_iop` ADD COLUMN `visit_status` VARCHAR(20) DEFAULT 'active'",
        ];

        foreach ($columns as $col => $sql) {
            if (!$this->_column_exists('patient_details_iop', $col)) {
                $old_debug = $this->db->db_debug;
                $this->db->db_debug = false;
                $this->db->query($sql);
                $this->db->db_debug = $old_debug;
            }
        }
    }

    private function _column_exists($table, $column)
    {
        $q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return ($q && $q->num_rows() > 0);
    }

    private function _get_discount_column($table)
    {
        if (!$this->db->table_exists($table)) {
            return null;
        }
        if ($this->_column_exists($table, 'discount')) {
            return 'discount';
        }
        if ($this->_column_exists($table, 'discount_amount')) {
            return 'discount_amount';
        }
        if ($this->_column_exists($table, 'discountAmount')) {
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

    // ========================================================================
    // CORE STATUS OPERATIONS
    // ========================================================================

    /**
     * Get current status for a visit
     * 
     * @param string $iop_id Visit ID
     * @return string|null Current status or null if not found
     */
    public function get_status($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '') return null;

        $row = $this->db->select('status')
            ->where(['iop_id' => $iop_id, 'InActive' => 0])
            ->get('iop_opd_workflow')
            ->row();

        return $row ? strtoupper(trim($row->status)) : null;
    }

    /**
     * Get full workflow record for a visit
     * 
     * @param string $iop_id Visit ID
     * @return object|null Workflow record
     */
    public function get_workflow($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '') return null;

        return $this->db->where(['iop_id' => $iop_id, 'InActive' => 0])
            ->get('iop_opd_workflow')
            ->row();
    }

    /**
     * Transition visit to a new status
     * 
     * This is the PRIMARY method for changing visit status.
     * All status changes should go through this method.
     * 
     * @param string $iop_id Visit ID
     * @param string $new_status Target status
     * @param string|null $user_id User making the change
     * @param string|null $reason Reason for change
     * @param string|null $source Module/method triggering the change
     * @param bool $force Force transition even if invalid (admin only)
     * @return array ['success' => bool, 'message' => string, 'old_status' => string, 'new_status' => string]
     */
    public function transition($iop_id, $new_status, $user_id = null, $reason = null, $source = null, $force = false)
    {
        $iop_id = trim((string)$iop_id);
        $new_status = strtoupper(trim((string)$new_status));

        if ($iop_id === '') {
            return ['success' => false, 'message' => 'Visit ID is required'];
        }

        if (!$this->is_valid_status($new_status)) {
            return ['success' => false, 'message' => "Invalid status: {$new_status}"];
        }

        // Get current status
        $current_status = $this->get_status($iop_id);
        
        // If no workflow exists, create one
        if ($current_status === null) {
            $inferred = $this->_infer_status_from_legacy($iop_id);
            $this->_create_workflow_record($iop_id, $user_id, $inferred);
            $current_status = $inferred;
        }

        // Validate transition
        if (!$force && !$this->is_valid_transition($current_status, $new_status)) {
            $from_label = $this->get_status_label($current_status);
            $to_label = $this->get_status_label($new_status);
            return [
                'success' => false, 
                'message' => "Invalid transition: {$from_label} → {$to_label}",
                'old_status' => $current_status,
                'new_status' => $new_status
            ];
        }

        if ($new_status === self::STATUS_IN_CONSULTATION) {
            $gate = $this->_vitals_gate($iop_id);
            if (!$gate['ok']) {
                return [
                    'success' => false,
                    'message' => $gate['message'],
                    'old_status' => $current_status,
                    'new_status' => $new_status
                ];
            }
        }

        if (in_array($new_status, array(self::STATUS_CLINICALLY_CLEARED, self::STATUS_COMPLETED), true)) {
            $blocks = $this->_run_clearance_checks($iop_id);
            if (!empty($blocks)) {
                return array(
                    'success' => false,
                    'blocked' => true,
                    'messages' => $blocks,
                    'message' => implode("\n\n", $blocks),
                    'old_status' => $current_status,
                    'new_status' => $new_status
                );
            }
        }

        if ($new_status === self::STATUS_CLINICALLY_CLEARED) {
            $eligibility = $this->validate_clinical_clearance($iop_id);
            if (!$eligibility['allowed']) {
                return array(
                    'success' => false,
                    'message' => implode("\n\n", $eligibility['reasons']),
                    'old_status' => $current_status,
                    'new_status' => $new_status
                );
            }
        }

        // Begin transaction
        $this->db->trans_begin();

        try {
            // Update workflow table (source of truth)
            $this->_update_workflow_status($iop_id, $new_status, $current_status, $user_id);

            // Sync legacy fields for backward compatibility
            $this->_sync_legacy_fields($iop_id, $new_status, $user_id);

            // Log audit trail
            $this->_log_audit($iop_id, $current_status, $new_status, $user_id, $reason, $source);

            // Commit transaction
            if ($this->db->trans_status() === false) {
                $this->db->trans_rollback();
                return ['success' => false, 'message' => 'Database error during transition'];
            }

            $this->db->trans_commit();

            log_message('info', "OPD_STATUS_ENGINE: {$iop_id} transitioned {$current_status} → {$new_status} by user {$user_id}");

            return [
                'success' => true,
                'message' => 'Status updated successfully',
                'old_status' => $current_status,
                'new_status' => $new_status
            ];

        } catch (Exception $e) {
            $this->db->trans_rollback();
            log_message('error', "OPD_STATUS_ENGINE ERROR: " . $e->getMessage());
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Create initial workflow record for a visit
     */
    private function _create_workflow_record($iop_id, $user_id = null, $initial_status = null)
    {
        // Get patient_no from visit
        $visit = $this->db->select('patient_no, doctor_id')
            ->where(['IO_ID' => $iop_id, 'InActive' => 0])
            ->get('patient_details_iop')
            ->row();

        $initial_status = strtoupper(trim((string)$initial_status));
        if (!$this->is_valid_status($initial_status)) {
            $initial_status = self::STATUS_REGISTERED;
        }

        $now = date('Y-m-d H:i:s');

        $data = [
            'iop_id'              => $iop_id,
            'patient_no'          => $visit ? $visit->patient_no : null,
            'status'              => $initial_status,
            'assigned_doctor_id'  => $visit ? $visit->doctor_id : null,
            'created_at'          => $now,
            'updated_at'          => $now,
            'updated_by'          => $user_id,
            'InActive'            => 0,
        ];

        switch ($initial_status) {
            case self::STATUS_WAITING:
                $data['waiting_at'] = $now;
                break;
            case self::STATUS_IN_CONSULTATION:
                $data['in_consultation_at'] = $now;
                break;
            case self::STATUS_CLINICALLY_CLEARED:
                if ($this->_column_exists('iop_opd_workflow', 'clinically_cleared_at')) {
                    $data['clinically_cleared_at'] = $now;
                }
                break;
            case self::STATUS_COMPLETED:
                if ($this->_column_exists('iop_opd_workflow', 'completed_at')) {
                    $data['completed_at'] = $now;
                }
                break;
        }

        $this->db->insert('iop_opd_workflow', $data);
        return $this->db->insert_id();
    }

    public function initialize_visit($iop_id, $initial_status = self::STATUS_WAITING, $user_id = null, $source = null)
    {
        $iop_id = trim((string)$iop_id);
        $initial_status = strtoupper(trim((string)$initial_status));

        if ($iop_id === '') {
            return array('success' => false, 'message' => 'Visit ID is required');
        }
        if (!$this->is_valid_status($initial_status)) {
            return array('success' => false, 'message' => "Invalid status: {$initial_status}");
        }

        if (in_array($initial_status, array(self::STATUS_CLINICALLY_CLEARED, self::STATUS_COMPLETED), true)) {
            $blocks = $this->_run_clearance_checks($iop_id);
            if (!empty($blocks)) {
                return array(
                    'success' => false,
                    'blocked' => true,
                    'messages' => $blocks,
                    'message' => implode("\n\n", $blocks),
                    'old_status' => null,
                    'new_status' => $initial_status
                );
            }
        }

        $current = $this->get_status($iop_id);
        if ($current === null) {
            $this->_create_workflow_record($iop_id, $user_id, $initial_status);
            $this->_sync_legacy_fields($iop_id, $initial_status, $user_id);
            $this->_log_audit($iop_id, null, $initial_status, $user_id, 'Initial workflow status', $source);
            return array(
                'success' => true,
                'message' => 'Workflow initialized',
                'old_status' => null,
                'new_status' => $initial_status
            );
        }

        if ($current === $initial_status) {
            return array(
                'success' => true,
                'message' => 'Status unchanged',
                'old_status' => $current,
                'new_status' => $current
            );
        }

        return $this->transition($iop_id, $initial_status, $user_id, 'Initialize existing workflow', $source, true);
    }

    public function sync_assignment($iop_id, $updated_by = null)
    {
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '' || !$this->db->table_exists('iop_opd_workflow')) {
            return false;
        }

        $visit = $this->db->select('doctor_id')
            ->where(array('IO_ID' => $iop_id, 'InActive' => 0))
            ->limit(1)
            ->get('patient_details_iop')
            ->row();

        $data = array(
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $updated_by,
        );
        if ($this->_column_exists('iop_opd_workflow', 'assigned_doctor_id')) {
            $data['assigned_doctor_id'] = $visit && isset($visit->doctor_id) ? $visit->doctor_id : null;
        }

        $this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
        $this->db->update('iop_opd_workflow', $data);
        return true;
    }

    private function _vitals_gate($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '') {
            return array('ok' => false, 'message' => 'Vitals required before consultation.');
        }

        $patient_no = null;
        $vitalsDone = false;

        $visit = $this->db->where(array('IO_ID' => $iop_id, 'InActive' => 0))
            ->limit(1)
            ->get('patient_details_iop')
            ->row();

        if ($visit && isset($visit->patient_no)) {
            $patient_no = (string)$visit->patient_no;
        }

        if ($this->_column_exists('patient_details_iop', 'vitals_status')) {
            $status = ($visit && isset($visit->vitals_status)) ? strtoupper(trim((string)$visit->vitals_status)) : '';
            if ($status === 'DONE') {
                $vitalsDone = true;
            }
        }

        if (!$vitalsDone && $this->db->table_exists('iop_vital_parameters')) {
            $q = $this->db->query(
                "SELECT 1 FROM iop_vital_parameters WHERE iop_id = ? AND InActive = 0 ORDER BY dDateTime DESC LIMIT 1",
                array($iop_id)
            );
            if ($q && $q->num_rows() > 0) {
                $vitalsDone = true;
            }
        }

        if ($vitalsDone) {
            return array('ok' => true, 'message' => '');
        }

        $url = 'app/nurse_module/vitals_queue';
        if ($patient_no !== null && $patient_no !== '') {
            $url = 'app/nurse_module/record_vitals/' . urlencode($iop_id) . '/' . urlencode($patient_no);
        }

        return array(
            'ok' => false,
            'message' => 'Vitals must be recorded before starting consultation. Record vitals: ' . $url
        );
    }

    private function _infer_status_from_legacy($iop_id)
    {
        $visit = $this->db->select('nStatus, clinical_clearance_status, visit_status')
            ->where(array('IO_ID' => $iop_id, 'InActive' => 0))
            ->get('patient_details_iop')
            ->row();

        if (!$visit) {
            return self::STATUS_REGISTERED;
        }

        $nStatus = isset($visit->nStatus) ? strtoupper(trim((string)$visit->nStatus)) : '';
        $visitStatus = isset($visit->visit_status) ? strtolower(trim((string)$visit->visit_status)) : '';
        $clin = isset($visit->clinical_clearance_status) ? (int)$visit->clinical_clearance_status : 0;

        if ($nStatus === 'DISCHARGED') return self::STATUS_FINAL_CLEARED;
        if ($nStatus === 'CANCELLED') return self::STATUS_CANCELLED;
        if ($visitStatus === 'closed' && $clin === 1) return self::STATUS_CLINICALLY_CLEARED;
        if ($clin === 1) return self::STATUS_CLINICALLY_CLEARED;
        if ($visitStatus === 'closed') return self::STATUS_CANCELLED;

        $hasEvidence = false;
        if ($this->db->table_exists('iop_laboratory')) {
            $this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
            $this->db->limit(1);
            $hasEvidence = ($this->db->get('iop_laboratory')->num_rows() > 0);
        }
        if (!$hasEvidence && $this->db->table_exists('iop_medication')) {
            $this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
            $this->db->limit(1);
            $hasEvidence = ($this->db->get('iop_medication')->num_rows() > 0);
        }
        if (!$hasEvidence && $this->db->table_exists('iop_diagnosis')) {
            $this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
            $this->db->limit(1);
            $hasEvidence = ($this->db->get('iop_diagnosis')->num_rows() > 0);
        }

        return $hasEvidence ? self::STATUS_IN_CONSULTATION : self::STATUS_WAITING;
    }

    /**
     * Update workflow status record
     */
    private function _update_workflow_status($iop_id, $new_status, $old_status, $user_id)
    {
        $now = date('Y-m-d H:i:s');

        $data = [
            'status'      => $new_status,
            'prev_status' => $old_status,
            'updated_at'  => $now,
            'updated_by'  => $user_id,
        ];

        // Set status-specific timestamps
        switch ($new_status) {
            case self::STATUS_WAITING:
                $data['waiting_at'] = $now;
                break;
            case self::STATUS_IN_CONSULTATION:
                $data['in_consultation_at'] = $now;
                break;
            case self::STATUS_CLINICALLY_CLEARED:
                $data['clinically_cleared_at'] = $now;
                break;
            case 'PENDING_LAB':
                if ($this->_column_exists('iop_opd_workflow', 'pending_lab_at')) {
                    $data['pending_lab_at'] = $now;
                }
                break;
            case 'PENDING_PHARMACY':
                if ($this->_column_exists('iop_opd_workflow', 'pending_pharmacy_at')) {
                    $data['pending_pharmacy_at'] = $now;
                }
                break;
            case self::STATUS_FINAL_CLEARED:
                $data['final_cleared_at'] = $now;
                break;
            case self::STATUS_COMPLETED:
                if ($this->_column_exists('iop_opd_workflow', 'completed_at')) {
                    $data['completed_at'] = $now;
                }
                break;
        }

        $this->db->where(['iop_id' => $iop_id, 'InActive' => 0]);
        $this->db->update('iop_opd_workflow', $data);
    }

    /**
     * Sync legacy fields in patient_details_iop for backward compatibility
     */
    private function _sync_legacy_fields($iop_id, $new_status, $user_id)
    {
        if (!isset($this->legacy_field_map[$new_status])) {
            return;
        }

        $data = $this->legacy_field_map[$new_status];

        // Add clearance metadata for cleared statuses
        if (in_array($new_status, [self::STATUS_CLINICALLY_CLEARED, self::STATUS_BILLING_PENDING, self::STATUS_FINAL_CLEARED, self::STATUS_COMPLETED])) {
            if ($this->_column_exists('patient_details_iop', 'clinically_cleared_by')) {
                $data['clinically_cleared_by'] = $user_id;
                $data['clinically_cleared_at'] = date('Y-m-d H:i:s');
            }
        }

        // Reset clearance for reopened visits
        if ($new_status === self::STATUS_REOPENED) {
            if ($this->_column_exists('patient_details_iop', 'clinically_cleared_by')) {
                $data['clinically_cleared_by'] = null;
                $data['clinically_cleared_at'] = null;
            }
        }

        $this->db->where('IO_ID', $iop_id);
        $this->db->update('patient_details_iop', $data);

        // Also update iop_clearance_workflow if it exists
        $this->_sync_clearance_workflow($iop_id, $new_status, $user_id);
    }

    /**
     * Sync clearance workflow table
     */
    private function _sync_clearance_workflow($iop_id, $new_status, $user_id)
    {
        if (!$this->db->table_exists('iop_clearance_workflow')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $clearance_field = null;

        switch ($new_status) {
            case self::STATUS_CLINICALLY_CLEARED:
                $clearance_field = 'clinical_cleared_at';
                break;
            case self::STATUS_FINAL_CLEARED:
                $clearance_field = 'final_cleared_at';
                break;
        }

        if ($clearance_field) {
            $existing = $this->db->where(['iop_id' => $iop_id, 'InActive' => 0])
                ->get('iop_clearance_workflow')
                ->row();

            if ($existing) {
                $this->db->where('iop_id', $iop_id);
                $this->db->update('iop_clearance_workflow', [
                    $clearance_field => $now,
                    'updated_by' => $user_id,
                ]);
            }
        }
    }

    /**
     * Log status transition to audit table
     */
    private function _log_audit($iop_id, $old_status, $new_status, $user_id, $reason, $source)
    {
        // Get patient_no
        $wf = $this->get_workflow($iop_id);
        $patient_no = $wf ? $wf->patient_no : null;

        $this->db->insert('opd_status_audit', [
            'iop_id'         => $iop_id,
            'patient_no'     => $patient_no,
            'old_status'     => $old_status,
            'new_status'     => $new_status,
            'trigger_source' => $source,
            'trigger_module' => $this->_detect_module($source),
            'reason'         => $reason ? substr($reason, 0, 255) : null,
            'changed_by'     => $user_id,
            'changed_at'     => date('Y-m-d H:i:s'),
            'ip_address'     => $this->input->ip_address(),
        ]);
    }

    private function _detect_module($source)
    {
        if (!$source) return null;
        $source = strtolower($source);
        
        if (strpos($source, 'lab') !== false) return 'LABORATORY';
        if (strpos($source, 'pharm') !== false) return 'PHARMACY';
        if (strpos($source, 'bill') !== false) return 'BILLING';
        if (strpos($source, 'opd') !== false) return 'OPD';
        if (strpos($source, 'doctor') !== false) return 'DOCTOR';
        if (strpos($source, 'nurse') !== false) return 'NURSE';
        
        return 'SYSTEM';
    }

    // ========================================================================
    // VALIDATION METHODS
    // ========================================================================

    /**
     * Check if a status value is valid
     */
    public function is_valid_status($status)
    {
        $status = strtoupper(trim((string)$status));
        return isset($this->transitions[$status]);
    }

    /**
     * Check if a transition is valid
     */
    public function is_valid_transition($from, $to)
    {
        $from = strtoupper(trim((string)$from));
        $to = strtoupper(trim((string)$to));

        if ($from === $to) return false; // No self-transitions
        if (!isset($this->transitions[$from])) return false;

        return in_array($to, $this->transitions[$from], true);
    }

    /**
     * Get allowed next statuses from current status
     */
    public function get_allowed_transitions($status)
    {
        $status = strtoupper(trim((string)$status));
        return isset($this->transitions[$status]) ? $this->transitions[$status] : [];
    }

    /**
     * Get all valid statuses
     */
    public function get_all_statuses()
    {
        return array_keys($this->transitions);
    }

    // ========================================================================
    // DISPLAY HELPERS
    // ========================================================================

    /**
     * Get human-readable label for a status
     */
    public function get_status_label($status)
    {
        $status = strtoupper(trim((string)$status));
        return isset($this->status_labels[$status]) 
            ? $this->status_labels[$status] 
            : ucwords(strtolower(str_replace('_', ' ', $status)));
    }

    /**
     * Get Bootstrap badge class for a status
     */
    public function get_status_badge_class($status)
    {
        $status = strtoupper(trim((string)$status));
        return isset($this->status_badges[$status]) 
            ? $this->status_badges[$status] 
            : 'label-default';
    }

    /**
     * Get HTML badge for a status
     */
    public function get_status_badge($status)
    {
        $status = strtoupper(trim((string)$status));
        $label = $this->get_status_label($status);
        $class = $this->get_status_badge_class($status);
        return '<span class="label ' . $class . '">' . htmlspecialchars($label) . '</span>';
    }

    /**
     * Get status with icon
     */
    public function get_status_with_icon($status)
    {
        $icons = [
            'REGISTERED'         => 'fa-user-plus',
            'WAITING'            => 'fa-clock-o',
            'IN_CONSULTATION'    => 'fa-stethoscope',
            'LAB_PENDING'        => 'fa-flask',
            'LAB_COMPLETED'      => 'fa-flask',
            'PHARMACY_PENDING'   => 'fa-medkit',
            'PHARMACY_COMPLETED' => 'fa-medkit',
            'CLINICALLY_CLEARED' => 'fa-check-circle',
            'BILLING_PENDING'    => 'fa-money',
            'FINAL_CLEARED'      => 'fa-flag-checkered',
            'ADMITTED'           => 'fa-hospital-o',
            'CANCELLED'          => 'fa-times-circle',
            'REOPENED'           => 'fa-undo',
        ];

        $status = strtoupper(trim((string)$status));
        $icon = isset($icons[$status]) ? $icons[$status] : 'fa-question-circle';
        $label = $this->get_status_label($status);
        $class = $this->get_status_badge_class($status);

        return '<span class="label ' . $class . '"><i class="fa ' . $icon . '"></i> ' . htmlspecialchars($label) . '</span>';
    }

    // ========================================================================
    // AUTO-COMPUTE STATUS
    // ========================================================================

    /**
     * Auto-compute status based on module states
     * Call this when lab/pharmacy/billing states change
     * 
     * @param string $iop_id Visit ID
     * @return string Computed status
     */
    public function compute_status($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        
        $pending_labs = $this->count_pending_labs($iop_id);
        $pending_meds = $this->count_pending_medications($iop_id);
        $billing_balance = $this->get_billing_balance($iop_id);
        $is_clinically_cleared = $this->is_clinically_cleared($iop_id);

        // Update counts in workflow
        $this->db->where(['iop_id' => $iop_id, 'InActive' => 0]);
        $this->db->update('iop_opd_workflow', [
            'pending_lab_count' => $pending_labs,
            'pending_pharmacy_count' => $pending_meds,
            'billing_balance' => $billing_balance,
        ]);

        // Determine status based on module states
        if ($pending_labs > 0) return self::STATUS_LAB_PENDING;
        if ($pending_meds > 0) return self::STATUS_PHARMACY_PENDING;
        if (!$is_clinically_cleared) return self::STATUS_IN_CONSULTATION;
        if ($billing_balance > 0) return self::STATUS_BILLING_PENDING;
        
        return self::STATUS_FINAL_CLEARED;
    }

    /**
     * Refresh and auto-transition based on computed status
     */
    public function refresh_status($iop_id, $user_id = null, $source = null)
    {
        $current = $this->get_status($iop_id);
        $computed = $this->compute_status($iop_id);

        // Only transition if different and valid
        if ($current !== $computed && $this->is_valid_transition($current, $computed)) {
            return $this->transition($iop_id, $computed, $user_id, 'Auto-computed', $source);
        }

        return ['success' => true, 'message' => 'Status unchanged', 'status' => $current];
    }

    // ========================================================================
    // MODULE INTEGRATION HELPERS
    // ========================================================================

    /**
     * Count pending lab orders for a visit
     */
    public function count_pending_labs($iop_id)
    {
        if (!$this->db->table_exists('iop_laboratory')) return 0;

        if ($this->_column_exists('iop_laboratory', 'result_status')) {
            $q = $this->db->query(
                "SELECT COUNT(*) as cnt FROM iop_laboratory 
                 WHERE iop_id = ? AND InActive = 0 
                 AND (result_status IS NULL OR UPPER(result_status) NOT IN ('COMPLETED','VERIFIED'))",
                [$iop_id]
            );
            $r = $q ? $q->row() : null;
            return $r ? (int)$r->cnt : 0;
        }

        if ($this->_column_exists('iop_laboratory', 'result')) {
            $q = $this->db->query(
                "SELECT COUNT(*) as cnt FROM iop_laboratory 
                 WHERE iop_id = ? AND InActive = 0 
                 AND (result IS NULL OR TRIM(result) = '')",
                [$iop_id]
            );
            $r = $q ? $q->row() : null;
            return $r ? (int)$r->cnt : 0;
        }

        $q = $this->db->query(
            "SELECT COUNT(*) as cnt FROM iop_laboratory WHERE iop_id = ? AND InActive = 0",
            [$iop_id]
        );
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->cnt : 0;
    }

    /**
     * Count pending medications for a visit
     */
    public function count_pending_medications($iop_id)
    {
        if (!$this->db->table_exists('iop_medication')) return 0;

        if ($this->_column_exists('iop_medication', 'dispensing_status')) {
            $q = $this->db->query(
                "SELECT COUNT(*) as cnt FROM iop_medication 
                 WHERE iop_id = ? AND InActive = 0 
                 AND (dispensing_status IS NULL OR UPPER(dispensing_status) NOT IN ('DISPENSED','UNAVAILABLE','CANCELLED'))",
                [$iop_id]
            );
            $r = $q ? $q->row() : null;
            return $r ? (int)$r->cnt : 0;
        }

        $q = $this->db->query(
            "SELECT COUNT(*) as cnt FROM iop_medication WHERE iop_id = ? AND InActive = 0",
            [$iop_id]
        );
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->cnt : 0;
    }

    /**
     * Get outstanding billing balance for a visit
     */
    public function get_billing_balance($iop_id)
    {
        if ($this->db->table_exists('billing_transactions') && $this->_column_exists('billing_transactions', 'balance_amount')) {
            $q = $this->db->query(
                "SELECT COUNT(*) AS c, COALESCE(SUM(balance_amount), 0) AS balance
                 FROM billing_transactions
                 WHERE encounter_id = ? AND InActive = 0",
                [$iop_id]
            );
            $r = $q ? $q->row() : null;
            if ($r && isset($r->c) && (int)$r->c > 0) {
                return max(0, (float)$r->balance);
            }
        }

        if (!$this->db->table_exists('iop_billing')) return 0;

        $discExpr = $this->_discount_expr('iop_billing', 'b');

        $q = $this->db->query(
            "SELECT COALESCE(SUM(
                b.total_amount - {$discExpr} - COALESCE(
                    (SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0
                )
            ), 0) AS balance
            FROM iop_billing b
            WHERE b.iop_id = ? AND b.InActive = 0",
            [$iop_id]
        );
        $r = $q ? $q->row() : null;
        return $r ? max(0, (float)$r->balance) : 0;
    }

    /**
     * Central clearance gate for every route that moves a visit to CLINICALLY_CLEARED.
     *
     * @param string $iop_id Visit ID
     * @return array ['allowed' => bool, 'reasons' => array]
     */
    private function _run_clearance_checks($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        $blocks = array();
        if ($iop_id === '') {
            return array('Invalid visit ID.');
        }

        $this->load->model('app/billing_model');
        if (isset($this->billing_model)
            && method_exists($this->billing_model, 'has_unpaid_invoices')
            && $this->billing_model->has_unpaid_invoices($iop_id)) {
            $blocks[] = 'This patient has outstanding payments. Please clear all bills at the cashier before clinical discharge.';
        }

        $this->load->model('app/laboratory_model');
        if (isset($this->laboratory_model)
            && method_exists($this->laboratory_model, 'has_pending_results')
            && $this->laboratory_model->has_pending_results($iop_id)) {
            $blocks[] = 'This patient has laboratory requests awaiting results. Please ensure all lab results are entered before discharge.';
        }

        if (isset($this->laboratory_model)
            && method_exists($this->laboratory_model, 'has_pending_imaging')
            && $this->laboratory_model->has_pending_imaging($iop_id)) {
            $blocks[] = 'This patient has pending sonography or radiology requests. Please ensure all imaging reports are completed before discharge.';
        }

        $this->load->model('app/procedure_request_model');
        if (isset($this->procedure_request_model)
            && method_exists($this->procedure_request_model, 'has_pending_procedures')
            && $this->procedure_request_model->has_pending_procedures($iop_id)) {
            $blocks[] = 'This patient has procedures that have not been completed. Please complete or cancel all pending procedures before discharge.';
        }

        return $blocks;
    }

    public function validate_clinical_clearance($iop_id)
    {
        $iop_id = trim((string)$iop_id);
        $reasons = array();

        if ($iop_id === '') {
            return array('allowed' => false, 'reasons' => array('Invalid visit ID'));
        }

        $snapshot = $this->get_closure_snapshot($iop_id, 24);
        if (!$snapshot) {
            return array('allowed' => false, 'reasons' => array('Visit not found'));
        }

        if ($this->_has_outstanding_clinical_billing($iop_id, $snapshot)) {
            $reasons[] = "Clinical clearance blocked:\nOutstanding payment exists for this visit.\nPlease complete payment before clearance.";
        }

        if ($this->_count_pending_laboratory_clearance($iop_id) > 0) {
            $reasons[] = "Clinical clearance blocked:\nPending laboratory investigations exist.";
        }

        if ($this->_count_pending_sonography_clearance($iop_id) > 0) {
            $reasons[] = "Clinical clearance blocked:\nPending sonography investigations exist.";
        }

        if ($this->_count_pending_radiology_clearance($iop_id) > 0) {
            $reasons[] = "Clinical clearance blocked:\nPending radiology investigations exist.";
        }

        if ($this->_count_pending_procedure_clearance($iop_id) > 0) {
            $reasons[] = "Clinical clearance blocked:\nPending procedures exist.";
        }

        return array(
            'allowed' => (count($reasons) === 0),
            'reasons' => $reasons
        );
    }

    private function _has_outstanding_clinical_billing($iop_id, $snapshot = null)
    {
        if (is_array($snapshot) && isset($snapshot['billing_balance']) && (float)$snapshot['billing_balance'] > 0.001) {
            return true;
        }

        if ($this->get_billing_balance($iop_id) > 0.001) {
            return true;
        }

        if (!$this->db->table_exists('iop_billing')) {
            return false;
        }

        if ($this->_column_exists('iop_billing', 'payment_status')) {
            $q = $this->db->query(
                "SELECT COUNT(*) AS cnt
                 FROM iop_billing
                 WHERE iop_id = ? AND InActive = 0
                   AND UPPER(TRIM(COALESCE(payment_status, ''))) IN ('UNPAID','PARTIAL','PENDING')",
                array($iop_id)
            );
            $r = $q ? $q->row() : null;
            if ($r && (int)$r->cnt > 0) {
                return true;
            }
        }

        return false;
    }

    private function _count_pending_laboratory_clearance($iop_id)
    {
        if (!$this->db->table_exists('iop_laboratory')) {
            return 0;
        }

        $sonoCat = $this->_get_sonography_category_id();
        $radioCat = $this->_get_radiology_category_id();
        $wfJoin = '';
        $wfCond = '';
        if ($this->db->table_exists('iop_laboratory_workflow')) {
            $wfJoin = " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 ";
            $wfCond = " OR UPPER(TRIM(COALESCE(W.status,''))) IN ('REQUESTED','IN_PROGRESS') ";
        }

        $sql = "SELECT COUNT(*) AS cnt
                FROM iop_laboratory L
                {$wfJoin}
                WHERE L.iop_id = ? AND L.InActive = 0
                  AND (L.category_id IS NULL OR (L.category_id != ? AND L.category_id != ?))
                  AND ((L.result IS NULL OR TRIM(L.result) = ''){$wfCond})";
        $q = $this->db->query($sql, array($iop_id, $sonoCat, $radioCat));
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->cnt : 0;
    }

    private function _count_pending_sonography_clearance($iop_id)
    {
        $count = 0;
        $pendingWorkflow = "('REQUESTED','PENDING','BILLED','PAID','SCHEDULED','IN_PROGRESS','PERFORMED')";

        if ($this->db->table_exists('iop_sonography_charge')) {
            $wfJoin = '';
            $wfCond = '';
            if ($this->db->table_exists('iop_laboratory_workflow')) {
                $wfJoin = " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = C.io_lab_id AND W.InActive = 0 ";
                $wfCond = " OR UPPER(TRIM(COALESCE(W.status,''))) IN {$pendingWorkflow} ";
            }
            $sql = "SELECT COUNT(*) AS cnt
                    FROM iop_sonography_charge C
                    LEFT JOIN iop_laboratory L ON L.io_lab_id = C.io_lab_id AND L.InActive = 0
                    {$wfJoin}
                    WHERE C.iop_id = ? AND C.InActive = 0
                      AND ((L.result IS NULL OR TRIM(L.result) = ''){$wfCond})";
            $q = $this->db->query($sql, array($iop_id));
            $r = $q ? $q->row() : null;
            $count += $r ? (int)$r->cnt : 0;
        }

        if ($this->db->table_exists('iop_laboratory')) {
            $sonoCat = $this->_get_sonography_category_id();
            $wfJoin = '';
            $wfCond = '';
            if ($this->db->table_exists('iop_laboratory_workflow')) {
                $wfJoin = " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 ";
                $wfCond = " OR UPPER(TRIM(COALESCE(W.status,''))) IN {$pendingWorkflow} ";
            }
            $sql = "SELECT COUNT(*) AS cnt
                    FROM iop_laboratory L
                    {$wfJoin}
                    WHERE L.iop_id = ? AND L.InActive = 0 AND L.category_id = ?
                      AND ((L.result IS NULL OR TRIM(L.result) = ''){$wfCond})";
            $q = $this->db->query($sql, array($iop_id, $sonoCat));
            $r = $q ? $q->row() : null;
            $count += $r ? (int)$r->cnt : 0;
        }

        return $count;
    }

    private function _count_pending_radiology_clearance($iop_id)
    {
        $count = 0;
        $pendingWorkflow = "('REQUESTED','PENDING','BILLED','PAID','SCHEDULED','IN_PROGRESS','PERFORMED')";

        if ($this->db->table_exists('iop_laboratory')) {
            $radioCat = $this->_get_radiology_category_id();
            $wfJoin = '';
            $wfCond = '';
            if ($this->db->table_exists('iop_laboratory_workflow')) {
                $wfJoin = " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 ";
                $wfCond = " OR UPPER(TRIM(COALESCE(W.status,''))) IN {$pendingWorkflow} ";
            }
            $sql = "SELECT COUNT(*) AS cnt
                    FROM iop_laboratory L
                    {$wfJoin}
                    WHERE L.iop_id = ? AND L.InActive = 0 AND L.category_id = ?
                      AND ((L.result IS NULL OR TRIM(L.result) = ''){$wfCond})";
            $q = $this->db->query($sql, array($iop_id, $radioCat));
            $r = $q ? $q->row() : null;
            $count += $r ? (int)$r->cnt : 0;
        }

        if ($this->db->table_exists('radiology_orders')) {
            $q = $this->db->query(
                "SELECT COUNT(*) AS cnt
                 FROM radiology_orders
                 WHERE iop_id = ? AND InActive = 0
                   AND LOWER(TRIM(COALESCE(status, ''))) IN ('pending','in_progress')",
                array($iop_id)
            );
            $r = $q ? $q->row() : null;
            $count += $r ? (int)$r->cnt : 0;
        }

        return $count;
    }

    private function _count_pending_procedure_clearance($iop_id)
    {
        if (!$this->db->table_exists('iop_procedure_request')) {
            return 0;
        }

        $q = $this->db->query(
            "SELECT COUNT(*) AS cnt
             FROM iop_procedure_request
             WHERE iop_id = ? AND InActive = 0
               AND UPPER(TRIM(COALESCE(status, ''))) IN ('REQUESTED','ORDERED','PENDING')",
            array($iop_id)
        );
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->cnt : 0;
    }

    private function _get_sonography_category_id()
    {
        if ($this->db->table_exists('bill_group_name')) {
            $r = $this->db->where('group_id', 18)->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
            if ($r && isset($r->group_id)) {
                return (int)$r->group_id;
            }
            $r = $this->db->like('group_name', 'sonograph', 'both')->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
            if ($r && isset($r->group_id)) {
                return (int)$r->group_id;
            }
        }
        return 18;
    }

    private function _get_radiology_category_id()
    {
        if ($this->db->table_exists('bill_group_name')) {
            $r = $this->db->where('group_id', 16)->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
            if ($r && isset($r->group_id)) {
                return (int)$r->group_id;
            }
            $r = $this->db->like('group_name', 'radiol', 'after')->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
            if ($r && isset($r->group_id)) {
                return (int)$r->group_id;
            }
            $r = $this->db->like('group_name', 'x-ray', 'both')->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
            if ($r && isset($r->group_id)) {
                return (int)$r->group_id;
            }
        }
        return 16;
    }

    /**
     * Check if visit is clinically cleared
     */
    public function is_clinically_cleared($iop_id)
    {
        $visit = $this->db->select('clinical_clearance_status')
            ->where(['IO_ID' => $iop_id, 'InActive' => 0])
            ->get('patient_details_iop')
            ->row();

        return ($visit && isset($visit->clinical_clearance_status) && $visit->clinical_clearance_status == 1);
    }

    // ========================================================================
    // BULK OPERATIONS
    // ========================================================================

    /**
     * Get workflow map for multiple visits (for list views)
     */
    public function get_workflow_map($iop_ids)
    {
        $map = [];
        if (!is_array($iop_ids) || count($iop_ids) === 0) return $map;

        $this->db->where_in('iop_id', $iop_ids);
        $this->db->where('InActive', 0);
        $rows = $this->db->get('iop_opd_workflow')->result();

        foreach ($rows as $r) {
            $map[$r->iop_id] = $r;
        }

        return $map;
    }

    /**
     * Get status counts for dashboard
     */
    public function get_status_counts($date = null, $doctor_id = null)
    {
        $date = $date ?: date('Y-m-d');

        $sql = "SELECT w.status, COUNT(*) as cnt
                FROM iop_opd_workflow w
                JOIN patient_details_iop p ON p.IO_ID = w.iop_id AND p.InActive = 0
                WHERE w.InActive = 0 AND p.date_visit = ?";
        $params = [$date];

        if ($doctor_id) {
            $sql .= " AND p.doctor_id = ?";
            $params[] = $doctor_id;
        }

        $sql .= " GROUP BY w.status";

        $q = $this->db->query($sql, $params);
        $results = $q ? $q->result() : [];

        $counts = [];
        foreach ($results as $r) {
            $counts[$r->status] = (int)$r->cnt;
        }

        return $counts;
    }

    /**
     * Get status history for a visit
     */
    public function get_status_history($iop_id, $limit = 20)
    {
        return $this->db->select('*')
            ->where('iop_id', $iop_id)
            ->order_by('changed_at', 'DESC')
            ->limit($limit)
            ->get('opd_status_audit')
            ->result();
    }

    public function get_stale_visits_for_closure($hours = 24, $limit = 100)
    {
        $hours = (int)$hours;
        $limit = (int)$limit;
        if ($hours < 1) $hours = 24;
        if ($limit < 1) $limit = 100;

        if (!$this->db->table_exists('patient_details_iop')) {
            return array();
        }

        $sql = "SELECT
                    P.IO_ID AS iop_id,
                    P.patient_no,
                    P.date_visit,
                    P.time_visit,
                    P.doctor_id,
                    P.nStatus,
                    P.visit_status,
                    COALESCE(W.status, '') AS wf_status,
                    CONCAT_WS(' ', TP.cValue, PI.firstname, PI.middlename, PI.lastname) AS patient_name,
                    CONCAT_WS(' ', TD.cValue, U.firstname, U.middlename, U.lastname) AS doctor_name
                FROM patient_details_iop P
                LEFT JOIN iop_opd_workflow W ON W.iop_id = P.IO_ID AND W.InActive = 0
                LEFT JOIN patient_personal_info PI ON PI.patient_no = P.patient_no
                LEFT JOIN system_parameters TP ON TP.param_id = PI.title
                LEFT JOIN users U ON U.user_id = P.doctor_id
                LEFT JOIN system_parameters TD ON TD.param_id = U.title
                WHERE P.InActive = 0
                  AND UPPER(P.patient_type) = 'OPD'
                  AND P.nStatus = 'Pending'
                  AND P.visit_status = 'active'
                  AND STR_TO_DATE(CONCAT(P.date_visit, ' ', COALESCE(P.time_visit,'00:00:00')), '%Y-%m-%d %H:%i:%s') < DATE_SUB(NOW(), INTERVAL ? HOUR)
                ORDER BY P.date_visit DESC, P.time_visit DESC
                LIMIT " . (int)$limit;

        $q = $this->db->query($sql, array($hours));
        $rows = $q ? $q->result_array() : array();
        if (!is_array($rows) || count($rows) === 0) {
            return array();
        }

        $iop_ids = array();
        foreach ($rows as $r) {
            $iop_ids[] = (string)$r['iop_id'];
        }

        $labMap = $this->_pending_lab_map($iop_ids);
        $rxMap  = $this->_pending_rx_map($iop_ids);
        $balMap = $this->_billing_balance_map($iop_ids);

        $out = array();
        $nowTs = time();
        foreach ($rows as $r) {
            $iop_id = (string)$r['iop_id'];
            $wf = strtoupper(trim((string)$r['wf_status']));
            if ($wf === '') {
                $wf = $this->_infer_status_from_legacy($iop_id);
            }
            $visitDt = trim((string)$r['date_visit']) . ' ' . (trim((string)$r['time_visit']) !== '' ? trim((string)$r['time_visit']) : '00:00:00');
            $visitTs = strtotime($visitDt);
            $diff = ($visitTs > 0) ? max(0, ($nowTs - $visitTs)) : 0;
            $diffH = (int)floor($diff / 3600);
            $diffD = (int)floor($diffH / 24);
            $ageHint = ($diffD > 0) ? ($diffD . 'd ' . ($diffH % 24) . 'h ago') : ($diffH . 'h ago');

            $pendingLabs = isset($labMap[$iop_id]) ? (int)$labMap[$iop_id] : 0;
            $pendingRx   = isset($rxMap[$iop_id]) ? (int)$rxMap[$iop_id] : 0;
            $balance     = isset($balMap[$iop_id]) ? (float)$balMap[$iop_id] : 0.0;

            $blockers = array();
            if ($pendingLabs > 0) $blockers[] = 'Pending lab';
            if ($pendingRx > 0) $blockers[] = 'Pending pharmacy';
            if ($balance > 0.001) $blockers[] = 'Outstanding bill';

            $canClose = (count($blockers) === 0);

            $recommended = '';
            if (in_array($wf, array(self::STATUS_REGISTERED, self::STATUS_WAITING), true)) {
                $recommended = 'CANCEL';
            } elseif (in_array($wf, array(self::STATUS_IN_CONSULTATION, self::STATUS_LAB_COMPLETED, self::STATUS_PHARMACY_COMPLETED), true)) {
                $recommended = 'CLINICAL_CLEAR';
            }

            $out[] = array(
                'iop_id' => $iop_id,
                'patient_no' => (string)$r['patient_no'],
                'patient_name' => trim((string)$r['patient_name']) !== '' ? (string)$r['patient_name'] : (string)$r['patient_no'],
                'doctor_name' => trim((string)$r['doctor_name']) !== '' ? (string)$r['doctor_name'] : '',
                'visit_datetime' => $visitDt,
                'age_hint' => $ageHint,
                'status' => $wf,
                'status_badge' => $this->get_status_with_icon($wf),
                'pending_lab_count' => $pendingLabs,
                'pending_rx_count' => $pendingRx,
                'billing_balance' => $balance,
                'can_close' => $canClose,
                'recommended_action' => $recommended,
                'blocker_text' => implode(', ', $blockers)
            );
        }

        return $out;
    }

    public function get_closure_snapshot($iop_id, $hours = 24)
    {
        $iop_id = trim((string)$iop_id);
        $hours = (int)$hours;
        if ($hours < 1) $hours = 24;
        if ($iop_id === '') return null;

        $visit = $this->db->select('IO_ID, patient_no, date_visit, time_visit, nStatus, visit_status')
            ->where(array('IO_ID' => $iop_id, 'InActive' => 0))
            ->get('patient_details_iop')
            ->row();
        if (!$visit) return null;

        $visitDt = trim((string)$visit->date_visit) . ' ' . (trim((string)$visit->time_visit) !== '' ? trim((string)$visit->time_visit) : '00:00:00');
        $visitTs = strtotime($visitDt);
        $isStale = ($visitTs > 0) ? ((time() - $visitTs) >= ($hours * 3600)) : false;

        $status = $this->get_status($iop_id);
        $status = $status ? strtoupper(trim((string)$status)) : self::STATUS_WAITING;

        $pendingLabs = $this->count_pending_labs($iop_id);
        $pendingRx = $this->count_pending_medications($iop_id);
        $balance = $this->get_billing_balance($iop_id);

        $blockers = array();
        if ($pendingLabs > 0) $blockers[] = 'Pending lab';
        if ($pendingRx > 0) $blockers[] = 'Pending pharmacy';
        if ($balance > 0.001) $blockers[] = 'Outstanding bill';

        $canClose = (count($blockers) === 0);

        $recommended = '';
        if (in_array($status, array(self::STATUS_REGISTERED, self::STATUS_WAITING), true)) {
            $recommended = 'CANCEL';
        } elseif (in_array($status, array(self::STATUS_IN_CONSULTATION, self::STATUS_LAB_COMPLETED, self::STATUS_PHARMACY_COMPLETED), true)) {
            $recommended = 'CLINICAL_CLEAR';
        }

        return array(
            'iop_id' => $iop_id,
            'patient_no' => isset($visit->patient_no) ? (string)$visit->patient_no : '',
            'visit_datetime' => $visitDt,
            'is_stale' => $isStale,
            'status' => $status,
            'pending_lab_count' => (int)$pendingLabs,
            'pending_rx_count' => (int)$pendingRx,
            'billing_balance' => (float)$balance,
            'can_close' => $canClose,
            'recommended_action' => $recommended,
            'blocker_text' => implode(', ', $blockers),
            'nStatus' => isset($visit->nStatus) ? (string)$visit->nStatus : '',
            'visit_status' => isset($visit->visit_status) ? (string)$visit->visit_status : ''
        );
    }

    private function _pending_lab_map($iop_ids)
    {
        $map = array();
        if (!is_array($iop_ids) || count($iop_ids) === 0) return $map;
        if (!$this->db->table_exists('iop_laboratory')) return $map;

        $cond = '';
        if ($this->_column_exists('iop_laboratory', 'result_status')) {
            $cond = " AND (result_status IS NULL OR UPPER(result_status) NOT IN ('COMPLETED','VERIFIED'))";
        } elseif ($this->_column_exists('iop_laboratory', 'result')) {
            $cond = " AND (result IS NULL OR TRIM(result) = '')";
        }

        $escaped = array();
        foreach ($iop_ids as $id) {
            $escaped[] = $this->db->escape((string)$id);
        }
        $in = implode(',', $escaped);

        $sql = "SELECT iop_id, COUNT(*) AS c FROM iop_laboratory WHERE InActive = 0 AND iop_id IN ({$in}){$cond} GROUP BY iop_id";
        $q = $this->db->query($sql);
        $rows = $q ? $q->result() : array();
        foreach ($rows as $r) {
            $map[(string)$r->iop_id] = (int)$r->c;
        }
        return $map;
    }

    private function _pending_rx_map($iop_ids)
    {
        $map = array();
        if (!is_array($iop_ids) || count($iop_ids) === 0) return $map;
        if (!$this->db->table_exists('iop_medication')) return $map;

        $cond = '';
        if ($this->_column_exists('iop_medication', 'dispensing_status')) {
            $cond = " AND (dispensing_status IS NULL OR UPPER(dispensing_status) NOT IN ('DISPENSED','UNAVAILABLE','CANCELLED'))";
        }

        $escaped = array();
        foreach ($iop_ids as $id) {
            $escaped[] = $this->db->escape((string)$id);
        }
        $in = implode(',', $escaped);

        $sql = "SELECT iop_id, COUNT(*) AS c FROM iop_medication WHERE InActive = 0 AND iop_id IN ({$in}){$cond} GROUP BY iop_id";
        $q = $this->db->query($sql);
        $rows = $q ? $q->result() : array();
        foreach ($rows as $r) {
            $map[(string)$r->iop_id] = (int)$r->c;
        }
        return $map;
    }

    private function _billing_balance_map($iop_ids)
    {
        $map = array();
        if (!is_array($iop_ids) || count($iop_ids) === 0) return $map;

        foreach ($iop_ids as $id) {
            $map[(string)$id] = 0.0;
        }

        $escaped = array();
        foreach ($iop_ids as $id) {
            $escaped[] = $this->db->escape((string)$id);
        }
        $in = implode(',', $escaped);

        if ($this->db->table_exists('billing_transactions') && $this->_column_exists('billing_transactions', 'balance_amount')) {
            $sql = "SELECT encounter_id AS iop_id, COUNT(*) AS c, COALESCE(SUM(balance_amount), 0) AS bal
                    FROM billing_transactions
                    WHERE InActive = 0 AND encounter_id IN ({$in})
                    GROUP BY encounter_id";
            $q = $this->db->query($sql);
            $rows = $q ? $q->result() : array();
            $hasAny = false;
            foreach ($rows as $r) {
                if (isset($r->c) && (int)$r->c > 0) {
                    $hasAny = true;
                    $map[(string)$r->iop_id] = (float)$r->bal;
                }
            }
            if ($hasAny) {
                return $map;
            }
        }

        if (!$this->db->table_exists('iop_billing')) {
            return $map;
        }

        $discExpr = $this->_discount_expr('iop_billing', 'b');

        $sql = "SELECT b.iop_id AS iop_id,
                       COALESCE(SUM(
                           b.total_amount - {$discExpr} - COALESCE(
                               (SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0
                           )
                       ), 0) AS bal
                FROM iop_billing b
                WHERE b.InActive = 0 AND b.iop_id IN ({$in})
                GROUP BY b.iop_id";
        $q = $this->db->query($sql);
        $rows = $q ? $q->result() : array();
        foreach ($rows as $r) {
            $map[(string)$r->iop_id] = (float)$r->bal;
        }
        return $map;
    }

    // ========================================================================
    // CONVENIENCE METHODS FOR COMMON TRANSITIONS
    // ========================================================================

    /**
     * Start consultation (WAITING → IN_CONSULTATION)
     */
    public function start_consultation($iop_id, $user_id)
    {
        return $this->transition($iop_id, self::STATUS_IN_CONSULTATION, $user_id, null, 'opd::start_consultation');
    }

    /**
     * Clinical clear (IN_CONSULTATION → CLINICALLY_CLEARED)
     * Automatically advances the next WAITING patient in the queue.
     */
    public function clinical_clear($iop_id, $user_id)
    {
        $result = $this->transition($iop_id, self::STATUS_CLINICALLY_CLEARED, $user_id, null, 'opd::clinical_clear');
        if ($result['success']) {
            $this->advance_queue($iop_id, $user_id);
        }
        return $result;
    }

    /**
     * Advance the OPD queue for the doctor assigned to $completed_iop_id.
     * Finds the oldest WAITING visit for the same doctor today and promotes
     * it to IN_CONSULTATION.
     *
     * @param string      $completed_iop_id  The visit that just finished/was cleared
     * @param string|null $user_id           User triggering the advancement
     * @return array|null Result of the transition, or null if no one was waiting
     */
    public function advance_queue($completed_iop_id, $user_id = null)
    {
        $completed_iop_id = trim((string)$completed_iop_id);

        // Find the doctor assigned to the completed visit
        $visit = $this->db->select('doctor_id, date_visit')
            ->where(['IO_ID' => $completed_iop_id, 'InActive' => 0])
            ->get('patient_details_iop')
            ->row();

        if (!$visit || empty($visit->doctor_id)) {
            return null; // No doctor assigned — cannot determine queue
        }

        $doctor_id  = (string)$visit->doctor_id;
        $visit_date = $visit->date_visit; // Only advance within the same calendar day

        $vitalsClause = '';
        if ($this->_column_exists('patient_details_iop', 'vitals_status')) {
            $vitalsClause = " AND P.vitals_status = 'DONE' ";
        } elseif ($this->db->table_exists('iop_vital_parameters')) {
            $vitalsClause = " AND EXISTS (SELECT 1 FROM iop_vital_parameters VP WHERE VP.iop_id = P.IO_ID AND VP.InActive = 0) ";
        }

        $sql = "SELECT P.IO_ID
                FROM patient_details_iop P
                INNER JOIN iop_opd_workflow W ON W.iop_id = P.IO_ID AND W.InActive = 0
                WHERE P.doctor_id      = ?
                  AND P.date_visit     = ?
                  AND P.InActive       = 0
                  AND W.status         = 'WAITING'
                  AND P.IO_ID         != ?
                  {$vitalsClause}
                ORDER BY COALESCE(W.waiting_at, P.time_visit) ASC
                LIMIT 1";

        $q = $this->db->query($sql, array($doctor_id, $visit_date, $completed_iop_id));
        $next = ($q && $q->num_rows() > 0) ? $q->row() : null;

        if (!$next) {
            log_message('info', "OPD_QUEUE: No WAITING patients for doctor {$doctor_id} — queue empty.");
            return null;
        }

        $next_iop_id = (string)$next->IO_ID;
        log_message('info', "OPD_QUEUE: Advancing {$next_iop_id} → IN_CONSULTATION (doctor {$doctor_id}, after {$completed_iop_id})");

        return $this->transition(
            $next_iop_id,
            self::STATUS_IN_CONSULTATION,
            $user_id,
            "Auto-advanced: previous patient ({$completed_iop_id}) cleared",
            'queue::auto_advance'
        );
    }

    /**
     * Final clear / discharge (CLINICALLY_CLEARED → FINAL_CLEARED)
     */
    public function final_clear($iop_id, $user_id)
    {
        return $this->transition($iop_id, self::STATUS_FINAL_CLEARED, $user_id, null, 'billing::final_clearance');
    }

    /**
     * Reopen visit (any terminal → REOPENED → WAITING)
     */
    public function reopen($iop_id, $user_id, $reason = null)
    {
        $result = $this->transition($iop_id, self::STATUS_REOPENED, $user_id, $reason, 'admin::reopen', true);
        if ($result['success']) {
            // Immediately transition to WAITING
            return $this->transition($iop_id, self::STATUS_WAITING, $user_id, $reason, 'admin::reopen');
        }
        return $result;
    }

    /**
     * Notify lab pending
     */
    public function notify_lab_pending($iop_id, $user_id = null)
    {
        $current = $this->get_status($iop_id);
        if (in_array($current, [self::STATUS_IN_CONSULTATION, self::STATUS_WAITING])) {
            return $this->transition($iop_id, self::STATUS_LAB_PENDING, $user_id, null, 'laboratory::request');
        }
        return ['success' => true, 'message' => 'Status unchanged'];
    }

    /**
     * Notify lab completed
     */
    public function notify_lab_completed($iop_id, $user_id = null)
    {
        if ($this->count_pending_labs($iop_id) === 0) {
            $current = $this->get_status($iop_id);
            if ($current === self::STATUS_LAB_PENDING) {
                return $this->transition($iop_id, self::STATUS_LAB_COMPLETED, $user_id, null, 'laboratory::complete');
            }
        }
        return ['success' => true, 'message' => 'Status unchanged'];
    }

    /**
     * Notify pharmacy pending
     */
    public function notify_pharmacy_pending($iop_id, $user_id = null)
    {
        $current = $this->get_status($iop_id);
        if (in_array($current, [self::STATUS_IN_CONSULTATION, self::STATUS_WAITING, self::STATUS_LAB_COMPLETED])) {
            return $this->transition($iop_id, self::STATUS_PHARMACY_PENDING, $user_id, null, 'pharmacy::prescription');
        }
        return ['success' => true, 'message' => 'Status unchanged'];
    }

    /**
     * Notify pharmacy completed
     */
    public function notify_pharmacy_completed($iop_id, $user_id = null)
    {
        if ($this->count_pending_medications($iop_id) === 0) {
            $current = $this->get_status($iop_id);
            if ($current === self::STATUS_PHARMACY_PENDING) {
                return $this->transition($iop_id, self::STATUS_PHARMACY_COMPLETED, $user_id, null, 'pharmacy::dispense');
            }
        }
        return ['success' => true, 'message' => 'Status unchanged'];
    }
}
