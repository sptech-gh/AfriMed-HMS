<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Diagnostic Notifications Model
 * Phase 3 (Week 4) - Multi-Channel & Role-Based Notifications
 * 
 * Features:
 * - SMS, Email, Push notification channels
 * - Role-based notification routing
 * - Escalation rules and templates
 * - Notification preferences per user
 * - Delivery tracking and retry logic
 */
class Diagnostic_notifications_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    /* ================================================================== */
    /*  SCHEMA INSTALLATION                                               */
    /* ================================================================== */

    public function ensure_notifications_schema()
    {
        $this->ensure_notification_channels_table();
        $this->ensure_notification_templates_table();
        $this->ensure_notification_queue_table();
        $this->ensure_notification_log_table();
        $this->ensure_notification_preferences_table();
        $this->ensure_escalation_rules_table();
        $this->seed_default_templates();
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

    private function ensure_notification_channels_table()
    {
        if ($this->table_exists('diagnostic_notification_channels')) return;

        $this->db->query("
            CREATE TABLE diagnostic_notification_channels (
                channel_id INT AUTO_INCREMENT PRIMARY KEY,
                channel_code VARCHAR(30) NOT NULL UNIQUE,
                channel_name VARCHAR(100) NOT NULL,
                channel_type ENUM('SMS','EMAIL','PUSH','IN_APP','WEBHOOK') NOT NULL,
                is_enabled TINYINT(1) DEFAULT 1,
                config_json TEXT,
                priority_order INT DEFAULT 1,
                retry_attempts INT DEFAULT 3,
                retry_delay_minutes INT DEFAULT 5,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_channel_type (channel_type),
                INDEX idx_enabled (is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Seed default channels
        $channels = [
            ['SMS_PRIMARY', 'Primary SMS Gateway', 'SMS', 1, '{"provider":"hubtel","api_key":"","sender_id":"HMS"}', 1],
            ['EMAIL_SMTP', 'SMTP Email', 'EMAIL', 1, '{"host":"smtp.gmail.com","port":587,"username":"","password":"","from_name":"HMS Diagnostics"}', 2],
            ['PUSH_FCM', 'Firebase Cloud Messaging', 'PUSH', 0, '{"server_key":"","project_id":""}', 3],
            ['IN_APP', 'In-App Notifications', 'IN_APP', 1, '{}', 4],
            ['WEBHOOK_SLACK', 'Slack Webhook', 'WEBHOOK', 0, '{"webhook_url":""}', 5]
        ];

        foreach ($channels as $ch) {
            $this->db->insert('diagnostic_notification_channels', [
                'channel_code' => $ch[0],
                'channel_name' => $ch[1],
                'channel_type' => $ch[2],
                'is_enabled' => $ch[3],
                'config_json' => $ch[4],
                'priority_order' => $ch[5]
            ]);
        }
    }

    private function ensure_notification_templates_table()
    {
        if ($this->table_exists('diagnostic_notification_templates')) return;

        $this->db->query("
            CREATE TABLE diagnostic_notification_templates (
                template_id INT AUTO_INCREMENT PRIMARY KEY,
                template_code VARCHAR(50) NOT NULL UNIQUE,
                template_name VARCHAR(150) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                channel_type ENUM('SMS','EMAIL','PUSH','IN_APP','WEBHOOK','ALL') DEFAULT 'ALL',
                subject_template VARCHAR(255),
                body_template TEXT NOT NULL,
                variables_json TEXT,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_event_type (event_type),
                INDEX idx_channel (channel_type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_notification_queue_table()
    {
        if ($this->table_exists('diagnostic_notification_queue')) return;

        $this->db->query("
            CREATE TABLE diagnostic_notification_queue (
                queue_id INT AUTO_INCREMENT PRIMARY KEY,
                notification_type VARCHAR(50) NOT NULL,
                channel_type ENUM('SMS','EMAIL','PUSH','IN_APP','WEBHOOK') NOT NULL,
                recipient_user_id INT,
                recipient_role VARCHAR(30),
                recipient_address VARCHAR(255),
                subject VARCHAR(255),
                body TEXT NOT NULL,
                priority ENUM('LOW','NORMAL','HIGH','CRITICAL') DEFAULT 'NORMAL',
                status ENUM('PENDING','PROCESSING','SENT','FAILED','CANCELLED') DEFAULT 'PENDING',
                reference_type VARCHAR(50),
                reference_id INT,
                patient_no VARCHAR(25),
                scheduled_at DATETIME DEFAULT NULL,
                attempts INT DEFAULT 0,
                max_attempts INT DEFAULT 3,
                last_attempt_at DATETIME DEFAULT NULL,
                sent_at DATETIME DEFAULT NULL,
                error_message TEXT,
                metadata_json TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                created_by INT,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_status (status),
                INDEX idx_priority (priority),
                INDEX idx_scheduled (scheduled_at),
                INDEX idx_recipient (recipient_user_id),
                INDEX idx_reference (reference_type, reference_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_notification_log_table()
    {
        if ($this->table_exists('diagnostic_notification_log')) return;

        $this->db->query("
            CREATE TABLE diagnostic_notification_log (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                queue_id INT,
                channel_type ENUM('SMS','EMAIL','PUSH','IN_APP','WEBHOOK') NOT NULL,
                recipient_address VARCHAR(255),
                status ENUM('SENT','DELIVERED','READ','FAILED','BOUNCED') NOT NULL,
                provider_response TEXT,
                provider_message_id VARCHAR(100),
                delivery_time_ms INT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_queue (queue_id),
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_notification_preferences_table()
    {
        if ($this->table_exists('diagnostic_notification_preferences')) return;

        $this->db->query("
            CREATE TABLE diagnostic_notification_preferences (
                pref_id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                notification_type VARCHAR(50) NOT NULL,
                channel_sms TINYINT(1) DEFAULT 1,
                channel_email TINYINT(1) DEFAULT 1,
                channel_push TINYINT(1) DEFAULT 1,
                channel_in_app TINYINT(1) DEFAULT 1,
                quiet_hours_start TIME DEFAULT NULL,
                quiet_hours_end TIME DEFAULT NULL,
                is_enabled TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY uk_user_type (user_id, notification_type),
                INDEX idx_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    private function ensure_escalation_rules_table()
    {
        if ($this->table_exists('diagnostic_escalation_rules')) return;

        $this->db->query("
            CREATE TABLE diagnostic_escalation_rules (
                rule_id INT AUTO_INCREMENT PRIMARY KEY,
                rule_name VARCHAR(100) NOT NULL,
                event_type VARCHAR(50) NOT NULL,
                severity_level ENUM('INFO','WARNING','CRITICAL','EMERGENCY') DEFAULT 'WARNING',
                initial_notify_roles VARCHAR(255),
                escalation_1_minutes INT DEFAULT 15,
                escalation_1_roles VARCHAR(255),
                escalation_2_minutes INT DEFAULT 30,
                escalation_2_roles VARCHAR(255),
                escalation_3_minutes INT DEFAULT 60,
                escalation_3_roles VARCHAR(255),
                auto_resolve_minutes INT DEFAULT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT NULL,
                InActive TINYINT(1) DEFAULT 0,
                INDEX idx_event (event_type),
                INDEX idx_severity (severity_level)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");

        // Seed default escalation rules
        $rules = [
            ['Critical Lab Result Alert', 'CRITICAL_RESULT', 'CRITICAL', 'doctor,nurse', 5, 'doctor,lab_supervisor', 15, 'admin,medical_director', 30, 'admin'],
            ['STAT Test Overdue', 'STAT_OVERDUE', 'EMERGENCY', 'laboratory', 10, 'lab_supervisor', 20, 'admin,medical_director', 30, 'admin'],
            ['Sample Integrity Alert', 'SAMPLE_INTEGRITY', 'WARNING', 'laboratory', 15, 'lab_supervisor', 30, 'admin', 60, 'admin'],
            ['Delta Check Alert', 'DELTA_ALERT', 'WARNING', 'doctor,nurse', 10, 'doctor', 30, 'medical_director', 60, 'admin'],
            ['TAT Breach Warning', 'TAT_BREACH', 'WARNING', 'laboratory', 15, 'lab_supervisor', 30, 'admin', 60, 'admin']
        ];

        foreach ($rules as $r) {
            $this->db->insert('diagnostic_escalation_rules', [
                'rule_name' => $r[0],
                'event_type' => $r[1],
                'severity_level' => $r[2],
                'initial_notify_roles' => $r[3],
                'escalation_1_minutes' => $r[4],
                'escalation_1_roles' => $r[5],
                'escalation_2_minutes' => $r[6],
                'escalation_2_roles' => $r[7],
                'escalation_3_minutes' => $r[8],
                'escalation_3_roles' => $r[9]
            ]);
        }
    }

    private function seed_default_templates()
    {
        if ($this->db->where('InActive', 0)->count_all_results('diagnostic_notification_templates') > 0) return;

        $templates = [
            [
                'CRITICAL_RESULT_SMS', 'Critical Result SMS', 'CRITICAL_RESULT', 'SMS',
                null,
                'URGENT: Critical lab result for {patient_name}. Test: {test_name}, Value: {result_value} {unit}. Immediate action required. -HMS Lab',
                '["patient_name","test_name","result_value","unit","reference_range"]'
            ],
            [
                'CRITICAL_RESULT_EMAIL', 'Critical Result Email', 'CRITICAL_RESULT', 'EMAIL',
                'CRITICAL: Lab Result Requires Immediate Attention - {patient_name}',
                '<h2>Critical Laboratory Result</h2><p><strong>Patient:</strong> {patient_name} ({patient_no})</p><p><strong>Test:</strong> {test_name}</p><p><strong>Result:</strong> {result_value} {unit}</p><p><strong>Reference Range:</strong> {reference_range}</p><p><strong>Flagged At:</strong> {flagged_at}</p><p style="color:red;font-weight:bold;">This result requires immediate clinical attention.</p>',
                '["patient_name","patient_no","test_name","result_value","unit","reference_range","flagged_at"]'
            ],
            [
                'STAT_OVERDUE_SMS', 'STAT Test Overdue SMS', 'STAT_OVERDUE', 'SMS',
                null,
                'ALERT: STAT test overdue! Patient: {patient_name}, Test: {test_name}, Ordered: {ordered_at}, TAT: {tat_minutes}min exceeded. -HMS Lab',
                '["patient_name","test_name","ordered_at","tat_minutes"]'
            ],
            [
                'TAT_BREACH_EMAIL', 'TAT Breach Email', 'TAT_BREACH', 'EMAIL',
                'TAT Breach Alert - {test_name} for {patient_name}',
                '<h2>Turnaround Time Breach</h2><p><strong>Patient:</strong> {patient_name}</p><p><strong>Test:</strong> {test_name}</p><p><strong>Expected TAT:</strong> {expected_tat} minutes</p><p><strong>Actual Time:</strong> {actual_time} minutes</p><p><strong>Breach Amount:</strong> {breach_minutes} minutes over</p>',
                '["patient_name","test_name","expected_tat","actual_time","breach_minutes"]'
            ],
            [
                'DELTA_ALERT_SMS', 'Delta Check Alert SMS', 'DELTA_ALERT', 'SMS',
                null,
                'Delta Alert: {patient_name} - {test_name} changed {delta_percent}% ({previous_value} to {current_value}). Review required. -HMS',
                '["patient_name","test_name","delta_percent","previous_value","current_value"]'
            ],
            [
                'SAMPLE_INTEGRITY_SMS', 'Sample Integrity Alert SMS', 'SAMPLE_INTEGRITY', 'SMS',
                null,
                'Sample Alert: {sample_id} for {patient_name} flagged - {issue_type}. Action needed. -HMS Lab',
                '["sample_id","patient_name","issue_type"]'
            ],
            [
                'RESULT_READY_SMS', 'Result Ready SMS', 'RESULT_READY', 'SMS',
                null,
                'Lab results ready for {patient_name}. Test: {test_name}. Please review in HMS. -HMS Lab',
                '["patient_name","test_name"]'
            ],
            [
                'ESCALATION_SMS', 'Escalation Alert SMS', 'ESCALATION', 'SMS',
                null,
                'ESCALATION: {original_alert} unacknowledged for {minutes_elapsed}min. Patient: {patient_name}. Immediate action required. -HMS',
                '["original_alert","minutes_elapsed","patient_name"]'
            ]
        ];

        foreach ($templates as $t) {
            $this->db->insert('diagnostic_notification_templates', [
                'template_code' => $t[0],
                'template_name' => $t[1],
                'event_type' => $t[2],
                'channel_type' => $t[3],
                'subject_template' => $t[4],
                'body_template' => $t[5],
                'variables_json' => $t[6]
            ]);
        }
    }

    /* ================================================================== */
    /*  NOTIFICATION QUEUE MANAGEMENT                                     */
    /* ================================================================== */

    /**
     * Queue a notification for sending
     */
    public function queue_notification($data)
    {
        $insert = [
            'notification_type' => $data['notification_type'],
            'channel_type' => $data['channel_type'],
            'recipient_user_id' => $data['recipient_user_id'] ?? null,
            'recipient_role' => $data['recipient_role'] ?? null,
            'recipient_address' => $data['recipient_address'] ?? null,
            'subject' => $data['subject'] ?? null,
            'body' => $data['body'],
            'priority' => $data['priority'] ?? 'NORMAL',
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null,
            'patient_no' => $data['patient_no'] ?? null,
            'scheduled_at' => $data['scheduled_at'] ?? null,
            'max_attempts' => $data['max_attempts'] ?? 3,
            'metadata_json' => isset($data['metadata']) ? json_encode($data['metadata']) : null,
            'created_by' => $this->session->userdata('user_id')
        ];

        try {
            $ok = $this->db->insert('diagnostic_notification_queue', $insert);
            $id = (int)$this->db->insert_id();
            if (!$ok || $id <= 0) {
                log_message('error', '[NOTIFICATION_QUEUE_FAIL] type=' . $data['notification_type']
                    . ' channel=' . $data['channel_type']
                    . ' recipient=' . ($data['recipient_user_id'] ?? 'null')
                    . ' db_error=' . json_encode($this->db->error()));
                return false;
            }
            return $id;
        } catch (Exception $e) {
            log_message('error', '[NOTIFICATION_QUEUE_EXCEPTION] ' . $e->getMessage()
                . ' type=' . $data['notification_type']);
            return false;
        }
    }

    /**
     * Queue notifications to multiple recipients by role
     */
    public function queue_role_notification($event_type, $roles, $data, $priority = 'NORMAL')
    {
        $role_list = is_array($roles) ? $roles : explode(',', $roles);
        $queued = [];

        foreach ($role_list as $role) {
            $role = trim($role);
            $users = $this->get_users_by_role($role);

            foreach ($users as $user) {
                // Check user preferences
                if (!$this->should_notify_user($user->user_id, $event_type)) continue;

                $channels = $this->get_user_enabled_channels($user->user_id, $event_type);

                foreach ($channels as $channel) {
                    $address = $this->get_user_channel_address($user, $channel);
                    if (!$address) continue;

                    $template = $this->get_template($event_type, $channel);
                    if (!$template) continue;

                    $body = $this->render_template($template->body_template, $data);
                    $subject = $template->subject_template ? $this->render_template($template->subject_template, $data) : null;

                    $queue_id = $this->queue_notification([
                        'notification_type' => $event_type,
                        'channel_type' => $channel,
                        'recipient_user_id' => $user->user_id,
                        'recipient_role' => $role,
                        'recipient_address' => $address,
                        'subject' => $subject,
                        'body' => $body,
                        'priority' => $priority,
                        'reference_type' => $data['reference_type'] ?? null,
                        'reference_id' => $data['reference_id'] ?? null,
                        'patient_no' => $data['patient_no'] ?? null,
                        'metadata' => $data
                    ]);

                    if ($queue_id !== false && $queue_id > 0) {
                        $queued[] = $queue_id;
                    } else {
                        log_message('error', '[NOTIFICATION_QUEUE_PARTIAL_FAIL] role=' . $role
                            . ' user=' . $user->user_id . ' event=' . $event_type
                            . ' channel=' . $channel);
                    }
                }
            }
        }

        return $queued;
    }

    /**
     * Send a critical result notification
     */
    public function send_critical_result_notification($result_data)
    {
        $rule = $this->get_escalation_rule('CRITICAL_RESULT');
        if (!$rule) return false;

        $data = [
            'patient_name' => $result_data['patient_name'],
            'patient_no' => $result_data['patient_no'],
            'test_name' => $result_data['test_name'],
            'result_value' => $result_data['result_value'],
            'unit' => $result_data['unit'] ?? '',
            'reference_range' => $result_data['reference_range'] ?? 'N/A',
            'flagged_at' => date('Y-m-d H:i:s'),
            'reference_type' => 'lab_result',
            'reference_id' => $result_data['result_id'] ?? null
        ];

        return $this->queue_role_notification(
            'CRITICAL_RESULT',
            $rule->initial_notify_roles,
            $data,
            'CRITICAL'
        );
    }

    /**
     * Send STAT test overdue notification
     */
    public function send_stat_overdue_notification($test_data)
    {
        $rule = $this->get_escalation_rule('STAT_OVERDUE');
        if (!$rule) return false;

        $data = [
            'patient_name' => $test_data['patient_name'],
            'patient_no' => $test_data['patient_no'],
            'test_name' => $test_data['test_name'],
            'ordered_at' => $test_data['ordered_at'],
            'tat_minutes' => $test_data['tat_minutes'],
            'reference_type' => 'lab_request',
            'reference_id' => $test_data['io_lab_id'] ?? null
        ];

        return $this->queue_role_notification(
            'STAT_OVERDUE',
            $rule->initial_notify_roles,
            $data,
            'CRITICAL'
        );
    }

    /**
     * Send TAT breach notification
     */
    public function send_tat_breach_notification($breach_data)
    {
        $rule = $this->get_escalation_rule('TAT_BREACH');
        if (!$rule) return false;

        $data = [
            'patient_name' => $breach_data['patient_name'],
            'patient_no' => $breach_data['patient_no'],
            'test_name' => $breach_data['test_name'],
            'expected_tat' => $breach_data['expected_tat'],
            'actual_time' => $breach_data['actual_time'],
            'breach_minutes' => $breach_data['actual_time'] - $breach_data['expected_tat'],
            'reference_type' => 'lab_request',
            'reference_id' => $breach_data['io_lab_id'] ?? null
        ];

        return $this->queue_role_notification(
            'TAT_BREACH',
            $rule->initial_notify_roles,
            $data,
            'HIGH'
        );
    }

    /**
     * Send delta check alert notification
     */
    public function send_delta_alert_notification($delta_data)
    {
        $rule = $this->get_escalation_rule('DELTA_ALERT');
        if (!$rule) return false;

        $data = [
            'patient_name' => $delta_data['patient_name'],
            'patient_no' => $delta_data['patient_no'],
            'test_name' => $delta_data['test_name'],
            'delta_percent' => $delta_data['delta_percent'],
            'previous_value' => $delta_data['previous_value'],
            'current_value' => $delta_data['current_value'],
            'reference_type' => 'delta_check',
            'reference_id' => $delta_data['delta_id'] ?? null
        ];

        return $this->queue_role_notification(
            'DELTA_ALERT',
            $rule->initial_notify_roles,
            $data,
            'HIGH'
        );
    }

    /* ================================================================== */
    /*  NOTIFICATION PROCESSING                                           */
    /* ================================================================== */

    /**
     * Process pending notifications in queue
     */
    public function process_notification_queue($limit = 50)
    {
        $pending = $this->db->select('*')
            ->from('diagnostic_notification_queue')
            ->where('status', 'PENDING')
            ->where('InActive', 0)
            ->where('(scheduled_at IS NULL OR scheduled_at <= NOW())', null, false)
            ->where('attempts <', 'max_attempts', false)
            ->order_by('FIELD(priority, "CRITICAL", "HIGH", "NORMAL", "LOW")', '', false)
            ->order_by('created_at', 'ASC')
            ->limit($limit)
            ->get()->result();

        $results = ['sent' => 0, 'failed' => 0];

        foreach ($pending as $notification) {
            $this->db->where('queue_id', $notification->queue_id)
                ->update('diagnostic_notification_queue', [
                    'status' => 'PROCESSING',
                    'attempts' => $notification->attempts + 1,
                    'last_attempt_at' => date('Y-m-d H:i:s')
                ]);

            $sent = $this->send_notification($notification);

            if ($sent['success']) {
                $this->db->where('queue_id', $notification->queue_id)
                    ->update('diagnostic_notification_queue', [
                        'status' => 'SENT',
                        'sent_at' => date('Y-m-d H:i:s')
                    ]);
                $results['sent']++;
            } else {
                $new_status = ($notification->attempts + 1 >= $notification->max_attempts) ? 'FAILED' : 'PENDING';
                $this->db->where('queue_id', $notification->queue_id)
                    ->update('diagnostic_notification_queue', [
                        'status' => $new_status,
                        'error_message' => $sent['error']
                    ]);
                $results['failed']++;
            }

            // Log the attempt
            $this->db->insert('diagnostic_notification_log', [
                'queue_id' => $notification->queue_id,
                'channel_type' => $notification->channel_type,
                'recipient_address' => $notification->recipient_address,
                'status' => $sent['success'] ? 'SENT' : 'FAILED',
                'provider_response' => $sent['response'] ?? null,
                'provider_message_id' => $sent['message_id'] ?? null,
                'delivery_time_ms' => $sent['delivery_time'] ?? null
            ]);
        }

        return $results;
    }

    /**
     * Send a single notification via appropriate channel
     */
    private function send_notification($notification)
    {
        $start_time = microtime(true);

        switch ($notification->channel_type) {
            case 'SMS':
                $result = $this->send_sms($notification->recipient_address, $notification->body);
                break;
            case 'EMAIL':
                $result = $this->send_email($notification->recipient_address, $notification->subject, $notification->body);
                break;
            case 'PUSH':
                $result = $this->send_push($notification->recipient_user_id, $notification->subject, $notification->body);
                break;
            case 'IN_APP':
                $result = $this->create_in_app_notification($notification);
                break;
            case 'WEBHOOK':
                $result = $this->send_webhook($notification);
                break;
            default:
                $result = ['success' => false, 'error' => 'Unknown channel type'];
        }

        $result['delivery_time'] = round((microtime(true) - $start_time) * 1000);
        return $result;
    }

    /**
     * Send SMS via configured gateway
     */
    private function send_sms($phone, $message)
    {
        $channel = $this->get_channel_config('SMS_PRIMARY');
        if (!$channel || !$channel->is_enabled) {
            return ['success' => false, 'error' => 'SMS channel not configured or disabled'];
        }

        $config = json_decode($channel->config_json, true);

        // Placeholder for actual SMS gateway integration
        // In production, integrate with Hubtel, Twilio, etc.
        if (empty($config['api_key'])) {
            // Log but mark as success for demo/testing
            log_message('info', "SMS (simulated) to {$phone}: {$message}");
            return ['success' => true, 'response' => 'Simulated send', 'message_id' => 'SIM_' . uniqid()];
        }

        // Actual SMS sending would go here
        return ['success' => true, 'response' => 'Sent', 'message_id' => uniqid()];
    }

    /**
     * Send Email via SMTP
     */
    private function send_email($to, $subject, $body)
    {
        $channel = $this->get_channel_config('EMAIL_SMTP');
        if (!$channel || !$channel->is_enabled) {
            return ['success' => false, 'error' => 'Email channel not configured or disabled'];
        }

        $config = json_decode($channel->config_json, true);

        if (empty($config['username'])) {
            log_message('info', "Email (simulated) to {$to}: {$subject}");
            return ['success' => true, 'response' => 'Simulated send', 'message_id' => 'SIM_' . uniqid()];
        }

        // Load CI email library
        $this->load->library('email');

        $email_config = [
            'protocol' => 'smtp',
            'smtp_host' => $config['host'] ?? 'smtp.gmail.com',
            'smtp_port' => $config['port'] ?? 587,
            'smtp_user' => $config['username'],
            'smtp_pass' => $config['password'],
            'mailtype' => 'html',
            'charset' => 'utf-8'
        ];

        $this->email->initialize($email_config);
        $this->email->from($config['username'], getFacilityName());
        $this->email->to($to);
        $this->email->subject($subject);
        
        $wrapped_body = wrap_email_in_platform_template($subject, $body);
        $this->email->message($wrapped_body);

        if ($this->email->send()) {
            return ['success' => true, 'response' => 'Sent', 'message_id' => uniqid()];
        } else {
            return ['success' => false, 'error' => $this->email->print_debugger(['headers'])];
        }
    }

    /**
     * Send Push notification via FCM
     */
    private function send_push($user_id, $title, $body)
    {
        $channel = $this->get_channel_config('PUSH_FCM');
        if (!$channel || !$channel->is_enabled) {
            return ['success' => false, 'error' => 'Push channel not configured or disabled'];
        }

        // Get user's FCM token (would need a user_devices table)
        // Placeholder implementation
        log_message('info', "Push (simulated) to user {$user_id}: {$title}");
        return ['success' => true, 'response' => 'Simulated send', 'message_id' => 'SIM_' . uniqid()];
    }

    /**
     * Create in-app notification
     */
    private function create_in_app_notification($notification)
    {
        // Use existing lab_delta_notifications or create new table
        if ($this->table_exists('lab_delta_notifications')) {
            $this->db->insert('lab_delta_notifications', [
                'doctor_id' => $notification->recipient_user_id,
                'patient_no' => $notification->patient_no,
                'notification_type' => $notification->notification_type,
                'message' => $notification->body,
                'sent_at' => date('Y-m-d H:i:s')
            ]);
            return ['success' => true, 'response' => 'Created', 'message_id' => $this->db->insert_id()];
        }

        return ['success' => false, 'error' => 'In-app notification table not found'];
    }

    /**
     * Send webhook notification
     */
    private function send_webhook($notification)
    {
        $channel = $this->get_channel_config('WEBHOOK_SLACK');
        if (!$channel || !$channel->is_enabled) {
            return ['success' => false, 'error' => 'Webhook channel not configured or disabled'];
        }

        $config = json_decode($channel->config_json, true);
        if (empty($config['webhook_url'])) {
            return ['success' => false, 'error' => 'Webhook URL not configured'];
        }

        $payload = json_encode([
            'text' => $notification->body,
            'attachments' => [[
                'color' => $notification->priority === 'CRITICAL' ? 'danger' : 'warning',
                'title' => $notification->subject ?? $notification->notification_type,
                'text' => $notification->body,
                'ts' => time()
            ]]
        ]);

        $ch = curl_init($config['webhook_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'response' => $response, 'message_id' => uniqid()];
        }

        return ['success' => false, 'error' => "HTTP {$http_code}: {$response}"];
    }

    /* ================================================================== */
    /*  ESCALATION MANAGEMENT                                             */
    /* ================================================================== */

    /**
     * Check and process escalations for unacknowledged notifications
     */
    public function process_escalations()
    {
        $rules = $this->db->where(['is_active' => 1, 'InActive' => 0])
            ->get('diagnostic_escalation_rules')->result();

        $escalated = 0;

        foreach ($rules as $rule) {
            $escalated += $this->check_escalation_for_rule($rule);
        }

        return $escalated;
    }

    private function check_escalation_for_rule($rule)
    {
        // Find unacknowledged notifications for this event type
        $unacked = $this->db->query("
            SELECT q.*, 
                   TIMESTAMPDIFF(MINUTE, q.created_at, NOW()) as minutes_elapsed
            FROM diagnostic_notification_queue q
            WHERE q.notification_type = ?
              AND q.status = 'SENT'
              AND q.InActive = 0
              AND NOT EXISTS (
                  SELECT 1 FROM diagnostic_notification_log l 
                  WHERE l.queue_id = q.queue_id AND l.status = 'READ'
              )
        ", [$rule->event_type])->result();

        $escalated = 0;

        foreach ($unacked as $notif) {
            $minutes = $notif->minutes_elapsed;
            $escalation_level = 0;
            $escalation_roles = null;

            if ($minutes >= $rule->escalation_3_minutes && $rule->escalation_3_roles) {
                $escalation_level = 3;
                $escalation_roles = $rule->escalation_3_roles;
            } elseif ($minutes >= $rule->escalation_2_minutes && $rule->escalation_2_roles) {
                $escalation_level = 2;
                $escalation_roles = $rule->escalation_2_roles;
            } elseif ($minutes >= $rule->escalation_1_minutes && $rule->escalation_1_roles) {
                $escalation_level = 1;
                $escalation_roles = $rule->escalation_1_roles;
            }

            if ($escalation_level > 0 && $escalation_roles) {
                $metadata = json_decode($notif->metadata_json, true) ?? [];
                $metadata['escalation_level'] = $escalation_level;
                $metadata['original_alert'] = $notif->notification_type;
                $metadata['minutes_elapsed'] = $minutes;

                $this->queue_role_notification(
                    'ESCALATION',
                    $escalation_roles,
                    $metadata,
                    'CRITICAL'
                );

                $escalated++;
            }
        }

        return $escalated;
    }

    /* ================================================================== */
    /*  HELPER METHODS                                                    */
    /* ================================================================== */

    private function get_channel_config($channel_code)
    {
        return $this->db->where(['channel_code' => $channel_code, 'InActive' => 0])
            ->get('diagnostic_notification_channels')->row();
    }

    private function get_template($event_type, $channel_type)
    {
        return $this->db->where([
            'event_type' => $event_type,
            'is_active' => 1,
            'InActive' => 0
        ])->where_in('channel_type', [$channel_type, 'ALL'])
            ->order_by("channel_type = '{$channel_type}'", 'DESC')
            ->get('diagnostic_notification_templates')->row();
    }

    private function get_escalation_rule($event_type)
    {
        return $this->db->where(['event_type' => $event_type, 'is_active' => 1, 'InActive' => 0])
            ->get('diagnostic_escalation_rules')->row();
    }

    private function render_template($template, $data)
    {
        foreach ($data as $key => $value) {
            if (!is_array($value) && !is_object($value)) {
                $template = str_replace('{' . $key . '}', $value, $template);
            }
        }
        return $template;
    }

    private function get_users_by_role($role)
    {
        return $this->db->select('u.user_id, u.username, u.email, u.phone')
            ->from('user u')
            ->join('user_roles ur', 'ur.user_id = u.user_id', 'left')
            ->where('u.InActive', 0)
            ->group_start()
                ->where('u.role', $role)
                ->or_where('ur.role_key', $role)
            ->group_end()
            ->get()->result();
    }

    private function should_notify_user($user_id, $notification_type)
    {
        $pref = $this->db->where(['user_id' => $user_id, 'notification_type' => $notification_type])
            ->get('diagnostic_notification_preferences')->row();

        if (!$pref) return true; // Default to notify if no preference set

        if (!$pref->is_enabled) return false;

        // Check quiet hours
        if ($pref->quiet_hours_start && $pref->quiet_hours_end) {
            $now = date('H:i:s');
            if ($now >= $pref->quiet_hours_start && $now <= $pref->quiet_hours_end) {
                return false;
            }
        }

        return true;
    }

    private function get_user_enabled_channels($user_id, $notification_type)
    {
        $pref = $this->db->where(['user_id' => $user_id, 'notification_type' => $notification_type])
            ->get('diagnostic_notification_preferences')->row();

        $channels = [];

        if (!$pref) {
            // Default: all channels enabled
            return ['SMS', 'EMAIL', 'IN_APP'];
        }

        if ($pref->channel_sms) $channels[] = 'SMS';
        if ($pref->channel_email) $channels[] = 'EMAIL';
        if ($pref->channel_push) $channels[] = 'PUSH';
        if ($pref->channel_in_app) $channels[] = 'IN_APP';

        return $channels;
    }

    private function get_user_channel_address($user, $channel)
    {
        switch ($channel) {
            case 'SMS':
                return $user->phone ?? null;
            case 'EMAIL':
                return $user->email ?? null;
            case 'PUSH':
            case 'IN_APP':
                return $user->user_id;
            default:
                return null;
        }
    }

    /* ================================================================== */
    /*  REPORTING & STATISTICS                                            */
    /* ================================================================== */

    public function get_notification_stats($date_from = null, $date_to = null)
    {
        $date_from = $date_from ?: date('Y-m-d', strtotime('-30 days'));
        $date_to = $date_to ?: date('Y-m-d');

        return $this->db->query("
            SELECT 
                COUNT(*) as total,
                SUM(status = 'SENT') as sent,
                SUM(status = 'FAILED') as failed,
                SUM(status = 'PENDING') as pending,
                SUM(priority = 'CRITICAL') as critical,
                SUM(priority = 'HIGH') as high,
                channel_type,
                notification_type
            FROM diagnostic_notification_queue
            WHERE DATE(created_at) BETWEEN ? AND ?
              AND InActive = 0
            GROUP BY channel_type, notification_type
        ", [$date_from, $date_to])->result();
    }

    public function get_pending_notifications($limit = 50)
    {
        return $this->db->select('q.*, p.firstname, p.lastname')
            ->from('diagnostic_notification_queue q')
            ->join('patient_personal_info p', 'p.patient_no = q.patient_no', 'left')
            ->where(['q.status' => 'PENDING', 'q.InActive' => 0])
            ->order_by('FIELD(q.priority, "CRITICAL", "HIGH", "NORMAL", "LOW")', '', false)
            ->order_by('q.created_at', 'ASC')
            ->limit($limit)
            ->get()->result();
    }

    public function get_notification_log($filters = [], $limit = 100)
    {
        $this->db->select('l.*, q.notification_type, q.recipient_address, q.priority, q.patient_no')
            ->from('diagnostic_notification_log l')
            ->join('diagnostic_notification_queue q', 'q.queue_id = l.queue_id', 'left')
            ->order_by('l.created_at', 'DESC')
            ->limit($limit);

        if (!empty($filters['status'])) {
            $this->db->where('l.status', $filters['status']);
        }
        if (!empty($filters['channel_type'])) {
            $this->db->where('l.channel_type', $filters['channel_type']);
        }
        if (!empty($filters['date_from'])) {
            $this->db->where('DATE(l.created_at) >=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $this->db->where('DATE(l.created_at) <=', $filters['date_to']);
        }

        return $this->db->get()->result();
    }

    /**
     * Mark notification as read/acknowledged
     */
    public function acknowledge_notification($queue_id, $user_id = null)
    {
        $user_id = $user_id ?: $this->session->userdata('user_id');

        $this->db->insert('diagnostic_notification_log', [
            'queue_id' => $queue_id,
            'channel_type' => 'IN_APP',
            'status' => 'READ',
            'provider_response' => json_encode(['acknowledged_by' => $user_id])
        ]);

        return true;
    }

    /**
     * Get user's unread in-app notifications
     */
    public function get_user_notifications($user_id, $unread_only = true, $limit = 20)
    {
        $this->db->select('q.*, p.firstname, p.lastname')
            ->from('diagnostic_notification_queue q')
            ->join('patient_personal_info p', 'p.patient_no = q.patient_no', 'left')
            ->where(['q.recipient_user_id' => $user_id, 'q.channel_type' => 'IN_APP', 'q.InActive' => 0]);

        if ($unread_only) {
            $this->db->where('NOT EXISTS (
                SELECT 1 FROM diagnostic_notification_log l 
                WHERE l.queue_id = q.queue_id AND l.status = "READ"
            )', null, false);
        }

        return $this->db->order_by('q.created_at', 'DESC')
            ->limit($limit)
            ->get()->result();
    }

    public function count_unread_notifications($user_id)
    {
        return $this->db->query("
            SELECT COUNT(*) as cnt
            FROM diagnostic_notification_queue q
            WHERE q.recipient_user_id = ?
              AND q.channel_type = 'IN_APP'
              AND q.InActive = 0
              AND NOT EXISTS (
                  SELECT 1 FROM diagnostic_notification_log l 
                  WHERE l.queue_id = q.queue_id AND l.status = 'READ'
              )
        ", [$user_id])->row()->cnt ?? 0;
    }
}
