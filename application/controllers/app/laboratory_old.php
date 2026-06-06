<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Laboratory_old extends General
{

	private $limit = 10;

	public function __construct()
	{
		parent::__construct();
		$this->load->model("app/laboratory_model");
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		redirect(base_url().'access_denied');
		return;
	}

	public function index()
	{
		// user restriction function
		$this->session->set_userdata('page_name', 'access_laboratory_module');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			redirect(base_url() . 'access_denied');
		}
		// end of user restriction function

		//$this->session->set_userdata(array('tab'=>'admin', 'module'=>'designation'));
		$this->session->set_userdata(array(
			'tab'			=>		'laboratory',
			'module'		=>		'iopd_laboratory',
			'subtab'		=>		'',
			'submodule'	=>		''
		));

		$this->pending_laboratory_requests();
	}

	public function pending_laboratory_requests($offset = 0)
	{
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);

		// $laboratory = $this->laboratory_model->getAll($this->limit, $offset);
		$laboratory_requests = $this->laboratory_model->pending_lab_requests($this->limit, $offset);

		$config['base_url'] = base_url() . 'app/laboratory/index/';
		$config['total_rows'] = $this->laboratory_model->count_all_pending_request();
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
		$this->table->set_heading('Labs Request', 'Patient ID', 'Patient Name', 'Age','Request Date');
		$i = 0 + $offset;


		foreach ($laboratory_requests as $laboratory_requests) {
			// if($laboratory->particular_name){$labs = $laboratory->particular_name;}else{$labs = $laboratory->laboratory_text;}
			$this->table->add_row(
				// $laboratory_requests->iop_id,
				anchor('app/laboratory/request/' . $laboratory_requests->iop_id, $laboratory_requests->iop_id),
				// $labs, 
				// $laboratory->particular_name,
				$laboratory_requests->patient_no,
				$laboratory_requests->patient_name,
				$this->birth_day_age($laboratory_requests->birthday),
				$laboratory_requests->dDate
				// $laboratory->findings,
				// $laboratory->result
				// anchor('app/complain/edit/'.$laboratory_requests->io_lab_id,'Process')
				// anchor('app/complain/delete/'.$laboratory->io_lab_id,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/laboratory/index', $this->data);
	}


	public function request($offset = 0)
	{
		$uri_segment = 5;
		$offset = $this->uri->segment($uri_segment);
		$iop_id = $this->uri->segment(4);
		// print_r($offset);

		$laboratory = $this->laboratory_model->getAll($this->limit, $offset, $iop_id);
		// $laboratory = $this->laboratory_model->pending_lab_requests($this->limit, $offset);

		$config['base_url'] = base_url() . 'app/laboratory/resquest/';
		$config['total_rows'] = $this->laboratory_model->count_all();
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
		$this->table->set_heading('Lab Request', 'Patient ID', 'Patient Name', 'Age', 'Findings', 'Results', 'Request Date', 'Action');
		$i = 0 + $offset;


		foreach ($laboratory as $laboratory) {
			if ($laboratory->particular_name) {
				$labs = $laboratory->particular_name;
			} else {
				$labs = $laboratory->laboratory_text;
			}
			$this->table->add_row(
				// $laboratory->io_lab_id, 
				anchor('app/laboratory/results/' . $laboratory->io_lab_id . '/' . $laboratory->iop_id . '/' . $labs, $labs),
				// $labs, 
				// $laboratory->particular_name,
				$laboratory->patient_no,
				$laboratory->patient_name,
				$this->birth_day_age($laboratory->birthday),
				$laboratory->findings,
				$laboratory->result,
				$laboratory->dDate,
				anchor('app/laboratory/delete/'.$laboratory->io_lab_id,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
				// anchor('app/complain/edit/'.$laboratory->io_lab_id,'Edit').'&nbsp|&nbsp;'.
				// anchor('app/complain/edit/'.$laboratory->io_lab_id,'Edit').'&nbsp|&nbsp;'.
				// anchor('app/complain/delete/'.$laboratory->io_lab_id,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/laboratory/request', $this->data);
	}

	public function results()
	{
		// // user restriction function
		// 		$this->session->set_userdata('page_name','add_lab_results');
		// 		$page_id = $this->general_model->getPageID();
		// 		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		// 		if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
		// 			redirect(base_url().'access_denied');
		// 		}
		// 		// end of user restriction function

		$this->data['lab'] = $this->uri->segment(4);
		$this->data['lab_patient'] = $this->uri->segment(5);
		$this->data['lab_request_name'] = $this->uri->segment(6);

		$this->load->view('app/laboratory/results', $this->data);
	}

	public function lab_enquiry()
	{
		if (isset($_POST['btnSearch'])) {
			// echo "<script>alert('aassghh kjgj')</script>";
			// $this->data['reports_title'] = "OPD Patient Diagnosis Reports";
			// $this->data['patientInfo'] = $this->reports_model->patientInfo();
			// $this->data['patientvisited'] = $this->reports_model->patientvisited();	

			// if($this->input->post('cType') == "browser"){
			// 	$this->load->view('app/reports/patient_visited_result',$this->data);	
			// }else{
			// 	$this->load->helper('file');
			// 	$this->load->helper('dompdf');  

			// 	$html = $this->load->view('app/reports_result_pdf/patient_visited_result', $this->data, true);
			// 	pdf_create($html, 'patient_visited', TRUE);
			// }


			// $uri_segment = 4;
			// $offset = $this->uri->segment($uri_segment);

			// $laboratory = $this->laboratory_model->getAll($this->limit, $offset);
			// $laboratory_requests = $this->laboratory_model->pending_lab_requests($this->limit, $offset);
			// $laboratory_requests = $this->laboratory_model->lab_enquiry($this->limit, $offset);
			$laboratory_requests = $this->laboratory_model->lab_enquiry();

			// $config['base_url'] = base_url().'app/laboratory/lab_enquiry/';
			// $config['total_rows'] = count($laboratory_requests);
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
			$this->table->set_heading('Labs Request', 'Patient ID', 'Patient Name', 'Request Date');
			// $i = 0 + $offset;


			foreach ($laboratory_requests as $laboratory_requests) {
				// if($laboratory->particular_name){$labs = $laboratory->particular_name;}else{$labs = $laboratory->laboratory_text;}
				$this->table->add_row(
					// $laboratory_requests->iop_id,
					anchor('app/laboratory/request/' . $laboratory_requests->iop_id, $laboratory_requests->iop_id),
					// $labs, 
					// $laboratory->particular_name,
					$laboratory_requests->patient_no,
					$laboratory_requests->patient_name,
					$laboratory_requests->dDate
					// $laboratory->result
					// anchor('app/complain/edit/'.$laboratory_requests->io_lab_id,'Process')
					// anchor('app/complain/delete/'.$laboratory->io_lab_id,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
				);
			}
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['table'] = $this->table->generate();

			$this->load->view('app/laboratory/lab_enquiry', $this->data);
		} else {
			// user restriction function
			$this->session->set_userdata('page_name', 'lab_enquiry');
			$page_id = $this->general_model->getPageID();
			$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
				redirect(base_url() . 'access_denied');
			}
			// end of user restriction function

			$this->session->set_userdata(array(
				'tab'			=>		'laboratory',
				'module'		=>		'lab_enquiry',
				'subtab'		=>		'',
				'submodule'	=>		''
			));

			// $this->data['lab_enquiry_list'] = $this->reports_model->patient_list();

			$this->load->view('app/laboratory/lab_enquiry', $this->data);
		}
	}

	// public function validate_complain(){
	// 	if($this->complain_model->validate_complain()){
	// 		$this->form_validation->set_message("validate_complain","Complain Name Already Exists.");
	// 		return false;
	// 	}else{
	// 		return true;
	// 	}
	// }

	// public function validate_complain_edit(){
	// 	if($this->complain_model->validate_complain_edit()){
	// 		$this->form_validation->set_message("validate_complain_edit","Complain Name Already Exists.");
	// 		return false;
	// 	}else{
	// 		return true;
	// 	}
	// }

	// public function save($id = null){
	// 	$this->form_validation->set_rules("result","Result","trim|xss_clean|required");
	// 	$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");

	// 	if($this->form_validation->run()){

	// 		//save the data
	// 		$this->laboratory_model->save();

	// 		$value = $this->input->post('complain');
	// 		General::logfile('Complain','INSERT',$value);

	// 		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Complain successfully Added!</div>");

	// 		//redirect
	// 		redirect(base_url().'app/complain',$this->data);


	// 	}else{
	// 		$this->results();	
	// 	}

	// }


	public function save_result($io_lab_id, $iop_id, $lab_request_name)
	{
		if (isset($_POST['btnSubmit'])) {
			// var_dump($this->input->post('result_upload'));
			$findings = $this->input->post('findings');
			$result = $this->input->post('result');
			if (empty($findings) && empty($result)) {
				// print_r($lab_request_name);
				// $this->load->view('app/laboratory/results/'.$this->input->post('io_lab_id').'/'.$this->input->post('iop_id').'/'.$this->input->post('lab_name'));
				// $this->load->view('app/laboratory/results/'.$io_lab_id.'/'.$iop_id.'/'.$lab_request_name,$this->data);
				redirect(base_url() . 'app/laboratory/request/' . $iop_id);
			} else {
				$this->edit_save();
			}
		} else {
			// // user restriction function
			// $this->session->set_userdata('page_name','update_complain');
			// $page_id = $this->general_model->getPageID();
			// $userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			// if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
			// 	redirect(base_url().'access_denied');
			// }
			// // end of user restriction function

			$this->data['lab'] = $this->data['lab'] = $this->uri->segment(4);
			$this->data['lab_patient'] = $this->uri->segment(5);
			$this->data['lab_request_name'] = $this->uri->segment(6);
			$this->load->view('app/laboratory/results/' . $io_lab_id . '/' . $iop_id . '/' . $lab_request_name, $this->data);
		}
	}

	public function edit_save()
	{
		$this->form_validation->set_rules("result", "Result", "trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>", "</div>");

		if ($this->form_validation->run()) {

			$this->laboratory_model->edit_save();

			$value = $this->input->post('io_lab_id');
			General::logfile('Labs', 'UPDATE', $value);

			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Lab successfully Recorded!</div>");
			// var_dump($this->input->post('iop_id'));
			//redirect
			redirect(base_url() . 'app/laboratory/request/' . $this->input->post('iop_id'), $this->data);
		} else {

			// // user restriction function
			// 	$this->session->set_userdata('page_name','update_complain');
			// 	$page_id = $this->general_model->getPageID();
			// 	$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
			// 	if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
			// 		redirect(base_url().'access_denied');
			// 	}
			// 	// end of user restriction function

			$this->data['lab'] = $this->data['lab'] = $this->uri->segment(4);
			$this->data['lab_patient'] = $this->uri->segment(5);
			$this->data['lab_request_name'] = $this->uri->segment(6);
			$this->load->view('app/laboratory/results/' . $this->input->post('io_lab_id') . '/' . $this->input->post('iop_id') . '/' . $this->input->post('lab_request_name'), $this->data);
		}
	}


	public function upload_results($id)
	{
		$this->data['lab'] = $id;
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/upload_results', $this->data);
	}


	public function upload_lab_result()
	{
		$config = array(
			'allowed_types'		=>		'pdf|jpg|jpeg|png|gif|doc|docx|xls|xlsx',
			'upload_path'		=>		realpath('public/patient_lab_result'),
			'max_size'			=>		3000
		);

		$this->load->library('upload', $config);
		// var_dump($this->load->library('upload', $config));

		if (!$this->upload->do_upload('result_upload')) {
			//$this->session->set_flashdata('message',"<div class='alert alert-block'><a class='close' data-dismiss='alert' href='#'>&times;</a>".$this->upload->display_errors()."</div>");

			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $this->upload->display_errors() . "</div>");
			redirect(base_url() . 'app/laboratory/upload_results/' . $this->input->post('io_lab_id'), $this->data);
		} else {

			$lab_data = $this->upload->data();

			$this->laboratory_model->upload_lab_result_pdf($lab_data, $this->input->post('io_lab_id'));
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Lab result successfully Uploaded</div>");
			// redirect(base_url().'app/laboratory/request/'.$this->input->post('iop_id'),$this->data);
			redirect(base_url() . 'app/laboratory/upload_results/' . $this->input->post('io_lab_id'), $this->data);
		}
	}

	public function birth_day_age($dob){
		// $dob='1993-07-01';
		$year = (date('Y') - date('Y',strtotime($dob)));
		return $year;
	}



	public function delete($id)
	{
		// // user restriction function
		// $this->session->set_userdata('page_name', 'delete_lab_request');
		// $page_id = $this->general_model->getPageID();
		// $userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		// if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
		// 	redirect(base_url() . 'access_denied');
		// }
		// // end of user restriction function

		$this->laboratory_model->delete($id);

		$value = $id;
		General::logfile('Lab Request', 'DELETE', $value);

		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Lab Request successfully Deleted!</div>");

		//redirect
		redirect(base_url() . 'app/laboratory/index', $this->data);
	}
}
