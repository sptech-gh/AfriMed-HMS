<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Diagnostic TAT Model
 * Phase 3 (Week 4) - TAT Monitoring & STAT Enforcement
 * 
 * Features:
 * - Department-specific TAT targets
 * - STAT test priority handling
 * - Real-time TAT tracking
 * - Breach detection and alerts
 * - Performance analytics
 */
class Diagnostic_tat_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  SCHEMA INSTALLATION                                               */
    /* ================================================================== */

    public function ensure_tat_schema()
    {
        $this->ensure_tat_targets_table();
        $this->ensure_tat_tracking_table();
        $this->ensure_stat_requests_table();
        $this->ensure_tat_breaches_table();
        $this->ensure_tat_performance_table();
        $this->seed_default_tat_targets();
        return true;
    }

    private function table_exists($table)
    {
        return $this->db->table_exists($table);
    }

    private function column_exists($table, $column)
    {
        return $this->db->field_exists($column, $table);
    }

    private function ensure_tat_targets_table()
    {
        if ($this->table_exists('diagnostic_tat_targets')) return;

        $this->db->query("
            CREATE TABLE diagnostic_tat_targets (
                target_id INT AUTO_INCREMENT PRIMARY KEY,
                department VARCHAR(50) NOT NULL,
                test_category VARCHAR(100),
                test_code VARCHAR(50),
                priority_level ENUM('ROUTINE','URGENT','STAT','CRITICAL') DEFAULT 'ROUTINE',
                target_minutes INT NOT NULL,
                warning_threshold_pct INT DEFAULT 80,
                critical_threshold_pct INT DEFAULT 100,
                escalation_enabled TINYINT(1) DEFAULT 1,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                InActive TINYINT(1) DEFAULT 0,
                UNIQUE KEY uk_dept_test_priority (department, test_category, test_code, priority_level),
                INDEX idx_department (department),
                INDEX idx_priority (priority_level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_tat_tracking_table()
    {
        if ($this->table_exists('diagnostic_tat_tracking')) return;

        $this->db->query("
            CREATE TABLE diagnostic_tat_tracking (
                tracking_id INT AUTO_INCREMENT PRIMARY KEY,
                io_lab_id INT NOT NULL,
                iop_id INT,
                patient_no VARCHAR(25),
                department VARCHAR(50) NOT NULL,
                test_name VARCHAR(255),
                test_code VARCHAR(50),
                priority_level ENUM('ROUTINE','URGENT','STAT','CRITICAL') DEFAULT 'ROUTINE',
                target_tat_minutes INT,
                ordered_at DATETIME NOT NULL,
                sample_collected_at DATETIME DEFAULT NULL,
                sample_received_at DATETIME DEFAULT NULL,
                processing_started_at DATETIME DEFAULT NULL,
                result_entered_at DATETIME DEFAULT NULL,
                result_verified_at DATETIME DEFAULT NULL,
                result_released_at DATETIME DEFAULT NULL,
                total_tat_minutes INT DEFAULT NULL,
                collection_tat_minutes INT DEFAULT NULL,
                transport_tat_minutes INT DEFAULT NULL,
                processing_tat_minutes INT DEFAULT NULL,
                verification_tat_minutes INT DEFAULT NULL,
                status ENUM('ORDERED','COLLECTING','IN_TRANSIT','RECEIVED','PROCESSING','RESULTED','VERIFIED','RELEASED','CANCELLED') DEFAULT 'ORDERED',
                is_breached TINYINT(1) DEFAULT 0,
                breach_minutes INT DEFAULT NULL,
                breach_notified TINYINT(1) DEFAULT 0,
                ordered_by INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                InActive TINYINT(1) DEFAULT 0,
                UNIQUE KEY uk_io_lab (io_lab_id),
                INDEX idx_patient (patient_no),
                INDEX idx_department (department),
                INDEX idx_priority (priority_level),
                INDEX idx_status (status),
                INDEX idx_breached (is_breached),
                INDEX idx_ordered (ordered_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_stat_requests_table()
    {
        if ($this->table_exists('diagnostic_stat_requests')) return;

        $this->db->query("
            CREATE TABLE diagnostic_stat_requests (
                stat_id INT AUTO_INCREMENT PRIMARY KEY,
                io_lab_id INT NOT NULL,
                iop_id INT,
                patient_no VARCHAR(25) NOT NULL,
                test_name VARCHAR(255) NOT NULL,
                clinical_indication TEXT,
                requested_by INT NOT NULL,
                requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                approved_by INT DEFAULT NULL,
                approved_at DATETIME DEFAULT NULL,
                approval_status ENUM('PENDING','APPROVED','REJECTED','AUTO_APPROVED') DEFAULT 'PENDING',
                rejection_reason TEXT,
                target_completion_at DATETIME,
                actual_completion_at DATETIME DEFAULT NULL,
                is_overdue TINYINT(1) DEFAULT 0,
                escalation_level INT DEFAULT 0,
                last_escalation_at DATETIME DEFAULT NULL,
                notes TEXT,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_io_lab (io_lab_id),
                INDEX idx_patient (patient_no),
                INDEX idx_status (approval_status),
                INDEX idx_overdue (is_overdue),
                INDEX idx_requested (requested_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_tat_breaches_table()
    {
        if ($this->table_exists('diagnostic_tat_breaches')) return;

        $this->db->query("
            CREATE TABLE diagnostic_tat_breaches (
                breach_id INT AUTO_INCREMENT PRIMARY KEY,
                tracking_id INT NOT NULL,
                io_lab_id INT NOT NULL,
                patient_no VARCHAR(25),
                department VARCHAR(50),
                test_name VARCHAR(255),
                priority_level VARCHAR(20),
                target_tat_minutes INT,
                actual_tat_minutes INT,
                breach_minutes INT,
                breach_phase ENUM('COLLECTION','TRANSPORT','PROCESSING','VERIFICATION','TOTAL') DEFAULT 'TOTAL',
                breach_severity ENUM('WARNING','CRITICAL','SEVERE') DEFAULT 'WARNING',
                root_cause VARCHAR(255) DEFAULT NULL,
                corrective_action TEXT DEFAULT NULL,
                acknowledged_by INT DEFAULT NULL,
                acknowledged_at DATETIME DEFAULT NULL,
                resolved_by INT DEFAULT NULL,
                resolved_at DATETIME DEFAULT NULL,
                resolution_notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_tracking (tracking_id),
                INDEX idx_department (department),
                INDEX idx_severity (breach_severity),
                INDEX idx_acknowledged (acknowledged_by),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_tat_performance_table()
    {
        if ($this->table_exists('diagnostic_tat_performance')) return;

        $this->db->query("
            CREATE TABLE diagnostic_tat_performance (
                perf_id INT AUTO_INCREMENT PRIMARY KEY,
                report_date DATE NOT NULL,
                department VARCHAR(50) NOT NULL,
                test_category VARCHAR(100),
                priority_level VARCHAR(20) DEFAULT 'ALL',
                total_tests INT DEFAULT 0,
                completed_tests INT DEFAULT 0,
                within_target INT DEFAULT 0,
                breached INT DEFAULT 0,
                avg_tat_minutes DECIMAL(10,2) DEFAULT NULL,
                median_tat_minutes DECIMAL(10,2) DEFAULT NULL,
                min_tat_minutes INT DEFAULT NULL,
                max_tat_minutes INT DEFAULT NULL,
                p90_tat_minutes INT DEFAULT NULL,
                compliance_rate DECIMAL(5,2) DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_date_dept_cat_pri (report_date, department, test_category, priority_level),
                INDEX idx_date (report_date),
                INDEX idx_department (department)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function seed_default_tat_targets()
    {
        if ($this->db->where('InActive', 0)->count_all_results('diagnostic_tat_targets') > 0) return;

        $targets = [
            // Haematology
            ['HAEMATOLOGY', 'Full Blood Count', null, 'ROUTINE', 120, 80, 100],
            ['HAEMATOLOGY', 'Full Blood Count', null, 'URGENT', 60, 80, 100],
            ['HAEMATOLOGY', 'Full Blood Count', null, 'STAT', 30, 80, 100],
            ['HAEMATOLOGY', 'Coagulation', null, 'ROUTINE', 180, 80, 100],
            ['HAEMATOLOGY', 'Coagulation', null, 'STAT', 45, 80, 100],
            
            // Biochemistry
            ['BIOCHEMISTRY', 'Basic Metabolic Panel', null, 'ROUTINE', 120, 80, 100],
            ['BIOCHEMISTRY', 'Basic Metabolic Panel', null, 'STAT', 30, 80, 100],
            ['BIOCHEMISTRY', 'Liver Function', null, 'ROUTINE', 180, 80, 100],
            ['BIOCHEMISTRY', 'Cardiac Markers', null, 'STAT', 30, 80, 100],
            ['BIOCHEMISTRY', 'Cardiac Markers', null, 'CRITICAL', 15, 80, 100],
            
            // Microbiology
            ['MICROBIOLOGY', 'Gram Stain', null, 'ROUTINE', 60, 80, 100],
            ['MICROBIOLOGY', 'Gram Stain', null, 'STAT', 30, 80, 100],
            ['MICROBIOLOGY', 'Culture', null, 'ROUTINE', 4320, 80, 100], // 72 hours
            ['MICROBIOLOGY', 'Rapid Antigen', null, 'STAT', 30, 80, 100],
            
            // Radiology
            ['RADIOLOGY', 'X-Ray', null, 'ROUTINE', 60, 80, 100],
            ['RADIOLOGY', 'X-Ray', null, 'STAT', 15, 80, 100],
            ['RADIOLOGY', 'CT Scan', null, 'ROUTINE', 120, 80, 100],
            ['RADIOLOGY', 'CT Scan', null, 'STAT', 30, 80, 100],
            ['RADIOLOGY', 'Ultrasound', null, 'ROUTINE', 90, 80, 100],
            
            // Default fallbacks
            ['DEFAULT', null, null, 'ROUTINE', 240, 80, 100],
            ['DEFAULT', null, null, 'URGENT', 120, 80, 100],
            ['DEFAULT', null, null, 'STAT', 60, 80, 100],
            ['DEFAULT', null, null, 'CRITICAL', 30, 80, 100]
        ];

        foreach ($targets as $t) {
            $this->db->insert('diagnostic_tat_targets', [
                'department' => $t[0],
                'test_category' => $t[1],
                'test_code' => $t[2],
                'priority_level' => $t[3],
                'target_minutes' => $t[4],
                'warning_threshold_pct' => $t[5],
                'critical_threshold_pct' => $t[6]
            ]);
        }
    }

    /* ================================================================== */
    /*  TAT TRACKING                                                      */
    /* ================================================================== */

    /**
     * Initialize TAT tracking for a new lab request
     */
    public function init_tat_tracking($io_lab_id, $data)
    {
        $target = $this->get_tat_target($data['department'] ?? 'DEFAULT', $data['test_category'] ?? null, $data['priority'] ?? 'ROUTINE');

        $insert = [
            'io_lab_id' => $io_lab_id,
            'iop_id' => $data['iop_id'] ?? null,
            'patient_no' => $data['patient_no'],
            'department' => $data['department'] ?? 'DEFAULT',
            'test_name' => $data['test_name'],
            'test_code' => $data['test_code'] ?? null,
            'priority_level' => $data['priority'] ?? 'ROUTINE',
            'target_tat_minutes' => $target ? $target->target_minutes : 240,
            'ordered_at' => $data['ordered_at'] ?? date('Y-m-d H:i:s'),
            'ordered_by' => $data['ordered_by'] ?? $this->session->userdata('user_id'),
            'status' => 'ORDERED'
        ];

        // Check if tracking already exists
        $existing = $this->db->where('io_lab_id', $io_lab_id)->get('diagnostic_tat_tracking')->row();
        if ($existing) {
            $this->db->where('io_lab_id', $io_lab_id)->update('diagnostic_tat_tracking', $insert);
            return $existing->tracking_id;
        }

        $this->db->insert('diagnostic_tat_tracking', $insert);
        return $this->db->insert_id();
    }

    /**
     * Update TAT tracking milestone
     */
    public function update_tat_milestone($io_lab_id, $milestone, $timestamp = null)
    {
        $timestamp = $timestamp ?: date('Y-m-d H:i:s');
        $tracking = $this->db->where('io_lab_id', $io_lab_id)->get('diagnostic_tat_tracking')->row();

        if (!$tracking) return false;

        $update = ['updated_at' => date('Y-m-d H:i:s')];

        switch ($milestone) {
            case 'COLLECTED':
                $update['sample_collected_at'] = $timestamp;
                $update['status'] = 'COLLECTING';
                if ($tracking->ordered_at) {
                    $update['collection_tat_minutes'] = $this->calculate_minutes($tracking->ordered_at, $timestamp);
                }
                break;

            case 'RECEIVED':
                $update['sample_received_at'] = $timestamp;
                $update['status'] = 'RECEIVED';
                if ($tracking->sample_collected_at) {
                    $update['transport_tat_minutes'] = $this->calculate_minutes($tracking->sample_collected_at, $timestamp);
                }
                break;

            case 'PROCESSING':
                $update['processing_started_at'] = $timestamp;
                $update['status'] = 'PROCESSING';
                break;

            case 'RESULTED':
                $update['result_entered_at'] = $timestamp;
                $update['status'] = 'RESULTED';
                if ($tracking->sample_received_at) {
                    $update['processing_tat_minutes'] = $this->calculate_minutes($tracking->sample_received_at, $timestamp);
                }
                break;

            case 'VERIFIED':
                $update['result_verified_at'] = $timestamp;
                $update['status'] = 'VERIFIED';
                if ($tracking->result_entered_at) {
                    $update['verification_tat_minutes'] = $this->calculate_minutes($tracking->result_entered_at, $timestamp);
                }
                break;

            case 'RELEASED':
                $update['result_released_at'] = $timestamp;
                $update['status'] = 'RELEASED';
                $update['total_tat_minutes'] = $this->calculate_minutes($tracking->ordered_at, $timestamp);

                // Check for breach
                if ($update['total_tat_minutes'] > $tracking->target_tat_minutes) {
                    $update['is_breached'] = 1;
                    $update['breach_minutes'] = $update['total_tat_minutes'] - $tracking->target_tat_minutes;
                    $this->record_breach($tracking, $update['total_tat_minutes']);
                }
                break;

            case 'CANCELLED':
                $update['status'] = 'CANCELLED';
                break;
        }

        $this->db->where('io_lab_id', $io_lab_id)->update('diagnostic_tat_tracking', $update);

        // Check for in-progress breach
        $this->check_in_progress_breach($io_lab_id);

        return true;
    }

    /**
     * Check if a test is approaching or has breached TAT while in progress
     */
    public function check_in_progress_breach($io_lab_id)
    {
        $tracking = $this->db->where('io_lab_id', $io_lab_id)->get('diagnostic_tat_tracking')->row();
        if (!$tracking || in_array($tracking->status, ['RELEASED', 'CANCELLED'])) return;

        $elapsed = $this->calculate_minutes($tracking->ordered_at, date('Y-m-d H:i:s'));
        $target = $tracking->target_tat_minutes;

        // Warning threshold (default 80%)
        $warning_threshold = $target * 0.8;

        if ($elapsed >= $target && !$tracking->is_breached) {
            // Breach occurred
            $this->db->where('io_lab_id', $io_lab_id)->update('diagnostic_tat_tracking', [
                'is_breached' => 1,
                'breach_minutes' => $elapsed - $target
            ]);

            $this->record_breach($tracking, $elapsed);

            // Trigger notification
            $this->trigger_breach_notification($tracking, $elapsed);

        } elseif ($elapsed >= $warning_threshold && !$tracking->breach_notified) {
            // Warning threshold reached
            $this->trigger_warning_notification($tracking, $elapsed);
            $this->db->where('io_lab_id', $io_lab_id)->update('diagnostic_tat_tracking', ['breach_notified' => 1]);
        }
    }

    private function record_breach($tracking, $actual_minutes)
    {
        $breach_minutes = $actual_minutes - $tracking->target_tat_minutes;
        $severity = 'WARNING';

        if ($breach_minutes > $tracking->target_tat_minutes * 0.5) {
            $severity = 'SEVERE';
        } elseif ($breach_minutes > $tracking->target_tat_minutes * 0.25) {
            $severity = 'CRITICAL';
        }

        $this->db->insert('diagnostic_tat_breaches', [
            'tracking_id' => $tracking->tracking_id,
            'io_lab_id' => $tracking->io_lab_id,
            'patient_no' => $tracking->patient_no,
            'department' => $tracking->department,
            'test_name' => $tracking->test_name,
            'priority_level' => $tracking->priority_level,
            'target_tat_minutes' => $tracking->target_tat_minutes,
            'actual_tat_minutes' => $actual_minutes,
            'breach_minutes' => $breach_minutes,
            'breach_severity' => $severity
        ]);
    }

    private function trigger_breach_notification($tracking, $elapsed)
    {
        // Load notifications model if available
        if (file_exists(APPPATH . 'models/app/diagnostic_notifications_model.php')) {
            $this->load->model('app/diagnostic_notifications_model', 'notifications');
            
            $patient = $this->db->where('patient_no', $tracking->patient_no)
                ->get('patient_personal_info')->row();

            $result = $this->notifications->send_tat_breach_notification([
                'patient_name' => $patient ? "{$patient->firstname} {$patient->lastname}" : $tracking->patient_no,
                'patient_no' => $tracking->patient_no,
                'test_name' => $tracking->test_name,
                'expected_tat' => $tracking->target_tat_minutes,
                'actual_time' => $elapsed,
                'io_lab_id' => $tracking->io_lab_id
            ]);
            if (empty($result)) {
                log_message('error', '[TAT_BREACH_NOTIFY_FAIL] io_lab_id=' . $tracking->io_lab_id
                    . ' patient=' . $tracking->patient_no . ' test=' . $tracking->test_name);
            }
        }
    }

    private function trigger_warning_notification($tracking, $elapsed)
    {
        // Similar to breach but with warning priority
        log_message('info', "TAT Warning: Test {$tracking->test_name} for patient {$tracking->patient_no} at {$elapsed} minutes (target: {$tracking->target_tat_minutes})");
    }

    /* ================================================================== */
    /*  STAT REQUEST MANAGEMENT                                           */
    /* ================================================================== */

    /**
     * Create a STAT request
     */
    public function create_stat_request($data)
    {
        $target = $this->get_tat_target($data['department'] ?? 'DEFAULT', $data['test_category'] ?? null, 'STAT');
        $target_minutes = $target ? $target->target_minutes : 60;

        $insert = [
            'io_lab_id' => $data['io_lab_id'],
            'iop_id' => $data['iop_id'] ?? null,
            'patient_no' => $data['patient_no'],
            'test_name' => $data['test_name'],
            'clinical_indication' => $data['clinical_indication'] ?? null,
            'requested_by' => $data['requested_by'] ?? $this->session->userdata('user_id'),
            'target_completion_at' => date('Y-m-d H:i:s', strtotime("+{$target_minutes} minutes")),
            'notes' => $data['notes'] ?? null
        ];

        // Auto-approve for certain conditions
        if ($this->should_auto_approve_stat($data)) {
            $insert['approval_status'] = 'AUTO_APPROVED';
            $insert['approved_at'] = date('Y-m-d H:i:s');
        }

        $this->db->insert('diagnostic_stat_requests', $insert);
        $stat_id = $this->db->insert_id();

        // Update TAT tracking priority
        $this->db->where('io_lab_id', $data['io_lab_id'])
            ->update('diagnostic_tat_tracking', [
                'priority_level' => 'STAT',
                'target_tat_minutes' => $target_minutes
            ]);

        return $stat_id;
    }

    private function should_auto_approve_stat($data)
    {
        // Auto-approve for emergency department
        if (isset($data['department']) && strtoupper($data['department']) === 'EMERGENCY') {
            return true;
        }

        // Auto-approve for critical clinical indications
        $critical_keywords = ['cardiac', 'stroke', 'sepsis', 'trauma', 'hemorrhage', 'emergency'];
        $indication = strtolower($data['clinical_indication'] ?? '');

        foreach ($critical_keywords as $keyword) {
            if (strpos($indication, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Approve a STAT request
     */
    public function approve_stat_request($stat_id, $user_id = null)
    {
        $user_id = $user_id ?: $this->session->userdata('user_id');

        $this->db->where('stat_id', $stat_id)->update('diagnostic_stat_requests', [
            'approval_status' => 'APPROVED',
            'approved_by' => $user_id,
            'approved_at' => date('Y-m-d H:i:s')
        ]);

        return true;
    }

    /**
     * Reject a STAT request
     */
    public function reject_stat_request($stat_id, $reason, $user_id = null)
    {
        $user_id = $user_id ?: $this->session->userdata('user_id');

        $stat = $this->db->where('stat_id', $stat_id)->get('diagnostic_stat_requests')->row();

        $this->db->where('stat_id', $stat_id)->update('diagnostic_stat_requests', [
            'approval_status' => 'REJECTED',
            'approved_by' => $user_id,
            'approved_at' => date('Y-m-d H:i:s'),
            'rejection_reason' => $reason
        ]);

        // Revert to URGENT priority
        if ($stat) {
            $this->db->where('io_lab_id', $stat->io_lab_id)
                ->update('diagnostic_tat_tracking', ['priority_level' => 'URGENT']);
        }

        return true;
    }

    /**
     * Check for overdue STAT requests
     */
    public function check_overdue_stat_requests()
    {
        $overdue = $this->db->query("
            SELECT s.*, t.status as tracking_status, p.firstname, p.lastname
            FROM diagnostic_stat_requests s
            LEFT JOIN diagnostic_tat_tracking t ON t.io_lab_id = s.io_lab_id
            LEFT JOIN patient_personal_info p ON p.patient_no = s.patient_no
            WHERE s.approval_status IN ('APPROVED', 'AUTO_APPROVED')
              AND s.actual_completion_at IS NULL
              AND s.target_completion_at < NOW()
              AND s.is_overdue = 0
              AND s.InActive = 0
              AND (t.status IS NULL OR t.status NOT IN ('RELEASED', 'CANCELLED'))
        ")->result();

        foreach ($overdue as $stat) {
            $this->db->where('stat_id', $stat->stat_id)
                ->update('diagnostic_stat_requests', ['is_overdue' => 1]);

            // Trigger notification
            if (file_exists(APPPATH . 'models/app/diagnostic_notifications_model.php')) {
                $this->load->model('app/diagnostic_notifications_model', 'notifications');
                $result = $this->notifications->send_stat_overdue_notification([
                    'patient_name' => "{$stat->firstname} {$stat->lastname}",
                    'patient_no' => $stat->patient_no,
                    'test_name' => $stat->test_name,
                    'ordered_at' => $stat->requested_at,
                    'tat_minutes' => $this->calculate_minutes($stat->requested_at, date('Y-m-d H:i:s')),
                    'io_lab_id' => $stat->io_lab_id
                ]);
                if (empty($result)) {
                    log_message('error', '[STAT_OVERDUE_NOTIFY_FAIL] io_lab_id=' . $stat->io_lab_id
                        . ' patient=' . $stat->patient_no . ' test=' . $stat->test_name);
                }
            }
        }

        return count($overdue);
    }

    /**
     * Escalate overdue STAT requests
     */
    public function escalate_stat_requests()
    {
        $escalation_intervals = [15, 30, 60]; // minutes

        $overdue = $this->db->where([
            'is_overdue' => 1,
            'actual_completion_at' => null,
            'InActive' => 0
        ])->where_in('approval_status', ['APPROVED', 'AUTO_APPROVED'])
            ->get('diagnostic_stat_requests')->result();

        $escalated = 0;

        foreach ($overdue as $stat) {
            $minutes_overdue = $this->calculate_minutes($stat->target_completion_at, date('Y-m-d H:i:s'));
            $current_level = $stat->escalation_level;

            // Determine if escalation is needed
            $new_level = $current_level;
            foreach ($escalation_intervals as $idx => $interval) {
                if ($minutes_overdue >= $interval && $current_level <= $idx) {
                    $new_level = $idx + 1;
                }
            }

            if ($new_level > $current_level) {
                $this->db->where('stat_id', $stat->stat_id)->update('diagnostic_stat_requests', [
                    'escalation_level' => $new_level,
                    'last_escalation_at' => date('Y-m-d H:i:s')
                ]);
                $escalated++;
            }
        }

        return $escalated;
    }

    /* ================================================================== */
    /*  TAT TARGET MANAGEMENT                                             */
    /* ================================================================== */

    public function get_tat_target($department, $test_category = null, $priority = 'ROUTINE')
    {
        // Try exact match first
        $target = $this->db->where([
            'department' => $department,
            'priority_level' => $priority,
            'is_active' => 1,
            'InActive' => 0
        ]);

        if ($test_category) {
            $target->where('test_category', $test_category);
        }

        $result = $target->get('diagnostic_tat_targets')->row();

        if ($result) return $result;

        // Try department-level default
        $result = $this->db->where([
            'department' => $department,
            'test_category' => null,
            'priority_level' => $priority,
            'is_active' => 1,
            'InActive' => 0
        ])->get('diagnostic_tat_targets')->row();

        if ($result) return $result;

        // Fall back to DEFAULT
        return $this->db->where([
            'department' => 'DEFAULT',
            'priority_level' => $priority,
            'is_active' => 1,
            'InActive' => 0
        ])->get('diagnostic_tat_targets')->row();
    }

    public function get_all_tat_targets($department = null)
    {
        $this->db->where(['is_active' => 1, 'InActive' => 0]);

        if ($department) {
            $this->db->where('department', $department);
        }

        return $this->db->order_by('department', 'ASC')
            ->order_by('test_category', 'ASC')
            ->order_by("FIELD(priority_level, 'ROUTINE', 'URGENT', 'STAT', 'CRITICAL')", '', false)
            ->get('diagnostic_tat_targets')->result();
    }

    public function save_tat_target($data)
    {
        $insert = [
            'department' => $data['department'],
            'test_category' => $data['test_category'] ?: null,
            'test_code' => $data['test_code'] ?: null,
            'priority_level' => $data['priority_level'],
            'target_minutes' => $data['target_minutes'],
            'warning_threshold_pct' => $data['warning_threshold_pct'] ?? 80,
            'critical_threshold_pct' => $data['critical_threshold_pct'] ?? 100,
            'escalation_enabled' => $data['escalation_enabled'] ?? 1
        ];

        if (!empty($data['target_id'])) {
            $this->db->where('target_id', $data['target_id'])->update('diagnostic_tat_targets', $insert);
            return $data['target_id'];
        }

        $this->db->insert('diagnostic_tat_targets', $insert);
        return $this->db->insert_id();
    }

    /* ================================================================== */
    /*  REPORTING & ANALYTICS                                             */
    /* ================================================================== */

    /**
     * Get real-time TAT dashboard data
     */
    public function get_tat_dashboard($department = null)
    {
        $where = "t.InActive = 0 AND DATE(t.ordered_at) = CURDATE()";
        if ($department) {
            $where .= " AND t.department = " . $this->db->escape($department);
        }

        return $this->db->query("
            SELECT 
                COUNT(*) as total_tests,
                SUM(t.status = 'RELEASED') as completed,
                SUM(t.status NOT IN ('RELEASED', 'CANCELLED')) as in_progress,
                SUM(t.is_breached = 1) as breached,
                SUM(t.priority_level = 'STAT') as stat_tests,
                SUM(t.priority_level = 'STAT' AND t.status NOT IN ('RELEASED', 'CANCELLED')) as stat_pending,
                AVG(CASE WHEN t.status = 'RELEASED' THEN t.total_tat_minutes END) as avg_tat,
                COUNT(CASE WHEN t.status = 'RELEASED' AND t.total_tat_minutes <= t.target_tat_minutes THEN 1 END) * 100.0 / 
                    NULLIF(SUM(t.status = 'RELEASED'), 0) as compliance_rate
            FROM diagnostic_tat_tracking t
            WHERE {$where}
        ")->row();
    }

    /**
     * Get tests currently at risk of breach
     */
    public function get_at_risk_tests($limit = 20)
    {
        return $this->db->query("
            SELECT t.*, p.firstname, p.lastname,
                   TIMESTAMPDIFF(MINUTE, t.ordered_at, NOW()) as elapsed_minutes,
                   (TIMESTAMPDIFF(MINUTE, t.ordered_at, NOW()) * 100.0 / t.target_tat_minutes) as pct_elapsed
            FROM diagnostic_tat_tracking t
            LEFT JOIN patient_personal_info p ON p.patient_no = t.patient_no
            WHERE t.status NOT IN ('RELEASED', 'CANCELLED')
              AND t.InActive = 0
              AND TIMESTAMPDIFF(MINUTE, t.ordered_at, NOW()) >= (t.target_tat_minutes * 0.7)
            ORDER BY pct_elapsed DESC
            LIMIT ?
        ", [$limit])->result();
    }

    /**
     * Get pending STAT requests
     */
    public function get_pending_stat_requests($limit = 50)
    {
        return $this->db->select('s.*, p.firstname, p.lastname, u.username as requested_by_name')
            ->from('diagnostic_stat_requests s')
            ->join('patient_personal_info p', 'p.patient_no = s.patient_no', 'left')
            ->join('user u', 'u.user_id = s.requested_by', 'left')
            ->where(['s.approval_status' => 'PENDING', 's.InActive' => 0])
            ->order_by('s.requested_at', 'ASC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Get active STAT tests
     */
    public function get_active_stat_tests($limit = 50)
    {
        return $this->db->query("
            SELECT s.*, t.status as tracking_status, t.total_tat_minutes,
                   p.firstname, p.lastname,
                   TIMESTAMPDIFF(MINUTE, s.requested_at, NOW()) as elapsed_minutes,
                   TIMESTAMPDIFF(MINUTE, NOW(), s.target_completion_at) as minutes_remaining
            FROM diagnostic_stat_requests s
            LEFT JOIN diagnostic_tat_tracking t ON t.io_lab_id = s.io_lab_id
            LEFT JOIN patient_personal_info p ON p.patient_no = s.patient_no
            WHERE s.approval_status IN ('APPROVED', 'AUTO_APPROVED')
              AND s.actual_completion_at IS NULL
              AND s.InActive = 0
              AND (t.status IS NULL OR t.status NOT IN ('RELEASED', 'CANCELLED'))
            ORDER BY s.target_completion_at ASC
            LIMIT ?
        ", [$limit])->result();
    }

    /**
     * Get TAT breaches for review
     */
    public function get_unacknowledged_breaches($limit = 50)
    {
        return $this->db->select('b.*, p.firstname, p.lastname')
            ->from('diagnostic_tat_breaches b')
            ->join('patient_personal_info p', 'p.patient_no = b.patient_no', 'left')
            ->where(['b.acknowledged_by' => null, 'b.InActive' => 0])
            ->order_by('b.breach_severity', 'DESC')
            ->order_by('b.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    /**
     * Acknowledge a TAT breach
     */
    public function acknowledge_breach($breach_id, $root_cause = null, $user_id = null)
    {
        $user_id = $user_id ?: $this->session->userdata('user_id');

        $this->db->where('breach_id', $breach_id)->update('diagnostic_tat_breaches', [
            'acknowledged_by' => $user_id,
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'root_cause' => $root_cause
        ]);

        return true;
    }

    /**
     * Resolve a TAT breach
     */
    public function resolve_breach($breach_id, $corrective_action, $notes = null, $user_id = null)
    {
        $user_id = $user_id ?: $this->session->userdata('user_id');

        $this->db->where('breach_id', $breach_id)->update('diagnostic_tat_breaches', [
            'resolved_by' => $user_id,
            'resolved_at' => date('Y-m-d H:i:s'),
            'corrective_action' => $corrective_action,
            'resolution_notes' => $notes
        ]);

        return true;
    }

    /**
     * Generate daily TAT performance summary
     */
    public function generate_daily_performance($date = null)
    {
        $date = $date ?: date('Y-m-d', strtotime('-1 day'));

        $departments = $this->db->distinct()->select('department')
            ->where(['InActive' => 0])
            ->where('DATE(ordered_at)', $date)
            ->get('diagnostic_tat_tracking')->result();

        foreach ($departments as $dept) {
            $this->calculate_department_performance($date, $dept->department);
        }

        return true;
    }

    private function calculate_department_performance($date, $department)
    {
        $stats = $this->db->query("
            SELECT 
                COUNT(*) as total_tests,
                SUM(status = 'RELEASED') as completed_tests,
                SUM(status = 'RELEASED' AND total_tat_minutes <= target_tat_minutes) as within_target,
                SUM(is_breached = 1) as breached,
                AVG(CASE WHEN status = 'RELEASED' THEN total_tat_minutes END) as avg_tat,
                MIN(CASE WHEN status = 'RELEASED' THEN total_tat_minutes END) as min_tat,
                MAX(CASE WHEN status = 'RELEASED' THEN total_tat_minutes END) as max_tat
            FROM diagnostic_tat_tracking
            WHERE DATE(ordered_at) = ?
              AND department = ?
              AND InActive = 0
        ", [$date, $department])->row();

        if (!$stats || $stats->total_tests == 0) return;

        $compliance = $stats->completed_tests > 0 
            ? ($stats->within_target / $stats->completed_tests) * 100 
            : null;

        // Upsert performance record
        $existing = $this->db->where([
            'report_date' => $date,
            'department' => $department,
            'test_category' => null,
            'priority_level' => 'ALL'
        ])->get('diagnostic_tat_performance')->row();

        $data = [
            'report_date' => $date,
            'department' => $department,
            'priority_level' => 'ALL',
            'total_tests' => $stats->total_tests,
            'completed_tests' => $stats->completed_tests,
            'within_target' => $stats->within_target,
            'breached' => $stats->breached,
            'avg_tat_minutes' => $stats->avg_tat,
            'min_tat_minutes' => $stats->min_tat,
            'max_tat_minutes' => $stats->max_tat,
            'compliance_rate' => $compliance
        ];

        if ($existing) {
            $this->db->where('perf_id', $existing->perf_id)->update('diagnostic_tat_performance', $data);
        } else {
            $this->db->insert('diagnostic_tat_performance', $data);
        }
    }

    /**
     * Get TAT performance trend
     */
    public function get_performance_trend($department = null, $days = 30)
    {
        $this->db->select('report_date, department, compliance_rate, avg_tat_minutes, total_tests, breached')
            ->from('diagnostic_tat_performance')
            ->where('report_date >=', date('Y-m-d', strtotime("-{$days} days")))
            ->order_by('report_date', 'ASC');

        if ($department) {
            $this->db->where('department', $department);
        }

        return $this->db->get()->result();
    }

    /* ================================================================== */
    /*  HELPER METHODS                                                    */
    /* ================================================================== */

    private function calculate_minutes($start, $end)
    {
        $start_time = strtotime($start);
        $end_time = strtotime($end);
        return round(($end_time - $start_time) / 60);
    }

    /**
     * Get count of tests at risk
     */
    public function count_at_risk_tests()
    {
        return $this->db->query("
            SELECT COUNT(*) as cnt
            FROM diagnostic_tat_tracking
            WHERE status NOT IN ('RELEASED', 'CANCELLED')
              AND InActive = 0
              AND TIMESTAMPDIFF(MINUTE, ordered_at, NOW()) >= (target_tat_minutes * 0.8)
        ")->row()->cnt ?? 0;
    }

    /**
     * Get count of active STAT tests
     */
    public function count_active_stat_tests()
    {
        return $this->db->where([
            'InActive' => 0
        ])->where_in('approval_status', ['APPROVED', 'AUTO_APPROVED'])
            ->where('actual_completion_at', null)
            ->count_all_results('diagnostic_stat_requests');
    }

    /**
     * Get count of pending STAT approvals
     */
    public function count_pending_stat_approvals()
    {
        return $this->db->where(['approval_status' => 'PENDING', 'InActive' => 0])
            ->count_all_results('diagnostic_stat_requests');
    }

    /**
     * Get count of unacknowledged breaches
     */
    public function count_unacknowledged_breaches()
    {
        return $this->db->where(['acknowledged_by' => null, 'InActive' => 0])
            ->count_all_results('diagnostic_tat_breaches');
    }
}
