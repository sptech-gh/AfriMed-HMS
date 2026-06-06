<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Pharmacy extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/pharmacy_model');
		$this->load->model('app/nurse_enhancement_model');
		$this->load->model('app/billing_model');
		$this->load->model('app/governance_model');
		$this->load->model('app/unified_billing_model');
		$this->load->model('app/pharmacy_architecture_model');
		$this->load->model('app/nhis_pharmacy_model');
		$this->load->model('app/pharmacy_stock_model');
		$this->load->model('app/pharmacy_dispense_model');
		$this->load->model('app/pharmacy_billing_model', 'pharmacy_bill_model');
		$this->load->model('app/pharmacy_workflow_model');
		$this->load->model('app/pharmacy_performance_model');
		
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
			return;
		}
		General::variable();
		
		// Read-only catalog endpoints needed by prescribers (doctors) and other roles
		$read_only_methods = array('drug_search_json', 'get_medication_masters_json');
		$current_method = $this->router->fetch_method();
		$is_read_only = in_array($current_method, $read_only_methods);

		// Doctors are excluded from DISPENSING but must access read-only catalog
		// endpoints for prescribing via the unified medication modal.
		if ($this->current_user_is_doctor() && !$is_read_only) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning"><i class="fa fa-info-circle"></i> The Pharmacy module is not available for Doctors.</div>');
			redirect(base_url() . 'app/dashboard');
			return;
		}
		// Non-admin, non-doctor, no pharmacy flag → block (but allow read-only)
		$hasFullPharmacy = ((isset($this->data['hasAccesstoPharmacy']) && $this->data['hasAccesstoPharmacy']) || has_privilege('pharmacy_access'));
		$hasDispensePharmacy = ($hasFullPharmacy || ($this->current_user_is_nurse() && has_privilege('pharmacy_dispense_access')));
		if (!$this->current_user_is_admin() && !$this->current_user_is_doctor() && !$hasDispensePharmacy) {
			if (!$is_read_only) {
				$this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-ban"></i> Access denied. You do not have permission to view the Pharmacy module.</div>');
				redirect(base_url() . 'app/dashboard');
				return;
			}
		}

		// Schema migrations — run once per session (not per request)
		if (!$this->session->userdata('_schema_pharmacy_ok')) {
			$this->pharmacy_model->install_pharmacy_workflow_tables();
			$this->pharmacy_model->ensure_pharmacy_enhancements();
			$this->pharmacy_model->ensure_pharmacy_v2_schema();
			$this->pharmacy_model->ensure_pharmacy_ghs_schema();
			$this->pharmacy_model->ensure_flexible_workflow_schema();
			$this->pharmacy_model->ensure_pharmacy_finalization_schema();
			$this->governance_model->ensure_governance_schema();
			$this->unified_billing_model->ensure_unified_billing_schema();
			$this->pharmacy_architecture_model->ensure_multistore_schema();
			$this->pharmacy_architecture_model->ensure_controlled_drugs_schema();
			$this->pharmacy_architecture_model->ensure_generic_mapping_schema();
			$this->pharmacy_architecture_model->ensure_prescription_locking_schema();
			$this->pharmacy_architecture_model->ensure_batch_recall_schema();
			$this->pharmacy_architecture_model->ensure_reconciliation_schema();
			$this->pharmacy_performance_model->ensure_performance_indexes();
			$this->session->set_userdata('_schema_pharmacy_ok', 1);
		}
	}

	private function _user_has_full_pharmacy_access()
	{
		return (
			$this->current_user_is_admin()
			|| $this->current_user_is_pharmacist()
			|| $this->current_user_is_super_admin()
			|| has_privilege('pharmacy_access')
		);
	}

	private function _user_has_pharmacy_dispense_access()
	{
		if ($this->_user_has_full_pharmacy_access()) {
			return true;
		}
		return ($this->current_user_is_nurse() && has_privilege('pharmacy_dispense_access'));
	}

	private function _require_full_pharmacy_access($json = false)
	{
		if ($this->_user_has_full_pharmacy_access()) {
			return true;
		}
		if ($json) {
			header('Content-Type: application/json');
			echo json_encode(array('ok' => false, 'message' => 'Access denied'));
			return false;
		}
		redirect(base_url() . 'access_denied');
		return false;
	}

	private function _require_pharmacy_dispense_access($json = false)
	{
		if ($this->_user_has_pharmacy_dispense_access()) {
			return true;
		}
		if ($json) {
			header('Content-Type: application/json');
			echo json_encode(array('ok' => false, 'message' => 'Access denied'));
			return false;
		}
		redirect(base_url() . 'access_denied');
		return false;
	}

	public function index()
	{
		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_worklist',
			'subtab' => '',
			'submodule' => ''
		));

		$filters = array(
			'search' => (string)$this->input->get_post('search'),
			'status' => (string)$this->input->get_post('status'),
			'date_from' => (string)$this->input->get_post('date_from'),
			'date_to' => (string)$this->input->get_post('date_to'),
			'limit' => (int)$this->input->get_post('limit')
		);

		$this->data['filters'] = $filters;
		$this->data['message'] = $this->session->flashdata('message');

		try {
			$this->pharmacy_model->reconcile_pharmacy_billing_queue_from_paid_invoices(50, $this->session->userdata('user_id'));
		} catch (Throwable $e) {
			
		}
		$this->data['patient_worklist'] = $this->pharmacy_model->get_patient_worklist($filters);
		
		// Get comprehensive summary with both item and patient counts
		$comprehensive = $this->pharmacy_model->get_pharmacy_summary_counts();
		
		// Legacy summary format (item counts) for backward compatibility
		$this->data['summary'] = array(
			'pending'          => $comprehensive['items']['pending'],
			'dispensed_today'  => $comprehensive['items']['dispensed_today'],
			'low_stock'        => $this->pharmacy_model->count_low_stock(),
			'awaiting_payment' => $comprehensive['items']['awaiting_payment'],
			'ready_to_dispense'=> $comprehensive['items']['ready'],
			'deferred'         => $comprehensive['items']['deferred'],
			'external'         => $comprehensive['items']['external'],
			'partial'          => $comprehensive['items']['partial'],
		);
		
		// Patient-level counts (matches what users see in worklist)
		$patientCounts = array(
			'total' => 0,
			'awaiting_payment' => 0,
			'ready' => 0,
			'in_progress' => 0,
			'external' => 0,
			'completed' => 0,
		);
		if (isset($this->data['patient_worklist']) && is_array($this->data['patient_worklist'])) {
			$patientCounts['total'] = count($this->data['patient_worklist']);
			foreach ($this->data['patient_worklist'] as $_pt) {
				$st = isset($_pt->overall_status) ? strtoupper(trim((string)$_pt->overall_status)) : '';
				if ($st === 'READY') {
					$patientCounts['ready']++;
				} elseif ($st === 'IN_PROGRESS' || $st === 'PARTIAL_PAID') {
					$patientCounts['in_progress']++;
				} elseif ($st === 'AWAITING_PAYMENT') {
					$patientCounts['awaiting_payment']++;
				} elseif ($st === 'EXTERNAL') {
					$patientCounts['external']++;
				} elseif ($st === 'COMPLETED') {
					$patientCounts['completed']++;
				}
			}
		}
		$this->data['patient_counts'] = $patientCounts;
		$this->load->view('app/pharmacy/pharmacy_dashboard', $this->data);
	}

	/**
	 * Named alias for index() — allows /app/pharmacy/queue route (Group 11 QA fix)
	 */
	public function queue()
	{
		$this->index();
	}

	public function walkin_queue()
	{
		if (!$this->_require_pharmacy_dispense_access(false)) {
			return;
		}
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'walkin_queue', 'subtab' => '', 'submodule' => ''));
		$this->load->model('app/walkin_order_model');
		$this->walkin_order_model->ensure_walkin_schema();
		$this->data['pending'] = $this->walkin_order_model->get_department_queue('PHARMACY', null, 200);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/pharmacy/walkin_queue', $this->data);
	}

	/**
	 * Patient prescription detail page - dispense medications for a specific patient
	 */
	public function patient($iop_id = '')
	{
		$iop_id = (string)$iop_id;
		if ($iop_id === '') {
			redirect(base_url() . 'app/pharmacy');
			return;
		}

		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_patient',
			'subtab' => '',
			'submodule' => ''
		));

		// Ensure billing queue entries exist and sync any discrepancies (SINGLE SOURCE OF TRUTH)
		$user_id = $this->session->userdata('user_id');
		$this->pharmacy_model->ensure_billing_queue_for_visit($iop_id, $user_id);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['patient_info'] = $this->pharmacy_model->get_patient_pharmacy_info($iop_id);
		$this->data['prescriptions'] = $this->pharmacy_model->get_patient_prescriptions($iop_id);
		$this->load->model('app/patient_history_model');
		$this->data['visit_details'] = $this->patient_history_model->get_visit_details($iop_id);
		$this->load->model('app/billing_model');
		$wf = $this->billing_model->get_clearance_workflow($iop_id);
		$this->data['medication_cleared'] = ($wf && isset($wf->medication_cleared_at) && trim((string)$wf->medication_cleared_at) !== '' && (string)$wf->medication_cleared_at !== '0000-00-00 00:00:00');
		$this->data['iop_id'] = $iop_id;

		if (!$this->data['patient_info']) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning">Patient visit not found.</div>');
			redirect(base_url() . 'app/pharmacy');
			return;
		}

		$this->load->view('app/pharmacy/patient_detail', $this->data);
	}

	/**
	 * Bulk dispense all eligible medications for a patient
	 */
	public function bulk_dispense()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$iop_id = (string)$this->input->post('iop_id');
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_model->bulk_dispense_patient($iop_id, $user_id);

		if ($result['success'] > 0) {
			$msg = '<div class="alert alert-success"><i class="fa fa-check"></i> Successfully dispensed ' . $result['success'] . ' medication(s).</div>';
		} else {
			$msg = '<div class="alert alert-warning"><i class="fa fa-warning"></i> No medications were dispensed.</div>';
		}

		if ($result['failed'] > 0) {
			$msg .= '<div class="alert alert-danger"><i class="fa fa-warning"></i> Failed to dispense ' . $result['failed'] . ' medication(s): ' . implode(', ', $result['errors']) . '</div>';
		}

		$this->session->set_flashdata('message', $msg);
		redirect(base_url() . 'app/pharmacy/patient/' . rawurlencode($iop_id));
	}

	public function walkin_fulfill_item()
	{
		// Governed execution surface: JSON-only
		header('Content-Type: application/json');

		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid method'));
			return;
		}

		if (!$this->_user_has_pharmacy_dispense_access()) {
			echo json_encode(array('ok' => false, 'error' => 'Access denied'));
			return;
		}

		$item_ref = trim((string)$this->input->post('item_ref'));
		$qty = (float)$this->input->post('qty');
		$idempotency_key = trim((string)$this->input->post('idempotency_key'));
		$notes = $this->input->post('notes');
		$notes = ($notes === null) ? null : (string)$notes;

		if ($item_ref === '' || strpos($item_ref, 'walkin_order_item_id:') !== 0) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid item_ref (expected walkin_order_item_id:<id>)'));
			return;
		}
		$internal_id = (int)substr($item_ref, strlen('walkin_order_item_id:'));
		if ($internal_id <= 0) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid item_ref'));
			return;
		}
		if ($qty <= 0) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid qty'));
			return;
		}

		$actor = (string)$this->session->userdata('user_id');
		if ($actor === '') {
			$actor = (string)$this->session->userdata('username');
		}
		if ($actor === '') {
			$actor = 'SYSTEM';
		}

		$meta = array(
			'ip' => isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '',
			'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '',
			'actor' => $actor,
		);
		$notes_payload = array('notes' => $notes, 'meta' => $meta);
		$notes_final = json_encode($notes_payload);

		if ($idempotency_key === '') {
			$idempotency_key = null;
		}

		$this->load->model('app/walkin_order_model');
		$this->walkin_order_model->ensure_walkin_schema();
		$res = $this->walkin_order_model->fulfill_walkin_order_item($internal_id, $qty, $actor, $idempotency_key, $notes_final);

		$outcome = 'error';
		if (is_array($res) && !empty($res['success'])) {
			if (!empty($res['replay'])) {
				$outcome = 'replay';
			} elseif (!empty($res['already_fulfilled'])) {
				$outcome = 'already_fulfilled';
			} else {
				$it = isset($res['item']) ? $res['item'] : null;
				$st = ($it && isset($it->fulfillment_status)) ? strtoupper(trim((string)$it->fulfillment_status)) : '';
				$outcome = ($st === 'FULFILLED') ? 'fulfilled' : 'partial';
			}
		} elseif (is_array($res) && isset($res['blocked_reason']) && $res['blocked_reason'] !== null) {
			$outcome = 'blocked_by_finance';
		} elseif (is_array($res) && isset($res['error']) && stripos((string)$res['error'], 'Insufficient stock') !== false) {
			$outcome = 'insufficient_stock';
		}

		echo json_encode(array(
			'ok' => (is_array($res) && !empty($res['success'])) ? true : false,
			'outcome' => $outcome,
			'item_ref' => $item_ref,
			'qty' => $qty,
			'idempotency_key' => $idempotency_key,
			'result' => $res,
		));
	}

	/**
	 * Patient medication clearance (from patient detail page)
	 */
	public function patient_clearance()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$iop_id = (string)$this->input->post('iop_id');
		$patient_no = (string)$this->input->post('patient_no');
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_model->patient_medication_clearance($iop_id, $patient_no, $user_id);

		if ($result['ok']) {
			if (isset($result['already_cleared']) && $result['already_cleared']) {
				$this->session->set_flashdata('message', '<div class="alert alert-info"><i class="fa fa-info-circle"></i> Medication clearance was already recorded.</div>');
			} else {
				$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> Medication clearance recorded successfully.</div>');
			}
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-warning"></i> ' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/patient/' . rawurlencode($iop_id));
	}

	public function log_action()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		if (!$this->_user_has_pharmacy_dispense_access()) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Access denied.</div>');
			redirect(base_url() . 'app/pharmacy');
			return;
		}

		$iop_med_id = (int)$this->input->post('iop_med_id');
		$iop_id = (string)$this->input->post('iop_id');
		$status = strtoupper(trim((string)$this->input->post('status')));
		$qty = trim((string)$this->input->post('qty'));
		$notes = (string)$this->input->post('notes');
		$return_url = (string)$this->input->post('return_url');

		$allowed = array('DISPENSED', 'PARTIAL');
		if ($iop_med_id <= 0 || $iop_id === '' || !in_array($status, $allowed, true)) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invalid request.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
			return;
		}

		if (($status === 'DISPENSED' || $status === 'PARTIAL') && $qty === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Quantity is required.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
			return;
		}

		$batch_no = trim((string)$this->input->post('batch_no'));
		$result = $this->pharmacy_model->dispense_medication(
			$iop_med_id, (float)$qty, $status, $notes,
			$this->session->userdata('user_id'), $batch_no
		);
		if (!$result['ok']) {
			$errMsg = implode(' ', $result['errors']);
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . htmlspecialchars($errMsg) . "</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
			return;
		}
		$this->pharmacy_model->log_pharmacy_audit($iop_med_id, $iop_id, '', strtoupper($status), 'PAID', strtoupper($status), $notes, $this->session->userdata('user_id'));
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication dispensed successfully. Stock updated.</div>");

		redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
	}

	// =========================================================================
	// PHASE 6B — AJAX Dispense Endpoint (JSON, no redirect)
	// POST app/pharmacy/dispense_ajax
	// Params: iop_med_id, iop_id, status (DISPENSED|PARTIAL), qty, notes, batch_no
	// =========================================================================
	public function dispense_ajax()
	{
		header('Content-Type: application/json');

		if (!$this->_require_pharmacy_dispense_access(true)) {
			return;
		}

		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'Method not allowed'));
			return;
		}

		$iop_med_id = (int)$this->input->post('iop_med_id');
		$iop_id     = (string)$this->input->post('iop_id');
		$status     = strtoupper(trim((string)$this->input->post('status')));
		$qty        = trim((string)$this->input->post('qty'));
		$notes      = (string)$this->input->post('notes');
		$batch_no   = trim((string)$this->input->post('batch_no'));

		$allowed = array('DISPENSED', 'PARTIAL');
		if ($iop_med_id <= 0 || $iop_id === '' || !in_array($status, $allowed, true)) {
			echo json_encode(array('ok' => false, 'message' => 'Invalid request parameters'));
			return;
		}
		if ($qty === '') {
			echo json_encode(array('ok' => false, 'message' => 'Quantity is required'));
			return;
		}

		$medRow = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$medRow) {
			echo json_encode(array('ok' => false, 'message' => 'Medication record not found'));
			return;
		}
		$medIop = '';
		if (isset($medRow->iop_id)) {
			$medIop = (string)$medRow->iop_id;
		} elseif (isset($medRow->IO_ID)) {
			$medIop = (string)$medRow->IO_ID;
		}
		$medIop = trim((string)$medIop);
		if ($medIop !== '' && $medIop !== $iop_id) {
			echo json_encode(array('ok' => false, 'message' => 'Invalid medication context'));
			return;
		}

		$result = $this->pharmacy_model->dispense_medication(
			$iop_med_id, (float)$qty, $status, $notes,
			$this->session->userdata('user_id'), $batch_no
		);

		if (!$result['ok']) {
			echo json_encode(array('ok' => false, 'message' => implode(' ', $result['errors'])));
			return;
		}

		/* Resolve patient_no, drug name, and FEFO batch expiry for GHS-compliant audit log */
		$_med_row = $medRow;
		$_patient_no = '';
		$_drug_name  = '';
		$_expiry     = null;
		if ($_med_row) {
			$_iop = $this->db->select('patient_no')->where(array('IO_ID' => $iop_id, 'InActive' => 0))->limit(1)->get('patient_details_iop')->row();
			if ($_iop) $_patient_no = (string)$_iop->patient_no;
			$_drug = $this->db->select('drug_name')->where('drug_id', (int)$_med_row->medicine_id)->limit(1)->get('medicine_drug_name')->row();
			if ($_drug) $_drug_name = (string)$_drug->drug_name;
			/* If batch_no was supplied by pharmacist use it; otherwise find FEFO batch expiry */
			if ($batch_no !== '') {
				$_bs = $this->db->select('expiry_date')->where(array('medication_id' => (int)$_med_row->medicine_id, 'batch_number' => $batch_no, 'InActive' => 0))->limit(1)->get('medication_stock')->row();
				if ($_bs) $_expiry = $_bs->expiry_date ?: null;
			} else {
				$_fefo = $this->pharmacy_model->get_batch_stock((int)$_med_row->medicine_id);
				if ($_fefo) { $_expiry = $_fefo[0]->expiry_date ?: null; $batch_no = (string)$_fefo[0]->batch_number; }
			}
		}

		$this->pharmacy_model->log_pharmacy_audit(
			$iop_med_id, $iop_id, $_patient_no, $status, 'PENDING', $status, $notes,
			$this->session->userdata('user_id'), $_drug_name, (float)$qty, $batch_no ?: null, $_expiry
		);

		/* Return fresh prescription data for the specific item */
		$rxList  = $this->pharmacy_model->get_patient_prescriptions($iop_id);
		$updated = null;
		foreach ($rxList as $r) {
			if ((int)$r->iop_med_id === $iop_med_id) { $updated = $r; break; }
		}

		echo json_encode(array(
			'ok'             => true,
			'message'        => 'Medication ' . strtolower($status) . ' successfully. Stock updated.',
			'iop_med_id'     => $iop_med_id,
			'dispensed_qty'  => $updated ? (float)$updated->dispensed_qty  : null,
			'remaining_qty'  => $updated ? (float)$updated->remaining_qty  : null,
			'current_stock'  => $updated ? (float)$updated->current_stock  : null,
			'status_label'   => $updated ? (string)$updated->status_label  : $status,
		));
	}

	public function medication_clearance()
	{
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		list($iop_id, $patient_no) = $this->get_url_params(4, 5);
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		if ($iop_id === '') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$req = $this->billing_model->medication_clearance_requirements($iop_id);
		if (!$req || !isset($req['ok']) || !$req['ok']) {
			$msg = "Cannot clear medication: ensure all drugs are billed and fully dispensed.";
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $msg . "</div>");
			redirect(base_url() . 'app/pharmacy');
			return;
		}
		$this->billing_model->upsert_clearance_stage($iop_id, 'MEDICATION', $patient_no !== '' ? $patient_no : null, $this->session->userdata('user_id'));
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication clearance recorded.</div>");
		redirect(base_url() . 'app/pharmacy');
	}

	/* ── Stock Management ──────────────────────────────── */

	public function stock()
	{
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_stock',
			'subtab' => '',
			'submodule' => ''
		));

		$filters = array(
			'search'       => trim((string)$this->input->get_post('search')),
			'show_low'     => ($this->input->get_post('show_low') === '1'),
			'show_out'     => ($this->input->get_post('show_out') === '1'),
			'show_expiring'=> ($this->input->get_post('show_expiring') === '1'),
			'show_expired' => ($this->input->get_post('show_expired') === '1'),
			'limit'        => (int)$this->input->get_post('limit'),
			'offset'       => (int)$this->input->get_post('offset'),
		);

		$this->data['filters']           = $filters;
		$this->data['message']           = $this->session->flashdata('message');
		$this->data['stock_list']        = $this->pharmacy_model->get_stock_list($filters);
		$this->data['total_count']       = $this->pharmacy_model->count_stock_list(array());
		$this->data['low_stock_count']   = $this->pharmacy_model->count_low_stock();
		$this->data['out_of_stock_count']= $this->pharmacy_model->count_out_of_stock();
		$this->data['expiring_count']    = $this->pharmacy_model->count_expiring_soon(90);
		$this->data['expired_count']     = $this->pharmacy_model->count_expired_batches();
		$this->data['filtered_count']    = $this->pharmacy_model->count_stock_list($filters);
		$this->load->view('app/pharmacy/stock_v2', $this->data);
	}

	// =========================================================================
	// PHASE 6C — Stock Alerts JSON (for dashboard badge polling)
	// GET app/pharmacy/stock_alerts_json
	// =========================================================================
	public function stock_alerts_json()
	{
		header('Content-Type: application/json');
		if (!$this->_require_full_pharmacy_access(true)) {
			return;
		}
		$alerts = $this->pharmacy_model->get_pharmacy_alerts();
		$alerts['out_of_stock'] = $this->pharmacy_model->count_out_of_stock();
		echo json_encode(array('success' => true, 'alerts' => $alerts));
	}

	// =========================================================================
	// PHASE 6B — Batch list JSON for dispense modal dropdown
	// GET app/pharmacy/batches_json?drug_id=<id>
	// =========================================================================
	public function batches_json()
	{
		header('Content-Type: application/json');
		if (!$this->_user_has_pharmacy_dispense_access()) {
			echo json_encode(array('ok' => false, 'batches' => array()));
			return;
		}
		$drug_id = (int)$this->input->get('drug_id');
		if ($drug_id <= 0) {
			echo json_encode(array('ok' => false, 'batches' => array()));
			return;
		}
		$batches = $this->pharmacy_model->get_batch_stock($drug_id);
		$out = array();
		foreach ($batches as $b) {
			$out[] = array(
				'batch_number' => (string)$b->batch_number,
				'quantity'     => (float)$b->quantity,
				'expiry_date'  => $b->expiry_date ? (string)$b->expiry_date : null,
			);
		}
		echo json_encode(array('ok' => true, 'batches' => $out));
	}

	public function adjust_stock_action()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$drug_id = (int)$this->input->post('drug_id');
		$qty_change = (float)$this->input->post('qty_change');
		$reason = trim((string)$this->input->post('reason'));
		$user_id = $this->session->userdata('user_id');

		if ($drug_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invalid drug selected.</div>");
			redirect(base_url() . 'app/pharmacy/stock');
			return;
		}

		if ($qty_change == 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Quantity change cannot be zero.</div>");
			redirect(base_url() . 'app/pharmacy/stock');
			return;
		}

		if ($reason === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Reason is required for stock adjustment.</div>");
			redirect(base_url() . 'app/pharmacy/stock');
			return;
		}

		// Admin and pharmacists: auto-approve. Others: create pending request.
		if (is_admin_role() || has_role('pharmacist')) {
			$ok = $this->pharmacy_model->adjust_stock($drug_id, $qty_change, $reason, $user_id);
			if ($ok) {
				$label = ($qty_change > 0) ? 'restocked' : 'adjusted';
				$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Stock " . $label . " successfully.</div>");
			} else {
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Stock adjustment failed.</div>");
			}
		} else {
			$reqData = array(
				'medication_id' => $drug_id,
				'request_type'  => 'adjustment',
				'quantity'      => $qty_change,
				'reason'        => $reason
			);
			$result = $this->governance_model->create_stock_request($reqData, $user_id);
			if ($result['ok']) {
				$this->session->set_flashdata('message', "<div class='alert alert-info alert-dismissable'><i class='fa fa-clock-o'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Stock adjustment request submitted (Request #" . $result['request_id'] . "). Awaiting admin approval.</div>");
			} else {
				$this->session->set_flashdata('message', "<div class='alert alert-danger'>Failed to submit stock request.</div>");
			}
		}
		redirect(base_url() . 'app/pharmacy/stock');
	}

	/**
	 * AJAX endpoint for stock adjustments - returns JSON
	 * POST app/pharmacy/adjust_stock_ajax
	 */
	public function adjust_stock_ajax()
	{
		header('Content-Type: application/json');
		if (!$this->_require_full_pharmacy_access(true)) {
			return;
		}
		
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid request method.'));
			return;
		}

		$drug_id = (int)$this->input->post('drug_id');
		$qty_change = (float)$this->input->post('qty_change');
		$reason = trim((string)$this->input->post('reason'));
		$action = trim((string)$this->input->post('action')); // 'restock' or 'adjust'
		$user_id = $this->session->userdata('user_id');

		if ($drug_id <= 0) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid drug selected.'));
			return;
		}
		if ($qty_change == 0) {
			echo json_encode(array('ok' => false, 'error' => 'Quantity change cannot be zero.'));
			return;
		}
		if ($reason === '') {
			echo json_encode(array('ok' => false, 'error' => 'Reason is required.'));
			return;
		}

		// For restock action, ensure positive quantity
		if ($action === 'restock' && $qty_change < 0) {
			$qty_change = abs($qty_change);
		}

		// Admin and pharmacists: auto-approve. Others: create pending request.
		if (is_admin_role() || has_role('pharmacist')) {
			// Use the new pharmacy stock model for better cache handling
			$ok = $this->pharmacy_stock_model->adjust_stock($drug_id, $qty_change, $reason, $user_id);
			if ($ok !== false) {
				// Force invalidate all stock cache to ensure legacy model sees fresh data
				$this->pharmacy_stock_model->invalidate_all_stock_cache();
				
				// Get fresh stock value from the same model with cache bypass to avoid conflicts
				$new_stock = $this->pharmacy_stock_model->get_drug_stock($drug_id, true);
				echo json_encode(array(
					'ok' => true,
					'auto_approved' => true,
					'new_stock' => $new_stock,
					'message' => ($qty_change > 0 ? 'Restocked' : 'Adjusted') . ' successfully.'
				));
			} else {
				echo json_encode(array('ok' => false, 'error' => 'Stock adjustment failed.'));
			}
		} else {
			$reqData = array(
				'medication_id' => $drug_id,
				'request_type'  => ($action === 'restock') ? 'restock' : 'adjustment',
				'quantity'      => $qty_change,
				'reason'        => $reason
			);
			$result = $this->governance_model->create_stock_request($reqData, $user_id);
			if ($result['ok']) {
				echo json_encode(array(
					'ok' => true,
					'auto_approved' => false,
					'request_id' => $result['request_id'],
					'message' => 'Request #' . $result['request_id'] . ' submitted. Awaiting admin approval.'
				));
			} else {
				echo json_encode(array('ok' => false, 'error' => 'Failed to submit request.'));
			}
		}
	}

	public function stock_history()
	{
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$drug_id = (int)$this->uri->segment(4);
		if ($drug_id <= 0) {
			redirect(base_url() . 'app/pharmacy/stock');
			return;
		}

		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_stock',
			'subtab' => '',
			'submodule' => ''
		));

		$this->data['drug'] = $this->pharmacy_model->get_drug_stock($drug_id);
		$this->data['history'] = $this->pharmacy_model->get_stock_history($drug_id, 50);
		$this->data['batch_stock'] = $this->pharmacy_model->get_batch_stock($drug_id, true);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/pharmacy/stock_history', $this->data);
	}

	/* ── Pharmacy V2 Endpoints ─────────────────────────── */

	public function mark_unavailable()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$iop_med_id = (int)$this->input->post('iop_med_id');
		$notes = (string)$this->input->post('notes');
		$return_url = (string)$this->input->post('return_url');

		if ($iop_med_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'>Invalid request.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
			return;
		}

		$result = $this->pharmacy_model->mark_unavailable($iop_med_id, $this->session->userdata('user_id'), $notes);
		if ($result['ok']) {
			$this->pharmacy_model->cancel_pharmacy_bill(
				$this->_get_bill_id_for_med($iop_med_id),
				$this->session->userdata('user_id'),
				$notes !== '' ? $notes : 'Marked unavailable by pharmacist'
			);
			$this->pharmacy_model->log_pharmacy_audit($iop_med_id, '', '', 'UNAVAILABLE', 'PENDING', 'UNAVAILABLE', $notes, $this->session->userdata('user_id'));
			$this->session->set_flashdata('message', "<div class='alert alert-info alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication marked as <strong>UNAVAILABLE</strong>. It will NOT be billed.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . htmlspecialchars($result['error']) . "</div>");
		}
		redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
	}

	public function mark_available()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$iop_med_id = (int)$this->input->post('iop_med_id');
		$return_url = (string)$this->input->post('return_url');

		$result = $this->pharmacy_model->mark_available($iop_med_id, $this->session->userdata('user_id'));
		if ($result['ok']) {
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication restored to PENDING.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'>" . htmlspecialchars($result['error']) . "</div>");
		}
		redirect($return_url !== '' ? $return_url : base_url() . 'app/pharmacy');
	}

	public function batch_restock()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$data = array(
			'medication_id' => (int)$this->input->post('medication_id'),
			'batch_number'  => trim((string)$this->input->post('batch_number')),
			'quantity'      => (float)$this->input->post('quantity'),
			'expiry_date'   => trim((string)$this->input->post('expiry_date')),
			'unit_cost'     => (float)$this->input->post('unit_cost'),
			'selling_price' => (float)$this->input->post('selling_price'),
			'supplier'      => trim((string)$this->input->post('supplier'))
		);
		$user_id = $this->session->userdata('user_id');

		// Admin: auto-approve. Others: create pending request.
		if (is_admin_role()) {
			$result = $this->pharmacy_model->add_batch_stock($data, $user_id);
			if ($result['ok']) {
				$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Batch stock added successfully (Admin auto-approved).</div>");
			} else {
				$this->session->set_flashdata('message', "<div class='alert alert-danger'>" . htmlspecialchars($result['error']) . "</div>");
			}
		} else {
			$data['request_type'] = 'batch_restock';
			$data['reason'] = 'Batch restock: ' . $data['batch_number'];
			$result = $this->governance_model->create_stock_request($data, $user_id);
			if ($result['ok']) {
				$this->session->set_flashdata('message', "<div class='alert alert-info alert-dismissable'><i class='fa fa-clock-o'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Batch restock request submitted (Request #" . $result['request_id'] . "). Awaiting admin approval.</div>");
			} else {
				$this->session->set_flashdata('message', "<div class='alert alert-danger'>Failed to submit restock request.</div>");
			}
		}
		redirect(base_url() . 'app/pharmacy/stock');
	}

	public function remove_expired()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}

		$stock_id = (int)$this->input->post('stock_id');
		$reason = trim((string)$this->input->post('reason'));
		if ($reason === '') $reason = 'Expired';

		$ok = $this->pharmacy_model->remove_expired_batch($stock_id, $this->session->userdata('user_id'), $reason);
		if ($ok) {
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Expired batch removed from stock.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'>Failed to remove batch.</div>");
		}
		redirect(base_url() . 'app/pharmacy/alerts');
	}

	public function alerts()
	{
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_alerts',
			'subtab' => '',
			'submodule' => ''
		));

		$this->data['message']           = $this->session->flashdata('message');
		$this->data['alerts']            = $this->pharmacy_model->get_pharmacy_alerts();
		$this->data['alerts']['out_of_stock'] = $this->pharmacy_model->count_out_of_stock();
		$this->data['expiring_batches']  = $this->pharmacy_model->get_expiring_batches(90, 100);
		$this->data['expired_batches']   = $this->pharmacy_model->get_expired_batches(100);
		$this->data['low_stock_drugs']   = $this->pharmacy_model->get_stock_list(array('show_low' => true, 'limit' => 100));
		$this->data['out_stock_drugs']   = $this->pharmacy_model->get_stock_list(array('show_out' => true, 'limit' => 100));
		$this->load->view('app/pharmacy/alerts', $this->data);
	}

	private function _get_bill_id_for_med($iop_med_id){
		if (!$this->pharmacy_model->table_exists('pharmacy_billing_queue')) return 0;
		$row = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => (int)$iop_med_id, 'InActive' => 0))->row();
		if (!$row) return 0;
		if (isset($row->bill_id) && (int)$row->bill_id > 0) return (int)$row->bill_id;
		if (isset($row->id) && (int)$row->id > 0) return (int)$row->id;
		return 0;
	}

	/* ── Flexible Workflow Endpoints ──────────────────────────── */

	public function mark_external_purchase()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$iop_med_id = (int)$this->input->post('iop_med_id');
		$reason     = trim((string)$this->input->post('reason'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id    = (string)$this->session->userdata('user_id');
		$result = $this->pharmacy_model->mark_external_purchase($iop_med_id, $user_id, $reason);
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Marked as External Purchase. Patient may obtain medication externally.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect($return_url !== '' ? $return_url : base_url().'app/pharmacy');
	}

	public function mark_unable_to_pay()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$iop_med_id = (int)$this->input->post('iop_med_id');
		$reason     = trim((string)$this->input->post('reason'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id    = (string)$this->session->userdata('user_id');
		$result = $this->pharmacy_model->mark_unable_to_pay($iop_med_id, $user_id, $reason);
		if ($result['ok']) {
			$bal = isset($result['outstanding_balance']) ? number_format((float)$result['outstanding_balance'], 2) : '0.00';
			$this->session->set_flashdata('message', '<div class="alert alert-warning">Marked as Unable to Pay. Outstanding balance GHS '.$bal.' recorded. Medication dispensed on humanitarian grounds.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect($return_url !== '' ? $return_url : base_url().'app/pharmacy');
	}

	public function mark_deferred_payment()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$iop_med_id  = (int)$this->input->post('iop_med_id');
		$reason      = trim((string)$this->input->post('reason'));
		$defer_until = trim((string)$this->input->post('defer_until'));
		$return_url  = trim((string)$this->input->post('return_url'));
		$user_id     = (string)$this->session->userdata('user_id');
		if ($defer_until === '') $defer_until = null;
		$result = $this->pharmacy_model->mark_deferred($iop_med_id, $user_id, $reason, $defer_until);
		if ($result['ok']) {
			$due = $defer_until ? ' (due '.$defer_until.')' : '';
			$this->session->set_flashdata('message', '<div class="alert alert-info">Payment deferred'.$due.'. Medication can be dispensed. Cashier notified.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect($return_url !== '' ? $return_url : base_url().'app/pharmacy');
	}

	public function mark_emergency_override()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$iop_med_id = (int)$this->input->post('iop_med_id');
		$reason     = trim((string)$this->input->post('reason'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id    = (string)$this->session->userdata('user_id');
		$result = $this->pharmacy_model->mark_emergency_override($iop_med_id, $user_id, $reason);
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger"><strong>Emergency Override Applied.</strong> Medication dispensed without payment. Audit trail logged.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect($return_url !== '' ? $return_url : base_url().'app/pharmacy');
	}

	public function request_waiver()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_require_full_pharmacy_access(false)) {
			return;
		}
		$iop_med_id = (int)$this->input->post('iop_med_id');
		$reason     = trim((string)$this->input->post('reason'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id    = (string)$this->session->userdata('user_id');
		$result = $this->pharmacy_model->request_waiver($iop_med_id, $user_id, $reason);
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-info">Waiver request submitted. Awaiting Admin/Super-Admin approval.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect($return_url !== '' ? $return_url : base_url().'app/pharmacy');
	}

	/* ── ─────────────────────────────────────────────────────── */

	public function drug_search_json()
	{
		header('Content-Type: application/json');
		if (!$this->_user_has_full_pharmacy_access() && !$this->current_user_is_doctor()) {
			echo json_encode(array());
			return;
		}
		$term = trim((string)$this->input->get_post('term'));
		$results = $this->pharmacy_model->search_drugs($term, 20);
		$out = array();
		foreach ($results as $r) {
			$label = $r->drug_name;
			if (isset($r->strength) && $r->strength !== '' && $r->strength !== null) {
				$label .= ' ' . $r->strength;
			}
			if (isset($r->dosage_form) && $r->dosage_form !== '' && $r->dosage_form !== null) {
				$label .= ' (' . $r->dosage_form . ')';
			}
			$stock = isset($r->nStock) ? (float)$r->nStock : 0;
			$nhis = isset($r->is_nhis_covered) ? (int)$r->is_nhis_covered : 0;
			$out[] = array(
				'id'           => (int)$r->drug_id,
				'label'        => $label,
				'drug_name'    => $r->drug_name,
				'generic_name' => isset($r->generic_name) ? (string)$r->generic_name : '',
				'category'     => isset($r->med_category_name) ? (string)$r->med_category_name : '',
				'stock'        => $stock,
				'price'        => (float)$r->nPrice,
				'nhis_covered' => $nhis,
				'nhis_price'   => isset($r->nhis_price) ? (float)$r->nhis_price : 0,
				'cash_price'   => isset($r->cash_price) ? (float)$r->cash_price : 0,
				'in_stock'     => ($stock > 0)
			);
		}
		echo json_encode($out);
	}

	// =========================================================================
	// MULTI-STORE PHARMACY MANAGEMENT
	// =========================================================================

	public function stores()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'pharmacy_stores'));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['stores'] = $this->pharmacy_architecture_model->get_all_stores(true);
		$this->data['summary'] = $this->pharmacy_architecture_model->get_stock_summary_by_store();
		$this->data['pending_transfers'] = $this->pharmacy_architecture_model->count_pending_transfers();
		$this->load->view('app/pharmacy/stores', $this->data);
	}

	public function store_add()
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'app/pharmacy/stores');
			return;
		}

		$data = array(
			'store_code' => $this->input->post('store_code'),
			'store_name' => $this->input->post('store_name'),
			'store_type' => $this->input->post('store_type'),
			'location' => $this->input->post('location'),
			'contact_phone' => $this->input->post('contact_phone'),
			'manager_user_id' => $this->input->post('manager_user_id'),
			'operating_hours' => $this->input->post('operating_hours'),
			'can_dispense' => $this->input->post('can_dispense') ? 1 : 0,
			'can_receive_transfers' => $this->input->post('can_receive_transfers') ? 1 : 0,
			'created_by' => $this->session->userdata('user_id')
		);

		$store_id = $this->pharmacy_architecture_model->add_store($data);
		if ($store_id) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Store added successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to add store.</div>');
		}
		redirect(base_url() . 'app/pharmacy/stores');
	}

	public function store_edit($store_id = '')
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'app/pharmacy/stores');
			return;
		}

		$store_id = (int)$store_id;
		$data = array(
			'store_name' => $this->input->post('store_name'),
			'store_type' => $this->input->post('store_type'),
			'location' => $this->input->post('location'),
			'contact_phone' => $this->input->post('contact_phone'),
			'manager_user_id' => $this->input->post('manager_user_id'),
			'operating_hours' => $this->input->post('operating_hours'),
			'is_active' => $this->input->post('is_active') ? 1 : 0,
			'can_dispense' => $this->input->post('can_dispense') ? 1 : 0,
			'can_receive_transfers' => $this->input->post('can_receive_transfers') ? 1 : 0
		);

		if ($this->pharmacy_architecture_model->update_store($store_id, $data)) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Store updated successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to update store.</div>');
		}
		redirect(base_url() . 'app/pharmacy/stores');
	}

	public function store_stock($store_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$store_id = (int)$store_id;
		$store = $this->pharmacy_architecture_model->get_store($store_id);
		if (!$store) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Store not found.</div>');
			redirect(base_url() . 'app/pharmacy/stores');
			return;
		}

		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'pharmacy_store_stock'));

		$filters = array(
			'search' => (string)$this->input->get_post('search'),
			'low_stock_only' => (int)$this->input->get_post('low_stock_only'),
			'expiring_soon' => (int)$this->input->get_post('expiring_soon')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['store'] = $store;
		$this->data['stock'] = $this->pharmacy_architecture_model->get_store_stock($store_id, $filters);
		$this->data['filters'] = $filters;
		$this->data['all_stores'] = $this->pharmacy_architecture_model->get_all_stores();
		$this->load->view('app/pharmacy/store_stock', $this->data);
	}

	public function transfer_request()
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'app/pharmacy/stores');
			return;
		}

		$from_store_id = (int)$this->input->post('from_store_id');
		$to_store_id = (int)$this->input->post('to_store_id');
		$drug_id = (int)$this->input->post('drug_id');
		$quantity = (float)$this->input->post('quantity');
		$batch_no = $this->input->post('batch_no') ?: null;
		$notes = $this->input->post('notes');
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_architecture_model->request_transfer($from_store_id, $to_store_id, $drug_id, $quantity, $user_id, $notes, $batch_no);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Transfer request #' . $result['transfer_no'] . ' created successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		$return_url = $this->input->post('return_url') ?: base_url() . 'app/pharmacy/transfers';
		redirect($return_url);
	}

	public function transfers()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'pharmacy_transfers'));

		$user_id = $this->session->userdata('user_id');
		$user_store = $this->pharmacy_architecture_model->get_user_store($user_id);
		$store_id = $user_store ? $user_store->store_id : null;

		$filters = array(
			'store_id' => $this->input->get_post('store_id') ?: $store_id,
			'status' => $this->input->get_post('status'),
			'date_from' => $this->input->get_post('date_from'),
			'date_to' => $this->input->get_post('date_to')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['transfers'] = $this->pharmacy_architecture_model->get_transfer_history($filters);
		$this->data['pending_incoming'] = $store_id ? $this->pharmacy_architecture_model->get_pending_transfers($store_id, 'incoming') : array();
		$this->data['pending_outgoing'] = $store_id ? $this->pharmacy_architecture_model->get_pending_transfers($store_id, 'outgoing') : array();
		$this->data['all_stores'] = $this->pharmacy_architecture_model->get_all_stores();
		$this->data['user_store'] = $user_store;
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/transfers', $this->data);
	}

	public function transfer_approve($transfer_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$transfer_id = (int)$transfer_id;
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_architecture_model->approve_transfer($transfer_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Transfer approved and stock deducted from source.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		redirect(base_url() . 'app/pharmacy/transfers');
	}

	public function transfer_receive($transfer_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$transfer_id = (int)$transfer_id;
		$user_id = $this->session->userdata('user_id');
		$received_qty = $this->input->post('received_qty') ? (float)$this->input->post('received_qty') : null;

		$result = $this->pharmacy_architecture_model->receive_transfer($transfer_id, $user_id, $received_qty);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Transfer received and stock added to destination.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		redirect(base_url() . 'app/pharmacy/transfers');
	}

	public function transfer_cancel($transfer_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$transfer_id = (int)$transfer_id;
		$user_id = $this->session->userdata('user_id');
		$reason = $this->input->post('reason') ?: 'Cancelled by user';

		$result = $this->pharmacy_architecture_model->cancel_transfer($transfer_id, $user_id, $reason);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning">Transfer cancelled.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		redirect(base_url() . 'app/pharmacy/transfers');
	}

	public function low_stock_report()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'pharmacy_low_stock'));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['low_stock'] = $this->pharmacy_architecture_model->get_low_stock_all_stores();
		$this->data['expiring'] = $this->pharmacy_architecture_model->get_expiring_items_all_stores(90);
		$this->load->view('app/pharmacy/low_stock_report', $this->data);
	}

	public function stores_json()
	{
		if (!$this->_require_full_pharmacy_access(true)) {
			return;
		}
		$stores = $this->pharmacy_architecture_model->get_all_stores();
		header('Content-Type: application/json');
		echo json_encode($stores);
	}

	public function store_stock_json($store_id = '')
	{
		if (!$this->_require_full_pharmacy_access(true)) {
			return;
		}
		$store_id = (int)$store_id;
		$drug_id = (int)$this->input->get_post('drug_id');
		$qty = $this->pharmacy_architecture_model->get_drug_stock_at_store($store_id, $drug_id);
		header('Content-Type: application/json');
		echo json_encode(array('store_id' => $store_id, 'drug_id' => $drug_id, 'quantity' => $qty));
	}

	// =========================================================================
	// CONTROLLED DRUGS MANAGEMENT
	// =========================================================================

	public function controlled_drugs()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'controlled_drugs'));

		$filters = array(
			'schedule_id' => $this->input->get('schedule_id'),
			'search' => $this->input->get('search')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['drugs'] = $this->pharmacy_architecture_model->get_controlled_drugs($filters);
		$this->data['schedules'] = $this->pharmacy_architecture_model->get_drug_schedules();
		$this->data['filters'] = $filters;
		$this->data['pending_auth_count'] = $this->pharmacy_architecture_model->count_pending_controlled_authorizations($this->session->userdata('user_id'));
		$this->load->view('app/pharmacy/controlled_drugs', $this->data);
	}

	public function controlled_drug_schedules()
	{
		require_role(array('admin'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'controlled_schedules'));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['schedules'] = $this->pharmacy_architecture_model->get_drug_schedules();
		$this->load->view('app/pharmacy/controlled_schedules', $this->data);
	}

	public function set_controlled($drug_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$drug_id = (int)$drug_id;

		if ($this->input->post()) {
			$schedule_id = (int)$this->input->post('schedule_id');
			$notes = $this->input->post('controlled_notes');

			if ($schedule_id > 0) {
				$this->pharmacy_architecture_model->set_drug_controlled($drug_id, $schedule_id, $notes);
				$this->session->set_flashdata('message', '<div class="alert alert-success">Drug marked as controlled substance.</div>');
			} else {
				$this->pharmacy_architecture_model->unset_drug_controlled($drug_id);
				$this->session->set_flashdata('message', '<div class="alert alert-info">Drug removed from controlled substances list.</div>');
			}
		}

		redirect(base_url() . 'app/pharmacy/controlled_drugs');
	}

	public function pending_authorizations()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'pending_auth'));

		$user_id = $this->session->userdata('user_id');

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['pending'] = $this->pharmacy_architecture_model->get_pending_controlled_authorizations($user_id);
		$this->data['schedules'] = $this->pharmacy_architecture_model->get_drug_schedules();
		$this->load->view('app/pharmacy/pending_authorizations', $this->data);
	}

	public function initiate_controlled_dispense()
	{
		require_role(array('admin', 'pharmacist'));

		if (!$this->input->post()) {
			redirect(base_url() . 'app/pharmacy');
			return;
		}

		$iop_med_id = (int)$this->input->post('iop_med_id');
		$drug_id = (int)$this->input->post('drug_id');
		$patient_no = $this->input->post('patient_no');
		$quantity = (float)$this->input->post('quantity');
		$user_id = $this->session->userdata('user_id');

		$data = array(
			'batch_no' => $this->input->post('batch_no'),
			'store_id' => $this->input->post('store_id'),
			'patient_id_type' => $this->input->post('patient_id_type'),
			'patient_id_number' => $this->input->post('patient_id_number'),
			'patient_id_verified' => $this->input->post('patient_id_verified') ? 1 : 0,
			'prescription_verified' => $this->input->post('prescription_verified') ? 1 : 0,
			'notes' => $this->input->post('notes')
		);

		$result = $this->pharmacy_architecture_model->initiate_controlled_dispense(
			$iop_med_id, $drug_id, $patient_no, $quantity, $user_id, $data
		);

		if ($result['success']) {
			if ($result['requires_second_auth']) {
				$this->session->set_flashdata('message', '<div class="alert alert-warning"><i class="fa fa-clock-o"></i> ' . htmlspecialchars($result['message']) . '</div>');
			} else {
				$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> ' . htmlspecialchars($result['message']) . '</div>');
			}
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy');
	}

	public function authorize_controlled($dispense_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$dispense_id = (int)$dispense_id;
		$user_id = $this->session->userdata('user_id');
		$witness_id = $this->input->post('witness_id');

		$result = $this->pharmacy_architecture_model->authorize_controlled_dispense($dispense_id, $user_id, $witness_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> ' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/pending_authorizations');
	}

	public function reject_controlled($dispense_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$dispense_id = (int)$dispense_id;
		$user_id = $this->session->userdata('user_id');
		$reason = $this->input->post('reason') ?: 'Rejected by pharmacist';

		$result = $this->pharmacy_architecture_model->reject_controlled_dispense($dispense_id, $user_id, $reason);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/pending_authorizations');
	}

	public function complete_controlled($dispense_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$dispense_id = (int)$dispense_id;
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_architecture_model->complete_controlled_dispense($dispense_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> ' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/pending_authorizations');
	}

	public function controlled_register()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'controlled_register'));

		$filters = array(
			'drug_id' => $this->input->get('drug_id'),
			'store_id' => $this->input->get('store_id'),
			'transaction_type' => $this->input->get('transaction_type'),
			'date_from' => $this->input->get('date_from'),
			'date_to' => $this->input->get('date_to'),
			'limit' => 100
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['register'] = $this->pharmacy_architecture_model->get_controlled_drug_register($filters);
		$this->data['drugs'] = $this->pharmacy_architecture_model->get_controlled_drugs();
		$this->data['stores'] = $this->pharmacy_architecture_model->get_all_stores();
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/controlled_register', $this->data);
	}

	public function check_controlled_json($drug_id = '')
	{
		$drug_id = (int)$drug_id;
		$info = $this->pharmacy_architecture_model->get_drug_schedule_info($drug_id);

		header('Content-Type: application/json');
		if ($info && $info->is_controlled) {
			echo json_encode(array(
				'is_controlled' => true,
				'schedule_code' => $info->schedule_code,
				'schedule_name' => $info->schedule_name,
				'requires_double_auth' => (bool)$info->requires_double_auth,
				'requires_witness' => (bool)$info->requires_witness,
				'requires_id_verification' => (bool)$info->requires_id_verification
			));
		} else {
			echo json_encode(array('is_controlled' => false));
		}
	}

	// =========================================================================
	// GENERIC VS BRAND DRUG MAPPING
	// =========================================================================

	public function generic_drugs()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'generic_drugs'));

		$filters = array(
			'search' => $this->input->get('search'),
			'therapeutic_class' => $this->input->get('therapeutic_class'),
			'is_essential' => $this->input->get('is_essential'),
			'is_nhis_listed' => $this->input->get('is_nhis_listed')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['generics'] = $this->pharmacy_architecture_model->get_generic_drugs($filters);
		$this->data['therapeutic_classes'] = $this->pharmacy_architecture_model->get_therapeutic_classes();
		$this->data['unmapped_count'] = count($this->pharmacy_architecture_model->get_unmapped_drugs(1000));
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/generic_drugs', $this->data);
	}

	public function add_generic()
	{
		require_role(array('admin', 'pharmacist'));

		if ($this->input->post()) {
			$data = array(
				'generic_name' => $this->input->post('generic_name'),
				'generic_code' => $this->input->post('generic_code'),
				'therapeutic_class' => $this->input->post('therapeutic_class'),
				'pharmacological_class' => $this->input->post('pharmacological_class'),
				'atc_code' => $this->input->post('atc_code'),
				'description' => $this->input->post('description'),
				'common_dosage_forms' => $this->input->post('common_dosage_forms'),
				'common_strengths' => $this->input->post('common_strengths'),
				'is_essential' => $this->input->post('is_essential') ? 1 : 0,
				'is_nhis_listed' => $this->input->post('is_nhis_listed') ? 1 : 0
			);

			$generic_id = $this->pharmacy_architecture_model->add_generic_drug($data);
			if ($generic_id) {
				$this->session->set_flashdata('message', '<div class="alert alert-success">Generic drug added successfully.</div>');
			} else {
				$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to add generic drug.</div>');
			}
		}

		redirect(base_url() . 'app/pharmacy/generic_drugs');
	}

	public function edit_generic($generic_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$generic_id = (int)$generic_id;

		if ($this->input->post()) {
			$data = array(
				'generic_name' => $this->input->post('generic_name'),
				'generic_code' => $this->input->post('generic_code'),
				'therapeutic_class' => $this->input->post('therapeutic_class'),
				'pharmacological_class' => $this->input->post('pharmacological_class'),
				'atc_code' => $this->input->post('atc_code'),
				'description' => $this->input->post('description'),
				'common_dosage_forms' => $this->input->post('common_dosage_forms'),
				'common_strengths' => $this->input->post('common_strengths'),
				'is_essential' => $this->input->post('is_essential') ? 1 : 0,
				'is_nhis_listed' => $this->input->post('is_nhis_listed') ? 1 : 0
			);

			$this->pharmacy_architecture_model->update_generic_drug($generic_id, $data);
			$this->session->set_flashdata('message', '<div class="alert alert-success">Generic drug updated.</div>');
		}

		redirect(base_url() . 'app/pharmacy/generic_drugs');
	}

	public function generic_brands($generic_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$generic_id = (int)$generic_id;
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'generic_brands'));

		$generic = $this->pharmacy_architecture_model->get_generic_drug($generic_id);
		if (!$generic) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Generic drug not found.</div>');
			redirect(base_url() . 'app/pharmacy/generic_drugs');
			return;
		}

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['generic'] = $generic;
		$this->data['brands'] = $this->pharmacy_architecture_model->get_brands_for_generic($generic_id);
		$this->data['unmapped_drugs'] = $this->pharmacy_architecture_model->get_unmapped_drugs(200);
		$this->load->view('app/pharmacy/generic_brands', $this->data);
	}

	public function map_brand()
	{
		require_role(array('admin', 'pharmacist'));

		if ($this->input->post()) {
			$drug_id = (int)$this->input->post('drug_id');
			$generic_id = (int)$this->input->post('generic_id');
			$user_id = $this->session->userdata('user_id');

			$data = array(
				'is_primary_brand' => $this->input->post('is_primary_brand') ? 1 : 0,
				'bioequivalence_rating' => $this->input->post('bioequivalence_rating'),
				'manufacturer' => $this->input->post('manufacturer'),
				'country_of_origin' => $this->input->post('country_of_origin'),
				'notes' => $this->input->post('notes'),
				'created_by' => $user_id
			);

			$result = $this->pharmacy_architecture_model->map_brand_to_generic($drug_id, $generic_id, $data);

			if ($result['success']) {
				$this->session->set_flashdata('message', '<div class="alert alert-success">Brand mapped to generic successfully.</div>');
			} else {
				$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
			}

			redirect(base_url() . 'app/pharmacy/generic_brands/' . $generic_id);
			return;
		}

		redirect(base_url() . 'app/pharmacy/generic_drugs');
	}

	public function unmap_brand($mapping_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$mapping_id = (int)$mapping_id;
		$generic_id = (int)$this->input->get_post('generic_id');

		$this->pharmacy_architecture_model->unmap_brand_from_generic($mapping_id);
		$this->session->set_flashdata('message', '<div class="alert alert-warning">Brand unmapped from generic.</div>');

		if ($generic_id) {
			redirect(base_url() . 'app/pharmacy/generic_brands/' . $generic_id);
		} else {
			redirect(base_url() . 'app/pharmacy/generic_drugs');
		}
	}

	public function unmapped_drugs()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'unmapped_drugs'));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['drugs'] = $this->pharmacy_architecture_model->get_unmapped_drugs(500);
		$this->data['generics'] = $this->pharmacy_architecture_model->get_generic_drugs();
		$this->load->view('app/pharmacy/unmapped_drugs', $this->data);
	}

	public function search_generics_json()
	{
		$term = $this->input->get_post('term');
		$results = $this->pharmacy_architecture_model->search_generics($term, 20);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	public function get_equivalents_json($drug_id = '')
	{
		$drug_id = (int)$drug_id;
		$equivalents = $this->pharmacy_architecture_model->find_equivalent_brands($drug_id, false);
		header('Content-Type: application/json');
		echo json_encode(array(
			'drug_id' => $drug_id,
			'equivalents' => $equivalents,
			'substitution_allowed' => $this->pharmacy_architecture_model->is_substitution_allowed($drug_id)
		));
	}

	public function get_substitutions_json($drug_id = '')
	{
		$drug_id = (int)$drug_id;
		$suggestions = $this->pharmacy_architecture_model->get_substitution_suggestions($drug_id, 5);
		header('Content-Type: application/json');
		echo json_encode(array(
			'drug_id' => $drug_id,
			'suggestions' => $suggestions,
			'substitution_allowed' => $this->pharmacy_architecture_model->is_substitution_allowed($drug_id)
		));
	}

	// =========================================================================
	// PRESCRIPTION LOCKING MECHANISM
	// =========================================================================

	public function prescription_status()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'prescription_status'));

		// Release expired locks
		$this->pharmacy_architecture_model->release_expired_locks();

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['statuses'] = $this->pharmacy_architecture_model->get_prescription_statuses();
		$this->data['status_counts'] = $this->pharmacy_architecture_model->count_prescriptions_by_status();
		$this->data['locked'] = $this->pharmacy_architecture_model->get_locked_prescriptions();
		$this->data['on_hold'] = $this->pharmacy_architecture_model->get_prescriptions_by_status('ON_HOLD', 50);
		$this->load->view('app/pharmacy/prescription_status', $this->data);
	}

	public function lock_prescription($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;
		$user_id = $this->session->userdata('user_id');
		$reason = $this->input->post('reason') ?: 'Dispensing';
		$return_url = trim((string)$this->input->post('return_url'));

		$result = $this->pharmacy_architecture_model->lock_prescription($iop_med_id, $user_id, $reason);

		if ($this->input->is_ajax_request()) {
			header('Content-Type: application/json');
			echo json_encode($result);
			return;
		}

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function unlock_prescription($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;
		$user_id = $this->session->userdata('user_id');
		$reason = $this->input->post('reason') ?: '';
		$return_url = trim((string)$this->input->post('return_url'));

		$result = $this->pharmacy_architecture_model->unlock_prescription($iop_med_id, $user_id, $reason);

		if ($this->input->is_ajax_request()) {
			header('Content-Type: application/json');
			echo json_encode($result);
			return;
		}

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy/prescription_status');
	}

	public function verify_prescription($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;
		$user_id = $this->session->userdata('user_id');
		$notes = $this->input->post('notes') ?: '';
		$return_url = trim((string)$this->input->post('return_url'));
		$defer_billing = (int)$this->input->post('defer_billing') === 1;

		$result = $defer_billing
			? $this->pharmacy_architecture_model->verify_prescription_deferred($iop_med_id, $user_id, $notes)
			: $this->pharmacy_architecture_model->verify_prescription($iop_med_id, $user_id, $notes);
		$result['iop_med_id'] = $iop_med_id;

		if ($this->input->is_ajax_request()) {
			header('Content-Type: application/json');
			echo json_encode($result);
			return;
		}

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function adjust_prescription()
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$this->input->post('iop_med_id');
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id = $this->session->userdata('user_id');
		$data = array(
			'dosage' => $this->input->post('dosage'),
			'frequency' => $this->input->post('frequency'),
			'freq_code' => $this->input->post('freq_code'),
			'days' => $this->input->post('days'),
			'approved_qty' => $this->input->post('approved_qty'),
			'billable_qty' => $this->input->post('billable_qty'),
			'reason' => $this->input->post('reason')
		);
		$result = $this->pharmacy_model->adjust_prescription_for_pharmacy($iop_med_id, $data, $user_id);
		if ($this->input->is_ajax_request()) {
			header('Content-Type: application/json');
			echo json_encode($result);
			return;
		}
		if (!empty($result['ok'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Pharmacy adjustment saved.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars(isset($result['error']) ? $result['error'] : 'Adjustment failed.') . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function bulk_verify_prescriptions()
	{
		require_role(array('admin', 'pharmacist'));
		$user_id = $this->session->userdata('user_id');
		$ids = $this->input->post('iop_med_ids');
		$notes = $this->input->post('notes') ?: '';
		if (!is_array($ids)) {
			$ids = array();
		}
		$verified = 0;
		$verified_ids = array();
		$failed = array();
		foreach ($ids as $id) {
			$iop_med_id = (int)$id;
			if ($iop_med_id <= 0) {
				continue;
			}
			$result = $this->pharmacy_architecture_model->verify_prescription_deferred($iop_med_id, $user_id, $notes);
			if (!empty($result['success'])) {
				$verified++;
				$verified_ids[] = $iop_med_id;
			} else {
				$failed[] = array('iop_med_id' => $iop_med_id, 'error' => isset($result['error']) ? $result['error'] : 'Verification failed');
			}
		}
		header('Content-Type: application/json');
		echo json_encode(array('success' => count($failed) === 0, 'verified' => $verified, 'verified_ids' => $verified_ids, 'failed' => $failed));
	}

	public function finalize_for_billing()
	{
		require_role(array('admin', 'pharmacist'));
		$iop_id = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id = $this->session->userdata('user_id');
		$result = $this->pharmacy_model->finalize_visit_for_billing($iop_id, $patient_no, $user_id);
		if ($this->input->is_ajax_request()) {
			header('Content-Type: application/json');
			echo json_encode($result);
			return;
		}
		if (!empty($result['ok'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Finalized for billing. New cashier queue entries: ' . (int)$result['created'] . '.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function substitute_medication()
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$this->input->post('iop_med_id');
		$substitute_drug_id = (int)$this->input->post('substitute_drug_id');
		$reason = $this->input->post('reason') ?: '';
		$overrides = array();
		$ovKeys = array('total_qty','days','dosage','unit','frequency','freq_code','route','instruction','advice');
		foreach ($ovKeys as $k) {
			$v = $this->input->post($k);
			if ($v !== null && $v !== '') {
				$overrides[$k] = $v;
			}
		}
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id = $this->session->userdata('user_id');
		$result = $this->pharmacy_model->substitute_medication($iop_med_id, $substitute_drug_id, $reason, $user_id, $overrides);
		if ($this->input->is_ajax_request()) {
			header('Content-Type: application/json');
			echo json_encode($result);
			return;
		}
		if (!empty($result['ok'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Medication substituted successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function cancel_prescription($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;
		$user_id = $this->session->userdata('user_id');
		$reason = $this->input->post('reason');
		$return_url = trim((string)$this->input->post('return_url'));

		if (empty($reason)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cancellation reason is required.</div>');
			if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
				redirect($return_url);
				return;
			}
			redirect(base_url() . 'app/pharmacy');
			return;
		}

		$result = $this->pharmacy_architecture_model->cancel_prescription($iop_med_id, $user_id, $reason);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function hold_prescription($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;
		$user_id = $this->session->userdata('user_id');
		$reason = $this->input->post('reason') ?: '';
		$return_url = trim((string)$this->input->post('return_url'));

		$result = $this->pharmacy_architecture_model->hold_prescription($iop_med_id, $user_id, $reason);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-info">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		if ($return_url !== '' && (strpos($return_url, base_url()) === 0 || strpos($return_url, 'app/') === 0)) {
			redirect($return_url);
			return;
		}
		redirect(base_url() . 'app/pharmacy');
	}

	public function resume_prescription($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;
		$user_id = $this->session->userdata('user_id');
		$notes = $this->input->post('notes') ?: '';
		$return_url = trim((string)$this->input->post('return_url'));

		$result = $this->pharmacy_architecture_model->resume_prescription($iop_med_id, $user_id, $notes);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}
		redirect(base_url() . 'app/pharmacy/prescription_status');
	}

	public function prescription_audit($iop_med_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$iop_med_id = (int)$iop_med_id;

		$prescription = $this->pharmacy_architecture_model->get_prescription_lock_status($iop_med_id);
		if (!$prescription) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Prescription not found.</div>');
			redirect(base_url() . 'app/pharmacy');
			return;
		}

		$this->data['prescription'] = $prescription;
		$this->data['audit'] = $this->pharmacy_architecture_model->get_prescription_audit($iop_med_id);
		$this->data['statuses'] = $this->pharmacy_architecture_model->get_prescription_statuses();
		$this->load->view('app/pharmacy/prescription_audit', $this->data);
	}

	public function get_prescription_status_json($iop_med_id = '')
	{
		$iop_med_id = (int)$iop_med_id;
		$prescription = $this->pharmacy_architecture_model->get_prescription_lock_status($iop_med_id);

		header('Content-Type: application/json');
		if ($prescription) {
			$statuses = $this->pharmacy_architecture_model->get_prescription_statuses();
			$current = isset($prescription->prescription_status) ? $prescription->prescription_status : 'PENDING';
			$allowed = $this->pharmacy_architecture_model->get_allowed_transitions($current);

			echo json_encode(array(
				'success' => true,
				'iop_med_id' => $iop_med_id,
				'status' => $current,
				'status_info' => isset($statuses[$current]) ? $statuses[$current] : null,
				'is_locked' => (bool)$prescription->is_locked,
				'locked_by' => $prescription->locked_by,
				'allowed_transitions' => $allowed
			));
		} else {
			echo json_encode(array('success' => false, 'error' => 'Prescription not found'));
		}
	}

	// =========================================================================
	// BATCH RECALL TRACKING
	// =========================================================================

	public function batch_recalls()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'batch_recalls'));

		$filters = array(
			'status' => $this->input->get('status'),
			'drug_id' => $this->input->get('drug_id'),
			'date_from' => $this->input->get('date_from'),
			'date_to' => $this->input->get('date_to')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['recalls'] = $this->pharmacy_architecture_model->get_batch_recalls($filters);
		$this->data['active_count'] = $this->pharmacy_architecture_model->count_active_recalls();
		$this->data['pending_notifications'] = $this->pharmacy_architecture_model->count_pending_notifications();
		$this->data['recall_types'] = $this->pharmacy_architecture_model->get_recall_types();
		$this->data['recall_classes'] = $this->pharmacy_architecture_model->get_recall_classes();
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/batch_recalls', $this->data);
	}

	public function create_recall()
	{
		require_role(array('admin', 'pharmacist'));

		if (!$this->input->post()) {
			redirect(base_url() . 'app/pharmacy/batch_recalls');
			return;
		}

		$data = array(
			'drug_id' => $this->input->post('drug_id'),
			'batch_number' => $this->input->post('batch_number'),
			'recall_type' => $this->input->post('recall_type'),
			'recall_class' => $this->input->post('recall_class'),
			'recall_reason' => $this->input->post('recall_reason'),
			'manufacturer' => $this->input->post('manufacturer'),
			'recall_date' => $this->input->post('recall_date'),
			'effective_date' => $this->input->post('effective_date'),
			'regulatory_reference' => $this->input->post('regulatory_reference'),
			'instructions' => $this->input->post('instructions'),
			'created_by' => $this->session->userdata('user_id')
		);

		$result = $this->pharmacy_architecture_model->create_batch_recall($data);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> Batch recall created. Affected patients have been identified.</div>');
			redirect(base_url() . 'app/pharmacy/recall_details/' . $result['recall_id']);
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to create recall.</div>');
			redirect(base_url() . 'app/pharmacy/batch_recalls');
		}
	}

	public function recall_details($recall_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$recall_id = (int)$recall_id;

		$recall = $this->pharmacy_architecture_model->get_batch_recall($recall_id);
		if (!$recall) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Recall not found.</div>');
			redirect(base_url() . 'app/pharmacy/batch_recalls');
			return;
		}

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['recall'] = $recall;
		$this->data['affected_patients'] = $this->pharmacy_architecture_model->get_recall_affected_patients($recall_id);
		$this->data['recall_types'] = $this->pharmacy_architecture_model->get_recall_types();
		$this->data['recall_classes'] = $this->pharmacy_architecture_model->get_recall_classes();
		$this->load->view('app/pharmacy/recall_details', $this->data);
	}

	public function notify_patient($affected_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$affected_id = (int)$affected_id;
		$user_id = $this->session->userdata('user_id');
		$method = $this->input->post('method') ?: 'PHONE';
		$notes = $this->input->post('notes') ?: '';
		$recall_id = (int)$this->input->post('recall_id');

		$this->pharmacy_architecture_model->mark_patient_notified($affected_id, $user_id, $method, $notes);
		$this->session->set_flashdata('message', '<div class="alert alert-success">Patient marked as notified.</div>');

		redirect(base_url() . 'app/pharmacy/recall_details/' . $recall_id);
	}

	public function mark_followup($affected_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$affected_id = (int)$affected_id;
		$notes = $this->input->post('notes') ?: '';
		$recall_id = (int)$this->input->post('recall_id');

		$this->pharmacy_architecture_model->mark_followup_required($affected_id, $notes);
		$this->session->set_flashdata('message', '<div class="alert alert-info">Follow-up marked.</div>');

		redirect(base_url() . 'app/pharmacy/recall_details/' . $recall_id);
	}

	public function resolve_recall($recall_id = '')
	{
		require_role(array('admin'));
		$recall_id = (int)$recall_id;
		$user_id = $this->session->userdata('user_id');
		$notes = $this->input->post('notes') ?: '';

		$result = $this->pharmacy_architecture_model->resolve_batch_recall($recall_id, $user_id, $notes);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to resolve recall.</div>');
		}

		redirect(base_url() . 'app/pharmacy/recall_details/' . $recall_id);
	}

	public function check_batch_recall_json()
	{
		$drug_id = (int)$this->input->get_post('drug_id');
		$batch_number = $this->input->get_post('batch_number');

		$is_recalled = $this->pharmacy_architecture_model->is_batch_recalled($drug_id, $batch_number);

		header('Content-Type: application/json');
		echo json_encode(array(
			'drug_id' => $drug_id,
			'batch_number' => $batch_number,
			'is_recalled' => $is_recalled
		));
	}

	// =========================================================================
	// FINANCIAL RECONCILIATION
	// =========================================================================

	public function reconciliations()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'reconciliations'));

		$filters = array(
			'status' => $this->input->get('status'),
			'store_id' => $this->input->get('store_id'),
			'date_from' => $this->input->get('date_from'),
			'date_to' => $this->input->get('date_to')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['reconciliations'] = $this->pharmacy_architecture_model->get_reconciliations($filters);
		$this->data['stores'] = $this->pharmacy_architecture_model->get_all_stores();
		$this->data['recon_types'] = $this->pharmacy_architecture_model->get_reconciliation_types();
		$this->data['recon_statuses'] = $this->pharmacy_architecture_model->get_reconciliation_statuses();
		$this->data['pending_count'] = $this->pharmacy_architecture_model->count_pending_reconciliations();
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/reconciliations', $this->data);
	}

	public function create_reconciliation()
	{
		require_role(array('admin', 'pharmacist'));

		if (!$this->input->post()) {
			redirect(base_url() . 'app/pharmacy/reconciliations');
			return;
		}

		$data = array(
			'store_id' => $this->input->post('store_id') ?: null,
			'reconciliation_type' => $this->input->post('reconciliation_type'),
			'period_start' => $this->input->post('period_start'),
			'period_end' => $this->input->post('period_end'),
			'created_by' => $this->session->userdata('user_id')
		);

		$result = $this->pharmacy_architecture_model->create_reconciliation($data);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> Reconciliation created and calculated.</div>');
			redirect(base_url() . 'app/pharmacy/reconciliation_details/' . $result['reconciliation_id']);
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to create reconciliation.</div>');
			redirect(base_url() . 'app/pharmacy/reconciliations');
		}
	}

	public function reconciliation_details($reconciliation_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$reconciliation_id = (int)$reconciliation_id;

		$recon = $this->pharmacy_architecture_model->get_reconciliation($reconciliation_id);
		if (!$recon) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Reconciliation not found.</div>');
			redirect(base_url() . 'app/pharmacy/reconciliations');
			return;
		}

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['recon'] = $recon;
		$this->data['items'] = $this->pharmacy_architecture_model->get_reconciliation_items($reconciliation_id);
		$this->data['discrepancies'] = $this->pharmacy_architecture_model->get_reconciliation_discrepancies($reconciliation_id);
		$this->data['recon_statuses'] = $this->pharmacy_architecture_model->get_reconciliation_statuses();
		$this->load->view('app/pharmacy/reconciliation_details', $this->data);
	}

	public function update_actual_cash($reconciliation_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$reconciliation_id = (int)$reconciliation_id;
		$actual_cash = (float)$this->input->post('actual_cash');
		$notes = $this->input->post('notes') ?: '';

		$result = $this->pharmacy_architecture_model->update_actual_cash($reconciliation_id, $actual_cash, $notes);

		if ($result['success']) {
			$variance = $result['variance'];
			$msg = 'Actual cash updated. Variance: GHS ' . number_format($variance, 2);
			if (abs($variance) > 0.01) {
				$msg .= ' (Discrepancy recorded)';
			}
			$this->session->set_flashdata('message', '<div class="alert alert-info">' . $msg . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/reconciliation_details/' . $reconciliation_id);
	}

	public function submit_reconciliation($reconciliation_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$reconciliation_id = (int)$reconciliation_id;
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_architecture_model->submit_reconciliation($reconciliation_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/reconciliation_details/' . $reconciliation_id);
	}

	public function approve_reconciliation($reconciliation_id = '')
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$reconciliation_id = (int)$reconciliation_id;
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_architecture_model->approve_reconciliation($reconciliation_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/reconciliation_details/' . $reconciliation_id);
	}

	public function finalize_reconciliation($reconciliation_id = '')
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$reconciliation_id = (int)$reconciliation_id;
		$user_id = $this->session->userdata('user_id');

		$result = $this->pharmacy_architecture_model->finalize_reconciliation($reconciliation_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/reconciliation_details/' . $reconciliation_id);
	}

	public function resolve_discrepancy($discrepancy_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$discrepancy_id = (int)$discrepancy_id;
		$user_id = $this->session->userdata('user_id');
		$resolution = $this->input->post('resolution');
		$reconciliation_id = (int)$this->input->post('reconciliation_id');

		$result = $this->pharmacy_architecture_model->resolve_discrepancy($discrepancy_id, $user_id, $resolution);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . htmlspecialchars($result['message']) . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to resolve discrepancy.</div>');
		}

		redirect(base_url() . 'app/pharmacy/reconciliation_details/' . $reconciliation_id);
	}

	// =========================================================================
	// NHIS PHARMACY COMPLIANCE (Phase 2)
	// =========================================================================

	public function nhis_drug_mapping()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'nhis_drug_mapping'));

		$filters = array(
			'category' => $this->input->get('category'),
			'search' => $this->input->get('search'),
			'mapped' => $this->input->get('mapped')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['mappings'] = $this->nhis_pharmacy_model->get_all_drug_mappings(array('is_active' => 1));
		$this->data['unmapped_drugs'] = $this->nhis_pharmacy_model->get_unmapped_drugs();
		$this->data['nhis_tariffs'] = $this->nhis_pharmacy_model->get_nhis_drug_tariffs();
		$this->data['categories'] = $this->nhis_pharmacy_model->get_nhis_tariff_categories();
		$this->data['stats'] = $this->nhis_pharmacy_model->get_nhis_pharmacy_stats();
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/nhis_drug_mapping', $this->data);
	}

	public function map_drug_nhis()
	{
		require_role(array('admin', 'pharmacist'));

		$drug_id = (int)$this->input->post('drug_id');
		$nhis_tariff_id = (int)$this->input->post('nhis_tariff_id');
		$user_id = $this->session->userdata('user_id');

		$result = $this->nhis_pharmacy_model->map_drug_to_nhis($drug_id, $nhis_tariff_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> Drug mapped to NHIS tariff successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/nhis_drug_mapping');
	}

	public function unmap_drug_nhis($drug_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$drug_id = (int)$drug_id;

		$this->nhis_pharmacy_model->unmap_drug_from_nhis($drug_id);
		$this->session->set_flashdata('message', '<div class="alert alert-info"><i class="fa fa-unlink"></i> Drug NHIS mapping removed.</div>');

		redirect(base_url() . 'app/pharmacy/nhis_drug_mapping');
	}

	public function nhis_tariffs()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'nhis_tariffs'));

		$filters = array(
			'category' => $this->input->get('category'),
			'search' => $this->input->get('search')
		);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['tariffs'] = $this->nhis_pharmacy_model->get_nhis_drug_tariffs($filters);
		$this->data['categories'] = $this->nhis_pharmacy_model->get_nhis_tariff_categories();
		$this->data['filters'] = $filters;
		$this->load->view('app/pharmacy/nhis_tariffs', $this->data);
	}

	public function search_nhis_tariffs_json()
	{
		$term = $this->input->get_post('term');
		$tariffs = $this->nhis_pharmacy_model->search_nhis_drug_tariffs($term, 20);

		header('Content-Type: application/json');
		echo json_encode($tariffs);
	}

	public function verify_nhis_json()
	{
		$patient_no = $this->input->get_post('patient_no');
		$drug_id = $this->input->get_post('drug_id');

		$result = $this->nhis_pharmacy_model->can_dispense_nhis($patient_no, $drug_id ? (int)$drug_id : null);

		header('Content-Type: application/json');
		echo json_encode($result);
	}

	public function validate_dispense_json()
	{
		$patient_no = $this->input->get_post('patient_no');
		$drug_id = (int)$this->input->get_post('drug_id');
		$quantity = (int)$this->input->get_post('quantity');

		$result = $this->nhis_pharmacy_model->validate_dispense($patient_no, $drug_id, $quantity);

		header('Content-Type: application/json');
		echo json_encode($result);
	}

	public function nhis_claim_stats()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'nhis_claim_stats'));

		$this->data['stats'] = $this->nhis_pharmacy_model->get_nhis_pharmacy_stats();
		$this->data['validation_errors'] = $this->nhis_pharmacy_model->get_validation_error_summary(
			date('Y-m-01'),
			date('Y-m-t')
		);
		$this->load->view('app/pharmacy/nhis_claim_stats', $this->data);
	}

	// =========================================================================
	// NHIS DRUG MAPPING TOOL (Phase 1 Critical Fix)
	// =========================================================================

	/**
	 * NHIS Drug Mapping Tool - Main View
	 */
	public function nhis_mapping_tool()
	{
		require_role(array('admin', 'pharmacist'));
		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'nhis_mapping_tool'));

		$filters = array(
			'search' => $this->input->get('search'),
			'category_id' => $this->input->get('category_id'),
			'view' => $this->input->get('view') ?: 'unmapped'
		);
		$page = max(1, (int)$this->input->get('page'));
		$limit = 50;
		$offset = ($page - 1) * $limit;

		if ($filters['view'] === 'mapped') {
			$this->data['drugs'] = $this->nhis_pharmacy_model->get_mapped_drugs_paginated($filters, $limit, $offset);
			$this->data['total_count'] = $this->nhis_pharmacy_model->count_mapped_drugs($filters);
		} else {
			$this->data['drugs'] = $this->nhis_pharmacy_model->get_unmapped_drugs_paginated($filters, $limit, $offset);
			$this->data['total_count'] = $this->nhis_pharmacy_model->count_unmapped_drugs($filters);
		}

		$this->data['filters'] = $filters;
		$this->data['page'] = $page;
		$this->data['limit'] = $limit;
		$this->data['total_pages'] = ceil($this->data['total_count'] / $limit);
		$this->data['categories'] = $this->nhis_pharmacy_model->get_drug_categories();
		$this->data['nhis_tariffs'] = $this->nhis_pharmacy_model->get_nhis_drug_tariffs();
		$this->data['stats'] = $this->nhis_pharmacy_model->get_nhis_pharmacy_stats();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view('app/pharmacy/nhis_mapping_tool', $this->data);
	}

	/**
	 * Save single NHIS drug mapping
	 */
	public function save_nhis_mapping()
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$drug_id = (int)$this->input->post('drug_id');
		$tariff_id = (int)$this->input->post('tariff_id');
		$user_id = $this->session->userdata('user_id');

		if ($drug_id <= 0 || $tariff_id <= 0) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-times"></i> Invalid drug or tariff selection.</div>');
			redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
			return;
		}

		$result = $this->nhis_pharmacy_model->map_drug_to_nhis($drug_id, $tariff_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> Drug successfully mapped to NHIS tariff.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-times"></i> ' . htmlspecialchars($result['error']) . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
	}

	/**
	 * Bulk save NHIS drug mappings
	 */
	public function bulk_nhis_mapping()
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$mappings_json = $this->input->post('mappings');
		$user_id = $this->session->userdata('user_id');

		if (empty($mappings_json)) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning"><i class="fa fa-exclamation"></i> No mappings provided.</div>');
			redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
			return;
		}

		$mappings = json_decode($mappings_json, true);
		if (!is_array($mappings) || empty($mappings)) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning"><i class="fa fa-exclamation"></i> Invalid mappings data.</div>');
			redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
			return;
		}

		$result = $this->nhis_pharmacy_model->bulk_save_mapping($mappings, $user_id);

		$msg = sprintf(
			'<div class="alert alert-info"><i class="fa fa-info-circle"></i> Bulk mapping complete: %d successful, %d failed.</div>',
			$result['success'],
			$result['failed']
		);
		$this->session->set_flashdata('message', $msg);

		redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
	}

	/**
	 * Auto-suggest NHIS mappings (AJAX)
	 */
	public function auto_suggest_mapping()
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			header('Content-Type: application/json');
			echo json_encode(array('success' => false, 'matches' => array(), 'count' => 0));
			return;
		}

		$drug_ids = $this->input->post('drug_ids');
		if (!empty($drug_ids) && is_string($drug_ids)) {
			$drug_ids = json_decode($drug_ids, true);
		}

		$matches = $this->nhis_pharmacy_model->auto_match_drugs($drug_ids ?: array());

		header('Content-Type: application/json');
		echo json_encode(array(
			'success' => true,
			'matches' => $matches,
			'count' => count($matches)
		));
	}

	/**
	 * Apply auto-suggested mappings
	 */
	public function apply_auto_mapping()
	{
		require_role(array('admin', 'pharmacist'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$user_id = $this->session->userdata('user_id');
		$matches = $this->nhis_pharmacy_model->auto_match_drugs();

		$mappings = array();
		foreach ($matches as $match) {
			$mappings[] = array(
				'drug_id' => $match['drug_id'],
				'tariff_id' => $match['suggested_tariff_id']
			);
		}

		if (empty($mappings)) {
			$this->session->set_flashdata('message', '<div class="alert alert-info"><i class="fa fa-info-circle"></i> No auto-matches found.</div>');
			redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
			return;
		}

		$result = $this->nhis_pharmacy_model->bulk_save_mapping($mappings, $user_id);

		$msg = sprintf(
			'<div class="alert alert-success"><i class="fa fa-magic"></i> Auto-mapping complete: %d drugs mapped, %d failed.</div>',
			$result['success'],
			$result['failed']
		);
		$this->session->set_flashdata('message', $msg);

		redirect(base_url() . 'app/pharmacy/nhis_mapping_tool');
	}

	/**
	 * Export unmapped drugs as CSV
	 */
	public function export_unmapped_csv()
	{
		require_role(array('admin', 'pharmacist'));

		$csv_data = $this->nhis_pharmacy_model->export_unmapped_drugs_csv();

		header('Content-Type: text/csv');
		header('Content-Disposition: attachment; filename="unmapped_drugs_' . date('Y-m-d') . '.csv"');
		header('Pragma: no-cache');
		header('Expires: 0');

		echo $csv_data;
		exit;
	}

	// =========================================================================
	// PHASE 6A — RX-Level Queue AJAX Endpoint
	// GET  app/pharmacy/rx_queue_json
	// Params: filter, search, date_from, date_to, limit, offset, page
	// =========================================================================

	public function rx_queue_json()
	{
		header('Content-Type: application/json');

		$filters = array(
			'filter'    => $this->input->get_post('filter')    ?: 'ALL',
			'search'    => $this->input->get_post('search')    ?: '',
			'date_from' => $this->input->get_post('date_from') ?: date('Y-m-d'),
			'date_to'   => $this->input->get_post('date_to')   ?: date('Y-m-d'),
			'limit'     => min((int)($this->input->get_post('limit') ?: 50), 200),
			'offset'    => max((int)($this->input->get_post('offset') ?: 0), 0),
		);

		$rows  = $this->pharmacy_model->get_rx_queue($filters);
		$total = $this->pharmacy_model->count_rx_queue($filters);

		/* Convert objects to arrays for JSON */
		$data = array();
		foreach ($rows as $r) {
			$data[] = array(
				'iop_med_id'      => (int)$r->iop_med_id,
				'prescription_no' => (string)$r->prescription_no,
				'iop_id'          => (string)$r->iop_id,
				'patient_name'    => (string)$r->patient_name,
				'patient_no'      => (string)$r->patient_no,
				'doctor_id'       => (string)$r->doctor_id,
				'visit_type'      => (string)$r->visit_type,
				'drug_name'       => (string)$r->drug_name,
				'generic_name'    => (string)$r->generic_name,
				'strength'        => (string)$r->strength,
				'dosage'          => (string)$r->dosage,
				'unit'            => (string)$r->unit,
				'route'           => (string)$r->route,
				'drug_form'       => (string)$r->drug_form,
				'freq_code'       => (string)$r->freq_code,
				'days'            => (int)$r->days,
				'total_qty'       => (int)$r->total_qty,
				'is_urgent'       => (int)$r->is_urgent,
				'is_prn'          => (int)$r->is_prn,
				'dispensing_status'=> (string)$r->dispensing_status,
				'payer_type'      => (string)$r->payer_type,
				'priority'        => (string)$r->priority,
				'priority_class'  => (string)$r->priority_class,
				'stock_qty'       => (float)$r->stock_qty,
				'stock_alert'     => (string)$r->stock_alert,
				'prescribed_at'   => (string)$r->prescribed_at,
				'date_visit'      => (string)$r->date_visit,
				'payment_status'  => (string)$r->payment_status,
			);
		}

		echo json_encode(array(
			'success'    => true,
			'total'      => $total,
			'count'      => count($data),
			'filter'     => strtoupper($filters['filter']),
			'date_from'  => $filters['date_from'],
			'date_to'    => $filters['date_to'],
			'rows'       => $data,
		));
	}

	// =========================================================================
	// DEPRECATED WORKLIST VIEWS (Phase 1 - Safe Deprecation)
	// =========================================================================

	/**
	 * Legacy worklist redirect (deprecated)
	 */
	public function worklist_legacy()
	{
		// Log deprecated access
		$user_id = $this->session->userdata('user_id');
		$this->nhis_pharmacy_model->log_deprecated_view_access('worklist.php', $user_id);

		// Show deprecation notice then redirect
		$this->data['deprecated_view'] = 'worklist.php';
		$this->data['redirect_url'] = base_url() . 'app/pharmacy';
		$this->load->view('app/pharmacy/deprecated_redirect', $this->data);
	}

	/**
	 * Legacy worklist v2 redirect (deprecated)
	 */
	public function worklist_v2_legacy()
	{
		// Log deprecated access
		$user_id = $this->session->userdata('user_id');
		$this->nhis_pharmacy_model->log_deprecated_view_access('worklist_v2.php', $user_id);

		// Show deprecation notice then redirect
		$this->data['deprecated_view'] = 'worklist_v2.php';
		$this->data['redirect_url'] = base_url() . 'app/pharmacy';
		$this->load->view('app/pharmacy/deprecated_redirect', $this->data);
	}

	// =========================================================================
	// PHARMACY RETURNS (Phase 2 - Clinical Safety)
	// =========================================================================

	/**
	 * Pharmacy returns list
	 */
	public function pharmacy_returns()
	{
		require_role(array('pharmacist', 'admin'));

		$this->load->model('app/pharmacy_returns_model');

		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_returns',
			'subtab' => '',
			'submodule' => ''
		));

		$filters = array(
			'search'      => (string)$this->input->get('search'),
			'status'      => (string)$this->input->get('status'),
			'return_type' => (string)$this->input->get('return_type'),
			'date_from'   => (string)$this->input->get('date_from'),
			'date_to'     => (string)$this->input->get('date_to')
		);

		$this->data['filters'] = $filters;
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['returns'] = $this->pharmacy_returns_model->get_returns_list($filters);
		$this->data['summary'] = $this->pharmacy_returns_model->get_returns_summary();

		$this->load->view('app/pharmacy/pharmacy_returns', $this->data);
	}

	/**
	 * Create return form
	 * Phase 2 Hardening: Added return_types, stock_locations, return_window_hours
	 */
	public function create_return($admin_id = '')
	{
		require_role(array('pharmacist', 'admin'));

		$this->load->model('app/pharmacy_returns_model');

		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_returns',
			'subtab' => '',
			'submodule' => ''
		));

		$search = (string)$this->input->get('search');

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['dispensed_items'] = $this->pharmacy_returns_model->get_dispensed_drugs_for_return('', $search);
		$this->data['return_reasons'] = $this->pharmacy_returns_model->get_return_reasons();
		$this->data['return_types'] = $this->pharmacy_returns_model->get_return_types();
		$this->data['stock_locations'] = $this->pharmacy_returns_model->get_stock_locations();
		$this->data['return_window_hours'] = $this->pharmacy_returns_model->get_return_window_hours();

		$this->load->view('app/pharmacy/return_create', $this->data);
	}

	/**
	 * Save return request
	 * Phase 2 Hardening: Added return_type, batch_no, stock_location, user_role
	 */
	public function save_return()
	{
		require_role(array('pharmacist', 'admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->load->model('app/pharmacy_returns_model');

		$admin_id = (int)$this->input->post('admin_id');
		$quantity_returned = (float)$this->input->post('quantity_returned');
		$return_reason = trim($this->input->post('return_reason'));
		$return_notes = trim($this->input->post('return_notes'));
		$return_type = trim($this->input->post('return_type'));
		$batch_no = trim($this->input->post('batch_no'));
		$stock_location = trim($this->input->post('stock_location'));

		if ($admin_id <= 0) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Invalid dispense record selected</div>');
			redirect(base_url() . 'app/pharmacy/create_return');
			return;
		}

		if ($quantity_returned <= 0) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Return quantity must be greater than 0</div>');
			redirect(base_url() . 'app/pharmacy/create_return');
			return;
		}

		if (empty($return_reason)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Return reason is required</div>');
			redirect(base_url() . 'app/pharmacy/create_return');
			return;
		}

		$result = $this->pharmacy_returns_model->save_return_request(array(
			'admin_id'          => $admin_id,
			'quantity_returned' => $quantity_returned,
			'return_reason'     => $return_reason,
			'return_notes'      => $return_notes,
			'return_type'       => $return_type,
			'batch_no'          => $batch_no,
			'stock_location'    => $stock_location,
			'user_id'           => $this->session->userdata('user_id'),
			'user_role'         => $this->session->userdata('role')
		));

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Return request ' . $result['return_number'] . ' created successfully</div>');
			redirect(base_url() . 'app/pharmacy/view_return/' . $result['return_id']);
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . $result['error'] . '</div>');
			redirect(base_url() . 'app/pharmacy/create_return');
		}
	}

	/**
	 * View return details
	 */
	public function view_return($return_id = '')
	{
		require_role(array('pharmacist', 'admin'));

		$this->load->model('app/pharmacy_returns_model');

		$return_id = (int)$return_id;
		if ($return_id <= 0) {
			redirect(base_url() . 'app/pharmacy/pharmacy_returns');
			return;
		}

		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_returns',
			'subtab' => '',
			'submodule' => ''
		));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['return'] = $this->pharmacy_returns_model->get_return_detail($return_id);
		$this->data['audit_trail'] = $this->pharmacy_returns_model->get_return_audit($return_id);

		$this->load->view('app/pharmacy/return_view', $this->data);
	}

	/**
	 * Approve return request
	 */
	public function approve_return()
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->load->model('app/pharmacy_returns_model');

		$return_id = (int)$this->input->post('return_id');
		if ($return_id <= 0) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Invalid return ID</div>');
			redirect(base_url() . 'app/pharmacy/pharmacy_returns');
			return;
		}

		$user_id = $this->session->userdata('user_id');
		$result = $this->pharmacy_returns_model->approve_return_request($return_id, $user_id);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . $result['message'] . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . $result['error'] . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/view_return/' . $return_id);
	}

	/**
	 * Reject return request
	 */
	public function reject_return()
	{
		require_role(array('admin'));
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->load->model('app/pharmacy_returns_model');

		$return_id = (int)$this->input->post('return_id');
		$rejection_reason = trim($this->input->post('rejection_reason'));

		if ($return_id <= 0) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Invalid return ID</div>');
			redirect(base_url() . 'app/pharmacy/pharmacy_returns');
			return;
		}

		if (empty($rejection_reason)) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Rejection reason is required</div>');
			redirect(base_url() . 'app/pharmacy/view_return/' . $return_id);
			return;
		}

		$user_id = $this->session->userdata('user_id');
		$result = $this->pharmacy_returns_model->reject_return_request($return_id, $user_id, $rejection_reason);

		if ($result['success']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">' . $result['message'] . '</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . $result['error'] . '</div>');
		}

		redirect(base_url() . 'app/pharmacy/view_return/' . $return_id);
	}

	public function daily_reconciliation()
	{
		require_role(array('admin', 'pharmacist'));
		$this->load->model('app/pharmacy_reconciliation_model');

		$date = $this->input->get('date') ?: date('Y-m-d', strtotime('-1 day'));
		$status = $this->input->get('status') ?: '';
		$run = $this->input->get('run');

		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'daily_reconciliation'));

		if ((string)$run === '1') {
			if ($this->input->method(TRUE) !== 'POST') {
				redirect(base_url() . 'access_denied');
				return;
			}
			$date_post = (string)$this->input->post('date');
			if ($date_post !== '') {
				$date = $date_post;
			}
			$status_post = (string)$this->input->post('status');
			if ($status_post !== '') {
				$status = $status_post;
			}
			$res = $this->pharmacy_reconciliation_model->reconcile_day($date);
			if (is_array($res) && !empty($res['ok'])) {
				$this->session->set_flashdata('message', '<div class="alert alert-success"><i class="fa fa-check"></i> Reconciliation generated for ' . htmlspecialchars($res['date']) . '.</div>');
			} else {
				$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'Unknown error';
				$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to generate reconciliation: ' . htmlspecialchars($err) . '</div>');
			}
			redirect(base_url() . 'app/pharmacy/daily_reconciliation?date=' . urlencode($date) . '&status=' . urlencode($status));
			return;
		}

		$rows = $this->pharmacy_reconciliation_model->get_day_report($date, $status);
		$critical = 0;
		$warning = 0;
		$okCount = 0;
		foreach ($rows as $r) {
			$st = isset($r->status) ? strtoupper((string)$r->status) : '';
			if ($st === 'CRITICAL') $critical++;
			elseif ($st === 'WARNING') $warning++;
			else $okCount++;
		}

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['date'] = $date;
		$this->data['status'] = $status;
		$this->data['rows'] = $rows;
		$this->data['critical'] = $critical;
		$this->data['warning'] = $warning;
		$this->data['ok_count'] = $okCount;
		$this->load->view('app/pharmacy/daily_reconciliation', $this->data);
	}

	public function daily_reconciliation_drug($drug_id = '')
	{
		require_role(array('admin', 'pharmacist'));
		$this->load->model('app/pharmacy_reconciliation_model');

		$drug_id = (int)$drug_id;
		$date = $this->input->get('date') ?: date('Y-m-d', strtotime('-1 day'));
		if ($drug_id <= 0) {
			redirect(base_url() . 'app/pharmacy/daily_reconciliation?date=' . urlencode($date));
			return;
		}

		$this->session->set_userdata(array('tab' => 'pharmacy', 'module' => 'daily_reconciliation'));

		$summary = $this->pharmacy_reconciliation_model->get_day_drug_summary($date, $drug_id);
		$dispenses = $this->pharmacy_reconciliation_model->get_drug_day_dispenses($date, $drug_id);
		$billings = $this->pharmacy_reconciliation_model->get_drug_day_billings($date, $drug_id);
		$adjustments = $this->pharmacy_reconciliation_model->get_drug_day_adjustments($date, $drug_id);

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['date'] = $date;
		$this->data['drug_id'] = $drug_id;
		$this->data['summary'] = $summary;
		$this->data['dispenses'] = $dispenses;
		$this->data['billings'] = $billings;
		$this->data['adjustments'] = $adjustments;
		$this->load->view('app/pharmacy/daily_reconciliation_drug', $this->data);
	}

	/**
	 * Data integrity check and fix for pharmacy module.
	 * Admin only - validates and fixes common data discrepancies.
	 */
	public function data_integrity_check()
	{
		require_role('admin');

		$result = $this->pharmacy_model->validate_and_fix_pharmacy_data();

		$this->session->set_userdata(array(
			'tab' => 'pharmacy',
			'module' => 'pharmacy_admin',
			'subtab' => 'integrity',
			'submodule' => ''
		));

		$this->data['title'] = 'Pharmacy Data Integrity Check';
		$this->data['issues'] = $result['issues'];
		$this->data['fixed'] = $result['fixed'];
		$this->data['message'] = $this->session->flashdata('message');

		// Simple inline view for results
		$html = '<!DOCTYPE html><html><head><title>Pharmacy Data Integrity Check</title>';
		$html .= '<link href="'.base_url().'public/css/bootstrap.min.css" rel="stylesheet">';
		$html .= '<link href="'.base_url().'public/css/AdminLTE.css" rel="stylesheet">';
		$html .= '</head><body class="skin-blue">';
		$html .= '<?php require_once(APPPATH."views/include/header.php");?>';
		
		echo '<!DOCTYPE html><html><head><title>Pharmacy Data Integrity Check</title>';
		echo '<link href="'.base_url().'public/css/bootstrap.min.css" rel="stylesheet">';
		echo '<link href="'.base_url().'public/css/AdminLTE.css" rel="stylesheet">';
		echo '</head><body class="skin-blue">';
		require_once(APPPATH.'views/include/header.php');
		echo '<div class="wrapper row-offcanvas row-offcanvas-left">';
		require_once(APPPATH.'views/include/sidebar.php');
		echo '<aside class="right-side"><section class="content-header"><h1><i class="fa fa-check-circle"></i> Pharmacy Data Integrity Check</h1></section>';
		echo '<section class="content">';
		
		$issueCount = count($result['issues']);
		$fixedCount = count($result['fixed']);
		
		if ($issueCount === 0) {
			echo '<div class="alert alert-success"><i class="fa fa-check"></i> <strong>All Clear!</strong> No data integrity issues found.</div>';
		} else {
			echo '<div class="alert alert-warning"><i class="fa fa-warning"></i> Found <strong>'.$issueCount.'</strong> issue(s), fixed <strong>'.$fixedCount.'</strong>.</div>';
			
			if (!empty($result['issues'])) {
				echo '<div class="box box-warning"><div class="box-header"><h3 class="box-title">Issues Detected</h3></div>';
				echo '<div class="box-body"><ul class="list-unstyled">';
				foreach ($result['issues'] as $issue) {
					echo '<li><i class="fa fa-exclamation-triangle text-warning"></i> '.htmlspecialchars($issue).'</li>';
				}
				echo '</ul></div></div>';
			}
			
			if (!empty($result['fixed'])) {
				echo '<div class="box box-success"><div class="box-header"><h3 class="box-title">Fixes Applied</h3></div>';
				echo '<div class="box-body"><ul class="list-unstyled">';
				foreach ($result['fixed'] as $fix) {
					echo '<li><i class="fa fa-check text-success"></i> '.htmlspecialchars($fix).'</li>';
				}
				echo '</ul></div></div>';
			}
		}
		
		echo '<a href="'.base_url().'app/pharmacy" class="btn btn-primary"><i class="fa fa-arrow-left"></i> Back to Pharmacy</a>';
		echo ' <a href="'.base_url().'app/pharmacy/data_integrity_check" class="btn btn-default"><i class="fa fa-refresh"></i> Run Again</a>';
		echo '</section></aside></div></body></html>';
	}
}
