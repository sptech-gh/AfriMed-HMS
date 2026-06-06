<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require APPPATH.'controllers/general.php';

/**
 * URL Migration Controller
 * 
 * Handles migration of legacy IDs with spaces to URL-safe format.
 * Admin-only access for database cleanup operations.
 */
class Url_migration extends General {

    public function __construct() {
        parent::__construct();
        if (General::is_logged_in() == FALSE) {
            redirect(base_url().'login');
        }
        General::variable();
        require_role('admin');
    }

    /**
     * Main migration dashboard
     */
    public function index() {
        $this->data['title'] = 'URL Migration Tool';
        $this->data['analysis'] = $this->analyze_ids();
        $this->load->view('app/admin/url_migration', $this->data);
    }

    /**
     * Analyze all IDs in the system for encoding issues
     */
    public function analyze_ids() {
        $results = array(
            'patient_details_iop' => $this->_analyze_table('patient_details_iop', 'IO_ID'),
            'iop_diagnosis' => $this->_analyze_table('iop_diagnosis', 'iop_id'),
            'iop_medication' => $this->_analyze_table('iop_medication', 'iop_id'),
            'iop_laboratory' => $this->_analyze_table('iop_laboratory', 'iop_id'),
            'iop_billing' => $this->_analyze_table('iop_billing', 'iop_id'),
            'iop_receipt' => $this->_analyze_table('iop_receipt', 'iop_id'),
            'iop_vital_sign' => $this->_analyze_table('iop_vital_sign', 'iop_id'),
            'iop_complain' => $this->_analyze_table('iop_complain', 'iop_id'),
        );
        return $results;
    }

    /**
     * Analyze a specific table for IDs with spaces
     */
    private function _analyze_table($table, $column) {
        if (!$this->_table_exists($table)) {
            return array('exists' => false, 'total' => 0, 'with_spaces' => 0, 'samples' => array());
        }
        
        $total = $this->db->count_all($table);
        
        $this->db->select($column);
        $this->db->like($column, ' ', 'both');
        $this->db->limit(10);
        $query = $this->db->get($table);
        $samples = array();
        foreach ($query->result() as $row) {
            $samples[] = $row->$column;
        }
        
        $this->db->like($column, ' ', 'both');
        $with_spaces = $this->db->count_all_results($table);
        
        return array(
            'exists' => true,
            'total' => $total,
            'with_spaces' => $with_spaces,
            'samples' => $samples
        );
    }

    /**
     * Execute migration - remove spaces from IDs
     * POST request with CSRF token required
     */
    public function execute() {
        if ($this->input->method() !== 'post') {
            redirect(base_url().'app/url_migration');
            return;
        }

        $dry_run = $this->input->post('dry_run') === '1';
        $results = array();
        $errors = array();

        // Start transaction
        $this->db->trans_start();

        try {
            // 1. Update patient_details_iop.IO_ID
            $results['patient_details_iop'] = $this->_migrate_column('patient_details_iop', 'IO_ID', $dry_run);

            // 2. Update all related tables
            $related_tables = array(
                'iop_diagnosis' => 'iop_id',
                'iop_medication' => 'iop_id',
                'iop_laboratory' => 'iop_id',
                'iop_billing' => 'iop_id',
                'iop_receipt' => 'iop_id',
                'iop_vital_sign' => 'iop_id',
                'iop_complain' => 'iop_id',
                'iop_scan' => 'iop_id',
                'iop_sonography' => 'iop_id',
                'iop_radiology' => 'iop_id',
                'iop_procedure' => 'iop_id',
                'smart_billing_ledger' => 'iop_id',
                'billing_queue' => 'iop_id',
                'encounter_owners' => 'iop_id',
                'clinical_workflow_status' => 'iop_id',
                'visit_type_assignments' => 'iop_id',
            );

            foreach ($related_tables as $table => $column) {
                if ($this->_table_exists($table)) {
                    $results[$table] = $this->_migrate_column($table, $column, $dry_run);
                }
            }

            if (!$dry_run) {
                $this->db->trans_complete();
                if ($this->db->trans_status() === FALSE) {
                    $errors[] = 'Transaction failed - changes rolled back';
                }
            }

        } catch (Exception $e) {
            $this->db->trans_rollback();
            $errors[] = 'Exception: ' . $e->getMessage();
        }

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => empty($errors),
            'dry_run' => $dry_run,
            'results' => $results,
            'errors' => $errors
        ));
    }

    /**
     * Migrate a single column - remove spaces from IDs
     */
    private function _migrate_column($table, $column, $dry_run = true) {
        if (!$this->_table_exists($table)) {
            return array('skipped' => true, 'reason' => 'Table does not exist');
        }

        // Count affected rows
        $this->db->like($column, ' ', 'both');
        $affected = $this->db->count_all_results($table);

        if ($affected === 0) {
            return array('affected' => 0, 'updated' => 0);
        }

        if ($dry_run) {
            return array('affected' => $affected, 'updated' => 0, 'dry_run' => true);
        }

        // Execute update - remove spaces
        $sql = "UPDATE `{$table}` SET `{$column}` = REPLACE(`{$column}`, ' ', '') WHERE `{$column}` LIKE '% %'";
        $this->db->query($sql);
        $updated = $this->db->affected_rows();

        return array('affected' => $affected, 'updated' => $updated);
    }

    /**
     * Check if table exists
     */
    private function _table_exists($table) {
        return $this->db->table_exists($table);
    }

    /**
     * API endpoint for AJAX analysis
     */
    public function analyze_json() {
        header('Content-Type: application/json');
        echo json_encode($this->analyze_ids());
    }

    /**
     * Generate report of all IDs with encoding issues
     */
    public function report() {
        $this->data['title'] = 'URL Encoding Issues Report';
        $this->data['analysis'] = $this->analyze_ids();
        
        // Get detailed samples
        $this->data['detailed_samples'] = array();
        
        if ($this->_table_exists('patient_details_iop')) {
            $this->db->select('IO_ID, patient_no, patient_type, date_visit');
            $this->db->like('IO_ID', ' ', 'both');
            $this->db->limit(50);
            $this->data['detailed_samples']['patient_details_iop'] = $this->db->get('patient_details_iop')->result();
        }
        
        $this->load->view('app/admin/url_migration_report', $this->data);
    }
}
