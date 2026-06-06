<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Order_master_model extends CI_Model
{
	protected $table = 'order_master';
	protected static $cache_by_visit = array();
	protected static $cache_by_module_source = array();

	public function __construct()
	{
		parent::__construct();
		$this->load->database();
		$this->load->helper('schema_guard');
		if (!schema_already_run('order_master_schema')) {
			$this->ensure_table();
			mark_schema_run('order_master_schema');
		}
	}

	public function ensure_table()
	{
		if (!$this->db->table_exists($this->table)) {
			$this->db->query("CREATE TABLE IF NOT EXISTS `order_master` (
				`id` INT AUTO_INCREMENT PRIMARY KEY,
				`patient_no` VARCHAR(25) NOT NULL DEFAULT '',
				`visit_id` VARCHAR(25) NOT NULL DEFAULT '',
				`status` VARCHAR(30) DEFAULT 'REQUESTED',
				`clinical_status` VARCHAR(30) DEFAULT 'REQUESTED',
				`financial_status` VARCHAR(30) DEFAULT 'PENDING',
				`payment_status` VARCHAR(30) DEFAULT 'UNPAID',
				`source_module` VARCHAR(20) DEFAULT 'LAB',
				`last_sync_at` DATETIME DEFAULT NULL,
				`event_version` DECIMAL(20,6) DEFAULT NULL,
				`module` VARCHAR(50) NOT NULL DEFAULT 'LAB',
				`source_id` INT DEFAULT NULL,
				`item_id` INT DEFAULT NULL,
				`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME DEFAULT NULL,
				`InActive` INT(1) DEFAULT 0,
				UNIQUE KEY `uniq_module_source` (`module`,`source_id`),
				KEY `idx_patient_no` (`patient_no`),
				KEY `idx_visit_id` (`visit_id`),
				KEY `idx_status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		}

		$cols = $this->db->list_fields($this->table);
		$has = function($c) use ($cols) { return in_array($c, $cols, true); };

		if (!$has('clinical_status')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `clinical_status` VARCHAR(30) DEFAULT 'REQUESTED' AFTER `status`");
		}
		if (!$has('financial_status')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `financial_status` VARCHAR(30) DEFAULT 'PENDING' AFTER `clinical_status`");
		}
		if (!$has('payment_status')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `payment_status` VARCHAR(30) DEFAULT 'UNPAID' AFTER `financial_status`");
		}
		if (!$has('source_module')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `source_module` VARCHAR(20) DEFAULT 'LAB' AFTER `payment_status`");
		}
		if (!$has('last_sync_at')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `last_sync_at` DATETIME DEFAULT NULL AFTER `source_module`");
		}
		if (!$has('event_version')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `event_version` DECIMAL(20,6) DEFAULT NULL AFTER `last_sync_at`");
		}
		if (!$has('module')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `module` VARCHAR(50) NOT NULL DEFAULT 'LAB' AFTER `event_version`");
		}
		if (!$has('source_id')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `source_id` INT DEFAULT NULL AFTER `module`");
		}
		if (!$has('item_id')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `item_id` INT DEFAULT NULL AFTER `source_id`");
		}
		if (!$has('InActive')) {
			$this->db->query("ALTER TABLE `order_master` ADD COLUMN `InActive` INT(1) DEFAULT 0");
		}
	}

	public function upsert($data)
	{
		if (!is_array($data)) return false;

		$now = date('Y-m-d H:i:s');
		$data['last_sync_at'] = isset($data['last_sync_at']) ? $data['last_sync_at'] : $now;
		$data['updated_at'] = isset($data['updated_at']) ? $data['updated_at'] : $now;
		if (!isset($data['created_at'])) {
			$data['created_at'] = $now;
		}
		if (!isset($data['InActive'])) {
			$data['InActive'] = 0;
		}

		$patient_no = isset($data['patient_no']) ? (string)$data['patient_no'] : '';
		$visit_id = isset($data['visit_id']) ? (string)$data['visit_id'] : '';
		$module = isset($data['module']) ? (string)$data['module'] : (isset($data['source_module']) ? (string)$data['source_module'] : 'LAB');
		$source_id = isset($data['source_id']) ? (int)$data['source_id'] : 0;

		if ($source_id > 0) {
			$this->db->where('module', $module);
			$this->db->where('source_id', $source_id);
			$this->db->where('InActive', 0);
			$this->db->limit(1);
			$q = $this->db->get($this->table);
			if ($q && $q->num_rows() > 0) {
				unset(self::$cache_by_module_source[$module.'|'.$source_id]);
				unset(self::$cache_by_visit[$patient_no.'|'.$visit_id]);
				$this->db->where('module', $module);
				$this->db->where('source_id', $source_id);
				$this->db->where('InActive', 0);
				return $this->db->update($this->table, $data);
			}
			unset(self::$cache_by_module_source[$module.'|'.$source_id]);
			unset(self::$cache_by_visit[$patient_no.'|'.$visit_id]);
			return $this->db->insert($this->table, $data);
		}

		$this->db->where('patient_no', $patient_no);
		$this->db->where('visit_id', $visit_id);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$q = $this->db->get($this->table);

		if ($q && $q->num_rows() > 0) {
			unset(self::$cache_by_visit[$patient_no.'|'.$visit_id]);
			$this->db->where('patient_no', $patient_no);
			$this->db->where('visit_id', $visit_id);
			$this->db->where('InActive', 0);
			return $this->db->update($this->table, $data);
		}

		unset(self::$cache_by_visit[$patient_no.'|'.$visit_id]);
		return $this->db->insert($this->table, $data);
	}

	public function get($patient_no, $visit_id)
	{
		$key = (string)$patient_no.'|'.(string)$visit_id;
		if (isset(self::$cache_by_visit[$key])) {
			return self::$cache_by_visit[$key];
		}
		$row = $this->db
			->where('patient_no', (string)$patient_no)
			->where('visit_id', (string)$visit_id)
			->where('InActive', 0)
			->get($this->table)
			->row_array();
		self::$cache_by_visit[$key] = $row;
		return $row;
	}

	public function get_by_module_source($module, $source_id)
	{
		$module = strtoupper(trim((string)$module));
		$source_id = (int)$source_id;
		if ($module === '' || $source_id <= 0) return null;
		$key = $module.'|'.$source_id;
		if (isset(self::$cache_by_module_source[$key])) {
			return self::$cache_by_module_source[$key];
		}
		$row = $this->db
			->where('module', $module)
			->where('source_id', $source_id)
			->where('InActive', 0)
			->get($this->table)
			->row_array();
		self::$cache_by_module_source[$key] = $row;
		return $row;
	}
}
