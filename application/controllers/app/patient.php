<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Patient extends General{

	private $limit = 10;

	public function __construct(){
		parent::__construct();
		$this->load->model("app/patient_model");
		$this->load->model("general_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		require_role(array('admin', 'doctor', 'nurse', 'receptionist', 'cashier'));
		
		$this->lang->load("message","english");
		if (!$this->session->userdata('_schema_patient_ok')) {
			$this->patient_model->ensure_nhis_schema();
			$this->patient_model->install_nhis_audit_table();
			$this->session->set_userdata('_schema_patient_ok', 1);
		}
	}
	
	public function masterlist(){
		$this->index();
	}

	public function index(){
		// user restriction function - Doctors, Nurses, Receptionists always have patient search access
		$this->session->set_userdata('page_name','patient_master');
		if (!has_role(array('admin', 'doctor', 'nurse', 'receptionist', 'cashier'))) {
			$page_id = $this->general_model->getPageID();
			$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
				redirect(base_url().'access_denied');
			}
		}
		// end of user restriction function
				
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'patient_master',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
		
		$this->patient_master();
	}
	
	public function patient_master($offset = 0){
		// pass value to session (preserve across pagination)
		// NOTE: allow Enter key submit (no btnSearch posted)
		if (strtoupper((string)$this->input->server('REQUEST_METHOD')) === 'POST') {
			if ($this->input->post('search') !== null) {
				$this->session->set_userdata("search_patient_master", (string)$this->input->post('search'));
			}
		}

		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$patients = $this->patient_model->getAll($this->limit, $offset);
		$searchFilter = trim((string)$this->session->userdata('search_patient_master'));
		$hasActiveSearch = ($searchFilter !== '');
		
		$config['base_url'] = base_url().'app/patient/index/';
 		$config['total_rows'] = $this->patient_model->count_all();
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
		$this->table->set_heading('Patient No', 'Patient Name','Gender','Civil Status','Age','Emergency Fullname','Emergency Phone Number','Date Entry','Action');
		$i = 0 + $offset;
		
		
		foreach ($patients as $patient)
		{	
				$this->table->add_row( 
									anchor('app/patient/view/'.$patient->patient_no,$patient->patient_no),
									$patient->name, 
									$patient->gender, 
									$patient->civil_status, 
									$patient->age,
									$patient->emergency_fullname,
									$patient->emergency_phone_number,
									date('M d, Y H:i:s',strtotime($patient->date_entry)), 
									//anchor('app/patient/edit/'.$patient->patient_no,'Modify').'&nbsp|&nbsp;'.
									//anchor('app/patient/delete/'.$patient->patient_no,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
									anchor('app/patient/edit/'.$patient->patient_no,'Modify')
									//anchor('app/patient/delete/'.$patient->patient_no,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		if ($hasActiveSearch && (!is_array($patients) || count($patients) === 0)) {
			$this->data['message'] .= "<div class='alert alert-info alert-dismissable'><i class='fa fa-info'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>No results found.</div>";
		}
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/patient/index',$this->data);	
	}
	
	public function view($id){
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($id);
		$this->load->view("app/patient/view",$this->data);
	}
	
	public function add(){
		$this->addPatient();
	}

	public function addPatient(){
		// user restriction function
		$this->session->set_userdata('page_name','add_new_patient');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
			redirect(base_url().'access_denied');
		}
		// end of user restriction function
		
		$this->session->set_userdata(array(
				 'tab'			=>		'patient',
				 'module'		=>		'add_new_patient',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
				 
				 
		$this->data['lastPatientID'] = $this->patient_model->lastPatientID();
		
		$this->load->view("app/patient/addPatient",$this->data);
	}
	
	function validate_patient(){
		if($this->patient_model->validate_patient()){
			$this->form_validation->set_message("validate_patient","Patient Already Exists.");
			return false;
		}else{
			return true;
		}
	}
	
	function validate_email(){
		if($this->patient_model->validate_email()){
			$this->form_validation->set_message("validate_email","Email Address Already Exists.");
			return false;
		}else{
			return true;
		}
	}

	function validate_birthday(){
		$s = trim((string)$this->input->post('birthday'));
		if ($s === '') {
			$this->form_validation->set_message('validate_birthday', 'Birthday is required.');
			return false;
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
			return true;
		}
		if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
			$a = (int)$m[1];
			$b = (int)$m[2];
			$y = (int)$m[3];
			$month = $a;
			$day = $b;
			if ($a > 12 && $b <= 12) {
				$day = $a;
				$month = $b;
			} elseif ($b > 12 && $a <= 12) {
				$month = $b;
				$day = $a;
			}
			if (checkdate($month, $day, $y)) {
				return true;
			}
		}
		if (strtotime($s) !== false) {
			return true;
		}
		$this->form_validation->set_message('validate_birthday', 'Invalid Birthday format. Use YYYY-MM-DD.');
		return false;
	}
	
	public function save(){
		// Required fields
		$this->form_validation->set_rules("patientID","Patient ID","trim|xss_clean|required");
		$this->form_validation->set_rules("title","Title","trim|xss_clean|required");
		$this->form_validation->set_rules("lastname","Surname","trim|xss_clean|required|callback_validate_patient");
		$this->form_validation->set_rules("firstname","First Name","trim|xss_clean|required");
		$this->form_validation->set_rules("birthday","Birthday","trim|xss_clean|required|callback_validate_birthday");
		$this->form_validation->set_rules("gender","Gender","trim|xss_clean|required");
		$this->form_validation->set_rules("noofhouse","Address","trim|xss_clean|required");
		$this->form_validation->set_rules("province","City","trim|xss_clean|required");
		$this->form_validation->set_rules("mobile","Phone No (Mobile)","trim|xss_clean|required");
		// Optional fields
		$this->form_validation->set_rules("middlename","Middle Name","trim|xss_clean");
		$this->form_validation->set_rules("civil_status","Civil Status","trim|xss_clean");
		$this->form_validation->set_rules("insurance_comp","Insurance Company","trim|xss_clean");
		$this->form_validation->set_rules("insurance_id","Insurance ID Number","trim|xss_clean");
		$this->form_validation->set_rules("emergency_fullname","Emergency Fullname","trim|xss_clean");
		$this->form_validation->set_rules("emergency_phone_number","Emergency Phone Number","trim|xss_clean");
		$this->form_validation->set_rules("email","Email Address","trim|xss_clean|valid_email");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		if($this->form_validation->run()){
			
			//save the data
			$this->patient_model->save();
			
			//update employeeID autonumber();
			$this->patient_model->updateAutoNum();
			
			//logfile
			$value = $this->input->post('firstname')." ".$this->input->post('middlename')." ".$this->input->post('lastname');
			General::logfile('Patient Registration','INSERT',$value);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient successfully Added!</div>");
			
			//redirect
			redirect(base_url().'app/patient');
			
			
		}else{
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-exclamation-circle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Registration failed.</strong> Please correct the highlighted errors and try again.</div>");
			$this->addPatient();	
		}	
	}
	
	public function edit($id = 0){
		if(isset($_POST['btnSubmit'])){
			
			$this->edit_save();
			
		}else{
			// user restriction function
				$this->session->set_userdata('page_name','modiffy_patient');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function
				
			// Try URI segment if no $id was passed as function argument
			if (empty($id)) {
				$id = $this->uri->segment(4);
			}
			$this->data['patientInfo'] = $this->patient_model->getPatient($id);
			if (!$this->data['patientInfo']) {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please select a patient to edit from the patient list.</div>");
				redirect(base_url() . 'app/patient');
				return;
			}
			$this->load->view('app/patient/editPatient', $this->data);
		}
	}
	
	function validate_patient_edit(){
		if($this->patient_model->validate_patient_edit()){
			$this->form_validation->set_message("validate_patient_edit","Patient Already Exists.");
			return false;
		}else{
			return true;
		}
	}
	
	function validate_email_edit(){
		if($this->patient_model->validate_email_edit()){
			$this->form_validation->set_message("validate_email_edit","Email Address Already Exists.");
			return false;
		}else{
			return true;
		}
	}
	
	public function edit_save(){
		$this->form_validation->set_rules("lastname","Surname","trim|xss_clean|required|callback_validate_patient_edit");
		$this->form_validation->set_rules("firstname","First Name","trim|xss_clean|required");	
		$this->form_validation->set_rules("birthday","Birthday","trim|xss_clean|required|callback_validate_birthday");
		$this->form_validation->set_rules("middlename","Middle Name","trim|xss_clean");	
		$this->form_validation->set_rules("email","Email Address","trim|xss_clean|valid_email");	
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		if($this->form_validation->run()){
			
			//save the data
			$this->patient_model->update();
			
			//logfile
			$value = $this->input->post('firstname')." ".$this->input->post('middlename')." ".$this->input->post('lastname');
			General::logfile('Patient Management','UPDATE',$value);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Patient successfully Updated!</div>");
			
			//redirect
			redirect(base_url().'app/patient',$this->data);
			
			
		}else{
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-exclamation-circle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Update failed.</strong> Please correct the highlighted errors and try again.</div>");
			// user restriction function
				$this->session->set_userdata('page_name','modiffy_patient');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function
				
			$this->data['patientInfo'] = $this->patient_model->getPatient($this->input->post('id')); 
			$this->load->view('app/patient/editPatient',$this->data);	
		}	
	}
	
	public function attachment($id){
		$this->data['patientAttachment'] = $this->patient_model->getPatientAttachment($id); 
		$this->data['patient_no'] = $id;
		$this->data['error'] = "";
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/patient/attachment',$this->data);	
	}
	
	public function remove_attachment(){
		$attach_id = $this->uri->segment("4");
		$patient_no = $this->uri->segment("5");	
		
		
		
		$this->db->where('attach_id',$attach_id);
		$this->data = array("InActive"=>"1");
		$this->db->update("patient_attachment",$this->data);
		
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Attachment successfully removed!</div>");
		redirect(base_url().'app/patient/attachment/'.$patient_no,$this->data);
	}
	
	public function addAttachment($id){
		$this->data['patient_no'] = $id;
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/patient/addAttachment',$this->data);	
	}
	
	public function upload_attachment(){
		$config = array(
					'allowed_types'		=>		'pdf|jpg|jpeg|png|gif|doc|docx|xls|xlsx',
					'upload_path'		=>		realpath('public/patient_attachment'),
					'max_size'			=>		3000
					);
		
		$this->load->library('upload', $config);
		
		if(!$this->upload->do_upload()){
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>".$this->upload->display_errors()."</div>");
			redirect(base_url().'app/patient/addAttachment/'.$this->input->post('patient_no'),$this->data);
		}else{
			
			$image_data = $this->upload->data();
			$this->patient_model->uploadAttachment($image_data,$this->input->post('patient_no'));
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Attachment successfully Uploaded</div>");
			redirect(base_url().'app/patient/attachment/'.$this->input->post('patient_no'),$this->data);
		}
	}
	
	public function upload_picture($id){
		$this->data['patient_no'] = $id;
		$this->data['patient'] = $this->patient_model->getPatient($id); 
		$this->data['error'] = "";
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/patient/upload_picture',$this->data);	
	}
	
	public function upload_na(){
		$config = array(
					'allowed_types'		=>		'jpg|jpeg|gif|png',
					'upload_path'		=>		realpath('public/patient_picture'),
					'max_size'			=>		2000
					);
		
		$this->load->library('upload', $config);
		
		if(!$this->upload->do_upload()){
			//$this->session->set_flashdata('message',"<div class='alert alert-block'><a class='close' data-dismiss='alert' href='#'>&times;</a>".$this->upload->display_errors()."</div>");
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>".$this->upload->display_errors()."</div>");
			redirect(base_url().'app/patient/upload_picture/'.$this->input->post('patient_no'),$this->data);
		}else{
			
			$image_data = $this->upload->data();
			$this->patient_model->uploadImg($image_data,$this->input->post('patient_no'));
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Profile successfully Uploaded</div>");
			redirect(base_url().'app/patient/upload_picture/'.$this->input->post('patient_no'),$this->data);
		}
	}
	
	
	
	
	
	
	
	
	
	/**
	 * AJAX: Verify NHIS card eligibility.
	 * POST: nhis_number, patient_no (optional)
	 * Returns JSON: {success, message, status, expiry_date, scheme, name}
	 */
	public function nhis_verify_ajax(){
		header('Content-Type: application/json');
		if (!General::is_logged_in()){
			echo json_encode(array('success' => false, 'message' => 'Session expired.'));
			return;
		}
		$nhis_number = strtoupper(trim((string)$this->input->post('nhis_number')));
		$patient_no  = trim((string)$this->input->post('patient_no'));
		if ($nhis_number === ''){
			echo json_encode(array('success' => false, 'message' => 'Please enter an NHIS number.'));
			return;
		}
		$this->load->model('app/billing_model');
		$this->billing_model->ensure_nhis_day5_schema();
		$result = $this->billing_model->nhis_api_check_eligibility(
			$nhis_number,
			($patient_no !== '') ? $patient_no : null
		);
		// Normalise: some versions return 'eligible' instead of 'success'
		if (!isset($result['success']) && isset($result['eligible'])){
			$result['success'] = (bool)$result['eligible'];
		}
		// Normalise: some versions return 'member_name' instead of 'name'
		if (!isset($result['name']) && isset($result['member_name'])){
			$result['name'] = $result['member_name'];
		}
		echo json_encode($result);
	}
}	