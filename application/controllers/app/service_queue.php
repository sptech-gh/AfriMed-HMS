<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Service Queue Controller
 * Cashier dashboard for pending service orders (lab, sonography, procedures)
 */
class Service_queue extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/billing_model');
        $this->load->model('app/service_billing_model');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin', 'cashier'));

        if (!$this->session->userdata('_schema_service_queue_ok')) {
            $this->billing_model->ensure_corporate_billing_schema();
            $this->session->set_userdata('_schema_service_queue_ok', 1);
        }
    }

    /**
     * Main service queue dashboard for cashier
     * DEPRECATED: Redirects to unified billing queue
     */
    public function index()
    {
        // DEPRECATED: Service Queue merged into Billing Queue
        // Redirect to unified billing queue for backward compatibility
        redirect(base_url() . 'app/cashier/billing_queue');
        return;
        
        /* Original code preserved for reference:
        if (!General::is_logged_in()) {
            redirect(base_url() . 'login');
            return;
        }

        // Access control - cashier, admin, billing roles
        if (!has_role(array('cashier', 'admin', 'billing'))) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Access denied. Cashier access required.</div>');
            redirect(base_url() . 'app/dashboard');
            return;
        }

        $this->variable();
        $this->session->set_userdata('module', 'service_queue');

        // Get pending service orders
        $this->data['pending_orders'] = $this->service_billing_model->get_pending_service_orders(200);
        
        // Get summary counts
        $this->data['summary'] = $this->_get_queue_summary();
        
        // Get companies for filter
        $this->data['companies'] = $this->service_billing_model->get_companies();

        $this->load->view('app/billing/service_queue', $this->data);
        */
    }

    /**
     * Get queue summary counts
     */
    private function _get_queue_summary()
    {
        $summary = array(
            'total_pending' => 0,
            'lab_pending' => 0,
            'sonography_pending' => 0,
            'procedure_pending' => 0,
            'total_amount' => 0,
            'nhis_count' => 0,
            'insurance_count' => 0,
            'company_count' => 0,
            'cash_count' => 0
        );

        if (!$this->service_billing_model->table_exists('service_orders')) {
            return $summary;
        }

        // Total pending
        $this->db->where('payment_status', 'UNPAID');
        $this->db->where('InActive', 0);
        $summary['total_pending'] = $this->db->count_all_results('service_orders');

        // By service type
        $this->db->select('service_type, COUNT(*) as cnt, SUM(patient_amount) as total');
        $this->db->where('payment_status', 'UNPAID');
        $this->db->where('InActive', 0);
        $this->db->group_by('service_type');
        $types = $this->db->get('service_orders')->result();
        foreach ($types as $t) {
            $key = strtolower($t->service_type) . '_pending';
            if (isset($summary[$key])) {
                $summary[$key] = (int)$t->cnt;
            }
            $summary['total_amount'] += (float)$t->total;
        }

        // By coverage type
        $this->db->select('coverage_type, COUNT(*) as cnt');
        $this->db->where('payment_status', 'UNPAID');
        $this->db->where('InActive', 0);
        $this->db->group_by('coverage_type');
        $coverage = $this->db->get('service_orders')->result();
        foreach ($coverage as $c) {
            $key = strtolower($c->coverage_type) . '_count';
            if (isset($summary[$key])) {
                $summary[$key] = (int)$c->cnt;
            }
        }

        return $summary;
    }

    /**
     * Process payment for a service order
     */
    public function process_payment()
    {
        if (!General::is_logged_in()) {
            echo json_encode(array('success' => false, 'error' => 'Session expired'));
            return;
        }

        if (!has_role(array('cashier', 'admin'))) {
            echo json_encode(array('success' => false, 'error' => 'Access denied'));
            return;
        }

        $order_id = (int)$this->input->post('order_id');
        $payment_method = trim((string)$this->input->post('payment_method'));
        $reference = trim((string)$this->input->post('reference'));

        if ($order_id <= 0) {
            echo json_encode(array('success' => false, 'error' => 'Invalid order ID'));
            return;
        }

        $order = $this->service_billing_model->get_service_order($order_id);
        if (!$order) {
            echo json_encode(array('success' => false, 'error' => 'Order not found'));
            return;
        }

        if ($order->payment_status === 'PAID') {
            echo json_encode(array('success' => false, 'error' => 'Order already paid'));
            return;
        }

        // Generate receipt number
        $receipt_no = 'SR' . date('ymd') . str_pad($order_id, 5, '0', STR_PAD_LEFT);

        // Mark as paid
        $user_id = $this->session->userdata('user_id');
        $result = $this->billing_model->mark_service_order_paid($order_id, $order->invoice_no, $receipt_no, $user_id);

        if ($result) {
            echo json_encode(array(
                'success' => true,
                'message' => 'Payment processed successfully',
                'receipt_no' => $receipt_no
            ));
        } else {
            echo json_encode(array('success' => false, 'error' => 'Failed to process payment'));
        }
    }

    /**
     * Request billing approval (waive, defer, etc.)
     */
    public function request_approval()
    {
        if (!General::is_logged_in()) {
            echo json_encode(array('success' => false, 'error' => 'Session expired'));
            return;
        }

        $order_id = (int)$this->input->post('order_id');
        $approval_type = trim((string)$this->input->post('approval_type'));
        $reason = trim((string)$this->input->post('reason'));

        if ($order_id <= 0) {
            echo json_encode(array('success' => false, 'error' => 'Invalid order ID'));
            return;
        }

        $order = $this->service_billing_model->get_service_order($order_id);
        if (!$order) {
            echo json_encode(array('success' => false, 'error' => 'Order not found'));
            return;
        }

        $result = $this->service_billing_model->request_billing_approval(array(
            'service_order_id' => $order_id,
            'patient_no' => $order->patient_no,
            'approval_type' => $approval_type,
            'original_amount' => $order->patient_amount,
            'reason' => $reason,
            'requested_by' => $this->session->userdata('user_id')
        ));

        echo json_encode($result);
    }

    /**
     * Admin approve billing exception
     */
    public function approve_exception()
    {
        if (!General::is_logged_in()) {
            echo json_encode(array('success' => false, 'error' => 'Session expired'));
            return;
        }

        if (!has_role('admin')) {
            echo json_encode(array('success' => false, 'error' => 'Admin access required'));
            return;
        }

        $approval_id = (int)$this->input->post('approval_id');
        if ($approval_id <= 0) {
            echo json_encode(array('success' => false, 'error' => 'Invalid approval ID'));
            return;
        }

        $result = $this->service_billing_model->approve_billing_exception(
            $approval_id,
            $this->session->userdata('user_id')
        );

        echo json_encode($result);
    }

    /**
     * Get pending approvals for admin
     */
    public function pending_approvals()
    {
        if (!General::is_logged_in()) {
            redirect(base_url() . 'login');
            return;
        }

        if (!has_role('admin')) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Admin access required.</div>');
            redirect(base_url() . 'app/dashboard');
            return;
        }

        $this->variable();
        $this->session->set_userdata('module', 'billing_approvals');

        $this->db->select('ba.*, p.firstname, p.lastname, so.service_name, so.service_type');
        $this->db->from('billing_approvals ba');
        $this->db->join('patient_personal_info p', 'p.patient_no = ba.patient_no', 'left');
        $this->db->join('service_orders so', 'so.id = ba.service_order_id', 'left');
        $this->db->where('ba.status', 'PENDING');
        $this->db->where('ba.InActive', 0);
        $this->db->order_by('ba.created_at', 'ASC');
        $this->data['approvals'] = $this->db->get()->result();

        $this->load->view('app/billing/pending_approvals', $this->data);
    }

    /**
     * Company management
     */
    public function companies()
    {
        if (!General::is_logged_in()) {
            redirect(base_url() . 'login');
            return;
        }

        if (!has_role('admin')) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Admin access required.</div>');
            redirect(base_url() . 'app/dashboard');
            return;
        }

        $this->variable();
        $this->session->set_userdata('module', 'corporate_companies');

        $this->data['companies'] = $this->service_billing_model->get_companies();

        $this->load->view('app/billing/companies', $this->data);
    }

    /**
     * Add company
     */
    public function add_company()
    {
        if (!General::is_logged_in() || !has_role('admin')) {
            echo json_encode(array('success' => false, 'error' => 'Access denied'));
            return;
        }

        $data = array(
            'company_name' => trim((string)$this->input->post('company_name')),
            'company_code' => trim((string)$this->input->post('company_code')),
            'contact_person' => trim((string)$this->input->post('contact_person')),
            'phone' => trim((string)$this->input->post('phone')),
            'email' => trim((string)$this->input->post('email')),
            'address' => trim((string)$this->input->post('address')),
            'credit_limit' => (float)$this->input->post('credit_limit'),
            'payment_terms' => trim((string)$this->input->post('payment_terms')) ?: 'NET30'
        );

        $result = $this->service_billing_model->add_company($data);
        echo json_encode($result);
    }

    /**
     * Set company pricing rule
     */
    public function set_company_pricing()
    {
        if (!General::is_logged_in() || !has_role('admin')) {
            echo json_encode(array('success' => false, 'error' => 'Access denied'));
            return;
        }

        $company_id = (int)$this->input->post('company_id');
        $pricing_type = trim((string)$this->input->post('pricing_type'));
        $value = (float)$this->input->post('value');
        $service_type = trim((string)$this->input->post('service_type')) ?: null;
        $service_id = (int)$this->input->post('service_id') ?: null;

        if ($company_id <= 0) {
            echo json_encode(array('success' => false, 'error' => 'Invalid company ID'));
            return;
        }

        $result = $this->service_billing_model->set_company_pricing(
            $company_id,
            $pricing_type,
            $value,
            $service_type,
            $service_id,
            $this->session->userdata('user_id')
        );

        echo json_encode(array('success' => $result));
    }

    /**
     * Insurance companies management
     */
    public function insurance()
    {
        if (!General::is_logged_in()) {
            redirect(base_url() . 'login');
            return;
        }

        if (!has_role('admin')) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Admin access required.</div>');
            redirect(base_url() . 'app/dashboard');
            return;
        }

        $this->variable();
        $this->session->set_userdata('module', 'insurance_companies');

        $this->data['insurance_companies'] = $this->service_billing_model->get_insurance_companies();

        $this->load->view('app/billing/insurance', $this->data);
    }

    /**
     * Add insurance company
     */
    public function add_insurance()
    {
        if (!General::is_logged_in() || !has_role('admin')) {
            echo json_encode(array('success' => false, 'error' => 'Access denied'));
            return;
        }

        $data = array(
            'insurance_name' => trim((string)$this->input->post('insurance_name')),
            'insurance_code' => trim((string)$this->input->post('insurance_code')),
            'coverage_type' => trim((string)$this->input->post('coverage_type')) ?: 'PERCENTAGE',
            'default_percent' => (float)$this->input->post('default_percent') ?: 80,
            'contact_person' => trim((string)$this->input->post('contact_person')),
            'phone' => trim((string)$this->input->post('phone')),
            'email' => trim((string)$this->input->post('email')),
            'address' => trim((string)$this->input->post('address'))
        );

        $result = $this->service_billing_model->add_insurance_company($data);
        echo json_encode($result);
    }

    /**
     * Set insurance coverage rule
     */
    public function set_insurance_coverage()
    {
        if (!General::is_logged_in() || !has_role('admin')) {
            echo json_encode(array('success' => false, 'error' => 'Access denied'));
            return;
        }

        $insurance_id = (int)$this->input->post('insurance_id');
        $coverage_percent = (float)$this->input->post('coverage_percent');
        $service_type = trim((string)$this->input->post('service_type')) ?: null;
        $max_amount = (float)$this->input->post('max_amount') ?: null;

        if ($insurance_id <= 0) {
            echo json_encode(array('success' => false, 'error' => 'Invalid insurance ID'));
            return;
        }

        $result = $this->service_billing_model->set_insurance_coverage(
            $insurance_id,
            $coverage_percent,
            $service_type,
            null,
            $max_amount
        );

        echo json_encode(array('success' => $result));
    }

    /**
     * Billing reports
     */
    public function reports()
    {
        if (!General::is_logged_in()) {
            redirect(base_url() . 'login');
            return;
        }

        if (!has_role(array('cashier', 'admin'))) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger">Access denied.</div>');
            redirect(base_url() . 'app/dashboard');
            return;
        }

        $this->variable();
        $this->session->set_userdata('module', 'billing_reports');

        $date_from = $this->input->get('date_from') ?: date('Y-m-01');
        $date_to = $this->input->get('date_to') ?: date('Y-m-d');

        $this->data['date_from'] = $date_from;
        $this->data['date_to'] = $date_to;
        $this->data['summary'] = $this->billing_model->get_coverage_billing_summary($date_from, $date_to);
        $this->data['outstanding'] = $this->service_billing_model->get_outstanding_bills(50);

        $this->load->view('app/billing/service_reports', $this->data);
    }
}
