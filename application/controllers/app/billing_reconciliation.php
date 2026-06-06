<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

/**
 * Billing Reconciliation Controller
 * 
 * Provides admin tools for:
 * - Viewing reconciliation issues
 * - Running reconciliation checks
 * - Migrating existing data to unified billing system
 * - Viewing patient financial ledger
 */
class Billing_reconciliation extends General {

    public function __construct(){
        parent::__construct();
        $this->load->model('app/billing_model');
        $this->load->model('app/billing_transaction_model');
        $this->load->model('app/pharmacy_model');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin'));

        if (!$this->session->userdata('_schema_billing_recon_ok')) {
            $this->billing_transaction_model->ensure_billing_transaction_schema();
            $this->session->set_userdata('_schema_billing_recon_ok', 1);
        }
    }

    /**
     * Main dashboard - reconciliation overview
     * DEPRECATED: Redirects to unified cashier reconciliation
     */
    public function index(){
        // DEPRECATED: Merged into cashier/reconciliation
        redirect(base_url() . 'app/cashier/reconciliation');
        return;
        
        /* Original code preserved:
        $data = array();
        
        // Get reconciliation issues
        $data['issues'] = $this->billing_transaction_model->get_reconciliation_issues(array('limit' => 50));
        
        // Get department summaries
        $today = date('Y-m-d');
        $data['pharmacy_summary'] = $this->billing_transaction_model->get_department_daily_summary('PHARMACY', $today);
        $data['lab_summary'] = $this->billing_transaction_model->get_department_daily_summary('LABORATORY', $today);
        
        // Count issues by type
        $data['issue_counts'] = array(
            'dispensed_not_billed' => 0,
            'billed_not_dispensed' => 0,
            'paid_not_completed' => 0,
            'other' => 0
        );
        foreach ($data['issues'] as $issue) {
            $type = $issue->issue_type;
            if (isset($data['issue_counts'][$type])) {
                $data['issue_counts'][$type]++;
            } else {
                $data['issue_counts']['other']++;
            }
        }
        
        $this->load->view('app/billing/reconciliation_dashboard', $data);
        */
    }

    /**
     * Run reconciliation check
     */
    public function run_check(){
        $date = $this->input->get('date') ? $this->input->get('date') : date('Y-m-d');
        $department = $this->input->get('department') ? strtoupper($this->input->get('department')) : null;
        
        $issues = $this->billing_transaction_model->run_reconciliation($department, $date);
        
        $this->session->set_flashdata('success', 'Reconciliation check completed. Found ' . count($issues) . ' issues.');
        redirect('app/billing_reconciliation');
    }

    /**
     * Migrate existing pharmacy data to unified billing
     */
    public function migrate_pharmacy(){
        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $user_id = $this->session->userdata('user_id');
        
        // Get medications not yet in billing_transactions
        $sql = "SELECT M.iop_med_id, M.iop_id, M.patient_no
                FROM iop_medication M
                LEFT JOIN billing_transactions BT ON BT.item_ref = CONCAT('iop_med_id:', M.iop_med_id) AND BT.department = 'PHARMACY' AND BT.InActive = 0
                WHERE M.InActive = 0 AND BT.txn_id IS NULL
                ORDER BY M.iop_med_id DESC
                LIMIT " . $limit;
        
        $meds = $this->db->query($sql)->result();
        
        $synced = 0;
        $errors = array();
        
        foreach ($meds as $med) {
            $result = $this->pharmacy_model->sync_medication_to_billing_transactions($med->iop_med_id, $user_id);
            if ($result['ok']) {
                $synced++;
            } else {
                $errors[] = 'Med #' . $med->iop_med_id . ': ' . (isset($result['error']) ? $result['error'] : 'Unknown');
            }
        }
        
        $msg = "Migrated $synced pharmacy records to unified billing.";
        if (!empty($errors)) {
            $msg .= ' Errors: ' . count($errors);
        }
        
        $this->session->set_flashdata($synced > 0 ? 'success' : 'warning', $msg);
        redirect('app/billing_reconciliation');
    }

    /**
     * Sync payments from iop_receipt to unified billing
     */
    public function sync_payments(){
        $limit = $this->input->get('limit') ? (int)$this->input->get('limit') : 100;
        $user_id = $this->session->userdata('user_id');
        
        // Get recent receipts
        $this->db->select('receipt_no');
        $this->db->from('iop_receipt');
        $this->db->where('InActive', 0);
        $this->db->order_by('receipt_no', 'DESC');
        $this->db->limit($limit);
        $receipts = $this->db->get()->result();
        
        $synced = 0;
        $errors = array();
        
        foreach ($receipts as $r) {
            $result = $this->billing_model->sync_receipt_to_unified($r->receipt_no, $user_id);
            if (isset($result['ok']) && $result['ok']) {
                $synced++;
            }
        }
        
        $this->session->set_flashdata('success', "Synced $synced payment records to unified billing.");
        redirect('app/billing_reconciliation');
    }

    /**
     * View patient financial ledger
     */
    public function patient_ledger($patient_no = null){
        if (!$patient_no) {
            $patient_no = $this->input->get('patient_no');
        }
        
        $data = array();
        $data['patient_no'] = $patient_no;
        $data['ledger'] = array();
        $data['balance'] = 0;
        $data['patient'] = null;
        
        if ($patient_no) {
            $data['ledger'] = $this->billing_transaction_model->get_patient_ledger($patient_no, 200);
            $data['balance'] = $this->billing_transaction_model->get_patient_balance($patient_no);
            $data['patient'] = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no))->row();
        }
        
        $this->load->view('app/billing/patient_ledger', $data);
    }

    /**
     * Resolve a reconciliation issue
     */
    public function resolve_issue(){
        $recon_id = $this->input->post('recon_id');
        $notes = $this->input->post('notes');
        $user_id = $this->session->userdata('user_id');
        
        if (!$recon_id) {
            $this->session->set_flashdata('error', 'Invalid issue ID');
            redirect('app/billing_reconciliation');
            return;
        }
        
        $this->db->where('recon_id', (int)$recon_id);
        $this->db->update('billing_reconciliation_log', array(
            'resolved' => 1,
            'resolved_by' => $user_id,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolution_notes' => $notes
        ));
        
        $this->session->set_flashdata('success', 'Issue marked as resolved.');
        redirect('app/billing_reconciliation');
    }

    /**
     * API: Get encounter billing summary
     */
    public function encounter_summary_json(){
        $iop_id = $this->input->get('iop_id');
        
        if (!$iop_id) {
            echo json_encode(array('ok' => false, 'error' => 'Missing iop_id'));
            return;
        }
        
        $summary = $this->billing_transaction_model->get_encounter_summary($iop_id);
        echo json_encode(array('ok' => true, 'summary' => $summary));
    }

    /**
     * Audit-preserving migration of legacy IPD_BED_SIDE / IPD_OT locks.
     * Forward-only: deactivates orphan legacy locks (no rewrite).
     */
    public function migrate_legacy_procedure_locks(){
        $user_id = $this->session->userdata('user_id');
        $this->load->model('app/billing_model');
        $result = $this->billing_model->migrate_legacy_procedure_locks($user_id);
        $bs = isset($result['bedside_deactivated']) ? (int)$result['bedside_deactivated'] : 0;
        $ot = isset($result['ot_deactivated']) ? (int)$result['ot_deactivated'] : 0;
        $errs = isset($result['errors']) && is_array($result['errors']) ? implode('; ', $result['errors']) : '';
        $msg = "Legacy procedure locks deactivated. Bedside: {$bs}. OT: {$ot}." . ($errs !== '' ? " Errors: {$errs}" : '');
        $this->session->set_flashdata(($bs + $ot > 0) ? 'success' : 'warning', $msg);
        redirect('app/billing_reconciliation/legacy_procedure_locks');
    }

    /**
     * Reconciliation listing of deactivated legacy procedure locks.
     */
    public function legacy_procedure_locks(){
        $this->load->model('app/billing_model');
        $limit = (int)$this->input->get('limit');
        if ($limit <= 0) { $limit = 200; }
        $data = array();
        $data['locks'] = $this->billing_model->get_legacy_procedure_locks($limit);
        $data['limit'] = $limit;
        $data['flash_success'] = $this->session->flashdata('success');
        $data['flash_warning'] = $this->session->flashdata('warning');
        $this->load->view('app/billing/legacy_procedure_locks', $data);
    }

    /**
     * Full data migration - run all sync operations
     */
    public function full_migration(){
        $user_id = $this->session->userdata('user_id');
        $results = array();
        
        // 1. Migrate pharmacy medications
        $sql = "SELECT COUNT(*) as c FROM iop_medication M
                LEFT JOIN billing_transactions BT ON BT.item_ref = CONCAT('iop_med_id:', M.iop_med_id) AND BT.department = 'PHARMACY' AND BT.InActive = 0
                WHERE M.InActive = 0 AND BT.txn_id IS NULL";
        $pending_meds = $this->db->query($sql)->row()->c;
        
        $synced_meds = 0;
        if ($pending_meds > 0) {
            $meds = $this->db->query(str_replace('COUNT(*) as c', 'M.iop_med_id', $sql) . ' LIMIT 500')->result();
            foreach ($meds as $med) {
                $r = $this->pharmacy_model->sync_medication_to_billing_transactions($med->iop_med_id, $user_id);
                if ($r['ok']) $synced_meds++;
            }
        }
        $results['pharmacy'] = array('pending' => $pending_meds, 'synced' => $synced_meds);
        
        // 2. Run reconciliation
        $issues = $this->billing_transaction_model->run_reconciliation(null, date('Y-m-d'));
        $results['reconciliation'] = array('issues_found' => count($issues));
        
        $this->session->set_flashdata('success', 
            "Migration complete. Pharmacy: {$synced_meds}/{$pending_meds} synced. " .
            "Reconciliation: " . count($issues) . " issues found."
        );
        redirect('app/billing_reconciliation');
    }
}
