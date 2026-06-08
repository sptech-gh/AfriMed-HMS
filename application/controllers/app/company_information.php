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
		
		// Load facility settings for the view
		$this->data['facilitySettings'] = array();
		if ($this->db->table_exists('facility_settings')) {
			$q = $this->db->get('facility_settings');
			if ($q) {
				$this->data['facilitySettings'] = $q->row_array();
			}
		}
		
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/general/company_information',$this->data);
	}
	
	public function save()
	{
		$this->load->library('brandinginstaller');
		// Ensure directories are installed and default assets exist
		$this->brandinginstaller->install();
		
		$logo = $this->input->post('old_logo');
		$header_logo = $this->input->post('old_header_logo');
		$login_logo = $this->input->post('old_login_logo');
		$isUpload = TRUE;
		$msg = array();

		$allowed = 'jpg|jpeg|png|gif|svg';
		$upload_dir = FCPATH . 'uploads/facility_logos/default/';

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
			if (!isset($this->upload)) {
				$this->load->library('upload', $config);
			} else {
				$this->upload->initialize($config);
			}
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
			if (!isset($this->upload)) {
				$this->load->library('upload', $config);
			} else {
				$this->upload->initialize($config);
			}
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

			// 1) Save to facility_settings table
			$facility_data = array(
				'facility_name'       => $this->input->post('company_name'),
				'facility_short_name' => $this->input->post('site_title'),
				'facility_tagline'    => $this->input->post('hospital_tagline'),
				'logo_path'           => $logo,
				'logo_dark'           => $login_logo,
				'logo_light'          => $header_logo,
				'address'             => $this->input->post('company_address'),
				'phone'               => $this->input->post('contact'),
				'email'               => $this->input->post('company_email'),
				'website'             => $this->input->post('website'),
				'tin'                 => $this->input->post('tin'),
				'registration_number' => $this->input->post('registration_number'),
				'footer_note'         => $this->input->post('footer_note'),
				'updated_at'          => date('Y-m-d H:i:s')
			);

			$query = $this->db->get('facility_settings');
			if ($query && $query->num_rows() > 0) {
				$row = $query->row();
				$this->db->where('id', $row->id);
				$result = $this->db->update('facility_settings', $facility_data);
			} else {
				$facility_data['created_at'] = date('Y-m-d H:i:s');
				$result = $this->db->insert('facility_settings', $facility_data);
			}

			// 2) Keep legacy company_info in sync for absolute safety
			if ($this->db->table_exists('company_info')) {
				$legacy_data = array(
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
				$this->db->update("company_info", $legacy_data);
			}
			
			if ($result)
			{
				// Refresh assets in uploads/facility_logos/default/ using installer
				$this->brandinginstaller->install();
				
				$value = $this->input->post('company_name');
				General::logfile('Company Information', 'UPDATE', $value);
				array_push($msg, "Facility branding settings saved successfully.");
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