<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Billing extends General
{

	private $limit = 10;

	public function __construct()
	{
		parent::__construct();
		$this->load->model("app/billing_model");
		$this->load->model("app/opd_model");
		$this->load->model("app/patient_model");
		$this->load->model("app/bill_history_model");
		$this->load->model("app/pharmacy_model");
		$this->load->model("app/smart_billing_model");
		$this->load->model("app/cashier_model");
		$this->load->model("app/unified_billing_model");
		$this->load->model("app/opd_status_engine");
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		require_role(array('cashier', 'doctor', 'admin'));
		if (!$this->session->userdata('_schema_billing_ok')) {
			$this->billing_model->ensure_nhis_billing_columns();
			$this->billing_model->install_nhis_config_table();
			$this->billing_model->ensure_nhis_day3_schema();
			$this->billing_model->ensure_nhis_day4_schema();
			$this->billing_model->ensure_nhis_day5_schema();
			$this->billing_model->ensure_billing_enhancements();
			$this->smart_billing_model->ensure_smart_billing_schema();
			$this->cashier_model->ensure_cashier_schema();
			$this->unified_billing_model->ensure_unified_billing_schema();
			$this->session->set_userdata('_schema_billing_ok', 1);
		}
	}


	public function index()
	{
		$this->dashboard();
	}
	
	/**
	 * Billing Dashboard with summary statistics
	 */
	public function dashboard()
	{
		$this->session->set_userdata(array(
			'tab' => 'billing',
			'module' => 'dashboard',
			'subtab' => '',
			'submodule' => ''
		));
		
		$data = $this->data;
		$data['title'] = 'Billing Dashboard';
		
		// Get today's billing totals
		$today = date('Y-m-d');
		$data['total_billing_today'] = $this->billing_model->get_total_billing_today();
		$data['pending_payments'] = $this->billing_model->count_pending_payments();
		$data['nhis_claims_today'] = $this->billing_model->count_nhis_claims_today();
		$data['refunds_pending'] = $this->billing_model->count_pending_refunds();
		
		// Revenue by payment type
		$revenue_by_type = $this->billing_model->get_revenue_by_payment_type($today);
		$data['cash_revenue'] = $revenue_by_type['cash']['amount'] ?? 0;
		$data['cash_count'] = $revenue_by_type['cash']['count'] ?? 0;
		$data['nhis_revenue'] = $revenue_by_type['nhis']['amount'] ?? 0;
		$data['nhis_count'] = $revenue_by_type['nhis']['count'] ?? 0;
		$data['insurance_revenue'] = $revenue_by_type['insurance']['amount'] ?? 0;
		$data['insurance_count'] = $revenue_by_type['insurance']['count'] ?? 0;
		
		// Department revenue
		$data['department_revenue'] = $this->billing_model->get_department_revenue_today();
		
		// Recent transactions
		$data['recent_transactions'] = $this->billing_model->get_recent_transactions(20);
		
		$this->load->view('app/billing/index', $data);
	}

	public function pointOfSale()
	{
		// Unified POS - redirect to /app/pos/pos_visit with IO_ID
		$patientId = $this->input->post('IO_ID');
		if ($patientId === NULL || $patientId === '') {
			$patientId = $this->input->post('patiIO_IDent');
		}
		if ($patientId === NULL || $patientId === '') {
			$patientId = $this->input->get('patient_id');
		}
		if ($patientId === NULL || $patientId === '') {
			$patientId = $this->input->get('IO_ID');
		}
		
		// Redirect to unified POS with patient context
		if ($patientId !== NULL && $patientId !== '') {
			redirect(base_url() . 'app/pos/pos_visit/' . $patientId);
		} else {
			redirect(base_url() . 'app/pos');
		}
	}

	public function searchPatient()
	{
		// user restriction function - cashiers always have billing access
		$this->session->set_userdata('page_name', 'pos');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			if (!has_role(array('cashier', 'admin'))) {
				redirect(base_url() . 'access_denied');
			}
		}
		// end of user restriction function

		$this->session->set_userdata(array(
			'tab'			=>		'billing',
			'module'		=>		'pos',
			'subtab'		=>		'',
			'submodule'	=>		''
		));

		$this->data['patientLists'] = $this->billing_model->getOPDPatient();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view("app/billing/searchPatient", $this->data);
	}

	/**
	 * AJAX endpoint for patient search autocomplete
	 * Returns JSON array of matching patients
	 */
	public function search_patient_ajax()
	{
		// Suppress any output buffering issues
		while (ob_get_level()) {
			ob_end_clean();
		}
		
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');
		
		try {
			$query = trim((string)$this->input->get('q'));
			$limit = (int)$this->input->get('limit');
			if ($limit <= 0 || $limit > 50) $limit = 20;
			
			$results = $this->billing_model->searchPatientForBilling($query, $limit);
			
			echo json_encode($results);
		} catch (Exception $e) {
			echo json_encode(array('error' => $e->getMessage()));
		}
		exit;
	}

	public function getItem($id)
	{
		$this->data['itemList'] = $this->billing_model->itemList($id);
		$this->data['particularName'] = $this->billing_model->particularName($id);
		$this->load->view("app/billing/itemList", $this->data);
	}

	public function getSonoItem($id = 18)
	{
		$this->load->model('app/laboratory_model');
		$this->laboratory_model->install_imaging_tables();
		$this->data['itemList'] = $this->laboratory_model->sonography_item_dropdown();
		$this->data['particularName'] = (object)array('group_name' => 'ULTRASONOGRAPHY');
		$this->load->view("app/billing/itemList", $this->data);
	}



	public function getRate($id)
	{
		$patient_no = $this->input->get('patient_no');
		if ($patient_no !== null && trim((string)$patient_no) !== '') {
			$nhisRate = $this->billing_model->getNhisServiceRate((int)$id, (string)$patient_no);
			if ($nhisRate) {
				$this->data['getRate'] = $nhisRate;
				$this->data['getRate']->charge_amount = $nhisRate->effective_rate;
				$this->data['nhis_covered'] = $nhisRate->nhis_covered;
			} else {
				$this->data['getRate'] = $this->billing_model->getRate($id);
				$this->data['nhis_covered'] = false;
			}
		} else {
			$this->data['getRate'] = $this->billing_model->getRate($id);
			$this->data['nhis_covered'] = false;
		}
		$this->load->view("app/billing/getRate", $this->data);
	}


	public function save_invoice()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$invNo = (string)$this->input->post('invoiceno');
		$postedPatNo = (string)$this->input->post('patient_no');
		$postedIopNo = (string)$this->input->post('opd_no');
		
		// Check if invoice already exists - if so, redirect to update_invoice
		$existing = $this->billing_model->headerInv2($invNo);
		if ($existing && isset($existing->invoice_no) && $existing->invoice_no === $invNo) {
			$exPat = isset($existing->patient_no) ? (string)$existing->patient_no : '';
			$exIop = isset($existing->iop_id) ? (string)$existing->iop_id : (isset($existing->IO_ID) ? (string)$existing->IO_ID : '');
			if (($postedPatNo !== '' && $exPat !== '' && $postedPatNo !== $exPat) || ($postedIopNo !== '' && $exIop !== '' && $postedIopNo !== $exIop)) {
				redirect(base_url() . 'access_denied');
				return;
			}
			// Invoice already exists - redirect to update flow
			return $this->update_invoice_from_save();
		}
		
		$paymentType = (string)$this->input->post('paymentType');
		if ($paymentType === 'insurance') {
			$patientNo = $this->input->post('patient_no');
			$status = $this->patient_model->get_insurance_card_status($patientNo);
			if ($status !== 'ACTIVE') {
				$_POST['paymentType'] = 'cash';
				$_POST['insurance_company'] = '';
				$msg = ($status === 'N/A') 
					? 'Patient is Self Pay. Billing has been set to Cash.'
					: 'Insurance card is inactive. Billing has been set to Cash.';
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $msg . "</div>");
			}
		}

		// SSOT chokepoint: route the entire save sequence through the facade.
		// (Phase 4 / Step 2 — no behavior change; sequence is identical to the
		// previous inline implementation.)
		$this->load->model('app/Billing_facade_model', 'billing_facade_model');
		$result = $this->billing_facade_model->save_invoice_from_post(
			$this->session->userdata('user_id')
		);

		$invNo = $result['invoice_no'];
		$patNo = $result['patient_no'];
		$iopNo = $result['iop_no'];

		if (empty($result['ok'])) {
			$err = htmlspecialchars((string)$result['error'], ENT_QUOTES, 'UTF-8');
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invoice could not be saved: " . $err . "</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transaction successfully Saved!</div>");
		}

		// Check if request came from POS page - redirect back to POS if so
		$referer = $this->input->server('HTTP_REFERER');
		if (strpos($referer, 'app/pos/pos_patient') !== false || strpos($referer, 'app/pos/saved') !== false) {
			redirect(base_url() . "app/pos/saved/" . url_safe_id($iopNo) . "/" . $patNo . "/" . url_safe_id($invNo), $this->data);
		} else {
			redirect(base_url() . "app/opd/billingView/" . url_safe_id($iopNo) . "/" . $patNo, $this->data);
		}
	}
	
	/**
	 * Wrapper to call update_invoice logic from save_invoice when editing existing invoices
	 */
	private function update_invoice_from_save()
	{
		// Delegate to the actual update_invoice method
		return $this->update_invoice();
	}

	public function update_invoice()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$invNo = (string)$this->input->post('invoiceno');
		$postedPatNo = (string)$this->input->post('patient_no');
		$postedIopNo = (string)$this->input->post('opd_no');
		$existing = $this->billing_model->headerInv2($invNo);
		if (!$existing) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$exPat = isset($existing->patient_no) ? (string)$existing->patient_no : '';
		$exIop = isset($existing->iop_id) ? (string)$existing->iop_id : (isset($existing->IO_ID) ? (string)$existing->IO_ID : '');
		if (($postedPatNo !== '' && $exPat !== '' && $postedPatNo !== $exPat) || ($postedIopNo !== '' && $exIop !== '' && $postedIopNo !== $exIop)) {
			redirect(base_url() . 'access_denied');
			return;
		}
		if ($existing && isset($existing->receipt_no) && trim((string)$existing->receipt_no) !== '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>This invoice already has a receipt and cannot be edited. Please create a new invoice instead.</div>");
			// Check if request came from POS page - redirect back to POS if so
			$referer = $this->input->server('HTTP_REFERER');
			$patNo = $this->input->post('patient_no');
			$iopNo = $this->input->post('opd_no');
			if (strpos($referer, 'app/pos/pos_patient') !== false || strpos($referer, 'app/pos/saved') !== false) {
				redirect(base_url() . "app/pos/saved/" . url_safe_id($iopNo) . "/" . $patNo . "/" . url_safe_id($invNo), $this->data);
			} else {
				redirect(base_url() . "app/opd/billingView/" . url_safe_id($iopNo) . "/" . $patNo, $this->data);
			}
			return;
		}

		$settlement = $this->billing_model->get_invoice_settlement($invNo);
		if (!empty($settlement['is_settled'])) {
			show_error('Invoice is settled and cannot be edited');
		}

		$paymentType = (string)$this->input->post('paymentType');
		if ($paymentType === 'insurance') {
			$patientNo = $this->input->post('patient_no');
			$status = $this->patient_model->get_insurance_card_status($patientNo);
			if ($status !== 'ACTIVE') {
				$_POST['paymentType'] = 'cash';
				$_POST['insurance_company'] = '';
				$msg = ($status === 'N/A') 
					? 'Patient is Self Pay. Billing has been set to Cash.'
					: 'Insurance card is inactive. Billing has been set to Cash.';
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $msg . "</div>");
			}
		}

		// SSOT chokepoint: route the entire update sequence through the facade.
		// (Phase 4 / Step 2 — no behavior change; sequence is identical to the
		// previous inline implementation.)
		$this->load->model('app/Billing_facade_model', 'billing_facade_model');
		$result = $this->billing_facade_model->update_invoice_from_post(
			$this->session->userdata('user_id')
		);

		$invNo = $result['invoice_no'];
		$patNo = $result['patient_no'];
		$iopNo = $result['iop_no'];

		if (empty($result['ok'])) {
			$err = htmlspecialchars((string)$result['error'], ENT_QUOTES, 'UTF-8');
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invoice could not be updated: " . $err . "</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invoice successfully Saved!</div>");
		}

		// Check if request came from POS page - redirect back to POS if so
		$referer = $this->input->server('HTTP_REFERER');
		if (strpos($referer, 'app/pos/pos_patient') !== false || strpos($referer, 'app/pos/saved') !== false) {
			redirect(base_url() . "app/pos/saved/" . url_safe_id($iopNo) . "/" . $patNo . "/" . url_safe_id($invNo), $this->data);
		} else {
			redirect(base_url() . "app/opd/billingView/" . url_safe_id($iopNo) . "/" . $patNo, $this->data);
		}
	}

	public function billingpdf()
	{
		$this->require_report_scope('financial');
		$this->audit_report_generation('financial', 'billing_invoice_pdf', 'pdf');
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$InvNo = $this->segment_decoded(6);

		$this->load->helper('file');
		$this->load->helper('dompdf');

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$patientInfo = $this->patient_model->getPatientInfo($patient_no);

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();

		$this->data['headerInv'] = $this->billing_model->headerInv2($InvNo);
		$this->data['detailsInv'] = $this->billing_model->detailsInv2($InvNo);

		$filename = $InvNo . "_" . date("mdY");

		$html = $this->load->view("app/opd/printInv2", $this->data, true);
		pdf_create($html, $filename, TRUE);
	}

	public function drug_list($id)
	{
		$this->data['drug_list'] = $this->billing_model->drug_list($id);
		$this->data['medicineName'] = $this->billing_model->medicineName($id);
		$this->load->view("app/billing/drug_list", $this->data);
	}

	public function getDrugRate($id)
	{
		$patient_no = $this->input->get('patient_no');
		if ($patient_no !== null && trim((string)$patient_no) !== '') {
			$nhisDrug = $this->billing_model->getNhisDrugRate((int)$id, (string)$patient_no);
			if ($nhisDrug) {
				$this->data['getDrugRate'] = $nhisDrug;
				$this->data['getDrugRate']->nPrice = $nhisDrug->effective_price;
				$this->data['nhis_covered'] = $nhisDrug->nhis_covered;
			} else {
				$this->data['getDrugRate'] = $this->billing_model->getDrugRate($id);
				$this->data['nhis_covered'] = false;
			}
		} else {
			$this->data['getDrugRate'] = $this->billing_model->getDrugRate($id);
			$this->data['nhis_covered'] = false;
		}
		$this->load->view("app/billing/getDrugRate", $this->data);
	}

	public function pharmacy_bills()
	{
		$this->session->set_userdata(array(
			'tab'       => 'billing',
			'module'    => 'pharmacy_bills',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->pharmacy_model->ensure_pharmacy_ghs_schema();
		$this->pharmacy_model->ensure_flexible_workflow_schema();

		$filters = array(
			'status'    => (string)$this->input->get_post('status') ?: 'PENDING',
			'date_from' => (string)$this->input->get_post('date_from'),
			'date_to'   => (string)$this->input->get_post('date_to'),
			'search'    => (string)$this->input->get_post('search'),
		);

		$this->data['filters']         = $filters;
		$this->data['bills']           = $this->pharmacy_model->get_pending_pharmacy_bills($filters);
		$this->data['deferred_bills']  = $this->pharmacy_model->get_deferred_pharmacy_bills();
		$this->data['waiver_requests'] = $this->billing_model->get_pending_waiver_requests();
		$this->data['outstanding']     = $this->billing_model->get_outstanding_balances_all(array('source_module' => 'PHARMACY'));
		$this->data['message']         = $this->session->flashdata('message');
		$this->data['is_admin']        = $this->current_user_is_admin();
		$this->data['summary']         = array(
			'pending_today'    => $this->pharmacy_model->count_pending_pharmacy_bills_today(),
			'paid_today'       => $this->pharmacy_model->count_paid_pharmacy_bills_today(),
			'total_pending'    => $this->pharmacy_model->count_awaiting_payment(),
			'deferred'         => $this->pharmacy_model->count_deferred_pharmacy(),
			'external'         => $this->pharmacy_model->count_external_purchases(),
			'pending_waivers'  => $this->billing_model->count_pending_waiver_requests(),
			'outstanding_bal'  => $this->billing_model->count_outstanding_balances(),
		);
		$this->load->view('app/billing/pharmacy_bills', $this->data);
	}

	public function approve_waiver()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		if (!$this->current_user_is_admin()) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Only Admin or Super-Admin may approve waivers.</div>');
			redirect(base_url().'app/billing/pharmacy_bills');
			return;
		}
		$waiver_id = (int)$this->input->post('waiver_id');
		$notes     = trim((string)$this->input->post('approval_notes'));
		$action    = trim((string)$this->input->post('action'));
		$user_id   = $this->session->userdata('user_id');

		if ($action === 'reject') {
			$result = $this->billing_model->reject_waiver_admin($waiver_id, $user_id, $notes);
			$msg = $result['ok'] ? '<div class="alert alert-warning">Waiver request rejected.</div>' : '<div class="alert alert-danger">Error: '.$result['error'].'</div>';
		} else {
			$result = $this->billing_model->approve_waiver_admin($waiver_id, $user_id, $notes);
			$msg = $result['ok'] ? '<div class="alert alert-success">Waiver approved. Bill marked as WAIVED.</div>' : '<div class="alert alert-danger">Error: '.$result['error'].'</div>';
		}
		$this->session->set_flashdata('message', $msg);
		redirect(base_url().'app/billing/pharmacy_bills?status=WAIVED');
	}

	public function settle_outstanding()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		$id      = (int)$this->input->post('outstanding_id');
		$user_id = $this->session->userdata('user_id');
		$this->billing_model->settle_outstanding_balance_admin($id, $user_id);
		$this->session->set_flashdata('message', '<div class="alert alert-success">Outstanding balance marked as settled.</div>');
		redirect(base_url().'app/billing/pharmacy_bills?status=DEFERRED');
	}

	public function pharmacy_payment()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->pharmacy_model->ensure_pharmacy_ghs_schema();

		$bill_id    = (int)$this->input->post('bill_id');
		$action     = trim((string)$this->input->post('action'));
		$reason     = trim((string)$this->input->post('reason'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id    = $this->session->userdata('user_id');

		if ($bill_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'>Invalid bill ID.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/billing/pharmacy_bills');
			return;
		}

		if ($action === 'cancel') {
			$result = $this->pharmacy_model->cancel_pharmacy_bill($bill_id, $user_id, $reason);
			$successMsg = "Pharmacy bill cancelled.";
		} else {
			$result = $this->pharmacy_model->mark_pharmacy_bill_paid($bill_id, $user_id);
			$successMsg = "Payment received. Pharmacy notified — patient may collect medication.";
		}

		if ($result['ok']) {
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $successMsg . "</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . htmlspecialchars($result['error']) . "</div>");
		}
		redirect($return_url !== '' ? $return_url : base_url() . 'app/billing/pharmacy_bills');
	}

	/* ================================================================== */
	/*  SMART BILLING — GHS 1-CLICK BILLING                               */
	/* ================================================================== */

	public function smart_billing()
	{
		require_role(array('cashier', 'admin'));
		$this->session->set_userdata(array(
			'tab'       => 'billing',
			'module'    => 'smart_billing',
			'subtab'    => '',
			'submodule' => ''
		));
		$date = (string)$this->input->get_post('date') ?: date('Y-m-d');
		$this->data['date']            = $date;
		$this->data['pending_queue']   = $this->smart_billing_model->get_pending_billing_queue($date);
		$this->data['billed_queue']    = $this->smart_billing_model->get_billed_queue($date);
		$this->data['pending_count']   = is_array($this->data['pending_queue']) ? count($this->data['pending_queue']) : 0;
		$this->data['billed_today']    = is_array($this->data['billed_queue']) ? count($this->data['billed_queue']) : 0;
		$this->data['waivers_today']   = 0;
		if (is_array($this->data['pending_queue'])) {
			foreach ($this->data['pending_queue'] as $row) {
				if ($row && !empty($row->consultation_waived)) {
					$this->data['waivers_today']++;
				}
			}
		}
		if (is_array($this->data['billed_queue'])) {
			foreach ($this->data['billed_queue'] as $row) {
				if ($row && !empty($row->consultation_waived)) {
					$this->data['waivers_today']++;
				}
			}
		}
		$this->data['config']          = $this->smart_billing_model->get_all_config();
		$this->data['message']         = $this->session->flashdata('message');
		$this->load->view('app/billing/smart_billing', $this->data);
	}

	public function smart_billing_preview()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'error' => 'Method not allowed')); return;
		}
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		if ($iop_id === '' || $patient_no === '') {
			echo json_encode(array('success' => false, 'error' => 'Missing iop_id or patient_no')); return;
		}
		$preview = $this->smart_billing_model->get_billing_preview($iop_id, $patient_no);
		echo json_encode(array('success' => true, 'data' => $preview));
	}

	public function debug_run_visit_fee_autobill()
	{
		require_role(array('admin'));
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');
		$method = strtoupper((string)$this->input->method(TRUE));
		if ($method !== 'POST' && $method !== 'GET') {
			echo json_encode(array('success' => false, 'error' => 'Method not allowed'));
			return;
		}
		$iop_id     = trim((string)$this->input->get_post('iop_id'));
		$patient_no = trim((string)$this->input->get_post('patient_no'));
		$backfill   = trim((string)$this->input->get_post('backfill_queue'));
		$user_id    = $this->session->userdata('user_id');
		if ($iop_id === '' || $patient_no === '') {
			echo json_encode(array('success' => false, 'error' => 'Missing iop_id or patient_no')); return;
		}

		try {
			$cfgKeys = array('auto_bill_visit_fees', 'enable_registration_fee', 'enable_consultation_fee');
			$cfg = array();
			foreach ($cfgKeys as $k) {
				$cfg[$k] = $this->smart_billing_model->get_config($k, null);
			}
			$cfgRows = null;
			try {
				if (method_exists($this->db, 'table_exists') && $this->db->table_exists('smart_billing_config')) {
					$this->db->select('config_id, config_key, config_value, updated_at, InActive');
					$this->db->where_in('config_key', $cfgKeys);
					$this->db->order_by('config_id', 'ASC');
					$qCfg = $this->db->get('smart_billing_config');
					$cfgRows = $qCfg ? $qCfg->result_array() : null;
				}
			} catch (Throwable $eCfg) {
				$cfgRows = array('error' => $eCfg->getMessage());
			}

			$this->load->model('app/visit_billing_resolver_model');
			$result = $this->visit_billing_resolver_model->auto_bill_visit_fees($iop_id, $patient_no, $user_id);
			$queue = null;
			if ($backfill === '1' || strtolower($backfill) === 'true' || strtolower($backfill) === 'yes') {
				try {
					$this->load->model('app/unified_billing_model');
					$this->unified_billing_model->backfill_visit_fees_for_queue($iop_id, $patient_no);
					$this->unified_billing_model->backfill_pending_transactions_to_queue($iop_id, $patient_no);
					$queue = $this->unified_billing_model->get_billing_queue($iop_id, $patient_no);
				} catch (Throwable $e2) {
					$queue = array('error' => $e2->getMessage());
				}
			}
			echo json_encode(array('success' => true, 'data' => array('result' => $result, 'queue' => $queue, 'config' => $cfg, 'config_rows' => $cfgRows)));
			return;
		} catch (Throwable $e) {
			echo json_encode(array('success' => false, 'error' => $e->getMessage()));
			return;
		}
	}

	public function runtime_db_probe()
	{
		require_role(array('admin'));
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');
		$out = array(
			'environment' => defined('ENVIRONMENT') ? ENVIRONMENT : null,
			'env' => array(
				'DB_HOSTNAME' => getenv('DB_HOSTNAME') !== false ? getenv('DB_HOSTNAME') : null,
				'DB_USERNAME' => getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : null,
				'DB_DATABASE' => getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : null,
				'CI_ENV' => getenv('CI_ENV') !== false ? getenv('CI_ENV') : null,
				'APP_BASE_URL' => getenv('APP_BASE_URL') !== false ? getenv('APP_BASE_URL') : null,
			),
			'ci_db' => array(
				'hostname' => isset($this->db->hostname) ? $this->db->hostname : null,
				'database' => isset($this->db->database) ? $this->db->database : null,
				'dbdriver' => isset($this->db->dbdriver) ? $this->db->dbdriver : null,
				'char_set' => isset($this->db->char_set) ? $this->db->char_set : null,
			),
			'mysql_identity' => null,
			'tables' => array(),
		);
		try {
			$q = $this->db->query('SELECT @@hostname AS mysql_host, DATABASE() AS db, USER() AS user, @@port AS port');
			$out['mysql_identity'] = $q ? $q->row_array() : null;
		} catch (Throwable $e) {
			$out['mysql_identity'] = array('error' => $e->getMessage());
		}
		try {
			$tables = array(
				'patient_review_authorizations',
				'visit_billing_decision_audit',
				'billing_transactions',
				'billing_queue',
				'iop_room_charge',
				'iop_sonography_charge',
			);
			foreach ($tables as $t) {
				$exists = null;
				if (method_exists($this->db, 'table_exists')) {
					$exists = (bool)$this->db->table_exists($t);
				}
				$out['tables'][$t] = $exists;
			}
		} catch (Throwable $e) {
			$out['tables_error'] = $e->getMessage();
		}
		try {
			$includeSono = trim((string)$this->input->get_post('include_sono_anomalies'));
			$iopFilter = trim((string)$this->input->get_post('iop_id'));
			$invoiceFilter = trim((string)$this->input->get_post('invoice_no'));
			$limit = (int)$this->input->get_post('limit');
			if ($limit <= 0) { $limit = 200; }
			if ($limit > 500) { $limit = 500; }
			$shouldRun = ($includeSono === '1' || $includeSono === 'true' || $includeSono === 'yes' || $iopFilter !== '' || $invoiceFilter !== '');
			$out['sonography_source_ref_anomalies'] = array(
				'ran' => false,
				'filters' => array('iop_id' => $iopFilter !== '' ? $iopFilter : null, 'invoice_no' => $invoiceFilter !== '' ? $invoiceFilter : null, 'limit' => $limit),
				'rows' => array(),
				'error' => null,
			);
			if ($shouldRun && $this->db->table_exists('billing_queue')) {
				$hasSonoCharge = $this->db->table_exists('iop_sonography_charge');
				$sel = array(
					'q.queue_id',
					'q.iop_id',
					'q.patient_no',
					'q.invoice_no',
					'q.status',
					'q.item_id',
					'q.item_name',
					'q.unit_price',
					'q.source_module',
					'q.source_ref',
					"CAST(q.source_ref AS UNSIGNED) AS source_ref_int",
				);
				if ($hasSonoCharge) {
					$sel[] = 'sc.charge_id AS resolved_charge_id';
					$sel[] = 'sc.io_lab_id AS resolved_io_lab_id';
					$sel[] = "CASE WHEN sc.charge_id = CAST(q.source_ref AS UNSIGNED) THEN 'CHARGE_ID' WHEN sc.io_lab_id = CAST(q.source_ref AS UNSIGNED) THEN 'IO_LAB_ID' WHEN sc.charge_id IS NULL THEN 'UNRESOLVED' ELSE 'UNRESOLVED' END AS match_type";
					$sel[] = "CASE WHEN sc.charge_id IS NOT NULL THEN CONCAT('sono_charge_id:', sc.charge_id) ELSE NULL END AS expected_source_ref";
					$sel[] = "CASE WHEN sc.charge_id IS NOT NULL AND CAST(q.item_id AS UNSIGNED) = sc.charge_id THEN 1 ELSE 0 END AS item_id_matches_charge_id";
				}
				$this->db->select(implode(',', $sel), false);
				$this->db->from('billing_queue q');
				if ($hasSonoCharge) {
					$this->db->join('iop_sonography_charge sc', "sc.InActive = 0 AND (sc.charge_id = CAST(q.source_ref AS UNSIGNED) OR sc.io_lab_id = CAST(q.source_ref AS UNSIGNED))", 'left', false);
				}
				$this->db->where('q.InActive', 0);
				$this->db->where('UPPER(q.item_type)', 'SONOGRAPHY');
				$this->db->where("q.source_ref REGEXP '^[0-9]+$'", null, false);
				if ($iopFilter !== '') {
					$this->db->where('q.iop_id', $iopFilter);
				}
				if ($invoiceFilter !== '') {
					$this->db->where('q.invoice_no', $invoiceFilter);
				}
				$this->db->order_by('q.queue_id', 'DESC');
				$this->db->limit($limit);
				$rows = $this->db->get()->result_array();
				$out['sonography_source_ref_anomalies']['ran'] = true;
				$out['sonography_source_ref_anomalies']['rows'] = $rows ? $rows : array();
			}
		} catch (Throwable $e) {
			if (!isset($out['sonography_source_ref_anomalies']) || !is_array($out['sonography_source_ref_anomalies'])) {
				$out['sonography_source_ref_anomalies'] = array('ran' => false, 'filters' => array(), 'rows' => array(), 'error' => null);
			}
			$out['sonography_source_ref_anomalies']['error'] = $e->getMessage();
		}
		echo json_encode($out);
		exit;
	}

	public function one_click_billing()
	{
		require_role(array('cashier', 'admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'error' => 'Method not allowed')); return;
		}
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$user_id    = $this->session->userdata('user_id');
		if ($iop_id === '' || $patient_no === '') {
			echo json_encode(array('success' => false, 'error' => 'Missing iop_id or patient_no')); return;
		}
		$result = $this->smart_billing_model->execute_one_click_billing($iop_id, $patient_no, $user_id);
		echo json_encode($result);
	}

	public function smart_billing_history($patient_no = '')
	{
		$patient_no = $patient_no !== '' ? $patient_no : trim((string)$this->input->get_post('patient_no'));
		if ($patient_no === '') { redirect(base_url().'app/billing/searchPatient'); return; }
		$this->session->set_userdata(array(
			'tab'       => 'billing',
			'module'    => 'smart_billing',
			'subtab'    => '',
			'submodule' => ''
		));
		$this->db->select('patient_no, CONCAT(lastname,\' \',firstname) AS patient_name, birthday, Insurance_comp');
		$pRow = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no))->row();
		$this->data['patient']  = $pRow;
		$this->data['history']  = $this->smart_billing_model->get_patient_history($patient_no);
		$this->data['message']  = $this->session->flashdata('message');
		$this->load->view('app/billing/smart_billing_history', $this->data);
	}

	public function smart_billing_config_save()
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') { redirect(base_url().'app/billing/smart_billing'); return; }
		$keys = array(
			'auto_bill_visit_fees',
			'enable_registration_fee',
			'enable_consultation_fee',
			'registration_fee_item_id',
			'consultation_fee_item_id',
			'detention_fee_item_id',
			'registration_fee_cash', 'registration_fee_nhis',
			'consultation_fee_cash', 'consultation_fee_nhis',
			'detention_fee_cash', 'detention_fee_nhis',
			'review_window_days', 'missed_appt_grace_days',
		);
		$user_id = $this->session->userdata('user_id');
		foreach ($keys as $k) {
			$v = $this->input->post($k);
			if ($v !== null) {
				$this->smart_billing_model->set_config($k, trim((string)$v), $user_id);
			}
		}
		$this->session->set_flashdata('message', "<div class='alert alert-success'>Smart billing configuration saved.</div>");
		redirect(base_url().'app/billing/smart_billing');
	}

	public function smart_billing_visit_fee_item_candidates()
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'error' => 'Method not allowed'));
			return;
		}
		$payload = $this->smart_billing_model->get_visit_fee_item_candidate_payload();
		echo json_encode(array(
			'success' => true,
			'data' => $payload,
			'csrf_hash' => $this->security->get_csrf_hash(),
		));
	}

	public function smart_billing_apply_visit_fee_item_ids()
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'error' => 'Method not allowed'));
			return;
		}
		$regId = (int)$this->input->post('registration_fee_item_id');
		$conId = (int)$this->input->post('consultation_fee_item_id');
		$user_id = $this->session->userdata('user_id');

		if ($regId <= 0 || $conId <= 0) {
			echo json_encode(array('success' => false, 'error' => 'Missing registration or consultation item ID', 'csrf_hash' => $this->security->get_csrf_hash()));
			return;
		}

		$this->smart_billing_model->set_config('registration_fee_item_id', (string)$regId, $user_id);
		$this->smart_billing_model->set_config('consultation_fee_item_id', (string)$conId, $user_id);

		echo json_encode(array(
			'success' => true,
			'message' => 'Fee item IDs applied.',
			'csrf_hash' => $this->security->get_csrf_hash(),
		));
	}

	public function final_clearance()
	{
		list($iop_id, $patient_no) = $this->get_url_params(4, 5);
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		if ($iop_id === '') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if ($patient_no === '') {
			$this->db->select('patient_no');
			$this->db->where(array('IO_ID' => $iop_id, 'InActive' => 0));
			$this->db->limit(1);
			$row = $this->db->get('patient_details_iop')->row();
			$patient_no = ($row && isset($row->patient_no)) ? (string)$row->patient_no : '';
		}
		$req = $this->billing_model->final_clearance_requirements($iop_id, $patient_no !== '' ? $patient_no : null);
		if (!$req || !isset($req['ok']) || !$req['ok']) {
			$msg = 'Cannot perform final system clearance.';
			if (isset($req['clinical_ok']) && !$req['clinical_ok']) {
				$msg = 'Cannot perform final system clearance: OPD Clinical Clearance is not completed.';
			} elseif (isset($req['medication_ok']) && !$req['medication_ok']) {
				$msg = 'Cannot perform final system clearance: Pharmacy Medication Clearance is not completed.';
			} elseif (isset($req['pharmacy_billing_ok']) && !$req['pharmacy_billing_ok']) {
				$unpaid = isset($req['unpaid_pharmacy_bills']) ? (int)$req['unpaid_pharmacy_bills'] : 0;
				$msg = 'Cannot perform final system clearance: ' . $unpaid . ' pharmacy bill(s) are still awaiting payment. Please collect payment at cashier first.';
			} elseif (isset($req['billing_ok']) && !$req['billing_ok']) {
				$msg = 'Cannot perform final system clearance: billing is not fully settled.';
			}
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $msg . "</div>");
			redirect(base_url() . 'app/billing/searchPatient');
			return;
		}

		$user_id = $this->session->userdata('user_id');
		$isIpd = (isset($req['encounter_type']) && strtoupper((string)$req['encounter_type']) === 'IPD');
		
		if ($isIpd) {
			$this->load->model('app/bed_occupancy_model');
			$discharge = $this->bed_occupancy_model->discharge_ipd($iop_id, $patient_no, $user_id);
			if (!is_array($discharge) || !isset($discharge['ok']) || $discharge['ok'] !== true) {
				$err = is_array($discharge) && isset($discharge['error']) ? (string)$discharge['error'] : 'unknown';
				$msg = ($err === 'admission_not_found')
					? 'Cannot perform final system clearance: IPD admission was not found.'
					: (($err === 'admission_not_active') ? 'Cannot perform final system clearance: IPD admission is not active.' : 'Cannot perform final system clearance: IPD discharge could not be completed.');
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $msg . "</div>");
				redirect(base_url() . 'app/billing/searchPatient');
				return;
			}
		} else {
			$result = $this->opd_status_engine->final_clear($iop_id, $user_id);
			if (!$result['success']) {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . htmlspecialchars($result['message']) . "</div>");
				redirect(base_url() . 'app/billing/searchPatient');
				return;
			}
		}
		
		// Update clearance workflow stage
		$this->billing_model->upsert_clearance_stage($iop_id, 'FINAL', $patient_no !== '' ? $patient_no : null, $user_id);
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Final system clearance completed. Patient has been discharged.</div>");
		redirect(base_url() . 'app/billing/searchPatient');
	}
}
