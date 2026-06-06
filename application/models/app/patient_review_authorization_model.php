<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Patient_review_authorization_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	public function ensure_schema()
	{
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$ok = $this->db->query("CREATE TABLE IF NOT EXISTS `patient_review_authorizations` (
				`id` bigint(20) NOT NULL AUTO_INCREMENT,
				`patient_id` varchar(25) NOT NULL,
				`originating_visit_id` varchar(25) DEFAULT NULL,
				`doctor_id` varchar(25) DEFAULT NULL,
				`review_start_date` date NOT NULL,
				`review_expiry_date` date NOT NULL,
				`status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
				`notes` text DEFAULT NULL,
				`created_at` datetime NOT NULL,
				`updated_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_patient_status` (`patient_id`,`status`,`InActive`),
				KEY `idx_expiry` (`review_expiry_date`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			if ($ok === false) {
				$err = $this->db->error();
				$msg = (is_array($err) && isset($err['message'])) ? (string)$err['message'] : '';
				log_message('error', 'patient_review_authorizations schema ensure failed: ' . $msg);
			}
		} catch (Throwable $e) {
			log_message('error', 'patient_review_authorizations schema ensure exception: ' . $e->getMessage());
		}
		if ($prev !== null) { $this->db->db_debug = $prev; }
	}

	public function create_authorization($patient_id, $originating_visit_id, $doctor_id, $review_start_date, $review_expiry_date, $notes = null, $user_id = null)
	{
		$this->ensure_schema();
		$patient_id = trim((string)$patient_id);
		if ($patient_id === '') {
			return array('ok' => false, 'error' => 'Missing patient_id');
		}
		$review_start_date = $review_start_date ? date('Y-m-d', strtotime((string)$review_start_date)) : date('Y-m-d');
		$review_expiry_date = $review_expiry_date ? date('Y-m-d', strtotime((string)$review_expiry_date)) : $review_start_date;
		if ($review_expiry_date < $review_start_date) {
			$review_expiry_date = $review_start_date;
		}
		$row = array(
			'patient_id' => $patient_id,
			'originating_visit_id' => $originating_visit_id !== null ? trim((string)$originating_visit_id) : null,
			'doctor_id' => $doctor_id !== null ? trim((string)$doctor_id) : null,
			'review_start_date' => $review_start_date,
			'review_expiry_date' => $review_expiry_date,
			'status' => 'ACTIVE',
			'notes' => $notes !== null && trim((string)$notes) !== '' ? (string)$notes : null,
			'created_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
			'InActive' => 0,
		);
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->insert('patient_review_authorizations', $row);
			return array('ok' => true, 'id' => (int)$this->db->insert_id());
		} catch (Throwable $e) {
			return array('ok' => false, 'error' => 'Insert failed');
		} finally {
			if ($prev !== null) { $this->db->db_debug = $prev; }
		}
	}

	public function expire_stale_authorizations($patient_id = null)
	{
		$this->ensure_schema();
		$today = date('Y-m-d');
		$this->db->where('InActive', 0);
		$this->db->where('status', 'ACTIVE');
		$this->db->where('review_expiry_date <', $today);
		if ($patient_id !== null && trim((string)$patient_id) !== '') {
			$this->db->where('patient_id', trim((string)$patient_id));
		}
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->update('patient_review_authorizations', array(
				'status' => 'EXPIRED',
				'updated_at' => date('Y-m-d H:i:s'),
			));
			return array('ok' => true, 'updated' => (int)$this->db->affected_rows());
		} catch (Throwable $e) {
			return array('ok' => false, 'error' => 'Expire update failed');
		} finally {
			if ($prev !== null) { $this->db->db_debug = $prev; }
		}
	}

	public function get_active_authorization_for_date($patient_id, $date = null)
	{
		$this->ensure_schema();
		if (!isset($this->db) || !is_object($this->db) || !method_exists($this->db, 'table_exists') || !$this->db->table_exists('patient_review_authorizations')) {
			log_message('error', 'patient_review_authorizations missing - review authorization lookup disabled');
			return null;
		}
		$patient_id = trim((string)$patient_id);
		if ($patient_id === '') return null;
		$this->expire_stale_authorizations($patient_id);
		$date = $date ? date('Y-m-d', strtotime((string)$date)) : date('Y-m-d');

		$this->db->where('patient_id', $patient_id);
		$this->db->where('status', 'ACTIVE');
		$this->db->where('InActive', 0);
		$this->db->where('review_start_date <=', $date);
		$this->db->where('review_expiry_date >=', $date);
		$this->db->order_by('id', 'DESC');
		$this->db->limit(1);
		$q = $this->db->get('patient_review_authorizations');
		if (!$q) {
			$err = $this->db->error();
			$msg = (is_array($err) && isset($err['message'])) ? (string)$err['message'] : '';
			log_message('error', 'patient_review_authorizations lookup failed: ' . $msg);
			return null;
		}
		return $q->row();
	}

	public function mark_used($id)
	{
		$this->ensure_schema();
		$id = (int)$id;
		if ($id <= 0) return array('ok' => false, 'error' => 'Invalid id');
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->where(array('id' => $id, 'InActive' => 0));
			$this->db->where('status', 'ACTIVE');
			$this->db->update('patient_review_authorizations', array(
				'status' => 'USED',
				'updated_at' => date('Y-m-d H:i:s'),
			));
			return array('ok' => true, 'updated' => (int)$this->db->affected_rows());
		} catch (Throwable $e) {
			return array('ok' => false, 'error' => 'Update failed');
		} finally {
			if ($prev !== null) { $this->db->db_debug = $prev; }
		}
	}
}
