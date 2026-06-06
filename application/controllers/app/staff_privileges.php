<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Staff_privileges extends General
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
			'tab'       => 'admin',
			'module'    => 'staff_privileges',
			'subtab'    => '',
			'submodule' => ''
		));

		$this->data['message'] = $this->session->flashdata('message');
		$this->data['privileges'] = $this->governance_model->get_all_privileges(false, 200);
		$this->data['active_privileges'] = $this->governance_model->get_all_privileges(true, 200);
		$this->data['users_list'] = $this->governance_model->get_users_list();
		$this->data['privilege_defs'] = $this->governance_model->get_privilege_definitions();
		$this->data['audit_log'] = $this->governance_model->get_all_privilege_audit(100);
		$this->data['summary'] = $this->governance_model->get_governance_summary();
		$this->load->view('app/admin/staff_privileges', $this->data);
	}

	public function grant()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$user_id = trim((string)$this->input->post('user_id'));
		$privilege_name = trim((string)$this->input->post('privilege_name'));
		$expiry_date = trim((string)$this->input->post('expiry_date'));
		$notes = trim((string)$this->input->post('notes'));
		$granted_by = $this->session->userdata('user_id');

		if ($user_id === '' || $privilege_name === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning'><i class='fa fa-warning'></i> User and privilege are required.</div>");
			redirect(base_url() . 'app/staff_privileges');
			return;
		}

		$result = $this->governance_model->grant_privilege($user_id, $privilege_name, $granted_by, $expiry_date !== '' ? $expiry_date : null, $notes);

		if ($result['ok']) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Privilege granted successfully.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($result['error']) . "</div>");
		}
		redirect(base_url() . 'app/staff_privileges');
	}

	public function revoke()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}

		$privilege_id = (int)$this->input->post('privilege_id');
		$revoked_by = $this->session->userdata('user_id');
		$notes = trim((string)$this->input->post('notes'));

		$result = $this->governance_model->revoke_privilege($privilege_id, $revoked_by, $notes);

		if ($result['ok']) {
			$this->session->set_flashdata('message', "<div class='alert alert-success'><i class='fa fa-check'></i> Privilege revoked successfully.</div>");
		} else {
			$this->session->set_flashdata('message', "<div class='alert alert-danger'><i class='fa fa-ban'></i> " . htmlspecialchars($result['error']) . "</div>");
		}
		redirect(base_url() . 'app/staff_privileges');
	}

	public function history($user_id = '')
	{
		$user_id = trim((string)$user_id);
		if ($user_id === '') {
			$user_id = trim((string)$this->uri->segment(4));
		}
		$this->data['privilege_history'] = $this->governance_model->get_privilege_history($user_id, 50);
		$this->data['user_id'] = $user_id;
		echo json_encode($this->data['privilege_history']);
	}
}
