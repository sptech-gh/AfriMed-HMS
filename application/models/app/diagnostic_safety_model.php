<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Diagnostic Safety Model
 * 
 * Enterprise-grade patient safety enhancements for Laboratory, Radiology, and Sonography modules.
 * Implements critical value alerts, dual verification, sample tracking, delta checks,
 * duplicate detection, unified diagnostics, audit trails, and notifications.
 * 
 * @author Senior Healthcare Systems Architect
 * @version 1.0.0
 */
class Diagnostic_safety_model extends CI_Model
{
    private static $schema_initialized = false;

    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  UTILITY METHODS                                                    */
    /* ================================================================== */

    public function table_exists($table_name)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table_name));
        return ($q && $q->num_rows() > 0);
    }

    public function column_exists($table_name, $column_name)
    {
        if (!$this->table_exists($table_name)) return false;
        $q = $this->db->query("SHOW COLUMNS FROM `{$table_name}` LIKE " . $this->db->escape($column_name));
        return ($q && $q->num_rows() > 0);
    }

    private function safe_alter($sql)
    {
        $old = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($old !== null) $this->db->db_debug = false;
        try { $this->db->query($sql); } catch (Exception $e) {}
        if ($old !== null) $this->db->db_debug = $old;
    }

    /* ================================================================== */
    /*  MASTER SCHEMA INITIALIZATION                                       */
    /* ================================================================== */

    public function ensure_all_safety_schemas()
    {
        if (self::$schema_initialized) return true;
        self::$schema_initialized = true;

        $this->ensure_critical_value_schema();
        $this->ensure_dual_verification_schema();
        $this->ensure_sample_tracking_schema();
        $this->ensure_delta_check_schema();
        $this->ensure_duplicate_detection_schema();
        $this->ensure_unified_diagnostic_schema();
        $this->ensure_diagnostic_audit_schema();
        $this->ensure_notification_schema();
        $this->ensure_tat_monitoring_schema();
        $this->ensure_diagnostic_billing_schema();

        return true;
    }

    /* ================================================================== */
    /*  PHASE A: CRITICAL VALUE ALERT SYSTEM                               */
    /* ================================================================== */

    public function ensure_critical_value_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_critical_ranges` (
            `range_id` int(11) NOT NULL AUTO_INCREMENT,
            `test_id` int(11) NOT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `test_code` varchar(50) DEFAULT NULL,
            `unit` varchar(50) DEFAULT NULL,
            `min_normal` decimal(18,4) DEFAULT NULL,
            `max_normal` decimal(18,4) DEFAULT NULL,
            `min_critical_low` decimal(18,4) DEFAULT NULL,
            `max_critical_high` decimal(18,4) DEFAULT NULL,
            `min_panic_low` decimal(18,4) DEFAULT NULL,
            `max_panic_high` decimal(18,4) DEFAULT NULL,
            `alert_severity` enum('LOW','MEDIUM','HIGH','CRITICAL','PANIC') NOT NULL DEFAULT 'HIGH',
            `escalation_minutes` int(11) NOT NULL DEFAULT 30,
            `requires_dual_verification` tinyint(1) NOT NULL DEFAULT 1,
            `diagnostic_type` enum('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL DEFAULT 'LAB',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`range_id`),
            KEY `idx_test` (`test_id`),
            KEY `idx_code` (`test_code`),
            KEY `idx_active` (`is_active`, `InActive`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_critical_alerts` (
            `alert_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `iop_id` varchar(25) DEFAULT NULL,
            `test_id` int(11) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `result_value` varchar(100) NOT NULL,
            `result_numeric` decimal(18,4) DEFAULT NULL,
            `unit` varchar(50) DEFAULT NULL,
            `reference_range` varchar(100) DEFAULT NULL,
            `alert_level` enum('ABNORMAL','CRITICAL_LOW','CRITICAL_HIGH','PANIC_LOW','PANIC_HIGH') NOT NULL,
            `alert_severity` enum('LOW','MEDIUM','HIGH','CRITICAL','PANIC') NOT NULL DEFAULT 'HIGH',
            `alert_message` text DEFAULT NULL,
            `ordering_doctor_id` varchar(25) DEFAULT NULL,
            `ordering_doctor_name` varchar(255) DEFAULT NULL,
            `notified_at` datetime DEFAULT NULL,
            `acknowledged_by` varchar(25) DEFAULT NULL,
            `acknowledged_at` datetime DEFAULT NULL,
            `acknowledgment_notes` text DEFAULT NULL,
            `escalated_flag` tinyint(1) NOT NULL DEFAULT 0,
            `escalated_at` datetime DEFAULT NULL,
            `escalation_level` int(11) NOT NULL DEFAULT 0,
            `resolved_flag` tinyint(1) NOT NULL DEFAULT 0,
            `resolved_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`alert_id`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_ack` (`acknowledged_at`),
            KEY `idx_escalated` (`escalated_flag`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->seed_critical_ranges();
        return true;
    }

    private function seed_critical_ranges()
    {
        if (!$this->table_exists('lab_critical_ranges')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('lab_critical_ranges');
        if ($count > 0) return;

        $now = date('Y-m-d H:i:s');
        $tests = [
            ['Hemoglobin', 'HGB', 'g/dL', 12.0, 17.5, 7.0, 20.0, 5.0, 22.0],
            ['White Blood Cell Count', 'WBC', 'x10^9/L', 4.5, 11.0, 2.0, 30.0, 1.0, 50.0],
            ['Platelet Count', 'PLT', 'x10^9/L', 150, 400, 50, 1000, 20, 1500],
            ['Glucose (Fasting)', 'GLU', 'mg/dL', 70, 100, 40, 400, 30, 500],
            ['Potassium', 'K', 'mEq/L', 3.5, 5.0, 2.5, 6.5, 2.0, 7.0],
            ['Sodium', 'NA', 'mEq/L', 136, 145, 120, 160, 110, 170],
            ['Creatinine', 'CREAT', 'mg/dL', 0.6, 1.2, null, 10.0, null, 15.0],
            ['Troponin I', 'TROP', 'ng/mL', 0, 0.04, null, 0.5, null, 2.0],
        ];

        foreach ($tests as $t) {
            $this->db->insert('lab_critical_ranges', [
                'test_id' => 0, 'test_name' => $t[0], 'test_code' => $t[1], 'unit' => $t[2],
                'min_normal' => $t[3], 'max_normal' => $t[4], 'min_critical_low' => $t[5],
                'max_critical_high' => $t[6], 'min_panic_low' => $t[7], 'max_panic_high' => $t[8],
                'alert_severity' => 'HIGH', 'escalation_minutes' => 30, 'requires_dual_verification' => 1,
                'diagnostic_type' => 'LAB', 'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function check_critical_value($test_id, $test_name, $result_value)
    {
        $this->ensure_critical_value_schema();
        $numeric = $this->extract_numeric($result_value);
        if ($numeric === null) return null;

        $range = null;
        if ($test_id > 0) {
            $range = $this->db->get_where('lab_critical_ranges', ['test_id' => (int)$test_id, 'is_active' => 1, 'InActive' => 0])->row();
        }
        if (!$range && $test_name) {
            $this->db->like('test_name', $test_name, 'both');
            $this->db->where(['is_active' => 1, 'InActive' => 0]);
            $range = $this->db->get('lab_critical_ranges')->row();
        }
        if (!$range) return null;

        $alert_level = null;
        $alert_severity = 'HIGH';

        if ($range->min_panic_low !== null && $numeric < (float)$range->min_panic_low) {
            $alert_level = 'PANIC_LOW'; $alert_severity = 'PANIC';
        } elseif ($range->max_panic_high !== null && $numeric > (float)$range->max_panic_high) {
            $alert_level = 'PANIC_HIGH'; $alert_severity = 'PANIC';
        } elseif ($range->min_critical_low !== null && $numeric < (float)$range->min_critical_low) {
            $alert_level = 'CRITICAL_LOW'; $alert_severity = 'CRITICAL';
        } elseif ($range->max_critical_high !== null && $numeric > (float)$range->max_critical_high) {
            $alert_level = 'CRITICAL_HIGH'; $alert_severity = 'CRITICAL';
        } elseif (($range->min_normal !== null && $numeric < (float)$range->min_normal) ||
                  ($range->max_normal !== null && $numeric > (float)$range->max_normal)) {
            $alert_level = 'ABNORMAL'; $alert_severity = 'MEDIUM';
        }

        if (!$alert_level) return null;

        return [
            'alert_level' => $alert_level,
            'alert_severity' => $alert_severity,
            'reference_range' => ($range->min_normal ?? '') . ' - ' . ($range->max_normal ?? ''),
            'unit' => $range->unit,
            'requires_dual_verification' => (bool)$range->requires_dual_verification,
            'escalation_minutes' => (int)$range->escalation_minutes,
            'result_numeric' => $numeric
        ];
    }

    private function extract_numeric($value)
    {
        if (is_numeric($value)) return (float)$value;
        if (is_string($value) && preg_match('/[\d.]+/', $value, $m)) return (float)$m[0];
        return null;
    }

    public function create_critical_alert($io_lab_id, $patient_no, $iop_id, $test_id, $test_name, $result_value, $alert_info, $doctor_id = null)
    {
        $this->ensure_critical_value_schema();
        $now = date('Y-m-d H:i:s');
        $user_id = $this->session->userdata('user_id');

        $msg = strtoupper($alert_info['alert_level']) . ": {$test_name} = {$result_value}";
        if ($alert_info['unit']) $msg .= " {$alert_info['unit']}";
        $msg .= " (Normal: {$alert_info['reference_range']}). IMMEDIATE ATTENTION REQUIRED.";

        $this->db->insert('lab_critical_alerts', [
            'io_lab_id' => (int)$io_lab_id, 'patient_no' => $patient_no, 'iop_id' => $iop_id,
            'test_id' => $test_id, 'test_name' => $test_name, 'result_value' => $result_value,
            'result_numeric' => $alert_info['result_numeric'], 'unit' => $alert_info['unit'],
            'reference_range' => $alert_info['reference_range'], 'alert_level' => $alert_info['alert_level'],
            'alert_severity' => $alert_info['alert_severity'], 'alert_message' => $msg,
            'ordering_doctor_id' => $doctor_id, 'notified_at' => $now,
            'created_at' => $now, 'InActive' => 0
        ]);

        $alert_id = $this->db->insert_id();
        $this->log_diagnostic_audit('CRITICAL_ALERT', 'lab_critical_alerts', $alert_id, $io_lab_id, $patient_no, null, $msg);
        return $alert_id;
    }

    public function acknowledge_critical_alert($alert_id, $notes = '')
    {
        if (!$this->table_exists('lab_critical_alerts')) return false;
        $alert = $this->db->get_where('lab_critical_alerts', ['alert_id' => (int)$alert_id, 'InActive' => 0])->row();
        if (!$alert) return false;

        $this->db->where('alert_id', (int)$alert_id)->update('lab_critical_alerts', [
            'acknowledged_by' => $this->session->userdata('user_id'),
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledgment_notes' => $notes
        ]);
        $this->log_diagnostic_audit('ALERT_ACKNOWLEDGED', 'lab_critical_alerts', $alert_id, $alert->io_lab_id, $alert->patient_no, null, $notes);
        return true;
    }

    public function get_pending_alerts($doctor_id = null, $limit = 50)
    {
        if (!$this->table_exists('lab_critical_alerts')) return [];
        $this->db->select('a.*, p.firstname, p.lastname');
        $this->db->from('lab_critical_alerts a');
        $this->db->join('patient_personal_info p', 'p.patient_no = a.patient_no', 'left');
        $this->db->where(['a.acknowledged_at' => null, 'a.resolved_flag' => 0, 'a.InActive' => 0]);
        if ($doctor_id) $this->db->where('a.ordering_doctor_id', $doctor_id);
        $this->db->order_by('a.alert_severity', 'DESC')->order_by('a.created_at', 'DESC')->limit($limit);
        return $this->db->get()->result();
    }

    public function count_pending_alerts($doctor_id = null)
    {
        if (!$this->table_exists('lab_critical_alerts')) return 0;
        $this->db->where(['acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0]);
        if ($doctor_id) $this->db->where('ordering_doctor_id', $doctor_id);
        return $this->db->count_all_results('lab_critical_alerts');
    }

    /* ================================================================== */
    /*  PHASE B: DUAL VERIFICATION                                         */
    /* ================================================================== */

    public function ensure_dual_verification_schema()
    {
        if ($this->table_exists('iop_laboratory_workflow')) {
            $cols = [
                'verified_level_1_by' => "ADD COLUMN `verified_level_1_by` varchar(25) DEFAULT NULL",
                'verified_level_1_at' => "ADD COLUMN `verified_level_1_at` datetime DEFAULT NULL",
                'verified_level_2_by' => "ADD COLUMN `verified_level_2_by` varchar(25) DEFAULT NULL",
                'verified_level_2_at' => "ADD COLUMN `verified_level_2_at` datetime DEFAULT NULL",
                'requires_dual_verification' => "ADD COLUMN `requires_dual_verification` tinyint(1) NOT NULL DEFAULT 0"
            ];
            foreach ($cols as $col => $sql) {
                if (!$this->column_exists('iop_laboratory_workflow', $col)) {
                    $this->safe_alter("ALTER TABLE `iop_laboratory_workflow` {$sql}");
                }
            }
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_verification_audit` (
            `audit_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `verification_level` int(11) NOT NULL,
            `verified_by` varchar(25) NOT NULL,
            `verified_at` datetime NOT NULL,
            `status` enum('APPROVED','REJECTED','AMENDED') NOT NULL,
            `original_result` text DEFAULT NULL,
            `amended_result` text DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`audit_id`),
            KEY `idx_lab` (`io_lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function verify_level_1($io_lab_id, $notes = '')
    {
        $this->ensure_dual_verification_schema();
        $wf = $this->db->get_where('iop_laboratory_workflow', ['io_lab_id' => (int)$io_lab_id, 'InActive' => 0])->row();
        if (!$wf) return ['ok' => false, 'error' => 'Workflow not found'];
        if (!empty($wf->verified_level_1_at)) return ['ok' => false, 'error' => 'Already verified L1'];

        $now = date('Y-m-d H:i:s');
        $user = $this->session->userdata('user_id');
        $this->db->where('io_lab_id', (int)$io_lab_id)->update('iop_laboratory_workflow', [
            'verified_level_1_by' => $user, 'verified_level_1_at' => $now,
            'status' => 'VERIFIED_LEVEL_1', 'updated_at' => $now, 'updated_by' => $user
        ]);
        $this->log_verification($io_lab_id, 1, 'APPROVED', $notes);

        if (!$wf->requires_dual_verification) {
            return $this->verify_level_2($io_lab_id, 'Auto-verified: dual not required');
        }
        return ['ok' => true, 'status' => 'VERIFIED_LEVEL_1'];
    }

    public function verify_level_2($io_lab_id, $notes = '')
    {
        $this->ensure_dual_verification_schema();
        $wf = $this->db->get_where('iop_laboratory_workflow', ['io_lab_id' => (int)$io_lab_id, 'InActive' => 0])->row();
        if (!$wf) return ['ok' => false, 'error' => 'Workflow not found'];

        $user = $this->session->userdata('user_id');
        if ($wf->requires_dual_verification) {
            if (empty($wf->verified_level_1_at)) return ['ok' => false, 'error' => 'L1 verification required first'];
            if ($wf->verified_level_1_by === $user) return ['ok' => false, 'error' => 'Same user cannot do both levels'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('io_lab_id', (int)$io_lab_id)->update('iop_laboratory_workflow', [
            'verified_level_2_by' => $user, 'verified_level_2_at' => $now, 'verified_at' => $now,
            'status' => 'VERIFIED', 'updated_at' => $now, 'updated_by' => $user
        ]);
        $this->log_verification($io_lab_id, 2, 'APPROVED', $notes);
        return ['ok' => true, 'status' => 'VERIFIED'];
    }

    private function log_verification($io_lab_id, $level, $status, $notes)
    {
        if (!$this->table_exists('lab_verification_audit')) return;
        $this->db->insert('lab_verification_audit', [
            'io_lab_id' => (int)$io_lab_id, 'verification_level' => $level,
            'verified_by' => $this->session->userdata('user_id'), 'verified_at' => date('Y-m-d H:i:s'),
            'status' => $status, 'notes' => $notes, 'ip_address' => $this->input->ip_address(),
            'created_at' => date('Y-m-d H:i:s'), 'InActive' => 0
        ]);
    }

    /* ================================================================== */
    /*  PHASE C: SAMPLE TRACKING                                           */
    /* ================================================================== */

    public function ensure_sample_tracking_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_sample_tracking` (
            `sample_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `sample_barcode` varchar(50) NOT NULL,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `iop_id` varchar(25) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `sample_type` varchar(50) DEFAULT NULL,
            `container_type` varchar(50) DEFAULT NULL,
            `sample_status` enum('REQUESTED','COLLECTED','RECEIVED_LAB','IN_PROCESS','RESULT_READY','VERIFIED','REJECTED','DISPOSED') NOT NULL DEFAULT 'REQUESTED',
            `collected_by` varchar(25) DEFAULT NULL,
            `collected_at` datetime DEFAULT NULL,
            `received_by` varchar(25) DEFAULT NULL,
            `received_at` datetime DEFAULT NULL,
            `sample_location` varchar(100) DEFAULT NULL,
            `rejected_by` varchar(25) DEFAULT NULL,
            `rejected_at` datetime DEFAULT NULL,
            `rejection_reason` text DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`sample_id`),
            UNIQUE KEY `uq_barcode` (`sample_barcode`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_status` (`sample_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function generate_barcode($io_lab_id)
    {
        return 'S' . date('ymd') . str_pad($io_lab_id, 6, '0', STR_PAD_LEFT) . strtoupper(substr(md5($io_lab_id), 0, 2));
    }

    public function create_sample($io_lab_id, $patient_no, $iop_id, $test_name, $sample_type = null)
    {
        $this->ensure_sample_tracking_schema();
        $existing = $this->db->get_where('lab_sample_tracking', ['io_lab_id' => (int)$io_lab_id, 'InActive' => 0])->row();
        if ($existing) return ['ok' => true, 'sample_id' => $existing->sample_id, 'barcode' => $existing->sample_barcode];

        $barcode = $this->generate_barcode($io_lab_id);
        $this->db->insert('lab_sample_tracking', [
            'sample_barcode' => $barcode, 'io_lab_id' => (int)$io_lab_id, 'patient_no' => $patient_no,
            'iop_id' => $iop_id, 'test_name' => $test_name, 'sample_type' => $sample_type,
            'sample_status' => 'REQUESTED', 'created_at' => date('Y-m-d H:i:s'), 'InActive' => 0
        ]);
        return ['ok' => true, 'sample_id' => $this->db->insert_id(), 'barcode' => $barcode];
    }

    public function update_sample_status($sample_id, $status, $location = null, $notes = '')
    {
        if (!$this->table_exists('lab_sample_tracking')) return false;
        $sample = $this->db->get_where('lab_sample_tracking', ['sample_id' => (int)$sample_id, 'InActive' => 0])->row();
        if (!$sample) return false;

        $now = date('Y-m-d H:i:s');
        $user = $this->session->userdata('user_id');
        $upd = ['sample_status' => $status];
        if ($location) $upd['sample_location'] = $location;

        if ($status === 'COLLECTED') { $upd['collected_by'] = $user; $upd['collected_at'] = $now; }
        if ($status === 'RECEIVED_LAB') { $upd['received_by'] = $user; $upd['received_at'] = $now; }
        if ($status === 'REJECTED') { $upd['rejected_by'] = $user; $upd['rejected_at'] = $now; $upd['rejection_reason'] = $notes; }

        $this->db->where('sample_id', (int)$sample_id)->update('lab_sample_tracking', $upd);
        $this->log_diagnostic_audit('SAMPLE_STATUS', 'lab_sample_tracking', $sample_id, $sample->io_lab_id, $sample->patient_no, $sample->sample_status, $status);
        return true;
    }

    public function get_sample_by_barcode($barcode)
    {
        if (!$this->table_exists('lab_sample_tracking')) return null;
        return $this->db->get_where('lab_sample_tracking', ['sample_barcode' => $barcode, 'InActive' => 0])->row();
    }

    /* ================================================================== */
    /*  PHASE D: DELTA CHECK                                               */
    /* ================================================================== */

    public function ensure_delta_check_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_delta_checks` (
            `delta_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `test_id` int(11) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `previous_value` varchar(100) DEFAULT NULL,
            `previous_numeric` decimal(18,4) DEFAULT NULL,
            `previous_date` datetime DEFAULT NULL,
            `current_value` varchar(100) NOT NULL,
            `current_numeric` decimal(18,4) DEFAULT NULL,
            `delta_percent` decimal(10,2) DEFAULT NULL,
            `flagged` tinyint(1) NOT NULL DEFAULT 0,
            `flag_reason` varchar(255) DEFAULT NULL,
            `reviewed_by` varchar(25) DEFAULT NULL,
            `reviewed_at` datetime DEFAULT NULL,
            `review_action` enum('ACCEPTED','REJECTED','REPEAT') DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`delta_id`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_flagged` (`flagged`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function perform_delta_check($io_lab_id, $patient_no, $test_id, $test_name, $current_value)
    {
        $this->ensure_delta_check_schema();
        $current_num = $this->extract_numeric($current_value);
        if ($current_num === null) return ['flagged' => false];

        // Find previous result
        $this->db->select('io_lab_id, result, dDate')->from('iop_laboratory');
        $this->db->join('patient_details_iop pd', 'pd.IO_ID = iop_laboratory.iop_id', 'left');
        $this->db->where(['pd.patient_no' => $patient_no, 'laboratory_id' => $test_id, 'iop_laboratory.InActive' => 0]);
        $this->db->where('io_lab_id !=', $io_lab_id)->where("result IS NOT NULL")->where("result != ''");
        $this->db->where("dDate >= DATE_SUB(NOW(), INTERVAL 72 HOUR)")->order_by('dDate', 'DESC')->limit(1);
        $prev = $this->db->get()->row();

        if (!$prev) return ['flagged' => false];
        $prev_num = $this->extract_numeric($prev->result);
        if (!$prev_num) return ['flagged' => false];

        $delta_pct = abs(($current_num - $prev_num) / $prev_num * 100);
        $flagged = ($delta_pct >= 50);

        $this->db->insert('lab_delta_checks', [
            'io_lab_id' => (int)$io_lab_id, 'patient_no' => $patient_no, 'test_id' => $test_id,
            'test_name' => $test_name, 'previous_value' => $prev->result, 'previous_numeric' => $prev_num,
            'previous_date' => $prev->dDate, 'current_value' => $current_value, 'current_numeric' => $current_num,
            'delta_percent' => $delta_pct, 'flagged' => $flagged ? 1 : 0,
            'flag_reason' => $flagged ? "Delta {$delta_pct}% exceeds 50%" : null,
            'created_at' => date('Y-m-d H:i:s'), 'InActive' => 0
        ]);

        return ['flagged' => $flagged, 'delta_percent' => round($delta_pct, 2), 'previous' => $prev->result];
    }

    /* ================================================================== */
    /*  PHASE E: DUPLICATE DETECTION                                       */
    /* ================================================================== */

    public function ensure_duplicate_detection_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_duplicate_override` (
            `override_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `duplicate_of` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `test_id` int(11) DEFAULT NULL,
            `hours_since` decimal(10,2) DEFAULT NULL,
            `override_reason` text NOT NULL,
            `override_by` varchar(25) NOT NULL,
            `override_at` datetime NOT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`override_id`),
            KEY `idx_lab` (`io_lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function check_duplicate($patient_no, $test_id, $window_hours = 24)
    {
        $this->db->select('l.io_lab_id, l.dDate, l.laboratory_text');
        $this->db->from('iop_laboratory l');
        $this->db->join('patient_details_iop pd', 'pd.IO_ID = l.iop_id', 'left');
        $this->db->where(['pd.patient_no' => $patient_no, 'l.laboratory_id' => $test_id, 'l.InActive' => 0]);
        $this->db->where("l.dDate >= DATE_SUB(NOW(), INTERVAL {$window_hours} HOUR)");
        $this->db->order_by('l.dDate', 'DESC')->limit(1);
        $dup = $this->db->get()->row();

        if ($dup) {
            $hours = round((time() - strtotime($dup->dDate)) / 3600, 1);
            return ['is_duplicate' => true, 'existing_id' => $dup->io_lab_id, 'hours_ago' => $hours, 'test_name' => $dup->laboratory_text];
        }
        return ['is_duplicate' => false];
    }

    public function record_duplicate_override($io_lab_id, $duplicate_of, $patient_no, $test_id, $hours, $reason)
    {
        $this->ensure_duplicate_detection_schema();
        $this->db->insert('lab_duplicate_override', [
            'io_lab_id' => (int)$io_lab_id, 'duplicate_of' => (int)$duplicate_of, 'patient_no' => $patient_no,
            'test_id' => $test_id, 'hours_since' => $hours, 'override_reason' => $reason,
            'override_by' => $this->session->userdata('user_id'), 'override_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'), 'InActive' => 0
        ]);
        return $this->db->insert_id();
    }

    /* ================================================================== */
    /*  PHASE F: UNIFIED DIAGNOSTIC                                        */
    /* ================================================================== */

    public function ensure_unified_diagnostic_schema()
    {
        if ($this->table_exists('iop_laboratory') && !$this->column_exists('iop_laboratory', 'diagnostic_type')) {
            $this->safe_alter("ALTER TABLE `iop_laboratory` ADD COLUMN `diagnostic_type` enum('LAB','RADIOLOGY','SONOGRAPHY') DEFAULT 'LAB'");
        }
        if ($this->table_exists('iop_laboratory') && !$this->column_exists('iop_laboratory', 'priority')) {
            $this->safe_alter("ALTER TABLE `iop_laboratory` ADD COLUMN `priority` enum('ROUTINE','URGENT','STAT') DEFAULT 'ROUTINE'");
        }
        return true;
    }

    /* ================================================================== */
    /*  PHASE J: AUDIT TRAIL                                               */
    /* ================================================================== */

    public function ensure_diagnostic_audit_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_audit_log` (
            `audit_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `action_type` varchar(50) NOT NULL,
            `table_name` varchar(100) DEFAULT NULL,
            `record_id` bigint(11) DEFAULT NULL,
            `io_lab_id` int(11) DEFAULT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `old_value` text DEFAULT NULL,
            `new_value` text DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `performed_by` varchar(25) NOT NULL,
            `performed_at` datetime NOT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `user_agent` varchar(255) DEFAULT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`audit_id`),
            KEY `idx_action` (`action_type`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_date` (`performed_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function log_diagnostic_audit($action, $table, $record_id, $io_lab_id, $patient_no, $old_val = null, $new_val = null)
    {
        $this->ensure_diagnostic_audit_schema();
        $this->db->insert('diagnostic_audit_log', [
            'action_type' => $action, 'table_name' => $table, 'record_id' => $record_id,
            'io_lab_id' => $io_lab_id, 'patient_no' => $patient_no,
            'old_value' => is_array($old_val) ? json_encode($old_val) : $old_val,
            'new_value' => is_array($new_val) ? json_encode($new_val) : $new_val,
            'performed_by' => $this->session->userdata('user_id'),
            'performed_at' => date('Y-m-d H:i:s'),
            'ip_address' => $this->input->ip_address(),
            'user_agent' => substr($this->input->user_agent(), 0, 255),
            'InActive' => 0
        ]);
    }

    /* ================================================================== */
    /*  PHASE K: NOTIFICATIONS                                             */
    /* ================================================================== */

    public function ensure_notification_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_notifications` (
            `notification_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `notification_type` enum('CRITICAL_RESULT','RESULT_READY','TAT_BREACH','PAYMENT_REQUIRED','ESCALATION') NOT NULL,
            `io_lab_id` int(11) DEFAULT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `recipient_user_id` varchar(25) DEFAULT NULL,
            `recipient_role` varchar(50) DEFAULT NULL,
            `title` varchar(255) NOT NULL,
            `message` text NOT NULL,
            `priority` enum('LOW','MEDIUM','HIGH','URGENT') NOT NULL DEFAULT 'MEDIUM',
            `read_flag` tinyint(1) NOT NULL DEFAULT 0,
            `read_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`notification_id`),
            KEY `idx_recipient` (`recipient_user_id`),
            KEY `idx_type` (`notification_type`),
            KEY `idx_read` (`read_flag`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function create_notification($type, $io_lab_id, $patient_no, $recipient_id, $title, $message, $priority = 'MEDIUM')
    {
        $this->ensure_notification_schema();
        $this->db->insert('diagnostic_notifications', [
            'notification_type' => $type, 'io_lab_id' => $io_lab_id, 'patient_no' => $patient_no,
            'recipient_user_id' => $recipient_id, 'title' => $title, 'message' => $message,
            'priority' => $priority, 'created_at' => date('Y-m-d H:i:s'), 'InActive' => 0
        ]);
        return $this->db->insert_id();
    }

    public function get_user_notifications($user_id, $unread_only = true, $limit = 20)
    {
        if (!$this->table_exists('diagnostic_notifications')) return [];
        $this->db->where(['recipient_user_id' => $user_id, 'InActive' => 0]);
        if ($unread_only) $this->db->where('read_flag', 0);
        $this->db->order_by('created_at', 'DESC')->limit($limit);
        return $this->db->get('diagnostic_notifications')->result();
    }

    public function mark_notification_read($notification_id)
    {
        if (!$this->table_exists('diagnostic_notifications')) return false;
        $this->db->where('notification_id', (int)$notification_id)->update('diagnostic_notifications', [
            'read_flag' => 1, 'read_at' => date('Y-m-d H:i:s')
        ]);
        return true;
    }

    /* ================================================================== */
    /*  PHASE L: TAT MONITORING                                            */
    /* ================================================================== */

    public function ensure_tat_monitoring_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_tat_config` (
            `config_id` int(11) NOT NULL AUTO_INCREMENT,
            `test_id` int(11) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `diagnostic_type` enum('LAB','RADIOLOGY','SONOGRAPHY') DEFAULT 'LAB',
            `target_tat_minutes` int(11) NOT NULL DEFAULT 120,
            `warning_tat_minutes` int(11) NOT NULL DEFAULT 90,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`config_id`),
            KEY `idx_test` (`test_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_tat_breaches` (
            `breach_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `target_tat` int(11) DEFAULT NULL,
            `actual_tat` int(11) DEFAULT NULL,
            `breach_minutes` int(11) DEFAULT NULL,
            `notified_flag` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`breach_id`),
            KEY `idx_lab` (`io_lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    /* ================================================================== */
    /*  PHASE H: DIAGNOSTIC BILLING                                        */
    /* ================================================================== */

    public function ensure_diagnostic_billing_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_billing_queue` (
            `queue_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `iop_id` varchar(25) NOT NULL,
            `patient_no` varchar(25) DEFAULT NULL,
            `diagnostic_type` enum('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL DEFAULT 'LAB',
            `test_id` int(11) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `rate_amount` decimal(18,2) NOT NULL DEFAULT 0,
            `quantity` int(11) NOT NULL DEFAULT 1,
            `billing_status` enum('PENDING','BILLED','PAID','WAIVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
            `invoice_no` varchar(50) DEFAULT NULL,
            `nhis_covered` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` datetime NOT NULL,
            `created_by` varchar(25) DEFAULT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`queue_id`),
            UNIQUE KEY `uq_lab` (`io_lab_id`),
            KEY `idx_iop` (`iop_id`),
            KEY `idx_status` (`billing_status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        return true;
    }

    public function auto_create_billing($io_lab_id, $iop_id, $patient_no, $diagnostic_type, $test_id, $test_name, $rate)
    {
        $this->ensure_diagnostic_billing_schema();
        $existing = $this->db->get_where('diagnostic_billing_queue', ['io_lab_id' => (int)$io_lab_id, 'InActive' => 0])->row();
        if ($existing) return ['ok' => true, 'queue_id' => $existing->queue_id, 'existing' => true];

        $this->db->insert('diagnostic_billing_queue', [
            'io_lab_id' => (int)$io_lab_id, 'iop_id' => $iop_id, 'patient_no' => $patient_no,
            'diagnostic_type' => $diagnostic_type, 'test_id' => $test_id, 'test_name' => $test_name,
            'rate_amount' => $rate, 'quantity' => 1, 'billing_status' => 'PENDING',
            'created_at' => date('Y-m-d H:i:s'), 'created_by' => $this->session->userdata('user_id'), 'InActive' => 0
        ]);
        return ['ok' => true, 'queue_id' => $this->db->insert_id(), 'existing' => false];
    }

    /* ================================================================== */
    /*  PHASE 4.5A: RADIOLOGY CRITICAL FINDINGS DETECTION                  */
    /* ================================================================== */

    public function ensure_radiology_critical_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `radiology_critical_findings` (
            `finding_id` int(11) NOT NULL AUTO_INCREMENT,
            `finding_code` varchar(50) NOT NULL,
            `finding_name` varchar(255) NOT NULL,
            `keywords` text NOT NULL,
            `severity` enum('LIFE_THREATENING','CRITICAL','URGENT') NOT NULL DEFAULT 'CRITICAL',
            `category` enum('CHEST','NEURO','VASCULAR','ABDOMINAL','TRAUMA','CARDIAC') NOT NULL,
            `requires_immediate_notification` tinyint(1) NOT NULL DEFAULT 1,
            `escalation_minutes` int(11) NOT NULL DEFAULT 15,
            `notification_template` text DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`finding_id`),
            UNIQUE KEY `uq_code` (`finding_code`),
            KEY `idx_severity` (`severity`),
            KEY `idx_category` (`category`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `radiology_critical_alerts` (
            `alert_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `order_id` int(11) DEFAULT NULL,
            `io_lab_id` int(11) DEFAULT NULL,
            `patient_no` varchar(25) NOT NULL,
            `iop_id` varchar(25) DEFAULT NULL,
            `finding_id` int(11) DEFAULT NULL,
            `finding_code` varchar(50) DEFAULT NULL,
            `finding_name` varchar(255) DEFAULT NULL,
            `detected_text` text DEFAULT NULL,
            `severity` enum('LIFE_THREATENING','CRITICAL','URGENT') NOT NULL,
            `alert_message` text DEFAULT NULL,
            `ordering_doctor_id` varchar(25) DEFAULT NULL,
            `ordering_doctor_name` varchar(255) DEFAULT NULL,
            `radiologist_id` varchar(25) DEFAULT NULL,
            `notified_at` datetime DEFAULT NULL,
            `acknowledged_by` varchar(25) DEFAULT NULL,
            `acknowledged_at` datetime DEFAULT NULL,
            `acknowledgment_notes` text DEFAULT NULL,
            `escalated_flag` tinyint(1) NOT NULL DEFAULT 0,
            `escalation_level` int(11) NOT NULL DEFAULT 0,
            `escalated_at` datetime DEFAULT NULL,
            `resolved_flag` tinyint(1) NOT NULL DEFAULT 0,
            `resolved_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`alert_id`),
            KEY `idx_order` (`order_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_severity` (`severity`),
            KEY `idx_ack` (`acknowledged_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->seed_radiology_critical_findings();
        return true;
    }

    private function seed_radiology_critical_findings()
    {
        if (!$this->table_exists('radiology_critical_findings')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('radiology_critical_findings');
        if ($count > 0) return;

        $now = date('Y-m-d H:i:s');
        $findings = [
            ['PNEUMOTHORAX', 'Pneumothorax', 'pneumothorax,collapsed lung,tension pneumo', 'LIFE_THREATENING', 'CHEST'],
            ['PULMONARY_EMBOLISM', 'Pulmonary Embolism', 'pulmonary embolism,PE,filling defect,saddle embolus', 'LIFE_THREATENING', 'CHEST'],
            ['AORTIC_DISSECTION', 'Aortic Dissection', 'aortic dissection,dissecting aneurysm,intimal flap', 'LIFE_THREATENING', 'VASCULAR'],
            ['STROKE_HEMORRHAGIC', 'Hemorrhagic Stroke', 'intracerebral hemorrhage,ICH,brain bleed,hemorrhagic stroke', 'LIFE_THREATENING', 'NEURO'],
            ['STROKE_ISCHEMIC', 'Ischemic Stroke', 'acute infarct,ischemic stroke,MCA infarct,cerebral infarction', 'CRITICAL', 'NEURO'],
            ['SUBDURAL_HEMATOMA', 'Subdural Hematoma', 'subdural hematoma,SDH,subdural collection', 'CRITICAL', 'NEURO'],
            ['EPIDURAL_HEMATOMA', 'Epidural Hematoma', 'epidural hematoma,EDH,extradural', 'LIFE_THREATENING', 'NEURO'],
            ['BOWEL_OBSTRUCTION', 'Bowel Obstruction', 'bowel obstruction,SBO,ileus,dilated loops', 'URGENT', 'ABDOMINAL'],
            ['BOWEL_PERFORATION', 'Bowel Perforation', 'free air,pneumoperitoneum,perforation', 'LIFE_THREATENING', 'ABDOMINAL'],
            ['APPENDICITIS', 'Acute Appendicitis', 'appendicitis,inflamed appendix,periappendiceal', 'URGENT', 'ABDOMINAL'],
            ['FRACTURE_SPINE', 'Spinal Fracture', 'spinal fracture,vertebral fracture,compression fracture', 'CRITICAL', 'TRAUMA'],
            ['FRACTURE_SKULL', 'Skull Fracture', 'skull fracture,calvarial fracture,depressed fracture', 'CRITICAL', 'TRAUMA'],
            ['MASS_SUSPICIOUS', 'Suspicious Mass', 'suspicious mass,malignant,tumor,neoplasm,carcinoma', 'URGENT', 'ABDOMINAL'],
            ['CARDIAC_TAMPONADE', 'Cardiac Tamponade', 'pericardial effusion,tamponade,cardiac compression', 'LIFE_THREATENING', 'CARDIAC'],
            ['AAA_RUPTURE', 'AAA Rupture', 'ruptured aneurysm,AAA rupture,retroperitoneal hematoma', 'LIFE_THREATENING', 'VASCULAR']
        ];

        foreach ($findings as $f) {
            $this->db->insert('radiology_critical_findings', [
                'finding_code' => $f[0], 'finding_name' => $f[1], 'keywords' => $f[2],
                'severity' => $f[3], 'category' => $f[4], 'requires_immediate_notification' => 1,
                'escalation_minutes' => ($f[3] === 'LIFE_THREATENING' ? 10 : ($f[3] === 'CRITICAL' ? 15 : 30)),
                'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function detect_radiology_critical_findings($findings_text, $impression_text = '')
    {
        $this->ensure_radiology_critical_schema();
        $combined = strtolower($findings_text . ' ' . $impression_text);
        $detected = [];

        $critical_findings = $this->db->get_where('radiology_critical_findings', ['is_active' => 1, 'InActive' => 0])->result();
        foreach ($critical_findings as $cf) {
            $keywords = array_map('trim', explode(',', strtolower($cf->keywords)));
            foreach ($keywords as $kw) {
                if ($kw && strpos($combined, $kw) !== false) {
                    $detected[] = [
                        'finding_id' => $cf->finding_id,
                        'finding_code' => $cf->finding_code,
                        'finding_name' => $cf->finding_name,
                        'severity' => $cf->severity,
                        'category' => $cf->category,
                        'matched_keyword' => $kw,
                        'escalation_minutes' => $cf->escalation_minutes
                    ];
                    break;
                }
            }
        }

        usort($detected, function($a, $b) {
            $order = ['LIFE_THREATENING' => 0, 'CRITICAL' => 1, 'URGENT' => 2];
            return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
        });

        return $detected;
    }

    public function create_radiology_critical_alert($order_id, $io_lab_id, $patient_no, $iop_id, $finding, $doctor_id = null, $radiologist_id = null)
    {
        $this->ensure_radiology_critical_schema();
        $now = date('Y-m-d H:i:s');

        $msg = strtoupper($finding['severity']) . " FINDING: {$finding['finding_name']}. IMMEDIATE ATTENTION REQUIRED.";

        $this->db->insert('radiology_critical_alerts', [
            'order_id' => $order_id, 'io_lab_id' => $io_lab_id, 'patient_no' => $patient_no,
            'iop_id' => $iop_id, 'finding_id' => $finding['finding_id'], 'finding_code' => $finding['finding_code'],
            'finding_name' => $finding['finding_name'], 'severity' => $finding['severity'],
            'alert_message' => $msg, 'ordering_doctor_id' => $doctor_id, 'radiologist_id' => $radiologist_id,
            'notified_at' => $now, 'created_at' => $now, 'InActive' => 0
        ]);

        $alert_id = $this->db->insert_id();
        $this->log_diagnostic_audit('RADIOLOGY_CRITICAL_ALERT', 'radiology_critical_alerts', $alert_id, $io_lab_id, $patient_no, null, $msg);

        if ($doctor_id) {
            $this->create_notification('CRITICAL_RESULT', $io_lab_id, $patient_no, $doctor_id,
                "CRITICAL: {$finding['finding_name']}", $msg, 'URGENT');
        }

        return $alert_id;
    }

    public function get_pending_radiology_alerts($doctor_id = null, $limit = 50)
    {
        if (!$this->table_exists('radiology_critical_alerts')) return [];
        $this->db->select('a.*, p.firstname, p.lastname');
        $this->db->from('radiology_critical_alerts a');
        $this->db->join('patient_personal_info p', 'p.patient_no = a.patient_no', 'left');
        $this->db->where(['a.acknowledged_at' => null, 'a.resolved_flag' => 0, 'a.InActive' => 0]);
        if ($doctor_id) $this->db->where('a.ordering_doctor_id', $doctor_id);
        $this->db->order_by('FIELD(a.severity, "LIFE_THREATENING", "CRITICAL", "URGENT")', '', false);
        $this->db->order_by('a.created_at', 'DESC')->limit($limit);
        return $this->db->get()->result();
    }

    public function acknowledge_radiology_alert($alert_id, $notes = '')
    {
        if (!$this->table_exists('radiology_critical_alerts')) return false;
        $alert = $this->db->get_where('radiology_critical_alerts', ['alert_id' => (int)$alert_id, 'InActive' => 0])->row();
        if (!$alert) return false;

        $this->db->where('alert_id', (int)$alert_id)->update('radiology_critical_alerts', [
            'acknowledged_by' => $this->session->userdata('user_id'),
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledgment_notes' => $notes
        ]);
        $this->log_diagnostic_audit('RADIOLOGY_ALERT_ACK', 'radiology_critical_alerts', $alert_id, $alert->io_lab_id, $alert->patient_no, null, $notes);
        return true;
    }

    /* ================================================================== */
    /*  PHASE 4.5B: SONOGRAPHY CRITICAL ALERTS                             */
    /* ================================================================== */

    public function ensure_sonography_critical_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `sonography_critical_definitions` (
            `def_id` int(11) NOT NULL AUTO_INCREMENT,
            `alert_code` varchar(50) NOT NULL,
            `alert_name` varchar(255) NOT NULL,
            `keywords` text NOT NULL,
            `category` enum('OBSTETRIC','ABDOMINAL','CARDIAC','VASCULAR','GYNECOLOGIC') NOT NULL,
            `severity` enum('LIFE_THREATENING','CRITICAL','URGENT') NOT NULL DEFAULT 'CRITICAL',
            `requires_immediate_action` tinyint(1) NOT NULL DEFAULT 1,
            `escalation_minutes` int(11) NOT NULL DEFAULT 10,
            `action_protocol` text DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`def_id`),
            UNIQUE KEY `uq_code` (`alert_code`),
            KEY `idx_category` (`category`),
            KEY `idx_severity` (`severity`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `sonography_critical_alerts` (
            `alert_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `iop_id` varchar(25) DEFAULT NULL,
            `def_id` int(11) DEFAULT NULL,
            `alert_code` varchar(50) DEFAULT NULL,
            `alert_name` varchar(255) DEFAULT NULL,
            `category` varchar(50) DEFAULT NULL,
            `detected_text` text DEFAULT NULL,
            `severity` enum('LIFE_THREATENING','CRITICAL','URGENT') NOT NULL,
            `alert_message` text DEFAULT NULL,
            `ordering_doctor_id` varchar(25) DEFAULT NULL,
            `sonographer_id` varchar(25) DEFAULT NULL,
            `notified_at` datetime DEFAULT NULL,
            `acknowledged_by` varchar(25) DEFAULT NULL,
            `acknowledged_at` datetime DEFAULT NULL,
            `acknowledgment_notes` text DEFAULT NULL,
            `escalated_flag` tinyint(1) NOT NULL DEFAULT 0,
            `escalation_level` int(11) NOT NULL DEFAULT 0,
            `escalated_at` datetime DEFAULT NULL,
            `resolved_flag` tinyint(1) NOT NULL DEFAULT 0,
            `resolved_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`alert_id`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_severity` (`severity`),
            KEY `idx_ack` (`acknowledged_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->seed_sonography_critical_definitions();
        return true;
    }

    private function seed_sonography_critical_definitions()
    {
        if (!$this->table_exists('sonography_critical_definitions')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('sonography_critical_definitions');
        if ($count > 0) return;

        $now = date('Y-m-d H:i:s');
        $defs = [
            ['ECTOPIC_PREGNANCY', 'Ectopic Pregnancy', 'ectopic,extrauterine,tubal pregnancy,adnexal mass with positive', 'OBSTETRIC', 'LIFE_THREATENING', 'Immediate OB/GYN consultation. Prepare for possible surgery.'],
            ['FETAL_DISTRESS', 'Fetal Distress', 'fetal distress,absent fetal heart,bradycardia,decelerations,non-reassuring', 'OBSTETRIC', 'LIFE_THREATENING', 'Immediate OB consultation. Consider emergency delivery.'],
            ['PLACENTAL_ABRUPTION', 'Placental Abruption', 'abruption,retroplacental,placental separation', 'OBSTETRIC', 'LIFE_THREATENING', 'Emergency OB consultation. Prepare for emergency C-section.'],
            ['PLACENTA_PREVIA', 'Placenta Previa', 'placenta previa,low-lying placenta,covering os', 'OBSTETRIC', 'CRITICAL', 'OB consultation. Bed rest. No vaginal exam.'],
            ['FETAL_DEMISE', 'Fetal Demise', 'fetal demise,no fetal heart,intrauterine death,IUFD', 'OBSTETRIC', 'CRITICAL', 'Confirm with senior sonographer. OB consultation.'],
            ['CORD_PROLAPSE', 'Cord Prolapse', 'cord prolapse,cord presentation,funic presentation', 'OBSTETRIC', 'LIFE_THREATENING', 'Emergency C-section. Elevate presenting part.'],
            ['INTERNAL_BLEEDING', 'Internal Bleeding', 'free fluid,hemoperitoneum,internal bleeding,hemorrhage', 'ABDOMINAL', 'LIFE_THREATENING', 'Immediate surgical consultation. Type and cross.'],
            ['AAA_ANEURYSM', 'Abdominal Aortic Aneurysm', 'AAA,aortic aneurysm,dilated aorta >5cm', 'VASCULAR', 'CRITICAL', 'Vascular surgery consultation. Monitor vitals.'],
            ['DVT', 'Deep Vein Thrombosis', 'DVT,deep vein thrombosis,non-compressible,thrombus', 'VASCULAR', 'URGENT', 'Anticoagulation consideration. Doppler confirmation.'],
            ['PERICARDIAL_EFFUSION', 'Pericardial Effusion', 'pericardial effusion,pericardial fluid,tamponade', 'CARDIAC', 'CRITICAL', 'Cardiology consultation. Monitor for tamponade.'],
            ['OVARIAN_TORSION', 'Ovarian Torsion', 'ovarian torsion,twisted ovary,absent ovarian flow', 'GYNECOLOGIC', 'CRITICAL', 'Immediate GYN consultation. Surgical emergency.'],
            ['RUPTURED_OVARIAN_CYST', 'Ruptured Ovarian Cyst', 'ruptured cyst,hemorrhagic cyst,cyst rupture', 'GYNECOLOGIC', 'URGENT', 'GYN consultation. Monitor hemodynamics.'],
            ['APPENDICITIS', 'Acute Appendicitis', 'appendicitis,inflamed appendix,periappendiceal', 'ABDOMINAL', 'URGENT', 'Surgical consultation. NPO status.'],
            ['CHOLECYSTITIS', 'Acute Cholecystitis', 'cholecystitis,gallbladder wall thickening,murphy sign', 'ABDOMINAL', 'URGENT', 'Surgical consultation. NPO. IV antibiotics.'],
            ['TESTICULAR_TORSION', 'Testicular Torsion', 'testicular torsion,absent testicular flow,twisted testicle', 'ABDOMINAL', 'LIFE_THREATENING', 'Immediate urology consultation. Surgical emergency.']
        ];

        foreach ($defs as $d) {
            $this->db->insert('sonography_critical_definitions', [
                'alert_code' => $d[0], 'alert_name' => $d[1], 'keywords' => $d[2],
                'category' => $d[3], 'severity' => $d[4], 'action_protocol' => $d[5],
                'requires_immediate_action' => 1,
                'escalation_minutes' => ($d[4] === 'LIFE_THREATENING' ? 5 : ($d[4] === 'CRITICAL' ? 10 : 20)),
                'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function detect_sonography_critical_alerts($findings_text, $result_text = '')
    {
        $this->ensure_sonography_critical_schema();
        $combined = strtolower($findings_text . ' ' . $result_text);
        $detected = [];

        $definitions = $this->db->get_where('sonography_critical_definitions', ['is_active' => 1, 'InActive' => 0])->result();
        foreach ($definitions as $def) {
            $keywords = array_map('trim', explode(',', strtolower($def->keywords)));
            foreach ($keywords as $kw) {
                if ($kw && strpos($combined, $kw) !== false) {
                    $detected[] = [
                        'def_id' => $def->def_id,
                        'alert_code' => $def->alert_code,
                        'alert_name' => $def->alert_name,
                        'category' => $def->category,
                        'severity' => $def->severity,
                        'action_protocol' => $def->action_protocol,
                        'matched_keyword' => $kw,
                        'escalation_minutes' => $def->escalation_minutes
                    ];
                    break;
                }
            }
        }

        usort($detected, function($a, $b) {
            $order = ['LIFE_THREATENING' => 0, 'CRITICAL' => 1, 'URGENT' => 2];
            return ($order[$a['severity']] ?? 3) - ($order[$b['severity']] ?? 3);
        });

        return $detected;
    }

    public function create_sonography_critical_alert($io_lab_id, $patient_no, $iop_id, $alert_def, $doctor_id = null, $sonographer_id = null)
    {
        $this->ensure_sonography_critical_schema();
        $now = date('Y-m-d H:i:s');

        $msg = strtoupper($alert_def['severity']) . ": {$alert_def['alert_name']}. ";
        if (!empty($alert_def['action_protocol'])) {
            $msg .= "ACTION: {$alert_def['action_protocol']}";
        }

        $this->db->insert('sonography_critical_alerts', [
            'io_lab_id' => $io_lab_id, 'patient_no' => $patient_no, 'iop_id' => $iop_id,
            'def_id' => $alert_def['def_id'], 'alert_code' => $alert_def['alert_code'],
            'alert_name' => $alert_def['alert_name'], 'category' => $alert_def['category'],
            'severity' => $alert_def['severity'], 'alert_message' => $msg,
            'ordering_doctor_id' => $doctor_id, 'sonographer_id' => $sonographer_id,
            'notified_at' => $now, 'created_at' => $now, 'InActive' => 0
        ]);

        $alert_id = $this->db->insert_id();
        $this->log_diagnostic_audit('SONOGRAPHY_CRITICAL_ALERT', 'sonography_critical_alerts', $alert_id, $io_lab_id, $patient_no, null, $msg);

        if ($doctor_id) {
            $this->create_notification('CRITICAL_RESULT', $io_lab_id, $patient_no, $doctor_id,
                "CRITICAL: {$alert_def['alert_name']}", $msg, 'URGENT');
        }

        return $alert_id;
    }

    public function get_pending_sonography_alerts($doctor_id = null, $limit = 50)
    {
        if (!$this->table_exists('sonography_critical_alerts')) return [];
        $this->db->select('a.*, p.firstname, p.lastname');
        $this->db->from('sonography_critical_alerts a');
        $this->db->join('patient_personal_info p', 'p.patient_no = a.patient_no', 'left');
        $this->db->where(['a.acknowledged_at' => null, 'a.resolved_flag' => 0, 'a.InActive' => 0]);
        if ($doctor_id) $this->db->where('a.ordering_doctor_id', $doctor_id);
        $this->db->order_by('FIELD(a.severity, "LIFE_THREATENING", "CRITICAL", "URGENT")', '', false);
        $this->db->order_by('a.created_at', 'DESC')->limit($limit);
        return $this->db->get()->result();
    }

    public function acknowledge_sonography_alert($alert_id, $notes = '')
    {
        if (!$this->table_exists('sonography_critical_alerts')) return false;
        $alert = $this->db->get_where('sonography_critical_alerts', ['alert_id' => (int)$alert_id, 'InActive' => 0])->row();
        if (!$alert) return false;

        $this->db->where('alert_id', (int)$alert_id)->update('sonography_critical_alerts', [
            'acknowledged_by' => $this->session->userdata('user_id'),
            'acknowledged_at' => date('Y-m-d H:i:s'),
            'acknowledgment_notes' => $notes
        ]);
        $this->log_diagnostic_audit('SONOGRAPHY_ALERT_ACK', 'sonography_critical_alerts', $alert_id, $alert->io_lab_id, $alert->patient_no, null, $notes);
        return true;
    }

    /* ================================================================== */
    /*  PHASE 4.5C: DOCTOR ACKNOWLEDGMENT ENFORCEMENT                      */
    /* ================================================================== */

    public function ensure_acknowledgment_enforcement_schema()
    {
        if ($this->table_exists('lab_critical_alerts') && !$this->column_exists('lab_critical_alerts', 'blocks_discharge')) {
            $this->safe_alter("ALTER TABLE `lab_critical_alerts` ADD COLUMN `blocks_discharge` tinyint(1) NOT NULL DEFAULT 1");
        }
        if ($this->table_exists('radiology_critical_alerts') && !$this->column_exists('radiology_critical_alerts', 'blocks_discharge')) {
            $this->safe_alter("ALTER TABLE `radiology_critical_alerts` ADD COLUMN `blocks_discharge` tinyint(1) NOT NULL DEFAULT 1");
        }
        if ($this->table_exists('sonography_critical_alerts') && !$this->column_exists('sonography_critical_alerts', 'blocks_discharge')) {
            $this->safe_alter("ALTER TABLE `sonography_critical_alerts` ADD COLUMN `blocks_discharge` tinyint(1) NOT NULL DEFAULT 1");
        }
        return true;
    }

    public function get_unacknowledged_critical_alerts($patient_no)
    {
        $this->ensure_acknowledgment_enforcement_schema();
        $alerts = ['lab' => [], 'radiology' => [], 'sonography' => []];

        if ($this->table_exists('lab_critical_alerts')) {
            $alerts['lab'] = $this->db->get_where('lab_critical_alerts', [
                'patient_no' => $patient_no, 'acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0
            ])->result();
        }
        if ($this->table_exists('radiology_critical_alerts')) {
            $alerts['radiology'] = $this->db->get_where('radiology_critical_alerts', [
                'patient_no' => $patient_no, 'acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0
            ])->result();
        }
        if ($this->table_exists('sonography_critical_alerts')) {
            $alerts['sonography'] = $this->db->get_where('sonography_critical_alerts', [
                'patient_no' => $patient_no, 'acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0
            ])->result();
        }

        return $alerts;
    }

    public function count_unacknowledged_critical_alerts($patient_no)
    {
        $alerts = $this->get_unacknowledged_critical_alerts($patient_no);
        return count($alerts['lab']) + count($alerts['radiology']) + count($alerts['sonography']);
    }

    public function can_discharge_patient($patient_no)
    {
        $count = $this->count_unacknowledged_critical_alerts($patient_no);
        return $count === 0;
    }

    public function get_discharge_blocking_alerts($patient_no)
    {
        $alerts = $this->get_unacknowledged_critical_alerts($patient_no);
        $blocking = [];

        foreach ($alerts['lab'] as $a) {
            $blocking[] = ['type' => 'LAB', 'alert_id' => $a->alert_id, 'test_name' => $a->test_name,
                'severity' => $a->alert_severity, 'created_at' => $a->created_at];
        }
        foreach ($alerts['radiology'] as $a) {
            $blocking[] = ['type' => 'RADIOLOGY', 'alert_id' => $a->alert_id, 'finding_name' => $a->finding_name,
                'severity' => $a->severity, 'created_at' => $a->created_at];
        }
        foreach ($alerts['sonography'] as $a) {
            $blocking[] = ['type' => 'SONOGRAPHY', 'alert_id' => $a->alert_id, 'alert_name' => $a->alert_name,
                'severity' => $a->severity, 'created_at' => $a->created_at];
        }

        return $blocking;
    }

    /* ================================================================== */
    /*  PHASE 4.5D: RESULT LOCKING & AMENDMENT TRACKING                    */
    /* ================================================================== */

    public function ensure_result_locking_schema()
    {
        if ($this->table_exists('iop_laboratory')) {
            if (!$this->column_exists('iop_laboratory', 'is_locked')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory` ADD COLUMN `is_locked` tinyint(1) NOT NULL DEFAULT 0");
            }
            if (!$this->column_exists('iop_laboratory', 'locked_at')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory` ADD COLUMN `locked_at` datetime DEFAULT NULL");
            }
            if (!$this->column_exists('iop_laboratory', 'locked_by')) {
                $this->safe_alter("ALTER TABLE `iop_laboratory` ADD COLUMN `locked_by` varchar(25) DEFAULT NULL");
            }
        }

        if ($this->table_exists('radiology_results')) {
            if (!$this->column_exists('radiology_results', 'is_locked')) {
                $this->safe_alter("ALTER TABLE `radiology_results` ADD COLUMN `is_locked` tinyint(1) NOT NULL DEFAULT 0");
            }
            if (!$this->column_exists('radiology_results', 'locked_at')) {
                $this->safe_alter("ALTER TABLE `radiology_results` ADD COLUMN `locked_at` datetime DEFAULT NULL");
            }
            if (!$this->column_exists('radiology_results', 'locked_by')) {
                $this->safe_alter("ALTER TABLE `radiology_results` ADD COLUMN `locked_by` varchar(25) DEFAULT NULL");
            }
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_amendments` (
            `amendment_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `io_lab_id` int(11) DEFAULT NULL,
            `order_id` int(11) DEFAULT NULL,
            `diagnostic_type` enum('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `original_result` text NOT NULL,
            `amended_result` text NOT NULL,
            `amendment_reason` text NOT NULL,
            `amended_by` varchar(25) NOT NULL,
            `amended_at` datetime NOT NULL,
            `approved_by` varchar(25) DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `approval_status` enum('PENDING','APPROVED','REJECTED') NOT NULL DEFAULT 'PENDING',
            `rejection_reason` text DEFAULT NULL,
            `ip_address` varchar(45) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`amendment_id`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_order` (`order_id`),
            KEY `idx_status` (`approval_status`),
            KEY `idx_date` (`amended_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        return true;
    }

    public function lock_lab_result($io_lab_id)
    {
        $this->ensure_result_locking_schema();
        if (!$this->table_exists('iop_laboratory')) return false;

        $now = date('Y-m-d H:i:s');
        $user = $this->session->userdata('user_id');

        $this->db->where('io_lab_id', (int)$io_lab_id)->update('iop_laboratory', [
            'is_locked' => 1, 'locked_at' => $now, 'locked_by' => $user
        ]);

        $this->log_diagnostic_audit('RESULT_LOCKED', 'iop_laboratory', $io_lab_id, $io_lab_id, null, null, 'Result locked after verification');
        return true;
    }

    public function is_result_locked($io_lab_id)
    {
        if (!$this->table_exists('iop_laboratory')) return false;
        if (!$this->column_exists('iop_laboratory', 'is_locked')) return false;

        $row = $this->db->select('is_locked')->get_where('iop_laboratory', ['io_lab_id' => (int)$io_lab_id])->row();
        return $row && (int)$row->is_locked === 1;
    }

    public function request_amendment($io_lab_id, $diagnostic_type, $original_result, $amended_result, $reason)
    {
        $this->ensure_result_locking_schema();
        $now = date('Y-m-d H:i:s');
        $user = $this->session->userdata('user_id');

        $this->db->insert('diagnostic_amendments', [
            'io_lab_id' => (int)$io_lab_id, 'diagnostic_type' => $diagnostic_type,
            'original_result' => $original_result, 'amended_result' => $amended_result,
            'amendment_reason' => $reason, 'amended_by' => $user, 'amended_at' => $now,
            'approval_status' => 'PENDING', 'ip_address' => $this->input->ip_address(),
            'created_at' => $now, 'InActive' => 0
        ]);

        $amendment_id = $this->db->insert_id();
        $this->log_diagnostic_audit('AMENDMENT_REQUESTED', 'diagnostic_amendments', $amendment_id, $io_lab_id, null, $original_result, $amended_result);
        return $amendment_id;
    }

    public function approve_amendment($amendment_id, $apply_change = true)
    {
        $this->ensure_result_locking_schema();
        $amendment = $this->db->get_where('diagnostic_amendments', ['amendment_id' => (int)$amendment_id, 'InActive' => 0])->row();
        if (!$amendment) return false;

        $now = date('Y-m-d H:i:s');
        $user = $this->session->userdata('user_id');

        $this->db->where('amendment_id', (int)$amendment_id)->update('diagnostic_amendments', [
            'approved_by' => $user, 'approved_at' => $now, 'approval_status' => 'APPROVED'
        ]);

        if ($apply_change && $amendment->io_lab_id && $amendment->diagnostic_type === 'LAB') {
            $this->db->where('io_lab_id', (int)$amendment->io_lab_id)->update('iop_laboratory', [
                'result' => $amendment->amended_result
            ]);
        }

        $this->log_diagnostic_audit('AMENDMENT_APPROVED', 'diagnostic_amendments', $amendment_id, $amendment->io_lab_id, null, null, 'Amendment approved and applied');
        return true;
    }

    public function reject_amendment($amendment_id, $reason)
    {
        $this->ensure_result_locking_schema();
        $amendment = $this->db->get_where('diagnostic_amendments', ['amendment_id' => (int)$amendment_id, 'InActive' => 0])->row();
        if (!$amendment) return false;

        $now = date('Y-m-d H:i:s');
        $user = $this->session->userdata('user_id');

        $this->db->where('amendment_id', (int)$amendment_id)->update('diagnostic_amendments', [
            'approved_by' => $user, 'approved_at' => $now, 'approval_status' => 'REJECTED', 'rejection_reason' => $reason
        ]);

        $this->log_diagnostic_audit('AMENDMENT_REJECTED', 'diagnostic_amendments', $amendment_id, $amendment->io_lab_id, null, null, $reason);
        return true;
    }

    public function get_pending_amendments($limit = 50)
    {
        if (!$this->table_exists('diagnostic_amendments')) return [];
        $this->db->where(['approval_status' => 'PENDING', 'InActive' => 0]);
        $this->db->order_by('amended_at', 'DESC')->limit($limit);
        return $this->db->get('diagnostic_amendments')->result();
    }

    /* ================================================================== */
    /*  PHASE 4.5E: MULTI-LEVEL ESCALATION & AUTO-NOTIFICATION             */
    /* ================================================================== */

    public function ensure_escalation_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_escalation_config` (
            `config_id` int(11) NOT NULL AUTO_INCREMENT,
            `escalation_level` int(11) NOT NULL,
            `escalation_role` varchar(50) NOT NULL,
            `escalation_title` varchar(100) DEFAULT NULL,
            `timeout_minutes` int(11) NOT NULL DEFAULT 30,
            `notification_method` enum('SYSTEM','SMS','EMAIL','ALL') NOT NULL DEFAULT 'SYSTEM',
            `diagnostic_type` enum('LAB','RADIOLOGY','SONOGRAPHY','ALL') NOT NULL DEFAULT 'ALL',
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`config_id`),
            KEY `idx_level` (`escalation_level`),
            KEY `idx_type` (`diagnostic_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->db->query("CREATE TABLE IF NOT EXISTS `diagnostic_escalation_log` (
            `log_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `alert_type` enum('LAB','RADIOLOGY','SONOGRAPHY') NOT NULL,
            `alert_id` bigint(11) NOT NULL,
            `escalation_level` int(11) NOT NULL,
            `escalated_to_role` varchar(50) DEFAULT NULL,
            `escalated_to_user` varchar(25) DEFAULT NULL,
            `notification_method` varchar(20) DEFAULT NULL,
            `notification_sent_at` datetime DEFAULT NULL,
            `notification_status` enum('PENDING','SENT','DELIVERED','FAILED') DEFAULT 'PENDING',
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`log_id`),
            KEY `idx_alert` (`alert_type`, `alert_id`),
            KEY `idx_level` (`escalation_level`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $this->seed_escalation_config();
        return true;
    }

    private function seed_escalation_config()
    {
        if (!$this->table_exists('diagnostic_escalation_config')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('diagnostic_escalation_config');
        if ($count > 0) return;

        $now = date('Y-m-d H:i:s');
        $configs = [
            [1, 'ordering_doctor', 'Ordering Physician', 15, 'SYSTEM', 'ALL'],
            [2, 'department_head', 'Department Head', 30, 'SYSTEM', 'ALL'],
            [3, 'medical_director', 'Medical Director', 45, 'ALL', 'ALL'],
            [4, 'cmo', 'Chief Medical Officer', 60, 'ALL', 'ALL']
        ];

        foreach ($configs as $c) {
            $this->db->insert('diagnostic_escalation_config', [
                'escalation_level' => $c[0], 'escalation_role' => $c[1], 'escalation_title' => $c[2],
                'timeout_minutes' => $c[3], 'notification_method' => $c[4], 'diagnostic_type' => $c[5],
                'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function check_and_escalate_alerts()
    {
        $this->ensure_escalation_schema();
        $escalated = ['lab' => 0, 'radiology' => 0, 'sonography' => 0];

        $configs = $this->db->get_where('diagnostic_escalation_config', ['is_active' => 1, 'InActive' => 0])->result();
        $config_map = [];
        foreach ($configs as $c) {
            $config_map[$c->escalation_level] = $c;
        }

        // Lab alerts
        if ($this->table_exists('lab_critical_alerts')) {
            $pending = $this->db->query("SELECT * FROM lab_critical_alerts 
                WHERE acknowledged_at IS NULL AND resolved_flag = 0 AND InActive = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)")->result();

            foreach ($pending as $alert) {
                $minutes_elapsed = (time() - strtotime($alert->created_at)) / 60;
                $next_level = $alert->escalation_level + 1;

                if (isset($config_map[$next_level])) {
                    $cfg = $config_map[$next_level];
                    $threshold = 0;
                    for ($i = 1; $i <= $next_level; $i++) {
                        if (isset($config_map[$i])) $threshold += $config_map[$i]->timeout_minutes;
                    }

                    if ($minutes_elapsed >= $threshold) {
                        $this->escalate_lab_alert($alert->alert_id, $next_level, $cfg);
                        $escalated['lab']++;
                    }
                }
            }
        }

        // Radiology alerts
        if ($this->table_exists('radiology_critical_alerts')) {
            $pending = $this->db->query("SELECT * FROM radiology_critical_alerts 
                WHERE acknowledged_at IS NULL AND resolved_flag = 0 AND InActive = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")->result();

            foreach ($pending as $alert) {
                $minutes_elapsed = (time() - strtotime($alert->created_at)) / 60;
                $next_level = $alert->escalation_level + 1;

                if (isset($config_map[$next_level])) {
                    $cfg = $config_map[$next_level];
                    $threshold = 0;
                    for ($i = 1; $i <= $next_level; $i++) {
                        if (isset($config_map[$i])) $threshold += $config_map[$i]->timeout_minutes;
                    }

                    if ($minutes_elapsed >= $threshold) {
                        $this->escalate_radiology_alert($alert->alert_id, $next_level, $cfg);
                        $escalated['radiology']++;
                    }
                }
            }
        }

        // Sonography alerts
        if ($this->table_exists('sonography_critical_alerts')) {
            $pending = $this->db->query("SELECT * FROM sonography_critical_alerts 
                WHERE acknowledged_at IS NULL AND resolved_flag = 0 AND InActive = 0
                AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)")->result();

            foreach ($pending as $alert) {
                $minutes_elapsed = (time() - strtotime($alert->created_at)) / 60;
                $next_level = $alert->escalation_level + 1;

                if (isset($config_map[$next_level])) {
                    $cfg = $config_map[$next_level];
                    $threshold = 0;
                    for ($i = 1; $i <= $next_level; $i++) {
                        if (isset($config_map[$i])) $threshold += $config_map[$i]->timeout_minutes;
                    }

                    if ($minutes_elapsed >= $threshold) {
                        $this->escalate_sonography_alert($alert->alert_id, $next_level, $cfg);
                        $escalated['sonography']++;
                    }
                }
            }
        }

        return $escalated;
    }

    private function escalate_lab_alert($alert_id, $level, $config)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->where('alert_id', (int)$alert_id)->update('lab_critical_alerts', [
            'escalated_flag' => 1, 'escalation_level' => $level, 'escalated_at' => $now
        ]);

        $this->db->insert('diagnostic_escalation_log', [
            'alert_type' => 'LAB', 'alert_id' => $alert_id, 'escalation_level' => $level,
            'escalated_to_role' => $config->escalation_role, 'notification_method' => $config->notification_method,
            'notification_sent_at' => $now, 'notification_status' => 'SENT', 'created_at' => $now, 'InActive' => 0
        ]);

        $this->log_diagnostic_audit('ALERT_ESCALATED', 'lab_critical_alerts', $alert_id, null, null, null,
            "Escalated to level {$level}: {$config->escalation_title}");
    }

    private function escalate_radiology_alert($alert_id, $level, $config)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->where('alert_id', (int)$alert_id)->update('radiology_critical_alerts', [
            'escalated_flag' => 1, 'escalation_level' => $level, 'escalated_at' => $now
        ]);

        $this->db->insert('diagnostic_escalation_log', [
            'alert_type' => 'RADIOLOGY', 'alert_id' => $alert_id, 'escalation_level' => $level,
            'escalated_to_role' => $config->escalation_role, 'notification_method' => $config->notification_method,
            'notification_sent_at' => $now, 'notification_status' => 'SENT', 'created_at' => $now, 'InActive' => 0
        ]);

        $this->log_diagnostic_audit('ALERT_ESCALATED', 'radiology_critical_alerts', $alert_id, null, null, null,
            "Escalated to level {$level}: {$config->escalation_title}");
    }

    private function escalate_sonography_alert($alert_id, $level, $config)
    {
        $now = date('Y-m-d H:i:s');

        $this->db->where('alert_id', (int)$alert_id)->update('sonography_critical_alerts', [
            'escalated_flag' => 1, 'escalation_level' => $level, 'escalated_at' => $now
        ]);

        $this->db->insert('diagnostic_escalation_log', [
            'alert_type' => 'SONOGRAPHY', 'alert_id' => $alert_id, 'escalation_level' => $level,
            'escalated_to_role' => $config->escalation_role, 'notification_method' => $config->notification_method,
            'notification_sent_at' => $now, 'notification_status' => 'SENT', 'created_at' => $now, 'InActive' => 0
        ]);

        $this->log_diagnostic_audit('ALERT_ESCALATED', 'sonography_critical_alerts', $alert_id, null, null, null,
            "Escalated to level {$level}: {$config->escalation_title}");
    }

    public function get_all_pending_critical_alerts($limit = 100)
    {
        $all = [];

        if ($this->table_exists('lab_critical_alerts')) {
            $lab = $this->db->select('alert_id, "LAB" as type, patient_no, test_name as item_name, alert_severity as severity, created_at, escalation_level')
                ->where(['acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0])
                ->get('lab_critical_alerts')->result_array();
            $all = array_merge($all, $lab);
        }

        if ($this->table_exists('radiology_critical_alerts')) {
            $rad = $this->db->select('alert_id, "RADIOLOGY" as type, patient_no, finding_name as item_name, severity, created_at, escalation_level')
                ->where(['acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0])
                ->get('radiology_critical_alerts')->result_array();
            $all = array_merge($all, $rad);
        }

        if ($this->table_exists('sonography_critical_alerts')) {
            $sono = $this->db->select('alert_id, "SONOGRAPHY" as type, patient_no, alert_name as item_name, severity, created_at, escalation_level')
                ->where(['acknowledged_at' => null, 'resolved_flag' => 0, 'InActive' => 0])
                ->get('sonography_critical_alerts')->result_array();
            $all = array_merge($all, $sono);
        }

        usort($all, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return array_slice($all, 0, $limit);
    }

    /* ================================================================== */
    /*  PERFORMANCE INDEXES                                                */
    /* ================================================================== */

    public function add_performance_indexes()
    {
        $indexes = [
            "ALTER TABLE `iop_laboratory` ADD INDEX IF NOT EXISTS `idx_iop_cat_active` (`iop_id`, `category_id`, `InActive`)",
            "ALTER TABLE `iop_laboratory` ADD INDEX IF NOT EXISTS `idx_result_date` (`result`(10), `dDate`)",
            "ALTER TABLE `iop_laboratory_workflow` ADD INDEX IF NOT EXISTS `idx_status_updated` (`status`, `updated_at`)"
        ];
        foreach ($indexes as $sql) {
            $this->safe_alter($sql);
        }
        return true;
    }

    /* ================================================================== */
    /*  PHASE 4.5 MASTER INITIALIZATION                                    */
    /* ================================================================== */

    public function ensure_phase45_schemas()
    {
        $this->ensure_radiology_critical_schema();
        $this->ensure_sonography_critical_schema();
        $this->ensure_acknowledgment_enforcement_schema();
        $this->ensure_result_locking_schema();
        $this->ensure_escalation_schema();
        return true;
    }

    /* ================================================================== */
    /*  PHASE 5: CHAIN-OF-CUSTODY TRACKING (Week 3)                        */
    /* ================================================================== */

    public function ensure_chain_of_custody_schema()
    {
        // Chain of custody handoff tracking
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_sample_custody` (
            `custody_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `sample_id` bigint(11) NOT NULL,
            `sample_barcode` varchar(50) NOT NULL,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `handoff_sequence` int(11) NOT NULL DEFAULT 1,
            `from_user_id` varchar(25) DEFAULT NULL,
            `from_user_name` varchar(100) DEFAULT NULL,
            `from_location` varchar(100) DEFAULT NULL,
            `to_user_id` varchar(25) NOT NULL,
            `to_user_name` varchar(100) DEFAULT NULL,
            `to_location` varchar(100) NOT NULL,
            `handoff_type` enum('COLLECTION','TRANSPORT','RECEIVE','PROCESS','STORAGE','DISPOSAL') NOT NULL,
            `temperature_celsius` decimal(5,2) DEFAULT NULL,
            `temperature_status` enum('NORMAL','WARNING','CRITICAL') DEFAULT NULL,
            `condition_notes` text DEFAULT NULL,
            `signature_hash` varchar(64) DEFAULT NULL,
            `device_id` varchar(100) DEFAULT NULL,
            `gps_coordinates` varchar(50) DEFAULT NULL,
            `handoff_at` datetime NOT NULL,
            `verified_by` varchar(25) DEFAULT NULL,
            `verified_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`custody_id`),
            KEY `idx_sample` (`sample_id`),
            KEY `idx_barcode` (`sample_barcode`),
            KEY `idx_lab` (`io_lab_id`),
            KEY `idx_handoff` (`handoff_at`),
            KEY `idx_to_user` (`to_user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Sample location tracking (real-time)
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_sample_locations` (
            `location_id` int(11) NOT NULL AUTO_INCREMENT,
            `location_code` varchar(20) NOT NULL,
            `location_name` varchar(100) NOT NULL,
            `location_type` enum('WARD','PHLEBOTOMY','TRANSPORT','LAB_RECEPTION','ANALYZER','STORAGE','ARCHIVE','DISPOSAL') NOT NULL,
            `department` varchar(100) DEFAULT NULL,
            `building` varchar(100) DEFAULT NULL,
            `floor` varchar(20) DEFAULT NULL,
            `room` varchar(50) DEFAULT NULL,
            `temperature_required` decimal(5,2) DEFAULT NULL,
            `temperature_tolerance` decimal(5,2) DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`location_id`),
            UNIQUE KEY `uq_code` (`location_code`),
            KEY `idx_type` (`location_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Temperature monitoring log
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_sample_temperature_log` (
            `temp_log_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `sample_id` bigint(11) NOT NULL,
            `sample_barcode` varchar(50) NOT NULL,
            `location_code` varchar(20) DEFAULT NULL,
            `temperature_celsius` decimal(5,2) NOT NULL,
            `humidity_percent` decimal(5,2) DEFAULT NULL,
            `status` enum('NORMAL','WARNING','CRITICAL','BREACH') NOT NULL DEFAULT 'NORMAL',
            `alert_sent` tinyint(1) NOT NULL DEFAULT 0,
            `recorded_by` varchar(25) DEFAULT NULL,
            `recorded_at` datetime NOT NULL,
            `device_id` varchar(100) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`temp_log_id`),
            KEY `idx_sample` (`sample_id`),
            KEY `idx_status` (`status`),
            KEY `idx_recorded` (`recorded_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Add custody columns to sample tracking if not exists
        if ($this->table_exists('lab_sample_tracking')) {
            $cols = [
                'current_location_code' => "ADD COLUMN `current_location_code` varchar(20) DEFAULT NULL",
                'current_custodian_id' => "ADD COLUMN `current_custodian_id` varchar(25) DEFAULT NULL",
                'last_temperature' => "ADD COLUMN `last_temperature` decimal(5,2) DEFAULT NULL",
                'temperature_breach_flag' => "ADD COLUMN `temperature_breach_flag` tinyint(1) NOT NULL DEFAULT 0",
                'custody_chain_hash' => "ADD COLUMN `custody_chain_hash` varchar(64) DEFAULT NULL",
                'total_handoffs' => "ADD COLUMN `total_handoffs` int(11) NOT NULL DEFAULT 0"
            ];
            foreach ($cols as $col => $sql) {
                if (!$this->column_exists('lab_sample_tracking', $col)) {
                    $this->safe_alter("ALTER TABLE `lab_sample_tracking` {$sql}");
                }
            }
        }

        $this->seed_default_locations();
        return true;
    }

    private function seed_default_locations()
    {
        if (!$this->table_exists('lab_sample_locations')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('lab_sample_locations');
        if ($count > 0) return;

        $locations = [
            ['LOC-WARD-GEN', 'General Ward', 'WARD', 'Nursing', 'Main', '1', 'Ward A', null, null],
            ['LOC-WARD-ICU', 'ICU', 'WARD', 'Intensive Care', 'Main', '2', 'ICU', null, null],
            ['LOC-WARD-PED', 'Pediatric Ward', 'WARD', 'Pediatrics', 'Main', '1', 'Ward B', null, null],
            ['LOC-PHLEB-1', 'Phlebotomy Station 1', 'PHLEBOTOMY', 'Laboratory', 'Main', 'G', 'Room 101', null, null],
            ['LOC-PHLEB-2', 'Phlebotomy Station 2', 'PHLEBOTOMY', 'Laboratory', 'Main', 'G', 'Room 102', null, null],
            ['LOC-TRANS-1', 'Transport Cart 1', 'TRANSPORT', 'Laboratory', null, null, null, 4.00, 2.00],
            ['LOC-TRANS-2', 'Transport Cart 2', 'TRANSPORT', 'Laboratory', null, null, null, 4.00, 2.00],
            ['LOC-LAB-REC', 'Lab Reception', 'LAB_RECEPTION', 'Laboratory', 'Lab', 'G', 'Reception', null, null],
            ['LOC-LAB-HAEM', 'Hematology Analyzer', 'ANALYZER', 'Laboratory', 'Lab', 'G', 'Haem Lab', null, null],
            ['LOC-LAB-CHEM', 'Chemistry Analyzer', 'ANALYZER', 'Laboratory', 'Lab', 'G', 'Chem Lab', null, null],
            ['LOC-LAB-MICRO', 'Microbiology Lab', 'ANALYZER', 'Laboratory', 'Lab', 'G', 'Micro Lab', 37.00, 1.00],
            ['LOC-STORE-COLD', 'Cold Storage', 'STORAGE', 'Laboratory', 'Lab', 'B1', 'Storage', 4.00, 2.00],
            ['LOC-STORE-FREEZE', 'Freezer Storage', 'STORAGE', 'Laboratory', 'Lab', 'B1', 'Freezer', -20.00, 5.00],
            ['LOC-ARCHIVE', 'Sample Archive', 'ARCHIVE', 'Laboratory', 'Lab', 'B1', 'Archive', 4.00, 2.00],
            ['LOC-DISPOSAL', 'Biohazard Disposal', 'DISPOSAL', 'Laboratory', 'Lab', 'B1', 'Disposal', null, null]
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($locations as $loc) {
            $this->db->insert('lab_sample_locations', [
                'location_code' => $loc[0], 'location_name' => $loc[1], 'location_type' => $loc[2],
                'department' => $loc[3], 'building' => $loc[4], 'floor' => $loc[5], 'room' => $loc[6],
                'temperature_required' => $loc[7], 'temperature_tolerance' => $loc[8],
                'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function record_sample_handoff($sample_id, $to_user_id, $to_location, $handoff_type, $temperature = null, $notes = null, $signature_hash = null)
    {
        $this->ensure_chain_of_custody_schema();
        
        $sample = $this->db->get_where('lab_sample_tracking', ['sample_id' => (int)$sample_id, 'InActive' => 0])->row();
        if (!$sample) return ['ok' => false, 'error' => 'Sample not found'];

        $now = date('Y-m-d H:i:s');
        $user_id = $this->session->userdata('user_id');
        
        // Get current sequence
        $last_custody = $this->db->select('handoff_sequence')->where('sample_id', (int)$sample_id)
            ->order_by('handoff_sequence', 'DESC')->limit(1)->get('lab_sample_custody')->row();
        $sequence = $last_custody ? $last_custody->handoff_sequence + 1 : 1;

        // Get user names
        $from_user = $this->db->get_where('user', ['user_id' => $user_id])->row();
        $to_user = $this->db->get_where('user', ['user_id' => $to_user_id])->row();

        // Determine temperature status
        $temp_status = null;
        if ($temperature !== null) {
            $location = $this->db->get_where('lab_sample_locations', ['location_code' => $to_location, 'InActive' => 0])->row();
            if ($location && $location->temperature_required !== null) {
                $diff = abs($temperature - $location->temperature_required);
                if ($diff <= ($location->temperature_tolerance ?? 2)) {
                    $temp_status = 'NORMAL';
                } elseif ($diff <= ($location->temperature_tolerance ?? 2) * 2) {
                    $temp_status = 'WARNING';
                } else {
                    $temp_status = 'CRITICAL';
                }
            }
        }

        // Generate signature hash if not provided
        if (!$signature_hash) {
            $signature_hash = hash('sha256', $sample->sample_barcode . $user_id . $to_user_id . $to_location . $now);
        }

        $this->db->insert('lab_sample_custody', [
            'sample_id' => (int)$sample_id,
            'sample_barcode' => $sample->sample_barcode,
            'io_lab_id' => $sample->io_lab_id,
            'patient_no' => $sample->patient_no,
            'handoff_sequence' => $sequence,
            'from_user_id' => $sample->current_custodian_id ?? $user_id,
            'from_user_name' => $from_user ? $from_user->username : null,
            'from_location' => $sample->current_location_code,
            'to_user_id' => $to_user_id,
            'to_user_name' => $to_user ? $to_user->username : null,
            'to_location' => $to_location,
            'handoff_type' => $handoff_type,
            'temperature_celsius' => $temperature,
            'temperature_status' => $temp_status,
            'condition_notes' => $notes,
            'signature_hash' => $signature_hash,
            'handoff_at' => $now,
            'created_at' => $now,
            'InActive' => 0
        ]);
        $custody_id = $this->db->insert_id();

        // Update sample tracking
        $chain_hash = hash('sha256', ($sample->custody_chain_hash ?? '') . $signature_hash);
        $this->db->where('sample_id', (int)$sample_id)->update('lab_sample_tracking', [
            'current_location_code' => $to_location,
            'current_custodian_id' => $to_user_id,
            'last_temperature' => $temperature,
            'temperature_breach_flag' => ($temp_status === 'CRITICAL') ? 1 : 0,
            'custody_chain_hash' => $chain_hash,
            'total_handoffs' => $sequence
        ]);

        // Log temperature if provided
        if ($temperature !== null) {
            $this->log_sample_temperature($sample_id, $sample->sample_barcode, $to_location, $temperature, $temp_status);
        }

        $this->log_diagnostic_audit('CUSTODY_HANDOFF', 'lab_sample_custody', $custody_id, $sample->io_lab_id, 
            $sample->patient_no, $sample->current_location_code, $to_location);

        return ['ok' => true, 'custody_id' => $custody_id, 'sequence' => $sequence, 'temp_status' => $temp_status];
    }

    public function log_sample_temperature($sample_id, $barcode, $location_code, $temperature, $status = null, $humidity = null)
    {
        $this->ensure_chain_of_custody_schema();
        
        if (!$status) {
            $location = $this->db->get_where('lab_sample_locations', ['location_code' => $location_code, 'InActive' => 0])->row();
            if ($location && $location->temperature_required !== null) {
                $diff = abs($temperature - $location->temperature_required);
                $tol = $location->temperature_tolerance ?? 2;
                if ($diff <= $tol) $status = 'NORMAL';
                elseif ($diff <= $tol * 2) $status = 'WARNING';
                elseif ($diff <= $tol * 3) $status = 'CRITICAL';
                else $status = 'BREACH';
            } else {
                $status = 'NORMAL';
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('lab_sample_temperature_log', [
            'sample_id' => (int)$sample_id,
            'sample_barcode' => $barcode,
            'location_code' => $location_code,
            'temperature_celsius' => $temperature,
            'humidity_percent' => $humidity,
            'status' => $status,
            'alert_sent' => 0,
            'recorded_by' => $this->session->userdata('user_id'),
            'recorded_at' => $now,
            'created_at' => $now,
            'InActive' => 0
        ]);

        // Update sample if breach
        if ($status === 'BREACH' || $status === 'CRITICAL') {
            $this->db->where('sample_id', (int)$sample_id)->update('lab_sample_tracking', [
                'temperature_breach_flag' => 1,
                'last_temperature' => $temperature
            ]);
        }

        return $status;
    }

    public function get_sample_custody_chain($sample_id)
    {
        if (!$this->table_exists('lab_sample_custody')) return [];
        return $this->db->where(['sample_id' => (int)$sample_id, 'InActive' => 0])
            ->order_by('handoff_sequence', 'ASC')->get('lab_sample_custody')->result();
    }

    public function verify_custody_chain($sample_id)
    {
        $chain = $this->get_sample_custody_chain($sample_id);
        if (empty($chain)) return ['valid' => false, 'error' => 'No custody records'];

        $computed_hash = '';
        foreach ($chain as $custody) {
            $computed_hash = hash('sha256', $computed_hash . $custody->signature_hash);
        }

        $sample = $this->db->get_where('lab_sample_tracking', ['sample_id' => (int)$sample_id, 'InActive' => 0])->row();
        if (!$sample) return ['valid' => false, 'error' => 'Sample not found'];

        return [
            'valid' => ($computed_hash === $sample->custody_chain_hash),
            'computed_hash' => $computed_hash,
            'stored_hash' => $sample->custody_chain_hash,
            'total_handoffs' => count($chain)
        ];
    }

    public function get_sample_locations($type = null)
    {
        if (!$this->table_exists('lab_sample_locations')) return [];
        $this->db->where(['is_active' => 1, 'InActive' => 0]);
        if ($type) $this->db->where('location_type', $type);
        return $this->db->order_by('location_name', 'ASC')->get('lab_sample_locations')->result();
    }

    /* ================================================================== */
    /*  PHASE 5: SAMPLE MOVEMENT AUDIT & RECOLLECTION WORKFLOW             */
    /* ================================================================== */

    public function ensure_sample_movement_schema()
    {
        // Sample movement audit log
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_sample_movement_audit` (
            `movement_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `sample_id` bigint(11) NOT NULL,
            `sample_barcode` varchar(50) NOT NULL,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `movement_type` enum('SCAN_IN','SCAN_OUT','LOCATION_CHANGE','STATUS_CHANGE','TEMPERATURE_CHECK','QUALITY_CHECK') NOT NULL,
            `from_location` varchar(100) DEFAULT NULL,
            `to_location` varchar(100) DEFAULT NULL,
            `from_status` varchar(50) DEFAULT NULL,
            `to_status` varchar(50) DEFAULT NULL,
            `performed_by` varchar(25) NOT NULL,
            `performed_at` datetime NOT NULL,
            `device_type` varchar(50) DEFAULT NULL,
            `device_id` varchar(100) DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`movement_id`),
            KEY `idx_sample` (`sample_id`),
            KEY `idx_barcode` (`sample_barcode`),
            KEY `idx_performed` (`performed_at`),
            KEY `idx_type` (`movement_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Sample rejection reasons
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_sample_rejection_reasons` (
            `reason_id` int(11) NOT NULL AUTO_INCREMENT,
            `reason_code` varchar(20) NOT NULL,
            `reason_name` varchar(100) NOT NULL,
            `reason_category` enum('COLLECTION','TRANSPORT','QUALITY','LABELING','VOLUME','CONTAMINATION','OTHER') NOT NULL,
            `requires_recollection` tinyint(1) NOT NULL DEFAULT 1,
            `severity` enum('LOW','MEDIUM','HIGH') NOT NULL DEFAULT 'MEDIUM',
            `description` text DEFAULT NULL,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`reason_id`),
            UNIQUE KEY `uq_code` (`reason_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Recollection requests
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_recollection_requests` (
            `recollection_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `original_sample_id` bigint(11) NOT NULL,
            `original_io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `iop_id` varchar(25) DEFAULT NULL,
            `test_name` varchar(255) DEFAULT NULL,
            `rejection_reason_id` int(11) DEFAULT NULL,
            `rejection_reason_text` varchar(255) DEFAULT NULL,
            `priority` enum('ROUTINE','URGENT','STAT') NOT NULL DEFAULT 'ROUTINE',
            `status` enum('PENDING','NOTIFIED','SCHEDULED','COLLECTED','CANCELLED') NOT NULL DEFAULT 'PENDING',
            `requested_by` varchar(25) NOT NULL,
            `requested_at` datetime NOT NULL,
            `notified_at` datetime DEFAULT NULL,
            `notification_method` varchar(50) DEFAULT NULL,
            `scheduled_at` datetime DEFAULT NULL,
            `collected_at` datetime DEFAULT NULL,
            `new_sample_id` bigint(11) DEFAULT NULL,
            `new_io_lab_id` int(11) DEFAULT NULL,
            `cancelled_by` varchar(25) DEFAULT NULL,
            `cancelled_at` datetime DEFAULT NULL,
            `cancellation_reason` text DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`recollection_id`),
            KEY `idx_original` (`original_sample_id`),
            KEY `idx_patient` (`patient_no`),
            KEY `idx_status` (`status`),
            KEY `idx_priority` (`priority`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Add rejection columns to sample tracking
        if ($this->table_exists('lab_sample_tracking')) {
            $cols = [
                'rejection_reason_id' => "ADD COLUMN `rejection_reason_id` int(11) DEFAULT NULL",
                'recollection_requested' => "ADD COLUMN `recollection_requested` tinyint(1) NOT NULL DEFAULT 0",
                'recollection_id' => "ADD COLUMN `recollection_id` bigint(11) DEFAULT NULL"
            ];
            foreach ($cols as $col => $sql) {
                if (!$this->column_exists('lab_sample_tracking', $col)) {
                    $this->safe_alter("ALTER TABLE `lab_sample_tracking` {$sql}");
                }
            }
        }

        $this->seed_rejection_reasons();
        return true;
    }

    private function seed_rejection_reasons()
    {
        if (!$this->table_exists('lab_sample_rejection_reasons')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('lab_sample_rejection_reasons');
        if ($count > 0) return;

        $reasons = [
            ['REJ-HEM', 'Hemolyzed Sample', 'QUALITY', 1, 'HIGH', 'Sample shows signs of hemolysis'],
            ['REJ-CLOT', 'Clotted Sample', 'QUALITY', 1, 'HIGH', 'Sample has clotted inappropriately'],
            ['REJ-LIP', 'Lipemic Sample', 'QUALITY', 1, 'MEDIUM', 'Sample is lipemic'],
            ['REJ-ICTERIC', 'Icteric Sample', 'QUALITY', 0, 'LOW', 'Sample shows icterus - may affect some tests'],
            ['REJ-QNS', 'Quantity Not Sufficient', 'VOLUME', 1, 'HIGH', 'Insufficient sample volume'],
            ['REJ-WRONG-TUBE', 'Wrong Collection Tube', 'COLLECTION', 1, 'HIGH', 'Sample collected in incorrect tube type'],
            ['REJ-UNLABELED', 'Unlabeled/Mislabeled', 'LABELING', 1, 'HIGH', 'Sample lacks proper identification'],
            ['REJ-LABEL-MISMATCH', 'Label Mismatch', 'LABELING', 1, 'HIGH', 'Label does not match requisition'],
            ['REJ-CONTAMINATED', 'Contaminated Sample', 'CONTAMINATION', 1, 'HIGH', 'Sample shows signs of contamination'],
            ['REJ-TEMP-BREACH', 'Temperature Breach', 'TRANSPORT', 1, 'HIGH', 'Sample temperature exceeded acceptable range'],
            ['REJ-EXPIRED', 'Sample Expired', 'TRANSPORT', 1, 'HIGH', 'Sample exceeded stability time'],
            ['REJ-LEAKED', 'Leaked/Broken Container', 'TRANSPORT', 1, 'HIGH', 'Sample container compromised'],
            ['REJ-DELAY', 'Excessive Transport Delay', 'TRANSPORT', 1, 'MEDIUM', 'Sample transport time exceeded limits'],
            ['REJ-OTHER', 'Other Reason', 'OTHER', 1, 'MEDIUM', 'Other rejection reason - specify in notes']
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($reasons as $r) {
            $this->db->insert('lab_sample_rejection_reasons', [
                'reason_code' => $r[0], 'reason_name' => $r[1], 'reason_category' => $r[2],
                'requires_recollection' => $r[3], 'severity' => $r[4], 'description' => $r[5],
                'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function log_sample_movement($sample_id, $movement_type, $from_location, $to_location, $from_status = null, $to_status = null, $notes = null)
    {
        $this->ensure_sample_movement_schema();
        
        $sample = $this->db->get_where('lab_sample_tracking', ['sample_id' => (int)$sample_id, 'InActive' => 0])->row();
        if (!$sample) return false;

        $now = date('Y-m-d H:i:s');
        $this->db->insert('lab_sample_movement_audit', [
            'sample_id' => (int)$sample_id,
            'sample_barcode' => $sample->sample_barcode,
            'io_lab_id' => $sample->io_lab_id,
            'patient_no' => $sample->patient_no,
            'movement_type' => $movement_type,
            'from_location' => $from_location,
            'to_location' => $to_location,
            'from_status' => $from_status,
            'to_status' => $to_status,
            'performed_by' => $this->session->userdata('user_id'),
            'performed_at' => $now,
            'notes' => $notes,
            'created_at' => $now,
            'InActive' => 0
        ]);

        return $this->db->insert_id();
    }

    public function reject_sample_with_recollection($sample_id, $reason_id, $notes = null, $priority = 'ROUTINE')
    {
        $this->ensure_sample_movement_schema();
        
        $sample = $this->db->get_where('lab_sample_tracking', ['sample_id' => (int)$sample_id, 'InActive' => 0])->row();
        if (!$sample) return ['ok' => false, 'error' => 'Sample not found'];

        $reason = $this->db->get_where('lab_sample_rejection_reasons', ['reason_id' => (int)$reason_id, 'InActive' => 0])->row();
        if (!$reason) return ['ok' => false, 'error' => 'Invalid rejection reason'];

        $now = date('Y-m-d H:i:s');
        $user_id = $this->session->userdata('user_id');

        // Update sample status to rejected
        $this->db->where('sample_id', (int)$sample_id)->update('lab_sample_tracking', [
            'sample_status' => 'REJECTED',
            'rejected_by' => $user_id,
            'rejected_at' => $now,
            'rejection_reason' => $reason->reason_name . ($notes ? ": $notes" : ''),
            'rejection_reason_id' => $reason_id
        ]);

        $recollection_id = null;
        
        // Create recollection request if required
        if ($reason->requires_recollection) {
            $this->db->insert('lab_recollection_requests', [
                'original_sample_id' => (int)$sample_id,
                'original_io_lab_id' => $sample->io_lab_id,
                'patient_no' => $sample->patient_no,
                'iop_id' => $sample->iop_id,
                'test_name' => $sample->test_name,
                'rejection_reason_id' => $reason_id,
                'rejection_reason_text' => $reason->reason_name,
                'priority' => $priority,
                'status' => 'PENDING',
                'requested_by' => $user_id,
                'requested_at' => $now,
                'notes' => $notes,
                'created_at' => $now,
                'InActive' => 0
            ]);
            $recollection_id = $this->db->insert_id();

            $this->db->where('sample_id', (int)$sample_id)->update('lab_sample_tracking', [
                'recollection_requested' => 1,
                'recollection_id' => $recollection_id
            ]);
        }

        // Log movement
        $this->log_sample_movement($sample_id, 'STATUS_CHANGE', null, null, $sample->sample_status, 'REJECTED', $reason->reason_name);
        
        $this->log_diagnostic_audit('SAMPLE_REJECTED', 'lab_sample_tracking', $sample_id, $sample->io_lab_id, 
            $sample->patient_no, null, $reason->reason_name);

        return [
            'ok' => true, 
            'recollection_required' => $reason->requires_recollection,
            'recollection_id' => $recollection_id
        ];
    }

    public function get_pending_recollections($limit = 50)
    {
        if (!$this->table_exists('lab_recollection_requests')) return [];
        
        return $this->db->select('r.*, p.firstname, p.lastname, p.phone')
            ->from('lab_recollection_requests r')
            ->join('patient_personal_info p', 'p.patient_no = r.patient_no', 'left')
            ->where(['r.status' => 'PENDING', 'r.InActive' => 0])
            ->order_by('r.priority', 'DESC')
            ->order_by('r.requested_at', 'ASC')
            ->limit($limit)
            ->get()->result();
    }

    public function update_recollection_status($recollection_id, $status, $notes = null)
    {
        if (!$this->table_exists('lab_recollection_requests')) return false;
        
        $now = date('Y-m-d H:i:s');
        $user_id = $this->session->userdata('user_id');
        $upd = ['status' => $status];

        if ($status === 'NOTIFIED') {
            $upd['notified_at'] = $now;
            $upd['notification_method'] = 'SYSTEM';
        } elseif ($status === 'SCHEDULED') {
            $upd['scheduled_at'] = $this->input->post('scheduled_at') ?: $now;
        } elseif ($status === 'COLLECTED') {
            $upd['collected_at'] = $now;
        } elseif ($status === 'CANCELLED') {
            $upd['cancelled_by'] = $user_id;
            $upd['cancelled_at'] = $now;
            $upd['cancellation_reason'] = $notes;
        }

        $this->db->where('recollection_id', (int)$recollection_id)->update('lab_recollection_requests', $upd);
        return true;
    }

    public function get_rejection_reasons($category = null)
    {
        if (!$this->table_exists('lab_sample_rejection_reasons')) return [];
        $this->db->where(['is_active' => 1, 'InActive' => 0]);
        if ($category) $this->db->where('reason_category', $category);
        return $this->db->order_by('reason_name', 'ASC')->get('lab_sample_rejection_reasons')->result();
    }

    public function get_sample_movement_history($sample_id, $limit = 100)
    {
        if (!$this->table_exists('lab_sample_movement_audit')) return [];
        return $this->db->where(['sample_id' => (int)$sample_id, 'InActive' => 0])
            ->order_by('performed_at', 'DESC')
            ->limit($limit)
            ->get('lab_sample_movement_audit')->result();
    }

    /* ================================================================== */
    /*  PHASE 5: DELTA CHECK INTELLIGENCE ENHANCEMENTS                     */
    /* ================================================================== */

    public function ensure_delta_check_enhanced_schema()
    {
        // Test-specific delta thresholds
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_delta_thresholds` (
            `threshold_id` int(11) NOT NULL AUTO_INCREMENT,
            `test_id` int(11) DEFAULT NULL,
            `test_name` varchar(255) NOT NULL,
            `test_code` varchar(50) DEFAULT NULL,
            `delta_percent_warning` decimal(10,2) NOT NULL DEFAULT 30.00,
            `delta_percent_critical` decimal(10,2) NOT NULL DEFAULT 50.00,
            `delta_absolute_warning` decimal(18,4) DEFAULT NULL,
            `delta_absolute_critical` decimal(18,4) DEFAULT NULL,
            `time_window_hours` int(11) NOT NULL DEFAULT 72,
            `unit` varchar(50) DEFAULT NULL,
            `clinical_significance` text DEFAULT NULL,
            `auto_notify_doctor` tinyint(1) NOT NULL DEFAULT 1,
            `requires_review` tinyint(1) NOT NULL DEFAULT 1,
            `is_active` tinyint(1) NOT NULL DEFAULT 1,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`threshold_id`),
            KEY `idx_test` (`test_id`),
            KEY `idx_code` (`test_code`),
            KEY `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Delta check clinical overrides
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_delta_overrides` (
            `override_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `delta_id` bigint(11) NOT NULL,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `override_type` enum('ACCEPT','CLINICAL_EXPECTED','TRANSFUSION','DIALYSIS','MEDICATION','OTHER') NOT NULL,
            `clinical_reason` text NOT NULL,
            `supporting_diagnosis` varchar(255) DEFAULT NULL,
            `overridden_by` varchar(25) NOT NULL,
            `overridden_at` datetime NOT NULL,
            `supervisor_approved` tinyint(1) NOT NULL DEFAULT 0,
            `supervisor_id` varchar(25) DEFAULT NULL,
            `supervisor_approved_at` datetime DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`override_id`),
            KEY `idx_delta` (`delta_id`),
            KEY `idx_lab` (`io_lab_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Delta check notifications
        $this->db->query("CREATE TABLE IF NOT EXISTS `lab_delta_notifications` (
            `notification_id` bigint(11) NOT NULL AUTO_INCREMENT,
            `delta_id` bigint(11) NOT NULL,
            `io_lab_id` int(11) NOT NULL,
            `patient_no` varchar(25) NOT NULL,
            `doctor_id` varchar(25) NOT NULL,
            `notification_type` enum('SYSTEM','SMS','EMAIL') NOT NULL DEFAULT 'SYSTEM',
            `message` text NOT NULL,
            `sent_at` datetime NOT NULL,
            `read_at` datetime DEFAULT NULL,
            `acknowledged_at` datetime DEFAULT NULL,
            `acknowledgment_notes` text DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`notification_id`),
            KEY `idx_delta` (`delta_id`),
            KEY `idx_doctor` (`doctor_id`),
            KEY `idx_read` (`read_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Add enhanced columns to delta checks
        if ($this->table_exists('lab_delta_checks')) {
            $cols = [
                'delta_severity' => "ADD COLUMN `delta_severity` enum('NORMAL','WARNING','CRITICAL') DEFAULT 'NORMAL'",
                'threshold_id' => "ADD COLUMN `threshold_id` int(11) DEFAULT NULL",
                'override_id' => "ADD COLUMN `override_id` bigint(11) DEFAULT NULL",
                'doctor_notified' => "ADD COLUMN `doctor_notified` tinyint(1) NOT NULL DEFAULT 0",
                'notification_id' => "ADD COLUMN `notification_id` bigint(11) DEFAULT NULL",
                'ordering_doctor_id' => "ADD COLUMN `ordering_doctor_id` varchar(25) DEFAULT NULL"
            ];
            foreach ($cols as $col => $sql) {
                if (!$this->column_exists('lab_delta_checks', $col)) {
                    $this->safe_alter("ALTER TABLE `lab_delta_checks` {$sql}");
                }
            }
        }

        $this->seed_delta_thresholds();
        return true;
    }

    private function seed_delta_thresholds()
    {
        if (!$this->table_exists('lab_delta_thresholds')) return;
        $count = $this->db->where('InActive', 0)->count_all_results('lab_delta_thresholds');
        if ($count > 0) return;

        $thresholds = [
            // Hematology
            ['Hemoglobin', 'HGB', 15, 25, 2.0, 3.0, 72, 'g/dL', 'Significant change may indicate bleeding, transfusion, or fluid shifts'],
            ['Hematocrit', 'HCT', 15, 25, null, null, 72, '%', 'Correlates with hemoglobin changes'],
            ['Platelet Count', 'PLT', 30, 50, 50, 100, 72, 'x10^9/L', 'Rapid changes may indicate consumption or production issues'],
            ['WBC Count', 'WBC', 30, 50, 5, 10, 72, 'x10^9/L', 'Significant changes may indicate infection or treatment response'],
            
            // Chemistry
            ['Potassium', 'K', 15, 25, 0.5, 1.0, 24, 'mmol/L', 'Critical electrolyte - rapid changes require immediate attention'],
            ['Sodium', 'NA', 5, 10, 5, 10, 24, 'mmol/L', 'Rapid changes may indicate fluid/electrolyte disorders'],
            ['Creatinine', 'CREAT', 25, 50, null, null, 48, 'mg/dL', 'Acute changes may indicate kidney injury'],
            ['BUN', 'BUN', 30, 50, null, null, 48, 'mg/dL', 'Changes correlate with renal function and hydration'],
            ['Glucose', 'GLU', 25, 40, 50, 100, 24, 'mg/dL', 'Significant variation in diabetic patients expected'],
            ['Calcium', 'CA', 10, 20, 1.0, 2.0, 48, 'mg/dL', 'Rapid changes may indicate parathyroid or malignancy issues'],
            
            // Cardiac
            ['Troponin I', 'TROP', 50, 100, null, null, 24, 'ng/mL', 'Rising pattern significant for MI diagnosis'],
            ['BNP', 'BNP', 30, 50, null, null, 48, 'pg/mL', 'Changes correlate with heart failure status'],
            
            // Coagulation
            ['INR', 'INR', 20, 30, 0.5, 1.0, 24, null, 'Significant for anticoagulation monitoring'],
            ['PTT', 'PTT', 25, 40, null, null, 24, 'seconds', 'Monitor for heparin therapy'],
            
            // Liver
            ['ALT', 'ALT', 50, 100, null, null, 72, 'U/L', 'Rapid rise may indicate acute liver injury'],
            ['AST', 'AST', 50, 100, null, null, 72, 'U/L', 'Consider cardiac vs hepatic source'],
            ['Bilirubin Total', 'TBIL', 30, 50, null, null, 48, 'mg/dL', 'Rising trend significant for liver function']
        ];

        $now = date('Y-m-d H:i:s');
        foreach ($thresholds as $t) {
            $this->db->insert('lab_delta_thresholds', [
                'test_name' => $t[0], 'test_code' => $t[1],
                'delta_percent_warning' => $t[2], 'delta_percent_critical' => $t[3],
                'delta_absolute_warning' => $t[4], 'delta_absolute_critical' => $t[5],
                'time_window_hours' => $t[6], 'unit' => $t[7],
                'clinical_significance' => $t[8],
                'auto_notify_doctor' => 1, 'requires_review' => 1,
                'is_active' => 1, 'created_at' => $now, 'InActive' => 0
            ]);
        }
    }

    public function perform_enhanced_delta_check($io_lab_id, $patient_no, $test_id, $test_name, $current_value, $ordering_doctor_id = null)
    {
        $this->ensure_delta_check_enhanced_schema();
        $current_num = $this->extract_numeric($current_value);
        if ($current_num === null) return ['flagged' => false, 'severity' => 'NORMAL'];

        // Get test-specific threshold
        $threshold = $this->db->where(['test_name' => $test_name, 'is_active' => 1, 'InActive' => 0])
            ->or_where(['test_id' => $test_id, 'is_active' => 1, 'InActive' => 0])
            ->get('lab_delta_thresholds')->row();

        $time_window = $threshold ? $threshold->time_window_hours : 72;
        $pct_warning = $threshold ? $threshold->delta_percent_warning : 30;
        $pct_critical = $threshold ? $threshold->delta_percent_critical : 50;
        $abs_warning = $threshold ? $threshold->delta_absolute_warning : null;
        $abs_critical = $threshold ? $threshold->delta_absolute_critical : null;

        // Find previous result within time window
        $this->db->select('io_lab_id, result, dDate')->from('iop_laboratory');
        $this->db->join('patient_details_iop pd', 'pd.IO_ID = iop_laboratory.iop_id', 'left');
        $this->db->where(['pd.patient_no' => $patient_no, 'laboratory_id' => $test_id, 'iop_laboratory.InActive' => 0]);
        $this->db->where('io_lab_id !=', $io_lab_id)->where("result IS NOT NULL")->where("result != ''");
        $this->db->where("dDate >= DATE_SUB(NOW(), INTERVAL {$time_window} HOUR)")->order_by('dDate', 'DESC')->limit(1);
        $prev = $this->db->get()->row();

        if (!$prev) return ['flagged' => false, 'severity' => 'NORMAL'];
        $prev_num = $this->extract_numeric($prev->result);
        if (!$prev_num) return ['flagged' => false, 'severity' => 'NORMAL'];

        // Calculate deltas
        $delta_pct = abs(($current_num - $prev_num) / $prev_num * 100);
        $delta_abs = abs($current_num - $prev_num);

        // Determine severity
        $severity = 'NORMAL';
        $flagged = false;
        $flag_reason = null;

        if ($delta_pct >= $pct_critical || ($abs_critical && $delta_abs >= $abs_critical)) {
            $severity = 'CRITICAL';
            $flagged = true;
            $flag_reason = "CRITICAL: Delta {$delta_pct}% exceeds {$pct_critical}%";
        } elseif ($delta_pct >= $pct_warning || ($abs_warning && $delta_abs >= $abs_warning)) {
            $severity = 'WARNING';
            $flagged = true;
            $flag_reason = "WARNING: Delta {$delta_pct}% exceeds {$pct_warning}%";
        }

        $now = date('Y-m-d H:i:s');
        $this->db->insert('lab_delta_checks', [
            'io_lab_id' => (int)$io_lab_id, 'patient_no' => $patient_no, 'test_id' => $test_id,
            'test_name' => $test_name, 'previous_value' => $prev->result, 'previous_numeric' => $prev_num,
            'previous_date' => $prev->dDate, 'current_value' => $current_value, 'current_numeric' => $current_num,
            'delta_percent' => $delta_pct, 'flagged' => $flagged ? 1 : 0, 'flag_reason' => $flag_reason,
            'delta_severity' => $severity, 'threshold_id' => $threshold ? $threshold->threshold_id : null,
            'ordering_doctor_id' => $ordering_doctor_id,
            'created_at' => $now, 'InActive' => 0
        ]);
        $delta_id = $this->db->insert_id();

        // Auto-notify doctor if flagged and threshold requires it
        $notification_id = null;
        if ($flagged && $ordering_doctor_id && $threshold && $threshold->auto_notify_doctor) {
            $notification_id = $this->create_delta_notification($delta_id, $io_lab_id, $patient_no, 
                $ordering_doctor_id, $test_name, $current_value, $prev->result, $delta_pct, $severity);
            
            $this->db->where('delta_id', $delta_id)->update('lab_delta_checks', [
                'doctor_notified' => 1, 'notification_id' => $notification_id
            ]);
        }

        return [
            'flagged' => $flagged,
            'severity' => $severity,
            'delta_percent' => round($delta_pct, 2),
            'delta_absolute' => round($delta_abs, 4),
            'previous' => $prev->result,
            'delta_id' => $delta_id,
            'notification_id' => $notification_id,
            'clinical_significance' => $threshold ? $threshold->clinical_significance : null
        ];
    }

    private function create_delta_notification($delta_id, $io_lab_id, $patient_no, $doctor_id, $test_name, $current, $previous, $delta_pct, $severity)
    {
        $now = date('Y-m-d H:i:s');
        $message = "DELTA CHECK {$severity}: {$test_name} changed from {$previous} to {$current} ({$delta_pct}% change). Patient: {$patient_no}. Please review.";

        $this->db->insert('lab_delta_notifications', [
            'delta_id' => $delta_id,
            'io_lab_id' => $io_lab_id,
            'patient_no' => $patient_no,
            'doctor_id' => $doctor_id,
            'notification_type' => 'SYSTEM',
            'message' => $message,
            'sent_at' => $now,
            'created_at' => $now,
            'InActive' => 0
        ]);

        return $this->db->insert_id();
    }

    public function override_delta_check($delta_id, $override_type, $clinical_reason, $diagnosis = null)
    {
        $this->ensure_delta_check_enhanced_schema();
        
        $delta = $this->db->get_where('lab_delta_checks', ['delta_id' => (int)$delta_id, 'InActive' => 0])->row();
        if (!$delta) return ['ok' => false, 'error' => 'Delta check not found'];

        $now = date('Y-m-d H:i:s');
        $user_id = $this->session->userdata('user_id');

        $this->db->insert('lab_delta_overrides', [
            'delta_id' => $delta_id,
            'io_lab_id' => $delta->io_lab_id,
            'patient_no' => $delta->patient_no,
            'override_type' => $override_type,
            'clinical_reason' => $clinical_reason,
            'supporting_diagnosis' => $diagnosis,
            'overridden_by' => $user_id,
            'overridden_at' => $now,
            'supervisor_approved' => 0,
            'created_at' => $now,
            'InActive' => 0
        ]);
        $override_id = $this->db->insert_id();

        $this->db->where('delta_id', $delta_id)->update('lab_delta_checks', [
            'override_id' => $override_id,
            'reviewed_by' => $user_id,
            'reviewed_at' => $now,
            'review_action' => 'ACCEPTED'
        ]);

        $this->log_diagnostic_audit('DELTA_OVERRIDE', 'lab_delta_overrides', $override_id, $delta->io_lab_id, 
            $delta->patient_no, null, "{$override_type}: {$clinical_reason}");

        return ['ok' => true, 'override_id' => $override_id];
    }

    public function get_pending_delta_reviews($limit = 50)
    {
        if (!$this->table_exists('lab_delta_checks')) return [];
        
        return $this->db->select('d.*, p.firstname, p.lastname, t.clinical_significance')
            ->from('lab_delta_checks d')
            ->join('patient_personal_info p', 'p.patient_no = d.patient_no', 'left')
            ->join('lab_delta_thresholds t', 't.threshold_id = d.threshold_id', 'left')
            ->where(['d.flagged' => 1, 'd.reviewed_at' => null, 'd.InActive' => 0])
            ->order_by('d.delta_severity', 'DESC')
            ->order_by('d.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    public function get_doctor_delta_notifications($doctor_id, $unread_only = true, $limit = 50)
    {
        if (!$this->table_exists('lab_delta_notifications')) return [];
        
        $this->db->select('n.*, d.test_name, d.current_value, d.previous_value, d.delta_percent, d.delta_severity, p.firstname, p.lastname')
            ->from('lab_delta_notifications n')
            ->join('lab_delta_checks d', 'd.delta_id = n.delta_id', 'left')
            ->join('patient_personal_info p', 'p.patient_no = n.patient_no', 'left')
            ->where(['n.doctor_id' => $doctor_id, 'n.InActive' => 0]);
        
        if ($unread_only) $this->db->where('n.read_at', null);
        
        return $this->db->order_by('n.sent_at', 'DESC')->limit($limit)->get()->result();
    }

    public function acknowledge_delta_notification($notification_id, $notes = null)
    {
        if (!$this->table_exists('lab_delta_notifications')) return false;

        $notification_id = (int)$notification_id;
        if ($notification_id <= 0) {
            return false;
        }

        $row = $this->db->get_where('lab_delta_notifications', [
            'notification_id' => $notification_id,
            'InActive' => 0
        ])->row();

        if (!$row) {
            return false;
        }

        $me = (string)$this->session->userdata('user_id');
        $is_admin = false;
        if (function_exists('has_role')) {
            $is_admin = (bool)has_role('admin');
        }

        if (!$is_admin) {
            $owner = isset($row->doctor_id) ? (string)$row->doctor_id : '';
            if ($owner === '' || $owner !== $me) {
                return false;
            }
        }
        
        $now = date('Y-m-d H:i:s');
        $this->db->where('notification_id', $notification_id)->update('lab_delta_notifications', [
            'read_at' => $now,
            'acknowledged_at' => $now,
            'acknowledgment_notes' => $notes
        ]);
        return true;
    }

    public function get_delta_thresholds($active_only = true)
    {
        if (!$this->table_exists('lab_delta_thresholds')) return [];
        $this->db->where('InActive', 0);
        if ($active_only) $this->db->where('is_active', 1);
        return $this->db->order_by('test_name', 'ASC')->get('lab_delta_thresholds')->result();
    }

    /* ================================================================== */
    /*  PHASE 5 MASTER INITIALIZATION                                      */
    /* ================================================================== */

    public function ensure_phase5_schemas()
    {
        $this->ensure_chain_of_custody_schema();
        $this->ensure_sample_movement_schema();
        $this->ensure_delta_check_enhanced_schema();
        return true;
    }
}
