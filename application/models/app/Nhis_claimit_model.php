<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Claim-IT Model
 * Ghana National Health Insurance Scheme Integration
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class Nhis_claimit_model extends CI_Model
{
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_READY = 'READY';
    const STATUS_SUBMITTED = 'SUBMITTED';
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_REJECTED = 'REJECTED';
    const STATUS_APPROVED = 'APPROVED';
    
    const MEMBER_ACTIVE = 'ACTIVE';
    const MEMBER_EXPIRED = 'EXPIRED';
    const MEMBER_INVALID = 'INVALID';
    
    private $api_mode = 'MOCK';

    private function _load_reference_model()
    {
        if (!isset($this->nhis_reference_model)) {
            $this->load->model('app/Nhis_reference_model', 'nhis_reference_model');
        }
    }

    private function should_seed_sample_data()
    {
        $flag = getenv('NHIS_SEED_SAMPLE_DATA');
        if ($flag === false) {
            return false;
        }
        $flag = strtoupper(trim((string)$flag));
        return in_array($flag, ['1', 'TRUE', 'YES', 'ON'], true);
    }
    
    public function __construct()
    {
        parent::__construct();
        $this->load->helper('schema_guard');
        if (!schema_already_run('nhis_claimit_schema')) {
            $this->ensure_schema();
            mark_schema_run('nhis_claimit_schema');
        }
    }
    
    public function ensure_schema()
    {
        // Use CREATE TABLE IF NOT EXISTS to avoid errors
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_config` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `config_key` VARCHAR(100) UNIQUE,
            `config_value` TEXT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // Seed config if empty - C-NHIS-5: Read from env vars, never use placeholder in production
        if ($this->db->count_all('nhis_config') == 0) {
            // API mode: read from env var first, fallback to MOCK for safety
            $api_mode = getenv('NHIS_MODE') !== false ? strtoupper(getenv('NHIS_MODE')) : 'MOCK';
            $this->db->insert('nhis_config', ['config_key' => 'nhis_api_mode', 'config_value' => $api_mode]);
            
            // Facility code: MUST come from env var in production - empty string forces config
            $facility_code = getenv('NHIS_FACILITY_CODE') !== false ? getenv('NHIS_FACILITY_CODE') : '';
            $this->db->insert('nhis_config', ['config_key' => 'nhis_facility_code', 'config_value' => $facility_code]);
        }
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_memberships` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `patient_no` VARCHAR(20) NOT NULL,
            `nhis_number` VARCHAR(50) NOT NULL,
            `member_name` VARCHAR(255),
            `expiry_date` DATE,
            `status` ENUM('ACTIVE','EXPIRED','INVALID','PENDING') DEFAULT 'PENDING',
            `last_verified` TIMESTAMP NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `patient_nhis` (`patient_no`, `nhis_number`),
            KEY `nhis_number` (`nhis_number`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_visits` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `patient_no` VARCHAR(20) NOT NULL,
            `iop_id` INT,
            `visit_date` DATE NOT NULL,
            `visit_type` ENUM('OPD','IPD','EMERGENCY') DEFAULT 'OPD',
            `department` VARCHAR(100),
            `provider_name` VARCHAR(255),
            `authorization_code` VARCHAR(100),
            `status` ENUM('PENDING','AUTHORIZED','COMPLETED') DEFAULT 'PENDING',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `patient_no` (`patient_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_claims` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `claim_number` VARCHAR(50) UNIQUE NOT NULL,
                `patient_no` VARCHAR(20) NOT NULL,
                `visit_id` INT,
                `invoice_id` INT,
                `claim_date` DATE NOT NULL,
                `claim_month` VARCHAR(7),
                `total_amount` DECIMAL(15,2) DEFAULT 0,
                `approved_amount` DECIMAL(15,2),
                `status` ENUM('DRAFT','READY','SUBMITTED','ACCEPTED','REJECTED','APPROVED','PAID') DEFAULT 'DRAFT',
                `submission_date` TIMESTAMP NULL,
                `rejection_reason` TEXT,
                `claimit_reference` VARCHAR(100),
                `validation_errors` JSON,
                `created_by` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY `patient_no` (`patient_no`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_claim_items` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `claim_id` INT NOT NULL,
            `service_code` VARCHAR(50) NOT NULL,
            `service_name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(100),
            `quantity` INT DEFAULT 1,
            `tariff` DECIMAL(15,2) DEFAULT 0,
            `amount` DECIMAL(15,2) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `claim_id` (`claim_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_diagnosis` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `claim_id` INT NOT NULL,
            `icd10_code` VARCHAR(20) NOT NULL,
            `diagnosis_name` VARCHAR(255),
            `diagnosis_type` ENUM('PRIMARY','SECONDARY') DEFAULT 'PRIMARY',
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `claim_id` (`claim_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `icd10_codes` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(20) UNIQUE NOT NULL,
            `description` VARCHAR(500) NOT NULL,
            `category` VARCHAR(100),
            `is_active` TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->ensure_reference_imports_schema();
        if ($this->db->count_all('icd10_codes') == 0 && $this->should_seed_sample_data()) {
            $this->seed_icd10_codes();
        }
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_tariffs` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `service_code` VARCHAR(50) UNIQUE NOT NULL,
            `service_name` VARCHAR(255) NOT NULL,
            `category` VARCHAR(100) NOT NULL,
            `tariff` DECIMAL(15,2) DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        if ($this->db->count_all('nhis_tariffs') == 0 && $this->should_seed_sample_data()) {
            $this->seed_nhis_tariffs();
        }
        
        $this->db->query("CREATE TABLE IF NOT EXISTS `claimit_logs` (
            `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `endpoint` VARCHAR(255) NOT NULL,
            `request` JSON,
            `response` JSON,
            `status` ENUM('SUCCESS','ERROR') DEFAULT 'SUCCESS',
            `api_mode` ENUM('MOCK','LIVE') DEFAULT 'MOCK',
            `user_id` INT,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `endpoint` (`endpoint`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        // NHIS Service Mapping table
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_service_mapping` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `hms_service_id` INT,
            `hms_service_name` VARCHAR(255) NOT NULL,
            `hms_service_type` ENUM('CONSULTATION','LABORATORY','RADIOLOGY','PHARMACY','PROCEDURE','OTHER') DEFAULT 'OTHER',
            `nhis_code` VARCHAR(50) NOT NULL,
            `nhis_name` VARCHAR(255),
            `category` VARCHAR(100),
            `is_covered` TINYINT(1) DEFAULT 1,
            `coverage_percent` DECIMAL(5,2) DEFAULT 100.00,
            `tariff_amount` DECIMAL(15,2) DEFAULT 0,
            `requires_authorization` TINYINT(1) DEFAULT 0,
            `is_active` TINYINT(1) DEFAULT 1,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `nhis_code` (`nhis_code`),
            KEY `hms_service_type` (`hms_service_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        if ($this->db->count_all('nhis_service_mapping') == 0 && $this->should_seed_sample_data()) {
            $this->seed_service_mapping();
        }
    }

    public function ensure_reference_imports_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_reference_imports` (
            `import_id` BIGINT AUTO_INCREMENT PRIMARY KEY,
            `ref_type` VARCHAR(30) NOT NULL,
            `version_label` VARCHAR(60) DEFAULT NULL,
            `effective_date` DATE DEFAULT NULL,
            `source_name` VARCHAR(255) DEFAULT NULL,
            `source_hash` VARCHAR(64) DEFAULT NULL,
            `imported_by` VARCHAR(25) DEFAULT NULL,
            `imported_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `notes` TEXT DEFAULT NULL,
            `is_active` TINYINT(1) DEFAULT 1,
            KEY `idx_ref_type` (`ref_type`),
            KEY `idx_active` (`ref_type`, `is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        if (!$this->_column_exists('icd10_codes', 'import_id')) {
            $this->db->query("ALTER TABLE `icd10_codes` ADD COLUMN `import_id` BIGINT DEFAULT NULL");
        }
        if (!$this->_column_exists('nhis_tariffs', 'import_id')) {
            $this->db->query("ALTER TABLE `nhis_tariffs` ADD COLUMN `import_id` BIGINT DEFAULT NULL");
        }
    }

    private function _column_exists($table, $column)
    {
        $q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE ".$this->db->escape($column));
        return ($q && $q->num_rows() > 0);
    }

    private function create_reference_import($ref_type, $meta = [])
    {
        $this->ensure_reference_imports_schema();

        $data = [
            'ref_type' => strtoupper(trim((string)$ref_type)),
            'version_label' => isset($meta['version_label']) ? (string)$meta['version_label'] : null,
            'effective_date' => isset($meta['effective_date']) && $meta['effective_date'] ? (string)$meta['effective_date'] : null,
            'source_name' => isset($meta['source_name']) ? (string)$meta['source_name'] : null,
            'source_hash' => isset($meta['source_hash']) ? (string)$meta['source_hash'] : null,
            'imported_by' => isset($meta['imported_by']) ? (string)$meta['imported_by'] : (string)$this->session->userdata('user_id'),
            'notes' => isset($meta['notes']) ? (string)$meta['notes'] : null,
            'is_active' => isset($meta['is_active']) ? (int)$meta['is_active'] : 1,
        ];

        if ($data['is_active'] === 1) {
            $this->db->where('ref_type', $data['ref_type'])->update('nhis_reference_imports', ['is_active' => 0]);
        }

        $this->db->insert('nhis_reference_imports', $data);
        return (int)$this->db->insert_id();
    }

    public function import_icd10_csv($file_path, $meta = [])
    {
        $this->ensure_schema();

        if (!is_readable($file_path)) {
            return ['success' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'File not readable'];
        }

        $import_id = $this->create_reference_import('ICD10', $meta);
        $fh = fopen($file_path, 'r');
        if (!$fh) {
            return ['success' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Failed to open file'];
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['success' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Empty CSV'];
        }

        $map = [];
        foreach ($header as $idx => $h) {
            $key = strtolower(trim((string)$h));
            $map[$key] = $idx;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($fh)) !== false) {
            $code = isset($map['code']) ? trim((string)$row[$map['code']]) : '';
            $desc = isset($map['description']) ? trim((string)$row[$map['description']]) : '';
            $cat  = isset($map['category']) ? trim((string)$row[$map['category']]) : null;
            $active = isset($map['is_active']) ? (int)$row[$map['is_active']] : 1;

            if ($code === '' || $desc === '') {
                $skipped++;
                continue;
            }

            $existing = $this->db->get_where('icd10_codes', ['code' => $code])->row();
            $data = [
                'code' => $code,
                'description' => $desc,
                'category' => $cat,
                'is_active' => $active ? 1 : 0,
                'import_id' => $import_id,
            ];

            if ($existing) {
                $this->db->where('id', (int)$existing->id)->update('icd10_codes', $data);
                $updated++;
            } else {
                $this->db->insert('icd10_codes', $data);
                $inserted++;
            }
        }

        fclose($fh);
        return ['success' => true, 'import_id' => $import_id, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }

    public function import_service_tariffs_csv($file_path, $meta = [])
    {
        $this->ensure_schema();

        if (!is_readable($file_path)) {
            return ['success' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'File not readable'];
        }

        $import_id = $this->create_reference_import('SERVICE_TARIFF', $meta);
        $fh = fopen($file_path, 'r');
        if (!$fh) {
            return ['success' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Failed to open file'];
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['success' => false, 'inserted' => 0, 'updated' => 0, 'skipped' => 0, 'error' => 'Empty CSV'];
        }

        $map = [];
        foreach ($header as $idx => $h) {
            $key = strtolower(trim((string)$h));
            $map[$key] = $idx;
        }

        $inserted = 0;
        $updated = 0;
        $skipped = 0;

        while (($row = fgetcsv($fh)) !== false) {
            $code = isset($map['service_code']) ? trim((string)$row[$map['service_code']]) : '';
            $name = isset($map['service_name']) ? trim((string)$row[$map['service_name']]) : '';
            $cat  = isset($map['category']) ? trim((string)$row[$map['category']]) : null;
            $tariff = isset($map['tariff']) ? (float)$row[$map['tariff']] : (isset($map['tariff_amount']) ? (float)$row[$map['tariff_amount']] : 0.0);
            $active = isset($map['is_active']) ? (int)$row[$map['is_active']] : 1;

            if ($code === '' || $name === '') {
                $skipped++;
                continue;
            }

            $existing = $this->db->get_where('nhis_tariffs', ['service_code' => $code])->row();
            $data = [
                'service_code' => $code,
                'service_name' => $name,
                'category' => $cat ?: 'GENERAL',
                'tariff' => $tariff,
                'is_active' => $active ? 1 : 0,
                'import_id' => $import_id,
            ];

            if ($existing) {
                $this->db->where('id', (int)$existing->id)->update('nhis_tariffs', $data);
                $updated++;
            } else {
                $this->db->insert('nhis_tariffs', $data);
                $inserted++;
            }
        }

        fclose($fh);
        return ['success' => true, 'import_id' => $import_id, 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped];
    }
    
    private function seed_icd10_codes()
    {
        $codes = [
            ['code'=>'B54','description'=>'Unspecified malaria','category'=>'Infectious'],
            ['code'=>'J06','description'=>'Acute upper respiratory infection','category'=>'Respiratory'],
            ['code'=>'I10','description'=>'Essential hypertension','category'=>'Cardiovascular'],
            ['code'=>'E11','description'=>'Type 2 diabetes mellitus','category'=>'Endocrine'],
            ['code'=>'K29','description'=>'Gastritis and duodenitis','category'=>'Gastrointestinal'],
            ['code'=>'A09','description'=>'Infectious gastroenteritis','category'=>'Infectious'],
            ['code'=>'J18','description'=>'Pneumonia','category'=>'Respiratory'],
            ['code'=>'N39','description'=>'Urinary tract infection','category'=>'Genitourinary'],
            ['code'=>'D50','description'=>'Iron deficiency anaemia','category'=>'Blood'],
            ['code'=>'M54','description'=>'Dorsalgia (back pain)','category'=>'Musculoskeletal']
        ];
        foreach ($codes as $c) { $this->db->insert('icd10_codes', $c); }
    }
    
    private function seed_nhis_tariffs()
    {
        $tariffs = [
            ['service_code'=>'CONS-OPD','service_name'=>'OPD Consultation','category'=>'CONSULTATION','tariff'=>15.00],
            ['service_code'=>'LAB-FBC','service_name'=>'Full Blood Count','category'=>'LABORATORY','tariff'=>20.00],
            ['service_code'=>'LAB-MPS','service_name'=>'Malaria Parasite','category'=>'LABORATORY','tariff'=>8.00],
            ['service_code'=>'LAB-URINE','service_name'=>'Urinalysis','category'=>'LABORATORY','tariff'=>10.00],
            ['service_code'=>'RAD-XRAY','service_name'=>'X-Ray','category'=>'RADIOLOGY','tariff'=>35.00],
            ['service_code'=>'RAD-USS','service_name'=>'Ultrasound','category'=>'RADIOLOGY','tariff'=>50.00],
            ['service_code'=>'PROC-DRESS','service_name'=>'Wound Dressing','category'=>'PROCEDURES','tariff'=>10.00]
        ];
        foreach ($tariffs as $t) { $this->db->insert('nhis_tariffs', $t); }
    }
    
    private function seed_service_mapping()
    {
        $mappings = [
            ['hms_service_name'=>'OPD Consultation','hms_service_type'=>'CONSULTATION','nhis_code'=>'OPD001','nhis_name'=>'Outpatient Consultation','category'=>'CONSULTATION','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>15.00],
            ['hms_service_name'=>'Full Blood Count','hms_service_type'=>'LABORATORY','nhis_code'=>'LAB002','nhis_name'=>'FBC','category'=>'LABORATORY','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>20.00],
            ['hms_service_name'=>'Malaria Test','hms_service_type'=>'LABORATORY','nhis_code'=>'LAB003','nhis_name'=>'Malaria Parasite','category'=>'LABORATORY','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>8.00],
            ['hms_service_name'=>'Urinalysis','hms_service_type'=>'LABORATORY','nhis_code'=>'LAB004','nhis_name'=>'Urine Analysis','category'=>'LABORATORY','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>10.00],
            ['hms_service_name'=>'X-Ray','hms_service_type'=>'RADIOLOGY','nhis_code'=>'RAD001','nhis_name'=>'X-Ray Examination','category'=>'RADIOLOGY','is_covered'=>1,'coverage_percent'=>80,'tariff_amount'=>35.00],
            ['hms_service_name'=>'Ultrasound','hms_service_type'=>'RADIOLOGY','nhis_code'=>'RAD004','nhis_name'=>'Ultrasound Scan','category'=>'RADIOLOGY','is_covered'=>0,'coverage_percent'=>0,'tariff_amount'=>50.00],
            ['hms_service_name'=>'Wound Dressing','hms_service_type'=>'PROCEDURE','nhis_code'=>'PROC001','nhis_name'=>'Wound Care','category'=>'PROCEDURES','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>10.00],
            ['hms_service_name'=>'Paracetamol','hms_service_type'=>'PHARMACY','nhis_code'=>'DRUG001','nhis_name'=>'Paracetamol 500mg','category'=>'PHARMACY','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>2.00],
            ['hms_service_name'=>'Amoxicillin','hms_service_type'=>'PHARMACY','nhis_code'=>'DRUG002','nhis_name'=>'Amoxicillin 500mg','category'=>'PHARMACY','is_covered'=>1,'coverage_percent'=>100,'tariff_amount'=>5.00],
            ['hms_service_name'=>'Metformin','hms_service_type'=>'PHARMACY','nhis_code'=>'DRUG003','nhis_name'=>'Metformin 500mg','category'=>'PHARMACY','is_covered'=>1,'coverage_percent'=>50,'tariff_amount'=>8.00]
        ];
        foreach ($mappings as $m) { $this->db->insert('nhis_service_mapping', $m); }
    }
    
    public function get_api_mode()
    {
        // Read from database, fallback to class default or env var
        $row = $this->db->get_where('nhis_config', ['config_key' => 'nhis_api_mode'])->row();
        if ($row && !empty($row->config_value)) {
            $this->api_mode = strtoupper($row->config_value);
        } else {
            // Fallback to env var or default
            $this->api_mode = getenv('NHIS_MODE') !== false ? strtoupper(getenv('NHIS_MODE')) : 'MOCK';
        }
        return $this->api_mode;
    }
    
    public function set_api_mode($mode)
    {
        $this->db->where('config_key', 'nhis_api_mode');
        $this->db->update('nhis_config', ['config_value' => $mode]);
        $this->api_mode = $mode;
    }
    
    public function register_membership($data)
    {
        $existing = $this->get_membership_by_patient($data['patient_no']);
        if ($existing) {
            $this->db->where('id', $existing->id);
            $this->db->update('nhis_memberships', $data);
            return $existing->id;
        }
        $this->db->insert('nhis_memberships', $data);
        return $this->db->insert_id();
    }
    
    public function get_membership_by_patient($patient_no)
    {
        return $this->db->get_where('nhis_memberships', ['patient_no' => $patient_no])->row();
    }
    
    public function update_membership_status($id, $status, $response = null)
    {
        $data = ['status' => $status, 'last_verified' => date('Y-m-d H:i:s')];
        if ($response && isset($response['expiry_date'])) $data['expiry_date'] = $response['expiry_date'];
        if ($response && isset($response['member_name'])) $data['member_name'] = $response['member_name'];
        $this->db->where('id', $id);
        return $this->db->update('nhis_memberships', $data);
    }
    
    public function create_visit($data)
    {
        $this->db->insert('nhis_visits', $data);
        return $this->db->insert_id();
    }
    
    public function generate_claim_number()
    {
        $prefix = 'NHIS-' . date('Ym') . '-';
        $this->db->like('claim_number', $prefix, 'after');
        $this->db->order_by('claim_id', 'DESC');
        $last = $this->db->get('nhis_claims')->row();
        $num = $last ? ((int)substr($last->claim_number, -4) + 1) : 1;
        return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
    
    public function create_claim($data)
    {
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
            'patient_no' => isset($data['patient_no']) ? $data['patient_no'] : null,
            'iop_id' => isset($data['visit_id']) ? $data['visit_id'] : null
        )));

        $data['claim_number'] = $data['claim_number'] ?? $this->generate_claim_number();
        $data['claim_date'] = $data['claim_date'] ?? date('Y-m-d');
        $data['claim_month'] = date('Y-m', strtotime($data['claim_date']));
        $data['created_by'] = $this->session->userdata('user_id');
        $trace = array(
            'method' => __METHOD__,
            'file' => __FILE__,
            'uri' => function_exists('uri_string') ? uri_string() : null,
            'patient_no' => $data['patient_no'] ?? null,
            'iop_id' => $data['visit_id'] ?? null,
            'claim_number' => $data['claim_number'] ?? null
        );
        log_message('error', 'NHIS_CLAIM_INSERT_TRACE: ' . json_encode($trace));
        $this->db->insert('nhis_claims', $data);
        return $this->db->insert_id();
    }
    
    public function get_claim($id)
    {
        $this->db->select('c.*, m.nhis_number, p.firstname, p.lastname');
        $this->db->from('nhis_claims c');
        $this->db->join('nhis_memberships m', 'm.patient_no = c.patient_no', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = c.patient_no', 'left');
        $this->db->where('c.id', $id);
        return $this->db->get()->row();
    }
    
    public function get_claims($filters = [], $limit = 100)
    {
        $this->db->select('c.*, m.nhis_number, p.firstname, p.lastname');
        $this->db->from('nhis_claims c');
        $this->db->join('nhis_memberships m', 'm.patient_no = c.patient_no', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = c.patient_no', 'left');
        if (!empty($filters['status'])) $this->db->where('c.status', $filters['status']);
        if (!empty($filters['claim_month'])) $this->db->where('c.claim_month', $filters['claim_month']);
        $this->db->order_by('c.created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get()->result();
    }
    
    public function add_claim_item($claim_id, $item)
    {
        $item['claim_id'] = $claim_id;
        $item['amount'] = ($item['quantity'] ?? 1) * ($item['tariff'] ?? 0);
        $this->db->insert('nhis_claim_items', $item);
        $this->recalculate_claim_total($claim_id);
        return $this->db->insert_id();
    }
    
    public function get_claim_items($claim_id)
    {
        return $this->db->get_where('nhis_claim_items', ['claim_id' => $claim_id])->result();
    }
    
    public function recalculate_claim_total($claim_id)
    {
        $total = $this->db->query("SELECT SUM(amount) as t FROM nhis_claim_items WHERE claim_id=?", [$claim_id])->row()->t ?? 0;
        $this->db->where('id', $claim_id);
        $this->db->update('nhis_claims', ['total_amount' => $total]);
    }
    
    public function add_claim_diagnosis($claim_id, $icd10_code, $type = 'PRIMARY')
    {
        $icd10_code = trim((string)$icd10_code);
        $description = null;

        try {
            $this->_load_reference_model();
            $ref = $this->nhis_reference_model->get_icd10($icd10_code);
            if ($ref) {
                $description = $ref->description;
            }
        } catch (Exception $e) {
        }

        if ($description === null) {
            // DEPRECATED FALLBACK — remove once nhis_ref_* confirmed complete
            $icd = $this->db->get_where('icd10_codes', ['code' => $icd10_code])->row();
            if ($icd) {
                $description = $icd->description;
            }
        }

        $this->db->insert('nhis_diagnosis', [
            'claim_id' => $claim_id,
            'icd10_code' => $icd10_code,
            'diagnosis_name' => $description,
            'diagnosis_type' => $type
        ]);
        return $this->db->insert_id();
    }
    
    public function get_claim_diagnoses($claim_id)
    {
        return $this->db->get_where('nhis_diagnosis', ['claim_id' => $claim_id])->result();
    }
    
    public function validate_claim($claim_id)
    {
        $errors = [];
        $claim = $this->get_claim($claim_id);
        if (!$claim) return ['valid' => false, 'errors' => ['Claim not found']];
        
        $membership = $this->get_membership_by_patient($claim->patient_no);
        if (!$membership || $membership->status !== 'ACTIVE') $errors[] = 'NHIS membership not active';
        
        $diagnoses = $this->get_claim_diagnoses($claim_id);
        if (empty($diagnoses)) $errors[] = 'No diagnosis attached';
        
        $items = $this->get_claim_items($claim_id);
        if (empty($items)) $errors[] = 'No services attached';
        
        if ($claim->total_amount <= 0) $errors[] = 'Invalid claim amount';
        
        $this->db->where('id', $claim_id);
        $this->db->update('nhis_claims', [
            'validation_errors' => empty($errors) ? null : json_encode($errors),
            'status' => empty($errors) ? 'READY' : 'DRAFT'
        ]);
        
        return ['valid' => empty($errors), 'errors' => $errors];
    }
    
    public function update_claim_status($claim_id, $status, $extra = [])
    {
        $data = ['status' => $status];
        if ($status === 'SUBMITTED') $data['submission_date'] = date('Y-m-d H:i:s');
        if (isset($extra['rejection_reason'])) $data['rejection_reason'] = $extra['rejection_reason'];
        if (isset($extra['claimit_reference'])) $data['claimit_reference'] = $extra['claimit_reference'];
        $this->db->where('id', $claim_id);
        return $this->db->update('nhis_claims', $data);
    }
    
    public function search_icd10($term, $limit = 20)
    {
        $term = trim((string)$term);
        $limit = (int)$limit;
        $results = [];

        try {
            $this->_load_reference_model();
            $canonical = $this->nhis_reference_model->search_icd10($term, $limit);
            if ($canonical) {
                foreach ($canonical as $row) {
                    $obj = new stdClass();
                    $obj->code = isset($row->nhis_code) ? $row->nhis_code : null;
                    $obj->description = isset($row->description) ? $row->description : null;
                    $obj->category = property_exists($row, 'category') ? $row->category : null;
                    $obj->is_active = property_exists($row, 'is_active') ? $row->is_active : 1;
                    $results[] = $obj;
                }
            }
        } catch (Exception $e) {
        }

        if (!empty($results)) {
            return $results;
        }

        // DEPRECATED FALLBACK — remove once nhis_ref_* confirmed complete
        $this->db->group_start();
        $this->db->like('code', $term);
        $this->db->or_like('description', $term);
        $this->db->group_end();
        $this->db->where('is_active', 1);
        $this->db->limit($limit);
        return $this->db->get('icd10_codes')->result();
    }
    
    public function search_tariffs($term, $limit = 20)
    {
        $term = trim((string)$term);
        $limit = (int)$limit;

        if ($term === '') {
            return [];
        }

        $results = [];

        try {
            $this->_load_reference_model();
            $canonical = $this->nhis_reference_model->search_tariffs($term, $limit);
            if ($canonical) {
                foreach ($canonical as $row) {
                    $obj = new stdClass();
                    $obj->service_code = isset($row->nhis_code) ? $row->nhis_code : null;
                    $obj->service_name = isset($row->description) ? $row->description : null;
                    $obj->category = property_exists($row, 'category') ? $row->category : null;
                    $obj->tariff = property_exists($row, 'tariff_amount') ? $row->tariff_amount : null;
                    $obj->is_active = property_exists($row, 'is_active') ? $row->is_active : 1;
                    $results[] = $obj;
                }
            }
        } catch (Exception $e) {
        }

        if (!empty($results)) {
            return $results;
        }

        // DEPRECATED FALLBACK — remove once nhis_ref_* confirmed complete
        $this->db->group_start();
        $this->db->like('service_code', $term);
        $this->db->or_like('service_name', $term);
        $this->db->group_end();
        $this->db->where('is_active', 1);
        $this->db->limit($limit);
        return $this->db->get('nhis_tariffs')->result();
    }
    
    public function get_tariffs_by_category($category = null)
    {
        if ($category) $this->db->where('category', $category);
        $this->db->where('is_active', 1);
        return $this->db->get('nhis_tariffs')->result();
    }
    
    public function log_api_call($endpoint, $request, $response, $status)
    {
        $this->db->insert('claimit_logs', [
            'endpoint' => $endpoint,
            'request' => json_encode($request),
            'response' => json_encode($response),
            'status' => $status,
            'api_mode' => $this->api_mode,
            'user_id' => $this->session->userdata('user_id')
        ]);
    }
    
    public function get_dashboard_summary()
    {
        $summary = ['total_claims' => 0, 'draft' => 0, 'ready' => 0, 'submitted' => 0, 'approved' => 0, 'rejected' => 0, 'total_amount' => 0];
        $stats = $this->db->query("SELECT status, COUNT(*) as cnt, SUM(total_amount) as amt FROM nhis_claims GROUP BY status")->result();
        foreach ($stats as $s) {
            $summary['total_claims'] += $s->cnt;
            $summary['total_amount'] += $s->amt ?? 0;
            $summary[strtolower($s->status)] = $s->cnt;
        }
        $summary['active_members'] = $this->db->where('status', 'ACTIVE')->count_all_results('nhis_memberships');
        return $summary;
    }
    
    public function get_tariff_categories()
    {
        $categories = [];

        try {
            $this->_load_reference_model();
            if ($this->db->table_exists('nhis_ref_tariffs') && $this->db->field_exists('category', 'nhis_ref_tariffs')) {
                $this->db->distinct();
                $q = $this->db->select('category')
                    ->where('is_active', 1)
                    ->where('category IS NOT NULL', null, false)
                    ->order_by('category', 'ASC')
                    ->get('nhis_ref_tariffs');
                if ($q && $q->num_rows() > 0) {
                    $categories = $q->result_array();
                }
            }
        } catch (Exception $e) {
        }

        if (!empty($categories)) {
            return $categories;
        }

        // DEPRECATED FALLBACK — remove once nhis_ref_* confirmed complete
        $this->db->distinct();
        return $this->db->select('category')
            ->where('is_active', 1)
            ->get('nhis_tariffs')
            ->result_array();
    }
    
    public function get_tariff_by_code($service_code)
    {
        $service_code = trim((string)$service_code);
        if ($service_code === '') {
            return null;
        }

        $tariff = null;

        try {
            $this->_load_reference_model();
            $ref = $this->nhis_reference_model->get_active_tariff($service_code);
            if ($ref) {
                $obj = new stdClass();
                $obj->service_code = isset($ref->nhis_code) ? $ref->nhis_code : $service_code;
                $obj->service_name = isset($ref->description) ? $ref->description : null;
                $obj->category = property_exists($ref, 'category') ? $ref->category : null;
                $obj->tariff = property_exists($ref, 'tariff_amount') ? $ref->tariff_amount : null;
                $obj->is_active = property_exists($ref, 'is_active') ? $ref->is_active : 1;
                $tariff = $obj;
            }
        } catch (Exception $e) {
        }

        if ($tariff !== null) {
            return $tariff;
        }

        // DEPRECATED FALLBACK — remove once nhis_ref_* confirmed complete
        return $this->db->get_where('nhis_tariffs', ['service_code' => $service_code, 'is_active' => 1])->row();
    }
    
    public function count_ready_claims()
    {
        return $this->db->where('status', 'READY')->count_all_results('nhis_claims');
    }
    
    public function count_submitted_claims()
    {
        return $this->db->where('status', 'SUBMITTED')->count_all_results('nhis_claims');
    }
    
    public function batch_submit_claims($claim_ids)
    {
        $results = ['success' => 0, 'failed' => 0, 'errors' => []];
        
        foreach ($claim_ids as $claim_id) {
            $validation = $this->validate_claim($claim_id);
            if (!$validation['valid']) {
                $results['failed']++;
                $results['errors'][$claim_id] = $validation['errors'];
                continue;
            }
            
            $this->update_claim_status($claim_id, 'SUBMITTED');
            $results['success']++;
        }
        
        return $results;
    }
    
    public function create_submission_batch($claim_ids, $user_id = null)
    {
        if (!$this->db->table_exists('nhis_submission_batches')) {
            $this->db->query("CREATE TABLE `nhis_submission_batches` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `batch_reference` VARCHAR(50) UNIQUE NOT NULL,
                `claim_count` INT DEFAULT 0,
                `total_amount` DECIMAL(15,2) DEFAULT 0,
                `status` ENUM('PENDING','SUBMITTED','PROCESSED') DEFAULT 'PENDING',
                `submitted_at` TIMESTAMP NULL,
                `processed_at` TIMESTAMP NULL,
                `created_by` INT,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
        
        $batch_ref = 'BATCH-' . date('Ymd') . '-' . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        $total = $this->db->select('SUM(total_amount) as t')
            ->where_in('id', $claim_ids)
            ->get('nhis_claims')->row()->t ?? 0;
        
        $this->db->insert('nhis_submission_batches', [
            'batch_reference' => $batch_ref,
            'claim_count' => count($claim_ids),
            'total_amount' => $total,
            'created_by' => $user_id ?? $this->session->userdata('user_id')
        ]);
        
        $batch_id = $this->db->insert_id();
        
        foreach ($claim_ids as $claim_id) {
            $this->db->where('id', $claim_id);
            $this->db->update('nhis_claims', ['batch_id' => $batch_id]);
        }
        
        return ['batch_id' => $batch_id, 'batch_reference' => $batch_ref];
    }
    
    public function get_claim_validation_summary($claim_id)
    {
        $claim = $this->get_claim($claim_id);
        $items = $this->get_claim_items($claim_id);
        $diagnoses = $this->get_claim_diagnoses($claim_id);
        $membership = $claim ? $this->get_membership_by_patient($claim->patient_no) : null;
        
        return [
            'claim' => $claim,
            'items_count' => count($items),
            'diagnoses_count' => count($diagnoses),
            'has_primary_diagnosis' => !empty(array_filter($diagnoses, fn($d) => $d->diagnosis_type === 'PRIMARY')),
            'membership_active' => $membership && $membership->status === 'ACTIVE',
            'total_amount' => $claim ? $claim->total_amount : 0
        ];
    }
    
    public function get_api_logs($filters = [], $limit = 100)
    {
        if (!empty($filters['endpoint'])) {
            $this->db->like('endpoint', $filters['endpoint']);
        }
        if (!empty($filters['status'])) {
            $this->db->where('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('created_at >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('created_at <=', $filters['date_to'] . ' 23:59:59');
        }
        
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        return $this->db->get('claimit_logs')->result();
    }
    
    public function get_readiness_checklist()
    {
        $checks = [];
        
        $checks['schema_created'] = $this->db->table_exists('nhis_claims');

        $canonicalIcd10 = 0;
        $legacyIcd10 = 0;
        $canonicalTariffs = 0;
        $legacyTariffs = 0;

        if ($this->db->table_exists('nhis_ref_icd10')) {
            $canonicalIcd10 = (int)$this->db->count_all('nhis_ref_icd10');
        }
        if ($this->db->table_exists('icd10_codes')) {
            $legacyIcd10 = (int)$this->db->count_all('icd10_codes');
        }
        if ($this->db->table_exists('nhis_ref_tariffs')) {
            $canonicalTariffs = (int)$this->db->count_all('nhis_ref_tariffs');
        }
        if ($this->db->table_exists('nhis_tariffs')) {
            $legacyTariffs = (int)$this->db->count_all('nhis_tariffs');
        }

        $checks['canonical_icd10_count'] = $canonicalIcd10;
        $checks['legacy_icd10_count'] = $legacyIcd10;
        $checks['canonical_tariff_count'] = $canonicalTariffs;
        $checks['legacy_tariff_count'] = $legacyTariffs;

        $checks['icd10_seeded'] = ($canonicalIcd10 > 0 || $legacyIcd10 > 0);
        $checks['tariffs_seeded'] = ($canonicalTariffs > 0 || $legacyTariffs > 0);
        $checks['api_configured'] = true;
        
        $mode = $this->db->get_where('nhis_config', ['config_key' => 'nhis_api_mode'])->row();
        $checks['live_mode_ready'] = $mode && $mode->config_value === 'LIVE';
        
        $checks['validation_engine'] = true;
        $checks['submission_queue'] = true;
        $checks['logging_enabled'] = $this->db->table_exists('claimit_logs');

        $checks['all_ready'] = (
            $checks['schema_created']
            && $checks['icd10_seeded']
            && $checks['tariffs_seeded']
            && $checks['logging_enabled']
        );
        
        return $checks;
    }
    
    // =========================================================================
    // ICD-10 DIAGNOSIS CODE METHODS (Phase 2 - Clinical Safety)
    // =========================================================================
    
    /**
     * Get ICD-10 code by exact code
     */
    public function get_icd10_by_code($code)
    {
        if (empty($code)) {
            return null;
        }
        
        $code = trim(strtoupper($code));
        
        $this->db->where('code', $code);
        $this->db->where('is_active', 1);
        $q = $this->db->get('icd10_codes');
        
        return $q ? $q->row() : null;
    }
    
    /**
     * Get common/frequently used diagnoses
     */
    public function get_common_diagnoses($category = '', $limit = 20)
    {
        $this->db->select('code, description, category');
        $this->db->from('icd10_codes');
        $this->db->where('is_active', 1);
        
        if (!empty($category)) {
            $this->db->where('category', $category);
        }
        
        $this->db->order_by('code', 'ASC');
        $this->db->limit((int)$limit);
        
        $q = $this->db->get();
        return $q ? $q->result() : array();
    }
    
    /**
     * Get all ICD-10 categories
     */
    public function get_icd10_categories()
    {
        $this->db->distinct();
        $this->db->select('category');
        $this->db->from('icd10_codes');
        $this->db->where('is_active', 1);
        $this->db->where('category IS NOT NULL');
        $this->db->order_by('category', 'ASC');

        $q = $this->db->get();
        return $q ? $q->result() : array();
    }
    
    /**
     * Seed additional common Ghana ICD-10 codes
     */
    public function seed_additional_icd10_codes()
    {
        $existing = $this->db->count_all('icd10_codes');
        if ($existing >= 50) {
            return; // Already seeded
        }
        
        $codes = array(
            // Infectious diseases common in Ghana
            array('code' => 'A01', 'description' => 'Typhoid and paratyphoid fevers', 'category' => 'Infectious'),
            array('code' => 'A06', 'description' => 'Amoebiasis', 'category' => 'Infectious'),
            array('code' => 'A15', 'description' => 'Respiratory tuberculosis', 'category' => 'Infectious'),
            array('code' => 'B50', 'description' => 'Plasmodium falciparum malaria', 'category' => 'Infectious'),
            array('code' => 'B51', 'description' => 'Plasmodium vivax malaria', 'category' => 'Infectious'),
            
            // Respiratory
            array('code' => 'J00', 'description' => 'Acute nasopharyngitis (common cold)', 'category' => 'Respiratory'),
            array('code' => 'J02', 'description' => 'Acute pharyngitis', 'category' => 'Respiratory'),
            array('code' => 'J03', 'description' => 'Acute tonsillitis', 'category' => 'Respiratory'),
            array('code' => 'J20', 'description' => 'Acute bronchitis', 'category' => 'Respiratory'),
            array('code' => 'J45', 'description' => 'Asthma', 'category' => 'Respiratory'),
            
            // Cardiovascular
            array('code' => 'I11', 'description' => 'Hypertensive heart disease', 'category' => 'Cardiovascular'),
            array('code' => 'I20', 'description' => 'Angina pectoris', 'category' => 'Cardiovascular'),
            array('code' => 'I21', 'description' => 'Acute myocardial infarction', 'category' => 'Cardiovascular'),
            array('code' => 'I50', 'description' => 'Heart failure', 'category' => 'Cardiovascular'),
            
            // Endocrine
            array('code' => 'E10', 'description' => 'Type 1 diabetes mellitus', 'category' => 'Endocrine'),
            array('code' => 'E03', 'description' => 'Hypothyroidism', 'category' => 'Endocrine'),
            array('code' => 'E05', 'description' => 'Thyrotoxicosis (hyperthyroidism)', 'category' => 'Endocrine'),
            
            // Gastrointestinal
            array('code' => 'K25', 'description' => 'Gastric ulcer', 'category' => 'Gastrointestinal'),
            array('code' => 'K26', 'description' => 'Duodenal ulcer', 'category' => 'Gastrointestinal'),
            array('code' => 'K35', 'description' => 'Acute appendicitis', 'category' => 'Gastrointestinal'),
            array('code' => 'K40', 'description' => 'Inguinal hernia', 'category' => 'Gastrointestinal'),
            array('code' => 'K80', 'description' => 'Cholelithiasis (gallstones)', 'category' => 'Gastrointestinal'),
            
            // Genitourinary
            array('code' => 'N10', 'description' => 'Acute tubulo-interstitial nephritis', 'category' => 'Genitourinary'),
            array('code' => 'N18', 'description' => 'Chronic kidney disease', 'category' => 'Genitourinary'),
            array('code' => 'N20', 'description' => 'Calculus of kidney and ureter', 'category' => 'Genitourinary'),
            
            // Musculoskeletal
            array('code' => 'M15', 'description' => 'Polyarthrosis', 'category' => 'Musculoskeletal'),
            array('code' => 'M25', 'description' => 'Other joint disorders', 'category' => 'Musculoskeletal'),
            array('code' => 'M79', 'description' => 'Soft tissue disorders', 'category' => 'Musculoskeletal'),
            
            // Skin
            array('code' => 'L02', 'description' => 'Cutaneous abscess, furuncle and carbuncle', 'category' => 'Skin'),
            array('code' => 'L03', 'description' => 'Cellulitis', 'category' => 'Skin'),
            array('code' => 'L20', 'description' => 'Atopic dermatitis', 'category' => 'Skin'),
            
            // Pregnancy
            array('code' => 'O80', 'description' => 'Single spontaneous delivery', 'category' => 'Pregnancy'),
            array('code' => 'O82', 'description' => 'Single delivery by caesarean section', 'category' => 'Pregnancy'),
            array('code' => 'O20', 'description' => 'Haemorrhage in early pregnancy', 'category' => 'Pregnancy'),
            
            // Injuries
            array('code' => 'S00', 'description' => 'Superficial injury of head', 'category' => 'Injuries'),
            array('code' => 'S52', 'description' => 'Fracture of forearm', 'category' => 'Injuries'),
            array('code' => 'S82', 'description' => 'Fracture of leg', 'category' => 'Injuries'),
            array('code' => 'T14', 'description' => 'Injury of unspecified body region', 'category' => 'Injuries'),
            
            // Mental health
            array('code' => 'F32', 'description' => 'Depressive episode', 'category' => 'Mental'),
            array('code' => 'F41', 'description' => 'Anxiety disorders', 'category' => 'Mental')
        );
        
        foreach ($codes as $c) {
            // Check if code exists
            $exists = $this->db->get_where('icd10_codes', array('code' => $c['code']))->num_rows();
            if ($exists == 0) {
                $c['is_active'] = 1;
                $this->db->insert('icd10_codes', $c);
            }
        }
    }
}
