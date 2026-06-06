<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Clinical Decision Support Model
 * 
 * Provides drug interaction checking, allergy detection,
 * duplicate therapy detection, dose validation, and high-risk drug alerts.
 * 
 * Part of Phase 3: Doctor Module Intelligence
 */
class Clinical_decision_support_model extends CI_Model
{
    private $schema_checked = false;

    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  SCHEMA HELPERS                                                     */
    /* ================================================================== */

    private function table_exists($t)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$t));
        return ($q && $q->num_rows() > 0);
    }

    private function column_exists($table, $col)
    {
        if (!$this->table_exists($table)) return false;
        $q = $this->db->query("SHOW COLUMNS FROM `" . (string)$table . "` LIKE " . $this->db->escape((string)$col));
        return ($q && $q->num_rows() > 0);
    }

    /* ================================================================== */
    /*  SCHEMA INSTALLATION (idempotent)                                   */
    /* ================================================================== */

    public function ensure_schema()
    {
        if ($this->schema_checked) return;
        $this->schema_checked = true;

        $this->create_drug_interactions_table();
        $this->create_drug_classes_tables();
        $this->create_patient_allergies_table();
        $this->create_drug_dose_limits_table();
        $this->create_high_risk_drugs_table();
        $this->create_patient_risk_flags_table();
        $this->create_consultation_locks_table();
        $this->create_clinical_notes_audit_table();
        $this->create_prescription_safety_alerts_table();
        
        // Phase 3.5: Additional Clinical Intelligence Tables
        $this->create_nhis_drug_diagnosis_rules_table();
        $this->create_prescription_workflow_table();
        $this->create_drug_contraindications_table();
        $this->create_clinical_override_audit_table();
        $this->create_clinical_decision_cache_table();
        $this->create_prescription_templates_tables();
    }

    private function create_drug_interactions_table()
    {
        if (!$this->table_exists('drug_interactions')) {
            $this->db->query("CREATE TABLE `drug_interactions` (
                `interaction_id` INT AUTO_INCREMENT PRIMARY KEY,
                `drug_id_1` INT NOT NULL,
                `drug_id_2` INT NOT NULL,
                `severity` ENUM('MILD','MODERATE','SEVERE','CONTRAINDICATED') NOT NULL DEFAULT 'MODERATE',
                `description` TEXT,
                `clinical_effect` TEXT,
                `management` TEXT,
                `reference_source` VARCHAR(255),
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `created_by` VARCHAR(25),
                INDEX idx_drug1 (drug_id_1),
                INDEX idx_drug2 (drug_id_2),
                INDEX idx_severity (severity),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            // Ensure is_active column exists on existing table
            if (!$this->column_exists('drug_interactions', 'is_active')) {
                $this->db->query("ALTER TABLE `drug_interactions` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
            }
        }
    }

    private function create_drug_classes_tables()
    {
        if (!$this->table_exists('drug_classes')) {
            $this->db->query("CREATE TABLE `drug_classes` (
                `class_id` INT AUTO_INCREMENT PRIMARY KEY,
                `class_name` VARCHAR(100) NOT NULL,
                `class_code` VARCHAR(20),
                `parent_class_id` INT DEFAULT NULL,
                `description` TEXT,
                `therapeutic_category` VARCHAR(100),
                `is_active` TINYINT(1) DEFAULT 1,
                `InActive` INT(1) DEFAULT 0,
                INDEX idx_name (class_name),
                INDEX idx_code (class_code),
                INDEX idx_parent (parent_class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Seed common drug classes
            $this->seed_drug_classes();
        }

        if (!$this->table_exists('drug_class_mapping')) {
            $this->db->query("CREATE TABLE `drug_class_mapping` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `drug_id` INT NOT NULL,
                `class_id` INT NOT NULL,
                `is_primary` TINYINT(1) DEFAULT 0,
                UNIQUE KEY uniq_drug_class (drug_id, class_id),
                INDEX idx_drug (drug_id),
                INDEX idx_class (class_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    private function seed_drug_classes()
    {
        $classes = array(
            array('Analgesics', 'ANA', 'Pain Management'),
            array('NSAIDs', 'NSAID', 'Anti-inflammatory'),
            array('Opioids', 'OPI', 'Pain Management'),
            array('Antibiotics', 'ABX', 'Anti-infective'),
            array('Penicillins', 'PEN', 'Anti-infective'),
            array('Cephalosporins', 'CEF', 'Anti-infective'),
            array('Fluoroquinolones', 'FLQ', 'Anti-infective'),
            array('Macrolides', 'MAC', 'Anti-infective'),
            array('Aminoglycosides', 'AMG', 'Anti-infective'),
            array('Antihypertensives', 'AHT', 'Cardiovascular'),
            array('ACE Inhibitors', 'ACEI', 'Cardiovascular'),
            array('ARBs', 'ARB', 'Cardiovascular'),
            array('Beta Blockers', 'BB', 'Cardiovascular'),
            array('Calcium Channel Blockers', 'CCB', 'Cardiovascular'),
            array('Diuretics', 'DIU', 'Cardiovascular'),
            array('Anticoagulants', 'ACG', 'Hematology'),
            array('Antiplatelets', 'APL', 'Hematology'),
            array('Antidiabetics', 'DM', 'Endocrine'),
            array('Sulfonylureas', 'SU', 'Endocrine'),
            array('Biguanides', 'BIG', 'Endocrine'),
            array('Insulins', 'INS', 'Endocrine'),
            array('Corticosteroids', 'COR', 'Anti-inflammatory'),
            array('Antihistamines', 'AH', 'Allergy'),
            array('Proton Pump Inhibitors', 'PPI', 'Gastrointestinal'),
            array('H2 Blockers', 'H2B', 'Gastrointestinal'),
            array('Antidepressants', 'AD', 'Psychiatry'),
            array('SSRIs', 'SSRI', 'Psychiatry'),
            array('Benzodiazepines', 'BZD', 'Psychiatry'),
            array('Antipsychotics', 'AP', 'Psychiatry'),
            array('Anticonvulsants', 'ACV', 'Neurology'),
            array('Antimalarials', 'AML', 'Anti-infective'),
            array('Bronchodilators', 'BRD', 'Respiratory'),
            array('Statins', 'STAT', 'Cardiovascular'),
        );

        foreach ($classes as $c) {
            $this->db->insert('drug_classes', array(
                'class_name' => $c[0],
                'class_code' => $c[1],
                'therapeutic_category' => $c[2],
                'is_active' => 1,
                'InActive' => 0
            ));
        }
    }

    private function create_patient_allergies_table()
    {
        if (!$this->table_exists('patient_allergies')) {
            $this->db->query("CREATE TABLE `patient_allergies` (
                `allergy_id` INT AUTO_INCREMENT PRIMARY KEY,
                `patient_no` VARCHAR(25) NOT NULL,
                `allergen_type` ENUM('DRUG','DRUG_CLASS','FOOD','ENVIRONMENTAL','OTHER') NOT NULL DEFAULT 'DRUG',
                `allergen_id` INT DEFAULT NULL,
                `allergen_name` VARCHAR(255) NOT NULL,
                `reaction_type` ENUM('MILD','MODERATE','SEVERE','ANAPHYLAXIS') NOT NULL DEFAULT 'MODERATE',
                `reaction_description` TEXT,
                `onset_date` DATE,
                `verified` TINYINT(1) DEFAULT 0,
                `verified_by` VARCHAR(25),
                `verified_at` DATETIME,
                `reported_by` VARCHAR(25),
                `reported_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `is_active` TINYINT(1) DEFAULT 1,
                `InActive` INT(1) DEFAULT 0,
                INDEX idx_patient (patient_no),
                INDEX idx_allergen (allergen_type, allergen_id),
                INDEX idx_severity (reaction_type),
                INDEX idx_active (is_active, InActive)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            if (!$this->column_exists('patient_allergies', 'is_active')) {
                $this->db->query("ALTER TABLE `patient_allergies` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
            }
        }
    }

    private function create_drug_dose_limits_table()
    {
        if (!$this->table_exists('drug_dose_limits')) {
            $this->db->query("CREATE TABLE `drug_dose_limits` (
                `limit_id` INT AUTO_INCREMENT PRIMARY KEY,
                `drug_id` INT NOT NULL,
                `min_single_dose` DECIMAL(10,3),
                `max_single_dose` DECIMAL(10,3),
                `max_daily_dose` DECIMAL(10,3),
                `dose_unit` VARCHAR(20) DEFAULT 'mg',
                `age_group` ENUM('PEDIATRIC','ADULT','GERIATRIC','ALL') DEFAULT 'ALL',
                `min_age_years` INT,
                `max_age_years` INT,
                `weight_based` TINYINT(1) DEFAULT 0,
                `dose_per_kg` DECIMAL(10,3),
                `max_dose_per_kg` DECIMAL(10,3),
                `renal_adjustment` TINYINT(1) DEFAULT 0,
                `hepatic_adjustment` TINYINT(1) DEFAULT 0,
                `notes` TEXT,
                `reference_source` VARCHAR(255),
                `is_active` TINYINT(1) DEFAULT 1,
                INDEX idx_drug (drug_id),
                INDEX idx_age (age_group),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            if (!$this->column_exists('drug_dose_limits', 'is_active')) {
                $this->db->query("ALTER TABLE `drug_dose_limits` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
            }
        }
    }

    private function create_high_risk_drugs_table()
    {
        if (!$this->table_exists('high_risk_drugs')) {
            $this->db->query("CREATE TABLE `high_risk_drugs` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `drug_id` INT NOT NULL,
                `risk_category` ENUM('NARCOTIC','CONTROLLED','CHEMOTHERAPY','ANTICOAGULANT','INSULIN','HIGH_ALERT','LASA') NOT NULL,
                `requires_double_check` TINYINT(1) DEFAULT 0,
                `requires_indication` TINYINT(1) DEFAULT 0,
                `max_quantity_per_rx` INT,
                `special_instructions` TEXT,
                `is_active` TINYINT(1) DEFAULT 1,
                UNIQUE KEY uniq_drug_risk (drug_id, risk_category),
                INDEX idx_drug (drug_id),
                INDEX idx_category (risk_category),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            if (!$this->column_exists('high_risk_drugs', 'is_active')) {
                $this->db->query("ALTER TABLE `high_risk_drugs` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
            }
        }
    }

    private function create_patient_risk_flags_table()
    {
        if (!$this->table_exists('patient_risk_flags')) {
            $this->db->query("CREATE TABLE `patient_risk_flags` (
                `flag_id` INT AUTO_INCREMENT PRIMARY KEY,
                `patient_no` VARCHAR(25) NOT NULL,
                `risk_type` VARCHAR(50) NOT NULL,
                `severity` VARCHAR(20) DEFAULT 'MODERATE',
                `description` TEXT,
                `onset_date` DATE,
                `diagnosed_by` VARCHAR(25),
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME,
                `InActive` INT(1) DEFAULT 0,
                INDEX idx_patient (patient_no),
                INDEX idx_risk (risk_type),
                INDEX idx_severity (severity),
                INDEX idx_active (is_active, InActive)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } else {
            // Ensure required columns exist on existing table
            if (!$this->column_exists('patient_risk_flags', 'risk_type')) {
                $this->db->query("ALTER TABLE `patient_risk_flags` ADD COLUMN `risk_type` VARCHAR(50) DEFAULT NULL");
            }
            if (!$this->column_exists('patient_risk_flags', 'severity')) {
                $this->db->query("ALTER TABLE `patient_risk_flags` ADD COLUMN `severity` VARCHAR(20) DEFAULT 'MODERATE'");
            }
            if (!$this->column_exists('patient_risk_flags', 'description')) {
                $this->db->query("ALTER TABLE `patient_risk_flags` ADD COLUMN `description` TEXT DEFAULT NULL");
            }
            if (!$this->column_exists('patient_risk_flags', 'is_active')) {
                $this->db->query("ALTER TABLE `patient_risk_flags` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
            }
        }
    }

    private function create_consultation_locks_table()
    {
        if ($this->table_exists('consultation_locks')) return;

        $this->db->query("CREATE TABLE `consultation_locks` (
            `lock_id` INT AUTO_INCREMENT PRIMARY KEY,
            `iop_id` VARCHAR(25) NOT NULL,
            `locked_by` VARCHAR(25) NOT NULL,
            `locked_at` DATETIME NOT NULL,
            `lock_expires_at` DATETIME NOT NULL,
            `lock_type` ENUM('EDITING','PRESCRIBING','REVIEWING') DEFAULT 'EDITING',
            `is_active` TINYINT(1) DEFAULT 1,
            UNIQUE KEY uniq_iop_lock (iop_id, lock_type, is_active),
            INDEX idx_iop (iop_id),
            INDEX idx_user (locked_by),
            INDEX idx_expires (lock_expires_at),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function create_clinical_notes_audit_table()
    {
        if ($this->table_exists('clinical_notes_audit')) return;

        $this->db->query("CREATE TABLE `clinical_notes_audit` (
            `audit_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `iop_id` VARCHAR(25) NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `note_type` ENUM('COMPLAINT','HISTORY','EXAMINATION','DIAGNOSIS','PLAN','GENERAL') NOT NULL DEFAULT 'GENERAL',
            `field_name` VARCHAR(100),
            `old_value` TEXT,
            `new_value` TEXT,
            `changed_by` VARCHAR(25) NOT NULL,
            `changed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45),
            INDEX idx_iop (iop_id),
            INDEX idx_patient (patient_no),
            INDEX idx_changed_at (changed_at),
            INDEX idx_type (note_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    private function create_prescription_safety_alerts_table()
    {
        if ($this->table_exists('prescription_safety_alerts')) return;

        $this->db->query("CREATE TABLE `prescription_safety_alerts` (
            `alert_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `iop_id` VARCHAR(25) NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `iop_med_id` INT,
            `drug_id` INT,
            `alert_type` ENUM('INTERACTION','ALLERGY','DUPLICATE','DOSE_HIGH','DOSE_LOW','HIGH_RISK','CONTRAINDICATION') NOT NULL,
            `severity` ENUM('INFO','WARNING','CRITICAL','BLOCKED') NOT NULL DEFAULT 'WARNING',
            `alert_message` TEXT NOT NULL,
            `related_drug_id` INT,
            `was_overridden` TINYINT(1) DEFAULT 0,
            `override_reason` TEXT,
            `overridden_by` VARCHAR(25),
            `overridden_at` DATETIME,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_by` VARCHAR(25),
            INDEX idx_iop (iop_id),
            INDEX idx_patient (patient_no),
            INDEX idx_type (alert_type),
            INDEX idx_severity (severity),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /* ================================================================== */
    /*  DRUG INTERACTION CHECKING                                          */
    /* ================================================================== */

    /**
     * Check for drug interactions between a new drug and existing prescriptions
     * @param int $drug_id - New drug being prescribed
     * @param string $patient_no - Patient number
     * @param string $iop_id - Current visit ID (to check active prescriptions)
     * @return array - List of interactions found
     */
    public function check_drug_interactions($drug_id, $patient_no, $iop_id = null)
    {
        $this->ensure_schema();
        $interactions = array();
        $drug_id = (int)$drug_id;
        
        if ($drug_id <= 0) return $interactions;
        
        // Get patient's active medications (current visit + recent history)
        $active_drugs = $this->get_patient_active_drugs($patient_no, $iop_id);
        
        if (empty($active_drugs)) return $interactions;
        
        // Check each active drug for interactions
        foreach ($active_drugs as $active_drug_id) {
            if ((int)$active_drug_id === $drug_id) continue; // Skip self
            
            $interaction = $this->get_interaction($drug_id, $active_drug_id);
            if ($interaction) {
                $interactions[] = $interaction;
            }
        }
        
        return $interactions;
    }

    /**
     * Get interaction between two drugs
     */
    private function get_interaction($drug_id_1, $drug_id_2)
    {
        if (!$this->table_exists('drug_interactions')) return null;
        
        $sql = "SELECT di.*, 
                       d1.drug_name as drug1_name, 
                       d2.drug_name as drug2_name
                FROM drug_interactions di
                LEFT JOIN medicine_drug_name d1 ON d1.drug_id = di.drug_id_1
                LEFT JOIN medicine_drug_name d2 ON d2.drug_id = di.drug_id_2
                WHERE di.is_active = 1
                AND ((di.drug_id_1 = ? AND di.drug_id_2 = ?)
                     OR (di.drug_id_1 = ? AND di.drug_id_2 = ?))
                LIMIT 1";
        
        $q = $this->db->query($sql, array($drug_id_1, $drug_id_2, $drug_id_2, $drug_id_1));
        return $q ? $q->row() : null;
    }

    /**
     * Add a drug interaction record
     */
    public function add_drug_interaction($drug_id_1, $drug_id_2, $severity, $description, $clinical_effect, $management, $user_id)
    {
        $this->ensure_schema();
        
        // Check if already exists
        $existing = $this->get_interaction($drug_id_1, $drug_id_2);
        if ($existing) {
            // Update existing
            $this->db->where('interaction_id', $existing->interaction_id);
            return $this->db->update('drug_interactions', array(
                'severity' => $severity,
                'description' => $description,
                'clinical_effect' => $clinical_effect,
                'management' => $management,
                'is_active' => 1
            ));
        }
        
        return $this->db->insert('drug_interactions', array(
            'drug_id_1' => (int)$drug_id_1,
            'drug_id_2' => (int)$drug_id_2,
            'severity' => $severity,
            'description' => $description,
            'clinical_effect' => $clinical_effect,
            'management' => $management,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $user_id
        ));
    }

    /* ================================================================== */
    /*  ALLERGY DETECTION                                                   */
    /* ================================================================== */

    /**
     * Check if patient is allergic to a drug or its class
     */
    public function check_drug_allergy($drug_id, $patient_no)
    {
        $this->ensure_schema();
        $alerts = array();
        $drug_id = (int)$drug_id;
        $patient_no = trim((string)$patient_no);
        
        if ($drug_id <= 0 || $patient_no === '') return $alerts;
        
        // Check direct drug allergy
        $direct = $this->check_direct_drug_allergy($drug_id, $patient_no);
        if ($direct) {
            $alerts[] = $direct;
        }
        
        // Check drug class allergy (cross-reactivity)
        $class_allergies = $this->check_drug_class_allergy($drug_id, $patient_no);
        $alerts = array_merge($alerts, $class_allergies);
        
        return $alerts;
    }

    private function check_direct_drug_allergy($drug_id, $patient_no)
    {
        if (!$this->table_exists('patient_allergies')) return null;
        
        $sql = "SELECT pa.*, COALESCE(d.drug_name, pa.allergen_name) as drug_name
                FROM patient_allergies pa
                LEFT JOIN medicine_drug_name d ON d.drug_id = pa.allergen_id
                WHERE pa.patient_no = ?
                AND pa.allergen_type = 'DRUG'
                AND pa.allergen_id = ?
                AND pa.is_active = 1
                AND pa.InActive = 0
                LIMIT 1";
        
        $q = $this->db->query($sql, array($patient_no, $drug_id));
        return $q ? $q->row() : null;
    }

    private function check_drug_class_allergy($drug_id, $patient_no)
    {
        $alerts = array();
        
        if (!$this->table_exists('patient_allergies') || 
            !$this->table_exists('drug_class_mapping')) {
            return $alerts;
        }
        
        // Get drug's classes
        $drug_classes = $this->get_drug_classes($drug_id);
        
        if (empty($drug_classes)) return $alerts;
        
        // Check if patient is allergic to any of these classes
        $class_ids = array();
        foreach ($drug_classes as $dc) {
            $class_ids[] = $dc->class_id;
        }
        
        if (empty($class_ids)) return $alerts;
        
        $this->db->select('pa.*, dc.class_name');
        $this->db->from('patient_allergies pa');
        $this->db->join('drug_classes dc', 'dc.class_id = pa.allergen_id', 'left');
        $this->db->where('pa.patient_no', $patient_no);
        $this->db->where('pa.allergen_type', 'DRUG_CLASS');
        $this->db->where_in('pa.allergen_id', $class_ids);
        $this->db->where('pa.is_active', 1);
        $this->db->where('pa.InActive', 0);
        
        $q = $this->db->get();
        return $q ? $q->result() : array();
    }

    /**
     * Add patient allergy
     */
    public function add_patient_allergy($patient_no, $allergen_type, $allergen_id, $allergen_name, $reaction_type, $reaction_description, $user_id)
    {
        $this->ensure_schema();
        
        return $this->db->insert('patient_allergies', array(
            'patient_no' => $patient_no,
            'allergen_type' => $allergen_type,
            'allergen_id' => $allergen_id,
            'allergen_name' => $allergen_name,
            'reaction_type' => $reaction_type,
            'reaction_description' => $reaction_description,
            'reported_by' => $user_id,
            'reported_at' => date('Y-m-d H:i:s'),
            'is_active' => 1,
            'InActive' => 0
        ));
    }

    /**
     * Get patient allergies
     */
    public function get_patient_allergies($patient_no)
    {
        $this->ensure_schema();
        
        if (!$this->table_exists('patient_allergies')) return array();
        
        $this->db->where('patient_no', $patient_no);
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->order_by('reaction_type', 'DESC'); // Severe first
        
        $q = $this->db->get('patient_allergies');
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  DUPLICATE THERAPY DETECTION                                         */
    /* ================================================================== */

    /**
     * Check for duplicate therapy (same drug class)
     */
    public function check_duplicate_therapy($drug_id, $patient_no, $iop_id = null)
    {
        $this->ensure_schema();
        $duplicates = array();
        $drug_id = (int)$drug_id;
        
        if ($drug_id <= 0) return $duplicates;
        
        // Get drug's therapeutic classes
        $drug_classes = $this->get_drug_classes($drug_id);
        
        if (empty($drug_classes)) return $duplicates;
        
        // Get patient's active medications
        $active_drugs = $this->get_patient_active_drugs($patient_no, $iop_id);
        
        if (empty($active_drugs)) return $duplicates;
        
        // Get drug name for new drug
        $new_drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $new_drug_name = $new_drug ? $new_drug->drug_name : 'Unknown';
        
        // Check each active drug's classes
        foreach ($active_drugs as $active_drug_id) {
            if ((int)$active_drug_id === $drug_id) continue; // Skip same drug
            
            $active_classes = $this->get_drug_classes($active_drug_id);
            
            // Get active drug name
            $active_drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $active_drug_id))->row();
            $active_drug_name = $active_drug ? $active_drug->drug_name : 'Unknown';
            
            // Find overlapping classes
            foreach ($drug_classes as $dc) {
                foreach ($active_classes as $ac) {
                    if ($dc->class_id == $ac->class_id) {
                        $duplicates[] = (object) array(
                            'new_drug_id' => $drug_id,
                            'new_drug_name' => $new_drug_name,
                            'existing_drug_id' => $active_drug_id,
                            'existing_drug_name' => $active_drug_name,
                            'class_id' => $dc->class_id,
                            'class_name' => $dc->class_name,
                            'message' => "Duplicate therapy: {$new_drug_name} and {$active_drug_name} both belong to {$dc->class_name} class"
                        );
                        break 2; // One duplicate per drug pair is enough
                    }
                }
            }
        }
        
        return $duplicates;
    }

    /* ================================================================== */
    /*  DOSE VALIDATION                                                     */
    /* ================================================================== */

    /**
     * Validate dose against limits
     */
    public function validate_dose($drug_id, $dose, $frequency, $patient_no)
    {
        $this->ensure_schema();
        $alerts = array();
        $drug_id = (int)$drug_id;
        
        if ($drug_id <= 0) return $alerts;
        
        // Get patient age for age-based dosing
        $patient = $this->get_patient_info($patient_no);
        $age = $patient ? (int)$patient->age : 30; // Default adult
        
        // Determine age group
        $age_group = 'ADULT';
        if ($age < 18) $age_group = 'PEDIATRIC';
        elseif ($age >= 65) $age_group = 'GERIATRIC';
        
        // Get dose limits
        $limits = $this->get_dose_limits($drug_id, $age_group);
        
        if (!$limits) return $alerts;
        
        // Parse dose value
        $dose_value = $this->parse_dose($dose);
        
        if ($dose_value <= 0) return $alerts;
        
        // Check single dose limits
        if ($limits->max_single_dose && $dose_value > (float)$limits->max_single_dose) {
            $alerts[] = (object) array(
                'type' => 'DOSE_HIGH',
                'severity' => 'WARNING',
                'message' => "Dose {$dose_value} exceeds maximum single dose of {$limits->max_single_dose} {$limits->dose_unit}"
            );
        }
        
        if ($limits->min_single_dose && $dose_value < (float)$limits->min_single_dose) {
            $alerts[] = (object) array(
                'type' => 'DOSE_LOW',
                'severity' => 'INFO',
                'message' => "Dose {$dose_value} is below minimum effective dose of {$limits->min_single_dose} {$limits->dose_unit}"
            );
        }
        
        // Calculate daily dose based on frequency
        $daily_multiplier = $this->get_frequency_multiplier($frequency);
        $daily_dose = $dose_value * $daily_multiplier;
        
        if ($limits->max_daily_dose && $daily_dose > (float)$limits->max_daily_dose) {
            $alerts[] = (object) array(
                'type' => 'DOSE_HIGH',
                'severity' => 'CRITICAL',
                'message' => "Daily dose {$daily_dose} {$limits->dose_unit} exceeds maximum daily dose of {$limits->max_daily_dose} {$limits->dose_unit}"
            );
        }
        
        return $alerts;
    }

    private function get_dose_limits($drug_id, $age_group)
    {
        if (!$this->table_exists('drug_dose_limits')) return null;
        
        $sql = "SELECT * FROM drug_dose_limits 
                WHERE drug_id = ? 
                AND is_active = 1
                AND age_group IN (?, 'ALL')
                ORDER BY FIELD(age_group, ?, 'ALL')
                LIMIT 1";
        
        $q = $this->db->query($sql, array($drug_id, $age_group, $age_group));
        return $q ? $q->row() : null;
    }

    /* ================================================================== */
    /*  HIGH RISK DRUG DETECTION                                            */
    /* ================================================================== */

    /**
     * Check if drug is high-risk
     */
    public function check_high_risk_drug($drug_id)
    {
        $this->ensure_schema();
        
        if (!$this->table_exists('high_risk_drugs')) return array();
        
        $sql = "SELECT hrd.*, d.drug_name
                FROM high_risk_drugs hrd
                LEFT JOIN medicine_drug_name d ON d.drug_id = hrd.drug_id
                WHERE hrd.drug_id = ?
                AND hrd.is_active = 1";
        
        $q = $this->db->query($sql, array((int)$drug_id));
        return $q ? $q->result() : array();
    }

    /**
     * Mark a drug as high-risk
     */
    public function add_high_risk_drug($drug_id, $risk_category, $requires_double_check, $requires_indication, $max_qty, $instructions)
    {
        $this->ensure_schema();
        
        return $this->db->insert('high_risk_drugs', array(
            'drug_id' => (int)$drug_id,
            'risk_category' => $risk_category,
            'requires_double_check' => $requires_double_check ? 1 : 0,
            'requires_indication' => $requires_indication ? 1 : 0,
            'max_quantity_per_rx' => $max_qty,
            'special_instructions' => $instructions,
            'is_active' => 1
        ));
    }

    /* ================================================================== */
    /*  PATIENT RISK FLAGS                                                  */
    /* ================================================================== */

    /**
     * Get patient risk flags
     */
    public function get_patient_risk_flags($patient_no)
    {
        $this->ensure_schema();
        
        if (!$this->table_exists('patient_risk_flags')) return array();
        
        $this->db->where('patient_no', $patient_no);
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->order_by('severity', 'DESC');
        
        $q = $this->db->get('patient_risk_flags');
        return $q ? $q->result() : array();
    }

    /**
     * Add patient risk flag
     */
    public function add_patient_risk_flag($patient_no, $risk_type, $severity, $description, $user_id)
    {
        $this->ensure_schema();
        
        // Check if already exists
        $existing = $this->db->get_where('patient_risk_flags', array(
            'patient_no' => $patient_no,
            'risk_type' => $risk_type,
            'is_active' => 1,
            'InActive' => 0
        ))->row();
        
        if ($existing) {
            // Update existing
            $this->db->where('flag_id', $existing->flag_id);
            return $this->db->update('patient_risk_flags', array(
                'severity' => $severity,
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s')
            ));
        }
        
        return $this->db->insert('patient_risk_flags', array(
            'patient_no' => $patient_no,
            'risk_type' => $risk_type,
            'severity' => $severity,
            'description' => $description,
            'diagnosed_by' => $user_id,
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'InActive' => 0
        ));
    }

    /* ================================================================== */
    /*  CONSULTATION LOCKING                                                */
    /* ================================================================== */

    /**
     * Acquire a consultation lock
     * @param string $iop_id - Visit ID
     * @param string $user_id - User acquiring the lock
     * @param string $lock_type - Type of lock (EDITING, PRESCRIBING, REVIEWING)
     * @param int $duration_minutes - Lock duration in minutes (default 15)
     * @return array - Result with success status and message
     */
    public function acquire_consultation_lock($iop_id, $user_id, $lock_type = 'EDITING', $duration_minutes = 15)
    {
        $this->ensure_schema();
        
        $iop_id = trim((string)$iop_id);
        $user_id = trim((string)$user_id);
        
        if ($iop_id === '' || $user_id === '') {
            return array('success' => false, 'message' => 'Invalid parameters');
        }
        
        // Clean up expired locks first
        $this->cleanup_expired_locks();
        
        // Check if already locked by another user
        $existing = $this->db->get_where('consultation_locks', array(
            'iop_id' => $iop_id,
            'lock_type' => $lock_type,
            'is_active' => 1
        ))->row();
        
        if ($existing) {
            if ($existing->locked_by === $user_id) {
                // Extend own lock
                $this->db->where('lock_id', $existing->lock_id);
                $this->db->update('consultation_locks', array(
                    'lock_expires_at' => date('Y-m-d H:i:s', strtotime("+{$duration_minutes} minutes"))
                ));
                return array('success' => true, 'message' => 'Lock extended', 'lock_id' => $existing->lock_id);
            } else {
                // Locked by someone else
                $locker = $this->get_user_name($existing->locked_by);
                return array(
                    'success' => false, 
                    'message' => "Consultation is currently being edited by {$locker}",
                    'locked_by' => $existing->locked_by,
                    'locked_by_name' => $locker,
                    'expires_at' => $existing->lock_expires_at
                );
            }
        }
        
        // Create new lock
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime("+{$duration_minutes} minutes"));
        
        $this->db->insert('consultation_locks', array(
            'iop_id' => $iop_id,
            'locked_by' => $user_id,
            'locked_at' => $now,
            'lock_expires_at' => $expires,
            'lock_type' => $lock_type,
            'is_active' => 1
        ));
        
        return array('success' => true, 'message' => 'Lock acquired', 'lock_id' => $this->db->insert_id());
    }

    /**
     * Release a consultation lock
     */
    public function release_consultation_lock($iop_id, $user_id, $lock_type = 'EDITING')
    {
        $this->ensure_schema();
        
        $this->db->where('iop_id', $iop_id);
        $this->db->where('locked_by', $user_id);
        $this->db->where('lock_type', $lock_type);
        $this->db->where('is_active', 1);
        
        return $this->db->update('consultation_locks', array('is_active' => 0));
    }

    /**
     * Check if consultation is locked
     */
    public function is_consultation_locked($iop_id, $exclude_user_id = null)
    {
        $this->ensure_schema();
        $this->cleanup_expired_locks();
        
        $this->db->where('iop_id', $iop_id);
        $this->db->where('is_active', 1);
        $this->db->where('lock_expires_at >', date('Y-m-d H:i:s'));
        
        if ($exclude_user_id) {
            $this->db->where('locked_by !=', $exclude_user_id);
        }
        
        $q = $this->db->get('consultation_locks');
        return ($q && $q->num_rows() > 0);
    }

    /**
     * Get lock info for a consultation
     */
    public function get_consultation_lock($iop_id)
    {
        $this->ensure_schema();
        $this->cleanup_expired_locks();
        
        $lock = $this->db->get_where('consultation_locks', array(
            'iop_id' => $iop_id,
            'is_active' => 1
        ))->row();
        
        if ($lock) {
            $lock->locked_by_name = $this->get_user_name($lock->locked_by);
        }
        
        return $lock;
    }

    private function cleanup_expired_locks()
    {
        if (!$this->table_exists('consultation_locks')) return;
        
        $this->db->where('lock_expires_at <', date('Y-m-d H:i:s'));
        $this->db->where('is_active', 1);
        $this->db->update('consultation_locks', array('is_active' => 0));
    }

    /* ================================================================== */
    /*  CLINICAL NOTES AUDIT                                                */
    /* ================================================================== */

    /**
     * Log a clinical note change
     */
    public function log_clinical_note_change($iop_id, $patient_no, $note_type, $field_name, $old_value, $new_value, $user_id)
    {
        $this->ensure_schema();
        
        // Don't log if values are the same
        if (trim((string)$old_value) === trim((string)$new_value)) {
            return true;
        }
        
        return $this->db->insert('clinical_notes_audit', array(
            'iop_id' => $iop_id,
            'patient_no' => $patient_no,
            'note_type' => $note_type,
            'field_name' => $field_name,
            'old_value' => $old_value,
            'new_value' => $new_value,
            'changed_by' => $user_id,
            'changed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->input->ip_address()
        ));
    }

    /**
     * Get clinical notes audit trail
     */
    public function get_clinical_notes_audit($iop_id, $limit = 50)
    {
        $this->ensure_schema();
        
        if (!$this->table_exists('clinical_notes_audit')) return array();
        
        $sql = "SELECT a.*, 
                       CONCAT(COALESCE(t.cValue,''), ' ', u.firstname, ' ', u.lastname) as changed_by_name
                FROM clinical_notes_audit a
                LEFT JOIN users u ON u.user_id = a.changed_by
                LEFT JOIN system_parameters t ON t.param_id = u.title
                WHERE a.iop_id = ?
                ORDER BY a.changed_at DESC
                LIMIT ?";
        
        $q = $this->db->query($sql, array($iop_id, (int)$limit));
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  PRESCRIPTION SAFETY ALERTS                                          */
    /* ================================================================== */

    /**
     * Log a prescription safety alert
     */
    public function log_safety_alert($iop_id, $patient_no, $iop_med_id, $drug_id, $alert_type, $severity, $message, $related_drug_id, $user_id)
    {
        $this->ensure_schema();
        
        return $this->db->insert('prescription_safety_alerts', array(
            'iop_id' => $iop_id,
            'patient_no' => $patient_no,
            'iop_med_id' => $iop_med_id,
            'drug_id' => $drug_id,
            'alert_type' => $alert_type,
            'severity' => $severity,
            'alert_message' => $message,
            'related_drug_id' => $related_drug_id,
            'was_overridden' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $user_id
        ));
    }

    /**
     * Record alert override
     */
    public function override_safety_alert($alert_id, $reason, $user_id)
    {
        $this->ensure_schema();
        
        $this->db->where('alert_id', $alert_id);
        return $this->db->update('prescription_safety_alerts', array(
            'was_overridden' => 1,
            'override_reason' => $reason,
            'overridden_by' => $user_id,
            'overridden_at' => date('Y-m-d H:i:s')
        ));
    }

    /**
     * Get safety alerts for a visit
     */
    public function get_visit_safety_alerts($iop_id)
    {
        $this->ensure_schema();
        
        if (!$this->table_exists('prescription_safety_alerts')) return array();
        
        $this->db->where('iop_id', $iop_id);
        $this->db->order_by('severity', 'ASC'); // BLOCKED first
        $this->db->order_by('created_at', 'DESC');
        
        $q = $this->db->get('prescription_safety_alerts');
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  COMPREHENSIVE PRESCRIPTION CHECK                                    */
    /* ================================================================== */

    /**
     * Run all safety checks for a prescription
     * Returns array of alerts with severity levels
     */
    public function check_prescription_safety($drug_id, $dose, $frequency, $patient_no, $iop_id = null)
    {
        $this->ensure_schema();
        $all_alerts = array();
        
        // Get drug name
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => (int)$drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : 'Unknown Drug';
        
        // 1. Drug Interactions
        $interactions = $this->check_drug_interactions($drug_id, $patient_no, $iop_id);
        foreach ($interactions as $i) {
            $interacting_drug = isset($i->drug1_name) && $i->drug_id_1 != $drug_id ? $i->drug1_name : (isset($i->drug2_name) ? $i->drug2_name : 'Unknown');
            $all_alerts[] = (object) array(
                'type' => 'INTERACTION',
                'severity' => $i->severity === 'CONTRAINDICATED' ? 'BLOCKED' : ($i->severity === 'SEVERE' ? 'CRITICAL' : 'WARNING'),
                'message' => "Drug interaction between {$drug_name} and {$interacting_drug}: " . (isset($i->description) ? $i->description : 'Potential interaction'),
                'details' => $i
            );
        }
        
        // 2. Allergies
        $allergies = $this->check_drug_allergy($drug_id, $patient_no);
        foreach ($allergies as $a) {
            $severity = (isset($a->reaction_type) && $a->reaction_type === 'ANAPHYLAXIS') ? 'BLOCKED' : 'CRITICAL';
            $allergen = isset($a->drug_name) ? $a->drug_name : (isset($a->allergen_name) ? $a->allergen_name : $drug_name);
            $reaction = isset($a->reaction_description) ? $a->reaction_description : 'Allergic reaction';
            $all_alerts[] = (object) array(
                'type' => 'ALLERGY',
                'severity' => $severity,
                'message' => "Patient allergic to {$allergen}: {$reaction}",
                'details' => $a
            );
        }
        
        // 3. Duplicate Therapy
        $duplicates = $this->check_duplicate_therapy($drug_id, $patient_no, $iop_id);
        foreach ($duplicates as $d) {
            $all_alerts[] = (object) array(
                'type' => 'DUPLICATE',
                'severity' => 'WARNING',
                'message' => $d->message,
                'details' => $d
            );
        }
        
        // 4. Dose Validation
        $dose_alerts = $this->validate_dose($drug_id, $dose, $frequency, $patient_no);
        foreach ($dose_alerts as $da) {
            $all_alerts[] = $da;
        }
        
        // 5. High Risk Drug
        $high_risk = $this->check_high_risk_drug($drug_id);
        foreach ($high_risk as $hr) {
            $instructions = isset($hr->special_instructions) ? $hr->special_instructions : 'Handle with care';
            $all_alerts[] = (object) array(
                'type' => 'HIGH_RISK',
                'severity' => 'WARNING',
                'message' => "High-risk medication ({$hr->risk_category}): {$instructions}",
                'details' => $hr
            );
        }
        
        // Sort by severity (BLOCKED > CRITICAL > WARNING > INFO)
        usort($all_alerts, function($a, $b) {
            $order = array('BLOCKED' => 0, 'CRITICAL' => 1, 'WARNING' => 2, 'INFO' => 3);
            $a_order = isset($order[$a->severity]) ? $order[$a->severity] : 4;
            $b_order = isset($order[$b->severity]) ? $order[$b->severity] : 4;
            return $a_order - $b_order;
        });
        
        return $all_alerts;
    }

    /**
     * Check if prescription should be blocked
     */
    public function should_block_prescription($alerts)
    {
        foreach ($alerts as $alert) {
            if (isset($alert->severity) && $alert->severity === 'BLOCKED') {
                return true;
            }
        }
        return false;
    }

    /**
     * Get highest severity from alerts
     */
    public function get_highest_severity($alerts)
    {
        $highest = 'INFO';
        $order = array('INFO' => 0, 'WARNING' => 1, 'CRITICAL' => 2, 'BLOCKED' => 3);
        
        foreach ($alerts as $alert) {
            if (isset($alert->severity) && isset($order[$alert->severity])) {
                if ($order[$alert->severity] > $order[$highest]) {
                    $highest = $alert->severity;
                }
            }
        }
        
        return $highest;
    }

    /* ================================================================== */
    /*  HELPER METHODS                                                      */
    /* ================================================================== */

    private function get_patient_active_drugs($patient_no, $current_iop_id = null)
    {
        $drugs = array();
        
        // Get drugs from current visit
        if ($current_iop_id) {
            $q = $this->db->select('medicine_id')
                ->where('iop_id', $current_iop_id)
                ->where('InActive', 0)
                ->get('iop_medication');
            
            if ($q) {
                foreach ($q->result() as $r) {
                    if ($r->medicine_id > 0) {
                        $drugs[$r->medicine_id] = $r->medicine_id;
                    }
                }
            }
        }
        
        // Get drugs from recent visits (last 30 days)
        $cutoff = date('Y-m-d', strtotime('-30 days'));
        $sql = "SELECT DISTINCT m.medicine_id
                FROM iop_medication m
                JOIN patient_details_iop p ON p.IO_ID = m.iop_id
                WHERE p.patient_no = ?
                AND p.date_visit >= ?
                AND m.InActive = 0
                AND m.medicine_id > 0";
        
        $q = $this->db->query($sql, array($patient_no, $cutoff));
        if ($q) {
            foreach ($q->result() as $r) {
                $drugs[$r->medicine_id] = $r->medicine_id;
            }
        }
        
        return array_values($drugs);
    }

    private function get_drug_classes($drug_id)
    {
        if (!$this->table_exists('drug_class_mapping') || !$this->table_exists('drug_classes')) {
            return array();
        }
        
        // Check if is_active column exists, add it if not
        if (!$this->column_exists('drug_classes', 'is_active')) {
            $this->db->query("ALTER TABLE `drug_classes` ADD COLUMN `is_active` TINYINT(1) DEFAULT 1");
        }
        
        $sql = "SELECT dc.*
                FROM drug_class_mapping dcm
                JOIN drug_classes dc ON dc.class_id = dcm.class_id
                WHERE dcm.drug_id = ?
                AND dc.is_active = 1
                AND dc.InActive = 0";
        
        $q = $this->db->query($sql, array($drug_id));
        return $q ? $q->result() : array();
    }

    private function get_patient_info($patient_no)
    {
        return $this->db->get_where('patient_personal_info', array(
            'patient_no' => $patient_no,
            'InActive' => 0
        ))->row();
    }

    private function parse_dose($dose)
    {
        // Extract numeric value from dose string
        preg_match('/[\d.]+/', (string)$dose, $matches);
        return isset($matches[0]) ? (float)$matches[0] : 0;
    }

    private function get_frequency_multiplier($frequency)
    {
        $freq = strtoupper((string)$frequency);
        $map = array(
            'OD' => 1, 'ONCE DAILY' => 1,
            'BD' => 2, 'TWICE DAILY' => 2, 'BID' => 2,
            'TDS' => 3, 'THREE TIMES DAILY' => 3, 'TID' => 3,
            'QDS' => 4, 'FOUR TIMES DAILY' => 4, 'QID' => 4,
            'Q4H' => 6, 'EVERY 4 HOURS' => 6,
            'Q6H' => 4, 'EVERY 6 HOURS' => 4,
            'Q8H' => 3, 'EVERY 8 HOURS' => 3,
            'Q12H' => 2, 'EVERY 12 HOURS' => 2,
            'STAT' => 1, 'PRN' => 1, 'AS NEEDED' => 1,
            'WEEKLY' => 0.14,
        );
        
        foreach ($map as $key => $val) {
            if (strpos($freq, $key) !== false) {
                return $val;
            }
        }
        
        return 1; // Default to once daily
    }

    private function get_user_name($user_id)
    {
        $sql = "SELECT CONCAT(COALESCE(t.cValue,''), ' ', u.firstname, ' ', u.lastname) as name
                FROM users u
                LEFT JOIN system_parameters t ON t.param_id = u.title
                WHERE u.user_id = ?";
        
        $q = $this->db->query($sql, array($user_id));
        $r = $q ? $q->row() : null;
        return $r ? trim($r->name) : 'Unknown User';
    }

    /**
     * Map drug to class
     */
    public function map_drug_to_class($drug_id, $class_id, $is_primary = false)
    {
        $this->ensure_schema();
        
        // Check if mapping exists
        $existing = $this->db->get_where('drug_class_mapping', array(
            'drug_id' => (int)$drug_id,
            'class_id' => (int)$class_id
        ))->row();
        
        if ($existing) {
            $this->db->where('id', $existing->id);
            return $this->db->update('drug_class_mapping', array('is_primary' => $is_primary ? 1 : 0));
        }
        
        return $this->db->insert('drug_class_mapping', array(
            'drug_id' => (int)$drug_id,
            'class_id' => (int)$class_id,
            'is_primary' => $is_primary ? 1 : 0
        ));
    }

    /**
     * Get all drug classes
     */
    public function get_all_drug_classes()
    {
        $this->ensure_schema();
        
        if (!$this->table_exists('drug_classes')) return array();
        
        $this->db->where('is_active', 1);
        $this->db->where('InActive', 0);
        $this->db->order_by('class_name', 'ASC');
        
        $q = $this->db->get('drug_classes');
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  PHASE 3.5: ADDITIONAL CLINICAL INTELLIGENCE TABLES                 */
    /* ================================================================== */

    /**
     * NHIS Drug-Diagnosis Rules Table
     * Validates drug prescriptions against diagnosis for NHIS claims
     */
    private function create_nhis_drug_diagnosis_rules_table()
    {
        if ($this->table_exists('nhis_drug_diagnosis_rules')) return;

        $this->db->query("CREATE TABLE `nhis_drug_diagnosis_rules` (
            `rule_id` INT AUTO_INCREMENT PRIMARY KEY,
            `diagnosis_code` VARCHAR(20) NOT NULL,
            `diagnosis_description` VARCHAR(255),
            `drug_id` INT NOT NULL,
            `drug_name` VARCHAR(255),
            `allowed` TINYINT(1) DEFAULT 1,
            `restriction_type` ENUM('ALLOWED','NOT_ALLOWED','REQUIRES_AUTHORIZATION','LIMITED_DURATION','LIMITED_QUANTITY') DEFAULT 'ALLOWED',
            `max_days` INT DEFAULT NULL,
            `max_quantity` INT DEFAULT NULL,
            `requires_prior_auth` TINYINT(1) DEFAULT 0,
            `notes` TEXT,
            `nhis_category` VARCHAR(50),
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_by` VARCHAR(25),
            `updated_at` DATETIME,
            INDEX idx_diag (diagnosis_code),
            INDEX idx_drug (drug_id),
            INDEX idx_restriction (restriction_type),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed some common NHIS rules
        $this->seed_nhis_rules();
    }

    /**
     * Seed common NHIS drug-diagnosis rules
     */
    private function seed_nhis_rules()
    {
        $rules = array(
            // Antibiotics require infection diagnosis
            array('J06.9', 'Acute upper respiratory infection', 'AMOXICILLIN', 'ALLOWED', 7, 21),
            array('J18.9', 'Pneumonia, unspecified', 'AMOXICILLIN', 'ALLOWED', 10, 30),
            array('N39.0', 'Urinary tract infection', 'CIPROFLOXACIN', 'ALLOWED', 7, 14),
            
            // Antihypertensives require hypertension diagnosis
            array('I10', 'Essential hypertension', 'AMLODIPINE', 'ALLOWED', 30, 30),
            array('I10', 'Essential hypertension', 'LISINOPRIL', 'ALLOWED', 30, 30),
            array('I10', 'Essential hypertension', 'ATENOLOL', 'ALLOWED', 30, 30),
            
            // Antidiabetics require diabetes diagnosis
            array('E11.9', 'Type 2 diabetes mellitus', 'METFORMIN', 'ALLOWED', 30, 60),
            array('E11.9', 'Type 2 diabetes mellitus', 'GLIBENCLAMIDE', 'ALLOWED', 30, 30),
            
            // Controlled substances - limited
            array('G43.9', 'Migraine', 'TRAMADOL', 'LIMITED_DURATION', 5, 10),
            array('M54.5', 'Low back pain', 'TRAMADOL', 'LIMITED_DURATION', 7, 14),
            
            // Not allowed combinations
            array('J06.9', 'Acute upper respiratory infection', 'METFORMIN', 'NOT_ALLOWED', null, null),
        );

        foreach ($rules as $rule) {
            // Find drug ID
            $drugResult = $this->db->query("SELECT drug_id FROM medicine_drug_name WHERE UPPER(drug_name) LIKE ? AND InActive=0 LIMIT 1", 
                array('%' . strtoupper($rule[2]) . '%'));
            $drugId = ($drugResult && $drugResult->num_rows() > 0) ? $drugResult->row()->drug_id : 0;
            
            if ($drugId > 0) {
                $this->db->insert('nhis_drug_diagnosis_rules', array(
                    'diagnosis_code' => $rule[0],
                    'diagnosis_description' => $rule[1],
                    'drug_id' => $drugId,
                    'drug_name' => $rule[2],
                    'restriction_type' => $rule[3],
                    'max_days' => $rule[4],
                    'max_quantity' => $rule[5],
                    'is_active' => 1
                ));
            }
        }
    }

    /**
     * Prescription Workflow Table
     * Tracks prescription lifecycle: PRESCRIBED -> PHARMACY_REVIEW -> APPROVED -> DISPENSED
     */
    private function create_prescription_workflow_table()
    {
        if ($this->table_exists('prescription_workflow')) return;

        $this->db->query("CREATE TABLE `prescription_workflow` (
            `workflow_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `iop_med_id` INT NOT NULL,
            `iop_id` VARCHAR(25) NOT NULL,
            `patient_no` VARCHAR(25) NOT NULL,
            `status` ENUM('PRESCRIBED','PHARMACY_REVIEW','PHARMACIST_QUERY','APPROVED','DISPENSING','DISPENSED','CANCELLED','ON_HOLD') DEFAULT 'PRESCRIBED',
            `previous_status` VARCHAR(30),
            `prescribed_by` VARCHAR(25),
            `prescribed_at` DATETIME,
            `reviewed_by` VARCHAR(25),
            `reviewed_at` DATETIME,
            `approved_by` VARCHAR(25),
            `approved_at` DATETIME,
            `dispensed_by` VARCHAR(25),
            `dispensed_at` DATETIME,
            `cancelled_by` VARCHAR(25),
            `cancelled_at` DATETIME,
            `cancel_reason` TEXT,
            `query_message` TEXT,
            `query_response` TEXT,
            `notes` TEXT,
            `updated_by` VARCHAR(25),
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_med (iop_med_id),
            INDEX idx_iop (iop_id),
            INDEX idx_patient (patient_no),
            INDEX idx_status (status),
            INDEX idx_prescribed_at (prescribed_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Drug Contraindications Table
     * Stores condition-based contraindications (pregnancy, renal, hepatic, etc.)
     */
    private function create_drug_contraindications_table()
    {
        if ($this->table_exists('drug_contraindications')) return;

        $this->db->query("CREATE TABLE `drug_contraindications` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `drug_id` INT NOT NULL,
            `drug_name` VARCHAR(255),
            `condition_type` ENUM('PREGNANCY','BREASTFEEDING','RENAL_IMPAIRMENT','HEPATIC_IMPAIRMENT','CARDIAC','PEDIATRIC','GERIATRIC','G6PD_DEFICIENCY','PORPHYRIA','MYASTHENIA_GRAVIS','OTHER') NOT NULL,
            `severity` ENUM('INFO','WARNING','CRITICAL','BLOCKED') DEFAULT 'WARNING',
            `trimester` VARCHAR(20) DEFAULT NULL COMMENT 'For pregnancy: 1ST, 2ND, 3RD, ALL',
            `gfr_threshold` INT DEFAULT NULL COMMENT 'For renal: eGFR below this triggers alert',
            `description` TEXT NOT NULL,
            `alternative_drugs` TEXT,
            `reference_source` VARCHAR(255),
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `created_by` VARCHAR(25),
            INDEX idx_drug (drug_id),
            INDEX idx_condition (condition_type),
            INDEX idx_severity (severity),
            INDEX idx_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Seed common contraindications
        $this->seed_contraindications();
    }

    /**
     * Seed common drug contraindications
     */
    private function seed_contraindications()
    {
        $contraindications = array(
            // Pregnancy contraindications
            array('METHOTREXATE', 'PREGNANCY', 'BLOCKED', 'ALL', 'Teratogenic - causes severe birth defects. Absolutely contraindicated.'),
            array('WARFARIN', 'PREGNANCY', 'BLOCKED', '1ST', 'Warfarin embryopathy in first trimester. Use LMWH instead.'),
            array('ISOTRETINOIN', 'PREGNANCY', 'BLOCKED', 'ALL', 'Severe teratogen - iPLEDGE program required.'),
            array('MISOPROSTOL', 'PREGNANCY', 'BLOCKED', 'ALL', 'Uterotonic - causes abortion. Contraindicated unless for termination.'),
            array('NSAID', 'PREGNANCY', 'WARNING', '3RD', 'May cause premature ductus arteriosus closure in third trimester.'),
            array('ACE', 'PREGNANCY', 'BLOCKED', '2ND', 'Fetotoxic - causes oligohydramnios, renal dysgenesis.'),
            
            // Renal impairment
            array('METFORMIN', 'RENAL_IMPAIRMENT', 'CRITICAL', null, 'Lactic acidosis risk. Contraindicated if eGFR < 30.'),
            array('GENTAMICIN', 'RENAL_IMPAIRMENT', 'CRITICAL', null, 'Nephrotoxic and accumulates. Dose adjustment required.'),
            array('NSAID', 'RENAL_IMPAIRMENT', 'WARNING', null, 'Reduces renal blood flow. Avoid in CKD stage 4-5.'),
            array('LITHIUM', 'RENAL_IMPAIRMENT', 'CRITICAL', null, 'Renally cleared - toxicity risk. Reduce dose.'),
            
            // Hepatic impairment
            array('PARACETAMOL', 'HEPATIC_IMPAIRMENT', 'WARNING', null, 'Hepatotoxic metabolite. Max 2g/day in liver disease.'),
            array('METHOTREXATE', 'HEPATIC_IMPAIRMENT', 'BLOCKED', null, 'Hepatotoxic - contraindicated in liver disease.'),
            array('STATIN', 'HEPATIC_IMPAIRMENT', 'WARNING', null, 'Monitor LFTs. Contraindicated in active liver disease.'),
            
            // G6PD deficiency
            array('PRIMAQUINE', 'G6PD_DEFICIENCY', 'BLOCKED', null, 'Causes severe hemolysis in G6PD deficiency.'),
            array('DAPSONE', 'G6PD_DEFICIENCY', 'BLOCKED', null, 'Causes hemolysis. Screen before use.'),
            array('SULFONAMIDE', 'G6PD_DEFICIENCY', 'CRITICAL', null, 'Risk of hemolytic anemia.'),
            
            // Pediatric
            array('ASPIRIN', 'PEDIATRIC', 'BLOCKED', null, 'Reye syndrome risk in children with viral illness.'),
            array('TETRACYCLINE', 'PEDIATRIC', 'BLOCKED', null, 'Causes permanent tooth discoloration under age 8.'),
            array('FLUOROQUINOLONE', 'PEDIATRIC', 'WARNING', null, 'Cartilage toxicity risk. Use only when no alternative.'),
            
            // Geriatric
            array('BENZODIAZEPINE', 'GERIATRIC', 'WARNING', null, 'Fall risk, cognitive impairment. Use lowest dose.'),
            array('ANTICHOLINERGIC', 'GERIATRIC', 'WARNING', null, 'Delirium, urinary retention, constipation risk.'),
        );

        foreach ($contraindications as $ci) {
            // Find matching drugs
            $drugResult = $this->db->query("SELECT drug_id, drug_name FROM medicine_drug_name WHERE UPPER(drug_name) LIKE ? AND InActive=0", 
                array('%' . strtoupper($ci[0]) . '%'));
            
            if ($drugResult && $drugResult->num_rows() > 0) {
                foreach ($drugResult->result() as $drug) {
                    $this->db->insert('drug_contraindications', array(
                        'drug_id' => $drug->drug_id,
                        'drug_name' => $drug->drug_name,
                        'condition_type' => $ci[1],
                        'severity' => $ci[2],
                        'trimester' => $ci[3],
                        'description' => $ci[4],
                        'is_active' => 1
                    ));
                }
            }
        }
    }

    /**
     * Clinical Override Audit Table
     * Tracks when doctors override safety alerts
     */
    private function create_clinical_override_audit_table()
    {
        if ($this->table_exists('clinical_override_audit')) return;

        $this->db->query("CREATE TABLE `clinical_override_audit` (
            `override_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `alert_id` BIGINT,
            `iop_id` VARCHAR(25) NOT NULL,
            `iop_med_id` INT,
            `patient_no` VARCHAR(25) NOT NULL,
            `drug_id` INT,
            `drug_name` VARCHAR(255),
            `alert_type` VARCHAR(50) NOT NULL,
            `alert_severity` VARCHAR(20) NOT NULL,
            `alert_message` TEXT,
            `override_reason` TEXT NOT NULL,
            `doctor_id` VARCHAR(25) NOT NULL,
            `doctor_name` VARCHAR(255),
            `supervisor_id` VARCHAR(25),
            `supervisor_approved` TINYINT(1) DEFAULT 0,
            `supervisor_approved_at` DATETIME,
            `patient_informed` TINYINT(1) DEFAULT 0,
            `informed_witness` VARCHAR(255),
            `overridden_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `ip_address` VARCHAR(45),
            INDEX idx_iop (iop_id),
            INDEX idx_patient (patient_no),
            INDEX idx_doctor (doctor_id),
            INDEX idx_alert_type (alert_type),
            INDEX idx_severity (alert_severity),
            INDEX idx_date (overridden_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Clinical Decision Cache Table
     * Caches safety check results for performance
     */
    private function create_clinical_decision_cache_table()
    {
        if ($this->table_exists('clinical_decision_cache')) return;

        $this->db->query("CREATE TABLE `clinical_decision_cache` (
            `cache_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `cache_key` VARCHAR(100) NOT NULL,
            `patient_no` VARCHAR(25),
            `drug_id` INT,
            `iop_id` VARCHAR(25),
            `alerts_json` JSON,
            `alert_count` INT DEFAULT 0,
            `highest_severity` VARCHAR(20),
            `is_blocked` TINYINT(1) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `expires_at` DATETIME NOT NULL,
            UNIQUE KEY uniq_cache_key (cache_key),
            INDEX idx_patient (patient_no),
            INDEX idx_drug (drug_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /**
     * Prescription Templates Tables
     * Allows doctors to save and reuse prescription templates
     */
    private function create_prescription_templates_tables()
    {
        if (!$this->table_exists('prescription_templates')) {
            $this->db->query("CREATE TABLE `prescription_templates` (
                `template_id` INT AUTO_INCREMENT PRIMARY KEY,
                `template_name` VARCHAR(255) NOT NULL,
                `template_code` VARCHAR(50),
                `diagnosis_code` VARCHAR(20),
                `diagnosis_description` VARCHAR(255),
                `department_id` INT,
                `specialty` VARCHAR(100),
                `description` TEXT,
                `is_nhis_compliant` TINYINT(1) DEFAULT 1,
                `usage_count` INT DEFAULT 0,
                `is_public` TINYINT(1) DEFAULT 0 COMMENT 'Available to all doctors',
                `created_by` VARCHAR(25) NOT NULL,
                `is_active` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME,
                INDEX idx_name (template_name),
                INDEX idx_diagnosis (diagnosis_code),
                INDEX idx_creator (created_by),
                INDEX idx_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$this->table_exists('prescription_template_items')) {
            $this->db->query("CREATE TABLE `prescription_template_items` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `template_id` INT NOT NULL,
                `drug_id` INT NOT NULL,
                `drug_name` VARCHAR(255),
                `dose` VARCHAR(50),
                `dose_unit` VARCHAR(20) DEFAULT 'mg',
                `frequency` VARCHAR(50),
                `duration` VARCHAR(50),
                `duration_days` INT,
                `quantity` INT,
                `route` VARCHAR(30) DEFAULT 'ORAL',
                `instructions` TEXT,
                `is_prn` TINYINT(1) DEFAULT 0,
                `sequence_order` INT DEFAULT 0,
                `is_active` TINYINT(1) DEFAULT 1,
                INDEX idx_template (template_id),
                INDEX idx_drug (drug_id),
                FOREIGN KEY (template_id) REFERENCES prescription_templates(template_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        // Seed some common templates
        $this->seed_prescription_templates();
    }

    /**
     * Seed common prescription templates
     */
    private function seed_prescription_templates()
    {
        // Check if templates already exist
        $existing = $this->db->get_where('prescription_templates', array('is_active' => 1))->num_rows();
        if ($existing > 0) return;

        $templates = array(
            array(
                'name' => 'Acute Upper Respiratory Infection',
                'code' => 'URTI_STANDARD',
                'diagnosis' => 'J06.9',
                'items' => array(
                    array('AMOXICILLIN', '500', 'TDS (Three times daily)', 7),
                    array('PARACETAMOL', '1000', 'TDS (Three times daily)', 5),
                    array('VITAMIN C', '500', 'OD (Once daily)', 7),
                )
            ),
            array(
                'name' => 'Uncomplicated Malaria',
                'code' => 'MALARIA_UNCOM',
                'diagnosis' => 'B50.9',
                'items' => array(
                    array('ARTEMETHER', '80', 'BD (Twice daily)', 3),
                    array('PARACETAMOL', '1000', 'TDS (Three times daily)', 3),
                )
            ),
            array(
                'name' => 'Essential Hypertension - Initial',
                'code' => 'HTN_INITIAL',
                'diagnosis' => 'I10',
                'items' => array(
                    array('AMLODIPINE', '5', 'OD (Once daily)', 30),
                )
            ),
            array(
                'name' => 'Type 2 Diabetes - Initial',
                'code' => 'DM2_INITIAL',
                'diagnosis' => 'E11.9',
                'items' => array(
                    array('METFORMIN', '500', 'BD (Twice daily)', 30),
                )
            ),
            array(
                'name' => 'Urinary Tract Infection',
                'code' => 'UTI_STANDARD',
                'diagnosis' => 'N39.0',
                'items' => array(
                    array('CIPROFLOXACIN', '500', 'BD (Twice daily)', 7),
                )
            ),
        );

        foreach ($templates as $tpl) {
            $this->db->insert('prescription_templates', array(
                'template_name' => $tpl['name'],
                'template_code' => $tpl['code'],
                'diagnosis_code' => $tpl['diagnosis'],
                'is_public' => 1,
                'created_by' => 'SYSTEM',
                'is_active' => 1
            ));
            $templateId = $this->db->insert_id();

            foreach ($tpl['items'] as $idx => $item) {
                // Find drug
                $drugResult = $this->db->query("SELECT drug_id, drug_name FROM medicine_drug_name WHERE UPPER(drug_name) LIKE ? AND InActive=0 LIMIT 1", 
                    array('%' . strtoupper($item[0]) . '%'));
                
                if ($drugResult && $drugResult->num_rows() > 0) {
                    $drug = $drugResult->row();
                    $this->db->insert('prescription_template_items', array(
                        'template_id' => $templateId,
                        'drug_id' => $drug->drug_id,
                        'drug_name' => $drug->drug_name,
                        'dose' => $item[1],
                        'frequency' => $item[2],
                        'duration_days' => $item[3],
                        'sequence_order' => $idx + 1,
                        'is_active' => 1
                    ));
                }
            }
        }
    }

    /* ================================================================== */
    /*  NHIS CLAIM INTELLIGENCE METHODS                                    */
    /* ================================================================== */

    /**
     * Check if drug is allowed for diagnosis under NHIS
     */
    public function check_nhis_drug_diagnosis($drug_id, $diagnosis_code)
    {
        $this->ensure_schema();
        if (!$this->table_exists('nhis_drug_diagnosis_rules')) return null;

        $this->db->where('drug_id', (int)$drug_id);
        $this->db->where('diagnosis_code', trim($diagnosis_code));
        $this->db->where('is_active', 1);
        
        $q = $this->db->get('nhis_drug_diagnosis_rules');
        return $q ? $q->row() : null;
    }

    /**
     * Validate prescription for NHIS compliance
     */
    public function validate_nhis_prescription($drug_id, $diagnosis_code, $days, $quantity)
    {
        $alerts = array();
        $rule = $this->check_nhis_drug_diagnosis($drug_id, $diagnosis_code);

        if ($rule) {
            if ($rule->restriction_type === 'NOT_ALLOWED') {
                $alerts[] = (object)array(
                    'type' => 'NHIS_NOT_ALLOWED',
                    'severity' => 'BLOCKED',
                    'message' => "This drug is not covered by NHIS for diagnosis {$diagnosis_code}. Claim will be rejected.",
                    'details' => $rule
                );
            } elseif ($rule->restriction_type === 'REQUIRES_AUTHORIZATION') {
                $alerts[] = (object)array(
                    'type' => 'NHIS_AUTH_REQUIRED',
                    'severity' => 'WARNING',
                    'message' => "This drug requires prior NHIS authorization for diagnosis {$diagnosis_code}.",
                    'details' => $rule
                );
            } elseif ($rule->restriction_type === 'LIMITED_DURATION') {
                if ($rule->max_days && $days > $rule->max_days) {
                    $alerts[] = (object)array(
                        'type' => 'NHIS_DURATION_EXCEEDED',
                        'severity' => 'WARNING',
                        'message' => "NHIS limits this drug to {$rule->max_days} days for this diagnosis. You prescribed {$days} days.",
                        'details' => $rule
                    );
                }
            } elseif ($rule->restriction_type === 'LIMITED_QUANTITY') {
                if ($rule->max_quantity && $quantity > $rule->max_quantity) {
                    $alerts[] = (object)array(
                        'type' => 'NHIS_QUANTITY_EXCEEDED',
                        'severity' => 'WARNING',
                        'message' => "NHIS limits this drug to {$rule->max_quantity} units. You prescribed {$quantity}.",
                        'details' => $rule
                    );
                }
            }
        }

        return $alerts;
    }

    /* ================================================================== */
    /*  PRESCRIPTION WORKFLOW METHODS                                      */
    /* ================================================================== */

    /**
     * Initialize prescription workflow when medication is saved
     */
    public function init_prescription_workflow($iop_med_id, $iop_id, $patient_no, $prescribed_by)
    {
        $this->ensure_schema();
        if (!$this->table_exists('prescription_workflow')) return false;

        // Check if workflow already exists
        $existing = $this->db->get_where('prescription_workflow', array('iop_med_id' => (int)$iop_med_id))->row();
        if ($existing) return $existing->workflow_id;

        $this->db->insert('prescription_workflow', array(
            'iop_med_id' => (int)$iop_med_id,
            'iop_id' => $iop_id,
            'patient_no' => $patient_no,
            'status' => 'PRESCRIBED',
            'prescribed_by' => $prescribed_by,
            'prescribed_at' => date('Y-m-d H:i:s'),
            'updated_by' => $prescribed_by
        ));

        return $this->db->insert_id();
    }

    /**
     * Update prescription workflow status
     */
    public function update_prescription_workflow($iop_med_id, $new_status, $user_id, $notes = '')
    {
        $this->ensure_schema();
        if (!$this->table_exists('prescription_workflow')) return false;

        $workflow = $this->db->get_where('prescription_workflow', array('iop_med_id' => (int)$iop_med_id))->row();
        if (!$workflow) return false;

        $update = array(
            'previous_status' => $workflow->status,
            'status' => $new_status,
            'updated_by' => $user_id,
            'updated_at' => date('Y-m-d H:i:s')
        );

        if ($notes) $update['notes'] = $notes;

        // Set specific timestamps based on status
        switch ($new_status) {
            case 'PHARMACY_REVIEW':
                $update['reviewed_by'] = $user_id;
                $update['reviewed_at'] = date('Y-m-d H:i:s');
                break;
            case 'APPROVED':
                $update['approved_by'] = $user_id;
                $update['approved_at'] = date('Y-m-d H:i:s');
                break;
            case 'DISPENSED':
                $update['dispensed_by'] = $user_id;
                $update['dispensed_at'] = date('Y-m-d H:i:s');
                break;
            case 'CANCELLED':
                $update['cancelled_by'] = $user_id;
                $update['cancelled_at'] = date('Y-m-d H:i:s');
                $update['cancel_reason'] = $notes;
                break;
        }

        $this->db->where('workflow_id', $workflow->workflow_id);
        return $this->db->update('prescription_workflow', $update);
    }

    /**
     * Get prescription workflow status
     */
    public function get_prescription_workflow($iop_med_id)
    {
        $this->ensure_schema();
        if (!$this->table_exists('prescription_workflow')) return null;

        return $this->db->get_where('prescription_workflow', array('iop_med_id' => (int)$iop_med_id))->row();
    }

    /* ================================================================== */
    /*  CONTRAINDICATION CHECKING METHODS                                  */
    /* ================================================================== */

    /**
     * Check drug contraindications for patient
     */
    public function check_contraindications($drug_id, $patient_no)
    {
        $this->ensure_schema();
        $alerts = array();

        if (!$this->table_exists('drug_contraindications')) return $alerts;

        // Get patient risk flags
        $risk_flags = $this->get_patient_risk_flags($patient_no);
        
        // Get drug contraindications
        $this->db->where('drug_id', (int)$drug_id);
        $this->db->where('is_active', 1);
        $contras = $this->db->get('drug_contraindications')->result();

        foreach ($contras as $ci) {
            $triggered = false;
            
            foreach ($risk_flags as $flag) {
                // Map risk flag types to contraindication condition types
                $mapping = array(
                    'PREGNANCY' => 'PREGNANCY',
                    'RENAL_IMPAIRMENT' => 'RENAL_IMPAIRMENT',
                    'HEPATIC_IMPAIRMENT' => 'HEPATIC_IMPAIRMENT',
                    'CARDIAC' => 'CARDIAC',
                    'G6PD_DEFICIENCY' => 'G6PD_DEFICIENCY'
                );

                if (isset($mapping[$flag->risk_type]) && $mapping[$flag->risk_type] === $ci->condition_type) {
                    $triggered = true;
                    break;
                }
            }

            if ($triggered) {
                $alerts[] = (object)array(
                    'type' => 'CONTRAINDICATION',
                    'severity' => $ci->severity,
                    'message' => "Contraindicated in {$ci->condition_type}: {$ci->description}",
                    'details' => (object)array(
                        'condition_type' => $ci->condition_type,
                        'drug_id' => $ci->drug_id,
                        'alternative_drugs' => $ci->alternative_drugs
                    )
                );
            }
        }

        return $alerts;
    }

    /* ================================================================== */
    /*  CLINICAL OVERRIDE AUDIT METHODS                                    */
    /* ================================================================== */

    /**
     * Log clinical override
     */
    public function log_clinical_override($data)
    {
        $this->ensure_schema();
        if (!$this->table_exists('clinical_override_audit')) return false;

        $insert = array(
            'alert_id' => isset($data['alert_id']) ? $data['alert_id'] : null,
            'iop_id' => $data['iop_id'],
            'iop_med_id' => isset($data['iop_med_id']) ? $data['iop_med_id'] : null,
            'patient_no' => $data['patient_no'],
            'drug_id' => isset($data['drug_id']) ? $data['drug_id'] : null,
            'drug_name' => isset($data['drug_name']) ? $data['drug_name'] : null,
            'alert_type' => $data['alert_type'],
            'alert_severity' => $data['alert_severity'],
            'alert_message' => isset($data['alert_message']) ? $data['alert_message'] : null,
            'override_reason' => $data['override_reason'],
            'doctor_id' => $data['doctor_id'],
            'doctor_name' => $this->get_user_name($data['doctor_id']),
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            'overridden_at' => date('Y-m-d H:i:s')
        );

        return $this->db->insert('clinical_override_audit', $insert);
    }

    /**
     * Get override history for patient
     */
    public function get_patient_override_history($patient_no, $limit = 50)
    {
        $this->ensure_schema();
        if (!$this->table_exists('clinical_override_audit')) return array();

        $this->db->where('patient_no', $patient_no);
        $this->db->order_by('overridden_at', 'DESC');
        $this->db->limit($limit);

        return $this->db->get('clinical_override_audit')->result();
    }

    /* ================================================================== */
    /*  CLINICAL DECISION CACHE METHODS                                    */
    /* ================================================================== */

    /**
     * Get cached safety check result
     */
    public function get_cached_safety_check($patient_no, $drug_id, $iop_id)
    {
        $this->ensure_schema();
        if (!$this->table_exists('clinical_decision_cache')) return null;

        $cache_key = md5("safety_{$patient_no}_{$drug_id}_{$iop_id}");

        $this->db->where('cache_key', $cache_key);
        $this->db->where('expires_at >', date('Y-m-d H:i:s'));

        $cached = $this->db->get('clinical_decision_cache')->row();
        
        if ($cached && $cached->alerts_json) {
            return json_decode($cached->alerts_json);
        }

        return null;
    }

    /**
     * Cache safety check result
     */
    public function cache_safety_check($patient_no, $drug_id, $iop_id, $alerts, $ttl_minutes = 30)
    {
        $this->ensure_schema();
        if (!$this->table_exists('clinical_decision_cache')) return false;

        $cache_key = md5("safety_{$patient_no}_{$drug_id}_{$iop_id}");
        $highest_severity = $this->get_highest_severity($alerts);
        $is_blocked = $this->should_block_prescription($alerts);

        // Delete existing cache
        $this->db->where('cache_key', $cache_key);
        $this->db->delete('clinical_decision_cache');

        // Insert new cache
        return $this->db->insert('clinical_decision_cache', array(
            'cache_key' => $cache_key,
            'patient_no' => $patient_no,
            'drug_id' => (int)$drug_id,
            'iop_id' => $iop_id,
            'alerts_json' => json_encode($alerts),
            'alert_count' => count($alerts),
            'highest_severity' => $highest_severity,
            'is_blocked' => $is_blocked ? 1 : 0,
            'expires_at' => date('Y-m-d H:i:s', strtotime("+{$ttl_minutes} minutes"))
        ));
    }

    /**
     * Clear expired cache entries
     */
    public function clear_expired_cache()
    {
        $this->ensure_schema();
        if (!$this->table_exists('clinical_decision_cache')) return;

        $this->db->where('expires_at <', date('Y-m-d H:i:s'));
        $this->db->delete('clinical_decision_cache');
    }

    /* ================================================================== */
    /*  PRESCRIPTION TEMPLATE METHODS                                      */
    /* ================================================================== */

    /**
     * Get prescription templates
     */
    public function get_prescription_templates($user_id = null, $diagnosis_code = null)
    {
        $this->ensure_schema();
        if (!$this->table_exists('prescription_templates')) return array();

        $this->db->where('is_active', 1);
        
        if ($diagnosis_code) {
            $this->db->where('diagnosis_code', $diagnosis_code);
        }

        // Get public templates or user's own templates
        if ($user_id) {
            $this->db->group_start();
            $this->db->where('is_public', 1);
            $this->db->or_where('created_by', $user_id);
            $this->db->group_end();
        } else {
            $this->db->where('is_public', 1);
        }

        $this->db->order_by('usage_count', 'DESC');
        
        return $this->db->get('prescription_templates')->result();
    }

    /**
     * Get template items
     */
    public function get_template_items($template_id)
    {
        $this->ensure_schema();
        if (!$this->table_exists('prescription_template_items')) return array();

        $this->db->where('template_id', (int)$template_id);
        $this->db->where('is_active', 1);
        $this->db->order_by('sequence_order', 'ASC');

        return $this->db->get('prescription_template_items')->result();
    }

    /**
     * Apply prescription template
     */
    public function apply_template($template_id, $iop_id, $patient_no, $user_id)
    {
        $this->ensure_schema();
        
        $template = $this->db->get_where('prescription_templates', array('template_id' => (int)$template_id))->row();
        if (!$template) return false;

        $items = $this->get_template_items($template_id);
        $results = array();

        foreach ($items as $item) {
            $results[] = array(
                'drug_id' => $item->drug_id,
                'drug_name' => $item->drug_name,
                'dose' => $item->dose,
                'frequency' => $item->frequency,
                'duration_days' => $item->duration_days,
                'quantity' => $item->quantity,
                'instructions' => $item->instructions
            );
        }

        // Increment usage count
        $this->db->where('template_id', $template_id);
        $this->db->set('usage_count', 'usage_count + 1', false);
        $this->db->update('prescription_templates');

        return $results;
    }

    /**
     * Create new prescription template
     */
    public function create_template($data, $items, $user_id)
    {
        $this->ensure_schema();
        if (!$this->table_exists('prescription_templates')) return false;

        $this->db->insert('prescription_templates', array(
            'template_name' => $data['name'],
            'template_code' => isset($data['code']) ? $data['code'] : null,
            'diagnosis_code' => isset($data['diagnosis_code']) ? $data['diagnosis_code'] : null,
            'diagnosis_description' => isset($data['diagnosis_description']) ? $data['diagnosis_description'] : null,
            'description' => isset($data['description']) ? $data['description'] : null,
            'is_public' => isset($data['is_public']) ? $data['is_public'] : 0,
            'created_by' => $user_id,
            'is_active' => 1
        ));

        $template_id = $this->db->insert_id();

        foreach ($items as $idx => $item) {
            $this->db->insert('prescription_template_items', array(
                'template_id' => $template_id,
                'drug_id' => $item['drug_id'],
                'drug_name' => isset($item['drug_name']) ? $item['drug_name'] : null,
                'dose' => isset($item['dose']) ? $item['dose'] : null,
                'frequency' => isset($item['frequency']) ? $item['frequency'] : null,
                'duration_days' => isset($item['duration_days']) ? $item['duration_days'] : null,
                'quantity' => isset($item['quantity']) ? $item['quantity'] : null,
                'instructions' => isset($item['instructions']) ? $item['instructions'] : null,
                'sequence_order' => $idx + 1,
                'is_active' => 1
            ));
        }

        return $template_id;
    }

    /* ================================================================== */
    /*  ENHANCED PRESCRIPTION SAFETY CHECK (with new features)             */
    /* ================================================================== */

    /**
     * Enhanced prescription safety check including contraindications and NHIS
     */
    public function check_prescription_safety_enhanced($drug_id, $dose, $frequency, $patient_no, $iop_id, $diagnosis_code = null, $days = null, $quantity = null)
    {
        $this->ensure_schema();
        $alerts = array();

        // Check cache first
        $cached = $this->get_cached_safety_check($patient_no, $drug_id, $iop_id);
        if ($cached !== null) {
            return $cached;
        }

        // 1. Original safety checks
        $base_alerts = $this->check_prescription_safety($drug_id, $dose, $frequency, $patient_no, $iop_id);
        $alerts = array_merge($alerts, $base_alerts);

        // 2. Contraindication checks
        $contra_alerts = $this->check_contraindications($drug_id, $patient_no);
        $alerts = array_merge($alerts, $contra_alerts);

        // 3. NHIS compliance checks (if diagnosis provided)
        if ($diagnosis_code) {
            $nhis_alerts = $this->validate_nhis_prescription($drug_id, $diagnosis_code, $days, $quantity);
            $alerts = array_merge($alerts, $nhis_alerts);
        }

        // Cache the result
        $this->cache_safety_check($patient_no, $drug_id, $iop_id, $alerts);

        return $alerts;
    }

    /**
     * Get severity score for prioritization
     */
    public function get_severity_score($severity)
    {
        $scores = array(
            'BLOCKED' => 100,
            'CRITICAL' => 80,
            'WARNING' => 50,
            'INFO' => 10
        );
        return isset($scores[$severity]) ? $scores[$severity] : 0;
    }

    /**
     * Sort alerts by severity score
     */
    public function sort_alerts_by_severity($alerts)
    {
        usort($alerts, function($a, $b) {
            return $this->get_severity_score($b->severity) - $this->get_severity_score($a->severity);
        });
        return $alerts;
    }

    /* ================================================================== */
    /*  PHASE 3 CRITICAL ENHANCEMENTS                                      */
    /* ================================================================== */

    public function ensure_phase3_enhancements()
    {
        $this->ensure_schema();
        $this->create_drug_duration_limits_table();
        $this->create_drug_pregnancy_category_table();
        $this->create_drug_renal_adjustments_table();
        $this->create_allergy_cross_sensitivity_table();
        $this->create_clinical_decision_log_table();
        $this->add_prescription_workflow_columns();
        $this->add_consultation_lock_columns();
    }

    /* ENHANCEMENT 1: DUPLICATE MEDICATION DETECTION */
    public function check_duplicate_medication($drug_id, $patient_no, $iop_id)
    {
        $this->ensure_schema();
        $alerts = array();
        $drug_id = (int)$drug_id;
        if ($drug_id <= 0) return $alerts;

        $new_drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
        if (!$new_drug) return $alerts;

        $new_generic = isset($new_drug->generic_name) ? strtoupper(trim($new_drug->generic_name)) : '';

        $current_meds = $this->db->select('m.*, d.drug_name, d.generic_name')
            ->from('iop_medication m')
            ->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left')
            ->where('m.iop_id', $iop_id)
            ->where('m.InActive', 0)
            ->where('m.medicine_id !=', $drug_id)
            ->get()->result();

        foreach ($current_meds as $med) {
            $existing_generic = isset($med->generic_name) ? strtoupper(trim($med->generic_name)) : '';

            if ((int)$med->medicine_id === $drug_id) {
                $alerts[] = (object)array('type' => 'DUPLICATE_EXACT', 'severity' => 'BLOCKED',
                    'message' => "DUPLICATE: {$new_drug->drug_name} is already prescribed.");
                continue;
            }

            if ($new_generic !== '' && $existing_generic !== '' && $new_generic === $existing_generic) {
                $alerts[] = (object)array('type' => 'DUPLICATE_GENERIC', 'severity' => 'BLOCKED',
                    'message' => "DUPLICATE GENERIC: {$new_drug->drug_name} has same active ingredient ({$new_generic}) as {$med->drug_name}.");
            }
        }

        $class_duplicates = $this->check_duplicate_therapy($drug_id, $patient_no, $iop_id);
        foreach ($class_duplicates as $dup) {
            $alerts[] = (object)array('type' => 'DUPLICATE_CLASS', 'severity' => 'WARNING', 'message' => $dup->message, 'details' => $dup);
        }

        return $alerts;
    }

    /* ENHANCEMENT 2: DOSE RANGE SAFETY VALIDATION */
    public function validate_dose_enhanced($drug_id, $dose, $frequency, $patient_no)
    {
        $this->ensure_schema();
        $alerts = array();
        $drug_id = (int)$drug_id;
        if ($drug_id <= 0) return $alerts;

        $patient = $this->get_patient_info($patient_no);
        $age = $patient ? $this->calculate_patient_age($patient) : 30;
        $age_group = ($age < 12) ? 'PEDIATRIC' : (($age >= 65) ? 'GERIATRIC' : 'ADULT');

        $limits = $this->get_dose_limits($drug_id, $age_group);
        if (!$limits) return $alerts;

        $dose_value = $this->parse_dose($dose);
        if ($dose_value <= 0) return $alerts;

        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : 'Unknown';

        if ($limits->max_single_dose && $dose_value > (float)$limits->max_single_dose) {
            $over_pct = (($dose_value - $limits->max_single_dose) / $limits->max_single_dose) * 100;
            $alerts[] = (object)array('type' => 'DOSE_EXCEEDS_MAX', 'severity' => ($over_pct > 50 ? 'BLOCKED' : 'CRITICAL'),
                'message' => "OVERDOSE RISK: {$drug_name} dose {$dose_value}{$limits->dose_unit} exceeds max {$limits->max_single_dose}{$limits->dose_unit} for {$age_group}.");
        }

        if ($limits->min_single_dose && $dose_value < (float)$limits->min_single_dose) {
            $alerts[] = (object)array('type' => 'DOSE_BELOW_MIN', 'severity' => 'WARNING',
                'message' => "SUBTHERAPEUTIC: {$drug_name} dose below minimum effective dose.");
        }

        $daily_dose = $dose_value * $this->get_frequency_multiplier($frequency);
        if ($limits->max_daily_dose && $daily_dose > (float)$limits->max_daily_dose) {
            $alerts[] = (object)array('type' => 'DAILY_DOSE_EXCEEDS_MAX', 'severity' => 'CRITICAL',
                'message' => "DAILY OVERDOSE: {$drug_name} daily dose {$daily_dose}{$limits->dose_unit} exceeds max {$limits->max_daily_dose}{$limits->dose_unit}/day.");
        }

        return $alerts;
    }

    private function calculate_patient_age($patient)
    {
        if (!$patient) return 30;
        if (isset($patient->age) && $patient->age) return (int)$patient->age;
        $dob = isset($patient->birthday) ? $patient->birthday : (isset($patient->dob) ? $patient->dob : null);
        if ($dob) { $birth = new DateTime($dob); return (new DateTime())->diff($birth)->y; }
        return 30;
    }

    /* ENHANCEMENT 3: DURATION SAFETY VALIDATION */
    private function create_drug_duration_limits_table()
    {
        if ($this->table_exists('drug_duration_limits')) return;
        $this->db->query("CREATE TABLE `drug_duration_limits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `drug_id` INT DEFAULT NULL,
            `drug_class_id` INT DEFAULT NULL,
            `drug_class_name` VARCHAR(100),
            `min_days` INT DEFAULT NULL,
            `max_days` INT DEFAULT NULL,
            `is_controlled` TINYINT(1) DEFAULT 0,
            `controlled_max_days` INT DEFAULT 5,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_drug (drug_id),
            INDEX idx_class (drug_class_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->seed_duration_limits();
    }

    private function seed_duration_limits()
    {
        $limits = array(
            array('Antibiotics', 3, 14, 0), array('Penicillins', 5, 14, 0), array('Fluoroquinolones', 3, 14, 0),
            array('Opioids', 1, 5, 1), array('Benzodiazepines', 1, 5, 1), array('NSAIDs', 1, 7, 0),
            array('Proton Pump Inhibitors', 1, 14, 0), array('Corticosteroids', 1, 7, 0)
        );
        foreach ($limits as $l) {
            $class = $this->db->get_where('drug_classes', array('class_name' => $l[0], 'InActive' => 0))->row();
            $this->db->insert('drug_duration_limits', array(
                'drug_class_id' => $class ? $class->class_id : null, 'drug_class_name' => $l[0],
                'min_days' => $l[1], 'max_days' => $l[2], 'is_controlled' => $l[3],
                'controlled_max_days' => $l[3] ? 5 : null, 'is_active' => 1
            ));
        }
    }

    public function validate_duration($drug_id, $duration_days)
    {
        $this->ensure_phase3_enhancements();
        $alerts = array();
        $drug_id = (int)$drug_id;
        $duration_days = (int)$duration_days;
        if ($drug_id <= 0 || $duration_days <= 0) return $alerts;

        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : 'Unknown';
        $drug_classes = $this->get_drug_classes($drug_id);
        $class_ids = array_map(function($dc) { return $dc->class_id; }, $drug_classes);

        if (!$this->table_exists('drug_duration_limits')) return $alerts;

        $this->db->where('is_active', 1);
        if (!empty($class_ids)) {
            $this->db->group_start();
            $this->db->where('drug_id', $drug_id);
            $this->db->or_where_in('drug_class_id', $class_ids);
            $this->db->group_end();
        } else {
            $this->db->where('drug_id', $drug_id);
        }
        $limits = $this->db->get('drug_duration_limits')->result();

        foreach ($limits as $limit) {
            if ($limit->is_controlled && $duration_days > $limit->controlled_max_days) {
                $alerts[] = (object)array('type' => 'DURATION_CONTROLLED_EXCEEDED', 'severity' => 'BLOCKED',
                    'message' => "CONTROLLED SUBSTANCE: {$drug_name} cannot exceed {$limit->controlled_max_days} days. Prescribed: {$duration_days} days.");
            } elseif ($limit->min_days && $duration_days < $limit->min_days) {
                $alerts[] = (object)array('type' => 'DURATION_TOO_SHORT', 'severity' => 'WARNING',
                    'message' => "INSUFFICIENT DURATION: {$drug_name} typically requires at least {$limit->min_days} days.");
            } elseif ($limit->max_days && $duration_days > $limit->max_days) {
                $alerts[] = (object)array('type' => 'DURATION_TOO_LONG', 'severity' => 'WARNING',
                    'message' => "EXCESSIVE DURATION: {$drug_name} should not exceed {$limit->max_days} days.");
            }
        }
        return $alerts;
    }

    /* ENHANCEMENT 4: PREGNANCY-SPECIFIC INTELLIGENCE */
    private function create_drug_pregnancy_category_table()
    {
        if ($this->table_exists('drug_pregnancy_category')) return;
        $this->db->query("CREATE TABLE `drug_pregnancy_category` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `drug_id` INT NOT NULL,
            `drug_name` VARCHAR(255),
            `category` ENUM('A','B','C','D','X') NOT NULL,
            `trimester_specific` VARCHAR(20) DEFAULT 'ALL',
            `description` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_drug (drug_id),
            INDEX idx_category (category)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->seed_pregnancy_categories();
    }

    private function seed_pregnancy_categories()
    {
        $cats = array(
            array('METHOTREXATE', 'X', 'Teratogenic - causes fetal death'),
            array('ISOTRETINOIN', 'X', 'Severe teratogen'),
            array('WARFARIN', 'X', 'Warfarin embryopathy'),
            array('MISOPROSTOL', 'X', 'Causes abortion'),
            array('PHENYTOIN', 'D', 'Fetal hydantoin syndrome'),
            array('VALPROIC', 'D', 'Neural tube defects'),
            array('LITHIUM', 'D', 'Ebstein anomaly risk'),
            array('TETRACYCLINE', 'D', 'Tooth discoloration'),
            array('LISINOPRIL', 'D', 'ACE inhibitor fetotoxicity'),
            array('IBUPROFEN', 'D', 'Avoid in third trimester'),
            array('OMEPRAZOLE', 'C', 'Use if clearly needed'),
            array('METFORMIN', 'C', 'May be used in gestational diabetes'),
            array('PARACETAMOL', 'B', 'Safe in pregnancy'),
            array('AMOXICILLIN', 'B', 'Safe in pregnancy'),
        );
        foreach ($cats as $c) {
            $drugs = $this->db->query("SELECT drug_id, drug_name FROM medicine_drug_name WHERE UPPER(drug_name) LIKE ? AND InActive=0 LIMIT 3", array('%'.strtoupper($c[0]).'%'));
            if ($drugs && $drugs->num_rows() > 0) {
                foreach ($drugs->result() as $d) {
                    $this->db->insert('drug_pregnancy_category', array('drug_id' => $d->drug_id, 'drug_name' => $d->drug_name, 'category' => $c[1], 'description' => $c[2], 'is_active' => 1));
                }
            }
        }
    }

    public function check_pregnancy_safety($drug_id, $patient_no)
    {
        $this->ensure_phase3_enhancements();
        $alerts = array();
        if (!$this->patient_has_risk_flag($patient_no, 'PREGNANCY')) return $alerts;
        if (!$this->table_exists('drug_pregnancy_category')) return $alerts;

        $preg_cat = $this->db->get_where('drug_pregnancy_category', array('drug_id' => (int)$drug_id, 'is_active' => 1))->row();
        if (!$preg_cat) return $alerts;

        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : $preg_cat->drug_name;

        if ($preg_cat->category === 'X') {
            $alerts[] = (object)array('type' => 'PREGNANCY_CATEGORY_X', 'severity' => 'BLOCKED',
                'message' => "PREGNANCY CONTRAINDICATED: {$drug_name} is Category X - absolutely contraindicated. {$preg_cat->description}");
        } elseif ($preg_cat->category === 'D') {
            $alerts[] = (object)array('type' => 'PREGNANCY_CATEGORY_D', 'severity' => 'CRITICAL',
                'message' => "PREGNANCY RISK: {$drug_name} is Category D - evidence of fetal risk. {$preg_cat->description}");
        } elseif ($preg_cat->category === 'C') {
            $alerts[] = (object)array('type' => 'PREGNANCY_CATEGORY_C', 'severity' => 'WARNING',
                'message' => "PREGNANCY CAUTION: {$drug_name} is Category C - use only if benefits outweigh risks.");
        }
        return $alerts;
    }

    private function patient_has_risk_flag($patient_no, $risk_type)
    {
        if (!$this->table_exists('patient_risk_flags')) return false;
        return $this->db->get_where('patient_risk_flags', array('patient_no' => $patient_no, 'risk_type' => $risk_type, 'is_active' => 1, 'InActive' => 0))->row() !== null;
    }

    /* ENHANCEMENT 5: RENAL DOSE ADJUSTMENT ENGINE */
    private function create_drug_renal_adjustments_table()
    {
        if ($this->table_exists('drug_renal_adjustments')) return;
        $this->db->query("CREATE TABLE `drug_renal_adjustments` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `drug_id` INT NOT NULL,
            `drug_name` VARCHAR(255),
            `egfr_min` INT NOT NULL,
            `egfr_max` INT DEFAULT NULL,
            `action` ENUM('REDUCE_DOSE','EXTEND_INTERVAL','AVOID','CONTRAINDICATED','MONITOR') NOT NULL,
            `recommended_dose` VARCHAR(100),
            `notes` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_drug (drug_id),
            INDEX idx_egfr (egfr_min)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->seed_renal_adjustments();
    }

    private function seed_renal_adjustments()
    {
        $adjs = array(
            array('METFORMIN', 30, 45, 'REDUCE_DOSE', '500mg BD', 'Reduce dose by 50%'),
            array('METFORMIN', 0, 30, 'CONTRAINDICATED', null, 'Contraindicated - lactic acidosis risk'),
            array('GENTAMICIN', 30, 60, 'EXTEND_INTERVAL', null, 'Extend interval to q24-48h'),
            array('GENTAMICIN', 0, 30, 'AVOID', null, 'Avoid - high nephrotoxicity'),
            array('CIPROFLOXACIN', 30, 60, 'REDUCE_DOSE', '250-500mg BD', 'Reduce dose by 50%'),
            array('IBUPROFEN', 30, 60, 'AVOID', null, 'Avoid NSAIDs in moderate CKD'),
            array('IBUPROFEN', 0, 30, 'CONTRAINDICATED', null, 'Contraindicated in severe CKD'),
            array('GABAPENTIN', 30, 60, 'REDUCE_DOSE', '200-700mg/day', 'Reduce dose based on eGFR'),
            array('DIGOXIN', 30, 60, 'REDUCE_DOSE', '62.5-125mcg OD', 'Reduce dose and monitor'),
        );
        foreach ($adjs as $a) {
            $drugs = $this->db->query("SELECT drug_id, drug_name FROM medicine_drug_name WHERE UPPER(drug_name) LIKE ? AND InActive=0 LIMIT 2", array('%'.strtoupper($a[0]).'%'));
            if ($drugs && $drugs->num_rows() > 0) {
                foreach ($drugs->result() as $d) {
                    $this->db->insert('drug_renal_adjustments', array('drug_id' => $d->drug_id, 'drug_name' => $d->drug_name, 'egfr_min' => $a[1], 'egfr_max' => $a[2], 'action' => $a[3], 'recommended_dose' => $a[4], 'notes' => $a[5], 'is_active' => 1));
                }
            }
        }
    }

    public function check_renal_adjustment($drug_id, $patient_no)
    {
        $this->ensure_phase3_enhancements();
        $alerts = array();
        if (!$this->patient_has_risk_flag($patient_no, 'RENAL_IMPAIRMENT')) return $alerts;
        if (!$this->table_exists('drug_renal_adjustments')) return $alerts;

        $flag = $this->db->get_where('patient_risk_flags', array('patient_no' => $patient_no, 'risk_type' => 'RENAL_IMPAIRMENT', 'is_active' => 1))->row();
        $egfr = 30;
        if ($flag && preg_match('/eGFR\s*[:=]?\s*(\d+)/i', $flag->description, $m)) $egfr = (int)$m[1];

        $adj = $this->db->query("SELECT * FROM drug_renal_adjustments WHERE drug_id = ? AND is_active = 1 AND egfr_min <= ? AND (egfr_max IS NULL OR egfr_max >= ?) ORDER BY egfr_min DESC LIMIT 1", array((int)$drug_id, $egfr, $egfr))->row();
        if (!$adj) return $alerts;

        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : $adj->drug_name;

        $severity = ($adj->action === 'CONTRAINDICATED') ? 'BLOCKED' : (($adj->action === 'AVOID') ? 'CRITICAL' : 'WARNING');
        $rec = $adj->recommended_dose ? " Recommended: {$adj->recommended_dose}" : '';
        $alerts[] = (object)array('type' => 'RENAL_ADJUSTMENT', 'severity' => $severity,
            'message' => "RENAL ({$adj->action}): {$drug_name} with eGFR {$egfr}.{$rec} {$adj->notes}");
        return $alerts;
    }

    /* ENHANCEMENT 6: ALLERGY CROSS-SENSITIVITY DETECTION */
    private function create_allergy_cross_sensitivity_table()
    {
        if ($this->table_exists('allergy_cross_sensitivity')) return;
        $this->db->query("CREATE TABLE `allergy_cross_sensitivity` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `allergy_group` VARCHAR(100) NOT NULL,
            `cross_reactive_class_name` VARCHAR(100),
            `cross_reactivity_percent` INT DEFAULT 10,
            `severity` ENUM('INFO','WARNING','CRITICAL','BLOCKED') DEFAULT 'WARNING',
            `description` TEXT,
            `is_active` TINYINT(1) DEFAULT 1,
            INDEX idx_allergy (allergy_group)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $this->seed_cross_sensitivities();
    }

    private function seed_cross_sensitivities()
    {
        $cross = array(
            array('PENICILLIN', 'Cephalosporins', 10, 'WARNING', '~10% cross-reactivity with cephalosporins'),
            array('PENICILLIN', 'Carbapenems', 5, 'WARNING', 'Low cross-reactivity (~5%)'),
            array('SULFONAMIDE', 'Thiazide Diuretics', 15, 'WARNING', 'May react to thiazides'),
            array('ASPIRIN', 'NSAIDs', 30, 'CRITICAL', 'High cross-reactivity - bronchospasm risk'),
            array('CEPHALOSPORIN', 'Penicillins', 5, 'WARNING', 'May react to penicillins'),
            array('ERYTHROMYCIN', 'Azithromycin', 50, 'CRITICAL', 'High cross-reactivity within macrolides'),
        );
        foreach ($cross as $c) {
            $this->db->insert('allergy_cross_sensitivity', array('allergy_group' => $c[0], 'cross_reactive_class_name' => $c[1], 'cross_reactivity_percent' => $c[2], 'severity' => $c[3], 'description' => $c[4], 'is_active' => 1));
        }
    }

    public function check_allergy_cross_sensitivity($drug_id, $patient_no)
    {
        $this->ensure_phase3_enhancements();
        $alerts = array();
        $allergies = $this->get_patient_allergies($patient_no);
        if (empty($allergies)) return $alerts;
        if (!$this->table_exists('allergy_cross_sensitivity')) return $alerts;

        $drug_classes = $this->get_drug_classes($drug_id);
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $drug_name = $drug ? $drug->drug_name : 'Unknown';

        foreach ($allergies as $allergy) {
            $allergen = strtoupper($allergy->allergen_name);
            $cross_list = $this->db->query("SELECT * FROM allergy_cross_sensitivity WHERE is_active = 1 AND UPPER(allergy_group) LIKE ?", array('%'.$allergen.'%'))->result();

            foreach ($cross_list as $cs) {
                $is_cross = false;
                foreach ($drug_classes as $dc) {
                    if (stripos($dc->class_name, $cs->cross_reactive_class_name) !== false) { $is_cross = true; break; }
                }
                if (!$is_cross && stripos($drug_name, $cs->cross_reactive_class_name) !== false) $is_cross = true;

                if ($is_cross) {
                    $alerts[] = (object)array('type' => 'ALLERGY_CROSS_SENSITIVITY', 'severity' => $cs->severity,
                        'message' => "CROSS-SENSITIVITY: Patient allergic to {$allergy->allergen_name}. {$drug_name} has {$cs->cross_reactivity_percent}% cross-reactivity. {$cs->description}");
                }
            }
        }
        return $alerts;
    }

    /* ENHANCEMENT 7: CONSULTATION LOCK HARDENING */
    private function add_consultation_lock_columns()
    {
        if (!$this->table_exists('consultation_locks')) return;
        if (!$this->column_exists('consultation_locks', 'session_id'))
            $this->db->query("ALTER TABLE `consultation_locks` ADD COLUMN `session_id` VARCHAR(128) DEFAULT NULL");
        if (!$this->column_exists('consultation_locks', 'force_unlocked_by'))
            $this->db->query("ALTER TABLE `consultation_locks` ADD COLUMN `force_unlocked_by` VARCHAR(25) DEFAULT NULL");
        if (!$this->column_exists('consultation_locks', 'force_unlocked_at'))
            $this->db->query("ALTER TABLE `consultation_locks` ADD COLUMN `force_unlocked_at` DATETIME DEFAULT NULL");
        if (!$this->column_exists('consultation_locks', 'force_unlock_reason'))
            $this->db->query("ALTER TABLE `consultation_locks` ADD COLUMN `force_unlock_reason` TEXT DEFAULT NULL");
    }

    public function force_unlock_consultation($iop_id, $admin_user_id, $reason)
    {
        $this->ensure_phase3_enhancements();
        $lock = $this->db->get_where('consultation_locks', array('iop_id' => $iop_id, 'is_active' => 1))->row();
        if (!$lock) return array('success' => false, 'message' => 'No active lock found');

        $this->db->where('lock_id', $lock->lock_id);
        $this->db->update('consultation_locks', array('is_active' => 0, 'force_unlocked_by' => $admin_user_id, 'force_unlocked_at' => date('Y-m-d H:i:s'), 'force_unlock_reason' => $reason));
        return array('success' => true, 'message' => 'Consultation unlocked');
    }

    public function cleanup_session_expired_locks()
    {
        if (!$this->table_exists('consultation_locks')) return;
        $this->db->where('locked_at <', date('Y-m-d H:i:s', strtotime('-30 minutes')));
        $this->db->where('is_active', 1);
        $this->db->update('consultation_locks', array('is_active' => 0));
    }

    /* ENHANCEMENT 8: PRESCRIPTION WORKFLOW HARDENING */
    private function add_prescription_workflow_columns()
    {
        if (!$this->table_exists('prescription_workflow')) return;
        if (!$this->column_exists('prescription_workflow', 'edit_count'))
            $this->db->query("ALTER TABLE `prescription_workflow` ADD COLUMN `edit_count` INT DEFAULT 0");
        if (!$this->column_exists('prescription_workflow', 'last_edited_by'))
            $this->db->query("ALTER TABLE `prescription_workflow` ADD COLUMN `last_edited_by` VARCHAR(25) DEFAULT NULL");
        if (!$this->column_exists('prescription_workflow', 'last_edited_at'))
            $this->db->query("ALTER TABLE `prescription_workflow` ADD COLUMN `last_edited_at` DATETIME DEFAULT NULL");
        if (!$this->column_exists('prescription_workflow', 'edit_locked'))
            $this->db->query("ALTER TABLE `prescription_workflow` ADD COLUMN `edit_locked` TINYINT(1) DEFAULT 0");
    }

    public function can_edit_prescription($iop_med_id)
    {
        $workflow = $this->get_prescription_workflow($iop_med_id);
        if (!$workflow) return array('can_edit' => true, 'reason' => null);
        $locked_statuses = array('APPROVED', 'DISPENSING', 'DISPENSED', 'CANCELLED');
        if (in_array($workflow->status, $locked_statuses))
            return array('can_edit' => false, 'reason' => "Cannot edit after status: {$workflow->status}", 'status' => $workflow->status);
        if (isset($workflow->edit_locked) && $workflow->edit_locked)
            return array('can_edit' => false, 'reason' => 'Prescription locked for editing');
        return array('can_edit' => true, 'reason' => null, 'status' => $workflow->status);
    }

    public function log_prescription_edit($iop_med_id, $user_id, $changes)
    {
        $this->ensure_phase3_enhancements();
        if ($this->table_exists('prescription_workflow')) {
            $this->db->where('iop_med_id', (int)$iop_med_id);
            $this->db->set('edit_count', 'edit_count + 1', false);
            $this->db->update('prescription_workflow', array('last_edited_by' => $user_id, 'last_edited_at' => date('Y-m-d H:i:s')));
        }
        $this->log_clinical_decision(array('decision_type' => 'PRESCRIPTION_EDIT', 'iop_med_id' => $iop_med_id, 'user_id' => $user_id, 'details' => json_encode($changes)));
        return true;
    }

    /* ENHANCEMENT 9: CLINICAL DECISION LOGGING */
    private function create_clinical_decision_log_table()
    {
        if ($this->table_exists('clinical_decision_log')) return;
        $this->db->query("CREATE TABLE `clinical_decision_log` (
            `log_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `decision_type` VARCHAR(50) NOT NULL,
            `iop_id` VARCHAR(25),
            `iop_med_id` INT,
            `patient_no` VARCHAR(25),
            `drug_id` INT,
            `drug_name` VARCHAR(255),
            `doctor_id` VARCHAR(25),
            `alerts_triggered` TEXT,
            `alert_count` INT DEFAULT 0,
            `highest_severity` VARCHAR(20),
            `was_blocked` TINYINT(1) DEFAULT 0,
            `override_used` TINYINT(1) DEFAULT 0,
            `override_reason` TEXT,
            `details` TEXT,
            `ip_address` VARCHAR(45),
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_iop (iop_id),
            INDEX idx_patient (patient_no),
            INDEX idx_doctor (doctor_id),
            INDEX idx_type (decision_type),
            INDEX idx_date (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function log_clinical_decision($data)
    {
        $this->ensure_phase3_enhancements();
        if (!$this->table_exists('clinical_decision_log')) return false;
        $insert = array(
            'decision_type' => isset($data['decision_type']) ? $data['decision_type'] : 'UNKNOWN',
            'iop_id' => isset($data['iop_id']) ? $data['iop_id'] : null,
            'iop_med_id' => isset($data['iop_med_id']) ? $data['iop_med_id'] : null,
            'patient_no' => isset($data['patient_no']) ? $data['patient_no'] : null,
            'drug_id' => isset($data['drug_id']) ? $data['drug_id'] : null,
            'drug_name' => isset($data['drug_name']) ? $data['drug_name'] : null,
            'doctor_id' => isset($data['user_id']) ? $data['user_id'] : (isset($data['doctor_id']) ? $data['doctor_id'] : null),
            'alerts_triggered' => isset($data['alerts']) ? json_encode($data['alerts']) : null,
            'alert_count' => isset($data['alert_count']) ? $data['alert_count'] : 0,
            'highest_severity' => isset($data['highest_severity']) ? $data['highest_severity'] : null,
            'was_blocked' => isset($data['was_blocked']) ? ($data['was_blocked'] ? 1 : 0) : 0,
            'override_used' => isset($data['override_used']) ? ($data['override_used'] ? 1 : 0) : 0,
            'override_reason' => isset($data['override_reason']) ? $data['override_reason'] : null,
            'details' => isset($data['details']) ? $data['details'] : null,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null,
            'created_at' => date('Y-m-d H:i:s')
        );
        return $this->db->insert('clinical_decision_log', $insert);
    }

    public function get_patient_decision_history($patient_no, $limit = 50)
    {
        if (!$this->table_exists('clinical_decision_log')) return array();
        $this->db->where('patient_no', $patient_no);
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get('clinical_decision_log')->result();
    }

    /* ENHANCEMENT 10: COMPREHENSIVE SAFETY CHECK WITH ALL ENHANCEMENTS */
    public function check_prescription_safety_full($drug_id, $dose, $frequency, $duration_days, $patient_no, $iop_id, $diagnosis_code = null)
    {
        $this->ensure_phase3_enhancements();
        $all_alerts = array();

        // 1. Duplicate medication check
        $dup_alerts = $this->check_duplicate_medication($drug_id, $patient_no, $iop_id);
        $all_alerts = array_merge($all_alerts, $dup_alerts);

        // 2. Enhanced dose validation
        $dose_alerts = $this->validate_dose_enhanced($drug_id, $dose, $frequency, $patient_no);
        $all_alerts = array_merge($all_alerts, $dose_alerts);

        // 3. Duration validation
        if ($duration_days > 0) {
            $dur_alerts = $this->validate_duration($drug_id, $duration_days);
            $all_alerts = array_merge($all_alerts, $dur_alerts);
        }

        // 4. Pregnancy safety
        $preg_alerts = $this->check_pregnancy_safety($drug_id, $patient_no);
        $all_alerts = array_merge($all_alerts, $preg_alerts);

        // 5. Renal adjustment
        $renal_alerts = $this->check_renal_adjustment($drug_id, $patient_no);
        $all_alerts = array_merge($all_alerts, $renal_alerts);

        // 6. Allergy cross-sensitivity
        $cross_alerts = $this->check_allergy_cross_sensitivity($drug_id, $patient_no);
        $all_alerts = array_merge($all_alerts, $cross_alerts);

        // 7. Original safety checks (interactions, allergies, high-risk)
        $base_alerts = $this->check_prescription_safety($drug_id, $dose, $frequency, $patient_no, $iop_id);
        $all_alerts = array_merge($all_alerts, $base_alerts);

        // 8. Contraindications
        $contra_alerts = $this->check_contraindications($drug_id, $patient_no);
        $all_alerts = array_merge($all_alerts, $contra_alerts);

        // 9. NHIS compliance (if diagnosis provided)
        if ($diagnosis_code) {
            $nhis_alerts = $this->validate_nhis_prescription($drug_id, $diagnosis_code, $duration_days, null);
            $all_alerts = array_merge($all_alerts, $nhis_alerts);
        }

        // Remove duplicates and sort by severity
        $all_alerts = $this->sort_alerts_by_severity($all_alerts);

        // Log the decision
        $drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        $this->log_clinical_decision(array(
            'decision_type' => 'PRESCRIPTION_SAFETY_CHECK',
            'iop_id' => $iop_id,
            'patient_no' => $patient_no,
            'drug_id' => $drug_id,
            'drug_name' => $drug ? $drug->drug_name : null,
            'alerts' => $all_alerts,
            'alert_count' => count($all_alerts),
            'highest_severity' => $this->get_highest_severity($all_alerts),
            'was_blocked' => $this->should_block_prescription($all_alerts)
        ));

        return $all_alerts;
    }

    /**
     * AJAX endpoint helper - returns JSON-ready safety check
     */
    public function check_prescription_safety_json($drug_id, $dose, $frequency, $duration_days, $patient_no, $iop_id, $diagnosis_code = null)
    {
        $alerts = $this->check_prescription_safety_full($drug_id, $dose, $frequency, $duration_days, $patient_no, $iop_id, $diagnosis_code);

        return array(
            'success' => true,
            'alerts' => $alerts,
            'alert_count' => count($alerts),
            'highest_severity' => $this->get_highest_severity($alerts),
            'is_blocked' => $this->should_block_prescription($alerts),
            'can_proceed' => !$this->should_block_prescription($alerts),
            'requires_override' => count($alerts) > 0 && !$this->should_block_prescription($alerts)
        );
    }
}
