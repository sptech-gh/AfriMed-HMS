<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Dashboard_model extends CI_Model{
	private $__table_exists_cache = array();
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function latest_patient(){
		$this->db->select("
			concat(B.cValue,' ',A.firstname,' ',A.lastname) as patient,
			DATE_FORMAT(A.date_entry, '%Y-%m-%d') as date_entry,
			A.age,
			C.cValue as gender,
			A.date_entry as date_entry2,
			A.patient_no
		",false);
		$where = "DATE_FORMAT(A.date_entry, '%Y-%m-%d') = '".date("Y-m-d")."' and A.InActive = 0";
		$this->db->where($where);	
		$this->db->order_by("A.date_entry","DESC");
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("system_parameters C","C.param_id = A.gender","left outer");
		$query = $this->db->get('patient_personal_info A',3,0);
		return $query->result();
	}
	
	public function latest_visited_patient(){
		$this->db->select("
			concat(C.cValue,' ',B.firstname,' ',B.lastname) as patient,
			A.IO_ID,
			A.date_visit,
			A.time_visit,
			E.dept_name,
			A.patient_no
		",false);
		$where = "A.date_visit = '".date("Y-m-d")."' and A.InActive = 0";
		$this->db->where($where);	
		$this->db->order_by("A.date_visit","DESC");
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("system_parameters D","D.param_id = B.gender","left outer");
		$this->db->join("department E","E.department_id = A.department_id","left outer");
		$query = $this->db->get('patient_details_iop A',3,0);
		return $query->result();
	}

	public function getTodayAppointment(){
		$this->db->select("
			A.patient_no,
			concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
			C.appID,
			C.appointmentDate,
			C.appHour,
			C.appMinutes,
			C.appAMPM,
			C.dateVisit,
			C.appointmentStatus,
			C.appointmentReason,
			C.dateEntry,
			C.consultantDoctor as consultantDoctorId,
			concat(E.cValue,' ',D.firstname,' ',D.middlename,' ',D.lastname) as 'consultantDoctor'
		",false);
		$where = "C.appointmentDate = '".date("Y-m-d")."' AND C.appointmentStatus = 'A'";
		$this->db->where($where);
		$this->db->order_by('A.lastname','asc');
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("patient_appointment C","C.patient_no = A.patient_no","join");
		$this->db->join("users D","D.user_id = C.consultantDoctor","left outer");
		$this->db->join("system_parameters E","E.param_id = D.title","left outer");
		$query = $this->db->get("patient_personal_info A", 10, 0);
		return $query->result();
	}

	/* ================================================================== */
	/*  ROLE-SPECIFIC DASHBOARD QUERIES                                   */
	/* ================================================================== */

	private function _table_exists($t) {
		$t = (string)$t;
		if ($t === '') {
			return false;
		}
		if (array_key_exists($t, $this->__table_exists_cache)) {
			return (bool)$this->__table_exists_cache[$t];
		}
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($t));
		$exists = ($q && $q->num_rows() > 0);
		$this->__table_exists_cache[$t] = (bool)$exists;
		return (bool)$exists;
	}

	/* ---------- ADMIN ---------- */

	public function count_total_patients() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_personal_info WHERE InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_total_users() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM users WHERE InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_today_opd() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_details_iop WHERE date_visit = '".date('Y-m-d')."' AND patient_type = 'OPD' AND InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_today_ipd() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_details_iop WHERE patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_today_appointments() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_appointment WHERE appointmentDate = '".date('Y-m-d')."' AND appointmentStatus = 'A'");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_revenue_today() {
		if (!$this->_table_exists('iop_billing')) return 0;
		$q = $this->db->query("SELECT IFNULL(SUM(total_amount),0) AS total FROM iop_billing WHERE DATE(dDate) = '".date('Y-m-d')."' AND InActive = 0");
		$r = $q->row();
		return $r ? (float)$r->total : 0;
	}

	public function count_pending_lab_bills() {
		if (!$this->_table_exists('iop_lab_billing')) return 0;
		$r = $this->db->where(array('payment_status' => 'PENDING', 'billing_generated' => 1, 'InActive' => 0))
			->count_all_results('iop_lab_billing');
		return (int)$r;
	}

	public function get_pending_lab_bills_list($limit = 20) {
		if (!$this->_table_exists('iop_lab_billing')) return array();
		// Get rate_amount from lab billing, fallback to bill_particular.charge_amount if 0
		$sql = "SELECT LB.lab_bill_id, LB.iop_id, LB.patient_no, LB.item_name,
					COALESCE(NULLIF(LB.rate_amount, 0), BP.charge_amount, 0) AS rate_amount,
					LB.payment_status, LB.department, LB.created_at,
					CONCAT(PPI.lastname,' ',PPI.firstname) AS patient_name
				FROM iop_lab_billing LB
				LEFT JOIN patient_personal_info PPI ON PPI.patient_no = LB.patient_no
				LEFT JOIN bill_particular BP ON BP.particular_id = LB.laboratory_id
				WHERE LB.payment_status = 'PENDING' AND LB.billing_generated = 1 AND LB.InActive = 0
				ORDER BY LB.created_at DESC
				LIMIT " . (int)$limit;
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	/**
	 * Get pending bills grouped by patient - CONSOLIDATED VIEW
	 * Groups lab, medication, and other pending items per patient for single-click billing
	 * @param int $limit
	 * @return array Patients with their aggregated pending items
	 */
	public function get_pending_bills_by_patient($limit = 30) {
		$patients = array();
		
		// Get pending lab/scan/radiology items grouped by patient
		if ($this->_table_exists('iop_lab_billing')) {
			$sql = "SELECT 
						LB.patient_no,
						LB.iop_id,
						CONCAT(PPI.lastname, ' ', PPI.firstname) AS patient_name,
						PPI.mobile_no AS phone,
						COUNT(*) AS lab_count,
						SUM(COALESCE(NULLIF(LB.rate_amount, 0), BP.charge_amount, 0)) AS lab_total,
						MIN(LB.created_at) AS earliest_request,
						MAX(LB.created_at) AS latest_request,
						GROUP_CONCAT(DISTINCT LB.department SEPARATOR ', ') AS departments
					FROM iop_lab_billing LB
					LEFT JOIN patient_personal_info PPI ON PPI.patient_no = LB.patient_no
					LEFT JOIN bill_particular BP ON BP.particular_id = LB.laboratory_id
					WHERE LB.payment_status = 'PENDING' AND LB.billing_generated = 1 AND LB.InActive = 0
					GROUP BY LB.patient_no, LB.iop_id
					ORDER BY earliest_request ASC
					LIMIT " . (int)$limit;
			$q = $this->db->query($sql);
			if ($q) {
				foreach ($q->result() as $row) {
					$key = $row->patient_no . '_' . $row->iop_id;
					if (!isset($patients[$key])) {
						$patients[$key] = (object)array(
							'patient_no' => $row->patient_no,
							'iop_id' => $row->iop_id,
							'patient_name' => $row->patient_name,
							'phone' => $row->phone,
							'lab_count' => 0,
							'lab_total' => 0,
							'med_count' => 0,
							'med_total' => 0,
							'other_count' => 0,
							'other_total' => 0,
							'earliest_request' => $row->earliest_request,
							'departments' => $row->departments
						);
					}
					$patients[$key]->lab_count = (int)$row->lab_count;
					$patients[$key]->lab_total = (float)$row->lab_total;
					$patients[$key]->departments = $row->departments;
					if (strtotime($row->earliest_request) < strtotime($patients[$key]->earliest_request)) {
						$patients[$key]->earliest_request = $row->earliest_request;
					}
				}
			}
		}
		
		// Get pending medication items grouped by patient
		if ($this->_table_exists('iop_medication_billing')) {
			$sql = "SELECT 
						MB.patient_no,
						MB.iop_id,
						CONCAT(PPI.lastname, ' ', PPI.firstname) AS patient_name,
						PPI.mobile_no AS phone,
						COUNT(*) AS med_count,
						SUM(COALESCE(MB.total_amount, 0)) AS med_total,
						MIN(MB.created_at) AS earliest_request
					FROM iop_medication_billing MB
					LEFT JOIN patient_personal_info PPI ON PPI.patient_no = MB.patient_no
					WHERE MB.payment_status = 'PENDING' AND MB.billing_generated = 1 AND MB.InActive = 0
					GROUP BY MB.patient_no, MB.iop_id
					ORDER BY earliest_request ASC
					LIMIT " . (int)$limit;
			$q = $this->db->query($sql);
			if ($q) {
				foreach ($q->result() as $row) {
					$key = $row->patient_no . '_' . $row->iop_id;
					if (!isset($patients[$key])) {
						$patients[$key] = (object)array(
							'patient_no' => $row->patient_no,
							'iop_id' => $row->iop_id,
							'patient_name' => $row->patient_name,
							'phone' => $row->phone,
							'lab_count' => 0,
							'lab_total' => 0,
							'med_count' => 0,
							'med_total' => 0,
							'other_count' => 0,
							'other_total' => 0,
							'earliest_request' => $row->earliest_request,
							'departments' => ''
						);
					}
					$patients[$key]->med_count = (int)$row->med_count;
					$patients[$key]->med_total = (float)$row->med_total;
					if (strtotime($row->earliest_request) < strtotime($patients[$key]->earliest_request)) {
						$patients[$key]->earliest_request = $row->earliest_request;
					}
				}
			}
		}
		
		// Calculate totals and sort by earliest request
		$result = array_values($patients);
		foreach ($result as &$p) {
			$p->total_items = $p->lab_count + $p->med_count + $p->other_count;
			$p->total_amount = $p->lab_total + $p->med_total + $p->other_total;
		}
		usort($result, function($a, $b) {
			return strtotime($a->earliest_request) - strtotime($b->earliest_request);
		});
		
		return array_slice($result, 0, $limit);
	}

	/**
	 * Get detailed pending items for a specific patient visit
	 * Used when cashier clicks on a patient to bill all items at once
	 * @param string $patient_no
	 * @param string $iop_id
	 * @return array All pending billable items for this patient
	 */
	public function get_patient_pending_items($patient_no, $iop_id = null) {
		$items = array();
		
		// Get pending lab items
		if ($this->_table_exists('iop_lab_billing')) {
			$sql = "SELECT 
						LB.lab_bill_id AS item_id,
						'LAB' AS item_type,
						LB.item_name,
						LB.department,
						COALESCE(NULLIF(LB.rate_amount, 0), BP.charge_amount, 0) AS amount,
						LB.io_lab_id AS source_id,
						LB.created_at,
						LB.iop_id
					FROM iop_lab_billing LB
					LEFT JOIN bill_particular BP ON BP.particular_id = LB.laboratory_id
					WHERE LB.patient_no = " . $this->db->escape($patient_no) . "
					AND LB.payment_status = 'PENDING' 
					AND LB.billing_generated = 1 
					AND LB.InActive = 0";
			if ($iop_id) {
				$sql .= " AND LB.iop_id = " . $this->db->escape($iop_id);
			}
			$sql .= " ORDER BY LB.created_at ASC";
			$q = $this->db->query($sql);
			if ($q) {
				foreach ($q->result() as $row) {
					$items[] = $row;
				}
			}
		}
		
		// Get pending medication items
		if ($this->_table_exists('iop_medication_billing')) {
			$sql = "SELECT 
						MB.med_bill_id AS item_id,
						'MEDICATION' AS item_type,
						MB.item_name,
						'PHARMACY' AS department,
						COALESCE(MB.total_amount, 0) AS amount,
						MB.medication_id AS source_id,
						MB.created_at,
						MB.iop_id
					FROM iop_medication_billing MB
					WHERE MB.patient_no = " . $this->db->escape($patient_no) . "
					AND MB.payment_status = 'PENDING' 
					AND MB.billing_generated = 1 
					AND MB.InActive = 0";
			if ($iop_id) {
				$sql .= " AND MB.iop_id = " . $this->db->escape($iop_id);
			}
			$sql .= " ORDER BY MB.created_at ASC";
			$q = $this->db->query($sql);
			if ($q) {
				foreach ($q->result() as $row) {
					$items[] = $row;
				}
			}
		}
		
		return $items;
	}

	/**
	 * Count patients with pending bills (for dashboard badge)
	 */
	public function count_patients_with_pending_bills() {
		$count = 0;
		if ($this->_table_exists('iop_lab_billing')) {
			$sql = "SELECT COUNT(DISTINCT CONCAT(patient_no, '_', iop_id)) AS cnt 
					FROM iop_lab_billing 
					WHERE payment_status = 'PENDING' AND billing_generated = 1 AND InActive = 0";
			$r = $this->db->query($sql)->row();
			$count = $r ? (int)$r->cnt : 0;
		}
		return $count;
	}

	public function get_payments_today() {
		if (!$this->_table_exists('iop_receipt')) return 0;
		$q = $this->db->query("SELECT IFNULL(SUM(amountPaid),0) AS total FROM iop_receipt WHERE DATE(dDate) = '".date('Y-m-d')."' AND InActive = 0");
		$r = $q->row();
		return $r ? (float)$r->total : 0;
	}

	/* ---------- DOCTOR ---------- */

	public function count_opd_waiting($doctor_id = null) {
		if ($this->_table_exists('iop_opd_workflow')) {
			$sql = "SELECT COUNT(*) AS c FROM iop_opd_workflow W
					JOIN patient_details_iop P ON P.IO_ID = W.iop_id AND P.InActive = 0
					WHERE W.status = 'WAITING' AND W.InActive = 0
					AND P.patient_type = 'OPD' AND P.date_visit = '".date('Y-m-d')."'";
			if ($doctor_id) { $sql .= " AND P.doctor_id = " . $this->db->escape($doctor_id); }
			$r = $this->db->query($sql)->row();
			return $r ? (int)$r->c : 0;
		}
		$sql = "SELECT COUNT(*) AS c FROM patient_details_iop WHERE date_visit = '".date('Y-m-d')."' AND patient_type = 'OPD' AND nStatus = 'Pending' AND InActive = 0";
		if ($doctor_id) { $sql .= " AND doctor_id = " . $this->db->escape($doctor_id); }
		$r = $this->db->query($sql)->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_opd_in_consultation($doctor_id = null) {
		if ($this->_table_exists('iop_opd_workflow')) {
			$sql = "SELECT COUNT(*) AS c FROM iop_opd_workflow W
					JOIN patient_details_iop P ON P.IO_ID = W.iop_id AND P.InActive = 0
					WHERE W.status = 'IN_CONSULTATION' AND W.InActive = 0
					AND P.patient_type = 'OPD' AND P.date_visit = '".date('Y-m-d')."'";
			if ($doctor_id) { $sql .= " AND P.doctor_id = " . $this->db->escape($doctor_id); }
			$r = $this->db->query($sql)->row();
			return $r ? (int)$r->c : 0;
		}
		return 0;
	}

	public function count_opd_completed_today($doctor_id = null) {
		if ($this->_table_exists('iop_opd_workflow')) {
			$sql = "SELECT COUNT(*) AS c FROM iop_opd_workflow W
					JOIN patient_details_iop P ON P.IO_ID = W.iop_id AND P.InActive = 0
					WHERE W.status IN ('CLINICALLY_CLEARED','COMPLETED') AND W.InActive = 0
					AND P.patient_type = 'OPD' AND P.date_visit = '".date('Y-m-d')."'";
			if ($doctor_id) { $sql .= " AND P.doctor_id = " . $this->db->escape($doctor_id); }
			$r = $this->db->query($sql)->row();
			return $r ? (int)$r->c : 0;
		}
		$sql = "SELECT COUNT(*) AS c FROM patient_details_iop WHERE date_visit = '".date('Y-m-d')."' AND patient_type = 'OPD' AND nStatus != 'Pending' AND InActive = 0";
		if ($doctor_id) { $sql .= " AND doctor_id = " . $this->db->escape($doctor_id); }
		$r = $this->db->query($sql)->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_doctor_ipd($doctor_id = null) {
		$sql = "SELECT COUNT(*) AS c FROM patient_details_iop WHERE patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0";
		if ($doctor_id) {
			$sql .= " AND doctor_id = " . $this->db->escape($doctor_id);
		}
		$r = $this->db->query($sql)->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_doctor_appointments($doctor_id, $limit = 10) {
		$this->db->select("
			A.patient_no,
			CONCAT(B.cValue,' ',A.firstname,' ',A.lastname) as patient_name,
			C.appointmentDate, C.appHour, C.appMinutes, C.appAMPM, C.appointmentReason
		", false);
		$this->db->where("C.appointmentDate = '".date('Y-m-d')."' AND C.appointmentStatus = 'A' AND C.consultantDoctor = " . $this->db->escape($doctor_id));
		$this->db->join("system_parameters B","B.param_id = A.title","left outer");
		$this->db->join("patient_appointment C","C.patient_no = A.patient_no","join");
		$this->db->order_by('C.appHour','asc');
		return $this->db->get("patient_personal_info A", $limit, 0)->result();
	}

	public function get_opd_waiting_list($doctor_id = null, $limit = 10) {
		$this->db->select("
			A.IO_ID, A.patient_no, A.date_visit, A.time_visit,
			CONCAT(C.cValue,' ',B.firstname,' ',B.lastname) as patient_name,
			D.dept_name
		", false);
		$where = "A.date_visit = '".date('Y-m-d')."' AND A.patient_type = 'OPD' AND A.nStatus = 'Pending' AND A.InActive = 0";
		if ($doctor_id) {
			$where .= " AND A.doctor_id = " . $this->db->escape($doctor_id);
		}
		$this->db->where($where);
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->order_by("A.time_visit","ASC");
		return $this->db->get("patient_details_iop A", $limit, 0)->result();
	}

	public function get_ipd_patients_list($doctor_id = null, $limit = 10) {
		$this->db->select("
			A.IO_ID, A.patient_no, A.date_visit,
			CONCAT(C.cValue,' ',B.firstname,' ',B.lastname) as patient_name,
			D.dept_name
		", false);
		$where = "A.patient_type = 'IPD' AND A.nStatus = 'Pending' AND A.InActive = 0";
		if ($doctor_id) {
			$where .= " AND A.doctor_id = " . $this->db->escape($doctor_id);
		}
		$this->db->where($where);
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->order_by("A.date_visit","DESC");
		return $this->db->get("patient_details_iop A", $limit, 0)->result();
	}

	/* ---------- NURSE ---------- */

	public function count_admitted_patients() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_details_iop WHERE patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_pending_vitals() {
		$sql = "SELECT COUNT(DISTINCT A.IO_ID) AS c
				FROM patient_details_iop A
				WHERE A.patient_type = 'IPD' AND A.nStatus = 'Pending' AND A.InActive = 0
				AND A.IO_ID NOT IN (
					SELECT V.iop_id FROM iop_vital_parameters V
					WHERE V.InActive = 0 AND DATE(V.dDateTime) = '".date('Y-m-d')."'
				)";
		$r = $this->db->query($sql)->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_admitted_patients($limit = 15) {
		$this->db->select("
			A.IO_ID, A.patient_no, A.date_visit,
			CONCAT(C.cValue,' ',B.firstname,' ',B.lastname) as patient_name,
			D.dept_name
		", false);
		$this->db->where("A.patient_type = 'IPD' AND A.nStatus = 'Pending' AND A.InActive = 0");
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->order_by("A.date_visit","DESC");
		return $this->db->get("patient_details_iop A", $limit, 0)->result();
	}

	public function count_today_opd_visits() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_details_iop WHERE date_visit = '".date('Y-m-d')."' AND patient_type = 'OPD' AND InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	/* ---------- LABORATORY ---------- */

	public function count_pending_labs() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory WHERE result = '' AND InActive = 0 AND (category_id IS NULL OR category_id != '18')");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_completed_labs_today() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory WHERE result != '' AND InActive = 0 AND DATE(dDate) = '".date('Y-m-d')."' AND (category_id IS NULL OR category_id != '18')");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_pending_labs($limit = 15) {
		$sql = "SELECT l.io_lab_id, l.iop_id, l.laboratory_id, l.dDate as date_entry,
				CONCAT(ppi.firstname,' ',ppi.lastname) AS patient_name,
				ppi.patient_no, bp.particular_name
				FROM iop_laboratory l
				JOIN patient_details_iop pd ON pd.IO_ID = l.iop_id
				JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
				LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id
				WHERE l.result = '' AND l.InActive = 0 AND (l.category_id IS NULL OR l.category_id != '18')
				ORDER BY l.dDate DESC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

	/* ---------- PHARMACY ---------- */

	public function count_pending_prescriptions() {
		if (!$this->_table_exists('iop_medication')) return 0;
		$q = $this->db->query("SELECT COUNT(DISTINCT A.iop_id) AS c
			FROM iop_medication A
			JOIN patient_details_iop P ON P.IO_ID = A.iop_id AND P.InActive = 0
			WHERE A.InActive = 0 AND A.dDate = '".date('Y-m-d')."'");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_dispensed_today() {
		if (!$this->_table_exists('iop_medication_administration')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication_administration WHERE InActive = 0 AND DATE(dDateTime) = '".date('Y-m-d')."' AND status = 'GIVEN'");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_pending_prescriptions($limit = 15) {
		if (!$this->_table_exists('iop_medication')) return array();
		$sql = "SELECT A.iop_med_id, A.iop_id, A.medicine_id, A.dDate, A.total_qty,
				B.drug_name, ppi.patient_no,
				CONCAT(ppi.firstname,' ',ppi.lastname) AS patient_name
				FROM iop_medication A
				LEFT JOIN medicine_drug_name B ON B.drug_id = A.medicine_id
				JOIN patient_details_iop pd ON pd.IO_ID = A.iop_id AND pd.InActive = 0
				JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
				WHERE A.InActive = 0 AND A.dDate = '".date('Y-m-d')."'
				ORDER BY A.dDate DESC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

	/* ---------- CASHIER ---------- */

	public function count_today_invoices() {
		if (!$this->_table_exists('iop_billing')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_billing WHERE DATE(dDate) = '".date('Y-m-d')."' AND InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_unpaid_invoices() {
		if (!$this->_table_exists('iop_billing')) return 0;
		$q = $this->db->query("
			SELECT COUNT(*) AS c FROM iop_billing b
			WHERE b.InActive = 0 AND b.total_amount > 0
			AND b.total_amount > IFNULL((
				SELECT SUM(r.amountPaid) FROM iop_receipt r
				WHERE r.invoice_no = b.invoice_no AND r.InActive = 0
			), 0)
		");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_recent_invoices($limit = 15) {
		if (!$this->_table_exists('iop_billing')) return array();
		$sql = "SELECT b.invoice_no, b.iop_id, b.total_amount,
				IFNULL((SELECT SUM(r.amountPaid) FROM iop_receipt r WHERE r.invoice_no = b.invoice_no AND r.InActive = 0), 0) AS amount_paid,
				b.dDate as date_entry, b.payment_type,
				CONCAT(ppi.firstname,' ',ppi.lastname) AS patient_name, ppi.patient_no
				FROM iop_billing b
				JOIN patient_details_iop pd ON pd.IO_ID = b.iop_id AND pd.InActive = 0
				JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
				WHERE b.InActive = 0 AND DATE(b.dDate) = '".date('Y-m-d')."'
				ORDER BY b.dDate DESC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

	public function get_outstanding_amount() {
		if (!$this->_table_exists('iop_billing')) return 0;
		$q = $this->db->query("
			SELECT IFNULL(SUM(
				b.total_amount - IFNULL((
					SELECT SUM(r.amountPaid) FROM iop_receipt r
					WHERE r.invoice_no = b.invoice_no AND r.InActive = 0
				), 0)
			), 0) AS outstanding
			FROM iop_billing b
			WHERE b.InActive = 0
			AND b.total_amount > IFNULL((
				SELECT SUM(r2.amountPaid) FROM iop_receipt r2
				WHERE r2.invoice_no = b.invoice_no AND r2.InActive = 0
			), 0)
		");
		$r = $q->row();
		return $r ? (float)$r->outstanding : 0;
	}

	/* ---------- RECEPTIONIST ---------- */

	public function count_today_registrations() {
		$q = $this->db->query("SELECT COUNT(*) AS c FROM patient_personal_info WHERE DATE(date_entry) = '".date('Y-m-d')."' AND InActive = 0");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_recent_registrations($limit = 15) {
		// Also fetch today's OPD visit ID + workflow status so the view can show View OPD vs Start OPD
		$wf_exists = $this->_table_exists('iop_opd_workflow');
		$wf_cols = $wf_exists
			? ", opd.IO_ID AS today_iop_id, COALESCE(W.status,'WAITING') AS today_opd_status"
			: ", opd.IO_ID AS today_iop_id, 'WAITING' AS today_opd_status";
		$wf_join = $wf_exists
			? "LEFT JOIN iop_opd_workflow W ON W.iop_id = opd.IO_ID AND W.InActive = 0"
			: '';
		$hasVitalsStatusCol = $this->db->field_exists('vitals_status', 'patient_details_iop');
		$vp_exists = $this->_table_exists('iop_vital_parameters');
		$vitals_cols = '';
		if ($hasVitalsStatusCol) {
			$vitals_cols = ", COALESCE(opd.vitals_status,'') AS today_vitals_status";
		} elseif ($vp_exists) {
			$vitals_cols = ", CASE WHEN opd.IO_ID IS NOT NULL AND EXISTS (SELECT 1 FROM iop_vital_parameters vp WHERE vp.iop_id = opd.IO_ID AND vp.InActive = 0 LIMIT 1) THEN 'DONE' ELSE '' END AS today_vitals_status";
		} else {
			$vitals_cols = ", '' AS today_vitals_status";
		}
		$sql = "SELECT p.patient_no,
				CONCAT(COALESCE(sp.cValue,''),' ',p.firstname,' ',p.lastname) AS patient_name,
				p.date_entry, p.age,
				COALESCE(g.cValue,'') AS gender
				{$wf_cols}
				{$vitals_cols}
				FROM patient_personal_info p
				LEFT JOIN system_parameters sp ON sp.param_id = p.title
				LEFT JOIN system_parameters g ON g.param_id = p.gender
				LEFT JOIN patient_details_iop opd
					ON opd.patient_no = p.patient_no
					AND opd.patient_type = 'OPD'
					AND opd.date_visit = '".date('Y-m-d')."'
					AND opd.InActive = 0
				{$wf_join}
				WHERE DATE(p.date_entry) = '".date('Y-m-d')."' AND p.InActive = 0
				ORDER BY p.date_entry DESC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

	public function get_opd_queue($limit = 20) {
		// Join iop_opd_workflow for the real unified status; fall back to nStatus only if no workflow row exists
		$wf_exists = $this->_table_exists('iop_opd_workflow');
		$status_col = $wf_exists
			? "COALESCE(W.status, CASE WHEN A.nStatus='Pending' THEN 'WAITING' ELSE 'COMPLETED' END)"
			: "CASE WHEN A.nStatus='Pending' THEN 'WAITING' ELSE 'COMPLETED' END";
		$wf_join = $wf_exists
			? "LEFT JOIN iop_opd_workflow W ON W.iop_id = A.IO_ID AND W.InActive = 0"
			: '';
		$sql = "SELECT A.IO_ID, A.patient_no, A.date_visit, A.time_visit, A.nStatus,
				({$status_col}) AS workflow_status,
				CONCAT(COALESCE(C.cValue,''),' ',B.firstname,' ',B.lastname) AS patient_name,
				D.dept_name,
				CONCAT(COALESCE(DT.cValue,''),' ',COALESCE(DOC.firstname,''),' ',COALESCE(DOC.lastname,'')) AS doctor_name
				FROM patient_details_iop A
				JOIN patient_personal_info B ON B.patient_no = A.patient_no
				LEFT JOIN system_parameters C ON C.param_id = B.title
				LEFT JOIN department D ON D.department_id = A.department_id
				LEFT JOIN users DOC ON DOC.user_id = A.doctor_id
				LEFT JOIN system_parameters DT ON DT.param_id = DOC.title
				{$wf_join}
				WHERE A.date_visit = '".date('Y-m-d')."' AND A.patient_type = 'OPD' AND A.InActive = 0
				ORDER BY
					FIELD(COALESCE(W.status,'WAITING'),'WAITING','REGISTERED','IN_CONSULTATION','LAB_PENDING','PHARMACY_PENDING','CLINICALLY_CLEARED','FINAL_CLEARED','COMPLETED') ASC,
					A.time_visit ASC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

	/* ---------- SONOGRAPHER ---------- */

	public function count_pending_sonography() {
		if (!$this->_table_exists('iop_laboratory')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory WHERE (result = '' OR result IS NULL) AND InActive = 0 AND category_id = '18'");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function count_completed_sonography_today() {
		if (!$this->_table_exists('iop_laboratory')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory WHERE result != '' AND result IS NOT NULL AND InActive = 0 AND category_id = '18' AND DATE(dDate) = '".date('Y-m-d')."'");
		$r = $q->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_pending_sonography($limit = 30) {
		if (!$this->_table_exists('iop_laboratory')) return array();
		$sql = "SELECT l.io_lab_id, l.iop_id, l.dDate,
				COALESCE(l.laboratory_text, 'Sonography Request') AS scan_name,
				CONCAT(ppi.firstname,' ',ppi.lastname) AS patient_name,
				ppi.patient_no,
				CONCAT(COALESCE(sp.cValue,''),' ',COALESCE(doc.firstname,''),' ',COALESCE(doc.lastname,'')) AS doctor_name
				FROM iop_laboratory l
				JOIN patient_details_iop pd ON pd.IO_ID = l.iop_id AND pd.InActive = 0
				JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
				LEFT JOIN users doc ON doc.user_id = l.doctor
				LEFT JOIN system_parameters sp ON sp.param_id = doc.title
				WHERE (l.result = '' OR l.result IS NULL) AND l.InActive = 0 AND l.category_id = '18'
				ORDER BY l.dDate DESC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

}