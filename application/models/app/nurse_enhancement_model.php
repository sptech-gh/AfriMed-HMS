<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Nurse_enhancement_model extends CI_Model {

	public function __construct(){
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

	public function tables_ready(){
		return $this->table_exists('iop_vital_parameters_extra')
			&& $this->table_exists('iop_medication_administration');
	}

	public function install_tables(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_vital_parameters_extra` (\n".
			"  `extra_id` int(11) NOT NULL AUTO_INCREMENT,\n".
			"  `vital_id` int(11) NOT NULL,\n".
			"  `spo2` varchar(25) DEFAULT NULL,\n".
			"  `blood_sugar` varchar(25) DEFAULT NULL,\n".
			"  `pain_score` varchar(25) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `created_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`extra_id`),\n".
			"  KEY `idx_vital` (`vital_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_medication_administration` (\n".
			"  `admin_id` int(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_med_id` int(11) NOT NULL,\n".
			"  `iop_id` varchar(25) DEFAULT NULL,\n".
			"  `status` varchar(20) NOT NULL,\n".
			"  `dose_given` varchar(50) DEFAULT NULL,\n".
			"  `notes` text,\n".
			"  `dDateTime` datetime NOT NULL,\n".
			"  `cPreparedBy` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`admin_id`),\n".
			"  KEY `idx_iop` (`iop_id`),\n".
			"  KEY `idx_iop_med` (`iop_med_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `nurse_doctor_message` (\n".
			"  `message_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(25) DEFAULT NULL,\n".
			"  `patient_no` varchar(15) DEFAULT NULL,\n".
			"  `from_user_id` varchar(25) DEFAULT NULL,\n".
			"  `to_doctor_id` varchar(15) DEFAULT NULL,\n".
			"  `to_user_id` varchar(25) DEFAULT NULL,\n".
			"  `message` text,\n".
			"  `status` varchar(20) NOT NULL DEFAULT 'UNREAD',\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `read_at` datetime DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`message_id`),\n".
			"  KEY `idx_to_status` (`to_doctor_id`,`status`),\n".
			"  KEY `idx_to_user_status` (`to_user_id`,`status`),\n".
			"  KEY `idx_iop` (`iop_id`,`patient_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->ensure_message_schema();

		$this->db->query("CREATE TABLE IF NOT EXISTS `nurse_shift` (\n".
			"  `shift_id` int(11) NOT NULL AUTO_INCREMENT,\n".
			"  `shift_name` varchar(50) NOT NULL,\n".
			"  `start_time` time DEFAULT NULL,\n".
			"  `end_time` time DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`shift_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->seed_default_shifts();

		$this->db->query("CREATE TABLE IF NOT EXISTS `nurse_shift_task` (\n".
			"  `task_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `shift_id` int(11) DEFAULT NULL,\n".
			"  `iop_id` varchar(25) DEFAULT NULL,\n".
			"  `patient_no` varchar(15) DEFAULT NULL,\n".
			"  `title` varchar(150) DEFAULT NULL,\n".
			"  `description` text,\n".
			"  `priority` varchar(20) DEFAULT 'NORMAL',\n".
			"  `status` varchar(20) NOT NULL DEFAULT 'OPEN',\n".
			"  `assigned_to` varchar(25) DEFAULT NULL,\n".
			"  `created_by` varchar(25) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `completed_at` datetime DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`task_id`),\n".
			"  KEY `idx_shift_status` (`shift_id`,`status`),\n".
			"  KEY `idx_iop` (`iop_id`,`patient_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		return true;
	}

	public function seed_default_shifts(){
		if (!$this->table_exists('nurse_shift')) {
			return false;
		}
		$q = $this->db->query("SELECT COUNT(*) AS c FROM nurse_shift WHERE InActive = 0");
		$r = $q ? $q->row() : null;
		$count = ($r && isset($r->c)) ? (int)$r->c : 0;
		if ($count > 0) {
			return true;
		}
		$this->db->insert('nurse_shift', array('shift_name' => 'Morning', 'start_time' => '07:00:00', 'end_time' => '15:00:00', 'InActive' => 0));
		$this->db->insert('nurse_shift', array('shift_name' => 'Afternoon', 'start_time' => '15:00:00', 'end_time' => '23:00:00', 'InActive' => 0));
		$this->db->insert('nurse_shift', array('shift_name' => 'Night', 'start_time' => '23:00:00', 'end_time' => '07:00:00', 'InActive' => 0));
		return true;
	}

	public function ensure_message_schema(){
		if (!$this->table_exists('nurse_doctor_message')) {
			return false;
		}
		if (!$this->column_exists('nurse_doctor_message', 'to_user_id')) {
			$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($old_debug !== null) {
				$this->db->db_debug = false;
			}
			$this->db->query("ALTER TABLE `nurse_doctor_message` ADD COLUMN `to_user_id` varchar(25) DEFAULT NULL");
			$this->db->query("ALTER TABLE `nurse_doctor_message` ADD KEY `idx_to_user_status` (`to_user_id`,`status`)");
			if ($old_debug !== null) {
				$this->db->db_debug = $old_debug;
			}
		}
		return true;
	}

	public function count_unread_doctor_messages($doctor_id){
		if (!$this->table_exists('nurse_doctor_message')) {
			return 0;
		}
		$doctor_id = (string) $doctor_id;
		$has_to_user_id = $this->column_exists('nurse_doctor_message', 'to_user_id');
		$this->db->from('nurse_doctor_message');
		$this->db->where('InActive', 0);
		$this->db->where('status', 'UNREAD');
		if ($has_to_user_id) {
			$this->db->group_start();
			$this->db->where('to_doctor_id', $doctor_id);
			$this->db->or_where('to_user_id', $doctor_id);
			$this->db->group_end();
		} else {
			$this->db->where('to_doctor_id', $doctor_id);
		}
		return (int) $this->db->count_all_results();
	}

	public function get_iop_doctor_id($iop_id){
		$q = $this->db->get_where('patient_details_iop', array('IO_ID' => (string)$iop_id));
		$r = $q ? $q->row() : null;
		return ($r && isset($r->doctor_id)) ? (string)$r->doctor_id : '';
	}

	public function send_message_to_doctor($iop_id, $patient_no, $from_user_id, $to_doctor_id, $message){
		$this->ensure_message_schema();
		$has_to_user_id = $this->column_exists('nurse_doctor_message', 'to_user_id');
		$data = array(
			'iop_id' => (string)$iop_id,
			'patient_no' => (string)$patient_no,
			'from_user_id' => (string)$from_user_id,
			'to_doctor_id' => (string)$to_doctor_id,
			'message' => (string)$message,
			'status' => 'UNREAD',
			'created_at' => date('Y-m-d H:i:s'),
			'read_at' => null,
			'InActive' => 0
		);
		if ($has_to_user_id) {
			$data['to_user_id'] = (string)$to_doctor_id;
		}
		$this->db->insert('nurse_doctor_message', $data);
		return true;
	}

	public function send_message_to_nurse($iop_id, $patient_no, $from_user_id, $to_user_id, $message){
		$this->ensure_message_schema();
		$has_to_user_id = $this->column_exists('nurse_doctor_message', 'to_user_id');
		$data = array(
			'iop_id' => (string)$iop_id,
			'patient_no' => (string)$patient_no,
			'from_user_id' => (string)$from_user_id,
			'to_doctor_id' => $has_to_user_id ? null : (string)$to_user_id,
			'message' => (string)$message,
			'status' => 'UNREAD',
			'created_at' => date('Y-m-d H:i:s'),
			'read_at' => null,
			'InActive' => 0
		);
		if ($has_to_user_id) {
			$data['to_user_id'] = (string)$to_user_id;
		}
		$this->db->insert('nurse_doctor_message', $data);
		return true;
	}

	public function get_messages_thread($iop_id, $patient_no){
		if (!$this->table_exists('nurse_doctor_message')) {
			return array();
		}
		$this->db->order_by('created_at', 'ASC');
		$q = $this->db->get_where('nurse_doctor_message', array(
			'iop_id' => (string)$iop_id,
			'patient_no' => (string)$patient_no,
			'InActive' => 0
		));
		return $q ? $q->result() : array();
	}

	public function get_last_counterparty_user($iop_id, $patient_no, $current_user_id){
		if (!$this->table_exists('nurse_doctor_message')) {
			return '';
		}
		$current_user_id = (string)$current_user_id;
		$this->db->order_by('created_at', 'DESC');
		$this->db->where('InActive', 0);
		$this->db->where('iop_id', (string)$iop_id);
		$this->db->where('patient_no', (string)$patient_no);
		$this->db->where('from_user_id !=', $current_user_id);
		$this->db->limit(1);
		$q = $this->db->get('nurse_doctor_message');
		$r = $q ? $q->row() : null;
		return ($r && isset($r->from_user_id)) ? (string)$r->from_user_id : '';
	}

	public function mark_thread_read_for_doctor($iop_id, $patient_no, $doctor_id){
		if (!$this->table_exists('nurse_doctor_message')) {
			return false;
		}
		$doctor_id = (string)$doctor_id;
		$has_to_user_id = $this->column_exists('nurse_doctor_message', 'to_user_id');
		$this->db->where('InActive', 0);
		$this->db->where('status', 'UNREAD');
		$this->db->where('iop_id', (string)$iop_id);
		$this->db->where('patient_no', (string)$patient_no);
		if ($has_to_user_id) {
			$this->db->group_start();
			$this->db->where('to_doctor_id', $doctor_id);
			$this->db->or_where('to_user_id', $doctor_id);
			$this->db->group_end();
		} else {
			$this->db->where('to_doctor_id', $doctor_id);
		}
		$this->db->update('nurse_doctor_message', array('status' => 'READ', 'read_at' => date('Y-m-d H:i:s')));
		return true;
	}

	public function mark_thread_read_for_user($iop_id, $patient_no, $user_id){
		if (!$this->table_exists('nurse_doctor_message')) {
			return false;
		}
		$user_id = (string)$user_id;
		$has_to_user_id = $this->column_exists('nurse_doctor_message', 'to_user_id');
		$this->db->where('InActive', 0);
		$this->db->where('status', 'UNREAD');
		$this->db->where('iop_id', (string)$iop_id);
		$this->db->where('patient_no', (string)$patient_no);
		if ($has_to_user_id) {
			$this->db->where('to_user_id', $user_id);
		} else {
			$this->db->where('to_doctor_id', $user_id);
		}
		$this->db->update('nurse_doctor_message', array('status' => 'READ', 'read_at' => date('Y-m-d H:i:s')));
		return true;
	}

	public function get_doctor_inbox($doctor_id){
		if (!$this->table_exists('nurse_doctor_message')) {
			return array();
		}
		$doctor_id = (string)$doctor_id;
		$has_to_user_id = $this->column_exists('nurse_doctor_message', 'to_user_id');
		$this->db->select("M.iop_id, M.patient_no, concat(P.firstname,' ',P.lastname) AS patient_name, MAX(M.created_at) AS last_time, SUM(CASE WHEN M.status = 'UNREAD' THEN 1 ELSE 0 END) AS unread_count", false);
		$this->db->from('nurse_doctor_message M');
		$this->db->join('patient_personal_info P', 'P.patient_no = M.patient_no', 'left');
		$this->db->where('M.InActive', 0);
		if ($has_to_user_id) {
			$this->db->group_start();
			$this->db->where('M.to_doctor_id', $doctor_id);
			$this->db->or_where('M.to_user_id', $doctor_id);
			$this->db->group_end();
		} else {
			$this->db->where('M.to_doctor_id', $doctor_id);
		}
		$this->db->group_by(array('M.iop_id','M.patient_no','P.firstname','P.lastname'));
		$this->db->order_by('last_time', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function get_shifts(){
		if (!$this->table_exists('nurse_shift')) {
			return array();
		}
		$this->db->order_by('shift_id', 'ASC');
		$q = $this->db->get_where('nurse_shift', array('InActive' => 0));
		return $q ? $q->result() : array();
	}

	public function get_shift_tasks($shift_id, $status){
		if (!$this->table_exists('nurse_shift_task')) {
			return array();
		}
		$this->db->order_by('created_at', 'DESC');
		$this->db->where('InActive', 0);
		if ($shift_id !== null && $shift_id !== '') {
			$this->db->where('shift_id', (int)$shift_id);
		}
		if ($status !== null && $status !== '') {
			$this->db->where('status', (string)$status);
		}
		$q = $this->db->get('nurse_shift_task');
		return $q ? $q->result() : array();
	}

	public function create_shift_task($shift_id, $title, $description, $priority, $iop_id, $patient_no, $assigned_to, $created_by){
		$data = array(
			'shift_id' => ($shift_id !== '' && $shift_id !== null) ? (int)$shift_id : null,
			'iop_id' => trim((string)$iop_id) !== '' ? (string)$iop_id : null,
			'patient_no' => trim((string)$patient_no) !== '' ? (string)$patient_no : null,
			'title' => (string)$title,
			'description' => trim((string)$description) !== '' ? (string)$description : null,
			'priority' => trim((string)$priority) !== '' ? (string)$priority : 'NORMAL',
			'status' => 'OPEN',
			'assigned_to' => trim((string)$assigned_to) !== '' ? (string)$assigned_to : null,
			'created_by' => (string)$created_by,
			'created_at' => date('Y-m-d H:i:s'),
			'completed_at' => null,
			'InActive' => 0
		);
		$this->db->insert('nurse_shift_task', $data);
		return true;
	}

	public function complete_shift_task($task_id, $user_id){
		$this->db->where('task_id', (int)$task_id);
		$this->db->update('nurse_shift_task', array(
			'status' => 'DONE',
			'completed_at' => date('Y-m-d H:i:s')
		));
		return true;
	}

	public function get_vitals_with_extras($iop_id){
		$this->db->select('V.*, X.spo2, X.blood_sugar, X.pain_score');
		$this->db->from('iop_vital_parameters V');
		$this->db->join('iop_vital_parameters_extra X', 'X.vital_id = V.vital_id AND X.InActive = 0', 'left');
		$this->db->where(array('V.iop_id' => (string)$iop_id, 'V.InActive' => 0));
		$this->db->order_by('V.dDateTime', 'DESC');
		return $this->db->get()->result();
	}

	public function save_vital_extra($vital_id, $spo2, $blood_sugar, $pain_score, $user_id){
		$spo2 = trim((string)$spo2);
		$blood_sugar = trim((string)$blood_sugar);
		$pain_score = trim((string)$pain_score);
		if ($spo2 === '' && $blood_sugar === '' && $pain_score === '') {
			return false;
		}

		$data = array(
			'vital_id' => (int)$vital_id,
			'spo2' => $spo2 !== '' ? $spo2 : null,
			'blood_sugar' => $blood_sugar !== '' ? $blood_sugar : null,
			'pain_score' => $pain_score !== '' ? $pain_score : null,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => (string)$user_id,
			'InActive' => 0
		);
		return $this->db->insert('iop_vital_parameters_extra', $data);
	}

	public function get_latest_med_admin_by_iop($iop_id){
		if (!$this->table_exists('iop_medication_administration')) {
			return array();
		}

		$this->db->select('A.*');
		$this->db->from('iop_medication_administration A');
		$this->db->join('(SELECT iop_med_id, MAX(admin_id) AS max_admin_id FROM iop_medication_administration WHERE InActive = 0 GROUP BY iop_med_id) L', 'L.max_admin_id = A.admin_id', 'inner');
		$this->db->where(array('A.iop_id' => (string)$iop_id, 'A.InActive' => 0));
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();

		$byMed = array();
		foreach ($rows as $r) {
			$byMed[(string)$r->iop_med_id] = $r;
		}
		return $byMed;
	}

	/* ================================================================== */
	/*  OPD VITALS WORKFLOW                                               */
	/* ================================================================== */

	/**
	 * Add vitals_status column to patient_details_iop if missing.
	 */
	public function ensure_vitals_workflow_schema(){
		if (!$this->column_exists('patient_details_iop', 'vitals_status')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `vitals_status` varchar(20) DEFAULT NULL");
		}
		if (!$this->column_exists('patient_details_iop', 'vitals_nurse_id')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `vitals_nurse_id` varchar(25) DEFAULT NULL");
		}
		if (!$this->column_exists('patient_details_iop', 'vitals_at')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `vitals_at` datetime DEFAULT NULL");
		}
	}

	/**
	 * Get today's OPD patients waiting for vitals (vitals_status IS NULL or != 'DONE').
	 */
	public function get_opd_vitals_queue($limit = 50){
		$this->ensure_vitals_workflow_schema();
		$sql = "SELECT A.IO_ID, A.patient_no, A.date_visit, A.time_visit,
				A.vitals_status, A.vitals_nurse_id, A.vitals_at,
				A.department_id, A.doctor_id,
				CONCAT(C.cValue,' ',B.firstname,' ',B.lastname) AS patient_name,
				D.dept_name
				FROM patient_details_iop A
				LEFT JOIN patient_personal_info B ON B.patient_no = A.patient_no
				LEFT JOIN system_parameters C ON C.param_id = B.title
				LEFT JOIN department D ON D.department_id = A.department_id
				WHERE A.date_visit = '".date('Y-m-d')."'
				AND A.patient_type = 'OPD'
				AND A.InActive = 0
				ORDER BY
					CASE WHEN A.vitals_status = 'DONE' THEN 1 ELSE 0 END ASC,
					A.time_visit ASC
				LIMIT ".(int)$limit;
		return $this->db->query($sql)->result();
	}

	/**
	 * Check if vitals have been recorded for a specific visit today.
	 */
	public function has_vitals_done($iop_id){
		$this->ensure_vitals_workflow_schema();
		$q = $this->db->get_where('patient_details_iop', array(
			'IO_ID' => (string)$iop_id,
			'InActive' => 0
		), 1);
		$r = $q ? $q->row() : null;
		return ($r && isset($r->vitals_status) && (string)$r->vitals_status === 'DONE');
	}

	/**
	 * Get single OPD visit row for vitals recording.
	 */
	public function get_opd_visit($iop_id){
		$q = $this->db->get_where('patient_details_iop', array(
			'IO_ID' => (string)$iop_id,
			'InActive' => 0
		), 1);
		return $q ? $q->row() : null;
	}

	/**
	 * Save OPD vitals: insert into iop_vital_parameters + update patient_details_iop inline columns + mark as DONE.
	 */
	public function save_opd_vitals($iop_id, $bp, $temperature, $pulse_rate, $weight, $height, $respiration, $nurse_id){
		$this->ensure_vitals_workflow_schema();
		$now = date('Y-m-d H:i:s');
		$today = date('Y-m-d');

		// 1. Insert into iop_vital_parameters (detailed vitals log)
		if (!$this->db->insert('iop_vital_parameters', array(
			'iop_id'      => (string)$iop_id,
			'dDate'        => $today,
			'dDateTime'    => $now,
			'pulse_rate'   => trim((string)$pulse_rate) !== '' ? (string)$pulse_rate : null,
			'temperature'  => trim((string)$temperature) !== '' ? (string)$temperature : null,
			'height'       => trim((string)$height) !== '' ? (string)$height : null,
			'bp'           => trim((string)$bp) !== '' ? (string)$bp : null,
			'respiration'  => trim((string)$respiration) !== '' ? (string)$respiration : null,
			'weight'       => trim((string)$weight) !== '' ? (string)$weight : null,
			'cPreparedBy'  => (string)$nurse_id,
			'InActive'     => 0
		))) {
			return false;
		}
		$vital_id = $this->db->insert_id();

		// 2. Update inline vitals on patient_details_iop
		$this->db->where('IO_ID', (string)$iop_id);
		if (!$this->db->update('patient_details_iop', array(
			'bp'            => trim((string)$bp) !== '' ? (string)$bp : null,
			'temperature'   => trim((string)$temperature) !== '' ? (string)$temperature : null,
			'pulse_rate'    => trim((string)$pulse_rate) !== '' ? (string)$pulse_rate : null,
			'weight'        => trim((string)$weight) !== '' ? (string)$weight : null,
			'height'        => trim((string)$height) !== '' ? (string)$height : null,
			'respiration'   => trim((string)$respiration) !== '' ? (string)$respiration : null,
			'vitals_status' => 'DONE',
			'vitals_nurse_id' => (string)$nurse_id,
			'vitals_at'     => $now
		))) {
			return false;
		}

		// 3. Save extras if tables ready
		if ($this->tables_ready()) {
			$this->save_vital_extra($vital_id, null, null, null, $nurse_id);
		}

		return $vital_id;
	}

	/**
	 * Count OPD patients pending vitals today.
	 */
	public function count_opd_vitals_pending(){
		$this->ensure_vitals_workflow_schema();
		$q = $this->db->query("
			SELECT COUNT(*) AS c FROM patient_details_iop
			WHERE date_visit = '".date('Y-m-d')."'
			AND patient_type = 'OPD' AND InActive = 0
			AND (vitals_status IS NULL OR vitals_status != 'DONE')
		");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	/**
	 * Count OPD patients with vitals completed today.
	 */
	public function count_opd_vitals_done(){
		$this->ensure_vitals_workflow_schema();
		$q = $this->db->query("
			SELECT COUNT(*) AS c FROM patient_details_iop
			WHERE date_visit = '".date('Y-m-d')."'
			AND patient_type = 'OPD' AND InActive = 0
			AND vitals_status = 'DONE'
		");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function save_medication_administration($iop_med_id, $iop_id, $status, $dose_given, $notes, $dDateTime, $user_id){
		$st = strtoupper(trim((string)$status));
		if ($st === 'AWAITING_PAYMENT') {
			return false;
		}
		if ($st === 'DISPENSED' || $st === 'PARTIAL') {
			if (function_exists('has_role') && !(has_role('pharmacist') || has_role('admin'))) {
				return false;
			}
		}
		$data = array(
			'iop_med_id' => (int)$iop_med_id,
			'iop_id' => (string)$iop_id,
			'status' => (string)$status,
			'dose_given' => trim((string)$dose_given) !== '' ? trim((string)$dose_given) : null,
			'notes' => trim((string)$notes) !== '' ? trim((string)$notes) : null,
			'dDateTime' => (string)$dDateTime,
			'cPreparedBy' => (string)$user_id,
			'InActive' => 0
		);
		$this->db->insert('iop_medication_administration', $data);
		return true;
	}

	/* ================================================================== */
	/*  SHIFT TASK ENHANCEMENTS — Ghana Private Hospital Workflow          */
	/* ================================================================== */

	/** Task category constants used in dropdown and display */
	public static $TASK_CATEGORIES = array(
		'MEDICATION_ROUND'    => 'Medication Round',
		'VITALS_CHECK'        => 'Vitals Check',
		'WOUND_CARE'          => 'Wound Care / Dressing',
		'IV_CARE'             => 'IV Line / Cannula Care',
		'PATIENT_MONITORING'  => 'Patient Monitoring',
		'SPECIMEN_COLLECTION' => 'Specimen Collection',
		'PATIENT_EDUCATION'   => 'Patient Education',
		'DISCHARGE_PREP'      => 'Discharge Preparation',
		'ADMISSION_PREP'      => 'Admission Preparation',
		'FEEDING'             => 'Feeding / Nutrition',
		'HANDOVER_NOTE'       => 'Handover Note',
		'ESCALATION'          => 'Escalation / Critical Alert',
		'ADMINISTRATIVE'      => 'Administrative Task',
		'BILLING_HOLD'        => 'Billing / Payment Hold',
		'OTHER'               => 'Other',
	);

	/**
	 * Safe migration for shift_task enhanced columns.
	 * Adds shift_date, task_category, completed_by, handover_notes.
	 */
	public function ensure_shift_task_enhanced_schema(){
		if (!$this->table_exists('nurse_shift_task')) {
			return false;
		}
		$_dbg = $this->db->db_debug; $this->db->db_debug = false;
		if (!$this->column_exists('nurse_shift_task', 'shift_date')) {
			$this->db->query("ALTER TABLE `nurse_shift_task` ADD COLUMN `shift_date` date DEFAULT NULL AFTER `shift_id`");
		}
		if (!$this->column_exists('nurse_shift_task', 'task_category')) {
			$this->db->query("ALTER TABLE `nurse_shift_task` ADD COLUMN `task_category` varchar(30) DEFAULT 'OTHER' AFTER `description`");
		}
		if (!$this->column_exists('nurse_shift_task', 'completed_by')) {
			$this->db->query("ALTER TABLE `nurse_shift_task` ADD COLUMN `completed_by` varchar(25) DEFAULT NULL AFTER `completed_at`");
		}
		if (!$this->column_exists('nurse_shift_task', 'handover_notes')) {
			$this->db->query("ALTER TABLE `nurse_shift_task` ADD COLUMN `handover_notes` text AFTER `completed_by`");
		}
		$this->db->db_debug = $_dbg;
		return true;
	}

	/**
	 * Get shift tasks filtered by date (defaults to today).
	 */
	public function get_shift_tasks_by_date($shift_id, $status, $shift_date = null){
		if (!$this->table_exists('nurse_shift_task')) {
			return array();
		}
		$this->ensure_shift_task_enhanced_schema();
		$has_category = $this->column_exists('nurse_shift_task', 'task_category');
		$has_completed_by = $this->column_exists('nurse_shift_task', 'completed_by');

		$select = 'T.*, U1.firstname AS created_firstname, U1.lastname AS created_lastname';
		if ($has_completed_by) {
			$select .= ', U2.firstname AS completed_firstname, U2.lastname AS completed_lastname';
		}
		$select .= ', U3.firstname AS assigned_firstname, U3.lastname AS assigned_lastname';

		$this->db->select($select, false);
		$this->db->from('nurse_shift_task T');
		$this->db->join('users U1', 'U1.user_id = T.created_by', 'left');
		if ($has_completed_by) {
			$this->db->join('users U2', 'U2.user_id = T.completed_by', 'left');
		}
		$this->db->join('users U3', 'U3.user_id = T.assigned_to', 'left');
		$this->db->where('T.InActive', 0);

		if ($shift_id !== null && $shift_id !== '') {
			$this->db->where('T.shift_id', (int)$shift_id);
		}
		if ($status !== null && $status !== '') {
			$this->db->where('T.status', (string)$status);
		}
		if ($shift_date === null) $shift_date = date('Y-m-d');
		if ($this->column_exists('nurse_shift_task', 'shift_date')) {
			$this->db->group_start();
			$this->db->where('T.shift_date', $shift_date);
			$this->db->or_where('T.shift_date IS NULL');
			$this->db->group_end();
		}
		$this->db->order_by("FIELD(T.priority,'URGENT','HIGH','NORMAL')", '', false);
		$this->db->order_by('T.created_at', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Enhanced complete_shift_task — records WHO completed it.
	 */
	public function complete_shift_task_enhanced($task_id, $user_id, $handover_notes = ''){
		$this->ensure_shift_task_enhanced_schema();
		$data = array(
			'status' => 'DONE',
			'completed_at' => date('Y-m-d H:i:s')
		);
		if ($this->column_exists('nurse_shift_task', 'completed_by')) {
			$data['completed_by'] = (string)$user_id;
		}
		if ($handover_notes !== '' && $this->column_exists('nurse_shift_task', 'handover_notes')) {
			$data['handover_notes'] = (string)$handover_notes;
		}
		$this->db->where('task_id', (int)$task_id);
		$this->db->update('nurse_shift_task', $data);
		return true;
	}

	/**
	 * Get all nurses (users with nurse role) for assignment dropdown.
	 */
	public function get_nurses_list(){
		// Find the nurse role ID
		$nurse_role_id = null;
		if ($this->db->table_exists('page_role')) {
			$q = $this->db->query("SELECT DISTINCT user_role FROM users WHERE InActive = 0");
			// We'll use the session-based approach: find users who have nurse access
		}
		// Approach: find users whose role matches known nurse role IDs
		// or fallback to users who have accessed nurse module pages
		$sql = "SELECT u.user_id, u.username, u.firstname, u.lastname, u.user_role
				FROM users u
				WHERE u.InActive = 0
				ORDER BY u.firstname, u.lastname";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Detect current shift from server time.
	 * Returns shift_id or null.
	 */
	public function detect_current_shift(){
		if (!$this->table_exists('nurse_shift')) return null;
		$now = date('H:i:s');
		$shifts = $this->get_shifts();
		foreach ($shifts as $s) {
			$start = $s->start_time;
			$end = $s->end_time;
			if ($start < $end) {
				// Normal range (e.g. 07:00 to 15:00)
				if ($now >= $start && $now < $end) return $s->shift_id;
			} else {
				// Overnight range (e.g. 23:00 to 07:00)
				if ($now >= $start || $now < $end) return $s->shift_id;
			}
		}
		return null;
	}

	/* ================================================================== */
	/*  INTAKE/OUTPUT ENHANCED SCHEMA — Ghana Fluid Balance Chart          */
	/* ================================================================== */

	/**
	 * Safe migration for enhanced I/O columns + glucose table.
	 */
	public function ensure_io_enhanced_schema(){
		$_dbg = $this->db->db_debug; $this->db->db_debug = false;

		// --- Intake enhancements ---
		if ($this->db->table_exists('iop_intake_record')) {
			if (!$this->column_exists('iop_intake_record', 'blood_products')) {
				$this->db->query("ALTER TABLE `iop_intake_record` ADD COLUMN `blood_products` varchar(25) DEFAULT '0'");
			}
			if (!$this->column_exists('iop_intake_record', 'ng_tube_feeds')) {
				$this->db->query("ALTER TABLE `iop_intake_record` ADD COLUMN `ng_tube_feeds` varchar(25) DEFAULT '0'");
			}
		}

		// --- Output enhancements ---
		if ($this->db->table_exists('iop_output_record')) {
			if (!$this->column_exists('iop_output_record', 'vomit')) {
				$this->db->query("ALTER TABLE `iop_output_record` ADD COLUMN `vomit` varchar(25) DEFAULT '0'");
			}
			if (!$this->column_exists('iop_output_record', 'drainage')) {
				$this->db->query("ALTER TABLE `iop_output_record` ADD COLUMN `drainage` varchar(25) DEFAULT '0'");
			}
			if (!$this->column_exists('iop_output_record', 'drainage_site')) {
				$this->db->query("ALTER TABLE `iop_output_record` ADD COLUMN `drainage_site` varchar(100) DEFAULT NULL");
			}
			if (!$this->column_exists('iop_output_record', 'stool_count')) {
				$this->db->query("ALTER TABLE `iop_output_record` ADD COLUMN `stool_count` varchar(10) DEFAULT '0'");
			}
			if (!$this->column_exists('iop_output_record', 'stool_consistency')) {
				$this->db->query("ALTER TABLE `iop_output_record` ADD COLUMN `stool_consistency` varchar(30) DEFAULT NULL");
			}
		}

		// --- Glucose monitoring table ---
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_glucose_monitoring` (
			`glucose_id` int(11) NOT NULL AUTO_INCREMENT,
			`iop_id` varchar(25) NOT NULL,
			`glucose_value` decimal(5,1) NOT NULL,
			`glucose_unit` varchar(10) DEFAULT 'mmol/L',
			`glucose_type` varchar(10) DEFAULT 'RBS',
			`dDate` date DEFAULT NULL,
			`dDateTime` varchar(50) DEFAULT NULL,
			`cPreparedBy` varchar(25) DEFAULT NULL,
			`notes` text,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`glucose_id`),
			KEY `idx_iop` (`iop_id`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->db_debug = $_dbg;
		return true;
	}

	/**
	 * Save a blood glucose reading.
	 */
	public function save_glucose_reading($iop_id, $glucose_value, $glucose_type, $dDate, $dDateTime, $notes, $user_id){
		$this->ensure_io_enhanced_schema();
		$data = array(
			'iop_id'        => (string)$iop_id,
			'glucose_value' => (float)$glucose_value,
			'glucose_unit'  => 'mmol/L',
			'glucose_type'  => in_array($glucose_type, array('RBS','FBS','2HPP')) ? $glucose_type : 'RBS',
			'dDate'         => $dDate,
			'dDateTime'     => $dDateTime,
			'cPreparedBy'   => (string)$user_id,
			'notes'         => trim((string)$notes) !== '' ? (string)$notes : null,
			'InActive'      => 0
		);
		return $this->db->insert('iop_glucose_monitoring', $data);
	}

	/**
	 * Get glucose readings for an encounter.
	 */
	public function get_glucose_readings($iop_id){
		if (!$this->db->table_exists('iop_glucose_monitoring')) {
			return array();
		}
		$this->db->select('G.*, concat(U.firstname," ",U.lastname) as nurse_name', false);
		$this->db->from('iop_glucose_monitoring G');
		$this->db->join('users U', 'U.user_id = G.cPreparedBy', 'left');
		$this->db->where(array('G.iop_id' => (string)$iop_id, 'G.InActive' => 0));
		$this->db->order_by('G.dDateTime', 'DESC');
		return $this->db->get()->result();
	}

	/**
	 * Calculate I/O totals for a given encounter (shift or 24hr).
	 */
	public function get_io_totals($iop_id){
		$totals = array(
			'intake_iv' => 0, 'intake_oral' => 0, 'intake_blood' => 0, 'intake_ng' => 0,
			'output_urine' => 0, 'output_faeces' => 0, 'output_vomit' => 0,
			'output_drainage' => 0, 'output_insensible' => 0,
			'total_intake' => 0, 'total_output' => 0, 'balance' => 0
		);

		if ($this->db->table_exists('iop_intake_record')) {
			$q = $this->db->query("SELECT 
				COALESCE(SUM(CAST(IV_fluids AS DECIMAL(10,1))),0) as iv,
				COALESCE(SUM(CAST(oral AS DECIMAL(10,1))),0) as oral,
				COALESCE(SUM(CASE WHEN blood_products IS NOT NULL THEN CAST(blood_products AS DECIMAL(10,1)) ELSE 0 END),0) as blood,
				COALESCE(SUM(CASE WHEN ng_tube_feeds IS NOT NULL THEN CAST(ng_tube_feeds AS DECIMAL(10,1)) ELSE 0 END),0) as ng
				FROM iop_intake_record WHERE iop_id = ".$this->db->escape($iop_id)." AND InActive = 0");
			$r = $q ? $q->row() : null;
			if ($r) {
				$totals['intake_iv'] = (float)$r->iv;
				$totals['intake_oral'] = (float)$r->oral;
				$totals['intake_blood'] = (float)$r->blood;
				$totals['intake_ng'] = (float)$r->ng;
			}
		}

		if ($this->db->table_exists('iop_output_record')) {
			$q2 = $this->db->query("SELECT 
				COALESCE(SUM(CAST(urine AS DECIMAL(10,1))),0) as urine,
				COALESCE(SUM(CAST(feaces AS DECIMAL(10,1))),0) as faeces,
				COALESCE(SUM(CAST(respitation AS DECIMAL(10,1))),0) as insensible,
				COALESCE(SUM(CASE WHEN vomit IS NOT NULL THEN CAST(vomit AS DECIMAL(10,1)) ELSE 0 END),0) as vomit,
				COALESCE(SUM(CASE WHEN drainage IS NOT NULL THEN CAST(drainage AS DECIMAL(10,1)) ELSE 0 END),0) as drainage
				FROM iop_output_record WHERE iop_id = ".$this->db->escape($iop_id)." AND InActive = 0");
			$r2 = $q2 ? $q2->row() : null;
			if ($r2) {
				$totals['output_urine'] = (float)$r2->urine;
				$totals['output_faeces'] = (float)$r2->faeces;
				$totals['output_insensible'] = (float)$r2->insensible;
				$totals['output_vomit'] = (float)$r2->vomit;
				$totals['output_drainage'] = (float)$r2->drainage;
			}
		}

		$totals['total_intake'] = $totals['intake_iv'] + $totals['intake_oral'] + $totals['intake_blood'] + $totals['intake_ng'];
		$totals['total_output'] = $totals['output_urine'] + $totals['output_faeces'] + $totals['output_vomit'] + $totals['output_drainage'] + $totals['output_insensible'];
		$totals['balance'] = $totals['total_intake'] - $totals['total_output'];
		return $totals;
	}

	/* ================================================================== */
	/*  SHIFT TASK BILLING & HANDOVER SCHEMA                              */
	/* ================================================================== */

	/**
	 * Idempotent migration for shift task billing, handover, and escalation.
	 * - Adds 13 missing columns to nurse_shift_task (checks existence first)
	 * - Adds 5 indexes (checks existence first)
	 * - Creates 3 new tables: nurse_shift_handover, nurse_shift_handover_tasks,
	 *   nurse_task_escalation
	 * Safe to call repeatedly — no-ops if already applied.
	 */
	public function ensure_nurse_shift_task_billing_schema(){
		if (!$this->table_exists('nurse_shift_task')) {
			return false;
		}
		$_dbg = $this->db->db_debug;
		$this->db->db_debug = false;

		// --- ADD MISSING COLUMNS to nurse_shift_task ---
		$cols = array(
			array('task_no',          "ALTER TABLE `nurse_shift_task` ADD COLUMN `task_no` VARCHAR(40) DEFAULT NULL AFTER `task_id`"),
			array('due_at',           "ALTER TABLE `nurse_shift_task` ADD COLUMN `due_at` DATETIME DEFAULT NULL AFTER `handover_notes`"),
			array('room_id',          "ALTER TABLE `nurse_shift_task` ADD COLUMN `room_id` INT DEFAULT NULL AFTER `due_at`"),
			array('is_billable',      "ALTER TABLE `nurse_shift_task` ADD COLUMN `is_billable` TINYINT(1) NOT NULL DEFAULT 0 AFTER `room_id`"),
			array('billing_triggered',"ALTER TABLE `nurse_shift_task` ADD COLUMN `billing_triggered` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_billable`"),
			array('queue_id',         "ALTER TABLE `nurse_shift_task` ADD COLUMN `queue_id` INT DEFAULT NULL AFTER `billing_triggered`"),
			array('billing_item_id',  "ALTER TABLE `nurse_shift_task` ADD COLUMN `billing_item_id` BIGINT DEFAULT NULL AFTER `queue_id`"),
			array('catalog_id',       "ALTER TABLE `nurse_shift_task` ADD COLUMN `catalog_id` INT DEFAULT NULL AFTER `billing_item_id`"),
			array('item_source',      "ALTER TABLE `nurse_shift_task` ADD COLUMN `item_source` VARCHAR(20) DEFAULT NULL AFTER `catalog_id`"),
			array('source_type',      "ALTER TABLE `nurse_shift_task` ADD COLUMN `source_type` VARCHAR(30) DEFAULT 'MANUAL' AFTER `item_source`"),
			array('source_ref',       "ALTER TABLE `nurse_shift_task` ADD COLUMN `source_ref` VARCHAR(100) DEFAULT NULL AFTER `source_type`"),
			array('escalated',        "ALTER TABLE `nurse_shift_task` ADD COLUMN `escalated` TINYINT(1) NOT NULL DEFAULT 0 AFTER `source_ref`"),
			array('acknowledged_at',  "ALTER TABLE `nurse_shift_task` ADD COLUMN `acknowledged_at` DATETIME DEFAULT NULL AFTER `escalated`"),
		);
		foreach ($cols as $c) {
			if (!$this->column_exists('nurse_shift_task', $c[0])) {
				$this->db->query($c[1]);
			}
		}

		// --- ADD UNIQUE on task_no (if column exists and index does not) ---
		if ($this->column_exists('nurse_shift_task', 'task_no')) {
			if (!$this->_index_exists('nurse_shift_task', 'uq_nst_task_no')) {
				$this->db->query("ALTER TABLE `nurse_shift_task` ADD UNIQUE KEY `uq_nst_task_no` (`task_no`)");
			}
		}

		// --- ADD INDEXES (check existence before each) ---
		$indexes = array(
			array('idx_nst_iop',      "ALTER TABLE `nurse_shift_task` ADD INDEX `idx_nst_iop` (`iop_id`)"),
			array('idx_nst_status',   "ALTER TABLE `nurse_shift_task` ADD INDEX `idx_nst_status` (`status`)"),
			array('idx_nst_shift',    "ALTER TABLE `nurse_shift_task` ADD INDEX `idx_nst_shift` (`shift_date`, `shift_id`)"),
			array('idx_nst_billable', "ALTER TABLE `nurse_shift_task` ADD INDEX `idx_nst_billable` (`is_billable`, `billing_triggered`)"),
			array('idx_nst_priority', "ALTER TABLE `nurse_shift_task` ADD INDEX `idx_nst_priority` (`priority`)"),
		);
		foreach ($indexes as $ix) {
			if (!$this->_index_exists('nurse_shift_task', $ix[0])) {
				$this->db->query($ix[1]);
			}
		}

		// --- CREATE nurse_shift_handover ---
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `nurse_shift_handover` (
				`handover_id`       INT AUTO_INCREMENT PRIMARY KEY,
				`handover_no`       VARCHAR(40) NOT NULL,
				`ward`              VARCHAR(50) NOT NULL,
				`shift_date`        DATE NOT NULL,
				`outgoing_shift_id` INT NOT NULL,
				`incoming_shift_id` INT NOT NULL,
				`handover_by`       VARCHAR(25) NOT NULL,
				`received_by`       VARCHAR(25) DEFAULT NULL,
				`received_at`       DATETIME DEFAULT NULL,
				`general_notes`     TEXT,
				`status`            VARCHAR(20) NOT NULL DEFAULT 'PENDING',
				`InActive`          INT NOT NULL DEFAULT 0,
				`created_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at`        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY `uq_nsh_handover_no` (`handover_no`),
				INDEX `idx_nsh_ward_date` (`ward`, `shift_date`),
				INDEX `idx_nsh_status` (`status`),
				INDEX `idx_nsh_handover_by` (`handover_by`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		// --- CREATE nurse_shift_handover_tasks ---
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `nurse_shift_handover_tasks` (
				`id`            INT AUTO_INCREMENT PRIMARY KEY,
				`handover_id`   INT NOT NULL,
				`task_id`       BIGINT NOT NULL,
				`carry_reason`  TEXT,
				`InActive`      INT NOT NULL DEFAULT 0,
				`created_at`    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				INDEX `idx_nsht_handover` (`handover_id`),
				INDEX `idx_nsht_task` (`task_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		// --- CREATE nurse_task_escalation ---
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `nurse_task_escalation` (
				`escalation_id`    INT AUTO_INCREMENT PRIMARY KEY,
				`task_id`          BIGINT NOT NULL,
				`escalated_by`     VARCHAR(25) NOT NULL,
				`escalated_to`     VARCHAR(25) NOT NULL,
				`escalation_reason` TEXT NOT NULL,
				`resolution_notes` TEXT,
				`status`           VARCHAR(20) NOT NULL DEFAULT 'OPEN',
				`escalated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`acknowledged_at`  DATETIME DEFAULT NULL,
				`resolved_at`      DATETIME DEFAULT NULL,
				`resolved_by`      VARCHAR(25) DEFAULT NULL,
				`InActive`         INT NOT NULL DEFAULT 0,
				INDEX `idx_nte_task` (`task_id`),
				INDEX `idx_nte_status` (`status`),
				INDEX `idx_nte_to` (`escalated_to`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");

		$this->db->db_debug = $_dbg;
		return true;
	}

	/**
	 * Check if an index exists on a table.
	 */
	private function _index_exists($table, $index){
		$q = $this->db->query(
			"SELECT COUNT(1) AS c FROM information_schema.statistics "
			."WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
			array((string)$table, (string)$index)
		);
		$row = $q ? $q->row() : null;
		return ($row && isset($row->c) && (int)$row->c > 0);
	}

	/* ================================================================== */
	/*  STEP 3-4: TASK CREATION + BILLING                                 */
	/* ================================================================== */

	/**
	 * Generate a unique, human-readable task reference number.
	 * Format: NST-YYYYMMDD-NNNN
	 */
	public function generate_task_no($shift_date){
		$date_part = date('Ymd', strtotime((string)$shift_date));
		$q = $this->db->query("SELECT MAX(task_id) AS mx FROM nurse_shift_task");
		$row = $q ? $q->row() : null;
		$seq = ($row && isset($row->mx) && (int)$row->mx > 0) ? ((int)$row->mx + 1) : 1;
		return 'NST-' . $date_part . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Create a shift task with validation, room_id snapshot, and optional billing trigger.
	 *
	 * @param array  $data  Task data
	 * @param string $actor User ID of creator
	 * @return array {success, task_no, task_id} or {success, error}
	 */
	public function create_task($data, $actor){
		$this->ensure_nurse_shift_task_billing_schema();

		// --- Required fields ---
		$iop_id     = isset($data['iop_id']) ? trim((string)$data['iop_id']) : '';
		$patient_no = isset($data['patient_no']) ? trim((string)$data['patient_no']) : '';
		$shift_id   = isset($data['shift_id']) ? (int)$data['shift_id'] : 0;
		$shift_date = isset($data['shift_date']) ? (string)$data['shift_date'] : date('Y-m-d');
		$title      = isset($data['title']) ? trim((string)$data['title']) : '';

		if ($iop_id === '' || $patient_no === '' || $title === '') {
			return array('success' => false, 'error' => 'iop_id, patient_no, and title are required.');
		}

		// --- Validate iop_id ↔ patient_no binding ---
		$enc = $this->db->select('IO_ID, patient_no, room_id')
			->where('IO_ID', $iop_id)
			->where('InActive', 0)
			->get('patient_details_iop')->row();
		if (!$enc) {
			return array('success' => false, 'error' => 'Encounter not found: ' . $iop_id);
		}
		if ((string)$enc->patient_no !== $patient_no) {
			return array('success' => false, 'error' => 'patient_no does not match encounter.');
		}

		// --- Validate shift_id ---
		if ($shift_id > 0) {
			$shift = $this->db->get_where('nurse_shift', array('shift_id' => $shift_id, 'InActive' => 0))->row();
			if (!$shift) {
				return array('success' => false, 'error' => 'Invalid shift_id: ' . $shift_id);
			}
		}

		// --- Billing validation ---
		$is_billable = isset($data['is_billable']) ? (int)$data['is_billable'] : 0;
		$catalog_id  = isset($data['catalog_id']) ? (int)$data['catalog_id'] : 0;
		$item_source = isset($data['item_source']) ? trim((string)$data['item_source']) : 'PARTICULAR';
		if ($is_billable && $catalog_id <= 0) {
			return array('success' => false, 'error' => 'catalog_id is required when task is billable.');
		}

		// --- Build insert ---
		$task_no = $this->generate_task_no($shift_date);
		$task_category = isset($data['task_category']) ? trim((string)$data['task_category']) : 'OTHER';
		if (!array_key_exists($task_category, self::$TASK_CATEGORIES)) {
			$task_category = 'OTHER';
		}

		$insert = array(
			'task_no'       => $task_no,
			'shift_id'      => $shift_id > 0 ? $shift_id : null,
			'shift_date'    => $shift_date,
			'iop_id'        => $iop_id,
			'patient_no'    => $patient_no,
			'title'         => $title,
			'description'   => isset($data['description']) ? trim((string)$data['description']) : null,
			'task_category' => $task_category,
			'priority'      => isset($data['priority']) && in_array($data['priority'], array('NORMAL','URGENT','CRITICAL')) ? $data['priority'] : 'NORMAL',
			'status'        => 'OPEN',
			'assigned_to'   => isset($data['assigned_to']) && trim((string)$data['assigned_to']) !== '' ? (string)$data['assigned_to'] : null,
			'created_by'    => (string)$actor,
			'created_at'    => date('Y-m-d H:i:s'),
			'due_at'        => isset($data['due_at']) && trim((string)$data['due_at']) !== '' ? (string)$data['due_at'] : null,
			'room_id'       => isset($enc->room_id) ? (int)$enc->room_id : null,
			'is_billable'   => $is_billable ? 1 : 0,
			'billing_triggered' => 0,
			'catalog_id'    => $is_billable ? $catalog_id : null,
			'item_source'   => $is_billable ? $item_source : null,
			'source_type'   => isset($data['source_type']) ? trim((string)$data['source_type']) : 'MANUAL',
			'source_ref'    => isset($data['source_ref']) ? trim((string)$data['source_ref']) : null,
			'escalated'     => 0,
			'InActive'      => 0,
		);

		$ok = $this->db->insert('nurse_shift_task', $insert);
		if (!$ok) {
			$err = $this->db->error();
			return array('success' => false, 'error' => isset($err['message']) ? (string)$err['message'] : 'Insert failed');
		}
		$task_id = $this->db->insert_id();

		// --- Trigger billing if billable ---
		if ($is_billable && $catalog_id > 0) {
			$qty = isset($data['quantity']) ? (float)$data['quantity'] : 1.0;
			if ($qty <= 0) { $qty = 1.0; }
			$bill_result = $this->trigger_task_billing($task_id, $iop_id, $patient_no, $catalog_id, $item_source, $qty, $actor);
			if (!$bill_result['success']) {
				// Billing failed — soft-delete the task to avoid orphan
				$this->db->where('task_id', $task_id)->update('nurse_shift_task', array('InActive' => 1));
				return array('success' => false, 'error' => 'Task created but billing failed: ' . (isset($bill_result['error']) ? $bill_result['error'] : 'Unknown'));
			}
		}

		return array('success' => true, 'task_no' => $task_no, 'task_id' => (int)$task_id);
	}

	/**
	 * Trigger billing for a task. Idempotent — checks billing_triggered flag.
	 *
	 * CRITICAL SEQUENCE:
	 * A. Check billing_triggered guard
	 * B. Resolve price via Price_engine_model
	 * C. Call add_to_billing_queue
	 * D. Update task with queue_id atomically
	 */
	public function trigger_task_billing($task_id, $iop_id, $patient_no, $catalog_id, $item_source, $qty, $actor){
		$task_id    = (int)$task_id;
		$catalog_id = (int)$catalog_id;
		if ($qty <= 0) { $qty = 1.0; }

		// Step A: Idempotency — check billing_triggered
		$task = $this->db->select('billing_triggered, queue_id')
			->where('task_id', $task_id)
			->where('InActive', 0)
			->get('nurse_shift_task')->row();
		if (!$task) {
			return array('success' => false, 'error' => 'Task not found.');
		}
		if ((int)$task->billing_triggered === 1) {
			$existing_qid = isset($task->queue_id) ? (int)$task->queue_id : 0;
			return array('success' => true, 'queue_id' => $existing_qid, 'note' => 'Already billed');
		}

		// Step B: Resolve price
		$this->load->model('app/Price_engine_model', 'price_engine');
		$resolve_type = ($item_source === 'DRUG') ? 'PHARMACY' : 'PARTICULAR';
		$res = $this->price_engine->resolve(array(
			'item_type'  => $resolve_type,
			'item_id'    => $catalog_id,
			'quantity'   => $qty,
			'patient_no' => (string)$patient_no,
		));
		if (!is_array($res) || empty($res['ok'])) {
			$err_msg = (is_array($res) && isset($res['error'])) ? (string)$res['error'] : 'Price resolution failed';
			return array('success' => false, 'error' => $err_msg);
		}
		$resolved_price = (float)$res['unit_price'];
		$resolved_name  = isset($res['item_name']) ? (string)$res['item_name'] : 'Nursing Supply';
		if ($resolved_price <= 0.009) {
			return array('success' => false, 'error' => 'Price could not be resolved for this item. Contact administrator.');
		}

		// Step C: Insert into billing_queue
		$this->load->model('app/unified_billing_model', 'unified_billing');
		$bill_result = $this->unified_billing->add_to_billing_queue(array(
			'iop_id'        => (string)$iop_id,
			'patient_no'    => (string)$patient_no,
			'item_type'     => 'SUPPLY',
			'item_id'       => $catalog_id,
			'item_name'     => $resolved_name,
			'unit_price'    => $resolved_price,
			'quantity'      => $qty,
			'source_module' => 'NURSE_SHIFT_TASK',
			'source_ref'    => 'task:' . $task_id,
			'requested_by'  => (string)$actor,
		));
		if (!is_array($bill_result) || empty($bill_result['success'])) {
			$err_msg = (is_array($bill_result) && isset($bill_result['error'])) ? (string)$bill_result['error'] : 'Billing queue insert failed';
			return array('success' => false, 'error' => $err_msg);
		}
		$queue_id = isset($bill_result['queue_id']) ? (int)$bill_result['queue_id'] : 0;

		// Step D: Atomic update — set billing_triggered=1 only if still 0
		$this->db->where('task_id', $task_id)
			->where('billing_triggered', 0)
			->update('nurse_shift_task', array(
				'billing_triggered' => 1,
				'queue_id' => $queue_id,
			));

		return array('success' => true, 'queue_id' => $queue_id);
	}

	/* ================================================================== */
	/*  STEP 5: STATUS TRANSITIONS                                        */
	/* ================================================================== */

	/**
	 * Update task status with transition validation and billing guards.
	 */
	public function update_task_status($task_id, $new_status, $actor, $notes = ''){
		$task_id = (int)$task_id;
		$new_status = strtoupper(trim((string)$new_status));
		$task = $this->db->select('status, billing_triggered, is_billable')
			->where('task_id', $task_id)->where('InActive', 0)
			->get('nurse_shift_task')->row();
		if (!$task) {
			return array('success' => false, 'error' => 'Task not found.');
		}
		$current = strtoupper(trim((string)$task->status));

		// Define valid transitions
		$valid = array(
			'OPEN'        => array('IN_PROGRESS', 'CANCELLED', 'HANDED_OVER'),
			'IN_PROGRESS' => array('COMPLETED', 'HANDED_OVER'),
		);
		$allowed = isset($valid[$current]) ? $valid[$current] : array();
		if (!in_array($new_status, $allowed)) {
			return array('success' => false, 'error' => "Cannot transition from {$current} to {$new_status}.");
		}

		// Cancellation guard: cannot cancel a billed task
		if ($new_status === 'CANCELLED' && (int)$task->billing_triggered === 1) {
			return array('success' => false, 'error' => 'Cannot cancel a task with billing already triggered.');
		}

		$upd = array('status' => $new_status);
		if ($new_status === 'IN_PROGRESS') {
			if ($this->column_exists('nurse_shift_task', 'acknowledged_at')) {
				$upd['acknowledged_at'] = date('Y-m-d H:i:s');
			}
		}
		if ($new_status === 'COMPLETED') {
			$upd['completed_by'] = (string)$actor;
			$upd['completed_at'] = date('Y-m-d H:i:s');
		}
		if ($notes !== '' && $this->column_exists('nurse_shift_task', 'handover_notes')) {
			$upd['handover_notes'] = (string)$notes;
		}

		$this->db->where('task_id', $task_id)->update('nurse_shift_task', $upd);
		return array('success' => true);
	}

	/* ================================================================== */
	/*  STEP 6: TASK QUERIES                                               */
	/* ================================================================== */

	/**
	 * Get tasks for a shift with bed JOIN and role filtering.
	 */
	public function get_tasks_for_shift($ward, $shift_date, $shift_id, $role, $user_id){
		$this->ensure_nurse_shift_task_billing_schema();
		$shift_date = $shift_date ?: date('Y-m-d');

		$sql = "SELECT nst.*, 
				CONCAT(pp.firstname,' ',pp.lastname) AS patient_name,
				rb.bed_name,
				CONCAT(u1.firstname,' ',u1.lastname) AS created_by_name,
				CONCAT(u2.firstname,' ',u2.lastname) AS assigned_to_name
			FROM nurse_shift_task nst
			LEFT JOIN patient_details_iop pi ON pi.IO_ID = nst.iop_id AND pi.InActive = 0
			LEFT JOIN patient_personal_info pp ON pp.patient_no = nst.patient_no
			LEFT JOIN room_beds rb ON rb.patient_no = pi.patient_no 
				AND rb.room_master_id = pi.room_id AND rb.InActive = 0
			LEFT JOIN users u1 ON u1.user_id = nst.created_by
			LEFT JOIN users u2 ON u2.user_id = nst.assigned_to
			WHERE nst.InActive = 0 
			AND (nst.shift_date = " . $this->db->escape($shift_date) . " OR nst.shift_date IS NULL)";

		if ($shift_id > 0) {
			$sql .= " AND (nst.shift_id = " . (int)$shift_id . " OR nst.shift_id IS NULL)";
		}

		// Role filter: nurse sees own tasks + unassigned; admin/doctor sees all
		if ($role === 'nurse' && $user_id) {
			$sql .= " AND (nst.assigned_to = " . $this->db->escape($user_id) . " OR nst.assigned_to IS NULL)";
		}

		$sql .= " ORDER BY FIELD(nst.priority,'CRITICAL','URGENT','NORMAL'), 
				nst.due_at ASC, nst.created_at ASC";

		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Get overdue tasks (past due_at, not completed/cancelled/handed_over).
	 */
	public function get_overdue_tasks($ward = null){
		$sql = "SELECT nst.*, 
				CONCAT(pp.firstname,' ',pp.lastname) AS patient_name,
				rb.bed_name
			FROM nurse_shift_task nst
			LEFT JOIN patient_details_iop pi ON pi.IO_ID = nst.iop_id AND pi.InActive = 0
			LEFT JOIN patient_personal_info pp ON pp.patient_no = nst.patient_no
			LEFT JOIN room_beds rb ON rb.patient_no = pi.patient_no 
				AND rb.room_master_id = pi.room_id AND rb.InActive = 0
			WHERE nst.InActive = 0
			AND nst.status NOT IN ('COMPLETED','CANCELLED','HANDED_OVER')
			AND nst.due_at IS NOT NULL AND nst.due_at < NOW()
			ORDER BY nst.due_at ASC";

		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Get all tasks for a patient encounter.
	 */
	public function get_tasks_for_encounter($iop_id){
		$this->db->select('nst.*, CONCAT(u1.firstname," ",u1.lastname) AS created_by_name, CONCAT(u2.firstname," ",u2.lastname) AS completed_by_name, CONCAT(u3.firstname," ",u3.lastname) AS assigned_to_name', false);
		$this->db->from('nurse_shift_task nst');
		$this->db->join('users u1', 'u1.user_id = nst.created_by', 'left');
		$this->db->join('users u2', 'u2.user_id = nst.completed_by', 'left');
		$this->db->join('users u3', 'u3.user_id = nst.assigned_to', 'left');
		$this->db->where('nst.iop_id', (string)$iop_id);
		$this->db->where('nst.InActive', 0);
		$this->db->order_by('FIELD(nst.priority,"CRITICAL","URGENT","NORMAL")', '', false);
		$this->db->order_by('nst.created_at', 'DESC');
		return $this->db->get()->result();
	}

	/**
	 * Get tasks for a specific patient on a given shift/date.
	 * This is the PRIMARY query for the patient-driven dashboard.
	 */
	public function get_tasks_for_patient_shift($iop_id, $shift_date = null, $shift_id = 0){
		$shift_date = $shift_date ?: date('Y-m-d');

		$this->db->select('nst.*, CONCAT(u1.firstname," ",u1.lastname) AS created_by_name, CONCAT(u2.firstname," ",u2.lastname) AS completed_by_name, CONCAT(u3.firstname," ",u3.lastname) AS assigned_to_name', false);
		$this->db->from('nurse_shift_task nst');
		$this->db->join('users u1', 'u1.user_id = nst.created_by', 'left');
		$this->db->join('users u2', 'u2.user_id = nst.completed_by', 'left');
		$this->db->join('users u3', 'u3.user_id = nst.assigned_to', 'left');
		$this->db->where('nst.iop_id', (string)$iop_id);
		$this->db->where('nst.InActive', 0);
		$this->db->where('nst.shift_date', $shift_date);

		if ($shift_id > 0) {
			$this->db->group_start();
			$this->db->where('nst.shift_id', (int)$shift_id);
			$this->db->or_where('nst.shift_id IS NULL', null, false);
			$this->db->group_end();
		}

		$this->db->order_by('FIELD(nst.priority,"CRITICAL","URGENT","NORMAL")', '', false);
		$this->db->order_by('nst.due_at', 'ASC');
		$this->db->order_by('nst.created_at', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Get overdue tasks for a single patient encounter.
	 */
	public function get_overdue_tasks_for_patient($iop_id){
		$this->db->select('nst.*, CONCAT(u1.firstname," ",u1.lastname) AS assigned_to_name', false);
		$this->db->from('nurse_shift_task nst');
		$this->db->join('users u1', 'u1.user_id = nst.assigned_to', 'left');
		$this->db->where('nst.iop_id', (string)$iop_id);
		$this->db->where('nst.InActive', 0);
		$this->db->where_not_in('nst.status', array('COMPLETED', 'CANCELLED', 'HANDED_OVER'));
		$this->db->where('nst.due_at IS NOT NULL', null, false);
		$this->db->where('nst.due_at <', date('Y-m-d H:i:s'));
		$this->db->order_by('nst.due_at', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Get tasks pending for handover (OPEN/IN_PROGRESS for a given shift).
	 */
	public function get_pending_for_handover($ward, $shift_date, $shift_id){
		$shift_date = $shift_date ?: date('Y-m-d');
		$this->db->select('nst.*, CONCAT(pp.firstname," ",pp.lastname) AS patient_name', false);
		$this->db->from('nurse_shift_task nst');
		$this->db->join('patient_personal_info pp', 'pp.patient_no = nst.patient_no', 'left');
		$this->db->where('nst.InActive', 0);
		$this->db->where_in('nst.status', array('OPEN', 'IN_PROGRESS'));
		$this->db->where('nst.shift_date', $shift_date);
		if ($shift_id > 0) {
			$this->db->where('nst.shift_id', (int)$shift_id);
		}
		$this->db->order_by('FIELD(nst.priority,"CRITICAL","URGENT","NORMAL")', '', false);
		$this->db->order_by('nst.created_at', 'ASC');
		return $this->db->get()->result();
	}

	/* ================================================================== */
	/*  STEP 7: HANDOVER FLOW                                              */
	/* ================================================================== */

	/**
	 * Create a shift handover — links tasks and updates their status.
	 */
	public function create_handover($ward, $outgoing_shift_id, $shift_date, $task_ids, $notes, $actor){
		if (empty($task_ids) || !is_array($task_ids)) {
			return array('success' => false, 'error' => 'No tasks selected for handover.');
		}
		$this->ensure_nurse_shift_task_billing_schema();

		// Determine incoming shift (next in sequence: 1→2, 2→3, 3→1)
		$shift_map = array(1 => 2, 2 => 3, 3 => 1);
		$incoming = isset($shift_map[(int)$outgoing_shift_id]) ? $shift_map[(int)$outgoing_shift_id] : 1;

		$handover_no = 'HSO-' . date('Ymd', strtotime($shift_date)) . '-' . (int)$outgoing_shift_id . '-' . substr(uniqid(), -6);

		$insert = array(
			'handover_no'       => $handover_no,
			'ward'              => (string)$ward,
			'shift_date'        => $shift_date,
			'outgoing_shift_id' => (int)$outgoing_shift_id,
			'incoming_shift_id' => $incoming,
			'handover_by'       => (string)$actor,
			'general_notes'     => trim((string)$notes) !== '' ? (string)$notes : null,
			'status'            => 'PENDING',
			'InActive'          => 0,
		);

		$this->db->insert('nurse_shift_handover', $insert);
		$handover_id = $this->db->insert_id();
		if (!$handover_id) {
			return array('success' => false, 'error' => 'Failed to create handover record.');
		}

		// Link tasks and update status
		foreach ($task_ids as $tid) {
			$tid = (int)$tid;
			$this->db->insert('nurse_shift_handover_tasks', array(
				'handover_id' => $handover_id,
				'task_id'     => $tid,
				'InActive'    => 0,
			));
			$this->db->where('task_id', $tid)
				->where('InActive', 0)
				->where_in('status', array('OPEN', 'IN_PROGRESS'))
				->update('nurse_shift_task', array(
					'status' => 'HANDED_OVER',
					'handover_notes' => trim((string)$notes) !== '' ? (string)$notes : null,
				));
		}

		return array('success' => true, 'handover_no' => $handover_no, 'handover_id' => (int)$handover_id);
	}

	/**
	 * Acknowledge a handover — resets tasks to OPEN for incoming shift.
	 */
	public function acknowledge_handover($handover_id, $actor){
		$handover_id = (int)$handover_id;
		$ho = $this->db->get_where('nurse_shift_handover', array('handover_id' => $handover_id, 'InActive' => 0))->row();
		if (!$ho) {
			return array('success' => false, 'error' => 'Handover not found.');
		}
		if (strtoupper(trim((string)$ho->status)) === 'ACKNOWLEDGED') {
			return array('success' => true, 'tasks_reset' => 0, 'note' => 'Already acknowledged');
		}

		// Update handover record
		$this->db->where('handover_id', $handover_id)->update('nurse_shift_handover', array(
			'received_by' => (string)$actor,
			'received_at' => date('Y-m-d H:i:s'),
			'status'      => 'ACKNOWLEDGED',
		));

		// Get linked tasks and reset to OPEN
		$tasks = $this->db->select('task_id')
			->where('handover_id', $handover_id)
			->where('InActive', 0)
			->get('nurse_shift_handover_tasks')->result();

		$count = 0;
		foreach ($tasks as $t) {
			$this->db->where('task_id', (int)$t->task_id)
				->where('status', 'HANDED_OVER')
				->where('InActive', 0)
				->update('nurse_shift_task', array(
					'status' => 'OPEN',
					'acknowledged_at' => date('Y-m-d H:i:s'),
				));
			if ($this->db->affected_rows() > 0) { $count++; }
		}

		return array('success' => true, 'tasks_reset' => $count);
	}

	/**
	 * Get pending handovers for acknowledgement.
	 */
	public function get_pending_handovers($incoming_shift_id, $shift_date){
		$this->db->select('h.*, CONCAT(u.firstname," ",u.lastname) AS handover_by_name', false);
		$this->db->from('nurse_shift_handover h');
		$this->db->join('users u', 'u.user_id = h.handover_by', 'left');
		$this->db->where('h.incoming_shift_id', (int)$incoming_shift_id);
		$this->db->where('h.shift_date', $shift_date);
		$this->db->where('h.status', 'PENDING');
		$this->db->where('h.InActive', 0);
		$this->db->order_by('h.created_at', 'DESC');
		return $this->db->get()->result();
	}

	/* ================================================================== */
	/*  STEP 8: ESCALATION FLOW                                            */
	/* ================================================================== */

	/**
	 * Escalate a task — creates escalation record and marks task.
	 */
	public function escalate_task($task_id, $escalated_to, $reason, $actor){
		$task_id = (int)$task_id;
		$task = $this->db->select('status')
			->where('task_id', $task_id)->where('InActive', 0)
			->get('nurse_shift_task')->row();
		if (!$task) {
			return array('success' => false, 'error' => 'Task not found.');
		}
		if (in_array(strtoupper((string)$task->status), array('COMPLETED', 'CANCELLED'))) {
			return array('success' => false, 'error' => 'Cannot escalate a completed or cancelled task.');
		}

		$this->db->insert('nurse_task_escalation', array(
			'task_id'           => $task_id,
			'escalated_by'      => (string)$actor,
			'escalated_to'      => (string)$escalated_to,
			'escalation_reason' => (string)$reason,
			'status'            => 'OPEN',
			'InActive'          => 0,
		));
		$esc_id = $this->db->insert_id();

		// Mark task as escalated + CRITICAL priority
		$this->db->where('task_id', $task_id)->update('nurse_shift_task', array(
			'escalated' => 1,
			'priority'  => 'CRITICAL',
		));

		return array('success' => true, 'escalation_id' => (int)$esc_id);
	}

	/**
	 * Resolve an escalation.
	 */
	public function resolve_escalation($escalation_id, $resolution_notes, $actor){
		$escalation_id = (int)$escalation_id;
		$esc = $this->db->get_where('nurse_task_escalation', array('escalation_id' => $escalation_id, 'InActive' => 0))->row();
		if (!$esc) {
			return array('success' => false, 'error' => 'Escalation not found.');
		}

		$this->db->where('escalation_id', $escalation_id)->update('nurse_task_escalation', array(
			'status'           => 'RESOLVED',
			'resolved_by'      => (string)$actor,
			'resolved_at'      => date('Y-m-d H:i:s'),
			'resolution_notes' => (string)$resolution_notes,
		));

		// Check if other open escalations exist for same task
		$task_id = (int)$esc->task_id;
		$open_count = $this->db->where('task_id', $task_id)
			->where('status', 'OPEN')
			->where('InActive', 0)
			->count_all_results('nurse_task_escalation');

		if ($open_count === 0) {
			$this->db->where('task_id', $task_id)->update('nurse_shift_task', array('escalated' => 0));
		}

		return array('success' => true);
	}

	/**
	 * Get open escalations (for admin/doctor dashboard).
	 */
	public function get_open_escalations(){
		$sql = "SELECT e.*, nst.title AS task_title, nst.priority, nst.task_no,
				CONCAT(pp.firstname,' ',pp.lastname) AS patient_name,
				CONCAT(u1.firstname,' ',u1.lastname) AS escalated_by_name,
				CONCAT(u2.firstname,' ',u2.lastname) AS escalated_to_name
			FROM nurse_task_escalation e
			LEFT JOIN nurse_shift_task nst ON nst.task_id = e.task_id
			LEFT JOIN patient_personal_info pp ON pp.patient_no = nst.patient_no
			LEFT JOIN users u1 ON u1.user_id = e.escalated_by
			LEFT JOIN users u2 ON u2.user_id = e.escalated_to
			WHERE e.status = 'OPEN' AND e.InActive = 0
			ORDER BY e.escalated_at ASC";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}
}
