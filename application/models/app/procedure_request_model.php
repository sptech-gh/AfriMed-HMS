<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Procedure_request_model extends CI_Model
{
	private static $_schema_done = false;

	public function __construct()
	{
		parent::__construct();
	}

	public function ensure_schema()
	{
		if (self::$_schema_done) return;
		self::$_schema_done = true;

		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) $this->db->db_debug = false;

		try {
			if (!$this->db->table_exists('iop_procedure_request')) {
				$this->db->query("CREATE TABLE `iop_procedure_request` (
					`request_id` INT AUTO_INCREMENT PRIMARY KEY,
					`iop_id` VARCHAR(25) NOT NULL,
					`patient_no` VARCHAR(25) NOT NULL,
					`procedure_id` INT NOT NULL,
					`procedure_name` VARCHAR(255) DEFAULT NULL,
					`category_id` INT DEFAULT NULL,
					`qty` DECIMAL(10,2) NOT NULL DEFAULT 1,
					`notes` TEXT,
					`requested_by` VARCHAR(25) DEFAULT NULL,
					`requested_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
					`status` VARCHAR(30) NOT NULL DEFAULT 'REQUESTED',
					`encounter_type` VARCHAR(10) NOT NULL DEFAULT 'OPD',
					`InActive` TINYINT(1) DEFAULT 0,
					`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
					`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX `idx_pr_iop` (`iop_id`),
					INDEX `idx_pr_patient` (`patient_no`),
					INDEX `idx_pr_proc` (`procedure_id`),
					INDEX `idx_pr_status` (`status`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
			}
		} catch (\Throwable $e) {
			log_message('error', 'Procedure_request_model ensure_schema: ' . $e->getMessage());
		}

		if ($prev !== null) $this->db->db_debug = $prev;
	}

	public function list_for_visit($iop_id)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '' || !$this->db->table_exists('iop_procedure_request')) {
			return array();
		}

		$has_users = $this->db->table_exists('users');
		$has_title = $this->db->table_exists('system_parameters');

		$this->db->select("A.request_id, A.iop_id, A.patient_no, A.category_id, A.procedure_id, A.procedure_name, A.qty, A.notes, A.status, A.requested_by, A.requested_at, B.particular_name, concat(" . ($has_title ? "D.cValue,' '," : "''" ) . "C.firstname,' ',C.middlename,' ',C.lastname) as requested_by_name", false);
		$this->db->from('iop_procedure_request A');
		$this->db->join('bill_particular B', 'B.particular_id = A.procedure_id', 'left');
		if ($has_users) {
			$this->db->join('users C', 'C.user_id = A.requested_by', 'left');
		}
		if ($has_users && $has_title) {
			$this->db->join('system_parameters D', 'D.param_id = C.title', 'left');
		}
		$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
		$this->db->order_by('A.requested_at', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function exists_pending($iop_id, $procedure_id)
	{
		$this->ensure_schema();
		if (!$this->db->table_exists('iop_procedure_request')) {
			return false;
		}
		$this->db->select('request_id');
		$this->db->where(array(
			'iop_id' => (string)$iop_id,
			'procedure_id' => (int)$procedure_id,
			'InActive' => 0
		));
		$this->db->where("(status = 'REQUESTED' OR status = 'ORDERED' OR status = 'PENDING')", null, false);
		$this->db->limit(1);
		$row = $this->db->get('iop_procedure_request')->row();
		return (bool)$row;
	}

	public function has_pending_procedures($iop_id)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '' || !$this->db->table_exists('iop_procedure_request')) {
			return false;
		}

		$this->db->select('request_id');
		$this->db->where(array(
			'iop_id' => $iop_id,
			'InActive' => 0
		));
		$this->db->where("UPPER(TRIM(COALESCE(status, ''))) IN ('REQUESTED','ORDERED','PENDING')", null, false);
		$this->db->limit(1);
		$row = $this->db->get('iop_procedure_request')->row();
		return (bool)$row;
	}
}
