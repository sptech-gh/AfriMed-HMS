<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once(APPPATH.'controllers/General.php');

/**
 * Unified Billing Controller
 * SINGLE DASHBOARD for all billing operations
 * 
 * Replaces: Cashier Dashboard + Billing & Finance Dashboard
 * 
 * @author HMS Enterprise Architect
 * @version 3.0 - Unified Billing
 */
class Unified_billing extends General {
    
    private $user_id;
    private $user_role;
    
    public function __construct(){
        parent::__construct();

		$enabled = getenv('UNIFIED_BILLING_UI_ENABLED');
		$enabled = is_string($enabled) ? strtolower(trim($enabled)) : '';
		if (!in_array($enabled, array('1', 'true', 'yes', 'on'), true)) {
			redirect('app/cashier/payments');
			return;
		}
        
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        
        // Load billing-specific dependencies
        $this->load->model('app/Billing_master_model');
        $this->load->helper('general');
        
        // Authentication
        $this->user_id = $this->session->userdata('user_id');
        $this->user_role = get_role_key();
        
        // Ensure schema exists
        if (!$this->session->userdata('_schema_billing_master_ok')) {
            $this->Billing_master_model->ensure_schema();
            $this->session->set_userdata('_schema_billing_master_ok', 1);
        }
        
        // Load view data
        $this->data['user_id'] = $this->user_id;
        $this->data['user_role'] = $this->user_role;
    }
    
    /**
     * Main Dashboard - UNIFIED SINGLE VIEW
     */
    public function index()
    {
        // Role-based access check
        if (!$this->_can_access_billing()) {
            $this->session->set_flashdata('error', 'You do not have access to billing.');
            redirect('app/dashboard');
        }
        
        // Get dashboard data
        $date = $this->input->get('date') ?? date('Y-m-d');
        
        $this->data['page_title'] = 'Unified Billing Dashboard';
        $this->data['summary'] = $this->Billing_master_model->get_dashboard_summary($date);
        $this->data['pending_bills'] = $this->Billing_master_model->get_pending_bills($date);
        $this->data['filter_date'] = $date;
        
        // Department access for service gates
        $this->data['can_collect_payment'] = $this->_can_collect_payment();
        $this->data['can_collect'] = $this->_can_collect_payment();
        $this->data['can_apply_discount'] = $this->_can_apply_discount();
        $this->data['can_override'] = $this->_can_override();
        $this->data['is_admin'] = $this->_is_admin();
        
        $this->load->view('app/unified_billing/dashboard', $this->data);
    }
    
    /**
     * Today's Bills
     */
    public function today()
    {
        $date = date('Y-m-d');
        $from = $date . ' 00:00:00';
        $to = date('Y-m-d', strtotime($date . ' +1 day')) . ' 00:00:00';
        
        $this->db->select('b.*, CONCAT(p.lastname, " ", p.firstname) as patient_name');
        $this->db->from('billing_master b');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        $this->db->where('b.created_at >=', $from);
        $this->db->where('b.created_at <', $to);
        $this->db->where('b.InActive', 0);
        $this->db->order_by('b.created_at', 'DESC');
        
        $this->data['page_title'] = "Today's Bills";
        $this->data['bills'] = $this->db->get()->result();
        $this->data['can_collect'] = $this->_can_collect_payment();
		$bill_ids = array();
		foreach ($this->data['bills'] as $b) {
			if (isset($b->bill_id)) {
				$bill_ids[] = $b->bill_id;
			}
		}
		$this->data['latest_payments'] = $this->Billing_master_model->get_latest_payments_for_bills($bill_ids);
        
        $this->load->view('app/unified_billing/bills_list', $this->data);
    }
    
    /**
     * Pending Bills
     */
    public function pending()
    {
        $department = $this->input->get('department');
        
        $this->data['page_title'] = 'Pending Bills';
        $this->data['bills'] = $this->Billing_master_model->get_pending_bills(date('Y-m-d'), $department);
        $this->data['department'] = $department;
        $this->data['can_collect'] = $this->_can_collect_payment();
		$bill_ids = array();
		foreach ($this->data['bills'] as $b) {
			if (isset($b->bill_id)) {
				$bill_ids[] = $b->bill_id;
			}
		}
		$this->data['latest_payments'] = $this->Billing_master_model->get_latest_payments_for_bills($bill_ids);
        
        $this->load->view('app/unified_billing/bills_list', $this->data);
    }
    
    /**
     * View Single Bill
     */
    public function view_bill($bill_id)
    {
        $bill = $this->Billing_master_model->get_bill($bill_id);
        
        if (!$bill) {
            $this->session->set_flashdata('error', 'Bill not found.');
            redirect('app/unified_billing');
        }
        
        $this->data['page_title'] = 'Bill #' . $bill->bill_no;
        $this->data['bill'] = $bill;
        $this->data['items'] = $this->Billing_master_model->get_bill_items($bill_id);
        $this->data['payments'] = $this->Billing_master_model->get_bill_payments($bill_id);
        $this->data['can_collect'] = $this->_can_collect_payment() && $bill->payment_status !== 'PAID';
        $this->data['can_discount'] = $this->_can_apply_discount();
        
        $this->load->view('app/unified_billing/bill_detail', $this->data);
    }
    
    /**
     * Record Payment - AJAX Endpoint
     */
    public function record_payment()
    {
        if (!$this->_can_collect_payment()) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        header('Content-Type: application/json');
        
        $data = [
            'bill_id' => $this->input->post('bill_id'),
            'amount' => $this->input->post('amount'),
            'payment_method' => $this->input->post('payment_method'),
            'reference_no' => $this->input->post('reference_no'),
            'notes' => $this->input->post('notes'),
            'collected_by' => $this->user_id
        ];
        
        $result = $this->Billing_master_model->record_payment($data);
        echo json_encode($result);
    }
    
    /**
     * Apply Discount - AJAX Endpoint
     */
    public function apply_discount()
    {
        if (!$this->_can_apply_discount()) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }
        
        header('Content-Type: application/json');
        
        $data = [
            'bill_id' => $this->input->post('bill_id'),
            'item_id' => $this->input->post('item_id'),
            'amount' => $this->input->post('amount'),
            'reason' => $this->input->post('reason'),
            'approved_by' => $this->input->post('approved_by') ?? $this->user_id,
            'adjusted_by' => $this->user_id
        ];
        
        $result = $this->Billing_master_model->apply_discount($data);
        echo json_encode($result);
    }
    
    /**
     * Search Bills
     */
    public function search()
    {
        $term = $this->input->get('term');
        $status = $this->input->get('status');
        $from = $this->input->get('from');
        $to = $this->input->get('to');
        
        $this->db->select('b.*, CONCAT(p.lastname, " ", p.firstname) as patient_name');
        $this->db->from('billing_master b');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        
        if ($term) {
            $this->db->group_start();
            $this->db->like('b.bill_no', $term);
            $this->db->or_like('p.firstname', $term);
            $this->db->or_like('p.lastname', $term);
            $this->db->or_like('b.patient_no', $term);
            $this->db->group_end();
        }
        
        if ($status) {
            $this->db->where('b.payment_status', $status);
        }
        
        if ($from) {
            $this->db->where('DATE(b.created_at) >=', $from);
        }
        
        if ($to) {
            $this->db->where('DATE(b.created_at) <=', $to);
        }
        
        $this->db->where('b.InActive', 0);
        $this->db->order_by('b.created_at', 'DESC');
        
        $this->data['page_title'] = 'Search Bills';
        $this->data['bills'] = $this->db->get()->result();
        $this->data['search_params'] = compact('term', 'status', 'from', 'to');
		$bill_ids = array();
		foreach ($this->data['bills'] as $b) {
			if (isset($b->bill_id)) {
				$bill_ids[] = $b->bill_id;
			}
		}
		$this->data['latest_payments'] = $this->Billing_master_model->get_latest_payments_for_bills($bill_ids);
        
        $this->load->view('app/unified_billing/search', $this->data);
    }
    
    /**
     * Patient Billing History
     */
    public function patient_bills($patient_no)
    {
        $this->data['page_title'] = 'Patient Billing History';
        $this->data['patient'] = $this->_get_patient($patient_no);
        $this->data['bills'] = $this->Billing_master_model->get_patient_bills($patient_no);
        $this->data['can_collect'] = $this->_can_collect_payment();
		$bill_ids = array();
		foreach ($this->data['bills'] as $b) {
			if (isset($b->bill_id)) {
				$bill_ids[] = $b->bill_id;
			}
		}
		$this->data['latest_payments'] = $this->Billing_master_model->get_latest_payments_for_bills($bill_ids);
        
        $this->load->view('app/unified_billing/patient_bills', $this->data);
    }
    
    /**
     * Print Receipt
     */
    public function print_receipt($payment_id)
    {
        $this->db->select('p.*, b.bill_no, b.patient_no, b.visit_id, b.visit_type, b.total_amount, b.discount_amount, b.net_amount, b.paid_amount, b.balance_due, b.payment_status, b.payer_type');
        if ($this->db->table_exists('billing_payment_links')) {
			$this->db->select('L.invoice_no as legacy_invoice_no, L.receipt_no as legacy_receipt_no');
		}
        $this->db->from('billing_payments p');
        $this->db->join('billing_master b', 'b.bill_id = p.bill_id', 'left');
        if ($this->db->table_exists('billing_payment_links')) {
			$this->db->join('billing_payment_links L', 'L.payment_id = p.payment_id AND L.InActive = 0', 'left');
		}
        $this->db->where('p.payment_id', $payment_id);
        $this->db->where('p.InActive', 0);
        
        $payment = $this->db->get()->row();
        
        if (!$payment) {
            show_error('Payment not found');
            return;
        }
        
        $this->data['payment'] = $payment;
        $this->data['patient'] = $this->_get_patient($payment->patient_no);

		$this->data['receipt_payment_method_label'] = isset($payment->payment_method) ? strtoupper(trim((string)$payment->payment_method)) : '';
		$this->data['receipt_cashier_id'] = isset($payment->collected_by) ? trim((string)$payment->collected_by) : '';
		$this->data['receipt_cashier_name'] = '';
		$this->data['receipt_outstanding_balance'] = isset($payment->balance_due) ? (float)$payment->balance_due : null;

		$legacy_invoice_no = isset($payment->legacy_invoice_no) ? trim((string)$payment->legacy_invoice_no) : '';
		$legacy_receipt_no = isset($payment->legacy_receipt_no) ? trim((string)$payment->legacy_receipt_no) : '';
		if ($legacy_invoice_no !== '') {
			$this->load->model('app/billing_model');
			$payload = $this->billing_model->build_receipt_print_payload($legacy_invoice_no, ($legacy_receipt_no !== '' ? $legacy_receipt_no : null));
			$okMatch = true;
			if ($legacy_receipt_no !== '') {
				$okMatch = (isset($payload['getOR']) && $payload['getOR'] && isset($payload['getOR']->receipt_no)
					&& trim((string)$payload['getOR']->receipt_no) === $legacy_receipt_no);
			}
			if ($okMatch) {
				$this->data = array_merge($this->data, $payload);
			}
		}

		if ($this->data['receipt_cashier_name'] === '' && $this->data['receipt_cashier_id'] !== '') {
			$this->load->model('app/user_model');
			$u = $this->user_model->getUser($this->data['receipt_cashier_id']);
			if ($u) {
				$nm = trim((string)(isset($u->firstname) ? $u->firstname : '') . ' ' . (string)(isset($u->lastname) ? $u->lastname : ''));
				$this->data['receipt_cashier_name'] = ($nm !== '' ? $nm : $this->data['receipt_cashier_id']);
			} else {
				$this->data['receipt_cashier_name'] = $this->data['receipt_cashier_id'];
			}
		}
        
        $this->load->view('app/unified_billing/receipt_print', $this->data);
    }

	public function print_official_receipt($payment_id)
	{
		$this->require_report_scope('financial');
		$this->audit_report_generation('financial', 'unified_billing_print_or', 'browser');

		$invoice_no = '';
		$receipt_no = '';
		$patient_no = '';

		if ($this->db->table_exists('billing_payment_links')) {
			$this->db->select('L.invoice_no, L.receipt_no, p.patient_no');
			$this->db->from('billing_payment_links L');
			$this->db->join('billing_payments p', 'p.payment_id = L.payment_id', 'left');
			$this->db->where('L.payment_id', $payment_id);
			$this->db->where('L.InActive', 0);
			$this->db->limit(1);
			$row = $this->db->get()->row();
			if ($row) {
				$invoice_no = isset($row->invoice_no) ? trim((string)$row->invoice_no) : '';
				$receipt_no = isset($row->receipt_no) ? trim((string)$row->receipt_no) : '';
				$patient_no = isset($row->patient_no) ? trim((string)$row->patient_no) : '';
			}
		}

		if ($invoice_no === '') {
			show_error('Legacy invoice/receipt is not linked to this payment');
			return;
		}

		$this->load->model('app/billing_model');
		$payload = $this->billing_model->build_receipt_print_payload($invoice_no, ($receipt_no !== '' ? $receipt_no : null));
		$this->data = array_merge($this->data, $payload);

		if ($patient_no === '' && isset($this->data['headerInv']) && $this->data['headerInv'] && isset($this->data['headerInv']->patient_no)) {
			$patient_no = trim((string)$this->data['headerInv']->patient_no);
		}
		$this->load->model('app/patient_model');
		$pi = ($patient_no !== '' && isset($this->patient_model) && method_exists($this->patient_model, 'getPatientInfo'))
			? $this->patient_model->getPatientInfo($patient_no)
			: null;
		if (!$pi) {
			$pi = new stdClass();
			$pi->name = '';
			$pi->street = '';
			$pi->subd_brgy = '';
			$pi->province = '';
			$pi->phone_no = '';
			if ($patient_no !== '') {
				$p = $this->_get_patient($patient_no);
				if ($p) {
					$pi->name = trim((string)$p->lastname . ' ' . (string)$p->firstname);
					$pi->phone_no = isset($p->mobile_no) ? (string)$p->mobile_no : '';
				}
			}
		}
		$this->data['patientInfo'] = $pi;

		$this->load->view('app/opd/printOR', $this->data);
	}

	public function print_official_receipt_pdf($payment_id)
	{
		$this->require_report_scope('financial');
		$this->audit_report_generation('financial', 'unified_billing_print_or', 'pdf');

		$invoice_no = '';
		$receipt_no = '';
		$patient_no = '';

		if ($this->db->table_exists('billing_payment_links')) {
			$this->db->select('L.invoice_no, L.receipt_no, p.patient_no');
			$this->db->from('billing_payment_links L');
			$this->db->join('billing_payments p', 'p.payment_id = L.payment_id', 'left');
			$this->db->where('L.payment_id', $payment_id);
			$this->db->where('L.InActive', 0);
			$this->db->limit(1);
			$row = $this->db->get()->row();
			if ($row) {
				$invoice_no = isset($row->invoice_no) ? trim((string)$row->invoice_no) : '';
				$receipt_no = isset($row->receipt_no) ? trim((string)$row->receipt_no) : '';
				$patient_no = isset($row->patient_no) ? trim((string)$row->patient_no) : '';
			}
		}

		if ($invoice_no === '') {
			show_error('Legacy invoice/receipt is not linked to this payment');
			return;
		}

		$this->load->helper('file');
		$this->load->helper('dompdf');

		$this->load->model('app/billing_model');
		$payload = $this->billing_model->build_receipt_print_payload($invoice_no, ($receipt_no !== '' ? $receipt_no : null));
		$this->data = array_merge($this->data, $payload);
		$this->data['ORNUM'] = isset($this->data['getOR']) ? $this->data['getOR'] : null;

		if ($patient_no === '' && isset($this->data['headerInv']) && $this->data['headerInv'] && isset($this->data['headerInv']->patient_no)) {
			$patient_no = trim((string)$this->data['headerInv']->patient_no);
		}
		$this->load->model('app/patient_model');
		$pi = ($patient_no !== '' && isset($this->patient_model) && method_exists($this->patient_model, 'getPatientInfo'))
			? $this->patient_model->getPatientInfo($patient_no)
			: null;
		if (!$pi) {
			$pi = new stdClass();
			$pi->name = '';
			$pi->street = '';
			$pi->subd_brgy = '';
			$pi->province = '';
			$pi->phone_no = '';
			if ($patient_no !== '') {
				$p = $this->_get_patient($patient_no);
				if ($p) {
					$pi->name = trim((string)$p->lastname . ' ' . (string)$p->firstname);
					$pi->phone_no = isset($p->mobile_no) ? (string)$p->mobile_no : '';
				}
			}
		}
		$this->data['patientInfo'] = $pi;

		$ORNUM = isset($this->data['getOR']) ? $this->data['getOR'] : null;
		$filename = (($ORNUM && isset($ORNUM->receipt_no) && trim((string)$ORNUM->receipt_no) !== '') ? $ORNUM->receipt_no : 'RECEIPT') . "_" . date("mdY");
		$html = $this->load->view('app/opd/printOR2', $this->data, true);
		pdf_create($html, $filename, TRUE);
	}
    
    /**
     * Print Bill/Invoice
     */
    public function print_bill($bill_id)
    {
        $bill = $this->Billing_master_model->get_bill($bill_id);
        
        if (!$bill) {
            show_error('Bill not found');
            return;
        }
        
        $this->data['bill'] = $bill;
        $this->data['items'] = $this->Billing_master_model->get_bill_items($bill_id);
        $this->data['payments'] = $this->Billing_master_model->get_bill_payments($bill_id);
        $this->data['patient'] = $this->_get_patient($bill->patient_no);
        
        $this->load->view('app/unified_billing/invoice_print', $this->data);
    }
    
    /**
     * API: Get Patient Outstanding Balance
     */
    public function get_patient_balance($patient_no)
    {
        header('Content-Type: application/json');
        
        $this->db->select('SUM(balance_due) as total');
        $this->db->where('patient_no', $patient_no);
        $this->db->where_in('payment_status', ['PENDING', 'PARTIAL']);
        $this->db->where('InActive', 0);
        $result = $this->db->get('billing_master')->row();
        
        echo json_encode([
            'success' => true,
            'patient_no' => $patient_no,
            'outstanding_balance' => $result->total ?? 0
        ]);
    }
    
    /**
     * Cancel Bill (Admin only)
     */
    public function cancel_bill($bill_id)
    {
        if (!$this->_can_override()) {
            $this->session->set_flashdata('error', 'Only administrators can cancel bills.');
            redirect('app/unified_billing/view_bill/' . $bill_id);
        }
        
        $this->db->where('bill_id', $bill_id);
        $this->db->update('billing_master', [
            'payment_status' => 'CANCELLED',
            'updated_by' => $this->user_id,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        // Cancel all items too
        $this->db->where('bill_id', $bill_id);
        $this->db->update('billing_items', ['InActive' => 1]);
        
        $this->session->set_flashdata('message', 'Bill cancelled successfully.');
        redirect('app/unified_billing');
    }
    
    /* ================================================================== */
    /*  ROLE-BASED ACCESS CONTROL                                        */
    /* ================================================================== */
    
    private function _can_access_billing()
    {
        return has_role(array('admin', 'cashier'));
    }
    
    private function _can_collect_payment()
    {
        return has_role(array('admin', 'cashier'));
    }
    
    private function _can_apply_discount()
    {
        return has_role('admin');
    }
    
    private function _can_override()
    {
        return has_role('admin');
    }
    
    private function _is_admin()
    {
        return $this->user_role === 'admin';
    }

    /**
     * Get patient info by patient_no
     */
    private function _get_patient($patient_no)
    {
        $this->db->where('patient_no', $patient_no);
        return $this->db->get('patient_personal_info')->row();
    }

    /**
     * Collect Payment Page
     * Shows pending bills ready for payment collection
     */
    public function collect_payment()
    {
        if (!$this->_can_collect_payment()) {
            $this->session->set_flashdata('error', 'You do not have permission to collect payments.');
            redirect('app/unified_billing');
        }

        $date = date('Y-m-d');

        $this->data['page_title'] = 'Collect Payment';
        $this->data['bills'] = $this->Billing_master_model->get_pending_bills($date);
        $this->data['can_collect'] = true;

        $this->load->view('app/unified_billing/collect_payment', $this->data);
    }

    /**
     * Blocked Services Report
     * Shows services that are blocked pending payment
     */
    public function blocked_services()
    {
        if (!$this->_can_access_billing()) {
            $this->session->set_flashdata('error', 'Access denied.');
            redirect('app/dashboard');
        }

        $blocked = array();
        if ($this->db->table_exists('billing_queue') && $this->db->field_exists('service_gate_status', 'billing_queue')) {
            $this->db->select('q.queue_id, q.invoice_no, q.patient_no, q.iop_id, q.item_type, q.item_name, q.net_amount, q.status, q.source_module, q.source_ref, q.requested_at, q.created_at, q.service_gate_status, CONCAT(p.lastname, " ", p.firstname) as patient_name');
            $this->db->from('billing_queue q');
            $this->db->join('patient_personal_info p', 'p.patient_no = q.patient_no', 'left');
            $this->db->where('q.service_gate_status', 'BLOCKED');
            $this->db->where_in('q.status', array('PENDING', 'BILLED'));
            $this->db->where('q.InActive', 0);
            $this->db->order_by('q.created_at', 'DESC');
            $blocked = $this->db->get()->result();
        }

        $this->data['page_title'] = 'Blocked Services';
        $this->data['blocked_items'] = $blocked;
        $this->data['can_unblock'] = $this->_can_collect_payment();

        $this->load->view('app/unified_billing/blocked_services', $this->data);
    }

    /**
     * Unblock Service (AJAX)
     * Allows cashier to unblock a service after payment
     */
    public function unblock_service()
    {
        if (!$this->_can_collect_payment()) {
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            return;
        }

        header('Content-Type: application/json');

        $queue_id = (int)$this->input->post('queue_id');
        if ($queue_id <= 0) {
            echo json_encode(['success' => false, 'error' => 'Queue ID required']);
            return;
        }

        if (!$this->db->table_exists('billing_queue')) {
            echo json_encode(['success' => false, 'error' => 'Billing queue not available']);
            return;
        }

        if (!$this->db->field_exists('service_gate_status', 'billing_queue')) {
            echo json_encode(['success' => true, 'message' => 'Service gate not enabled']);
            return;
        }

        $this->db->where('queue_id', $queue_id);
        $this->db->where('service_gate_status', 'BLOCKED');
        $this->db->where('InActive', 0);
        $this->db->update('billing_queue', array(
            'service_gate_status' => 'RELEASED',
            'released_at' => date('Y-m-d H:i:s'),
            'released_by' => $this->user_id
        ));

        echo json_encode(['success' => true, 'message' => 'Service unblocked successfully']);
    }

    /**
     * Refunds Management Page
     * Shows and processes refund requests
     */
    public function refunds()
    {
        if (!$this->_can_apply_discount()) {
            $this->session->set_flashdata('error', 'Only supervisors and admins can process refunds.');
            redirect('app/unified_billing');
        }

        $this->db->select('r.*, b.bill_no, b.patient_no, CONCAT(p.lastname, " ", p.firstname) as patient_name');
        $this->db->from('billing_refunds r');
        $this->db->join('billing_master b', 'b.bill_id = r.bill_id');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        $this->db->where('r.InActive', 0);
        $this->db->order_by('r.requested_at', 'DESC');

        $this->data['page_title'] = 'Refund Management';
        $this->data['refunds'] = $this->db->get()->result();

        $this->load->view('app/unified_billing/refunds', $this->data);
    }

    /**
     * Analytics Dashboard
     * Revenue analytics and reporting
     */
    public function analytics()
    {
        if (!$this->_can_access_billing()) {
            $this->session->set_flashdata('error', 'Access denied.');
            redirect('app/dashboard');
        }

        // Revenue_analytics_model was quarantined (Phase 4 / Step 2 — SSOT consolidation).
        // Until a legacy-table aggregator is wired in, return empty datasets so
        // the page renders without fatal errors. The previous output was zeros
        // for every metric (eb_* tables were empty), so user-visible behaviour
        // is preserved.
        $from = $this->input->get('from') ?? date('Y-m-01');
        $to = $this->input->get('to') ?? date('Y-m-d');

        $this->data['page_title'] = 'Revenue Analytics';
        $this->data['from'] = $from;
        $this->data['to'] = $to;
        $this->data['summary'] = $this->_get_revenue_summary($from, $to);
        $this->data['by_department'] = array();
        $this->data['by_payment_method'] = array();
        $this->data['analytics_unavailable_notice'] = 'Detailed analytics are temporarily unavailable while the billing engine is being consolidated. Use the Reconciliation Dashboard for current figures.';

        $this->load->view('app/unified_billing/analytics', $this->data);
    }

    /**
     * Department Revenue Report
     */
    public function department_report()
    {
        if (!$this->_can_access_billing()) {
            $this->session->set_flashdata('error', 'Access denied.');
            redirect('app/dashboard');
        }

        // Revenue_analytics_model was quarantined (Phase 4 / Step 2 — SSOT consolidation).
        // See ::analytics() above for context.
        $from = $this->input->get('from') ?? date('Y-m-01');
        $to = $this->input->get('to') ?? date('Y-m-d');

        $this->data['page_title'] = 'Department Revenue Report';
        $this->data['from'] = $from;
        $this->data['to'] = $to;
        $this->data['departments'] = array();
        $this->data['analytics_unavailable_notice'] = 'Department revenue is temporarily unavailable while the billing engine is being consolidated.';

        $this->load->view('app/unified_billing/department_report', $this->data);
    }

    /**
     * Outstanding Balances Report
     */
    public function outstanding_report()
    {
        if (!$this->_can_access_billing()) {
            $this->session->set_flashdata('error', 'Access denied.');
            redirect('app/dashboard');
        }

        $this->db->select('b.*, CONCAT(p.lastname, " ", p.firstname) as patient_name, p.mobile_no');
        $this->db->from('billing_master b');
        $this->db->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left');
        $this->db->where_in('b.payment_status', ['PENDING', 'PARTIAL']);
        $this->db->where('b.InActive', 0);
        $this->db->where('b.balance_due >', 0);
        $this->db->order_by('b.balance_due', 'DESC');

        $this->data['page_title'] = 'Outstanding Balances';
        $this->data['outstanding'] = $this->db->get()->result();
        $this->data['total_outstanding'] = array_sum(array_column($this->data['outstanding'], 'balance_due'));

        $this->load->view('app/unified_billing/outstanding_report', $this->data);
    }

    /**
     * Private helper: Get revenue summary for date range
     */
    private function _get_revenue_summary($from, $to)
    {
        // Total revenue
        $this->db->select('SUM(allocated_amount) as total_revenue, COUNT(*) as transaction_count');
        $this->db->where('DATE(collected_at) >=', $from);
        $this->db->where('DATE(collected_at) <=', $to);
        $this->db->where('InActive', 0);
        $revenue = $this->db->get('billing_payments')->row();

        // Total bills
        $this->db->select('SUM(net_amount) as total_billed, COUNT(*) as bill_count');
        $this->db->where('DATE(created_at) >=', $from);
        $this->db->where('DATE(created_at) <=', $to);
        $this->db->where('InActive', 0);
        $billed = $this->db->get('billing_master')->row();

        // Outstanding
        $this->db->select('SUM(balance_due) as total_outstanding');
        $this->db->where_in('payment_status', ['PENDING', 'PARTIAL']);
        $this->db->where('InActive', 0);
        $outstanding = $this->db->get('billing_master')->row();

        return [
            'total_revenue' => $revenue->total_revenue ?? 0,
            'transaction_count' => $revenue->transaction_count ?? 0,
            'total_billed' => $billed->total_billed ?? 0,
            'bill_count' => $billed->bill_count ?? 0,
            'total_outstanding' => $outstanding->total_outstanding ?? 0,
            'collection_rate' => ($billed->total_billed > 0) ?
                round((($revenue->total_revenue / $billed->total_billed) * 100), 2) : 0
        ];
    }
}
