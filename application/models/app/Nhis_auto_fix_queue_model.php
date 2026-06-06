<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_auto_fix_queue_model extends CI_Model
{
    private $table = 'nhis_auto_fix_queue';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    public function ensure_schema()
    {
        if (method_exists($this->db, 'table_exists') && $this->db->table_exists($this->table)) {
            return true;
        }

        $this->db->query("CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `action_hash` VARCHAR(64) NOT NULL,
            `action_key` VARCHAR(60) NOT NULL,
            `zone` VARCHAR(20) NOT NULL,
            `severity` VARCHAR(20) DEFAULT NULL,
            `confidence` DECIMAL(5,2) DEFAULT NULL,
            `status` ENUM('PENDING','APPROVED','REJECTED','EXECUTED') NOT NULL DEFAULT 'PENDING',
            `payload_json` MEDIUMTEXT NOT NULL,
            `audit_before_json` MEDIUMTEXT DEFAULT NULL,
            `audit_after_json` MEDIUMTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT NULL,
            `approved_by` VARCHAR(25) DEFAULT NULL,
            `approved_at` DATETIME DEFAULT NULL,
            `executed_at` DATETIME DEFAULT NULL,
            `notes` TEXT DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_action_hash` (`action_hash`),
            KEY `idx_status` (`status`),
            KEY `idx_action_key` (`action_key`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return true;
    }

    public function enqueue(array $action, $action_hash, $zone)
    {
        $this->ensure_schema();

        $action_hash = trim((string)$action_hash);
        if ($action_hash === '') {
            return array('success' => false, 'error' => 'INVALID_ACTION_HASH');
        }

        $existing = $this->db->select('id, status')
            ->from($this->table)
            ->where('action_hash', $action_hash)
            ->limit(1)
            ->get()->row();

        if ($existing) {
            return array(
                'success' => true,
                'queued' => false,
                'id' => (int)$existing->id,
                'status' => (string)$existing->status,
                'message' => 'Already queued'
            );
        }

        $payload = json_encode($action);
        $ok = $this->db->insert($this->table, array(
            'action_hash' => $action_hash,
            'action_key' => isset($action['action_key']) ? (string)$action['action_key'] : '',
            'zone' => (string)$zone,
            'severity' => isset($action['severity']) ? (string)$action['severity'] : null,
            'confidence' => isset($action['confidence']) ? (float)$action['confidence'] : null,
            'status' => 'PENDING',
            'payload_json' => $payload !== false ? $payload : '{}',
            'created_at' => date('Y-m-d H:i:s'),
        ));

        if (!$ok) {
            return array('success' => false, 'error' => 'QUEUE_INSERT_FAILED');
        }

        return array(
            'success' => true,
            'queued' => true,
            'id' => (int)$this->db->insert_id(),
            'status' => 'PENDING',
        );
    }

    public function mark_executed($id, array $before, array $after)
    {
        $this->ensure_schema();

        $id = (int)$id;
        if ($id <= 0) {
            return false;
        }

        return (bool)$this->db->where('id', $id)->update($this->table, array(
            'status' => 'EXECUTED',
            'audit_before_json' => json_encode($before),
            'audit_after_json' => json_encode($after),
            'updated_at' => date('Y-m-d H:i:s'),
            'executed_at' => date('Y-m-d H:i:s'),
        ));
    }

    public function is_executed($action_hash)
    {
        $this->ensure_schema();

        $action_hash = trim((string)$action_hash);
        if ($action_hash === '') {
            return false;
        }

        $row = $this->db->select('status')
            ->from($this->table)
            ->where('action_hash', $action_hash)
            ->limit(1)
            ->get()->row();

        return $row && isset($row->status) && (string)$row->status === 'EXECUTED';
    }

    public function get_by_hash($action_hash)
    {
        $this->ensure_schema();
        $action_hash = trim((string)$action_hash);
        if ($action_hash === '') {
            return null;
        }

        return $this->db->from($this->table)->where('action_hash', $action_hash)->limit(1)->get()->row();
    }
}
