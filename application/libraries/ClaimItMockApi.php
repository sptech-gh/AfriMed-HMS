<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ClaimItMockApi
{
    private $CI;
    private $facility_code = 'GHS-001';
    
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('app/nhis_claimit_model', 'nhis');
    }
    
    public function check_eligibility($nhis_number, $facility_code = null)
    {
        $request = ['nhis_number' => $nhis_number, 'facility_code' => $facility_code ?? $this->facility_code];
        usleep(rand(100000, 300000));
        
        if (preg_match('/^NHIS-\d{3}-\d{4}$/', $nhis_number)) {
            $response = [
                'success' => true,
                'data' => [
                    'nhis_number' => $nhis_number,
                    'member_name' => 'Test Member ' . substr($nhis_number, 5, 3),
                    'status' => 'ACTIVE',
                    'expiry_date' => date('Y-m-d', strtotime('+12 months')),
                    'verified_at' => date('Y-m-d H:i:s')
                ]
            ];
            $status = 'SUCCESS';
        } else {
            $response = ['success' => false, 'error' => ['code' => 'INVALID', 'message' => 'Invalid NHIS number']];
            $status = 'ERROR';
        }
        
        $this->CI->nhis->log_api_call('/mock/claimit/eligibility', $request, $response, $status);
        return $response;
    }
    
    public function authorize_visit($data)
    {
        $request = $data;
        usleep(rand(200000, 400000));
        
        $auth_code = 'AUTH-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $response = [
            'success' => true,
            'data' => [
                'visit_id' => 'VIS-' . date('Ymd') . '-' . rand(1000, 9999),
                'authorization_code' => $auth_code,
                'status' => 'AUTHORIZED'
            ]
        ];
        
        $this->CI->nhis->log_api_call('/mock/claimit/visit', $request, $response, 'SUCCESS');
        return $response;
    }
    
    public function submit_claim($claim_data)
    {
        $request = $claim_data;
        usleep(rand(300000, 600000));
        
        $claimit_ref = 'CLM-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 10));
        $response = [
            'success' => true,
            'data' => [
                'claimit_reference' => $claimit_ref,
                'claim_number' => $claim_data['claim_number'] ?? null,
                'status' => 'ACCEPTED',
                'submitted_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->CI->nhis->log_api_call('/mock/claimit/submit', $request, $response, 'SUCCESS');
        return $response;
    }
    
    public function check_claim_status($claimit_reference)
    {
        $request = ['claimit_reference' => $claimit_reference];
        usleep(rand(100000, 200000));
        
        $statuses = ['PENDING', 'APPROVED', 'APPROVED', 'APPROVED', 'REJECTED'];
        $status = $statuses[array_rand($statuses)];
        
        $response = [
            'success' => true,
            'data' => [
                'claimit_reference' => $claimit_reference,
                'status' => $status,
                'checked_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        if ($status === 'REJECTED') {
            $response['data']['rejection_reason'] = 'Missing documentation';
        }
        
        $this->CI->nhis->log_api_call('/mock/claimit/status', $request, $response, 'SUCCESS');
        return $response;
    }
    
    public function submit_batch($batch_data)
    {
        $request = $batch_data;
        usleep(rand(500000, 1000000));
        
        $response = [
            'success' => true,
            'data' => [
                'batch_reference' => 'BATCH-' . date('Ymd') . '-' . rand(100, 999),
                'claims_received' => count($batch_data['claims'] ?? []),
                'status' => 'PROCESSING',
                'submitted_at' => date('Y-m-d H:i:s')
            ]
        ];
        
        $this->CI->nhis->log_api_call('/mock/claimit/batch', $request, $response, 'SUCCESS');
        return $response;
    }
}
