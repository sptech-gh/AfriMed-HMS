<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Nhis_claims extends General {

	public function __construct(){
		parent::__construct();
		$this->load->model("app/billing_model");
		$this->load->model("app/patient_model");
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		// Allow admin, cashier, and billing roles to access NHIS Claims
		require_role(array('admin', 'cashier', 'billing'));
		if (!$this->session->userdata('_schema_nhis_claims_ok')) {
			$this->billing_model->ensure_nhis_day5_schema();
			$this->session->set_userdata('_schema_nhis_claims_ok', 1);
		}
	}

	/**
	 * Get the correct Claim-It API adapter based on current mode.
	 * MOCK → ClaimItMockApi  |  LIVE → ClaimItLocalApi
	 * @return object
	 */
	private function _get_claimit_api(){
		if (!isset($this->claimit)) {
			$this->load->model('app/nhis_claimit_model', 'claimit');
		}
		$mode = $this->claimit->get_api_mode();
		if ($mode === 'LIVE') {
			if (!isset($this->claimitlocalapi)) {
				$this->load->library('ClaimItLocalApi');
			}
			return $this->claimitlocalapi;
		}
		if (!isset($this->claimitmockapi)) {
			$this->load->library('ClaimItMockApi');
		}
		return $this->claimitmockapi;
	}

	/**
	 * Claims Dashboard — main page with stats, charts, filters, table.
	 */
	public function index(){
		$this->session->set_userdata(array(
			'tab'       => 'admin',
			'module'    => 'nhis_claims',
			'subtab'    => '',
			'submodule' => ''
		));

		// Collect filters from GET
		$filters = array(
			'status'      => $this->input->get('status'),
			'recon_status'=> $this->input->get('recon_status'),
			'date_from'   => $this->input->get('date_from'),
			'date_to'     => $this->input->get('date_to'),
			'patient_no'  => $this->input->get('patient_no'),
			'amount_min'  => $this->input->get('amount_min'),
			'amount_max'  => $this->input->get('amount_max'),
			'search'      => $this->input->get('search')
		);

		$this->data['claims']        = $this->billing_model->get_claims_list_v2($filters);
		$this->data['stats']         = $this->billing_model->get_nhis_dashboard_stats();
		$this->data['alerts']        = $this->billing_model->get_nhis_alert_counts();
		$this->data['timeline']      = $this->billing_model->get_claims_timeline(30);
		$this->data['distribution']  = $this->billing_model->get_claims_status_distribution();
		$this->data['api_config']    = $this->billing_model->get_nhis_api_config();
		$this->data['filters']       = $filters;
		$this->data['message']       = $this->session->flashdata('message');

		$this->load->view('app/nhis/claims_dashboard', $this->data);
	}

	/**
	 * View single claim detail.
	 */
	public function view($claim_id = 0){
		$claim_id = (int)$claim_id;
		$claim = $this->billing_model->get_claim($claim_id);
		if (!$claim) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Claim not found.</div>');
			redirect(base_url().'app/nhis_claims');
			return;
		}
		$this->data['claim']       = $claim;
		$this->data['claim_lines'] = $this->billing_model->get_claim_lines($claim_id);
		$this->data['patient']     = $this->patient_model->getPatientInfo($claim->patient_no);
		$this->data['message']     = $this->session->flashdata('message');

		$this->load->view('app/nhis/claim_view', $this->data);
	}

	/**
	 * AJAX: Check eligibility for a patient.
	 */
	public function check_eligibility(){
		$nhis_number = $this->input->post('nhis_number');
		$patient_no  = $this->input->post('patient_no');
		$result = $this->billing_model->nhis_api_check_eligibility($nhis_number, $patient_no);
		header('Content-Type: application/json');
		echo json_encode($result);
	}

	/**
	 * Submit a single claim to NHIS API.
	 */
	public function submit_claim($claim_id = 0){
		$claim_id = (int)$claim_id;
		$result = $this->billing_model->nhis_api_submit_claim($claim_id);

		// Auto-reconcile after submission
		if ($result['success'] && in_array($result['status'], array('APPROVED', 'REJECTED'))) {
			$this->billing_model->reconcile_claim($claim_id);
		}

		$alertClass = $result['success'] ? 'alert-info' : 'alert-danger';
		$this->session->set_flashdata('message',
			'<div class="alert '.$alertClass.' alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>'
			. '<i class="fa fa-medkit"></i> ' . htmlspecialchars($result['message']) . '</div>');

		$redirect = $this->input->get('redirect');
		if ($redirect === 'dashboard') {
			redirect(base_url().'app/nhis_claims');
		} else {
			redirect(base_url().'app/nhis_claims/view/'.$claim_id);
		}
	}

	/**
	 * Bulk submit all pending claims.
	 */
	public function submit_all_pending(){
		$this->billing_model->ensure_nhis_day5_schema();
		$this->db->where(array('InActive' => 0, 'status' => 'PENDING'));
		$pending = $this->db->get('nhis_claims')->result();

		$submitted = 0; $failed = 0;
		foreach ($pending as $c) {
			$result = $this->billing_model->nhis_api_submit_claim($c->claim_id);
			if ($result['success'] && in_array($result['status'], array('APPROVED', 'REJECTED'))) {
				$this->billing_model->reconcile_claim($c->claim_id);
			}
			if ($result['success']) { $submitted++; } else { $failed++; }
		}

		$this->session->set_flashdata('message',
			'<div class="alert alert-info alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>'
			. '<i class="fa fa-upload"></i> Bulk submission complete: '.$submitted.' submitted, '.$failed.' failed.</div>');
		redirect(base_url().'app/nhis_claims');
	}

	/**
	 * Run reconciliation on all unreconciled claims.
	 */
	public function reconcile_all(){
		$results = $this->billing_model->reconcile_all_pending();
		$count = count($results);

		$this->session->set_flashdata('message',
			'<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>'
			. '<i class="fa fa-check-circle"></i> Reconciled '.$count.' claims.</div>');
		redirect(base_url().'app/nhis_claims');
	}

	/**
	 * Reconcile single claim.
	 */
	public function reconcile($claim_id = 0){
		$claim_id = (int)$claim_id;
		$result = $this->billing_model->reconcile_claim($claim_id);

		$this->session->set_flashdata('message',
			'<div class="alert alert-info alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>'
			. '<i class="fa fa-refresh"></i> Claim reconciled: ' . htmlspecialchars($result) . '</div>');
		redirect(base_url().'app/nhis_claims/view/'.$claim_id);
	}

	/**
	 * NHIS API Settings page.
	 */
	public function settings(){
		if (!$this->current_user_is_admin()) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->data['api_config'] = $this->billing_model->get_nhis_api_config();
		$this->data['message']    = $this->session->flashdata('message');
		$this->load->view('app/nhis/settings', $this->data);
	}

	/**
	 * Save NHIS API settings.
	 */
	public function save_settings(){
		if (!$this->current_user_is_admin()) {
			redirect(base_url().'access_denied');
			return;
		}

		$keys = array('api_mode', 'api_base_url', 'api_key',
			'mock_approval_rate', 'mock_underpay_rate', 'mock_reject_rate', 'mock_delay_ms');
		foreach ($keys as $k) {
			$val = $this->input->post($k);
			if ($val !== null) {
				$this->billing_model->set_nhis_api_config($k, $val);
			}
		}

		$this->billing_model->log_nhis_audit('API_SETTINGS_UPDATED', 'nhis_api_config', null, null,
			json_encode($this->input->post()), $this->session->userdata('user_id'));

		$this->session->set_flashdata('message',
			'<div class="alert alert-success alert-dismissable"><button type="button" class="close" data-dismiss="alert">&times;</button>'
			. '<i class="fa fa-check"></i> NHIS API settings saved.</div>');
		redirect(base_url().'app/nhis_claims/settings');
	}

	/**
	 * AJAX endpoint: get alert counts for header badge.
	 */
	public function get_alerts_json(){
		$counts = $this->billing_model->get_nhis_alert_counts();
		header('Content-Type: application/json');
		echo json_encode($counts);
	}

	// =========================================================================
	// CLAIM-IT INTEGRATION METHODS
	// =========================================================================

	/**
	 * Claim-IT Dashboard - Main NHIS Claim-IT interface
	 */
	public function claimit(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$api = $this->_get_claimit_api();
		
		$this->data['summary'] = $this->claimit->get_dashboard_summary();
		$this->data['claims'] = $this->claimit->get_claims(['status' => $this->input->get('status')], 50);
		$this->data['api_mode'] = $this->claimit->get_api_mode();
		$this->data['message'] = $this->session->flashdata('message');
		
		$this->load->view('app/nhis/claimit_dashboard', $this->data);
	}

	/**
	 * AJAX: Verify NHIS eligibility via Claim-IT
	 */
	public function claimit_eligibility(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$api = $this->_get_claimit_api();
		
		$nhis_number = $this->input->post('nhis_number');
		$patient_no = $this->input->post('patient_no');
		
		$result = $api->check_eligibility($nhis_number);
		
		if ($result['success'] && $patient_no) {
			$this->claimit->register_membership([
				'patient_no' => $patient_no,
				'nhis_number' => $nhis_number,
				'member_name' => $result['data']['member_name'] ?? null,
				'status' => $result['data']['status'] ?? 'PENDING',
				'expiry_date' => $result['data']['expiry_date'] ?? null
			]);
			$this->claimit->update_membership_status(
				$this->claimit->get_membership_by_patient($patient_no)->id,
				$result['data']['status'],
				$result['data']
			);
		}
		
		header('Content-Type: application/json');
		echo json_encode($result);
	}

	/**
	 * Generate claim from invoice
	 */
	public function generate_claim($invoice_id = 0){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		
		$invoice = $this->billing_model->getInvoice($invoice_id);
		if (!$invoice) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Invoice not found.</div>');
			redirect(base_url().'app/nhis_claims/claimit');
			return;
		}
		
		$membership = $this->claimit->get_membership_by_patient($invoice->patient_no);
		if (!$membership || $membership->status !== 'ACTIVE') {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Patient has no active NHIS membership.</div>');
			redirect(base_url().'app/billing/invoice/'.$invoice_id);
			return;
		}
		
		$claim_id = $this->claimit->create_claim([
			'patient_no' => $invoice->patient_no,
			'invoice_id' => $invoice_id,
			'invoice_no' => $invoice->invoice_no ?? null
		]);
		
		$items = $this->billing_model->getInvoiceItems($invoice_id);
		foreach ($items as $item) {
			$tariff = $this->claimit->search_tariffs($item->bill_name, 1);
			$this->claimit->add_claim_item($claim_id, [
				'service_code' => !empty($tariff) ? $tariff[0]->service_code : 'MISC-001',
				'service_name' => $item->bill_name,
				'quantity' => $item->qty ?? 1,
				'tariff' => $item->amount ?? 0
			]);
		}
		
		$this->session->set_flashdata('message', '<div class="alert alert-success">Claim generated: ' . $this->claimit->get_claim($claim_id)->claim_number . '</div>');
		redirect(base_url().'app/nhis_claims/claimit_view/'.$claim_id);
	}

	/**
	 * View Claim-IT claim
	 */
	public function claimit_view($claim_id = 0){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		
		$claim = $this->claimit->get_claim($claim_id);
		if (!$claim) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Claim not found.</div>');
			redirect(base_url().'app/nhis_claims/claimit');
			return;
		}
		
		$this->data['claim'] = $claim;
		$this->data['items'] = $this->claimit->get_claim_items($claim_id);
		$this->data['diagnoses'] = $this->claimit->get_claim_diagnoses($claim_id);
		$this->data['validation'] = $this->claimit->validate_claim($claim_id);
		$this->data['message'] = $this->session->flashdata('message');
		
		$this->load->view('app/nhis/claimit_view', $this->data);
	}

	/**
	 * Add diagnosis to claim
	 */
	public function add_diagnosis(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		
		$claim_id = $this->input->post('claim_id');
		$icd10_code = $this->input->post('icd10_code');
		$type = $this->input->post('diagnosis_type') ?: 'PRIMARY';
		
		$this->claimit->add_claim_diagnosis($claim_id, $icd10_code, $type);
		
		$this->session->set_flashdata('message', '<div class="alert alert-success">Diagnosis added.</div>');
		redirect(base_url().'app/nhis_claims/claimit_view/'.$claim_id);
	}

	/**
	 * Submit claim to Claim-IT
	 */
	public function claimit_submit($claim_id = 0){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$api = $this->_get_claimit_api();
		
		$validation = $this->claimit->validate_claim($claim_id);
		if (!$validation['valid']) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Validation failed: ' . implode(', ', $validation['errors']) . '</div>');
			redirect(base_url().'app/nhis_claims/claimit_view/'.$claim_id);
			return;
		}
		
		$claim = $this->claimit->get_claim($claim_id);
		$items = $this->claimit->get_claim_items($claim_id);
		$diagnoses = $this->claimit->get_claim_diagnoses($claim_id);
		
		// Fetch full patient record for XSD-required demographics
		$patient = $this->patient_model->getPatientInfo($claim->patient_no);
		
		$claim_data = [
			'claim_number' => $claim->claim_number,
			'patient_no' => $claim->patient_no,
			'nhis_number' => $claim->nhis_number,
			'nhis_member_id' => isset($claim->nhis_number) ? $claim->nhis_number : '',
			'total_amount' => $claim->total_amount,
			'encounter_type' => isset($claim->visit_type) ? $claim->visit_type : 'OPD',
			'attending_doctor' => isset($claim->doctor_name) ? $claim->doctor_name : '',
			'visit_date' => isset($claim->created_at) ? date('Y-m-d', strtotime($claim->created_at)) : date('Y-m-d'),
			// XSD-required patient demographics
			'surname' => $patient ? $patient->lastname : '',
			'other_names' => $patient ? trim(($patient->firstname ?: '') . ' ' . ($patient->middlename ?: '')) : '',
			'date_of_birth' => $patient && $patient->birthday ? date('Y-m-d', strtotime($patient->birthday)) : '',
			'gender' => $patient ? $patient->gender : '',
			'is_dependant' => '0',
			'type_of_attendance' => 'New Attendance',
			'service_outcome' => 'Discharged',
			'items' => $items,
			'diagnoses' => $diagnoses
		];
		
		$result = $api->submit_claim($claim_data);
		
		if ($result['success']) {
			$this->claimit->update_claim_status($claim_id, 'SUBMITTED', [
				'claimit_reference' => $result['data']['claimit_reference']
			]);
			$this->session->set_flashdata('message', '<div class="alert alert-success">Claim submitted. Reference: ' . $result['data']['claimit_reference'] . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Submission failed: ' . (isset($result['message']) ? $result['message'] : 'Unknown error') . '</div>');
		}
		
		redirect(base_url().'app/nhis_claims/claimit_view/'.$claim_id);
	}

	/**
	 * AJAX: Search ICD-10 codes
	 */
	public function search_icd10(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$term = $this->input->get('term');
		$results = $this->claimit->search_icd10($term, 20);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	/**
	 * AJAX: Search tariffs
	 */
	public function search_tariffs(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$term = $this->input->get('term');
		$results = $this->claimit->search_tariffs($term, 20);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	/**
	 * ICD-10 Mapping page
	 */
	public function icd10_mapping(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$this->data['codes'] = $this->claimit->search_icd10('', 500);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/nhis/icd10_mapping', $this->data);
	}

	/**
	 * Tariff Mapping page
	 */
	public function tariff_mapping(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$this->data['tariffs'] = $this->claimit->get_tariffs_by_category();
		$this->data['categories'] = $this->claimit->get_tariff_categories();
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/nhis/tariff_mapping', $this->data);
	}

	public function import_icd10_csv()
	{
		$this->load->model('app/nhis_claimit_model', 'claimit');
		if (empty($_FILES['csv_file']['name']) || empty($_FILES['csv_file']['tmp_name'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">No file selected.</div>');
			redirect(base_url().'app/nhis_claims/icd10_mapping');
			return;
		}

		$dir = APPPATH . 'cache/nhis_imports';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'icd10_' . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$_FILES['csv_file']['name']);
		if (!@move_uploaded_file($_FILES['csv_file']['tmp_name'], $dest)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Upload failed.</div>');
			redirect(base_url().'app/nhis_claims/icd10_mapping');
			return;
		}

		$meta = array(
			'version_label' => $this->input->post('version_label'),
			'effective_date' => $this->input->post('effective_date'),
			'source_name' => $this->input->post('source_name') ?: $_FILES['csv_file']['name'],
			'notes' => $this->input->post('notes')
		);

		$result = $this->claimit->import_icd10_csv($dest, $meta);
		if (!empty($result['success'])) {
			$msg = 'ICD-10 import complete. Inserted: ' . (int)$result['inserted'] . ', Updated: ' . (int)$result['updated'] . ', Skipped: ' . (int)$result['skipped'];
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>');
		} else {
			$err = isset($result['error']) ? (string)$result['error'] : 'Import failed';
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>');
		}
		redirect(base_url().'app/nhis_claims/icd10_mapping');
	}

	public function import_service_tariffs_csv()
	{
		$this->load->model('app/nhis_claimit_model', 'claimit');
		if (empty($_FILES['csv_file']['name']) || empty($_FILES['csv_file']['tmp_name'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">No file selected.</div>');
			redirect(base_url().'app/nhis_claims/tariff_mapping');
			return;
		}

		$dir = APPPATH . 'cache/nhis_imports';
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		$dest = rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . 'tariffs_' . date('Ymd_His') . '_' . preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$_FILES['csv_file']['name']);
		if (!@move_uploaded_file($_FILES['csv_file']['tmp_name'], $dest)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Upload failed.</div>');
			redirect(base_url().'app/nhis_claims/tariff_mapping');
			return;
		}

		$meta = array(
			'version_label' => $this->input->post('version_label'),
			'effective_date' => $this->input->post('effective_date'),
			'source_name' => $this->input->post('source_name') ?: $_FILES['csv_file']['name'],
			'notes' => $this->input->post('notes')
		);

		$result = $this->claimit->import_service_tariffs_csv($dest, $meta);
		if (!empty($result['success'])) {
			$msg = 'Tariff import complete. Inserted: ' . (int)$result['inserted'] . ', Updated: ' . (int)$result['updated'] . ', Skipped: ' . (int)$result['skipped'];
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($msg) . '</div>');
		} else {
			$err = isset($result['error']) ? (string)$result['error'] : 'Import failed';
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($err) . '</div>');
		}
		redirect(base_url().'app/nhis_claims/tariff_mapping');
	}

	/**
	 * Toggle API Mode (Mock/Live)
	 */
	public function toggle_api_mode(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$mode = $this->input->post('mode');
		if (in_array($mode, ['MOCK', 'LIVE'])) {
			$this->claimit->set_api_mode($mode);
			$this->session->set_flashdata('message', '<div class="alert alert-success">API mode set to ' . $mode . '</div>');
		}
		redirect(base_url().'app/nhis_claims/claimit');
	}

	/**
	 * Submission Queue
	 */
	public function submission_queue(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$this->data['ready_claims'] = $this->claimit->get_claims(['status' => 'READY'], 100);
		$this->data['submitted_claims'] = $this->claimit->get_claims(['status' => 'SUBMITTED'], 50);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/nhis/submission_queue', $this->data);
	}

	/**
	 * API Logs
	 */
	public function api_logs(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$filters = [
			'endpoint' => $this->input->get('endpoint'),
			'status' => $this->input->get('status'),
			'date_from' => $this->input->get('date_from'),
			'date_to' => $this->input->get('date_to')
		];
		$this->data['logs'] = $this->claimit->get_api_logs($filters, 100);
		$this->data['filters'] = $filters;
		$this->load->view('app/nhis/api_logs', $this->data);
	}

	/**
	 * Batch submit multiple claims
	 */
	public function batch_submit(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$api = $this->_get_claimit_api();
		
		$claim_ids = $this->input->post('claim_ids');
		if (empty($claim_ids) || !is_array($claim_ids)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">No claims selected.</div>');
			redirect(base_url().'app/nhis_claims/submission_queue');
			return;
		}
		
		$batch = $this->claimit->create_submission_batch($claim_ids);
		$results = $this->claimit->batch_submit_claims($claim_ids);
		
		$msg = "Batch {$batch['batch_reference']}: {$results['success']} submitted, {$results['failed']} failed.";
		$alertClass = $results['failed'] > 0 ? 'warning' : 'success';
		
		$this->session->set_flashdata('message', '<div class="alert alert-'.$alertClass.'">'.$msg.'</div>');
		redirect(base_url().'app/nhis_claims/submission_queue');
	}

	/**
	 * Readiness Checklist
	 */
	public function readiness(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$this->data['checklist'] = $this->claimit->get_readiness_checklist();
		$this->data['summary'] = $this->claimit->get_dashboard_summary();
		$this->data['api_mode'] = $this->claimit->get_api_mode();
		$this->load->view('app/nhis/readiness', $this->data);
	}

	/**
	 * AJAX: Validate claim
	 */
	public function validate_claim_ajax(){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$claim_id = $this->input->post('claim_id');
		$result = $this->claimit->validate_claim($claim_id);
		header('Content-Type: application/json');
		echo json_encode($result);
	}

	/**
	 * Mark claim as ready
	 */
	public function mark_ready($claim_id = 0){
		$this->load->model('app/nhis_claimit_model', 'claimit');
		$validation = $this->claimit->validate_claim($claim_id);
		
		if ($validation['valid']) {
			$this->claimit->update_claim_status($claim_id, 'READY');
			$this->session->set_flashdata('message', '<div class="alert alert-success">Claim marked as ready for submission.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cannot mark ready: ' . implode(', ', $validation['errors']) . '</div>');
		}
		
		redirect(base_url().'app/nhis_claims/claimit_view/'.$claim_id);
	}
}
