<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

/**
 * NHIS Controller
 * 
 * Handles NHIS integration management, claims dashboard, coverage management,
 * and reconciliation UI.
 * 
 * @package     HMS
 * @subpackage  Controllers
 * @category    NHIS Integration
 */
class Nhis extends General {

    public function __construct() {
        parent::__construct();
        $this->load->model('app/nhis_model');
        $this->load->model('app/patient_model');
        $this->load->model('app/governance_model');
        $this->load->config('nhis');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        // Allow admin, cashier, and billing roles to access NHIS functions
        require_role(array('admin', 'cashier', 'billing'));
    }

    private function nhis_export_privilege_defined()
    {
        if (!isset($this->governance_model) || !method_exists($this->governance_model, 'get_privilege_definitions')) {
            return false;
        }

        $defs = $this->governance_model->get_privilege_definitions();
        return is_array($defs) && array_key_exists('nhis_export', $defs);
    }

    private function require_nhis_export_access()
    {
        if ($this->nhis_export_privilege_defined() && function_exists('has_privilege') && !has_privilege('nhis_export')) {
            $this->session->set_flashdata('error', 'You do not have permission to export NHIS data.');
            redirect('app/nhis/claims');
            exit;
        }
    }

    private function log_nhis_export_audit($export_type, $filters)
    {
        try {
            $now = date('Y-m-d H:i:s');
            $user_id = $this->session->userdata('user_id');
            $payload = array(
                'export_type' => (string)$export_type,
                'user_id' => $user_id,
                'timestamp' => $now,
                'filters' => array(
                    'status' => isset($filters['status']) ? $filters['status'] : null,
                    'from_date' => isset($filters['from_date']) ? $filters['from_date'] : null,
                    'to_date' => isset($filters['to_date']) ? $filters['to_date'] : null,
                ),
            );

            if ($this->db->table_exists('nhis_audit_log')) {
                $this->db->insert('nhis_audit_log', array(
                    'action_type' => 'export',
                    'reference_type' => 'claims',
                    'reference_id' => (string)$export_type,
                    'new_value' => json_encode($payload),
                    'api_request' => json_encode($payload['filters']),
                    'status' => 'success',
                    'performed_by' => $user_id,
                    'ip_address' => $this->input->ip_address(),
                    'created_at' => $now,
                ));
                return;
            }

            if ($this->db->table_exists('pharmacy_audit_log')) {
                $this->db->insert('pharmacy_audit_log', array(
                    'event_type'   => 'NHIS_EXPORT_' . strtoupper((string)$export_type),
                    'notes'        => substr(json_encode($payload), 0, 255),
                    'performed_by' => $user_id,
                    'performed_at' => $now,
                ));
            }
        } catch (Exception $e) {
            log_message('error', 'NHIS export audit failed: ' . $e->getMessage());
        }
    }

    /**
     * NHIS Dashboard
     */
    public function index() {
        $this->data['title'] = 'NHIS Dashboard';
        $this->data['page_name'] = 'nhis/dashboard';
        
        // Get summary statistics
        $this->data['stats'] = $this->nhis_model->get_dashboard_stats();
        $this->data['recent_claims'] = $this->nhis_model->get_recent_claims(10);
        $this->data['pending_claims'] = $this->nhis_model->get_claims_by_status('pending', 10);
        $this->data['reconciliation_issues'] = $this->nhis_model->get_open_reconciliation_issues(5);
        $this->data['mode'] = $this->config->item('nhis_mode');
        
        $this->load->view('app/nhis/dashboard', $this->data);
    }

    /**
     * Claims List
     */
    public function claims() {
        $this->data['title'] = 'NHIS Claims';
        $this->data['page_name'] = 'nhis/claims';
        
        $status = $this->input->get('status');
        $from_date = $this->input->get('from_date');
        $to_date = $this->input->get('to_date');
        $search = $this->input->get('search');
        
        $filters = array(
            'status' => $status,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'search' => $search
        );
        
        $this->data['claims'] = $this->nhis_model->get_claims($filters);
        $this->data['filters'] = $filters;
        $this->data['statuses'] = $this->config->item('nhis_claim_statuses');
        
        $this->load->view('app/nhis/claims', $this->data);
    }

    /**
     * View Claim Details
     */
    public function claim($id) {
        $claim = $this->nhis_model->get_claim($id, array('include_quarantined' => true));
        
        if (!$claim) {
            $this->session->set_flashdata('error', 'Claim not found');
            redirect('app/nhis/claims');
            return;
        }
        
        $this->data['title'] = 'Claim Details - ' . $claim->claim_number;
        $this->data['page_name'] = 'nhis/claim_detail';
        $this->data['claim'] = $claim;
        $this->data['items'] = $this->nhis_model->get_claim_items($id);
        $this->data['audit_log'] = $this->nhis_model->get_claim_audit_log($id);
        
        $this->load->view('app/nhis/claim_detail', $this->data);
    }

    /**
     * Submit Claim
     */
    public function submit_claim($id) {
        $user_id = $this->session->userdata('user_id');
        $result = $this->nhis_model->submit_claim($id, $user_id);
        
        if ($result['success']) {
            $this->session->set_flashdata('success', 'Claim submitted successfully');
        } else {
            $this->session->set_flashdata('error', $result['message']);
        }
        
        redirect('app/nhis/claim/' . $id);
    }

    /**
     * Check Claim Status (refresh from NHIS)
     */
    public function check_claim_status($id) {
        $result = $this->nhis_model->check_claim_status($id);
        
        if ($result['success']) {
            $this->session->set_flashdata('success', 'Claim status updated');
        } else {
            $this->session->set_flashdata('error', $result['message']);
        }
        
        redirect('app/nhis/claim/' . $id);
    }

    /**
     * Generate Claim for Encounter
     */
    public function generate_claim($encounter_id) {
        $user_id = $this->session->userdata('user_id');
        $result = $this->nhis_model->generate_claim($encounter_id, $user_id);
        
        if ($result['success']) {
            $this->session->set_flashdata('success', 'Claim generated: ' . $result['claim_number']);
            redirect('app/nhis/claim/' . $result['claim_id']);
        } else {
            $this->session->set_flashdata('error', $result['message']);
            redirect('app/nhis/claims');
        }
    }

    /**
     * Validate NHIS Card (AJAX)
     */
    public function validate_card() {
        $member_id = $this->input->post('member_id');
        $patient_no = $this->input->post('patient_no');
        
        $result = $this->nhis_model->validate_nhis_card($member_id, $patient_no);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Check Coverage (AJAX)
     */
    public function check_coverage() {
        $item_type = $this->input->post('item_type');
        $item_id = $this->input->post('item_id');
        $patient_no = $this->input->post('patient_no');
        
        $result = $this->nhis_model->get_item_coverage($item_type, $item_id);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Calculate Billing Split (AJAX)
     */
    public function calculate_split() {
        $item_type = $this->input->post('item_type');
        $item_id = $this->input->post('item_id');
        $amount = $this->input->post('amount');
        $patient_no = $this->input->post('patient_no');
        
        $result = $this->nhis_model->calculate_billing_split($item_type, $item_id, $amount, $patient_no);
        
        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Coverage Management
     */
    public function coverage() {
        $this->data['title'] = 'NHIS Coverage Management';
        $this->data['page_name'] = 'nhis/coverage';
        
        $item_type = $this->input->get('type') ?: 'drug';
        $search = $this->input->get('search');
        
        $this->data['coverage_items'] = $this->nhis_model->get_coverage_items($item_type, $search);
        $this->data['item_type'] = $item_type;
        $this->data['search'] = $search;
        $this->data['item_types'] = $this->config->item('nhis_item_types');
        
        $this->load->view('app/nhis/coverage', $this->data);
    }

    /**
     * Save Coverage Item (AJAX)
     */
    public function save_coverage() {
        $data = array(
            'item_type' => $this->input->post('item_type'),
            'item_id' => $this->input->post('item_id'),
            'item_name' => $this->input->post('item_name'),
            'nhis_code' => $this->input->post('nhis_code'),
            'coverage_percentage' => $this->input->post('coverage_percentage'),
            'max_limit' => $this->input->post('max_limit') ?: null,
            'requires_preauth' => $this->input->post('requires_preauth') ? 1 : 0,
            'formulary_status' => $this->input->post('formulary_status') ?: 'approved',
            'is_active' => $this->input->post('is_active') ? 1 : 0
        );
        
        $id = $this->input->post('id');
        
        if ($id) {
            $this->db->where('id', $id)->update('nhis_coverage', $data);
        } else {
            $this->db->insert('nhis_coverage', $data);
            $id = $this->db->insert_id();
        }
        
        header('Content-Type: application/json');
        echo json_encode(array('success' => true, 'id' => $id));
    }

    /**
     * Reconciliation Dashboard
     */
    public function reconciliation() {
        $this->data['title'] = 'NHIS Reconciliation';
        $this->data['page_name'] = 'nhis/reconciliation';
        
        $status = $this->input->get('status') ?: 'open';
        $from_date = $this->input->get('from_date');
        $to_date = $this->input->get('to_date');
        
        $this->data['issues'] = $this->nhis_model->get_reconciliation_issues($status, $from_date, $to_date);
        $this->data['status'] = $status;
        $this->data['summary'] = $this->nhis_model->get_reconciliation_summary();
        
        $this->load->view('app/nhis/reconciliation', $this->data);
    }

    /**
     * Run Reconciliation
     */
    public function run_reconciliation() {
        $date = $this->input->post('date') ?: date('Y-m-d');
        $result = $this->nhis_model->run_reconciliation($date);
        
        $this->session->set_flashdata('success', 'Reconciliation completed. Found ' . count($result) . ' issues.');
        redirect('app/nhis/reconciliation');
    }

    /**
     * Resolve Reconciliation Issue
     */
    public function resolve_issue($id) {
        $notes = $this->input->post('notes');
        $user_id = $this->session->userdata('user_id');
        
        $this->db->where('id', $id)->update('nhis_reconciliation', array(
            'status' => 'resolved',
            'resolution_notes' => $notes,
            'resolved_by' => $user_id,
            'resolved_at' => date('Y-m-d H:i:s')
        ));
        
        $this->session->set_flashdata('success', 'Issue resolved');
        redirect('app/nhis/reconciliation');
    }

    /**
     * Audit Log
     */
    public function audit_log() {
        $this->data['title'] = 'NHIS Audit Log';
        $this->data['page_name'] = 'nhis/audit_log';
        
        $action_type = $this->input->get('action');
        $from_date = $this->input->get('from_date');
        $to_date = $this->input->get('to_date');
        $patient_no = $this->input->get('patient_no');
        
        $filters = array(
            'action_type' => $action_type,
            'from_date' => $from_date,
            'to_date' => $to_date,
            'patient_no' => $patient_no
        );
        
        $this->data['logs'] = $this->nhis_model->get_audit_logs($filters, 100);
        $this->data['filters'] = $filters;
        
        $this->load->view('app/nhis/audit_log', $this->data);
    }

    /**
     * NHIS Reports
     */
    public function reports() {
        $this->data['title'] = 'NHIS Reports';
        $this->data['page_name'] = 'nhis/reports';
        
        $report_type = $this->input->get('type') ?: 'claims_summary';
        $from_date = $this->input->get('from_date') ?: date('Y-m-01');
        $to_date = $this->input->get('to_date') ?: date('Y-m-d');
        
        $this->data['report_type'] = $report_type;
        $this->data['from_date'] = $from_date;
        $this->data['to_date'] = $to_date;
        
        switch ($report_type) {
            case 'claims_summary':
                $this->data['report_data'] = $this->nhis_model->get_claims_summary_report($from_date, $to_date);
                break;
            case 'coverage_analysis':
                $this->data['report_data'] = $this->nhis_model->get_coverage_analysis_report($from_date, $to_date);
                break;
            case 'rejection_analysis':
                $this->data['report_data'] = $this->nhis_model->get_rejection_analysis_report($from_date, $to_date);
                break;
            case 'revenue_breakdown':
                $this->data['report_data'] = $this->nhis_model->get_revenue_breakdown_report($from_date, $to_date);
                break;
            default:
                $this->data['report_data'] = array();
        }
        
        $this->load->view('app/nhis/reports', $this->data);
    }

    /**
     * Settings
     */
    public function settings() {
        $this->data['title'] = 'NHIS Settings';
        $this->data['page_name'] = 'nhis/settings';
        
        $this->data['mode'] = $this->config->item('nhis_mode');
        $this->data['facility_code'] = $this->config->item('nhis_facility_code');
        $this->data['config'] = array(
            'cache_hours' => $this->config->item('nhis_cache_eligibility_hours'),
            'auto_submit' => $this->config->item('nhis_claim_auto_submit'),
            'default_coverage' => $this->config->item('nhis_default_coverage_percentage')
        );
        
        $this->load->view('app/nhis/settings', $this->data);
    }

    /**
     * Batch Submit Claims
     */
    public function batch_submit() {
        $claim_ids = $this->input->post('claim_ids');
        $user_id = $this->session->userdata('user_id');
        
        if (!is_array($claim_ids) || empty($claim_ids)) {
            $this->session->set_flashdata('error', 'No claims selected');
            redirect('app/nhis/claims');
        }
        
        $success = 0;
        $failed = 0;
        $errors = array();
        
        // H-NHIS-6: Add exception handling to prevent partial batch corruption
        foreach ($claim_ids as $id) {
            try {
                $result = $this->nhis_model->submit_claim($id, $user_id);
                if ($result['success']) {
                    $success++;
                } else {
                    $failed++;
                    $errors[] = "Claim #{$id}: " . ($result['message'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $failed++;
                $errors[] = "Claim #{$id}: Exception - " . $e->getMessage();
                log_message('error', 'NHIS batch_submit exception for claim ' . $id . ': ' . $e->getMessage());
            }
        }
        
        $message = "Batch submission complete: {$success} succeeded, {$failed} failed";
        if (!empty($errors) && count($errors) <= 5) {
            $message .= ' | ' . implode(' | ', $errors);
        }
        $this->session->set_flashdata($failed > 0 ? 'warning' : 'success', $message);
        redirect('app/nhis/claims');
    }

    /**
     * Batch Check Status
     */
    public function batch_check_status() {
        $claim_ids = $this->input->post('claim_ids');
        
        if (!is_array($claim_ids) || empty($claim_ids)) {
            $this->session->set_flashdata('error', 'No claims selected');
            redirect('app/nhis/claims');
        }
        
        $updated = 0;
        foreach ($claim_ids as $id) {
            $result = $this->nhis_model->check_claim_status($id);
            if ($result['success']) {
                $updated++;
            }
        }
        
        $this->session->set_flashdata('success', "Status checked for {$updated} claims");
        redirect('app/nhis/claims');
    }

    /**
     * Export Claims to CSV
     */
    public function export_claims() {
        // Privilege gate: require nhis_export if it exists, else fall back to role check
        $this->require_nhis_export_access();

        $status = $this->input->get('status');
        $from_date = $this->input->get('from_date');
        $to_date = $this->input->get('to_date');
        
        $filters = array(
            'status' => $status,
            'from_date' => $from_date,
            'to_date' => $to_date
        );

        // Audit trail: record who exported, when, and with which filters
        $this->log_nhis_export_audit('claims_csv', $filters);
        
        $claims = $this->nhis_model->get_claims($filters);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="nhis_claims_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Header row
        fputcsv($output, array(
            'Claim Number', 'Patient No', 'NHIS Member ID', 'Visit Date', 
            'Encounter Type', 'Status', 'Total Amount', 'Approved Amount', 
            'Patient Copay', 'Submitted At', 'NHIS Reference'
        ));
        
        foreach ($claims as $claim) {
            fputcsv($output, array(
                $claim->claim_number,
                $claim->patient_no,
                $claim->nhis_member_id,
                $claim->visit_date,
                $claim->encounter_type,
                $claim->claim_status,
                $claim->total_claim_amount,
                $claim->approved_amount,
                $claim->patient_copay,
                $claim->submitted_at,
                $claim->nhis_reference_id
            ));
        }
        
        fclose($output);
        exit;
    }

    /**
     * Export Claim Line Items to CSV (lab + imaging included) — C3
     */
    public function export_claim_items() {
        // Privilege gate: require nhis_export if it exists, else fall back to role check
        $this->require_nhis_export_access();

        $status     = $this->input->get('status');
        $from_date  = $this->input->get('from_date');
        $to_date    = $this->input->get('to_date');

        $filters = array(
            'status'    => $status,
            'from_date' => $from_date,
            'to_date'   => $to_date,
        );

        // Audit trail: record who exported, when, and with which filters
        $this->log_nhis_export_audit('claim_items_csv', $filters);

        $claims = $this->nhis_model->get_claims($filters);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="nhis_claim_items_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');

        fputcsv($output, array(
            'Claim Number', 'Patient No', 'NHIS Member ID', 'Claim Status',
            'Item Type', 'Item Name', 'NHIS Code', 'Quantity',
            'Unit Price', 'Total Amount', 'NHIS Amount', 'Patient Amount',
            'Coverage %',
        ));

        foreach ($claims as $claim) {
            $items = $this->nhis_model->get_claim_items($claim->id);
            foreach ($items as $item) {
                fputcsv($output, array(
                    $claim->claim_number,
                    $claim->patient_no,
                    isset($claim->nhis_member_id) ? $claim->nhis_member_id : '',
                    isset($claim->status) ? $claim->status : '',
                    isset($item->item_type) ? strtoupper($item->item_type) : '',
                    isset($item->item_name) ? $item->item_name : '',
                    isset($item->nhis_code) ? $item->nhis_code : '',
                    isset($item->quantity)  ? $item->quantity  : 1,
                    isset($item->unit_price)          ? number_format((float)$item->unit_price, 2, '.', '') : '0.00',
                    isset($item->total_amount)         ? number_format((float)$item->total_amount, 2, '.', '') : '0.00',
                    isset($item->nhis_amount)          ? number_format((float)$item->nhis_amount, 2, '.', '') : '0.00',
                    isset($item->patient_amount)       ? number_format((float)$item->patient_amount, 2, '.', '') : '0.00',
                    isset($item->coverage_percentage)  ? number_format((float)$item->coverage_percentage, 2, '.', '') : '0.00',
                ));
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Development/Test utility: create a minimal NHIS batch from existing claims
     * and redirect to XML export. Not intended for production batch management.
     */
    public function create_test_batch()
    {
        // Safety: ensure required tables exist
        if (!$this->db->table_exists('nhis_claims')) {
            show_error('nhis_claims table does not exist. Run NHIS migrations first.', 500);
            return;
        }
        if (
            !$this->db->table_exists('nhis_batches') ||
            !$this->db->table_exists('nhis_batch_claims')
        ) {
            show_error('NHIS batch tables do not exist. Run NHIS Phase 1 migrations first.', 500);
            return;
        }

        // POST: create batch from selected claim numbers
        if (strtoupper($this->input->server('REQUEST_METHOD')) === 'POST') {
            $claim_numbers = $this->input->post('claim_numbers');
            if (!is_array($claim_numbers) || count($claim_numbers) === 0) {
                $this->session->set_flashdata('error', 'No claims selected for test batch.');
                redirect('app/nhis/create_test_batch');
                return;
            }

            // Fetch selected claims – we only need a few fields
            $claims = $this->db
                ->select('claim_number, encounter_id, total_amount')
                ->from('nhis_claims')
                ->where_in('claim_number', $claim_numbers)
                ->get()
                ->result();

            if (!$claims) {
                $this->session->set_flashdata('error', 'Selected claims not found.');
                redirect('app/nhis/create_test_batch');
                return;
            }

            $user_id = $this->session->userdata('user_id');
            if (empty($user_id)) {
                $user_id = null;
            }

            $now   = date('Y-m-d H:i:s');
            $today = date('Y-m-d');
            $year  = date('Y');
            $month = date('m');

            $batch_number = 'TEST-' . date('YmdHis');

            $this->db->trans_start();

            // Insert batch header
            $batch_data = array(
                'batch_number'   => $batch_number,
                'service_year'   => $year,
                'service_month'  => $month,
                'creation_date'  => $today,
                'batch_amount'   => 0.00,
                'claims_count'   => 0,
                'batch_currency' => 'GHS',
                'status'         => 'draft',
                'created_by'     => $user_id,
                'created_at'     => $now,
                'updated_at'     => $now,
            );
            $this->db->insert('nhis_batches', $batch_data);
            $batch_id = (int) $this->db->insert_id();

            $total_amount = 0.0;
            $count        = 0;

            foreach ($claims as $c) {
                $visit_id     = isset($c->encounter_id) ? (int) $c->encounter_id : 0;
                $claim_number = isset($c->claim_number) ? (string) $c->claim_number : '';
                $amount       = isset($c->total_amount) ? (float) $c->total_amount : 0.0;

                if ($visit_id <= 0 || $claim_number === '') {
                    // Dev utility: skip malformed records quietly
                    continue;
                }

                $row = array(
                    'batch_id'                    => $batch_id,
                    'visit_id'                    => $visit_id,
                    'claim_identification_number' => $claim_number,
                    'total_cost'                  => $amount,
                    'treatments_count'            => 0,
                    'medicines_count'             => 0,
                    'validation_status'           => 'pending',
                    'created_at'                  => $now,
                    'updated_at'                  => $now,
                );
                $this->db->insert('nhis_batch_claims', $row);

                $total_amount += $amount;
                $count++;
            }

            // Update batch totals
            $this->db->where('id', $batch_id)->update(
                'nhis_batches',
                array(
                    'batch_amount' => $total_amount,
                    'claims_count' => $count,
                    'updated_at'   => $now,
                )
            );

            $this->db->trans_complete();

            if ($this->db->trans_status() === FALSE) {
                $this->session->set_flashdata('error', 'Failed to create test batch.');
                redirect('app/nhis/create_test_batch');
                return;
            }

            // Redirect to XML export for this batch
            redirect('app/nhis/export_batch_xml/' . $batch_id);
            return;
        }

        // GET: show a very minimal HTML list of recent claims to pick from
        $claims = $this->db
            ->select('claim_number, patient_no, encounter_id, total_amount, created_at')
            ->from('nhis_claims')
            ->order_by('created_at', 'DESC')
            ->limit(20)
            ->get()
            ->result();

        // Simple inline HTML – dev-only, no separate view file
        $error = $this->session->flashdata('error');

        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Create Test NHIS Batch</title></head><body>';
        echo '<h1>Create Test NHIS Batch</h1>';

        if (!empty($error)) {
            echo '<div style="color:red;margin-bottom:10px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        if (!$claims) {
            echo '<p>No claims found. Generate some NHIS claims first.</p>';
            echo '</body></html>';
            return;
        }

        $action = site_url('app/nhis/create_test_batch');
        echo '<form method="post" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
        echo '<table border="1" cellpadding="4" cellspacing="0">';
        echo '<tr>'
           . '<th>Select</th>'
           . '<th>Claim Number</th>'
           . '<th>Patient No</th>'
           . '<th>Encounter ID</th>'
           . '<th>Total Amount</th>'
           . '<th>Created At</th>'
           . '</tr>';

        foreach ($claims as $c) {
            echo '<tr>';
            echo '<td><input type="checkbox" name="claim_numbers[]" value="' . htmlspecialchars((string) $c->claim_number, ENT_QUOTES, 'UTF-8') . '"></td>';
            echo '<td>' . htmlspecialchars((string) $c->claim_number, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars((string) $c->patient_no, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . (int) $c->encounter_id . '</td>';
            echo '<td>' . number_format((float) $c->total_amount, 2) . '</td>';
            echo '<td>' . htmlspecialchars((string) $c->created_at, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '</tr>';
        }

        echo '</table>';
        echo '<p><button type="submit">Create Test Batch &​amp; Export XML</button></p>';
        echo '</form>';
        echo '</body></html>';
    }

    public function export_batch_xml($batch_id = 0)
    {
        $batch_id = (int)$batch_id;
        if ($batch_id <= 0) {
            $this->session->set_flashdata('error', 'Invalid batch selected for XML export');
            redirect('app/nhis/claims');
            return;
        }

        require_once APPPATH . 'services/NHIS/NHISClaimPreValidator.php';
        require_once APPPATH . 'services/NHIS/NHISValidationException.php';
        require_once APPPATH . 'services/NHIS/NHISXMLSchemaException.php';
        require_once APPPATH . 'services/NHIS/NHISClaimXMLGenerator.php';

        try {
            $generator = new NHISClaimXMLGenerator();
            $xml       = $generator->generate($batch_id);

            $filename = 'nhis_batch_' . $batch_id . '_' . date('Ymd_His') . '.xml';

            header('Content-Type: application/xml; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            echo $xml;
            exit;
        } catch (NHISValidationException $e) {
            $this->session->set_flashdata('error', $e->getMessage());
            redirect('app/nhis/claims');
        } catch (NHISXMLSchemaException $e) {
            $this->session->set_flashdata('error', $e->getMessage());
            redirect('app/nhis/claims');
        } catch (Exception $e) {
            log_message('error', 'NHIS export_batch_xml exception for batch ' . $batch_id . ': ' . $e->getMessage());
            $this->session->set_flashdata('error', 'Failed to export NHIS XML batch');
            redirect('app/nhis/claims');
        }
    }
}
