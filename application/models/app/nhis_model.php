<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Integration Model
 * 
 * Handles NHIS Claim-IT API integration with dual-mode support (Mock/Live).
 * Provides coverage engine, claim generation, billing split, and reconciliation.
 * 
 * @package     HMS
 * @subpackage  Models
 * @category    NHIS Integration
 */
class Nhis_model extends CI_Model {

    private $mode;
    private $base_url;
    private $config;
    private $nhis_audit_pk_column = null;

    /**
     * H-NHIS-1: Status mapping between nhis_model (lowercase) and Nhis_claimit_model (uppercase)
     * This ensures compatibility when both models interact with the same database
     */
    private static $status_map = array(
        // nhis_model (lowercase) -> Nhis_claimit_model (uppercase)
        'draft'      => 'DRAFT',
        'pending'    => 'READY',
        'submitted'  => 'SUBMITTED',
        'processing' => 'SUBMITTED',
        'approved'   => 'APPROVED',
        'partial'    => 'APPROVED',
        'rejected'   => 'REJECTED',
        'paid'       => 'PAID',
        'cancelled'  => 'REJECTED'
    );

    /**
     * Normalize status to lowercase (nhis_model convention)
     * @param string $status
     * @return string
     */
    public function normalize_status($status) {
        return strtolower(trim($status));
    }

    /**
     * Convert nhis_model status to Nhis_claimit_model status (uppercase)
     * @param string $status
     * @return string
     */
    public function to_claimit_status($status) {
        $normalized = $this->normalize_status($status);
        return isset(self::$status_map[$normalized]) ? self::$status_map[$normalized] : strtoupper($status);
    }

    /**
     * Convert Nhis_claimit_model status (uppercase) to nhis_model status (lowercase)
     * @param string $status
     * @return string
     */
    public function from_claimit_status($status) {
        $reversed = array_flip(self::$status_map);
        $upper = strtoupper(trim($status));
        return isset($reversed[$upper]) ? $reversed[$upper] : strtolower($status);
    }

    public function __construct() {
        parent::__construct();
        
        // Load NHIS config if it exists, otherwise use defaults
        if (file_exists(APPPATH . 'config/nhis.php')) {
            $this->load->config('nhis');
            $CI =& get_instance();
            $this->mode = $CI->config->item('nhis_mode') ?: 'mock';
            $this->base_url = ($this->mode === 'live') 
                ? $CI->config->item('nhis_live_base_url')
                : $CI->config->item('nhis_mock_base_url');
        } else {
            // Default to mock mode if no config exists
            $this->mode = 'mock';
            $this->base_url = '';
        }
        
        $this->ensure_nhis_schema();
    }

    /**
     * Ensure all NHIS tables exist (idempotent)
     */
    public function ensure_nhis_schema() {
        $this->_create_nhis_coverage_table();
        $this->_create_nhis_claims_table();
        $this->_create_nhis_claim_items_table();
        $this->_create_nhis_audit_log_table();
        $this->_create_nhis_eligibility_cache_table();
        $this->_create_nhis_reconciliation_table();
        $this->_ensure_patient_nhis_columns();
    }

    private function _create_nhis_coverage_table() {
        if ($this->db->table_exists('nhis_coverage')) return;
        
        $sql = "CREATE TABLE nhis_coverage (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            item_type VARCHAR(30) NOT NULL COMMENT 'drug, lab, radiology, procedure, service, consumable, consultation',
            item_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to item table based on type',
            item_name VARCHAR(255) DEFAULT NULL,
            nhis_code VARCHAR(50) DEFAULT NULL COMMENT 'Official NHIS code',
            coverage_percentage DECIMAL(5,2) DEFAULT 100.00,
            max_limit DECIMAL(18,2) DEFAULT NULL COMMENT 'Max amount NHIS will cover',
            requires_preauth TINYINT(1) DEFAULT 0,
            formulary_status VARCHAR(20) DEFAULT 'approved' COMMENT 'approved, restricted, not_listed',
            effective_date DATE DEFAULT NULL,
            expiry_date DATE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_item (item_type, item_id),
            INDEX idx_nhis_code (nhis_code),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }

    private function _create_nhis_claims_table() {
        if ($this->db->table_exists('nhis_claims')) return;
        
        $sql = "CREATE TABLE nhis_claims (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            claim_number VARCHAR(50) NOT NULL UNIQUE,
            patient_no VARCHAR(25) NOT NULL,
            encounter_id INT(11) UNSIGNED NOT NULL COMMENT 'FK to patient_details_iop.IO_ID',
            encounter_type VARCHAR(10) DEFAULT 'OPD' COMMENT 'OPD, IPD',
            nhis_member_id VARCHAR(50) DEFAULT NULL,
            facility_code VARCHAR(20) DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'draft' COMMENT 'draft, pending, submitted, processing, approved, partial, rejected, paid, cancelled',
            total_amount DECIMAL(18,2) DEFAULT 0.00,
            approved_amount DECIMAL(18,2) DEFAULT NULL,
            patient_amount DECIMAL(18,2) DEFAULT 0.00,
            diagnosis_codes TEXT DEFAULT NULL COMMENT 'JSON array of ICD codes',
            attending_doctor VARCHAR(100) DEFAULT NULL,
            created_at DATE NOT NULL,
            discharge_date DATE DEFAULT NULL,
            submitted_at DATETIME DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            paid_at DATETIME DEFAULT NULL,
            rejection_reason TEXT DEFAULT NULL,
            nhis_response_code VARCHAR(20) DEFAULT NULL,
            nhis_reference_id VARCHAR(100) DEFAULT NULL,
            submission_attempts INT(3) DEFAULT 0,
            last_submission_error TEXT DEFAULT NULL,
            created_by VARCHAR(25) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_patient (patient_no),
            INDEX idx_encounter (encounter_id),
            INDEX idx_status (status),
            INDEX idx_created_at (created_at),
            INDEX idx_claim_number (claim_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }

    private function _create_nhis_claim_items_table() {
        if ($this->db->table_exists('nhis_claim_items')) return;
        
        $sql = "CREATE TABLE nhis_claim_items (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            claim_id BIGINT(20) NOT NULL,
            item_type VARCHAR(30) NOT NULL,
            item_id INT(11) UNSIGNED NOT NULL,
            item_name VARCHAR(255) NOT NULL,
            nhis_code VARCHAR(50) DEFAULT NULL,
            quantity DECIMAL(10,2) DEFAULT 1.00,
            unit_price DECIMAL(18,2) NOT NULL,
            total_amount DECIMAL(18,2) NOT NULL,
            coverage_percentage DECIMAL(5,2) DEFAULT 100.00,
            nhis_amount DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Amount covered by NHIS',
            patient_amount DECIMAL(18,2) DEFAULT 0.00 COMMENT 'Amount patient pays',
            approved_amount DECIMAL(18,2) DEFAULT NULL,
            item_status VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, approved, rejected, partial',
            rejection_reason VARCHAR(255) DEFAULT NULL,
            billing_reference VARCHAR(50) DEFAULT NULL COMMENT 'FK to billing detail',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_claim (claim_id),
            INDEX idx_item (item_type, item_id),
            FOREIGN KEY (claim_id) REFERENCES nhis_claims(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }

    private function _create_nhis_audit_log_table() {
        if ($this->db->table_exists('nhis_audit_log')) return;
        
        $sql = "CREATE TABLE nhis_audit_log (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            action_type VARCHAR(50) NOT NULL COMMENT 'eligibility_check, coverage_applied, claim_created, claim_submitted, claim_approved, claim_rejected, reconciliation',
            reference_type VARCHAR(30) DEFAULT NULL COMMENT 'claim, patient, encounter, billing',
            reference_id VARCHAR(50) DEFAULT NULL,
            patient_no VARCHAR(25) DEFAULT NULL,
            old_value TEXT DEFAULT NULL,
            new_value TEXT DEFAULT NULL,
            api_request TEXT DEFAULT NULL,
            api_response TEXT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'success' COMMENT 'success, failed, error',
            error_message TEXT DEFAULT NULL,
            performed_by VARCHAR(25) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_action (action_type),
            INDEX idx_reference (reference_type, reference_id),
            INDEX idx_patient (patient_no),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }

    private function _create_nhis_eligibility_cache_table() {
        if ($this->db->table_exists('nhis_eligibility_cache')) return;
        
        $sql = "CREATE TABLE nhis_eligibility_cache (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            nhis_member_id VARCHAR(50) NOT NULL,
            patient_no VARCHAR(25) DEFAULT NULL,
            is_eligible TINYINT(1) DEFAULT 0,
            member_name VARCHAR(255) DEFAULT NULL,
            gender VARCHAR(10) DEFAULT NULL,
            date_of_birth DATE DEFAULT NULL,
            card_expiry_date DATE DEFAULT NULL,
            scheme_name VARCHAR(100) DEFAULT NULL,
            scheme_type VARCHAR(50) DEFAULT NULL,
            eligibility_message VARCHAR(255) DEFAULT NULL,
            raw_response TEXT DEFAULT NULL,
            checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            INDEX idx_member (nhis_member_id),
            INDEX idx_patient (patient_no),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }

    private function _create_nhis_reconciliation_table() {
        if ($this->db->table_exists('nhis_reconciliation')) return;
        
        $sql = "CREATE TABLE nhis_reconciliation (
            id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reconciliation_date DATE NOT NULL,
            issue_type VARCHAR(50) NOT NULL COMMENT 'covered_billed_patient, not_covered_billed_nhis, duplicate_claim, missing_claim, amount_mismatch',
            reference_type VARCHAR(30) NOT NULL,
            reference_id VARCHAR(50) NOT NULL,
            patient_no VARCHAR(25) DEFAULT NULL,
            claim_id INT(11) UNSIGNED DEFAULT NULL,
            expected_amount DECIMAL(18,2) DEFAULT NULL,
            actual_amount DECIMAL(18,2) DEFAULT NULL,
            difference_amount DECIMAL(18,2) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            status VARCHAR(20) DEFAULT 'open' COMMENT 'open, investigating, resolved, ignored',
            resolution_notes TEXT DEFAULT NULL,
            resolved_by VARCHAR(25) DEFAULT NULL,
            resolved_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_date (reconciliation_date),
            INDEX idx_issue (issue_type),
            INDEX idx_status (status),
            INDEX idx_patient (patient_no)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->query($sql);
    }

    private function _ensure_patient_nhis_columns() {
        // Add NHIS columns to patient_personal_info if not exist
        $columns = array(
            'nhis_member_id' => "VARCHAR(50) DEFAULT NULL COMMENT 'NHIS Member ID'",
            'nhis_card_expiry' => "DATE DEFAULT NULL",
            'nhis_verified' => "TINYINT(1) DEFAULT 0",
            'nhis_verified_at' => "DATETIME DEFAULT NULL",
            'nhis_scheme_name' => "VARCHAR(100) DEFAULT NULL",
            'nhis_scheme_type' => "VARCHAR(50) DEFAULT NULL"
        );
        
        foreach ($columns as $col => $def) {
            if (!$this->_column_exists('patient_personal_info', $col)) {
                $this->db->query("ALTER TABLE patient_personal_info ADD COLUMN {$col} {$def}");
            }
        }
    }

    private function _column_exists($table, $column) {
        $result = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $result->num_rows() > 0;
    }

    // =========================================================================
    // ELIGIBILITY & CARD VALIDATION
    // =========================================================================

    /**
     * Validate NHIS card and check eligibility
     * M-NHIS-2: Added input format validation
     */
    public function validate_nhis_card($nhis_member_id, $patient_no = null) {
        $nhis_member_id = trim($nhis_member_id);
        if (empty($nhis_member_id)) {
            return array('success' => false, 'message' => 'NHIS Member ID is required');
        }
        
        // M-NHIS-2: Validate NHIS ID format (Ghana format: typically alphanumeric, 10-15 chars)
        if (!preg_match('/^[A-Z0-9\-]{5,20}$/i', $nhis_member_id)) {
            return array('success' => false, 'message' => 'Invalid NHIS Member ID format');
        }

        // Check cache first
        $cached = $this->_get_cached_eligibility($nhis_member_id);
        if ($cached && strtotime($cached->expires_at) > time()) {
            return array(
                'success' => true,
                'cached' => true,
                'eligible' => (bool)$cached->is_eligible,
                'data' => $cached
            );
        }

        // Call API (mock or live)
        $response = $this->_call_api('validate_card', array(
            'member_id' => $nhis_member_id
        ));

        if (!$response['success']) {
            $this->_log_audit('eligibility_check', 'patient', $patient_no, null, null, 
                json_encode(array('member_id' => $nhis_member_id)), 
                json_encode($response), 'failed', $response['message']);
            return $response;
        }

        // Cache the result
        $cache_hours = $this->config->item('nhis_cache_eligibility_hours') ?: 24;
        $this->_cache_eligibility($nhis_member_id, $patient_no, $response['data'], $cache_hours);

        // Update patient record if patient_no provided
        if ($patient_no && $response['data']['eligible']) {
            $this->_update_patient_nhis_info($patient_no, $response['data']);
        }

        $this->_log_audit('eligibility_check', 'patient', $patient_no, null, 
            json_encode($response['data']), 
            json_encode(array('member_id' => $nhis_member_id)), 
            json_encode($response), 'success');

        return $response;
    }

    private function _get_cached_eligibility($nhis_member_id) {
        return $this->db->where('nhis_member_id', $nhis_member_id)
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->order_by('checked_at', 'DESC')
            ->get('nhis_eligibility_cache')
            ->row();
    }

    private function _cache_eligibility($nhis_member_id, $patient_no, $data, $cache_hours) {
        $this->db->insert('nhis_eligibility_cache', array(
            'nhis_member_id' => $nhis_member_id,
            'patient_no' => $patient_no,
            'is_eligible' => isset($data['eligible']) ? (int)$data['eligible'] : 0,
            'member_name' => isset($data['name']) ? $data['name'] : null,
            'gender' => isset($data['gender']) ? $data['gender'] : null,
            'date_of_birth' => isset($data['dob']) ? $data['dob'] : null,
            'card_expiry_date' => isset($data['expiry_date']) ? $data['expiry_date'] : null,
            'scheme_name' => isset($data['scheme_name']) ? $data['scheme_name'] : null,
            'scheme_type' => isset($data['scheme_type']) ? $data['scheme_type'] : null,
            'eligibility_message' => isset($data['message']) ? $data['message'] : null,
            'raw_response' => json_encode($data),
            'checked_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$cache_hours} hours"))
        ));
    }

    private function _update_patient_nhis_info($patient_no, $data) {
        $update = array(
            'nhis_verified' => 1,
            'nhis_verified_at' => date('Y-m-d H:i:s')
        );
        if (isset($data['expiry_date'])) $update['nhis_card_expiry'] = $data['expiry_date'];
        if (isset($data['scheme_name'])) $update['nhis_scheme_name'] = $data['scheme_name'];
        if (isset($data['scheme_type'])) $update['nhis_scheme_type'] = $data['scheme_type'];
        if (isset($data['member_id'])) $update['nhis_member_id'] = $data['member_id'];

        $this->db->where('patient_no', $patient_no)->update('patient_personal_info', $update);
    }

    /**
     * Check if patient is NHIS eligible
     */
    public function is_patient_nhis_eligible($patient_no) {
        $patient = $this->db->select('nhis_member_id, nhis_verified, nhis_card_expiry, Insurance_comp')
            ->where('patient_no', $patient_no)
            ->get('patient_personal_info')
            ->row();

        if (!$patient) return false;

        // Check if insurance is NHIS
        $ins = strtoupper(trim($patient->Insurance_comp ?: ''));
        if (strpos($ins, 'NHIS') === false) return false;

        // Check if verified and not expired
        if (!$patient->nhis_verified) return false;
        if ($patient->nhis_card_expiry && strtotime($patient->nhis_card_expiry) < strtotime('today')) {
            $grace = $this->config->item('nhis_card_expiry_grace_days') ?: 0;
            if (strtotime($patient->nhis_card_expiry . " +{$grace} days") < strtotime('today')) {
                return false;
            }
        }

        return true;
    }

    // =========================================================================
    // COVERAGE ENGINE
    // =========================================================================

    /**
     * Get coverage for an item
     */
    public function get_item_coverage($item_type, $item_id) {
        $coverage = $this->db->where('item_type', $item_type)
            ->where('item_id', $item_id)
            ->where('is_active', 1)
            ->where('(effective_date IS NULL OR effective_date <= CURDATE())')
            ->where('(expiry_date IS NULL OR expiry_date >= CURDATE())')
            ->get('nhis_coverage')
            ->row();

        if ($coverage) {
            return array(
                'covered' => true,
                'coverage_percentage' => (float)$coverage->coverage_percentage,
                'max_limit' => $coverage->max_limit ? (float)$coverage->max_limit : null,
                'nhis_code' => $coverage->nhis_code,
                'requires_preauth' => (bool)$coverage->requires_preauth,
                'formulary_status' => $coverage->formulary_status
            );
        }

        // Check default coverage from config
        $default_coverage = $this->_get_default_coverage($item_type);
        if ($default_coverage > 0) {
            return array(
                'covered' => true,
                'coverage_percentage' => $default_coverage,
                'max_limit' => null,
                'nhis_code' => null,
                'requires_preauth' => false,
                'formulary_status' => 'default'
            );
        }

        return array(
            'covered' => false,
            'coverage_percentage' => 0,
            'max_limit' => null,
            'nhis_code' => null,
            'requires_preauth' => false,
            'formulary_status' => 'not_listed'
        );
    }

    private function _get_default_coverage($item_type) {
        $config_map = array(
            'drug' => 'nhis_drug_coverage',
            'lab' => 'nhis_lab_coverage',
            'radiology' => 'nhis_radiology_coverage',
            'procedure' => 'nhis_procedure_coverage',
            'consultation' => 'nhis_consultation_coverage',
            'service' => 'nhis_default_coverage_percent'
        );

        $key = isset($config_map[$item_type]) ? $config_map[$item_type] : 'nhis_default_coverage_percent';
        return (float)($this->config->item($key) ?: 0);
    }

    /**
     * Calculate billing split for an item
     */
    public function calculate_billing_split($item_type, $item_id, $amount, $patient_no) {
        if (!$this->is_patient_nhis_eligible($patient_no)) {
            return array(
                'nhis_eligible' => false,
                'total_amount' => $amount,
                'nhis_amount' => 0,
                'patient_amount' => $amount,
                'coverage_percentage' => 0,
                'coverage_status' => 'not_covered'
            );
        }

        $coverage = $this->get_item_coverage($item_type, $item_id);
        
        if (!$coverage['covered']) {
            return array(
                'nhis_eligible' => true,
                'total_amount' => $amount,
                'nhis_amount' => 0,
                'patient_amount' => $amount,
                'coverage_percentage' => 0,
                'coverage_status' => 'not_covered'
            );
        }

        $nhis_amount = $amount * ($coverage['coverage_percentage'] / 100);
        
        // Apply max limit if set
        if ($coverage['max_limit'] && $nhis_amount > $coverage['max_limit']) {
            $nhis_amount = $coverage['max_limit'];
        }

        $patient_amount = $amount - $nhis_amount;

        $status = 'covered';
        if ($coverage['coverage_percentage'] < 100 || $patient_amount > 0) {
            $status = 'partial';
        }

        return array(
            'nhis_eligible' => true,
            'total_amount' => $amount,
            'nhis_amount' => round($nhis_amount, 2),
            'patient_amount' => round($patient_amount, 2),
            'coverage_percentage' => $coverage['coverage_percentage'],
            'coverage_status' => $status,
            'nhis_code' => $coverage['nhis_code'],
            'requires_preauth' => $coverage['requires_preauth']
        );
    }

    /**
     * Add or update coverage for an item
     */
    public function set_item_coverage($item_type, $item_id, $data) {
        $existing = $this->db->where('item_type', $item_type)
            ->where('item_id', $item_id)
            ->get('nhis_coverage')
            ->row();

        $record = array(
            'item_type' => $item_type,
            'item_id' => $item_id,
            'item_name' => isset($data['item_name']) ? $data['item_name'] : null,
            'nhis_code' => isset($data['nhis_code']) ? $data['nhis_code'] : null,
            'coverage_percentage' => isset($data['coverage_percentage']) ? $data['coverage_percentage'] : 100,
            'max_limit' => isset($data['max_limit']) ? $data['max_limit'] : null,
            'requires_preauth' => isset($data['requires_preauth']) ? (int)$data['requires_preauth'] : 0,
            'formulary_status' => isset($data['formulary_status']) ? $data['formulary_status'] : 'approved',
            'effective_date' => isset($data['effective_date']) ? $data['effective_date'] : null,
            'expiry_date' => isset($data['expiry_date']) ? $data['expiry_date'] : null,
            'notes' => isset($data['notes']) ? $data['notes'] : null,
            'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1
        );

        if ($existing) {
            $this->db->where('id', $existing->id)->update('nhis_coverage', $record);
            return $existing->id;
        } else {
            $this->db->insert('nhis_coverage', $record);
            return $this->db->insert_id();
        }
    }

    // =========================================================================
    // CLAIM GENERATION
    // =========================================================================

    /**
     * Generate claim for an encounter
     */
    public function generate_claim($encounter_id, $user_id = null) {
        $CI = get_instance();
        $router_class = isset($CI->router->class) ? $CI->router->class : null;
        $router_method = isset($CI->router->method) ? $CI->router->method : null;
        $uri = function_exists('uri_string') ? uri_string() : null;
        log_message('error', 'NHIS_CLAIM_CALL_TRACE: ' . json_encode(array(
            'method' => __METHOD__,
            'file' => __FILE__,
            'uri' => $uri,
            'router_class' => $router_class,
            'router_method' => $router_method,
            'patient_no' => isset($encounter_id) ? $encounter_id : null
        )));

        // Get encounter info
        $encounter = $this->db->select('I.IO_ID, I.patient_no, I.date_visit, I.patient_type, 
            P.nhis_member_id, P.firstname, P.lastname')
            ->from('patient_details_iop I')
            ->join('patient_personal_info P', 'P.patient_no = I.patient_no', 'left')
            ->where('I.IO_ID', $encounter_id)
            ->get()->row();

        if (!$encounter) {
            return array('success' => false, 'message' => 'Encounter not found');
        }

        if (!$this->is_patient_nhis_eligible($encounter->patient_no)) {
            return array('success' => false, 'message' => 'Patient is not NHIS eligible');
        }

        // Check if claim already exists
        $existing = $this->db->where('encounter_id', $encounter_id)
            ->where('status !=', 'cancelled')
            ->get('nhis_claims')->row();

        if ($existing) {
            return array('success' => false, 'message' => 'Claim already exists for this encounter', 'claim_id' => $existing->id);
        }

        $this->db->trans_start();

        // Generate claim number
        $claim_number = $this->_generate_claim_number();

        // Get diagnosis codes
        $diagnoses = $this->_get_encounter_diagnoses($encounter_id);

        // Get attending doctor
        $doctor = $this->_get_encounter_doctor($encounter_id);

        // Create claim header
        $claim_data = array(
            'claim_number' => $claim_number,
            'patient_no' => $encounter->patient_no,
            'encounter_id' => $encounter_id,
            'encounter_type' => $encounter->patient_type ?: 'OPD',
            'nhis_member_id' => $encounter->nhis_member_id,
            'facility_code' => $this->config->item('nhis_facility_code'),
            'status' => 'draft',
            'diagnosis_codes' => json_encode($diagnoses),
            'attending_doctor' => $doctor,
            // H-NHIS-2: Convert DATE to DATETIME format for strict mode compatibility
            'created_at' => date('Y-m-d H:i:s', strtotime($encounter->date_visit)),
            'created_by' => $user_id
        );

        $trace = array(
            'method' => __METHOD__,
            'file' => __FILE__,
            'uri' => function_exists('uri_string') ? uri_string() : null,
            'patient_no' => isset($claim_data['patient_no']) ? $claim_data['patient_no'] : null,
            'iop_id' => isset($claim_data['encounter_id']) ? $claim_data['encounter_id'] : null,
            'claim_number' => isset($claim_data['claim_number']) ? $claim_data['claim_number'] : null
        );
        log_message('error', 'NHIS_CLAIM_INSERT_TRACE: ' . json_encode($trace));
        $this->db->insert('nhis_claims', $claim_data);
        $claim_id = $this->db->insert_id();

        // Add claim items
        $total_amount = $this->_add_claim_items($claim_id, $encounter_id, $encounter->patient_no);

        $soft_block = false;

        try {
            if (!isset($this->nhis_validation)) {
                $this->load->model('app/Nhis_validation_model', 'nhis_validation');
            }
            $diag_codes = array();
            if (is_array($diagnoses)) {
                foreach ($diagnoses as $d) {
                    if (is_array($d) && isset($d['code']) && trim((string)$d['code']) !== '') {
                        $diag_codes[] = (string)$d['code'];
                    }
                }
            }
            $payload = array(
                'services' => (isset($total_amount['validation_services']) && is_array($total_amount['validation_services']))
                    ? $total_amount['validation_services']
                    : array(),
                'diagnoses' => $diag_codes,
                'procedures' => array(),
            );
            $payloadResult = $this->nhis_validation->validate_claim_payload($payload);

            $payloadErrors = array();
            if (is_array($payloadResult) && isset($payloadResult['errors']) && is_array($payloadResult['errors'])) {
                $payloadErrors = $payloadResult['errors'];
            }
            if (is_array($payloadResult) && empty($payloadResult['valid']) && !empty($payloadErrors)) {
                $this->db->where('id', $claim_id)->update('nhis_claims', array(
                    'validation_errors' => json_encode($payloadErrors)
                ));
            }
            log_message(
                'debug',
                '[NHIS_VALIDATION] context=CLAIM_PAYLOAD encounter_id=' . (int)$encounter_id
                . ' claim_id=' . (int)$claim_id
                . ' valid=' . (!empty($payloadResult['valid']) ? '1' : '0')
                . ' errors=' . (isset($payloadResult['errors']) ? json_encode($payloadResult['errors']) : '[]')
            );

            $mode = $this->nhis_validation->_get_enforcement_mode();

            if ($mode === 1) {
                if (is_array($payloadResult) && empty($payloadResult['valid'])) {
                    log_message(
                        'debug',
                        '[NHIS_ENFORCEMENT_WARNING] claim_id=' . (int)$claim_id
                        . ' errors=' . (isset($payloadResult['errors']) ? json_encode($payloadResult['errors']) : '[]')
                    );
                }
            } elseif ($mode === 2) {
                if (is_array($payloadResult) && empty($payloadResult['valid'])) {
                    $this->db->where('id', $claim_id)
                        ->update('nhis_claims', array('status' => 'draft'));
                    log_message(
                        'debug',
                        '[NHIS_ENFORCEMENT_SOFT_BLOCK] claim_id=' . (int)$claim_id
                        . ' reason=' . (isset($payloadResult['errors']) ? json_encode($payloadResult['errors']) : '[]')
                    );
                    try {
                        $this->_upsert_enforcement_state(
                            'claim',
                            (int)$claim_id,
                            $encounter->patient_no,
                            'SOFT_BLOCKED',
                            (int)$mode,
                            (isset($payloadResult['errors']) ? $payloadResult['errors'] : array())
                        );
                    } catch (Exception $e) {
                        log_message('error', '[NHIS_ENFORCEMENT_AUDIT_FAIL] claim_id=' . (int)$claim_id . ' err=' . $e->getMessage());
                    }
                    $soft_block = true;
                }
            } elseif ($mode === 3) {
                log_message('debug', '[NHIS_ENFORCEMENT_STRICT_MODE_DISABLED] claim_id=' . (int)$claim_id);
            }
        } catch (Exception $e) {
            log_message(
                'error',
                '[NHIS_VALIDATION_FAIL] context=CLAIM_PAYLOAD encounter_id=' . (int)$encounter_id
                . ' claim_id=' . (int)$claim_id
                . ' err=' . $e->getMessage()
            );
        }

        if ($soft_block) {
            $this->db->trans_complete();

            if ($this->db->trans_status() === FALSE) {
                log_message('error', 'NHIS claim generation failed for encounter ' . $encounter_id . ' - transaction rolled back');
                return array('success' => false, 'message' => 'Failed to generate claim - database transaction failed');
            }

            $this->_log_audit('claim_created', 'claim', $claim_id, $encounter->patient_no,
                null, json_encode($claim_data), null, 'success');

            return array(
                'success' => true,
                'claim_id' => $claim_id,
                'claim_number' => $claim_number,
                'total_amount' => $total_amount['nhis_total'],
                'patient_amount' => $total_amount['patient_total']
            );
        }

        // Phase 4 / Step 7 — verify computed claim totals against billing_transactions SSOT.
        // In strict mode (BILLING_FACADE_NHIS_CLAIM_SSOT = TRUE), a mismatch rolls back the
        // entire claim. In permissive mode (default), the mismatch is audited but the
        // claim proceeds — same behavior as before this hook existed.
        $this->load->model('app/Billing_facade_model', 'billing_facade_model');
        $ssot_check = $this->billing_facade_model->verify_nhis_claim_against_ssot(
            $encounter_id,
            $total_amount['nhis_total'],
            $total_amount['patient_total'],
            $user_id,
            $encounter->patient_no
        );
        if (!$ssot_check['ok']) {
            $this->db->trans_complete();
            log_message('error', 'NHIS claim total/SSOT mismatch (strict): ' . $ssot_check['error']);
            return array('success' => false, 'message' => $ssot_check['error']);
        }

        // Update total
        $this->db->where('id', $claim_id)->update('nhis_claims', array(
            'total_amount' => $total_amount['nhis_total'],
            'patient_amount' => $total_amount['patient_total']
        ));

        $this->db->trans_complete();

        // M-NHIS-6: Explicit transaction status check with proper rollback handling
        // trans_complete() auto-commits on success or rolls back on failure
        if ($this->db->trans_status() === FALSE) {
            log_message('error', 'NHIS claim generation failed for encounter ' . $encounter_id . ' - transaction rolled back');
            return array('success' => false, 'message' => 'Failed to generate claim - database transaction failed');
        }

        $this->_log_audit('claim_created', 'claim', $claim_id, $encounter->patient_no, 
            null, json_encode($claim_data), null, 'success');

        return array(
            'success' => true,
            'claim_id' => $claim_id,
            'claim_number' => $claim_number,
            'total_amount' => $total_amount['nhis_total'],
            'patient_amount' => $total_amount['patient_total']
        );
    }

    /**
     * Generate unique claim number
     * M-NHIS-4: Added uniqueness validation with retry loop
     */
    private function _generate_claim_number() {
        $prefix = 'CLM';
        $date = date('Ymd');
        $max_attempts = 10;
        
        for ($i = 0; $i < $max_attempts; $i++) {
            $random = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
            $claim_number = "{$prefix}-{$date}-{$random}";
            
            // Check if claim number already exists
            $exists = $this->db->where('claim_number', $claim_number)
                ->count_all_results('nhis_claims');
            
            if ($exists == 0) {
                return $claim_number;
            }
        }
        
        // Fallback: use timestamp for guaranteed uniqueness
        $timestamp = time();
        return "{$prefix}-{$date}-{$timestamp}";
    }

    private function _get_encounter_diagnoses($encounter_id) {
        $diagnoses = $this->db->select('D.icd_code, D.diagnosis_name')
            ->from('iop_diagnosis ID')
            ->join('diagnosis D', 'D.diagnosis_id = ID.diagnosis_id', 'left')
            ->where('ID.iop_id', $encounter_id)
            ->where('ID.InActive', 0)
            ->get()->result();

        $codes = array();
        foreach ($diagnoses as $d) {
            if (!empty($d->icd_code)) {
                $codes[] = array('code' => $d->icd_code, 'name' => $d->diagnosis_name);
            }
        }
        return $codes;
    }

    private function _get_encounter_doctor($encounter_id) {
        $doctor = $this->db->select("CONCAT(U.firstname, ' ', U.lastname) as name")
            ->from('patient_details_iop I')
            ->join('user U', 'U.user_id = I.doctor_id', 'left')
            ->where('I.IO_ID', $encounter_id)
            ->get()->row();

        return $doctor ? $doctor->name : null;
    }

    private function _add_claim_items($claim_id, $encounter_id, $patient_no) {
        $nhis_total = 0;
        $patient_total = 0;
        $validation_services = array();
        $nhis_validation_ok = false;
        try {
            if (!isset($this->nhis_validation)) {
                $this->load->model('app/Nhis_validation_model', 'nhis_validation');
            }
            $nhis_validation_ok = isset($this->nhis_validation) && is_object($this->nhis_validation);
        } catch (Exception $e) {
            log_message(
                'error',
                '[NHIS_VALIDATION_FAIL] context=CLAIM_ITEM_INIT encounter_id=' . (int)$encounter_id
                . ' claim_id=' . (int)$claim_id
                . ' err=' . $e->getMessage()
            );
        }

        // Add medications
        $meds = $this->db->select('M.iop_med_id, M.medicine_id, M.total_qty, D.drug_name, D.nPrice')
            ->from('iop_medication M')
            ->join('medicine_drug_name D', 'D.drug_id = M.medicine_id', 'left')
            ->where('M.iop_id', $encounter_id)
            ->where('M.InActive', 0)
            ->get()->result();

        foreach ($meds as $med) {
            $amount = (float)$med->total_qty * (float)$med->nPrice;
            $split = $this->calculate_billing_split('drug', $med->medicine_id, $amount, $patient_no);
            
            $this->db->insert('nhis_claim_items', array(
                'claim_id' => $claim_id,
                'item_type' => 'drug',
                'item_id' => $med->medicine_id,
                'item_name' => $med->drug_name,
                'nhis_code' => $split['nhis_code'],
                'quantity' => $med->total_qty,
                'unit_price' => $med->nPrice,
                'total_amount' => $amount,
                'coverage_percentage' => $split['coverage_percentage'],
                'nhis_amount' => $split['nhis_amount'],
                'patient_amount' => $split['patient_amount']
            ));

            if ((int)$med->iop_med_id > 0) {
                $validation_services[] = array('module' => 'PHARMACY', 'id' => (int)$med->iop_med_id);
            }
            if ($nhis_validation_ok && (int)$med->iop_med_id > 0) {
                try {
                    $validation = $this->nhis_validation->validate_service('PHARMACY', (int)$med->iop_med_id);
                    log_message(
                        'debug',
                        '[NHIS_VALIDATION] context=CLAIM_ITEM module=PHARMACY id=' . (int)$med->iop_med_id
                        . ' encounter_id=' . (int)$encounter_id
                        . ' claim_id=' . (int)$claim_id
                        . ' valid=' . (!empty($validation['valid']) ? '1' : '0')
                        . ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
                    );
                } catch (Exception $e) {
                    log_message(
                        'error',
                        '[NHIS_VALIDATION_FAIL] context=CLAIM_ITEM module=PHARMACY id=' . (int)$med->iop_med_id
                        . ' encounter_id=' . (int)$encounter_id
                        . ' claim_id=' . (int)$claim_id
                        . ' err=' . $e->getMessage()
                    );
                }
            }

            $nhis_total += $split['nhis_amount'];
            $patient_total += $split['patient_amount'];
        }

        // Add laboratory tests — join iop_lab_billing for rate/nhis data, bill_particular for name/nhis_code
        $labQ = $this->db->query("
            SELECT
                L.io_lab_id,
                L.laboratory_id,
                COALESCE(LB.item_name, BP.particular_name, L.laboratory_text, 'Laboratory Test') AS lab_name,
                COALESCE(LB.rate_amount, BP.charge_amount, 0)                                     AS unit_price,
                COALESCE(LB.nhis_flag, 0)                                                         AS nhis_flag,
                COALESCE(BP.nhis_code, '')                                                         AS nhis_code,
                COALESCE(BP.nhis_price, 0)                                                         AS nhis_price,
                COALESCE(BP.is_nhis_covered, 0)                                                    AS is_nhis_covered
            FROM iop_laboratory L
            LEFT JOIN iop_lab_billing LB ON LB.io_lab_id = L.io_lab_id AND LB.InActive = 0
            LEFT JOIN bill_particular BP ON BP.particular_id = L.laboratory_id
            WHERE L.iop_id = ? AND L.InActive = 0 AND L.category_id != 18
        ", array($encounter_id));
        $labs = $labQ ? $labQ->result() : array();

        foreach ($labs as $lab) {
            $isNhisCovered = (int)$lab->nhis_flag === 1 || (int)$lab->is_nhis_covered === 1;
            $nhisRate = (float)$lab->nhis_price > 0 ? (float)$lab->nhis_price : (float)$lab->unit_price;
            $cashRate  = (float)$lab->unit_price;
            $amount    = $isNhisCovered ? $nhisRate : $cashRate;
            $split = $this->calculate_billing_split('lab', $lab->laboratory_id, $amount, $patient_no);

            $this->db->insert('nhis_claim_items', array(
                'claim_id'            => $claim_id,
                'item_type'           => 'lab',
                'item_id'             => (int)$lab->laboratory_id,
                'item_name'           => $lab->lab_name,
                'nhis_code'           => $lab->nhis_code !== '' ? $lab->nhis_code : $split['nhis_code'],
                'quantity'            => 1,
                'unit_price'          => $cashRate,
                'total_amount'        => $amount,
                'coverage_percentage' => $split['coverage_percentage'],
                'nhis_amount'         => $split['nhis_amount'],
                'patient_amount'      => $split['patient_amount'],
            ));

            if ((int)$lab->io_lab_id > 0) {
                $validation_services[] = array('module' => 'LAB', 'id' => (int)$lab->io_lab_id);
            }
            if ($nhis_validation_ok && (int)$lab->io_lab_id > 0) {
                try {
                    $validation = $this->nhis_validation->validate_service('LAB', (int)$lab->io_lab_id);
                    log_message(
                        'debug',
                        '[NHIS_VALIDATION] context=CLAIM_ITEM module=LAB id=' . (int)$lab->io_lab_id
                        . ' encounter_id=' . (int)$encounter_id
                        . ' claim_id=' . (int)$claim_id
                        . ' valid=' . (!empty($validation['valid']) ? '1' : '0')
                        . ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
                    );
                } catch (Exception $e) {
                    log_message(
                        'error',
                        '[NHIS_VALIDATION_FAIL] context=CLAIM_ITEM module=LAB id=' . (int)$lab->io_lab_id
                        . ' encounter_id=' . (int)$encounter_id
                        . ' claim_id=' . (int)$claim_id
                        . ' err=' . $e->getMessage()
                    );
                }
            }

            $nhis_total   += $split['nhis_amount'];
            $patient_total += $split['patient_amount'];
        }

        // Add sonography/imaging items
        $sonoQ = $this->db->query("
            SELECT
                SC.charge_id,
                SC.scan_item_id,
                COALESCE(SI.item_name, SC.clinical_question, 'Sonography') AS item_name,
                COALESCE(SC.rate_amount, 0)                                AS unit_price,
                COALESCE(SC.nhis_flag, 0)                                  AS nhis_flag,
                COALESCE(SC.nhis_price, 0)                                 AS nhis_price
            FROM iop_sonography_charge SC
            LEFT JOIN sonography_items SI ON SI.item_id = SC.scan_item_id
            WHERE SC.iop_id = ? AND SC.InActive = 0
        ", array($encounter_id));
        $sonos = $sonoQ ? $sonoQ->result() : array();

        foreach ($sonos as $sono) {
            $isNhisCovered = (int)$sono->nhis_flag === 1;
            $nhisRate = (float)$sono->nhis_price > 0 ? (float)$sono->nhis_price : (float)$sono->unit_price;
            $cashRate  = (float)$sono->unit_price;
            $amount    = $isNhisCovered ? $nhisRate : $cashRate;
            $split = $this->calculate_billing_split('imaging', $sono->scan_item_id, $amount, $patient_no);

            $this->db->insert('nhis_claim_items', array(
                'claim_id'            => $claim_id,
                'item_type'           => 'imaging',
                'item_id'             => (int)$sono->scan_item_id,
                'item_name'           => $sono->item_name,
                'nhis_code'           => $split['nhis_code'],
                'quantity'            => 1,
                'unit_price'          => $cashRate,
                'total_amount'        => $amount,
                'coverage_percentage' => $split['coverage_percentage'],
                'nhis_amount'         => $split['nhis_amount'],
                'patient_amount'      => $split['patient_amount'],
            ));

            if ((int)$sono->charge_id > 0) {
                $validation_services[] = array('module' => 'SONOGRAPHY', 'id' => (int)$sono->charge_id);
            }
            if ($nhis_validation_ok && (int)$sono->charge_id > 0) {
                try {
                    $validation = $this->nhis_validation->validate_service('SONOGRAPHY', (int)$sono->charge_id);
                    log_message(
                        'debug',
                        '[NHIS_VALIDATION] context=CLAIM_ITEM module=SONOGRAPHY id=' . (int)$sono->charge_id
                        . ' encounter_id=' . (int)$encounter_id
                        . ' claim_id=' . (int)$claim_id
                        . ' valid=' . (!empty($validation['valid']) ? '1' : '0')
                        . ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
                    );
                } catch (Exception $e) {
                    log_message(
                        'error',
                        '[NHIS_VALIDATION_FAIL] context=CLAIM_ITEM module=SONOGRAPHY id=' . (int)$sono->charge_id
                        . ' encounter_id=' . (int)$encounter_id
                        . ' claim_id=' . (int)$claim_id
                        . ' err=' . $e->getMessage()
                    );
                }
            }

            $nhis_total   += $split['nhis_amount'];
            $patient_total += $split['patient_amount'];
        }

        // Add billing items (services, procedures, etc.)
        // H-NHIS-4: Join bill_particular to get actual particular_id for coverage lookup
        $bills = $this->db->select('B.invoice_no, B.bill_name, B.amount, B.qty, BP.particular_id')
            ->from('iop_billing_t B')
            ->join('iop_billing H', 'H.invoice_no = B.invoice_no', 'inner')
            ->join('bill_particular BP', 'BP.particular_name = B.bill_name', 'left')
            ->where('H.iop_id', $encounter_id)
            ->where('H.InActive', 0)
            ->get()->result();

        foreach ($bills as $bill) {
            $amount = (float)$bill->amount * (float)$bill->qty;
            // H-NHIS-4: Use actual particular_id for coverage lookup, fallback to 0
            $item_id = !empty($bill->particular_id) ? (int)$bill->particular_id : 0;
            $split = $this->calculate_billing_split('service', $item_id, $amount, $patient_no);
            
            $this->db->insert('nhis_claim_items', array(
                'claim_id' => $claim_id,
                'item_type' => 'service',
                'item_id' => $item_id,
                'item_name' => $bill->bill_name,
                'nhis_code' => $split['nhis_code'],
                'quantity' => $bill->qty,
                'unit_price' => $bill->amount,
                'total_amount' => $amount,
                'coverage_percentage' => $split['coverage_percentage'],
                'nhis_amount' => $split['nhis_amount'],
                'patient_amount' => $split['patient_amount'],
                'billing_reference' => $bill->invoice_no
            ));

            $nhis_total += $split['nhis_amount'];
            $patient_total += $split['patient_amount'];
        }

        return array(
            'nhis_total' => $nhis_total,
            'patient_total' => $patient_total,
            'validation_services' => $validation_services
        );
    }

    // =========================================================================
    // CLAIM SUBMISSION
    // =========================================================================

    /**
     * Submit claim to NHIS
     */
    public function submit_claim($claim_id, $user_id = null) {
        if ($this->is_claim_quarantined($claim_id)) {
            log_message('error', '[NHIS_EXPORT_BLOCKED] claim_id=' . (int)$claim_id);
            return array('success' => false, 'message' => 'Claim is quarantined');
        }

        $claim = $this->db->where('id', $claim_id)->get('nhis_claims')->row();
        
        if (!$claim) {
            return array('success' => false, 'message' => 'Claim not found');
        }

        if (!in_array($claim->status, array('draft', 'pending', 'rejected'))) {
            return array('success' => false, 'message' => 'Claim cannot be submitted in current status');
        }

        $max_attempts = $this->config->item('nhis_claim_resubmit_max_attempts') ?: 3;
        if ($claim->submission_attempts >= $max_attempts) {
            return array('success' => false, 'message' => 'Maximum submission attempts reached');
        }

        // Get claim items
        $items = $this->db->where('claim_id', $claim_id)->get('nhis_claim_items')->result();

        // Prepare submission payload
        $payload = array(
            'claim_number' => $claim->claim_number,
            'facility_code' => $claim->facility_code,
            'member_id' => $claim->nhis_member_id,
            'patient_name' => $this->_get_patient_name($claim->patient_no),
            'created_at' => $claim->created_at,
            'encounter_type' => $claim->encounter_type,
            'diagnosis_codes' => json_decode($claim->diagnosis_codes, true),
            'attending_doctor' => $claim->attending_doctor,
            'total_amount' => $claim->total_amount,
            'items' => array()
        );

        foreach ($items as $item) {
            $payload['items'][] = array(
                'type' => $item->item_type,
                'nhis_code' => $item->nhis_code,
                'name' => $item->item_name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'amount' => $item->nhis_amount
            );
        }

        // Call API
        $response = $this->_call_api('submit_claim', $payload);

        // Update claim
        $update = array(
            'submission_attempts' => $claim->submission_attempts + 1,
            'submitted_at' => date('Y-m-d H:i:s')
        );

        if ($response['success']) {
            $update['status'] = 'submitted';
            $update['nhis_response_code'] = isset($response['data']['response_code']) ? $response['data']['response_code'] : null;
            $update['nhis_reference_id'] = isset($response['data']['reference_id']) ? $response['data']['reference_id'] : null;
            $update['last_submission_error'] = null;
        } else {
            $update['status'] = 'pending';
            $update['last_submission_error'] = $response['message'];
        }

        $this->db->where('id', $claim_id)->update('nhis_claims', $update);

        $this->_log_audit('claim_submitted', 'claim', $claim_id, $claim->patient_no,
            json_encode(array('status' => $update['status'])),
            json_encode($payload), json_encode($response),
            $response['success'] ? 'success' : 'failed',
            $response['success'] ? null : $response['message']);

        return $response;
    }

    private function _get_patient_name($patient_no) {
        $p = $this->db->select("CONCAT(firstname, ' ', lastname) as name")
            ->where('patient_no', $patient_no)
            ->get('patient_personal_info')->row();
        return $p ? $p->name : '';
    }

    /**
     * Check claim status from NHIS
     * @param int $claim_id
     * @return array
     */
    public function check_claim_status($claim_id) {
        return $this->check_status($claim_id);
    }

    /**
     * Check claim status from NHIS (internal)
     */
    public function check_status($claim_id) {
        $claim = $this->db->where('id', $claim_id)->get('nhis_claims')->row();
        
        if (!$claim || !$claim->nhis_reference_id) {
            return array('success' => false, 'message' => 'Claim not found or not submitted');
        }

        $response = $this->_call_api('status', array(
            'reference_id' => $claim->nhis_reference_id,
            'claim_number' => $claim->claim_number
        ));

        if ($response['success'] && isset($response['data']['status'])) {
            $status = strtolower($response['data']['status']);
            $update = array('status' => $status);

            if ($status === 'approved') {
                $update['approved_at'] = date('Y-m-d H:i:s');
                $update['approved_amount'] = isset($response['data']['approved_amount']) 
                    ? $response['data']['approved_amount'] : $claim->total_amount;
            } elseif ($status === 'rejected') {
                $update['rejection_reason'] = isset($response['data']['reason']) 
                    ? $response['data']['reason'] : 'Rejected by NHIS';
            } elseif ($status === 'paid') {
                $update['paid_at'] = date('Y-m-d H:i:s');
            }

            $this->db->where('id', $claim_id)->update('nhis_claims', $update);

            $this->_log_audit('status_check', 'claim', $claim_id, $claim->patient_no,
                json_encode($update), null, json_encode($response), 'success');
        }

        return $response;
    }

    // =========================================================================
    // API COMMUNICATION
    // =========================================================================

    private function _call_api($endpoint, $data = array()) {
        $url = $this->base_url . '/' . $endpoint;

        if ($this->mode === 'mock') {
            return $this->_mock_api_call($endpoint, $data);
        }

        // Live API call
        $CI =& get_instance();
        $api_key = $CI->config->item('nhis_api_key');
        $api_secret = $CI->config->item('nhis_api_secret');
        $facility_code = $CI->config->item('nhis_facility_code');
        
        // Build request body
        $body = json_encode($data);
        
        // Generate HMAC-SHA256 signature for NHIS Claim-IT authentication
        $timestamp = gmdate('Y-m-d\TH:i:s\Z');
        $signature_payload = $timestamp . $endpoint . $body;
        $signature = base64_encode(hash_hmac('sha256', $signature_payload, $api_secret, true));
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'X-API-Key: ' . $api_key,
                'X-Facility-Code: ' . $facility_code,
                'X-Timestamp: ' . $timestamp,
                'X-Signature: ' . $signature
            ),
            CURLOPT_TIMEOUT => $CI->config->item('nhis_api_timeout') ?: 30,
            CURLOPT_CONNECTTIMEOUT => $CI->config->item('nhis_api_connect_timeout') ?: 10,
            // C-NHIS-6: Enforce SSL certificate verification
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            return array('success' => false, 'message' => 'API Error: ' . $error);
        }

        $decoded = json_decode($response, true);
        if ($http_code >= 200 && $http_code < 300) {
            return array('success' => true, 'data' => $decoded);
        }

        return array(
            'success' => false, 
            'message' => isset($decoded['message']) ? $decoded['message'] : 'API request failed',
            'http_code' => $http_code
        );
    }

    private function _mock_api_call($endpoint, $data) {
        // Simulate API responses for testing
        switch ($endpoint) {
            case 'validate_card':
                return $this->_mock_validate_card($data);
            case 'submit_claim':
                return $this->_mock_submit_claim($data);
            case 'status':
                return $this->_mock_status($data);
            case 'check_coverage':
                return $this->_mock_check_coverage($data);
            default:
                return array('success' => false, 'message' => 'Unknown endpoint');
        }
    }

    private function _mock_validate_card($data) {
        $member_id = isset($data['member_id']) ? $data['member_id'] : '';
        
        // Simulate different scenarios based on member ID patterns
        if (empty($member_id)) {
            return array('success' => false, 'message' => 'Member ID is required');
        }

        if (strpos($member_id, 'INVALID') !== false) {
            return array('success' => false, 'message' => 'Invalid NHIS Member ID');
        }

        if (strpos($member_id, 'EXPIRED') !== false) {
            return array(
                'success' => true,
                'data' => array(
                    'eligible' => false,
                    'member_id' => $member_id,
                    'name' => 'Test Patient',
                    'gender' => 'Male',
                    'dob' => '1990-01-15',
                    'expiry_date' => date('Y-m-d', strtotime('-30 days')),
                    'scheme_name' => 'NHIS Standard',
                    'scheme_type' => 'formal',
                    'message' => 'Card has expired'
                )
            );
        }

        // Default: valid card
        return array(
            'success' => true,
            'data' => array(
                'eligible' => true,
                'member_id' => $member_id,
                'name' => 'Test Patient',
                'gender' => 'Male',
                'dob' => '1990-01-15',
                'expiry_date' => date('Y-m-d', strtotime('+1 year')),
                'scheme_name' => 'NHIS Standard',
                'scheme_type' => 'formal',
                'message' => 'Member is eligible'
            )
        );
    }

    private function _mock_submit_claim($data) {
        $claim_number = isset($data['claim_number']) ? $data['claim_number'] : '';
        
        // Simulate submission
        return array(
            'success' => true,
            'data' => array(
                'response_code' => 'SUCCESS',
                'reference_id' => 'NHIS-' . strtoupper(substr(md5($claim_number . time()), 0, 12)),
                'message' => 'Claim submitted successfully',
                'submitted_at' => date('Y-m-d H:i:s')
            )
        );
    }

    private function _mock_status($data) {
        $reference_id = isset($data['reference_id']) ? $data['reference_id'] : '';
        
        // Simulate random status for testing
        $statuses = array('processing', 'approved', 'approved', 'approved', 'partial', 'rejected');
        $status = $statuses[array_rand($statuses)];

        $response = array(
            'success' => true,
            'data' => array(
                'reference_id' => $reference_id,
                'status' => $status
            )
        );

        if ($status === 'approved' || $status === 'partial') {
            $response['data']['approved_amount'] = isset($data['total_amount']) 
                ? $data['total_amount'] * ($status === 'partial' ? 0.8 : 1) 
                : 0;
        }

        if ($status === 'rejected') {
            $response['data']['reason'] = 'Mock rejection: Documentation incomplete';
        }

        return $response;
    }

    private function _mock_check_coverage($data) {
        return array(
            'success' => true,
            'data' => array(
                'covered' => true,
                'coverage_percentage' => 100,
                'max_limit' => null
            )
        );
    }

    // =========================================================================
    // RECONCILIATION
    // =========================================================================

    /**
     * Run reconciliation check
     */
    public function run_reconciliation($date = null) {
        $date = $date ?: date('Y-m-d');
        $issues = array();

        // Check for covered items billed to patient
        $issues = array_merge($issues, $this->_check_covered_billed_patient($date));

        // Check for not covered items billed to NHIS
        $issues = array_merge($issues, $this->_check_not_covered_billed_nhis($date));

        // Check for missing claims
        $issues = array_merge($issues, $this->_check_missing_claims($date));

        // Log issues
        foreach ($issues as $issue) {
            $this->db->insert('nhis_reconciliation', array(
                'reconciliation_date' => $date,
                'issue_type' => $issue['type'],
                'reference_type' => $issue['reference_type'],
                'reference_id' => $issue['reference_id'],
                'patient_no' => isset($issue['patient_no']) ? $issue['patient_no'] : null,
                'expected_amount' => isset($issue['expected']) ? $issue['expected'] : null,
                'actual_amount' => isset($issue['actual']) ? $issue['actual'] : null,
                'difference_amount' => isset($issue['difference']) ? $issue['difference'] : null,
                'description' => $issue['description']
            ));
        }

        $this->_log_audit('reconciliation', 'system', $date, null,
            json_encode(array('issues_found' => count($issues))), null, null, 'success');

        return array('success' => true, 'issues' => $issues, 'count' => count($issues));
    }

    private function _check_covered_billed_patient($date) {
        // Find NHIS patients with covered items but no NHIS claim
        $issues = array();
        
        $encounters = $this->db->select('I.IO_ID, I.patient_no, I.date_visit')
            ->from('patient_details_iop I')
            ->join('patient_personal_info P', 'P.patient_no = I.patient_no', 'inner')
            ->where('I.date_visit', $date)
            ->where('P.nhis_verified', 1)
            ->where("P.Insurance_comp LIKE '%NHIS%'")
            ->get()->result();

        foreach ($encounters as $enc) {
            $claim = $this->db->where('encounter_id', $enc->IO_ID)
                ->where('status !=', 'cancelled')
                ->get('nhis_claims')->row();

            if (!$claim) {
                $issues[] = array(
                    'type' => 'missing_claim',
                    'reference_type' => 'encounter',
                    'reference_id' => $enc->IO_ID,
                    'patient_no' => $enc->patient_no,
                    'description' => 'NHIS patient encounter without claim'
                );
            }
        }

        return $issues;
    }

    private function _check_not_covered_billed_nhis($date) {
        // H-NHIS-3: Check for claim items that are not covered but billed to NHIS
        $issues = array();
        
        // Find claim items with nhis_amount > 0 where coverage entry says not covered
        $query = $this->db->query("
            SELECT CI.id, CI.claim_id, CI.item_type, CI.item_id, CI.item_name, 
                   CI.nhis_amount, C.claim_number, C.patient_no
            FROM nhis_claim_items CI
            JOIN nhis_claims C ON C.id = CI.claim_id
            LEFT JOIN nhis_coverage NC ON NC.item_type = CI.item_type AND NC.item_id = CI.item_id
            WHERE DATE(C.created_at) = ?
              AND CI.nhis_amount > 0
              AND (NC.id IS NULL OR NC.is_active = 0 OR NC.formulary_status = 'not_listed')
        ", array($date));
        
        foreach ($query->result() as $row) {
            $issues[] = array(
                'type' => 'not_covered_billed_nhis',
                'reference_type' => 'claim_item',
                'reference_id' => $row->id,
                'patient_no' => $row->patient_no,
                'expected' => 0,
                'actual' => $row->nhis_amount,
                'difference' => $row->nhis_amount,
                'description' => "Item '{$row->item_name}' not in coverage but billed to NHIS (Claim: {$row->claim_number})"
            );
        }
        
        return $issues;
    }

    private function _check_missing_claims($date) {
        // H-NHIS-3: Check for NHIS encounters with billing but no claim generated
        $issues = array();
        
        // Find NHIS patients with billing records but no claim for that encounter
        $query = $this->db->query("
            SELECT DISTINCT I.IO_ID, I.patient_no, I.date_visit, IB.total_amount
            FROM patient_details_iop I
            JOIN patient_personal_info P ON P.patient_no = I.patient_no
            JOIN iop_billing IB ON IB.iop_id = I.IO_ID AND IB.InActive = 0
            LEFT JOIN nhis_claims NC ON NC.encounter_id = I.IO_ID AND NC.status != 'cancelled'
            WHERE I.date_visit = ?
              AND P.nhis_verified = 1
              AND P.Insurance_comp LIKE '%NHIS%'
              AND NC.id IS NULL
              AND IB.total_amount > 0
        ", array($date));
        
        foreach ($query->result() as $row) {
            $issues[] = array(
                'type' => 'billing_without_claim',
                'reference_type' => 'encounter',
                'reference_id' => $row->IO_ID,
                'patient_no' => $row->patient_no,
                'expected' => $row->total_amount,
                'actual' => 0,
                'difference' => $row->total_amount,
                'description' => 'NHIS patient has billing but no claim generated'
            );
        }
        
        return $issues;
    }

    // =========================================================================
    // AUDIT LOGGING
    // =========================================================================

    private function _log_audit($action, $ref_type, $ref_id, $patient_no = null, 
        $new_value = null, $request = null, $response = null, $status = 'success', $error = null) {
        
        if (!$this->config->item('nhis_audit_enabled')) return;

        $this->db->insert('nhis_audit_log', array(
            'action_type' => $action,
            'reference_type' => $ref_type,
            'reference_id' => $ref_id,
            'patient_no' => $patient_no,
            'new_value' => $new_value,
            'api_request' => $request,
            'api_response' => $response,
            'status' => $status,
            'error_message' => $error,
            'performed_by' => $this->session->userdata('user_id'),
            'ip_address' => $this->input->ip_address()
        ));
    }

    private function _upsert_enforcement_state($ref_type, $ref_id, $patient_no, $enforcement_flag, $mode, $errors)
    {
        if (!$this->config->item('nhis_audit_enabled')) {
            return;
        }

        $ref_type = (string)$ref_type;
        $ref_id = (int)$ref_id;
        $enforcement_flag = strtoupper(trim((string)$enforcement_flag));
        $mode = (int)$mode;
        if ($mode < 0 || $mode > 3) {
            $mode = 0;
        }

        $payload = array(
            'enforcement_flag' => $enforcement_flag,
            'mode' => $mode,
        );

        $errors_json = json_encode(is_array($errors) ? $errors : array());
        $now_user = $this->session->userdata('user_id');
        $ip = $this->input->ip_address();

        $pk = $this->_get_nhis_audit_pk_column();
        $this->db->select($pk);
        $this->db->from('nhis_audit_log');
        $this->db->where('reference_type', $ref_type);
        $this->db->where('reference_id', $ref_id);
        $this->db->where('action_type', 'enforcement_state');
        $this->db->where('status', 'active');
        $this->db->order_by($pk, 'DESC');
        $this->db->limit(1);
        $existing = $this->db->get()->row();

        $record = array(
            'action_type' => 'enforcement_state',
            'reference_type' => $ref_type,
            'reference_id' => $ref_id,
            'patient_no' => $patient_no,
            'new_value' => json_encode($payload),
            'api_request' => null,
            'api_response' => $errors_json,
            'status' => 'active',
            'error_message' => null,
            'performed_by' => $now_user,
            'ip_address' => $ip
        );

        if ($existing && isset($existing->{$pk})) {
            $this->db->where($pk, (int)$existing->{$pk})->update('nhis_audit_log', $record);
            $this->db->where('reference_type', $ref_type)
                ->where('reference_id', $ref_id)
                ->where('action_type', 'enforcement_state')
                ->where('status', 'active')
                ->where("{$pk} !=", (int)$existing->{$pk})
                ->update('nhis_audit_log', array('status' => 'superseded'));
            return;
        }

        $this->db->where('reference_type', $ref_type)
            ->where('reference_id', $ref_id)
            ->where('action_type', 'enforcement_state')
            ->where('status', 'active')
            ->update('nhis_audit_log', array('status' => 'superseded'));

        $this->db->insert('nhis_audit_log', $record);
    }

    public function is_claim_quarantined($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return false;
        }

        $pk = $this->_get_nhis_audit_pk_column();
        $this->db->select($pk);
        $this->db->from('nhis_audit_log');
        $this->db->where('reference_type', 'claim');
        $this->db->where('reference_id', $claim_id);
        $this->db->where('action_type', 'enforcement_state');
        $this->db->where('status', 'active');
        $this->db->order_by($pk, 'DESC');
        $this->db->limit(1);
        $row = $this->db->get()->row();

        return !empty($row);
    }

    public function get_quarantined_claims($filters = array())
    {
        $this->db->select('C.*');
        $this->db->select('A.new_value as enforcement_new_value');
        $this->db->select('A.error_message as enforcement_error_message');
        $this->db->select('A.api_response as enforcement_api_response');
        $this->db->select('A.performed_by as enforcement_performed_by');
        $this->db->select('A.created_at as enforcement_created_at');
        $this->db->from('nhis_claims C');
        $this->db->join(
            'nhis_audit_log A',
            "A.reference_type = 'claim'"
            . " AND A.reference_id = C.id"
            . " AND A.action_type = 'enforcement_state'"
            . " AND A.status = 'active'",
            'inner',
            false
        );

        if (is_array($filters)) {
            if (!empty($filters['facility_id'])) {
                if ($this->db->field_exists('facility_id', 'nhis_claims')) {
                    $this->db->where('C.facility_id', $filters['facility_id']);
                } elseif ($this->db->field_exists('facility_code', 'nhis_claims')) {
                    $this->db->where('C.facility_code', $filters['facility_id']);
                }
            }
            if (!empty($filters['facility_code']) && $this->db->field_exists('facility_code', 'nhis_claims')) {
                $this->db->where('C.facility_code', $filters['facility_code']);
            }
            if (!empty($filters['status'])) {
                $this->db->where('C.status', $filters['status']);
            }
            if (!empty($filters['date_from'])) {
                $this->db->where('C.created_at >=', $filters['date_from']);
            }
            if (!empty($filters['date_to'])) {
                $this->db->where('C.created_at <=', $filters['date_to']);
            }
        }

        $this->db->order_by('C.created_at', 'DESC');
        return $this->db->get()->result_array();
    }

    public function get_claim_enforcement_details($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $pk = $this->_get_nhis_audit_pk_column();

        return $this->db
            ->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'enforcement_state')
            ->where('status', 'active')
            ->order_by($pk, 'DESC')
            ->limit(1)
            ->get('nhis_audit_log')
            ->row_array();
    }

    public function revalidate_claim($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return array('success' => false, 'message' => 'Invalid claim id');
        }

        if (!$this->is_claim_quarantined($claim_id)) {
            return array('success' => false, 'message' => 'Claim is not quarantined');
        }

        $this->load->model('app/Nhis_validation_model', 'validator');

        $payload = $this->_build_claim_payload($claim_id);
        if (empty($payload)) {
            return array('success' => false, 'message' => 'Unable to build claim payload');
        }

        $result = $this->validator->validate_claim_payload($payload);
        if (empty($result['valid'])) {
            log_message('debug', '[NHIS_REVALIDATION_FAIL] claim_id=' . (int)$claim_id);
            return array(
                'success' => false,
                'errors' => isset($result['errors']) ? $result['errors'] : array(),
            );
        }

        $claim = $this->db->select('patient_no')->where('id', $claim_id)->get('nhis_claims')->row();
        $this->_log_audit(
            'revalidation_passed',
            'claim',
            $claim_id,
            $claim && isset($claim->patient_no) ? (string)$claim->patient_no : null,
            json_encode(array(
                'revalidated' => true,
                'timestamp' => date('Y-m-d H:i:s'),
            )),
            null,
            null,
            'active',
            null
        );

        return array('success' => true);
    }

    public function release_claim_from_quarantine($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return array('success' => false, 'message' => 'Invalid claim id');
        }

        if (!$this->is_claim_quarantined($claim_id)) {
            return array('success' => false, 'message' => 'Not quarantined');
        }

        $recheck = $this->revalidate_claim($claim_id);
        if (empty($recheck['success'])) {
            return $recheck;
        }

        $claim = $this->db->select('patient_no')->where('id', $claim_id)->get('nhis_claims')->row();
        $this->_log_audit(
            'enforcement_release',
            'claim',
            $claim_id,
            $claim && isset($claim->patient_no) ? (string)$claim->patient_no : null,
            json_encode(array(
                'released_after_revalidation' => true,
                'timestamp' => date('Y-m-d H:i:s'),
            )),
            null,
            null,
            'active',
            null
        );

        $this->db->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->where('action_type', 'enforcement_state')
            ->where('status', 'active')
            ->update('nhis_audit_log', array('status' => 'superseded'));

        log_message('info', '[NHIS_QUARANTINE_RELEASED] claim_id=' . (int)$claim_id);

        return array('success' => true);
    }

    private function _apply_quarantine_exclusion($claim_alias = 'C')
    {
        $this->_apply_active_claim_filter($this->db, $claim_alias, false);
    }

    private function _build_claim_payload($claim_id)
    {
        $claim_id = (int)$claim_id;
        if ($claim_id <= 0) {
            return null;
        }

        $claim = $this->db->where('id', $claim_id)->get('nhis_claims')->row();
        if (!$claim || !isset($claim->encounter_id)) {
            return null;
        }

        $encounter_id = (int)$claim->encounter_id;

        $services = array();

        if (method_exists($this->db, 'table_exists') && $this->db->table_exists('iop_medication')) {
            $meds = $this->db->select('iop_med_id')
                ->from('iop_medication')
                ->where('iop_id', $encounter_id)
                ->where('InActive', 0)
                ->get()->result();
            foreach ($meds as $m) {
                $id = isset($m->iop_med_id) ? (int)$m->iop_med_id : 0;
                if ($id > 0) {
                    $services[] = array('module' => 'PHARMACY', 'id' => $id);
                }
            }
        }

        if (method_exists($this->db, 'table_exists') && $this->db->table_exists('iop_laboratory')) {
            $this->db->select('io_lab_id');
            $this->db->from('iop_laboratory');
            $this->db->where('iop_id', $encounter_id);
            $this->db->where('InActive', 0);
            if (method_exists($this->db, 'field_exists') && $this->db->field_exists('category_id', 'iop_laboratory')) {
                $this->db->where('category_id !=', 18);
            }
            $labs = $this->db->get()->result();
            foreach ($labs as $l) {
                $id = isset($l->io_lab_id) ? (int)$l->io_lab_id : 0;
                if ($id > 0) {
                    $services[] = array('module' => 'LAB', 'id' => $id);
                }
            }
        }

        if (method_exists($this->db, 'table_exists') && $this->db->table_exists('iop_sonography_charge')) {
            $sonos = $this->db->select('charge_id')
                ->from('iop_sonography_charge')
                ->where('iop_id', $encounter_id)
                ->where('InActive', 0)
                ->get()->result();
            foreach ($sonos as $s) {
                $id = isset($s->charge_id) ? (int)$s->charge_id : 0;
                if ($id > 0) {
                    $services[] = array('module' => 'SONOGRAPHY', 'id' => $id);
                }
            }
        }

        $diag_codes = array();
        if (isset($claim->diagnosis_codes) && $claim->diagnosis_codes !== null && $claim->diagnosis_codes !== '') {
            $decoded = json_decode($claim->diagnosis_codes, true);
            if (is_array($decoded)) {
                foreach ($decoded as $d) {
                    if (is_array($d) && isset($d['code']) && trim((string)$d['code']) !== '') {
                        $diag_codes[] = (string)$d['code'];
                    } elseif (is_string($d) && trim($d) !== '') {
                        $diag_codes[] = trim($d);
                    }
                }
            }
        }

        if (empty($diag_codes) && method_exists($this, '_get_encounter_diagnoses')) {
            $fallback = $this->_get_encounter_diagnoses($encounter_id);
            if (is_array($fallback)) {
                foreach ($fallback as $d) {
                    if (is_array($d) && isset($d['code']) && trim((string)$d['code']) !== '') {
                        $diag_codes[] = (string)$d['code'];
                    }
                }
            }
        }

        if (empty($services) && empty($diag_codes)) {
            return null;
        }

        return array(
            'services' => $services,
            'diagnoses' => $diag_codes,
            'procedures' => array(),
        );
    }

    private function _get_nhis_audit_pk_column()
    {
        if ($this->nhis_audit_pk_column !== null) {
            return $this->nhis_audit_pk_column;
        }

        $pk = 'id';
        try {
            if (method_exists($this->db, 'field_exists')) {
                if ($this->db->field_exists('id', 'nhis_audit_log')) {
                    $pk = 'id';
                } elseif ($this->db->field_exists('audit_id', 'nhis_audit_log')) {
                    $pk = 'audit_id';
                }
            }
        } catch (Exception $e) {
            $pk = 'id';
        }

        $this->nhis_audit_pk_column = $pk;
        return $pk;
    }

    private function _apply_active_claim_filter($db, $alias = 'C', $include_quarantined = false)
    {
        if ($include_quarantined) {
            return;
        }

        $alias = trim((string)$alias);
        if ($alias === '') {
            $alias = 'C';
        }

        // Safety guard: only apply this filter if nhis_claims has an "id" column.
        // Some legacy schemas may not yet have the new PK; in that case we skip the
        // quarantine filter instead of breaking queries with "Unknown column 'C.id'".
        if (!$this->db->field_exists('id', 'nhis_claims')) {
            return;
        }

        $db->where(
            "NOT EXISTS (\n"
            . "  SELECT 1\n"
            . "  FROM nhis_audit_log nal\n"
            . "  WHERE nal.reference_type = 'claim'\n"
            . "    AND nal.reference_id = {$alias}.id\n"
            . "    AND nal.action_type = 'enforcement_state'\n"
            . "    AND nal.status = 'active'\n"
            . ")",
            null,
            false
        );
    }

    // =========================================================================
    // REPORTING & QUERIES
    // =========================================================================

    /**
     * Count claims for pagination
     * M-NHIS-5: Added for pagination support
     */
    public function count_claims($filters = array()) {
        $this->db->from('nhis_claims C');

        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        
        if (!empty($filters['status'])) {
            $this->db->where('C.status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('C.created_at >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('C.created_at <=', $filters['date_to']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('C.patient_no', $filters['patient_no']);
        }
        
        return $this->db->count_all_results();
    }

    /**
     * Get claims list with filters
     * M-NHIS-5: Supports pagination via limit/offset
     */
    public function get_claims($filters = array(), $limit = 50, $offset = 0) {
        $this->db->select('C.*, P.firstname, P.lastname')
            ->from('nhis_claims C')
            ->join('patient_personal_info P', 'P.patient_no = C.patient_no', 'left');

        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));

        if (!empty($filters['status'])) {
            $this->db->where('C.status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('C.created_at >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('C.created_at <=', $filters['date_to']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('C.patient_no', $filters['patient_no']);
        }

        return $this->db->order_by('C.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result();
    }

    /**
     * Get claim details with items
     */
    public function get_claim_details($claim_id, $filters = array()) {
        $this->db->select('C.*, P.firstname, P.lastname, P.nhis_member_id as patient_nhis_id')
            ->from('nhis_claims C')
            ->join('patient_personal_info P', 'P.patient_no = C.patient_no', 'left')
            ->where('C.id', $claim_id);

        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));

        $claim = $this->db->get()->row();

        if ($claim) {
            $claim->items = $this->db->where('claim_id', $claim_id)
                ->get('nhis_claim_items')->result();
        }

        return $claim;
    }

    /**
     * Get NHIS summary statistics
     */
    public function get_summary_stats($date_from = null, $date_to = null, $filters = array()) {
        $date_from = $date_from ?: date('Y-m-01');
        $date_to = $date_to ?: date('Y-m-d');

        $stats = array();

        // Total claims
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $stats['total_claims'] = $this->db->where('C.created_at >=', $date_from)
            ->where('C.created_at <=', $date_to)
            ->count_all_results();

        // Claims by status
        $this->db->select('C.status, COUNT(*) as count, SUM(C.total_amount) as amount');
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $by_status = $this->db->where('C.created_at >=', $date_from)
            ->where('C.created_at <=', $date_to)
            ->group_by('C.status')
            ->get()->result();

        $stats['by_status'] = array();
        foreach ($by_status as $s) {
            $stats['by_status'][$s->status] = array(
                'count' => (int)$s->count,
                'amount' => (float)$s->amount
            );
        }

        // Total amounts
        $this->db->select('SUM(C.total_amount) as claimed, SUM(C.approved_amount) as approved, SUM(C.patient_amount) as copay');
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $totals = $this->db->where('C.created_at >=', $date_from)
            ->where('C.created_at <=', $date_to)
            ->get()->row();

        $stats['total_claimed'] = (float)($totals->claimed ?: 0);
        $stats['total_approved'] = (float)($totals->approved ?: 0);
        $stats['total_copay'] = (float)($totals->copay ?: 0);

        return $stats;
    }

    /**
     * Get coverage list
     */
    public function get_coverage_list($item_type = null, $limit = 100, $offset = 0) {
        if ($item_type) {
            $this->db->where('item_type', $item_type);
        }
        return $this->db->order_by('item_type, item_name')
            ->limit($limit, $offset)
            ->get('nhis_coverage')->result();
    }

    /**
     * Get reconciliation issues
     */
    public function get_reconciliation_issues($status = 'open', $limit = 50) {
        return $this->db->where('status', $status)
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get('nhis_reconciliation')->result();
    }

    /**
     * Resolve reconciliation issue
     */
    public function resolve_reconciliation_issue($issue_id, $notes, $user_id) {
        return $this->db->where('id', $issue_id)->update('nhis_reconciliation', array(
            'status' => 'resolved',
            'resolution_notes' => $notes,
            'resolved_by' => $user_id,
            'resolved_at' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Get current mode
     */
    public function get_mode() {
        return $this->mode;
    }

    /**
     * Get dashboard statistics
     */
    public function get_dashboard_stats($filters = array()) {
        $today = date('Y-m-d');
        $month_start = date('Y-m-01');
        
        $stats = array();
        
        // Today's claims
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $stats['today_claims'] = $this->db->where('DATE(C.created_at)', $today)
            ->count_all_results();
        
        // Pending claims
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $stats['pending_claims'] = $this->db->where_in('C.status', array('draft', 'pending'))
            ->count_all_results();
        
        // Submitted awaiting response
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $stats['submitted_claims'] = $this->db->where('C.status', 'submitted')
            ->count_all_results();
        
        // Approved this month
        $this->db->select('COUNT(*) as count, SUM(C.approved_amount) as amount');
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $approved = $this->db->where('C.status', 'approved')
            ->where('C.approved_at >=', $month_start)
            ->get()->row();
        $stats['approved_count'] = (int)($approved->count ?: 0);
        $stats['approved_amount'] = (float)($approved->amount ?: 0);
        
        // Rejected this month
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $stats['rejected_claims'] = $this->db->where('C.status', 'rejected')
            ->where('C.created_at >=', $month_start)
            ->count_all_results();
        
        // Total claimed this month
        $this->db->select('SUM(C.total_amount) as amount');
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $total = $this->db->where('C.created_at >=', $month_start)
            ->get()->row();
        $stats['month_claimed'] = (float)($total->amount ?: 0);
        
        // Open reconciliation issues
        $stats['open_issues'] = $this->db->where('status', 'open')
            ->count_all_results('nhis_reconciliation');
        
        return $stats;
    }

    /**
     * Get recent claims
     */
    public function get_recent_claims($limit = 10, $filters = array()) {
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        return $this->db->select('C.*, P.firstname, P.lastname')
            ->from('nhis_claims C')
            ->join('patient_personal_info P', 'P.patient_no = C.patient_no', 'left')
            ->order_by('C.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Get claims by status
     */
    public function get_claims_by_status($status, $limit = 10, $filters = array()) {
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        return $this->db->select('C.*, P.firstname, P.lastname')
            ->from('nhis_claims C')
            ->join('patient_personal_info P', 'P.patient_no = C.patient_no', 'left')
            ->where('C.status', $status)
            ->order_by('C.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Get open reconciliation issues
     */
    public function get_open_reconciliation_issues($limit = 10) {
        return $this->db->where('status', 'open')
            ->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get('nhis_reconciliation')->result();
    }

    /**
     * Get single claim
     */
    public function get_claim($id, $filters = array()) {
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        return $this->db->select('C.*, P.firstname, P.lastname, P.nhis_member_id as patient_nhis_id')
            ->from('nhis_claims C')
            ->join('patient_personal_info P', 'P.patient_no = C.patient_no', 'left')
            ->where('C.id', $id)
            ->get()->row();
    }

    /**
     * Get claim items
     */
    public function get_claim_items($claim_id) {
        return $this->db->where('claim_id', $claim_id)
            ->order_by('item_type, item_name')
            ->get('nhis_claim_items')->result();
    }

    /**
     * Get claim audit log
     */
    public function get_claim_audit_log($claim_id) {
        return $this->db->where('reference_type', 'claim')
            ->where('reference_id', $claim_id)
            ->order_by('created_at', 'DESC')
            ->get('nhis_audit_log')->result();
    }

    /**
     * Get coverage items
     */
    public function get_coverage_items($item_type = null, $search = null) {
        if ($item_type) {
            $this->db->where('item_type', $item_type);
        }
        if ($search) {
            $this->db->group_start()
                ->like('item_name', $search)
                ->or_like('nhis_code', $search)
                ->group_end();
        }
        return $this->db->order_by('item_name')
            ->get('nhis_coverage')->result();
    }

    /**
     * Get reconciliation summary
     */
    public function get_reconciliation_summary() {
        $summary = array();
        
        $by_status = $this->db->select('status, COUNT(*) as count')
            ->group_by('status')
            ->get('nhis_reconciliation')->result();
        
        foreach ($by_status as $s) {
            $summary[$s->status] = (int)$s->count;
        }
        
        $by_type = $this->db->select('issue_type, COUNT(*) as count')
            ->where('status', 'open')
            ->group_by('issue_type')
            ->get('nhis_reconciliation')->result();
        
        $summary['by_type'] = array();
        foreach ($by_type as $t) {
            $summary['by_type'][$t->issue_type] = (int)$t->count;
        }
        
        return $summary;
    }

    /**
     * Get audit logs with filters
     */
    public function get_audit_logs($filters = array(), $limit = 100) {
        if (!empty($filters['action_type'])) {
            $this->db->where('action_type', $filters['action_type']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('patient_no', $filters['patient_no']);
        }
        if (!empty($filters['from_date'])) {
            $this->db->where('created_at >=', $filters['from_date'] . ' 00:00:00');
        }
        if (!empty($filters['to_date'])) {
            $this->db->where('created_at <=', $filters['to_date'] . ' 23:59:59');
        }

        return $this->db->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get('nhis_audit_log')->result();
    }

    /**
     * Get claims summary report
     */
    public function get_claims_summary_report($from_date, $to_date, $filters = array()) {
        $this->db->select("
            DATE(C.created_at) as date,
            COUNT(*) as total_claims,
            SUM(CASE WHEN C.status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN C.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN C.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(C.total_amount) as total_amount,
            SUM(C.approved_amount) as approved_amount,
            SUM(C.patient_amount) as copay_amount
        ", false);
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        return $this->db->where('C.created_at >=', $from_date)
            ->where('C.created_at <=', $to_date)
            ->group_by('DATE(C.created_at)')
            ->order_by('date', 'DESC')
            ->get()->result();
    }

    /**
     * Get coverage analysis report
     */
    public function get_coverage_analysis_report($from_date, $to_date, $filters = array()) {
        $this->db->select("
            CI.item_type,
            COUNT(*) as item_count,
            SUM(CI.total_amount) as total_amount,
            SUM(CI.nhis_amount) as nhis_amount,
            SUM(CI.patient_amount) as patient_amount,
            AVG(CI.coverage_percentage) as avg_coverage
        ", false)
            ->from('nhis_claim_items CI')
            ->join('nhis_claims C', 'C.claim_id = CI.claim_id', 'inner')
            ->where('C.created_at >=', $from_date)
            ->where('C.created_at <=', $to_date);

        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));

        return $this->db->group_by('CI.item_type')
            ->get()->result();
    }

    /**
     * Get rejection analysis report
     */
    public function get_rejection_analysis_report($from_date, $to_date, $filters = array()) {
        $this->db->select('C.rejection_reason, COUNT(*) as count');
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        return $this->db->where('C.status', 'rejected')
            ->where('C.created_at >=', $from_date)
            ->where('C.created_at <=', $to_date)
            ->where('C.rejection_reason IS NOT NULL', null, false)
            ->group_by('C.rejection_reason')
            ->order_by('count', 'DESC')
            ->get()->result();
    }

    /**
     * Get revenue breakdown report
     */
    public function get_revenue_breakdown_report($from_date, $to_date, $filters = array()) {
        $data = array();
        
        // NHIS vs Patient revenue
        $this->db->select("
            SUM(C.total_amount) as total,
            SUM(C.approved_amount) as nhis_revenue,
            SUM(C.patient_amount) as patient_revenue
        ", false);
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $totals = $this->db->where('C.created_at >=', $from_date)
            ->where('C.created_at <=', $to_date)
            ->get()->row();
        
        $data['totals'] = $totals;
        
        // By status instead of encounter_type (column doesn't exist in actual table)
        $this->db->select("
            C.status,
            COUNT(*) as claims,
            SUM(C.total_amount) as total,
            SUM(C.approved_amount) as nhis,
            SUM(C.patient_amount) as patient
        ", false);
        $this->db->from('nhis_claims C');
        $this->_apply_active_claim_filter($this->db, 'C', !empty($filters['include_quarantined']));
        $data['by_type'] = $this->db->where('C.created_at >=', $from_date)
            ->where('C.created_at <=', $to_date)
            ->group_by('C.status')
            ->get()->result();
        
        return $data;
    }

    /**
     * Get audit log
     */
    public function get_audit_log($filters = array(), $limit = 100) {
        if (!empty($filters['action_type'])) {
            $this->db->where('action_type', $filters['action_type']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('patient_no', $filters['patient_no']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('created_at >=', $filters['date_from'] . ' 00:00:00');
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('created_at <=', $filters['date_to'] . ' 23:59:59');
        }

        return $this->db->order_by('created_at', 'DESC')
            ->limit($limit)
            ->get('nhis_audit_log')->result();
    }
}
