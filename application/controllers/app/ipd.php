<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Ipd extends General{

	private $limit = 10;

	private function _ipd_table_exists($table, $schema_flag = null)
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

	public function __construct(){
		parent::__construct();
		$this->load->model("app/ipd_model");
		$this->load->model("app/opd_model");
		$this->load->model("app/billing_model");
		$this->load->model("app/encounter_owner_model");
		$this->load->model("general_model");
		$this->load->model("app/patient_model");
		$this->load->model("app/clinical_history_model");
		$this->load->model("app/Medication_dictionary_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		if (!$this->session->userdata('_schema_ipd_ok')) {
			$this->Medication_dictionary_model->ensure_dictionary_schema();
			$this->session->set_userdata('_schema_ipd_ok', 1);
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
			$allowed = array('index','registration','admit','admit_patient','admit_patient2','getRoomMaster','getBeds','getRoomMaster2','getBeds2','validate_ipd','save_ipd','view','discharge');
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

	/**
	 * Ensure IPD sub-pages are always opened with a valid encounter context.
	 *
	 * Many IPD views share a "patient sidebar" that assumes both $getOPDPatient and
	 * $patientInfo are non-null objects. If a user opens a URL like
	 * /app/ipd/progress_note without the required segments, CI will still render the
	 * view but the models return null, causing PHP warnings ("Attempt to read property
	 * ... on null").
	 *
	 * This helper enforces:
	 * 1) Required params exist
	 * 2) Encounter exists
	 * 3) URL patient_no matches encounter patient_no (canonical redirect)
	 * 4) Patient exists
	 *
	 * @return array [bool $ok, object|null $encounter, object|null $patient]
	 */
	private function require_ipd_context_or_redirect($iop_no, $patient_no, $action_label = 'ipd_context'){
		$iop_no = trim((string)$iop_no);
		$patient_no = trim((string)$patient_no);

		if ($iop_no === '' || $patient_no === '') {
			$this->session->set_flashdata(
				'message',
				"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please open this page from an In-Patient record (missing visit context).</div>"
			);
			redirect(base_url().'app/ipd');
			return array(false, null, null);
		}

		$enc = $this->opd_model->getOPDPatient($iop_no);
		if (!$enc) {
			$this->session->set_flashdata(
				'message',
				"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Visit not found. Please re-open the patient admission and try again.</div>"
			);
			redirect(base_url().'app/ipd');
			return array(false, null, null);
		}

		$encPatientNo = isset($enc->patient_no) ? trim((string)$enc->patient_no) : '';
		if ($encPatientNo !== '' && $patient_no !== $encPatientNo) {
			// Canonicalize incorrect URLs to avoid null lookups and accidental cross-patient access.
			redirect(base_url().'app/ipd/'.(string)$action_label.'/'.url_safe_id($iop_no).'/'.$encPatientNo);
			return array(false, null, null);
		}

		$patient = $this->patient_model->getPatientInfo($encPatientNo !== '' ? $encPatientNo : $patient_no);
		if (!$patient) {
			$this->session->set_flashdata(
				'message',
				"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient record not found for this visit.</div>"
			);
			redirect(base_url().'app/ipd/view/'.url_safe_id($iop_no).'/'.$patient_no);
			return array(false, null, null);
		}

		return array(true, $enc, $patient);
	}

	private function ensure_ipd_medication_rx_schema()
	{
		try {
			$this->load->model('app/Prescription_engine_model');
			if (isset($this->Prescription_engine_model) && method_exists($this->Prescription_engine_model, 'ensure_phase4_schema')) {
				$this->Prescription_engine_model->ensure_phase4_schema();
			}
		} catch (\Throwable $e) {
			log_message('error', 'IPD Rx schema prescription engine: ' . $e->getMessage());
		}

		if ($this->_ipd_table_exists('iop_medication', 'iop_medication_schema') && !$this->db->field_exists('rx_number', 'iop_medication')) {
			$dbg = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($dbg !== null) { $this->db->db_debug = false; }
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `rx_number` VARCHAR(50) NULL DEFAULT NULL");
			if ($dbg !== null) { $this->db->db_debug = $dbg; }
		}
	}

	private function log_ipd_rx_generation_failed($iop_med_id, $patient_no, $iop_id, $error, $doctor_id)
	{
		log_message('error', 'IPD_RX_GENERATION_FAILED iop_med_id=' . (int)$iop_med_id . ' err=' . (string)$error);
		try {
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'log_audit')) {
				$this->billing_transaction_model->log_audit(array(
					'action' => 'IPD_RX_GENERATION_FAILED',
					'table_name' => 'iop_medication',
					'record_id' => (int)$iop_med_id,
					'patient_no' => (string)$patient_no,
					'description' => (string)$error,
					'visit_id' => (string)$iop_id,
					'visit_type' => 'IPD'
				), $doctor_id);
			}
		} catch (\Throwable $e) {
			log_message('error', 'IPD_RX_GENERATION_FAILED audit log failed: ' . $e->getMessage());
		}
	}
	
	public function index($offset = 0){
		
		// user restriction function
				$this->session->set_userdata('page_name','ipd_enquiry');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					if (!has_role(array('doctor', 'nurse', 'receptionist', 'cashier'))) {
						redirect(base_url().'access_denied');
					}
				}
				// end of user restriction function		
				
				 
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'',
				 'subtab'		=>		'ipd',
				 'submodule'	=>		'ipd_master'));
		
		//set session in textfield to paginate the table		 
		$this->session->set_userdata(array(
			'search_ipd'				=>		$this->input->post('search'),
			'search_ipd_cFrom'			=>		$this->input->post('cFrom'),
			'search_ipd_cTo'			=>		$this->input->post('cTo'),
			'search_ipd_department'		=>		$this->input->post('department'),
			'search_ipd_doctor'			=>		$this->input->post('doctor'),
			'search_ipd_insurance'			=>		$this->input->post('insurance')
		));		 
				 
		// $uri_segment = 4;
		// $offset = $this->uri->segment($uri_segment);
		
		$patient = $this->ipd_model->getAll($this->limit, $offset);
		
		// $config['base_url'] = base_url().'app/opd/search_result/';
 		// $config['total_rows'] = $this->ipd_model->count_all();
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
	
		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
        $this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('IPD No','Patient No','Patient Name', 'Insurance','Date Admit','Department','Room & Bed No.','Incharge Doctor','Status');
		$i = (int)$offset;
		
		
		foreach ($patient as $patient)
		{	
				if($patient->nStatus == "Pending"){ 
					$nStatus = "Checked In";
					$discharge = anchor('app/billing/final_clearance/'.url_safe_id($patient->IO_ID).'/'.$patient->patient_no,'Final Clearance',array('onclick'=>"return confirm('Perform Final System Clearance? This will only succeed if discharge summary, medication clearance, and billing are complete.');"));
				}else{ 
					$nStatus = "Discharged";
					$discharge = "Discharged";
				}

				
			// Single source of truth: use determine_payer_type() from billing_model
				$payerType = $this->billing_model->determine_payer_type($patient->patient_no);
				if ($payerType === 'NHIS') {
					$insurance = '<span class="label label-success">NHIS</span>';
				} else {
					$insurance = '<span class="label label-default">CASH</span>';
				}

				$iop_safe = str_replace(' ', '-', $patient->IO_ID);
				$pno_safe = str_replace(' ', '-', $patient->patient_no);
				$this->table->add_row( 
									// $patient->IO_ID,
									// $patient->patient_no,
									anchor('app/opd/view/'.$iop_safe.'/'.$pno_safe,$patient->IO_ID),
									anchor('app/patient/view/'.$pno_safe,$patient->patient_no),
									$patient->name, 
									$insurance, 
									date('M d, Y',strtotime($patient->date_visit))." ".date('H:i:s',strtotime($patient->time_visit)), 
									$patient->dept_name, 
									"Rm ".$patient->room_name." Bed No.".$patient->bed_name, 
									$patient->doctor,
									$nStatus,
									$discharge
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/ipd/index',$this->data);	
	}
	
	public function discharge(){
		// Use segment_decoded for automatic URL decoding (handles IP%20000002 -> IP 000002)
		$iop_no = $this->segment_decoded(4);
		$patient_no = $this->segment_decoded(5);

		$iop_no = (string)$iop_no;
		$patient_no = (string)$patient_no;
		if ($iop_no === '' || $patient_no === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to discharge: missing patient context.</div>");
			redirect(base_url().'app/ipd/index');
			return;
		}
		redirect(base_url().'app/billing/final_clearance/'.url_safe_id($iop_no).'/'.$patient_no);
	}
	
	public function registration(){
		
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'',
				 'subtab'		=>		'ipd',
				 'submodule'	=>		'ipd_registration'));

		$this->data['admission_queue'] = $this->opd_model->get_admission_queue(50);
		$this->data['message']         = $this->session->flashdata('message');
		$this->load->view("app/ipd/registration",$this->data);	
	}

	public function mark_admitted_ajax()
	{
		header('Content-Type: application/json');
		$queue_id = (int)$this->input->post('queue_id');
		$user_id  = (string)$this->session->userdata('user_id');
		if ($queue_id < 1) {
			echo json_encode(array('ok' => false, 'error' => 'Invalid queue_id'));
			return;
		}
		$this->opd_model->mark_admission_assigned($queue_id, $user_id);
		echo json_encode(array('ok' => true));
	}
	
	public function admit(){
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'',
				 'subtab'		=>		'ipd',
				 'submodule'	=>		'ipd_registration'));
		
		// Use segment_decoded for automatic URL decoding
		$patient_no = $this->segment_decoded(4);
		
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['room_category'] = $this->general_model->room_category();
		$this->data['lastIPDNo'] = $this->general_model->lastIPDNo();
		
		$this->load->view("app/ipd/admit",$this->data);		
	}

	public function getRoomMaster(){
		// Room type is numeric, no decoding needed
		$roomType = (int)$this->uri->segment(4);
		$occupied = $this->uri->segment(5);	
		
		$this->data['getRoomMaster'] = $this->ipd_model->getRoomMaster($roomType,$occupied);
		$this->load->view("app/ipd/getRoomMaster",$this->data);
		
	}
	
	public function getBeds($room_id){
		
		$this->data['getBeds'] = $this->general_model->getBeds($room_id);
		$this->load->view("app/ipd/getBeds",$this->data);
		
	}
	
	public function getRoomMaster2(){
		// Room type is numeric, no decoding needed
		$roomType = (int)$this->uri->segment(4);
		$occupied = $this->uri->segment(5);	
		
		$this->data['getRoomMaster'] = $this->ipd_model->getRoomMaster($roomType,$occupied);
		$this->load->view("app/ipd/getRoomMaster2",$this->data);
		
	}
	
	public function getBeds2($room_id){
		
		$this->data['getBeds'] = $this->general_model->getBeds($room_id);
		$this->load->view("app/ipd/getBeds2",$this->data);
		
	}
	
	public function validate_ipd(){
		if($this->ipd_model->validate_ipd()){
			$this->form_validation->set_message("validate_ipd","IPD Patient Already Exists.");
			return false;
		}else{
			return true;
		}	
	}
	
	public function save_ipd(){
		$this->form_validation->set_rules("patient_no","Patient No.","trim|xss_clean|required|callback_validate_ipd");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		if($this->form_validation->run()){
			$patient_no = (string)$this->input->post('patient_no');
			$this->load->model('app/bed_occupancy_model');
			$res = $this->bed_occupancy_model->admit_ipd_from_post();
			if (!is_array($res) || !isset($res['ok']) || $res['ok'] !== true) {
				$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'unknown';
				$msg = ($err === 'bed_not_found')
					? 'Unable to admit: bed not found.'
					: (($err === 'bed_occupied') ? 'Unable to admit: bed is already occupied.' : 'Admission could not be completed.');
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$msg}</div>");
				redirect(base_url().'app/ipd/admit/'.$patient_no);
				return;
			}

			$assignedDoctor = (string)$this->input->post('doctor');
			if ($assignedDoctor !== '') {
				$this->encounter_owner_model->assign_owner(
					(string)$this->input->post('iopNo'),
					(string)$this->input->post('patient_no'),
					'IPD',
					$assignedDoctor,
					(string)$this->session->userdata('user_id'),
					'ipd_registration',
					null
				);
			}
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>IPD Patient successfully saved!</div>");
			
			//redirect
			redirect(base_url().'app/ipd/index',$this->data);
			
			
		}else{
			redirect(base_url().'app/ipd/admit/'.$this->input->post('patient_no'));
		}		
	}
	
	public function delete_diagnos(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		$iop_no = $this->segment_decoded(5);
		$patient_no = $this->segment_decoded(6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_diagnos')) {
			return;
		}
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Diagnosis successfully Added!</div>");
		
		$this->db->query("UPDATE iop_diagnosis SET InActive = 1 WHERE iop_diag_id = ?", array((int)$id));
		
		redirect(base_url().'app/ipd/diagnosis/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function view(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		$req_patient_no = (string)$patient_no;

		$this->load->library('EncounterContext');
		$ctx = $this->encountercontext->resolve('IPD', $iop_no, $patient_no, array(
			'include_billing' => false,
			'include_vitals' => false,
			'include_timeline' => true,
			'timeline_limit' => 30,
			'log_non_owner_view' => true,
			'hasAccesstoDoctor' => (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) ? true : false,
			'role_id' => (isset($this->data['userInfo']) && isset($this->data['userInfo']->user_role)) ? (string)$this->data['userInfo']->user_role : '',
		));
		if (!$ctx || !isset($ctx['ok']) || !$ctx['ok']) {
			if (isset($ctx['canonical_redirect_url']) && $ctx['canonical_redirect_url']) {
				redirect($ctx['canonical_redirect_url']);
				return;
			}
			$code = isset($ctx['error_code']) ? (string)$ctx['error_code'] : '';
			if ($code === 'PATIENT_NOT_FOUND') {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient record not found for this encounter.</div>");
				redirect(base_url() . 'app/ipd');
				return;
			}
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>IPD encounter not found.</div>");
			redirect(base_url() . 'app/ipd');
			return;
		}

		if (isset($ctx['canonical_redirect_url']) && $ctx['canonical_redirect_url']) {
			$resolvedPatient = isset($ctx['patient_no']) ? (string)$ctx['patient_no'] : '';
			if ($req_patient_no === '' || ($resolvedPatient !== '' && $resolvedPatient !== $req_patient_no)) {
				redirect($ctx['canonical_redirect_url']);
				return;
			}
		}

		$iop_no = $ctx['iop_no'];
		$patient_no = $ctx['patient_no'];
		if (isset($ctx['data']) && is_array($ctx['data'])) {
			foreach ($ctx['data'] as $k => $v) {
				$this->data[$k] = $v;
			}
		}

		$this->data['message'] = $this->session->flashdata('message');
		
		$this->load->view("app/ipd/view",$this->data);	
	}
	
	public function diagnosis(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		
		$this->data['diagnosisList'] = $this->ipd_model->diagnosisList($iop_no);
		$this->data['patientDiagnosis'] = $this->ipd_model->patientDiagnosis($iop_no);
		
		
		$this->load->view("app/ipd/diagnosis",$this->data);	
	}
	
	
	
	
	
	
	
	public function ipd_reg($patient_no){
		$this->data['lastOPDNo'] = $this->general_model->lastOPDNo();
		$this->data['message'] = "";
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->load->view("app/opd/opdReg",$this->data);	
	}
	
	public function admit_patient2($offset = 0){
		// user restriction function
				$this->session->set_userdata('page_name','ipd_registration');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function			
				
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'',
				 'subtab'		=>		'ipd',
				 'submodule'	=>		'ipd_registration'));	
		
		$patient = $this->opd_model->getAll_search($this->limit, $offset);
		
		$config['base_url'] = base_url().'app/opd/search_result/';
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
		$this->table->set_heading('Patient No', 'Patient Name','Gender','Civil Status','Age','Date Entry','Action');
		$i = (int)$offset;
		
		
		foreach ($patient as $patient)
		{	
				$this->table->add_row( 
									anchor('app/patient/view/'.$patient->patient_no,$patient->patient_no),
									$patient->name, 
									$patient->gender, 
									$patient->civil_status, 
									$patient->age,
									date('M d, Y H:i:s',strtotime($patient->date_entry)), 
									anchor('app/opd/opd_reg/'.$patient->patient_no,'OPD Visit')
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/opd/search_result',$this->data);	
	}

	
	
	public function admit_patient($offset = 0){
		// user restriction function
				$this->session->set_userdata('page_name','ipd_registration');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function		
				
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'',
				 'subtab'		=>		'ipd',
				 'submodule'	=>		'ipd_registration'));	
				 
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$patient = $this->opd_model->getAll_search($this->limit, $offset);
		
		$config['base_url'] = base_url().'app/ipd/admit_patient/';
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
		$this->table->set_heading('Patient No', 'Patient Name','Gender','Civil Status','Age','Date Entry','Admit');
		$i = (int)$offset;
		
		
		foreach ($patient as $patient)
		{	
				$this->table->add_row( 
									anchor('app/patient/view/'.$patient->patient_no,$patient->patient_no),
									$patient->name, 
									$patient->gender, 
									$patient->civil_status, 
									$patient->age,
									date('M d, Y H:i:s',strtotime($patient->date_entry)), 
									anchor('app/ipd/admit/'.$patient->patient_no,'Admit')
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/ipd/search_result',$this->data);	
	}
	
	public function save_diagnosis(){
		$this->form_validation->set_rules("diagnosis","Diagnosis","trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		$opd_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_diagnosis')) {
			return;
		}
		
		if($this->form_validation->run()){
			
			$this->opd_model->save_diagnosis();
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Diagnosis successfully Added!</div>");
			
			//redirect
			redirect(base_url().'app/ipd/diagnosis/'.$opd_no.'/'.$patient_no,$this->data);
			
			
		}else{
			redirect(base_url().'app/ipd/diagnosis/'.$opd_no.'/'.$patient_no,$this->data);
		}		
	}

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
			$iop_id = url_decode_id($opd_no);

			if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'IPD', 'save_diagnosis_batch', true)) {
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
				'redirect' => base_url() . 'app/ipd/diagnosis/' . url_safe_id($iop_id) . '/' . $patient_no
			));
		} catch (Exception $e) {
			echo json_encode(array('success' => false, 'message' => 'Server error: ' . $e->getMessage()));
		}
	}
	
	public function medication(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		
		$this->data['message'] = $this->session->flashdata('message');
		
		$this->data['medicineCategory'] = $this->opd_model->medicineCategory();
		
		
		$this->data['patientMedication'] = $this->opd_model->patientMedication($iop_no);
		
		
		$this->load->view("app/ipd/medication",$this->data);	
	}
	
	public function save_medication(){
		$opd_no_raw = $this->input->post('opd_no');
		$patient_no = trim((string)$this->input->post('patient_no'));
		if (!$this->doctor_write_allowed_or_redirect($opd_no_raw, $patient_no, 'IPD', 'save_medication')) {
			return;
		}

		// Step 1 — Decode URL-safe ID (CRITICAL: stores correct visit ID)
		$iop_id    = url_decode_id($opd_no_raw);
		$doctor_id = (string)$this->session->userdata('user_id');
		$drug_id   = (int)$this->input->post('drug_name');
		$qty       = max(1, (int)$this->input->post('qty'));

		// Step 2 — NHIS payment gate (once per request)
		$gate = $this->billing_model->check_nhis_payment_gate($patient_no, $iop_id, 'PHARMACY');
		if (!$gate['allowed']) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> " . htmlspecialchars($gate['reason']) . "</div>");
			redirect(base_url() . 'app/ipd/medication/' . $opd_no_raw . '/' . $patient_no);
			return;
		}
		$payer = $this->billing_model->determine_payer_type($patient_no);

		// Step 3 — NHIS formulary check (non-blocking warning)
		$warningHtml = '';
		if ($payer === 'NHIS' && $drug_id > 0) {
			try {
				$cov = $this->billing_model->check_drug_nhis_coverage($drug_id);
				if (!empty($cov['found']) && empty($cov['is_nhis_covered'])) {
					$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-exclamation-triangle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>NHIS Notice:</strong> " . htmlspecialchars($cov['drug_name']) . " is not on the NHIS formulary (cash price applies).</div>";
				}
			} catch (Exception $e) {
				log_message('error', 'IPD NHIS formulary check non-blocking: ' . $e->getMessage());
			}
		}

		// Step 4 — CDS safety check (BLOCKED severity stops save; others are warnings)
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
					redirect(base_url() . 'app/ipd/medication/' . $opd_no_raw . '/' . $patient_no);
					return;
				}

				foreach ($alerts as $a) {
					$warningHtml .= "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Safety Alert — " . htmlspecialchars($a->type) . ':</strong> ' . htmlspecialchars($a->message) . "</div>";
				}
			} catch (Exception $e) {
				log_message('error', 'IPD CDS check non-blocking: ' . $e->getMessage());
			}
		}

		// Step 5 — Hoist schema checks (no per-column SHOW COLUMNS inside loop)
		$this->ensure_ipd_medication_rx_schema();
		$has_prescribed  = $this->db->field_exists('prescribed_by',     'iop_medication');
		$has_disp_status = $this->db->field_exists('dispensing_status', 'iop_medication');
		$has_pay_status  = $this->db->field_exists('payment_status',    'iop_medication');
		$has_frequency   = $this->db->field_exists('frequency',         'iop_medication');
		$has_rx_number   = $this->db->field_exists('rx_number',         'iop_medication');
		$has_unit        = $this->db->field_exists('unit',              'iop_medication');
		$has_freq_code   = $this->db->field_exists('freq_code',         'iop_medication');
		$has_route       = $this->db->field_exists('route',             'iop_medication');
		$has_drug_form   = $this->db->field_exists('drug_form',         'iop_medication');
		$has_nhis_covered= $this->db->field_exists('is_nhis_covered',   'iop_medication');
		$has_is_prn      = $this->db->field_exists('is_prn',            'iop_medication');
		$has_is_urgent   = $this->db->field_exists('is_urgent',         'iop_medication');
		$has_prescribed_dose_value  = $this->db->field_exists('prescribed_dose_value',  'iop_medication');
		$has_prescribed_dose_unit   = $this->db->field_exists('prescribed_dose_unit',   'iop_medication');
		$has_strength_per_unit_value= $this->db->field_exists('strength_per_unit_value','iop_medication');
		$has_strength_per_unit_unit = $this->db->field_exists('strength_per_unit_unit', 'iop_medication');
		$has_required_units         = $this->db->field_exists('required_units',         'iop_medication');
		$has_total_active_mass_value= $this->db->field_exists('total_active_mass_value','iop_medication');
		$has_total_active_mass_unit = $this->db->field_exists('total_active_mass_unit', 'iop_medication');
		$has_prescribed_qty         = $this->db->field_exists('prescribed_qty',         'iop_medication');

		$insert = array(
			'iop_id'        => $iop_id,
			'medicine_id'   => $drug_id ?: null,
			'medicine_text' => strip_tags(trim((string)$this->input->post('medicine_text'))),
			'instruction'   => strip_tags(trim((string)$this->input->post('instruction'))),
			'advice'        => strip_tags(trim((string)$this->input->post('advice'))),
			'days'          => max(1, (int)$this->input->post('nDays')),
			'total_qty'     => max(0.01, (float)$this->input->post('qty')),
			'dosage'        => strip_tags(trim((string)$this->input->post('dosage'))),
			'cPreparedBy'   => $doctor_id,
			'dDate'         => date('Y-m-d H:i:s'),
			'InActive'      => 0,
		);
		if ($has_prescribed)  $insert['prescribed_by']     = $doctor_id;
		if ($has_disp_status) $insert['dispensing_status'] = 'PENDING';
		if ($has_pay_status)  $insert['payment_status']    = 'PENDING';
		if ($has_frequency) {
			$freq = strip_tags(trim((string)$this->input->post('frequency')));
			if ($freq !== '') $insert['frequency'] = $freq;
		}
		if ($has_rx_number) $insert['rx_number'] = null;
		/* Phase 4 fields */
		if ($has_unit)        $insert['unit']                   = strip_tags(trim((string)$this->input->post('unit')));
		if ($has_freq_code)   $insert['freq_code']              = strip_tags(trim((string)$this->input->post('freq_code')));
		if ($has_route)       $insert['route']                  = strip_tags(trim((string)$this->input->post('route')));
		if ($has_drug_form)   $insert['drug_form']              = strip_tags(trim((string)$this->input->post('drug_form')));
		if ($has_nhis_covered)$insert['is_nhis_covered']         = (int)$this->input->post('is_nhis_covered');
		if ($has_is_prn)      $insert['is_prn']                  = (int)$this->input->post('is_prn');
		if ($has_is_urgent)   $insert['is_urgent']               = (int)$this->input->post('is_urgent');
		/* Structured strength fields */
		if ($has_prescribed_dose_value) $insert['prescribed_dose_value']   = (float)$this->input->post('dosage');
		if ($has_prescribed_dose_unit)  $insert['prescribed_dose_unit']    = strip_tags(trim((string)$this->input->post('unit')));
		if ($has_strength_per_unit_value)$insert['strength_per_unit_value'] = (float)$this->input->post('strength_per_unit_value');
		if ($has_strength_per_unit_unit) $insert['strength_per_unit_unit'] = strip_tags(trim((string)$this->input->post('strength_per_unit_unit')));
		if ($has_required_units)        $insert['required_units']          = (float)$this->input->post('required_units');
		if ($has_total_active_mass_value)$insert['total_active_mass_value'] = (float)$this->input->post('total_active_mass_value');
		if ($has_total_active_mass_unit) $insert['total_active_mass_unit'] = strip_tags(trim((string)$this->input->post('total_active_mass_unit')));
		/* prescribed_qty population under feature flag */
		$useDecimalPrescriptions = $this->config->item('ENABLE_DECIMAL_PRESCRIPTIONS') || $this->config->item('ENABLE_DECIMAL_PRESCRIBED_QTY');
		if ($has_prescribed_qty) {
			$requiredUnits = (float)$this->input->post('required_units');
			$totalQty = (float)$this->input->post('qty');
			if ($useDecimalPrescriptions) {
				$insert['prescribed_qty'] = $requiredUnits > 0 ? $requiredUnits : $totalQty;
			} else {
				$insert['prescribed_qty'] = (int)round($totalQty);
			}
		}

		$this->db->insert('iop_medication', $insert);
		$new_id = $this->db->insert_id();

		if (!$new_id) {
			log_message('error', 'IPD_MEDICATION_INSERT_FAILED: iop_id=' . $iop_id . ' ' . json_encode($this->db->error()));
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-times'></i> Failed to save medication. Please try again.</div>");
			redirect(base_url() . 'app/ipd/medication/' . $opd_no_raw . '/' . $patient_no);
			return;
		}

		// Step 6 — Both billing queues + NHIS audit (non-blocking)
		try {
			$rx_no = $this->Prescription_engine_model->generate_prescription_no();
			if ($this->db->field_exists('prescription_no', 'iop_medication')) {
				$this->Prescription_engine_model->stamp_prescription_no($new_id, $rx_no);
			}
			if ($this->db->field_exists('rx_number', 'iop_medication')) {
				$this->db->where('iop_med_id', (int)$new_id);
				$this->db->update('iop_medication', array('rx_number' => $rx_no));
			}
		} catch (\Throwable $e) {
			$this->log_ipd_rx_generation_failed($new_id, $patient_no, $iop_id, $e->getMessage(), $doctor_id);
		}

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
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->load->model('app/pharmacy_model');
				$this->pharmacy_model->create_or_update_pharmacy_bill($new_id, $doctor_id);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'IPD pharmacy_bill non-blocking: ' . $e->getMessage());
			}

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
					'item_name'     => $drug_name !== '' ? $drug_name : 'Medication',
					'unit_price'    => $drugPrice,
					'quantity'      => $qty,
					'payer_type'    => $payer,
					'source_module' => 'PHARMACY',
					'source_ref'    => 'iop_id:' . (string)$iop_id . ':iop_medication:' . (string)(int)$new_id,
					'requested_by'  => $doctor_id,
				));
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'IPD unified_billing non-blocking: ' . $e->getMessage());
			}
		}

		// NHIS audit log
		try {
			$_dbg = $this->db->db_debug; $this->db->db_debug = false;
			$this->billing_model->log_nhis_audit(
				'IPD_SAVE_MEDICATION', 'iop_medication', $new_id,
				null, json_encode(array('drug_id' => $drug_id, 'qty' => $qty)),
				$doctor_id, $patient_no, $iop_id
			);
			$this->db->db_debug = $_dbg;
		} catch (Exception $e) {
			$this->db->db_debug = isset($_dbg) ? $_dbg : true;
			log_message('error', 'IPD nhis_audit non-blocking: ' . $e->getMessage());
		}

		// CDS workflow tracking
		if ($cdsLoaded && $drug_id > 0) {
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->Clinical_decision_support_model->init_prescription_workflow(
					$new_id, $iop_id, $patient_no, $doctor_id
				);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'IPD cds_workflow non-blocking: ' . $e->getMessage());
			}
		}

		$successHtml = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Medication successfully Added!</div>";
		$this->session->set_flashdata('message', $warningHtml . $successHtml);

		redirect(base_url() . 'app/ipd/medication/' . $opd_no_raw . '/' . $patient_no);
	}

	/**
	 * Batch save medications — AJAX endpoint for the unified medication modal (IPD).
	 *
	 * Replicates the OPD save_medication_batch logic with correct IPD context:
	 *   - RBAC: encounter_type = 'IPD'
	 *   - Billing audit: context = 'IPD_BATCH'
	 *   - Redirect: app/ipd/medication/...
	 *   - Prescription Engine: Rx numbers + NHIS queue routing
	 *
	 * OPD's _post_save_billing_triggers() and _run_prescription_validation() are
	 * private — their 4-step billing sequence and validation logic are replicated
	 * inline here (verified from OPD L3274-3497).
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

		if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_medication_batch', true)) {
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

		// ── Prescription validation (inline — mirrors OPD _run_prescription_validation) ──

		// Build medication array for validation
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

		// Load models for validation
		$this->load->model('app/billing_model');
		$cdsLoaded = false;
		try {
			$this->load->model('app/Clinical_decision_support_model');
			$this->Clinical_decision_support_model->ensure_phase3_enhancements();
			$cdsLoaded = true;
		} catch (Exception $e) {
			log_message('error', 'IPD batch CDS model load: ' . $e->getMessage());
		}

		// NHIS payment gate — checked ONCE for the entire batch
		$gate = $this->billing_model->check_nhis_payment_gate($patNo, $iop_id, 'PHARMACY');
		if (!$gate['allowed']) {
			echo json_encode(array(
				'status'     => 'nhis_block',
				'success'    => false,
				'saved'      => 0,
				'blocked'    => 0,
				'warnings'   => array(),
				'message'    => 'Payment Required: ' . $gate['reason'],
				'nhis_block' => true,
			));
			return;
		}
		$payer = $this->billing_model->determine_payer_type($patNo);

		// Hoist column-existence checks (once, not per drug)
		$this->ensure_ipd_medication_rx_schema();
		$schema = array(
			'frequency'             => $this->db->field_exists('frequency',             'iop_medication'),
			'prescribed_by'         => $this->db->field_exists('prescribed_by',         'iop_medication'),
			'dispensing_status'     => $this->db->field_exists('dispensing_status',     'iop_medication'),
			'payment_status'        => $this->db->field_exists('payment_status',        'iop_medication'),
			'diagnosis_code'        => $this->db->field_exists('diagnosis_code',        'iop_medication'),
			'diagnosis_description' => $this->db->field_exists('diagnosis_description', 'iop_medication'),
			'route'                 => $this->db->field_exists('route',                 'iop_medication'),
			'drug_form'             => $this->db->field_exists('drug_form',             'iop_medication'),
			'unit'                  => $this->db->field_exists('unit',                  'iop_medication'),
			'freq_code'             => $this->db->field_exists('freq_code',             'iop_medication'),
			'is_nhis_covered'       => $this->db->field_exists('is_nhis_covered',       'iop_medication'),
			'is_prn'                => $this->db->field_exists('is_prn',                'iop_medication'),
			'is_urgent'             => $this->db->field_exists('is_urgent',             'iop_medication'),
			'prescription_no'       => $this->db->field_exists('prescription_no',       'iop_medication'),
			'rx_number'             => $this->db->field_exists('rx_number',             'iop_medication'),
			'prescribed_dose_value' => $this->db->field_exists('prescribed_dose_value', 'iop_medication'),
			'prescribed_dose_unit'  => $this->db->field_exists('prescribed_dose_unit',  'iop_medication'),
			'strength_per_unit_value'=> $this->db->field_exists('strength_per_unit_value','iop_medication'),
			'strength_per_unit_unit' => $this->db->field_exists('strength_per_unit_unit', 'iop_medication'),
			'required_units'         => $this->db->field_exists('required_units',         'iop_medication'),
			'total_active_mass_value'=> $this->db->field_exists('total_active_mass_value','iop_medication'),
			'total_active_mass_unit' => $this->db->field_exists('total_active_mass_unit', 'iop_medication'),
			'prescribed_qty'         => $this->db->field_exists('prescribed_qty',         'iop_medication'),
		);

		// Per-drug: NHIS formulary + CDS safety
		$blocked_drugs  = array();
		$cds_warnings   = array();
		$nhis_warnings  = array();

		foreach ($med_list as $idx => $med) {
			$drug_id = (int)$med['drug_id'];
			if ($drug_id <= 0) continue;

			// NHIS formulary
			if ($payer === 'NHIS') {
				try {
					$cov = $this->billing_model->check_drug_nhis_coverage($drug_id);
					if (!empty($cov['found']) && empty($cov['is_nhis_covered'])) {
						$name = isset($cov['drug_name']) ? htmlspecialchars($cov['drug_name']) : 'Drug #' . $drug_id;
						$nhis_warnings[] = $name . ' is not on the NHIS formulary (cash price applies)';
					}
				} catch (Exception $e) {
					log_message('error', 'IPD batch NHIS formulary check drug ' . $drug_id . ': ' . $e->getMessage());
				}
			}

			// CDS safety check
			if ($cdsLoaded) {
				try {
					$alerts = $this->Clinical_decision_support_model->check_prescription_safety_full(
						$drug_id,
						$med['dosage'], $med['frequency'], max(1, $med['days']),
						$patNo, $iop_id, $med['diagnosis_code']
					);
					if ($this->Clinical_decision_support_model->should_block_prescription($alerts)) {
						foreach ($alerts as $a) {
							if ($a->severity === 'BLOCKED') {
								$blocked_drugs[] = array('entry_idx' => $idx, 'drug_id' => $drug_id, 'type' => $a->type, 'message' => $a->message);
							}
						}
					} else {
						foreach ($alerts as $a) {
							$cds_warnings[] = $a->type . ': ' . $a->message;
						}
					}
				} catch (Exception $e) {
					log_message('error', 'IPD batch CDS check drug ' . $drug_id . ': ' . $e->getMessage());
				}
			}
		}

		if (!empty($blocked_drugs)) {
			$msgs = array();
			foreach ($blocked_drugs as $b) { $msgs[] = $b['type'] . ': ' . $b['message']; }
			echo json_encode(array(
				'status'   => 'blocked',
				'success'  => false,
				'saved'    => 0,
				'blocked'  => count($blocked_drugs),
				'warnings' => array(),
				'message'  => 'Prescription blocked — patient safety risk: ' . implode('; ', $msgs),
				'details'  => $blocked_drugs,
			));
			return;
		}

		$warnings = array_merge($nhis_warnings, $cds_warnings);

		// ── Transaction: all-or-nothing inserts ─────────────────────────────
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
				'total_qty'     => isset($entry['total_qty'])   ? max(0.01, (float)$entry['total_qty'])   : 1,
				'cPreparedBy'   => $doctor_id,
				'dDate'         => date('Y-m-d H:i:s'),
				'InActive'      => 0,
			);

			if ($schema['frequency'])              $data['frequency']             = isset($entry['frequency'])            ? strip_tags(trim($entry['frequency']))             : '';
			if ($schema['prescribed_by'])          $data['prescribed_by']         = $doctor_id;
			if ($schema['dispensing_status'])      $data['dispensing_status']     = 'PENDING';
			if ($schema['payment_status'])         $data['payment_status']        = 'PENDING';
			if ($schema['route'])                  $data['route']                 = isset($entry['route'])                ? strip_tags(trim($entry['route']))                 : null;
			if ($schema['drug_form'])              $data['drug_form']             = isset($entry['drug_form'])            ? strip_tags(trim($entry['drug_form']))             : null;
			if ($schema['diagnosis_code'])         $data['diagnosis_code']        = isset($entry['diagnosis_code'])       ? strip_tags(trim($entry['diagnosis_code']))        : null;
			if ($schema['diagnosis_description'])  $data['diagnosis_description'] = isset($entry['diagnosis_description'])? strip_tags(trim($entry['diagnosis_description'])) : null;
			if ($schema['unit'])                   $data['unit']                  = isset($entry['unit'])                 ? strip_tags(trim($entry['unit']))                  : null;
			if ($schema['freq_code'])              $data['freq_code']             = isset($entry['freq_code'])            ? strip_tags(trim($entry['freq_code']))             : null;
			if ($schema['is_nhis_covered'])        $data['is_nhis_covered']       = isset($entry['is_nhis_covered'])      ? (int)$entry['is_nhis_covered']                   : 0;
			if ($schema['is_prn'])                 $data['is_prn']                = isset($entry['is_prn'])               ? (int)$entry['is_prn']                            : 0;
			if ($schema['is_urgent'])              $data['is_urgent']             = isset($entry['is_urgent'])            ? (int)$entry['is_urgent']                         : 0;
			if ($schema['rx_number'])              $data['rx_number']             = null;
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
			log_message('error', 'IPD save_medication_batch TRANSACTION FAILED: iop_id=' . $iop_id . ' patient=' . $patNo);
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

		// ── Post-commit: Prescription Engine + billing triggers (non-blocking) ──
		$this->load->model('app/Prescription_engine_model');
		try { $this->Prescription_engine_model->ensure_phase4_schema(); } catch (Exception $e) {
			log_message('error', 'IPD batch Rx schema: ' . $e->getMessage());
		}

		$this->load->model('app/pharmacy_model');
		$this->load->model('app/unified_billing_model');

		foreach ($saved_drugs as $sd) {
			// 1. Prescription Engine: generate + stamp Rx number
			try {
				$rx_no = $this->Prescription_engine_model->generate_prescription_no();
				if ($schema['prescription_no']) {
					$this->Prescription_engine_model->stamp_prescription_no($sd['iop_med_id'], $rx_no);
				}
				if (!empty($schema['rx_number'])) {
					$this->db->where('iop_med_id', (int)$sd['iop_med_id']);
					$this->db->update('iop_medication', array('rx_number' => $rx_no));
				}
			} catch (\Throwable $e) {
				$this->log_ipd_rx_generation_failed($sd['iop_med_id'], $patNo, $iop_id, $e->getMessage(), $doctor_id);
				$rx_no = '';
			}

			// Route NHIS drugs to nhis_claim_queue
			if ($sd['is_nhis_covered']) {
				try {
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
				} catch (Exception $e) {
					log_message('error', 'IPD batch NHIS queue: ' . $e->getMessage());
				}
			}

			// Audit: PRESCRIBED
			try {
				$this->Prescription_engine_model->audit_log('PRESCRIBED', array(
					'iop_med_id'      => $sd['iop_med_id'],
					'prescription_no' => $rx_no,
					'iop_id'          => $iop_id,
					'patient_no'      => $patNo,
					'new_status'      => 'PENDING',
					'notes'           => 'Batch save via IPD (' . $payer . ')',
					'user_id'         => $doctor_id,
				));
			} catch (Exception $e) {
				log_message('error', 'IPD batch Rx audit: ' . $e->getMessage());
			}

			// 2. Billing triggers (mirrors OPD _post_save_billing_triggers with context='IPD_BATCH')
			$isVerified = false;
			try {
				if ($this->db->field_exists('prescription_status', 'iop_medication')) {
					$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => (int)$sd['iop_med_id'], 'InActive' => 0))->row();
					$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : 'PENDING';
					$isVerified = ($st === 'VERIFIED');
				}
			} catch (Exception $e) {
				$isVerified = false;
			}

			if ($isVerified) {
				// 2a. pharmacy_model->create_or_update_pharmacy_bill
				try {
					$_dbg = $this->db->db_debug; $this->db->db_debug = false;
					$this->pharmacy_model->create_or_update_pharmacy_bill($sd['iop_med_id'], $doctor_id);
					$this->db->db_debug = $_dbg;
				} catch (Exception $e) {
					$this->db->db_debug = isset($_dbg) ? $_dbg : true;
					log_message('error', 'IPD_BATCH pharmacy_bill non-blocking: ' . $e->getMessage());
				}

				// 2b. unified_billing_model->add_to_billing_queue
				try {
					$drugPrice = 0;
					if ($sd['drug_id'] > 0) {
						$_dbg = $this->db->db_debug; $this->db->db_debug = false;
						$dr = $this->db->select('drug_name, nPrice, cash_price')
							->get_where('medicine_drug_name', array('drug_id' => $sd['drug_id']))->row();
						$this->db->db_debug = $_dbg;
						if ($dr) {
							if (empty($sd['drug_name'])) $sd['drug_name'] = (string)$dr->drug_name;
							$drugPrice = (!empty($dr->cash_price) && (float)$dr->cash_price > 0) ? (float)$dr->cash_price : (float)$dr->nPrice;
						}
					}
					$this->unified_billing_model->add_to_billing_queue(array(
						'iop_id'        => $iop_id,
						'patient_no'    => $patNo,
						'item_type'     => 'PHARMACY',
						'item_id'       => (string)$sd['iop_med_id'],
						'item_name'     => $sd['drug_name'] ?: 'Medication',
						'unit_price'    => $drugPrice,
						'quantity'      => $sd['qty'] > 0 ? $sd['qty'] : 1,
						'payer_type'    => $payer,
						'source_module' => 'PHARMACY',
						'source_ref'    => 'iop_id:' . (string)$iop_id . ':iop_medication:' . (string)(int)$sd['iop_med_id'],
						'requested_by'  => $doctor_id,
					));
				} catch (Exception $e) {
					log_message('error', 'IPD_BATCH unified_billing non-blocking: ' . $e->getMessage());
				}
			}

			// 3. NHIS audit log
			try {
				$_dbg = $this->db->db_debug; $this->db->db_debug = false;
				$this->billing_model->log_nhis_audit(
					'IPD_BATCH_SAVE_MEDICATION', 'iop_medication', $sd['iop_med_id'],
					null, json_encode(array('drug_id' => $sd['drug_id'], 'qty' => $sd['qty'])),
					$doctor_id, $patNo, $iop_id
				);
				$this->db->db_debug = $_dbg;
			} catch (Exception $e) {
				$this->db->db_debug = isset($_dbg) ? $_dbg : true;
				log_message('error', 'IPD_BATCH nhis_audit non-blocking: ' . $e->getMessage());
			}

			// 4. CDS workflow tracking
			if ($cdsLoaded && $sd['drug_id'] > 0) {
				try {
					$_dbg = $this->db->db_debug; $this->db->db_debug = false;
					$this->Clinical_decision_support_model->init_prescription_workflow(
						$sd['iop_med_id'], $iop_id, $patNo, $doctor_id
					);
					$this->db->db_debug = $_dbg;
				} catch (Exception $e) {
					$this->db->db_debug = isset($_dbg) ? $_dbg : true;
					log_message('error', 'IPD_BATCH cds_workflow non-blocking: ' . $e->getMessage());
				}
			}
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
			'redirect' => base_url() . 'app/ipd/medication/' . url_safe_id($iop_id) . '/' . $patient_no,
		));
	}

	public function delete_medication(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_medication')) {
			return;
		}
		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => (int)$id, 'InActive' => 0))->row();
			$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : '';
			if ($st !== '' && $st !== 'PENDING') {
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cannot delete a prescription after verification/workflow has started. Current status: <strong>" . htmlspecialchars($st) . "</strong>.</div>");
				redirect(base_url().'app/ipd/medication/'.$iop_no.'/'.$patient_no,$this->data);
				return;
			}
		}
		
		$this->db->query("UPDATE iop_medication SET InActive = 1 WHERE iop_med_id = ?", array((int)$id));
		
		redirect(base_url().'app/ipd/medication/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function complain(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		
		$this->data['ComplainList'] = $this->opd_model->ComplainList();
		
		$this->data['patientComplain'] = $this->opd_model->patientComplain($iop_no);
		
		
		$this->load->view("app/ipd/complain",$this->data);	
	}
	
	public function save_complain(){
		$this->form_validation->set_rules("complain","Complain","trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		$opd_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_complain')) {
			return;
		}
		
		if($this->form_validation->run()){
			
			$this->opd_model->save_complain();
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Complain successfully Added!</div>");
			
			//redirect
			redirect(base_url().'app/ipd/complain/'.$opd_no.'/'.$patient_no,$this->data);
			
			
		}else{
			redirect(base_url().'app/ipd/complain/'.$opd_no.'/'.$patient_no,$this->data);
		}		
	}

	public function save_complaint_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Invalid request method'));
			return;
		}

		$opd_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		$entries = json_decode($this->input->post('entries'), true);
		$iop_id = url_decode_id($opd_no);

		if (!$this->doctor_write_allowed_or_redirect($iop_id, $patient_no, 'IPD', 'save_complaint_batch', true)) {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Access denied'));
			return;
		}

		if (empty($entries) || !is_array($entries)) {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'No complaints to save'));
			return;
		}

		$visit_q = $this->db->query(
			"SELECT IO_ID FROM patient_details_iop WHERE IO_ID = ? AND patient_no = ? AND InActive = 0 LIMIT 1",
			array($iop_id, $patient_no)
		);
		if (!$visit_q || $visit_q->num_rows() === 0) {
			echo json_encode(array('status' => 'error', 'success' => false, 'message' => 'Invalid IPD visit'));
			return;
		}

		$recorded_by = (string)$this->session->userdata('user_id');
		$now = date("Y-m-d H:i:s");
		$today = date("Y-m-d");

		$has_severity    = $this->opd_model->column_exists('iop_complaints', 'severity');
		$has_duration    = $this->opd_model->column_exists('iop_complaints', 'duration');
		$has_onset       = $this->opd_model->column_exists('iop_complaints', 'onset');
		$has_recorded_by = $this->opd_model->column_exists('iop_complaints', 'recorded_by');
		$has_text        = $this->opd_model->column_exists('iop_complaints', 'complain_text');

		$this->db->trans_start();

		$saved = 0;
		$ignored = 0;
		$errors = array();
		$usage_queue = array();

		foreach ($entries as $idx => $entry) {
			$raw_text = isset($entry['complain']) ? trim(strip_tags((string)$entry['complain'])) : '';
			if ($raw_text === '') {
				$ignored++;
				continue;
			}

			$severity = isset($entry['severity']) ? trim(strip_tags((string)$entry['severity'])) : '';
			$duration = isset($entry['duration']) ? trim(strip_tags((string)$entry['duration'])) : '';
			$onset = isset($entry['onset']) ? trim(strip_tags((string)$entry['onset'])) : '';

			if (!in_array($severity, array('', 'Mild', 'Moderate', 'Severe'), true)) { $severity = ''; }
			if (!in_array($onset, array('', 'Acute', 'Chronic', 'Recurrent'), true)) { $onset = ''; }
			if (strlen($duration) > 100) { $duration = substr($duration, 0, 100); }

			$complain_id = 'others';
			$complain_name_check = strtoupper($raw_text);
			$master_q = $this->db->query(
				"SELECT complain_id FROM complain WHERE UPPER(complain_name) = ? AND InActive = 0 LIMIT 1",
				array($complain_name_check)
			);
			if ($master_q && $master_q->num_rows() > 0) {
				$complain_id = (int)$master_q->row()->complain_id;
			}

			$is_duplicate = false;
			if ($complain_id !== 'others') {
				$dup_q = $this->db->query(
					"SELECT iop_comp_id FROM iop_complaints WHERE iop_id = ? AND complain_id = ? AND DATE(dDate) = ? AND InActive = 0 LIMIT 1",
					array($iop_id, $complain_id, $today)
				);
				$is_duplicate = ($dup_q && $dup_q->num_rows() > 0);
			} elseif ($has_text) {
				$dup_q = $this->db->query(
					"SELECT iop_comp_id FROM iop_complaints WHERE iop_id = ? AND complain_id = 'others' AND complain_text = ? AND DATE(dDate) = ? AND InActive = 0 LIMIT 1",
					array($iop_id, $raw_text, $today)
				);
				$is_duplicate = ($dup_q && $dup_q->num_rows() > 0);
			}

			if ($is_duplicate) {
				$ignored++;
				continue;
			}

			$data = array(
				'iop_id' => $iop_id,
				'complain_id' => $complain_id,
				'dDate' => $now,
				'InActive' => 0,
			);
			if ($has_text)        { $data['complain_text'] = $raw_text; }
			if ($has_severity)    { $data['severity'] = $severity; }
			if ($has_duration)    { $data['duration'] = $duration; }
			if ($has_onset)       { $data['onset'] = $onset; }
			if ($has_recorded_by) { $data['recorded_by'] = $recorded_by; }

			if ($this->db->insert('iop_complaints', $data)) {
				$saved++;
				$usage_queue[] = array('complain_id' => $complain_id, 'complain_name' => $raw_text);
			} else {
				$errors[] = "Row {$idx}: DB insert failed";
			}
		}

		$this->db->trans_complete();

		if ($this->db->trans_status() === false || !empty($errors)) {
			echo json_encode(array(
				'status' => 'error',
				'success' => false,
				'message' => 'Failed to save complaints. No records were inserted.',
				'errors' => $errors,
			));
			return;
		}

		if (!empty($usage_queue) && $recorded_by !== '') {
			foreach ($usage_queue as $u) {
				$this->opd_model->increment_complaint_usage($recorded_by, $u['complain_id'], $u['complain_name']);
			}
		}

		$status = 'success';
		$msg = "{$saved} complaint(s) saved successfully.";
		if ($ignored > 0 && $saved > 0) {
			$status = 'warning';
			$msg = "{$saved} complaint(s) saved. {$ignored} duplicate(s) ignored.";
		} elseif ($ignored > 0 && $saved === 0) {
			echo json_encode(array(
				'status' => 'warning',
				'success' => false,
				'saved_count' => 0,
				'ignored' => $ignored,
				'message' => "All selected complaint(s) are already recorded for this visit today.",
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
			'status' => $status,
			'success' => true,
			'saved_count' => $saved,
			'ignored' => $ignored,
			'message' => $msg,
			'redirect' => base_url() . 'app/ipd/complain/' . url_safe_id($iop_id) . '/' . $patient_no,
		));
	}
	
	public function delete_complain(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_complain')) {
			return;
		}
		
		$this->db->query("UPDATE iop_complaints SET InActive = 1 WHERE iop_comp_id = ?", array((int)$id));
		
		redirect(base_url().'app/ipd/complain/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function vitalSign(){
		if ($this->input->method(TRUE) === 'POST' && $this->input->post('opd_no') != '' && $this->input->post('patient_no') != '') {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_vitalSign')) {
				return;
			}
			$this->data = array(
				'iop_id'		=>		$this->input->post('opd_no'),
				'dDate'			=>		$this->input->post('dDate'),
				'dDateTime'		=>		$this->input->post('dDate')." ".$this->input->post('cTime'),
				'pulse_rate'	=>		$this->input->post('pulse_rate'),
				'temperature'	=>		$this->input->post('temperature'),
				'height'		=>		$this->input->post('height'),
				'bp'			=>		$this->input->post('bp'),
				'respiration'	=>		$this->input->post('respiration'),
				'weight'		=>		$this->input->post('weight'),
				'cPreparedBy'	=>		$this->session->userdata('user_id'),
				'InActive'		=>		0
			);
			$this->db->insert('iop_vital_parameters',$this->data);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Parameters successfully Added!</div>");
			
			//redirect
			redirect(base_url().'app/ipd/vitalSign/'.urldecode($this->input->post('opd_no')).'/'.urldecode($this->input->post('patient_no')),$this->data);
			
		}else{
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
			$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);		
			$this->data['getVital'] = $this->opd_model->getVital($iop_no);
			$this->data['message'] = $this->session->flashdata('message');
		
			$this->load->view("app/ipd/vitalSign",$this->data);	
		}
	}
	
	public function delete_vital(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_vital')) {
			return;
		}
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Parameters successfully Deleted!</div>");
		
		$this->db->query("UPDATE iop_vital_parameters SET InActive = 1 WHERE vital_id = ?", array((int)$id));
		
		redirect(base_url().'app/ipd/vitalSign/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function save_vital(){
		$iop_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'save_vital')) {
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
		$this->db->where("IO_ID",$iop_no);
		$this->db->update("patient_details_iop",$this->data);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vital Sign successfully Updated!</div>");
			
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);	
			
		//redirect
		redirect(base_url().'app/ipd/vitalSign/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function patientHistory(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);

		// Ensure schema and auto-populate from patient history on new visits
		$this->clinical_history_model->ensure_clinical_history_schema();
		$this->clinical_history_model->populate_from_patient_history($iop_no, $patient_no);

		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['clinical_summary'] = $this->clinical_history_model->get_comprehensive_summary($patient_no);
		$this->data['patient_history'] = $this->clinical_history_model->get_patient_history($patient_no);
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->view("app/ipd/patientHistory",$this->data);	
	}
	
	public function save_patientHistory(){
		$iop_no = $this->input->post('opd_no');
		$patient_no = $this->input->post('patient_no');
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'save_patientHistory')) {
			return;
		}

		$user_id = $this->session->userdata('user_id');

		$history_data = array(
			'allergies'						=>	$this->input->post('allergies'),
			'warnings'						=>	$this->input->post('warnings'),
			'social_history'				=>	$this->input->post('social_history'),
			'family_history'				=>	$this->input->post('family_history'),
			'personal_history'				=>	$this->input->post('personal_history'),
			'past_medical_history'			=>	$this->input->post('past_medical_history'),
			'history_presenting_complaint'	=>	$this->input->post('history_presenting_complaint'),
			'past_surgical_history'			=>	$this->input->post('past_surgical_history'),
			'drug_history'					=>	$this->input->post('drug_history'),
			'gynae_obstetric_history'		=>	$this->input->post('gynae_obstetric_history'),
			'on_direct_questioning'			=>	$this->input->post('on_direct_questioning'),
			'examination_findings'			=>	$this->input->post('examination_findings'),
			'examination_general'			=>	$this->input->post('examination_general'),
			'examination_cardiovascular'	=>	$this->input->post('examination_cardiovascular'),
			'examination_respiratory'		=>	$this->input->post('examination_respiratory'),
			'examination_gastrointestinal'	=>	$this->input->post('examination_gastrointestinal'),
			'examination_neurological'		=>	$this->input->post('examination_neurological'),
			'examination_musculoskeletal'	=>	$this->input->post('examination_musculoskeletal'),
			'examination_other'				=>	$this->input->post('examination_other')
		);

		$this->clinical_history_model->save_visit_history($iop_no, $history_data, $user_id);

		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Clinical History successfully Updated!</div>");

		//redirect
		redirect(base_url().'app/ipd/patientHistory/'.$iop_no.'/'.$patient_no);
	}
	
	public function discharge_summary(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['get_discharge_summary'] = $this->opd_model->get_discharge_summary($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);		
		$this->data['getConditionDis'] = $this->general_model->getConditionDis();
		$this->data['message'] = $this->session->flashdata('message');
		
		$this->load->view("app/ipd/discharge_summary",$this->data);	
	}
	
	public function save_discharge_summary(){
		if (!$this->doctor_write_allowed_or_redirect($this->input->post('opd_no'), $this->input->post('patient_no'), 'IPD', 'save_discharge_summary')) {
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
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Discharge summary successfully Added!</div>");
		
		redirect(base_url().'app/ipd/discharge_summary/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	public function progress_note(){
		if ($this->input->method(TRUE) === 'POST' && $this->input->post('opd_no') != '' && $this->input->post('patient_no') != '') {
			$ok = $this->ipd_model->save_progress_note();
			if ($ok) {
				$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Progress Notes successfully Added!</div>");
			} else {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Progress Notes could not be saved.</div>");
			}

			redirect(base_url().'app/ipd/progress_note/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
			return;
		}else{
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);

			list($ok, $enc, $patient) = $this->require_ipd_context_or_redirect($iop_no, $patient_no, 'progress_note');
			if (!$ok) {
				return;
			}
			$this->data['getOPDPatient'] = $enc;
			$this->data['getProgressNote'] = $this->opd_model->getProgressNote($iop_no);
			$this->data['patientInfo'] = $patient;
			$this->data['message'] = $this->session->flashdata('message');
		
			$this->load->view("app/ipd/progress_note",$this->data);	
		}
	}
	
	public function delete_progress(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_progress')) {
			return;
		}
		
		$this->db->query("UPDATE iop_progress_note SET InActive = 1 WHERE progress_id = ?", array((int)$id));
		
		redirect(base_url().'app/ipd/progress_note/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function intake_output(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
		$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);	
		$this->data['message'] = $this->session->flashdata('message');
		
		$this->data['getIntake'] = $this->ipd_model->getIntake($iop_no);
		$this->data['getOutput'] = $this->ipd_model->getOutput($iop_no);
		
		$this->load->view("app/ipd/intake_output",$this->data);	
	}
	
	public function save_intake(){
		$this->data = array(
			'iop_id'		=>		$this->input->post('opd_no'),
			'particulars'	=>		$this->input->post('particular'),
			'IV_fluids'		=>		$this->input->post('fluids'),
			'oral'			=>		$this->input->post('oral'),
			'no_stool'		=>		$this->input->post('no_stool'),
			'no_urine'		=>		$this->input->post('no_urine'),
			'dDate'			=>		$this->input->post('dDate'),
			'dDateTime'		=>		$this->input->post('dDate')." ".$this->input->post('cTime'),
			'cPreparedBy'	=>		$this->session->userdata('user_id'),
			'InActive'		=>		0
		);	
		$this->db->insert("iop_intake_record",$this->data);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Intake Record successfully Added!</div>");
		
		redirect(base_url().'app/ipd/intake_output/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	public function delete_intake(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_intake')) {
			return;
		}
		
		$this->db->query("UPDATE iop_intake_record SET InActive = 1 WHERE intake_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Intake Record successfully Deleted!</div>");
		
		redirect(base_url().'app/ipd/intake_output/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function save_output(){
		$this->data = array(
			'iop_id'		=>		$this->input->post('opd_no'),
			'urine'			=>		$this->input->post('urine'),
			'feaces'		=>		$this->input->post('feaces'),
			'respitation'	=>		$this->input->post('respitation'),
			'skin'			=>		$this->input->post('skin'),
			'dDate'			=>		$this->input->post('dDate2'),
			'dDateTime'		=>		$this->input->post('dDate2')." ".$this->input->post('cTime2'),
			'cPreparedBy'	=>		$this->session->userdata('user_id'),
			'InActive'		=>		0
		);	
		$this->db->insert("iop_output_record",$this->data);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Output Record successfully Added!</div>");
		
		redirect(base_url().'app/ipd/intake_output/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	public function delete_output(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_output')) {
			return;
		}
		
		$this->db->query("UPDATE iop_output_record SET InActive = 1 WHERE output_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Output Record successfully Deleted!</div>");
		
		redirect(base_url().'app/ipd/intake_output/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function nurse_progress_note(){
		if ($this->input->method(TRUE) === 'POST' && $this->input->post('opd_no') != '' && $this->input->post('patient_no') != '') {
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
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Notes successfully Added!</div>");
		
			redirect(base_url().'app/ipd/nurse_progress_note/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
		}else{
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
			$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
			$this->data['getNurseProgressNote'] = $this->opd_model->getNurseProgressNote($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);	
			$this->data['message'] = $this->session->flashdata('message');
		
			$this->load->view("app/ipd/nurse_progress_note",$this->data);	
		}
	}
	
	public function delete_nurse_progress(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_nurse_progress')) {
			return;
		}
		
		$this->db->query("UPDATE iop_nurse_notes SET InActive = 1 WHERE nurse_notes_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Notes successfully Deleted!</div>");
		
		redirect(base_url().'app/ipd/nurse_progress_note/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function operation_theater(){
		if ($this->input->method(TRUE) === 'POST' && $this->input->post('opd_no') != '' && $this->input->post('patient_no') != '') {
			$this->db->trans_begin();
			$this->db->query("DELETE FROM iop_operation_theater WHERE iop_id = ?", array($this->input->post('opd_no')));
			
			$this->data = array(
				'iop_id'				=>		$this->input->post('opd_no'),
				'dDate_from'			=>		$this->input->post('dDate_from'),
				'dTime_from'			=>		$this->input->post('dTime_from'),
				'dDate_to'				=>		$this->input->post('dDate_to'),
				'dTime_to'				=>		$this->input->post('dTime_to'),
				'operation_name'		=>		$this->input->post('operation_name'),
				'diagnosis'				=>		$this->input->post('diagnosis'),
				'name_of_surgeon'		=>		$this->input->post('surgeon'),
				'name_of_anesthesia'	=>		$this->input->post('anesthesia'),
				'assistant_name1'		=>		$this->input->post('assistant1'),
				'assistant_name2'		=>		$this->input->post('assistant2'),
				'assistant_name3'		=>		$this->input->post('assistant3'),
				'assistant_name4'		=>		$this->input->post('assistant4'),
				'operation_procedure'	=>		$this->input->post('operation_procedure'),
				'notes'					=>		$this->input->post('notes'),
				'InActive'				=>		0
			);
			$this->db->insert('iop_operation_theater',$this->data);
			
			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Operation Theater could not be saved.</div>");
			} else {
				$this->db->trans_commit();
				$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Operation Theater successfully Saved!</div>");
			}
		
			redirect(base_url().'app/ipd/operation_theater/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
		}else{
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
			$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
			$this->data['message'] = $this->session->flashdata('message');
			
			$this->data['particular_cat'] = $this->billing_model->particular_cat();
			$this->data['medicine_cat'] = $this->billing_model->medicine_cat();
			
			$this->data['getOperationTheater'] = $this->opd_model->getOperationTheater($iop_no);
			$this->data['room_category'] = $this->general_model->room_category();
			
			$this->data['surgery_list'] = $this->general_model->surgery_list();
			$this->load->view("app/ipd/operation_theater",$this->data);	
		}
	}
	
	public function room_transfer(){
		if ($this->input->method(TRUE) === 'POST' && $this->input->post('opd_no') != '' && $this->input->post('patient_no') != '') {
			$iop_id = (string)$this->input->post('opd_no');
			$new_bed_id = (int)$this->input->post('bed_name');
			if ($iop_id === '' || $new_bed_id <= 0) {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to transfer: invalid admission/bed selection.</div>");
				redirect(base_url().'app/ipd/room_transfer/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
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
				(string)$this->input->post('patient_no'),
				$new_bed_id,
				$transfer
			);
			if (is_array($res) && isset($res['ok']) && $res['ok'] === true) {
				$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient successfully Transfered!</div>");
			} else {
				$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'unknown';
				$msg = ($err === 'admission_not_active')
					? 'Unable to transfer: admission is not active.'
					: (($err === 'bed_not_found') ? 'Unable to transfer: bed not found.' : (($err === 'bed_occupied') ? 'Unable to transfer: bed is already occupied.' : 'Transfer could not be completed.'));
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>{$msg}</div>");
			}
		
			redirect(base_url().'app/ipd/room_transfer/'.urldecode($this->input->post('opd_no')).'/'.urldecode($this->input->post('patient_no')),$this->data);
			
		}else{
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
			$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
			$this->data['message'] = $this->session->flashdata('message');
			
			$this->data['room_transfer'] = $this->ipd_model->room_transfer($iop_no);
			
			$this->data['particular_cat'] = $this->billing_model->particular_cat();
			$this->data['medicine_cat'] = $this->billing_model->medicine_cat();
			
			$this->data['getOperationTheater'] = $this->opd_model->getOperationTheater($iop_no);
			$this->data['room_category'] = $this->general_model->room_category();
			
			$this->load->view("app/ipd/room_transfer",$this->data);	
		}
	}
	
	public function delete_room_transfer(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_room_transfer')) {
			return;
		}
		
		$this->db->query("UPDATE iop_room_transfer SET InActive = 1 WHERE transfer_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Room successfully Deleted!</div>");
		
		redirect(base_url().'app/ipd/room_transfer/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function bed_side_procedure(){
		if ($this->input->method(TRUE) === 'POST' && $this->input->post('opd_no') != '' && $this->input->post('patient_no') != '') {
			$iop_id = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$particular_id = (int)$this->input->post('particular');
			$qty = (int)$this->input->post('qty') ?: 1;
			
			$this->data = array(
				'iop_id'				=>		$iop_id,
				'dDate'					=>		date("Y-m-d"),
				'dDateTime'				=>		date("Y-m-d h:i:s"),
				'cItem_id'				=>		$particular_id,
				'qty'					=>		$qty,
				'notes'					=>		$this->input->post('remarks'),
				'cPreparedBy'			=>		$this->session->userdata('user_id'),
				'InActive'				=>		0
			);

			$this->db->trans_begin();
			$this->db->insert('iop_bed_side_procedure',$this->data);
			$procedure_id = $this->db->insert_id();
			if ((int)$procedure_id <= 0) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to save bedside procedure.</div>");
				redirect(base_url().'app/ipd/bed_side_procedure/'.$iop_id.'/'.$patient_no,$this->data);
				return;
			}

			$actor = (string)$this->session->userdata('user_id');
			$this->load->model('app/billing_transaction_model');
			$proj = null;
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_bed_side_procedure')) {
				$proj = $this->billing_transaction_model->sync_bed_side_procedure((int)$procedure_id, $actor);
			}
			if (!is_array($proj) || !isset($proj['ok']) || !$proj['ok']) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Billing projection failed. Procedure was not saved.</div>");
				redirect(base_url().'app/ipd/bed_side_procedure/'.$iop_id.'/'.$patient_no,$this->data);
				return;
			}

			$procedure_name = 'Bed Side Procedure';
			$procedure_price = 0.0;
			$proc_q = $this->db->get_where('bill_particular', ['particular_id' => $particular_id]);
			if ($proc_q && $proc_q->num_rows() > 0) {
				$procedure_name = $proc_q->row()->particular_name ?: $procedure_name;
				$procedure_price = isset($proc_q->row()->charge_amount) ? (float)$proc_q->row()->charge_amount : 0.0;
			}
			$this->load->model('app/unified_billing_model');
			$qres = null;
			if (isset($this->unified_billing_model) && method_exists($this->unified_billing_model, 'add_to_billing_queue')) {
				$qres = $this->unified_billing_model->add_to_billing_queue(array(
					'iop_id' => (string)$iop_id,
					'patient_no' => (string)$patient_no,
					'item_type' => 'PROCEDURE',
					'item_id' => (string)(int)$particular_id,
					'item_name' => (string)$procedure_name,
					'unit_price' => (float)$procedure_price,
					'quantity' => (float)$qty,
					'source_module' => 'IPD_BED_SIDE',
					'source_ref' => 'iop_bed_side_procedure_id:' . (int)$procedure_id,
					'requested_by' => (string)$actor,
					'idempotency_key' => 'ipd_bed_side:' . (int)$procedure_id,
				));
			}
			if (!is_array($qres) || !isset($qres['success']) || !$qres['success']) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Billing queue entry failed. Procedure was not saved.</div>");
				redirect(base_url().'app/ipd/bed_side_procedure/'.$iop_id.'/'.$patient_no,$this->data);
				return;
			}
			
			// UNIFIED BILLING: Auto-create billing entry for procedure
			try {
				$this->load->library('billing_automation');
				// Get procedure name from bill_particular
				$this->billing_automation->on_procedure_ordered(
					(string)$patient_no,
					(string)$iop_id,
					'IPD',
					$particular_id,
					$procedure_name,
					(string)$this->session->userdata('user_id')
				);
			} catch (Exception $e) {
				log_message('error', 'IPD bed_side_procedure billing_automation: ' . $e->getMessage());
			}

			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to save bedside procedure.</div>");
				redirect(base_url().'app/ipd/bed_side_procedure/'.$iop_id.'/'.$patient_no,$this->data);
				return;
			}
			$this->db->trans_commit();
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Bed Side Procedure successfully Saved! (Billing auto-generated)</div>");
		
			redirect(base_url().'app/ipd/bed_side_procedure/'.$iop_id.'/'.$patient_no,$this->data);
			
		}else{
			list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
			$this->data['getOPDPatient'] = $this->opd_model->getOPDPatient($iop_no);
			$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['getServices'] = $this->opd_model->getServices($iop_no);
			
			$this->data['particular_cat'] = $this->billing_model->particular_cat();
			
			$this->load->view("app/ipd/bed_side_procedure",$this->data);			
		}
	}
	
	public function delete_bed_side(){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$id = (int)$this->uri->segment(4);
		list($iop_no, $patient_no) = $this->get_url_params(5, 6);
		if (!$this->doctor_write_allowed_or_redirect($iop_no, $patient_no, 'IPD', 'delete_bed_side')) {
			return;
		}
		
		$this->db->query("UPDATE iop_bed_side_procedure SET InActive = 1 WHERE bed_pro_id = ?", array((int)$id));
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Bed Side Procedure successfully Deleted!</div>");
		
		redirect(base_url().'app/ipd/bed_side_procedure/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	public function laboratory(){
		list($iop_no, $patient_no) = $this->get_url_params(4, 5);
		
		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_no);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		
		$this->data['message'] = $this->session->flashdata('message');
		
		//list of category
		$this->data['particular_cat'] = $this->billing_model->particular_cat();
		
		
		$this->data['patient_lab'] = $this->opd_model->patient_lab($iop_no);

		$ipd_batch_enabled = false;
		$enabled = getenv('IPD_DIAGNOSTICS_BATCH_ENABLED');
		$enabled = is_string($enabled) ? strtolower(trim($enabled)) : '';
		if (in_array($enabled, array('1', 'true', 'yes', 'on'), true)) {
			$ipd_batch_enabled = true;
		}
		$this->data['ipd_diagnostics_batch_enabled'] = $ipd_batch_enabled;

		if ($ipd_batch_enabled) {
			// For Multi-Entry Laboratory Modal - SSOT: bill_particular (match OPD)
			$this->data['lab_categories'] = $this->billing_model->get_lab_categories();
			$this->data['lab_tests'] = $this->billing_model->get_lab_tests();
			$this->data['lab_save_url'] = base_url() . 'app/ipd/save_laboratory_batch';

			// For Sonography modal - match OPD modern flow
			$this->load->model('app/Ghana_test_catalog_model');
			$this->load->model('app/laboratory_model');
			$this->data['sono_items'] = $this->Ghana_test_catalog_model->get_sonography_tests();
			$this->data['sono_categories'] = $this->Ghana_test_catalog_model->get_sonography_categories();
			$this->data['sonography_category_id'] = $this->laboratory_model->get_sonography_category_id();
			$this->data['sono_save_url'] = base_url() . 'app/ipd/save_sonography_batch';
		}

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
		
		// For Radiology Modal (mirrors OPD laboratory())
		$this->load->model('app/laboratory_model');
		$radiologyCatId = (int)$this->laboratory_model->get_radiology_category_id();
		$select = 'particular_id AS id, particular_name AS test_name';
		if ($this->db->field_exists('charge_amount', 'bill_particular')) {
			$select .= ', charge_amount AS price';
		}
		if ($this->db->field_exists('is_nhis_covered', 'bill_particular')) {
			$select .= ', is_nhis_covered';
		}
		$this->db->select($select, false);
		$this->db->from('bill_particular');
		$this->db->where('group_id', $radiologyCatId);
		$this->db->where('InActive', 0);
		$this->db->order_by('particular_name', 'ASC');
		$this->data['radiology_tests'] = $this->db->get()->result();
		$this->data['radiology_save_url'] = base_url() . 'app/ipd/save_radiology_batch';
		$this->data['patient_payer_type'] = $this->billing_model->determine_payer_type((string)$patient_no);

		// Doctor list for modals
		$this->data['doctorList2'] = $this->general_model->doctorList();

		$this->load->view("app/ipd/laboratory",$this->data);	
	}

	public function save_laboratory_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
			return;
		}

		$enabled = getenv('IPD_DIAGNOSTICS_BATCH_ENABLED');
		$enabled = is_string($enabled) ? strtolower(trim($enabled)) : '';
		if (!in_array($enabled, array('1', 'true', 'yes', 'on'), true)) {
			echo json_encode(array('success' => false, 'message' => 'Batch ordering is disabled'));
			return;
		}

		$_db_debug_orig = $this->db->db_debug;
		$this->db->db_debug = false;

		try {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$entries = json_decode($this->input->post('entries'), true);

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_laboratory_batch', true)) {
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
			$lab_group_ids = $this->config->item('lab_group_ids');
			if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
				$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
			}

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
				$bp_gid = isset($bp->group_id) ? (int)$bp->group_id : 0;
				if (!in_array($bp_gid, $lab_group_ids, true)) {
					continue;
				}

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
					'laboratory_text' => $remarks,
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
						$this->diag_fin_state->get_financial_state_detail('LAB', $io_lab_id_batch);
						$this->diag_fin_state->detect_drift('LAB', $io_lab_id_batch, true);
					} catch (\Throwable $e) {
					}

					try {
						$this->laboratory_model->upsert_workflow_status($io_lab_id_batch, 'REQUESTED', $doctor_id);
					} catch (\Throwable $e) {
					}

					try {
						$this->laboratory_model->ensure_lab_charge_posted(
							$io_lab_id_batch, $iop_id, $patient_no, 'IPD',
							$billing_id, $lab_text, $doctor_id, $batchPayerType
						);
					} catch (\Throwable $e) {
					}

					try {
						$this->load->library('billing_automation');
						$this->billing_automation->on_lab_ordered(
							$patient_no, $iop_id, 'IPD', $billing_id, $lab_text, $doctor_id
						);
					} catch (\Throwable $e) {
					}

					try {
						$this->nhis_validation->validate_service('LAB', (int)$io_lab_id_batch);
					} catch (\Throwable $e) {
					}

					try {
						$this->billing_model->ensure_corporate_billing_schema();
						$exists = false;
						if ($this->_ipd_table_exists('service_orders', 'service_orders_schema')) {
							$ex = $this->db->select('id')->get_where('service_orders', array(
								'reference_table' => 'iop_laboratory',
								'reference_id' => (int)$io_lab_id_batch,
								'InActive' => 0
							), 1)->row();
							$exists = ($ex && isset($ex->id));
						}
						if (!$exists) {
							$labName = $lab_text ?: 'Laboratory Test';
							$labPrice = 0.0;
							$bp2 = $this->db->select('particular_name, charge_amount')->get_where('bill_particular', array('particular_id' => (int)$billing_id))->row();
							if ($bp2) {
								if (!empty($bp2->particular_name)) { $labName = (string)$bp2->particular_name; }
								$labPrice = isset($bp2->charge_amount) ? (float)$bp2->charge_amount : 0.0;
							}
							$this->billing_model->create_service_order_for_request(
								(string)$iop_id,
								(string)$patient_no,
								'LAB',
								$billing_id > 0 ? (int)$billing_id : null,
								(string)$labName,
								(float)$labPrice,
								(string)$doctor_id,
								'IPD',
								'iop_laboratory',
								(int)$io_lab_id_batch
							);
						}
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
				'redirect' => base_url() . 'app/ipd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));
		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'IPD save_laboratory_batch fatal: ' . $e->getMessage());
			echo json_encode(array('success' => false, 'message' => 'Server error: ' . $e->getMessage()));
		}
	}

	public function save_sonography_batch()
	{
		header('Content-Type: application/json');
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('success' => false, 'message' => 'Invalid request method'));
			return;
		}

		$enabled = getenv('IPD_DIAGNOSTICS_BATCH_ENABLED');
		$enabled = is_string($enabled) ? strtolower(trim($enabled)) : '';
		if (!in_array($enabled, array('1', 'true', 'yes', 'on'), true)) {
			echo json_encode(array('success' => false, 'message' => 'Batch ordering is disabled'));
			return;
		}

		$_db_debug_orig = $this->db->db_debug;
		$this->db->db_debug = false;

		try {
			$opd_no = $this->input->post('opd_no');
			$patient_no = $this->input->post('patient_no');
			$entries = json_decode($this->input->post('entries'), true);

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_sonography_batch', true)) {
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

				$remarks = isset($entry['remarks']) ? trim((string)$entry['remarks']) : '';
				$priority = isset($entry['priority']) ? trim((string)$entry['priority']) : 'Normal';
				$item_name = '';

				$ghs_sono = $this->Ghana_test_catalog_model->get_sono_test_for_order($item_id);
				$billing_id = $item_id;
				if ($ghs_sono) {
					if (isset($ghs_sono->test_name) && trim((string)$ghs_sono->test_name) !== '') {
						$item_name = (string)$ghs_sono->test_name;
					}
					if (!empty($ghs_sono->particular_id)) {
						$billing_id = (int)$ghs_sono->particular_id;
					} else {
						$bp_match = $this->db->query(
							"SELECT particular_id FROM bill_particular WHERE LOWER(TRIM(particular_name)) = LOWER(TRIM(?)) AND InActive = 0 LIMIT 1",
							array($item_name)
						);
						if ($bp_match && $bp_match->num_rows() > 0) {
							$billing_id = (int)$bp_match->row()->particular_id;
							$this->db->where('test_id', $item_id)->update('ghs_sonography_tests', array('particular_id' => $billing_id));
						} else {
							$billing_id = $item_id;
						}
					}
				}
				if ($item_name === '') {
					$bp_q = $this->db->query("SELECT particular_name FROM bill_particular WHERE particular_id = ? LIMIT 1", array($item_id));
					$item_name = ($bp_q && $bp_q->num_rows() > 0) ? (string)$bp_q->row()->particular_name : '';
				}
				if ($item_name === '') {
					$item_name = 'Sonography';
				}

				$nowDate = date('Y-m-d');
				$nowDateTime = date('Y-m-d H:i:s');
				$data = array(
					'iop_id'          => $iop_id,
					'laboratory_id'   => (int)$billing_id,
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

					try {
						$sonoUrgency = (strtolower($priority) === 'urgent') ? 'URGENT' : 'ROUTINE';
						$this->laboratory_model->upsert_sonography_request_meta(
							$io_lab_id, $item_id, $remarks, 'IPD', $patient_no, $doctor_id, $doctor_id, $sonoUrgency
						);
					} catch (\Throwable $e) {
					}

					try {
						$this->diag_fin_state->get_financial_state_detail('SONOGRAPHY', (int)$io_lab_id);
						$this->diag_fin_state->detect_drift('SONOGRAPHY', (int)$io_lab_id, true);
					} catch (\Throwable $e) {
					}

					try {
						$this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $doctor_id);
					} catch (\Throwable $e) {
					}

					try {
						$this->laboratory_model->ensure_ipd_sonography_charge_posted(
							$io_lab_id,
							(string)$iop_id,
							(string)$patient_no,
							$item_id,
							$remarks !== '' ? $remarks : null,
							(string)$doctor_id
						);
					} catch (\Throwable $e) {
					}

					try {
						$this->load->library('billing_automation');
						$this->billing_automation->on_sonography_ordered(
							$patient_no, $iop_id, 'IPD', (int)$billing_id, (string)$item_name, (string)$doctor_id
						);
					} catch (\Throwable $e) {
					}

					try {
						$this->nhis_validation->validate_service('SONOGRAPHY', (int)$io_lab_id);
					} catch (\Throwable $e) {
					}

					try {
						$this->billing_model->ensure_corporate_billing_schema();
						$exists = false;
						if ($this->_ipd_table_exists('service_orders', 'service_orders_schema')) {
							$ex = $this->db->select('id')->get_where('service_orders', array(
								'reference_table' => 'iop_laboratory',
								'reference_id' => (int)$io_lab_id,
								'InActive' => 0
							), 1)->row();
							$exists = ($ex && isset($ex->id));
						}
						if (!$exists) {
							$sonoName = $item_name ?: 'Sonography Scan';
							$sonoPrice = 0.0;
							$bp2 = null;
							if ((int)$billing_id > 0) {
								$bp2 = $this->db->select('particular_name, charge_amount')->get_where('bill_particular', array('particular_id' => (int)$billing_id))->row();
							}
							if ($bp2) {
								if (!empty($bp2->particular_name)) { $sonoName = (string)$bp2->particular_name; }
								$sonoPrice = isset($bp2->charge_amount) ? (float)$bp2->charge_amount : 0.0;
							}
							$this->billing_model->create_service_order_for_request(
								(string)$iop_id,
								(string)$patient_no,
								'SONOGRAPHY',
								(int)$billing_id > 0 ? (int)$billing_id : null,
								(string)$sonoName,
								(float)$sonoPrice,
								(string)$doctor_id,
								'IPD',
								'iop_laboratory',
								(int)$io_lab_id
							);
						}
					} catch (\Throwable $e) {
					}
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
				'redirect' => base_url() . 'app/ipd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));
		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'IPD save_sonography_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	/**
	 * Batch save laboratory requests (IPD) - AJAX endpoint for multi-entry system.
	 * Mirrors OPD save_laboratory_batch, but enforces IPD RBAC + redirects to IPD laboratory view.
	 */
	public function save_laboratory_batch_old()
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

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_laboratory_batch', true)) {
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
			$encounter = $this->db->get_where('patient_details_iop', array(
				'IO_ID' => (string)$iop_id,
				'patient_no' => (string)$patient_no,
				'InActive' => 0
			), 1)->row();
			if (!$encounter) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'Invalid IPD patient encounter'));
				return;
			}

			$saved = 0;
			$ignored = 0;
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
				if ($lab_id <= 0) {
					continue;
				}

				$priority = isset($entry['priority']) ? trim((string)$entry['priority']) : 'Normal';
				$remarks = isset($entry['remarks']) ? trim((string)$entry['remarks']) : '';
				$specimen_type = isset($entry['specimen_type']) ? trim((string)$entry['specimen_type']) : '';

				// Validate lab id exists in billing catalog + resolve category
				$bp = $this->db->query(
					"SELECT particular_id, particular_name, group_id FROM bill_particular WHERE particular_id = ? AND InActive = 0 LIMIT 1",
					array($lab_id)
				)->row();
				if (!$bp) {
					continue;
				}

				// Ignore duplicates for this visit that are still pending (no result yet)
				$this->db->select('io_lab_id');
				$this->db->where(array(
					'iop_id' => (string)$iop_id,
					'category_id' => (int)$bp->group_id,
					'laboratory_id' => (int)$bp->particular_id,
					'InActive' => 0
				));
				$this->db->where("(result = '' OR result IS NULL)", null, false);
				$this->db->limit(1);
				$dup = $this->db->get('iop_laboratory')->row();
				if ($dup) {
					$ignored++;
					continue;
				}

				$data = array(
					'iop_id'          => (string)$iop_id,
					'dDate'           => date('Y-m-d'),
					'dDateTime'       => date('Y-m-d H:i:s'),
					'category_id'     => (int)$bp->group_id,
					'laboratory_id'   => (int)$bp->particular_id,
					'laboratory_text' => (string)$bp->particular_name,
					'findings'        => '',
					'result'          => '',
					'doctor'          => (string)$doctor_id,
					'InActive'        => 0
				);
				if ($hasPriorityCol) {
					$data['priority'] = ($priority !== '') ? $priority : 'Normal';
				}
				if ($hasRequestedByCol) {
					$data['requested_by'] = (string)$doctor_id;
				}
				if ($hasClinicalNotesCol) {
					$data['clinical_notes'] = $remarks;
				}
				if ($hasSpecimenTypeCol) {
					$data['specimen_type'] = $specimen_type;
				}
				if ($hasPayerTypeCol) {
					$data['payer_type'] = $batchPayerType;
				}
				if ($hasNhisFlagCol) {
					$data['nhis_flag'] = $batchNhisFlag;
				}

				$this->db->insert('iop_laboratory', $data);
				if ($this->db->affected_rows() > 0) {
					$io_lab_id = (int)$this->db->insert_id();
					$saved++;

					// workflow + pricing + billing automation (server-side price resolution)
					try { $this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $doctor_id); } catch (\Throwable $e) {}
					try {
						$this->laboratory_model->ensure_lab_charge_posted(
							$io_lab_id, $iop_id, $patient_no, 'IPD',
							(int)$bp->particular_id, (string)$bp->particular_name, (string)$doctor_id, $batchPayerType
						);
					} catch (\Throwable $e) {}

					try {
						$this->load->library('billing_automation');
						$this->billing_automation->on_lab_ordered(
							$patient_no, $iop_id, 'IPD', (int)$bp->particular_id, (string)$bp->particular_name, $doctor_id
						);
					} catch (\Throwable $e) {}

					if ($batchPayerType === 'NHIS') {
						try { $this->nhis_validation->validate_service('LAB', (int)$io_lab_id); } catch (\Throwable $e) {}
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
				'redirect' => base_url() . 'app/ipd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));

		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'IPD save_laboratory_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	/**
	 * Batch save sonography requests (IPD) - AJAX endpoint for multi-entry system.
	 * Mirrors OPD save_sonography_batch with 'IPD' billing context.
	 */
	public function save_sonography_batch_old()
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

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_sonography_batch', true)) {
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
			$encounter = $this->db->get_where('patient_details_iop', array(
				'IO_ID' => (string)$iop_id,
				'patient_no' => (string)$patient_no,
				'InActive' => 0
			), 1)->row();
			if (!$encounter) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'Invalid IPD patient encounter'));
				return;
			}

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

			// Pending cache for duplicates (same as OPD)
			$normalize_text = function($text) {
				$text = preg_replace('/\\s+/', ' ', (string)$text);
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
				if ($item_id <= 0) {
					continue;
				}
				$remarks = isset($entry['remarks']) ? trim((string)$entry['remarks']) : '';
				$priority = isset($entry['priority']) ? trim((string)$entry['priority']) : 'Normal';

				$sono = $this->Ghana_test_catalog_model->get_sonography_test($item_id);
				if (!$sono) {
					continue;
				}
				$sonoName = isset($sono->test_name) ? (string)$sono->test_name : '';
				if ($sonoName === '') {
					$sonoName = 'Sonography Scan';
				}

				$normName = $normalize_text($sonoName);
				if (isset($pending_ids[(string)$item_id]) || ($normName !== '' && isset($pending_texts[$normName]))) {
					$ignored++;
					continue;
				}

				$data = array(
					'iop_id'          => (string)$iop_id,
					'dDate'           => date('Y-m-d'),
					'dDateTime'       => date('Y-m-d H:i:s'),
					'category_id'     => (int)$sono_cat_id,
					'laboratory_id'   => (int)$item_id,
					'laboratory_text' => $sonoName,
					'findings'        => '',
					'result'          => '',
					'doctor'          => (string)$doctor_id,
					'InActive'        => 0
				);
				if ($hasPriorityCol) {
					$data['priority'] = ($priority !== '') ? $priority : 'Normal';
				}
				if ($hasRequestedByCol) {
					$data['requested_by'] = (string)$doctor_id;
				}
				if ($hasClinicalNotesCol) {
					$data['clinical_notes'] = $remarks;
				}
				if ($hasPayerTypeCol) {
					$data['payer_type'] = $batchPayerType;
				}
				if ($hasNhisFlagCol) {
					$data['nhis_flag'] = $batchNhisFlag;
				}

				$this->db->insert('iop_laboratory', $data);
				if ($this->db->affected_rows() > 0) {
					$io_lab_id = (int)$this->db->insert_id();
					$saved++;

					// workflow + meta + pricing + billing automation
					try { $this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $doctor_id); } catch (\Throwable $e) {}
					try {
						$sonoUrgency = (strtolower($priority) === 'urgent') ? 'URGENT' : 'ROUTINE';
						$this->laboratory_model->upsert_sonography_request_meta(
							$io_lab_id, $item_id, $remarks, 'IPD', $patient_no, $doctor_id, $doctor_id, $sonoUrgency
						);
					} catch (\Throwable $e) {}
					try {
						$this->laboratory_model->ensure_ipd_sonography_charge_posted(
							$io_lab_id,
							(string)$iop_id,
							(string)$patient_no,
							(int)$item_id,
							($remarks !== '' ? $remarks : null),
							(string)$doctor_id
						);
					} catch (\Throwable $e) {}

					try {
						$this->load->library('billing_automation');
						$this->billing_automation->on_sonography_ordered(
							(string)$patient_no, (string)$iop_id, 'IPD', (int)$item_id, (string)$sonoName, (string)$doctor_id
						);
					} catch (\Throwable $e) {}

					if ($batchPayerType === 'NHIS') {
						try { $this->nhis_validation->validate_service('SONOGRAPHY', (int)$io_lab_id); } catch (\Throwable $e) {}
					}
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
				'redirect' => base_url() . 'app/ipd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));

		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'IPD save_sonography_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	public function save_laboratory(){
		if (!$this->doctor_write_allowed_or_redirect($this->input->post('opd_no'), $this->input->post('patient_no'), 'IPD', 'save_laboratory')) {
			return;
		}
		$category_id = (int)$this->input->post('category');
		$particular = (int)$this->input->post('particular');
		$clinical_question = trim((string)$this->input->post('clinical_question'));
		$urgency = trim((string)$this->input->post('urgency'));
		$laboratory_text = $this->input->get_post('laboratory_text');
		$isSonography = false;
		if ($category_id === 18) {
			if ($particular <= 0) {
				$isSonography = true;
			} else if ($this->_ipd_table_exists('sonography_items', 'sonography_items_schema')) {
				$sonoRow = $this->db->select('item_id')->get_where('sonography_items', array('item_id' => (int)$particular, 'InActive' => 0))->row();
				if ($sonoRow) {
					$isSonography = true;
				}
			}
		}

		if ($isSonography) {
			$u = strtoupper(trim((string)$urgency));
			if ($u !== 'ROUTINE' && $u !== 'URGENT' && $u !== 'STAT') {
				$urgency = 'ROUTINE';
			}
			if ($particular <= 0 && $clinical_question === '') {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please select a sonography scan or enter a clinical question.</div>");
				redirect(base_url().'app/ipd/laboratory/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
				return;
			}
			if ($particular <= 0) {
				$particular = 0;
				if (trim((string)$laboratory_text) === '') {
					$laboratory_text = 'Custom Sonography Request';
				}
			}
		}

		// STRICT (non-sonography) IPD lab requests: must exist in billing catalog (bill_particular)
		if (!$isSonography) {
			$lab_group_ids = $this->config->item('lab_group_ids');
			if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
				$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
			}

			$bp = $this->db->query("SELECT particular_id, particular_name, group_id FROM bill_particular WHERE particular_id = ? AND InActive = 0 LIMIT 1", array($particular))->row();
			if (!$bp) {
				log_message('error', 'Invalid lab request blocked: bill_particular not found (IPD save_laboratory) user_id=' . (int)$this->session->userdata('user_id') . ' post=' . json_encode($this->input->post(NULL, true)));
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invalid laboratory test selected. Please select from the lab test list.</div>");
				redirect(base_url().'app/ipd/laboratory/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
				return;
			}
			$bp_gid = (int)$bp->group_id;
			if (!in_array($bp_gid, $lab_group_ids, true)) {
				log_message('error', 'Invalid lab request blocked: non-lab group_id (IPD save_laboratory) user_id=' . (int)$this->session->userdata('user_id') . ' bill_particular_id=' . (int)$bp->particular_id . ' group_id=' . (int)$bp_gid . ' post=' . json_encode($this->input->post(NULL, true)));
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invalid laboratory test selected. Please select from the lab test list.</div>");
				redirect(base_url().'app/ipd/laboratory/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
				return;
			}

			// Force canonical values from catalog
			$category_id = $bp_gid;
			$particular = (int)$bp->particular_id;
			$laboratory_text = (string)$bp->particular_name;
		}
		$this->load->model('app/laboratory_model');
		$this->load->model('app/billing_model');
		$this->load->model('app/Nhis_validation_model', 'nhis_validation');
		$ipdPatientNo = (string)$this->input->post('patient_no');
		$ipdPayerType = $this->billing_model->determine_payer_type($ipdPatientNo);
		$ipdNhisFlag  = ($ipdPayerType === 'NHIS') ? 1 : 0;
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
		$this->data = array(
				'iop_id'				=>		$this->input->post('opd_no'),
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
			if ($this->laboratory_model->column_exists('iop_laboratory', 'payer_type')) {
				$this->data['payer_type'] = $ipdPayerType;
			}
			if ($this->laboratory_model->column_exists('iop_laboratory', 'nhis_flag')) {
				$this->data['nhis_flag'] = $ipdNhisFlag;
			}
			$this->laboratory_model->install_imaging_tables();
			if (!($category_id === 18 && (int)$particular === 0)) {
				$this->load->model('app/laboratory_model');
				$sono_cat = (int)$this->laboratory_model->get_sonography_category_id();
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
						$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>A similar laboratory request is already pending.</div>");
						redirect(base_url().'app/ipd/laboratory/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
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
						$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>A similar laboratory request is already pending.</div>");
						redirect(base_url().'app/ipd/laboratory/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
						return;
					}
				}
			}
			$this->db->insert('iop_laboratory',$this->data);
			$io_lab_id = $this->db->insert_id();
			try {
				$nhis_module = ($category_id === 18) ? 'SONOGRAPHY' : 'LAB';
				$validation = $this->nhis_validation->validate_service($nhis_module, (int)$io_lab_id);
				log_message(
					'debug',
					'[NHIS_VALIDATION] module=' . $nhis_module . ' id=' . (int)$io_lab_id
					. ' valid=' . (!empty($validation['valid']) ? '1' : '0')
					. ' ref=' . (isset($validation['resolved_item_ref']) ? (string)$validation['resolved_item_ref'] : 'null')
				);
			} catch (\Throwable $e) {
			}
			try {
				$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
				$diagMod = ($category_id === 18) ? 'SONOGRAPHY' : 'LAB';
				$diagId = (int)$io_lab_id;
				if ($diagMod === 'SONOGRAPHY' && $this->_ipd_table_exists('iop_sonography_charge', 'iop_sonography_charge_schema')) {
					$this->db->select('charge_id');
					$this->db->from('iop_sonography_charge');
					$this->db->where('InActive', 0);
					$this->db->where('io_lab_id', (int)$io_lab_id);
					$this->db->order_by('charge_id', 'DESC');
					$this->db->limit(1);
					$ch = $this->db->get()->row();
					if ($ch && isset($ch->charge_id)) {
						$diagId = (int)$ch->charge_id;
					}
				}
				$det = $this->diag_fin_state->get_financial_state_detail($diagMod, (int)$diagId);
				$state = isset($det['state']) ? (string)$det['state'] : 'REQUESTED';
				$ref = isset($det['resolved_item_ref']) ? (string)$det['resolved_item_ref'] : '';
				log_message('debug', '[DIAG_STATE] module=' . $diagMod . ' id=' . (int)$diagId . ' resolved_ref=' . $ref . ' state=' . $state);
				$dr = $this->diag_fin_state->detect_drift($diagMod, (int)$diagId, true);
				if (isset($dr['drift_types']) && is_array($dr['drift_types']) && !empty($dr['drift_types'])) {
					$dref = isset($dr['resolved_item_ref']) ? (string)$dr['resolved_item_ref'] : '';
					$sev = isset($dr['severity']) ? (string)$dr['severity'] : 'NONE';
					log_message('debug', '[DIAG_DRIFT] module=' . $diagMod . ' id=' . (int)$diagId . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
					if ($sev === 'CRITICAL') {
						log_message('error', '[DIAG_ALERT] module=' . $diagMod . ' id=' . (int)$diagId . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev);
					}
					if (in_array('POLICY_VIOLATION', $dr['drift_types'], true) || in_array('UNDERPAID_RELEASE', $dr['drift_types'], true)) {
						$pr = isset($dr['policy_reason']) ? (string)$dr['policy_reason'] : '';
						$msg = in_array('UNDERPAID_RELEASE', $dr['drift_types'], true) ? 'Payment below required threshold' : 'Policy denied but flow continued';
						log_message('error', '[POLICY_WARNING] module=' . $diagMod . ' id=' . (int)$diagId . ' resolved_ref=' . $dref . ' drift=' . implode('|', $dr['drift_types']) . ' severity=' . $sev . ' policy_reason=' . $pr . ' msg=' . $msg);
					}
				}
			} catch (\Throwable $e) {
			}
			$this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $this->session->userdata('user_id'));
			if ($category_id === 18) {
				$this->laboratory_model->upsert_sonography_request_meta(
					$io_lab_id,
					$particular > 0 ? $particular : null,
					$clinical_question !== '' ? $clinical_question : null,
					'IPD',
					(string)$this->input->post('patient_no'),
					(string)$this->input->post('doctor'),
					(string)$this->session->userdata('user_id'),
					$urgency !== '' ? (string)$urgency : null
				);
				$this->laboratory_model->ensure_ipd_sonography_charge_posted(
					$io_lab_id,
					(string)$this->input->post('opd_no'),
					(string)$this->input->post('patient_no'),
					$particular > 0 ? $particular : null,
					$clinical_question !== '' ? $clinical_question : null,
					(string)$this->session->userdata('user_id')
				);
				
				// UNIFIED BILLING: Auto-create billing entry for sonography
				try {
					$this->load->library('billing_automation');
					$sonoText = $laboratory_text ?: 'Sonography Scan';
					$this->billing_automation->on_sonography_ordered(
						(string)$this->input->post('patient_no'),
						(string)$this->input->post('opd_no'),
						'IPD',
						$particular,
						$sonoText,
						(string)$this->session->userdata('user_id')
					);
				} catch (Exception $e) {
					log_message('error', 'IPD sonography billing_automation: ' . $e->getMessage());
				}

				// Align with OPD: create a service order + unified billing queue entry (idempotent by reference_id)
				try {
					$this->billing_model->ensure_corporate_billing_schema();
					$exists = false;
					if ($this->_ipd_table_exists('service_orders', 'service_orders_schema')) {
						$ex = $this->db->select('id')->get_where('service_orders', array(
							'reference_table' => 'iop_laboratory',
							'reference_id' => (int)$io_lab_id,
							'InActive' => 0
						), 1)->row();
						$exists = ($ex && isset($ex->id));
					}
					if (!$exists) {
						$sonoName = $laboratory_text ?: 'Sonography Scan';
						$sonoPrice = 0.0;
						if ($particular > 0) {
							$bp = $this->db->select('particular_name, charge_amount')->get_where('bill_particular', array('particular_id' => (int)$particular))->row();
							if ($bp) {
								if (!empty($bp->particular_name)) { $sonoName = (string)$bp->particular_name; }
								$sonoPrice = isset($bp->charge_amount) ? (float)$bp->charge_amount : 0.0;
							}
						}
						$this->billing_model->create_service_order_for_request(
							(string)$this->input->post('opd_no'),
							(string)$this->input->post('patient_no'),
							'SONOGRAPHY',
							$particular > 0 ? (int)$particular : null,
							(string)$sonoName,
							(float)$sonoPrice,
							(string)$this->session->userdata('user_id'),
							'IPD',
							'iop_laboratory',
							(int)$io_lab_id
						);
					}
				} catch (Exception $e) {
					log_message('error', 'IPD sonography service_order non-blocking: ' . $e->getMessage());
				}
				
				log_message('info', 'SONOGRAPHY_REQUEST_CREATED ipd iop_id='.$this->data['iop_id'].' io_lab_id='.$io_lab_id);
				General::logfile('Sonography', 'REQUEST', (string)$io_lab_id);
			} else {
				$this->laboratory_model->ensure_lab_charge_posted(
					$io_lab_id,
					(string)$this->input->post('opd_no'),
					(string)$this->input->post('patient_no'),
					'IPD',
					$particular,
					$laboratory_text,
					(string)$this->session->userdata('user_id'),
					$ipdPayerType
				);
				log_message('info', 'LAB_BILL_GENERATED ipd io_lab_id='.$io_lab_id.' particular='.$particular.' payer='.$ipdPayerType);
				
				// UNIFIED BILLING: Auto-create billing entry for lab test
				try {
					$this->load->library('billing_automation');
					$this->billing_automation->on_lab_ordered(
						(string)$this->input->post('patient_no'),
						(string)$this->input->post('opd_no'),
						'IPD',
						$particular,
						$laboratory_text,
						(string)$this->session->userdata('user_id')
					);
				} catch (Exception $e) {
					log_message('error', 'IPD lab billing_automation: ' . $e->getMessage());
				}

				// Align with OPD: create a service order + unified billing queue entry (idempotent by reference_id)
				try {
					$this->billing_model->ensure_corporate_billing_schema();
					$exists = false;
					if ($this->_ipd_table_exists('service_orders', 'service_orders_schema')) {
						$ex = $this->db->select('id')->get_where('service_orders', array(
							'reference_table' => 'iop_laboratory',
							'reference_id' => (int)$io_lab_id,
							'InActive' => 0
						), 1)->row();
						$exists = ($ex && isset($ex->id));
					}
					if (!$exists) {
						$labName = $laboratory_text ?: 'Laboratory Test';
						$labPrice = 0.0;
						if ($particular > 0) {
							$bp = $this->db->select('particular_name, charge_amount')->get_where('bill_particular', array('particular_id' => (int)$particular))->row();
							if ($bp) {
								if (!empty($bp->particular_name)) { $labName = (string)$bp->particular_name; }
								$labPrice = isset($bp->charge_amount) ? (float)$bp->charge_amount : 0.0;
							}
						}
						$this->billing_model->create_service_order_for_request(
							(string)$this->input->post('opd_no'),
							(string)$this->input->post('patient_no'),
							'LAB',
							$particular > 0 ? (int)$particular : null,
							(string)$labName,
							(float)$labPrice,
							(string)$this->session->userdata('user_id'),
							'IPD',
							'iop_laboratory',
							(int)$io_lab_id
						);
					}
				} catch (Exception $e) {
					log_message('error', 'IPD lab service_order non-blocking: ' . $e->getMessage());
				}
			}
			log_message('info', 'LAB_REQUEST_CREATED ipd iop_id='.$this->data['iop_id'].' io_lab_id='.$io_lab_id);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Laboratory successfully Saved! (Billing auto-generated)</div>");
		
			redirect(base_url().'app/ipd/laboratory/'.$this->input->post('opd_no').'/'.$this->input->post('patient_no'),$this->data);
	}
	
	/**
	 * Batch save radiology requests — AJAX endpoint (IPD).
	 * Mirrors OPD save_radiology_batch with 'IPD' billing context.
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

			if (!$this->doctor_write_allowed_or_redirect($opd_no, $patient_no, 'IPD', 'save_radiology_batch', true)) {
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
			$encounter = $this->db->get_where('patient_details_iop', array(
				'IO_ID' => (string)$iop_id,
				'patient_no' => (string)$patient_no,
				'InActive' => 0
			), 1)->row();
			if (!$encounter) {
				$this->db->db_debug = $_db_debug_orig;
				echo json_encode(array('success' => false, 'message' => 'Invalid IPD patient encounter'));
				return;
			}

			$saved = 0;
			$ignored = 0;
			$doctor_id = $this->session->userdata('user_id');

			$this->load->model('app/radiology_model');
			$this->load->model('app/laboratory_model');
			$this->load->model('app/billing_model');
			$this->load->model('app/billing_transaction_model');
			$this->load->model('app/Diagnostic_finance_state_model', 'diag_fin_state');
			$this->load->model('app/Nhis_validation_model', 'nhis_validation');
			$this->load->library('billing_automation');

			$radiology_cat_id = $this->laboratory_model->get_radiology_category_id();
			$batchPayerType = $this->billing_model->determine_payer_type((string)$patient_no);
			$batchNhisFlag  = ($batchPayerType === 'NHIS') ? 1 : 0;
			$hasClinicalNotesCol = $this->laboratory_model->column_exists('iop_laboratory', 'clinical_notes');
			$hasPriorityCol    = $this->laboratory_model->column_exists('iop_laboratory', 'priority');
			$hasRequestedByCol = $this->laboratory_model->column_exists('iop_laboratory', 'requested_by');
			$hasPayerTypeCol   = $this->laboratory_model->column_exists('iop_laboratory', 'payer_type');
			$hasNhisFlagCol    = $this->laboratory_model->column_exists('iop_laboratory', 'nhis_flag');

			foreach ($entries as $entry) {
				$entry_iop_id = isset($entry['iop_id']) ? url_decode_id($entry['iop_id']) : $iop_id;
				$entry_patient_no = isset($entry['patient_no']) ? trim((string)$entry['patient_no']) : (string)$patient_no;
				if ((string)$entry_iop_id !== (string)$iop_id || (string)$entry_patient_no !== (string)$patient_no) {
					log_message('error', 'IPD Radiology batch skipped mismatched item context: ' . json_encode($entry));
					$ignored++;
					continue;
				}

				$test_id = isset($entry['test_id']) ? (int)$entry['test_id'] : (isset($entry['radiology_test_id']) ? (int)$entry['radiology_test_id'] : 0);
				if (!$test_id) continue;

				$clinical_notes = isset($entry['clinical_notes']) ? trim($entry['clinical_notes']) : '';
				$priority = isset($entry['priority']) ? trim($entry['priority']) : 'normal';
				$test_name = isset($entry['test_name']) ? trim($entry['test_name']) : (isset($entry['radiology_test_text']) ? trim($entry['radiology_test_text']) : '');

				if ($test_name === '') {
					$bp = $this->db->select('particular_name, group_id')->get_where('bill_particular', array('particular_id' => (int)$test_id, 'InActive' => 0), 1)->row();
					if ($bp && isset($bp->particular_name)) {
						$test_name = trim((string)$bp->particular_name);
					}
				}
				if ($test_name === '') {
					log_message('error', 'IPD Radiology batch skipped missing test_name for test_id=' . (int)$test_id);
					$ignored++;
					continue;
				}

				// 1. Insert into iop_laboratory
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

				// Duplicate check
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

					// 2. Create radiology_orders record
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
						log_message('error', 'IPD Radiology batch create_order: ' . $e->getMessage());
					}

					if ($order_id > 0) {
						try {
							$this->diag_fin_state->get_financial_state_detail('RADIOLOGY', $order_id);
							$this->diag_fin_state->detect_drift('RADIOLOGY', $order_id, true);
						} catch (\Throwable $e) {}
					}

					// 3. Workflow status
					try {
						$this->laboratory_model->upsert_workflow_status($io_lab_id, 'REQUESTED', $doctor_id);
					} catch (\Throwable $e) { log_message('error', 'IPD Radiology batch workflow: ' . $e->getMessage()); }

					// 4. Billing — key difference: 'IPD' instead of 'OPD'
					try {
						$bill_res = $this->billing_automation->on_radiology_ordered(
							$patient_no, $iop_id, 'IPD', $test_id, $test_name, $doctor_id
						);
						if ($order_id > 0 && is_array($bill_res) && isset($bill_res['success'])) {
							if ($this->_ipd_table_exists('radiology_orders', 'radiology_orders_schema')) {
								$upd = array();
								if ($this->db->field_exists('billed', 'radiology_orders')) {
									$upd['billed'] = $bill_res['success'] ? 1 : 0;
								}
								if ($this->db->field_exists('invoice_no', 'radiology_orders')) {
									$upd['invoice_no'] = $bill_res['success'] ? null : 'BILLING_FAILED';
								}
								if (!empty($upd)) {
									$this->db->where(array('id' => $order_id, 'InActive' => 0));
									$this->db->update('radiology_orders', $upd);
								}
							}
							if (!$bill_res['success']) {
								$err = isset($bill_res['error']) ? (string)$bill_res['error'] : 'Billing automation returned success=false';
								log_message('error', 'IPD Radiology billing failed order_id=' . $order_id . ' test_id=' . $test_id . ' err=' . $err);
								if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'log_audit')) {
									$this->billing_transaction_model->log_audit(array(
										'action' => 'IPD_RADIOLOGY_BILLING_FAILED',
										'table_name' => 'iop_laboratory',
										'record_id' => $io_lab_id,
										'patient_no' => (string)$patient_no,
										'description' => $err,
										'visit_id' => (string)$iop_id,
										'visit_type' => 'IPD',
										'test_id' => (int)$test_id,
										'test_name' => (string)$test_name,
										'billing_result' => $bill_res
									), $doctor_id);
								}
							}
						}
					} catch (\Throwable $e) {
						log_message('error', 'IPD Radiology batch billing: ' . $e->getMessage());
					}

					// 5. NHIS validation
					if ($batchPayerType === 'NHIS') {
						try {
							$this->nhis_validation->validate_service('RADIOLOGY', array(
								'iop_id' => $iop_id,
								'patient_no' => $patient_no,
								'test_id' => $test_id,
							));
						} catch (\Throwable $e) {
							log_message('error', 'IPD Radiology NHIS validation: ' . $e->getMessage());
						}
					}

					log_message('info', 'IPD_RADIOLOGY_REQUEST iop_id=' . $iop_id . ' io_lab_id=' . $io_lab_id . ' test_id=' . $test_id . ' test=' . $test_name);
				} else {
					$db_err = $this->db->error();
					log_message('error', 'IPD Radiology batch insert FAILED test_id=' . $test_id . ': ' . json_encode($db_err));
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
				'redirect' => base_url() . 'app/ipd/laboratory/' . url_safe_id($iop_id) . '/' . $patient_no
			));

		} catch (\Throwable $e) {
			$this->db->db_debug = $_db_debug_orig;
			log_message('error', 'IPD save_radiology_batch fatal: ' . $e->getMessage());
			echo json_encode(array(
				'success' => false,
				'message' => 'Server error: ' . $e->getMessage()
			));
		}
	}

	public function delete_lab(){
		$id = (int)$this->uri->segment(4);
		$iop_no = $this->segment_decoded(5);
		$patient_no = $this->segment_decoded(6);
		$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Removal of laboratory requests is disabled to protect clinical records.</div>");
		redirect(base_url().'app/ipd/laboratory/'.$iop_no.'/'.$patient_no,$this->data);
	}
	
	
	
	
	public function getLabRequests(){
		// POST data
		$postData = $this->input->post();
	
		// Get data
		$data = $this->opd_model->getLabRequests($postData);
	
		echo json_encode($data);
	  }
	
	
	public function getDiagnosis(){
		// POST data
		$postData = $this->input->post();
	
		// Get data
		$data = $this->opd_model->getDiagnosis($postData);
	
		echo json_encode($data);
	  }
	
	
	public function getMeds(){
		// POST data
		$postData = $this->input->post();
	
		// Get data
		$data = $this->opd_model->getMeds($postData);
	
		echo json_encode($data);
	  }
	
	
	
	
	
	
}
