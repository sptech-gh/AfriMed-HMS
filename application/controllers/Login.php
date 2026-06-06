<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Login extends General{

	function __construct(){
		parent::__construct();	
		$this->load->model('login_model');
		$this->load->model('general_model');
		$this->load->model('app/governance_model');
		$this->load->helper('rbac');
		General::variable();
		$this->governance_model->ensure_governance_schema();
		$this->governance_model->ensure_default_roles_and_users();
	}
	
	public function index(){
		if($this->session->userdata('is_logged_in')){
            redirect(base_url().'app/dashboard');
        }else{
            $this->login();        
        }      
	}
	
	function login($destroy_session = true){
		if ($destroy_session) {
			$this->session->unset_userdata(array(
				'username'      => '',
				'is_logged_in'  => false,
				'user_id'		=> ''
			));
			$this->session->sess_destroy();
		}
		$this->load->view("login",$this->data);		
	}

	function loginNow($username,$password){
		$this->data['usernamelogin'] = $username;
		$this->data['passwordlogin'] = $password;
		$this->load->view("login",$this->data);		
	}
	
	function validate_credentials(){
		if($this->login_model->validate_login()){
			return true;	
		}else{
			$this->form_validation->set_message("validate_credentials","Invalid Login");
			return false;
		}
	}
	
	public function validate_login(){
		$username = $this->input->post('username');
		
		// Check brute force protection
		if ($this->_is_login_blocked($username)) {
			$this->session->set_flashdata('login_error', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-lock'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Account temporarily locked due to too many failed attempts. Please try again later.</div>");
			$this->login();
			return;
		}
		
		$this->form_validation->set_rules("username","Username","trim|xss_clean|required|callback_validate_credentials");	
		$this->form_validation->set_rules("password","Password","trim|xss_clean|required");	
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");
		
		if($this->form_validation->run()){
			// Clear failed login attempts on successful login
			$this->_clear_login_attempts($username);
			
			$user_info = $this->general_model->getUserLoggedIn($this->input->post('username'));
			$rbac_key = _rbac_normalise_module(
				isset($user_info->module) ? $user_info->module : '',
				isset($user_info->user_role) ? (int)$user_info->user_role : 0
			);

			// Load dynamic privilege role keys and privilege names
			$dynRoleKeys = $this->governance_model->get_dynamic_role_keys($user_info->user_id);
			$privilegeNames = $this->governance_model->get_user_privilege_names($user_info->user_id);

			// Regenerate session ID to prevent session fixation attacks
			$this->session->sess_regenerate(TRUE);

			$this->data = $this->session->set_userdata(array(
                    'username'          =>          $this->input->post('username'),
                    'user_role'         =>          $user_info->user_role,
                    'is_logged_in'      =>          true,
					'user_id'			=>			$user_info->user_id,
					'department'		=>			$user_info->department_id,
					'rbac_module'		=>			$rbac_key,
					'rbac_role_name'	=>			isset($user_info->role_name) ? $user_info->role_name : '',
					'dynamic_role_keys'	=>			$dynRoleKeys,
					'user_privilege_names' =>		$privilegeNames,
					'privileges_loaded_at' =>		date('Y-m-d H:i:s')
             )); 
			 
			 $userModule = $this->login_model->getMyModule($this->session->userdata('user_id'));
			 redirect(base_url().'app/dashboard',$this->data);
			
        }else{
			// Record failed login attempt
			$this->_record_failed_login($username);
            $this->login(false);
        }
	}
	
	/**
	 * Check if login is blocked due to too many failed attempts
	 */
	private function _is_login_blocked($username){
		$this->_ensure_login_attempts_table();
		
		$lockout_time = 15 * 60; // 15 minutes lockout
		$max_attempts = 5;
		$cutoff = date('Y-m-d H:i:s', time() - $lockout_time);
		
		$this->db->where('username', $username);
		$this->db->where('attempt_time >', $cutoff);
		$this->db->where('successful', 0);
		$count = $this->db->count_all_results('login_attempts');
		
		return ($count >= $max_attempts);
	}
	
	/**
	 * Record a failed login attempt
	 */
	private function _record_failed_login($username){
		$this->_ensure_login_attempts_table();
		
		$this->db->insert('login_attempts', array(
			'username' => $username,
			'ip_address' => $this->input->ip_address(),
			'attempt_time' => date('Y-m-d H:i:s'),
			'successful' => 0
		));
	}
	
	/**
	 * Clear failed login attempts after successful login
	 */
	private function _clear_login_attempts($username){
		$this->_ensure_login_attempts_table();
		
		$this->db->where('username', $username);
		$this->db->delete('login_attempts');
	}
	
	/**
	 * Ensure login_attempts table exists
	 */
	private function _ensure_login_attempts_table(){
		$query = $this->db->query("SHOW TABLES LIKE 'login_attempts'");
		if ($query->num_rows() == 0) {
			$this->db->query("CREATE TABLE `login_attempts` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`username` varchar(100) NOT NULL,
				`ip_address` varchar(45) NOT NULL,
				`attempt_time` datetime NOT NULL,
				`successful` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_username_time` (`username`, `attempt_time`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}
	}
	
	public function logout(){
        $this->session->unset_userdata(array(
                'username'          =>      '',
                'is_logged_in'      =>      false,
				'user_id'			=>		'',
				'rbac_module'		=>		'',
				'rbac_role_name'	=>		''
        ));
        $this->session->sess_destroy();    
        // redirect(base_url().'login');
        redirect(base_url().'');
    }
	
}

















