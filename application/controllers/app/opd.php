<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Opd extends General
{

	private $limit = 10;

	private function _opd_table_exists($table, $schema_flag = null)
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

	public function __construct()
	{
		parent::__construct();
		$this->load->model("app/ipd_model");
		$this->load->model("app/opd_model");
		$this->load->model("app/billing_model");
		$this->load->model("app/encounter_owner_model");
		$this->load->model("general_model");
		$this->load->model("app/patient_model");
		$this->load->model("app/nurse_enhancement_model");
		$this->load->model("app/clinical_workflow_model");
		$this->load->model("app/insurance_company_model");
		$this->load->model("app/medical_master_model");
		$this->load->model("app/smart_billing_model");
		$this->load->model("app/unified_billing_model");
		$this->load->model("app/Clinical_decision_support_model");
		$this->load->model("app/opd_status_engine");
		$this->load->model("app/clinical_history_model");
		$this->load->model("app/Medication_dictionary_model");
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		if (!$this->session->userdata('_schema_opd_ok')) {
			$this->opd_model->ensure_medication_schema();
			$this->opd_model->ensure_opd_vitals_schema();
			$this->opd_model->ensure_detention_schema();
			$this->opd_model->ensure_diagnosis_schema();
			$this->opd_model->ensure_complaints_schema();
			$this->opd_model->ensure_workflow_schema();
			$this->clinical_workflow_model->ensure_clinical_schema();
			$this->medical_master_model->ensure_all_master_tables();
			$this->smart_billing_model->ensure_smart_billing_schema();
			$this->opd_model->ensure_clearance_schema();
			$this->opd_model->ensure_performance_indexes();
			$this->Clinical_decision_support_model->ensure_schema();
			$this->opd_status_engine->ensure_schema();
			$this->clinical_history_model->ensure_clinical_history_schema();
			$this->Medication_dictionary_model->ensure_dictionary_schema();
			$this->general_model->ensure_ghs_departments();
			$this->session->set_userdata('_schema_opd_ok', 1);
		}
		require_role(array('doctor', 'nurse', 'receptionist', 'cashier'));
		if ($this->current_user_is_nurse()) {
			$method = (string)$this->router->fetch_method();
			$allowed = array('view');
			if (!in_array($method, $allowed, true)) {
				redirect(base_url().'access_denied');
				return;
			}
		}
		if ($this->current_user_is_reception()) {
			$method = (string)$this->router->fetch_method();
			$allowed = array('registration','search_result','validate_opd','save_opd','index','set_queue_status','update_status_ajax','reassign_doctor','opd_reg','view','start_consultation','start_consultation_ajax','discharge','search_diagnosis_json','search_medication_json','search_scan_json','search_lab_json','patient_history_json','check_clearance_ajax','admit_patient_from_opd','start_opd_quick','queue_status_ajax','clinical_clear','closure_desk','close_stale_visit_ajax','assign_detention_bed_ajax');
			if (!in_array($method, $allowed, true)) {
				redirect(base_url().'access_denied');
				return;
			}
		}
		if (has_role('cashier') && !$this->current_user_is_admin()) {
			$method = (string)$this->router->fetch_method();
			$allowed = array('printInv','printOR','pdfOR','index','view','billing','billingView');
			if (!in_array($method, $allowed, true)) {
				redirect(base_url().'access_denied');
				return;
			}
		}
	}

	private function doctor_override_key($encounter_type, $iop_id){
		return strtoupper(trim((string)$encounter_type)).'|'.(string)$iop_id;
	}

	private function doctor_has_active_override($encounter_type, $iop_id){
		$k = $this->doctor_override_key($encounter_type, $iop_id);
		$overrides = $this->session->userdata('doctor_overrides');
		if (!is_array($overrides) || !isset($overrides[$k])) {
			return false;
		}
		$ts = (int)$overrides[$k];
		return ($ts > 0 && (time() - $ts) <= 900);
	}

	private function doctor_write_allowed_or_redirect($iop_id, $patient_no, $encounter_type, $action_label, $is_ajax = false){
		$iop_id = (string)$iop_id;
		if (function_exists('sanitize_id_for_db')) {
			$iop_id = sanitize_id_for_db($iop_id);
		} else {
			$iop_id = trim(urldecode($iop_id));
		}
		$patient_no = trim((string)$patient_no);
		$encounter_type = strtoupper(trim((string)$encounter_type));
		$me = (string)$this->session->userdata('user_id');
		if ($this->current_user_is_admin()) {
			General::logfile('RBAC', 'ADMIN', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me);
			return true;
		}
		if (!$this->current_user_is_doctor()) {
			General::logfile('RBAC', 'DENY', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me);
			if ($is_ajax) {
				return false;
			}
			redirect(base_url().'access_denied');
			return false;
		}
		$enc = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0), 1)->row();
		if (!$enc) {
			General::logfile('RBAC', 'DENY', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me.'|missing_encounter');
			if ($is_ajax) {
				return false;
			}
			redirect(base_url().'access_denied');
			return false;
		}
		$dbPatientNo = isset($enc->patient_no) ? trim((string)$enc->patient_no) : '';
		if ($patient_no !== '' && $dbPatientNo !== '' && $dbPatientNo !== $patient_no) {
			General::logfile('RBAC', 'DENY', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me.'|patient_mismatch|db:'.$dbPatientNo);
			if ($is_ajax) {
				return false;
			}
			redirect(base_url().'access_denied');
			return false;
		}
		$encTypeDb = isset($enc->patient_type) ? strtoupper(trim((string)$enc->patient_type)) : '';
		if ($encTypeDb === 'OPD' || $encTypeDb === 'IPD') {
			$encounter_type = $encTypeDb;
		}

		$this->encounter_owner_model->install_tables();
		$this->encounter_owner_model->ensure_owner_from_patient_details($iop_id, $dbPatientNo !== '' ? $dbPatientNo : $patient_no);
		$ownerId = $this->encounter_owner_model->get_owner_doctor_id($iop_id, $encounter_type);
		if ($ownerId === '') {
			// Monday-safe fallback: if encounter has no owner, allow the first write to claim ownership.
			$this->encounter_owner_model->assign_owner(
				$iop_id,
				$dbPatientNo !== '' ? $dbPatientNo : $patient_no,
				$encounter_type,
				$me,
				$me,
				'auto_claim_first_write',
				null
			);
			$ownerId = $me;
		}
		if ($ownerId !== '' && $ownerId === $me) {
			General::logfile('RBAC', 'ALLOW', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me);
			return true;
		}
		if ($this->doctor_has_active_override($encounter_type, $iop_id)) {
			General::logfile('RBAC', 'ALLOW', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me.'|override');
			return true;
		}
		$this->encounter_owner_model->logfile('DoctorEncounter', 'WRITE_NON_OWNER', 'iop:'.$iop_id.'|type:'.$encounter_type.'|action:'.$action_label, $me);
		$this->encounter_owner_model->record_event($iop_id, $patient_no, $encounter_type, 'WRITE_NON_OWNER', null, null, $action_label, $me);
		General::logfile('RBAC', 'DENY', $encounter_type.'|'.$action_label.'|iop:'.$iop_id.'|p:'.$patient_no.'|u:'.$me.'|non_owner');
		if ($is_ajax) {
			return false;
		}
		redirect(base_url().'access_denied');
		return false;
	}

	public function registration()
	{

		$this->session->set_userdata(array(
			'tab'			=>		'patient',
			'module'		=>		'',
			'subtab'		=>		'opd',
			'submodule'	=>		'opd_registration'
		));


		$this->load->view("app/opd/registration", $this->data);
	}

	/**
	 * Quick Start OPD - auto-queues patient, assigns doctor, sets status based on doctor availability
	 * Called from receptionist dashboard "Start OPD" button
	 */
	public function start_opd_quick($patient_no = null)
	{
		if (!$patient_no) {
			$patient_no = $this->input->get_post('patient_no');
		}
		$patient_no = trim((string)$patient_no);

		if ($patient_no === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Patient number is required.</div>");
			redirect(base_url() . 'app/dashboard');
			return;
		}

		$result = $this->opd_model->quick_start_opd($patient_no, $this->session->userdata('user_id'));

		if (!$result['success']) {
			$msg = isset($result['message']) ? $result['message'] : 'Failed to start OPD.';
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($msg) . "</div>");
			redirect(base_url() . 'app/dashboard');
			return;
		}

		// Success - set appropriate flash message based on status
		$statusLabel = ($result['status'] === 'WAITING') ? 'Waiting for Doctor' : 'In Consultation';
		$alertClass = ($result['status'] === 'WAITING') ? 'alert-warning' : 'alert-success';
		$icon = ($result['status'] === 'WAITING') ? 'fa-clock-o' : 'fa-check-circle';

		$msg = "<div class='alert {$alertClass} alert-dismissable'>"
			. "<button type='button' class='close' data-dismiss='alert'>&times;</button>"
			. "<i class='fa {$icon}'></i> "
			. "<strong>OPD Started:</strong> " . htmlspecialchars($result['message'])
			. " <br><small>OPD No: <strong>" . htmlspecialchars($result['iop_id']) . "</strong> | Status: <strong>{$statusLabel}</strong></small>"
			. "</div>";

		$this->session->set_flashdata('message', $msg);
		redirect(base_url() . 'app/dashboard');
	}


	public function search_result($offset = 0)
	{
		// user restriction function
		$this->session->set_userdata('page_name', 'opd_registration');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			if (!has_role(array('doctor', 'nurse', 'receptionist', 'cashier'))) {
				redirect(base_url() . 'access_denied');
			}
		}
		// end of user restriction function		 


		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		$offset = is_numeric($offset) ? (int)$offset : 0;
		$this->session->set_userdata(array(
			'tab'			=>		'patient',
			'module'		=>		'',
			'subtab'		=>		'opd',
			'submodule'	=>		'opd_registration'
		));


		$patient = $this->opd_model->getAll_search($this->limit, $offset);

		$config['base_url'] = base_url() . 'app/opd/search_result/';
		$config['total_rows'] = $this->opd_model->count_all_search();
		$config['per_page'] = $this->limit;


		$config['uri_segment'] = $uri_segment;
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';

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
		$this->data['pagination'] = $this->pagination->create_links();

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Patient No', 'Patient Name', 'Gender', 'Civil Status', 'Age', 'Date Entry', 'Action');
		$i = 0 + $offset;


		foreach ($patient as $patient) {
			$this->table->add_row(
				anchor('app/patient/view/' . $patient->patient_no, $patient->patient_no),
				$patient->name,
				$patient->gender,
				$patient->civil_status,
				$patient->age,
				date('M d, Y H:i:s', strtotime($patient->date_entry)),
				anchor('app/opd/opd_reg/' . $patient->patient_no, 'Check IN')
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/opd/search_result', $this->data);
	}

	public function opd_reg($patient_no)
	{
		$this->data['lastOPDNo'] = $this->general_model->lastOPDNo();
		$this->data['message'] = "";
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['patientHistory'] = $this->patient_model->getPatientHistory($patient_no);
		$this->load->model('app/visit_billing_resolver_model');
		if (isset($this->visit_billing_resolver_model) && method_exists($this->visit_billing_resolver_model, 'preview_visit_fee_decisions')) {
			$this->data['visit_fee_preview'] = $this->visit_billing_resolver_model->preview_visit_fee_decisions((string)$patient_no, null, date('Y-m-d'));
		} else {
			$this->data['visit_fee_preview'] = null;
		}
		$this->insurance_company_model->ensure_insurance_schema();
		$this->data['insurance_list'] = $this->insurance_company_model->get_active_insurance_list();
		$this->data['doctorList'] = $this->general_model->doctorList();
		$this->data['doctorList2'] = $this->general_model->doctorList();
		$this->data['departmentList'] = $this->general_model->departmentList();
		$this->load->view("app/opd/opdReg", $this->data);
	}


	public function index($offset = 0)
	{
// var_dump($this->input->post("insurance"));
		$offset = is_numeric($offset) ? (int)$offset : 0;
		// user restriction function
		$this->session->set_userdata('page_name', 'opd_enquiry');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			if (!has_role(array('doctor', 'nurse', 'receptionist', 'cashier'))) {
				redirect(base_url() . 'access_denied');
			}
		}
		// end of user restriction function		

		$this->session->set_userdata(array(
			'tab'			=>		'patient',
			'module'		=>		'',
			'subtab'		=>		'opd',
			'submodule'	=>		'opd_master'
		));

		// set session filters only when the form posts (preserve on redirects)
		// NOTE: allow Enter key submit (no btnSearch posted)
		if (strtoupper((string)$this->input->server('REQUEST_METHOD')) === 'POST') {
			$this->session->set_userdata(array(
				'search_opd_master'			=>		(string)$this->input->post('search'),
				'search_opd_From'			=>		(string)$this->input->post('cFrom'),
				'search_opd_cTo'			=>		(string)$this->input->post('cTo'),
				'search_opd_department'		=>		(string)$this->input->post('department'),
				'search_opd_doctor'			=>		(string)$this->input->post('doctor'),
				'search_opd_insurance'		=>		(string)$this->input->post('insurance')
			));
		}

		// $uri_segment = 4;
		// $offset = $this->uri->segment($uri_segment);

		if (!$this->_opd_table_exists('iop_opd_workflow', 'iop_opd_workflow_schema')) {
			$this->opd_model->install_opd_workflow_table();
		}
		$patients = $this->opd_model->getAll($this->limit, $offset);
		$hasActiveSearch = false;
		$isPost = (strtoupper((string)$this->input->server('REQUEST_METHOD')) === 'POST');
		$searchMaster = trim((string)$this->session->userdata('search_opd_master'));
		$searchFrom = trim((string)$this->session->userdata('search_opd_From'));
		$searchTo = trim((string)$this->session->userdata('search_opd_cTo'));
		$searchDept = trim((string)$this->session->userdata('search_opd_department'));
		$searchDoc = trim((string)$this->session->userdata('search_opd_doctor'));
		$searchIns = trim((string)$this->session->userdata('search_opd_insurance'));
		if ($searchMaster !== '' || $searchDept !== '' || $searchDoc !== '' || $searchIns !== '') {
			$hasActiveSearch = true;
		}
		if ($isPost) {
			$hasActiveSearch = true;
		} else {
			$today = date('Y-m-d');
			if (($searchFrom !== '' && $searchFrom !== $today) || ($searchTo !== '' && $searchTo !== $today)) {
				$hasActiveSearch = true;
			}
		}
		$iopIds = array();
		foreach ($patients as $p) {
			$iopIds[] = (string)$p->IO_ID;
		}
		// Bulk-preload consultation gate cache rows for these visits so that
		// ConsultationEligibilityService->get_gate_ui_payload() can reuse them
		// without issuing one query per row in the OPD Active list.
		if (!empty($iopIds)) {
			try {
				$this->load->model('app/opd_consultation_gate_cache_model', 'opd_gate_cache');
				$this->opd_gate_cache->preload_caches($iopIds);
			} catch (Throwable $e) {
				// Fallback: if preload fails for any reason, gate lookups will
				// gracefully fall back to legacy per-row get_cache() behavior.
			}
		}
		$vitalsDoneMap = array();
		if (is_array($iopIds) && count($iopIds) > 0) {
			$hasVitalsStatusCol = $this->db->field_exists('vitals_status', 'patient_details_iop');
			if ($hasVitalsStatusCol) {
				$this->db->select('IO_ID, vitals_status');
				$this->db->where_in('IO_ID', $iopIds);
				$this->db->where('InActive', 0);
				$qv = $this->db->get('patient_details_iop');
				$rows = $qv ? $qv->result() : array();
				foreach ($rows as $r) {
					$st = isset($r->vitals_status) ? strtoupper(trim((string)$r->vitals_status)) : '';
					$vitalsDoneMap[(string)$r->IO_ID] = ($st === 'DONE');
				}
			} elseif ($this->_opd_table_exists('iop_vital_parameters', 'iop_vital_parameters_schema')) {
				$this->db->select('iop_id');
				$this->db->where_in('iop_id', $iopIds);
				$this->db->where('InActive', 0);
				$this->db->group_by('iop_id');
				$qv = $this->db->get('iop_vital_parameters');
				$rows = $qv ? $qv->result() : array();
				foreach ($rows as $r) {
					$vitalsDoneMap[(string)$r->iop_id] = true;
				}
			}
		}
		$workflowMap  = $this->opd_model->get_workflow_map($iopIds);
		$visitTypeMap = $this->smart_billing_model->get_visit_type_map($iopIds);
		$_vtBadgeCls  = array(
			'FIRST_VISIT'        => 'label-primary',
			'REVIEW'             => 'label-success',
			'FOLLOW_UP'          => 'label-info',
			'WALK_IN'            => 'label-default',
			'MISSED_APPOINTMENT' => 'label-warning',
			'EMERGENCY'          => 'label-danger',
		);
		$activePatients = array();
		$visitedPatients = array();
		foreach ($patients as $p) {
			$wf = isset($workflowMap[(string)$p->IO_ID]) ? $workflowMap[(string)$p->IO_ID] : null;
			$wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
			$legacyCompleted = (isset($p->nStatus) && (string)$p->nStatus !== 'Pending');
			$clearedStatuses = array('COMPLETED','CLINICALLY_CLEARED','PENDING_LAB','PENDING_PHARMACY');
			if ($legacyCompleted || in_array($wfStatus, $clearedStatuses, true)) {
				$visitedPatients[] = $p;
			} else {
				$activePatients[] = $p;
			}
		}
		// Busy doctors — used to disable Start buttons per-doctor
		$busyDoctorIds = $this->opd_model->get_busy_doctor_ids();
		$busyDoctorSet = array_flip($busyDoctorIds); // O(1) lookup
		$__payerTypeMap = array();
		try {
			$__pnos = array();
			foreach ($patients as $_p) {
				if (isset($_p->patient_no) && trim((string)$_p->patient_no) !== '') {
					$__pnos[(string)$_p->patient_no] = true;
				}
			}
			$__pnList = array_keys($__pnos);
			if (!empty($__pnList)) {
				$this->load->model('app/patient_model');
				$this->patient_model->ensure_nhis_schema();
				$hasNhisNumber = $this->patient_model->column_exists('patient_personal_info', 'nhis_number');
				$hasNhisExpiry = $this->patient_model->column_exists('patient_personal_info', 'nhis_expiry_date');
				if ($hasNhisNumber) {
					$sel = 'patient_no, nhis_number';
					if ($hasNhisExpiry) { $sel .= ', nhis_expiry_date'; }
					$this->db->select($sel, false);
					$this->db->where_in('patient_no', $__pnList);
					$this->db->where('InActive', 0);
					$rows = $this->db->get('patient_personal_info')->result();
					$raw = array();
					foreach ((array)$rows as $r) {
						$pn = isset($r->patient_no) ? trim((string)$r->patient_no) : '';
						if ($pn === '') { continue; }
						$nh = isset($r->nhis_number) ? trim((string)$r->nhis_number) : '';
						$exp = ($hasNhisExpiry && isset($r->nhis_expiry_date)) ? $r->nhis_expiry_date : null;
						$raw[$pn] = array('nhis_number' => $nh, 'nhis_expiry_date' => $exp);
					}
					foreach ($__pnList as $pn) {
						$nh = isset($raw[$pn]) ? (string)$raw[$pn]['nhis_number'] : '';
						if ($nh === '') {
							$__payerTypeMap[$pn] = 'CASH';
							continue;
						}
						$exp = isset($raw[$pn]) ? $raw[$pn]['nhis_expiry_date'] : null;
						$live = $this->patient_model->compute_nhis_status($nh, $exp);
						$__payerTypeMap[$pn] = ($live === 'ACTIVE') ? 'NHIS' : 'CASH';
					}
				} else {
					foreach ($__pnList as $pn) { $__payerTypeMap[$pn] = 'CASH'; }
				}
			}
		} catch (\Throwable $e) {
			$__payerTypeMap = array();
		}
// var_dump($patient);
		// $config['base_url'] = base_url().'app/opd/index/';
		// $config['total_rows'] = $this->opd_model->count_all();
		// $config['per_page'] = $this->limit;


		// $config['uri_segment'] = $uri_segment;
		// $config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		// $config['full_tag_close'] = '</ul><!--pagination-->';

		// $config['first_link'] = '&laquo; First';
		// $config['first_tag_open'] = '<li class="prev page">';
		// $config['first_tag_close'] = '</li>';

		// $config['last_link'] = 'Last &raquo;';
		// $config['last_tag_open'] = '<li class="next page">';
		// $config['last_tag_close'] = '</li>';

		// $config['next_link'] = 'Next &rarr;';
		// $config['next_tag_open'] = '<li class="next page">';
		// $config['next_tag_close'] = '</li>';

		// $config['prev_link'] = '&larr; Previous';
		// $config['prev_tag_open'] = '<li class="prev page">';
		// $config['prev_tag_close'] = '</li>';

		// $config['cur_tag_open'] = '<li class="active"><a href="">';
		// $config['cur_tag_close'] = '</a></li>';

		// $config['num_tag_open'] = '<li class="page">';
		// $config['num_tag_close'] = '</li>';

		// $this->pagination->initialize($config);
		// $this->data['pagination'] = $this->pagination->create_links();

		$isDoctor = $this->current_user_is_doctor();

		if ($isDoctor) {
			$tmpl = array(
				'table_open'    => '<table class="table table-hover table-striped opd-clickable-table">',
				'row_start'     => '<tr class="opd-row">',
				'row_alt_start' => '<tr class="opd-row">',
			);
			$this->table->set_template($tmpl);
			$this->table->set_empty('&nbsp;');
			$this->table->set_heading('Patient', 'Visit Type', 'Time', 'Status', 'Actions');
		} else {
			$tmpl = array(
				'table_open'    => '<table class="table table-hover table-striped opd-clickable-table">',
				'row_start'     => '<tr class="opd-row">',
				'row_alt_start' => '<tr class="opd-row">',
			);
			$this->table->set_template($tmpl);
			$this->table->set_empty('&nbsp;');
			$this->table->set_heading('OPD No', 'Patient No', 'Patient Name', 'Age', 'Coverage', 'Visit Type', 'Visit Date Time', 'Department', 'Consultant Doctor', 'Status', '');
		}
		$i = 0 + $offset;

		$this->load->library('ConsultationEligibilityService');

		foreach ($activePatients as $patient) {
			$wf = isset($workflowMap[(string)$patient->IO_ID]) ? $workflowMap[(string)$patient->IO_ID] : null;
			$wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
			$vitalsDone = isset($vitalsDoneMap[(string)$patient->IO_ID]) ? (bool)$vitalsDoneMap[(string)$patient->IO_ID] : false;
			$_vtEntry = isset($visitTypeMap[(string)$patient->IO_ID]) ? $visitTypeMap[(string)$patient->IO_ID] : null;
			$_vtBadge = '';
			if ($_vtEntry && isset($_vtEntry->visit_type) && $_vtEntry->visit_type !== '') {
				$_vtCls   = isset($_vtBadgeCls[$_vtEntry->visit_type]) ? $_vtBadgeCls[$_vtEntry->visit_type] : 'label-default';
				$_vtLabel = str_replace('_', ' ', $_vtEntry->visit_type);
				$_vtBadge = ' <span class="label ' . $_vtCls . '" title="Visit Type" style="font-size:10px;">' . $_vtLabel . '</span>';
				if ($_vtEntry->consultation_waived) {
				$_vtBadge .= ' <span class="label label-success" title="Consultation Waived" style="font-size:10px;">Waived</span>';
				}
			}
			if ($_vtEntry && isset($_vtEntry->visit_type) && $_vtEntry->visit_type !== '') {
				$_vtClsCell = isset($_vtBadgeCls[$_vtEntry->visit_type])
					? $_vtBadgeCls[$_vtEntry->visit_type]
					: 'label-default';
				$_vtLabelCell = $this->smart_billing_model->visit_type_label($_vtEntry->visit_type);
				$visitTypeCell = '<span class="label ' . $_vtClsCell . '">' . htmlspecialchars($_vtLabelCell) . '</span>';
				if ($_vtEntry->consultation_waived) {
					$visitTypeCell .= ' <span class="label label-success" style="font-size:10px;">Waived</span>';
				}
			} else {
				$visitTypeCell = '<span class="label label-default" style="opacity:0.5;">&#8212;</span>';
			}

			$isClinicallyCleared = (isset($patient->clinical_clearance_status) && $patient->clinical_clearance_status == 1);
			$clearedBadge = $isClinicallyCleared
				? ' <span class="label label-success" title="Clinically Cleared"><i class="fa fa-check"></i> Cleared</span>'
				: '';

			$iop_safe = str_replace(' ', '-', $patient->IO_ID);
			$pno_safe = str_replace(' ', '-', $patient->patient_no);

			if ($patient->nStatus == 'Pending') {
				$queueLabel = ($wfStatus !== '') ? $wfStatus : 'WAITING';
				$gateUi = null;
				$gateBadge = '';
				try {
					if ($queueLabel === 'WAITING') {
						$gateUi = $this->consultationeligibilityservice->get_gate_ui_payload((string)$patient->IO_ID);
						if (is_array($gateUi) && !empty($gateUi['ok'])) {
							$icon = (!empty($gateUi['can_start'])) ? 'fa-unlock' : 'fa-lock';
							$tip = isset($gateUi['tooltip']) ? (string)$gateUi['tooltip'] : '';
							$gateBadge = ' <span class="label ' . htmlspecialchars((string)$gateUi['badge_class']) . '"'
								. ' title="' . htmlspecialchars($tip) . '"'
								. ' style="font-size:10px;">'
								. '<i class="fa ' . $icon . '"></i> ' . htmlspecialchars((string)$gateUi['label']) . '</span>';
						}
					}
				} catch (Throwable $e) {
					$gateUi = null;
					$gateBadge = '';
				}
				$nStatus    = $this->opd_model->get_status_badge($queueLabel) . $clearedBadge . $gateBadge;
				$discharge  = '';

				if (!$isClinicallyCleared) {
					if ($isDoctor) {
						if ($queueLabel === 'IN_CONSULTATION') {
							$discharge .= anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe,
								'<i class="fa fa-stethoscope"></i> Open',
								array('class' => 'btn btn-xs btn-primary', 'style' => 'margin-right:4px;'));
							$discharge .= '<button class="btn btn-success btn-xs clinical-clear-btn" data-iop="'.$iop_safe.'" data-patient="'.$pno_safe.'">'.
								'<i class="fa fa-check-circle"></i> Done</button>';
						} else {
							$discharge .= anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe,
								'<i class="fa fa-eye"></i> View',
								array('class' => 'btn btn-xs btn-default'));
						}
					} else {
						$isNurseUser  = $this->current_user_is_nurse();
						$isAdminUser  = $this->current_user_is_admin();
						$isReceptionUser = $this->current_user_is_reception();
						if ($queueLabel === 'WAITING' || $queueLabel === '') {
							if (!$vitalsDone) {
								if ($isNurseUser || $isReceptionUser) {
									$discharge .= anchor('app/vitals/record_vitals/' . $iop_safe . '/' . $pno_safe,
										'<i class="fa fa-heartbeat"></i> Record Vitals',
										array('class' => 'btn btn-xs btn-primary', 'style' => 'margin-right:5px;'));
								} else {
									$discharge .= '<button class="btn btn-xs btn-default" disabled="disabled"'
										. ' title="Vitals must be recorded before consultation"'
										. ' style="cursor:not-allowed;opacity:0.6;margin-right:5px;">'
										. '<i class="fa fa-heartbeat"></i> Await Vitals</button>';
								}
							} else {
								if ($isNurseUser) {
									$discharge .= '<span class="label label-success" style="margin-right:5px;"><i class="fa fa-check"></i> Vitals Done</span>';
									$discharge .= anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe,
										'<i class="fa fa-eye"></i> View',
										array('class' => 'btn btn-xs btn-default', 'style' => 'margin-right:5px;'));
								} else {
									// Determine if this patient's assigned doctor is already busy
									$visitDocId   = isset($patient->doctor_id) ? (string)$patient->doctor_id : '';
									$doctorBusy   = ($visitDocId !== '' && isset($busyDoctorSet[$visitDocId]));
									$gateAllowsStart = true;
									$gateTip = '';
									$gatePayUrl = '';
									if (is_array($gateUi) && isset($gateUi['can_start'])) {
										$gateAllowsStart = (bool)$gateUi['can_start'];
										$gateTip = isset($gateUi['tooltip']) ? (string)$gateUi['tooltip'] : '';
										$gatePayUrl = isset($gateUi['action_url']) ? (string)$gateUi['action_url'] : '';
									}
									if (!$gateAllowsStart) {
										$discharge .= '<button class="btn btn-xs btn-default" disabled="disabled"'
											. ' title="' . htmlspecialchars($gateTip !== '' ? $gateTip : 'Payment required before consultation') . '"'
											. ' style="cursor:not-allowed;opacity:0.6;margin-right:5px;">'
											. '<i class="fa fa-lock"></i> Payment</button>';
										if ($gatePayUrl !== '') {
											$discharge .= '<a class="btn btn-xs btn-danger" style="margin-right:5px;" href="' . htmlspecialchars($gatePayUrl) . '">'
												. '<i class="fa fa-credit-card"></i> Cashier</a>';
										}
									} elseif ($doctorBusy && !$isAdminUser) {
										$discharge .= '<button class="btn btn-xs btn-default" disabled="disabled"'
											. ' title="Doctor is currently in consultation with another patient"'
											. ' style="cursor:not-allowed;opacity:0.6;margin-right:5px;">'
											. '<i class="fa fa-clock-o"></i> Doctor Busy</button>';
									} else {
										$discharge .= '<button class="btn btn-xs btn-warning start-consultation-btn"'
											. ' data-iop="'.$iop_safe.'" data-patient="'.$pno_safe.'"'
											. ' data-doctor-id="'.htmlspecialchars($visitDocId).'"'
											. ' style="margin-right:5px;"'
											. ($doctorBusy && $isAdminUser ? ' title="Admin override: doctor has another active consultation"' : '')
											. '>'
											. '<i class="fa fa-play"></i> Start</button>';
									}
								}
							}
						}
						if ($queueLabel === 'IN_CONSULTATION' && !$isNurseUser) {
							$discharge .= '<button class="btn btn-success btn-xs clinical-clear-btn" data-iop="'.$iop_safe.'" data-patient="'.$pno_safe.'" style="margin-right:5px;">'.
								'<i class="fa fa-check-circle"></i> Clinically Clear</button>';
						}
						if (!$isNurseUser) {
							$discharge .= ' <a href="#" class="opd-reassign" data-toggle="modal" data-target="#myModal" data-iop-id="'.$iop_safe.'" data-patient-no="'.$pno_safe.'">'.
								'<i class="fa fa-user-md"></i> Re-Assign</a>';
						}
					}
				}
			} else {
				$nStatus   = '<span class="label label-default">Completed</span>' . $clearedBadge;
				$discharge = 'Discharged';
			}

			$__pno = isset($patient->patient_no) ? (string)$patient->patient_no : '';
			$payerType = (isset($__payerTypeMap[$__pno]) ? (string)$__payerTypeMap[$__pno] : 'CASH');
			$insurance = ($payerType === 'NHIS')
				? '<span class="label label-success">NHIS</span>'
				: '<span class="label label-default">CASH</span>';

			if ($isDoctor) {
				// Doctor view: Patient name + insurance pill | Visit type badge | Time | Status | Actions
				$patientCell = '<strong>' . htmlspecialchars(trim($patient->name)) . '</strong>'.
					'<br><small class="text-muted">'.htmlspecialchars($patient->age).' yrs &nbsp;'.$insurance.'</small>';
				$this->table->add_row(
					anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe, $patientCell),
					$_vtBadge !== '' ? $_vtBadge : '<span class="text-muted">&#8212;</span>',
					date('H:i', strtotime($patient->time_visit)),
					$nStatus,
					$discharge
				);
			} else {
				$this->table->add_row(
					anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe, $patient->IO_ID),
					anchor('app/patient/view/' . $pno_safe, $patient->patient_no),
					$patient->name,
					$patient->age,
					$insurance,
					$visitTypeCell,
					date('M d, Y', strtotime($patient->date_visit)) . ' ' . date('H:i:s', strtotime($patient->time_visit)),
					$patient->dept_name,
					$patient->doctor,
					$nStatus,
					$discharge
				);
			}
		}
		$this->data['message'] = $this->session->flashdata('message');
		if ($hasActiveSearch && (!is_array($patients) || count($patients) === 0)) {
			$this->data['message'] .= "<div class='alert alert-info alert-dismissable'><i class='fa fa-info'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>No results found.</div>";
		}
		$this->data['table'] = $this->_inject_row_hrefs($this->table->generate());

		// Visited/Completed patients table for the same selected date range
		$this->table->clear();
		$tmpl2 = array(
			'table_open'    => '<table class="table table-hover table-striped opd-clickable-table">',
			'row_start'     => '<tr class="opd-row">',
			'row_alt_start' => '<tr class="opd-row">',
		);
		$this->table->set_template($tmpl2);
		$this->table->set_empty('&nbsp;');
		if ($isDoctor) {
			$this->table->set_heading('Patient', 'Time', 'Status', 'Actions');
		} else {
			$this->table->set_heading('OPD No', 'Patient No', 'Patient Name', 'Age', 'Coverage', 'Visit Type', 'Visit Date Time', 'Department', 'Consultant Doctor', 'Status', '');
		}
		foreach ($visitedPatients as $vp) {
			$wf = isset($workflowMap[(string)$vp->IO_ID]) ? $workflowMap[(string)$vp->IO_ID] : null;
			$wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
			if ($wfStatus === '') {
				$wfStatus = (isset($vp->nStatus) && (string)$vp->nStatus !== 'Pending') ? 'CLINICALLY_CLEARED' : 'WAITING';
			}
			$statusLabel = $this->opd_model->get_status_badge($wfStatus);
			$_vtEntryV = isset($visitTypeMap[(string)$vp->IO_ID]) ? $visitTypeMap[(string)$vp->IO_ID] : null;
			$visitTypeCellV = '<span class="label label-default" style="opacity:0.5;">&#8212;</span>';
			if ($_vtEntryV && isset($_vtEntryV->visit_type) && $_vtEntryV->visit_type !== '') {
				$_vtClsV   = isset($_vtBadgeCls[$_vtEntryV->visit_type]) ? $_vtBadgeCls[$_vtEntryV->visit_type] : 'label-default';
				$_vtLabelV = $this->smart_billing_model->visit_type_label($_vtEntryV->visit_type);
				$visitTypeCellV = '<span class="label ' . $_vtClsV . '">' . htmlspecialchars($_vtLabelV) . '</span>';
				if ($_vtEntryV->consultation_waived) {
					$visitTypeCellV .= ' <span class="label label-success" style="font-size:10px;">Waived</span>';
				}
			}
			$actions = $this->_build_status_widget((string)$vp->IO_ID, (string)$vp->patient_no, $wfStatus);
			$__pnoV = isset($vp->patient_no) ? (string)$vp->patient_no : '';
			$payerType = (isset($__payerTypeMap[$__pnoV]) ? (string)$__payerTypeMap[$__pnoV] : 'CASH');
			$insurance = ($payerType === 'NHIS')
				? '<span class="label label-success">NHIS</span>'
				: '<span class="label label-default">CASH</span>';
			$iop_safe = str_replace(' ', '-', $vp->IO_ID);
			$pno_safe = str_replace(' ', '-', $vp->patient_no);
			if ($isDoctor) {
				$patientCellV = '<strong>' . htmlspecialchars(trim($vp->name)) . '</strong>' .
					'<br><small class="text-muted">' . htmlspecialchars($vp->age) . ' yrs &nbsp;' . $insurance . '</small>';
				$this->table->add_row(
					anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe, $patientCellV),
					date('H:i', strtotime($vp->time_visit)),
					$statusLabel,
					$actions
				);
			} else {
				$this->table->add_row(
					anchor('app/opd/view/' . $iop_safe . '/' . $pno_safe, $vp->IO_ID),
					anchor('app/patient/view/' . $pno_safe, $vp->patient_no),
					$vp->name,
					$vp->age,
					$insurance,
					$visitTypeCellV,
					date('M d, Y', strtotime($vp->date_visit)) . ' ' . date('H:i:s', strtotime($vp->time_visit)),
					$vp->dept_name,
					$vp->doctor,
					$statusLabel,
					$actions
				);
			}
		}
		$this->data['table_visited']    = $this->_inject_row_hrefs($this->table->generate());
		$this->data['visited_count']    = is_array($visitedPatients) ? count($visitedPatients) : 0;
		$this->data['active_count']     = is_array($activePatients) ? count($activePatients) : 0;
		$this->data['is_doctor_view']   = $isDoctor;
		$this->data['csrf_token_name']  = $this->security->get_csrf_token_name();
		$this->data['csrf_hash']        = $this->security->get_csrf_hash();
		$this->data['can_opd_closure_desk'] = ($this->current_user_is_admin() || $this->current_user_is_reception());

		$this->load->view('app/opd/index', $this->data);
	}

	public function closure_desk()
	{
		require_role(array('admin', 'receptionist'));
		$hours = (int)$this->input->get('hours');
		if ($hours < 1) {
			$hours = 24;
		}
		$this->data['hours'] = $hours;
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['stale_visits'] = $this->opd_status_engine->get_stale_visits_for_closure($hours, 200);
		$this->load->view('app/opd/closure_desk', $this->data);
	}

	public function close_stale_visit_ajax()
	{
		require_role(array('admin', 'receptionist'));
		header('Content-Type: application/json');

		$iop_id = trim((string)$this->input->post('iop_id'));
		$mode = strtolower(trim((string)$this->input->post('mode')));
		$hours = (int)$this->input->post('hours');
		if ($hours < 1) {
			$hours = 24;
		}

		if ($iop_id === '' || ($mode !== 'cancel' && $mode !== 'clinical_clear')) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid request'));
			return;
		}

		$snap = $this->opd_status_engine->get_closure_snapshot($iop_id, $hours);
		if (!$snap) {
			echo json_encode(array('ok' => false, 'error' => 'Visit not found'));
			return;
		}

		if (empty($snap['is_stale']) && !$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'Visit is not yet eligible (must be older than ' . (int)$hours . ' hours)'));
			return;
		}

		if ($mode === 'clinical_clear') {
			$eligibility = $this->opd_status_engine->validate_clinical_clearance($iop_id);
			if (!$eligibility || empty($eligibility['allowed'])) {
				$reasons = ($eligibility && isset($eligibility['reasons']) && is_array($eligibility['reasons'])) ? $eligibility['reasons'] : array('Blocked');
				echo json_encode(array('ok' => false, 'error' => implode("\n\n", $reasons)));
				return;
			}
		} elseif (empty($snap['can_close'])) {
			$blockers = isset($snap['blocker_text']) ? (string)$snap['blocker_text'] : 'Blocked';
			echo json_encode(array('ok' => false, 'error' => 'Cannot close: ' . $blockers));
			return;
		}

		$user_id = (string)$this->session->userdata('user_id');
		$reason = 'Stale visit closure desk (' . (int)$hours . 'h)';

		if ($mode === 'cancel') {
			$result = $this->opd_status_engine->transition($iop_id, Opd_status_engine::STATUS_CANCELLED, $user_id, $reason, 'reception::closure_desk');
			if (!$result || empty($result['success'])) {
				$msg = ($result && isset($result['message'])) ? (string)$result['message'] : 'Failed';
				echo json_encode(array('ok' => false, 'error' => $msg));
				return;
			}
			echo json_encode(array('ok' => true));
			return;
		}

		$result = $this->opd_status_engine->transition($iop_id, Opd_status_engine::STATUS_CLINICALLY_CLEARED, $user_id, $reason, 'reception::closure_desk');
		if (!$result || empty($result['success'])) {
			$msg = ($result && isset($result['message'])) ? (string)$result['message'] : 'Failed';
			echo json_encode(array('ok' => false, 'error' => $msg));
			return;
		}

		$patient_no = isset($snap['patient_no']) ? (string)$snap['patient_no'] : null;
		$this->billing_model->upsert_clearance_stage($iop_id, 'CLINICAL', $patient_no, $user_id);

		echo json_encode(array('ok' => true));
	}

	public function set_queue_status($iop_id, $patient_no, $status)
	{
		$status = strtoupper(trim((string)$status));
		
		// Validate status is known
		if (!$this->opd_status_engine->is_valid_status($status)) {
			redirect(base_url() . 'app/opd/index');
			return;
		}
		
		// Permission check
		if (!($this->current_user_is_admin() || $this->current_user_is_reception() || $this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'set_queue_status'))) {
			return;
		}
		
		$user_id = $this->session->userdata('user_id');
		
		// Use unified status engine for transition
		$result = $this->opd_status_engine->transition($iop_id, $status, $user_id, null, 'opd::set_queue_status');
		
		if (!$result['success']) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Invalid Transition:</strong> " . htmlspecialchars($result['message']) . "</div>");
			redirect(base_url() . 'app/opd/index');
			return;
		}
		
		$newLabel = $this->opd_status_engine->get_status_label($status);
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Status updated to <strong>" . htmlspecialchars($newLabel) . "</strong>.</div>");
		redirect(base_url() . 'app/opd/index');
	}

	/**
	 * Post-process a generated table HTML string and inject data-href, data-iop, data-pno
	 * onto every <tr class="opd-row"> by extracting the href from the first anchor in each row.
	 * This enables whole-row click navigation in JS without interfering with buttons/links inside.
	 */
	private function _inject_row_hrefs($html)
	{
		return preg_replace_callback(
			'#<tr class="opd-row">(.*?)</tr>#si',
			function($m) {
				$inner = $m[1];
				// Find the first <a href="..."> in this row — that is the OPD view link
				if (preg_match('#<a\s[^>]*href="([^"]+)"[^>]*>#i', $inner, $am)) {
					$href = htmlspecialchars($am[1], ENT_QUOTES);
					// Extract iop and pno from URL pattern .../view/{iop}/{pno}
					$dataAttrs = ' data-href="' . $href . '"';
					if (preg_match('#/view/([^/]+)/([^/"]+)#', $am[1], $vm)) {
						$dataAttrs .= ' data-iop="' . htmlspecialchars($vm[1], ENT_QUOTES) . '"';
						$dataAttrs .= ' data-pno="' . htmlspecialchars($vm[2], ENT_QUOTES) . '"';
					}
					return '<tr class="opd-row opd-row-clickable"' . $dataAttrs . '>' . $inner . '</tr>';
				}
				return $m[0];
			},
			$html
		);
	}

	private function _build_status_widget($iop_id, $patient_no, $current_status){
		$current_status = strtoupper(trim((string)$current_status));
		$badge = $this->opd_model->get_status_badge($current_status);

		// Cashiers see read-only status only — they cannot change workflow
		if ($this->current_user_is_cashier() && !$this->current_user_is_admin()) {
			return $badge;
		}

		$allowed = $this->opd_model->get_allowed_transitions($current_status);

		// Role-based filtering: only show transitions the current role can action
		$isAdmin   = $this->current_user_is_admin();
		$isDoctor  = $this->current_user_is_doctor();
		$isReception = $this->current_user_is_reception();
		if (!$isAdmin) {
			// Clinical transitions — only doctors (and admin)
			$clinical_only = array('PENDING_LAB', 'PENDING_PHARMACY', 'ADMITTED');
			// Doctor + reception can complete; only doctors set clinical statuses
			if ($isReception) {
				$allowed = array_values(array_diff($allowed, $clinical_only));
			} elseif (!$isDoctor) {
				// Any other role — no transitions
				$allowed = array();
			}
		}

		$btn_map = array(
			'IN_CONSULTATION'    => array('label' => '<i class="fa fa-stethoscope"></i> In Consultation', 'class' => 'btn-warning'),
			'CLINICALLY_CLEARED' => array('label' => '<i class="fa fa-check-circle"></i> Clinically Clear',  'class' => 'btn-success'),
			'PENDING_LAB'        => array('label' => '<i class="fa fa-flask"></i> Pending Lab',               'class' => 'btn-danger'),
			'PENDING_PHARMACY'   => array('label' => '<i class="fa fa-medkit"></i> Pending Pharmacy',         'class' => 'btn-primary'),
			'COMPLETED'          => array('label' => '<i class="fa fa-flag-checkered"></i> Complete',         'class' => 'btn-default'),
			'WAITING'            => array('label' => '<i class="fa fa-clock-o"></i> Set Waiting',             'class' => 'btn-info'),
			'ADMITTED'           => array('label' => '<i class="fa fa-hospital-o"></i> Admit (IPD)',          'class' => 'btn-danger'),
			'CANCELLED'          => array('label' => '<i class="fa fa-times-circle"></i> Cancel',             'class' => 'btn-default'),
		);
		$html = $badge;
		if (empty($allowed)) {
			return $html;
		}
		$html .= '<br>';
		foreach ($allowed as $next) {
			$info = isset($btn_map[$next]) ? $btn_map[$next] : array('label' => $next, 'class' => 'btn-default');
			$html .= '<button type="button"'
				. ' class="btn btn-xs ' . $info['class'] . ' opd-status-btn"'
				. ' data-iop-id="' . htmlspecialchars($iop_id) . '"'
				. ' data-patient-no="' . htmlspecialchars($patient_no) . '"'
				. ' data-status="' . $next . '"'
				. ' style="margin:1px;"'
				. '>' . $info['label'] . '</button>';
		}
		return $html;
	}

	public function update_status_ajax(){
		header('Content-Type: application/json');
		if (!($this->current_user_is_admin() || $this->current_user_is_reception() || $this->current_user_is_doctor())) {
			echo json_encode(array('ok' => false, 'error' => 'Access denied'));
			return;
		}
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$new_status = strtoupper(trim((string)$this->input->post('status')));
		if ($iop_id === '' || $new_status === '') {
			echo json_encode(array('ok' => false, 'error' => 'Missing parameters'));
			return;
		}
		if (!$this->opd_status_engine->is_valid_status($new_status)) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid status value'));
			return;
		}
		$current_status = $this->opd_status_engine->get_status($iop_id);
		if ($current_status === null) {
			$current_status = 'WAITING';
		}
		if (!$this->opd_status_engine->is_valid_transition($current_status, $new_status)) {
			echo json_encode(array(
				'ok'             => false,
				'error'          => 'Invalid transition: ' . $this->opd_model->get_status_label($current_status) . ' → ' . $this->opd_model->get_status_label($new_status),
				'current_status' => $current_status,
			));
			return;
		}
		$user_id = $this->session->userdata('user_id');
		$result = $this->opd_status_engine->transition($iop_id, $new_status, $user_id, null, 'opd::update_status_ajax');
		if (empty($result['success'])) {
			$messages = array();
			if (isset($result['messages']) && is_array($result['messages'])) {
				$messages = $result['messages'];
			} elseif (!empty($result['message'])) {
				$messages = array($result['message']);
			}
			if (empty($messages)) {
				$messages = array('Status update was not allowed.');
			}
			echo json_encode(array(
				'ok' => false,
				'status' => !empty($result['blocked']) ? 'blocked' : 'error',
				'messages' => $messages,
				'error' => implode("\n", $messages),
				'current_status' => $current_status,
			));
			return;
		}
		$new_widget = $this->_build_status_widget($iop_id, $patient_no, $new_status);
		echo json_encode(array(
			'ok'           => true,
			'status'       => 'success',
			'iop_id'       => $iop_id,
			'old_status'   => $current_status,
			'new_status'   => $new_status,
			'label'        => $this->opd_status_engine->get_status_label($new_status),
			'badge'        => $this->opd_status_engine->get_status_badge($new_status),
			'widget'       => $new_widget,
			'allowed_next' => $this->opd_status_engine->get_allowed_transitions($new_status),
		));
	}

	public function validate_opd()
	{
		if ($this->opd_model->validate_opd()) {
			$this->form_validation->set_message("validate_opd", "OPD Patient Already Exists.");
			return false;
		} else {
			return true;
		}
	}

	public function save_opd()
	{
		$overrideReason = trim((string)$this->input->post('override_reason'));
		$isOverride     = ($overrideReason !== '' && $this->current_user_is_admin());
		$patientNoRaw   = trim((string)$this->input->post('patient_no'));
		if (!$isOverride && $patientNoRaw !== '') {
			$this->load->model('app/billing_model');
			if (isset($this->billing_model) && method_exists($this->billing_model, 'check_patient_outstanding_balance_for_registration')) {
				$ob = $this->billing_model->check_patient_outstanding_balance_for_registration($patientNoRaw, 5);
				if ($ob && isset($ob['blocked']) && $ob['blocked']) {
					$listHtml = '';
					if (isset($ob['invoices']) && is_array($ob['invoices'])) {
						foreach ($ob['invoices'] as $inv) {
							$invNo = isset($inv['invoice_no']) ? (string)$inv['invoice_no'] : '';
							$bal = isset($inv['balance_due']) ? (float)$inv['balance_due'] : 0.0;
							if ($invNo !== '' && $bal > 0) {
								$listHtml .= '<li>' . htmlspecialchars($invNo) . ' &mdash; Balance: GHS ' . number_format($bal, 2) . '</li>';
							}
						}
					}
					$this->session->set_flashdata('outstanding_blocked', json_encode(array(
						'patient_no' => $patientNoRaw,
						'balance' => isset($ob['balance']) ? (float)$ob['balance'] : 0.0,
						'count' => isset($ob['count']) ? (int)$ob['count'] : 0,
						'invoices' => isset($ob['invoices']) ? $ob['invoices'] : array(),
					)));
					$this->session->set_flashdata('message',
						"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i>"
						."<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
						."<strong>Registration Blocked:</strong> Patient has an outstanding balance of <strong>GHS "
						. number_format((float)$ob['balance'], 2)
						."</strong> from previous visit(s). Please settle at the cashier before creating a new OPD visit."
						. ($listHtml !== '' ? ("<ul class='mt-1'>" . $listHtml . "</ul>") : '')
						."</div>"
					);
					redirect(base_url() . 'app/opd/opd_reg/' . rawurlencode($patientNoRaw));
					return;
				}
			}
			$clearance = $this->opd_model->check_patient_clearance($patientNoRaw);
			if ($clearance['blocked']) {
				$itemsHtml = '';
				foreach ($clearance['pending_items'] as $pi) {
					$itemsHtml .= '<li><i class="fa ' . htmlspecialchars($pi['icon']) . '"></i> ' . htmlspecialchars($pi['label']) . ' (' . (int)$pi['count'] . ')</li>';
				}
				if ($itemsHtml === '') {
					$itemsHtml = '<li>Previous visit not cleared</li>';
				}
				$this->session->set_flashdata('clearance_blocked', json_encode(array(
					'blocking_iop'  => $clearance['blocking_iop'],
					'pending_items' => $clearance['pending_items'],
					'patient_no'    => $patientNoRaw,
				)));
				$this->session->set_flashdata('message',
					"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i>"
					."<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
					."<strong>Registration Blocked:</strong> Patient has an uncleared previous visit (<strong>"
					. htmlspecialchars((string)$clearance['blocking_iop'])
					."</strong>). Resolve pending items before creating a new visit.<ul class='mt-1'>" . $itemsHtml . "</ul></div>"
				);
				redirect(base_url() . 'app/opd/opd_reg/' . rawurlencode($patientNoRaw));
				return;
			}
		}
		$patientNoRule = $isOverride ? 'trim|xss_clean|required' : 'trim|xss_clean|required|callback_validate_opd';
		$this->form_validation->set_rules("patient_no", "Patient No.", $patientNoRule);
		$this->form_validation->set_rules("department", "Department", "trim|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>", "</div>");

		if ($this->form_validation->run()) {
			if (!$this->input->post('doctor')) {
				$this->load->model('general_model');
				$available = $this->general_model->getDoctorAvailability('IN');
				$doctorIds = array();
				foreach ($available as $d) {
					$doctorIds[] = (string)$d->user_id;
				}
				if (count($doctorIds) === 0) {
					$all = $this->general_model->doctorList();
					foreach ($all as $d) {
						$doctorIds[] = (string)$d->user_id;
					}
				}
				$nextDoctorId = $this->opd_model->get_next_doctor_round_robin($doctorIds, 'OPD_ANY');
				if ($nextDoctorId) {
					$_POST['doctor'] = $nextDoctorId;
				}
			}

			$ins_status = $this->input->post('insurance_card_status');
			if ($ins_status !== null && trim((string)$ins_status) !== '') {
				$this->patient_model->update_insurance_card_status($this->input->post('patient_no'), $ins_status);
			}

			$ins_cover_id = $this->input->post('insurance_cover_id');
			if ($ins_cover_id !== null && trim((string)$ins_cover_id) !== '') {
				$insRow = $this->insurance_company_model->get_insurance_by_id((int)$ins_cover_id);
				if ($insRow) {
					$this->db->where('patient_no', $this->input->post('patient_no'));
					$this->db->update('patient_personal_info', array('Insurance_comp' => $insRow->company_name));
					$billingType = isset($insRow->billing_type) ? $insRow->billing_type : 'CASH';
					$_POST['insurance_billing_type'] = $billingType;
				}
			} else {
				$_POST['insurance_billing_type'] = 'CASH';
			}

			$patientNo = (string)$this->input->post('patient_no');
			$this->patient_model->ensure_nhis_schema();
			$this->load->model('app/billing_model');
			$nhisPayer = $this->billing_model->determine_payer_type($patientNo);

			if ($this->patient_model->is_nhis_active($patientNo)) {
				$nhisInfo = $this->patient_model->get_nhis_info($patientNo);
				$liveStatus = $this->patient_model->compute_nhis_status($nhisInfo->nhis_number, $nhisInfo->nhis_expiry_date);
				if ($liveStatus !== 'ACTIVE') {
					$this->patient_model->update_nhis_info($patientNo, $nhisInfo->nhis_number, $nhisInfo->nhis_expiry_date, (string)$this->session->userdata('user_id'));
					$this->session->set_flashdata('nhis_warning', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Expired:</strong> Patient's NHIS card has expired. Billing will default to CASH.</div>");
					$nhisPayer = 'CASH';
				}
			}

			if ($nhisPayer === 'NHIS') {
				$regFee = (float)$this->billing_model->get_nhis_config('registration_fee_nhis', '0');
				$isReview = $this->billing_model->is_nhis_review_visit($patientNo);
				if ($isReview) {
					$this->session->set_flashdata('nhis_billing_info', "<div class='alert alert-info alert-dismissable'><i class='fa fa-medkit'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Review Visit:</strong> This is a follow-up visit within the review window. Registration &amp; Consultation are <strong>FREE</strong>.</div>");
				} else {
					$conFee = (float)$this->billing_model->get_nhis_config('consultation_fee_nhis', '0');
					$pct = (float)$this->billing_model->get_nhis_config('nhis_subsidy_percent', '100');
					$msg = '<strong>NHIS Patient:</strong> Registration fee: GHS ' . number_format($regFee, 2);
					$msg .= ' | Consultation fee: GHS ' . number_format($conFee, 2);
					$msg .= ' | NHIS subsidy: ' . number_format($pct, 0) . '%';
					$this->session->set_flashdata('nhis_billing_info', "<div class='alert alert-info alert-dismissable'><i class='fa fa-medkit'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $msg . "</div>");
				}
			}

			$this->opd_model->save();

			$this->opd_model->save_vital();

			// NHIS: Generate OTAC for this visit
			if ($nhisPayer === 'NHIS') {
				$_otac = $this->billing_model->generate_otac_for_visit(
					(string)$this->input->post('opdNo'),
					$patientNo,
					null,
					(string)$this->session->userdata('user_id')
				);
				if ($_otac) {
					$this->session->set_flashdata('nhis_otac', "<div class='alert alert-info alert-dismissable'>"
						."<i class='fa fa-key'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
						."<strong>NHIS OTAC Generated:</strong> <code style='font-size:15px;font-weight:bold;'>{$_otac}</code> "
						."&mdash; Valid for 72 hours. Record this code for claims submission.</div>");
				}
			}

			if (!$this->_opd_table_exists('iop_opd_workflow', 'iop_opd_workflow_schema')) {
				$this->opd_model->install_opd_workflow_table();
			}
			$_reg_iop_id = (string)$this->input->post('opdNo');
			$this->load->model('app/nurse_enhancement_model');
			$this->nurse_enhancement_model->ensure_vitals_workflow_schema();
			$this->opd_status_engine->initialize_visit($_reg_iop_id, 'WAITING', $this->session->userdata('user_id'), 'opd::save_opd');

			// Smart Billing: detect visit type and create pending ledger entry
			$_sb_iop_id     = (string)$this->input->post('opdNo');
			$_sb_patient_no = (string)$this->input->post('patient_no');
			$_sb_visit_info = $this->smart_billing_model->detect_visit_type($_sb_patient_no, $_sb_iop_id);
			$this->smart_billing_model->upsert_ledger($_sb_iop_id, $_sb_patient_no, $_sb_visit_info);
			$this->smart_billing_model->log_audit($_sb_iop_id, $_sb_patient_no, 'OPD_REGISTERED', 'Visit type: ' . $_sb_visit_info['visit_type'], $this->session->userdata('user_id'));

			$autoBillingInfoMsg = '';
			$autoBillingWarnMsg = '';
			$this->load->model('app/visit_billing_resolver_model');
			if (isset($this->visit_billing_resolver_model) && method_exists($this->visit_billing_resolver_model, 'auto_bill_visit_fees')) {
				$ab = $this->visit_billing_resolver_model->auto_bill_visit_fees($_sb_iop_id, $_sb_patient_no, (string)$this->session->userdata('user_id'));
				if (is_array($ab) && !empty($ab['ok'])) {
					$createdCount = isset($ab['created']) && is_array($ab['created']) ? count($ab['created']) : 0;
					$errorCount = isset($ab['errors']) && is_array($ab['errors']) ? count($ab['errors']) : 0;
					$this->smart_billing_model->log_audit($_sb_iop_id, $_sb_patient_no, 'AUTO_BILL_VISIT_FEES', 'created=' . $createdCount . '; errors=' . $errorCount, $this->session->userdata('user_id'));
					if ($createdCount > 0) {
						$autoBillingInfoMsg = "<div class='alert alert-info alert-dismissable'><i class='fa fa-money'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Visit fee(s) auto-billed: {$createdCount} item(s).</div>";
						$this->session->set_flashdata('auto_billing_info', $autoBillingInfoMsg);
					}
					if ($errorCount > 0) {
						$autoBillingWarnMsg = "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Auto-billing encountered {$errorCount} issue(s). Please review billing.</div>";
						$this->session->set_flashdata('auto_billing_warning', $autoBillingWarnMsg);
					}
					if ($createdCount === 0 && $errorCount === 0) {
						$autoBillingInfoMsg = "<div class='alert alert-info alert-dismissable'><i class='fa fa-money'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Visit fee auto-billing ran, but created 0 item(s). This can happen if fees are configured as free/disabled, the patient is a returning patient (registration skipped), the visit is a waived follow-up/review, or fees were already billed for this visit.</div>";
						$this->session->set_flashdata('auto_billing_info', $autoBillingInfoMsg);
					}
				} else {
					$err = (is_array($ab) && isset($ab['error'])) ? trim((string)$ab['error']) : '';
					$autoBillingWarnMsg = "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Visit fee auto-billing did not complete." . ($err !== '' ? (' ' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8')) : '') . "</div>";
					$this->session->set_flashdata('auto_billing_warning', $autoBillingWarnMsg);
				}
			} else {
				$autoBillingWarnMsg = "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Visit fee auto-billing is not available on this system (missing auto-bill handler).</div>";
				$this->session->set_flashdata('auto_billing_warning', $autoBillingWarnMsg);
			}

			$assignedDoctor = (string)$this->input->post('doctor');
			if ($assignedDoctor !== '') {
				$this->encounter_owner_model->assign_owner(
					(string)$this->input->post('opdNo'),
					(string)$this->input->post('patient_no'),
					'OPD',
					$assignedDoctor,
					(string)$this->session->userdata('user_id'),
					'opd_registration',
					null
				);
			}

			$this->db->where(array("cCode" => "OUTPATIENTNO", 'InActive' => 0));
			$this->db->update('system_option', array('cValue' => $this->input->post('userID2')));

			// Doctor-busy warning at registration time
			$flash_msgs = array();
			$flash_msgs[] = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>OPD Patient successfully saved!</div>";
			if ($autoBillingInfoMsg !== '') { $flash_msgs[] = $autoBillingInfoMsg; }
			if ($autoBillingWarnMsg !== '') { $flash_msgs[] = $autoBillingWarnMsg; }
			if ($assignedDoctor !== '' && $this->opd_model->table_exists('iop_opd_workflow')) {
				$busy_q = $this->db->query(
					"SELECT W.iop_id, CONCAT(P.firstname,' ',P.lastname) AS patient_name
					 FROM iop_opd_workflow W
					 INNER JOIN patient_details_iop V ON V.IO_ID = W.iop_id AND V.InActive = 0
					 INNER JOIN patient_personal_info P ON P.patient_no = V.patient_no
					 WHERE V.doctor_id = ? AND W.status = 'IN_CONSULTATION'
					   AND W.iop_id <> ?
					 LIMIT 5",
					array($assignedDoctor, (string)$this->input->post('opdNo'))
				);
				$busy_rows = $busy_q ? $busy_q->result() : array();
				if (count($busy_rows) > 0) {
					$doc_q = $this->db->query(
						"SELECT CONCAT(COALESCE(S.cValue,''),' ',U.firstname,' ',U.lastname) AS doc_name
						 FROM users U LEFT JOIN system_parameters S ON S.param_id = U.title
						 WHERE U.user_id = ? LIMIT 1", array($assignedDoctor));
					$doc_name = ($doc_q && $doc_q->row()) ? trim($doc_q->row()->doc_name) : 'The assigned doctor';
					$names = array();
					foreach ($busy_rows as $br) { $names[] = htmlspecialchars(trim($br->patient_name)).' ('.$br->iop_id.')'; }
					$flash_msgs[] = "<div class='alert alert-danger alert-dismissable'><i class='fa fa-user-md'></i>"
						."<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
						."<strong>Doctor Busy:</strong> ".htmlspecialchars($doc_name)." is currently consulting: "
						.implode(', ', $names)
						.". Patient has been set to <strong>WAITING</strong>. Consider re-assigning to another available doctor.</div>";
				}
			}
			$this->session->set_flashdata('message', implode('', $flash_msgs));

			//redirect
			redirect(base_url() . 'app/opd/index', $this->data);
		} else {
			$this->opd_reg($this->input->post('patient_no'));
		}
	}

	public function check_clearance_ajax()
	{
		header('Content-Type: application/json');
		if (!$this->input->is_ajax_request()) {
			echo json_encode(array('ok' => false, 'error' => 'AJAX only'));
			return;
		}
		$patient_no = trim((string)$this->input->post('patient_no'));
		if ($patient_no === '') {
			echo json_encode(array('ok' => true, 'blocked' => false));
			return;
		}
		$clearance = $this->opd_model->check_patient_clearance($patient_no);
		$this->load->model('app/billing_model');
		$ob = array('blocked' => false, 'count' => 0, 'balance' => 0.0, 'invoices' => array());
		if (isset($this->billing_model) && method_exists($this->billing_model, 'check_patient_outstanding_balance_for_registration')) {
			$ob = $this->billing_model->check_patient_outstanding_balance_for_registration($patient_no, 3);
		}
		echo json_encode(array(
			'ok'            => true,
			'blocked'       => ($clearance['blocked'] || (isset($ob['blocked']) && $ob['blocked'])),
			'blocking_iop'  => $clearance['blocking_iop'],
			'pending_items' => $clearance['pending_items'],
			'outstanding_blocked' => isset($ob['blocked']) ? (bool)$ob['blocked'] : false,
			'outstanding_balance' => isset($ob['balance']) ? (float)$ob['balance'] : 0.0,
			'outstanding_count' => isset($ob['count']) ? (int)$ob['count'] : 0,
			'outstanding_invoices' => isset($ob['invoices']) ? $ob['invoices'] : array(),
		));
	}

	public function admit_patient_from_opd()
	{
		header('Content-Type: application/json');
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$reason     = trim((string)$this->input->post('reason'));
		$doctor_id  = trim((string)$this->input->post('doctor_id'));
		$user_id    = (string)$this->session->userdata('user_id');
		
		log_message('info', 'ADMIT_FROM_OPD_REQUEST iop_id='.$iop_id.' patient_no='.$patient_no.' user='.$user_id);
		
		if ($iop_id === '' || $patient_no === '') {
			log_message('error', 'ADMIT_FROM_OPD_FAIL missing_params iop_id='.$iop_id.' patient_no='.$patient_no);
			echo json_encode(array('ok' => false, 'error' => 'Missing iop_id or patient_no'));
			return;
		}
		
		$isDoctor = $this->current_user_is_doctor();
		$isAdmin = $this->current_user_is_admin();
		
		if (!$isDoctor && !$isAdmin) {
			$module = $this->current_user_module_key();
			$roleId = $this->current_user_role_id();
			log_message('error', 'ADMIT_FROM_OPD_DENIED user='.$user_id.' module='.$module.' roleId='.$roleId.' isDoctor='.($isDoctor?'Y':'N').' isAdmin='.($isAdmin?'Y':'N'));
			echo json_encode(array('ok' => false, 'error' => 'Only doctors or admins can admit patients (Your role: '.$module.')'));
			return;
		}
		
		try {
			$queue_id = $this->opd_model->create_admission_queue($iop_id, $patient_no, $reason, $doctor_id ?: $user_id, $user_id);
			$this->opd_model->log_status_transition($iop_id, $patient_no, 'IN_CONSULTATION', 'PENDING_ADMISSION', $user_id, 'admit_from_opd');
			log_message('info', 'ADMIT_FROM_OPD_SUCCESS iop_id='.$iop_id.' queue_id='.$queue_id.' user='.$user_id);
			echo json_encode(array(
				'ok'       => true,
				'queue_id' => $queue_id,
				'message'  => 'Patient queued for IPD admission. IPD staff can complete the registration at /app/ipd/registration.',
			));
		} catch (Exception $e) {
			log_message('error', 'ADMIT_FROM_OPD_EXCEPTION iop_id='.$iop_id.' error='.$e->getMessage());
			echo json_encode(array('ok' => false, 'error' => 'Database error: ' . $e->getMessage()));
		}
	}

	public function detain_patient_ajax()
	{
		header('Content-Type: application/json');
		if (!$this->input->is_ajax_request()) {
			echo json_encode(array('ok' => false, 'error' => 'AJAX only'));
			return;
		}
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$user_id    = (string)$this->session->userdata('user_id');

		if ($iop_id === '' || $patient_no === '') {
			echo json_encode(array('ok' => false, 'error' => 'Missing iop_id or patient_no'));
			return;
		}
		if (!$this->current_user_is_doctor() && !$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'Only doctors or admins can detain patients'));
			return;
		}

		$this->opd_model->ensure_detention_schema();
		$res = $this->opd_model->mark_detention_start($iop_id, $patient_no);
		if (!$res || empty($res['ok'])) {
			$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'Unknown error';
			log_message('error', 'OPD_DETENTION_START_FAIL iop_id='.$iop_id.' patient_no='.$patient_no.' user='.$user_id.' error='.$err);
			echo json_encode(array('ok' => false, 'error' => $err));
			return;
		}
		log_message('info', 'OPD_DETENTION_START_OK iop_id='.$iop_id.' patient_no='.$patient_no.' user='.$user_id);
		echo json_encode($res);
	}

	public function assign_detention_bed_ajax()
	{
		header('Content-Type: application/json');
		if (!$this->input->is_ajax_request()) {
			echo json_encode(array('ok' => false, 'error' => 'AJAX only'));
			return;
		}
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$bed_id     = (int)$this->input->post('bed_id');
		$user_id    = (string)$this->session->userdata('user_id');

		if ($iop_id === '' || $patient_no === '' || $bed_id <= 0) {
			echo json_encode(array('ok' => false, 'error' => 'Missing iop_id, patient_no, or bed_id'));
			return;
		}
		if (!$this->current_user_is_reception() && !$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'Only reception or admins can assign detention beds'));
			return;
		}

		$this->opd_model->ensure_detention_schema();
		$res = $this->opd_model->assign_detention_bed($iop_id, $patient_no, $bed_id);
		if (!$res || empty($res['ok'])) {
			$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'Unknown error';
			log_message('error', 'OPD_DETENTION_BED_FAIL iop_id='.$iop_id.' patient_no='.$patient_no.' bed_id='.$bed_id.' user='.$user_id.' error='.$err);
			echo json_encode(array('ok' => false, 'error' => $err));
			return;
		}
		log_message('info', 'OPD_DETENTION_BED_OK iop_id='.$iop_id.' patient_no='.$patient_no.' bed_id='.$bed_id.' user='.$user_id);
		echo json_encode($res);
	}

	public function reassign_doctor()
	{
		$iop_id = $this->input->post('iop_id');
		$patient_no = $this->input->post('patient_no');
		$doctor_id = $this->input->post('doctor');
		
		// Decode URL-safe IDs
		$iop_id = $this->decode_url_id($iop_id);
		$patient_no = $this->decode_url_id($patient_no);
		
		if ($iop_id && $patient_no && $doctor_id) {
			if (!$this->_opd_table_exists('iop_opd_workflow', 'iop_opd_workflow_schema')) {
				$this->opd_model->install_opd_workflow_table();
			}
			$this->opd_model->update_doctor_assignment($iop_id, $patient_no, $doctor_id, $this->session->userdata('user_id'));
			$this->encounter_owner_model->assign_owner(
				(string)$iop_id,
				(string)$patient_no,
				'OPD',
				(string)$doctor_id,
				(string)$this->session->userdata('user_id'),
				'opd_reassign',
				null
			);
			$flash_msgs = array();
			$flash_msgs[] = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Doctor successfully re-assigned!</div>";
			// Doctor-busy warning on re-assign
			$busy_q = $this->db->query(
				"SELECT W.iop_id, CONCAT(P.firstname,' ',P.lastname) AS patient_name
				 FROM iop_opd_workflow W
				 INNER JOIN patient_details_iop V ON V.IO_ID = W.iop_id AND V.InActive = 0
				 INNER JOIN patient_personal_info P ON P.patient_no = V.patient_no
				 WHERE V.doctor_id = ? AND W.status = 'IN_CONSULTATION'
				   AND W.iop_id <> ?
				 LIMIT 5",
				array((string)$doctor_id, (string)$iop_id)
			);
			$busy_rows = $busy_q ? $busy_q->result() : array();
			if (count($busy_rows) > 0) {
				$doc_q = $this->db->query(
					"SELECT CONCAT(COALESCE(S.cValue,''),' ',U.firstname,' ',U.lastname) AS doc_name
					 FROM users U LEFT JOIN system_parameters S ON S.param_id = U.title
					 WHERE U.user_id = ? LIMIT 1", array((string)$doctor_id));
				$doc_name = ($doc_q && $doc_q->row()) ? trim($doc_q->row()->doc_name) : 'The assigned doctor';
				$names = array();
				foreach ($busy_rows as $br) { $names[] = htmlspecialchars(trim($br->patient_name)).' ('.$br->iop_id.')'; }
				$flash_msgs[] = "<div class='alert alert-danger alert-dismissable'><i class='fa fa-user-md'></i>"
					."<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
					."<strong>Doctor Busy:</strong> ".htmlspecialchars($doc_name)." is currently consulting: "
					.implode(', ', $names)
					.". This patient should wait until the doctor is free.</div>";
			}
			$this->session->set_flashdata('message', implode('', $flash_msgs));
		}
		redirect(base_url() . 'app/opd/index', $this->data);
	}

	public function start_consultation($iop_id, $patient_no)
	{
		// Decode URL-safe IDs
		$iop_id     = $this->decode_url_id($iop_id);
		$patient_no = $this->decode_url_id($patient_no);

		$this->load->library('ConsultationEligibilityService');
		$gate = $this->consultationeligibilityservice->evaluate_visit_consultation_gate($iop_id);
		if (empty($gate['ok']) || empty($gate['can_start'])) {
			$ui = $this->consultationeligibilityservice->get_gate_ui_payload($iop_id);
			$tip = (is_array($ui) && isset($ui['tooltip'])) ? (string)$ui['tooltip'] : 'Payment required before consultation.';
			$url = (is_array($ui) && isset($ui['action_url'])) ? (string)$ui['action_url'] : (base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id));
			$this->session->set_flashdata('message',
				"<div class='alert alert-danger alert-dismissable'>"
				. "<i class='fa fa-lock'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "<strong>Consultation Blocked:</strong> " . htmlspecialchars($tip)
				. " <a class='btn btn-xs btn-danger' style='margin-left:6px;' href='" . htmlspecialchars($url) . "'><i class='fa fa-credit-card'></i> Go to Cashier</a>"
				. "</div>"
			);
			redirect(base_url() . 'app/opd/index');
			return;
		}

		if (!($this->current_user_is_admin() || $this->current_user_is_reception())) {
			if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'start_consultation')) {
				return;
			}
		}

		$messages  = array();
		$user_id   = (string)$this->session->userdata('user_id');
		$isAdmin   = $this->current_user_is_admin();

		// Resolve the assigned doctor for this visit
		$visit     = $this->db->get_where('patient_details_iop', array('IO_ID' => (string)$iop_id, 'InActive' => 0), 1)->row();
		$doctor_id = ($visit && isset($visit->doctor_id)) ? (string)$visit->doctor_id : '';

		// Doctor-busy enforcement: HARD BLOCK for non-admins
		if ($doctor_id !== '') {
			$active = $this->opd_model->get_doctor_active_consultation($doctor_id, $iop_id);
			if ($active) {
				$doc_q    = $this->db->query(
					"SELECT CONCAT(COALESCE(S.cValue,''),' ',U.firstname,' ',U.lastname) AS doc_name
					 FROM users U LEFT JOIN system_parameters S ON S.param_id = U.title
					 WHERE U.user_id = ? LIMIT 1", array($doctor_id));
				$doc_name = ($doc_q && $doc_q->row()) ? trim($doc_q->row()->doc_name) : 'The assigned doctor';
				$busy_patient = htmlspecialchars(trim($active->patient_name)) . ' (' . $active->iop_id . ')';

				if (!$isAdmin) {
					// Non-admin: hard block — do not start
					$this->session->set_flashdata('message',
						"<div class='alert alert-danger alert-dismissable'>"
						. "<i class='fa fa-ban'></i>"
						. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
						. "<strong>Consultation Blocked:</strong> "
						. htmlspecialchars($doc_name) . " is currently consulting <strong>" . $busy_patient . "</strong>. "
						. "Only one patient may be consulted at a time. Complete or reassign the current consultation first."
						. "</div>");
					redirect(base_url() . 'app/opd/index');
					return;
				}

				// Admin: allow override but show a prominent warning
				$messages[] = "<div class='alert alert-warning alert-dismissable'>"
					. "<i class='fa fa-exclamation-triangle'></i>"
					. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
					. "<strong>Admin Override:</strong> "
					. htmlspecialchars($doc_name) . " already has an active consultation with <strong>" . $busy_patient . "</strong>."
					. "</div>";
			}
		}

		// Proceed with transition via unified status engine
		$result = $this->opd_status_engine->start_consultation($iop_id, $user_id);

		if ($result['success']) {
			$messages[] = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. "Consultation started successfully.</div>";
		} else {
			$messages[] = "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i>"
				. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
				. htmlspecialchars($result['message']) . "</div>";
		}

		$this->session->set_flashdata('message', implode('', $messages));
		redirect(base_url() . 'app/opd/index');
	}

	/**
	 * AJAX endpoint for Start Consultation button in queue table.
	 * Returns JSON — used by the live queue UI to start a consultation inline.
	 */
	public function start_consultation_ajax()
	{
		header('Content-Type: application/json');

		if (!($this->current_user_is_admin() || $this->current_user_is_reception() || $this->current_user_is_doctor())) {
			echo json_encode(array('ok' => false, 'error' => 'Access denied'));
			return;
		}

		$iop_id     = $this->decode_url_id(trim((string)$this->input->post('iop_id')));
		$patient_no = $this->decode_url_id(trim((string)$this->input->post('patient_no')));
		$user_id    = (string)$this->session->userdata('user_id');
		$isAdmin    = $this->current_user_is_admin();

		if ($iop_id === '') {
			echo json_encode(array('ok' => false, 'error' => 'Missing visit ID'));
			return;
		}

		$this->load->library('ConsultationEligibilityService');
		$gate = $this->consultationeligibilityservice->evaluate_visit_consultation_gate($iop_id);
		if (empty($gate['ok']) || empty($gate['can_start'])) {
			$ui = $this->consultationeligibilityservice->get_gate_ui_payload($iop_id);
			echo json_encode(array(
				'ok' => false,
				'blocked' => true,
				'block_type' => 'PAYMENT',
				'error' => (is_array($ui) && isset($ui['tooltip']) && $ui['tooltip'] !== '') ? (string)$ui['tooltip'] : 'Payment required before consultation',
				'payment_url' => (is_array($ui) && isset($ui['action_url'])) ? (string)$ui['action_url'] : (base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id)),
				'gate' => $ui,
			));
			return;
		}

		// Resolve assigned doctor
		$visit     = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0), 1)->row();
		$doctor_id = ($visit && isset($visit->doctor_id)) ? (string)$visit->doctor_id : '';

		// Single-consultation enforcement
		if ($doctor_id !== '') {
			$active = $this->opd_model->get_doctor_active_consultation($doctor_id, $iop_id);
			if ($active && !$isAdmin) {
				$doc_q    = $this->db->query(
					"SELECT CONCAT(COALESCE(S.cValue,''),' ',U.firstname,' ',U.lastname) AS doc_name
					 FROM users U LEFT JOIN system_parameters S ON S.param_id = U.title
					 WHERE U.user_id = ? LIMIT 1", array($doctor_id));
				$doc_name = ($doc_q && $doc_q->row()) ? trim($doc_q->row()->doc_name) : 'The assigned doctor';
				echo json_encode(array(
					'ok'        => false,
					'blocked'   => true,
					'block_type'=> 'DOCTOR_BUSY',
					'error'     => htmlspecialchars($doc_name) . ' is already in consultation with '
					             . htmlspecialchars(trim($active->patient_name))
					             . ' (' . $active->iop_id . '). Complete that consultation first.',
				));
				return;
			}
		}

		$result = $this->opd_status_engine->start_consultation($iop_id, $user_id);

		if ($result['success']) {
			echo json_encode(array('ok' => true, 'iop_id' => $iop_id, 'new_status' => 'IN_CONSULTATION'));
		} else {
			echo json_encode(array('ok' => false, 'error' => $result['message']));
		}
	}

	public function view()
	{
		// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
		$iop_no = $this->segment_decoded(4);
		$patient_no = $this->segment_decoded(5);
		if ($this->current_user_is_cashier() && !$this->current_user_is_admin()) {
			redirect(base_url() . 'app/cashier/billing_queue?iop_id=' . urlencode((string)$iop_no) . '&patient_no=' . urlencode((string)$patient_no));
			return;
		}

		$this->load->library('EncounterContext');
		$ctx = $this->encountercontext->resolve('OPD', $iop_no, $patient_no, array(
			'include_billing' => true,
			'include_vitals' => true,
			'include_timeline' => true,
			'timeline_limit' => 30,
			'log_non_owner_view' => true,
			'hasAccesstoDoctor' => (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) ? true : false,
			'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
		));
		if (!$ctx || !isset($ctx['ok']) || !$ctx['ok']) {
			$code = isset($ctx['error_code']) ? (string)$ctx['error_code'] : '';
			if ($code === 'PATIENT_NOT_FOUND') {
				$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Patient information not found.</div>");
				redirect(base_url() . 'app/opd/index');
				return;
			}
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd');
			return;
		}

		if (isset($ctx['canonical_redirect_url']) && $ctx['canonical_redirect_url']) {
			redirect($ctx['canonical_redirect_url']);
			return;
		}
		$iop_no = $ctx['iop_no'];
		$patient_no = $ctx['patient_no'];
		if (isset($ctx['data']) && is_array($ctx['data'])) {
			foreach ($ctx['data'] as $k => $v) {
				$this->data[$k] = $v;
			}
		}

		$this->load->model('app/smart_billing_model');
		$vtMap = $this->smart_billing_model->get_visit_type_map(array($iop_no));
		$this->data['visit_type_entry'] = isset($vtMap[$iop_no]) ? $vtMap[$iop_no] : null;

		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view("app/opd/view", $this->data);
	}

	/**
	 * Resolve iop_no + patient_no with DB fallback.
	 * Returns ['iop_no' => ..., 'patient_no' => ..., 'getOPDPatient' => ..., 'patientInfo' => ...]
	 * or false if the visit/patient cannot be found.
	 */
	private function _resolve_opd_visit($iop_no, $patient_no) {
		$opd = $this->opd_model->getOPDPatient($iop_no);

		// Fallback: look up by patient_no to get the real IO_ID
		if (!$opd && !empty($patient_no)) {
			$rec = $this->db->query(
				"SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND InActive = 0 ORDER BY date_visit DESC LIMIT 1",
				array($patient_no)
			);
			if ($rec && $rec->num_rows() > 0) {
				$real_iop = $rec->row()->IO_ID;
				$opd = $this->opd_model->getOPDPatient($real_iop);
				if ($opd) { $iop_no = $real_iop; }
			}
		}

		$info = $this->patient_model->getPatientInfo($patient_no);
		// Fallback: pull patient_no from OPD record
		if (!$info && $opd) {
			$patient_no = $opd->patient_no;
			$info = $this->patient_model->getPatientInfo($patient_no);
		}

		if (!$opd || !$info) { return false; }

		return [
			'iop_no'        => $iop_no,
			'patient_no'    => $patient_no,
			'getOPDPatient' => $opd,
			'patientInfo'   => $info,
		];
	}

	public function diagnosis()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];

		$this->data['diagnosisList'] = $this->opd_model->diagnosisList();
		$this->data['patientDiagnosis'] = $this->opd_model->patientDiagnosis($iop_no);

		$this->load->view("app/opd/diagnosis", $this->data);
	}

	public function validate_diagnosis()
	{
		if ($this->opd_model->validate_diagnosis()) {
			$this->form_validation->set_message("validate_diagnosis", "Diagnosis Already Exists.");
			return false;
		} else {
			return true;
		}
	}

	public function save_diagnosis()
	{
		$this->form_validation->set_rules("diagnosis", "Diagnosis", "trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>", "</div>");

		$opd_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		// $diagnosis_text = $this->input->post('diagnosis_text');

		if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'OPD', 'save_diagnosis')) {
			return;
		}

		if ($this->form_validation->run()) {

			$this->opd_model->save_diagnosis();

			$this->billing_model->log_nhis_audit('DOCTOR_SAVE_DIAGNOSIS', 'iop_diagnosis', $opd_no,
				null, $this->input->post('diagnosis'), $this->session->userdata('user_id'), $patient_no, $opd_no);

			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Diagnosis successfully Added!</div>");

			//redirect
			redirect(base_url() . 'app/opd/diagnosis/' . $opd_no . '/' . $patient_no, $this->data);
		} else {
			redirect(base_url() . 'app/opd/diagnosis/' . $opd_no . '/' . $patient_no, $this->data);
		}
	}


	public function delete_complain()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'delete_complain')) {
			return;
		}

		$this->db->query("UPDATE iop_complaints SET InActive = 1 WHERE iop_comp_id = ?", array((int)$id));

		redirect(base_url() . 'app/opd/complain/' . url_safe_id($iop_no) . '/' . $patient_no);
	}

	public function delete_medication()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'delete_medication')) {
			return;
		}
		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => (int)$id, 'InActive' => 0))->row();
			$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : '';
			if ($st !== '' && $st !== 'PENDING') {
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cannot delete a prescription after verification/workflow has started. Current status: <strong>" . htmlspecialchars($st) . "</strong>.</div>");
				redirect(base_url() . 'app/opd/medication/' . url_safe_id($iop_no) . '/' . $patient_no);
				return;
			}
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication successfully Deleted!</div>");

		$this->db->query("UPDATE iop_medication SET InActive = 1 WHERE iop_med_id = ?", array((int)$id));

		redirect(base_url() . 'app/opd/medication/' . url_safe_id($iop_no) . '/' . $patient_no);
	}

	public function delete_diagnos()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'delete_diagnos')) {
			return;
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Diagnosis successfully Deleted!</div>");

		$this->db->query("UPDATE iop_diagnosis SET InActive = 1 WHERE iop_diag_id = ?", array((int)$id));

		redirect(base_url() . 'app/opd/diagnosis/' . url_safe_id($iop_no) . '/' . $patient_no);
	}

	/**
	 * Batch save diagnoses - AJAX endpoint for multi-entry system
	 */
	public function save_diagnosis_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
			return;
		}
		
		try {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$entries_raw = $this->input->post('entries');
			$entries = json_decode($entries_raw, true);

			// Decode the URL-safe ID
			$iop_id = url_decode_id($opd_no);

			if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'save_diagnosis_batch', true)) {
				echo json_encode(array('success' => false, 'message' => 'Access denied'));
				return;
			}

			if (empty($entries) || !is_array($entries)) {
				echo json_encode(array('success' => false, 'message' => 'No items to save'));
				return;
			}

			$saved = 0;

			foreach ($entries as $entry) {
				$diag_id = isset($entry['diagnosis_id']) ? trim($entry['diagnosis_id']) : '';
				$diag_text = isset($entry['diagnosis_text']) ? trim($entry['diagnosis_text']) : '';
				$remarks = isset($entry['remarks']) ? trim($entry['remarks']) : '';

				if (empty($diag_id) && empty($diag_text)) continue;

				$icd_code = null;
				if ($diag_id && is_numeric($diag_id)) {
					$dq = $this->db->query("SELECT icd_code FROM diagnosis WHERE diagnosis_id = ? AND InActive = 0 LIMIT 1", array((int)$diag_id));
					if ($dq && $dq->num_rows() > 0) {
						$icd_code = $dq->row()->icd_code;
					}
				}

				$data = array(
					'iop_id' => $iop_id,
					'diagnosis_id' => ($diag_id && is_numeric($diag_id)) ? (int)$diag_id : 0,
					'diagnosis_text' => $diag_text,
					'remarks' => $remarks,
					'dDate' => date("Y-m-d H:i:s"),
					'InActive' => 0
				);

				$col_q = $this->db->query("SHOW COLUMNS FROM `iop_diagnosis` LIKE 'icd_code'");
				if ($col_q && $col_q->num_rows() > 0) {
					$data['icd_code'] = $icd_code;
				}

				if ($this->db->insert('iop_diagnosis', $data)) {
					$saved++;
				}
			}

			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$saved} diagnosis(es) successfully added!</div>");

			echo json_encode(array(
				'success' => true,
				'saved' => $saved,
				'message' => "{$saved} diagnosis(es) saved successfully",
				'redirect' => base_url() . 'app/opd/diagnosis/' . url_safe_id($iop_id) . '/' . $patient_no
			));
		} catch (Exception $e) {
			echo json_encode(array('success' => false, 'message' => 'Server error: ' . $e->getMessage()));
		}
	}

	/**
	 * Batch save complaints - AJAX endpoint for multi-entry system
	 */
	public function save_complaint_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Invalid request method'));
			return;
		}

		$opd_no     = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		$entries    = json_decode($this->input->post('entries'), true);

		// Decode URL-safe ID before permission check (OP-000004 -> OP 000004)
		$iop_id = url_decode_id($opd_no);

		// ── Permission ───────────────────────────────────────────────────────
		if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'save_complaint_batch', true)) {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Access denied'));
			return;
		}

		// ── Input validation ─────────────────────────────────────────────────
		if (empty($entries) || !is_array($entries)) {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'No complaints to save'));
			return;
		}

		// Validate iop_id exists in patient_details_iop before inserting
		$visit_q = $this->db->query(
			"SELECT IO_ID FROM patient_details_iop WHERE IO_ID = ? AND InActive = 0 LIMIT 1",
			array($iop_id)
		);
		if (!$visit_q || $visit_q->num_rows() === 0) {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Invalid OPD visit'));
			return;
		}

		// ── Session / audit fields ───────────────────────────────────────────
		$recorded_by = (string)$this->session->userdata('user_id');
		$now         = date("Y-m-d H:i:s");
		$today       = date("Y-m-d");

		// ── Batch-level clinical fields (shared across all complaints) ───────
		// These come from the Phase 2 UI's cm-duration / cm-severity / cm-onset
		// They are sent per-entry in the entries array from the frontend
		// (each entry carries: complain, severity, duration, onset)

		// ── Determine available columns BEFORE transaction (avoids SHOW COLUMNS inside loop) ──
		$has_severity    = $this->opd_model->column_exists('iop_complaints', 'severity');
		$has_duration    = $this->opd_model->column_exists('iop_complaints', 'duration');
		$has_onset       = $this->opd_model->column_exists('iop_complaints', 'onset');
		$has_recorded_by = $this->opd_model->column_exists('iop_complaints', 'recorded_by');
		$has_text        = $this->opd_model->column_exists('iop_complaints', 'complain_text');

		// ── Begin transaction — all-or-nothing ───────────────────────────────
		$this->db->trans_start();

		$saved       = 0;
		$ignored     = 0;
		$errors      = array();
		$usage_queue = array(); // [{ complain_id, complain_name }] for post-commit tracking

		foreach ($entries as $idx => $entry) {

			// Sanitize complaint text
			$raw_text = isset($entry['complain']) ? trim(strip_tags((string)$entry['complain'])) : '';
			if ($raw_text === '') {
				$ignored++;
				continue;
			}

			// Sanitize clinical fields
			$severity = isset($entry['severity']) ? trim(strip_tags((string)$entry['severity'])) : '';
			$duration = isset($entry['duration']) ? trim(strip_tags((string)$entry['duration'])) : '';
			$onset    = isset($entry['onset'])    ? trim(strip_tags((string)$entry['onset']))    : '';

			// Whitelist severity values
			$valid_severities = array('', 'Mild', 'Moderate', 'Severe');
			if (!in_array($severity, $valid_severities, true)) { $severity = ''; }

			// Whitelist onset values
			$valid_onsets = array('', 'Acute', 'Chronic', 'Recurrent');
			if (!in_array($onset, $valid_onsets, true)) { $onset = ''; }

			// Duration: strip to 100 chars max
			if (strlen($duration) > 100) { $duration = substr($duration, 0, 100); }

			// Resolve complain_id: try to match against master list by name,
			// fall back to 'others' for custom/free-text entries
			$complain_id = 'others';
			$complain_name_check = strtoupper($raw_text);
			$master_q = $this->db->query(
				"SELECT complain_id FROM complain WHERE UPPER(complain_name) = ? AND InActive = 0 LIMIT 1",
				array($complain_name_check)
			);
			if ($master_q && $master_q->num_rows() > 0) {
				$complain_id = (int)$master_q->row()->complain_id;
			}

			// ── Duplicate check: same iop_id + same complain identifier + same day ──
			// For structured: match on complain_id (int)
			// For free-text: match on complain_text exact
			$is_duplicate = false;
			if ($complain_id !== 'others') {
				$dup_q = $this->db->query(
					"SELECT iop_comp_id FROM iop_complaints
					 WHERE iop_id = ? AND complain_id = ? AND DATE(dDate) = ? AND InActive = 0
					 LIMIT 1",
					array($iop_id, $complain_id, $today)
				);
				$is_duplicate = ($dup_q && $dup_q->num_rows() > 0);
			} else {
				if ($has_text) {
					$dup_q = $this->db->query(
						"SELECT iop_comp_id FROM iop_complaints
						 WHERE iop_id = ? AND complain_id = 'others'
						   AND complain_text = ? AND DATE(dDate) = ? AND InActive = 0
						 LIMIT 1",
						array($iop_id, $raw_text, $today)
					);
					$is_duplicate = ($dup_q && $dup_q->num_rows() > 0);
				}
			}

			if ($is_duplicate) {
				$ignored++;
				continue;
			}

			// ── Build insert row ─────────────────────────────────────────────
			$data = array(
				'iop_id'      => $iop_id,
				'complain_id' => $complain_id,
				'dDate'       => $now,
				'InActive'    => 0,
			);

			// complain_text: always store original text for display
			if ($has_text) {
				$data['complain_text'] = $raw_text;
			}

			// Proper clinical columns (Phase 1 added these)
			if ($has_severity)    { $data['severity']    = $severity; }
			if ($has_duration)    { $data['duration']    = $duration; }
			if ($has_onset)       { $data['onset']       = $onset; }
			if ($has_recorded_by) { $data['recorded_by'] = $recorded_by; }

			if ($this->db->insert('iop_complaints', $data)) {
				$saved++;
				$usage_queue[] = array(
					'complain_id'   => $complain_id,
					'complain_name' => $raw_text,
				);
			} else {
				$errors[] = "Row {$idx}: DB insert failed";
			}
		}

		// ── Commit or rollback ───────────────────────────────────────────────
		$this->db->trans_complete();

		if ($this->db->trans_status() === false || !empty($errors)) {
			// Transaction rolled back — no partial inserts
			echo json_encode(array(
				'status'  => 'error',
				'success' => false,
				'message' => 'Failed to save complaints. No records were inserted.',
				'errors'  => $errors,
			));
			return;
		}

		// ── Update usage counts (outside transaction — non-blocking) ─────────
		if (!empty($usage_queue) && $recorded_by !== '') {
			foreach ($usage_queue as $u) {
				$this->opd_model->increment_complaint_usage(
					$recorded_by,
					$u['complain_id'],
					$u['complain_name']
				);
			}
		}

		// ── Build response ───────────────────────────────────────────────────
		$status = 'success';
		$msg    = "{$saved} complaint(s) saved successfully.";
		if ($ignored > 0 && $saved > 0) {
			$status = 'warning';
			$msg    = "{$saved} complaint(s) saved. {$ignored} duplicate(s) ignored.";
		} elseif ($ignored > 0 && $saved === 0) {
			// Nothing saved — all were duplicates. Return error so UI does not redirect silently.
			echo json_encode(array(
				'status'      => 'warning',
				'success'     => false,
				'saved_count' => 0,
				'ignored'     => $ignored,
				'message'     => "All selected complaint(s) are already recorded for this visit today.",
			));
			return;
		}

		$this->session->set_flashdata('message',
			"<div class='alert alert-success alert-dismissable'>"
			. "<i class='fa fa-check'></i>"
			. "<button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>"
			. htmlspecialchars($msg)
			. "</div>"
		);

		echo json_encode(array(
			'status'      => $status,
			'success'     => true,
			'saved_count' => $saved,
			'ignored'     => $ignored,
			'message'     => $msg,
			'redirect'    => base_url() . 'app/opd/complain/' . url_safe_id($iop_id) . '/' . $patient_no,
		));
	}

	/**
	 * Batch save laboratory requests - AJAX endpoint for multi-entry system
	 *  - Now integrated with Unified Billing System (auto-creates billing entries)
	 */
	public function save_laboratory_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
			return;
		}

		// Disable db_debug so SQL errors are catchable instead of CI exit()ing with HTML
		$_db_debug_orig = $this->db->db_debug;
		$this->db->db_debug = false;

		try {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$entries = json_decode($this->input->post('entries'), true);

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'OPD', 'save_laboratory_batch', true)) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'Access denied'));
				return;
			}

			if (empty($entries) || !is_array($entries)) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'No items to save'));
				return;
			}

			$iop_id    = url_decode_id($opd_no);
			$saved     = 0;
			$ignored   = 0;
			$doctor_id = $this->session->userdata('user_id');

			$this->load->model('app/billing_model');
			$this->load->model('app/laboratory_model');
			$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
			$this->load->model('app/Nhis_validation_model', 'nhis_validation');
			$batchPayerType = $this->billing_model->determine_payer_type((string)$patient_no);
			$batchNhisFlag  = ($batchPayerType === 'NHIS') ? 1 : 0;
			$hasPriorityCol    = $this->laboratory_model->column_exists('iop_laboratory', 'priority');
			$hasRequestedByCol = $this->laboratory_model->column_exists('iop_laboratory', 'requested_by');
			$hasPayerTypeCol   = $this->laboratory_model->column_exists('iop_laboratory', 'payer_type');
			$hasNhisFlagCol    = $this->laboratory_model->column_exists('iop_laboratory', 'nhis_flag');
			$hasClinicalNotesCol = $this->laboratory_model->column_exists('iop_laboratory', 'clinical_notes');
			$hasSpecimenTypeCol  = $this->laboratory_model->column_exists('iop_laboratory', 'specimen_type');

			foreach ($entries as $entry) {
				$lab_id = isset($entry['laboratory_id']) ? (int)$entry['laboratory_id'] : 0;
				if ($lab_id <= 0) continue;

				$remarks  = isset($entry['remarks'])  ? trim((string)$entry['remarks'])  : '';
				$priority = isset($entry['priority']) ? trim((string)$entry['priority']) : 'Normal';

				$bp = $this->db->query(
					"SELECT particular_id, particular_name, group_id FROM bill_particular WHERE particular_id = ? AND InActive = 0 LIMIT 1",
					array($lab_id)
				)->row();
				if (!$bp) continue;

				$lab_text      = (string)$bp->particular_name;
				$category_id   = (int)$bp->group_id;
				$billing_id    = (int)$bp->particular_id;
				$specimen_type = '';

				$nowDate = date('Y-m-d');
				$nowDateTime = date('Y-m-d H:i:s');
				$data = array(
					'iop_id'          => $iop_id,
					'laboratory_id'   => $billing_id,
					'category_id'     => (int)$category_id,
					'laboratory_text' => $lab_text,
					'findings'        => '',
					'result'          => '',
					'doctor'          => $doctor_id,
					'dDate'           => $nowDate,
					'dDateTime'       => $nowDateTime,
					'InActive'        => 0,
				);

				if ($hasClinicalNotesCol && $remarks !== '') { $data['clinical_notes'] = $remarks; }
				if ($hasPriorityCol)    { $data['priority']    = $priority; }
				if ($hasRequestedByCol) { $data['requested_by'] = $doctor_id; }
				if ($hasPayerTypeCol)   { $data['payer_type']   = $batchPayerType; }
				if ($hasNhisFlagCol)    { $data['nhis_flag']    = $batchNhisFlag; }
				if ($hasSpecimenTypeCol && $specimen_type !== '') { $data['specimen_type'] = $specimen_type; }

				$this->db->where(array(
					'iop_id' => $iop_id,
					'category_id' => (int)$category_id,
					'laboratory_id' => (int)$billing_id,
					'InActive' => 0
				));
				$this->db->where("(result = '' OR result IS NULL)", null, false);
				$this->db->limit(1);
				$dup = $this->db->get('iop_laboratory')->row();
				if ($dup) {
					$ignored++;
					continue;
				}

				if ($this->db->insert('iop_laboratory', $data)) {
					$io_lab_id_batch = (int)$this->db->insert_id();
					$saved++;

					try {
						$det = $this->diag_fin_state->get_financial_state_detail('LAB', $io_lab_id_batch);
						$state = isset($det['state']) ? (string)$det['state'] : 'REQUESTED';
						$ref = isset($det['resolved_item_ref']) ? (string)$det['resolved_item_ref'] : '';
						log_message('debug', '[DIAG_STATE] module=LAB id=' . $io_lab_id_batch . ' resolved_ref=' . $ref . ' state=' . $state);
						$dr = $this->diag_fin_state->detect_drift('LAB', $io_lab_id_batch, true);
						if (isset($dr['drift_types']) && is_array($dr['drift_types']) && !empty($dr['drift_types'])) {
							$dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : '';
							$sev = isset($dr['severity']) ? (string)$dr['severity'] : 'NONE';
							log_message('debug', '[DIAG_DRIFT] module=LAB id=' . $io_lab_id_batch . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
							if ($sev === 'CRITICAL') {
								log_message('error', '[DIAG_ALERT] module=LAB id=' . $io_lab_id_batch . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
							}
							if (in_array('POLICY_VIOLATION', $dr['drift_types'], true) || in_array('UNDERPAID_RELEASE', $dr['drift_types'], true)) {
								$pr = isset($dr['policy_reason']) ? (string)$dr['policy_reason'] : '';
								$msg = in_array('UNDERPAID_RELEASE', $dr['drift_types'], true) ? 'Payment below required threshold' : 'Policy denied but flow continued';
								log_message('error', '[POLICY_WARNING] module=LAB id=' . $io_lab_id_batch . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev . ' policy_reason=' . $pr . ' msg=' . $msg);
							}
						}
					} catch (\Throwable $e) {
					}

					// Non-fatal post-insert operations
					try {
						$this->laboratory_model->upsert_workflow_status($io_lab_id_batch, 'REQUESTED', $doctor_id);
					} catch (\Throwable $e) { log_message('error', 'Lab batch workflow error: ' . $e->getMessage()); }

					try {
						$this->laboratory_model->ensure_lab_charge_posted(
							$io_lab_id_batch, $iop_id, $patient_no, 'OPD',
							$billing_id, $lab_text, $doctor_id, $batchPayerType
						);
					} catch (\Throwable $e) { log_message('error', 'Lab batch charge error: ' . $e->getMessage()); }

					try {
						$this->load->library('billing_automation');
						$this->billing_automation->on_lab_ordered(
							$patient_no, $iop_id, 'OPD', $billing_id, $lab_text, $doctor_id
						);
					} catch (\Throwable $e) { log_message('error', 'Lab batch billing error: ' . $e->getMessage()); }

					try {
						$validation = $this->nhis_validation->validate_service('LAB', (int)$io_lab_id_batch);
						log_message(
							'debug',
							'[NHIS_VALIDATION] module=LAB id=' . (int)$io_lab_id_batch
							. ' valid=' . (!empty($validation['valid']) ? '1' : '0')
							. ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
						);
					} catch (\Throwable $e) {
					}
				}
			}

			$msg_extra = ($ignored > 0) ? (' ' . $ignored . ' duplicate request(s) ignored.') : '';
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$saved} lab request(s) successfully added!{$msg_extra}</div>");

			$this->db->db_debug = $_db_debug_orig;

			echo json_encode(array(
				'success' => true,
				'saved' => $saved,
				'ignored' => $ignored,
				'message' => "{$saved} lab request(s) saved successfully" . ($ignored > 0 ? (" ({$ignored} duplicate ignored)") : ''),
				'redirect' => base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));

		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'save_laboratory_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	/**
	 * Batch save medications - AJAX endpoint for multi-entry system
	 *
	 * FIXED: (1) SHOW COLUMNS hoisted outside loop, (2) transaction wraps all inserts,
	 *        (3) NHIS payment gate enforced, (4) CDS safety check per drug,
	 *        (5) prescribed_by always populated, (6) pharmacy + unified billing queues,
	 *        (7) NHIS audit log per drug.
	 */
	public function save_medication_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Invalid request method'));
			return;
		}
		$opd_no     = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		$entries_raw = $this->input->post('entries');
		$entries = json_decode($entries_raw, true);

		if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'OPD', 'save_medication_batch', true)) {
			echo json_encode(array('success' => false, 'message' => 'Access denied'));
			return;
		}

		if (empty($entries) || !is_array($entries)) {
			echo json_encode(array('success' => false, 'message' => 'No items to save'));
			return;
		}

		$iop_id    = url_decode_id($opd_no);
		$doctor_id = (string)$this->session->userdata('user_id');
		$patNo     = trim((string)$patient_no);

		// ── Build medication array for shared validation kernel ───────────────
		$med_list = array();
		foreach ($entries as $idx => $entry) {
			$med_list[$idx] = array(
				'drug_id'        => isset($entry['drug_name'])       ? (int)$entry['drug_name']             : 0,
				'dosage'         => isset($entry['dosage'])          ? trim($entry['dosage'])                : '',
				'frequency'      => isset($entry['frequency'])       ? trim($entry['frequency'])             : '',
				'days'           => isset($entry['days'])            ? (int)$entry['days']                  : 1,
				'diagnosis_code' => isset($entry['diagnosis_code'])  ? trim($entry['diagnosis_code'])        : '',
			);
		}

		// ── Shared validation: NHIS gate + CDS + formulary ───────────────────
		$val = $this->_run_prescription_validation($iop_id, $patNo, $med_list);

		if ($val['status'] === 'nhis_block') {
			echo json_encode(array(
				'status'     => 'nhis_block',
				'success'    => false,
				'saved'      => 0,
				'blocked'    => 0,
				'warnings'   => array(),
				'message'    => $val['message'],
				'nhis_block' => true,
			));
			return;
		}

		if ($val['status'] === 'blocked') {
			echo json_encode(array(
				'status'   => 'blocked',
				'success'  => false,
				'saved'    => 0,
				'blocked'  => count($val['blocked_drugs']),
				'warnings' => array(),
				'message'  => $val['message'],
				'details'  => $val['blocked_drugs'],
			));
			return;
		}

		$payer      = $val['payer'];
		$schema     = $val['schema'];
		$cdsLoaded  = $val['cds_loaded'];
		$warnings   = array_merge($val['nhis_warnings'], $val['cds_warnings']);

		// ── Transaction: all-or-nothing inserts ───────────────────────────────
		$this->db->trans_start();

		$saved       = 0;
		$saved_drugs = array();

		foreach ($entries as $entry) {
			$drug_id       = isset($entry['drug_name'])     ? (int)$entry['drug_name']                      : 0;
			$medicine_text = isset($entry['medicine_text']) ? strip_tags(trim($entry['medicine_text']))      : '';

			if (!$drug_id && empty($medicine_text)) continue;

			$data = array(
				'iop_id'        => $iop_id,
				'medicine_id'   => $drug_id ?: null,
				'medicine_text' => $medicine_text,
				'dosage'        => isset($entry['dosage'])      ? strip_tags(trim($entry['dosage']))      : '',
				'instruction'   => isset($entry['instruction']) ? strip_tags(trim($entry['instruction'])) : '',
				'advice'        => isset($entry['advice'])      ? strip_tags(trim($entry['advice']))      : '',
				'days'          => isset($entry['days'])        ? max(1, (int)$entry['days'])             : 1,
				'total_qty'     => isset($entry['total_qty'])   ? max(0.01, (float)$entry['total_qty'])     : 1,
				'cPreparedBy'   => $doctor_id,
				'dDate'         => date('Y-m-d H:i:s'),
				'InActive'      => 0,
			);

			if ($schema['frequency'])             $data['frequency']            = isset($entry['frequency'])           ? strip_tags(trim($entry['frequency']))            : '';
			if ($schema['prescribed_by'])         $data['prescribed_by']        = $doctor_id;
			if ($schema['dispensing_status'])     $data['dispensing_status']    = 'PENDING';
			if ($schema['payment_status'])        $data['payment_status']       = 'PENDING';
			if ($schema['route'])                 $data['route']                = isset($entry['route'])               ? strip_tags(trim($entry['route']))                : null;
			if ($schema['drug_form'])             $data['drug_form']            = isset($entry['drug_form'])           ? strip_tags(trim($entry['drug_form']))            : null;
			if ($schema['diagnosis_code'])        $data['diagnosis_code']       = isset($entry['diagnosis_code'])      ? strip_tags(trim($entry['diagnosis_code']))       : null;
			if ($schema['diagnosis_description']) $data['diagnosis_description']= isset($entry['diagnosis_description'])? strip_tags(trim($entry['diagnosis_description'])): null;
			/* Phase 4 fields */
			if ($schema['unit'])                  $data['unit']                 = isset($entry['unit'])                ? strip_tags(trim($entry['unit']))                 : null;
			if ($schema['freq_code'])             $data['freq_code']            = isset($entry['freq_code'])           ? strip_tags(trim($entry['freq_code']))            : null;
			if ($schema['is_nhis_covered'])       $data['is_nhis_covered']      = isset($entry['is_nhis_covered'])     ? (int)$entry['is_nhis_covered']                  : 0;
			if ($schema['is_prn'])                $data['is_prn']               = isset($entry['is_prn'])              ? (int)$entry['is_prn']                           : 0;
			if ($schema['is_urgent'])             $data['is_urgent']            = isset($entry['is_urgent'])           ? (int)$entry['is_urgent']                        : 0;
			/* Structured strength fields */
			if ($schema['prescribed_dose_value']) $data['prescribed_dose_value'] = isset($entry['dosage']) ? (float)$entry['dosage'] : null;
			if ($schema['prescribed_dose_unit'])  $data['prescribed_dose_unit']  = isset($entry['unit']) ? strip_tags(trim($entry['unit'])) : null;
			if ($schema['strength_per_unit_value'])$data['strength_per_unit_value']= isset($entry['strength_per_unit_value']) ? (float)$entry['strength_per_unit_value'] : null;
			if ($schema['strength_per_unit_unit'])$data['strength_per_unit_unit']= isset($entry['strength_per_unit_unit']) ? strip_tags(trim($entry['strength_per_unit_unit'])) : null;
			if ($schema['required_units'])        $data['required_units']       = isset($entry['required_units']) ? (float)$entry['required_units'] : null;
			if ($schema['total_active_mass_value'])$data['total_active_mass_value']= isset($entry['total_active_mass_value']) ? (float)$entry['total_active_mass_value'] : null;
			if ($schema['total_active_mass_unit'])$data['total_active_mass_unit']= isset($entry['total_active_mass_unit']) ? strip_tags(trim($entry['total_active_mass_unit'])) : null;
			/* prescribed_qty population under feature flag */
			$useDecimalPrescriptions = $this->config->item('ENABLE_DECIMAL_PRESCRIPTIONS') || $this->config->item('ENABLE_DECIMAL_PRESCRIBED_QTY');
			if ($schema['prescribed_qty']) {
				if ($useDecimalPrescriptions) {
					$data['prescribed_qty'] = isset($entry['required_units']) && $entry['required_units'] > 0
						? (float)$entry['required_units']
						: (isset($entry['total_qty']) ? (float)$entry['total_qty'] : 1.0);
				} else {
					$data['prescribed_qty'] = isset($entry['total_qty']) ? (int)round((float)$entry['total_qty']) : 1;
				}
			}

			if ($this->db->insert('iop_medication', $data)) {
				$new_id = $this->db->insert_id();
				$saved++;
				$saved_drugs[] = array(
					'iop_med_id'      => $new_id,
					'drug_id'         => $drug_id,
					'qty'             => (float)$data['total_qty'],
					'drug_name'       => $medicine_text,
					'is_nhis_covered' => isset($data['is_nhis_covered']) ? (int)$data['is_nhis_covered'] : 0,
				);
			}
		}

		$this->db->trans_complete();

		if ($this->db->trans_status() === false) {
			log_message('error', 'save_medication_batch TRANSACTION FAILED: iop_id=' . $iop_id . ' patient=' . $patNo);
			echo json_encode(array(
				'status'  => 'error',
				'success' => false,
				'saved'   => 0,
				'blocked' => 0,
				'warnings'=> array(),
				'message' => 'Database error — no medications were saved. Please try again.',
			));
			return;
		}

		// ── Post-commit: Phase 4 prescription engine + shared billing ───────────
		$this->load->model('app/Prescription_engine_model');
		$this->Prescription_engine_model->ensure_phase4_schema();

		// Load Unified Billing Automation
		$this->load->library('billing_automation');

		foreach ($saved_drugs as $sd) {
			/* Generate and stamp prescription number (Phase 4) */
			$rx_no = $this->Prescription_engine_model->generate_prescription_no();
			if ($this->db->field_exists('prescription_no', 'iop_medication')) {
				$this->Prescription_engine_model->stamp_prescription_no($sd['iop_med_id'], $rx_no);
			}

			/* Route NHIS drugs to nhis_claim_queue */
			if ($sd['is_nhis_covered']) {
				$this->Prescription_engine_model->push_to_nhis_queue(array(
					'iop_med_id'      => $sd['iop_med_id'],
					'prescription_no' => $rx_no,
					'patient_no'      => $patNo,
					'iop_id'          => $iop_id,
					'drug_id'         => $sd['drug_id'],
					'drug_name'       => $sd['drug_name'],
					'quantity'        => $sd['qty'],
					'unit_price'      => 0,
				));
			}

			/* Audit: PRESCRIBED */
			$this->Prescription_engine_model->audit_log('PRESCRIBED', array(
				'iop_med_id'      => $sd['iop_med_id'],
				'prescription_no' => $rx_no,
				'iop_id'          => $iop_id,
				'patient_no'      => $patNo,
				'new_status'      => 'PENDING',
				'notes'           => 'Batch save via OPD (' . $payer . ')',
				'user_id'         => $doctor_id,
			));

			/* Existing billing helper (price lookup, smart billing) */
			$this->_post_save_billing_triggers(
				$sd['iop_med_id'], $sd['drug_id'], $sd['drug_name'], $sd['qty'],
				$iop_id, $patNo, $doctor_id, $payer, $cdsLoaded, 'OPD_BATCH'
			);

		}

		// Build flash for page redirect
		$flashBody  = "{$saved} medication(s) successfully added!";
		$flashClass = empty($warnings) ? 'alert-success' : 'alert-warning';
		$flashIcon  = empty($warnings) ? 'fa-check'      : 'fa-exclamation-triangle';
		if (!empty($warnings)) {
			$flashBody .= '<br><strong>Alerts:</strong> ' . implode('; ', array_map('htmlspecialchars', $warnings));
		}
		$this->session->set_flashdata('message', "<div class='{$flashClass} alert alert-dismissable'><i class='fa {$flashIcon}'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$flashBody}</div>");

		echo json_encode(array(
			'status'   => 'success',
			'success'  => true,
			'saved'    => $saved,
			'blocked'  => 0,
			'warnings' => $warnings,
			'message'  => "{$saved} medication(s) saved successfully",
			'redirect' => base_url() . 'app/opd/medication/' . url_safe_id($iop_id) . '/' . $patient_no,
		));
	}

	/**
	 * Batch save sonography requests - AJAX endpoint for multi-entry system
	 *  - Now integrated with Unified Billing System (auto-creates billing entries)
	 */
	public function save_sonography_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
			return;
		}

		$_db_debug_orig = $this->db->db_debug;
		$this->db->db_debug = false;

		try {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$entries = json_decode($this->input->post('entries'), true);

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'OPD', 'save_sonography_batch', true)) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'Access denied'));
				return;
			}

			if (empty($entries) || !is_array($entries)) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'No items to save'));
				return;
			}

			$iop_id = url_decode_id($opd_no);
			$saved = 0;
			$ignored = 0;
			$doctor_id = $this->session->userdata('user_id');

			$this->load->model('app/laboratory_model');
			$this->load->model('app/Ghana_test_catalog_model');
			$this->load->model('app/billing_model');
			$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
			$this->load->model('app/Nhis_validation_model', 'nhis_validation');
			$sono_cat_id = (int)$this->laboratory_model->get_sonography_category_id();
			$batchPayerType = $this->billing_model->determine_payer_type((string)$patient_no);
			$batchNhisFlag  = ($batchPayerType === 'NHIS') ? 1 : 0;
			$hasPriorityCol    = $this->laboratory_model->column_exists('iop_laboratory', 'priority');
			$hasRequestedByCol = $this->laboratory_model->column_exists('iop_laboratory', 'requested_by');
			$hasClinicalNotesCol = $this->laboratory_model->column_exists('iop_laboratory', 'clinical_notes');
			$hasPayerTypeCol   = $this->laboratory_model->column_exists('iop_laboratory', 'payer_type');
			$hasNhisFlagCol    = $this->laboratory_model->column_exists('iop_laboratory', 'nhis_flag');
			$normalize_text = function($text) {
				$text = preg_replace('/\s+/', ' ', (string)$text);
				return strtolower(trim((string)$text));
			};
			$pending_ids = array();
			$pending_texts = array();
			$this->db->select('laboratory_id, laboratory_text');
			$this->db->where(array('iop_id' => $iop_id, 'category_id' => (int)$sono_cat_id, 'InActive' => 0));
			$this->db->where("(result = '' OR result IS NULL)", null, false);
			$this->db->limit(200);
			$pending_rows = $this->db->get('iop_laboratory')->result_array();
			foreach ($pending_rows as $pr) {
				$pid = isset($pr['laboratory_id']) ? (int)$pr['laboratory_id'] : 0;
				if ($pid > 0) {
					$pending_ids[(string)$pid] = true;
				}
				$pt = isset($pr['laboratory_text']) ? $normalize_text($pr['laboratory_text']) : '';
				if ($pt !== '') {
					$pending_texts[$pt] = true;
				}
			}

			foreach ($entries as $entry) {
				$item_id = isset($entry['sonography_item_id']) ? (int)$entry['sonography_item_id'] : 0;
				if (!$item_id) continue;

				$remarks = isset($entry['remarks']) ? trim($entry['remarks']) : '';
				$priority = isset($entry['priority']) ? trim($entry['priority']) : 'Normal';
				$item_name = isset($entry['sonography_item_text']) ? trim($entry['sonography_item_text']) : '';

				// Resolve from GHS sonography catalog first
				$ghs_sono = $this->Ghana_test_catalog_model->get_sono_test_for_order($item_id);
				$billing_id = $item_id;

				if ($ghs_sono) {
					if ($item_name === '') $item_name = $ghs_sono->test_name;
					if ($ghs_sono->particular_id) {
						$billing_id = (int)$ghs_sono->particular_id;
					} else {
						$bp_match = $this->db->query(
							"SELECT particular_id FROM bill_particular WHERE LOWER(TRIM(particular_name)) = LOWER(TRIM(?)) AND InActive = 0 LIMIT 1",
							array($ghs_sono->test_name)
						);
						if ($bp_match && $bp_match->num_rows() > 0) {
							$billing_id = (int)$bp_match->row()->particular_id;
							$this->db->where('test_id', $item_id)->update('ghs_sonography_tests', ['particular_id' => $billing_id]);
						} else {
							$billing_id = $item_id;
						}
					}
				} elseif ($item_name === '') {
					// Fallback: treat item_id as bill_particular.particular_id (legacy)
					$bp_q = $this->db->query("SELECT particular_name FROM bill_particular WHERE particular_id = ? LIMIT 1", array($item_id));
					$item_name = ($bp_q && $bp_q->num_rows() > 0) ? $bp_q->row()->particular_name : '';
					$billing_id = $item_id;
				}

				$nowDate = date('Y-m-d');
				$nowDateTime = date('Y-m-d H:i:s');
				$data = array(
					'iop_id'          => $iop_id,
					'laboratory_id'   => $billing_id,
					'category_id'     => $sono_cat_id,
					'laboratory_text' => $item_name,
					'findings'        => '',
					'result'          => '',
					'doctor'          => $doctor_id,
					'dDate'           => $nowDate,
					'dDateTime'       => $nowDateTime,
					'InActive'        => 0
				);

				if ($hasClinicalNotesCol && $remarks !== '') { $data['clinical_notes'] = $remarks; }
				if ($hasPriorityCol)    { $data['priority']    = $priority; }
				if ($hasRequestedByCol) { $data['requested_by'] = $doctor_id; }
				if ($hasPayerTypeCol)   { $data['payer_type']   = $batchPayerType; }
				if ($hasNhisFlagCol)    { $data['nhis_flag']    = $batchNhisFlag; }

				$billing_id_i = (int)$billing_id;
				$item_norm = $item_name !== '' ? $normalize_text($item_name) : '';
				if ($billing_id_i > 0 && isset($pending_ids[(string)$billing_id_i])) {
					$ignored++;
					continue;
				}
				if ($item_norm !== '' && isset($pending_texts[$item_norm])) {
					$ignored++;
					continue;
				}

				if ($this->db->insert('iop_laboratory', $data)) {
					$io_lab_id = (int)$this->db->insert_id();
					$saved++;
					if ($billing_id_i > 0) {
						$pending_ids[(string)$billing_id_i] = true;
					}
					if ($item_norm !== '') {
						$pending_texts[$item_norm] = true;
					}

					// Create sonography request metadata (needed for sonographer work queue + display)
					try {
						$sonoUrgency = (strtolower($priority) === 'urgent') ? 'URGENT' : 'ROUTINE';
						$this->laboratory_model->upsert_sonography_request_meta(
							$io_lab_id, $item_id, $remarks, 'OPD', $patient_no, $doctor_id, $doctor_id, $sonoUrgency
						);
					} catch (\Throwable $e) { log_message('error', 'Sono batch meta error: ' . $e->getMessage()); }

					// Create workflow status
					try {
						$this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $doctor_id);
					} catch (\Throwable $e) { log_message('error', 'Sono batch workflow error: ' . $e->getMessage()); }

					// Auto-create billing entry
					try {
						$this->load->library('billing_automation');
						$this->billing_automation->on_sonography_ordered(
							$patient_no, $iop_id, 'OPD', $billing_id, $item_name, $doctor_id
						);
					} catch (\Throwable $e) { log_message('error', 'Sono batch billing error: ' . $e->getMessage()); }

					try {
						$validation = $this->nhis_validation->validate_service('SONOGRAPHY', (int)$io_lab_id);
						log_message(
							'debug',
							'[NHIS_VALIDATION] module=SONOGRAPHY id=' . (int)$io_lab_id
							. ' valid=' . (!empty($validation['valid']) ? '1' : '0')
							. ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
						);
					} catch (\Throwable $e) {
					}

					// Diagnostic financial state/drift: SONOGRAPHY is resolved via charge identity only.
					try {
						$charge_id = 0;
						if ($this->_opd_table_exists('iop_sonography_charge', 'iop_sonography_charge_schema')) {
							$this->db->select('charge_id');
							$this->db->from('iop_sonography_charge');
							$this->db->where('InActive', 0);
							$this->db->where('io_lab_id', (int)$io_lab_id);
							$this->db->order_by('charge_id', 'DESC');
							$this->db->limit(1);
							$ch = $this->db->get()->row();
							if ($ch && isset($ch->charge_id)) {
								$charge_id = (int)$ch->charge_id;
							}
						}
						if ($charge_id > 0) {
							$det = $this->diag_fin_state->get_financial_state_detail('SONOGRAPHY', $charge_id);
							$state = isset($det['state']) ? (string)$det['state'] : 'REQUESTED';
							$ref = isset($det['resolved_item_ref']) ? (string)$det['resolved_item_ref'] : '';
							log_message('debug', '[DIAG_STATE] module=SONOGRAPHY id=' . $charge_id . ' resolved_ref=' . $ref . ' state=' . $state);
							$dr = $this->diag_fin_state->detect_drift('SONOGRAPHY', $charge_id, true);
							if (isset($dr['drift_types']) && is_array($dr['drift_types']) && !empty($dr['drift_types'])) {
								$dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : '';
								$sev = isset($dr['severity']) ? (string)$dr['severity'] : 'NONE';
								log_message('debug', '[DIAG_DRIFT] module=SONOGRAPHY id=' . $charge_id . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
								if ($sev === 'CRITICAL') {
									log_message('error', '[DIAG_ALERT] module=SONOGRAPHY id=' . $charge_id . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
								}
								if (in_array('POLICY_VIOLATION', $dr['drift_types'], true) || in_array('UNDERPAID_RELEASE', $dr['drift_types'], true)) {
									$pr = isset($dr['policy_reason']) ? (string)$dr['policy_reason'] : '';
									$msg = in_array('UNDERPAID_RELEASE', $dr['drift_types'], true) ? 'Payment below required threshold' : 'Policy denied but flow continued';
									log_message('error', '[POLICY_WARNING] module=SONOGRAPHY id=' . $charge_id . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev . ' policy_reason=' . $pr . ' msg=' . $msg);
								}
							}
						}
					} catch (\Throwable $e) {
					}
				} else {
					$db_err = $this->db->error();
					log_message('error', 'Sono batch insert FAILED for item_id=' . $item_id . ': ' . json_encode($db_err) . ' | data=' . json_encode($data));
				}
			}

			$msg_extra = ($ignored > 0) ? (' ' . $ignored . ' duplicate request(s) ignored.') : '';
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$saved} sonography request(s) successfully added!{$msg_extra}</div>");

			$this->db->db_debug = $_db_debug_orig;

			echo json_encode(array(
				'success' => true,
				'saved' => $saved,
				'ignored' => $ignored,
				'message' => "{$saved} sonography request(s) saved successfully" . ($ignored > 0 ? (" ({$ignored} duplicate ignored)") : ''),
				'redirect' => base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));

		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'save_sonography_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	/**
	 * Batch save radiology requests - AJAX endpoint for multi-entry system
	 *  - Inserts into iop_laboratory (so tests display in the laboratory view)
	 *  - Also creates radiology_orders record (for radiology technician workflow)
	 *  - Integrated with Unified Billing System
	 */
	public function save_radiology_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
			return;
		}

		$_db_debug_orig = $this->db->db_debug;
		$this->db->db_debug = false;

		try {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$entries = json_decode($this->input->post('entries'), true);

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'OPD', 'save_radiology_batch', true)) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'Access denied'));
				return;
			}

			if (empty($entries) || !is_array($entries)) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'No items to save'));
				return;
			}

			$iop_id = url_decode_id($opd_no);
			$saved = 0;
			$ignored = 0;
			$doctor_id = $this->session->userdata('user_id');

			$this->load->model('app/radiology_model');
			$this->load->model('app/laboratory_model');
			$this->load->model('app/billing_model');
			$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
			$this->load->model('app/Nhis_validation_model', 'nhis_validation');

			$radiology_cat_id = $this->laboratory_model->get_radiology_category_id();
			$batchPayerType = $this->billing_model->determine_payer_type((string)$patient_no);
			$batchNhisFlag  = ($batchPayerType === 'NHIS') ? 1 : 0;
			$hasClinicalNotesCol = $this->laboratory_model->column_exists('iop_laboratory', 'clinical_notes');
			$hasPriorityCol    = $this->laboratory_model->column_exists('iop_laboratory', 'priority');
			$hasRequestedByCol = $this->laboratory_model->column_exists('iop_laboratory', 'requested_by');
			$hasPayerTypeCol   = $this->laboratory_model->column_exists('iop_laboratory', 'payer_type');
			$hasNhisFlagCol    = $this->laboratory_model->column_exists('iop_laboratory', 'nhis_flag');

			foreach ($entries as $entry) {
				$test_id = isset($entry['radiology_test_id']) ? (int)$entry['radiology_test_id'] : 0;
				if (!$test_id) continue;

				$clinical_notes = isset($entry['clinical_notes']) ? trim($entry['clinical_notes']) : '';
				$priority = isset($entry['priority']) ? trim($entry['priority']) : 'normal';
				$test_name = isset($entry['radiology_test_text']) ? trim($entry['radiology_test_text']) : '';

				// Resolve test name from radiology_test_master if not sent from JS
				if ($test_name === '') {
					$testRow = $this->radiology_model->get_test($test_id);
					if ($testRow && isset($testRow->test_name)) {
						$test_name = trim($testRow->test_name);
					}
				}

				// 1. Insert into iop_laboratory (canonical display record)
				$nowDate = date('Y-m-d');
				$nowDateTime = date('Y-m-d H:i:s');
				$data = array(
					'iop_id'          => $iop_id,
					'laboratory_id'   => $test_id,
					'category_id'     => $radiology_cat_id,
					'laboratory_text' => $test_name,
					'findings'        => '',
					'result'          => '',
					'doctor'          => $doctor_id,
					'dDate'           => $nowDate,
					'dDateTime'       => $nowDateTime,
					'InActive'        => 0
				);

				if ($hasClinicalNotesCol && $clinical_notes !== '') { $data['clinical_notes'] = $clinical_notes; }
				if ($hasPriorityCol)    { $data['priority']    = $priority; }
				if ($hasRequestedByCol) { $data['requested_by'] = $doctor_id; }
				if ($hasPayerTypeCol)   { $data['payer_type']   = $batchPayerType; }
				if ($hasNhisFlagCol)    { $data['nhis_flag']    = $batchNhisFlag; }

				$this->db->where(array(
					'iop_id' => $iop_id,
					'category_id' => (int)$radiology_cat_id,
					'laboratory_id' => (int)$test_id,
					'InActive' => 0
				));
				$this->db->where("(result = '' OR result IS NULL)", null, false);
				$this->db->limit(1);
				$dup = $this->db->get('iop_laboratory')->row();
				if ($dup) {
					$ignored++;
					continue;
				}

				if ($this->db->insert('iop_laboratory', $data)) {
					$io_lab_id = (int)$this->db->insert_id();
					$saved++;

					// 2. Create radiology_orders record (for radiology technician workflow)
					$order_data = array(
						'iop_id' => $iop_id,
						'patient_no' => $patient_no,
						'test_id' => $test_id,
						'priority' => $priority,
						'clinical_notes' => $clinical_notes,
						'ordered_by' => $doctor_id
					);
					$order_id = 0;
					try {
						$order_id = (int)$this->radiology_model->create_order($order_data);
					} catch (\Throwable $e) {
						log_message('error', 'Radiology batch create_order error: ' . $e->getMessage());
					}
					if ($order_id > 0) {
						try {
							$det = $this->diag_fin_state->get_financial_state_detail('RADIOLOGY', $order_id);
							$state = isset($det['state']) ? (string)$det['state'] : 'REQUESTED';
							$ref = isset($det['resolved_item_ref']) ? (string)$det['resolved_item_ref'] : '';
							log_message('debug', '[DIAG_STATE] module=RADIOLOGY id=' . $order_id . ' resolved_ref=' . $ref . ' state=' . $state);
							$dr = $this->diag_fin_state->detect_drift('RADIOLOGY', $order_id, true);
							if (isset($dr['drift_types']) && is_array($dr['drift_types']) && !empty($dr['drift_types'])) {
								$dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : '';
								$sev = isset($dr['severity']) ? (string)$dr['severity'] : 'NONE';
								log_message('debug', '[DIAG_DRIFT] module=RADIOLOGY id=' . $order_id . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
								if ($sev === 'CRITICAL') {
									log_message('error', '[DIAG_ALERT] module=RADIOLOGY id=' . $order_id . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
								}
								if (in_array('POLICY_VIOLATION', $dr['drift_types'], true) || in_array('UNDERPAID_RELEASE', $dr['drift_types'], true)) {
									$pr = isset($dr['policy_reason']) ? (string)$dr['policy_reason'] : '';
									$msg = in_array('UNDERPAID_RELEASE', $dr['drift_types'], true) ? 'Payment below required threshold' : 'Policy denied but flow continued';
									log_message('error', '[POLICY_WARNING] module=RADIOLOGY id=' . $order_id . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev . ' policy_reason=' . $pr . ' msg=' . $msg);
								}
							}
						} catch (\Throwable $e) {
						}
					}

					// 3. Create workflow status
					try {
						$this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $doctor_id);
					} catch (\Throwable $e) { log_message('error', 'Radiology batch workflow error: ' . $e->getMessage()); }

					// 4. Auto-create billing entry
					try {
						$this->load->library('billing_automation');
						$bill_res = $this->billing_automation->on_radiology_ordered(
							$patient_no, $iop_id, 'OPD', $test_id, $test_name, $doctor_id
						);
						if ($order_id > 0 && is_array($bill_res) && isset($bill_res['success']) && $bill_res['success']) {
							if ($this->_opd_table_exists('radiology_orders', 'radiology_orders_schema')) {
								$upd = array();
								if ($this->db->field_exists('billed', 'radiology_orders')) { $upd['billed'] = 1; }
								if ($this->db->field_exists('invoice_no', 'radiology_orders')) { $upd['invoice_no'] = null; }
								if (!empty($upd)) {
								$this->db->where(array('id' => $order_id, 'InActive' => 0));
								$this->db->update('radiology_orders', $upd);
								}
							}
						}
						if ($order_id > 0 && is_array($bill_res) && isset($bill_res['success']) && !$bill_res['success']) {
							log_message('error', 'Radiology billing_automation failed for order_id=' . $order_id . ' test_id=' . $test_id . ' iop_id=' . $iop_id . ' patient_no=' . $patient_no . ' err=' . (isset($bill_res['error']) ? (string)$bill_res['error'] : ''));
							if ($this->_opd_table_exists('radiology_orders', 'radiology_orders_schema')) {
								$upd = array();
								if ($this->db->field_exists('billed', 'radiology_orders')) { $upd['billed'] = 0; }
								if ($this->db->field_exists('invoice_no', 'radiology_orders')) { $upd['invoice_no'] = 'BILLING_FAILED'; }
								if (!empty($upd)) {
								$this->db->where(array('id' => $order_id, 'InActive' => 0));
								$this->db->update('radiology_orders', $upd);
								}
							}
						}
					} catch (\Throwable $e) {
						log_message('error', 'Radiology batch billing error: ' . $e->getMessage());
						if ($order_id > 0 && $this->_opd_table_exists('radiology_orders', 'radiology_orders_schema')) {
							$upd = array();
							if ($this->db->field_exists('billed', 'radiology_orders')) { $upd['billed'] = 0; }
							if ($this->db->field_exists('invoice_no', 'radiology_orders')) { $upd['invoice_no'] = 'BILLING_FAILED'; }
							if (!empty($upd)) {
							$this->db->where(array('id' => $order_id, 'InActive' => 0));
							$this->db->update('radiology_orders', $upd);
							}
						}
					}

					if ($order_id > 0) {
						try {
							$validation = $this->nhis_validation->validate_service('RADIOLOGY', (int)$order_id);
							log_message(
								'debug',
								'[NHIS_VALIDATION] module=RADIOLOGY id=' . (int)$order_id
								. ' test_id=' . (int)$test_id
								. ' valid=' . (!empty($validation['valid']) ? '1' : '0')
								. ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
							);
						} catch (\Throwable $e) {
						}
					}

					log_message('info', 'RADIOLOGY_REQUEST_CREATED iop_id=' . $iop_id . ' io_lab_id=' . $io_lab_id . ' test_id=' . $test_id . ' test=' . $test_name);
				} else {
					$db_err = $this->db->error();
					log_message('error', 'Radiology batch iop_laboratory insert FAILED for test_id=' . $test_id . ': ' . json_encode($db_err) . ' | data=' . json_encode($data));
				}
			}

			$msg_extra = ($ignored > 0) ? (' ' . $ignored . ' duplicate request(s) ignored.') : '';
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$saved} radiology request(s) successfully added!{$msg_extra}</div>");

			$this->db->db_debug = $_db_debug_orig;

			echo json_encode(array(
				'success' => true,
				'saved' => $saved,
				'ignored' => $ignored,
				'message' => "{$saved} radiology request(s) saved successfully" . ($ignored > 0 ? (" ({$ignored} duplicate ignored)") : ''),
				'redirect' => base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));

		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'save_radiology_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	public function patientHistory()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];
		
		// Auto-populate from patient-level persistent history (for new visits)
		$this->clinical_history_model->populate_from_patient_history($iop_no, $patient_no);
		
		// Re-fetch visit data after population
		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		
		// Get comprehensive clinical history summary
		$this->data['clinical_summary'] = $this->clinical_history_model->get_comprehensive_summary($patient_no);
		
		// Get patient-level persistent history
		$this->data['patient_history'] = $this->clinical_history_model->get_patient_history($patient_no);
		
		// Get ODQ and Examination structures
		$this->data['odq_structure'] = $this->clinical_history_model->get_odq_structure();
		$this->data['exam_structure'] = $this->clinical_history_model->get_examination_structure();
		
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view("app/opd/patientHistory", $this->data);
	}

	public function delete_vital()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'delete_vital')) {
			return;
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Parameters successfully Deleted!</div>");

		$this->db->query("UPDATE iop_vital_parameters SET InActive = 1 WHERE vital_id = ?", array((int)$id));

		redirect(base_url() . 'app/opd/vitalSign/' . url_safe_id($iop_no) . '/' . $patient_no);
	}

	public function vitalSign()
	{
		// Check for POST data - handle both btnSave button and general POST
		$isPost = $this->input->method() === 'post';
		$hasOpdNo = $this->input->post('opd_no');
		
		if ($isPost && $hasOpdNo) {
			// CRITICAL: Decode URL-safe ID back to original format (OP-000002 -> OP 000002)
			$iop_id = url_decode_id($this->input->post('opd_no'));
			$patient_no = $this->input->post('patient_no');
			if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'save_vitalSign')) {
				return;
			}
			
			$this->data = array(
				'iop_id'		=>		$iop_id,
				'dDate'			=>		$this->input->post('dDate'),
				'dDateTime'		=>		$this->input->post('dDate') . " " . $this->input->post('cTime'),
				'pulse_rate'	=>		$this->input->post('pulse_rate'),
				'temperature'	=>		$this->input->post('temperature'),
				'height'		=>		$this->input->post('height'),
				'bp'			=>		$this->input->post('bp'),
				'respiration'	=>		$this->input->post('respiration'),
				'weight'		=>		$this->input->post('weight'),
				'InActive'		=>		0
			);
			$this->db->insert('iop_vital_parameters', $this->data);

			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Parameters successfully Added!</div>");

			//redirect
			redirect(base_url() . 'app/opd/vitalSign/' . url_safe_id($iop_id) . '/' . $patient_no);
		} else {
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);
			
			// Guard: Validate required URL parameters (only for GET requests)
			if (empty($iop_no) || empty($patient_no)) {
				$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-exclamation-triangle'></i> Invalid URL - missing visit or patient ID.</div>");
				redirect(base_url() . 'app/opd');
				return;
			}

			$v = $this->_resolve_opd_visit($iop_no, $patient_no);
			if (!$v) {
				$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
				redirect(base_url() . 'app/opd'); return;
			}
			$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
			$this->data['getOPDPatient'] = $v['getOPDPatient'];
			$this->data['patientInfo']   = $v['patientInfo'];
			
			$this->data['getVital'] = $this->opd_model->getVital($iop_no);
			$this->data['message'] = $this->session->flashdata('message');

			$this->load->view("app/opd/vitalSign", $this->data);
		}
	}

	public function complain()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];
		$this->data['message']       = $this->session->flashdata('message');

		$this->data['ComplainList']        = $this->opd_model->ComplainList();
		$this->data['patientComplain']     = $this->opd_model->patientComplain($iop_no);
		$this->data['doctorTopComplaints'] = $this->opd_model->getDoctorTopComplaints(
			(string)$this->session->userdata('user_id')
		);

		$this->load->view("app/opd/complain", $this->data);
	}

	public function medication()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['medicineCategory'] = $this->opd_model->medicineCategory();
		$this->data['patientMedication'] = $this->opd_model->patientMedication($iop_no);

		// For Multi-Entry Medication Modal
		$this->data['drug_categories'] = $this->opd_model->medicineCategory();

		$this->load->view("app/opd/medication", $this->data);
	}


	public function save_complain()
	{
		$this->form_validation->set_rules("complain", "Complain", "trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>", "</div>");

		$opd_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'OPD', 'save_complain')) {
			return;
		}

		if ($this->form_validation->run()) {

			$this->opd_model->save_complain();

			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Complain successfully Added!</div>");

			//redirect
			redirect(base_url() . 'app/opd/complain/' . url_safe_id($opd_no) . '/' . $patient_no);
		} else {
			redirect(base_url() . 'app/opd/complain/' . url_safe_id($opd_no) . '/' . $patient_no);
		}
	}

	public function save_vital()
	{
		// CRITICAL: Decode URL-safe ID back to original format (OP-000002 -> OP 000002)
		$iop_no = url_decode_id($this->input->post('opd_no'));
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'save_vital')) {
			return;
		}

		$this->data = array(
			'pulse_rate'		=>	$this->input->post('pulse_rate'),
			'temperature'		=>	$this->input->post('temperature'),
			'height'			=>	$this->input->post('height'),
			'bp'				=>	$this->input->post('bp'),
			'respiration'		=>	$this->input->post('respiration'),
			'weight'			=>	$this->input->post('weight')
		);
		$this->db->where("IO_ID", $iop_no);
		$this->db->update("patient_details_iop", $this->data);

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Sign successfully Updated!</div>");

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);

		//redirect
		redirect(base_url() . 'app/opd/vitalSign/' . url_safe_id($iop_no) . '/' . $patient_no);
	}

	public function save_patientHistory()
	{
		// CRITICAL: Decode URL-safe ID back to original format (OP-000002 -> OP 000002)
		$iop_no = url_decode_id($this->input->post('opd_no'));
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'save_patientHistory')) {
			return;
		}

		$user_id = $this->session->userdata('user_id');

		// Comprehensive clinical history data aligned with GHS/NHIS standards
		$history_data = array(
			// Core history fields
			'allergies'					=>	$this->input->post('allergies'),
			'warnings'					=>	$this->input->post('warnings'),
			'social_history'			=>	$this->input->post('social_history'),
			'family_history'			=>	$this->input->post('family_history'),
			'personal_history'			=>	$this->input->post('personal_history'),
			'past_medical_history'		=>	$this->input->post('past_medical_history'),
			// Enhanced history fields
			'history_presenting_complaint'	=>	$this->input->post('history_presenting_complaint'),
			'past_surgical_history'		=>	$this->input->post('past_surgical_history'),
			'drug_history'				=>	$this->input->post('drug_history'),
			'gynae_obstetric_history'	=>	$this->input->post('gynae_obstetric_history'),
			'on_direct_questioning'		=>	$this->input->post('on_direct_questioning'),
			// Examination fields
			'examination_findings'		=>	$this->input->post('examination_findings'),
			'examination_general'		=>	$this->input->post('examination_general'),
			'examination_cardiovascular'=>	$this->input->post('examination_cardiovascular'),
			'examination_respiratory'	=>	$this->input->post('examination_respiratory'),
			'examination_gastrointestinal'=>$this->input->post('examination_gastrointestinal'),
			'examination_neurological'	=>	$this->input->post('examination_neurological'),
			'examination_musculoskeletal'=>	$this->input->post('examination_musculoskeletal'),
			'examination_other'			=>	$this->input->post('examination_other')
		);

		// Save using clinical history model (handles both visit and patient-level persistence)
		$this->clinical_history_model->save_visit_history($iop_no, $history_data, $user_id);

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Clinical History successfully Updated!</div>");

		//redirect
		redirect(base_url() . 'app/opd/patientHistory/' . url_safe_id($iop_no) . '/' . $patient_no);
	}

	/**
	 * Shared prescription validation kernel — called by save_medication() and save_medication_batch().
	 *
	 * @param string $iop_id      Decoded visit ID (NOT URL-safe)
	 * @param string $patient_no  Patient number
	 * @param array  $medications Array of medication entries, each with keys:
	 *                            drug_id, dosage, frequency, days, diagnosis_code
	 * @param bool   $models_loaded  Whether billing + CDS models are already loaded
	 *
	 * @return array {
	 *   'ok'              => bool,
	 *   'status'          => 'blocked'|'nhis_block'|'error'|'ok',
	 *   'message'         => string,
	 *   'payer'           => string,
	 *   'blocked_drugs'   => array,   // entries with BLOCKED CDS severity
	 *   'cds_warnings'    => array,   // non-blocking CDS warning strings
	 *   'nhis_warnings'   => array,   // NHIS formulary non-coverage strings
	 *   'schema'          => array,   // column-existence map (hoisted)
	 *   'cds_loaded'      => bool,
	 * }
	 */
	private function _run_prescription_validation($iop_id, $patient_no, array $medications)
	{
		$result = array(
			'ok'            => false,
			'status'        => 'error',
			'message'       => '',
			'payer'         => 'CASH',
			'blocked_drugs' => array(),
			'cds_warnings'  => array(),
			'nhis_warnings' => array(),
			'schema'        => array(),
			'cds_loaded'    => false,
		);

		// ── Validate decoded iop_id ───────────────────────────────────────────
		if (trim((string)$iop_id) === '') {
			$result['message'] = 'Invalid visit ID.';
			return $result;
		}

		// ── Load models (idempotent via CI loader) ────────────────────────────
		$this->load->model('app/billing_model');
		try {
			$this->load->model('app/Clinical_decision_support_model');
			$this->Clinical_decision_support_model->ensure_phase3_enhancements();
			$result['cds_loaded'] = true;
		} catch (Exception $e) {
			log_message('error', 'CDS model load (_run_prescription_validation): ' . $e->getMessage());
		}

		// ── NHIS payment gate — checked ONCE for the entire batch ─────────────
		$gate = $this->billing_model->check_nhis_payment_gate($patient_no, $iop_id, 'PHARMACY');
		if (!$gate['allowed']) {
			$result['status']  = 'nhis_block';
			$result['message'] = 'Payment Required: ' . $gate['reason'];
			return $result;
		}
		$result['payer'] = $this->billing_model->determine_payer_type($patient_no);

		// ── Hoist column-existence checks (once, not per drug) ────────────────
		$result['schema'] = array(
			'frequency'            => $this->db->field_exists('frequency',            'iop_medication'),
			'prescribed_by'        => $this->db->field_exists('prescribed_by',        'iop_medication'),
			'dispensing_status'    => $this->db->field_exists('dispensing_status',    'iop_medication'),
			'payment_status'       => $this->db->field_exists('payment_status',       'iop_medication'),
			'diagnosis_code'       => $this->db->field_exists('diagnosis_code',       'iop_medication'),
			'diagnosis_description'=> $this->db->field_exists('diagnosis_description','iop_medication'),
			'route'                => $this->db->field_exists('route',                'iop_medication'),
			'drug_form'            => $this->db->field_exists('drug_form',            'iop_medication'),
			/* Phase 4 columns */
			'unit'                 => $this->db->field_exists('unit',                 'iop_medication'),
			'freq_code'            => $this->db->field_exists('freq_code',            'iop_medication'),
			'is_nhis_covered'      => $this->db->field_exists('is_nhis_covered',      'iop_medication'),
			'is_prn'               => $this->db->field_exists('is_prn',               'iop_medication'),
			'is_urgent'            => $this->db->field_exists('is_urgent',            'iop_medication'),
			'prescription_no'      => $this->db->field_exists('prescription_no',      'iop_medication'),
		);

		// ── Per-drug: CDS safety + NHIS formulary ────────────────────────────
		foreach ($medications as $idx => $med) {
			$drug_id = isset($med['drug_id']) ? (int)$med['drug_id'] : 0;
			if ($drug_id <= 0) continue;

			// NHIS formulary
			if ($result['payer'] === 'NHIS') {
				try {
					$cov = $this->billing_model->check_drug_nhis_coverage($drug_id);
					if (!empty($cov['found']) && empty($cov['is_nhis_covered'])) {
						$name = isset($cov['drug_name']) ? htmlspecialchars($cov['drug_name']) : 'Drug #' . $drug_id;
						$result['nhis_warnings'][] = $name . ' is not on the NHIS formulary (cash price applies)';
					}
				} catch (Exception $e) {
					log_message('error', 'NHIS formulary check error drug ' . $drug_id . ': ' . $e->getMessage());
				}
			}

			// CDS safety check
			if ($result['cds_loaded']) {
				try {
					$alerts = $this->Clinical_decision_support_model->check_prescription_safety_full(
						$drug_id,
						isset($med['dosage'])         ? trim($med['dosage'])         : '',
						isset($med['frequency'])      ? trim($med['frequency'])      : '',
						isset($med['days'])           ? (int)$med['days']            : 1,
						$patient_no,
						$iop_id,
						isset($med['diagnosis_code']) ? trim($med['diagnosis_code']) : ''
					);

					if ($this->Clinical_decision_support_model->should_block_prescription($alerts)) {
						foreach ($alerts as $a) {
							if ($a->severity === 'BLOCKED') {
								$result['blocked_drugs'][] = array(
									'entry_idx' => $idx,
									'drug_id'   => $drug_id,
									'type'      => $a->type,
									'message'   => $a->message,
								);
							}
						}
					} else {
						foreach ($alerts as $a) {
							$result['cds_warnings'][] = $a->type . ': ' . $a->message;
						}
					}
				} catch (Exception $e) {
					log_message('error', 'CDS check error drug ' . $drug_id . ': ' . $e->getMessage());
				}
			}
		}

		if (!empty($result['blocked_drugs'])) {
			$msgs = array();
			foreach ($result['blocked_drugs'] as $b) { $msgs[] = $b['type'] . ': ' . $b['message']; }
			$result['status']  = 'blocked';
			$result['message'] = 'Prescription blocked — patient safety risk: ' . implode('; ', $msgs);
			return $result;
		}

		$result['ok']     = true;
		$result['status'] = 'ok';
		return $result;
	}

	/**
	 * Shared post-save billing + audit trigger — called after every successful medication insert.
	 * Non-blocking: any error is logged but does NOT undo the saved prescription.
	 *
	 * @param int    $iop_med_id   New iop_medication primary key
	 * @param int    $drug_id      Drug master ID (0 for free-text)
	 * @param string $drug_name    Drug name (for billing label)
	 * @param int    $qty          Quantity
	 * @param string $iop_id       Decoded visit ID
	 * @param string $patient_no   Patient number
	 * @param string $doctor_id    Prescribing doctor user ID
	 * @param string $payer        NHIS or CASH
	 * @param bool   $cds_loaded   Whether CDS model is available
	 * @param string $context      Log context label (OPD/IPD/NURSE)
	 */
	private function _post_save_billing_triggers($iop_med_id, $drug_id, $drug_name, $qty,
		$iop_id, $patient_no, $doctor_id, $payer, $cds_loaded, $context = 'OPD')
	{
		$isVerified = false;
		try {
			if ($this->db->field_exists('prescription_status', 'iop_medication')) {
				$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => (int)$iop_med_id, 'InActive' => 0))->row();
				$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : 'PENDING';
				$isVerified = ($st === 'VERIFIED');
			}
		} catch (Exception $e) {
			$isVerified = false;
		}

		if ($isVerified) {
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->load->model('app/pharmacy_model');
				$this->pharmacy_model->create_or_update_pharmacy_bill($iop_med_id, $doctor_id);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', $context . ' pharmacy_bill non-blocking: ' . $e->getMessage());
			}

			try {
				$drugPrice = 0;
				if ($drug_id > 0) {
					$_dbg = $this->db->db_debug; $this->db->db_debug = false;
					$dr = $this->db->select('drug_name, nPrice, cash_price')
						->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
					$this->db->db_debug = $_dbg;
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
					'item_id'       => (string)$iop_med_id,
					'item_name'     => $drug_name ?: 'Medication',
					'unit_price'    => $drugPrice,
					'quantity'      => $qty > 0 ? $qty : 1,
					'payer_type'    => $payer,
					'source_module' => 'PHARMACY',
					'source_ref'    => 'iop_id:' . (string)$iop_id . ':iop_medication:' . (string)(int)$iop_med_id,
					'requested_by'  => $doctor_id,
				));
			} catch (Exception $e) {
				log_message('error', $context . ' unified_billing non-blocking: ' . $e->getMessage());
			}
		}

		// 3. NHIS audit log
		try {
			$_dbg = $this->db->db_debug; $this->db->db_debug = false;
			$this->load->model('app/billing_model');
			$this->billing_model->log_nhis_audit(
				$context . '_SAVE_MEDICATION', 'iop_medication', $iop_med_id,
				null, json_encode(array('drug_id' => $drug_id, 'qty' => $qty)),
				$doctor_id, $patient_no, $iop_id
			);
			$this->db->db_debug = $_dbg;
		} catch (Exception $e) {
			$this->db->db_debug = isset($_dbg) ? $_dbg : true;
			log_message('error', $context . ' nhis_audit non-blocking: ' . $e->getMessage());
		}

		// 4. CDS workflow tracking
		if ($cds_loaded && $drug_id > 0) {
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->Clinical_decision_support_model->init_prescription_workflow(
					$iop_med_id, $iop_id, $patient_no, $doctor_id
				);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', $context . ' cds_workflow non-blocking: ' . $e->getMessage());
			}
		}
	}

	public function getDrugName($id)
	{

		$this->data['drug_name_lists'] = $this->opd_model->drug_name_lists($id);

		$this->load->view("app/opd/drug_name_lists", $this->data);
	}

	public function save_medication()
	{
		$iop_id_raw = $this->input->post('opd_no');
		$patient_no_raw = $this->input->post('patient_no');
		$iop_id = url_decode_id($iop_id_raw);
		$patNo  = trim((string)$patient_no_raw);
		$doctor_id = (string)$this->session->userdata('user_id');

		if (!$this->doctor_write_allowed_or_redirect($iop_id_raw, $patient_no_raw, 'OPD', 'save_medication')) {
			return;
		}

		if ($this->is_visit_cleared($iop_id)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-lock'></i> Cannot add prescriptions - patient is clinically cleared.</div>");
			redirect(base_url() . 'app/opd/view/' . url_safe_id($iop_id) . '/' . $patNo);
			return;
		}

		$drugId        = (int)$this->input->post('drug_name');
		$diagnosisCode = strip_tags(trim((string)$this->input->post('diagnosis_code')));
		$diagnosisDesc = strip_tags(trim((string)$this->input->post('diagnosis_description')));

		// ── Shared validation kernel (NHIS gate + CDS + formulary) ───────────
		$val = $this->_run_prescription_validation($iop_id, $patNo, array(
			array(
				'drug_id'        => $drugId,
				'dosage'         => $this->input->post('dosage'),
				'frequency'      => $this->input->post('frequency'),
				'days'           => $this->input->post('nDays'),
				'diagnosis_code' => $diagnosisCode,
			),
		));

		if ($val['status'] === 'nhis_block') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> " . htmlspecialchars($val['message']) . "</div>");
			redirect(base_url() . 'app/opd/medication/' . $iop_id_raw . '/' . $patNo);
			return;
		}

		if ($val['status'] === 'blocked') {
			$blockHtml = '';
			foreach ($val['blocked_drugs'] as $b) {
				$blockHtml .= '<li><strong>' . htmlspecialchars($b['type']) . ':</strong> ' . htmlspecialchars($b['message']) . '</li>';
			}
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> <strong>Prescription Blocked - Patient Safety Risk:</strong><ul>{$blockHtml}</ul>Please consult a senior physician or document an override justification.</div>");
			redirect(base_url() . 'app/opd/medication/' . url_safe_id($iop_id) . '/' . $patNo);
			return;
		}

		$payer      = $val['payer'];
		$schema     = $val['schema'];
		$cdsLoaded  = $val['cds_loaded'];

		// Build non-blocking warning HTML from validation results
		$warningHtml = '';
		foreach ($val['nhis_warnings'] as $w) {
			$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-exclamation-triangle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Notice:</strong> " . htmlspecialchars($w) . "</div>";
		}
		foreach ($val['cds_warnings'] as $w) {
			$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Safety Alert:</strong> " . htmlspecialchars($w) . "</div>";
		}
		if ($payer === 'NHIS' && empty($diagnosisCode)) {
			$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-exclamation-triangle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Notice:</strong> No diagnosis code provided. This may affect NHIS claim submission.</div>";
		}

		// ── Build insert array using hoisted schema map ───────────────────────
		$insert = array(
			'iop_id'      => $iop_id,
			'medicine_id' => $drugId ?: null,
			'medicine_text' => strip_tags(trim((string)$this->input->post('medicine_text'))),
			'instruction' => strip_tags(trim((string)$this->input->post('instruction'))),
			'advice'      => strip_tags(trim((string)$this->input->post('advice'))),
			'days'        => max(1, (int)$this->input->post('nDays')),
			'total_qty'   => max(0.01, (float)$this->input->post('qty')),
			'dosage'      => strip_tags(trim((string)$this->input->post('dosage'))),
			'cPreparedBy' => $doctor_id,
			'dDate'       => date('Y-m-d H:i:s'),
			'InActive'    => 0,
		);
		if ($schema['prescribed_by'])        $insert['prescribed_by']          = $doctor_id;
		if ($schema['frequency']) {
			$freq = strip_tags(trim((string)$this->input->post('frequency')));
			if ($freq !== '')                $insert['frequency']               = $freq;
		}
		if ($schema['dispensing_status'])    $insert['dispensing_status']       = 'PENDING';
		if ($schema['payment_status'])       $insert['payment_status']          = 'PENDING';
		if ($schema['diagnosis_code'])       $insert['diagnosis_code']          = $diagnosisCode;
		if ($schema['diagnosis_description'])$insert['diagnosis_description']   = $diagnosisDesc;
		/* Phase 4 fields */
		if ($schema['unit'])                 $insert['unit']                   = strip_tags(trim((string)$this->input->post('unit')));
		if ($schema['freq_code'])            $insert['freq_code']              = strip_tags(trim((string)$this->input->post('freq_code')));
		if ($schema['route'])                $insert['route']                  = strip_tags(trim((string)$this->input->post('route')));
		if ($schema['drug_form'])            $insert['drug_form']              = strip_tags(trim((string)$this->input->post('drug_form')));
		if ($schema['is_nhis_covered'])      $insert['is_nhis_covered']         = (int)$this->input->post('is_nhis_covered');
		if ($schema['is_prn'])               $insert['is_prn']                  = (int)$this->input->post('is_prn');
		if ($schema['is_urgent'])            $insert['is_urgent']               = (int)$this->input->post('is_urgent');
		/* Structured strength fields */
		if ($schema['prescribed_dose_value'])$insert['prescribed_dose_value']   = (float)$this->input->post('dosage');
		if ($schema['prescribed_dose_unit']) $insert['prescribed_dose_unit']    = strip_tags(trim((string)$this->input->post('unit')));
		if ($schema['strength_per_unit_value'])$insert['strength_per_unit_value'] = (float)$this->input->post('strength_per_unit_value');
		if ($schema['strength_per_unit_unit'])$insert['strength_per_unit_unit'] = strip_tags(trim((string)$this->input->post('strength_per_unit_unit')));
		if ($schema['required_units'])       $insert['required_units']          = (float)$this->input->post('required_units');
		if ($schema['total_active_mass_value'])$insert['total_active_mass_value'] = (float)$this->input->post('total_active_mass_value');
		if ($schema['total_active_mass_unit'])$insert['total_active_mass_unit'] = strip_tags(trim((string)$this->input->post('total_active_mass_unit')));
		/* prescribed_qty population under feature flag */
		$useDecimalPrescriptions = $this->config->item('ENABLE_DECIMAL_PRESCRIPTIONS') || $this->config->item('ENABLE_DECIMAL_PRESCRIBED_QTY');
		if ($schema['prescribed_qty']) {
			$requiredUnits = (float)$this->input->post('required_units');
			$totalQty = (float)$this->input->post('qty');
			if ($useDecimalPrescriptions) {
				$insert['prescribed_qty'] = $requiredUnits > 0 ? $requiredUnits : $totalQty;
			} else {
				$insert['prescribed_qty'] = (int)round($totalQty);
			}
		}

		log_message('info', 'OPD_MEDICATION_INSERT: iop_id=' . $iop_id . ' patient=' . $patNo . ' drug=' . $drugId);
		$this->db->insert('iop_medication', $insert);
		$new_iop_med_id = $this->db->insert_id();

		if (!$new_iop_med_id) {
			log_message('error', 'OPD_MEDICATION_INSERT_FAILED: ' . json_encode($this->db->error()));
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-times'></i> Failed to save medication. Please try again.</div>");
			redirect(base_url() . 'app/opd/medication/' . url_safe_id($iop_id) . '/' . $patNo);
			return;
		}

		log_message('info', 'OPD_MEDICATION_INSERT_SUCCESS: iop_med_id=' . $new_iop_med_id);

		// ── Post-save billing + audit (shared helper, non-blocking) ──────────
		$this->_post_save_billing_triggers(
			$new_iop_med_id, $drugId,
			strip_tags(trim((string)$this->input->post('medicine_text'))),
			(int)$this->input->post('qty'),
			$iop_id, $patNo, $doctor_id, $payer, $cdsLoaded, 'OPD'
		);

		// Log individual CDS safety alerts (non-blocking)
		if ($cdsLoaded && isset($this->Clinical_decision_support_model)) {
			try {
				foreach ($val['cds_warnings'] as $w) {
					// cds_warnings are plain strings; detailed alert logging is in init_prescription_workflow
				}
			} catch (Exception $e) {
				log_message('error', 'OPD CDS alert log non-blocking: ' . $e->getMessage());
			}
		}

		$successHtml = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication successfully Added!</div>";
		$this->session->set_flashdata('message', $warningHtml . $successHtml);

		redirect(base_url() . 'app/opd/medication/' . $iop_id_raw . '/' . $patNo);
	}



	public function billing()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];

		$this->data['message'] = $this->session->flashdata('message');

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();
		$this->data['nhis_subsidy_pct'] = (float)$this->billing_model->get_nhis_config('nhis_subsidy_percent', '100');


		if ($this->billing_model->checkInvoice($iop_no)) {
			redirect(base_url() . "app/opd/billingView/" . url_safe_id($iop_no) . "/" . $patient_no);
		} else {
			$this->load->view("app/opd/billing", $this->data);
		}
	}

	public function billingView()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];

		$this->data['message'] = $this->session->flashdata('message');

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();
		// Get the invoice number for this encounter
		$this->db->where(array('iop_id' => $iop_no, 'InActive' => 0));
		$this->db->order_by('bill_id', 'DESC');
		$query = $this->db->get('iop_billing');
		$existingInv = $query->row();
		if ($existingInv) {
			$invNo = $existingInv->invoice_no;
			$this->data['headerInv'] = $this->billing_model->headerInv2($invNo);
			$this->data['detailsInv'] = $this->billing_model->detailsInv2($invNo);
		} else {
			$this->data['headerInv'] = null;
			$this->data['detailsInv'] = array();
		}
		$this->data['nhis_subsidy_pct'] = (float)$this->billing_model->get_nhis_config('nhis_subsidy_percent', '100');
		$this->data['billable_items'] = $this->billing_model->patientMedication($patient_no, $iop_no);

		$this->load->view("app/opd/billingView", $this->data);
	}

	public function printInv()
	{
		$this->require_report_scope('financial');
		$this->audit_report_generation('financial', 'opd_print_invoice', 'browser');
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$InvNo = $this->segment_decoded(6);

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();

		$this->data['headerInv'] = $this->billing_model->headerInv2($InvNo);
		$this->data['detailsInv'] = $this->billing_model->detailsInv2($InvNo);

		$this->load->view("app/opd/printInv", $this->data);
	}

	public function printOR()
	{
		$this->require_report_scope('financial');
		$this->audit_report_generation('financial', 'opd_print_or', 'browser');
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$InvNo = $this->segment_decoded(6);

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();

		$payload = $this->billing_model->build_receipt_print_payload($InvNo);
		$this->data = array_merge($this->data, $payload);

		$this->load->view("app/opd/printOR", $this->data);
	}

	public function pdfOR()
	{
		$this->require_report_scope('financial');
		$this->audit_report_generation('financial', 'opd_print_or', 'pdf');
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$InvNo = $this->segment_decoded(6);

		$this->load->helper('file');
		$this->load->helper('dompdf');

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);

		$this->data['payment_type'] = $this->billing_model->payment_type();
		$this->data['insurance_company'] = $this->billing_model->insurance_company();
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['invoice_no'] = $this->billing_model->invoice_no();

		$payload = $this->billing_model->build_receipt_print_payload($InvNo);
		$this->data = array_merge($this->data, $payload);
		$this->data['ORNUM'] = $this->data['getOR'];
		$ORNUM = $this->data['getOR'];
		$filename = (($ORNUM && isset($ORNUM->receipt_no) && trim((string)$ORNUM->receipt_no) !== '') ? $ORNUM->receipt_no : 'RECEIPT') . "_" . date("mdY");

		$html = $this->load->view("app/opd/printOR2", $this->data, true);
		pdf_create($html, $filename, TRUE);
	}

	/**
	 * AJAX Clinical Clear - locks the visit
	 */
	public function clinical_clear()
	{
		header('Content-Type: application/json');
		
		$iop_id = $this->input->post('iop_id');
		$patient_no = $this->input->post('patient_no');
		
		// Decode URL-safe IDs
		$iop_id = $this->decode_url_id($iop_id);
		$patient_no = $this->decode_url_id($patient_no);
		
		$userId = (string)$this->session->userdata('user_id');
		
		if (empty($iop_id)) {
			echo json_encode(['status' => 'error', 'message' => 'Invalid visit ID']);
			return;
		}
		
		// Use unified status engine for clinical clearance (also triggers advance_queue internally)
		$result = $this->opd_status_engine->clinical_clear($iop_id, $userId);
		
		if (!$result['success']) {
			echo json_encode(['status' => 'error', 'message' => $result['message']]);
			return;
		}
		
		// Update clearance workflow stage
		$this->billing_model->upsert_clearance_stage($iop_id, 'CLINICAL', $patient_no, $userId);
		
		// Generate NHIS claim if applicable
		if ($patient_no) {
			$claimId = $this->billing_model->generate_nhis_claim($iop_id, $patient_no, $userId);
		}

		// Find the patient that was just promoted to IN_CONSULTATION (if any)
		$promoted = null;
		$promotedQ = $this->db->query(
			"SELECT P.IO_ID, P.patient_no,
			        CONCAT(COALESCE(S.cValue,''),' ',PI.firstname,' ',PI.lastname) AS patient_name
			 FROM iop_opd_workflow W
			 INNER JOIN patient_details_iop P ON P.IO_ID = W.iop_id AND P.InActive = 0
			 INNER JOIN patient_personal_info PI ON PI.patient_no = P.patient_no
			 LEFT JOIN system_parameters S ON S.param_id = PI.title
			 WHERE W.status = 'IN_CONSULTATION' AND W.InActive = 0
			   AND P.date_visit = ? AND P.IO_ID != ?
			   AND P.doctor_id = (SELECT doctor_id FROM patient_details_iop WHERE IO_ID = ? AND InActive = 0 LIMIT 1)
			 ORDER BY W.in_consultation_at DESC LIMIT 1",
			array(date('Y-m-d'), $iop_id, $iop_id)
		);
		if ($promotedQ && $promotedQ->num_rows() > 0) {
			$pr = $promotedQ->row();
			$promoted = array('iop_id' => $pr->IO_ID, 'patient_no' => $pr->patient_no, 'patient_name' => trim($pr->patient_name));
		}

		echo json_encode(['status' => 'success', 'message' => 'Patient clinically cleared', 'promoted' => $promoted]);
	}

	/**
	 * AJAX Reopen Visit - Admin only
	 */
	public function reopen_visit_ajax()
	{
		header('Content-Type: application/json');
		
		if (!$this->current_user_is_admin()) {
			echo json_encode(['status' => 'error', 'message' => 'Admin access required']);
			return;
		}
		
		$iop_id = $this->input->post('iop_id');
		// Decode URL-safe ID
		$iop_id = $this->decode_url_id($iop_id);
		
		if (empty($iop_id)) {
			echo json_encode(['status' => 'error', 'message' => 'Invalid visit ID']);
			return;
		}
		
		$userId = (string)$this->session->userdata('user_id');
		$reason = $this->input->post('reason') ?: 'Admin reopen';
		
		// Use unified status engine for reopen
		$result = $this->opd_status_engine->reopen($iop_id, $userId, $reason);
		
		if ($result['success']) {
			echo json_encode(['status' => 'success', 'message' => 'Visit reopened']);
		} else {
			echo json_encode(['status' => 'error', 'message' => $result['message']]);
		}
	}

	/**
	 * Check if visit is clinically cleared (for backend guards)
	 */
	public function is_visit_cleared($iop_id)
	{
		$visit = $this->db->get_where('patient_details_iop', ['IO_ID' => $iop_id])->row();
		return ($visit && isset($visit->clinical_clearance_status) && $visit->clinical_clearance_status == 1);
	}

	/**
	 * Discharge / Clinical Clearance (non-AJAX version)
	 * Uses unified status engine - consolidated with clinical_clear()
	 */
	public function discharge()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		if (!($this->current_user_is_admin() || $this->current_user_is_reception())) {
			if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'OPD', 'clinical_clearance')) {
				return;
			}
		}

		$userId = (string)$this->session->userdata('user_id');
		
		// Use unified status engine for clinical clearance
		$result = $this->opd_status_engine->clinical_clear($iop_no, $userId);
		
		if (!$result['success']) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . htmlspecialchars($result['message']) . "</div>");
			redirect(base_url() . 'app/opd/index');
			return;
		}
		
		// Update clearance workflow stage
		$this->billing_model->upsert_clearance_stage($iop_no, 'CLINICAL', $patient_no, $userId);
		$this->billing_model->log_nhis_audit('VISIT_DISCHARGED', 'patient_details_iop', $iop_no, null, 'CLINICALLY_CLEARED', $userId, $patient_no, $iop_no);

		// Generate NHIS claim
		$claimId = $this->billing_model->generate_nhis_claim($iop_no, $patient_no, $userId);
		$claimMsg = '';
		if ($claimId && $claimId > 0) {
			$claim = $this->billing_model->get_claim($claimId);
			$claimRef = ($claim && isset($claim->claim_ref)) ? $claim->claim_ref : '';
			$claimMsg = "<div class='alert alert-info alert-dismissable'><i class='fa fa-medkit'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Claim Generated:</strong> " . htmlspecialchars($claimRef) . "</div>";
		}

		// Queue post-visit SMS
		$this->load->model('app/sms_model');
		$smsQueued = $this->sms_model->queue_post_visit_sms($patient_no, $iop_no);
		if ($smsQueued === false || $smsQueued <= 0) {
			log_message('error', '[OPD_DISCHARGE_SMS_FAIL] patient=' . $patient_no . ' iop=' . $iop_no);
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>OPD Patient clinically cleared!</div>" . $claimMsg);

		redirect(base_url() . 'app/opd/index');
	}

	public function admit_patient()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$iop_no     = (string)$this->input->post('iop_no');
		$patient_no = (string)$this->input->post('patient_no');
		$ward       = trim((string)$this->input->post('ward'));
		$bed        = trim((string)$this->input->post('bed'));
		$notes      = trim((string)$this->input->post('notes'));
		$return_url = trim((string)$this->input->post('return_url'));
		$user_id    = (string)$this->session->userdata('user_id');

		if ($iop_no === '' || $patient_no === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'>OPD reference and patient number are required.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/opd/index');
			return;
		}

		// Backend guard: Check if visit is clinically cleared
		if ($this->is_visit_cleared($iop_no)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-lock'></i> Cannot admit patient - visit is clinically cleared.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/opd/index');
			return;
		}

		$this->load->model('app/pharmacy_model');
		$this->pharmacy_model->ensure_pharmacy_ghs_schema();
		$this->db->trans_begin();

		if ($this->_opd_table_exists('patient_admissions', 'patient_admissions_schema')) {
			$this->db->where(array('iop_id' => $iop_no, 'patient_no' => $patient_no, 'status' => 'Admitted', 'InActive' => 0));
			$already = $this->db->count_all_results('patient_admissions');
			if ($already > 0) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient is already admitted for this OPD visit.</div>");
				redirect($return_url !== '' ? $return_url : base_url() . 'app/opd/index');
				return;
			}

			$now = date('Y-m-d H:i:s');
			$this->db->insert('patient_admissions', array(
				'patient_no'      => $patient_no,
				'iop_id'          => $iop_no,
				'ward'            => $ward,
				'bed'             => $bed,
				'admitted_by'     => $user_id,
				'admission_date'  => $now,
				'status'          => 'Admitted',
				'notes'           => $notes,
				'created_at'      => $now,
				'updated_at'      => $now,
				'InActive'        => 0,
			));

			$this->pharmacy_model->log_pharmacy_audit(0, $iop_no, $patient_no, 'PATIENT_ADMITTED', null, 'Admitted', 'Ward: ' . $ward . ' Bed: ' . $bed, $user_id);
		}

		$this->opd_status_engine->transition($iop_no, 'ADMITTED', $user_id, 'Patient admitted to IPD', 'opd::admit_patient', true);
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Admission could not be completed.</div>");
			redirect($return_url !== '' ? $return_url : base_url() . 'app/opd/index');
			return;
		}
		$this->db->trans_commit();

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient admitted to ward: <strong>" . htmlspecialchars($ward) . "</strong>. Admission record created.</div>");
		redirect($return_url !== '' ? $return_url : base_url() . 'app/opd/index');
	}

	public function discharge_summary()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);

		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['get_discharge_summary'] = $this->opd_model->get_discharge_summary($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['getConditionDis'] = $this->general_model->getConditionDis();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view("app/opd/discharge_summary", $this->data);
	}


	public function save_discharge_summary()
	{
		if (!$this->doctor_write_allowed_or_redirect($this->input->post('opd_no'), $this->input->post('patient_no'), 'OPD', 'save_discharge_summary')) {
			return;
		}
		$this->db->trans_begin();
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
		$this->db->insert("iop_discharge_summary", $this->data);
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Discharge summary could not be saved.</div>");
			redirect(base_url() . 'app/opd/discharge_summary/' . $this->input->post('opd_no') . '/' . $this->input->post('patient_no'), $this->data);
			return;
		}
		$this->db->trans_commit();

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Discharge summary successfully Added!</div>");

		redirect(base_url() . 'app/opd/discharge_summary/' . $this->input->post('opd_no') . '/' . $this->input->post('patient_no'), $this->data);
	}


	public function laboratory()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd'); return;
		}
		$iop_no = $v['iop_no']; $patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo']   = $v['patientInfo'];

		$this->data['message'] = $this->session->flashdata('message');

		//list of category
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		$this->data['patient_lab'] = $this->opd_model->patient_lab($iop_no);

		// For Sonography modal - Use GHS Standard Catalog
		$this->load->model('app/Ghana_test_catalog_model');
		$this->data['sono_items'] = $this->Ghana_test_catalog_model->get_sonography_tests();
		$this->data['sono_categories'] = $this->Ghana_test_catalog_model->get_sonography_categories();
		$this->data['doctorList'] = $this->general_model->doctorList();
		$this->data['doctorList2'] = $this->data['doctorList'];
		$this->load->model('app/laboratory_model');
		$this->data['sonography_category_id'] = $this->laboratory_model->get_sonography_category_id();

		// For Multi-Entry Laboratory Modal - Single Source of Truth: bill_particular
		$this->data['lab_categories'] = $this->billing_model->get_lab_categories();
		$this->data['lab_tests'] = $this->billing_model->get_lab_tests();

		// For Radiology Modal
		$this->load->model('app/radiology_model');
		$this->data['radiology_tests'] = $this->radiology_model->get_active_tests();

		// C2: Resolve patient payer type for NHIS badge in modal
		$labViewPatientNo = isset($v['patientInfo']) && isset($v['patientInfo']->patient_no) ? (string)$v['patientInfo']->patient_no : '';
		if ($labViewPatientNo === '' && isset($getOPDPatient->patient_no)) {
			$labViewPatientNo = (string)$getOPDPatient->patient_no;
		}
		$this->data['patient_payer_type'] = ($labViewPatientNo !== '') ? $this->billing_model->determine_payer_type($labViewPatientNo) : 'CASH';

		// Pre-compute deletion eligibility for each lab record
		$deleteMap = array();
		if (is_array($this->data['patient_lab']) || is_object($this->data['patient_lab'])) {
			foreach ($this->data['patient_lab'] as $pl) {
				$plId = isset($pl->io_lab_id) ? (int)$pl->io_lab_id : 0;
				if ($plId > 0) {
					$deleteMap[$plId] = $this->laboratory_model->can_delete_lab_request($plId);
				}
			}
		}
		$this->data['lab_delete_map'] = $deleteMap;

		$pilotSnapshotRead = (bool)$this->config->item('lab_release_snapshot_read_enabled');
		$this->data['lab_release_snapshot_read_pilot'] = $pilotSnapshotRead;
		$this->data['release_snapshot_map'] = array();
		if ($pilotSnapshotRead) {
			$this->load->model('app/laboratory_release_model');
			if (isset($this->laboratory_release_model) && method_exists($this->laboratory_release_model, 'table_exists') && $this->laboratory_release_model->table_exists('iop_laboratory_release_snapshot')) {
				$q = $this->db->query(
					"SELECT S.*\n					 FROM iop_laboratory_release_snapshot S\n					 JOIN iop_laboratory_release_batch B ON B.release_id = S.release_id AND B.InActive = 0 AND B.release_status = 'RELEASED'\n					 WHERE S.iop_id = ? AND S.InActive = 0\n					 ORDER BY S.snapshot_id DESC",
					array((string)$iop_no)
				);
				$rows = $q ? $q->result_array() : array();
				$map = array();
				foreach ($rows as $r) {
					$k = isset($r['io_lab_id']) ? (int)$r['io_lab_id'] : 0;
					if ($k > 0 && !isset($map[$k])) {
						$map[$k] = $r;
					}
				}
				$this->data['release_snapshot_map'] = $map;
			}
		}

		$this->load->view("app/opd/laboratory", $this->data);
	}

	public function procedures()
	{
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$v = $this->_resolve_opd_visit($iop_no, $patient_no);
		if (!$v) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Visit or patient record not found.</div>");
			redirect(base_url() . 'app/opd');
			return;
		}
		$iop_no = $v['iop_no'];
		$patient_no = $v['patient_no'];
		$this->data['getOPDPatient'] = $v['getOPDPatient'];
		$this->data['patientInfo'] = $v['patientInfo'];
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->model('app/billing_model');
		try {
			if (isset($this->billing_model) && method_exists($this->billing_model, 'ensure_procedure_catalog_seeded')) {
				$this->billing_model->ensure_procedure_catalog_seeded((string)$this->session->userdata('user_id'));
			}
		} catch (\Throwable $e) {
			log_message('error', 'opd/procedures ensure_procedure_catalog_seeded: ' . $e->getMessage());
		}

		$this->load->model('app/procedure_request_model');
		$this->procedure_request_model->ensure_schema();
		$this->data['procedure_requests'] = $this->procedure_request_model->list_for_visit($iop_no);

		$this->data['procedure_categories'] = array();
		$this->data['procedure_category_id'] = 0;
		if ($this->_opd_table_exists('bill_group_name', 'bill_group_name_schema')) {
			$q = $this->db->query("SELECT group_id, group_name, group_desc FROM bill_group_name WHERE InActive = 0 AND (UPPER(group_name) LIKE '%PROCEDURE%' OR UPPER(group_name) LIKE '%MINOR%') ORDER BY group_name ASC");
			$this->data['procedure_categories'] = $q ? $q->result() : array();
			foreach ($this->data['procedure_categories'] as $g) {
				$gn = isset($g->group_name) ? strtoupper(trim((string)$g->group_name)) : '';
				if ($gn === 'PROCEDURES' || $gn === 'PROCEDURE') {
					$this->data['procedure_category_id'] = isset($g->group_id) ? (int)$g->group_id : 0;
					break;
				}
			}
			if ((int)$this->data['procedure_category_id'] <= 0 && !empty($this->data['procedure_categories'])) {
				$first = $this->data['procedure_categories'][0];
				$this->data['procedure_category_id'] = isset($first->group_id) ? (int)$first->group_id : 0;
			}
		}

		$this->data['procedure_items'] = array();
		if ((int)$this->data['procedure_category_id'] > 0 && $this->_opd_table_exists('bill_particular', 'bill_particular_schema')) {
			$this->db->order_by('particular_name', 'ASC');
			$this->data['procedure_items'] = $this->db->get_where('bill_particular', array('group_id' => (int)$this->data['procedure_category_id'], 'InActive' => 0))->result();
		}

		$this->load->view('app/opd/procedures', $this->data);
	}



	public function save_laboratory()
	{
		// CRITICAL: Decode URL-safe IDs back to original format (OP-000002 -> OP 000002)
		$iop_id = url_decode_id($this->input->post('opd_no'));
		$patient_no = $this->input->post('patient_no');
		
		if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'save_laboratory')) {
			return;
		}

		// Backend guard: Check if visit is clinically cleared
		if ($this->is_visit_cleared($iop_id)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-lock'></i> Cannot add lab orders - patient is clinically cleared.</div>");
			redirect(base_url() . 'app/opd/view/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$this->load->model('app/billing_model');
		$labGate = $this->billing_model->check_nhis_payment_gate(
			(string)$patient_no,
			(string)$iop_id,
			'LAB'
		);
		if (!$labGate['allowed']) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> " . htmlspecialchars($labGate['reason']) . "</div>");
			redirect(base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$this->load->model('app/laboratory_model');
		$sono_cat_pre = (int)$this->laboratory_model->get_sonography_category_id();
		$category_id = (int)$this->input->post('category');
		$particular = (int)$this->input->post('particular');
		// Sonography modal posts scan item id as 'item' — fall back to it
		if ($particular <= 0) {
			$particular = (int)$this->input->post('item');
		}
		$clinical_question = trim((string)$this->input->post('clinical_question'));
		$urgency = trim((string)$this->input->post('urgency'));
		$laboratory_text = $this->input->get_post('laboratory_text');
		$is_urgent_post = ($this->input->post('is_urgent') == '1') ? 1 : 0;
		$specimen_type_post = trim((string)$this->input->post('specimen_type'));
		// SSOT: For non-sonography lab requests, treat $particular as bill_particular.particular_id
		if ($category_id !== $sono_cat_pre) {
			if ($particular <= 0) {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please select a laboratory test.</div>");
				redirect(base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no);
				return;
			}
			$bp = $this->db->query(
				"SELECT particular_id, particular_name, group_id FROM bill_particular WHERE particular_id = ? AND InActive = 0 LIMIT 1",
				array($particular)
			)->row();
			if (!$bp) {
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Selected lab test is invalid or inactive.</div>");
				redirect(base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no);
				return;
			}
			$category_id = (int)$bp->group_id;
			$particular = (int)$bp->particular_id;
			$laboratory_text = (string)$bp->particular_name;
		}
		if ($category_id === $sono_cat_pre) {
			$u = strtoupper(trim((string)$urgency));
			if ($u !== 'ROUTINE' && $u !== 'URGENT' && $u !== 'STAT') {
				$urgency = 'ROUTINE';
			}
			if ($particular <= 0 && $clinical_question === '') {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please select a sonography scan or enter a clinical question.</div>");
				redirect(base_url() . 'app/opd/laboratory/' . $this->input->post('opd_no') . '/' . $this->input->post('patient_no'), $this->data);
				return;
			}
			if ($particular <= 0) {
				$particular = 0;
				if (trim((string)$laboratory_text) === '') {
					$laboratory_text = 'Custom Sonography Request';
				}
			}
		}

		$dDate = trim((string)$this->input->post('dDate'));
		if ($dDate === '') {
			$dDate = date('Y-m-d');
		}
		$cTimeRaw = trim((string)$this->input->post('cTime'));
		if ($cTimeRaw === '') {
			$cTimeRaw = date('H:i:s');
		}
		$dDateTime = null;
		$dtInput = $dDate . ' ' . $cTimeRaw;
		$formats = array('Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d g:i A', 'Y-m-d g:i:s A', 'Y-m-d h:i A', 'Y-m-d h:i:s A');
		for ($i = 0; $i < count($formats); $i++) {
			$dt = DateTime::createFromFormat($formats[$i], $dtInput);
			if ($dt instanceof DateTime) {
				$dDateTime = $dt->format('Y-m-d H:i:s');
				break;
			}
		}
		if ($dDateTime === null) {
			$dDateTime = $dDate . ' ' . date('H:i:s');
		}

		$insertData = array(
			'iop_id'				=>		$iop_id,
			'dDate'					=>		$dDate,
			'dDateTime'				=>		$dDateTime,
			'category_id'			=>		$category_id,
			'laboratory_id'			=>		$particular,
			'laboratory_text'		=>		$laboratory_text,
			'findings'				=>		$this->input->post('findings'),
			'result'				=>		$this->input->post('results'),
			'doctor'				=>		$this->input->post('doctor'),
			'InActive'				=>		0
		);
		if ($this->laboratory_model->column_exists('iop_laboratory', 'is_urgent')) {
			$insertData['is_urgent'] = $is_urgent_post;
		}
		if ($this->laboratory_model->column_exists('iop_laboratory', 'specimen_type') && $specimen_type_post !== '') {
			$insertData['specimen_type'] = $specimen_type_post;
		}
		if ($this->laboratory_model->column_exists('iop_laboratory', 'clinical_notes') && $clinical_question !== '') {
			$insertData['clinical_notes'] = $clinical_question;
		}
		$labPayerType = $this->billing_model->determine_payer_type((string)$patient_no);
		$labNhisFlag  = ($labPayerType === 'NHIS') ? 1 : 0;
		if ($this->laboratory_model->column_exists('iop_laboratory', 'payer_type')) {
			$insertData['payer_type'] = $labPayerType;
		}
		if ($this->laboratory_model->column_exists('iop_laboratory', 'nhis_flag')) {
			$insertData['nhis_flag'] = $labNhisFlag;
		}
		$this->data = $insertData;
		$this->load->model('app/laboratory_model');
		$this->laboratory_model->install_imaging_tables();
		$sono_cat = (int)$this->laboratory_model->get_sonography_category_id();
		if (!($category_id === $sono_cat && (int)$particular === 0)) {
			$lab_id_i = (int)$this->data['laboratory_id'];
			$lab_text = (string)$this->data['laboratory_text'];
			if ((int)$this->data['category_id'] === $sono_cat && trim($lab_text) !== '') {
				$norm_target = strtolower(trim((string)preg_replace('/\s+/', ' ', $lab_text)));
				$this->db->select('laboratory_id, laboratory_text');
				$this->db->where(array(
					'iop_id' => $this->data['iop_id'],
					'category_id' => $this->data['category_id'],
					'InActive' => 0
				));
				$this->db->where("(result = '' OR result IS NULL)", null, false);
				$this->db->limit(200);
				$cands = $this->db->get('iop_laboratory')->result_array();
				$dup_found = false;
				for ($i = 0; $i < count($cands); $i++) {
					$cid = isset($cands[$i]['laboratory_id']) ? (int)$cands[$i]['laboratory_id'] : 0;
					if ($cid > 0 && $cid === $lab_id_i) {
						$dup_found = true;
						break;
					}
					$ct = isset($cands[$i]['laboratory_text']) ? (string)$cands[$i]['laboratory_text'] : '';
					$ctn = strtolower(trim((string)preg_replace('/\s+/', ' ', $ct)));
					if ($ctn !== '' && $ctn === $norm_target) {
						$dup_found = true;
						break;
					}
				}
				if ($dup_found) {
					$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>A similar laboratory request is already pending.</div>");
					redirect(base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no);
					return;
				}
			} else {
				$this->db->where(array(
					'iop_id' => $this->data['iop_id'],
					'category_id' => $this->data['category_id'],
					'laboratory_id' => $lab_id_i,
					'InActive' => 0
				));
				$this->db->where("(result = '' OR result IS NULL)", null, false);
				$this->db->limit(1);
				$dup = $this->db->get('iop_laboratory')->row();
				if ($dup) {
					$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>A similar laboratory request is already pending.</div>");
					redirect(base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no);
					return;
				}
			}
		}
		$this->db->insert('iop_laboratory', $this->data);
		$io_lab_id = $this->db->insert_id();
		try {
			$this->load->model('app/Nhis_validation_model', 'nhis_validation');
			$nhis_module = ($category_id === $sono_cat) ? 'SONOGRAPHY' : 'LAB';
			$validation = $this->nhis_validation->validate_service($nhis_module, (int)$io_lab_id);
			log_message(
				'debug',
				'[NHIS_VALIDATION] module=' . $nhis_module . ' id=' . (int)$io_lab_id
				. ' valid=' . (!empty($validation['valid']) ? '1' : '0')
				. ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
			);
		} catch (\Throwable $e) {
		}
		$this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $this->session->userdata('user_id'));
		if ($category_id === $sono_cat) {
			$this->laboratory_model->upsert_sonography_request_meta(
				$io_lab_id,
				$particular > 0 ? $particular : null,
				$clinical_question !== '' ? $clinical_question : null,
				'OPD',
				(string)$patient_no,
				(string)$this->input->post('doctor'),
				(string)$this->session->userdata('user_id'),
				$urgency !== '' ? (string)$urgency : null
			);
			$this->laboratory_model->ensure_sonography_charge_posted(
				$io_lab_id,
				(string)$iop_id,
				(string)$patient_no,
				'OPD',
				$particular > 0 ? $particular : null,
				$clinical_question !== '' ? $clinical_question : null,
				(string)$this->session->userdata('user_id')
			);
			log_message('info', 'SONOGRAPHY_REQUEST_CREATED opd iop_id='.$insertData['iop_id'].' io_lab_id='.$io_lab_id);
			General::logfile('Sonography', 'REQUEST', (string)$io_lab_id);
			
			// Create unified service order for sonography
			$this->billing_model->ensure_corporate_billing_schema();
			$sonoName = $laboratory_text ?: 'Sonography Request';
			if ($particular > 0) {
				$bp = $this->db->select('particular_name, charge_amount')->get_where('bill_particular', array('particular_id' => $particular))->row();
				if ($bp) {
					$sonoName = $bp->particular_name;
					$sonoPrice = (float)$bp->charge_amount;
				}
			}
			$this->billing_model->create_service_order_for_request(
				(string)$iop_id,
				(string)$patient_no,
				'SONOGRAPHY',
				$particular > 0 ? $particular : null,
				$sonoName,
				isset($sonoPrice) ? $sonoPrice : 0,
				(string)$this->session->userdata('user_id'),
				'OPD',
				'iop_laboratory',
				$io_lab_id
			);
		} else {
			$this->laboratory_model->ensure_lab_charge_posted(
				$io_lab_id,
				(string)$iop_id,
				(string)$patient_no,
				'OPD',
				$particular,
				$laboratory_text,
				(string)$this->session->userdata('user_id'),
				$labPayerType
			);
			log_message('info', 'LAB_BILL_GENERATED opd io_lab_id='.$io_lab_id.' particular='.$particular.' payer='.$labPayerType);
			
			// Create unified service order for lab test
			$this->billing_model->ensure_corporate_billing_schema();
			$labName = $laboratory_text ?: 'Laboratory Test';
			$labPrice = 0;
			if ($particular > 0) {
				$bp = $this->db->select('particular_name, charge_amount')->get_where('bill_particular', array('particular_id' => $particular))->row();
				if ($bp) {
					$labName = $bp->particular_name;
					$labPrice = (float)$bp->charge_amount;
				}
			}
			$this->billing_model->create_service_order_for_request(
				(string)$iop_id,
				(string)$patient_no,
				'LAB',
				$particular > 0 ? $particular : null,
				$labName,
				$labPrice,
				(string)$this->session->userdata('user_id'),
				'OPD',
				'iop_laboratory',
				$io_lab_id
			);
		}
		log_message('info', 'LAB_REQUEST_CREATED opd iop_id='.$this->data['iop_id'].' io_lab_id='.$io_lab_id);

		$this->billing_model->log_nhis_audit('DOCTOR_SAVE_LAB', 'iop_laboratory', $io_lab_id,
			null, json_encode(array('category' => $category_id, 'particular' => $particular)),
			$this->session->userdata('user_id'), (string)$patient_no, (string)$iop_id);

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Laboratory successfully Saved!</div>");
		redirect(base_url() . 'app/opd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no);
	}

	public function save_procedure()
	{
		$iop_id = url_decode_id($this->input->post('opd_no'));
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'OPD', 'save_procedure')) {
			return;
		}

		if ($this->is_visit_cleared($iop_id)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-lock'></i> Cannot add procedure orders - patient is clinically cleared.</div>");
			redirect(base_url() . 'app/opd/view/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$this->load->model('app/billing_model');
		$gate = $this->billing_model->check_nhis_payment_gate((string)$patient_no, (string)$iop_id, 'PROCEDURE');
		if (!is_array($gate) || (isset($gate['allowed']) && !$gate['allowed'])) {
			$reason = is_array($gate) && isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required before proceeding.';
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> " . htmlspecialchars($reason) . "</div>");
			redirect(base_url() . 'app/opd/procedures/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$procedure_id = (int)$this->input->post('procedure_id');
		$category_id = (int)$this->input->post('category_id');
		$qty = (float)$this->input->post('qty');
		if ($qty <= 0) { $qty = 1; }
		$notes = (string)$this->input->post('notes');
		$actor = (string)$this->session->userdata('user_id');

		if ($procedure_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please select a procedure.</div>");
			redirect(base_url() . 'app/opd/procedures/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$bp = null;
		if ($this->_opd_table_exists('bill_particular', 'bill_particular_schema')) {
			$bp = $this->db->query(
				"SELECT particular_id, particular_name, group_id, charge_amount FROM bill_particular WHERE particular_id = ? AND InActive = 0 LIMIT 1",
				array($procedure_id)
			)->row();
		}
		if (!$bp) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Selected procedure is invalid or inactive.</div>");
			redirect(base_url() . 'app/opd/procedures/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$procedure_name = isset($bp->particular_name) ? (string)$bp->particular_name : 'Procedure';
		$procedure_price = isset($bp->charge_amount) ? (float)$bp->charge_amount : 0.0;
		$category_id = isset($bp->group_id) ? (int)$bp->group_id : $category_id;

		$this->load->model('app/procedure_request_model');
		if ($this->procedure_request_model->exists_pending($iop_id, $procedure_id)) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>A similar procedure request is already pending.</div>");
			redirect(base_url() . 'app/opd/procedures/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		$this->procedure_request_model->ensure_schema();
		$this->db->insert('iop_procedure_request', array(
			'iop_id' => (string)$iop_id,
			'patient_no' => (string)$patient_no,
			'procedure_id' => (int)$procedure_id,
			'procedure_name' => (string)$procedure_name,
			'category_id' => (int)$category_id,
			'qty' => (float)$qty,
			'notes' => (string)$notes,
			'requested_by' => (string)$actor,
			'requested_at' => date('Y-m-d H:i:s'),
			'status' => 'REQUESTED',
			'encounter_type' => 'OPD',
			'InActive' => 0
		));
		$request_id = (int)$this->db->insert_id();
		if ($request_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save procedure request.</div>");
			redirect(base_url() . 'app/opd/procedures/' . url_safe_id($iop_id) . '/' . $patient_no);
			return;
		}

		try {
			$this->load->model('app/billing_transaction_model');
			$payer = $this->billing_model->determine_payer_type((string)$patient_no);
			$item_ref = 'opd_procedure_request_id:' . (int)$request_id;
			$this->billing_transaction_model->create_transaction(array(
				'patient_no' => (string)$patient_no,
				'encounter_id' => (string)$iop_id,
				'encounter_type' => 'OPD',
				'department' => 'OPD',
				'item_type' => 'SERVICE',
				'item_id' => (int)$procedure_id,
				'item_ref' => (string)$item_ref,
				'item_name' => (string)$procedure_name,
				'quantity' => (float)$qty,
				'unit_price' => (float)$procedure_price,
				'payer_type' => (string)$payer,
				'notes' => (string)$notes
			), $actor);
		} catch (\Throwable $e) {
			log_message('error', 'save_procedure billing transaction failed: ' . $e->getMessage());
		}

		try {
			$this->load->model('app/unified_billing_model');
			if (isset($this->unified_billing_model) && method_exists($this->unified_billing_model, 'add_to_billing_queue')) {
				$payer = $this->billing_model->determine_payer_type((string)$patient_no);
				$this->unified_billing_model->add_to_billing_queue(array(
					'iop_id' => (string)$iop_id,
					'patient_no' => (string)$patient_no,
					'item_type' => 'PROCEDURE',
					'item_id' => (string)(int)$procedure_id,
					'item_name' => (string)$procedure_name,
					'unit_price' => (float)$procedure_price,
					'quantity' => (float)$qty,
					'payer_type' => (string)$payer,
					'source_module' => 'PROCEDURE',
					'source_ref' => 'opd_procedure_request:' . (int)$request_id,
					'requested_by' => (string)$actor,
					'idempotency_key' => 'opd_procedure:' . (int)$request_id,
				));
			}
		} catch (\Throwable $e) {
			log_message('error', 'save_procedure billing queue failed: ' . $e->getMessage());
		}

		try {
			$this->load->model('app/encounter_timeline_model');
			$this->encounter_timeline_model->append_event(
				(string)$iop_id,
				(string)$patient_no,
				'OPD',
				'OPD_PROCEDURE_REQUEST',
				'Procedure requested',
				array(
					'request_id' => (int)$request_id,
					'procedure_id' => (int)$procedure_id,
					'procedure_name' => (string)$procedure_name,
					'qty' => (float)$qty,
				),
				(string)$actor
			);
		} catch (\Throwable $e) {
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Procedure request saved.</div>");
		redirect(base_url() . 'app/opd/procedures/' . url_safe_id($iop_id) . '/' . $patient_no);
	}

	public function delete_procedure()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$request_id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		$redirect = base_url() . 'app/opd/procedures/' . url_safe_id($iop_no) . '/' . $patient_no;

		$canEditClinical = false;
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) { $canEditClinical = true; }
		if (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'])   { $canEditClinical = true; }
		if (!$canEditClinical) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Only doctors can remove procedure requests.</div>");
			redirect($redirect);
			return;
		}

		$this->load->model('app/procedure_request_model');
		$this->procedure_request_model->ensure_schema();
		$req = $this->db->get_where('iop_procedure_request', array('request_id' => $request_id, 'InActive' => 0))->row();
		if (!$req || (string)$req->iop_id !== (string)url_decode_id($iop_no)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Procedure request not found or does not belong to this visit.</div>");
			redirect($redirect);
			return;
		}

		$userId = (string)$this->session->userdata('user_id');
		$isAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		$isOwner = (isset($req->requested_by) && (string)$req->requested_by === $userId);
		if (!$isOwner && !$isAdmin) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>You can only remove procedure requests that you ordered.</div>");
			redirect($redirect);
			return;
		}

		$txn = null;
		if ($this->_opd_table_exists('billing_transactions', 'billing_transactions_schema')) {
			$this->db->where(array('InActive' => 0, 'department' => 'OPD', 'item_ref' => 'opd_procedure_request_id:' . (int)$request_id));
			$this->db->limit(1);
			$txn = $this->db->get('billing_transactions')->row();
		}
		if ($txn && ((isset($txn->invoice_no) && trim((string)$txn->invoice_no) !== '') || (isset($txn->paid_amount) && (float)$txn->paid_amount > 0.009))) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cannot remove: procedure is already invoiced/paid.</div>");
			redirect($redirect);
			return;
		}

		$this->db->query("UPDATE iop_procedure_request SET InActive = 1 WHERE request_id = ?", array((int)$request_id));
		if ($txn && isset($txn->txn_id)) {
			$txnId = (int)$txn->txn_id;
			$net = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
			$this->db->query("UPDATE billing_transactions SET InActive = 1 WHERE txn_id = ?", array($txnId));
			if ($net > 0.009 && $this->_opd_table_exists('patient_financial_ledger', 'patient_financial_ledger_schema')) {
				$this->load->model('app/billing_transaction_model');
				$this->billing_transaction_model->create_ledger_entry(array(
					'patient_no' => (string)$patient_no,
					'txn_id' => $txnId,
					'reference_type' => 'VOID',
					'reference_no' => isset($txn->txn_ref) ? (string)$txn->txn_ref : null,
					'description' => 'VOID OPD Procedure: ' . (isset($txn->item_name) ? (string)$txn->item_name : ''),
					'debit_amount' => 0,
					'credit_amount' => $net,
				), $userId);
			}
		}
		if ($this->_opd_table_exists('billing_queue', 'billing_queue_schema')) {
			$this->db->where(array('InActive' => 0, 'source_module' => 'PROCEDURE', 'source_ref' => 'opd_procedure_request:' . (int)$request_id));
			$this->db->update('billing_queue', array('InActive' => 1));
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Procedure request removed successfully.</div>");
		redirect($redirect);
	}

	public function delete_lab()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$io_lab_id  = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		$redirect = base_url() . 'app/opd/laboratory/' . url_safe_id($iop_no) . '/' . $patient_no;

		// Only doctors (clinical users) may delete
		$canEditClinical = false;
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) { $canEditClinical = true; }
		if (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'])   { $canEditClinical = true; }
		if (!$canEditClinical) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Only doctors can remove test requests.</div>");
			redirect($redirect);
			return;
		}

		// Verify the record exists and belongs to this visit
		$this->load->model('app/laboratory_model');
		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab || (string)$lab->iop_id !== (string)url_decode_id($iop_no)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Test request not found or does not belong to this visit.</div>");
			redirect($redirect);
			return;
		}

		// Ownership check: only the requesting doctor or an admin can delete
		$userId = (string)$this->session->userdata('user_id');
		$isAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		$isOwner = (isset($lab->doctor) && (string)$lab->doctor === $userId);
		if (!$isOwner && !$isAdmin) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>You can only remove test requests that you ordered.</div>");
			redirect($redirect);
			return;
		}

		// Check if deletion is allowed (billing guard, results guard)
		$check = $this->laboratory_model->delete_lab_request_with_guard($io_lab_id, $userId);
		if (!is_array($check) || !isset($check['allowed']) || !$check['allowed']) {
			$reason = (is_array($check) && isset($check['reason'])) ? (string)$check['reason'] : 'Unable to remove test request.';
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . htmlspecialchars($reason) . "</div>");
			redirect($redirect);
			return;
		}

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Test request removed successfully.</div>");
		redirect($redirect);
	}


	public function getLabRequests()
	{
		// POST data
		$postData = $this->input->post();

		// Get data
		$data = $this->opd_model->getLabRequests($postData);

		echo json_encode($data);
	}


	public function getDiagnosis()
	{
		// POST data
		$postData = $this->input->post();

		// Get data
		$data = $this->opd_model->getDiagnosis($postData);

		echo json_encode($data);
	}


	public function getMeds()
	{
		// POST data
		$postData = $this->input->post();

		// Get data
		$data = $this->opd_model->getMeds($postData);

		echo json_encode($data);
	}

	/* ================================================================== */
	/*  AJAX SEARCH ENDPOINTS (searchable dropdowns)                       */
	/* ================================================================== */

	public function search_diagnosis_json()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->clinical_workflow_model->search_diagnoses($term);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	public function search_medication_json()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->clinical_workflow_model->search_medications($term);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	public function search_scan_json()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->clinical_workflow_model->search_scans($term);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	public function search_lab_json()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->clinical_workflow_model->search_lab_tests($term);
		header('Content-Type: application/json');
		echo json_encode($results);
	}

	public function patient_history_json()
	{
		$patient_no = trim((string)$this->input->get('patient_no'));
		if ($patient_no === '') {
			header('Content-Type: application/json');
			echo json_encode(array());
			return;
		}
		$history = $this->clinical_workflow_model->get_patient_history($patient_no);
		header('Content-Type: application/json');
		echo json_encode($history);
	}

	/**
	 * Phase 3: AJAX endpoint for real-time prescription safety checking
	 * Called when doctor selects a drug before saving
	 */
	public function check_prescription_safety_json()
	{
		$drug_id = (int)$this->input->get_post('drug_id');
		$patient_no = trim((string)$this->input->get_post('patient_no'));
		$iop_id = trim((string)$this->input->get_post('iop_id'));
		$dose = trim((string)$this->input->get_post('dose'));
		$frequency = trim((string)$this->input->get_post('frequency'));

		header('Content-Type: application/json');

		if ($drug_id <= 0 || $patient_no === '') {
			echo json_encode(array('success' => false, 'message' => 'Invalid parameters'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$alerts = $this->Clinical_decision_support_model->check_prescription_safety(
			$drug_id, $dose, $frequency, $patient_no, $iop_id
		);

		$blocked = $this->Clinical_decision_support_model->should_block_prescription($alerts);
		$highest_severity = $this->Clinical_decision_support_model->get_highest_severity($alerts);

		// Format alerts for JSON response
		$formatted = array();
		foreach ($alerts as $alert) {
			$formatted[] = array(
				'type' => $alert->type,
				'severity' => $alert->severity,
				'message' => $alert->message
			);
		}

		echo json_encode(array(
			'success' => true,
			'blocked' => $blocked,
			'highest_severity' => $highest_severity,
			'alert_count' => count($alerts),
			'alerts' => $formatted
		));
	}

	/**
	 * Phase 3: AJAX endpoint to get patient allergies
	 */
	public function get_patient_allergies_json()
	{
		$patient_no = trim((string)$this->input->get_post('patient_no'));

		header('Content-Type: application/json');

		if ($patient_no === '') {
			echo json_encode(array('success' => false, 'allergies' => array()));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$allergies = $this->Clinical_decision_support_model->get_patient_allergies($patient_no);

		echo json_encode(array(
			'success' => true,
			'count' => count($allergies),
			'allergies' => $allergies
		));
	}

	/**
	 * Phase 3: AJAX endpoint to get patient risk flags
	 */
	public function get_patient_risk_flags_json()
	{
		$patient_no = trim((string)$this->input->get_post('patient_no'));

		header('Content-Type: application/json');

		if ($patient_no === '') {
			echo json_encode(array('success' => false, 'flags' => array()));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$flags = $this->Clinical_decision_support_model->get_patient_risk_flags($patient_no);

		echo json_encode(array(
			'success' => true,
			'count' => count($flags),
			'flags' => $flags
		));
	}

	/**
	 * Phase 3: AJAX endpoint to add patient allergy
	 */
	public function add_patient_allergy_json()
	{
		$patient_no = trim((string)$this->input->post('patient_no'));
		$allergen_type = trim((string)$this->input->post('allergen_type'));
		$allergen_id = (int)$this->input->post('allergen_id');
		$allergen_name = trim((string)$this->input->post('allergen_name'));
		$reaction_type = trim((string)$this->input->post('reaction_type'));
		$reaction_description = trim((string)$this->input->post('reaction_description'));

		header('Content-Type: application/json');

		if ($patient_no === '' || $allergen_name === '') {
			echo json_encode(array('success' => false, 'message' => 'Patient and allergen name are required'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$result = $this->Clinical_decision_support_model->add_patient_allergy(
			$patient_no, $allergen_type ?: 'DRUG', $allergen_id ?: null, $allergen_name,
			$reaction_type ?: 'MODERATE', $reaction_description,
			$this->session->userdata('user_id')
		);

		echo json_encode(array(
			'success' => $result ? true : false,
			'message' => $result ? 'Allergy recorded successfully' : 'Failed to record allergy'
		));
	}

	/**
	 * Close/Discharge an OPD visit - Admin only
	 * Allows admins to close abandoned or completed visits
	 */
	public function close_visit($iop_id = '', $patient_no = '')
	{
		require_role(array('admin', 'receptionist'));
		
		if ($iop_id === '' || $patient_no === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invalid request.</div>");
			redirect(base_url() . 'app/opd/index');
			return;
		}

		$reason = trim((string)$this->input->post('close_reason'));
		$notes = trim((string)$this->input->post('close_notes'));
		
		if ($reason === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Please provide a reason for closing this visit.</div>");
			redirect(base_url() . 'app/opd/view/' . $iop_id . '/' . $patient_no);
			return;
		}

		$result = $this->opd_model->close_opd_visit($iop_id, $patient_no, $reason, $notes, $this->session->userdata('user_id'));
		
		if ($result) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Visit closed successfully.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Failed to close visit.</div>");
		}
		
		redirect(base_url() . 'app/opd/index');
	}

	/**
	 * Bulk close old pending visits - Admin only
	 */
	public function bulk_close_visits()
	{
		require_role('admin');
		
		$days_old = (int)$this->input->post('days_old');
		$reason = trim((string)$this->input->post('bulk_reason'));
		
		if ($days_old < 1) {
			$days_old = 30; // Default to 30 days
		}
		
		if ($reason === '') {
			$reason = 'Auto-closed: Visit older than ' . $days_old . ' days';
		}

		$count = $this->opd_model->bulk_close_old_visits($days_old, $reason, $this->session->userdata('user_id'));
		
		$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> {$count} old visits have been closed.</div>");
		redirect(base_url() . 'app/opd/index');
	}

	/**
	 * Reopen a closed visit - Admin only
	 */
	public function reopen_visit($iop_id = '', $patient_no = '')
	{
		require_role('admin');
		
		if ($iop_id === '' || $patient_no === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invalid request.</div>");
			redirect(base_url() . 'app/opd/index');
			return;
		}

		$result = $this->opd_model->reopen_visit($iop_id, $patient_no, $this->session->userdata('user_id'));
		
		if ($result) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Visit reopened successfully.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Failed to reopen visit.</div>");
		}
		
		redirect(base_url() . 'app/opd/view/' . $iop_id . '/' . $patient_no);
	}

	/**
	 * Get pending visits count for dashboard
	 */
	public function pending_visits_json()
	{
		$stats = $this->opd_model->get_visit_statistics();
		header('Content-Type: application/json');
		echo json_encode($stats);
	}

	/**
	 * Get ICD-10 code details (Phase 2 - Clinical Safety)
	 */
	public function get_diagnosis_json($code = '')
	{
		$code = trim((string)$code);
		
		if (empty($code)) {
			header('Content-Type: application/json');
			echo json_encode(array('error' => 'No code provided'));
			return;
		}
		
		$this->load->model('app/Nhis_claimit_model');
		$result = $this->Nhis_claimit_model->get_icd10_by_code($code);
		
		header('Content-Type: application/json');
		if ($result) {
			echo json_encode($result);
		} else {
			echo json_encode(array('error' => 'Code not found'));
		}
	}

	/* ================================================================== */
	/*  PHASE 3.5: PRESCRIPTION TEMPLATES & ENHANCED CLINICAL INTELLIGENCE */
	/* ================================================================== */

	/**
	 * Get prescription templates for doctor
	 */
	public function get_prescription_templates_json()
	{
		$user_id = $this->session->userdata('user_id');
		$diagnosis_code = trim((string)$this->input->get_post('diagnosis_code'));

		header('Content-Type: application/json');

		$this->load->model('app/Clinical_decision_support_model');
		$templates = $this->Clinical_decision_support_model->get_prescription_templates(
			$user_id, 
			$diagnosis_code ?: null
		);

		echo json_encode(array(
			'success' => true,
			'count' => count($templates),
			'templates' => $templates
		));
	}

	/**
	 * Get template items for a specific template
	 */
	public function get_template_items_json($template_id = 0)
	{
		$template_id = (int)$template_id;

		header('Content-Type: application/json');

		if ($template_id <= 0) {
			echo json_encode(array('success' => false, 'items' => array()));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$items = $this->Clinical_decision_support_model->get_template_items($template_id);

		echo json_encode(array(
			'success' => true,
			'count' => count($items),
			'items' => $items
		));
	}

	/**
	 * Apply prescription template - returns items to populate form
	 */
	public function apply_prescription_template_json()
	{
		$template_id = (int)$this->input->post('template_id');
		$iop_id = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$user_id = $this->session->userdata('user_id');

		header('Content-Type: application/json');

		if ($template_id <= 0) {
			echo json_encode(array('success' => false, 'message' => 'Invalid template'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$items = $this->Clinical_decision_support_model->apply_template($template_id, $iop_id, $patient_no, $user_id);

		if ($items === false) {
			echo json_encode(array('success' => false, 'message' => 'Template not found'));
			return;
		}

		echo json_encode(array(
			'success' => true,
			'count' => count($items),
			'items' => $items
		));
	}

	/**
	 * Validate NHIS prescription compliance
	 */
	public function validate_nhis_prescription_json()
	{
		$drug_id = (int)$this->input->get_post('drug_id');
		$diagnosis_code = trim((string)$this->input->get_post('diagnosis_code'));
		$days = (int)$this->input->get_post('days');
		$quantity = (int)$this->input->get_post('quantity');

		header('Content-Type: application/json');

		if ($drug_id <= 0 || $diagnosis_code === '') {
			echo json_encode(array('success' => true, 'alerts' => array(), 'compliant' => true));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$alerts = $this->Clinical_decision_support_model->validate_nhis_prescription(
			$drug_id, $diagnosis_code, $days, $quantity
		);

		$blocked = false;
		foreach ($alerts as $alert) {
			if ($alert->severity === 'BLOCKED') {
				$blocked = true;
				break;
			}
		}

		echo json_encode(array(
			'success' => true,
			'compliant' => count($alerts) === 0,
			'blocked' => $blocked,
			'alert_count' => count($alerts),
			'alerts' => $alerts
		));
	}

	/**
	 * Check drug contraindications for patient
	 */
	public function check_contraindications_json()
	{
		$drug_id = (int)$this->input->get_post('drug_id');
		$patient_no = trim((string)$this->input->get_post('patient_no'));

		header('Content-Type: application/json');

		if ($drug_id <= 0 || $patient_no === '') {
			echo json_encode(array('success' => true, 'alerts' => array()));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$alerts = $this->Clinical_decision_support_model->check_contraindications($drug_id, $patient_no);

		$blocked = false;
		foreach ($alerts as $alert) {
			if ($alert->severity === 'BLOCKED') {
				$blocked = true;
				break;
			}
		}

		echo json_encode(array(
			'success' => true,
			'blocked' => $blocked,
			'alert_count' => count($alerts),
			'alerts' => $alerts
		));
	}

	/**
	 * Add patient risk flag
	 */
	public function add_patient_risk_flag_json()
	{
		$patient_no = trim((string)$this->input->post('patient_no'));
		$risk_type = trim((string)$this->input->post('risk_type'));
		$severity = trim((string)$this->input->post('severity'));
		$description = trim((string)$this->input->post('description'));

		header('Content-Type: application/json');

		if ($patient_no === '' || $risk_type === '') {
			echo json_encode(array('success' => false, 'message' => 'Patient and risk type are required'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$result = $this->Clinical_decision_support_model->add_patient_risk_flag(
			$patient_no, $risk_type, $severity ?: 'MODERATE', $description,
			$this->session->userdata('user_id')
		);

		echo json_encode(array(
			'success' => $result ? true : false,
			'message' => $result ? 'Risk flag added successfully' : 'Failed to add risk flag'
		));
	}

	/**
	 * Log clinical override when doctor proceeds despite warnings
	 */
	public function log_clinical_override_json()
	{
		$iop_id = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));
		$drug_id = (int)$this->input->post('drug_id');
		$drug_name = trim((string)$this->input->post('drug_name'));
		$alert_type = trim((string)$this->input->post('alert_type'));
		$alert_severity = trim((string)$this->input->post('alert_severity'));
		$alert_message = trim((string)$this->input->post('alert_message'));
		$override_reason = trim((string)$this->input->post('override_reason'));

		header('Content-Type: application/json');

		if ($iop_id === '' || $override_reason === '') {
			echo json_encode(array('success' => false, 'message' => 'IOP ID and override reason are required'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$result = $this->Clinical_decision_support_model->log_clinical_override(array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'drug_id' => $drug_id,
			'drug_name' => $drug_name,
			'alert_type' => $alert_type,
			'alert_severity' => $alert_severity,
			'alert_message' => $alert_message,
			'override_reason' => $override_reason,
			'doctor_id' => $this->session->userdata('user_id')
		));

		echo json_encode(array(
			'success' => $result ? true : false,
			'message' => $result ? 'Override logged successfully' : 'Failed to log override'
		));
	}

	/**
	 * Get patient clinical summary (allergies, risk flags, recent alerts)
	 */
	public function get_patient_clinical_summary_json()
	{
		$patient_no = trim((string)$this->input->get_post('patient_no'));

		header('Content-Type: application/json');

		if ($patient_no === '') {
			echo json_encode(array('success' => false));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		
		$allergies = $this->Clinical_decision_support_model->get_patient_allergies($patient_no);
		$risk_flags = $this->Clinical_decision_support_model->get_patient_risk_flags($patient_no);
		$override_history = $this->Clinical_decision_support_model->get_patient_override_history($patient_no, 10);

		echo json_encode(array(
			'success' => true,
			'patient_no' => $patient_no,
			'allergies' => array(
				'count' => count($allergies),
				'items' => $allergies
			),
			'risk_flags' => array(
				'count' => count($risk_flags),
				'items' => $risk_flags
			),
			'override_history' => array(
				'count' => count($override_history),
				'items' => $override_history
			)
		));
	}

	/**
	 * Phase 3: Real-time prescription safety check AJAX endpoint
	 * Returns comprehensive safety alerts for a drug before prescription is saved
	 */
	public function check_prescription_safety_ajax()
	{
		$drug_id = (int)$this->input->get_post('drug_id');
		$patient_no = trim((string)$this->input->get_post('patient_no'));
		$iop_id = trim((string)$this->input->get_post('iop_id'));
		$dose = trim((string)$this->input->get_post('dose'));
		$frequency = trim((string)$this->input->get_post('frequency'));
		$duration_days = (int)$this->input->get_post('duration_days');
		$diagnosis_code = trim((string)$this->input->get_post('diagnosis_code'));

		header('Content-Type: application/json');

		if ($drug_id <= 0 || $patient_no === '' || $iop_id === '') {
			echo json_encode(array('success' => false, 'message' => 'Drug ID, patient number, and IOP ID are required'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$this->Clinical_decision_support_model->ensure_phase3_enhancements();

		$result = $this->Clinical_decision_support_model->check_prescription_safety_json(
			$drug_id, $dose, $frequency, $duration_days, $patient_no, $iop_id, $diagnosis_code
		);

		echo json_encode($result);
	}

	/**
	 * Phase 3: Force unlock consultation (Admin/Supervisor only)
	 */
	public function force_unlock_consultation_ajax()
	{
		$this->require_role(array('admin', 'supervisor', 'doctor'));

		$iop_id = trim((string)$this->input->post('iop_id'));
		$reason = trim((string)$this->input->post('reason'));

		header('Content-Type: application/json');

		if ($iop_id === '' || $reason === '') {
			echo json_encode(array('success' => false, 'message' => 'IOP ID and reason are required'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$result = $this->Clinical_decision_support_model->force_unlock_consultation(
			$iop_id, $this->session->userdata('user_id'), $reason
		);

		echo json_encode($result);
	}

	/**
	 * Phase 3: Get clinical decision history for a patient
	 */
	public function get_clinical_decision_history_json()
	{
		$patient_no = trim((string)$this->input->get_post('patient_no'));

		header('Content-Type: application/json');

		if ($patient_no === '') {
			echo json_encode(array('success' => false, 'message' => 'Patient number is required'));
			return;
		}

		$this->load->model('app/Clinical_decision_support_model');
		$history = $this->Clinical_decision_support_model->get_patient_decision_history($patient_no, 50);

		echo json_encode(array(
			'success' => true,
			'patient_no' => $patient_no,
			'history' => $history,
			'count' => count($history)
		));
	}

	/**
	 * Phase 3.1: Enhanced prescription safety check with pediatric, cumulative, allergy, black-box
	 */
	public function check_phase31_safety_ajax()
	{
		$drug_id = (int)$this->input->get_post('drug_id');
		$patient_no = trim((string)$this->input->get_post('patient_no'));
		$iop_id = trim((string)$this->input->get_post('iop_id'));
		$dose = trim((string)$this->input->get_post('dose'));
		$frequency = trim((string)$this->input->get_post('frequency'));

		header('Content-Type: application/json');

		if ($drug_id <= 0 || $patient_no === '' || $iop_id === '') {
			echo json_encode(array('success' => false, 'message' => 'Drug ID, patient number, and IOP ID are required'));
			return;
		}

		$this->load->model('app/Clinical_safety_model');
		$result = $this->Clinical_safety_model->check_phase31_safety($drug_id, $dose, $frequency, $patient_no, $iop_id);

		// Log alerts if any
		if (!empty($result['alerts'])) {
			$drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
			$drug_name = $drug ? $drug->drug_name : 'Unknown';
			foreach ($result['alerts'] as $alert) {
				$this->Clinical_safety_model->log_clinical_alert(array(
					'user_id' => $this->session->userdata('user_id'),
					'patient_no' => $patient_no,
					'iop_id' => $iop_id,
					'drug_id' => $drug_id,
					'drug_name' => $drug_name,
					'alert_type' => isset($alert->type) ? $alert->type : 'OTHER',
					'severity' => isset($alert->severity) ? $alert->severity : 'WARNING',
					'message' => isset($alert->message) ? $alert->message : '',
					'requires_supervisor' => isset($alert->requires_supervisor) ? $alert->requires_supervisor : false,
					'calculated' => isset($alert->calculated) ? $alert->calculated : null
				));
			}
		}

		echo json_encode($result);
	}

	/**
	 * Phase 3.1: Update patient weight for pediatric dosing
	 */
	public function update_patient_weight_ajax()
	{
		$patient_no = trim((string)$this->input->post('patient_no'));
		$weight_kg = (float)$this->input->post('weight_kg');

		header('Content-Type: application/json');

		if ($patient_no === '' || $weight_kg <= 0) {
			echo json_encode(array('success' => false, 'message' => 'Patient number and valid weight are required'));
			return;
		}

		$this->load->model('app/Clinical_safety_model');
		$result = $this->Clinical_safety_model->update_patient_weight($patient_no, $weight_kg);

		echo json_encode(array(
			'success' => $result,
			'message' => $result ? 'Weight updated successfully' : 'Failed to update weight',
			'weight_kg' => $weight_kg
		));
	}

	/**
	 * AJAX Queue status — returns today's OPD queue for a doctor (or all doctors)
	 * Used by the OPD index page to auto-refresh without a full page reload.
	 */
	public function queue_status_ajax()
	{
		header('Content-Type: application/json');
		if (!($this->current_user_is_admin() || $this->current_user_is_reception() || $this->current_user_is_doctor())) {
			echo json_encode(array('ok' => false, 'error' => 'Access denied'));
			return;
		}
		$doctor_id = trim((string)$this->input->get('doctor_id'));
		$today     = date('Y-m-d');

		$sql = "SELECT P.IO_ID AS iop_id, P.patient_no, P.doctor_id,
		               COALESCE(W.status,'WAITING') AS status,
		               CONCAT(COALESCE(S.cValue,''),' ',PI.firstname,' ',PI.lastname) AS patient_name,
		               CONCAT(COALESCE(DS.cValue,''),' ',COALESCE(DOC.firstname,''),' ',COALESCE(DOC.lastname,'')) AS doctor_name,
		               P.time_visit
		        FROM patient_details_iop P
		        INNER JOIN patient_personal_info PI ON PI.patient_no = P.patient_no
		        LEFT JOIN iop_opd_workflow W ON W.iop_id = P.IO_ID AND W.InActive = 0
		        LEFT JOIN system_parameters S ON S.param_id = PI.title
		        LEFT JOIN users DOC ON DOC.user_id = P.doctor_id
		        LEFT JOIN system_parameters DS ON DS.param_id = DOC.title
		        WHERE P.date_visit = ? AND P.patient_type = 'OPD' AND P.InActive = 0
		          AND COALESCE(W.status,'WAITING') IN ('WAITING','IN_CONSULTATION','LAB_PENDING','PHARMACY_PENDING','LAB_COMPLETED','PHARMACY_COMPLETED')";
		$params = array($today);

		if ($doctor_id !== '') {
			$sql .= " AND P.doctor_id = ?";
			$params[] = $doctor_id;
		}

		$sql .= " ORDER BY FIELD(COALESCE(W.status,'WAITING'),'IN_CONSULTATION','WAITING','LAB_PENDING','PHARMACY_PENDING','LAB_COMPLETED','PHARMACY_COMPLETED') ASC,
		                   COALESCE(W.waiting_at, P.time_visit) ASC";

		$q = $this->db->query($sql, $params);
		$rows = ($q && $q->num_rows() > 0) ? $q->result() : array();

		// Lightweight, cache-only gate snapshot for UI badges (no SSOT recompute)
		$this->load->library('ConsultationEligibilityService');
		$queue = array();
		foreach ($rows as $r) {
			$gate = null;
			try {
				$gate = $this->consultationeligibilityservice->get_gate_ui_payload((string)$r->iop_id, array('allow_recompute' => false));
			} catch (Throwable $e) {
				$gate = null;
			}
			$queue[] = array(
				'iop_id'       => $r->iop_id,
				'patient_no'   => $r->patient_no,
				'patient_name' => trim($r->patient_name),
				'doctor_name'  => trim($r->doctor_name),
				'status'       => $r->status,
				'time_visit'   => $r->time_visit,
				'gate'         => $gate,
			);
		}

		$busy_doctor_ids = $this->opd_model->get_busy_doctor_ids();
		echo json_encode(array('ok' => true, 'queue' => $queue, 'busy_doctors' => $busy_doctor_ids, 'ts' => time()));
	}

	public function log_safety_override_ajax()
	{
		$log_data = array(
			'user_id' => $this->session->userdata('user_id'),
			'patient_no' => trim((string)$this->input->post('patient_no')),
			'iop_id' => trim((string)$this->input->post('iop_id')),
			'drug_id' => (int)$this->input->post('drug_id'),
			'drug_name' => trim((string)$this->input->post('drug_name')),
			'alert_type' => trim((string)$this->input->post('alert_type')),
			'severity' => trim((string)$this->input->post('severity')),
			'message' => trim((string)$this->input->post('alert_message')),
			'was_overridden' => 1,
			'override_reason' => trim((string)$this->input->post('override_reason'))
		);

		header('Content-Type: application/json');

		$this->load->model('app/Clinical_safety_model');
		$result = $this->Clinical_safety_model->log_clinical_alert($log_data);

		echo json_encode(array(
			'success' => $result ? true : false,
			'message' => $result ? 'Override logged successfully' : 'Failed to log override'
		));
	}

	/**
	 * Phase 3: Return route / form / unit / frequency master data for MedicationModal JS
	 */
	public function get_medication_masters_json()
	{
		header('Content-Type: application/json');

		$routes = array();
		$forms  = array();
		$units  = array();
		$freqs  = array();

		try {
			/* medication_route */
			if ($this->_opd_table_exists('medication_route', 'medication_route_schema')) {
				$rows = $this->db->select('route')->where('is_active', 1)->order_by('route')->get('medication_route')->result();
				foreach ($rows as $r) { $routes[] = array('route' => $r->route); }
			}

			/* medication_form */
			if ($this->_opd_table_exists('medication_form', 'medication_form_schema')) {
				$rows = $this->db->select('form')->where('is_active', 1)->order_by('form')->get('medication_form')->result();
				foreach ($rows as $r) { $forms[] = array('form' => $r->form); }
			}

			/* medication_unit — actual column is 'unit' (not unit_name/abbreviation) */
			if ($this->_opd_table_exists('medication_unit', 'medication_unit_schema')) {
				$rows = $this->db->select('unit')->where('is_active', 1)->order_by('sort_order')->get('medication_unit')->result();
				foreach ($rows as $r) { $units[] = array('unit' => $r->unit); }
			}

			/* medication_frequency — actual columns are 'code', 'label', 'doses_per_day' */
			if ($this->_opd_table_exists('medication_frequency', 'medication_frequency_schema')) {
				$rows = $this->db->select('code, label, doses_per_day')->where('is_active', 1)->order_by('sort_order')->get('medication_frequency')->result();
				foreach ($rows as $r) {
					$freqs[] = array(
						'code'          => $r->code,
						'label'         => $r->label,
						'doses_per_day' => (float)$r->doses_per_day
					);
				}
			}
		} catch (Exception $e) {
			/* Graceful — JS has built-in fallbacks */
		}

		/* Fallbacks when Phase 2 tables are empty / not yet created */
		if (empty($routes)) {
			foreach (array('Oral','IV','IM','Subcutaneous','Topical','Inhalation','Sublingual','Rectal','Intranasal') as $v) {
				$routes[] = array('route' => $v);
			}
		}
		if (empty($forms)) {
			foreach (array('Tablet','Capsule','Syrup','Injection','Suspension','Cream','Ointment','Drops','Inhaler','Patch') as $v) {
				$forms[] = array('form' => $v);
			}
		}
		if (empty($units)) {
			foreach (array('mg','g','mcg','ml','tablet','capsule','drop','puff','IU') as $v) {
				$units[] = array('unit' => $v);
			}
		}

		echo json_encode(array(
			'success'     => true,
			'routes'      => $routes,
			'forms'       => $forms,
			'units'       => $units,
			'frequencies' => $freqs
		));
	}
}
