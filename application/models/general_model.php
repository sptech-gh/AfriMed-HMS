<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class General_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
		date_default_timezone_set("Africa/Accra");
	}

	private function ensure_ghana_title_names(){
		if (schema_already_run('ghana_title_names_schema')) {
			return;
		}
		if (schema_already_run('system_parameters_schema')) {
			// system_parameters table exists, proceed with title names setup
		} else {
			if (!$this->db->table_exists('system_parameters')) {
				return;
			}
			mark_schema_run('system_parameters_schema');
		}
		mark_schema_run('ghana_title_names_schema');
		$code = 'title_name';
		$desired = array(
			'Mr.',
			'Mrs.',
			'Miss.',
			'Ms.',
			'Dr.',
			'Prof.',
			'Rev.',
			'Pastor',
			'Imam',
			'Hon.',
			'Nana',
			'Chief',
			'Master',
			'Baby'
		);
		$variants = array(
			'mr.' => 'Mr.',
			'mr' => 'Mr.',
			'mr .' => 'Mr.',
			'mrs.' => 'Mrs.',
			'mrs' => 'Mrs.',
			'mrs .' => 'Mrs.',
			'miss.' => 'Miss.',
			'miss' => 'Miss.',
			'miss .' => 'Miss.',
			'ms.' => 'Ms.',
			'ms' => 'Ms.',
			'ms .' => 'Ms.',
			'dr.' => 'Dr.',
			'dr' => 'Dr.',
			'dr .' => 'Dr.',
			'prof.' => 'Prof.',
			'prof' => 'Prof.',
			'prof .' => 'Prof.',
			'rev.' => 'Rev.',
			'rev' => 'Rev.',
			'rev .' => 'Rev.',
			'hon.' => 'Hon.',
			'hon' => 'Hon.',
			'hon .' => 'Hon.',
			'dra.' => null,
			'dra' => null
		);

		$this->db->select('param_id, cValue, InActive');
		$this->db->where('cCode', $code);
		$q = $this->db->get('system_parameters');
		$rows = $q ? $q->result() : array();
		$byNorm = array();
		foreach ($rows as $r) {
			$norm = strtolower(trim((string)$r->cValue));
			if ($norm !== '') {
				if (!isset($byNorm[$norm])) {
					$byNorm[$norm] = array();
				}
				$byNorm[$norm][] = $r;
			}
		}

		// Deactivate Dra. (not commonly used in Ghana HMS)
		foreach (array('dra.', 'dra') as $bad) {
			if (isset($byNorm[$bad])) {
				foreach ($byNorm[$bad] as $r) {
					$this->db->where('param_id', (int)$r->param_id);
					$this->db->update('system_parameters', array('InActive' => 1));
				}
			}
		}

		// Ensure standard Ghana titles exist and are active (idempotent)
		foreach ($desired as $title) {
			$foundRowsById = array();
			foreach ($variants as $k => $canonical) {
				if ($canonical === $title && isset($byNorm[$k])) {
					foreach ($byNorm[$k] as $row) {
						$foundRowsById[(int)$row->param_id] = $row;
					}
				}
			}
			$norm = strtolower(trim((string)$title));
			if (isset($byNorm[$norm])) {
				foreach ($byNorm[$norm] as $row) {
					$foundRowsById[(int)$row->param_id] = $row;
				}
			}
			$foundRows = array_values($foundRowsById);

			if (count($foundRows) > 0) {
				// Keep the oldest (lowest param_id) row and normalize its value.
				usort($foundRows, function($a, $b){
					return ((int)$a->param_id) - ((int)$b->param_id);
				});
				$keep = $foundRows[0];
				$this->db->where('param_id', (int)$keep->param_id);
				$this->db->update('system_parameters', array('cValue' => $title, 'InActive' => 0));

				// Deactivate duplicates (same semantic title) to avoid clutter.
				for ($i = 1; $i < count($foundRows); $i++) {
					$this->db->where('param_id', (int)$foundRows[$i]->param_id);
					$this->db->update('system_parameters', array('InActive' => 1));
				}
			} else {
				$this->db->insert('system_parameters', array(
					'cCode' => $code,
					'cValue' => $title,
					'cDesc' => '',
					'InActive' => 0
				));
			}
		}
	}
	
	private function ensure_company_info_schema(){
		$this->load->helper('schema_guard');
		if (schema_already_run('company_info_schema')) {
			return;
		}
		if (!$this->db->table_exists('company_info')) {
			return;
		}
		$checks = array(
			'site_title' => "ALTER TABLE company_info ADD COLUMN site_title VARCHAR(255) NULL",
			'company_email' => "ALTER TABLE company_info ADD COLUMN company_email VARCHAR(255) NULL",
			'header_logo' => "ALTER TABLE company_info ADD COLUMN header_logo VARCHAR(255) NULL",
			'hospital_tagline' => "ALTER TABLE company_info ADD COLUMN hospital_tagline VARCHAR(500) NULL",
			'login_logo' => "ALTER TABLE company_info ADD COLUMN login_logo VARCHAR(255) NULL",
			'theme_default' => "ALTER TABLE company_info ADD COLUMN theme_default VARCHAR(10) NOT NULL DEFAULT 'light'",
			'updated_at' => "ALTER TABLE company_info ADD COLUMN updated_at TIMESTAMP NULL"
		);
		foreach ($checks as $field => $sql) {
			if (!$this->db->field_exists($field, 'company_info')) {
				$this->db->query($sql);
			}
		}
		mark_schema_run('company_info_schema');
	}
	
	public function companyInfo(){
		$this->ensure_company_info_schema();
		
		// Ensure BrandingService is explicitly loaded
		require_once APPPATH . 'libraries/BrandingService.php';
		
		// Map BrandingService settings to legacy company_info properties for backward compatibility
		$settings = BrandingService::settings();
		
		$info = new stdClass();
		$info->company_name = !empty($settings['facility_name']) ? $settings['facility_name'] : 'Healthcare Facility';
		$info->site_title = !empty($settings['facility_short_name']) ? $settings['facility_short_name'] : '';
		$info->hospital_tagline = !empty($settings['facility_tagline']) ? $settings['facility_tagline'] : '';
		
		// Handle logo compatibility. If empty in settings, fall back to 'sample.jpg'
		$info->logo = !empty($settings['logo_path']) ? $settings['logo_path'] : 'sample.jpg';
		$info->login_logo = !empty($settings['logo_dark']) ? $settings['logo_dark'] : 'sample.jpg';
		$info->header_logo = !empty($settings['logo_light']) ? $settings['logo_light'] : 'sample.jpg';
		
		$info->company_address = !empty($settings['address']) ? $settings['address'] : '';
		$info->company_contactNo = !empty($settings['phone']) ? $settings['phone'] : '';
		$info->company_email = !empty($settings['email']) ? $settings['email'] : '';
		$info->TIN = !empty($settings['tin']) ? $settings['tin'] : '';
		$info->theme_default = 'light';
		
		// Map aliases / modern properties to ensure 100% print template coverage
		$info->facility_name = $info->company_name;
		$info->facility_short_name = $info->site_title;
		$info->facility_tagline = $info->hospital_tagline;
		$info->logo_path = !empty($settings['logo_path']) ? $settings['logo_path'] : '';
		$info->logo_dark = !empty($settings['logo_dark']) ? $settings['logo_dark'] : '';
		$info->logo_light = !empty($settings['logo_light']) ? $settings['logo_light'] : '';
		$info->address = $info->company_address;
		$info->phone = $info->company_contactNo;
		$info->email = $info->company_email;
		$info->website = !empty($settings['website']) ? $settings['website'] : '';
		$info->tin = $info->TIN;
		$info->registration_number = !empty($settings['registration_number']) ? $settings['registration_number'] : '';
		$info->footer_note = !empty($settings['footer_note']) ? $settings['footer_note'] : '';
		
		return $info;
	}
	
	public function getUserLoggedIn($username){
		static $cache = array();
		$username = (string)$username;
		if ($username !== '' && isset($cache[$username])) {
			return $cache[$username];
		}
		$this->db->select("A.user_id, A.lastname, A.firstname, A.middlename, A.picture, B.designation,A.user_role,C.module,C.role_name,
				D.department_id,
				A.doctorIsIn, A.doctorLastIn, A.doctorLastOut");
		$this->db->where('A.username', $username);
		$this->db->join("designation B","B.designation_id = A.designation","left outer");
		$this->db->join("user_roles C","C.role_id = A.user_role","left outer");
		$this->db->join("department D","D.department_id = A.department","left outer");
		$query = $this->db->get("users A");
		$row = $query ? $query->row() : null;
		if ($username !== '') {
			$cache[$username] = $row;
		}
		return $row;
	}	

	public function insertNew($roomid, $bedNo)
	{
		$this->db->query("INSERT INTO room_beds(room_master_id,bed_name,nStatus,InActive) VALUES(?,?,'Vacant','0')", array($roomid, $bedNo));
	}
	
	public function UserTitles(){
		$this->ensure_ghana_title_names();
		$this->db->select("param_id, cValue");	
		$this->db->where(array(
			'cCode'		=>	'title_name',
			'InActive'	=>	0	
		));
		$preferred = array('Mr.','Mrs.','Miss.','Ms.','Dr.','Prof.','Rev.','Pastor','Imam','Hon.','Nana','Chief','Master','Baby');
		$escaped = array();
		foreach ($preferred as $p) {
			$escaped[] = $this->db->escape($p);
		}
		$field = 'FIELD(cValue,' . implode(',', $escaped) . ')';
		// Preferred titles first, then any custom titles.
		$this->db->order_by($field . '=0', 'ASC', false);
		$this->db->order_by($field, 'ASC', false);
		$this->db->order_by('cValue', 'asc');
		$query = $this->db->get("system_parameters");
		return $query->result();
	}
	
	public function gender(){
		$this->db->select("param_id, cValue");	
		$this->db->where(array(
			'cCode'		=>	'gender',
			'InActive'	=>	0	
		));
		$this->db->order_by('cValue','asc');
		$query = $this->db->get("system_parameters");
		return $query->result();
	}
	
	public function civilStatus(){
		$this->db->select("param_id, cValue");	
		$this->db->where(array(
			'cCode'		=>	'civil_status',
			'InActive'	=>	0	
		));
		$this->db->order_by('cValue','asc');
		$query = $this->db->get("system_parameters");
		return $query->result();
	}
	
	public function departmentList(){
		$this->load->helper('schema_guard');
		$hasSortOrder = schema_already_run('ghs_departments_schema');
		if (!$hasSortOrder) {
			if (!$this->db->field_exists('sort_order', 'department')) {
				$this->ensure_ghs_departments();
			} else {
				mark_schema_run('ghs_departments_schema');
			}
			$hasSortOrder = true;
		} else {
			// Flag already exists, so sort_order column is guaranteed to exist
			$hasSortOrder = true;
		}
		$this->db->select("department_id, dept_name");
		$this->db->where(array('InActive' => 0));
		if ($hasSortOrder) {
			$this->db->order_by('IF(sort_order IS NULL OR sort_order=0, 9999, sort_order)', 'ASC', false);
		}
		$this->db->order_by('dept_name', 'ASC');
		$query = $this->db->get("department");
		return $query->result();
	}

	public function ensure_ghs_departments()
	{
		$this->load->helper('schema_guard');
		if (schema_already_run('ghs_departments_schema')) {
			return;
		}
		// 1. Add sort_order column if missing
		if (!$this->db->field_exists('sort_order', 'department')) {
			$this->db->query("ALTER TABLE `department` ADD COLUMN `sort_order` INT(4) NOT NULL DEFAULT 0");
		}

		// 2. GHS/NHIS-standard clinical departments for Ghana HMS
		//    Format: [dept_code, dept_name, sort_order]
		$ghs = array(
			array('GEN-OPD',   'General OPD',                    1),
			array('EMERGENCY', 'Emergency / Casualty',            2),
			array('FAMILY-MED','Family Medicine',                 3),
			array('INTERNAL',  'Internal Medicine',               4),
			array('PEDIATRICS','Pediatrics',                      5),
			array('OBS-GYN',   'Obstetrics & Gynecology (ANC)',   6),
			array('SURGERY',   'General Surgery',                 7),
			array('ORTHO',     'Orthopedics',                     8),
			array('ENT',       'Ear, Nose & Throat (ENT)',        9),
			array('EYE',       'Eye / Ophthalmology',            10),
			array('DENTAL',    'Dental',                         11),
			array('MENTAL',    'Mental Health / Psychiatry',     12),
			array('DERMATO',   'Dermatology',                    13),
			array('PHYSIO',    'Physiotherapy',                  14),
			array('LAB',       'Laboratory',                     15),
			array('RADIOLOGY', 'Radiology',                      16),
			array('SONO',      'Sonography / Ultrasound',        17),
			array('PHARMACY',  'Pharmacy',                       18),
			array('DRESSING',  'Dressing Room',                  19),
			array('INJECTION', 'Injection Room',                 20),
			array('NUTRITION', 'Nutrition / Dietician',          21),
			array('CHRONIC',   'Chronic Care Clinic',            22),
			array('DIABETES',  'Diabetes Clinic',                23),
			array('HYPER',     'Hypertension Clinic',            24),
			array('HIV-ART',   'HIV / ART Clinic',               25),
			array('TB',        'TB Clinic',                      26),
			array('PUB-HLTH',  'Public Health',                  27),
		);

		foreach ($ghs as $d) {
			list($code, $name, $order) = $d;
			$exists = $this->db->query(
				"SELECT department_id FROM department WHERE dept_code = ? LIMIT 1",
				array($code)
			);
			if ($exists && $exists->num_rows() > 0) {
				// Update name, sort_order, ensure active
				$row = $exists->row();
				$this->db->where('department_id', $row->department_id);
				$this->db->update('department', array(
					'dept_name'  => $name,
					'sort_order' => $order,
					'InActive'   => 0,
				));
			} else {
				// Check by name to avoid creating duplicates
				$byName = $this->db->query(
					"SELECT department_id FROM department WHERE dept_name = ? LIMIT 1",
					array($name)
				);
				if ($byName && $byName->num_rows() > 0) {
					$row = $byName->row();
					$this->db->where('department_id', $row->department_id);
					$this->db->update('department', array(
						'dept_code'  => $code,
						'sort_order' => $order,
						'InActive'   => 0,
					));
				} else {
					$this->db->insert('department', array(
						'dept_code'  => $code,
						'dept_name'  => $name,
						'sort_order' => $order,
						'InActive'   => 0,
					));
				}
			}
		}

		// 3. Soft-retire legacy non-clinical departments that don't match any GHS code
		//    and are NOT referenced in patient_details_iop or users tables.
		$ghs_codes = array_column($ghs, 0);
		$placeholders = implode(',', array_fill(0, count($ghs_codes), '?'));
		$legacy = $this->db->query(
			"SELECT department_id, dept_name FROM department
			 WHERE dept_code NOT IN ({$placeholders}) AND InActive = 0",
			$ghs_codes
		);
		if ($legacy && $legacy->num_rows() > 0) {
			foreach ($legacy->result() as $leg) {
				$used = $this->db->query(
					"SELECT COUNT(*) AS c FROM patient_details_iop WHERE department_id = ? LIMIT 1",
					array($leg->department_id)
				);
				$usedUsers = $this->db->query(
					"SELECT COUNT(*) AS c FROM users WHERE department = ? LIMIT 1",
					array($leg->department_id)
				);
				$inUse = ($used && (int)$used->row()->c > 0)
					|| ($usedUsers && (int)$usedUsers->row()->c > 0);
				if (!$inUse) {
					$this->db->where('department_id', $leg->department_id);
					$this->db->update('department', array('InActive' => 1));
				}
			}
		}
		mark_schema_run('ghs_departments_schema');
	}
	
	public function designationList(){
		$this->db->select("designation_id, designation");	
		$this->db->where(array(
			'InActive'	=>	0	
		));
		$this->db->order_by('designation','asc');
		$query = $this->db->get("designation");
		return $query->result();
	}
	
	public function userRoleList(){
		$this->db->select("role_id, role_name");	
		$this->db->where(array(
			'InActive'	=>	0	
		));
		$this->db->order_by('role_name','asc');
		$query = $this->db->get("user_roles");
		return $query->result();
	}
	
	public function floorList(){
		$this->db->where(array(
			'InActive'	=>	0	
		));
		$this->db->order_by('floor_name','asc');
		$query = $this->db->get("floor");
		return $query->result();
	}
	
	public function roomTypeList(){
		$this->db->where(array(
			'InActive'	=>	0	
		));
		$this->db->order_by('category_name','asc');
		$query = $this->db->get("room_category");
		return $query->result();
	}
	
	public function roomMasterList(){
		$this->db->where(array(
			'InActive'	=>	0	
		));
		$this->db->order_by('room_name','asc');
		$query = $this->db->get("room_master");
		return $query->result();
	}
	
	public function bloodGroup(){
		$this->db->where(array(
			'cCode'		=>	'blood_type',
			'InActive'	=>	0	
		));
		$this->db->order_by('cValue','asc');
		$query = $this->db->get("system_parameters");
		return $query->result();
	}
	
	public function religionList(){
		$this->db->where(array(
			'cCode'		=>	'religion',
			'InActive'	=>	0	
		));
		$this->db->order_by('cValue','asc');
		$query = $this->db->get("system_parameters");
		return $query->result();
	}
	
	public function doctorList(){
		$this->db->select("A.user_id,
					concat(B.cValue,' ',A.firstname,' ',A.lastname) as 'name'",false);
		$this->db->where(array(
			'C.module'		=>	'doctor',
			'A.InActive'	=>	0	
		));
		$this->db->order_by('A.lastname','asc');
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("user_roles C","C.role_id = A.user_role","left outer");
		$query = $this->db->get("users A");
		return $query->result();
	}
	
	public function insuranceCompList(){
		$this->db->where(array(
			'InActive'	=>	0	
		));
		$this->db->order_by('company_name','asc');
		$query = $this->db->get("insurance_comp ");
		return $query->result();
	}
	
	
	public function getPageID(){
		static $cache = array();
		$pageLink = (string)$this->session->userdata('page_name');
		if ($pageLink !== '' && isset($cache[$pageLink])) {
			return $cache[$pageLink];
		}
		$this->db->select('page_id');
		$this->db->where("page_link", $pageLink);
		$query = $this->db->get('pages');
		$row = $query ? $query->row() : null;
		if ($pageLink !== '') {
			$cache[$pageLink] = $row;
		}
		return $row;		
	}
	
	public function lastOPDNo(){
		$this->db->select("(cValue + 1) as 'opdNo'");
		$this->db->where("cCode","OUTPATIENTNO");
		$query = $this->db->get("system_option");	
		return $query->row();
	}
	
	public function lastIPDNo(){
		$this->db->select("(cValue + 1) as 'ipdNo'");
		$this->db->where("cCode","INPATIENTNO");
		$query = $this->db->get("system_option");	
		return $query->row();
	}
	
	public function patientList(){
		$this->db->select("A.patient_no,
				concat(B.firstname,' ',B.lastname) as name
				",false);
		$this->db->where(array(
			'A.InActive'	=>		0,
			'A.nStatus'		=>		'Pending'
		));
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->result();	
	}
	
	public function room_category(){
		$query = $this->db->get_where("room_category",array('InActive' => 0));	
		return $query->result();
	}
	
	public function numberofOccuBeds($room_id){
		$this->db->select("count(bed_name) as numberofOccuBeds");
		$query = $this->db->get_where("room_beds", array(
			'InActive' 			=> 	'0',
			'nStatus'			=>	'Occupied',
			'room_master_id'	=>	$room_id
		));
		return $query->row();
	}
	
	public function numberofUnOccuBeds($room_id){
		$this->db->select("count(bed_name) as numberofOccuBeds");
		$query = $this->db->get_where("room_beds", array(
			'InActive' 			=> 	'0',
			'nStatus'			=>	'Vacant',
			'room_master_id'	=>	$room_id
		));
		return $query->row();
	}
	
	public function getBeds($room_id){
		$this->db->select("
			A.room_bed_id,
			A.bed_name,
			C.patient_no,
			B.IO_ID,
			concat(D.cValue,' ',C.firstname,' ',C.lastname) as patient,
			B.date_visit,
			B.time_visit,
			A.nStatus
		",false);
		$this->db->where(array(
			'A.room_master_id'		=>		$room_id,
			'A.InActive'			=>		'0'
		));
		$this->db->join("patient_details_iop B","B.IO_ID = A.patient_no","left outer");
		$this->db->join("patient_personal_info C","C.patient_no = B.patient_no","left outer");
		$this->db->join("system_parameters D","D.param_id = C.title","left outer");
		$this->db->order_by("A.bed_name","ASC");
		$query = $this->db->get("room_beds A");
		return $query->result();
	}
	
	public function getConditionDis(){
		$query = $this->db->get_where("system_parameters",array(
			'InActive'		=>		0,
			'cCode'			=>		'condition_upon_discharge'
		));	
		return $query->result();
	}
	
	public function getPreparedBy($user_id){
		$query = $this->db->query(
			"SELECT concat(A.firstname,' ',A.middlename,' ',A.lastname) as cPreparedBy FROM `users` A WHERE `A`.`user_id` = ?",
			array($user_id)
		);
		return $query->row();
	}
	
	public function opdLists($val,$cType){
		$this->db->select("
				patient_no,
				concat(firstname,' ',lastname) as patient
		",false);
		$valEsc = $this->db->escape_like_str($val);
		$where = "(
				patient_no like '%".$valEsc."%' or 
				firstname like '%".$valEsc."%' or 
				lastname like '%".$valEsc."%'
			)
			";
		$this->db->where($where);
		$this->db->order_by("patient_no","ASC");
		$query = $this->db->get("patient_personal_info");
		return $query->result();
	}
	
	public function getDoctor($doctor_id){
		$this->db->select("concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as doctor",false);
		$this->db->join("system_parameters B","B.param_id = A.title","left outer join");
		$query = $this->db->get_where("users A",array('A.user_id' => $doctor_id));
		return $query->row();
	}
	
	public function getDeptName($department_id){
		$query = $this->db->get_where("department",array('department_id' => $department_id));
		return $query->row();
	}
	
	
	public function getroomName($room_master_id){
		$query = $this->db->get_where("room_master",array('room_master_id' => $room_master_id));
		return $query->row();
	}
	
	public function getRoomNameLists($category_id){
		$this->db->order_by("room_name","ASC");
		$query = $this->db->get_where("room_master",array('category_id'=>$category_id,'InActive'=>0));
		return $query->result();	
	}
	
	public function getBedList($category_id){
		$this->db->order_by("bed_name","ASC");
		$query = $this->db->get_where("room_beds",array('room_master_id'=>$category_id,'InActive'=>0,'nStatus'=>'Vacant'));
		return $query->result();	
	}
	
	public function getConditionUpon($id){
		$query = $this->db->get_where("system_parameters",array('param_id' => $id));
		return $query->row();
	}
	
	public function ipdLists($val){
		$val = trim((string)$val);
		$this->db->select("
				B.patient_no,
				A.IO_ID,
				concat(B.firstname,' ',B.lastname) as patient
		",false);
		$valEsc = $this->db->escape_like_str($val);
		// NOTE: .htaccess normalizes %20 into '-' in request URIs.
		// When a user types a full name with spaces (e.g. "John Doe"), the AJAX request
		// path becomes "John-Doe". Preserve IO_ID matching (IP-000025) while also
		// allowing name searches by treating '-' as a possible space for firstname/lastname.
		$valName = str_replace('-', ' ', $val);
		$valNameEsc = $this->db->escape_like_str($valName);

		$firstNameExpr = "B.firstname like '%".$valEsc."%'";
		$lastNameExpr = "B.lastname like '%".$valEsc."%'";
		if ($valNameEsc !== $valEsc) {
			$firstNameExpr = "(" . $firstNameExpr . " or B.firstname like '%".$valNameEsc."%')";
			$lastNameExpr = "(" . $lastNameExpr . " or B.lastname like '%".$valNameEsc."%')";
		}
		$where = "(
				B.patient_no like '%".$valEsc."%' or 
				A.IO_ID like '%".$valEsc."%' or 
				".$firstNameExpr." or 
				".$lastNameExpr."
			) and 
			A.patient_type = 'IPD' and A.nStatus = 'Pending'
			";
		$this->db->where($where);
		$this->db->order_by("A.IO_ID","ASC");
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->result();
	}
	
	public function reason_dicount(){
		$query = $this->db->get_where("system_parameters",array('Inactive' => '0', 'cCode' => 'reason_for_discount'));
		return $query->result();	
	}
	
	public function surgery_list(){
		$query = $this->db->get_where("surgical_package",array('InActive' => 0));
		return $query->result();	
	}
	
	public function getSurgeryName(){
		$query = $this->db->get_where("surgical_package",array('InActive' => 0, 'surgery_id' => $this->input->post('surgery_name')));
		return $query->row();
	}	
	
	public function getSurgeryItems(){
		$this->db->select("B.particular_name,A.costs,A.cDesc");
		$this->db->join("bill_particular B","B.particular_id = A.surgery_item","left outer join");
		$this->db->order_by("B.particular_name","ASC");
		$query = $this->db->get_where("surgical_package_t A",array('A.InActive' => 0, 'surgery_id' => $this->input->post('surgery_name')));
		return $query->result();
	}
	
	public function getSurgeryItems2($iop_id){
		$this->db->select("C.particular_name,B.costs,B.cDesc");
		$this->db->join("surgical_package_t B","B.surgery_id = A.operation_name","left outer");
		$this->db->join("bill_particular C","C.particular_id = B.surgery_item","left outer");
		$query = $this->db->get_where("iop_operation_theater A",array(
			'A.InActive'	=>		0,
			'A.iop_id'		=>		$iop_id,
			'B.InActive'	=>		0
		));
		return $query->result();
	}


	public function getDoctorAvailability($status){
		$this->db->select("A.user_id,
					D.dept_name,
					concat(B.cValue,' ',A.firstname,' ',A.lastname) as 'name',
					A.doctorLastOut,
					A.doctorLastIn,
					A.doctorIsIn",false);
		$this->db->where(array(
			'C.module'		=>	'doctor',
			'A.doctorIsIn'	=>	$status,
			'A.InActive'	=>	0	
		));
		$this->db->order_by('A.lastname','asc');
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("department D","D.department_id = A.department","left outer");
		$this->db->join("user_roles C","C.role_id = A.user_role","left outer");
		$query = $this->db->get("users A");
		return $query->result();
	}
	
	
	
	
	
	
	
	
	
	
}
