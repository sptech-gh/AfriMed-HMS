<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

/**
 * Production Setup Controller
 * 
 * Handles all production hardening migrations and setup tasks
 * Access restricted to admin users only
 */
class Production_setup extends General {
    
    public function __construct(){
        parent::__construct();
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role('admin');

        $this->load->model('app/production_hardening_model');
        $this->load->model('app/billing_transaction_model');
    }
    
    /**
     * Main dashboard
     */
    public function index(){
        $this->data['title'] = 'Production Setup';
        $this->data['migration_status'] = $this->_check_migration_status();
        $this->data['system_status'] = $this->_check_system_status();
        $this->load->view('app/production_setup/dashboard', $this->data);
    }
    
    /**
     * Run all migrations
     */
    public function run_migrations(){
        $results = array();
        
        // Run billing transaction schema
        $this->billing_transaction_model->ensure_billing_transaction_schema();
        $results['billing_transactions'] = 'OK';
        
        // Run production hardening migrations
        $hardening = $this->production_hardening_model->run_all_migrations();
        $results = array_merge($results, $hardening);
        
        // Create session table if using database sessions
        $this->_create_session_table();
        $results['session_table'] = 'OK';
        
        $this->session->set_flashdata('message', '<div class="alert alert-success">All migrations completed successfully.</div>');
        
        echo json_encode(array('ok' => true, 'results' => $results));
    }
    
    /**
     * Convert tables to InnoDB
     */
    public function convert_innodb(){
        $results = $this->production_hardening_model->convert_tables_to_innodb();
        echo json_encode(array('ok' => true, 'results' => $results));
    }
    
    /**
     * Standardize charset
     */
    public function standardize_charset(){
        $count = $this->production_hardening_model->standardize_charset();
        echo json_encode(array('ok' => true, 'tables_converted' => $count));
    }
    
    /**
     * Run daily reconciliation
     */
    public function run_reconciliation(){
        $results = $this->production_hardening_model->run_daily_reconciliation();
        echo json_encode(array('ok' => true, 'results' => $results));
    }
    
    /**
     * View reconciliation issues
     */
    public function reconciliation_issues(){
        $this->db->where('resolved', 0);
        $this->db->order_by('severity', 'ASC');
        $this->db->order_by('created_at', 'DESC');
        $issues = $this->db->get('reconciliation_issues')->result();
        
        $this->data['title'] = 'Reconciliation Issues';
        $this->data['issues'] = $issues;
        $this->load->view('app/production_setup/reconciliation_issues', $this->data);
    }
    
    /**
     * Resolve reconciliation issue
     */
    public function resolve_issue(){
        $issue_id = $this->input->post('issue_id');
        $notes = $this->input->post('resolution_notes');
        
        $this->db->where('issue_id', $issue_id);
        $this->db->update('reconciliation_issues', array(
            'resolved' => 1,
            'resolved_by' => $this->session->userdata('user_id'),
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution_notes' => $notes
        ));
        
        echo json_encode(array('ok' => true));
    }
    
    /**
     * View audit log
     */
    public function audit_log(){
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit(500);
        $logs = $this->db->get('system_audit_log')->result();
        
        $this->data['title'] = 'System Audit Log';
        $this->data['logs'] = $logs;
        $this->load->view('app/production_setup/audit_log', $this->data);
    }
    
    /**
     * View price override log
     */
    public function price_overrides(){
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit(200);
        $overrides = $this->db->get('billing_price_override_log')->result();
        
        $this->data['title'] = 'Price Override Log';
        $this->data['overrides'] = $overrides;
        $this->load->view('app/production_setup/price_overrides', $this->data);
    }
    
    /**
     * System health check
     */
    public function health_check(){
        $health = array();
        
        // Database connection
        $health['database'] = $this->db->conn_id ? 'OK' : 'FAIL';
        
        // Required tables
        $required_tables = array(
            'users', 'patient_personal_info', 'patient_details_iop',
            'iop_billing', 'iop_billing_t', 'iop_receipt',
            'iop_medication', 'iop_laboratory', 'medicine_drug_name'
        );
        
        foreach ($required_tables as $table) {
            $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table));
            $health['table_' . $table] = ($q && $q->num_rows() > 0) ? 'OK' : 'MISSING';
        }
        
        // Check security tables
        $security_tables = array(
            'login_attempts', 'security_audit_log', 'system_audit_log',
            'billing_transactions', 'reconciliation_issues'
        );
        
        foreach ($security_tables as $table) {
            $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table));
            $health['security_' . $table] = ($q && $q->num_rows() > 0) ? 'OK' : 'NOT_INSTALLED';
        }
        
        // Environment check
        $health['environment'] = ENVIRONMENT;
        $health['csrf_protection'] = $this->config->item('csrf_protection') ? 'ENABLED' : 'DISABLED';
        $health['session_database'] = $this->config->item('sess_use_database') ? 'ENABLED' : 'DISABLED';
        
        echo json_encode(array('ok' => true, 'health' => $health));
    }
    
    /**
     * Generate production readiness report
     */
    public function readiness_report(){
        $score = 0;
        $max_score = 100;
        $findings = array();
        
        // Security checks (40 points)
        $security_score = 0;
        
        // CSRF enabled
        if ($this->config->item('csrf_protection')) {
            $security_score += 8;
        } else {
            $findings[] = array('severity' => 'CRITICAL', 'item' => 'CSRF protection disabled');
        }
        
        // Session database
        if ($this->config->item('sess_use_database')) {
            $security_score += 5;
        } else {
            $findings[] = array('severity' => 'HIGH', 'item' => 'Session not using database');
        }
        
        // Login attempts table
        $q = $this->db->query("SHOW TABLES LIKE 'login_attempts'");
        if ($q && $q->num_rows() > 0) {
            $security_score += 7;
        } else {
            $findings[] = array('severity' => 'HIGH', 'item' => 'Brute force protection not installed');
        }
        
        // Security audit log
        $q = $this->db->query("SHOW TABLES LIKE 'security_audit_log'");
        if ($q && $q->num_rows() > 0) {
            $security_score += 5;
        }
        
        // Check bcrypt passwords
        $q = $this->db->query("SELECT COUNT(*) as cnt FROM users WHERE LENGTH(password) = 32");
        $md5_count = $q->row()->cnt;
        if ($md5_count == 0) {
            $security_score += 10;
        } else {
            $findings[] = array('severity' => 'HIGH', 'item' => "$md5_count users still have MD5 passwords");
            $security_score += 5; // Partial credit for migration support
        }
        
        // Environment
        if (ENVIRONMENT === 'production') {
            $security_score += 5;
        } else {
            $findings[] = array('severity' => 'MEDIUM', 'item' => 'Environment not set to production');
        }
        
        $score += $security_score;
        
        // Financial integrity (25 points)
        $financial_score = 0;
        
        // Billing transactions table
        $q = $this->db->query("SHOW TABLES LIKE 'billing_transactions'");
        if ($q && $q->num_rows() > 0) {
            $financial_score += 10;
        } else {
            $findings[] = array('severity' => 'HIGH', 'item' => 'Billing transactions table not installed');
        }
        
        // Price override audit
        $q = $this->db->query("SHOW TABLES LIKE 'billing_price_override_log'");
        if ($q && $q->num_rows() > 0) {
            $financial_score += 8;
        }
        
        // Reconciliation engine
        $q = $this->db->query("SHOW TABLES LIKE 'reconciliation_issues'");
        if ($q && $q->num_rows() > 0) {
            $financial_score += 7;
        }
        
        $score += $financial_score;
        
        // Database hardening (15 points)
        $db_score = 0;
        
        // Check InnoDB usage
        $q = $this->db->query("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND ENGINE = 'InnoDB'");
        $innodb_count = $q->row()->cnt;
        $q = $this->db->query("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()");
        $total_tables = $q->row()->cnt;
        
        if ($total_tables > 0) {
            $innodb_percent = ($innodb_count / $total_tables) * 100;
            if ($innodb_percent >= 90) {
                $db_score += 10;
            } elseif ($innodb_percent >= 50) {
                $db_score += 5;
                $findings[] = array('severity' => 'MEDIUM', 'item' => 'Only ' . round($innodb_percent) . '% tables using InnoDB');
            } else {
                $findings[] = array('severity' => 'HIGH', 'item' => 'Most tables not using InnoDB');
            }
        }
        
        // Check charset
        $q = $this->db->query("SELECT COUNT(*) as cnt FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_COLLATION LIKE 'utf8mb4%'");
        $utf8mb4_count = $q->row()->cnt;
        if ($total_tables > 0 && ($utf8mb4_count / $total_tables) >= 0.9) {
            $db_score += 5;
        }
        
        $score += $db_score;
        
        // Compliance (10 points)
        $compliance_score = 0;
        
        // Consent tracking
        $q = $this->db->query("SHOW TABLES LIKE 'patient_consent'");
        if ($q && $q->num_rows() > 0) {
            $compliance_score += 5;
        } else {
            $findings[] = array('severity' => 'MEDIUM', 'item' => 'Patient consent tracking not installed');
        }
        
        // Data retention policy
        $q = $this->db->query("SHOW TABLES LIKE 'data_retention_policy'");
        if ($q && $q->num_rows() > 0) {
            $compliance_score += 5;
        }
        
        $score += $compliance_score;
        
        // Audit logging (10 points)
        $audit_score = 0;
        
        $q = $this->db->query("SHOW TABLES LIKE 'system_audit_log'");
        if ($q && $q->num_rows() > 0) {
            $audit_score += 10;
        } else {
            $findings[] = array('severity' => 'HIGH', 'item' => 'Central audit system not installed');
        }
        
        $score += $audit_score;
        
        $report = array(
            'score' => $score,
            'max_score' => $max_score,
            'percentage' => round(($score / $max_score) * 100),
            'status' => $score >= 90 ? 'PRODUCTION_READY' : ($score >= 70 ? 'NEEDS_WORK' : 'NOT_READY'),
            'findings' => $findings,
            'breakdown' => array(
                'security' => $security_score . '/40',
                'financial' => $financial_score . '/25',
                'database' => $db_score . '/15',
                'compliance' => $compliance_score . '/10',
                'audit' => $audit_score . '/10'
            )
        );
        
        echo json_encode($report);
    }
    
    // ==================== PRIVATE HELPERS ====================
    
    private function _check_migration_status(){
        $status = array();
        
        $tables = array(
            'billing_transactions',
            'billing_service_gates',
            'billing_price_override_log',
            'patient_consent',
            'data_retention_policy',
            'reconciliation_issues',
            'system_audit_log',
            'login_attempts',
            'security_audit_log',
            'nhis_claims'
        );
        
        foreach ($tables as $table) {
            $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table));
            $status[$table] = ($q && $q->num_rows() > 0);
        }
        
        return $status;
    }
    
    private function _check_system_status(){
        return array(
            'environment' => ENVIRONMENT,
            'csrf_enabled' => $this->config->item('csrf_protection'),
            'session_db' => $this->config->item('sess_use_database'),
            'db_debug' => $this->db->db_debug
        );
    }
    
    private function _create_session_table(){
        $this->db->query("CREATE TABLE IF NOT EXISTS `ci_sessions` (
            `id` varchar(128) NOT NULL,
            `ip_address` varchar(45) NOT NULL,
            `timestamp` int(10) unsigned NOT NULL DEFAULT 0,
            `data` blob NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ci_sessions_timestamp` (`timestamp`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}
