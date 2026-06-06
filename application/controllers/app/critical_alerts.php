<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

/**
 * Critical Alerts Controller
 * 
 * Unified dashboard for managing critical alerts across Laboratory, Radiology, and Sonography.
 * Implements doctor acknowledgment enforcement, escalation monitoring, and amendment tracking.
 * 
 * @author Senior Healthcare Systems Architect
 * @version 1.0.0
 */
class Critical_alerts extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/diagnostic_safety_model');
        $this->load->model('app/patient_model');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin', 'laboratory', 'sonographer', 'doctor'));

        if (!$this->session->userdata('_schema_critical_alerts_ok')) {
            $this->diagnostic_safety_model->ensure_phase45_schemas();
            $this->session->set_userdata('_schema_critical_alerts_ok', 1);
        }
    }

    /**
     * Main dashboard - shows all pending critical alerts
     */
    public function index()
    {
        $data['title'] = 'Critical Alerts Dashboard';
        $data['page'] = 'critical_alerts';
        
        // Get all pending alerts
        $data['all_alerts'] = $this->diagnostic_safety_model->get_all_pending_critical_alerts(100);
        
        // Get counts by type
        $data['lab_alerts'] = $this->diagnostic_safety_model->get_pending_lab_critical_alerts(null, 50);
        $data['radiology_alerts'] = $this->diagnostic_safety_model->get_pending_radiology_alerts(null, 50);
        $data['sonography_alerts'] = $this->diagnostic_safety_model->get_pending_sonography_alerts(null, 50);
        
        // Get pending amendments
        $data['pending_amendments'] = $this->diagnostic_safety_model->get_pending_amendments(20);
        
        // Stats
        $data['stats'] = [
            'total_pending' => count($data['all_alerts']),
            'lab_count' => count($data['lab_alerts']),
            'radiology_count' => count($data['radiology_alerts']),
            'sonography_count' => count($data['sonography_alerts']),
            'amendments_pending' => count($data['pending_amendments'])
        ];
        
        $this->load->view('app/critical_alerts/dashboard', $data);
    }

    /**
     * Lab critical alerts list
     */
    public function lab_alerts()
    {
        $data['title'] = 'Laboratory Critical Alerts';
        $data['page'] = 'critical_alerts';
        $data['alerts'] = $this->diagnostic_safety_model->get_pending_lab_critical_alerts(null, 100);
        $this->load->view('app/critical_alerts/lab_alerts', $data);
    }

    /**
     * Radiology critical alerts list
     */
    public function radiology_alerts()
    {
        $data['title'] = 'Radiology Critical Findings';
        $data['page'] = 'critical_alerts';
        $data['alerts'] = $this->diagnostic_safety_model->get_pending_radiology_alerts(null, 100);
        $this->load->view('app/critical_alerts/radiology_alerts', $data);
    }

    /**
     * Sonography critical alerts list
     */
    public function sonography_alerts()
    {
        $data['title'] = 'Sonography Critical Alerts';
        $data['page'] = 'critical_alerts';
        $data['alerts'] = $this->diagnostic_safety_model->get_pending_sonography_alerts(null, 100);
        $this->load->view('app/critical_alerts/sonography_alerts', $data);
    }

    /**
     * Acknowledge a lab critical alert (AJAX)
     */
    public function acknowledge_lab()
    {
        $alert_id = $this->input->post('alert_id');
        $notes = $this->input->post('notes');
        
        if (!$alert_id) {
            echo json_encode(['success' => false, 'message' => 'Alert ID required']);
            return;
        }
        
        $result = $this->diagnostic_safety_model->acknowledge_lab_critical_alert($alert_id, $notes);
        echo json_encode(['success' => $result, 'message' => $result ? 'Alert acknowledged' : 'Failed to acknowledge']);
    }

    /**
     * Acknowledge a radiology critical alert (AJAX)
     */
    public function acknowledge_radiology()
    {
        $alert_id = $this->input->post('alert_id');
        $notes = $this->input->post('notes');
        
        if (!$alert_id) {
            echo json_encode(['success' => false, 'message' => 'Alert ID required']);
            return;
        }
        
        $result = $this->diagnostic_safety_model->acknowledge_radiology_alert($alert_id, $notes);
        echo json_encode(['success' => $result, 'message' => $result ? 'Alert acknowledged' : 'Failed to acknowledge']);
    }

    /**
     * Acknowledge a sonography critical alert (AJAX)
     */
    public function acknowledge_sonography()
    {
        $alert_id = $this->input->post('alert_id');
        $notes = $this->input->post('notes');
        
        if (!$alert_id) {
            echo json_encode(['success' => false, 'message' => 'Alert ID required']);
            return;
        }
        
        $result = $this->diagnostic_safety_model->acknowledge_sonography_alert($alert_id, $notes);
        echo json_encode(['success' => $result, 'message' => $result ? 'Alert acknowledged' : 'Failed to acknowledge']);
    }

    /**
     * Check if patient can be discharged (AJAX)
     */
    public function check_discharge()
    {
        $patient_no = $this->input->post('patient_no');
        
        if (!$patient_no) {
            echo json_encode(['success' => false, 'message' => 'Patient number required']);
            return;
        }
        
        $can_discharge = $this->diagnostic_safety_model->can_discharge_patient($patient_no);
        $blocking_alerts = [];
        
        if (!$can_discharge) {
            $blocking_alerts = $this->diagnostic_safety_model->get_discharge_blocking_alerts($patient_no);
        }
        
        echo json_encode([
            'success' => true,
            'can_discharge' => $can_discharge,
            'blocking_alerts' => $blocking_alerts,
            'message' => $can_discharge ? 'Patient can be discharged' : 'Patient has unacknowledged critical alerts'
        ]);
    }

    /**
     * Get patient's unacknowledged alerts (AJAX)
     */
    public function patient_alerts()
    {
        $patient_no = $this->input->post('patient_no');
        
        if (!$patient_no) {
            echo json_encode(['success' => false, 'message' => 'Patient number required']);
            return;
        }
        
        $alerts = $this->diagnostic_safety_model->get_unacknowledged_critical_alerts($patient_no);
        $count = $this->diagnostic_safety_model->count_unacknowledged_critical_alerts($patient_no);
        
        echo json_encode([
            'success' => true,
            'count' => $count,
            'alerts' => $alerts
        ]);
    }

    /**
     * Amendments management page
     */
    public function amendments()
    {
        $data['title'] = 'Result Amendments';
        $data['page'] = 'critical_alerts';
        $data['pending_amendments'] = $this->diagnostic_safety_model->get_pending_amendments(100);
        $this->load->view('app/critical_alerts/amendments', $data);
    }

    /**
     * Approve an amendment (AJAX)
     */
    public function approve_amendment()
    {
        $amendment_id = $this->input->post('amendment_id');
        
        if (!$amendment_id) {
            echo json_encode(['success' => false, 'message' => 'Amendment ID required']);
            return;
        }
        
        $result = $this->diagnostic_safety_model->approve_amendment($amendment_id);
        echo json_encode(['success' => $result, 'message' => $result ? 'Amendment approved' : 'Failed to approve']);
    }

    /**
     * Reject an amendment (AJAX)
     */
    public function reject_amendment()
    {
        $amendment_id = $this->input->post('amendment_id');
        $reason = $this->input->post('reason');
        
        if (!$amendment_id) {
            echo json_encode(['success' => false, 'message' => 'Amendment ID required']);
            return;
        }
        
        if (!$reason) {
            echo json_encode(['success' => false, 'message' => 'Rejection reason required']);
            return;
        }
        
        $result = $this->diagnostic_safety_model->reject_amendment($amendment_id, $reason);
        echo json_encode(['success' => $result, 'message' => $result ? 'Amendment rejected' : 'Failed to reject']);
    }

    /**
     * Request a result amendment (AJAX)
     */
    public function request_amendment()
    {
        $io_lab_id = $this->input->post('io_lab_id');
        $diagnostic_type = $this->input->post('diagnostic_type');
        $original_result = $this->input->post('original_result');
        $amended_result = $this->input->post('amended_result');
        $reason = $this->input->post('reason');
        
        if (!$io_lab_id || !$diagnostic_type || !$amended_result || !$reason) {
            echo json_encode(['success' => false, 'message' => 'All fields are required']);
            return;
        }
        
        $amendment_id = $this->diagnostic_safety_model->request_amendment(
            $io_lab_id, $diagnostic_type, $original_result, $amended_result, $reason
        );
        
        echo json_encode([
            'success' => (bool)$amendment_id,
            'amendment_id' => $amendment_id,
            'message' => $amendment_id ? 'Amendment requested successfully' : 'Failed to request amendment'
        ]);
    }

    /**
     * Run escalation check (cron endpoint)
     */
    public function run_escalation()
    {
        // This should be called by a cron job
        $escalated = $this->diagnostic_safety_model->check_and_escalate_alerts();
        
        echo json_encode([
            'success' => true,
            'escalated' => $escalated,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get alert counts for header badge (AJAX)
     */
    public function get_alert_counts()
    {
        $all_alerts = $this->diagnostic_safety_model->get_all_pending_critical_alerts(500);
        
        $counts = [
            'total' => count($all_alerts),
            'life_threatening' => 0,
            'critical' => 0,
            'urgent' => 0
        ];
        
        foreach ($all_alerts as $alert) {
            $sev = strtoupper($alert['severity'] ?? '');
            if ($sev === 'LIFE_THREATENING' || $sev === 'PANIC') {
                $counts['life_threatening']++;
            } elseif ($sev === 'CRITICAL') {
                $counts['critical']++;
            } elseif ($sev === 'URGENT') {
                $counts['urgent']++;
            }
        }
        
        echo json_encode($counts);
    }

    /**
     * Escalation configuration page
     */
    public function escalation_config()
    {
        $data['title'] = 'Escalation Configuration';
        $data['page'] = 'critical_alerts';
        
        $this->db->where('InActive', 0)->order_by('escalation_level', 'ASC');
        $data['configs'] = $this->db->get('diagnostic_escalation_config')->result();
        
        $this->load->view('app/critical_alerts/escalation_config', $data);
    }

    /**
     * Save escalation config (AJAX)
     */
    public function save_escalation_config()
    {
        $config_id = $this->input->post('config_id');
        $timeout_minutes = $this->input->post('timeout_minutes');
        $notification_method = $this->input->post('notification_method');
        $is_active = $this->input->post('is_active');
        
        if (!$config_id) {
            echo json_encode(['success' => false, 'message' => 'Config ID required']);
            return;
        }
        
        $this->db->where('config_id', (int)$config_id)->update('diagnostic_escalation_config', [
            'timeout_minutes' => (int)$timeout_minutes,
            'notification_method' => $notification_method,
            'is_active' => (int)$is_active
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Configuration saved']);
    }

    /**
     * Critical findings definitions management
     */
    public function radiology_findings()
    {
        $data['title'] = 'Radiology Critical Findings Definitions';
        $data['page'] = 'critical_alerts';
        
        $this->db->where('InActive', 0)->order_by('severity', 'ASC');
        $data['findings'] = $this->db->get('radiology_critical_findings')->result();
        
        $this->load->view('app/critical_alerts/radiology_findings', $data);
    }

    /**
     * Sonography alert definitions management
     */
    public function sonography_definitions()
    {
        $data['title'] = 'Sonography Critical Alert Definitions';
        $data['page'] = 'critical_alerts';
        
        $this->db->where('InActive', 0)->order_by('severity', 'ASC');
        $data['definitions'] = $this->db->get('sonography_critical_definitions')->result();
        
        $this->load->view('app/critical_alerts/sonography_definitions', $data);
    }
}
