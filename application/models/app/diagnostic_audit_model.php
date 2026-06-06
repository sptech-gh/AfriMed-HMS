<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Diagnostic Audit Model
 * Phase 3 (Week 4) - Audit Trail Hardening
 * 
 * Features:
 * - Immutable audit logs with blockchain-style hash integrity
 * - Comprehensive event tracking
 * - Tamper detection
 * - Compliance reporting
 * - Data retention policies
 */
class Diagnostic_audit_model extends CI_Model
{
    private $hash_algorithm = 'sha256';

    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  SCHEMA INSTALLATION                                               */
    /* ================================================================== */

    public function ensure_audit_schema()
    {
        $this->ensure_audit_log_table();
        $this->ensure_audit_chain_table();
        $this->ensure_audit_config_table();
        $this->ensure_audit_retention_table();
        $this->ensure_audit_verification_table();
        $this->seed_audit_config();
        return true;
    }

    private function table_exists($table)
    {
        return $this->db->table_exists($table);
    }

    private function ensure_audit_log_table()
    {
        if ($this->table_exists('diagnostic_audit_log')) return;

        $this->db->query("
            CREATE TABLE diagnostic_audit_log (
                audit_id BIGINT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                event_category ENUM('RESULT','SAMPLE','ORDER','ACCESS','CONFIG','SECURITY','SYSTEM') NOT NULL,
                severity ENUM('INFO','WARNING','ERROR','CRITICAL','SECURITY') DEFAULT 'INFO',
                entity_type VARCHAR(50) NOT NULL,
                entity_id VARCHAR(50),
                patient_no VARCHAR(25),
                user_id INT,
                username VARCHAR(100),
                user_role VARCHAR(50),
                action VARCHAR(100) NOT NULL,
                old_value TEXT,
                new_value TEXT,
                change_summary TEXT,
                ip_address VARCHAR(45),
                user_agent VARCHAR(500),
                session_id VARCHAR(100),
                request_uri VARCHAR(500),
                request_method VARCHAR(10),
                additional_data JSON,
                record_hash VARCHAR(64) NOT NULL,
                previous_hash VARCHAR(64),
                chain_sequence BIGINT,
                is_verified TINYINT(1) DEFAULT 1,
                verification_failed_at DATETIME DEFAULT NULL,
                created_at DATETIME(6) DEFAULT CURRENT_TIMESTAMP(6),
                INDEX idx_event_type (event_type),
                INDEX idx_category (event_category),
                INDEX idx_severity (severity),
                INDEX idx_entity (entity_type, entity_id),
                INDEX idx_patient (patient_no),
                INDEX idx_user (user_id),
                INDEX idx_created (created_at),
                INDEX idx_chain (chain_sequence),
                INDEX idx_hash (record_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_audit_chain_table()
    {
        if ($this->table_exists('diagnostic_audit_chain')) return;

        $this->db->query("
            CREATE TABLE diagnostic_audit_chain (
                chain_id INT AUTO_INCREMENT PRIMARY KEY,
                chain_date DATE NOT NULL UNIQUE,
                first_sequence BIGINT NOT NULL,
                last_sequence BIGINT NOT NULL,
                record_count INT NOT NULL,
                chain_start_hash VARCHAR(64) NOT NULL,
                chain_end_hash VARCHAR(64) NOT NULL,
                merkle_root VARCHAR(64),
                is_sealed TINYINT(1) DEFAULT 0,
                sealed_at DATETIME DEFAULT NULL,
                sealed_by INT DEFAULT NULL,
                verification_status ENUM('PENDING','VERIFIED','FAILED','TAMPERED') DEFAULT 'PENDING',
                last_verified_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_date (chain_date),
                INDEX idx_sealed (is_sealed),
                INDEX idx_status (verification_status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_audit_config_table()
    {
        if ($this->table_exists('diagnostic_audit_config')) return;

        $this->db->query("
            CREATE TABLE diagnostic_audit_config (
                config_id INT AUTO_INCREMENT PRIMARY KEY,
                config_key VARCHAR(50) NOT NULL UNIQUE,
                config_value TEXT,
                config_type ENUM('STRING','INT','BOOL','JSON') DEFAULT 'STRING',
                description VARCHAR(255),
                updated_at DATETIME DEFAULT NULL,
                updated_by INT DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_audit_retention_table()
    {
        if ($this->table_exists('diagnostic_audit_retention')) return;

        $this->db->query("
            CREATE TABLE diagnostic_audit_retention (
                retention_id INT AUTO_INCREMENT PRIMARY KEY,
                event_category VARCHAR(50) NOT NULL,
                retention_days INT NOT NULL DEFAULT 2555,
                archive_after_days INT DEFAULT 365,
                compress_archived TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_category (event_category)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Seed retention policies (7 years default for medical records)
        $policies = [
            ['RESULT', 2555, 365],
            ['SAMPLE', 2555, 365],
            ['ORDER', 2555, 365],
            ['ACCESS', 730, 180],
            ['CONFIG', 1825, 365],
            ['SECURITY', 2555, 365],
            ['SYSTEM', 365, 90]
        ];

        foreach ($policies as $p) {
            $this->db->insert('diagnostic_audit_retention', [
                'event_category' => $p[0],
                'retention_days' => $p[1],
                'archive_after_days' => $p[2]
            ]);
        }
    }

    private function ensure_audit_verification_table()
    {
        if ($this->table_exists('diagnostic_audit_verification')) return;

        $this->db->query("
            CREATE TABLE diagnostic_audit_verification (
                verification_id INT AUTO_INCREMENT PRIMARY KEY,
                verification_type ENUM('SCHEDULED','MANUAL','ALERT') NOT NULL,
                start_date DATE,
                end_date DATE,
                records_checked BIGINT DEFAULT 0,
                records_valid BIGINT DEFAULT 0,
                records_invalid BIGINT DEFAULT 0,
                chains_checked INT DEFAULT 0,
                chains_valid INT DEFAULT 0,
                chains_invalid INT DEFAULT 0,
                status ENUM('RUNNING','COMPLETED','FAILED') DEFAULT 'RUNNING',
                error_details TEXT,
                started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                completed_at DATETIME DEFAULT NULL,
                performed_by INT,
                INDEX idx_status (status),
                INDEX idx_started (started_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function seed_audit_config()
    {
        if ($this->db->count_all_results('diagnostic_audit_config') > 0) return;

        $configs = [
            ['hash_algorithm', 'sha256', 'STRING', 'Hash algorithm for record integrity'],
            ['chain_seal_hour', '23', 'INT', 'Hour of day to seal daily chain (0-23)'],
            ['auto_verify_enabled', '1', 'BOOL', 'Enable automatic chain verification'],
            ['verify_interval_hours', '6', 'INT', 'Hours between automatic verifications'],
            ['alert_on_tamper', '1', 'BOOL', 'Send alert when tampering detected'],
            ['log_read_access', '0', 'BOOL', 'Log read-only access to records'],
            ['log_search_queries', '0', 'BOOL', 'Log search/filter operations'],
            ['retention_check_daily', '1', 'BOOL', 'Run daily retention policy check'],
            ['archive_path', '/var/audit_archive', 'STRING', 'Path for archived audit logs'],
            ['max_export_records', '10000', 'INT', 'Maximum records per export']
        ];

        foreach ($configs as $c) {
            $this->db->insert('diagnostic_audit_config', [
                'config_key' => $c[0],
                'config_value' => $c[1],
                'config_type' => $c[2],
                'description' => $c[3]
            ]);
        }
    }

    /* ================================================================== */
    /*  AUDIT LOGGING                                                     */
    /* ================================================================== */

    /**
     * Log an audit event with hash chain integrity
     */
    public function log_event($event_type, $data)
    {
        // Get previous hash for chain
        $last_record = $this->db->select('audit_id, record_hash, chain_sequence')
            ->order_by('audit_id', 'DESC')
            ->limit(1)
            ->get('diagnostic_audit_log')->row();

        $previous_hash = $last_record ? $last_record->record_hash : str_repeat('0', 64);
        $chain_sequence = $last_record ? $last_record->chain_sequence + 1 : 1;

        // Build audit record
        $record = [
            'event_type' => $event_type,
            'event_category' => $data['category'] ?? 'SYSTEM',
            'severity' => $data['severity'] ?? 'INFO',
            'entity_type' => $data['entity_type'] ?? 'unknown',
            'entity_id' => $data['entity_id'] ?? null,
            'patient_no' => $data['patient_no'] ?? null,
            'user_id' => $data['user_id'] ?? $this->session->userdata('user_id'),
            'username' => $data['username'] ?? $this->session->userdata('username'),
            'user_role' => $data['user_role'] ?? $this->session->userdata('role'),
            'action' => $data['action'] ?? $event_type,
            'old_value' => isset($data['old_value']) ? (is_array($data['old_value']) ? json_encode($data['old_value']) : $data['old_value']) : null,
            'new_value' => isset($data['new_value']) ? (is_array($data['new_value']) ? json_encode($data['new_value']) : $data['new_value']) : null,
            'change_summary' => $data['change_summary'] ?? null,
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr($this->input->user_agent(), 0, 500),
            'session_id' => session_id(),
            'request_uri' => substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
            'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'additional_data' => isset($data['additional']) ? json_encode($data['additional']) : null,
            'previous_hash' => $previous_hash,
            'chain_sequence' => $chain_sequence
        ];

        // Calculate record hash
        $record['record_hash'] = $this->calculate_record_hash($record, $previous_hash);

        $this->db->insert('diagnostic_audit_log', $record);
        return $this->db->insert_id();
    }

    /**
     * Calculate hash for a record
     */
    private function calculate_record_hash($record, $previous_hash)
    {
        $hash_data = [
            'event_type' => $record['event_type'],
            'event_category' => $record['event_category'],
            'entity_type' => $record['entity_type'],
            'entity_id' => $record['entity_id'],
            'patient_no' => $record['patient_no'],
            'user_id' => $record['user_id'],
            'action' => $record['action'],
            'old_value' => $record['old_value'],
            'new_value' => $record['new_value'],
            'chain_sequence' => $record['chain_sequence'],
            'previous_hash' => $previous_hash
        ];

        return hash($this->hash_algorithm, json_encode($hash_data));
    }

    /**
     * Log result entry/modification
     */
    public function log_result_event($action, $result_data, $old_data = null)
    {
        return $this->log_event('RESULT_' . strtoupper($action), [
            'category' => 'RESULT',
            'severity' => in_array($action, ['delete', 'modify', 'override']) ? 'WARNING' : 'INFO',
            'entity_type' => 'lab_result',
            'entity_id' => $result_data['result_id'] ?? $result_data['io_lab_id'] ?? null,
            'patient_no' => $result_data['patient_no'] ?? null,
            'action' => $action,
            'old_value' => $old_data,
            'new_value' => $result_data,
            'change_summary' => $this->generate_change_summary($old_data, $result_data)
        ]);
    }

    /**
     * Log sample event
     */
    public function log_sample_event($action, $sample_data, $old_data = null)
    {
        return $this->log_event('SAMPLE_' . strtoupper($action), [
            'category' => 'SAMPLE',
            'severity' => in_array($action, ['reject', 'lost', 'compromised']) ? 'WARNING' : 'INFO',
            'entity_type' => 'lab_sample',
            'entity_id' => $sample_data['sample_id'] ?? $sample_data['barcode'] ?? null,
            'patient_no' => $sample_data['patient_no'] ?? null,
            'action' => $action,
            'old_value' => $old_data,
            'new_value' => $sample_data,
            'change_summary' => $this->generate_change_summary($old_data, $sample_data)
        ]);
    }

    /**
     * Log order event
     */
    public function log_order_event($action, $order_data, $old_data = null)
    {
        return $this->log_event('ORDER_' . strtoupper($action), [
            'category' => 'ORDER',
            'severity' => in_array($action, ['cancel', 'modify']) ? 'WARNING' : 'INFO',
            'entity_type' => 'lab_order',
            'entity_id' => $order_data['io_lab_id'] ?? null,
            'patient_no' => $order_data['patient_no'] ?? null,
            'action' => $action,
            'old_value' => $old_data,
            'new_value' => $order_data
        ]);
    }

    /**
     * Log access event
     */
    public function log_access_event($action, $entity_type, $entity_id, $patient_no = null)
    {
        $config = $this->get_config('log_read_access');
        if ($action === 'view' && !$config) return null;

        return $this->log_event('ACCESS_' . strtoupper($action), [
            'category' => 'ACCESS',
            'severity' => 'INFO',
            'entity_type' => $entity_type,
            'entity_id' => $entity_id,
            'patient_no' => $patient_no,
            'action' => $action
        ]);
    }

    /**
     * Log security event
     */
    public function log_security_event($event_type, $details, $severity = 'WARNING')
    {
        return $this->log_event($event_type, [
            'category' => 'SECURITY',
            'severity' => $severity,
            'entity_type' => 'security',
            'action' => $event_type,
            'additional' => $details
        ]);
    }

    /**
     * Log configuration change
     */
    public function log_config_event($config_key, $old_value, $new_value)
    {
        return $this->log_event('CONFIG_CHANGE', [
            'category' => 'CONFIG',
            'severity' => 'WARNING',
            'entity_type' => 'config',
            'entity_id' => $config_key,
            'action' => 'modify',
            'old_value' => $old_value,
            'new_value' => $new_value,
            'change_summary' => "Config '{$config_key}' changed from '{$old_value}' to '{$new_value}'"
        ]);
    }

    private function generate_change_summary($old, $new)
    {
        if (!$old || !$new) return null;

        $old = is_array($old) ? $old : json_decode($old, true);
        $new = is_array($new) ? $new : json_decode($new, true);

        if (!is_array($old) || !is_array($new)) return null;

        $changes = [];
        foreach ($new as $key => $value) {
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $old_val = $old[$key] ?? 'null';
                $changes[] = "{$key}: {$old_val} → {$value}";
            }
        }

        return implode('; ', array_slice($changes, 0, 5));
    }

    /* ================================================================== */
    /*  CHAIN VERIFICATION                                                */
    /* ================================================================== */

    /**
     * Verify integrity of audit chain
     */
    public function verify_chain($start_date = null, $end_date = null)
    {
        $verification_id = $this->start_verification('MANUAL', $start_date, $end_date);

        $where = "1=1";
        $params = [];

        if ($start_date) {
            $where .= " AND DATE(created_at) >= ?";
            $params[] = $start_date;
        }
        if ($end_date) {
            $where .= " AND DATE(created_at) <= ?";
            $params[] = $end_date;
        }

        $records = $this->db->query("
            SELECT audit_id, record_hash, previous_hash, chain_sequence,
                   event_type, event_category, entity_type, entity_id,
                   patient_no, user_id, action, old_value, new_value
            FROM diagnostic_audit_log
            WHERE {$where}
            ORDER BY chain_sequence ASC
        ", $params)->result();

        $valid = 0;
        $invalid = 0;
        $previous_hash = null;

        foreach ($records as $record) {
            // First record in range - get its expected previous hash
            if ($previous_hash === null) {
                if ($record->chain_sequence == 1) {
                    $previous_hash = str_repeat('0', 64);
                } else {
                    $prev_record = $this->db->where('chain_sequence', $record->chain_sequence - 1)
                        ->get('diagnostic_audit_log')->row();
                    $previous_hash = $prev_record ? $prev_record->record_hash : str_repeat('0', 64);
                }
            }

            // Verify previous hash matches
            if ($record->previous_hash !== $previous_hash) {
                $invalid++;
                $this->mark_record_invalid($record->audit_id);
                continue;
            }

            // Recalculate and verify record hash
            $calculated_hash = $this->calculate_record_hash([
                'event_type' => $record->event_type,
                'event_category' => $record->event_category,
                'entity_type' => $record->entity_type,
                'entity_id' => $record->entity_id,
                'patient_no' => $record->patient_no,
                'user_id' => $record->user_id,
                'action' => $record->action,
                'old_value' => $record->old_value,
                'new_value' => $record->new_value,
                'chain_sequence' => $record->chain_sequence,
                'previous_hash' => $previous_hash
            ], $previous_hash);

            if ($calculated_hash !== $record->record_hash) {
                $invalid++;
                $this->mark_record_invalid($record->audit_id);
            } else {
                $valid++;
            }

            $previous_hash = $record->record_hash;
        }

        $this->complete_verification($verification_id, count($records), $valid, $invalid);

        return [
            'total' => count($records),
            'valid' => $valid,
            'invalid' => $invalid,
            'integrity' => $invalid === 0 ? 'VERIFIED' : 'COMPROMISED'
        ];
    }

    private function start_verification($type, $start_date, $end_date)
    {
        $this->db->insert('diagnostic_audit_verification', [
            'verification_type' => $type,
            'start_date' => $start_date,
            'end_date' => $end_date,
            'performed_by' => $this->session->userdata('user_id')
        ]);
        return $this->db->insert_id();
    }

    private function complete_verification($id, $total, $valid, $invalid)
    {
        $this->db->where('verification_id', $id)->update('diagnostic_audit_verification', [
            'records_checked' => $total,
            'records_valid' => $valid,
            'records_invalid' => $invalid,
            'status' => $invalid > 0 ? 'FAILED' : 'COMPLETED',
            'completed_at' => date('Y-m-d H:i:s')
        ]);

        // Alert if tampering detected
        if ($invalid > 0 && $this->get_config('alert_on_tamper')) {
            $this->log_security_event('AUDIT_TAMPER_DETECTED', [
                'verification_id' => $id,
                'invalid_records' => $invalid
            ], 'CRITICAL');
        }
    }

    private function mark_record_invalid($audit_id)
    {
        $this->db->where('audit_id', $audit_id)->update('diagnostic_audit_log', [
            'is_verified' => 0,
            'verification_failed_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Seal daily chain
     */
    public function seal_daily_chain($date = null)
    {
        $date = $date ?: date('Y-m-d', strtotime('-1 day'));

        // Check if already sealed
        $existing = $this->db->where(['chain_date' => $date, 'is_sealed' => 1])
            ->get('diagnostic_audit_chain')->row();
        if ($existing) return false;

        // Get chain boundaries
        $boundaries = $this->db->query("
            SELECT 
                MIN(chain_sequence) as first_seq,
                MAX(chain_sequence) as last_seq,
                COUNT(*) as record_count,
                MIN(record_hash) as first_hash,
                MAX(record_hash) as last_hash
            FROM diagnostic_audit_log
            WHERE DATE(created_at) = ?
        ", [$date])->row();

        if (!$boundaries || !$boundaries->record_count) return false;

        // Calculate merkle root
        $merkle_root = $this->calculate_merkle_root($date);

        // Upsert chain record
        $chain_data = [
            'chain_date' => $date,
            'first_sequence' => $boundaries->first_seq,
            'last_sequence' => $boundaries->last_seq,
            'record_count' => $boundaries->record_count,
            'chain_start_hash' => $boundaries->first_hash,
            'chain_end_hash' => $boundaries->last_hash,
            'merkle_root' => $merkle_root,
            'is_sealed' => 1,
            'sealed_at' => date('Y-m-d H:i:s'),
            'sealed_by' => $this->session->userdata('user_id'),
            'verification_status' => 'VERIFIED'
        ];

        $existing_chain = $this->db->where('chain_date', $date)->get('diagnostic_audit_chain')->row();
        if ($existing_chain) {
            $this->db->where('chain_id', $existing_chain->chain_id)->update('diagnostic_audit_chain', $chain_data);
        } else {
            $this->db->insert('diagnostic_audit_chain', $chain_data);
        }

        return true;
    }

    private function calculate_merkle_root($date)
    {
        $hashes = $this->db->select('record_hash')
            ->where('DATE(created_at)', $date)
            ->order_by('chain_sequence', 'ASC')
            ->get('diagnostic_audit_log')->result();

        if (empty($hashes)) return null;

        $layer = array_map(function($h) { return $h->record_hash; }, $hashes);

        while (count($layer) > 1) {
            $next_layer = [];
            for ($i = 0; $i < count($layer); $i += 2) {
                $left = $layer[$i];
                $right = isset($layer[$i + 1]) ? $layer[$i + 1] : $left;
                $next_layer[] = hash($this->hash_algorithm, $left . $right);
            }
            $layer = $next_layer;
        }

        return $layer[0];
    }

    /* ================================================================== */
    /*  QUERY & REPORTING                                                 */
    /* ================================================================== */

    /**
     * Search audit logs
     */
    public function search_audit_log($filters = [], $limit = 100, $offset = 0)
    {
        $this->db->select('a.*, u.username as performer_name')
            ->from('diagnostic_audit_log a')
            ->join('user u', 'u.user_id = a.user_id', 'left');

        if (!empty($filters['event_type'])) {
            $this->db->where('a.event_type', $filters['event_type']);
        }
        if (!empty($filters['event_category'])) {
            $this->db->where('a.event_category', $filters['event_category']);
        }
        if (!empty($filters['severity'])) {
            $this->db->where('a.severity', $filters['severity']);
        }
        if (!empty($filters['entity_type'])) {
            $this->db->where('a.entity_type', $filters['entity_type']);
        }
        if (!empty($filters['entity_id'])) {
            $this->db->where('a.entity_id', $filters['entity_id']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('a.patient_no', $filters['patient_no']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('a.user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(a.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(a.created_at) <=', $filters['date_to']);
        }
        if (!empty($filters['search'])) {
            $this->db->group_start()
                ->like('a.action', $filters['search'])
                ->or_like('a.change_summary', $filters['search'])
                ->or_like('a.entity_id', $filters['search'])
            ->group_end();
        }

        return $this->db->order_by('a.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()->result();
    }

    /**
     * Count audit logs matching filters
     */
    public function count_audit_log($filters = [])
    {
        $this->db->from('diagnostic_audit_log a');

        if (!empty($filters['event_type'])) {
            $this->db->where('a.event_type', $filters['event_type']);
        }
        if (!empty($filters['event_category'])) {
            $this->db->where('a.event_category', $filters['event_category']);
        }
        if (!empty($filters['severity'])) {
            $this->db->where('a.severity', $filters['severity']);
        }
        if (!empty($filters['patient_no'])) {
            $this->db->where('a.patient_no', $filters['patient_no']);
        }
        if (!empty($filters['user_id'])) {
            $this->db->where('a.user_id', $filters['user_id']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(a.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(a.created_at) <=', $filters['date_to']);
        }

        return $this->db->count_all_results();
    }

    /**
     * Get audit trail for specific entity
     */
    public function get_entity_audit_trail($entity_type, $entity_id, $limit = 50)
    {
        return $this->db->select('a.*, u.username as performer_name')
            ->from('diagnostic_audit_log a')
            ->join('user u', 'u.user_id = a.user_id', 'left')
            ->where(['a.entity_type' => $entity_type, 'a.entity_id' => $entity_id])
            ->order_by('a.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Get patient audit trail
     */
    public function get_patient_audit_trail($patient_no, $limit = 100)
    {
        return $this->db->select('a.*, u.username as performer_name')
            ->from('diagnostic_audit_log a')
            ->join('user u', 'u.user_id = a.user_id', 'left')
            ->where('a.patient_no', $patient_no)
            ->order_by('a.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Get user activity log
     */
    public function get_user_activity($user_id, $date_from = null, $date_to = null, $limit = 100)
    {
        $this->db->select('a.*')
            ->from('diagnostic_audit_log a')
            ->where('a.user_id', $user_id);

        if ($date_from) {
            $this->db->where('DATE(a.created_at) >=', $date_from);
        }
        if ($date_to) {
            $this->db->where('DATE(a.created_at) <=', $date_to);
        }

        return $this->db->order_by('a.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Get audit statistics
     */
    public function get_audit_stats($date_from = null, $date_to = null)
    {
        $date_from = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_to ?: date('Y-m-d');

        return $this->db->query("
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT patient_no) as unique_patients,
                SUM(event_category = 'RESULT') as result_events,
                SUM(event_category = 'SAMPLE') as sample_events,
                SUM(event_category = 'ORDER') as order_events,
                SUM(event_category = 'ACCESS') as access_events,
                SUM(event_category = 'SECURITY') as security_events,
                SUM(severity = 'CRITICAL') as critical_events,
                SUM(severity = 'WARNING') as warning_events,
                SUM(is_verified = 0) as unverified_records
            FROM diagnostic_audit_log
            WHERE DATE(created_at) BETWEEN ? AND ?
        ", [$date_from, $date_to])->row();
    }

    /**
     * Get events by category over time
     */
    public function get_events_by_category($days = 30)
    {
        return $this->db->query("
            SELECT 
                DATE(created_at) as event_date,
                event_category,
                COUNT(*) as event_count
            FROM diagnostic_audit_log
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at), event_category
            ORDER BY event_date ASC
        ", [$days])->result();
    }

    /**
     * Get chain status summary
     */
    public function get_chain_status()
    {
        return $this->db->query("
            SELECT 
                COUNT(*) as total_chains,
                SUM(is_sealed = 1) as sealed_chains,
                SUM(verification_status = 'VERIFIED') as verified_chains,
                SUM(verification_status = 'FAILED' OR verification_status = 'TAMPERED') as failed_chains,
                MIN(chain_date) as earliest_date,
                MAX(chain_date) as latest_date
            FROM diagnostic_audit_chain
        ")->row();
    }

    /**
     * Get recent verifications
     */
    public function get_recent_verifications($limit = 10)
    {
        return $this->db->select('v.*, u.username as performed_by_name')
            ->from('diagnostic_audit_verification v')
            ->join('user u', 'u.user_id = v.performed_by', 'left')
            ->order_by('v.started_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /* ================================================================== */
    /*  COMPLIANCE EXPORT                                                 */
    /* ================================================================== */

    /**
     * Export audit log for compliance
     */
    public function export_audit_log($filters = [], $format = 'csv')
    {
        $max_records = $this->get_config('max_export_records') ?: 10000;
        $records = $this->search_audit_log($filters, $max_records);

        // Log the export
        $this->log_event('AUDIT_EXPORT', [
            'category' => 'ACCESS',
            'severity' => 'INFO',
            'entity_type' => 'audit_export',
            'action' => 'export',
            'additional' => [
                'filters' => $filters,
                'format' => $format,
                'record_count' => count($records)
            ]
        ]);

        return $records;
    }

    /**
     * Generate compliance report
     */
    public function generate_compliance_report($date_from, $date_to)
    {
        $stats = $this->get_audit_stats($date_from, $date_to);
        $chain_status = $this->get_chain_status();

        // Verify chains in range
        $verification = $this->verify_chain($date_from, $date_to);

        return [
            'period' => ['from' => $date_from, 'to' => $date_to],
            'statistics' => $stats,
            'chain_integrity' => $chain_status,
            'verification_result' => $verification,
            'generated_at' => date('Y-m-d H:i:s'),
            'generated_by' => $this->session->userdata('username')
        ];
    }

    /* ================================================================== */
    /*  CONFIGURATION                                                     */
    /* ================================================================== */

    public function get_config($key)
    {
        $config = $this->db->where('config_key', $key)->get('diagnostic_audit_config')->row();
        if (!$config) return null;

        switch ($config->config_type) {
            case 'INT':
                return (int) $config->config_value;
            case 'BOOL':
                return $config->config_value === '1' || $config->config_value === 'true';
            case 'JSON':
                return json_decode($config->config_value, true);
            default:
                return $config->config_value;
        }
    }

    public function set_config($key, $value)
    {
        $old_value = $this->get_config($key);

        $this->db->where('config_key', $key)->update('diagnostic_audit_config', [
            'config_value' => is_array($value) ? json_encode($value) : $value,
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $this->session->userdata('user_id')
        ]);

        $this->log_config_event($key, $old_value, $value);

        return true;
    }

    public function get_all_config()
    {
        return $this->db->get('diagnostic_audit_config')->result();
    }

    /* ================================================================== */
    /*  RETENTION MANAGEMENT                                              */
    /* ================================================================== */

    /**
     * Apply retention policies
     */
    public function apply_retention_policies()
    {
        $policies = $this->db->where('is_active', 1)->get('diagnostic_audit_retention')->result();
        $archived = 0;
        $deleted = 0;

        foreach ($policies as $policy) {
            $archive_date = date('Y-m-d', strtotime("-{$policy->archive_after_days} days"));
            $delete_date = date('Y-m-d', strtotime("-{$policy->retention_days} days"));

            // Archive old records (in production, would move to archive table/storage)
            $to_archive = $this->db->where('event_category', $policy->event_category)
                ->where('DATE(created_at) <', $archive_date)
                ->count_all_results('diagnostic_audit_log');
            $archived += $to_archive;

            // Delete records past retention period
            // Note: In production, ensure proper backup before deletion
            $this->db->where('event_category', $policy->event_category)
                ->where('DATE(created_at) <', $delete_date)
                ->delete('diagnostic_audit_log');
            $deleted += $this->db->affected_rows();
        }

        $this->log_event('RETENTION_APPLIED', [
            'category' => 'SYSTEM',
            'severity' => 'INFO',
            'entity_type' => 'retention',
            'action' => 'apply_policies',
            'additional' => ['archived' => $archived, 'deleted' => $deleted]
        ]);

        return ['archived' => $archived, 'deleted' => $deleted];
    }

    /**
     * Get retention policies
     */
    public function get_retention_policies()
    {
        return $this->db->get('diagnostic_audit_retention')->result();
    }

    /**
     * Update retention policy
     */
    public function update_retention_policy($category, $retention_days, $archive_after_days)
    {
        $this->db->where('event_category', $category)->update('diagnostic_audit_retention', [
            'retention_days' => $retention_days,
            'archive_after_days' => $archive_after_days
        ]);

        return true;
    }
}
