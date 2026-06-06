<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . 'models/app/pharmacy_base_model.php');

/**
 * Pharmacy Stock Model
 * 
 * Handles all stock-related operations:
 * - Stock levels and queries
 * - Batch stock management (FEFO)
 * - Stock adjustments and deductions
 * - Expiry tracking
 * - Low stock alerts
 * 
 * Part of Phase 4 Performance Optimization.
 */
class Pharmacy_stock_model extends Pharmacy_base_model
{
	private static $_schema_done = false;
	
	public function __construct()
	{
		parent::__construct();
	}
	
	// =========================================================================
	// SCHEMA MANAGEMENT
	// =========================================================================
	
	public function ensure_stock_schema()
	{
		if (self::$_schema_done) return;
		self::$_schema_done = true;
		
		// Create medication_stock table for batch tracking
		if (!$this->table_exists('medication_stock')) {
			$this->db->query("
				CREATE TABLE `medication_stock` (
					`stock_id` int(11) NOT NULL AUTO_INCREMENT,
					`medication_id` int(11) NOT NULL,
					`batch_number` varchar(50) DEFAULT NULL,
					`quantity_initial` decimal(10,2) NOT NULL DEFAULT 0,
					`quantity_remaining` decimal(10,2) NOT NULL DEFAULT 0,
					`expiry_date` date DEFAULT NULL,
					`unit_cost` decimal(15,2) DEFAULT 0,
					`selling_price` decimal(15,2) DEFAULT 0,
					`supplier` varchar(100) DEFAULT NULL,
					`status` varchar(20) DEFAULT 'ACTIVE',
					`created_by` varchar(25) DEFAULT NULL,
					`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
					`updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
					`InActive` tinyint(1) DEFAULT 0,
					PRIMARY KEY (`stock_id`),
					KEY `idx_medication_id` (`medication_id`),
					KEY `idx_expiry_date` (`expiry_date`),
					KEY `idx_status` (`status`),
					KEY `idx_batch_active` (`medication_id`, `status`, `InActive`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}
		
		// Create stock adjustment log
		if (!$this->table_exists('pharmacy_stock_adjustment')) {
			$this->db->query("
				CREATE TABLE `pharmacy_stock_adjustment` (
					`adjustment_id` int(11) NOT NULL AUTO_INCREMENT,
					`drug_id` int(11) NOT NULL,
					`adjustment_type` varchar(30) NOT NULL,
					`qty_change` decimal(10,2) NOT NULL,
					`stock_before` decimal(10,2) DEFAULT 0,
					`stock_after` decimal(10,2) DEFAULT 0,
					`reason` text,
					`reference_type` varchar(30) DEFAULT NULL,
					`reference_id` int(11) DEFAULT NULL,
					`created_at` datetime DEFAULT CURRENT_TIMESTAMP,
					`created_by` varchar(25) DEFAULT NULL,
					PRIMARY KEY (`adjustment_id`),
					KEY `idx_drug_id` (`drug_id`),
					KEY `idx_created_at` (`created_at`),
					KEY `idx_ref` (`reference_type`, `reference_id`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}
		
		// Add performance indexes to medicine_drug_name
		if ($this->table_exists('medicine_drug_name')) {
			$this->add_index_if_not_exists('medicine_drug_name', 'idx_stock_alert', 'nStock');
			$this->add_index_if_not_exists('medicine_drug_name', 'idx_active_stock', array('InActive', 'nStock'));
			$this->add_index_if_not_exists('medicine_drug_name', 'idx_category', 'category_id');
		}
	}
	
	// =========================================================================
	// STOCK QUERIES (CACHED)
	// =========================================================================
	
	/**
	 * Get current stock for a drug (cached)
	 */
	public function get_drug_stock($drug_id, $bypass_cache = false)
	{
		$key = 'drug_stock_' . $drug_id;
		
		// If bypass_cache is true, get fresh data
		if ($bypass_cache) {
			$this->cache_invalidate($key);
		}
		
		return $this->cache_get($key, function() use ($drug_id, $bypass_cache) {
			$this->db->select('nStock');
			$this->db->from('medicine_drug_name');
			$this->db->where('drug_id', $drug_id);
			$q = $this->db->get();
			$row = $q ? $q->row() : null;
			$stock = $row ? (float)$row->nStock : 0;
			
			// Debug logging
			log_message('debug', "Pharmacy_stock_model::get_drug_stock - drug_id: $drug_id, stock: $stock, bypass_cache: " . ($bypass_cache ? 'true' : 'false'));
			
			return $stock;
		}, 30); // Cache for 30 seconds
	}
	
	/**
	 * Get stock map for multiple drugs (batch fetch)
	 */
	public function get_stock_map($drug_ids)
	{
		if (empty($drug_ids)) return array();
		
		$drug_ids = array_unique(array_filter($drug_ids));
		$key = 'stock_map_' . md5(implode(',', $drug_ids));
		
		return $this->cache_get($key, function() use ($drug_ids) {
			$this->db->select('drug_id, nStock, nReorderLevel');
			$this->db->from('medicine_drug_name');
			$this->db->where_in('drug_id', $drug_ids);
			$q = $this->db->get();
			$rows = $q ? $q->result() : array();
			
			$map = array();
			foreach ($rows as $r) {
				$map[$r->drug_id] = array(
					'stock' => (float)$r->nStock,
					'reorder' => (float)$r->nReorderLevel,
					'low' => ((float)$r->nStock <= (float)$r->nReorderLevel)
				);
			}
			return $map;
		}, 30);
	}
	
	/**
	 * Get full drug list (cached)
	 */
	public function get_drug_list($filters = array())
	{
		$search = isset($filters['search']) ? trim($filters['search']) : '';
		$category = isset($filters['category']) ? (int)$filters['category'] : 0;
		$show_low = isset($filters['show_low']) ? (bool)$filters['show_low'] : false;
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 500;
		
		$key = 'drug_list_' . md5(serialize($filters));
		
		return $this->cache_get($key, function() use ($search, $category, $show_low, $limit) {
			$this->db->select('d.drug_id, d.drug_name, d.generic_name, d.nStock, d.nPrice, d.nReorderLevel, d.category_id, c.category_name');
			$this->db->from('medicine_drug_name d');
			$this->db->join('medicine_category c', 'c.category_id = d.category_id', 'left');
			$this->db->where('d.InActive', 0);
			
			if ($search !== '') {
				$this->db->group_start();
				$this->db->like('d.drug_name', $search);
				$this->db->or_like('d.generic_name', $search);
				$this->db->group_end();
			}
			
			if ($category > 0) {
				$this->db->where('d.category_id', $category);
			}
			
			if ($show_low) {
				$this->db->where('d.nStock <= d.nReorderLevel');
			}
			
			$this->db->order_by('d.drug_name', 'ASC');
			$this->db->limit($limit);
			
			$q = $this->db->get();
			return $q ? $q->result() : array();
		}, 60); // Cache for 1 minute
	}
	
	/**
	 * Count low stock drugs (cached)
	 */
	public function count_low_stock()
	{
		return $this->cache_get('count_low_stock', function() {
			$this->db->from('medicine_drug_name');
			$this->db->where('InActive', 0);
			$this->db->where('nStock <= nReorderLevel', null, false);
			return $this->db->count_all_results();
		}, 60);
	}
	
	// =========================================================================
	// STOCK MODIFICATIONS
	// =========================================================================
	
	/**
	 * Deduct stock with audit trail
	 */
	public function deduct_stock($drug_id, $qty, $user_id = null, $ref_type = 'DISPENSE', $ref_id = 0)
	{
		$before = $this->get_drug_stock($drug_id);
		$after = max(0, $before - $qty);
		
		$this->db->set('nStock', $after);
		$this->db->where('drug_id', $drug_id);
		$this->db->update('medicine_drug_name');
		
		$this->log_stock_adjustment($drug_id, 'DEDUCT', -$qty, $before, $after, 'Dispensed', $ref_type, $ref_id, $user_id);
		
		// Invalidate cache
		$this->cache_invalidate('drug_stock_' . $drug_id);
		$this->cache_invalidate('stock_map_');
		$this->cache_invalidate('count_low_stock');
		
		return $after;
	}
	
	/**
	 * Adjust stock (restock or write-off)
	 */
	public function adjust_stock($drug_id, $qty_change, $reason, $user_id = null)
	{
		$before = $this->get_drug_stock($drug_id, true);
		
		// 1. Check and migrate legacy stock if needed to prevent zeroing
		$this->db->select('SUM(quantity_remaining) as total');
		$this->db->from('medication_stock');
		$this->db->where('medication_id', $drug_id);
		$this->db->where('InActive', 0);
		$this->db->where('status', 'ACTIVE');
		$this->db->group_start();
		$this->db->where('expiry_date IS NULL');
		$this->db->or_where('expiry_date >=', date('Y-m-d'));
		$this->db->group_end();
		$q = $this->db->get();
		$batch_total = $q ? (float)$q->row()->total : 0.0;
		
		if ($before > $batch_total + 0.001) {
			$diff = $before - $batch_total;
			$this->add_batch_stock(array(
				'medication_id' => $drug_id,
				'quantity' => $diff,
				'batch_number' => 'LEGACY-MIG',
				'expiry_date' => date('Y-m-d', strtotime('+2 years'))
			), $user_id);
		}

		// 2. Perform adjustment via batches
		if ($qty_change > 0) {
			$this->add_batch_stock(array(
				'medication_id' => $drug_id,
				'quantity' => $qty_change,
				'batch_number' => 'ADJ-' . date('ymdHi'),
				'expiry_date' => date('Y-m-d', strtotime('+2 years'))
			), $user_id);
		} elseif ($qty_change < 0) {
			$this->deduct_batch_stock_fefo($drug_id, abs($qty_change), $user_id, 'ADJUSTMENT', 0);
		}
		
		// 3. Read back fresh stock to ensure synchronization
		$after = $this->get_drug_stock($drug_id, true);
		
		// 4. Log the manual adjustment
		$type = $qty_change >= 0 ? 'RESTOCK' : 'WRITE_OFF';
		$this->log_stock_adjustment($drug_id, $type, $qty_change, $before, $after, $reason, 'MANUAL', 0, $user_id);
		
		// Invalidate cache immediately after update
		$this->cache_invalidate('drug_stock_' . $drug_id);
		$this->cache_invalidate('stock_map_');
		$this->cache_invalidate('count_low_stock');
		$this->cache_invalidate('drug_list_');
		
		return true;
	}
	
	/**
	 * Force invalidate all pharmacy stock cache
	 */
	public function invalidate_all_stock_cache()
	{
		$this->cache_invalidate('drug_stock_');
		$this->cache_invalidate('stock_map_');
		$this->cache_invalidate('count_low_stock');
		$this->cache_invalidate('count_out_of_stock');
		$this->cache_invalidate('count_expiring_');
		$this->cache_invalidate('drug_list_');
		$this->cache_invalidate('pharmacy_alerts_');
		
		log_message('debug', 'Pharmacy_stock_model::invalidate_all_stock_cache - All stock cache invalidated');
	}
	private function log_stock_adjustment($drug_id, $type, $qty_change, $before, $after, $reason, $ref_type, $ref_id, $user_id)
	{
		$this->ensure_stock_schema();
		
		$this->db->insert('pharmacy_stock_adjustment', array(
			'drug_id' => $drug_id,
			'adjustment_type' => $type,
			'qty_change' => $qty_change,
			'stock_before' => $before,
			'stock_after' => $after,
			'reason' => $reason,
			'reference_type' => $ref_type,
			'reference_id' => $ref_id,
			'created_by' => $user_id,
			'created_at' => date('Y-m-d H:i:s')
		));
	}
	
	/**
	 * Get stock adjustment history
	 */
	public function get_stock_history($drug_id, $limit = 20)
	{
		$this->ensure_stock_schema();
		
		$this->db->select('*');
		$this->db->from('pharmacy_stock_adjustment');
		$this->db->where('drug_id', $drug_id);
		$this->db->order_by('created_at', 'DESC');
		$this->db->limit($limit);
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
	
	// =========================================================================
	// BATCH STOCK (FEFO - First Expiry First Out)
	// =========================================================================
	
	/**
	 * Add batch stock
	 */
	public function add_batch_stock($data, $user_id = null)
	{
		$this->ensure_stock_schema();
		
		$insert = array(
			'medication_id' => (int)$data['medication_id'],
			'batch_number' => isset($data['batch_number']) ? $data['batch_number'] : null,
			'quantity_initial' => (float)$data['quantity'],
			'quantity_remaining' => (float)$data['quantity'],
			'expiry_date' => isset($data['expiry_date']) ? $data['expiry_date'] : null,
			'unit_cost' => isset($data['unit_cost']) ? (float)$data['unit_cost'] : 0,
			'selling_price' => isset($data['selling_price']) ? (float)$data['selling_price'] : 0,
			'supplier' => isset($data['supplier']) ? $data['supplier'] : null,
			'status' => 'ACTIVE',
			'created_by' => $user_id,
			'created_at' => date('Y-m-d H:i:s')
		);
		
		$this->db->insert('medication_stock', $insert);
		$stock_id = $this->db->insert_id();
		
		// Sync master stock
		$this->sync_master_stock($data['medication_id']);
		
		// Invalidate cache
		$this->cache_invalidate('batch_stock_');
		$this->cache_invalidate('drug_stock_');
		
		return $stock_id;
	}
	
	/**
	 * Get batch stock for medication (FEFO order)
	 */
	public function get_batch_stock($medication_id, $include_expired = false)
	{
		$this->ensure_stock_schema();
		
		$key = 'batch_stock_' . $medication_id . '_' . ($include_expired ? '1' : '0');
		
		return $this->cache_get($key, function() use ($medication_id, $include_expired) {
			$this->db->select('*');
			$this->db->from('medication_stock');
			$this->db->where('medication_id', $medication_id);
			$this->db->where('InActive', 0);
			$this->db->where('quantity_remaining >', 0);
			
			if (!$include_expired) {
				$this->db->group_start();
				$this->db->where('expiry_date IS NULL');
				$this->db->or_where('expiry_date >=', date('Y-m-d'));
				$this->db->group_end();
			}
			
			$this->db->order_by('expiry_date', 'ASC'); // FEFO
			$this->db->order_by('created_at', 'ASC');
			
			$q = $this->db->get();
			return $q ? $q->result() : array();
		}, 30);
	}
	
	/**
	 * Deduct from batch stock using FEFO
	 */
	public function deduct_batch_stock_fefo($medication_id, $qty, $user_id = null, $ref_type = 'DISPENSE', $ref_id = 0)
	{
		$this->ensure_stock_schema();

		$remaining = (float)$qty;
		$deducted = array();
		$today = date('Y-m-d');

		$this->db->trans_begin();
		try {
			$sql = "SELECT stock_id, batch_number, quantity_remaining\n"
				. "FROM medication_stock\n"
				. "WHERE medication_id = ?\n"
				. "AND InActive = 0\n"
				. "AND status = 'ACTIVE'\n"
				. "AND quantity_remaining > 0\n"
				. "AND (expiry_date IS NULL OR expiry_date >= ?)\n"
				. "ORDER BY expiry_date ASC, created_at ASC\n"
				. "FOR UPDATE";
			$batches = $this->db->query($sql, array($medication_id, $today))->result();

			foreach ($batches as $batch) {
				if ($remaining <= 0) break;
				$available = isset($batch->quantity_remaining) ? (float)$batch->quantity_remaining : 0.0;
				if ($available <= 0) continue;
				$take = min($available, $remaining);
				if ($take <= 0) continue;

				$this->db->set('quantity_remaining', 'quantity_remaining - ' . (float)$take, false);
				$this->db->where('stock_id', (int)$batch->stock_id);
				$this->db->where('quantity_remaining >=', (float)$take);
				$this->db->update('medication_stock');
				if ((int)$this->db->affected_rows() <= 0) {
					$this->db->trans_rollback();
					return array('success' => false, 'deducted' => array(), 'shortfall' => $remaining);
				}

				$deducted[] = array(
					'stock_id' => $batch->stock_id,
					'batch_number' => $batch->batch_number,
					'qty' => $take
				);
				$remaining -= $take;
			}

			if ($remaining > 0) {
				$this->db->trans_rollback();
				return array('success' => false, 'deducted' => array(), 'shortfall' => $remaining);
			}

			$this->sync_master_stock($medication_id);
			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('success' => false, 'deducted' => array(), 'shortfall' => $remaining);
			}
			$this->db->trans_commit();
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('success' => false, 'deducted' => array(), 'shortfall' => $remaining);
		}

		$this->cache_invalidate('batch_stock_' . $medication_id);
		$this->cache_invalidate('drug_stock_' . $medication_id);

		return array('success' => true, 'deducted' => $deducted, 'shortfall' => 0);
	}
	
	/**
	 * Sync master stock from batch totals
	 */
	private function sync_master_stock($medication_id)
	{
		$this->ensure_stock_schema();
		
		$this->db->select('SUM(quantity_remaining) as total');
		$this->db->from('medication_stock');
		$this->db->where('medication_id', $medication_id);
		$this->db->where('InActive', 0);
		$this->db->where('status', 'ACTIVE');
		$this->db->group_start();
		$this->db->where('expiry_date IS NULL');
		$this->db->or_where('expiry_date >=', date('Y-m-d'));
		$this->db->group_end();
		
		$q = $this->db->get();
		$row = $q ? $q->row() : null;
		$total = $row ? (float)$row->total : 0;
		
		$this->db->set('nStock', $total);
		$this->db->where('drug_id', $medication_id);
		$this->db->update('medicine_drug_name');
		
		$this->cache_invalidate('drug_stock_' . $medication_id);
	}
	
	// =========================================================================
	// EXPIRY MANAGEMENT
	// =========================================================================
	
	/**
	 * Get expiring batches
	 */
	public function get_expiring_batches($days = 30, $limit = 100)
	{
		$this->ensure_stock_schema();
		
		$cutoff = date('Y-m-d', strtotime('+' . (int)$days . ' days'));
		
		$this->db->select('s.*, d.drug_name');
		$this->db->from('medication_stock s');
		$this->db->join('medicine_drug_name d', 'd.drug_id = s.medication_id', 'left');
		$this->db->where('s.InActive', 0);
		$this->db->where('s.quantity_remaining >', 0);
		$this->db->where('s.expiry_date IS NOT NULL');
		$this->db->where('s.expiry_date <=', $cutoff);
		$this->db->where('s.expiry_date >=', date('Y-m-d'));
		$this->db->order_by('s.expiry_date', 'ASC');
		$this->db->limit($limit);
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
	
	/**
	 * Get expired batches
	 */
	public function get_expired_batches($limit = 100)
	{
		$this->ensure_stock_schema();
		
		$this->db->select('s.*, d.drug_name');
		$this->db->from('medication_stock s');
		$this->db->join('medicine_drug_name d', 'd.drug_id = s.medication_id', 'left');
		$this->db->where('s.InActive', 0);
		$this->db->where('s.quantity_remaining >', 0);
		$this->db->where('s.expiry_date <', date('Y-m-d'));
		$this->db->order_by('s.expiry_date', 'ASC');
		$this->db->limit($limit);
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
	
	/**
	 * Count expiring soon
	 */
	public function count_expiring_soon($days = 30)
	{
		$key = 'count_expiring_' . $days;
		return $this->cache_get($key, function() use ($days) {
			$this->ensure_stock_schema();
			$cutoff = date('Y-m-d', strtotime('+' . (int)$days . ' days'));
			
			$this->db->from('medication_stock');
			$this->db->where('InActive', 0);
			$this->db->where('quantity_remaining >', 0);
			$this->db->where('expiry_date IS NOT NULL');
			$this->db->where('expiry_date <=', $cutoff);
			$this->db->where('expiry_date >=', date('Y-m-d'));
			
			return $this->db->count_all_results();
		}, 300);
	}
	
	/**
	 * Count expired batches
	 */
	public function count_expired_batches()
	{
		return $this->cache_get('count_expired', function() {
			$this->ensure_stock_schema();
			
			$this->db->from('medication_stock');
			$this->db->where('InActive', 0);
			$this->db->where('quantity_remaining >', 0);
			$this->db->where('expiry_date <', date('Y-m-d'));
			
			return $this->db->count_all_results();
		}, 300);
	}
	
	/**
	 * Remove expired batch
	 */
	public function remove_expired_batch($stock_id, $user_id, $reason = 'Expired')
	{
		$this->ensure_stock_schema();
		
		// Get batch info
		$this->db->where('stock_id', $stock_id);
		$q = $this->db->get('medication_stock');
		$batch = $q ? $q->row() : null;
		
		if (!$batch) return false;
		
		// Mark as inactive
		$this->db->set('InActive', 1);
		$this->db->set('status', 'REMOVED');
		$this->db->where('stock_id', $stock_id);
		$this->db->update('medication_stock');
		
		// Log adjustment
		$this->log_stock_adjustment(
			$batch->medication_id,
			'EXPIRED_REMOVAL',
			-$batch->quantity_remaining,
			$batch->quantity_remaining,
			0,
			$reason,
			'BATCH',
			$stock_id,
			$user_id
		);
		
		// Sync master stock
		$this->sync_master_stock($batch->medication_id);
		
		// Invalidate cache
		$this->cache_invalidate('batch_stock_');
		$this->cache_invalidate('count_expired');
		$this->cache_invalidate('count_expiring');
		
		return true;
	}
	
	// =========================================================================
	// DRUG SEARCH (OPTIMIZED)
	// =========================================================================
	
	/**
	 * Search drugs with stock info
	 */
	public function search_drugs($term, $limit = 20)
	{
		$term = trim($term);
		if (strlen($term) < 2) return array();
		
		$this->db->select('d.drug_id, d.drug_name, d.generic_name, d.nStock, d.nPrice, d.nReorderLevel, d.is_nhis_covered, d.nhis_price, c.category_name');
		$this->db->from('medicine_drug_name d');
		$this->db->join('medicine_category c', 'c.category_id = d.category_id', 'left');
		$this->db->where('d.InActive', 0);
		$this->db->group_start();
		$this->db->like('d.drug_name', $term);
		$this->db->or_like('d.generic_name', $term);
		$this->db->group_end();
		$this->db->order_by('d.drug_name', 'ASC');
		$this->db->limit($limit);
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
}
