<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Stock_approval extends General
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/governance_model');
		$this->load->model('general_model');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
			return;
		}
		General::variable();
		require_role('admin');
		if (!$this->session->userdata('_schema_governance_ok')) {
			$this->governance_model->ensure_governance_schema();
			$this->session->set_userdata('_schema_governance_ok', 1);
		}
	}

	public function index()
	{
		$this->session->set_userdata(array(
			'tab'       => 'pharmacy',
			'module'    => 'pending_approvals',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['pending'] = $this->governance_model->get_pending_stock_requests(100);
		$this->data['history'] = $this->governance_model->get_stock_requests(array(), 100);
		$this->data['pending_count'] = $this->governance_model->count_pending_stock_requests();
		$this->data['audit_log'] = $this->governance_model->get_stock_audit_log(array(), 100);
		$this->load->view('app/pharmacy/pending_approvals', $this->data);
	}

	public function approve()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-exclamation-triangle'></i> Please use the Approve button from the pending requests list.</div>");
			redirect(base_url() . 'app/stock_approval');
			return;
		}

		$request_id = (int)$this->input->post('request_id');
		$admin_notes = trim((string)$this->input->post('admin_notes'));
		$approved_by = $this->session->userdata('user_id');

		$result = $this->governance_model->approve_stock_request($request_id, $approved_by, $admin_notes);

		if ($result['ok']) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Request #" . $request_id . " approved. Stock updated (Old: " . $result['old_qty'] . " → New: " . $result['new_qty'] . ").</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($result['error']) . "</div>");
		}
		redirect(base_url() . 'app/stock_approval');
	}

	public function reject()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-exclamation-triangle'></i> Please use the Reject button from the pending requests list.</div>");
			redirect(base_url() . 'app/stock_approval');
			return;
		}

		$request_id = (int)$this->input->post('request_id');
		$admin_notes = trim((string)$this->input->post('admin_notes'));
		$rejected_by = $this->session->userdata('user_id');

		$result = $this->governance_model->reject_stock_request($request_id, $rejected_by, $admin_notes);

		if ($result['ok']) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-times'></i> Request #" . $request_id . " rejected. No stock changes made.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($result['error']) . "</div>");
		}
		redirect(base_url() . 'app/stock_approval');
	}
}
