<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Diagnostic Safety Controller
 * Phase 3 (Week 4) - Enterprise Safety Features
 * 
 * Endpoints for:
 * - Multi-Channel Notifications
 * - TAT Monitoring & STAT Enforcement
 * - Audit Trail Management
 */
class Diagnostic_safety extends General
{
    public function __construct()
    {
        parent::__construct();
        
        $this->load->model('app/diagnostic_notifications_model', 'notifications');
        $this->load->model('app/diagnostic_tat_model', 'tat');
        $this->load->model('app/diagnostic_audit_model', 'audit');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin', 'laboratory', 'sonographer', 'doctor'));

        if (!$this->session->userdata('_schema_diagnostic_safety_ok')) {
            $this->notifications->ensure_notifications_schema();
            $this->tat->ensure_tat_schema();
            $this->audit->ensure_audit_schema();
            $this->session->set_userdata('_schema_diagnostic_safety_ok', 1);
        }
    }

    /* ================================================================== */
    /*  DASHBOARD                                                         */
    /* ================================================================== */

    public function index()
    {
        $this->data['title'] = 'Diagnostic Safety Dashboard';
        
        // TAT Stats
        $this->data['tat_dashboard'] = $this->tat->get_tat_dashboard();
        $this->data['at_risk_tests'] = $this->tat->get_at_risk_tests(10);
        $this->data['active_stat_tests'] = $this->tat->get_active_stat_tests(10);
        
        // Notification Stats
        $this->data['pending_notifications'] = $this->notifications->get_pending_notifications(10);
        $this->data['notification_stats'] = $this->notifications->get_notification_stats();
        
        // Audit Stats
        $this->data['audit_stats'] = $this->audit->get_audit_stats();
        $this->data['chain_status'] = $this->audit->get_chain_status();
        
        // Counts for badges
        $this->data['stat_pending_count'] = $this->tat->count_pending_stat_approvals();
        $this->data['breach_count'] = $this->tat->count_unacknowledged_breaches();
        $this->data['at_risk_count'] = $this->tat->count_at_risk_tests();
        
        $this->load->view('app/diagnostic_safety/dashboard', $this->data);
    }

    /* ================================================================== */
    /*  TAT MONITORING                                                    */
    /* ================================================================== */

    public function tat_dashboard()
    {
        $this->data['title'] = 'TAT Monitoring Dashboard';
        
        $department = $this->input->get('department');
        
        $this->data['dashboard'] = $this->tat->get_tat_dashboard($department);
        $this->data['at_risk_tests'] = $this->tat->get_at_risk_tests(20);
        $this->data['performance_trend'] = $this->tat->get_performance_trend($department, 30);
        $this->data['unacknowledged_breaches'] = $this->tat->get_unacknowledged_breaches(20);
        
        $this->load->view('app/diagnostic_safety/tat_dashboard', $this->data);
    }

    public function stat_queue()
    {
        $this->data['title'] = 'STAT Test Queue';
        
        $this->data['pending_approvals'] = $this->tat->get_pending_stat_requests();
        $this->data['active_stat_tests'] = $this->tat->get_active_stat_tests(50);
        
        $this->load->view('app/diagnostic_safety/stat_queue', $this->data);
    }

    public function approve_stat()
    {
        $stat_id = $this->input->post('stat_id');
        
        if (!$stat_id) {
            $this->session->set_flashdata('error', 'Invalid STAT request');
            redirect('app/diagnostic_safety/stat_queue');
        }
        
        $this->tat->approve_stat_request($stat_id);
        
        // Log audit
        $this->audit->log_event('STAT_APPROVED', [
            'category' => 'ORDER',
            'entity_type' => 'stat_request',
            'entity_id' => $stat_id,
            'action' => 'approve'
        ]);
        
        $this->session->set_flashdata('success', 'STAT request approved');
        redirect('app/diagnostic_safety/stat_queue');
    }

    public function reject_stat()
    {
        $stat_id = $this->input->post('stat_id');
        $reason = $this->input->post('reason');
        
        if (!$stat_id || !$reason) {
            $this->session->set_flashdata('error', 'STAT ID and reason are required');
            redirect('app/diagnostic_safety/stat_queue');
        }
        
        $this->tat->reject_stat_request($stat_id, $reason);
        
        // Log audit
        $this->audit->log_event('STAT_REJECTED', [
            'category' => 'ORDER',
            'entity_type' => 'stat_request',
            'entity_id' => $stat_id,
            'action' => 'reject',
            'additional' => ['reason' => $reason]
        ]);
        
        $this->session->set_flashdata('success', 'STAT request rejected');
        redirect('app/diagnostic_safety/stat_queue');
    }

    public function acknowledge_breach()
    {
        $breach_id = $this->input->post('breach_id');
        $root_cause = $this->input->post('root_cause');
        
        if (!$breach_id) {
            echo json_encode(['success' => false, 'error' => 'Invalid breach ID']);
            return;
        }
        
        $this->tat->acknowledge_breach($breach_id, $root_cause);
        
        echo json_encode(['success' => true]);
    }

    public function resolve_breach()
    {
        $breach_id = $this->input->post('breach_id');
        $corrective_action = $this->input->post('corrective_action');
        $notes = $this->input->post('notes');
        
        if (!$breach_id || !$corrective_action) {
            echo json_encode(['success' => false, 'error' => 'Breach ID and corrective action required']);
            return;
        }
        
        $this->tat->resolve_breach($breach_id, $corrective_action, $notes);
        
        echo json_encode(['success' => true]);
    }

    public function tat_targets()
    {
        $this->data['title'] = 'TAT Target Configuration';
        $this->data['targets'] = $this->tat->get_all_tat_targets();
        
        $this->load->view('app/diagnostic_safety/tat_targets', $this->data);
    }

    public function save_tat_target()
    {
        $data = [
            'target_id' => $this->input->post('target_id'),
            'department' => $this->input->post('department'),
            'test_category' => $this->input->post('test_category'),
            'test_code' => $this->input->post('test_code'),
            'priority_level' => $this->input->post('priority_level'),
            'target_minutes' => $this->input->post('target_minutes'),
            'warning_threshold_pct' => $this->input->post('warning_threshold_pct') ?: 80,
            'critical_threshold_pct' => $this->input->post('critical_threshold_pct') ?: 100
        ];
        
        $target_id = $this->tat->save_tat_target($data);
        
        $this->session->set_flashdata('success', 'TAT target saved');
        redirect('app/diagnostic_safety/tat_targets');
    }

    /* ================================================================== */
    /*  NOTIFICATIONS                                                     */
    /* ================================================================== */

    public function notifications()
    {
        $this->data['title'] = 'Notification Center';
        
        $filters = [
            'status' => $this->input->get('status'),
            'channel_type' => $this->input->get('channel'),
            'date_from' => $this->input->get('date_from'),
            'date_to' => $this->input->get('date_to')
        ];
        
        $this->data['notifications'] = $this->notifications->get_notification_log($filters, 100);
        $this->data['pending'] = $this->notifications->get_pending_notifications(50);
        $this->data['stats'] = $this->notifications->get_notification_stats();
        
        $this->load->view('app/diagnostic_safety/notifications', $this->data);
    }

    public function my_notifications()
    {
        $user_id = $this->session->userdata('user_id');
        
        $this->data['title'] = 'My Notifications';
        $this->data['notifications'] = $this->notifications->get_user_notifications($user_id, false, 50);
        $this->data['unread_count'] = $this->notifications->count_unread_notifications($user_id);
        
        $this->load->view('app/diagnostic_safety/my_notifications', $this->data);
    }

    public function get_unread_count()
    {
        $user_id = $this->session->userdata('user_id');
        $count = $this->notifications->count_unread_notifications($user_id);
        
        echo json_encode(['count' => $count]);
    }

    public function mark_notification_read()
    {
        $queue_id = $this->input->post('queue_id');
        
        if (!$queue_id) {
            echo json_encode(['success' => false]);
            return;
        }
        
        $this->notifications->acknowledge_notification($queue_id);
        echo json_encode(['success' => true]);
    }

    public function process_queue()
    {
        // This would typically be called by a cron job
        $results = $this->notifications->process_notification_queue(50);
        
        echo json_encode([
            'success' => true,
            'sent' => $results['sent'],
            'failed' => $results['failed']
        ]);
    }

    public function process_escalations()
    {
        // This would typically be called by a cron job
        $escalated = $this->notifications->process_escalations();
        
        echo json_encode([
            'success' => true,
            'escalated' => $escalated
        ]);
    }

    /* ================================================================== */
    /*  AUDIT TRAIL                                                       */
    /* ================================================================== */

    public function audit_log()
    {
        $this->data['title'] = 'Audit Trail';
        
        $filters = [
            'event_category' => $this->input->get('category'),
            'severity' => $this->input->get('severity'),
            'user_id' => $this->input->get('user_id'),
            'patient_no' => $this->input->get('patient_no'),
            'date_from' => $this->input->get('date_from') ?: date('Y-m-d', strtotime('-7 days')),
            'date_to' => $this->input->get('date_to') ?: date('Y-m-d'),
            'search' => $this->input->get('search')
        ];
        
        $page = $this->input->get('page') ?: 1;
        $limit = 50;
        $offset = ($page - 1) * $limit;
        
        $this->data['logs'] = $this->audit->search_audit_log($filters, $limit, $offset);
        $this->data['total'] = $this->audit->count_audit_log($filters);
        $this->data['filters'] = $filters;
        $this->data['page'] = $page;
        $this->data['total_pages'] = ceil($this->data['total'] / $limit);
        
        $this->data['stats'] = $this->audit->get_audit_stats($filters['date_from'], $filters['date_to']);
        
        $this->load->view('app/diagnostic_safety/audit_log', $this->data);
    }

    public function audit_detail($audit_id)
    {
        $this->data['title'] = 'Audit Detail';
        
        $log = $this->audit->search_audit_log(['entity_id' => $audit_id], 1);
        $this->data['log'] = $log ? $log[0] : null;
        
        if (!$this->data['log']) {
            $this->session->set_flashdata('error', 'Audit record not found');
            redirect('app/diagnostic_safety/audit_log');
        }
        
        $this->load->view('app/diagnostic_safety/audit_detail', $this->data);
    }

    public function entity_audit($entity_type, $entity_id)
    {
        $this->data['title'] = "Audit Trail: {$entity_type} #{$entity_id}";
        $this->data['entity_type'] = $entity_type;
        $this->data['entity_id'] = $entity_id;
        $this->data['logs'] = $this->audit->get_entity_audit_trail($entity_type, $entity_id);
        
        $this->load->view('app/diagnostic_safety/entity_audit', $this->data);
    }

    public function patient_audit($patient_no)
    {
        $this->data['title'] = "Patient Audit Trail: {$patient_no}";
        $this->data['patient_no'] = $patient_no;
        $this->data['logs'] = $this->audit->get_patient_audit_trail($patient_no);
        
        // Get patient info
        $this->data['patient'] = $this->db->where('patient_no', $patient_no)
            ->get('patient_personal_info')->row();
        
        $this->load->view('app/diagnostic_safety/patient_audit', $this->data);
    }

    public function verify_chain()
    {
        $start_date = $this->input->post('start_date');
        $end_date = $this->input->post('end_date');
        
        $result = $this->audit->verify_chain($start_date, $end_date);
        
        echo json_encode($result);
    }

    public function seal_chain()
    {
        $date = $this->input->post('date');
        
        $result = $this->audit->seal_daily_chain($date);
        
        echo json_encode(['success' => $result]);
    }

    public function chain_status()
    {
        $this->data['title'] = 'Audit Chain Status';
        
        $this->data['chain_status'] = $this->audit->get_chain_status();
        $this->data['recent_verifications'] = $this->audit->get_recent_verifications(20);
        $this->data['retention_policies'] = $this->audit->get_retention_policies();
        
        $this->load->view('app/diagnostic_safety/chain_status', $this->data);
    }

    public function export_audit()
    {
        $filters = [
            'event_category' => $this->input->get('category'),
            'severity' => $this->input->get('severity'),
            'date_from' => $this->input->get('date_from'),
            'date_to' => $this->input->get('date_to')
        ];
        
        $records = $this->audit->export_audit_log($filters);
        
        // Generate CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="audit_export_' . date('Y-m-d_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header row
        fputcsv($output, [
            'Audit ID', 'Timestamp', 'Event Type', 'Category', 'Severity',
            'Entity Type', 'Entity ID', 'Patient No', 'User', 'Action',
            'Change Summary', 'IP Address', 'Hash'
        ]);
        
        foreach ($records as $r) {
            fputcsv($output, [
                $r->audit_id,
                $r->created_at,
                $r->event_type,
                $r->event_category,
                $r->severity,
                $r->entity_type,
                $r->entity_id,
                $r->patient_no,
                $r->username,
                $r->action,
                $r->change_summary,
                $r->ip_address,
                $r->record_hash
            ]);
        }
        
        fclose($output);
    }

    public function compliance_report()
    {
        $date_from = $this->input->get('date_from') ?: date('Y-m-01');
        $date_to = $this->input->get('date_to') ?: date('Y-m-d');
        
        $this->data['title'] = 'Compliance Report';
        $this->data['report'] = $this->audit->generate_compliance_report($date_from, $date_to);
        $this->data['date_from'] = $date_from;
        $this->data['date_to'] = $date_to;
        
        $this->load->view('app/diagnostic_safety/compliance_report', $this->data);
    }

    /* ================================================================== */
    /*  CRON ENDPOINTS                                                    */
    /* ================================================================== */

    public function cron_check_tat()
    {
        // Check for overdue STAT requests
        $overdue = $this->tat->check_overdue_stat_requests();
        
        // Escalate if needed
        $escalated = $this->tat->escalate_stat_requests();
        
        // Generate daily performance
        $this->tat->generate_daily_performance();
        
        echo json_encode([
            'success' => true,
            'overdue_found' => $overdue,
            'escalated' => $escalated
        ]);
    }

    public function cron_process_notifications()
    {
        $results = $this->notifications->process_notification_queue(100);
        $escalated = $this->notifications->process_escalations();
        
        echo json_encode([
            'success' => true,
            'sent' => $results['sent'],
            'failed' => $results['failed'],
            'escalated' => $escalated
        ]);
    }

    public function cron_seal_audit_chain()
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $sealed = $this->audit->seal_daily_chain($yesterday);
        
        echo json_encode([
            'success' => true,
            'sealed' => $sealed,
            'date' => $yesterday
        ]);
    }

    public function cron_verify_audit()
    {
        $result = $this->audit->verify_chain(
            date('Y-m-d', strtotime('-7 days')),
            date('Y-m-d')
        );
        
        echo json_encode($result);
    }

    public function cron_retention()
    {
        $result = $this->audit->apply_retention_policies();
        
        echo json_encode([
            'success' => true,
            'archived' => $result['archived'],
            'deleted' => $result['deleted']
        ]);
    }
}
