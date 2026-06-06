<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * Medical Data Controller
 * 
 * AJAX endpoints for smart autocomplete, custom entry save,
 * lab result templates, doctor notifications, and patient timeline.
 */
class Medical_data extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/medical_master_model');
		if (General::is_logged_in() == FALSE) {
			if ($this->input->is_ajax_request()) {
				header('Content-Type: application/json');
				echo json_encode(array('error' => 'Not authenticated'));
				exit;
			}
			redirect(base_url() . 'login');
		}
		General::variable();
		$this->medical_master_model->ensure_all_master_tables();
	}

	/* ================================================================== */
	/*  SMART AUTOCOMPLETE ENDPOINTS                                       */
	/* ================================================================== */

	public function search_medications()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->medical_master_model->search_medications_smart($term);
		$this->_json($results);
	}

	public function search_diagnoses()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->medical_master_model->search_diagnoses_smart($term);
		$this->_json($results);
	}

	public function search_lab_tests()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->medical_master_model->search_lab_tests_smart($term);
		$this->_json($results);
	}

	public function search_scans()
	{
		$term = trim((string)$this->input->get('term'));
		$category = trim((string)$this->input->get('category'));
		$results = $this->medical_master_model->search_scans_smart($term, $category);
		$this->_json($results);
	}

	public function search_sonography()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->medical_master_model->search_scans_smart($term, 'Ultrasound');
		$this->_json($results);
	}

	public function search_ecg()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->medical_master_model->search_scans_smart($term, 'ECG');
		$this->_json($results);
	}

	public function search_xray()
	{
		$term = trim((string)$this->input->get('term'));
		$results = $this->medical_master_model->search_scans_smart($term, 'X-Ray');
		$this->_json($results);
	}

	/* ================================================================== */
	/*  CUSTOM ENTRY SAVE ENDPOINTS                                        */
	/* ================================================================== */

	public function save_custom_medication()
	{
		$name = trim((string)$this->input->post('name'));
		if ($name === '') {
			$this->_json(array('success' => false, 'message' => 'Name is required'));
			return;
		}
		$user_id = (string)$this->session->userdata('user_id');
		$id = $this->medical_master_model->save_custom_medication($name, $user_id);
		$this->_json(array('success' => true, 'id' => $id, 'name' => $name));
	}

	public function save_custom_diagnosis()
	{
		$name = trim((string)$this->input->post('name'));
		if ($name === '') {
			$this->_json(array('success' => false, 'message' => 'Name is required'));
			return;
		}
		$user_id = (string)$this->session->userdata('user_id');
		$id = $this->medical_master_model->save_custom_diagnosis($name, $user_id);
		$this->_json(array('success' => true, 'id' => $id, 'name' => $name));
	}

	public function save_custom_lab_test()
	{
		$name = trim((string)$this->input->post('name'));
		if ($name === '') {
			$this->_json(array('success' => false, 'message' => 'Name is required'));
			return;
		}
		$user_id = (string)$this->session->userdata('user_id');
		$id = $this->medical_master_model->save_custom_lab_test($name, $user_id);
		$this->_json(array('success' => true, 'id' => $id, 'name' => $name));
	}

	public function save_custom_scan()
	{
		$name = trim((string)$this->input->post('name'));
		$category = trim((string)$this->input->post('category'));
		if ($name === '') {
			$this->_json(array('success' => false, 'message' => 'Name is required'));
			return;
		}
		$user_id = (string)$this->session->userdata('user_id');
		$id = $this->medical_master_model->save_custom_scan($name, $category, $user_id);
		$this->_json(array('success' => true, 'id' => $id, 'name' => $name));
	}

	/* ================================================================== */
	/*  LAB RESULT TEMPLATE ENDPOINTS                                      */
	/* ================================================================== */

	public function get_lab_template()
	{
		$test_name = trim((string)$this->input->get('test_name'));
		if ($test_name === '') {
			$this->_json(array());
			return;
		}
		$templates = $this->medical_master_model->find_template_by_test_name($test_name);
		$this->_json($templates);
	}

	public function get_template_test_names()
	{
		$names = $this->medical_master_model->get_all_template_test_names();
		$this->_json($names);
	}

	public function save_structured_result()
	{
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$entries_json = $this->input->post('entries');
		if ($io_lab_id <= 0 || !$entries_json) {
			$this->_json(array('success' => false, 'message' => 'Invalid data'));
			return;
		}
		$entries = json_decode($entries_json, true);
		if (!is_array($entries) || empty($entries)) {
			$this->_json(array('success' => false, 'message' => 'No entries'));
			return;
		}

		$this->load->model('app/laboratory_model');
		$this->load->model('app/service_gate_model', 'service_gate');
		$this->load->model('app/service_gate_audit_model', 'service_gate_audit');

		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			$this->_json(array('success' => false, 'message' => 'Record not found'));
			return;
		}
		$iop_id = ($row && isset($row->iop_id)) ? (string)$row->iop_id : '';
		$isAdmin = has_role('admin');

		$user_id = (string)$this->session->userdata('user_id');
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$rowLocked) {
			$this->db->trans_rollback();
			$this->_json(array('success' => false, 'message' => 'Record not found'));
			return;
		}
		$iop_id_locked = isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		if (!$gate['allowed'] && !$isAdmin) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'save_structured_result', $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required';
			$this->_json(array('success' => false, 'message' => 'Payment Required: '.$reason));
			return;
		}

		$count = $this->medical_master_model->save_structured_result($io_lab_id, $entries, $user_id);

		// Also update the legacy result field with summary
		$summary_parts = array();
		foreach ($entries as $e) {
			$p = isset($e['parameter_name']) ? $e['parameter_name'] : '';
			$v = isset($e['result_value']) ? $e['result_value'] : '';
			if ($p !== '' && $v !== '') $summary_parts[] = $p . ': ' . $v;
		}
		if (!empty($summary_parts)) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->update('iop_laboratory', array('result' => implode('; ', $summary_parts)));
		}

		// Update lab workflow status to REPORTED_TEXT (Results Submitted)
		$labRow = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id))->row();
		$hasPdf = ($labRow && isset($labRow->lab_result_upload) && trim((string)$labRow->lab_result_upload) !== '');
		$status = $hasPdf ? 'REPORTED_BOTH' : 'REPORTED_TEXT';
		$this->laboratory_model->upsert_workflow_status($io_lab_id, $status, $user_id);
		$this->laboratory_model->save_technician($io_lab_id, $user_id);
		log_message('info', 'LAB_STRUCTURED_RESULT_SUBMITTED io_lab_id='.$io_lab_id.' status='.$status.' user='.$user_id.' count='.$count);

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$this->_json(array('success' => false, 'message' => 'Failed to save structured result'));
			return;
		}
		$this->db->trans_commit();

		$auditDecision = $gate;
		if (!$gate['allowed'] && $isAdmin) {
			$auditDecision = array(
				'allowed' => true,
				'decision_type' => 'BYPASSED_ADMIN',
				'raw_gate' => $gate,
				'override_context' => array(
					'type' => 'ADMIN_OVERRIDE',
					'approved_by' => $user_id !== '' ? (int)$user_id : null,
				),
				'reason' => 'Admin override',
			);
		}
		$this->audit_lab_gate_decision($io_lab_id, 'save_structured_result', $auditDecision, 'LAB_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);

		$this->_notify_doctor_of_result($io_lab_id);
		$this->_json(array('success' => true, 'saved' => $count, 'status' => $status));
	}

	private function audit_lab_gate_decision($io_lab_id, $action, $decision, $event_code)
	{
		try {
			$io_lab_id = (int)$io_lab_id;
			$action = trim((string)$action);
			$event_code = trim((string)$event_code);
			if ($io_lab_id <= 0 || $event_code === '') {
				return;
			}
			if (!isset($this->service_gate_audit) || !method_exists($this->service_gate_audit, 'log_event')) {
				return;
			}

			$item_ref = 'io_lab_id:' . (int)$io_lab_id;
			$userId = (string)$this->session->userdata('user_id');
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';
			$uri = method_exists($this->uri, 'uri_string') ? (string)$this->uri->uri_string() : '';

			$decisionType = 'RAW';
			$rawGate = is_array($decision) ? $decision : array();
			$overrideContext = null;
			$finalAllowed = (is_array($rawGate) && array_key_exists('allowed', $rawGate)) ? (bool)$rawGate['allowed'] : false;
			$reason = (is_array($rawGate) && isset($rawGate['reason'])) ? (string)$rawGate['reason'] : null;
			if (is_array($decision) && isset($decision['decision_type']) && isset($decision['raw_gate'])) {
				$decisionType = trim((string)$decision['decision_type']) !== '' ? trim((string)$decision['decision_type']) : 'RAW';
				$rawGate = is_array($decision['raw_gate']) ? $decision['raw_gate'] : array();
				$overrideContext = isset($decision['override_context']) ? $decision['override_context'] : null;
				$finalAllowed = array_key_exists('allowed', $decision) ? (bool)$decision['allowed'] : $finalAllowed;
				$reason = isset($decision['reason']) ? (string)$decision['reason'] : $reason;
			}

			$blockedReason = (is_array($rawGate) && isset($rawGate['blocked_reason'])) ? trim((string)$rawGate['blocked_reason']) : '';
			if (!$finalAllowed && $blockedReason === '') {
				$blockedReason = 'PAYMENT_PENDING';
			}

			$this->service_gate_audit->log_event(array(
				'event_code' => $event_code,
				'module' => 'LAB',
				'item_ref' => $item_ref,
				'user_id' => $userId !== '' ? (int)$userId : null,
				'action' => $action !== '' ? $action : null,
				'blocked_reason' => $finalAllowed ? null : $blockedReason,
				'allowed' => $finalAllowed ? 1 : 0,
				'gate_version' => 'v1',
				'reason' => $reason,
				'payload' => array(
					'io_lab_id' => (int)$io_lab_id,
					'item_ref' => $item_ref,
					'gate_version' => 'v1',
					'method' => $method,
					'uri' => $uri,
					'allowed' => $finalAllowed ? true : false,
					'decision_type' => $decisionType,
					'raw_gate' => $rawGate,
					'override_context' => $overrideContext,
				),
				'ip' => $ip,
			));
		} catch (\Throwable $e) {
		}
	}

	private function maybe_sample_gate_parity_check($io_lab_id, $decision = null)
	{
		try {
			$io_lab_id = (int)$io_lab_id;
			if ($io_lab_id <= 0) {
				return;
			}
			$rawGate = null;
			if (is_array($decision) && isset($decision['raw_gate']) && is_array($decision['raw_gate'])) {
				$rawGate = $decision['raw_gate'];
			} elseif (is_array($decision)) {
				$rawGate = $decision;
			}
			$rawAllowed = null;
			if (is_array($rawGate) && array_key_exists('allowed', $rawGate)) {
				$rawAllowed = (bool)$rawGate['allowed'];
			}
			if ($rawAllowed === true) {
				if (rand(1, 100) > 2) {
					return;
				}
			}

			$this->load->model('app/unified_worklist_model', 'unified_worklist');
			$this->load->model('app/service_gate_model', 'service_gate');

			$item_ref = 'io_lab_id:' . (int)$io_lab_id;
			$row = method_exists($this->unified_worklist, 'get_item_by_ref_raw') ? $this->unified_worklist->get_item_by_ref_raw($item_ref) : $this->unified_worklist->get_item_by_ref($item_ref);
			if (!$row) {
				return;
			}

			$iop_id = isset($row['iop_id']) ? $row['iop_id'] : null;
			$patient_no = isset($row['patient_no']) ? $row['patient_no'] : null;
			$backend = method_exists($this->service_gate, 'check_service_raw')
				? $this->service_gate->check_service_raw('LAB', (string)(int)$io_lab_id, $iop_id, $patient_no)
				: $this->service_gate->check_service('LAB', (string)(int)$io_lab_id, $iop_id, $patient_no);

			$sql_allowed = isset($row['can_proceed']) ? ((int)$row['can_proceed'] === 1 || $row['can_proceed'] === true) : false;
			$backend_allowed = isset($backend['allowed']) ? (bool)$backend['allowed'] : false;
			$sql_reason = $sql_allowed ? 'ALLOWED' : (isset($row['blocked_reason']) ? (string)$row['blocked_reason'] : null);
			$backend_reason = $backend_allowed ? 'ALLOWED' : (isset($backend['blocked_reason']) ? (string)$backend['blocked_reason'] : null);
			$mismatch = ($sql_allowed !== $backend_allowed) || ($sql_reason !== $backend_reason);
			if (!$mismatch) {
				return;
			}

			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$userId = (string)$this->session->userdata('user_id');
			if (isset($this->service_gate_audit) && method_exists($this->service_gate_audit, 'log_event')) {
				$this->service_gate_audit->log_event(array(
					'event_code' => 'GATE_PARITY_MISMATCH',
					'module' => 'LAB',
					'item_ref' => $item_ref,
					'user_id' => $userId !== '' ? (int)$userId : null,
					'action' => 'parity_sample',
					'blocked_reason' => null,
					'allowed' => null,
					'gate_version' => 'v1',
					'reason' => 'Gate parity mismatch (LAB)',
					'payload' => array(
						'io_lab_id' => (int)$io_lab_id,
						'item_ref' => $item_ref,
						'sql' => array(
							'allowed' => $sql_allowed,
							'reason' => $sql_reason,
							'row' => $row,
						),
						'backend' => $backend,
						'raw_gate' => $rawGate,
					),
					'ip' => $ip,
				));
			}
		} catch (\Throwable $e) {
		}
	}

	public function get_structured_results()
	{
		$io_lab_id = (int)$this->input->get('io_lab_id');
		if ($io_lab_id <= 0) {
			$this->_json(array());
			return;
		}
		$results = $this->medical_master_model->get_structured_results($io_lab_id);
		$this->_json($results);
	}

	private function _notify_doctor_of_result($io_lab_id)
	{
		// Get lab record details
		$sql = "SELECT l.iop_id, l.doctor AS doctor_id,
				       COALESCE(bp.particular_name, l.laboratory_text, 'Lab Test') AS test_name,
				       pd.patient_no
				FROM iop_laboratory l
				LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id
				LEFT JOIN patient_details_iop pd ON pd.IO_ID = l.iop_id AND pd.InActive = 0
				WHERE l.io_lab_id = ? AND l.InActive = 0 LIMIT 1";
		$q = $this->db->query($sql, array($io_lab_id));
		if (!$q || $q->num_rows() === 0) return;
		$lab = $q->row();
		if (empty($lab->doctor_id)) return;

		$this->medical_master_model->create_lab_notification(
			$lab->doctor_id, $lab->patient_no, $lab->iop_id, $io_lab_id, $lab->test_name
		);
	}

	/* ================================================================== */
	/*  DOCTOR NOTIFICATION ENDPOINTS                                      */
	/* ================================================================== */

	public function get_notifications()
	{
		$doctor_id = (string)$this->session->userdata('user_id');
		$notifications = $this->medical_master_model->get_unread_notifications($doctor_id);
		$this->_json($notifications);
	}

	public function count_notifications()
	{
		$doctor_id = (string)$this->session->userdata('user_id');
		$count = $this->medical_master_model->count_unread_notifications($doctor_id);
		$this->_json(array('count' => $count));
	}

	public function mark_read()
	{
		$notif_id = (int)$this->input->post('notif_id');
		$doctor_id = (string)$this->session->userdata('user_id');
		$this->medical_master_model->mark_notification_read($notif_id, $doctor_id);
		$this->_json(array('success' => true));
	}

	public function mark_all_read()
	{
		$doctor_id = (string)$this->session->userdata('user_id');
		$this->medical_master_model->mark_all_read($doctor_id);
		$this->_json(array('success' => true));
	}

	/* ================================================================== */
	/*  PATIENT TIMELINE ENDPOINT                                          */
	/* ================================================================== */

	public function patient_timeline()
	{
		$patient_no = trim((string)$this->input->get('patient_no'));
		if ($patient_no === '') {
			$this->_json(array());
			return;
		}
		$timeline = $this->medical_master_model->get_patient_timeline($patient_no);
		$this->_json($timeline);
	}

	/* ================================================================== */
	/*  CATEGORY LISTS                                                     */
	/* ================================================================== */

	public function lab_categories()
	{
		$cats = $this->medical_master_model->get_lab_categories();
		$this->_json($cats);
	}

	public function scan_categories()
	{
		$cats = $this->medical_master_model->get_scan_categories();
		$this->_json($cats);
	}

	/* ================================================================== */
	/*  HELPER                                                             */
	/* ================================================================== */

	private function _json($data)
	{
		header('Content-Type: application/json');
		echo json_encode($data);
	}
}
