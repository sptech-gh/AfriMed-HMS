<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Service Billing Model - Unified Billing Engine
 * 
 * Single Source of Truth for all HMS billing:
 * - Lab Tests
 * - Sonography/Radiology
 * - Procedures
 * - Medications
 * 
 * Pricing Priority: Company > Private Insurance > NHIS > Default
 * 
 * @author HMS Architect
 * @version 1.0
 */
class Service_billing_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  SCHEMA INSTALLATION (idempotent)                                   */
    /* ================================================================== */

    public function ensure_service_billing_schema()
    {
        $this->_install_service_orders();
        $this->_install_company_pricing();
        $this->_install_billing_approvals();
        $this->_install_billing_audit_log();
        $this->_install_insurance_coverage();
    }

    private function table_exists($table)
    {
        return $this->db->table_exists($table);
    }

    private function column_exists($table, $column)
    {
        return $this->db->field_exists($column, $table);
    }

    /**
     * Unified service orders table - tracks all billable services
     */
    private function _install_service_orders()
    {
        // Use IF NOT EXISTS for atomic, race-condition safe table creation
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `service_orders` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `order_no` VARCHAR(30) NOT NULL,
                `visit_id` VARCHAR(30) NOT NULL COMMENT 'iop_id / IO_ID',
                `patient_no` VARCHAR(25) NOT NULL,
                `encounter_type` VARCHAR(10) NOT NULL DEFAULT 'OPD' COMMENT 'OPD, IPD',
                `service_type` VARCHAR(30) NOT NULL COMMENT 'LAB, SONOGRAPHY, RADIOLOGY, PROCEDURE, MEDICATION',
                `service_id` INT DEFAULT NULL COMMENT 'FK to specific service table',
                `service_name` VARCHAR(255) NOT NULL,
                `department` VARCHAR(50) DEFAULT NULL,
                `quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
                `base_price` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Default price from master',
                `final_price` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'After pricing engine',
                `coverage_type` VARCHAR(30) DEFAULT 'CASH' COMMENT 'CASH, NHIS, INSURANCE, COMPANY',
                `coverage_percent` DECIMAL(5,2) DEFAULT 0,
                `patient_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Amount patient pays',
                `covered_amount` DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Amount covered by payer',
                `insurance_id` INT DEFAULT NULL COMMENT 'FK to insurance_companies',
                `company_id` INT DEFAULT NULL COMMENT 'FK to corporate_companies',
                `nhis_member_id` VARCHAR(50) DEFAULT NULL,
                `billing_status` VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, BILLED, INVOICED',
                `payment_status` VARCHAR(20) NOT NULL DEFAULT 'UNPAID' COMMENT 'UNPAID, PAID, COVERED, WAIVED, DEFERRED',
                `approval_status` VARCHAR(20) DEFAULT NULL COMMENT 'APPROVED, REJECTED, PENDING',
                `approval_id` INT DEFAULT NULL COMMENT 'FK to billing_approvals',
                `service_status` VARCHAR(20) NOT NULL DEFAULT 'REQUESTED' COMMENT 'REQUESTED, PAID, IN_PROGRESS, COMPLETED, CANCELLED',
                `invoice_no` VARCHAR(30) DEFAULT NULL,
                `receipt_no` VARCHAR(30) DEFAULT NULL,
                `reference_table` VARCHAR(50) DEFAULT NULL COMMENT 'Source table name',
                `reference_id` INT DEFAULT NULL COMMENT 'Source record ID',
                `requested_by` VARCHAR(25) DEFAULT NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                `InActive` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_order_no` (`order_no`),
                KEY `idx_so_visit` (`visit_id`),
                KEY `idx_so_patient` (`patient_no`),
                KEY `idx_so_type` (`service_type`),
                KEY `idx_so_billing` (`billing_status`),
                KEY `idx_so_payment` (`payment_status`),
                KEY `idx_so_service` (`service_status`),
                KEY `idx_so_invoice` (`invoice_no`),
                KEY `idx_so_date` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Unified service orders - single source of truth'
        ");
    }

    /**
     * Company/Corporate pricing overrides
     */
    private function _install_company_pricing()
    {
        // Use IF NOT EXISTS for atomic, race-condition safe table creation
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `corporate_companies` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `company_name` VARCHAR(150) NOT NULL,
                    `company_code` VARCHAR(20) DEFAULT NULL,
                    `contact_person` VARCHAR(100) DEFAULT NULL,
                    `phone` VARCHAR(30) DEFAULT NULL,
                    `email` VARCHAR(100) DEFAULT NULL,
                    `address` TEXT DEFAULT NULL,
                    `credit_limit` DECIMAL(18,2) DEFAULT 0,
                    `current_balance` DECIMAL(18,2) DEFAULT 0,
                    `payment_terms` VARCHAR(50) DEFAULT 'NET30',
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `InActive` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_company_code` (`company_code`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Corporate/Company accounts'
            ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `company_pricing` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `company_id` INT UNSIGNED NOT NULL,
                    `service_type` VARCHAR(30) DEFAULT NULL COMMENT 'NULL = all services',
                    `service_id` INT DEFAULT NULL COMMENT 'NULL = all in type',
                    `pricing_type` VARCHAR(20) NOT NULL DEFAULT 'PERCENT' COMMENT 'FIXED, PERCENT, DISCOUNT',
                    `value` DECIMAL(18,4) NOT NULL DEFAULT 0 COMMENT 'Fixed price or percentage',
                    `effective_from` DATE DEFAULT NULL,
                    `effective_to` DATE DEFAULT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `created_by` VARCHAR(25) DEFAULT NULL,
                    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `InActive` TINYINT(1) NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    KEY `idx_cp_company` (`company_id`),
                    KEY `idx_cp_service` (`service_type`, `service_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Company-specific pricing rules'
            ");
    }

    /**
     * Billing approvals for exceptions (waive, defer, emergency)
     */
    private function _install_billing_approvals()
    {
        // Use IF NOT EXISTS for atomic, race-condition safe table creation
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `billing_approvals` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `service_order_id` INT UNSIGNED DEFAULT NULL,
                `invoice_no` VARCHAR(30) DEFAULT NULL,
                `patient_no` VARCHAR(25) NOT NULL,
                `approval_type` VARCHAR(30) NOT NULL COMMENT 'WAIVE, DEFER, EMERGENCY, CREDIT, DISCOUNT',
                `original_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
                `approved_amount` DECIMAL(18,2) NOT NULL DEFAULT 0,
                `discount_percent` DECIMAL(5,2) DEFAULT 0,
                `reason` TEXT NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING' COMMENT 'PENDING, APPROVED, REJECTED',
                `requested_by` VARCHAR(25) NOT NULL,
                `approved_by` VARCHAR(25) DEFAULT NULL,
                `approved_at` DATETIME DEFAULT NULL,
                `expiry_date` DATE DEFAULT NULL COMMENT 'For deferred payments',
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `InActive` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_ba_order` (`service_order_id`),
                KEY `idx_ba_invoice` (`invoice_no`),
                KEY `idx_ba_patient` (`patient_no`),
                KEY `idx_ba_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Billing exception approvals'
        ");
    }

    /**
     * Comprehensive billing audit log
     */
    private function _install_billing_audit_log()
    {
        // Check if table exists with wrong schema (has 'audit_id' instead of 'id')
        if ($this->table_exists('billing_audit_log')) {
            // Check if it's the old schema by looking for 'audit_id' column
            $has_old_schema = $this->db->field_exists('audit_id', 'billing_audit_log');
            if ($has_old_schema) {
                // Backup old table and create new one with correct schema
                $this->db->query("RENAME TABLE `billing_audit_log` TO `billing_audit_log_backup_" . date('YmdHis') . "`");
            }
        }
        
        // Use IF NOT EXISTS for atomic, race-condition safe table creation
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `billing_audit_log` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `action_type` VARCHAR(50) NOT NULL COMMENT 'PRICE_CHANGE, DISCOUNT, WAIVE, VOID, etc',
                `service_order_id` INT DEFAULT NULL,
                `invoice_no` VARCHAR(30) DEFAULT NULL,
                `patient_no` VARCHAR(25) DEFAULT NULL,
                `old_value` TEXT DEFAULT NULL,
                `new_value` TEXT DEFAULT NULL,
                `amount_before` DECIMAL(18,2) DEFAULT NULL,
                `amount_after` DECIMAL(18,2) DEFAULT NULL,
                `reason` TEXT DEFAULT NULL,
                `performed_by` VARCHAR(25) NOT NULL,
                `performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `ip_address` VARCHAR(45) DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_bal_order` (`service_order_id`),
                KEY `idx_bal_invoice` (`invoice_no`),
                KEY `idx_bal_patient` (`patient_no`),
                KEY `idx_bal_action` (`action_type`),
                KEY `idx_bal_date` (`performed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Billing audit trail'
        ");
    }

    /**
     * Insurance companies table
     */
    private function _install_insurance_companies()
    {
        // Use IF NOT EXISTS for atomic, race-condition safe table creation
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `insurance_companies` (
                `insurance_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `insurance_name` VARCHAR(150) NOT NULL,
                `insurance_code` VARCHAR(20) DEFAULT NULL,
                `coverage_type` VARCHAR(30) DEFAULT 'PERCENTAGE' COMMENT 'PERCENTAGE, FIXED, COPAY',
                `default_percent` DECIMAL(5,2) NOT NULL DEFAULT 80,
                `contact_person` VARCHAR(100) DEFAULT NULL,
                `phone` VARCHAR(30) DEFAULT NULL,
                `email` VARCHAR(100) DEFAULT NULL,
                `address` TEXT DEFAULT NULL,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `InActive` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`insurance_id`),
                UNIQUE KEY `uk_insurance_name` (`insurance_name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Private insurance companies'
        ");
    }

    /**
     * Insurance coverage rules
     */
    private function _install_insurance_coverage()
    {
        $this->_install_insurance_companies();
        
        // Use IF NOT EXISTS for atomic, race-condition safe table creation
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `insurance_coverage_rules` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `insurance_id` INT NOT NULL COMMENT 'FK to insurance_companies',
                `service_type` VARCHAR(30) DEFAULT NULL,
                `service_id` INT DEFAULT NULL,
                `coverage_percent` DECIMAL(5,2) NOT NULL DEFAULT 100,
                `max_amount` DECIMAL(18,2) DEFAULT NULL,
                `requires_preauth` TINYINT(1) NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `InActive` TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (`id`),
                KEY `idx_icr_insurance` (`insurance_id`),
                KEY `idx_icr_service` (`service_type`, `service_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Insurance coverage rules'
        ");
    }

    /* ================================================================== */
    /*  SERVICE ORDER MANAGEMENT                                           */
    /* ================================================================== */

    /**
     * Generate unique order number
     */
    public function generate_order_no()
    {
        $prefix = 'SO' . date('ymd');
        $this->db->select('MAX(CAST(SUBSTRING(order_no, 9) AS UNSIGNED)) as max_seq');
        $this->db->like('order_no', $prefix, 'after');
        $row = $this->db->get('service_orders')->row();
        $seq = ($row && $row->max_seq) ? (int)$row->max_seq + 1 : 1;
        return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Create a service order with automatic pricing
     */
    public function create_service_order($data)
    {
        $this->ensure_service_billing_schema();

        $required = ['visit_id', 'patient_no', 'service_type', 'service_name'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        // Generate order number
        $data['order_no'] = $this->generate_order_no();

        // Get patient coverage info
        $coverage = $this->get_patient_coverage($data['patient_no']);
        $data['coverage_type'] = $coverage['type'];
        $data['insurance_id'] = $coverage['insurance_id'];
        $data['company_id'] = $coverage['company_id'];
        $data['nhis_member_id'] = $coverage['nhis_member_id'];

        // Calculate pricing
        $pricing = $this->calculate_service_price(
            $data['service_type'],
            isset($data['service_id']) ? $data['service_id'] : null,
            isset($data['base_price']) ? (float)$data['base_price'] : 0,
            $data['patient_no']
        );

        $data['base_price'] = $pricing['base_price'];
        $data['final_price'] = $pricing['final_price'];
        $data['coverage_percent'] = $pricing['coverage_percent'];
        $data['patient_amount'] = $pricing['patient_amount'];
        $data['covered_amount'] = $pricing['covered_amount'];

        // Set defaults
        if (!isset($data['quantity'])) $data['quantity'] = 1;
        if (!isset($data['encounter_type'])) $data['encounter_type'] = 'OPD';
        if (!isset($data['billing_status'])) $data['billing_status'] = 'PENDING';
        if (!isset($data['payment_status'])) $data['payment_status'] = 'UNPAID';
        if (!isset($data['service_status'])) $data['service_status'] = 'REQUESTED';
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->db->insert('service_orders', $data);
        $id = $this->db->insert_id();

        if ($id) {
            $this->log_billing_audit('SERVICE_ORDER_CREATED', $id, null, $data['patient_no'],
                null, json_encode($data), null, $data['final_price'],
                'Service order created', isset($data['requested_by']) ? $data['requested_by'] : 'system');

            return ['success' => true, 'order_id' => $id, 'order_no' => $data['order_no'], 'pricing' => $pricing];
        }

        return ['success' => false, 'error' => 'Failed to create service order'];
    }

    /**
     * Get service order by ID
     */
    public function get_service_order($id)
    {
        return $this->db->get_where('service_orders', ['id' => (int)$id, 'InActive' => 0])->row();
    }

    /**
     * Get service orders for a visit
     */
    public function get_visit_service_orders($visit_id, $service_type = null)
    {
        $this->db->where('visit_id', (string)$visit_id);
        $this->db->where('InActive', 0);
        if ($service_type) {
            $this->db->where('service_type', $service_type);
        }
        $this->db->order_by('created_at', 'DESC');
        return $this->db->get('service_orders')->result();
    }

    /**
     * Get pending service orders for cashier
     */
    public function get_pending_service_orders($limit = 100)
    {
        $this->db->select('so.*, p.firstname, p.lastname, p.middlename');
        $this->db->from('service_orders so');
        $this->db->join('patient_personal_info p', 'p.patient_no = so.patient_no', 'left');
        $this->db->where('so.payment_status', 'UNPAID');
        $this->db->where('so.InActive', 0);
        $this->db->order_by('so.created_at', 'ASC');
        $this->db->limit($limit);
        return $this->db->get()->result();
    }

    /**
     * Update service order status
     */
    public function update_service_order_status($id, $billing_status = null, $payment_status = null, $service_status = null, $user_id = null)
    {
        $order = $this->get_service_order($id);
        if (!$order) return false;

        $update = ['updated_at' => date('Y-m-d H:i:s')];
        $changes = [];

        if ($billing_status !== null && $billing_status !== $order->billing_status) {
            $changes['billing_status'] = ['old' => $order->billing_status, 'new' => $billing_status];
            $update['billing_status'] = $billing_status;
        }
        if ($payment_status !== null && $payment_status !== $order->payment_status) {
            $changes['payment_status'] = ['old' => $order->payment_status, 'new' => $payment_status];
            $update['payment_status'] = $payment_status;
        }
        if ($service_status !== null && $service_status !== $order->service_status) {
            $changes['service_status'] = ['old' => $order->service_status, 'new' => $service_status];
            $update['service_status'] = $service_status;
        }

        if (count($changes) === 0) return true;

        $this->db->where('id', $id);
        $this->db->update('service_orders', $update);

        $this->log_billing_audit('STATUS_CHANGE', $id, $order->invoice_no, $order->patient_no,
            json_encode(['old' => $changes]), json_encode(['new' => $update]),
            null, null, 'Status updated', $user_id ?: 'system');

        return true;
    }

    /* ================================================================== */
    /*  PRICING ENGINE - Single Source of Truth                            */
    /* ================================================================== */

    /**
     * Calculate service price with priority: Company > Insurance > NHIS > Default
     */
    public function calculate_service_price($service_type, $service_id, $base_price, $patient_no)
    {
        $result = [
            'base_price' => (float)$base_price,
            'final_price' => (float)$base_price,
            'coverage_type' => 'CASH',
            'coverage_percent' => 0,
            'patient_amount' => (float)$base_price,
            'covered_amount' => 0,
            'pricing_source' => 'DEFAULT'
        ];

        // Get base price from master if not provided
        if ($base_price <= 0 && $service_id > 0) {
            $result['base_price'] = $this->get_default_price($service_type, $service_id);
            $result['final_price'] = $result['base_price'];
            $result['patient_amount'] = $result['base_price'];
        }

        // Get patient coverage info
        $coverage = $this->get_patient_coverage($patient_no);

        // Priority 1: Company Pricing
        if ($coverage['company_id'] > 0) {
            $companyPrice = $this->get_company_price($coverage['company_id'], $service_type, $service_id, $result['base_price']);
            if ($companyPrice !== null) {
                $result['final_price'] = $companyPrice;
                $result['coverage_type'] = 'COMPANY';
                $result['patient_amount'] = 0; // Company pays
                $result['covered_amount'] = $companyPrice;
                $result['pricing_source'] = 'COMPANY';
                return $result;
            }
        }

        // Priority 2: Private Insurance
        if ($coverage['insurance_id'] > 0) {
            $insuranceCoverage = $this->get_insurance_coverage($coverage['insurance_id'], $service_type, $service_id);
            if ($insuranceCoverage) {
                $result['coverage_type'] = 'INSURANCE';
                $result['coverage_percent'] = $insuranceCoverage['percent'];
                $result['covered_amount'] = $result['final_price'] * ($insuranceCoverage['percent'] / 100);
                $result['patient_amount'] = $result['final_price'] - $result['covered_amount'];
                $result['pricing_source'] = 'INSURANCE';
                return $result;
            }
        }

        // Priority 3: NHIS
        if ($coverage['is_nhis'] && $coverage['nhis_member_id']) {
            $nhisPrice = $this->get_nhis_price($service_type, $service_id);
            if ($nhisPrice !== null) {
                $result['final_price'] = $nhisPrice;
                $result['coverage_type'] = 'NHIS';
                $result['coverage_percent'] = 100;
                $result['patient_amount'] = 0;
                $result['covered_amount'] = $nhisPrice;
                $result['pricing_source'] = 'NHIS';
                return $result;
            }
        }

        // Default: Cash payment
        return $result;
    }

    /**
     * Get default price from service master tables
     */
    public function get_default_price($service_type, $service_id)
    {
        $service_id = (int)$service_id;
        if ($service_id <= 0) return 0;

        switch (strtoupper($service_type)) {
            case 'LAB':
            case 'LABORATORY':
            case 'SONOGRAPHY':
            case 'RADIOLOGY':
            case 'PROCEDURE':
                if ($this->table_exists('bill_particular')) {
                    $row = $this->db->select('charge_amount')->get_where('bill_particular', ['particular_id' => $service_id])->row();
                    return $row ? (float)$row->charge_amount : 0;
                }
                break;
            case 'MEDICATION':
                if ($this->table_exists('medicine_drug_name')) {
                    $row = $this->db->select('nPrice')->get_where('medicine_drug_name', ['drug_id' => $service_id])->row();
                    return $row ? (float)$row->nPrice : 0;
                }
                break;
        }
        return 0;
    }

    /**
     * Get patient coverage information
     */
    public function get_patient_coverage($patient_no)
    {
        $result = [
            'type' => 'CASH',
            'is_nhis' => false,
            'nhis_member_id' => null,
            'insurance_id' => null,
            'company_id' => null
        ];

        if (!$patient_no) return $result;

        $patient = $this->db->select('nhis_member_id, nhis_card_expiry, Insurance_comp, insurance_no, company_id')
            ->get_where('patient_personal_info', ['patient_no' => $patient_no])
            ->row();

        if (!$patient) return $result;

        // Check NHIS
        if (isset($patient->nhis_member_id) && trim((string)$patient->nhis_member_id) !== '') {
            $expiry = isset($patient->nhis_card_expiry) ? $patient->nhis_card_expiry : null;
            if (!$expiry || strtotime($expiry) >= strtotime(date('Y-m-d'))) {
                $result['is_nhis'] = true;
                $result['nhis_member_id'] = $patient->nhis_member_id;
                $result['type'] = 'NHIS';
            }
        }

        // Check Company
        if (isset($patient->company_id) && (int)$patient->company_id > 0) {
            $result['company_id'] = (int)$patient->company_id;
            $result['type'] = 'COMPANY';
        }

        // Check Insurance
        if (isset($patient->Insurance_comp) && trim((string)$patient->Insurance_comp) !== '') {
            // Try to find insurance company ID
            $ins = $this->db->select('insurance_id')->get_where('insurance_companies', ['insurance_name' => $patient->Insurance_comp])->row();
            if ($ins) {
                $result['insurance_id'] = (int)$ins->insurance_id;
                if ($result['type'] === 'CASH') {
                    $result['type'] = 'INSURANCE';
                }
            }
        }

        return $result;
    }

    /**
     * Get company-specific price
     */
    public function get_company_price($company_id, $service_type, $service_id, $base_price)
    {
        if (!$this->table_exists('company_pricing')) return null;

        // Try specific service first
        $this->db->where('company_id', (int)$company_id);
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->group_start();
            $this->db->where('service_type', $service_type);
            $this->db->where('service_id', (int)$service_id);
        $this->db->group_end();
        $this->db->or_group_start();
            $this->db->where('service_type', $service_type);
            $this->db->where('service_id IS NULL');
        $this->db->group_end();
        $this->db->or_group_start();
            $this->db->where('service_type IS NULL');
            $this->db->where('service_id IS NULL');
        $this->db->group_end();
        $this->db->order_by('service_id DESC, service_type DESC'); // Most specific first
        $this->db->limit(1);
        $rule = $this->db->get('company_pricing')->row();

        if (!$rule) return null;

        switch ($rule->pricing_type) {
            case 'FIXED':
                return (float)$rule->value;
            case 'PERCENT':
                // Percentage increase
                return $base_price * (1 + (float)$rule->value / 100);
            case 'DISCOUNT':
                // Percentage discount
                return $base_price * (1 - (float)$rule->value / 100);
            default:
                return null;
        }
    }

    /**
     * Get insurance coverage for service
     */
    public function get_insurance_coverage($insurance_id, $service_type, $service_id)
    {
        if (!$this->table_exists('insurance_coverage_rules')) {
            // Default 80% coverage if no rules defined
            return ['percent' => 80, 'max_amount' => null];
        }

        $this->db->where('insurance_id', (int)$insurance_id);
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->group_start();
            $this->db->where('service_type', $service_type);
            $this->db->where('service_id', (int)$service_id);
        $this->db->group_end();
        $this->db->or_group_start();
            $this->db->where('service_type', $service_type);
            $this->db->where('service_id IS NULL');
        $this->db->group_end();
        $this->db->or_group_start();
            $this->db->where('service_type IS NULL');
            $this->db->where('service_id IS NULL');
        $this->db->group_end();
        $this->db->order_by('service_id DESC, service_type DESC');
        $this->db->limit(1);
        $rule = $this->db->get('insurance_coverage_rules')->row();

        if ($rule) {
            return [
                'percent' => (float)$rule->coverage_percent,
                'max_amount' => $rule->max_amount ? (float)$rule->max_amount : null
            ];
        }

        return null;
    }

    /**
     * Get NHIS price for service
     */
    public function get_nhis_price($service_type, $service_id)
    {
        $service_id = (int)$service_id;
        if ($service_id <= 0) return null;

        switch (strtoupper($service_type)) {
            case 'LAB':
            case 'LABORATORY':
            case 'SONOGRAPHY':
            case 'RADIOLOGY':
            case 'PROCEDURE':
                if ($this->table_exists('bill_particular') && $this->column_exists('bill_particular', 'is_nhis_covered')) {
                    $row = $this->db->select('is_nhis_covered, nhis_charge_amount, charge_amount')
                        ->get_where('bill_particular', ['particular_id' => $service_id])
                        ->row();
                    if ($row && (int)$row->is_nhis_covered === 1) {
                        return (float)$row->nhis_charge_amount > 0 ? (float)$row->nhis_charge_amount : (float)$row->charge_amount;
                    }
                }
                break;
            case 'MEDICATION':
                if ($this->table_exists('medicine_drug_name') && $this->column_exists('medicine_drug_name', 'is_nhis_covered')) {
                    $row = $this->db->select('is_nhis_covered, nhis_price, nPrice')
                        ->get_where('medicine_drug_name', ['drug_id' => $service_id])
                        ->row();
                    if ($row && (int)$row->is_nhis_covered === 1) {
                        return (float)$row->nhis_price > 0 ? (float)$row->nhis_price : (float)$row->nPrice;
                    }
                }
                break;
        }
        return null;
    }

    /* ================================================================== */
    /*  PAYMENT GATE - Service Blocking                                    */
    /* ================================================================== */

    /**
     * Check if service can proceed (payment gate)
     */
    public function check_service_payment_gate($service_order_id)
    {
        $order = $this->get_service_order($service_order_id);
        if (!$order) {
            return ['allowed' => false, 'reason' => 'Service order not found'];
        }

        // Allowed payment statuses
        $allowed = ['PAID', 'COVERED', 'WAIVED', 'DEFERRED'];

        if (in_array($order->payment_status, $allowed)) {
            return ['allowed' => true, 'reason' => 'Payment verified'];
        }

        // Check coverage type
        if ($order->coverage_type === 'NHIS' && $order->covered_amount > 0) {
            return ['allowed' => true, 'reason' => 'NHIS covered'];
        }
        if ($order->coverage_type === 'INSURANCE' && $order->covered_amount > 0 && $order->patient_amount <= 0) {
            return ['allowed' => true, 'reason' => 'Insurance covered'];
        }
        if ($order->coverage_type === 'COMPANY') {
            return ['allowed' => true, 'reason' => 'Company account'];
        }

        // Check for approved exceptions
        if ($order->approval_id > 0) {
            $approval = $this->db->get_where('billing_approvals', ['id' => $order->approval_id, 'status' => 'APPROVED'])->row();
            if ($approval) {
                return ['allowed' => true, 'reason' => 'Admin approved: ' . $approval->approval_type];
            }
        }

        return [
            'allowed' => false,
            'reason' => 'Payment required before service. Contact cashier.',
            'amount_due' => $order->patient_amount
        ];
    }

    /**
     * Check payment gate for visit-level services
     */
    public function check_visit_payment_gate($visit_id, $service_type = null)
    {
        $orders = $this->get_visit_service_orders($visit_id, $service_type);
        $unpaid = [];

        foreach ($orders as $order) {
            $gate = $this->check_service_payment_gate($order->id);
            if (!$gate['allowed']) {
                $unpaid[] = [
                    'order_id' => $order->id,
                    'service_name' => $order->service_name,
                    'amount' => $order->patient_amount,
                    'reason' => $gate['reason']
                ];
            }
        }

        if (count($unpaid) > 0) {
            return [
                'allowed' => false,
                'reason' => count($unpaid) . ' service(s) require payment',
                'unpaid_services' => $unpaid
            ];
        }

        return ['allowed' => true, 'reason' => 'All services paid/covered'];
    }

    /* ================================================================== */
    /*  BILLING APPROVALS                                                  */
    /* ================================================================== */

    /**
     * Request billing approval (waive, defer, etc.)
     */
    public function request_billing_approval($data)
    {
        $this->ensure_service_billing_schema();

        $required = ['patient_no', 'approval_type', 'reason', 'requested_by'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim((string)$data[$field]) === '') {
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }

        $data['status'] = 'PENDING';
        $data['created_at'] = date('Y-m-d H:i:s');

        $this->db->insert('billing_approvals', $data);
        $id = $this->db->insert_id();

        return $id ? ['success' => true, 'approval_id' => $id] : ['success' => false, 'error' => 'Failed to create approval request'];
    }

    /**
     * Approve billing exception
     */
    public function approve_billing_exception($approval_id, $approved_by)
    {
        $approval = $this->db->get_where('billing_approvals', ['id' => (int)$approval_id])->row();
        if (!$approval) {
            return ['success' => false, 'error' => 'Approval request not found'];
        }

        $this->db->where('id', $approval_id);
        $this->db->update('billing_approvals', [
            'status' => 'APPROVED',
            'approved_by' => $approved_by,
            'approved_at' => date('Y-m-d H:i:s')
        ]);

        // Update service order if linked
        if ($approval->service_order_id) {
            $newStatus = 'UNPAID';
            switch ($approval->approval_type) {
                case 'WAIVE':
                    $newStatus = 'WAIVED';
                    break;
                case 'DEFER':
                    $newStatus = 'DEFERRED';
                    break;
            }
            $this->update_service_order_status($approval->service_order_id, null, $newStatus, null, $approved_by);
        }

        $this->log_billing_audit('APPROVAL_GRANTED', $approval->service_order_id, $approval->invoice_no,
            $approval->patient_no, null, json_encode(['type' => $approval->approval_type]),
            $approval->original_amount, $approval->approved_amount, $approval->reason, $approved_by);

        return ['success' => true];
    }

    /* ================================================================== */
    /*  AUDIT LOGGING                                                      */
    /* ================================================================== */

    /**
     * Log billing audit entry
     */
    public function log_billing_audit($action_type, $service_order_id, $invoice_no, $patient_no,
        $old_value, $new_value, $amount_before, $amount_after, $reason, $performed_by)
    {
        if (!$this->table_exists('billing_audit_log')) return false;
        
        // Defensive: Check if service_order_id column exists
        if (!$this->db->field_exists('service_order_id', 'billing_audit_log')) {
            log_message('error', 'billing_audit_log missing service_order_id column. Run ensure_service_billing_schema() to fix.');
            return false;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'CLI';

        return $this->db->insert('billing_audit_log', [
            'action_type' => $action_type,
            'service_order_id' => $service_order_id,
            'invoice_no' => $invoice_no,
            'patient_no' => $patient_no,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'amount_before' => $amount_before,
            'amount_after' => $amount_after,
            'reason' => $reason,
            'performed_by' => $performed_by,
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $ip
        ]);
    }

    /* ================================================================== */
    /*  COMPANY MANAGEMENT                                                 */
    /* ================================================================== */

    /**
     * Add corporate company
     */
    public function add_company($data)
    {
        $this->ensure_service_billing_schema();

        if (!isset($data['company_name']) || trim($data['company_name']) === '') {
            return ['success' => false, 'error' => 'Company name is required'];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('corporate_companies', $data);
        $id = $this->db->insert_id();

        return $id ? ['success' => true, 'company_id' => $id] : ['success' => false, 'error' => 'Failed to add company'];
    }

    /**
     * Set company pricing rule
     */
    public function set_company_pricing($company_id, $pricing_type, $value, $service_type = null, $service_id = null, $created_by = null)
    {
        $this->ensure_service_billing_schema();

        // Deactivate existing rule for same scope
        $this->db->where('company_id', (int)$company_id);
        if ($service_type) {
            $this->db->where('service_type', $service_type);
        } else {
            $this->db->where('service_type IS NULL');
        }
        if ($service_id) {
            $this->db->where('service_id', (int)$service_id);
        } else {
            $this->db->where('service_id IS NULL');
        }
        $this->db->update('company_pricing', ['is_active' => 0]);

        // Insert new rule
        $this->db->insert('company_pricing', [
            'company_id' => (int)$company_id,
            'service_type' => $service_type,
            'service_id' => $service_id ? (int)$service_id : null,
            'pricing_type' => $pricing_type,
            'value' => (float)$value,
            'is_active' => 1,
            'created_by' => $created_by,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->db->insert_id() > 0;
    }

    /**
     * Get all companies
     */
    public function get_companies()
    {
        if (!$this->table_exists('corporate_companies')) return [];
        return $this->db->get_where('corporate_companies', ['InActive' => 0, 'is_active' => 1])->result();
    }

    /* ================================================================== */
    /*  INSURANCE MANAGEMENT                                               */
    /* ================================================================== */

    /**
     * Add insurance company
     */
    public function add_insurance_company($data)
    {
        $this->ensure_service_billing_schema();

        if (!isset($data['insurance_name']) || trim($data['insurance_name']) === '') {
            return ['success' => false, 'error' => 'Insurance name is required'];
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $this->db->insert('insurance_companies', $data);
        $id = $this->db->insert_id();

        return $id ? ['success' => true, 'insurance_id' => $id] : ['success' => false, 'error' => 'Failed to add insurance company'];
    }

    /**
     * Get all insurance companies
     */
    public function get_insurance_companies()
    {
        if (!$this->table_exists('insurance_companies')) return [];
        return $this->db->get_where('insurance_companies', ['InActive' => 0, 'is_active' => 1])->result();
    }

    /**
     * Set insurance coverage rule
     */
    public function set_insurance_coverage($insurance_id, $coverage_percent, $service_type = null, $service_id = null, $max_amount = null)
    {
        $this->ensure_service_billing_schema();

        // Deactivate existing rule for same scope
        $this->db->where('insurance_id', (int)$insurance_id);
        if ($service_type) {
            $this->db->where('service_type', $service_type);
        } else {
            $this->db->where('service_type IS NULL');
        }
        if ($service_id) {
            $this->db->where('service_id', (int)$service_id);
        } else {
            $this->db->where('service_id IS NULL');
        }
        $this->db->update('insurance_coverage_rules', ['is_active' => 0]);

        // Insert new rule
        $this->db->insert('insurance_coverage_rules', [
            'insurance_id' => (int)$insurance_id,
            'service_type' => $service_type,
            'service_id' => $service_id ? (int)$service_id : null,
            'coverage_percent' => (float)$coverage_percent,
            'max_amount' => $max_amount ? (float)$max_amount : null,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return $this->db->insert_id() > 0;
    }

    /* ================================================================== */
    /*  REPORTING                                                          */
    /* ================================================================== */

    /**
     * Get billing summary by coverage type
     */
    public function get_billing_summary($date_from = null, $date_to = null)
    {
        if (!$this->table_exists('service_orders')) return [];

        $this->db->select('coverage_type, COUNT(*) as count, SUM(final_price) as total, SUM(patient_amount) as patient_total, SUM(covered_amount) as covered_total');
        $this->db->from('service_orders');
        $this->db->where('InActive', 0);
        if ($date_from) $this->db->where('created_at >=', $date_from);
        if ($date_to) $this->db->where('created_at <=', $date_to . ' 23:59:59');
        $this->db->group_by('coverage_type');
        return $this->db->get()->result();
    }

    /**
     * Get outstanding bills
     */
    public function get_outstanding_bills($limit = 100)
    {
        if (!$this->table_exists('service_orders')) return [];

        $this->db->select('so.*, p.firstname, p.lastname');
        $this->db->from('service_orders so');
        $this->db->join('patient_personal_info p', 'p.patient_no = so.patient_no', 'left');
        $this->db->where('so.payment_status', 'UNPAID');
        $this->db->where('so.patient_amount >', 0);
        $this->db->where('so.InActive', 0);
        $this->db->order_by('so.created_at', 'ASC');
        $this->db->limit($limit);
        return $this->db->get()->result();
    }
}
