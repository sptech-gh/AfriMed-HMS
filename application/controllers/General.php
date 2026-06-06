<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class General extends CI_Controller{

	public $data = array();

	function __construct(){
		parent::__construct();	
		date_default_timezone_set("Africa/Accra");
		$this->load->model('general_model');
		
		// Load UI helper for enhanced UI/UX support
		$this->load->helper('ui');
		$this->load->helper('rbac');
		$this->load->helper('url_safe');
		$this->load->config('ui_config');
		
		// Load Unified Billing System helpers and models
		$this->load->helper('service_gate');
		$this->load->model('app/unified_billing_model', 'unified_billing');
		$this->load->model('app/service_gate_model', 'service_gate');

		// Ensure unified billing schema is up to date (idempotent).
		// Guarded by a session flag so the ALTER TABLE checks run once per
		// login session rather than on every HTTP request.
		if ($this->unified_billing && !$this->session->userdata('_schema_unified_billing_ok')) {
			$this->unified_billing->ensure_unified_billing_schema();
			$this->session->set_userdata('_schema_unified_billing_ok', 1);
		}
	}
	
	/**
	 * Decode URL-safe ID back to database format
	 * Handles both old format (with spaces) and new format (without spaces)
	 * 
	 * @param string $id The URL-safe ID
	 * @return string Database-compatible ID
	 */
	protected function decode_url_id($id) {
		if (function_exists('sanitize_id_for_db')) {
			return sanitize_id_for_db($id);
		}
		// Fallback if helper not loaded — do NOT convert dashes, IDs use dashes natively
		$id = urldecode((string)$id);
		return trim($id);
	}
	
	/**
	 * Encode ID for URL usage
	 * 
	 * @param string $id The database ID
	 * @return string URL-safe ID
	 */
	protected function encode_url_id($id) {
		if (function_exists('url_safe_id')) {
			return url_safe_id($id);
		}
		// Fallback
		return str_replace(' ', '-', (string)$id);
	}
	
	/**
	 * Get URL-decoded IOP ID and patient number from URI segments
	 * Handles both old format (with spaces) and new format (without spaces)
	 * 
	 * @param int ...$segments Variable number of segment indices to decode
	 * @return array Array of decoded values
	 */
	protected function get_url_params(...$segments) {
		$result = array();
		foreach ($segments as $seg) {
			$val = $this->uri->segment($seg);
			$result[] = $this->decode_url_id($val);
		}
		return $result;
	}
	
	/**
	 * Get URL-decoded URI segment
	 * Use this instead of $this->uri->segment() for IDs that may contain encoded characters
	 * 
	 * @param int $n Segment index
	 * @param mixed $default Default value if segment doesn't exist
	 * @return string Decoded segment value
	 */
	protected function segment_decoded($n, $default = null) {
		$val = $this->uri->segment($n, $default);
		if ($val === null || $val === false || $val === '') {
			return $default;
		}
		return $this->decode_url_id($val);
	}
	
	/**
	 * Get multiple URL-decoded URI segments at once
	 * Returns associative array with segment index as key
	 * 
	 * @param array $segments Array of segment indices
	 * @return array Associative array of decoded values
	 */
	protected function segments_decoded(array $segments) {
		$result = array();
		foreach ($segments as $seg) {
			$result[$seg] = $this->segment_decoded($seg);
		}
		return $result;
	}
	
	public function variable(){
		$this->data['companyInfo'] = $this->general_model->companyInfo();
		$this->data['userInfo'] = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (!$this->session->userdata('is_logged_in')) {
			return;
		}
		
		// Validate and refresh cached rbac_module if it doesn't match the DB
		// This fixes issues where session has stale role data
		$cachedRbac = $this->session->userdata('rbac_module');
		if ($cachedRbac && isset($this->data['userInfo']) && is_object($this->data['userInfo'])) {
			$dbModule = isset($this->data['userInfo']->module) ? strtolower(trim((string)$this->data['userInfo']->module)) : '';
			// If DB says 'doctor' but cache says something else, clear the cache
			if ($dbModule === 'doctor' && $cachedRbac !== 'doctor') {
				$this->session->unset_userdata('rbac_module');
			}
		}

		// Auto-refresh privileges if they've been modified by admin
		// Throttle to once per 60 seconds to avoid hitting DB on every request
		$last_priv_check = (int)$this->session->userdata('_last_priv_check_ts');
		if ((time() - $last_priv_check) > 60) {
			check_privilege_refresh_needed();
			$this->session->set_userdata('_last_priv_check_ts', time());
		}
		
		$cache = $this->session->userdata('lookup_cache');
		$cacheOk = is_array($cache)
			&& isset($cache['ts'])
			&& (time() - (int)$cache['ts'] < 120)
			&& isset($cache['data'])
			&& is_array($cache['data']);
		if ($cacheOk) {
			foreach ($cache['data'] as $k => $v) {
				$this->data[$k] = $v;
			}
		} else {
			$cacheData = array();
			$cacheData['UserTitles'] = $this->general_model->UserTitles();
			$cacheData['gender'] = $this->general_model->gender();
			$cacheData['civilStatus'] = $this->general_model->civilStatus();
			$cacheData['departmentList'] = $this->general_model->departmentList();
			$cacheData['designationList'] = $this->general_model->designationList();
			$cacheData['userRoleList'] = $this->general_model->userRoleList();
			$cacheData['roomTypeList'] = $this->general_model->roomTypeList();
			$cacheData['floorList'] = $this->general_model->floorList();
			$cacheData['roomMasterList'] = $this->general_model->roomMasterList();
			$cacheData['bloodGroup'] = $this->general_model->bloodGroup();
			$cacheData['religionList'] = $this->general_model->religionList();
			$cacheData['doctorList'] = $this->general_model->doctorList();
			$cacheData['doctorList2'] = $cacheData['doctorList'];
			$cacheData['insuranceCompList'] = $this->general_model->insuranceCompList();
			$cacheData['patientListRows'] = $this->general_model->patientList();
			$this->session->set_userdata('lookup_cache', array('ts' => time(), 'data' => $cacheData));
			foreach ($cacheData as $k => $v) {
				$this->data[$k] = $v;
			}
		}

		
		if ($this->session->userdata('username')) {
			// Sidebar menu restriction access
		$userRole = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
		if (!$userRole) {
			$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		}

		// Dashboard 
		$this->data['hasAccesstoDoctorAvail'] = ($this->has_rights_to_access("134",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Billing Module Validation
		$this->data['hasAccesstoBilling'] = ($this->has_rights_to_access("85",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoPOS'] = ($this->has_rights_to_access("84",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoSurgical'] = ($this->has_rights_to_access("116",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Patient Appointment
		$this->data['hasAccesstoAppointment'] = ($this->has_rights_to_access("121",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAddAppointment'] = ($this->has_rights_to_access("122",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Patient Management
		$this->data['hasAccesstoPatient'] = ($this->has_rights_to_access("49",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAddPatient'] = ($this->has_rights_to_access("48",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoPatient'] = ($this->has_rights_to_access("49",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoOPDRegistration'] = ($this->has_rights_to_access("91",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoOPDEnquiry'] = ($this->has_rights_to_access("92",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoIPDRegistration'] = ($this->has_rights_to_access("93",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoIPDEnquiry'] = ($this->has_rights_to_access("94",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Room Management
		$this->data['hasAccesstoRooms'] = ($this->has_rights_to_access("44",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoRoomsEnquiry'] = ($this->has_rights_to_access("99",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Nurse Module
		$this->data['hasAccesstoNurse'] = ($this->has_rights_to_access("128",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseBedSide'] = ($this->has_rights_to_access("107",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseInOutTake'] = ($this->has_rights_to_access("101",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseIPRoomTransfer'] = ($this->has_rights_to_access("104",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseDiagnosis'] = ($this->has_rights_to_access("120",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseProgressNote'] = ($this->has_rights_to_access("102",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseDischarge'] = ($this->has_rights_to_access("106",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNursePatientHistory'] = ($this->has_rights_to_access("105",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseMedication'] = ($this->has_rights_to_access("100",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoNurseVitalSign'] = ($this->has_rights_to_access("103",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		$u = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
		$module = ($u && isset($u->module)) ? strtolower(trim((string)$u->module)) : '';
		$roleId = ($u && isset($u->user_role)) ? (int)$u->user_role : 0;
		$roleKey = function_exists('get_role_key') ? (string)get_role_key() : $module;
		$isSuperAdmin = ($module === 'super_admin' || $roleId === 1);
		$isAdmin = ($module === 'administrator' || $isSuperAdmin);
		$isDoctor = ($module === 'doctor' || $module === 'doctor_module' || $module === 'doctor module' || $roleId === 5);
		$isNurse = ($module === 'nurse' || $roleId === 7);
		$isPharmacy = ($module === 'pharmacy' || $module === 'pharmacist' || $module === 'dispenser' || $module === 'dispensary' || $roleId === 10);
		$isSonography = ($roleKey === 'sonographer' || $module === 'sonography' || $module === 'sonographer');
		// Pharmacy access: Only for pharmacy staff and admin (NOT doctors - they prescribe, they don't dispense)
		$this->data['hasAccesstoPharmacy'] = ($isPharmacy || $isAdmin);
		$this->data['hasAccesstoSonography'] = ($isSonography || $isAdmin);
		if (!$isNurse && !$isAdmin) {
			$this->data['hasAccesstoNurse'] = FALSE;
			$this->data['hasAccesstoNurseBedSide'] = FALSE;
			$this->data['hasAccesstoNurseInOutTake'] = FALSE;
			$this->data['hasAccesstoNurseIPRoomTransfer'] = FALSE;
			$this->data['hasAccesstoNurseDiagnosis'] = FALSE;
			$this->data['hasAccesstoNurseProgressNote'] = FALSE;
			$this->data['hasAccesstoNurseDischarge'] = FALSE;
			$this->data['hasAccesstoNursePatientHistory'] = FALSE;
			$this->data['hasAccesstoNurseMedication'] = FALSE;
			$this->data['hasAccesstoNurseVitalSign'] = FALSE;
		}
		if ($isPharmacy && !$isAdmin) {
			$this->data['hasAccesstoBilling'] = FALSE;
			$this->data['hasAccesstoPOS'] = FALSE;
			$this->data['hasAccesstoSurgical'] = FALSE;
			$this->data['hasAccesstoAdmin'] = FALSE;
		}
		// Doctor Role Restrictions - Doctors should focus on clinical work only
		// They should NOT have access to billing, pharmacy dispensing, or admin
		if ($isDoctor && !$isAdmin) {
			$this->data['hasAccesstoBilling'] = FALSE;
			$this->data['hasAccesstoPOS'] = FALSE;
			$this->data['hasAccesstoPharmacy'] = FALSE;
			$this->data['hasAccesstoAdmin'] = FALSE;
			$this->data['hasAccesstoUsers'] = FALSE;
			$this->data['hasAccesstoAddUsers'] = FALSE;
		}

		// Cashier Role Detection and Restrictions
		// Cashiers should ONLY see billing-related features by default
		// Admin can grant additional access via dynamic privileges
		$isCashier = ($module === 'cashier' || $module === 'billing' || $module === 'billing / cashier' || $module === 'billing/cashier' || in_array($roleId, array(5, 6, 8)));
		if ($isCashier && !$isAdmin) {
			// Cashiers should NOT have access to clinical modules by default
			$this->data['hasAccesstoOPDRegistration'] = (has_privilege('opd_access')) ? TRUE : FALSE;
			$this->data['hasAccesstoOPDEnquiry'] = (has_privilege('opd_access')) ? TRUE : FALSE;
			$this->data['hasAccesstoIPDRegistration'] = (has_privilege('ipd_access')) ? TRUE : FALSE;
			$this->data['hasAccesstoIPDEnquiry'] = (has_privilege('ipd_access')) ? TRUE : FALSE;
			
			// No access to diagnostics unless explicitly granted
			$this->data['hasAccesstoLaboratory'] = (has_privilege('laboratory_access')) ? TRUE : FALSE;
			$this->data['hasAccesstoSonography'] = (has_privilege('sonography_access')) ? TRUE : FALSE;
			
			// No access to nurse or doctor modules
			$this->data['hasAccesstoNurse'] = FALSE;
			$this->data['hasAccesstoDoctor'] = FALSE;
			$this->data['hasAccesstoDoctorIPD'] = FALSE;
			$this->data['hasAccesstoDoctorOPD'] = FALSE;
			$this->data['hasAccesstoEMR'] = FALSE;
			$this->data['hasAccesstoEMRIPD'] = FALSE;
			$this->data['hasAccesstoEMROPD'] = FALSE;
			
			// No admin access
			$this->data['hasAccesstoAdmin'] = FALSE;
			$this->data['hasAccesstoUsers'] = FALSE;
			$this->data['hasAccesstoAddUsers'] = FALSE;
			
			// Pharmacy access only if explicitly granted
			$this->data['hasAccesstoPharmacy'] = (has_privilege('pharmacy_access')) ? TRUE : FALSE;
			
			// Ensure billing access is granted for cashiers
			$this->data['hasAccesstoBilling'] = TRUE;
			$this->data['hasAccesstoPOS'] = TRUE;
			
			// Limited patient access - can view for billing purposes
			$this->data['hasAccesstoPatient'] = TRUE;
			$this->data['hasAccesstoAddPatient'] = (has_privilege('patient_registration')) ? TRUE : FALSE;
			
			// Reports - only billing-related reports
			$this->data['hasAccesstoReport'] = TRUE;
			$this->data['hasAccesstoReportDailySales'] = TRUE;
			$this->data['hasAccesstoReportPatient'] = FALSE;
			$this->data['hasAccesstoReportIndividualPatient'] = FALSE;
			$this->data['hasAccesstoReportOPD'] = FALSE;
			$this->data['hasAccesstoReportAdmitted'] = FALSE;
			$this->data['hasAccesstoReportDischarge'] = FALSE;
		}
		$this->data['isCashierRole'] = $isCashier;

		// Doctor Module
		$this->data['hasAccesstoDoctor'] = ($this->has_rights_to_access("129",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoDoctorIPD'] = ($this->has_rights_to_access("90",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoDoctorOPD'] = ($this->has_rights_to_access("89",$userRole->user_role) == FALSE) ? FALSE : TRUE;
		if ($isDoctor || $isAdmin) {
			$this->data['hasAccesstoDoctor'] = TRUE;
			$this->data['hasAccesstoDoctorIPD'] = TRUE;
			$this->data['hasAccesstoDoctorOPD'] = TRUE;
			// Doctors need OPD/IPD enquiry access to view their patient worklists
			$this->data['hasAccesstoOPDEnquiry'] = TRUE;
			$this->data['hasAccesstoIPDEnquiry'] = TRUE;
			// Doctors need patient access for clinical work
			$this->data['hasAccesstoPatient'] = TRUE;
			// Doctors need EMR access
			$this->data['hasAccesstoEMR'] = TRUE;
			$this->data['hasAccesstoEMROPD'] = TRUE;
			$this->data['hasAccesstoEMRIPD'] = TRUE;
			// Doctors need appointment access
			$this->data['hasAccesstoAppointment'] = TRUE;
			// Doctors need clinical reports
			$this->data['hasAccesstoReport'] = TRUE;
			$this->data['hasAccesstoReportOPD'] = TRUE;
			$this->data['hasAccesstoReportAdmitted'] = TRUE;
			$this->data['hasAccesstoReportDischarge'] = TRUE;
			$this->data['hasAccesstoReportPatient'] = TRUE;
			$this->data['hasAccesstoReportIndividualPatient'] = TRUE;
		}

		$this->data['doctor_message_unread_count'] = 0;
		if ($this->data['hasAccesstoDoctor']) {
			$this->load->model('app/nurse_enhancement_model');
			if ($this->nurse_enhancement_model->table_exists('nurse_doctor_message')) {
				$this->data['doctor_message_unread_count'] = $this->nurse_enhancement_model->count_unread_doctor_messages($this->session->userdata('user_id'));
			}
		}

		// Laboratory Module
		$isLaboratory = ($module === 'laboratory' || $module === 'lab' || $module === 'labs' || $module === 'lab_module');
		$labPage135 = ($this->has_rights_to_access("135", $userRole->user_role) != FALSE);
		$labPage136 = ($this->has_rights_to_access("136", $userRole->user_role) != FALSE);
		// Doctors get read-only lab access to view their patients' results
		$this->data['hasAccesstoLaboratory'] = ($isLaboratory || $isAdmin || $isDoctor || $labPage135 || $labPage136);
		log_message('info', 'LAB_ACCESS_CHECK module=['.$module.'] roleId=['.$roleId.'] isLab=['.($isLaboratory?'Y':'N').'] isAdmin=['.($isAdmin?'Y':'N').'] isDoc=['.($isDoctor?'Y':'N').'] page135=['.($labPage135?'Y':'N').'] page136=['.($labPage136?'Y':'N').'] hasAccess=['.($this->data['hasAccesstoLaboratory']?'Y':'N').']');

		// EMR Module
		$this->data['hasAccesstoEMR'] = ($this->has_rights_to_access("130",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoEMRIPD'] = ($this->has_rights_to_access("96",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoEMROPD'] = ($this->has_rights_to_access("95",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Users Module
		$this->data['hasAccesstoUsers'] = ($this->has_rights_to_access("36",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAddUsers'] = ($this->has_rights_to_access("37",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Administration Module
		$this->data['hasAccesstoAdmin'] = ($this->has_rights_to_access("131",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminCompanyInfo'] = ($this->has_rights_to_access("111",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminDepartment'] = ($this->has_rights_to_access("28",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminDesignation'] = ($this->has_rights_to_access("40",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminBillGroupName'] = ($this->has_rights_to_access("56",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminParticularBill'] = ($this->has_rights_to_access("60",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminComplain'] = ($this->has_rights_to_access("72",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminDiagnosis'] = ($this->has_rights_to_access("64",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminSurgicalPack'] = ($this->has_rights_to_access("112",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminInsuranceCompany'] = ($this->has_rights_to_access("68",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminMedicineCategory'] = ($this->has_rights_to_access("76",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminDrugName'] = ($this->has_rights_to_access("80",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminAckReceipt'] = ($this->has_rights_to_access("117",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminParameters'] = ($this->has_rights_to_access("52",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminBackup'] = ($this->has_rights_to_access("127",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoAdminPages'] = ($this->has_rights_to_access("1",$userRole->user_role) == FALSE) ? FALSE : TRUE;

		// Reports Module
		$this->data['hasAccesstoReport'] = ($this->has_rights_to_access("132",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportPatient'] = ($this->has_rights_to_access("88",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportIndividualPatient'] = ($this->has_rights_to_access("98",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportOPD'] = ($this->has_rights_to_access("108",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportAdmitted'] = ($this->has_rights_to_access("109",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportDischarge'] = ($this->has_rights_to_access("110",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportDailySales'] = ($this->has_rights_to_access("87",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportDoctorsFee'] = ($this->has_rights_to_access("133",$userRole->user_role) == FALSE) ? FALSE : TRUE;
			$this->data['hasAccesstoReportAR'] = ($this->has_rights_to_access("119",$userRole->user_role) == FALSE) ? FALSE : TRUE;
		}


		


	}
	
	
	
	public function logfile($module,$event,$value){
		$event = substr((string)$event, 0, 10);
		$logData = array(
				'user_id'		=>		$this->session->userdata('user_id'),
				'module'		=>		$module,
				'event'			=>		$event,
				'value'			=>		$value,
				'ipaddress'		=>		$this->input->ip_address(),
				'date_time'		=>		date("Y-m-d h:i:s")
		);
		$ok = $this->db->insert('logfile',$logData);
		if (!$ok) {
			log_message('error', 'logfile insert failed: module='.(string)$module.' event='.(string)$event);
		}
	}
	
	//set if the user is currently logged in
    public function is_logged_in(){
        if($this->session->userdata('is_logged_in')){
            return true;    
        }else{
            return false;																
        }
    }
	
	public function has_rights_to_access($page_id,$role_id){
		static $rightsByRole = array();
		static $checkedRole = array();
		$page_id = (string)$page_id;
		$role_id = (string)$role_id;
		if ($page_id === '' || $role_id === '') {
			return false;
		}
		if (!isset($checkedRole[$role_id])) {
			$checkedRole[$role_id] = true;
			$rightsByRole[$role_id] = array();
			$this->db->select('page_id');
			$this->db->where('role_id', $role_id);
			$q = $this->db->get('user_roles_pages');
			$rows = $q ? $q->result() : array();
			if ($rows) {
				foreach ($rows as $r) {
					if (isset($r->page_id)) {
						$rightsByRole[$role_id][(string)$r->page_id] = true;
					}
				}
			}
		}
		return isset($rightsByRole[$role_id][$page_id]);
	}

	protected function current_user_module_key(){
		$u = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
		if (!$u) {
			$u = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		}
		$module = ($u && isset($u->module)) ? strtolower(trim((string)$u->module)) : '';
		if ($module === 'super admin') {
			$module = 'super_admin';
		}
		return $module;
	}

	protected function current_user_role_id(){
		$u = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
		if (!$u) {
			$u = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		}
		return ($u && isset($u->user_role)) ? (int)$u->user_role : (int)$this->session->userdata('user_role');
	}

	protected function current_user_is_super_admin(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'super_admin' || $roleId === 1);
	}

	protected function current_user_is_admin(){
		$module = $this->current_user_module_key();
		return ($module === 'administrator' || $this->current_user_is_super_admin());
	}

	protected function current_user_is_reception(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'receptionist' || $module === 'reception' || $roleId === 3);
	}

	protected function current_user_is_nurse(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'nurse' || $roleId === 7);
	}

	protected function current_user_is_laboratory(){
		$module = $this->current_user_module_key();
		return ($module === 'laboratory');
	}

	protected function current_user_is_doctor(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'doctor' || $module === 'doctor_module' || $module === 'doctor module' || $roleId === 5);
	}

	protected function current_user_is_pharmacist(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'pharmacy' || $module === 'pharmacist' || $module === 'dispenser' || $module === 'dispensary' || $roleId === 10);
	}

	protected function current_user_is_cashier(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'cashier' || $module === 'billing' || $roleId === 6);
	}

	public function user_can_generate_report_scope($scope){
		$scope = strtolower(trim((string)$scope));
		$u = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
		if (!$u) {
			$u = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		}
		$module = ($u && isset($u->module)) ? strtolower(trim((string)$u->module)) : '';
		$roleId = ($u && isset($u->user_role)) ? (int)$u->user_role : 0;
		$isAdmin = ($module === 'administrator' || $module === 'super_admin' || $roleId === 1);
		if ($isAdmin) {
			return true;
		}

		if ($scope === 'lab') {
			return (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory']);
		}
		if ($scope === 'sonography') {
			return (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']);
		}
		if ($scope === 'opd') {
			return ((isset($this->data['hasAccesstoOPDRegistration']) && $this->data['hasAccesstoOPDRegistration']) || (isset($this->data['hasAccesstoOPDEnquiry']) && $this->data['hasAccesstoOPDEnquiry']));
		}
		if ($scope === 'financial') {
			$isCashier = ($module === 'cashier' || $module === 'billing' || $module === 'billing / cashier' || $module === 'billing/cashier');
			$hasAccesstoBilling = isset($this->data['hasAccesstoBilling']) && $this->data['hasAccesstoBilling'];
			$hasAccesstoPOS     = isset($this->data['hasAccesstoPOS'])     && $this->data['hasAccesstoPOS'];
			return ($isAdmin || $isCashier || has_role('cashier') || $hasAccesstoBilling || $hasAccesstoPOS);
		}
		if ($scope === 'clinical') {
			return ((isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) || (isset($this->data['hasAccesstoNurse']) && $this->data['hasAccesstoNurse']));
		}
		return false;
	}

	public function require_report_scope($scope){
		if (!$this->user_can_generate_report_scope($scope)) {
			redirect(base_url().'access_denied');
			exit;
		}
	}

	public function audit_report_generation($scope, $report_key, $format){
		$scope = strtolower(trim((string)$scope));
		$report_key = trim((string)$report_key);
		$format = trim((string)$format);
		if ($report_key === '') {
			return;
		}
		$value = $scope.'|'.$report_key;
		if ($format !== '') {
			$value .= '|'.$format;
		}
		$this->logfile('Reports', 'GENERATE', $value);
	}
	
	public function getRoomName($category_id){
		$this->data['room'] = $this->general_model->getRoomNameLists($category_id);
		$this->load->view('app/general/roomList',$this->data);
	}
	
	public function getBedList($category_id){
		$this->data['bed'] = $this->general_model->getBedList($category_id);
		$this->load->view('app/general/bedList',$this->data);
	}
	
	public function ipdLists($val = NULL){
		$this->data['showPatients'] = $this->general_model->ipdLists($val);
		$this->load->view("app/general/showIPD",$this->data);	
	}
	
	public function surgical_costing(){
		$this->data['surgery_list'] = $this->general_model->surgery_list($val);
		$this->load->view("app/general/surgical_costing",$this->data);	
	}	

	public function getDoctorOUT(){
		$this->data['doctor'] = $this->general_model->getDoctorAvailability('OUT');
		$this->data['docStatus'] = "OUT";
		$this->load->view('app/general/doctorsAvailability.php',$this->data);
		// echo "<pre>".print_r($this->data['bed'],TRUE)."</pre>";
	}

	public function getDoctorIN(){
		$this->data['doctor'] = $this->general_model->getDoctorAvailability('IN');
		$this->data['docStatus'] = "IN";
		$this->load->view('app/general/doctorsAvailability.php',$this->data);
		// echo "<pre>".print_r($this->data['bed'],TRUE)."</pre>";
	}

	public function procDocAvail($id, $status)
	{
		if(!$this->session->userdata('is_logged_in')){
			redirect(base_url().'login');
			return;
		}
		$sessionUserId = $this->session->userdata('user_id');
		if ((int)$sessionUserId !== (int)$id) {
			redirect(base_url().'access_denied');
			return;
		}

		if($status == "IN")
		{
			$this->data = array(
				'doctorLastIn'			=>		date("Y-m-d h:i:s"),
				'doctorIsIn'			=>		$status
			);	
		}
		else
		{
			$this->data = array(
				'doctorLastOut'			=>		date("Y-m-d h:i:s"),
				'doctorIsIn'			=>		$status
			);	
		}

		
		$this->db->where_in('user_id', array($sessionUserId, (string)(int)$sessionUserId));
		$this->db->update("users",$this->data);

		$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
		if ($ref) {
			redirect($ref);
			return;
		}
		redirect(base_url().'app/dashboard');



	}

	public function setTheme($theme = NULL)
	{
		if (!$this->session->userdata('is_logged_in')) {
			header('Content-Type: application/json; charset=utf-8');
			header('HTTP/1.1 403 Forbidden');
			echo json_encode(array('ok' => false, 'error' => 'unauthorized'));
			return;
		}

		$theme = strtolower((string) $theme);
		if ($theme !== 'dark' && $theme !== 'light') {
			$theme = 'light';
		}

		$this->session->set_userdata('ui_theme', $theme);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode(array('ok' => true, 'theme' => $theme));
	}

	
	
	
	
	
}