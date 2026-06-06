<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing Automation Library
 * 
 * Automatically creates billing entries when services are ordered.
 * This library bridges the gap between clinical modules and the
 * unified billing system.
 * 
 * Usage:
 * $this->load->library('billing_automation');
 * $this->billing_automation->on_service_ordered($data);
 */
class Billing_automation {
    
    protected $CI;
    protected $billing_model;
    
    // Service type mappings
    const SERVICE_LABORATORY = 'LABORATORY';
    const SERVICE_RADIOLOGY = 'RADIOLOGY';
    const SERVICE_SONOGRAPHY = 'SONOGRAPHY';
    const SERVICE_PHARMACY = 'PHARMACY';
    const SERVICE_PROCEDURE = 'PROCEDURE';
    const SERVICE_CONSULTATION = 'CONSULTATION';
    const SERVICE_REGISTRATION = 'REGISTRATION';
    
    public function __construct()
    {
        $this->CI =& get_instance();
        $this->CI->load->model('app/Billing_master_model');
        $this->CI->load->model('app/billing_model');
        $this->billing_model = $this->CI->Billing_master_model;
    }
    
    /**
     * Main entry point - called when a service is ordered
     * 
     * @param array $data Service order data
     * @return array Result with success/failure info
     */
    public function on_service_ordered($data)
    {
        // Validate required fields
        $required = ['patient_no', 'visit_id', 'visit_type', 'service_type', 
                     'service_id', 'service_name', 'department', 'requested_by'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                log_message('error', 'Billing Automation: Missing required field: ' . $field);
                return ['success' => false, 'error' => 'Missing required field: ' . $field];
            }
        }
        
        // Get or create a bill for this visit
        $bill = $this->_get_or_create_bill($data);
        
        if (!$bill['success']) {
            return $bill;
        }
        
        // Get pricing for the service
        $price = $this->_get_service_price($data['service_type'], $data['service_id'], $data['patient_no']);
        if ($price === null) {
            return ['success' => false, 'error' => 'Pricing lookup failed'];
        }
        
        // Add the item to the bill
        $item_data = [
            'service_type' => $data['service_type'],
            'service_id' => $data['service_id'],
            'service_name' => $data['service_name'],
            'department' => $data['department'],
            'requested_by' => $data['requested_by'],
            'requested_at' => date('Y-m-d H:i:s'),
            'quantity' => $data['quantity'] ?? 1,
            'unit_price' => $price['unit_price'],
            'discount_amount' => $data['discount'] ?? 0,
            'source_module' => $data['source_module'] ?? null,
            'source_ref_id' => $data['source_ref_id'] ?? null,
            'source_ref_table' => $data['source_ref_table'] ?? null
        ];
        
        $result = $this->billing_model->add_service_to_bill(
            $bill['bill_id'], 
            $item_data, 
            $data['requested_by']
        );
        
        if ($result['success']) {
            log_message('info', sprintf(
                'Billing Automation: Added %s to bill %s for patient %s',
                $data['service_name'],
                $bill['bill_no'],
                $data['patient_no']
            ));
        }
        
        return $result;
    }
    
    /**
     * Called when multiple services are ordered (batch)
     */
    public function on_services_batch_ordered($patient_no, $visit_id, $visit_type, 
                                               $services, $requested_by)
    {
        $results = [];
        
        foreach ($services as $service) {
            $service['patient_no'] = $patient_no;
            $service['visit_id'] = $visit_id;
            $service['visit_type'] = $visit_type;
            $service['requested_by'] = $requested_by;
            
            $results[] = $this->on_service_ordered($service);
        }
        
        return [
            'success' => true,
            'processed' => count($services),
            'results' => $results
        ];
    }
    
    /**
     * Called when a lab test is ordered
     */
    public function on_lab_ordered($patient_no, $visit_id, $visit_type, 
                                      $test_id, $test_name, $requested_by)
    {
        try {
            $this->CI->load->model('app/billing_transaction_model');
            $this->CI->load->model('app/laboratory_model');
            $this->CI->load->model('app/billing_model');
            $this->CI->load->model('app/unified_billing_model');

            $io_lab_id = 0;
            if ($this->CI->db->table_exists('iop_laboratory')) {
                $this->CI->db->select('io_lab_id');
                $this->CI->db->from('iop_laboratory');
                $this->CI->db->where('InActive', 0);
                $this->CI->db->where('iop_id', (string)$visit_id);
                $this->CI->db->where('laboratory_id', (int)$test_id);
                if ($this->CI->db->field_exists('requested_by', 'iop_laboratory')) {
                    $this->CI->db->where('requested_by', (string)$requested_by);
                }
                $this->CI->db->order_by('io_lab_id', 'DESC');
                $this->CI->db->limit(1);
                $row = $this->CI->db->get()->row();
                if ($row && isset($row->io_lab_id)) {
                    $io_lab_id = (int)$row->io_lab_id;
                }
            }

            if ($io_lab_id > 0) {
                $payer_type = null;
                if (isset($this->CI->billing_model) && method_exists($this->CI->billing_model, 'determine_payer_type')) {
                    $payer_type = $this->CI->billing_model->determine_payer_type((string)$patient_no);
                }

                $labName = (string)$test_name;
                $labPrice = 0.0;
                if ($labName === '') { $labName = 'Laboratory Test'; }
                if ($this->CI->db->table_exists('bill_particular')) {
                    $bp = $this->CI->db->select('particular_name, charge_amount')
                        ->get_where('bill_particular', array('particular_id' => (int)$test_id, 'InActive' => 0))
                        ->row();
                    if ($bp) {
                        if (isset($bp->particular_name) && trim((string)$bp->particular_name) !== '') {
                            $labName = (string)$bp->particular_name;
                        }
                        if (isset($bp->charge_amount)) {
                            $labPrice = (float)$bp->charge_amount;
                        }
                    }
                }

                if (isset($this->CI->laboratory_model) && method_exists($this->CI->laboratory_model, 'ensure_lab_charge_posted')) {
                    $this->CI->laboratory_model->ensure_lab_charge_posted(
                        $io_lab_id,
                        (string)$visit_id,
                        (string)$patient_no,
                        (string)$visit_type,
                        (int)$test_id,
                        (string)$test_name,
                        (string)$requested_by,
                        $payer_type
                    );
                }

                if (isset($this->CI->billing_transaction_model) && method_exists($this->CI->billing_transaction_model, 'sync_lab_request')) {
                    $this->CI->billing_transaction_model->sync_lab_request($io_lab_id, $requested_by);
                }

                if (isset($this->CI->unified_billing_model) && method_exists($this->CI->unified_billing_model, 'add_to_billing_queue')) {
                    $res = $this->CI->unified_billing_model->add_to_billing_queue(array(
                        'iop_id' => (string)$visit_id,
                        'patient_no' => (string)$patient_no,
                        'item_type' => 'LAB',
                        'item_id' => (string)(int)$test_id,
                        'item_name' => $labName,
                        'unit_price' => (float)$labPrice,
                        'quantity' => 1,
                        'payer_type' => $payer_type ? (string)$payer_type : 'CASH',
                        'source_module' => 'LAB',
                        'source_ref' => 'io_lab_id:' . (int)$io_lab_id,
                        'requested_by' => (string)$requested_by
                    ));
                    if (!$res || !isset($res['success']) || !$res['success']) {
                        log_message('error', 'Billing Automation: on_lab_ordered add_to_billing_queue failed: ' . json_encode($res));
                    }
                }
                return array('success' => true);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Billing Automation: on_lab_ordered failed: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }

        return array('success' => true);
    }
    
    /**
     * Called when a radiology test is ordered
     */
    public function on_radiology_ordered($patient_no, $visit_id, $visit_type,
                                          $test_id, $test_name, $requested_by)
    {
        try {
            $this->CI->load->model('app/billing_transaction_model');

            $order_id = 0;
            if ($this->CI->db->table_exists('radiology_orders')) {
                $this->CI->db->select('id');
                $this->CI->db->from('radiology_orders');
                $this->CI->db->where('InActive', 0);
                if ($this->CI->db->field_exists('iop_id', 'radiology_orders')) {
                    $this->CI->db->where('iop_id', (string)$visit_id);
                }
                if ($this->CI->db->field_exists('patient_no', 'radiology_orders')) {
                    $this->CI->db->where('patient_no', (string)$patient_no);
                }
                if ($this->CI->db->field_exists('test_id', 'radiology_orders')) {
                    $this->CI->db->where('test_id', (int)$test_id);
                }
                if ($this->CI->db->field_exists('ordered_by', 'radiology_orders')) {
                    $this->CI->db->where('ordered_by', (string)$requested_by);
                }
                $this->CI->db->order_by('id', 'DESC');
                $this->CI->db->limit(1);
                $row = $this->CI->db->get()->row();
                if ($row && isset($row->id)) {
                    $order_id = (int)$row->id;
                }
            }

            if ($order_id > 0 && isset($this->CI->billing_transaction_model) && method_exists($this->CI->billing_transaction_model, 'sync_radiology_order')) {
                $this->CI->billing_transaction_model->sync_radiology_order($order_id, $requested_by, null, null, $visit_type);
            }

            return array('success' => true);
        } catch (\Throwable $e) {
            log_message('error', 'Billing Automation: on_radiology_ordered failed: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }
    }
    
    /**
     * Called when a sonography scan is ordered
     */
    public function on_sonography_ordered($patient_no, $visit_id, $visit_type,
                                           $scan_id, $scan_name, $requested_by)
    {
        try {
            $this->CI->load->model('app/billing_transaction_model');
            $this->CI->load->model('app/laboratory_model');
            $this->CI->load->model('app/unified_billing_model');
            $this->CI->load->model('app/billing_model');

            $io_lab_id = 0;
            if ($this->CI->db->table_exists('iop_laboratory')) {
				$canFilterByRequester = $this->CI->db->field_exists('requested_by', 'iop_laboratory') && (string)$requested_by !== '';
				if ($canFilterByRequester) {
					$this->CI->db->select('io_lab_id');
					$this->CI->db->from('iop_laboratory');
					$this->CI->db->where('InActive', 0);
					$this->CI->db->where('iop_id', (string)$visit_id);
					$this->CI->db->where('laboratory_id', (int)$scan_id);
					$this->CI->db->where('requested_by', (string)$requested_by);
					$this->CI->db->order_by('io_lab_id', 'DESC');
					$this->CI->db->limit(1);
					$row = $this->CI->db->get()->row();
					if ($row && isset($row->io_lab_id)) {
						$io_lab_id = (int)$row->io_lab_id;
					}
				}

				if ($io_lab_id <= 0) {
					$this->CI->db->select('io_lab_id');
					$this->CI->db->from('iop_laboratory');
					$this->CI->db->where('InActive', 0);
					$this->CI->db->where('iop_id', (string)$visit_id);
					$this->CI->db->where('laboratory_id', (int)$scan_id);
					$this->CI->db->order_by('io_lab_id', 'DESC');
					$this->CI->db->limit(1);
					$row = $this->CI->db->get()->row();
					if ($row && isset($row->io_lab_id)) {
						$io_lab_id = (int)$row->io_lab_id;
					}
				}
            }

            if ($io_lab_id > 0) {
                if (isset($this->CI->laboratory_model) && method_exists($this->CI->laboratory_model, 'ensure_sonography_charge_posted')) {
                    $this->CI->laboratory_model->ensure_sonography_charge_posted(
                        $io_lab_id,
                        (string)$visit_id,
                        (string)$patient_no,
                        (string)$visit_type,
                        (int)$scan_id,
                        null,
                        (string)$requested_by
                    );
                }

                $charge_id = 0;
                $rate_amount = null;
                $item_name = null;
                if ($this->CI->db->table_exists('iop_sonography_charge')) {
                    $this->CI->db->select('charge_id, rate_amount, item_name');
                    $this->CI->db->from('iop_sonography_charge');
                    $this->CI->db->where('InActive', 0);
                    $this->CI->db->where('io_lab_id', (int)$io_lab_id);
                    $this->CI->db->order_by('charge_id', 'DESC');
                    $this->CI->db->limit(1);
                    $ch = $this->CI->db->get()->row();
                    if ($ch && isset($ch->charge_id)) {
                        $charge_id = (int)$ch->charge_id;
                        if (isset($ch->rate_amount)) {
                            $rate_amount = (float)$ch->rate_amount;
                        }
                        if (isset($ch->item_name) && trim((string)$ch->item_name) !== '') {
                            $item_name = (string)$ch->item_name;
                        }
                    }
                }

                if ($charge_id > 0 && isset($this->CI->billing_transaction_model) && method_exists($this->CI->billing_transaction_model, 'sync_sonography_charge')) {
                    try {
                        $this->CI->billing_transaction_model->sync_sonography_charge($charge_id, $requested_by);
                    } catch (\Throwable $e) {
                        log_message('error', 'Billing Automation: on_sonography_ordered SSOT sync failed: ' . $e->getMessage());
                    }
                }
                try {
                    if ($charge_id > 0 && isset($this->CI->unified_billing_model) && method_exists($this->CI->unified_billing_model, 'add_to_billing_queue')) {
                        $queue_name = ($item_name !== null && $item_name !== '') ? $item_name : (string)$scan_name;
                        $queue_rate = ($rate_amount !== null) ? (float)$rate_amount : 0.0;
                        $payer_type = 'CASH';
                        if (isset($this->CI->billing_model) && method_exists($this->CI->billing_model, 'determine_payer_type')) {
                            $pt = (string)$this->CI->billing_model->determine_payer_type((string)$patient_no);
                            if ($pt !== '') {
                                $payer_type = strtoupper($pt);
                            }
                        }
						$res = $this->CI->unified_billing_model->add_to_billing_queue(array(
                            'iop_id' => (string)$visit_id,
                            'patient_no' => (string)$patient_no,
                            'item_type' => 'SONOGRAPHY',
                            'item_id' => (string)$charge_id,
                            'item_name' => (string)$queue_name,
                            'quantity' => 1,
                            'unit_price' => (float)$queue_rate,
                            'payer_type' => $payer_type,
                            'source_module' => 'SONOGRAPHY',
                            'source_ref' => 'sono_charge_id:' . (int)$charge_id,
                            'requested_by' => (string)$requested_by
                        ));
						if (!$res || !isset($res['success']) || !$res['success']) {
							log_message('error', 'Billing Automation: on_sonography_ordered add_to_billing_queue failed: ' . json_encode($res));
						}
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Billing Automation: on_sonography_ordered queue failed: ' . $e->getMessage());
                }
                return array('success' => true);
            }
            log_message('debug', 'Billing Automation: on_sonography_ordered skipped - io_lab_id not resolved (iop_id=' . (string)$visit_id . ', scan_id=' . (int)$scan_id . ', requested_by=' . (string)$requested_by . ')');
        } catch (\Throwable $e) {
            log_message('error', 'Billing Automation: on_sonography_ordered failed: ' . $e->getMessage());
            return array('success' => false, 'error' => $e->getMessage());
        }

        return array('success' => true);
    }
    
    /**
     * Called when medication is prescribed
     */
    public function on_medication_prescribed($patient_no, $visit_id, $visit_type,
                                              $medicine_id, $medicine_name, 
                                              $quantity, $requested_by)
    {
        return $this->on_service_ordered([
            'patient_no' => $patient_no,
            'visit_id' => $visit_id,
            'visit_type' => $visit_type,
            'service_type' => self::SERVICE_PHARMACY,
            'service_id' => $medicine_id,
            'service_name' => $medicine_name,
            'department' => 'Pharmacy',
            'quantity' => $quantity,
            'requested_by' => $requested_by,
            'source_module' => 'PHARMACY',
            'source_ref_table' => 'iop_pharmacy'
        ]);
    }
    
    /**
     * Called when a procedure is ordered
     */
    public function on_procedure_ordered($patient_no, $visit_id, $visit_type,
                                            $procedure_id, $procedure_name, $requested_by)
    {
        return $this->on_service_ordered([
            'patient_no' => $patient_no,
            'visit_id' => $visit_id,
            'visit_type' => $visit_type,
            'service_type' => self::SERVICE_PROCEDURE,
            'service_id' => $procedure_id,
            'service_name' => $procedure_name,
            'department' => 'Procedures',
            'requested_by' => $requested_by,
            'source_module' => 'PROCEDURE',
            'source_ref_table' => 'iop_procedure'
        ]);
    }
    
    /**
     * Check if a service can be processed by a department
     */
    public function can_process_service($source_ref_table, $source_ref_id)
    {
        $this->CI->db->where('source_ref_table', $source_ref_table);
        $this->CI->db->where('source_ref_id', $source_ref_id);
        $item = $this->CI->db->get('billing_items')->row();
        
        if (!$item) {
            // No billing record - may be pre-unified billing item
            return ['can_process' => true, 'reason' => 'No billing record found'];
        }
        
        if ($item->gate_status === 'RELEASED') {
            return ['can_process' => true, 'gate_status' => $item->gate_status];
        }
        
        return [
            'can_process' => false, 
            'gate_status' => $item->gate_status,
            'bill_id' => $item->bill_id,
            'item_id' => $item->item_id,
            'reason' => 'Payment required before processing'
        ];
    }
    
    /* ================================================================== */
    /*  PRIVATE HELPERS                                                  */
    /* ================================================================== */
    
    /**
     * Get an existing bill or create a new one for the visit
     */
    private function _get_or_create_bill($data)
    {
        // Look for an existing unpaid bill for this visit
        $this->CI->db->where('patient_no', $data['patient_no']);
        $this->CI->db->where('visit_id', $data['visit_id']);
        $this->CI->db->where_in('payment_status', ['PENDING', 'PARTIAL']);
        $this->CI->db->where('InActive', 0);
        $existing_bill = $this->CI->db->get('billing_master')->row();
        
        if ($existing_bill) {
            return [
                'success' => true,
                'bill_id' => $existing_bill->bill_id,
                'bill_no' => $existing_bill->bill_no,
                'existing' => true
            ];
        }
        
        // Create a new bill
        $bill_data = [
            'patient_no' => $data['patient_no'],
            'visit_id' => $data['visit_id'],
            'visit_type' => $data['visit_type'],
            'created_by' => $data['requested_by'],
            'items' => []  // Will add items separately
        ];
        
        return $this->billing_model->create_bill($bill_data);
    }
    
    /**
     * Get pricing for a service - Uses GHS Standard Catalog
     */
    private function _get_service_price($service_type, $service_id, $patient_no = null)
    {
        $price = 0.0;
        $nhis_price = 0.0;

        if ($service_type === self::SERVICE_PHARMACY) {
            $medicine = $this->CI->db->get_where('medicine_master',
                ['medicine_id' => $service_id])->row();
            $price = $medicine ? (float)($medicine->price ?? 0) : 0.0;
            return ['unit_price' => $price, 'nhis_price' => $nhis_price];
        }

        if (!isset($this->CI->price_engine_model)) {
            $this->CI->load->model('app/Price_engine_model', 'price_engine_model');
        }

        $item_type = 'ITEM_SERVICE';
        switch ($service_type) {
            case self::SERVICE_LABORATORY:
                $item_type = 'ITEM_LAB_TEST';
                break;
            case self::SERVICE_RADIOLOGY:
            case self::SERVICE_SONOGRAPHY:
                $item_type = 'ITEM_IMAGING';
                break;
        }

        // Billing_master_model applies company pricing adjustments. To avoid double-applying
        // pricing_percentage, we only choose between CASH vs NHIS here.
        $base_payer = 'CASH';
        if ($patient_no !== null && trim((string)$patient_no) !== '') {
            if (!isset($this->CI->billing_model)) {
                $this->CI->load->model('app/billing_model');
            }
            if (isset($this->CI->billing_model) && method_exists($this->CI->billing_model, 'determine_payer_type')) {
                $base_payer = strtoupper(trim((string)$this->CI->billing_model->determine_payer_type((string)$patient_no)));
            }
        }
        $base_payer = ($base_payer === 'NHIS') ? 'NHIS' : 'CASH';

        // Resolve effective unit price (NHIS-vs-CASH) via Price Engine
        $resolved = $this->CI->price_engine_model->resolve(array(
            'item_type'  => $item_type,
            'item_id'    => (int)$service_id,
            'patient_no' => (string)$patient_no,
            'payer_type' => $base_payer,
            'quantity'   => 1,
        ));
        if (empty($resolved) || empty($resolved['ok'])) {
            $err = isset($resolved['error']) ? (string)$resolved['error'] : 'Unknown price engine error';
            log_message('error', 'Billing Automation: Price engine resolve failed for service_type=' . (string)$service_type . ' service_id=' . (int)$service_id . ' patient_no=' . (string)$patient_no . ' error=' . $err);
            return null;
        }
        $price = isset($resolved['unit_price']) ? (float)$resolved['unit_price'] : 0.0;

        // Provide a best-effort nhis_price (legacy callers may expect it) without changing
        // the effective unit price used for billing items.
        $resolved_nhis = $this->CI->price_engine_model->resolve(array(
            'item_type'  => $item_type,
            'item_id'    => (int)$service_id,
            'payer_type' => 'NHIS',
            'quantity'   => 1,
        ));
        if (!empty($resolved_nhis) && !empty($resolved_nhis['ok']) && !empty($resolved_nhis['nhis_covered'])) {
            $nhis_price = isset($resolved_nhis['unit_price']) ? (float)$resolved_nhis['unit_price'] : 0.0;
        }

        return ['unit_price' => $price, 'nhis_price' => $nhis_price];
    }
}
