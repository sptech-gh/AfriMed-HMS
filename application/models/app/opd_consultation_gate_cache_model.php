<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Opd_consultation_gate_cache_model extends CI_Model
{
	/**
	 * Optional in-memory prefetch map for gate cache rows.
	 * Keys are iop_id (string), values are row objects from opd_consultation_gate_cache.
	 * When populated via preload_caches(), get_cache() will serve from this map
	 * instead of issuing per-row SELECT queries. If not populated, behavior
	 * remains identical to the legacy implementation.
	 */
	protected $prefetch_map = array();
	public function __construct()
	{
		parent::__construct();
	}

	public function ensure_schema()
	{
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `opd_consultation_gate_cache` (
				`cache_id` bigint(20) NOT NULL AUTO_INCREMENT,
				`iop_id` varchar(25) NOT NULL,
				`patient_no` varchar(25) DEFAULT NULL,
				`status` varchar(30) NOT NULL DEFAULT 'UNKNOWN',
				`can_start` tinyint(1) NOT NULL DEFAULT 0,
				`outstanding_balance` decimal(18,2) NOT NULL DEFAULT 0.00,
				`badge_class` varchar(40) DEFAULT NULL,
				`label` varchar(80) DEFAULT NULL,
				`tooltip` varchar(500) DEFAULT NULL,
				`action_url` varchar(255) DEFAULT NULL,
				`details_json` mediumtext DEFAULT NULL,
				`computed_at` datetime NOT NULL,
				`computed_by` varchar(25) DEFAULT NULL,
				`compute_trigger` varchar(30) DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`cache_id`),
				UNIQUE KEY `uq_opd_gate_cache_iop` (`iop_id`),
				KEY `idx_status` (`status`),
				KEY `idx_patient` (`patient_no`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

			$this->db->query("CREATE TABLE IF NOT EXISTS `opd_consultation_gate_audit` (
				`audit_id` bigint(20) NOT NULL AUTO_INCREMENT,
				`iop_id` varchar(25) NOT NULL,
				`patient_no` varchar(25) DEFAULT NULL,
				`old_status` varchar(30) DEFAULT NULL,
				`new_status` varchar(30) NOT NULL,
				`reason` varchar(500) DEFAULT NULL,
				`actor_user_id` varchar(25) DEFAULT NULL,
				`trigger` varchar(30) DEFAULT NULL,
				`details_json` mediumtext DEFAULT NULL,
				`created_at` datetime NOT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`audit_id`),
				KEY `idx_iop` (`iop_id`),
				KEY `idx_created` (`created_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

			$this->db->query("CREATE TABLE IF NOT EXISTS `opd_consultation_gate_overrides` (
				`id` bigint(20) NOT NULL AUTO_INCREMENT,
				`iop_id` varchar(25) NOT NULL,
				`patient_no` varchar(25) DEFAULT NULL,
				`status` varchar(20) NOT NULL DEFAULT 'ACTIVE',
				`reason` varchar(500) DEFAULT NULL,
				`approved_by` varchar(25) DEFAULT NULL,
				`approved_at` datetime NOT NULL,
				`expires_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_iop_status` (`iop_id`,`status`,`InActive`),
				KEY `idx_exp` (`expires_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} catch (Throwable $e) {
			log_message('error', 'opd_consultation_gate_cache schema ensure failed: ' . $e->getMessage());
		}
		if ($prev !== null) { $this->db->db_debug = $prev; }
	}

	public function get_cache($iop_id)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') return null;
		// Serve from preloaded map when available to avoid N+1 queries
		if (!empty($this->prefetch_map) && array_key_exists($iop_id, $this->prefetch_map)) {
			return $this->prefetch_map[$iop_id];
		}
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$this->db->limit(1);
		return $this->db->get('opd_consultation_gate_cache')->row();
	}

	/**
	 * Bulk-preload cache rows for a list of IOP IDs.
	 * This allows high-volume screens (e.g. OPD Active list) to perform a
	 * single SELECT ... WHERE iop_id IN (...) instead of one query per row.
	 *
	 * @param array $iop_ids
	 * @return void
	 */
	public function preload_caches($iop_ids)
	{
		$this->ensure_schema();
		$this->prefetch_map = array();
		if (!is_array($iop_ids) || empty($iop_ids)) {
			return;
		}
		$uniq = array();
		foreach ($iop_ids as $id) {
			$id = trim((string)$id);
			if ($id === '') { continue; }
			$uniq[$id] = true;
		}
		if (empty($uniq)) {
			return;
		}
		$this->db->where_in('iop_id', array_keys($uniq));
		$this->db->where('InActive', 0);
		$q = $this->db->get('opd_consultation_gate_cache');
		$rows = $q ? $q->result() : array();
		$map = array();
		foreach ((array)$rows as $row) {
			$key = isset($row->iop_id) ? trim((string)$row->iop_id) : '';
			if ($key === '') { continue; }
			$map[$key] = $row;
		}
		$this->prefetch_map = $map;
	}

	public function upsert_snapshot($payload, $actor_user_id = null, $trigger = null)
	{
		$this->ensure_schema();
		if (!is_array($payload) || empty($payload['iop_id'])) return false;
		$iop_id = trim((string)$payload['iop_id']);
		$patient_no = isset($payload['patient_no']) ? trim((string)$payload['patient_no']) : null;
		$status = isset($payload['status']) ? trim((string)$payload['status']) : 'UNKNOWN';
		$can_start = !empty($payload['can_start']) ? 1 : 0;
		$outstanding = isset($payload['outstanding_balance']) ? (float)$payload['outstanding_balance'] : 0.0;
		$ui = array();
		if (isset($payload['ui']) && is_array($payload['ui'])) {
			$ui = $payload['ui'];
		}

		$badge_class = isset($ui['badge_class']) ? (string)$ui['badge_class'] : (isset($payload['badge_class']) ? (string)$payload['badge_class'] : null);
		$label = isset($ui['label']) ? (string)$ui['label'] : (isset($payload['label']) ? (string)$payload['label'] : null);
		$tooltip = isset($ui['tooltip']) ? (string)$ui['tooltip'] : (isset($payload['tooltip']) ? (string)$payload['tooltip'] : null);
		$action_url = isset($ui['action_url']) ? (string)$ui['action_url'] : (isset($payload['payment_url']) ? (string)$payload['payment_url'] : null);

		$details = json_encode($payload);
		$now = date('Y-m-d H:i:s');

		$existing = $this->get_cache($iop_id);
		$old_status = $existing && isset($existing->status) ? (string)$existing->status : null;

		$data = array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'status' => $status,
			'can_start' => $can_start,
			'outstanding_balance' => round((float)$outstanding, 2),
			'badge_class' => $badge_class,
			'label' => $label,
			'tooltip' => $tooltip,
			'action_url' => $action_url,
			'details_json' => $details,
			'computed_at' => $now,
			'computed_by' => $actor_user_id,
			'compute_trigger' => $trigger,
			'InActive' => 0,
		);

		if ($existing) {
			$this->db->where(array('iop_id' => $iop_id));
			$this->db->update('opd_consultation_gate_cache', $data);
		} else {
			$this->db->insert('opd_consultation_gate_cache', $data);
		}

		if ($old_status !== $status) {
			$reason = null;
			if (isset($payload['blocking_reasons']) && is_array($payload['blocking_reasons']) && !empty($payload['blocking_reasons'])) {
				$reason = implode(' | ', $payload['blocking_reasons']);
			}
			$this->db->insert('opd_consultation_gate_audit', array(
				'iop_id' => $iop_id,
				'patient_no' => $patient_no,
				'old_status' => $old_status,
				'new_status' => $status,
				'reason' => $reason,
				'actor_user_id' => $actor_user_id,
				'trigger' => $trigger,
				'details_json' => $details,
				'created_at' => $now,
				'InActive' => 0,
			));
		}
		return true;
	}

	public function get_active_override($iop_id)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') return null;
		$now = date('Y-m-d H:i:s');
		$this->db->where('iop_id', $iop_id);
		$this->db->where('InActive', 0);
		$this->db->where('status', 'ACTIVE');
		$this->db->group_start();
		$this->db->where('expires_at IS NULL', null, false);
		$this->db->or_where('expires_at >=', $now);
		$this->db->group_end();
		$this->db->order_by('id', 'DESC');
		$this->db->limit(1);
		return $this->db->get('opd_consultation_gate_overrides')->row();
	}

	public function create_override($iop_id, $patient_no, $reason, $approved_by, $expires_at = null)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') return array('ok' => false, 'error' => 'Missing iop_id');
		$row = array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no !== null ? trim((string)$patient_no) : null,
			'status' => 'ACTIVE',
			'reason' => $reason !== null ? trim((string)$reason) : null,
			'approved_by' => $approved_by,
			'approved_at' => date('Y-m-d H:i:s'),
			'expires_at' => $expires_at,
			'InActive' => 0,
		);
		$this->db->insert('opd_consultation_gate_overrides', $row);
		return array('ok' => true, 'id' => (int)$this->db->insert_id());
	}
}
