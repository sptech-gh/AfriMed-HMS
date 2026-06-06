<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Pharmacy_reconciliation_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->database();
	}

	public function ensure_reconciliation_log_schema()
	{
		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) { $this->db->db_debug = false; }
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `pharmacy_reconciliation_log` (
				`id` bigint(20) NOT NULL AUTO_INCREMENT,
				`recon_date` date NOT NULL,
				`drug_id` int(11) NOT NULL,
				`opening_stock` decimal(18,2) NOT NULL DEFAULT 0,
				`restocked_qty` decimal(18,2) NOT NULL DEFAULT 0,
				`dispensed_qty` decimal(18,2) NOT NULL DEFAULT 0,
				`billed_qty` decimal(18,2) NOT NULL DEFAULT 0,
				`expected_stock` decimal(18,2) NOT NULL DEFAULT 0,
				`actual_stock` decimal(18,2) NOT NULL DEFAULT 0,
				`stock_diff` decimal(18,2) NOT NULL DEFAULT 0,
				`billing_diff` decimal(18,2) NOT NULL DEFAULT 0,
				`is_baseline` tinyint(1) NOT NULL DEFAULT 0,
				`status` varchar(20) NOT NULL DEFAULT 'OK',
				`created_at` datetime NOT NULL,
				PRIMARY KEY (`id`),
				UNIQUE KEY `uq_recon_day_drug` (`recon_date`, `drug_id`),
				KEY `idx_recon_date` (`recon_date`),
				KEY `idx_drug_id` (`drug_id`),
				KEY `idx_status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8");
		} catch (Exception $e) {
		}

		if ($this->db->table_exists('pharmacy_reconciliation_log')) {
			$this->_ensure_log_column('pharmacy_reconciliation_log', 'is_baseline', "ALTER TABLE `pharmacy_reconciliation_log` ADD COLUMN `is_baseline` tinyint(1) NOT NULL DEFAULT 0 AFTER `billing_diff`");
		}
		if ($prev_debug !== null) { $this->db->db_debug = $prev_debug; }
		return true;
	}

	public function reconcile_day($date)
	{
		$this->ensure_reconciliation_log_schema();

		$date = trim((string)$date);
		$ts = strtotime($date);
		if ($ts === false) {
			return array('ok' => false, 'error' => 'Invalid date');
		}
		$day = date('Y-m-d', $ts);
		$prevDay = date('Y-m-d', strtotime($day . ' -1 day'));

		if (!$this->db->table_exists('medicine_drug_name')) {
			return array('ok' => false, 'error' => 'Stock table not available');
		}

		$drugIds = $this->_get_drug_ids_for_day($day);
		if (count($drugIds) === 0) {
			$this->_clear_day($day);
			return array('ok' => true, 'date' => $day, 'rows' => 0, 'critical' => 0, 'warning' => 0, 'ok_count' => 0);
		}

		$this->db->trans_begin();

		$prevCloseMap = $this->_get_prev_day_actual_stock_map($prevDay);
		$dispensedMap = $this->_get_dispensed_qty_map($day);
		$restockedMap = $this->_get_restocked_qty_map($day);
		$billedMap = $this->_get_billed_qty_map($day);
		$actualMap = $this->_get_actual_stock_map($drugIds);

		$now = date('Y-m-d H:i:s');
		$rows = array();
		$critical = 0;
		$warning = 0;
		$okCount = 0;

		foreach ($drugIds as $drugId) {
			$drugId = (int)$drugId;
			$actual = isset($actualMap[$drugId]) ? (float)$actualMap[$drugId] : 0.0;
			$dispensed = isset($dispensedMap[$drugId]) ? (float)$dispensedMap[$drugId] : 0.0;
			$restocked = isset($restockedMap[$drugId]) ? (float)$restockedMap[$drugId] : 0.0;
			$billed = isset($billedMap[$drugId]) ? (float)$billedMap[$drugId] : 0.0;

			$hasPrev = isset($prevCloseMap[$drugId]);
			$isBaseline = $hasPrev ? 0 : 1;
			$opening = $hasPrev ? (float)$prevCloseMap[$drugId] : ($actual - $restocked + $dispensed);
			$expected = $opening + $restocked - $dispensed;

			$stockDiff = $actual - $expected;
			$billingDiff = $dispensed - $billed;

			$status = 'OK';
			if (abs($stockDiff) > 0.0001) {
				$status = 'CRITICAL';
				$critical++;
			} elseif (abs($billingDiff) > 0.0001) {
				$status = 'WARNING';
				$warning++;
			} else {
				$okCount++;
			}

			$rows[] = array(
				'recon_date' => $day,
				'drug_id' => $drugId,
				'opening_stock' => round($opening, 2),
				'restocked_qty' => round($restocked, 2),
				'dispensed_qty' => round($dispensed, 2),
				'billed_qty' => round($billed, 2),
				'expected_stock' => round($expected, 2),
				'actual_stock' => round($actual, 2),
				'stock_diff' => round($stockDiff, 2),
				'billing_diff' => round($billingDiff, 2),
				'is_baseline' => $isBaseline,
				'status' => $status,
				'created_at' => $now
			);
		}

		$this->_clear_day($day);
		$chunk = 500;
		for ($i = 0; $i < count($rows); $i += $chunk) {
			$this->db->insert_batch('pharmacy_reconciliation_log', array_slice($rows, $i, $chunk));
			if ($this->db->trans_status() === FALSE) {
				break;
			}
		}
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Failed to save reconciliation log');
		}
		$this->db->trans_commit();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Failed to commit reconciliation transaction');
		}

		return array('ok' => true, 'date' => $day, 'rows' => count($rows), 'critical' => $critical, 'warning' => $warning, 'ok_count' => $okCount);
	}

	public function get_day_report($date, $status = '')
	{
		$this->ensure_reconciliation_log_schema();
		$date = trim((string)$date);
		$ts = strtotime($date);
		if ($ts === false) return array();
		$day = date('Y-m-d', $ts);

		$this->db->select('l.*, d.drug_name');
		$this->db->from('pharmacy_reconciliation_log l');
		$this->db->join('medicine_drug_name d', 'd.drug_id = l.drug_id', 'left');
		$this->db->where('l.recon_date', $day);
		if ($status !== '') {
			$this->db->where('l.status', strtoupper(trim((string)$status)));
		}
		$this->db->order_by("FIELD(l.status,'CRITICAL','WARNING','OK')", '', false);
		$this->db->order_by('ABS(l.stock_diff)', 'DESC', false);
		$this->db->order_by('ABS(l.billing_diff)', 'DESC', false);
		$this->db->order_by('d.drug_name', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function get_day_drug_summary($date, $drug_id)
	{
		$this->ensure_reconciliation_log_schema();
		$ts = strtotime((string)$date);
		if ($ts === false) return null;
		$day = date('Y-m-d', $ts);
		$drug_id = (int)$drug_id;

		$this->db->select('l.*, d.drug_name');
		$this->db->from('pharmacy_reconciliation_log l');
		$this->db->join('medicine_drug_name d', 'd.drug_id = l.drug_id', 'left');
		$this->db->where(array('l.recon_date' => $day, 'l.drug_id' => $drug_id));
		$this->db->limit(1);
		$q = $this->db->get();
		return $q ? $q->row() : null;
	}

	public function get_drug_day_dispenses($date, $drug_id)
	{
		$ts = strtotime((string)$date);
		if ($ts === false) return array();
		$day = date('Y-m-d', $ts);
		$drug_id = (int)$drug_id;
		if (!$this->db->table_exists('iop_medication_administration') || !$this->db->table_exists('iop_medication')) return array();

		$sql = "SELECT a.*, m.iop_med_id, m.patient_no, m.medicine_id
			FROM iop_medication_administration a
			JOIN iop_medication m ON m.iop_med_id = a.iop_med_id AND m.InActive = 0
			WHERE a.InActive = 0
			AND m.medicine_id = ?
			AND a.status IN ('DISPENSED','PARTIAL','RETURN')
			AND DATE(a.dDateTime) = ?
			ORDER BY a.dDateTime ASC, a.admin_id ASC";
		$q = $this->db->query($sql, array($drug_id, $day));
		return $q ? $q->result() : array();
	}

	public function get_drug_day_billings($date, $drug_id)
	{
		$ts = strtotime((string)$date);
		if ($ts === false) return array();
		$day = date('Y-m-d', $ts);
		$drug_id = (int)$drug_id;
		if (!$this->db->table_exists('billing_transactions')) return array();

		$sql = "SELECT *
			FROM billing_transactions
			WHERE InActive = 0
			AND department = 'PHARMACY'
			AND item_type = 'DRUG'
			AND item_id = ?
			AND DATE(created_at) = ?
			ORDER BY created_at ASC, txn_id ASC";
		$q = $this->db->query($sql, array($drug_id, $day));
		return $q ? $q->result() : array();
	}

	public function get_drug_day_adjustments($date, $drug_id)
	{
		$ts = strtotime((string)$date);
		if ($ts === false) return array();
		$day = date('Y-m-d', $ts);
		$drug_id = (int)$drug_id;
		if (!$this->db->table_exists('pharmacy_stock_adjustment')) return array();

		$sql = "SELECT *
			FROM pharmacy_stock_adjustment
			WHERE InActive = 0
			AND drug_id = ?
			AND DATE(created_at) = ?
			ORDER BY created_at ASC, adjustment_id ASC";
		$q = $this->db->query($sql, array($drug_id, $day));
		return $q ? $q->result() : array();
	}

	private function _clear_day($day)
	{
		$this->db->where('recon_date', $day);
		$this->db->delete('pharmacy_reconciliation_log');
	}

	private function _get_prev_day_actual_stock_map($prevDay)
	{
		$map = array();
		if (!$this->db->table_exists('pharmacy_reconciliation_log')) return $map;
		$this->db->select('drug_id, actual_stock');
		$this->db->from('pharmacy_reconciliation_log');
		$this->db->where('recon_date', $prevDay);
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = (float)$r->actual_stock;
		}
		return $map;
	}

	private function _get_drug_ids_for_day($day)
	{
		$ids = array();

		$this->db->select('drug_id');
		$this->db->from('medicine_drug_name');
		$this->db->where('InActive', 0);
		$this->db->group_start();
		$this->db->where('nStock !=', 0);
		if ($this->db->table_exists('iop_medication_administration') && $this->db->table_exists('iop_medication')) {
			$this->db->or_where('drug_id IN (SELECT DISTINCT m.medicine_id FROM iop_medication_administration a JOIN iop_medication m ON m.iop_med_id = a.iop_med_id WHERE a.InActive = 0 AND m.InActive = 0 AND a.status IN (\'DISPENSED\',\'PARTIAL\',\'RETURN\') AND DATE(a.dDateTime) = ' . $this->db->escape($day) . ')', null, false);
		}
		if ($this->db->table_exists('pharmacy_stock_adjustment')) {
			$this->db->or_where('drug_id IN (SELECT DISTINCT drug_id FROM pharmacy_stock_adjustment WHERE InActive = 0 AND DATE(created_at) = ' . $this->db->escape($day) . ')', null, false);
		}
		if ($this->db->table_exists('billing_transactions')) {
			$this->db->or_where("drug_id IN (SELECT DISTINCT item_id FROM billing_transactions WHERE InActive = 0 AND department = 'PHARMACY' AND item_type = 'DRUG' AND item_id IS NOT NULL AND DATE(created_at) = " . $this->db->escape($day) . ")", null, false);
		}
		$this->db->group_end();
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$ids[] = (int)$r->drug_id;
		}
		$ids = array_values(array_unique(array_filter($ids)));
		sort($ids);
		return $ids;
	}

	private function _get_dispensed_qty_map($day)
	{
		$map = array();
		if (!$this->db->table_exists('iop_medication_administration') || !$this->db->table_exists('iop_medication')) return $map;

		$sql = "SELECT m.medicine_id AS drug_id, SUM(a.dose_given) AS dispensed_qty
			FROM iop_medication_administration a
			JOIN iop_medication m ON m.iop_med_id = a.iop_med_id AND m.InActive = 0
			WHERE a.InActive = 0
			AND a.status IN ('DISPENSED','PARTIAL','RETURN')
			AND DATE(a.dDateTime) = ?
			GROUP BY m.medicine_id";
		$q = $this->db->query($sql, array($day));
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = (float)$r->dispensed_qty;
		}
		return $map;
	}

	private function _get_restocked_qty_map($day)
	{
		$map = array();
		if (!$this->db->table_exists('pharmacy_stock_adjustment')) return $map;
		$sql = "SELECT drug_id, SUM(qty_change) AS restocked_qty
			FROM pharmacy_stock_adjustment
			WHERE InActive = 0
			AND qty_change > 0
			AND DATE(created_at) = ?
			GROUP BY drug_id";
		$q = $this->db->query($sql, array($day));
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = (float)$r->restocked_qty;
		}
		return $map;
	}

	private function _get_billed_qty_map($day)
	{
		$map = array();
		if (!$this->db->table_exists('billing_transactions')) return $map;
		$sql = "SELECT item_id AS drug_id, SUM(quantity) AS billed_qty
			FROM billing_transactions
			WHERE InActive = 0
			AND department = 'PHARMACY'
			AND item_type = 'DRUG'
			AND item_id IS NOT NULL
			AND DATE(created_at) = ?
			GROUP BY item_id";
		$q = $this->db->query($sql, array($day));
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = (float)$r->billed_qty;
		}
		return $map;
	}

	private function _get_actual_stock_map($drugIds)
	{
		$map = array();
		if (!is_array($drugIds) || count($drugIds) === 0) return $map;
		$this->db->select('drug_id, nStock');
		$this->db->from('medicine_drug_name');
		$this->db->where('InActive', 0);
		$this->db->where_in('drug_id', $drugIds);
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = (float)$r->nStock;
		}
		return $map;
	}

	private function _ensure_log_column($table, $column, $alterSql)
	{
		$table = trim((string)$table);
		$column = trim((string)$column);
		if ($table === '' || $column === '' || $alterSql === '') return false;
		try {
			$q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE ?", array($column));
			if ($q && $q->num_rows() > 0) return true;
			$this->db->query($alterSql);
			return true;
		} catch (Exception $e) {
			return false;
		}
	}
}
