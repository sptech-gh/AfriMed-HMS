<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Pharmacy_model extends CI_Model
{
	private $phase2_env_flag_cache = array();
	private $_table_cache = array();
	private $_column_cache = array();
	
	public function __construct()
	{
		parent::__construct();
	}

	private function _pbq_pk_col()
	{
		static $pkCache = null;
		if ($pkCache !== null) {
			return $pkCache;
		}
		if ($this->table_exists('pharmacy_billing_queue')) {
			$q = $this->db->query("SHOW KEYS FROM `pharmacy_billing_queue` WHERE Key_name = 'PRIMARY'");
			if ($q && $q->num_rows() > 0) {
				$row = $q->row();
				if ($row && isset($row->Column_name) && trim((string)$row->Column_name) !== '') {
					$pkCache = (string)$row->Column_name;
					return $pkCache;
				}
			}
			if ($this->column_exists('pharmacy_billing_queue', 'id')) {
				$pkCache = 'id';
				return $pkCache;
			}
			if ($this->column_exists('pharmacy_billing_queue', 'bill_id')) {
				$pkCache = 'bill_id';
				return $pkCache;
			}
		}
		$pkCache = 'id';
		return $pkCache;
	}

	private function _pbq_total_col()
	{
		static $totalCache = null;
		if ($totalCache !== null) {
			return $totalCache;
		}
		if ($this->table_exists('pharmacy_billing_queue') && $this->column_exists('pharmacy_billing_queue', 'total_amount')) {
			$totalCache = 'total_amount';
			return $totalCache;
		}
		$totalCache = 'total';
		return $totalCache;
	}

	private function _get_drug_display_name($drug_id)
	{
		$drug_id = (int)$drug_id;
		if ($drug_id <= 0 || !$this->table_exists('medicine_drug_name')) return '';
		$row = $this->db->select('drug_name')->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
		return ($row && isset($row->drug_name)) ? (string)$row->drug_name : '';
	}

	private function _add_column_if_missing($table, $col, $definition)
	{
		if ($this->table_exists($table) && !$this->column_exists($table, $col)) {
			$this->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
			return true;
		}
		return false;
	}

	public function table_exists($table_name)
	{
		$table_name = (string)$table_name;
		if (array_key_exists($table_name, $this->_table_cache)) {
			return $this->_table_cache[$table_name];
		}
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table_name));
		$this->_table_cache[$table_name] = ($q && $q->num_rows() > 0);
		return $this->_table_cache[$table_name];
	}

	public function install_pharmacy_workflow_tables()
	{
		if (!$this->table_exists('iop_medication_administration')) {
			$this->load->model('app/nurse_enhancement_model');
			$this->nurse_enhancement_model->install_tables();
		}
		return true;
	}

	private function get_med_dispense_map($iop_med_ids)
	{
		$map = array();
		if (!is_array($iop_med_ids) || count($iop_med_ids) === 0) {
			return $map;
		}
		if (!$this->table_exists('iop_medication_administration')) {
			return $map;
		}

		$this->db->select("iop_med_id, status, dose_given, dDateTime, admin_id", false);
		$this->db->from('iop_medication_administration');
		$this->db->where('InActive', 0);
		$this->db->where_in('iop_med_id', $iop_med_ids);
		$this->db->order_by('admin_id', 'ASC');
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();

		foreach ($rows as $r) {
			$mid = (string)$r->iop_med_id;
			if (!isset($map[$mid])) {
				$map[$mid] = array(
					'dispensed_qty' => 0.0,
					'latest_status' => '',
					'latest_at' => ''
				);
			}
			$st = strtoupper(trim((string)$r->status));
			if ($st === 'DISPENSED' || $st === 'PARTIAL') {
				$map[$mid]['dispensed_qty'] += (float)$r->dose_given;
			}
			$map[$mid]['latest_status'] = $st;
			$map[$mid]['latest_at'] = (string)$r->dDateTime;
		}
		return $map;
	}

	public function get_worklist($filters = array())
	{
		$search = isset($filters['search']) ? trim((string)$filters['search']) : '';
		$date_from = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
		$date_to = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 200;
		if ($limit <= 0 || $limit > 500) {
			$limit = 200;
		}

		$selectBase = "A.iop_med_id, A.iop_id, A.medicine_id, A.dDate, IFNULL(A.instruction,'') as instruction, IFNULL(A.advice,'') as advice, IFNULL(A.days,0) as days, A.total_qty, IFNULL(B.medicine_text,'') as medicine_text, IFNULL(A.dosage,'') as dosage, B.drug_name, P.patient_no, CONCAT_WS(' ', T.cValue, P.firstname, P.middlename, P.lastname) as patient_name";
		if ($this->column_exists('iop_medication', 'frequency')) {
			$selectBase .= ", IFNULL(A.frequency,'') as frequency";
		} else {
			$selectBase .= ", '' as frequency";
		}
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$selectBase .= ", IFNULL(A.dispensing_status,'PENDING') as dispensing_status";
		} else {
			$selectBase .= ", 'PENDING' as dispensing_status";
		}
		if ($this->column_exists('iop_medication', 'payment_status')) {
			$selectBase .= ", IFNULL(A.payment_status,'PENDING') as payment_status";
		} else {
			$selectBase .= ", 'PENDING' as payment_status";
		}
		/* Phase 4 columns â€” safe with fallbacks */
		$selectBase .= $this->column_exists('iop_medication', 'prescription_no') ? ", IFNULL(A.prescription_no,'') as prescription_no" : ", '' as prescription_no";
		$selectBase .= $this->column_exists('iop_medication', 'unit')            ? ", IFNULL(A.unit,'') as unit"                         : ", '' as unit";
		$selectBase .= $this->column_exists('iop_medication', 'freq_code')       ? ", IFNULL(A.freq_code,'') as freq_code"               : ", '' as freq_code";
		$selectBase .= $this->column_exists('iop_medication', 'route')           ? ", IFNULL(A.route,'') as route"                       : ", '' as route";
		$selectBase .= $this->column_exists('iop_medication', 'is_urgent')       ? ", IFNULL(A.is_urgent,0) as is_urgent"                : ", 0 as is_urgent";
		$selectBase .= $this->column_exists('iop_medication', 'is_prn')          ? ", IFNULL(A.is_prn,0) as is_prn"                     : ", 0 as is_prn";
		$selectBase .= $this->column_exists('iop_medication', 'is_nhis_covered') ? ", IFNULL(A.is_nhis_covered,0) as is_nhis_covered"    : ", 0 as is_nhis_covered";
		$this->db->select($selectBase, false);
		$this->db->from('iop_medication A');
		$this->db->join('medicine_drug_name B', 'B.drug_id = A.medicine_id', 'left outer');
		$this->db->join('patient_details_iop I', 'I.IO_ID = A.iop_id', 'left outer');
		$this->db->join('patient_personal_info P', 'P.patient_no = I.patient_no', 'left outer');
		$this->db->join('system_parameters T', 'T.param_id = P.title', 'left outer');
		$this->db->where('A.InActive', 0);

		if ($date_from !== '') {
			$this->db->where('DATE(A.dDate) >=', $date_from);
		}
		if ($date_to !== '') {
			$this->db->where('DATE(A.dDate) <=', $date_to);
		}
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('A.iop_id', $search);
			$this->db->or_like('P.patient_no', $search);
			$this->db->or_like('P.firstname', $search);
			$this->db->or_like('P.lastname', $search);
			$this->db->or_like('B.drug_name', $search);
			$this->db->or_like('B.medicine_text', $search);
			$this->db->group_end();
		}

		$this->db->order_by('A.dDate', 'DESC');
		$this->db->limit($limit);
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();

		$ids = array();
		foreach ($rows as $r) {
			$ids[] = (int)$r->iop_med_id;
		}
		$disp = $this->get_med_dispense_map($ids);

		foreach ($rows as $r) {
			$mid = (string)$r->iop_med_id;
			$d = isset($disp[$mid]) ? $disp[$mid] : array('dispensed_qty' => 0.0, 'latest_status' => '', 'latest_at' => '');
			$r->dispensed_qty = (float)$d['dispensed_qty'];
			$r->latest_status = (string)$d['latest_status'];
			$r->latest_at = (string)$d['latest_at'];
			$total = (float)$r->total_qty;
			$st = 'PENDING';
			if ($total > 0 && $r->dispensed_qty >= $total) {
				$st = 'DISPENSED';
			} elseif ($r->dispensed_qty > 0) {
				$st = 'PARTIAL';
			} elseif ($r->latest_status === 'RESERVED') {
				$st = 'RESERVED';
			}
			$r->pharmacy_status = $st;
		}

		return $rows;
	}

	/* ================================================================== */
	/*  PHARMACY ENHANCEMENTS                                             */
	/* ================================================================== */

	private function column_exists($table, $col){
		$cache_key = $table . '.' . $col;
		if (array_key_exists($cache_key, $this->_column_cache)) {
			return $this->_column_cache[$cache_key];
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".addslashes($table)."` LIKE '".addslashes($col)."'");
		$this->_column_cache[$cache_key] = ($q && $q->num_rows() > 0);
		return $this->_column_cache[$cache_key];
	}

	public function ensure_pharmacy_adjustment_schema(){
		if ($this->table_exists('pharmacy_prescription_adjustment')) return;
		$this->db->query("CREATE TABLE IF NOT EXISTS `pharmacy_prescription_adjustment` (
			`adjustment_id` int(11) NOT NULL AUTO_INCREMENT,
			`iop_med_id` int(11) NOT NULL,
			`iop_id` varchar(25) DEFAULT NULL,
			`patient_no` varchar(25) DEFAULT NULL,
			`original_dosage` varchar(100) DEFAULT NULL,
			`original_frequency` varchar(100) DEFAULT NULL,
			`original_freq_code` varchar(20) DEFAULT NULL,
			`original_days` int(11) DEFAULT NULL,
			`original_total_qty` decimal(11,2) DEFAULT NULL,
			`original_unit` varchar(30) DEFAULT NULL,
			`adjusted_dosage` varchar(100) DEFAULT NULL,
			`adjusted_frequency` varchar(100) DEFAULT NULL,
			`adjusted_freq_code` varchar(20) DEFAULT NULL,
			`adjusted_days` int(11) DEFAULT NULL,
			`approved_qty` decimal(11,2) DEFAULT NULL,
			`billable_qty` decimal(11,2) DEFAULT NULL,
			`adjustment_reason` text,
			`adjusted_by` varchar(25) NOT NULL,
			`adjusted_at` datetime NOT NULL,
			`active` tinyint(1) NOT NULL DEFAULT 1,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`adjustment_id`),
			KEY `idx_ppa_med_active` (`iop_med_id`,`active`,`InActive`),
			KEY `idx_ppa_iop` (`iop_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
	}

	public function get_active_prescription_adjustment($iop_med_id){
		$this->ensure_pharmacy_adjustment_schema();
		$this->db->where(array('iop_med_id' => (int)$iop_med_id, 'active' => 1, 'InActive' => 0));
		$this->db->order_by('adjustment_id', 'DESC');
		$this->db->limit(1);
		return $this->db->get('pharmacy_prescription_adjustment')->row();
	}

	private function _effective_prescription_values($med){
		$qty = isset($med->total_qty) ? (float)$med->total_qty : 1.0;
		$out = array('approved_qty' => ($qty > 0 ? $qty : 1.0), 'billable_qty' => ($qty > 0 ? $qty : 1.0), 'adjustment' => null);
		if (!$med || !isset($med->iop_med_id) || !$this->table_exists('pharmacy_prescription_adjustment')) return $out;
		$adj = $this->get_active_prescription_adjustment((int)$med->iop_med_id);
		if ($adj) {
			$approved = isset($adj->approved_qty) ? (float)$adj->approved_qty : 0.0;
			$billable = isset($adj->billable_qty) ? (float)$adj->billable_qty : 0.0;
			if ($approved > 0) $out['approved_qty'] = $approved;
			if ($billable > 0) $out['billable_qty'] = $billable;
			elseif ($approved > 0) $out['billable_qty'] = $approved;
			$out['adjustment'] = $adj;
		}
		return $out;
	}

	public function adjust_prescription_for_pharmacy($iop_med_id, $data, $user_id){
		$this->ensure_pharmacy_adjustment_schema();
		$iop_med_id = (int)$iop_med_id;
		$user_id = (string)$user_id;
		$reason = isset($data['reason']) ? trim((string)$data['reason']) : '';
		if ($iop_med_id <= 0) return array('ok' => false, 'error' => 'Invalid prescription.');
		if ($reason === '') return array('ok' => false, 'error' => 'Adjustment reason is required.');

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
		if (!in_array($rxStatus, array('PENDING','VERIFIED'), true)) {
			return array('ok' => false, 'error' => 'Cannot adjust prescription in '.$rxStatus.' status.');
		}
		if (isset($med->billing_finalized_at) && trim((string)$med->billing_finalized_at) !== '') {
			return array('ok' => false, 'error' => 'Cannot adjust after billing finalization.');
		}
		if ($this->get_total_dispensed($iop_med_id) > 0) {
			return array('ok' => false, 'error' => 'Cannot adjust after dispensing has started.');
		}
		if ($this->table_exists('pharmacy_billing_queue')) {
			$bill = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
			if ($bill) {
				$payStatus = isset($bill->payment_status) ? strtoupper(trim((string)$bill->payment_status)) : 'PENDING';
				if ($payStatus !== '' && $payStatus !== 'PENDING') return array('ok' => false, 'error' => 'Cannot adjust after payment or billing exception activity.');
			}
		}
		if ($this->table_exists('billing_transactions')) {
			$this->db->where(array('department' => 'PHARMACY', 'item_ref' => 'iop_med_id:' . $iop_med_id, 'InActive' => 0));
			$this->db->limit(1);
			$txn = $this->db->get('billing_transactions')->row();
			if ($txn) {
				$invoice = isset($txn->invoice_no) ? trim((string)$txn->invoice_no) : '';
				$paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
				$status = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
				if ($invoice !== '' || $paid > 0.009 || ($status !== '' && $status !== 'PENDING')) return array('ok' => false, 'error' => 'Cannot adjust after cashier/payment activity.');
			}
		}

		$approvedQty = isset($data['approved_qty']) ? (float)$data['approved_qty'] : (isset($med->total_qty) ? (float)$med->total_qty : 0.0);
		$billableQty = (isset($data['billable_qty']) && $data['billable_qty'] !== '') ? (float)$data['billable_qty'] : $approvedQty;
		$days = (isset($data['days']) && $data['days'] !== '') ? (int)$data['days'] : (isset($med->days) ? (int)$med->days : 0);
		if ($approvedQty <= 0 || $billableQty <= 0) return array('ok' => false, 'error' => 'Approved and billable quantities must be greater than zero.');
		if ($days < 0) return array('ok' => false, 'error' => 'Duration cannot be negative.');

		$this->db->trans_begin();
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('active', 1);
		$this->db->update('pharmacy_prescription_adjustment', array('active' => 0));
		$this->db->insert('pharmacy_prescription_adjustment', array(
			'iop_med_id' => $iop_med_id,
			'iop_id' => isset($med->iop_id) ? (string)$med->iop_id : '',
			'patient_no' => isset($med->patient_no) ? (string)$med->patient_no : '',
			'original_dosage' => isset($med->dosage) ? (string)$med->dosage : '',
			'original_frequency' => isset($med->frequency) ? (string)$med->frequency : '',
			'original_freq_code' => isset($med->freq_code) ? (string)$med->freq_code : '',
			'original_days' => isset($med->days) ? (int)$med->days : null,
			'original_total_qty' => isset($med->total_qty) ? (float)$med->total_qty : null,
			'original_unit' => isset($med->unit) ? (string)$med->unit : '',
			'adjusted_dosage' => isset($data['dosage']) ? trim((string)$data['dosage']) : (isset($med->dosage) ? (string)$med->dosage : ''),
			'adjusted_frequency' => isset($data['frequency']) ? trim((string)$data['frequency']) : (isset($med->frequency) ? (string)$med->frequency : ''),
			'adjusted_freq_code' => isset($data['freq_code']) ? trim((string)$data['freq_code']) : (isset($med->freq_code) ? (string)$med->freq_code : ''),
			'adjusted_days' => $days,
			'approved_qty' => $approvedQty,
			'billable_qty' => $billableQty,
			'adjustment_reason' => $reason,
			'adjusted_by' => $user_id,
			'adjusted_at' => date('Y-m-d H:i:s'),
			'active' => 1,
			'InActive' => 0
		));
		if ($rxStatus === 'VERIFIED' && $this->table_exists('pharmacy_billing_queue')) {
			$this->create_or_update_pharmacy_bill($iop_med_id, $user_id);
		}
		$this->log_pharmacy_audit($iop_med_id, isset($med->iop_id) ? (string)$med->iop_id : '', isset($med->patient_no) ? (string)$med->patient_no : '', 'PHARMACY_ADJUSTMENT', null, 'ACTIVE', $reason, $user_id, isset($med->medicine_text) ? (string)$med->medicine_text : null);
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Adjustment failed and was rolled back.');
		}
		$this->db->trans_commit();
		return array('ok' => true, 'message' => 'Pharmacy adjustment saved.', 'approved_qty' => $approvedQty, 'billable_qty' => $billableQty);
	}

	/**
	 * One-time schema migration: add pharmacist_id, batch_no to administration table,
	 * and ensure stock_adjustment table exists.
	 */
	public function ensure_pharmacy_enhancements(){
		static $done = false;
		if ($done) return;
		$done = true;

		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		if ($this->table_exists('iop_medication_administration')) {
			if (!$this->column_exists('iop_medication_administration', 'pharmacist_id')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `pharmacist_id` varchar(25) DEFAULT NULL AFTER `cPreparedBy`");
			}
			if (!$this->column_exists('iop_medication_administration', 'batch_no')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `batch_no` varchar(50) DEFAULT NULL AFTER `pharmacist_id`");
			}
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `pharmacy_stock_adjustment` (
			`adjustment_id` int(11) NOT NULL AUTO_INCREMENT,
			`drug_id` int(11) NOT NULL,
			`adjustment_type` varchar(30) NOT NULL DEFAULT 'MANUAL',
			`qty_change` decimal(11,2) NOT NULL DEFAULT 0,
			`stock_before` decimal(11,2) NOT NULL DEFAULT 0,
			`stock_after` decimal(11,2) NOT NULL DEFAULT 0,
			`reason` text DEFAULT NULL,
			`reference_type` varchar(30) DEFAULT NULL,
			`reference_id` int(11) DEFAULT NULL,
			`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
			`created_by` varchar(25) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`adjustment_id`),
			KEY `idx_drug_id` (`drug_id`),
			KEY `idx_created_at` (`created_at`),
			KEY `idx_ref` (`reference_type`, `reference_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		
		// Fix legacy tables that used adj_id instead of adjustment_id
		if ($this->table_exists('pharmacy_stock_adjustment') && $this->column_exists('pharmacy_stock_adjustment', 'adj_id') && !$this->column_exists('pharmacy_stock_adjustment', 'adjustment_id')) {
			$this->db->query("ALTER TABLE `pharmacy_stock_adjustment` CHANGE `adj_id` `adjustment_id` int(11) NOT NULL AUTO_INCREMENT");
		}

		if ($old !== null) { $this->db->db_debug = $old; }
	}

	/* â”€â”€ Stock Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	/**
	 * Get current stock for a drug.
	 */
	public function get_drug_stock($drug_id){
		$drug_id = (int)$drug_id;
		if ($drug_id <= 0 || !$this->table_exists('medicine_drug_name')) return null;
		$this->db->select('drug_id, drug_name, nStock, re_order_level, nPrice, is_nhis_covered, nhis_price, cash_price');
		$row = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
		return $row;
	}

	/**
	 * Deduct stock after dispensing. Returns true if successful, false if insufficient.
	 */
	public function deduct_stock($drug_id, $qty, $user_id = null, $ref_type = 'DISPENSE', $ref_id = 0){
		$drug_id = (int)$drug_id;
		$qty = (float)$qty;
		if ($drug_id <= 0 || $qty <= 0) return false;

		$this->db->query(
			"UPDATE medicine_drug_name\n			SET nStock = nStock - ?\n			WHERE drug_id = ?\n			AND InActive = 0\n			AND nStock >= ?",
			array($qty, $drug_id, $qty)
		);
		if ($this->db->affected_rows() === 0) {
			return false;
		}

		$after = 0;
		$this->db->select('nStock');
		$r = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
		if ($r && isset($r->nStock)) {
			$after = (float)$r->nStock;
		}
		$before = $after + $qty;

		$this->log_stock_adjustment($drug_id, 'DISPENSE', -$qty, $before, $after, 'Auto-deducted on dispense', $ref_type, $ref_id, $user_id);
		return true;
	}

	/**
	 * Manually adjust stock (add or remove).
	 */
	public function adjust_stock($drug_id, $qty_change, $reason, $user_id = null){
		$drug_id = (int)$drug_id;
		$qty_change = (float)$qty_change;
		if ($drug_id <= 0) return false;

		$row = $this->get_drug_stock($drug_id);
		if (!$row) return false;

		$currentStock = (float)$row->nStock;
		$newStock = $currentStock + $qty_change;
		if ($newStock < 0) $newStock = 0;

		$this->db->where('drug_id', $drug_id);
		$this->db->update('medicine_drug_name', array('nStock' => $newStock));

		$type = ($qty_change >= 0) ? 'RESTOCK' : 'WRITE_OFF';
		$this->log_stock_adjustment($drug_id, $type, $qty_change, $currentStock, $newStock, $reason, 'MANUAL', 0, $user_id);
		$this->_run_pharmacy_stock_adjustment_diagnostics($drug_id, $type, $qty_change, $currentStock, $newStock, $reason, $user_id);
		return true;
	}

	private function log_stock_adjustment($drug_id, $type, $qty_change, $before, $after, $reason, $ref_type, $ref_id, $user_id){
		$this->ensure_pharmacy_enhancements();
		$this->db->insert('pharmacy_stock_adjustment', array(
			'drug_id' => (int)$drug_id,
			'adjustment_type' => (string)$type,
			'qty_change' => (float)$qty_change,
			'stock_before' => (float)$before,
			'stock_after' => (float)$after,
			'reason' => (string)$reason,
			'reference_type' => (string)$ref_type,
			'reference_id' => (int)$ref_id,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => (string)$user_id,
			'InActive' => 0
		));
	}

	/**
	 * Get stock list with reorder alerts, search, and pagination.
	 */
	public function get_stock_list($filters = array()){
		$search       = isset($filters['search'])       ? trim((string)$filters['search']) : '';
		$show_low     = isset($filters['show_low'])     ? (bool)$filters['show_low']       : false;
		$show_out     = isset($filters['show_out'])     ? (bool)$filters['show_out']       : false;
		$show_expiring= isset($filters['show_expiring'])? (bool)$filters['show_expiring']  : false;
		$show_expired = isset($filters['show_expired'])  ? (bool)$filters['show_expired']   : false;
		$limit        = isset($filters['limit'])        ? (int)$filters['limit']           : 50;
		$offset       = isset($filters['offset'])       ? (int)$filters['offset']          : 0;
		if ($limit <= 0 || $limit > 500) $limit = 50;

		if (!$this->table_exists('medicine_drug_name')) return array();

		// For expired filter, we need to join with medication_stock to find already expired batches
		if ($show_expired && $this->table_exists('medication_stock')) {
			$today = date('Y-m-d');
			$this->db->select('d.drug_id, d.drug_name, d.generic_name, d.dosage_form, d.strength, d.med_cat_id, d.nStock, d.re_order_level, d.nPrice, d.is_nhis_covered, d.nhis_price, d.cash_price, d.uom, MAX(s.expiry_date) as nearest_expiry');
			$this->db->from('medicine_drug_name d');
			$this->db->join('medication_stock s', 's.medication_id = d.drug_id AND s.InActive = 0 AND s.quantity > 0', 'inner');
			$this->db->where('d.InActive', 0);
			$this->db->where('s.expiry_date IS NOT NULL', null, false);
			$this->db->where('s.expiry_date <', $today);
			$this->db->group_by(array('d.drug_id', 'd.drug_name', 'd.generic_name', 'd.dosage_form', 'd.strength', 'd.med_cat_id', 'd.nStock', 'd.re_order_level', 'd.nPrice', 'd.is_nhis_covered', 'd.nhis_price', 'd.cash_price', 'd.uom'));
			
			if ($search !== '') {
				$this->db->group_start();
				$this->db->like('d.drug_name', $search);
				$this->db->or_like('d.generic_name', $search);
				$this->db->or_like('d.drug_id', $search);
				$this->db->group_end();
			}
			$this->db->order_by('nearest_expiry', 'DESC');
			$this->db->limit($limit, $offset);
			$q = $this->db->get();
			return $q ? $q->result() : array();
		}

		// For expiring filter, we need to join with medication_stock to find batches expiring soon
		if ($show_expiring && $this->table_exists('medication_stock')) {
			$cutoff = date('Y-m-d', strtotime('+30 days'));
			$this->db->select('d.drug_id, d.drug_name, d.generic_name, d.dosage_form, d.strength, d.med_cat_id, d.nStock, d.re_order_level, d.nPrice, d.is_nhis_covered, d.nhis_price, d.cash_price, d.uom, MIN(s.expiry_date) as nearest_expiry');
			$this->db->from('medicine_drug_name d');
			$this->db->join('medication_stock s', 's.medication_id = d.drug_id AND s.InActive = 0 AND s.quantity > 0', 'inner');
			$this->db->where('d.InActive', 0);
			$this->db->where('s.expiry_date IS NOT NULL', null, false);
			$this->db->where('s.expiry_date <=', $cutoff);
			$this->db->where('s.expiry_date >=', date('Y-m-d'));
			$this->db->group_by(array('d.drug_id', 'd.drug_name', 'd.generic_name', 'd.dosage_form', 'd.strength', 'd.med_cat_id', 'd.nStock', 'd.re_order_level', 'd.nPrice', 'd.is_nhis_covered', 'd.nhis_price', 'd.cash_price', 'd.uom'));
			
			if ($search !== '') {
				$this->db->group_start();
				$this->db->like('d.drug_name', $search);
				$this->db->or_like('d.generic_name', $search);
				$this->db->or_like('d.drug_id', $search);
				$this->db->group_end();
			}
			$this->db->order_by('nearest_expiry', 'ASC');
			$this->db->limit($limit, $offset);
			$q = $this->db->get();
			return $q ? $q->result() : array();
		}

		$this->db->select('drug_id, drug_name, generic_name, dosage_form, strength, med_cat_id, nStock, re_order_level, nPrice, is_nhis_covered, nhis_price, cash_price, uom');
		$this->db->from('medicine_drug_name');
		$this->db->where('InActive', 0);

		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('drug_name', $search);
			$this->db->or_like('generic_name', $search);
			$this->db->or_like('drug_id', $search);
			$this->db->group_end();
		}
		if ($show_out) {
			$this->db->where('nStock <=', 0);
		} elseif ($show_low) {
			$this->db->where('nStock <= re_order_level', null, false);
		}
		$this->db->order_by('nStock', 'ASC');
		$this->db->order_by('drug_name', 'ASC');
		$this->db->limit($limit, $offset);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function count_stock_list($filters = array()){
		$search       = isset($filters['search'])       ? trim((string)$filters['search']) : '';
		$show_low     = isset($filters['show_low'])     ? (bool)$filters['show_low']       : false;
		$show_out     = isset($filters['show_out'])     ? (bool)$filters['show_out']       : false;
		$show_expiring= isset($filters['show_expiring'])? (bool)$filters['show_expiring']  : false;
		$show_expired = isset($filters['show_expired'])  ? (bool)$filters['show_expired']   : false;
		if (!$this->table_exists('medicine_drug_name')) return 0;

		// For expired filter, count distinct drugs with already expired batches
		if ($show_expired && $this->table_exists('medication_stock')) {
			$today = date('Y-m-d');
			$this->db->select('COUNT(DISTINCT d.drug_id) as cnt');
			$this->db->from('medicine_drug_name d');
			$this->db->join('medication_stock s', 's.medication_id = d.drug_id AND s.InActive = 0 AND s.quantity > 0', 'inner');
			$this->db->where('d.InActive', 0);
			$this->db->where('s.expiry_date IS NOT NULL', null, false);
			$this->db->where('s.expiry_date <', $today);
			
			if ($search !== '') {
				$this->db->group_start();
				$this->db->like('d.drug_name', $search);
				$this->db->or_like('d.generic_name', $search);
				$this->db->or_like('d.drug_id', $search);
				$this->db->group_end();
			}
			$q = $this->db->get();
			$r = $q ? $q->row() : null;
			return $r ? (int)$r->cnt : 0;
		}

		// For expiring filter, count distinct drugs with expiring batches
		if ($show_expiring && $this->table_exists('medication_stock')) {
			$cutoff = date('Y-m-d', strtotime('+30 days'));
			$this->db->select('COUNT(DISTINCT d.drug_id) as cnt');
			$this->db->from('medicine_drug_name d');
			$this->db->join('medication_stock s', 's.medication_id = d.drug_id AND s.InActive = 0 AND s.quantity > 0', 'inner');
			$this->db->where('d.InActive', 0);
			$this->db->where('s.expiry_date IS NOT NULL', null, false);
			$this->db->where('s.expiry_date <=', $cutoff);
			$this->db->where('s.expiry_date >=', date('Y-m-d'));
			
			if ($search !== '') {
				$this->db->group_start();
				$this->db->like('d.drug_name', $search);
				$this->db->or_like('d.generic_name', $search);
				$this->db->or_like('d.drug_id', $search);
				$this->db->group_end();
			}
			$q = $this->db->get();
			$r = $q ? $q->row() : null;
			return $r ? (int)$r->cnt : 0;
		}

		$this->db->from('medicine_drug_name');
		$this->db->where('InActive', 0);
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('drug_name', $search);
			$this->db->or_like('generic_name', $search);
			$this->db->or_like('drug_id', $search);
			$this->db->group_end();
		}
		if ($show_out) {
			$this->db->where('nStock <=', 0);
		} elseif ($show_low) {
			$this->db->where('nStock <= re_order_level', null, false);
		}
		return $this->db->count_all_results();
	}

	public function count_low_stock(){
		if (!$this->table_exists('medicine_drug_name')) return 0;
		$this->db->from('medicine_drug_name');
		$this->db->where('InActive', 0);
		$this->db->where('nStock <= re_order_level', null, false);
		$this->db->where('nStock > 0', null, false);
		return $this->db->count_all_results();
	}

	public function count_out_of_stock(){
		if (!$this->table_exists('medicine_drug_name')) return 0;
		$this->db->from('medicine_drug_name');
		$this->db->where('InActive', 0);
		$this->db->where('nStock <=', 0);
		return $this->db->count_all_results();
	}

	/**
	 * Get stock adjustment history for a drug.
	 */
	public function get_stock_history($drug_id, $limit = 20){
		$this->ensure_pharmacy_enhancements();
		$drug_id = (int)$drug_id;
		$this->db->where(array('drug_id' => $drug_id, 'InActive' => 0));
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit((int)$limit);
		$q = $this->db->get('pharmacy_stock_adjustment');
		return $q ? $q->result() : array();
	}

	/* â”€â”€ Dispense Validation â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	/**
	 * Validate that dispensing is allowed. Returns array with 'ok' and 'errors'.
	 * Checks: valid med, remaining qty, stock availability, NHIS payment gate.
	 */
	public function validate_dispense($iop_med_id, $qty, $status){
		$iop_med_id = (int)$iop_med_id;
		$qty = (float)$qty;
		$status = strtoupper(trim((string)$status));
		$errors = array();

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) {
			return array('ok' => false, 'errors' => array('Prescription not found.'), 'med' => null);
		}

		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				$errors[] = 'Prescription must be VERIFIED before dispensing.';
			}
		}

		$effective = $this->_effective_prescription_values($med);
		$totalQty = isset($effective['approved_qty']) ? (float)$effective['approved_qty'] : (float)$med->total_qty;
		$dispensedQty = $this->get_total_dispensed($iop_med_id);
		$remaining = $totalQty - $dispensedQty;
		if ($remaining < 0) $remaining = 0;

		if ($status === 'DISPENSED' || $status === 'PARTIAL') {
			if ($qty <= 0) {
				$errors[] = 'Quantity must be greater than zero.';
			}
			if ($qty > $remaining + 0.001) {
				$errors[] = 'Cannot dispense '.$qty.' â€” only '.$remaining.' remaining on prescription.';
			}

			$drugId = isset($med->medicine_id) ? (int)$med->medicine_id : 0;
			if ($drugId > 0) {
				$drugRow = $this->get_drug_stock($drugId);
				if ($drugRow) {
					$currentStock = (float)$drugRow->nStock;
					if ($currentStock < $qty) {
						$errors[] = 'Insufficient stock. Available: '.$currentStock.', Requested: '.$qty.'.';
					}
				}
			}
		}

		if ($dispensedQty >= $totalQty && $totalQty > 0) {
			$errors[] = 'This prescription is already fully dispensed.';
		}

		return array('ok' => (count($errors) === 0), 'errors' => $errors, 'med' => $med);
	}

	/**
	 * Check NHIS payment gate for a prescription. Uses billing_model.
	 */
	public function check_prescription_payment_gate($iop_med_id){
		$iop_med_id = (int)$iop_med_id;
		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('allowed' => false, 'reason' => 'Prescription not found.');

		$iop_id = isset($med->iop_id) ? (string)$med->iop_id : '';
		if ($iop_id === '') return array('allowed' => true, 'reason' => '');

		$this->db->select('patient_no');
		$this->db->where(array('IO_ID' => $iop_id, 'InActive' => 0));
		$this->db->limit(1);
		$pd = $this->db->get('patient_details_iop')->row();
		$patient_no = ($pd && isset($pd->patient_no)) ? (string)$pd->patient_no : '';

		if ($patient_no === '') return array('allowed' => true, 'reason' => '');

		$this->load->model('app/billing_model');
		$gate = $this->billing_model->check_nhis_payment_gate($patient_no, $iop_id, 'PHARMACY');
		return $gate;
	}

	/**
	 * Full dispense action: validate, log administration, deduct stock, save pharmacist.
	 */
	public function dispense_medication($iop_med_id, $qty, $status, $notes, $user_id, $batch_no = ''){
		$iop_med_id = (int)$iop_med_id;
		$qty = (float)$qty;
		$status = strtoupper(trim((string)$status));
		if (!in_array($status, array('DISPENSED', 'PARTIAL'), true)) {
			return array('ok' => false, 'errors' => array('Invalid dispensing status.'), 'med' => null);
		}

		$validation = $this->validate_dispense($iop_med_id, $qty, $status);
		if (!$validation['ok']) {
			return $validation;
		}
		$med = $validation['med'];
		$iop_id = isset($med->iop_id) ? (string)$med->iop_id : '';

		$mode = '';
		if ($this->_phase2_kill_switch_enabled()) {
			$mode = 'KILL_SWITCH';
			$this->_audit_phase2_dispense('PHASE2_KILL_SWITCH_BYPASS', $iop_med_id, $iop_id, null, $user_id, array('action' => 'DISPENSE', 'status' => $status, 'qty' => $qty, 'flag' => 'PHARMACY_PHASE2_ENFORCEMENT_KILL_SWITCH', 'decision' => 'BYPASS'));
		} else {
			$gate = $this->check_flexible_dispense_gate($iop_med_id);
			if (!isset($gate['allowed']) || !$gate['allowed']) {
				$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required before dispensing.';
				$this->_audit_phase2_dispense('PHASE2_DISPENSE_GATE_BLOCKED', $iop_med_id, $iop_id, null, $user_id, array('reason' => $reason, 'status' => $status, 'qty' => $qty));
				return array('ok' => false, 'errors' => array($reason), 'med' => $med);
			}
			$mode = isset($gate['mode']) ? strtoupper(trim((string)$gate['mode'])) : '';
		}
		$exceptionModes = array('EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','ADMITTED');
		if (!in_array($mode, $exceptionModes, true) && $this->_phase2_enforce_flag('PHARMACY_ENFORCE_DISPENSE_PAYMENT_MATCH')) {
			$effective = $this->_effective_prescription_values($med);
			$paidCap = $this->_get_ssot_paid_qty_total($iop_med_id, isset($effective['approved_qty']) ? (float)$effective['approved_qty'] : (float)$med->total_qty);
			if (!$paidCap['ok']) {
				return array('ok' => false, 'errors' => array($paidCap['error']), 'med' => $med);
			}
			$alreadyDispensed = $this->get_total_dispensed($iop_med_id);
			$remainingPaid = (float)$paidCap['paid_qty_total'] - (float)$alreadyDispensed;
			if ($remainingPaid < 0) $remainingPaid = 0;
			if ($remainingPaid <= 0.0001) {
				$this->_audit_phase2_dispense('PHASE2_DISPENSE_PAYMENT_CAP_BLOCKED', $iop_med_id, $iop_id, null, $user_id, array('remaining_paid' => $remainingPaid, 'already_dispensed' => $alreadyDispensed, 'requested_qty' => $qty));
				return array('ok' => false, 'errors' => array('Payment required before dispensing. If clinically necessary, use the emergency/waiver workflow.'), 'med' => $med);
			}
			if ($qty > $remainingPaid + 0.0001) {
				$this->_audit_phase2_dispense('PHASE2_DISPENSE_PAYMENT_CAP_BLOCKED', $iop_med_id, $iop_id, null, $user_id, array('remaining_paid' => $remainingPaid, 'already_dispensed' => $alreadyDispensed, 'requested_qty' => $qty));
				return array('ok' => false, 'errors' => array('Payment only covers '.$remainingPaid.' remaining unit(s) for this prescription. If clinically necessary, use the emergency/waiver workflow.'), 'med' => $med);
			}
		}

		$lastErrors = array();
		for ($attempt = 0; $attempt < 2; $attempt++) {
			$adminId = 0;
			$errors = array();
			$this->db->trans_begin();

			$this->load->model('app/nurse_enhancement_model');
			$saved = $this->nurse_enhancement_model->save_medication_administration(
				$iop_med_id, $iop_id, $status, $qty, $notes, date('Y-m-d H:i:s'), $user_id
			);
			if (!$saved) {
				$errors = array('Dispense failed: unable to record administration.');
			}

			if (count($errors) === 0) {
				$adminId = (int)$this->db->insert_id();
				if ($adminId <= 0) {
					$errors = array('Dispense failed: administration record not created.');
				}
			}

			if (count($errors) === 0 && $this->column_exists('iop_medication_administration', 'pharmacist_id')) {
				$updateData = array('pharmacist_id' => (string)$user_id);
				if (trim((string)$batch_no) !== '') {
					$updateData['batch_no'] = trim((string)$batch_no);
				}
				$this->db->where('admin_id', $adminId);
				$this->db->update('iop_medication_administration', $updateData);
			}

			if (count($errors) === 0 && ($status === 'DISPENSED' || $status === 'PARTIAL') && $qty > 0) {
				$drugId = isset($med->medicine_id) ? (int)$med->medicine_id : 0;
				if ($drugId > 0) {
					$deductOk = true;
					if ($this->has_batch_stock($drugId)) {
						$deductOk = (bool)$this->deduct_batch_stock_fefo($drugId, $qty, $user_id, 'DISPENSE', $iop_med_id);
					} else {
						$deductOk = (bool)$this->deduct_stock($drugId, $qty, $user_id, 'DISPENSE', $iop_med_id);
					}
					if (!$deductOk) {
						$errors = array('Dispense failed: stock deduction failed.');
					}
				}
			}

			if (count($errors) === 0) {
				$this->update_dispensing_status($iop_med_id);
				// Sync dispense status to pharmacy_billing_queue via canonical model
				$this->load->model('app/pharmacy_billing_model');
				$pbq_status = ($status === 'DISPENSED') ? 'DISPENSED' : 'PARTIAL';
				$pbqOk = $this->pharmacy_billing_model->sync_dispense_status($iop_med_id, $pbq_status, $user_id);
				if (!$pbqOk) {
					log_message('error', '[PBQ_SYNC_MISS] iop_med_id=' . $iop_med_id . ' status=' . $pbq_status
						. ' - no PBQ row found or table missing; non-blocking');
				}
			}

			if (count($errors) === 0 && $this->db->table_exists('billing_transactions')) {
				$syncOk = true;
				$syncErr = '';
				$this->load->model('app/billing_transaction_model');
				$txn = $this->billing_transaction_model->get_transaction_by_item_ref('iop_med_id:' . $iop_med_id, 'PHARMACY');
				if (!$txn) {
					$sync = $this->billing_transaction_model->sync_pharmacy_medication($iop_med_id, $user_id);
					if (!is_array($sync) || !isset($sync['ok']) || !$sync['ok']) {
						$syncOk = false;
						$syncErr = (is_array($sync) && isset($sync['error'])) ? (string)$sync['error'] : 'Failed to sync billing transaction.';
					} else {
						$txn = $this->billing_transaction_model->get_transaction((int)$sync['txn_id']);
					}
				}
				if ($syncOk && $txn) {
					$orderStatus = isset($txn->order_status) ? strtoupper(trim((string)$txn->order_status)) : 'ORDERED';
					if ($orderStatus === 'ORDERED') {
						$r = $this->billing_transaction_model->update_order_status((int)$txn->txn_id, 'APPROVED', $user_id);
						if (!is_array($r) || !isset($r['ok']) || !$r['ok']) {
							$syncOk = false;
							$syncErr = (is_array($r) && isset($r['error'])) ? (string)$r['error'] : 'Failed to approve billing transaction.';
						}
						$orderStatus = 'APPROVED';
					}
					if ($syncOk && $orderStatus === 'APPROVED') {
						$r = $this->billing_transaction_model->update_order_status((int)$txn->txn_id, 'DISPENSED', $user_id);
						if (!is_array($r) || !isset($r['ok']) || !$r['ok']) {
							$syncOk = false;
							$syncErr = (is_array($r) && isset($r['error'])) ? (string)$r['error'] : 'Failed to mark billing transaction as dispensed.';
						}
					}
				}
				if (!$syncOk) {
					$errors = array('Dispense failed: ' . $syncErr);
				}
			}

			if (count($errors) === 0 && $this->db->trans_status() === FALSE) {
				$errors = array('Dispense failed: transaction rolled back');
			}

			if (count($errors) === 0) {
				$this->db->trans_commit();
				$this->_run_pharmacy_dispense_diagnostics($iop_med_id, $qty, $status, $user_id, $mode, $adminId);
				return array('ok' => true, 'errors' => array(), 'admin_id' => $adminId);
			}

			$lastErrors = $errors;
			$retryable = $this->_is_retryable_lock_error();
			$this->db->trans_rollback();
			if ($retryable && $attempt < 1) {
				continue;
			}
			return array('ok' => false, 'errors' => $errors, 'med' => $med);
		}

		return array('ok' => false, 'errors' => $lastErrors, 'med' => $med);
	}

	private function _run_pharmacy_dispense_diagnostics($iop_med_id, $qty, $status, $user_id, $mode, $admin_id){
		try {
			$this->load->model('app/Pharmacy_diagnostics_model', 'pharmacy_diagnostics_model');
			$this->pharmacy_diagnostics_model->log_dispense_shadow($iop_med_id, $qty, $status, $user_id, $mode, $admin_id);
		} catch (Exception $e) {
			return;
		}
	}

	private function _run_pharmacy_stock_adjustment_diagnostics($drug_id, $type, $qty_change, $before, $after, $reason, $user_id){
		try {
			$this->load->model('app/Pharmacy_diagnostics_model', 'pharmacy_diagnostics_model');
			$this->pharmacy_diagnostics_model->log_stock_adjustment_shadow($drug_id, $type, $qty_change, $before, $after, $reason, $user_id);
		} catch (Exception $e) {
			return;
		}
	}

	private function _run_pharmacy_expiry_removal_diagnostics($stock_id, $drug_id, $qty, $batch_number, $reason, $user_id){
		try {
			$this->load->model('app/Pharmacy_diagnostics_model', 'pharmacy_diagnostics_model');
			$this->pharmacy_diagnostics_model->log_expiry_removal_shadow($stock_id, $drug_id, $qty, $batch_number, $reason, $user_id);
		} catch (Exception $e) {
			return;
		}
	}

	private function _phase2_kill_switch_enabled()
	{
		return $this->_phase2_env_flag('PHARMACY_PHASE2_ENFORCEMENT_KILL_SWITCH');
	}

	private function _phase2_enforce_flag($name)
	{
		if ($this->_phase2_kill_switch_enabled()) return false;
		return $this->_phase2_env_flag($name);
	}

	private function _phase2_env_flag($name)
	{
		$name = (string)$name;
		if ($name === '') return false;
		if (array_key_exists($name, $this->phase2_env_flag_cache)) {
			return (bool)$this->phase2_env_flag_cache[$name];
		}
		$env = getenv($name);
		if ($env === false) {
			$this->phase2_env_flag_cache[$name] = false;
			return false;
		}
		$val = filter_var($env, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($val === null) {
			$val = (trim((string)$env) !== '' && trim((string)$env) !== '0');
		}
		$this->phase2_env_flag_cache[$name] = (bool)$val;
		return (bool)$val;
	}

	private function _audit_phase2_dispense($action_type, $iop_med_id, $iop_id, $patient_no, $user_id, $details = array())
	{
		try {
			if ($patient_no === null || $patient_no === '') {
				$patient_no = $this->_get_patient_no_for_iop($iop_id);
			}
			$this->load->model('app/unified_billing_model');
			$this->unified_billing_model->log_billing_audit($action_type, array(
				'entity_type' => 'IOP_MEDICATION',
				'entity_id' => (string)$iop_med_id,
				'invoice_no' => null,
				'patient_no' => $patient_no !== '' ? (string)$patient_no : null,
				'description' => is_string($details) ? $details : json_encode($details),
			));
		} catch (Exception $e) {
			return;
		}
	}

	private function _is_retryable_lock_error(){
		if (!method_exists($this->db, 'error')) return false;
		$e = $this->db->error();
		if (!is_array($e) || !isset($e['code'])) return false;
		$code = (int)$e['code'];
		return ($code === 1213 || $code === 1205);
	}

	private function _get_ssot_paid_qty_total($iop_med_id, $prescribed_qty){
		$iop_med_id = (int)$iop_med_id;
		$prescribed_qty = (float)$prescribed_qty;
		$this->load->model('app/billing_transaction_model');
		if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'ensure_billing_transaction_schema')) {
			$this->billing_transaction_model->ensure_billing_transaction_schema();
		}
		$item_ref = 'iop_med_id:' . $iop_med_id;
		$txn = null;
		if ($this->db->table_exists('billing_transactions')) {
			$this->db->where('InActive', 0);
			$this->db->where('department', 'PHARMACY');
			$this->db->where('item_ref', $item_ref);
			$this->db->limit(1);
			$txn = $this->db->get('billing_transactions')->row();
		}
		if (!$txn) {
			if ($this->db->table_exists('pharmacy_billing_queue')) {
				$bill = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
				if ($bill) {
					$payStatus = isset($bill->payment_status) ? strtoupper(trim((string)$bill->payment_status)) : 'PENDING';
					$extStatus = isset($bill->extended_status) ? strtoupper(trim((string)$bill->extended_status)) : '';
					$payer = isset($bill->payer_type) ? strtoupper(trim((string)$bill->payer_type)) : 'CASH';
					$exceptions = array('EXTERNAL_PURCHASE','EXTERNAL','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','WAIVER_APPROVED','WAIVER_REQUESTED');
					if ($prescribed_qty <= 0) {
						$prescribed_qty = isset($bill->quantity) ? (float)$bill->quantity : 0.0;
					}
					if ($payer === 'NHIS' || $payStatus === 'PAID' || $payStatus === 'WAIVED' || in_array($extStatus, $exceptions, true) || in_array($payStatus, $exceptions, true)) {
						return array('ok' => true, 'paid_qty_total' => max(0.0, (float)$prescribed_qty));
					}
					return array('ok' => true, 'paid_qty_total' => 0.0);
				}
			}
			return array('ok' => false, 'error' => 'No SSOT billing record found. Ask cashier to create invoice and record payment first.');
		}
		$payer = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : 'CASH';
		$pay = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
		$net = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
		$paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
		$bal = isset($txn->balance_amount) ? (float)$txn->balance_amount : max(0.0, $net - $paid);

		if ($prescribed_qty <= 0) {
			$prescribed_qty = isset($txn->quantity) ? (float)$txn->quantity : 0.0;
		}
		if ($prescribed_qty <= 0) {
			return array('ok' => true, 'paid_qty_total' => 0.0);
		}

		if ($payer === 'NHIS' || $pay === 'NHIS') {
			return array('ok' => true, 'paid_qty_total' => $prescribed_qty);
		}
		if ($payer !== '' && $payer !== 'CASH') {
			return array('ok' => true, 'paid_qty_total' => $prescribed_qty);
		}
		if (in_array($pay, array('PAID', 'WAIVED'), true) || $bal <= 0.0001) {
			return array('ok' => true, 'paid_qty_total' => $prescribed_qty);
		}
		if ($pay === 'PARTIAL') {
			if ($net <= 0.0001) {
				return array('ok' => true, 'paid_qty_total' => $prescribed_qty);
			}
			$f = $paid / $net;
			if ($f < 0) $f = 0;
			if ($f > 1) $f = 1;
			$q = $prescribed_qty * $f;
			$q = floor($q * 100.0) / 100.0;
			if ($q > $prescribed_qty) $q = $prescribed_qty;
			return array('ok' => true, 'paid_qty_total' => $q);
		}
		return array('ok' => true, 'paid_qty_total' => 0.0);
	}

	private function get_total_dispensed($iop_med_id){
		if (!$this->table_exists('iop_medication_administration')) return 0;
		$this->db->select('SUM(dose_given) AS total_disp', false);
		$this->db->where(array('iop_med_id' => (int)$iop_med_id, 'InActive' => 0));
		$this->db->where_in('status', array('DISPENSED', 'PARTIAL', 'RETURN'));
		$r = $this->db->get('iop_medication_administration')->row();
		return ($r && isset($r->total_disp)) ? (float)$r->total_disp : 0;
	}

	/* â”€â”€ Enhanced Worklist with NHIS + Payment + Stock â”€ */

	/**
	 * Enhanced worklist that adds NHIS coverage, stock level, and payment info.
	 */
	public function get_enhanced_worklist($filters = array()){
		$rows = $this->get_worklist($filters);

		$iop_ids = array();
		$drug_ids = array();
		foreach ($rows as $r) {
			if (isset($r->iop_id) && !in_array((string)$r->iop_id, $iop_ids)) {
				$iop_ids[] = (string)$r->iop_id;
			}
			$did = isset($r->medicine_id) ? (int)$r->medicine_id : 0;
			if ($did > 0 && !in_array($did, $drug_ids)) {
				$drug_ids[] = $did;
			}
		}

		$stockMap = $this->get_stock_map($drug_ids);
		$nhisMap = $this->get_nhis_map($drug_ids);
		$payerMap = $this->get_payer_map($iop_ids);

		foreach ($rows as $r) {
			$did = isset($r->medicine_id) ? (int)$r->medicine_id : 0;
			$iid = isset($r->iop_id) ? (string)$r->iop_id : '';

			$r->current_stock = isset($stockMap[$did]) ? (float)$stockMap[$did]['nStock'] : 0;
			$r->reorder_level = isset($stockMap[$did]) ? (float)$stockMap[$did]['re_order_level'] : 0;
			$r->stock_low = ($r->current_stock <= $r->reorder_level);

			$r->is_nhis_covered = isset($nhisMap[$did]) ? (bool)$nhisMap[$did]['is_nhis_covered'] : false;
			$r->nhis_price = isset($nhisMap[$did]) ? (float)$nhisMap[$did]['nhis_price'] : 0;
			$r->cash_price = isset($nhisMap[$did]) ? (float)$nhisMap[$did]['cash_price'] : 0;

			$r->payer_type = isset($payerMap[$iid]) ? (string)$payerMap[$iid] : 'CASH';
		}

		return $rows;
	}

	private function get_stock_map($drug_ids){
		$map = array();
		if (!is_array($drug_ids) || count($drug_ids) === 0) return $map;
		if (!$this->table_exists('medicine_drug_name')) return $map;

		$this->db->select('drug_id, nStock, re_order_level');
		$this->db->where('InActive', 0);
		$this->db->where_in('drug_id', $drug_ids);
		$rows = $this->db->get('medicine_drug_name')->result();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = array(
				'nStock' => (float)$r->nStock,
				're_order_level' => (float)$r->re_order_level
			);
		}
		return $map;
	}

	private function get_nhis_map($drug_ids){
		$map = array();
		if (!is_array($drug_ids) || count($drug_ids) === 0) return $map;
		if (!$this->table_exists('medicine_drug_name')) return $map;
		if (!$this->column_exists('medicine_drug_name', 'is_nhis_covered')) return $map;

		$this->db->select('drug_id, is_nhis_covered, nhis_price, cash_price');
		$this->db->where('InActive', 0);
		$this->db->where_in('drug_id', $drug_ids);
		$rows = $this->db->get('medicine_drug_name')->result();
		foreach ($rows as $r) {
			$map[(int)$r->drug_id] = array(
				'is_nhis_covered' => ((int)$r->is_nhis_covered === 1),
				'nhis_price' => (float)$r->nhis_price,
				'cash_price' => (float)$r->cash_price
			);
		}
		return $map;
	}

	private function get_payer_map($iop_ids){
		$map = array();
		if (!is_array($iop_ids) || count($iop_ids) === 0) return $map;

		$this->load->model('app/billing_model');

		$this->db->select('IO_ID, patient_no');
		$this->db->where('InActive', 0);
		$this->db->where_in('IO_ID', $iop_ids);
		$rows = $this->db->get('patient_details_iop')->result();

		foreach ($rows as $r) {
			$pNo = isset($r->patient_no) ? (string)$r->patient_no : '';
			if ($pNo !== '') {
				$payer = $this->billing_model->determine_payer_type($pNo);
				$map[(string)$r->IO_ID] = $payer;
			} else {
				$map[(string)$r->IO_ID] = 'CASH';
			}
		}
		return $map;
	}

	/* â”€â”€ Summary Counts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function count_pending_prescriptions(){
		if (!$this->table_exists('iop_medication')) return 0;
		$sql = "SELECT COUNT(*) AS c FROM iop_medication m
			WHERE m.InActive = 0
			AND m.total_qty > 0
			AND (
				SELECT IFNULL(SUM(a.dose_given),0) FROM iop_medication_administration a
				WHERE a.iop_med_id = m.iop_med_id AND a.InActive = 0
				AND a.status IN ('DISPENSED','PARTIAL','RETURN')
			) < m.total_qty";
		$r = $this->db->query($sql)->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_dispensed_today(){
		if (!$this->table_exists('iop_medication_administration')) return 0;
		$this->db->where(array('InActive' => 0));
		$this->db->where("DATE(dDateTime) = '".date('Y-m-d')."'");
		$this->db->where_in('status', array('DISPENSED','PARTIAL'));
		return $this->db->count_all_results('iop_medication_administration');
	}

	public function count_partial_prescriptions(){
		if (!$this->table_exists('iop_medication')) return 0;
		if (!$this->column_exists('iop_medication', 'dispensing_status')) return 0;
		return $this->db->where(array('dispensing_status' => 'PARTIAL', 'InActive' => 0))->count_all_results('iop_medication');
	}

	/* ================================================================== */
	/*  PHARMACY V2 â€” Full Pharmacy System Enhancement                     */
	/* ================================================================== */

	/**
	 * One-time schema migration for Pharmacy V2.
	 * - Adds generic_name, dosage_form, strength to medicine_drug_name
	 * - Creates medication_stock table for batch/expiry tracking
	 * - Adds dispensing_status, prescribed_by, frequency to iop_medication
	 * - Seeds Ghana-relevant medicine categories
	 */
	public function ensure_pharmacy_v2_schema(){
		static $v2done = false;
		if ($v2done) return;
		$v2done = true;

		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		if ($this->table_exists('medicine_drug_name')) {
			if (!$this->column_exists('medicine_drug_name', 'generic_name')) {
				$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `generic_name` varchar(200) DEFAULT NULL AFTER `drug_name`");
			}
			if (!$this->column_exists('medicine_drug_name', 'dosage_form')) {
				$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `dosage_form` varchar(50) DEFAULT NULL AFTER `generic_name`");
			}
			if (!$this->column_exists('medicine_drug_name', 'strength')) {
				$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `strength` varchar(50) DEFAULT NULL AFTER `dosage_form`");
			}
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `medication_stock` (
			`stock_id` int(11) NOT NULL AUTO_INCREMENT,
			`medication_id` int(11) NOT NULL,
			`batch_number` varchar(50) NOT NULL,
			`quantity` decimal(11,2) NOT NULL DEFAULT 0,
			`expiry_date` date DEFAULT NULL,
			`unit_cost` decimal(18,2) NOT NULL DEFAULT 0,
			`selling_price` decimal(18,2) NOT NULL DEFAULT 0,
			`received_date` date DEFAULT NULL,
			`supplier` varchar(200) DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`created_by` varchar(25) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`stock_id`),
			KEY `idx_med_id` (`medication_id`),
			KEY `idx_batch` (`batch_number`),
			KEY `idx_expiry` (`expiry_date`),
			KEY `idx_active_stock` (`medication_id`, `InActive`, `quantity`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		if ($this->table_exists('iop_medication')) {
			if (!$this->column_exists('iop_medication', 'dispensing_status')) {
				$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `dispensing_status` varchar(20) NOT NULL DEFAULT 'PENDING'");
				$this->db->query("ALTER TABLE `iop_medication` ADD INDEX `idx_disp_status` (`dispensing_status`)");
			}
			if (!$this->column_exists('iop_medication', 'prescribed_by')) {
				$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `prescribed_by` varchar(25) DEFAULT NULL");
			}
			if (!$this->column_exists('iop_medication', 'frequency')) {
				$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `frequency` varchar(100) DEFAULT NULL");
			}
		}

		$this->seed_ghana_categories();

		if ($old !== null) { $this->db->db_debug = $old; }
	}

	private function seed_ghana_categories(){
		if (!$this->table_exists('medicine_category')) return;
		$categories = array(
			'ANTIBIOTICS', 'ANTIMALARIALS', 'ANALGESICS (PAINKILLERS)',
			'ANTIHYPERTENSIVES', 'ANTIDIABETICS', 'GASTROINTESTINAL DRUGS',
			'ANTIFUNGALS', 'ANTIVIRALS', 'VITAMINS & SUPPLEMENTS',
			'RESPIRATORY DRUGS', 'DERMATOLOGICALS', 'VACCINES', 'EMERGENCY DRUGS'
		);
		foreach ($categories as $cat) {
			$this->db->where(array('med_category_name' => $cat, 'InActive' => 0));
			$exists = $this->db->count_all_results('medicine_category');
			if ($exists == 0) {
				$this->db->insert('medicine_category', array(
					'med_category_name' => $cat,
					'med_category_desc' => $cat,
					'InActive' => 0
				));
			}
		}
	}

	/* â”€â”€ Batch Stock Management (FEFO) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function add_batch_stock($data, $user_id = null){
		$this->ensure_pharmacy_v2_schema();
		$medication_id = isset($data['medication_id']) ? (int)$data['medication_id'] : 0;
		$batch_number = isset($data['batch_number']) ? trim((string)$data['batch_number']) : '';
		$quantity = isset($data['quantity']) ? (float)$data['quantity'] : 0;
		$expiry_date = isset($data['expiry_date']) ? trim((string)$data['expiry_date']) : null;
		$unit_cost = isset($data['unit_cost']) ? (float)$data['unit_cost'] : 0;
		$selling_price = isset($data['selling_price']) ? (float)$data['selling_price'] : 0;
		$supplier = isset($data['supplier']) ? trim((string)$data['supplier']) : '';

		if ($medication_id <= 0 || $quantity <= 0 || $batch_number === '') {
			return array('ok' => false, 'error' => 'Medication ID, batch number, and quantity are required.');
		}

		$this->db->where(array('medication_id' => $medication_id, 'batch_number' => $batch_number, 'InActive' => 0));
		$existing = $this->db->get('medication_stock')->row();

		if ($existing) {
			$newQty = (float)$existing->quantity + $quantity;
			$this->db->where('stock_id', $existing->stock_id);
			$this->db->update('medication_stock', array('quantity' => $newQty));
		} else {
			$this->db->insert('medication_stock', array(
				'medication_id' => $medication_id,
				'batch_number' => $batch_number,
				'quantity' => $quantity,
				'expiry_date' => ($expiry_date !== '' && $expiry_date !== null) ? $expiry_date : null,
				'unit_cost' => $unit_cost,
				'selling_price' => $selling_price,
				'received_date' => date('Y-m-d'),
				'supplier' => $supplier,
				'created_at' => date('Y-m-d H:i:s'),
				'created_by' => (string)$user_id,
				'InActive' => 0
			));
		}

		$this->sync_master_stock($medication_id);

		$this->log_stock_adjustment($medication_id, 'BATCH_RESTOCK', $quantity,
			0, 0, 'Batch restock: ' . $batch_number, 'BATCH', 0, $user_id);

		return array('ok' => true, 'error' => '');
	}

	public function get_batch_stock($medication_id, $include_expired = false){
		$this->ensure_pharmacy_v2_schema();
		$medication_id = (int)$medication_id;
		if ($medication_id <= 0) return array();

		$this->db->where(array('medication_id' => $medication_id, 'InActive' => 0));
		$this->db->where('quantity >', 0);
		if (!$include_expired) {
			$this->db->group_start();
			$this->db->where('expiry_date IS NULL', null, false);
			$this->db->or_where('expiry_date >=', date('Y-m-d'));
			$this->db->group_end();
		}
		$this->db->order_by('expiry_date', 'ASC');
		$this->db->order_by('created_at', 'ASC');
		return $this->db->get('medication_stock')->result();
	}

	public function get_total_batch_stock($medication_id){
		$this->ensure_pharmacy_v2_schema();
		$medication_id = (int)$medication_id;
		if ($medication_id <= 0) return 0;

		$this->db->select('SUM(quantity) AS total', false);
		$this->db->where(array('medication_id' => $medication_id, 'InActive' => 0));
		$this->db->where('quantity >', 0);
		$this->db->group_start();
		$this->db->where('expiry_date IS NULL', null, false);
		$this->db->or_where('expiry_date >=', date('Y-m-d'));
		$this->db->group_end();
		$r = $this->db->get('medication_stock')->row();
		return ($r && isset($r->total)) ? (float)$r->total : 0;
	}

	private function has_batch_stock($medication_id){
		if (!$this->table_exists('medication_stock')) return false;
		$this->db->where(array('medication_id' => (int)$medication_id, 'InActive' => 0));
		$this->db->where('quantity >', 0);
		return ($this->db->count_all_results('medication_stock') > 0);
	}

	public function deduct_batch_stock_fefo($medication_id, $qty, $user_id = null, $ref_type = 'DISPENSE', $ref_id = 0){
		$this->ensure_pharmacy_v2_schema();
		$medication_id = (int)$medication_id;
		$qty = (float)$qty;
		if ($medication_id <= 0 || $qty <= 0) return false;

		$today = date('Y-m-d');
		$qB = $this->db->query(
			"SELECT stock_id, batch_number, expiry_date, quantity\n			FROM medication_stock\n			WHERE medication_id = ?\n			AND InActive = 0\n			AND quantity > 0\n			AND (expiry_date IS NULL OR expiry_date >= ?)\n			ORDER BY expiry_date IS NULL, expiry_date ASC, stock_id ASC\n			FOR UPDATE",
			array($medication_id, $today)
		);
		$batches = $qB ? $qB->result() : array();

		$totalAvailable = 0;
		foreach ($batches as $b) {
			$totalAvailable += (float)$b->quantity;
		}
		if ($totalAvailable + 0.0001 < $qty) {
			return false;
		}

		$remaining = $qty;
		$deducted = array();

		foreach ($batches as $batch) {
			if ($remaining <= 0) break;
			$available = (float)$batch->quantity;
			$take = min($available, $remaining);

			$this->db->query(
				"UPDATE medication_stock\n				SET quantity = quantity - ?\n				WHERE stock_id = ?\n				AND InActive = 0\n				AND quantity >= ?",
				array($take, (int)$batch->stock_id, $take)
			);
			if ($this->db->affected_rows() === 0) {
				return false;
			}

			$remaining -= $take;
			$deducted[] = array(
				'batch_number' => $batch->batch_number,
				'qty_taken' => $take,
				'expiry_date' => $batch->expiry_date
			);
		}

		if ($remaining > 0.0001) {
			return false;
		}

		$this->sync_master_stock($medication_id);

		$batchInfo = array();
		foreach ($deducted as $d) {
			$batchInfo[] = $d['batch_number'] . ':' . $d['qty_taken'];
		}
		$this->log_stock_adjustment($medication_id, 'BATCH_DISPENSE', -$qty,
			0, 0, 'FEFO dispense from batches: ' . implode(', ', $batchInfo),
			$ref_type, $ref_id, $user_id);

		return true;
	}

	private function sync_master_stock($medication_id){
		$medication_id = (int)$medication_id;
		if ($medication_id <= 0) return;

		$this->db->where(array('medication_id' => $medication_id, 'InActive' => 0));
		$batchCount = $this->db->count_all_results('medication_stock');

		if ($batchCount > 0) {
			$total = $this->get_total_batch_stock($medication_id);
			$this->db->where('drug_id', $medication_id);
			$this->db->update('medicine_drug_name', array('nStock' => $total));
		}
	}

	public function get_expiring_batches($days = 30, $limit = 100){
		$this->ensure_pharmacy_v2_schema();
		$cutoff = date('Y-m-d', strtotime('+' . (int)$days . ' days'));

		$this->db->select('s.*, d.drug_name, d.generic_name, d.dosage_form, d.strength');
		$this->db->from('medication_stock s');
		$this->db->join('medicine_drug_name d', 'd.drug_id = s.medication_id', 'left');
		$this->db->where(array('s.InActive' => 0, 'd.InActive' => 0));
		$this->db->where('s.quantity >', 0);
		$this->db->where('s.expiry_date IS NOT NULL', null, false);
		$this->db->where('s.expiry_date <=', $cutoff);
		$this->db->where('s.expiry_date >=', date('Y-m-d'));
		$this->db->order_by('s.expiry_date', 'ASC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	public function get_expired_batches($limit = 100){
		$this->ensure_pharmacy_v2_schema();

		$this->db->select('s.*, d.drug_name, d.generic_name');
		$this->db->from('medication_stock s');
		$this->db->join('medicine_drug_name d', 'd.drug_id = s.medication_id', 'left');
		$this->db->where(array('s.InActive' => 0));
		$this->db->where('s.quantity >', 0);
		$this->db->where('s.expiry_date IS NOT NULL', null, false);
		$this->db->where('s.expiry_date <', date('Y-m-d'));
		$this->db->order_by('s.expiry_date', 'ASC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	public function count_expiring_soon($days = 30){
		$this->ensure_pharmacy_v2_schema();
		if (!$this->table_exists('medication_stock')) return 0;
		$cutoff = date('Y-m-d', strtotime('+' . (int)$days . ' days'));

		$this->db->where(array('InActive' => 0));
		$this->db->where('quantity >', 0);
		$this->db->where('expiry_date IS NOT NULL', null, false);
		$this->db->where('expiry_date <=', $cutoff);
		$this->db->where('expiry_date >=', date('Y-m-d'));
		return $this->db->count_all_results('medication_stock');
	}

	public function count_expired_batches(){
		$this->ensure_pharmacy_v2_schema();
		if (!$this->table_exists('medication_stock')) return 0;

		$this->db->where(array('InActive' => 0));
		$this->db->where('quantity >', 0);
		$this->db->where('expiry_date IS NOT NULL', null, false);
		$this->db->where('expiry_date <', date('Y-m-d'));
		return $this->db->count_all_results('medication_stock');
	}

	public function remove_expired_batch($stock_id, $user_id, $reason = 'Expired'){
		$this->ensure_pharmacy_v2_schema();
		$stock_id = (int)$stock_id;
		if ($stock_id <= 0) return false;

		$batch = $this->db->get_where('medication_stock', array('stock_id' => $stock_id))->row();
		if (!$batch) return false;

		$qty = (float)$batch->quantity;
		$med_id = (int)$batch->medication_id;

		$this->db->where('stock_id', $stock_id);
		$this->db->update('medication_stock', array('quantity' => 0, 'InActive' => 1));

		$this->sync_master_stock($med_id);

		$this->log_stock_adjustment($med_id, 'EXPIRED_REMOVAL', -$qty,
			$qty, 0, $reason . ' (Batch: ' . $batch->batch_number . ')',
			'EXPIRED', $stock_id, $user_id);
		$this->_run_pharmacy_expiry_removal_diagnostics($stock_id, $med_id, $qty, $batch->batch_number, $reason, $user_id);

		return true;
	}

	/* â”€â”€ Dispensing Status Management â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function update_dispensing_status($iop_med_id){
		$this->ensure_pharmacy_v2_schema();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0) return;
		if (!$this->column_exists('iop_medication', 'dispensing_status')) return;

		$this->db->trans_start();

		$sql = "SELECT * FROM iop_medication WHERE iop_med_id = ? AND InActive = 0 FOR UPDATE";
		$med = $this->db->query($sql, array($iop_med_id))->row();

		if (!$med) {
			$this->db->trans_complete();
			return;
		}

		$currentStatus = isset($med->dispensing_status) ? strtoupper(trim((string)$med->dispensing_status)) : 'PENDING';
		if ($currentStatus === 'UNAVAILABLE' || $currentStatus === 'PAID') {
			$this->db->trans_complete();
			return;
		}

		$totalQty = (float)$med->total_qty;
		$dispensedQty = $this->get_total_dispensed($iop_med_id);

		$newStatus = 'PENDING';
		if ($totalQty > 0 && $dispensedQty >= $totalQty) {
			$newStatus = 'DISPENSED';
		} elseif ($dispensedQty > 0) {
			$newStatus = 'PARTIAL';
		} else {
			if ($this->table_exists('iop_medication_administration')) {
				$this->db->where(array('iop_med_id' => $iop_med_id, 'InActive' => 0, 'status' => 'RESERVED'));
				$reserved = $this->db->count_all_results('iop_medication_administration');
				if ($reserved > 0) $newStatus = 'RESERVED';
			}
		}

		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', array('dispensing_status' => $newStatus));

		$this->db->trans_complete();
	}

	public function mark_unavailable($iop_med_id, $user_id, $notes = ''){
		$this->ensure_pharmacy_v2_schema();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0) return array('ok' => false, 'error' => 'Invalid prescription ID.');

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before marking unavailable.');
			}
		}

		$dispensedQty = $this->get_total_dispensed($iop_med_id);
		if ($dispensedQty > 0) {
			return array('ok' => false, 'error' => 'Cannot mark as unavailable â€” medication already partially/fully dispensed.');
		}

		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('iop_medication', array('dispensing_status' => 'UNAVAILABLE'));
		}

		$iop_id = isset($med->iop_id) ? (string)$med->iop_id : '';
		if ($this->table_exists('iop_medication_administration')) {
			$this->load->model('app/nurse_enhancement_model');
			$this->nurse_enhancement_model->save_medication_administration(
				$iop_med_id, $iop_id, 'UNAVAILABLE', 0,
				$notes !== '' ? $notes : 'Medication unavailable',
				date('Y-m-d H:i:s'), $user_id
			);
		}

		return array('ok' => true, 'error' => '');
	}

	public function mark_available($iop_med_id, $user_id){
		$this->ensure_pharmacy_v2_schema();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0) return array('ok' => false, 'error' => 'Invalid prescription ID.');
		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if ($med && $this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before restoring availability.');
			}
		}

		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('iop_medication', array('dispensing_status' => 'PENDING'));
		}

		return array('ok' => true, 'error' => '');
	}

	/* â”€â”€ Billing Integration â€” Only billable items â”€â”€â”€â”€â”€â”€ */

	public function get_dispensed_for_billing($iop_id){
		$this->ensure_pharmacy_v2_schema();
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return array();

		$this->db->select("A.iop_med_id, A.medicine_id, A.medicine_text, A.total_qty, A.dispensing_status, B.drug_name, B.nPrice");
		$this->db->from('iop_medication A');
		$this->db->join('medicine_drug_name B', 'B.drug_id = A.medicine_id', 'left');
		$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where("A.dispensing_status != 'UNAVAILABLE'", null, false);
		}
		return $this->db->get()->result();
	}

	/* â”€â”€ Pharmacy Alerts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function get_pharmacy_alerts(){
		return array(
			'low_stock' => $this->count_low_stock(),
			'expiring_soon' => $this->count_expiring_soon(90),
			'expired' => $this->count_expired_batches(),
			'pending_prescriptions' => $this->count_pending_prescriptions()
		);
	}

	/* ================================================================== */
	/*  GHS PHARMACY â†’ CASHIER WORKFLOW                                   */
	/* ================================================================== */

	/**
	 * Create pharmacy_billing_queue, pharmacy_audit_log tables and add
	 * payment_status column to iop_medication.
	 */
	public function ensure_pharmacy_ghs_schema(){
		static $ghsDone = false;
		if ($ghsDone) return;
		$ghsDone = true;

		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) { $this->db->db_debug = false; }

		if ($this->table_exists('iop_medication')) {
			if (!$this->column_exists('iop_medication', 'payment_status')) {
				$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `payment_status` varchar(20) NOT NULL DEFAULT 'PENDING'");
				$this->db->query("ALTER TABLE `iop_medication` ADD INDEX `idx_pay_status` (`payment_status`)");
			}
		}

		$this->load->model('app/pharmacy_billing_model', 'pharmacy_bill_model');
		$this->pharmacy_bill_model->ensure_billing_schema();
		if ($this->table_exists('pharmacy_billing_queue')) {
			foreach (array(
				'pricing_source' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `pricing_source` VARCHAR(30) DEFAULT NULL",
				'pricing_source_id' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `pricing_source_id` VARCHAR(64) DEFAULT NULL",
				'resolved_drug_id' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `resolved_drug_id` INT(11) DEFAULT NULL",
				'resolved_stock_id' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `resolved_stock_id` INT(11) DEFAULT NULL",
				'substitution_flag' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `substitution_flag` TINYINT(1) NOT NULL DEFAULT 0",
			) as $_pbq_col => $_pbq_sql) {
				if (!$this->column_exists('pharmacy_billing_queue', $_pbq_col)) {
					$this->db->query($_pbq_sql);
				}
			}
		}

		$has_generic_audit = $this->table_exists('pharmacy_audit_log')
			&& $this->column_exists('pharmacy_audit_log', 'audit_table')
			&& $this->column_exists('pharmacy_audit_log', 'record_id');
		if (!$has_generic_audit) {
			$this->db->query("CREATE TABLE IF NOT EXISTS `pharmacy_audit_log` (
				`log_id` int(11) NOT NULL AUTO_INCREMENT,
				`iop_med_id` int(11) DEFAULT NULL,
				`iop_id` varchar(11) DEFAULT NULL,
				`patient_no` varchar(20) DEFAULT NULL,
				`event_type` varchar(50) NOT NULL,
				`old_status` varchar(30) DEFAULT NULL,
				`new_status` varchar(30) DEFAULT NULL,
				`drug_name` varchar(255) DEFAULT NULL,
				`qty_dispensed` decimal(10,3) DEFAULT NULL,
				`batch_no` varchar(100) DEFAULT NULL,
				`expiry_date` date DEFAULT NULL,
				`notes` varchar(255) DEFAULT NULL,
				`performed_by` varchar(25) DEFAULT NULL,
				`performed_at` datetime NOT NULL,
				PRIMARY KEY (`log_id`),
				KEY `idx_pal_iop_med` (`iop_med_id`),
				KEY `idx_pal_event` (`event_type`),
				KEY `idx_pal_at` (`performed_at`)
			) ENGINE=MyISAM DEFAULT CHARSET=latin1");
			/* Migrate existing installations â€” add columns if absent */
			foreach (array(
				"ALTER TABLE `pharmacy_audit_log` ADD COLUMN `drug_name` varchar(255) DEFAULT NULL AFTER `new_status`",
				"ALTER TABLE `pharmacy_audit_log` ADD COLUMN `qty_dispensed` decimal(10,3) DEFAULT NULL AFTER `drug_name`",
				"ALTER TABLE `pharmacy_audit_log` ADD COLUMN `batch_no` varchar(100) DEFAULT NULL AFTER `qty_dispensed`",
				"ALTER TABLE `pharmacy_audit_log` ADD COLUMN `expiry_date` date DEFAULT NULL AFTER `batch_no`",
			) as $_pal_sql) {
				if ($this->table_exists('pharmacy_audit_log')) {
					$_col = '';
					preg_match('/ADD COLUMN `(\w+)`/', $_pal_sql, $_m);
					if (!empty($_m[1]) && !$this->column_exists('pharmacy_audit_log', $_m[1])) {
						$this->db->query($_pal_sql);
					}
				}
			}
		}

		$this->db->query("CREATE TABLE IF NOT EXISTS `patient_admissions` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`patient_no` varchar(20) NOT NULL,
			`iop_id` varchar(11) DEFAULT NULL,
			`ward` varchar(100) DEFAULT NULL,
			`bed` varchar(50) DEFAULT NULL,
			`admitted_by` varchar(25) DEFAULT NULL,
			`admission_date` datetime NOT NULL,
			`discharge_date` datetime DEFAULT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'Admitted',
			`notes` text DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`updated_at` datetime DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_pa_patient_no` (`patient_no`),
			KEY `idx_pa_status` (`status`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		if ($old !== null) { $this->db->db_debug = $old; }
	}

	/**
	 * Auto-create / update pharmacy billing queue entry when prescription is saved.
	 */
	public function create_or_update_pharmacy_bill($iop_med_id, $user_id = null){
		$this->ensure_pharmacy_ghs_schema();
		$this->load->helper('quantity_semantics');
		$pk_col = $this->_pbq_pk_col();
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0) return false;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return false;
		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return false;
			}
		}

		$drug_id = isset($med->medicine_id) ? (int)$med->medicine_id : 0;
		$qty = qs_pick_prescribed_qty($med, 1.0);
		$effective = $this->_effective_prescription_values($med);
		if (isset($effective['billable_qty']) && (float)$effective['billable_qty'] > 0) {
			$qty = (float)$effective['billable_qty'];
		}
		$drug_name = isset($med->medicine_text) ? trim((string)$med->medicine_text) : '';
		$iop_id = '';
		if (isset($med->iop_id)) {
			$iop_id = (string)$med->iop_id;
		} elseif (isset($med->IO_ID)) {
			$iop_id = (string)$med->IO_ID;
		}
		$iop_id = trim((string)$iop_id);

		$patient_no = '';
		if ($iop_id !== '') {
			$pd = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0))->row();
			$patient_no = ($pd && isset($pd->patient_no)) ? (string)$pd->patient_no : '';
		}

		$unit_price = 0;
		$payer_type = 'CASH';
		$pricing = array();
		if ($drug_id > 0) {
			$this->load->model('app/Price_engine_model', 'price_engine');
			if ($this->price_engine && method_exists($this->price_engine, 'resolve')) {
				$res = $this->price_engine->resolve(array(
					'item_type'  => 'PHARMACY',
					'item_id'    => $drug_id,
					'quantity'   => $qty,
					'patient_no' => $patient_no,
					'require_positive_price' => true,
					'context' => array(
						'source_module' => 'PHARMACY',
						'source_ref' => 'iop_med_id:' . (string)$iop_med_id,
					),
				));
				if (is_array($res) && !empty($res['ok'])) {
					$unit_price = isset($res['unit_price']) ? (float)$res['unit_price'] : 0;
					$payer_type = isset($res['payer_type']) ? strtoupper(trim((string)$res['payer_type'])) : 'CASH';
					$pricing = $res;
				}
			}
			if ($unit_price <= 0.009) {
				$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'PRICE_RESOLUTION_FAILED', null, (string)$drug_id, 'Substitute medication pricing resolution failed. Drug ID: ' . (string)$drug_id . '. Workflow blocked to prevent zero-value billing.', $user_id, $drug_name);
				log_message('error', 'PHARMACY_PRICE_RESOLUTION_FAILED iop_med_id=' . $iop_med_id . ' drug_id=' . $drug_id . ' patient_no=' . $patient_no);
				return false;
			}
		} else {
			$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'PRICE_RESOLUTION_FAILED', null, '0', 'Medication has no canonical drug ID. Workflow blocked to prevent zero-value billing.', $user_id, $drug_name);
			return false;
		}

		$total = $qty * $unit_price;

		$now = date('Y-m-d H:i:s');
		$existing = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id))->row();
		$has_total = $this->column_exists('pharmacy_billing_queue', 'total');
		$has_total_amount = $this->column_exists('pharmacy_billing_queue', 'total_amount');

		if ($existing) {
			$payStatus = strtoupper(trim((string)$existing->payment_status));
			if ($payStatus === 'PENDING') {
				$existing_pk = isset($existing->{$pk_col}) ? (int)$existing->{$pk_col} : 0;
				if ($existing_pk > 0) {
					$upd = array(
					'iop_id'     => $iop_id,
					'patient_no' => $patient_no,
					'drug_name'  => $drug_name,
					'quantity'   => $qty,
					'unit_price' => $unit_price,
					'updated_at' => $now,
					'InActive'   => 0,
					);
					if ($this->column_exists('pharmacy_billing_queue', 'quantity_semantics_version')) {
						$sem = qs_flag_enabled('ENABLE_DECIMAL_PRESCRIBED_QTY', false) ? qs_decimal_semantics_version() : qs_default_semantics_version();
						$upd['quantity_semantics_version'] = (int)$sem;
					}
					if ($this->column_exists('pharmacy_billing_queue', 'drug_id')) {
						$upd['drug_id'] = $drug_id;
					}
					if ($this->column_exists('pharmacy_billing_queue', 'payer_type')) {
						$upd['payer_type'] = $payer_type;
					}
					foreach (array(
						'pricing_source' => isset($pricing['price_source']) ? (string)$pricing['price_source'] : null,
						'pricing_source_id' => isset($pricing['pricing_source_id']) ? (string)$pricing['pricing_source_id'] : (isset($pricing['source_id']) ? (string)$pricing['source_id'] : null),
						'resolved_drug_id' => $drug_id > 0 ? $drug_id : null,
						'resolved_stock_id' => isset($pricing['resolved_stock_id']) ? $pricing['resolved_stock_id'] : null,
						'substitution_flag' => (!empty($med->original_medicine_id) || !empty($med->substituted_medicine_id)) ? 1 : 0,
					) as $_col => $_val) {
						if ($this->column_exists('pharmacy_billing_queue', $_col)) {
							$upd[$_col] = $_val;
						}
					}
					if ($has_total) $upd['total'] = $total;
					if ($has_total_amount) $upd['total_amount'] = $total;
					$this->db->where($pk_col, $existing_pk);
					$this->db->update('pharmacy_billing_queue', $upd);
				}
			}
		} else {
			$data = array(
				'iop_med_id'     => $iop_med_id,
				'iop_id'         => $iop_id,
				'patient_no'     => $patient_no,
				'drug_id'        => $drug_id,
				'drug_name'      => $drug_name,
				'quantity'       => $qty,
				'unit_price'     => $unit_price,
				'payment_status' => 'PENDING',
				'dispense_status'=> 'WAITING',
				'created_at'     => $now,
				'updated_at'     => $now,
				'InActive'       => 0,
			);
			if ($this->column_exists('pharmacy_billing_queue', 'quantity_semantics_version')) {
				$sem = qs_flag_enabled('ENABLE_DECIMAL_PRESCRIBED_QTY', false) ? qs_decimal_semantics_version() : qs_default_semantics_version();
				$data['quantity_semantics_version'] = (int)$sem;
			}
			if ($this->column_exists('pharmacy_billing_queue', 'payer_type')) {
				$data['payer_type'] = $payer_type;
			}
			foreach (array(
				'pricing_source' => isset($pricing['price_source']) ? (string)$pricing['price_source'] : null,
				'pricing_source_id' => isset($pricing['pricing_source_id']) ? (string)$pricing['pricing_source_id'] : (isset($pricing['source_id']) ? (string)$pricing['source_id'] : null),
				'resolved_drug_id' => $drug_id > 0 ? $drug_id : null,
				'resolved_stock_id' => isset($pricing['resolved_stock_id']) ? $pricing['resolved_stock_id'] : null,
				'substitution_flag' => (!empty($med->original_medicine_id) || !empty($med->substituted_medicine_id)) ? 1 : 0,
			) as $_col => $_val) {
				if ($this->column_exists('pharmacy_billing_queue', $_col)) {
					$data[$_col] = $_val;
				}
			}
			if ($has_total) $data['total'] = $total;
			if ($has_total_amount) $data['total_amount'] = $total;
			if ($this->column_exists('pharmacy_billing_queue', 'billed_by')) {
				$data['billed_by'] = $user_id !== null ? (string)$user_id : null;
			}
			$this->db->insert('pharmacy_billing_queue', $data);
			$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'BILL_CREATED', null, 'PENDING', 'Pharmacy bill queued', $user_id);
		}
		return true;
	}

	/**
	 * Ensure all prescriptions for a patient visit have billing queue entries.
	 * Creates missing entries and syncs extended_status from iop_medication.
	 * This is the SINGLE SOURCE OF TRUTH sync method.
	 */
	public function ensure_billing_queue_for_visit($iop_id, $user_id = null){
		$this->ensure_pharmacy_ghs_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		$pk_col = $this->_pbq_pk_col();
		
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return 0;
		
		// Get all prescriptions for this visit
		$meds = $this->db->get_where('iop_medication', array('iop_id' => $iop_id, 'InActive' => 0))->result();
		$created = 0;
		
		foreach ($meds as $med) {
			$iop_med_id = (int)$med->iop_med_id;
			if ($this->db->field_exists('prescription_status', 'iop_medication')) {
				$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
				if ($rxStatus !== 'VERIFIED') {
					continue;
				}
			}
			
			// Check if billing queue entry exists
			$existing = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id))->row();
			
			if (!$existing) {
				// Create billing queue entry
				$this->create_or_update_pharmacy_bill($iop_med_id, $user_id);
				$created++;
			} else {
				$payStatus = isset($existing->payment_status) ? strtoupper(trim((string)$existing->payment_status)) : 'PENDING';
				if ($payStatus === 'PENDING') {
					$this->create_or_update_pharmacy_bill($iop_med_id, $user_id);
				}

				// Sync extended_status from iop_medication to billing queue if needed
				$medExtStatus = isset($med->extended_status) ? strtoupper(trim((string)$med->extended_status)) : '';
				$billExtStatus = isset($existing->extended_status) ? strtoupper(trim((string)$existing->extended_status)) : '';
				
				// If medication has extended status but billing queue doesn't, sync it
				if ($medExtStatus !== '' && $billExtStatus === '') {
					$updateData = array('extended_status' => $medExtStatus, 'updated_at' => date('Y-m-d H:i:s'));
					
					// Also update payment_status if it's a waiver/exception
					if (in_array($medExtStatus, array('WAIVED', 'WAIVER_APPROVED'))) {
						$updateData['payment_status'] = 'WAIVED';
					}
					
					$existing_pk = isset($existing->{$pk_col}) ? (int)$existing->{$pk_col} : 0;
					if ($existing_pk > 0) {
						$this->db->where($pk_col, $existing_pk);
						$this->db->update('pharmacy_billing_queue', $updateData);
					}
				}
			}
		}
		
		return $created;
	}

	public function finalize_visit_for_billing($iop_id, $patient_no, $user_id){
		$this->ensure_pharmacy_ghs_schema();
		$this->ensure_pharmacy_finalization_schema();
		$this->load->model('app/pharmacy_architecture_model');
		if (isset($this->pharmacy_architecture_model) && method_exists($this->pharmacy_architecture_model, 'ensure_prescription_locking_schema')) {
			$this->pharmacy_architecture_model->ensure_prescription_locking_schema();
		}

		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		if ($iop_id === '') {
			return array('ok' => false, 'error' => 'Invalid visit ID.', 'created' => 0, 'skipped' => 0);
		}

		$this->db->trans_begin();
		$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
		$meds = $this->db->get('iop_medication')->result();
		$created = 0;
		$skipped = 0;
		$hasRxStatusCol = $this->column_exists('iop_medication', 'prescription_status');
		$hasDispStatusCol = $this->column_exists('iop_medication', 'dispensing_status');
		$hasFinalizedAtCol = $this->column_exists('iop_medication', 'billing_finalized_at');
		$hasFinalizedByCol = $this->column_exists('iop_medication', 'billing_finalized_by');
		$hasPbq = $this->table_exists('pharmacy_billing_queue');
		$pbqMap = array();
		if ($hasPbq && is_array($meds) && count($meds) > 0) {
			$ids = array();
			foreach ($meds as $_m) {
				$mid = isset($_m->iop_med_id) ? (int)$_m->iop_med_id : 0;
				if ($mid > 0) $ids[] = $mid;
			}
			if (count($ids) > 0) {
				$rows = $this->db
					->where_in('iop_med_id', $ids)
					->where('InActive', 0)
					->get('pharmacy_billing_queue')
					->result();
				foreach ($rows as $r) {
					if ($r && isset($r->iop_med_id)) {
						$pbqMap[(int)$r->iop_med_id] = $r;
					}
				}
			}
		}

		foreach ($meds as $med) {
			$iop_med_id = (int)$med->iop_med_id;
			$rxStatus = $hasRxStatusCol ? strtoupper(trim((string)$med->prescription_status)) : 'VERIFIED';
			$dispStatus = $hasDispStatusCol ? strtoupper(trim((string)$med->dispensing_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED' || in_array($dispStatus, array('UNAVAILABLE','EXTERNAL','CANCELLED'), true)) {
				$skipped++;
				continue;
			}

			$existing = ($hasPbq && isset($pbqMap[$iop_med_id])) ? $pbqMap[$iop_med_id] : null;
			$didUpdatePbq = false;
			if (!$existing) {
				if ($this->create_or_update_pharmacy_bill($iop_med_id, $user_id)) {
					$created++;
					$didUpdatePbq = true;
				} else {
					$this->db->trans_rollback();
					return array('ok' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: ' . (isset($med->medicine_id) ? (string)(int)$med->medicine_id : '0') . '. Workflow blocked to prevent zero-value billing.', 'created' => $created, 'skipped' => $skipped);
				}
			} else {
				$payStatus = isset($existing->payment_status) ? strtoupper(trim((string)$existing->payment_status)) : 'PENDING';
				if ($payStatus === 'PENDING') {
					if (!$this->create_or_update_pharmacy_bill($iop_med_id, $user_id)) {
						$this->db->trans_rollback();
						return array('ok' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: ' . (isset($med->medicine_id) ? (string)(int)$med->medicine_id : '0') . '. Workflow blocked to prevent zero-value billing.', 'created' => $created, 'skipped' => $skipped);
					}
					$didUpdatePbq = true;
				}
			}

			$queue = $existing;
			if ($hasPbq && $didUpdatePbq) {
				$queue = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
				if ($queue) {
					$pbqMap[$iop_med_id] = $queue;
				}
			}
			if ($queue) {
				try {
					$this->load->model('app/unified_billing_model');
					$totalCol = $this->_pbq_total_col();
					$quantity = isset($queue->quantity) ? (float)$queue->quantity : (isset($med->total_qty) ? (float)$med->total_qty : 1.0);
					$unitPrice = isset($queue->unit_price) ? (float)$queue->unit_price : 0.0;
					$payer = isset($queue->payer_type) ? strtoupper(trim((string)$queue->payer_type)) : 'CASH';
					if ($quantity <= 0) $quantity = 1.0;
					if ($unitPrice <= 0 && isset($queue->{$totalCol}) && $quantity > 0) {
						$unitPrice = (float)$queue->{$totalCol} / $quantity;
					}
					if ($unitPrice <= 0.009) {
						$this->db->trans_rollback();
						return array('ok' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: ' . (isset($queue->drug_id) ? (string)(int)$queue->drug_id : '0') . '. Workflow blocked to prevent zero-value billing.', 'created' => $created, 'skipped' => $skipped);
					}
					$queuePayload = array(
						'iop_id'        => $iop_id,
						'patient_no'    => $patient_no,
						'item_type'     => 'PHARMACY',
						'item_id'       => (string)$iop_med_id,
						'item_name'     => isset($queue->drug_name) && trim((string)$queue->drug_name) !== '' ? (string)$queue->drug_name : 'Medication',
						'unit_price'    => $unitPrice,
						'quantity'      => $quantity,
						'payer_type'    => $payer !== '' ? $payer : 'CASH',
						'source_module' => 'PHARMACY',
						'source_ref'    => 'iop_id:' . $iop_id . ':iop_medication:' . (string)$iop_med_id,
						'requested_by'  => (string)$user_id,
						'notes'         => 'Finalized for pharmacy billing',
					);
					foreach (array('pricing_source','pricing_source_id','resolved_drug_id','resolved_stock_id','substitution_flag') as $_prov_col) {
						if (isset($queue->{$_prov_col})) {
							$queuePayload[$_prov_col] = $queue->{$_prov_col};
						}
					}
					$res = $this->unified_billing_model->add_to_billing_queue($queuePayload);
					if (!$res['success'] && isset($res['error']) && $res['error'] !== 'Item already in billing queue') {
						log_message('error', 'PHARMACY_FINALIZE_UNIFIED_QUEUE_FAILED iop_med_id=' . $iop_med_id . ' err=' . (string)$res['error']);
						$this->db->trans_rollback();
						return array('ok' => false, 'error' => (string)$res['error'], 'created' => $created, 'skipped' => $skipped);
					}
				} catch (Exception $e) {
					log_message('error', 'PHARMACY_FINALIZE_UNIFIED_QUEUE_EXCEPTION iop_med_id=' . $iop_med_id . ' err=' . $e->getMessage());
					$this->db->trans_rollback();
					return array('ok' => false, 'error' => $e->getMessage(), 'created' => $created, 'skipped' => $skipped);
				}
			}

			if ($hasFinalizedAtCol) {
				$upd = array('billing_finalized_at' => date('Y-m-d H:i:s'));
				if ($hasFinalizedByCol) {
					$upd['billing_finalized_by'] = (string)$user_id;
				}
				$this->db->where('iop_med_id', $iop_med_id);
				$this->db->where('billing_finalized_at IS NULL', null, false);
				$this->db->update('iop_medication', $upd);
			}
			$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'FINALIZED_FOR_BILLING', $rxStatus, 'FINALIZED', 'Prescription finalized for cashier billing', $user_id);
		}

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Finalization failed and was rolled back.', 'created' => 0, 'skipped' => $skipped);
		}
		$this->db->trans_commit();
		return array('ok' => true, 'error' => '', 'created' => $created, 'skipped' => $skipped);
	}

	public function substitute_medication($iop_med_id, $substitute_drug_id, $reason, $user_id, $overrides = array()){
		$this->ensure_pharmacy_finalization_schema();
		$iop_med_id = (int)$iop_med_id;
		$substitute_drug_id = (int)$substitute_drug_id;
		$reason = trim((string)$reason);
		if (!is_array($overrides)) { $overrides = array(); }
		if ($iop_med_id <= 0 || $substitute_drug_id <= 0 || $reason === '') {
			return array('ok' => false, 'error' => 'Prescription, substitute medication, and reason are required.');
		}

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'billing_finalized_at')) {
			$finalizedAt = isset($med->billing_finalized_at) ? trim((string)$med->billing_finalized_at) : '';
			if ($finalizedAt !== '') {
				return array('ok' => false, 'error' => 'Cannot substitute after billing finalization.');
			}
		}
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if (!in_array($rxStatus, array('PENDING','VERIFIED'), true)) {
				return array('ok' => false, 'error' => 'Cannot substitute prescription in ' . $rxStatus . ' status.');
			}
		}
		if ($this->get_total_dispensed($iop_med_id) > 0) {
			return array('ok' => false, 'error' => 'Cannot substitute after dispensing has started.');
		}

		if ($this->table_exists('pharmacy_billing_queue')) {
			$bill = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
			if ($bill) {
				$payStatus = isset($bill->payment_status) ? strtoupper(trim((string)$bill->payment_status)) : 'PENDING';
				if ($payStatus !== '' && $payStatus !== 'PENDING') {
					return array('ok' => false, 'error' => 'Cannot substitute after billing/payment activity is recorded. Please reverse/void billing and try again.');
				}
			}
		}
		if ($this->table_exists('billing_transactions')) {
			$this->db->select('payment_status, invoice_no, paid_amount');
			$this->db->where(array(
				'InActive' => 0,
				'department' => 'PHARMACY',
				'item_ref' => 'iop_med_id:' . (int)$iop_med_id,
			));
			$this->db->limit(1);
			$txn = $this->db->get('billing_transactions')->row();
			if ($txn) {
				$invNo = isset($txn->invoice_no) ? trim((string)$txn->invoice_no) : '';
				$paidAmt = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
				$pay = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
				if ($invNo !== '' || $paidAmt > 0.009 || ($pay !== '' && $pay !== 'PENDING')) {
					return array('ok' => false, 'error' => 'Cannot substitute after billing/payment activity is recorded. Please reverse/void billing and try again.');
				}
			}
		}

		$rxUpdates = array();
		if (array_key_exists('total_qty', $overrides)) {
			$qty = (float)$overrides['total_qty'];
			if ($qty <= 0) {
				return array('ok' => false, 'error' => 'Quantity must be greater than zero.');
			}
			$rxUpdates['total_qty'] = $qty;
		}
		if (array_key_exists('days', $overrides)) {
			$days = (int)$overrides['days'];
			if ($days < 0) {
				return array('ok' => false, 'error' => 'Days cannot be negative.');
			}
			$rxUpdates['days'] = $days;
		}
		$txtCols = array('dosage','unit','frequency','freq_code','route','instruction','advice');
		foreach ($txtCols as $c) {
			if (array_key_exists($c, $overrides)) {
				$rxUpdates[$c] = trim((string)$overrides[$c]);
			}
		}

		$original_id = isset($med->medicine_id) ? (int)$med->medicine_id : 0;
		if ($original_id <= 0 || $original_id === $substitute_drug_id) {
			return array('ok' => false, 'error' => 'Invalid substitute medication.');
		}

		$this->load->model('app/pharmacy_architecture_model');
		if ($this->pharmacy_architecture_model->is_drug_controlled($original_id)) {
			$schedule_info = $this->pharmacy_architecture_model->get_drug_schedule_info($original_id);
			if ($schedule_info && in_array($schedule_info->schedule_code, array('SCHEDULE_I', 'SCHEDULE_II'), true)) {
				$this->log_pharmacy_audit($iop_med_id, isset($med->iop_id) ? (string)$med->iop_id : '', '', 'SUBSTITUTION_DENIED', (string)$original_id, (string)$substitute_drug_id, 'Substitution not allowed (controlled drug): ' . $reason, $user_id);
				return array('ok' => false, 'error' => 'Substitution is not allowed for this controlled medication.');
			}
		}
		if ($this->pharmacy_architecture_model->is_drug_controlled($substitute_drug_id)) {
			$schedule_info = $this->pharmacy_architecture_model->get_drug_schedule_info($substitute_drug_id);
			if ($schedule_info && in_array($schedule_info->schedule_code, array('SCHEDULE_I', 'SCHEDULE_II'), true)) {
				$this->log_pharmacy_audit($iop_med_id, isset($med->iop_id) ? (string)$med->iop_id : '', '', 'SUBSTITUTION_DENIED', (string)$original_id, (string)$substitute_drug_id, 'Substitute is controlled and not allowed: ' . $reason, $user_id);
				return array('ok' => false, 'error' => 'Selected substitute is a controlled medication and cannot be substituted.');
			}
		}
		$subDrug = $this->get_drug_stock($substitute_drug_id);
		if (!$subDrug) {
			return array('ok' => false, 'error' => 'Substitute medication not found.');
		}
		if (isset($subDrug->nStock) && (float)$subDrug->nStock <= 0) {
			return array('ok' => false, 'error' => 'Selected substitute has no stock available.');
		}

		$sub_name = $this->_get_drug_display_name($substitute_drug_id);
		if ($sub_name === '') {
			return array('ok' => false, 'error' => 'Substitute medication not found.');
		}
		$orig_text = isset($med->medicine_text) ? (string)$med->medicine_text : $this->_get_drug_display_name($original_id);

		$this->db->trans_begin();
		$update = array(
			'medicine_id' => $substitute_drug_id,
			'medicine_text' => $sub_name,
		);
		foreach ($rxUpdates as $k => $v) {
			if ($this->column_exists('iop_medication', $k)) {
				$update[$k] = $v;
			}
		}
		if ($this->column_exists('iop_medication', 'original_medicine_id')) $update['original_medicine_id'] = $original_id;
		if ($this->column_exists('iop_medication', 'original_medicine_text')) $update['original_medicine_text'] = $orig_text;
		if ($this->column_exists('iop_medication', 'substituted_medicine_id')) $update['substituted_medicine_id'] = $substitute_drug_id;
		if ($this->column_exists('iop_medication', 'substituted_medicine_text')) $update['substituted_medicine_text'] = $sub_name;
		if ($this->column_exists('iop_medication', 'substitution_reason')) $update['substitution_reason'] = $reason;
		if ($this->column_exists('iop_medication', 'substituted_by')) $update['substituted_by'] = (string)$user_id;
		if ($this->column_exists('iop_medication', 'substituted_at')) $update['substituted_at'] = date('Y-m-d H:i:s');
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', $update);
		if ($this->table_exists('pharmacy_billing_queue')) {
			if (!$this->create_or_update_pharmacy_bill($iop_med_id, $user_id)) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Substitute medication pricing resolution failed. Drug ID: ' . (string)$substitute_drug_id . '. Workflow blocked to prevent zero-value billing.');
			}
		}
		if ($this->table_exists('billing_transactions')) {
			$this->load->model('app/billing_transaction_model');
			if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_pending_pharmacy_transaction_from_rx')) {
				$sync = $this->billing_transaction_model->sync_pending_pharmacy_transaction_from_rx($iop_med_id, $user_id);
				if (!is_array($sync) || empty($sync['ok'])) {
					$this->db->trans_rollback();
					return array('ok' => false, 'error' => isset($sync['error']) ? (string)$sync['error'] : 'Billing sync failed.');
				}
			}
		}

		$this->log_pharmacy_audit($iop_med_id, isset($med->iop_id) ? (string)$med->iop_id : '', '', 'SUBSTITUTED', (string)$original_id, (string)$substitute_drug_id, $reason, $user_id, $sub_name);

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Substitution failed and was rolled back.');
		}
		$this->db->trans_commit();
		return array('ok' => true, 'error' => '', 'drug_name' => $sub_name);
	}

	/**
	 * Sync dispensing status based on actual dispense records.
	 * Updates iop_medication.dispensing_status based on iop_medication_administration.
	 */
	public function sync_dispensing_status($iop_med_id){
		$iop_med_id = (int)$iop_med_id;
		if ($iop_med_id <= 0) return false;
		
		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return false;
		
		$totalQty = (float)$med->total_qty;
		
		// Get actual dispensed quantity from administration records
		$disp = $this->get_med_dispense_map(array($iop_med_id));
		$dispensedQty = isset($disp[(string)$iop_med_id]) ? (float)$disp[(string)$iop_med_id]['dispensed_qty'] : 0;
		
		// Determine correct status
		$newStatus = 'PENDING';
		if ($dispensedQty >= $totalQty && $totalQty > 0) {
			$newStatus = 'DISPENSED';
		} elseif ($dispensedQty > 0) {
			$newStatus = 'PARTIAL';
		}
		
		// Update if different
		$currentStatus = isset($med->dispensing_status) ? strtoupper(trim((string)$med->dispensing_status)) : 'PENDING';
		if ($currentStatus !== $newStatus && $this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('iop_medication', array('dispensing_status' => $newStatus));
			return true;
		}
		
		return false;
	}

	/**
	 * GHS payment gate: check if pharmacy billing queue shows payment received or exception status.
	 * Uses pharmacy_billing_queue as SINGLE SOURCE OF TRUTH.
	 */
	public function check_ghs_payment_gate($iop_med_id){
		$this->ensure_pharmacy_ghs_schema();
		$iop_med_id = (int)$iop_med_id;

		if (!$this->table_exists('pharmacy_billing_queue')) {
			return array('paid' => false, 'reason' => 'Pharmacy billing queue not initialised.');
		}
		$bill = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$bill) {
			return array('paid' => false, 'reason' => 'No pharmacy bill found. Ask cashier to generate bill first.');
		}
		
		$payStatus = strtoupper(trim((string)$bill->payment_status));
		$extStatus = isset($bill->extended_status) ? strtoupper(trim((string)$bill->extended_status)) : '';
		
		// Payment cleared statuses
		if ($payStatus === 'PAID' || $payStatus === 'WAIVED' || $payStatus === 'CANCELLED') {
			return array('paid' => true, 'reason' => '');
		}
		
		// Exception statuses that bypass normal payment requirement
		$exceptionStatuses = array('EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','WAIVER_APPROVED','EMERGENCY');
		if (in_array($extStatus, $exceptionStatuses)) {
			return array('paid' => true, 'reason' => '');
		}
		
		return array('paid' => false, 'reason' => 'Payment required before dispensing. Direct patient to cashier.');
	}

	/**
	 * Cashier: retrieve pending/all pharmacy bills.
	 */
	public function get_pending_pharmacy_bills($filters = array()){
		$this->ensure_pharmacy_ghs_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return array();
		$pk_col = $this->_pbq_pk_col();
		$total_col = $this->_pbq_total_col();

		$status  = isset($filters['status'])    ? trim((string)$filters['status'])    : 'PENDING';
		$from    = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
		$to      = isset($filters['date_to'])   ? trim((string)$filters['date_to'])   : '';
		$search  = isset($filters['search'])    ? trim((string)$filters['search'])    : '';
		$limit   = isset($filters['limit'])     ? (int)$filters['limit']              : 200;
		if ($limit <= 0 || $limit > 500) $limit = 200;

		$this->db->select("Q.{$pk_col} AS id, Q.iop_med_id, Q.iop_id, Q.patient_no, Q.drug_name, Q.quantity, Q.unit_price, Q.{$total_col} AS total, Q.payment_status, Q.dispense_status, Q.paid_by, Q.paid_at, Q.created_at, CONCAT_WS(' ', T.cValue, P.firstname, P.lastname) AS patient_name", false);
		$this->db->from('pharmacy_billing_queue Q');
		$this->db->join('iop_medication M', 'M.iop_med_id = Q.iop_med_id', 'left');
		$this->db->join('patient_personal_info P', 'P.patient_no = Q.patient_no', 'left');
		$this->db->join('system_parameters T', 'T.param_id = P.title', 'left');
		$this->db->where('Q.InActive', 0);
		$this->db->where('M.InActive', 0);
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$this->db->where('M.prescription_status', 'VERIFIED');
		}

		if ($status !== 'ALL') {
			$this->db->where('Q.payment_status', $status);
		}
		if ($from !== '') { $this->db->where('DATE(Q.created_at) >=', $from); }
		if ($to   !== '') { $this->db->where('DATE(Q.created_at) <=', $to); }
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('Q.patient_no', $search);
			$this->db->or_like('Q.iop_id', $search);
			$this->db->or_like('Q.drug_name', $search);
			$this->db->or_like('P.firstname', $search);
			$this->db->or_like('P.lastname', $search);
			$this->db->group_end();
		}
		$this->db->order_by('Q.created_at', 'DESC');
		$this->db->limit($limit);
		return $this->db->get()->result();
	}

	/**
	 * Cashier marks pharmacy bill as PAID and unlocks dispensing.
	 */
	public function mark_pharmacy_bill_paid($bill_id, $user_id){
		$this->ensure_pharmacy_ghs_schema();
		$pk_col = $this->_pbq_pk_col();
		$bill_id = (int)$bill_id;
		if ($bill_id <= 0) return array('ok' => false, 'error' => 'Invalid bill ID.');

		$bill = $this->db->get_where('pharmacy_billing_queue', array($pk_col => $bill_id, 'InActive' => 0))->row();
		if (!$bill) return array('ok' => false, 'error' => 'Bill not found.');
		if (strtoupper(trim((string)$bill->payment_status)) === 'PAID') {
			return array('ok' => false, 'error' => 'Bill already marked as paid.');
		}

		$now = date('Y-m-d H:i:s');
		$this->db->where($pk_col, $bill_id);
		$this->db->update('pharmacy_billing_queue', array(
			'payment_status'  => 'PAID',
			'dispense_status' => 'READY',
			'paid_by'         => (string)$user_id,
			'paid_at'         => $now,
			'updated_at'      => $now,
		));

		$iop_med_id = (int)$bill->iop_med_id;
		if ($iop_med_id > 0 && $this->table_exists('iop_medication') && $this->column_exists('iop_medication', 'payment_status')) {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('iop_medication', array('payment_status' => 'PAID'));
		}

		$this->log_pharmacy_audit($iop_med_id, (string)$bill->iop_id, (string)$bill->patient_no, 'PAYMENT_RECEIVED', 'PENDING', 'PAID', null, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/**
	 * Cashier cancels a pharmacy bill (medication unavailable).
	 */
	public function cancel_pharmacy_bill($bill_id, $user_id, $reason = ''){
		$this->ensure_pharmacy_ghs_schema();
		$pk_col = $this->_pbq_pk_col();
		$bill_id = (int)$bill_id;
		if ($bill_id <= 0) return array('ok' => false, 'error' => 'Invalid bill ID.');

		$bill = $this->db->get_where('pharmacy_billing_queue', array($pk_col => $bill_id, 'InActive' => 0))->row();
		if (!$bill) return array('ok' => false, 'error' => 'Bill not found.');

		$this->db->where($pk_col, $bill_id);
		$this->db->update('pharmacy_billing_queue', array(
			'payment_status'  => 'CANCELLED',
			'dispense_status' => 'UNAVAILABLE',
			'updated_at'      => date('Y-m-d H:i:s'),
		));

		$iop_med_id = (int)$bill->iop_med_id;
		$this->log_pharmacy_audit($iop_med_id, (string)$bill->iop_id, (string)$bill->patient_no, 'BILL_CANCELLED', 'PENDING', 'CANCELLED', $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* â”€â”€ GHS Dashboard Counts â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function count_awaiting_payment(){
		$this->ensure_pharmacy_ghs_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		return $this->db->where(array('payment_status' => 'PENDING', 'InActive' => 0))->count_all_results('pharmacy_billing_queue');
	}

	public function count_ready_to_dispense(){
		$this->ensure_pharmacy_ghs_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		return $this->db->where(array('payment_status' => 'PAID', 'dispense_status' => 'READY', 'InActive' => 0))->count_all_results('pharmacy_billing_queue');
	}

	public function count_unavailable_prescriptions(){
		if (!$this->table_exists('iop_medication')) return 0;
		if (!$this->column_exists('iop_medication', 'dispensing_status')) return 0;
		return $this->db->where(array('dispensing_status' => 'UNAVAILABLE', 'InActive' => 0))->count_all_results('iop_medication');
	}

	public function count_pending_pharmacy_bills_today(){
		$this->ensure_pharmacy_ghs_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		$this->db->where(array('payment_status' => 'PENDING', 'InActive' => 0));
		$this->db->where("DATE(created_at) = '".date('Y-m-d')."'");
		return $this->db->count_all_results('pharmacy_billing_queue');
	}

	public function count_paid_pharmacy_bills_today(){
		$this->ensure_pharmacy_ghs_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		$this->db->where(array('payment_status' => 'PAID', 'InActive' => 0));
		$this->db->where("DATE(paid_at) = '".date('Y-m-d')."'");
		return $this->db->count_all_results('pharmacy_billing_queue');
	}

	public function reconcile_pharmacy_billing_queue_from_paid_invoices($limit = 50, $user_id = null){
		$this->ensure_pharmacy_ghs_schema();
		$limit = (int)$limit;
		if ($limit <= 0 || $limit > 200) { $limit = 50; }
		if (!$this->table_exists('pharmacy_billing_queue')) return array('ok' => true, 'reconciled' => 0);
		if (!$this->table_exists('iop_billing')) return array('ok' => true, 'reconciled' => 0);
		if (!$this->column_exists('iop_billing', 'payment_status')) return array('ok' => true, 'reconciled' => 0);
		if (!$this->table_exists('iop_medication')) return array('ok' => true, 'reconciled' => 0);
		$hasIopId = $this->column_exists('iop_medication', 'iop_id');
		$hasIOID = $this->column_exists('iop_medication', 'IO_ID');
		$medIopJoin = null;
		if ($hasIopId && $hasIOID) {
			$medIopJoin = '(H.iop_id = M.iop_id OR H.iop_id = M.IO_ID)';
		} elseif ($hasIopId) {
			$medIopJoin = 'H.iop_id = M.iop_id';
		} elseif ($hasIOID) {
			$medIopJoin = 'H.iop_id = M.IO_ID';
		}
		if ($medIopJoin === null) return array('ok' => true, 'reconciled' => 0);

		$this->db->select("H.invoice_no, UPPER(TRIM(H.payment_status)) AS payment_status", false);
		$this->db->from('pharmacy_billing_queue Q');
		$this->db->join('iop_medication M', "M.InActive = 0 AND M.iop_med_id = Q.iop_med_id", 'inner', false);
		$this->db->join('iop_billing H', "H.InActive = 0 AND " . $medIopJoin, 'inner', false);
		$this->db->where('Q.InActive', 0);
		$this->db->where('Q.payment_status', 'PENDING');
		$this->db->where("UPPER(TRIM(H.payment_status)) IN ('PAID','PARTIAL')", null, false);
		$this->db->group_by(array('H.invoice_no', 'H.payment_status'));
		$this->db->order_by('H.dDate', 'DESC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		if (empty($rows)) return array('ok' => true, 'reconciled' => 0);

		$this->load->model('app/billing_model');
		$reconciled = 0;
		foreach ($rows as $r) {
			$inv = isset($r->invoice_no) ? trim((string)$r->invoice_no) : '';
			$st = isset($r->payment_status) ? strtoupper(trim((string)$r->payment_status)) : '';
			if ($inv === '' || ($st !== 'PAID' && $st !== 'PARTIAL')) continue;
			try {
				$ok = $this->billing_model->reconcile_pharmacy_queue_for_invoice($inv, $st, $user_id);
				if ($ok) { $reconciled++; }
			} catch (Throwable $e) {
				
			}
		}
		return array('ok' => true, 'reconciled' => $reconciled);
	}

	/* â”€â”€ Patient-Level Counts (for dashboard consistency) â”€â”€ */

	/**
	 * Get comprehensive pharmacy summary with both item and patient counts.
	 * This ensures dashboard numbers match what users see in the worklist.
	 */
	public function get_pharmacy_summary_counts(){
		$this->ensure_pharmacy_ghs_schema();
		
		$summary = array(
			// Item-level counts (individual prescriptions)
			'items' => array(
				'pending' => $this->count_pending_prescriptions(),
				'ready' => $this->count_ready_to_dispense(),
				'partial' => $this->count_partial_prescriptions(),
				'awaiting_payment' => $this->count_awaiting_payment(),
				'external' => $this->count_external_purchases(),
				'deferred' => $this->count_deferred_pharmacy(),
				'dispensed_today' => $this->count_dispensed_today()
			),
			// Patient-level counts (unique patient visits)
			'patients' => $this->_count_patients_by_status()
		);
		
		return $summary;
	}

	/**
	 * Count unique patient visits by pharmacy status.
	 * This matches what users see in the worklist.
	 */
	private function _count_patients_by_status(){
		$counts = array(
			'pending' => 0,
			'ready' => 0,
			'in_progress' => 0,
			'awaiting_payment' => 0,
			'external' => 0,
			'completed' => 0,
			'total' => 0
		);
		
		if (!$this->table_exists('iop_medication')) {
			return $counts;
		}
		
		$hasBillingQueue = $this->table_exists('pharmacy_billing_queue');
		
		// Get all active patient visits with prescriptions
		$sql = "SELECT 
			I.IO_ID as iop_id,
			P.Insurance_comp,
			COUNT(DISTINCT M.iop_med_id) as total_items,
			SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') = 'DISPENSED' THEN 1 ELSE 0 END) as dispensed_count,
			SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') = 'PARTIAL' THEN 1 ELSE 0 END) as partial_count,
			SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') IN ('PENDING','') THEN 1 ELSE 0 END) as pending_count";
		
		if ($hasBillingQueue) {
			$sql .= ",
			SUM(CASE WHEN IFNULL(PBQ.payment_status,'PENDING') IN ('PAID','WAIVED') THEN 1 ELSE 0 END) as paid_count,
			SUM(CASE WHEN IFNULL(PBQ.extended_status,'') IN ('EXTERNAL_PURCHASE','WAIVED','WAIVER_APPROVED','EMERGENCY','DEFERRED','UNABLE_TO_PAY') THEN 1 ELSE 0 END) as exception_count";
		} else {
			$sql .= ",
			SUM(CASE WHEN IFNULL(M.payment_status,'PENDING') = 'PAID' THEN 1 ELSE 0 END) as paid_count,
			SUM(CASE WHEN IFNULL(M.extended_status,'') IN ('EXTERNAL_PURCHASE','WAIVED','EMERGENCY') THEN 1 ELSE 0 END) as exception_count";
		}
		
		$sql .= "
		FROM patient_details_iop I
		INNER JOIN iop_medication M ON M.iop_id = I.IO_ID AND M.InActive = 0";
		
		if ($hasBillingQueue) {
			$sql .= "
		LEFT JOIN pharmacy_billing_queue PBQ ON PBQ.iop_med_id = M.iop_med_id AND PBQ.InActive = 0";
		}
		
		$sql .= "
		LEFT JOIN patient_personal_info P ON P.patient_no = I.patient_no
		WHERE I.InActive = 0
		GROUP BY I.IO_ID, P.Insurance_comp";
		
		$q = $this->db->query($sql);
		$rows = $q ? $q->result() : array();
		
		foreach ($rows as $r) {
			$total = (int)$r->total_items;
			$dispensed = (int)$r->dispensed_count;
			$partial = (int)$r->partial_count;
			$pending = (int)$r->pending_count;
			$paid = (int)$r->paid_count;
			$exceptions = (int)$r->exception_count;
			
			// Determine payer type
			$ins = isset($r->Insurance_comp) ? strtoupper(trim((string)$r->Insurance_comp)) : '';
			$isNHIS = (strpos($ins, 'NHIS') !== false);
			
			// Determine payment status
			$paymentCleared = $isNHIS || ($paid >= $total) || ($exceptions >= $total);
			
			// Classify patient
			$counts['total']++;
			
			if ($dispensed >= $total && $total > 0) {
				$counts['completed']++;
			} elseif ($partial > 0 || $dispensed > 0) {
				$counts['in_progress']++;
			} elseif ($exceptions > 0 && $exceptions >= $pending) {
				$counts['external']++;
			} elseif ($paymentCleared) {
				$counts['ready']++;
			} elseif ($pending > 0) {
				$counts['awaiting_payment']++;
			} else {
				$counts['pending']++;
			}
		}
		
		return $counts;
	}

	/**
	 * Validate pharmacy data integrity and fix common discrepancies.
	 * Call this periodically or when discrepancies are detected.
	 * Returns array of issues found and fixed.
	 */
	public function validate_and_fix_pharmacy_data(){
		$issues = array();
		$fixed = array();
		
		if (!$this->table_exists('iop_medication')) {
			return array('issues' => $issues, 'fixed' => $fixed);
		}
		
		$hasBillingQueue = $this->table_exists('pharmacy_billing_queue');
		
		// Issue 1: Medications with dispensing_status but no administration record
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$sql = "SELECT M.iop_med_id, M.dispensing_status, M.total_qty,
				(SELECT IFNULL(SUM(a.dose_given),0) FROM iop_medication_administration a 
				 WHERE a.iop_med_id = M.iop_med_id AND a.InActive = 0 
				 AND a.status IN ('DISPENSED','PARTIAL')) as actual_dispensed
				FROM iop_medication M
				WHERE M.InActive = 0 
				AND M.dispensing_status = 'DISPENSED'
				HAVING actual_dispensed < M.total_qty";
			$q = $this->db->query($sql);
			$rows = $q ? $q->result() : array();
			
			foreach ($rows as $r) {
				$issues[] = "Medication #{$r->iop_med_id} marked DISPENSED but only {$r->actual_dispensed}/{$r->total_qty} actually dispensed";
				// Fix: Update status to PARTIAL if partially dispensed, PENDING if none
				$newStatus = ($r->actual_dispensed > 0) ? 'PARTIAL' : 'PENDING';
				$this->db->where('iop_med_id', $r->iop_med_id)
					->update('iop_medication', array('dispensing_status' => $newStatus));
				$fixed[] = "Fixed medication #{$r->iop_med_id} status to {$newStatus}";
			}
		}
		
		// Issue 2: Billing queue entries without matching medication
		if ($hasBillingQueue) {
			$pk_col = $this->_pbq_pk_col();
			$sql = "SELECT PBQ.{$pk_col} AS queue_id, PBQ.iop_med_id 
				FROM pharmacy_billing_queue PBQ
				LEFT JOIN iop_medication M ON M.iop_med_id = PBQ.iop_med_id
				WHERE PBQ.InActive = 0 AND (M.iop_med_id IS NULL OR M.InActive = 1)";
			$q = $this->db->query($sql);
			$orphans = $q ? $q->result() : array();
			
			foreach ($orphans as $o) {
				$issues[] = "Orphan billing queue entry #{$o->queue_id} for deleted medication #{$o->iop_med_id}";
				$this->db->where($pk_col, $o->queue_id)
					->update('pharmacy_billing_queue', array('InActive' => 1));
				$fixed[] = "Deactivated orphan billing queue entry #{$o->queue_id}";
			}
		}
		
		// Issue 3: Medications marked PAID in billing queue but PENDING in iop_medication
		if ($hasBillingQueue && $this->column_exists('iop_medication', 'payment_status')) {
			$sql = "SELECT M.iop_med_id, M.payment_status as med_status, PBQ.payment_status as queue_status
				FROM iop_medication M
				INNER JOIN pharmacy_billing_queue PBQ ON PBQ.iop_med_id = M.iop_med_id AND PBQ.InActive = 0
				WHERE M.InActive = 0 
				AND PBQ.payment_status = 'PAID' 
				AND IFNULL(M.payment_status,'PENDING') != 'PAID'";
			$q = $this->db->query($sql);
			$mismatches = $q ? $q->result() : array();
			
			foreach ($mismatches as $m) {
				$issues[] = "Payment status mismatch for medication #{$m->iop_med_id}: queue=PAID, medication={$m->med_status}";
				$this->db->where('iop_med_id', $m->iop_med_id)
					->update('iop_medication', array('payment_status' => 'PAID'));
				$fixed[] = "Synced payment status for medication #{$m->iop_med_id} to PAID";
			}
		}
		
		return array('issues' => $issues, 'fixed' => $fixed);
	}

	/* â”€â”€ Pharmacy Audit Log â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, $event_type, $old_status, $new_status, $notes, $user_id, $drug_name = null, $qty_dispensed = null, $batch_no = null, $expiry_date = null){
		if (!$this->table_exists('pharmacy_audit_log')) return;

		$has_legacy = $this->column_exists('pharmacy_audit_log', 'iop_med_id')
			&& $this->column_exists('pharmacy_audit_log', 'event_type')
			&& $this->column_exists('pharmacy_audit_log', 'performed_at');
		if ($has_legacy) {
			$data = array(
				'iop_med_id'   => (int)$iop_med_id,
				'iop_id'       => (string)$iop_id,
				'patient_no'   => (string)$patient_no,
				'event_type'   => (string)$event_type,
				'old_status'   => $old_status !== null ? (string)$old_status : null,
				'new_status'   => $new_status !== null ? (string)$new_status : null,
				'notes'        => $notes ? substr((string)$notes, 0, 255) : null,
				'performed_by' => (string)$user_id,
				'performed_at' => date('Y-m-d H:i:s'),
			);
			if ($this->column_exists('pharmacy_audit_log', 'drug_name'))    $data['drug_name']     = $drug_name    !== null ? substr((string)$drug_name, 0, 255) : null;
			if ($this->column_exists('pharmacy_audit_log', 'qty_dispensed')) $data['qty_dispensed'] = $qty_dispensed !== null ? (float)$qty_dispensed : null;
			if ($this->column_exists('pharmacy_audit_log', 'batch_no'))      $data['batch_no']      = $batch_no      !== null ? substr((string)$batch_no, 0, 100) : null;
			if ($this->column_exists('pharmacy_audit_log', 'expiry_date'))   $data['expiry_date']   = $expiry_date   !== null ? (string)$expiry_date : null;
			$this->db->insert('pharmacy_audit_log', $data);
			return;
		}

		$has_generic = $this->column_exists('pharmacy_audit_log', 'audit_table')
			&& $this->column_exists('pharmacy_audit_log', 'record_id')
			&& $this->column_exists('pharmacy_audit_log', 'old_value')
			&& $this->column_exists('pharmacy_audit_log', 'new_value');
		if ($has_generic) {
			$meta = array(
				'iop_med_id' => (int)$iop_med_id,
				'iop_id' => (string)$iop_id,
				'patient_no' => (string)$patient_no,
				'drug_name' => $drug_name,
				'qty_dispensed' => $qty_dispensed,
				'batch_no' => $batch_no,
				'expiry_date' => $expiry_date,
			);
			$this->db->insert('pharmacy_audit_log', array(
				'audit_table' => 'pharmacy',
				'record_id' => (int)$iop_med_id,
				'event_type' => (string)$event_type,
				'old_value' => $old_status !== null ? (string)$old_status : null,
				'new_value' => $new_status !== null ? (string)$new_status : null,
				'performed_by' => (string)$user_id,
				'performed_at' => date('Y-m-d H:i:s'),
				'notes' => $notes !== '' ? $notes : json_encode($meta),
				'ip_address' => $this->input->ip_address()
			));
		}
	}

	/* â”€â”€ Drug Search (AJAX) â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function search_drugs($term, $limit = 20){
		if (!$this->table_exists('medicine_drug_name')) return array();
		$term = trim((string)$term);
		if ($term === '') return array();

		$this->db->select('d.drug_id, d.drug_name, d.nStock, d.nPrice, d.re_order_level, c.med_category_name');
		if ($this->column_exists('medicine_drug_name', 'generic_name')) {
			$this->db->select('d.generic_name, d.dosage_form, d.strength');
		}
		if ($this->column_exists('medicine_drug_name', 'is_nhis_covered')) {
			$this->db->select('d.is_nhis_covered, d.nhis_price, d.cash_price');
		}
		$this->db->from('medicine_drug_name d');
		$this->db->join('medicine_category c', 'c.cat_id = d.med_cat_id', 'left');
		$this->db->where('d.InActive', 0);
		$this->db->group_start();
		$this->db->like('d.drug_name', $term);
		if ($this->column_exists('medicine_drug_name', 'generic_name')) {
			$this->db->or_like('d.generic_name', $term);
		}
		$this->db->group_end();
		$this->db->order_by('d.drug_name', 'ASC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	/* ================================================================== */
	/*  FLEXIBLE FINANCIAL + CLINICAL WORKFLOW (GHS)                      */
	/*  Statuses: EXTERNAL_PURCHASE | UNABLE_TO_PAY | DEFERRED | WAIVED   */
	/*            EMERGENCY | ADMITTED                                     */
	/* ================================================================== */

	public function ensure_flexible_workflow_schema(){
		static $fwDone = false;
		if ($fwDone) return;
		$fwDone = true;

		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) $this->db->db_debug = false;

		/* â”€â”€ pharmacy_billing_queue: add flexible columns â”€â”€ */
		if ($this->table_exists('pharmacy_billing_queue')) {
			$cols = array(
				'extended_status'    => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `extended_status` varchar(30) NOT NULL DEFAULT 'PENDING'",
				'external_flag'      => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `external_flag` tinyint(1) NOT NULL DEFAULT 0",
				'unable_to_pay_flag' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `unable_to_pay_flag` tinyint(1) NOT NULL DEFAULT 0",
				'deferred_flag'      => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `deferred_flag` tinyint(1) NOT NULL DEFAULT 0",
				'waiver_flag'        => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `waiver_flag` tinyint(1) NOT NULL DEFAULT 0",
				'emergency_flag'     => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `emergency_flag` tinyint(1) NOT NULL DEFAULT 0",
				'waiver_approved_by' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `waiver_approved_by` varchar(25) DEFAULT NULL",
				'waiver_approved_at' => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `waiver_approved_at` datetime DEFAULT NULL",
				'waiver_reason'      => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `waiver_reason` text DEFAULT NULL",
				'deferred_until'     => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `deferred_until` date DEFAULT NULL",
				'outstanding_balance'=> "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `outstanding_balance` decimal(18,2) NOT NULL DEFAULT 0",
				'flex_notes'         => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `flex_notes` text DEFAULT NULL",
				'cancelled_by'       => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `cancelled_by` varchar(25) DEFAULT NULL",
				'cancelled_reason'   => "ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `cancelled_reason` text DEFAULT NULL",
			);
			foreach ($cols as $col => $sql) {
				if (!$this->column_exists('pharmacy_billing_queue', $col)) {
					$this->db->query($sql);
				}
			}
		}

		/* â”€â”€ iop_medication: add flexible columns â”€â”€ */
		if ($this->table_exists('iop_medication')) {
			$medCols = array(
				'extended_status'    => "ALTER TABLE `iop_medication` ADD COLUMN `extended_status` varchar(30) NOT NULL DEFAULT 'PENDING'",
				'emergency_flag'     => "ALTER TABLE `iop_medication` ADD COLUMN `emergency_flag` tinyint(1) NOT NULL DEFAULT 0",
				'emergency_reason'   => "ALTER TABLE `iop_medication` ADD COLUMN `emergency_reason` text DEFAULT NULL",
				'emergency_by'       => "ALTER TABLE `iop_medication` ADD COLUMN `emergency_by` varchar(25) DEFAULT NULL",
				'emergency_at'       => "ALTER TABLE `iop_medication` ADD COLUMN `emergency_at` datetime DEFAULT NULL",
			);
			foreach ($medCols as $col => $sql) {
				if (!$this->column_exists('iop_medication', $col)) {
					$this->db->query($sql);
				}
			}
		}

		/* â”€â”€ outstanding_balances table â”€â”€ */
		$this->db->query("CREATE TABLE IF NOT EXISTS `outstanding_balances` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`patient_no` varchar(20) NOT NULL,
			`iop_id` varchar(11) DEFAULT NULL,
			`source_module` varchar(30) NOT NULL DEFAULT 'PHARMACY',
			`source_id` int(11) DEFAULT NULL,
			`description` varchar(255) DEFAULT NULL,
			`amount` decimal(18,2) NOT NULL DEFAULT 0,
			`balance_type` varchar(20) NOT NULL DEFAULT 'DEFERRED',
			`status` varchar(20) NOT NULL DEFAULT 'OUTSTANDING',
			`settled_by` varchar(25) DEFAULT NULL,
			`settled_at` datetime DEFAULT NULL,
			`due_date` date DEFAULT NULL,
			`created_by` varchar(25) DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`updated_at` datetime DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_ob_patient` (`patient_no`),
			KEY `idx_ob_iop` (`iop_id`),
			KEY `idx_ob_status` (`status`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		/* â”€â”€ financial_audit_log table â”€â”€ */
		$this->db->query("CREATE TABLE IF NOT EXISTS `financial_audit_log` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`patient_no` varchar(20) DEFAULT NULL,
			`iop_id` varchar(11) DEFAULT NULL,
			`source_module` varchar(30) DEFAULT NULL,
			`source_id` int(11) DEFAULT NULL,
			`event_type` varchar(50) NOT NULL,
			`old_status` varchar(30) DEFAULT NULL,
			`new_status` varchar(30) DEFAULT NULL,
			`amount` decimal(18,2) DEFAULT NULL,
			`notes` text DEFAULT NULL,
			`performed_by` varchar(25) DEFAULT NULL,
			`performed_at` datetime NOT NULL,
			PRIMARY KEY (`id`),
			KEY `idx_fal_patient` (`patient_no`),
			KEY `idx_fal_event` (`event_type`),
			KEY `idx_fal_at` (`performed_at`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		/* â”€â”€ waiver_requests table â”€â”€ */
		$this->db->query("CREATE TABLE IF NOT EXISTS `waiver_requests` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`patient_no` varchar(20) NOT NULL,
			`iop_id` varchar(11) DEFAULT NULL,
			`source_module` varchar(30) NOT NULL DEFAULT 'PHARMACY',
			`source_id` int(11) DEFAULT NULL,
			`amount` decimal(18,2) NOT NULL DEFAULT 0,
			`reason` text DEFAULT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'PENDING',
			`requested_by` varchar(25) DEFAULT NULL,
			`requested_at` datetime NOT NULL,
			`approved_by` varchar(25) DEFAULT NULL,
			`approved_at` datetime DEFAULT NULL,
			`approval_notes` text DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_wr_patient` (`patient_no`),
			KEY `idx_wr_status` (`status`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		/* â”€â”€ emergency_overrides table â”€â”€ */
		$this->db->query("CREATE TABLE IF NOT EXISTS `emergency_overrides` (
			`id` int(11) NOT NULL AUTO_INCREMENT,
			`patient_no` varchar(20) NOT NULL,
			`iop_id` varchar(11) DEFAULT NULL,
			`source_module` varchar(30) NOT NULL DEFAULT 'PHARMACY',
			`source_id` int(11) DEFAULT NULL,
			`reason` text DEFAULT NULL,
			`override_by` varchar(25) DEFAULT NULL,
			`override_at` datetime NOT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`id`),
			KEY `idx_eo_patient` (`patient_no`),
			KEY `idx_eo_at` (`override_at`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		if ($old !== null) $this->db->db_debug = $old;
	}

	public function ensure_pharmacy_finalization_schema(){
		if ($this->table_exists('iop_medication')) {
			$this->_add_column_if_missing('iop_medication', 'billing_finalized_at', 'datetime DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'billing_finalized_by', 'varchar(25) DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'original_medicine_id', 'int(11) DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'original_medicine_text', 'varchar(255) DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'substituted_medicine_id', 'int(11) DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'substituted_medicine_text', 'varchar(255) DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'substitution_reason', 'text DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'substituted_by', 'varchar(25) DEFAULT NULL');
			$this->_add_column_if_missing('iop_medication', 'substituted_at', 'datetime DEFAULT NULL');
		}
		if ($this->table_exists('pharmacy_audit_log')) {
			$this->_add_column_if_missing('pharmacy_audit_log', 'original_drug_id', 'int(11) DEFAULT NULL');
			$this->_add_column_if_missing('pharmacy_audit_log', 'substitute_drug_id', 'int(11) DEFAULT NULL');
		}
	}

	/* â”€â”€ Flexible Payment-Gate Check â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	/**
	 * Returns true if a prescription can be dispensed without cashier payment.
	 * Exceptions: EXTERNAL_PURCHASE, UNABLE_TO_PAY, DEFERRED, WAIVED, EMERGENCY
	 */
	public function check_flexible_dispense_gate($iop_med_id){
		$this->ensure_flexible_workflow_schema();
		$iop_med_id = (int)$iop_med_id;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('allowed' => false, 'reason' => 'Prescription not found.');

		if ($this->column_exists('iop_medication', 'emergency_flag') && !empty($med->emergency_flag)) {
			return array('allowed' => true, 'reason' => 'Emergency override active.', 'mode' => 'EMERGENCY');
		}
		if ($this->column_exists('iop_medication', 'extended_status')) {
			$es = strtoupper(trim((string)$med->extended_status));
			$allowed_exceptions = array('EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','ADMITTED');
			if (in_array($es, $allowed_exceptions)) {
				return array('allowed' => true, 'reason' => $es, 'mode' => $es);
			}
		}
		$gate = $this->check_ghs_payment_gate($iop_med_id);
		if ($gate['paid']) {
			return array('allowed' => true, 'reason' => 'Payment confirmed.', 'mode' => 'PAID');
		}
		return array('allowed' => false, 'reason' => 'Payment required. Direct patient to cashier.', 'mode' => 'BLOCKED');
	}

	/* â”€â”€ External Purchase â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function mark_external_purchase($iop_med_id, $user_id, $reason = ''){
		$this->ensure_flexible_workflow_schema();
		$iop_med_id = (int)$iop_med_id;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before marking external purchase.');
			}
		}

		$now = date('Y-m-d H:i:s');
		$iop_id     = isset($med->iop_id) ? (string)$med->iop_id : '';
		$patient_no = $this->_get_patient_no_for_iop($iop_id);

		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', array(
			'dispensing_status' => 'EXTERNAL',
			'extended_status'   => 'EXTERNAL_PURCHASE',
			'payment_status'    => 'EXTERNAL',
		));

		if ($this->table_exists('pharmacy_billing_queue')) {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('pharmacy_billing_queue', array(
				'extended_status' => 'EXTERNAL_PURCHASE',
				'external_flag'   => 1,
				'payment_status'  => 'EXTERNAL',
				'dispense_status' => 'EXTERNAL',
				'flex_notes'      => $reason,
				'updated_at'      => $now,
			));
		}

		$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'EXTERNAL_PURCHASE', 'PENDING', 'EXTERNAL_PURCHASE', $reason, $user_id);
		$this->_log_financial_audit('PHARMACY', $iop_med_id, $patient_no, $iop_id, 'EXTERNAL_PURCHASE', 'PENDING', 'EXTERNAL_PURCHASE', 0, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* â”€â”€ Unable to Pay â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function mark_unable_to_pay($iop_med_id, $user_id, $reason = ''){
		$this->ensure_flexible_workflow_schema();
		$iop_med_id = (int)$iop_med_id;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before marking unable to pay.');
			}
		}

		$now = date('Y-m-d H:i:s');
		$iop_id     = isset($med->iop_id) ? (string)$med->iop_id : '';
		$patient_no = $this->_get_patient_no_for_iop($iop_id);

		$amount = isset($med->unit_price) ? (float)$med->unit_price : 0.0;

		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', array(
			'extended_status' => 'UNABLE_TO_PAY',
			'payment_status'  => 'UNABLE_TO_PAY',
		));

		if ($this->table_exists('pharmacy_billing_queue')) {
			$total_col = $this->_pbq_total_col();
			$this->db->where('iop_med_id', $iop_med_id);
			$q = $this->db->get('pharmacy_billing_queue')->row();
			if ($q && isset($q->{$total_col})) $amount = (float)$q->{$total_col};

			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('pharmacy_billing_queue', array(
				'extended_status'     => 'UNABLE_TO_PAY',
				'unable_to_pay_flag'  => 1,
				'payment_status'      => 'UNABLE_TO_PAY',
				'outstanding_balance' => $amount,
				'flex_notes'          => $reason,
				'updated_at'          => $now,
			));
		}

		if ($amount > 0) {
			$this->db->insert('outstanding_balances', array(
				'patient_no'    => $patient_no,
				'iop_id'        => $iop_id,
				'source_module' => 'PHARMACY',
				'source_id'     => $iop_med_id,
				'description'   => 'Unable to pay â€” pharmacy prescription',
				'amount'        => $amount,
				'balance_type'  => 'UNABLE_TO_PAY',
				'status'        => 'OUTSTANDING',
				'due_date'      => null,
				'created_by'    => $user_id,
				'created_at'    => $now,
			));
		}

		$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'UNABLE_TO_PAY', 'PENDING', 'UNABLE_TO_PAY', $reason, $user_id);
		$this->_log_financial_audit('PHARMACY', $iop_med_id, $patient_no, $iop_id, 'UNABLE_TO_PAY', 'PENDING', 'UNABLE_TO_PAY', $amount, $reason, $user_id);
		return array('ok' => true, 'error' => '', 'outstanding_balance' => $amount);
	}

	/* â”€â”€ Deferred Payment â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function mark_deferred($iop_med_id, $user_id, $reason = '', $defer_until = null){
		$this->ensure_flexible_workflow_schema();
		$iop_med_id = (int)$iop_med_id;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before deferring payment.');
			}
		}

		$now = date('Y-m-d H:i:s');
		$iop_id     = isset($med->iop_id) ? (string)$med->iop_id : '';
		$patient_no = $this->_get_patient_no_for_iop($iop_id);
		$amount     = 0.0;

		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', array(
			'extended_status' => 'DEFERRED',
			'payment_status'  => 'DEFERRED',
		));

		if ($this->table_exists('pharmacy_billing_queue')) {
			$total_col = $this->_pbq_total_col();
			$this->db->where('iop_med_id', $iop_med_id);
			$q = $this->db->get('pharmacy_billing_queue')->row();
			if ($q && isset($q->{$total_col})) $amount = (float)$q->{$total_col};

			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('pharmacy_billing_queue', array(
				'extended_status'     => 'DEFERRED',
				'deferred_flag'       => 1,
				'payment_status'      => 'DEFERRED',
				'outstanding_balance' => $amount,
				'deferred_until'      => $defer_until,
				'flex_notes'          => $reason,
				'updated_at'          => $now,
			));
		}

		if ($amount > 0) {
			$this->db->insert('outstanding_balances', array(
				'patient_no'    => $patient_no,
				'iop_id'        => $iop_id,
				'source_module' => 'PHARMACY',
				'source_id'     => $iop_med_id,
				'description'   => 'Deferred payment â€” pharmacy prescription',
				'amount'        => $amount,
				'balance_type'  => 'DEFERRED',
				'status'        => 'OUTSTANDING',
				'due_date'      => $defer_until,
				'created_by'    => $user_id,
				'created_at'    => $now,
			));
		}

		$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'DEFERRED', 'PENDING', 'DEFERRED', $reason, $user_id);
		$this->_log_financial_audit('PHARMACY', $iop_med_id, $patient_no, $iop_id, 'DEFERRED_PAYMENT', 'PENDING', 'DEFERRED', $amount, $reason, $user_id);
		return array('ok' => true, 'error' => '', 'outstanding_balance' => $amount);
	}

	/* â”€â”€ Emergency Override â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function mark_emergency_override($iop_med_id, $user_id, $reason = ''){
		$this->ensure_flexible_workflow_schema();
		$iop_med_id = (int)$iop_med_id;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before emergency override.');
			}
		}

		$now = date('Y-m-d H:i:s');
		$iop_id     = isset($med->iop_id) ? (string)$med->iop_id : '';
		$patient_no = $this->_get_patient_no_for_iop($iop_id);

		$updData = array('extended_status' => 'EMERGENCY');
		if ($this->column_exists('iop_medication', 'emergency_flag')) {
			$updData['emergency_flag']   = 1;
			$updData['emergency_reason'] = $reason;
			$updData['emergency_by']     = $user_id;
			$updData['emergency_at']     = $now;
		}
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', $updData);

		if ($this->table_exists('pharmacy_billing_queue')) {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('pharmacy_billing_queue', array(
				'extended_status' => 'EMERGENCY',
				'emergency_flag'  => 1,
				'flex_notes'      => $reason,
				'updated_at'      => $now,
			));
		}

		if ($this->table_exists('emergency_overrides')) {
			$this->db->insert('emergency_overrides', array(
				'patient_no'    => $patient_no,
				'iop_id'        => $iop_id,
				'source_module' => 'PHARMACY',
				'source_id'     => $iop_med_id,
				'reason'        => $reason,
				'override_by'   => $user_id,
				'override_at'   => $now,
			));
		}

		$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'EMERGENCY_OVERRIDE', 'PENDING', 'EMERGENCY', $reason, $user_id);
		$this->_log_financial_audit('PHARMACY', $iop_med_id, $patient_no, $iop_id, 'EMERGENCY_OVERRIDE', 'PENDING', 'EMERGENCY', 0, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	/* â”€â”€ Waiver Request / Approval â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function request_waiver($iop_med_id, $user_id, $reason = ''){
		$this->ensure_flexible_workflow_schema();
		$iop_med_id = (int)$iop_med_id;

		$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
		if (!$med) return array('ok' => false, 'error' => 'Prescription not found.');
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'VERIFIED') {
				return array('ok' => false, 'error' => 'Prescription must be VERIFIED before requesting waiver.');
			}
		}

		$now = date('Y-m-d H:i:s');
		$iop_id     = isset($med->iop_id) ? (string)$med->iop_id : '';
		$patient_no = $this->_get_patient_no_for_iop($iop_id);
		$amount     = 0.0;

		if ($this->table_exists('pharmacy_billing_queue')) {
			$total_col = $this->_pbq_total_col();
			$this->db->where('iop_med_id', $iop_med_id);
			$q = $this->db->get('pharmacy_billing_queue')->row();
			if ($q && isset($q->{$total_col})) $amount = (float)$q->{$total_col};
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->update('pharmacy_billing_queue', array(
				'extended_status' => 'WAIVER_REQUESTED',
				'waiver_flag'     => 1,
				'flex_notes'      => $reason,
				'updated_at'      => $now,
			));
		}
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication', array('extended_status' => 'WAIVER_REQUESTED', 'payment_status' => 'WAIVER_REQUESTED'));

		if ($this->table_exists('waiver_requests')) {
			$this->db->where(array('source_module' => 'PHARMACY', 'source_id' => $iop_med_id, 'status' => 'PENDING', 'InActive' => 0));
			if ($this->db->count_all_results('waiver_requests') === 0) {
				$this->db->insert('waiver_requests', array(
					'patient_no'    => $patient_no,
					'iop_id'        => $iop_id,
					'source_module' => 'PHARMACY',
					'source_id'     => $iop_med_id,
					'amount'        => $amount,
					'reason'        => $reason,
					'status'        => 'PENDING',
					'requested_by'  => $user_id,
					'requested_at'  => $now,
				));
			}
		}

		$this->log_pharmacy_audit($iop_med_id, $iop_id, $patient_no, 'WAIVER_REQUESTED', 'PENDING', 'WAIVER_REQUESTED', $reason, $user_id);
		$this->_log_financial_audit('PHARMACY', $iop_med_id, $patient_no, $iop_id, 'WAIVER_REQUESTED', 'PENDING', 'WAIVER_REQUESTED', $amount, $reason, $user_id);
		return array('ok' => true, 'error' => '');
	}

	public function approve_pharmacy_waiver($waiver_id, $admin_id, $approval_notes = ''){
		$this->ensure_flexible_workflow_schema();
		$waiver_id = (int)$waiver_id;
		if (!$this->table_exists('waiver_requests')) return array('ok' => false, 'error' => 'Waiver system not initialised.');

		$wr = $this->db->get_where('waiver_requests', array('id' => $waiver_id, 'InActive' => 0))->row();
		if (!$wr) return array('ok' => false, 'error' => 'Waiver request not found.');

		$now = date('Y-m-d H:i:s');
		$this->db->where('id', $waiver_id);
		$this->db->update('waiver_requests', array(
			'status'         => 'APPROVED',
			'approved_by'    => $admin_id,
			'approved_at'    => $now,
			'approval_notes' => $approval_notes,
		));

		$srcId = (int)$wr->source_id;
		if ($wr->source_module === 'PHARMACY') {
			$this->db->where('iop_med_id', $srcId);
			$this->db->update('iop_medication', array('extended_status' => 'WAIVED', 'payment_status' => 'WAIVED'));
			if ($this->table_exists('pharmacy_billing_queue')) {
				$this->db->where('iop_med_id', $srcId);
				$this->db->update('pharmacy_billing_queue', array(
					'extended_status'    => 'WAIVED',
					'waiver_flag'        => 1,
					'payment_status'     => 'WAIVED',
					'waiver_approved_by' => $admin_id,
					'waiver_approved_at' => $now,
					'waiver_reason'      => $approval_notes,
					'updated_at'         => $now,
				));
			}
		}
		$this->_log_financial_audit($wr->source_module, $srcId, (string)$wr->patient_no, (string)$wr->iop_id, 'WAIVER_APPROVED', 'WAIVER_REQUESTED', 'WAIVED', (float)$wr->amount, $approval_notes, $admin_id);
		return array('ok' => true, 'error' => '');
	}

	/* â”€â”€ Cashier: Get Flexible Bills â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	public function get_deferred_pharmacy_bills(){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return array();
		$this->db->select('Q.*, M.total_qty, M.dispensing_status, D.drug_name AS drug_label');
		$this->db->from('pharmacy_billing_queue Q');
		$this->db->join('iop_medication M', 'M.iop_med_id = Q.iop_med_id', 'left');
		$this->db->join('medicine_drug_name D', 'D.drug_id = Q.drug_id', 'left');
		$this->db->where("Q.extended_status IN ('DEFERRED','UNABLE_TO_PAY')", null, false);
		$this->db->where('Q.InActive', 0);
		$this->db->order_by('Q.created_at', 'DESC');
		return $this->db->get()->result();
	}

	public function get_waiver_requests_pending(){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('waiver_requests')) return array();
		$this->db->select('W.*, P.firstname, P.lastname');
		$this->db->from('waiver_requests W');
		$this->db->join('patient_personal_info P', 'P.patient_no = W.patient_no', 'left');
		$this->db->where(array('W.status' => 'PENDING', 'W.InActive' => 0));
		$this->db->order_by('W.requested_at', 'DESC');
		return $this->db->get()->result();
	}

	public function get_external_purchase_list($date_from = '', $date_to = ''){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return array();
		$this->db->select('Q.*, D.drug_name AS drug_label');
		$this->db->from('pharmacy_billing_queue Q');
		$this->db->join('medicine_drug_name D', 'D.drug_id = Q.drug_id', 'left');
		$this->db->where("Q.extended_status = 'EXTERNAL_PURCHASE'", null, false);
		$this->db->where('Q.InActive', 0);
		if ($date_from !== '') $this->db->where('DATE(Q.created_at) >=', $date_from);
		if ($date_to !== '')   $this->db->where('DATE(Q.created_at) <=', $date_to);
		$this->db->order_by('Q.created_at', 'DESC');
		return $this->db->get()->result();
	}

	public function count_external_purchases(){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		return $this->db->where(array('extended_status' => 'EXTERNAL_PURCHASE', 'InActive' => 0))->count_all_results('pharmacy_billing_queue');
	}

	public function count_deferred_pharmacy(){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		$this->db->where("extended_status IN ('DEFERRED','UNABLE_TO_PAY')", null, false);
		$this->db->where('InActive', 0);
		return $this->db->count_all_results('pharmacy_billing_queue');
	}

	public function count_emergency_pharmacy(){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('pharmacy_billing_queue')) return 0;
		return $this->db->where(array('emergency_flag' => 1, 'InActive' => 0))->count_all_results('pharmacy_billing_queue');
	}

	public function settle_outstanding_balance($outstanding_id, $user_id){
		$this->ensure_flexible_workflow_schema();
		if (!$this->table_exists('outstanding_balances')) return false;
		$this->db->where('id', (int)$outstanding_id);
		$this->db->update('outstanding_balances', array(
			'status'     => 'SETTLED',
			'settled_by' => $user_id,
			'settled_at' => date('Y-m-d H:i:s'),
			'updated_at' => date('Y-m-d H:i:s'),
		));
		return true;
	}

	/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
	   PATIENT-BASED PHARMACY WORKLIST (V3)
	   Uses pharmacy_billing_queue as SINGLE SOURCE OF TRUTH for payment status
	   Uses iop_medication_administration for ACTUAL dispensed quantities
	   â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */

	/**
	 * Get list of patients with pending prescriptions (grouped by patient/IOP).
	 * Returns one row per patient visit, with summary counts.
	 */
	public function get_patient_worklist($filters = array()){
		$search = isset($filters['search']) ? trim((string)$filters['search']) : '';
		$status_filter = isset($filters['status']) ? strtoupper(trim((string)$filters['status'])) : '';
		$date_from = isset($filters['date_from']) ? trim((string)$filters['date_from']) : '';
		$date_to = isset($filters['date_to']) ? trim((string)$filters['date_to']) : '';
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
		if ($limit <= 0 || $limit > 500) $limit = 100;

		// Check if pharmacy_billing_queue exists (single source of truth for payment)
		$hasBillingQueue = $this->table_exists('pharmacy_billing_queue');
		$hasClearanceWorkflow = $this->table_exists('iop_clearance_workflow');
		$hasAdminTable = $this->table_exists('iop_medication_administration');

		$extExpr = $hasBillingQueue
			? "UPPER(TRIM(COALESCE(NULLIF(PBQ.extended_status,''), NULLIF(M.extended_status,''),'')))"
			: "UPPER(TRIM(IFNULL(M.extended_status,'')))";
		$resolvedExtSet = "('EXTERNAL_PURCHASE','EXTERNAL','WAIVED','WAIVER_APPROVED','CANCELLED','UNAVAILABLE','UNABLE_TO_PAY')";

		if ($hasAdminTable) {
			$dispensedCountExpr = "SUM(CASE WHEN IFNULL(ADM.dispensed_qty,0) >= IFNULL(M.total_qty,0) AND IFNULL(M.total_qty,0) > 0 THEN 1 ELSE 0 END) as dispensed_count";
			$partialCountExpr = "SUM(CASE WHEN IFNULL(ADM.dispensed_qty,0) > 0 AND IFNULL(ADM.dispensed_qty,0) < IFNULL(M.total_qty,0) THEN 1 ELSE 0 END) as partial_count";
			$pendingCountExpr = "SUM(CASE WHEN IFNULL(ADM.dispensed_qty,0) <= 0 AND IFNULL(M.total_qty,0) > 0 AND IFNULL(M.dispensing_status,'PENDING') NOT IN ('DISPENSED','EXTERNAL','UNAVAILABLE') AND {$extExpr} NOT IN {$resolvedExtSet} THEN 1 ELSE 0 END) as pending_count";
			$unresolvedCountExpr = "SUM(CASE WHEN (IFNULL(M.total_qty,0) - IFNULL(ADM.dispensed_qty,0)) > 0.0001 AND IFNULL(M.dispensing_status,'PENDING') NOT IN ('EXTERNAL','UNAVAILABLE') AND {$extExpr} NOT IN {$resolvedExtSet} THEN 1 ELSE 0 END) as unresolved_count";
		} else {
			$dispensedCountExpr = "SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') = 'DISPENSED' THEN 1 ELSE 0 END) as dispensed_count";
			$partialCountExpr = "SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') = 'PARTIAL' THEN 1 ELSE 0 END) as partial_count";
			$pendingCountExpr = "SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') IN ('PENDING','') AND {$extExpr} NOT IN {$resolvedExtSet} THEN 1 ELSE 0 END) as pending_count";
			$unresolvedCountExpr = "SUM(CASE WHEN IFNULL(M.dispensing_status,'PENDING') NOT IN ('DISPENSED','EXTERNAL','UNAVAILABLE') AND {$extExpr} NOT IN {$resolvedExtSet} THEN 1 ELSE 0 END) as unresolved_count";
		}

		// Get distinct patient visits with prescription counts
		// Payment status from pharmacy_billing_queue (SINGLE SOURCE OF TRUTH)
		$sql = "SELECT 
			I.IO_ID as iop_id,
			I.patient_no,
			I.date_visit,
			CONCAT_WS(' ', T.cValue, P.firstname, P.middlename, P.lastname) as patient_name,
			P.Insurance_comp";
		if ($hasClearanceWorkflow) {
			$sql .= ", IFNULL(WF.medication_cleared_at,'') as medication_cleared_at";
		} else {
			$sql .= ", '' as medication_cleared_at";
		}
		$sql .= ",
			COUNT(DISTINCT M.iop_med_id) as total_items,
			{$dispensedCountExpr},
			{$partialCountExpr},
			{$pendingCountExpr},
			{$unresolvedCountExpr},";
		
		// Payment counts from billing queue (single source of truth)
		if ($hasBillingQueue) {
			$sql .= "
			SUM(CASE WHEN IFNULL(PBQ.payment_status,'PENDING') IN ('PAID','WAIVED') THEN 1 ELSE 0 END) as paid_count,
			SUM(CASE WHEN IFNULL(PBQ.extended_status,'') IN ('EXTERNAL_PURCHASE','WAIVED','WAIVER_APPROVED','EMERGENCY','DEFERRED','UNABLE_TO_PAY') THEN 1 ELSE 0 END) as exception_count,";
		} else {
			$sql .= "
			SUM(CASE WHEN IFNULL(M.payment_status,'PENDING') = 'PAID' THEN 1 ELSE 0 END) as paid_count,
			SUM(CASE WHEN IFNULL(M.extended_status,'') IN ('EXTERNAL_PURCHASE','WAIVED','EMERGENCY') THEN 1 ELSE 0 END) as exception_count,";
		}
		
		$sql .= "
			MIN(M.dDate) as first_rx_date,
			MAX(M.dDate) as last_rx_date
		FROM patient_details_iop I
		INNER JOIN iop_medication M ON M.iop_id = I.IO_ID AND M.InActive = 0";
		if ($hasClearanceWorkflow) {
			$sql .= "\n\t\tLEFT JOIN iop_clearance_workflow WF ON WF.iop_id = I.IO_ID AND WF.InActive = 0";
		}
		if ($hasAdminTable) {
			$sql .= "\n\t\tLEFT JOIN (SELECT iop_med_id, SUM(CASE WHEN status IN ('DISPENSED','PARTIAL') THEN dose_given ELSE 0 END) as dispensed_qty FROM iop_medication_administration WHERE InActive = 0 GROUP BY iop_med_id) ADM ON ADM.iop_med_id = M.iop_med_id";
		}
		
		if ($hasBillingQueue) {
			$sql .= "
		LEFT JOIN pharmacy_billing_queue PBQ ON PBQ.iop_med_id = M.iop_med_id AND PBQ.InActive = 0";
		}
		
		$sql .= "
		LEFT JOIN patient_personal_info P ON P.patient_no = I.patient_no
		LEFT JOIN system_parameters T ON T.param_id = P.title
		WHERE I.InActive = 0";

		if ($date_from !== '') {
			$sql .= " AND DATE(M.dDate) >= " . $this->db->escape($date_from);
		}
		if ($date_to !== '') {
			$sql .= " AND DATE(M.dDate) <= " . $this->db->escape($date_to);
		}
		if ($search !== '') {
			$sql .= " AND (I.IO_ID LIKE " . $this->db->escape('%'.$search.'%') . 
					" OR P.patient_no LIKE " . $this->db->escape('%'.$search.'%') .
					" OR P.firstname LIKE " . $this->db->escape('%'.$search.'%') .
					" OR P.lastname LIKE " . $this->db->escape('%'.$search.'%') . ")";
		}

		$sql .= " GROUP BY I.IO_ID, I.patient_no, I.date_visit, patient_name, P.Insurance_comp";
		if ($hasClearanceWorkflow) {
			$sql .= ", WF.medication_cleared_at";
		}

		// Filter by status after grouping
		if ($status_filter === 'PENDING') {
			$sql .= " HAVING pending_count > 0";
		} elseif ($status_filter === 'PARTIAL') {
			$sql .= " HAVING partial_count > 0";
		} elseif ($status_filter === 'COMPLETED') {
			$sql .= " HAVING pending_count = 0 AND partial_count = 0";
		} elseif ($status_filter === 'AWAITING_PAYMENT') {
			$sql .= " HAVING paid_count < total_items AND exception_count < total_items";
		}

		$sql .= " ORDER BY last_rx_date DESC LIMIT " . (int)$limit;

		$q = $this->db->query($sql);
		$rows = $q ? $q->result() : array();

		// Build payment status map from actual billing records (SINGLE SOURCE OF TRUTH)
		$iop_ids = array();
		foreach ($rows as $r) {
			$iop_ids[] = $r->iop_id;
		}
		$billingStatusMap = $this->_get_billing_payment_status_map($iop_ids);

		// Compute overall status for each patient
		foreach ($rows as $r) {
			$total = (int)$r->total_items;
			$dispensed = (int)$r->dispensed_count;
			$partial = (int)$r->partial_count;
			$pending = (int)$r->pending_count;
			$exceptions = (int)$r->exception_count;

			$clearedAt = isset($r->medication_cleared_at) ? trim((string)$r->medication_cleared_at) : '';
			$medCleared = ($clearedAt !== '' && $clearedAt !== '0000-00-00 00:00:00');
			if ($medCleared) {
				$clearTs = strtotime($clearedAt);
				$lastRxTs = isset($r->last_rx_date) ? strtotime((string)$r->last_rx_date) : 0;
				if ($clearTs && $lastRxTs && $lastRxTs > $clearTs) {
					$medCleared = false;
				}
			}
			$r->medication_cleared = $medCleared;
			$unresolved = isset($r->unresolved_count) ? (int)$r->unresolved_count : $pending;
			$r->_hide_from_worklist = ($r->medication_cleared && $unresolved === 0 && $partial === 0);

			// Payer type (determine first as it affects payment status)
			$ins = isset($r->Insurance_comp) ? strtoupper(trim((string)$r->Insurance_comp)) : '';
			$r->payer_type = (strpos($ins, 'NHIS') !== false) ? 'NHIS' : 'CASH';

			// Payment status from ACTUAL BILLING RECORDS (SINGLE SOURCE OF TRUTH)
			// NHIS patients are auto-cleared, CASH patients check iop_billing + iop_receipt
			$billingStatus = isset($billingStatusMap[$r->iop_id]) ? $billingStatusMap[$r->iop_id] : null;
			
			if ($r->payer_type === 'NHIS') {
				// NHIS patients are auto-cleared for pharmacy
				$paymentCleared = true;
				$paymentPartial = false;
			} elseif ($billingStatus !== null) {
				// Use actual billing records
				$paymentCleared = $billingStatus['is_paid'];
				$paymentPartial = $billingStatus['partial_paid'];
			} elseif ($exceptions >= $total) {
				// All items have exception status (external, waived, etc.)
				$paymentCleared = true;
				$paymentPartial = false;
			} else {
				// No billing record found - awaiting payment
				$paymentCleared = false;
				$paymentPartial = false;
			}
			
			if ($paymentCleared) {
				$r->payment_status = 'CLEARED';
				$r->payment_class = 'success';
			} elseif ($paymentPartial) {
				$r->payment_status = 'PARTIAL';
				$r->payment_class = 'warning';
			} else {
				$r->payment_status = 'AWAITING';
				$r->payment_class = 'danger';
			}

			// Overall dispensing status (SINGLE SOURCE OF TRUTH)
			if ($unresolved === 0 && $partial === 0) {
				$r->overall_status = 'COMPLETED';
				$r->status_class = 'success';
			} elseif ($dispensed >= $total && $total > 0) {
				$r->overall_status = 'COMPLETED';
				$r->status_class = 'success';
			} elseif ($partial > 0 || $dispensed > 0) {
				$r->overall_status = 'IN_PROGRESS';
				$r->status_class = 'warning';
			} elseif ($paymentCleared) {
				// Payment cleared but nothing dispensed yet = Ready to dispense
				$r->overall_status = 'READY';
				$r->status_class = 'info';
			} elseif ($paymentPartial) {
				// Some items paid, waiting for rest
				$r->overall_status = 'PARTIAL_PAID';
				$r->status_class = 'warning';
			} else {
				// Nothing paid yet
				$r->overall_status = 'AWAITING_PAYMENT';
				$r->status_class = 'danger';
			}
		}

		$filtered = array();
		foreach ($rows as $r) {
			if (isset($r->_hide_from_worklist) && $r->_hide_from_worklist) continue;
			$filtered[] = $r;
		}

		return $filtered;
	}

	/**
	 * Get billing payment status for multiple IOP visits.
	 * Uses iop_billing + iop_receipt as SINGLE SOURCE OF TRUTH.
	 */
	private function _get_billing_payment_status_map($iop_ids){
		$map = array();
		if (empty($iop_ids) || !$this->table_exists('iop_billing')) {
			return $map;
		}

		// Get all invoices for these visits
		$this->db->select('invoice_no, iop_id, total_amount, payer_type');
		$this->db->from('iop_billing');
		$this->db->where('InActive', 0);
		$this->db->where_in('iop_id', $iop_ids);
		$invoices = $this->db->get()->result();

		if (empty($invoices)) {
			return $map;
		}

		// Get receipt totals for these invoices
		$invoiceNos = array();
		$invoiceMap = array(); // invoice_no => iop_id
		foreach ($invoices as $inv) {
			$invoiceNos[] = $inv->invoice_no;
			$invoiceMap[$inv->invoice_no] = array(
				'iop_id' => $inv->iop_id,
				'total' => (float)$inv->total_amount,
				'payer_type' => isset($inv->payer_type) ? strtoupper(trim((string)$inv->payer_type)) : 'CASH'
			);
		}

		$receiptTotals = array();
		if ($this->table_exists('iop_receipt') && !empty($invoiceNos)) {
			$this->db->select('invoice_no, SUM(amountPaid) as total_paid', false);
			$this->db->from('iop_receipt');
			$this->db->where('InActive', 0);
			$this->db->where_in('invoice_no', $invoiceNos);
			$this->db->group_by('invoice_no');
			$receipts = $this->db->get()->result();
			foreach ($receipts as $rec) {
				$receiptTotals[$rec->invoice_no] = (float)$rec->total_paid;
			}
		}

		// Build status map per iop_id
		foreach ($invoiceMap as $invNo => $invData) {
			$iop_id = $invData['iop_id'];
			$total = $invData['total'];
			$paid = isset($receiptTotals[$invNo]) ? $receiptTotals[$invNo] : 0;
			$payerType = $invData['payer_type'];

			// NHIS invoices are considered paid
			$isPaid = ($payerType === 'NHIS') || ($total > 0 && $paid >= $total) || ($total <= 0);
			$partialPaid = ($paid > 0 && $paid < $total);

			if (!isset($map[$iop_id])) {
				$map[$iop_id] = array('is_paid' => $isPaid, 'partial_paid' => $partialPaid, 'total' => $total, 'paid' => $paid);
			} else {
				// Multiple invoices - combine status
				$map[$iop_id]['total'] += $total;
				$map[$iop_id]['paid'] += $paid;
				// All invoices must be paid for overall to be paid
				$map[$iop_id]['is_paid'] = $map[$iop_id]['is_paid'] && $isPaid;
				$map[$iop_id]['partial_paid'] = $map[$iop_id]['partial_paid'] || $partialPaid;
			}
		}

		return $map;
	}

	/**
	 * Get all prescriptions for a specific patient visit (IOP).
	 * Uses pharmacy_billing_queue as SINGLE SOURCE OF TRUTH for payment status.
	 */
	public function get_patient_prescriptions($iop_id){
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return array();

		// Base select - medication and drug info
		// Use COALESCE to get drug name from available columns (medicine_name doesn't exist)
		$selectBase = "M.iop_med_id, M.iop_id, M.medicine_id, M.dDate, M.instruction, M.advice, M.days, M.total_qty,
			COALESCE(NULLIF(D.drug_name,''), NULLIF(D.medicine_text,''), CONCAT('Drug #', M.medicine_id)) as drug_name,
			IFNULL(D.medicine_text,'') as medicine_text, 
			D.nStock as current_stock, D.re_order_level,
			D.nPrice, D.is_nhis_covered, D.nhis_price,
			IFNULL(M.frequency,'') as frequency";

		// Dispensing status from iop_medication
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$selectBase .= ", IFNULL(M.dispensing_status,'PENDING') as dispensing_status";
		} else {
			$selectBase .= ", 'PENDING' as dispensing_status";
		}

		// Prescription status (verification workflow)
		if ($this->column_exists('iop_medication', 'prescription_status')) {
			$selectBase .= ", IFNULL(M.prescription_status,'PENDING') as prescription_status";
		} else {
			$selectBase .= ", 'VERIFIED' as prescription_status";
		}

		// Extended status from iop_medication (for waiver, deferred, etc.)
		if ($this->column_exists('iop_medication', 'extended_status')) {
			$selectBase .= ", IFNULL(M.extended_status,'') as extended_status";
		} else {
			$selectBase .= ", '' as extended_status";
		}

		// Payment status from pharmacy_billing_queue (SINGLE SOURCE OF TRUTH)
		$hasBillingQueue = $this->table_exists('pharmacy_billing_queue');
		if ($hasBillingQueue) {
			$selectBase .= ", IFNULL(PBQ.payment_status,'PENDING') as payment_status";
			$selectBase .= ", IFNULL(PBQ.extended_status,'') as billing_extended_status";
		} else {
			// Fallback to iop_medication if billing queue doesn't exist
			if ($this->column_exists('iop_medication', 'payment_status')) {
				$selectBase .= ", IFNULL(M.payment_status,'PENDING') as payment_status";
			} else {
				$selectBase .= ", 'PENDING' as payment_status";
			}
			$selectBase .= ", '' as billing_extended_status";
		}

		/* Phase 4 columns â€” guarded selects */
		$selectBase .= $this->column_exists('iop_medication', 'prescription_no') ? ", IFNULL(M.prescription_no,'') as prescription_no" : ", '' as prescription_no";
		$selectBase .= $this->column_exists('iop_medication', 'dosage')          ? ", IFNULL(M.dosage,'') as dosage"                   : ", '' as dosage";
		$selectBase .= $this->column_exists('iop_medication', 'unit')            ? ", IFNULL(M.unit,'') as unit"                       : ", '' as unit";
		$selectBase .= $this->column_exists('iop_medication', 'freq_code')       ? ", IFNULL(M.freq_code,'') as freq_code"             : ", '' as freq_code";
		$selectBase .= $this->column_exists('iop_medication', 'route')           ? ", IFNULL(M.route,'') as route"                     : ", '' as route";
		$selectBase .= $this->column_exists('iop_medication', 'drug_form')       ? ", IFNULL(M.drug_form,'') as drug_form"             : ", '' as drug_form";
		$selectBase .= $this->column_exists('iop_medication', 'is_urgent')       ? ", IFNULL(M.is_urgent,0) as is_urgent"              : ", 0 as is_urgent";
		$selectBase .= $this->column_exists('iop_medication', 'is_prn')          ? ", IFNULL(M.is_prn,0) as is_prn"                   : ", 0 as is_prn";
		$selectBase .= $this->column_exists('iop_medication', 'doctor_id')       ? ", COALESCE(M.doctor_id, M.prescribed_by, '') as doctor_id" : ", IFNULL(M.prescribed_by,'') as doctor_id";

		$this->db->select($selectBase, false);
		$this->db->from('iop_medication M');
		$this->db->join('medicine_drug_name D', 'D.drug_id = M.medicine_id', 'left');
		if ($hasBillingQueue) {
			$this->db->join('pharmacy_billing_queue PBQ', 'PBQ.iop_med_id = M.iop_med_id AND PBQ.InActive = 0', 'left');
		}
		$this->db->where('M.iop_id', $iop_id);
		$this->db->where('M.InActive', 0);
		$this->db->order_by('M.dDate', 'ASC');

		$rows = $this->db->get()->result();

		$visitClearedAt = '';
		$clearTs = 0;
		if ($this->table_exists('iop_clearance_workflow')) {
			$this->load->model('app/billing_model');
			$wf = $this->billing_model->get_clearance_workflow($iop_id);
			if ($wf && isset($wf->medication_cleared_at)) {
				$visitClearedAt = trim((string)$wf->medication_cleared_at);
				if ($visitClearedAt !== '' && $visitClearedAt !== '0000-00-00 00:00:00') {
					$clearTs = strtotime($visitClearedAt);
				}
			}
		}
		if ($clearTs) {
			$lastRxTs = 0;
			foreach ($rows as $_r0) {
				$ts = isset($_r0->dDate) ? strtotime((string)$_r0->dDate) : 0;
				if ($ts && $ts > $lastRxTs) $lastRxTs = $ts;
			}
			// If there is a newer prescription after clearance, treat clearance as invalidated for dispensing purposes.
			if ($lastRxTs && $lastRxTs > $clearTs) {
				$clearTs = 0;
			}
		}

		// Get dispensed quantities from iop_medication_administration (SOURCE OF TRUTH for dispensing)
		$ids = array();
		foreach ($rows as $r) $ids[] = (int)$r->iop_med_id;
		$disp = $this->get_med_dispense_map($ids);
		$adjMap = array();
		if (count($ids) > 0 && $this->table_exists('pharmacy_prescription_adjustment')) {
			$this->db->where_in('iop_med_id', $ids);
			$this->db->where(array('active' => 1, 'InActive' => 0));
			$adjs = $this->db->get('pharmacy_prescription_adjustment')->result();
			foreach ($adjs as $_adj) {
				if (isset($_adj->iop_med_id)) $adjMap[(string)(int)$_adj->iop_med_id] = $_adj;
			}
		}

		$txnMap = array();
		if (count($ids) > 0 && $this->db->table_exists('billing_transactions')) {
			$itemRefs = array();
			foreach ($ids as $_mid) {
				$itemRefs[] = 'iop_med_id:' . (int)$_mid;
			}
			$this->db->select('item_ref, payer_type, payment_status, net_amount, paid_amount, balance_amount, quantity');
			$this->db->from('billing_transactions');
			$this->db->where('InActive', 0);
			$this->db->where('department', 'PHARMACY');
			$this->db->where_in('item_ref', $itemRefs);
			$txns = $this->db->get()->result();
			if ($txns) {
				foreach ($txns as $_t) {
					if (isset($_t->item_ref)) {
						$txnMap[(string)$_t->item_ref] = $_t;
					}
				}
			}
		}

		foreach ($rows as $r) {
			$mid = (string)$r->iop_med_id;
			$r->prescribed_total_qty = (float)$r->total_qty;
			$r->prescribed_days = (int)$r->days;
			$r->prescribed_dosage = (string)$r->dosage;
			$r->prescribed_frequency = (string)$r->frequency;
			$r->has_pharmacy_adjustment = isset($adjMap[$mid]) ? 1 : 0;
			$r->pharmacy_adjustment_reason = '';
			$r->pharmacy_adjusted_by = '';
			$r->pharmacy_adjusted_at = '';
			$r->billable_qty = (float)$r->total_qty;
			if (isset($adjMap[$mid])) {
				$_adj = $adjMap[$mid];
				$r->has_pharmacy_adjustment = 1;
				$r->approved_qty = isset($_adj->approved_qty) && (float)$_adj->approved_qty > 0 ? (float)$_adj->approved_qty : (float)$r->total_qty;
				$r->billable_qty = isset($_adj->billable_qty) && (float)$_adj->billable_qty > 0 ? (float)$_adj->billable_qty : (float)$r->approved_qty;
				$r->adjusted_dosage = isset($_adj->adjusted_dosage) ? (string)$_adj->adjusted_dosage : (string)$r->dosage;
				$r->adjusted_frequency = isset($_adj->adjusted_frequency) ? (string)$_adj->adjusted_frequency : (string)$r->frequency;
				$r->adjusted_freq_code = isset($_adj->adjusted_freq_code) ? (string)$_adj->adjusted_freq_code : (string)$r->freq_code;
				$r->adjusted_days = isset($_adj->adjusted_days) ? (int)$_adj->adjusted_days : (int)$r->days;
				$r->pharmacy_adjustment_reason = isset($_adj->adjustment_reason) ? (string)$_adj->adjustment_reason : '';
				$r->pharmacy_adjusted_by = isset($_adj->adjusted_by) ? (string)$_adj->adjusted_by : '';
				$r->pharmacy_adjusted_at = isset($_adj->adjusted_at) ? (string)$_adj->adjusted_at : '';
				$r->total_qty = $r->approved_qty;
				$r->days = $r->adjusted_days;
				$r->dosage = $r->adjusted_dosage;
				$r->frequency = $r->adjusted_frequency;
				$r->freq_code = $r->adjusted_freq_code;
			} else {
				$r->approved_qty = (float)$r->total_qty;
				$r->billable_qty = (float)$r->total_qty;
				$r->adjusted_dosage = (string)$r->dosage;
				$r->adjusted_frequency = (string)$r->frequency;
				$r->adjusted_freq_code = (string)$r->freq_code;
				$r->adjusted_days = (int)$r->days;
			}
			$d = isset($disp[$mid]) ? $disp[$mid] : array('dispensed_qty' => 0.0);
			$r->dispensed_qty = (float)$d['dispensed_qty'];
			$r->remaining_qty = max(0, (float)$r->total_qty - $r->dispensed_qty);
			$r->paid_qty_total = 0.0;
			$r->paid_remaining_qty = 0.0;

			// Stock status
			$r->stock_low = ((float)$r->current_stock <= (float)$r->re_order_level);
			$r->out_of_stock = ((float)$r->current_stock <= 0);

			// Normalize payment status (from billing queue - single source of truth)
			$payStatus = strtoupper(trim((string)$r->payment_status));
			
			// Merge extended status from both sources (billing queue takes precedence)
			$extStatus = strtoupper(trim((string)$r->extended_status));
			$billingExtStatus = isset($r->billing_extended_status) ? strtoupper(trim((string)$r->billing_extended_status)) : '';
			if ($billingExtStatus !== '') {
				$extStatus = $billingExtStatus;
			}
			$r->extended_status = $extStatus;

			$rxStatus = strtoupper(trim((string)$r->prescription_status));
			if ($rxStatus === '') $rxStatus = 'PENDING';
			$r->prescription_status = $rxStatus;
			$isVerified = ($rxStatus === 'VERIFIED');

			$itemRef = 'iop_med_id:' . (int)$r->iop_med_id;
			if (isset($txnMap[$itemRef])) {
				$txn = $txnMap[$itemRef];
				$payer = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : 'CASH';
				$pay = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
				$net = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
				$paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
				$bal = isset($txn->balance_amount) ? (float)$txn->balance_amount : max(0.0, $net - $paid);
				$prescribedQty = (float)$r->total_qty;
				if ($prescribedQty < 0) $prescribedQty = 0.0;

				if ($prescribedQty > 0) {
					if ($payer === 'NHIS' || $pay === 'NHIS') {
						$r->paid_qty_total = $prescribedQty;
					} elseif ($payer !== '' && $payer !== 'CASH') {
						$r->paid_qty_total = $prescribedQty;
					} elseif (in_array($pay, array('PAID', 'WAIVED'), true) || $bal <= 0.0001) {
						$r->paid_qty_total = $prescribedQty;
					} elseif ($pay === 'PARTIAL') {
						if ($net <= 0.0001) {
							$r->paid_qty_total = $prescribedQty;
						} else {
							$f = $paid / $net;
							if ($f < 0) $f = 0;
							if ($f > 1) $f = 1;
							$q = $prescribedQty * $f;
							$q = floor($q * 100.0) / 100.0;
							if ($q > $prescribedQty) $q = $prescribedQty;
							$r->paid_qty_total = $q;
						}
					} else {
						$r->paid_qty_total = 0.0;
					}
				}
			}

			// Exception statuses that bypass normal payment requirement
			$exceptionStatuses = array('EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','WAIVER_REQUESTED','WAIVER_APPROVED');
			$r->is_exception = in_array($extStatus, $exceptionStatuses);

			// If waived/waiver_approved, treat payment as cleared
			if (in_array($extStatus, array('WAIVED', 'WAIVER_APPROVED'))) {
				$payStatus = 'WAIVED';
				$r->payment_status = 'WAIVED';
			}

			// Fallback when no billing_transactions exists: treat PBQ payment/exception as full coverage
			if ((float)$r->paid_qty_total <= 0.0001) {
				if ($payStatus === 'PAID' || $payStatus === 'WAIVED' || $r->is_exception) {
					$r->paid_qty_total = max(0.0, (float)$r->total_qty);
				}
			}
			$r->paid_remaining_qty = (float)$r->paid_qty_total - (float)$r->dispensed_qty;
			if ($r->paid_remaining_qty < 0) $r->paid_remaining_qty = 0.0;

			// Determine if dispensing is allowed
			$r->can_dispense = $isVerified && !$r->out_of_stock && $r->remaining_qty > 0 && ($r->is_exception || $r->paid_remaining_qty > 0.0001);
			if ($clearTs) {
				$rxTs = isset($r->dDate) ? strtotime((string)$r->dDate) : 0;
				if ($rxTs && $rxTs <= $clearTs) {
					$r->can_dispense = false;
				}
			}

			// Determine dispensing status from actual dispense records (SOURCE OF TRUTH)
			$dispStatus = strtoupper(trim((string)$r->dispensing_status));
			
			// Override dispensing_status based on actual dispensed quantities
			if ($r->dispensed_qty >= $r->total_qty && $r->total_qty > 0) {
				$dispStatus = 'DISPENSED';
			} elseif ($r->dispensed_qty > 0 && $r->dispensed_qty < $r->total_qty) {
				$dispStatus = 'PARTIAL';
			}

			// Status label for display - check EXTERNAL status FIRST before dispensed check
			// because EXTERNAL items should show as EXTERNAL even if remaining_qty > 0
			$isExternalStatus = in_array($extStatus, array('EXTERNAL_PURCHASE', 'EXTERNAL')) || $dispStatus === 'EXTERNAL';
			$isCancelled = ($rxStatus === 'CANCELLED' || $extStatus === 'CANCELLED');
			$isOnHold = ($rxStatus === 'ON_HOLD');
			
			if ($isCancelled) {
				$r->status_label = 'CANCELLED';
				$r->status_class = 'default';
				$r->remaining_qty = 0;
				$r->can_dispense = false;
			} elseif ($isOnHold) {
				$r->status_label = 'ON_HOLD';
				$r->status_class = 'default';
				$r->can_dispense = false;
			} elseif ($isExternalStatus) {
				$r->status_label = 'EXTERNAL';
				$r->status_class = 'info';
				$r->remaining_qty = 0; // Mark as resolved for clearance purposes
			} elseif ($r->dispensed_qty >= $r->total_qty && $r->total_qty > 0) {
				$r->status_label = 'DISPENSED';
				$r->status_class = 'success';
			} elseif ($dispStatus === 'PARTIAL' || ($r->dispensed_qty > 0 && $r->dispensed_qty < $r->total_qty)) {
				$r->status_label = 'PARTIAL';
				$r->status_class = 'warning';
			} elseif ($dispStatus === 'UNAVAILABLE' || $extStatus === 'UNAVAILABLE') {
				$r->status_label = 'UNAVAILABLE';
				$r->status_class = 'danger';
			} elseif (in_array($extStatus, array('WAIVED', 'WAIVER_APPROVED'))) {
				$r->status_label = 'WAIVED';
				$r->status_class = 'success';
				$r->remaining_qty = 0; // Mark as resolved for clearance purposes
			} elseif ($extStatus === 'UNABLE_TO_PAY') {
				$r->status_label = 'UNABLE_TO_PAY';
				$r->status_class = 'warning';
				$r->remaining_qty = 0; // Mark as resolved for clearance purposes
			} else {
				$r->status_label = 'PENDING';
				$r->status_class = 'default';
			}
		}

		return $rows;
	}

	/**
	 * Get patient info for pharmacy detail page.
	 */
	public function get_patient_pharmacy_info($iop_id){
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return null;

		$this->db->select("I.IO_ID as iop_id, I.patient_no, I.date_visit, I.patient_type,
			CONCAT_WS(' ', T.cValue, P.firstname, P.middlename, P.lastname) as patient_name,
			P.Insurance_comp, P.insurance_no, P.mobile_no, P.birthday", false);
		$this->db->from('patient_details_iop I');
		$this->db->join('patient_personal_info P', 'P.patient_no = I.patient_no', 'left');
		$this->db->join('system_parameters T', 'T.param_id = P.title', 'left');
		$this->db->where('I.IO_ID', $iop_id);
		$row = $this->db->get()->row();

		if ($row) {
			$ins = isset($row->Insurance_comp) ? strtoupper(trim((string)$row->Insurance_comp)) : '';
			$row->payer_type = (strpos($ins, 'NHIS') !== false) ? 'NHIS' : 'CASH';
		}

		return $row;
	}

	/**
	 * Bulk dispense all remaining items for a patient (for quick dispense).
	 */
	public function bulk_dispense_patient($iop_id, $user_id){
		$prescriptions = $this->get_patient_prescriptions($iop_id);
		$results = array('success' => 0, 'failed' => 0, 'errors' => array());

		foreach ($prescriptions as $rx) {
			if (!$rx->can_dispense || $rx->remaining_qty <= 0) continue;

			$cap = isset($rx->paid_remaining_qty) ? (float)$rx->paid_remaining_qty : (float)$rx->remaining_qty;
			$qty = min($cap, (float)$rx->remaining_qty, (float)$rx->current_stock);
			if ($qty <= 0) continue;

			$result = $this->dispense_medication($rx->iop_med_id, $qty, 'DISPENSED', 'Bulk dispense', $user_id, '');
			if ($result['ok']) {
				$results['success']++;
			} else {
				$results['failed']++;
				$results['errors'][] = $rx->drug_name . ': ' . implode(', ', $result['errors']);
			}
		}

		return $results;
	}

	/**
	 * Mark all prescriptions for a patient as cleared (medication clearance).
	 * Uses pharmacy_billing_queue as SINGLE SOURCE OF TRUTH for status.
	 */
	public function patient_medication_clearance($iop_id, $patient_no, $user_id){
		// Ensure billing queue entries exist and are synced
		$this->ensure_billing_queue_for_visit($iop_id, $user_id);
		
		$this->load->model('app/billing_model');
		$wf = $this->billing_model->get_clearance_workflow($iop_id);
		if ($wf && isset($wf->medication_cleared_at) && trim((string)$wf->medication_cleared_at) !== '' && (string)$wf->medication_cleared_at !== '0000-00-00 00:00:00') {
			return array('ok' => true, 'error' => '', 'already_cleared' => true);
		}
		
		$prescriptions = $this->get_patient_prescriptions($iop_id);
		$allCleared = true;
		$issues = array();

		// Statuses that count as "resolved" for clearance purposes
		$resolvedDispenseStatuses = array('DISPENSED', 'EXTERNAL');
		$resolvedExtendedStatuses = array('EXTERNAL_PURCHASE', 'EXTERNAL', 'WAIVED', 'WAIVER_APPROVED', 'UNAVAILABLE', 'CANCELLED', 'UNABLE_TO_PAY');

		foreach ($prescriptions as $rx) {
			$dispStatus = strtoupper(trim((string)$rx->dispensing_status));
			$extStatus = strtoupper(trim((string)$rx->extended_status));
			$statusLabel = strtoupper(trim((string)$rx->status_label));

			// Check if item is resolved via:
			// 1. Fully dispensed (dispensed_qty >= total_qty)
			// 2. Dispensing status is DISPENSED or EXTERNAL
			// 3. Extended status indicates resolution (external purchase, waived, etc.)
			// 4. Status label is EXTERNAL or DISPENSED
			$isFullyDispensed = ($rx->dispensed_qty >= $rx->total_qty && $rx->total_qty > 0);
			$isDispenseResolved = in_array($dispStatus, $resolvedDispenseStatuses);
			$isExtendedResolved = in_array($extStatus, $resolvedExtendedStatuses);
			$isLabelResolved = in_array($statusLabel, array('DISPENSED', 'EXTERNAL'));

			$resolved = $isFullyDispensed || $isDispenseResolved || $isExtendedResolved || $isLabelResolved;

			if (!$resolved && $rx->remaining_qty > 0) {
				$allCleared = false;
				$drugName = !empty($rx->drug_name) ? $rx->drug_name : (!empty($rx->medicine_text) ? $rx->medicine_text : 'Unknown drug');
				$issues[] = $drugName . ' (remaining: ' . (int)$rx->remaining_qty . ')';
			}
		}

		if (!$allCleared) {
			return array('ok' => false, 'error' => 'Not all medications resolved: ' . implode(', ', $issues));
		}

		// Record clearance in workflow table
		$this->billing_model->upsert_clearance_stage($iop_id, 'MEDICATION', $patient_no, $user_id);

		// Log audit
		$this->log_pharmacy_audit(0, $iop_id, $patient_no, 'MEDICATION_CLEARANCE', null, 'CLEARED', 'All medications cleared for patient', $user_id);

		return array('ok' => true, 'error' => '', 'already_cleared' => false);
	}

	/* â”€â”€ Internal helpers â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	private function _get_patient_no_for_iop($iop_id){
		$iop_id = (string)$iop_id;
		if ($iop_id === '' || !$this->table_exists('patient_details_iop')) return '';
		$r = $this->db->select('patient_no')->get_where('patient_details_iop', array('IO_ID' => $iop_id))->row();
		return $r ? (string)$r->patient_no : '';
	}

	private function _log_financial_audit($module, $source_id, $patient_no, $iop_id, $event, $old, $new, $amount, $notes, $user_id){
		if (!$this->table_exists('financial_audit_log')) return;
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

	// ==================== UNIFIED BILLING TRANSACTION INTEGRATION ====================

	/**
	 * Sync medication to unified billing_transactions table.
	 * This ensures single source of truth for all billing/payment status.
	 */
	public function sync_medication_to_billing_transactions($iop_med_id, $user_id = null){
		$this->load->model('app/billing_transaction_model');
		return $this->billing_transaction_model->sync_pharmacy_medication($iop_med_id, $user_id);
	}

	/**
	 * Get payment status from unified billing_transactions (SINGLE SOURCE OF TRUTH).
	 */
	public function get_unified_payment_status($iop_med_id){
		$this->load->model('app/billing_transaction_model');
		$txn = $this->billing_transaction_model->get_transaction_by_item_ref('iop_med_id:' . $iop_med_id, 'PHARMACY');
		if (!$txn) {
			return array('found' => false, 'payment_status' => 'PENDING', 'order_status' => 'ORDERED');
		}
		return array(
			'found'          => true,
			'txn_id'         => $txn->txn_id,
			'payment_status' => $txn->payment_status,
			'order_status'   => $txn->order_status,
			'paid_amount'    => (float)$txn->paid_amount,
			'balance_amount' => (float)$txn->balance_amount,
			'payer_type'     => $txn->payer_type
		);
	}

	/**
	 * Update order status in unified billing_transactions when dispensing.
	 */
	public function update_unified_order_status($iop_med_id, $new_status, $user_id = null){
		$this->load->model('app/billing_transaction_model');
		$txn = $this->billing_transaction_model->get_transaction_by_item_ref('iop_med_id:' . $iop_med_id, 'PHARMACY');
		if (!$txn) {
			// Auto-sync first
			$sync = $this->sync_medication_to_billing_transactions($iop_med_id, $user_id);
			if (!$sync['ok']) {
				return array('ok' => false, 'error' => 'Failed to sync medication');
			}
			$txn = $this->billing_transaction_model->get_transaction($sync['txn_id']);
		}
		if (!$txn) {
			return array('ok' => false, 'error' => 'Transaction not found');
		}
		return $this->billing_transaction_model->update_order_status($txn->txn_id, $new_status, $user_id);
	}

	/**
	 * Check if medication can be dispensed based on unified payment status.
	 * NHIS patients are auto-cleared.
	 */
	public function check_unified_payment_gate($iop_med_id){
		$status = $this->get_unified_payment_status($iop_med_id);
		
		// If not in unified system, check legacy
		if (!$status['found']) {
			return $this->check_ghs_payment_gate($iop_med_id);
		}
		
		$payStatus = strtoupper($status['payment_status']);
		$payerType = strtoupper($status['payer_type']);
		
		// NHIS patients are auto-cleared
		if ($payerType === 'NHIS' || $payStatus === 'NHIS') {
			return array('paid' => true, 'reason' => 'NHIS covered');
		}
		
		// Check if paid or waived
		if (in_array($payStatus, array('PAID', 'WAIVED'))) {
			return array('paid' => true, 'reason' => 'Payment cleared');
		}
		
		// Partial payment - allow dispensing
		if ($payStatus === 'PARTIAL') {
			return array('paid' => true, 'reason' => 'Partial payment received');
		}
		
		return array('paid' => false, 'reason' => 'Payment required. Balance: ' . number_format($status['balance_amount'], 2));
	}

	/**
	 * Get worklist with unified billing status (SINGLE SOURCE OF TRUTH).
	 */
	public function get_unified_patient_worklist($filters = array()){
		// Get base worklist
		$rows = $this->get_patient_worklist($filters);
		
		if (empty($rows)) {
			return $rows;
		}
		
		// Load billing transaction model
		$this->load->model('app/billing_transaction_model');
		
		// Get all encounter IDs
		$encounter_ids = array();
		foreach ($rows as $r) {
			$encounter_ids[] = $r->iop_id;
		}
		
		// Build unified status map
		$statusMap = array();
		foreach ($encounter_ids as $enc_id) {
			$txns = $this->billing_transaction_model->get_encounter_transactions($enc_id, 'PHARMACY');
			if (!empty($txns)) {
				$total = count($txns);
				$paid = 0;
				$dispensed = 0;
				$completed = 0;
				
				foreach ($txns as $txn) {
					if (in_array($txn->payment_status, array('PAID', 'NHIS', 'WAIVED'))) $paid++;
					if ($txn->order_status === 'DISPENSED') $dispensed++;
					if ($txn->order_status === 'COMPLETED') $completed++;
				}
				
				$statusMap[$enc_id] = array(
					'total'     => $total,
					'paid'      => $paid,
					'dispensed' => $dispensed,
					'completed' => $completed
				);
			}
		}
		
		// Update rows with unified status
		foreach ($rows as $r) {
			if (isset($statusMap[$r->iop_id])) {
				$s = $statusMap[$r->iop_id];
				
				// Override payment status from unified source
				if ($s['paid'] >= $s['total']) {
					$r->payment_status = 'CLEARED';
					$r->payment_class = 'success';
				} elseif ($s['paid'] > 0) {
					$r->payment_status = 'PARTIAL';
					$r->payment_class = 'warning';
				}
				
				// Override order status from unified source
				if ($s['completed'] >= $s['total']) {
					$r->overall_status = 'COMPLETED';
					$r->status_class = 'success';
				} elseif ($s['dispensed'] > 0 || $s['completed'] > 0) {
					$r->overall_status = 'IN_PROGRESS';
					$r->status_class = 'warning';
				} elseif ($s['paid'] >= $s['total']) {
					$r->overall_status = 'READY';
					$r->status_class = 'info';
				}
			}
		}
		
		return $rows;
	}

	/**
	 * Sync all medications for an encounter to billing_transactions.
	 */
	public function sync_encounter_medications($iop_id, $user_id = null){
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return array('ok' => false, 'error' => 'Invalid encounter ID');
		
		$meds = $this->db->get_where('iop_medication', array('iop_id' => $iop_id, 'InActive' => 0))->result();
		if (empty($meds)) {
			return array('ok' => true, 'synced' => 0);
		}
		
		$synced = 0;
		$errors = array();
		
		foreach ($meds as $med) {
			$result = $this->sync_medication_to_billing_transactions($med->iop_med_id, $user_id);
			if ($result['ok']) {
				$synced++;
			} else {
				$errors[] = 'Med #' . $med->iop_med_id . ': ' . (isset($result['error']) ? $result['error'] : 'Unknown error');
			}
		}
		
		return array('ok' => empty($errors), 'synced' => $synced, 'errors' => $errors);
	}

	/**
	 * Get encounter billing summary from unified system.
	 */
	public function get_encounter_billing_summary($iop_id){
		$this->load->model('app/billing_transaction_model');
		return $this->billing_transaction_model->get_encounter_summary($iop_id);
	}

	/**
	 * Run pharmacy reconciliation to detect discrepancies.
	 */
	public function run_pharmacy_reconciliation($date = null){
		$this->load->model('app/billing_transaction_model');
		return $this->billing_transaction_model->run_reconciliation('PHARMACY', $date);
	}

	/* =========================================================================
	 * Phase 6A â€” RX-Level Queue
	 * Returns one row per prescription line with all columns needed for the
	 * pharmacy queue UI (RX no, patient, doctor, drug, payer, urgency, status).
	 * ======================================================================= */
	public function get_rx_queue($filters = array()) {
		$search       = isset($filters['search'])     ? trim((string)$filters['search'])                 : '';
		$filter       = isset($filters['filter'])     ? strtoupper(trim((string)$filters['filter']))     : 'ALL';
		$date_from    = isset($filters['date_from'])  ? trim((string)$filters['date_from'])              : date('Y-m-d');
		$date_to      = isset($filters['date_to'])    ? trim((string)$filters['date_to'])                : date('Y-m-d');
		$limit        = isset($filters['limit'])      ? min((int)$filters['limit'], 500)                 : 200;
		$offset       = isset($filters['offset'])     ? (int)$filters['offset']                          : 0;
		if ($limit <= 0) $limit = 200;

		$hasPrescNo  = $this->db->field_exists('prescription_no', 'iop_medication');
		$hasIsUrgent = $this->db->field_exists('is_urgent',       'iop_medication');
		$hasIsPrn    = $this->db->field_exists('is_prn',          'iop_medication');
		$hasFreqCode = $this->db->field_exists('freq_code',       'iop_medication');
		$hasUnit     = $this->db->field_exists('unit',            'iop_medication');
		$hasDoctorId = $this->db->field_exists('doctor_id',       'iop_medication');
		$hasIopDept  = $this->db->field_exists('department_id',   'patient_details_iop');

		$rxNo     = $hasPrescNo  ? 'M.prescription_no'            : "'' ";
		$isUrgent = $hasIsUrgent ? 'IFNULL(M.is_urgent, 0)'       : '0';
		$isPrn    = $hasIsPrn    ? 'IFNULL(M.is_prn, 0)'          : '0';
		$freqCode = $hasFreqCode ? 'IFNULL(M.freq_code, M.frequency)' : 'M.frequency';
		$unitCol  = $hasUnit     ? 'IFNULL(M.unit, \'\')'         : "''";
		$doctorId = $hasDoctorId ? 'COALESCE(M.doctor_id, M.prescribed_by, \'\')' : 'IFNULL(M.prescribed_by, \'\')';

		$deptJoin       = $hasIopDept
			? "LEFT JOIN  department DEP         ON DEP.department_id = I.department_id"
			: "";
		$deptNameSelect = $hasIopDept
			? "IFNULL(DEP.dept_name, IFNULL(I.visit_type, 'OPD'))"
			: "IFNULL(I.visit_type, 'OPD')";

		$sql = "SELECT
			M.iop_med_id,
			{$rxNo}           AS prescription_no,
			M.iop_id,
			M.medicine_id,
			D.drug_name,
			D.generic_name,
			D.strength,
			D.dosage_form,
			IFNULL(M.dosage, '')                  AS dosage,
			{$unitCol}                             AS unit,
			IFNULL(M.route, '')                   AS route,
			IFNULL(M.drug_form, '')               AS drug_form,
			{$freqCode}                            AS freq_code,
			IFNULL(M.frequency, '')               AS frequency_label,
			IFNULL(M.days, 0)                     AS days,
			IFNULL(M.total_qty, 0)                AS total_qty,
			{$isUrgent}                            AS is_urgent,
			{$isPrn}                               AS is_prn,
			IFNULL(M.dispensing_status, 'PENDING') AS dispensing_status,
			IFNULL(M.is_nhis_covered, 0)           AS is_nhis_covered,
			M.dDate                               AS prescribed_at,
			{$doctorId}                            AS doctor_id,
			TRIM(CONCAT_WS(' ', NULLIF(U.firstname,''), NULLIF(U.middlename,''), NULLIF(U.lastname,''))) AS doctor_name,
			CONCAT_WS(' ', T.cValue, P.firstname, P.middlename, P.lastname) AS patient_name,
			P.patient_no,
			IFNULL(P.Insurance_comp, '')           AS insurance_comp,
			I.date_visit,
			IFNULL(I.visit_type, 'OPD')            AS visit_type,
			IFNULL(D.nStock, 0)                    AS stock_qty,
			IFNULL(D.re_order_level, 0)            AS reorder_level,
			{$deptNameSelect}                      AS department_name,
			IFNULL(M.payment_status, 'PENDING')    AS payment_status
		FROM iop_medication M
		INNER JOIN patient_details_iop I   ON I.IO_ID    = M.iop_id
		INNER JOIN patient_personal_info P ON P.patient_no = I.patient_no
		LEFT JOIN  system_parameters T     ON T.param_id  = P.title
		LEFT JOIN  medicine_drug_name D    ON D.drug_id   = M.medicine_id
		LEFT JOIN  users U                 ON U.user_id   = {$doctorId}
		{$deptJoin}
		WHERE M.InActive = 0
		  AND I.InActive = 0
		  AND DATE(M.dDate) BETWEEN " . $this->db->escape($date_from) . " AND " . $this->db->escape($date_to);

		/* Filter by tab */
		if ($filter === 'PENDING') {
			$sql .= " AND IFNULL(M.dispensing_status,'PENDING') IN ('PENDING','')";
		} elseif ($filter === 'DISPENSED') {
			$sql .= " AND IFNULL(M.dispensing_status,'PENDING') = 'DISPENSED'";
		} elseif ($filter === 'NHIS') {
			$sql .= " AND IFNULL(M.is_nhis_covered, 0) = 1";
		} elseif ($filter === 'CASH') {
			$sql .= " AND IFNULL(M.is_nhis_covered, 0) = 0";
		} elseif ($filter === 'URGENT') {
			$sql .= $hasIsUrgent ? " AND M.is_urgent = 1" : " AND 0=1";
		} elseif ($filter === 'PRN') {
			$sql .= $hasIsPrn ? " AND M.is_prn = 1" : " AND 0=1";
		}

		/* Search */
		if ($search !== '') {
			$esc = $this->db->escape('%' . $search . '%');
			$rxCond = $hasPrescNo ? " OR M.prescription_no LIKE {$esc}" : '';
			$sql .= " AND (P.firstname LIKE {$esc}
					 OR P.lastname   LIKE {$esc}
					 OR P.patient_no LIKE {$esc}
					 OR M.iop_id     LIKE {$esc}
					 OR D.drug_name  LIKE {$esc}
					 OR M.prescribed_by LIKE {$esc}
					 {$rxCond})";
		}

		/* Sort: urgent first â†’ STAT â†’ PRN â†’ time */
		$urgentSort = $hasIsUrgent ? "M.is_urgent DESC, " : '';
		$statSort   = $hasFreqCode ? "IF(UPPER(IFNULL(M.freq_code,''))='STAT',0,1) ASC, " : '';
		$prnSort    = $hasIsPrn    ? "M.is_prn DESC, "   : '';
		$sql .= " ORDER BY FIELD(IFNULL(M.dispensing_status,'PENDING'),'PENDING','PARTIAL','DISPENSED'),
		         {$urgentSort}{$statSort}{$prnSort}M.dDate ASC
		         LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

		$q    = $this->db->query($sql);
		$rows = $q ? $q->result() : array();

		/* Enrich each row */
		foreach ($rows as $r) {
			/* Payer type */
			$ins = strtoupper(trim((string)$r->insurance_comp));
			$r->payer_type = ($r->is_nhis_covered || strpos($ins, 'NHIS') !== false) ? 'NHIS' : 'CASH';

			/* Priority label */
			if ($r->is_urgent) {
				$r->priority       = 'URGENT';
				$r->priority_class = 'danger';
			} elseif (strtoupper($r->freq_code) === 'STAT') {
				$r->priority       = 'STAT';
				$r->priority_class = 'warning';
			} elseif ($r->is_prn) {
				$r->priority       = 'PRN';
				$r->priority_class = 'info';
			} else {
				$r->priority       = 'ROUTINE';
				$r->priority_class = 'default';
			}

			/* Stock alert */
			$stock = (float)$r->stock_qty;
			$reord = (float)$r->reorder_level;
			$need  = (float)$r->total_qty;
			if ($stock <= 0) {
				$r->stock_alert = 'OUT';
			} elseif ($stock < $need) {
				$r->stock_alert = 'LOW';
			} elseif ($reord > 0 && $stock <= $reord) {
				$r->stock_alert = 'REORDER';
			} else {
				$r->stock_alert = 'OK';
			}
		}

		return $rows;
	}

	/* Count for pagination */
	public function count_rx_queue($filters = array()) {
		$search    = isset($filters['search'])    ? trim((string)$filters['search'])            : '';
		$filter    = isset($filters['filter'])    ? strtoupper(trim((string)$filters['filter'])): 'ALL';
		$date_from = isset($filters['date_from']) ? trim((string)$filters['date_from'])         : date('Y-m-d');
		$date_to   = isset($filters['date_to'])   ? trim((string)$filters['date_to'])           : date('Y-m-d');

		$hasIsUrgent = $this->db->field_exists('is_urgent',       'iop_medication');
		$hasIsPrn    = $this->db->field_exists('is_prn',          'iop_medication');

		$sql = "SELECT COUNT(*) AS cnt
		FROM iop_medication M
		INNER JOIN patient_details_iop I   ON I.IO_ID    = M.iop_id
		INNER JOIN patient_personal_info P ON P.patient_no = I.patient_no
		LEFT JOIN  medicine_drug_name D    ON D.drug_id   = M.medicine_id
		WHERE M.InActive = 0 AND I.InActive = 0
		  AND DATE(M.dDate) BETWEEN " . $this->db->escape($date_from) . " AND " . $this->db->escape($date_to);

		if ($filter === 'PENDING')   $sql .= " AND IFNULL(M.dispensing_status,'PENDING') IN ('PENDING','')";
		if ($filter === 'DISPENSED') $sql .= " AND IFNULL(M.dispensing_status,'PENDING') = 'DISPENSED'";
		if ($filter === 'NHIS')      $sql .= " AND IFNULL(M.is_nhis_covered, 0) = 1";
		if ($filter === 'CASH')      $sql .= " AND IFNULL(M.is_nhis_covered, 0) = 0";
		if ($filter === 'URGENT')    $sql .= $hasIsUrgent ? " AND M.is_urgent = 1"   : " AND 0=1";
		if ($filter === 'PRN')       $sql .= $hasIsPrn    ? " AND M.is_prn = 1"      : " AND 0=1";

		if ($search !== '') {
			$esc = $this->db->escape('%' . $search . '%');
			$sql .= " AND (P.firstname LIKE {$esc} OR P.lastname LIKE {$esc} OR P.patient_no LIKE {$esc} OR D.drug_name LIKE {$esc} OR M.prescribed_by LIKE {$esc})";
		}

		$q = $this->db->query($sql);
		return $q ? (int)$q->row()->cnt : 0;
	}

	/* â”€â”€ Company Pricing Integration â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€ */

	/**
	 * Get company-adjusted pharmacy price for a medicine
	 * Returns array with base_price, adjusted_price, and adjustment details
	 */
	public function get_company_pharmacy_price($medicine_id, $company_id = null)
	{
		$medicine_id = (int)$medicine_id;
		if ($medicine_id <= 0) {
			return array(
				'base_price' => 0,
				'adjusted_price' => 0,
				'percentage_applied' => 0,
				'difference' => 0,
				'company_id' => $company_id
			);
		}

		// Get base price from medicine_master or medicine_drug_name
		$base_price = 0;
		$medicine = $this->db->get_where('medicine_drug_name', array('drug_id' => $medicine_id, 'InActive' => 0))->row();
		if ($medicine) {
			// Use selling_price if available, otherwise nPrice
			$base_price = isset($medicine->selling_price) ? (float)$medicine->selling_price : (float)$medicine->nPrice;
		}

		// Apply company pricing if applicable
		if (empty($company_id) || empty($base_price)) {
			return array(
				'base_price' => $base_price,
				'adjusted_price' => $base_price,
				'percentage_applied' => 0.00,
				'difference' => 0.00,
				'company_id' => $company_id
			);
		}

		// Delegate pricing math to Price_engine_model (Single Source of Truth).
		$this->load->model('app/Price_engine_model', 'price_engine_model');
		$res = $this->price_engine_model->apply_company_pricing($base_price, (int)$company_id);
		return array(
			'base_price'         => $res['base_amount'],
			'adjusted_price'     => $res['adjusted_amount'],
			'percentage_applied' => $res['percentage_applied'],
			'difference'         => $res['difference'],
			'company_id'         => $company_id
		);
	}

	/**
	 * Get dispensed medications for billing with company pricing
	 * Extends get_dispensed_for_billing to include company pricing
	 */
	public function get_dispensed_for_billing_with_company_pricing($iop_id, $company_id = null)
	{
		$this->ensure_pharmacy_v2_schema();
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return array();

		$this->db->select("A.iop_med_id, A.medicine_id, A.medicine_text, A.total_qty, A.dispensing_status, B.drug_name, B.nPrice, B.selling_price");
		$this->db->from('iop_medication A');
		$this->db->join('medicine_drug_name B', 'B.drug_id = A.medicine_id', 'left');
		$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
		if ($this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->where("A.dispensing_status != 'UNAVAILABLE'", null, false);
		}

		$medications = $this->db->get()->result();
		$result = array();

		foreach ($medications as $med) {
			// Get base price (prefer selling_price over nPrice)
			$base_price = isset($med->selling_price) ? (float)$med->selling_price : (float)$med->nPrice;

			// Apply company pricing
			$pricing = $this->get_company_pharmacy_price($med->medicine_id, $company_id);

			$med->base_price = $pricing['base_price'];
			$med->adjusted_price = $pricing['adjusted_price'];
			$med->adjustment_percentage = $pricing['percentage_applied'];
			$med->adjustment_amount = $pricing['difference'];
			$med->company_id = $company_id;

			$result[] = $med;
		}

		return $result;
	}

	/**
	 * Apply company pricing to pharmacy billing items
	 * Used when creating billing items from pharmacy prescriptions
	 */
	public function apply_company_pricing_to_pharmacy_items($items, $company_id = null)
	{
		if (empty($items) || empty($company_id)) {
			return $items;
		}

		$result = array();
		foreach ($items as $item) {
			$medicine_id = isset($item['medicine_id']) ? $item['medicine_id'] : null;
			$quantity = isset($item['quantity']) ? $item['quantity'] : 1;

			// Get company pricing for this medicine
			$pricing = $this->get_company_pharmacy_price($medicine_id, $company_id);

			// Update item with pricing information
			$item['base_price'] = $pricing['base_price'];
			$item['unit_price'] = $pricing['adjusted_price'];
			$item['adjustment_percentage'] = $pricing['percentage_applied'];
			$item['adjustment_amount'] = $pricing['difference'];
			$item['company_id'] = $company_id;

			// Recalculate amounts
			$item['gross_amount'] = $quantity * $pricing['adjusted_price'];
			$item['net_amount'] = $item['gross_amount'] - (isset($item['discount_amount']) ? $item['discount_amount'] : 0);

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Get pharmacy pricing summary for a company
	 * Returns statistics about pharmacy pricing adjustments
	 */
	public function get_pharmacy_pricing_summary($company_id, $from_date, $to_date)
	{
		$company_id = (int)$company_id;
		if ($company_id <= 0) return null;

		$sql = "
			SELECT 
				COUNT(*) as total_pharmacy_items,
				SUM(bi.base_price * bi.quantity) as base_revenue,
				SUM(bi.adjusted_price * bi.quantity) as adjusted_revenue,
				SUM((bi.adjusted_price - bi.base_price) * bi.quantity) as adjustment_amount,
				AVG(bi.adjustment_percentage) as avg_adjustment_pct
			FROM billing_items bi
			INNER JOIN billing_master bm ON bm.bill_id = bi.bill_id
			WHERE bi.InActive = 0
			AND bm.InActive = 0
			AND bi.company_id = ?
			AND bi.service_type = 'PHARMACY'
			AND DATE(bm.created_at) BETWEEN ? AND ?
		";

		$query = $this->db->query($sql, [$company_id, $from_date, $to_date]);
		return $query->row();
	}

	/**
	 * Check if pharmacy billing should use company pricing
	 * Returns company_id if patient has company cover, null otherwise
	 */
	public function should_use_company_pricing($patient_no)
	{
		if (empty($patient_no)) return null;

		$this->db->select('ppi.Insurance_comp, ic.pricing_percentage');
		$this->db->from('patient_personal_info ppi');
		$this->db->join('insurance_comp ic', 'ic.in_com_id = ppi.Insurance_comp', 'left');
		$this->db->where('ppi.patient_no', $patient_no);
		$this->db->where('ppi.InActive', 0);
		$this->db->where('(ic.pricing_percentage IS NOT NULL AND ic.pricing_percentage != 0)', null, false);

		$result = $this->db->get()->row();
		return ($result && isset($result->Insurance_comp)) ? (int)$result->Insurance_comp : null;
	}
}
