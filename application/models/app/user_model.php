<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class User_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function getAll($limit = 10, $offset = 0){
		$this->db->select("A.user_id,
					concat(D.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as name,
					C.designation,
					B.dept_name,
					A.email_address,
					A.InActive",false);
		// Escape search term to prevent SQL injection
		$search = $this->db->escape_like_str($this->session->userdata("search_user"));
		$where = "(A.lastname like '%".$search."%' 
					or A.firstname like '%".$search."%' 
					or A.user_id like '%".$search."%' 
					or C.designation like '%".$search."%' 
					or B.dept_name like '%".$search."%' 
					or A.email_address like '%".$search."%' 
					or A.InActive like '%".$search."%')";
		$this->db->where($where);
		$this->db->join("department B","B.department_id = A.department","left outer");
		$this->db->join("designation C","C.designation_id = A.designation","left outer");
		$this->db->join("system_parameters D","D.param_id = A.title","left outer");
		$this->db->order_by('A.user_id','asc');
		$query = $this->db->get("users A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->select("A.user_id,
					concat(D.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as name,
					C.designation,
					B.dept_name,
					A.email_address,
					A.InActive",false);
		// Escape search term to prevent SQL injection
		$search = $this->db->escape_like_str($this->session->userdata("search_user"));
		$where = "(A.lastname like '%".$search."%' 
					or A.firstname like '%".$search."%' 
					or A.user_id like '%".$search."%' 
					or C.designation like '%".$search."%' 
					or B.dept_name like '%".$search."%' 
					or A.email_address like '%".$search."%' 
					or A.InActive like '%".$search."%')";
		$this->db->where($where);
		$this->db->join("department B","B.department_id = A.department","left outer");
		$this->db->join("designation C","C.designation_id = A.designation","left outer");
		$this->db->join("system_parameters D","D.param_id = A.title","left outer");
		$this->db->order_by('A.user_id','asc');
		$query = $this->db->get("users A");
		return $query->num_rows();
	}
	
	public function lastUserID(){
		$this->db->select("(cValue + 1) as cValue");
		$this->db->where(array('cCode'=>'employee_no','InActive'=>0));
		$query = $this->db->get("system_option");
		return $query->row();	
	}
	
	
	public function validate_username(){
		$this->db->select("username");
		$this->db->where(array(
			'username'		=>		$this->input->post('username'),
			'InActive'		=>		0
		));	
		$query = $this->db->get("users");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}	
	}
	
	public function validate_email(){
		$this->db->select("email_address");
		$this->db->where(array(
			'email_address'	=>	$this->input->post('email'),
			'InActive'		=>	0
		));	
		$query = $this->db->get("users");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}	
	}
	
	public function validate_name(){
		$this->db->select("lastname");
		$this->db->where(array(
			'lastname'		=>		$this->input->post('lastname'),
			'firstname'		=>		$this->input->post('firstname'),
			'InActive'		=>		0
		));	
		$query = $this->db->get("users");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}	
	}
	
	public function save_user(){
		$age = 0;
		$birthday = $this->input->post('birthday');
		
		// Only calculate age if birthday is provided
		if(!empty($birthday)){
			$dob = strtotime($birthday);
			if($dob !== false){
				$tdate = strtotime(date("Y-m-d"));
				while($tdate > $dob = strtotime('+1 year', $dob))
				{
					++$age;
				}
			}
		}
		
		// Hash password with bcrypt before storing
		$plain_password = $this->input->post('password');
		$hashed_password = password_hash($plain_password, PASSWORD_BCRYPT);
		
		$this->data = array(
			'user_id'			=>		$this->input->post('userid'),
			'department'		=>		(int)$this->input->post('department'),
			'designation'		=>		(int)$this->input->post('designation'),
			'user_role'			=>		(int)$this->input->post('user_role'),
			'cType'				=>		$this->input->post('cType'),
			'title'				=>		(int)$this->input->post('title'),
			'lastname'			=>		$this->input->post('lastname'),
			'firstname'			=>		$this->input->post('firstname'),
			'middlename'		=>		$this->input->post('middlename'),
			'age'				=>		$age,
			'street'			=>		$this->input->post('noofhouse'),
			'subd_brgy'			=>		$this->input->post('brgy'),
			'province'			=>		$this->input->post('province'),
			'phone_no'			=>		$this->input->post('phone'),
			'mobile_no'			=>		$this->input->post('mobile'),
			'gender'			=>		(int)$this->input->post('gender'),
			'civil_status'		=>		(int)$this->input->post('civil_status'),
			'birthday'			=>		!empty($birthday) ? $birthday : NULL,
			'birthplace'		=>		$this->input->post('birthplace'),
			'email_address'		=>		$this->input->post('email'),
			'username'			=>		$this->input->post('username'),
			'password'			=>		$hashed_password,
			'InActive'			=>		0
		);	
		$this->db->insert("users",$this->data);
		
	}
	
	
	public function updateAutoNum(){
		$this->db->where(array(
			'cCode'			=>		'employee_no',
			'InActive'		=>		0
		));	
		$this->data = array('cValue'	=>		$this->input->post('userID2'));
		$this->db->update("system_option",$this->data);
	}
	
	public function getUser($id){
		$this->db->where("user_id",$id);
		$query = $this->db->get("users");
		return $query->row();	
	}
	
	public function validate_username_edit(){
		$this->db->select("username");
		$this->db->where(array(
			'username'		=>		$this->input->post('username'),
			'InActive'		=>		0,
			'user_id !='	=>		$this->input->post('userid')
		));	
		$query = $this->db->get("users");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}	
	}
	
	public function validate_email_edit(){
		$this->db->select("email_address");
		$this->db->where(array(
			'email_address'	=>	$this->input->post('email'),
			'InActive'		=>	0,
			'user_id !='	=>		$this->input->post('userid')
		));	
		$query = $this->db->get("users");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}	
	}
	
	public function validate_name_edit(){
		$this->db->select("lastname");
		$this->db->where(array(
			'lastname'		=>		$this->input->post('lastname'),
			'firstname'		=>		$this->input->post('firstname'),
			'InActive'		=>		0,
			'user_id !='	=>		$this->input->post('userid')
		));	
		$query = $this->db->get("users");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}	
	}
	
	public function edit_user(){
		$age = 0;
		$birthday = $this->input->post('birthday');
		
		// Only calculate age if birthday is provided
		if(!empty($birthday)){
			$dob = strtotime($birthday);
			if($dob !== false){
				$tdate = strtotime(date("Y-m-d"));
				while($tdate > $dob = strtotime('+1 year', $dob))
				{
					++$age;
				}
			}
		}
		
		$this->data = array(
			'department'		=>		(int)$this->input->post('department'),
			'designation'		=>		(int)$this->input->post('designation'),
			'user_role'			=>		(int)$this->input->post('user_role'),
			'cType'				=>		$this->input->post('cType'),
			'title'				=>		(int)$this->input->post('title'),
			'lastname'			=>		$this->input->post('lastname'),
			'firstname'			=>		$this->input->post('firstname'),
			'middlename'		=>		$this->input->post('middlename'),
			'age'				=>		$age,
			'street'			=>		$this->input->post('noofhouse'),
			'subd_brgy'			=>		$this->input->post('brgy'),
			'province'			=>		$this->input->post('province'),
			'phone_no'			=>		$this->input->post('phone'),
			'mobile_no'			=>		$this->input->post('mobile'),
			'gender'			=>		(int)$this->input->post('gender'),
			'civil_status'		=>		(int)$this->input->post('civil_status'),
			'birthday'			=>		!empty($birthday) ? $birthday : NULL,
			'birthplace'		=>		$this->input->post('birthplace'),
			'username'			=>		$this->input->post('username'),
			'email_address'		=>		$this->input->post('email')
		);	
		$this->db->where('user_id', $this->input->post('userid'));
		$this->db->update("users",$this->data);
	}
	
	public function delete($id){
		$this->db->where('user_id',$id);
		$this->data = array('InActive'=>1);
		$this->db->update("users",$this->data);	
	}
	
	public function activate($id){
		$this->db->where('user_id',$id);
		$this->data = array('InActive'=>0);
		$this->db->update("users",$this->data);	
	}
	
	public function uploadImg($image_data = array(),$emp_id){
		$this->data = array(
			'picture'	=>		$image_data['file_name']
		);
		$this->db->where('user_id',$emp_id);
		$this->db->update('users',$this->data);
		

	}

	public function changepassword()
	{
		$new_password = $this->input->post('newpassword');
		$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
		
		$this->data = array(
			'password' => $hashed_password
		);	
		$this->db->where('user_id', $this->input->post('userid'));
		$result = $this->db->update("users", $this->data);

		if ($result) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	/**
	 * Hash password using bcrypt for new user creation
	 */
	public function hash_password($plain_password){
		return password_hash($plain_password, PASSWORD_BCRYPT);
	}
	
	
	
	
	
	
	
	
}