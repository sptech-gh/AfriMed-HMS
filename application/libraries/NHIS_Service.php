<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Service Library
 * 
 * Centralized NHIS logic for coverage, billing split, and claim generation
 * Single source of truth for all NHIS-related calculations
 */
class NHIS_Service {
    
    protected $CI;
    
    // NHIS Coverage percentages
    const NHIS_COVERAGE_PERCENT = 100; // NHIS covers 100% of approved items
    const NHIS_COPAY_PERCENT = 0;      // Patient copay for NHIS items
    
    // Claim statuses
    const CLAIM_PENDING = 'PENDING';
    const CLAIM_SUBMITTED = 'SUBMITTED';
    const CLAIM_APPROVED = 'APPROVED';
    const CLAIM_REJECTED = 'REJECTED';
    const CLAIM_PAID = 'PAID';
    
    public function __construct(){
        $this->CI =& get_instance();
        $this->CI->load->database();
    }
    
    /**
     * Check if patient is NHIS member
     */
    public function is_nhis_patient($patient_no){
        $this->CI->db->select('Insurance_comp, insurance_no, insurance_card_status');
        $this->CI->db->where('patient_no', $patient_no);
        $patient = $this->CI->db->get('patient_personal_info')->row();
        
        if (!$patient) return false;
        
        $insurance = strtoupper(trim($patient->Insurance_comp));
        $has_nhis = (strpos($insurance, 'NHIS') !== false || $insurance === 'NATIONAL HEALTH INSURANCE');
        $card_active = (!isset($patient->insurance_card_status) || $patient->insurance_card_status === 'ACTIVE');
        
        return $has_nhis && $card_active;
    }
    
    /**
     * Get patient NHIS details
     */
    public function get_nhis_details($patient_no){
        $this->CI->db->select('Insurance_comp, insurance_no, insurance_card_status');
        $this->CI->db->where('patient_no', $patient_no);
        $patient = $this->CI->db->get('patient_personal_info')->row();
        
        if (!$patient) {
            return array('is_nhis' => false);
        }
        
        $is_nhis = $this->is_nhis_patient($patient_no);
        
        return array(
            'is_nhis' => $is_nhis,
            'insurance_company' => $patient->Insurance_comp,
            'member_id' => $patient->insurance_no,
            'card_status' => isset($patient->insurance_card_status) ? $patient->insurance_card_status : 'UNKNOWN'
        );
    }
    
    /**
     * Check if drug is NHIS covered
     */
    public function is_drug_covered($drug_id){
        $this->CI->db->select('is_nhis_covered, nhis_price, cash_price, nPrice');
        $this->CI->db->where('drug_id', $drug_id);
        $drug = $this->CI->db->get('medicine_drug_name')->row();
        
        if (!$drug) return array('covered' => false);
        
        return array(
            'covered' => (isset($drug->is_nhis_covered) && $drug->is_nhis_covered == 1),
            'nhis_price' => isset($drug->nhis_price) ? (float)$drug->nhis_price : 0,
            'cash_price' => isset($drug->cash_price) ? (float)$drug->cash_price : (float)$drug->nPrice
        );
    }
    
    /**
     * Check if service/procedure is NHIS covered
     */
    public function is_service_covered($particular_id){
        $this->CI->db->select('is_nhis_covered, nhis_charge_amount, chargeAmount');
        $this->CI->db->where('particular_id', $particular_id);
        $service = $this->CI->db->get('bill_particular')->row();
        
        if (!$service) return array('covered' => false);
        
        return array(
            'covered' => (isset($service->is_nhis_covered) && $service->is_nhis_covered == 1),
            'nhis_rate' => isset($service->nhis_charge_amount) ? (float)$service->nhis_charge_amount : 0,
            'cash_rate' => (float)$service->chargeAmount
        );
    }
    
    /**
     * Calculate billing split for NHIS patient
     * Returns: nhis_amount, patient_amount, total_amount
     */
    public function calculate_billing_split($patient_no, $items){
        $is_nhis = $this->is_nhis_patient($patient_no);
        
        $result = array(
            'is_nhis' => $is_nhis,
            'total_amount' => 0,
            'nhis_amount' => 0,
            'patient_amount' => 0,
            'items' => array()
        );
        
        foreach ($items as $item) {
            $item_total = (float)$item['quantity'] * (float)$item['unit_price'];
            $nhis_covered = 0;
            $patient_pays = $item_total;
            
            if ($is_nhis) {
                // Check if item is NHIS covered
                $covered = false;
                if (isset($item['item_type'])) {
                    if ($item['item_type'] === 'DRUG' && isset($item['item_id'])) {
                        $coverage = $this->is_drug_covered($item['item_id']);
                        $covered = $coverage['covered'];
                        if ($covered && $coverage['nhis_price'] > 0) {
                            $item_total = (float)$item['quantity'] * $coverage['nhis_price'];
                        }
                    } elseif (in_array($item['item_type'], array('SERVICE', 'LAB', 'IMAGING')) && isset($item['item_id'])) {
                        $coverage = $this->is_service_covered($item['item_id']);
                        $covered = $coverage['covered'];
                        if ($covered && $coverage['nhis_rate'] > 0) {
                            $item_total = (float)$item['quantity'] * $coverage['nhis_rate'];
                        }
                    }
                }
                
                if ($covered) {
                    $nhis_covered = $item_total * (self::NHIS_COVERAGE_PERCENT / 100);
                    $patient_pays = $item_total - $nhis_covered;
                }
            }
            
            $result['items'][] = array(
                'item_name' => isset($item['item_name']) ? $item['item_name'] : '',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'total' => $item_total,
                'nhis_covered' => $nhis_covered,
                'patient_pays' => $patient_pays,
                'is_covered' => ($nhis_covered > 0)
            );
            
            $result['total_amount'] += $item_total;
            $result['nhis_amount'] += $nhis_covered;
            $result['patient_amount'] += $patient_pays;
        }
        
        return $result;
    }
    
    /**
     * Get effective price for item based on patient NHIS status
     */
    public function get_effective_price($item_type, $item_id, $patient_no){
        $is_nhis = $this->is_nhis_patient($patient_no);
        
        if ($item_type === 'DRUG') {
            $coverage = $this->is_drug_covered($item_id);
            if ($is_nhis && $coverage['covered'] && $coverage['nhis_price'] > 0) {
                return array(
                    'price' => $coverage['nhis_price'],
                    'is_nhis_rate' => true,
                    'is_covered' => true
                );
            }
            return array(
                'price' => $coverage['cash_price'],
                'is_nhis_rate' => false,
                'is_covered' => $coverage['covered']
            );
        }
        
        if (in_array($item_type, array('SERVICE', 'LAB', 'IMAGING'))) {
            $coverage = $this->is_service_covered($item_id);
            if ($is_nhis && $coverage['covered'] && $coverage['nhis_rate'] > 0) {
                return array(
                    'price' => $coverage['nhis_rate'],
                    'is_nhis_rate' => true,
                    'is_covered' => true
                );
            }
            return array(
                'price' => $coverage['cash_rate'],
                'is_nhis_rate' => false,
                'is_covered' => $coverage['covered']
            );
        }
        
        return array('price' => 0, 'is_nhis_rate' => false, 'is_covered' => false);
    }
    
    /**
     * Generate NHIS claim for encounter
     */
    public function generate_claim($patient_no, $encounter_id, $user_id = null){
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
            'patient_no' => isset($patient_no) ? $patient_no : null,
            'iop_id' => isset($encounter_id) ? $encounter_id : null
        )));

        $this->_ensure_claims_table();
        
        if (!$this->is_nhis_patient($patient_no)) {
            return array('ok' => false, 'error' => 'Patient is not NHIS member');
        }
        
        // Get patient details
        $nhis_details = $this->get_nhis_details($patient_no);
        
        // Get billable items for encounter
        $items = $this->_get_encounter_billable_items($encounter_id);
        
        if (empty($items)) {
            return array('ok' => false, 'error' => 'No billable items found');
        }
        
        // Calculate totals
        $split = $this->calculate_billing_split($patient_no, $items);
        
        if ($split['nhis_amount'] <= 0) {
            return array('ok' => false, 'error' => 'No NHIS-covered items');
        }
        
        // Generate claim number
        $claim_number = 'NHIS-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Insert claim
        $trace = array(
            'method' => __METHOD__,
            'file' => __FILE__,
            'uri' => function_exists('uri_string') ? uri_string() : null,
            'patient_no' => $patient_no,
            'iop_id' => $encounter_id,
            'claim_number' => $claim_number
        );
        log_message('error', 'NHIS_CLAIM_INSERT_TRACE: ' . json_encode($trace));
        $this->CI->db->insert('nhis_claims', array(
            'claim_number' => $claim_number,
            'patient_no' => $patient_no,
            'encounter_id' => $encounter_id,
            'nhis_member_id' => $nhis_details['member_id'],
            'total_amount' => $split['total_amount'],
            'nhis_amount' => $split['nhis_amount'],
            'patient_amount' => $split['patient_amount'],
            'items_count' => count($split['items']),
            'status' => self::CLAIM_PENDING,
            'created_by' => $user_id,
            'created_at' => date('Y-m-d H:i:s'),
            'InActive' => 0
        ));
        
        $claim_id = $this->CI->db->insert_id();
        
        // Insert claim items
        foreach ($split['items'] as $item) {
            if ($item['nhis_covered'] > 0) {
                $this->CI->db->insert('nhis_claim_items', array(
                    'claim_id' => $claim_id,
                    'item_name' => $item['item_name'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_amount' => $item['total'],
                    'nhis_amount' => $item['nhis_covered'],
                    'patient_amount' => $item['patient_pays'],
                    'created_at' => date('Y-m-d H:i:s')
                ));
            }
        }
        
        return array(
            'ok' => true,
            'claim_id' => $claim_id,
            'claim_number' => $claim_number,
            'nhis_amount' => $split['nhis_amount'],
            'patient_amount' => $split['patient_amount']
        );
    }
    
    /**
     * Get pending claims for submission
     */
    public function get_pending_claims($limit = 100){
        $this->_ensure_claims_table();
        
        $this->CI->db->select('c.*, p.firstname, p.lastname, p.insurance_no');
        $this->CI->db->from('nhis_claims c');
        $this->CI->db->join('patient_personal_info p', 'p.patient_no = c.patient_no', 'left');
        $this->CI->db->where('c.status', self::CLAIM_PENDING);
        $this->CI->db->where('c.InActive', 0);
        $this->CI->db->order_by('c.created_at', 'ASC');
        $this->CI->db->limit($limit);
        
        return $this->CI->db->get()->result();
    }
    
    /**
     * Submit claim to NHIS (placeholder for API integration)
     */
    public function submit_claim($claim_id, $user_id = null){
        $this->_ensure_claims_table();
        
        $claim = $this->CI->db->get_where('nhis_claims', array('claim_id' => $claim_id))->row();
        if (!$claim) {
            return array('ok' => false, 'error' => 'Claim not found');
        }
        
        // TODO: Integrate with actual NHIS Claim-IT API
        // For now, mark as submitted
        $this->CI->db->where('claim_id', $claim_id);
        $this->CI->db->update('nhis_claims', array(
            'status' => self::CLAIM_SUBMITTED,
            'submitted_at' => date('Y-m-d H:i:s'),
            'submitted_by' => $user_id
        ));
        
        return array('ok' => true, 'status' => self::CLAIM_SUBMITTED);
    }
    
    // ==================== PRIVATE HELPERS ====================
    
    private function _ensure_claims_table(){
        $this->CI->db->query("CREATE TABLE IF NOT EXISTS `nhis_claims` (
            `claim_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `claim_number` varchar(50) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `encounter_id` varchar(25) NOT NULL,
            `nhis_member_id` varchar(50) DEFAULT NULL,
            `total_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `nhis_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `patient_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `items_count` int(11) NOT NULL DEFAULT 0,
            `status` varchar(20) NOT NULL DEFAULT 'PENDING',
            `submitted_at` datetime DEFAULT NULL,
            `submitted_by` varchar(25) DEFAULT NULL,
            `response_code` varchar(50) DEFAULT NULL,
            `response_message` text,
            `approved_amount` decimal(18,2) DEFAULT NULL,
            `paid_at` datetime DEFAULT NULL,
            `created_by` varchar(25) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`claim_id`),
            UNIQUE KEY `uq_claim_number` (`claim_number`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_encounter` (`encounter_id`),
            KEY `idx_status` (`status`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $this->CI->db->query("CREATE TABLE IF NOT EXISTS `nhis_claim_items` (
            `item_id` bigint(20) NOT NULL AUTO_INCREMENT,
            `claim_id` bigint(20) NOT NULL,
            `item_name` varchar(255) NOT NULL,
            `quantity` decimal(10,2) NOT NULL DEFAULT 1,
            `unit_price` decimal(18,2) NOT NULL DEFAULT 0,
            `total_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `nhis_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `patient_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            PRIMARY KEY (`item_id`),
            KEY `idx_claim` (`claim_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    
    private function _get_encounter_billable_items($encounter_id){
        $items = array();
        
        // Get medications
        $meds = $this->CI->db->query("
            SELECT m.iop_med_id, m.medicine_id, m.qty, d.drug_name, d.nPrice
            FROM iop_medication m
            JOIN medicine_drug_name d ON d.drug_id = m.medicine_id
            WHERE m.iop_id = ? AND m.InActive = 0
        ", array($encounter_id))->result();
        
        foreach ($meds as $med) {
            $items[] = array(
                'item_type' => 'DRUG',
                'item_id' => $med->medicine_id,
                'item_name' => $med->drug_name,
                'quantity' => $med->qty,
                'unit_price' => $med->nPrice
            );
        }
        
        // Get lab tests — use iop_lab_billing for rate/nhis data, bill_particular for name
        $labQ = $this->CI->db->query("
            SELECT
                L.io_lab_id,
                L.laboratory_id,
                COALESCE(LB.item_name, BP.particular_name, L.laboratory_text, 'Laboratory Test') AS item_name,
                COALESCE(BP.nhis_price, LB.rate_amount, BP.charge_amount, 0)                      AS nhis_unit_price,
                COALESCE(LB.rate_amount, BP.charge_amount, 0)                                     AS unit_price,
                COALESCE(LB.nhis_flag, 0)                                                         AS nhis_flag,
                COALESCE(BP.is_nhis_covered, 0)                                                    AS is_nhis_covered
            FROM iop_laboratory L
            LEFT JOIN iop_lab_billing LB ON LB.io_lab_id = L.io_lab_id AND LB.InActive = 0
            LEFT JOIN bill_particular BP ON BP.particular_id = L.laboratory_id
            WHERE L.iop_id = ? AND L.InActive = 0 AND L.category_id != 18
        ", array($encounter_id));
        $labs = $labQ ? $labQ->result() : array();

        foreach ($labs as $lab) {
            $isNhis = (int)$lab->nhis_flag === 1 || (int)$lab->is_nhis_covered === 1;
            $items[] = array(
                'item_type'  => 'LAB',
                'item_id'    => (int)$lab->laboratory_id,
                'item_name'  => $lab->item_name,
                'quantity'   => 1,
                'unit_price' => $isNhis && (float)$lab->nhis_unit_price > 0 ? (float)$lab->nhis_unit_price : (float)$lab->unit_price,
            );
        }

        // Get sonography/imaging items
        $sonoQ = $this->CI->db->query("
            SELECT
                SC.scan_item_id,
                COALESCE(SI.item_name, SC.clinical_question, 'Sonography') AS item_name,
                COALESCE(SC.nhis_price, SC.rate_amount, 0)                 AS nhis_unit_price,
                COALESCE(SC.rate_amount, 0)                                AS unit_price,
                COALESCE(SC.nhis_flag, 0)                                  AS nhis_flag
            FROM iop_sonography_charge SC
            LEFT JOIN sonography_items SI ON SI.item_id = SC.scan_item_id
            WHERE SC.iop_id = ? AND SC.InActive = 0
        ", array($encounter_id));
        $sonos = $sonoQ ? $sonoQ->result() : array();

        foreach ($sonos as $sono) {
            $isNhis = (int)$sono->nhis_flag === 1;
            $items[] = array(
                'item_type'  => 'IMAGING',
                'item_id'    => (int)$sono->scan_item_id,
                'item_name'  => $sono->item_name,
                'quantity'   => 1,
                'unit_price' => $isNhis && (float)$sono->nhis_unit_price > 0 ? (float)$sono->nhis_unit_price : (float)$sono->unit_price,
            );
        }

        return $items;
    }
}
