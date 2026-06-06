<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . 'models/app/pharmacy_base_model.php');

/**
 * Pharmacy Dispense Model
 * 
 * Handles all dispensing operations:
 * - Prescription validation
 * - Dispense logging
 * - Status updates
 * - Medication administration records
 * 
 * Part of Phase 4 Performance Optimization.
 */
class Pharmacy_dispense_model extends Pharmacy_base_model
{
	private static $_schema_done = false;
	
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/pharmacy_stock_model');
	}
	
	// =========================================================================
	// SCHEMA MANAGEMENT
	// =========================================================================
	
	public function ensure_dispense_schema()
	{
		if (self::$_schema_done) return;
		self::$_schema_done = true;
		
		// Add columns to iop_medication
		if ($this->table_exists('iop_medication')) {
			$this->add_column_if_not_exists('iop_medication', 'dispensing_status', "varchar(20) DEFAULT 'PENDING'");
			$this->add_column_if_not_exists('iop_medication', 'payment_status', "varchar(20) DEFAULT 'PENDING'");
			$this->add_column_if_not_exists('iop_medication', 'prescribed_by', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('iop_medication', 'frequency', "varchar(100) DEFAULT NULL");
			
			// Performance indexes
			$this->add_index_if_not_exists('iop_medication', 'idx_iop_id', 'iop_id');
			$this->add_index_if_not_exists('iop_medication', 'idx_medicine_id', 'medicine_id');
			$this->add_index_if_not_exists('iop_medication', 'idx_dispense_status', 'dispensing_status');
			$this->add_index_if_not_exists('iop_medication', 'idx_date', 'dDate');
			$this->add_index_if_not_exists('iop_medication', 'idx_active_status', array('InActive', 'dispensing_status'));
		}
		
		// Add columns to iop_medication_administration
		if ($this->table_exists('iop_medication_administration')) {
			$this->add_column_if_not_exists('iop_medication_administration', 'pharmacist_id', "varchar(25) DEFAULT NULL");
			$this->add_column_if_not_exists('iop_medication_administration', 'batch_no', "varchar(50) DEFAULT NULL");
			
			// Performance indexes
			$this->add_index_if_not_exists('iop_medication_administration', 'idx_iop_med_id', 'iop_med_id');
			$this->add_index_if_not_exists('iop_medication_administration', 'idx_status', 'status');
			$this->add_index_if_not_exists('iop_medication_administration', 'idx_datetime', 'dDateTime');
		}
	}
	
	// =========================================================================
	// DISPENSE VALIDATION
	// =========================================================================
	
	/**
	 * Validate dispense request
	 */
	public function validate_dispense($iop_med_id, $qty, $status)
	{
		$this->ensure_dispense_schema();
		
		// Get prescription
		$this->db->select('m.*, d.drug_name, d.nStock');
		$this->db->from('iop_medication m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
		$this->db->where('m.iop_med_id', $iop_med_id);
		$this->db->where('m.InActive', 0);
		$q = $this->db->get();
		$med = $q ? $q->row() : null;
		
		if (!$med) {
			return array('valid' => false, 'error' => 'Prescription not found');
		}
		
		// Check current status
		$currentStatus = strtoupper(trim($med->dispensing_status ?? 'PENDING'));
		if ($currentStatus === 'DISPENSED') {
			return array('valid' => false, 'error' => 'Already fully dispensed');
		}
		
		// Calculate remaining
		$total = (float)$med->total_qty;
		$dispensed = $this->get_total_dispensed($iop_med_id);
		$remaining = $total - $dispensed;
		
		if ($qty > $remaining) {
			return array('valid' => false, 'error' => "Cannot dispense {$qty}. Only {$remaining} remaining.");
		}
		
		// Check stock
		$stock = (float)$med->nStock;
		if ($qty > $stock) {
			return array('valid' => false, 'error' => "Insufficient stock. Only {$stock} available.");
		}
		
		return array(
			'valid' => true,
			'medication' => $med,
			'remaining' => $remaining,
			'stock' => $stock
		);
	}
	
	/**
	 * Get total dispensed for a prescription
	 */
	public function get_total_dispensed($iop_med_id)
	{
		if (!$this->table_exists('iop_medication_administration')) return 0;
		
		$this->db->select('SUM(dose_given) as total');
		$this->db->from('iop_medication_administration');
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->where('InActive', 0);
		$this->db->where_in('status', array('DISPENSED', 'PARTIAL', 'RETURN'));
		
		$q = $this->db->get();
		$row = $q ? $q->row() : null;
		return $row ? (float)$row->total : 0;
	}
	
	// =========================================================================
	// DISPENSE OPERATIONS
	// =========================================================================
	
	/**
	 * Dispense medication
	 */
	public function dispense_medication($iop_med_id, $qty, $status, $notes, $user_id, $batch_no = '')
	{
		$this->load->model('app/pharmacy_model');
		$res = $this->pharmacy_model->dispense_medication($iop_med_id, $qty, $status, $notes, $user_id, $batch_no);
		if (!isset($res['ok']) || !$res['ok']) {
			$err = isset($res['errors']) ? implode(' ', (array)$res['errors']) : 'Dispense failed';
			return array('success' => false, 'error' => $err);
		}
		return array('success' => true, 'admin_id' => isset($res['admin_id']) ? (int)$res['admin_id'] : null);
	}
	
	/**
	 * Update dispensing status based on dispensed qty
	 */
	public function update_dispensing_status($iop_med_id)
	{
		$this->ensure_dispense_schema();
		
		// Get prescription
		$this->db->select('total_qty');
		$this->db->from('iop_medication');
		$this->db->where('iop_med_id', $iop_med_id);
		$q = $this->db->get();
		$med = $q ? $q->row() : null;
		
		if (!$med) return;
		
		$total = (float)$med->total_qty;
		$dispensed = $this->get_total_dispensed($iop_med_id);
		
		$new_status = 'PENDING';
		if ($dispensed >= $total) {
			$new_status = 'DISPENSED';
		} elseif ($dispensed > 0) {
			$new_status = 'PARTIAL';
		}
		
		$this->db->set('dispensing_status', $new_status);
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication');
	}
	
	/**
	 * Mark medication as unavailable
	 */
	public function mark_unavailable($iop_med_id, $user_id, $notes = '')
	{
		$this->ensure_dispense_schema();
		
		$this->db->set('dispensing_status', 'UNAVAILABLE');
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication');
		
		// Log audit
		$this->log_audit('iop_medication', $iop_med_id, 'MARK_UNAVAILABLE', null, 'UNAVAILABLE', $user_id, $notes);
		
		return true;
	}
	
	/**
	 * Mark medication as available (restore from unavailable)
	 */
	public function mark_available($iop_med_id, $user_id)
	{
		$this->ensure_dispense_schema();
		
		$this->db->set('dispensing_status', 'PENDING');
		$this->db->where('iop_med_id', $iop_med_id);
		$this->db->update('iop_medication');
		
		$this->log_audit('iop_medication', $iop_med_id, 'MARK_AVAILABLE', 'UNAVAILABLE', 'PENDING', $user_id);
		
		return true;
	}
	
	// =========================================================================
	// DISPENSE QUERIES (BATCH OPTIMIZED)
	// =========================================================================
	
	/**
	 * Get dispense map for multiple prescriptions (batch fetch)
	 */
	public function get_dispense_map($iop_med_ids)
	{
		if (empty($iop_med_ids)) return array();
		
		if (!$this->table_exists('iop_medication_administration')) {
			return array();
		}
		
		$this->db->select("iop_med_id, status, dose_given, dDateTime");
		$this->db->from('iop_medication_administration');
		$this->db->where('InActive', 0);
		$this->db->where_in('iop_med_id', $iop_med_ids);
		$this->db->order_by('admin_id', 'ASC');
		
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		
		$map = array();
		foreach ($rows as $r) {
			$mid = (string)$r->iop_med_id;
			if (!isset($map[$mid])) {
				$map[$mid] = array(
					'dispensed_qty' => 0.0,
					'latest_status' => '',
					'latest_at' => ''
				);
			}
			$st = strtoupper(trim($r->status));
			if ($st === 'DISPENSED' || $st === 'PARTIAL') {
				$map[$mid]['dispensed_qty'] += (float)$r->dose_given;
			}
			$map[$mid]['latest_status'] = $st;
			$map[$mid]['latest_at'] = $r->dDateTime;
		}
		
		return $map;
	}
	
	/**
	 * Get dispensed medications for billing
	 */
	public function get_dispensed_for_billing($iop_id)
	{
		$this->ensure_dispense_schema();
		
		$this->db->select('m.*, d.drug_name, d.nPrice');
		$this->db->from('iop_medication m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
		$this->db->where('m.iop_id', $iop_id);
		$this->db->where('m.InActive', 0);
		$this->db->where("m.dispensing_status != 'UNAVAILABLE'");
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
	
	// =========================================================================
	// SUMMARY COUNTS
	// =========================================================================
	
	/**
	 * Count pending prescriptions
	 */
	public function count_pending_prescriptions()
	{
		$this->ensure_dispense_schema();
		
		return $this->cache_get('count_pending_rx', function() {
			$sql = "SELECT COUNT(*) AS c FROM iop_medication m
				WHERE m.InActive = 0
				AND (m.dispensing_status IS NULL OR m.dispensing_status IN ('PENDING','PARTIAL','RESERVED'))
				AND EXISTS (SELECT 1 FROM patient_details_iop i WHERE i.IO_ID = m.iop_id AND i.InActive = 0)";
			$q = $this->db->query($sql);
			$r = $q ? $q->row() : null;
			return $r ? (int)$r->c : 0;
		}, 30);
	}
	
	/**
	 * Count dispensed today
	 */
	public function count_dispensed_today()
	{
		return $this->cache_get('count_dispensed_today', function() {
			if (!$this->table_exists('iop_medication_administration')) return 0;
			
			$this->db->where('InActive', 0);
			$this->db->where("DATE(dDateTime) = '" . date('Y-m-d') . "'");
			$this->db->where_in('status', array('DISPENSED', 'PARTIAL'));
			return $this->db->count_all_results('iop_medication_administration');
		}, 30);
	}
	
	/**
	 * Count partial prescriptions
	 */
	public function count_partial_prescriptions()
	{
		$this->ensure_dispense_schema();
		
		return $this->cache_get('count_partial_rx', function() {
			$this->db->where('dispensing_status', 'PARTIAL');
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('iop_medication');
		}, 30);
	}
	
	/**
	 * Count unavailable prescriptions
	 */
	public function count_unavailable_prescriptions()
	{
		$this->ensure_dispense_schema();
		
		return $this->cache_get('count_unavailable_rx', function() {
			$this->db->where('dispensing_status', 'UNAVAILABLE');
			$this->db->where('InActive', 0);
			return $this->db->count_all_results('iop_medication');
		}, 60);
	}
	
	// =========================================================================
	// BULK DISPENSE
	// =========================================================================
	
	/**
	 * Bulk dispense all eligible medications for a patient
	 */
	public function bulk_dispense_patient($iop_id, $user_id)
	{
		$this->ensure_dispense_schema();
		
		// Get all pending/partial medications
		$this->db->select('m.iop_med_id, m.medicine_id, m.total_qty, m.dispensing_status, d.nStock');
		$this->db->from('iop_medication m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
		$this->db->where('m.iop_id', $iop_id);
		$this->db->where('m.InActive', 0);
		$this->db->where_in('m.dispensing_status', array('PENDING', 'PARTIAL', 'RESERVED'));
		
		$q = $this->db->get();
		$meds = $q ? $q->result() : array();
		
		$results = array('success' => 0, 'failed' => 0, 'errors' => array());
		
		foreach ($meds as $med) {
			$dispensed = $this->get_total_dispensed($med->iop_med_id);
			$remaining = (float)$med->total_qty - $dispensed;
			$stock = (float)$med->nStock;
			
			if ($remaining <= 0) continue;
			
			$qty = min($remaining, $stock);
			if ($qty <= 0) {
				$results['failed']++;
				$results['errors'][] = "No stock for medication #{$med->iop_med_id}";
				continue;
			}
			
			$status = ($qty >= $remaining) ? 'DISPENSED' : 'PARTIAL';
			$result = $this->dispense_medication($med->iop_med_id, $qty, $status, 'Bulk dispense', $user_id);
			
			if ($result['success']) {
				$results['success']++;
			} else {
				$results['failed']++;
				$results['errors'][] = $result['error'];
			}
		}
		
		// Invalidate cache
		$this->cache_invalidate('count_');
		
		return $results;
	}
}
