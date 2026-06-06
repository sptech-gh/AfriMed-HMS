<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Encounter_owner_model extends CI_Model{

	public function __construct(){
		parent::__construct();
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table_name, $column_name){
		$table_name = (string)$table_name;
		$column_name = (string)$column_name;
		if ($table_name === '' || $column_name === '') {
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		return ($q && $q->num_rows() > 0);
	}

	public function tables_ready(){
		return $this->table_exists('iop_encounter_owner') && $this->table_exists('iop_encounter_owner_audit');
	}

	public function ensure_override_page_exists(){
		if (!$this->table_exists('pages')) {
			return;
		}
		$this->db->select('page_id');
		$this->db->where(array('page_link' => 'doctor_override', 'InActive' => 0));
		$row = $this->db->get('pages')->row();
		if ($row) {
			return;
		}
		$this->db->insert('pages', array(
			'page_module' => 'doctor',
			'page_name' => 'Doctor Override Encounter Ownership',
			'page_link' => 'doctor_override',
			'InActive' => 0
		));
	}

	public function get_override_page_id(){
		if (!$this->table_exists('pages')) {
			return 0;
		}
		$this->db->select('page_id');
		$this->db->where(array('page_link' => 'doctor_override', 'InActive' => 0));
		$row = $this->db->get('pages')->row();
		return ($row && isset($row->page_id)) ? (int)$row->page_id : 0;
	}

	public function role_can_override($role_id){
		$role_id = (string)$role_id;
		if ($role_id === '') {
			return false;
		}
		$this->install_tables();
		$pageId = $this->get_override_page_id();
		if ($pageId <= 0 || !$this->table_exists('user_roles_pages')) {
			return false;
		}
		$this->db->select('page_id');
		$this->db->where(array('role_id' => $role_id, 'page_id' => (string)$pageId));
		$q = $this->db->get('user_roles_pages');
		return ($q && $q->num_rows() > 0);
	}

	public function install_tables(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_encounter_owner` (\n".
			"  `owner_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(15) NOT NULL,\n".
			"  `patient_no` varchar(15) DEFAULT NULL,\n".
			"  `encounter_type` varchar(5) NOT NULL,\n".
			"  `current_doctor_id` varchar(15) NOT NULL,\n".
			"  `assigned_at` datetime NOT NULL,\n".
			"  `assigned_by` varchar(15) DEFAULT NULL,\n".
			"  `assignment_reason` text,\n".
			"  `last_transfer_id` bigint(11) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL DEFAULT 0,\n".
			"  PRIMARY KEY (`owner_id`),\n".
			"  UNIQUE KEY `uniq_encounter` (`iop_id`,`encounter_type`),\n".
			"  KEY `idx_doctor` (`current_doctor_id`),\n".
			"  KEY `idx_patient` (`patient_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_encounter_owner_audit` (\n".
			"  `audit_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(15) DEFAULT NULL,\n".
			"  `patient_no` varchar(15) DEFAULT NULL,\n".
			"  `encounter_type` varchar(5) DEFAULT NULL,\n".
			"  `actor_user_id` varchar(15) DEFAULT NULL,\n".
			"  `action` varchar(30) NOT NULL,\n".
			"  `from_doctor_id` varchar(15) DEFAULT NULL,\n".
			"  `to_doctor_id` varchar(15) DEFAULT NULL,\n".
			"  `ipaddress` varchar(25) DEFAULT NULL,\n".
			"  `details` text,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  PRIMARY KEY (`audit_id`),\n".
			"  KEY `idx_iop` (`iop_id`,`patient_no`),\n".
			"  KEY `idx_action` (`action`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->ensure_override_page_exists();
		return true;
	}

	private function audit($iop_id, $patient_no, $encounter_type, $actor_user_id, $action, $from_doctor_id, $to_doctor_id, $details){
		if (!$this->table_exists('iop_encounter_owner_audit')) {
			return;
		}
		$this->db->insert('iop_encounter_owner_audit', array(
			'iop_id' => $iop_id !== null ? (string)$iop_id : null,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'encounter_type' => $encounter_type !== null ? (string)$encounter_type : null,
			'actor_user_id' => $actor_user_id !== null ? (string)$actor_user_id : null,
			'action' => (string)$action,
			'from_doctor_id' => $from_doctor_id !== null ? (string)$from_doctor_id : null,
			'to_doctor_id' => $to_doctor_id !== null ? (string)$to_doctor_id : null,
			'ipaddress' => $this->input->ip_address(),
			'details' => $details !== null ? (string)$details : null,
			'created_at' => date('Y-m-d H:i:s')
		));
	}

	public function logfile($module, $event, $value, $user_id = null){
		if (!$this->table_exists('logfile')) {
			return;
		}
		$this->db->insert('logfile', array(
			'user_id' => $user_id !== null ? (string)$user_id : (string)$this->session->userdata('user_id'),
			'module' => (string)$module,
			'event' => substr((string)$event, 0, 50),
			'value' => (string)$value,
			'ipaddress' => $this->input->ip_address(),
			'date_time' => date('Y-m-d h:i:s')
		));
	}

	public function record_event($iop_id, $patient_no, $encounter_type, $action, $from_doctor_id = null, $to_doctor_id = null, $details = null, $actor_user_id = null){
		$this->install_tables();
		$actor_user_id = $actor_user_id !== null ? (string)$actor_user_id : (string)$this->session->userdata('user_id');
		$this->audit($iop_id, $patient_no, $encounter_type, $actor_user_id, $action, $from_doctor_id, $to_doctor_id, $details);
	}

	public function get_owner_row($iop_id, $encounter_type = null){
		if (!$this->table_exists('iop_encounter_owner')) {
			return null;
		}
		$iop_id = (string)$iop_id;
		if ($iop_id === '') {
			return null;
		}
		if ($encounter_type !== null && $encounter_type !== '') {
			$this->db->where(array('iop_id' => $iop_id, 'encounter_type' => (string)$encounter_type, 'InActive' => 0));
			return $this->db->get('iop_encounter_owner', 1)->row();
		}
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		return $this->db->get('iop_encounter_owner', 1)->row();
	}

	public function ensure_owner_from_patient_details($iop_id, $patient_no = null){
		$this->install_tables();
		$iop_id = (string)$iop_id;
		if ($iop_id === '') {
			return null;
		}
		$enc = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0), 1)->row();
		if (!$enc) {
			return null;
		}
		$encounter_type = isset($enc->patient_type) ? (string)$enc->patient_type : 'OPD';
		$existing = $this->get_owner_row($iop_id, $encounter_type);
		if ($existing) {
			return $existing;
		}
		$now = date('Y-m-d H:i:s');
		$this->db->insert('iop_encounter_owner', array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no !== null && $patient_no !== '' ? (string)$patient_no : (isset($enc->patient_no) ? (string)$enc->patient_no : null),
			'encounter_type' => $encounter_type,
			'current_doctor_id' => isset($enc->doctor_id) ? (string)$enc->doctor_id : '',
			'assigned_at' => $now,
			'assigned_by' => (string)$this->session->userdata('user_id'),
			'assignment_reason' => 'auto_seed',
			'InActive' => 0
		));
		$this->audit($iop_id, isset($enc->patient_no) ? (string)$enc->patient_no : null, $encounter_type, (string)$this->session->userdata('user_id'), 'SEEDED', null, isset($enc->doctor_id) ? (string)$enc->doctor_id : null, null);
		return $this->get_owner_row($iop_id, $encounter_type);
	}

	public function assign_owner($iop_id, $patient_no, $encounter_type, $doctor_id, $assigned_by, $reason = null, $transfer_id = null){
		$this->install_tables();
		$iop_id = (string)$iop_id;
		$encounter_type = (string)$encounter_type;
		$doctor_id = trim((string)$doctor_id);
		if ($iop_id === '' || $encounter_type === '' || $doctor_id === '') {
			return array('ok' => false);
		}
		$existing = $this->get_owner_row($iop_id, $encounter_type);
		$now = date('Y-m-d H:i:s');
		$data = array(
			'patient_no' => $patient_no !== null && $patient_no !== '' ? (string)$patient_no : null,
			'current_doctor_id' => $doctor_id,
			'assigned_at' => $now,
			'assigned_by' => $assigned_by !== null ? (string)$assigned_by : null,
			'assignment_reason' => $reason !== null ? (string)$reason : null,
			'last_transfer_id' => $transfer_id !== null ? (int)$transfer_id : null,
			'InActive' => 0
		);
		if ($existing) {
			$from = isset($existing->current_doctor_id) ? (string)$existing->current_doctor_id : null;
			$this->db->where(array('iop_id' => $iop_id, 'encounter_type' => $encounter_type));
			$this->db->update('iop_encounter_owner', $data);
			$this->audit($iop_id, $patient_no, $encounter_type, $assigned_by, 'OWNER_CHANGED', $from, $doctor_id, $reason);
			return array('ok' => true, 'from_doctor_id' => $from);
		}
		$this->db->insert('iop_encounter_owner', array_merge(array(
			'iop_id' => $iop_id,
			'encounter_type' => $encounter_type
		), $data));
		$this->audit($iop_id, $patient_no, $encounter_type, $assigned_by, 'OWNER_ASSIGNED', null, $doctor_id, $reason);
		return array('ok' => true, 'from_doctor_id' => null);
	}

	public function is_owner($iop_id, $doctor_id, $encounter_type = null){
		$iop_id = (string)$iop_id;
		$doctor_id = (string)$doctor_id;
		if ($iop_id === '' || $doctor_id === '') {
			return false;
		}
		$owner = $this->get_owner_row($iop_id, $encounter_type);
		if ($owner && isset($owner->current_doctor_id) && (string)$owner->current_doctor_id !== '') {
			return ((string)$owner->current_doctor_id === $doctor_id);
		}
		$enc = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0), 1)->row();
		return ($enc && isset($enc->doctor_id) && (string)$enc->doctor_id === $doctor_id);
	}

	public function get_owner_doctor_id($iop_id, $encounter_type = null){
		$owner = $this->get_owner_row($iop_id, $encounter_type);
		if ($owner && isset($owner->current_doctor_id) && (string)$owner->current_doctor_id !== '') {
			return (string)$owner->current_doctor_id;
		}
		$enc = $this->db->get_where('patient_details_iop', array('IO_ID' => (string)$iop_id, 'InActive' => 0), 1)->row();
		return ($enc && isset($enc->doctor_id)) ? (string)$enc->doctor_id : '';
	}
}
