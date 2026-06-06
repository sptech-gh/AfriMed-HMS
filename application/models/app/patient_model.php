<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Patient_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}

	private function _normalize_date_ymd($raw)
	{
		$s = trim((string)$raw);
		if ($s === '') {
			return null;
		}
		if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
			return $s;
		}
		if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
			$a = (int)$m[1];
			$b = (int)$m[2];
			$y = (int)$m[3];
			if ($y < 1900 || $y > 2100) {
				return null;
			}
			$month = $a;
			$day = $b;
			if ($a > 12 && $b <= 12) {
				$day = $a;
				$month = $b;
			} elseif ($b > 12 && $a <= 12) {
				$month = $b;
				$day = $a;
			}
			if (!checkdate($month, $day, $y)) {
				return null;
			}
			return sprintf('%04d-%02d-%02d', $y, $month, $day);
		}
		$ts = strtotime($s);
		if ($ts !== false) {
			return date('Y-m-d', $ts);
		}
		return null;
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table_name, $column_name){
		$table_name = (string) $table_name;
		$column_name = (string) $column_name;
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		return ($q && $q->num_rows() > 0);
	}

	/* ── NHIS schema & helpers ─────────────────────────── */

	public function ensure_nhis_schema(){
		if (!$this->table_exists('patient_personal_info')) {
			return false;
		}
		$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old_debug !== null) { $this->db->db_debug = false; }

		if (!$this->column_exists('patient_personal_info', 'nhis_number')) {
			$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `nhis_number` varchar(30) DEFAULT NULL");
			$this->db->query("ALTER TABLE `patient_personal_info` ADD KEY `idx_nhis_number` (`nhis_number`)");
		}
		if (!$this->column_exists('patient_personal_info', 'nhis_status')) {
			$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `nhis_status` varchar(20) DEFAULT NULL");
			$this->db->query("ALTER TABLE `patient_personal_info` ADD KEY `idx_nhis_status` (`nhis_status`)");
		}
		if (!$this->column_exists('patient_personal_info', 'nhis_expiry_date')) {
			$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `nhis_expiry_date` date DEFAULT NULL");
		}
		if (!$this->column_exists('patient_personal_info', 'emergency_fullname')) {
			$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `emergency_fullname` varchar(100) DEFAULT NULL");
		}
		if (!$this->column_exists('patient_personal_info', 'emergency_phone_number')) {
			$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `emergency_phone_number` varchar(25) DEFAULT NULL");
		}

		if ($old_debug !== null) { $this->db->db_debug = $old_debug; }
		return true;
	}

	public function install_nhis_audit_table(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `nhis_patient_audit` (
			`audit_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`patient_no` varchar(25) NOT NULL,
			`field_name` varchar(50) NOT NULL,
			`old_value` varchar(255) DEFAULT NULL,
			`new_value` varchar(255) DEFAULT NULL,
			`changed_by` varchar(25) DEFAULT NULL,
			`changed_at` datetime NOT NULL,
			`ip_address` varchar(45) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`audit_id`),
			KEY `idx_nhis_audit_patient` (`patient_no`),
			KEY `idx_nhis_audit_date` (`changed_at`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function normalize_nhis_status($status){
		$status = strtoupper(trim((string)$status));
		$allowed = array('ACTIVE', 'EXPIRED', 'INVALID');
		if (!in_array($status, $allowed, true)) {
			return null;
		}
		return $status;
	}

	public function compute_nhis_status($nhis_number, $nhis_expiry_date){
		$nhis_number = trim((string)$nhis_number);
		if ($nhis_number === '') {
			return null;
		}
		if ($nhis_expiry_date === null || trim((string)$nhis_expiry_date) === '' || trim((string)$nhis_expiry_date) === '0000-00-00') {
			return 'ACTIVE';
		}
		$exp = strtotime((string)$nhis_expiry_date);
		if ($exp === false) {
			return 'ACTIVE';
		}
		$today = strtotime(date('Y-m-d'));
		return ($exp >= $today) ? 'ACTIVE' : 'EXPIRED';
	}

	public function get_nhis_info($patient_no){
		$patient_no = (string)$patient_no;
		$this->ensure_nhis_schema();
		$cols = array();
		if ($this->column_exists('patient_personal_info', 'nhis_number')) { $cols[] = 'nhis_number'; }
		if ($this->column_exists('patient_personal_info', 'nhis_status')) { $cols[] = 'nhis_status'; }
		if ($this->column_exists('patient_personal_info', 'nhis_expiry_date')) { $cols[] = 'nhis_expiry_date'; }
		if (empty($cols)) {
			return (object)array('nhis_number' => null, 'nhis_status' => null, 'nhis_expiry_date' => null);
		}
		$this->db->select(implode(',', $cols));
		$q = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no, 'InActive' => 0));
		$r = $q ? $q->row() : null;
		return (object)array(
			'nhis_number'      => ($r && isset($r->nhis_number)) ? $r->nhis_number : null,
			'nhis_status'      => ($r && isset($r->nhis_status)) ? $r->nhis_status : null,
			'nhis_expiry_date' => ($r && isset($r->nhis_expiry_date)) ? $r->nhis_expiry_date : null
		);
	}

	public function update_nhis_info($patient_no, $nhis_number, $nhis_expiry_date, $changed_by = null){
		$patient_no = (string)$patient_no;
		$this->ensure_nhis_schema();
		$this->install_nhis_audit_table();

		$nhis_number = trim((string)$nhis_number);
		if ($nhis_number === '') { $nhis_number = null; }
		$nhis_expiry_date = trim((string)$nhis_expiry_date);
		if ($nhis_expiry_date === '' || $nhis_expiry_date === '0000-00-00') { $nhis_expiry_date = null; }

		$nhis_status = $this->compute_nhis_status($nhis_number, $nhis_expiry_date);

		$old = $this->get_nhis_info($patient_no);

		$now = date('Y-m-d H:i:s');
		$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;

		$fields = array(
			'nhis_number'      => $nhis_number,
			'nhis_status'      => $nhis_status,
			'nhis_expiry_date' => $nhis_expiry_date
		);
		foreach ($fields as $fname => $newVal) {
			$oldVal = isset($old->$fname) ? $old->$fname : null;
			if ((string)$oldVal !== (string)$newVal) {
				$this->db->insert('nhis_patient_audit', array(
					'patient_no' => $patient_no,
					'field_name' => $fname,
					'old_value'  => $oldVal,
					'new_value'  => $newVal,
					'changed_by' => $changed_by,
					'changed_at' => $now,
					'ip_address' => $ip,
					'InActive'   => 0
				));
			}
		}

		$update = array();
		if ($this->column_exists('patient_personal_info', 'nhis_number'))      { $update['nhis_number'] = $nhis_number; }
		if ($this->column_exists('patient_personal_info', 'nhis_status'))      { $update['nhis_status'] = $nhis_status; }
		if ($this->column_exists('patient_personal_info', 'nhis_expiry_date')) { $update['nhis_expiry_date'] = $nhis_expiry_date; }
		if (!empty($update)) {
			$this->db->where('patient_no', $patient_no);
			$this->db->where('InActive', 0);
			$this->db->update('patient_personal_info', $update);
		}
		return $nhis_status;
	}

	public function is_nhis_active($patient_no){
		$info = $this->get_nhis_info($patient_no);
		if ($info->nhis_number === null || trim((string)$info->nhis_number) === '') {
			return false;
		}
		$computed = $this->compute_nhis_status($info->nhis_number, $info->nhis_expiry_date);
		return ($computed === 'ACTIVE');
	}

	/* ── End NHIS ────────────────────────────────────────── */

	public function ensure_insurance_card_status_schema(){
		if (!$this->table_exists('patient_personal_info')) {
			return false;
		}
		if (!$this->column_exists('patient_personal_info', 'insurance_card_status')) {
			$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($old_debug !== null) {
				$this->db->db_debug = false;
			}
			$this->db->query("ALTER TABLE `patient_personal_info` ADD COLUMN `insurance_card_status` varchar(20) DEFAULT 'ACTIVE'");
			$this->db->query("ALTER TABLE `patient_personal_info` ADD KEY `idx_ins_card_status` (`insurance_card_status`)");
			if ($old_debug !== null) {
				$this->db->db_debug = $old_debug;
			}
		}
		return true;
	}

	public function normalize_insurance_card_status($status){
		$status = strtoupper(trim((string)$status));
		if ($status === '0') {
			$status = 'INACTIVE';
		}
		if ($status === '1') {
			$status = 'ACTIVE';
		}
		// N/A is valid for Self Pay patients
		if ($status !== 'ACTIVE' && $status !== 'INACTIVE' && $status !== 'N/A') {
			$status = 'ACTIVE';
		}
		return $status;
	}

	public function get_insurance_card_status($patient_no){
		$patient_no = (string)$patient_no;
		if (!$this->table_exists('patient_personal_info')) {
			return 'ACTIVE';
		}
		if (!$this->column_exists('patient_personal_info', 'insurance_card_status')) {
			return 'ACTIVE';
		}
		$this->db->select('insurance_card_status');
		$q = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no, 'InActive' => 0));
		$r = $q ? $q->row() : null;
		$st = ($r && isset($r->insurance_card_status)) ? (string)$r->insurance_card_status : 'ACTIVE';
		return $this->normalize_insurance_card_status($st);
	}

	public function update_insurance_card_status($patient_no, $status){
		$patient_no = (string)$patient_no;
		$status = $this->normalize_insurance_card_status($status);
		$this->ensure_insurance_card_status_schema();
		if (!$this->column_exists('patient_personal_info', 'insurance_card_status')) {
			return false;
		}
		$this->db->where('patient_no', $patient_no);
		$this->db->where('InActive', 0);
		$this->db->update('patient_personal_info', array('insurance_card_status' => $status));
		return true;
	}
	
	public function getAll($limit = 10, $offset = 0){
		

		$this->db->select("
			A.patient_no,
			concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
			C.cValue as 'gender',
			D.cValue as 'civil_status',
			A.age,
			A.emergency_fullname,
			A.emergency_phone_number,
			A.date_entry
		",false);
		$search = trim((string)$this->session->userdata("search_patient_master"));
		if ($search === '' && $this->input->post('search') !== null) {
			$search = trim((string)$this->input->post('search'));
		}
		$this->db->where('A.InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.lastname', $search);
			$this->db->or_like('A.firstname', $search);
			$this->db->or_like('A.middlename', $search);
			$this->db->or_like('A.patient_no', $search);
			$this->db->or_like('A.insurance_no', $search);
			$this->db->or_like('A.id_identifiers', $search);
			$this->db->or_like('C.cValue', $search);
			$this->db->or_like('D.cValue', $search);
			$this->db->group_end();
		}
		$this->db->order_by('lastname','asc');
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("system_parameters C","C.param_id = A.gender","left outer");
		$this->db->join("system_parameters D","D.param_id = A.civil_status","left outer");
		$query = $this->db->get("patient_personal_info A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		$this->db->select("
			A.patient_no,
			concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
			C.cValue,
			D.cValue,
			A.age,
			A.emergency_fullname,
			A.emergency_phone_number,
			A.date_entry
		",false);
		$search = trim((string)$this->session->userdata("search_patient_master"));
		if ($search === '' && $this->input->post('search') !== null) {
			$search = trim((string)$this->input->post('search'));
		}
		$this->db->where('A.InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.lastname', $search);
			$this->db->or_like('A.firstname', $search);
			$this->db->or_like('A.middlename', $search);
			$this->db->or_like('A.patient_no', $search);
			$this->db->or_like('A.insurance_no', $search);
			$this->db->or_like('A.id_identifiers', $search);
			$this->db->or_like('C.cValue', $search);
			$this->db->or_like('D.cValue', $search);
			$this->db->group_end();
		}
		$this->db->order_by('lastname','asc');
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("system_parameters C","C.param_id = A.gender","left outer");
		$this->db->join("system_parameters D","D.param_id = A.civil_status","left outer");
		$query = $this->db->get("patient_personal_info A");
		return $query->num_rows();
	}
	
	public function lastPatientID(){
		$this->db->select("(cValue + 1) as patient_no");
		$this->db->where("cCode","patient_no");
		$query = $this->db->get("system_option");	
		return $query->row();
	}
	
	public function validate_email(){
		$this->db->where(array(
			'email_address'		=>		$this->input->post('email'),
			'InActive'			=>		0
		));
		$query = $this->db->get("patient_personal_info");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function validate_patient(){
		$birthday = $this->_normalize_date_ymd($this->input->post('birthday'));
		$this->db->where(array(
			'lastname'		=>		$this->input->post('lastname'),
			'firstname'		=>		$this->input->post('firstname'),
			'birthday'		=>		$birthday,
			'InActive'		=>		0
		));
		$query = $this->db->get("patient_personal_info");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function save(){
		$birthday = $this->_normalize_date_ymd($this->input->post('birthday'));
		$age = 0;
		if ($birthday !== null) {
			$dob = strtotime($birthday);
			if ($dob !== false) {
				$tdate = strtotime(date('Y-m-d'));
				while( $tdate > $dob = strtotime('+1 year', $dob))
				{
						++$age;
				}
			}
		}
		
		$this->ensure_nhis_schema();
		$this->install_nhis_audit_table();

		$nhis_number_raw = trim((string)$this->input->post('nhis_number'));
		$nhis_expiry_raw = trim((string)$this->input->post('nhis_expiry_date'));
		$nhis_status_computed = $this->compute_nhis_status($nhis_number_raw, $nhis_expiry_raw);

		$this->data = array(
			'patient_no'		=>		$this->input->post('patientID'),
			'title'				=>		$this->input->post('title'),
			'lastname'			=>		$this->input->post('lastname'),
			'firstname'			=>		$this->input->post('firstname'),
			'middlename'		=>		$this->input->post('middlename'),
			'gender'			=>		$this->input->post('gender'),
			'civil_status'		=>		$this->input->post('civil_status'),
			'birthday'			=>		$birthday,
			'birthplace'		=>		$this->input->post('birthplace'),
			'address2'			=>		$this->input->post('address2'),
			'age'				=>		$age,
			'religion'			=>		$this->input->post('religion'),
			'street'			=>		$this->input->post('noofhouse'),
			'subd_brgy'			=>		$this->input->post('brgy'),
			'province'			=>		$this->input->post('province'),
			'phone_no_office'	=>		$this->input->post('phone_office'),
			'phone_no'			=>		$this->input->post('phone'),
			'mobile_no'			=>		$this->input->post('mobile'),
			'email_address'		=>		$this->input->post('email'),
			'date_entry'		=>		date("Y-m-d h:i:s"),
			'blood_group'		=>		$this->input->post('bloodGroup'),
			'Insurance_comp'	=>		$this->input->post('insurance_comp'),
			'insurance_no'		=>		$this->input->post('insurance_id'),
			'emergency_fullname'	=>		$this->input->post('emergency_fullname'),
			'emergency_phone_number'	=>		$this->input->post('emergency_phone_number'),
			'InActive'			=>		0
		);
		if ($this->column_exists('patient_personal_info', 'nhis_number')) {
			$this->data['nhis_number'] = ($nhis_number_raw !== '') ? $nhis_number_raw : null;
		}
		if ($this->column_exists('patient_personal_info', 'nhis_status')) {
			$this->data['nhis_status'] = $nhis_status_computed;
		}
		if ($this->column_exists('patient_personal_info', 'nhis_expiry_date')) {
			$this->data['nhis_expiry_date'] = ($nhis_expiry_raw !== '' && $nhis_expiry_raw !== '0000-00-00') ? $nhis_expiry_raw : null;
		}
		$this->db->insert("patient_personal_info",$this->data);

		if ($nhis_number_raw !== '') {
			$changedBy = null;
			if (isset($this->session) && is_object($this->session) && method_exists($this->session, 'userdata')) {
				$changedBy = (string)$this->session->userdata('user_id');
			}
			$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
			$this->db->insert('nhis_patient_audit', array(
				'patient_no' => $this->input->post('patientID'),
				'field_name' => 'nhis_number',
				'old_value'  => null,
				'new_value'  => $nhis_number_raw,
				'changed_by' => $changedBy,
				'changed_at' => date('Y-m-d H:i:s'),
				'ip_address' => $ip,
				'InActive'   => 0
			));
		}
		
	}
	
	public function updateAutoNum(){
		$this->db->where(array(
			'cCode'			=>		'patient_no',
			'InActive'		=>		0
		));	
		$this->data = array('cValue'	=>		$this->input->post('userID2'));
		$this->db->update("system_option",$this->data);
	}
	
	public function getPatient($id){
		$query = $this->db->get_where("patient_personal_info", array('patient_no' => $id));	
		return $query->row();
	}
	
	public function validate_patient_edit(){
		$birthday = $this->_normalize_date_ymd($this->input->post('birthday'));
		$this->db->where(array(
			'lastname'		=>		$this->input->post('lastname'),
			'firstname'		=>		$this->input->post('firstname'),
			'patient_no !='	=>		$this->input->post('id'),
			'birthday'		=>		$birthday,
			'InActive'		=>		0
		));
		$query = $this->db->get("patient_personal_info");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function validate_email_edit(){
		$this->db->where(array(
			'email_address'		=>		$this->input->post('email'),
			'patient_no !='		=>		$this->input->post('id'),
			'InActive'			=>		0
		));
		$query = $this->db->get("patient_personal_info");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function update(){
		$birthday = $this->_normalize_date_ymd($this->input->post('birthday'));
		$age = 0;
		if ($birthday !== null) {
			$dob = strtotime($birthday);
			if ($dob !== false) {
				$tdate = strtotime(date('Y-m-d'));
				while( $tdate > $dob = strtotime('+1 year', $dob))
				{
						++$age;
				}
			}
		}

		$patient_no = $this->input->post('id');
		$nhis_number_raw = trim((string)$this->input->post('nhis_number'));
		$nhis_expiry_raw = trim((string)$this->input->post('nhis_expiry_date'));

		$changedBy = null;
		if (isset($this->session) && is_object($this->session) && method_exists($this->session, 'userdata')) {
			$changedBy = (string)$this->session->userdata('user_id');
		}
		$this->update_nhis_info($patient_no, $nhis_number_raw, $nhis_expiry_raw, $changedBy);
		
		$this->data = array(
			'title'				=>		$this->input->post('title'),
			'lastname'			=>		$this->input->post('lastname'),
			'firstname'			=>		$this->input->post('firstname'),
			'middlename'		=>		$this->input->post('middlename'),
			'gender'			=>		$this->input->post('gender'),
			'civil_status'		=>		$this->input->post('civil_status'),
			'birthday'			=>		$birthday,
			'birthplace'		=>		$this->input->post('birthplace'),
			'age'				=>		$age,
			'religion'			=>		$this->input->post('religion'),
			'street'			=>		$this->input->post('noofhouse'),
			'subd_brgy'			=>		$this->input->post('brgy'),
			'province'			=>		$this->input->post('province'),
			'phone_no_office'	=>		$this->input->post('phone_office'),
			'phone_no'			=>		$this->input->post('phone'),
			'mobile_no'			=>		$this->input->post('mobile'),
			'email_address'		=>		$this->input->post('email'),
			'blood_group'		=>		$this->input->post('bloodGroup'),
			'Insurance_comp'	=>		$this->input->post('insurance_comp'),
			'insurance_no'		=>		$this->input->post('insurance_id'),
			'emergency_fullname'	=>		$this->input->post('emergency_fullname'),
			'emergency_phone_number'	=>		$this->input->post('emergency_phone_number')
		);
		$this->db->where("patient_no",$patient_no);
		$this->db->update("patient_personal_info",$this->data);
		
	}
	
	public function getPatientAttachment($id){
		$this->db->select("
			A.date_uploaded,
			A.attach_id,
			A.description,
			A.file_name,
			A.file_type,
			A.file_size,
			A.patient_no,
			concat(B.firstname,' ',B.lastname) as name
		",false);
		$this->db->where(array(
			'A.patient_no'	=>		$id,
			'A.InActive'		=>		0
		));
		$this->db->join("users B","B.user_id = A.uploaded_by","left outer");
		$query = $this->db->get("patient_attachment A");
		return $query->result();
	}
	
	
	public function uploadAttachment($image_data = array(),$emp_id){
		$this->data = array(
			'patient_no'		=>		$this->input->post('patient_no'),
			'date_uploaded'		=>		date("Y-m-d h:i:s"),
			'uploaded_by'		=>		$this->session->userdata('user_id'),
			'description'		=>		$this->input->post('description'),
			'file_name'			=>		$image_data['file_name'],
			'file_type'			=>		$image_data['file_type'],
			'file_size'			=>		$image_data['file_size'],
			'InActive'			=>		0
		);
		$this->db->insert('patient_attachment',$this->data);
		

	}
	
	public function uploadImg($image_data = array(),$emp_id){
		$this->data = array(
			'picture'	=>		$image_data['file_name']
		);
		$this->db->where('patient_no',$emp_id);
		$this->db->update('patient_personal_info',$this->data);
		

	}
	
	public function getPatientHistory($id){
		$this->db->select("allergies,warnings,social_history,family_history,personal_history,past_medical_history");
		$this->db->where("patient_no",$id);
		$this->db->order_by('IO_ID','desc');
		$query = $this->db->get("patient_details_iop");
		return $query->row();
	}
	
	public function getInsurance($id){
		$this->db->select("company_name");
		$this->db->where("in_com_id",$id);
		$this->db->order_by('in_com_id','desc');
		$query = $this->db->get("insurance_comp");
		return $query->row();
	}
	
	public function getPatientInfo($id){
		$insStatusSelect = "'ACTIVE'";
		if ($this->column_exists('patient_personal_info', 'insurance_card_status')) {
			$insStatusSelect = "A.insurance_card_status";
		}
		$nhisNumberSelect = "NULL";
		$nhisStatusSelect = "NULL";
		$nhisExpirySelect = "NULL";
		if ($this->column_exists('patient_personal_info', 'nhis_number')) {
			$nhisNumberSelect = "A.nhis_number";
		}
		if ($this->column_exists('patient_personal_info', 'nhis_status')) {
			$nhisStatusSelect = "A.nhis_status";
		}
		if ($this->column_exists('patient_personal_info', 'nhis_expiry_date')) {
			$nhisExpirySelect = "A.nhis_expiry_date";
		}
		$this->db->select("
			A.patient_no,
			A.lastname,
			A.firstname,
			A.middlename,
			concat(IFNULL(B.cValue,''),' ',A.firstname,' ',IFNULL(A.middlename,''),' ',A.lastname) as 'name',
			A.picture,
			A.age,
			A.street,
			A.subd_brgy,
			A.province,
			A.address2,
			TRIM(CONCAT_WS(', ', NULLIF(TRIM(IFNULL(A.street,'')), ''), NULLIF(TRIM(IFNULL(A.address2,'')), ''), NULLIF(TRIM(IFNULL(A.subd_brgy,'')), ''), NULLIF(TRIM(IFNULL(A.province,'')), ''))) as 'address',
			A.birthday,
			A.birthplace,
			C.cValue as 'gender',
			D.cValue as 'civil_status',
			A.date_entry,
			A.phone_no,
			A.phone_no_office,
			A.mobile_no,
			A.email_address,
			A.InActive,
			A.emergency_fullname,
			A.emergency_phone_number,
			F.cValue as 'blood_group',
			A.Insurance_comp,
			A.insurance_no,
			".$insStatusSelect." as insurance_card_status,
			".$nhisNumberSelect." as nhis_number,
			".$nhisStatusSelect." as nhis_status,
			".$nhisExpirySelect." as nhis_expiry_date,
			A.id_identifiers,
			E.cValue as 'religion',
			G.company_name
		",false);
		$this->db->where("A.patient_no",$id);
		$this->db->order_by('lastname','asc');
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("system_parameters C","C.param_id = A.gender","left outer");
		$this->db->join("system_parameters D","D.param_id = A.civil_status","left outer");
		$this->db->join("system_parameters E","E.param_id = A.religion","left outer");
		$this->db->join("system_parameters F","F.param_id = A.blood_group","left outer");
		$this->db->join("insurance_comp G","G.in_com_id = A.Insurance_comp","left outer");
		$query = $this->db->get("patient_personal_info A");
		return $query->row();
	}
	
	
	
	
	
	
}