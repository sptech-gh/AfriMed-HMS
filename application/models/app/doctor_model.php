<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Doctor_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}
	
	public function getAll($limit = 10, $offset = 0){
		if($this->input->post('cFrom') == ""){
			$cFrom = date("Y-m-d");	
		}else{
			//$cFrom = "2014-01-01";	
			$cFrom = $this->input->post('cFrom');
		}
		
		if($this->input->post('cTo') == ""){
			$cTo = date("Y-m-d");	
		}else{
			//$cTo = "2014-01-01";	
			$cTo = $this->input->post('cTo');
		}
		
		$this->db->select("
			A.IO_ID,
			B.patient_no,
			concat(C.cValue,' ',B.firstname,' ',B.middlename,' ',B.lastname) as 'name',
			B.age,
			A.date_visit,
			A.time_visit,
			D.dept_name,
			D.dept_name,
			concat(F.cValue,' ',E.firstname,' ',E.middlename,' ',E.lastname) as 'doctor',
			A.nStatus,
			",false);
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				B.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				B.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.IO_ID LIKE '%{$search}%' ESCAPE '!'
				) 
				AND E.user_id = ".$this->db->escape($this->session->userdata('user_id'))." 
				AND A.date_visit BETWEEN ".$this->db->escape($cFrom)." AND ".$this->db->escape($cTo)."  
				AND A.patient_type = 'OPD' 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->order_by('B.patient_no','asc');
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->join("users E","E.user_id = A.doctor_id","left outer");
		$this->db->join("system_parameters F","F.param_id = E.title","left outer");
		$query = $this->db->get("patient_details_iop A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all(){
		if($this->input->post('cFrom') == ""){
			$cFrom = date("Y-m-d");	
		}else{
			$cFrom = $this->input->post('cFrom');	
		}
		
		if($this->input->post('cTo') == ""){
			$cTo = date("Y-m-d");	
		}else{
			$cTo = $this->input->post('cTo');	
		}
		
		$this->db->select('COUNT(*) AS c', false);
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				B.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				B.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.IO_ID LIKE '%{$search}%' ESCAPE '!'
				) 
				AND A.department_id = ".$this->db->escape($this->session->userdata('department'))." 
				AND E.user_id = ".$this->db->escape($this->session->userdata('user_id'))." 
				AND A.date_visit BETWEEN ".$this->db->escape($cFrom)." AND ".$this->db->escape($cTo)." 
				AND A.patient_type = 'OPD' 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("users E","E.user_id = A.doctor_id","left outer");
		$query = $this->db->get("patient_details_iop A");
		$row = $query ? $query->row() : null;
		return ($row && isset($row->c)) ? (int)$row->c : 0;
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	public function getAll2($limit = 10, $offset = 0){
		if($this->input->post('cFrom') == ""){
			$cFrom = date("Y-m-d");	
		}else{
			//$cFrom = "2014-01-01";	
			$cFrom = $this->input->post('cFrom');
		}
		
		if($this->input->post('cTo') == ""){
			$cTo = date("Y-m-d");	
		}else{
			//$cTo = "2014-01-01";	
			$cTo = $this->input->post('cTo');
		}
		
		$this->db->select("
			A.IO_ID,
			B.patient_no,
			concat(C.cValue,' ',B.firstname,' ',B.middlename,' ',B.lastname) as 'name',
			B.age,
			A.date_visit,
			A.time_visit,
			D.dept_name,
			D.dept_name,
			concat(F.cValue,' ',E.firstname,' ',E.middlename,' ',E.lastname) as 'doctor',
			A.nStatus,
			G.bed_name,
			H.room_name
			",false);
		$rawSearch = (string)$this->input->post('search');
		$search = $this->db->escape_like_str($rawSearch);
		$clauses = array();
		if ($rawSearch !== '') {
			$clauses[] = "(B.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				B.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.IO_ID LIKE '%{$search}%' ESCAPE '!')";
		}
		$clauses[] = "E.user_id = " . $this->db->escape($this->session->userdata('user_id'));
		$clauses[] = "A.date_visit BETWEEN " . $this->db->escape($cFrom) . " AND " . $this->db->escape($cTo);
		$clauses[] = "A.patient_type = 'IPD'";
		$clauses[] = "A.InActive = 0";
		$this->db->where(implode(' AND ', $clauses));
		$this->db->order_by('B.patient_no','asc');
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->join("users E","E.user_id = A.doctor_id","left outer");
		$this->db->join("system_parameters F","F.param_id = E.title","left outer");
		$this->db->join("room_beds G","G.room_bed_id = room_id","left outer");
		$this->db->join("room_master H","H.room_master_id = G.room_master_id","left outer");
		$query = $this->db->get("patient_details_iop A", $limit, $offset);
		return $query->result();
	}
	
	public function count_all2(){
		if($this->input->post('cFrom') == ""){
			$cFrom = date("Y-m-d");	
		}else{
			$cFrom = $this->input->post('cFrom');	
		}
		
		if($this->input->post('cTo') == ""){
			$cTo = date("Y-m-d");	
		}else{
			$cTo = $this->input->post('cTo');	
		}
		
		$this->db->select('COUNT(*) AS c', false);
		$rawSearch = (string)$this->input->post('search');
		$search = $this->db->escape_like_str($rawSearch);
		$clauses = array();
		if ($rawSearch !== '') {
			$clauses[] = "(B.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				B.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.IO_ID LIKE '%{$search}%' ESCAPE '!')";
		}
		$clauses[] = "A.department_id = " . $this->db->escape($this->session->userdata('department'));
		$clauses[] = "E.user_id = " . $this->db->escape($this->session->userdata('user_id'));
		$clauses[] = "A.date_visit BETWEEN " . $this->db->escape($cFrom) . " AND " . $this->db->escape($cTo);
		$clauses[] = "A.patient_type = 'IPD'";
		$clauses[] = "A.InActive = 0";
		$this->db->where(implode(' AND ', $clauses));
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("users E","E.user_id = A.doctor_id","left outer");
		$query = $this->db->get("patient_details_iop A");
		$row = $query ? $query->row() : null;
		return ($row && isset($row->c)) ? (int)$row->c : 0;
	}

	/**
	 * Doctor Report Hub — GHS/NHIS-compliant stats for the logged-in doctor.
	 * Single source of truth: iop_opd_workflow (status), iop_diagnosis (morbidity),
	 * iop_medication / iop_laboratory (orders), patient_details_iop (visits).
	 */
	public function get_doctor_report_stats($date_from, $date_to)
	{
		$doctor_id = (string)$this->session->userdata('user_id');
		$stats = array();

		// 1. OPD ATTENDANCE (GHS Form 1 / DHIMS2)
		$r = $this->db->query(
			"SELECT
			    COUNT(*) AS total_visits,
			    SUM(CASE WHEN W.status IN ('CLINICALLY_CLEARED','FINAL_CLEARED','COMPLETED') THEN 1 ELSE 0 END) AS completed,
			    SUM(CASE WHEN W.status IN ('WAITING','IN_CONSULTATION','LAB_PENDING','PHARMACY_PENDING','LAB_COMPLETED','PHARMACY_COMPLETED') THEN 1 ELSE 0 END) AS active,
			    SUM(CASE WHEN W.status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled,
			    SUM(CASE WHEN SP_g.cValue IN ('Male','M') THEN 1 ELSE 0 END) AS male_count,
			    SUM(CASE WHEN SP_g.cValue IN ('Female','F') THEN 1 ELSE 0 END) AS female_count,
			    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, PI.birthday, CURDATE()) < 5 THEN 1 ELSE 0 END) AS under5,
			    SUM(CASE WHEN TIMESTAMPDIFF(YEAR, PI.birthday, CURDATE()) >= 5 THEN 1 ELSE 0 END) AS over5
			FROM patient_details_iop P
			LEFT JOIN iop_opd_workflow W ON W.iop_id = P.IO_ID AND W.InActive = 0
			LEFT JOIN patient_personal_info PI ON PI.patient_no = P.patient_no
			LEFT JOIN system_parameters SP_g ON SP_g.param_id = PI.gender
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD' AND P.InActive = 0
			  AND P.date_visit BETWEEN ? AND ?",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['attendance'] = ($r && $r->row()) ? $r->row()
			: (object)array('total_visits'=>0,'completed'=>0,'active'=>0,'cancelled'=>0,'male_count'=>0,'female_count'=>0,'under5'=>0,'over5'=>0);

		// 2. NHIS vs CASH SPLIT (uses nhis_number column for NHIS detection)
		$r2 = $this->db->query(
			"SELECT
			    SUM(CASE WHEN PI.nhis_number IS NOT NULL AND TRIM(PI.nhis_number) != '' THEN 1 ELSE 0 END) AS nhis_count,
			    SUM(CASE WHEN PI.nhis_number IS NULL OR TRIM(PI.nhis_number) = '' THEN 1 ELSE 0 END) AS cash_count
			FROM patient_details_iop P
			LEFT JOIN patient_personal_info PI ON PI.patient_no = P.patient_no
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD' AND P.InActive = 0
			  AND P.date_visit BETWEEN ? AND ?",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['payer'] = ($r2 && $r2->row()) ? $r2->row()
			: (object)array('nhis_count'=>0,'cash_count'=>0);

		// 3. TOP 10 DIAGNOSES (GHS Morbidity / NHIS ICD-10 codes)
		// Join to diagnosis master for name/ICD, fallback to diagnosis_text
		$r3 = $this->db->query(
			"SELECT COALESCE(DM.icd_code,'') AS icd_code,
			        COALESCE(DM.diagnosis_name, D.diagnosis_text, 'Unknown') AS diagnosis_name,
			        COUNT(*) AS freq
			FROM iop_diagnosis D
			INNER JOIN patient_details_iop P ON P.IO_ID = D.iop_id AND P.InActive = 0
			LEFT JOIN diagnosis DM ON DM.diagnosis_id = D.diagnosis_id
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD'
			  AND P.date_visit BETWEEN ? AND ? AND D.InActive = 0
			GROUP BY diagnosis_name, icd_code
			ORDER BY freq DESC LIMIT 10",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['top_diagnoses'] = $r3 ? $r3->result() : array();

		// 4. LAB REQUESTS
		$r4 = $this->db->query(
			"SELECT COUNT(*) AS total_labs,
			    SUM(CASE WHEN IL.status IN ('Completed','Released','Approved') THEN 1 ELSE 0 END) AS completed_labs
			FROM iop_laboratory IL
			INNER JOIN patient_details_iop P ON P.IO_ID = IL.iop_id AND P.InActive = 0
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD'
			  AND P.date_visit BETWEEN ? AND ?",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['labs'] = ($r4 && $r4->row()) ? $r4->row()
			: (object)array('total_labs'=>0,'completed_labs'=>0);

		// 5. PRESCRIPTIONS (uses dispensing_status column)
		$r5 = $this->db->query(
			"SELECT COUNT(*) AS total_rx,
			    SUM(CASE WHEN LOWER(IM.dispensing_status) = 'dispensed' THEN 1 ELSE 0 END) AS dispensed_rx
			FROM iop_medication IM
			INNER JOIN patient_details_iop P ON P.IO_ID = IM.iop_id AND P.InActive = 0
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD'
			  AND P.date_visit BETWEEN ? AND ?",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['rx'] = ($r5 && $r5->row()) ? $r5->row()
			: (object)array('total_rx'=>0,'dispensed_rx'=>0);

		// 6. ADMISSIONS from OPD (GHS referral / admission tracking)
		$r6 = $this->db->query(
			"SELECT SUM(CASE WHEN W.status = 'ADMITTED' THEN 1 ELSE 0 END) AS admitted_count
			FROM patient_details_iop P
			LEFT JOIN iop_opd_workflow W ON W.iop_id = P.IO_ID AND W.InActive = 0
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD' AND P.InActive = 0
			  AND P.date_visit BETWEEN ? AND ?",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['admissions'] = ($r6 && $r6->row()) ? $r6->row()
			: (object)array('admitted_count'=>0);

		// 7. DAILY TREND (last 7 calendar days — GHS daily returns)
		$r7 = $this->db->query(
			"SELECT DATE(P.date_visit) AS visit_day, COUNT(*) AS daily_count
			FROM patient_details_iop P
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD' AND P.InActive = 0
			  AND P.date_visit >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
			  AND P.date_visit <= CURDATE()
			GROUP BY DATE(P.date_visit) ORDER BY visit_day ASC",
			array($doctor_id)
		);
		$stats['daily_trend'] = $r7 ? $r7->result() : array();

		// 8. VISIT TYPE BREAKDOWN (NHIS: new / review / follow-up)
		// Check if smart_billing_ledger table exists first
		$sblExists = $this->db->table_exists('smart_billing_ledger');
		if ($sblExists) {
			$r8 = $this->db->query(
				"SELECT COALESCE(SB.visit_type,'WALK_IN') AS visit_type, COUNT(*) AS cnt
				FROM patient_details_iop P
				LEFT JOIN smart_billing_ledger SB ON SB.iop_id = P.IO_ID
				WHERE P.doctor_id = ? AND P.patient_type = 'OPD' AND P.InActive = 0
				  AND P.date_visit BETWEEN ? AND ?
				GROUP BY visit_type ORDER BY cnt DESC",
				array($doctor_id, $date_from, $date_to)
			);
			$stats['visit_types'] = $r8 ? $r8->result() : array();
		} else {
			$stats['visit_types'] = array();
		}

		// 9. AVERAGE CONSULTATION TIME (minutes)
		$r9 = $this->db->query(
			"SELECT ROUND(AVG(TIMESTAMPDIFF(MINUTE, W.in_consultation_at,
			    COALESCE(W.clinically_cleared_at, W.updated_at))), 1) AS avg_min
			FROM iop_opd_workflow W
			INNER JOIN patient_details_iop P ON P.IO_ID = W.iop_id AND P.InActive = 0
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD'
			  AND P.date_visit BETWEEN ? AND ?
			  AND W.in_consultation_at IS NOT NULL AND W.InActive = 0",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['avg_consult_min'] = ($r9 && $r9->row() && $r9->row()->avg_min !== null)
			? (float)$r9->row()->avg_min : 0;

		// 10. TOP 8 MEDICATIONS PRESCRIBED
		// Join to medicine_drug_name for actual drug name, fallback to medicine_text
		$r10 = $this->db->query(
			"SELECT COALESCE(DN.drug_name, IM.medicine_text, 'Unknown') AS drug_name, COUNT(*) AS freq
			FROM iop_medication IM
			INNER JOIN patient_details_iop P ON P.IO_ID = IM.iop_id AND P.InActive = 0
			LEFT JOIN medicine_drug_name DN ON DN.drug_id = IM.medicine_id
			WHERE P.doctor_id = ? AND P.patient_type = 'OPD'
			  AND P.date_visit BETWEEN ? AND ?
			GROUP BY drug_name ORDER BY freq DESC LIMIT 8",
			array($doctor_id, $date_from, $date_to)
		);
		$stats['top_meds'] = $r10 ? $r10->result() : array();

		return $stats;
	}
	
}