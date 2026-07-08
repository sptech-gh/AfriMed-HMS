<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Laboratory extends General
{

	private $limit = 10;

	private function ensure_private_lab_result_dir(){
		// Store uploads ABOVE the web root so they are not directly accessible via URL.
		// FCPATH = web root (e.g. C:/laragon/www/hms-master/).
		// We go one level up then into private_uploads to keep it outside the served tree.
		$dir = rtrim(dirname(rtrim(FCPATH, '\\/')),'\\/')
			. DIRECTORY_SEPARATOR . 'hms_private_uploads'
			. DIRECTORY_SEPARATOR . 'patient_lab_result';
		if (!is_dir($dir)) {
			@mkdir($dir, 0750, true);
		}
		return $dir;
	}

	public function __construct()
	{
		parent::__construct();
		$this->load->model("app/laboratory_model");
		$this->load->model("app/medical_master_model");
		$this->load->model("app/unified_billing_model");
		$this->load->model("app/diagnostic_safety_model");
		$this->load->model("app/service_gate_model", "service_gate");
		$this->load->model('app/service_gate_audit_model', 'service_gate_audit');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();
		log_message('debug', 'LAB_CONSTRUCTOR method='.$this->router->fetch_method());
		if (!$this->session->userdata('_schema_lab_ok')) {
			$this->laboratory_model->ensure_lab_workflow_enhancements();
			$this->laboratory_model->ensure_lab_flexible_schema();
			$this->laboratory_model->ensure_lab_ghs_schema();
			$this->medical_master_model->ensure_all_master_tables();
			$this->unified_billing_model->ensure_unified_billing_schema();
			$this->diagnostic_safety_model->ensure_all_safety_schemas();
			$this->session->set_userdata('_schema_lab_ok', 1);
		}
	}

	public function walkin_queue()
	{
		if (!$this->current_user_is_admin() && !(isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory'])) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$this->session->set_userdata(array('tab' => 'laboratory', 'module' => 'walkin_queue', 'subtab' => '', 'submodule' => ''));
		$this->load->model('app/walkin_order_model');
		$this->walkin_order_model->ensure_walkin_schema();
		$this->data['pending'] = $this->walkin_order_model->get_department_queue('LAB', null, 200);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/walkin_queue', $this->data);
	}

	public function walkin_update_status()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!$this->current_user_is_admin() && !(isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory'])) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$item_id = (int)$this->input->post('item_id');
		$status = trim((string)$this->input->post('status'));
		$notes = trim((string)$this->input->post('notes'));
		$this->load->model('app/walkin_order_model');
		$res = $this->walkin_order_model->update_lab_status($item_id, $status, (string)$this->session->userdata('user_id'), $notes);
		if (is_array($res) && !empty($res['success'])) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Walk-in lab status updated.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">' . htmlspecialchars(isset($res['error']) ? $res['error'] : 'Update failed') . '</div>');
		}
		redirect(base_url() . 'app/laboratory/walkin_queue');
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

	private function lab_payment_confirmed_raw($io_lab_id, $iop_id = null, $patient_no = null)
	{
		try {
			$io_lab_id = (int)$io_lab_id;
			if ($io_lab_id <= 0 || !isset($this->service_gate)) {
				return false;
			}
			if (method_exists($this->service_gate, 'check_service_raw')) {
				$raw = $this->service_gate->check_service_raw('LAB', (string)$io_lab_id, $iop_id, $patient_no);
				return (is_array($raw) && isset($raw['allowed']) && (bool)$raw['allowed']);
			}
		} catch (\Throwable $e) {
		}
		return false;
	}

	private function lab_result_edit_locked($io_lab_id)
	{
		try {
			$io_lab_id = (int)$io_lab_id;
			if ($io_lab_id <= 0) return false;
			$wf = $this->laboratory_model->get_workflow_status($io_lab_id);
			if (!$wf) return false;
			$st = isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
			if ($st !== 'VERIFIED') return false;
			$delivered = isset($wf->delivered_at) ? trim((string)$wf->delivered_at) : '';
			if ($delivered !== '' && $delivered !== '0000-00-00 00:00:00') return true;
			$ack = isset($wf->doctor_acknowledged_at) ? trim((string)$wf->doctor_acknowledged_at) : '';
			if ($ack !== '' && $ack !== '0000-00-00 00:00:00') return true;
		} catch (\Throwable $e) {
		}
		return false;
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

	public function batch_save_results()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if ($this->input->method() !== 'post') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid method'));
			exit;
		}

		$iop_id_raw = (string)$this->input->post('iop_id');
		$iop_id = url_decode_id($iop_id_raw);
		$entriesRaw = $this->input->post('entries');
		$entries = is_string($entriesRaw) ? json_decode($entriesRaw, true) : $entriesRaw;
		if (!is_array($entries) || empty($entries)) {
			echo json_encode(array('ok' => false, 'error' => 'No entries'));
			exit;
		}

		$this->laboratory_model->install_imaging_tables();
		$savedCount = 0;
		$skipped = array();
		$isAdmin = $this->current_user_is_admin();
		$shadowMode = (bool)$this->config->item('lab_release_shadow_mode');
		if ($shadowMode) {
			$this->load->model('app/laboratory_release_model');
		}

		foreach ($entries as $e) {
			$io_lab_id = isset($e['io_lab_id']) ? (int)$e['io_lab_id'] : 0;
			if ($io_lab_id <= 0) { $skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'invalid_id'); continue; }
			$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
			if (!$row) { $skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'not_found'); continue; }
			if ($iop_id !== '' && isset($row->iop_id) && (string)$row->iop_id !== (string)$iop_id) {
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'wrong_visit');
				continue;
			}
			if (!$this->user_can_write_lab_row($row)) {
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'access_denied');
				continue;
			}

			$findings = isset($e['findings']) ? (string)$e['findings'] : '';
			$result = isset($e['result']) ? (string)$e['result'] : '';
			if (trim($result) === '') {
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'empty_result');
				continue;
			}

			$this->db->trans_begin();
			$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
			$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
			if (!$rowLocked) {
				$this->db->trans_rollback();
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'not_found');
				continue;
			}
			$gate = $this->service_gate->check_lab_gate($io_lab_id, isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '');
			if ($this->lab_result_edit_locked($io_lab_id)) {
				$this->db->trans_rollback();
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'locked');
				continue;
			}
			if (!$gate['allowed'] && !$isAdmin) {
				$this->db->trans_rollback();
				$this->audit_lab_gate_decision($io_lab_id, 'batch_save_results', $gate, 'LAB_GATE_BLOCKED');
				$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'payment_required', 'label' => isset($gate['reason']) ? $gate['reason'] : 'Payment required');
				continue;
			}

			$old = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id))->row();
			$oldVal = $old ? array('findings' => isset($old->findings) ? (string)$old->findings : '', 'result' => isset($old->result) ? (string)$old->result : '') : null;

			$this->laboratory_model->update_result_fields($io_lab_id, $findings, $result);
			$after = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id))->row();
			$hasPdf = ($after && isset($after->lab_result_upload) && trim((string)$after->lab_result_upload) !== '');
			$status = $hasPdf ? 'REPORTED_BOTH' : 'REPORTED_TEXT';
			$this->laboratory_model->upsert_workflow_status($io_lab_id, $status, $this->session->userdata('user_id'));
			$this->laboratory_model->save_technician($io_lab_id, $this->session->userdata('user_id'));

			$patient_no = isset($rowLocked->patient_no) ? (string)$rowLocked->patient_no : '';
			$this->diagnostic_safety_model->log_diagnostic_audit('RESULT_SAVED_BATCH', 'iop_laboratory', $io_lab_id, $io_lab_id, $patient_no, $oldVal, array('findings' => $findings, 'result' => $result));

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				$skipped[] = array('io_lab_id' => $io_lab_id, 'reason' => 'txn_failed');
				continue;
			}
			$this->db->trans_commit();
			if ($shadowMode && isset($this->laboratory_release_model) && method_exists($this->laboratory_release_model, 'save_result_draft_shadow')) {
				try {
					$user_id = (string)$this->session->userdata('user_id');
					$this->laboratory_release_model->save_result_draft_shadow($io_lab_id, $findings, $result, $user_id, array(
						'action' => 'batch_save_results',
						'has_pdf' => $hasPdf ? 1 : 0,
						'workflow_status' => $status,
					));
				} catch (\Throwable $e) {
					log_message('error', 'LAB_RELEASE_SHADOW_WRITE_FAILED action=batch_save_results io_lab_id='.(int)$io_lab_id.' err='.$e->getMessage());
				}
			}
			$savedCount++;
			$auditDecision = $gate;
			if (!$gate['allowed'] && $isAdmin) {
				$user_id = (string)$this->session->userdata('user_id');
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
			$this->audit_lab_gate_decision($io_lab_id, 'batch_save_results', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		}

		echo json_encode(array('ok' => true, 'saved' => $savedCount, 'skipped' => $skipped));
		exit;
	}

	public function install_release_schema()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if ($this->input->method() !== 'post') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid method'));
			exit;
		}
		if (!$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'access_denied'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->ensure_release_schema();
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	public function release_shadow_report()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if (!$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'access_denied'));
			exit;
		}
		if (!(bool)$this->config->item('lab_release_diagnostics_enabled')) {
			echo json_encode(array('ok' => false, 'error' => 'diagnostics_disabled'));
			exit;
		}

		$limit = (int)$this->input->get('limit');
		if ($limit <= 0) $limit = 100;
		if ($limit > 500) $limit = 500;

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->get_shadow_mismatch_report($limit);
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	public function release_shadow_item()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if (!$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'access_denied'));
			exit;
		}
		if (!(bool)$this->config->item('lab_release_diagnostics_enabled')) {
			echo json_encode(array('ok' => false, 'error' => 'diagnostics_disabled'));
			exit;
		}

		$io_lab_id = (int)$this->input->get('io_lab_id');
		if ($io_lab_id <= 0) {
			echo json_encode(array('ok' => false, 'error' => 'invalid_io_lab_id'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->get_shadow_item_report($io_lab_id);
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	private function require_release_batch_mode_json(){
		if (!$this->current_user_is_admin()) {
			echo json_encode(array('ok' => false, 'error' => 'access_denied'));
			exit;
		}
		if (!(bool)$this->config->item('lab_release_batch_mode_enabled')) {
			echo json_encode(array('ok' => false, 'error' => 'batch_mode_disabled'));
			exit;
		}
	}

	public function release_group_status()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		$this->require_release_batch_mode_json();
		$iop_id = trim((string)$this->input->get('iop_id'));
		$category_id = trim((string)$this->input->get('category_id'));
		if ($iop_id === '' || $category_id === '') {
			echo json_encode(array('ok' => false, 'error' => 'invalid_group'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->get_release_group_status($iop_id, $category_id);
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	public function release_group_build()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if ($this->input->method() !== 'post') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid method'));
			exit;
		}
		$this->require_release_batch_mode_json();
		$iop_id = trim((string)$this->input->post('iop_id'));
		$category_id = trim((string)$this->input->post('category_id'));
		if ($iop_id === '' || $category_id === '') {
			echo json_encode(array('ok' => false, 'error' => 'invalid_group'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->build_release_group($iop_id, $category_id, (string)$this->session->userdata('user_id'));
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	public function release_group_release()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if ($this->input->method() !== 'post') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid method'));
			exit;
		}
		$this->require_release_batch_mode_json();
		$iop_id = trim((string)$this->input->post('iop_id'));
		$category_id = trim((string)$this->input->post('category_id'));
		if ($iop_id === '' || $category_id === '') {
			echo json_encode(array('ok' => false, 'error' => 'invalid_group'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$enforce = (bool)$this->config->item('lab_release_enforce_no_partial');
			$res = $this->laboratory_release_model->release_group($iop_id, $category_id, (string)$this->session->userdata('user_id'), $enforce);
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	public function release_group_amend()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		if ($this->input->method() !== 'post') {
			echo json_encode(array('ok' => false, 'error' => 'Invalid method'));
			exit;
		}
		$this->require_release_batch_mode_json();
		$iop_id = trim((string)$this->input->post('iop_id'));
		$category_id = trim((string)$this->input->post('category_id'));
		if ($iop_id === '' || $category_id === '') {
			echo json_encode(array('ok' => false, 'error' => 'invalid_group'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->amend_release_group($iop_id, $category_id, (string)$this->session->userdata('user_id'));
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	public function release_group_snapshots()
	{
		while (ob_get_level()) { ob_end_clean(); }
		header('Content-Type: application/json');
		header('Cache-Control: no-cache, must-revalidate');

		$this->require_release_batch_mode_json();
		$iop_id = trim((string)$this->input->get('iop_id'));
		$category_id = trim((string)$this->input->get('category_id'));
		$release_id = $this->input->get('release_id');
		$release_id = ($release_id === null || $release_id === '') ? null : (int)$release_id;
		if ($iop_id === '' || $category_id === '') {
			echo json_encode(array('ok' => false, 'error' => 'invalid_group'));
			exit;
		}

		$this->load->model('app/laboratory_release_model');
		try {
			$res = $this->laboratory_release_model->get_release_group_snapshots($iop_id, $category_id, $release_id);
			echo json_encode($res);
			exit;
		} catch (\Throwable $e) {
			echo json_encode(array('ok' => false, 'error' => 'exception', 'message' => $e->getMessage()));
			exit;
		}
	}

	private function user_can_read_lab_row($row){
		if (!$row) {
			return false;
		}
		$sono_cat = (string)$this->laboratory_model->get_sonography_category_id();
		if (isset($row->category_id) && (string)$row->category_id === $sono_cat) {
			if ($this->current_user_is_admin()) {
				return true;
			}
			if (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) {
				return true;
			}
			$userId = (string)$this->session->userdata('user_id');
			if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor'] && $userId !== '') {
				if (isset($row->doctor) && (string)$row->doctor !== '' && (string)$row->doctor === $userId) {
					return true;
				}
				if (isset($row->assigned_doctor_id) && (string)$row->assigned_doctor_id !== '' && (string)$row->assigned_doctor_id === $userId) {
					return true;
				}
				$this->laboratory_model->install_imaging_tables();
				$meta = $this->laboratory_model->get_sonography_request_meta((int)$row->io_lab_id);
				if ($meta && isset($meta->requesting_doctor_id) && (string)$meta->requesting_doctor_id !== '' && (string)$meta->requesting_doctor_id === $userId) {
					return true;
				}
			}
			return false;
		}
		$radio_cat = (string)$this->laboratory_model->get_radiology_category_id();
		if (isset($row->category_id) && (string)$row->category_id === $radio_cat) {
			if ($this->current_user_is_admin()) {
				return true;
			}
			if (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) {
				return true;
			}
		}
		if ((isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']) || (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory'])) {
			return true;
		}
		$userId = (string)$this->session->userdata('user_id');
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) {
			$assigned = isset($row->assigned_doctor_id) ? (string)$row->assigned_doctor_id : '';
			$requesting = isset($row->doctor) ? (string)$row->doctor : '';
			if ($assigned !== '' && $assigned === $userId) {
				return true;
			}
			if ($requesting !== '' && $requesting === $userId) {
				return true;
			}
		}
		return false;
	}

	private function user_can_write_lab_row($row){
		if (!$row) {
			return false;
		}
		$sono_cat = (string)$this->laboratory_model->get_sonography_category_id();
		if (isset($row->category_id) && (string)$row->category_id === $sono_cat) {
			if ($this->current_user_is_admin()) {
				return true;
			}
			if (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) {
				return true;
			}
			return false;
		}
		$radio_cat = (string)$this->laboratory_model->get_radiology_category_id();
		if (isset($row->category_id) && (string)$row->category_id === $radio_cat) {
			if ($this->current_user_is_admin()) {
				return true;
			}
			if (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) {
				return true;
			}
		}
		if ((isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']) || (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory'])) {
			return true;
		}
		return false;
	}

	public function install_imaging(){
		if (!$this->current_user_is_admin()) {
			redirect(base_url().'access_denied');
			return;
		}
		$this->laboratory_model->install_imaging_tables();
		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Imaging/Sonography metadata tables installed/verified.</div>");
		redirect(base_url().'app/laboratory');
	}

	public function db_hardening(){
		if (!$this->current_user_is_admin()) {
			redirect(base_url().'access_denied');
			return;
		}
		$this->laboratory_model->install_imaging_tables();
		$plan = $this->laboratory_model->imaging_db_hardening_plan();

		$isPost = (isset($_SERVER['REQUEST_METHOD']) && strtoupper((string)$_SERVER['REQUEST_METHOD']) === 'POST');
		$doRun = $isPost && ((string)$this->input->post('confirm_run') === 'YES');
		$result = null;
		if ($doRun) {
			$result = $this->laboratory_model->apply_imaging_db_hardening();
			if ($result && isset($result['ok']) && $result['ok']) {
				$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>DB hardening/migration completed.</div>");
			} else {
				$this->session->set_flashdata('message',"<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>DB hardening/migration finished with errors. Review the output below.</div>");
			}
			$plan = $this->laboratory_model->imaging_db_hardening_plan();
		}

		$html = "<div style='margin-bottom:10px'>";
		$html .= "<a class='btn btn-default' href='".base_url()."app/laboratory'><i class='fa fa-arrow-left'></i> Back</a> ";
		$html .= "<a class='btn btn-default' href='".base_url()."app/laboratory/db_hardening'><i class='fa fa-refresh'></i> Refresh</a>";
		$html .= "</div>";
		$html .= "<div class='alert alert-info' style='margin-bottom:10px'>This tool migrates imaging/workflow tables to <b>InnoDB</b> and <b>utf8mb4</b>, and repairs MyISAM if detected. It is idempotent. Run during low-traffic hours.</div>";

		if ($result && isset($result['steps']) && is_array($result['steps'])) {
			$html .= "<h4>Execution Results</h4>";
			$html .= "<div class='box box-default'><div class='box-body table-responsive no-padding'>";
			$html .= "<table class='table table-hover table-striped'><thead><tr><th>SQL</th><th>Status</th><th>Error</th></tr></thead><tbody>";
			foreach ($result['steps'] as $s) {
				$ok = isset($s['ok']) && $s['ok'];
				$html .= "<tr>";
				$html .= "<td><code>".htmlspecialchars((string)$s['sql'], ENT_QUOTES, 'UTF-8')."</code></td>";
				$html .= "<td>".($ok ? "<span class='label label-success'>OK</span>" : "<span class='label label-danger'>FAILED</span>")."</td>";
				$html .= "<td>".htmlspecialchars((string)(isset($s['error']) ? $s['error'] : ''), ENT_QUOTES, 'UTF-8')."</td>";
				$html .= "</tr>";
			}
			$html .= "</tbody></table></div></div>";
		}

		$html .= "<h4>Dry-run Plan</h4>";
		$html .= "<div class='box box-default'><div class='box-body table-responsive no-padding'>";
		$html .= "<table class='table table-hover table-striped'><thead><tr><th>Table</th><th>Engine</th><th>Collation</th></tr></thead><tbody>";
		if ($plan && isset($plan['tables']) && is_array($plan['tables'])) {
			foreach ($plan['tables'] as $t) {
				$html .= "<tr>";
				$html .= "<td>".htmlspecialchars((string)$t['table'], ENT_QUOTES, 'UTF-8')."</td>";
				$html .= "<td>".htmlspecialchars((string)$t['engine'], ENT_QUOTES, 'UTF-8')."</td>";
				$html .= "<td>".htmlspecialchars((string)$t['collation'], ENT_QUOTES, 'UTF-8')."</td>";
				$html .= "</tr>";
			}
		}
		$html .= "</tbody></table></div></div>";

		$html .= "<div class='box box-default'><div class='box-body'>";
		$html .= "<div style='margin-bottom:10px'><b>SQL to execute:</b></div>";
		$html .= "<pre style='max-height:320px;overflow:auto'>";
		if ($plan && isset($plan['sql']) && is_array($plan['sql'])) {
			foreach ($plan['sql'] as $stmt) {
				$html .= htmlspecialchars((string)$stmt, ENT_QUOTES, 'UTF-8') . ";\n";
			}
		}
		$html .= "</pre>";
		$html .= "</div></div>";

		$html .= "<form method='post' action='".base_url()."app/laboratory/db_hardening' style='margin-top:10px'>";
		$html .= "<input type='hidden' name='confirm_run' value='YES'>";
		$html .= "<button class='btn btn-danger' type='submit' onclick=\"return confirm('Run DB hardening now? This may lock tables during ALTER.');\"><i class='fa fa-database'></i> Execute Migration</button>";
		$html .= "</form>";

		$this->data['page_title'] = 'DB Hardening / Migrations';
		$this->data['message'] = (string)$this->session->flashdata('message');
		$this->data['table'] = $html;
		$this->data['pagination'] = '';
		$this->load->view('app/laboratory/index', $this->data);
	}

	public function download_result($io_lab_id){
		$io_lab_id = (int)$io_lab_id;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			redirect(base_url().'access_denied');
			return;
		}
		if (!$this->user_can_read_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		$filename = isset($row->lab_result_upload) ? (string)$row->lab_result_upload : '';
		if (trim($filename) === '') {
			redirect(base_url().'access_denied');
			return;
		}
		$snapshotRead = (bool)$this->config->item('lab_release_snapshot_read_enabled');
		$isAdmin = $this->current_user_is_admin();
		$isLab = (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory']);
		if ($snapshotRead && !$isAdmin && !$isLab) {
			if ($this->db->table_exists('iop_laboratory_release_snapshot') && $this->db->table_exists('iop_laboratory_release_batch')) {
				$q = $this->db->query(
					"SELECT S.snapshot_id\n					 FROM iop_laboratory_release_snapshot S\n					 JOIN iop_laboratory_release_batch B ON B.release_id = S.release_id AND B.InActive = 0 AND B.release_status = 'RELEASED'\n					 WHERE S.io_lab_id = ? AND S.InActive = 0\n					 LIMIT 1",
					array((int)$io_lab_id)
				);
				$hasSnap = $q && $q->row();
				if (!$hasSnap) {
					redirect(base_url().'access_denied');
					return;
				}
			} else {
				redirect(base_url().'access_denied');
				return;
			}
		}
		$this->laboratory_model->install_imaging_tables();
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) {
			$this->laboratory_model->mark_delivered_if_needed($io_lab_id, $this->session->userdata('user_id'));
		}

		$safeName = basename($filename);
		$privateDir = $this->ensure_private_lab_result_dir();
		$privateBase = realpath($privateDir);
		$privatePath = realpath($privateDir . DIRECTORY_SEPARATOR . $safeName);
		$publicBase = realpath('public/patient_lab_result');
		$publicPath = realpath('public/patient_lab_result/'.$safeName);
		$fullPath = null;
		if ($privateBase && $privatePath && strpos($privatePath, $privateBase) === 0 && file_exists($privatePath)) {
			$fullPath = $privatePath;
		} else if ($publicBase && $publicPath && strpos($publicPath, $publicBase) === 0 && file_exists($publicPath)) {
			$fullPath = $publicPath;
		}
		if (!$fullPath) {
			redirect(base_url().'access_denied');
			return;
		}

		$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
		$contentType = 'application/octet-stream';
		if ($ext === 'pdf') {
			$contentType = 'application/pdf';
		} else if ($ext === 'jpg' || $ext === 'jpeg') {
			$contentType = 'image/jpeg';
		} else if ($ext === 'png') {
			$contentType = 'image/png';
		} else {
			redirect(base_url().'access_denied');
			return;
		}
		header('X-Content-Type-Options: nosniff');
		header('Content-Type: '.$contentType);
		header('Content-Disposition: inline; filename="'.$safeName.'"');
		header('Content-Length: '.filesize($fullPath));
		readfile($fullPath);
		exit;
	}

	public function index()
	{
		// D6: Redirect legacy index to the enhanced lab queue
		redirect(base_url() . 'app/laboratory/lab_queue');
	}

	public function pending_laboratory_requests($offset = 0)
	{
		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);

		// $laboratory = $this->laboratory_model->getAll($this->limit, $offset);
		$laboratory_requests = $this->laboratory_model->pending_lab_requests($this->limit, $offset);

		$config['base_url'] = base_url() . 'app/laboratory/index/';
		$config['total_rows'] = $this->laboratory_model->count_all_pending_request();
		$config['per_page'] = $this->limit;

		$config['uri_segment'] = $uri_segment;
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';

		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();

		// Build payment-status map keyed by iop_id
		$iopIds = array();
		foreach ($laboratory_requests as $lr) { $iopIds[] = (string)$lr->iop_id; }
		$labBillMap = array();
		if (!empty($iopIds) && $this->laboratory_model->table_exists('iop_lab_billing')) {
			$rows = $this->db->where_in('iop_id', $iopIds)->where('InActive', 0)->get('iop_lab_billing')->result();
			foreach ($rows as $r) { $labBillMap[(string)$r->iop_id] = $r; }
		}

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('IOP', 'Patient ID', 'Patient Name', 'Age', 'Payment', 'Request Date', 'Action');
		foreach ($laboratory_requests as $laboratory_requests) {
			$iop = (string)$laboratory_requests->iop_id;
			$payBadge = '';
			$billRow = isset($labBillMap[$iop]) ? $labBillMap[$iop] : null;
			$payStatus = $billRow ? strtoupper(trim((string)$billRow->payment_status)) : 'UNKNOWN';
			if ($payStatus === 'PAID') {
				$payBadge = '<span class="label label-success"><i class="fa fa-check"></i> PAID</span>';
			} elseif ($payStatus === 'PENDING') {
				$payBadge = '<span class="label label-warning"><i class="fa fa-clock-o"></i> PENDING</span>';
			} else {
				$payBadge = '<span class="label label-default">—</span>';
			}
			$deleteForm = '<form method="post" action="'.base_url().'app/laboratory/delete_main/'.$iop.'" style="display:inline;" onsubmit="return confirm(\'Are you sure want to delete?\')">'
				.'<input type="hidden" name="'.$this->security->get_csrf_token_name().'" value="'.$this->security->get_csrf_hash().'">'
				.'<button type="submit" class="delete btn btn-xs btn-danger">Delete</button>'
				.'</form>';

			$this->table->add_row(
				anchor('app/laboratory/request/' . $iop, $iop),
				$laboratory_requests->patient_no,
				$laboratory_requests->patient_name,
				$this->birth_day_age($laboratory_requests->birthday),
				$payBadge,
				$laboratory_requests->dDate,
				$deleteForm
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/laboratory/index', $this->data);
	}

	public function delete_sonography_main($iop_id)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Deletion of sonography requests is disabled to protect clinical records.</div>");
		redirect(base_url() . 'app/laboratory/sonography', $this->data);
	}

	public function sonography($offset = 0){
		if (!$this->current_user_is_admin() && !(isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography'])) {
			redirect(base_url().'access_denied');
			return;
		}
		// user restriction function
		$this->session->set_userdata('page_name', 'access_laboratory_module');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			redirect(base_url() . 'access_denied');
		}
		// end of user restriction function

		$this->session->set_userdata(array(
			'tab' => 'laboratory',
			'module' => 'sonography',
			'subtab' => '',
			'submodule' => ''
		));

		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		$groupId = 18;
		$this->data['page_title'] = 'Sonography (Ultrasound)';
		$laboratory_requests = $this->laboratory_model->pending_sonography_requests($groupId, $this->limit, $offset);

		$config['base_url'] = base_url() . 'app/laboratory/sonography/';
		$config['total_rows'] = $this->laboratory_model->count_pending_sonography_requests($groupId);
		$config['per_page'] = $this->limit;
		$config['uri_segment'] = $uri_segment;
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('IOP', 'Patient ID', 'Patient Name', 'Age', 'Request Date', 'Action');
		foreach ($laboratory_requests as $r) {
			$deleteForm = '<form method="post" action="'.base_url().'app/laboratory/delete_sonography_main/'.$r->iop_id.'" style="display:inline;" onsubmit="return confirm(\'Are you sure want to delete?\')">'
				.'<input type="hidden" name="'.$this->security->get_csrf_token_name().'" value="'.$this->security->get_csrf_hash().'">'
				.'<button type="submit" class="delete btn btn-xs btn-danger">Delete</button>'
				.'</form>';
			$this->table->add_row(
				anchor('app/laboratory/sonography_request/' . $r->iop_id, $r->iop_id),
				$r->patient_no,
				$r->patient_name,
				$this->birth_day_age($r->birthday),
				$r->dDate,
				$deleteForm
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->load->view('app/laboratory/index', $this->data);
	}

	public function sonography_request($offset = 0){
		if (!$this->current_user_is_admin() && !(isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography'])) {
			redirect(base_url().'access_denied');
			return;
		}
		$uri_segment = 5;
		$offset = $this->uri->segment($uri_segment);
		// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
		$iop_id = $this->segment_decoded(4);
		$groupId = 18;
		$this->data['page_title'] = 'Sonography Requests';
		$labs = $this->laboratory_model->get_sonography_requests($groupId, $iop_id, true);
		$ioIds = array();
		foreach ($labs as $l) { $ioIds[] = $l->io_lab_id; }
		$this->data['workflow_map'] = $this->laboratory_model->get_workflow_map($ioIds);

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Sonography', 'Patient ID', 'Patient Name', 'Age', 'Status', 'Findings', 'Results', 'Request Date', 'Action');
		foreach ($labs as $laboratory) {
			$labsName = $laboratory->particular_name ? $laboratory->particular_name : $laboratory->laboratory_text;
			$wf = isset($this->data['workflow_map'][(string)$laboratory->io_lab_id]) ? $this->data['workflow_map'][(string)$laboratory->io_lab_id] : null;
			$status = $wf && isset($wf->status) ? $wf->status : ((trim((string)$laboratory->result) === '') ? 'REQUESTED' : 'REPORTED');
			$st = strtoupper(trim((string)$status));
			if ($st === 'REQUESTED') {
				$status = '<span class="label label-info">Requested</span>';
			} else if ($st === 'IN_PROGRESS') {
				$status = '<span class="label label-warning">In Progress</span>';
			} else if ($st === 'CANCELLED') {
				$status = '<span class="label label-danger">Cancelled</span>';
			} else if ($st === 'REPORTED_TEXT' || $st === 'REPORTED_PDF' || $st === 'REPORTED_BOTH' || $st === 'REPORTED') {
				$status = '<span class="label label-success">Reported</span>';
			} else {
				$status = '<span class="label label-default">'.$st.'</span>';
			}
			$this->table->add_row(
				anchor('app/laboratory/results/' . $laboratory->io_lab_id . '/' . $laboratory->iop_id, $labsName),
				$laboratory->patient_no,
				$laboratory->patient_name,
				$this->birth_day_age($laboratory->birthday),
				$status,
				$laboratory->findings,
				$laboratory->result,
				$laboratory->dDate,
				''
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->data['pagination'] = '';
		$this->load->view('app/laboratory/request', $this->data);
	}


	public function request($offset = 0)
	{
		// Controller-level RBAC (do not rely on sidebar visibility):
		// Sonography staff should not access the Laboratory request list route.
		$hasLabAccess = (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory']);
		if (!$this->current_user_is_admin() && !$hasLabAccess) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$uri_segment = 5;
		$offset = $this->uri->segment($uri_segment);
		// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
		$iop_id = $this->segment_decoded(4);
		// print_r($offset);

		$laboratory = $this->laboratory_model->getAll($this->limit, $offset, $iop_id);
		// $laboratory = $this->laboratory_model->pending_lab_requests($this->limit, $offset);

		$config['base_url'] = base_url() . 'app/laboratory/resquest/';
		$config['total_rows'] = $this->laboratory_model->count_all();
		$config['per_page'] = $this->limit;


		$config['uri_segment'] = $uri_segment;
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';

		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';

		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';

		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';

		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';

		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';

		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';

		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Lab Request', 'Patient ID', 'Patient Name', 'Age', 'Findings', 'Results', 'Request Date', 'Action');
		$i = 0 + $offset;


		foreach ($laboratory as $laboratory) {
			if ($laboratory->particular_name) {
				$labs = $laboratory->particular_name;
			} else {
				$labs = $laboratory->laboratory_text;
			}
			// Deletion is disabled server-side; do not surface a destructive UI to non-admin users.
			$deleteForm = '';
			if ($this->current_user_is_admin()) {
				$deleteForm = '<form method="post" action="'.base_url().'app/laboratory/delete/'.$laboratory->io_lab_id.'" style="display:inline;" onsubmit="return confirm(\'Are you sure want to delete?\')">'
					.'<input type="hidden" name="'.$this->security->get_csrf_token_name().'" value="'.$this->security->get_csrf_hash().'">'
					.'<button type="submit" class="delete btn btn-xs btn-danger">Delete</button>'
					.'</form>';
			}
			$this->table->add_row(
				// $laboratory->io_lab_id, 
				anchor('app/laboratory/results/' . $laboratory->io_lab_id . '/' . $laboratory->iop_id, $labs),
				// $labs, 
				// $laboratory->particular_name,
				$laboratory->patient_no,
				$laboratory->patient_name,
				$this->birth_day_age($laboratory->birthday),
				$laboratory->findings,
				$laboratory->result,
				$laboratory->dDate,
				$deleteForm
				// anchor('app/complain/edit/'.$laboratory->io_lab_id,'Edit').'&nbsp|&nbsp;'.
				// anchor('app/complain/edit/'.$laboratory->io_lab_id,'Edit').'&nbsp|&nbsp;'.
				// anchor('app/complain/delete/'.$laboratory->io_lab_id,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();

		$this->load->view('app/laboratory/request', $this->data);
	}

	public function results()
	{
		// // user restriction function
		// 		$this->session->set_userdata('page_name','add_lab_results');
		// 		$page_id = $this->general_model->getPageID();
		// 		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		// 		if(General::has_rights_to_access($page_id->page_id,$userRole->user_role) == FALSE){
		// 			redirect(base_url().'access_denied');
		// 		}
		// 		// end of user restriction function

		$io_lab_id = (int)$this->uri->segment(4);
		$this->laboratory_model->install_imaging_tables();
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);

		// Keep Sonography workflow inside the Sonography controller to prevent
		// sonographers from being sent to /app/laboratory/request/... by the shared results view.
		$sonoCat = (string)$this->laboratory_model->get_sonography_category_id();
		if ($row && isset($row->category_id) && (string)$row->category_id === $sonoCat) {
			$iop = isset($row->iop_id) ? (string)$row->iop_id : '';
			if ($iop !== '') {
				redirect(base_url() . 'app/sonography/results/' . (int)$io_lab_id . '/' . rawurlencode($iop));
				return;
			}
		}

		if (!$this->user_can_read_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) {
			$this->laboratory_model->mark_delivered_if_needed($io_lab_id, $this->session->userdata('user_id'));
		}
		
		// Unified Service Gate - check via unified billing
		$iop_id = isset($row->iop_id) ? $row->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id);
		$this->data['service_gate'] = $gate;
		
		// Also check legacy payment status for backward compatibility
		$payStatus = $this->laboratory_model->get_lab_payment_status($io_lab_id);
		$this->data['payment_status'] = $payStatus;
		
		// Use unified gate status OR legacy status for gate badge
		$this->data['gate_status'] = $gate['status'];
		$this->data['gate_allowed'] = $gate['allowed'] || $payStatus['paid'];
		
		// Block unpaid tests (unless admin)
		$canProceed = $gate['allowed'] || $payStatus['paid'] || $this->current_user_is_admin();
		
		// Pass payment block flag to view - this will disable Save button
		$this->data['payment_blocked'] = !$canProceed && $this->user_can_write_lab_row($row);
		
		if (!$canProceed && $this->user_can_write_lab_row($row)) {
			$existingResult = isset($row->result) ? trim((string)$row->result) : '';
			if ($existingResult === '' || strtolower($existingResult) === 'uploaded') {
				// Show professional payment pending view instead of redirect with flash message
				$this->_load_payment_pending_view($row, $payStatus);
				return;
			}
			// Even if there's existing result, still block - add warning message
			log_message('info', 'LAB_PAYMENT_BLOCKED_VIEW io_lab_id='.$io_lab_id.' gate='.$gate['status'].' payStatus='.$payStatus['label']);
		}

		if ($this->user_can_write_lab_row($row)) {
			$this->laboratory_model->touch_in_progress_if_needed($io_lab_id, $this->session->userdata('user_id'));
			$this->laboratory_model->save_technician($io_lab_id, $this->session->userdata('user_id'));
		}
		$this->data['lab'] = $this->uri->segment(4);
		$lab_patient = $this->uri->segment(5);
		// Decode URL-encoded IOP ID (e.g., OP%20000002 -> OP 000002)
		if ($lab_patient !== false && $lab_patient !== null) {
			$lab_patient = urldecode($lab_patient);
		}
		$this->data['lab_patient'] = $lab_patient;
		// Get test name from database record instead of URL (URL no longer contains test name)
		$testName = '';
		if ($row) {
			if (isset($row->particular_name) && trim((string)$row->particular_name) !== '') {
				$testName = trim((string)$row->particular_name);
			} elseif (isset($row->laboratory_text) && trim((string)$row->laboratory_text) !== '') {
				$testName = trim((string)$row->laboratory_text);
			}
		}
		$this->data['lab_request_name'] = $testName;
		$this->data['lab_row'] = $row;
		$this->data['workflow'] = $this->laboratory_model->get_workflow_status($io_lab_id);
		$this->data['attachment_meta'] = $this->laboratory_model->get_latest_attachment_meta($io_lab_id);
		$this->data['is_read_only'] = !$this->user_can_write_lab_row($row);
		$this->data['module_base'] = 'laboratory';
		if ($row && isset($row->category_id) && (int)$row->category_id === 18) {
			$this->data['billing_status'] = $this->laboratory_model->get_sonography_billing_status($io_lab_id);
		}

		$this->load->view('app/laboratory/results', $this->data);
	}

	/**
	 * Load professional payment pending view
	 * Shows patient info, test details, and payment status in a user-friendly UI
	 */
	private function _load_payment_pending_view($row, $payStatus)
	{
		// Load patient information
		$patientName = 'N/A';
		$patientNo = isset($row->patient_no) ? (string)$row->patient_no : '';
		$iopId = isset($row->iop_id) ? (string)$row->iop_id : '';
		$patientAge = '';
		
		if ($patientNo !== '') {
			$patientInfo = $this->laboratory_model->get_patient_info_by_no($patientNo);
			if ($patientInfo) {
				$firstName = isset($patientInfo->firstname) ? trim((string)$patientInfo->firstname) : '';
				$lastName = isset($patientInfo->lastname) ? trim((string)$patientInfo->lastname) : '';
				$middleName = isset($patientInfo->middlename) ? trim((string)$patientInfo->middlename) : '';
				$patientName = trim($lastName . ' ' . $firstName . ' ' . $middleName);
				if ($patientName === '') {
					$patientName = isset($patientInfo->patient_name) ? (string)$patientInfo->patient_name : 'N/A';
				}
				
				// Calculate age
				if (isset($patientInfo->birthday) && $patientInfo->birthday) {
					$patientAge = $this->birth_day_age($patientInfo->birthday);
				}
			}
		}
		
		// Get test name
		$testName = '';
		if (isset($row->particular_name) && trim((string)$row->particular_name) !== '') {
			$testName = trim((string)$row->particular_name);
		} elseif (isset($row->laboratory_text) && trim((string)$row->laboratory_text) !== '') {
			$testName = trim((string)$row->laboratory_text);
		}
		
		// Get doctor name
		$doctorName = 'N/A';
		$doctorId = isset($row->doctor) ? (string)$row->doctor : '';
		if ($doctorId !== '') {
			$user = $this->laboratory_model->get_user_by_id($doctorId);
			if ($user) {
				$firstName = isset($user->firstname) ? trim((string)$user->firstname) : '';
				$lastName = isset($user->lastname) ? trim((string)$user->lastname) : '';
				$doctorName = trim($lastName . ' ' . $firstName);
				if ($doctorName === '') {
					$doctorName = isset($user->name) ? (string)$user->name : 'N/A';
				}
			}
		}
		
		// Format request date
		$requestDate = isset($row->dDate) ? (string)$row->dDate : '';
		if ($requestDate !== '') {
			$requestDate = date('F d, Y h:i A', strtotime($requestDate));
		}
		
		// Prepare data for view
		$viewData = array(
			'patient_name' => $patientName,
			'patient_no' => $patientNo,
			'iop_id' => $iopId,
			'patient_age' => $patientAge,
			'test_name' => $testName,
			'requested_by' => $doctorName,
			'request_date' => $requestDate,
			'payment_status_label' => isset($payStatus['label']) ? $payStatus['label'] : 'PENDING',
			'invoice_no' => isset($payStatus['invoice_no']) ? $payStatus['invoice_no'] : ''
		);
		
		$this->load->view('app/laboratory/payment_pending', $viewData);
	}

	public function lab_test_access()
	{
		echo "Lab access OK - hasAccesstoLaboratory=" . ($this->data['hasAccesstoLaboratory'] ? 'YES' : 'NO');
		exit;
	}

	public function lab_enquiry()
	{
		// Simple direct view load - no permission checks
		$this->session->set_userdata(array(
			'tab' => 'laboratory',
			'module' => 'lab_enquiry',
			'subtab' => '',
			'submodule' => ''
		));
		$this->load->view('app/laboratory/lab_enquiry', $this->data);
		return;

		// Debug: log that we reached this method
		log_message('info', 'LAB_ENQUIRY_REACHED user='.$this->session->userdata('username').' hasLab='.($this->data['hasAccesstoLaboratory'] ? 'Y' : 'N'));

		if (isset($_POST['btnSearch'])) {
			// echo "<script>alert('aassghh kjgj')</script>";
			// $this->data['reports_title'] = "OPD Patient Diagnosis Reports";
			// $this->data['patientInfo'] = $this->reports_model->patientInfo();
			// $this->data['patientvisited'] = $this->reports_model->patientvisited();	

			// if($this->input->post('cType') == "browser"){
			// 	$this->load->view('app/reports/patient_visited_result',$this->data);	
			// }else{
			// 	$this->load->helper('file');
			// 	$this->load->helper('dompdf');  

			// 	$html = $this->load->view('app/reports_result_pdf/patient_visited_result', $this->data, true);
			// 	pdf_create($html, 'patient_visited', TRUE);
			// }


			// $uri_segment = 4;
			// $offset = $this->uri->segment($uri_segment);

			// $laboratory = $this->laboratory_model->getAll($this->limit, $offset);
			// $laboratory_requests = $this->laboratory_model->pending_lab_requests($this->limit, $offset);
			// $laboratory_requests = $this->laboratory_model->lab_enquiry($this->limit, $offset);
			$laboratory_requests = $this->laboratory_model->lab_enquiry();

			// $config['base_url'] = base_url().'app/laboratory/lab_enquiry/';
			// $config['total_rows'] = count($laboratory_requests);
			// $config['per_page'] = $this->limit;

			// $config['uri_segment'] = $uri_segment;
			// $config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
			// $config['full_tag_close'] = '</ul><!--pagination-->';

			// $config['first_link'] = '&laquo; First';
			// $config['first_tag_open'] = '<li class="prev page">';
			// $config['first_tag_close'] = '</li>';

			// $config['last_link'] = 'Last &raquo;';
			// $config['last_tag_open'] = '<li class="next page">';
			// $config['last_tag_close'] = '</li>';

			// $config['next_link'] = 'Next &rarr;';
			// $config['next_tag_open'] = '<li class="next page">';
			// $config['next_tag_close'] = '</li>';

			// $config['prev_link'] = '&larr; Previous';
			// $config['prev_tag_open'] = '<li class="prev page">';
			// $config['prev_tag_close'] = '</li>';

			// $config['cur_tag_open'] = '<li class="active"><a href="">';
			// $config['cur_tag_close'] = '</a></li>';

			// $config['num_tag_open'] = '<li class="page">';
			// $config['num_tag_close'] = '</li>';

			// $this->pagination->initialize($config);
			// $this->data['pagination'] = $this->pagination->create_links();

			$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
			$this->table->set_template($tmpl);
			$this->table->set_empty("&nbsp;");
			$this->table->set_heading('Labs Request', 'Patient ID', 'Patient Name', 'Request Date');
			// $i = 0 + $offset;


			foreach ($laboratory_requests as $laboratory_requests) {
				// if($laboratory->particular_name){$labs = $laboratory->particular_name;}else{$labs = $laboratory->laboratory_text;}
				$this->table->add_row(
					// $laboratory_requests->iop_id,
					anchor('app/laboratory/request/' . $laboratory_requests->iop_id, $laboratory_requests->iop_id),
					// $labs, 
					// $laboratory->particular_name,
					$laboratory_requests->patient_no,
					$laboratory_requests->patient_name,
					$laboratory_requests->dDate
					// $laboratory->result
					// anchor('app/complain/edit/'.$laboratory_requests->io_lab_id,'Process')
					// anchor('app/complain/delete/'.$laboratory->io_lab_id,'Delete',array('class'=>'delete','onclick'=>"return confirm('Are you sure want to delete?')"))
				);
			}
			$this->data['message'] = $this->session->flashdata('message');
			$this->data['table'] = $this->table->generate();

			$this->load->view('app/laboratory/lab_enquiry', $this->data);
		} else {
			// Permission already controlled by hasAccesstoLaboratory in sidebar

			$this->session->set_userdata(array(
				'tab'			=>		'laboratory',
				'module'		=>		'lab_enquiry',
				'subtab'		=>		'',
				'submodule'	=>		''
			));

			// $this->data['lab_enquiry_list'] = $this->reports_model->patient_list();

			$this->load->view('app/laboratory/lab_enquiry', $this->data);
		}
	}

	// public function validate_complain(){
	// 	if($this->complain_model->validate_complain()){
	// 		$this->form_validation->set_message("validate_complain","Complain Name Already Exists.");
	// 		return false;
	// 	}else{
	// 		return true;
	// 	}
	// }

	// public function validate_complain_edit(){
	// 	if($this->complain_model->validate_complain_edit()){
	// 		$this->form_validation->set_message("validate_complain_edit","Complain Name Already Exists.");
	// 		return false;
	// 	}else{
	// 		return true;
	// 	}
	// }

	// public function save($id = null){
	// 	$this->form_validation->set_rules("result","Result","trim|xss_clean|required");
	// 	$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>","</div>");

	// 	if($this->form_validation->run()){

	// 		//save the data
	// 		$this->laboratory_model->save();

	// 		$value = $this->input->post('complain');
	// 		General::logfile('Complain','INSERT',$value);

	// 		$this->session->set_flashdata('message',"<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Complain successfully Added!</div>");

	// 		//redirect
	// 		redirect(base_url().'app/complain',$this->data);


	// 	}else{
	// 		$this->results();	
	// 	}

	// }


	public function save_result($io_lab_id = 0, $iop_id = '')
	{
		$io_lab_id = (int)$io_lab_id;
		// Check for POST submission - btnSubmit may not always be set depending on how form is submitted
		if ($this->input->method() === 'post') {
			$findings = $this->input->post('findings');
			$result = $this->input->post('result');
			if (empty($findings) && empty($result)) {
				redirect(base_url() . 'app/laboratory/request/' . $iop_id);
			} else {
				$this->edit_save();
			}
		} else {
			// Redirect to results page if accessed directly without POST
			redirect(base_url() . 'app/laboratory/results/' . $io_lab_id . '/' . $iop_id);
		}
	}

	public function edit_save()
	{
		$this->form_validation->set_rules("result", "Result", "trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>", "</div>");

		$io_lab_id = (int)$this->input->post('io_lab_id');
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}

		if (!$this->form_validation->run()) {
			$io_lab_id = (int)$this->input->post('io_lab_id');
			$iop_id = $this->input->post('iop_id');
			log_message('info', 'LAB_SAVE_VALIDATION_FAILED io_lab_id='.$io_lab_id.' errors='.validation_errors());
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>".validation_errors()."</div>");
			redirect(base_url() . 'app/laboratory/results/' . $io_lab_id . '/' . $iop_id);
			return;
		}
		$shadowMode = (bool)$this->config->item('lab_release_shadow_mode');
		if ($shadowMode) {
			$this->load->model('app/laboratory_release_model');
		}

		$this->laboratory_model->install_imaging_tables();
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$rowLocked) {
			$this->db->trans_rollback();
			redirect(base_url().'access_denied');
			return;
		}

		$iop_id = isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id);
		if ($this->lab_result_edit_locked($io_lab_id)) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Result is locked (already verified and delivered to doctor).</div>");
			redirect(base_url() . 'app/laboratory/results/' . (int)$io_lab_id . '/' . url_safe_id($iop_id));
			return;
		}
		if (!$gate['allowed'] && !$this->current_user_is_admin()) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'edit_save', $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Outstanding balance';
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> Lab result cannot be saved until payment is verified. Status: <strong>".$reason."</strong></div>");
			redirect(base_url().'app/laboratory/request/'.url_safe_id($iop_id));
			return;
		}

		$this->laboratory_model->edit_save();
		$this->laboratory_model->install_imaging_tables();
		$io_lab_id = $this->input->post('io_lab_id');
		$row = $this->db->get_where('iop_laboratory', array('io_lab_id' => (int)$io_lab_id))->row();
			$hasPdf = ($row && isset($row->lab_result_upload) && trim((string)$row->lab_result_upload) !== '');
			$status = $hasPdf ? 'REPORTED_BOTH' : 'REPORTED_TEXT';
			$this->laboratory_model->upsert_workflow_status($io_lab_id, $status, $this->session->userdata('user_id'));
			$this->laboratory_model->save_technician($io_lab_id, $this->session->userdata('user_id'));
			log_message('info', 'LAB_RESULT_PUBLISHED text io_lab_id='.(int)$io_lab_id);

			// === PATIENT SAFETY: Critical Value Detection ===
			$result_value = $this->input->post('result');
			$test_id = isset($row->laboratory_id) ? (int)$row->laboratory_id : 0;
			$test_name = isset($row->laboratory_text) ? $row->laboratory_text : '';
			$patient_no = isset($row->patient_no) ? $row->patient_no : '';
			$iop_id_val = isset($row->iop_id) ? $row->iop_id : '';
			$doctor_id = isset($row->doctor) ? $row->doctor : null;

			$critical_check = $this->diagnostic_safety_model->check_critical_value($test_id, $test_name, $result_value);
			$critical_msg = '';
			if ($critical_check) {
				$alert_id = $this->diagnostic_safety_model->create_critical_alert(
					$io_lab_id, $patient_no, $iop_id_val, $test_id, $test_name, $result_value, $critical_check, $doctor_id
				);
				// Mark workflow as requiring dual verification if critical
				if ($critical_check['requires_dual_verification']) {
					$this->db->where('io_lab_id', (int)$io_lab_id)->update('iop_laboratory_workflow', ['requires_dual_verification' => 1]);
				}
				$severity_class = in_array($critical_check['alert_severity'], ['PANIC', 'CRITICAL']) ? 'danger' : 'warning';
				$critical_msg = "<div class='alert alert-{$severity_class}'><i class='fa fa-exclamation-triangle'></i> <strong>CRITICAL VALUE ALERT:</strong> {$test_name} = {$result_value} is {$critical_check['alert_level']}. Ordering physician has been notified.</div>";
				log_message('error', 'CRITICAL_VALUE_DETECTED io_lab_id='.$io_lab_id.' level='.$critical_check['alert_level'].' value='.$result_value);
			}

			// === PATIENT SAFETY: Delta Check ===
			$delta_check = $this->diagnostic_safety_model->perform_delta_check($io_lab_id, $patient_no, $test_id, $test_name, $result_value);
			$delta_msg = '';
			if ($delta_check['flagged']) {
				$delta_msg = "<div class='alert alert-warning'><i class='fa fa-exclamation-circle'></i> <strong>DELTA ALERT:</strong> Result differs by {$delta_check['delta_percent']}% from previous value ({$delta_check['previous']}). Please review.</div>";
				log_message('info', 'DELTA_FLAG io_lab_id='.$io_lab_id.' delta='.$delta_check['delta_percent'].'%');
			}

			// === Audit Trail ===
			$this->diagnostic_safety_model->log_diagnostic_audit('RESULT_SAVED', 'iop_laboratory', $io_lab_id, $io_lab_id, $patient_no, null, $result_value);

			$value = $this->input->post('io_lab_id');
			General::logfile('Labs', 'UPDATE', $value);

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to save lab result.</div>");
				redirect(base_url() . 'app/laboratory/results/' . (int)$io_lab_id . '/' . $iop_id);
				return;
			}
			$this->db->trans_commit();
			if ($shadowMode && isset($this->laboratory_release_model) && method_exists($this->laboratory_release_model, 'save_result_draft_shadow')) {
				try {
					$user_id = (string)$this->session->userdata('user_id');
					$findings_value = $this->input->post('findings');
					$this->laboratory_release_model->save_result_draft_shadow((int)$io_lab_id, $findings_value, $result_value, $user_id, array(
						'action' => 'edit_save',
						'has_pdf' => $hasPdf ? 1 : 0,
						'workflow_status' => $status,
					));
				} catch (\Throwable $e) {
					log_message('error', 'LAB_RELEASE_SHADOW_WRITE_FAILED action=edit_save io_lab_id='.(int)$io_lab_id.' err='.$e->getMessage());
				}
			}
			$auditDecision = $gate;
			if (!$gate['allowed'] && $this->current_user_is_admin()) {
				$user_id = (string)$this->session->userdata('user_id');
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
			$this->audit_lab_gate_decision($io_lab_id, 'edit_save', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);

			$success_msg = "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Lab result successfully recorded!</div>";
			$this->session->set_flashdata('message', $critical_msg . $delta_msg . $success_msg);
			
			// Redirect to lab queue (index) so user can see test removed from pending list
			redirect(base_url() . 'app/laboratory/index');
	}


	public function upload_results($id)
	{
		$io_lab_id = (int)$id;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_read_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		
		// PAYMENT GATE: Block access to upload page if payment not verified
		$payStatus = $this->laboratory_model->get_lab_payment_status($io_lab_id);
		$iop_id = isset($row->iop_id) ? $row->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id);
		$canProceed = $gate['allowed'] || $payStatus['paid'] || $this->current_user_is_admin();
		
		if (!$canProceed && $this->user_can_write_lab_row($row)) {
			// Lab staff trying to access unpaid test - redirect to payment pending view
			log_message('info', 'LAB_UPLOAD_VIEW_BLOCKED io_lab_id='.$io_lab_id.' user='.$this->session->userdata('user_id'));
			$this->_load_payment_pending_view($row, $payStatus);
			return;
		}
		
		$this->data['lab'] = $id;
		$this->data['title'] = 'Upload Results';
		$this->data['lab_row'] = $row;
		$this->data['attachment_meta'] = $this->laboratory_model->get_latest_attachment_meta($io_lab_id);
		$this->data['is_read_only'] = !$this->user_can_write_lab_row($row);
		// Only show upload-related messages, not payment warnings from other pages
		$flashMsg = $this->session->flashdata('message');
		if ($flashMsg && strpos($flashMsg, 'Payment Required') !== false) {
			$flashMsg = ''; // Clear payment warnings - they're handled by the main results page
		}
		$this->data['message'] = $flashMsg;
		$this->load->view('app/laboratory/upload_results', $this->data);
	}


	public function upload_lab_result()
	{
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}

		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';

		$privateDir = $this->ensure_private_lab_result_dir();
		$config = array(
			'allowed_types'		=>		'pdf|jpg|jpeg|png',
			'upload_path'		=>		$privateDir,
			'max_size'			=>		3000
		);

		$this->load->library('upload', $config);
		// var_dump($this->load->library('upload', $config));

		if (!$this->upload->do_upload('result_upload')) {
			//$this->session->set_flashdata('message',"<div class='alert alert-block'><a class='close' data-dismiss='alert' href='#'>&times;</a>".$this->upload->display_errors()."</div>");

			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $this->upload->display_errors() . "</div>");
			redirect(base_url() . 'app/laboratory/upload_results/' . $this->input->post('io_lab_id'), $this->data);
		} else {

			$lab_data = $this->upload->data();
			$uploadedFullPath = (is_array($lab_data) && isset($lab_data['full_path'])) ? (string)$lab_data['full_path'] : '';

			$this->db->trans_begin();
			$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
			$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
			$iop_id_locked = $rowLocked && isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '';
			$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
			if ($this->lab_result_edit_locked($io_lab_id)) {
				$this->db->trans_rollback();
				if ($uploadedFullPath !== '') {
					@unlink($uploadedFullPath);
				}
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Result is locked (already verified and delivered to doctor).</div>");
				redirect(base_url().'app/laboratory/upload_results/'.$io_lab_id);
				return;
			}
			if (!$gate['allowed'] && !$this->current_user_is_admin()) {
				$this->db->trans_rollback();
				if ($uploadedFullPath !== '') {
					@unlink($uploadedFullPath);
				}
				$this->audit_lab_gate_decision($io_lab_id, 'upload_lab_result', $gate, 'LAB_GATE_BLOCKED');
				$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
				$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Outstanding balance';
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> Lab result cannot be uploaded until payment is verified. Status: <strong>".$reason."</strong></div>");
				redirect(base_url().'app/laboratory/upload_results/'.$io_lab_id);
				return;
			}

			$this->laboratory_model->upload_lab_result_pdf($lab_data, $io_lab_id, $this->session->userdata('user_id'));
			$this->laboratory_model->save_technician($io_lab_id, $this->session->userdata('user_id'));
			log_message('info', 'LAB_RESULT_PUBLISHED upload io_lab_id='.$io_lab_id);

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to save uploaded lab result.</div>");
				redirect(base_url() . 'app/laboratory/upload_results/' . (int)$io_lab_id);
				return;
			}
			$this->db->trans_commit();
			$auditDecision = $gate;
			if (!$gate['allowed'] && $this->current_user_is_admin()) {
				$user_id = (string)$this->session->userdata('user_id');
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
			$this->audit_lab_gate_decision($io_lab_id, 'upload_lab_result', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Lab result successfully Uploaded</div>");
			redirect(base_url() . 'app/laboratory/upload_results/' . $this->input->post('io_lab_id'), $this->data);
		}
	}

	public function birth_day_age($dob){
		// $dob='1993-07-01';
		$year = (date('Y') - date('Y',strtotime($dob)));
		return $year;
	}



	public function delete($id)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		// // user restriction function
		// $this->session->set_userdata('page_name', 'delete_lab_request');
		// $page_id = $this->general_model->getPageID();
		// $userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		// if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
		// 	redirect(base_url() . 'access_denied');
		// }
		// // end of user restriction function

		$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Deletion of laboratory requests is disabled to protect clinical records.</div>");
		redirect(base_url() . 'app/laboratory/index', $this->data);
	}

	
	public function delete_main($id)
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		// // user restriction function
		// $this->session->set_userdata('page_name', 'delete_lab_request');
		// $page_id = $this->general_model->getPageID();
		// $userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		// if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
		// 	redirect(base_url() . 'access_denied');
		// }
		// // end of user restriction function

		$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Deletion of laboratory requests is disabled to protect clinical records.</div>");
		redirect(base_url() . 'app/laboratory/index', $this->data);
	}

	/* ================================================================== */
	/*  ENHANCED LAB QUEUE                                                */
	/* ================================================================== */

	public function lab_queue($offset = 0){
		// Permission check - lab staff, admin, and doctors can access
		$hasLabAccess = isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory'];
		$hasAdminAccess = isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'];
		$hasDoctorAccess = isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor'];
		if (!$hasLabAccess && !$hasAdminAccess && !$hasDoctorAccess) {
			redirect(base_url() . 'access_denied');
		}

		$this->session->set_userdata(array(
			'tab' => 'laboratory', 'module' => 'lab_queue', 'subtab' => '', 'submodule' => ''
		));

		$uri_segment = 4;
		$offset = (int)$this->uri->segment($uri_segment);
		$status_filter = $this->input->get('status') ? strtoupper(trim($this->input->get('status'))) : 'PENDING';

		$limit = 20;
		$labs = $this->laboratory_model->get_lab_queue($limit, $offset, $status_filter);
		$total = $this->laboratory_model->count_lab_queue($status_filter);

		// Get payment + workflow maps
		$ioIds = array();
		foreach ($labs as $l) { $ioIds[] = $l->io_lab_id; }
		$paymentMap = $this->laboratory_model->get_lab_payment_status_map($ioIds);
		$workflowMap = $this->laboratory_model->get_workflow_map($ioIds);
		$rawGateMap = array();
		foreach ($labs as $l) {
			$io = isset($l->io_lab_id) ? (int)$l->io_lab_id : 0;
			if ($io <= 0) continue;
			$iop = isset($l->iop_id) ? (string)$l->iop_id : null;
			$pno = isset($l->patient_no) ? (string)$l->patient_no : null;
			$rawGateMap[(string)$io] = (isset($this->service_gate) && method_exists($this->service_gate, 'check_service_raw'))
				? $this->service_gate->check_service_raw('LAB', (string)$io, $iop, $pno)
				: $this->service_gate->check_lab_gate($io, $iop);
		}

		$config['base_url'] = base_url() . 'app/laboratory/lab_queue/';
		$config['total_rows'] = $total;
		$config['per_page'] = $limit;
		$config['uri_segment'] = $uri_segment;
		$config['suffix'] = $status_filter !== 'PENDING' ? '?status='.$status_filter : '';
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul>';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);

		$this->data['page_title'] = 'Lab Queue';
		$this->data['labs'] = $labs;
		$this->data['payment_map'] = $paymentMap;
		$this->data['workflow_map'] = $workflowMap;
		$this->data['raw_gate_map'] = $rawGateMap;
		$this->data['status_filter'] = $status_filter;
		$this->data['total_pending'] = $this->laboratory_model->count_lab_queue('PENDING');
		$this->data['total_completed'] = $this->laboratory_model->count_lab_queue('COMPLETED');
		$this->data['total_urgent'] = $this->_count_urgent_labs();
		$this->data['total_abnormal'] = $this->_count_abnormal_results();
		$this->data['total_deferred'] = $this->laboratory_model->count_deferred_labs();
		$this->data['total_external'] = $this->laboratory_model->count_external_labs();
		$this->data['orphaned_count'] = $hasAdminAccess ? (int)$this->laboratory_model->count_orphaned_lab_requests() : 0;
		$this->data['pagination'] = $this->pagination->create_links();
		$this->data['message'] = $this->session->flashdata('message');

		$this->load->model('app/cashier_model');
		$this->data['dispatch_notifications'] = $this->cashier_model->get_pending_dept_notifications('LABORATORY');

		$this->load->view('app/laboratory/lab_queue', $this->data);
	}

	public function orphaned_lab_requests($offset = 0){
		$hasAdminAccess = isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'];
		if (!$hasAdminAccess) {
			redirect(base_url() . 'access_denied');
		}

		$this->session->set_userdata(array(
			'tab' => 'laboratory', 'module' => 'orphaned_lab_requests', 'subtab' => '', 'submodule' => ''
		));

		$uri_segment = 4;
		$offset = (int)$this->uri->segment($uri_segment);
		$limit = 50;
		$rows = $this->laboratory_model->get_orphaned_lab_requests($limit, $offset);
		$total = $this->laboratory_model->count_orphaned_lab_requests();

		$config['base_url'] = base_url() . 'app/laboratory/orphaned_lab_requests/';
		$config['total_rows'] = $total;
		$config['per_page'] = $limit;
		$config['uri_segment'] = $uri_segment;
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul>';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);

		$this->data['page_title'] = 'Orphaned Lab Requests';
		$this->data['rows'] = $rows;
		$this->data['total'] = $total;
		$this->data['pagination'] = $this->pagination->create_links();
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/orphaned_lab_requests', $this->data);
	}

	public function backfill_order_master($offset = 0){
		$hasAdminAccess = isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'];
		if (!$hasAdminAccess) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$uri_segment = 4;
		$offset = (int)$this->uri->segment($uri_segment);
		$limit = (int)$this->input->get('limit');
		if ($limit <= 0) { $limit = 500; }
		if ($limit > 2000) { $limit = 2000; }
		$run = $this->laboratory_model->backfill_order_master($limit, $offset);
		$total = (int)$this->laboratory_model->count_order_master_backfill_candidates();
		$processed = isset($run['processed']) ? (int)$run['processed'] : 0;
		$next_offset = $offset + $processed;
		$has_more = ($processed > 0 && $next_offset < $total);
		$out = array(
			'ok' => true,
			'offset' => $offset,
			'limit' => $limit,
			'processed' => $processed,
			'total' => $total,
			'next_offset' => $next_offset,
			'has_more' => $has_more,
		);
		$this->output->set_content_type('application/json');
		$this->output->set_output(json_encode($out));
	}

	public function mark_complete($io_lab_id = 0){
		$io_lab_id = (int)$io_lab_id;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}

		$isAdmin = $this->current_user_is_admin();

		$user_id = (string)$this->session->userdata('user_id');
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$rowLocked) {
			$this->db->trans_rollback();
			redirect(base_url().'access_denied');
			return;
		}

		$iop_id_locked = isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		if (!$gate['allowed'] && !$isAdmin) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'mark_complete', $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required';
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Payment Required:</strong> Cannot mark as complete until payment is verified. <strong>".htmlspecialchars($reason)."</strong></div>");
			$returnTo = $this->input->get('return') ? $this->input->get('return') : 'lab_queue';
			redirect(base_url().'app/laboratory/'.$returnTo);
			return;
		}

		$ok = $this->laboratory_model->mark_lab_completed($io_lab_id, $user_id);
		if (!$ok || $this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cannot mark as complete — results must be reported first.</div>");
			$returnTo = $this->input->get('return') ? $this->input->get('return') : 'lab_queue';
			redirect(base_url().'app/laboratory/'.$returnTo);
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
		$this->audit_lab_gate_decision($io_lab_id, 'mark_complete', $auditDecision, 'LAB_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Test marked as completed/verified.</div>");
		$returnTo = $this->input->get('return') ? $this->input->get('return') : 'lab_queue';
		redirect(base_url().'app/laboratory/'.$returnTo);
	}

	/* ================================================================== */
	/*  IMAGING MODULE (X-ray, ECG, Sonography)                           */
	/* ================================================================== */

	public function imaging($type = 'sonography'){
		$type = strtolower(trim((string)$type));
		$types = $this->laboratory_model->get_imaging_types();
		if (!isset($types[$type])) {
			redirect(base_url().'app/laboratory/imaging/sonography');
			return;
		}

		// Sonography has its own existing page — redirect unless we're in imaging mode
		$this->session->set_userdata('page_name', 'access_laboratory_module');
		$page_id = $this->general_model->getPageID();
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
			redirect(base_url() . 'access_denied');
		}

		$this->session->set_userdata(array(
			'tab' => 'laboratory', 'module' => 'imaging_'.$type, 'subtab' => '', 'submodule' => ''
		));

		$uri_segment = 5;
		$offset = (int)$this->uri->segment($uri_segment);
		$status_filter = $this->input->get('status') ? strtoupper(trim($this->input->get('status'))) : 'PENDING';
		$limit = 20;

		$labs = $this->laboratory_model->get_imaging_queue($type, $limit, $offset, $status_filter);
		$total = $this->laboratory_model->count_imaging_queue($type, $status_filter);

		// Payment + workflow maps
		$ioIds = array();
		foreach ($labs as $l) { $ioIds[] = $l->io_lab_id; }
		$paymentMap = $this->laboratory_model->get_lab_payment_status_map($ioIds);
		$workflowMap = $this->laboratory_model->get_workflow_map($ioIds);

		$config['base_url'] = base_url() . 'app/laboratory/imaging/'.$type.'/';
		$config['total_rows'] = $total;
		$config['per_page'] = $limit;
		$config['uri_segment'] = $uri_segment;
		$config['suffix'] = $status_filter !== 'PENDING' ? '?status='.$status_filter : '';
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul>';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);

		$this->data['page_title'] = $types[$type]['label'];
		$this->data['imaging_type'] = $type;
		$this->data['imaging_types'] = $types;
		$this->data['labs'] = $labs;
		$this->data['payment_map'] = $paymentMap;
		$this->data['workflow_map'] = $workflowMap;
		$this->data['status_filter'] = $status_filter;
		$this->data['imaging_summary'] = $this->laboratory_model->get_imaging_summary();
		$this->data['pagination'] = $this->pagination->create_links();
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/imaging_queue', $this->data);
	}

	public function imaging_request($iop_id = ''){
		// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
		$iop_id = (string)$this->segment_decoded(4);
		$type = (string)$this->uri->segment(5);
		if ($type === '') $type = 'sonography';
		$type = strtolower(trim($type));

		// Sonography should be handled by the Sonography module/controller.
		// This prevents sonography staff from drifting into Laboratory request/results routes.
		if ($type === 'sonography') {
			redirect(base_url() . 'app/sonography/request/' . rawurlencode((string)$iop_id));
			return;
		}

		// Basic controller RBAC: only allow imaging request listing for users who have
		// lab/imaging access, not arbitrary logged-in users.
		$hasLabAccess = (isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory']);
		$hasSonoAccess = (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']);
		if (!$this->current_user_is_admin() && !$hasLabAccess && !$hasSonoAccess) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$types = $this->laboratory_model->get_imaging_types();
		$catId = isset($types[$type]) ? (int)$types[$type]['id'] : 18;

		$this->data['page_title'] = isset($types[$type]) ? $types[$type]['label'] . ' Requests' : 'Imaging Requests';

		$sql = "SELECT l.*, CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,
				COALESCE(NULLIF(TRIM(l.laboratory_text),''), bp.particular_name, 'Unknown Test') AS particular_name,
				ppi.patient_no, ppi.birthday, l.laboratory_text
			FROM iop_laboratory l
			JOIN patient_details_iop pd ON pd.IO_ID = l.iop_id
			JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
			LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id
			WHERE l.iop_id = ? AND l.InActive = 0 AND l.category_id = ?
			ORDER BY l.dDate DESC";
		$labs = $this->db->query($sql, array($iop_id, $catId))->result();

		$ioIds = array();
		foreach ($labs as $l) { $ioIds[] = $l->io_lab_id; }
		$this->data['workflow_map'] = $this->laboratory_model->get_workflow_map($ioIds);
		$this->data['payment_map'] = $this->laboratory_model->get_lab_payment_status_map($ioIds);

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Test', 'Patient ID', 'Patient Name', 'Age', 'Status', 'Payment', 'Findings', 'Results', 'Date', 'Action');
		foreach ($labs as $lab) {
			$labsName = $lab->particular_name ? $lab->particular_name : $lab->laboratory_text;
			$wf = isset($this->data['workflow_map'][(string)$lab->io_lab_id]) ? $this->data['workflow_map'][(string)$lab->io_lab_id] : null;
			$pay = isset($this->data['payment_map'][(string)$lab->io_lab_id]) ? $this->data['payment_map'][(string)$lab->io_lab_id] : null;

			$st = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : ((trim((string)$lab->result) === '') ? 'REQUESTED' : 'REPORTED');
			$statusHtml = $this->_workflow_badge($st);
			$payHtml = $pay ? $this->_payment_badge($pay) : '<span class="label label-default">N/A</span>';

			$this->table->add_row(
				anchor('app/laboratory/results/'.$lab->io_lab_id.'/'.$lab->iop_id, $labsName),
				$lab->patient_no,
				$lab->patient_name,
				$this->birth_day_age($lab->birthday),
				$statusHtml,
				$payHtml,
				$lab->findings,
				$lab->result,
				$lab->dDate,
				$this->_imaging_action_links($lab, $wf, $pay)
			);
		}
		$this->data['message'] = $this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->data['pagination'] = '';
		$this->data['imaging_type'] = $type;
		$this->load->view('app/laboratory/request', $this->data);
	}

	/* ================================================================== */
	/*  HELPER: Badge rendering                                           */
	/* ================================================================== */

	private function _workflow_badge($status){
		$st = strtoupper(trim((string)$status));
		if ($st === 'REQUESTED') return '<span class="label label-info">Requested</span>';
		if ($st === 'IN_PROGRESS') return '<span class="label label-warning">In Progress</span>';
		if ($st === 'CANCELLED') return '<span class="label label-danger">Cancelled</span>';
		if (in_array($st, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'))) return '<span class="label label-success">Reported</span>';
		if ($st === 'VERIFIED') return '<span class="label label-primary">Verified</span>';
		if ($st === '') return '<span class="label label-default">New</span>';
		return '<span class="label label-default">'.$st.'</span>';
	}

	private function _payment_badge($pay){
		if (!$pay) return '<span class="label label-default">N/A</span>';
		$label = isset($pay['label']) ? $pay['label'] : 'Unknown';
		$paid = isset($pay['paid']) ? $pay['paid'] : false;
		if ($paid) return '<span class="label label-success">'.$label.'</span>';
		if (strpos($label, 'Partial') !== false) return '<span class="label label-warning">'.$label.'</span>';
		if ($label === 'No Invoice') return '<span class="label label-default">'.$label.'</span>';
		return '<span class="label label-danger">'.$label.'</span>';
	}

	private function _imaging_action_links($lab, $wf, $pay){
		$st = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		$reported = in_array($st, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'));
		$links = '';
		$links .= anchor('app/laboratory/results/'.$lab->io_lab_id.'/'.$lab->iop_id, '<i class="fa fa-pencil"></i> Enter Result', array('class'=>'btn btn-xs btn-primary'));
		if ($reported) {
			$links .= ' '.anchor('app/laboratory/mark_complete/'.$lab->io_lab_id, '<i class="fa fa-check"></i> Complete', array('class'=>'btn btn-xs btn-success', 'onclick'=>"return confirm('Mark as completed?')"));
		}
		return $links;
	}

	/* ================================================================== */
	/*  URGENT / ABNORMAL HELPERS                                          */
	/* ================================================================== */

	private function _count_urgent_labs(){
		// Urgency column not implemented in current schema - return 0
		// TODO: Add urgency column to iop_laboratory if urgent lab tracking is needed
		return 0;
	}

	private function _count_abnormal_results(){
		if (!$this->db->table_exists('lab_result_entries')) return 0;
		// Only count abnormal results that are NOT yet verified/completed
		$sql = "SELECT COUNT(DISTINCT e.io_lab_id) AS cnt 
				FROM lab_result_entries e
				LEFT JOIN iop_laboratory_workflow w ON w.io_lab_id = e.io_lab_id AND w.InActive = 0
				WHERE e.result_flag IN ('high','critical','low','abnormal')
				  AND e.InActive = 0
				  AND e.entered_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
				  AND (w.status IS NULL OR w.status NOT IN ('VERIFIED','COMPLETED'))";
		$q = $this->db->query($sql);
		if (!$q) return 0;
		$row = $q->row();
		return $row ? (int)$row->cnt : 0;
	}

	/* ── Lab Flexible Workflow Endpoints ────────────────────────── */

	public function mark_lab_external()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		require_role(array('admin', 'senior_nurse', 'billing_manager'));
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$reason    = trim((string)$this->input->post('reason'));
		$user_id   = (string)$this->session->userdata('user_id');
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		$iop_id_locked = $rowLocked && isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : $iop_id;
		$patient_no_locked = $rowLocked && isset($rowLocked->patient_no) ? (string)$rowLocked->patient_no : null;
		if ($this->lab_payment_confirmed_raw($io_lab_id, $iop_id_locked, $patient_no_locked)) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_external', array(
				'allowed' => false,
				'decision_type' => 'POLICY_BLOCK',
				'raw_gate' => array('allowed' => true, 'reason' => 'Payment confirmed (raw)'),
				'override_context' => array('type' => 'EXTERNAL_LAB', 'justification' => $reason),
				'reason' => 'Policy: cannot mark external after payment confirmed'
			), 'LAB_GATE_BLOCKED');
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cannot mark as External: payment is already confirmed for this test.</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		$result = $this->laboratory_model->mark_lab_external($io_lab_id, $user_id, $reason);
		if ($result && isset($result['ok']) && $result['ok'] && is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) {
			$ex = $this->service_gate->create_exception_system('LAB', (string)(int)$io_lab_id, 'WAIVER', $reason !== '' ? $reason : 'External lab referral', $user_id, $patient_no_locked, $iop_id_locked);
			if (is_array($ex) && isset($ex['success']) && !$ex['success']) {
				$this->db->trans_rollback();
				$result = array('ok' => false, 'error' => isset($ex['error']) ? $ex['error'] : 'Failed to create gate exception');
			}
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$result = array('ok' => false, 'error' => 'Transaction failed');
		} else {
			$this->db->trans_commit();
		}
		if ($result['ok']) {
			$auditDecision = array(
				'allowed' => true,
				'decision_type' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'BYPASSED_OVERRIDE' : 'RAW',
				'raw_gate' => $gate,
				'override_context' => array(
					'type' => 'EXTERNAL_LAB',
					'justification' => $reason,
					'approved_by' => $user_id !== '' ? (int)$user_id : null,
				),
				'reason' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'Override endpoint' : (isset($gate['reason']) ? $gate['reason'] : null),
			);
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_external', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		}
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Lab test marked as External Referral. Patient to obtain test at external lab.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	public function mark_lab_unable_to_pay()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		require_role(array('admin', 'senior_nurse', 'billing_manager'));
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$reason    = trim((string)$this->input->post('reason'));
		$user_id   = (string)$this->session->userdata('user_id');
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		$iop_id_locked = $rowLocked && isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : $iop_id;
		$patient_no_locked = $rowLocked && isset($rowLocked->patient_no) ? (string)$rowLocked->patient_no : null;
		if ($this->lab_payment_confirmed_raw($io_lab_id, $iop_id_locked, $patient_no_locked)) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_unable_to_pay', array(
				'allowed' => false,
				'decision_type' => 'POLICY_BLOCK',
				'raw_gate' => array('allowed' => true, 'reason' => 'Payment confirmed (raw)'),
				'override_context' => array('type' => 'UNABLE_TO_PAY', 'justification' => $reason),
				'reason' => 'Policy: cannot mark unable-to-pay after payment confirmed'
			), 'LAB_GATE_BLOCKED');
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cannot mark Unable to Pay: payment is already confirmed for this test.</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		$result = $this->laboratory_model->mark_lab_unable_to_pay($io_lab_id, $user_id, $reason);
		if ($result && isset($result['ok']) && $result['ok'] && is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) {
			$ex = $this->service_gate->create_exception_system('LAB', (string)(int)$io_lab_id, 'STAFF', $reason !== '' ? $reason : 'Unable to pay', $user_id, $patient_no_locked, $iop_id_locked);
			if (is_array($ex) && isset($ex['success']) && !$ex['success']) {
				$this->db->trans_rollback();
				$result = array('ok' => false, 'error' => isset($ex['error']) ? $ex['error'] : 'Failed to create gate exception');
			}
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$result = array('ok' => false, 'error' => 'Transaction failed');
		} else {
			$this->db->trans_commit();
		}
		if ($result['ok']) {
			$auditDecision = array(
				'allowed' => true,
				'decision_type' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'BYPASSED_OVERRIDE' : 'RAW',
				'raw_gate' => $gate,
				'override_context' => array(
					'type' => 'UNABLE_TO_PAY',
					'justification' => $reason,
					'approved_by' => $user_id !== '' ? (int)$user_id : null,
				),
				'reason' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'Override endpoint' : (isset($gate['reason']) ? $gate['reason'] : null),
			);
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_unable_to_pay', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		}
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-warning">Lab test marked Unable to Pay. Outstanding balance recorded. Test can proceed.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	public function mark_lab_deferred()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		require_role(array('admin', 'senior_nurse', 'billing_manager'));
		$io_lab_id   = (int)$this->input->post('io_lab_id');
		$reason      = trim((string)$this->input->post('reason'));
		$defer_until = trim((string)$this->input->post('defer_until'));
		$user_id     = (string)$this->session->userdata('user_id');
		if ($defer_until === '') $defer_until = null;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		$iop_id_locked = $rowLocked && isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : $iop_id;
		$patient_no_locked = $rowLocked && isset($rowLocked->patient_no) ? (string)$rowLocked->patient_no : null;
		if ($this->lab_payment_confirmed_raw($io_lab_id, $iop_id_locked, $patient_no_locked)) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_deferred', array(
				'allowed' => false,
				'decision_type' => 'POLICY_BLOCK',
				'raw_gate' => array('allowed' => true, 'reason' => 'Payment confirmed (raw)'),
				'override_context' => array('type' => 'DEFERRED', 'justification' => $reason, 'defer_until' => $defer_until),
				'reason' => 'Policy: cannot defer payment after payment confirmed'
			), 'LAB_GATE_BLOCKED');
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cannot defer payment: payment is already confirmed for this test.</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		$result = $this->laboratory_model->mark_lab_deferred($io_lab_id, $user_id, $reason, $defer_until);
		if ($result && isset($result['ok']) && $result['ok'] && is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) {
			$exReason = $reason !== '' ? $reason : 'Payment deferred';
			if ($defer_until) {
				$exReason .= ' (until ' . $defer_until . ')';
			}
			$ex = $this->service_gate->create_exception_system('LAB', (string)(int)$io_lab_id, 'DEFERRED', $exReason, $user_id, $patient_no_locked, $iop_id_locked);
			if (is_array($ex) && isset($ex['success']) && !$ex['success']) {
				$this->db->trans_rollback();
				$result = array('ok' => false, 'error' => isset($ex['error']) ? $ex['error'] : 'Failed to create gate exception');
			}
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$result = array('ok' => false, 'error' => 'Transaction failed');
		} else {
			$this->db->trans_commit();
		}
		if ($result['ok']) {
			$auditDecision = array(
				'allowed' => true,
				'decision_type' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'BYPASSED_OVERRIDE' : 'RAW',
				'raw_gate' => $gate,
				'override_context' => array(
					'type' => 'DEFERRED',
					'justification' => $reason,
					'defer_until' => $defer_until,
					'approved_by' => $user_id !== '' ? (int)$user_id : null,
				),
				'reason' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'Override endpoint' : (isset($gate['reason']) ? $gate['reason'] : null),
			);
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_deferred', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		}
		if ($result['ok']) {
			$due = $defer_until ? ' (due '.$defer_until.')' : '';
			$this->session->set_flashdata('message', '<div class="alert alert-info">Lab payment deferred'.$due.'. Test can proceed. Cashier notified.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	public function mark_lab_emergency()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		require_role(array('admin', 'senior_nurse', 'billing_manager'));
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$reason    = trim((string)$this->input->post('reason'));
		$user_id   = (string)$this->session->userdata('user_id');
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			redirect(base_url().'access_denied');
			return;
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		$iop_id_locked = $rowLocked && isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : $iop_id;
		$patient_no_locked = $rowLocked && isset($rowLocked->patient_no) ? (string)$rowLocked->patient_no : null;
		if ($this->lab_payment_confirmed_raw($io_lab_id, $iop_id_locked, $patient_no_locked)) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_emergency', array(
				'allowed' => false,
				'decision_type' => 'POLICY_BLOCK',
				'raw_gate' => array('allowed' => true, 'reason' => 'Payment confirmed (raw)'),
				'override_context' => array('type' => 'EMERGENCY', 'justification' => $reason),
				'reason' => 'Policy: cannot apply emergency override after payment confirmed'
			), 'LAB_GATE_BLOCKED');
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cannot apply Emergency Override: payment is already confirmed for this test.</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		$result = $this->laboratory_model->mark_lab_emergency($io_lab_id, $user_id, $reason);
		if ($result && isset($result['ok']) && $result['ok'] && is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) {
			$ex = $this->service_gate->create_exception_system('LAB', (string)(int)$io_lab_id, 'EMERGENCY', $reason !== '' ? $reason : 'Emergency override', $user_id, $patient_no_locked, $iop_id_locked);
			if (is_array($ex) && isset($ex['success']) && !$ex['success']) {
				$this->db->trans_rollback();
				$result = array('ok' => false, 'error' => isset($ex['error']) ? $ex['error'] : 'Failed to create gate exception');
			}
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$result = array('ok' => false, 'error' => 'Transaction failed');
		} else {
			$this->db->trans_commit();
		}
		if ($result['ok']) {
			$auditDecision = array(
				'allowed' => true,
				'decision_type' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'BYPASSED_OVERRIDE' : 'RAW',
				'raw_gate' => $gate,
				'override_context' => array(
					'type' => 'EMERGENCY',
					'justification' => $reason,
					'approved_by' => $user_id !== '' ? (int)$user_id : null,
				),
				'reason' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'Emergency override' : (isset($gate['reason']) ? $gate['reason'] : null),
			);
			$this->audit_lab_gate_decision($io_lab_id, 'mark_lab_emergency', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		}
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger"><strong>Emergency Override.</strong> Lab test proceeds without payment. Audit logged.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	public function request_lab_waiver()
	{
		if ($this->input->method(TRUE) !== 'POST') redirect(base_url().'access_denied');
		require_role(array('admin', 'senior_nurse', 'billing_manager'));
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$reason    = trim((string)$this->input->post('reason'));
		$user_id   = (string)$this->session->userdata('user_id');
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$this->user_can_write_lab_row($row)) {
			redirect(base_url().'access_denied');
			return;
		}
		$iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		$iop_id_locked = $rowLocked && isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : $iop_id;
		$patient_no_locked = $rowLocked && isset($rowLocked->patient_no) ? (string)$rowLocked->patient_no : null;
		if ($this->lab_payment_confirmed_raw($io_lab_id, $iop_id_locked, $patient_no_locked)) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'request_lab_waiver', array(
				'allowed' => false,
				'decision_type' => 'POLICY_BLOCK',
				'raw_gate' => array('allowed' => true, 'reason' => 'Payment confirmed (raw)'),
				'override_context' => array('type' => 'WAIVER', 'justification' => $reason),
				'reason' => 'Policy: cannot request waiver after payment confirmed'
			), 'LAB_GATE_BLOCKED');
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Cannot request waiver: payment is already confirmed for this test.</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		$result = $this->laboratory_model->request_lab_waiver($io_lab_id, $user_id, $reason);
		if ($result && isset($result['ok']) && $result['ok'] && is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) {
			$ex = $this->service_gate->create_exception_system('LAB', (string)(int)$io_lab_id, 'WAIVER', $reason !== '' ? $reason : 'Waiver requested', $user_id, $patient_no_locked, $iop_id_locked);
			if (is_array($ex) && isset($ex['success']) && !$ex['success']) {
				$this->db->trans_rollback();
				$result = array('ok' => false, 'error' => isset($ex['error']) ? $ex['error'] : 'Failed to create gate exception');
			}
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$result = array('ok' => false, 'error' => 'Transaction failed');
		} else {
			$this->db->trans_commit();
		}
		if ($result['ok']) {
			$auditDecision = array(
				'allowed' => true,
				'decision_type' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'BYPASSED_OVERRIDE' : 'RAW',
				'raw_gate' => $gate,
				'override_context' => array(
					'type' => 'WAIVER',
					'justification' => $reason,
					'approved_by' => $user_id !== '' ? (int)$user_id : null,
				),
				'reason' => (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) ? 'Override endpoint' : (isset($gate['reason']) ? $gate['reason'] : null),
			);
			$this->audit_lab_gate_decision($io_lab_id, 'request_lab_waiver', $auditDecision, 'LAB_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		}
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-info">Waiver request submitted. Awaiting Admin/Super-Admin approval.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	public function upload_external_result($io_lab_id = 0)
	{
		$io_lab_id = (int)$io_lab_id;
		$user_id   = (string)$this->session->userdata('user_id');

		if ($this->input->method(TRUE) !== 'POST') {
			$this->data['io_lab_id'] = $io_lab_id;
			$this->data['message']   = $this->session->flashdata('message');
			$this->load->view('app/laboratory/upload_external_result', $this->data);
			return;
		}

		$notes    = trim((string)$this->input->post('notes'));
		$filePath = '';

		if (isset($_FILES['result_file']) && $_FILES['result_file']['error'] === UPLOAD_ERR_OK) {
			$dir = $this->ensure_private_lab_result_dir();
			$ext = strtolower(pathinfo($_FILES['result_file']['name'], PATHINFO_EXTENSION));
			$allowed = array('pdf','jpg','jpeg','png');
			if (!in_array($ext, $allowed)) {
				$this->session->set_flashdata('message', '<div class="alert alert-danger">Invalid file type. Only PDF, JPG, PNG allowed.</div>');
				redirect(base_url().'app/laboratory/upload_external_result/'.$io_lab_id);
				return;
			}
			$fileName = 'lab_ext_'.$io_lab_id.'_'.time().'.'.$ext;
			if (!move_uploaded_file($_FILES['result_file']['tmp_name'], $dir.DIRECTORY_SEPARATOR.$fileName)) {
				$this->session->set_flashdata('message', '<div class="alert alert-danger">File upload failed. Please try again.</div>');
				redirect(base_url().'app/laboratory/upload_external_result/'.$io_lab_id);
				return;
			}
			$filePath = $fileName;
		}

		$result = $this->laboratory_model->upload_external_result($io_lab_id, $user_id, $filePath, $notes);
		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">External result uploaded successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Error: '.$result['error'].'</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	/* ================================================================== */
	/*  PATIENT SAFETY: Critical Alerts Dashboard                          */
	/* ================================================================== */

	public function critical_alerts()
	{
		if (!$this->current_user_is_admin() && !isset($this->data['hasAccesstoLaboratory'])) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->data['title'] = 'Critical Value Alerts';
		$this->data['pending_alerts'] = $this->diagnostic_safety_model->get_pending_alerts(null, 100);
		$this->data['alert_count'] = $this->diagnostic_safety_model->count_pending_alerts();
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/critical_alerts', $this->data);
	}

	public function acknowledge_alert($alert_id = 0)
	{
		$alert_id = (int)$alert_id;
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url().'app/laboratory/critical_alerts');
			return;
		}

		$notes = trim((string)$this->input->post('notes'));
		$result = $this->diagnostic_safety_model->acknowledge_critical_alert($alert_id, $notes);

		if ($result) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Alert acknowledged successfully.</div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to acknowledge alert.</div>');
		}
		redirect(base_url().'app/laboratory/critical_alerts');
	}

	/* ================================================================== */
	/*  PATIENT SAFETY: Dual Verification                                  */
	/* ================================================================== */

	public function verify_result($io_lab_id = 0, $level = 1)
	{
		$io_lab_id = (int)$io_lab_id;
		$level = (int)$level;

		if (!$this->current_user_is_admin() && !isset($this->data['hasAccesstoLaboratory'])) {
			redirect(base_url().'access_denied');
			return;
		}

		$notes = trim((string)$this->input->post('notes'));
		$user_id = (string)$this->session->userdata('user_id');

		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$rowLocked) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Verification failed: Record not found</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}

		$iop_id_locked = isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		if (!$gate['allowed'] && !$this->current_user_is_admin()) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'verify_result_level_' . (int)$level, $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Payment required: ' . (isset($gate['reason']) ? $gate['reason'] : 'Outstanding balance') . '</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}

		if ($level === 1) {
			$result = $this->diagnostic_safety_model->verify_level_1($io_lab_id, $notes);
		} else {
			$result = $this->diagnostic_safety_model->verify_level_2($io_lab_id, $notes);
		}

		if (!is_array($result) || !isset($result['ok']) || !$result['ok'] || $this->db->trans_status() === false) {
			$this->db->trans_rollback();
			$error = (is_array($result) && isset($result['error'])) ? (string)$result['error'] : 'Verification failed';
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Verification failed: ' . $error . '</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}

		$this->db->trans_commit();

		$auditDecision = $gate;
		if (!$gate['allowed'] && $this->current_user_is_admin()) {
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
		$this->audit_lab_gate_decision($io_lab_id, 'verify_result_level_' . (int)$level, $auditDecision, 'LAB_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);

		$this->session->set_flashdata('message', '<div class="alert alert-success">Result verified at Level '.$level.'. Status: '.$result['status'].'</div>');
		redirect(base_url().'app/laboratory/lab_queue');
	}

	/* ================================================================== */
	/*  PATIENT SAFETY: Sample Tracking                                    */
	/* ================================================================== */

	public function sample_tracking()
	{
		if (!$this->current_user_is_admin() && !isset($this->data['hasAccesstoLaboratory'])) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->data['title'] = 'Sample Tracking';
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/sample_tracking', $this->data);
	}

	public function scan_sample()
	{
		$barcode = trim((string)$this->input->post('barcode'));
		if (!$barcode) {
			echo json_encode(['ok' => false, 'error' => 'No barcode provided']);
			return;
		}

		$sample = $this->diagnostic_safety_model->get_sample_by_barcode($barcode);
		if (!$sample) {
			echo json_encode(['ok' => false, 'error' => 'Sample not found']);
			return;
		}

		echo json_encode(['ok' => true, 'sample' => $sample]);
	}

	public function update_sample_status()
	{
		$sample_id = (int)$this->input->post('sample_id');
		$status = trim((string)$this->input->post('status'));
		$location = trim((string)$this->input->post('location'));
		$notes = trim((string)$this->input->post('notes'));

		$valid_statuses = ['COLLECTED', 'RECEIVED_LAB', 'IN_PROCESS', 'RESULT_READY', 'VERIFIED', 'REJECTED', 'DISPOSED'];
		if (!in_array($status, $valid_statuses)) {
			echo json_encode(['ok' => false, 'error' => 'Invalid status']);
			return;
		}

		$result = $this->diagnostic_safety_model->update_sample_status($sample_id, $status, $location, $notes);
		echo json_encode(['ok' => $result]);
	}

	public function create_sample($io_lab_id = 0)
	{
		$io_lab_id = (int)$io_lab_id;
		$row = $this->db->get_where('iop_laboratory', ['io_lab_id' => $io_lab_id, 'InActive' => 0])->row();
		if (!$row) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Lab record not found.</div>');
			redirect(base_url().'app/laboratory/lab_queue');
			return;
		}

		$patient_no = '';
		if ($row->iop_id) {
			$pd = $this->db->get_where('patient_details_iop', ['IO_ID' => $row->iop_id])->row();
			if ($pd) $patient_no = $pd->patient_no;
		}

		$result = $this->diagnostic_safety_model->create_sample(
			$io_lab_id, $patient_no, $row->iop_id, 
			isset($row->laboratory_text) ? $row->laboratory_text : '',
			$this->input->post('sample_type')
		);

		if ($result['ok']) {
			$this->session->set_flashdata('message', '<div class="alert alert-success">Sample created. Barcode: <strong>'.$result['barcode'].'</strong></div>');
		} else {
			$this->session->set_flashdata('message', '<div class="alert alert-danger">Failed to create sample.</div>');
		}
		redirect(base_url().'app/laboratory/lab_queue');
	}

	/* ================================================================== */
	/*  DUPLICATE ORDER CHECK (AJAX)                                       */
	/* ================================================================== */

	public function check_duplicate_order()
	{
		$patient_no = trim((string)$this->input->post('patient_no'));
		$test_id = (int)$this->input->post('test_id');

		if (!$patient_no || !$test_id) {
			echo json_encode(['is_duplicate' => false]);
			return;
		}

		$result = $this->diagnostic_safety_model->check_duplicate($patient_no, $test_id, 24);
		echo json_encode($result);
	}

	/* ================================================================== */
	/*  DELTA FLAGS REVIEW                                                 */
	/* ================================================================== */

	public function delta_flags()
	{
		if (!$this->current_user_is_admin() && !isset($this->data['hasAccesstoLaboratory'])) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->data['title'] = 'Delta Check Flags';
		$this->data['flags'] = $this->diagnostic_safety_model->get_pending_delta_flags(50);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/delta_flags', $this->data);
	}

	/* ================================================================== */
	/*  NOTIFICATIONS                                                      */
	/* ================================================================== */

	public function get_notifications()
	{
		$user_id = $this->session->userdata('user_id');
		$notifications = $this->diagnostic_safety_model->get_user_notifications($user_id, true, 10);
		echo json_encode(['ok' => true, 'notifications' => $notifications]);
	}

	public function mark_notification_read($notification_id = 0)
	{
		$this->diagnostic_safety_model->mark_notification_read((int)$notification_id);
		echo json_encode(['ok' => true]);
	}

	/* ================================================================== */
	/*  SAFETY DASHBOARD                                                   */
	/* ================================================================== */

	public function safety_dashboard()
	{
		if (!$this->current_user_is_admin() && !isset($this->data['hasAccesstoLaboratory'])) {
			redirect(base_url().'access_denied');
			return;
		}

		$this->data['title'] = 'Laboratory Safety Dashboard';
		$this->data['critical_alert_count'] = $this->diagnostic_safety_model->count_pending_alerts();
		$this->data['pending_alerts'] = $this->diagnostic_safety_model->get_pending_alerts(null, 10);
		$this->data['delta_flags'] = $this->diagnostic_safety_model->get_pending_delta_flags(10);
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/safety_dashboard', $this->data);
	}

	/* ================================================================== */
	/*  B3 — Technician Assignment (AJAX POST)                            */
	/* ================================================================== */

	public function assign_technician()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'POST required'));
			return;
		}
		if (!$this->current_user_is_admin() && !(isset($this->data['hasAccesstoLaboratory']) && $this->data['hasAccesstoLaboratory'])) {
			echo json_encode(array('ok' => false, 'message' => 'Access denied'));
			return;
		}

		$io_lab_id     = (int)$this->input->post('io_lab_id');
		$technician_id = trim((string)$this->input->post('technician_id'));
		$user_id       = (string)$this->session->userdata('user_id');

		if ($io_lab_id <= 0 || $technician_id === '') {
			echo json_encode(array('ok' => false, 'message' => 'Missing parameters'));
			return;
		}

		$this->laboratory_model->install_imaging_tables();
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}
		if (!$this->user_can_write_lab_row($row)) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Access denied to this record'));
			return;
		}

		$gate = $this->service_gate->check_lab_gate($io_lab_id, isset($row->iop_id) ? (string)$row->iop_id : '');
		if (!$gate['allowed'] && !$this->current_user_is_admin()) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'assign_technician', $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			echo json_encode(array('ok' => false, 'message' => 'Payment required: ' . (isset($gate['reason']) ? $gate['reason'] : 'Outstanding balance')));
			return;
		}

		$ok = $this->laboratory_model->save_technician($io_lab_id, $technician_id);
		if (!$ok || $this->db->trans_status() === false) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Failed to assign technician'));
			return;
		}
		$this->db->trans_commit();
		log_message('info', 'LAB_TECH_ASSIGNED io_lab_id='.$io_lab_id.' tech='.$technician_id.' by='.$user_id);
		$auditDecision = $gate;
		if (!$gate['allowed'] && $this->current_user_is_admin()) {
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
		$this->audit_lab_gate_decision($io_lab_id, 'assign_technician', $auditDecision, 'LAB_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		echo json_encode(array('ok' => true, 'message' => 'Technician assigned'));
	}

	/* ================================================================== */
	/*  B7 — Supervisor Verification (AJAX POST)                          */
	/* ================================================================== */

	public function supervisor_verify()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'POST required'));
			return;
		}
		$isSupervisor = $this->current_user_is_admin() || has_role(array('admin', 'lab_supervisor', 'pathologist', 'senior_sonographer', 'radiologist'));
		if (!$isSupervisor) {
			echo json_encode(array('ok' => false, 'message' => 'Supervisor role required'));
			return;
		}

		$io_lab_id = (int)$this->input->post('io_lab_id');
		$notes     = trim((string)$this->input->post('notes'));
		$user_id   = (string)$this->session->userdata('user_id');

		if ($io_lab_id <= 0) {
			echo json_encode(array('ok' => false, 'message' => 'Missing io_lab_id'));
			return;
		}

		$this->laboratory_model->install_imaging_tables();
		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}
		$gate = $this->service_gate->check_lab_gate($io_lab_id, isset($row->iop_id) ? (string)$row->iop_id : '');
		if (!$gate['allowed'] && !$this->current_user_is_admin()) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'supervisor_verify', $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			echo json_encode(array('ok' => false, 'message' => 'Payment required: ' . (isset($gate['reason']) ? $gate['reason'] : 'Outstanding balance')));
			return;
		}

		$wf = $this->laboratory_model->get_workflow_status($io_lab_id);
		$st = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		$reportedStates = array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED');

		if (!in_array($st, $reportedStates) && $st !== 'VERIFIED') {
			// Also allow verify if result text is set directly in iop_laboratory
			$hasResult = (isset($row->result) && trim((string)$row->result) !== '');
			if (!$hasResult) {
				echo json_encode(array('ok' => false, 'message' => 'Result must be reported before supervisor verification'));
				return;
			}
		}
		if ($st === 'VERIFIED') {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => true, 'message' => 'Already verified'));
			return;
		}

		$this->laboratory_model->upsert_workflow_status($io_lab_id, 'VERIFIED', $user_id);
		$this->laboratory_model->ensure_lab_workflow_enhancements();

		if ($this->laboratory_model->column_exists('iop_laboratory_workflow', 'verified_by')) {
			$update = array('verified_by' => $user_id, 'verified_at' => date('Y-m-d H:i:s'));
			if ($notes !== '' && $this->laboratory_model->column_exists('iop_laboratory_workflow', 'supervisor_notes')) {
				$update['supervisor_notes'] = $notes;
			}
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->update('iop_laboratory_workflow', $update);
		}

		log_message('info', 'LAB_SUPERVISOR_VERIFIED io_lab_id='.$io_lab_id.' by='.$user_id);
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Failed to verify'));
			return;
		}
		$this->db->trans_commit();
		$auditDecision = $gate;
		if (!$gate['allowed'] && $this->current_user_is_admin()) {
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
		$this->audit_lab_gate_decision($io_lab_id, 'supervisor_verify', $auditDecision, 'LAB_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		echo json_encode(array('ok' => true, 'message' => 'Result verified by supervisor'));
	}

	/* ================================================================== */
	/*  A7 — Doctor Acknowledgement (AJAX POST)                           */
	/* ================================================================== */

	public function doctor_acknowledge()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'POST required'));
			return;
		}
		$isDoctor = isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor'];
		$isAdmin  = $this->current_user_is_admin();
		if (!$isDoctor && !$isAdmin) {
			echo json_encode(array('ok' => false, 'message' => 'Doctor role required'));
			return;
		}

		$io_lab_id = (int)$this->input->post('io_lab_id');
		$user_id   = (string)$this->session->userdata('user_id');

		if ($io_lab_id <= 0) {
			echo json_encode(array('ok' => false, 'message' => 'Missing io_lab_id'));
			return;
		}

		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}

		$this->db->trans_begin();
		$this->laboratory_model->lock_lab_request_for_update($io_lab_id);
		$rowLocked = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$rowLocked) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}
		$iop_id_locked = isset($rowLocked->iop_id) ? (string)$rowLocked->iop_id : '';
		$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id_locked);
		if (!$gate['allowed'] && !$isAdmin) {
			$this->db->trans_rollback();
			$this->audit_lab_gate_decision($io_lab_id, 'doctor_acknowledge', $gate, 'LAB_GATE_BLOCKED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gate);
			$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required';
			echo json_encode(array('ok' => false, 'message' => 'Payment required: '.$reason));
			return;
		}

		$this->laboratory_model->mark_delivered_if_needed($io_lab_id, $user_id);
		$this->laboratory_model->ensure_lab_workflow_enhancements();

		if ($this->laboratory_model->table_exists('iop_laboratory_workflow') &&
		    $this->laboratory_model->column_exists('iop_laboratory_workflow', 'doctor_acknowledged_at')) {
			$existing = $this->db->get_where('iop_laboratory_workflow', array('io_lab_id' => $io_lab_id))->row();
			if ($existing) {
				$this->db->where('io_lab_id', $io_lab_id);
				$this->db->update('iop_laboratory_workflow', array(
					'doctor_acknowledged_at' => date('Y-m-d H:i:s'),
					'doctor_acknowledged_by' => $user_id
				));
			}
		}

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Failed to acknowledge'));
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
		$this->audit_lab_gate_decision($io_lab_id, 'doctor_acknowledge', $auditDecision, 'LAB_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $auditDecision);
		log_message('info', 'LAB_DOCTOR_ACKNOWLEDGED io_lab_id='.$io_lab_id.' by='.$user_id);
		echo json_encode(array('ok' => true, 'message' => 'Result acknowledged'));
	}

	/* ================================================================== */
	/*  D2 — Reference Ranges AJAX                                         */
	/* ================================================================== */

	public function get_ref_ranges()
	{
		header('Content-Type: application/json');
		$io_lab_id = (int)$this->input->get_post('io_lab_id');
		if ($io_lab_id <= 0) {
			echo json_encode(array('ok' => false, 'ranges' => array()));
			return;
		}
		$this->load->model('app/laboratory_model');

		// Get the laboratory_id for this request
		$labRow = $this->db->select('laboratory_id, laboratory_text')->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$labRow) {
			echo json_encode(array('ok' => true, 'ranges' => array(), 'test_name' => ''));
			return;
		}
		$laboratory_id = (int)$labRow->laboratory_id;
		$testName = '';

		// Get test name - prioritize laboratory_text (actual ordered test name)
		if (isset($labRow->laboratory_text) && trim((string)$labRow->laboratory_text) !== '') {
			$testName = trim((string)$labRow->laboratory_text);
		} elseif ($laboratory_id > 0 && $this->laboratory_model->table_exists('bill_particular')) {
			// Fallback to bill_particular only if laboratory_text is empty
			$bp = $this->db->select('particular_name')->get_where('bill_particular', array('particular_id' => $laboratory_id))->row();
			if ($bp) {
				$testName = (string)$bp->particular_name;
			}
		}

		// Pull representative reference ranges from previously completed lab_result_entries for same laboratory_id
		$ranges = array();
		if ($laboratory_id > 0 && $this->laboratory_model->table_exists('lab_result_entries')) {
			// Find a recently completed io_lab_id for same laboratory_id that has ranges set
			$sibling = $this->db->query("
				SELECT E.parameter_name, E.unit,
				       E.ref_range_low, E.ref_range_high
				FROM lab_result_entries E
				INNER JOIN iop_laboratory L ON L.io_lab_id = E.io_lab_id
				WHERE L.laboratory_id = ?
				  AND L.InActive = 0
				  AND E.InActive = 0
				  AND (E.ref_range_low IS NOT NULL OR E.ref_range_high IS NOT NULL)
				ORDER BY E.io_lab_id DESC
				LIMIT 30
			", array($laboratory_id));
			if ($sibling) {
				// Deduplicate by parameter_name — keep most recent
				$seen = array();
				foreach ($sibling->result() as $r) {
					$key = strtolower(trim((string)$r->parameter_name));
					if ($key === '' || isset($seen[$key])) continue;
					$seen[$key] = true;
					$ranges[] = array(
						'parameter' => (string)$r->parameter_name,
						'unit'      => (string)$r->unit,
						'low'       => $r->ref_range_low !== null ? (string)$r->ref_range_low : '',
						'high'      => $r->ref_range_high !== null ? (string)$r->ref_range_high : '',
					);
				}
			}
		}

		echo json_encode(array('ok' => true, 'test_name' => $testName, 'ranges' => $ranges));
	}
}
