<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Mock API Controller
 * 
 * Provides mock endpoints that simulate the NHIS Claim-IT API for development/testing.
 * These endpoints return realistic responses to test the integration workflow.
 * 
 * @package     HMS
 * @subpackage  Controllers
 * @category    NHIS Integration
 */
class Nhis_mock_api extends CI_Controller {

    public function __construct() {
        parent::__construct();
        $this->load->config('nhis');
        
        // H-NHIS-5: Block mock API access in production environment
        if (ENVIRONMENT === 'production') {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(array(
                'success' => false,
                'error_code' => 'MOCK_API_DISABLED',
                'message' => 'Mock API is disabled in production environment'
            ));
            exit;
        }
        
        // Set JSON response headers
        header('Content-Type: application/json');
        header('X-Mock-API: true');
        
        // M-NHIS-3: Rate limiting note - in production, the live NHIS API enforces rate limits.
        // For mock API in dev/test, no rate limiting is applied. Production deployments should
        // implement rate limiting at the web server level (e.g., nginx limit_req) or use
        // a dedicated rate limiting service to prevent abuse.
    }

    /**
     * Validate NHIS Card / Check Eligibility
     * POST /app/nhis_mock_api/validate_card
     */
    public function validate_card() {
        $input = $this->_get_json_input();
        $member_id = isset($input['member_id']) ? trim($input['member_id']) : '';

        // Validate input
        if (empty($member_id)) {
            $this->_respond(400, array(
                'success' => false,
                'error_code' => 'MISSING_MEMBER_ID',
                'message' => 'Member ID is required'
            ));
            return;
        }

        // Simulate different scenarios based on member ID patterns
        // INVALID* - Invalid card
        if (stripos($member_id, 'INVALID') !== false) {
            $this->_respond(404, array(
                'success' => false,
                'error_code' => 'MEMBER_NOT_FOUND',
                'message' => 'NHIS Member ID not found in registry'
            ));
            return;
        }

        // EXPIRED* - Expired card
        if (stripos($member_id, 'EXPIRED') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'eligible' => false,
                    'member_id' => $member_id,
                    'name' => 'Expired Card Patient',
                    'gender' => 'Female',
                    'date_of_birth' => '1985-06-20',
                    'card_expiry_date' => date('Y-m-d', strtotime('-60 days')),
                    'scheme_name' => 'NHIS Standard',
                    'scheme_type' => 'informal',
                    'status' => 'EXPIRED',
                    'message' => 'Card has expired. Please renew.'
                )
            ));
            return;
        }

        // SUSPENDED* - Suspended membership
        if (stripos($member_id, 'SUSPENDED') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'eligible' => false,
                    'member_id' => $member_id,
                    'name' => 'Suspended Member',
                    'gender' => 'Male',
                    'date_of_birth' => '1978-03-10',
                    'card_expiry_date' => date('Y-m-d', strtotime('+6 months')),
                    'scheme_name' => 'NHIS Standard',
                    'scheme_type' => 'formal',
                    'status' => 'SUSPENDED',
                    'message' => 'Membership suspended due to premium arrears'
                )
            ));
            return;
        }

        // PARTIAL* - Partial coverage member
        if (stripos($member_id, 'PARTIAL') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'eligible' => true,
                    'member_id' => $member_id,
                    'name' => 'Partial Coverage Patient',
                    'gender' => 'Female',
                    'date_of_birth' => '1992-11-05',
                    'card_expiry_date' => date('Y-m-d', strtotime('+8 months')),
                    'scheme_name' => 'NHIS Basic',
                    'scheme_type' => 'informal',
                    'status' => 'ACTIVE',
                    'coverage_level' => 'basic',
                    'coverage_percentage' => 70,
                    'message' => 'Member eligible with basic coverage (70%)'
                )
            ));
            return;
        }

        // Default: Valid active member with full coverage
        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'eligible' => true,
                'member_id' => $member_id,
                'name' => $this->_generate_mock_name($member_id),
                'gender' => (crc32($member_id) % 2 === 0) ? 'Male' : 'Female',
                'date_of_birth' => date('Y-m-d', strtotime('-' . (20 + (crc32($member_id) % 50)) . ' years')),
                'card_expiry_date' => date('Y-m-d', strtotime('+1 year')),
                'scheme_name' => 'NHIS Standard',
                'scheme_type' => 'formal',
                'status' => 'ACTIVE',
                'coverage_level' => 'standard',
                'coverage_percentage' => 100,
                'message' => 'Member is eligible for services'
            )
        ));
    }

    /**
     * Check Item Coverage
     * POST /app/nhis_mock_api/check_coverage
     */
    public function check_coverage() {
        $input = $this->_get_json_input();
        $item_type = isset($input['item_type']) ? $input['item_type'] : '';
        $item_code = isset($input['item_code']) ? $input['item_code'] : '';
        $item_name = isset($input['item_name']) ? $input['item_name'] : '';

        if (empty($item_type)) {
            $this->_respond(400, array(
                'success' => false,
                'error_code' => 'MISSING_ITEM_TYPE',
                'message' => 'Item type is required'
            ));
            return;
        }

        // Simulate coverage based on item patterns
        // Items containing "NOTCOVERED" are not covered
        if (stripos($item_name, 'NOTCOVERED') !== false || stripos($item_code, 'NC') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'covered' => false,
                    'item_type' => $item_type,
                    'item_code' => $item_code,
                    'coverage_percentage' => 0,
                    'max_limit' => null,
                    'requires_preauth' => false,
                    'formulary_status' => 'not_listed',
                    'message' => 'Item is not covered under NHIS'
                )
            ));
            return;
        }

        // Items containing "RESTRICTED" require pre-authorization
        if (stripos($item_name, 'RESTRICTED') !== false || stripos($item_code, 'RS') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'covered' => true,
                    'item_type' => $item_type,
                    'item_code' => $item_code,
                    'coverage_percentage' => 80,
                    'max_limit' => 500.00,
                    'requires_preauth' => true,
                    'formulary_status' => 'restricted',
                    'message' => 'Item requires pre-authorization'
                )
            ));
            return;
        }

        // Items containing "PARTIAL" have partial coverage
        if (stripos($item_name, 'PARTIAL') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'covered' => true,
                    'item_type' => $item_type,
                    'item_code' => $item_code,
                    'coverage_percentage' => 60,
                    'max_limit' => 200.00,
                    'requires_preauth' => false,
                    'formulary_status' => 'approved',
                    'message' => 'Item has partial coverage (60%)'
                )
            ));
            return;
        }

        // Default: Full coverage
        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'covered' => true,
                'item_type' => $item_type,
                'item_code' => $item_code,
                'coverage_percentage' => 100,
                'max_limit' => null,
                'requires_preauth' => false,
                'formulary_status' => 'approved',
                'message' => 'Item is fully covered'
            )
        ));
    }

    /**
     * Submit Claim
     * POST /app/nhis_mock_api/submit_claim
     */
    public function submit_claim() {
        $input = $this->_get_json_input();
        
        // Validate required fields
        $required = array('claim_number', 'facility_code', 'member_id', 'visit_date', 'total_amount');
        foreach ($required as $field) {
            if (empty($input[$field])) {
                $this->_respond(400, array(
                    'success' => false,
                    'error_code' => 'MISSING_FIELD',
                    'message' => "Required field '{$field}' is missing"
                ));
                return;
            }
        }

        $claim_number = $input['claim_number'];
        $total_amount = (float)$input['total_amount'];

        // Simulate rejection for claims with "REJECT" in claim number
        if (stripos($claim_number, 'REJECT') !== false) {
            $this->_respond(200, array(
                'success' => false,
                'error_code' => 'CLAIM_REJECTED',
                'data' => array(
                    'claim_number' => $claim_number,
                    'status' => 'rejected',
                    'rejection_reason' => 'Mock rejection: Invalid diagnosis codes',
                    'submitted_at' => date('Y-m-d H:i:s')
                ),
                'message' => 'Claim was rejected'
            ));
            return;
        }

        // Simulate validation error for very high amounts
        if ($total_amount > 50000) {
            $this->_respond(200, array(
                'success' => false,
                'error_code' => 'AMOUNT_EXCEEDS_LIMIT',
                'data' => array(
                    'claim_number' => $claim_number,
                    'status' => 'pending_review',
                    'message' => 'Claim amount exceeds automatic approval limit. Manual review required.'
                ),
                'message' => 'Claim requires manual review'
            ));
            return;
        }

        // Generate reference ID
        $reference_id = 'NHIS-' . date('Ymd') . '-' . strtoupper(substr(md5($claim_number . time()), 0, 8));

        // Successful submission
        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'claim_number' => $claim_number,
                'reference_id' => $reference_id,
                'status' => 'submitted',
                'response_code' => 'ACCEPTED',
                'total_amount' => $total_amount,
                'submitted_at' => date('Y-m-d H:i:s'),
                'estimated_processing_days' => 14,
                'message' => 'Claim submitted successfully and queued for processing'
            ),
            'message' => 'Claim accepted'
        ));
    }

    /**
     * Check Claim Status
     * POST /app/nhis_mock_api/claim_status
     */
    public function claim_status() {
        $input = $this->_get_json_input();
        $reference_id = isset($input['reference_id']) ? $input['reference_id'] : '';
        $claim_number = isset($input['claim_number']) ? $input['claim_number'] : '';

        if (empty($reference_id) && empty($claim_number)) {
            $this->_respond(400, array(
                'success' => false,
                'error_code' => 'MISSING_IDENTIFIER',
                'message' => 'Either reference_id or claim_number is required'
            ));
            return;
        }

        $identifier = !empty($reference_id) ? $reference_id : $claim_number;

        // Simulate different statuses based on identifier patterns
        // *APPROVED* - Approved claim
        if (stripos($identifier, 'APPROVED') !== false) {
            $total = isset($input['total_amount']) ? (float)$input['total_amount'] : 1000.00;
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'reference_id' => $reference_id,
                    'claim_number' => $claim_number,
                    'status' => 'approved',
                    'approved_amount' => $total,
                    'approved_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
                    'payment_batch' => 'BATCH-' . date('Ym') . '-001',
                    'expected_payment_date' => date('Y-m-d', strtotime('+30 days')),
                    'message' => 'Claim fully approved'
                )
            ));
            return;
        }

        // *PARTIAL* - Partially approved
        if (stripos($identifier, 'PARTIAL') !== false) {
            $total = isset($input['total_amount']) ? (float)$input['total_amount'] : 1000.00;
            $approved = $total * 0.75;
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'reference_id' => $reference_id,
                    'claim_number' => $claim_number,
                    'status' => 'partial',
                    'claimed_amount' => $total,
                    'approved_amount' => $approved,
                    'rejected_amount' => $total - $approved,
                    'approved_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
                    'partial_reason' => 'Some items not on formulary',
                    'message' => 'Claim partially approved'
                )
            ));
            return;
        }

        // *REJECTED* - Rejected claim
        if (stripos($identifier, 'REJECTED') !== false) {
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'reference_id' => $reference_id,
                    'claim_number' => $claim_number,
                    'status' => 'rejected',
                    'rejection_reason' => 'Member not eligible at time of service',
                    'rejected_at' => date('Y-m-d H:i:s', strtotime('-3 days')),
                    'can_resubmit' => true,
                    'message' => 'Claim rejected'
                )
            ));
            return;
        }

        // *PAID* - Paid claim
        if (stripos($identifier, 'PAID') !== false) {
            $total = isset($input['total_amount']) ? (float)$input['total_amount'] : 1000.00;
            $this->_respond(200, array(
                'success' => true,
                'data' => array(
                    'reference_id' => $reference_id,
                    'claim_number' => $claim_number,
                    'status' => 'paid',
                    'approved_amount' => $total,
                    'paid_amount' => $total,
                    'paid_at' => date('Y-m-d H:i:s', strtotime('-7 days')),
                    'payment_reference' => 'PAY-' . date('Ymd') . '-' . rand(1000, 9999),
                    'message' => 'Claim paid'
                )
            ));
            return;
        }

        // Default: Processing
        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'reference_id' => $reference_id,
                'claim_number' => $claim_number,
                'status' => 'processing',
                'submitted_at' => date('Y-m-d H:i:s', strtotime('-5 days')),
                'estimated_completion' => date('Y-m-d', strtotime('+9 days')),
                'message' => 'Claim is being processed'
            )
        ));
    }

    /**
     * Get Tariff / Price List
     * GET /app/nhis_mock_api/tariff
     */
    public function tariff() {
        $item_type = $this->input->get('type');
        $search = $this->input->get('search');

        // Return mock tariff data
        $tariffs = array(
            'drugs' => array(
                array('code' => 'DRG001', 'name' => 'Paracetamol 500mg', 'unit' => 'tablet', 'price' => 0.50, 'covered' => true),
                array('code' => 'DRG002', 'name' => 'Amoxicillin 250mg', 'unit' => 'capsule', 'price' => 1.20, 'covered' => true),
                array('code' => 'DRG003', 'name' => 'Metformin 500mg', 'unit' => 'tablet', 'price' => 0.80, 'covered' => true),
                array('code' => 'DRG004', 'name' => 'Omeprazole 20mg', 'unit' => 'capsule', 'price' => 2.50, 'covered' => true),
                array('code' => 'DRG005', 'name' => 'Ibuprofen 400mg', 'unit' => 'tablet', 'price' => 0.60, 'covered' => true)
            ),
            'services' => array(
                array('code' => 'SRV001', 'name' => 'OPD Consultation', 'unit' => 'visit', 'price' => 15.00, 'covered' => true),
                array('code' => 'SRV002', 'name' => 'Specialist Consultation', 'unit' => 'visit', 'price' => 30.00, 'covered' => true),
                array('code' => 'SRV003', 'name' => 'Emergency Room', 'unit' => 'visit', 'price' => 50.00, 'covered' => true),
                array('code' => 'SRV004', 'name' => 'Wound Dressing', 'unit' => 'procedure', 'price' => 10.00, 'covered' => true)
            ),
            'labs' => array(
                array('code' => 'LAB001', 'name' => 'Full Blood Count', 'unit' => 'test', 'price' => 25.00, 'covered' => true),
                array('code' => 'LAB002', 'name' => 'Malaria RDT', 'unit' => 'test', 'price' => 10.00, 'covered' => true),
                array('code' => 'LAB003', 'name' => 'Urinalysis', 'unit' => 'test', 'price' => 15.00, 'covered' => true),
                array('code' => 'LAB004', 'name' => 'Blood Glucose', 'unit' => 'test', 'price' => 12.00, 'covered' => true),
                array('code' => 'LAB005', 'name' => 'Liver Function Test', 'unit' => 'test', 'price' => 45.00, 'covered' => true)
            ),
            'radiology' => array(
                array('code' => 'RAD001', 'name' => 'Chest X-Ray', 'unit' => 'exam', 'price' => 40.00, 'covered' => true),
                array('code' => 'RAD002', 'name' => 'Abdominal Ultrasound', 'unit' => 'exam', 'price' => 60.00, 'covered' => true),
                array('code' => 'RAD003', 'name' => 'CT Scan Head', 'unit' => 'exam', 'price' => 250.00, 'covered' => true, 'requires_preauth' => true)
            )
        );

        $result = array();
        if ($item_type && isset($tariffs[$item_type])) {
            $result = $tariffs[$item_type];
        } else {
            foreach ($tariffs as $type => $items) {
                foreach ($items as $item) {
                    $item['type'] = $type;
                    $result[] = $item;
                }
            }
        }

        // Filter by search term
        if ($search) {
            $result = array_filter($result, function($item) use ($search) {
                return stripos($item['name'], $search) !== false || 
                       stripos($item['code'], $search) !== false;
            });
            $result = array_values($result);
        }

        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'items' => $result,
                'count' => count($result),
                'last_updated' => date('Y-m-d')
            )
        ));
    }

    /**
     * Batch Claim Status Check
     * POST /app/nhis_mock_api/batch_status
     */
    public function batch_status() {
        $input = $this->_get_json_input();
        $claims = isset($input['claims']) ? $input['claims'] : array();

        if (empty($claims) || !is_array($claims)) {
            $this->_respond(400, array(
                'success' => false,
                'error_code' => 'INVALID_INPUT',
                'message' => 'Claims array is required'
            ));
            return;
        }

        $results = array();
        $statuses = array('processing', 'approved', 'approved', 'partial', 'rejected', 'paid');

        foreach ($claims as $claim) {
            $ref = isset($claim['reference_id']) ? $claim['reference_id'] : '';
            $num = isset($claim['claim_number']) ? $claim['claim_number'] : '';
            $status = $statuses[array_rand($statuses)];

            $results[] = array(
                'reference_id' => $ref,
                'claim_number' => $num,
                'status' => $status,
                'checked_at' => date('Y-m-d H:i:s')
            );
        }

        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'results' => $results,
                'total_checked' => count($results)
            )
        ));
    }

    /**
     * Health Check / API Status
     * GET /app/nhis_mock_api/health
     */
    public function health() {
        $this->_respond(200, array(
            'success' => true,
            'data' => array(
                'status' => 'healthy',
                'api_version' => '1.0.0',
                'environment' => 'mock',
                'server_time' => date('Y-m-d H:i:s'),
                'uptime' => '99.9%'
            ),
            'message' => 'NHIS Mock API is operational'
        ));
    }

    /**
     * API Documentation
     * GET /app/nhis_mock_api/docs
     */
    public function docs() {
        $docs = array(
            'api_name' => 'NHIS Claim-IT Mock API',
            'version' => '1.0.0',
            'base_url' => base_url('app/nhis_mock_api'),
            'endpoints' => array(
                array(
                    'method' => 'POST',
                    'path' => '/validate_card',
                    'description' => 'Validate NHIS card and check member eligibility',
                    'parameters' => array('member_id' => 'string (required)'),
                    'test_patterns' => array(
                        'INVALID* - Returns invalid card error',
                        'EXPIRED* - Returns expired card',
                        'SUSPENDED* - Returns suspended membership',
                        'PARTIAL* - Returns partial coverage member',
                        'Any other - Returns valid active member'
                    )
                ),
                array(
                    'method' => 'POST',
                    'path' => '/check_coverage',
                    'description' => 'Check if an item is covered by NHIS',
                    'parameters' => array(
                        'item_type' => 'string (required)',
                        'item_code' => 'string',
                        'item_name' => 'string'
                    )
                ),
                array(
                    'method' => 'POST',
                    'path' => '/submit_claim',
                    'description' => 'Submit a claim for processing',
                    'parameters' => array(
                        'claim_number' => 'string (required)',
                        'facility_code' => 'string (required)',
                        'member_id' => 'string (required)',
                        'visit_date' => 'date (required)',
                        'total_amount' => 'decimal (required)',
                        'items' => 'array'
                    )
                ),
                array(
                    'method' => 'POST',
                    'path' => '/claim_status',
                    'description' => 'Check status of a submitted claim',
                    'parameters' => array(
                        'reference_id' => 'string',
                        'claim_number' => 'string'
                    )
                ),
                array(
                    'method' => 'GET',
                    'path' => '/tariff',
                    'description' => 'Get NHIS tariff/price list',
                    'parameters' => array(
                        'type' => 'string (drugs|services|labs|radiology)',
                        'search' => 'string'
                    )
                ),
                array(
                    'method' => 'POST',
                    'path' => '/batch_status',
                    'description' => 'Check status of multiple claims',
                    'parameters' => array('claims' => 'array of {reference_id, claim_number}')
                ),
                array(
                    'method' => 'GET',
                    'path' => '/health',
                    'description' => 'API health check'
                )
            )
        );

        $this->_respond(200, array('success' => true, 'data' => $docs));
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    private function _get_json_input() {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        
        if (!$data) {
            // Fall back to POST data
            $data = $this->input->post();
        }
        
        return $data ?: array();
    }

    private function _respond($http_code, $data) {
        http_response_code($http_code);
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    private function _generate_mock_name($seed) {
        $first_names = array('Kwame', 'Ama', 'Kofi', 'Akua', 'Yaw', 'Abena', 'Kwesi', 'Efua', 'Kwabena', 'Adjoa');
        $last_names = array('Mensah', 'Asante', 'Osei', 'Boateng', 'Owusu', 'Agyeman', 'Amoah', 'Appiah', 'Darko', 'Frimpong');
        
        $hash = crc32($seed);
        $first = $first_names[$hash % count($first_names)];
        $last = $last_names[($hash >> 4) % count($last_names)];
        
        return $first . ' ' . $last;
    }
}
