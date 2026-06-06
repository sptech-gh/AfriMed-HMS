<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Particular_bill extends General{

	private $limit = 10;

	public function __construct(){
		parent::__construct();
		$this->load->model("app/particular_bills_model");
		$this->load->model("app/room_master_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		require_role('admin');
	}
	
	public function index(){
		// user restriction function
		$this->session->set_userdata('page_name','particular_bill');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
			redirect(base_url().'access_denied');
		}
		// end of user restriction function
				
		//$this->session->set_userdata(array('tab'=>'admin', 'module'=>'designation'));
		$this->session->set_userdata(array(
				 'tab'			=>		'admin',
				 'module'		=>		'particular_bill',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
		
		$this->particular_bill();
	}
	
	public function particular_bill($offset = 0){
		if($this->session->userdata('search_particular_bill')){

		}else{

		}
		
		$this->session->set_userdata('search_particular_bill',$this->input->post('search'));	

		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		
		$bill_particular = $this->particular_bills_model->getAll($this->limit, $offset);
		
		$config['base_url'] = base_url().'app/particular_bill/index/';
 		$config['total_rows'] = $this->particular_bills_model->count_all();
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
		$this->table->set_heading('Particular Name', 'Group Name','Description','Amount','Action');
		$i = 0 + $offset;
		
		
		foreach ($bill_particular as $bill_particular)
		{	
				$deleteForm = '<form method="post" action="'.base_url().'app/particular_bill/delete/'.$bill_particular->particular_id.'" style="display:inline;" onsubmit="return confirm(\'Are you sure want to delete?\')">'
					.'<input type="hidden" name="'.$this->security->get_csrf_token_name().'" value="'.$this->security->get_csrf_hash().'">'
					.'<button type="submit" class="delete btn btn-xs btn-danger">Delete</button>'
					.'</form>';
				$this->table->add_row( 
									anchor('app/particular_bill/view/'.$bill_particular->particular_id,$bill_particular->particular_name),
									$bill_particular->group_name, 
									$bill_particular->particular_desc, 
									$bill_particular->charge_amount, 
									anchor('app/particular_bill/edit/'.$bill_particular->particular_id,'Edit').'&nbsp|&nbsp;'.
									$deleteForm
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/particular_bill/index',$this->data);	
	}
	
	public function add(){
		// user restriction function
				$this->session->set_userdata('page_name','add_particular_bill');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function
		$this->data['group_name'] = $this->particular_bills_model->group_name();
				
		$this->load->view('app/particular_bill/add', $this->data);		
	}
	
	public function validate_particular_name(){
		if($this->particular_bills_model->validate_particular_name()){
			$this->form_validation->set_message("validate_particular_name","Particular Name Already Exists.");
			return false;
		}else{
			return true;
		}
	}
	
	public function save(){
		$this->form_validation->set_rules("partcular_name","Particular Name","trim|xss_clean|required|callback_validate_particular_name");
		$this->form_validation->set_rules("description","Description","trim|xss_clean|required");
		$this->form_validation->set_rules("amount","Amount","trim|xss_clean|required|decimal");	
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		if($this->form_validation->run()){
			
			//save the data
			$this->particular_bills_model->save();
			
			//save price
			$this->data['a'] = array(
				'nRef_ID'		=>		$this->db->insert_id(),	
				'price'			=>		$this->input->post('amount'),	
				'updatedBy'		=>		$this->session->userdata('user_id'),	
				'dDate'			=>		date("Y-m-d h:i:s")
			);
			$this->db->insert("price_history",$this->data['a']);
			
			$value = $this->input->post('partcular_name');
			General::logfile('Particular Bill','INSERT',$value);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Particular Bill Name successfully Added!</div>");
			
			//redirect
			redirect(base_url().'app/particular_bill',$this->data);
			
			
		}else{
			// Validation failed - reload add form with posted data
			$this->data['posted'] = (object)array(
				'group_id' => $this->input->post('group_name'),
				'particular_name' => $this->input->post('partcular_name'),
				'particular_desc' => $this->input->post('description'),
				'charge_amount' => $this->input->post('amount'),
				'is_nhis_covered' => $this->input->post('is_nhis_covered'),
				'nhis_charge_amount' => $this->input->post('nhis_charge_amount')
			);
			$this->add();	
		}
	
	}
	
	public function edit($id = 0){
		if(isset($_POST['btnSubmit'])){
			
			$this->edit_save();
			
		}else{
			// Validate ID
			$id = (int)$id;
			if ($id <= 0) {
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Invalid bill particular ID.</div>");
				redirect(base_url().'app/particular_bill');
			}
			
			// user restriction function
				$this->session->set_userdata('page_name','update_particular_bill');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function
			
			$this->data['group_name'] = $this->particular_bills_model->group_name();	
			$this->data['bill_particular'] = $this->particular_bills_model->getBillName($id);
			
			// Check if record exists
			if (!$this->data['bill_particular'] || !is_object($this->data['bill_particular'])) {
				$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Bill particular record not found.</div>");
				redirect(base_url().'app/particular_bill');
			}
			
			$this->load->view('app/particular_bill/edit',$this->data);	
		}
	}
	
	public function validate_particular_name_edit(){
		if($this->particular_bills_model->validate_particular_name_edit()){
			$this->form_validation->set_message("validate_particular_name_edit","Particular Name Already Exists.");
			return false;
		}else{
			return true;
		}
	}
	
	public function edit_save(){
		$this->form_validation->set_rules("partcular_name","Particular Name","trim|xss_clean|required|callback_validate_particular_name_edit");
		$this->form_validation->set_rules("description","Description","trim|xss_clean|required");
		$this->form_validation->set_rules("amount","Amount","trim|xss_clean|required|decimal");	
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
	
		if($this->form_validation->run()){
			
			//save the data
			$this->particular_bills_model->edit_save();
			
			//save price
			$this->data['a'] = array(
				'nRef_ID'		=>		$this->input->post('id'),	
				'price'			=>		$this->input->post('amount'),	
				'updatedBy'		=>		$this->session->userdata('user_id'),	
				'dDate'			=>		date("Y-m-d h:i:s")
			);
			$this->db->insert("price_history",$this->data['a']);
			
			$value = $this->input->post('partcular_name');
			General::logfile('Particular Bill','UPDATE',$value);
			
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Particular Bill Name successfully Updated!</div>");
			
			//redirect
			redirect(base_url().'app/particular_bill',$this->data);
			
			
		}else{
			// Validation failed - reload form with data
			$id = (int)$this->input->post('id');
			
			// user restriction function
				$this->session->set_userdata('page_name','update_particular_bill');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function
			
			$this->data['group_name'] = $this->particular_bills_model->group_name();	
			$this->data['bill_particular'] = $id > 0 ? $this->particular_bills_model->getBillName($id) : null;
			
			// If we can't load the record but have an ID, create a minimal object to preserve form data
			if (!$this->data['bill_particular'] && $id > 0) {
				$this->data['bill_particular'] = (object)array(
					'particular_id' => $id,
					'group_id' => $this->input->post('group_name'),
					'particular_name' => $this->input->post('partcular_name'),
					'particular_desc' => $this->input->post('description'),
					'charge_amount' => $this->input->post('amount'),
					'is_nhis_covered' => $this->input->post('is_nhis_covered'),
					'nhis_charge_amount' => $this->input->post('nhis_charge_amount')
				);
			}
			
			$this->load->view('app/particular_bill/edit',$this->data);	
		}
	
	}
	
	
	public function delete($id){
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

				// user restriction function
				$this->session->set_userdata('page_name','delete_particular_bill');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function
			
		$this->particular_bills_model->delete($id);
		
		$value = $id;
		General::logfile('Particular Bill','DELETE',$value);
				
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Particular Bill successfully Deleted!</div>");
			
		//redirect
		redirect(base_url().'app/particular_bill',$this->data);
	}
	
	public function view($id){
		$this->data['getPriceHistory'] = $this->room_master_model->getPriceHistory($id); 
		$this->data['bill_particular'] = $this->particular_bills_model->getBillName($id); 
		$this->load->view('app/particular_bill/view',$this->data);	
	}
	
	
	
	
	
	
	
	
	
	

	
	
	
}