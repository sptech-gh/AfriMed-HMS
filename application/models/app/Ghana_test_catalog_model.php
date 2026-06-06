<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ghana Test Catalog Model
 * 
 * Manages GHS/NHIS-compliant laboratory and sonography test catalogs.
 * This model provides a single source of truth for all diagnostic tests.
 * 
 * @author HMS Development Team
 * @version 1.0.0
 */
class Ghana_test_catalog_model extends CI_Model
{
    // Lab test categories (GHS Standard)
    const CAT_HEMATOLOGY = 'Hematology';
    const CAT_BIOCHEMISTRY = 'Biochemistry';
    const CAT_MICROBIOLOGY = 'Microbiology';
    const CAT_SEROLOGY = 'Serology';
    const CAT_URINALYSIS = 'Urinalysis';
    const CAT_PARASITOLOGY = 'Parasitology';
    const CAT_CLINICAL_CHEMISTRY = 'Clinical Chemistry';
    const CAT_IMMUNOLOGY = 'Immunology';
    const CAT_HORMONES = 'Hormones';
    
    // Sonography categories
    const SONO_OBSTETRIC = 'Obstetric';
    const SONO_ABDOMINAL = 'Abdominal';
    const SONO_PELVIC = 'Pelvic';
    const SONO_UROLOGY = 'Urology';
    const SONO_GENERAL = 'General';
    const SONO_VASCULAR = 'Vascular';
    
    public function __construct()
    {
        parent::__construct();
    }
    
    /**
     * Ensure all required tables exist with proper structure
     */
    public function ensure_catalog_tables()
    {
        $this->_create_lab_tests_table();
        $this->_create_sonography_tests_table();
        $this->_create_test_categories_table();
        $this->_populate_default_tests();
        $this->_migrate_billing_bridge();
    }
    
    /**
     * Create lab_tests table (GHS Standard)
     */
    private function _create_lab_tests_table()
    {
        if ($this->db->table_exists('ghs_lab_tests')) {
            return;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `ghs_lab_tests` (
            `test_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `test_code` VARCHAR(20) NOT NULL COMMENT 'GHS/NHIS code',
            `test_name` VARCHAR(150) NOT NULL,
            `category` VARCHAR(50) NOT NULL,
            `department` VARCHAR(50) DEFAULT 'Laboratory',
            `specimen_type` VARCHAR(100) DEFAULT NULL COMMENT 'Blood, Urine, Stool, etc.',
            `price` DECIMAL(10,2) DEFAULT 0.00,
            `nhis_code` VARCHAR(20) DEFAULT NULL,
            `nhis_price` DECIMAL(10,2) DEFAULT 0.00,
            `is_nhis_covered` TINYINT(1) DEFAULT 0,
            `turnaround_time` VARCHAR(50) DEFAULT NULL COMMENT 'Expected result time',
            `requires_fasting` TINYINT(1) DEFAULT 0,
            `special_instructions` TEXT DEFAULT NULL,
            `particular_id` INT(11) DEFAULT NULL COMMENT 'FK to bill_particular for billing bridge',
            `is_active` TINYINT(1) DEFAULT 1,
            `display_order` INT(11) DEFAULT 0,
            `created_by` INT(11) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `InActive` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`test_id`),
            UNIQUE KEY `uk_test_code` (`test_code`),
            KEY `idx_category` (`category`),
            KEY `idx_active` (`is_active`, `InActive`),
            KEY `idx_nhis` (`is_nhis_covered`),
            KEY `idx_particular` (`particular_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        log_message('info', 'Ghana Test Catalog: Created ghs_lab_tests table');
    }
    
    /**
     * Create sonography_tests table (GHS Standard)
     */
    private function _create_sonography_tests_table()
    {
        if ($this->db->table_exists('ghs_sonography_tests')) {
            return;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `ghs_sonography_tests` (
            `test_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `test_code` VARCHAR(20) NOT NULL COMMENT 'GHS/NHIS code',
            `test_name` VARCHAR(150) NOT NULL,
            `category` VARCHAR(50) NOT NULL COMMENT 'Obstetric, Abdominal, etc.',
            `body_part` VARCHAR(100) DEFAULT NULL,
            `department` VARCHAR(50) DEFAULT 'Radiology',
            `price` DECIMAL(10,2) DEFAULT 0.00,
            `nhis_code` VARCHAR(20) DEFAULT NULL,
            `nhis_price` DECIMAL(10,2) DEFAULT 0.00,
            `is_nhis_covered` TINYINT(1) DEFAULT 0,
            `preparation` TEXT DEFAULT NULL COMMENT 'Patient preparation instructions',
            `particular_id` INT(11) DEFAULT NULL COMMENT 'FK to bill_particular for billing bridge',
            `is_active` TINYINT(1) DEFAULT 1,
            `display_order` INT(11) DEFAULT 0,
            `created_by` INT(11) DEFAULT NULL,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            `InActive` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`test_id`),
            UNIQUE KEY `uk_test_code` (`test_code`),
            KEY `idx_category` (`category`),
            KEY `idx_active` (`is_active`, `InActive`),
            KEY `idx_nhis` (`is_nhis_covered`),
            KEY `idx_particular` (`particular_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        log_message('info', 'Ghana Test Catalog: Created ghs_sonography_tests table');
    }
    
    /**
     * Create test categories reference table
     */
    private function _create_test_categories_table()
    {
        if ($this->db->table_exists('ghs_test_categories')) {
            return;
        }
        
        $sql = "CREATE TABLE IF NOT EXISTS `ghs_test_categories` (
            `category_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `category_type` ENUM('LAB', 'SONOGRAPHY', 'RADIOLOGY') NOT NULL,
            `category_name` VARCHAR(100) NOT NULL,
            `category_code` VARCHAR(20) DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `display_order` INT(11) DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `InActive` TINYINT(1) DEFAULT 0,
            PRIMARY KEY (`category_id`),
            KEY `idx_type` (`category_type`),
            KEY `idx_active` (`is_active`, `InActive`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
        
        // Populate categories
        $this->_populate_categories();
    }
    
    /**
     * Populate test categories
     */
    private function _populate_categories()
    {
        $check = $this->db->get_where('ghs_test_categories', ['category_type' => 'LAB'])->num_rows();
        if ($check > 0) return;
        
        $categories = [
            // Lab categories
            ['LAB', 'Hematology', 'HEM', 'Blood cell counts and related tests', 1],
            ['LAB', 'Biochemistry', 'BIO', 'Blood chemistry and metabolic tests', 2],
            ['LAB', 'Microbiology', 'MIC', 'Culture and sensitivity tests', 3],
            ['LAB', 'Serology', 'SER', 'Antibody and antigen tests', 4],
            ['LAB', 'Urinalysis', 'URI', 'Urine examination tests', 5],
            ['LAB', 'Parasitology', 'PAR', 'Parasite detection tests', 6],
            ['LAB', 'Clinical Chemistry', 'CHM', 'Organ function tests', 7],
            ['LAB', 'Immunology', 'IMM', 'Immune system tests', 8],
            ['LAB', 'Hormones', 'HOR', 'Hormone level tests', 9],
            ['LAB', 'Coagulation', 'COA', 'Blood clotting tests', 10],
            
            // Sonography categories
            ['SONOGRAPHY', 'Obstetric', 'OBS', 'Pregnancy-related scans', 1],
            ['SONOGRAPHY', 'Abdominal', 'ABD', 'Abdominal organ scans', 2],
            ['SONOGRAPHY', 'Pelvic', 'PEL', 'Pelvic organ scans', 3],
            ['SONOGRAPHY', 'Urology', 'URO', 'Urinary system scans', 4],
            ['SONOGRAPHY', 'General', 'GEN', 'General purpose scans', 5],
            ['SONOGRAPHY', 'Vascular', 'VAS', 'Blood vessel scans', 6],
            ['SONOGRAPHY', 'Musculoskeletal', 'MSK', 'Muscle and joint scans', 7],
        ];
        
        foreach ($categories as $cat) {
            $this->db->insert('ghs_test_categories', [
                'category_type' => $cat[0],
                'category_name' => $cat[1],
                'category_code' => $cat[2],
                'description' => $cat[3],
                'display_order' => $cat[4],
                'is_active' => 1
            ]);
        }
    }
    
    /**
     * Populate default GHS standard tests
     */
    private function _populate_default_tests()
    {
        $this->_populate_lab_tests();
        $this->_populate_sonography_tests();
    }
    
    /**
     * Populate Ghana Standard Laboratory Tests
     */
    private function _populate_lab_tests()
    {
        // Check if table has any rows (active or inactive) to avoid re-population
        if ($this->db->table_exists('ghs_lab_tests')) {
            $total = $this->db->count_all('ghs_lab_tests');
            if ($total > 0) return;
        }
        
        $tests = [
            // HEMATOLOGY
            ['HEM001', 'Full Blood Count (FBC)', 'Hematology', 'Blood', 50.00, 'NHIS-HEM-001', 40.00, 1, 'Same day', 0],
            ['HEM002', 'Hemoglobin (Hb)', 'Hematology', 'Blood', 20.00, 'NHIS-HEM-002', 15.00, 1, '30 mins', 0],
            ['HEM003', 'Erythrocyte Sedimentation Rate (ESR)', 'Hematology', 'Blood', 25.00, 'NHIS-HEM-003', 20.00, 1, '1 hour', 0],
            ['HEM004', 'Blood Group & Rh Factor', 'Hematology', 'Blood', 30.00, 'NHIS-HEM-004', 25.00, 1, '30 mins', 0],
            ['HEM005', 'Peripheral Blood Film', 'Hematology', 'Blood', 35.00, 'NHIS-HEM-005', 30.00, 1, '2 hours', 0],
            ['HEM006', 'Reticulocyte Count', 'Hematology', 'Blood', 30.00, 'NHIS-HEM-006', 25.00, 1, '2 hours', 0],
            ['HEM007', 'Sickling Test', 'Hematology', 'Blood', 25.00, 'NHIS-HEM-007', 20.00, 1, '1 hour', 0],
            ['HEM008', 'Hemoglobin Electrophoresis', 'Hematology', 'Blood', 80.00, 'NHIS-HEM-008', 60.00, 1, '1 day', 0],
            ['HEM009', 'G6PD Screening', 'Hematology', 'Blood', 40.00, 'NHIS-HEM-009', 35.00, 1, '2 hours', 0],
            ['HEM010', 'Platelet Count', 'Hematology', 'Blood', 25.00, 'NHIS-HEM-010', 20.00, 1, '1 hour', 0],
            
            // MALARIA / PARASITOLOGY
            ['PAR001', 'Malaria Parasite (MP)', 'Parasitology', 'Blood', 20.00, 'NHIS-PAR-001', 15.00, 1, '30 mins', 0],
            ['PAR002', 'Malaria RDT', 'Parasitology', 'Blood', 25.00, 'NHIS-PAR-002', 20.00, 1, '15 mins', 0],
            ['PAR003', 'Stool for Ova and Parasites', 'Parasitology', 'Stool', 25.00, 'NHIS-PAR-003', 20.00, 1, '1 hour', 0],
            ['PAR004', 'Stool for Occult Blood', 'Parasitology', 'Stool', 20.00, 'NHIS-PAR-004', 15.00, 1, '30 mins', 0],
            
            // BIOCHEMISTRY / CLINICAL CHEMISTRY
            ['BIO001', 'Fasting Blood Sugar (FBS)', 'Biochemistry', 'Blood', 25.00, 'NHIS-BIO-001', 20.00, 1, '1 hour', 1],
            ['BIO002', 'Random Blood Sugar (RBS)', 'Biochemistry', 'Blood', 25.00, 'NHIS-BIO-002', 20.00, 1, '30 mins', 0],
            ['BIO003', 'HbA1c (Glycated Hemoglobin)', 'Biochemistry', 'Blood', 80.00, 'NHIS-BIO-003', 60.00, 1, '1 day', 0],
            ['BIO004', 'Oral Glucose Tolerance Test (OGTT)', 'Biochemistry', 'Blood', 60.00, 'NHIS-BIO-004', 50.00, 1, '3 hours', 1],
            ['BIO005', 'Urea', 'Biochemistry', 'Blood', 30.00, 'NHIS-BIO-005', 25.00, 1, '2 hours', 0],
            ['BIO006', 'Creatinine', 'Biochemistry', 'Blood', 30.00, 'NHIS-BIO-006', 25.00, 1, '2 hours', 0],
            ['BIO007', 'Electrolytes (Na, K, Cl)', 'Biochemistry', 'Blood', 50.00, 'NHIS-BIO-007', 40.00, 1, '2 hours', 0],
            ['BIO008', 'Uric Acid', 'Biochemistry', 'Blood', 30.00, 'NHIS-BIO-008', 25.00, 1, '2 hours', 0],
            ['BIO009', 'Lipid Profile', 'Biochemistry', 'Blood', 80.00, 'NHIS-BIO-009', 60.00, 1, '1 day', 1],
            ['BIO010', 'Total Cholesterol', 'Biochemistry', 'Blood', 35.00, 'NHIS-BIO-010', 30.00, 1, '2 hours', 1],
            ['BIO011', 'Triglycerides', 'Biochemistry', 'Blood', 35.00, 'NHIS-BIO-011', 30.00, 1, '2 hours', 1],
            ['BIO012', 'HDL Cholesterol', 'Biochemistry', 'Blood', 35.00, 'NHIS-BIO-012', 30.00, 1, '2 hours', 1],
            ['BIO013', 'LDL Cholesterol', 'Biochemistry', 'Blood', 35.00, 'NHIS-BIO-013', 30.00, 1, '2 hours', 1],
            
            // LIVER FUNCTION
            ['LFT001', 'Liver Function Test (LFT) Full', 'Clinical Chemistry', 'Blood', 100.00, 'NHIS-LFT-001', 80.00, 1, '1 day', 0],
            ['LFT002', 'Total Bilirubin', 'Clinical Chemistry', 'Blood', 30.00, 'NHIS-LFT-002', 25.00, 1, '2 hours', 0],
            ['LFT003', 'Direct Bilirubin', 'Clinical Chemistry', 'Blood', 30.00, 'NHIS-LFT-003', 25.00, 1, '2 hours', 0],
            ['LFT004', 'AST (SGOT)', 'Clinical Chemistry', 'Blood', 35.00, 'NHIS-LFT-004', 30.00, 1, '2 hours', 0],
            ['LFT005', 'ALT (SGPT)', 'Clinical Chemistry', 'Blood', 35.00, 'NHIS-LFT-005', 30.00, 1, '2 hours', 0],
            ['LFT006', 'Alkaline Phosphatase (ALP)', 'Clinical Chemistry', 'Blood', 35.00, 'NHIS-LFT-006', 30.00, 1, '2 hours', 0],
            ['LFT007', 'GGT', 'Clinical Chemistry', 'Blood', 35.00, 'NHIS-LFT-007', 30.00, 1, '2 hours', 0],
            ['LFT008', 'Total Protein', 'Clinical Chemistry', 'Blood', 30.00, 'NHIS-LFT-008', 25.00, 1, '2 hours', 0],
            ['LFT009', 'Albumin', 'Clinical Chemistry', 'Blood', 30.00, 'NHIS-LFT-009', 25.00, 1, '2 hours', 0],
            
            // KIDNEY FUNCTION
            ['RFT001', 'Renal Function Test (RFT) Full', 'Clinical Chemistry', 'Blood', 80.00, 'NHIS-RFT-001', 60.00, 1, '1 day', 0],
            ['RFT002', 'BUN (Blood Urea Nitrogen)', 'Clinical Chemistry', 'Blood', 30.00, 'NHIS-RFT-002', 25.00, 1, '2 hours', 0],
            ['RFT003', 'eGFR (Estimated GFR)', 'Clinical Chemistry', 'Blood', 35.00, 'NHIS-RFT-003', 30.00, 1, '2 hours', 0],
            
            // SEROLOGY / INFECTION TESTS
            ['SER001', 'HIV 1 & 2 Antibodies', 'Serology', 'Blood', 50.00, 'NHIS-SER-001', 0.00, 1, '1 hour', 0],
            ['SER002', 'Hepatitis B Surface Antigen (HBsAg)', 'Serology', 'Blood', 40.00, 'NHIS-SER-002', 35.00, 1, '1 hour', 0],
            ['SER003', 'Hepatitis C Antibody', 'Serology', 'Blood', 50.00, 'NHIS-SER-003', 40.00, 1, '1 hour', 0],
            ['SER004', 'VDRL/RPR (Syphilis)', 'Serology', 'Blood', 30.00, 'NHIS-SER-004', 25.00, 1, '1 hour', 0],
            ['SER005', 'Widal Test (Typhoid)', 'Serology', 'Blood', 35.00, 'NHIS-SER-005', 30.00, 1, '2 hours', 0],
            ['SER006', 'ASO Titre', 'Serology', 'Blood', 40.00, 'NHIS-SER-006', 35.00, 1, '2 hours', 0],
            ['SER007', 'Rheumatoid Factor (RF)', 'Serology', 'Blood', 40.00, 'NHIS-SER-007', 35.00, 1, '2 hours', 0],
            ['SER008', 'CRP (C-Reactive Protein)', 'Serology', 'Blood', 45.00, 'NHIS-SER-008', 40.00, 1, '2 hours', 0],
            ['SER009', 'Pregnancy Test (Blood)', 'Serology', 'Blood', 30.00, 'NHIS-SER-009', 25.00, 1, '1 hour', 0],
            
            // URINALYSIS
            ['URI001', 'Urinalysis (Routine)', 'Urinalysis', 'Urine', 25.00, 'NHIS-URI-001', 20.00, 1, '30 mins', 0],
            ['URI002', 'Urine Microscopy', 'Urinalysis', 'Urine', 25.00, 'NHIS-URI-002', 20.00, 1, '30 mins', 0],
            ['URI003', 'Urine Culture & Sensitivity', 'Urinalysis', 'Urine', 60.00, 'NHIS-URI-003', 50.00, 1, '2-3 days', 0],
            ['URI004', 'Pregnancy Test (Urine)', 'Urinalysis', 'Urine', 20.00, 'NHIS-URI-004', 15.00, 1, '15 mins', 0],
            ['URI005', '24-Hour Urine Protein', 'Urinalysis', 'Urine', 50.00, 'NHIS-URI-005', 40.00, 1, '1 day', 0],
            ['URI006', 'Urine Albumin/Creatinine Ratio', 'Urinalysis', 'Urine', 45.00, 'NHIS-URI-006', 40.00, 1, '2 hours', 0],
            
            // MICROBIOLOGY
            ['MIC001', 'Blood Culture', 'Microbiology', 'Blood', 80.00, 'NHIS-MIC-001', 60.00, 1, '3-5 days', 0],
            ['MIC002', 'Wound Swab Culture', 'Microbiology', 'Swab', 50.00, 'NHIS-MIC-002', 40.00, 1, '2-3 days', 0],
            ['MIC003', 'Throat Swab Culture', 'Microbiology', 'Swab', 45.00, 'NHIS-MIC-003', 35.00, 1, '2-3 days', 0],
            ['MIC004', 'Sputum Culture', 'Microbiology', 'Sputum', 50.00, 'NHIS-MIC-004', 40.00, 1, '2-3 days', 0],
            ['MIC005', 'Sputum AFB (TB)', 'Microbiology', 'Sputum', 30.00, 'NHIS-MIC-005', 0.00, 1, '1-2 days', 0],
            ['MIC006', 'Stool Culture', 'Microbiology', 'Stool', 50.00, 'NHIS-MIC-006', 40.00, 1, '2-3 days', 0],
            ['MIC007', 'High Vaginal Swab (HVS)', 'Microbiology', 'Swab', 50.00, 'NHIS-MIC-007', 40.00, 1, '2-3 days', 0],
            
            // HORMONES
            ['HOR001', 'Thyroid Function Test (TFT)', 'Hormones', 'Blood', 120.00, 'NHIS-HOR-001', 100.00, 1, '1 day', 0],
            ['HOR002', 'TSH', 'Hormones', 'Blood', 50.00, 'NHIS-HOR-002', 40.00, 1, '1 day', 0],
            ['HOR003', 'Free T4', 'Hormones', 'Blood', 50.00, 'NHIS-HOR-003', 40.00, 1, '1 day', 0],
            ['HOR004', 'Free T3', 'Hormones', 'Blood', 50.00, 'NHIS-HOR-004', 40.00, 1, '1 day', 0],
            ['HOR005', 'PSA (Prostate Specific Antigen)', 'Hormones', 'Blood', 80.00, 'NHIS-HOR-005', 60.00, 1, '1 day', 0],
            
            // COAGULATION
            ['COA001', 'Prothrombin Time (PT)', 'Coagulation', 'Blood', 40.00, 'NHIS-COA-001', 35.00, 1, '2 hours', 0],
            ['COA002', 'INR', 'Coagulation', 'Blood', 40.00, 'NHIS-COA-002', 35.00, 1, '2 hours', 0],
            ['COA003', 'aPTT', 'Coagulation', 'Blood', 45.00, 'NHIS-COA-003', 40.00, 1, '2 hours', 0],
            ['COA004', 'Bleeding Time', 'Coagulation', 'Blood', 25.00, 'NHIS-COA-004', 20.00, 1, '30 mins', 0],
            ['COA005', 'Clotting Time', 'Coagulation', 'Blood', 25.00, 'NHIS-COA-005', 20.00, 1, '30 mins', 0],
        ];
        
        foreach ($tests as $test) {
            $this->db->insert('ghs_lab_tests', [
                'test_code' => $test[0],
                'test_name' => $test[1],
                'category' => $test[2],
                'specimen_type' => $test[3],
                'price' => $test[4],
                'nhis_code' => $test[5],
                'nhis_price' => $test[6],
                'is_nhis_covered' => $test[7],
                'turnaround_time' => $test[8],
                'requires_fasting' => $test[9],
                'is_active' => 1
            ]);
        }
        
        log_message('info', 'Ghana Test Catalog: Populated ' . count($tests) . ' lab tests');
    }
    
    /**
     * Populate Ghana Standard Sonography Tests
     */
    private function _populate_sonography_tests()
    {
        // Check if table has any rows (active or inactive) to avoid re-population
        if ($this->db->table_exists('ghs_sonography_tests')) {
            $total = $this->db->count_all('ghs_sonography_tests');
            if ($total > 0) return;
        }
        
        $tests = [
            // OBSTETRIC
            ['OBS001', 'Obstetric Scan (Early Pregnancy)', 'Obstetric', 'Uterus/Pelvis', 80.00, 'NHIS-OBS-001', 60.00, 1, 'Full bladder required'],
            ['OBS002', 'Dating Scan', 'Obstetric', 'Uterus', 80.00, 'NHIS-OBS-002', 60.00, 1, 'Full bladder required'],
            ['OBS003', 'Anomaly Scan (Level 2)', 'Obstetric', 'Fetus', 120.00, 'NHIS-OBS-003', 100.00, 1, 'No preparation needed'],
            ['OBS004', 'Growth Scan', 'Obstetric', 'Fetus', 80.00, 'NHIS-OBS-004', 60.00, 1, 'No preparation needed'],
            ['OBS005', 'Biophysical Profile (BPP)', 'Obstetric', 'Fetus', 100.00, 'NHIS-OBS-005', 80.00, 1, 'No preparation needed'],
            ['OBS006', 'Nuchal Translucency Scan', 'Obstetric', 'Fetus', 100.00, 'NHIS-OBS-006', 80.00, 1, 'Full bladder may help'],
            ['OBS007', 'Cervical Length Scan', 'Obstetric', 'Cervix', 80.00, 'NHIS-OBS-007', 60.00, 1, 'Full bladder required'],
            
            // PELVIC
            ['PEL001', 'Pelvic Scan (Female)', 'Pelvic', 'Pelvis', 80.00, 'NHIS-PEL-001', 60.00, 1, 'Full bladder required'],
            ['PEL002', 'Transvaginal Scan', 'Pelvic', 'Uterus/Ovaries', 100.00, 'NHIS-PEL-002', 80.00, 1, 'Empty bladder'],
            ['PEL003', 'Follicular Tracking', 'Pelvic', 'Ovaries', 60.00, 'NHIS-PEL-003', 50.00, 1, 'Full bladder required'],
            
            // ABDOMINAL
            ['ABD001', 'Abdominal Scan (Full)', 'Abdominal', 'Abdomen', 100.00, 'NHIS-ABD-001', 80.00, 1, 'Fasting 6-8 hours'],
            ['ABD002', 'Upper Abdominal Scan', 'Abdominal', 'Upper Abdomen', 80.00, 'NHIS-ABD-002', 60.00, 1, 'Fasting 6-8 hours'],
            ['ABD003', 'Liver Scan', 'Abdominal', 'Liver', 60.00, 'NHIS-ABD-003', 50.00, 1, 'Fasting 6-8 hours'],
            ['ABD004', 'Gallbladder Scan', 'Abdominal', 'Gallbladder', 60.00, 'NHIS-ABD-004', 50.00, 1, 'Fasting 6-8 hours'],
            ['ABD005', 'Pancreas Scan', 'Abdominal', 'Pancreas', 60.00, 'NHIS-ABD-005', 50.00, 1, 'Fasting 6-8 hours'],
            ['ABD006', 'Spleen Scan', 'Abdominal', 'Spleen', 60.00, 'NHIS-ABD-006', 50.00, 1, 'No preparation needed'],
            ['ABD007', 'Appendix Scan', 'Abdominal', 'Appendix', 80.00, 'NHIS-ABD-007', 60.00, 1, 'No preparation needed'],
            
            // UROLOGY
            ['URO001', 'Kidney Scan (KUB)', 'Urology', 'Kidneys/Ureters/Bladder', 80.00, 'NHIS-URO-001', 60.00, 1, 'Full bladder required'],
            ['URO002', 'Prostate Scan (Transabdominal)', 'Urology', 'Prostate', 80.00, 'NHIS-URO-002', 60.00, 1, 'Full bladder required'],
            ['URO003', 'Prostate Scan (Transrectal)', 'Urology', 'Prostate', 120.00, 'NHIS-URO-003', 100.00, 1, 'Enema may be required'],
            ['URO004', 'Bladder Scan', 'Urology', 'Bladder', 60.00, 'NHIS-URO-004', 50.00, 1, 'Full bladder required'],
            ['URO005', 'Post-Void Residual Volume', 'Urology', 'Bladder', 50.00, 'NHIS-URO-005', 40.00, 1, 'After voiding'],
            ['URO006', 'Scrotal Scan', 'Urology', 'Scrotum/Testes', 80.00, 'NHIS-URO-006', 60.00, 1, 'No preparation needed'],
            
            // GENERAL
            ['GEN001', 'Thyroid Scan', 'General', 'Thyroid', 80.00, 'NHIS-GEN-001', 60.00, 1, 'No preparation needed'],
            ['GEN002', 'Breast Scan (Bilateral)', 'General', 'Breasts', 100.00, 'NHIS-GEN-002', 80.00, 1, 'No preparation needed'],
            ['GEN003', 'Breast Scan (Unilateral)', 'General', 'Breast', 60.00, 'NHIS-GEN-003', 50.00, 1, 'No preparation needed'],
            ['GEN004', 'Soft Tissue Scan', 'General', 'Soft Tissue', 60.00, 'NHIS-GEN-004', 50.00, 1, 'No preparation needed'],
            ['GEN005', 'Lymph Node Scan', 'General', 'Lymph Nodes', 60.00, 'NHIS-GEN-005', 50.00, 1, 'No preparation needed'],
            ['GEN006', 'Musculoskeletal Scan', 'General', 'Muscle/Joint', 80.00, 'NHIS-GEN-006', 60.00, 1, 'No preparation needed'],
            
            // VASCULAR
            ['VAS001', 'Carotid Doppler', 'Vascular', 'Carotid Arteries', 150.00, 'NHIS-VAS-001', 120.00, 1, 'No preparation needed'],
            ['VAS002', 'Lower Limb Arterial Doppler', 'Vascular', 'Leg Arteries', 150.00, 'NHIS-VAS-002', 120.00, 1, 'No preparation needed'],
            ['VAS003', 'Lower Limb Venous Doppler', 'Vascular', 'Leg Veins', 150.00, 'NHIS-VAS-003', 120.00, 1, 'No preparation needed'],
            ['VAS004', 'DVT Scan', 'Vascular', 'Deep Veins', 120.00, 'NHIS-VAS-004', 100.00, 1, 'No preparation needed'],
        ];
        
        foreach ($tests as $test) {
            $this->db->insert('ghs_sonography_tests', [
                'test_code' => $test[0],
                'test_name' => $test[1],
                'category' => $test[2],
                'body_part' => $test[3],
                'price' => $test[4],
                'nhis_code' => $test[5],
                'nhis_price' => $test[6],
                'is_nhis_covered' => $test[7],
                'preparation' => $test[8],
                'is_active' => 1
            ]);
        }
        
        log_message('info', 'Ghana Test Catalog: Populated ' . count($tests) . ' sonography tests');
    }
    
    /* ================================================================== */
    /*  BILLING BRIDGE — Link GHS catalog to bill_particular & NHIS      */
    /* ================================================================== */

    /**
     * Idempotent migration: add particular_id column (if missing) to both
     * GHS tables, then auto-link rows to bill_particular by fuzzy name match.
     * Also seeds nhis_service_mapping for Claim-It integration.
     */
    private function _migrate_billing_bridge()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $old = $this->db->db_debug;
        $this->db->db_debug = false;

        // Add particular_id column if tables already existed before this migration
        foreach (['ghs_lab_tests', 'ghs_sonography_tests'] as $tbl) {
            if (!$this->db->table_exists($tbl)) continue;
            $cols = $this->db->list_fields($tbl);
            if (!in_array('particular_id', $cols)) {
                $this->db->query("ALTER TABLE `{$tbl}` ADD COLUMN `particular_id` INT(11) DEFAULT NULL COMMENT 'FK to bill_particular'");
                $this->db->query("ALTER TABLE `{$tbl}` ADD KEY `idx_particular` (`particular_id`)");
            }
        }

        // Auto-link: match GHS lab tests → bill_particular by name (case-insensitive)
        if ($this->db->table_exists('bill_particular')) {
            $this->_link_catalog_to_billing('ghs_lab_tests');
            $this->_link_catalog_to_billing('ghs_sonography_tests');
        }

        // Seed nhis_service_mapping from GHS catalog
        $this->_sync_nhis_service_mapping();

        $this->db->db_debug = $old;
    }

    /**
     * For each GHS test with particular_id = NULL, find a bill_particular
     * row whose particular_name matches (case-insensitive) and link it.
     */
    private function _link_catalog_to_billing($catalog_table)
    {
        $unlinked = $this->db->query(
            "SELECT test_id, test_name FROM `{$catalog_table}` WHERE particular_id IS NULL AND is_active = 1 AND InActive = 0"
        );
        if (!$unlinked || $unlinked->num_rows() === 0) return;

        foreach ($unlinked->result() as $row) {
            $bp = $this->db->query(
                "SELECT particular_id FROM bill_particular WHERE LOWER(TRIM(particular_name)) = LOWER(TRIM(?)) AND InActive = 0 LIMIT 1",
                array($row->test_name)
            );
            if ($bp && $bp->num_rows() > 0) {
                $this->db->where('test_id', $row->test_id);
                $this->db->update($catalog_table, ['particular_id' => $bp->row()->particular_id]);
            }
        }
    }

    /**
     * Auto-populate nhis_service_mapping from GHS lab & sonography catalogs
     * so Claim-It can resolve NHIS codes for every test.
     */
    private function _sync_nhis_service_mapping()
    {
        if (!$this->db->table_exists('nhis_service_mapping')) return;

        // Lab tests
        $lab_tests = $this->db->query(
            "SELECT test_id, test_name, nhis_code, nhis_price, is_nhis_covered, category
             FROM ghs_lab_tests WHERE nhis_code IS NOT NULL AND nhis_code != '' AND is_active = 1 AND InActive = 0"
        );
        if ($lab_tests && $lab_tests->num_rows() > 0) {
            foreach ($lab_tests->result() as $t) {
                $exists = $this->db->get_where('nhis_service_mapping', [
                    'nhis_code' => $t->nhis_code,
                    'hms_service_type' => 'LABORATORY'
                ])->row();
                if (!$exists) {
                    $this->db->insert('nhis_service_mapping', [
                        'hms_service_id'   => (int)$t->test_id,
                        'hms_service_name' => $t->test_name,
                        'hms_service_type' => 'LABORATORY',
                        'nhis_code'        => $t->nhis_code,
                        'nhis_name'        => $t->test_name,
                        'category'         => 'LABORATORY',
                        'is_covered'       => (int)$t->is_nhis_covered,
                        'coverage_percent' => $t->is_nhis_covered ? 100.00 : 0.00,
                        'tariff_amount'    => (float)$t->nhis_price,
                        'is_active'        => 1
                    ]);
                }
            }
        }

        // Sonography tests
        $sono_tests = $this->db->query(
            "SELECT test_id, test_name, nhis_code, nhis_price, is_nhis_covered, category
             FROM ghs_sonography_tests WHERE nhis_code IS NOT NULL AND nhis_code != '' AND is_active = 1 AND InActive = 0"
        );
        if ($sono_tests && $sono_tests->num_rows() > 0) {
            foreach ($sono_tests->result() as $t) {
                $exists = $this->db->get_where('nhis_service_mapping', [
                    'nhis_code' => $t->nhis_code,
                    'hms_service_type' => 'RADIOLOGY'
                ])->row();
                if (!$exists) {
                    $this->db->insert('nhis_service_mapping', [
                        'hms_service_id'   => (int)$t->test_id,
                        'hms_service_name' => $t->test_name,
                        'hms_service_type' => 'RADIOLOGY',
                        'nhis_code'        => $t->nhis_code,
                        'nhis_name'        => $t->test_name,
                        'category'         => 'RADIOLOGY',
                        'is_covered'       => (int)$t->is_nhis_covered,
                        'coverage_percent' => $t->is_nhis_covered ? 100.00 : 0.00,
                        'tariff_amount'    => (float)$t->nhis_price,
                        'is_active'        => 1
                    ]);
                }
            }
        }
    }

    /**
     * Resolve the bill_particular ID for a given GHS test.
     * Returns particular_id if linked, or NULL.
     */
    public function get_particular_id_for_lab_test($test_id)
    {
        $row = $this->db->select('particular_id')
            ->get_where('ghs_lab_tests', ['test_id' => (int)$test_id])
            ->row();
        return ($row && $row->particular_id) ? (int)$row->particular_id : null;
    }

    /**
     * Resolve the bill_particular ID for a given GHS sonography test.
     * Returns particular_id if linked, or NULL.
     */
    public function get_particular_id_for_sono_test($test_id)
    {
        $row = $this->db->select('particular_id')
            ->get_where('ghs_sonography_tests', ['test_id' => (int)$test_id])
            ->row();
        return ($row && $row->particular_id) ? (int)$row->particular_id : null;
    }

    /**
     * Get full test details including billing linkage, for save operations.
     * Returns object with test_name, nhis_code, nhis_price, price, particular_id, category etc.
     */
    public function get_lab_test_for_order($test_id)
    {
        return $this->db->select('test_id, test_code, test_name, category, specimen_type,
                price, nhis_code, nhis_price, is_nhis_covered, particular_id')
            ->get_where('ghs_lab_tests', ['test_id' => (int)$test_id, 'is_active' => 1, 'InActive' => 0])
            ->row();
    }

    /**
     * Get full sonography test details for save operations.
     */
    public function get_sono_test_for_order($test_id)
    {
        return $this->db->select('test_id, test_code, test_name, category, body_part,
                price, nhis_code, nhis_price, is_nhis_covered, particular_id')
            ->get_where('ghs_sonography_tests', ['test_id' => (int)$test_id, 'is_active' => 1, 'InActive' => 0])
            ->row();
    }

    /**
     * Resolve a GHS category name (e.g. "Hematology") to a bill_group_name.group_id.
     * Uses case-insensitive matching with common alias handling.
     * Returns int group_id, or 0 if no match found.
     */
    public function resolve_lab_category_id($category_name)
    {
        static $cache = null;
        if ($category_name === null || trim((string)$category_name) === '') return 0;

        if ($cache === null) {
            $cache = [];
            if ($this->db->table_exists('bill_group_name')) {
                $rows = $this->db->get('bill_group_name')->result();
                foreach ($rows as $r) {
                    $cache[strtolower(trim($r->group_name))] = (int)$r->group_id;
                }
            }
        }

        $key = strtolower(trim((string)$category_name));
        if (isset($cache[$key])) return $cache[$key];

        // Common GHS → legacy name aliases
        $aliases = [
            'hematology'        => 'haematology',
            'haematology'       => 'haematology',
            'clinical chemistry'=> 'biochemistry',
            'hormones'          => 'biochemistry',
            'coagulation'       => 'haematology',
            'immunology'        => 'serology',
            'parasitology'      => 'clinical pathology',
            'urinalysis'        => 'clinical pathology',
        ];
        if (isset($aliases[$key]) && isset($cache[$aliases[$key]])) {
            return $cache[$aliases[$key]];
        }

        // Partial match: check if any group_name contains the category or vice-versa
        foreach ($cache as $gn => $gid) {
            if (strpos($gn, $key) !== false || strpos($key, $gn) !== false) {
                return $gid;
            }
        }

        return 0;
    }

    /* ================================================================== */
    /*  PUBLIC API METHODS - For Doctor UI                                */
    /* ================================================================== */
    
    /**
     * Get all active lab test categories for dropdown
     * MERGED: Includes categories from both GHS catalog AND bill_particular
     */
    public function get_lab_categories()
    {
        $this->ensure_catalog_tables();

        $categories = [];
        $seen_cats = [];

        // Get categories from GHS catalog
        $this->db->distinct();
        $this->db->select('category AS category_name');
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->order_by('category', 'ASC');
        $results = $this->db->get('ghs_lab_tests')->result();

        foreach ($results as $row) {
            $cat_name = trim($row->category_name);
            if ($cat_name && !in_array(strtolower($cat_name), $seen_cats)) {
                $categories[] = (object)[
                    'category_id' => count($categories) + 1,
                    'category_name' => $cat_name
                ];
                $seen_cats[] = strtolower($cat_name);
            }
        }

        // Also get categories from bill_particular
        if ($this->db->table_exists('bill_particular') && $this->db->table_exists('bill_group_name')) {
            $this->db->distinct();
            $this->db->select('bgn.group_name AS category_name');
            $this->db->from('bill_particular bp');
            $this->db->join('bill_group_name bgn', 'bgn.group_id = bp.group_id', 'left');
            $this->db->where('bp.InActive', 0);
            $this->db->where_in('bp.group_id', array('8', '9', '10', '11', '12', '13', '14', '15'));
            $this->db->where('bgn.group_name IS NOT NULL');
            $this->db->order_by('bgn.group_name', 'ASC');
            $bp_results = $this->db->get()->result();

            foreach ($bp_results as $row) {
                $cat_name = trim($row->category_name);
                if ($cat_name && !in_array(strtolower($cat_name), $seen_cats)) {
                    $categories[] = (object)[
                        'category_id' => count($categories) + 1,
                        'category_name' => $cat_name
                    ];
                    $seen_cats[] = strtolower($cat_name);
                }
            }
        }

        return $categories;
    }
    
    /**
     * Get all active lab tests for dropdown
     * Optimized for doctor UI with search/filter support
     * MERGED: Includes both GHS catalog tests AND bill_particular tests
     */
    public function get_lab_tests($category = null, $search = null, $include_inactive = false)
    {
        $this->ensure_catalog_tables();
		if ($this->db->table_exists('ghs_lab_tests') && $this->db->table_exists('bill_particular')) {
			$this->auto_map_lab_particular_ids(25);
		}

		if (!$this->db->table_exists('bill_particular') || !$this->db->table_exists('bill_group_name')) {
			return array();
		}

		$this->db->select("bp.particular_id AS test_id,
			COALESCE(NULLIF(TRIM(ghs.test_code),''), bp.particular_id) AS test_code,
			bp.particular_name AS test_name,
			bgn.group_name AS category,
			COALESCE(ghs.specimen_type,'') AS specimen_type,
			COALESCE(bp.charge_amount, 0) AS price,
			COALESCE(NULLIF(TRIM(ghs.nhis_code),''), '') AS nhis_code,
			COALESCE(ghs.nhis_price, 0) AS nhis_price,
			COALESCE(ghs.is_nhis_covered, COALESCE(bp.is_nhis_covered, 0)) AS is_nhis_covered,
			COALESCE(ghs.turnaround_time,'') AS turnaround_time,
			COALESCE(ghs.requires_fasting, 0) AS requires_fasting,
			bp.particular_id AS particular_id,
			COALESCE(ghs.is_active, 1) AS is_active", false);
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bgn', 'bgn.group_id = bp.group_id', 'left');
		if ($this->db->table_exists('ghs_lab_tests')) {
			$this->db->join('ghs_lab_tests ghs', 'ghs.particular_id = bp.particular_id AND ghs.InActive = 0', 'left');
		}
		$this->db->where('bp.InActive', 0);
		$this->db->where('bgn.group_name IS NOT NULL');
		$this->db->where_in('bp.group_id', array('8', '9', '10', '11', '12', '13', '14', '15'));
		if (!$include_inactive && $this->db->table_exists('ghs_lab_tests')) {
			$this->db->group_start();
			$this->db->where('ghs.is_active', 1);
			$this->db->or_where('ghs.test_id IS NULL', null, false);
			$this->db->group_end();
		}
		if ($category) {
			$this->db->where('bgn.group_name', (string)$category);
		}
		if ($search) {
			$this->db->group_start();
			$this->db->like('bp.particular_name', $search);
			$this->db->or_like('bp.particular_id', $search);
			if ($this->db->table_exists('ghs_lab_tests')) {
				$this->db->or_like('ghs.test_code', $search);
			}
			$this->db->group_end();
		}
		$this->db->order_by('bgn.group_name', 'ASC');
		$this->db->order_by('bp.particular_name', 'ASC');
		return $this->db->get()->result();
    }
    
    /**
     * Get all active sonography categories for dropdown
     * MERGED: Includes categories from both GHS catalog AND bill_particular
     */
    public function get_sonography_categories()
    {
        $this->ensure_catalog_tables();

        $categories = [];
        $seen_cats = [];

        // Get categories from GHS catalog
        $this->db->distinct();
        $this->db->select('category AS category_name');
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->order_by('category', 'ASC');
        $results = $this->db->get('ghs_sonography_tests')->result();

        foreach ($results as $row) {
            $cat_name = trim($row->category_name);
            if ($cat_name && !in_array(strtolower($cat_name), $seen_cats)) {
                $categories[] = (object)[
                    'category_id' => count($categories) + 1,
                    'category_name' => $cat_name
                ];
                $seen_cats[] = strtolower($cat_name);
            }
        }

        // Also get categories from bill_particular (sonography category_id = 18)
        if ($this->db->table_exists('bill_particular') && $this->db->table_exists('bill_group_name')) {
            $sono_cat_id = 18; // Default sonography category ID

            $this->db->distinct();
            $this->db->select('bgn.group_name AS category_name');
            $this->db->from('bill_particular bp');
            $this->db->join('bill_group_name bgn', 'bgn.group_id = bp.group_id', 'left');
            $this->db->where('bp.InActive', 0);
            $this->db->where('bp.group_id', (string)$sono_cat_id);
            $this->db->where('bgn.group_name IS NOT NULL');
            $this->db->order_by('bgn.group_name', 'ASC');
            $bp_results = $this->db->get()->result();

            foreach ($bp_results as $row) {
                $cat_name = trim($row->category_name);
                if ($cat_name && !in_array(strtolower($cat_name), $seen_cats)) {
                    $categories[] = (object)[
                        'category_id' => count($categories) + 1,
                        'category_name' => $cat_name
                    ];
                    $seen_cats[] = strtolower($cat_name);
                }
            }
        }

        return $categories;
    }
    
    /**
     * Get all active sonography tests for dropdown
     * MERGED: Includes both GHS catalog tests AND bill_particular tests (sonography category)
     */
    public function get_sonography_tests($category = null, $search = null, $include_inactive = false)
    {
        $this->ensure_catalog_tables();

        // Get tests from GHS catalog
        $this->db->select('test_id, test_code, test_name, category, body_part,
                          price, nhis_code, nhis_price, is_nhis_covered, preparation, particular_id, is_active');
        if (!$include_inactive) {
            $this->db->where('is_active', 1);
        }
        $this->db->where('InActive', 0);

        if ($category) {
            $this->db->where('category', $category);
        }

        if ($search) {
            $this->db->group_start();
            $this->db->like('test_name', $search);
            $this->db->or_like('test_code', $search);
            $this->db->or_like('body_part', $search);
            $this->db->group_end();
        }

        $this->db->order_by('category', 'ASC');
        $this->db->order_by('test_name', 'ASC');
        $ghs_tests = $this->db->get('ghs_sonography_tests')->result();

        // Also get sonography tests from bill_particular (old system)
        // Join with bill_group_name to get category names instead of IDs
        if ($this->db->table_exists('bill_particular') && $this->db->table_exists('bill_group_name')) {
            // Get sonography category ID from laboratory model
            $sono_cat_id = 18; // Default sonography category ID

            $this->db->select('bp.particular_id AS test_id, bp.particular_id AS test_code, bp.particular_name AS test_name,
                              bgn.group_name AS category, bp.particular_name AS body_part,
                              COALESCE(bp.charge_amount, 0) AS price,
                              "" AS nhis_code, 0 AS nhis_price, 0 AS is_nhis_covered,
                              "" AS preparation, bp.particular_id AS particular_id');
            $this->db->from('bill_particular bp');
            $this->db->join('bill_group_name bgn', 'bgn.group_id = bp.group_id', 'left');
            $this->db->where('bp.InActive', 0);
            $this->db->where('bgn.group_name IS NOT NULL');
            // Filter for sonography/scans category
            $this->db->where('bp.group_id', (string)$sono_cat_id);

            if ($search) {
                $this->db->group_start();
                $this->db->like('bp.particular_name', $search);
                $this->db->or_like('bp.particular_id', $search);
                $this->db->group_end();
            }

            $this->db->order_by('bgn.group_name', 'ASC');
            $this->db->order_by('bp.particular_name', 'ASC');
            $bp_tests = $this->db->get()->result();

            // Merge results, avoiding duplicates by name
            $seen_names = array_map(function($t) { return strtolower(trim($t->test_name)); }, $ghs_tests);
            foreach ($bp_tests as $bp) {
                $name_key = strtolower(trim($bp->test_name));
                if (!in_array($name_key, $seen_names)) {
                    $ghs_tests[] = $bp;
                    $seen_names[] = $name_key;
                }
            }
        }

        return $ghs_tests;
    }
    
    /**
     * Search lab tests (AJAX endpoint)
     */
    public function search_lab_tests($term, $limit = 50)
    {
        $this->ensure_catalog_tables();
		$term = trim((string)$term);
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 50; }
		if ($term === '') { return array(); }
		if ($this->db->table_exists('ghs_lab_tests') && $this->db->table_exists('bill_particular')) {
			$this->auto_map_lab_particular_ids(25);
		}

		if (!$this->db->table_exists('bill_particular') || !$this->db->table_exists('bill_group_name')) {
			return array();
		}

		$starts = $term . '%';
		$like = '%' . $term . '%';
		$this->db->select("bp.particular_id AS id,
			bp.particular_name AS label,
			COALESCE(NULLIF(TRIM(ghs.test_code),''), '') AS code,
			bgn.group_name AS category,
			COALESCE(bp.charge_amount, 0) AS price,
			COALESCE(ghs.nhis_price, 0) AS nhis_price,
			COALESCE(ghs.is_nhis_covered, COALESCE(bp.is_nhis_covered, 0)) AS is_nhis_covered,
			COALESCE(ghs.test_id, 0) AS legacy_test_id", false);
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bgn', 'bgn.group_id = bp.group_id', 'left');
		if ($this->db->table_exists('ghs_lab_tests')) {
			$this->db->join('ghs_lab_tests ghs', 'ghs.particular_id = bp.particular_id AND ghs.InActive = 0 AND ghs.is_active = 1', 'left');
		}
		$this->db->where('bp.InActive', 0);
		$this->db->where_in('bp.group_id', array('8', '9', '10', '11', '12', '13', '14', '15'));
		$this->db->group_start();
		$this->db->like('bp.particular_name', $starts, 'after');
		$this->db->or_like('bp.particular_name', $like);
		$this->db->or_like('bp.particular_id', $like);
		if ($this->db->table_exists('ghs_lab_tests')) {
			$this->db->or_like('ghs.test_code', $like);
		}
		$this->db->group_end();
		$this->db->order_by("CASE WHEN bp.particular_name LIKE " . $this->db->escape($starts) . " THEN 0 ELSE 1 END", null, false);
		$this->db->order_by('bp.particular_name', 'ASC');
		$this->db->limit($limit);
		return $this->db->get()->result();
    }

	public function auto_map_lab_particular_ids($limit = 200)
	{
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 200; }
		if (!$this->db->table_exists('ghs_lab_tests') || !$this->db->table_exists('bill_particular')) {
			return array('checked' => 0, 'mapped' => 0);
		}
		$rows = $this->db->select('test_id, test_name')
			->from('ghs_lab_tests')
			->where('InActive', 0)
			->where('is_active', 1)
			->group_start()
			->where('particular_id IS NULL', null, false)
			->or_where('particular_id', 0)
			->group_end()
			->limit($limit)
			->get()->result();
		$mapped = 0;
		$checked = 0;
		foreach ($rows as $r) {
			$checked++;
			$name = isset($r->test_name) ? strtolower(trim((string)$r->test_name)) : '';
			if ($name === '') { continue; }
			$bpq = $this->db->query(
				"SELECT particular_id FROM bill_particular WHERE InActive = 0 AND group_id IN (8,9,10,11,12,13,14,15) AND LOWER(TRIM(particular_name)) = ?",
				array($name)
			);
			if ($bpq && $bpq->num_rows() === 1) {
				$bp_id = (int)$bpq->row()->particular_id;
				$this->db->where('test_id', (int)$r->test_id);
				$this->db->where('InActive', 0);
				$this->db->update('ghs_lab_tests', array('particular_id' => $bp_id));
				$mapped++;
			}
		}
		return array('checked' => $checked, 'mapped' => $mapped);
	}
    
    /**
     * Search sonography tests (AJAX endpoint)
     */
    public function search_sonography_tests($term, $limit = 50)
    {
        $this->ensure_catalog_tables();
        
        $this->db->select('test_id AS id, test_name AS label, test_code AS code, 
                          category, body_part, price, nhis_price, is_nhis_covered');
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->group_start();
        $this->db->like('test_name', $term);
        $this->db->or_like('test_code', $term);
        $this->db->or_like('body_part', $term);
        $this->db->group_end();
        $this->db->order_by('test_name', 'ASC');
        $this->db->limit($limit);
        
        return $this->db->get('ghs_sonography_tests')->result();
    }
    
    /**
     * Get single lab test by ID
     */
    public function get_lab_test($test_id)
    {
        return $this->db->get_where('ghs_lab_tests', ['test_id' => $test_id])->row();
    }
    
    /**
     * Get single sonography test by ID
     */
    public function get_sonography_test($test_id)
    {
        return $this->db->get_where('ghs_sonography_tests', ['test_id' => $test_id])->row();
    }
    
    /* ================================================================== */
    /*  ADMIN MANAGEMENT METHODS                                          */
    /* ================================================================== */
    
    /**
     * Add new lab test (Admin)
     */
    public function add_lab_test($data)
    {
        $this->ensure_catalog_tables();
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = 1;
        
        if ($this->db->insert('ghs_lab_tests', $data)) {
            return ['success' => true, 'test_id' => $this->db->insert_id()];
        }
        return ['success' => false, 'error' => $this->db->error()];
    }
    
    /**
     * Update lab test (Admin)
     */
    public function update_lab_test($test_id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('test_id', $test_id);
        if ($this->db->update('ghs_lab_tests', $data)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => $this->db->error()];
    }
    
    /**
     * Deactivate lab test (Admin)
     */
    public function deactivate_lab_test($test_id)
    {
        return $this->update_lab_test($test_id, ['is_active' => 0]);
    }
    
    /**
     * Add new sonography test (Admin)
     */
    public function add_sonography_test($data)
    {
        $this->ensure_catalog_tables();
        
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['is_active'] = 1;
        
        if ($this->db->insert('ghs_sonography_tests', $data)) {
            return ['success' => true, 'test_id' => $this->db->insert_id()];
        }
        return ['success' => false, 'error' => $this->db->error()];
    }
    
    /**
     * Update sonography test (Admin)
     */
    public function update_sonography_test($test_id, $data)
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        $this->db->where('test_id', $test_id);
        if ($this->db->update('ghs_sonography_tests', $data)) {
            return ['success' => true];
        }
        return ['success' => false, 'error' => $this->db->error()];
    }
    
    /**
     * Deactivate sonography test (Admin)
     */
    public function deactivate_sonography_test($test_id)
    {
        return $this->update_sonography_test($test_id, ['is_active' => 0]);
    }
    
    /**
     * Get test statistics for admin dashboard
     */
    public function get_test_statistics()
    {
        $this->ensure_catalog_tables();
        
        $stats = [];
        
        // Lab tests
        $stats['lab_total'] = $this->db->where('InActive', 0)->count_all_results('ghs_lab_tests');
        $stats['lab_active'] = $this->db->where(['is_active' => 1, 'InActive' => 0])->count_all_results('ghs_lab_tests');
        $stats['lab_nhis'] = $this->db->where(['is_nhis_covered' => 1, 'is_active' => 1, 'InActive' => 0])->count_all_results('ghs_lab_tests');
        
        // Sonography tests
        $stats['sono_total'] = $this->db->where('InActive', 0)->count_all_results('ghs_sonography_tests');
        $stats['sono_active'] = $this->db->where(['is_active' => 1, 'InActive' => 0])->count_all_results('ghs_sonography_tests');
        $stats['sono_nhis'] = $this->db->where(['is_nhis_covered' => 1, 'is_active' => 1, 'InActive' => 0])->count_all_results('ghs_sonography_tests');
        
        return $stats;
    }
}
