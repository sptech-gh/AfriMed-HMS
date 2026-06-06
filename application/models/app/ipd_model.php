<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed'); 

class Ipd_model extends CI_Model{
	
	public function __construct(){
		parent::__construct();	
	}

	private function _table_exists_simple($table)
	{
		$table = trim((string)$table);
		if ($table === '') {
			return false;
		}
		$q = $this->db->query("SHOW TABLES LIKE ?", array($table));
		return ($q && $q->num_rows() > 0);
	}

	private function _index_exists_simple($table, $index)
	{
		$table = trim((string)$table);
		$index = trim((string)$index);
		if ($table === '' || $index === '') {
			return false;
		}
		$q = $this->db->query(
			"SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
			array($table, $index)
		);
		$row = $q ? $q->row() : null;
		return ($row && isset($row->c) && (int)$row->c > 0);
	}

	private function _trigger_exists_simple($trigger)
	{
		$trigger = trim((string)$trigger);
		if ($trigger === '') {
			return false;
		}
		$q = $this->db->query(
			"SELECT COUNT(1) AS c FROM information_schema.triggers WHERE trigger_schema = DATABASE() AND trigger_name = ?",
			array($trigger)
		);
		$row = $q ? $q->row() : null;
		return ($row && isset($row->c) && (int)$row->c > 0);
	}

	public function ensure_room_beds_invariants_schema()
	{
		if (!$this->_table_exists_simple('room_beds')) {
			return array('ok' => false, 'error' => 'room_beds_missing');
		}

		$qcol = $this->db->query("SHOW COLUMNS FROM `room_beds` LIKE 'patient_no'");
		$col = $qcol ? $qcol->row() : null;
		if (!$col || !isset($col->Type)) {
			return array('ok' => false, 'error' => 'room_beds.patient_no_missing');
		}
		$type = (string)$col->Type;
		$null = isset($col->Null) ? (string)$col->Null : 'YES';

		try {
			$this->db->query(
				"UPDATE `room_beds` SET `patient_no` = NULL WHERE `InActive` = 0 AND LOWER(TRIM(`nStatus`)) = 'vacant' AND TRIM(IFNULL(`patient_no`, '')) = ''"
			);
		} catch (\Throwable $e) {
			return array('ok' => false, 'error' => 'normalize_vacant_failed:' . $e->getMessage());
		}

		if (strtoupper($null) === 'NO') {
			try {
				$this->db->query("ALTER TABLE `room_beds` MODIFY `patient_no` {$type} NULL DEFAULT NULL");
			} catch (\Throwable $e) {
				return array('ok' => false, 'error' => 'alter_patient_no_nullable_failed:' . $e->getMessage());
			}
		}

		$qdup = $this->db->query(
			"SELECT patient_no, COUNT(*) AS c FROM room_beds WHERE InActive = 0 AND patient_no IS NOT NULL AND TRIM(patient_no) <> '' GROUP BY patient_no HAVING c > 1 LIMIT 5"
		);
		$dups = $qdup ? $qdup->result() : array();
		if (!empty($dups)) {
			return array('ok' => false, 'error' => 'duplicate_patient_no_in_room_beds', 'examples' => $dups);
		}

		$idx = 'uq_room_beds_patient_inactive';
		if (!$this->_index_exists_simple('room_beds', $idx)) {
			try {
				$this->db->query("CREATE UNIQUE INDEX `{$idx}` ON `room_beds` (`patient_no`, `InActive`)");
			} catch (\Throwable $e) {
				return array('ok' => false, 'error' => 'create_unique_index_failed:' . $e->getMessage());
			}
		}

		$trgIns = 'trg_room_beds_patient_required_ins';
		if (!$this->_trigger_exists_simple($trgIns)) {
			try {
				$this->db->query(
					"CREATE TRIGGER `{$trgIns}` BEFORE INSERT ON `room_beds` FOR EACH ROW BEGIN IF (LOWER(TRIM(NEW.nStatus)) = 'occupied' AND (NEW.patient_no IS NULL OR TRIM(NEW.patient_no) = '')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'occupied bed must have patient_no'; END IF; END"
				);
			} catch (\Throwable $e) {
				// best-effort
			}
		}
		$trgUpd = 'trg_room_beds_patient_required_upd';
		if (!$this->_trigger_exists_simple($trgUpd)) {
			try {
				$this->db->query(
					"CREATE TRIGGER `{$trgUpd}` BEFORE UPDATE ON `room_beds` FOR EACH ROW BEGIN IF (LOWER(TRIM(NEW.nStatus)) = 'occupied' AND (NEW.patient_no IS NULL OR TRIM(NEW.patient_no) = '')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'occupied bed must have patient_no'; END IF; END"
				);
			} catch (\Throwable $e) {
				// best-effort
			}
		}

		return array('ok' => true);
	}

	public function lock_bed_row($bed_id){
		$bed_id = (int)$bed_id;
		if ($bed_id <= 0) {
			return null;
		}
		$q = $this->db->query(
			"SELECT * FROM room_beds WHERE room_bed_id = ? AND InActive = 0 LIMIT 1 FOR UPDATE",
			array($bed_id)
		);
		if ($q === false) {
			$q = $this->db->query(
				"SELECT * FROM room_beds WHERE room_bed_id = ? AND InActive = 0 LIMIT 1",
				array($bed_id)
			);
		}
		return $q ? $q->row() : null;
	}

	public function lock_ipd_admission_row($iop_id, $patient_no = null){
		$iop_id = (string)$iop_id;
		$patient_no = $patient_no !== null ? (string)$patient_no : null;
		if ($iop_id === '') {
			return null;
		}
		$sql = "SELECT * FROM patient_details_iop WHERE IO_ID = ? AND patient_type = 'IPD' AND InActive = 0";
		$params = array($iop_id);
		if ($patient_no !== null && trim($patient_no) !== '') {
			$sql .= " AND patient_no = ?";
			$params[] = $patient_no;
		}
		$sql_for_update = $sql . " LIMIT 1 FOR UPDATE";
		$q = $this->db->query($sql_for_update, $params);
		if ($q === false) {
			$sql_plain = $sql . " LIMIT 1";
			$q = $this->db->query($sql_plain, $params);
		}
		return $q ? $q->row() : null;
	}

	public function conditional_occupy_bed($bed_id, $iop_id){
		$bed_id = (int)$bed_id;
		$iop_id = (string)$iop_id;
		if ($bed_id <= 0 || $iop_id === '') {
			return false;
		}
		$this->db->query(
			"UPDATE room_beds SET nStatus = 'Occupied', patient_no = ? WHERE room_bed_id = ? AND InActive = 0 AND (((LOWER(TRIM(nStatus)) = 'vacant') AND (patient_no = '' OR patient_no IS NULL)) OR patient_no = ?)",
			array($iop_id, $bed_id, $iop_id)
		);
		if ((int)$this->db->affected_rows() > 0) {
			return true;
		}
		$q = $this->db->query(
			"SELECT room_bed_id FROM room_beds WHERE room_bed_id = ? AND InActive = 0 AND patient_no = ? AND LOWER(TRIM(nStatus)) = 'occupied' LIMIT 1",
			array($bed_id, $iop_id)
		);
		return ($q && $q->num_rows() > 0);
	}

	public function conditional_vacate_bed($bed_id, $iop_id){
		$bed_id = (int)$bed_id;
		$iop_id = (string)$iop_id;
		if ($bed_id <= 0 || $iop_id === '') {
			return false;
		}
		$this->db->query(
			"UPDATE room_beds SET nStatus = 'Vacant', patient_no = NULL WHERE room_bed_id = ? AND InActive = 0 AND patient_no = ?",
			array($bed_id, $iop_id)
		);
		if ((int)$this->db->affected_rows() > 0) {
			return true;
		}
		$q = $this->db->query(
			"SELECT room_bed_id FROM room_beds WHERE room_bed_id = ? AND InActive = 0 AND (patient_no IS NULL OR TRIM(patient_no) = '') AND LOWER(TRIM(nStatus)) = 'vacant' LIMIT 1",
			array($bed_id)
		);
		return ($q && $q->num_rows() > 0);
	}

	public function conditional_vacate_all_beds_for_iop($iop_id){
		$iop_id = (string)$iop_id;
		if ($iop_id === '') {
			return 0;
		}
		$this->db->query(
			"UPDATE room_beds SET nStatus = 'Vacant', patient_no = NULL WHERE InActive = 0 AND patient_no = ?",
			array($iop_id)
		);
		return (int)$this->db->affected_rows();
	}
	
	public function getRoomMaster($roomType,$occupied){
		$this->db->select("
				A.room_master_id,
				B.floor_name,
				A.room_name,
				C.category_name
		");
		
		$safeRoomType = $this->db->escape($roomType);
		if($occupied == "all"){
			$where = "A.category_id = ".$safeRoomType;
		}else if($occupied == "occupied"){
			$where = "A.category_id = ".$safeRoomType." and D.nStatus = 'Occupied' ";
		}else if($occupied == "unoccupied"){
			$where = "A.category_id = ".$safeRoomType." and D.nStatus = 'Vacant' ";
		}else{
			$where = "A.category_id = ".$safeRoomType;	
		}
		
		$this->db->where($where);
		$this->db->order_by("A.room_name","ASC");
		$this->db->join("floor B","B.floor_id = A.floor","left outer");
		$this->db->join("room_category C","C.category_id = A.category_id","left outer");
		$this->db->join("room_beds D","D.room_master_id = A.room_master_id","left outer");
		$this->db->group_by(array('A.room_master_id','B.floor_name','A.room_name','C.category_name'));
		$query = $this->db->get("room_master A");
		return $query->result();
	}
	
	public function validate_ipd(){
		$this->db->where(array(
			'patient_no'		=>		$this->input->post('patient_no'),
			'nStatus'			=>		'Pending',
			'patient_type'		=>		'IPD',
			'InActive'			=>		0
		));
		$query = $this->db->get("patient_details_iop");
		if($query->num_rows() > 0){
			return true;
		}else{
			return false;
		}
	}
	
	public function updateBed(){
		$iop_id = (string)$this->input->post('iopNo');
		$bed_id = (int)$this->input->post('bed_no');
		return $this->conditional_occupy_bed($bed_id, $iop_id);
	}
	
	public function save(){
		$this->data = array(
			'IO_ID'						=>		$this->input->post('iopNo'),
			'patient_no'				=>		$this->input->post('patient_no'),
			'patient_type'				=>		'IPD',
			'date_visit'				=>		date('Y-m-d'),
			'time_visit'				=>		date('h:i:s'),
			'doctor_id'					=>		$this->input->post('doctor'),
			'refferal_doctor'			=>		0,
			'room_id'					=>		$this->input->post('bed_no'),
			'department_id'				=>		$this->input->post('department'),
			'provisional_diagnosis'		=>		$this->input->post('prov_diagnosis'),
			'complaints'				=>		$this->input->post('complaint'),
			'allergies'					=>		$this->input->post('allergies'),
			'warnings'					=>		$this->input->post('warnings'),
			'social_history'			=>		$this->input->post('social_history'),
			'family_history'			=>		$this->input->post('family_history'),
			'personal_history'			=>		$this->input->post('personal_history'),
			'past_medical_history'		=>		$this->input->post('past_medical_history'),
			'pulse_rate'				=>		$this->input->post('pulse_rate'),
			'temperature'				=>		$this->input->post('temperature'),
			'height'					=>		$this->input->post('height'),
			'bp'						=>		$this->input->post('bp'),
			'respiration'				=>		$this->input->post('respiration'),
			'weight'					=>		$this->input->post('weight'),
			'nStatus'					=>		'Pending',
			'InActive'					=>		0
		);	
		
		$this->db->insert("patient_details_iop",$this->data);
	}
	
	public function getAll($limit = 10, $offset = 0, $applyLimit = false){
		if($this->input->post("cFrom") == ""){
			$cFrom = date("Y-m-d");	
		}else{
			$cFrom = $this->input->post("cFrom");
		}
		
		if($this->input->post("cTo") == ""){
			$cTo = date("Y-m-d");	
		}else{
			$cTo = $this->input->post("cTo");
		}
		
		$this->db->select("
			A.IO_ID,
			B.patient_no,
			concat(C.cValue,' ',B.firstname,' ',B.middlename,' ',B.lastname) as 'name',
			B.age,
			B.Insurance_comp,
			A.date_visit,
			A.time_visit,
			D.dept_name,
			D.dept_name,
			concat(F.cValue,' ',E.firstname,' ',E.middlename,' ',E.lastname) as 'doctor',
			A.nStatus,
			G.bed_name,
			H.room_name
			",false);
		$search = $this->db->escape_like_str($this->input->post('search'));
		$ins = $this->db->escape_like_str($this->input->post('insurance'));
		$dept = $this->db->escape_like_str($this->input->post('department'));
		$doc = $this->db->escape_like_str($this->input->post('doctor'));
		$where = "(
				B.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				B.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.IO_ID LIKE '%{$search}%' ESCAPE '!'
				) 
				AND B.Insurance_comp LIKE '%{$ins}%' ESCAPE '!'
				AND A.department_id LIKE '%{$dept}%' ESCAPE '!' 
				AND E.user_id LIKE '%{$doc}%' ESCAPE '!' 
				AND A.date_visit BETWEEN ".$this->db->escape($cFrom)." AND ".$this->db->escape($cTo)."  
				AND A.patient_type = 'IPD' 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->order_by('B.patient_no','asc');
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->join("users E","E.user_id = A.doctor_id","left outer");
		$this->db->join("system_parameters F","F.param_id = E.title","left outer");
		$this->db->join("room_beds G","G.room_bed_id = room_id","left outer");
		$this->db->join("room_master H","H.room_master_id = G.room_master_id","left outer");
		$limit = (int)$limit;
		$offset = (int)$offset;
		if ($applyLimit && $limit > 0) {
			$query = $this->db->get("patient_details_iop A", $limit, $offset);
		} else {
			$query = $this->db->get("patient_details_iop A");
		}
		return $query->result();
	}
	
	public function count_all(){
		if($this->input->post("cFrom") == ""){
			$cFrom = date("Y-m-d");	
		}else{
			$cFrom = $this->input->post("cFrom");
		}
		
		if($this->input->post("cTo") == ""){
			$cTo = date("Y-m-d");	
		}else{
			$cTo = $this->input->post("cTo");
		}
		
		$this->db->select('COUNT(*) AS c', false);
		$rawSearch = (string)$this->input->post('search');
		$dept = $this->db->escape_like_str($this->input->post('department'));
		$doc = $this->db->escape_like_str($this->input->post('doctor'));
		$search = $this->db->escape_like_str($rawSearch);
		$clauses = array();
		if ($rawSearch !== '') {
			$clauses[] = "(B.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				B.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				B.patient_no LIKE '%{$search}%' ESCAPE '!' OR 
				A.IO_ID LIKE '%{$search}%' ESCAPE '!')";
		}
		$clauses[] = "A.department_id LIKE '%{$dept}%' ESCAPE '!'";
		$clauses[] = "E.user_id LIKE '%{$doc}%' ESCAPE '!'";
		$clauses[] = "A.date_visit BETWEEN ".$this->db->escape($cFrom)." AND ".$this->db->escape($cTo);
		$clauses[] = "A.patient_type = 'IPD'";
		$clauses[] = "A.InActive = 0";
		$this->db->where(implode(' AND ', $clauses));
		// No ordering needed for COUNT(*), avoid unnecessary sort
		$this->db->join("patient_personal_info B","B.patient_no = A.patient_no","left outer");
		$this->db->join("system_parameters C","C.param_id = B.title","left outer");
		$this->db->join("department D","D.department_id = A.department_id","left outer");
		$this->db->join("users E","E.user_id = A.doctor_id","left outer");
		$this->db->join("system_parameters F","F.param_id = E.title","left outer");
		$query = $this->db->get("patient_details_iop A");
		$row = $query ? $query->row() : null;
		return ($row && isset($row->c)) ? (int)$row->c : 0;
	}
	
	public function getIPDPatient($iop_no){
		$this->db->select("
				A.IO_ID,
				A.patient_no,
				A.date_visit,
				A.time_visit,
				concat(D.cValue,' ',B.firstname,' ',B.lastname) as ref_doctor,
				concat(E.cValue,' ',C.firstname,' ',C.lastname) as con_doctor,
				F.dept_name,
				A.pulse_rate,
				A.temperature,
				A.height,
				A.bp,
				A.respiration,
				A.weight,
				A.allergies,
				A.warnings,
				A.social_history,
				A.family_history,
				A.personal_history,
				A.past_medical_history,
				A.nStatus,
				G.bed_name,
				H.room_name
		",false);
		$this->db->where("A.IO_ID",$iop_no);
		$this->db->join("users B","B.user_id = A.refferal_doctor","left outer");
		$this->db->join("users C","C.user_id = A.doctor_id","left outer");
		$this->db->join("system_parameters D","D.param_id = B.title","left outer");
		$this->db->join("system_parameters E","E.param_id = C.title","left outer");
		$this->db->join("department F","F.department_id = A.department_id","left outer");
		$this->db->join("room_beds G","G.room_bed_id = A.room_id","left outer");
		$this->db->join("room_master H","H.room_master_id = G.room_master_id","left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->row();
	}
	
	public function diagnosisList(){
		// Keep IPD diagnosis master consistent with OPD (single clinical catalog).
		// This prevents "different results" across modules and ensures ICD/category
		// metadata is available wherever diagnosis is captured.
		$this->db->select("diagnosis_id, diagnosis_name, icd_code, category, common_treatment", false);
		$this->db->where("InActive", 0);
		$this->db->where("(is_active = 1 OR is_active IS NULL)", null, false);
		$this->db->order_by("category", "ASC");
		$this->db->order_by("icd_code", "ASC");
		$this->db->order_by("diagnosis_name", "ASC");
		$query = $this->db->get("diagnosis");
		return $query->result();
	}
	
	public function patientDiagnosis($iop_no){
		$this->db->select("A.iop_diag_id,B.diagnosis_name, A.remarks,A.diagnosis_text");
		$this->db->order_by("A.iop_diag_id","DESC");
		$this->db->where(array(
			'A.iop_id'		=>		$iop_no,
			'A.InActive'	=>		0
		));
		$this->db->join("diagnosis B","B.diagnosis_id = A.diagnosis_id","left outer");
		$query = $this->db->get("iop_diagnosis A");	
		return $query->result();	
	}
	
	public function save_progress_note(){
		$this->data = array(
			'iop_id'	=>		$this->input->post('opd_no'),
			'dDate'		=>		$this->input->post('dDate'),
			'dDateTime'	=>		$this->input->post('dDate')." ".$this->input->post('cTime'),
			'progress'	=>		$this->input->post('progress'),
			'treatment'	=>		$this->input->post('treatment'),
			'remarks'		=>		$this->input->post('remarks'),
			'cPreparedBy'	=>		$this->session->userdata('user_id'),
			'InActive'		=>		0
		);	
		$query = $this->db->insert('iop_progress_note',$this->data);
		return ($query === true);
	}
	
	public function getIntake($iop_no){
		if (!$this->db->table_exists('iop_intake_record')) {
			return array();
		}
		$this->db->order_by("dDateTime","DESC");
		$query = $this->db->get_where("iop_intake_record",array(
			'iop_id'	=>	$iop_no,
			'InActive'	=>	0
		));
		return $query->result();
	}
	
	public function getOutput($iop_no){
		if (!$this->db->table_exists('iop_output_record')) {
			return array();
		}
		$this->db->order_by("dDateTime","DESC");
		$query = $this->db->get_where("iop_output_record",array(
			'iop_id'	=>	$iop_no,
			'InActive'	=>	0
		));
		return $query->result();
	}
	
	public function room_transfer($iop_no){
		$this->db->select("
			A.transfer_id,
			D.category_name,
			C.room_name,
			B.bed_name,
			A.dDateTime,
			E.floor_name,
			A.reason,
			A.cPreparedBy
		");
		$this->db->order_by("transfer_id","DESC");
		$this->db->join("room_beds B","B.room_bed_id = A.bed_id","left outer");
		$this->db->join("room_master C","C.room_master_id = A.room_master_id","left outer");
		$this->db->join("room_category D","D.category_id = A.room_category_id","left outer");
		$this->db->join("floor E","E.floor_id = C.floor","left outer");
		$query = $this->db->get_where("iop_room_transfer A",array(
			'A.iop_id'	=>		$iop_no,
			'A.InActive'	=>		0
		));	
		return $query->result();
	}
	
	public function savepatientRoom(){
		$this->data = array(
				'iop_id'				=>		$this->input->post('iopNo'),
				'dDate'					=>		date("Y-m-d"),
				'dDateTime'				=>		date("Y-m-d h:i:s"),
				'room_category_id'		=>		$this->input->post('roomType'),
				'room_master_id'		=>		$this->input->post('room_idfor'),
				'bed_id'				=>		$this->input->post('bed_no'),
				'reason'				=>		'Patient Admitted',
				'cPreparedBy'			=>		$this->session->userdata('user_id'),
				'InActive'				=>		0
			);
			$this->db->insert('iop_room_transfer',$this->data);
	}

	public function create_ipd_admission_from_detention($params)
	{
		$params = is_array($params) ? $params : array();
		$ipd_iop_id = isset($params['ipd_iop_id']) ? trim((string)$params['ipd_iop_id']) : '';
		$patient_no = isset($params['patient_no']) ? trim((string)$params['patient_no']) : '';
		$doctor_id = isset($params['doctor_id']) ? trim((string)$params['doctor_id']) : '';
		$department_id = isset($params['department_id']) ? trim((string)$params['department_id']) : '';
		$bed_id = isset($params['bed_id']) ? (int)$params['bed_id'] : 0;
		$admitted_at = isset($params['admitted_at']) ? trim((string)$params['admitted_at']) : '';
		$opd_iop_id = isset($params['opd_iop_id']) ? trim((string)$params['opd_iop_id']) : '';

		if ($ipd_iop_id === '' || $patient_no === '' || $bed_id <= 0 || $admitted_at === '' || $opd_iop_id === '') {
			return array('ok' => false, 'error' => 'Missing required admission parameters');
		}

		$this->db->trans_begin();
		try {
			// Idempotency: do not create duplicate IPD admission for the same OPD detention
			if ($this->db->field_exists('source_opd_iop_id', 'patient_details_iop')) {
				$q = $this->db->query(
					"SELECT IO_ID FROM patient_details_iop WHERE patient_type = 'IPD' AND source_opd_iop_id = ? AND InActive = 0 LIMIT 1 FOR UPDATE",
					array($opd_iop_id)
				);
				if ($q === false) {
					$q = $this->db->query(
						"SELECT IO_ID FROM patient_details_iop WHERE patient_type = 'IPD' AND source_opd_iop_id = ? AND InActive = 0 LIMIT 1",
						array($opd_iop_id)
					);
				}
				$existing = $q ? $q->row() : null;
				if ($existing && isset($existing->IO_ID)) {
					$this->db->trans_commit();
					return array('ok' => true, 'already_exists' => true, 'ipd_iop_id' => (string)$existing->IO_ID);
				}
			}

			// Do not allow duplicate active IPD admissions for this patient
			$q = $this->db->query(
				"SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0 LIMIT 1 FOR UPDATE",
				array($patient_no)
			);
			if ($q === false) {
				$q = $this->db->query(
					"SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0 LIMIT 1",
					array($patient_no)
				);
			}
			$active = $q ? $q->row() : null;
			if ($active) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Patient already has an active IPD admission');
			}

			$bed = $this->lock_bed_row($bed_id);
			if (!$bed) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Bed not found');
			}
			$bedStatus = isset($bed->nStatus) ? strtoupper(trim((string)$bed->nStatus)) : '';
			$bedOcc = isset($bed->patient_no) ? trim((string)$bed->patient_no) : '';
			if ($bedStatus === 'OCCUPIED' && $bedOcc !== '' && $bedOcc !== $opd_iop_id && $bedOcc !== $ipd_iop_id) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Bed is already occupied');
			}

			$room_master_id = isset($bed->room_master_id) ? (int)$bed->room_master_id : 0;
			if ($room_master_id <= 0) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Bed missing room_master_id');
			}
			$this->db->where(array('room_master_id' => $room_master_id, 'InActive' => 0));
			$this->db->limit(1);
			$rm = $this->db->get('room_master')->row();
			if (!$rm) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Room master not found');
			}
			$room_category_id = isset($rm->category_id) ? (int)$rm->category_id : 0;
			if ($room_category_id <= 0) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Room missing category_id');
			}

			$visitDate = date('Y-m-d', strtotime($admitted_at));
			$visitTime = date('H:i:s', strtotime($admitted_at));

			$data = array(
				'IO_ID' => $ipd_iop_id,
				'patient_no' => $patient_no,
				'patient_type' => 'IPD',
				'date_visit' => $visitDate,
				'time_visit' => $visitTime,
				'doctor_id' => $doctor_id === '' ? null : $doctor_id,
				'refferal_doctor' => 0,
				'room_id' => $bed_id,
				'department_id' => $department_id === '' ? null : $department_id,
				'nStatus' => 'Pending',
				'InActive' => 0
			);
			if ($this->db->field_exists('source_opd_iop_id', 'patient_details_iop')) {
				$data['source_opd_iop_id'] = $opd_iop_id;
			}

			$this->db->insert('patient_details_iop', $data);
			if ((int)$this->db->affected_rows() <= 0) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Failed to create IPD admission');
			}

			if (!$this->conditional_occupy_bed($bed_id, $ipd_iop_id)) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Bed could not be occupied (concurrent update)');
			}

			$this->db->insert('iop_room_transfer', array(
				'iop_id' => $ipd_iop_id,
				'dDate' => $visitDate,
				'dDateTime' => $admitted_at,
				'room_category_id' => $room_category_id,
				'room_master_id' => $room_master_id,
				'bed_id' => $bed_id,
				'reason' => 'Patient Admitted',
				'cPreparedBy' => null,
				'InActive' => 0
			));

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Admission transaction failed');
			}
			$this->db->trans_commit();
			return array('ok' => true, 'ipd_iop_id' => $ipd_iop_id);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Exception: ' . $e->getMessage());
		}
	}
    
    public function getAll_search($limit = 10, $offset = 0){
        $this->db->select("
            A.patient_no,
            concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
            C.cValue as 'gender',
            D.cValue as 'civil_status',
            A.age,
            A.date_entry
        ",false);
        $search = $this->db->escape_like_str($this->input->post('search'));
        $where = "(
                A.lastname LIKE '%{$search}%' ESCAPE '!' OR 
                A.firstname LIKE '%{$search}%' ESCAPE '!' OR 
                A.patient_no LIKE '%{$search}%' ESCAPE '!'
                ) 
                AND A.InActive = 0";
        $this->db->where($where);
        $this->db->order_by('lastname','asc');
        $this->db->join("system_parameters B","B.param_id = A.title","left outer");
        $this->db->join("system_parameters C","C.param_id = A.gender","left outer");
        $this->db->join("system_parameters D","D.param_id = A.civil_status","left outer");
        $query = $this->db->get("patient_personal_info A", $limit, $offset);
        return $query->result();
    }
    
    public function count_all_search(){
        $this->db->select("
            A.patient_no,
            concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
            C.cValue,
            D.cValue,
            A.age,
            A.date_entry
        ",false);
        $search = $this->db->escape_like_str($this->input->post('search'));
        $where = "(
                A.lastname LIKE '%{$search}%' ESCAPE '!' OR 
                A.firstname LIKE '%{$search}%' ESCAPE '!' OR 
                A.patient_no LIKE '%{$search}%' ESCAPE '!'
                )           
                AND A.InActive = 0";
        $this->db->where($where);
        $this->db->order_by('lastname','asc');
        $this->db->join("system_parameters B","B.param_id = A.title","left outer");
        $this->db->join("system_parameters C","C.param_id = A.gender","left outer");
        $this->db->join("system_parameters D","D.param_id = A.civil_status","left outer");
        $query = $this->db->get("patient_personal_info A");
        return $query->num_rows();
    }
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
}
