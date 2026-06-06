<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once APPPATH . 'controllers/general.php';

/**
 * Sample Tracking Controller
 * 
 * Chain-of-Custody Tracking, Sample Movement Audit, Recollection Workflow,
 * and Delta Check Intelligence management.
 * 
 * @author Senior Healthcare Systems Architect
 * @version 1.0.0
 */
class Sample_tracking extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/diagnostic_safety_model');
        $this->load->model('app/laboratory_model');

        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        General::variable();
        require_role(array('admin', 'laboratory', 'sonographer'));

        if (!$this->session->userdata('_schema_sample_tracking_ok')) {
            $this->diagnostic_safety_model->ensure_phase5_schemas();
            $this->session->set_userdata('_schema_sample_tracking_ok', 1);
        }
    }

    /**
     * Main dashboard - Sample tracking overview
     */
    public function index()
    {
        $data['title'] = 'Sample Tracking Dashboard';
        $data['currentTab'] = 'sample_tracking';

        // Get statistics
        $data['pending_samples'] = $this->_count_samples_by_status('REQUESTED');
        $data['in_transit'] = $this->_count_samples_in_transit();
        $data['pending_recollections'] = count($this->diagnostic_safety_model->get_pending_recollections(100));
        $data['temperature_breaches'] = $this->_count_temperature_breaches();
        $data['pending_delta_reviews'] = count($this->diagnostic_safety_model->get_pending_delta_reviews(100));

        // Get recent samples
        $data['recent_samples'] = $this->_get_recent_samples(20);
        
        // Get pending recollections
        $data['recollections'] = $this->diagnostic_safety_model->get_pending_recollections(10);

        // Get flagged delta checks
        $data['delta_reviews'] = $this->diagnostic_safety_model->get_pending_delta_reviews(10);

        $this->load->view('app/sample_tracking/dashboard', $data);
    }

    /**
     * Chain of Custody view
     */
    public function custody()
    {
        $data['title'] = 'Chain of Custody';
        $data['currentTab'] = 'sample_tracking';
        $data['locations'] = $this->diagnostic_safety_model->get_sample_locations();
        $data['samples'] = $this->_get_active_samples(50);
        
        $this->load->view('app/sample_tracking/custody', $data);
    }

    /**
     * View custody chain for a specific sample
     */
    public function custody_chain($sample_id)
    {
        $data['title'] = 'Sample Custody Chain';
        $data['currentTab'] = 'sample_tracking';
        
        $data['sample'] = $this->db->get_where('lab_sample_tracking', ['sample_id' => (int)$sample_id, 'InActive' => 0])->row();
        if (!$data['sample']) {
            $this->session->set_flashdata('error', 'Sample not found');
            redirect('app/sample_tracking/custody');
        }

        $data['chain'] = $this->diagnostic_safety_model->get_sample_custody_chain($sample_id);
        $data['verification'] = $this->diagnostic_safety_model->verify_custody_chain($sample_id);
        $data['temperature_log'] = $this->_get_sample_temperature_log($sample_id);
        $data['movement_history'] = $this->diagnostic_safety_model->get_sample_movement_history($sample_id);

        $this->load->view('app/sample_tracking/custody_chain', $data);
    }

    /**
     * Record sample handoff (AJAX)
     */
    public function record_handoff()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $sample_id = $this->input->post('sample_id');
        $to_user_id = $this->input->post('to_user_id');
        $to_location = $this->input->post('to_location');
        $handoff_type = $this->input->post('handoff_type');
        $temperature = $this->input->post('temperature');
        $notes = $this->input->post('notes');

        if (!$sample_id || !$to_user_id || !$to_location || !$handoff_type) {
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            return;
        }

        $result = $this->diagnostic_safety_model->record_sample_handoff(
            $sample_id, $to_user_id, $to_location, $handoff_type,
            $temperature ? (float)$temperature : null, $notes
        );

        echo json_encode($result);
    }

    /**
     * Log temperature reading (AJAX)
     */
    public function log_temperature()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $sample_id = $this->input->post('sample_id');
        $barcode = $this->input->post('barcode');
        $location = $this->input->post('location');
        $temperature = $this->input->post('temperature');
        $humidity = $this->input->post('humidity');

        if (!$sample_id || !$temperature) {
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            return;
        }

        $status = $this->diagnostic_safety_model->log_sample_temperature(
            $sample_id, $barcode, $location, (float)$temperature, null, $humidity ? (float)$humidity : null
        );

        echo json_encode(['ok' => true, 'status' => $status]);
    }

    /**
     * Scan sample barcode (AJAX)
     */
    public function scan_barcode()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $barcode = $this->input->post('barcode');
        if (!$barcode) {
            echo json_encode(['ok' => false, 'error' => 'Barcode required']);
            return;
        }

        $sample = $this->diagnostic_safety_model->get_sample_by_barcode($barcode);
        if (!$sample) {
            echo json_encode(['ok' => false, 'error' => 'Sample not found']);
            return;
        }

        // Get patient info
        $patient = $this->db->get_where('patient_personal_info', ['patient_no' => $sample->patient_no])->row();

        echo json_encode([
            'ok' => true,
            'sample' => $sample,
            'patient_name' => $patient ? $patient->firstname . ' ' . $patient->lastname : 'Unknown'
        ]);
    }

    /**
     * Recollection requests management
     */
    public function recollections()
    {
        $data['title'] = 'Recollection Requests';
        $data['currentTab'] = 'sample_tracking';
        
        $status = $this->input->get('status') ?: 'PENDING';
        $data['current_status'] = $status;
        
        if ($status === 'ALL') {
            $data['recollections'] = $this->_get_all_recollections(100);
        } else {
            $data['recollections'] = $this->_get_recollections_by_status($status, 100);
        }

        $data['rejection_reasons'] = $this->diagnostic_safety_model->get_rejection_reasons();

        $this->load->view('app/sample_tracking/recollections', $data);
    }

    /**
     * Reject sample and create recollection request
     */
    public function reject_sample()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            redirect(base_url() . 'access_denied');
            return;
        }

        $sample_id = $this->input->post('sample_id');
        $reason_id = $this->input->post('reason_id');
        $notes = $this->input->post('notes');
        $priority = $this->input->post('priority') ?: 'ROUTINE';

        if (!$sample_id || !$reason_id) {
            $this->session->set_flashdata('error', 'Sample ID and rejection reason required');
            redirect('app/sample_tracking/custody');
        }

        $result = $this->diagnostic_safety_model->reject_sample_with_recollection($sample_id, $reason_id, $notes, $priority);

        if ($result['ok']) {
            $msg = 'Sample rejected successfully.';
            if ($result['recollection_required']) {
                $msg .= ' Recollection request #' . $result['recollection_id'] . ' created.';
            }
            $this->session->set_flashdata('success', $msg);
        } else {
            $this->session->set_flashdata('error', $result['error']);
        }

        redirect('app/sample_tracking/recollections');
    }

    /**
     * Update recollection status (AJAX)
     */
    public function update_recollection()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $recollection_id = $this->input->post('recollection_id');
        $status = $this->input->post('status');
        $notes = $this->input->post('notes');

        if (!$recollection_id || !$status) {
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            return;
        }

        $result = $this->diagnostic_safety_model->update_recollection_status($recollection_id, $status, $notes);
        echo json_encode(['ok' => $result]);
    }

    /**
     * Delta Check Reviews
     */
    public function delta_checks()
    {
        $data['title'] = 'Delta Check Reviews';
        $data['currentTab'] = 'sample_tracking';
        
        $data['pending_reviews'] = $this->diagnostic_safety_model->get_pending_delta_reviews(50);
        $data['thresholds'] = $this->diagnostic_safety_model->get_delta_thresholds();

        $this->load->view('app/sample_tracking/delta_checks', $data);
    }

    /**
     * Delta threshold configuration
     */
    public function delta_thresholds()
    {
        $data['title'] = 'Delta Check Thresholds';
        $data['currentTab'] = 'sample_tracking';
        $data['thresholds'] = $this->diagnostic_safety_model->get_delta_thresholds(false);

        $this->load->view('app/sample_tracking/delta_thresholds', $data);
    }

    /**
     * Save delta threshold (AJAX)
     */
    public function save_threshold()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $threshold_id = $this->input->post('threshold_id');
        $data = [
            'test_name' => $this->input->post('test_name'),
            'test_code' => $this->input->post('test_code'),
            'delta_percent_warning' => $this->input->post('delta_percent_warning'),
            'delta_percent_critical' => $this->input->post('delta_percent_critical'),
            'delta_absolute_warning' => $this->input->post('delta_absolute_warning') ?: null,
            'delta_absolute_critical' => $this->input->post('delta_absolute_critical') ?: null,
            'time_window_hours' => $this->input->post('time_window_hours') ?: 72,
            'unit' => $this->input->post('unit'),
            'clinical_significance' => $this->input->post('clinical_significance'),
            'auto_notify_doctor' => $this->input->post('auto_notify_doctor') ? 1 : 0,
            'requires_review' => $this->input->post('requires_review') ? 1 : 0,
            'is_active' => $this->input->post('is_active') ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($threshold_id) {
            $this->db->where('threshold_id', (int)$threshold_id)->update('lab_delta_thresholds', $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['InActive'] = 0;
            $this->db->insert('lab_delta_thresholds', $data);
            $threshold_id = $this->db->insert_id();
        }

        echo json_encode(['ok' => true, 'threshold_id' => $threshold_id]);
    }

    /**
     * Override delta check (AJAX)
     */
    public function override_delta()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $delta_id = $this->input->post('delta_id');
        $override_type = $this->input->post('override_type');
        $clinical_reason = $this->input->post('clinical_reason');
        $diagnosis = $this->input->post('diagnosis');

        if (!$delta_id || !$override_type || !$clinical_reason) {
            echo json_encode(['ok' => false, 'error' => 'Missing required fields']);
            return;
        }

        $result = $this->diagnostic_safety_model->override_delta_check($delta_id, $override_type, $clinical_reason, $diagnosis);
        echo json_encode($result);
    }

    /**
     * Doctor delta notifications
     */
    public function my_delta_notifications()
    {
        $data['title'] = 'My Delta Check Notifications';
        $data['currentTab'] = 'sample_tracking';
        
        $doctor_id = $this->session->userdata('user_id');
        $data['notifications'] = $this->diagnostic_safety_model->get_doctor_delta_notifications($doctor_id, false, 50);

        $this->load->view('app/sample_tracking/delta_notifications', $data);
    }

    /**
     * Acknowledge delta notification (AJAX)
     */
    public function acknowledge_delta_notification()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $notification_id = $this->input->post('notification_id');
        $notes = $this->input->post('notes');

        if (!$notification_id) {
            echo json_encode(['ok' => false, 'error' => 'Notification ID required']);
            return;
        }

        $result = $this->diagnostic_safety_model->acknowledge_delta_notification($notification_id, $notes);
        echo json_encode(['ok' => $result]);
    }

    /**
     * Location management
     */
    public function locations()
    {
        $data['title'] = 'Sample Locations';
        $data['currentTab'] = 'sample_tracking';
        $data['locations'] = $this->diagnostic_safety_model->get_sample_locations();

        $this->load->view('app/sample_tracking/locations', $data);
    }

    /**
     * Save location (AJAX)
     */
    public function save_location()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        if ($this->input->method(TRUE) !== 'POST') {
            show_error('Invalid request', 400);
        }

        $location_id = $this->input->post('location_id');
        $data = [
            'location_code' => $this->input->post('location_code'),
            'location_name' => $this->input->post('location_name'),
            'location_type' => $this->input->post('location_type'),
            'department' => $this->input->post('department'),
            'building' => $this->input->post('building'),
            'floor' => $this->input->post('floor'),
            'room' => $this->input->post('room'),
            'temperature_required' => $this->input->post('temperature_required') ?: null,
            'temperature_tolerance' => $this->input->post('temperature_tolerance') ?: null,
            'is_active' => $this->input->post('is_active') ? 1 : 0
        ];

        if ($location_id) {
            $this->db->where('location_id', (int)$location_id)->update('lab_sample_locations', $data);
        } else {
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['InActive'] = 0;
            $this->db->insert('lab_sample_locations', $data);
            $location_id = $this->db->insert_id();
        }

        echo json_encode(['ok' => true, 'location_id' => $location_id]);
    }

    /**
     * Get users for handoff (AJAX)
     */
    public function get_users()
    {
        if (!$this->input->is_ajax_request()) {
            show_error('Invalid request', 400);
        }

        $users = $this->db->select('user_id, username, firstname, lastname')
            ->where('InActive', 0)
            ->order_by('username', 'ASC')
            ->get('user')->result();

        echo json_encode(['ok' => true, 'users' => $users]);
    }

    /* ================================================================== */
    /*  PRIVATE HELPER METHODS                                             */
    /* ================================================================== */

    private function _count_samples_by_status($status)
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_sample_tracking')) return 0;
        return $this->db->where(['sample_status' => $status, 'InActive' => 0])->count_all_results('lab_sample_tracking');
    }

    private function _count_samples_in_transit()
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_sample_tracking')) return 0;
        return $this->db->where_in('sample_status', ['COLLECTED', 'RECEIVED_LAB', 'IN_PROCESS'])
            ->where('InActive', 0)->count_all_results('lab_sample_tracking');
    }

    private function _count_temperature_breaches()
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_sample_tracking')) return 0;
        return $this->db->where(['temperature_breach_flag' => 1, 'InActive' => 0])->count_all_results('lab_sample_tracking');
    }

    private function _get_recent_samples($limit = 20)
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_sample_tracking')) return [];
        return $this->db->select('s.*, p.firstname, p.lastname')
            ->from('lab_sample_tracking s')
            ->join('patient_personal_info p', 'p.patient_no = s.patient_no', 'left')
            ->where('s.InActive', 0)
            ->order_by('s.created_at', 'DESC')
            ->limit($limit)->get()->result();
    }

    private function _get_active_samples($limit = 50)
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_sample_tracking')) return [];
        return $this->db->select('s.*, p.firstname, p.lastname, l.location_name')
            ->from('lab_sample_tracking s')
            ->join('patient_personal_info p', 'p.patient_no = s.patient_no', 'left')
            ->join('lab_sample_locations l', 'l.location_code = s.current_location_code', 'left')
            ->where_not_in('s.sample_status', ['DISPOSED', 'REJECTED'])
            ->where('s.InActive', 0)
            ->order_by('s.created_at', 'DESC')
            ->limit($limit)->get()->result();
    }

    private function _get_sample_temperature_log($sample_id, $limit = 50)
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_sample_temperature_log')) return [];
        return $this->db->where(['sample_id' => (int)$sample_id, 'InActive' => 0])
            ->order_by('recorded_at', 'DESC')
            ->limit($limit)->get('lab_sample_temperature_log')->result();
    }

    private function _get_all_recollections($limit = 100)
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_recollection_requests')) return [];
        return $this->db->select('r.*, p.firstname, p.lastname, p.phone')
            ->from('lab_recollection_requests r')
            ->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left')
            ->where('r.InActive', 0)
            ->order_by('r.requested_at', 'DESC')
            ->limit($limit)->get()->result();
    }

    private function _get_recollections_by_status($status, $limit = 100)
    {
        if (!$this->diagnostic_safety_model->table_exists('lab_recollection_requests')) return [];
        return $this->db->select('r.*, p.firstname, p.lastname, p.phone')
            ->from('lab_recollection_requests r')
            ->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left')
            ->where(['r.status' => $status, 'r.InActive' => 0])
            ->order_by('r.priority', 'DESC')
            ->order_by('r.requested_at', 'ASC')
            ->limit($limit)->get()->result();
    }
}
