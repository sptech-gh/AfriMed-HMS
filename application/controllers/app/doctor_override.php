<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Doctor_override extends General {

	public function __construct(){
		parent::__construct();
		$this->load->model('app/encounter_owner_model');
		$this->load->model('general_model');
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
		}
		General::variable();
		require_role('doctor');
	}

	private function override_key($encounter_type, $iop_id){
		return strtoupper(trim((string)$encounter_type)).'|'.(string)$iop_id;
	}

	private function has_override_permission(){
		if ($this->current_user_is_admin()) {
			return true;
		}
		$u = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
		$roleId = ($u && isset($u->user_role)) ? (string)$u->user_role : '';
		return $this->encounter_owner_model->role_can_override($roleId);
	}

	public function grant($encounter_type = null, $iop_id = null, $patient_no = null){
		$encounter_type = strtoupper(trim((string)$encounter_type));
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		if ($encounter_type !== 'OPD' && $encounter_type !== 'IPD') {
			redirect(base_url().'access_denied');
			return;
		}
		if ($iop_id === '') {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->has_override_permission()) {
			redirect(base_url().'access_denied');
			return;
		}

		$k = $this->override_key($encounter_type, $iop_id);
		$overrides = $this->session->userdata('doctor_overrides');
		if (!is_array($overrides)) {
			$overrides = array();
		}
		$overrides[$k] = time();
		$this->session->set_userdata('doctor_overrides', $overrides);

		$this->encounter_owner_model->logfile(
			'DoctorEncounter',
			'OVERRIDE_USED',
			'iop:'.$iop_id.'|type:'.$encounter_type,
			$this->session->userdata('user_id')
		);
		$this->encounter_owner_model->record_event($iop_id, $patient_no !== '' ? $patient_no : null, $encounter_type, 'OVERRIDE_USED', null, null, null, $this->session->userdata('user_id'));

		$return = (string)$this->input->get('return');
		if ($return !== '') {
			redirect($return);
			return;
		}
		if ($patient_no !== '') {
			if ($encounter_type === 'IPD') {
				redirect(base_url().'app/ipd/view/'.$iop_id.'/'.$patient_no);
				return;
			}
			redirect(base_url().'app/opd/view/'.$iop_id.'/'.$patient_no);
			return;
		}
		redirect(base_url().'app/dashboard');
	}

	public function clear($encounter_type = null, $iop_id = null){
		$encounter_type = strtoupper(trim((string)$encounter_type));
		$iop_id = (string)$iop_id;
		if ($encounter_type !== 'OPD' && $encounter_type !== 'IPD') {
			redirect(base_url().'access_denied');
			return;
		}
		if ($iop_id === '') {
			redirect(base_url().'access_denied');
			return;
		}
		$k = $this->override_key($encounter_type, $iop_id);
		$overrides = $this->session->userdata('doctor_overrides');
		if (!is_array($overrides)) {
			$overrides = array();
		}
		if (isset($overrides[$k])) {
			unset($overrides[$k]);
			$this->session->set_userdata('doctor_overrides', $overrides);
		}
		redirect(base_url().'app/dashboard');
	}
}
