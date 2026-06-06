<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Emr extends General{

	private $limit = 10;

	public function __construct(){
		parent::__construct();
		$this->load->model("app/opd_model");
		$this->load->model("app/ipd_model");
		$this->load->model("app/doctor_model");
		$this->load->model("general_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		require_role('doctor');
	}
	
	public function times(){
		$this->load->view("app/emr/time",$this->data);
	}
	
	public function opd($offset = 0){
				 
		$this->session->set_userdata(array(
				 'tab'			=>		'emr',
				 'module'		=>		'opd_emr',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
				 
				// user restriction function - Doctors always have EMR access
				$this->session->set_userdata('page_name','opd_emr');
				if (!has_role(array('admin', 'doctor'))) {
					$page_id = $this->general_model->getPageID();
					$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
					if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
						redirect(base_url().'access_denied');
					}
				}
				// end of user restriction function		 
				 
		$this->session->set_userdata('emr_viewing','opd_emr_viewing');
	 
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$patient = $this->opd_model->getAll($this->limit, $offset);
		
		$config['base_url'] = base_url().'app/opd/index/';
 		$config['total_rows'] = $this->opd_model->count_all();
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
	
		$tmpl = array('table_open' => '<table class="table table-hover table-striped" id="opdTable">');
        $this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		
		// Check if user is admin for action column
		$isAdmin = has_role('admin');
		if ($isAdmin) {
			$this->table->set_heading('OPD No','Patient No','Patient Name','Age', 'Insurance','Visit Date Time','Department','Consultant Doctor','Status','Action');
		} else {
			$this->table->set_heading('OPD No','Patient No','Patient Name','Age', 'Insurance','Visit Date Time','Department','Consultant Doctor','Status');
		}
		$i = 0 + $offset;
		
		
		foreach ($patient as $patient)
		{	
				if($patient->nStatus == "Pending"){ 
					$nStatus = '<span class="label label-warning">Pending</span>';
					$actionBtn = '<button class="btn btn-xs btn-danger close-visit-btn" data-iop="'.$patient->IO_ID.'" data-patient="'.$patient->patient_no.'" data-name="'.$patient->name.'"><i class="fa fa-times"></i> Close</button>';
				}else{ 
					$nStatus = '<span class="label label-success">Discharged</span>';
					$actionBtn = '<button class="btn btn-xs btn-info reopen-visit-btn" data-iop="'.$patient->IO_ID.'" data-patient="'.$patient->patient_no.'" data-name="'.$patient->name.'"><i class="fa fa-refresh"></i> Reopen</button>';
				}
				
			$insurance = '';
			if(!empty($patient->Insurance_comp)){
				$insData = $this->opd_model->getInsurance($patient->Insurance_comp);
				$insurance = ($insData && isset($insData->company_name)) ? $insData->company_name : '';
			}

			if ($isAdmin) {
				$this->table->add_row( 
									anchor('app/opd/view/'.$patient->IO_ID.'/'.$patient->patient_no,$patient->IO_ID),
									anchor('app/patient/view/'.$patient->patient_no,$patient->patient_no),
									$patient->name, 
									$patient->age, 
									$insurance,
									date('M d, Y',strtotime($patient->date_visit))." ".date('H:i:s',strtotime($patient->time_visit)), 
									$patient->dept_name, 
									$patient->doctor,
									$nStatus,
									$actionBtn
				);
			} else {
				$this->table->add_row( 
									anchor('app/opd/view/'.$patient->IO_ID.'/'.$patient->patient_no,$patient->IO_ID),
									anchor('app/patient/view/'.$patient->patient_no,$patient->patient_no),
									$patient->name, 
									$patient->age, 
									$insurance,
									date('M d, Y',strtotime($patient->date_visit))." ".date('H:i:s',strtotime($patient->time_visit)), 
									$patient->dept_name, 
									$patient->doctor,
									$nStatus
				);
			}
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->data['closure_reasons'] = $this->opd_model->get_closure_reasons();
		$this->data['visit_stats'] = $this->opd_model->get_visit_statistics();
		$this->data['is_admin'] = $isAdmin;

		$this->load->view('app/emr/opd',$this->data);	
		
	}
	
	public function ipd($offset = 0){
			$this->session->set_userdata(array(
				 'tab'			=>		'emr',
				 'module'		=>		'ipd_emr',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
				 
				// user restriction function - Doctors always have EMR access
				$this->session->set_userdata('page_name','ipd_emr');
				if (!has_role(array('admin', 'doctor'))) {
					$page_id = $this->general_model->getPageID();
					$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
					if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
						redirect(base_url().'access_denied');
					}
				}
				// end of user restriction function		 
				 
		$this->session->set_userdata('emr_viewing','ipd_emr_viewing');
				 
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$patient = $this->ipd_model->getAll($this->limit, $offset, true);
		
		$config['base_url'] = base_url().'app/opd/search_result/';
 		$config['total_rows'] = $this->ipd_model->count_all();
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
		$this->table->set_heading('IPD No','Patient No','Patient Name', 'Insurance','Date Admit','Department','Room & Bed No.','Incharge Doctor','Status');
		$i = 0 + $offset;
		
		
		foreach ($patient as $patient)
		{	
				if($patient->nStatus == "Pending"){ 
					$nStatus = "Pending";
				}else{ 
					$nStatus = "Discharged";
				}
					
			$insurance = '';
			if(!empty($patient->Insurance_comp)){
				$insData = $this->opd_model->getInsurance($patient->Insurance_comp);
				$insurance = ($insData && isset($insData->company_name)) ? $insData->company_name : '';
			}

				$this->table->add_row( 
									anchor('app/ipd/view/'.$patient->IO_ID.'/'.$patient->patient_no,$patient->IO_ID),
									anchor('app/patient/view/'.$patient->patient_no,$patient->patient_no),
									$patient->name, 
									$insurance,
									date('M d, Y',strtotime($patient->date_visit))." ".date('H:i:s',strtotime($patient->time_visit)), 
									$patient->dept_name, 
									"Rm ".$patient->room_name." Bed No.".$patient->bed_name, 
									$patient->doctor,
									$nStatus
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/emr/ipd',$this->data);	
	}
	
}