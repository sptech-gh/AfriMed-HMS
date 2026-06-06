<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * Shift Task Controller — Patient-Driven Nursing Task Management
 *
 * Architecture: follows the SAME patient-first pattern as medication(),
 * intake_output(), consumable_order — all clinical modules in this HMS.
 *
 * Entry flow:
 *   1. Nurse navigates to index() → pick.php patient selector
 *   2. Selects patient → redirected to index() with iop_id + patient_no
 *   3. Patient-specific shift task dashboard loads
 *
 * Secondary view:
 *   ward_overview() → aggregated shift board for handover compilation only
 */
class Shift_task_controller extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/Nurse_enhancement_model');
		$this->load->model('app/ipd_model');
		$this->load->model('app/patient_model');
		$this->load->model('app/billing_model');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		require_role(array('nurse', 'receptionist', 'doctor', 'admin'));
	}

	private function _normalize_id($value)
	{
		if (function_exists('sanitize_id_for_db')) {
			return sanitize_id_for_db((string)$value);
		}
		return trim(urldecode((string)$value));
	}

	private function _json($data)
	{
		header('Content-Type: application/json');
		$csrf_name = $this->security->get_csrf_token_name();
		$csrf_hash = $this->security->get_csrf_hash();
		if (is_array($data)) {
			$data['csrf_token_name'] = $csrf_name;
			$data['csrf_token_hash'] = $csrf_hash;
		}
		echo json_encode($data);
	}

	private function _is_nurse_or_admin()
	{
		$role = strtolower(trim((string)$this->session->userdata('role')));
		return in_array($role, array('nurse', 'admin'));
	}

	private function _is_admin_or_doctor()
	{
		$role = strtolower(trim((string)$this->session->userdata('role')));
		return in_array($role, array('admin', 'doctor'));
	}

	private function _get_role()
	{
		return strtolower(trim((string)$this->session->userdata('role')));
	}

	// =========================================================================
	// PRIMARY VIEW: PATIENT-DRIVEN TASK DASHBOARD
	// =========================================================================
	// Pattern: identical to consumable_order::index()
	//   - No patient context → pick.php patient selector
	//   - With patient context → patient-specific task view
	// =========================================================================

	public function index()
	{
		$this->Nurse_enhancement_model->ensure_nurse_shift_task_billing_schema();

		// --- Resolve patient context (URI > POST > none) ---
		$iop_id     = $this->_normalize_id($this->uri->segment(4));
		$patient_no = $this->_normalize_id($this->uri->segment(5));

		if ($iop_id === '' || $patient_no === '') {
			if ($this->input->post('iop_no') != '' && $this->input->post('patient_no') != '') {
				$iop_id     = $this->_normalize_id($this->input->post('iop_no'));
				$patient_no = $this->_normalize_id($this->input->post('patient_no'));
			}
		}

		// --- No patient selected → show patient picker ---
		if ($iop_id === '' || $patient_no === '') {
			$this->session->set_userdata(array(
				'tab' => 'nurse_module', 'module' => 'shift_task_controller',
			));
			$this->data['module_title'] = 'Shift Tasks';
			$this->data['module'] = 'shift_task_controller';
			$this->data['form_action'] = base_url() . 'app/shift_task_controller';
			$this->load->view('app/nurse_module/pick', $this->data);
			return;
		}

		// --- Patient selected → load patient-specific task dashboard ---
		$enc = $this->ipd_model->getIPDPatient($iop_id);
		$pat = $this->patient_model->getPatientInfo($patient_no);

		$this->data['getOPDPatient'] = $enc;
		$this->data['patientInfo']   = $pat;
		$this->data['iop_id']        = $iop_id;
		$this->data['patient_no']    = $patient_no;
		$this->data['message']       = $this->session->flashdata('message');

		// Shifts for filter
		$this->data['shifts'] = $this->db->where('InActive', 0)
			->order_by('shift_id', 'ASC')->get('nurse_shift')->result();

		// Task categories
		$this->data['task_categories'] = Nurse_enhancement_model::$TASK_CATEGORIES;

		// Current shift auto-detect
		$current_shift = $this->_detect_current_shift();
		$this->data['current_shift_id'] = $current_shift;

		// Patient's tasks for current date + shift
		$this->data['patient_tasks'] = $this->Nurse_enhancement_model
			->get_tasks_for_encounter($iop_id);

		// Overdue tasks for this patient
		$this->data['overdue_tasks'] = $this->Nurse_enhancement_model
			->get_overdue_tasks_for_patient($iop_id);

		// Billing feature flag
		$this->data['billing_enabled'] = $this->_billing_enabled();

		// User context
		$this->data['user_role'] = $this->_get_role();
		$this->data['user_id']   = (string)$this->session->userdata('user_id');

		// Bed info (from encounter room_id)
		$this->data['bed_info'] = $this->_get_bed_info($iop_id, $patient_no);

		// Nurses list for assignment dropdown
		$this->data['nurses_list'] = $this->Nurse_enhancement_model->get_nurses_list();

		$this->load->view('app/nurse_module/shift_task_dashboard', $this->data);
	}

	// =========================================================================
	// SECONDARY VIEW: WARD OVERVIEW (HANDOVER + OVERDUE BOARD)
	// =========================================================================

	public function ward_overview()
	{
		$this->Nurse_enhancement_model->ensure_nurse_shift_task_billing_schema();

		$this->session->set_userdata(array(
			'tab' => 'nurse_module', 'module' => 'shift_ward_overview',
		));

		$this->data['shifts'] = $this->db->where('InActive', 0)
			->order_by('shift_id', 'ASC')->get('nurse_shift')->result();

		$current_shift = $this->_detect_current_shift();
		$this->data['current_shift_id'] = $current_shift;
		$this->data['user_role'] = $this->_get_role();
		$this->data['user_id']   = (string)$this->session->userdata('user_id');
		$this->data['message']   = $this->session->flashdata('message');

		// Pending handovers for incoming shift
		$this->data['pending_handovers'] = $this->Nurse_enhancement_model
			->get_pending_handovers($current_shift, date('Y-m-d'));

		// All overdue tasks across all patients
		$this->data['overdue_tasks'] = $this->Nurse_enhancement_model->get_overdue_tasks();

		$this->load->view('app/nurse_module/shift_ward_overview', $this->data);
	}

	// =========================================================================
	// AJAX: GET TASKS FOR A SPECIFIC PATIENT
	// =========================================================================

	public function get_patient_tasks_ajax()
	{
		$iop_id     = $this->_normalize_id($this->input->post('iop_id'));
		$shift_date = $this->input->post('shift_date') ?: date('Y-m-d');
		$shift_id   = (int)$this->input->post('shift_id');

		if ($iop_id === '') {
			$this->_json(array('success' => false, 'error' => 'iop_id required'));
			return;
		}

		$tasks = $this->Nurse_enhancement_model
			->get_tasks_for_patient_shift($iop_id, $shift_date, $shift_id);

		$this->_json(array('success' => true, 'data' => $tasks));
	}

	// =========================================================================
	// AJAX: GET WARD-LEVEL TASKS (for ward overview)
	// =========================================================================

	public function get_shift_tasks_ajax()
	{
		$shift_date = $this->input->get_post('shift_date') ?: date('Y-m-d');
		$shift_id   = (int)$this->input->get_post('shift_id');
		$ward       = $this->input->get_post('ward') ?: 'GENERAL';
		$role       = $this->_get_role();
		$user_id    = (string)$this->session->userdata('user_id');

		$tasks = $this->Nurse_enhancement_model
			->get_tasks_for_shift($ward, $shift_date, $shift_id, $role, $user_id);

		$this->_json(array('success' => true, 'data' => $tasks));
	}

	// =========================================================================
	// AJAX: GET OVERDUE TASKS
	// =========================================================================

	public function get_overdue_ajax()
	{
		$iop_id = $this->_normalize_id($this->input->post('iop_id'));
		if ($iop_id !== '') {
			$tasks = $this->Nurse_enhancement_model->get_overdue_tasks_for_patient($iop_id);
		} else {
			$tasks = $this->Nurse_enhancement_model->get_overdue_tasks();
		}
		$this->_json(array('success' => true, 'data' => $tasks, 'count' => count($tasks)));
	}

	// =========================================================================
	// CREATE TASK (always patient-bound)
	// =========================================================================

	public function create()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('success' => false, 'error' => 'POST required'));
			return;
		}

		$role = $this->_get_role();
		if (!in_array($role, array('nurse', 'receptionist', 'admin'))) {
			$this->_json(array('success' => false, 'error' => 'Access denied'));
			return;
		}

		$actor = (string)$this->session->userdata('user_id');
		$data = array(
			'iop_id'        => $this->_normalize_id($this->input->post('iop_id')),
			'patient_no'    => $this->_normalize_id($this->input->post('patient_no')),
			'shift_id'      => (int)$this->input->post('shift_id'),
			'shift_date'    => $this->input->post('shift_date') ?: date('Y-m-d'),
			'title'         => trim((string)$this->input->post('title')),
			'description'   => trim((string)$this->input->post('description')),
			'task_category' => trim((string)$this->input->post('task_category')),
			'priority'      => strtoupper(trim((string)$this->input->post('priority'))),
			'due_at'        => $this->input->post('due_at') ?: null,
			'assigned_to'   => $this->input->post('assigned_to') ?: null,
			'is_billable'   => (int)$this->input->post('is_billable'),
			'catalog_id'    => (int)$this->input->post('catalog_id'),
			'item_source'   => $this->input->post('item_source') ?: 'PARTICULAR',
			'quantity'      => (float)$this->input->post('quantity') ?: 1,
			'source_type'   => 'MANUAL',
		);

		if (!$this->_billing_enabled()) {
			$data['is_billable'] = 0;
		}

		$result = $this->Nurse_enhancement_model->create_task($data, $actor);
		$this->_json($result);
	}

	// =========================================================================
	// UPDATE TASK STATUS
	// =========================================================================

	public function update_status()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('success' => false, 'error' => 'POST required'));
			return;
		}
		if (!$this->_is_nurse_or_admin()) {
			$this->_json(array('success' => false, 'error' => 'Access denied'));
			return;
		}

		$task_id    = (int)$this->input->post('task_id');
		$new_status = trim((string)$this->input->post('new_status'));
		$notes      = trim((string)$this->input->post('notes'));
		$actor      = (string)$this->session->userdata('user_id');

		$result = $this->Nurse_enhancement_model->update_task_status($task_id, $new_status, $actor, $notes);
		$this->_json($result);
	}

	// =========================================================================
	// HANDOVER
	// =========================================================================

	public function get_handover_tasks()
	{
		$ward       = $this->input->get_post('ward') ?: 'GENERAL';
		$shift_date = $this->input->get_post('shift_date') ?: date('Y-m-d');
		$shift_id   = (int)$this->input->get_post('shift_id');

		$tasks = $this->Nurse_enhancement_model
			->get_pending_for_handover($ward, $shift_date, $shift_id);
		$this->_json(array('success' => true, 'data' => $tasks));
	}

	public function create_handover()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('success' => false, 'error' => 'POST required'));
			return;
		}
		if (!$this->_is_nurse_or_admin()) {
			$this->_json(array('success' => false, 'error' => 'Access denied'));
			return;
		}

		$ward       = $this->input->post('ward') ?: 'GENERAL';
		$shift_id   = (int)$this->input->post('outgoing_shift_id');
		$shift_date = $this->input->post('shift_date') ?: date('Y-m-d');
		$notes      = trim((string)$this->input->post('general_notes'));
		$actor      = (string)$this->session->userdata('user_id');

		$task_ids_raw = $this->input->post('task_ids');
		$task_ids = array();
		if (is_array($task_ids_raw)) {
			$task_ids = array_map('intval', $task_ids_raw);
		} elseif (is_string($task_ids_raw) && $task_ids_raw !== '') {
			$task_ids = array_map('intval', explode(',', $task_ids_raw));
		}

		$result = $this->Nurse_enhancement_model
			->create_handover($ward, $shift_id, $shift_date, $task_ids, $notes, $actor);
		$this->_json($result);
	}

	public function acknowledge_handover()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('success' => false, 'error' => 'POST required'));
			return;
		}
		if (!$this->_is_nurse_or_admin()) {
			$this->_json(array('success' => false, 'error' => 'Access denied'));
			return;
		}

		$handover_id = (int)$this->input->post('handover_id');
		$actor       = (string)$this->session->userdata('user_id');

		$result = $this->Nurse_enhancement_model->acknowledge_handover($handover_id, $actor);
		$this->_json($result);
	}

	// =========================================================================
	// ESCALATION
	// =========================================================================

	public function escalate_task()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('success' => false, 'error' => 'POST required'));
			return;
		}
		$role = $this->_get_role();
		if (!in_array($role, array('nurse', 'doctor', 'admin'))) {
			$this->_json(array('success' => false, 'error' => 'Access denied'));
			return;
		}

		$task_id      = (int)$this->input->post('task_id');
		$escalated_to = trim((string)$this->input->post('escalated_to'));
		$reason       = trim((string)$this->input->post('reason'));
		$actor        = (string)$this->session->userdata('user_id');

		if ($reason === '') {
			$this->_json(array('success' => false, 'error' => 'Escalation reason is required.'));
			return;
		}

		$result = $this->Nurse_enhancement_model->escalate_task($task_id, $escalated_to, $reason, $actor);
		$this->_json($result);
	}

	public function resolve_escalation()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->_json(array('success' => false, 'error' => 'POST required'));
			return;
		}
		if (!$this->_is_admin_or_doctor()) {
			$this->_json(array('success' => false, 'error' => 'Only admin or doctor can resolve escalations.'));
			return;
		}

		$escalation_id    = (int)$this->input->post('escalation_id');
		$resolution_notes = trim((string)$this->input->post('resolution_notes'));
		$actor            = (string)$this->session->userdata('user_id');

		$result = $this->Nurse_enhancement_model->resolve_escalation($escalation_id, $resolution_notes, $actor);
		$this->_json($result);
	}

	public function get_escalations_ajax()
	{
		if (!$this->_is_admin_or_doctor()) {
			$this->_json(array('success' => false, 'error' => 'Access denied'));
			return;
		}
		$escalations = $this->Nurse_enhancement_model->get_open_escalations();
		$this->_json(array('success' => true, 'data' => $escalations));
	}

	// =========================================================================
	// CATALOG SEARCH (for billable task creation)
	// =========================================================================

	public function search_catalog_ajax()
	{
		header('Content-Type: application/json');
		$term = trim((string)$this->input->post('term'));
		if (strlen($term) < 2) {
			echo json_encode(array('success' => true, 'results' => array()));
			return;
		}

		$nursing_groups = array(
			'CONSUMABLES', 'WARD SUPPLIES', 'NURSING CONSUMABLES',
			'WOUND CARE SUPPLIES', 'IV & INFUSION SUPPLIES',
			'CATHETER & DRAINAGE', 'RESPIRATORY SUPPLIES', 'WARD SERVICES',
		);

		$this->db->select('bp.particular_id AS catalog_id, bp.particular_name AS item_name, bp.charge_amount AS price, bg.group_name');
		$this->db->from('bill_particular bp');
		$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'left');
		$this->db->where('bp.InActive', 0);
		$this->db->like('bp.particular_name', $term);
		$this->db->where_in('bg.group_name', $nursing_groups);
		$this->db->order_by('bp.particular_name', 'ASC');
		$this->db->limit(20);
		$q = $this->db->get();
		$results = $q ? $q->result() : array();

		echo json_encode(array('success' => true, 'results' => $results));
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	private function _detect_current_shift()
	{
		$hour = (int)date('H');
		if ($hour >= 6 && $hour < 14)  return 1;
		if ($hour >= 14 && $hour < 22) return 2;
		return 3;
	}

	private function _billing_enabled()
	{
		if ($this->db->table_exists('smart_billing_config')) {
			$row = $this->db->get_where('smart_billing_config', array(
				'config_key' => 'SHIFT_TASK_BILLING_ENABLED'
			))->row();
			if ($row && isset($row->config_value)) {
				return strtoupper(trim((string)$row->config_value)) === 'TRUE'
					|| (string)$row->config_value === '1';
			}
		}
		return false;
	}

	/**
	 * Get bed assignment for a patient encounter.
	 */
	private function _get_bed_info($iop_id, $patient_no)
	{
		$enc = $this->db->select('room_id')
			->where('IO_ID', $iop_id)->where('InActive', 0)
			->get('patient_details_iop')->row();
		if (!$enc || !isset($enc->room_id) || (int)$enc->room_id <= 0) {
			return null;
		}
		$bed = $this->db->select('rb.bed_name, rm.room_name')
			->from('room_beds rb')
			->join('room_master rm', 'rm.room_master_id = rb.room_master_id', 'left')
			->where('rb.patient_no', (string)$patient_no)
			->where('rb.room_master_id', (int)$enc->room_id)
			->where('rb.InActive', 0)
			->get()->row();
		return $bed ?: null;
	}
}
