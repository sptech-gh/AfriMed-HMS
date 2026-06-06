<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Schema Migration Model
 * Ensures all critical tables exist for NHIS Claim-IT integration
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class Schema_migration_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }
    
    /**
     * Run all migrations
     */
    public function run_all_migrations()
    {
        $results = [];
        
        $results['doctor'] = $this->ensure_doctor_table();
        $results['invoice'] = $this->ensure_invoice_tables();
        $results['payment_transactions'] = $this->ensure_payment_transactions();
        $results['medicine_master'] = $this->ensure_medicine_master();
        $results['radiology'] = $this->ensure_radiology_tables();
        $results['nhis_enhanced'] = $this->ensure_nhis_enhanced_mapping();
        $results['nhis_claimit_readiness'] = $this->ensure_nhis_claimit_readiness();
        
        return $results;
    }
    
    /**
     * Check if table exists
     */
    private function table_exists($table)
    {
        return $this->db->table_exists($table);
    }
    
    /**
     * Check if column exists
     */
    private function column_exists($table, $column)
    {
        if (!$this->table_exists($table)) return false;
        $fields = $this->db->list_fields($table);
        return in_array($column, $fields);
    }
    
    /**
     * Ensure doctor table exists
     */
    public function ensure_doctor_table()
    {
        if (!$this->table_exists('doctor')) {
            $this->db->query("CREATE TABLE IF NOT EXISTS `doctor` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `doctor_name` VARCHAR(255) NOT NULL,
                `department_id` INT,
                `specialization` VARCHAR(255),
                `license_number` VARCHAR(100),
                `phone` VARCHAR(50),
                `email` VARCHAR(255),
                `nhis_provider_id` VARCHAR(50),
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0,
                KEY `idx_department` (`department_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            
            return 'CREATED';
        }
        
        // Add NHIS provider ID if missing
        if (!$this->column_exists('doctor', 'nhis_provider_id')) {
            $this->db->query("ALTER TABLE `doctor` ADD COLUMN `nhis_provider_id` VARCHAR(50) AFTER `email`");
        }
        
        return 'EXISTS';
    }
    
    /**
     * Ensure invoice tables exist (wrapper for iop_billing)
     * Creates views for compatibility if needed
     */
    public function ensure_invoice_tables()
    {
        // The system uses iop_billing as the invoice table
        // Ensure NHIS columns exist
        if ($this->table_exists('iop_billing')) {
            if (!$this->column_exists('iop_billing', 'nhis_covered_amount')) {
                $this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `nhis_covered_amount` DECIMAL(18,2) DEFAULT 0");
            }
            if (!$this->column_exists('iop_billing', 'patient_payable_amount')) {
                $this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `patient_payable_amount` DECIMAL(18,2) DEFAULT 0");
            }
            if (!$this->column_exists('iop_billing', 'billing_type')) {
                $this->db->query("ALTER TABLE `iop_billing` ADD COLUMN `billing_type` VARCHAR(20) DEFAULT 'OPD'");
            }
        }
        
        // Ensure iop_billing_t has NHIS columns
        if ($this->table_exists('iop_billing_t')) {
            if (!$this->column_exists('iop_billing_t', 'nhis_code')) {
                $this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `nhis_code` VARCHAR(50)");
            }
            if (!$this->column_exists('iop_billing_t', 'nhis_covered')) {
                $this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `nhis_covered` DECIMAL(18,2) DEFAULT 0");
            }
            if (!$this->column_exists('iop_billing_t', 'service_type')) {
                $this->db->query("ALTER TABLE `iop_billing_t` ADD COLUMN `service_type` VARCHAR(50)");
            }
        }
        
        // Create invoice view for compatibility - iop_billing uses bill_id not id
        $this->db->query("CREATE OR REPLACE VIEW `invoice` AS
            SELECT 
                bill_id AS id,
                invoice_no,
                patient_no AS patient_id,
                iop_id AS visit_id,
                COALESCE(billing_type, 'OPD') AS billing_type,
                total_amount,
                COALESCE(nhis_covered_amount, 0) AS nhis_covered_amount,
                COALESCE(patient_payable_amount, total_amount) AS patient_payable,
                CASE 
                    WHEN payment_type = 'PAID' THEN 'paid'
                    WHEN payment_type = 'PARTIAL' THEN 'partial'
                    ELSE 'pending'
                END AS status,
                dDate AS created_at,
                created_by
            FROM iop_billing
            WHERE InActive = 0");
        
        // Create invoice_item view for compatibility - iop_billing_t has 'id' column
        $this->db->query("CREATE OR REPLACE VIEW `invoice_item` AS
            SELECT 
                COALESCE(id, 0) AS id,
                invoice_no AS invoice_id,
                bill_name AS service_name,
                COALESCE(service_type, 'other') AS service_type,
                qty AS quantity,
                rate AS unit_price,
                COALESCE(nhis_covered, 0) AS nhis_covered,
                COALESCE(nhis_code, '') AS nhis_code,
                amount AS total
            FROM iop_billing_t
            WHERE InActive = 0");
        
        return 'ENHANCED';
    }
    
    /**
     * Ensure payment_transactions table exists
     */
    public function ensure_payment_transactions()
    {
        if (!$this->table_exists('payment_transactions')) {
            $this->db->query("CREATE TABLE `payment_transactions` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `invoice_no` VARCHAR(50) NOT NULL,
                `patient_no` VARCHAR(25),
                `payment_method` ENUM('cash','mobile','bank','card','insurance','nhis') DEFAULT 'cash',
                `amount` DECIMAL(18,2) NOT NULL,
                `reference` VARCHAR(100),
                `paid_by` VARCHAR(255),
                `payment_date` DATETIME NOT NULL,
                `receipt_no` VARCHAR(50),
                `user_id` INT,
                `notes` TEXT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0,
                KEY `idx_invoice` (`invoice_no`),
                KEY `idx_patient` (`patient_no`),
                KEY `idx_date` (`payment_date`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            
            // Migrate existing payments from iop_receipt
            if ($this->table_exists('iop_receipt')) {
                $this->db->query("INSERT INTO `payment_transactions` 
                    (invoice_no, patient_no, payment_method, amount, reference, payment_date, receipt_no, user_id, InActive)
                    SELECT invoice_no, patient_no, 'cash', amountPaid, receiptNo, dDate, receiptNo, user_id, InActive
                    FROM iop_receipt
                    ON DUPLICATE KEY UPDATE amount = VALUES(amount)");
            }
            
            return 'CREATED';
        }
        return 'EXISTS';
    }
    
    /**
     * Ensure medicine_master table exists
     */
    public function ensure_medicine_master()
    {
        // System uses medicine_drug_name - create view for compatibility
        if ($this->table_exists('medicine_drug_name')) {
            // Add NHIS columns if missing
            if (!$this->column_exists('medicine_drug_name', 'nhis_code')) {
                $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_code` VARCHAR(50)");
            }
            if (!$this->column_exists('medicine_drug_name', 'is_nhis_covered')) {
                $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `is_nhis_covered` TINYINT(1) DEFAULT 0");
            }
            if (!$this->column_exists('medicine_drug_name', 'nhis_price')) {
                $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_price` DECIMAL(18,2) DEFAULT 0");
            }
            
            // Create medicine_master view - medicine_drug_name uses drug_id not id
            $this->db->query("CREATE OR REPLACE VIEW `medicine_master` AS
                SELECT 
                    drug_id AS id,
                    drug_name AS medicine_name,
                    generic_name,
                    COALESCE(nhis_code, '') AS nhis_code,
                    med_cat_id AS category,
                    nPrice AS price,
                    COALESCE(nStock, 0) AS stock,
                    CASE WHEN InActive = 0 THEN 'active' ELSE 'inactive' END AS status
                FROM medicine_drug_name");
            
            return 'VIEW_CREATED';
        }
        
        // Create actual table if medicine_drug_name doesn't exist
        if (!$this->table_exists('medicine_master')) {
            $this->db->query("CREATE TABLE `medicine_master` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `medicine_name` VARCHAR(255) NOT NULL,
                `generic_name` VARCHAR(255),
                `nhis_code` VARCHAR(50),
                `category` INT,
                `price` DECIMAL(18,2) DEFAULT 0,
                `nhis_price` DECIMAL(18,2) DEFAULT 0,
                `stock` INT DEFAULT 0,
                `is_nhis_covered` TINYINT(1) DEFAULT 0,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            return 'CREATED';
        }
        
        return 'EXISTS';
    }
    
    /**
     * Ensure radiology tables exist
     */
    public function ensure_radiology_tables()
    {
        if (!$this->table_exists('radiology_test_master')) {
            $this->db->query("CREATE TABLE `radiology_test_master` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `test_name` VARCHAR(255) NOT NULL,
                `test_code` VARCHAR(50),
                `nhis_code` VARCHAR(50),
                `price` DECIMAL(18,2) DEFAULT 0,
                `nhis_price` DECIMAL(18,2) DEFAULT 0,
                `department` VARCHAR(100) DEFAULT 'Radiology',
                `category` VARCHAR(100),
                `is_nhis_covered` TINYINT(1) DEFAULT 0,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0,
                KEY `idx_nhis_code` (`nhis_code`),
                KEY `idx_status` (`status`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            
            // Seed common radiology tests
            $tests = [
                ['X-Ray Chest PA', 'XRAY-001', 'RAD001', 50.00, 35.00, 1],
                ['X-Ray Abdomen', 'XRAY-002', 'RAD002', 60.00, 40.00, 1],
                ['Ultrasound Abdomen', 'USS-001', 'RAD003', 120.00, 80.00, 1],
                ['Ultrasound Pelvis', 'USS-002', 'RAD004', 100.00, 70.00, 0],
                ['CT Scan Head', 'CT-001', 'RAD005', 500.00, 350.00, 1],
                ['CT Scan Abdomen', 'CT-002', 'RAD006', 600.00, 400.00, 1],
                ['MRI Brain', 'MRI-001', 'RAD007', 1200.00, 800.00, 0],
                ['ECG', 'ECG-001', 'RAD008', 30.00, 20.00, 1],
                ['Echo', 'ECHO-001', 'RAD009', 200.00, 150.00, 1]
            ];
            
            foreach ($tests as $t) {
                $this->db->insert('radiology_test_master', [
                    'test_name' => $t[0],
                    'test_code' => $t[1],
                    'nhis_code' => $t[2],
                    'price' => $t[3],
                    'nhis_price' => $t[4],
                    'is_nhis_covered' => $t[5]
                ]);
            }
            
            return 'CREATED';
        }
        
        // Add NHIS columns if missing
        if (!$this->column_exists('radiology_test_master', 'nhis_code')) {
            $this->db->query("ALTER TABLE `radiology_test_master` ADD COLUMN `nhis_code` VARCHAR(50)");
        }
        if (!$this->column_exists('radiology_test_master', 'is_nhis_covered')) {
            $this->db->query("ALTER TABLE `radiology_test_master` ADD COLUMN `is_nhis_covered` TINYINT(1) DEFAULT 0");
        }
        
        return 'EXISTS';
    }
    
    /**
     * Ensure NHIS service mapping is complete
     */
    public function ensure_nhis_enhanced_mapping()
    {
        if (!$this->table_exists('nhis_service_mapping')) {
            return 'MISSING_BASE_TABLE';
        }
        
        // Add additional columns if needed
        if (!$this->column_exists('nhis_service_mapping', 'department')) {
            $this->db->query("ALTER TABLE `nhis_service_mapping` ADD COLUMN `department` VARCHAR(100)");
        }
        if (!$this->column_exists('nhis_service_mapping', 'requires_preauth')) {
            $this->db->query("ALTER TABLE `nhis_service_mapping` ADD COLUMN `requires_preauth` TINYINT(1) DEFAULT 0");
        }
        
        // Add more comprehensive mappings
        $mappings = [
            // Consultation
            ['OPD Consultation', 'CONSULTATION', 'OPD001', 'OPD Consultation', 'CONSULTATION', 1, 100, 15.00],
            ['Specialist Consultation', 'CONSULTATION', 'OPD002', 'Specialist Consultation', 'CONSULTATION', 1, 80, 25.00],
            
            // Laboratory
            ['Full Blood Count', 'LABORATORY', 'LAB001', 'FBC', 'LABORATORY', 1, 100, 20.00],
            ['Malaria Parasite', 'LABORATORY', 'LAB002', 'MP', 'LABORATORY', 1, 100, 8.00],
            ['Urinalysis', 'LABORATORY', 'LAB003', 'Urine RE', 'LABORATORY', 1, 100, 10.00],
            ['Blood Sugar', 'LABORATORY', 'LAB004', 'FBS/RBS', 'LABORATORY', 1, 100, 12.00],
            ['Liver Function Test', 'LABORATORY', 'LAB005', 'LFT', 'LABORATORY', 1, 80, 45.00],
            ['Renal Function Test', 'LABORATORY', 'LAB006', 'RFT', 'LABORATORY', 1, 80, 40.00],
            ['Lipid Profile', 'LABORATORY', 'LAB007', 'Lipid Panel', 'LABORATORY', 1, 70, 50.00],
            ['HIV Test', 'LABORATORY', 'LAB008', 'HIV Screening', 'LABORATORY', 1, 100, 15.00],
            ['Hepatitis B', 'LABORATORY', 'LAB009', 'HBsAg', 'LABORATORY', 1, 100, 20.00],
            ['Widal Test', 'LABORATORY', 'LAB010', 'Widal', 'LABORATORY', 1, 100, 15.00],
            
            // Radiology
            ['X-Ray Chest', 'RADIOLOGY', 'RAD001', 'CXR', 'RADIOLOGY', 1, 100, 35.00],
            ['Ultrasound Abdomen', 'RADIOLOGY', 'RAD002', 'USS Abdomen', 'RADIOLOGY', 1, 80, 80.00],
            ['CT Scan', 'RADIOLOGY', 'RAD003', 'CT', 'RADIOLOGY', 0, 0, 350.00],
            
            // Pharmacy
            ['Paracetamol 500mg', 'PHARMACY', 'DRUG001', 'Paracetamol', 'PHARMACY', 1, 100, 0.50],
            ['Amoxicillin 500mg', 'PHARMACY', 'DRUG002', 'Amoxicillin', 'PHARMACY', 1, 100, 1.00],
            ['Metformin 500mg', 'PHARMACY', 'DRUG003', 'Metformin', 'PHARMACY', 1, 100, 0.80],
            ['Amlodipine 5mg', 'PHARMACY', 'DRUG004', 'Amlodipine', 'PHARMACY', 1, 100, 0.60],
            ['Omeprazole 20mg', 'PHARMACY', 'DRUG005', 'Omeprazole', 'PHARMACY', 1, 100, 0.70],
            ['Artemether-Lumefantrine', 'PHARMACY', 'DRUG006', 'ACT', 'PHARMACY', 1, 100, 5.00],
            ['Ciprofloxacin 500mg', 'PHARMACY', 'DRUG007', 'Ciprofloxacin', 'PHARMACY', 1, 80, 1.50],
            
            // Procedures
            ['Wound Dressing', 'PROCEDURE', 'PROC001', 'Dressing', 'PROCEDURES', 1, 100, 10.00],
            ['Injection Administration', 'PROCEDURE', 'PROC002', 'Injection', 'PROCEDURES', 1, 100, 5.00],
            ['Minor Surgery', 'PROCEDURE', 'PROC003', 'Minor Surgery', 'PROCEDURES', 1, 70, 100.00],
            ['Suturing', 'PROCEDURE', 'PROC004', 'Suturing', 'PROCEDURES', 1, 100, 30.00]
        ];
        
        foreach ($mappings as $m) {
            // Check if mapping exists
            $exists = $this->db->get_where('nhis_service_mapping', ['nhis_code' => $m[2]])->row();
            if (!$exists) {
                $this->db->insert('nhis_service_mapping', [
                    'hms_service_name' => $m[0],
                    'hms_service_type' => $m[1],
                    'nhis_code' => $m[2],
                    'nhis_name' => $m[3],
                    'category' => $m[4],
                    'is_covered' => $m[5],
                    'coverage_percent' => $m[6],
                    'tariff_amount' => $m[7]
                ]);
            }
        }
        
        return 'ENHANCED';
    }
    
    // =========================================================================
    // NHIS CLAIM-IT READINESS MIGRATION
    // =========================================================================

    /**
     * Ensure all NHIS Claim-It integration prerequisites are met.
     * Idempotent — safe to call repeatedly.
     */
    public function ensure_nhis_claimit_readiness()
    {
        $fixes = [];
        $fixes[] = $this->_create_gdrg_codes_table();
        $fixes[] = $this->_add_sonography_nhis_columns();
        $fixes[] = $this->_add_radiology_nhis_columns();
        $fixes[] = $this->_auto_map_drugs_to_nhis();
        $fixes[] = $this->_populate_nhis_coverage();
        $fixes[] = $this->_map_bill_particular_nhis();
        $fixes[] = $this->_expand_icd10_codes();
        $fixes[] = $this->_map_diagnoses_icd10();
        return implode('; ', array_filter($fixes));
    }

    /**
     * Create G-DRG codes table with common Ghana procedure codes
     */
    private function _create_gdrg_codes_table()
    {
        if ($this->table_exists('nhis_gdrg_codes')) return null;

        $this->db->query("CREATE TABLE `nhis_gdrg_codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `gdrg_code` VARCHAR(20) NOT NULL,
            `description` VARCHAR(500) NOT NULL,
            `category` VARCHAR(100) NOT NULL,
            `tariff` DECIMAL(15,2) DEFAULT 0,
            `level` VARCHAR(50) DEFAULT 'District',
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `gdrg_code` (`gdrg_code`),
            KEY `category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed common Ghana G-DRG codes
        $codes = array(
            array('V10A','Normal Delivery','OBSTETRICS',120.00,'District'),
            array('V10B','Caesarean Section','OBSTETRICS',450.00,'District'),
            array('V20A','Appendectomy','GENERAL SURGERY',350.00,'District'),
            array('V20B','Hernia Repair','GENERAL SURGERY',300.00,'District'),
            array('V20C','Incision and Drainage','GENERAL SURGERY',80.00,'District'),
            array('V30A','Wound Suturing (Minor)','PROCEDURES',40.00,'District'),
            array('V30B','Wound Suturing (Major)','PROCEDURES',80.00,'District'),
            array('V30C','Wound Dressing','PROCEDURES',15.00,'District'),
            array('V30D','Catheterization','PROCEDURES',30.00,'District'),
            array('V30E','Nasogastric Tube Insertion','PROCEDURES',25.00,'District'),
            array('V40A','Male Circumcision','PROCEDURES',150.00,'District'),
            array('V50A','Fracture Management (Closed)','ORTHOPAEDICS',200.00,'District'),
            array('V50B','Fracture Management (Open)','ORTHOPAEDICS',400.00,'District'),
            array('V60A','Dental Extraction (Simple)','DENTAL',50.00,'District'),
            array('V60B','Dental Extraction (Surgical)','DENTAL',120.00,'District'),
            array('V70A','Eye Examination','OPHTHALMOLOGY',25.00,'District'),
            array('V80A','ENT Examination','ENT',25.00,'District'),
            array('V90A','Physiotherapy Session','PHYSIOTHERAPY',20.00,'District'),
            array('V99A','OPD Consultation','CONSULTATION',15.00,'District'),
            array('V99B','Specialist Consultation','CONSULTATION',25.00,'Regional'),
            array('V99C','Emergency Consultation','CONSULTATION',30.00,'District')
        );
        foreach ($codes as $c) {
            $this->db->insert('nhis_gdrg_codes', array(
                'gdrg_code' => $c[0], 'description' => $c[1],
                'category' => $c[2], 'tariff' => $c[3], 'level' => $c[4]
            ));
        }
        return 'G-DRG table created with ' . count($codes) . ' codes';
    }

    /**
     * Add NHIS columns to sonography tables
     */
    private function _add_sonography_nhis_columns()
    {
        $added = 0;
        // scan_master — the master list of scan types
        if ($this->table_exists('scan_master')) {
            if (!$this->column_exists('scan_master', 'nhis_code')) {
                $this->db->query("ALTER TABLE `scan_master` ADD COLUMN `nhis_code` VARCHAR(50) DEFAULT NULL");
                $added++;
            }
            if (!$this->column_exists('scan_master', 'nhis_price')) {
                $this->db->query("ALTER TABLE `scan_master` ADD COLUMN `nhis_price` DECIMAL(18,2) DEFAULT NULL");
                $added++;
            }
            if (!$this->column_exists('scan_master', 'is_nhis_covered')) {
                $this->db->query("ALTER TABLE `scan_master` ADD COLUMN `is_nhis_covered` TINYINT(1) DEFAULT 0");
                $added++;
            }
        }
        // iop_sonography_charge — per-visit charges (referenced by nhis_model claim generation)
        if ($this->table_exists('iop_sonography_charge')) {
            if (!$this->column_exists('iop_sonography_charge', 'nhis_flag')) {
                $this->db->query("ALTER TABLE `iop_sonography_charge` ADD COLUMN `nhis_flag` TINYINT(1) DEFAULT 0");
                $added++;
            }
            if (!$this->column_exists('iop_sonography_charge', 'nhis_price')) {
                $this->db->query("ALTER TABLE `iop_sonography_charge` ADD COLUMN `nhis_price` DECIMAL(18,2) DEFAULT NULL");
                $added++;
            }
            if (!$this->column_exists('iop_sonography_charge', 'nhis_code')) {
                $this->db->query("ALTER TABLE `iop_sonography_charge` ADD COLUMN `nhis_code` VARCHAR(50) DEFAULT NULL");
                $added++;
            }
        }
        return $added > 0 ? "Sonography: {$added} NHIS columns added" : null;
    }

    /**
     * Add NHIS columns to radiology tables
     */
    private function _add_radiology_nhis_columns()
    {
        $added = 0;
        if ($this->table_exists('radiology_test_master')) {
            if (!$this->column_exists('radiology_test_master', 'nhis_code')) {
                $this->db->query("ALTER TABLE `radiology_test_master` ADD COLUMN `nhis_code` VARCHAR(50) DEFAULT NULL");
                $added++;
            }
            if (!$this->column_exists('radiology_test_master', 'nhis_price')) {
                $this->db->query("ALTER TABLE `radiology_test_master` ADD COLUMN `nhis_price` DECIMAL(18,2) DEFAULT NULL");
                $added++;
            }
            if (!$this->column_exists('radiology_test_master', 'is_nhis_covered')) {
                $this->db->query("ALTER TABLE `radiology_test_master` ADD COLUMN `is_nhis_covered` TINYINT(1) DEFAULT 0");
                $added++;
            }
        }
        if ($this->table_exists('radiology_orders')) {
            if (!$this->column_exists('radiology_orders', 'nhis_code')) {
                $this->db->query("ALTER TABLE `radiology_orders` ADD COLUMN `nhis_code` VARCHAR(50) DEFAULT NULL");
                $added++;
            }
            if (!$this->column_exists('radiology_orders', 'nhis_price')) {
                $this->db->query("ALTER TABLE `radiology_orders` ADD COLUMN `nhis_price` DECIMAL(18,2) DEFAULT NULL");
                $added++;
            }
        }
        return $added > 0 ? "Radiology: {$added} NHIS columns added" : null;
    }

    /**
     * Auto-map drugs in medicine_drug_name to NHIS tariffs by generic name matching
     */
    private function _auto_map_drugs_to_nhis()
    {
        if (!$this->table_exists('medicine_drug_name') || !$this->table_exists('nhis_drug_tariffs')) return null;
        if (!$this->column_exists('medicine_drug_name', 'nhis_drug_code')) return null;

        // Only map drugs that have no mapping yet
        $unmapped = $this->db->query("
            SELECT drug_id, drug_name, generic_name, dosage_form, strength
            FROM medicine_drug_name
            WHERE InActive = 0
              AND (nhis_drug_code IS NULL OR nhis_drug_code = '')
        ")->result();

        if (empty($unmapped)) return null;

        $mapped = 0;
        foreach ($unmapped as $drug) {
            // Try exact generic_name match first
            $tariff = null;
            if (!empty($drug->generic_name)) {
                $tariff = $this->db->query("
                    SELECT tariff_id, nhis_code, drug_name, unit_price
                    FROM nhis_drug_tariffs
                    WHERE is_active = 1
                      AND LOWER(generic_name) = LOWER(?)
                    ORDER BY
                      CASE WHEN LOWER(dosage_form) = LOWER(?) THEN 0 ELSE 1 END,
                      tariff_id ASC
                    LIMIT 1
                ", array($drug->generic_name, $drug->dosage_form ?: ''))->row();
            }
            // Fallback: fuzzy match on drug_name
            if (!$tariff && !empty($drug->drug_name)) {
                $tariff = $this->db->query("
                    SELECT tariff_id, nhis_code, drug_name, unit_price
                    FROM nhis_drug_tariffs
                    WHERE is_active = 1
                      AND LOWER(drug_name) LIKE CONCAT('%', LOWER(?), '%')
                    LIMIT 1
                ", array($drug->generic_name ?: $drug->drug_name))->row();
            }
            if ($tariff) {
                $this->db->where('drug_id', $drug->drug_id);
                $this->db->update('medicine_drug_name', array(
                    'nhis_drug_code'  => $tariff->nhis_code,
                    'nhis_drug_name'  => $tariff->drug_name,
                    'nhis_tariff_id'  => $tariff->tariff_id,
                    'nhis_unit_tariff'=> $tariff->unit_price,
                    'is_nhis_covered' => 1
                ));
                // Also populate drug_tariff_mapping if empty
                if ($this->table_exists('drug_tariff_mapping')) {
                    $exists = $this->db->get_where('drug_tariff_mapping', array('drug_id' => $drug->drug_id))->row();
                    if (!$exists) {
                        $this->db->insert('drug_tariff_mapping', array(
                            'drug_id'        => $drug->drug_id,
                            'nhis_tariff_id' => $tariff->tariff_id,
                            'nhis_drug_code' => $tariff->nhis_code,
                            'nhis_drug_name' => $tariff->drug_name,
                            'unit_tariff'    => $tariff->unit_price,
                            'dosage_form'    => $drug->dosage_form,
                            'strength'       => $drug->strength,
                            'is_active'      => 1
                        ));
                    }
                }
                $mapped++;
            }
        }
        return $mapped > 0 ? "Drugs: {$mapped}/" . count($unmapped) . " auto-mapped to NHIS tariffs" : null;
    }

    /**
     * Populate nhis_coverage rules from nhis_service_mapping + config defaults
     */
    private function _populate_nhis_coverage()
    {
        if (!$this->table_exists('nhis_coverage') || !$this->table_exists('nhis_service_mapping')) return null;

        $existing = (int)$this->db->count_all('nhis_coverage');
        if ($existing > 0) return null; // Already populated

        $mappings = $this->db->get_where('nhis_service_mapping', array('is_active' => 1, 'is_covered' => 1))->result();
        $inserted = 0;
        foreach ($mappings as $m) {
            $this->db->insert('nhis_coverage', array(
                'item_type'           => strtolower($m->hms_service_type ?: 'service'),
                'item_id'             => (int)($m->hms_service_id ?: 0),
                'item_name'           => $m->hms_service_name,
                'nhis_code'           => $m->nhis_code,
                'coverage_percentage' => $m->coverage_percent ?: 100,
                'requires_preauth'    => (int)($m->requires_preauth ?: 0),
                'formulary_status'    => 'approved',
                'is_active'           => 1,
                'created_at'          => date('Y-m-d H:i:s')
            ));
            $inserted++;
        }
        return $inserted > 0 ? "Coverage: {$inserted} rules populated from service mapping" : null;
    }

    /**
     * Map bill_particular nhis_code from nhis_service_mapping by name match
     */
    private function _map_bill_particular_nhis()
    {
        if (!$this->table_exists('bill_particular') || !$this->table_exists('nhis_service_mapping')) return null;
        if (!$this->column_exists('bill_particular', 'nhis_code')) return null;

        $unmapped = $this->db->query("
            SELECT particular_id, particular_name
            FROM bill_particular
            WHERE (nhis_code IS NULL OR nhis_code = '')
        ")->result();

        if (empty($unmapped)) return null;

        $mapped = 0;
        foreach ($unmapped as $bp) {
            $match = $this->db->query("
                SELECT nhis_code, tariff_amount, coverage_percent
                FROM nhis_service_mapping
                WHERE is_active = 1
                  AND LOWER(hms_service_name) = LOWER(?)
                LIMIT 1
            ", array($bp->particular_name))->row();

            if (!$match) {
                // Fuzzy match
                $match = $this->db->query("
                    SELECT nhis_code, tariff_amount, coverage_percent
                    FROM nhis_service_mapping
                    WHERE is_active = 1
                      AND LOWER(hms_service_name) LIKE CONCAT('%', LOWER(?), '%')
                    LIMIT 1
                ", array($bp->particular_name))->row();
            }

            if ($match) {
                $this->db->where('particular_id', $bp->particular_id);
                $this->db->update('bill_particular', array(
                    'nhis_code'    => $match->nhis_code,
                    'nhis_price'   => $match->tariff_amount,
                    'is_nhis_covered' => 1
                ));
                $mapped++;
            }
        }
        return $mapped > 0 ? "Bill particulars: {$mapped}/" . count($unmapped) . " mapped to NHIS codes" : null;
    }

    /**
     * Expand ICD-10 codes table with comprehensive Ghana-relevant codes
     */
    private function _expand_icd10_codes()
    {
        if (!$this->table_exists('icd10_codes')) return null;

        $current = (int)$this->db->count_all('icd10_codes');
        if ($current >= 100) return null; // Already expanded

        $codes = array(
            // Top 50 Ghana diagnoses not in seed
            array('A01.0','Typhoid fever','Infectious'),
            array('A02','Salmonellosis','Infectious'),
            array('A06','Amoebiasis','Infectious'),
            array('A09','Infectious gastroenteritis and colitis','Infectious'),
            array('A15','Respiratory tuberculosis','Infectious'),
            array('B50','Plasmodium falciparum malaria','Infectious'),
            array('B51','Plasmodium vivax malaria','Infectious'),
            array('B52','Plasmodium malariae malaria','Infectious'),
            array('J00','Acute nasopharyngitis','Respiratory'),
            array('J02','Acute pharyngitis','Respiratory'),
            array('J03','Acute tonsillitis','Respiratory'),
            array('J06.9','Acute upper respiratory infection, unspecified','Respiratory'),
            array('J18.9','Pneumonia, unspecified organism','Respiratory'),
            array('J20','Acute bronchitis','Respiratory'),
            array('J45','Asthma','Respiratory'),
            array('J46','Status asthmaticus','Respiratory'),
            array('I11','Hypertensive heart disease','Cardiovascular'),
            array('I20','Angina pectoris','Cardiovascular'),
            array('I21','Acute myocardial infarction','Cardiovascular'),
            array('I50','Heart failure','Cardiovascular'),
            array('I64','Stroke, not specified','Cardiovascular'),
            array('E10','Type 1 diabetes mellitus','Endocrine'),
            array('E03','Hypothyroidism','Endocrine'),
            array('E05','Thyrotoxicosis','Endocrine'),
            array('E66','Obesity','Endocrine'),
            array('K25','Gastric ulcer','Gastrointestinal'),
            array('K26','Duodenal ulcer','Gastrointestinal'),
            array('K35','Acute appendicitis','Gastrointestinal'),
            array('K40','Inguinal hernia','Gastrointestinal'),
            array('K80','Cholelithiasis','Gastrointestinal'),
            array('K92.0','Haematemesis','Gastrointestinal'),
            array('N10','Acute tubulo-interstitial nephritis','Genitourinary'),
            array('N18','Chronic kidney disease','Genitourinary'),
            array('N20','Calculus of kidney and ureter','Genitourinary'),
            array('N30','Cystitis','Genitourinary'),
            array('N39.0','Urinary tract infection, site not specified','Genitourinary'),
            array('M15','Polyarthrosis','Musculoskeletal'),
            array('M25','Other joint disorders','Musculoskeletal'),
            array('M54.5','Low back pain','Musculoskeletal'),
            array('M79','Soft tissue disorders','Musculoskeletal'),
            array('L02','Cutaneous abscess','Skin'),
            array('L03','Cellulitis','Skin'),
            array('L20','Atopic dermatitis','Skin'),
            array('L50','Urticaria','Skin'),
            array('O80','Single spontaneous delivery','Pregnancy'),
            array('O82','Caesarean delivery','Pregnancy'),
            array('O20','Haemorrhage in early pregnancy','Pregnancy'),
            array('O21','Hyperemesis gravidarum','Pregnancy'),
            array('O46','Antepartum haemorrhage','Pregnancy'),
            array('S00','Superficial injury of head','Injuries'),
            array('S52','Fracture of forearm','Injuries'),
            array('S82','Fracture of lower leg','Injuries'),
            array('T14','Injury of unspecified body region','Injuries'),
            array('T78.4','Allergy, unspecified','Allergic'),
            array('R50','Fever, unspecified','Symptoms'),
            array('R10','Abdominal pain','Symptoms'),
            array('R11','Nausea and vomiting','Symptoms'),
            array('R51','Headache','Symptoms'),
            array('R05','Cough','Symptoms'),
            array('F32','Depressive episode','Mental'),
            array('F41','Anxiety disorders','Mental'),
            array('G40','Epilepsy','Neurological'),
            array('G43','Migraine','Neurological'),
            array('H10','Conjunctivitis','Ophthalmology'),
            array('H66','Otitis media','ENT'),
            array('J01','Acute sinusitis','ENT'),
            array('B35','Dermatophytosis (ringworm)','Infectious'),
            array('B37','Candidiasis','Infectious'),
            array('B77','Ascariasis','Infectious'),
            array('D50','Iron deficiency anaemia','Blood'),
            array('D64','Other anaemias','Blood'),
            array('P59','Neonatal jaundice','Perinatal'),
            array('P22','Respiratory distress of newborn','Perinatal'),
            array('A90','Dengue fever','Infectious'),
            array('B20','HIV disease','Infectious'),
            array('C50','Malignant neoplasm of breast','Neoplasms'),
            array('C53','Malignant neoplasm of cervix uteri','Neoplasms'),
            array('N40','Benign prostatic hyperplasia','Genitourinary'),
            array('N80','Endometriosis','Genitourinary'),
            array('K21','Gastro-oesophageal reflux disease','Gastrointestinal'),
            array('E78','Disorders of lipoprotein metabolism','Endocrine'),
            array('J44','Chronic obstructive pulmonary disease','Respiratory'),
            array('I25','Chronic ischaemic heart disease','Cardiovascular'),
            array('O14','Pre-eclampsia','Pregnancy'),
            array('O15','Eclampsia','Pregnancy'),
            array('O72','Postpartum haemorrhage','Pregnancy'),
            array('Z34','Supervision of normal pregnancy','Pregnancy')
        );

        $inserted = 0;
        foreach ($codes as $c) {
            $exists = $this->db->get_where('icd10_codes', array('code' => $c[0]))->row();
            if (!$exists) {
                $this->db->insert('icd10_codes', array(
                    'code' => $c[0], 'description' => $c[1],
                    'category' => $c[2], 'is_active' => 1
                ));
                $inserted++;
            }
        }
        return $inserted > 0 ? "ICD-10: {$inserted} codes added (total now " . ($current + $inserted) . ")" : null;
    }

    /**
     * Map unlinked diagnoses to ICD-10 codes by name matching
     */
    private function _map_diagnoses_icd10()
    {
        if (!$this->table_exists('diagnosis') || !$this->table_exists('icd10_codes')) return null;
        if (!$this->column_exists('diagnosis', 'icd_code')) return null;

        $unmapped = $this->db->query("
            SELECT diagnosis_id, diagnosis_name
            FROM diagnosis
            WHERE InActive = 0
              AND (icd_code IS NULL OR icd_code = '')
        ")->result();

        if (empty($unmapped)) return null;

        $mapped = 0;
        foreach ($unmapped as $d) {
            // Try exact match
            $icd = $this->db->query("
                SELECT code FROM icd10_codes
                WHERE is_active = 1
                  AND LOWER(description) = LOWER(?)
                LIMIT 1
            ", array($d->diagnosis_name))->row();

            if (!$icd) {
                // Fuzzy: diagnosis name contains ICD description or vice versa
                $icd = $this->db->query("
                    SELECT code FROM icd10_codes
                    WHERE is_active = 1
                      AND (LOWER(description) LIKE CONCAT('%', LOWER(?), '%')
                           OR LOWER(?) LIKE CONCAT('%', LOWER(description), '%'))
                    ORDER BY LENGTH(description) DESC
                    LIMIT 1
                ", array($d->diagnosis_name, $d->diagnosis_name))->row();
            }

            if ($icd) {
                $this->db->where('diagnosis_id', $d->diagnosis_id);
                $this->db->update('diagnosis', array('icd_code' => $icd->code));
                $mapped++;
            }
        }
        return $mapped > 0 ? "Diagnoses: {$mapped}/" . count($unmapped) . " mapped to ICD-10" : null;
    }

    /**
     * Get migration status report
     */
    public function get_migration_status()
    {
        $status = [];
        
        $tables = [
            'doctor' => 'Doctor Table',
            'iop_billing' => 'Invoice/Billing Table',
            'iop_billing_t' => 'Invoice Items Table',
            'payment_transactions' => 'Payment Transactions',
            'medicine_drug_name' => 'Medicine Master',
            'radiology_test_master' => 'Radiology Tests',
            'nhis_service_mapping' => 'NHIS Service Mapping',
            'nhis_claims' => 'NHIS Claims',
            'nhis_tariffs' => 'NHIS Tariffs',
            'icd10_codes' => 'ICD-10 Codes',
            'nhis_gdrg_codes' => 'G-DRG Procedure Codes',
            'nhis_coverage' => 'NHIS Coverage Rules',
            'nhis_drug_tariffs' => 'NHIS Drug Tariffs',
            'drug_tariff_mapping' => 'Drug-Tariff Mapping',
            'nhis_memberships' => 'NHIS Memberships',
            'nhis_diagnosis' => 'NHIS Claim Diagnoses',
            'claimit_logs' => 'Claim-It API Logs'
        ];
        
        foreach ($tables as $table => $desc) {
            $exists = $this->table_exists($table);
            $count = $exists ? $this->db->count_all($table) : 0;
            $status[$table] = [
                'description' => $desc,
                'exists' => $exists,
                'records' => $count
            ];
        }
        
        return $status;
    }
}
