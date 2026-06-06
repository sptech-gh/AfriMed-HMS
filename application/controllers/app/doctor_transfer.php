<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php'; 

class Doctor_transfer extends General {

	public function __construct(){
		parent::__construct();
		$this->load->model('app/doctor_transfer_model');
		$this->load->model('general_model');
		$this->load->model('app/patient_model');
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		require_role('doctor');
		if ((!isset($this->data['hasAccesstoDoctor']) || !$this->data['hasAccesstoDoctor']) && !$this->current_user_is_super_admin()) {
			redirect(base_url().'access_denied');
		}
		$this->data['doctor_transfer_tables_ready'] = $this->doctor_transfer_model->tables_ready();
	}

	public function install(){
		if (!$this->current_user_is_super_admin()) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->doctor_transfer_model->install_tables();
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Doctor transfer tables installed/verified.</div>");
		redirect(base_url().'app/dashboard');
	}

	private function ensure_tables_ready_or_redirect(){
		if ($this->doctor_transfer_model->tables_ready()) {
			return true;
		}
		$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Doctor transfer module is not yet installed. Please ask a Super Admin to run <strong>app/doctor_transfer/install</strong>.</div>");
		redirect(base_url().'app/dashboard');
		return false;
	}

	public function inbox(){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$this->session->set_userdata(array(
			'tab' => 'doctor',
			'module' => 'doctor_transfer',
			'subtab' => '',
			'submodule' => ''
		));

		$docId = (string) $this->session->userdata('user_id');
		$this->data['incoming'] = $this->doctor_transfer_model->get_incoming($docId, 50, 0);
		$this->data['outgoing'] = $this->doctor_transfer_model->get_outgoing($docId, 50, 0);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/doctor/transfer_inbox', $this->data);
	}

	public function request($iop_id = null, $patient_no = null){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$this->session->set_userdata(array(
			'tab' => 'doctor',
			'module' => 'doctor_transfer',
			'subtab' => '',
			'submodule' => ''
		));

		$iop_id = (string) $iop_id;
		$patient_no = (string) $patient_no;

		$encounter = $this->doctor_transfer_model->get_encounter($iop_id, $patient_no);
		if (!$encounter) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Encounter not found.</div>");
			redirect(base_url().'app/doctor/opd');
			return;
		}

		if ((string)$encounter->doctor_id !== (string)$this->session->userdata('user_id')) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->data['encounter'] = $encounter;
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/doctor/transfer_request', $this->data);
	}

	public function submit_request(){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$iop_id = $this->input->post('iop_id');
		$patient_no = $this->input->post('patient_no');
		$to_doctor_id = $this->input->post('to_doctor_id');
		$reason = $this->input->post('reason');
		$handover_note = $this->input->post('handover_note');
		$reason_code = $this->input->post('reason_code');
		$urgency_level = $this->input->post('urgency_level');

		$res = $this->doctor_transfer_model->create_transfer_request($iop_id, $patient_no, $to_doctor_id, $reason, $handover_note, $reason_code, $urgency_level);
		if (!$res['ok']) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transfer request failed (".htmlspecialchars($res['error']).").</div>");
			redirect(base_url().'app/doctor_transfer/request/'.$iop_id.'/'.$patient_no);
			return;
		}

		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transfer request submitted.</div>");
		redirect(base_url().'app/doctor_transfer/view/'.$res['transfer_id']);
	}

	public function view($transfer_id){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$this->session->set_userdata(array(
			'tab' => 'doctor',
			'module' => 'doctor_transfer',
			'subtab' => '',
			'submodule' => ''
		));

		$transfer = $this->doctor_transfer_model->get_transfer($transfer_id);
		if (!$transfer) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transfer not found.</div>");
			redirect(base_url().'app/doctor_transfer/inbox');
			return;
		}

		$docId = (string) $this->session->userdata('user_id');
		if ((string)$transfer->from_doctor_id !== $docId && (string)$transfer->to_doctor_id !== $docId) {
			redirect(base_url().'access_denied');
			return;
		}

		$isFirstView = false;
		if ((string)$transfer->to_doctor_id === $docId) {
			$isFirstView = $this->doctor_transfer_model->mark_first_viewed((int)$transfer_id, $docId);
		}

		$checklist = array();
		if (isset($transfer->handover_checklist_json) && trim((string)$transfer->handover_checklist_json) !== '') {
			$decoded = json_decode((string)$transfer->handover_checklist_json, true);
			if (is_array($decoded)) {
				$checklist = $decoded;
			}
		}

		$this->data['transfer'] = $transfer;
		$this->data['handover_checklist'] = $checklist;
		$this->data['is_first_view'] = $isFirstView;
		$this->data['notes'] = $this->doctor_transfer_model->get_notes($transfer_id);
		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($transfer->patient_no);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/doctor/transfer_view', $this->data);
	}

	public function accept($transfer_id){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$handover_note = $this->input->post('handover_note');
		$res = $this->doctor_transfer_model->accept($transfer_id, $handover_note);
		if (!$res['ok']) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Accept failed (".htmlspecialchars($res['error']).").</div>");
			redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
			return;
		}
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transfer accepted. Patient moved into your queue.</div>");
		redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
	}

	public function reject($transfer_id){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$note = $this->input->post('note');
		$res = $this->doctor_transfer_model->reject($transfer_id, $note);
		if (!$res['ok']) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Reject failed (".htmlspecialchars($res['error']).").</div>");
			redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
			return;
		}
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transfer rejected.</div>");
		redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
	}

	public function cancel($transfer_id){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		if (strtoupper((string)$this->input->server('REQUEST_METHOD')) !== 'POST') {
			redirect(base_url().'access_denied');
			return;
		}
		$res = $this->doctor_transfer_model->cancel($transfer_id);
		if (!$res['ok']) {
			$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cancel failed (".htmlspecialchars($res['error']).").</div>");
			redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
			return;
		}
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Transfer canceled.</div>");
		redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
	}

	public function add_note($transfer_id){
		if (!$this->ensure_tables_ready_or_redirect()) { return; }
		$transfer = $this->doctor_transfer_model->get_transfer($transfer_id);
		if (!$transfer) {
			redirect(base_url().'access_denied');
			return;
		}
		$docId = (string) $this->session->userdata('user_id');
		if ((string)$transfer->from_doctor_id !== $docId && (string)$transfer->to_doctor_id !== $docId) {
			redirect(base_url().'access_denied');
			return;
		}

		$note = $this->input->post('note');
		$note = trim((string)$note);
		if ($note !== '') {
			$this->doctor_transfer_model->add_note($transfer_id, (string)$this->session->userdata('user_id'), $note);
			$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Note added.</div>");
		}
		redirect(base_url().'app/doctor_transfer/view/'.$transfer_id);
	}
}
