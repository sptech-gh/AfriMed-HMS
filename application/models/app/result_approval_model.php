<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Result Approval Model
 * Enterprise Safety Audit Implementation
 * 
 * Features:
 * - Critical Result Edit Restrictions (only authorized roles can modify)
 * - Verification Role Enforcement (correct role verification)
 * - Supervisor Approval Workflow (critical changes require approval)
 * - Full Audit Trail
 */
class Result_approval_model extends CI_Model
{
    private static $schema_initialized = false;

    // Role definitions for verification
    private $lab_verification_roles = ['admin', 'lab_supervisor', 'pathologist', 'senior_lab_tech'];
    private $radiology_verification_roles = ['admin', 'radiologist', 'senior_radiologist'];
    private $sonography_verification_roles = ['admin', 'sonographer', 'senior_sonographer', 'radiologist'];
    
    // Roles that can edit critical results
    private $critical_edit_roles = ['admin', 'lab_supervisor', 'pathologist', 'radiologist', 'senior_radiologist'];
    
    // Roles that can approve critical changes
    private $supervisor_roles = ['admin', 'lab_supervisor', 'pathologist', 'chief_pathologist', 'radiologist', 'senior_radiologist'];

    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  UTILITY METHODS                                                   */
    /* ================================================================== */

    private function table_exists($table)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table));
        return ($q && $q->num_rows() > 0);
    }

    private function column_exists($table, $column)
    {
        if (!$this->table_exists($table)) return false;
        $q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($column));
        return ($q && $q->num_rows() > 0);
    }

    private function safe_alter($sql)
    {
        $old = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($old !== null) $this->db->db_debug = false;
        try { $this->db->query($sql); } catch (Exception $e) {}
        if ($old !== null) $this->db->db_debug = $old;
    }

    private function resolve_record_patient_no($diagnostic_type, $record_id)
    {
        $diagnostic_type = strtoupper(trim((string)$diagnostic_type));
        $record_id = (int)$record_id;
        if ($record_id <= 0) {
            return null;
        }

        if (!$this->table_exists('iop_laboratory') || !$this->table_exists('patient_details_iop')) {
            return null;
        }

        $row = $this->db
            ->select('PD.patient_no')
            ->from('iop_laboratory L')
            ->join('patient_details_iop PD', 'PD.IO_ID = L.iop_id AND PD.InActive = 0', 'left')
            ->where('L.io_lab_id', $record_id)
            ->where('L.InActive', 0)
            ->limit(1)
            ->get()
            ->row();

        if (!$row || !isset($row->patient_no)) {
            return null;
        }
        $p = trim((string)$row->patient_no);
        return $p !== '' ? $p : null;
    }

    private function validate_record_patient_binding($diagnostic_type, $record_id, $patient_no)
    {
        $patient_no = trim((string)$patient_no);
        if ($patient_no === '') {
            return ['ok' => false, 'error' => 'Missing patient context'];
        }

        $dbPatient = $this->resolve_record_patient_no($diagnostic_type, $record_id);
        if ($dbPatient === null) {
            return ['ok' => false, 'error' => 'Record not found'];
        }

        if ($dbPatient !== $patient_no) {
            return ['ok' => false, 'error' => 'Patient mismatch'];
        }

        return ['ok' => true];
    }

    /* ================================================================== */
    /*  SCHEMA INSTALLATION                                               */
    /* ================================================================== */

    public function ensure_approval_schema()
    {
        if (self::$schema_initialized) return true;
        self::$schema_initialized = true;

        $this->ensure_result_edit_restrictions_schema();
        $this->ensure_verification_roles_schema();
        $this->ensure_supervisor_approval_schema();
        $this->ensure_result_lock_schema();
        $this->seed_default_config();

        return true;
    }

    private function ensure_result_edit_restrictions_schema()
    {
        // Result edit permissions table
        $this->db->query("CREATE TABLE IF NOT EXISTS `result_edit_permissions` (
            `permission_id` INT AUTO_INCREMENT PRIMARY KEY,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `result_category` VARCHAR(50) NOT NULL DEFAULT 'GENERAL',
            `is_critical` TINYINT(1) NOT NULL DEFAULT 0,
            `allowed_roles` TEXT NOT NULL,
            `requires_supervisor_approval` TINYINT(1) NOT NULL DEFAULT 0,
            `edit_window_hours` INT DEFAULT 24,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `uk_type_category` (`diagnostic_type`, `result_category`),
            INDEX `idx_active` (`is_active`, `InActive`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Result edit requests (for critical results requiring approval)
        $this->db->query("CREATE TABLE IF NOT EXISTS `result_edit_requests` (
            `request_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `record_id` INT NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `test_name` VARCHAR(255) DEFAULT NULL,
            `original_value` TEXT,
            `proposed_value` TEXT,
            `edit_reason` TEXT NOT NULL,
            `clinical_justification` TEXT,
            `is_critical_result` TINYINT(1) NOT NULL DEFAULT 0,
            `requested_by` VARCHAR(25) NOT NULL,
            `requested_at` DATETIME NOT NULL,
            `status` ENUM('PENDING','APPROVED','REJECTED','CANCELLED','EXPIRED') NOT NULL DEFAULT 'PENDING',
            `reviewed_by` VARCHAR(25) DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `review_notes` TEXT,
            `auto_expired` TINYINT(1) NOT NULL DEFAULT 0,
            `expires_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            INDEX `idx_status` (`status`),
            INDEX `idx_record` (`diagnostic_type`, `record_id`),
            INDEX `idx_patient` (`patient_no`),
            INDEX `idx_requested` (`requested_by`, `requested_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Result edit audit log
        $this->db->query("CREATE TABLE IF NOT EXISTS `result_edit_audit` (
            `audit_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `record_id` INT NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `test_name` VARCHAR(255) DEFAULT NULL,
            `field_name` VARCHAR(100) NOT NULL,
            `old_value` TEXT,
            `new_value` TEXT,
            `edit_type` ENUM('DIRECT','APPROVED','AMENDMENT','CORRECTION') NOT NULL DEFAULT 'DIRECT',
            `edit_reason` TEXT,
            `request_id` BIGINT DEFAULT NULL,
            `edited_by` VARCHAR(25) NOT NULL,
            `edited_at` DATETIME NOT NULL,
            `supervisor_approved` TINYINT(1) NOT NULL DEFAULT 0,
            `approved_by` VARCHAR(25) DEFAULT NULL,
            `approved_at` DATETIME DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            INDEX `idx_record` (`diagnostic_type`, `record_id`),
            INDEX `idx_patient` (`patient_no`),
            INDEX `idx_edited` (`edited_by`, `edited_at`),
            INDEX `idx_type` (`edit_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensure_verification_roles_schema()
    {
        // Verification role requirements
        $this->db->query("CREATE TABLE IF NOT EXISTS `verification_role_config` (
            `config_id` INT AUTO_INCREMENT PRIMARY KEY,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `test_category` VARCHAR(100) DEFAULT 'GENERAL',
            `verification_level` INT NOT NULL DEFAULT 1,
            `required_roles` TEXT NOT NULL,
            `min_experience_months` INT DEFAULT 0,
            `requires_certification` TINYINT(1) NOT NULL DEFAULT 0,
            `certification_types` TEXT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `uk_type_cat_level` (`diagnostic_type`, `test_category`, `verification_level`),
            INDEX `idx_active` (`is_active`, `InActive`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // User verification credentials
        $this->db->query("CREATE TABLE IF NOT EXISTS `user_verification_credentials` (
            `credential_id` INT AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT NOT NULL,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY','ALL') NOT NULL DEFAULT 'ALL',
            `can_verify_level_1` TINYINT(1) NOT NULL DEFAULT 0,
            `can_verify_level_2` TINYINT(1) NOT NULL DEFAULT 0,
            `can_verify_critical` TINYINT(1) NOT NULL DEFAULT 0,
            `certification_number` VARCHAR(100) DEFAULT NULL,
            `certification_expiry` DATE DEFAULT NULL,
            `experience_start_date` DATE DEFAULT NULL,
            `granted_by` INT DEFAULT NULL,
            `granted_at` DATETIME DEFAULT NULL,
            `revoked_by` INT DEFAULT NULL,
            `revoked_at` DATETIME DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            UNIQUE KEY `uk_user_type` (`user_id`, `diagnostic_type`),
            INDEX `idx_active` (`is_active`, `InActive`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Verification attempt log
        $this->db->query("CREATE TABLE IF NOT EXISTS `verification_attempt_log` (
            `attempt_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `record_id` INT NOT NULL,
            `patient_no` VARCHAR(25) DEFAULT NULL,
            `verification_level` INT NOT NULL,
            `attempted_by` VARCHAR(25) NOT NULL,
            `attempt_result` ENUM('SUCCESS','DENIED_ROLE','DENIED_CREDENTIAL','DENIED_SAME_USER','DENIED_SEQUENCE','ERROR') NOT NULL,
            `denial_reason` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `attempted_at` DATETIME NOT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_record` (`diagnostic_type`, `record_id`),
            INDEX `idx_user` (`attempted_by`),
            INDEX `idx_result` (`attempt_result`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensure_supervisor_approval_schema()
    {
        // Supervisor approval queue
        $this->db->query("CREATE TABLE IF NOT EXISTS `supervisor_approval_queue` (
            `approval_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `approval_type` ENUM('RESULT_EDIT','RESULT_AMENDMENT','CRITICAL_OVERRIDE','VERIFICATION_BYPASS','RESULT_DELETE') NOT NULL,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `record_id` INT NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `test_name` VARCHAR(255) DEFAULT NULL,
            `action_description` TEXT NOT NULL,
            `original_data` JSON DEFAULT NULL,
            `proposed_data` JSON DEFAULT NULL,
            `urgency` ENUM('ROUTINE','URGENT','STAT') NOT NULL DEFAULT 'ROUTINE',
            `clinical_justification` TEXT,
            `requested_by` VARCHAR(25) NOT NULL,
            `requested_at` DATETIME NOT NULL,
            `status` ENUM('PENDING','APPROVED','REJECTED','ESCALATED','EXPIRED') NOT NULL DEFAULT 'PENDING',
            `assigned_supervisor` VARCHAR(25) DEFAULT NULL,
            `reviewed_by` VARCHAR(25) DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `review_notes` TEXT,
            `escalation_level` INT NOT NULL DEFAULT 0,
            `escalated_at` DATETIME DEFAULT NULL,
            `escalated_to` VARCHAR(25) DEFAULT NULL,
            `expires_at` DATETIME DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `InActive` INT(1) NOT NULL DEFAULT 0,
            INDEX `idx_status` (`status`),
            INDEX `idx_type` (`approval_type`),
            INDEX `idx_supervisor` (`assigned_supervisor`, `status`),
            INDEX `idx_requested` (`requested_by`),
            INDEX `idx_urgency` (`urgency`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Supervisor approval audit
        $this->db->query("CREATE TABLE IF NOT EXISTS `supervisor_approval_audit` (
            `audit_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `approval_id` BIGINT NOT NULL,
            `action` ENUM('CREATED','ASSIGNED','APPROVED','REJECTED','ESCALATED','EXPIRED','CANCELLED') NOT NULL,
            `performed_by` VARCHAR(25) NOT NULL,
            `performed_at` DATETIME NOT NULL,
            `notes` TEXT,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_approval` (`approval_id`),
            INDEX `idx_action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function ensure_result_lock_schema()
    {
        // Result locks - prevents concurrent edits
        $this->db->query("CREATE TABLE IF NOT EXISTS `result_locks` (
            `lock_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `diagnostic_type` ENUM('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `record_id` INT NOT NULL,
            `lock_type` ENUM('EDIT','VERIFICATION','APPROVAL') NOT NULL DEFAULT 'EDIT',
            `locked_by` VARCHAR(25) NOT NULL,
            `locked_at` DATETIME NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `released_at` DATETIME DEFAULT NULL,
            `released_by` VARCHAR(25) DEFAULT NULL,
            UNIQUE KEY `uk_record_lock` (`diagnostic_type`, `record_id`, `lock_type`, `is_active`),
            INDEX `idx_expires` (`expires_at`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Add lock columns to workflow tables if needed
        if ($this->table_exists('iop_laboratory_workflow')) {
            if (!$this->column_exists('iop_laboratory_workflow', 'is_locked')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `is_locked` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!$this->column_exists('iop_laboratory_workflow', 'locked_by')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `locked_by` VARCHAR(25) DEFAULT NULL");
            }
            if (!$this->column_exists('iop_laboratory_workflow', 'locked_at')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `locked_at` DATETIME DEFAULT NULL");
            }
            if (!$this->column_exists('iop_laboratory_workflow', 'is_finalized')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `is_finalized` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!$this->column_exists('iop_laboratory_workflow', 'finalized_at')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `finalized_at` DATETIME DEFAULT NULL");
            }
        }
    }

    private function seed_default_config()
    {
        // Seed edit permissions if empty
        if ($this->db->where('InActive', 0)->count_all_results('result_edit_permissions') == 0) {
            $now = date('Y-m-d H:i:s');
            $permissions = [
                ['LAB', 'GENERAL', 0, json_encode(['admin', 'lab_tech', 'senior_lab_tech', 'pathologist']), 0, 24],
                ['LAB', 'CRITICAL', 1, json_encode(['admin', 'lab_supervisor', 'pathologist']), 1, 4],
                ['LAB', 'PANIC', 1, json_encode(['admin', 'pathologist', 'chief_pathologist']), 1, 2],
                ['RADIOLOGY', 'GENERAL', 0, json_encode(['admin', 'radiologist', 'senior_radiologist']), 0, 24],
                ['RADIOLOGY', 'CRITICAL', 1, json_encode(['admin', 'senior_radiologist']), 1, 4],
                ['SONOGRAPHY', 'GENERAL', 0, json_encode(['admin', 'sonographer', 'radiologist']), 0, 24],
                ['SONOGRAPHY', 'CRITICAL', 1, json_encode(['admin', 'senior_sonographer', 'radiologist']), 1, 4],
            ];
            foreach ($permissions as $p) {
                $this->db->insert('result_edit_permissions', [
                    'diagnostic_type' => $p[0], 'result_category' => $p[1], 'is_critical' => $p[2],
                    'allowed_roles' => $p[3], 'requires_supervisor_approval' => $p[4],
                    'edit_window_hours' => $p[5], 'is_active' => 1, 'created_at' => $now
                ]);
            }
        }

        // Seed verification role config if empty
        if ($this->db->where('InActive', 0)->count_all_results('verification_role_config') == 0) {
            $now = date('Y-m-d H:i:s');
            $configs = [
                ['LAB', 'GENERAL', 1, json_encode(['lab_tech', 'senior_lab_tech', 'pathologist', 'admin'])],
                ['LAB', 'GENERAL', 2, json_encode(['senior_lab_tech', 'lab_supervisor', 'pathologist', 'admin'])],
                ['LAB', 'CRITICAL', 1, json_encode(['senior_lab_tech', 'pathologist', 'admin'])],
                ['LAB', 'CRITICAL', 2, json_encode(['lab_supervisor', 'pathologist', 'chief_pathologist', 'admin'])],
                ['RADIOLOGY', 'GENERAL', 1, json_encode(['radiologist', 'senior_radiologist', 'admin'])],
                ['RADIOLOGY', 'GENERAL', 2, json_encode(['senior_radiologist', 'admin'])],
                ['SONOGRAPHY', 'GENERAL', 1, json_encode(['sonographer', 'senior_sonographer', 'radiologist', 'admin'])],
                ['SONOGRAPHY', 'GENERAL', 2, json_encode(['senior_sonographer', 'radiologist', 'admin'])],
            ];
            foreach ($configs as $c) {
                $this->db->insert('verification_role_config', [
                    'diagnostic_type' => $c[0], 'test_category' => $c[1], 'verification_level' => $c[2],
                    'required_roles' => $c[3], 'is_active' => 1, 'created_at' => $now
                ]);
            }
        }
    }

    /* ================================================================== */
    /*  CRITICAL RESULT EDIT RESTRICTIONS                                 */
    /* ================================================================== */

    /**
     * Check if user can edit a result
     */
    public function can_edit_result($diagnostic_type, $record_id, $is_critical = false)
    {
        $this->ensure_approval_schema();
        
        $user_id = $this->session->userdata('user_id');
        $user_role = $this->get_user_role();

        // Admin always has access
        if ($user_role === 'admin' || $this->is_admin()) {
            return ['allowed' => true, 'requires_approval' => false];
        }

        // Check if result is finalized/locked
        $lock_check = $this->check_result_lock($diagnostic_type, $record_id);
        if (!$lock_check['can_edit']) {
            return ['allowed' => false, 'reason' => $lock_check['reason']];
        }

        // Get edit permissions
        $category = $is_critical ? 'CRITICAL' : 'GENERAL';
        $permission = $this->db->get_where('result_edit_permissions', [
            'diagnostic_type' => $diagnostic_type,
            'result_category' => $category,
            'is_active' => 1,
            'InActive' => 0
        ])->row();

        if (!$permission) {
            // Fall back to GENERAL
            $permission = $this->db->get_where('result_edit_permissions', [
                'diagnostic_type' => $diagnostic_type,
                'result_category' => 'GENERAL',
                'is_active' => 1,
                'InActive' => 0
            ])->row();
        }

        if (!$permission) {
            return ['allowed' => false, 'reason' => 'No edit permissions configured'];
        }

        // Check allowed roles
        $allowed_roles = json_decode($permission->allowed_roles, true) ?: [];
        if (!in_array($user_role, $allowed_roles)) {
            $this->log_edit_denial($diagnostic_type, $record_id, 'ROLE_NOT_ALLOWED', $user_role);
            return ['allowed' => false, 'reason' => 'Your role is not authorized to edit this result'];
        }

        // Check edit window
        $result_time = $this->get_result_timestamp($diagnostic_type, $record_id);
        if ($result_time && $permission->edit_window_hours > 0) {
            $window_end = strtotime($result_time) + ($permission->edit_window_hours * 3600);
            if (time() > $window_end) {
                return [
                    'allowed' => false,
                    'reason' => "Edit window expired ({$permission->edit_window_hours} hours)",
                    'requires_approval' => true
                ];
            }
        }

        return [
            'allowed' => true,
            'requires_approval' => (bool)$permission->requires_supervisor_approval,
            'edit_window_hours' => $permission->edit_window_hours
        ];
    }

    /**
     * Request approval to edit a critical result
     */
    public function request_edit_approval($diagnostic_type, $record_id, $patient_no, $test_name, $original_value, $proposed_value, $reason, $justification = null)
    {
        $this->ensure_approval_schema();

        $bind = $this->validate_record_patient_binding($diagnostic_type, $record_id, $patient_no);
        if (!$bind['ok']) {
            return ['ok' => false, 'error' => $bind['error']];
        }
        
        $user_id = $this->session->userdata('user_id');
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));

        // Check for existing pending request
        $existing = $this->db->get_where('result_edit_requests', [
            'diagnostic_type' => $diagnostic_type,
            'record_id' => $record_id,
            'status' => 'PENDING',
            'InActive' => 0
        ])->row();

        if ($existing) {
            return ['ok' => false, 'error' => 'A pending edit request already exists for this result'];
        }

        // Determine if critical
        $is_critical = $this->is_critical_result($diagnostic_type, $record_id);

        $this->db->insert('result_edit_requests', [
            'diagnostic_type' => $diagnostic_type,
            'record_id' => $record_id,
            'patient_no' => $patient_no,
            'test_name' => $test_name,
            'original_value' => $original_value,
            'proposed_value' => $proposed_value,
            'edit_reason' => $reason,
            'clinical_justification' => $justification,
            'is_critical_result' => $is_critical ? 1 : 0,
            'requested_by' => $user_id,
            'requested_at' => $now,
            'status' => 'PENDING',
            'expires_at' => $expires,
            'created_at' => $now
        ]);

        $request_id = $this->db->insert_id();

        // Create supervisor approval entry
        $this->create_supervisor_approval(
            'RESULT_EDIT',
            $diagnostic_type,
            $record_id,
            $patient_no,
            $test_name,
            "Edit request: {$reason}",
            ['original' => $original_value],
            ['proposed' => $proposed_value],
            $is_critical ? 'URGENT' : 'ROUTINE',
            $justification
        );

        return ['ok' => true, 'request_id' => $request_id];
    }

    /**
     * Apply an approved edit
     */
    public function apply_approved_edit($request_id)
    {
        $request = $this->db->get_where('result_edit_requests', [
            'request_id' => $request_id,
            'status' => 'APPROVED',
            'InActive' => 0
        ])->row();

        if (!$request) {
            return ['ok' => false, 'error' => 'Approved request not found'];
        }

        // Log the edit
        $this->log_result_edit(
            $request->diagnostic_type,
            $request->record_id,
            $request->patient_no,
            $request->test_name,
            'result',
            $request->original_value,
            $request->proposed_value,
            'APPROVED',
            $request->edit_reason,
            $request_id,
            true,
            $request->reviewed_by,
            $request->reviewed_at
        );

        return ['ok' => true];
    }

    /* ================================================================== */
    /*  VERIFICATION ROLE ENFORCEMENT                                     */
    /* ================================================================== */

    /**
     * Check if user can verify a result at specified level
     */
    public function can_verify_result($diagnostic_type, $record_id, $verification_level = 1, $test_category = 'GENERAL')
    {
        $this->ensure_approval_schema();
        
        $user_id = $this->session->userdata('user_id');
        $user_role = $this->get_user_role();

        // Admin always has access
        if ($user_role === 'admin' || $this->is_admin()) {
            return ['allowed' => true, 'reason' => 'Admin access'];
        }

        // Get verification config
        $config = $this->db->get_where('verification_role_config', [
            'diagnostic_type' => $diagnostic_type,
            'test_category' => $test_category,
            'verification_level' => $verification_level,
            'is_active' => 1,
            'InActive' => 0
        ])->row();

        if (!$config) {
            // Fall back to GENERAL category
            $config = $this->db->get_where('verification_role_config', [
                'diagnostic_type' => $diagnostic_type,
                'test_category' => 'GENERAL',
                'verification_level' => $verification_level,
                'is_active' => 1,
                'InActive' => 0
            ])->row();
        }

        if (!$config) {
            $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_ROLE', 'No verification config found');
            return ['allowed' => false, 'reason' => 'No verification configuration found'];
        }

        // Check required roles
        $required_roles = json_decode($config->required_roles, true) ?: [];
        if (!in_array($user_role, $required_roles)) {
            $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_ROLE', "Role '{$user_role}' not in allowed list");
            return ['allowed' => false, 'reason' => 'Your role is not authorized to verify at this level'];
        }

        // Check user credentials
        $credential = $this->get_user_verification_credential($user_id, $diagnostic_type);
        if ($credential) {
            // Check level permissions
            if ($verification_level == 1 && !$credential->can_verify_level_1) {
                $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_CREDENTIAL', 'Level 1 verification not granted');
                return ['allowed' => false, 'reason' => 'You do not have Level 1 verification credentials'];
            }
            if ($verification_level == 2 && !$credential->can_verify_level_2) {
                $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_CREDENTIAL', 'Level 2 verification not granted');
                return ['allowed' => false, 'reason' => 'You do not have Level 2 verification credentials'];
            }

            // Check certification expiry
            if ($config->requires_certification && $credential->certification_expiry) {
                if (strtotime($credential->certification_expiry) < time()) {
                    $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_CREDENTIAL', 'Certification expired');
                    return ['allowed' => false, 'reason' => 'Your certification has expired'];
                }
            }

            // Check experience
            if ($config->min_experience_months > 0 && $credential->experience_start_date) {
                $months = (time() - strtotime($credential->experience_start_date)) / (30 * 24 * 3600);
                if ($months < $config->min_experience_months) {
                    $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_CREDENTIAL', "Insufficient experience: {$months} months");
                    return ['allowed' => false, 'reason' => "Minimum {$config->min_experience_months} months experience required"];
                }
            }
        }

        // For level 2, check that same user didn't do level 1
        if ($verification_level == 2) {
            $level1_verifier = $this->get_level1_verifier($diagnostic_type, $record_id);
            if ($level1_verifier && $level1_verifier == $user_id) {
                $this->log_verification_attempt($diagnostic_type, $record_id, null, $verification_level, 'DENIED_SAME_USER', 'Same user cannot verify both levels');
                return ['allowed' => false, 'reason' => 'You cannot verify both levels of the same result'];
            }
        }

        return ['allowed' => true, 'reason' => 'Verification permitted'];
    }

    /**
     * Perform verification with role enforcement
     */
    public function verify_with_role_check($diagnostic_type, $record_id, $patient_no, $verification_level, $notes = '', $test_category = 'GENERAL')
    {
        $bind = $this->validate_record_patient_binding($diagnostic_type, $record_id, $patient_no);
        if (!$bind['ok']) {
            return ['ok' => false, 'error' => $bind['error']];
        }

        // Check permission first
        $can_verify = $this->can_verify_result($diagnostic_type, $record_id, $verification_level, $test_category);
        
        if (!$can_verify['allowed']) {
            return ['ok' => false, 'error' => $can_verify['reason']];
        }

        $user_id = $this->session->userdata('user_id');
        $now = date('Y-m-d H:i:s');

        // Log successful attempt
        $this->log_verification_attempt($diagnostic_type, $record_id, $patient_no, $verification_level, 'SUCCESS', $notes);

        // Update workflow based on diagnostic type
        if ($diagnostic_type === 'LAB') {
            return $this->update_lab_verification($record_id, $verification_level, $user_id, $now, $notes);
        } elseif ($diagnostic_type === 'RADIOLOGY' || $diagnostic_type === 'SONOGRAPHY') {
            return $this->update_imaging_verification($record_id, $verification_level, $user_id, $now, $notes);
        }

        return ['ok' => false, 'error' => 'Unknown diagnostic type'];
    }

    private function update_lab_verification($record_id, $level, $user_id, $now, $notes)
    {
        if (!$this->table_exists('iop_laboratory_workflow')) {
            return ['ok' => false, 'error' => 'Workflow table not found'];
        }

        $wf = $this->db->get_where('iop_laboratory_workflow', ['io_lab_id' => $record_id, 'InActive' => 0])->row();
        if (!$wf) {
            return ['ok' => false, 'error' => 'Workflow record not found'];
        }

        $data = ['updated_at' => $now, 'updated_by' => $user_id];

        if ($level == 1) {
            if ($this->column_exists('iop_laboratory_workflow', 'verified_level_1_by')) {
                $data['verified_level_1_by'] = $user_id;
                $data['verified_level_1_at'] = $now;
            }
            $data['status'] = 'VERIFIED_LEVEL_1';
        } else {
            if ($this->column_exists('iop_laboratory_workflow', 'verified_level_2_by')) {
                $data['verified_level_2_by'] = $user_id;
                $data['verified_level_2_at'] = $now;
            }
            $data['verified_at'] = $now;
            $data['status'] = 'VERIFIED';
        }

        $this->db->where('io_lab_id', $record_id)->update('iop_laboratory_workflow', $data);

        return ['ok' => true, 'status' => $data['status']];
    }

    private function update_imaging_verification($record_id, $level, $user_id, $now, $notes)
    {
        // Similar logic for sonography/radiology workflow
        if (!$this->table_exists('iop_laboratory_workflow')) {
            return ['ok' => false, 'error' => 'Workflow table not found'];
        }

        $wf = $this->db->get_where('iop_laboratory_workflow', ['io_lab_id' => $record_id, 'InActive' => 0])->row();
        if (!$wf) {
            return ['ok' => false, 'error' => 'Workflow record not found'];
        }

        $data = ['updated_at' => $now, 'updated_by' => $user_id, 'verified_at' => $now, 'status' => 'VERIFIED'];

        $this->db->where('io_lab_id', $record_id)->update('iop_laboratory_workflow', $data);

        return ['ok' => true, 'status' => 'VERIFIED'];
    }

    /* ================================================================== */
    /*  SUPERVISOR APPROVAL WORKFLOW                                      */
    /* ================================================================== */

    /**
     * Create a supervisor approval request
     */
    public function create_supervisor_approval($approval_type, $diagnostic_type, $record_id, $patient_no, $test_name, $description, $original_data, $proposed_data, $urgency = 'ROUTINE', $justification = null)
    {
        $this->ensure_approval_schema();
        
        $user_id = $this->session->userdata('user_id');
        $now = date('Y-m-d H:i:s');
        
        // Set expiry based on urgency
        $expiry_hours = $urgency === 'STAT' ? 2 : ($urgency === 'URGENT' ? 8 : 24);
        $expires = date('Y-m-d H:i:s', strtotime("+{$expiry_hours} hours"));

        // Find available supervisor
        $supervisor = $this->find_available_supervisor($diagnostic_type);

        $this->db->insert('supervisor_approval_queue', [
            'approval_type' => $approval_type,
            'diagnostic_type' => $diagnostic_type,
            'record_id' => $record_id,
            'patient_no' => $patient_no,
            'test_name' => $test_name,
            'action_description' => $description,
            'original_data' => json_encode($original_data),
            'proposed_data' => json_encode($proposed_data),
            'urgency' => $urgency,
            'clinical_justification' => $justification,
            'requested_by' => $user_id,
            'requested_at' => $now,
            'status' => 'PENDING',
            'assigned_supervisor' => $supervisor,
            'expires_at' => $expires,
            'created_at' => $now
        ]);

        $approval_id = $this->db->insert_id();

        // Log creation
        $this->log_approval_action($approval_id, 'CREATED', $user_id, "Approval requested: {$description}");

        if ($supervisor) {
            $this->log_approval_action($approval_id, 'ASSIGNED', $user_id, "Assigned to supervisor: {$supervisor}");
        }

        return ['ok' => true, 'approval_id' => $approval_id, 'assigned_to' => $supervisor];
    }

    /**
     * Approve a pending request
     */
    public function approve_request($approval_id, $notes = '')
    {
        $this->ensure_approval_schema();
        
        $approval = $this->db->get_where('supervisor_approval_queue', [
            'approval_id' => $approval_id,
            'status' => 'PENDING',
            'InActive' => 0
        ])->row();

        if (!$approval) {
            return ['ok' => false, 'error' => 'Pending approval not found'];
        }

        // Check if user is authorized supervisor
        $user_id = $this->session->userdata('user_id');
        if (!$this->is_authorized_supervisor($approval->diagnostic_type)) {
            return ['ok' => false, 'error' => 'You are not authorized to approve this request'];
        }

        $now = date('Y-m-d H:i:s');

        // Update approval
        $this->db->where('approval_id', $approval_id)->update('supervisor_approval_queue', [
            'status' => 'APPROVED',
            'reviewed_by' => $user_id,
            'reviewed_at' => $now,
            'review_notes' => $notes
        ]);

        // Update related edit request if exists
        if ($approval->approval_type === 'RESULT_EDIT') {
            $this->db->where([
                'diagnostic_type' => $approval->diagnostic_type,
                'record_id' => $approval->record_id,
                'status' => 'PENDING'
            ])->update('result_edit_requests', [
                'status' => 'APPROVED',
                'reviewed_by' => $user_id,
                'reviewed_at' => $now,
                'review_notes' => $notes
            ]);
        }

        // Log approval
        $this->log_approval_action($approval_id, 'APPROVED', $user_id, $notes);

        return ['ok' => true, 'message' => 'Request approved'];
    }

    /**
     * Reject a pending request
     */
    public function reject_request($approval_id, $reason)
    {
        $this->ensure_approval_schema();
        
        if (empty($reason)) {
            return ['ok' => false, 'error' => 'Rejection reason is required'];
        }

        $approval = $this->db->get_where('supervisor_approval_queue', [
            'approval_id' => $approval_id,
            'status' => 'PENDING',
            'InActive' => 0
        ])->row();

        if (!$approval) {
            return ['ok' => false, 'error' => 'Pending approval not found'];
        }

        $user_id = $this->session->userdata('user_id');
        if (!$this->is_authorized_supervisor($approval->diagnostic_type)) {
            return ['ok' => false, 'error' => 'You are not authorized to reject this request'];
        }

        $now = date('Y-m-d H:i:s');

        // Update approval
        $this->db->where('approval_id', $approval_id)->update('supervisor_approval_queue', [
            'status' => 'REJECTED',
            'reviewed_by' => $user_id,
            'reviewed_at' => $now,
            'review_notes' => $reason
        ]);

        // Update related edit request
        if ($approval->approval_type === 'RESULT_EDIT') {
            $this->db->where([
                'diagnostic_type' => $approval->diagnostic_type,
                'record_id' => $approval->record_id,
                'status' => 'PENDING'
            ])->update('result_edit_requests', [
                'status' => 'REJECTED',
                'reviewed_by' => $user_id,
                'reviewed_at' => $now,
                'review_notes' => $reason
            ]);
        }

        // Log rejection
        $this->log_approval_action($approval_id, 'REJECTED', $user_id, $reason);

        return ['ok' => true, 'message' => 'Request rejected'];
    }

    /**
     * Escalate a pending request
     */
    public function escalate_request($approval_id, $escalate_to = null, $reason = '')
    {
        $approval = $this->db->get_where('supervisor_approval_queue', [
            'approval_id' => $approval_id,
            'status' => 'PENDING',
            'InActive' => 0
        ])->row();

        if (!$approval) {
            return ['ok' => false, 'error' => 'Pending approval not found'];
        }

        $user_id = $this->session->userdata('user_id');
        $now = date('Y-m-d H:i:s');
        $new_level = $approval->escalation_level + 1;

        // Find higher-level supervisor if not specified
        if (!$escalate_to) {
            $escalate_to = $this->find_escalation_supervisor($approval->diagnostic_type, $new_level);
        }

        $this->db->where('approval_id', $approval_id)->update('supervisor_approval_queue', [
            'status' => 'ESCALATED',
            'escalation_level' => $new_level,
            'escalated_at' => $now,
            'escalated_to' => $escalate_to,
            'assigned_supervisor' => $escalate_to
        ]);

        // Create new pending entry for escalated supervisor
        $this->db->where('approval_id', $approval_id)->update('supervisor_approval_queue', [
            'status' => 'PENDING'
        ]);

        $this->log_approval_action($approval_id, 'ESCALATED', $user_id, "Escalated to level {$new_level}: {$reason}");

        return ['ok' => true, 'escalated_to' => $escalate_to, 'level' => $new_level];
    }

    /**
     * Get pending approvals for current supervisor
     */
    public function get_pending_approvals($diagnostic_type = null, $limit = 50)
    {
        $user_id = $this->session->userdata('user_id');

        $this->db->select('a.*, p.firstname, p.lastname, u.username as requested_by_name')
            ->from('supervisor_approval_queue a')
            ->join('patient_personal_info p', 'p.patient_no = a.patient_no', 'left')
            ->join('user u', 'u.user_id = a.requested_by', 'left')
            ->where('a.status', 'PENDING')
            ->where('a.InActive', 0);

        // If not admin, filter by assigned supervisor or diagnostic type access
        if (!$this->is_admin()) {
            $this->db->group_start()
                ->where('a.assigned_supervisor', $user_id)
                ->or_where('a.assigned_supervisor IS NULL')
            ->group_end();
        }

        if ($diagnostic_type) {
            $this->db->where('a.diagnostic_type', $diagnostic_type);
        }

        return $this->db->order_by('a.urgency', 'DESC')
            ->order_by('a.requested_at', 'ASC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Count pending approvals
     */
    public function count_pending_approvals($diagnostic_type = null)
    {
        $user_id = $this->session->userdata('user_id');

        $this->db->from('supervisor_approval_queue')
            ->where('status', 'PENDING')
            ->where('InActive', 0);

        if (!$this->is_admin()) {
            $this->db->group_start()
                ->where('assigned_supervisor', $user_id)
                ->or_where('assigned_supervisor IS NULL')
            ->group_end();
        }

        if ($diagnostic_type) {
            $this->db->where('diagnostic_type', $diagnostic_type);
        }

        return $this->db->count_all_results();
    }

    /* ================================================================== */
    /*  HELPER METHODS                                                    */
    /* ================================================================== */

    private function get_user_role()
    {
        $role = $this->session->userdata('role');
        if (!$role) {
            $role = $this->session->userdata('user_role');
        }
        if (!$role) {
            $user_id = $this->session->userdata('user_id');
            if ($user_id) {
                $user = $this->db->select('ur.role_name')
                    ->from('user u')
                    ->join('user_roles ur', 'ur.role_id = u.user_role', 'left')
                    ->where('u.user_id', $user_id)
                    ->get()->row();
                $role = $user ? strtolower($user->role_name) : 'unknown';
            }
        }
        return strtolower(trim($role ?: 'unknown'));
    }

    private function is_admin()
    {
        $role = $this->get_user_role();
        return $role === 'admin' || $role === 'administrator' || $this->session->userdata('is_admin');
    }

    private function is_authorized_supervisor($diagnostic_type)
    {
        if ($this->is_admin()) return true;

        $user_role = $this->get_user_role();
        return in_array($user_role, $this->supervisor_roles);
    }

    private function find_available_supervisor($diagnostic_type)
    {
        // Find online supervisor for the diagnostic type
        $roles = $diagnostic_type === 'LAB' ? $this->lab_verification_roles :
                ($diagnostic_type === 'RADIOLOGY' ? $this->radiology_verification_roles : $this->sonography_verification_roles);

        // For now, return null (will be assigned manually or by escalation)
        return null;
    }

    private function find_escalation_supervisor($diagnostic_type, $level)
    {
        // Higher level supervisors
        if ($level >= 2) {
            // Return chief/senior supervisor
            return null; // Would query for available senior supervisor
        }
        return null;
    }

    private function get_user_verification_credential($user_id, $diagnostic_type)
    {
        return $this->db->get_where('user_verification_credentials', [
            'user_id' => $user_id,
            'is_active' => 1,
            'InActive' => 0
        ])->row();
    }

    private function get_level1_verifier($diagnostic_type, $record_id)
    {
        if (!$this->table_exists('iop_laboratory_workflow')) return null;
        if (!$this->column_exists('iop_laboratory_workflow', 'verified_level_1_by')) return null;

        $wf = $this->db->select('verified_level_1_by')
            ->get_where('iop_laboratory_workflow', ['io_lab_id' => $record_id, 'InActive' => 0])
            ->row();

        return $wf ? $wf->verified_level_1_by : null;
    }

    private function check_result_lock($diagnostic_type, $record_id)
    {
        // Check if result is locked
        $lock = $this->db->get_where('result_locks', [
            'diagnostic_type' => $diagnostic_type,
            'record_id' => $record_id,
            'is_active' => 1
        ])->row();

        if ($lock && strtotime($lock->expires_at) > time()) {
            $user_id = $this->session->userdata('user_id');
            if ($lock->locked_by != $user_id) {
                return ['can_edit' => false, 'reason' => 'Result is locked by another user'];
            }
        }

        // Check if finalized
        if ($this->table_exists('iop_laboratory_workflow') && $this->column_exists('iop_laboratory_workflow', 'is_finalized')) {
            $wf = $this->db->select('is_finalized')
                ->get_where('iop_laboratory_workflow', ['io_lab_id' => $record_id, 'InActive' => 0])
                ->row();
            if ($wf && $wf->is_finalized) {
                return ['can_edit' => false, 'reason' => 'Result has been finalized and cannot be edited'];
            }
        }

        return ['can_edit' => true];
    }

    private function get_result_timestamp($diagnostic_type, $record_id)
    {
        if ($this->table_exists('iop_laboratory_workflow')) {
            $wf = $this->db->select('reported_at, verified_at, created_at')
                ->get_where('iop_laboratory_workflow', ['io_lab_id' => $record_id, 'InActive' => 0])
                ->row();
            if ($wf) {
                return $wf->verified_at ?: $wf->reported_at ?: $wf->created_at;
            }
        }
        return null;
    }

    private function is_critical_result($diagnostic_type, $record_id)
    {
        // Check if there's a critical alert for this result
        if ($this->table_exists('lab_critical_alerts')) {
            $alert = $this->db->get_where('lab_critical_alerts', [
                'io_lab_id' => $record_id,
                'InActive' => 0
            ])->row();
            return $alert ? true : false;
        }
        return false;
    }

    /* ================================================================== */
    /*  LOGGING METHODS                                                   */
    /* ================================================================== */

    private function log_result_edit($diagnostic_type, $record_id, $patient_no, $test_name, $field_name, $old_value, $new_value, $edit_type, $reason, $request_id = null, $supervisor_approved = false, $approved_by = null, $approved_at = null)
    {
        $this->db->insert('result_edit_audit', [
            'diagnostic_type' => $diagnostic_type,
            'record_id' => $record_id,
            'patient_no' => $patient_no,
            'test_name' => $test_name,
            'field_name' => $field_name,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'edit_type' => $edit_type,
            'edit_reason' => $reason,
            'request_id' => $request_id,
            'edited_by' => $this->session->userdata('user_id'),
            'edited_at' => date('Y-m-d H:i:s'),
            'supervisor_approved' => $supervisor_approved ? 1 : 0,
            'approved_by' => $approved_by,
            'approved_at' => $approved_at,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr($this->input->user_agent(), 0, 500),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function log_edit_denial($diagnostic_type, $record_id, $reason, $user_role)
    {
        $this->log_verification_attempt($diagnostic_type, $record_id, null, 0, 'DENIED_ROLE', "Edit denied: {$reason} (role: {$user_role})");
    }

    private function log_verification_attempt($diagnostic_type, $record_id, $patient_no, $level, $result, $reason = null)
    {
        if (!$this->table_exists('verification_attempt_log')) return;

        $this->db->insert('verification_attempt_log', [
            'diagnostic_type' => $diagnostic_type,
            'record_id' => $record_id,
            'patient_no' => $patient_no,
            'verification_level' => $level,
            'attempted_by' => $this->session->userdata('user_id'),
            'attempt_result' => $result,
            'denial_reason' => $reason,
            'ip_address' => $this->input->ip_address(),
            'attempted_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    private function log_approval_action($approval_id, $action, $performed_by, $notes = null)
    {
        if (!$this->table_exists('supervisor_approval_audit')) return;

        $this->db->insert('supervisor_approval_audit', [
            'approval_id' => $approval_id,
            'action' => $action,
            'performed_by' => $performed_by,
            'performed_at' => date('Y-m-d H:i:s'),
            'notes' => $notes,
            'ip_address' => $this->input->ip_address(),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /* ================================================================== */
    /*  REPORTING                                                         */
    /* ================================================================== */

    /**
     * Get approval statistics
     */
    public function get_approval_stats($date_from = null, $date_to = null)
    {
        $date_from = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_to ?: date('Y-m-d');

        return $this->db->query("
            SELECT 
                COUNT(*) as total_requests,
                SUM(status = 'PENDING') as pending,
                SUM(status = 'APPROVED') as approved,
                SUM(status = 'REJECTED') as rejected,
                SUM(status = 'ESCALATED') as escalated,
                SUM(status = 'EXPIRED') as expired,
                SUM(urgency = 'STAT') as stat_requests,
                SUM(urgency = 'URGENT') as urgent_requests,
                AVG(TIMESTAMPDIFF(MINUTE, requested_at, COALESCE(reviewed_at, NOW()))) as avg_response_minutes
            FROM supervisor_approval_queue
            WHERE DATE(created_at) BETWEEN ? AND ?
            AND InActive = 0
        ", [$date_from, $date_to])->row();
    }

    /**
     * Get verification statistics
     */
    public function get_verification_stats($date_from = null, $date_to = null)
    {
        $date_from = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_to ?: date('Y-m-d');

        return $this->db->query("
            SELECT 
                COUNT(*) as total_attempts,
                SUM(attempt_result = 'SUCCESS') as successful,
                SUM(attempt_result LIKE 'DENIED%') as denied,
                SUM(attempt_result = 'DENIED_ROLE') as denied_role,
                SUM(attempt_result = 'DENIED_CREDENTIAL') as denied_credential,
                SUM(attempt_result = 'DENIED_SAME_USER') as denied_same_user
            FROM verification_attempt_log
            WHERE DATE(created_at) BETWEEN ? AND ?
        ", [$date_from, $date_to])->row();
    }

    /**
     * Get edit audit trail for a record
     */
    public function get_edit_audit_trail($diagnostic_type, $record_id)
    {
        return $this->db->select('a.*, u.username as edited_by_name, u2.username as approved_by_name')
            ->from('result_edit_audit a')
            ->join('user u', 'u.user_id = a.edited_by', 'left')
            ->join('user u2', 'u2.user_id = a.approved_by', 'left')
            ->where(['a.diagnostic_type' => $diagnostic_type, 'a.record_id' => $record_id, 'a.InActive' => 0])
            ->order_by('a.edited_at', 'DESC')
            ->get()->result();
    }
}
