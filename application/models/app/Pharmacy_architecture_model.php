<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Pharmacy Architecture Model
 * 
 * Implements critical architecture fixes for the pharmacy system:
 * 1. Multi-Store Pharmacy Architecture
 * 2. Controlled Drugs Management
 * 3. Generic vs Brand Drug Mapping
 * 4. Prescription Locking Mechanism
 * 5. Batch Recall Tracking
 * 6. Financial Reconciliation Engine
 */
class Pharmacy_architecture_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	// =========================================================================
	// UTILITY METHODS
	// =========================================================================

	public function table_exists($table_name)
	{
		$table_name = (string)$table_name;
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table, $column)
	{
		$q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE " . $this->db->escape($column));
		return ($q && $q->num_rows() > 0);
	}

	// =========================================================================
	// SECTION 1: MULTI-STORE PHARMACY ARCHITECTURE
	// =========================================================================

	/**
	 * Ensure all multi-store schema is in place
	 */
	public function ensure_multistore_schema()
	{
		$this->_create_pharmacy_stores_table();
		$this->_create_store_stock_table();
		$this->_create_stock_transfer_table();
		$this->_add_store_columns_to_existing_tables();
		$this->_seed_default_store();
		return true;
	}

	/**
	 * Create pharmacy_stores table - master list of pharmacy locations
	 */
	private function _create_pharmacy_stores_table()
	{
		if ($this->table_exists('pharmacy_stores')) {
			return;
		}

		$sql = "CREATE TABLE `pharmacy_stores` (
			`store_id` int(11) NOT NULL AUTO_INCREMENT,
			`store_code` varchar(20) NOT NULL,
			`store_name` varchar(100) NOT NULL,
			`store_type` enum('MAIN','SATELLITE','WARD','EMERGENCY') NOT NULL DEFAULT 'SATELLITE',
			`location` varchar(255) DEFAULT NULL,
			`contact_phone` varchar(20) DEFAULT NULL,
			`manager_user_id` varchar(25) DEFAULT NULL,
			`is_active` tinyint(1) NOT NULL DEFAULT 1,
			`can_dispense` tinyint(1) NOT NULL DEFAULT 1,
			`can_receive_transfers` tinyint(1) NOT NULL DEFAULT 1,
			`operating_hours` varchar(100) DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`created_by` varchar(25) DEFAULT NULL,
			`updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`store_id`),
			UNIQUE KEY `uk_store_code` (`store_code`),
			KEY `idx_store_type` (`store_type`),
			KEY `idx_is_active` (`is_active`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create pharmacy_store_stock table - per-store inventory
	 */
	private function _create_store_stock_table()
	{
		if ($this->table_exists('pharmacy_store_stock')) {
			return;
		}

		$sql = "CREATE TABLE `pharmacy_store_stock` (
			`stock_id` int(11) NOT NULL AUTO_INCREMENT,
			`store_id` int(11) NOT NULL,
			`drug_id` int(11) NOT NULL,
			`batch_no` varchar(50) DEFAULT NULL,
			`quantity` decimal(12,2) NOT NULL DEFAULT 0,
			`reorder_level` decimal(12,2) DEFAULT 10,
			`max_stock_level` decimal(12,2) DEFAULT 1000,
			`unit_cost` decimal(18,2) DEFAULT 0,
			`expiry_date` date DEFAULT NULL,
			`last_restocked_at` datetime DEFAULT NULL,
			`last_dispensed_at` datetime DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`stock_id`),
			UNIQUE KEY `uk_store_drug_batch` (`store_id`, `drug_id`, `batch_no`),
			KEY `idx_store_id` (`store_id`),
			KEY `idx_drug_id` (`drug_id`),
			KEY `idx_expiry` (`expiry_date`),
			KEY `idx_low_stock` (`quantity`, `reorder_level`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create pharmacy_stock_transfer table - inter-store transfers
	 */
	private function _create_stock_transfer_table()
	{
		if ($this->table_exists('pharmacy_stock_transfer')) {
			return;
		}

		$sql = "CREATE TABLE `pharmacy_stock_transfer` (
			`transfer_id` int(11) NOT NULL AUTO_INCREMENT,
			`transfer_no` varchar(30) NOT NULL,
			`from_store_id` int(11) NOT NULL,
			`to_store_id` int(11) NOT NULL,
			`drug_id` int(11) NOT NULL,
			`batch_no` varchar(50) DEFAULT NULL,
			`quantity` decimal(12,2) NOT NULL,
			`status` enum('PENDING','APPROVED','IN_TRANSIT','RECEIVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
			`requested_by` varchar(25) NOT NULL,
			`requested_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`approved_by` varchar(25) DEFAULT NULL,
			`approved_at` datetime DEFAULT NULL,
			`received_by` varchar(25) DEFAULT NULL,
			`received_at` datetime DEFAULT NULL,
			`received_qty` decimal(12,2) DEFAULT NULL,
			`notes` text DEFAULT NULL,
			`rejection_reason` text DEFAULT NULL,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`transfer_id`),
			UNIQUE KEY `uk_transfer_no` (`transfer_no`),
			KEY `idx_from_store` (`from_store_id`),
			KEY `idx_to_store` (`to_store_id`),
			KEY `idx_status` (`status`),
			KEY `idx_drug_id` (`drug_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Add store_id columns to existing tables
	 */
	private function _add_store_columns_to_existing_tables()
	{
		// Add store_id to iop_medication_administration
		if ($this->table_exists('iop_medication_administration')) {
			if (!$this->column_exists('iop_medication_administration', 'store_id')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `store_id` int(11) DEFAULT 1 AFTER `admin_id`");
			}
		}

		// Add store_id to pharmacy_stock_adjustment
		if ($this->table_exists('pharmacy_stock_adjustment')) {
			if (!$this->column_exists('pharmacy_stock_adjustment', 'store_id')) {
				$this->db->query("ALTER TABLE `pharmacy_stock_adjustment` ADD COLUMN `store_id` int(11) DEFAULT 1 AFTER `adjustment_id`");
			}
		}

		// Add default_store_id to users table for pharmacist assignment
		if ($this->table_exists('users')) {
			if (!$this->column_exists('users', 'default_pharmacy_store_id')) {
				$this->db->query("ALTER TABLE `users` ADD COLUMN `default_pharmacy_store_id` int(11) DEFAULT NULL");
			}
		}
	}

	/**
	 * Seed default main pharmacy store
	 */
	private function _seed_default_store()
	{
		$q = $this->db->get_where('pharmacy_stores', array('store_code' => 'MAIN'));
		if ($q && $q->num_rows() > 0) {
			return;
		}

		$this->db->insert('pharmacy_stores', array(
			'store_code' => 'MAIN',
			'store_name' => 'Main Pharmacy',
			'store_type' => 'MAIN',
			'location' => 'Ground Floor, Main Building',
			'is_active' => 1,
			'can_dispense' => 1,
			'can_receive_transfers' => 1,
			'operating_hours' => '08:00-20:00',
			'created_by' => 'SYSTEM'
		));
	}

	// =========================================================================
	// STORE MANAGEMENT METHODS
	// =========================================================================

	/**
	 * Get all active pharmacy stores
	 */
	public function get_all_stores($include_inactive = false)
	{
		$this->db->select('*');
		$this->db->from('pharmacy_stores');
		if (!$include_inactive) {
			$this->db->where('is_active', 1);
			$this->db->where('InActive', 0);
		}
		$this->db->order_by('store_type', 'ASC');
		$this->db->order_by('store_name', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get store by ID
	 */
	public function get_store($store_id)
	{
		$q = $this->db->get_where('pharmacy_stores', array('store_id' => (int)$store_id, 'InActive' => 0));
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get store by code
	 */
	public function get_store_by_code($store_code)
	{
		$q = $this->db->get_where('pharmacy_stores', array('store_code' => $store_code, 'InActive' => 0));
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Add new pharmacy store
	 */
	public function add_store($data)
	{
		$insert = array(
			'store_code' => strtoupper(trim($data['store_code'])),
			'store_name' => trim($data['store_name']),
			'store_type' => isset($data['store_type']) ? $data['store_type'] : 'SATELLITE',
			'location' => isset($data['location']) ? trim($data['location']) : null,
			'contact_phone' => isset($data['contact_phone']) ? trim($data['contact_phone']) : null,
			'manager_user_id' => isset($data['manager_user_id']) ? $data['manager_user_id'] : null,
			'is_active' => isset($data['is_active']) ? (int)$data['is_active'] : 1,
			'can_dispense' => isset($data['can_dispense']) ? (int)$data['can_dispense'] : 1,
			'can_receive_transfers' => isset($data['can_receive_transfers']) ? (int)$data['can_receive_transfers'] : 1,
			'operating_hours' => isset($data['operating_hours']) ? trim($data['operating_hours']) : null,
			'created_by' => isset($data['created_by']) ? $data['created_by'] : null
		);

		$this->db->insert('pharmacy_stores', $insert);
		return $this->db->insert_id();
	}

	/**
	 * Update pharmacy store
	 */
	public function update_store($store_id, $data)
	{
		$update = array();
		$allowed = array('store_name', 'store_type', 'location', 'contact_phone', 'manager_user_id', 
						 'is_active', 'can_dispense', 'can_receive_transfers', 'operating_hours');

		foreach ($allowed as $field) {
			if (isset($data[$field])) {
				$update[$field] = $data[$field];
			}
		}

		if (empty($update)) {
			return false;
		}

		$this->db->where('store_id', (int)$store_id);
		return $this->db->update('pharmacy_stores', $update);
	}

	/**
	 * Deactivate store (soft delete)
	 */
	public function deactivate_store($store_id)
	{
		$this->db->where('store_id', (int)$store_id);
		return $this->db->update('pharmacy_stores', array('is_active' => 0));
	}

	// =========================================================================
	// STORE STOCK MANAGEMENT
	// =========================================================================

	/**
	 * Get stock for a specific store
	 */
	public function get_store_stock($store_id, $filters = array())
	{
		$this->db->select('ss.*, d.drug_name, d.nStock as central_stock, d.nPrice, d.reorder_level as central_reorder');
		$this->db->from('pharmacy_store_stock ss');
		$this->db->join('medicine_drug_name d', 'd.drug_id = ss.drug_id', 'left');
		$this->db->where('ss.store_id', (int)$store_id);
		$this->db->where('ss.InActive', 0);

		if (!empty($filters['search'])) {
			$this->db->like('d.drug_name', $filters['search']);
		}

		if (!empty($filters['low_stock_only'])) {
			$this->db->where('ss.quantity <= ss.reorder_level');
		}

		if (!empty($filters['expiring_soon'])) {
			$days = (int)$filters['expiring_soon'];
			$this->db->where('ss.expiry_date <=', date('Y-m-d', strtotime("+{$days} days")));
			$this->db->where('ss.expiry_date >=', date('Y-m-d'));
		}

		$this->db->order_by('d.drug_name', 'ASC');

		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
		$offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
		$this->db->limit($limit, $offset);

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get stock quantity for a drug at a specific store
	 */
	public function get_drug_stock_at_store($store_id, $drug_id, $batch_no = null)
	{
		$this->db->select('SUM(quantity) as total_qty');
		$this->db->from('pharmacy_store_stock');
		$this->db->where('store_id', (int)$store_id);
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->where('InActive', 0);

		if ($batch_no !== null) {
			$this->db->where('batch_no', $batch_no);
		}

		$q = $this->db->get();
		if ($q && $q->num_rows() > 0) {
			return (float)$q->row()->total_qty;
		}
		return 0;
	}

	/**
	 * Add or update stock at a store
	 */
	public function upsert_store_stock($store_id, $drug_id, $quantity, $data = array())
	{
		$batch_no = isset($data['batch_no']) ? $data['batch_no'] : null;

		// Check if record exists
		$this->db->where('store_id', (int)$store_id);
		$this->db->where('drug_id', (int)$drug_id);
		if ($batch_no !== null) {
			$this->db->where('batch_no', $batch_no);
		} else {
			$this->db->where('batch_no IS NULL');
		}
		$q = $this->db->get('pharmacy_store_stock');

		if ($q && $q->num_rows() > 0) {
			// Update existing
			$existing = $q->row();
			$new_qty = (float)$existing->quantity + (float)$quantity;

			$update = array('quantity' => $new_qty);
			if (isset($data['expiry_date'])) $update['expiry_date'] = $data['expiry_date'];
			if (isset($data['unit_cost'])) $update['unit_cost'] = $data['unit_cost'];
			if ($quantity > 0) $update['last_restocked_at'] = date('Y-m-d H:i:s');

			$this->db->where('stock_id', $existing->stock_id);
			$this->db->update('pharmacy_store_stock', $update);
			return $existing->stock_id;
		} else {
			// Insert new
			$insert = array(
				'store_id' => (int)$store_id,
				'drug_id' => (int)$drug_id,
				'batch_no' => $batch_no,
				'quantity' => (float)$quantity,
				'reorder_level' => isset($data['reorder_level']) ? (float)$data['reorder_level'] : 10,
				'max_stock_level' => isset($data['max_stock_level']) ? (float)$data['max_stock_level'] : 1000,
				'unit_cost' => isset($data['unit_cost']) ? (float)$data['unit_cost'] : 0,
				'expiry_date' => isset($data['expiry_date']) ? $data['expiry_date'] : null,
				'last_restocked_at' => date('Y-m-d H:i:s')
			);
			$this->db->insert('pharmacy_store_stock', $insert);
			return $this->db->insert_id();
		}
	}

	/**
	 * Deduct stock from a store (for dispensing)
	 */
	public function deduct_store_stock($store_id, $drug_id, $quantity, $batch_no = null, $user_id = null)
	{
		$current = $this->get_drug_stock_at_store($store_id, $drug_id, $batch_no);
		if ($current < $quantity) {
			return array('success' => false, 'error' => 'Insufficient stock at this store');
		}

		// Find the stock record(s) to deduct from
		$this->db->select('*');
		$this->db->from('pharmacy_store_stock');
		$this->db->where('store_id', (int)$store_id);
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->where('quantity >', 0);
		$this->db->where('InActive', 0);
		if ($batch_no !== null) {
			$this->db->where('batch_no', $batch_no);
		}
		$this->db->order_by('expiry_date', 'ASC'); // FEFO - First Expiry First Out
		$q = $this->db->get();

		if (!$q || $q->num_rows() === 0) {
			return array('success' => false, 'error' => 'No stock records found');
		}

		$remaining = (float)$quantity;
		foreach ($q->result() as $stock) {
			if ($remaining <= 0) break;

			$deduct = min((float)$stock->quantity, $remaining);
			$new_qty = (float)$stock->quantity - $deduct;

			$this->db->where('stock_id', $stock->stock_id);
			$this->db->update('pharmacy_store_stock', array(
				'quantity' => $new_qty,
				'last_dispensed_at' => date('Y-m-d H:i:s')
			));

			$remaining -= $deduct;
		}

		return array('success' => true, 'deducted' => $quantity);
	}

	// =========================================================================
	// STOCK TRANSFER METHODS
	// =========================================================================

	/**
	 * Generate unique transfer number
	 */
	private function _generate_transfer_no()
	{
		return 'TRF-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
	}

	/**
	 * Request stock transfer between stores
	 */
	public function request_transfer($from_store_id, $to_store_id, $drug_id, $quantity, $user_id, $notes = null, $batch_no = null)
	{
		// Validate stores
		$from_store = $this->get_store($from_store_id);
		$to_store = $this->get_store($to_store_id);

		if (!$from_store || !$to_store) {
			return array('success' => false, 'error' => 'Invalid store(s)');
		}

		if (!$to_store->can_receive_transfers) {
			return array('success' => false, 'error' => 'Destination store cannot receive transfers');
		}

		// Check source stock
		$available = $this->get_drug_stock_at_store($from_store_id, $drug_id, $batch_no);
		if ($available < $quantity) {
			return array('success' => false, 'error' => "Insufficient stock. Available: {$available}");
		}

		$transfer_no = $this->_generate_transfer_no();

		$this->db->insert('pharmacy_stock_transfer', array(
			'transfer_no' => $transfer_no,
			'from_store_id' => (int)$from_store_id,
			'to_store_id' => (int)$to_store_id,
			'drug_id' => (int)$drug_id,
			'batch_no' => $batch_no,
			'quantity' => (float)$quantity,
			'status' => 'PENDING',
			'requested_by' => $user_id,
			'notes' => $notes
		));

		return array('success' => true, 'transfer_id' => $this->db->insert_id(), 'transfer_no' => $transfer_no);
	}

	/**
	 * Approve transfer request
	 */
	public function approve_transfer($transfer_id, $user_id)
	{
		$transfer = $this->get_transfer($transfer_id);
		if (!$transfer) {
			return array('success' => false, 'error' => 'Transfer not found');
		}

		if ($transfer->status !== 'PENDING') {
			return array('success' => false, 'error' => 'Transfer is not pending');
		}

		// Verify stock still available
		$available = $this->get_drug_stock_at_store($transfer->from_store_id, $transfer->drug_id, $transfer->batch_no);
		if ($available < $transfer->quantity) {
			return array('success' => false, 'error' => "Insufficient stock. Available: {$available}");
		}

		// Deduct from source store
		$deduct = $this->deduct_store_stock($transfer->from_store_id, $transfer->drug_id, $transfer->quantity, $transfer->batch_no, $user_id);
		if (!$deduct['success']) {
			return $deduct;
		}

		$this->db->where('transfer_id', (int)$transfer_id);
		$this->db->update('pharmacy_stock_transfer', array(
			'status' => 'IN_TRANSIT',
			'approved_by' => $user_id,
			'approved_at' => date('Y-m-d H:i:s')
		));

		return array('success' => true);
	}

	/**
	 * Receive transfer at destination store
	 */
	public function receive_transfer($transfer_id, $user_id, $received_qty = null)
	{
		$transfer = $this->get_transfer($transfer_id);
		if (!$transfer) {
			return array('success' => false, 'error' => 'Transfer not found');
		}

		if ($transfer->status !== 'IN_TRANSIT' && $transfer->status !== 'APPROVED') {
			return array('success' => false, 'error' => 'Transfer is not in transit');
		}

		$qty = ($received_qty !== null) ? (float)$received_qty : (float)$transfer->quantity;

		// Add to destination store
		$this->upsert_store_stock($transfer->to_store_id, $transfer->drug_id, $qty, array(
			'batch_no' => $transfer->batch_no
		));

		$this->db->where('transfer_id', (int)$transfer_id);
		$this->db->update('pharmacy_stock_transfer', array(
			'status' => 'RECEIVED',
			'received_by' => $user_id,
			'received_at' => date('Y-m-d H:i:s'),
			'received_qty' => $qty
		));

		return array('success' => true);
	}

	/**
	 * Cancel transfer
	 */
	public function cancel_transfer($transfer_id, $user_id, $reason = null)
	{
		$transfer = $this->get_transfer($transfer_id);
		if (!$transfer) {
			return array('success' => false, 'error' => 'Transfer not found');
		}

		if ($transfer->status === 'RECEIVED' || $transfer->status === 'CANCELLED') {
			return array('success' => false, 'error' => 'Cannot cancel this transfer');
		}

		// If already in transit, return stock to source
		if ($transfer->status === 'IN_TRANSIT' || $transfer->status === 'APPROVED') {
			$this->upsert_store_stock($transfer->from_store_id, $transfer->drug_id, $transfer->quantity, array(
				'batch_no' => $transfer->batch_no
			));
		}

		$this->db->where('transfer_id', (int)$transfer_id);
		$this->db->update('pharmacy_stock_transfer', array(
			'status' => 'CANCELLED',
			'rejection_reason' => $reason
		));

		return array('success' => true);
	}

	/**
	 * Get transfer by ID
	 */
	public function get_transfer($transfer_id)
	{
		$this->db->select('t.*, fs.store_name as from_store_name, ts.store_name as to_store_name, d.drug_name');
		$this->db->from('pharmacy_stock_transfer t');
		$this->db->join('pharmacy_stores fs', 'fs.store_id = t.from_store_id', 'left');
		$this->db->join('pharmacy_stores ts', 'ts.store_id = t.to_store_id', 'left');
		$this->db->join('medicine_drug_name d', 'd.drug_id = t.drug_id', 'left');
		$this->db->where('t.transfer_id', (int)$transfer_id);
		$q = $this->db->get();
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get pending transfers for a store
	 */
	public function get_pending_transfers($store_id, $direction = 'incoming')
	{
		$this->db->select('t.*, fs.store_name as from_store_name, ts.store_name as to_store_name, d.drug_name');
		$this->db->from('pharmacy_stock_transfer t');
		$this->db->join('pharmacy_stores fs', 'fs.store_id = t.from_store_id', 'left');
		$this->db->join('pharmacy_stores ts', 'ts.store_id = t.to_store_id', 'left');
		$this->db->join('medicine_drug_name d', 'd.drug_id = t.drug_id', 'left');

		if ($direction === 'incoming') {
			$this->db->where('t.to_store_id', (int)$store_id);
			$this->db->where_in('t.status', array('APPROVED', 'IN_TRANSIT'));
		} else {
			$this->db->where('t.from_store_id', (int)$store_id);
			$this->db->where('t.status', 'PENDING');
		}

		$this->db->where('t.InActive', 0);
		$this->db->order_by('t.requested_at', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get transfer history
	 */
	public function get_transfer_history($filters = array())
	{
		$this->db->select('t.*, fs.store_name as from_store_name, ts.store_name as to_store_name, d.drug_name');
		$this->db->from('pharmacy_stock_transfer t');
		$this->db->join('pharmacy_stores fs', 'fs.store_id = t.from_store_id', 'left');
		$this->db->join('pharmacy_stores ts', 'ts.store_id = t.to_store_id', 'left');
		$this->db->join('medicine_drug_name d', 'd.drug_id = t.drug_id', 'left');
		$this->db->where('t.InActive', 0);

		if (!empty($filters['store_id'])) {
			$this->db->group_start();
			$this->db->where('t.from_store_id', (int)$filters['store_id']);
			$this->db->or_where('t.to_store_id', (int)$filters['store_id']);
			$this->db->group_end();
		}

		if (!empty($filters['status'])) {
			$this->db->where('t.status', $filters['status']);
		}

		if (!empty($filters['date_from'])) {
			$this->db->where('DATE(t.requested_at) >=', $filters['date_from']);
		}

		if (!empty($filters['date_to'])) {
			$this->db->where('DATE(t.requested_at) <=', $filters['date_to']);
		}

		$this->db->order_by('t.requested_at', 'DESC');

		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
		$offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
		$this->db->limit($limit, $offset);

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	// =========================================================================
	// STORE-AWARE DISPENSING
	// =========================================================================

	/**
	 * Dispense medication from a specific store
	 */
	public function dispense_from_store($store_id, $iop_med_id, $quantity, $user_id, $batch_no = null)
	{
		// Get prescription details
		$this->db->select('m.*, d.drug_name');
		$this->db->from('iop_medication m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
		$this->db->where('m.iop_med_id', (int)$iop_med_id);
		$q = $this->db->get();

		if (!$q || $q->num_rows() === 0) {
			return array('success' => false, 'error' => 'Prescription not found');
		}

		$med = $q->row();
		$drug_id = $med->medicine_id;

		// Check store stock
		$available = $this->get_drug_stock_at_store($store_id, $drug_id, $batch_no);
		if ($available < $quantity) {
			return array('success' => false, 'error' => "Insufficient stock at this store. Available: {$available}");
		}

		// Deduct stock
		$deduct = $this->deduct_store_stock($store_id, $drug_id, $quantity, $batch_no, $user_id);
		if (!$deduct['success']) {
			return $deduct;
		}

		// Log administration with store_id
		if ($this->table_exists('iop_medication_administration')) {
			$this->db->insert('iop_medication_administration', array(
				'iop_med_id' => (int)$iop_med_id,
				'store_id' => (int)$store_id,
				'dose_given' => (float)$quantity,
				'status' => 'DISPENSED',
				'dDateTime' => date('Y-m-d H:i:s'),
				'pharmacist_id' => $user_id,
				'batch_no' => $batch_no,
				'InActive' => 0
			));
		}

		return array('success' => true, 'dispensed' => $quantity);
	}

	/**
	 * Get user's assigned store
	 */
	public function get_user_store($user_id)
	{
		if (!$this->column_exists('users', 'default_pharmacy_store_id')) {
			return $this->get_store_by_code('MAIN');
		}

		$this->db->select('default_pharmacy_store_id');
		$this->db->from('users');
		$this->db->where('user_id', $user_id);
		$q = $this->db->get();

		if ($q && $q->num_rows() > 0) {
			$store_id = $q->row()->default_pharmacy_store_id;
			if ($store_id) {
				return $this->get_store($store_id);
			}
		}

		// Default to main store
		return $this->get_store_by_code('MAIN');
	}

	/**
	 * Assign user to a store
	 */
	public function assign_user_to_store($user_id, $store_id)
	{
		if (!$this->column_exists('users', 'default_pharmacy_store_id')) {
			return false;
		}

		$this->db->where('user_id', $user_id);
		return $this->db->update('users', array('default_pharmacy_store_id' => (int)$store_id));
	}

	// =========================================================================
	// REPORTING & ANALYTICS
	// =========================================================================

	/**
	 * Get low stock items across all stores
	 */
	public function get_low_stock_all_stores()
	{
		$this->db->select('ss.*, s.store_name, s.store_code, d.drug_name');
		$this->db->from('pharmacy_store_stock ss');
		$this->db->join('pharmacy_stores s', 's.store_id = ss.store_id', 'left');
		$this->db->join('medicine_drug_name d', 'd.drug_id = ss.drug_id', 'left');
		$this->db->where('ss.quantity <= ss.reorder_level');
		$this->db->where('ss.InActive', 0);
		$this->db->where('s.is_active', 1);
		$this->db->order_by('s.store_name', 'ASC');
		$this->db->order_by('d.drug_name', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get expiring items across all stores
	 */
	public function get_expiring_items_all_stores($days = 90)
	{
		$this->db->select('ss.*, s.store_name, s.store_code, d.drug_name');
		$this->db->from('pharmacy_store_stock ss');
		$this->db->join('pharmacy_stores s', 's.store_id = ss.store_id', 'left');
		$this->db->join('medicine_drug_name d', 'd.drug_id = ss.drug_id', 'left');
		$this->db->where('ss.expiry_date <=', date('Y-m-d', strtotime("+{$days} days")));
		$this->db->where('ss.expiry_date >=', date('Y-m-d'));
		$this->db->where('ss.quantity >', 0);
		$this->db->where('ss.InActive', 0);
		$this->db->where('s.is_active', 1);
		$this->db->order_by('ss.expiry_date', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get stock summary by store
	 */
	public function get_stock_summary_by_store()
	{
		$sql = "SELECT 
					s.store_id, s.store_code, s.store_name, s.store_type,
					COUNT(DISTINCT ss.drug_id) as total_items,
					SUM(ss.quantity) as total_quantity,
					SUM(ss.quantity * ss.unit_cost) as total_value,
					SUM(CASE WHEN ss.quantity <= ss.reorder_level THEN 1 ELSE 0 END) as low_stock_count,
					SUM(CASE WHEN ss.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 90 DAY) AND ss.expiry_date >= CURDATE() THEN 1 ELSE 0 END) as expiring_count
				FROM pharmacy_stores s
				LEFT JOIN pharmacy_store_stock ss ON ss.store_id = s.store_id AND ss.InActive = 0
				WHERE s.is_active = 1 AND s.InActive = 0
				GROUP BY s.store_id
				ORDER BY s.store_type, s.store_name";

		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Count pending transfers
	 */
	public function count_pending_transfers($store_id = null, $direction = 'all')
	{
		$this->db->from('pharmacy_stock_transfer');
		$this->db->where('InActive', 0);

		if ($store_id !== null) {
			if ($direction === 'incoming') {
				$this->db->where('to_store_id', (int)$store_id);
				$this->db->where_in('status', array('APPROVED', 'IN_TRANSIT'));
			} elseif ($direction === 'outgoing') {
				$this->db->where('from_store_id', (int)$store_id);
				$this->db->where('status', 'PENDING');
			} else {
				$this->db->group_start();
				$this->db->where('from_store_id', (int)$store_id);
				$this->db->or_where('to_store_id', (int)$store_id);
				$this->db->group_end();
				$this->db->where_in('status', array('PENDING', 'APPROVED', 'IN_TRANSIT'));
			}
		} else {
			$this->db->where_in('status', array('PENDING', 'APPROVED', 'IN_TRANSIT'));
		}

		return $this->db->count_all_results();
	}

	// =========================================================================
	// SECTION 2: CONTROLLED DRUGS MANAGEMENT
	// =========================================================================

	/**
	 * Ensure controlled drugs schema is in place
	 */
	public function ensure_controlled_drugs_schema()
	{
		$this->_create_controlled_drug_schedules_table();
		$this->_create_controlled_drug_dispensing_table();
		$this->_create_controlled_drug_register_table();
		$this->_add_controlled_drug_columns();
		$this->_seed_drug_schedules();
		return true;
	}

	/**
	 * Create controlled drug schedules table (DEA/Ghana FDA schedules)
	 */
	private function _create_controlled_drug_schedules_table()
	{
		if ($this->table_exists('controlled_drug_schedules')) {
			return;
		}

		$sql = "CREATE TABLE `controlled_drug_schedules` (
			`schedule_id` int(11) NOT NULL AUTO_INCREMENT,
			`schedule_code` varchar(20) NOT NULL,
			`schedule_name` varchar(100) NOT NULL,
			`description` text DEFAULT NULL,
			`requires_double_auth` tinyint(1) NOT NULL DEFAULT 1,
			`requires_witness` tinyint(1) NOT NULL DEFAULT 0,
			`max_days_supply` int(11) DEFAULT 30,
			`requires_id_verification` tinyint(1) NOT NULL DEFAULT 1,
			`requires_prescription_original` tinyint(1) NOT NULL DEFAULT 1,
			`audit_frequency_days` int(11) DEFAULT 7,
			`storage_requirements` varchar(255) DEFAULT NULL,
			`is_active` tinyint(1) NOT NULL DEFAULT 1,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`schedule_id`),
			UNIQUE KEY `uk_schedule_code` (`schedule_code`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create controlled drug dispensing log with double authentication
	 */
	private function _create_controlled_drug_dispensing_table()
	{
		if ($this->table_exists('controlled_drug_dispensing')) {
			return;
		}

		$sql = "CREATE TABLE `controlled_drug_dispensing` (
			`dispense_id` int(11) NOT NULL AUTO_INCREMENT,
			`iop_med_id` int(11) NOT NULL,
			`drug_id` int(11) NOT NULL,
			`patient_no` varchar(25) NOT NULL,
			`quantity_dispensed` decimal(12,2) NOT NULL,
			`batch_no` varchar(50) DEFAULT NULL,
			`store_id` int(11) DEFAULT NULL,
			`primary_pharmacist_id` varchar(25) NOT NULL,
			`primary_auth_at` datetime NOT NULL,
			`secondary_pharmacist_id` varchar(25) DEFAULT NULL,
			`secondary_auth_at` datetime DEFAULT NULL,
			`witness_id` varchar(25) DEFAULT NULL,
			`witness_at` datetime DEFAULT NULL,
			`patient_id_type` varchar(50) DEFAULT NULL,
			`patient_id_number` varchar(100) DEFAULT NULL,
			`patient_id_verified` tinyint(1) NOT NULL DEFAULT 0,
			`prescription_verified` tinyint(1) NOT NULL DEFAULT 0,
			`status` enum('PENDING_AUTH','AUTHORIZED','DISPENSED','CANCELLED','REJECTED') NOT NULL DEFAULT 'PENDING_AUTH',
			`rejection_reason` text DEFAULT NULL,
			`notes` text DEFAULT NULL,
			`dispensed_at` datetime DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`dispense_id`),
			KEY `idx_iop_med_id` (`iop_med_id`),
			KEY `idx_drug_id` (`drug_id`),
			KEY `idx_patient_no` (`patient_no`),
			KEY `idx_status` (`status`),
			KEY `idx_primary_pharmacist` (`primary_pharmacist_id`),
			KEY `idx_secondary_pharmacist` (`secondary_pharmacist_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create controlled drug register (running balance log)
	 */
	private function _create_controlled_drug_register_table()
	{
		if ($this->table_exists('controlled_drug_register')) {
			return;
		}

		$sql = "CREATE TABLE `controlled_drug_register` (
			`register_id` int(11) NOT NULL AUTO_INCREMENT,
			`drug_id` int(11) NOT NULL,
			`store_id` int(11) DEFAULT NULL,
			`batch_no` varchar(50) DEFAULT NULL,
			`transaction_type` enum('RECEIPT','DISPENSE','ADJUSTMENT','DESTRUCTION','TRANSFER_IN','TRANSFER_OUT') NOT NULL,
			`reference_type` varchar(50) DEFAULT NULL,
			`reference_id` int(11) DEFAULT NULL,
			`quantity_in` decimal(12,2) DEFAULT 0,
			`quantity_out` decimal(12,2) DEFAULT 0,
			`balance_before` decimal(12,2) NOT NULL,
			`balance_after` decimal(12,2) NOT NULL,
			`patient_no` varchar(25) DEFAULT NULL,
			`patient_name` varchar(200) DEFAULT NULL,
			`prescriber_name` varchar(200) DEFAULT NULL,
			`prescription_no` varchar(50) DEFAULT NULL,
			`supplier_name` varchar(200) DEFAULT NULL,
			`invoice_no` varchar(50) DEFAULT NULL,
			`witnessed_by` varchar(25) DEFAULT NULL,
			`recorded_by` varchar(25) NOT NULL,
			`recorded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`notes` text DEFAULT NULL,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`register_id`),
			KEY `idx_drug_id` (`drug_id`),
			KEY `idx_store_id` (`store_id`),
			KEY `idx_transaction_type` (`transaction_type`),
			KEY `idx_recorded_at` (`recorded_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Add controlled drug columns to medicine_drug_name
	 */
	private function _add_controlled_drug_columns()
	{
		if (!$this->table_exists('medicine_drug_name')) {
			return;
		}

		if (!$this->column_exists('medicine_drug_name', 'is_controlled')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `is_controlled` tinyint(1) NOT NULL DEFAULT 0");
		}

		if (!$this->column_exists('medicine_drug_name', 'schedule_id')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `schedule_id` int(11) DEFAULT NULL");
		}

		if (!$this->column_exists('medicine_drug_name', 'controlled_notes')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `controlled_notes` text DEFAULT NULL");
		}
	}

	/**
	 * Seed default drug schedules (Ghana FDA / International standards)
	 */
	private function _seed_drug_schedules()
	{
		$q = $this->db->get_where('controlled_drug_schedules', array('schedule_code' => 'SCHEDULE_I'));
		if ($q && $q->num_rows() > 0) {
			return;
		}

		$schedules = array(
			array(
				'schedule_code' => 'SCHEDULE_I',
				'schedule_name' => 'Schedule I - Prohibited',
				'description' => 'No accepted medical use, high abuse potential (e.g., Heroin, LSD)',
				'requires_double_auth' => 1,
				'requires_witness' => 1,
				'max_days_supply' => 0,
				'requires_id_verification' => 1,
				'requires_prescription_original' => 1,
				'audit_frequency_days' => 1,
				'storage_requirements' => 'Double-locked cabinet, separate from other drugs'
			),
			array(
				'schedule_code' => 'SCHEDULE_II',
				'schedule_name' => 'Schedule II - High Potential',
				'description' => 'High abuse potential with severe dependence (e.g., Morphine, Fentanyl, Oxycodone)',
				'requires_double_auth' => 1,
				'requires_witness' => 1,
				'max_days_supply' => 30,
				'requires_id_verification' => 1,
				'requires_prescription_original' => 1,
				'audit_frequency_days' => 7,
				'storage_requirements' => 'Locked cabinet with restricted access'
			),
			array(
				'schedule_code' => 'SCHEDULE_III',
				'schedule_name' => 'Schedule III - Moderate Potential',
				'description' => 'Moderate abuse potential (e.g., Codeine combinations, Ketamine)',
				'requires_double_auth' => 1,
				'requires_witness' => 0,
				'max_days_supply' => 90,
				'requires_id_verification' => 1,
				'requires_prescription_original' => 1,
				'audit_frequency_days' => 14,
				'storage_requirements' => 'Locked cabinet'
			),
			array(
				'schedule_code' => 'SCHEDULE_IV',
				'schedule_name' => 'Schedule IV - Low Potential',
				'description' => 'Low abuse potential (e.g., Diazepam, Tramadol, Zolpidem)',
				'requires_double_auth' => 0,
				'requires_witness' => 0,
				'max_days_supply' => 180,
				'requires_id_verification' => 1,
				'requires_prescription_original' => 0,
				'audit_frequency_days' => 30,
				'storage_requirements' => 'Secure storage area'
			),
			array(
				'schedule_code' => 'SCHEDULE_V',
				'schedule_name' => 'Schedule V - Minimal Potential',
				'description' => 'Minimal abuse potential (e.g., Cough preparations with codeine)',
				'requires_double_auth' => 0,
				'requires_witness' => 0,
				'max_days_supply' => 365,
				'requires_id_verification' => 0,
				'requires_prescription_original' => 0,
				'audit_frequency_days' => 90,
				'storage_requirements' => 'Standard pharmacy storage'
			)
		);

		foreach ($schedules as $s) {
			$this->db->insert('controlled_drug_schedules', $s);
		}
	}

	// =========================================================================
	// CONTROLLED DRUG SCHEDULE MANAGEMENT
	// =========================================================================

	/**
	 * Get all drug schedules
	 */
	public function get_drug_schedules()
	{
		$this->db->where('is_active', 1);
		$this->db->where('InActive', 0);
		$this->db->order_by('schedule_code', 'ASC');
		$q = $this->db->get('controlled_drug_schedules');
		return $q ? $q->result() : array();
	}

	/**
	 * Get schedule by ID
	 */
	public function get_schedule($schedule_id)
	{
		$q = $this->db->get_where('controlled_drug_schedules', array('schedule_id' => (int)$schedule_id));
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Check if drug is controlled
	 */
	public function is_drug_controlled($drug_id)
	{
		if (!$this->column_exists('medicine_drug_name', 'is_controlled')) {
			return false;
		}

		$this->db->select('is_controlled, schedule_id');
		$this->db->where('drug_id', (int)$drug_id);
		$q = $this->db->get('medicine_drug_name');

		if ($q && $q->num_rows() > 0) {
			$row = $q->row();
			return (int)$row->is_controlled === 1;
		}
		return false;
	}

	/**
	 * Get drug schedule info
	 */
	public function get_drug_schedule_info($drug_id)
	{
		if (!$this->column_exists('medicine_drug_name', 'schedule_id')) {
			return null;
		}

		$this->db->select('d.drug_id, d.drug_name, d.is_controlled, d.schedule_id, s.*');
		$this->db->from('medicine_drug_name d');
		$this->db->join('controlled_drug_schedules s', 's.schedule_id = d.schedule_id', 'left');
		$this->db->where('d.drug_id', (int)$drug_id);
		$q = $this->db->get();

		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Mark drug as controlled
	 */
	public function set_drug_controlled($drug_id, $schedule_id, $notes = null)
	{
		if (!$this->column_exists('medicine_drug_name', 'is_controlled')) {
			return false;
		}

		$this->db->where('drug_id', (int)$drug_id);
		return $this->db->update('medicine_drug_name', array(
			'is_controlled' => 1,
			'schedule_id' => (int)$schedule_id,
			'controlled_notes' => $notes
		));
	}

	/**
	 * Remove controlled status from drug
	 */
	public function unset_drug_controlled($drug_id)
	{
		if (!$this->column_exists('medicine_drug_name', 'is_controlled')) {
			return false;
		}

		$this->db->where('drug_id', (int)$drug_id);
		return $this->db->update('medicine_drug_name', array(
			'is_controlled' => 0,
			'schedule_id' => null,
			'controlled_notes' => null
		));
	}

	/**
	 * Get all controlled drugs
	 */
	public function get_controlled_drugs($filters = array())
	{
		if (!$this->column_exists('medicine_drug_name', 'is_controlled')) {
			return array();
		}

		$this->db->select('d.*, s.schedule_code, s.schedule_name, s.requires_double_auth, s.requires_witness');
		$this->db->from('medicine_drug_name d');
		$this->db->join('controlled_drug_schedules s', 's.schedule_id = d.schedule_id', 'left');
		$this->db->where('d.is_controlled', 1);
		$this->db->where('d.InActive', 0);

		if (!empty($filters['schedule_id'])) {
			$this->db->where('d.schedule_id', (int)$filters['schedule_id']);
		}

		if (!empty($filters['search'])) {
			$this->db->like('d.drug_name', $filters['search']);
		}

		$this->db->order_by('s.schedule_code', 'ASC');
		$this->db->order_by('d.drug_name', 'ASC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	// =========================================================================
	// CONTROLLED DRUG DISPENSING WITH DOUBLE AUTHENTICATION
	// =========================================================================

	/**
	 * Initiate controlled drug dispensing (requires second authorization)
	 */
	public function initiate_controlled_dispense($iop_med_id, $drug_id, $patient_no, $quantity, $primary_pharmacist_id, $data = array())
	{
		// Check if drug is controlled
		$schedule_info = $this->get_drug_schedule_info($drug_id);
		if (!$schedule_info || !$schedule_info->is_controlled) {
			return array('success' => false, 'error' => 'Drug is not marked as controlled');
		}

		// Check for existing pending dispense
		$existing = $this->get_pending_controlled_dispense($iop_med_id);
		if ($existing) {
			return array('success' => false, 'error' => 'A pending authorization already exists for this prescription', 'dispense_id' => $existing->dispense_id);
		}

		$insert = array(
			'iop_med_id' => (int)$iop_med_id,
			'drug_id' => (int)$drug_id,
			'patient_no' => $patient_no,
			'quantity_dispensed' => (float)$quantity,
			'batch_no' => isset($data['batch_no']) ? $data['batch_no'] : null,
			'store_id' => isset($data['store_id']) ? (int)$data['store_id'] : null,
			'primary_pharmacist_id' => $primary_pharmacist_id,
			'primary_auth_at' => date('Y-m-d H:i:s'),
			'patient_id_type' => isset($data['patient_id_type']) ? $data['patient_id_type'] : null,
			'patient_id_number' => isset($data['patient_id_number']) ? $data['patient_id_number'] : null,
			'patient_id_verified' => isset($data['patient_id_verified']) ? (int)$data['patient_id_verified'] : 0,
			'prescription_verified' => isset($data['prescription_verified']) ? (int)$data['prescription_verified'] : 0,
			'notes' => isset($data['notes']) ? $data['notes'] : null,
			'status' => $schedule_info->requires_double_auth ? 'PENDING_AUTH' : 'AUTHORIZED'
		);

		$this->db->insert('controlled_drug_dispensing', $insert);
		$dispense_id = $this->db->insert_id();

		$result = array(
			'success' => true,
			'dispense_id' => $dispense_id,
			'requires_second_auth' => (bool)$schedule_info->requires_double_auth,
			'requires_witness' => (bool)$schedule_info->requires_witness,
			'status' => $insert['status']
		);

		// If no second auth required, auto-complete
		if (!$schedule_info->requires_double_auth) {
			$result['message'] = 'Controlled drug authorized for dispensing';
		} else {
			$result['message'] = 'Awaiting second pharmacist authorization';
		}

		return $result;
	}

	/**
	 * Authorize controlled drug dispensing (second pharmacist)
	 */
	public function authorize_controlled_dispense($dispense_id, $secondary_pharmacist_id, $witness_id = null)
	{
		$dispense = $this->get_controlled_dispense($dispense_id);
		if (!$dispense) {
			return array('success' => false, 'error' => 'Dispense record not found');
		}

		if ($dispense->status !== 'PENDING_AUTH') {
			return array('success' => false, 'error' => 'This dispense is not pending authorization');
		}

		// Cannot authorize own request
		if ($dispense->primary_pharmacist_id === $secondary_pharmacist_id) {
			return array('success' => false, 'error' => 'Cannot authorize your own dispense request. A different pharmacist must authorize.');
		}

		// Check if witness is required
		$schedule_info = $this->get_drug_schedule_info($dispense->drug_id);
		if ($schedule_info && $schedule_info->requires_witness && !$witness_id) {
			return array('success' => false, 'error' => 'This schedule requires a witness');
		}

		$update = array(
			'secondary_pharmacist_id' => $secondary_pharmacist_id,
			'secondary_auth_at' => date('Y-m-d H:i:s'),
			'status' => 'AUTHORIZED'
		);

		if ($witness_id) {
			$update['witness_id'] = $witness_id;
			$update['witness_at'] = date('Y-m-d H:i:s');
		}

		$this->db->where('dispense_id', (int)$dispense_id);
		$this->db->update('controlled_drug_dispensing', $update);

		return array('success' => true, 'message' => 'Controlled drug dispensing authorized');
	}

	/**
	 * Complete controlled drug dispensing
	 */
	public function complete_controlled_dispense($dispense_id, $user_id)
	{
		$dispense = $this->get_controlled_dispense($dispense_id);
		if (!$dispense) {
			return array('success' => false, 'error' => 'Dispense record not found');
		}

		if ($dispense->status !== 'AUTHORIZED') {
			return array('success' => false, 'error' => 'This dispense is not authorized yet');
		}

		// Update status to dispensed
		$this->db->where('dispense_id', (int)$dispense_id);
		$this->db->update('controlled_drug_dispensing', array(
			'status' => 'DISPENSED',
			'dispensed_at' => date('Y-m-d H:i:s')
		));

		// Log to controlled drug register
		$this->log_controlled_drug_register(array(
			'drug_id' => $dispense->drug_id,
			'store_id' => $dispense->store_id,
			'batch_no' => $dispense->batch_no,
			'transaction_type' => 'DISPENSE',
			'reference_type' => 'controlled_drug_dispensing',
			'reference_id' => $dispense_id,
			'quantity_out' => $dispense->quantity_dispensed,
			'patient_no' => $dispense->patient_no,
			'recorded_by' => $user_id,
			'witnessed_by' => $dispense->witness_id
		));

		return array('success' => true, 'message' => 'Controlled drug dispensed and logged');
	}

	/**
	 * Reject controlled drug dispensing
	 */
	public function reject_controlled_dispense($dispense_id, $user_id, $reason)
	{
		$dispense = $this->get_controlled_dispense($dispense_id);
		if (!$dispense) {
			return array('success' => false, 'error' => 'Dispense record not found');
		}

		if ($dispense->status === 'DISPENSED') {
			return array('success' => false, 'error' => 'Cannot reject an already dispensed record');
		}

		$this->db->where('dispense_id', (int)$dispense_id);
		$this->db->update('controlled_drug_dispensing', array(
			'status' => 'REJECTED',
			'rejection_reason' => $reason,
			'secondary_pharmacist_id' => $user_id,
			'secondary_auth_at' => date('Y-m-d H:i:s')
		));

		return array('success' => true, 'message' => 'Controlled drug dispense request rejected');
	}

	/**
	 * Get pending controlled dispense for a prescription
	 */
	public function get_pending_controlled_dispense($iop_med_id)
	{
		$this->db->where('iop_med_id', (int)$iop_med_id);
		$this->db->where_in('status', array('PENDING_AUTH', 'AUTHORIZED'));
		$this->db->where('InActive', 0);
		$q = $this->db->get('controlled_drug_dispensing');
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get controlled dispense by ID
	 */
	public function get_controlled_dispense($dispense_id)
	{
		$this->db->select('cd.*, d.drug_name, s.schedule_code, s.schedule_name, s.requires_witness');
		$this->db->from('controlled_drug_dispensing cd');
		$this->db->join('medicine_drug_name d', 'd.drug_id = cd.drug_id', 'left');
		$this->db->join('controlled_drug_schedules s', 's.schedule_id = d.schedule_id', 'left');
		$this->db->where('cd.dispense_id', (int)$dispense_id);
		$q = $this->db->get();
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get pending authorizations for a pharmacist
	 */
	public function get_pending_controlled_authorizations($exclude_user_id = null)
	{
		$this->db->select('cd.*, d.drug_name, s.schedule_code, p.firstname, p.lastname');
		$this->db->from('controlled_drug_dispensing cd');
		$this->db->join('medicine_drug_name d', 'd.drug_id = cd.drug_id', 'left');
		$this->db->join('controlled_drug_schedules s', 's.schedule_id = d.schedule_id', 'left');
		$this->db->join('patient_personal_info p', 'p.patient_no = cd.patient_no', 'left');
		$this->db->where('cd.status', 'PENDING_AUTH');
		$this->db->where('cd.InActive', 0);

		if ($exclude_user_id) {
			$this->db->where('cd.primary_pharmacist_id !=', $exclude_user_id);
		}

		$this->db->order_by('cd.created_at', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Count pending controlled authorizations
	 */
	public function count_pending_controlled_authorizations($exclude_user_id = null)
	{
		$this->db->from('controlled_drug_dispensing');
		$this->db->where('status', 'PENDING_AUTH');
		$this->db->where('InActive', 0);

		if ($exclude_user_id) {
			$this->db->where('primary_pharmacist_id !=', $exclude_user_id);
		}

		return $this->db->count_all_results();
	}

	// =========================================================================
	// CONTROLLED DRUG REGISTER (RUNNING BALANCE LOG)
	// =========================================================================

	/**
	 * Log entry to controlled drug register
	 */
	public function log_controlled_drug_register($data)
	{
		$drug_id = (int)$data['drug_id'];
		$store_id = isset($data['store_id']) ? (int)$data['store_id'] : null;
		$batch_no = isset($data['batch_no']) ? $data['batch_no'] : null;

		// Get current balance
		$balance_before = $this->get_controlled_drug_balance($drug_id, $store_id, $batch_no);

		$qty_in = isset($data['quantity_in']) ? (float)$data['quantity_in'] : 0;
		$qty_out = isset($data['quantity_out']) ? (float)$data['quantity_out'] : 0;
		$balance_after = $balance_before + $qty_in - $qty_out;

		$insert = array(
			'drug_id' => $drug_id,
			'store_id' => $store_id,
			'batch_no' => $batch_no,
			'transaction_type' => $data['transaction_type'],
			'reference_type' => isset($data['reference_type']) ? $data['reference_type'] : null,
			'reference_id' => isset($data['reference_id']) ? (int)$data['reference_id'] : null,
			'quantity_in' => $qty_in,
			'quantity_out' => $qty_out,
			'balance_before' => $balance_before,
			'balance_after' => $balance_after,
			'patient_no' => isset($data['patient_no']) ? $data['patient_no'] : null,
			'patient_name' => isset($data['patient_name']) ? $data['patient_name'] : null,
			'prescriber_name' => isset($data['prescriber_name']) ? $data['prescriber_name'] : null,
			'prescription_no' => isset($data['prescription_no']) ? $data['prescription_no'] : null,
			'supplier_name' => isset($data['supplier_name']) ? $data['supplier_name'] : null,
			'invoice_no' => isset($data['invoice_no']) ? $data['invoice_no'] : null,
			'witnessed_by' => isset($data['witnessed_by']) ? $data['witnessed_by'] : null,
			'recorded_by' => $data['recorded_by'],
			'notes' => isset($data['notes']) ? $data['notes'] : null
		);

		$this->db->insert('controlled_drug_register', $insert);
		return $this->db->insert_id();
	}

	/**
	 * Get current balance for a controlled drug
	 */
	public function get_controlled_drug_balance($drug_id, $store_id = null, $batch_no = null)
	{
		$this->db->select('balance_after');
		$this->db->from('controlled_drug_register');
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->where('InActive', 0);

		if ($store_id !== null) {
			$this->db->where('store_id', (int)$store_id);
		}

		if ($batch_no !== null) {
			$this->db->where('batch_no', $batch_no);
		}

		$this->db->order_by('register_id', 'DESC');
		$this->db->limit(1);
		$q = $this->db->get();

		if ($q && $q->num_rows() > 0) {
			return (float)$q->row()->balance_after;
		}
		return 0;
	}

	/**
	 * Get controlled drug register entries
	 */
	public function get_controlled_drug_register($filters = array())
	{
		$this->db->select('r.*, d.drug_name, s.store_name');
		$this->db->from('controlled_drug_register r');
		$this->db->join('medicine_drug_name d', 'd.drug_id = r.drug_id', 'left');
		$this->db->join('pharmacy_stores s', 's.store_id = r.store_id', 'left');
		$this->db->where('r.InActive', 0);

		if (!empty($filters['drug_id'])) {
			$this->db->where('r.drug_id', (int)$filters['drug_id']);
		}

		if (!empty($filters['store_id'])) {
			$this->db->where('r.store_id', (int)$filters['store_id']);
		}

		if (!empty($filters['transaction_type'])) {
			$this->db->where('r.transaction_type', $filters['transaction_type']);
		}

		if (!empty($filters['date_from'])) {
			$this->db->where('DATE(r.recorded_at) >=', $filters['date_from']);
		}

		if (!empty($filters['date_to'])) {
			$this->db->where('DATE(r.recorded_at) <=', $filters['date_to']);
		}

		$this->db->order_by('r.recorded_at', 'DESC');

		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
		$offset = isset($filters['offset']) ? (int)$filters['offset'] : 0;
		$this->db->limit($limit, $offset);

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get controlled drug audit summary
	 */
	public function get_controlled_drug_audit_summary($drug_id, $store_id = null, $date_from = null, $date_to = null)
	{
		$this->db->select('
			SUM(quantity_in) as total_in,
			SUM(quantity_out) as total_out,
			COUNT(*) as transaction_count
		');
		$this->db->from('controlled_drug_register');
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->where('InActive', 0);

		if ($store_id !== null) {
			$this->db->where('store_id', (int)$store_id);
		}

		if ($date_from) {
			$this->db->where('DATE(recorded_at) >=', $date_from);
		}

		if ($date_to) {
			$this->db->where('DATE(recorded_at) <=', $date_to);
		}

		$q = $this->db->get();
		$summary = ($q && $q->num_rows() > 0) ? $q->row() : null;

		if ($summary) {
			$summary->current_balance = $this->get_controlled_drug_balance($drug_id, $store_id);
		}

		return $summary;
	}

	// =========================================================================
	// SECTION 3: GENERIC VS BRAND DRUG MAPPING
	// =========================================================================

	/**
	 * Ensure generic drug mapping schema is in place
	 */
	public function ensure_generic_mapping_schema()
	{
		$this->_create_drug_generic_master_table();
		$this->_create_drug_brand_mapping_table();
		$this->_add_generic_mapping_columns();
		return true;
	}

	/**
	 * Create drug generic master table
	 */
	private function _create_drug_generic_master_table()
	{
		if ($this->table_exists('drug_generic_master')) {
			return;
		}

		$sql = "CREATE TABLE `drug_generic_master` (
			`generic_id` int(11) NOT NULL AUTO_INCREMENT,
			`generic_name` varchar(255) NOT NULL,
			`generic_code` varchar(50) DEFAULT NULL,
			`therapeutic_class` varchar(100) DEFAULT NULL,
			`pharmacological_class` varchar(100) DEFAULT NULL,
			`atc_code` varchar(20) DEFAULT NULL,
			`description` text DEFAULT NULL,
			`common_dosage_forms` varchar(255) DEFAULT NULL,
			`common_strengths` varchar(255) DEFAULT NULL,
			`is_essential` tinyint(1) NOT NULL DEFAULT 0,
			`is_nhis_listed` tinyint(1) NOT NULL DEFAULT 0,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`generic_id`),
			UNIQUE KEY `uk_generic_name` (`generic_name`),
			KEY `idx_therapeutic_class` (`therapeutic_class`),
			KEY `idx_atc_code` (`atc_code`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create drug brand mapping table
	 */
	private function _create_drug_brand_mapping_table()
	{
		if ($this->table_exists('drug_brand_mapping')) {
			return;
		}

		$sql = "CREATE TABLE `drug_brand_mapping` (
			`mapping_id` int(11) NOT NULL AUTO_INCREMENT,
			`drug_id` int(11) NOT NULL,
			`generic_id` int(11) NOT NULL,
			`is_primary_brand` tinyint(1) NOT NULL DEFAULT 0,
			`bioequivalence_rating` varchar(10) DEFAULT NULL,
			`manufacturer` varchar(200) DEFAULT NULL,
			`country_of_origin` varchar(100) DEFAULT NULL,
			`notes` text DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`created_by` varchar(25) DEFAULT NULL,
			`InActive` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`mapping_id`),
			UNIQUE KEY `uk_drug_generic` (`drug_id`, `generic_id`),
			KEY `idx_generic_id` (`generic_id`),
			KEY `idx_drug_id` (`drug_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Add generic mapping columns to medicine_drug_name
	 */
	private function _add_generic_mapping_columns()
	{
		if (!$this->table_exists('medicine_drug_name')) {
			return;
		}

		if (!$this->column_exists('medicine_drug_name', 'primary_generic_id')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `primary_generic_id` int(11) DEFAULT NULL");
		}

		if (!$this->column_exists('medicine_drug_name', 'is_generic_drug')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `is_generic_drug` tinyint(1) NOT NULL DEFAULT 0");
		}

		if (!$this->column_exists('medicine_drug_name', 'manufacturer')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `manufacturer` varchar(200) DEFAULT NULL");
		}
	}

	// =========================================================================
	// GENERIC DRUG MANAGEMENT
	// =========================================================================

	/**
	 * Add a new generic drug
	 */
	public function add_generic_drug($data)
	{
		$insert = array(
			'generic_name' => $data['generic_name'],
			'generic_code' => isset($data['generic_code']) ? $data['generic_code'] : null,
			'therapeutic_class' => isset($data['therapeutic_class']) ? $data['therapeutic_class'] : null,
			'pharmacological_class' => isset($data['pharmacological_class']) ? $data['pharmacological_class'] : null,
			'atc_code' => isset($data['atc_code']) ? $data['atc_code'] : null,
			'description' => isset($data['description']) ? $data['description'] : null,
			'common_dosage_forms' => isset($data['common_dosage_forms']) ? $data['common_dosage_forms'] : null,
			'common_strengths' => isset($data['common_strengths']) ? $data['common_strengths'] : null,
			'is_essential' => isset($data['is_essential']) ? (int)$data['is_essential'] : 0,
			'is_nhis_listed' => isset($data['is_nhis_listed']) ? (int)$data['is_nhis_listed'] : 0
		);

		$this->db->insert('drug_generic_master', $insert);
		return $this->db->insert_id();
	}

	/**
	 * Update a generic drug
	 */
	public function update_generic_drug($generic_id, $data)
	{
		$update = array();
		$allowed = array('generic_name', 'generic_code', 'therapeutic_class', 'pharmacological_class', 
						 'atc_code', 'description', 'common_dosage_forms', 'common_strengths', 
						 'is_essential', 'is_nhis_listed');

		foreach ($allowed as $field) {
			if (isset($data[$field])) {
				$update[$field] = $data[$field];
			}
		}

		if (empty($update)) {
			return false;
		}

		$this->db->where('generic_id', (int)$generic_id);
		return $this->db->update('drug_generic_master', $update);
	}

	/**
	 * Get all generic drugs
	 */
	public function get_generic_drugs($filters = array())
	{
		$this->db->select('g.*, COUNT(m.mapping_id) as brand_count');
		$this->db->from('drug_generic_master g');
		$this->db->join('drug_brand_mapping m', 'm.generic_id = g.generic_id AND m.InActive = 0', 'left');
		$this->db->where('g.InActive', 0);

		if (!empty($filters['search'])) {
			$this->db->group_start();
			$this->db->like('g.generic_name', $filters['search']);
			$this->db->or_like('g.therapeutic_class', $filters['search']);
			$this->db->or_like('g.atc_code', $filters['search']);
			$this->db->group_end();
		}

		if (!empty($filters['therapeutic_class'])) {
			$this->db->where('g.therapeutic_class', $filters['therapeutic_class']);
		}

		if (isset($filters['is_essential']) && $filters['is_essential'] !== '') {
			$this->db->where('g.is_essential', (int)$filters['is_essential']);
		}

		if (isset($filters['is_nhis_listed']) && $filters['is_nhis_listed'] !== '') {
			$this->db->where('g.is_nhis_listed', (int)$filters['is_nhis_listed']);
		}

		$this->db->group_by('g.generic_id');
		$this->db->order_by('g.generic_name', 'ASC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get generic drug by ID
	 */
	public function get_generic_drug($generic_id)
	{
		$q = $this->db->get_where('drug_generic_master', array('generic_id' => (int)$generic_id, 'InActive' => 0));
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get therapeutic classes for dropdown
	 */
	public function get_therapeutic_classes()
	{
		$this->db->distinct();
		$this->db->select('therapeutic_class');
		$this->db->from('drug_generic_master');
		$this->db->where('therapeutic_class IS NOT NULL', NULL, FALSE);
		$this->db->where('therapeutic_class !=', '');
		$this->db->where('InActive', 0);
		$this->db->order_by('therapeutic_class', 'ASC');
		$q = $this->db->get();

		$classes = array();
		if ($q) {
			foreach ($q->result() as $row) {
				$classes[] = $row->therapeutic_class;
			}
		}
		return $classes;
	}

	// =========================================================================
	// BRAND MAPPING MANAGEMENT
	// =========================================================================

	/**
	 * Map a brand drug to a generic
	 */
	public function map_brand_to_generic($drug_id, $generic_id, $data = array())
	{
		// Check if mapping already exists
		$existing = $this->db->get_where('drug_brand_mapping', array(
			'drug_id' => (int)$drug_id,
			'generic_id' => (int)$generic_id,
			'InActive' => 0
		));

		if ($existing && $existing->num_rows() > 0) {
			return array('success' => false, 'error' => 'This brand is already mapped to this generic');
		}

		$insert = array(
			'drug_id' => (int)$drug_id,
			'generic_id' => (int)$generic_id,
			'is_primary_brand' => isset($data['is_primary_brand']) ? (int)$data['is_primary_brand'] : 0,
			'bioequivalence_rating' => isset($data['bioequivalence_rating']) ? $data['bioequivalence_rating'] : null,
			'manufacturer' => isset($data['manufacturer']) ? $data['manufacturer'] : null,
			'country_of_origin' => isset($data['country_of_origin']) ? $data['country_of_origin'] : null,
			'notes' => isset($data['notes']) ? $data['notes'] : null,
			'created_by' => isset($data['created_by']) ? $data['created_by'] : null
		);

		$this->db->insert('drug_brand_mapping', $insert);
		$mapping_id = $this->db->insert_id();

		// Update primary_generic_id on medicine_drug_name if this is primary
		if ($insert['is_primary_brand'] && $this->column_exists('medicine_drug_name', 'primary_generic_id')) {
			$this->db->where('drug_id', (int)$drug_id);
			$this->db->update('medicine_drug_name', array('primary_generic_id' => (int)$generic_id));
		}

		return array('success' => true, 'mapping_id' => $mapping_id);
	}

	/**
	 * Remove brand-generic mapping
	 */
	public function unmap_brand_from_generic($mapping_id)
	{
		$this->db->where('mapping_id', (int)$mapping_id);
		return $this->db->update('drug_brand_mapping', array('InActive' => 1));
	}

	/**
	 * Get brands for a generic drug
	 */
	public function get_brands_for_generic($generic_id)
	{
		$this->db->select('m.*, d.drug_name, d.nPrice, d.nStock, d.dosage_form, d.strength');
		$this->db->from('drug_brand_mapping m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.drug_id', 'left');
		$this->db->where('m.generic_id', (int)$generic_id);
		$this->db->where('m.InActive', 0);
		$this->db->where('d.InActive', 0);
		$this->db->order_by('m.is_primary_brand', 'DESC');
		$this->db->order_by('d.drug_name', 'ASC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get generic info for a brand drug
	 */
	public function get_generic_for_brand($drug_id)
	{
		$this->db->select('g.*, m.is_primary_brand, m.bioequivalence_rating');
		$this->db->from('drug_brand_mapping m');
		$this->db->join('drug_generic_master g', 'g.generic_id = m.generic_id', 'left');
		$this->db->where('m.drug_id', (int)$drug_id);
		$this->db->where('m.InActive', 0);
		$this->db->where('g.InActive', 0);
		$this->db->order_by('m.is_primary_brand', 'DESC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Find equivalent brands (same generic)
	 */
	public function find_equivalent_brands($drug_id, $include_out_of_stock = false)
	{
		// Get the generic(s) for this drug
		$generics = $this->get_generic_for_brand($drug_id);
		if (empty($generics)) {
			return array();
		}

		$generic_ids = array();
		foreach ($generics as $g) {
			$generic_ids[] = $g->generic_id;
		}

		$this->db->select('d.drug_id, d.drug_name, d.nPrice, d.nStock, d.dosage_form, d.strength, 
						   g.generic_name, m.is_primary_brand, m.bioequivalence_rating, m.manufacturer');
		$this->db->from('drug_brand_mapping m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.drug_id', 'left');
		$this->db->join('drug_generic_master g', 'g.generic_id = m.generic_id', 'left');
		$this->db->where_in('m.generic_id', $generic_ids);
		$this->db->where('m.drug_id !=', (int)$drug_id);
		$this->db->where('m.InActive', 0);
		$this->db->where('d.InActive', 0);

		if (!$include_out_of_stock) {
			$this->db->where('d.nStock >', 0);
		}

		$this->db->order_by('d.nPrice', 'ASC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get unmapped drugs (brands without generic mapping)
	 */
	public function get_unmapped_drugs($limit = 100)
	{
		$this->db->select('d.*');
		$this->db->from('medicine_drug_name d');
		$this->db->join('drug_brand_mapping m', 'm.drug_id = d.drug_id AND m.InActive = 0', 'left');
		$this->db->where('m.mapping_id IS NULL');
		$this->db->where('d.InActive', 0);
		$this->db->order_by('d.drug_name', 'ASC');
		$this->db->limit($limit);

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Search generics for autocomplete
	 */
	public function search_generics($term, $limit = 20)
	{
		$this->db->select('generic_id, generic_name, therapeutic_class, is_essential, is_nhis_listed');
		$this->db->from('drug_generic_master');
		$this->db->like('generic_name', $term);
		$this->db->where('InActive', 0);
		$this->db->order_by('generic_name', 'ASC');
		$this->db->limit($limit);

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get generic substitution suggestions for a prescription
	 */
	public function get_substitution_suggestions($drug_id, $max_suggestions = 5)
	{
		$equivalents = $this->find_equivalent_brands($drug_id, false);

		// Sort by price (cheapest first) and limit
		usort($equivalents, function($a, $b) {
			return $a->nPrice - $b->nPrice;
		});

		return array_slice($equivalents, 0, $max_suggestions);
	}

	/**
	 * Check if generic substitution is allowed for a drug
	 */
	public function is_substitution_allowed($drug_id)
	{
		// Check if drug has any generic mappings
		$generics = $this->get_generic_for_brand($drug_id);
		if (empty($generics)) {
			return false;
		}

		// Check if drug is controlled (controlled drugs may have restrictions)
		if ($this->is_drug_controlled($drug_id)) {
			$schedule_info = $this->get_drug_schedule_info($drug_id);
			// Schedule I and II typically don't allow substitution
			if ($schedule_info && in_array($schedule_info->schedule_code, array('SCHEDULE_I', 'SCHEDULE_II'))) {
				return false;
			}
		}

		return true;
	}

	// =========================================================================
	// SECTION 4: PRESCRIPTION LOCKING MECHANISM
	// =========================================================================

	/**
	 * Ensure prescription locking schema is in place
	 */
	public function ensure_prescription_locking_schema()
	{
		$this->_create_prescription_lock_table();
		$this->_create_prescription_audit_table();
		$this->_add_prescription_lock_columns();
		return true;
	}

	/**
	 * Create prescription lock table
	 */
	private function _create_prescription_lock_table()
	{
		if ($this->table_exists('prescription_locks')) {
			return;
		}

		$sql = "CREATE TABLE `prescription_locks` (
			`lock_id` int(11) NOT NULL AUTO_INCREMENT,
			`iop_med_id` int(11) NOT NULL,
			`patient_no` varchar(25) NOT NULL,
			`lock_status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
			`lock_reason` varchar(100) DEFAULT NULL,
			`locked_by` varchar(25) NOT NULL,
			`locked_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`unlocked_by` varchar(25) DEFAULT NULL,
			`unlocked_at` datetime DEFAULT NULL,
			`unlock_reason` text DEFAULT NULL,
			`expires_at` datetime DEFAULT NULL,
			PRIMARY KEY (`lock_id`),
			KEY `idx_iop_med_id` (`iop_med_id`),
			KEY `idx_patient_no` (`patient_no`),
			KEY `idx_lock_status` (`lock_status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create prescription audit table
	 */
	private function _create_prescription_audit_table()
	{
		if ($this->table_exists('prescription_status_audit')) {
			return;
		}

		$sql = "CREATE TABLE `prescription_status_audit` (
			`audit_id` int(11) NOT NULL AUTO_INCREMENT,
			`iop_med_id` int(11) NOT NULL,
			`patient_no` varchar(25) NOT NULL,
			`old_status` varchar(30) DEFAULT NULL,
			`new_status` varchar(30) NOT NULL,
			`action` varchar(50) NOT NULL,
			`action_by` varchar(25) NOT NULL,
			`action_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`notes` text DEFAULT NULL,
			`ip_address` varchar(45) DEFAULT NULL,
			PRIMARY KEY (`audit_id`),
			KEY `idx_iop_med_id` (`iop_med_id`),
			KEY `idx_patient_no` (`patient_no`),
			KEY `idx_action_at` (`action_at`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Add prescription lock columns to iop_medication
	 */
	private function _add_prescription_lock_columns()
	{
		if (!$this->table_exists('iop_medication')) {
			return;
		}

		if (!$this->column_exists('iop_medication', 'prescription_status')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `prescription_status` varchar(30) NOT NULL DEFAULT 'PENDING'");
		}

		if (!$this->column_exists('iop_medication', 'is_locked')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `is_locked` tinyint(1) NOT NULL DEFAULT 0");
		}

		if (!$this->column_exists('iop_medication', 'locked_by')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `locked_by` varchar(25) DEFAULT NULL");
		}

		if (!$this->column_exists('iop_medication', 'locked_at')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `locked_at` datetime DEFAULT NULL");
		}

		if (!$this->column_exists('iop_medication', 'verified_by')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `verified_by` varchar(25) DEFAULT NULL");
		}

		if (!$this->column_exists('iop_medication', 'verified_at')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `verified_at` datetime DEFAULT NULL");
		}

		if (!$this->column_exists('iop_medication', 'cancelled_by')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `cancelled_by` varchar(25) DEFAULT NULL");
		}

		if (!$this->column_exists('iop_medication', 'cancelled_at')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `cancelled_at` datetime DEFAULT NULL");
		}

		if (!$this->column_exists('iop_medication', 'cancel_reason')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `cancel_reason` text DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'billing_finalized_at')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `billing_finalized_at` datetime DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'billing_finalized_by')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `billing_finalized_by` varchar(25) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'original_medicine_id')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `original_medicine_id` int(11) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'original_medicine_text')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `original_medicine_text` varchar(255) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'substituted_medicine_id')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `substituted_medicine_id` int(11) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'substituted_medicine_text')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `substituted_medicine_text` varchar(255) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'substitution_reason')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `substitution_reason` text DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'substituted_by')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `substituted_by` varchar(25) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'substituted_at')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `substituted_at` datetime DEFAULT NULL");
		}
	}

	// =========================================================================
	// PRESCRIPTION STATUS CONSTANTS
	// =========================================================================

	/**
	 * Get valid prescription statuses
	 */
	public function get_prescription_statuses()
	{
		return array(
			'PENDING' => array('label' => 'Pending', 'class' => 'warning', 'icon' => 'clock-o'),
			'VERIFIED' => array('label' => 'Verified', 'class' => 'info', 'icon' => 'check'),
			'IN_PROGRESS' => array('label' => 'In Progress', 'class' => 'primary', 'icon' => 'spinner'),
			'PARTIAL' => array('label' => 'Partial', 'class' => 'warning', 'icon' => 'adjust'),
			'DISPENSED' => array('label' => 'Dispensed', 'class' => 'success', 'icon' => 'check-circle'),
			'CANCELLED' => array('label' => 'Cancelled', 'class' => 'danger', 'icon' => 'times-circle'),
			'ON_HOLD' => array('label' => 'On Hold', 'class' => 'default', 'icon' => 'pause'),
			'EXPIRED' => array('label' => 'Expired', 'class' => 'danger', 'icon' => 'calendar-times-o')
		);
	}

	/**
	 * Get allowed status transitions
	 */
	public function get_allowed_transitions($current_status)
	{
		$transitions = array(
			'PENDING' => array('VERIFIED', 'CANCELLED', 'ON_HOLD'),
			'VERIFIED' => array('IN_PROGRESS', 'CANCELLED', 'ON_HOLD'),
			'IN_PROGRESS' => array('PARTIAL', 'DISPENSED', 'CANCELLED', 'ON_HOLD'),
			'PARTIAL' => array('IN_PROGRESS', 'DISPENSED', 'CANCELLED'),
			'DISPENSED' => array(), // Terminal state
			'CANCELLED' => array(), // Terminal state
			'ON_HOLD' => array('PENDING', 'VERIFIED', 'CANCELLED'),
			'EXPIRED' => array() // Terminal state
		);

		return isset($transitions[$current_status]) ? $transitions[$current_status] : array();
	}

	/**
	 * Check if transition is valid
	 */
	public function is_valid_transition($from_status, $to_status)
	{
		$allowed = $this->get_allowed_transitions($from_status);
		return in_array($to_status, $allowed);
	}

	// =========================================================================
	// PRESCRIPTION LOCKING OPERATIONS
	// =========================================================================

	/**
	 * Lock a prescription for dispensing
	 */
	public function lock_prescription($iop_med_id, $user_id, $reason = 'Dispensing')
	{
		// Check if already locked
		$prescription = $this->get_prescription_lock_status($iop_med_id);
		if (!$prescription) {
			return array('success' => false, 'error' => 'Prescription not found');
		}

		if ($prescription->is_locked && $prescription->locked_by !== $user_id) {
			return array('success' => false, 'error' => 'Prescription is locked by another user: ' . $prescription->locked_by);
		}

		// Check if prescription can be locked (not in terminal state)
		if (in_array($prescription->prescription_status, array('DISPENSED', 'CANCELLED', 'EXPIRED'))) {
			return array('success' => false, 'error' => 'Cannot lock prescription in ' . $prescription->prescription_status . ' status');
		}

		// Create lock record
		$this->db->insert('prescription_locks', array(
			'iop_med_id' => (int)$iop_med_id,
			'patient_no' => $prescription->patient_no,
			'lock_status' => 'ACTIVE',
			'lock_reason' => $reason,
			'locked_by' => $user_id,
			'expires_at' => date('Y-m-d H:i:s', strtotime('+30 minutes'))
		));

		// Update prescription
		$this->db->where('iop_med_id', (int)$iop_med_id);
		$this->db->update('iop_medication', array(
			'is_locked' => 1,
			'locked_by' => $user_id,
			'locked_at' => date('Y-m-d H:i:s')
		));

		// Log audit
		$this->log_prescription_audit($iop_med_id, $prescription->patient_no, $prescription->prescription_status, $prescription->prescription_status, 'LOCK', $user_id, 'Locked for: ' . $reason);

		return array('success' => true, 'message' => 'Prescription locked successfully');
	}

	/**
	 * Unlock a prescription
	 */
	public function unlock_prescription($iop_med_id, $user_id, $reason = '')
	{
		$prescription = $this->get_prescription_lock_status($iop_med_id);
		if (!$prescription) {
			return array('success' => false, 'error' => 'Prescription not found');
		}

		if (!$prescription->is_locked) {
			return array('success' => false, 'error' => 'Prescription is not locked');
		}

		// Only the locker or admin can unlock
		$is_admin = $this->session->userdata('role') === 'admin';
		if ($prescription->locked_by !== $user_id && !$is_admin) {
			return array('success' => false, 'error' => 'Only the user who locked or an admin can unlock');
		}

		// Update lock record
		$this->db->where('iop_med_id', (int)$iop_med_id);
		$this->db->where('lock_status', 'ACTIVE');
		$this->db->update('prescription_locks', array(
			'lock_status' => 'RELEASED',
			'unlocked_by' => $user_id,
			'unlocked_at' => date('Y-m-d H:i:s'),
			'unlock_reason' => $reason
		));

		// Update prescription
		$this->db->where('iop_med_id', (int)$iop_med_id);
		$this->db->update('iop_medication', array(
			'is_locked' => 0,
			'locked_by' => null,
			'locked_at' => null
		));

		// Log audit
		$this->log_prescription_audit($iop_med_id, $prescription->patient_no, $prescription->prescription_status, $prescription->prescription_status, 'UNLOCK', $user_id, $reason);

		return array('success' => true, 'message' => 'Prescription unlocked');
	}

	/**
	 * Get prescription lock status
	 */
	public function get_prescription_lock_status($iop_med_id)
	{
		$this->db->from('iop_medication m');
		$has_patient_no = $this->column_exists('iop_medication', 'patient_no');
		if ($has_patient_no) {
			$this->db->select('m.*, p.firstname, p.lastname');
			$this->db->join('patient_personal_info p', 'p.patient_no = m.patient_no', 'left');
		} else {
			$this->db->select('m.*, i.patient_no, p.firstname, p.lastname');
			$this->db->join('patient_details_iop i', 'i.IO_ID = m.iop_id AND i.InActive = 0', 'left');
			$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
		}
		$this->db->where('m.iop_med_id', (int)$iop_med_id);
		$q = $this->db->get();

		if ($q && $q->num_rows() > 0) {
			$row = $q->row();
			// Set defaults if columns don't exist yet
			if (!isset($row->prescription_status)) $row->prescription_status = 'PENDING';
			if (!isset($row->is_locked)) $row->is_locked = 0;
			if (!isset($row->locked_by)) $row->locked_by = null;
			return $row;
		}
		return null;
	}

	/**
	 * Release expired locks
	 */
	public function release_expired_locks()
	{
		// Find expired locks
		$this->db->select('iop_med_id');
		$this->db->from('prescription_locks');
		$this->db->where('lock_status', 'ACTIVE');
		$this->db->where('expires_at <', date('Y-m-d H:i:s'));
		$q = $this->db->get();

		$released = 0;
		if ($q && $q->num_rows() > 0) {
			foreach ($q->result() as $lock) {
				$this->unlock_prescription($lock->iop_med_id, 'SYSTEM', 'Lock expired');
				$released++;
			}
		}

		return $released;
	}

	// =========================================================================
	// PRESCRIPTION STATUS OPERATIONS
	// =========================================================================

	/**
	 * Update prescription status
	 */
	public function update_prescription_status($iop_med_id, $new_status, $user_id, $notes = '', $create_billing_on_verify = true)
	{
		$prescription = $this->get_prescription_lock_status($iop_med_id);
		if (!$prescription) {
			return array('success' => false, 'error' => 'Prescription not found');
		}

		$current_status = isset($prescription->prescription_status) ? $prescription->prescription_status : 'PENDING';

		// Validate transition
		if (!$this->is_valid_transition($current_status, $new_status)) {
			return array('success' => false, 'error' => "Cannot transition from $current_status to $new_status");
		}

		// Check lock (except for CANCELLED which can override)
		if ($prescription->is_locked && $prescription->locked_by !== $user_id && $new_status !== 'CANCELLED') {
			return array('success' => false, 'error' => 'Prescription is locked by: ' . $prescription->locked_by);
		}

		// Update status
		$update = array('prescription_status' => $new_status);

		if ($new_status === 'VERIFIED') {
			$update['verified_by'] = $user_id;
			$update['verified_at'] = date('Y-m-d H:i:s');
		} elseif ($new_status === 'CANCELLED') {
			$update['cancelled_by'] = $user_id;
			$update['cancelled_at'] = date('Y-m-d H:i:s');
			$update['cancel_reason'] = $notes;
			$update['is_locked'] = 0;
			$update['locked_by'] = null;
		}

		$this->db->where('iop_med_id', (int)$iop_med_id);
		$this->db->update('iop_medication', $update);

		if ($new_status === 'VERIFIED' && $create_billing_on_verify) {
			try {
				$this->load->model('app/pharmacy_model');
				$this->pharmacy_model->create_or_update_pharmacy_bill($iop_med_id, $user_id);
			} catch (Exception $e) {
				log_message('error', 'PHARMACY_VERIFY_PBQ_CREATE_FAILED iop_med_id='.(int)$iop_med_id.' err='.$e->getMessage());
			}

			try {
				$this->load->model('app/unified_billing_model');
				$drug_id = isset($prescription->medicine_id) ? (int)$prescription->medicine_id : 0;
				$drug_name = isset($prescription->medicine_text) ? trim((string)$prescription->medicine_text) : '';
				$qty = isset($prescription->total_qty) ? (float)$prescription->total_qty : 1.0;
				$payer = (isset($prescription->is_nhis_covered) && (int)$prescription->is_nhis_covered === 1) ? 'NHIS' : 'CASH';
				$unit_price = 0.0;
				if ($drug_id > 0) {
					try {
						$this->load->model('app/billing_model');
						$rateRow = $this->billing_model->getNhisDrugRate($drug_id, isset($prescription->patient_no) ? (string)$prescription->patient_no : null);
						if ($rateRow) {
							if ($drug_name === '' && isset($rateRow->drug_name)) {
								$drug_name = (string)$rateRow->drug_name;
							}
							if (isset($rateRow->effective_price)) {
								$unit_price = (float)$rateRow->effective_price;
							} elseif (isset($rateRow->nPrice)) {
								$unit_price = (float)$rateRow->nPrice;
							}
						}
					} catch (Exception $e) {
						$unit_price = 0.0;
					}
					if ($unit_price <= 0.0) {
						$dr = $this->db->select('drug_name, nPrice, cash_price')
							->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
						if ($dr) {
							if ($drug_name === '' && isset($dr->drug_name)) $drug_name = (string)$dr->drug_name;
							$unit_price = (!empty($dr->cash_price) && (float)$dr->cash_price > 0) ? (float)$dr->cash_price : (float)$dr->nPrice;
						}
					}
				}
				$res = $this->unified_billing_model->add_to_billing_queue(array(
					'iop_id'        => isset($prescription->iop_id) ? (string)$prescription->iop_id : '',
					'patient_no'    => isset($prescription->patient_no) ? (string)$prescription->patient_no : '',
					'item_type'     => 'PHARMACY',
					'item_id'       => (string)(int)$iop_med_id,
					'item_name'     => $drug_name !== '' ? $drug_name : 'Medication',
					'unit_price'    => $unit_price,
					'quantity'      => $qty > 0 ? $qty : 1,
					'payer_type'    => $payer,
					'source_module' => 'PHARMACY',
					'source_ref'    => 'iop_id:' . (isset($prescription->iop_id) ? (string)$prescription->iop_id : '') . ':iop_medication:' . (string)(int)$iop_med_id,
					'requested_by'  => (string)$user_id,
					'notes'         => $notes,
				));
				if (!$res['success'] && isset($res['error']) && $res['error'] !== 'Item already in billing queue') {
					log_message('error', 'PHARMACY_VERIFY_BILLING_QUEUE_FAILED iop_med_id='.(int)$iop_med_id.' err='.(string)$res['error']);
				}
			} catch (Exception $e) {
				log_message('error', 'PHARMACY_VERIFY_BILLING_QUEUE_EXCEPTION iop_med_id='.(int)$iop_med_id.' err='.$e->getMessage());
			}
		}

		// Log audit
		$this->log_prescription_audit($iop_med_id, $prescription->patient_no, $current_status, $new_status, 'STATUS_CHANGE', $user_id, $notes);

		return array('success' => true, 'message' => "Status updated to $new_status");
	}

	/**
	 * Verify prescription (pharmacist review)
	 */
	public function verify_prescription($iop_med_id, $user_id, $notes = '')
	{
		return $this->update_prescription_status($iop_med_id, 'VERIFIED', $user_id, $notes);
	}

	public function verify_prescription_deferred($iop_med_id, $user_id, $notes = '')
	{
		return $this->update_prescription_status($iop_med_id, 'VERIFIED', $user_id, $notes, false);
	}

	/**
	 * Cancel prescription
	 */
	public function cancel_prescription($iop_med_id, $user_id, $reason)
	{
		if (empty($reason)) {
			return array('success' => false, 'error' => 'Cancellation reason is required');
		}
		return $this->update_prescription_status($iop_med_id, 'CANCELLED', $user_id, $reason);
	}

	/**
	 * Put prescription on hold
	 */
	public function hold_prescription($iop_med_id, $user_id, $reason = '')
	{
		return $this->update_prescription_status($iop_med_id, 'ON_HOLD', $user_id, $reason);
	}

	/**
	 * Resume prescription from hold
	 */
	public function resume_prescription($iop_med_id, $user_id, $notes = '')
	{
		$prescription = $this->get_prescription_lock_status($iop_med_id);
		if (!$prescription || $prescription->prescription_status !== 'ON_HOLD') {
			return array('success' => false, 'error' => 'Prescription is not on hold');
		}

		// Resume to PENDING or VERIFIED based on whether it was verified before
		$new_status = $prescription->verified_by ? 'VERIFIED' : 'PENDING';
		return $this->update_prescription_status($iop_med_id, $new_status, $user_id, $notes);
	}

	/**
	 * Log prescription audit
	 */
	public function log_prescription_audit($iop_med_id, $patient_no, $old_status, $new_status, $action, $user_id, $notes = '')
	{
		$this->db->insert('prescription_status_audit', array(
			'iop_med_id' => (int)$iop_med_id,
			'patient_no' => $patient_no,
			'old_status' => $old_status,
			'new_status' => $new_status,
			'action' => $action,
			'action_by' => $user_id,
			'notes' => $notes,
			'ip_address' => $this->input->ip_address()
		));
	}

	/**
	 * Get prescription audit history
	 */
	public function get_prescription_audit($iop_med_id)
	{
		$this->db->select('*');
		$this->db->from('prescription_status_audit');
		$this->db->where('iop_med_id', (int)$iop_med_id);
		$this->db->order_by('action_at', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get locked prescriptions
	 */
	public function get_locked_prescriptions($user_id = null)
	{
		$this->db->from('iop_medication m');
		$drug_fk = $this->column_exists('iop_medication', 'medicine_id') ? 'medicine_id' : 'drug_id';
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.' . $drug_fk, 'left');
		$has_patient_no = $this->column_exists('iop_medication', 'patient_no');
		if ($has_patient_no) {
			$this->db->select('m.*, d.drug_name, p.firstname, p.lastname');
			$this->db->join('patient_personal_info p', 'p.patient_no = m.patient_no', 'left');
		} else {
			$this->db->select('m.*, i.patient_no, d.drug_name, p.firstname, p.lastname');
			$this->db->join('patient_details_iop i', 'i.IO_ID = m.iop_id AND i.InActive = 0', 'left');
			$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
		}
		$this->db->where('m.is_locked', 1);

		if ($user_id) {
			$this->db->where('m.locked_by', $user_id);
		}

		$this->db->order_by('m.locked_at', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get prescriptions by status
	 */
	public function get_prescriptions_by_status($status, $limit = 100)
	{
		$this->db->from('iop_medication m');
		$drug_fk = $this->column_exists('iop_medication', 'medicine_id') ? 'medicine_id' : 'drug_id';
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.' . $drug_fk, 'left');
		$has_patient_no = $this->column_exists('iop_medication', 'patient_no');
		if ($has_patient_no) {
			$this->db->select('m.*, d.drug_name, p.firstname, p.lastname');
			$this->db->join('patient_personal_info p', 'p.patient_no = m.patient_no', 'left');
		} else {
			$this->db->select('m.*, i.patient_no, d.drug_name, p.firstname, p.lastname');
			$this->db->join('patient_details_iop i', 'i.IO_ID = m.iop_id AND i.InActive = 0', 'left');
			$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
		}
		$this->db->where('m.prescription_status', $status);
		$this->db->order_by('m.iop_med_id', 'DESC');
		$this->db->limit($limit);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Count prescriptions by status
	 */
	public function count_prescriptions_by_status()
	{
		$this->db->select('prescription_status, COUNT(*) as count');
		$this->db->from('iop_medication');
		// Use iop_med_id as fallback if created_at doesn't exist
		if ($this->column_exists('iop_medication', 'created_at')) {
			$this->db->where('DATE(created_at) >=', date('Y-m-d', strtotime('-30 days')));
		}
		$this->db->group_by('prescription_status');
		$q = $this->db->get();

		$counts = array();
		if ($q) {
			foreach ($q->result() as $row) {
				$counts[$row->prescription_status] = $row->count;
			}
		}
		return $counts;
	}

	// =========================================================================
	// SECTION 5: BATCH RECALL TRACKING
	// =========================================================================

	/**
	 * Ensure batch recall schema is in place
	 */
	public function ensure_batch_recall_schema()
	{
		$this->_create_batch_recall_table();
		$this->_create_recall_affected_patients_table();
		$this->_add_batch_recall_columns();
		return true;
	}

	/**
	 * Create batch recall table
	 */
	private function _create_batch_recall_table()
	{
		if ($this->table_exists('batch_recalls')) {
			return;
		}

		$sql = "CREATE TABLE `batch_recalls` (
			`recall_id` int(11) NOT NULL AUTO_INCREMENT,
			`drug_id` int(11) NOT NULL,
			`batch_number` varchar(50) NOT NULL,
			`recall_type` varchar(30) NOT NULL DEFAULT 'VOLUNTARY',
			`recall_class` varchar(10) DEFAULT NULL,
			`recall_reason` text NOT NULL,
			`manufacturer` varchar(200) DEFAULT NULL,
			`recall_date` date NOT NULL,
			`effective_date` date NOT NULL,
			`expiry_date` date DEFAULT NULL,
			`regulatory_reference` varchar(100) DEFAULT NULL,
			`instructions` text DEFAULT NULL,
			`status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
			`affected_qty_in_stock` decimal(18,2) DEFAULT 0,
			`affected_qty_dispensed` decimal(18,2) DEFAULT 0,
			`patients_notified` int(11) DEFAULT 0,
			`created_by` varchar(25) NOT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`resolved_by` varchar(25) DEFAULT NULL,
			`resolved_at` datetime DEFAULT NULL,
			`resolution_notes` text DEFAULT NULL,
			PRIMARY KEY (`recall_id`),
			KEY `idx_drug_id` (`drug_id`),
			KEY `idx_batch_number` (`batch_number`),
			KEY `idx_status` (`status`),
			KEY `idx_recall_date` (`recall_date`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create recall affected patients table
	 */
	private function _create_recall_affected_patients_table()
	{
		if ($this->table_exists('recall_affected_patients')) {
			return;
		}

		$sql = "CREATE TABLE `recall_affected_patients` (
			`affected_id` int(11) NOT NULL AUTO_INCREMENT,
			`recall_id` int(11) NOT NULL,
			`patient_no` varchar(25) NOT NULL,
			`iop_med_id` int(11) DEFAULT NULL,
			`dispensed_date` date DEFAULT NULL,
			`quantity_dispensed` decimal(18,2) DEFAULT 0,
			`notification_status` varchar(30) NOT NULL DEFAULT 'PENDING',
			`notification_method` varchar(50) DEFAULT NULL,
			`notified_at` datetime DEFAULT NULL,
			`notified_by` varchar(25) DEFAULT NULL,
			`patient_response` text DEFAULT NULL,
			`follow_up_required` tinyint(1) NOT NULL DEFAULT 0,
			`follow_up_notes` text DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`affected_id`),
			KEY `idx_recall_id` (`recall_id`),
			KEY `idx_patient_no` (`patient_no`),
			KEY `idx_notification_status` (`notification_status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Add batch recall columns to medication_stock
	 */
	private function _add_batch_recall_columns()
	{
		if ($this->table_exists('medication_stock')) {
			if (!$this->column_exists('medication_stock', 'is_recalled')) {
				$this->db->query("ALTER TABLE `medication_stock` ADD COLUMN `is_recalled` tinyint(1) NOT NULL DEFAULT 0");
			}
			if (!$this->column_exists('medication_stock', 'recall_id')) {
				$this->db->query("ALTER TABLE `medication_stock` ADD COLUMN `recall_id` int(11) DEFAULT NULL");
			}
		}

		if ($this->table_exists('iop_medication_administration')) {
			if (!$this->column_exists('iop_medication_administration', 'batch_recalled')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `batch_recalled` tinyint(1) NOT NULL DEFAULT 0");
			}
		}
	}

	// =========================================================================
	// BATCH RECALL OPERATIONS
	// =========================================================================

	/**
	 * Create a new batch recall
	 */
	public function create_batch_recall($data)
	{
		$insert = array(
			'drug_id' => (int)$data['drug_id'],
			'batch_number' => $data['batch_number'],
			'recall_type' => isset($data['recall_type']) ? $data['recall_type'] : 'VOLUNTARY',
			'recall_class' => isset($data['recall_class']) ? $data['recall_class'] : null,
			'recall_reason' => $data['recall_reason'],
			'manufacturer' => isset($data['manufacturer']) ? $data['manufacturer'] : null,
			'recall_date' => $data['recall_date'],
			'effective_date' => $data['effective_date'],
			'expiry_date' => isset($data['expiry_date']) ? $data['expiry_date'] : null,
			'regulatory_reference' => isset($data['regulatory_reference']) ? $data['regulatory_reference'] : null,
			'instructions' => isset($data['instructions']) ? $data['instructions'] : null,
			'status' => 'ACTIVE',
			'created_by' => $data['created_by']
		);

		$this->db->insert('batch_recalls', $insert);
		$recall_id = $this->db->insert_id();

		// Mark affected stock as recalled
		$this->_mark_stock_recalled($recall_id, $data['drug_id'], $data['batch_number']);

		// Find affected patients
		$this->_identify_affected_patients($recall_id, $data['drug_id'], $data['batch_number']);

		// Update recall stats
		$this->_update_recall_stats($recall_id);

		return array('success' => true, 'recall_id' => $recall_id);
	}

	/**
	 * Mark stock as recalled
	 */
	private function _mark_stock_recalled($recall_id, $drug_id, $batch_number)
	{
		if (!$this->table_exists('medication_stock')) {
			return;
		}

		$this->db->where('medication_id', (int)$drug_id);
		$this->db->where('batch_number', $batch_number);
		$this->db->update('medication_stock', array(
			'is_recalled' => 1,
			'recall_id' => $recall_id,
			'status' => 'RECALLED'
		));
	}

	/**
	 * Identify patients who received recalled batch
	 */
	private function _identify_affected_patients($recall_id, $drug_id, $batch_number)
	{
		// Find dispensed medications with this batch
		$this->db->select('a.iop_med_id, a.patient_no, a.qty, DATE(a.created_at) as dispensed_date');
		$this->db->from('iop_medication_administration a');
		$this->db->join('iop_medication m', 'm.iop_med_id = a.iop_med_id', 'left');
		$this->db->where('m.drug_id', (int)$drug_id);
		$this->db->where('a.batch_no', $batch_number);
		$q = $this->db->get();

		if ($q && $q->num_rows() > 0) {
			foreach ($q->result() as $row) {
				// Check if already recorded
				$existing = $this->db->get_where('recall_affected_patients', array(
					'recall_id' => $recall_id,
					'patient_no' => $row->patient_no,
					'iop_med_id' => $row->iop_med_id
				));

				if (!$existing || $existing->num_rows() == 0) {
					$this->db->insert('recall_affected_patients', array(
						'recall_id' => $recall_id,
						'patient_no' => $row->patient_no,
						'iop_med_id' => $row->iop_med_id,
						'dispensed_date' => $row->dispensed_date,
						'quantity_dispensed' => $row->qty,
						'notification_status' => 'PENDING'
					));
				}

				// Mark administration record
				$this->db->where('iop_med_id', $row->iop_med_id);
				$this->db->where('batch_no', $batch_number);
				$this->db->update('iop_medication_administration', array('batch_recalled' => 1));
			}
		}
	}

	/**
	 * Update recall statistics
	 */
	private function _update_recall_stats($recall_id)
	{
		$recall = $this->get_batch_recall($recall_id);
		if (!$recall) return;

		// Count affected stock
		$affected_stock = 0;
		if ($this->table_exists('medication_stock')) {
			$this->db->select_sum('quantity_remaining', 'total');
			$this->db->where('recall_id', $recall_id);
			$q = $this->db->get('medication_stock');
			if ($q && $q->num_rows() > 0) {
				$affected_stock = $q->row()->total ?: 0;
			}
		}

		// Count affected dispensed
		$this->db->select_sum('quantity_dispensed', 'total');
		$this->db->where('recall_id', $recall_id);
		$q = $this->db->get('recall_affected_patients');
		$affected_dispensed = ($q && $q->num_rows() > 0) ? ($q->row()->total ?: 0) : 0;

		// Count patients notified
		$this->db->where('recall_id', $recall_id);
		$this->db->where('notification_status', 'NOTIFIED');
		$patients_notified = $this->db->count_all_results('recall_affected_patients');

		$this->db->where('recall_id', $recall_id);
		$this->db->update('batch_recalls', array(
			'affected_qty_in_stock' => $affected_stock,
			'affected_qty_dispensed' => $affected_dispensed,
			'patients_notified' => $patients_notified
		));
	}

	/**
	 * Get batch recall by ID
	 */
	public function get_batch_recall($recall_id)
	{
		$this->db->select('r.*, d.drug_name');
		$this->db->from('batch_recalls r');
		$this->db->join('medicine_drug_name d', 'd.drug_id = r.drug_id', 'left');
		$this->db->where('r.recall_id', (int)$recall_id);
		$q = $this->db->get();
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get all batch recalls
	 */
	public function get_batch_recalls($filters = array())
	{
		$this->db->select('r.*, d.drug_name, 
			(SELECT COUNT(*) FROM recall_affected_patients WHERE recall_id = r.recall_id) as affected_patients');
		$this->db->from('batch_recalls r');
		$this->db->join('medicine_drug_name d', 'd.drug_id = r.drug_id', 'left');

		if (!empty($filters['status'])) {
			$this->db->where('r.status', $filters['status']);
		}

		if (!empty($filters['drug_id'])) {
			$this->db->where('r.drug_id', (int)$filters['drug_id']);
		}

		if (!empty($filters['date_from'])) {
			$this->db->where('r.recall_date >=', $filters['date_from']);
		}

		if (!empty($filters['date_to'])) {
			$this->db->where('r.recall_date <=', $filters['date_to']);
		}

		$this->db->order_by('r.recall_date', 'DESC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get active recalls
	 */
	public function get_active_recalls()
	{
		return $this->get_batch_recalls(array('status' => 'ACTIVE'));
	}

	/**
	 * Get affected patients for a recall
	 */
	public function get_recall_affected_patients($recall_id)
	{
		$this->db->select('a.*, p.firstname, p.lastname, p.phone, p.email');
		$this->db->from('recall_affected_patients a');
		$this->db->join('patient_personal_info p', 'p.patient_no = a.patient_no', 'left');
		$this->db->where('a.recall_id', (int)$recall_id);
		$this->db->order_by('a.notification_status', 'ASC');
		$this->db->order_by('a.dispensed_date', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Mark patient as notified
	 */
	public function mark_patient_notified($affected_id, $user_id, $method, $notes = '')
	{
		$this->db->where('affected_id', (int)$affected_id);
		$this->db->update('recall_affected_patients', array(
			'notification_status' => 'NOTIFIED',
			'notification_method' => $method,
			'notified_at' => date('Y-m-d H:i:s'),
			'notified_by' => $user_id,
			'patient_response' => $notes
		));

		// Get recall_id and update stats
		$q = $this->db->get_where('recall_affected_patients', array('affected_id' => (int)$affected_id));
		if ($q && $q->num_rows() > 0) {
			$this->_update_recall_stats($q->row()->recall_id);
		}

		return true;
	}

	/**
	 * Mark patient follow-up required
	 */
	public function mark_followup_required($affected_id, $notes = '')
	{
		$this->db->where('affected_id', (int)$affected_id);
		return $this->db->update('recall_affected_patients', array(
			'follow_up_required' => 1,
			'follow_up_notes' => $notes
		));
	}

	/**
	 * Resolve a batch recall
	 */
	public function resolve_batch_recall($recall_id, $user_id, $notes = '')
	{
		$this->db->where('recall_id', (int)$recall_id);
		$this->db->update('batch_recalls', array(
			'status' => 'RESOLVED',
			'resolved_by' => $user_id,
			'resolved_at' => date('Y-m-d H:i:s'),
			'resolution_notes' => $notes
		));

		return array('success' => true, 'message' => 'Recall resolved');
	}

	/**
	 * Check if a batch is recalled
	 */
	public function is_batch_recalled($drug_id, $batch_number)
	{
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->where('batch_number', $batch_number);
		$this->db->where('status', 'ACTIVE');
		$count = $this->db->count_all_results('batch_recalls');
		return $count > 0;
	}

	/**
	 * Get recall types
	 */
	public function get_recall_types()
	{
		return array(
			'VOLUNTARY' => 'Voluntary Recall',
			'MANDATORY' => 'Mandatory Recall (FDA)',
			'MARKET_WITHDRAWAL' => 'Market Withdrawal',
			'SAFETY_ALERT' => 'Safety Alert'
		);
	}

	/**
	 * Get recall classes
	 */
	public function get_recall_classes()
	{
		return array(
			'I' => 'Class I - Serious health hazard',
			'II' => 'Class II - May cause temporary health problems',
			'III' => 'Class III - Unlikely to cause health problems'
		);
	}

	/**
	 * Count active recalls
	 */
	public function count_active_recalls()
	{
		$this->db->where('status', 'ACTIVE');
		return $this->db->count_all_results('batch_recalls');
	}

	/**
	 * Count pending notifications
	 */
	public function count_pending_notifications()
	{
		$this->db->select('a.affected_id');
		$this->db->from('recall_affected_patients a');
		$this->db->join('batch_recalls r', 'r.recall_id = a.recall_id', 'left');
		$this->db->where('a.notification_status', 'PENDING');
		$this->db->where('r.status', 'ACTIVE');
		return $this->db->count_all_results();
	}

	/**
	 * Get recall summary report
	 */
	public function get_recall_summary_report($date_from = null, $date_to = null)
	{
		$this->db->select('
			COUNT(*) as total_recalls,
			SUM(CASE WHEN status = "ACTIVE" THEN 1 ELSE 0 END) as active_recalls,
			SUM(CASE WHEN status = "RESOLVED" THEN 1 ELSE 0 END) as resolved_recalls,
			SUM(affected_qty_in_stock) as total_stock_affected,
			SUM(affected_qty_dispensed) as total_dispensed_affected,
			SUM(patients_notified) as total_patients_notified
		');
		$this->db->from('batch_recalls');

		if ($date_from) {
			$this->db->where('recall_date >=', $date_from);
		}
		if ($date_to) {
			$this->db->where('recall_date <=', $date_to);
		}

		$q = $this->db->get();
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	// =========================================================================
	// SECTION 6: FINANCIAL RECONCILIATION ENGINE
	// =========================================================================

	/**
	 * Ensure financial reconciliation schema is in place
	 */
	public function ensure_reconciliation_schema()
	{
		$this->_create_reconciliation_table();
		$this->_create_reconciliation_items_table();
		$this->_create_reconciliation_discrepancies_table();
		return true;
	}

	/**
	 * Create reconciliation table
	 */
	private function _create_reconciliation_table()
	{
		if ($this->table_exists('pharmacy_reconciliations')) {
			return;
		}

		$sql = "CREATE TABLE `pharmacy_reconciliations` (
			`reconciliation_id` int(11) NOT NULL AUTO_INCREMENT,
			`store_id` int(11) DEFAULT NULL,
			`reconciliation_type` varchar(30) NOT NULL DEFAULT 'DAILY',
			`period_start` date NOT NULL,
			`period_end` date NOT NULL,
			`status` varchar(30) NOT NULL DEFAULT 'DRAFT',
			`total_sales` decimal(18,2) DEFAULT 0,
			`total_cost` decimal(18,2) DEFAULT 0,
			`gross_profit` decimal(18,2) DEFAULT 0,
			`total_dispensed_qty` decimal(18,2) DEFAULT 0,
			`total_dispensed_value` decimal(18,2) DEFAULT 0,
			`nhis_claims_amount` decimal(18,2) DEFAULT 0,
			`cash_collections` decimal(18,2) DEFAULT 0,
			`expected_cash` decimal(18,2) DEFAULT 0,
			`actual_cash` decimal(18,2) DEFAULT 0,
			`variance` decimal(18,2) DEFAULT 0,
			`variance_reason` text DEFAULT NULL,
			`stock_opening_value` decimal(18,2) DEFAULT 0,
			`stock_closing_value` decimal(18,2) DEFAULT 0,
			`stock_adjustments` decimal(18,2) DEFAULT 0,
			`items_count` int(11) DEFAULT 0,
			`discrepancies_count` int(11) DEFAULT 0,
			`notes` text DEFAULT NULL,
			`created_by` varchar(25) NOT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`approved_by` varchar(25) DEFAULT NULL,
			`approved_at` datetime DEFAULT NULL,
			`finalized_by` varchar(25) DEFAULT NULL,
			`finalized_at` datetime DEFAULT NULL,
			PRIMARY KEY (`reconciliation_id`),
			KEY `idx_store_id` (`store_id`),
			KEY `idx_status` (`status`),
			KEY `idx_period` (`period_start`, `period_end`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create reconciliation items table
	 */
	private function _create_reconciliation_items_table()
	{
		if ($this->table_exists('pharmacy_reconciliation_items')) {
			return;
		}

		$sql = "CREATE TABLE `pharmacy_reconciliation_items` (
			`item_id` int(11) NOT NULL AUTO_INCREMENT,
			`reconciliation_id` int(11) NOT NULL,
			`drug_id` int(11) NOT NULL,
			`opening_stock` decimal(18,2) DEFAULT 0,
			`received_qty` decimal(18,2) DEFAULT 0,
			`dispensed_qty` decimal(18,2) DEFAULT 0,
			`adjusted_qty` decimal(18,2) DEFAULT 0,
			`expected_closing` decimal(18,2) DEFAULT 0,
			`actual_closing` decimal(18,2) DEFAULT 0,
			`variance_qty` decimal(18,2) DEFAULT 0,
			`unit_cost` decimal(18,2) DEFAULT 0,
			`unit_price` decimal(18,2) DEFAULT 0,
			`total_cost` decimal(18,2) DEFAULT 0,
			`total_sales` decimal(18,2) DEFAULT 0,
			`has_discrepancy` tinyint(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`item_id`),
			KEY `idx_reconciliation_id` (`reconciliation_id`),
			KEY `idx_drug_id` (`drug_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create reconciliation discrepancies table
	 */
	private function _create_reconciliation_discrepancies_table()
	{
		if ($this->table_exists('pharmacy_reconciliation_discrepancies')) {
			return;
		}

		$sql = "CREATE TABLE `pharmacy_reconciliation_discrepancies` (
			`discrepancy_id` int(11) NOT NULL AUTO_INCREMENT,
			`reconciliation_id` int(11) NOT NULL,
			`item_id` int(11) DEFAULT NULL,
			`drug_id` int(11) DEFAULT NULL,
			`discrepancy_type` varchar(50) NOT NULL,
			`expected_value` decimal(18,2) DEFAULT 0,
			`actual_value` decimal(18,2) DEFAULT 0,
			`variance` decimal(18,2) DEFAULT 0,
			`explanation` text DEFAULT NULL,
			`resolution` text DEFAULT NULL,
			`status` varchar(30) NOT NULL DEFAULT 'OPEN',
			`resolved_by` varchar(25) DEFAULT NULL,
			`resolved_at` datetime DEFAULT NULL,
			`created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`discrepancy_id`),
			KEY `idx_reconciliation_id` (`reconciliation_id`),
			KEY `idx_status` (`status`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	// =========================================================================
	// RECONCILIATION OPERATIONS
	// =========================================================================

	/**
	 * Create a new reconciliation
	 */
	public function create_reconciliation($data)
	{
		$insert = array(
			'store_id' => isset($data['store_id']) ? (int)$data['store_id'] : null,
			'reconciliation_type' => isset($data['reconciliation_type']) ? $data['reconciliation_type'] : 'DAILY',
			'period_start' => $data['period_start'],
			'period_end' => $data['period_end'],
			'status' => 'DRAFT',
			'created_by' => $data['created_by']
		);

		$this->db->insert('pharmacy_reconciliations', $insert);
		$reconciliation_id = $this->db->insert_id();

		// Calculate reconciliation data
		$this->_calculate_reconciliation($reconciliation_id);

		return array('success' => true, 'reconciliation_id' => $reconciliation_id);
	}

	/**
	 * Calculate reconciliation data
	 */
	private function _calculate_reconciliation($reconciliation_id)
	{
		$recon = $this->get_reconciliation($reconciliation_id);
		if (!$recon) return;

		$period_start = $recon->period_start;
		$period_end = $recon->period_end;
		$store_id = $recon->store_id;

		// Get all drugs with activity in period
		$this->db->distinct();
		$this->db->select('m.drug_id');
		$this->db->from('iop_medication_administration a');
		$this->db->join('iop_medication m', 'm.iop_med_id = a.iop_med_id', 'left');
		$this->db->where('DATE(a.created_at) >=', $period_start);
		$this->db->where('DATE(a.created_at) <=', $period_end);
		$q = $this->db->get();

		$total_sales = 0;
		$total_cost = 0;
		$total_dispensed_qty = 0;
		$items_count = 0;
		$discrepancies_count = 0;

		if ($q && $q->num_rows() > 0) {
			foreach ($q->result() as $row) {
				$drug_id = $row->drug_id;
				if (!$drug_id) continue;

				// Get drug info
				$drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
				if (!$drug) continue;

				// Calculate dispensed qty in period
				$this->db->select_sum('a.qty', 'total_qty');
				$this->db->from('iop_medication_administration a');
				$this->db->join('iop_medication m', 'm.iop_med_id = a.iop_med_id', 'left');
				$this->db->where('m.drug_id', $drug_id);
				$this->db->where('DATE(a.created_at) >=', $period_start);
				$this->db->where('DATE(a.created_at) <=', $period_end);
				$dispensed = $this->db->get()->row();
				$dispensed_qty = $dispensed ? ($dispensed->total_qty ?: 0) : 0;

				$unit_cost = isset($drug->cost_price) ? $drug->cost_price : 0;
				$unit_price = $drug->nPrice;
				$item_cost = $dispensed_qty * $unit_cost;
				$item_sales = $dispensed_qty * $unit_price;

				// Insert item record
				$this->db->insert('pharmacy_reconciliation_items', array(
					'reconciliation_id' => $reconciliation_id,
					'drug_id' => $drug_id,
					'dispensed_qty' => $dispensed_qty,
					'actual_closing' => $drug->nStock,
					'unit_cost' => $unit_cost,
					'unit_price' => $unit_price,
					'total_cost' => $item_cost,
					'total_sales' => $item_sales
				));

				$total_sales += $item_sales;
				$total_cost += $item_cost;
				$total_dispensed_qty += $dispensed_qty;
				$items_count++;
			}
		}

		// Calculate cash collections from billing
		$this->db->select_sum('amount_paid', 'total_paid');
		$this->db->from('iop_billing');
		$this->db->where('DATE(created_at) >=', $period_start);
		$this->db->where('DATE(created_at) <=', $period_end);
		$billing = $this->db->get()->row();
		$cash_collections = $billing ? ($billing->total_paid ?: 0) : 0;

		// Update reconciliation totals
		$this->db->where('reconciliation_id', $reconciliation_id);
		$this->db->update('pharmacy_reconciliations', array(
			'total_sales' => $total_sales,
			'total_cost' => $total_cost,
			'gross_profit' => $total_sales - $total_cost,
			'total_dispensed_qty' => $total_dispensed_qty,
			'total_dispensed_value' => $total_sales,
			'cash_collections' => $cash_collections,
			'expected_cash' => $total_sales,
			'items_count' => $items_count,
			'discrepancies_count' => $discrepancies_count
		));
	}

	/**
	 * Get reconciliation by ID
	 */
	public function get_reconciliation($reconciliation_id)
	{
		$this->db->select('r.*, s.store_name');
		$this->db->from('pharmacy_reconciliations r');
		$this->db->join('pharmacy_stores s', 's.store_id = r.store_id', 'left');
		$this->db->where('r.reconciliation_id', (int)$reconciliation_id);
		$q = $this->db->get();
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Get all reconciliations
	 */
	public function get_reconciliations($filters = array())
	{
		$this->db->select('r.*, s.store_name');
		$this->db->from('pharmacy_reconciliations r');
		$this->db->join('pharmacy_stores s', 's.store_id = r.store_id', 'left');

		if (!empty($filters['status'])) {
			$this->db->where('r.status', $filters['status']);
		}

		if (!empty($filters['store_id'])) {
			$this->db->where('r.store_id', (int)$filters['store_id']);
		}

		if (!empty($filters['date_from'])) {
			$this->db->where('r.period_start >=', $filters['date_from']);
		}

		if (!empty($filters['date_to'])) {
			$this->db->where('r.period_end <=', $filters['date_to']);
		}

		$this->db->order_by('r.period_end', 'DESC');

		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get reconciliation items
	 */
	public function get_reconciliation_items($reconciliation_id)
	{
		$this->db->select('i.*, d.drug_name');
		$this->db->from('pharmacy_reconciliation_items i');
		$this->db->join('medicine_drug_name d', 'd.drug_id = i.drug_id', 'left');
		$this->db->where('i.reconciliation_id', (int)$reconciliation_id);
		$this->db->order_by('d.drug_name', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Get reconciliation discrepancies
	 */
	public function get_reconciliation_discrepancies($reconciliation_id)
	{
		$this->db->select('d.*, dr.drug_name');
		$this->db->from('pharmacy_reconciliation_discrepancies d');
		$this->db->join('medicine_drug_name dr', 'dr.drug_id = d.drug_id', 'left');
		$this->db->where('d.reconciliation_id', (int)$reconciliation_id);
		$this->db->order_by('d.created_at', 'DESC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	/**
	 * Update actual cash and calculate variance
	 */
	public function update_actual_cash($reconciliation_id, $actual_cash, $notes = '')
	{
		$recon = $this->get_reconciliation($reconciliation_id);
		if (!$recon) {
			return array('success' => false, 'error' => 'Reconciliation not found');
		}

		$variance = $actual_cash - $recon->expected_cash;

		$this->db->where('reconciliation_id', (int)$reconciliation_id);
		$this->db->update('pharmacy_reconciliations', array(
			'actual_cash' => $actual_cash,
			'variance' => $variance,
			'variance_reason' => $notes
		));

		// Create discrepancy if variance exists
		if (abs($variance) > 0.01) {
			$this->db->insert('pharmacy_reconciliation_discrepancies', array(
				'reconciliation_id' => $reconciliation_id,
				'discrepancy_type' => 'CASH_VARIANCE',
				'expected_value' => $recon->expected_cash,
				'actual_value' => $actual_cash,
				'variance' => $variance,
				'explanation' => $notes
			));

			$this->db->where('reconciliation_id', $reconciliation_id);
			$this->db->set('discrepancies_count', 'discrepancies_count + 1', false);
			$this->db->update('pharmacy_reconciliations');
		}

		return array('success' => true, 'variance' => $variance);
	}

	/**
	 * Submit reconciliation for approval
	 */
	public function submit_reconciliation($reconciliation_id, $user_id)
	{
		$recon = $this->get_reconciliation($reconciliation_id);
		if (!$recon || $recon->status !== 'DRAFT') {
			return array('success' => false, 'error' => 'Invalid reconciliation status');
		}

		$this->db->where('reconciliation_id', (int)$reconciliation_id);
		$this->db->update('pharmacy_reconciliations', array(
			'status' => 'PENDING_APPROVAL'
		));

		return array('success' => true, 'message' => 'Submitted for approval');
	}

	/**
	 * Approve reconciliation
	 */
	public function approve_reconciliation($reconciliation_id, $user_id)
	{
		$recon = $this->get_reconciliation($reconciliation_id);
		if (!$recon || $recon->status !== 'PENDING_APPROVAL') {
			return array('success' => false, 'error' => 'Invalid reconciliation status');
		}

		$this->db->where('reconciliation_id', (int)$reconciliation_id);
		$this->db->update('pharmacy_reconciliations', array(
			'status' => 'APPROVED',
			'approved_by' => $user_id,
			'approved_at' => date('Y-m-d H:i:s')
		));

		return array('success' => true, 'message' => 'Reconciliation approved');
	}

	/**
	 * Finalize reconciliation
	 */
	public function finalize_reconciliation($reconciliation_id, $user_id)
	{
		$recon = $this->get_reconciliation($reconciliation_id);
		if (!$recon || $recon->status !== 'APPROVED') {
			return array('success' => false, 'error' => 'Reconciliation must be approved first');
		}

		$this->db->where('reconciliation_id', (int)$reconciliation_id);
		$this->db->update('pharmacy_reconciliations', array(
			'status' => 'FINALIZED',
			'finalized_by' => $user_id,
			'finalized_at' => date('Y-m-d H:i:s')
		));

		return array('success' => true, 'message' => 'Reconciliation finalized');
	}

	/**
	 * Resolve discrepancy
	 */
	public function resolve_discrepancy($discrepancy_id, $user_id, $resolution)
	{
		$this->db->where('discrepancy_id', (int)$discrepancy_id);
		$this->db->update('pharmacy_reconciliation_discrepancies', array(
			'status' => 'RESOLVED',
			'resolution' => $resolution,
			'resolved_by' => $user_id,
			'resolved_at' => date('Y-m-d H:i:s')
		));

		return array('success' => true, 'message' => 'Discrepancy resolved');
	}

	/**
	 * Get reconciliation types
	 */
	public function get_reconciliation_types()
	{
		return array(
			'DAILY' => 'Daily Reconciliation',
			'WEEKLY' => 'Weekly Reconciliation',
			'MONTHLY' => 'Monthly Reconciliation',
			'QUARTERLY' => 'Quarterly Reconciliation',
			'ANNUAL' => 'Annual Reconciliation',
			'ADHOC' => 'Ad-hoc Reconciliation'
		);
	}

	/**
	 * Get reconciliation statuses
	 */
	public function get_reconciliation_statuses()
	{
		return array(
			'DRAFT' => array('label' => 'Draft', 'class' => 'default'),
			'PENDING_APPROVAL' => array('label' => 'Pending Approval', 'class' => 'warning'),
			'APPROVED' => array('label' => 'Approved', 'class' => 'info'),
			'FINALIZED' => array('label' => 'Finalized', 'class' => 'success'),
			'REJECTED' => array('label' => 'Rejected', 'class' => 'danger')
		);
	}

	/**
	 * Count pending reconciliations
	 */
	public function count_pending_reconciliations()
	{
		$this->db->where('status', 'PENDING_APPROVAL');
		return $this->db->count_all_results('pharmacy_reconciliations');
	}

	/**
	 * Get reconciliation summary
	 */
	public function get_reconciliation_summary($date_from = null, $date_to = null)
	{
		$this->db->select('
			COUNT(*) as total_reconciliations,
			SUM(total_sales) as total_sales,
			SUM(total_cost) as total_cost,
			SUM(gross_profit) as total_profit,
			SUM(cash_collections) as total_cash,
			SUM(ABS(variance)) as total_variance,
			SUM(discrepancies_count) as total_discrepancies
		');
		$this->db->from('pharmacy_reconciliations');
		$this->db->where('status', 'FINALIZED');

		if ($date_from) {
			$this->db->where('period_start >=', $date_from);
		}
		if ($date_to) {
			$this->db->where('period_end <=', $date_to);
		}

		$q = $this->db->get();
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}
}
