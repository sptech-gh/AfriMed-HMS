<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Nurse_module extends General{

	private $limit = 10;

	private function _normalize_id($value){
		if (function_exists('sanitize_id_for_db')) {
			return sanitize_id_for_db((string)$value);
		}
		return trim(urldecode((string)$value));
	}

	private function _nurse_table_exists($table, $schema_flag = null)
	{
		if ($schema_flag !== null && schema_already_run($schema_flag)) {
			return true;
		}
		$exists = $this->db->table_exists($table);
		if ($schema_flag !== null && $exists) {
			mark_schema_run($schema_flag);
		}
		return $exists;
	}

	private function _iop_patient_binding_exists($iop_id, $patient_no){
		$iop_id = $this->_normalize_id($iop_id);
		$patient_no = $this->_normalize_id($patient_no);
		if ($iop_id === '' || $patient_no === '') {
			return false;
		}
		$row = $this->db
			->select('IO_ID')
			->where(array('IO_ID' => (string)$iop_id, 'patient_no' => (string)$patient_no, 'InActive' => 0))
			->limit(1)
			->get('patient_details_iop')
			->row();
		return (bool)$row;
	}

	/**
	 * Check if an encounter is clinically cleared (closed).
	 * Returns true if the encounter is locked for further modifications.
	 */
	private function _is_encounter_closed($iop_id){
		$iop_id = $this->_normalize_id($iop_id);
		if ($iop_id === '') return true;
		$visit = $this->db
			->select('clinical_clearance_status')
			->where(array('IO_ID' => (string)$iop_id, 'InActive' => 0))
			->limit(1)
			->get('patient_details_iop')
			->row();
		if (!$visit) return true;
		// Column may not exist on older schemas — treat as open
		return (isset($visit->clinical_clearance_status) && (int)$visit->clinical_clearance_status === 1);
	}

	private function _require_iop_patient_binding_or_redirect($iop_id, $patient_no, $redirect_path){
		if (!$this->_iop_patient_binding_exists($iop_id, $patient_no)) {
			redirect(base_url() . 'access_denied');
			return false;
		}
		// Block writes on clinically cleared encounters (admin override allowed)
		if ($this->_is_encounter_closed($iop_id) && !$this->current_user_is_admin()) {
			log_message('error', '[NURSE_ENCOUNTER_LOCKED] iop=' . $iop_id
				. ' user=' . $this->session->userdata('user_id') . ' path=' . $redirect_path);
			$this->session->set_flashdata('message',
				"<div class='alert alert-danger alert-dismissable'><i class='fa fa-lock'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "<strong>Encounter Locked:</strong> This patient is clinically cleared. No further modifications are allowed.</div>");
			redirect(base_url() . $redirect_path . '/' . urlencode((string)$iop_id) . '/' . urlencode((string)$patient_no));
			return false;
		}
		return true;
	}

	private function _apply_ipd_encounter_context($iop_no, $patient_no, $redirect_path){
		$req_iop_no = (string)$iop_no;
		$req_patient_no = (string)$patient_no;
		$this->load->library('EncounterContext');
		$ctx = $this->encountercontext->resolve('IPD', $iop_no, $patient_no, array(
			'include_billing' => false,
			'include_vitals' => false,
			'log_non_owner_view' => false,
			'hasAccesstoDoctor' => false,
			'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
		));
		if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
			$iop_no = $ctx['iop_no'];
			$patient_no = $ctx['patient_no'];
			if ($this->input->post('iop_no') == '' && $this->input->post('patient_no') == '' && ($req_patient_no === '' || $req_patient_no !== (string)$patient_no || $req_iop_no !== (string)$iop_no)) {
				redirect(base_url().$redirect_path.'/'.url_safe_id($iop_no).'/'.url_safe_id($patient_no));
				return false;
			}
			if (isset($ctx['data']) && is_array($ctx['data'])) {
				foreach ($ctx['data'] as $k => $v) {
					$this->data[$k] = $v;
				}
			}
		} else {
			$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		}
		if (!isset($this->data['getOPDPatient'])) {
			$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		}
		if (!isset($this->data['patientInfo'])) {
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		}
		return array($iop_no, $patient_no);
	}

	/**
	 * Fetch OPD prescriptions for the same patient (reference only).
	 * When a patient is admitted (OPD→IPD), the doctor must re-prescribe
	 * medications under the IPD encounter. This reference panel lets
	 * nurses see what was prescribed outpatient so they can prompt
	 * the doctor if needed.
	 */
	private function _get_opd_prescriptions_for_patient($patient_no, $current_iop_id)
	{
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') return array();

		// Find OPD encounters for this patient
		$this->db->select('IO_ID');
		$this->db->where(array('patient_no' => $patient_no, 'patient_type' => 'OPD', 'InActive' => 0));
		$this->db->order_by('date_visit', 'DESC');
		$this->db->limit(5);
		$opd_visits = $this->db->get('patient_details_iop')->result();
		if (empty($opd_visits)) return array();

		$opd_ids = array();
		foreach ($opd_visits as $v) {
			if ((string)$v->IO_ID !== (string)$current_iop_id) {
				$opd_ids[] = (string)$v->IO_ID;
			}
		}
		if (empty($opd_ids)) return array();

		// Get medications from those OPD encounters
		$this->db->select("
			A.iop_id,
			A.iop_med_id,
			B.drug_name,
			A.medicine_text,
			A.dosage,
			A.frequency,
			A.instruction,
			A.advice,
			A.days,
			A.total_qty,
			A.dDate,
			concat(D.cValue,' ',C.firstname,' ',C.lastname) as prescribed_by_name
		", false);
		$this->db->where_in('A.iop_id', $opd_ids);
		$this->db->where('A.InActive', 0);
		$this->db->join('medicine_drug_name B', 'B.drug_id = A.medicine_id', 'left outer');
		$this->db->join('users C', 'C.user_id = A.cPreparedBy', 'left outer');
		$this->db->join('system_parameters D', 'D.param_id = C.title', 'left outer');
		$this->db->order_by('A.dDate', 'DESC');
		$this->db->limit(20);
		return $this->db->get('iop_medication A')->result();
	}

	public function __construct(){
		parent::__construct();
		$this->load->model("app/billing_model");
		$this->load->model("app/billing_transaction_model");
		$this->load->model("app/ipd_model");
		$this->load->model("app/opd_model");
		$this->load->model("app/patient_model");
		$this->load->model("app/nurse_enhancement_model");
		$this->load->model("app/Medication_dictionary_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		if (!$this->session->userdata('_schema_nurse_ok')) {
			$this->Medication_dictionary_model->ensure_dictionary_schema();
			$this->session->set_userdata('_schema_nurse_ok', 1);
		}
		require_role(array('nurse', 'doctor'));
	}

	public function index(){
		redirect(base_url().'app/nurse_module/vitals_queue');
	}

	public function patient($id = ''){
		$id = trim((string)$id);
		if ($id === '') {
			redirect(base_url().'app/nursing/dashboard');
			return;
		}
		redirect(base_url().'app/nursing/workspace/'.urlencode($id));
	}

	public function install_enhancements(){
		if (!$this->current_user_is_admin()) {
			redirect(base_url().'access_denied');
			return;
		}
		$this->nurse_enhancement_model->install_tables();
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Nurse enhancements installed/verified.</div>");
		redirect(base_url().'app/dashboard');
	}
	
	public function medication(){
		
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}

		
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/medication')) {
				return;
			}
			$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['medicineCategory'] = $this->opd_model->medicineCategory();
			$this->data['patientMedication'] = $this->opd_model->patientMedication($iop_no);
			$this->data['nurse_enhancements_ready'] = $this->nurse_enhancement_model->tables_ready();
			$this->data['adminLatestByMed'] = $this->nurse_enhancement_model->get_latest_med_admin_by_iop($iop_no);

			// Fetch OPD prescriptions for same patient as reference (nurses cannot administer these,
			// but seeing them helps prompt the doctor to re-prescribe under the IPD encounter).
			$this->data['opdMedications'] = $this->_get_opd_prescriptions_for_patient($patient_no, $iop_no);
		
			$this->load->view("app/nurse_module/medication",$this->data);	
		}else{
				// user restriction function
				$this->session->set_userdata('page_name','nurse_medication_reports');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function		
			
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_medication',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Medication";
			$this->data['module'] = "medication";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}

	public function messages(){
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			redirect(base_url().'access_denied');
			return;
		}

		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			$this->session->set_userdata("abc","");
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}

			$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
			$this->data['messages_ready'] = $this->_nurse_table_exists('nurse_doctor_message', 'nurse_doctor_message_schema');
			$this->data['assigned_doctor_id'] = $this->nurse_enhancement_model->get_iop_doctor_id($iop_no);
			$this->data['thread'] = $this->data['messages_ready'] ? $this->nurse_enhancement_model->get_messages_thread($iop_no, $patient_no) : array();
			if ($this->data['messages_ready']) {
				$this->nurse_enhancement_model->mark_thread_read_for_user($iop_no, $patient_no, $this->session->userdata('user_id'));
			}
			$this->data['message'] = $this->session->flashdata('message');
			$this->load->view('app/nurse_module/messages_thread', $this->data);
			return;
		}

		$this->session->set_userdata(array(
			'tab' => 'nurse_module',
			'module' => 'nurse_messages',
			'subtab' => '',
			'submodule' => ''
		));
		$this->data['module_title'] = 'Nurse → Doctor Messages';
		$this->data['module'] = 'messages';
		$this->load->view('app/nurse_module/pick', $this->data);
	}

	public function send_message(){
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_nurse_table_exists('nurse_doctor_message', 'nurse_doctor_message_schema')) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Nurse messaging is not installed. Ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/messages/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
			return;
		}

		$iop_id = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->_require_iop_patient_binding_or_redirect($iop_id, $patient_no, 'app/nurse_module/messages')) {
			return;
		}
		$to_doctor_id = $this->input->post('to_doctor_id');
		$message = $this->input->post('message');
		if (trim((string)$message) === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Message cannot be empty.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/messages/'.$iop_id.'/'.$patient_no,$this->data);
			return;
		}
		if (trim((string)$to_doctor_id) === '') {
			$to_doctor_id = $this->nurse_enhancement_model->get_iop_doctor_id($iop_id);
		}
		$this->nurse_enhancement_model->send_message_to_doctor($iop_id, $patient_no, $this->session->userdata('user_id'), $to_doctor_id, $message);
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Message sent.</div>");
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/messages/'.$iop_id.'/'.$patient_no,$this->data);
	}

	public function shift_tasks(){
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			redirect(base_url().'access_denied');
			return;
		}
		$this->session->set_userdata(array(
			'tab' => 'nurse_module',
			'module' => 'nurse_shift_tasks',
			'subtab' => '',
			'submodule' => ''
		));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['tasks_ready'] = $this->_nurse_table_exists('nurse_shift_task', 'nurse_shift_task_schema') && $this->_nurse_table_exists('nurse_shift', 'nurse_shift_schema');
		$this->data['shifts'] = $this->data['tasks_ready'] ? $this->nurse_enhancement_model->get_shifts() : array();

		// Shift selection: POST > URI > auto-detect from server time
		$shift_id = $this->input->post('shift_id');
		if ($shift_id === '' || $shift_id === null) {
			$shift_id = $this->uri->segment('4');
		}
		if (($shift_id === '' || $shift_id === null) && $this->data['tasks_ready']) {
			$shift_id = $this->nurse_enhancement_model->detect_current_shift();
		}
		$this->data['selected_shift_id'] = $shift_id;

		// Date filter (defaults to today)
		$shift_date = $this->input->post('shift_date');
		if (!$shift_date) $shift_date = date('Y-m-d');
		$this->data['shift_date'] = $shift_date;

		// Use enhanced method with date filtering + JOINed user names
		$this->data['open_tasks'] = $this->data['tasks_ready']
			? $this->nurse_enhancement_model->get_shift_tasks_by_date($shift_id, 'OPEN', $shift_date)
			: array();
		$this->data['done_tasks'] = $this->data['tasks_ready']
			? $this->nurse_enhancement_model->get_shift_tasks_by_date($shift_id, 'DONE', $shift_date)
			: array();

		// Task categories for dropdown
		$this->data['task_categories'] = Nurse_enhancement_model::$TASK_CATEGORIES;

		// Nurses list for assignment
		$this->data['nurses_list'] = $this->data['tasks_ready']
			? $this->nurse_enhancement_model->get_nurses_list()
			: array();

		$this->load->view('app/nurse_module/shift_tasks', $this->data);
	}

	public function save_shift_task(){
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_nurse_table_exists('nurse_shift_task', 'nurse_shift_task_schema')) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Shift tasks are not installed. Ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.</div>");
			redirect(base_url().'app/nurse_module/shift_tasks');
			return;
		}

		$title = $this->input->post('title');
		if (trim((string)$title) === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Task title is required.</div>");
			redirect(base_url().'app/nurse_module/shift_tasks');
			return;
		}

		// Ensure enhanced schema exists
		$this->nurse_enhancement_model->ensure_shift_task_enhanced_schema();

		$shift_id = $this->input->post('shift_id');
		$shift_date = $this->input->post('shift_date');
		if (!$shift_date) $shift_date = date('Y-m-d');
		$task_category = $this->input->post('task_category');
		if (!$task_category || !isset(Nurse_enhancement_model::$TASK_CATEGORIES[$task_category])) {
			$task_category = 'OTHER';
		}

		$data = array(
			'shift_id'      => ($shift_id !== '' && $shift_id !== null) ? (int)$shift_id : null,
			'shift_date'    => $shift_date,
			'iop_id'        => trim((string)$this->input->post('iop_no')) !== '' ? (string)$this->input->post('iop_no') : null,
			'patient_no'    => trim((string)$this->input->post('patient_no')) !== '' ? (string)$this->input->post('patient_no') : null,
			'title'         => (string)$title,
			'description'   => trim((string)$this->input->post('description')) !== '' ? (string)$this->input->post('description') : null,
			'task_category' => $task_category,
			'priority'      => trim((string)$this->input->post('priority')) !== '' ? (string)$this->input->post('priority') : 'NORMAL',
			'status'        => 'OPEN',
			'assigned_to'   => trim((string)$this->input->post('assigned_to')) !== '' ? (string)$this->input->post('assigned_to') : null,
			'created_by'    => (string)$this->session->userdata('user_id'),
			'created_at'    => date('Y-m-d H:i:s'),
			'completed_at'  => null,
			'InActive'      => 0
		);
		$this->db->insert('nurse_shift_task', $data);

		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Task created.</div>");
		redirect(base_url().'app/nurse_module/shift_tasks/'.$shift_id);
	}

	public function complete_shift_task(){
		if (!isset($this->data['hasAccesstoNurse']) || !$this->data['hasAccesstoNurse']) {
			redirect(base_url().'access_denied');
			return;
		}
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		$task_id = $this->input->post('task_id');
		$shift_id = $this->input->post('shift_id');
		$handover_notes = trim((string)$this->input->post('handover_notes'));
		$this->nurse_enhancement_model->complete_shift_task_enhanced($task_id, $this->session->userdata('user_id'), $handover_notes);
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Task completed.</div>");
		redirect(base_url().'app/nurse_module/shift_tasks/'.$shift_id);
	}

	public function save_medication_admin(){
		// Allow if user has NurseMedication access OR general Nurse access (covers page 100 not being assigned)
		$hasAccess = (isset($this->data['hasAccesstoNurseMedication']) && $this->data['hasAccesstoNurseMedication'])
			|| (isset($this->data['hasAccesstoNurse']) && $this->data['hasAccesstoNurse'])
			|| $this->current_user_is_admin();
		if (!$hasAccess) {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->nurse_enhancement_model->tables_ready()) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Nurse enhancements are not installed. Please ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/medication/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
			return;
		}

		$iop_med_id = $this->input->post('iop_med_id');
		$iop_id = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->_require_iop_patient_binding_or_redirect($iop_id, $patient_no, 'app/nurse_module/medication')) {
			return;
		}
		$status = $this->input->post('status');
		$dose_given = $this->input->post('dose_given');
		$notes = $this->input->post('notes');
		$dDateTime = $this->input->post('dDateTime');
		if (trim((string)$dDateTime) === '') {
			$dDateTime = date('Y-m-d H:i:s');
		}

		$this->nurse_enhancement_model->save_medication_administration($iop_med_id, $iop_id, $status, $dose_given, $notes, $dDateTime, $this->session->userdata('user_id'));
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication administration saved.</div>");
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/medication/'.$iop_id.'/'.$patient_no,$this->data);
	}
	
	public function save_medication(){
		// RBAC: Nurses are restricted to medication ADMINISTRATION only.
		// Prescribing is a doctor-only function (via ipd.php::save_medication).
		$opd_no_raw = $this->input->post('opd_no');
		$patient_no = trim((string)$this->input->post('patient_no'));
		General::logfile('RBAC', 'DENY', 'NURSE|save_medication_blocked|iop:' . $opd_no_raw . '|p:' . $patient_no . '|u:' . $this->session->userdata('user_id'));
		$this->session->set_flashdata('message',
			"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i>"
			."<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
			."<strong>Access Denied:</strong> Only doctors can prescribe medications. "
			."Nurses may record medication administration using the <em>Administer</em> button.</div>"
		);
		$this->session->set_userdata('abc','1');
		redirect(base_url().'app/nurse_module/medication/' . urlencode((string)$opd_no_raw) . '/' . urlencode((string)$patient_no));
		return;

		// Step 2 — Resolve original prescribing doctor from visit record
		// prescribed_by must be the doctor, not the nurse entering the order
		$prescribed_by = $nurse_id;
		try {
			$visit = $this->db->select('doctor_id, cDoctor')
				->get_where('patient_details_iop', array('IO_ID' => $iop_id))->row();
			if ($visit) {
				$prescribed_by = !empty($visit->doctor_id) ? (string)$visit->doctor_id
					: (!empty($visit->cDoctor) ? (string)$visit->cDoctor : $nurse_id);
			}
		} catch (Exception $e) {
			log_message('error', 'Nurse prescribed_by lookup non-blocking: ' . $e->getMessage());
		}

		// Step 3 — NHIS payment gate
		$gate = $this->billing_model->check_nhis_payment_gate($patient_no, $iop_id, 'PHARMACY');
		if (!$gate['allowed']) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> " . htmlspecialchars($gate['reason']) . "</div>");
			$this->session->set_userdata('abc', '1');
			redirect(base_url() . 'app/nurse_module/medication/' . $opd_no_raw . '/' . $patient_no);
			return;
		}
		$payer = $this->billing_model->determine_payer_type($patient_no);

		// Step 4 — NHIS formulary check (non-blocking warning)
		$warningHtml = '';
		if ($payer === 'NHIS' && $drug_id > 0) {
			try {
				$cov = $this->billing_model->check_drug_nhis_coverage($drug_id);
				if (!empty($cov['found']) && empty($cov['is_nhis_covered'])) {
					$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-exclamation-triangle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Notice:</strong> " . htmlspecialchars($cov['drug_name']) . " is not on the NHIS formulary (cash price applies).</div>";
				}
			} catch (Exception $e) {
				log_message('error', 'Nurse NHIS formulary check non-blocking: ' . $e->getMessage());
			}
		}

		// Step 5 — CDS safety check (BLOCKED stops save)
		$cdsLoaded = false;
		if ($drug_id > 0) {
			try {
				$this->load->model('app/Clinical_decision_support_model');
				$this->Clinical_decision_support_model->ensure_phase3_enhancements();
				$cdsLoaded = true;

				$alerts = $this->Clinical_decision_support_model->check_prescription_safety_full(
					$drug_id,
					strip_tags(trim((string)$this->input->post('dosage'))),
					strip_tags(trim((string)$this->input->post('frequency'))),
					max(1, (int)$this->input->post('nDays')),
					$patient_no, $iop_id,
					strip_tags(trim((string)$this->input->post('diagnosis_code')))
				);

				if ($this->Clinical_decision_support_model->should_block_prescription($alerts)) {
					$blockHtml = '';
					foreach ($alerts as $a) {
						if ($a->severity === 'BLOCKED') {
							$blockHtml .= '<li><strong>' . htmlspecialchars($a->type) . ':</strong> ' . htmlspecialchars($a->message) . '</li>';
						}
					}
					$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> <strong>Prescription Blocked - Patient Safety Risk:</strong><ul>{$blockHtml}</ul>Please consult a senior physician.</div>");
					$this->session->set_userdata('abc', '1');
					redirect(base_url() . 'app/nurse_module/medication/' . $opd_no_raw . '/' . $patient_no);
					return;
				}

				foreach ($alerts as $a) {
					$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Safety Alert — " . htmlspecialchars($a->type) . ':</strong> ' . htmlspecialchars($a->message) . "</div>";
				}
			} catch (Exception $e) {
				log_message('error', 'Nurse CDS check non-blocking: ' . $e->getMessage());
			}
		}

		// Step 6 — Hoist schema checks
		$has_prescribed  = $this->db->field_exists('prescribed_by',     'iop_medication');
		$has_disp_status = $this->db->field_exists('dispensing_status', 'iop_medication');
		$has_pay_status  = $this->db->field_exists('payment_status',    'iop_medication');
		$has_frequency   = $this->db->field_exists('frequency',         'iop_medication');

		$insert = array(
			'iop_id'        => $iop_id,
			'medicine_id'   => $drug_id ?: null,
			'medicine_text' => strip_tags(trim((string)$this->input->post('medicine_text'))),
			'instruction'   => strip_tags(trim((string)$this->input->post('instruction'))),
			'advice'        => strip_tags(trim((string)$this->input->post('advice'))),
			'days'          => max(1, (int)$this->input->post('nDays')),
			'total_qty'     => $qty,
			'cPreparedBy'   => $nurse_id,
			'dDate'         => date('Y-m-d H:i:s'),
			'InActive'      => 0,
		);
		// prescribed_by = original prescribing doctor; cPreparedBy = nurse who entered the order
		if ($has_prescribed)  $insert['prescribed_by']     = $prescribed_by;
		if ($has_disp_status) $insert['dispensing_status'] = 'PENDING';
		if ($has_pay_status)  $insert['payment_status']    = 'PENDING';
		if ($has_frequency) {
			$freq = strip_tags(trim((string)$this->input->post('frequency')));
			if ($freq !== '') $insert['frequency'] = $freq;
		}

		$this->db->insert('iop_medication', $insert);
		$new_id = $this->db->insert_id();

		if (!$new_id) {
			log_message('error', 'NURSE_MEDICATION_INSERT_FAILED: iop_id=' . $iop_id . ' ' . json_encode($this->db->error()));
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-times'></i> Failed to save medication. Please try again.</div>");
			$this->session->set_userdata('abc', '1');
			redirect(base_url() . 'app/nurse_module/medication/' . $opd_no_raw . '/' . $patient_no);
			return;
		}

		// Step 7 — Both billing queues + NHIS audit (non-blocking)
		$drug_name = strip_tags(trim((string)$this->input->post('medicine_text')));
		$isVerified = false;
		try {
			if ($this->db->field_exists('prescription_status', 'iop_medication')) {
				$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => (int)$new_id, 'InActive' => 0))->row();
				$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : 'PENDING';
				$isVerified = ($st === 'VERIFIED');
			}
		} catch (Exception $e) {
			$isVerified = false;
		}

		if ($isVerified) {
			// Pharmacy billing queue
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->load->model('app/pharmacy_model');
				$this->pharmacy_model->create_or_update_pharmacy_bill($new_id, $prescribed_by);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'Nurse pharmacy_bill non-blocking: ' . $e->getMessage());
			}

			// Unified billing queue
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$drugPrice = 0;
				if ($drug_id > 0) {
					$dr = $this->db->select('drug_name, nPrice, cash_price')
						->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
					if ($dr) {
						if (empty($drug_name)) $drug_name = (string)$dr->drug_name;
						$drugPrice = (!empty($dr->cash_price) && (float)$dr->cash_price > 0) ? (float)$dr->cash_price : (float)$dr->nPrice;
					}
				}
				$this->load->model('app/unified_billing_model');
				$this->unified_billing_model->add_to_billing_queue(array(
					'iop_id'        => $iop_id,
					'patient_no'    => $patient_no,
					'item_type'     => 'PHARMACY',
					'item_id'       => (string)$new_id,
					'item_name'     => $drug_name ?: 'Medication',
					'unit_price'    => $drugPrice,
					'quantity'      => $qty,
					'payer_type'    => $payer,
					'source_module' => 'PHARMACY',
					'source_ref'    => 'iop_id:' . (string)$iop_id . ':iop_medication:' . (string)(int)$new_id,
					'requested_by'  => $prescribed_by,
				));
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'Nurse unified_billing non-blocking: ' . $e->getMessage());
			}
		}

		// NHIS audit log
		try {
			$_dbg = $this->db->db_debug; $this->db->db_debug = false;
			$this->billing_model->log_nhis_audit(
				'NURSE_SAVE_MEDICATION', 'iop_medication', $new_id,
				null, json_encode(array('drug_id' => $drug_id, 'qty' => $qty, 'nurse_id' => $nurse_id)),
				$prescribed_by, $patient_no, $iop_id
			);
			$this->db->db_debug = $_dbg;
		} catch (Exception $e) {
			$this->db->db_debug = isset($_dbg) ? $_dbg : true;
			log_message('error', 'Nurse nhis_audit non-blocking: ' . $e->getMessage());
		}

		// CDS workflow tracking
		if ($cdsLoaded && $drug_id > 0) {
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->Clinical_decision_support_model->init_prescription_workflow(
					$new_id, $iop_id, $patient_no, $prescribed_by
				);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'Nurse cds_workflow non-blocking: ' . $e->getMessage());
			}
		}

		$successHtml = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication successfully Added!</div>";
		$this->session->set_flashdata('message', $warningHtml . $successHtml);

		$this->session->set_userdata('abc', '1');
		redirect(base_url() . 'app/nurse_module/medication/' . $opd_no_raw . '/' . $patient_no);
	}
	
	public function delete_medication(){
		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/medication')) {
			return;
		}
		if (!$this->current_user_is_admin()) {
			General::logfile('RBAC', 'DENY', 'NURSE|delete_medication|iop:'.$iop_no.'|p:'.$patient_no.'|u:'.$this->session->userdata('user_id'));
			redirect(base_url().'access_denied');
			return;
		}
		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => (int)$id, 'InActive' => 0))->row();
			$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : '';
			if ($st !== '' && $st !== 'PENDING') {
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cannot delete a prescription after verification/workflow has started. Current status: <strong>" . htmlspecialchars($st) . "</strong>.</div>");
				$this->session->set_userdata('abc', '1');
				redirect(base_url().'app/nurse_module/medication/'.$iop_no.'/'.$patient_no,$this->data);
				return;
			}
		}
		
		$this->db->query("UPDATE iop_medication SET InActive = 1 WHERE iop_med_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication successfully Deleted!</div>");
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/medication/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	
	
	
	
	public function intake_output(){
		
		$seg_iop = trim((string)$this->uri->segment(4));
		$seg_patient = trim((string)$this->uri->segment(5));
		$has_uri_params = ($seg_iop !== '' && $seg_patient !== '');
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1" || $has_uri_params){
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
			if (trim((string)$iop_no) === '' || trim((string)$patient_no) === '') {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
				redirect(base_url().'app/nurse_module/intake_output');
				return;
			}
			$req_iop_no = (string)$iop_no;
			$req_patient_no = (string)$patient_no;
			$this->load->library('EncounterContext');
			$ctx = $this->encountercontext->resolve('IPD', $iop_no, $patient_no, array(
				'include_billing' => false,
				'include_vitals' => false,
				'log_non_owner_view' => false,
				'hasAccesstoDoctor' => false,
				'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
			));
			if (!$ctx || !isset($ctx['ok']) || !$ctx['ok']) {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to resolve patient context.</div>");
				redirect(base_url().'app/nurse_module/intake_output');
				return;
			}
			$iop_no = $ctx['iop_no'];
			$patient_no = $ctx['patient_no'];
			if ($this->input->post('iop_no') == '' && $this->input->post('patient_no') == '' && ($req_patient_no === '' || $req_patient_no !== (string)$patient_no || $req_iop_no !== (string)$iop_no)) {
				redirect(base_url().'app/nurse_module/intake_output/'.url_safe_id($iop_no).'/'.url_safe_id($patient_no));
				return;
			}
			if (isset($ctx['data']) && is_array($ctx['data'])) {
				foreach ($ctx['data'] as $k => $v) {
					$this->data[$k] = $v;
				}
			}
		
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['medicineCategory'] = $this->opd_model->medicineCategory();
			$this->data['patientMedication'] = $this->opd_model->patientMedication($iop_no);
		
			// Ensure enhanced I/O schema (adds new columns safely)
			$this->nurse_enhancement_model->ensure_io_enhanced_schema();

			$this->data['getIntake'] = $this->ipd_model->getIntake($iop_no);
			$this->data['getOutput'] = $this->ipd_model->getOutput($iop_no);
			$this->data['glucoseReadings'] = $this->nurse_enhancement_model->get_glucose_readings($iop_no);
			$this->data['io_totals'] = $this->nurse_enhancement_model->get_io_totals($iop_no);
		
			$this->load->view("app/nurse_module/intake_output",$this->data);
		}else{
				// user restriction function
				$this->session->set_userdata('page_name','nurse_intake_output_reports');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function		
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_intake_output',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Intake/Output Record";
			$this->data['module'] = "intake_output";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
			
	}
	
	public function intake_history($iopNo = '', $patientNo = ''){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $iopNo !== '' || $patientNo !== '' || $this->session->userdata("abc") == "1"){
			$this->session->set_userdata("abc","");
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				$iop_no = $iopNo;
				$patient_no = $patientNo;
				if ($iop_no === '' && $patient_no === '') {
					list($iop_no, $patient_no) = $this->get_url_params(4, 5);
				}
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
			if ($iop_no === '' && $patient_no === '') {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
				redirect(base_url().'app/nurse_module/intake_history');
				return;
			}
			if ($iop_no !== '') {
				$req_iop_no = (string)$iop_no;
				$req_patient_no = (string)$patient_no;
				$this->load->library('EncounterContext');
				$ctx = $this->encountercontext->resolve('IPD', $iop_no, $patient_no, array(
					'include_billing' => false,
					'include_vitals' => false,
					'log_non_owner_view' => false,
					'hasAccesstoDoctor' => false,
					'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
				));
				if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
					$iop_no = $ctx['iop_no'];
					$patient_no = $ctx['patient_no'];
					if ($this->input->post('iop_no') == '' && $this->input->post('patient_no') == '' && ($req_patient_no === '' || $req_patient_no !== (string)$patient_no || $req_iop_no !== (string)$iop_no)) {
						redirect(base_url().'app/nurse_module/intake_history/'.url_safe_id($iop_no).'/'.url_safe_id($patient_no));
						return;
					}
					if (isset($ctx['data']) && is_array($ctx['data'])) {
						foreach ($ctx['data'] as $k => $v) {
							$this->data[$k] = $v;
						}
					}
				} else {
					$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
					$this->data['patientInfo'] = $patient_no !== '' ? $this->patient_model->getPatientInfo($patient_no) : null;
				}
			} else {
				$this->data['getOPDPatient'] = null;
				$this->data['patientInfo'] = $patient_no !== '' ? $this->patient_model->getPatientInfo($patient_no) : null;
			}
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['iop_no'] = $iop_no;
			$this->data['patient_no'] = $patient_no;
			$intakeHistory = $this->get_legacy_intake_history($iop_no, $patient_no);
			if ($intakeHistory === false) {
				$this->data['message'] = "<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to load intake records. Please try again.</div>";
				$intakeHistory = array();
			}
			$this->data['intake_history'] = $intakeHistory;
			$this->load->view("app/nurse_module/intake_history",$this->data);
		}else{
			$this->session->set_userdata('page_name','nurse_intake_history_reports');
			$page_id = $this->general_model->getPageID();
			$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
				redirect(base_url().'access_denied');
			}

			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_intake_history',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Intake History";
			$this->data['module'] = "intake_history";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}

	protected function get_legacy_intake_history($iopId = '', $patientNo = '')
	{
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if ($iopId === '' && $patientNo === '') {
			return array();
		}
		if (!$this->_nurse_table_exists('iop_intake_record', 'iop_intake_record_schema')) {
			return false;
		}

		$this->db->from('iop_intake_record');
		$this->db->where('InActive', 0);
		if ($iopId !== '') {
			$this->db->where('iop_id', $iopId);
		} elseif ($patientNo !== '' && $this->db->field_exists('patient_no', 'iop_intake_record')) {
			$this->db->where('patient_no', $patientNo);
		} else {
			return array();
		}
		$this->db->order_by('dDateTime', 'DESC');
		$this->db->order_by('intake_id', 'DESC');
		$query = $this->db->get();
		return $query ? $query->result() : false;
	}

	public function io_balance_history($iopNo = '', $patientNo = ''){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $iopNo !== '' || $patientNo !== '' || $this->session->userdata("abc") == "1"){
			$this->session->set_userdata("abc","");
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				$iop_no = $iopNo;
				$patient_no = $patientNo;
				if ($iop_no === '' && $patient_no === '') {
					list($iop_no, $patient_no) = $this->get_url_params(4, 5);
				}
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
			if ($iop_no === '' && $patient_no === '') {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
				redirect(base_url().'app/nurse_module/io_balance_history');
				return;
			}
			if ($iop_no !== '') {
				$req_iop_no = (string)$iop_no;
				$req_patient_no = (string)$patient_no;
				$this->load->library('EncounterContext');
				$ctx = $this->encountercontext->resolve('IPD', $iop_no, $patient_no, array(
					'include_billing' => false,
					'include_vitals' => false,
					'log_non_owner_view' => false,
					'hasAccesstoDoctor' => false,
					'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
				));
				if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
					$iop_no = $ctx['iop_no'];
					$patient_no = $ctx['patient_no'];
					if ($this->input->post('iop_no') == '' && $this->input->post('patient_no') == '' && ($req_patient_no === '' || $req_patient_no !== (string)$patient_no || $req_iop_no !== (string)$iop_no)) {
						redirect(base_url().'app/nurse_module/io_balance_history/'.url_safe_id($iop_no).'/'.url_safe_id($patient_no));
						return;
					}
					if (isset($ctx['data']) && is_array($ctx['data'])) {
						foreach ($ctx['data'] as $k => $v) {
							$this->data[$k] = $v;
						}
					}
				} else {
					$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
					$this->data['patientInfo'] = $patient_no !== '' ? $this->patient_model->getPatientInfo($patient_no) : null;
				}
			} else {
				$this->data['getOPDPatient'] = null;
				$this->data['patientInfo'] = $patient_no !== '' ? $this->patient_model->getPatientInfo($patient_no) : null;
			}
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['iop_no'] = $iop_no;
			$this->data['patient_no'] = $patient_no;
			$this->data['io_balance_history'] = $this->get_legacy_io_balance_history($iop_no, $patient_no);
			$this->load->view("app/nurse_module/io_balance_history",$this->data);
		}else{
			$this->session->set_userdata('page_name','nurse_io_balance_history_reports');
			$page_id = $this->general_model->getPageID();
			$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
				redirect(base_url().'access_denied');
			}

			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_io_balance_history',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Intake/Output Balance History";
			$this->data['module'] = "io_balance_history";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}

	protected function get_legacy_io_balance_history($iopId = '', $patientNo = '')
	{
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if ($iopId === '' && $patientNo === '') {
			return array();
		}

		$history = array();
		if ($this->_nurse_table_exists('iop_intake_record', 'iop_intake_record_schema')) {
			$this->db->from('iop_intake_record');
			$this->db->where('InActive', 0);
			if ($iopId !== '') {
				$this->db->where('iop_id', $iopId);
			} elseif ($patientNo !== '' && $this->db->field_exists('patient_no', 'iop_intake_record')) {
				$this->db->where('patient_no', $patientNo);
			} else {
				return array();
			}
			$this->db->order_by('dDateTime', 'DESC');
			if ($this->db->field_exists('intake_id', 'iop_intake_record')) {
				$this->db->order_by('intake_id', 'DESC');
			}
			$query = $this->db->get();
			if ($query) {
				foreach($query->result() as $row) {
					$intakeTotal = (float)(isset($row->IV_fluids) ? $row->IV_fluids : 0) + (float)(isset($row->oral) ? $row->oral : 0);
					if (isset($row->blood_loss)) {
						$intakeTotal += (float)$row->blood_loss;
					}
					$record = new stdClass();
					$record->type = 'intake';
					$record->record_id = isset($row->intake_id) ? $row->intake_id : 0;
					$record->iop_id = isset($row->iop_id) ? $row->iop_id : $iopId;
					$record->dDateTime = isset($row->dDateTime) ? $row->dDateTime : '';
					$record->intake_total = $intakeTotal;
					$record->output_total = 0;
					$record->balance = $intakeTotal;
					$record->cPreparedBy = isset($row->cPreparedBy) ? $row->cPreparedBy : '';
					$history[] = $record;
				}
			}
		}

		if ($this->_nurse_table_exists('iop_output_record', 'iop_output_record_schema')) {
			$this->db->from('iop_output_record');
			$this->db->where('InActive', 0);
			if ($iopId !== '') {
				$this->db->where('iop_id', $iopId);
			} elseif ($patientNo !== '' && $this->db->field_exists('patient_no', 'iop_output_record')) {
				$this->db->where('patient_no', $patientNo);
			} else {
				return $history;
			}
			$this->db->order_by('dDateTime', 'DESC');
			if ($this->db->field_exists('output_id', 'iop_output_record')) {
				$this->db->order_by('output_id', 'DESC');
			}
			$query = $this->db->get();
			if ($query) {
				foreach($query->result() as $row) {
					$outputTotal = (float)(isset($row->urine) ? $row->urine : 0) + (float)(isset($row->feaces) ? $row->feaces : 0) + (float)(isset($row->respitation) ? $row->respitation : 0) + (float)(isset($row->skin) ? $row->skin : 0);
					$record = new stdClass();
					$record->type = 'output';
					$record->record_id = isset($row->output_id) ? $row->output_id : 0;
					$record->iop_id = isset($row->iop_id) ? $row->iop_id : $iopId;
					$record->dDateTime = isset($row->dDateTime) ? $row->dDateTime : '';
					$record->intake_total = 0;
					$record->output_total = $outputTotal;
					$record->balance = 0 - $outputTotal;
					$record->cPreparedBy = isset($row->cPreparedBy) ? $row->cPreparedBy : '';
					$history[] = $record;
				}
			}
		}

		usort($history, function($left, $right) {
			$leftTime = isset($left->dDateTime) ? strtotime($left->dDateTime) : 0;
			$rightTime = isset($right->dDateTime) ? strtotime($right->dDateTime) : 0;
			if ($leftTime == $rightTime) {
				return 0;
			}
			return ($leftTime < $rightTime) ? 1 : -1;
		});
		return $history;
	}

	public function io_balance_detail($type = '', $recordId = '', $iopId = '', $patientNo = '')
	{
		$type = strtolower(trim((string)$type));
		$recordId = (int)$recordId;
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if (($type !== 'intake' && $type !== 'output') || $recordId <= 0) {
			$historyUrl = base_url().'app/nurse_module/io_balance_history/'.$iopId.'/'.$patientNo;
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Balance record not found.</div>");
			redirect($historyUrl);
			return;
		}

		$record = $type === 'intake' ? $this->get_legacy_intake_detail_record($recordId) : $this->get_legacy_output_detail_record($recordId);
		if (!$record) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Balance record not found.</div>");
			redirect($historyUrl);
			return;
		}

		if ($iopId === '' && isset($record->iop_id)) {
			$iopId = $record->iop_id;
		}
		$req_patient_no = (string)$patientNo;
		if ($iopId !== '') {
			$this->load->library('EncounterContext');
			$ctx = $this->encountercontext->resolve('IPD', $iopId, $patientNo, array(
				'include_billing' => false,
				'include_vitals' => false,
				'log_non_owner_view' => false,
				'hasAccesstoDoctor' => false,
				'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
			));
			if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
				$patientNo = (string)$ctx['patient_no'];
				if ($req_patient_no === '' || $req_patient_no !== (string)$patientNo) {
					redirect(base_url().'app/nurse_module/io_balance_detail/'.$type.'/'.$recordId.'/'.url_safe_id($iopId).'/'.url_safe_id($patientNo));
					return;
				}
				if (isset($ctx['data']) && is_array($ctx['data'])) {
					foreach ($ctx['data'] as $k => $v) {
						$this->data[$k] = $v;
					}
				}
			}
		}
		if (!isset($this->data['getOPDPatient'])) {
			$this->data['getOPDPatient'] = $iopId !== '' ? $this->ipd_model->getIPDPatient($iopId) : null;
		}
		if (!isset($this->data['patientInfo'])) {
			$this->data['patientInfo'] = $patientNo !== '' ? $this->patient_model->getPatientInfo($patientNo) : null;
		}
		$historyUrl = base_url().'app/nurse_module/io_balance_history/'.$iopId.'/'.$patientNo;
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['type'] = $type;
		$this->data['record'] = $record;
		$this->data['iop_no'] = $iopId;
		$this->data['patient_no'] = $patientNo;
		$this->data['back_url'] = base_url().'app/nurse_module/io_balance_history/'.$iopId.'/'.$patientNo;
		$this->load->view("app/nurse_module/io_balance_detail",$this->data);
	}

	protected function get_legacy_intake_detail_record($recordId)
	{
		$recordId = (int)$recordId;
		if ($recordId <= 0 || !$this->_nurse_table_exists('iop_intake_record', 'iop_intake_record_schema')) {
			return null;
		}
		$this->db->from('iop_intake_record');
		$this->db->where('intake_id', $recordId);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$query = $this->db->get();
		return ($query && $query->num_rows() > 0) ? $query->row() : null;
	}

	protected function get_legacy_output_detail_record($recordId)
	{
		$recordId = (int)$recordId;
		if ($recordId <= 0 || !$this->_nurse_table_exists('iop_output_record', 'iop_output_record_schema')) {
			return null;
		}
		$this->db->from('iop_output_record');
		$this->db->where('output_id', $recordId);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$query = $this->db->get();
		return ($query && $query->num_rows() > 0) ? $query->row() : null;
	}

	public function vitals_history($iopNo = '', $patientNo = ''){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $iopNo !== '' || $patientNo !== '' || $this->session->userdata("abc") == "1"){
			$this->session->set_userdata("abc","");
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				$iop_no = $iopNo;
				$patient_no = $patientNo;
				if ($iop_no === '' && $patient_no === '') {
					list($iop_no, $patient_no) = $this->get_url_params(4, 5);
				}
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
			if ($iop_no === '' && $patient_no === '') {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
				redirect(base_url().'app/nurse_module/vitals_history');
				return;
			}
			if ($iop_no !== '') {
				$req_iop_no = (string)$iop_no;
				$req_patient_no = (string)$patient_no;
				$this->load->library('EncounterContext');
				$ctx = $this->encountercontext->resolve('IPD', $iop_no, $patient_no, array(
					'include_billing' => false,
					'include_vitals' => false,
					'log_non_owner_view' => false,
					'hasAccesstoDoctor' => false,
					'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
				));
				if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
					$iop_no = $ctx['iop_no'];
					$patient_no = $ctx['patient_no'];
					if ($this->input->post('iop_no') == '' && $this->input->post('patient_no') == '' && ($req_patient_no === '' || $req_patient_no !== (string)$patient_no || $req_iop_no !== (string)$iop_no)) {
						redirect(base_url().'app/nurse_module/vitals_history/'.url_safe_id($iop_no).'/'.url_safe_id($patient_no));
						return;
					}
					if (isset($ctx['data']) && is_array($ctx['data'])) {
						foreach ($ctx['data'] as $k => $v) {
							$this->data[$k] = $v;
						}
					}
				} else {
					$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
					$this->data['patientInfo'] = $patient_no !== '' ? $this->patient_model->getPatientInfo($patient_no) : null;
				}
			} else {
				$this->data['getOPDPatient'] = null;
				$this->data['patientInfo'] = $patient_no !== '' ? $this->patient_model->getPatientInfo($patient_no) : null;
			}
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['iop_no'] = $iop_no;
			$this->data['patient_no'] = $patient_no;
			$this->data['vitals_history'] = $this->get_legacy_vitals_history($iop_no, $patient_no);
			$this->load->view("app/nurse_module/vitals_history",$this->data);
		}else{
			$this->session->set_userdata('page_name','nurse_vitals_history_reports');
			$page_id = $this->general_model->getPageID();
			$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
				redirect(base_url().'access_denied');
			}

			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_vitals_history',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Vitals History";
			$this->data['module'] = "vitals_history";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}

	protected function get_legacy_vitals_history($iopId = '', $patientNo = '')
	{
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if ($iopId === '' && $patientNo === '') {
			return array();
		}
		if (!$this->_nurse_table_exists('iop_vital_parameters', 'iop_vital_parameters_schema')) {
			return array();
		}

		$this->db->from('iop_vital_parameters');
		$this->db->where('InActive', 0);
		if ($iopId !== '') {
			$this->db->where('iop_id', $iopId);
		} elseif ($patientNo !== '' && $this->db->field_exists('patient_no', 'iop_vital_parameters')) {
			$this->db->where('patient_no', $patientNo);
		} else {
			return array();
		}
		$this->db->order_by('dDateTime', 'DESC');
		if ($this->db->field_exists('vital_id', 'iop_vital_parameters')) {
			$this->db->order_by('vital_id', 'DESC');
		}
		$query = $this->db->get();
		return $query ? $query->result() : array();
	}

	public function vitals_detail($vitalId = '', $iopId = '', $patientNo = '')
	{
		$vitalId = (int)$vitalId;
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if ($vitalId <= 0 && $iopId === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/vitals_history');
			return;
		}
		$record = $this->get_legacy_vitals_detail($vitalId);
		if (!$record && $iopId !== '') {
			$record = $this->get_latest_legacy_vitals_detail($iopId);
		}
		if ($record === false) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to load vitals record. Please try again.</div>");
			redirect(base_url().'app/nurse_module/vitals_history/'.$iopId.'/'.$patientNo);
			return;
		}
		if (!$record) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vitals record not found.</div>");
			redirect(base_url().'app/nurse_module/vitals_history/'.$iopId.'/'.$patientNo);
			return;
		}

		if ($iopId === '' && isset($record->iop_id)) {
			$iopId = $record->iop_id;
		}
		$req_patient_no = (string)$patientNo;
		if ($iopId !== '') {
			$this->load->library('EncounterContext');
			$ctx = $this->encountercontext->resolve('IPD', $iopId, $patientNo, array(
				'include_billing' => false,
				'include_vitals' => false,
				'log_non_owner_view' => false,
				'hasAccesstoDoctor' => false,
				'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
			));
			if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
				$patientNo = (string)$ctx['patient_no'];
				if ($req_patient_no === '' || $req_patient_no !== (string)$patientNo) {
					redirect(base_url().'app/nurse_module/vitals_detail/'.$vitalId.'/'.url_safe_id($iopId).'/'.url_safe_id($patientNo));
					return;
				}
				if (isset($ctx['data']) && is_array($ctx['data'])) {
					foreach ($ctx['data'] as $k => $v) {
						$this->data[$k] = $v;
					}
				}
			}
		}
		$this->data['vitals'] = $record;
		$this->data['iop_no'] = $iopId;
		$this->data['patient_no'] = $patientNo;
		if (!isset($this->data['patientInfo'])) {
			$this->data['patientInfo'] = $patientNo !== '' ? $this->patient_model->getPatientInfo($patientNo) : null;
		}
		if (!isset($this->data['getOPDPatient'])) {
			$this->data['getOPDPatient'] = $iopId !== '' ? $this->ipd_model->getIPDPatient($iopId) : null;
		}
		$this->data['back_url'] = base_url().'app/nurse_module/vitals_history/'.$iopId.'/'.$patientNo;
		$this->load->view("app/nurse_module/vitals_detail",$this->data);
	}

	protected function get_legacy_vitals_detail($vitalId)
	{
		$vitalId = (int)$vitalId;
		if ($vitalId <= 0) {
			return null;
		}
		if (!$this->_nurse_table_exists('iop_vital_parameters', 'iop_vital_parameters_schema')) {
			return false;
		}
		if (!$this->db->field_exists('vital_id', 'iop_vital_parameters')) {
			return false;
		}
		$this->db->from('iop_vital_parameters');
		$this->db->where('vital_id', $vitalId);
		$this->db->where('InActive', 0);
		$this->db->order_by('dDateTime', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get();
		return $query ? $query->row() : false;
	}

	protected function get_latest_legacy_vitals_detail($iopId)
	{
		$iopId = trim((string)$iopId);
		if ($iopId === '') {
			return null;
		}
		if (!$this->_nurse_table_exists('iop_vital_parameters', 'iop_vital_parameters_schema')) {
			return false;
		}
		$this->db->from('iop_vital_parameters');
		$this->db->where('iop_id', $iopId);
		$this->db->where('InActive', 0);
		$this->db->order_by('dDateTime', 'DESC');
		if ($this->db->field_exists('vital_id', 'iop_vital_parameters')) {
			$this->db->order_by('vital_id', 'DESC');
		}
		$this->db->limit(1);
		$query = $this->db->get();
		return $query ? $query->row() : false;
	}

	public function intake_detail($intakeId = '', $iopId = '', $patientNo = '')
	{
		$intakeId = (int)$intakeId;
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if ($intakeId <= 0 && $iopId === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/intake_history');
			return;
		}
		$record = $this->get_legacy_intake_detail($intakeId);
		if (!$record && $iopId !== '') {
			$record = $this->get_latest_legacy_intake_detail($iopId);
		}
		if ($record === false) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to load intake record. Please try again.</div>");
			redirect(base_url().'app/nurse_module/intake_history/'.$iopId.'/'.$patientNo);
			return;
		}
		if (!$record) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Intake record not found.</div>");
			redirect(base_url().'app/nurse_module/intake_history/'.$iopId.'/'.$patientNo);
			return;
		}

		if ($iopId === '' && isset($record->iop_id)) {
			$iopId = $record->iop_id;
		}
		$req_patient_no = (string)$patientNo;
		if ($iopId !== '') {
			$this->load->library('EncounterContext');
			$ctx = $this->encountercontext->resolve('IPD', $iopId, $patientNo, array(
				'include_billing' => false,
				'include_vitals' => false,
				'log_non_owner_view' => false,
				'hasAccesstoDoctor' => false,
				'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
			));
			if ($ctx && isset($ctx['ok']) && $ctx['ok']) {
				$patientNo = (string)$ctx['patient_no'];
				if ($req_patient_no === '' || $req_patient_no !== (string)$patientNo) {
					redirect(base_url().'app/nurse_module/intake_detail/'.$intakeId.'/'.url_safe_id($iopId).'/'.url_safe_id($patientNo));
					return;
				}
				if (isset($ctx['data']) && is_array($ctx['data'])) {
					foreach ($ctx['data'] as $k => $v) {
						$this->data[$k] = $v;
					}
				}
			}
		}
		$this->data['intake'] = $record;
		$this->data['iop_no'] = $iopId;
		$this->data['patient_no'] = $patientNo;
		if (!isset($this->data['patientInfo'])) {
			$this->data['patientInfo'] = $patientNo !== '' ? $this->patient_model->getPatientInfo($patientNo) : null;
		}
		if (!isset($this->data['getOPDPatient'])) {
			$this->data['getOPDPatient'] = $iopId !== '' ? $this->ipd_model->getIPDPatient($iopId) : null;
		}
		$this->data['back_url'] = base_url().'app/nurse_module/intake_history/'.$iopId.'/'.$patientNo;
		$this->load->view("app/nurse_module/intake_detail",$this->data);
	}

	protected function get_legacy_intake_detail($intakeId)
	{
		$intakeId = (int)$intakeId;
		if ($intakeId <= 0) {
			return null;
		}
		if (!$this->_nurse_table_exists('iop_intake_record', 'iop_intake_record_schema')) {
			return false;
		}
		$this->db->from('iop_intake_record');
		$this->db->where('intake_id', $intakeId);
		$this->db->where('InActive', 0);
		$this->db->order_by('dDateTime', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get();
		return $query ? $query->row() : null;
	}

	protected function get_latest_legacy_intake_detail($iopId)
	{
		$iopId = trim((string)$iopId);
		if ($iopId === '') {
			return null;
		}
		if (!$this->_nurse_table_exists('iop_intake_record', 'iop_intake_record_schema')) {
			return false;
		}
		$this->db->from('iop_intake_record');
		$this->db->where('iop_id', $iopId);
		$this->db->where('InActive', 0);
		$this->db->order_by('dDateTime', 'DESC');
		$this->db->order_by('intake_id', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get();
		return $query ? $query->row() : null;
	}
	
	public function save_intake(){
		$iopId = trim((string)$this->input->post('opd_no'));
		$patientNo = trim((string)$this->input->post('patient_no'));
		$ivFluids = $this->clinical_intake_non_negative_int($this->input->post('fluids'));
		$oral = $this->clinical_intake_non_negative_int($this->input->post('oral'));
		$bloodLoss = $this->clinical_intake_non_negative_int($this->input->post('blood_loss'));
		$noStool = $this->clinical_intake_non_negative_int($this->input->post('no_stool'));
		$noUrine = $this->clinical_intake_non_negative_int($this->input->post('no_urine'));
		$dDate = trim((string)$this->input->post('dDate'));
		$cTime = trim((string)$this->input->post('cTime'));
		$dDate = $dDate !== '' ? $dDate : date('Y-m-d');
		$cTime = $cTime !== '' ? $cTime : date('H:i:s');
		if ($iopId === '' || $patientNo === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/intake_output');
			return;
		}
		if ($ivFluids === null || $oral === null || $bloodLoss === null || $noStool === null || $noUrine === null) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Intake values must be numeric and cannot be negative.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		if (!$this->_nurse_table_exists('iop_intake_record', 'iop_intake_record_schema')) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save intake record. Please try again.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		$this->data = array(
			'iop_id'		=>		$iopId,
			'particulars'	=>		$this->input->post('particular'),
			'IV_fluids'		=>		$ivFluids,
			'oral'			=>		$oral,
			'no_stool'		=>		$noStool,
			'no_urine'		=>		$noUrine,
			'dDate'			=>		$dDate,
			'dDateTime'		=>		$dDate." ".$cTime,
			'cPreparedBy'	=>		$this->session->userdata('user_id'),
			'InActive'		=>		0
		);	
		if ($this->db->field_exists('blood_loss', 'iop_intake_record')) {
			$this->data['blood_loss'] = $bloodLoss;
		}
		// Enhanced I/O fields
		if ($this->db->field_exists('blood_products', 'iop_intake_record')) {
			$this->data['blood_products'] = $this->clinical_intake_non_negative_int($this->input->post('blood_products'));
		}
		if ($this->db->field_exists('ng_tube_feeds', 'iop_intake_record')) {
			$this->data['ng_tube_feeds'] = $this->clinical_intake_non_negative_int($this->input->post('ng_tube_feeds'));
		}
		if (!$this->db->insert("iop_intake_record",$this->data)) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save intake record. Please try again.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		$this->clinical_try_record_intake($this->data, $patientNo);
		$this->load->model('app/encounter_timeline_model');
		$this->encounter_timeline_model->append_event(
			$iopId,
			$patientNo,
			'IPD',
			'NURSE_INTAKE',
			'Intake recorded',
			array(
				'iv_fluids' => $ivFluids,
				'oral' => $oral,
				'blood_loss' => $bloodLoss,
				'datetime' => isset($this->data['dDateTime']) ? (string)$this->data['dDateTime'] : null,
			),
			(string)$this->session->userdata('user_id')
		);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Intake Record successfully Added!</div>");
		
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo,$this->data);
	}

	protected function clinical_try_record_intake(array $legacyData, $patientNo)
	{
		$this->config->load('clinical_runtime', true);
		$ctmEnabled = (bool)$this->config->item('clinical_ctm_enabled', 'clinical_runtime');
		$dualWriteEnabled = (bool)$this->config->item('clinical_intake_ctm_dual_write_enabled', 'clinical_runtime');
		if (!$ctmEnabled || !$dualWriteEnabled) {
			return;
		}

		try {
			$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
			if (!file_exists($factory)) {
				log_message('debug', 'clinical_intake_dual_write skipped factory_missing');
				return;
			}
			require_once($factory);
			ClinicalReplayFactory::loadDependencies();

			$payload = $this->clinical_normalize_intake_payload($legacyData, $patientNo);
			if ($payload === null) {
				log_message('debug', 'clinical_intake_dual_write skipped invalid_payload');
				return;
			}

			$leaseSeconds = (int)$this->config->item('clinical_ctm_lease_seconds', 'clinical_runtime');
			$prefix = (string)$this->config->item('clinical_ctm_lease_owner_prefix', 'clinical_runtime');
			if ($prefix === '') {
				$prefix = 'hms-clinical-web';
			}

			$service = new IntakeService(
				new CiClinicalTransactionManager($this->db, $leaseSeconds, $prefix . ':' . getmypid()),
				new CiClinicalEventWriter($this->db)
			);
			$result = $service->record($payload);
			$out = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;
			log_message('debug', 'clinical_intake_dual_write success ' . json_encode($out));
		} catch (Throwable $e) {
			log_message('debug', 'clinical_intake_dual_write failed ' . $e->getMessage());
		}
	}

	protected function clinical_normalize_intake_payload(array $legacyData, $patientNo)
	{
		$iopId = isset($legacyData['iop_id']) ? trim((string)$legacyData['iop_id']) : '';
		$patientNo = trim((string)$patientNo);
		$actorUserId = isset($legacyData['cPreparedBy']) ? trim((string)$legacyData['cPreparedBy']) : '';
		$oralMl = $this->clinical_intake_non_negative_int(isset($legacyData['oral']) ? $legacyData['oral'] : 0);
		$ivMl = $this->clinical_intake_non_negative_int(isset($legacyData['IV_fluids']) ? $legacyData['IV_fluids'] : 0);
		if ($iopId === '' || $actorUserId === '' || $oralMl === null || $ivMl === null) {
			return null;
		}
		$recordedAt = isset($legacyData['dDateTime']) ? trim((string)$legacyData['dDateTime']) : '';
		if ($recordedAt === '') {
			$recordedAt = date('Y-m-d H:i:s');
		}
		$idempotencyKey = $this->input->post('idempotency_key');
		if (trim((string)$idempotencyKey) === '') {
			$idempotencyKey = hash('sha256', implode('|', array($iopId, $patientNo, $actorUserId, $recordedAt, $ivMl, $oralMl, isset($legacyData['particulars']) ? $legacyData['particulars'] : '')));
		}

		return array(
			'iop_id' => $iopId,
			'patient_no' => $patientNo,
			'actor_user_id' => $actorUserId,
			'idempotency_key' => $idempotencyKey,
			'particulars' => isset($legacyData['particulars']) ? $legacyData['particulars'] : '',
			'oral_ml' => $oralMl,
			'iv_ml' => $ivMl,
			'iv_fluids_ml' => $ivMl,
			'blood_ml' => 0,
			'recorded_at' => $recordedAt,
			'created_at' => date('Y-m-d H:i:s'),
		);
	}

	protected function clinical_intake_non_negative_int($value)
	{
		if ($value === null || $value === '') {
			return 0;
		}
		if (!is_numeric($value)) {
			return null;
		}
		$value = (int)$value;
		return $value < 0 ? null : $value;
	}

	protected function nursing_vitals_non_negative_number($value)
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_numeric($value)) {
			return false;
		}
		$value = $value + 0;
		return $value < 0 ? false : $value;
	}
	
	public function save_output(){
		$iopId = trim((string)$this->input->post('opd_no'));
		$patientNo = trim((string)$this->input->post('patient_no'));
		$urine = $this->clinical_intake_non_negative_int($this->input->post('urine'));
		$feaces = $this->clinical_intake_non_negative_int($this->input->post('feaces'));
		$respitation = $this->clinical_intake_non_negative_int($this->input->post('respitation'));
		$skin = $this->clinical_intake_non_negative_int($this->input->post('skin'));
		$dDate = trim((string)$this->input->post('dDate2'));
		$cTime = trim((string)$this->input->post('cTime2'));
		$dDate = $dDate !== '' ? $dDate : date('Y-m-d');
		$cTime = $cTime !== '' ? $cTime : date('H:i:s');
		if ($iopId === '' || $patientNo === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/intake_output');
			return;
		}
		if ($urine === null || $feaces === null || $respitation === null || $skin === null) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Output values must be numeric and cannot be negative.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		if (!$this->_nurse_table_exists('iop_output_record', 'iop_output_record_schema')) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save output record. Please try again.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		$this->data = array(
			'iop_id'		=>		$iopId,
			'urine'			=>		$urine,
			'feaces'		=>		$feaces,
			'respitation'	=>		$respitation,
			'skin'			=>		$skin,
			'dDate'			=>		$dDate,
			'dDateTime'		=>		$dDate." ".$cTime,
			'cPreparedBy'	=>		$this->session->userdata('user_id'),
			'InActive'		=>		0
		);
		// Enhanced output fields
		if ($this->db->field_exists('vomit', 'iop_output_record')) {
			$this->data['vomit'] = $this->clinical_intake_non_negative_int($this->input->post('vomit'));
		}
		if ($this->db->field_exists('drainage', 'iop_output_record')) {
			$this->data['drainage'] = $this->clinical_intake_non_negative_int($this->input->post('drainage'));
		}
		if ($this->db->field_exists('drainage_site', 'iop_output_record')) {
			$site = trim((string)$this->input->post('drainage_site'));
			$this->data['drainage_site'] = $site !== '' ? $site : null;
		}
		if ($this->db->field_exists('stool_count', 'iop_output_record')) {
			$this->data['stool_count'] = $this->clinical_intake_non_negative_int($this->input->post('stool_count'));
		}
		if ($this->db->field_exists('stool_consistency', 'iop_output_record')) {
			$consistency = trim((string)$this->input->post('stool_consistency'));
			$this->data['stool_consistency'] = $consistency !== '' ? $consistency : null;
		}
		if (!$this->db->insert("iop_output_record",$this->data)) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save output record. Please try again.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		$this->load->model('app/encounter_timeline_model');
		$this->encounter_timeline_model->append_event(
			$iopId,
			$patientNo,
			'IPD',
			'NURSE_OUTPUT',
			'Output recorded',
			array(
				'urine' => $urine,
				'feaces' => $feaces,
				'respiration' => $respitation,
				'skin' => $skin,
				'datetime' => isset($this->data['dDateTime']) ? (string)$this->data['dDateTime'] : null,
			),
			(string)$this->session->userdata('user_id')
		);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Output Record successfully Added!</div>");
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo,$this->data);
	}

	/**
	 * Save a blood glucose reading (Ghana standard: mmol/L).
	 */
	public function save_glucose(){
		$iopId = trim((string)$this->input->post('opd_no'));
		$patientNo = trim((string)$this->input->post('patient_no'));
		if ($iopId === '' || $patientNo === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning'><i class='fa fa-warning'></i> Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/intake_output');
			return;
		}
		$glucoseValue = $this->input->post('glucose_value');
		if (!is_numeric($glucoseValue) || (float)$glucoseValue <= 0) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger'><i class='fa fa-warning'></i> Glucose value must be a positive number (mmol/L).</div>");
			$this->session->set_userdata('abc','1');
			redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
			return;
		}
		$dDate = trim((string)$this->input->post('glucose_date'));
		$cTime = trim((string)$this->input->post('glucose_time'));
		$dDate = $dDate !== '' ? $dDate : date('Y-m-d');
		$cTime = $cTime !== '' ? $cTime : date('H:i:s');

		$this->nurse_enhancement_model->save_glucose_reading(
			$iopId,
			(float)$glucoseValue,
			$this->input->post('glucose_type'),
			$dDate,
			$dDate.' '.$cTime,
			$this->input->post('glucose_notes'),
			$this->session->userdata('user_id')
		);
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Glucose reading saved.</div>");
		$this->session->set_userdata('abc','1');
		redirect(base_url().'app/nurse_module/intake_output/'.$iopId.'/'.$patientNo);
	}
	
	public function delete_intake(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/intake_output')) {
			return;
		}
		
		$this->db->query("UPDATE iop_intake_record SET InActive = 1 WHERE intake_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Intake Record successfully Deleted!</div>");
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/intake_output/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function delete_output(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/intake_output')) {
			return;
		}
		
		$this->db->query("UPDATE iop_output_record SET InActive = 1 WHERE output_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Output Record successfully Deleted!</div>");
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/intake_output/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	
	
	
	public function nurse_progress_note(){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
			
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/nurse_progress_note')) {
				return;
			}
			$ctx_result = $this->_apply_ipd_encounter_context($iop_no, $patient_no, 'app/nurse_module/nurse_progress_note');
			if (!$ctx_result) {
				return;
			}
			list($iop_no, $patient_no) = $ctx_result;
			
			$this->data['message'] = $this->session->flashdata('message');
		
			$this->data['getNurseProgressNote'] = $this->opd_model->getNurseProgressNote($iop_no);
			$this->load->view("app/nurse_module/nurse_progress_note",$this->data);	
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','nurse_progress_note_report');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function		
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_progress_note',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Nurse Progress Note";
			$this->data['module'] = "nurse_progress_note";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}
	
	public function save_nurse_progress_note(){
		if (!$this->_require_iop_patient_binding_or_redirect($this->input->post('opd_no'), $this->input->post('patient_no'), 'app/nurse_module/nurse_progress_note')) {
			return;
		}
			$this->data = array(
				'iop_id'		=>		$this->input->post('opd_no'),
				'dDate'			=>		$this->input->post('dDate'),
				'dDateTime'		=>		$this->input->post('dDate')." ".$this->input->post('cTime'),
				'focus'			=>		$this->input->post('focus'),
				'notes'			=>		$this->input->post('notes'),
				'cPreparedBy'	=>		$this->session->userdata('user_id'),
				'InActive'		=>		0
			);
			$this->db->insert('iop_nurse_notes',$this->data);
			$this->load->model('app/encounter_timeline_model');
			$this->encounter_timeline_model->append_event(
				(string)$this->input->post('opd_no'),
				(string)$this->input->post('patient_no'),
				'IPD',
				'NURSE_PROGRESS_NOTE',
				'Nurse progress note added',
				array(
					'focus' => (string)$this->input->post('focus'),
					'datetime' => (string)$this->input->post('dDate')." ".(string)$this->input->post('cTime'),
				),
				(string)$this->session->userdata('user_id')
			);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Notes successfully Added!</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/nurse_progress_note/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	public function delete_nurse_progress(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/nurse_progress_note')) {
			return;
		}
		
		$this->db->query("UPDATE iop_nurse_notes SET InActive = 1 WHERE nurse_notes_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Notes successfully Deleted!</div>");
		
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/nurse_progress_note/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	
	
	
	
	
	
	public function vitalSign(){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
		
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/vitalSign')) {
				return;
			}
			$ctx_result = $this->_apply_ipd_encounter_context($iop_no, $patient_no, 'app/nurse_module/vitalSign');
			if (!$ctx_result) {
				return;
			}
			list($iop_no, $patient_no) = $ctx_result;
			$this->data['nurse_enhancements_ready'] = $this->nurse_enhancement_model->tables_ready();
			if ($this->data['nurse_enhancements_ready']) {
				$this->data['getVital'] = $this->nurse_enhancement_model->get_vitals_with_extras($iop_no);
			} else {
				$this->data['getVital'] = $this->opd_model->getVital($iop_no);
			}
			$this->data['message'] = $this->session->flashdata('message');
		
			$this->load->view("app/nurse_module/vitalSign",$this->data);	
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','nurse_vital_sign_report');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function	
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_vital_sign',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Vital Sign";
			$this->data['module'] = "vitalSign";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}
	
	public function save_vitalSign(){
		$iopId = trim((string)$this->input->post('opd_no'));
		$patientNo = trim((string)$this->input->post('patient_no'));
		if (!$this->_require_iop_patient_binding_or_redirect($iopId, $patientNo, 'app/nurse_module/vitalSign')) {
			return;
		}
		$bp = trim((string)$this->input->post('bp'));
		$pulseRate = $this->nursing_vitals_non_negative_number($this->input->post('pulse_rate'));
		$temperature = $this->nursing_vitals_non_negative_number($this->input->post('temperature'));
		$height = $this->nursing_vitals_non_negative_number($this->input->post('height'));
		$respiration = $this->nursing_vitals_non_negative_number($this->input->post('respiration'));
		$weight = $this->nursing_vitals_non_negative_number($this->input->post('weight'));
		$spo2 = $this->nursing_vitals_non_negative_number($this->input->post('spo2'));
		$bloodSugar = $this->nursing_vitals_non_negative_number($this->input->post('blood_sugar'));
		$painScore = $this->nursing_vitals_non_negative_number($this->input->post('pain_score'));
		$dDate = trim((string)$this->input->post('dDate'));
		$cTime = trim((string)$this->input->post('cTime'));
		$dDate = $dDate !== '' ? $dDate : date('Y-m-d');
		$cTime = $cTime !== '' ? $cTime : date('H:i:s');
		if ($iopId === '' || $patientNo === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/vitalSign');
			return;
		}
		if ($pulseRate === false || $temperature === false || $height === false || $respiration === false || $weight === false || $spo2 === false || $bloodSugar === false || $painScore === false) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vitals values must be numeric and cannot be negative.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/vitalSign/'.$iopId.'/'.$patientNo);
			return;
		}
		if ($bp === '' && $pulseRate === null && $temperature === null && $height === null && $respiration === null && $weight === null) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>At least one vital sign is required.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/vitalSign/'.$iopId.'/'.$patientNo);
			return;
		}
		if (!$this->_nurse_table_exists('iop_vital_parameters', 'iop_vital_parameters_schema') || !$this->_nurse_table_exists('patient_details_iop', 'patient_details_iop_schema')) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save vital parameters. Please try again.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/vitalSign/'.$iopId.'/'.$patientNo);
			return;
		}
		$this->data = array(
				'iop_id'		=>		$iopId,
				'dDate'			=>		$dDate,
				'dDateTime'		=>		$dDate." ".$cTime,
				'pulse_rate'	=>		$pulseRate,
				'temperature'	=>		$temperature,
				'height'		=>		$height,
				'bp'			=>		$bp,
				'respiration'	=>		$respiration,
				'weight'		=>		$weight,
				'cPreparedBy'	=>		$this->session->userdata('user_id'),
				'InActive'		=>		0
			);
			if (!$this->db->insert('iop_vital_parameters',$this->data)) {
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save vital parameters. Please try again.</div>");
				$this->session->set_userdata("abc","1");
				redirect(base_url().'app/nurse_module/vitalSign/'.$iopId.'/'.$patientNo);
				return;
			}
			$vital_id = $this->db->insert_id();
			if ($this->nurse_enhancement_model->tables_ready()) {
				$this->nurse_enhancement_model->save_vital_extra(
					$vital_id,
					$spo2,
					$bloodSugar,
					$painScore,
					$this->session->userdata('user_id')
				);
			}
			// Mark vitals as DONE so the consultation gate passes
			$iop_id_val = $iopId;
			if ($iop_id_val) {
				$this->db->where('IO_ID', (string)$iop_id_val);
				if (!$this->db->update('patient_details_iop', array(
					'vitals_status'   => 'DONE',
					'vitals_nurse_id' => $this->session->userdata('user_id'),
					'vitals_at'       => date('Y-m-d H:i:s')
				))) {
					$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital parameters were saved, but status update failed.</div>");
					$this->session->set_userdata("abc","1");
					redirect(base_url().'app/nurse_module/vitalSign/'.$iopId.'/'.$patientNo);
					return;
				}
			}
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Parameters successfully Added!</div>");
			$this->load->model('app/encounter_timeline_model');
			$this->encounter_timeline_model->append_event(
				$iopId,
				$patientNo,
				'IPD',
				'NURSE_VITALS',
				'Vital signs recorded',
				array(
					'bp' => $bp,
					'pulse_rate' => $pulseRate,
					'temperature' => $temperature,
					'respiration' => $respiration,
					'weight' => $weight,
					'height' => $height,
					'datetime' => $dDate." ".$cTime,
				),
				(string)$this->session->userdata('user_id')
			);
			
			$this->session->set_userdata("abc","1");
			//redirect
			redirect(base_url().'app/nurse_module/vitalSign/'.$iopId.'/'.$patientNo,$this->data);	
	}
	
	public function delete_vital(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/vitalSign')) {
			return;
		}
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Parameters successfully Deleted!</div>");
		
		$this->db->query("UPDATE iop_vital_parameters SET InActive = 1 WHERE vital_id = ?", array((int)$id));
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/vitalSign/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	
	
	
	public function room_transfer(){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
		
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/room_transfer')) {
				return;
			}
			$ctx_result = $this->_apply_ipd_encounter_context($iop_no, $patient_no, 'app/nurse_module/room_transfer');
			if (!$ctx_result) {
				return;
			}
			list($iop_no, $patient_no) = $ctx_result;
			$this->data['message'] = $this->session->flashdata('message');
			
			$this->data['room_transfer'] = $this->ipd_model->room_transfer($iop_no);
			
			$this->data['particular_cat'] = $this->billing_model->particular_cat();
			$this->data['medicine_cat'] = $this->billing_model->medicine_cat();
			
			$this->data['getOperationTheater'] = $this->opd_model->getOperationTheater($iop_no);
			$this->data['room_category'] = $this->general_model->room_category();
		
			$this->load->view("app/nurse_module/room_transfer",$this->data);	
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','nurse_room_transfer_report');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function	
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_room_transfer',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Room transfer";
			$this->data['module'] = "room_transfer";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}
	
	public function save_room_transfer(){
		$iop_id = (string)$this->input->post('opd_no');
		$new_bed_id = (int)$this->input->post('bed_name');
		$patient_no = (string)$this->input->post('patient_no');
		if (!$this->_require_iop_patient_binding_or_redirect($iop_id, $patient_no, 'app/nurse_module/room_transfer')) {
			return;
		}

		if ($iop_id === '' || $new_bed_id <= 0) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to transfer: invalid admission/bed selection.</div>");
			$this->session->set_userdata("abc","1");
			redirect(base_url().'app/nurse_module/room_transfer/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
			return;
		}

		$this->load->model('app/bed_occupancy_model');
		$transfer = array(
			'dDate' => $this->input->post('dDate'),
			'dDateTime' => $this->input->post('dDate')." ".$this->input->post('dTime'),
			'room_category_id' => $this->input->post('roomType'),
			'room_master_id' => $this->input->post('room_name'),
			'reason' => $this->input->post('reason'),
			'cPreparedBy' => $this->session->userdata('user_id'),
		);
		$res = $this->bed_occupancy_model->transfer_ipd_bed(
			$iop_id,
			(string)$patient_no,
			$new_bed_id,
			$transfer
		);
		if (is_array($res) && isset($res['ok']) && $res['ok'] === true) {
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient successfully Transfered!</div>");
			$this->load->model('app/encounter_timeline_model');
			$this->encounter_timeline_model->append_event(
				$iop_id,
				(string)$patient_no,
				'IPD',
				'NURSE_ROOM_TRANSFER',
				'Room transfer completed',
				array(
					'bed_id' => $new_bed_id,
					'room_id' => (string)$this->input->post('room_name'),
					'room_category_id' => (string)$this->input->post('roomType'),
					'datetime' => (string)$this->input->post('dDate')." ".(string)$this->input->post('dTime'),
				),
				(string)$this->session->userdata('user_id')
			);
		} else {
			$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'unknown';
			$msg = ($err === 'admission_not_active')
				? 'Unable to transfer: admission is not active.'
				: (($err === 'bed_not_found') ? 'Unable to transfer: bed not found.' : (($err === 'bed_occupied') ? 'Unable to transfer: bed is already occupied.' : 'Transfer could not be completed.'));
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$msg}</div>");
		}
		
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/room_transfer/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	public function delete_room_transfer(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/room_transfer')) {
			return;
		}
		
		$this->db->query("UPDATE iop_room_transfer SET InActive = 1 WHERE transfer_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Room successfully Deleted!</div>");
		
		$this->session->set_userdata("abc","1");
		redirect(base_url().'app/nurse_module/room_transfer/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	
	
	
	
	
	public function patientHistory(){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
		
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/patientHistory')) {
				return;
			}
			$ctx_result = $this->_apply_ipd_encounter_context($iop_no, $patient_no, 'app/nurse_module/patientHistory');
			if (!$ctx_result) {
				return;
			}
			list($iop_no, $patient_no) = $ctx_result;
			$this->data['message'] = $this->session->flashdata('message');
		
			$this->load->view("app/nurse_module/patientHistory",$this->data);	
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','nurse_patientHistory_report');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function	
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_patientHistory',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient History";
			$this->data['module'] = "patientHistory";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}
	
	public function save_patientHistory(){
		if (!$this->current_user_is_admin()) {
			General::logfile('RBAC', 'DENY', 'NURSE|save_patientHistory|iop:'.$this->input->post('opd_no').'|p:'.$this->input->post('patient_no').'|u:'.$this->session->userdata('user_id'));
			redirect(base_url().'access_denied');
			return;
		}
		$iop_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/patientHistory')) {
			return;
		}
		
		$this->data = array(
			'allergies'				=>	$this->input->post('allergies'),
			'warnings'				=>	$this->input->post('warnings'),
			'social_history'		=>	$this->input->post('social_history'),
			'family_history'		=>	$this->input->post('family_history'),
			'personal_history'		=>	$this->input->post('personal_history'),
			'past_medical_history'	=>	$this->input->post('past_medical_history')
		);
		$this->db->where("IO_ID",$iop_no);
		$this->db->update("patient_details_iop",$this->data);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient History successfully Updated!</div>");
			
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);	
			
		$this->session->set_userdata("abc","1");	
		//redirect
		redirect(base_url().'app/nurse_module/patientHistory/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	
	
	
	
	
	
	public function discharge_summary(){
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
		
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/discharge_summary')) {
				return;
			}
			$ctx_result = $this->_apply_ipd_encounter_context($iop_no, $patient_no, 'app/nurse_module/discharge_summary');
			if (!$ctx_result) {
				return;
			}
			list($iop_no, $patient_no) = $ctx_result;
			$this->data['get_discharge_summary'] = $this->opd_model->get_discharge_summary($iop_no);
			$this->data['getConditionDis'] = $this->general_model->getConditionDis();
			$this->data['message'] = $this->session->flashdata('message');
		
		
			$this->load->view("app/nurse_module/discharge_summary",$this->data);	
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','nurse_discharge_summary_report');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function	
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_discharge_summary',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Discharge Summary";
			$this->data['module'] = "discharge_summary";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}
	
	public function save_discharge_summary(){
		if (!$this->current_user_is_admin()) {
			General::logfile('RBAC', 'DENY', 'NURSE|save_discharge_summary|iop:'.$this->input->post('opd_no').'|p:'.$this->input->post('patient_no').'|u:'.$this->session->userdata('user_id'));
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->_require_iop_patient_binding_or_redirect($this->input->post('opd_no'), $this->input->post('patient_no'), 'app/nurse_module/discharge_summary')) {
			return;
		}
		$this->db->query("DELETE FROM iop_discharge_summary WHERE iop_id = ?", array($this->input->post('opd_no')));
		
		$this->data = array(
			'iop_id'					=>		$this->input->post('opd_no'),
			'dDate'						=>		date("Y-m-d"),
			'dDateTime'					=>		date("Y-m-d h:i:s"),
			'reason_admission'			=>		$this->input->post('reason_admission'),
			'condition_upon_discharge'	=>		$this->input->post('condition'),
			'admitting_impression'		=>		$this->input->post('admitting_impression'),
			'final_diagnosis'			=>		$this->input->post('final_diagnosis'),
			'physical_exam_findings'	=>		$this->input->post('physical_exam_findings'),
			'course_ward'				=>		$this->input->post('course_ward'),
			'InActive'					=>		0
		);
		//$this->db->where("iop_id",$this->input->post('opd_no'));
		$this->db->insert("iop_discharge_summary",$this->data);
		$this->load->model('app/encounter_timeline_model');
		$this->encounter_timeline_model->append_event(
			(string)$this->input->post('opd_no'),
			(string)$this->input->post('patient_no'),
			'IPD',
			'NURSE_DISCHARGE_SUMMARY',
			'Discharge summary saved',
			array(
				'condition' => (string)$this->input->post('condition'),
				'final_diagnosis' => (string)$this->input->post('final_diagnosis'),
			),
			(string)$this->session->userdata('user_id')
		);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Discharge summary successfully Added!</div>");
		$this->session->set_userdata("abc","1");	
		redirect(base_url().'app/nurse_module/discharge_summary/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	
	
	
	public function bed_side_procedure(){
		
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
		
			if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/bed_side_procedure')) {
				return;
			}
			$ctx_result = $this->_apply_ipd_encounter_context($iop_no, $patient_no, 'app/nurse_module/bed_side_procedure');
			if (!$ctx_result) {
				return;
			}
			list($iop_no, $patient_no) = $ctx_result;
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['getServices'] = $this->opd_model->getServices($iop_no);
			
			$this->data['particular_cat'] = $this->billing_model->nurse_ward_service_categories();
			
			$this->load->view("app/nurse_module/bed_side_procedure",$this->data);			
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','nurse_bed_side_procedure_report');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function	
				
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_bed_side',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Bed Side Procedure";
			$this->data['module'] = "bed_side_procedure";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}

	public function bed_side_item($id = ''){
		$this->data['itemList'] = $this->billing_model->nurse_ward_service_itemList($id);
		$this->data['particularName'] = $this->billing_model->nurse_ward_service_particularName($id);
		$this->data['itemPlaceholder'] = '- Service Item -';
		$this->load->view("app/billing/itemList", $this->data);
	}
	
	public function save_bed_side_procedure(){
		if (!$this->_require_iop_patient_binding_or_redirect($this->input->post('opd_no'), $this->input->post('patient_no'), 'app/nurse_module/bed_side_procedure')) {
			return;
		}
			$this->data = array(
				'iop_id'				=>		$this->input->post('opd_no'),
				'dDate'					=>		date("Y-m-d"),
				'dDateTime'				=>		date("Y-m-d h:i:s"),
				'cItem_id'				=>		$this->input->post('particular'),
				'qty'					=>		$this->input->post('qty'),
				'notes'					=>		$this->input->post('remarks'),
				'cPreparedBy'			=>		$this->session->userdata('user_id'),
				'InActive'				=>		0
			);
			$this->db->insert('iop_bed_side_procedure',$this->data);
			$bed_pro_id = (int)$this->db->insert_id();
			$this->load->model('app/encounter_timeline_model');
			$this->encounter_timeline_model->append_event(
				(string)$this->input->post('opd_no'),
				(string)$this->input->post('patient_no'),
				'IPD',
				'NURSE_BED_SIDE_PROCEDURE',
				'Bed side procedure saved',
				array(
					'bed_pro_id' => $bed_pro_id,
					'item_id' => (string)$this->input->post('particular'),
					'qty' => (string)$this->input->post('qty'),
				),
				(string)$this->session->userdata('user_id')
			);
			$billing_projection = array('ok' => false);
			if ($bed_pro_id > 0 && method_exists($this->billing_transaction_model, 'sync_bed_side_procedure')) {
				$billing_projection = $this->billing_transaction_model->sync_bed_side_procedure($bed_pro_id, $this->session->userdata('user_id'));
			}
			
			if (isset($billing_projection['ok']) && $billing_projection['ok']) {
				$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Bed Side Procedure successfully Saved! Billing transaction synchronized.</div>");
			} else {
				log_message('error', 'Nurse bed_side_procedure billing sync failed for bed_pro_id '.$bed_pro_id.': '.json_encode($billing_projection));
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Bed Side Procedure saved, but billing synchronization requires review.</div>");
			}
		
			$this->session->set_userdata("abc","1");	
			redirect(base_url().'app/nurse_module/bed_side_procedure/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	public function delete_bed_side(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->_require_iop_patient_binding_or_redirect($iop_no, $patient_no, 'app/nurse_module/bed_side_procedure')) {
			return;
		}
		
		$this->db->query("UPDATE iop_bed_side_procedure SET InActive = 1 WHERE bed_pro_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Bed Side Procedure successfully Deleted!</div>");
		
		redirect(base_url().'app/nurse_module/bed_side_procedure/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	/* ================================================================== */
	/*  OPD VITALS WORKFLOW                                               */
	/* ================================================================== */

	public function vitals_queue(){
		$this->session->set_userdata(array(
			'tab'       => 'nurse_module',
			'module'    => 'nurse_vitals_queue',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->nurse_enhancement_model->ensure_vitals_workflow_schema();
		$this->data['message']        = $this->session->flashdata('message');
		$this->data['vitals_queue']   = $this->nurse_enhancement_model->get_opd_vitals_queue();
		$this->data['pending_count']  = $this->nurse_enhancement_model->count_opd_vitals_pending();
		$this->data['done_count']     = $this->nurse_enhancement_model->count_opd_vitals_done();
		$this->load->view('app/nurse_module/vitals_queue', $this->data);
	}

	public function record_vitals(){
		list($iop_id, $patient_no) = $this->get_url_params(4, 5);

		if (!$iop_id || !$patient_no) {
			redirect(base_url().'app/nurse_module/vitals_queue');
			return;
		}

		$this->session->set_userdata(array(
			'tab'       => 'nurse_module',
			'module'    => 'nurse_vitals_queue',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->nurse_enhancement_model->ensure_vitals_workflow_schema();
		if (!$this->_require_iop_patient_binding_or_redirect($iop_id, $patient_no, 'app/nurse_module/vitals_queue')) {
			return;
		}
		$visit = $this->nurse_enhancement_model->get_opd_visit($iop_id);
		if (!$visit) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning'><i class='fa fa-warning'></i> Visit not found.</div>");
			redirect(base_url().'app/nurse_module/vitals_queue');
			return;
		}

		$this->data['visit']       = $visit;
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['message']     = $this->session->flashdata('message');
		$this->load->view('app/nurse_module/record_vitals', $this->data);
	}

	public function save_opd_vitals(){
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		if (!$this->_require_iop_patient_binding_or_redirect($iop_id, $patient_no, 'app/nurse_module/vitals_queue')) {
			return;
		}

		if (!$iop_id || !$patient_no) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/nurse_module/vitals_queue');
			return;
		}

		$bp          = trim((string)$this->input->post('bp'));
		$temperature = $this->nursing_vitals_non_negative_number($this->input->post('temperature'));
		$pulse_rate  = $this->nursing_vitals_non_negative_number($this->input->post('pulse_rate'));
		$weight      = $this->nursing_vitals_non_negative_number($this->input->post('weight'));
		$height      = $this->nursing_vitals_non_negative_number($this->input->post('height'));
		$respiration = $this->nursing_vitals_non_negative_number($this->input->post('respiration'));

		if ($temperature === false || $pulse_rate === false || $weight === false || $height === false || $respiration === false) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vitals values must be numeric and cannot be negative.</div>");
			redirect(base_url().'app/nurse_module/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		if ($bp === '' && $temperature === null && $pulse_rate === null && $weight === null) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>At least one vital sign (BP, Temperature, Pulse, or Weight) is required.</div>");
			redirect(base_url().'app/nurse_module/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		if (!$this->_nurse_table_exists('iop_vital_parameters', 'iop_vital_parameters_schema') || !$this->_nurse_table_exists('patient_details_iop', 'patient_details_iop_schema')) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save vitals. Please try again.</div>");
			redirect(base_url().'app/nurse_module/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		$nurse_id = $this->session->userdata('user_id');
		$vital_id = $this->nurse_enhancement_model->save_opd_vitals($iop_id, $bp, $temperature, $pulse_rate, $weight, $height, $respiration, $nurse_id);
		if (!$vital_id) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save vitals. Please try again.</div>");
			redirect(base_url().'app/nurse_module/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vitals recorded successfully. Patient is now ready for consultation.</div>");
		redirect(base_url().'app/nurse_module/vitals_queue');
	}

	public function diagnosis(){
		
		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			//delete session for abc
			$this->session->set_userdata("abc","");	
			//set if post is null
			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
				$iop_no = $this->segment_decoded(4);
				$patient_no = $this->segment_decoded(5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}
		
			$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		
			$this->data['message'] = $this->session->flashdata('message');
			
			$this->data['medicineCategory'] = $this->opd_model->medicineCategory();
			$this->data['patientMedication'] = $this->opd_model->patientMedication($iop_no);
		
			$this->load->view("app/nurse_module/diagnosis",$this->data);	
		}else{
				// user restriction function
				$this->session->set_userdata('page_name','nurse_diagnosis_reports');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function		
			
			$this->session->set_userdata(array(
				 'tab'			=>		'nurse_module',
				 'module'		=>		'nurse_diagnosis',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
			
			$this->data['module_title'] = "Patient Diagnosis";
			$this->data['module'] = "diagnosis";
			$this->load->view("app/nurse_module/pick",$this->data);	
		}
	}

	/**
	 * Consumable Orders — nurse module entry point
	 * Delegates to the standalone consumable_order controller after patient selection
	 */
	public function consumable_order(){

		if(isset($_POST['btnSubmit']) || $this->input->post('patient_no') != '' || $this->input->post('iop_no') != '' || $this->session->userdata("abc") == "1"){
			$this->session->set_userdata("abc","");

			if($this->input->post("iop_no") == "" && $this->input->post("patient_no") == ""){
				list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			}else{
				$iop_no = $this->input->post("iop_no");
				$patient_no = $this->input->post("patient_no");
			}

			// Redirect to the standalone consumable_order controller with patient context
			redirect(base_url().'app/consumable_order/index/'.urlencode($iop_no).'/'.urlencode($patient_no));
		}else{
			$this->session->set_userdata(array(
				 'tab'		=>		'nurse_module',
				 'module'	=>		'consumable_order',
				 'subtab'	=>		'',
				 'submodule'=>		''));

			$this->data['module_title'] = "Consumable Orders";
			$this->data['module'] = "consumable_order";
			$this->load->view("app/nurse_module/pick",$this->data);
		}
	}




}