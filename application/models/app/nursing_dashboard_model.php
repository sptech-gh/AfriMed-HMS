<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . 'services/Nursing/ClinicalEventAggregator.php');
require_once(APPPATH . 'services/Nursing/Workspace/WorkspacePayloadComposer.php');
require_once(APPPATH . 'services/Nursing/BedsideProcedureClinicalEnricher.php');

class Nursing_dashboard_model extends CI_Model{

	const VITALS_OVERDUE_HOURS = 6;
	const STALE_ADMISSION_DAYS = 30;
	const NO_RECENT_NOTE_HOURS = 24;
	const REFRESH_INTERVAL_SECONDS = 90;
	const STALE_AFTER_SECONDS = 180;

	public function __construct(){
		parent::__construct();
	}

	private function table_exists($table){
		return $this->db->table_exists($table);
	}

	private function field_exists($field, $table){
		return $this->table_exists($table) && $this->db->field_exists($field, $table);
	}

	public function get_dashboard_payload($ward_id = '', $shift = '', $date = ''){
		$date = trim((string)$date) !== '' ? trim((string)$date) : date('Y-m-d');
		$generatedAt = date('Y-m-d H:i:s');
		$warnings = $this->get_source_warnings();
		if (!$this->table_exists('patient_details_iop') || !$this->table_exists('patient_personal_info')) {
			return array(
				'status' => 'error',
				'code' => 'CENSUS_SOURCE_UNAVAILABLE',
				'generated_at' => $generatedAt,
				'message' => 'Active nursing census source is unavailable.',
				'patients' => array(),
				'alerts' => array(),
				'refresh' => $this->refresh_contract($generatedAt, 'source_unavailable'),
				'meta' => array('partial' => true, 'warnings' => $warnings)
			);
		}
		$patients = $this->get_active_patients($ward_id);
		$detainedOpdPatients = $this->get_detained_opd_patients();
		$shiftContext = $this->current_shift($shift);
		$alerts = array();
		$pendingMedications = array();
		$unknownMedications = array();
		$overdueVitals = array();
		$pendingProcedures = array();

		foreach ($patients as $patient) {
			$patientAlerts = isset($patient['alerts']) && is_array($patient['alerts']) ? $patient['alerts'] : array();
			foreach ($patientAlerts as $alert) {
				$alerts[] = $alert;
			}
			$medications = isset($patient['medications']) && is_array($patient['medications']) ? $patient['medications'] : array();
			foreach ($medications as $medication) {
				if (isset($medication['status']) && $medication['status'] === 'due') {
					$pendingMedications[] = $medication;
				} elseif (isset($medication['status']) && $medication['status'] === 'unknown') {
					$unknownMedications[] = $medication;
				}
			}
			if (isset($patient['latest_vitals']['status']) && in_array($patient['latest_vitals']['status'], array('missing', 'overdue', 'incomplete'))) {
				$overdueVitals[] = array(
					'patient_id' => $patient['patient_id'],
					'patient_name' => $patient['name'],
					'bed_name' => $patient['bed']['bed_name'],
					'last_vitals_time' => $patient['latest_vitals']['recorded_at'],
					'overdue_duration_label' => $patient['latest_vitals']['status'] === 'missing' ? 'No vitals found' : 'Vitals need review'
				);
			}
			$procedures = isset($patient['procedures']) && is_array($patient['procedures']) ? $patient['procedures'] : array();
			foreach ($procedures as $procedure) {
				if (isset($procedure['status']) && $procedure['status'] === 'pending') {
					$pendingProcedures[] = $procedure;
				}
			}
		}
		$escalationGroups = $this->build_escalation_groups($patients);
		$handoverSnapshot = $this->build_handover_snapshot($patients, $pendingMedications, $unknownMedications, $warnings, $shiftContext, $generatedAt);

		return array(
			'status' => 'ok',
			'generated_at' => $generatedAt,
			'refresh' => $this->refresh_contract($generatedAt, 'fresh'),
			'ward' => array(
				'ward_id' => trim((string)$ward_id) !== '' ? (string)$ward_id : null,
				'ward_name' => null
			),
			'shift' => $shiftContext,
			'snapshot' => array(
				'active_patient_count' => count($patients),
				'detained_opd_count' => count($detainedOpdPatients),
				'critical_alert_count' => $this->count_alerts_by_severity($alerts, 'critical'),
				'pending_medication_count' => count($pendingMedications),
				'unknown_medication_count' => count($unknownMedications),
				'overdue_vitals_count' => count($overdueVitals),
				'pending_procedure_count' => count($pendingProcedures)
			),
			'patients' => $patients,
			'detained_opd_patients' => $detainedOpdPatients,
			'escalation_groups' => $escalationGroups,
			'handover_snapshot' => $handoverSnapshot,
			'alerts' => $alerts,
			'pending_medications' => $pendingMedications,
			'unknown_medications' => $unknownMedications,
			'overdue_vitals' => $overdueVitals,
			'pending_procedures' => $pendingProcedures,
			'meta' => array(
				'partial' => count($warnings) > 0,
				'warnings' => $warnings
			)
		);
	}

	public function get_detained_opd_patients($limit = 30, $search = ''){
		if (!$this->table_exists('patient_details_iop') || !$this->table_exists('patient_personal_info')) {
			return array();
		}
		if (!$this->field_exists('detention_start_at', 'patient_details_iop')) {
			return array();
		}

		$select = "A.IO_ID, A.patient_no, A.date_visit, A.time_visit, A.nStatus, A.department_id, A.patient_type, A.detention_start_at, B.firstname, B.middlename, B.lastname, B.age";
		$select .= $this->field_exists('converted_to_admission_at', 'patient_details_iop') ? ", A.converted_to_admission_at" : ", NULL as converted_to_admission_at";
		$select .= $this->field_exists('converted_ipd_iop_id', 'patient_details_iop') ? ", A.converted_ipd_iop_id" : ", NULL as converted_ipd_iop_id";
		$select .= $this->table_exists('system_parameters') ? ", C.cValue as title_name, G.cValue as sex_name" : ", NULL as title_name, NULL as sex_name";
		$select .= $this->table_exists('department') ? ", D.dept_name" : ", NULL as dept_name";
		$this->db->select($select, false);
		$this->db->from('patient_details_iop A');
		$this->db->join('patient_personal_info B', 'B.patient_no = A.patient_no', 'left outer');
		if ($this->table_exists('system_parameters')) {
			$this->db->join('system_parameters C', 'C.param_id = B.title', 'left outer');
			$this->db->join('system_parameters G', 'G.param_id = B.gender', 'left outer');
		}
		if ($this->table_exists('department')) {
			$this->db->join('department D', 'D.department_id = A.department_id', 'left outer');
		}

		$this->db->where('A.InActive', 0);
		$this->db->where('A.nStatus', 'Pending');
		$this->db->where("A.patient_type <> 'IPD'", null, false);
		$this->db->where("A.detention_start_at IS NOT NULL AND TRIM(CAST(A.detention_start_at AS CHAR)) <> '' AND TRIM(CAST(A.detention_start_at AS CHAR)) <> '0000-00-00 00:00:00'", null, false);
		if ($this->field_exists('converted_to_admission_at', 'patient_details_iop')) {
			$this->db->where("(A.converted_to_admission_at IS NULL OR TRIM(CAST(A.converted_to_admission_at AS CHAR)) = '' OR TRIM(CAST(A.converted_to_admission_at AS CHAR)) = '0000-00-00 00:00:00')", null, false);
		}
		if ($this->field_exists('converted_ipd_iop_id', 'patient_details_iop')) {
			$this->db->where("(A.converted_ipd_iop_id IS NULL OR A.converted_ipd_iop_id = '')", null, false);
		}
		if (trim((string)$search) !== '') {
			$search = $this->db->escape_like_str($search);
			$this->db->where("(B.firstname LIKE '%{$search}%' ESCAPE '!' OR B.lastname LIKE '%{$search}%' ESCAPE '!' OR A.patient_no LIKE '%{$search}%' ESCAPE '!' OR A.IO_ID LIKE '%{$search}%' ESCAPE '!')", null, false);
		}
		$this->db->order_by('A.detention_start_at', 'DESC');
		$this->db->limit((int)$limit);
		$query = $this->db->get();
		$rows = $query ? $query->result() : array();

		$out = array();
		foreach ($rows as $row) {
			$title = isset($row->title_name) && trim((string)$row->title_name) !== '' ? trim((string)$row->title_name) . ' ' : '';
			$name = $title . trim((string)$row->firstname . ' ' . (string)$row->middlename . ' ' . (string)$row->lastname);
			$out[] = array(
				'encounter_id' => isset($row->IO_ID) ? (string)$row->IO_ID : null,
				'patient_no' => isset($row->patient_no) ? (string)$row->patient_no : null,
				'name' => $name,
				'age' => isset($row->age) ? (string)$row->age : null,
				'sex' => isset($row->sex_name) ? (string)$row->sex_name : null,
				'department_name' => isset($row->dept_name) ? (string)$row->dept_name : null,
				'detained_since' => isset($row->detention_start_at) ? (string)$row->detention_start_at : null,
				'patient_type' => isset($row->patient_type) ? (string)$row->patient_type : null
			);
		}
		return $out;
	}

	public function get_active_patients($ward_id = '', $search = '', $priority = ''){
		if (!$this->table_exists('patient_details_iop') || !$this->table_exists('patient_personal_info')) {
			return array();
		}

		$select = "A.IO_ID, A.patient_no, A.date_visit, A.time_visit, A.nStatus, A.room_id, A.department_id, B.firstname, B.middlename, B.lastname, B.age";
		$select .= $this->table_exists('system_parameters') ? ", C.cValue as title_name, G.cValue as sex_name" : ", NULL as title_name, NULL as sex_name";
		$select .= $this->table_exists('department') ? ", D.dept_name" : ", NULL as dept_name";
		$select .= $this->table_exists('room_beds') ? ", E.bed_name, E.room_bed_id" : ", NULL as bed_name, NULL as room_bed_id";
		$select .= ($this->table_exists('room_beds') && $this->table_exists('room_master')) ? ", F.room_name, F.room_master_id" : ", NULL as room_name, NULL as room_master_id";
		$this->db->select($select, false);
		$this->db->from('patient_details_iop A');
		$this->db->join('patient_personal_info B', 'B.patient_no = A.patient_no', 'left outer');
		if ($this->table_exists('system_parameters')) {
			$this->db->join('system_parameters C', 'C.param_id = B.title', 'left outer');
			$this->db->join('system_parameters G', 'G.param_id = B.gender', 'left outer');
		}
		if ($this->table_exists('department')) {
			$this->db->join('department D', 'D.department_id = A.department_id', 'left outer');
		}
		if ($this->table_exists('room_beds')) {
			$this->db->join('room_beds E', 'E.room_bed_id = A.room_id', 'left outer');
		}
		if ($this->table_exists('room_beds') && $this->table_exists('room_master')) {
			$this->db->join('room_master F', 'F.room_master_id = E.room_master_id', 'left outer');
		}
		$this->db->where(array('A.patient_type' => 'IPD', 'A.nStatus' => 'Pending', 'A.InActive' => 0));
		if (trim((string)$ward_id) !== '' && $this->table_exists('room_beds') && $this->table_exists('room_master')) {
			$this->db->where('F.room_master_id', $ward_id);
		}
		if (trim((string)$search) !== '') {
			$search = $this->db->escape_like_str($search);
			$this->db->where("(B.firstname LIKE '%{$search}%' ESCAPE '!' OR B.lastname LIKE '%{$search}%' ESCAPE '!' OR A.patient_no LIKE '%{$search}%' ESCAPE '!' OR A.IO_ID LIKE '%{$search}%' ESCAPE '!')", null, false);
		}
		$this->db->order_by('A.date_visit', 'DESC');
		$query = $this->db->get();
		$rows = $query ? $query->result() : array();
		$patients = array();

		foreach ($rows as $row) {
			$patient = $this->build_patient_card($row);
			if (trim((string)$priority) !== '' && $patient['status_flags']['priority_state'] !== $priority) {
				continue;
			}
			$patients[] = $patient;
		}

		usort($patients, array($this, 'sort_patients_by_priority'));
		return $patients;
	}

	public function get_patient_summary($id){
		$id = trim((string)$id);
		if ($id === '') {
			return null;
		}
		$select = "A.IO_ID, A.patient_no, A.date_visit, A.time_visit, A.nStatus, A.room_id, A.department_id, B.firstname, B.middlename, B.lastname, B.age";
		$select .= $this->table_exists('system_parameters') ? ", C.cValue as title_name, G.cValue as sex_name" : ", NULL as title_name, NULL as sex_name";
		$select .= $this->table_exists('department') ? ", D.dept_name" : ", NULL as dept_name";
		$select .= $this->table_exists('room_beds') ? ", E.bed_name, E.room_bed_id" : ", NULL as bed_name, NULL as room_bed_id";
		$select .= ($this->table_exists('room_beds') && $this->table_exists('room_master')) ? ", F.room_name, F.room_master_id" : ", NULL as room_name, NULL as room_master_id";
		$this->db->select($select, false);
		$this->db->from('patient_details_iop A');
		$this->db->join('patient_personal_info B', 'B.patient_no = A.patient_no', 'left outer');
		if ($this->table_exists('system_parameters')) {
			$this->db->join('system_parameters C', 'C.param_id = B.title', 'left outer');
			$this->db->join('system_parameters G', 'G.param_id = B.gender', 'left outer');
		}
		if ($this->table_exists('department')) {
			$this->db->join('department D', 'D.department_id = A.department_id', 'left outer');
		}
		if ($this->table_exists('room_beds')) {
			$this->db->join('room_beds E', 'E.room_bed_id = A.room_id', 'left outer');
		}
		if ($this->table_exists('room_beds') && $this->table_exists('room_master')) {
			$this->db->join('room_master F', 'F.room_master_id = E.room_master_id', 'left outer');
		}
		$this->db->where(array('A.InActive' => 0));
		$this->db->where("(A.IO_ID = " . $this->db->escape($id) . " OR A.patient_no = " . $this->db->escape($id) . ")", null, false);
		$this->db->limit(1);
		$query = $this->db->get();
		$row = $query ? $query->row() : null;
		if (!$row) {
			return null;
		}
		$patient = $this->build_patient_card($row);
		return array(
			'status' => 'ok',
			'patient' => array(
				'patient_id' => $patient['patient_id'],
				'patient_no' => $patient['patient_no'],
				'encounter_id' => $patient['encounter_id'],
				'name' => $patient['name'],
				'age' => $patient['age'],
				'sex' => $patient['sex'],
				'ward_name' => $patient['ward']['ward_name'],
				'bed_name' => $patient['bed']['bed_name'],
				'admitted_at' => trim((string)$row->date_visit . ' ' . (string)$row->time_visit)
			),
			'summary' => array(
				'latest_vitals' => $patient['latest_vitals'],
				'pending_medications' => $patient['medications'],
				'recent_notes' => $this->get_recent_notes($patient['encounter_id'], 3),
				'recent_procedures' => $patient['procedures'],
				'active_alerts' => $patient['alerts'],
				'priority_score' => $patient['priority_score'],
				'priority_band' => $patient['priority_band'],
				'priority_reasons' => $patient['priority_reasons'],
				'data_quality_flags' => $patient['data_quality_flags']
			),
			'meta' => array('partial' => false, 'warnings' => array())
		);
	}

	public function get_patient_workspace_payload($id){
		$summary = $this->get_patient_summary($id);
		if (!$summary || !isset($summary['patient'])) {
			return null;
		}
		$patient = $summary['patient'];
		$iopId = isset($patient['encounter_id']) ? (string)$patient['encounter_id'] : '';
		$patientNo = isset($patient['patient_no']) ? (string)$patient['patient_no'] : '';
		$clinical = isset($summary['summary']) ? $summary['summary'] : array();
		$vitalsHistory = $this->get_vitals_history($iopId, 20);
		$recentNotes = $this->get_recent_notes($iopId, 20);
		$recentProcedures = $this->get_recent_bedside_procedures($iopId, $patientNo, 20);
		$recentMedications = $this->get_recent_medication_administrations($iopId, 20);
		$recentIoEntries = $this->get_recent_intake_output_entries($iopId, 20);
		$clinicalContext = $this->get_opd_clinical_context($iopId, $patientNo);
		$ioSummary = $this->get_intake_output_summary($iopId, $recentIoEntries);
		$eventAggregator = new ClinicalEventAggregator();
		$payload = array(
			'status' => 'ok',
			'workspace_status' => 'stable_read_only',
			'patient' => $patient,
			'summary' => $clinical,
			'clinical_context' => $clinicalContext,
			'vitals_history' => array_slice($vitalsHistory, 0, 8),
			'recent_notes' => array_slice($recentNotes, 0, 5),
			'recent_procedures' => array_slice($recentProcedures, 0, 5),
			'pending_medications' => isset($clinical['pending_medications']) ? $clinical['pending_medications'] : array(),
			'recent_medication_administrations' => array_slice($recentMedications, 0, 5),
			'active_alerts' => isset($clinical['active_alerts']) ? $clinical['active_alerts'] : array(),
			'data_quality_flags' => isset($clinical['data_quality_flags']) ? $clinical['data_quality_flags'] : array(),
			'timeline' => $eventAggregator->buildPatientTimeline(array(
				'vitals' => $vitalsHistory,
				'notes' => $recentNotes,
				'procedures' => $recentProcedures,
				'medications' => $recentMedications,
				'intake_output' => $recentIoEntries,
				'clinical_context' => $clinicalContext
			), 30),
			'io_summary' => $ioSummary,
			'shift_tasks' => array(),
			'meta' => array('partial' => false, 'warnings' => array(), 'last_updated_at' => date('Y-m-d H:i:s'))
		);
		$workspaceComposer = new WorkspacePayloadComposer();
		$payload['composed_panels'] = $workspaceComposer->compose($payload);
		return $payload;
	}

	public function get_opd_clinical_context($iopId, $patientNo){
		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		if ($iopId === '') {
			return array(
				'complaints' => array(),
				'diagnoses' => array(),
				'prescriptions' => array(),
				'labs' => array(),
				'history' => array(),
				'detention' => array()
			);
		}
		return array(
			'complaints' => $this->get_readonly_complaints($iopId),
			'diagnoses' => $this->get_readonly_diagnoses($iopId),
			'prescriptions' => $this->get_readonly_prescriptions($iopId),
			'labs' => $this->get_readonly_lab_context($iopId),
			'history' => $this->get_readonly_history_context($iopId, $patientNo),
			'detention' => $this->get_detention_transition_context($iopId)
		);
	}

	public function get_readonly_complaints($iopId){
		if (!$this->table_exists('iop_complaints')) return array();
		$this->load->model('app/opd_model');
		$rows = $this->opd_model->patientComplain($iopId);
		$out = array();
		foreach ($rows as $row) {
			$out[] = array(
				'complaint_id' => isset($row->iop_comp_id) ? (string)$row->iop_comp_id : null,
				'complaint' => isset($row->complain_name) ? (string)$row->complain_name : null,
				'complaint_text' => isset($row->complain_text) ? (string)$row->complain_text : null,
				'duration' => isset($row->duration) ? (string)$row->duration : null,
				'severity' => isset($row->severity) ? (string)$row->severity : null,
				'onset' => isset($row->onset) ? (string)$row->onset : null,
				'remarks' => isset($row->remarks) ? (string)$row->remarks : null,
				'recorded_at' => isset($row->dDate) ? (string)$row->dDate : null,
				'recorded_by' => isset($row->recorded_by) ? (string)$row->recorded_by : null,
				'recorded_by_name' => isset($row->recorded_by_name) ? (string)$row->recorded_by_name : null
			);
		}
		return $out;
	}

	public function get_readonly_diagnoses($iopId){
		if (!$this->table_exists('iop_diagnosis')) return array();
		$this->load->model('app/opd_model');
		$rows = $this->opd_model->patientDiagnosis($iopId);
		$out = array();
		foreach ($rows as $row) {
			$out[] = array(
				'diagnosis_id' => isset($row->iop_diag_id) ? (string)$row->iop_diag_id : null,
				'diagnosis' => isset($row->diagnosis_name) ? (string)$row->diagnosis_name : null,
				'icd_code' => isset($row->icd_code) ? (string)$row->icd_code : null,
				'category' => isset($row->category) ? (string)$row->category : null,
				'remarks' => isset($row->remarks) ? (string)$row->remarks : null,
				'diagnosis_text' => isset($row->diagnosis_text) ? (string)$row->diagnosis_text : null,
				'recorded_at' => isset($row->dDate) ? (string)$row->dDate : null
			);
		}
		return $out;
	}

	public function get_readonly_prescriptions($iopId){
		if (!$this->table_exists('iop_medication')) return array();
		$this->load->model('app/opd_model');
		$rows = $this->opd_model->patientMedication($iopId);
		$out = array();
		foreach ($rows as $row) {
			$medName = isset($row->drug_name) && trim((string)$row->drug_name) !== '' ? (string)$row->drug_name : (isset($row->medicine_text) ? (string)$row->medicine_text : 'Medication');
			$out[] = array(
				'prescription_id' => isset($row->iop_med_id) ? (string)$row->iop_med_id : null,
				'medication' => $medName,
				'dosage' => isset($row->dosage) ? (string)$row->dosage : null,
				'frequency' => isset($row->frequency) ? (string)$row->frequency : null,
				'days' => isset($row->days) ? (string)$row->days : null,
				'total_qty' => isset($row->total_qty) ? (string)$row->total_qty : null,
				'instruction' => isset($row->instruction) ? (string)$row->instruction : null,
				'advice' => isset($row->advice) ? (string)$row->advice : null,
				'dispensing_status' => isset($row->dispensing_status) ? (string)$row->dispensing_status : null,
				'nhis_drug_code' => isset($row->nhis_drug_code) ? (string)$row->nhis_drug_code : null,
				'prescriber' => isset($row->name) ? (string)$row->name : null,
				'recorded_at' => isset($row->dDate) ? (string)$row->dDate : null
			);
		}
		return $out;
	}

	public function get_readonly_lab_context($iopId){
		if (!$this->table_exists('iop_laboratory')) return array();
		$this->load->model('app/opd_model');
		$rows = $this->opd_model->patient_lab($iopId);
		$out = array();
		foreach ($rows as $row) {
			$resultText = isset($row->result) ? trim((string)$row->result) : '';
			$findingsText = isset($row->findings) ? trim((string)$row->findings) : '';
			$status = ($resultText !== '' || $findingsText !== '') ? 'completed' : 'pending';
			$out[] = array(
				'lab_id' => isset($row->io_lab_id) ? (string)$row->io_lab_id : null,
				'category_id' => isset($row->category_id) ? (string)$row->category_id : null,
				'test_name' => isset($row->particular_name) ? (string)$row->particular_name : null,
				'group_name' => isset($row->group_name) ? (string)$row->group_name : null,
				'status' => $status,
				'findings' => $findingsText !== '' ? $findingsText : null,
				'result' => $resultText !== '' ? $resultText : null,
				'lab_result_upload' => isset($row->lab_result_upload) ? (string)$row->lab_result_upload : null,
				'clinical_question' => isset($row->clinical_question) ? (string)$row->clinical_question : null,
				'doctor' => isset($row->doctor) ? (string)$row->doctor : null,
				'recorded_at' => isset($row->dDateTime) ? (string)$row->dDateTime : null
			);
		}
		return $out;
	}

	public function get_readonly_history_context($iopId, $patientNo){
		$history = array(
			'allergies' => '',
			'warnings' => '',
			'chronic_conditions' => '',
			'past_medical_history' => '',
			'drug_history' => ''
		);
		if ($this->table_exists('patient_details_iop')) {
			$select = array('IO_ID');
			foreach (array('allergies', 'warnings', 'past_medical_history', 'drug_history') as $col) {
				if ($this->field_exists($col, 'patient_details_iop')) {
					$select[] = $col;
				}
			}
			$this->db->select(implode(',', $select), false);
			$this->db->where('IO_ID', $iopId);
			$this->db->where('InActive', 0);
			$this->db->limit(1);
			$row = $this->db->get('patient_details_iop')->row();
			if ($row) {
				$history['allergies'] = isset($row->allergies) ? trim((string)$row->allergies) : '';
				$history['warnings'] = isset($row->warnings) ? trim((string)$row->warnings) : '';
				$history['past_medical_history'] = isset($row->past_medical_history) ? trim((string)$row->past_medical_history) : '';
				$history['drug_history'] = isset($row->drug_history) ? trim((string)$row->drug_history) : '';
			}
		}
		if (trim((string)$patientNo) !== '') {
			$this->load->model('app/clinical_history_model');
			$patient = $this->clinical_history_model->get_patient_history($patientNo);
			if ($patient && isset($patient->chronic_conditions)) {
				$history['chronic_conditions'] = trim((string)$patient->chronic_conditions);
			}
		}
		return $history;
	}

	public function get_detention_transition_context($iopId){
		$ctx = array(
			'encounter_id' => (string)$iopId,
			'is_detained' => false,
			'detention_start_at' => null,
			'converted_to_admission' => false,
			'converted_to_admission_at' => null,
			'converted_ipd_iop_id' => null,
			'source_opd_iop_id' => null
		);
		if (!$this->table_exists('patient_details_iop') || trim((string)$iopId) === '') {
			return $ctx;
		}
		$select = array('IO_ID');
		foreach (array('patient_type', 'detention_start_at', 'converted_to_admission_at', 'converted_ipd_iop_id', 'source_opd_iop_id') as $col) {
			if ($this->field_exists($col, 'patient_details_iop')) {
				$select[] = $col;
			}
		}
		$this->db->select(implode(',', $select), false);
		$this->db->where('IO_ID', $iopId);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$row = $this->db->get('patient_details_iop')->row();
		if (!$row) return $ctx;
		$detentionStart = isset($row->detention_start_at) ? trim((string)$row->detention_start_at) : '';
		$convertedAt = isset($row->converted_to_admission_at) ? trim((string)$row->converted_to_admission_at) : '';
		$ctx['detention_start_at'] = $detentionStart !== '' ? $detentionStart : null;
		$ctx['is_detained'] = $detentionStart !== '';
		$ctx['converted_to_admission_at'] = $convertedAt !== '' ? $convertedAt : null;
		$ctx['converted_to_admission'] = $convertedAt !== '' || (isset($row->converted_ipd_iop_id) && trim((string)$row->converted_ipd_iop_id) !== '');
		$ctx['converted_ipd_iop_id'] = isset($row->converted_ipd_iop_id) && trim((string)$row->converted_ipd_iop_id) !== '' ? (string)$row->converted_ipd_iop_id : null;
		$ctx['source_opd_iop_id'] = isset($row->source_opd_iop_id) && trim((string)$row->source_opd_iop_id) !== '' ? (string)$row->source_opd_iop_id : null;
		return $ctx;
	}

	public function get_vitals_history($iopId, $limit = 8){
		$iopId = trim((string)$iopId);
		if ($iopId === '' || !$this->table_exists('iop_vital_parameters')) {
			return array();
		}
		$hasExtras = $this->table_exists('iop_vital_parameters_extra')
			&& $this->field_exists('vital_id', 'iop_vital_parameters')
			&& $this->field_exists('vital_id', 'iop_vital_parameters_extra');
		if ($hasExtras) {
			$this->db->select('V.*, X.spo2, X.blood_sugar, X.pain_score', false);
			$this->db->from('iop_vital_parameters V');
			$this->db->join('iop_vital_parameters_extra X', 'X.vital_id = V.vital_id AND X.InActive = 0', 'left');
			$this->db->where(array('V.iop_id' => $iopId, 'V.InActive' => 0));
			$this->db->order_by('V.dDateTime', 'DESC');
			$this->db->order_by('V.vital_id', 'DESC');
		} else {
			$this->db->from('iop_vital_parameters');
			$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
			$this->db->order_by('dDateTime', 'DESC');
			if ($this->field_exists('vital_id', 'iop_vital_parameters')) {
				$this->db->order_by('vital_id', 'DESC');
			}
		}
		$this->db->limit((int)$limit);
		$query = $this->db->get();
		$rows = $query ? $query->result() : array();
		$result = array();
		foreach ($rows as $row) {
			$result[] = array(
				'vital_id' => isset($row->vital_id) ? (string)$row->vital_id : null,
				'recorded_at' => isset($row->dDateTime) ? (string)$row->dDateTime : null,
				'bp' => isset($row->bp) ? (string)$row->bp : null,
				'temperature' => isset($row->temperature) ? (string)$row->temperature : null,
				'pulse' => isset($row->pulse_rate) ? (string)$row->pulse_rate : null,
				'respiratory_rate' => isset($row->respiration) ? (string)$row->respiration : null,
				'weight' => isset($row->weight) ? (string)$row->weight : null,
				'height' => isset($row->height) ? (string)$row->height : null,
				'spo2' => isset($row->spo2) ? (string)$row->spo2 : null,
				'blood_sugar' => isset($row->blood_sugar) ? (string)$row->blood_sugar : null,
				'pain_score' => isset($row->pain_score) ? (string)$row->pain_score : null
			);
		}
		return $result;
	}

	private function build_patient_card($row){
		$iopId = isset($row->IO_ID) ? (string)$row->IO_ID : '';
		$patientNo = isset($row->patient_no) ? (string)$row->patient_no : '';
		$latestVitals = $this->get_latest_vitals($iopId);
		$medications = $this->get_due_medications($iopId, $patientNo);
		$dueMedicationCount = $this->count_due_medications($medications);
		$procedures = $this->get_recent_bedside_procedures($iopId, $patientNo);
		$lastNote = $this->get_last_note_time($iopId);
		$dataQualityFlags = $this->derive_data_quality_flags($row, $lastNote);
		$alerts = $this->derive_alerts($row, $latestVitals, $medications, $dataQualityFlags);
		$priority = $this->derive_priority($latestVitals, $medications, $procedures, $alerts, $dataQualityFlags, $lastNote);
		$nameParts = array();
		if (isset($row->title_name) && trim((string)$row->title_name) !== '') $nameParts[] = trim((string)$row->title_name);
		if (isset($row->firstname) && trim((string)$row->firstname) !== '') $nameParts[] = trim((string)$row->firstname);
		if (isset($row->middlename) && trim((string)$row->middlename) !== '') $nameParts[] = trim((string)$row->middlename);
		if (isset($row->lastname) && trim((string)$row->lastname) !== '') $nameParts[] = trim((string)$row->lastname);
		$name = trim(implode(' ', $nameParts));
		if ($name === '') $name = $patientNo;

		return array(
			'patient_id' => $iopId,
			'patient_no' => $patientNo,
			'encounter_id' => $iopId,
			'name' => $name,
			'age' => isset($row->age) ? (string)$row->age : null,
			'sex' => isset($row->sex_name) ? (string)$row->sex_name : null,
			'ward' => array(
				'ward_id' => isset($row->room_master_id) ? (string)$row->room_master_id : null,
				'ward_name' => isset($row->room_name) ? (string)$row->room_name : null
			),
			'bed' => array(
				'bed_id' => isset($row->room_bed_id) ? (string)$row->room_bed_id : null,
				'bed_name' => isset($row->bed_name) ? (string)$row->bed_name : null
			),
			'latest_vitals' => $latestVitals,
			'status_flags' => array(
				'priority_state' => $priority['band'],
				'vitals_status' => $latestVitals['status'],
				'medication_due' => $dueMedicationCount > 0,
				'procedure_pending' => false,
				'has_alert' => count($alerts) > 0
			),
			'counts' => array(
				'pending_medications' => $dueMedicationCount,
				'pending_procedures' => 0,
				'active_alerts' => count($alerts)
			),
			'priority_score' => $priority['score'],
			'priority_band' => $priority['band'],
			'priority_reasons' => $priority['reasons'],
			'data_quality_flags' => $dataQualityFlags,
			'last_note_time' => $lastNote,
			'medications' => $medications,
			'procedures' => $procedures,
			'alerts' => $alerts,
			'department_name' => isset($row->dept_name) ? (string)$row->dept_name : null,
			'admitted_at' => trim((string)$row->date_visit . ' ' . (string)$row->time_visit)
		);
	}

	private function get_latest_vitals($iopId){
		$default = array('recorded_at' => null, 'temperature' => null, 'pulse' => null, 'bp' => null, 'respiratory_rate' => null, 'spo2' => null, 'status' => 'missing', 'age_hours' => null, 'critical_reasons' => array(), 'completeness_count' => 0);
		if (!$this->table_exists('iop_vital_parameters') || trim((string)$iopId) === '') {
			return $default;
		}
		$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
		$this->db->order_by('dDateTime', 'DESC');
		if ($this->field_exists('vital_id', 'iop_vital_parameters')) $this->db->order_by('vital_id', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get('iop_vital_parameters');
		$row = $query ? $query->row() : null;
		if (!$row) {
			return $default;
		}
		$recordedAt = isset($row->dDateTime) ? (string)$row->dDateTime : null;
		$vitals = array(
			'recorded_at' => $recordedAt,
			'temperature' => isset($row->temperature) ? (string)$row->temperature : null,
			'pulse' => isset($row->pulse_rate) ? (string)$row->pulse_rate : null,
			'bp' => isset($row->bp) ? (string)$row->bp : null,
			'respiratory_rate' => isset($row->respiration) ? (string)$row->respiration : null,
			'spo2' => isset($row->spo2) ? (string)$row->spo2 : null,
			'status' => 'normal',
			'age_hours' => $this->hours_since($recordedAt),
			'critical_reasons' => array(),
			'completeness_count' => 0
		);
		$vitals['status'] = $this->derive_vitals_status($vitals);
		$vitals['critical_reasons'] = $this->critical_vitals_reasons($vitals);
		$vitals['completeness_count'] = $this->vitals_completeness_count($vitals);
		return $vitals;
	}

	private function derive_vitals_status($vitals){
		if (!isset($vitals['recorded_at']) || !$vitals['recorded_at']) {
			return 'missing';
		}
		$criticalReasons = $this->critical_vitals_reasons($vitals);
		if (count($criticalReasons) > 0) {
			return 'critical';
		}
		if ($this->vitals_completeness_count($vitals) < 2) {
			return 'incomplete';
		}
		if (isset($vitals['age_hours']) && $vitals['age_hours'] !== null && $vitals['age_hours'] > self::VITALS_OVERDUE_HOURS) {
			return 'overdue';
		}
		return 'normal';
	}

	private function get_due_medications($iopId, $patientNo){
		if (!$this->table_exists('iop_medication') || trim((string)$iopId) === '') {
			return array();
		}
		$administrationAvailable = $this->table_exists('iop_medication_administration') && $this->field_exists('dDateTime', 'iop_medication_administration');
		$this->db->select('A.iop_med_id, A.iop_id, A.dDate, A.total_qty, A.instruction, A.dosage, A.frequency, A.medicine_text, B.drug_name', false);
		$this->db->from('iop_medication A');
		$this->db->join('medicine_drug_name B', 'B.drug_id = A.medicine_id', 'left outer');
		$this->db->where(array('A.iop_id' => $iopId, 'A.InActive' => 0));
		$this->db->order_by('A.iop_med_id', 'DESC');
		$this->db->limit(10);
		$query = $this->db->get();
		$rows = $query ? $query->result() : array();
		$result = array();
		foreach ($rows as $row) {
			$status = 'unknown';
			if ($administrationAvailable) {
				$status = $this->medication_administered_today(isset($row->iop_med_id) ? $row->iop_med_id : 0) ? 'administered_today' : 'due';
			}
			if ($status === 'administered_today') {
				continue;
			}
			$name = isset($row->drug_name) && trim((string)$row->drug_name) !== '' ? (string)$row->drug_name : (isset($row->medicine_text) ? (string)$row->medicine_text : 'Medication');
			$result[] = array(
				'patient_id' => (string)$iopId,
				'patient_name' => '',
				'bed_name' => null,
				'medication_name' => $name,
				'dose' => isset($row->dosage) && trim((string)$row->dosage) !== '' ? (string)$row->dosage : (isset($row->total_qty) ? (string)$row->total_qty : null),
				'due_time' => isset($row->dDate) ? (string)$row->dDate : null,
				'status' => $status,
				'iop_med_id' => isset($row->iop_med_id) ? (string)$row->iop_med_id : null,
				'patient_no' => (string)$patientNo
			);
		}
		return $result;
	}

	private function medication_administered_today($iopMedId){
		if (!$this->table_exists('iop_medication_administration') || !$this->field_exists('dDateTime', 'iop_medication_administration') || (int)$iopMedId <= 0) {
			return false;
		}
		$this->db->where('iop_med_id', (int)$iopMedId);
		$this->db->where('InActive', 0);
		$this->db->where('DATE(dDateTime) = ' . $this->db->escape(date('Y-m-d')), null, false);
		$this->db->limit(1);
		$query = $this->db->get('iop_medication_administration');
		return ($query && $query->num_rows() > 0);
	}

	private function get_recent_medication_administrations($iopId, $limit = 20){
		if (!$this->table_exists('iop_medication_administration') || trim((string)$iopId) === '') {
			return array();
		}
		$hasMedication = $this->table_exists('iop_medication');
		$hasDrugMaster = $this->table_exists('medicine_drug_name');
		$select = 'A.admin_id, A.iop_med_id, A.status, A.dose_given, A.notes, A.dDateTime, A.cPreparedBy';
		if ($hasMedication) {
			$select .= ', M.medicine_text';
		}
		if ($hasMedication && $hasDrugMaster) {
			$select .= ', B.drug_name';
		}
		$this->db->select($select, false);
		$this->db->from('iop_medication_administration A');
		if ($hasMedication) {
			$this->db->join('iop_medication M', 'M.iop_med_id = A.iop_med_id', 'left outer');
		}
		if ($hasMedication && $hasDrugMaster) {
			$this->db->join('medicine_drug_name B', 'B.drug_id = M.medicine_id', 'left outer');
		}
		$this->db->where(array('A.iop_id' => $iopId, 'A.InActive' => 0));
		$this->db->order_by('A.dDateTime', 'DESC');
		$this->db->limit((int)$limit);
		$query = $this->db->get();
		$rows = $query ? $query->result() : array();
		$result = array();
		foreach ($rows as $row) {
			$name = isset($row->drug_name) && trim((string)$row->drug_name) !== '' ? (string)$row->drug_name : (isset($row->medicine_text) ? (string)$row->medicine_text : 'Medication');
			$result[] = array(
				'administration_id' => isset($row->admin_id) ? (string)$row->admin_id : null,
				'iop_med_id' => isset($row->iop_med_id) ? (string)$row->iop_med_id : null,
				'medication_name' => $name,
				'status' => isset($row->status) ? (string)$row->status : null,
				'dose_given' => isset($row->dose_given) ? (string)$row->dose_given : null,
				'notes' => isset($row->notes) ? (string)$row->notes : null,
				'recorded_at' => isset($row->dDateTime) ? (string)$row->dDateTime : null,
				'actor' => isset($row->cPreparedBy) ? (string)$row->cPreparedBy : null
			);
		}
		return $result;
	}

	private function get_recent_intake_output_entries($iopId, $limit = 20){
		$result = array();
		$iopId = trim((string)$iopId);
		if ($iopId === '') {
			return $result;
		}
		if ($this->table_exists('iop_intake_record')) {
			$this->db->from('iop_intake_record');
			$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
			$this->db->order_by('dDateTime', 'DESC');
			if ($this->field_exists('intake_id', 'iop_intake_record')) $this->db->order_by('intake_id', 'DESC');
			$this->db->limit((int)$limit);
			$query = $this->db->get();
			$rows = $query ? $query->result() : array();
			foreach ($rows as $row) {
				$iv = isset($row->IV_fluids) ? (int)$row->IV_fluids : 0;
				$oral = isset($row->oral) ? (int)$row->oral : 0;
				$bloodLoss = isset($row->blood_loss) ? (int)$row->blood_loss : 0;
				$summary = 'IV ' . $iv . ' · Oral ' . $oral;
				if ($bloodLoss > 0) $summary .= ' · Blood loss ' . $bloodLoss;
				$result[] = array(
					'io_type' => 'intake',
					'record_id' => isset($row->intake_id) ? (string)$row->intake_id : null,
					'table' => 'iop_intake_record',
					'recorded_at' => isset($row->dDateTime) ? (string)$row->dDateTime : null,
					'summary' => $summary,
					'amount' => $iv + $oral,
					'actor' => isset($row->cPreparedBy) ? (string)$row->cPreparedBy : null
				);
			}
		}
		if ($this->table_exists('iop_output_record')) {
			$this->db->from('iop_output_record');
			$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
			$this->db->order_by('dDateTime', 'DESC');
			if ($this->field_exists('output_id', 'iop_output_record')) $this->db->order_by('output_id', 'DESC');
			$this->db->limit((int)$limit);
			$query = $this->db->get();
			$rows = $query ? $query->result() : array();
			foreach ($rows as $row) {
				$urine = isset($row->urine) ? (int)$row->urine : 0;
				$summary = 'Urine ' . $urine;
				if (isset($row->feaces) && trim((string)$row->feaces) !== '') $summary .= ' · Feaces ' . (string)$row->feaces;
				if (isset($row->respitation) && trim((string)$row->respitation) !== '') $summary .= ' · Respiration ' . (string)$row->respitation;
				if (isset($row->skin) && trim((string)$row->skin) !== '') $summary .= ' · Skin ' . (string)$row->skin;
				$result[] = array(
					'io_type' => 'output',
					'record_id' => isset($row->output_id) ? (string)$row->output_id : null,
					'table' => 'iop_output_record',
					'recorded_at' => isset($row->dDateTime) ? (string)$row->dDateTime : null,
					'summary' => $summary,
					'amount' => $urine,
					'actor' => isset($row->cPreparedBy) ? (string)$row->cPreparedBy : null
				);
			}
		}
		usort($result, array($this, 'sort_recent_workspace_rows'));
		return array_slice($result, 0, (int)$limit);
	}

	private function get_intake_output_summary($iopId, array $recentEntries){
		$intakeTotal = 0;
		$outputTotal = 0;
		foreach ($recentEntries as $entry) {
			if (isset($entry['io_type']) && $entry['io_type'] === 'output') {
				$outputTotal += isset($entry['amount']) ? (int)$entry['amount'] : 0;
			} else {
				$intakeTotal += isset($entry['amount']) ? (int)$entry['amount'] : 0;
			}
		}
		return array(
			'status' => count($recentEntries) > 0 ? 'available' : 'empty',
			'intake_total' => $intakeTotal,
			'output_total' => $outputTotal,
			'balance' => $intakeTotal - $outputTotal,
			'recent_entries' => array_slice($recentEntries, 0, 5),
			'encounter_id' => (string)$iopId
		);
	}

	public function sort_recent_workspace_rows($a, $b){
		$ta = isset($a['recorded_at']) ? strtotime((string)$a['recorded_at']) : 0;
		$tb = isset($b['recorded_at']) ? strtotime((string)$b['recorded_at']) : 0;
		if ($ta == $tb) return 0;
		return ($ta > $tb) ? -1 : 1;
	}

	private function get_recent_bedside_procedures($iopId, $patientNo, $limit = 5){
		if (!$this->table_exists('iop_bed_side_procedure') || trim((string)$iopId) === '') {
			return array();
		}
		$this->db->select('A.bed_pro_id, A.iop_id, A.dDateTime, A.qty, A.notes, A.cPreparedBy, B.particular_name', false);
		$this->db->from('iop_bed_side_procedure A');
		$this->db->join('bill_particular B', 'B.particular_id = A.cItem_id', 'left outer');
		$this->db->where(array('A.iop_id' => $iopId, 'A.InActive' => 0));
		$this->db->order_by('A.dDateTime', 'DESC');
		$this->db->limit((int)$limit);
		$query = $this->db->get();
		$rows = $query ? $query->result() : array();
		$result = array();
		$enricher = new BedsideProcedureClinicalEnricher();
		foreach ($rows as $row) {
			$procedureName = isset($row->particular_name) ? (string)$row->particular_name : 'Bedside procedure';
			$notes = isset($row->notes) ? (string)$row->notes : '';
			$actor = isset($row->cPreparedBy) ? (string)$row->cPreparedBy : null;
			$recordedAt = isset($row->dDateTime) ? (string)$row->dDateTime : null;
			$enriched = $enricher->enrich($procedureName, $notes, $actor, $recordedAt);
			$result[] = array_merge(array(
				'procedure_id' => isset($row->bed_pro_id) ? (string)$row->bed_pro_id : null,
				'patient_id' => (string)$iopId,
				'patient_name' => '',
				'bed_name' => null,
				'procedure_name' => $procedureName,
				'status' => 'recent',
				'recorded_at' => $recordedAt,
				'actor' => $actor,
				'notes' => $notes,
				'patient_no' => (string)$patientNo
			), $enriched);
		}
		return $result;
	}

	private function get_last_note_time($iopId){
		if (!$this->table_exists('iop_nurse_notes') || trim((string)$iopId) === '') {
			return null;
		}
		$this->db->select('dDateTime');
		$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
		$this->db->order_by('dDateTime', 'DESC');
		$this->db->limit(1);
		$query = $this->db->get('iop_nurse_notes');
		$row = $query ? $query->row() : null;
		return $row && isset($row->dDateTime) ? (string)$row->dDateTime : null;
	}

	private function get_recent_notes($iopId, $limit = 3){
		if (!$this->table_exists('iop_nurse_notes') || trim((string)$iopId) === '') {
			return array();
		}
		$this->db->select('nurse_notes_id, dDateTime, focus, notes');
		$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
		$this->db->order_by('dDateTime', 'DESC');
		$this->db->limit((int)$limit);
		$query = $this->db->get('iop_nurse_notes');
		$rows = $query ? $query->result() : array();
		$result = array();
		foreach ($rows as $row) {
			$result[] = array(
				'note_id' => isset($row->nurse_notes_id) ? (string)$row->nurse_notes_id : null,
				'recorded_at' => isset($row->dDateTime) ? (string)$row->dDateTime : null,
				'focus' => isset($row->focus) ? (string)$row->focus : null,
				'note' => isset($row->notes) ? (string)$row->notes : null
			);
		}
		return $result;
	}

	private function derive_alerts($row, $latestVitals, $medications, $dataQualityFlags){
		$alerts = array();
		$dueMedicationCount = $this->count_due_medications($medications);
		if (isset($latestVitals['status']) && $latestVitals['status'] === 'critical') {
			$alerts[] = array(
				'alert_id' => 'vitals_' . (isset($row->IO_ID) ? (string)$row->IO_ID : ''),
				'patient_id' => isset($row->IO_ID) ? (string)$row->IO_ID : '',
				'patient_no' => isset($row->patient_no) ? (string)$row->patient_no : '',
				'patient_name' => trim((isset($row->firstname) ? (string)$row->firstname : '') . ' ' . (isset($row->lastname) ? (string)$row->lastname : '')),
				'bed_name' => isset($row->bed_name) ? (string)$row->bed_name : null,
				'alert_type' => 'critical_vitals',
				'severity' => 'critical',
				'label' => 'Critical vitals need review',
				'source' => 'latest_vitals',
				'created_at' => isset($latestVitals['recorded_at']) ? $latestVitals['recorded_at'] : null
			);
		}
		if (isset($latestVitals['status']) && in_array($latestVitals['status'], array('missing', 'overdue', 'incomplete'))) {
			$alerts[] = array(
				'alert_id' => 'vitals_' . $latestVitals['status'] . '_' . (isset($row->IO_ID) ? (string)$row->IO_ID : ''),
				'patient_id' => isset($row->IO_ID) ? (string)$row->IO_ID : '',
				'patient_no' => isset($row->patient_no) ? (string)$row->patient_no : '',
				'patient_name' => trim((isset($row->firstname) ? (string)$row->firstname : '') . ' ' . (isset($row->lastname) ? (string)$row->lastname : '')),
				'bed_name' => isset($row->bed_name) ? (string)$row->bed_name : null,
				'alert_type' => $latestVitals['status'] . '_vitals',
				'severity' => 'warning',
				'label' => ucfirst($latestVitals['status']) . ' vitals need review',
				'source' => 'latest_vitals',
				'created_at' => isset($latestVitals['recorded_at']) ? $latestVitals['recorded_at'] : null
			);
		}
		if ($dueMedicationCount > 0) {
			$alerts[] = array(
				'alert_id' => 'pending_medication_' . (isset($row->IO_ID) ? (string)$row->IO_ID : ''),
				'patient_id' => isset($row->IO_ID) ? (string)$row->IO_ID : '',
				'patient_no' => isset($row->patient_no) ? (string)$row->patient_no : '',
				'patient_name' => trim((isset($row->firstname) ? (string)$row->firstname : '') . ' ' . (isset($row->lastname) ? (string)$row->lastname : '')),
				'bed_name' => isset($row->bed_name) ? (string)$row->bed_name : null,
				'alert_type' => 'pending_medication',
				'severity' => 'warning',
				'label' => $dueMedicationCount . ' active medication order(s) lack same-day administration record',
				'source' => 'iop_medication',
				'created_at' => date('Y-m-d H:i:s')
			);
		}
		if (count($dataQualityFlags) > 0) {
			$alerts[] = array(
				'alert_id' => 'data_quality_' . (isset($row->IO_ID) ? (string)$row->IO_ID : ''),
				'patient_id' => isset($row->IO_ID) ? (string)$row->IO_ID : '',
				'patient_no' => isset($row->patient_no) ? (string)$row->patient_no : '',
				'patient_name' => trim((isset($row->firstname) ? (string)$row->firstname : '') . ' ' . (isset($row->lastname) ? (string)$row->lastname : '')),
				'bed_name' => isset($row->bed_name) ? (string)$row->bed_name : null,
				'alert_type' => 'data_quality',
				'severity' => 'info',
				'label' => implode(', ', $dataQualityFlags),
				'source' => 'census_context',
				'created_at' => date('Y-m-d H:i:s')
			);
		}
		return $alerts;
	}

	private function derive_priority($latestVitals, $medications, $procedures, $alerts, $dataQualityFlags, $lastNote){
		$score = 0;
		$reasons = array();
		$vitalsStatus = isset($latestVitals['status']) ? $latestVitals['status'] : 'missing';
		if ($vitalsStatus === 'critical') {
			$score += 100;
			$reasons[] = 'critical_vitals';
		} elseif ($vitalsStatus === 'missing') {
			$score += 40;
			$reasons[] = 'missing_vitals';
		} elseif ($vitalsStatus === 'overdue') {
			$score += 35;
			$reasons[] = 'overdue_vitals';
		} elseif ($vitalsStatus === 'incomplete') {
			$score += 20;
			$reasons[] = 'incomplete_vitals';
		}
		$medicationScore = $this->count_due_medications($medications) * 15;
		if ($medicationScore > 45) $medicationScore = 45;
		if ($medicationScore > 0) {
			$score += $medicationScore;
			$reasons[] = 'pending_medication';
		}
		if (count($dataQualityFlags) > 0) {
			$score += 10;
			$reasons[] = 'data_quality';
		}
		if (!$lastNote || $this->hours_since($lastNote) > self::NO_RECENT_NOTE_HOURS) {
			$score += 10;
			$reasons[] = 'no_recent_nurse_note';
		}
		$band = 'normal';
		if ($score >= 100) {
			$band = 'critical';
		} elseif ($score >= 60) {
			$band = 'high';
		} elseif ($score >= 30) {
			$band = 'medium';
		} elseif ($score > 0) {
			$band = 'low';
		}
		return array('score' => $score, 'band' => $band, 'reasons' => $reasons);
	}

	public function sort_patients_by_priority($a, $b){
		$sa = isset($a['priority_score']) ? (int)$a['priority_score'] : 0;
		$sb = isset($b['priority_score']) ? (int)$b['priority_score'] : 0;
		if ($sa == $sb) return 0;
		return ($sa > $sb) ? -1 : 1;
	}

	private function refresh_contract($generatedAt, $status){
		return array(
			'interval_seconds' => self::REFRESH_INTERVAL_SECONDS,
			'stale_after_seconds' => self::STALE_AFTER_SECONDS,
			'generated_at' => $generatedAt,
			'status' => $status
		);
	}

	private function build_escalation_groups($patients){
		$groups = array(
			'critical' => array('label' => 'CRITICAL', 'count' => 0, 'patients' => array()),
			'high' => array('label' => 'HIGH', 'count' => 0, 'patients' => array()),
			'watch' => array('label' => 'WATCH', 'count' => 0, 'patients' => array()),
			'normal' => array('label' => 'NORMAL', 'count' => 0, 'patients' => array())
		);
		foreach ($patients as $patient) {
			$band = $this->escalation_band(isset($patient['priority_band']) ? $patient['priority_band'] : 'normal');
			$groups[$band]['patients'][] = $patient;
			$groups[$band]['count']++;
		}
		return $groups;
	}

	private function escalation_band($priorityBand){
		if ($priorityBand === 'critical') return 'critical';
		if ($priorityBand === 'high') return 'high';
		if ($priorityBand === 'medium' || $priorityBand === 'low') return 'watch';
		return 'normal';
	}

	private function build_handover_snapshot($patients, $pendingMedications, $unknownMedications, $warnings, $shiftContext, $generatedAt){
		$patientsNeedingReview = array();
		$criticalVitals = array();
		$recentNotes = array();
		$recentProcedures = array();
		$dataQualityWarnings = array();
		foreach ($patients as $patient) {
			$band = $this->escalation_band(isset($patient['priority_band']) ? $patient['priority_band'] : 'normal');
			$vitalsStatus = isset($patient['latest_vitals']['status']) ? $patient['latest_vitals']['status'] : 'missing';
			$needsReview = in_array($band, array('critical', 'high')) || in_array($vitalsStatus, array('missing', 'overdue', 'incomplete', 'critical')) || (isset($patient['counts']['pending_medications']) && (int)$patient['counts']['pending_medications'] > 0) || (isset($patient['data_quality_flags']) && count($patient['data_quality_flags']) > 0) || !$patient['last_note_time'] || $this->hours_since($patient['last_note_time']) > self::NO_RECENT_NOTE_HOURS;
			if ($needsReview) {
				$patientsNeedingReview[] = $this->handover_patient_summary($patient, $band);
			}
			if ($vitalsStatus === 'critical') {
				$criticalVitals[] = $this->handover_patient_summary($patient, $band);
			}
			if (isset($patient['data_quality_flags']) && count($patient['data_quality_flags']) > 0) {
				$dataQualityWarnings[] = array(
					'patient_id' => $patient['patient_id'],
					'patient_no' => $patient['patient_no'],
					'name' => $patient['name'],
					'flags' => $patient['data_quality_flags']
				);
			}
			$notes = $this->get_recent_notes($patient['encounter_id'], 1);
			foreach ($notes as $note) {
				$note['patient_id'] = $patient['patient_id'];
				$note['patient_no'] = $patient['patient_no'];
				$note['patient_name'] = $patient['name'];
				$recentNotes[] = $note;
			}
			$procedures = isset($patient['procedures']) && is_array($patient['procedures']) ? $patient['procedures'] : array();
			foreach ($procedures as $procedure) {
				$procedure['patient_name'] = $patient['name'];
				$recentProcedures[] = $procedure;
			}
		}
		return array(
			'shift' => $shiftContext,
			'generated_at' => $generatedAt,
			'patients_needing_review' => $patientsNeedingReview,
			'critical_vitals' => $criticalVitals,
			'pending_medications' => $pendingMedications,
			'unknown_medications' => $unknownMedications,
			'recent_notes' => $recentNotes,
			'recent_procedures' => $recentProcedures,
			'data_quality_warnings' => $dataQualityWarnings,
			'partial_data_warnings' => $warnings
		);
	}

	private function handover_patient_summary($patient, $band){
		return array(
			'patient_id' => isset($patient['patient_id']) ? $patient['patient_id'] : '',
			'patient_no' => isset($patient['patient_no']) ? $patient['patient_no'] : '',
			'name' => isset($patient['name']) ? $patient['name'] : '',
			'bed_name' => isset($patient['bed']['bed_name']) ? $patient['bed']['bed_name'] : null,
			'escalation_band' => strtoupper($band),
			'priority_score' => isset($patient['priority_score']) ? (int)$patient['priority_score'] : 0,
			'priority_reasons' => isset($patient['priority_reasons']) ? $patient['priority_reasons'] : array(),
			'vitals_status' => isset($patient['latest_vitals']['status']) ? $patient['latest_vitals']['status'] : 'missing',
			'pending_medications' => isset($patient['counts']['pending_medications']) ? (int)$patient['counts']['pending_medications'] : 0,
			'data_quality_flags' => isset($patient['data_quality_flags']) ? $patient['data_quality_flags'] : array()
		);
	}

	private function get_source_warnings(){
		$warnings = array();
		$optionalTables = array(
			'iop_vital_parameters',
			'iop_medication',
			'iop_medication_administration',
			'iop_bed_side_procedure',
			'iop_nurse_notes',
			'room_beds',
			'room_master'
		);
		foreach ($optionalTables as $table) {
			if (!$this->table_exists($table)) {
				$warnings[] = $table . ' unavailable';
			}
		}
		return $warnings;
	}

	private function derive_data_quality_flags($row, $lastNote){
		$flags = array();
		if (!isset($row->bed_name) || trim((string)$row->bed_name) === '') {
			$flags[] = 'missing_bed';
		}
		if (!isset($row->room_name) || trim((string)$row->room_name) === '') {
			$flags[] = 'missing_ward';
		}
		$hasName = (isset($row->firstname) && trim((string)$row->firstname) !== '') || (isset($row->lastname) && trim((string)$row->lastname) !== '');
		if (!$hasName) {
			$flags[] = 'partial_identity';
		}
		if (isset($row->date_visit) && trim((string)$row->date_visit) !== '') {
			$admissionAge = $this->days_since((string)$row->date_visit);
			if ($admissionAge !== null && $admissionAge > self::STALE_ADMISSION_DAYS) {
				$flags[] = 'stale_admission';
			}
		}
		return $flags;
	}

	private function count_due_medications($medications){
		$count = 0;
		foreach ($medications as $medication) {
			if (isset($medication['status']) && $medication['status'] === 'due') {
				$count++;
			}
		}
		return $count;
	}

	private function vitals_completeness_count($vitals){
		$count = 0;
		if (isset($vitals['bp']) && trim((string)$vitals['bp']) !== '') $count++;
		if (isset($vitals['temperature']) && trim((string)$vitals['temperature']) !== '') $count++;
		if (isset($vitals['pulse']) && trim((string)$vitals['pulse']) !== '') $count++;
		if (isset($vitals['respiratory_rate']) && trim((string)$vitals['respiratory_rate']) !== '') $count++;
		return $count;
	}

	private function critical_vitals_reasons($vitals){
		$reasons = array();
		$temp = isset($vitals['temperature']) ? (float)$vitals['temperature'] : 0;
		$pulse = isset($vitals['pulse']) ? (float)$vitals['pulse'] : 0;
		$resp = isset($vitals['respiratory_rate']) ? (float)$vitals['respiratory_rate'] : 0;
		$spo2 = isset($vitals['spo2']) ? (float)$vitals['spo2'] : 0;
		if ($temp > 0 && ($temp >= 39 || $temp <= 35)) $reasons[] = 'temperature';
		if ($pulse > 0 && ($pulse >= 130 || $pulse <= 40)) $reasons[] = 'pulse';
		if ($resp > 0 && $resp >= 30) $reasons[] = 'respiratory_rate';
		if ($spo2 > 0 && $spo2 < 90) $reasons[] = 'spo2';
		$bp = $this->parse_bp(isset($vitals['bp']) ? $vitals['bp'] : '');
		if ($bp) {
			if ($bp['systolic'] >= 180 || $bp['systolic'] < 90) $reasons[] = 'systolic_bp';
			if ($bp['diastolic'] >= 120 || $bp['diastolic'] < 60) $reasons[] = 'diastolic_bp';
		}
		return $reasons;
	}

	private function parse_bp($bp){
		$bp = trim((string)$bp);
		if ($bp === '') {
			return null;
		}
		if (!preg_match('/([0-9]{2,3})\\s*\\/\\s*([0-9]{2,3})/', $bp, $matches)) {
			return null;
		}
		return array('systolic' => (int)$matches[1], 'diastolic' => (int)$matches[2]);
	}

	private function hours_since($dateTime){
		$time = strtotime((string)$dateTime);
		if (!$time) {
			return null;
		}
		return round((time() - $time) / 3600, 2);
	}

	private function days_since($date){
		$time = strtotime((string)$date);
		if (!$time) {
			return null;
		}
		return floor((time() - $time) / 86400);
	}

	private function count_alerts_by_severity($alerts, $severity){
		$count = 0;
		foreach ($alerts as $alert) {
			if (isset($alert['severity']) && $alert['severity'] === $severity) $count++;
		}
		return $count;
	}

	private function current_shift($shift){
		$shift = trim((string)$shift);
		$hour = (int)date('G');
		if ($shift === '') {
			if ($hour >= 6 && $hour < 14) {
				$shift = 'morning';
			} elseif ($hour >= 14 && $hour < 22) {
				$shift = 'afternoon';
			} else {
				$shift = 'night';
			}
		}
		if (!in_array($shift, array('morning', 'afternoon', 'night'))) {
			$shift = 'morning';
		}
		$windows = array(
			'morning' => array('label' => 'Morning Shift', 'started_at' => '06:00', 'ends_at' => '13:59'),
			'afternoon' => array('label' => 'Afternoon Shift', 'started_at' => '14:00', 'ends_at' => '21:59'),
			'night' => array('label' => 'Night Shift', 'started_at' => '22:00', 'ends_at' => '05:59')
		);
		return array(
			'code' => $shift,
			'label' => $windows[$shift]['label'],
			'started_at' => $windows[$shift]['started_at'],
			'ends_at' => $windows[$shift]['ends_at']
		);
	}
}
