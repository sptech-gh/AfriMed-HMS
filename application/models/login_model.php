<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Login_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function validate_login(){
		$username = $this->input->post('username');
		$password = $this->input->post('password');
		
		$this->db->select("username, password");
		$this->db->where(array(
			'username'  => $username,
			'InActive'  => 0
		));
		$query = $this->db->get('users');
		
		if ($query->num_rows() == 1) {
			$user = $query->row();
			$stored_hash = $user->password;
			
			// Support both bcrypt (new) and MD5 (legacy) for migration period
			if (password_verify($password, $stored_hash)) {
				return true;
			}
			// Legacy MD5 check - auto-upgrade to bcrypt on successful login
			if (strlen($stored_hash) == 32 && $stored_hash === md5($password)) {
				$this->upgrade_password_hash($username, $password);
				return true;
			}
		}
		return false;
	}
	
	/**
	 * Upgrade legacy MD5 password to bcrypt
	 */
	private function upgrade_password_hash($username, $plain_password){
		$new_hash = password_hash($plain_password, PASSWORD_BCRYPT);
		$this->db->where('username', $username);
		$this->db->update('users', array('password' => $new_hash));
	}
	
	/**
	 * Hash a new password using bcrypt
	 */
	public function hash_password($plain_password){
		return password_hash($plain_password, PASSWORD_BCRYPT);
	}
	
	/**
	 * Verify password against stored hash (supports bcrypt and legacy MD5)
	 */
	public function verify_password($plain_password, $stored_hash){
		if (password_verify($plain_password, $stored_hash)) {
			return true;
		}
		// Legacy MD5 fallback
		if (strlen($stored_hash) == 32 && $stored_hash === md5($plain_password)) {
			return true;
		}
		return false;
	}
	
	public function getMyModule($user_id){
		$this->db->select("B.module");
		$this->db->where("user_id",$user_id);
		$this->db->join("user_roles B","B.role_id = A.user_role","left outer");
		$query = $this->db->get("users A");
		return $query->row();	
	}
	
	
}