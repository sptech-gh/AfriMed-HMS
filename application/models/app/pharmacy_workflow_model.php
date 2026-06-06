<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require_once(APPPATH . 'models/app/pharmacy_base_model.php');

/**
 * Pharmacy Workflow Model
 * 
 * Handles all workflow operations:
 * - Patient worklist
 * - Prescription queries
 * - Workflow status management
 * - Archiving
 * 
 * Part of Phase 4 Performance Optimization.
 */
class Pharmacy_workflow_model extends Pharmacy_base_model
{
	public function __construct()
	{
		parent::__construct();
		$this->load->model('app/pharmacy_stock_model');
		$this->load->model('app/pharmacy_dispense_model');
		$this->load->model('app/pharmacy_billing_model');
	}
	
	// =========================================================================
	// PATIENT WORKLIST (OPTIMIZED)
	// =========================================================================
	
	/**
	 * Get patient worklist - grouped by patient/visit
	 * Optimized with batch fetching to eliminate N+1 queries
	 */
	public function get_patient_worklist($filters = array())
	{
		$search = isset($filters['search']) ? trim($filters['search']) : '';
		$status = isset($filters['status']) ? trim($filters['status']) : '';
		$date_from = isset($filters['date_from']) ? trim($filters['date_from']) : '';
		$date_to = isset($filters['date_to']) ? trim($filters['date_to']) : '';
		$limit = isset($filters['limit']) ? (int)$filters['limit'] : 100;
		
		// Base query - get distinct IOPs with medications
		$this->db->select("
			i.IO_ID as iop_id,
			i.patient_no,
			i.date_visit,
			CONCAT_WS(' ', t.cValue, p.firstname, p.middlename, p.lastname) as patient_name,
			p.Insurance_comp,
			COUNT(m.iop_med_id) as total_items,
			SUM(CASE WHEN m.dispensing_status = 'DISPENSED' THEN 1 ELSE 0 END) as dispensed_count,
			SUM(CASE WHEN m.dispensing_status = 'PARTIAL' THEN 1 ELSE 0 END) as partial_count,
			SUM(CASE WHEN m.dispensing_status IN ('PENDING','RESERVED') OR m.dispensing_status IS NULL THEN 1 ELSE 0 END) as pending_count,
			SUM(CASE WHEN m.dispensing_status = 'UNAVAILABLE' THEN 1 ELSE 0 END) as unavailable_count,
			SUM(CASE WHEN m.dispensing_status = 'EXTERNAL' THEN 1 ELSE 0 END) as external_count
		", false);
		
		$this->db->from('patient_details_iop i');
		$this->db->join('iop_medication m', 'm.iop_id = i.IO_ID AND m.InActive = 0', 'inner');
		$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
		$this->db->join('system_parameters t', 't.param_id = p.title', 'left');
		$this->db->where('i.InActive', 0);
		
		// Date filters
		if ($date_from !== '') {
			$this->db->where('DATE(i.date_visit) >=', $date_from);
		} else {
			// Default: last 7 days
			$this->db->where('DATE(i.date_visit) >=', date('Y-m-d', strtotime('-7 days')));
		}
		if ($date_to !== '') {
			$this->db->where('DATE(i.date_visit) <=', $date_to);
		}
		
		// Search filter
		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('p.firstname', $search);
			$this->db->or_like('p.lastname', $search);
			$this->db->or_like('i.patient_no', $search);
			$this->db->or_like('i.IO_ID', $search);
			$this->db->group_end();
		}
		
		$this->db->group_by('i.IO_ID');
		
		// Status filter - applied via HAVING
		if ($status !== '') {
			switch (strtoupper($status)) {
				case 'PENDING':
					$this->db->having('pending_count >', 0);
					break;
				case 'PARTIAL':
					$this->db->having('partial_count >', 0);
					break;
				case 'COMPLETED':
					$this->db->having('pending_count', 0);
					$this->db->having('partial_count', 0);
					break;
				case 'AWAITING_PAYMENT':
					// Will filter after with billing data
					break;
			}
		} else {
			// Default: show only those with pending items
			$this->db->having('(pending_count > 0 OR partial_count > 0)');
		}
		
		$this->db->order_by('i.date_visit', 'DESC');
		$this->db->limit($limit);
		
		$q = $this->db->get();
		$patients = $q ? $q->result() : array();
		
		if (empty($patients)) return array();
		
		// Batch fetch additional data
		$iop_ids = array_column($patients, 'iop_id');
		$payment_map = $this->pharmacy_billing_model->get_payment_status_map($iop_ids);
		
		// Enrich patient data
		foreach ($patients as &$pt) {
			// Determine payer type
			$ins = strtoupper(trim($pt->Insurance_comp ?? ''));
			$pt->payer_type = ($ins === 'NHIS' || $this->is_nhis_patient($pt->patient_no)) ? 'NHIS' : 'CASH';
			
			// Payment status from billing queue
			$billing = isset($payment_map[$pt->iop_id]) ? $payment_map[$pt->iop_id] : null;
			if ($billing) {
				$pt->payment_status = $billing['payment_status'];
				$pt->extended_status = $billing['extended_status'];
			} else {
				$pt->payment_status = ($pt->payer_type === 'NHIS') ? 'CLEARED' : 'PENDING';
				$pt->extended_status = null;
			}
			
			// Determine overall status
			if ((int)$pt->pending_count === 0 && (int)$pt->partial_count === 0) {
				$pt->overall_status = 'COMPLETED';
				$pt->status_class = 'success';
			} elseif ((int)$pt->partial_count > 0) {
				$pt->overall_status = 'IN_PROGRESS';
				$pt->status_class = 'info';
			} elseif ((int)$pt->external_count > 0) {
				$pt->overall_status = 'EXTERNAL';
				$pt->status_class = 'purple';
			} else {
				$pt->overall_status = 'PENDING';
				$pt->status_class = 'warning';
			}
			
			// Payment class
			if ($pt->payment_status === 'PAID' || $pt->payment_status === 'CLEARED' || $pt->payer_type === 'NHIS') {
				$pt->payment_class = 'success';
				$pt->payment_status = 'CLEARED';
			} elseif ($pt->payment_status === 'PARTIAL') {
				$pt->payment_class = 'warning';
			} else {
				$pt->payment_class = 'danger';
				$pt->payment_status = 'AWAITING';
			}
		}
		
		return $patients;
	}
	
	/**
	 * Get patient prescriptions for detail view
	 */
	public function get_patient_prescriptions($iop_id)
	{
		$this->pharmacy_dispense_model->ensure_dispense_schema();
		
		// Get all medications
		$this->db->select("
			m.iop_med_id, m.iop_id, m.medicine_id, m.dDate, m.total_qty,
			IFNULL(m.dosage,'') as dosage,
			IFNULL(m.instruction,'') as instruction,
			IFNULL(m.advice,'') as advice,
			IFNULL(m.days,0) as days,
			IFNULL(m.frequency,'') as frequency,
			IFNULL(m.dispensing_status,'PENDING') as dispensing_status,
			IFNULL(m.payment_status,'PENDING') as payment_status,
			d.drug_id, d.drug_name, d.generic_name, d.nStock, d.nPrice,
			d.is_nhis_covered, d.nhis_price
		", false);
		$this->db->from('iop_medication m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.medicine_id', 'left');
		$this->db->where('m.iop_id', $iop_id);
		$this->db->where('m.InActive', 0);
		$this->db->order_by('m.dDate', 'DESC');
		
		$q = $this->db->get();
		$meds = $q ? $q->result() : array();
		
		if (empty($meds)) return array();
		
		// Batch fetch dispense data
		$med_ids = array_column($meds, 'iop_med_id');
		$dispense_map = $this->pharmacy_dispense_model->get_dispense_map($med_ids);
		
		// Batch fetch billing data
		$this->pharmacy_billing_model->ensure_billing_schema();
		$total_col = $this->column_exists('pharmacy_billing_queue', 'total_amount') ? 'total_amount' : 'total';
		$this->db->select("iop_med_id, payment_status, extended_status, payer_type, {$total_col} AS total_amount", false);
		$this->db->from('pharmacy_billing_queue');
		$this->db->where_in('iop_med_id', $med_ids);
		$this->db->where('InActive', 0);
		$bq = $this->db->get();
		$billing_rows = $bq ? $bq->result() : array();
		$billing_map = array();
		foreach ($billing_rows as $br) {
			$billing_map[$br->iop_med_id] = $br;
		}
		
		// Enrich medication data
		foreach ($meds as &$med) {
			$mid = $med->iop_med_id;
			
			// Dispense info
			if (isset($dispense_map[$mid])) {
				$med->dispensed_qty = $dispense_map[$mid]['dispensed_qty'];
				$med->latest_dispense_at = $dispense_map[$mid]['latest_at'];
			} else {
				$med->dispensed_qty = 0;
				$med->latest_dispense_at = null;
			}
			$med->remaining_qty = max(0, (float)$med->total_qty - $med->dispensed_qty);
			
			// Billing info
			if (isset($billing_map[$mid])) {
				$med->billing_status = $billing_map[$mid]->payment_status;
				$med->billing_extended = $billing_map[$mid]->extended_status;
				$med->payer_type = $billing_map[$mid]->payer_type;
				$med->bill_amount = (float)$billing_map[$mid]->total_amount;
			} else {
				$med->billing_status = 'PENDING';
				$med->billing_extended = null;
				$med->payer_type = 'CASH';
				$med->bill_amount = (float)$med->nPrice * (float)$med->total_qty;
			}
			
			// Stock status
			$med->stock_available = (float)$med->nStock;
			$med->stock_low = ($med->stock_available <= 10);
			
			// Can dispense?
			$med->can_dispense = (
				$med->dispensing_status !== 'DISPENSED' &&
				$med->dispensing_status !== 'EXTERNAL' &&
				$med->dispensing_status !== 'UNAVAILABLE' &&
				$med->remaining_qty > 0 &&
				$med->stock_available > 0
			);
		}
		
		return $meds;
	}
	
	/**
	 * Get patient pharmacy info
	 */
	public function get_patient_pharmacy_info($iop_id)
	{
		$this->db->select("
			i.IO_ID as iop_id, i.patient_no, i.date_visit,
			CONCAT_WS(' ', t.cValue, p.firstname, p.middlename, p.lastname) as patient_name,
			p.Insurance_comp, p.nhis_number, p.nhis_status, p.nhis_expiry_date
		");
		$this->db->from('patient_details_iop i');
		$this->db->join('patient_personal_info p', 'p.patient_no = i.patient_no', 'left');
		$this->db->join('system_parameters t', 't.param_id = p.title', 'left');
		$this->db->where('i.IO_ID', $iop_id);
		
		$q = $this->db->get();
		$info = $q ? $q->row() : null;
		
		if ($info) {
			$ins = strtoupper(trim($info->Insurance_comp ?? ''));
			$info->payer_type = ($ins === 'NHIS' || !empty($info->nhis_number)) ? 'NHIS' : 'CASH';
			$info->nhis_active = ($info->payer_type === 'NHIS' && strtoupper($info->nhis_status ?? '') !== 'EXPIRED');
		}
		
		return $info;
	}
	
	// =========================================================================
	// ARCHIVING
	// =========================================================================
	
	/**
	 * Archive old prescriptions
	 */
	public function archive_old_prescriptions($days_old = 90)
	{
		$cutoff = date('Y-m-d', strtotime("-{$days_old} days"));
		
		// Create archive table if not exists
		if (!$this->table_exists('iop_medication_archive')) {
			$this->db->query("CREATE TABLE `iop_medication_archive` LIKE `iop_medication`");
			$this->add_column_if_not_exists('iop_medication_archive', 'archived_at', "datetime DEFAULT CURRENT_TIMESTAMP");
		}
		
		// Move old completed prescriptions to archive
		$sql = "INSERT INTO `iop_medication_archive` 
			SELECT m.*, NOW() as archived_at 
			FROM `iop_medication` m
			WHERE m.dDate < ?
			AND m.dispensing_status = 'DISPENSED'
			AND m.InActive = 0";
		
		$this->db->query($sql, array($cutoff));
		$archived = $this->db->affected_rows();
		
		// Mark as inactive in main table
		$this->db->set('InActive', 1);
		$this->db->where('dDate <', $cutoff);
		$this->db->where('dispensing_status', 'DISPENSED');
		$this->db->update('iop_medication');
		
		return $archived;
	}
	
	/**
	 * Archive old billing queue entries
	 */
	public function archive_old_billing_queue($days_old = 90)
	{
		$this->pharmacy_billing_model->ensure_billing_schema();
		
		$cutoff = date('Y-m-d', strtotime("-{$days_old} days"));
		
		// Create archive table if not exists
		if (!$this->table_exists('pharmacy_billing_queue_archive')) {
			$this->db->query("CREATE TABLE `pharmacy_billing_queue_archive` LIKE `pharmacy_billing_queue`");
			$this->add_column_if_not_exists('pharmacy_billing_queue_archive', 'archived_at', "datetime DEFAULT CURRENT_TIMESTAMP");
		}
		
		// Move old completed entries to archive
		$sql = "INSERT INTO `pharmacy_billing_queue_archive` 
			SELECT b.*, NOW() as archived_at 
			FROM `pharmacy_billing_queue` b
			WHERE b.created_at < ?
			AND b.dispense_status = 'DISPENSED'
			AND b.InActive = 0";
		
		$this->db->query($sql, array($cutoff));
		$archived = $this->db->affected_rows();
		
		// Mark as inactive in main table
		$this->db->set('InActive', 1);
		$this->db->where('created_at <', $cutoff);
		$this->db->where('dispense_status', 'DISPENSED');
		$this->db->update('pharmacy_billing_queue');
		
		return $archived;
	}
	
	/**
	 * Get archive statistics
	 */
	public function get_archive_stats()
	{
		$stats = array(
			'prescriptions_archivable' => 0,
			'billing_archivable' => 0,
			'prescriptions_archived' => 0,
			'billing_archived' => 0
		);
		
		$cutoff = date('Y-m-d', strtotime('-90 days'));
		
		// Count archivable prescriptions
		$this->db->where('dDate <', $cutoff);
		$this->db->where('dispensing_status', 'DISPENSED');
		$this->db->where('InActive', 0);
		$stats['prescriptions_archivable'] = $this->db->count_all_results('iop_medication');
		
		// Count archivable billing
		if ($this->table_exists('pharmacy_billing_queue')) {
			$this->db->where('created_at <', $cutoff);
			$this->db->where('dispense_status', 'DISPENSED');
			$this->db->where('InActive', 0);
			$stats['billing_archivable'] = $this->db->count_all_results('pharmacy_billing_queue');
		}
		
		// Count archived
		if ($this->table_exists('iop_medication_archive')) {
			$stats['prescriptions_archived'] = $this->db->count_all_results('iop_medication_archive');
		}
		if ($this->table_exists('pharmacy_billing_queue_archive')) {
			$stats['billing_archived'] = $this->db->count_all_results('pharmacy_billing_queue_archive');
		}
		
		return $stats;
	}
	
	// =========================================================================
	// MEDICATION CLEARANCE
	// =========================================================================
	
	/**
	 * Check medication clearance for a visit
	 */
	public function check_medication_clearance($iop_id)
	{
		$prescriptions = $this->get_patient_prescriptions($iop_id);
		
		$result = array(
			'total' => count($prescriptions),
			'dispensed' => 0,
			'partial' => 0,
			'pending' => 0,
			'unavailable' => 0,
			'external' => 0,
			'cleared' => false
		);
		
		foreach ($prescriptions as $rx) {
			switch (strtoupper($rx->dispensing_status)) {
				case 'DISPENSED':
					$result['dispensed']++;
					break;
				case 'PARTIAL':
					$result['partial']++;
					break;
				case 'UNAVAILABLE':
					$result['unavailable']++;
					break;
				case 'EXTERNAL':
					$result['external']++;
					break;
				default:
					$result['pending']++;
			}
		}
		
		// Cleared if no pending or partial
		$result['cleared'] = ($result['pending'] === 0 && $result['partial'] === 0);
		
		return $result;
	}
	
	/**
	 * Mark medication clearance
	 */
	public function mark_medication_clearance($iop_id, $patient_no, $user_id)
	{
		$clearance = $this->check_medication_clearance($iop_id);
		
		if (!$clearance['cleared']) {
			return array(
				'success' => false,
				'error' => "Cannot clear: {$clearance['pending']} pending, {$clearance['partial']} partial"
			);
		}
		
		// Log clearance
		$this->log_audit('patient_details_iop', $iop_id, 'MEDICATION_CLEARANCE', null, 'CLEARED', $user_id);
		
		return array('success' => true, 'clearance' => $clearance);
	}
}
