<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Doctor_messages extends General{

	public function __construct(){
		parent::__construct();
		$this->load->model('app/nurse_enhancement_model');
		$this->load->model('app/patient_model');
		$this->load->model('app/ipd_model');
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		require_role(array('doctor', 'nurse'));
	}

	public function inbox(){
		$this->session->set_userdata(array(
			'tab' => 'doctor',
			'module' => 'doctor_messages',
			'subtab' => '',
			'submodule' => ''
		));

		if (!isset($this->data['hasAccesstoDoctor']) || !$this->data['hasAccesstoDoctor']) {
			redirect(base_url().'access_denied');
			return;
		}

		$doctorId = $this->session->userdata('user_id');
		$this->data['messages_ready'] = $this->nurse_enhancement_model->table_exists('nurse_doctor_message');
		$this->data['inbox'] = $this->data['messages_ready'] ? $this->nurse_enhancement_model->get_doctor_inbox($doctorId) : array();
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/doctor/messages_inbox', $this->data);
	}

	public function thread(){
		if (!isset($this->data['hasAccesstoDoctor']) || !$this->data['hasAccesstoDoctor']) {
			redirect(base_url().'access_denied');
			return;
		}

		$iop_id = $this->uri->segment('4');
		$patient_no = $this->uri->segment('5');

		$this->data['getOPDPatient'] = $this->ipd_model->getIPDPatient($iop_id);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);

		$doctorId = $this->session->userdata('user_id');
		$this->data['messages_ready'] = $this->nurse_enhancement_model->table_exists('nurse_doctor_message');
		$this->data['thread'] = $this->data['messages_ready'] ? $this->nurse_enhancement_model->get_messages_thread($iop_id, $patient_no) : array();
		$this->data['reply_to_user_id'] = $this->data['messages_ready'] ? $this->nurse_enhancement_model->get_last_counterparty_user($iop_id, $patient_no, $doctorId) : '';

		if ($this->data['messages_ready']) {
			$this->nurse_enhancement_model->mark_thread_read_for_doctor($iop_id, $patient_no, $doctorId);
		}

		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/doctor/messages_thread', $this->data);
	}

	public function reply(){
		if (!isset($this->data['hasAccesstoDoctor']) || !$this->data['hasAccesstoDoctor']) {
			redirect(base_url().'access_denied');
			return;
		}

		if (!$this->nurse_enhancement_model->table_exists('nurse_doctor_message')) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Nurse messaging is not installed. Ask an Administrator to run <strong>app/nurse_module/install_enhancements</strong>.</div>");
			redirect(base_url().'app/doctor_messages/inbox');
			return;
		}

		$iop_id = $this->input->post('iop_id');
		$patient_no = $this->input->post('patient_no');
		$to_nurse_id = $this->input->post('to_nurse_id');
		$message = $this->input->post('message');

		if (trim((string)$message) === '') {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Message cannot be empty.</div>");
			redirect(base_url().'app/doctor_messages/thread/'.$iop_id.'/'.$patient_no);
			return;
		}

		$this->nurse_enhancement_model->send_message_to_nurse($iop_id, $patient_no, $this->session->userdata('user_id'), $to_nurse_id, $message);
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Message sent.</div>");
		redirect(base_url().'app/doctor_messages/thread/'.$iop_id.'/'.$patient_no);
	}
}
