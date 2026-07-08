<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Cashier extends General
{
	private $limit = 20;

	public function __construct()
	{
		parent::__construct();
		$this->load->model("app/billing_model");
		$this->load->model("app/bill_history_model");
		$this->load->model("app/patient_model");
		$this->load->model("app/cashier_model");
		$this->load->model("app/unified_billing_model");
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		$method = (string)$this->router->fetch_method();
		$allowedForInvoiceView = array('invoice', 'print_receipt', 'pdf_receipt');
		$allowedForWalkinCollect = array('invoice', 'process_payment', 'print_receipt', 'pdf_receipt', 'bill_walkin_order');
		if (!has_role(array('cashier', 'admin'))) {
			$canInvoiceView = false;
			$canWalkinCollect = false;
			try {
				$this->load->model('app/governance_model');
				if (!$this->session->userdata('_schema_governance_ok')) {
					$this->governance_model->ensure_governance_schema();
					$this->session->set_userdata('_schema_governance_ok', 1);
				}
				$uid = $this->session->userdata('user_id');
				$canInvoiceView = ($uid && $this->governance_model->user_has_privilege($uid, 'cashier_invoice_view'));
				$canWalkinCollect = ($uid && $this->governance_model->user_has_privilege($uid, 'cashier_walkin_collect_access'));
			} catch (Exception $e) {
				$canInvoiceView = false;
				$canWalkinCollect = false;
			}
			$allowed = ($canInvoiceView && in_array($method, $allowedForInvoiceView, true))
				|| ($canWalkinCollect && in_array($method, $allowedForWalkinCollect, true));
			if (!$allowed) {
				redirect(base_url() . 'access_denied');
				return;
			}
		}
		if (!schema_already_run('cashier_schema')) {
			$this->cashier_model->ensure_cashier_schema();
		}
		if (!schema_already_run('unified_billing_schema')) {
			$this->unified_billing_model->ensure_unified_billing_schema();
			$this->unified_billing_model->ensure_billing_performance_indexes();
		}
	}

	/**
	 * Cashier Dashboard - DEPRECATED: Redirects to Billing & Finance
	 * Legacy cashier module has been consolidated into Enterprise Billing (Billing & Finance)
	 */
	public function index()
	{
		// Legacy code below - kept for reference
		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'cashier_dashboard',
			'subtab' => '',
			'submodule' => ''
		));

		$today = date('Y-m-d');

		// Get unified billing statistics
		$this->data['stats'] = $this->unified_billing_model->get_billing_statistics($today, $today);
		
		// Get billing queue summary
		$this->data['queue_summary'] = $this->unified_billing_model->get_billing_queue_summary();
		
		// Get daily reconciliation
		$this->data['reconciliation'] = $this->unified_billing_model->get_daily_reconciliation($today);
		
		// Get recent unpaid invoices
		$this->data['unpaid_invoices'] = $this->cashier_model->get_invoices(10, 0, '', 'unpaid');
		
		// Get today's payments
		$this->data['today_payments'] = $this->cashier_model->get_today_payments(10);
		
		// Get payment summary
		$this->data['payment_summary'] = $this->cashier_model->get_payment_summary();

		$this->data['page_title'] = 'Cashier Dashboard';
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/dashboard', $this->data);
	}

	/**
	 * Payment Collection - DEPRECATED: Redirects to Billing & Finance
	 */
	public function payments($offset = 0)
	{
		// Legacy code below
		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'payment_collection',
			'subtab' => '',
			'submodule' => ''
		));

		$uri_segment = 4;
		$offset = (int)$this->uri->segment($uri_segment);

		$search = trim((string)$this->input->get('search'));
		$status = trim((string)$this->input->get('status'));
		if ($status === '') $status = 'unpaid';

		$invoices = $this->cashier_model->get_invoices($this->limit, $offset, $search, $status);
		$total = $this->cashier_model->count_invoices($search, $status);

		$config['base_url'] = base_url() . 'app/cashier/payments/';
		$config['total_rows'] = $total;
		$config['per_page'] = $this->limit;
		$config['uri_segment'] = $uri_segment;

		$qs = '';
		$parts = array();
		if ($search !== '') $parts[] = 'search=' . urlencode($search);
		if ($status !== 'unpaid') $parts[] = 'status=' . urlencode($status);
		if (count($parts) > 0) {
			$qs = '?' . implode('&', $parts);
			$config['suffix'] = $qs;
			$config['first_url'] = $config['base_url'] . '0' . $qs;
		}

		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul>';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';

		$this->pagination->initialize($config);

		$this->data['page_title'] = 'Payment Collection';
		$this->data['invoices'] = $invoices;
		$this->data['pagination'] = $this->pagination->create_links();
		$this->data['search'] = $search;
		$this->data['status'] = $status;
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['summary'] = $this->cashier_model->get_payment_summary();
		$this->data['queue_summary'] = $this->unified_billing_model->get_billing_queue_summary();
		$this->data['payment_methods'] = $this->cashier_model->get_payment_methods();

		$this->load->view('app/cashier/payments', $this->data);
	}

	/**
	 * Process payment for an invoice
	 */
	public function process_payment()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$limited_walkin_collect = (!has_role(array('cashier', 'admin')) && function_exists('has_privilege') && has_privilege('cashier_walkin_collect_access'));

		$invoice_no = trim((string)$this->input->post('invoice_no'));
		$amount = (float)$this->input->post('amount');
		$payment_method = trim((string)$this->input->post('payment_method'));
		$reference = trim((string)$this->input->post('reference'));
		$notes = trim((string)$this->input->post('notes'));

		if ($invoice_no === '' || $amount <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invalid invoice or amount.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		// Closed encounter bypass check
		$iop_id = '';
		$patient_no = '';
		$inv = $this->db->get_where('iop_billing', array('invoice_no' => $invoice_no, 'InActive' => 0))->row();
		if ($inv) {
			$iop_id = isset($inv->iop_id) ? (string)$inv->iop_id : '';
			$patient_no = isset($inv->patient_no) ? (string)$inv->patient_no : '';
		}

		$this->load->model('app/opd_model');
		if ($iop_id !== '' && $this->opd_model->is_encounter_locked($iop_id)) {
			$notes = '[CLOSED_ENCOUNTER_BYPASS] ' . $notes;
			$_POST['notes'] = $notes;

			$this->load->model('app/cashier_model');
			$this->cashier_model->log_financial_audit(
				'PAYMENT',
				null,
				$invoice_no,
				$patient_no,
				$amount,
				null,
				null,
				'[CLOSED_ENCOUNTER_BYPASS] Payment processed for invoice: ' . $invoice_no,
				$this->session->userdata('user_id')
			);
		}

		$subject = null;
		if ($this->db->field_exists('billing_subject_type', 'iop_billing') && $this->db->field_exists('billing_subject_id', 'iop_billing')) {
			$this->db->select('billing_subject_type, billing_subject_id');
			$this->db->where('invoice_no', $invoice_no);
			$this->db->where('InActive', 0);
			$subject = $this->db->get('iop_billing')->row();
		}
		if ($limited_walkin_collect) {
			$bst = $subject && isset($subject->billing_subject_type) ? strtoupper(trim((string)$subject->billing_subject_type)) : '';
			if ($bst !== 'WALKIN_ORDER') {
				log_message('error', 'cashier.process_payment denied: limited walk-in collect attempted on non-walkin invoice ' . $invoice_no . ' by user_id=' . (string)$this->session->userdata('user_id'));
				redirect(base_url() . 'access_denied');
				return;
			}
		}
		if ($subject && strtoupper(trim((string)$subject->billing_subject_type)) === 'WALKIN_ORDER' && trim((string)$subject->billing_subject_id) !== '') {
			$this->load->model('app/unified_billing_model');
			$result = $this->unified_billing_model->process_payment_by_subject('WALKIN_ORDER', (string)$subject->billing_subject_id, $invoice_no, $amount, $payment_method, $this->session->userdata('user_id'), $reference, $notes);
			if (isset($result['success']) && $result['success'] && strtoupper(trim((string)(isset($result['payment_status']) ? $result['payment_status'] : ''))) === 'PAID') {
				$this->load->model('app/walkin_order_model');
				$this->walkin_order_model->mark_order_paid_authorized((string)$subject->billing_subject_id, isset($result['receipt_no']) ? (string)$result['receipt_no'] : null, (string)$this->session->userdata('user_id'));
			}
		} else {
			$result = $this->cashier_model->process_payment($invoice_no, $amount, $payment_method, $reference, $notes, $this->session->userdata('user_id'));
		}

		if (isset($result['success']) && $result['success']) {
			$receipt_no = isset($result['receipt_no']) ? (string)$result['receipt_no'] : '';
			$this->cashier_model->trigger_dispatch_notifications($invoice_no, $receipt_no, $this->session->userdata('user_id'));
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Payment of GHS " . number_format($amount, 2) . " recorded successfully. Receipt #" . $receipt_no . "</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . (isset($result['error']) ? $result['error'] : 'Payment failed') . "</div>");
		}

		redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoice_no));
	}

	/**
	 * View invoice details for payment
	 */
	public function invoice($invoice_no = '')
	{
		$invoice_no = $invoice_no !== '' ? $invoice_no : trim((string)$this->input->get('invoice_no'));
		if ($invoice_no === '') {
			redirect(base_url() . 'app/cashier/payments');
			return;
		}
		$limited_walkin_collect = (!has_role(array('cashier', 'admin')) && function_exists('has_privilege') && has_privilege('cashier_walkin_collect_access'));
		if ($limited_walkin_collect) {
			$bst = '';
			if ($this->db->field_exists('billing_subject_type', 'iop_billing')) {
				$this->db->select('billing_subject_type');
				$this->db->where('invoice_no', $invoice_no);
				$this->db->where('InActive', 0);
				$r = $this->db->get('iop_billing')->row();
				$bst = $r && isset($r->billing_subject_type) ? strtoupper(trim((string)$r->billing_subject_type)) : '';
			}
			if ($bst !== 'WALKIN_ORDER') {
				log_message('error', 'cashier.invoice denied: limited walk-in collect attempted on non-walkin invoice ' . $invoice_no . ' by user_id=' . (string)$this->session->userdata('user_id'));
				redirect(base_url() . 'access_denied');
				return;
			}
		}

		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'payment_collection',
			'subtab' => '',
			'submodule' => ''
		));

		$invoice = $this->cashier_model->get_invoice_details($invoice_no);
		if (!$invoice) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invoice not found.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		$this->data['page_title'] = 'Invoice Details - ' . $invoice_no;
		$this->data['invoice'] = $invoice;
		$this->data['items'] = $this->cashier_model->get_invoice_items($invoice_no);
		$this->data['payments'] = $this->cashier_model->get_invoice_payments($invoice_no);
		$this->data['walkin_order'] = null;
		$this->data['walkin_fulfillment_items'] = array();
		if ($this->db->field_exists('billing_subject_type', 'iop_billing') && $this->db->field_exists('billing_subject_id', 'iop_billing')) {
			$this->db->select('billing_subject_type, billing_subject_id');
			$this->db->where('invoice_no', $invoice_no);
			$this->db->where('InActive', 0);
			$subj = $this->db->get('iop_billing')->row();
			if ($subj && strtoupper(trim((string)$subj->billing_subject_type)) === 'WALKIN_ORDER') {
				try {
					$this->load->model('app/walkin_order_model');
					if (isset($this->walkin_order_model) && method_exists($this->walkin_order_model, 'sync_paid_order_from_billing')) {
						$this->walkin_order_model->sync_paid_order_from_billing((string)$subj->billing_subject_id, (string)$this->session->userdata('user_id'));
					}
				} catch (\Throwable $e) {
					log_message('error', 'cashier invoice walk-in paid order sync failed: invoice=' . $invoice_no . ' order=' . (string)$subj->billing_subject_id . ' error=' . $e->getMessage());
				}
				$this->db->where('walkin_order_id', (string)$subj->billing_subject_id);
				$this->db->where('InActive', 0);
				$this->data['walkin_order'] = $this->db->get('walkin_orders')->row();
				$this->db->where('walkin_order_id', (string)$subj->billing_subject_id);
				$this->db->where('InActive', 0);
				$this->db->order_by('internal_id', 'ASC');
				$this->data['walkin_fulfillment_items'] = $this->db->get('walkin_order_items')->result();
			}
		}
		$this->data['refunds'] = $this->cashier_model->get_invoice_refunds($invoice_no);
		$this->data['refund_totals'] = $this->cashier_model->get_invoice_refund_totals($invoice_no);
		$can_refund = false;
		if (function_exists('has_role') && has_role('admin')) {
			$can_refund = true;
		} else {
			try {
				$this->load->library('BillingAuth');
				if (isset($this->billingauth) && method_exists($this->billingauth, 'check')) {
					$can_refund = (bool)$this->billingauth->check('refund');
				}
			} catch (Exception $e) {
				$can_refund = false;
			}
		}
		$this->data['can_refund'] = $can_refund;
		$this->data['payment_methods'] = $this->cashier_model->get_payment_methods();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/invoice', $this->data);
	}

	/**
	 * Daily Collection Report - DEPRECATED: Redirects to Billing & Finance
	 */
	public function daily_collection()
	{
		// Legacy code below
		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'daily_collection',
			'subtab' => '',
			'submodule' => ''
		));

		$date = trim((string)$this->input->get('date'));
		if ($date === '') $date = date('Y-m-d');

		$cashier_id = null;
		if (!has_role('admin')) {
			$cashier_id = $this->session->userdata('user_id');
		} else {
			$cashier_id = trim((string)$this->input->get('cashier_id'));
			if ($cashier_id === '') $cashier_id = null;
		}

		$this->data['page_title'] = 'Daily Collection Report';
		$this->data['date'] = $date;
		$this->data['cashier_id'] = $cashier_id;
		$this->data['collections'] = $this->cashier_model->get_daily_collections($date, $cashier_id);
		$this->data['summary'] = $this->cashier_model->get_daily_summary($date, $cashier_id);
		$this->data['by_method'] = $this->cashier_model->get_collections_by_method($date, $cashier_id);
		$this->data['cashiers'] = has_role('admin') ? $this->cashier_model->get_cashiers() : array();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/daily_collection', $this->data);
	}

	/**
	 * Print receipt
	 */
	public function print_receipt($receipt_no = '')
	{
		if ($receipt_no === '') {
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		$receipt = $this->cashier_model->get_receipt($receipt_no);
		if (!$receipt || !isset($receipt->invoice_no) || trim((string)$receipt->invoice_no) === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Receipt not found.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		// Canonical SSOT print payload (same as OPD / Unified Billing)
		$this->data['companyInfo'] = $this->general_model->companyInfo();
		$this->data['patientInfo'] = $this->_receipt_customer_info($receipt);
		$payload = $this->billing_model->build_receipt_print_payload((string)$receipt->invoice_no, (string)$receipt_no);
		$this->data = array_merge($this->data, $payload);
		$this->data['receipt'] = $receipt;

		$this->load->view('app/cashier/thermal_receipt', $this->data);
	}

	/**
	 * Print final thermal receipt with all items grouped by department
	 */
	public function thermal_final_receipt($invoice_no = '')
	{
		$invoice_no = trim((string)$invoice_no);
		if ($invoice_no === '') {
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		$invoice = $this->cashier_model->get_invoice_details($invoice_no);
		if (!$invoice) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invoice not found.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		$this->data['companyInfo'] = $this->general_model->companyInfo();
		$this->data['invoice'] = $invoice;
		$this->data['patientInfo'] = $this->_receipt_customer_info((object)array('patient_no' => $invoice->patient_no, 'invoice_no' => $invoice_no));
		
		// Load items
		$this->load->model('app/billing_model');
		$this->data['items'] = $this->billing_model->detailsInv2($invoice_no);
		
		// Load payments
		$this->data['payments'] = $this->cashier_model->get_invoice_payments($invoice_no);
		
		// Load dispatch notifications
		$this->data['notifications'] = $this->cashier_model->get_dispatch_notifications($invoice_no);

		$this->load->view('app/cashier/thermal_final_receipt', $this->data);
	}

	/**
	 * Get dispatch status for AJAX live updates
	 */
	public function dispatch_status_json($invoice_no = '')
	{
		$invoice_no = trim((string)$invoice_no);
		if ($invoice_no === '') {
			echo json_encode(array());
			return;
		}
		$notifs = $this->cashier_model->get_dispatch_notifications($invoice_no);
		echo json_encode($notifs);
	}

	/**
	 * Mark a department dispatch notification as completed
	 */
	public function mark_dispatched()
	{
		$notif_id = (int)$this->input->post('notification_id');
		$redirect_url = $this->input->post('redirect_url');
		
		if ($notif_id > 0) {
			$user_id = $this->session->userdata('user_id');
			$this->cashier_model->mark_notification_dispatched($notif_id, $user_id);
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Patient cleared and marked as processed.</div>");
		}

		if ($redirect_url) {
			redirect($redirect_url);
		} else {
			redirect(base_url() . 'app/dashboard');
		}
	}

	/**
	 * Print receipt to PDF (canonical template)
	 */
	public function pdf_receipt($receipt_no = '')
	{
		if ($receipt_no === '') {
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		$receipt = $this->cashier_model->get_receipt($receipt_no);
		if (!$receipt || !isset($receipt->invoice_no) || trim((string)$receipt->invoice_no) === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Receipt not found.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		$this->load->helper('file');
		$this->load->helper('dompdf');

		$this->data['companyInfo'] = $this->general_model->companyInfo();
		$this->data['patientInfo'] = $this->_receipt_customer_info($receipt);
		$payload = $this->billing_model->build_receipt_print_payload((string)$receipt->invoice_no, (string)$receipt_no);
		$this->data = array_merge($this->data, $payload);
		$this->data['ORNUM'] = isset($this->data['getOR']) ? $this->data['getOR'] : null;
		$ORNUM = $this->data['ORNUM'];
		$filename = (($ORNUM && isset($ORNUM->receipt_no) && trim((string)$ORNUM->receipt_no) !== '') ? $ORNUM->receipt_no : 'RECEIPT') . '_' . date('mdY');

		$html = $this->load->view('app/opd/printOR2', $this->data, true);
		pdf_create($html, $filename, TRUE);
	}

	private function _receipt_customer_info($receipt)
	{
		$patient_no = ($receipt && isset($receipt->patient_no)) ? trim((string)$receipt->patient_no) : '';
		if ($patient_no !== '') {
			$patient = $this->patient_model->getPatientInfo($patient_no);
			if ($patient) return $patient;
		}

		$fallback = (object)array(
			'name' => 'Walk-in Client',
			'street' => '',
			'subd_brgy' => '',
			'province' => '',
			'phone_no' => '',
		);
		$invoice_no = ($receipt && isset($receipt->invoice_no)) ? trim((string)$receipt->invoice_no) : '';
		if ($invoice_no === '' || !$this->db->table_exists('walkin_orders') || !$this->db->table_exists('iop_billing')) {
			return $fallback;
		}

		$join_order = "B.billing_subject_type = 'WALKIN_ORDER' AND CONVERT(B.billing_subject_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(O.walkin_order_id USING utf8mb4) COLLATE utf8mb4_unicode_ci";
		$this->db->select('O.customer_name, O.phone, O.gender, O.walkin_code');
		$this->db->from('iop_billing B');
		$this->db->join('walkin_orders O', $join_order, 'inner', false);
		$this->db->where('B.invoice_no', $invoice_no);
		$this->db->where('B.InActive', 0);
		$this->db->where('O.InActive', 0);
		$row = $this->db->get()->row();
		if (!$row) return $fallback;

		if (isset($row->customer_name) && trim((string)$row->customer_name) !== '') {
			$fallback->name = trim((string)$row->customer_name);
		}
		if (isset($row->walkin_code) && trim((string)$row->walkin_code) !== '') {
			$fallback->street = 'Walk-In Code: ' . trim((string)$row->walkin_code);
		}
		if (isset($row->gender) && trim((string)$row->gender) !== '') {
			$fallback->subd_brgy = 'Gender: ' . trim((string)$row->gender);
		}
		if (isset($row->phone) && trim((string)$row->phone) !== '') {
			$fallback->phone_no = trim((string)$row->phone);
		}
		return $fallback;
	}

	/**
	 * Void a payment (admin only)
	 */
	public function void_payment()
	{
		require_role('admin');

		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$receipt_no = trim((string)$this->input->post('receipt_no'));
		$reason = trim((string)$this->input->post('void_reason'));

		if ($receipt_no === '' || $reason === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Receipt number and reason are required.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		// Closed encounter bypass check
		$iop_id = '';
		$patient_no = '';
		$invoice_no = '';
		$amount = 0.00;
		$rec = $this->db->get_where('iop_receipt', array('receipt_no' => $receipt_no))->row();
		if ($rec) {
			$iop_id = isset($rec->iop_id) ? (string)$rec->iop_id : '';
			$patient_no = isset($rec->patient_no) ? (string)$rec->patient_no : '';
			$invoice_no = isset($rec->invoice_no) ? (string)$rec->invoice_no : '';
			$amount = isset($rec->amountPaid) ? (float)$rec->amountPaid : 0.00;
		}
		if ($iop_id === '' && $invoice_no !== '') {
			$inv = $this->db->get_where('iop_billing', array('invoice_no' => $invoice_no))->row();
			if ($inv) {
				$iop_id = isset($inv->iop_id) ? (string)$inv->iop_id : '';
				if ($patient_no === '') {
					$patient_no = isset($inv->patient_no) ? (string)$inv->patient_no : '';
				}
			}
		}

		$this->load->model('app/opd_model');
		if ($iop_id !== '' && $this->opd_model->is_encounter_locked($iop_id)) {
			$reason = '[CLOSED_ENCOUNTER_BYPASS] ' . $reason;
			$_POST['void_reason'] = $reason;

			$this->load->model('app/cashier_model');
			$this->cashier_model->log_financial_audit(
				'VOID',
				$receipt_no,
				$invoice_no,
				$patient_no,
				$amount,
				null,
				null,
				'[CLOSED_ENCOUNTER_BYPASS] Payment voided for receipt: ' . $receipt_no,
				$this->session->userdata('user_id')
			);
		}

		$result = $this->cashier_model->void_payment($receipt_no, $reason, $this->session->userdata('user_id'));

		if ($result['success']) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Payment voided successfully.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . $result['error'] . "</div>");
		}

		redirect(base_url() . 'app/cashier/payments');
	}

	/**
	 * Refund a payment (admin only)
	 */
	public function refund_payment()
	{
		$can_refund = false;
		if (function_exists('has_role') && has_role('admin')) {
			$can_refund = true;
		} else {
			try {
				$this->load->library('BillingAuth');
				if (isset($this->billingauth) && method_exists($this->billingauth, 'check')) {
					$can_refund = (bool)$this->billingauth->check('refund');
				}
			} catch (Exception $e) {
				$can_refund = false;
			}
		}
		if (!$can_refund) {
			redirect(base_url() . 'access_denied');
			return;
		}

		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$receipt_no = trim((string)$this->input->post('receipt_no'));
		$amount = (float)$this->input->post('refund_amount');
		$reason = trim((string)$this->input->post('refund_reason'));
		$refund_receipt_no = trim((string)$this->input->post('refund_receipt_no'));

		if ($receipt_no === '' || $amount <= 0 || $reason === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Receipt number, refund amount and reason are required.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}

		// Closed encounter bypass check
		$iop_id = '';
		$patient_no = '';
		$invoice_no = '';
		$rec = $this->db->get_where('iop_receipt', array('receipt_no' => $receipt_no))->row();
		if ($rec) {
			$iop_id = isset($rec->iop_id) ? (string)$rec->iop_id : '';
			$patient_no = isset($rec->patient_no) ? (string)$rec->patient_no : '';
			$invoice_no = isset($rec->invoice_no) ? (string)$rec->invoice_no : '';
		}
		if ($iop_id === '' && $invoice_no !== '') {
			$inv = $this->db->get_where('iop_billing', array('invoice_no' => $invoice_no))->row();
			if ($inv) {
				$iop_id = isset($inv->iop_id) ? (string)$inv->iop_id : '';
				if ($patient_no === '') {
					$patient_no = isset($inv->patient_no) ? (string)$inv->patient_no : '';
				}
			}
		}

		$this->load->model('app/opd_model');
		if ($iop_id !== '' && $this->opd_model->is_encounter_locked($iop_id)) {
			$reason = '[CLOSED_ENCOUNTER_BYPASS] ' . $reason;
			$_POST['refund_reason'] = $reason;

			$this->load->model('app/cashier_model');
			$this->cashier_model->log_financial_audit(
				'REFUND',
				$refund_receipt_no,
				$invoice_no,
				$patient_no,
				$amount,
				null,
				null,
				'[CLOSED_ENCOUNTER_BYPASS] Payment refunded for receipt: ' . $receipt_no,
				$this->session->userdata('user_id')
			);
		}

		$orig = $this->cashier_model->get_receipt($receipt_no);
		if (!$orig || !isset($orig->invoice_no) || trim((string)$orig->invoice_no) === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Receipt not found.</div>");
			redirect(base_url() . 'app/cashier/payments');
			return;
		}
		$invoice_no = (string)$orig->invoice_no;

		$result = $this->cashier_model->refund_payment($receipt_no, $amount, $reason, $this->session->userdata('user_id'), $refund_receipt_no);

		if (isset($result['success']) && $result['success']) {
			$rr = isset($result['refund_receipt_no']) ? (string)$result['refund_receipt_no'] : '';
			$msg = "<div class='alert alert-success'><i class='fa fa-check'></i> Refund issued successfully.";
			if ($rr !== '') {
				$msg .= " Refund Receipt #" . $rr;
			}
			$msg .= "</div>";
			$this->session->set_flashdata('message', $msg);
		} else {
			$err = (is_array($result) && isset($result['error'])) ? $result['error'] : 'Refund failed';
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . $err . "</div>");
		}

		redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoice_no));
	}

	/**
	 * Export daily collection to PDF
	 */
	public function export_daily_pdf()
	{
		$date = trim((string)$this->input->get('date'));
		if ($date === '') $date = date('Y-m-d');

		$cashier_id = null;
		if (!has_role('admin')) {
			$cashier_id = $this->session->userdata('user_id');
		} else {
			$cashier_id = trim((string)$this->input->get('cashier_id'));
			if ($cashier_id === '') $cashier_id = null;
		}

		$this->data['date'] = $date;
		$this->data['collections'] = $this->cashier_model->get_daily_collections($date, $cashier_id);
		$this->data['summary'] = $this->cashier_model->get_daily_summary($date, $cashier_id);
		$this->data['by_method'] = $this->cashier_model->get_collections_by_method($date, $cashier_id);
		$this->data['companyInfo'] = $this->general_model->companyInfo();

		$html = $this->load->view('app/cashier/daily_collection_pdf', $this->data, TRUE);
		$filename = 'daily_collection_' . $date;

		$this->load->helper('dompdf');
		pdf_create($html, $filename, TRUE);
	}

	/**
	 * Billing Queue - DEPRECATED: Redirects to Billing & Finance
	 */
	public function billing_queue()
	{
		// Legacy code below
		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'billing_queue',
			'subtab' => '',
			'submodule' => ''
		));

		$iop_id = trim((string)$this->input->get('iop_id'));
		$patient_no = trim((string)$this->input->get('patient_no'));
		$hasFilter = ($iop_id !== '' || $patient_no !== '');
		$mode = $hasFilter ? 'detail' : 'group';

		$this->data['page_title'] = 'Billing Queue';
		$this->data['mode'] = $mode;
		$this->data['filter_iop_id'] = $iop_id;
		$this->data['filter_patient_no'] = $patient_no;
		$this->data['queue_items'] = $hasFilter ? $this->unified_billing_model->get_billing_queue(($iop_id !== '' ? $iop_id : null), ($patient_no !== '' ? $patient_no : null)) : array();
		$this->data['queue_groups'] = array();
		$this->data['patient_map'] = array();
		$this->data['summary'] = $this->unified_billing_model->get_billing_queue_summary();
		$this->data['message'] = $this->session->flashdata('message');

		if ($mode === 'group') {
			$items = $this->unified_billing_model->get_billing_queue(null, null, 'PENDING', array('run_global_backfills' => false));
			$groups = array();
			$patientNos = array();
			foreach ($items as $it) {
				$k = (string)$it->patient_no . '|' . (string)$it->iop_id;
				if (!isset($groups[$k])) {
					$groups[$k] = (object)array(
						'patient_no' => (string)$it->patient_no,
						'iop_id' => (string)$it->iop_id,
						'payer_type' => (string)$it->payer_type,
						'items_count' => 0,
						'total_amount' => 0.0,
						'first_requested_at' => isset($it->requested_at) ? (string)$it->requested_at : null,
					);
					$patientNos[(string)$it->patient_no] = true;
				}
				$groups[$k]->items_count += 1;
				$groups[$k]->total_amount += (float)$it->net_amount;
				$reqAt = isset($it->requested_at) ? trim((string)$it->requested_at) : '';
				if ($reqAt !== '') {
					$cur = isset($groups[$k]->first_requested_at) ? trim((string)$groups[$k]->first_requested_at) : '';
					if ($cur === '' || strtotime($reqAt) < strtotime($cur)) {
						$groups[$k]->first_requested_at = $reqAt;
					}
				}
			}
			$this->data['queue_groups'] = array_values($groups);

			$pn = array_keys($patientNos);
			if (count($pn) > 0) {
				$hasPhone = $this->db->field_exists('phone', 'patient_personal_info');
				$this->db->select("patient_no, CONCAT(lastname,' ',firstname) AS patient_name" . ($hasPhone ? ", phone" : ""), false);
				$this->db->where_in('patient_no', $pn);
				$q = $this->db->get('patient_personal_info');
				$map = array();
				if ($q) {
					foreach ($q->result() as $r) {
						$map[(string)$r->patient_no] = $r;
					}
				}
				$this->data['patient_map'] = $map;
			}
		} else {
			$pn = array();
			if ($patient_no !== '') $pn[] = $patient_no;
			if (count($pn) > 0) {
				$hasPhone = $this->db->field_exists('phone', 'patient_personal_info');
				$this->db->select("patient_no, CONCAT(lastname,' ',firstname) AS patient_name" . ($hasPhone ? ", phone" : ""), false);
				$this->db->where_in('patient_no', $pn);
				$q = $this->db->get('patient_personal_info');
				$map = array();
				if ($q) {
					foreach ($q->result() as $r) {
						$map[(string)$r->patient_no] = $r;
					}
				}
				$this->data['patient_map'] = $map;
			}
		}

		$this->load->view('app/cashier/billing_queue', $this->data);
	}
	
	/**
	 * Create invoice for a patient/visit from the billing queue
	 */
	public function bill_patient($iop_id = '', $patient_no = '')
	{
		$iop_id = trim((string)urldecode((string)$iop_id));
		$patient_no = trim((string)urldecode((string)$patient_no));
		if ($iop_id === '' || $patient_no === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Missing visit ID or patient number.</div>");
			redirect(base_url() . 'app/cashier/billing_queue');
			return;
		}

		$this->load->model('app/opd_model');
		if ($this->opd_model->is_encounter_locked($iop_id)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Cannot generate invoice: The patient encounter is closed/locked.</div>");
			redirect(base_url() . 'app/cashier/billing_queue');
			return;
		}

		$user_id = $this->session->userdata('user_id');

		// Use VisitBillingOrchestrator to ensure queue + invoice for this visit.
		$invoiceNo = '';
		try {
			$this->load->library('VisitBillingOrchestrator');
			if (isset($this->visitbillingorchestrator)
				&& is_object($this->visitbillingorchestrator)
				&& method_exists($this->visitbillingorchestrator, 'ensureVisitInvoice')) {
				$payload = $this->visitbillingorchestrator->ensureVisitInvoice((string)$iop_id, (string)$patient_no, (string)$user_id);
				if (is_array($payload) && !empty($payload['success']) && isset($payload['invoice']) && is_array($payload['invoice'])) {
					$invoiceNo = isset($payload['invoice']['invoice_no']) ? (string)$payload['invoice']['invoice_no'] : '';
				}
			}
		} catch (\Throwable $e) {
			$invoiceNo = '';
		}

		if ($invoiceNo !== '') {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Invoice ready: <strong>" . htmlspecialchars($invoiceNo, ENT_QUOTES, 'UTF-8') . "</strong></div>");
			redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoiceNo));
			return;
		}

		// Fallback to legacy engine if orchestrator was unavailable or failed gracefully.
		$res = $this->unified_billing_model->generate_invoice($iop_id, $patient_no, null, $user_id);
		if (isset($res['success']) && $res['success'] && isset($res['invoice_no'])) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Invoice created: <strong>" . htmlspecialchars((string)$res['invoice_no'], ENT_QUOTES, 'UTF-8') . "</strong></div>");
			redirect(base_url() . 'app/cashier/invoice/' . urlencode((string)$res['invoice_no']));
			return;
		}
		$err = (is_array($res) && isset($res['error'])) ? (string)$res['error'] : 'Unable to create invoice.';
		$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</div>");
		redirect(base_url() . 'app/cashier/billing_queue?iop_id=' . urlencode($iop_id) . '&patient_no=' . urlencode($patient_no));
	}

	public function bill_walkin_order($walkin_order_id = '')
	{
		$limited_walkin_collect = (!has_role(array('cashier', 'admin')) && function_exists('has_privilege') && has_privilege('cashier_walkin_collect_access'));
		$walkin_order_id = trim((string)urldecode((string)$walkin_order_id));
		if ($walkin_order_id === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Missing walk-in order ID.</div>");
			redirect(base_url() . 'app/walkin');
			return;
		}

		$this->load->model('app/walkin_order_model');
		$this->walkin_order_model->ensure_walkin_schema();
		if (!$this->db->table_exists('walkin_orders')) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Walk-in order schema not ready.</div>");
			redirect(base_url() . 'app/walkin');
			return;
		}

		$this->db->where('walkin_order_id', $walkin_order_id);
		$this->db->where('InActive', 0);
		$order = $this->db->get('walkin_orders')->row();
		if (!$order) {
			if ($limited_walkin_collect) {
				log_message('error', 'cashier.bill_walkin_order denied: limited walk-in collect attempted on missing order ' . $walkin_order_id . ' by user_id=' . (string)$this->session->userdata('user_id'));
			}
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Walk-in order not found.</div>");
			redirect(base_url() . 'app/walkin');
			return;
		}

		$existing_invoice = (isset($order->invoice_no) && trim((string)$order->invoice_no) !== '') ? trim((string)$order->invoice_no) : '';
		if ($existing_invoice !== '') {
			redirect(base_url() . 'app/cashier/invoice/' . urlencode($existing_invoice));
			return;
		}

		$user_id = $this->session->userdata('user_id');
		$this->unified_billing_model->retry_failed_queue_by_subject('WALKIN_ORDER', $walkin_order_id, null, $user_id);
		$res = $this->unified_billing_model->generate_invoice_by_subject('WALKIN_ORDER', $walkin_order_id, null, $user_id);
		if (isset($res['success']) && $res['success'] && isset($res['invoice_no'])) {
			$invoice_no = (string)$res['invoice_no'];
			try {
				$this->db->where('walkin_order_id', $walkin_order_id);
				$this->db->where('InActive', 0);
				$this->db->update('walkin_orders', array(
					'invoice_no' => $invoice_no,
					'payment_status' => 'INVOICED',
				));
			} catch (\Throwable $e) {
			}
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Invoice created: <strong>" . htmlspecialchars($invoice_no, ENT_QUOTES, 'UTF-8') . "</strong></div>");
			redirect(base_url() . 'app/cashier/invoice/' . urlencode($invoice_no));
			return;
		}

		$err = (is_array($res) && isset($res['error'])) ? (string)$res['error'] : 'Unable to create invoice.';
		$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . "</div>");
		redirect(base_url() . 'app/walkin');
	}

	/**
	 * Daily Reconciliation Report - DEPRECATED: Redirects to Billing & Finance
	 */
	public function reconciliation()
	{
		// Legacy code below
		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'reconciliation',
			'subtab' => '',
			'submodule' => ''
		));

		$date = trim((string)$this->input->get('date'));
		if ($date === '') $date = date('Y-m-d');

		$this->data['page_title'] = 'Daily Reconciliation';
		$this->data['date'] = $date;
		$this->data['reconciliation'] = $this->unified_billing_model->get_daily_reconciliation($date);
		$this->data['dashboard'] = $this->unified_billing_model->get_cashier_dashboard_summary();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/reconciliation', $this->data);
	}

	/**
	 * Financial Ledger View - DEPRECATED: Redirects to Billing & Finance
	 */
	public function ledger()
	{
		// Legacy code below
		require_role('admin');

		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'ledger',
			'subtab' => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get('from'));
		$date_to = trim((string)$this->input->get('to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '') $date_to = date('Y-m-d');

		$this->data['page_title'] = 'Financial Ledger';
		$this->data['date_from'] = $date_from;
		$this->data['date_to'] = $date_to;
		
		// Get ledger entries
		$this->db->where('transaction_date >=', $date_from);
		$this->db->where('transaction_date <=', $date_to);
		$this->db->order_by('transaction_date', 'DESC');
		$this->db->order_by('ledger_id', 'DESC');
		$this->db->limit(500);
		$this->data['entries'] = $this->db->get('financial_ledger')->result();

		// Get accounts for reference
		$this->data['accounts'] = $this->db->get('chart_of_accounts')->result();

		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/ledger', $this->data);
	}

	/**
	 * Audit Log
	 */
	public function audit_log()
	{
		// Legacy code below
		require_role('admin');

		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'audit_log',
			'subtab' => '',
			'submodule' => ''
		));

		$filters = array(
			'action_type' => trim((string)$this->input->get('action')),
			'invoice_no' => trim((string)$this->input->get('invoice')),
			'date_from' => trim((string)$this->input->get('from')),
			'date_to' => trim((string)$this->input->get('to'))
		);

		if ($filters['date_from'] === '') $filters['date_from'] = date('Y-m-d', strtotime('-7 days'));
		if ($filters['date_to'] === '') $filters['date_to'] = date('Y-m-d');

		$this->data['page_title'] = 'Audit Log';
		$this->data['filters'] = $filters;
		$this->data['entries'] = $this->unified_billing_model->get_audit_log($filters, 200);
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/audit_log', $this->data);
	}

	/**
	 * Discrepancies Report
	 */
	public function discrepancies()
	{
		// Legacy code below
		require_role('admin');

		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'discrepancies',
			'subtab' => '',
			'submodule' => ''
		));

		$this->data['page_title'] = 'Discrepancies Report';
		$this->data['issues'] = $this->unified_billing_model->detect_discrepancies();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/discrepancies', $this->data);
	}

	/**
	 * Auto-fix discrepancies (admin only, POST)
	 */
	public function fix_discrepancies()
	{
		if (!$this->current_user_is_admin()) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$type = $this->input->post('type');
		$fixed = $this->unified_billing_model->auto_fix_discrepancies($type);

		$type_label = $type ? $type : 'ALL';
		$this->session->set_flashdata('message', 
			"<div class='alert alert-success'><i class='fa fa-check'></i> Auto-fixed {$fixed} discrepancies (Type: {$type_label}).</div>");

		redirect(base_url() . 'app/cashier/discrepancies');
	}

	/**
	 * Statistics Report
	 */
	public function statistics()
	{
		// Legacy code below
		require_role('admin');

		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'statistics',
			'subtab' => '',
			'submodule' => ''
		));

		$date_from = trim((string)$this->input->get('from'));
		$date_to = trim((string)$this->input->get('to'));
		if ($date_from === '') $date_from = date('Y-m-01');
		if ($date_to === '') $date_to = date('Y-m-d');

		$this->data['page_title'] = 'Billing Statistics';
		$this->data['date_from'] = $date_from;
		$this->data['date_to'] = $date_to;
		$this->data['stats'] = $this->unified_billing_model->get_billing_statistics($date_from, $date_to);
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/cashier/statistics', $this->data);
	}
}
