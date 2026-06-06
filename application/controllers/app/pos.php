<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Pos extends General
{

	private $limit = 10;

	public function __construct()
	{
		parent::__construct();
		$this->load->model("general_model");
		$this->load->model("app/billing_model");
		$this->load->model("app/opd_model");
		$this->load->model("app/patient_model");
		$this->load->model("app/cashier_model");
		$this->load->model("app/unified_billing_model");
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		require_role('cashier');
		$this->load->model('app/billing_transaction_model');
		$needSchema = !$this->session->userdata('_schema_pos_ok');
		if (!$needSchema) {
			$needSchema = !$this->billing_transaction_model->column_exists('billing_transactions', 'item_ref');
		}
		if ($needSchema) {
			$this->billing_model->ensure_billing_enhancements();
			$this->cashier_model->ensure_cashier_schema();
			$this->billing_transaction_model->ensure_billing_transaction_schema();
			$this->unified_billing_model->ensure_unified_billing_schema();
			$this->session->set_userdata('_schema_pos_ok', 1);
		}
	}

	public function index()
	{
		// user restriction function - cashiers always have POS access
		$this->session->set_userdata('page_name', 'pos');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			if (!has_role(array('cashier', 'admin'))) {
				redirect(base_url() . 'access_denied');
			}
		}
		// end of user restriction function		 

		$this->posPage();
	}

	function posPage()
	{
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();

		$this->data['medicine_cat'] = $this->billing_model->medicine_cat();


		$this->data['reason_dicount'] = $this->general_model->reason_dicount();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();
		$this->data['receipt_no2'] = $this->billing_model->receipt_no();

		$this->load->view("app/pos/index", $this->data);
	}

	function pos_patient($patient_no, $iop_id = null)
	{
		$patient_no = sanitize_id_for_db((string)$patient_no);
		if ($iop_id !== null) {
			$iop_id = sanitize_id_for_db((string)$iop_id);
		}
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['medicine_cat'] = $this->billing_model->medicine_cat();
		$this->data['reason_dicount'] = $this->general_model->reason_dicount();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();
		$this->data['receipt_no2'] = $this->billing_model->receipt_no();

		$this->data['patient_rows'] = $this->patient_model->getPatientInfo($patient_no);
		
		// If iop_id is provided (from consolidated billing), use it directly
		if ($iop_id !== null && $iop_id !== '') {
			$visit = $this->db->select('IO_ID')
				->where(array('IO_ID' => $iop_id, 'patient_no' => $patient_no, 'InActive' => 0))
				->limit(1)
				->get('patient_details_iop')
				->row();
			if (!$visit) {
				redirect(base_url() . 'access_denied');
				return;
			}
			$this->data['auto_load_io_id'] = $iop_id;
			$this->data['auto_load_patient_no'] = $patient_no;
			$this->data['iop_no'] = $iop_id;
			$this->data['direct'] = TRUE; // Show patient info immediately
			$this->data['consolidated_billing'] = TRUE;
			
			// Load all pending items for this patient/visit for consolidated billing
			$this->load->model('app/dashboard_model');
			$this->data['pending_items'] = $this->dashboard_model->get_patient_pending_items($patient_no, $iop_id);
		} else {
			// Find the patient's most recent active visit (IO_ID) for billing
			$this->db->select('IO_ID');
			$this->db->from('patient_details_iop');
			$this->db->where('patient_no', $patient_no);
			$this->db->where('InActive', 0);
			$this->db->order_by('IO_ID', 'DESC');
			$this->db->limit(1);
			$visit = $this->db->get()->row();
			
			if ($visit && isset($visit->IO_ID)) {
				$this->data['auto_load_io_id'] = $visit->IO_ID;
				$this->data['auto_load_patient_no'] = $patient_no;
				$this->data['iop_no'] = $visit->IO_ID;
				$this->data['direct'] = TRUE; // Show patient info immediately
			} else {
				// Fallback to direct mode if no visit found
				$this->data['direct'] = TRUE;
			}
		}
		
		$this->load->view("app/pos/index", $this->data);
	}

	/**
	 * POS page with visit/IO_ID context - unified entry point
	 * Accepts IO_ID from billing/pointOfSale redirect
	 * Redirects to main POS with auto-load patient script
	 */
	function pos_visit($io_id = null)
	{
		// Get IO_ID from parameter, POST, or GET
		if ($io_id === null || $io_id === '') {
			$io_id = $this->input->post('IO_ID');
		}
		if ($io_id === null || $io_id === '') {
			$io_id = $this->input->get('IO_ID');
		}
		
		// Decode URL-safe ID (OP-000002 -> OP 000002)
		if ($io_id !== null && $io_id !== '') {
			$io_id = url_decode_id($io_id);
			$io_id = sanitize_id_for_db((string)$io_id);
		}
		
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['medicine_cat'] = $this->billing_model->medicine_cat();
		$this->data['reason_dicount'] = $this->general_model->reason_dicount();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();
		$this->data['receipt_no2'] = $this->billing_model->receipt_no();

		// Pass IO_ID to view for auto-loading patient via AJAX (keeps all buttons visible)
		if ($io_id !== null && $io_id !== '') {
			$this->data['auto_load_io_id'] = $io_id;
		}
		
		$this->load->view("app/pos/index", $this->data);
	}

	/**
	 * POS page for billing a specific pending lab item
	 * Auto-loads the patient and the lab item into the billing form
	 */
	function pos_lab_bill($lab_bill_id = null)
	{
		if ($lab_bill_id === null || $lab_bill_id === '') {
			redirect(base_url() . 'app/dashboard');
			return;
		}

		// Get the lab bill details
		$this->load->model('app/laboratory_model');
		$lab_bill = $this->db->get_where('iop_lab_billing', array('lab_bill_id' => (int)$lab_bill_id, 'InActive' => 0))->row();
		
		if (!$lab_bill) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Lab bill not found.</div>');
			redirect(base_url() . 'app/dashboard');
			return;
		}

		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['medicine_cat'] = $this->billing_model->medicine_cat();
		$this->data['reason_dicount'] = $this->general_model->reason_dicount();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();
		$this->data['receipt_no2'] = $this->billing_model->receipt_no();

		// Get patient info
		$patient_no = isset($lab_bill->patient_no) ? $lab_bill->patient_no : '';
		$patient_info = $this->patient_model->getPatientInfo($patient_no);
		
		// Set direct flag so view displays patient info properly
		$this->data['direct'] = true;
		$this->data['patient_rows'] = $patient_info;
		
		// Also set OPD/IO info for display
		$iop_id = isset($lab_bill->iop_id) ? $lab_bill->iop_id : '';
		if ($iop_id !== '') {
			$this->load->model('app/opd_model');
			$iop_info = $this->opd_model->getOPDPatient($iop_id);
			$this->data['iop_info'] = $iop_info;
		}
		
		// Pass lab bill info to auto-load into billing form
		$this->data['auto_load_io_id'] = $iop_id;
		$this->data['auto_load_patient_no'] = $patient_no;
		$this->data['auto_load_lab_bill'] = $lab_bill;
		
		$this->load->view("app/pos/index", $this->data);
	}

	function test()
	{
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		//$this->data['itemList'] = $this->billing_model->itemList();

		$this->load->view("app/pos/test", $this->data);
	}

	public function showPatients($val = NULL)
	{
		//$cFrom = $this->uri->segment("4");
		//$cTo = $this->uri->segment("5");

		$this->data['showPatients'] = $this->billing_model->showPatients($val);
		$this->load->view("app/pos/showPatients", $this->data);
	}

	public function patient($id)
	{
		// Check if this is an AJAX request (for partial view) or direct browser access (full page)
		if ($this->input->is_ajax_request()) {
			// AJAX request - return partial view for patient search
			return $this->patientDetials($id);
		}
		
		// Direct browser access - redirect to full billing page
		$id = url_decode_id($id);
		
		// Try to find by IO_ID first
		$patientInfo = $this->billing_model->loadPatientInfo($id);
		
		if ($patientInfo) {
			// Found by IO_ID - redirect to pos_patient with patient_no
			redirect(base_url() . 'app/pos/pos_patient/' . $patientInfo->patient_no . '/' . $patientInfo->IO_ID);
			return;
		}
		
		// Try as patient_no
		$latestVisit = $this->_get_latest_visit_for_patient($id);
		if ($latestVisit) {
			redirect(base_url() . 'app/pos/pos_patient/' . $id . '/' . $latestVisit->IO_ID);
			return;
		}
		
		// No visit found - redirect to POS with flash message
		$this->session->set_flashdata('message', '<div class="alert alert-warning"><i class="fa fa-warning"></i> No active visit found for this patient. Please register OPD/IPD first.</div>');
		redirect(base_url() . 'app/pos');
	}

	public function patientDetials($id)
	{
		// Decode URL-safe ID (OP-000002 -> OP 000002)
		$id = sanitize_id_for_db((string)url_decode_id($id));
		
		// First try to load by IO_ID (visit ID)
		$patientInfo = $this->billing_model->loadPatientInfo($id);
		
		// If not found, check if this is a patient_no and find their latest visit
		if (!$patientInfo) {
			$latestVisit = $this->_get_latest_visit_for_patient($id);
			if ($latestVisit) {
				$patientInfo = $this->billing_model->loadPatientInfo($latestVisit->IO_ID);
			}
		}
		
		$this->data['patientDetials'] = $patientInfo;
		$this->load->view("app/pos/patientDetials", $this->data);
	}
	
	/**
	 * Get the latest visit (IO_ID) for a given patient number
	 * This allows POS to work with patient numbers, not just IO_IDs
	 */
	private function _get_latest_visit_for_patient($patient_no)
	{
		return $this->db->select('IO_ID, patient_no, patient_type, date_visit')
			->where('patient_no', $patient_no)
			->where('InActive', 0)
			->order_by('IO_ID', 'DESC')
			->limit(1)
			->get('patient_details_iop')
			->row();
	}

	public function getDoctorFee($invoiceno)
	{
		$invoiceno = sanitize_id_for_db((string)$invoiceno);
		$inv = $this->billing_model->headerInv2($invoiceno);
		if (!$inv) {
			echo json_encode(array());
			return;
		}

		$result = $this->db->query("SELECT * FROM doctors_fee WHERE invoice_no = ?", array($invoiceno));
		$aso_arr = $result->row_array(); // assoc. array w/o numeric indexes
		$user_id = isset($aso_arr['user_id']) ? $aso_arr['user_id'] : null;
		$feeType = isset($aso_arr['feeType']) ? $aso_arr['feeType'] : null;
		$value = isset($aso_arr['value']) ? $aso_arr['value'] : null;
		$totalFee = isset($aso_arr['totalFee']) ? $aso_arr['totalFee'] : null;
		$notes = isset($aso_arr['notes']) ? $aso_arr['notes'] : null;
		echo json_encode($aso_arr);
	}

	public function saveDoctorFee($invoiceno)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$invoiceno = sanitize_id_for_db((string)$invoiceno);
		$inv = $this->billing_model->headerInv2($invoiceno);
		if (!$inv) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$doctorId = sanitize_id_for_db((string)$this->input->post('doctor'));

		$isExist = $this->db->query("SELECT * FROM doctors_fee WHERE user_id = ? AND invoice_no = ?", array($doctorId, $invoiceno));

		if ($isExist->num_rows == 0) {
			$this->data = array(
				'user_id'			=>	$doctorId,
				'date'				=>	date("Y-m-d"),
				'invoice_no'		=>	$invoiceno,
				'completeDate'		=>	date("Y-m-d h:i:s"),
				'feeType'			=>	$this->input->post('cType'),
				'value'				=>	$this->input->post('valueFee'),
				'totalFee'			=>	$this->input->post('totalFee'),
				'notes'				=>	$this->input->post('notes')
			);
			$this->db->insert('doctors_fee', $this->data);
		} else {
			$this->db->where(array('user_id' => $doctorId, 'invoice_no' => $invoiceno));
			$this->db->update('doctors_fee', array(
				'date' => date("Y-m-d"),
				'completeDate' => date("Y-m-d h:i:s"),
				'feeType' => $this->input->post('cType'),
				'value' => $this->input->post('valueFee'),
				'totalFee' => $this->input->post('totalFee'),
				'notes' => $this->input->post('notes')
			));
		}

		// echo $isExist->num_rows;



	}

	public function saved()
	{
		// Decode all URL-safe IDs (OP-000002 -> OP 000002, SI-000055 -> SI 000055)
		$iop_no    = sanitize_id_for_db((string)url_decode_id($this->segment_decoded(4)));
		$patient_no = sanitize_id_for_db((string)url_decode_id($this->segment_decoded(5)));
		$invoiceno  = sanitize_id_for_db((string)url_decode_id($this->segment_decoded(6)));
		$inv = $this->billing_model->headerInv2($invoiceno);
		if (!$inv) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$invPat = isset($inv->patient_no) ? sanitize_id_for_db((string)$inv->patient_no) : '';
		$invIop = isset($inv->iop_id) ? sanitize_id_for_db((string)$inv->iop_id) : (isset($inv->IO_ID) ? sanitize_id_for_db((string)$inv->IO_ID) : '');
		if (($patient_no !== '' && $invPat !== '' && $patient_no !== $invPat) || ($iop_no !== '' && $invIop !== '' && $iop_no !== $invIop)) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['patientDetials'] = $this->billing_model->loadPatientInfo($iop_no);

		$this->data['headerInv'] = $this->billing_model->headerInv($iop_no, $invoiceno);
		if (method_exists($this->billing_model, 'backfill_invoice_line_meta')) {
			$this->billing_model->backfill_invoice_line_meta($invoiceno, (string)$this->session->userdata('user_id'));
		}
		$this->data['detailsInv'] = $this->billing_model->detailsInv2($invoiceno);

		$this->data['receipt_no2'] = $this->billing_model->receipt_no();

		$this->data['message'] = $this->session->flashdata('message');

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();

		$this->data['medicine_cat'] = $this->billing_model->medicine_cat();

		$this->data['reason_dicount'] = $this->general_model->reason_dicount();
		$this->data['doctorList'] = $this->general_model->doctorList();

		$payload = $this->billing_model->build_receipt_print_payload($invoiceno);
		if (isset($payload['headerInv'])) unset($payload['headerInv']);
		if (isset($payload['detailsInv'])) unset($payload['detailsInv']);
		$this->data = array_merge($this->data, $payload);
		if (isset($this->data['getOR']) && $this->data['getOR'] && isset($this->data['getOR']->receipt_no) && trim((string)$this->data['getOR']->receipt_no) !== '') {
			$this->data['hasOR'] = "1";
			$this->data['OR_number'] = (string)$this->data['getOR']->receipt_no;
		} else {
			$this->data['hasOR'] = "0";
			$this->data['OR_number'] = "-";
		}

		$settle = $this->billing_model->get_invoice_settlement($invoiceno);
		$this->data['pos_payment_status'] = $this->billing_model->compute_payment_status($invoiceno);
		$this->data['pos_paid_amount'] = $settle['paid'];
		$this->data['pos_balance_due'] = max(0, $settle['total'] - $settle['paid']);
		if (method_exists($this->billing_model, 'reconcile_pharmacy_queue_for_invoice')) {
			$this->billing_model->reconcile_pharmacy_queue_for_invoice($invoiceno, $this->data['pos_payment_status'], (string)$this->session->userdata('user_id'));
		}

		$this->load->view("app/pos/saved", $this->data);
	}

	public function save_payment()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$iop_no = sanitize_id_for_db((string)$this->input->post("opd_no"));
		$patient_no = sanitize_id_for_db((string)$this->input->post("patient_no"));
		$invoiceno = sanitize_id_for_db((string)$this->input->post("invoiceno"));
		$receipt_no = $this->input->post('receiptno');
		$invoice_total = (float)$this->input->post('totalAmount');
		$amount_paid = (float)$this->input->post('amountPaid');
		$payment_type = $this->input->post('paymentType');
		$cashier_id = $this->session->userdata('user_id');

		$inv = $this->billing_model->headerInv2($invoiceno);
		if (!$inv) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$invPat = isset($inv->patient_no) ? sanitize_id_for_db((string)$inv->patient_no) : '';
		$invIop = isset($inv->iop_id) ? sanitize_id_for_db((string)$inv->iop_id) : (isset($inv->IO_ID) ? sanitize_id_for_db((string)$inv->IO_ID) : '');
		if (($patient_no !== '' && $invPat !== '' && $patient_no !== $invPat) || ($iop_no !== '' && $invIop !== '' && $iop_no !== $invIop)) {
			redirect(base_url() . 'access_denied');
			return;
		}

		// Authoritative settlement (do not rely on posted totals for partial payments)
		$settle = $this->billing_model->get_invoice_settlement($invoiceno);
		$total_due = isset($settle['total']) ? (float)$settle['total'] : 0;
		$total_paid = isset($settle['paid']) ? (float)$settle['paid'] : 0;
		$remaining = round(max(0, $total_due - $total_paid), 2);
		$amount_paid = round($amount_paid, 2);
		$effective_paid = min($amount_paid, $remaining);
		$computed_change = max(0, $amount_paid - $remaining);
		if ($total_due > 0) {
			$invoice_total = $total_due;
		}

		// Ensure billing enhancements are in place (adds cashier_id column if needed)
		$this->billing_model->ensure_billing_enhancements();

		// Phase 4 / Step 4 — optional facade route. Default OFF.
		$this->load->model('app/Billing_facade_model', 'billing_facade_model');
		if ($this->billing_facade_model->is_receipt_route_enabled()) {
			$res = $this->billing_facade_model->record_payment(array(
				'invoice_no'      => $invoiceno,
				'amount'          => $effective_paid,
				'payment_method'  => $payment_type,
				'receipt_no'      => $receipt_no,
				'cashier_id'      => $cashier_id,
				'notes'           => 'POS Payment',
				'change'          => $computed_change,
				'discount'        => $this->input->post('discount'),
				'subtotal'        => $this->input->post('nGross'),
				'total_purchased' => $this->input->post('totalItem'),
				'source'          => 'POS',
			));
			if (empty($res['ok'])) {
				$err = htmlspecialchars((string)$res['error'], ENT_QUOTES, 'UTF-8');
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Payment failed: " . $err . "</div>");
				redirect(base_url() . 'app/pos/saved/' . url_safe_id($iop_no) . '/' . $patient_no . '/' . url_safe_id($invoiceno), $this->data);
				return;
			}
			// Use the receipt_no actually persisted (caller-supplied value preserved by facade).
			$receipt_no = $res['receipt_no'];
			// Maintain receipt-series counter that POS form drives.
			$this->db->query("UPDATE system_option SET cValue = ? WHERE cCode = 'receipt_no'", array($this->input->post('receipt_no2')));
		} else {
			// Insert into iop_receipt (legacy table)
			$receiptData = array(
				'receipt_no'		=>	$receipt_no,
				'invoice_no'		=>	$invoiceno,
				'dDate'				=>	date("Y-m-d H:i:s"),
				'iop_id'			=>	$iop_no,
				'patient_no'		=>	$patient_no,
				'payment_type'		=>	$payment_type,
				'total_amount'		=>	$invoice_total,
				'change'			=>	$computed_change,
				'amountPaid'		=>	$effective_paid,
				'total_purchased'	=>	$this->input->post('totalItem'),
				'discount'			=>	$this->input->post('discount'),
				'subtotal'			=>	$this->input->post('nGross'),
				'creditCardNo'		=>	'',
				'creditCardHolder'	=>	'',
				'insurance_company'	=>	'',
				'remarks'			=>	'',
				'InActive'			=>	0
			);
			// Add cashier_id only if column exists
			if ($this->billing_model->column_exists('iop_receipt', 'cashier_id')) {
				$receiptData['cashier_id'] = $cashier_id;
			}
			$this->db->insert('iop_receipt', $receiptData);

			// Best-effort sync: reflect receipt payment into billing_transactions SSOT
			if (method_exists($this->billing_model, 'sync_receipt_to_unified')) {
				$this->billing_model->sync_receipt_to_unified($receipt_no, $cashier_id);
			}

			// Also log to cashier_payment_log for audit trail
			if ($this->db->table_exists('cashier_payment_log')) {
				$this->db->insert('cashier_payment_log', array(
					'receipt_no' => $receipt_no,
					'invoice_no' => $invoiceno,
					'patient_no' => $patient_no,
					'amount' => $effective_paid,
					'payment_method' => strtoupper($payment_type),
					'cashier_id' => $cashier_id,
					'payment_date' => date('Y-m-d H:i:s'),
					'notes' => 'POS Payment'
				));
			}

			// Update invoice with receipt number
			$this->db->query("UPDATE iop_billing SET receipt_no = ? WHERE invoice_no = ?",
				array($receipt_no, $invoiceno));
			$this->db->query("UPDATE system_option SET cValue = ? WHERE cCode = 'receipt_no'", array($this->input->post('receipt_no2')));

			// Record to financial ledger via unified billing model
			if (method_exists($this->unified_billing_model, 'record_payment_to_ledger')) {
				$this->unified_billing_model->record_payment_to_ledger(
					$receipt_no, $invoiceno, $patient_no, $effective_paid, strtoupper($payment_type), $cashier_id
				);
			}
		}

		$this->billing_model->apply_post_payment_side_effects($invoiceno, $receipt_no, $cashier_id, $iop_no, $patient_no);

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Payment saved successfully!</div>");
		redirect(base_url() . 'app/pos/saved/' . url_safe_id($iop_no) . '/' . $patient_no . '/' . url_safe_id($invoiceno), $this->data);
	}

	public function getPatientMedication($patientNo, $iopNo)
	{
		$patientNo = sanitize_id_for_db((string)$patientNo);
		$iopNo = sanitize_id_for_db((string)$iopNo);
		$visit = $this->db->select('IO_ID')
			->where(array('IO_ID' => (string)$iopNo, 'patient_no' => (string)$patientNo, 'InActive' => 0))
			->limit(1)
			->get('patient_details_iop')
			->row();
		if (!$visit) {
			$this->data['patientMedication'] = array();
			$this->load->view("app/pos/getpatientMedication", $this->data);
			return;
		}
		$this->data['patientMedication']  = $this->billing_model->patientMedication($patientNo, $iopNo);
		$this->load->view("app/pos/getpatientMedication", $this->data);
	}

	public function patientMedicationJson($patientNo = null, $iopNo = null)
	{
		$patientNo = sanitize_id_for_db((string)$patientNo);
		$iopNo = sanitize_id_for_db((string)$iopNo);
		if ($patientNo === '' || $iopNo === '') {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array('success' => false, 'message' => 'Missing patient or visit ID', 'items' => array(), 'stats' => array())));
			return;
		}
		$visit = $this->db->select('IO_ID')
			->where(array('IO_ID' => (string)$iopNo, 'patient_no' => (string)$patientNo, 'InActive' => 0))
			->limit(1)
			->get('patient_details_iop')
			->row();
		if (!$visit) {
			$this->output
				->set_content_type('application/json')
				->set_output(json_encode(array('success' => false, 'message' => 'Invalid patient/visit context', 'items' => array(), 'stats' => array())));
			return;
		}
		$items = $this->billing_model->patientMedication($patientNo, $iopNo);
		$invoice_no = null;
		if (!is_array($items) || empty($items)) {
			if (method_exists($this->billing_model, 'get_latest_invoice_no_for_visit')) {
				$invoice_no = $this->billing_model->get_latest_invoice_no_for_visit($patientNo, $iopNo);
			}
		}
		if (!is_array($items)) {
			$items = array();
		}

		$stats = array(
			'locked' => 0,
			'unverified_meds' => 0,
			'unavailable_meds' => 0,
		);
		if ($this->db->table_exists('iop_billable_item_lock')) {
			$this->db->where(array('iop_id' => (string)$iopNo, 'patient_no' => (string)$patientNo, 'InActive' => 0));
			$stats['locked'] = (int)$this->db->count_all_results('iop_billable_item_lock');
		}
		if ($this->billing_model->column_exists('iop_medication', 'prescription_status')) {
			$this->db->where(array('iop_id' => (string)$iopNo, 'InActive' => 0));
			$this->db->where('prescription_status !=', 'VERIFIED');
			$stats['unverified_meds'] = (int)$this->db->count_all_results('iop_medication');
		}
		if ($this->billing_model->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where(array('iop_id' => (string)$iopNo, 'InActive' => 0));
			$this->db->where('dispensing_status', 'UNAVAILABLE');
			$stats['unavailable_meds'] = (int)$this->db->count_all_results('iop_medication');
		}

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array('success' => true, 'items' => $items, 'invoice_no' => $invoice_no, 'stats' => $stats)));
	}

	/**
	 * Receipt/Payment page for an invoice - Shows payment form
	 * GET: invoice_no
	 */
	public function receipt($invoice_no = null)
	{
		if (!$invoice_no) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'>No invoice specified.</div>");
			redirect(base_url() . 'app/unified_billing');
			return;
		}
		
		// Load invoice details
		$this->data['invoice'] = $this->billing_model->headerInv2($invoice_no);
		
		if (!$this->data['invoice']) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'>Invoice not found: " . htmlspecialchars($invoice_no) . "</div>");
			redirect(base_url() . 'app/unified_billing');
			return;
		}
		
		// Load patient info
		$patient_no = $this->data['invoice']->patient_no;
		$iop_no = $this->data['invoice']->iop_id;

		// Redirect to saved invoice view (pos_patient shows only unbilled/unlocked items)
		redirect(base_url() . 'app/pos/saved/' . url_safe_id($iop_no) . '/' . $patient_no . '/' . url_safe_id($invoice_no));
	}

	/**
	 * Accept partial or full payment via the billing model's record_payment().
	 * POST: invoice_no, amount_paid, payment_method, notes
	 */
	public function accept_payment()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$invoice_no = (string)$this->input->post('invoice_no');
		$invoice_no = sanitize_id_for_db($invoice_no);
		$amount = (float)$this->input->post('amount_paid');
		$method = (string)$this->input->post('payment_method');
		$notes = (string)$this->input->post('notes');
		$cashier = (string)$this->session->userdata('user_id');

		$inv = $this->billing_model->headerInv2($invoice_no);
		if (!$inv) {
			redirect(base_url() . 'access_denied');
			return;
		}

		if ($invoice_no === '' || $amount <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invalid invoice or amount.</div>");
			redirect(base_url() . 'app/billing_history');
			return;
		}

		if ($method === '') { $method = 'cash'; }

		$result = $this->billing_model->record_payment(
			$invoice_no, $amount, $method, null, $cashier, $notes
		);

		if ($result['ok']) {
			$badge = $result['status'] === 'PAID' ? 'success' : 'info';
			$msg = htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8');
			if (isset($result['receipt_no'])) {
				$msg .= ' Receipt: ' . htmlspecialchars($result['receipt_no'], ENT_QUOTES, 'UTF-8');
			}
			if (isset($result['balance']) && $result['balance'] > 0) {
				$msg .= ' Balance: ' . number_format($result['balance'], 2);
			}
			$this->session->set_flashdata('message', "<div class='alert alert-{$badge} alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$msg}</div>");
		} else {
			$msg = htmlspecialchars($result['message'], ENT_QUOTES, 'UTF-8');
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$msg}</div>");
		}

		// Redirect back to billing history view for this invoice
		redirect(base_url() . 'app/billing_history/view/' . $invoice_no);
	}
}
