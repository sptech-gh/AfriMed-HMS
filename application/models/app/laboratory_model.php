<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Laboratory_model extends CI_Model
{
	private $utf8mb4_checked = false;
	private $utf8mb4_ready = false;

	private function db_safe_text($value){
		if ($value === null) {
			return null;
		}
		$s = (string)$value;
		if ($s === '') {
			return $s;
		}
		if ($this->is_utf8mb4_ready()) {
			return $s;
		}
		$clean = preg_replace('/[\x{10000}-\x{10FFFF}]/u', '', $s);
		if ($clean === null) {
			$clean = preg_replace('/[\xF0-\xF7][\x80-\xBF]{3}/', '', $s);
			if ($clean === null) {
				$clean = $s;
			}
		}
		return $clean;
	}

	private function is_utf8mb4_ready(){
		if ($this->utf8mb4_checked) {
			return $this->utf8mb4_ready;
		}
		$this->utf8mb4_checked = true;
		$this->utf8mb4_ready = false;
		try {
			if (!isset($this->db) || !isset($this->db->database) || !$this->db->database) {
				return false;
			}
			$schema = (string)$this->db->database;
			$sql = "SELECT TABLE_NAME, COLUMN_NAME, CHARACTER_SET_NAME
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = ?
				AND ((TABLE_NAME='iop_laboratory' AND COLUMN_NAME IN ('findings','result'))
					OR (TABLE_NAME='iop_sonography_report_draft' AND COLUMN_NAME IN ('findings','result')))";
			$q = $this->db->query($sql, array($schema));
			$rows = $q ? $q->result_array() : array();
			if (!$rows || count($rows) < 4) {
				return false;
			}
			foreach ($rows as $r) {
				$cs = isset($r['CHARACTER_SET_NAME']) ? strtolower((string)$r['CHARACTER_SET_NAME']) : '';
				if ($cs !== 'utf8mb4') {
					return false;
				}
			}
			$this->utf8mb4_ready = true;
			return true;
		} catch (Exception $e) {
			return false;
		}
	}

	public function __construct()
	{
		parent::__construct();
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table_name, $column_name){
		$table_name = (string) $table_name;
		$column_name = (string) $column_name;
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		return ($q && $q->num_rows() > 0);
	}

	public function info_column($table_name, $column_name){
		$table_name  = (string)$table_name;
		$column_name = (string)$column_name;
		if (!$this->table_exists($table_name)) return null;
		$schema = (string)$this->db->database;
		$q = $this->db->query(
			"SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, CHARACTER_SET_NAME, COLLATION_NAME
			 FROM information_schema.COLUMNS
			 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
			 LIMIT 1",
			array($schema, $table_name, $column_name)
		);
		if (!$q || $q->num_rows() === 0) return null;
		return $q->row_array();
	}

	public function ensure_lab_schema(){
		if (!$this->table_exists('iop_laboratory')) {
			return false;
		}
		if (!$this->column_exists('iop_laboratory', 'lab_result_upload')) {
			$this->db->query("ALTER TABLE `iop_laboratory` ADD COLUMN `lab_result_upload` varchar(255) DEFAULT NULL");
		}
		if ($this->table_exists('iop_laboratory_workflow')) {
			if (!$this->column_exists('iop_laboratory_workflow', 'requested_at')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `requested_at` datetime DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'scheduled_at')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `scheduled_at` datetime DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'performed_at')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `performed_at` datetime DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'verified_at')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `verified_at` datetime DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'delivered_at')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `delivered_at` datetime DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'cancelled_at')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `cancelled_at` datetime DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'cancelled_by')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `cancelled_by` varchar(25) DEFAULT NULL");
			}
			if (!$this->column_exists('iop_laboratory_workflow', 'cancel_reason')) {
				$this->db->query("ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `cancel_reason` text");
			}
		}
		return true;
	}

	public function ensure_lab_workflow_enhancements(){
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return false;
		}
		static $wf_booted = false;
		if ($wf_booted) return true;
		$wf_booted = true;

		$alters = array(
			'technician_id'           => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `technician_id` varchar(50) DEFAULT NULL",
			'verified_by'             => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `verified_by` varchar(50) DEFAULT NULL",
			'supervisor_notes'        => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `supervisor_notes` text",
			'doctor_acknowledged_at'  => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `doctor_acknowledged_at` datetime DEFAULT NULL",
			'doctor_acknowledged_by'  => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `doctor_acknowledged_by` varchar(50) DEFAULT NULL",
			'completed_at'            => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `completed_at` datetime DEFAULT NULL",
		);
		foreach ($alters as $col => $sql) {
			if (!$this->column_exists('iop_laboratory_workflow', $col)) {
				$this->db->query($sql);
			}
		}
		return true;
	}

	public function save_technician($io_lab_id, $technician_id){
		$io_lab_id     = (int)$io_lab_id;
		$technician_id = trim((string)$technician_id);
		if ($io_lab_id <= 0 || $technician_id === '') {
			return false;
		}
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return false;
		}
		$this->ensure_lab_workflow_enhancements();
		$existing = $this->db->get_where('iop_laboratory_workflow', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		$now = date('Y-m-d H:i:s');
		if ($existing) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->update('iop_laboratory_workflow', array(
				'technician_id' => $technician_id,
				'updated_at'    => $now
			));
		} else {
			$this->db->insert('iop_laboratory_workflow', array(
				'io_lab_id'     => $io_lab_id,
				'status'        => 'IN_PROGRESS',
				'technician_id' => $technician_id,
				'requested_at'  => $now,
				'updated_at'    => $now,
				'InActive'      => 0
			));
		}
		return ($this->db->affected_rows() > 0);
	}

	public function install_imaging_tables(){
		$this->ensure_lab_schema();
		$this->db->query("CREATE TABLE IF NOT EXISTS `sonography_items` (\n".
			"  `item_id` int(11) NOT NULL AUTO_INCREMENT,\n".
			"  `item_name` varchar(255) NOT NULL,\n".
			"  `bill_particular_id` int(11) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`item_id`),\n".
			"  KEY `idx_active` (`InActive`)\n".
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		if ($this->table_exists('sonography_items')) {
			if (!$this->column_exists('sonography_items', 'bill_particular_id')) {
				$this->db->query("ALTER TABLE `sonography_items` ADD COLUMN `bill_particular_id` int(11) DEFAULT NULL");
			}
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_sonography_request_meta` (\n".
			"  `meta_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `io_lab_id` int(11) NOT NULL,\n".
			"  `scan_item_id` int(11) DEFAULT NULL,\n".
			"  `urgency` varchar(20) DEFAULT NULL,\n".
			"  `requested_at` datetime DEFAULT NULL,\n".
			"  `clinical_question` text,\n".
			"  `encounter_type` varchar(3) DEFAULT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `requesting_doctor_id` varchar(25) DEFAULT NULL,\n".
			"  `created_by` varchar(25) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `updated_at` datetime DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`meta_id`),\n".
			"  UNIQUE KEY `uq_lab` (`io_lab_id`),\n".
			"  KEY `idx_scan` (`scan_item_id`)\n".
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		if ($this->table_exists('iop_sonography_request_meta')) {
			if (!$this->column_exists('iop_sonography_request_meta', 'urgency')) {
				$this->db->query("ALTER TABLE `iop_sonography_request_meta` ADD COLUMN `urgency` varchar(20) DEFAULT NULL");
			}
			if (!$this->column_exists('iop_sonography_request_meta', 'requested_at')) {
				$this->db->query("ALTER TABLE `iop_sonography_request_meta` ADD COLUMN `requested_at` datetime DEFAULT NULL");
			}
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_sonography_report_draft` (\n".
			"  `draft_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `io_lab_id` int(11) NOT NULL,\n".
			"  `findings` text,\n".
			"  `result` text,\n".
			"  `updated_at` datetime NOT NULL,\n".
			"  `updated_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`draft_id`),\n".
			"  UNIQUE KEY `uq_lab` (`io_lab_id`)\n".
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		if ($this->table_exists('sonography_items')) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM `sonography_items` WHERE `InActive` = 0");
			$row = $q ? $q->row() : null;
			$cnt = $row && isset($row->c) ? (int)$row->c : 0;
			if ($cnt === 0) {
				$defaults = array(
					'Abdominal Ultrasound',
					'Pelvic Ultrasound',
					'Obstetric Ultrasound',
					'KUB Ultrasound',
					'Thyroid Ultrasound',
					'Breast Ultrasound',
					'Scrotal Ultrasound',
					'Carotid Doppler',
					'Early Pregnancy Scan'
				);
				foreach ($defaults as $name) {
					$this->db->insert('sonography_items', array(
						'item_name' => (string)$name,
						'InActive' => 0
					));
				}
			}
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_attachment_meta` (\n".
			"  `meta_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `io_lab_id` int(11) NOT NULL,\n".
			"  `stored_filename` varchar(255) NOT NULL,\n".
			"  `original_filename` varchar(255) DEFAULT NULL,\n".
			"  `mime_type` varchar(100) DEFAULT NULL,\n".
			"  `file_size_kb` int(11) DEFAULT NULL,\n".
			"  `sha256` varchar(64) DEFAULT NULL,\n".
			"  `uploaded_by` varchar(25) DEFAULT NULL,\n".
			"  `uploaded_at` datetime NOT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`meta_id`),\n".
			"  KEY `idx_lab` (`io_lab_id`),\n".
			"  KEY `idx_file` (`stored_filename`(191))\n".
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_workflow` (\n".
			"  `wf_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `io_lab_id` int(11) NOT NULL,\n".
			"  `status` varchar(30) NOT NULL,\n".
			"  `requested_at` datetime DEFAULT NULL,\n".
			"  `scheduled_at` datetime DEFAULT NULL,\n".
			"  `performed_at` datetime DEFAULT NULL,\n".
			"  `reported_at` datetime DEFAULT NULL,\n".
			"  `verified_at` datetime DEFAULT NULL,\n".
			"  `delivered_at` datetime DEFAULT NULL,\n".
			"  `updated_at` datetime NOT NULL,\n".
			"  `updated_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`wf_id`),\n".
			"  UNIQUE KEY `uq_lab` (`io_lab_id`),\n".
			"  KEY `idx_status` (`status`)\n".
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_sonography_charge` (\n".
			"  `charge_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `io_lab_id` int(11) NOT NULL,\n".
			"  `iop_id` varchar(25) NOT NULL,\n".
			"  `patient_no` varchar(25) DEFAULT NULL,\n".
			"  `encounter_type` varchar(3) DEFAULT NULL,\n".
			"  `scan_item_id` int(11) DEFAULT NULL,\n".
			"  `bill_particular_id` int(11) DEFAULT NULL,\n".
			"  `item_name` varchar(255) DEFAULT NULL,\n".
			"  `rate_amount` decimal(18,2) NOT NULL DEFAULT 0,\n".
			"  `quantity` decimal(18,2) NOT NULL DEFAULT 1,\n".
			"  `status` varchar(20) NOT NULL DEFAULT 'PENDING',\n".
			"  `invoice_no` varchar(50) DEFAULT NULL,\n".
			"  `detail_id` int(11) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `created_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`charge_id`),\n".
			"  UNIQUE KEY `uq_lab` (`io_lab_id`),\n".
			"  KEY `idx_iop_status` (`iop_id`,`status`),\n".
			"  KEY `idx_invoice` (`invoice_no`)\n".
			") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		return true;
	}

	private function get_db_schema_name(){
		return (isset($this->db) && isset($this->db->database)) ? (string)$this->db->database : '';
	}

	private function info_table($table_name){
		$schema = $this->get_db_schema_name();
		if ($schema === '') {
			return null;
		}
		$q = $this->db->query(
			"SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
			array($schema, (string)$table_name)
		);
		$row = $q ? $q->row_array() : null;
		return $row ? $row : null;
	}


	public function imaging_db_hardening_plan(){
		$this->install_imaging_tables();
		$tables = array(
			'iop_laboratory',
			'sonography_items',
			'iop_sonography_request_meta',
			'iop_sonography_report_draft',
			'iop_laboratory_attachment_meta',
			'iop_laboratory_workflow',
			'iop_sonography_charge'
		);
		$plan = array(
			'ok' => true,
			'tables' => array(),
			'columns' => array(),
			'sql' => array()
		);

		foreach ($tables as $t) {
			if (!$this->table_exists($t)) {
				continue;
			}
			$info = $this->info_table($t);
			$engine = $info && isset($info['ENGINE']) ? strtoupper(trim((string)$info['ENGINE'])) : '';
			$coll = $info && isset($info['TABLE_COLLATION']) ? strtolower(trim((string)$info['TABLE_COLLATION'])) : '';
			$plan['tables'][] = array(
				'table' => $t,
				'engine' => $engine,
				'collation' => $coll
			);
			$skipFullCharsetConvert = ($t === 'iop_laboratory');

			if ($engine === 'MYISAM') {
				$plan['sql'][] = "REPAIR TABLE `".$t."`";
			}
			if ($engine !== '' && $engine !== 'INNODB') {
				$plan['sql'][] = "ALTER TABLE `".$t."` ENGINE=InnoDB";
			}
			if (!$skipFullCharsetConvert && $coll !== '' && strpos($coll, 'utf8mb4_') !== 0) {
				$plan['sql'][] = "ALTER TABLE `".$t."` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
			}
		}

		$colTargets = array(
			array('iop_laboratory', 'findings'),
			array('iop_laboratory', 'result'),
			array('iop_sonography_report_draft', 'findings'),
			array('iop_sonography_report_draft', 'result'),
			array('iop_laboratory_workflow', 'cancel_reason')
		);
		foreach ($colTargets as $pair) {
			$t = (string)$pair[0];
			$c = (string)$pair[1];
			if (!$this->table_exists($t) || !$this->column_exists($t, $c)) {
				continue;
			}
			$ci = $this->info_column($t, $c);
			$cs = $ci && isset($ci['CHARACTER_SET_NAME']) ? strtolower(trim((string)$ci['CHARACTER_SET_NAME'])) : '';
			$cc = $ci && isset($ci['COLLATION_NAME']) ? strtolower(trim((string)$ci['COLLATION_NAME'])) : '';
			$ctype = $ci && isset($ci['COLUMN_TYPE']) ? (string)$ci['COLUMN_TYPE'] : 'text';
			$nullable = $ci && isset($ci['IS_NULLABLE']) ? strtoupper(trim((string)$ci['IS_NULLABLE'])) : 'YES';
			$plan['columns'][] = array(
				'table' => $t,
				'column' => $c,
				'column_type' => $ctype,
				'charset' => $cs,
				'collation' => $cc,
				'nullable' => $nullable
			);
			if ($cs !== 'utf8mb4' || $cc !== 'utf8mb4_unicode_ci') {
				$nullSql = ($nullable === 'NO') ? 'NOT NULL' : 'NULL';
				$plan['sql'][] = "ALTER TABLE `".$t."` MODIFY `".$c."` ".$ctype." CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ".$nullSql;
			}
		}

		$uniq = array();
		$final = array();
		foreach ($plan['sql'] as $stmt) {
			$key = trim((string)$stmt);
			if ($key === '' || isset($uniq[$key])) {
				continue;
			}
			$uniq[$key] = true;
			$final[] = $key;
		}
		$plan['sql'] = $final;
		return $plan;
	}

	public function apply_imaging_db_hardening(){
		$plan = $this->imaging_db_hardening_plan();
		$out = array(
			'ok' => true,
			'steps' => array()
		);
		if (!$plan || !isset($plan['sql']) || !is_array($plan['sql']) || count($plan['sql']) === 0) {
			return $out;
		}
		foreach ($plan['sql'] as $stmt) {
			$res = array('sql' => $stmt, 'ok' => true, 'error' => '');
			try {
				$this->db->query($stmt);
			} catch (Exception $e) {
				$res['ok'] = false;
				$res['error'] = (string)$e->getMessage();
				$out['ok'] = false;
			}
			$out['steps'][] = $res;
		}
		return $out;
	}

	public function upsert_sonography_report_draft($io_lab_id, $findings, $result, $updated_by = null){
		$this->install_imaging_tables();
		if (!$this->table_exists('iop_sonography_report_draft')) {
			return false;
		}
		$io_lab_id = (int)$io_lab_id;
		$now = date('Y-m-d H:i:s');
		$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$existing = $this->db->get('iop_sonography_report_draft')->row();
		$data = array(
			'findings' => $findings !== null ? $this->db_safe_text($findings) : null,
			'result' => $result !== null ? $this->db_safe_text($result) : null,
			'updated_at' => $now,
			'updated_by' => $updated_by !== null ? (string)$updated_by : null,
			'InActive' => 0
		);
		if ($existing) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->update('iop_sonography_report_draft', $data);
			return true;
		}
		$data['io_lab_id'] = $io_lab_id;
		$this->db->insert('iop_sonography_report_draft', $data);
		return true;
	}

	public function get_sonography_report_draft($io_lab_id){
		$this->install_imaging_tables();
		if (!$this->table_exists('iop_sonography_report_draft')) {
			return null;
		}
		$this->db->where(array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get('iop_sonography_report_draft');
		return $q ? $q->row() : null;
	}

	public function clear_sonography_report_draft($io_lab_id){
		if (!$this->table_exists('iop_sonography_report_draft')) {
			return true;
		}
		$this->db->where(array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0));
		$this->db->update('iop_sonography_report_draft', array('InActive' => 1));
		return true;
	}

	public function upsert_sonography_charge_from_request($io_lab_id, $iop_id, $patient_no, $encounter_type, $scan_item_id, $clinical_question, $rate_amount, $quantity, $created_by = null){
		$this->install_imaging_tables();
		if (!$this->table_exists('iop_sonography_charge')) {
			return array('ok' => false);
		}
		$io_lab_id = (int)$io_lab_id;
		$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$existing = $this->db->get('iop_sonography_charge')->row();

		$itemName = null;
		$billParticularId = null;
		$scanId = ($scan_item_id !== null && (int)$scan_item_id > 0) ? (int)$scan_item_id : null;
		if ($scanId !== null && $this->table_exists('ghs_sonography_tests')) {
			$g = $this->db->get_where('ghs_sonography_tests', array('test_id' => $scanId, 'InActive' => 0))->row();
			if ($g && isset($g->test_name) && trim((string)$g->test_name) !== '') {
				$itemName = (string)$g->test_name;
			}
			if ($g && isset($g->particular_id) && (int)$g->particular_id > 0) {
				$billParticularId = (int)$g->particular_id;
			}
		}
		if ($scanId !== null && ($itemName === null || trim((string)$itemName) === '' || $billParticularId === null) && $this->table_exists('sonography_items')) {
			$this->db->select('item_name, bill_particular_id');
			$this->db->where(array('item_id' => $scanId, 'InActive' => 0));
			$this->db->limit(1);
			$si = $this->db->get('sonography_items')->row();
			if ($si) {
				if ($itemName === null || trim((string)$itemName) === '') {
					$itemName = isset($si->item_name) ? (string)$si->item_name : null;
				}
				if ($billParticularId === null && isset($si->bill_particular_id) && (int)$si->bill_particular_id > 0) {
					$billParticularId = (int)$si->bill_particular_id;
				}
			}
		}
		if ($billParticularId !== null && $this->table_exists('bill_particular')) {
			// Do not link to inactive bill_particular
			$bp = $this->db->get_where('bill_particular', array('particular_id' => (int)$billParticularId, 'InActive' => 0), 1)->row();
			if (!$bp) {
				$billParticularId = null;
			}
		}
		if ($itemName === null || trim((string)$itemName) === '') {
			$itemName = ($clinical_question !== null && trim((string)$clinical_question) !== '') ? ('Custom Sonography - '.trim((string)$clinical_question)) : 'Custom Sonography';
		}

		$rate = (float)$rate_amount;
		$qty = (float)$quantity;
		if ($qty <= 0) {
			$qty = 1;
		}
		$data = array(
			'iop_id' => (string)$iop_id,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'encounter_type' => $encounter_type !== null ? (string)$encounter_type : null,
			'scan_item_id' => $scanId,
			'bill_particular_id' => $billParticularId,
			'item_name' => $itemName,
			'rate_amount' => $rate,
			'quantity' => $qty,
			'status' => 'PENDING',
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => $created_by !== null ? (string)$created_by : null,
			'InActive' => 0
		);
		if ($existing) {
			$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
			$this->db->update('iop_sonography_charge', $data);
			return array('ok' => true, 'charge_id' => isset($existing->charge_id) ? (int)$existing->charge_id : 0);
		}
		$data['io_lab_id'] = $io_lab_id;
		$this->db->insert('iop_sonography_charge', $data);
		return array('ok' => true, 'charge_id' => (int)$this->db->insert_id());
	}

	public function sonography_item_dropdown(){
		if (!$this->table_exists('sonography_items')) {
			return array();
		}
		$this->db->select('item_id AS particular_id, item_name AS particular_name', false);
		$this->db->from('sonography_items');
		$this->db->where('InActive', 0);
		$this->db->order_by('item_name', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function upsert_sonography_request_meta($io_lab_id, $scan_item_id, $clinical_question, $encounter_type, $patient_no, $requesting_doctor_id, $created_by, $urgency = null){
		if (!$this->table_exists('iop_sonography_request_meta')) {
			return false;
		}
		$io_lab_id = (int)$io_lab_id;
		$now = date('Y-m-d H:i:s');
		$existing = $this->db->get_where('iop_sonography_request_meta', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		$urgency = $urgency !== null ? strtoupper(trim((string)$urgency)) : '';
		if ($urgency !== 'ROUTINE' && $urgency !== 'URGENT' && $urgency !== 'STAT') {
			$urgency = '';
		}
		$data = array(
			'scan_item_id' => ($scan_item_id !== null && (int)$scan_item_id > 0) ? (int)$scan_item_id : null,
			'clinical_question' => $clinical_question !== null ? (string)$clinical_question : null,
			'encounter_type' => $encounter_type !== null ? (string)$encounter_type : null,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'requesting_doctor_id' => $requesting_doctor_id !== null ? (string)$requesting_doctor_id : null,
			'created_by' => $created_by !== null ? (string)$created_by : null,
			'InActive' => 0
		);
		if ($urgency !== '' && $this->column_exists('iop_sonography_request_meta', 'urgency')) {
			$data['urgency'] = $urgency;
		}
		if ($existing) {
			$data['updated_at'] = $now;
			if ((!isset($existing->requested_at) || empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00') && $this->column_exists('iop_sonography_request_meta', 'requested_at')) {
				$data['requested_at'] = $now;
			}
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->where('InActive', 0);
			return $this->db->update('iop_sonography_request_meta', $data);
		}
		$data['io_lab_id'] = $io_lab_id;
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		if ($this->column_exists('iop_sonography_request_meta', 'requested_at')) {
			$data['requested_at'] = $now;
		}
		return $this->db->insert('iop_sonography_request_meta', $data);
	}

	public function get_sonography_billing_status($io_lab_id){
		$this->install_imaging_tables();
		$io_lab_id = (int)$io_lab_id;
		$meta = $this->get_sonography_request_meta($io_lab_id);
		$enc = $meta && isset($meta->encounter_type) ? strtoupper(trim((string)$meta->encounter_type)) : '';
		$out = array(
			'encounter_type' => $enc,
			'is_pending' => true,
			'label' => 'Billing Pending'
		);
		$charge = $this->get_sonography_charge_by_lab($io_lab_id);
		if ($charge) {
			$inv = isset($charge->invoice_no) ? trim((string)$charge->invoice_no) : '';
			$st = isset($charge->status) ? strtoupper(trim((string)$charge->status)) : '';
			$encType = isset($charge->encounter_type) ? strtoupper(trim((string)$charge->encounter_type)) : $enc;
			$out['encounter_type'] = $encType;
			$invStatus = '';
			$payerType = 'CASH';
			if ($inv !== '' && $this->table_exists('iop_billing')) {
				$this->db->select('payment_status, payer_type');
				$this->db->where(array('invoice_no' => $inv, 'InActive' => 0));
				$this->db->limit(1);
				$invRow = $this->db->get('iop_billing')->row();
				if ($invRow) {
					$invStatus = isset($invRow->payment_status) ? strtoupper(trim((string)$invRow->payment_status)) : '';
					$payerType = isset($invRow->payer_type) ? strtoupper(trim((string)$invRow->payer_type)) : 'CASH';
				}
			}
			$isCovered = ($payerType !== '' && $payerType !== 'CASH');
			$isDeposit = ($invStatus === 'PARTIAL');
			$isPaid = ($invStatus === 'PAID');
			if ($encType === 'IPD') {
				$out['is_pending'] = false;
				if ($st === 'PAID' || $isPaid) {
					$out['label'] = 'Paid';
				} else if ($isCovered) {
					$out['label'] = 'Covered';
				} else if ($isDeposit) {
					$out['label'] = 'Deposit Received';
				} else if ($inv !== '' || $st === 'INVOICED') {
					$out['label'] = 'Billed';
				} else {
					$out['label'] = 'Billing Pending (Charge Posted)';
				}
			} else {
				$out['is_pending'] = ($st !== 'PAID' && !$isPaid && !$isDeposit && !$isCovered);
				if ($st === 'PAID' || $isPaid) {
					$out['label'] = 'Paid';
				} else if ($isCovered) {
					$out['label'] = 'Covered';
				} else if ($isDeposit) {
					$out['label'] = 'Deposit Received';
				} else if ($inv !== '' || $st === 'INVOICED') {
					$out['label'] = 'Billed';
				} else {
					$out['label'] = 'Billing Pending (Charge Posted)';
				}
			}
			return $out;
		}
		if ($enc === 'IPD') {
			$charge = $this->get_ipd_sonography_charge_by_lab($io_lab_id);
			if (!$charge) {
				$out['is_pending'] = true;
				$out['label'] = 'Billing Not Posted';
				return $out;
			}
			$inv = isset($charge->invoice_no) ? trim((string)$charge->invoice_no) : '';
			$out['is_pending'] = ($inv === '');
			$out['label'] = ($inv === '') ? 'Billing Pending (Charge Posted)' : 'Billed';
			return $out;
		}

		if ($this->table_exists('iop_billable_item_lock')) {
			$srcRef = 'iop_sonography_request:' . (string)$io_lab_id;
			$this->db->where(array('source_module' => 'SONOGRAPHY', 'source_ref' => $srcRef, 'InActive' => 0));
			$this->db->limit(1);
			$lock = $this->db->get('iop_billable_item_lock')->row();
			$st = $lock && isset($lock->status) ? strtoupper(trim((string)$lock->status)) : '';
			$out['is_pending'] = ($st !== 'PAID');
			$out['label'] = ($st === 'PAID') ? 'Paid' : 'Billing Pending';
			return $out;
		}
		return $out;
	}

	public function get_sonography_charge_by_lab($io_lab_id){
		if (!$this->table_exists('iop_sonography_charge')) {
			return null;
		}
		$this->db->where(array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get('iop_sonography_charge');
		return $q ? $q->row() : null;
	}

	public function lock_sonography_request_for_update($io_lab_id)
	{
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) {
			return false;
		}

		$charge_id = 0;

		if ($this->table_exists('iop_laboratory')) {
			$this->db->query('SELECT io_lab_id FROM iop_laboratory WHERE io_lab_id = ? FOR UPDATE', array($io_lab_id));
		}
		if ($this->table_exists('iop_laboratory_workflow')) {
			$this->db->query('SELECT io_lab_id FROM iop_laboratory_workflow WHERE io_lab_id = ? FOR UPDATE', array($io_lab_id));
		}
		if ($this->table_exists('iop_sonography_charge')) {
			$q = $this->db->query('SELECT charge_id FROM iop_sonography_charge WHERE InActive = 0 AND (charge_id = ? OR io_lab_id = ?) ORDER BY charge_id DESC LIMIT 1 FOR UPDATE', array($io_lab_id, $io_lab_id));
			$row = $q ? $q->row() : null;
			$charge_id = ($row && isset($row->charge_id)) ? (int)$row->charge_id : 0;
		}
		if ($charge_id > 0 && $this->table_exists('billing_transactions')) {
			$item_ref = 'sono_charge_id:' . $charge_id;
			$this->db->query("SELECT txn_id FROM billing_transactions WHERE InActive = 0 AND department = 'IMAGING' AND item_ref = ? LIMIT 1 FOR UPDATE", array($item_ref));
		}
		return true;
	}

	public function lock_lab_request_for_update($io_lab_id)
	{
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) {
			return false;
		}

		if ($this->table_exists('iop_laboratory')) {
			$this->db->query('SELECT io_lab_id FROM iop_laboratory WHERE io_lab_id = ? FOR UPDATE', array($io_lab_id));
		}
		if ($this->table_exists('iop_laboratory_workflow')) {
			$this->db->query('SELECT io_lab_id FROM iop_laboratory_workflow WHERE io_lab_id = ? FOR UPDATE', array($io_lab_id));
		}
		if ($this->table_exists('billing_transactions')) {
			$item_ref = 'io_lab_id:' . (int)$io_lab_id;
			$this->db->query("SELECT txn_id FROM billing_transactions WHERE InActive = 0 AND item_ref = ? FOR UPDATE", array($item_ref));
		}
		return true;
	}

	public function cancel_sonography_request($io_lab_id, $reason, $cancelled_by){
		$io_lab_id = (int)$io_lab_id;
		$reason = trim((string)$reason);
		$cancelled_by = (string)$cancelled_by;
		if ($io_lab_id <= 0 || $reason === '') {
			return false;
		}
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return false;
		}
		$now = date('Y-m-d H:i:s');
		$ok = $this->upsert_workflow_status($io_lab_id, 'CANCELLED', $cancelled_by);
		if (!$ok) {
			return false;
		}
		$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
		$this->db->update('iop_laboratory_workflow', array(
			'cancelled_at' => $now,
			'cancelled_by' => $cancelled_by !== '' ? $cancelled_by : null,
			'cancel_reason' => $reason,
			'updated_at' => $now,
			'updated_by' => $cancelled_by !== '' ? $cancelled_by : null
		));
		return true;
	}

	public function sync_order_master_from_sonography_charge($charge_id, $user_id = null, $last_sync_at = null, $is_backfill = false, $trace_id = null)
	{
		$charge_id = (int)$charge_id;
		if ($charge_id <= 0) {
			return false;
		}
		if (!$this->table_exists('iop_sonography_charge')) {
			return false;
		}
		$ch = $this->db->get_where('iop_sonography_charge', array('charge_id' => $charge_id, 'InActive' => 0))->row();
		if (!$ch && $this->column_exists('iop_sonography_charge', 'id')) {
			$ch = $this->db->get_where('iop_sonography_charge', array('id' => $charge_id, 'InActive' => 0))->row();
		}
		if (!$ch) {
			return false;
		}
		$io_lab_id = isset($ch->io_lab_id) ? (int)$ch->io_lab_id : 0;
		if ($io_lab_id <= 0) {
			return false;
		}

		$this->load->library('OrderMasterSync');

		$visit_id = isset($ch->iop_id) ? (string)$ch->iop_id : '';
		$patient_no = isset($ch->patient_no) ? (string)$ch->patient_no : '';
		if ($patient_no === '' && $visit_id !== '' && $this->table_exists('patient_details_iop')) {
			$v = $this->db->get_where('patient_details_iop', array('IO_ID' => $visit_id, 'InActive' => 0))->row();
			if ($v && isset($v->patient_no)) {
				$patient_no = (string)$v->patient_no;
			}
		}

		$invoice_no = isset($ch->invoice_no) ? trim((string)$ch->invoice_no) : '';
		$status = isset($ch->status) ? strtoupper(trim((string)$ch->status)) : '';

		$financial_state = 'PENDING';
		$payment_state = 'UNPAID';
		if ($invoice_no !== '') {
			$financial_state = 'BILLED';
		}
		if ($status === 'PAID') {
			$financial_state = 'PAID';
			$payment_state = 'PAID';
		}

		if ($invoice_no !== '' && $this->table_exists('iop_billing')) {
			$this->db->select('payment_status, payer_type, total_amount');
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->limit(1);
			$inv = $this->db->get('iop_billing')->row();
			if ($inv) {
				$payer = isset($inv->payer_type) ? strtoupper(trim((string)$inv->payer_type)) : 'CASH';
				$invStatus = isset($inv->payment_status) ? strtoupper(trim((string)$inv->payment_status)) : '';
				if ($payer !== '' && $payer !== 'CASH') {
					$payment_state = 'COVERED';
				} else if ($invStatus === 'PAID') {
					$payment_state = 'PAID';
				} else if ($invStatus === 'PARTIAL') {
					$payment_state = 'DEPOSIT';
				}

				if ($payment_state === 'PAID') {
					$financial_state = 'PAID';
				} else if ($payment_state === 'DEPOSIT' || $payment_state === 'COVERED') {
					$financial_state = 'BILLED';
				}
			}
		}

		$sync_at = ($last_sync_at !== null && trim((string)$last_sync_at) !== '') ? (string)$last_sync_at : date('Y-m-d H:i:s');
		$order = array(
			'patient_no' => $patient_no,
			'iop_id' => $visit_id,
			'source_module' => 'SONOGRAPHY',
			'module' => 'SONOGRAPHY',
			'source_id' => $io_lab_id,
			'is_backfill' => $is_backfill ? 1 : 0,
			'last_sync_at' => $sync_at,
		);
		if ($trace_id !== null && trim((string)$trace_id) !== '') {
			$order['trace_id'] = (string)$trace_id;
			log_message('info', 'Sonography SSOT sync (charge): ' . json_encode(array(
				'trace_id' => (string)$trace_id,
				'io_lab_id' => $io_lab_id,
				'invoice_no' => $invoice_no,
				'financial_state' => $financial_state,
				'payment_state' => $payment_state,
			)));
		}

		$this->ordermastersync->sync_billing($order, $financial_state);
		$this->ordermastersync->sync_payment($order, $payment_state);
		return true;
	}

	public function get_sonography_request_meta($io_lab_id){
		if (!$this->table_exists('iop_sonography_request_meta')) {
			return null;
		}
		$q = $this->db->get_where('iop_sonography_request_meta', array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0));
		return $q ? $q->row() : null;
	}

	public function ensure_sonography_charge_posted($io_lab_id, $iop_id, $patient_no, $encounter_type, $scan_item_id, $clinical_question, $created_by = null){
		$this->install_imaging_tables();
		if (!$this->table_exists('iop_sonography_charge')) {
			return false;
		}
		$io_lab_id = (int)$io_lab_id;
		$iop_id = (string)$iop_id;
		$encounter_type = strtoupper(trim((string)$encounter_type));
		if ($encounter_type !== 'IPD' && $encounter_type !== 'OPD') {
			$encounter_type = null;
		}
		$labItemName = null;
		try {
			if ($this->table_exists('iop_laboratory')) {
				$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0), 1)->row();
				if ($lab && isset($lab->category_id) && (int)$lab->category_id === 18) {
					if (isset($lab->laboratory_id) && (int)$lab->laboratory_id > 0) {
						$scan_item_id = (int)$lab->laboratory_id;
					}
					if (isset($lab->laboratory_text) && trim((string)$lab->laboratory_text) !== '') {
						$labItemName = trim((string)$lab->laboratory_text);
					}
				}
			}
		} catch (\Throwable $e) {
			$labItemName = null;
		}
		$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$exists = $this->db->get('iop_sonography_charge')->row();
		if ($exists) {
			try {
				$existingInvoice = isset($exists->invoice_no) ? trim((string)$exists->invoice_no) : '';
				$existingStatus = isset($exists->status) ? strtoupper(trim((string)$exists->status)) : '';
				$existingBillParticularId = isset($exists->bill_particular_id) ? (int)$exists->bill_particular_id : 0;
				$existingRate = isset($exists->rate_amount) ? (float)$exists->rate_amount : 0.0;
				$needsEnrich = ($existingInvoice === '' && ($existingStatus === '' || $existingStatus === 'PENDING') && ($existingBillParticularId <= 0 || $existingRate <= 0.0));
				if ($needsEnrich) {
					$itemName = isset($exists->item_name) ? (string)$exists->item_name : null;
					$billParticularId = ($existingBillParticularId > 0) ? $existingBillParticularId : null;
					$rateAmt = ($existingRate > 0.0) ? $existingRate : 0.0;

					$scanId = ($scan_item_id !== null && (int)$scan_item_id > 0) ? (int)$scan_item_id : null;
					// Ghana catalog is authoritative for OPD multi-entry sonography
					if ($scanId !== null && $this->table_exists('ghs_sonography_tests')) {
						$g = $this->db->get_where('ghs_sonography_tests', array('test_id' => $scanId, 'InActive' => 0), 1)->row();
						if ($g) {
							if (($itemName === null || trim((string)$itemName) === '') && isset($g->test_name) && trim((string)$g->test_name) !== '') {
								$itemName = (string)$g->test_name;
							}
							if ($billParticularId === null && isset($g->particular_id) && (int)$g->particular_id > 0) {
								$billParticularId = (int)$g->particular_id;
							}
							if ($rateAmt <= 0.0) {
								$payer = null;
								try {
									$this->load->model('app/billing_model');
									$payer = $this->billing_model->determine_payer_type((string)$patient_no);
								} catch (\Throwable $e) {
									$payer = null;
								}
								$useNhis = ($payer === 'NHIS' && isset($g->is_nhis_covered) && (int)$g->is_nhis_covered === 1);
								if ($useNhis && isset($g->nhis_price) && (float)$g->nhis_price > 0) {
									$rateAmt = (float)$g->nhis_price;
								} else if (isset($g->price) && (float)$g->price > 0) {
									$rateAmt = (float)$g->price;
								}
							}
						}
					}
					if ($billParticularId === null && $scanId !== null && $this->table_exists('bill_particular')) {
						$this->db->select('particular_id, particular_name, charge_amount');
						$this->db->where(array('particular_id' => (int)$scanId, 'InActive' => 0));
						$this->db->limit(1);
						$bp = $this->db->get('bill_particular')->row();
						if ($bp && isset($bp->particular_id)) {
							$billParticularId = (int)$bp->particular_id;
							if (($itemName === null || trim((string)$itemName) === '') && isset($bp->particular_name)) {
								$itemName = (string)$bp->particular_name;
							}
							if ($rateAmt <= 0.0 && isset($bp->charge_amount)) {
								$rateAmt = (float)$bp->charge_amount;
							}
						}
					}
					if ($billParticularId !== null && $this->table_exists('bill_particular')) {
						// Do not link to inactive bill_particular
						$bpOk = $this->db->get_where('bill_particular', array('particular_id' => (int)$billParticularId, 'InActive' => 0), 1)->row();
						if (!$bpOk) {
							$billParticularId = null;
						}
					}

					$update = array();
					if ($existingBillParticularId <= 0 && $billParticularId !== null) {
						$update['bill_particular_id'] = (int)$billParticularId;
					}
					if ($existingRate <= 0.0 && $rateAmt > 0.0) {
						$update['rate_amount'] = (float)$rateAmt;
					}
					if ($labItemName !== null && $labItemName !== '' && (!isset($exists->item_name) || trim((string)$exists->item_name) === '' || trim((string)$exists->item_name) !== $labItemName)) {
						$update['item_name'] = (string)$labItemName;
					} else if (($itemName !== null && trim((string)$itemName) !== '') && (!isset($exists->item_name) || trim((string)$exists->item_name) === '' || trim((string)$exists->item_name) !== trim((string)$itemName))) {
						$update['item_name'] = (string)$itemName;
					}
					if (!empty($update)) {
						$update['updated_at'] = date('Y-m-d H:i:s');
						if ($this->column_exists('iop_sonography_charge', 'updated_by') && $created_by !== null) {
							$update['updated_by'] = (string)$created_by;
						}
						$this->db->where('charge_id', (int)$exists->charge_id);
						$this->db->update('iop_sonography_charge', $update);
						// Keep SSOT billing_transactions aligned (self-healing pricing)
						try {
							if ($this->table_exists('billing_transactions')) {
								$ref = 'sono_charge_id:' . (int)$exists->charge_id;
								$bt = $this->db->get_where('billing_transactions', array('InActive' => 0, 'department' => 'IMAGING', 'item_ref' => $ref), 1)->row();
								if ($bt && isset($update['rate_amount']) && (float)$update['rate_amount'] > 0) {
									$u = array();
									$unit = (float)$update['rate_amount'];
									$qty = isset($bt->quantity) ? (float)$bt->quantity : 1.0;
									$u['unit_price'] = $unit;
									$u['gross_amount'] = $unit * $qty;
									$u['total_amount'] = $unit * $qty;
									$u['net_amount'] = $unit * $qty;
									$u['balance_amount'] = $unit * $qty;
									$u['updated_at'] = date('Y-m-d H:i:s');
									if ($created_by !== null) { $u['updated_by'] = (string)$created_by; }
									$this->db->where(array('txn_id' => (int)$bt->txn_id, 'InActive' => 0));
									$this->db->update('billing_transactions', $u);
								}
							}
						} catch (\Throwable $e) {
						}
					}
				}
			} catch (\Throwable $e) {
			}
			return true;
		}

		$itemName = $labItemName;
		$billParticularId = null;
		$rateAmt = 0.0;
		$scanId = ($scan_item_id !== null && (int)$scan_item_id > 0) ? (int)$scan_item_id : null;

		$this->load->model('app/billing_model');
		$nhisUsed = false;
		// Ghana catalog (if present) is authoritative for OPD sonography requests
		if ($scanId !== null && $this->table_exists('ghs_sonography_tests')) {
			$g = $this->db->get_where('ghs_sonography_tests', array('test_id' => $scanId, 'InActive' => 0), 1)->row();
			if ($g) {
				if (isset($g->test_name) && trim((string)$g->test_name) !== '') {
					$itemName = (string)$g->test_name;
				}
				if (isset($g->particular_id) && (int)$g->particular_id > 0) {
					$billParticularId = (int)$g->particular_id;
				}
				$payer = null;
				if ($patient_no !== null) {
					try { $payer = $this->billing_model->determine_payer_type((string)$patient_no); } catch (\Throwable $e) { $payer = null; }
				}
				$useNhis = ($payer === 'NHIS' && isset($g->is_nhis_covered) && (int)$g->is_nhis_covered === 1);
				if ($useNhis && isset($g->nhis_price) && (float)$g->nhis_price > 0) {
					$rateAmt = (float)$g->nhis_price;
					$nhisUsed = true;
				} else if (isset($g->price) && (float)$g->price > 0) {
					$rateAmt = (float)$g->price;
				}
			}
		}
		// Legacy fallback: sonography_items / bill_particular
		if ($rateAmt <= 0.0 && $scanId !== null && $patient_no !== null) {
			$sonoNhis = $this->billing_model->getNhisSonographyRate($scanId, (string)$patient_no);
			if ($sonoNhis) {
				if ($itemName === null || trim((string)$itemName) === '') {
					$itemName = isset($sonoNhis->item_name) ? (string)$sonoNhis->item_name : null;
				}
				if ($billParticularId === null) {
					$billParticularId = isset($sonoNhis->bill_particular_id) ? (int)$sonoNhis->bill_particular_id : null;
				}
				$rateAmt = (float)$sonoNhis->effective_rate;
				$nhisUsed = !empty($sonoNhis->nhis_covered_flag);
			}
		}
		if ($itemName === null && $scanId !== null && $this->table_exists('sonography_items')) {
			$this->db->select('item_name, bill_particular_id');
			$this->db->where(array('item_id' => $scanId, 'InActive' => 0));
			$this->db->limit(1);
			$si = $this->db->get('sonography_items')->row();
			if ($si) {
				$itemName = isset($si->item_name) ? (string)$si->item_name : null;
				$billParticularId = isset($si->bill_particular_id) && (int)$si->bill_particular_id > 0 ? (int)$si->bill_particular_id : null;
			}
		}
		if ($billParticularId === null && $scanId !== null && $this->table_exists('bill_particular')) {
			$this->db->select('particular_id, particular_name');
			$this->db->where(array('particular_id' => (int)$scanId, 'InActive' => 0));
			$this->db->limit(1);
			$bp = $this->db->get('bill_particular')->row();
			if ($bp && isset($bp->particular_id)) {
				$billParticularId = (int)$bp->particular_id;
				if ($itemName === null || trim((string)$itemName) === '') {
					$itemName = isset($bp->particular_name) ? (string)$bp->particular_name : $itemName;
				}
			}
		}
		if ($billParticularId !== null && $this->table_exists('bill_particular')) {
			$bpOk = $this->db->get_where('bill_particular', array('particular_id' => (int)$billParticularId, 'InActive' => 0), 1)->row();
			if (!$bpOk) {
				$billParticularId = null;
			}
		}
		if (!$nhisUsed && $billParticularId !== null && $this->table_exists('bill_particular')) {
			$this->db->select('charge_amount');
			$this->db->where(array('particular_id' => (int)$billParticularId, 'InActive' => 0));
			$this->db->limit(1);
			$bp = $this->db->get('bill_particular')->row();
			if ($bp && isset($bp->charge_amount)) {
				$rateAmt = (float)$bp->charge_amount;
			}
		}
		if ($itemName === null || trim((string)$itemName) === '') {
			$itemName = ($clinical_question !== null && trim((string)$clinical_question) !== '') ? ('Custom Sonography - '.trim((string)$clinical_question)) : 'Custom Sonography';
		}

		$this->db->insert('iop_sonography_charge', array(
			'io_lab_id' => $io_lab_id,
			'iop_id' => $iop_id,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'encounter_type' => $encounter_type,
			'scan_item_id' => $scanId,
			'bill_particular_id' => $billParticularId,
			'item_name' => $itemName,
			'rate_amount' => $rateAmt,
			'quantity' => 1,
			'status' => 'PENDING',
			'invoice_no' => null,
			'detail_id' => null,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => $created_by !== null ? (string)$created_by : null,
			'InActive' => 0
		));
		return true;
	}

	public function ensure_ipd_sonography_charge_posted($io_lab_id, $iop_id, $patient_no, $scan_item_id, $clinical_question, $created_by = null){
		return $this->ensure_sonography_charge_posted($io_lab_id, $iop_id, $patient_no, 'IPD', $scan_item_id, $clinical_question, $created_by);
	}

	public function get_ipd_sonography_charge_by_lab($io_lab_id){
		if (!$this->table_exists('iop_sonography_charge')) {
			return null;
		}
		$this->db->where(array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get('iop_sonography_charge');
		return $q ? $q->row() : null;
	}

	public function upsert_workflow_status($io_lab_id, $status, $updated_by){
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return false;
		}
		$io_lab_id = (int) $io_lab_id;
		$status = strtoupper(trim((string) $status));
		$now = date('Y-m-d H:i:s');
		$existing = $this->db->get_where('iop_laboratory_workflow', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		$this->load->library('OrderStateMachine');
		$current_status = $existing && isset($existing->status) ? strtoupper(trim((string)$existing->status)) : 'REQUESTED';
		if ($current_status === '') { $current_status = 'REQUESTED'; }

		$assert_current = $current_status;
		if (
			in_array($this->orderstatemachine->normalize($current_status), array('REQUESTED'), true)
			&& $this->orderstatemachine->normalize($status) === 'PAID'
		) {
			$pay = $this->get_lab_payment_status($io_lab_id);
			if (
				(isset($pay['paid']) && $pay['paid'])
				|| $this->_ssot_lab_cleared($io_lab_id)
			) {
				$assert_current = 'BILLED';
			}
		}
		if (
			in_array($this->orderstatemachine->normalize($current_status), array('REQUESTED', 'BILLED'), true)
			&& $this->orderstatemachine->normalize($status) === 'IN_PROGRESS'
		) {
			$pay = $this->get_lab_payment_status($io_lab_id);
			if (
				(isset($pay['paid']) && $pay['paid'])
				|| $this->_ssot_lab_cleared($io_lab_id)
			) {
				$assert_current = 'PAID';
			}
		}
		if (!$this->orderstatemachine->assert_transition($assert_current, $status)) {
			return false;
		}
		if ($existing) {
			$data = array(
				'status' => $status,
				'updated_at' => $now,
				'updated_by' => (string)$updated_by
			);
			if ($status === 'REQUESTED' && (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00')) {
				$data['requested_at'] = $now;
			}
			if ($status === 'BILLED') {
				if (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00') {
					$data['requested_at'] = $now;
				}
			}
			if ($status === 'PAID') {
				if (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00') {
					$data['requested_at'] = $now;
				}
			}
			if ($status === 'IN_PROGRESS' && (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00')) {
				$data['requested_at'] = $now;
			}
			if ($status === 'SCHEDULED' && isset($existing->scheduled_at) && (empty($existing->scheduled_at) || $existing->scheduled_at === '0000-00-00 00:00:00')) {
				$data['scheduled_at'] = $now;
				if (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00') {
					$data['requested_at'] = $now;
				}
			}
			if ($status === 'PERFORMED' && isset($existing->performed_at) && (empty($existing->performed_at) || $existing->performed_at === '0000-00-00 00:00:00')) {
				$data['performed_at'] = $now;
				if (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00') {
					$data['requested_at'] = $now;
				}
			}
			if ($status === 'REPORTED_TEXT' || $status === 'REPORTED_PDF' || $status === 'REPORTED_BOTH') {
				$data['reported_at'] = $now;
				if (isset($existing->performed_at) && (empty($existing->performed_at) || $existing->performed_at === '0000-00-00 00:00:00')) {
					$data['performed_at'] = $now;
				}
				if (isset($existing->scheduled_at) && (empty($existing->scheduled_at) || $existing->scheduled_at === '0000-00-00 00:00:00')) {
					$data['scheduled_at'] = $now;
				}
				if (empty($existing->requested_at) || $existing->requested_at === '0000-00-00 00:00:00') {
					$data['requested_at'] = $now;
				}
			}
			if ($status === 'VERIFIED' && isset($existing->verified_at) && (empty($existing->verified_at) || $existing->verified_at === '0000-00-00 00:00:00')) {
				$data['verified_at'] = $now;
				if (empty($existing->reported_at) || $existing->reported_at === '0000-00-00 00:00:00') {
					$data['reported_at'] = $now;
				}
			}
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->update('iop_laboratory_workflow', $data);
			$this->_insert_order_state_audit($io_lab_id, 'LAB', $current_status, $status, $updated_by);
			$this->_sync_order_master_from_lab($io_lab_id, $status, false, date('Y-m-d H:i:s'));
			return true;
		}

		$data = array(
			'io_lab_id' => $io_lab_id,
			'status' => $status,
			'requested_at' => ($status === 'REQUESTED' || $status === 'IN_PROGRESS' || $status === 'SCHEDULED' || $status === 'PERFORMED' || $status === 'REPORTED_TEXT' || $status === 'REPORTED_PDF' || $status === 'REPORTED_BOTH' || $status === 'VERIFIED') ? $now : null,
			'scheduled_at' => ($status === 'SCHEDULED') ? $now : null,
			'performed_at' => ($status === 'PERFORMED' || $status === 'REPORTED_TEXT' || $status === 'REPORTED_PDF' || $status === 'REPORTED_BOTH') ? $now : null,
			'reported_at' => ($status === 'REPORTED_TEXT' || $status === 'REPORTED_PDF' || $status === 'REPORTED_BOTH' || $status === 'VERIFIED') ? $now : null,
			'verified_at' => ($status === 'VERIFIED') ? $now : null,
			'delivered_at' => null,
			'updated_at' => $now,
			'updated_by' => (string)$updated_by,
			'InActive' => 0
		);
		$this->db->insert('iop_laboratory_workflow', $data);
		$this->_insert_order_state_audit($io_lab_id, 'LAB', $current_status, $status, $updated_by);
		$this->_sync_order_master_from_lab($io_lab_id, $status, false, date('Y-m-d H:i:s'));
		return true;
	}

	private function _ssot_lab_cleared($io_lab_id){
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) return false;
		if (!$this->table_exists('billing_transactions')) return false;
		if (!$this->column_exists('billing_transactions', 'item_ref')) return false;
		$this->db->select('payer_type, payment_status, net_amount, paid_amount, balance_amount');
		$this->db->from('billing_transactions');
		$this->db->where('InActive', 0);
		$this->db->where('item_ref', 'io_lab_id:'.(int)$io_lab_id);
		$this->db->order_by('txn_id', 'DESC');
		$this->db->limit(1);
		$bt = $this->db->get()->row();
		if (!$bt) return false;
		$payer = isset($bt->payer_type) ? strtoupper(trim((string)$bt->payer_type)) : '';
		$ps = isset($bt->payment_status) ? strtoupper(trim((string)$bt->payment_status)) : '';
		$net = isset($bt->net_amount) ? (float)$bt->net_amount : 0.0;
		$paid = isset($bt->paid_amount) ? (float)$bt->paid_amount : 0.0;
		$bal = isset($bt->balance_amount) ? (float)$bt->balance_amount : 0.0;
		if ($payer !== '' && $payer !== 'CASH') return true;
		if (in_array($ps, array('PAID', 'PARTIAL', 'NHIS', 'WAIVED'), true)) return true;
		if ($net <= 0.009) return true;
		if ($paid > 0.009) return true;
		if ($bal <= 0.009) return true;
		return false;
	}

	private function _ensure_order_state_audit_table(){
		if ($this->table_exists('order_state_audit')) {
			return;
		}
		$this->db->query("CREATE TABLE IF NOT EXISTS `order_state_audit` (
			`id` INT AUTO_INCREMENT PRIMARY KEY,
			`order_id` INT,
			`module` VARCHAR(50),
			`from_state` VARCHAR(50),
			`to_state` VARCHAR(50),
			`changed_by` INT,
			`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
			KEY `idx_order` (`order_id`),
			KEY `idx_module` (`module`),
			KEY `idx_created` (`created_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	private function _insert_order_state_audit($order_id, $module, $from_state, $to_state, $changed_by){
		$this->_ensure_order_state_audit_table();
		if (!$this->table_exists('order_state_audit')) {
			return;
		}
		$this->db->insert('order_state_audit', array(
			'order_id' => (int)$order_id,
			'module' => (string)$module,
			'from_state' => (string)$from_state,
			'to_state' => (string)$to_state,
			'changed_by' => (int)$changed_by,
		));
	}

	private function _ensure_order_master_table(){
		$this->load->model('app/Order_master_model');
		if (isset($this->Order_master_model) && is_object($this->Order_master_model)) {
			$this->Order_master_model->ensure_table();
		}
	}

	private function _sync_order_master_from_lab($io_lab_id, $status, $is_backfill = false, $last_sync_at = ''){
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) return;
		if (!$this->table_exists('iop_laboratory')) return;

		$this->_ensure_order_master_table();
		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id))->row();
		if (!$lab) return;

		$visit_id = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$patient_no = $visit_id !== '' ? (string)$this->_lab_get_patient_no($visit_id) : '';
		$item_id = isset($lab->laboratory_id) ? (int)$lab->laboratory_id : 0;
		$category_id = isset($lab->category_id) ? (int)$lab->category_id : 0;
		$module = 'LAB';
		if ($category_id === 18) {
			$module = 'SONOGRAPHY';
		} else if ($category_id === 19 || $category_id === 20) {
			$module = 'RADIOLOGY';
		}

		$this->load->library('OrderStateMachine');
		$this->load->library('OrderMasterSync');

		$st = $this->orderstatemachine->normalize($status);
		$order = array(
			'patient_no' => $patient_no,
			'iop_id' => $visit_id,
			'status' => $st,
			'source_module' => $module,
			'module' => $module,
			'source_id' => $io_lab_id,
			'item_id' => $item_id > 0 ? $item_id : null,
			'is_backfill' => $is_backfill ? 1 : 0,
			'last_sync_at' => $last_sync_at !== '' ? (string)$last_sync_at : date('Y-m-d H:i:s'),
		);
		if (in_array($st, array('REQUESTED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED'), true)) {
			$this->ordermastersync->sync_lab_order($order);
		}

		$financial_state = 'PENDING';
		$billing_generated = null;
		if ($this->table_exists('iop_lab_billing')) {
			$b = $this->db->get_where('iop_lab_billing', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
			if ($b) {
				$billing_generated = isset($b->billing_generated) ? (int)$b->billing_generated : null;
				$financial_state = ($billing_generated === 0) ? 'NO_BILL_REQUIRED' : 'BILLED';
			}
		}
		$pay = $this->get_lab_payment_status($io_lab_id);
		$label = isset($pay['label']) ? strtoupper(trim((string)$pay['label'])) : '';
		$payment_state = 'UNPAID';
		if ($label === 'PAID') {
			$payment_state = 'PAID';
		} else if ($label === 'NO BILLING REQUIRED' || $label === 'NO BILL REQUIRED') {
			$payment_state = 'NO_BILL_REQUIRED';
		}
		if ($payment_state === 'PAID') {
			$financial_state = 'PAID';
		} else if ($payment_state === 'NO_BILL_REQUIRED') {
			$financial_state = 'NO_BILL_REQUIRED';
		}
		$this->ordermastersync->sync_billing($order, $financial_state);
		$this->ordermastersync->sync_payment($order, $payment_state);
	}

	public function count_order_master_backfill_candidates(){
		if (!$this->table_exists('iop_laboratory')) return 0;
		$this->db->from('iop_laboratory');
		$this->db->where('InActive', 0);
		return (int)$this->db->count_all_results();
	}

	public function backfill_order_master($limit = 500, $offset = 0){
		$limit = (int)$limit;
		$offset = (int)$offset;
		if ($limit <= 0) { $limit = 500; }
		if ($offset < 0) { $offset = 0; }
		if (!$this->table_exists('iop_laboratory')) {
			return array('processed' => 0);
		}
		$this->_ensure_order_master_table();
		if (!$this->table_exists('order_master')) {
			return array('processed' => 0);
		}
		$this->load->library('OrderStateMachine');

		$this->db->select('L.io_lab_id');
		if ($this->table_exists('iop_laboratory_workflow')) {
			$this->db->select("COALESCE(W.status,'REQUESTED') AS wf_status", false);
			$this->db->select("COALESCE(NULLIF(W.updated_at,'0000-00-00 00:00:00'), NULLIF(W.created_at,'0000-00-00 00:00:00')) AS wf_ts", false);
		} else {
			$this->db->select("'REQUESTED' AS wf_status", false);
			$this->db->select("NULL AS wf_ts", false);
		}
		$this->db->from('iop_laboratory L');
		if ($this->table_exists('iop_laboratory_workflow')) {
			$this->db->join('iop_laboratory_workflow W', 'W.io_lab_id = L.io_lab_id AND W.InActive = 0', 'left');
		}
		$this->db->where('L.InActive', 0);
		$this->db->order_by('L.io_lab_id', 'ASC');
		$this->db->limit($limit, $offset);
		$rows = $this->db->get()->result();
		$processed = 0;
		foreach ($rows as $r) {
			$io = isset($r->io_lab_id) ? (int)$r->io_lab_id : 0;
			if ($io <= 0) continue;
			$st = isset($r->wf_status) ? (string)$r->wf_status : 'REQUESTED';
			$this->_sync_order_master_from_lab($io, $st, true, (isset($r->wf_ts) ? (string)$r->wf_ts : ''));
			$processed++;
		}
		return array('processed' => (int)$processed);
	}

	public function mark_delivered_if_needed($io_lab_id, $viewer_user_id){
		$io_lab_id = (int)$io_lab_id;
		$viewer_user_id = (string)$viewer_user_id;
		if ($io_lab_id <= 0 || $viewer_user_id === '') {
			return false;
		}
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return false;
		}
		if (!$this->column_exists('iop_laboratory_workflow', 'delivered_at')) {
			return false;
		}
		$lab = $this->get_lab_record_with_patient($io_lab_id);
		if (!$lab) {
			return false;
		}
		if (!isset($lab->category_id) || (int)$lab->category_id !== 18) {
			return false;
		}
		$requesting = isset($lab->doctor) ? (string)$lab->doctor : '';
		$assigned = isset($lab->assigned_doctor_id) ? (string)$lab->assigned_doctor_id : '';
		if ($requesting !== $viewer_user_id && $assigned !== $viewer_user_id) {
			$this->install_imaging_tables();
			$meta = $this->get_sonography_request_meta($io_lab_id);
			$reqMeta = $meta && isset($meta->requesting_doctor_id) ? (string)$meta->requesting_doctor_id : '';
			if ($reqMeta !== $viewer_user_id) {
				return false;
			}
		}
		$wf = $this->get_workflow_status($io_lab_id);
		if (!$wf) {
			return false;
		}
		$st = isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		$isReported = ($st === 'REPORTED_TEXT' || $st === 'REPORTED_PDF' || $st === 'REPORTED_BOTH' || $st === 'REPORTED' || $st === 'VERIFIED');
		if (!$isReported) {
			return false;
		}
		$delivered = isset($wf->delivered_at) ? (string)$wf->delivered_at : '';
		if ($delivered !== '' && $delivered !== '0000-00-00 00:00:00') {
			return true;
		}
		$now = date('Y-m-d H:i:s');
		$this->db->where('io_lab_id', $io_lab_id);
		$this->db->update('iop_laboratory_workflow', array(
			'delivered_at' => $now,
			'updated_at' => $now,
			'updated_by' => $viewer_user_id
		));
		return true;
	}

	public function save_attachment_meta($io_lab_id, $lab_data, $uploaded_by, $full_path){
		if (!$this->table_exists('iop_laboratory_attachment_meta')) {
			return false;
		}
		$stored = isset($lab_data['file_name']) ? (string)$lab_data['file_name'] : '';
		if ($stored === '') {
			return false;
		}
		$client = isset($lab_data['client_name']) ? (string)$lab_data['client_name'] : '';
		$client = $client !== '' ? basename($client) : '';
		if (strlen($client) > 255) {
			$client = substr($client, -255);
		}
		$sha256 = null;
		if ($full_path && file_exists($full_path)) {
			$sha256 = hash_file('sha256', $full_path);
		}
		$data = array(
			'io_lab_id' => (int)$io_lab_id,
			'stored_filename' => $stored,
			'original_filename' => ($client !== '') ? $client : null,
			'mime_type' => isset($lab_data['file_type']) ? (string)$lab_data['file_type'] : null,
			'file_size_kb' => isset($lab_data['file_size']) ? (int)$lab_data['file_size'] : null,
			'sha256' => $sha256,
			'uploaded_by' => (string)$uploaded_by,
			'uploaded_at' => date('Y-m-d H:i:s'),
			'InActive' => 0
		);
		$this->db->insert('iop_laboratory_attachment_meta', $data);
		return true;
	}

	public function touch_in_progress_if_needed($io_lab_id, $updated_by){
		$io_lab_id = (int)$io_lab_id;
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return false;
		}
		$wf = $this->get_workflow_status($io_lab_id);
		if (!$wf) {
			return $this->upsert_workflow_status($io_lab_id, 'IN_PROGRESS', $updated_by);
		}
		$st = isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		if ($st === '' || $st === 'REQUESTED') {
			return $this->upsert_workflow_status($io_lab_id, 'IN_PROGRESS', $updated_by);
		}
		return true;
	}

	public function get_latest_attachment_meta($io_lab_id){
		if (!$this->table_exists('iop_laboratory_attachment_meta')) {
			return null;
		}
		$this->db->order_by('meta_id', 'DESC');
		$q = $this->db->get_where('iop_laboratory_attachment_meta', array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0), 1);
		return $q ? $q->row() : null;
	}

	public function get_workflow_status($io_lab_id){
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return null;
		}
		$q = $this->db->get_where('iop_laboratory_workflow', array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0));
		return $q ? $q->row() : null;
	}

	public function get_workflow_map($io_lab_ids){
		$map = array();
		if (!$this->table_exists('iop_laboratory_workflow')) {
			return $map;
		}
		if (!is_array($io_lab_ids) || count($io_lab_ids) === 0) {
			return $map;
		}
		$clean = array();
		foreach ($io_lab_ids as $id) {
			$clean[] = (int)$id;
		}
		$this->db->where_in('io_lab_id', $clean);
		$this->db->where('InActive', 0);
		$q = $this->db->get('iop_laboratory_workflow');
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(string)$r->io_lab_id] = $r;
		}
		return $map;
	}

	public function sonography_weekly_dashboard_stats($group_id = 18){
		$group_id = (int)$group_id;
		$out = array(
			'completed_this_week' => 0,
			'overdue' => 0,
			'cancelled_this_week' => 0,
			'scheduled_today' => 0
		);
		if (!$this->table_exists('iop_laboratory') || !$this->table_exists('iop_laboratory_workflow')) {
			return $out;
		}
		$joinMeta = $this->table_exists('iop_sonography_request_meta');
		$metaJoin = $joinMeta ? " LEFT JOIN iop_sonography_request_meta M ON M.io_lab_id = L.io_lab_id AND M.InActive = 0 " : "";
		$reqExpr = $joinMeta ? "COALESCE(W.requested_at, M.requested_at, L.dDate)" : "COALESCE(W.requested_at, L.dDate)";
		$reportedStatuses = "'REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED'";
		$sql =
			"SELECT\n".
			"  SUM(CASE WHEN UPPER(TRIM(W.status)) IN ({$reportedStatuses}) AND W.reported_at IS NOT NULL AND W.reported_at <> '0000-00-00 00:00:00' AND YEARWEEK(W.reported_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS completed_this_week,\n".
			"  SUM(CASE WHEN UPPER(TRIM(W.status)) = 'CANCELLED' AND W.cancelled_at IS NOT NULL AND W.cancelled_at <> '0000-00-00 00:00:00' AND YEARWEEK(W.cancelled_at, 1) = YEARWEEK(CURDATE(), 1) THEN 1 ELSE 0 END) AS cancelled_this_week,\n".
			"  SUM(CASE WHEN (W.status IS NULL OR UPPER(TRIM(W.status)) NOT IN ({$reportedStatuses})) AND (W.status IS NULL OR UPPER(TRIM(W.status)) <> 'CANCELLED') AND DATE({$reqExpr}) = CURDATE() THEN 1 ELSE 0 END) AS scheduled_today,\n".
			"  SUM(CASE WHEN (W.status IS NULL OR UPPER(TRIM(W.status)) NOT IN ({$reportedStatuses}) ) AND (W.status IS NULL OR UPPER(TRIM(W.status)) <> 'CANCELLED') AND DATE({$reqExpr}) < CURDATE() THEN 1 ELSE 0 END) AS overdue\n".
			"FROM iop_laboratory L\n".
			"LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0\n".
			$metaJoin.
			"WHERE L.InActive = 0 AND L.category_id = ?";
		$q = $this->db->query($sql, array($group_id));
		$row = $q ? $q->row_array() : null;
		if (!$row) {
			return $out;
		}
		$out['completed_this_week'] = isset($row['completed_this_week']) ? (int)$row['completed_this_week'] : 0;
		$out['cancelled_this_week'] = isset($row['cancelled_this_week']) ? (int)$row['cancelled_this_week'] : 0;
		$out['scheduled_today'] = isset($row['scheduled_today']) ? (int)$row['scheduled_today'] : 0;
		$out['overdue'] = isset($row['overdue']) ? (int)$row['overdue'] : 0;
		return $out;
	}

	public function get_lab_record_with_patient($io_lab_id){
		$this->db->select('L.*, PD.patient_no, PD.doctor_id AS assigned_doctor_id, COALESCE(NULLIF(TRIM(BP.particular_name),\'\'), NULLIF(TRIM(L.laboratory_text),\'\'), \'Unknown Test\') AS particular_name');
		$this->db->from('iop_laboratory L');
		$this->db->join('patient_details_iop PD', 'PD.IO_ID = L.iop_id', 'left');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id', 'left');
		$this->db->where('L.io_lab_id', (int)$io_lab_id);
		$q = $this->db->get();
		return $q ? $q->row() : null;
	}

	public function pending_sonography_requests($group_id, $limit = 10, $offset = 0, $filter = '', $search = ''){
		$group_id = (int)$group_id;
		$limit = (int)$limit;
		$offset = (int)$offset;
		$filter = strtolower(trim((string)$filter));
		$search = trim((string)$search);
		$joinMeta = $this->table_exists('iop_sonography_request_meta');
		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$metaJoin = $joinMeta ? " LEFT JOIN iop_sonography_request_meta M ON M.io_lab_id = L.io_lab_id AND M.InActive = 0 " : "";
		$wfJoin = $joinWf ? " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 " : "";

		$ssotSelect = '';
		$ssotJoin = '';
		if ($group_id === 18 && $this->table_exists('billing_transactions') && $this->column_exists('billing_transactions', 'item_ref') && $this->column_exists('billing_transactions', 'department')) {
			if ($this->table_exists('iop_sonography_charge') && $this->column_exists('iop_sonography_charge', 'rate_amount') && $this->column_exists('iop_sonography_charge', 'charge_id')) {
				$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
				if ($prev_debug !== null) { $this->db->db_debug = false; }
				try {
					$now = date('Y-m-d H:i:s');
					if ($this->table_exists('ghs_sonography_tests') && $this->column_exists('iop_sonography_charge', 'scan_item_id')) {
						$sqlFixCharge =
							"UPDATE iop_sonography_charge sc " .
							"JOIN ghs_sonography_tests g ON g.InActive = 0 AND g.test_id = sc.scan_item_id " .
							"SET sc.rate_amount = CASE WHEN (COALESCE(sc.nhis_flag,0) = 1 AND COALESCE(g.nhis_price,0) > 0) THEN g.nhis_price ELSE g.price END, " .
							"sc.item_name = CASE WHEN TRIM(COALESCE(sc.item_name,'')) = '' THEN g.test_name ELSE sc.item_name END " .
							"WHERE sc.InActive = 0 AND COALESCE(sc.rate_amount,0) <= 0 AND COALESCE(g.price,0) > 0";
						$this->db->query($sqlFixCharge);
					}
					$sqlFix =
						"UPDATE billing_transactions bt " .
						"JOIN iop_sonography_charge sc ON sc.InActive = 0 AND bt.item_ref = CONCAT('sono_charge_id:', sc.charge_id) " .
						"SET bt.unit_price = sc.rate_amount, " .
						"bt.gross_amount = (sc.rate_amount * COALESCE(bt.quantity,1)), " .
						"bt.total_amount = (sc.rate_amount * COALESCE(bt.quantity,1)), " .
						"bt.net_amount = (sc.rate_amount * COALESCE(bt.quantity,1)), " .
						"bt.balance_amount = (sc.rate_amount * COALESCE(bt.quantity,1)), " .
						"bt.updated_at = " . $this->db->escape($now) . " " .
						"WHERE bt.InActive = 0 AND bt.department = 'IMAGING' " .
						"AND sc.rate_amount > 0 " .
						"AND (COALESCE(bt.unit_price,0) <= 0 OR COALESCE(bt.net_amount,0) <= 0) " .
						"AND (bt.payment_status IS NULL OR UPPER(TRIM(bt.payment_status)) NOT IN ('PAID','WAIVED','NHIS'))";
					$this->db->query($sqlFix);
				} catch (\Throwable $e) {
				}
				if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }
			}

			$hasPay = $this->column_exists('billing_transactions', 'payment_status');
			$hasPayer = $this->column_exists('billing_transactions', 'payer_type');
			$hasNet = $this->column_exists('billing_transactions', 'net_amount');
			$hasAuthStatus = $this->column_exists('billing_transactions', 'authorization_status') || $this->column_exists('billing_transactions', 'auth_status');
			$hasAuthCode = $this->column_exists('billing_transactions', 'authorization_code') || $this->column_exists('billing_transactions', 'auth_code');

			$enforceAuth = false;
			try {
				$env = getenv('enforce_insurance_auth');
				if ($env === false) { $env = getenv('ENFORCE_INSURANCE_AUTH'); }
				if ($env !== false) {
					$tmp = strtolower(trim((string)$env));
					$enforceAuth = in_array($tmp, array('1', 'true', 'yes', 'on'), true);
				}
				if (!$enforceAuth && $this->table_exists('system_option') && $this->column_exists('system_option', 'cCode') && $this->column_exists('system_option', 'cValue')) {
					$row = $this->db->get_where('system_option', array('cCode' => 'enforce_insurance_auth', 'InActive' => 0), 1)->row();
					if (!$row) {
						$row = $this->db->get_where('system_option', array('cCode' => 'ENFORCE_INSURANCE_AUTH', 'InActive' => 0), 1)->row();
					}
					if ($row && isset($row->cValue)) {
						$tmp = strtolower(trim((string)$row->cValue));
						$enforceAuth = in_array($tmp, array('1', 'true', 'yes', 'on'), true);
					}
				}
				if (!$enforceAuth && isset($this->config)) {
					$tmp = $this->config->item('enforce_insurance_auth');
					if ($tmp === null) { $tmp = $this->config->item('ENFORCE_INSURANCE_AUTH'); }
					if ($tmp !== null) {
						if (is_bool($tmp)) {
							$enforceAuth = $tmp;
						} else {
							$val = filter_var($tmp, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
							if ($val !== null) { $enforceAuth = $val; }
						}
					}
				}
			} catch (\Throwable $e) {
				$enforceAuth = false;
			}

			$payExpr = $hasPay ? "UPPER(TRIM(bt.payment_status))" : "''";
			$payerExpr = $hasPayer ? "UPPER(TRIM(bt.payer_type))" : "''";
			$netExpr = $hasNet ? "COALESCE(bt.net_amount,0)" : "0";

			$authOkExpr = '0';
			if ($enforceAuth && ($hasAuthStatus || $hasAuthCode)) {
				$parts = array();
				if ($this->column_exists('billing_transactions', 'authorization_status')) {
					$parts[] = "UPPER(TRIM(bt.authorization_status)) = 'APPROVED'";
				}
				if ($this->column_exists('billing_transactions', 'auth_status')) {
					$parts[] = "UPPER(TRIM(bt.auth_status)) = 'APPROVED'";
				}
				if ($this->column_exists('billing_transactions', 'authorization_code')) {
					$parts[] = "TRIM(COALESCE(bt.authorization_code,'')) <> ''";
				}
				if ($this->column_exists('billing_transactions', 'auth_code')) {
					$parts[] = "TRIM(COALESCE(bt.auth_code,'')) <> ''";
				}
				if (!empty($parts)) {
					$authOkExpr = '(' . implode(' OR ', $parts) . ')';
				}
			}
			$needsAuthExpr = $enforceAuth ? "(({$payerExpr} IN ('INSURANCE','COMPANY')) AND NOT({$authOkExpr}))" : '0';

			$whereNotCancelled2 = $joinWf ? " AND (W2.status IS NULL OR UPPER(TRIM(W2.status)) <> 'CANCELLED') " : "";
			$wfJoin2 = $joinWf ? " LEFT JOIN iop_laboratory_workflow W2 ON W2.io_lab_id = L2.io_lab_id AND W2.InActive = 0 " : "";

			$ssotJoin =
				" LEFT JOIN (".
				"SELECT L2.iop_id, ".
				"COUNT(*) AS ssot_total, ".
				"SUM(CASE WHEN bt.item_ref IS NULL THEN 1 ELSE 0 END) AS ssot_missing, ".
				"SUM(CASE WHEN {$payExpr} = 'PARTIAL' THEN 1 ELSE 0 END) AS ssot_partial, ".
				"SUM(CASE WHEN {$payerExpr} = 'CASH' AND {$netExpr} <= 0 AND {$payExpr} NOT IN ('PAID','WAIVED','NHIS') THEN 1 ELSE 0 END) AS ssot_zero_net, ".
				"SUM(CASE WHEN {$needsAuthExpr} THEN 1 ELSE 0 END) AS ssot_auth_blocked, ".
				"SUM(CASE WHEN ({$payerExpr} = 'NHIS' OR {$payExpr} = 'NHIS') THEN 1 ELSE 0 END) AS ssot_nhis, ".
				"SUM(CASE WHEN {$payExpr} IN ('PAID','WAIVED') THEN 1 ELSE 0 END) AS ssot_paid, ".
				"SUM(CASE WHEN bt.item_ref IS NOT NULL AND ({$payerExpr} <> '' AND {$payerExpr} <> 'CASH' AND NOT({$needsAuthExpr})) THEN 1 ELSE 0 END) AS ssot_covered, ".
				"SUM(CASE WHEN bt.item_ref IS NOT NULL AND ({$payExpr} IN ('PAID','WAIVED','NHIS') OR {$payerExpr} = 'NHIS' OR ({$payerExpr} <> '' AND {$payerExpr} <> 'CASH' AND NOT({$needsAuthExpr}))) THEN 1 ELSE 0 END) AS ssot_cleared, ".
				"SUM(CASE WHEN bt.item_ref IS NOT NULL AND NOT({$payExpr} IN ('PAID','WAIVED','NHIS') OR {$payerExpr} = 'NHIS' OR ({$payerExpr} <> '' AND {$payerExpr} <> 'CASH' AND NOT({$needsAuthExpr}))) THEN 1 ELSE 0 END) AS ssot_uncleared ".
				"FROM iop_laboratory L2 ".
				$wfJoin2.
				"LEFT JOIN iop_sonography_charge C2 ON C2.io_lab_id = L2.io_lab_id AND C2.InActive = 0 ".
				"LEFT JOIN billing_transactions bt ON bt.InActive = 0 AND bt.department = 'IMAGING' AND bt.item_ref = CONCAT('sono_charge_id:', COALESCE(C2.charge_id, L2.io_lab_id)) ".
				"WHERE (L2.result = '' OR L2.result IS NULL) AND L2.InActive = 0 AND L2.category_id = " . $this->db->escape((string)$group_id) .
				$whereNotCancelled2.
				" GROUP BY L2.iop_id".
				") SSOT ON SSOT.iop_id = L.iop_id ";

			$ssotSelect = ", ".
				"MAX(SSOT.ssot_total) AS ssot_total, ".
				"MAX(SSOT.ssot_missing) AS ssot_missing, ".
				"MAX(SSOT.ssot_partial) AS ssot_partial, ".
				"MAX(SSOT.ssot_zero_net) AS ssot_zero_net, ".
				"MAX(SSOT.ssot_auth_blocked) AS ssot_auth_blocked, ".
				"MAX(SSOT.ssot_nhis) AS ssot_nhis, ".
				"MAX(SSOT.ssot_paid) AS ssot_paid, ".
				"MAX(SSOT.ssot_covered) AS ssot_covered, ".
				"MAX(SSOT.ssot_cleared) AS ssot_cleared, ".
				"MAX(SSOT.ssot_uncleared) AS ssot_uncleared";
		}
		$whereSearch = '';
		if ($search !== '') {
			$like = $this->db->escape('%'.$search.'%');
			$whereSearch = " AND (PD.IO_ID LIKE {$like} OR PPI.patient_no LIKE {$like} OR CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) LIKE {$like}) ";
		}
		$whereNotCancelled = $joinWf ? " AND (W.status IS NULL OR UPPER(TRIM(W.status)) <> 'CANCELLED') " : "";
		$coalesceParts = array();
		if ($joinWf) $coalesceParts[] = 'W.requested_at';
		if ($joinMeta) $coalesceParts[] = 'M.requested_at';
		$coalesceParts[] = 'L.dDate';
		$reqExpr = "MIN(COALESCE(".implode(', ', $coalesceParts)."))";
		$urgExpr = $joinMeta ? "MAX(CASE UPPER(COALESCE(M.urgency,'')) WHEN 'STAT' THEN 3 WHEN 'URGENT' THEN 2 WHEN 'ROUTINE' THEN 1 ELSE 0 END)" : "0";
		$having = '';
		if ($filter === 'today') {
			$having = " HAVING DATE({$reqExpr}) = CURDATE() ";
		} else if ($filter === 'overdue') {
			$having = " HAVING DATE({$reqExpr}) < CURDATE() ";
		}
		$sql = "SELECT L.iop_id, CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) AS patient_name, PPI.patient_no, PPI.birthday, ".
			"MIN(L.dDate) AS dDate, {$reqExpr} AS requested_at, {$urgExpr} AS urgency_rank{$ssotSelect} ".
			"FROM iop_laboratory L ".
			"JOIN patient_details_iop PD ON PD.IO_ID=L.iop_id ".
			"JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no ".
			$metaJoin.
			$wfJoin.
			$ssotJoin.
			"WHERE (L.result = '' OR L.result IS NULL) AND L.InActive = 0 AND L.category_id = ".$this->db->escape((string)$group_id).
			$whereNotCancelled.
			$whereSearch.
			" GROUP BY L.iop_id, patient_name, PPI.patient_no, PPI.birthday ".
			$having.
			" ORDER BY urgency_rank DESC, requested_at ASC LIMIT {$offset}, {$limit}";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Get completed sonography scans with search functionality
	 */
	public function completed_sonography_scans($group_id, $limit = 10, $offset = 0, $search = '', $date_from = '', $date_to = ''){
		$group_id = (int)$group_id;
		$limit = (int)$limit;
		$offset = (int)$offset;
		$search = trim((string)$search);
		$date_from = trim((string)$date_from);
		$date_to = trim((string)$date_to);
		
		$joinMeta = $this->table_exists('iop_sonography_request_meta');
		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$joinSonoItems = $this->table_exists('sonography_items');
		$hasResultFile = $this->column_exists('iop_laboratory', 'result_file');
		
		$metaJoin = $joinMeta ? " LEFT JOIN iop_sonography_request_meta M ON M.io_lab_id = L.io_lab_id AND M.InActive = 0 " : "";
		$wfJoin = $joinWf ? " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 " : "";
		$sonoItemsJoin = $joinSonoItems ? " LEFT JOIN sonography_items SI ON SI.item_id = L.laboratory_id " : "";
		
		$whereSearch = '';
		if ($search !== '') {
			$like = $this->db->escape('%'.$search.'%');
			$whereSearch = " AND (PD.IO_ID LIKE {$like} OR PPI.patient_no LIKE {$like} OR CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) LIKE {$like} OR L.result LIKE {$like}) ";
		}
		
		$whereDate = '';
		if ($date_from !== '') {
			$whereDate .= " AND DATE(L.dDate) >= " . $this->db->escape($date_from) . " ";
		}
		if ($date_to !== '') {
			$whereDate .= " AND DATE(L.dDate) <= " . $this->db->escape($date_to) . " ";
		}
		
		$resultFileCol = $hasResultFile ? "L.result_file" : "NULL AS result_file";
		
		$wfCols = "NULL AS wf_status, NULL AS reported_at, NULL AS reported_by";
		if ($joinWf) {
			$wfColsList = array('status', 'reported_at', 'reported_by');
			$wfColsSelect = array();
			foreach ($wfColsList as $col) {
				if ($this->column_exists('iop_laboratory_workflow', $col)) {
					$wfColsSelect[] = "W.{$col}" . ($col === 'status' ? " AS wf_status" : "");
				} else {
					$wfColsSelect[] = "NULL AS " . ($col === 'status' ? "wf_status" : $col);
				}
			}
			$wfCols = implode(", ", $wfColsSelect);
		}
		
		$sql = "SELECT L.io_lab_id, L.iop_id, L.laboratory_id, L.laboratory_text, L.result, L.findings, L.dDate, {$resultFileCol},
			CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) AS patient_name, 
			PPI.patient_no, PPI.birthday,
			COALESCE(NULLIF(TRIM(BP.particular_name),''), NULLIF(TRIM(L.laboratory_text),''), 'Unknown Test') AS particular_name,
			".($joinSonoItems ? "SI.item_name AS sono_item_name," : "NULL AS sono_item_name,")."
			".($joinMeta ? "M.clinical_question, M.urgency, M.requested_at, M.encounter_type," : "NULL AS clinical_question, NULL AS urgency, NULL AS requested_at, NULL AS encounter_type,")."
			{$wfCols}
			FROM iop_laboratory L 
			JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id 
			JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no 
			LEFT JOIN bill_particular BP ON BP.particular_id = L.laboratory_id
			{$metaJoin}
			{$wfJoin}
			{$sonoItemsJoin}
			WHERE (L.result IS NOT NULL AND L.result <> '') 
			AND L.InActive = 0 
			AND L.category_id = ".$this->db->escape((string)$group_id)."
			{$whereSearch}
			{$whereDate}
			ORDER BY L.dDate DESC, L.io_lab_id DESC
			LIMIT {$offset}, {$limit}";
		
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Count completed sonography scans
	 */
	public function count_completed_sonography_scans($group_id, $search = '', $date_from = '', $date_to = ''){
		$group_id = (int)$group_id;
		$search = trim((string)$search);
		$date_from = trim((string)$date_from);
		$date_to = trim((string)$date_to);
		
		$whereSearch = '';
		if ($search !== '') {
			$like = $this->db->escape('%'.$search.'%');
			$whereSearch = " AND (PD.IO_ID LIKE {$like} OR PPI.patient_no LIKE {$like} OR CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) LIKE {$like} OR L.result LIKE {$like}) ";
		}
		
		$whereDate = '';
		if ($date_from !== '') {
			$whereDate .= " AND DATE(L.dDate) >= " . $this->db->escape($date_from) . " ";
		}
		if ($date_to !== '') {
			$whereDate .= " AND DATE(L.dDate) <= " . $this->db->escape($date_to) . " ";
		}
		
		$sql = "SELECT COUNT(*) AS c FROM iop_laboratory L 
			JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id 
			JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no 
			WHERE (L.result IS NOT NULL AND L.result <> '') 
			AND L.InActive = 0 
			AND L.category_id = ".$this->db->escape((string)$group_id)."
			{$whereSearch}
			{$whereDate}";
		
		$q = $this->db->query($sql);
		$row = $q ? $q->row_array() : null;
		return $row && isset($row['c']) ? (int)$row['c'] : 0;
	}

	public function count_pending_sonography_requests($group_id, $filter = '', $search = ''){
		$group_id = (int)$group_id;
		$filter = strtolower(trim((string)$filter));
		$search = trim((string)$search);
		$joinMeta = $this->table_exists('iop_sonography_request_meta');
		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$metaJoin = $joinMeta ? " LEFT JOIN iop_sonography_request_meta M ON M.io_lab_id = L.io_lab_id AND M.InActive = 0 " : "";
		$wfJoin = $joinWf ? " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 " : "";
		$whereSearch = '';
		if ($search !== '') {
			$like = $this->db->escape('%'.$search.'%');
			$whereSearch = " AND (PD.IO_ID LIKE {$like} OR PPI.patient_no LIKE {$like} OR CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) LIKE {$like}) ";
		}
		$whereNotCancelled = $joinWf ? " AND (W.status IS NULL OR UPPER(TRIM(W.status)) <> 'CANCELLED') " : "";
		$coalesceParts2 = array();
		if ($joinWf) $coalesceParts2[] = 'W.requested_at';
		if ($joinMeta) $coalesceParts2[] = 'M.requested_at';
		$coalesceParts2[] = 'L.dDate';
		$reqExpr = "MIN(COALESCE(".implode(', ', $coalesceParts2)."))";
		$having = '';
		if ($filter === 'today') {
			$having = " HAVING DATE({$reqExpr}) = CURDATE() ";
		} else if ($filter === 'overdue') {
			$having = " HAVING DATE({$reqExpr}) < CURDATE() ";
		}
		$sql = "SELECT COUNT(*) AS c FROM (".
			"SELECT L.iop_id, {$reqExpr} AS requested_at ".
			"FROM iop_laboratory L ".
			"JOIN patient_details_iop PD ON PD.IO_ID=L.iop_id ".
			"JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no ".
			$metaJoin.
			$wfJoin.
			"WHERE (L.result = '' OR L.result IS NULL) AND L.InActive = 0 AND L.category_id = ".$this->db->escape((string)$group_id).
			$whereNotCancelled.
			$whereSearch.
			" GROUP BY L.iop_id ".
			$having.
			") Z";
		$q = $this->db->query($sql);
		$row = $q ? $q->row_array() : null;
		if (!$row || !isset($row['c'])) {
			return 0;
		}
		return (int)$row['c'];
	}

	public function get_sonography_requests($group_id, $iop_id, $include_reported = false){
		$group_id = (int)$group_id;
		$iop_id = (string)$iop_id;
		$whereResult = $include_reported ? "" : " AND l.result = '' ";
		$joinSonoMeta = $this->table_exists('iop_sonography_request_meta');
		$joinSonoItems = $this->table_exists('sonography_items');
		$joinGhsSono = $this->table_exists('ghs_sonography_tests');
		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$sql = "SELECT l.*,CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name,".
			"COALESCE(NULLIF(TRIM(l.laboratory_text),''), NULLIF(TRIM(gst.test_name),''), NULLIF(TRIM(bp.particular_name),''), NULLIF(TRIM(si.item_name),''), 'Unknown Test') AS particular_name,".
			"COALESCE(NULLIF(TRIM(gst.test_name),''), NULLIF(TRIM(si.item_name),'')) AS sono_item_name,".
			($joinSonoMeta ? "m.meta_id AS sono_meta_id, m.scan_item_id AS sono_scan_item_id, m.clinical_question AS clinical_question, m.encounter_type AS encounter_type, m.urgency AS urgency, m.requested_at AS requested_at," : "NULL AS sono_meta_id, NULL AS sono_scan_item_id, NULL AS clinical_question, NULL AS encounter_type, NULL AS urgency, NULL AS requested_at,").
			($joinWf ? "w.status AS wf_status, w.cancel_reason AS cancel_reason," : "NULL AS wf_status, NULL AS cancel_reason,").
			" ppi.patient_no,ppi.birthday,l.laboratory_text ".
			"FROM iop_laboratory l ".
			"JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id ".
			"JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no ".
			"LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id AND bp.InActive = 0 ".
			($joinSonoMeta ? "LEFT JOIN iop_sonography_request_meta m ON m.io_lab_id = l.io_lab_id AND m.InActive = 0 " : "").
			($joinGhsSono ? "LEFT JOIN ghs_sonography_tests gst ON gst.test_id = COALESCE(m.scan_item_id, l.laboratory_id) AND gst.InActive = 0 " : "LEFT JOIN (SELECT NULL AS test_id, NULL AS test_name) gst ON 1=0 ").
			($joinSonoItems ? "LEFT JOIN sonography_items si ON si.item_id = COALESCE(m.scan_item_id, l.laboratory_id) AND si.InActive = 0 " : "LEFT JOIN (SELECT NULL AS item_id, NULL AS item_name) si ON 1=0 ").
			($joinWf ? "LEFT JOIN iop_laboratory_workflow w ON w.io_lab_id = l.io_lab_id AND w.InActive = 0 " : "").
			"WHERE l.iop_id=".$this->db->escape($iop_id)." and l.InActive = 0 AND l.category_id = '".(string)$group_id."'".$whereResult;
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	public function pending_radiology_requests_grouped($radiology_category, $radiology_test_category, $limit = 10, $offset = 0, $filter = '', $search = ''){
		$radiology_category = (int)$radiology_category;
		$radiology_test_category = trim((string)$radiology_test_category);
		$limit = (int)$limit;
		$offset = (int)$offset;
		$filter = strtolower(trim((string)$filter));
		$search = trim((string)$search);
		if (!$this->table_exists('iop_laboratory') || !$this->table_exists('radiology_test_master')) {
			return array();
		}
		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$wfJoin = $joinWf ? " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 " : "";
		$whereSearch = '';
		if ($search !== '') {
			$like = $this->db->escape('%'.$search.'%');
			$whereSearch = " AND (L.iop_id LIKE {$like} OR PPI.patient_no LIKE {$like} OR CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) LIKE {$like}) ";
		}
		$urgExpr = "0";
		$reqExpr = $joinWf ? "MIN(COALESCE(W.requested_at, L.dDate))" : "MIN(L.dDate)";
		$having = '';
		if ($filter === 'today') {
			$having = " HAVING DATE({$reqExpr}) = CURDATE() ";
		} else if ($filter === 'overdue') {
			$having = " HAVING DATE({$reqExpr}) < CURDATE() ";
		}
		$whereNotCancelled = $joinWf ? " AND (W.status IS NULL OR UPPER(TRIM(W.status)) <> 'CANCELLED') " : "";
		$sql = "SELECT L.iop_id, CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) AS patient_name, PPI.patient_no, PPI.birthday, " .
			"{$reqExpr} AS requested_at, {$urgExpr} AS urgency_rank, MIN(L.dDate) AS dDate " .
			"FROM iop_laboratory L " .
			"JOIN radiology_test_master T ON T.id = L.laboratory_id AND T.InActive = 0 " .
			"JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0 " .
			"JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no " .
			$wfJoin .
			"WHERE (L.result = '' OR L.result IS NULL) AND L.InActive = 0 AND L.category_id = " . $this->db->escape((string)$radiology_category) .
			$whereNotCancelled .
			"AND T.category = " . $this->db->escape($radiology_test_category) . " " .
			$whereSearch .
			" GROUP BY L.iop_id, patient_name, PPI.patient_no, PPI.birthday " .
			$having .
			" ORDER BY urgency_rank DESC, requested_at ASC LIMIT {$offset}, {$limit}";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	public function count_pending_radiology_requests_grouped($radiology_category, $radiology_test_category, $filter = '', $search = ''){
		$radiology_category = (int)$radiology_category;
		$radiology_test_category = trim((string)$radiology_test_category);
		$filter = strtolower(trim((string)$filter));
		$search = trim((string)$search);
		if (!$this->table_exists('iop_laboratory') || !$this->table_exists('radiology_test_master')) {
			return 0;
		}
		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$wfJoin = $joinWf ? " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 " : "";
		$whereSearch = '';
		if ($search !== '') {
			$like = $this->db->escape('%'.$search.'%');
			$whereSearch = " AND (L.iop_id LIKE {$like} OR PPI.patient_no LIKE {$like} OR CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) LIKE {$like}) ";
		}
		$reqExpr = $joinWf ? "MIN(COALESCE(W.requested_at, L.dDate))" : "MIN(L.dDate)";
		$having = '';
		if ($filter === 'today') {
			$having = " HAVING DATE({$reqExpr}) = CURDATE() ";
		} else if ($filter === 'overdue') {
			$having = " HAVING DATE({$reqExpr}) < CURDATE() ";
		}
		$whereNotCancelled = $joinWf ? " AND (W.status IS NULL OR UPPER(TRIM(W.status)) <> 'CANCELLED') " : "";
		$sql = "SELECT COUNT(*) AS c FROM (".
			"SELECT L.iop_id, {$reqExpr} AS requested_at " .
			"FROM iop_laboratory L " .
			"JOIN radiology_test_master T ON T.id = L.laboratory_id AND T.InActive = 0 " .
			"JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0 " .
			"JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no " .
			$wfJoin .
			"WHERE (L.result = '' OR L.result IS NULL) AND L.InActive = 0 AND L.category_id = " . $this->db->escape((string)$radiology_category) .
			$whereNotCancelled .
			"AND T.category = " . $this->db->escape($radiology_test_category) . " " .
			$whereSearch .
			" GROUP BY L.iop_id " .
			$having .
			") Z";
		$q = $this->db->query($sql);
		$row = $q ? $q->row_array() : null;
		return ($row && isset($row['c'])) ? (int)$row['c'] : 0;
	}

	public function get_ipd_sonography_charge_map($io_lab_ids){
		if (!$this->table_exists('iop_sonography_charge')) {
			return array();
		}
		if (!$io_lab_ids || !is_array($io_lab_ids) || count($io_lab_ids) === 0) {
			return array();
		}
		$clean = array();
		foreach ($io_lab_ids as $id) {
			$clean[] = (int)$id;
		}
		$this->db->where_in('io_lab_id', $clean);
		$this->db->where('InActive', 0);
		$q = $this->db->get('iop_sonography_charge');
		$rows = $q ? $q->result() : array();
		$map = array();
		foreach ($rows as $r) {
			$map[(string)$r->io_lab_id] = $r;
		}
		return $map;
	}

	/**
	 * Return the category_id used for sonography/imaging in bill_group_name.
	 * Falls back to 18 if the system_parameters or bill_group_name lookup fails.
	 */
	public function get_sonography_category_id(){
		static $cached = null;
		if ($cached !== null) return $cached;
		if ($this->table_exists('bill_group_name')) {
			$r = $this->db->like('group_name', 'sonog', 'after')->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
			if ($r && isset($r->group_id) && (int)$r->group_id > 0) {
				$cached = (int)$r->group_id;
				return $cached;
			}
		}
		$cached = 18;
		return $cached;
	}

	/**
	 * Return the category_id used for radiology in bill_group_name.
	 * Tries X-RAYS (16) first, then looks for any 'RADIOLOGY' group.
	 * Creates one if none exist. Cached after first call.
	 */
	public function get_radiology_category_id(){
		static $cached = null;
		if ($cached !== null) return $cached;
		if ($this->table_exists('bill_group_name')) {
			// Check for existing radiology-ish groups
			$r = $this->db->where('group_id', 16)->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
			if ($r && isset($r->group_id)) {
				$cached = (int)$r->group_id;
				return $cached;
			}
			$r = $this->db->like('group_name', 'radiol', 'after')->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
			if ($r && isset($r->group_id) && (int)$r->group_id > 0) {
				$cached = (int)$r->group_id;
				return $cached;
			}
			$r = $this->db->like('group_name', 'x-ray', 'both')->where('InActive', 0)->limit(1)->get('bill_group_name')->row();
			if ($r && isset($r->group_id) && (int)$r->group_id > 0) {
				$cached = (int)$r->group_id;
				return $cached;
			}
		}
		$cached = 16;
		return $cached;
	}

	public function has_pending_results($iop_id){
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '' || !$this->table_exists('iop_laboratory')) {
			return false;
		}

		$sono_cat = (int)$this->get_sonography_category_id();
		$radio_cat = (int)$this->get_radiology_category_id();
		$wf_join = '';
		$wf_condition = '';
		if ($this->table_exists('iop_laboratory_workflow')) {
			$wf_join = "LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0";
			$wf_condition = " OR (W.status IS NOT NULL AND UPPER(TRIM(W.status)) NOT IN ('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED','CANCELLED'))";
		}

		$sql = "SELECT 1
			FROM iop_laboratory L
			{$wf_join}
			WHERE L.iop_id = ? AND L.InActive = 0
			  AND (L.category_id IS NULL OR (L.category_id != ? AND L.category_id != ?))
			  AND ((L.result IS NULL OR TRIM(L.result) = ''){$wf_condition})
			LIMIT 1";
		$q = $this->db->query($sql, array($iop_id, $sono_cat, $radio_cat));
		return ($q && $q->num_rows() > 0);
	}

	public function has_pending_imaging($iop_id){
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') {
			return false;
		}

		$completed = "('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED','CANCELLED')";
		if ($this->table_exists('iop_laboratory')) {
			$sono_cat = (int)$this->get_sonography_category_id();
			$radio_cat = (int)$this->get_radiology_category_id();
			$wf_join = '';
			$wf_condition = '';
			if ($this->table_exists('iop_laboratory_workflow')) {
				$wf_join = "LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0";
				$wf_condition = " OR (W.status IS NULL OR UPPER(TRIM(W.status)) NOT IN {$completed})";
			}
			$sql = "SELECT 1
				FROM iop_laboratory L
				{$wf_join}
				WHERE L.iop_id = ? AND L.InActive = 0
				  AND L.category_id IN (?, ?)
				  AND ((L.result IS NULL OR TRIM(L.result) = ''){$wf_condition})
				LIMIT 1";
			$q = $this->db->query($sql, array($iop_id, $sono_cat, $radio_cat));
			if ($q && $q->num_rows() > 0) {
				return true;
			}
		}

		if ($this->table_exists('radiology_orders')) {
			$q = $this->db->query(
				"SELECT 1
				 FROM radiology_orders
				 WHERE iop_id = ? AND InActive = 0
				   AND LOWER(TRIM(COALESCE(status, ''))) IN ('pending','in_progress')
				 LIMIT 1",
				array($iop_id)
			);
			if ($q && $q->num_rows() > 0) {
				return true;
			}
		}

		return false;
	}

	public function pending_labs()
	{
		$sono_cat = (int)$this->get_sonography_category_id();
		$radio_cat = (int)$this->get_radiology_category_id();
		$query = $this->db->query(
			"SELECT l.*,CONVERT(CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_name,CONVERT(COALESCE(NULLIF(TRIM(l.laboratory_text),''),NULLIF(TRIM(bp.particular_name),''),'Unknown Test') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS particular_name, CONVERT(ppi.patient_no USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_no,ppi.birthday,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id AND (l.category_id IS NULL OR (l.category_id != {$sono_cat} AND l.category_id != {$radio_cat})) WHERE l.result = '' AND l.InActive = 0 AND (l.category_id IS NULL OR (l.category_id != ? AND l.category_id != ?))
			UNION
			SELECT l.*,CONVERT(CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_name,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS bparticular_name, CONVERT(ppi.patient_no USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_no,ppi.birthday,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no WHERE l.result = '' AND l.category_id = 'others' AND l.InActive = 0",
			array($sono_cat, $radio_cat)
		);
		return $query ? $query->result() : array();
	}

	public function pending_lab_requests($limit = 10, $offset = 0)
	{
		$limit  = (int)$limit;
		$offset = (int)$offset;
		$sono_cat = (int)$this->get_sonography_category_id();
		$radio_cat = (int)$this->get_radiology_category_id();
		$urgCol = $this->column_exists('iop_laboratory', 'is_urgent') ? 'IFNULL(l.is_urgent,0) DESC,' : '';
		$query = $this->db->query(
			"SELECT DISTINCT l.iop_id, CONCAT(ppi.lastname,' ',ppi.firstname,' ',COALESCE(ppi.middlename,'')) AS patient_name, ppi.patient_no, ppi.birthday, l.dDate
			FROM iop_laboratory l
			JOIN patient_details_iop pd ON pd.IO_ID = l.iop_id AND pd.InActive = 0
			JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
			WHERE (l.result = '' OR l.result IS NULL) AND l.InActive = 0
			AND (l.category_id IS NULL OR (l.category_id != ? AND l.category_id != ?))
			ORDER BY {$urgCol} l.dDate DESC
			LIMIT ?, ?",
			array($sono_cat, $radio_cat, $offset, $limit)
		);
		return $query ? $query->result() : array();
	}

	public function getAll($limit = 10, $offset = 0, $iop_id = null)
	{
		$limit  = (int)$limit;
		$offset = (int)$offset;
		$iop_id = (string)$iop_id;
		$sono_cat = (int)$this->get_sonography_category_id();
		$radio_cat = (int)$this->get_radiology_category_id();
		$query = $this->db->query(
			"SELECT l.*,CONVERT(CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_name,CONVERT(COALESCE(NULLIF(TRIM(l.laboratory_text),''),NULLIF(TRIM(bp.particular_name),''),'Unknown Test') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS particular_name, CONVERT(ppi.patient_no USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_no,ppi.birthday,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id AND (l.category_id IS NULL OR (l.category_id != {$sono_cat} AND l.category_id != {$radio_cat})) WHERE l.result = '' AND l.iop_id=? AND l.InActive = 0 AND (l.category_id IS NULL OR (l.category_id != ? AND l.category_id != ?))
			UNION
			SELECT l.*,CONVERT(CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_name,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS bparticular_name, CONVERT(ppi.patient_no USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_no,ppi.birthday,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no WHERE l.result = '' AND l.category_id = 'others' AND l.iop_id=? AND l.InActive = 0
			LIMIT ?, ?",
			array($iop_id, $sono_cat, $radio_cat, $iop_id, $offset, $limit)
		);
		return $query ? $query->result() : array();
	}


	public function getAllzz($limit = 10, $offset = 0, $iop_id = null)
	{
		$query = $this->db->query("SELECT l.*,CONVERT(CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_name,CONVERT(COALESCE(NULLIF(TRIM(bp.particular_name),''),NULLIF(TRIM(l.laboratory_text),''),'Unknown Test') USING utf8mb4) COLLATE utf8mb4_unicode_ci AS particular_name, CONVERT(ppi.patient_no USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_no,ppi.birthday,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id WHERE l.result = '' and l.iop_id='$iop_id' and l.InActive = 0
		UNION
		SELECT l.*,CONVERT(CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_name,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS particular_name, CONVERT(ppi.patient_no USING utf8mb4) COLLATE utf8mb4_unicode_ci AS patient_no,ppi.birthday,CONVERT(l.laboratory_text USING utf8mb4) COLLATE utf8mb4_unicode_ci AS laboratory_text FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no  WHERE l.result = '' AND l.category_id = 'others' and l.iop_id='$iop_id' and l.InActive = 0 LIMIT $limit $offset ");
		return $query->result();
	}

	// public function getAll($limit = 10, $offset = 0){
	// 	$this->db->order_by('io_lab_id','asc');
	// 	$where = "result = '' ";
	// 	$this->db->where($where);
	// 	$query = $this->db->get("iop_laboratory", $limit, $offset);
	// 	return $query->result();
	// }

	public function count_all_pending_request()
	{
		$sono_cat = (int)$this->get_sonography_category_id();
		$radio_cat = (int)$this->get_radiology_category_id();
		$query = $this->db->query(
			"SELECT COUNT(DISTINCT l.iop_id) AS c FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id AND pd.InActive = 0 JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id WHERE (l.result = '' OR l.result IS NULL) AND l.InActive = 0 AND (l.category_id IS NULL OR (l.category_id != ? AND l.category_id != ?))",
			array($sono_cat, $radio_cat)
		);
		$row = $query ? $query->row() : null;
		return ($row && isset($row->c)) ? (int)$row->c : 0;
	}

	public function count_all()
	{
		$this->db->order_by('io_lab_id', 'asc');
		$where = "result = '' ";
		$this->db->where($where);
		$query = $this->db->get("iop_laboratory");
		return $query->num_rows();
	}


	public function lab_enquiry()
	{
		$from = $this->input->post('cFrom');
		$to = $this->input->post('cTo');
		$query = $this->db->query("SELECT DISTINCT(l.iop_id),CONCAT(ppi.lastname,' ',ppi.firstname,' ',ppi.middlename) AS patient_name, ppi.patient_no,l.dDate FROM iop_laboratory l JOIN patient_details_iop pd ON pd.IO_ID=l.iop_id JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no  WHERE DATE(l.dDate) BETWEEN DATE(?) AND DATE(?) AND l.result != '' AND l.InActive = 0", array($from, $to));
		return $query->result();
	}



	public function upload_lab_result_pdf($lab_data = array(), $io_lab_id = null, $uploaded_by = null)
	{
		$prev = $this->db->get_where('iop_laboratory', array('io_lab_id' => (int)$io_lab_id))->row();
		$hadText = false;
		$prevResult = '';
		$prevFindings = '';
		if ($prev) {
			$prevResult = isset($prev->result) ? trim((string)$prev->result) : '';
			$prevFindings = isset($prev->findings) ? trim((string)$prev->findings) : '';
			if ($prevResult !== '' && strtolower($prevResult) !== 'uploaded') {
				$hadText = true;
			}
			if ($prevFindings !== '' && strtolower($prevFindings) !== 'uploaded') {
				$hadText = true;
			}
		}
		$newResult = $prevResult;
		$newFindings = $prevFindings;
		if (trim($newResult) === '' || strtolower(trim($newResult)) === 'uploaded') {
			$newResult = 'uploaded';
		}
		if (trim($newFindings) === '' || strtolower(trim($newFindings)) === 'uploaded') {
			$newFindings = 'uploaded';
		}
		$newFindings = $this->db_safe_text($newFindings);
		$newResult = $this->db_safe_text($newResult);

		$this->data = array(
			'lab_result_upload'	=>	$lab_data['file_name'],
			'findings' => $newFindings,
			'result' => $newResult
		);
		// var_dump($io_lab_id);
		$this->db->where('io_lab_id', $io_lab_id);
		$this->db->update('iop_laboratory', $this->data);

		if ($this->table_exists('iop_laboratory_attachment_meta') && isset($lab_data['full_path'])) {
			$this->save_attachment_meta($io_lab_id, $lab_data, $uploaded_by, $lab_data['full_path']);
		}
		if ($this->table_exists('iop_laboratory_workflow')) {
			$status = $hadText ? 'REPORTED_BOTH' : 'REPORTED_PDF';
			$this->upsert_workflow_status($io_lab_id, $status, $uploaded_by);
		}
	}




	public function validate_complain_edit()
	{
		$this->db->where(array(
			'complain_name'		=>		$this->input->post('complain'),
			'complain_id !='	=>		$this->input->post('id'),
			'InActive'			=>		0
		));
		$query = $this->db->get("complain");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function validate_complain()
	{
		$this->db->where(array(
			'complain_name'	=>		$this->input->post('complain'),
			'InActive'			=>		0
		));
		$query = $this->db->get("complain");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function delete($id)
	{
		$this->data = array(
			'InActive'		=> 1
		);
		$this->db->where("io_lab_id", $id);
		$this->db->update('iop_laboratory', $this->data);
	}

	/**
	 * Check whether a lab/sonography request can be deleted by the requesting doctor.
	 * Deletion is blocked when:
	 *   - billing is fulfilled (PAID)
	 *   - results or findings have been entered
	 *   - workflow status is beyond BILLED (e.g. REPORTED, VERIFIED)
	 *
	 * @param int $io_lab_id
	 * @return array ['allowed' => bool, 'reason' => string]
	 */
	public function can_delete_lab_request($io_lab_id)
	{
		$io_lab_id = (int)$io_lab_id;
		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id))->row();
		if (!$lab) {
			return array('allowed' => false, 'reason' => 'Record not found.');
		}
		if ((int)$lab->InActive === 1) {
			return array('allowed' => false, 'reason' => 'Already deleted.');
		}

		// Block if results or findings have been entered
		$hasResult   = isset($lab->result)   && trim((string)$lab->result) !== '';
		$hasFindings = isset($lab->findings)  && trim((string)$lab->findings) !== '';
		if ($hasResult || $hasFindings) {
			return array('allowed' => false, 'reason' => 'Results have already been entered for this test.');
		}

		// Block if a result file has been uploaded
		if (isset($lab->lab_result_upload) && trim((string)$lab->lab_result_upload) !== '') {
			return array('allowed' => false, 'reason' => 'A result file has been uploaded for this test.');
		}

		// Block if workflow status indicates work has progressed beyond billing
		if ($this->table_exists('iop_laboratory_workflow')) {
			$wf = $this->db->get_where('iop_laboratory_workflow', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
			if ($wf && isset($wf->status)) {
				$wfStatus = strtoupper(trim((string)$wf->status));
				$blockedStatuses = array('REPORTED', 'REPORTED_TEXT', 'REPORTED_PDF', 'REPORTED_BOTH', 'VERIFIED', 'DELIVERED');
				if (in_array($wfStatus, $blockedStatuses)) {
					return array('allowed' => false, 'reason' => 'Test has been reported/verified and cannot be deleted.');
				}
			}
		}

		// Block if billing is fulfilled (PAID) — check iop_lab_billing first
		$isCat18 = isset($lab->category_id) && (int)$lab->category_id === 18;
		if ($this->table_exists('iop_lab_billing')) {
			$labBill = $this->db->get_where('iop_lab_billing', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
			if ($labBill) {
				$payStatus = isset($labBill->payment_status) ? strtoupper(trim((string)$labBill->payment_status)) : '';
				if ($payStatus === 'PAID') {
					return array('allowed' => false, 'reason' => 'Billing has been fulfilled (paid). Cannot delete.');
				}
			}
		}

		// For sonography (cat 18), also check iop_sonography_charge
		if ($isCat18 && $this->table_exists('iop_sonography_charge')) {
			$charge = $this->db->get_where('iop_sonography_charge', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
			if ($charge) {
				$st = isset($charge->status) ? strtoupper(trim((string)$charge->status)) : '';
				if ($st === 'PAID') {
					return array('allowed' => false, 'reason' => 'Sonography billing has been fulfilled (paid). Cannot delete.');
				}
			}
		}

		return array('allowed' => true, 'reason' => '');
	}

	/**
	 * Soft-delete a lab/sonography request and cascade-clean related records.
	 * Sets InActive=1 on iop_laboratory and related tables.
	 *
	 * @param int    $io_lab_id
	 * @param string $deleted_by  User ID of the doctor performing the delete
	 * @return bool
	 */
	public function soft_delete_lab_request($io_lab_id, $deleted_by)
	{
		$io_lab_id = (int)$io_lab_id;
		$now = date('Y-m-d H:i:s');

		// 1. Soft-delete the main iop_laboratory record
		$this->db->where('io_lab_id', $io_lab_id);
		$this->db->update('iop_laboratory', array('InActive' => 1));

		// 2. Soft-delete workflow status
		if ($this->table_exists('iop_laboratory_workflow')) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->where('InActive', 0);
			$this->db->update('iop_laboratory_workflow', array(
				'status'       => 'CANCELLED',
				'cancelled_at' => $now,
				'cancelled_by' => (string)$deleted_by,
				'cancel_reason'=> 'Deleted by requesting doctor',
				'updated_at'   => $now,
				'updated_by'   => (string)$deleted_by,
				'InActive'     => 1
			));
		}

		// 3. Soft-delete sonography request metadata
		if ($this->table_exists('iop_sonography_request_meta')) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->where('InActive', 0);
			$this->db->update('iop_sonography_request_meta', array('InActive' => 1));
		}

		// 4. Soft-delete lab billing record (if PENDING, not PAID)
		if ($this->table_exists('iop_lab_billing')) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->where('InActive', 0);
			$this->db->where('payment_status !=', 'PAID');
			$this->db->update('iop_lab_billing', array(
				'InActive'   => 1,
				'updated_at' => $now
			));
		}

		// 5. Soft-delete sonography charge (if not PAID)
		if ($this->table_exists('iop_sonography_charge')) {
			$this->db->where('io_lab_id', $io_lab_id);
			$this->db->where('InActive', 0);
			$this->db->where('status !=', 'PAID');
			$this->db->update('iop_sonography_charge', array(
				'InActive' => 1
			));
		}
		return true;
	}

	public function delete_lab_request_with_guard($io_lab_id, $deleted_by)
	{
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) {
			return array('allowed' => false, 'reason' => 'Invalid request id.');
		}

		$this->db->trans_begin();

		$locked = $this->lock_lab_request_for_update($io_lab_id);
		if (!$locked) {
			$this->db->trans_rollback();
			return array('allowed' => false, 'reason' => 'Unable to lock request.');
		}

		if ($this->table_exists('iop_lab_billing')) {
			$this->db->query('SELECT io_lab_id FROM iop_lab_billing WHERE io_lab_id = ? AND InActive = 0 FOR UPDATE', array($io_lab_id));
		}
		if ($this->table_exists('iop_sonography_charge')) {
			$this->db->query('SELECT io_lab_id FROM iop_sonography_charge WHERE io_lab_id = ? AND InActive = 0 FOR UPDATE', array($io_lab_id));
		}

		$check = $this->can_delete_lab_request($io_lab_id);
		if (!is_array($check) || !isset($check['allowed'])) {
			$this->db->trans_rollback();
			return array('allowed' => false, 'reason' => 'Unable to validate deletion.');
		}
		if (!$check['allowed']) {
			$this->db->trans_rollback();
			return $check;
		}

		$this->soft_delete_lab_request($io_lab_id, $deleted_by);

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('allowed' => false, 'reason' => 'Delete failed.');
		}
		$this->db->trans_commit();

		log_message('info', 'Lab request soft-deleted: io_lab_id=' . $io_lab_id . ' by user=' . $deleted_by);
		return array('allowed' => true, 'reason' => '');
	}

	
	public function delete_main_lab($id)
	{
		$this->data = array(
			'InActive'		=> 1
		);
		// $this->db->where("io_lab_id", $id);
		$this->db->where("iop_id", $id);
		$this->db->where("result", '');
		$this->db->update('iop_laboratory', $this->data);
	}

	public function delete_main_lab_by_category($iop_id, $category_id)
	{
		$this->data = array(
			'InActive'		=> 1
		);
		$this->db->where("iop_id", $iop_id);
		$this->db->where("category_id", (int)$category_id);
		$this->db->where("result", '');
		$this->db->update('iop_laboratory', $this->data);
	}

	public function edit_save()
	{
		if (!$this->input->post('findings')) {
			$findings = '';
		} else {
			$findings = $this->db_safe_text($this->input->post('findings'));
		}

		if (!$this->input->post('result')) {
			$result = '';
		} else {
			$result = $this->db_safe_text($this->input->post('result'));
		}

		$this->data = array(
			'findings'		=> $findings,
			'result'		=> $result
		);
		$this->db->where("io_lab_id", $this->input->post('io_lab_id'));
		$this->db->update('iop_laboratory', $this->data);
	}

	public function update_result_fields($io_lab_id, $findings, $result)
	{
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) return false;
		$findings = $findings === null ? '' : (string)$findings;
		$result = $result === null ? '' : (string)$result;
		$this->data = array(
			'findings' => $this->db_safe_text($findings),
			'result'   => $this->db_safe_text($result),
		);
		$this->db->where('io_lab_id', $io_lab_id);
		$this->db->update('iop_laboratory', $this->data);
		return ($this->db->affected_rows() >= 0);
	}

	public function save()
	{
		$this->data = array(
			'complain_name'		=> strtoupper($this->input->post('complain')),
			'complain_desc'		=> $this->input->post('description'),
			'InActive'			=> 0
		);
		$this->db->insert('complain', $this->data);
	}

	public function getComplain($id)
	{
		$query = $this->db->get_where("complain", array("complain_id" => $id));
		return $query->row();
	}

	/* ================================================================== */
	/*  LAB & IMAGING WORKFLOW ENHANCEMENTS                               */
	/* ================================================================== */


	/**
	 * Check payment status for a lab/imaging test by io_lab_id.
	 * Returns: array('paid'=>bool, 'label'=>string, 'payer_type'=>string)
	 */
	public function get_lab_payment_status($io_lab_id){
		$io_lab_id = (int)$io_lab_id;
		$out = array('paid' => false, 'label' => 'Unpaid', 'payer_type' => 'CASH', 'invoice_no' => '');

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id))->row();
		if (!$lab) return $out;

		$iop_id = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		if ($iop_id === '') return $out;

		// First check iop_lab_billing table for individual lab test payment status
		if ($this->table_exists('iop_lab_billing')) {
			$labBill = $this->db->get_where('iop_lab_billing', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
			if ($labBill) {
				$payStatus = isset($labBill->payment_status) ? strtoupper(trim((string)$labBill->payment_status)) : '';
				$out['invoice_no'] = isset($labBill->invoice_no) ? (string)$labBill->invoice_no : '';
				if ($payStatus === 'PAID') {
					$out['paid'] = true;
					$out['label'] = 'Paid';
					return $out;
				}
				// If billing_generated = 0, it means no billing required yet
				$billingGenerated = isset($labBill->billing_generated) ? (int)$labBill->billing_generated : 0;
				if ($billingGenerated === 0) {
					$out['paid'] = true;
					$out['label'] = 'No Billing Required';
					return $out;
				}
				// Deposit policy: if invoice exists and is PARTIAL/PAID/covered, allow service
				$invoice_no = trim((string)$out['invoice_no']);
				if ($invoice_no !== '' && $this->table_exists('iop_billing')) {
					$this->db->select('payment_status, payer_type');
					$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
					$this->db->limit(1);
					$inv = $this->db->get('iop_billing')->row();
					if ($inv) {
						$payer = isset($inv->payer_type) ? strtoupper(trim((string)$inv->payer_type)) : 'CASH';
						$invStatus = isset($inv->payment_status) ? strtoupper(trim((string)$inv->payment_status)) : '';
						if ($payer !== '' && $payer !== 'CASH') {
							$out['paid'] = true;
							$out['label'] = 'Covered';
							return $out;
						}
						if ($invStatus === 'PAID') {
							$out['paid'] = true;
							$out['label'] = 'Paid';
							return $out;
						}
						if ($invStatus === 'PARTIAL') {
							$out['paid'] = true;
							$out['label'] = 'Deposit Received';
							return $out;
						}
					}
				}
				if ($payStatus === 'PENDING') {
					$out['paid'] = false;
					$out['label'] = 'Pending Payment';
					return $out;
				}
				// Any other status (BILLED, etc) - do NOT allow, requires payment
				$out['paid'] = false;
				$out['label'] = 'Pending Payment';
				return $out;
			} else {
				// No iop_lab_billing record - check if this is a new test that needs billing
				// If iop_lab_billing table exists but no record, it means billing hasn't been set up yet
				// Block until billing is created (except for admins)
				$out['paid'] = false;
				$out['label'] = 'Not Billed';
				return $out;
			}
		} else {
			// iop_lab_billing table doesn't exist - fallback to legacy invoice check
			// Don't auto-allow, fall through to invoice check below
		}

		// Fallback: Check main visit invoice in iop_billing
		if (!$this->table_exists('iop_billing')) {
			$out['label'] = 'No Invoice';
			return $out;
		}

		$q = $this->db->query(
			"SELECT b.invoice_no, b.total_amount, b.payer_type,
				IFNULL((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0) AS total_paid
			FROM iop_billing b
			WHERE b.iop_id = ? AND b.InActive = 0
			ORDER BY b.dDate DESC LIMIT 1",
			array($iop_id)
		);
		$bill = $q ? $q->row() : null;
		if (!$bill) {
			$out['label'] = 'No Invoice';
			return $out;
		}

		$out['invoice_no'] = isset($bill->invoice_no) ? (string)$bill->invoice_no : '';
		$out['payer_type'] = isset($bill->payer_type) ? strtoupper(trim((string)$bill->payer_type)) : 'CASH';

		// NHIS patients are considered paid
		if ($out['payer_type'] === 'NHIS') {
			$out['paid'] = true;
			$out['label'] = 'NHIS Covered';
			return $out;
		}

		// Deposit policy: covered payer types are considered cleared
		if ($out['payer_type'] !== '' && $out['payer_type'] !== 'CASH') {
			$out['paid'] = true;
			$out['label'] = 'Covered';
			return $out;
		}

		// Insurance patients are considered paid
		$paymentType = isset($bill->payment_type) ? strtoupper(trim((string)$bill->payment_type)) : '';
		if ($paymentType === 'INSURANCE COMPANY') {
			$out['paid'] = true;
			$out['label'] = 'Insurance';
			return $out;
		}

		$totalAmt = isset($bill->total_amount) ? (float)$bill->total_amount : 0;
		$totalPaid = isset($bill->total_paid) ? (float)$bill->total_paid : 0;

		if ($totalAmt <= 0 || $totalPaid >= $totalAmt) {
			$out['paid'] = true;
			$out['label'] = 'Paid';
		} else if ($totalPaid > 0) {
			$out['paid'] = true;
			$out['label'] = 'Deposit Received';
		} else {
			$out['paid'] = false;
			$out['label'] = 'Unpaid';
		}
		return $out;
	}

	/**
	 * Get payment status map for multiple io_lab_ids.
	 */
	public function get_lab_payment_status_map($io_lab_ids){
		$map = array();
		if (!is_array($io_lab_ids) || count($io_lab_ids) === 0) return $map;
		// Group labs by iop_id first to minimize queries
		$labRows = array();
		$clean = array();
		foreach ($io_lab_ids as $id) { $clean[] = (int)$id; }
		$this->db->where_in('io_lab_id', $clean);
		$q = $this->db->get('iop_laboratory');
		$rows = $q ? $q->result() : array();
		$iopMap = array();
		foreach ($rows as $r) {
			$iopMap[(string)$r->io_lab_id] = (string)$r->iop_id;
		}

		// Get billing info per iop_id
		$iop_ids = array_unique(array_values($iopMap));
		$billingMap = array();
		if (count($iop_ids) > 0 && $this->table_exists('iop_billing')) {
			$this->db->where_in('iop_id', $iop_ids);
			$this->db->where('InActive', 0);
			$this->db->order_by('dDate', 'DESC');
			$bills = $this->db->get('iop_billing')->result();
			foreach ($bills as $b) {
				$key = (string)$b->iop_id;
				if (!isset($billingMap[$key])) {
					$billingMap[$key] = $b;
				}
			}
			// Get receipt sums
			$invNos = array();
			foreach ($billingMap as $b) { $invNos[] = (string)$b->invoice_no; }
			$receiptMap = array();
			if (count($invNos) > 0 && $this->table_exists('iop_receipt')) {
				$this->db->select('invoice_no, SUM(amountPaid) AS total_paid');
				$this->db->where_in('invoice_no', $invNos);
				$this->db->where('InActive', 0);
				$this->db->group_by('invoice_no');
				$rr = $this->db->get('iop_receipt')->result();
				foreach ($rr as $r) { $receiptMap[(string)$r->invoice_no] = (float)$r->total_paid; }
			}
		}

		foreach ($clean as $labId) {
			$labIdStr = (string)$labId;
			$iopId = isset($iopMap[$labIdStr]) ? $iopMap[$labIdStr] : '';
			$out = array('paid' => false, 'label' => 'No Invoice', 'payer_type' => 'CASH');
			if ($iopId !== '' && isset($billingMap[$iopId])) {
				$b = $billingMap[$iopId];
				$payer = isset($b->payer_type) ? strtoupper(trim((string)$b->payer_type)) : 'CASH';
				$out['payer_type'] = $payer;
				$inv = (string)$b->invoice_no;
				$invStatus = isset($b->payment_status) ? strtoupper(trim((string)$b->payment_status)) : '';
				$totalAmt = isset($b->total_amount) ? (float)$b->total_amount : 0.0;
				$totalPaid = isset($receiptMap[$inv]) ? (float)$receiptMap[$inv] : 0.0;
				if ($payer !== '' && $payer !== 'CASH') {
					$out['paid'] = true;
					$out['label'] = 'Covered';
				} else if ($invStatus === 'PAID' || $invStatus === 'WAIVED') {
					$out['paid'] = true;
					$out['label'] = 'Paid';
				} else if ($invStatus === 'PARTIAL') {
					$out['paid'] = true;
					$out['label'] = 'Deposit Received';
				} else if ($totalAmt <= 0 || $totalPaid >= $totalAmt) {
					$out['paid'] = true;
					$out['label'] = 'Paid';
				} else if ($totalPaid > 0.009) {
					$out['paid'] = true;
					$out['label'] = 'Deposit Received';
				} else {
					$out['paid'] = false;
					$out['label'] = 'Unpaid';
				}
			}
			$map[$labIdStr] = $out;
		}
		return $map;
	}

	/**
	 * Enhanced lab queue: pending labs with workflow + payment status.
	 * Excludes imaging (category_id 18,19,20).
	 */
	public function get_lab_queue($limit = 20, $offset = 0, $status_filter = ''){
		$limit = (int)$limit;
		$offset = (int)$offset;
		$status_filter = strtoupper(trim((string)$status_filter));

		$lab_group_ids = $this->config->item('lab_group_ids');
		if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
			$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
		}

		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$urgentSort = $this->column_exists('iop_laboratory', 'is_urgent') ? 'IFNULL(L.is_urgent,0) DESC' : '';

		$this->db->select('L.io_lab_id, L.iop_id, L.dDate, L.category_id, L.laboratory_id, L.findings, L.result, L.laboratory_text, L.lab_result_upload');
		if ($this->column_exists('iop_laboratory', 'is_urgent')) {
			$this->db->select('IFNULL(L.is_urgent,0) AS is_urgent', false);
		} else {
			$this->db->select('0 AS is_urgent', false);
		}
		if ($this->column_exists('iop_laboratory', 'specimen_type')) {
			$this->db->select('L.specimen_type');
		} else {
			$this->db->select('NULL AS specimen_type', false);
		}
		if ($this->column_exists('iop_laboratory', 'extended_status')) {
			$this->db->select('L.extended_status');
		} else {
			$this->db->select('NULL AS extended_status', false);
		}
		$this->db->select("CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) AS patient_name", false);
		$this->db->select('PPI.patient_no, PPI.birthday');
		$this->db->select('BP.particular_name AS test_name');
		if ($joinWf) {
			$this->db->select('W.status AS wf_status, W.technician_id, W.requested_at AS wf_requested_at, W.reported_at AS wf_reported_at, W.completed_at AS wf_completed_at');
		} else {
			$this->db->select('NULL AS wf_status, NULL AS technician_id, NULL AS wf_requested_at, NULL AS wf_reported_at, NULL AS wf_completed_at', false);
		}

		$this->db->from('iop_laboratory L');
		$this->db->join('patient_details_iop PD', 'PD.IO_ID = L.iop_id AND PD.InActive = 0', 'inner');
		$this->db->join('patient_personal_info PPI', 'PPI.patient_no = PD.patient_no', 'inner');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'inner');
		$this->db->join('bill_group_name BG', 'BG.group_id = BP.group_id AND BG.InActive = 0', 'inner');
		if ($joinWf) {
			$this->db->join('iop_laboratory_workflow W', 'W.io_lab_id = L.io_lab_id AND W.InActive = 0', 'left');
		}

		$this->db->where('L.InActive', 0);
		$this->db->where('L.laboratory_id >', 0);
		$this->db->where_in('BP.group_id', $lab_group_ids);
		if ($status_filter === 'PENDING') {
			$this->db->where("L.result = ''", null, false);
		} else if ($status_filter === 'COMPLETED') {
			$this->db->where("L.result != ''", null, false);
		}

		if ($urgentSort !== '') {
			$this->db->order_by($urgentSort, '', false);
		}
		$this->db->order_by('L.dDate', 'DESC');
		$this->db->order_by('L.io_lab_id', 'DESC');
		$this->db->limit($limit, $offset);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Count for lab queue.
	 */
	public function count_lab_queue($status_filter = ''){
		$status_filter = strtoupper(trim((string)$status_filter));

		$lab_group_ids = $this->config->item('lab_group_ids');
		if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
			$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
		}

		$this->db->from('iop_laboratory L');
		$this->db->join('patient_details_iop PD', 'PD.IO_ID = L.iop_id AND PD.InActive = 0', 'inner');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'inner');
		$this->db->join('bill_group_name BG', 'BG.group_id = BP.group_id AND BG.InActive = 0', 'inner');
		$this->db->where('L.InActive', 0);
		$this->db->where('L.laboratory_id >', 0);
		$this->db->where_in('BP.group_id', $lab_group_ids);
		if ($status_filter === 'PENDING') {
			$this->db->where("L.result = ''", null, false);
		} else if ($status_filter === 'COMPLETED') {
			$this->db->where("L.result != ''", null, false);
		}
		return (int)$this->db->count_all_results();
	}


	/**
	 * Mark a lab test as completed/verified.
	 */
	public function mark_lab_completed($io_lab_id, $user_id){
		$io_lab_id = (int)$io_lab_id;
		$user_id = (string)$user_id;
		if (!$this->table_exists('iop_laboratory_workflow')) return false;
		$this->ensure_lab_workflow_enhancements();
		$now = date('Y-m-d H:i:s');
		$wf = $this->get_workflow_status($io_lab_id);
		if (!$wf) return false;

		$st = isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		$reported = in_array($st, array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED'));
		if (!$reported) return false;

		$this->db->where('io_lab_id', $io_lab_id);
		$this->db->update('iop_laboratory_workflow', array(
			'status' => 'VERIFIED',
			'verified_at' => $now,
			'completed_at' => $now,
			'updated_at' => $now,
			'updated_by' => $user_id
		));
		return true;
	}

	/* ================================================================== */
	/*  IMAGING WORKFLOW ENHANCEMENTS (X-ray, ECG, Sonography)            */
	/* ================================================================== */

	/**
	 * Imaging type configuration.
	 * category_id mapping: 18=Sonography, 19=X-ray, 20=ECG
	 */
	public function get_imaging_types(){
		$sono = (int)$this->get_sonography_category_id();
		$rad = (int)$this->get_radiology_category_id();
		return array(
			'sonography' => array('id' => $sono > 0 ? $sono : 18, 'label' => 'Sonography (Ultrasound)', 'icon' => 'fa-heartbeat'),
			'xray'       => array('id' => $rad > 0 ? $rad : 16, 'label' => 'X-Ray', 'icon' => 'fa-bolt', 'rad_category' => 'X-Ray'),
			'ecg'        => array('id' => $rad > 0 ? $rad : 16, 'label' => 'ECG', 'icon' => 'fa-area-chart', 'rad_category' => 'Cardiac')
		);
	}

	/**
	 * Get imaging category_id from type key.
	 */
	public function get_imaging_category_id($type_key){
		$types = $this->get_imaging_types();
		$type_key = strtolower(trim((string)$type_key));
		return isset($types[$type_key]) ? (int)$types[$type_key]['id'] : 0;
	}

	/**
	 * Get imaging queue for a specific type.
	 */
	public function get_imaging_queue($type_key, $limit = 20, $offset = 0, $status_filter = ''){
		$catId = $this->get_imaging_category_id($type_key);
		if ($catId <= 0) return array();
		$limit = (int)$limit;
		$offset = (int)$offset;
		$status_filter = strtoupper(trim((string)$status_filter));

		$joinWf = $this->table_exists('iop_laboratory_workflow');
		$wfJoin = $joinWf ? " LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0 " : "";
		$wfSelect = $joinWf ? ", W.status AS wf_status, W.technician_id, W.requested_at AS wf_requested_at, W.reported_at AS wf_reported_at, W.completed_at AS wf_completed_at" : ", NULL AS wf_status, NULL AS technician_id, NULL AS wf_requested_at, NULL AS wf_reported_at, NULL AS wf_completed_at";

		$statusWhere = "";
		if ($status_filter === 'PENDING') {
			$statusWhere = $joinWf
				? " AND (W.status IS NULL OR UPPER(TRIM(W.status)) NOT IN ('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED','CANCELLED')) "
				: " AND L.result = '' ";
		} else if ($status_filter === 'COMPLETED') {
			$statusWhere = $joinWf
				? " AND UPPER(TRIM(W.status)) IN ('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED') "
				: " AND L.result != '' ";
		}

		$types = $this->get_imaging_types();
		$type_key = strtolower(trim((string)$type_key));
		$radFilterJoin = '';
		$radFilterWhere = '';
		if (($type_key === 'xray' || $type_key === 'ecg') && $this->table_exists('radiology_test_master')) {
			$radFilterJoin = " LEFT JOIN radiology_test_master RT ON RT.id = L.laboratory_id AND RT.InActive = 0 ";
			$cat = isset($types[$type_key]['rad_category']) ? (string)$types[$type_key]['rad_category'] : '';
			if ($cat !== '') {
				$radFilterWhere = " AND RT.category = " . $this->db->escape($cat) . " ";
			}
		}

		$sql = "SELECT L.io_lab_id, L.iop_id, L.dDate, L.category_id, L.laboratory_id, L.findings, L.result, L.laboratory_text, L.lab_result_upload,
				CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) AS patient_name,
				PPI.patient_no, PPI.birthday,
				COALESCE(NULLIF(TRIM(L.laboratory_text),''), NULLIF(TRIM(BP.particular_name),''), 'Unknown Test') AS test_name
				{$wfSelect}
			FROM iop_laboratory L
			JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0
			JOIN patient_personal_info PPI ON PPI.patient_no = PD.patient_no
			LEFT JOIN bill_particular BP ON BP.particular_id = L.laboratory_id AND BP.InActive = 0
			{$radFilterJoin}
			{$wfJoin}
			WHERE L.InActive = 0
			AND L.category_id = ?
			{$radFilterWhere}
			{$statusWhere}
			ORDER BY L.dDate DESC, L.io_lab_id DESC
			LIMIT {$offset}, {$limit}";
		return $this->db->query($sql, array($catId))->result();
	}

	/**
	 * Count imaging queue for a specific type.
	 */
	public function count_imaging_queue($type_key, $status_filter = ''){
		$catId = $this->get_imaging_category_id($type_key);
		if ($catId <= 0) return 0;
		$status_filter = strtoupper(trim((string)$status_filter));
		$types = $this->get_imaging_types();
		$type_key = strtolower(trim((string)$type_key));
		$radFilterJoin = '';
		$radFilterWhere = '';
		if (($type_key === 'xray' || $type_key === 'ecg') && $this->table_exists('radiology_test_master')) {
			$radFilterJoin = " LEFT JOIN radiology_test_master RT ON RT.id = L.laboratory_id AND RT.InActive = 0 ";
			$cat = isset($types[$type_key]['rad_category']) ? (string)$types[$type_key]['rad_category'] : '';
			if ($cat !== '') {
				$radFilterWhere = " AND RT.category = " . $this->db->escape($cat) . " ";
			}
		}

		$joinWf = $this->table_exists('iop_laboratory_workflow');

		if ($status_filter === 'PENDING' && $joinWf) {
			$sql = "SELECT COUNT(*) AS c FROM iop_laboratory L
				LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0
				JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0
				{$radFilterJoin}
				WHERE L.InActive = 0 AND L.category_id = ?
				{$radFilterWhere}
				AND (W.status IS NULL OR UPPER(TRIM(W.status)) NOT IN ('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED','CANCELLED'))";
		} else if ($status_filter === 'COMPLETED' && $joinWf) {
			$sql = "SELECT COUNT(*) AS c FROM iop_laboratory L
				LEFT JOIN iop_laboratory_workflow W ON W.io_lab_id = L.io_lab_id AND W.InActive = 0
				JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0
				{$radFilterJoin}
				WHERE L.InActive = 0 AND L.category_id = ?
				{$radFilterWhere}
				AND UPPER(TRIM(W.status)) IN ('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED','VERIFIED')";
		} else {
			$sql = "SELECT COUNT(*) AS c FROM iop_laboratory L
				JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id AND PD.InActive = 0
				{$radFilterJoin}
				WHERE L.InActive = 0 AND L.category_id = ?
				{$radFilterWhere}";
		}
		$r = $this->db->query($sql, array($catId))->row();
		return $r ? (int)$r->c : 0;
	}

	/**
	 * Get summary counts for all imaging types.
	 */
	public function get_imaging_summary(){
		$types = $this->get_imaging_types();
		$summary = array();
		foreach ($types as $key => $t) {
			$summary[$key] = array(
				'label' => $t['label'],
				'icon' => $t['icon'],
				'pending' => $this->count_imaging_queue($key, 'PENDING'),
				'completed' => $this->count_imaging_queue($key, 'COMPLETED'),
				'total' => $this->count_imaging_queue($key)
			);
		}
		return $summary;
	}

	/* ================================================================== */
	/*  FLEXIBLE LAB FINANCIAL + CLINICAL WORKFLOW (GHS)                  */
	/* ================================================================== */

	public function ensure_lab_flexible_schema(){
		static $lfDone = false;
		if ($lfDone) return;
		$lfDone = true;

		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) $this->db->db_debug = false;

		/* ── iop_laboratory: add flexible columns ── */
		if ($this->table_exists('iop_laboratory')) {
			$cols = array(
				'extended_status'              => "ALTER TABLE `iop_laboratory` ADD COLUMN `extended_status` varchar(30) NOT NULL DEFAULT 'PENDING'",
				'external_lab_flag'            => "ALTER TABLE `iop_laboratory` ADD COLUMN `external_lab_flag` tinyint(1) NOT NULL DEFAULT 0",
				'deferred_flag'                => "ALTER TABLE `iop_laboratory` ADD COLUMN `deferred_flag` tinyint(1) NOT NULL DEFAULT 0",
				'unable_to_pay_flag'           => "ALTER TABLE `iop_laboratory` ADD COLUMN `unable_to_pay_flag` tinyint(1) NOT NULL DEFAULT 0",
				'emergency_flag'               => "ALTER TABLE `iop_laboratory` ADD COLUMN `emergency_flag` tinyint(1) NOT NULL DEFAULT 0",
				'waiver_flag'                  => "ALTER TABLE `iop_laboratory` ADD COLUMN `waiver_flag` tinyint(1) NOT NULL DEFAULT 0",
				'referral_note'                => "ALTER TABLE `iop_laboratory` ADD COLUMN `referral_note` text DEFAULT NULL",
				'external_result_path'         => "ALTER TABLE `iop_laboratory` ADD COLUMN `external_result_path` varchar(255) DEFAULT NULL",
				'external_result_uploaded_by'  => "ALTER TABLE `iop_laboratory` ADD COLUMN `external_result_uploaded_by` varchar(25) DEFAULT NULL",
				'external_result_uploaded_at'  => "ALTER TABLE `iop_laboratory` ADD COLUMN `external_result_uploaded_at` datetime DEFAULT NULL",
				'flex_notes'                   => "ALTER TABLE `iop_laboratory` ADD COLUMN `flex_notes` text DEFAULT NULL",
			);
			foreach ($cols as $col => $sql) {
				if (!$this->column_exists('iop_laboratory', $col)) {
					$this->db->query($sql);
				}
			}
		}

		/* ── iop_laboratory_workflow: add flexible columns ── */
		if ($this->table_exists('iop_laboratory_workflow')) {
			$wfCols = array(
				'external_lab_flag' => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `external_lab_flag` tinyint(1) NOT NULL DEFAULT 0",
				'deferred_flag'     => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `deferred_flag` tinyint(1) NOT NULL DEFAULT 0",
				'emergency_flag'    => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `emergency_flag` tinyint(1) NOT NULL DEFAULT 0",
				'waiver_flag'       => "ALTER TABLE `iop_laboratory_workflow` ADD COLUMN `waiver_flag` tinyint(1) NOT NULL DEFAULT 0",
			);
			foreach ($wfCols as $col => $sql) {
				if (!$this->column_exists('iop_laboratory_workflow', $col)) {
					$this->db->query($sql);
				}
			}
		}

		if ($old !== null) $this->db->db_debug = $old;
	}

	/* ── Lab: External Lab ──────────────────────────────────── */

	public function mark_lab_external($io_lab_id, $user_id, $referral_note = ''){
		$this->ensure_lab_flexible_schema();
		$io_lab_id = (int)$io_lab_id;

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('ok' => false, 'error' => 'Lab record not found.');

		try {
			$this->load->model('app/billing_transaction_model');
			$this->load->model('app/billing_disposition_model');
			$sync = $this->billing_transaction_model->sync_lab_request($io_lab_id, $user_id);
			if (is_array($sync) && !empty($sync['ok']) && !empty($sync['txn_id'])) {
				$cid = 'labdisp:' . (int)$io_lab_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
				$this->billing_disposition_model->append_event(array(
					'txn_id' => (int)$sync['txn_id'],
					'from_state' => 'NORMAL_BILLABLE',
					'to_state' => 'EXTERNAL_REFERRAL',
					'from_state_source' => 'payload',
					'actor_user_id' => (string)$user_id,
					'reason' => (string)$referral_note,
					'source_module' => 'LABORATORY',
					'source_ref' => 'io_lab_id:' . (int)$io_lab_id,
					'correlation_id' => $cid,
				));
			} else {
				$this->billing_disposition_model->log_shadow_issue('LAB_SSOT_MISSING_SHADOW', 'io_lab_id:' . (int)$io_lab_id, array(
					'schema_version' => 1,
					'io_lab_id' => (int)$io_lab_id,
					'action' => 'mark_lab_external',
					'sync_ok' => is_array($sync) && isset($sync['ok']) ? (bool)$sync['ok'] : null,
					'sync_error' => is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : null,
				), $user_id);
			}
		} catch (\Throwable $e) {
		}

		$now = date('Y-m-d H:i:s');
		$updData = array(
			'extended_status'   => 'EXTERNAL_LAB',
			'external_lab_flag' => 1,
			'flex_notes'        => $referral_note,
		);
		if ($this->column_exists('iop_laboratory', 'referral_note')) {
			$updData['referral_note'] = $referral_note;
		}
		$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory', $updData);

		if ($this->table_exists('iop_laboratory_workflow') && $this->column_exists('iop_laboratory_workflow', 'external_lab_flag')) {
			$this->upsert_workflow_status($io_lab_id, 'EXTERNAL_LAB', $user_id);
			$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory_workflow', array(
				'external_lab_flag' => 1,
				'updated_at'        => $now,
				'updated_by'        => $user_id,
			));
		}

		$this->_log_lab_financial_audit('LABORATORY', $io_lab_id, (string)$lab->iop_id, 'EXTERNAL_LAB', 'PENDING', 'EXTERNAL_LAB', 0, $referral_note, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* ── Lab: Unable to Pay ─────────────────────────────────── */

	public function mark_lab_unable_to_pay($io_lab_id, $user_id, $reason = ''){
		$this->ensure_lab_flexible_schema();
		$io_lab_id = (int)$io_lab_id;

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('ok' => false, 'error' => 'Lab record not found.');

		try {
			$this->load->model('app/billing_transaction_model');
			$this->load->model('app/billing_disposition_model');
			$sync = $this->billing_transaction_model->sync_lab_request($io_lab_id, $user_id);
			if (is_array($sync) && !empty($sync['ok']) && !empty($sync['txn_id'])) {
				$cid = 'labdisp:' . (int)$io_lab_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
				$this->billing_disposition_model->append_event(array(
					'txn_id' => (int)$sync['txn_id'],
					'from_state' => 'NORMAL_BILLABLE',
					'to_state' => 'UNABLE_TO_PAY_REVIEW',
					'from_state_source' => 'payload',
					'actor_user_id' => (string)$user_id,
					'reason' => (string)$reason,
					'source_module' => 'LABORATORY',
					'source_ref' => 'io_lab_id:' . (int)$io_lab_id,
					'correlation_id' => $cid,
				));
			} else {
				$this->billing_disposition_model->log_shadow_issue('LAB_SSOT_MISSING_SHADOW', 'io_lab_id:' . (int)$io_lab_id, array(
					'schema_version' => 1,
					'io_lab_id' => (int)$io_lab_id,
					'action' => 'mark_lab_unable_to_pay',
					'sync_ok' => is_array($sync) && isset($sync['ok']) ? (bool)$sync['ok'] : null,
					'sync_error' => is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : null,
				), $user_id);
			}
		} catch (\Throwable $e) {
		}

		$now = date('Y-m-d H:i:s');
		$updData = array(
			'extended_status'    => 'UNABLE_TO_PAY',
			'unable_to_pay_flag' => 1,
			'flex_notes'         => $reason,
		);
		$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory', $updData);

		$iop_id     = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$patient_no = $this->_lab_get_patient_no($iop_id);

		if ($this->table_exists('outstanding_balances')) {
			$this->db->insert('outstanding_balances', array(
				'patient_no'    => $patient_no,
				'iop_id'        => $iop_id,
				'source_module' => 'LABORATORY',
				'source_id'     => $io_lab_id,
				'description'   => 'Unable to pay — lab test',
				'amount'        => 0,
				'balance_type'  => 'UNABLE_TO_PAY',
				'status'        => 'OUTSTANDING',
				'created_by'    => $user_id,
				'created_at'    => $now,
			));
		}

		$this->_log_lab_financial_audit('LABORATORY', $io_lab_id, $iop_id, 'UNABLE_TO_PAY', 'PENDING', 'UNABLE_TO_PAY', 0, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* ── Lab: Deferred ──────────────────────────────────────── */

	public function mark_lab_deferred($io_lab_id, $user_id, $reason = '', $defer_until = null){
		$this->ensure_lab_flexible_schema();
		$io_lab_id = (int)$io_lab_id;

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('ok' => false, 'error' => 'Lab record not found.');

		try {
			$this->load->model('app/billing_transaction_model');
			$this->load->model('app/billing_disposition_model');
			$sync = $this->billing_transaction_model->sync_lab_request($io_lab_id, $user_id);
			if (is_array($sync) && !empty($sync['ok']) && !empty($sync['txn_id'])) {
				$cid = 'labdisp:' . (int)$io_lab_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
				$this->billing_disposition_model->append_event(array(
					'txn_id' => (int)$sync['txn_id'],
					'from_state' => 'NORMAL_BILLABLE',
					'to_state' => 'DEFERRED_RECEIVABLE',
					'from_state_source' => 'payload',
					'actor_user_id' => (string)$user_id,
					'reason' => (string)$reason,
					'source_module' => 'LABORATORY',
					'source_ref' => 'io_lab_id:' . (int)$io_lab_id,
					'correlation_id' => $cid,
				));
			} else {
				$this->billing_disposition_model->log_shadow_issue('LAB_SSOT_MISSING_SHADOW', 'io_lab_id:' . (int)$io_lab_id, array(
					'schema_version' => 1,
					'io_lab_id' => (int)$io_lab_id,
					'action' => 'mark_lab_deferred',
					'sync_ok' => is_array($sync) && isset($sync['ok']) ? (bool)$sync['ok'] : null,
					'sync_error' => is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : null,
				), $user_id);
			}
		} catch (\Throwable $e) {
		}

		$now = date('Y-m-d H:i:s');
		$updData = array(
			'extended_status' => 'DEFERRED',
			'deferred_flag'   => 1,
			'flex_notes'      => $reason,
		);
		$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory', $updData);

		if ($this->table_exists('iop_laboratory_workflow') && $this->column_exists('iop_laboratory_workflow', 'deferred_flag')) {
			$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory_workflow', array(
				'deferred_flag' => 1,
				'updated_at'    => $now,
				'updated_by'    => $user_id,
			));
		}

		$iop_id     = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$patient_no = $this->_lab_get_patient_no($iop_id);

		if ($this->table_exists('outstanding_balances')) {
			$this->db->insert('outstanding_balances', array(
				'patient_no'    => $patient_no,
				'iop_id'        => $iop_id,
				'source_module' => 'LABORATORY',
				'source_id'     => $io_lab_id,
				'description'   => 'Deferred payment — lab test',
				'amount'        => 0,
				'balance_type'  => 'DEFERRED',
				'status'        => 'OUTSTANDING',
				'due_date'      => $defer_until,
				'created_by'    => $user_id,
				'created_at'    => $now,
			));
		}

		$this->_log_lab_financial_audit('LABORATORY', $io_lab_id, $iop_id, 'DEFERRED', 'PENDING', 'DEFERRED', 0, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* ── Lab: Emergency Override ────────────────────────────── */

	public function mark_lab_emergency($io_lab_id, $user_id, $reason = ''){
		$this->ensure_lab_flexible_schema();
		$io_lab_id = (int)$io_lab_id;

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('ok' => false, 'error' => 'Lab record not found.');

		try {
			$this->load->model('app/billing_transaction_model');
			$this->load->model('app/billing_disposition_model');
			$sync = $this->billing_transaction_model->sync_lab_request($io_lab_id, $user_id);
			if (is_array($sync) && !empty($sync['ok']) && !empty($sync['txn_id'])) {
				$cid = 'labdisp:' . (int)$io_lab_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
				$this->billing_disposition_model->append_event(array(
					'txn_id' => (int)$sync['txn_id'],
					'from_state' => 'NORMAL_BILLABLE',
					'to_state' => 'EMERGENCY_PENDING_BILLING',
					'from_state_source' => 'payload',
					'actor_user_id' => (string)$user_id,
					'reason' => (string)$reason,
					'source_module' => 'LABORATORY',
					'source_ref' => 'io_lab_id:' . (int)$io_lab_id,
					'correlation_id' => $cid,
				));
			} else {
				$this->billing_disposition_model->log_shadow_issue('LAB_SSOT_MISSING_SHADOW', 'io_lab_id:' . (int)$io_lab_id, array(
					'schema_version' => 1,
					'io_lab_id' => (int)$io_lab_id,
					'action' => 'mark_lab_emergency',
					'sync_ok' => is_array($sync) && isset($sync['ok']) ? (bool)$sync['ok'] : null,
					'sync_error' => is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : null,
				), $user_id);
			}
		} catch (\Throwable $e) {
		}

		$now = date('Y-m-d H:i:s');
		$updData = array(
			'extended_status' => 'EMERGENCY',
			'emergency_flag'  => 1,
			'flex_notes'      => $reason,
		);
		$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory', $updData);

		if ($this->table_exists('iop_laboratory_workflow') && $this->column_exists('iop_laboratory_workflow', 'emergency_flag')) {
			$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory_workflow', array(
				'emergency_flag' => 1,
				'updated_at'     => $now,
				'updated_by'     => $user_id,
			));
		}

		if ($this->table_exists('emergency_overrides')) {
			$iop_id     = isset($lab->iop_id) ? (string)$lab->iop_id : '';
			$patient_no = $this->_lab_get_patient_no($iop_id);
			$this->db->insert('emergency_overrides', array(
				'patient_no'    => $patient_no,
				'iop_id'        => $iop_id,
				'source_module' => 'LABORATORY',
				'source_id'     => $io_lab_id,
				'reason'        => $reason,
				'override_by'   => $user_id,
				'override_at'   => $now,
			));
		}

		$iop_id = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$this->_log_lab_financial_audit('LABORATORY', $io_lab_id, $iop_id, 'EMERGENCY_OVERRIDE', 'PENDING', 'EMERGENCY', 0, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* ── Lab: Upload External Result ────────────────────────── */

	public function upload_external_result($io_lab_id, $user_id, $file_path, $notes = ''){
		$this->ensure_lab_flexible_schema();
		$io_lab_id = (int)$io_lab_id;

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('ok' => false, 'error' => 'Lab record not found.');

		$now = date('Y-m-d H:i:s');
		$updData = array(
			'lab_result_upload' => $file_path,
			'result'            => 'uploaded',
		);
		if ($this->column_exists('iop_laboratory', 'external_result_path')) {
			$updData['external_result_path']        = $file_path;
			$updData['external_result_uploaded_by'] = $user_id;
			$updData['external_result_uploaded_at'] = $now;
		}
		if ($notes !== '' && $this->column_exists('iop_laboratory', 'flex_notes')) {
			$updData['flex_notes'] = $notes;
		}
		$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory', $updData);

		$hasPdf = ($file_path !== '');
		$status = $hasPdf ? 'REPORTED_BOTH' : 'REPORTED_TEXT';
		$this->upsert_workflow_status($io_lab_id, $status, $user_id);

		$iop_id = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$this->_log_lab_financial_audit('LABORATORY', $io_lab_id, $iop_id, 'EXTERNAL_RESULT_UPLOADED', 'EXTERNAL_LAB', 'EXTERNAL_RESULT_AVAILABLE', 0, $notes, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* ── Lab: Waiver Request ────────────────────────────────── */

	public function request_lab_waiver($io_lab_id, $user_id, $reason = ''){
		$this->ensure_lab_flexible_schema();
		$io_lab_id = (int)$io_lab_id;

		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('ok' => false, 'error' => 'Lab record not found.');

		try {
			$this->load->model('app/billing_transaction_model');
			$this->load->model('app/billing_disposition_model');
			$sync = $this->billing_transaction_model->sync_lab_request($io_lab_id, $user_id);
			if (is_array($sync) && !empty($sync['ok']) && !empty($sync['txn_id'])) {
				$cid = 'labdisp:' . (int)$io_lab_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
				$this->billing_disposition_model->append_event(array(
					'txn_id' => (int)$sync['txn_id'],
					'from_state' => 'NORMAL_BILLABLE',
					'to_state' => 'WAIVER_REQUESTED',
					'from_state_source' => 'payload',
					'actor_user_id' => (string)$user_id,
					'reason' => (string)$reason,
					'source_module' => 'LABORATORY',
					'source_ref' => 'io_lab_id:' . (int)$io_lab_id,
					'correlation_id' => $cid,
				));
			} else {
				$this->billing_disposition_model->log_shadow_issue('LAB_SSOT_MISSING_SHADOW', 'io_lab_id:' . (int)$io_lab_id, array(
					'schema_version' => 1,
					'io_lab_id' => (int)$io_lab_id,
					'action' => 'request_lab_waiver',
					'sync_ok' => is_array($sync) && isset($sync['ok']) ? (bool)$sync['ok'] : null,
					'sync_error' => is_array($sync) && isset($sync['error']) ? (string)$sync['error'] : null,
				), $user_id);
			}
		} catch (\Throwable $e) {
		}

		$now = date('Y-m-d H:i:s');
		$updData = array(
			'extended_status' => 'WAIVER_REQUESTED',
			'waiver_flag'     => 1,
			'flex_notes'      => $reason,
		);
		$this->db->where('io_lab_id', $io_lab_id)->update('iop_laboratory', $updData);

		$iop_id     = isset($lab->iop_id) ? (string)$lab->iop_id : '';
		$patient_no = $this->_lab_get_patient_no($iop_id);

		if ($this->table_exists('waiver_requests')) {
			$this->db->where(array('source_module' => 'LABORATORY', 'source_id' => $io_lab_id, 'status' => 'PENDING', 'InActive' => 0));
			if ($this->db->count_all_results('waiver_requests') === 0) {
				$this->db->insert('waiver_requests', array(
					'patient_no'    => $patient_no,
					'iop_id'        => $iop_id,
					'source_module' => 'LABORATORY',
					'source_id'     => $io_lab_id,
					'amount'        => 0,
					'reason'        => $reason,
					'status'        => 'PENDING',
					'requested_by'  => $user_id,
					'requested_at'  => $now,
				));
			}
		}

		$this->_log_lab_financial_audit('LABORATORY', $io_lab_id, $iop_id, 'WAIVER_REQUESTED', 'PENDING', 'WAIVER_REQUESTED', 0, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* ── Lab: Flexible status check ─────────────────────────── */

	public function get_lab_flexible_status($io_lab_id){
		$io_lab_id = (int)$io_lab_id;
		$lab = $this->db->get_where('iop_laboratory', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if (!$lab) return array('status' => 'UNKNOWN', 'flags' => array());
		$es = (isset($lab->extended_status) && $lab->extended_status) ? strtoupper(trim((string)$lab->extended_status)) : 'PENDING';
		return array(
			'status'          => $es,
			'external_lab'    => !empty($lab->external_lab_flag),
			'deferred'        => !empty($lab->deferred_flag),
			'unable_to_pay'   => !empty($lab->unable_to_pay_flag),
			'emergency'       => !empty($lab->emergency_flag),
			'waiver'          => !empty($lab->waiver_flag),
			'referral_note'   => isset($lab->referral_note) ? (string)$lab->referral_note : '',
			'external_result' => isset($lab->external_result_path) ? (string)$lab->external_result_path : '',
		);
	}

	/* ================================================================== */
	/*  LAB BILLING — Auto-bill regular (non-sonography) lab requests     */
	/* ================================================================== */

	public function ensure_lab_billing_schema()
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_lab_billing` (
			`lab_bill_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`io_lab_id` int(11) NOT NULL,
			`iop_id` varchar(25) NOT NULL,
			`patient_no` varchar(25) DEFAULT NULL,
			`encounter_type` varchar(3) NOT NULL DEFAULT 'OPD',
			`laboratory_id` int(11) DEFAULT NULL,
			`item_name` varchar(255) DEFAULT NULL,
			`rate_amount` decimal(18,2) NOT NULL DEFAULT 0,
			`quantity` decimal(18,2) NOT NULL DEFAULT 1,
			`payment_status` varchar(20) NOT NULL DEFAULT 'PENDING',
			`billing_generated` tinyint(1) NOT NULL DEFAULT 0,
			`invoice_no` varchar(50) DEFAULT NULL,
			`detail_id` int(11) DEFAULT NULL,
			`department` varchar(50) DEFAULT 'LABORATORY',
			`external_flag` tinyint(1) NOT NULL DEFAULT 0,
			`requested_by` varchar(25) DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`updated_at` datetime DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`lab_bill_id`),
			UNIQUE KEY `uq_io_lab` (`io_lab_id`),
			KEY `idx_iop_status` (`iop_id`, `payment_status`),
			KEY `idx_patient_status` (`patient_no`, `payment_status`),
			KEY `idx_invoice` (`invoice_no`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		return true;
	}

	/* ================================================================== */
	/*  GHS SCHEMA — InnoDB migration, indexes, clinical & NHIS columns   */
	/* ================================================================== */

	public function ensure_lab_ghs_schema()
	{
		static $ghsDone = false;
		if ($ghsDone) return;
		$ghsDone = true;

		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) $this->db->db_debug = false;

		/* A1 — Migrate iop_laboratory to InnoDB + utf8mb4 */
		if ($this->table_exists('iop_laboratory')) {
			$q = $this->db->query("SHOW TABLE STATUS WHERE Name = 'iop_laboratory'");
			$ts = $q ? $q->row() : null;
			if ($ts) {
				if (isset($ts->Engine) && strtolower($ts->Engine) !== 'innodb') {
					$this->db->query("ALTER TABLE `iop_laboratory` ENGINE=InnoDB");
				}
				if (isset($ts->Collation) && strpos(strtolower((string)$ts->Collation), 'utf8mb4') === false) {
					$this->db->query("ALTER TABLE `iop_laboratory` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
				}
			}

			/* E4 — Performance indexes on iop_laboratory */
			$idxCheck = $this->db->query("SHOW INDEX FROM `iop_laboratory` WHERE Key_name = 'idx_iop_inactive'");
			if (!$idxCheck || $idxCheck->num_rows() === 0) {
				$this->db->query("ALTER TABLE `iop_laboratory` ADD INDEX `idx_iop_inactive` (`iop_id`, `InActive`)");
			}
			$idxCheck2 = $this->db->query("SHOW INDEX FROM `iop_laboratory` WHERE Key_name = 'idx_date_cat'");
			if (!$idxCheck2 || $idxCheck2->num_rows() === 0) {
				$this->db->query("ALTER TABLE `iop_laboratory` ADD INDEX `idx_date_cat` (`dDate`, `InActive`, `category_id`)");
			}
			$idxCheck3 = $this->db->query("SHOW INDEX FROM `iop_laboratory` WHERE Key_name = 'idx_result_inactive'");
			if (!$idxCheck3 || $idxCheck3->num_rows() === 0) {
				$this->db->query("ALTER TABLE `iop_laboratory` ADD INDEX `idx_result_inactive` (`result`(20), `InActive`)");
			}

			/* Fix iop_id column width — varchar(11) is too short for some IOP IDs */
			$col = $this->info_column('iop_laboratory', 'iop_id');
			if ($col && isset($col['COLUMN_TYPE']) && strtolower((string)$col['COLUMN_TYPE']) === 'varchar(11)') {
				$this->db->query("ALTER TABLE `iop_laboratory` MODIFY COLUMN `iop_id` varchar(25) NOT NULL");
			}

			/* Fix dDateTime — varchar(100) -> datetime */
			$dtCol = $this->info_column('iop_laboratory', 'dDateTime');
			if ($dtCol && isset($dtCol['COLUMN_TYPE']) && strpos(strtolower((string)$dtCol['COLUMN_TYPE']), 'varchar') !== false) {
				$this->db->query("ALTER TABLE `iop_laboratory` MODIFY COLUMN `dDateTime` datetime DEFAULT NULL");
			}

			/* A2 — GHS clinical columns */
			$ghsCols = array(
				'is_urgent'       => "ALTER TABLE `iop_laboratory` ADD COLUMN `is_urgent` tinyint(1) NOT NULL DEFAULT 0",
				'specimen_type'   => "ALTER TABLE `iop_laboratory` ADD COLUMN `specimen_type` varchar(50) DEFAULT NULL",
				'clinical_notes'  => "ALTER TABLE `iop_laboratory` ADD COLUMN `clinical_notes` text DEFAULT NULL",
				'nhis_flag'       => "ALTER TABLE `iop_laboratory` ADD COLUMN `nhis_flag` tinyint(1) NOT NULL DEFAULT 0",
				'payer_type'      => "ALTER TABLE `iop_laboratory` ADD COLUMN `payer_type` varchar(20) NOT NULL DEFAULT 'CASH'",
			);
			foreach ($ghsCols as $col => $sql) {
				if (!$this->column_exists('iop_laboratory', $col)) {
					$this->db->query($sql);
				}
			}
		}

		/* A4 — NHIS tariff columns on bill_particular */
		if ($this->table_exists('bill_particular')) {
			$nhisCols = array(
				'nhis_code'        => "ALTER TABLE `bill_particular` ADD COLUMN `nhis_code` varchar(50) DEFAULT NULL",
				'is_nhis_covered'  => "ALTER TABLE `bill_particular` ADD COLUMN `is_nhis_covered` tinyint(1) NOT NULL DEFAULT 0",
				'nhis_price'       => "ALTER TABLE `bill_particular` ADD COLUMN `nhis_price` decimal(18,2) DEFAULT NULL",
			);
			foreach ($nhisCols as $col => $sql) {
				if (!$this->column_exists('bill_particular', $col)) {
					$this->db->query($sql);
				}
			}
		}

		/* C1 — NHIS columns on iop_lab_billing */
		if ($this->table_exists('iop_lab_billing')) {
			$billNhisCols = array(
				'payer_type'   => "ALTER TABLE `iop_lab_billing` ADD COLUMN `payer_type` varchar(20) NOT NULL DEFAULT 'CASH'",
				'nhis_flag'    => "ALTER TABLE `iop_lab_billing` ADD COLUMN `nhis_flag` tinyint(1) NOT NULL DEFAULT 0",
				'nhis_code'    => "ALTER TABLE `iop_lab_billing` ADD COLUMN `nhis_code` varchar(50) DEFAULT NULL",
				'nhis_price'   => "ALTER TABLE `iop_lab_billing` ADD COLUMN `nhis_price` decimal(18,2) DEFAULT NULL",
			);
			foreach ($billNhisCols as $col => $sql) {
				if (!$this->column_exists('iop_lab_billing', $col)) {
					$this->db->query($sql);
				}
			}
		}

		/* A5 — Structured lab result entries */
		$this->db->query("CREATE TABLE IF NOT EXISTS `lab_result_entries` (
			`entry_id`       bigint(11) NOT NULL AUTO_INCREMENT,
			`io_lab_id`      int(11) NOT NULL,
			`parameter_name` varchar(150) NOT NULL,
			`result_value`   varchar(100) DEFAULT NULL,
			`unit`           varchar(50) DEFAULT NULL,
			`ref_range_low`  varchar(50) DEFAULT NULL,
			`ref_range_high` varchar(50) DEFAULT NULL,
			`is_abnormal`    tinyint(1) NOT NULL DEFAULT 0,
			`is_critical`    tinyint(1) NOT NULL DEFAULT 0,
			`entered_by`     varchar(25) DEFAULT NULL,
			`entered_at`     datetime DEFAULT NULL,
			`InActive`       int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`entry_id`),
			KEY `idx_lab` (`io_lab_id`),
			KEY `idx_critical` (`is_critical`, `InActive`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		/* A6 — Specimen collection log */
		$this->db->query("CREATE TABLE IF NOT EXISTS `lab_specimen_log` (
			`specimen_id`   bigint(11) NOT NULL AUTO_INCREMENT,
			`io_lab_id`     int(11) NOT NULL,
			`iop_id`        varchar(25) DEFAULT NULL,
			`patient_no`    varchar(25) DEFAULT NULL,
			`specimen_type` varchar(50) DEFAULT NULL,
			`barcode_no`    varchar(100) DEFAULT NULL,
			`collected_by`  varchar(25) DEFAULT NULL,
			`collected_at`  datetime DEFAULT NULL,
			`received_at`   datetime DEFAULT NULL,
			`received_by`   varchar(25) DEFAULT NULL,
			`notes`         text DEFAULT NULL,
			`InActive`      int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`specimen_id`),
			UNIQUE KEY `uq_io_lab` (`io_lab_id`),
			KEY `idx_patient` (`patient_no`),
			KEY `idx_barcode` (`barcode_no`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

		if ($old !== null) $this->db->db_debug = $old;
	}

	public function ensure_lab_charge_posted($io_lab_id, $iop_id, $patient_no, $encounter_type, $laboratory_id, $laboratory_text, $requested_by = null, $payer_type = null)
	{
		$this->ensure_lab_billing_schema();
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) return false;
		$existing = $this->db->get_where('iop_lab_billing', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if ($existing) return true;

		$itemName = null;
		$rateAmt = 0.0;
		$nhisFlag = 0;
		$labId = (int)$laboratory_id;
		$resolvedPayer = ($payer_type !== null) ? strtoupper(trim((string)$payer_type)) : 'CASH';
		if ($resolvedPayer !== 'NHIS') { $resolvedPayer = 'CASH'; }

		if ($labId > 0) {
			$lab_group_ids = $this->config->item('lab_group_ids');
			if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
				$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
			}
			if ($this->table_exists('bill_particular')) {
				$bp = $this->db->select('particular_id, group_id')->get_where('bill_particular', array('particular_id' => $labId, 'InActive' => 0))->row();
				if (!$bp) {
					return false;
				}
				$gid = isset($bp->group_id) ? (int)$bp->group_id : 0;
				if (!in_array($gid, $lab_group_ids, true)) {
					return false;
				}
			}
			$this->load->model('app/Price_engine_model');
			$pricing = $this->Price_engine_model->resolve(array(
				'item_type'  => Price_engine_model::ITEM_SERVICE,
				'item_id'    => $labId,
				'patient_no' => $patient_no !== null ? (string)$patient_no : '',
				'payer_type' => $resolvedPayer,
				'quantity'   => 1,
			));
			if (isset($pricing['ok']) && $pricing['ok']) {
				$itemName = isset($pricing['item_name']) ? (string)$pricing['item_name'] : null;
				$rateAmt  = isset($pricing['unit_price']) ? (float)$pricing['unit_price'] : 0.0;
				$nhisFlag = !empty($pricing['nhis_covered']) ? 1 : 0;
			}
		}
		if ($labId <= 0) {
			return false;
		}
		if ($itemName === null || trim((string)$itemName) === '') {
			$itemName = ($laboratory_text !== null && trim((string)$laboratory_text) !== '') ? trim((string)$laboratory_text) : 'Laboratory Test';
		}

		$billRow = array(
			'io_lab_id'         => $io_lab_id,
			'iop_id'            => (string)$iop_id,
			'patient_no'        => $patient_no !== null ? (string)$patient_no : null,
			'encounter_type'    => strtoupper(trim((string)$encounter_type)),
			'laboratory_id'     => $labId > 0 ? $labId : null,
			'item_name'         => $itemName,
			'rate_amount'       => $rateAmt,
			'quantity'          => 1,
			'payment_status'    => 'PENDING',
			'billing_generated' => 1,
			'invoice_no'        => null,
			'detail_id'         => null,
			'department'        => 'LABORATORY',
			'external_flag'     => 0,
			'requested_by'      => $requested_by !== null ? (string)$requested_by : null,
			'created_at'        => date('Y-m-d H:i:s'),
			'InActive'          => 0,
		);
		if ($this->column_exists('iop_lab_billing', 'payer_type')) {
			$billRow['payer_type'] = $resolvedPayer;
		}
		if ($this->column_exists('iop_lab_billing', 'nhis_flag')) {
			$billRow['nhis_flag'] = $nhisFlag;
		}
		$this->db->insert('iop_lab_billing', $billRow);
		// Advance workflow: REQUESTED → BILLED
		$user = $requested_by !== null ? (string)$requested_by : 'system';
		$this->upsert_workflow_status($io_lab_id, 'BILLED', $user);
		return true;
	}

	public function mark_lab_bill_paid($io_lab_id, $invoice_no, $detail_id = null)
	{
		if (!$this->table_exists('iop_lab_billing')) return false;
		$this->db->where('io_lab_id', (int)$io_lab_id);
		$this->db->where('InActive', 0);
		$data = array(
			'payment_status' => 'PAID',
			'invoice_no'     => (string)$invoice_no,
			'updated_at'     => date('Y-m-d H:i:s'),
		);
		if ($detail_id !== null) {
			$data['detail_id'] = (int)$detail_id;
		}
		$this->db->update('iop_lab_billing', $data);
		return true;
	}

	public function mark_lab_bills_paid_by_invoice($invoice_no)
	{
		if (!$this->table_exists('iop_lab_billing')) return false;
		$this->db->where('invoice_no', (string)$invoice_no);
		$this->db->where('InActive', 0);
		$this->db->update('iop_lab_billing', array(
			'payment_status' => 'PAID',
			'updated_at'     => date('Y-m-d H:i:s'),
		));
		return true;
	}

	public function mark_lab_bills_paid_by_iop($iop_id, $invoice_no)
	{
		if (!$this->table_exists('iop_lab_billing')) return false;
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return false;
		// Fetch affected rows first so we can advance each workflow status
		$pending = $this->db->get_where('iop_lab_billing', array(
			'iop_id'         => $iop_id,
			'payment_status' => 'PENDING',
			'InActive'       => 0
		))->result();
		foreach ($pending as $bill) {
			$this->upsert_workflow_status((int)$bill->io_lab_id, 'PAID', 'system');
		}
		$this->db->where('iop_id', $iop_id);
		$this->db->where('payment_status', 'PENDING');
		$this->db->where('InActive', 0);
		$this->db->update('iop_lab_billing', array(
			'payment_status' => 'PAID',
			'invoice_no'     => (string)$invoice_no,
			'updated_at'     => date('Y-m-d H:i:s'),
		));
		return true;
	}

	public function get_lab_bill_status($io_lab_id)
	{
		if (!$this->table_exists('iop_lab_billing')) return null;
		return $this->db->get_where('iop_lab_billing', array('io_lab_id' => (int)$io_lab_id, 'InActive' => 0))->row();
	}

	public function get_pending_lab_bills($limit = 50)
	{
		$this->ensure_lab_billing_schema();
		$sql = "SELECT LB.*, CONCAT(PPI.lastname,' ',PPI.firstname) AS patient_name, PPI.patient_no AS ppi_patient_no
				FROM iop_lab_billing LB
				LEFT JOIN patient_details_iop PD ON PD.IO_ID = LB.iop_id AND PD.InActive = 0
				LEFT JOIN patient_personal_info PPI ON PPI.patient_no = LB.patient_no
				WHERE LB.payment_status = 'PENDING' AND LB.billing_generated = 1 AND LB.InActive = 0
				ORDER BY LB.created_at DESC
				LIMIT " . (int)$limit;
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	public function count_pending_lab_bills()
	{
		if (!$this->table_exists('iop_lab_billing')) return 0;
		$r = $this->db->where(array('payment_status' => 'PENDING', 'billing_generated' => 1, 'InActive' => 0))
			->count_all_results('iop_lab_billing');
		return (int)$r;
	}

	/* ── Lab Queue — payment-status aware ──────────────────── */

	public function pending_lab_requests_paid($limit = 10, $offset = 0)
	{
		$this->ensure_lab_billing_schema();
		$limit = (int)$limit;
		$offset = (int)$offset;
		$hasBilling = $this->table_exists('iop_lab_billing');
		$hasInvoice = $hasBilling && $this->table_exists('iop_billing');
		$payJoin = $hasBilling ? " LEFT JOIN iop_lab_billing LB ON LB.io_lab_id = L.io_lab_id AND LB.InActive = 0 " : "";
		$invJoin = $hasInvoice ? " LEFT JOIN iop_billing B ON B.invoice_no = LB.invoice_no AND B.InActive = 0 " : "";
		$payFilter = $hasBilling ? " AND (LB.billing_generated = 0 OR LB.payment_status = 'PAID'" . ($hasInvoice ? " OR (B.invoice_no IS NOT NULL AND (UPPER(TRIM(B.payment_status)) IN ('PAID','PARTIAL','WAIVED') OR (B.payer_type IS NOT NULL AND UPPER(TRIM(B.payer_type)) <> 'CASH')))" : "") . ") " : "";
		$paySelect = $hasBilling ? ($hasInvoice ? "CASE\n\t\t\t\t\t\t\t\tWHEN LB.billing_generated = 0 THEN 'NO_BILLING'\n\t\t\t\t\t\t\t\tWHEN LB.payment_status = 'PAID' THEN 'PAID'\n\t\t\t\t\t\t\t\tWHEN (B.payer_type IS NOT NULL AND UPPER(TRIM(B.payer_type)) <> 'CASH') THEN 'COVERED'\n\t\t\t\t\t\t\t\tWHEN UPPER(TRIM(B.payment_status)) = 'PARTIAL' THEN 'DEPOSIT'\n\t\t\t\t\t\t\t\tWHEN UPPER(TRIM(B.payment_status)) = 'PAID' THEN 'PAID'\n\t\t\t\t\t\t\t\tWHEN UPPER(TRIM(B.payment_status)) = 'WAIVED' THEN 'WAIVED'\n\t\t\t\t\t\t\t\tELSE COALESCE(LB.payment_status,'UNKNOWN')\n\t\t\t\t\t\t\tEND AS payment_status" : "COALESCE(LB.payment_status,'UNKNOWN') AS payment_status") : "'UNKNOWN' AS payment_status";
		$sql = "SELECT DISTINCT L.iop_id,
				CONCAT(ppi.lastname,' ',ppi.firstname,' ',COALESCE(ppi.middlename,'')) AS patient_name,
				ppi.patient_no, ppi.birthday, L.dDate,
				" . $paySelect . "
				FROM iop_laboratory L
				JOIN patient_details_iop pd ON pd.IO_ID = L.iop_id AND pd.InActive = 0
				JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
				" . $payJoin . "
				" . $invJoin . "
				WHERE (L.result = '' OR L.result IS NULL) AND L.InActive = 0
				AND (L.category_id IS NULL OR L.category_id != '18')
				" . $payFilter . "
				ORDER BY L.dDate DESC LIMIT {$offset}, {$limit}";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	public function get_lab_bill_map($io_lab_ids)
	{
		if (!$this->table_exists('iop_lab_billing') || empty($io_lab_ids)) return array();
		$clean = array_map('intval', (array)$io_lab_ids);
		$this->db->where_in('io_lab_id', $clean)->where('InActive', 0);
		$rows = $this->db->get('iop_lab_billing')->result();
		$map = array();
		foreach ($rows as $r) { $map[(int)$r->io_lab_id] = $r; }
		return $map;
	}

	/* ── Lab Dashboard Counts ───────────────────────────────── */

	public function count_external_labs(){
		$this->ensure_lab_flexible_schema();
		if (!$this->table_exists('iop_laboratory')) return 0;
		if (!$this->column_exists('iop_laboratory', 'external_lab_flag')) return 0;
		$lab_group_ids = $this->config->item('lab_group_ids');
		if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
			$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
		}
		if (!$this->table_exists('bill_particular') || !$this->table_exists('bill_group_name')) {
			return $this->db->where(array('external_lab_flag' => 1, 'InActive' => 0))->count_all_results('iop_laboratory');
		}
		$this->db->from('iop_laboratory L');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'inner');
		$this->db->join('bill_group_name BG', 'BG.group_id = BP.group_id AND BG.InActive = 0', 'inner');
		$this->db->where('L.InActive', 0);
		$this->db->where('L.laboratory_id >', 0);
		$this->db->where('L.external_lab_flag', 1);
		$this->db->where_in('BP.group_id', $lab_group_ids);
		return (int)$this->db->count_all_results();
	}

	public function count_deferred_labs(){
		$this->ensure_lab_flexible_schema();
		if (!$this->table_exists('iop_laboratory')) return 0;
		if (!$this->column_exists('iop_laboratory', 'deferred_flag')) return 0;
		$lab_group_ids = $this->config->item('lab_group_ids');
		if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
			$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
		}
		if (!$this->table_exists('bill_particular') || !$this->table_exists('bill_group_name')) {
			return $this->db->where(array('deferred_flag' => 1, 'InActive' => 0))->count_all_results('iop_laboratory');
		}
		$this->db->from('iop_laboratory L');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'inner');
		$this->db->join('bill_group_name BG', 'BG.group_id = BP.group_id AND BG.InActive = 0', 'inner');
		$this->db->where('L.InActive', 0);
		$this->db->where('L.laboratory_id >', 0);
		$this->db->where('L.deferred_flag', 1);
		$this->db->where_in('BP.group_id', $lab_group_ids);
		return (int)$this->db->count_all_results();
	}

	public function count_emergency_labs(){
		$this->ensure_lab_flexible_schema();
		if (!$this->table_exists('iop_laboratory')) return 0;
		if (!$this->column_exists('iop_laboratory', 'emergency_flag')) return 0;
		$lab_group_ids = $this->config->item('lab_group_ids');
		if (!is_array($lab_group_ids) || empty($lab_group_ids)) {
			$lab_group_ids = array(8, 9, 10, 11, 12, 13, 14, 15);
		}
		if (!$this->table_exists('bill_particular') || !$this->table_exists('bill_group_name')) {
			return $this->db->where(array('emergency_flag' => 1, 'InActive' => 0))->count_all_results('iop_laboratory');
		}
		$this->db->from('iop_laboratory L');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'inner');
		$this->db->join('bill_group_name BG', 'BG.group_id = BP.group_id AND BG.InActive = 0', 'inner');
		$this->db->where('L.InActive', 0);
		$this->db->where('L.laboratory_id >', 0);
		$this->db->where('L.emergency_flag', 1);
		$this->db->where_in('BP.group_id', $lab_group_ids);
		return (int)$this->db->count_all_results();
	}

	public function get_orphaned_lab_requests($limit = 50, $offset = 0){
		$limit = (int)$limit;
		$offset = (int)$offset;
		$this->db->select("L.io_lab_id, L.iop_id, L.dDate, L.category_id, L.laboratory_id, L.laboratory_text, L.result, L.findings, L.InActive", false);
		$this->db->select("CONCAT(PPI.lastname,' ',PPI.firstname,' ',PPI.middlename) AS patient_name", false);
		$this->db->select('PPI.patient_no');
		$this->db->from('iop_laboratory L');
		$this->db->join('patient_details_iop PD', 'PD.IO_ID = L.iop_id AND PD.InActive = 0', 'left');
		$this->db->join('patient_personal_info PPI', 'PPI.patient_no = PD.patient_no', 'left');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'left');
		$this->db->where('L.InActive', 0);
		$this->db->where('BP.particular_id IS NULL', null, false);
		$this->db->order_by('L.dDate', 'DESC');
		$this->db->order_by('L.io_lab_id', 'DESC');
		$this->db->limit($limit, $offset);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function count_orphaned_lab_requests(){
		$this->db->from('iop_laboratory L');
		$this->db->join('bill_particular BP', 'BP.particular_id = L.laboratory_id AND BP.InActive = 0', 'left');
		$this->db->where('L.InActive', 0);
		$this->db->where('BP.particular_id IS NULL', null, false);
		return (int)$this->db->count_all_results();
	}

	/* ── Internal helpers ───────────────────────────────────── */

	private function _lab_get_patient_no($iop_id){
		$iop_id = (string)$iop_id;
		if ($iop_id === '' || !$this->table_exists('patient_details_iop')) return '';
		$r = $this->db->select('patient_no')->get_where('patient_details_iop', array('IO_ID' => $iop_id))->row();
		return $r ? (string)$r->patient_no : '';
	}

	private function _log_lab_financial_audit($module, $source_id, $iop_id, $event, $old, $new, $amount, $notes, $user_id){
		if (!$this->table_exists('financial_audit_log')) return;
		$patient_no = $this->_lab_get_patient_no($iop_id);
		$this->db->insert('financial_audit_log', array(
			'patient_no'    => (string)$patient_no,
			'iop_id'        => (string)$iop_id,
			'source_module' => (string)$module,
			'source_id'     => (int)$source_id,
			'event_type'    => (string)$event,
			'old_status'    => $old !== null ? (string)$old : null,
			'new_status'    => $new !== null ? (string)$new : null,
			'amount'        => (float)$amount,
			'notes'         => $notes ? substr((string)$notes, 0, 500) : null,
			'performed_by'  => (string)$user_id,
			'performed_at'  => date('Y-m-d H:i:s'),
		));
	}

	/**
	 * Get patient information by patient number
	 */
	public function get_patient_info_by_no($patient_no)
	{
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') return null;
		
		$this->db->select('PPI.*');
		$this->db->from('patient_personal_info PPI');
		$this->db->where('PPI.patient_no', $patient_no);
		$this->db->where('PPI.InActive', 0);
		$q = $this->db->get();
		return $q ? $q->row() : null;
	}

	/**
	 * Get user information by user ID
	 */
	public function get_user_by_id($user_id)
	{
		$user_id = trim((string)$user_id);
		if ($user_id === '') return null;
		
		$this->db->select('U.*');
		$this->db->from('users U');
		$this->db->where('U.user_id', $user_id);
		$this->db->where('U.InActive', 0);
		$q = $this->db->get();
		return $q ? $q->row() : null;
	}

	/* ================================================================== */
	/*  ADMIN BILLING BYPASS FOR SONOGRAPHY/RADIOLOGY                    */
	/* ================================================================== */

	/**
	 * Ensure the billing bypass table exists
	 */
	public function ensure_billing_bypass_table()
	{
		if ($this->table_exists('imaging_billing_bypass')) {
			return true;
		}
		$this->db->query("CREATE TABLE IF NOT EXISTS `imaging_billing_bypass` (
			`bypass_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`io_lab_id` int(11) NOT NULL,
			`bypass_reason` text NOT NULL,
			`bypassed_by` varchar(25) NOT NULL,
			`bypassed_at` datetime NOT NULL,
			`is_active` tinyint(1) NOT NULL DEFAULT 1,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`bypass_id`),
			UNIQUE KEY `uq_io_lab` (`io_lab_id`),
			KEY `idx_active` (`is_active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		return true;
	}

	/**
	 * Check if a sonography/radiology test has admin billing bypass
	 * @param int $io_lab_id
	 * @return bool
	 */
	public function has_billing_bypass($io_lab_id)
	{
		$this->ensure_billing_bypass_table();
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) return false;
		
		$this->db->where(array('io_lab_id' => $io_lab_id, 'is_active' => 1, 'InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get('imaging_billing_bypass');
		return ($q && $q->num_rows() > 0);
	}

	/**
	 * Get billing bypass details for a test
	 * @param int $io_lab_id
	 * @return object|null
	 */
	public function get_billing_bypass($io_lab_id)
	{
		$this->ensure_billing_bypass_table();
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) return null;
		
		$this->db->where(array('io_lab_id' => $io_lab_id, 'is_active' => 1, 'InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get('imaging_billing_bypass');
		return $q ? $q->row() : null;
	}

	/**
	 * Set admin billing bypass for a test (admin only)
	 * @param int $io_lab_id
	 * @param string $reason
	 * @param string $bypassed_by Admin user ID
	 * @return array
	 */
	public function set_billing_bypass($io_lab_id, $reason, $bypassed_by)
	{
		$this->ensure_billing_bypass_table();
		$io_lab_id = (int)$io_lab_id;
		$reason = trim((string)$reason);
		$bypassed_by = trim((string)$bypassed_by);
		
		if ($io_lab_id <= 0) {
			return array('success' => false, 'error' => 'Invalid test ID');
		}
		if ($reason === '') {
			return array('success' => false, 'error' => 'Bypass reason is required');
		}
		if ($bypassed_by === '') {
			return array('success' => false, 'error' => 'Admin user ID is required');
		}
		
		// Check if bypass already exists
		$existing = $this->get_billing_bypass($io_lab_id);
		if ($existing) {
			return array('success' => true, 'bypass_id' => $existing->bypass_id, 'message' => 'Bypass already exists');
		}
		
		$now = date('Y-m-d H:i:s');
		$this->db->insert('imaging_billing_bypass', array(
			'io_lab_id' => $io_lab_id,
			'bypass_reason' => $reason,
			'bypassed_by' => $bypassed_by,
			'bypassed_at' => $now,
			'is_active' => 1,
			'InActive' => 0
		));
		
		$bypass_id = $this->db->insert_id();
		log_message('info', 'IMAGING_BILLING_BYPASS_SET io_lab_id='.$io_lab_id.' by='.$bypassed_by.' reason='.$reason);
		
		return array('success' => true, 'bypass_id' => $bypass_id);
	}

	/**
	 * Remove billing bypass for a test (admin only)
	 * @param int $io_lab_id
	 * @param string $removed_by Admin user ID
	 * @return bool
	 */
	public function remove_billing_bypass($io_lab_id, $removed_by)
	{
		$this->ensure_billing_bypass_table();
		$io_lab_id = (int)$io_lab_id;
		
		$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
		$this->db->update('imaging_billing_bypass', array('is_active' => 0));
		
		log_message('info', 'IMAGING_BILLING_BYPASS_REMOVED io_lab_id='.$io_lab_id.' by='.$removed_by);
		return true;
	}

	/**
	 * Check if sonography test can be processed (payment complete OR admin bypass)
	 * @param int $io_lab_id
	 * @return array ['allowed' => bool, 'reason' => string, 'bypass' => bool]
	 */
	public function check_sonography_billing_gate($io_lab_id)
	{
		$io_lab_id = (int)$io_lab_id;
		
		// Check for admin bypass first
		if ($this->has_billing_bypass($io_lab_id)) {
			$bypass = $this->get_billing_bypass($io_lab_id);
			return array(
				'allowed' => true, 
				'reason' => 'Admin bypass: ' . ($bypass ? $bypass->bypass_reason : 'Approved'),
				'bypass' => true,
				'bypassed_by' => $bypass ? $bypass->bypassed_by : null,
				'bypassed_at' => $bypass ? $bypass->bypassed_at : null
			);
		}
		
		// Get lab record to check encounter type
		$labRow = $this->get_lab_record_with_patient($io_lab_id);
		if (!$labRow) {
			return array('allowed' => false, 'reason' => 'Test record not found', 'bypass' => false);
		}
		
		// Skip billing gate if test is already reported
		$labResult = isset($labRow->result) ? trim((string)$labRow->result) : '';
		$labFindings = isset($labRow->findings) ? trim((string)$labRow->findings) : '';
		if ($labResult !== '' || $labFindings !== '') {
			return array('allowed' => true, 'reason' => 'Already reported', 'bypass' => false);
		}
		
		// Check workflow status
		$wfStatus = '';
		if (method_exists($this, 'get_workflow_status')) {
			$wf = $this->get_workflow_status($io_lab_id);
			$wfStatus = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		}
		if ($wfStatus === 'REPORTED' || $wfStatus === 'VERIFIED') {
			return array('allowed' => true, 'reason' => 'Already reported', 'bypass' => false);
		}
		
		// Get encounter type from meta
		$meta = $this->get_sonography_request_meta($io_lab_id);
		$enc = $meta && isset($meta->encounter_type) ? strtoupper(trim((string)$meta->encounter_type)) : 'OPD';
		
		// Check billing status
		if ($enc === 'IPD') {
			$charge = $this->get_ipd_sonography_charge_by_lab($io_lab_id);
		} else {
			$charge = $this->get_sonography_charge_by_lab($io_lab_id);
		}
		
		if (!$charge) {
			return array(
				'allowed' => false, 
				'reason' => 'Billing not yet posted. Please notify Billing & Finance to post the charge.',
				'bypass' => false
			);
		}
		
		$status = isset($charge->status) ? strtoupper(trim((string)$charge->status)) : '';
		if ($status === 'PAID') {
			return array('allowed' => true, 'reason' => 'Payment complete', 'bypass' => false);
		}
		$invoice_no = isset($charge->invoice_no) ? trim((string)$charge->invoice_no) : '';
		if ($invoice_no !== '' && $this->table_exists('iop_billing')) {
			$this->db->select('payment_status, payer_type, total_amount');
			$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
			$this->db->limit(1);
			$inv = $this->db->get('iop_billing')->row();
			if ($inv) {
				$payer = isset($inv->payer_type) ? strtoupper(trim((string)$inv->payer_type)) : 'CASH';
				$invStatus = isset($inv->payment_status) ? strtoupper(trim((string)$inv->payment_status)) : '';
				if ($payer !== '' && $payer !== 'CASH') {
					return array('allowed' => true, 'reason' => 'Covered', 'bypass' => false);
				}
				if ($invStatus === 'PAID') {
					return array('allowed' => true, 'reason' => 'Payment complete', 'bypass' => false);
				}
				if ($invStatus === 'PARTIAL') {
					return array('allowed' => true, 'reason' => 'Deposit received', 'bypass' => false);
				}
				if ($invStatus === '' && $this->table_exists('iop_receipt') && isset($inv->total_amount)) {
					$this->db->select('COALESCE(SUM(amountPaid),0) AS total_paid', false);
					$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
					$this->db->limit(1);
					$sumRow = $this->db->get('iop_receipt')->row();
					$totalPaid = ($sumRow && isset($sumRow->total_paid)) ? (float)$sumRow->total_paid : 0.0;
					if ($totalPaid > 0.009) {
						return array('allowed' => true, 'reason' => 'Deposit received', 'bypass' => false);
					}
				}
			}
		}
		return array(
			'allowed' => false, 
			'reason' => 'Payment not complete (Status: ' . ($status ?: 'PENDING') . '). Patient must pay or admin must authorize.',
			'bypass' => false
		);
	}
}
