<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Doctor_transfer_model extends CI_Model {

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
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		return ($q && $q->num_rows() > 0);
	}

	public function tables_ready(){
		$ok = $this->table_exists('doctor_patient_transfer')
			&& $this->table_exists('doctor_patient_transfer_note')
			&& $this->table_exists('doctor_patient_transfer_audit');
		if ($ok) {
			$this->ensure_transfer_columns();
		}
		return $ok;
	}

	public function ensure_transfer_columns(){
		if (!$this->table_exists('doctor_patient_transfer')) {
			return;
		}
		if (!$this->column_exists('doctor_patient_transfer', 'reason_code')) {
			$this->db->query("ALTER TABLE doctor_patient_transfer ADD COLUMN reason_code varchar(50) DEFAULT NULL");
		}
		if (!$this->column_exists('doctor_patient_transfer', 'urgency_level')) {
			$this->db->query("ALTER TABLE doctor_patient_transfer ADD COLUMN urgency_level varchar(20) NOT NULL DEFAULT 'NORMAL'");
		}
		if (!$this->column_exists('doctor_patient_transfer', 'handover_checklist_json')) {
			$this->db->query("ALTER TABLE doctor_patient_transfer ADD COLUMN handover_checklist_json text");
		}
		if (!$this->column_exists('doctor_patient_transfer', 'first_viewed_at')) {
			$this->db->query("ALTER TABLE doctor_patient_transfer ADD COLUMN first_viewed_at datetime DEFAULT NULL");
		}
		if (!$this->column_exists('doctor_patient_transfer', 'first_viewed_by')) {
			$this->db->query("ALTER TABLE doctor_patient_transfer ADD COLUMN first_viewed_by varchar(15) DEFAULT NULL");
		}
	}

	public function count_incoming_pending($doctor_id){
		if (!$this->tables_ready()) {
			return 0;
		}
		$this->db->where(array(
			'to_doctor_id' => (string)$doctor_id,
			'status' => 'PENDING'
		));
		return (int) $this->db->count_all_results('doctor_patient_transfer');
	}

	public function install_tables(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `doctor_patient_transfer` (\n".
			"  `transfer_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(15) NOT NULL,\n".
			"  `patient_no` varchar(15) NOT NULL,\n".
			"  `patient_type` varchar(5) DEFAULT NULL,\n".
			"  `from_doctor_id` varchar(15) NOT NULL,\n".
			"  `to_doctor_id` varchar(15) NOT NULL,\n".
			"  `status` varchar(20) NOT NULL DEFAULT 'PENDING',\n".
			"  `reason_code` varchar(50) DEFAULT NULL,\n".
			"  `urgency_level` varchar(20) NOT NULL DEFAULT 'NORMAL',\n".
			"  `reason` text,\n".
			"  `handover_checklist_json` text,\n".
			"  `requested_at` datetime NOT NULL,\n".
			"  `responded_at` datetime DEFAULT NULL,\n".
			"  `accepted_at` datetime DEFAULT NULL,\n".
			"  `rejected_at` datetime DEFAULT NULL,\n".
			"  `canceled_at` datetime DEFAULT NULL,\n".
			"  `first_viewed_at` datetime DEFAULT NULL,\n".
			"  `first_viewed_by` varchar(15) DEFAULT NULL,\n".
			"  `created_by` varchar(15) NOT NULL,\n".
			"  `updated_by` varchar(15) DEFAULT NULL,\n".
			"  PRIMARY KEY (`transfer_id`),\n".
			"  KEY `idx_to_doctor_status` (`to_doctor_id`,`status`),\n".
			"  KEY `idx_from_doctor_status` (`from_doctor_id`,`status`),\n".
			"  KEY `idx_iop` (`iop_id`,`patient_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->ensure_transfer_columns();

		$this->db->query("CREATE TABLE IF NOT EXISTS `doctor_patient_transfer_note` (\n".
			"  `note_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `transfer_id` bigint(11) NOT NULL,\n".
			"  `author_user_id` varchar(15) NOT NULL,\n".
			"  `note` text NOT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  PRIMARY KEY (`note_id`),\n".
			"  KEY `idx_transfer` (`transfer_id`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `doctor_patient_transfer_audit` (\n".
			"  `audit_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `transfer_id` bigint(11) DEFAULT NULL,\n".
			"  `iop_id` varchar(15) DEFAULT NULL,\n".
			"  `patient_no` varchar(15) DEFAULT NULL,\n".
			"  `actor_user_id` varchar(15) DEFAULT NULL,\n".
			"  `action` varchar(30) NOT NULL,\n".
			"  `from_doctor_id` varchar(15) DEFAULT NULL,\n".
			"  `to_doctor_id` varchar(15) DEFAULT NULL,\n".
			"  `ipaddress` varchar(25) DEFAULT NULL,\n".
			"  `details` text,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  PRIMARY KEY (`audit_id`),\n".
			"  KEY `idx_transfer` (`transfer_id`),\n".
			"  KEY `idx_iop` (`iop_id`,`patient_no`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		return true;
	}

	public function build_handover_checklist($iop_id, $patient_no){
		$iop_id = (string)$iop_id;
		$patient_no = (string)$patient_no;
		$out = array(
			'pending_labs' => array(),
			'pending_imaging' => array(),
			'active_medications' => array(),
			'allergies' => '',
			'warnings' => '',
			'billing_flags' => array()
		);

		$enc = $this->get_encounter($iop_id, $patient_no);
		if ($enc) {
			$out['allergies'] = isset($enc->allergies) ? (string)$enc->allergies : '';
			$out['warnings'] = isset($enc->warnings) ? (string)$enc->warnings : '';
		}

		if ($this->table_exists('iop_laboratory')) {
			$this->db->select('laboratory_text, category_id');
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			$this->db->where("(result IS NULL OR TRIM(result) = '')", null, false);
			$this->db->order_by('io_lab_id', 'desc');
			$this->db->limit(20);
			$rows = $this->db->get('iop_laboratory')->result();
			foreach ($rows as $r) {
				$name = isset($r->laboratory_text) ? trim((string)$r->laboratory_text) : '';
				$cat = isset($r->category_id) ? (string)$r->category_id : '';
				if ($name === '') {
					continue;
				}
				if ($cat === '18' || $cat === 18) {
					$out['pending_imaging'][] = $name;
				} else {
					$out['pending_labs'][] = $name;
				}
			}
		}

		if ($this->table_exists('iop_medication')) {
			$this->db->select('medicine_text');
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			$this->db->order_by('iop_med_id', 'desc');
			$this->db->limit(20);
			$rows = $this->db->get('iop_medication')->result();
			foreach ($rows as $r) {
				$name = isset($r->medicine_text) ? trim((string)$r->medicine_text) : '';
				if ($name !== '') {
					$out['active_medications'][] = $name;
				}
			}
		}

		$out['billing_flags'] = array('critical_unpaid_items' => false);
		$CI = &get_instance();
		if ($CI) {
			$CI->load->model('app/billing_model');
			if (isset($CI->billing_model) && method_exists($CI->billing_model, 'has_unpaid_billable_locks_for_iop')) {
				$out['billing_flags']['critical_unpaid_items'] = (bool)$CI->billing_model->has_unpaid_billable_locks_for_iop($iop_id);
			}
		}

		return $out;
	}

	public function create_transfer_request($iop_id, $patient_no, $to_doctor_id, $reason, $handover_note, $reason_code = null, $urgency_level = null){
		$from_doctor_id = (string) $this->session->userdata('user_id');

		$encounter = $this->get_encounter($iop_id, $patient_no);
		if (!$encounter) {
			return array('ok' => false, 'error' => 'encounter_not_found');
		}

		if ((string)$encounter->doctor_id !== (string)$from_doctor_id) {
			return array('ok' => false, 'error' => 'not_owner');
		}

		if ((string)$to_doctor_id === (string)$from_doctor_id) {
			return array('ok' => false, 'error' => 'invalid_target');
		}

		$existing = $this->db->get_where('doctor_patient_transfer', array(
			'iop_id' => (string)$iop_id,
			'patient_no' => (string)$patient_no,
			'status' => 'PENDING'
		))->row();
		if ($existing) {
			return array('ok' => false, 'error' => 'pending_exists', 'transfer_id' => $existing->transfer_id);
		}

		$now = date('Y-m-d H:i:s');
		$urgency_level = strtoupper(trim((string)$urgency_level));
		if ($urgency_level !== 'URGENT' && $urgency_level !== 'CRITICAL') {
			$urgency_level = 'NORMAL';
		}
		$checklist = $this->build_handover_checklist($iop_id, $patient_no);
		$data = array(
			'iop_id' => (string) $iop_id,
			'patient_no' => (string) $patient_no,
			'patient_type' => isset($encounter->patient_type) ? (string)$encounter->patient_type : null,
			'from_doctor_id' => (string) $from_doctor_id,
			'to_doctor_id' => (string) $to_doctor_id,
			'status' => 'PENDING',
			'reason_code' => $reason_code !== null ? trim((string)$reason_code) : null,
			'urgency_level' => $urgency_level,
			'reason' => (string) $reason,
			'handover_checklist_json' => json_encode($checklist),
			'requested_at' => $now,
			'created_by' => (string) $from_doctor_id
		);

		$this->db->insert('doctor_patient_transfer', $data);
		$transfer_id = $this->db->insert_id();

		$this->audit($transfer_id, $iop_id, $patient_no, $from_doctor_id, 'REQUESTED', $from_doctor_id, $to_doctor_id, $reason);

		$handover_note = trim((string)$handover_note);
		if ($handover_note !== '') {
			$this->add_note($transfer_id, $from_doctor_id, $handover_note);
		}

		return array('ok' => true, 'transfer_id' => $transfer_id);
	}

	public function mark_first_viewed($transfer_id, $doctor_id){
		$transfer_id = (int)$transfer_id;
		$doctor_id = (string)$doctor_id;
		if ($transfer_id <= 0 || $doctor_id === '' || !$this->table_exists('doctor_patient_transfer')) {
			return false;
		}
		$this->ensure_transfer_columns();
		$this->db->where(array('transfer_id' => $transfer_id));
		$this->db->where("(first_viewed_at IS NULL OR CAST(first_viewed_at AS CHAR) = '0000-00-00 00:00:00')", null, false);
		$this->db->update('doctor_patient_transfer', array(
			'first_viewed_at' => date('Y-m-d H:i:s'),
			'first_viewed_by' => $doctor_id
		));
		return ($this->db->affected_rows() > 0);
	}

	public function get_incoming($doctor_id, $limit = 20, $offset = 0){
		$this->db->select('T.*, P.firstname, P.lastname, P.middlename');
		$this->db->from('doctor_patient_transfer T');
		$this->db->join('patient_personal_info P', 'P.patient_no = T.patient_no', 'left');
		$this->db->where('T.to_doctor_id', (string)$doctor_id);
		$this->db->order_by('T.requested_at', 'desc');
		$this->db->limit((int)$limit, (int)$offset);
		return $this->db->get()->result();
	}

	public function get_outgoing($doctor_id, $limit = 20, $offset = 0){
		$this->db->select('T.*, P.firstname, P.lastname, P.middlename');
		$this->db->from('doctor_patient_transfer T');
		$this->db->join('patient_personal_info P', 'P.patient_no = T.patient_no', 'left');
		$this->db->where('T.from_doctor_id', (string)$doctor_id);
		$this->db->order_by('T.requested_at', 'desc');
		$this->db->limit((int)$limit, (int)$offset);
		return $this->db->get()->result();
	}

	public function get_transfer($transfer_id){
		return $this->db->get_where('doctor_patient_transfer', array('transfer_id' => (int)$transfer_id))->row();
	}

	public function get_notes($transfer_id){
		$this->db->order_by('created_at', 'asc');
		return $this->db->get_where('doctor_patient_transfer_note', array('transfer_id' => (int)$transfer_id))->result();
	}

	public function add_note($transfer_id, $author_user_id, $note){
		$data = array(
			'transfer_id' => (int)$transfer_id,
			'author_user_id' => (string)$author_user_id,
			'note' => (string)$note,
			'created_at' => date('Y-m-d H:i:s')
		);
		$this->db->insert('doctor_patient_transfer_note', $data);
		$this->audit($transfer_id, null, null, $author_user_id, 'NOTE_ADDED', null, null, null);
		return true;
	}

	public function accept($transfer_id, $handover_note){
		$actor = (string) $this->session->userdata('user_id');
		$transfer = $this->get_transfer($transfer_id);
		if (!$transfer) {
			return array('ok' => false, 'error' => 'not_found');
		}
		if ($transfer->status !== 'PENDING') {
			return array('ok' => false, 'error' => 'not_pending');
		}
		if ((string)$transfer->to_doctor_id !== (string)$actor) {
			return array('ok' => false, 'error' => 'not_allowed');
		}

		$encounter = $this->get_encounter($transfer->iop_id, $transfer->patient_no);
		if (!$encounter) {
			return array('ok' => false, 'error' => 'encounter_not_found');
		}
		if ((string)$encounter->doctor_id !== (string)$transfer->from_doctor_id) {
			return array('ok' => false, 'error' => 'owner_changed');
		}

		$this->db->where(array('IO_ID' => (string)$transfer->iop_id, 'patient_no' => (string)$transfer->patient_no));
		$this->db->update('patient_details_iop', array('doctor_id' => (string)$transfer->to_doctor_id));

		$CI = &get_instance();
		if ($CI) {
			$CI->load->model('app/encounter_owner_model');
			if (isset($CI->encounter_owner_model)) {
				$CI->encounter_owner_model->assign_owner(
					(string)$transfer->iop_id,
					(string)$transfer->patient_no,
					(string)$transfer->patient_type,
					(string)$transfer->to_doctor_id,
					$actor,
					'transfer_accept',
					(int)$transfer_id
				);
				$CI->encounter_owner_model->logfile(
					'DoctorTransfer',
					'TRANSFER',
					'from:'.(string)$transfer->from_doctor_id.'|to:'.(string)$transfer->to_doctor_id.'|iop:'.(string)$transfer->iop_id.'|reason:'.(string)$transfer->reason.'|urgency:'.(isset($transfer->urgency_level) ? (string)$transfer->urgency_level : 'NORMAL'),
					$actor
				);
			}
		}

		$now = date('Y-m-d H:i:s');
		$this->db->where('transfer_id', (int)$transfer_id);
		$this->db->update('doctor_patient_transfer', array(
			'status' => 'ACCEPTED',
			'responded_at' => $now,
			'accepted_at' => $now,
			'updated_by' => $actor
		));

		$this->audit($transfer_id, $transfer->iop_id, $transfer->patient_no, $actor, 'ACCEPTED', $transfer->from_doctor_id, $transfer->to_doctor_id, null);

		$handover_note = trim((string)$handover_note);
		if ($handover_note !== '') {
			$this->add_note($transfer_id, $actor, $handover_note);
		}

		return array('ok' => true);
	}

	public function reject($transfer_id, $note){
		$actor = (string) $this->session->userdata('user_id');
		$transfer = $this->get_transfer($transfer_id);
		if (!$transfer) {
			return array('ok' => false, 'error' => 'not_found');
		}
		if ($transfer->status !== 'PENDING') {
			return array('ok' => false, 'error' => 'not_pending');
		}
		if ((string)$transfer->to_doctor_id !== (string)$actor) {
			return array('ok' => false, 'error' => 'not_allowed');
		}

		$now = date('Y-m-d H:i:s');
		$this->db->where('transfer_id', (int)$transfer_id);
		$this->db->update('doctor_patient_transfer', array(
			'status' => 'REJECTED',
			'responded_at' => $now,
			'rejected_at' => $now,
			'updated_by' => $actor
		));

		$this->audit($transfer_id, $transfer->iop_id, $transfer->patient_no, $actor, 'REJECTED', $transfer->from_doctor_id, $transfer->to_doctor_id, null);

		$note = trim((string)$note);
		if ($note !== '') {
			$this->add_note($transfer_id, $actor, $note);
		}

		return array('ok' => true);
	}

	public function cancel($transfer_id){
		$actor = (string) $this->session->userdata('user_id');
		$transfer = $this->get_transfer($transfer_id);
		if (!$transfer) {
			return array('ok' => false, 'error' => 'not_found');
		}
		if ($transfer->status !== 'PENDING') {
			return array('ok' => false, 'error' => 'not_pending');
		}
		if ((string)$transfer->from_doctor_id !== (string)$actor) {
			return array('ok' => false, 'error' => 'not_allowed');
		}

		$now = date('Y-m-d H:i:s');
		$this->db->where('transfer_id', (int)$transfer_id);
		$this->db->update('doctor_patient_transfer', array(
			'status' => 'CANCELED',
			'canceled_at' => $now,
			'updated_by' => $actor
		));

		$this->audit($transfer_id, $transfer->iop_id, $transfer->patient_no, $actor, 'CANCELED', $transfer->from_doctor_id, $transfer->to_doctor_id, null);

		return array('ok' => true);
	}

	public function get_encounter($iop_id, $patient_no){
		return $this->db->get_where('patient_details_iop', array('IO_ID' => (string)$iop_id, 'patient_no' => (string)$patient_no, 'InActive' => 0))->row();
	}

	private function audit($transfer_id, $iop_id, $patient_no, $actor_user_id, $action, $from_doctor_id, $to_doctor_id, $details){
		$data = array(
			'transfer_id' => $transfer_id ? (int)$transfer_id : null,
			'iop_id' => $iop_id !== null ? (string)$iop_id : null,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'actor_user_id' => $actor_user_id !== null ? (string)$actor_user_id : null,
			'action' => (string)$action,
			'from_doctor_id' => $from_doctor_id !== null ? (string)$from_doctor_id : null,
			'to_doctor_id' => $to_doctor_id !== null ? (string)$to_doctor_id : null,
			'ipaddress' => $this->input->ip_address(),
			'details' => $details !== null ? (string)$details : null,
			'created_at' => date('Y-m-d H:i:s')
		);
		$this->db->insert('doctor_patient_transfer_audit', $data);
		return true;
	}
}
