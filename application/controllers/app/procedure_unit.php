<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Procedure_unit extends General
{
	public function __construct()
	{
		parent::__construct();

		$this->load->model('app/procedure_unit_model');
		$this->load->model('app/service_gate_model', 'service_gate');
		$this->load->model('app/service_gate_audit_model', 'service_gate_audit');
		$this->load->model('app/governance_model');

		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
			return;
		}
		General::variable();
		require_role(array('admin', 'doctor', 'nurse', 'procedure_unit'));

		if (!$this->session->userdata('_schema_procedure_unit_ok')) {
			$this->governance_model->ensure_governance_schema();
			if (method_exists($this->governance_model, 'ensure_default_roles_and_users')) {
				$this->governance_model->ensure_default_roles_and_users();
			}
			$this->load->model('app/procedure_request_model');
			$this->procedure_request_model->ensure_schema();
			$this->procedure_unit_model->ensure_schema();
			$this->session->set_userdata('_schema_procedure_unit_ok', 1);
		}
	}

	private function audit_gate_decision($request_id, $action, $decision, $event_code)
	{
		try {
			$request_id = (int)$request_id;
			$event_code = trim((string)$event_code);
			$action = trim((string)$action);
			if ($request_id <= 0 || $event_code === '') {
				return;
			}

			$item_ref = 'opd_procedure_request_id:' . (int)$request_id;
			$userId = (string)$this->session->userdata('user_id');
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';
			$uri = method_exists($this->uri, 'uri_string') ? (string)$this->uri->uri_string() : '';

			$finalAllowed = (is_array($decision) && array_key_exists('allowed', $decision)) ? (bool)$decision['allowed'] : false;
			$reason = (is_array($decision) && isset($decision['reason'])) ? (string)$decision['reason'] : null;
			$blockedReason = (is_array($decision) && isset($decision['blocked_reason'])) ? trim((string)$decision['blocked_reason']) : '';
			if (!$finalAllowed && $blockedReason === '') {
				$blockedReason = 'PAYMENT_PENDING';
			}

			if (!isset($this->service_gate_audit) || !method_exists($this->service_gate_audit, 'log_event')) {
				return;
			}

			$this->service_gate_audit->log_event(array(
				'event_code' => $event_code,
				'module' => 'PROCEDURE',
				'item_ref' => $item_ref,
				'user_id' => $userId !== '' ? (int)$userId : null,
				'action' => $action !== '' ? $action : null,
				'blocked_reason' => $finalAllowed ? null : $blockedReason,
				'allowed' => $finalAllowed ? 1 : 0,
				'gate_version' => 'v1',
				'reason' => $reason,
				'payload' => array(
					'request_id' => (int)$request_id,
					'item_ref' => $item_ref,
					'method' => $method,
					'uri' => $uri,
					'allowed' => $finalAllowed ? true : false,
					'raw_gate' => $decision,
				),
				'ip' => $ip,
			));
		} catch (\Throwable $e) {
		}
	}

	public function index()
	{
		$this->session->set_userdata(array(
			'tab' => 'procedure_unit',
			'module' => 'procedure_unit_worklist',
			'subtab' => '',
			'submodule' => ''
		));

		$filters = array(
			'status' => (string)$this->input->get('status'),
			'search' => (string)$this->input->get('search'),
			'date_from' => (string)$this->input->get('date_from'),
			'date_to' => (string)$this->input->get('date_to'),
			'limit' => (int)$this->input->get('limit'),
		);

		$items = $this->procedure_unit_model->get_worklist($filters);

		$data = $this->data;
		$data['title'] = 'Procedure Unit Worklist';
		$data['filters'] = $filters;
		$data['items'] = $items;
		$data['message'] = $this->session->flashdata('message');

		$this->load->view('app/procedure_unit/worklist', $data);
	}

	public function perform()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		require_role(array('admin', 'doctor', 'nurse', 'procedure_unit'));

		$request_id = (int)$this->input->post('request_id');
		if ($request_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invalid request.</div>");
			redirect(base_url() . 'app/procedure_unit');
			return;
		}

		$req = $this->procedure_unit_model->get_request($request_id);
		if (!$req) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Request not found.</div>");
			redirect(base_url() . 'app/procedure_unit');
			return;
		}

		$gate = $this->service_gate->check_service('PROCEDURE', (string)$request_id, isset($req['iop_id']) ? (string)$req['iop_id'] : null, isset($req['patient_no']) ? (string)$req['patient_no'] : null);
		$this->audit_gate_decision($request_id, 'PERFORM', $gate, 'PROCEDURE_PERFORM_GATE');

		if (!is_array($gate) || (isset($gate['allowed']) && !$gate['allowed'])) {
			$reason = is_array($gate) && isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required.';
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Cannot perform: " . htmlspecialchars($reason) . "</div>");
			redirect(base_url() . 'app/procedure_unit');
			return;
		}

		$userId = (string)$this->session->userdata('user_id');
		$res = $this->procedure_unit_model->mark_performed($request_id, $userId);
		if (is_array($res) && !empty($res['ok'])) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Procedure marked as performed.</div>");
		} else {
			$err = is_array($res) && isset($res['error']) ? $res['error'] : 'Update failed';
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($err) . "</div>");
		}
		redirect(base_url() . 'app/procedure_unit');
	}

	public function cancel()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		require_role(array('admin', 'doctor', 'nurse', 'procedure_unit'));

		$request_id = (int)$this->input->post('request_id');
		$reason = trim((string)$this->input->post('reason'));
		if ($reason === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Cancel reason is required.</div>");
			redirect(base_url() . 'app/procedure_unit');
			return;
		}
		if ($request_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> Invalid request.</div>");
			redirect(base_url() . 'app/procedure_unit');
			return;
		}

		$userId = (string)$this->session->userdata('user_id');
		$res = $this->procedure_unit_model->cancel_request($request_id, $userId, $reason);
		if (is_array($res) && !empty($res['ok'])) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Procedure request cancelled.</div>");
		} else {
			$err = is_array($res) && isset($res['error']) ? $res['error'] : 'Update failed';
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($err) . "</div>");
		}
		redirect(base_url() . 'app/procedure_unit');
	}
}
