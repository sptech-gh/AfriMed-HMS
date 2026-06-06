<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

/**
 * Radiology Controller
 * Manages radiology tests, orders, and results
 * 
 * @author HMS Enterprise Architect
 * @version 1.0
 */
class Radiology extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/radiology_model');
        $this->load->model('app/billing_model');
        $this->load->model('app/diagnostic_safety_model');
        $this->load->model('app/service_gate_audit_model', 'service_gate_audit');
        General::variable();
        require_role(array('admin', 'doctor', 'sonographer', 'nurse', 'receptionist', 'radiology', 'radiologist'));
        static $rad_booted = false;
        if (!$rad_booted) {
            $rad_booted = true;
            $this->radiology_model->harden_radiology_schema();
            $this->diagnostic_safety_model->ensure_all_safety_schemas();
        }
    }
    
    /**
     * Radiology Dashboard
     */
    public function index()
    {
        $data = $this->data;
        $data['title'] = 'Radiology Dashboard';
        $data['pending_orders'] = $this->radiology_model->get_pending_orders();
        $data['completed_today'] = $this->radiology_model->count_completed_today();
        $data['pending_count'] = $this->radiology_model->count_pending_orders();
        $data['tests'] = $this->radiology_model->get_all_tests();
        
        $this->load->view('app/radiology/index', $data);
    }

    public function xray_queue()
    {
        $data = $this->data;
        $data['title'] = 'X-Ray Queue';
        $data['pending_orders'] = $this->radiology_model->get_pending_orders_for_queue('xray');
        $this->load->view('app/radiology/queue', $data);
    }

    public function ecg_queue()
    {
        $data = $this->data;
        $data['title'] = 'ECG Queue';
        $data['pending_orders'] = $this->radiology_model->get_pending_orders_for_queue('ecg');
        $this->load->view('app/radiology/queue', $data);
    }

    public function ct_queue()
    {
        $data = $this->data;
        $data['title'] = 'CT Scan Queue';
        $data['pending_orders'] = $this->radiology_model->get_pending_orders_for_queue('ct');
        $this->load->view('app/radiology/queue', $data);
    }
    
    /**
     * Add new radiology test
     */
    public function add_test()
    {
        require_role('admin');
        
        $data = $this->data;
        $data['title'] = 'Add Radiology Test';
        
        if ($this->input->post()) {
            $test_data = [
                'test_name' => $this->input->post('test_name'),
                'test_code' => $this->input->post('test_code'),
                'nhis_code' => $this->input->post('nhis_code'),
                'price' => $this->input->post('price'),
                'nhis_price' => $this->input->post('nhis_price'),
                'department' => $this->input->post('department'),
                'category' => $this->input->post('category'),
                'is_nhis_covered' => $this->input->post('is_nhis_covered') ? 1 : 0
            ];
            
            $result = $this->radiology_model->add_test($test_data);
            if ($result) {
                $this->session->set_flashdata('success', 'Radiology test added successfully');
                redirect('app/radiology');
            } else {
                $this->session->set_flashdata('error', 'Failed to add test');
            }
        }
        
        $this->load->view('app/radiology/add_test', $data);
    }
    
    /**
     * Order radiology test for patient
     */
    public function order_test($iop_id = null)
    {
        require_role(array('admin', 'doctor'));
        
        $data = $this->data;
        $data['title'] = 'Order Radiology Test';
        $data['tests'] = $this->radiology_model->get_active_tests();
        $data['iop_id'] = $iop_id;
        
        if ($iop_id) {
            $this->load->model('app/opd_model');
            $data['patient'] = $this->opd_model->getPatientDetailsByIopId($iop_id);
        }
        
        if ($this->input->post()) {
            $this->load->model('app/Nhis_validation_model', 'nhis_validation');
            $order_data = [
                'iop_id' => $this->input->post('iop_id'),
                'patient_no' => $this->input->post('patient_no'),
                'test_id' => $this->input->post('test_id'),
                'priority' => $this->input->post('priority') ?: 'normal',
                'clinical_notes' => $this->input->post('clinical_notes'),
                'ordered_by' => $this->session->userdata('user_id')
            ];
            
            $result = $this->radiology_model->create_order($order_data);
            if ($result) {
                try {
                    $validation = $this->nhis_validation->validate_service('RADIOLOGY', (int)$result);
                    log_message(
                        'debug',
                        '[NHIS_VALIDATION] module=RADIOLOGY id=' . (int)$result
                        . ' valid=' . (!empty($validation['valid']) ? '1' : '0')
                        . ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
                    );
                } catch (\Throwable $e) {
                }
                try {
                    $this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
                    $det = $this->diag_fin_state->get_financial_state_detail('RADIOLOGY', (int)$result);
                    $state = isset($det['state']) ? (string)$det['state'] : 'REQUESTED';
                    $ref = isset($det['resolved_item_ref']) ? (string)$det['resolved_item_ref'] : '';
                    log_message('debug', '[DIAG_STATE] module=RADIOLOGY id=' . (int)$result . ' resolved_ref=' . $ref . ' state=' . $state);
                    $dr = $this->diag_fin_state->detect_drift('RADIOLOGY', (int)$result, true);
                    if (isset($dr['drift_types']) && is_array($dr['drift_types']) && !empty($dr['drift_types'])) {
                        $dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : '';
                        $sev = isset($dr['severity']) ? (string)$dr['severity'] : 'NONE';
                        log_message('debug', '[DIAG_DRIFT] module=RADIOLOGY id=' . (int)$result . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
                        if ($sev === 'CRITICAL') {
                            log_message('error', '[DIAG_ALERT] module=RADIOLOGY id=' . (int)$result . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
                        }
                        if (in_array('POLICY_VIOLATION', $dr['drift_types'], true) || in_array('UNDERPAID_RELEASE', $dr['drift_types'], true)) {
                            $pr = isset($dr['policy_reason']) ? (string)$dr['policy_reason'] : '';
                            $msg = in_array('UNDERPAID_RELEASE', $dr['drift_types'], true) ? 'Payment below required threshold' : 'Policy denied but flow continued';
                            log_message('error', '[POLICY_WARNING] module=RADIOLOGY id=' . (int)$result . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev . ' policy_reason=' . $pr . ' msg=' . $msg);
                        }
                    }
                } catch (\Throwable $e) {
                }
                $this->session->set_flashdata('success', 'Radiology order created successfully');
                redirect('app/radiology');
            } else {
                $this->session->set_flashdata('error', 'Failed to create order');
            }
        }
        
        $this->load->view('app/radiology/order', $data);
    }
    
    /**
     * Enter radiology result
     */
    public function result_entry($order_id = null)
    {
        require_role(array('admin', 'sonographer', 'doctor', 'radiology', 'radiologist'));
        
        $data = $this->data;
        $data['title'] = 'Enter Radiology Result';
        
        if ($order_id) {
            $data['order'] = $this->radiology_model->get_order($order_id);
            if (!$data['order']) {
                $this->session->set_flashdata('error', 'Order not found');
                redirect('app/radiology');
            }

            // Payment gate (SSOT-first). Admins bypass; NHIS auto-allowed by gate.
            $this->load->model('app/service_gate_model', 'service_gate');
            $iop_id = isset($data['order']->iop_id) ? $data['order']->iop_id : null;
            $gate = $this->service_gate->check_radiology_gate((int)$order_id, $iop_id);
            $data['service_gate'] = $gate;
            if (!$gate['allowed'] && !has_role('admin')) {
                $this->audit_radiology_gate_event('RAD_GATE_BLOCKED', (int)$order_id, 'result_entry_view', $gate);
				$this->maybe_sample_radiology_gate_parity_check((int)$order_id, $gate);
                $this->session->set_flashdata('error', 'Payment required before result entry: ' . (isset($gate['reason']) ? $gate['reason'] : 'Outstanding balance'));
                redirect('app/radiology');
                return;
            }
        }
        
        if ($this->input->post()) {
			$order_id_post = (int)$this->input->post('order_id');
			if ($order_id_post > 0) {
				$this->db->trans_begin();
				$this->radiology_model->lock_radiology_request_for_update($order_id_post);
				$orderPost = $this->radiology_model->get_order($order_id_post);
				if (!$orderPost) {
					$this->db->trans_rollback();
					$this->session->set_flashdata('error', 'Order not found');
					redirect('app/radiology');
					return;
				}
				$this->load->model('app/service_gate_model', 'service_gate');
				$iop_id_post = isset($orderPost->iop_id) ? $orderPost->iop_id : null;
				$gatePost = $this->service_gate->check_radiology_gate($order_id_post, $iop_id_post);
				if (!$gatePost['allowed'] && !has_role('admin')) {
					$this->audit_radiology_gate_event('RAD_GATE_BLOCKED', (int)$order_id_post, 'result_entry_post', $gatePost);
					$this->maybe_sample_radiology_gate_parity_check((int)$order_id_post, $gatePost);
					$this->db->trans_rollback();
					$this->session->set_flashdata('error', 'Payment required before result entry: ' . (isset($gatePost['reason']) ? $gatePost['reason'] : 'Outstanding balance'));
					redirect('app/radiology');
					return;
				}
			}

			$result_data = [
				'order_id' => $this->input->post('order_id'),
				'findings' => $this->input->post('findings'),
				'impression' => $this->input->post('impression'),
				'recommendations' => $this->input->post('recommendations'),
                'performed_by' => $this->session->userdata('user_id'),
            ];
            
            $result = $this->radiology_model->save_result($result_data);
            if ($result) {
                // Phase 4.5: Detect critical findings and create alerts
                $findings_text = $this->input->post('findings');
                $impression_text = $this->input->post('impression');
                $detected = $this->diagnostic_safety_model->detect_radiology_critical_findings($findings_text, $impression_text);
                
                if (!empty($detected)) {
                    $order = $this->radiology_model->get_order($this->input->post('order_id'));
                    foreach ($detected as $finding) {
                        $this->diagnostic_safety_model->create_radiology_critical_alert(
                            $result,
                            $order->patient_no ?? null,
                            $finding['finding_id'],
                            $findings_text,
                            $order->ordered_by ?? null
                        );
                    }
                    $this->session->set_flashdata('warning', 'CRITICAL FINDING DETECTED: ' . count($detected) . ' critical finding(s) identified. Ordering physician has been notified.');
                }
                
                $this->session->set_flashdata('success', 'Result saved successfully');
				if ($order_id_post > 0) {
					$this->db->trans_commit();
					$this->audit_radiology_gate_event('RAD_GATE_ALLOWED', (int)$order_id_post, 'result_entry_commit', isset($gatePost) ? $gatePost : null);
					$this->maybe_sample_radiology_gate_parity_check((int)$order_id_post, isset($gatePost) ? $gatePost : null);
				}
                redirect('app/radiology');
            } else {
				if ($order_id_post > 0) {
					$this->db->trans_rollback();
				}
                $this->session->set_flashdata('error', 'Failed to save result');
            }
        }
        
        $this->load->view('app/radiology/result', $data);
    }

	private function audit_radiology_gate_event($event_code, $order_id, $action, $gate)
	{
		try {
			$order_id = (int)$order_id;
			$event_code = trim((string)$event_code);
			$action = trim((string)$action);
			if ($order_id <= 0 || $event_code === '') {
				return;
			}
			if (!isset($this->service_gate_audit) || !method_exists($this->service_gate_audit, 'log_event')) {
				return;
			}
			$userId = (string)$this->session->userdata('user_id');
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$item_ref = 'radiology_order_id:' . (int)$order_id;
			$blocked_reason = null;
			if (is_array($gate) && isset($gate['blocked_reason'])) {
				$blocked_reason = (string)$gate['blocked_reason'];
			}
			$allowed = null;
			if (is_array($gate) && array_key_exists('allowed', $gate)) {
				$allowed = ((int)(bool)$gate['allowed']);
			}
			$this->service_gate_audit->log_event(array(
				'event_code' => $event_code,
				'module' => 'RADIOLOGY',
				'item_ref' => $item_ref,
				'user_id' => $userId !== '' ? (int)$userId : null,
				'action' => $action !== '' ? $action : null,
				'blocked_reason' => $blocked_reason,
				'allowed' => $allowed,
				'gate_version' => 'v1',
				'reason' => (is_array($gate) && isset($gate['reason'])) ? (string)$gate['reason'] : null,
				'payload' => array(
					'order_id' => (int)$order_id,
					'gate_version' => 'v1',
					'gate' => $gate,
				),
				'ip' => $ip,
			));
		} catch (\Throwable $e) {
		}
	}

	private function maybe_sample_radiology_gate_parity_check($order_id, $decision = null)
	{
		try {
			$order_id = (int)$order_id;
			if ($order_id <= 0) {
				return;
			}
			$allowed = null;
			if (is_array($decision) && array_key_exists('allowed', $decision)) {
				$allowed = (bool)$decision['allowed'];
			}
			if ($allowed === true) {
				if (rand(1, 100) > 2) {
					return;
				}
			}

			$this->load->model('app/unified_worklist_model', 'unified_worklist');
			$this->load->model('app/service_gate_model', 'service_gate');
			$item_ref = 'radiology_order_id:' . (int)$order_id;
			$row = $this->unified_worklist->get_item_by_ref($item_ref);
			if (!$row) {
				return;
			}
			$iop_id = isset($row['iop_id']) ? $row['iop_id'] : null;
			$patient_no = isset($row['patient_no']) ? $row['patient_no'] : null;
			$backend = $this->service_gate->check_service('RADIOLOGY', (string)(int)$order_id, $iop_id, $patient_no);

			$sql_allowed = isset($row['can_proceed']) ? ((int)$row['can_proceed'] === 1 || $row['can_proceed'] === true) : false;
			$backend_allowed = isset($backend['allowed']) ? (bool)$backend['allowed'] : false;
			$sql_reason = $sql_allowed ? 'ALLOWED' : (isset($row['blocked_reason']) ? (string)$row['blocked_reason'] : null);
			$backend_reason = $backend_allowed ? 'ALLOWED' : (isset($backend['blocked_reason']) ? (string)$backend['blocked_reason'] : null);
			$mismatch = ($sql_allowed !== $backend_allowed) || ($sql_reason !== $backend_reason);
			if (!$mismatch) {
				return;
			}

			$userId = (string)$this->session->userdata('user_id');
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			if (isset($this->service_gate_audit) && method_exists($this->service_gate_audit, 'log_event')) {
				$this->service_gate_audit->log_event(array(
					'event_code' => 'GATE_PARITY_MISMATCH',
					'module' => 'RADIOLOGY',
					'item_ref' => $item_ref,
					'user_id' => $userId !== '' ? (int)$userId : null,
					'action' => 'radiology.sample',
					'blocked_reason' => 'MISMATCH',
					'allowed' => null,
					'gate_version' => 'v1',
					'reason' => 'MISMATCH',
					'payload' => array(
						'gate_version' => 'v1',
						'sql' => array('can_proceed' => $sql_allowed, 'blocked_reason' => $sql_reason),
						'backend' => array('allowed' => $backend_allowed, 'blocked_reason' => $backend_reason, 'status' => isset($backend['status']) ? $backend['status'] : null),
					),
					'ip' => $ip,
				));
			}
		} catch (\Throwable $e) {
		}
	}
    
    /**
     * View radiology report
     */
    public function view_report($order_id)
    {
        $data = $this->data;
        $data['title'] = 'Radiology Report';
        $data['order'] = $this->radiology_model->get_order_with_result($order_id);
        
        if (!$data['order']) {
            $this->session->set_flashdata('error', 'Report not found');
            redirect('app/radiology');
        }
        
        $this->load->view('app/radiology/view_report', $data);
    }
    
    /**
     * Edit radiology test
     * SECURITY: Only admin can edit test prices and details
     */
    public function edit_test($test_id = null)
    {
        require_role('admin'); // Sonographers cannot edit test prices
        
        if (!$test_id) {
            $this->session->set_flashdata('error', 'Test ID required');
            redirect('app/radiology');
        }
        
        $data = $this->data;
        $data['title'] = 'Edit Radiology Test';
        $data['test'] = $this->radiology_model->get_test($test_id);
        
        if (!$data['test']) {
            $this->session->set_flashdata('error', 'Test not found');
            redirect('app/radiology');
        }
        
        if ($this->input->post()) {
            $test_data = [
                'test_name' => $this->input->post('test_name'),
                'test_code' => $this->input->post('test_code'),
                'nhis_code' => $this->input->post('nhis_code'),
                'price' => $this->input->post('price'),
                'nhis_price' => $this->input->post('nhis_price'),
                'department' => $this->input->post('department'),
                'category' => $this->input->post('category'),
                'is_nhis_covered' => $this->input->post('is_nhis_covered') ? 1 : 0,
                'status' => $this->input->post('status') ?: 'active'
            ];
            
            $result = $this->radiology_model->update_test($test_id, $test_data);
            if ($result) {
                $this->session->set_flashdata('success', 'Radiology test updated successfully');
                redirect('app/radiology');
            } else {
                $this->session->set_flashdata('error', 'Failed to update test');
            }
        }
        
        $this->load->view('app/radiology/edit_test', $data);
    }
    
    /**
     * Delete radiology test (soft delete)
     */
    public function delete_test($test_id = null)
    {
        require_role('admin');
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
        
        if (!$test_id) {
            $this->session->set_flashdata('error', 'Test ID required');
            redirect('app/radiology');
        }
        
        $result = $this->radiology_model->delete_test($test_id);
        if ($result) {
            $this->session->set_flashdata('success', 'Radiology test deleted successfully');
        } else {
            $this->session->set_flashdata('error', 'Failed to delete test');
        }
        redirect('app/radiology');
    }
    
    /**
     * AJAX: Get test details
     */
    public function get_test_json($test_id)
    {
        $test = $this->radiology_model->get_test($test_id);
        header('Content-Type: application/json');
        echo json_encode($test ?: ['error' => 'Test not found']);
    }
    
    /**
     * AJAX: Search tests
     */
    public function search_tests_json()
    {
        $term = $this->input->get('term');
        $tests = $this->radiology_model->search_tests($term);
        header('Content-Type: application/json');
        echo json_encode($tests);
    }
}
