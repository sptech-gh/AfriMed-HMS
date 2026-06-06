<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Dashboard extends General{

	function __construct(){
		parent::__construct();	
		$this->load->model("app/dashboard_model");
		$this->load->model("app/doctor_model");
		$this->load->model("app/doctor_transfer_model");
		$this->load->model("app/laboratory_model");
		$this->load->model("app/clinical_workflow_model");
		if(General::is_logged_in() == FALSE){
            redirect(base_url().'login');    
        }
		General::variable();
	}
	
	public function index(){
		$this->dashboard();	
	}
	
	public function dashboard(){
		$this->session->set_userdata(array(
                 'tab'          =>      '',
                 'module'       =>      '',
                 'subtab'       =>      '',
                 'submodule'    =>      ''));

		$roleKey = get_role_key();

		switch ($roleKey) {
			case 'admin':
				$this->_load_admin_data();
				$view = 'app/dashboard_admin';
				break;
			case 'doctor':
				$this->_load_doctor_data();
				$view = 'app/dashboard_doctor';
				break;
			case 'nurse':
				$this->_load_nurse_data();
				$view = 'app/dashboard_nurse';
				break;
			case 'laboratory':
				redirect('app/laboratory/lab_queue');
				return;
			case 'pharmacist':
				$this->_load_pharmacy_data();
				$view = 'app/dashboard_pharmacy';
				break;
			case 'receptionist':
				$this->_load_receptionist_data();
				$view = 'app/dashboard_receptionist';
				break;
			case 'cashier':
				// Redirect cashiers to Unified Billing Dashboard (single source of truth)
				redirect('app/unified_billing');
				break;
			case 'sonographer':
				redirect('app/sonography/imaging_queue/sonography');
				return;
			default:
				$this->_load_admin_data();
				$view = 'app/dashboard';
				break;
		}

		$this->load->view($view, $this->data);
	}

	private function _load_admin_data() {
		$this->data['total_patients']      = $this->dashboard_model->count_total_patients();
		$this->data['total_users']         = $this->dashboard_model->count_total_users();
		$this->data['today_opd']           = $this->dashboard_model->count_today_opd();
		$this->data['today_ipd']           = $this->dashboard_model->count_today_ipd();
		$this->data['today_appointments']  = $this->dashboard_model->count_today_appointments();
		$this->data['revenue_today']       = $this->dashboard_model->get_revenue_today();
		$this->data['payments_today']      = $this->dashboard_model->get_payments_today();
		$this->data['opd_waiting']         = $this->dashboard_model->count_opd_waiting();
		$this->data['opd_in_consultation'] = $this->dashboard_model->count_opd_in_consultation();
		$this->data['opd_completed_today'] = $this->dashboard_model->count_opd_completed_today();
		$this->data['latest_patient']      = $this->dashboard_model->latest_patient();
		$this->data['latest_visited_patient'] = $this->dashboard_model->latest_visited_patient();
		$this->data['getTodayAppointment'] = $this->dashboard_model->getTodayAppointment();
	}

	private function _load_doctor_data() {
		$doctor_id = $this->session->userdata('user_id');
		$this->data['opd_waiting']         = $this->dashboard_model->count_opd_waiting($doctor_id);
		$this->data['opd_in_consultation'] = $this->dashboard_model->count_opd_in_consultation($doctor_id);
		$this->data['opd_completed_today'] = $this->dashboard_model->count_opd_completed_today($doctor_id);
		$this->data['ipd_count']           = $this->dashboard_model->count_doctor_ipd($doctor_id);
		$this->data['today_appointments']  = $this->dashboard_model->count_today_appointments();
		$this->data['doctor_appointments'] = $this->dashboard_model->get_doctor_appointments($doctor_id);
		$this->data['opd_waiting_list']    = $this->dashboard_model->get_opd_waiting_list($doctor_id);
		$this->data['ipd_patients_list']   = $this->dashboard_model->get_ipd_patients_list($doctor_id);
	}

	private function _load_nurse_data() {
		$this->data['admitted_count']  = $this->dashboard_model->count_admitted_patients();
		$this->data['pending_vitals']  = $this->dashboard_model->count_pending_vitals();
		$this->data['today_opd']       = $this->dashboard_model->count_today_opd_visits();
		$this->data['admitted_list']   = $this->dashboard_model->get_admitted_patients();
	}

	private function _load_lab_data() {
		if (!$this->session->userdata('_schema_clinical_workflow_ok')) {
			$this->clinical_workflow_model->ensure_clinical_schema();
			$this->session->set_userdata('_schema_clinical_workflow_ok', 1);
		}
		$this->data['tests_today']         = $this->clinical_workflow_model->count_tests_today();
		$this->data['pending_labs']        = $this->clinical_workflow_model->count_pending_tests();
		$this->data['completed_today']     = $this->clinical_workflow_model->count_completed_tests_today();
		$this->data['urgent_tests']        = $this->clinical_workflow_model->count_urgent_tests();
		$this->data['lab_worklist']        = $this->clinical_workflow_model->get_lab_worklist();
	}

	private function _load_pharmacy_data() {
		if (!$this->session->userdata('_schema_clinical_workflow_ok')) {
			$this->clinical_workflow_model->ensure_clinical_schema();
			$this->session->set_userdata('_schema_clinical_workflow_ok', 1);
		}
		$this->data['pending_rx']      = $this->clinical_workflow_model->count_pharmacy_pending();
		$this->data['reserved_rx']     = $this->clinical_workflow_model->count_pharmacy_reserved();
		$this->data['partial_rx']      = $this->clinical_workflow_model->count_pharmacy_partial();
		$this->data['dispensed_today'] = $this->clinical_workflow_model->count_pharmacy_dispensed_today();
		$this->data['pending_rx_list'] = $this->dashboard_model->get_pending_prescriptions();
	}

	private function _load_receptionist_data() {
		$this->data['today_registrations']  = $this->dashboard_model->count_today_registrations();
		$this->data['today_opd']            = $this->dashboard_model->count_today_opd();
		$this->data['waiting_patients']     = $this->dashboard_model->count_opd_waiting();
		$this->data['opd_in_consultation']  = $this->dashboard_model->count_opd_in_consultation();
		$this->data['opd_completed_today']  = $this->dashboard_model->count_opd_completed_today();
		$this->data['today_appointments']   = $this->dashboard_model->count_today_appointments();
		$this->data['recent_registrations'] = $this->dashboard_model->get_recent_registrations();
		$this->data['opd_queue']            = $this->dashboard_model->get_opd_queue();
	}

	private function _load_cashier_data() {
		$this->data['today_invoices']       = $this->dashboard_model->count_today_invoices();
		$this->data['unpaid_invoices']      = $this->dashboard_model->count_unpaid_invoices();
		$this->data['payments_today']       = $this->dashboard_model->get_payments_today();
		$this->data['revenue_today']        = $this->dashboard_model->get_revenue_today();
		$this->data['outstanding']          = $this->dashboard_model->get_outstanding_amount();
		$this->data['recent_invoices']      = $this->dashboard_model->get_recent_invoices();
		// Legacy individual lab bills (kept for backward compatibility)
		$this->data['pending_lab_bills']    = $this->dashboard_model->count_pending_lab_bills();
		$this->data['pending_lab_list']     = $this->dashboard_model->get_pending_lab_bills_list(20);
		// NEW: Patient-grouped pending bills for consolidated billing
		$this->data['pending_patients_count'] = $this->dashboard_model->count_patients_with_pending_bills();
		$this->data['pending_patients']     = $this->dashboard_model->get_pending_bills_by_patient(30);
	}

	public function counts_ajax()
	{
		header('Content-Type: application/json');
		if (General::is_logged_in() == FALSE) {
			echo json_encode(array('ok' => false));
			return;
		}
		$this->load->model('app/opd_model');
		$doctor_id = null;
		if (get_role_key() === 'doctor') {
			$doctor_id = $this->session->userdata('user_id');
		}
		echo json_encode(array(
			'ok'               => true,
			'opd_waiting'      => $this->dashboard_model->count_opd_waiting($doctor_id),
			'opd_consultation' => $this->dashboard_model->count_opd_in_consultation($doctor_id),
			'opd_completed'    => $this->dashboard_model->count_opd_completed_today($doctor_id),
			'today_opd'        => $this->dashboard_model->count_today_opd(),
			'today_ipd'        => $this->dashboard_model->count_today_ipd(),
			'ts'               => time(),
		));
	}

	private function _load_sonographer_data() {
		$this->load->model('app/laboratory_model');
		$this->laboratory_model->install_imaging_tables();
		$this->data['pending_scans']     = $this->dashboard_model->count_pending_sonography();
		$this->data['completed_today']   = $this->dashboard_model->count_completed_sonography_today();
		$this->data['pending_scan_list'] = $this->dashboard_model->get_pending_sonography();
	}
	
}