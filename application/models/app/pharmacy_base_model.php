<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pharmacy Base Model
 * 
 * Shared utilities and caching layer for all pharmacy models.
 * Part of Phase 4 Performance Optimization.
 */
class Pharmacy_base_model extends CI_Model
{
	// =========================================================================
	// CACHING LAYER
	// =========================================================================
	
	protected static $_cache = array();
	protected static $_cache_ttl = 300; // 5 minutes default
	protected static $_cache_timestamps = array();
	
	/**
	 * Get cached value or execute callback
	 */
	protected function cache_get($key, $callback, $ttl = null)
	{
		$ttl = $ttl ?: self::$_cache_ttl;
		$now = time();
		
		// Check if cached and not expired
		if (isset(self::$_cache[$key]) && isset(self::$_cache_timestamps[$key])) {
			if (($now - self::$_cache_timestamps[$key]) < $ttl) {
				return self::$_cache[$key];
			}
		}
		
		// Execute callback and cache result
		$result = $callback();
		self::$_cache[$key] = $result;
		self::$_cache_timestamps[$key] = $now;
		
		return $result;
	}
	
	/**
	 * Invalidate cache by key or pattern
	 */
	protected function cache_invalidate($pattern = null)
	{
		if ($pattern === null) {
			self::$_cache = array();
			self::$_cache_timestamps = array();
			return;
		}
		
		foreach (array_keys(self::$_cache) as $key) {
			if (strpos($key, $pattern) !== false) {
				unset(self::$_cache[$key]);
				unset(self::$_cache_timestamps[$key]);
			}
		}
	}
	
	/**
	 * Set cache TTL
	 */
	public function set_cache_ttl($seconds)
	{
		self::$_cache_ttl = (int)$seconds;
	}
	
	// =========================================================================
	// DATABASE UTILITIES
	// =========================================================================
	
	/**
	 * Check if table exists (cached)
	 */
	public function table_exists($table_name)
	{
		$key = 'table_exists_' . $table_name;
		return $this->cache_get($key, function() use ($table_name) {
			$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table_name));
			return ($q && $q->num_rows() > 0);
		}, 3600); // Cache for 1 hour
	}
	
	/**
	 * Check if column exists (cached)
	 */
	public function column_exists($table, $col)
	{
		$key = 'column_exists_' . $table . '_' . $col;
		return $this->cache_get($key, function() use ($table, $col) {
			$q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($col));
			return ($q && $q->num_rows() > 0);
		}, 3600); // Cache for 1 hour
	}
	
	/**
	 * Safe column add (idempotent)
	 */
	protected function add_column_if_not_exists($table, $col, $definition)
	{
		if (!$this->column_exists($table, $col)) {
			$this->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
			$this->cache_invalidate('column_exists_' . $table);
			return true;
		}
		return false;
	}
	
	/**
	 * Safe index add (idempotent)
	 * Checks both index existence AND column existence before creating
	 */
	protected function add_index_if_not_exists($table, $index_name, $columns)
	{
		// First check if all columns exist
		$cols_array = is_array($columns) ? $columns : array($columns);
		foreach ($cols_array as $col) {
			if (!$this->column_exists($table, $col)) {
				return false; // Column doesn't exist, skip index creation
			}
		}
		
		$key = 'index_exists_' . $table . '_' . $index_name;
		$exists = $this->cache_get($key, function() use ($table, $index_name) {
			$q = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = " . $this->db->escape($index_name));
			return ($q && $q->num_rows() > 0);
		}, 3600);
		
		if (!$exists) {
			$cols = is_array($columns) ? implode('`, `', $columns) : $columns;
			$this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$cols}`)");
			$this->cache_invalidate('index_exists_' . $table);
			return true;
		}
		return false;
	}
	
	/**
	 * Batch fetch by IDs - prevents N+1 queries
	 */
	protected function batch_fetch($table, $id_column, $ids, $select = '*', $where = array())
	{
		if (empty($ids)) return array();
		
		$ids = array_unique(array_filter($ids));
		if (empty($ids)) return array();
		
		$this->db->select($select);
		$this->db->from($table);
		$this->db->where_in($id_column, $ids);
		
		foreach ($where as $k => $v) {
			$this->db->where($k, $v);
		}
		
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		
		// Index by ID for O(1) lookup
		$map = array();
		foreach ($rows as $row) {
			$map[$row->$id_column] = $row;
		}
		
		return $map;
	}
	
	/**
	 * Execute query with timing for performance monitoring
	 */
	protected function timed_query($sql, $bindings = array())
	{
		$start = microtime(true);
		$result = $this->db->query($sql, $bindings);
		$elapsed = microtime(true) - $start;
		
		// Log slow queries (> 100ms)
		if ($elapsed > 0.1) {
			log_message('debug', sprintf('SLOW QUERY (%.2fms): %s', $elapsed * 1000, $sql));
		}
		
		return $result;
	}
	
	// =========================================================================
	// COMMON PHARMACY UTILITIES
	// =========================================================================
	
	/**
	 * Get patient info for IOP
	 */
	public function get_patient_for_iop($iop_id)
	{
		$key = 'patient_iop_' . $iop_id;
		return $this->cache_get($key, function() use ($iop_id) {
			$this->db->select('I.patient_no, I.date_visit, P.firstname, P.middlename, P.lastname, P.Insurance_comp');
			$this->db->from('patient_details_iop I');
			$this->db->join('patient_personal_info P', 'P.patient_no = I.patient_no', 'left');
			$this->db->where('I.IO_ID', $iop_id);
			$q = $this->db->get();
			return $q ? $q->row() : null;
		}, 60); // Cache for 1 minute
	}
	
	/**
	 * Check if patient is NHIS
	 */
	public function is_nhis_patient($patient_no)
	{
		$key = 'is_nhis_' . $patient_no;
		return $this->cache_get($key, function() use ($patient_no) {
			$this->db->select('Insurance_comp, nhis_number, nhis_status');
			$this->db->from('patient_personal_info');
			$this->db->where('patient_no', $patient_no);
			$q = $this->db->get();
			$row = $q ? $q->row() : null;
			
			if (!$row) return false;
			
			$ins = strtoupper(trim($row->Insurance_comp ?? ''));
			$nhis = trim($row->nhis_number ?? '');
			$status = strtoupper(trim($row->nhis_status ?? ''));
			
			return ($ins === 'NHIS' || !empty($nhis)) && $status !== 'EXPIRED';
		}, 60);
	}
	
	/**
	 * Log audit trail
	 */
	public function log_audit($table, $record_id, $event, $old_value, $new_value, $user_id, $notes = '')
	{
		if (!$this->table_exists('pharmacy_audit_log')) {
			return false;
		}

		$has_generic = $this->column_exists('pharmacy_audit_log', 'audit_table')
			&& $this->column_exists('pharmacy_audit_log', 'record_id')
			&& $this->column_exists('pharmacy_audit_log', 'event_type');

		if ($has_generic) {
			$this->db->insert('pharmacy_audit_log', array(
				'audit_table' => $table,
				'record_id' => $record_id,
				'event_type' => $event,
				'old_value' => is_array($old_value) ? json_encode($old_value) : $old_value,
				'new_value' => is_array($new_value) ? json_encode($new_value) : $new_value,
				'performed_by' => $user_id,
				'performed_at' => date('Y-m-d H:i:s'),
				'notes' => $notes,
				'ip_address' => $this->input->ip_address()
			));
			return true;
		}

		$has_legacy = $this->column_exists('pharmacy_audit_log', 'iop_med_id')
			&& $this->column_exists('pharmacy_audit_log', 'event_type')
			&& $this->column_exists('pharmacy_audit_log', 'performed_at');
		if ($has_legacy) {
			$data = array(
				'iop_med_id' => (int)$record_id,
				'event_type' => (string)$event,
				'performed_by' => (string)$user_id,
				'performed_at' => date('Y-m-d H:i:s'),
			);
			if ($this->column_exists('pharmacy_audit_log', 'old_status')) {
				$data['old_status'] = is_array($old_value) ? json_encode($old_value) : ($old_value !== null ? (string)$old_value : null);
			}
			if ($this->column_exists('pharmacy_audit_log', 'new_status')) {
				$data['new_status'] = is_array($new_value) ? json_encode($new_value) : ($new_value !== null ? (string)$new_value : null);
			}
			if ($this->column_exists('pharmacy_audit_log', 'notes')) {
				$data['notes'] = $notes !== '' ? substr((string)$notes, 0, 255) : null;
			}
			if ($this->column_exists('pharmacy_audit_log', 'ip_address')) {
				$data['ip_address'] = $this->input->ip_address();
			}
			$this->db->insert('pharmacy_audit_log', $data);
			return true;
		}
		
		return true;
	}
}
