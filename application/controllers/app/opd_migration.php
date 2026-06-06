<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * OPD Status Migration Controller
 * 
 * Migrates existing OPD visits to the unified status engine.
 * Run once during Phase 3 of the OPD unification project.
 * 
 * Access: Admin only
 * URL: /app/opd_migration
 */
class Opd_migration extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model("app/opd_model");
        $this->load->model("app/opd_status_engine");
        $this->load->model("app/billing_model");
        
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin'));
    }

    public function index()
    {
        $this->data['title'] = 'OPD Status Migration';
        $this->data['message'] = $this->session->flashdata('message');
        
        // Get migration stats
        $this->data['stats'] = $this->_get_migration_stats();
        
        $this->load->view('app/opd/migration_dashboard', $this->data);
    }

    /**
     * Get migration statistics
     */
    private function _get_migration_stats()
    {
        $stats = [];
        
        // Total visits
        $q = $this->db->query("SELECT COUNT(*) as cnt FROM patient_details_iop WHERE InActive = 0");
        $stats['total_visits'] = $q ? (int)$q->row()->cnt : 0;
        
        // Visits with workflow records
        $q = $this->db->query("SELECT COUNT(DISTINCT w.iop_id) as cnt 
                              FROM iop_opd_workflow w 
                              JOIN patient_details_iop p ON p.IO_ID = w.iop_id 
                              WHERE w.InActive = 0 AND p.InActive = 0");
        $stats['with_workflow'] = $q ? (int)$q->row()->cnt : 0;
        
        // Visits without workflow records
        $stats['without_workflow'] = $stats['total_visits'] - $stats['with_workflow'];
        
        // Status inconsistencies
        $q = $this->db->query("
            SELECT COUNT(*) as cnt 
            FROM patient_details_iop p
            LEFT JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0
            WHERE p.InActive = 0
            AND (
                (p.nStatus = 'Discharged' AND (w.status IS NULL OR w.status NOT IN ('FINAL_CLEARED','CLINICALLY_CLEARED','COMPLETED')))
                OR (p.clinical_clearance_status = 1 AND (w.status IS NULL OR w.status NOT LIKE '%CLEARED%'))
                OR (p.nStatus = 'Pending' AND w.status IN ('FINAL_CLEARED','COMPLETED'))
            )
        ");
        $stats['inconsistencies'] = $q ? (int)$q->row()->cnt : 0;
        
        // Legacy nStatus distribution
        $q = $this->db->query("SELECT nStatus, COUNT(*) as cnt FROM patient_details_iop WHERE InActive = 0 GROUP BY nStatus");
        $stats['legacy_status'] = $q ? $q->result() : [];
        
        // Workflow status distribution
        $q = $this->db->query("SELECT status, COUNT(*) as cnt FROM iop_opd_workflow WHERE InActive = 0 GROUP BY status ORDER BY cnt DESC");
        $stats['workflow_status'] = $q ? $q->result() : [];
        
        return $stats;
    }

    /**
     * Preview migration changes
     */
    public function preview()
    {
        header('Content-Type: application/json');
        
        $limit = (int)$this->input->get('limit') ?: 100;
        
        // Get visits without workflow or with inconsistencies
        $q = $this->db->query("
            SELECT 
                p.IO_ID,
                p.patient_no,
                p.nStatus as legacy_status,
                p.clinical_clearance_status,
                p.visit_status,
                p.date_visit,
                w.status as workflow_status,
                CASE 
                    WHEN w.iop_id IS NULL THEN 'MISSING_WORKFLOW'
                    WHEN p.nStatus = 'Discharged' AND w.status NOT IN ('FINAL_CLEARED','CLINICALLY_CLEARED','COMPLETED') THEN 'INCONSISTENT'
                    WHEN p.clinical_clearance_status = 1 AND w.status NOT LIKE '%CLEARED%' THEN 'INCONSISTENT'
                    ELSE 'OK'
                END as issue,
                CASE
                    WHEN p.nStatus = 'Discharged' THEN 'FINAL_CLEARED'
                    WHEN p.clinical_clearance_status = 1 THEN 'CLINICALLY_CLEARED'
                    WHEN w.status IS NOT NULL THEN w.status
                    ELSE 'WAITING'
                END as proposed_status
            FROM patient_details_iop p
            LEFT JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0
            WHERE p.InActive = 0
            HAVING issue != 'OK'
            ORDER BY p.date_visit DESC
            LIMIT ?
        ", [$limit]);
        
        $results = $q ? $q->result() : [];
        
        echo json_encode([
            'status' => 'success',
            'count' => count($results),
            'records' => $results
        ]);
    }

    /**
     * Execute migration
     */
    public function execute()
    {
        header('Content-Type: application/json');
        
        if (strtoupper($this->input->method(TRUE)) !== 'POST') {
            echo json_encode(['status' => 'error', 'message' => 'POST required']);
            return;
        }

        $user_id = $this->session->userdata('user_id');
        $dry_run = $this->input->post('dry_run') === '1';
        $batch_size = (int)$this->input->post('batch_size') ?: 500;
        
        $results = [
            'created' => 0,
            'updated' => 0,
            'errors' => [],
            'dry_run' => $dry_run
        ];

        // Start transaction if not dry run
        if (!$dry_run) {
            $this->db->trans_begin();
        }

        try {
            // Step 1: Create workflow records for visits without them
            $results['created'] = $this->_create_missing_workflows($user_id, $dry_run, $batch_size);
            
            // Step 2: Fix inconsistencies
            $results['updated'] = $this->_fix_inconsistencies($user_id, $dry_run, $batch_size);
            
            if (!$dry_run) {
                if ($this->db->trans_status() === false) {
                    $this->db->trans_rollback();
                    $results['errors'][] = 'Database transaction failed';
                } else {
                    $this->db->trans_commit();
                }
            }
            
        } catch (Exception $e) {
            if (!$dry_run) {
                $this->db->trans_rollback();
            }
            $results['errors'][] = $e->getMessage();
        }

        echo json_encode($results);
    }

    /**
     * Create missing workflow records
     */
    private function _create_missing_workflows($user_id, $dry_run, $batch_size)
    {
        $count = 0;
        
        // Get visits without workflow records
        $q = $this->db->query("
            SELECT p.IO_ID, p.patient_no, p.nStatus, p.clinical_clearance_status, p.doctor_id
            FROM patient_details_iop p
            LEFT JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0
            WHERE p.InActive = 0 AND w.iop_id IS NULL
            LIMIT ?
        ", [$batch_size]);
        
        $rows = $q ? $q->result() : [];
        
        foreach ($rows as $row) {
            // Determine status based on legacy fields
            $status = 'WAITING';
            if ($row->nStatus === 'Discharged') {
                $status = 'FINAL_CLEARED';
            } elseif ($row->clinical_clearance_status == 1) {
                $status = 'CLINICALLY_CLEARED';
            }
            
            if (!$dry_run) {
                $this->opd_status_engine->initialize_visit($row->IO_ID, $status, $user_id, 'opd_migration::create_missing_workflows');
            }
            
            $count++;
        }
        
        return $count;
    }

    /**
     * Fix status inconsistencies
     */
    private function _fix_inconsistencies($user_id, $dry_run, $batch_size)
    {
        $count = 0;
        
        // Get inconsistent records
        $q = $this->db->query("
            SELECT p.IO_ID, p.patient_no, p.nStatus, p.clinical_clearance_status, w.status as workflow_status
            FROM patient_details_iop p
            JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0
            WHERE p.InActive = 0
            AND (
                (p.nStatus = 'Discharged' AND w.status NOT IN ('FINAL_CLEARED','CLINICALLY_CLEARED','COMPLETED'))
                OR (p.clinical_clearance_status = 1 AND w.status NOT LIKE '%CLEARED%' AND w.status != 'COMPLETED')
            )
            LIMIT ?
        ", [$batch_size]);
        
        $rows = $q ? $q->result() : [];
        
        foreach ($rows as $row) {
            $new_status = $row->workflow_status;
            
            if ($row->nStatus === 'Discharged') {
                $new_status = 'FINAL_CLEARED';
            } elseif ($row->clinical_clearance_status == 1) {
                $new_status = 'CLINICALLY_CLEARED';
            }
            
            if ($new_status !== $row->workflow_status && !$dry_run) {
                $this->opd_status_engine->transition($row->IO_ID, $new_status, $user_id, 'Sync with legacy status during migration', 'opd_migration::fix_inconsistencies', true);
            }
            
            $count++;
        }
        
        return $count;
    }

	/**
	 * Generate a non-mutating migration report.
	 *
	 * Status writes must run through Opd_status_engine so clearance gates, legacy
	 * field sync, and audit logging remain consistent. Use the controller migration
	 * action for execution; this endpoint only reports what would be touched.
	 */
	public function generate_sql()
	{
		$sql = "-- OPD Status Migration Report\n";
		$sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
		$sql .= "-- This report is intentionally read-only.\n";
		$sql .= "-- Do not update OPD workflow status with direct SQL.\n";
		$sql .= "-- Run the OPD migration action instead; it routes writes through Opd_status_engine.\n\n";
		
		$sql .= "-- Visits without workflow records\n";
		$sql .= "SELECT\n";
		$sql .= "    p.IO_ID,\n";
		$sql .= "    p.patient_no,\n";
		$sql .= "    CASE\n";
		$sql .= "        WHEN p.nStatus = 'Discharged' THEN 'FINAL_CLEARED'\n";
		$sql .= "        WHEN p.clinical_clearance_status = 1 THEN 'CLINICALLY_CLEARED'\n";
		$sql .= "        ELSE 'WAITING'\n";
		$sql .= "    END AS engine_initial_status,\n";
		$sql .= "    p.doctor_id\n";
		$sql .= "FROM patient_details_iop p\n";
		$sql .= "LEFT JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0\n";
		$sql .= "WHERE p.InActive = 0 AND w.iop_id IS NULL;\n\n";
		
		$sql .= "-- Workflow records inconsistent with legacy status\n";
		$sql .= "SELECT\n";
		$sql .= "    p.IO_ID,\n";
		$sql .= "    p.patient_no,\n";
		$sql .= "    p.nStatus,\n";
		$sql .= "    p.clinical_clearance_status,\n";
		$sql .= "    w.status AS workflow_status,\n";
		$sql .= "    CASE\n";
		$sql .= "        WHEN p.nStatus = 'Discharged' THEN 'FINAL_CLEARED'\n";
		$sql .= "        WHEN p.clinical_clearance_status = 1 THEN 'CLINICALLY_CLEARED'\n";
		$sql .= "        ELSE w.status\n";
		$sql .= "    END AS engine_target_status\n";
		$sql .= "FROM patient_details_iop p\n";
		$sql .= "JOIN iop_opd_workflow w ON w.iop_id = p.IO_ID AND w.InActive = 0\n";
		$sql .= "WHERE p.InActive = 0\n";
		$sql .= "AND (\n";
		$sql .= "    (p.nStatus = 'Discharged' AND w.status NOT IN ('FINAL_CLEARED','CLINICALLY_CLEARED','COMPLETED'))\n";
		$sql .= "    OR (p.clinical_clearance_status = 1 AND w.status NOT LIKE '%CLEARED%' AND w.status != 'COMPLETED')\n";
		$sql .= ");\n\n";
		
		$sql .= "-- Verification query\n";
		$sql .= "SELECT \n";
        $sql .= "    'Total Visits' as metric, COUNT(*) as count FROM patient_details_iop WHERE InActive = 0\n";
        $sql .= "UNION ALL\n";
        $sql .= "SELECT \n";
        $sql .= "    'With Workflow', COUNT(DISTINCT w.iop_id) \n";
        $sql .= "FROM iop_opd_workflow w \n";
        $sql .= "JOIN patient_details_iop p ON p.IO_ID = w.iop_id \n";
        $sql .= "WHERE w.InActive = 0 AND p.InActive = 0;\n";
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="opd_migration_' . date('Ymd_His') . '.sql"');
        echo $sql;
    }
}
