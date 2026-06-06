<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Vitals extends General {

	public function __construct(){
		parent::__construct();
		$this->load->model('app/nurse_enhancement_model');
		$this->load->model('app/patient_model');
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		require_role(array('nurse', 'doctor', 'receptionist', 'admin'));
	}

	public function index(){
		redirect(base_url().'app/vitals/vitals_queue');
	}

	public function vitals_queue(){
		$this->session->set_userdata(array(
			'tab'       => 'nurse_module',
			'module'    => 'nurse_vitals_queue',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->nurse_enhancement_model->ensure_vitals_workflow_schema();
		$this->data['message']        = $this->session->flashdata('message');
		$this->data['vitals_queue']   = $this->nurse_enhancement_model->get_opd_vitals_queue();
		$this->data['pending_count']  = $this->nurse_enhancement_model->count_opd_vitals_pending();
		$this->data['done_count']     = $this->nurse_enhancement_model->count_opd_vitals_done();
		$this->load->view('app/nurse_module/vitals_queue', $this->data);
	}

	public function record_vitals(){
		list($iop_id, $patient_no) = $this->get_url_params(4, 5);

		if (!$iop_id || !$patient_no) {
			redirect(base_url().'app/vitals/vitals_queue');
			return;
		}

		$this->session->set_userdata(array(
			'tab'       => 'nurse_module',
			'module'    => 'nurse_vitals_queue',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->nurse_enhancement_model->ensure_vitals_workflow_schema();
		$visit = $this->nurse_enhancement_model->get_opd_visit($iop_id);
		if (!$visit) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning'><i class='fa fa-warning'></i> Visit not found.</div>");
			redirect(base_url().'app/vitals/vitals_queue');
			return;
		}

		$this->data['visit']       = $visit;
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['message']     = $this->session->flashdata('message');
		$this->load->view('app/nurse_module/record_vitals', $this->data);
	}

	public function save_opd_vitals(){
		$iop_id     = trim((string)$this->input->post('iop_id'));
		$patient_no = trim((string)$this->input->post('patient_no'));

		if (!$iop_id || !$patient_no) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing patient context.</div>");
			redirect(base_url().'app/vitals/vitals_queue');
			return;
		}

		$bp          = trim((string)$this->input->post('bp'));
		$temperature = $this->vitals_non_negative_number($this->input->post('temperature'));
		$pulse_rate  = $this->vitals_non_negative_number($this->input->post('pulse_rate'));
		$weight      = $this->vitals_non_negative_number($this->input->post('weight'));
		$height      = $this->vitals_non_negative_number($this->input->post('height'));
		$respiration = $this->vitals_non_negative_number($this->input->post('respiration'));

		if ($temperature === false || $pulse_rate === false || $weight === false || $height === false || $respiration === false) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vitals values must be numeric and cannot be negative.</div>");
			redirect(base_url().'app/vitals/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		if ($bp === '' && $temperature === null && $pulse_rate === null && $weight === null) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>At least one vital sign (BP, Temperature, Pulse, or Weight) is required.</div>");
			redirect(base_url().'app/vitals/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		if (!$this->db->table_exists('iop_vital_parameters') || !$this->db->table_exists('patient_details_iop')) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save vitals. Please try again.</div>");
			redirect(base_url().'app/vitals/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		$actor_id = $this->session->userdata('user_id');
		$vital_id = $this->nurse_enhancement_model->save_opd_vitals($iop_id, $bp, $temperature, $pulse_rate, $weight, $height, $respiration, $actor_id);
		if (!$vital_id) {
			$this->session->set_flashdata('message',"<div class='alert alert-danger alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Unable to save vitals. Please try again.</div>");
			redirect(base_url().'app/vitals/record_vitals/'.$iop_id.'/'.$patient_no);
			return;
		}

		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Vitals recorded successfully. Patient is now ready for consultation.</div>");
		redirect(base_url().'app/vitals/vitals_queue');
	}

	protected function vitals_non_negative_number($value)
	{
		if ($value === null || $value === '') {
			return null;
		}
		if (!is_numeric($value)) {
			return false;
		}
		$value = $value + 0;
		return $value < 0 ? false : $value;
	}
}
