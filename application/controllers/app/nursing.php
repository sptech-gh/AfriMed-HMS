<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Nursing extends General{

	public function __construct(){
		parent::__construct();
		$this->load->model('app/nursing_dashboard_model');
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		require_role(array('nurse', 'doctor', 'admin'));
	}

	public function index(){
		$this->dashboard();
	}

	public function dashboard(){
		if (!$this->guard_nursing_access()) return;
		$this->session->set_userdata(array(
			'tab' => 'nurse_module',
			'module' => 'nursing_dashboard',
			'subtab' => '',
			'submodule' => ''
		));
		$payload = $this->nursing_dashboard_model->get_dashboard_payload(
			$this->input->get('ward_id'),
			$this->input->get('shift'),
			$this->input->get('date')
		);
		$this->data['dashboard_payload'] = $payload;
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/nursing/dashboard', $this->data);
	}

	public function patient($id = ''){
		if (!$this->guard_nursing_access()) return;
		$this->session->set_userdata(array(
			'tab' => 'nurse_module',
			'module' => 'nursing_dashboard',
			'subtab' => '',
			'submodule' => ''
		));
		$summary = $this->nursing_dashboard_model->get_patient_summary($id);
		if (!$summary) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-warning'></i> Patient was not found or is not active.</div>");
			redirect(base_url().'app/nursing/dashboard');
			return;
		}
		$this->data['patient_summary'] = $summary;
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/nursing/patient_preview', $this->data);
	}

	public function workspace($id = ''){
		if (!$this->guard_nursing_access()) return;
		$this->session->set_userdata(array(
			'tab' => 'nurse_module',
			'module' => 'nursing_dashboard',
			'subtab' => '',
			'submodule' => ''
		));
		$workspace = $this->nursing_dashboard_model->get_patient_workspace_payload($id);
		if (!$workspace) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-warning'></i> Patient was not found or is not active.</div>");
			redirect(base_url().'app/nursing/dashboard');
			return;
		}
		$this->data['workspace_payload'] = $workspace;
		$this->data['patient_summary'] = $workspace;
		$this->config->load('nursing_workspace', true);
		$this->data['nursing_workspace_write_mode'] = (bool)$this->config->item('nursing_workspace_write_mode', 'nursing_workspace');
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/nursing/workspace_placeholder', $this->data);
	}

	public function api_dashboard(){
		if (!$this->api_guard()) return;
		$payload = $this->nursing_dashboard_model->get_dashboard_payload(
			$this->input->get('ward_id'),
			$this->input->get('shift'),
			$this->input->get('date')
		);
		if (isset($payload['patients']) && count($payload['patients']) === 0) {
			$payload['code'] = 'WARD_EMPTY';
		}
		$this->json_response($payload);
	}

	public function api_patients(){
		if (!$this->api_guard()) return;
		$patients = $this->nursing_dashboard_model->get_active_patients(
			$this->input->get('ward_id'),
			$this->input->get('search'),
			$this->input->get('priority')
		);
		$this->json_response(array(
			'status' => 'ok',
			'patients' => $patients,
			'meta' => array('count' => count($patients), 'partial' => false, 'warnings' => array())
		));
	}

	public function api_patient_summary($id = ''){
		if (!$this->api_guard()) return;
		$summary = $this->nursing_dashboard_model->get_patient_summary($id);
		if (!$summary) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_NOT_FOUND',
				'message' => 'Patient was not found or is not active in the selected ward.'
			), 404);
			return;
		}
		$this->json_response($summary);
	}

	public function api_save_vitalSign($id = ''){
		if (!$this->api_guard()) return;
		if (strtolower((string)$this->input->method()) !== 'post') {
			$this->json_response(array('status' => 'error', 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed.'), 405);
			return;
		}

		$summary = $this->nursing_dashboard_model->get_patient_summary($id);
		if (!$summary || !isset($summary['patient'])) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_NOT_FOUND',
				'message' => 'Patient was not found or is not active.'
			), 404);
			return;
		}

		$patient = $summary['patient'];
		$iopId = isset($patient['encounter_id']) ? trim((string)$patient['encounter_id']) : '';
		$patientNo = isset($patient['patient_no']) ? trim((string)$patient['patient_no']) : '';
		$actorUserId = trim((string)$this->session->userdata('user_id'));

		// Block writes on clinically cleared encounters
		if ($iopId !== '') {
			$_visit = $this->db->select('clinical_clearance_status')
				->get_where('patient_details_iop', ['IO_ID' => $iopId, 'InActive' => 0])->row();
			if ($_visit && isset($_visit->clinical_clearance_status) && (int)$_visit->clinical_clearance_status === 1) {
				log_message('error', '[NURSE_API_ENCOUNTER_LOCKED] endpoint=api_save_vitalSign iop=' . $iopId . ' user=' . $actorUserId);
				$this->json_response(array(
					'status' => 'error',
					'code' => 'ENCOUNTER_CLOSED',
					'message' => 'This encounter is clinically cleared. No further modifications are allowed.',
					'errors' => array(array('field' => 'encounter_id', 'code' => 'ENCOUNTER_CLOSED', 'message' => 'Encounter locked.')),
					'warnings' => array()
				), 403);
				return;
			}
		}

		$raw = (string)$this->input->raw_input_stream;
		$decoded = json_decode($raw, true);
		$post = $this->input->post(NULL, true);
		if (!is_array($post)) {
			$post = array();
		}
		$body = is_array($decoded) ? array_merge($post, $decoded) : $post;

		$idempotencyKey = '';
		if (isset($body['idempotency_key'])) {
			$idempotencyKey = trim((string)$body['idempotency_key']);
		}
		if ($idempotencyKey === '' && isset($_SERVER['HTTP_X_IDEMPOTENCY_KEY'])) {
			$idempotencyKey = trim((string)$_SERVER['HTTP_X_IDEMPOTENCY_KEY']);
		}
		if ($idempotencyKey === '' && isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
			$idempotencyKey = trim((string)$_SERVER['HTTP_IDEMPOTENCY_KEY']);
		}
		if ($idempotencyKey === '') {
			$this->json_response(array(
				'status' => 'validation_error',
				'code' => 'IDEMPOTENCY_KEY_REQUIRED',
				'message' => 'Missing idempotency key.',
				'errors' => array(array('field' => 'idempotency_key', 'code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'Missing idempotency key.')),
				'warnings' => array()
			), 400);
			return;
		}

		if (isset($body['patient_no']) && trim((string)$body['patient_no']) !== '' && trim((string)$body['patient_no']) !== $patientNo) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_CONTEXT_MISMATCH',
				'message' => 'Patient context mismatch.',
				'errors' => array(array('field' => 'patient_no', 'code' => 'PATIENT_CONTEXT_MISMATCH', 'message' => 'Patient context mismatch.')),
				'warnings' => array()
			), 409);
			return;
		}

		$this->load->model('app/nurse_enhancement_model');
		$adapterPath = APPPATH . 'services/Nursing/NursingVitalsAdapter.php';
		if (!file_exists($adapterPath)) {
			$this->json_response(array('status' => 'error', 'code' => 'ADAPTER_MISSING', 'message' => 'Vitals adapter is unavailable.'), 500);
			return;
		}
		require_once($adapterPath);

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			$this->json_response(array('status' => 'error', 'code' => 'CTM_FACTORY_MISSING', 'message' => 'Clinical transaction dependencies are unavailable.'), 500);
			return;
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		$this->config->load('clinical_runtime', true);
		$leaseSeconds = (int)$this->config->item('clinical_ctm_lease_seconds', 'clinical_runtime');
		$prefix = (string)$this->config->item('clinical_ctm_lease_owner_prefix', 'clinical_runtime');
		if ($prefix === '') {
			$prefix = 'hms-clinical-web';
		}
		$leaseOwner = $prefix . ':' . getmypid();

		try {
			$transactions = new CiClinicalTransactionManager($this->db, $leaseSeconds > 0 ? $leaseSeconds : 60, $leaseOwner);
			$adapter = new NursingVitalsAdapter($this->db, $this->nurse_enhancement_model);
			$result = $adapter->record($iopId, $patientNo, $actorUserId, $idempotencyKey, $body, $transactions);
		} catch (Exception $e) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'VITALS_SAVE_FAILED',
				'message' => $e->getMessage(),
				'errors' => array(),
				'warnings' => array()
			), 500);
			return;
		}

		if (!is_array($result)) {
			$this->json_response(array('status' => 'error', 'code' => 'VITALS_SAVE_FAILED', 'message' => 'Unexpected result.', 'errors' => array(), 'warnings' => array()), 500);
			return;
		}
		if (isset($result['status']) && $result['status'] === 'validation_error') {
			$this->json_response($result, 422);
			return;
		}
		if (!isset($result['status']) || $result['status'] !== 'success') {
			$this->json_response($result, 500);
			return;
		}

		$updated = $this->nursing_dashboard_model->get_patient_summary($iopId);
		$result['patient_summary'] = $updated ? $updated : $summary;
		if (!isset($result['errors'])) {
			$result['errors'] = array();
		}
		if (!isset($result['warnings'])) {
			$result['warnings'] = array();
		}
		$this->json_response($result, 200);
	}

	public function api_save_procedure($id = ''){
		if (!$this->api_guard()) return;
		if (strtolower((string)$this->input->method()) !== 'post') {
			$this->json_response(array('status' => 'error', 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed.'), 405);
			return;
		}

		$summary = $this->nursing_dashboard_model->get_patient_summary($id);
		if (!$summary || !isset($summary['patient'])) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_NOT_FOUND',
				'message' => 'Patient was not found or is not active.'
			), 404);
			return;
		}

		$patient = $summary['patient'];
		$iopId = isset($patient['encounter_id']) ? trim((string)$patient['encounter_id']) : '';
		$patientNo = isset($patient['patient_no']) ? trim((string)$patient['patient_no']) : '';
		$actorUserId = trim((string)$this->session->userdata('user_id'));

		// Block writes on clinically cleared encounters
		if ($iopId !== '') {
			$_visit = $this->db->select('clinical_clearance_status')
				->get_where('patient_details_iop', ['IO_ID' => $iopId, 'InActive' => 0])->row();
			if ($_visit && isset($_visit->clinical_clearance_status) && (int)$_visit->clinical_clearance_status === 1) {
				log_message('error', '[NURSE_API_ENCOUNTER_LOCKED] endpoint=api_save_procedure iop=' . $iopId . ' user=' . $actorUserId);
				$this->json_response(array(
					'status' => 'error',
					'code' => 'ENCOUNTER_CLOSED',
					'message' => 'This encounter is clinically cleared. No further modifications are allowed.',
					'errors' => array(array('field' => 'encounter_id', 'code' => 'ENCOUNTER_CLOSED', 'message' => 'Encounter locked.')),
					'warnings' => array(),
					'governance_observed' => true
				), 403);
				return;
			}
		}

		$raw = (string)$this->input->raw_input_stream;
		$decoded = json_decode($raw, true);
		$post = $this->input->post(NULL, true);
		if (!is_array($post)) {
			$post = array();
		}
		$body = is_array($decoded) ? array_merge($post, $decoded) : $post;

		$idempotencyKey = '';
		if (isset($body['idempotency_key'])) {
			$idempotencyKey = trim((string)$body['idempotency_key']);
		}
		if ($idempotencyKey === '' && isset($_SERVER['HTTP_X_IDEMPOTENCY_KEY'])) {
			$idempotencyKey = trim((string)$_SERVER['HTTP_X_IDEMPOTENCY_KEY']);
		}
		if ($idempotencyKey === '' && isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
			$idempotencyKey = trim((string)$_SERVER['HTTP_IDEMPOTENCY_KEY']);
		}
		if ($idempotencyKey === '') {
			$this->json_response(array(
				'status' => 'validation_error',
				'code' => 'IDEMPOTENCY_KEY_REQUIRED',
				'message' => 'Missing idempotency key.',
				'errors' => array(array('field' => 'idempotency_key', 'code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'Missing idempotency key.')),
				'warnings' => array(),
				'governance_observed' => true
			), 400);
			return;
		}

		if (isset($body['patient_no']) && trim((string)$body['patient_no']) !== '' && trim((string)$body['patient_no']) !== $patientNo) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_CONTEXT_MISMATCH',
				'message' => 'Patient context mismatch.',
				'errors' => array(array('field' => 'patient_no', 'code' => 'PATIENT_CONTEXT_MISMATCH', 'message' => 'Patient context mismatch.')),
				'warnings' => array(),
				'governance_observed' => true
			), 409);
			return;
		}
		if (isset($body['encounter_id']) && trim((string)$body['encounter_id']) !== '' && trim((string)$body['encounter_id']) !== $iopId) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'ENCOUNTER_CONTEXT_MISMATCH',
				'message' => 'Encounter context mismatch.',
				'errors' => array(array('field' => 'encounter_id', 'code' => 'ENCOUNTER_CONTEXT_MISMATCH', 'message' => 'Encounter context mismatch.')),
				'warnings' => array(),
				'governance_observed' => true
			), 409);
			return;
		}

		if (isset($body['simulate_failure']) && trim((string)$body['simulate_failure']) !== '') {
			$sf = trim((string)$body['simulate_failure']);
			$allowed = array('after_insert', 'after_projection');
			if (!in_array($sf, $allowed, true)) {
				$this->json_response(array(
					'status' => 'validation_error',
					'code' => 'SIMULATE_FAILURE_INVALID',
					'message' => 'simulate_failure invalid.',
					'errors' => array(array('field' => 'simulate_failure', 'code' => 'SIMULATE_FAILURE_INVALID', 'message' => 'simulate_failure invalid.')),
					'warnings' => array(),
					'governance_observed' => true
				), 422);
				return;
			}
			$this->config->load('clinical_runtime', true);
			$failureEnabled = (bool)$this->config->item('clinical_failure_injection_enabled', 'clinical_runtime');
			if ((defined('ENVIRONMENT') && ENVIRONMENT === 'production') || !$failureEnabled) {
				$this->json_response(array(
					'status' => 'error',
					'code' => 'SIMULATE_FAILURE_NOT_ALLOWED',
					'message' => 'simulate_failure not allowed.',
					'errors' => array(array('field' => 'simulate_failure', 'code' => 'SIMULATE_FAILURE_NOT_ALLOWED', 'message' => 'simulate_failure not allowed.')),
					'warnings' => array(),
					'governance_observed' => true
				), 403);
				return;
			}
		}

		$this->load->model('app/billing_transaction_model');
		$adapterPath = APPPATH . 'services/Nursing/NursingProcedureAdapter.php';
		if (!file_exists($adapterPath)) {
			$this->json_response(array('status' => 'error', 'code' => 'ADAPTER_MISSING', 'message' => 'Procedure adapter is unavailable.', 'governance_observed' => true), 500);
			return;
		}
		require_once($adapterPath);

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			$this->json_response(array('status' => 'error', 'code' => 'CTM_FACTORY_MISSING', 'message' => 'Clinical transaction dependencies are unavailable.', 'governance_observed' => true), 500);
			return;
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		$this->config->load('clinical_runtime', true);
		$leaseSeconds = (int)$this->config->item('clinical_ctm_lease_seconds', 'clinical_runtime');
		$prefix = (string)$this->config->item('clinical_ctm_lease_owner_prefix', 'clinical_runtime');
		if ($prefix === '') {
			$prefix = 'hms-clinical-web';
		}
		$leaseOwner = $prefix . ':' . getmypid();

		try {
			$transactions = new CiClinicalTransactionManager($this->db, $leaseSeconds > 0 ? $leaseSeconds : 60, $leaseOwner);
			$adapter = new NursingProcedureAdapter($this->db, $this->billing_transaction_model);
			$result = $adapter->record($iopId, $patientNo, $actorUserId, $idempotencyKey, $body, $transactions);
		} catch (InvalidArgumentException $e) {
			$code = strtoupper($e->getMessage());
			$this->json_response(array(
				'status' => 'validation_error',
				'code' => $code,
				'message' => $e->getMessage(),
				'errors' => array(array('field' => 'procedure', 'code' => $code, 'message' => $e->getMessage())),
				'warnings' => array(),
				'governance_observed' => true
			), 422);
			return;
		} catch (Exception $e) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PROCEDURE_SAVE_FAILED',
				'message' => $e->getMessage(),
				'errors' => array(),
				'warnings' => array(),
				'governance_observed' => true
			), 500);
			return;
		}

		if (!is_array($result)) {
			$this->json_response(array('status' => 'error', 'code' => 'PROCEDURE_SAVE_FAILED', 'message' => 'Unexpected result.', 'errors' => array(), 'warnings' => array(), 'governance_observed' => true), 500);
			return;
		}
		if (!isset($result['status']) || $result['status'] !== 'success') {
			$this->json_response($result, 500);
			return;
		}

		$updated = $this->nursing_dashboard_model->get_patient_summary($iopId);
		$result['patient_summary'] = $updated ? $updated : $summary;
		if (!isset($result['errors'])) {
			$result['errors'] = array();
		}
		if (!isset($result['warnings'])) {
			$result['warnings'] = array();
		}
		$this->json_response($result, 200);
	}

	public function api_save_note($id = ''){
		if (!$this->api_guard()) return;
		if (strtolower((string)$this->input->method()) !== 'post') {
			$this->json_response(array('status' => 'error', 'code' => 'METHOD_NOT_ALLOWED', 'message' => 'Method not allowed.'), 405);
			return;
		}

		$summary = $this->nursing_dashboard_model->get_patient_summary($id);
		if (!$summary || !isset($summary['patient'])) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_NOT_FOUND',
				'message' => 'Patient was not found or is not active.'
			), 404);
			return;
		}

		$patient = $summary['patient'];
		$iopId = isset($patient['encounter_id']) ? trim((string)$patient['encounter_id']) : '';
		$patientNo = isset($patient['patient_no']) ? trim((string)$patient['patient_no']) : '';
		$actorUserId = trim((string)$this->session->userdata('user_id'));

		// Block writes on clinically cleared encounters
		if ($iopId !== '') {
			$_visit = $this->db->select('clinical_clearance_status')
				->get_where('patient_details_iop', ['IO_ID' => $iopId, 'InActive' => 0])->row();
			if ($_visit && isset($_visit->clinical_clearance_status) && (int)$_visit->clinical_clearance_status === 1) {
				log_message('error', '[NURSE_API_ENCOUNTER_LOCKED] endpoint=api_save_note iop=' . $iopId . ' user=' . $actorUserId);
				$this->json_response(array(
					'status' => 'error',
					'code' => 'ENCOUNTER_CLOSED',
					'message' => 'This encounter is clinically cleared. No further modifications are allowed.',
					'errors' => array(array('field' => 'encounter_id', 'code' => 'ENCOUNTER_CLOSED', 'message' => 'Encounter locked.')),
					'warnings' => array()
				), 403);
				return;
			}
		}

		$raw = (string)$this->input->raw_input_stream;
		$decoded = json_decode($raw, true);
		$post = $this->input->post(NULL, true);
		if (!is_array($post)) {
			$post = array();
		}
		$body = is_array($decoded) ? array_merge($post, $decoded) : $post;

		$idempotencyKey = '';
		if (isset($body['idempotency_key'])) {
			$idempotencyKey = trim((string)$body['idempotency_key']);
		}
		if ($idempotencyKey === '' && isset($_SERVER['HTTP_X_IDEMPOTENCY_KEY'])) {
			$idempotencyKey = trim((string)$_SERVER['HTTP_X_IDEMPOTENCY_KEY']);
		}
		if ($idempotencyKey === '' && isset($_SERVER['HTTP_IDEMPOTENCY_KEY'])) {
			$idempotencyKey = trim((string)$_SERVER['HTTP_IDEMPOTENCY_KEY']);
		}
		if ($idempotencyKey === '') {
			$this->json_response(array(
				'status' => 'validation_error',
				'code' => 'IDEMPOTENCY_KEY_REQUIRED',
				'message' => 'Missing idempotency key.',
				'errors' => array(array('field' => 'idempotency_key', 'code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'Missing idempotency key.')),
				'warnings' => array()
			), 400);
			return;
		}

		if (isset($body['patient_no']) && trim((string)$body['patient_no']) !== '' && trim((string)$body['patient_no']) !== $patientNo) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'PATIENT_CONTEXT_MISMATCH',
				'message' => 'Patient context mismatch.',
				'errors' => array(array('field' => 'patient_no', 'code' => 'PATIENT_CONTEXT_MISMATCH', 'message' => 'Patient context mismatch.')),
				'warnings' => array()
			), 409);
			return;
		}
		if (isset($body['encounter_id']) && trim((string)$body['encounter_id']) !== '' && trim((string)$body['encounter_id']) !== $iopId) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'ENCOUNTER_CONTEXT_MISMATCH',
				'message' => 'Encounter context mismatch.',
				'errors' => array(array('field' => 'encounter_id', 'code' => 'ENCOUNTER_CONTEXT_MISMATCH', 'message' => 'Encounter context mismatch.')),
				'warnings' => array()
			), 409);
			return;
		}

		$adapterPath = APPPATH . 'services/Nursing/NursingNotesAdapter.php';
		if (!file_exists($adapterPath)) {
			$this->json_response(array('status' => 'error', 'code' => 'ADAPTER_MISSING', 'message' => 'Notes adapter is unavailable.'), 500);
			return;
		}
		require_once($adapterPath);

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			$this->json_response(array('status' => 'error', 'code' => 'CTM_FACTORY_MISSING', 'message' => 'Clinical transaction dependencies are unavailable.'), 500);
			return;
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		$this->config->load('clinical_runtime', true);
		$leaseSeconds = (int)$this->config->item('clinical_ctm_lease_seconds', 'clinical_runtime');
		$prefix = (string)$this->config->item('clinical_ctm_lease_owner_prefix', 'clinical_runtime');
		if ($prefix === '') {
			$prefix = 'hms-clinical-web';
		}
		$leaseOwner = $prefix . ':' . getmypid();

		try {
			$transactions = new CiClinicalTransactionManager($this->db, $leaseSeconds > 0 ? $leaseSeconds : 60, $leaseOwner);
			$adapter = new NursingNotesAdapter($this->db);
			$result = $adapter->record($iopId, $patientNo, $actorUserId, $idempotencyKey, $body, $transactions);
		} catch (InvalidArgumentException $e) {
			$code = $e->getMessage();
			$field = 'note_text';
			if ($code === 'note_text_too_long') {
				$field = 'note_text';
			}
			$this->json_response(array(
				'status' => 'validation_error',
				'code' => strtoupper($code),
				'message' => $code,
				'errors' => array(array('field' => $field, 'code' => strtoupper($code), 'message' => $code)),
				'warnings' => array(),
				'governance_observed' => true
			), 422);
			return;
		} catch (Exception $e) {
			$this->json_response(array(
				'status' => 'error',
				'code' => 'NOTE_SAVE_FAILED',
				'message' => $e->getMessage(),
				'errors' => array(),
				'warnings' => array(),
				'governance_observed' => true
			), 500);
			return;
		}

		if (!is_array($result)) {
			$this->json_response(array('status' => 'error', 'code' => 'NOTE_SAVE_FAILED', 'message' => 'Unexpected result.', 'errors' => array(), 'warnings' => array(), 'governance_observed' => true), 500);
			return;
		}
		if (!isset($result['status']) || $result['status'] !== 'success') {
			$this->json_response($result, 500);
			return;
		}

		$updated = $this->nursing_dashboard_model->get_patient_summary($iopId);
		$result['patient_summary'] = $updated ? $updated : $summary;
		if (!isset($result['errors'])) {
			$result['errors'] = array();
		}
		if (!isset($result['warnings'])) {
			$result['warnings'] = array();
		}
		$this->json_response($result, 200);
	}

	public function api_alerts(){
		if (!$this->api_guard()) return;
		$payload = $this->nursing_dashboard_model->get_dashboard_payload($this->input->get('ward_id'));
		$this->json_response(array(
			'status' => 'ok',
			'alerts' => isset($payload['alerts']) ? $payload['alerts'] : array(),
			'meta' => array('count' => isset($payload['alerts']) ? count($payload['alerts']) : 0, 'partial' => false, 'warnings' => array())
		));
	}

	private function guard_nursing_access(){
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			redirect(base_url().'access_denied');
			return false;
		}
		return true;
	}

	private function api_guard(){
		if (General::is_logged_in() == FALSE) {
			$this->json_response(array('status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'You are not authorized to view the nursing dashboard.'), 401);
			return false;
		}
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			$this->json_response(array('status' => 'error', 'code' => 'UNAUTHORIZED', 'message' => 'You are not authorized to view the nursing dashboard.'), 403);
			return false;
		}
		return true;
	}

	private function json_response($payload, $statusCode = 200){
		$this->output
			->set_status_header($statusCode)
			->set_content_type('application/json')
			->set_output(json_encode($payload));
	}
}
