<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Mock API Controller
 * Simulates Ghana NHIS Claim-IT API for testing
 * 
 * Endpoints:
 * - GET  /api/nhis_mock/check/{nhis_number} - Eligibility Check
 * - POST /api/nhis_mock/submit - Claims Submission
 * - GET  /api/nhis_mock/status/{claim_id} - Claim Status
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class Nhis_mock extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
        
        $this->load->database();
    }
    
    /**
     * Eligibility Check
     * GET /api/nhis_mock/check/{nhis_number}
     */
    public function check($nhis_number = null)
    {
        $this->_log_request('eligibility_check', ['nhis_number' => $nhis_number]);
        
        if (empty($nhis_number)) {
            $this->_error_response('NHIS number is required', 400);
            return;
        }
        
        // Simulate different scenarios based on NHIS number patterns
        $nhis_number = strtoupper(trim($nhis_number));
        
        // Check if member exists in database
        $member = $this->db->get_where('nhis_memberships', ['nhis_number' => $nhis_number])->row();
        
        if ($member) {
            // Return actual member data
            $response = [
                'success' => true,
                'status' => $member->status,
                'data' => [
                    'nhis_number' => $member->nhis_number,
                    'name' => $member->member_name ?? 'NHIS Member',
                    'gender' => 'Unknown',
                    'date_of_birth' => null,
                    'expiry' => $member->expiry_date,
                    'scheme' => 'NHIS',
                    'scheme_type' => 'National Health Insurance',
                    'facility_code' => 'GHS-001',
                    'is_active' => $member->status === 'ACTIVE',
                    'last_verified' => $member->last_verified
                ]
            ];
        } else {
            // Generate mock response based on number pattern
            $response = $this->_generate_mock_eligibility($nhis_number);
        }
        
        $this->_log_response('eligibility_check', $response);
        echo json_encode($response);
    }
    
    /**
     * Generate mock eligibility response
     */
    private function _generate_mock_eligibility($nhis_number)
    {
        // Pattern-based mock responses for testing
        // Numbers ending in 0-5: ACTIVE
        // Numbers ending in 6-7: EXPIRED
        // Numbers ending in 8-9: INVALID
        
        $last_digit = substr($nhis_number, -1);
        
        if (in_array($last_digit, ['0', '1', '2', '3', '4', '5'])) {
            $status = 'ACTIVE';
            $expiry = date('Y-12-31', strtotime('+1 year'));
        } elseif (in_array($last_digit, ['6', '7'])) {
            $status = 'EXPIRED';
            $expiry = date('Y-m-d', strtotime('-3 months'));
        } else {
            $status = 'INVALID';
            $expiry = null;
        }
        
        // Generate mock name
        $first_names = ['Kwame', 'Ama', 'Kofi', 'Akua', 'Yaw', 'Abena', 'Kwesi', 'Efua'];
        $last_names = ['Asante', 'Mensah', 'Owusu', 'Boateng', 'Adjei', 'Osei', 'Appiah', 'Darko'];
        $genders = ['Male', 'Female'];
        
        $name = $first_names[array_rand($first_names)] . ' ' . $last_names[array_rand($last_names)];
        $gender = $genders[array_rand($genders)];
        
        return [
            'success' => $status !== 'INVALID',
            'status' => $status,
            'data' => [
                'nhis_number' => $nhis_number,
                'name' => $name,
                'gender' => $gender,
                'date_of_birth' => date('Y-m-d', strtotime('-' . rand(20, 60) . ' years')),
                'expiry' => $expiry,
                'scheme' => 'NHIS',
                'scheme_type' => 'National Health Insurance',
                'facility_code' => 'GHS-001',
                'is_active' => $status === 'ACTIVE',
                'last_verified' => date('Y-m-d H:i:s')
            ],
            'message' => $status === 'INVALID' ? 'Member not found in NHIS database' : null
        ];
    }
    
    /**
     * Claims Submission
     * POST /api/nhis_mock/submit
     */
    public function submit()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            $input = $this->input->post();
        }
        
        $this->_log_request('claim_submit', $input);
        
        // Validate required fields
        $required = ['claim_number', 'patient_no', 'nhis_number', 'services'];
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->_error_response("Missing required field: {$field}", 400);
                return;
            }
        }
        
        // Validate services
        if (!is_array($input['services']) || count($input['services']) === 0) {
            $this->_error_response('At least one service is required', 400);
            return;
        }
        
        // Calculate total
        $total = 0;
        foreach ($input['services'] as $service) {
            $total += floatval($service['amount'] ?? 0);
        }
        
        // Generate claim reference
        $claim_ref = 'CLM' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Simulate processing (90% success rate)
        $success = rand(1, 10) <= 9;
        
        if ($success) {
            $response = [
                'success' => true,
                'status' => 'ACCEPTED',
                'data' => [
                    'claim_id' => $claim_ref,
                    'claim_number' => $input['claim_number'],
                    'submission_date' => date('Y-m-d H:i:s'),
                    'total_amount' => number_format($total, 2, '.', ''),
                    'services_count' => count($input['services']),
                    'expected_processing_days' => rand(7, 14),
                    'message' => 'Claim submitted successfully. Await processing.'
                ]
            ];
            
            // Update claim status in database if exists
            $this->db->where('claim_number', $input['claim_number']);
            $this->db->update('nhis_claims', [
                'status' => 'SUBMITTED',
                'claimit_reference' => $claim_ref,
                'submission_date' => date('Y-m-d H:i:s')
            ]);
        } else {
            $errors = [
                'Invalid diagnosis code',
                'Service not covered under scheme',
                'Member eligibility expired',
                'Duplicate claim detected',
                'Missing supporting documentation'
            ];
            
            $response = [
                'success' => false,
                'status' => 'REJECTED',
                'data' => [
                    'claim_number' => $input['claim_number'],
                    'rejection_reason' => $errors[array_rand($errors)],
                    'rejection_date' => date('Y-m-d H:i:s')
                ]
            ];
        }
        
        $this->_log_response('claim_submit', $response);
        echo json_encode($response);
    }
    
    /**
     * Claim Status Check
     * GET /api/nhis_mock/status/{claim_id}
     */
    public function status($claim_id = null)
    {
        $this->_log_request('claim_status', ['claim_id' => $claim_id]);
        
        if (empty($claim_id)) {
            $this->_error_response('Claim ID is required', 400);
            return;
        }
        
        // Check database first
        $claim = $this->db->get_where('nhis_claims', ['claimit_reference' => $claim_id])->row();
        
        if (!$claim) {
            $claim = $this->db->get_where('nhis_claims', ['claim_number' => $claim_id])->row();
        }
        
        if ($claim) {
            $response = [
                'success' => true,
                'data' => [
                    'claim_id' => $claim->claimit_reference ?? $claim_id,
                    'claim_number' => $claim->claim_number,
                    'status' => $claim->status,
                    'total_amount' => $claim->total_amount,
                    'approved_amount' => $claim->approved_amount,
                    'submission_date' => $claim->submission_date,
                    'patient_no' => $claim->patient_no
                ]
            ];
        } else {
            // Generate mock status
            $statuses = ['PENDING', 'PROCESSING', 'APPROVED', 'PAID'];
            $status = $statuses[array_rand($statuses)];
            
            $response = [
                'success' => true,
                'data' => [
                    'claim_id' => $claim_id,
                    'status' => $status,
                    'total_amount' => number_format(rand(50, 500), 2, '.', ''),
                    'approved_amount' => $status === 'APPROVED' || $status === 'PAID' 
                        ? number_format(rand(40, 450), 2, '.', '') 
                        : null,
                    'processing_date' => date('Y-m-d H:i:s', strtotime('-' . rand(1, 7) . ' days')),
                    'message' => $this->_get_status_message($status)
                ]
            ];
        }
        
        $this->_log_response('claim_status', $response);
        echo json_encode($response);
    }
    
    /**
     * Batch Claims Submission
     * POST /api/nhis_mock/batch_submit
     */
    public function batch_submit()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        if (empty($input)) {
            $input = $this->input->post();
        }
        
        $this->_log_request('batch_submit', $input);
        
        if (empty($input['claims']) || !is_array($input['claims'])) {
            $this->_error_response('Claims array is required', 400);
            return;
        }
        
        $results = [
            'success' => true,
            'batch_id' => 'BATCH' . date('Ymd') . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT),
            'submitted' => 0,
            'rejected' => 0,
            'claims' => []
        ];
        
        foreach ($input['claims'] as $claim) {
            $success = rand(1, 10) <= 9;
            $claim_ref = 'CLM' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $results['claims'][] = [
                'claim_number' => $claim['claim_number'] ?? 'UNKNOWN',
                'claim_id' => $success ? $claim_ref : null,
                'status' => $success ? 'ACCEPTED' : 'REJECTED',
                'message' => $success ? 'Submitted successfully' : 'Validation failed'
            ];
            
            if ($success) {
                $results['submitted']++;
            } else {
                $results['rejected']++;
            }
        }
        
        $this->_log_response('batch_submit', $results);
        echo json_encode($results);
    }
    
    /**
     * Get Tariff/Service Codes
     * GET /api/nhis_mock/tariffs
     */
    public function tariffs()
    {
        $category = $this->input->get('category');
        
        $this->db->from('nhis_tariffs');
        $this->db->where('is_active', 1);
        if ($category) {
            $this->db->where('category', $category);
        }
        $tariffs = $this->db->get()->result();
        
        $response = [
            'success' => true,
            'count' => count($tariffs),
            'data' => $tariffs
        ];
        
        echo json_encode($response);
    }
    
    /**
     * Get ICD-10 Codes
     * GET /api/nhis_mock/icd10
     */
    public function icd10()
    {
        $search = $this->input->get('q');
        
        $this->db->from('icd10_codes');
        $this->db->where('is_active', 1);
        if ($search) {
            $this->db->group_start();
            $this->db->like('code', $search);
            $this->db->or_like('description', $search);
            $this->db->group_end();
        }
        $this->db->limit(50);
        $codes = $this->db->get()->result();
        
        $response = [
            'success' => true,
            'count' => count($codes),
            'data' => $codes
        ];
        
        echo json_encode($response);
    }
    
    /**
     * Health Check
     * GET /api/nhis_mock/health
     */
    public function health()
    {
        $response = [
            'success' => true,
            'status' => 'healthy',
            'api_version' => '1.0.0',
            'mode' => 'MOCK',
            'timestamp' => date('Y-m-d H:i:s'),
            'endpoints' => [
                'eligibility' => '/api/nhis_mock/check/{nhis_number}',
                'submit' => '/api/nhis_mock/submit',
                'status' => '/api/nhis_mock/status/{claim_id}',
                'batch_submit' => '/api/nhis_mock/batch_submit',
                'tariffs' => '/api/nhis_mock/tariffs',
                'icd10' => '/api/nhis_mock/icd10'
            ]
        ];
        
        echo json_encode($response);
    }
    
    /**
     * Get status message
     */
    private function _get_status_message($status)
    {
        $messages = [
            'PENDING' => 'Claim is awaiting review',
            'PROCESSING' => 'Claim is being processed',
            'APPROVED' => 'Claim has been approved for payment',
            'PAID' => 'Payment has been disbursed',
            'REJECTED' => 'Claim was rejected'
        ];
        return $messages[$status] ?? 'Unknown status';
    }
    
    /**
     * Error response helper
     */
    private function _error_response($message, $code = 400)
    {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message
            ]
        ]);
    }
    
    /**
     * Log API request
     */
    private function _log_request($endpoint, $data)
    {
        log_message('info', "NHIS Mock API Request [{$endpoint}]: " . json_encode($data));
    }
    
    /**
     * Log API response
     */
    private function _log_response($endpoint, $data)
    {
        log_message('info', "NHIS Mock API Response [{$endpoint}]: " . json_encode($data));
        
        // Also log to database if table exists
        if ($this->db->table_exists('claimit_logs')) {
            $this->db->insert('claimit_logs', [
                'endpoint' => $endpoint,
                'request' => json_encode($data),
                'response' => json_encode($data),
                'status' => 'SUCCESS',
                'api_mode' => 'MOCK',
                'user_id' => $this->session->userdata('user_id')
            ]);
        }
    }
}
