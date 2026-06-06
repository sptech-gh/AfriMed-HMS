<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Governance_model extends CI_Model
{
	private $_table_exists_cache = array();
	private $_column_exists_cache = array();
	private $_user_privileges_cache = array();

	public function __construct()
	{
		parent::__construct();
	}

	/* ================================================================== */
	/*  UTILITY                                                           */
	/* ================================================================== */

	public function table_exists($t)
	{
		$t = (string)$t;
		if (array_key_exists($t, $this->_table_exists_cache)) {
			return $this->_table_exists_cache[$t];
		}
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($t));
		$this->_table_exists_cache[$t] = ($q && $q->num_rows() > 0);
		return $this->_table_exists_cache[$t];
	}

	public function column_exists($table, $col)
	{
		$table = (string)$table;
		$col = (string)$col;
		$cache_key = $table . '.' . $col;
		if (array_key_exists($cache_key, $this->_column_exists_cache)) {
			return $this->_column_exists_cache[$cache_key];
		}
		$q = $this->db->query("SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE " . $this->db->escape($col));
		$this->_column_exists_cache[$cache_key] = ($q && $q->num_rows() > 0);
		return $this->_column_exists_cache[$cache_key];
	}

	/* ================================================================== */
	/*  SCHEMA INSTALL  (safe, idempotent)                                */
	/* ================================================================== */

	public function ensure_governance_schema()
	{
		$this->load->helper('schema_guard');
		$this->_install_schema_run_flags();
		if (schema_already_run('governance_schema')) {
			return;
		}
		$this->_install_user_privileges();
		$this->_install_privilege_audit_log();
		$this->_install_privilege_refresh_tracker();
		$this->_install_pharmacy_stock_requests();
		$this->_install_stock_audit_log();
		mark_schema_run('governance_schema');
	}

	private function _install_schema_run_flags()
	{
		if ($this->table_exists('schema_run_flags')) return;
		$this->db->query("
			CREATE TABLE `schema_run_flags` (
				`flag_key` VARCHAR(100) NOT NULL,
				`run_at` DATETIME NOT NULL,
				`schema_hash` VARCHAR(64) DEFAULT NULL,
				PRIMARY KEY (`flag_key`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		");
	}

	private function _install_user_privileges()
	{
		if ($this->table_exists('user_privileges')) return;
		$this->db->query("
			CREATE TABLE `user_privileges` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_id` VARCHAR(25) NOT NULL,
				`privilege_name` VARCHAR(100) NOT NULL,
				`granted_by` VARCHAR(25) NOT NULL,
				`granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`expiry_date` DATE DEFAULT NULL,
				`is_active` TINYINT(1) NOT NULL DEFAULT 1,
				`revoked_by` VARCHAR(25) DEFAULT NULL,
				`revoked_at` DATETIME DEFAULT NULL,
				`notes` TEXT DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_up_user` (`user_id`, `is_active`),
				KEY `idx_up_priv` (`privilege_name`, `is_active`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		");
	}

	private function _install_privilege_audit_log()
	{
		if ($this->table_exists('privilege_audit_log')) return;
		$this->db->query("
			CREATE TABLE `privilege_audit_log` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`user_id` VARCHAR(25) NOT NULL,
				`privilege_name` VARCHAR(100) NOT NULL,
				`action` VARCHAR(20) NOT NULL,
				`performed_by` VARCHAR(25) NOT NULL,
				`performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`ip_address` VARCHAR(45) DEFAULT NULL,
				`details` TEXT DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_pal_user` (`user_id`),
				KEY `idx_pal_date` (`performed_at`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		");
	}

	/**
	 * Install privilege refresh tracker table.
	 * Tracks when a user's privileges were last modified so sessions can auto-refresh.
	 */
	private function _install_privilege_refresh_tracker()
	{
		if ($this->table_exists('privilege_refresh_tracker')) return;
		$this->db->query("
			CREATE TABLE `privilege_refresh_tracker` (
				`user_id` VARCHAR(25) NOT NULL,
				`last_modified` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`user_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		");
	}

	private function _install_pharmacy_stock_requests()
	{
		if ($this->table_exists('pharmacy_stock_requests')) return;
		$this->db->query("
			CREATE TABLE `pharmacy_stock_requests` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`medication_id` INT NOT NULL,
				`request_type` VARCHAR(20) NOT NULL DEFAULT 'restock',
				`quantity` DECIMAL(18,2) NOT NULL DEFAULT 0,
				`batch_number` VARCHAR(50) DEFAULT NULL,
				`expiry_date` DATE DEFAULT NULL,
				`unit_cost` DECIMAL(18,2) DEFAULT 0,
				`selling_price` DECIMAL(18,2) DEFAULT 0,
				`supplier` VARCHAR(255) DEFAULT NULL,
				`reason` TEXT DEFAULT NULL,
				`requested_by` VARCHAR(25) NOT NULL,
				`approved_by` VARCHAR(25) DEFAULT NULL,
				`status` VARCHAR(20) NOT NULL DEFAULT 'pending',
				`admin_notes` TEXT DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`approved_at` DATETIME DEFAULT NULL,
				PRIMARY KEY (`id`),
				KEY `idx_psr_status` (`status`),
				KEY `idx_psr_med` (`medication_id`),
				KEY `idx_psr_req` (`requested_by`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		");
	}

	private function _install_stock_audit_log()
	{
		if ($this->table_exists('stock_audit_log')) return;
		$this->db->query("
			CREATE TABLE `stock_audit_log` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`medication_id` INT NOT NULL,
				`old_quantity` DECIMAL(18,2) NOT NULL DEFAULT 0,
				`new_quantity` DECIMAL(18,2) NOT NULL DEFAULT 0,
				`action_type` VARCHAR(30) NOT NULL,
				`reference_type` VARCHAR(30) DEFAULT NULL,
				`reference_id` INT UNSIGNED DEFAULT NULL,
				`performed_by` VARCHAR(25) NOT NULL,
				`approved_by` VARCHAR(25) DEFAULT NULL,
				`notes` TEXT DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `idx_sal_med` (`medication_id`),
				KEY `idx_sal_date` (`created_at`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8
		");
	}

	/* ================================================================== */
	/*  PART 1 — DYNAMIC PRIVILEGE SYSTEM                                 */
	/* ================================================================== */

	/**
	 * Canonical privilege names with labels and mapped role keys.
	 */
	public function get_privilege_definitions()
	{
		return array(
			'cashier_access'        => array('label' => 'Cashier / Billing Access',    'role_key' => 'cashier'),
			'cashier_invoice_view'  => array('label' => 'Cashier Invoice View (Temporary)', 'role_key' => 'temp_cashier_invoice_view'),
			'cashier_walkin_collect_access' => array('label' => 'Walk-In Payment Collection (Controlled)', 'role_key' => 'cashier_walkin_collect'),
			'walkin_access'         => array('label' => 'Walk-In Services Access (Controlled)', 'role_key' => 'walkin'),
			'pharmacy_access'       => array('label' => 'Pharmacy Access',             'role_key' => 'pharmacist'),
			'pharmacy_dispense_access' => array('label' => 'Pharmacy Dispensing (Nursing)', 'role_key' => 'pharmacy_dispense'),
			'lab_access'            => array('label' => 'Laboratory Access',           'role_key' => 'laboratory'),
			'procedure_unit_access' => array('label' => 'Procedure Unit Access',       'role_key' => 'procedure_unit'),
			'billing_access'        => array('label' => 'Billing Access',              'role_key' => 'cashier'),
			'reporting_access'      => array('label' => 'Reporting Access',            'role_key' => 'cashier'),
			'stock_approval_access' => array('label' => 'Stock Approval Access',       'role_key' => 'admin'),
			'receptionist_access'   => array('label' => 'Receptionist Access',          'role_key' => 'receptionist'),
			'nurse_access'          => array('label' => 'Nurse Module Access',         'role_key' => 'nurse'),
			'doctor_access'         => array('label' => 'Doctor Module Access',        'role_key' => 'doctor'),
			'sonography_access'     => array('label' => 'Sonography Access',           'role_key' => 'sonographer'),
			'radiology_access'      => array('label' => 'Radiology Access',            'role_key' => 'radiology'),
			'radiologist_access'    => array('label' => 'Radiologist Access',          'role_key' => 'radiologist'),
		);
	}

	/**
	 * Get all active privileges for a user (auto-expires).
	 */
	public function get_user_privileges($user_id)
	{
		$user_id = trim((string)$user_id);
		if ($user_id === '') return array();
		if (array_key_exists($user_id, $this->_user_privileges_cache)) {
			return $this->_user_privileges_cache[$user_id];
		}
		$this->load->helper('schema_guard');
		if (!schema_already_run('governance_schema') && !$this->table_exists('user_privileges')) return array();

		$today = date('Y-m-d');

		// Auto-expire overdue privileges
		$this->db->set('is_active', 0);
		$this->db->where('user_id', $user_id);
		$this->db->where('is_active', 1);
		$this->db->where('expiry_date IS NOT NULL');
		$this->db->where('expiry_date <', $today);
		$this->db->update('user_privileges');

		$this->db->where('user_id', $user_id);
		$this->db->where('is_active', 1);
		$this->db->order_by('granted_at', 'DESC');
		$this->_user_privileges_cache[$user_id] = $this->db->get('user_privileges')->result();
		return $this->_user_privileges_cache[$user_id];
	}

	/**
	 * Get active privilege names as flat array for a user.
	 */
	public function get_user_privilege_names($user_id)
	{
		$privs = $this->get_user_privileges($user_id);
		$names = array();
		foreach ($privs as $p) {
			$names[] = $p->privilege_name;
		}
		return $names;
	}

	/**
	 * Get role keys that a user has gained via dynamic privileges.
	 */
	public function get_dynamic_role_keys($user_id)
	{
		$privNames = $this->get_user_privilege_names($user_id);
		$defs = $this->get_privilege_definitions();
		$roleKeys = array();
		foreach ($privNames as $pn) {
			if (isset($defs[$pn]) && isset($defs[$pn]['role_key'])) {
				$roleKeys[] = $defs[$pn]['role_key'];
			}
		}
		return array_unique($roleKeys);
	}

	/**
	 * Check if user has a specific privilege.
	 */
	public function user_has_privilege($user_id, $privilege_name)
	{
		return in_array($privilege_name, $this->get_user_privilege_names($user_id), true);
	}

	/**
	 * Grant a privilege.
	 */
	public function grant_privilege($user_id, $privilege_name, $granted_by, $expiry_date = null, $notes = '')
	{
		$user_id = trim((string)$user_id);
		$privilege_name = trim((string)$privilege_name);
		$granted_by = trim((string)$granted_by);

		if ($user_id === '' || $privilege_name === '') {
			return array('ok' => false, 'error' => 'User ID and privilege name are required.');
		}

		// Check if already active
		if ($this->user_has_privilege($user_id, $privilege_name)) {
			return array('ok' => false, 'error' => 'User already has this privilege.');
		}

		$data = array(
			'user_id'        => $user_id,
			'privilege_name' => $privilege_name,
			'granted_by'     => $granted_by,
			'granted_at'     => date('Y-m-d H:i:s'),
			'expiry_date'    => ($expiry_date !== null && $expiry_date !== '') ? $expiry_date : null,
			'is_active'      => 1,
			'notes'          => $notes
		);
		$this->db->insert('user_privileges', $data);

		// Mark user for privilege refresh
		$this->_mark_privilege_refresh($user_id);

		$this->_log_privilege_audit($user_id, $privilege_name, 'GRANT', $granted_by,
			'Expiry: ' . ($expiry_date ? $expiry_date : 'None') . ($notes !== '' ? ' | ' . $notes : ''));

		log_message('info', 'PRIVILEGE_GRANTED user_id='.$user_id.' privilege='.$privilege_name.' by='.$granted_by);

		return array('ok' => true);
	}

	/**
	 * Revoke a privilege.
	 */
	public function revoke_privilege($privilege_id, $revoked_by, $notes = '')
	{
		$privilege_id = (int)$privilege_id;
		if ($privilege_id <= 0) {
			return array('ok' => false, 'error' => 'Invalid privilege ID.');
		}

		$row = $this->db->get_where('user_privileges', array('id' => $privilege_id))->row();
		if (!$row) {
			return array('ok' => false, 'error' => 'Privilege not found.');
		}

		$this->db->where('id', $privilege_id);
		$this->db->update('user_privileges', array(
			'is_active'  => 0,
			'revoked_by' => $revoked_by,
			'revoked_at' => date('Y-m-d H:i:s')
		));

		// Mark user for privilege refresh
		$this->_mark_privilege_refresh($row->user_id);

		$this->_log_privilege_audit($row->user_id, $row->privilege_name, 'REVOKE', $revoked_by, $notes);

		log_message('info', 'PRIVILEGE_REVOKED user_id='.$row->user_id.' privilege='.$row->privilege_name.' by='.$revoked_by);

		return array('ok' => true);
	}

	/**
	 * Mark a user's privileges as modified (triggers session refresh on next request).
	 */
	private function _mark_privilege_refresh($user_id)
	{
		if (!$this->table_exists('privilege_refresh_tracker')) {
			$this->_install_privilege_refresh_tracker();
		}
		$user_id = trim((string)$user_id);
		if ($user_id === '') return;

		$now = date('Y-m-d H:i:s');
		$exists = $this->db->get_where('privilege_refresh_tracker', array('user_id' => $user_id))->row();
		if ($exists) {
			$this->db->where('user_id', $user_id);
			$this->db->update('privilege_refresh_tracker', array('last_modified' => $now));
		} else {
			$this->db->insert('privilege_refresh_tracker', array('user_id' => $user_id, 'last_modified' => $now));
		}
	}

	/**
	 * Get the last privilege modification timestamp for a user.
	 * @return string|null  DateTime string or null if never modified
	 */
	public function get_privilege_last_modified($user_id)
	{
		$this->load->helper('schema_guard');
		if (!schema_already_run('governance_schema') && !$this->table_exists('privilege_refresh_tracker')) return null;
		$row = $this->db->get_where('privilege_refresh_tracker', array('user_id' => (string)$user_id))->row();
		return $row ? $row->last_modified : null;
	}

	/**
	 * Check if user's privileges need refresh (modified after session load).
	 * @param string $user_id
	 * @param string $session_loaded_at  DateTime when privileges were loaded into session
	 * @return bool
	 */
	public function needs_privilege_refresh($user_id, $session_loaded_at)
	{
		if (!$session_loaded_at) return true;
		$lastMod = $this->get_privilege_last_modified($user_id);
		if (!$lastMod) return false;
		return (strtotime($lastMod) > strtotime($session_loaded_at));
	}

	/**
	 * Get all privileges (with user info) for admin listing.
	 */
	public function get_all_privileges($active_only = false, $limit = 200)
	{
		$this->db->select('p.*, u.username, u.firstname, u.lastname');
		$this->db->from('user_privileges p');
		$this->db->join('users u', 'u.user_id = p.user_id', 'left');
		if ($active_only) {
			$this->db->where('p.is_active', 1);
		}
		$this->db->order_by('p.granted_at', 'DESC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	/**
	 * Get privilege history for a specific user.
	 */
	public function get_privilege_history($user_id, $limit = 50)
	{
		if (!$this->table_exists('privilege_audit_log')) return array();
		$this->db->where('user_id', (string)$user_id);
		$this->db->order_by('performed_at', 'DESC');
		$this->db->limit((int)$limit);
		return $this->db->get('privilege_audit_log')->result();
	}

	/**
	 * Get full audit log.
	 */
	public function get_all_privilege_audit($limit = 100)
	{
		if (!$this->table_exists('privilege_audit_log')) return array();
		$this->db->select('a.*, u.username as actor_name');
		$this->db->from('privilege_audit_log a');
		$this->db->join('users u', 'u.user_id = a.performed_by', 'left');
		$this->db->order_by('a.performed_at', 'DESC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	private function _log_privilege_audit($user_id, $privilege_name, $action, $performed_by, $details = '')
	{
		if (!$this->table_exists('privilege_audit_log')) return;
		$this->db->insert('privilege_audit_log', array(
			'user_id'        => $user_id,
			'privilege_name' => $privilege_name,
			'action'         => $action,
			'performed_by'   => $performed_by,
			'performed_at'   => date('Y-m-d H:i:s'),
			'ip_address'     => $this->input->ip_address(),
			'details'        => $details
		));
	}

	/**
	 * Get list of all users for privilege assignment dropdown.
	 */
	public function get_users_list()
	{
		$this->db->select('user_id, username, firstname, lastname, user_role');
		$this->db->from('users');
		$this->db->where('InActive', 0);
		$this->db->order_by('firstname', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Count active privileges.
	 */
	public function count_active_privileges()
	{
		if (!$this->table_exists('user_privileges')) return 0;
		$this->db->where('is_active', 1);
		return (int)$this->db->count_all_results('user_privileges');
	}

	/* ================================================================== */
	/*  PART 2 — PHARMACY STOCK APPROVAL WORKFLOW                         */
	/* ================================================================== */

	/**
	 * Create a stock request (pending approval).
	 */
	public function create_stock_request($data, $requested_by)
	{
		$insert = array(
			'medication_id'  => (int)$data['medication_id'],
			'request_type'   => trim((string)$data['request_type']),
			'quantity'       => (float)$data['quantity'],
			'batch_number'   => isset($data['batch_number']) ? trim((string)$data['batch_number']) : null,
			'expiry_date'    => isset($data['expiry_date']) && $data['expiry_date'] !== '' ? $data['expiry_date'] : null,
			'unit_cost'      => isset($data['unit_cost']) ? (float)$data['unit_cost'] : 0,
			'selling_price'  => isset($data['selling_price']) ? (float)$data['selling_price'] : 0,
			'supplier'       => isset($data['supplier']) ? trim((string)$data['supplier']) : null,
			'reason'         => isset($data['reason']) ? trim((string)$data['reason']) : null,
			'requested_by'   => (string)$requested_by,
			'status'         => 'pending',
			'created_at'     => date('Y-m-d H:i:s')
		);

		$this->db->insert('pharmacy_stock_requests', $insert);
		$id = $this->db->insert_id();

		return array('ok' => true, 'request_id' => $id);
	}

	/**
	 * Approve a stock request — actually applies the stock change.
	 */
	public function approve_stock_request($request_id, $approved_by, $admin_notes = '')
	{
		$request_id = (int)$request_id;
		$req = $this->db->get_where('pharmacy_stock_requests', array('id' => $request_id))->row();
		if (!$req) {
			return array('ok' => false, 'error' => 'Request not found.');
		}
		if ($req->status !== 'pending') {
			return array('ok' => false, 'error' => 'Request has already been ' . $req->status . '.');
		}

		// Get current stock
		$drug = $this->db->get_where('medicine_drug_name', array('drug_id' => (int)$req->medication_id))->row();
		if (!$drug) {
			return array('ok' => false, 'error' => 'Drug not found.');
		}

		$old_qty = (float)$drug->nStock;
		$change = (float)$req->quantity;
		$new_qty = $old_qty;

		if ($req->request_type === 'add' || $req->request_type === 'restock' || $req->request_type === 'batch_restock') {
			$new_qty = $old_qty + $change;
		} elseif ($req->request_type === 'adjustment') {
			// quantity is the change (can be negative)
			$new_qty = $old_qty + $change;
			if ($new_qty < 0) $new_qty = 0;
		}

		// Update master stock
		$this->db->where('drug_id', (int)$req->medication_id);
		$this->db->update('medicine_drug_name', array('nStock' => $new_qty));

		// If batch restock, also insert into medication_stock if the table exists
		if (($req->request_type === 'batch_restock' || $req->request_type === 'restock') && $this->table_exists('medication_stock')) {
			// Check if batch already exists - if so, update quantity instead of inserting
			$existingBatch = $this->db->get_where('medication_stock', array(
				'medication_id' => (int)$req->medication_id,
				'batch_number'  => $req->batch_number ? $req->batch_number : 'APPROVED-' . $request_id,
				'InActive'      => 0
			))->row();
			
			if ($existingBatch) {
				// Update existing batch
				$newBatchQty = (float)$existingBatch->quantity + abs($change);
				$this->db->where('stock_id', $existingBatch->stock_id);
				$this->db->update('medication_stock', array('quantity' => $newBatchQty));
			} else {
				// Insert new batch - use correct column name 'quantity'
				$batchData = array(
					'medication_id'  => (int)$req->medication_id,
					'batch_number'   => $req->batch_number ? $req->batch_number : 'APPROVED-' . $request_id,
					'quantity'       => abs($change),
					'expiry_date'    => $req->expiry_date,
					'unit_cost'      => (float)$req->unit_cost,
					'selling_price'  => (float)$req->selling_price,
					'supplier'       => $req->supplier,
					'received_date'  => date('Y-m-d'),
					'created_by'     => $approved_by,
					'created_at'     => date('Y-m-d H:i:s'),
					'InActive'       => 0
				);
				$this->db->insert('medication_stock', $batchData);
			}
		}

		// Mark approved
		$this->db->where('id', $request_id);
		$this->db->update('pharmacy_stock_requests', array(
			'status'      => 'approved',
			'approved_by' => $approved_by,
			'approved_at' => date('Y-m-d H:i:s'),
			'admin_notes' => $admin_notes
		));

		// Log to stock_audit_log
		$this->log_stock_audit(
			(int)$req->medication_id, $old_qty, $new_qty,
			'APPROVED_' . strtoupper($req->request_type),
			$req->requested_by, $approved_by,
			'Request #' . $request_id . '. ' . $admin_notes,
			'stock_request', $request_id
		);

		return array('ok' => true, 'old_qty' => $old_qty, 'new_qty' => $new_qty);
	}

	/**
	 * Reject a stock request — no stock change.
	 */
	public function reject_stock_request($request_id, $rejected_by, $admin_notes = '')
	{
		$request_id = (int)$request_id;
		$req = $this->db->get_where('pharmacy_stock_requests', array('id' => $request_id))->row();
		if (!$req) {
			return array('ok' => false, 'error' => 'Request not found.');
		}
		if ($req->status !== 'pending') {
			return array('ok' => false, 'error' => 'Request has already been ' . $req->status . '.');
		}

		$this->db->where('id', $request_id);
		$this->db->update('pharmacy_stock_requests', array(
			'status'      => 'rejected',
			'approved_by' => $rejected_by,
			'approved_at' => date('Y-m-d H:i:s'),
			'admin_notes' => $admin_notes
		));

		// Log rejection
		$this->log_stock_audit(
			(int)$req->medication_id, 0, 0,
			'REJECTED_' . strtoupper($req->request_type),
			$req->requested_by, $rejected_by,
			'Request #' . $request_id . ' REJECTED. ' . $admin_notes,
			'stock_request', $request_id
		);

		return array('ok' => true);
	}

	/**
	 * Get pending stock requests.
	 */
	public function get_pending_stock_requests($limit = 100)
	{
		if (!$this->table_exists('pharmacy_stock_requests')) return array();
		$this->db->select('r.*, d.drug_name, u.username as requester_name, u.firstname as requester_first, u.lastname as requester_last');
		$this->db->from('pharmacy_stock_requests r');
		$this->db->join('medicine_drug_name d', 'd.drug_id = r.medication_id', 'left');
		$this->db->join('users u', 'u.user_id = r.requested_by', 'left');
		$this->db->where('r.status', 'pending');
		$this->db->order_by('r.created_at', 'ASC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	/**
	 * Get all stock requests (for history).
	 */
	public function get_stock_requests($filters = array(), $limit = 100)
	{
		if (!$this->table_exists('pharmacy_stock_requests')) return array();
		$this->db->select('r.*, d.drug_name, u.username as requester_name, u.firstname as requester_first, u.lastname as requester_last, a.username as approver_name');
		$this->db->from('pharmacy_stock_requests r');
		$this->db->join('medicine_drug_name d', 'd.drug_id = r.medication_id', 'left');
		$this->db->join('users u', 'u.user_id = r.requested_by', 'left');
		$this->db->join('users a', 'a.user_id = r.approved_by', 'left');
		if (isset($filters['status']) && $filters['status'] !== '' && $filters['status'] !== 'all') {
			$this->db->where('r.status', $filters['status']);
		}
		if (isset($filters['medication_id']) && (int)$filters['medication_id'] > 0) {
			$this->db->where('r.medication_id', (int)$filters['medication_id']);
		}
		$this->db->order_by('r.created_at', 'DESC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	/**
	 * Count pending stock requests.
	 */
	public function count_pending_stock_requests()
	{
		if (!$this->table_exists('pharmacy_stock_requests')) return 0;
		$this->db->where('status', 'pending');
		return (int)$this->db->count_all_results('pharmacy_stock_requests');
	}

	/* ================================================================== */
	/*  PART 3 — STOCK AUDIT LOG                                          */
	/* ================================================================== */

	public function log_stock_audit($medication_id, $old_qty, $new_qty, $action_type, $performed_by, $approved_by = null, $notes = '', $ref_type = null, $ref_id = null)
	{
		if (!$this->table_exists('stock_audit_log')) return;
		$this->db->insert('stock_audit_log', array(
			'medication_id'  => (int)$medication_id,
			'old_quantity'   => (float)$old_qty,
			'new_quantity'   => (float)$new_qty,
			'action_type'    => (string)$action_type,
			'reference_type' => $ref_type,
			'reference_id'   => $ref_id ? (int)$ref_id : null,
			'performed_by'   => (string)$performed_by,
			'approved_by'    => $approved_by,
			'notes'          => $notes,
			'created_at'     => date('Y-m-d H:i:s')
		));
	}

	/**
	 * Get stock audit log entries.
	 */
	public function get_stock_audit_log($filters = array(), $limit = 100)
	{
		if (!$this->table_exists('stock_audit_log')) return array();
		$this->db->select('s.*, d.drug_name, u.username as performer_name, a.username as approver_name');
		$this->db->from('stock_audit_log s');
		$this->db->join('medicine_drug_name d', 'd.drug_id = s.medication_id', 'left');
		$this->db->join('users u', 'u.user_id = s.performed_by', 'left');
		$this->db->join('users a', 'a.user_id = s.approved_by', 'left');
		if (isset($filters['medication_id']) && (int)$filters['medication_id'] > 0) {
			$this->db->where('s.medication_id', (int)$filters['medication_id']);
		}
		if (isset($filters['date_from']) && $filters['date_from'] !== '') {
			$this->db->where('s.created_at >=', $filters['date_from'] . ' 00:00:00');
		}
		if (isset($filters['date_to']) && $filters['date_to'] !== '') {
			$this->db->where('s.created_at <=', $filters['date_to'] . ' 23:59:59');
		}
		$this->db->order_by('s.created_at', 'DESC');
		$this->db->limit((int)$limit);
		return $this->db->get()->result();
	}

	/**
	 * Summary counts for admin dashboard.
	 */
	public function get_governance_summary()
	{
		return array(
			'active_privileges'       => $this->count_active_privileges(),
			'pending_stock_requests'  => $this->count_pending_stock_requests(),
		);
	}

	/* ================================================================== */
	/*  DEFAULT ROLES & USERS SEED  (safe, idempotent)                    */
	/* ================================================================== */

	/**
	 * Ensure standard roles exist in user_roles and fix module fields.
	 * Also seeds default users for sonographer and cashier if missing.
	 * Password for all seeded users: hms2024  (MD5)
	 */
	public function ensure_default_roles_and_users()
	{
		$this->_fix_existing_role_modules();
		$this->_seed_missing_roles();
		$this->_seed_missing_designations();
		$this->_seed_default_users();
	}

	public function bootstrap_radiology_roles_and_users($password = 'hms2024')
	{
		$this->_seed_missing_roles();

		$password = (string)$password;
		if ($password === '') {
			$password = 'hms2024';
		}
		$pwdHash = password_hash($password, PASSWORD_BCRYPT);

		$targets = array(
			array('module' => 'radiology', 'username' => 'radiology', 'firstname' => 'Default', 'lastname' => 'Radiology', 'email' => 'radiology@hms.local'),
			array('module' => 'radiologist', 'username' => 'radiologist', 'firstname' => 'Default', 'lastname' => 'Radiologist', 'email' => 'radiologist@hms.local'),
		);

		$created = array('roles_checked' => 2, 'users_created' => 0);
		foreach ($targets as $t) {
			$exists = $this->db->query("SELECT id FROM users WHERE username = ? AND InActive = 0 LIMIT 1", array($t['username']));
			if ($exists && $exists->num_rows() > 0) {
				continue;
			}

			$roleRow = $this->db->query("SELECT role_id FROM user_roles WHERE module = ? AND InActive = 0 LIMIT 1", array($t['module']));
			if (!$roleRow || $roleRow->num_rows() === 0) {
				continue;
			}
			$roleId = (int)$roleRow->row()->role_id;

			$maxRow = $this->db->query("SELECT MAX(CAST(user_id AS UNSIGNED)) AS mx FROM users");
			$nextId = ($maxRow && $maxRow->row() && $maxRow->row()->mx) ? str_pad((int)$maxRow->row()->mx + 1, 5, '0', STR_PAD_LEFT) : '00020';

			$this->db->insert('users', array(
				'user_id'       => $nextId,
				'department'    => 2,
				'designation'   => 1,
				'user_role'     => $roleId,
				'cType'         => '',
				'title'         => 7,
				'lastname'      => $t['lastname'],
				'firstname'     => $t['firstname'],
				'middlename'    => '',
				'age'           => 30,
				'street'        => '',
				'subd_brgy'     => '',
				'province'      => '',
				'phone_no'      => '',
				'mobile_no'     => '',
				'gender'        => 1,
				'civil_status'  => 3,
				'birthday'      => '1994-01-01',
				'birthplace'    => '',
				'email_address' => $t['email'],
				'username'      => $t['username'],
				'password'      => $pwdHash,
				'picture'       => '',
				'InActive'      => 0,
			));

			$created['users_created']++;
			log_message('info', 'SEED_USER_CREATED username=' . $t['username'] . ' role=' . $t['module'] . ' user_id=' . $nextId);
		}

		return $created;
	}

	/**
	 * Fix module field on existing roles so RBAC normalises correctly.
	 */
	private function _fix_existing_role_modules()
	{
		// role_id=3 (Receptionist) — module was '0', should be 'receptionist'
		$this->db->where('role_id', 3);
		$this->db->where("(module = '0' OR module IS NULL OR module = '')", null, false);
		$this->db->update('user_roles', array('module' => 'receptionist'));

		// role_id=10 (Pharmacy) — module was NULL, should be 'pharmacy'
		$this->db->where('role_id', 10);
		$this->db->where("(module IS NULL OR module = '' OR module = '0')", null, false);
		$this->db->update('user_roles', array('module' => 'pharmacy'));

		// role_id=11 (Lab) — module was NULL, should be 'laboratory'
		$this->db->where('role_id', 11);
		$this->db->where("(module IS NULL OR module = '' OR module = '0')", null, false);
		$this->db->update('user_roles', array('module' => 'laboratory'));

		// role_id=7 (Nurse) — module was '0', should be 'nurse'
		$this->db->where('role_id', 7);
		$this->db->where("(module = '0' OR module IS NULL OR module = '')", null, false);
		$this->db->update('user_roles', array('module' => 'nurse'));
	}

	/**
	 * Seed any missing standard roles.
	 */
	private function _seed_missing_roles()
	{
		$required = array(
			array('module' => 'billing',     'role_name' => 'Cashier / Billing', 'role_description' => 'Handles invoices, payments, receipts'),
			array('module' => 'sonography',  'role_name' => 'Sonographer',       'role_description' => 'Ultrasound and imaging scans'),
			array('module' => 'procedure_unit',  'role_name' => 'Procedure Unit',       'role_description' => 'Handles procedure requests and execution workflow'),
			array('module' => 'radiology',   'role_name' => 'Radiology',         'role_description' => 'Radiology queue and imaging result entry'),
			array('module' => 'radiologist', 'role_name' => 'Radiologist',       'role_description' => 'Radiologist workflow and result authoring'),
		);

		foreach ($required as $role) {
			$q = $this->db->query("SELECT role_id FROM user_roles WHERE module = ? AND InActive = 0 LIMIT 1", array($role['module']));
			if ($q->num_rows() === 0) {
				$this->db->insert('user_roles', array(
					'module'           => $role['module'],
					'role_name'        => $role['role_name'],
					'role_description' => $role['role_description'],
					'InActive'         => 0,
				));
			}
		}
	}

	private function _seed_missing_designations()
	{
		if (!$this->table_exists('designation')) {
			return;
		}

		$required = array(
			array('designation' => 'Radiology',              'description' => 'Radiology department staff'),
			array('designation' => 'Radiologist',            'description' => 'Radiology physician (reporting / interpretation)'),
			array('designation' => 'Senior Radiologist',     'description' => 'Senior radiologist / approving consultant'),
			array('designation' => 'Radiology Technologist', 'description' => 'Radiology imaging staff (X-Ray / CT / ECG acquisition)'),
			array('designation' => 'Radiographer',           'description' => 'Imaging staff (radiography)'),
			array('designation' => 'Sonographer',            'description' => 'Ultrasound imaging staff'),
			array('designation' => 'Senior Sonographer',     'description' => 'Senior sonographer / approving staff'),
			array('designation' => 'ECG Technician',         'description' => 'ECG acquisition and workflow support'),
			array('designation' => 'CT Technologist',        'description' => 'CT scan acquisition and workflow support'),
			array('designation' => 'Lab Technician',         'description' => 'Laboratory technical staff'),
			array('designation' => 'Senior Lab Technician',  'description' => 'Senior laboratory technical staff'),
			array('designation' => 'Lab Supervisor',         'description' => 'Laboratory supervisor / verification authority'),
			array('designation' => 'Quality Manager',        'description' => 'Quality / safety oversight'),
			array('designation' => 'Procedure Unit',         'description' => 'Procedure unit staff'),
		);

		foreach ($required as $d) {
			$name = trim((string)$d['designation']);
			$desc = (string)$d['description'];
			if ($name === '') {
				continue;
			}

			$row = $this->db->query(
				"SELECT designation_id, InActive FROM designation WHERE LOWER(designation) = ? LIMIT 1",
				array(strtolower($name))
			);
			if ($row && $row->num_rows() > 0) {
				$existing = $row->row();
				if (isset($existing->InActive) && (int)$existing->InActive === 1) {
					$this->db->where('designation_id', (int)$existing->designation_id);
					$this->db->update('designation', array(
						'InActive'     => 0,
						'description'  => $desc,
					));
				}
				continue;
			}

			$this->db->insert('designation', array(
				'designation' => $name,
				'description' => $desc,
				'InActive'    => 0,
			));
		}
	}

	/**
	 * Seed default users for roles that have no users.
	 * Default password: hms2024
	 */
	private function _seed_default_users()
	{
		$activeRow = $this->db->query("SELECT COUNT(*) AS c FROM users WHERE InActive = 0")->row();
		if ($activeRow && (int)$activeRow->c > 0) {
			return;
		}

		$defaultPwd = md5('hms2024');

		$seeds = array(
			array(
				'module'    => 'sonography',
				'username'  => 'sonographer',
				'firstname' => 'Default',
				'lastname'  => 'Sonographer',
				'email'     => 'sonographer@hms.local',
			),
			array(
				'module'    => 'billing',
				'username'  => 'cashier',
				'firstname' => 'Default',
				'lastname'  => 'Cashier',
				'email'     => 'cashier@hms.local',
			),
		);

		foreach ($seeds as $s) {
			// Check if any active user with this username exists
			$exists = $this->db->query("SELECT id FROM users WHERE username = ? AND InActive = 0 LIMIT 1", array($s['username']));
			if ($exists->num_rows() > 0) continue;

			// Get the role_id for this module
			$roleRow = $this->db->query("SELECT role_id FROM user_roles WHERE module = ? AND InActive = 0 LIMIT 1", array($s['module']));
			if ($roleRow->num_rows() === 0) continue;
			$roleId = (int)$roleRow->row()->role_id;

			// Generate next user_id
			$maxRow = $this->db->query("SELECT MAX(CAST(user_id AS UNSIGNED)) AS mx FROM users");
			$nextId = ($maxRow->row() && $maxRow->row()->mx) ? str_pad((int)$maxRow->row()->mx + 1, 5, '0', STR_PAD_LEFT) : '00020';

			$this->db->insert('users', array(
				'user_id'       => $nextId,
				'department'    => 2,
				'designation'   => 1,
				'user_role'     => $roleId,
				'cType'         => '',
				'title'         => 7,
				'lastname'      => $s['lastname'],
				'firstname'     => $s['firstname'],
				'middlename'    => '',
				'age'           => 30,
				'street'        => '',
				'subd_brgy'     => '',
				'province'      => '',
				'phone_no'      => '',
				'mobile_no'     => '',
				'gender'        => 1,
				'civil_status'  => 3,
				'birthday'      => '1994-01-01',
				'birthplace'    => '',
				'email_address' => $s['email'],
				'username'      => $s['username'],
				'password'      => $defaultPwd,
				'picture'       => '',
				'InActive'      => 0,
			));

			log_message('info', 'SEED_USER_CREATED username=' . $s['username'] . ' role=' . $s['module'] . ' user_id=' . $nextId);
		}
	}
}
