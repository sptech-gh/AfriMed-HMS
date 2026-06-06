<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Encounter_timeline_model extends CI_Model{

	public function __construct(){
		parent::__construct();
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function tables_ready(){
		return $this->table_exists('iop_encounter_timeline_event');
	}

	public function install_tables(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_encounter_timeline_event` (\n".
			"  `event_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(15) NOT NULL,\n".
			"  `patient_no` varchar(15) DEFAULT NULL,\n".
			"  `encounter_type` varchar(5) NOT NULL,\n".
			"  `event_type` varchar(40) NOT NULL,\n".
			"  `summary` text,\n".
			"  `payload_json` longtext,\n".
			"  `actor_user_id` varchar(15) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `InActive` int(1) NOT NULL DEFAULT 0,\n".
			"  PRIMARY KEY (`event_id`),\n".
			"  KEY `idx_iop` (`iop_id`,`encounter_type`),\n".
			"  KEY `idx_patient` (`patient_no`),\n".
			"  KEY `idx_type` (`event_type`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function append_event($iop_id, $patient_no, $encounter_type, $event_type, $summary = null, $payload = null, $actor_user_id = null){
		$iop_id = (string)$iop_id;
		$encounter_type = strtoupper(trim((string)$encounter_type));
		$event_type = strtoupper(trim((string)$event_type));
		if ($iop_id === '' || $encounter_type === '' || $event_type === '') {
			return array('ok' => false);
		}
		try {
			$this->install_tables();
			$payloadJson = null;
			if ($payload !== null) {
				if (is_string($payload)) {
					$payloadJson = $payload;
				} else {
					$payloadJson = json_encode($payload);
				}
			}
			$this->db->insert('iop_encounter_timeline_event', array(
				'iop_id' => $iop_id,
				'patient_no' => $patient_no !== null && $patient_no !== '' ? (string)$patient_no : null,
				'encounter_type' => $encounter_type,
				'event_type' => substr($event_type, 0, 40),
				'summary' => $summary !== null ? (string)$summary : null,
				'payload_json' => $payloadJson,
				'actor_user_id' => $actor_user_id !== null ? (string)$actor_user_id : (string)$this->session->userdata('user_id'),
				'created_at' => date('Y-m-d H:i:s'),
				'InActive' => 0
			));
			return array('ok' => true, 'event_id' => (int)$this->db->insert_id());
		} catch (Throwable $e) {
			log_message('error', 'Encounter timeline append failed: '.$e->getMessage());
			return array('ok' => false);
		}
	}

	public function get_events($iop_id, $encounter_type = null, $limit = 30){
		$iop_id = (string)$iop_id;
		if ($iop_id === '' || !$this->table_exists('iop_encounter_timeline_event')) {
			return array();
		}
		$limit = (int)$limit;
		if ($limit <= 0) $limit = 30;
		$this->db->from('iop_encounter_timeline_event');
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		if ($encounter_type !== null && trim((string)$encounter_type) !== '') {
			$this->db->where('encounter_type', strtoupper(trim((string)$encounter_type)));
		}
		$this->db->order_by('event_id', 'DESC');
		$this->db->limit($limit);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
}
