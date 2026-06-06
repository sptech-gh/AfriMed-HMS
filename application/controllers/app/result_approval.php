<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

/**
 * Result Approval Controller
 * Enterprise Safety Audit Implementation
 * 
 * Handles:
 * - Critical Result Edit Restrictions
 * - Verification Role Enforcement
 * - Supervisor Approval Workflow
 */
class Result_approval extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/result_approval_model');
        $this->load->model('app/laboratory_model');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin', 'laboratory', 'sonographer'));

        if (!$this->session->userdata('_schema_result_approval_ok')) {
            $this->result_approval_model->ensure_approval_schema();
            $this->session->set_userdata('_schema_result_approval_ok', 1);
        }

        // Set session data for sidebar
        $this->session->set_userdata('currentTab', 'result_approval');
        $this->session->set_userdata('currentModule', 'result_approval');
    }

    /**
     * Supervisor Approval Dashboard
     */
    public function index()
    {
        $this->require_supervisor_access();
        
        $data = [
            'title' => 'Supervisor Approval Queue',
            'pending_approvals' => $this->result_approval_model->get_pending_approvals(null, 100),
            'pending_count' => $this->result_approval_model->count_pending_approvals(),
            'approval_stats' => $this->result_approval_model->get_approval_stats(),
            'verification_stats' => $this->result_approval_model->get_verification_stats()
        ];
        
        $this->load->view('app/result_approval/dashboard', $data);
    }

    /**
     * View pending approvals by type
     */
    public function pending($type = null)
    {
        $this->require_supervisor_access();
        
        $diagnostic_type = strtoupper($type);
        if (!in_array($diagnostic_type, ['LAB', 'RADIOLOGY', 'SONOGRAPHY'])) {
            $diagnostic_type = null;
        }
        
        $data = [
            'title' => 'Pending Approvals' . ($diagnostic_type ? " - {$diagnostic_type}" : ''),
            'diagnostic_type' => $diagnostic_type,
            'pending_approvals' => $this->result_approval_model->get_pending_approvals($diagnostic_type, 100),
            'pending_count' => $this->result_approval_model->count_pending_approvals($diagnostic_type)
        ];
        
        $this->load->view('app/result_approval/pending', $data);
    }

    /**
     * View single approval request details
     */
    public function view($approval_id)
    {
        $this->require_supervisor_access();
        
        $approval_id = (int)$approval_id;
        
        $this->db->select('a.*, p.firstname, p.lastname, p.patient_no as pno, u.username as requested_by_name, u2.username as reviewed_by_name')
            ->from('supervisor_approval_queue a')
            ->join('patient_personal_info p', 'p.patient_no = a.patient_no', 'left')
            ->join('user u', 'u.user_id = a.requested_by', 'left')
            ->join('user u2', 'u2.user_id = a.reviewed_by', 'left')
            ->where('a.approval_id', $approval_id);
        
        $approval = $this->db->get()->row();
        
        if (!$approval) {
            $this->session->set_flashdata('error', 'Approval request not found');
            redirect('app/result_approval');
            return;
        }
        
        // Get audit trail
        $audit_trail = $this->db->select('a.*, u.username')
            ->from('supervisor_approval_audit a')
            ->join('user u', 'u.user_id = a.performed_by', 'left')
            ->where('a.approval_id', $approval_id)
            ->order_by('a.performed_at', 'DESC')
            ->get()->result();
        
        $data = [
            'title' => 'Approval Request #' . $approval_id,
            'approval' => $approval,
            'audit_trail' => $audit_trail
        ];
        
        $this->load->view('app/result_approval/view', $data);
    }

    /**
     * Approve a request
     */
    public function approve()
    {
        $this->require_supervisor_access();

        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }
        
        $approval_id = $this->input->post('approval_id');
        $notes = $this->input->post('notes');
        
        $result = $this->result_approval_model->approve_request($approval_id, $notes);
        
        if ($result['ok']) {
            $this->session->set_flashdata('success', 'Request approved successfully');
        } else {
            $this->session->set_flashdata('error', $result['error']);
        }
        
        redirect('app/result_approval');
    }

    /**
     * Reject a request
     */
    public function reject()
    {
        $this->require_supervisor_access();

        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }
        
        $approval_id = $this->input->post('approval_id');
        $reason = $this->input->post('reason');
        
        if (empty($reason)) {
            $this->session->set_flashdata('error', 'Rejection reason is required');
            redirect('app/result_approval/view/' . $approval_id);
            return;
        }
        
        $result = $this->result_approval_model->reject_request($approval_id, $reason);
        
        if ($result['ok']) {
            $this->session->set_flashdata('success', 'Request rejected');
        } else {
            $this->session->set_flashdata('error', $result['error']);
        }
        
        redirect('app/result_approval');
    }

    /**
     * Escalate a request
     */
    public function escalate()
    {
        $this->require_supervisor_access();

        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }
        
        $approval_id = $this->input->post('approval_id');
        $escalate_to = $this->input->post('escalate_to');
        $reason = $this->input->post('reason');
        
        $result = $this->result_approval_model->escalate_request($approval_id, $escalate_to, $reason);
        
        if ($result['ok']) {
            $this->session->set_flashdata('success', 'Request escalated to level ' . $result['level']);
        } else {
            $this->session->set_flashdata('error', $result['error']);
        }
        
        redirect('app/result_approval');
    }

    /**
     * Check if user can edit a result (AJAX)
     */
    public function check_edit_permission()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            return;
        }

        $diagnostic_type = $this->input->post('diagnostic_type');
        $record_id = $this->input->post('record_id');
        $is_critical = $this->input->post('is_critical') == '1';
        
        $result = $this->result_approval_model->can_edit_result($diagnostic_type, $record_id, $is_critical);
        
        echo json_encode($result);
    }

    /**
     * Request edit approval (AJAX)
     */
    public function request_edit()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            return;
        }

        $diagnostic_type = $this->input->post('diagnostic_type');
        $record_id = $this->input->post('record_id');
        $patient_no = $this->input->post('patient_no');
        $test_name = $this->input->post('test_name');
        $original_value = $this->input->post('original_value');
        $proposed_value = $this->input->post('proposed_value');
        $reason = $this->input->post('reason');
        $justification = $this->input->post('justification');
        
        if (empty($reason)) {
            echo json_encode(['ok' => false, 'error' => 'Edit reason is required']);
            return;
        }
        
        $result = $this->result_approval_model->request_edit_approval(
            $diagnostic_type, $record_id, $patient_no, $test_name,
            $original_value, $proposed_value, $reason, $justification
        );
        
        echo json_encode($result);
    }

    /**
     * Check if user can verify a result (AJAX)
     */
    public function check_verify_permission()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            return;
        }

        $diagnostic_type = $this->input->post('diagnostic_type');
        $record_id = $this->input->post('record_id');
        $level = (int)$this->input->post('level') ?: 1;
        $category = $this->input->post('category') ?: 'GENERAL';
        
        $result = $this->result_approval_model->can_verify_result($diagnostic_type, $record_id, $level, $category);
        
        echo json_encode($result);
    }

    /**
     * Perform verification with role check (AJAX)
     */
    public function verify_result()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            return;
        }

        $diagnostic_type = $this->input->post('diagnostic_type');
        $record_id = $this->input->post('record_id');
        $patient_no = $this->input->post('patient_no');
        $level = (int)$this->input->post('level') ?: 1;
        $notes = $this->input->post('notes');
        $category = $this->input->post('category') ?: 'GENERAL';
        
        $result = $this->result_approval_model->verify_with_role_check(
            $diagnostic_type, $record_id, $patient_no, $level, $notes, $category
        );
        
        echo json_encode($result);
    }

    /**
     * Get edit audit trail for a record (AJAX)
     */
    public function get_audit_trail()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            return;
        }

        $diagnostic_type = $this->input->post('diagnostic_type');
        $record_id = $this->input->post('record_id');
        
        $trail = $this->result_approval_model->get_edit_audit_trail($diagnostic_type, $record_id);
        
        echo json_encode(['ok' => true, 'trail' => $trail]);
    }

    /**
     * Get pending approval count (AJAX - for badges)
     */
    public function get_pending_count()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            echo json_encode(['ok' => false, 'error' => 'Invalid request']);
            return;
        }

        $count = $this->result_approval_model->count_pending_approvals();
        echo json_encode(['count' => $count]);
    }

    /**
     * Verification credentials management
     */
    public function credentials()
    {
        $this->require_admin_access();
        
        $this->db->select('c.*, u.username, u.firstname, u.lastname')
            ->from('user_verification_credentials c')
            ->join('user u', 'u.user_id = c.user_id', 'left')
            ->where('c.InActive', 0)
            ->order_by('u.username', 'ASC');
        
        $data = [
            'title' => 'Verification Credentials',
            'credentials' => $this->db->get()->result(),
            'users' => $this->db->select('user_id, username, firstname, lastname')
                ->from('user')
                ->where('InActive', 0)
                ->order_by('username', 'ASC')
                ->get()->result()
        ];
        
        $this->load->view('app/result_approval/credentials', $data);
    }

    /**
     * Grant verification credential
     */
    public function grant_credential()
    {
        $this->require_admin_access();

        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }
        
        $user_id = $this->input->post('user_id');
        $diagnostic_type = $this->input->post('diagnostic_type');
        $can_level_1 = $this->input->post('can_level_1') ? 1 : 0;
        $can_level_2 = $this->input->post('can_level_2') ? 1 : 0;
        $can_critical = $this->input->post('can_critical') ? 1 : 0;
        $cert_number = $this->input->post('certification_number');
        $cert_expiry = $this->input->post('certification_expiry');
        $exp_start = $this->input->post('experience_start_date');
        
        // Check if exists
        $existing = $this->db->get_where('user_verification_credentials', [
            'user_id' => $user_id,
            'diagnostic_type' => $diagnostic_type,
            'InActive' => 0
        ])->row();
        
        $data = [
            'user_id' => $user_id,
            'diagnostic_type' => $diagnostic_type,
            'can_verify_level_1' => $can_level_1,
            'can_verify_level_2' => $can_level_2,
            'can_verify_critical' => $can_critical,
            'certification_number' => $cert_number ?: null,
            'certification_expiry' => $cert_expiry ?: null,
            'experience_start_date' => $exp_start ?: null,
            'granted_by' => $this->session->userdata('user_id'),
            'granted_at' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ];
        
        if ($existing) {
            $this->db->where('credential_id', $existing->credential_id)->update('user_verification_credentials', $data);
            $this->session->set_flashdata('success', 'Credential updated');
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('user_verification_credentials', $data);
            $this->session->set_flashdata('success', 'Credential granted');
        }
        
        redirect('app/result_approval/credentials');
    }

    /**
     * Revoke verification credential
     */
    public function revoke_credential($credential_id)
    {
        $this->require_admin_access();

        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }
        
        $this->db->where('credential_id', $credential_id)->update('user_verification_credentials', [
            'is_active' => 0,
            'revoked_by' => $this->session->userdata('user_id'),
            'revoked_at' => date('Y-m-d H:i:s')
        ]);
        
        $this->session->set_flashdata('success', 'Credential revoked');
        redirect('app/result_approval/credentials');
    }

    /**
     * Edit permissions configuration
     */
    public function permissions()
    {
        $this->require_admin_access();
        
        $data = [
            'title' => 'Edit Permissions Configuration',
            'permissions' => $this->db->get_where('result_edit_permissions', ['InActive' => 0])->result(),
            'verification_config' => $this->db->get_where('verification_role_config', ['InActive' => 0])->result()
        ];
        
        $this->load->view('app/result_approval/permissions', $data);
    }

    /**
     * Save edit permission
     */
    public function save_permission()
    {
        $this->require_admin_access();

        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }
        
        $permission_id = $this->input->post('permission_id');
        $diagnostic_type = $this->input->post('diagnostic_type');
        $result_category = $this->input->post('result_category');
        $is_critical = $this->input->post('is_critical') ? 1 : 0;
        $allowed_roles = $this->input->post('allowed_roles'); // array
        $requires_approval = $this->input->post('requires_supervisor_approval') ? 1 : 0;
        $edit_window = (int)$this->input->post('edit_window_hours');
        
        $data = [
            'diagnostic_type' => $diagnostic_type,
            'result_category' => $result_category,
            'is_critical' => $is_critical,
            'allowed_roles' => json_encode($allowed_roles ?: []),
            'requires_supervisor_approval' => $requires_approval,
            'edit_window_hours' => $edit_window,
            'is_active' => 1,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        if ($permission_id) {
            $this->db->where('permission_id', $permission_id)->update('result_edit_permissions', $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $this->db->insert('result_edit_permissions', $data);
        }
        
        $this->session->set_flashdata('success', 'Permission saved');
        redirect('app/result_approval/permissions');
    }

    /**
     * Reports
     */
    public function reports()
    {
        $this->require_supervisor_access();
        
        $date_from = $this->input->get('date_from') ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $this->input->get('date_to') ?: date('Y-m-d');
        
        $data = [
            'title' => 'Approval & Verification Reports',
            'date_from' => $date_from,
            'date_to' => $date_to,
            'approval_stats' => $this->result_approval_model->get_approval_stats($date_from, $date_to),
            'verification_stats' => $this->result_approval_model->get_verification_stats($date_from, $date_to)
        ];
        
        $this->load->view('app/result_approval/reports', $data);
    }

    /**
     * Helper: Require supervisor access
     */
    private function require_supervisor_access()
    {
        if (!has_role(array('admin', 'laboratory', 'sonographer'))) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-ban"></i> Access denied. Supervisor privileges required.</div>');
            redirect(base_url() . 'app/dashboard');
        }
    }

    /**
     * Helper: Require admin access
     */
    private function require_admin_access()
    {
        if (!has_role('admin')) {
            $this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-ban"></i> Access denied. Admin privileges required.</div>');
            redirect(base_url() . 'app/dashboard');
        }
    }
}
