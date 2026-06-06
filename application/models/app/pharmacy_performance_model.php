<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pharmacy Performance Model
 * 
 * Handles performance optimization:
 * - Database indexes
 * - Query optimization
 * - Performance metrics
 * - Archiving operations
 * 
 * Part of Phase 4 Performance Optimization.
 */
class Pharmacy_performance_model extends CI_Model
{
	private static $_indexes_done = false;
	
	public function __construct()
	{
		parent::__construct();
	}
	
	// =========================================================================
	// DATABASE INDEXES
	// =========================================================================
	
	/**
	 * Ensure all performance indexes exist
	 */
	public function ensure_performance_indexes()
	{
		if (self::$_indexes_done) return;
		self::$_indexes_done = true;
		
		$old = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old !== null) $this->db->db_debug = false;
		
		// iop_medication indexes
		$this->_add_index('iop_medication', 'idx_iop_id', 'iop_id');
		$this->_add_index('iop_medication', 'idx_medicine_id', 'medicine_id');
		$this->_add_index('iop_medication', 'idx_dDate', 'dDate');
		$this->_add_index('iop_medication', 'idx_dispense_status', 'dispensing_status');
		$this->_add_index('iop_medication', 'idx_active_date', array('InActive', 'dDate'));
		$this->_add_index('iop_medication', 'idx_active_status', array('InActive', 'dispensing_status'));
		
		// iop_medication_administration indexes
		if ($this->_table_exists('iop_medication_administration')) {
			$this->_add_index('iop_medication_administration', 'idx_iop_med_id', 'iop_med_id');
			$this->_add_index('iop_medication_administration', 'idx_status', 'status');
			$this->_add_index('iop_medication_administration', 'idx_datetime', 'dDateTime');
			$this->_add_index('iop_medication_administration', 'idx_active_status', array('InActive', 'status'));
		}
		
		// medicine_drug_name indexes
		$this->_add_index('medicine_drug_name', 'idx_drug_name', 'drug_name');
		$this->_add_index('medicine_drug_name', 'idx_category', 'category_id');
		$this->_add_index('medicine_drug_name', 'idx_stock', 'nStock');
		$this->_add_index('medicine_drug_name', 'idx_active', 'InActive');
		$this->_add_index('medicine_drug_name', 'idx_active_stock', array('InActive', 'nStock'));
		
		// patient_details_iop indexes
		$this->_add_index('patient_details_iop', 'idx_patient_no', 'patient_no');
		$this->_add_index('patient_details_iop', 'idx_date_visit', 'date_visit');
		$this->_add_index('patient_details_iop', 'idx_active_date', array('InActive', 'date_visit'));
		
		// pharmacy_billing_queue indexes
		if ($this->_table_exists('pharmacy_billing_queue')) {
			$this->_add_index('pharmacy_billing_queue', 'idx_iop_med_id', 'iop_med_id');
			$this->_add_index('pharmacy_billing_queue', 'idx_iop_id', 'iop_id');
			$this->_add_index('pharmacy_billing_queue', 'idx_patient_no', 'patient_no');
			$this->_add_index('pharmacy_billing_queue', 'idx_payment_status', 'payment_status');
			$this->_add_index('pharmacy_billing_queue', 'idx_dispense_status', 'dispense_status');
			$this->_add_index('pharmacy_billing_queue', 'idx_created_at', 'created_at');
			$this->_add_index('pharmacy_billing_queue', 'idx_active_payment', array('InActive', 'payment_status'));
		}
		
		// medication_stock indexes
		if ($this->_table_exists('medication_stock')) {
			$this->_add_index('medication_stock', 'idx_medication_id', 'medication_id');
			$this->_add_index('medication_stock', 'idx_expiry_date', 'expiry_date');
			$this->_add_index('medication_stock', 'idx_status', 'status');
			$this->_add_index('medication_stock', 'idx_batch_active', array('medication_id', 'status', 'InActive'));
		}
		
		// pharmacy_stock_adjustment indexes
		if ($this->_table_exists('pharmacy_stock_adjustment')) {
			$this->_add_index('pharmacy_stock_adjustment', 'idx_drug_id', 'drug_id');
			$this->_add_index('pharmacy_stock_adjustment', 'idx_created_at', 'created_at');
			$this->_add_index('pharmacy_stock_adjustment', 'idx_ref', array('reference_type', 'reference_id'));
		}
		
		if ($old !== null) $this->db->db_debug = $old;
	}
	
	/**
	 * Add index if not exists
	 * Checks column existence before creating index
	 */
	private function _add_index($table, $index_name, $columns)
	{
		if (!$this->_table_exists($table)) return false;
		
		// Check if all columns exist first
		$cols_array = is_array($columns) ? $columns : array($columns);
		foreach ($cols_array as $col) {
			if (!$this->_column_exists($table, $col)) {
				return false; // Column doesn't exist, skip index creation
			}
		}
		
		// Check if index exists
		$q = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index_name));
		if ($q && $q->num_rows() > 0) return false;
		
		// Create index
		$cols = is_array($columns) ? implode('`, `', $columns) : $columns;
		$this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$cols}`)");
		
		return true;
	}
	
	/**
	 * Check if column exists
	 */
	private function _column_exists($table, $column)
	{
		$q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($column));
		return ($q && $q->num_rows() > 0);
	}
	
	/**
	 * Check if table exists
	 */
	private function _table_exists($table)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table));
		return ($q && $q->num_rows() > 0);
	}
	
	// =========================================================================
	// PERFORMANCE METRICS
	// =========================================================================
	
	/**
	 * Get table statistics
	 */
	public function get_table_stats()
	{
		$tables = array(
			'iop_medication',
			'iop_medication_administration',
			'medicine_drug_name',
			'patient_details_iop',
			'pharmacy_billing_queue',
			'medication_stock',
			'pharmacy_stock_adjustment'
		);
		
		$stats = array();
		
		foreach ($tables as $table) {
			if (!$this->_table_exists($table)) {
				$stats[$table] = array('exists' => false);
				continue;
			}
			
			// Get row count
			$q = $this->db->query("SELECT COUNT(*) as cnt FROM `{$table}`");
			$row = $q ? $q->row() : null;
			$count = $row ? (int)$row->cnt : 0;
			
			// Get table size
			$q = $this->db->query("
				SELECT 
					ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
				FROM information_schema.TABLES 
				WHERE table_schema = DATABASE()
				AND table_name = ?
			", array($table));
			$row = $q ? $q->row() : null;
			$size = $row ? (float)$row->size_mb : 0;
			
			// Get index count
			$q = $this->db->query("SHOW INDEX FROM `{$table}`");
			$indexes = $q ? $q->num_rows() : 0;
			
			$stats[$table] = array(
				'exists' => true,
				'rows' => $count,
				'size_mb' => $size,
				'indexes' => $indexes
			);
		}
		
		return $stats;
	}
	
	/**
	 * Get slow query candidates
	 */
	public function analyze_slow_queries()
	{
		$issues = array();
		
		// Check for missing indexes on frequently queried columns
		$checks = array(
			array('iop_medication', 'iop_id', 'idx_iop_id'),
			array('iop_medication', 'dispensing_status', 'idx_dispense_status'),
			array('medicine_drug_name', 'drug_name', 'idx_drug_name'),
			array('patient_details_iop', 'patient_no', 'idx_patient_no'),
		);
		
		foreach ($checks as $check) {
			list($table, $column, $index) = $check;
			if (!$this->_table_exists($table)) continue;
			
			$q = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index));
			if (!$q || $q->num_rows() === 0) {
				$issues[] = array(
					'type' => 'missing_index',
					'table' => $table,
					'column' => $column,
					'index' => $index,
					'fix' => "ALTER TABLE `{$table}` ADD INDEX `{$index}` (`{$column}`)"
				);
			}
		}
		
		// Check for large tables without recent archiving
		$q = $this->db->query("SELECT COUNT(*) as cnt FROM `iop_medication` WHERE dDate < DATE_SUB(NOW(), INTERVAL 90 DAY) AND InActive = 0");
		$row = $q ? $q->row() : null;
		if ($row && (int)$row->cnt > 1000) {
			$issues[] = array(
				'type' => 'archive_needed',
				'table' => 'iop_medication',
				'old_records' => (int)$row->cnt,
				'recommendation' => 'Archive prescriptions older than 90 days'
			);
		}
		
		return $issues;
	}
	
	// =========================================================================
	// QUERY OPTIMIZATION HELPERS
	// =========================================================================
	
	/**
	 * Optimize a query with EXPLAIN
	 */
	public function explain_query($sql)
	{
		$q = $this->db->query("EXPLAIN " . $sql);
		return $q ? $q->result() : array();
	}
	
	/**
	 * Get query execution time
	 */
	public function benchmark_query($sql, $iterations = 5)
	{
		$times = array();
		
		for ($i = 0; $i < $iterations; $i++) {
			$start = microtime(true);
			$this->db->query($sql);
			$times[] = microtime(true) - $start;
		}
		
		return array(
			'min' => min($times) * 1000,
			'max' => max($times) * 1000,
			'avg' => (array_sum($times) / count($times)) * 1000,
			'iterations' => $iterations
		);
	}
	
	// =========================================================================
	// ARCHIVING OPERATIONS
	// =========================================================================
	
	/**
	 * Run full archive operation
	 */
	public function run_archive($days_old = 90)
	{
		$this->load->model('app/pharmacy_workflow_model');
		
		$results = array(
			'prescriptions_archived' => $this->pharmacy_workflow_model->archive_old_prescriptions($days_old),
			'billing_archived' => $this->pharmacy_workflow_model->archive_old_billing_queue($days_old),
			'archived_at' => date('Y-m-d H:i:s')
		);
		
		return $results;
	}
	
	/**
	 * Get archive status
	 */
	public function get_archive_status()
	{
		$this->load->model('app/pharmacy_workflow_model');
		return $this->pharmacy_workflow_model->get_archive_stats();
	}
	
	// =========================================================================
	// CACHE MANAGEMENT
	// =========================================================================
	
	/**
	 * Clear all pharmacy caches
	 */
	public function clear_all_caches()
	{
		// Clear static caches in models
		$this->load->model('app/pharmacy_stock_model');
		$this->load->model('app/pharmacy_dispense_model');
		$this->load->model('app/pharmacy_billing_model');
		$this->load->model('app/pharmacy_workflow_model');
		
		// Each model extends pharmacy_base_model which has cache_invalidate
		$this->pharmacy_stock_model->cache_invalidate();
		
		return true;
	}
	
	// =========================================================================
	// HEALTH CHECK
	// =========================================================================
	
	/**
	 * Run pharmacy system health check
	 */
	public function health_check()
	{
		$health = array(
			'status' => 'OK',
			'checks' => array(),
			'warnings' => array(),
			'errors' => array()
		);
		
		// Check tables exist
		$required_tables = array('iop_medication', 'medicine_drug_name', 'patient_details_iop');
		foreach ($required_tables as $table) {
			if ($this->_table_exists($table)) {
				$health['checks'][] = "Table {$table} exists";
			} else {
				$health['errors'][] = "Table {$table} missing";
				$health['status'] = 'ERROR';
			}
		}
		
		// Check indexes
		$this->ensure_performance_indexes();
		$health['checks'][] = "Performance indexes verified";
		
		// Check for slow query candidates
		$issues = $this->analyze_slow_queries();
		if (!empty($issues)) {
			foreach ($issues as $issue) {
				if ($issue['type'] === 'missing_index') {
					$health['warnings'][] = "Missing index on {$issue['table']}.{$issue['column']}";
				} elseif ($issue['type'] === 'archive_needed') {
					$health['warnings'][] = "{$issue['old_records']} old records need archiving in {$issue['table']}";
				}
			}
			if ($health['status'] === 'OK') {
				$health['status'] = 'WARNING';
			}
		}
		
		// Check table sizes
		$stats = $this->get_table_stats();
		foreach ($stats as $table => $info) {
			if (isset($info['rows']) && $info['rows'] > 100000) {
				$health['warnings'][] = "Table {$table} has {$info['rows']} rows - consider archiving";
			}
		}
		
		return $health;
	}
}
