<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

class Patient_history extends General{
	private $default_limit = 20;

	public function __construct(){
		parent::__construct();
		$this->load->model('app/patient_history_model');
		$this->load->model('app/patient_model');
		$this->load->model('general_model');
		if(General::is_logged_in() == FALSE){
			redirect(base_url().'login');
			return;
		}
		General::variable();
		if (!$this->session->userdata('_schema_patient_history_ok')) {
			$this->patient_history_model->ensure_access_log_schema();
			$this->patient_history_model->ensure_history_indexes();
			$this->session->set_userdata('_schema_patient_history_ok', 1);
		}
	}

	private function current_user_is_nurse_user(){
		$module = $this->current_user_module_key();
		$roleId = $this->current_user_role_id();
		return ($module === 'nurse' || $roleId === 7);
	}

	private function require_history_access_or_redirect(){
		if ($this->current_user_is_admin() || $this->current_user_is_doctor() || $this->current_user_is_nurse_user() || $this->current_user_is_reception() || has_role('cashier')) {
			return;
		}
		redirect(base_url().'access_denied');
		exit;
	}

	private function is_full_clinical_access(){
		return (
			$this->current_user_is_admin()
			|| $this->current_user_is_doctor()
			|| $this->current_user_is_nurse_user()
			|| $this->current_user_is_reception()
		);
	}

	private function audit_access($patient_no, $action, $context = array()){
		$route = method_exists($this->uri, 'uri_string') ? (string)$this->uri->uri_string() : '';
		$ctx = array_merge(array(
			'user_id' => (string)$this->session->userdata('user_id'),
			'user_role' => (string)$this->current_user_role_id(),
			'module' => (string)$this->current_user_module_key(),
			'route' => $route,
			'ipaddress' => $this->input->ip_address(),
			'user_agent' => substr((string)$this->input->user_agent(), 0, 255)
		), is_array($context) ? $context : array());
		$this->patient_history_model->log_access($patient_no, $action, $ctx);
		General::logfile('PatientHistory', substr((string)$action, 0, 10), 'patient_no:'.(string)$patient_no);
	}

	public function index($patient_no = null){
		$this->require_history_access_or_redirect();
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') {
			redirect(base_url().'app/patient');
			return;
		}

		$this->data['patientInfo'] = $this->patient_model->getPatientInfo($patient_no);
		if (!$this->data['patientInfo']) {
			redirect(base_url().'access_denied');
			return;
		}
		$this->data['patient_no'] = $patient_no;
		$this->data['full_access'] = $this->is_full_clinical_access();
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['doctorList'] = $this->general_model->doctorList();
		$this->data['summary'] = $this->patient_history_model->get_patient_summary($patient_no);
		$this->data['record_counts'] = $this->patient_history_model->get_patient_record_counts($patient_no);
		$this->data['latest_vitals'] = $this->patient_history_model->get_latest_vitals($patient_no);
		$this->data['allergy_summary'] = $this->patient_history_model->get_allergy_summary($patient_no);
		$this->data['medical_history'] = $this->patient_history_model->get_medical_history_summary($patient_no);
		if ($this->is_full_clinical_access()) {
			$this->data['active_medications'] = $this->patient_history_model->get_active_medications($patient_no, 10);
		} else {
			$this->data['active_medications'] = array();
		}

		$this->session->set_userdata(array(
			'tab' => 'patient',
			'module' => 'patient_history',
			'subtab' => '',
			'submodule' => ''
		));

		$this->audit_access($patient_no, 'VIEW_PAGE');
		$this->load->view('app/patient/history_timeline', $this->data);
	}

	public function summary_json($patient_no = null){
		$this->require_history_access_or_redirect();
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'error' => 'missing_patient_no')));
			return;
		}

		$full = $this->is_full_clinical_access();
		$data = array(
			'summary'        => $this->patient_history_model->get_patient_summary($patient_no),
			'record_counts'  => $this->patient_history_model->get_patient_record_counts($patient_no),
			'latest_vitals'  => $this->patient_history_model->get_latest_vitals($patient_no),
			'allergy_summary'=> $this->patient_history_model->get_allergy_summary($patient_no),
			'medical_history'=> $this->patient_history_model->get_medical_history_summary($patient_no),
			'active_medications' => $full ? $this->patient_history_model->get_active_medications($patient_no, 10) : array()
		);

		$this->audit_access($patient_no, 'VIEW_SUMMARY');

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array('ok' => true, 'full_access' => $full, 'data' => $data)));
	}

	public function visits_json($patient_no = null){
		$this->require_history_access_or_redirect();
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'error' => 'missing_patient_no')));
			return;
		}

		$limit = (int)$this->input->get('limit');
		$offset = (int)$this->input->get('offset');
		if ($limit <= 0 || $limit > 50) {
			$limit = $this->default_limit;
		}
		if ($offset < 0) {
			$offset = 0;
		}

		$filters = array(
			'from' => trim((string)$this->input->get('from')),
			'to' => trim((string)$this->input->get('to')),
			'doctor' => trim((string)$this->input->get('doctor')),
			'encounter' => trim((string)$this->input->get('encounter')),
			'type' => trim((string)$this->input->get('type')),
			'q' => trim((string)$this->input->get('q'))
		);

		$total = $this->patient_history_model->count_visits($patient_no, $filters);
		$rows = $this->patient_history_model->get_visits($patient_no, $filters, $limit, $offset);

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'ok' => true,
				'patient_no' => $patient_no,
				'total' => $total,
				'limit' => $limit,
				'offset' => $offset,
				'rows' => $rows
			)));
	}

	public function visit_json($iop_id = null){
		$this->require_history_access_or_redirect();
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') {
			$this->output
				->set_status_header(400)
				->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'error' => 'missing_iop_id')));
			return;
		}

		$hdr = $this->patient_history_model->get_visit_header($iop_id);
		if (!$hdr || !isset($hdr->patient_no)) {
			$this->output
				->set_status_header(404)
				->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'error' => 'not_found')));
			return;
		}

		$patient_no = (string)$hdr->patient_no;
		$details = $this->patient_history_model->get_visit_details($iop_id);
		if (!$details) {
			$this->output
				->set_status_header(404)
				->set_content_type('application/json')
				->set_output(json_encode(array('ok' => false, 'error' => 'not_found')));
			return;
		}

		// Consolidated release snapshot-read enforcement (prevents legacy leakage on timeline)
		$snapshotRead = (bool)$this->config->item('lab_release_snapshot_read_enabled');
		$isAdmin = $this->current_user_is_admin();
		$isLab = (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory']);
		if ($snapshotRead && !$isAdmin && !$isLab) {
			if ($this->db->table_exists('iop_laboratory_release_snapshot') && $this->db->table_exists('iop_laboratory_release_batch')) {
				$q = $this->db->query(
					"SELECT S.io_lab_id, S.findings_snapshot, S.result_snapshot, S.attachment_snapshot\n"
					." FROM iop_laboratory_release_snapshot S\n"
					." JOIN iop_laboratory_release_batch B ON B.release_id = S.release_id AND B.InActive = 0 AND B.release_status = 'RELEASED'\n"
					." WHERE S.iop_id = ? AND S.InActive = 0\n"
					." ORDER BY S.snapshot_id DESC",
					array((string)$iop_id)
				);
				$rows = $q ? $q->result_array() : array();
				$snapMap = array();
				foreach ($rows as $r) {
					$k = isset($r['io_lab_id']) ? (int)$r['io_lab_id'] : 0;
					if ($k > 0 && !isset($snapMap[$k])) {
						$snapMap[$k] = $r;
					}
				}

				$pending = 'PENDING CONSOLIDATED RELEASE';
				foreach (array('labs', 'imaging') as $bucket) {
					if (!isset($details[$bucket]) || !is_array($details[$bucket])) {
						continue;
					}
					foreach ($details[$bucket] as $idx => $row) {
						$ioLabId = isset($row['io_lab_id']) ? (int)$row['io_lab_id'] : 0;
						if ($ioLabId > 0 && isset($snapMap[$ioLabId])) {
							$snap = $snapMap[$ioLabId];
							$details[$bucket][$idx]['findings'] = isset($snap['findings_snapshot']) ? (string)$snap['findings_snapshot'] : '';
							$details[$bucket][$idx]['result'] = isset($snap['result_snapshot']) ? (string)$snap['result_snapshot'] : '';
							$details[$bucket][$idx]['lab_result_upload'] = isset($snap['attachment_snapshot']) ? (string)$snap['attachment_snapshot'] : '';
						} else {
							$details[$bucket][$idx]['findings'] = $pending;
							$details[$bucket][$idx]['result'] = $pending;
							$details[$bucket][$idx]['lab_result_upload'] = '';
						}
					}
				}
			}
		}

		$full = $this->is_full_clinical_access();
		if (!$full) {
			$details['progress_notes'] = array();
			$details['nurse_notes'] = array();
			$details['diagnoses'] = array();
			$details['complaints'] = array();
			$details['prescriptions'] = array();
			$details['labs'] = array();
			$details['imaging'] = array();
			$details['billing'] = array('total' => 0.0, 'paid' => 0.0, 'outstanding' => 0.0, 'invoices' => array());
		}

		$this->audit_access($patient_no, 'VIEW_VISIT', array(
			'iop_id' => $iop_id,
			'encounter_type' => isset($hdr->patient_type) ? (string)$hdr->patient_type : null
		));

		$this->output
			->set_content_type('application/json')
			->set_output(json_encode(array(
				'ok' => true,
				'full_access' => $full,
				'data' => $details
			)));
	}
}
