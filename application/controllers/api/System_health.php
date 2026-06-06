<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * System Health Check Controller
 * Scans entire HMS system for broken pages, missing tables, permission issues
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class System_health extends CI_Controller
{
    private $results = [];
    
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }
    
    /**
     * Full System Health Check
     * GET /api/system_health/check
     */
    public function check()
    {
        header('Content-Type: application/json');
        
        $this->results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'overall_status' => 'HEALTHY',
            'score' => 100,
            'modules' => [],
            'database' => [],
            'controllers' => [],
            'views' => [],
            'issues' => [],
            'nhis_integration' => []
        ];
        
        // Run all checks
        $this->_check_database_tables();
        $this->_check_controllers();
        $this->_check_nhis_integration();
        $this->_check_critical_views();
        
        // Calculate score
        $this->_calculate_score();
        
        echo json_encode($this->results, JSON_PRETTY_PRINT);
    }
    
    /**
     * Check required database tables
     */
    private function _check_database_tables()
    {
        $required_tables = [
            // Core tables
            'users' => 'User Management',
            'patient_personal_info' => 'Patient Records',
            'patient_details_iop' => 'OPD Visits',
            'invoice' => 'Billing/Invoices',
            'invoice_item' => 'Invoice Items',
            'medicine_master' => 'Pharmacy',
            'lab_test_master' => 'Laboratory',
            'radiology_test_master' => 'Radiology',
            'department' => 'Departments',
            'doctor' => 'Doctors',
            
            // NHIS tables
            'nhis_config' => 'NHIS Configuration',
            'nhis_memberships' => 'NHIS Members',
            'nhis_claims' => 'NHIS Claims',
            'nhis_claim_items' => 'NHIS Claim Items',
            'nhis_diagnosis' => 'NHIS Diagnoses',
            'nhis_visits' => 'NHIS Visits',
            'nhis_tariffs' => 'NHIS Tariffs',
            'nhis_service_mapping' => 'NHIS Service Mapping',
            'icd10_codes' => 'ICD-10 Codes',
            'claimit_logs' => 'Claim-IT API Logs',
            
            // Billing tables
            'billing_refunds' => 'Refund Management',
            'payment_transactions' => 'Payment Transactions',
            'smart_billing_ledger' => 'Smart Billing',
            'pharmacy_billing_queue' => 'Pharmacy Billing'
        ];
        
        foreach ($required_tables as $table => $description) {
            $exists = $this->db->table_exists($table);
            $count = $exists ? $this->db->count_all($table) : 0;
            
            $this->results['database'][$table] = [
                'description' => $description,
                'exists' => $exists,
                'record_count' => $count,
                'status' => $exists ? 'OK' : 'MISSING'
            ];
            
            if (!$exists) {
                $this->results['issues'][] = [
                    'type' => 'DATABASE',
                    'severity' => 'HIGH',
                    'message' => "Table '{$table}' ({$description}) is missing"
                ];
            }
        }
    }
    
    /**
     * Check critical controllers exist
     */
    private function _check_controllers()
    {
        $controllers = [
            'app/Opd.php' => 'OPD Module',
            'app/Patient.php' => 'Patient Management',
            'app/Billing.php' => 'Billing',
            'app/Ebilling.php' => 'Enterprise Billing',
            'app/Pharmacy.php' => 'Pharmacy',
            'app/Laboratory.php' => 'Laboratory',
            'app/Radiology.php' => 'Radiology',
            'app/Ipd.php' => 'IPD/Inpatient',
            'app/Nhis_claims.php' => 'NHIS Claims',
            'app/Appointment.php' => 'Appointments',
            'app/Ghs_reports.php' => 'GHS Reports',
            'api/Nhis_mock.php' => 'NHIS Mock API'
        ];
        
        $base_path = APPPATH . 'controllers/';
        
        foreach ($controllers as $file => $description) {
            $full_path = $base_path . $file;
            $exists = file_exists($full_path);
            
            $this->results['controllers'][$file] = [
                'description' => $description,
                'exists' => $exists,
                'status' => $exists ? 'OK' : 'MISSING'
            ];
            
            if (!$exists) {
                $this->results['issues'][] = [
                    'type' => 'CONTROLLER',
                    'severity' => 'HIGH',
                    'message' => "Controller '{$file}' ({$description}) is missing"
                ];
            }
        }
    }
    
    /**
     * Check NHIS integration status
     */
    private function _check_nhis_integration()
    {
        $checks = [];
        
        // Check NHIS tables
        $nhis_tables = ['nhis_config', 'nhis_memberships', 'nhis_claims', 'nhis_tariffs', 'icd10_codes', 'nhis_service_mapping'];
        $tables_ok = true;
        foreach ($nhis_tables as $t) {
            if (!$this->db->table_exists($t)) {
                $tables_ok = false;
                break;
            }
        }
        $checks['database_schema'] = ['status' => $tables_ok ? 'OK' : 'MISSING', 'message' => $tables_ok ? 'All NHIS tables exist' : 'Some NHIS tables missing'];
        
        // Check ICD-10 codes seeded
        $icd10_count = $this->db->table_exists('icd10_codes') ? $this->db->count_all('icd10_codes') : 0;
        $checks['icd10_codes'] = ['status' => $icd10_count > 0 ? 'OK' : 'EMPTY', 'count' => $icd10_count];
        
        // Check tariffs seeded
        $tariff_count = $this->db->table_exists('nhis_tariffs') ? $this->db->count_all('nhis_tariffs') : 0;
        $checks['nhis_tariffs'] = ['status' => $tariff_count > 0 ? 'OK' : 'EMPTY', 'count' => $tariff_count];
        
        // Check service mapping
        $mapping_count = $this->db->table_exists('nhis_service_mapping') ? $this->db->count_all('nhis_service_mapping') : 0;
        $checks['service_mapping'] = ['status' => $mapping_count > 0 ? 'OK' : 'EMPTY', 'count' => $mapping_count];
        
        // Check Mock API
        $mock_api_exists = file_exists(APPPATH . 'controllers/api/Nhis_mock.php');
        $checks['mock_api'] = ['status' => $mock_api_exists ? 'OK' : 'MISSING'];
        
        // Check Claim-IT controller
        $claimit_exists = file_exists(APPPATH . 'controllers/app/Nhis_claims.php');
        $checks['claimit_controller'] = ['status' => $claimit_exists ? 'OK' : 'MISSING'];
        
        // Check API mode
        if ($this->db->table_exists('nhis_config')) {
            $mode = $this->db->get_where('nhis_config', ['config_key' => 'nhis_api_mode'])->row();
            $checks['api_mode'] = ['status' => 'OK', 'mode' => $mode ? $mode->config_value : 'MOCK'];
        } else {
            $checks['api_mode'] = ['status' => 'UNKNOWN', 'mode' => 'N/A'];
        }
        
        // Check claims count
        $claims_count = $this->db->table_exists('nhis_claims') ? $this->db->count_all('nhis_claims') : 0;
        $checks['claims'] = ['total' => $claims_count];
        
        // Check active members
        if ($this->db->table_exists('nhis_memberships')) {
            $active = $this->db->where('status', 'ACTIVE')->count_all_results('nhis_memberships');
            $checks['active_members'] = ['count' => $active];
        }
        
        $this->results['nhis_integration'] = $checks;
        
        // Overall NHIS status
        $nhis_ready = $tables_ok && $mock_api_exists && $claimit_exists && $icd10_count > 0 && $tariff_count > 0;
        $this->results['nhis_integration']['overall'] = $nhis_ready ? 'READY' : 'INCOMPLETE';
    }
    
    /**
     * Check critical views exist
     */
    private function _check_critical_views()
    {
        $views = [
            'app/opd/index.php' => 'OPD Dashboard',
            'app/patient/index.php' => 'Patient List',
            'app/billing/index.php' => 'Billing Dashboard',
            'app/pharmacy/worklist.php' => 'Pharmacy Worklist',
            'app/nhis/claimit_dashboard.php' => 'Claim-IT Dashboard',
            'app/nhis/submission_queue.php' => 'NHIS Submission Queue',
            'app/enterprise_billing/refund_management.php' => 'Refund Management'
        ];
        
        $base_path = APPPATH . 'views/';
        
        foreach ($views as $file => $description) {
            $full_path = $base_path . $file;
            $exists = file_exists($full_path);
            
            $this->results['views'][$file] = [
                'description' => $description,
                'exists' => $exists,
                'status' => $exists ? 'OK' : 'MISSING'
            ];
            
            if (!$exists) {
                $this->results['issues'][] = [
                    'type' => 'VIEW',
                    'severity' => 'MEDIUM',
                    'message' => "View '{$file}' ({$description}) is missing"
                ];
            }
        }
    }
    
    /**
     * Calculate overall health score
     */
    private function _calculate_score()
    {
        $total_checks = 0;
        $passed_checks = 0;
        
        // Database checks
        foreach ($this->results['database'] as $item) {
            $total_checks++;
            if ($item['status'] === 'OK') $passed_checks++;
        }
        
        // Controller checks
        foreach ($this->results['controllers'] as $item) {
            $total_checks++;
            if ($item['status'] === 'OK') $passed_checks++;
        }
        
        // View checks
        foreach ($this->results['views'] as $item) {
            $total_checks++;
            if ($item['status'] === 'OK') $passed_checks++;
        }
        
        $score = $total_checks > 0 ? round(($passed_checks / $total_checks) * 100) : 0;
        $this->results['score'] = $score;
        
        if ($score >= 90) {
            $this->results['overall_status'] = 'HEALTHY';
        } elseif ($score >= 70) {
            $this->results['overall_status'] = 'WARNING';
        } else {
            $this->results['overall_status'] = 'CRITICAL';
        }
        
        $this->results['summary'] = [
            'total_checks' => $total_checks,
            'passed' => $passed_checks,
            'failed' => $total_checks - $passed_checks,
            'issues_count' => count($this->results['issues'])
        ];
    }
    
    /**
     * NHIS Readiness Report
     * GET /api/system_health/nhis_readiness
     */
    public function nhis_readiness()
    {
        header('Content-Type: application/json');
        
        $report = [
            'report_title' => 'NHIS Claim-IT Integration Readiness Report',
            'generated_at' => date('Y-m-d H:i:s'),
            'facility' => 'Hebrew Medical Center',
            'modules' => [],
            'mock_api' => [],
            'broken_pages' => [],
            'stability_score' => 0,
            'recommendations' => []
        ];
        
        // Module status
        $modules = [
            'OPD Registration' => $this->_check_module_opd(),
            'Patient Check-In' => $this->_check_module_checkin(),
            'Billing Integration' => $this->_check_module_billing(),
            'Claims Management' => $this->_check_module_claims(),
            'Service Mapping' => $this->_check_module_mapping(),
            'Mock API' => $this->_check_module_mockapi()
        ];
        
        $ok_count = 0;
        foreach ($modules as $name => $status) {
            $report['modules'][$name] = $status;
            if ($status['status'] === 'OK') $ok_count++;
        }
        
        // Mock API endpoints
        $report['mock_api'] = [
            'eligibility' => '/api/nhis_mock/check/{nhis_number}',
            'submit' => '/api/nhis_mock/submit',
            'status' => '/api/nhis_mock/status/{claim_id}',
            'batch_submit' => '/api/nhis_mock/batch_submit',
            'tariffs' => '/api/nhis_mock/tariffs',
            'icd10' => '/api/nhis_mock/icd10',
            'health' => '/api/nhis_mock/health'
        ];
        
        // Calculate stability score
        $report['stability_score'] = round(($ok_count / count($modules)) * 100);
        
        // Overall readiness
        $report['mock_api_ready'] = file_exists(APPPATH . 'controllers/api/Nhis_mock.php');
        $report['live_ready'] = $report['stability_score'] >= 90;
        $report['overall_readiness'] = $report['stability_score'] . '%';
        
        // Recommendations
        if ($report['stability_score'] < 100) {
            foreach ($modules as $name => $status) {
                if ($status['status'] !== 'OK') {
                    $report['recommendations'][] = "Fix {$name}: " . ($status['message'] ?? 'Check module configuration');
                }
            }
        }
        
        if ($report['live_ready']) {
            $report['recommendations'][] = 'System is ready for Ghana NHIS Claim-IT live integration after obtaining API credentials';
        }
        
        echo json_encode($report, JSON_PRETTY_PRINT);
    }
    
    private function _check_module_opd()
    {
        $controller_exists = file_exists(APPPATH . 'controllers/app/Opd.php');
        $view_exists = file_exists(APPPATH . 'views/app/opd/index.php');
        return [
            'status' => ($controller_exists && $view_exists) ? 'OK' : 'ISSUE',
            'controller' => $controller_exists,
            'view' => $view_exists
        ];
    }
    
    private function _check_module_checkin()
    {
        // Check if clinical workflow model exists
        $model_exists = file_exists(APPPATH . 'models/app/Clinical_workflow_model.php');
        return [
            'status' => $model_exists ? 'OK' : 'ISSUE',
            'workflow_model' => $model_exists
        ];
    }
    
    private function _check_module_billing()
    {
        $controller_exists = file_exists(APPPATH . 'controllers/app/Billing.php');
        $ebilling_exists = file_exists(APPPATH . 'controllers/app/Ebilling.php');
        return [
            'status' => ($controller_exists && $ebilling_exists) ? 'OK' : 'ISSUE',
            'billing_controller' => $controller_exists,
            'ebilling_controller' => $ebilling_exists
        ];
    }
    
    private function _check_module_claims()
    {
        $controller_exists = file_exists(APPPATH . 'controllers/app/Nhis_claims.php');
        $table_exists = $this->db->table_exists('nhis_claims');
        return [
            'status' => ($controller_exists && $table_exists) ? 'OK' : 'ISSUE',
            'controller' => $controller_exists,
            'table' => $table_exists
        ];
    }
    
    private function _check_module_mapping()
    {
        $table_exists = $this->db->table_exists('nhis_service_mapping');
        $count = $table_exists ? $this->db->count_all('nhis_service_mapping') : 0;
        return [
            'status' => ($table_exists && $count > 0) ? 'OK' : 'ISSUE',
            'table' => $table_exists,
            'mappings_count' => $count
        ];
    }
    
    private function _check_module_mockapi()
    {
        $exists = file_exists(APPPATH . 'controllers/api/Nhis_mock.php');
        return [
            'status' => $exists ? 'OK' : 'MISSING',
            'file' => $exists
        ];
    }
}
