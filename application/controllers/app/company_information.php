<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Company_information extends General{

	private $limit = 10;

	public function __construct(){
		parent::__construct();
		$this->load->model("general_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
		require_role('admin');
	}
	
	public function index(){
		// user restriction function
				$this->session->set_userdata('page_name','company_information');
				$page_id = $this->general_model->getPageID();
				$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
				if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
					redirect(base_url().'access_denied');
				}
				// end of user restriction function		
				
				 
		$this->session->set_userdata(array(
				 'tab'			=>		'admin',
				 'module'		=>		'company_information',
				 'subtab'		=>		'',
				 'submodule'	=>		''));
		
		$this->data['companyInfo'] = $this->general_model->companyInfo();
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/general/company_information',$this->data);
	}
	
	public function save()
	{
		$logo = $this->input->post('old_logo');
		$header_logo = $this->input->post('old_header_logo');
		$login_logo = $this->input->post('old_login_logo');
		$isUpload = TRUE;
		$msg = array();

		$allowed = 'jpg|jpeg|png|gif|svg';
		$upload_dir = realpath('public/company_logo');

		// Main logo upload
		if (!empty($_FILES['logo']['name']))
		{
			$config = array(
				'allowed_types' => $allowed,
				'upload_path'   => $upload_dir,
				'max_size'      => 2000
			);
			$this->load->library('upload', $config);
			if ($this->upload->do_upload('logo'))
			{
				$upload_data = $this->upload->data();
				$logo = $upload_data['file_name'];
			} else {
				array_push($msg, $this->upload->display_errors());
				$isUpload = FALSE;
			}
		}

		// Header logo upload
		if (!empty($_FILES['header_logo']['name']))
		{
			$config = array(
				'allowed_types' => $allowed,
				'upload_path'   => $upload_dir,
				'max_size'      => 2000
			);
			$this->upload->initialize($config);
			if ($this->upload->do_upload('header_logo'))
			{
				$upload_data = $this->upload->data();
				$header_logo = $upload_data['file_name'];
			} else {
				array_push($msg, $this->upload->display_errors());
				$isUpload = FALSE;
			}
		}

		// Login logo upload
		if (!empty($_FILES['login_logo']['name']))
		{
			$config = array(
				'allowed_types' => $allowed,
				'upload_path'   => $upload_dir,
				'max_size'      => 2000
			);
			$this->upload->initialize($config);
			if ($this->upload->do_upload('login_logo'))
			{
				$upload_data = $this->upload->data();
				$login_logo = $upload_data['file_name'];
			} else {
				array_push($msg, $this->upload->display_errors());
				$isUpload = FALSE;
			}
		}

		if ($isUpload) 
		{
			$theme_default = $this->input->post('theme_default');
			if ($theme_default !== 'dark' && $theme_default !== 'light') {
				$theme_default = 'light';
			}

			$this->data = array(
				'company_name'       => $this->input->post('company_name'),
				'company_address'    => $this->input->post('company_address'),
				'company_contactNo'  => $this->input->post('contact'),
				'company_email'      => $this->input->post('company_email'),
				'TIN'                => $this->input->post('tin'),
				'logo'               => $logo,
				'header_logo'        => $header_logo,
				'login_logo'         => $login_logo,
				'site_title'         => $this->input->post('site_title'),
				'hospital_tagline'   => $this->input->post('hospital_tagline'),
				'theme_default'      => $theme_default,
				'updated_at'         => date('Y-m-d H:i:s')
			);
			$result = $this->db->update("company_info", $this->data);
			
			if ($result)
			{
				$value = $this->input->post('company_name');
				General::logfile('Company Information', 'UPDATE', $value);
				array_push($msg, "Branding settings saved successfully.");
			}
			else
			{
				array_push($msg, "Error saving data. Please try again.");
			}
		}

		$alertClass = $isUpload ? 'alert-success' : 'alert-warning';
		$this->session->set_flashdata('message', "<div class='alert {$alertClass} alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . implode("<br>", $msg) . "</div>");
		redirect(base_url() . 'app/company_information', $this->data);
	}
	
	
	
	
	
	
	
	
	
	
	
}