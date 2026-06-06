<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Laboratory_release_model extends CI_Model
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
				AND (TABLE_NAME='iop_laboratory_result_draft' AND COLUMN_NAME IN ('findings','result'))";
			$q = $this->db->query($sql, array($schema));
			$rows = $q ? $q->result_array() : array();
			if (!$rows || count($rows) < 2) {
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

	public function ensure_release_schema(){
		$created = array();
		$errors = array();
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_result_draft` (
				`draft_id` int(11) NOT NULL AUTO_INCREMENT,
				`io_lab_id` int(11) NOT NULL,
				`iop_id` varchar(50) DEFAULT NULL,
				`patient_no` varchar(50) DEFAULT NULL,
				`draft_status` varchar(30) DEFAULT NULL,
				`findings` text,
				`result` text,
				`attachment_meta_id` varchar(100) DEFAULT NULL,
				`entered_by` varchar(50) DEFAULT NULL,
				`entered_at` datetime DEFAULT NULL,
				`updated_by` varchar(50) DEFAULT NULL,
				`updated_at` datetime DEFAULT NULL,
				`validated_by` varchar(50) DEFAULT NULL,
				`validated_at` datetime DEFAULT NULL,
				`verified_by` varchar(50) DEFAULT NULL,
				`verified_at` datetime DEFAULT NULL,
				`rejected_by` varchar(50) DEFAULT NULL,
				`rejected_at` datetime DEFAULT NULL,
				`rejection_reason` text,
				`source_context` text,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`draft_id`),
				KEY `idx_io_lab_id` (`io_lab_id`),
				KEY `idx_iop_id` (`iop_id`),
				KEY `idx_patient_no` (`patient_no`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$created[] = 'iop_laboratory_result_draft';
		} catch (Exception $e) {
			$errors[] = array('table' => 'iop_laboratory_result_draft', 'error' => $e->getMessage());
		}

		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_release_batch` (
				`release_id` int(11) NOT NULL AUTO_INCREMENT,
				`release_no` varchar(50) DEFAULT NULL,
				`iop_id` varchar(50) DEFAULT NULL,
				`patient_no` varchar(50) DEFAULT NULL,
				`facility_id` varchar(50) DEFAULT NULL,
				`branch_id` varchar(50) DEFAULT NULL,
				`department_id` varchar(50) DEFAULT NULL,
				`release_group_type` varchar(50) DEFAULT NULL,
				`release_group_reference` varchar(100) DEFAULT NULL,
				`release_status` varchar(30) DEFAULT NULL,
				`release_version` int(11) DEFAULT NULL,
				`ready_for_release_at` datetime DEFAULT NULL,
				`release_locked_at` datetime DEFAULT NULL,
				`released_by` varchar(50) DEFAULT NULL,
				`released_at` datetime DEFAULT NULL,
				`amended_from_release_id` int(11) DEFAULT NULL,
				`cancelled_by` varchar(50) DEFAULT NULL,
				`cancelled_at` datetime DEFAULT NULL,
				`cancel_reason` text,
				`created_by` varchar(50) DEFAULT NULL,
				`created_at` datetime DEFAULT NULL,
				`updated_by` varchar(50) DEFAULT NULL,
				`updated_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`release_id`),
				KEY `idx_iop_id` (`iop_id`),
				KEY `idx_patient_no` (`patient_no`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$created[] = 'iop_laboratory_release_batch';
		} catch (Exception $e) {
			$errors[] = array('table' => 'iop_laboratory_release_batch', 'error' => $e->getMessage());
		}

		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_release_item` (
				`release_item_id` int(11) NOT NULL AUTO_INCREMENT,
				`release_id` int(11) DEFAULT NULL,
				`io_lab_id` int(11) DEFAULT NULL,
				`draft_id` int(11) DEFAULT NULL,
				`item_status` varchar(30) DEFAULT NULL,
				`included_in_release` tinyint(1) NOT NULL DEFAULT 1,
				`requires_group_completion` tinyint(1) NOT NULL DEFAULT 0,
				`requires_consultant_signoff` tinyint(1) NOT NULL DEFAULT 0,
				`requires_pathologist_review` tinyint(1) NOT NULL DEFAULT 0,
				`dependency_policy_code` varchar(50) DEFAULT NULL,
				`created_at` datetime DEFAULT NULL,
				`updated_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`release_item_id`),
				KEY `idx_release_id` (`release_id`),
				KEY `idx_io_lab_id` (`io_lab_id`),
				KEY `idx_draft_id` (`draft_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$created[] = 'iop_laboratory_release_item';
		} catch (Exception $e) {
			$errors[] = array('table' => 'iop_laboratory_release_item', 'error' => $e->getMessage());
		}

		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_release_snapshot` (
				`snapshot_id` int(11) NOT NULL AUTO_INCREMENT,
				`release_id` int(11) DEFAULT NULL,
				`release_version` int(11) DEFAULT NULL,
				`io_lab_id` int(11) DEFAULT NULL,
				`draft_id` int(11) DEFAULT NULL,
				`patient_no` varchar(50) DEFAULT NULL,
				`iop_id` varchar(50) DEFAULT NULL,
				`test_name` varchar(255) DEFAULT NULL,
				`findings_snapshot` text,
				`result_snapshot` text,
				`attachment_snapshot` text,
				`critical_flag` tinyint(1) NOT NULL DEFAULT 0,
				`verified_by` varchar(50) DEFAULT NULL,
				`verified_at` datetime DEFAULT NULL,
				`released_by` varchar(50) DEFAULT NULL,
				`released_at` datetime DEFAULT NULL,
				`snapshot_hash` varchar(128) DEFAULT NULL,
				`created_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`snapshot_id`),
				KEY `idx_release_id` (`release_id`),
				KEY `idx_io_lab_id` (`io_lab_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$created[] = 'iop_laboratory_release_snapshot';
		} catch (Exception $e) {
			$errors[] = array('table' => 'iop_laboratory_release_snapshot', 'error' => $e->getMessage());
		}

		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_result_acknowledgement` (
				`ack_id` int(11) NOT NULL AUTO_INCREMENT,
				`release_id` int(11) DEFAULT NULL,
				`snapshot_id` int(11) DEFAULT NULL,
				`io_lab_id` int(11) DEFAULT NULL,
				`patient_no` varchar(50) DEFAULT NULL,
				`iop_id` varchar(50) DEFAULT NULL,
				`clinician_user_id` varchar(50) DEFAULT NULL,
				`viewed_at` datetime DEFAULT NULL,
				`acknowledged_at` datetime DEFAULT NULL,
				`acknowledgement_required` tinyint(1) NOT NULL DEFAULT 0,
				`acknowledgement_reason` text,
				`critical_acknowledgement` tinyint(1) NOT NULL DEFAULT 0,
				`created_at` datetime DEFAULT NULL,
				`updated_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`ack_id`),
				KEY `idx_release_id` (`release_id`),
				KEY `idx_snapshot_id` (`snapshot_id`),
				KEY `idx_io_lab_id` (`io_lab_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$created[] = 'iop_laboratory_result_acknowledgement';
		} catch (Exception $e) {
			$errors[] = array('table' => 'iop_laboratory_result_acknowledgement', 'error' => $e->getMessage());
		}

		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `iop_laboratory_release_event` (
				`event_id` int(11) NOT NULL AUTO_INCREMENT,
				`event_type` varchar(50) DEFAULT NULL,
				`release_id` int(11) DEFAULT NULL,
				`io_lab_id` int(11) DEFAULT NULL,
				`patient_no` varchar(50) DEFAULT NULL,
				`iop_id` varchar(50) DEFAULT NULL,
				`facility_id` varchar(50) DEFAULT NULL,
				`payload_json` mediumtext,
				`created_by` varchar(50) DEFAULT NULL,
				`created_at` datetime DEFAULT NULL,
				`InActive` tinyint(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`event_id`),
				KEY `idx_event_type` (`event_type`),
				KEY `idx_release_id` (`release_id`),
				KEY `idx_io_lab_id` (`io_lab_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
			$created[] = 'iop_laboratory_release_event';
		} catch (Exception $e) {
			$errors[] = array('table' => 'iop_laboratory_release_event', 'error' => $e->getMessage());
		}

		return array('ok' => count($errors) === 0, 'created' => $created, 'errors' => $errors);
	}

	public function save_result_draft_shadow($io_lab_id, $findings, $result, $user_id, $context = array()){
		$io_lab_id = (int)$io_lab_id;
		$user_id = trim((string)$user_id);
		if ($io_lab_id <= 0) {
			return false;
		}
		if (!$this->table_exists('iop_laboratory_result_draft')) {
			return false;
		}
		$now = date('Y-m-d H:i:s');
		$payload = array(
			'io_lab_id' => $io_lab_id,
			'draft_status' => 'ENTERED',
			'findings' => $this->db_safe_text($findings),
			'result' => $this->db_safe_text($result),
			'updated_by' => $user_id !== '' ? $user_id : null,
			'updated_at' => $now,
			'InActive' => 0,
		);
		if (is_array($context) && !empty($context)) {
			$payload['source_context'] = json_encode($context);
		}

		$existing = $this->db->get_where('iop_laboratory_result_draft', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if ($existing) {
			$this->db->where('draft_id', (int)$existing->draft_id);
			$ok = $this->db->update('iop_laboratory_result_draft', $payload);
			if ($ok === false) {
				$err = $this->db->error();
				$code = isset($err['code']) ? (string)$err['code'] : '';
				$msg = isset($err['message']) ? (string)$err['message'] : 'unknown_db_error';
				log_message('error', 'LAB_RELEASE_SHADOW_WRITE_DB_FAILED op=update io_lab_id='.(int)$io_lab_id.' code='.$code.' msg='.$msg);
				return false;
			}
			return ($this->db->affected_rows() >= 0);
		}

		$payload['entered_by'] = $user_id !== '' ? $user_id : null;
		$payload['entered_at'] = $now;
		$ok = $this->db->insert('iop_laboratory_result_draft', $payload);
		if ($ok === false) {
			$err = $this->db->error();
			$code = isset($err['code']) ? (string)$err['code'] : '';
			$msg = isset($err['message']) ? (string)$err['message'] : 'unknown_db_error';
			log_message('error', 'LAB_RELEASE_SHADOW_WRITE_DB_FAILED op=insert io_lab_id='.(int)$io_lab_id.' code='.$code.' msg='.$msg);
			return false;
		}
		return ($this->db->affected_rows() > 0);
	}

	public function get_shadow_mismatch_report($limit = 100){
		$limit = (int)$limit;
		if ($limit <= 0) $limit = 100;
		if ($limit > 500) $limit = 500;
		if (!$this->table_exists('iop_laboratory_result_draft')) {
			return array('ok' => false, 'error' => 'draft_table_missing', 'rows' => array());
		}
		$hasPD = $this->table_exists('patient_details_iop') && $this->column_exists('patient_details_iop', 'patient_no') && $this->column_exists('patient_details_iop', 'IO_ID');
		$patientSelect = $hasPD ? "PD.patient_no" : "NULL";
		$patientJoin = $hasPD ? "\n\t\t\tLEFT JOIN patient_details_iop PD ON PD.IO_ID = L.iop_id" : "";

		$sql = "SELECT L.io_lab_id, L.iop_id, {$patientSelect} AS patient_no,
			L.findings AS legacy_findings, L.result AS legacy_result,
			D.draft_id, D.findings AS draft_findings, D.result AS draft_result, D.updated_at AS draft_updated_at
			FROM iop_laboratory L
			LEFT JOIN iop_laboratory_result_draft D ON D.io_lab_id = L.io_lab_id AND D.InActive = 0{$patientJoin}
			WHERE L.InActive = 0
			AND TRIM(COALESCE(L.result,'')) <> ''
			AND (
				D.draft_id IS NULL
				OR COALESCE(D.result,'') <> COALESCE(L.result,'')
				OR COALESCE(D.findings,'') <> COALESCE(L.findings,'')
			)
			ORDER BY L.io_lab_id DESC
			LIMIT ?";
		$q = $this->db->query($sql, array($limit));
		$rows = $q ? $q->result_array() : array();
		return array('ok' => true, 'rows' => $rows, 'count' => count($rows));
	}

	public function get_shadow_item_report($io_lab_id){
		$io_lab_id = (int)$io_lab_id;
		if ($io_lab_id <= 0) {
			return array('ok' => false, 'error' => 'invalid_io_lab_id');
		}
		if (!$this->table_exists('iop_laboratory_result_draft')) {
			return array('ok' => false, 'error' => 'draft_table_missing');
		}

		$legacy = null;
		$draft = null;
		$mismatch_reasons = array();
		$matches = array('findings' => null, 'result' => null, 'all' => null);

		$this->db->select('io_lab_id, iop_id, findings, result, lab_result_upload, laboratory_id, laboratory_text, category_id');
		$this->db->where(array('io_lab_id' => $io_lab_id, 'InActive' => 0));
		$this->db->limit(1);
		$legacyRow = $this->db->get('iop_laboratory')->row();
		if (!$legacyRow) {
			return array('ok' => false, 'error' => 'legacy_not_found', 'io_lab_id' => $io_lab_id);
		}

		$patient_no = null;
		$hasPD = $this->table_exists('patient_details_iop') && $this->column_exists('patient_details_iop', 'patient_no') && $this->column_exists('patient_details_iop', 'IO_ID');
		if ($hasPD && isset($legacyRow->iop_id) && (string)$legacyRow->iop_id !== '') {
			$this->db->select('patient_no');
			$this->db->where(array('IO_ID' => (string)$legacyRow->iop_id));
			$this->db->limit(1);
			$pd = $this->db->get('patient_details_iop')->row();
			if ($pd && isset($pd->patient_no)) {
				$patient_no = (string)$pd->patient_no;
			}
		}

		$legacy = array(
			'io_lab_id' => (int)$legacyRow->io_lab_id,
			'iop_id' => isset($legacyRow->iop_id) ? (string)$legacyRow->iop_id : null,
			'patient_no' => $patient_no,
			'findings' => isset($legacyRow->findings) ? (string)$legacyRow->findings : '',
			'result' => isset($legacyRow->result) ? (string)$legacyRow->result : '',
			'has_pdf' => (isset($legacyRow->lab_result_upload) && trim((string)$legacyRow->lab_result_upload) !== '') ? 1 : 0,
			'laboratory_id' => isset($legacyRow->laboratory_id) ? (int)$legacyRow->laboratory_id : null,
			'laboratory_text' => isset($legacyRow->laboratory_text) ? (string)$legacyRow->laboratory_text : null,
			'category_id' => isset($legacyRow->category_id) ? (string)$legacyRow->category_id : null,
		);

		$draftRow = $this->db->get_where('iop_laboratory_result_draft', array('io_lab_id' => $io_lab_id, 'InActive' => 0))->row();
		if ($draftRow) {
			$draft = array(
				'draft_id' => isset($draftRow->draft_id) ? (int)$draftRow->draft_id : null,
				'io_lab_id' => isset($draftRow->io_lab_id) ? (int)$draftRow->io_lab_id : $io_lab_id,
				'draft_status' => isset($draftRow->draft_status) ? (string)$draftRow->draft_status : null,
				'findings' => isset($draftRow->findings) ? (string)$draftRow->findings : '',
				'result' => isset($draftRow->result) ? (string)$draftRow->result : '',
				'updated_by' => isset($draftRow->updated_by) ? (string)$draftRow->updated_by : null,
				'updated_at' => isset($draftRow->updated_at) ? (string)$draftRow->updated_at : null,
				'source_context' => isset($draftRow->source_context) ? (string)$draftRow->source_context : null,
			);
		}

		if (!$draftRow) {
			$mismatch_reasons[] = 'draft_missing';
			$matches['findings'] = false;
			$matches['result'] = false;
			$matches['all'] = false;
		} else {
			$matches['findings'] = ((string)$legacy['findings'] === (string)$draft['findings']);
			$matches['result'] = ((string)$legacy['result'] === (string)$draft['result']);
			$matches['all'] = ($matches['findings'] && $matches['result']);
			if (!$matches['findings']) $mismatch_reasons[] = 'findings_mismatch';
			if (!$matches['result']) $mismatch_reasons[] = 'result_mismatch';
		}

		return array(
			'ok' => true,
			'io_lab_id' => $io_lab_id,
			'legacy' => $legacy,
			'draft' => $draft,
			'matches' => $matches,
			'mismatch_reasons' => $mismatch_reasons,
		);
	}

	private function get_patient_no_for_iop($iop_id){
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '') {
			return null;
		}
		if (!$this->table_exists('patient_details_iop') || !$this->column_exists('patient_details_iop', 'patient_no') || !$this->column_exists('patient_details_iop', 'IO_ID')) {
			return null;
		}
		$this->db->select('patient_no');
		$this->db->where(array('IO_ID' => $iop_id));
		$this->db->limit(1);
		$row = $this->db->get('patient_details_iop')->row();
		return ($row && isset($row->patient_no)) ? (string)$row->patient_no : null;
	}

	private function release_group_reference($iop_id, $category_id){
		return trim((string)$iop_id).':'.trim((string)$category_id);
	}

	private function release_tables_ready(){
		return $this->table_exists('iop_laboratory_result_draft')
			&& $this->table_exists('iop_laboratory_release_batch')
			&& $this->table_exists('iop_laboratory_release_item')
			&& $this->table_exists('iop_laboratory_release_snapshot')
			&& $this->table_exists('iop_laboratory_release_event');
	}

	private function get_release_group_items($iop_id, $category_id){
		$this->db->select("L.io_lab_id, L.iop_id, L.category_id, L.laboratory_id, L.laboratory_text, L.findings AS legacy_findings, L.result AS legacy_result, L.lab_result_upload, D.draft_id, D.draft_status, D.findings AS draft_findings, D.result AS draft_result, D.updated_at AS draft_updated_at", false);
		$this->db->from('iop_laboratory L');
		$this->db->join('iop_laboratory_result_draft D', 'D.io_lab_id = L.io_lab_id AND D.InActive = 0', 'left');
		$this->db->where(array('L.iop_id' => (string)$iop_id, 'L.category_id' => (string)$category_id, 'L.InActive' => 0));
		$this->db->order_by('L.io_lab_id', 'ASC');
		$q = $this->db->get();
		$rows = $q ? $q->result_array() : array();
		foreach ($rows as &$row) {
			$row['ready'] = !empty($row['draft_id']);
			$row['item_status'] = $row['ready'] ? 'READY' : 'WAITING_DRAFT';
		}
		unset($row);
		return $rows;
	}

	private function get_active_release_batch($group_reference){
		$this->db->where(array('release_group_type' => 'IOP_CATEGORY', 'release_group_reference' => $group_reference, 'InActive' => 0));
		$this->db->where_in('release_status', array('DRAFT', 'READY', 'LOCKED'));
		$this->db->order_by('release_id', 'DESC');
		$this->db->limit(1);
		return $this->db->get('iop_laboratory_release_batch')->row();
	}

	private function get_latest_released_batch($group_reference){
		$this->db->where(array('release_group_type' => 'IOP_CATEGORY', 'release_group_reference' => $group_reference, 'InActive' => 0, 'release_status' => 'RELEASED'));
		$this->db->order_by('release_id', 'DESC');
		$this->db->limit(1);
		return $this->db->get('iop_laboratory_release_batch')->row();
	}

	private function get_snapshot_count_for_release($release_id){
		$release_id = (int)$release_id;
		if ($release_id <= 0 || !$this->table_exists('iop_laboratory_release_snapshot')) {
			return 0;
		}
		$this->db->where(array('release_id' => $release_id, 'InActive' => 0));
		return (int)$this->db->count_all_results('iop_laboratory_release_snapshot');
	}

	public function get_release_group_status($iop_id, $category_id){
		$iop_id = trim((string)$iop_id);
		$category_id = trim((string)$category_id);
		if ($iop_id === '' || $category_id === '') {
			return array('ok' => false, 'error' => 'invalid_group');
		}
		if (!$this->release_tables_ready()) {
			return array('ok' => false, 'error' => 'release_schema_missing');
		}
		$group_reference = $this->release_group_reference($iop_id, $category_id);
		$items = $this->get_release_group_items($iop_id, $category_id);
		$ready_count = 0;
		foreach ($items as $item) {
			if (!empty($item['ready'])) {
				$ready_count++;
			}
		}
		$batch = $this->get_active_release_batch($group_reference);
		$releasedBatch = $this->get_latest_released_batch($group_reference);
		$releasedSnapshotCount = $releasedBatch && isset($releasedBatch->release_id) ? $this->get_snapshot_count_for_release((int)$releasedBatch->release_id) : 0;
		return array(
			'ok' => true,
			'group' => array(
				'release_group_type' => 'IOP_CATEGORY',
				'release_group_reference' => $group_reference,
				'iop_id' => $iop_id,
				'category_id' => $category_id,
				'patient_no' => $this->get_patient_no_for_iop($iop_id),
			),
			'batch' => $batch ? (array)$batch : null,
			'released_batch' => $releasedBatch ? (array)$releasedBatch : null,
			'released_snapshot_count' => $releasedSnapshotCount,
			'items' => $items,
			'item_count' => count($items),
			'ready_count' => $ready_count,
			'all_ready' => count($items) > 0 && $ready_count === count($items),
		);
	}

	public function build_release_group($iop_id, $category_id, $user_id){
		$status = $this->get_release_group_status($iop_id, $category_id);
		if (empty($status['ok'])) {
			return $status;
		}
		if ((int)$status['item_count'] <= 0) {
			return array('ok' => false, 'error' => 'no_group_items', 'status' => $status);
		}
		$now = date('Y-m-d H:i:s');
		$user_id = trim((string)$user_id);
		$batch = $status['batch'];
		$batch_status = !empty($status['all_ready']) ? 'READY' : 'DRAFT';
		if (!$batch) {
			$group_reference = isset($status['group']['release_group_reference']) ? (string)$status['group']['release_group_reference'] : $this->release_group_reference($status['group']['iop_id'], $status['group']['category_id']);
			$prevReleased = $this->get_latest_released_batch($group_reference);
			$nextVersion = 1;
			$amendedFrom = null;
			if ($prevReleased && isset($prevReleased->release_id)) {
				$prevVer = isset($prevReleased->release_version) ? (int)$prevReleased->release_version : 1;
				$nextVersion = $prevVer + 1;
				$amendedFrom = (int)$prevReleased->release_id;
			}
			$this->db->insert('iop_laboratory_release_batch', array(
				'iop_id' => $status['group']['iop_id'],
				'patient_no' => $status['group']['patient_no'],
				'release_group_type' => 'IOP_CATEGORY',
				'release_group_reference' => $status['group']['release_group_reference'],
				'release_status' => $batch_status,
				'release_version' => $nextVersion,
				'amended_from_release_id' => $amendedFrom,
				'ready_for_release_at' => $batch_status === 'READY' ? $now : null,
				'created_by' => $user_id !== '' ? $user_id : null,
				'created_at' => $now,
				'updated_by' => $user_id !== '' ? $user_id : null,
				'updated_at' => $now,
				'InActive' => 0,
			));
			$release_id = (int)$this->db->insert_id();
		} else {
			$release_id = (int)$batch['release_id'];
			$this->db->where('release_id', $release_id);
			$this->db->update('iop_laboratory_release_batch', array(
				'release_status' => $batch_status,
				'ready_for_release_at' => $batch_status === 'READY' ? $now : null,
				'updated_by' => $user_id !== '' ? $user_id : null,
				'updated_at' => $now,
			));
		}
		foreach ($status['items'] as $item) {
			$existing = $this->db->get_where('iop_laboratory_release_item', array('release_id' => $release_id, 'io_lab_id' => (int)$item['io_lab_id'], 'InActive' => 0))->row();
			$payload = array(
				'release_id' => $release_id,
				'io_lab_id' => (int)$item['io_lab_id'],
				'draft_id' => !empty($item['draft_id']) ? (int)$item['draft_id'] : null,
				'item_status' => $item['item_status'],
				'included_in_release' => 1,
				'updated_at' => $now,
				'InActive' => 0,
			);
			if ($existing) {
				$this->db->where('release_item_id', (int)$existing->release_item_id);
				$this->db->update('iop_laboratory_release_item', $payload);
			} else {
				$payload['created_at'] = $now;
				$this->db->insert('iop_laboratory_release_item', $payload);
			}
		}
		$this->db->insert('iop_laboratory_release_event', array(
			'event_type' => 'BATCH_BUILT',
			'release_id' => $release_id,
			'patient_no' => $status['group']['patient_no'],
			'iop_id' => $status['group']['iop_id'],
			'payload_json' => json_encode(array('category_id' => $status['group']['category_id'], 'item_count' => $status['item_count'], 'ready_count' => $status['ready_count'])),
			'created_by' => $user_id !== '' ? $user_id : null,
			'created_at' => $now,
			'InActive' => 0,
		));
		return $this->get_release_group_status($iop_id, $category_id);
	}

	public function release_group($iop_id, $category_id, $user_id, $enforce_no_partial = true){
		$build = $this->build_release_group($iop_id, $category_id, $user_id);
		if (empty($build['ok'])) {
			return $build;
		}
		if ($enforce_no_partial && empty($build['all_ready'])) {
			return array('ok' => false, 'error' => 'group_not_ready', 'status' => $build);
		}
		if (empty($build['batch']) || empty($build['batch']['release_id'])) {
			return array('ok' => false, 'error' => 'batch_missing', 'status' => $build);
		}
		$now = date('Y-m-d H:i:s');
		$user_id = trim((string)$user_id);
		$release_id = (int)$build['batch']['release_id'];
		$released = 0;
		$this->db->trans_begin();
		$this->db->where('release_id', $release_id);
		$this->db->update('iop_laboratory_release_batch', array('release_status' => 'LOCKED', 'release_locked_at' => $now, 'updated_by' => $user_id !== '' ? $user_id : null, 'updated_at' => $now));
		foreach ($build['items'] as $item) {
			if (empty($item['ready']) || empty($item['draft_id'])) {
				continue;
			}
			$test_name = isset($item['laboratory_text']) ? (string)$item['laboratory_text'] : '';
			$findings = isset($item['draft_findings']) ? (string)$item['draft_findings'] : '';
			$result = isset($item['draft_result']) ? (string)$item['draft_result'] : '';
			$hashPayload = array('release_id' => $release_id, 'io_lab_id' => (int)$item['io_lab_id'], 'draft_id' => (int)$item['draft_id'], 'findings' => $findings, 'result' => $result);
			$this->db->insert('iop_laboratory_release_snapshot', array(
				'release_id' => $release_id,
				'release_version' => isset($build['batch']['release_version']) ? (int)$build['batch']['release_version'] : 1,
				'io_lab_id' => (int)$item['io_lab_id'],
				'draft_id' => (int)$item['draft_id'],
				'patient_no' => $build['group']['patient_no'],
				'iop_id' => $build['group']['iop_id'],
				'test_name' => $test_name,
				'findings_snapshot' => $findings,
				'result_snapshot' => $result,
				'attachment_snapshot' => isset($item['lab_result_upload']) ? (string)$item['lab_result_upload'] : null,
				'snapshot_hash' => hash('sha256', json_encode($hashPayload)),
				'created_at' => $now,
				'InActive' => 0,
			));
			$released++;
		}
		$this->db->where('release_id', $release_id);
		$this->db->update('iop_laboratory_release_batch', array('release_status' => 'RELEASED', 'released_by' => $user_id !== '' ? $user_id : null, 'released_at' => $now, 'updated_by' => $user_id !== '' ? $user_id : null, 'updated_at' => $now));
		$this->db->insert('iop_laboratory_release_event', array(
			'event_type' => 'RELEASED',
			'release_id' => $release_id,
			'patient_no' => $build['group']['patient_no'],
			'iop_id' => $build['group']['iop_id'],
			'payload_json' => json_encode(array('category_id' => $build['group']['category_id'], 'snapshot_count' => $released)),
			'created_by' => $user_id !== '' ? $user_id : null,
			'created_at' => $now,
			'InActive' => 0,
		));
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'release_transaction_failed');
		}
		$this->db->trans_commit();
		$status = $this->get_release_group_status($iop_id, $category_id);
		$status['released_snapshot_count'] = $released;
		return $status;
	}

	public function amend_release_group($iop_id, $category_id, $user_id){
		$iop_id = trim((string)$iop_id);
		$category_id = trim((string)$category_id);
		if ($iop_id === '' || $category_id === '') {
			return array('ok' => false, 'error' => 'invalid_group');
		}
		if (!$this->release_tables_ready()) {
			return array('ok' => false, 'error' => 'release_schema_missing');
		}
		$group_reference = $this->release_group_reference($iop_id, $category_id);
		$active = $this->get_active_release_batch($group_reference);
		if ($active) {
			return array('ok' => false, 'error' => 'active_batch_exists');
		}
		$released = $this->get_latest_released_batch($group_reference);
		if (!$released) {
			return array('ok' => false, 'error' => 'no_released_batch');
		}
		return $this->build_release_group($iop_id, $category_id, $user_id);
	}

	public function get_release_group_snapshots($iop_id, $category_id, $release_id = null){
		$iop_id = trim((string)$iop_id);
		$category_id = trim((string)$category_id);
		if ($iop_id === '' || $category_id === '') {
			return array('ok' => false, 'error' => 'invalid_group');
		}
		if (!$this->release_tables_ready()) {
			return array('ok' => false, 'error' => 'release_schema_missing');
		}
		$group_reference = $this->release_group_reference($iop_id, $category_id);
		$release_id = $release_id === null ? null : (int)$release_id;

		$batch = null;
		if ($release_id !== null && $release_id > 0) {
			$this->db->where(array('release_id' => $release_id, 'InActive' => 0, 'release_status' => 'RELEASED'));
			$this->db->limit(1);
			$batchRow = $this->db->get('iop_laboratory_release_batch')->row();
			if ($batchRow) {
				$batch = (array)$batchRow;
			}
		}
		if (!$batch) {
			$releasedRow = $this->get_latest_released_batch($group_reference);
			if (!$releasedRow) {
				return array('ok' => false, 'error' => 'no_released_batch');
			}
			$batch = (array)$releasedRow;
			$release_id = isset($batch['release_id']) ? (int)$batch['release_id'] : null;
		}
		if (!$release_id) {
			return array('ok' => false, 'error' => 'release_id_missing');
		}

		$this->db->where(array('release_id' => (int)$release_id, 'InActive' => 0));
		$this->db->order_by('io_lab_id', 'ASC');
		$q = $this->db->get('iop_laboratory_release_snapshot');
		$snapshots = $q ? $q->result_array() : array();

		return array(
			'ok' => true,
			'group' => array(
				'release_group_type' => 'IOP_CATEGORY',
				'release_group_reference' => $group_reference,
				'iop_id' => $iop_id,
				'category_id' => $category_id,
				'patient_no' => $this->get_patient_no_for_iop($iop_id),
			),
			'released_batch' => $batch,
			'snapshot_count' => count($snapshots),
			'snapshots' => $snapshots,
		);
	}
}
