<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Patient_history_model extends CI_Model{
	public function __construct(){
		parent::__construct();
	}

	private function index_exists($table_name, $index_name){
		$table_name = (string)$table_name;
		$index_name = (string)$index_name;
		if ($table_name === '' || $index_name === '' || !$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW INDEX FROM `".$table_name."` WHERE Key_name = ".$this->db->escape($index_name));
		return ($q && $q->num_rows() > 0);
	}

	public function ensure_history_indexes(){
		try {
			if ($this->table_exists('patient_details_iop') && !$this->index_exists('patient_details_iop', 'idx_patient_visit')) {
				$this->db->query("CREATE INDEX idx_patient_visit ON patient_details_iop (patient_no, date_visit, time_visit)");
			}
			if ($this->table_exists('iop_vital_parameters') && !$this->index_exists('iop_vital_parameters', 'idx_iop_dt')) {
				$this->db->query("CREATE INDEX idx_iop_dt ON iop_vital_parameters (iop_id, dDateTime)");
			}
			if ($this->table_exists('iop_progress_note') && !$this->index_exists('iop_progress_note', 'idx_iop_dt')) {
				$this->db->query("CREATE INDEX idx_iop_dt ON iop_progress_note (iop_id, dDateTime)");
			}
			if ($this->table_exists('iop_nurse_notes') && !$this->index_exists('iop_nurse_notes', 'idx_iop_dt')) {
				$this->db->query("CREATE INDEX idx_iop_dt ON iop_nurse_notes (iop_id, dDateTime)");
			}
			if ($this->table_exists('iop_diagnosis') && !$this->index_exists('iop_diagnosis', 'idx_iop')) {
				$this->db->query("CREATE INDEX idx_iop ON iop_diagnosis (iop_id)");
			}
			if ($this->table_exists('iop_complaints') && !$this->index_exists('iop_complaints', 'idx_iop')) {
				$this->db->query("CREATE INDEX idx_iop ON iop_complaints (iop_id)");
			}
			if ($this->table_exists('iop_medication') && !$this->index_exists('iop_medication', 'idx_iop')) {
				$this->db->query("CREATE INDEX idx_iop ON iop_medication (iop_id)");
			}
			if ($this->table_exists('iop_laboratory') && !$this->index_exists('iop_laboratory', 'idx_iop')) {
				$this->db->query("CREATE INDEX idx_iop ON iop_laboratory (iop_id)");
			}
			if ($this->table_exists('iop_billing') && !$this->index_exists('iop_billing', 'idx_iop')) {
				$this->db->query("CREATE INDEX idx_iop ON iop_billing (iop_id)");
			}
			if ($this->table_exists('iop_receipt') && !$this->index_exists('iop_receipt', 'idx_invoice')) {
				$this->db->query("CREATE INDEX idx_invoice ON iop_receipt (invoice_no)");
			}
		} catch (Exception $e) {
			log_message('error', 'ensure_history_indexes exception: ' . (string)$e->getMessage());
		}
		return true;
	}

	public function table_exists($table_name){
		$table_name = (string)$table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table_name, $column_name){
		$table_name = (string)$table_name;
		$column_name = (string)$column_name;
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		return ($q && $q->num_rows() > 0);
	}

	public function ensure_access_log_schema(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `patient_history_access_log` (\n".
			"  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,\n".
			"  `patient_no` varchar(25) NOT NULL,\n".
			"  `iop_id` varchar(25) DEFAULT NULL,\n".
			"  `encounter_type` varchar(3) DEFAULT NULL,\n".
			"  `user_id` varchar(25) DEFAULT NULL,\n".
			"  `user_role` varchar(25) DEFAULT NULL,\n".
			"  `module` varchar(50) DEFAULT NULL,\n".
			"  `action` varchar(30) NOT NULL,\n".
			"  `route` varchar(255) DEFAULT NULL,\n".
			"  `ipaddress` varchar(60) DEFAULT NULL,\n".
			"  `user_agent` varchar(255) DEFAULT NULL,\n".
			"  `created_at` datetime NOT NULL,\n".
			"  `InActive` int(1) NOT NULL DEFAULT 0,\n".
			"  PRIMARY KEY (`log_id`),\n".
			"  KEY `idx_patient` (`patient_no`),\n".
			"  KEY `idx_user` (`user_id`),\n".
			"  KEY `idx_created` (`created_at`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function log_access($patient_no, $action, $context = array()){
		$this->ensure_access_log_schema();
		$patient_no = trim((string)$patient_no);
		$action = strtoupper(trim((string)$action));
		if ($patient_no === '' || $action === '') {
			return false;
		}
		$data = array(
			'patient_no' => $patient_no,
			'iop_id' => isset($context['iop_id']) ? (string)$context['iop_id'] : null,
			'encounter_type' => isset($context['encounter_type']) ? (string)$context['encounter_type'] : null,
			'user_id' => isset($context['user_id']) ? (string)$context['user_id'] : null,
			'user_role' => isset($context['user_role']) ? (string)$context['user_role'] : null,
			'module' => isset($context['module']) ? (string)$context['module'] : null,
			'action' => substr($action, 0, 30),
			'route' => isset($context['route']) ? substr((string)$context['route'], 0, 255) : null,
			'ipaddress' => isset($context['ipaddress']) ? substr((string)$context['ipaddress'], 0, 60) : null,
			'user_agent' => isset($context['user_agent']) ? substr((string)$context['user_agent'], 0, 255) : null,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive' => 0
		);
		$ok = $this->db->insert('patient_history_access_log', $data);
		return (bool)$ok;
	}

	public function get_patient_summary($patient_no){
		$patient_no = trim((string)$patient_no);
		$out = array('total_visits' => 0, 'last_visit' => null, 'active_conditions' => array());
		if ($patient_no === '' || !$this->table_exists('patient_details_iop')) {
			return $out;
		}
		$q = $this->db->query("SELECT COUNT(*) AS c, MAX(date_visit) AS last_visit FROM patient_details_iop WHERE patient_no = ? AND InActive = 0", array($patient_no));
		$r = $q ? $q->row_array() : null;
		if ($r) {
			$out['total_visits'] = isset($r['c']) ? (int)$r['c'] : 0;
			$out['last_visit'] = isset($r['last_visit']) ? $r['last_visit'] : null;
		}
		if ($this->table_exists('iop_diagnosis')) {
			$hasMaster = $this->table_exists('diagnosis');
			if ($hasMaster) {
				$dq = $this->db->query("SELECT dx_name FROM (SELECT COALESCE(B.diagnosis_name, A.diagnosis_text) AS dx_name, MAX(A.iop_diag_id) AS max_id FROM iop_diagnosis A LEFT JOIN diagnosis B ON B.diagnosis_id = A.diagnosis_id INNER JOIN patient_details_iop P ON P.IO_ID = A.iop_id AND P.InActive = 0 WHERE P.patient_no = ? AND A.InActive = 0 GROUP BY dx_name ORDER BY max_id DESC LIMIT 5) T", array($patient_no));
			} else {
				$dq = $this->db->query("SELECT dx_name FROM (SELECT A.diagnosis_text AS dx_name, MAX(A.iop_diag_id) AS max_id FROM iop_diagnosis A INNER JOIN patient_details_iop P ON P.IO_ID = A.iop_id AND P.InActive = 0 WHERE P.patient_no = ? AND A.InActive = 0 GROUP BY dx_name ORDER BY max_id DESC LIMIT 5) T", array($patient_no));
			}
			$rows = $dq ? $dq->result_array() : array();
			foreach ($rows as $row) {
				$n = isset($row['dx_name']) ? trim((string)$row['dx_name']) : '';
				if ($n !== '') {
					$out['active_conditions'][] = $n;
				}
			}
		}
		return $out;
	}

	public function get_visit_header($iop_id){
		$iop_id = trim((string)$iop_id);
		if ($iop_id === '' || !$this->table_exists('patient_details_iop')) {
			return null;
		}

		// Core fields always present
		$select = "A.IO_ID, A.patient_no, A.patient_type, A.date_visit, A.time_visit, A.doctor_id, A.department_id,
			A.provisional_diagnosis, A.complaints, A.nStatus,
			A.allergies, A.warnings, A.social_history, A.family_history, A.personal_history, A.past_medical_history,
			concat(F.cValue,' ',E.firstname,' ',E.middlename,' ',E.lastname) as doctor_name, D.dept_name";

		// Extended clinical history columns (added by clinical_history_model migration)
		$extended_cols = array(
			'history_presenting_complaint',
			'past_surgical_history',
			'drug_history',
			'gynae_obstetric_history',
			'on_direct_questioning',
			'examination_findings',
			'examination_general',
			'examination_cardiovascular',
			'examination_respiratory',
			'examination_gastrointestinal',
			'examination_neurological',
			'examination_musculoskeletal',
			'examination_other',
			'clinical_history_updated_at',
			'clinical_history_updated_by',
		);
		foreach ($extended_cols as $col) {
			if ($this->column_exists('patient_details_iop', $col)) {
				$select .= ", A.`{$col}`";
			}
		}

		$this->db->select($select, false);
		$this->db->from('patient_details_iop A');
		$this->db->join('department D', 'D.department_id = A.department_id', 'left');
		$this->db->join('users E', '(E.user_id = A.doctor_id OR CAST(E.user_id AS UNSIGNED) = CAST(A.doctor_id AS UNSIGNED))', 'left', false);
		$this->db->join('system_parameters F', 'F.param_id = E.title', 'left');
		$this->db->where(array('A.IO_ID' => $iop_id, 'A.InActive' => 0));
		$this->db->limit(1);
		$q = $this->db->get();
		return $q ? $q->row() : null;
	}

	public function count_visits($patient_no, $filters = array()){
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '' || !$this->table_exists('patient_details_iop')) {
			return 0;
		}
		$params = array($patient_no);
		$sql = $this->build_visits_sql($filters, true);
		$q = $this->db->query($sql['sql'], array_merge($params, $sql['bind']));
		$r = $q ? $q->row_array() : null;
		return $r && isset($r['c']) ? (int)$r['c'] : 0;
	}

	public function get_visits($patient_no, $filters = array(), $limit = 20, $offset = 0){
		$patient_no = trim((string)$patient_no);
		$limit = (int)$limit;
		$offset = (int)$offset;
		if ($limit <= 0) {
			$limit = 20;
		}
		if ($offset < 0) {
			$offset = 0;
		}
		if ($patient_no === '' || !$this->table_exists('patient_details_iop')) {
			return array();
		}
		$params = array($patient_no);
		$sql = $this->build_visits_sql($filters, false);
		$q = $this->db->query($sql['sql']." LIMIT ".$offset.", ".$limit, array_merge($params, $sql['bind']));
		return $q ? $q->result_array() : array();
	}

	private function build_visits_sql($filters, $countOnly){
		$bind = array();
		$where = array();
		$where[] = "A.patient_no = ?";
		$where[] = "A.InActive = 0";

		$hasLabs = $this->table_exists('iop_laboratory');
		$hasDiag = $this->table_exists('iop_diagnosis');
		$hasMeds = $this->table_exists('iop_medication');
		$hasVitals = $this->table_exists('iop_vital_parameters');
		$hasBilling = $this->table_exists('iop_billing');
		$hasDiagnosisMaster = $this->table_exists('diagnosis');
		$hasDrugMaster = $this->table_exists('medicine_drug_name');
		$hasBillParticular = $this->table_exists('bill_particular');
		$hasSonoItems = $this->table_exists('sonography_items');

		$from = isset($filters['from']) ? trim((string)$filters['from']) : '';
		$to = isset($filters['to']) ? trim((string)$filters['to']) : '';
		$doctor = isset($filters['doctor']) ? trim((string)$filters['doctor']) : '';
		$encounter = isset($filters['encounter']) ? strtoupper(trim((string)$filters['encounter'])) : '';
		$type = isset($filters['type']) ? strtolower(trim((string)$filters['type'])) : '';
		$q = isset($filters['q']) ? trim((string)$filters['q']) : '';

		if ($from !== '') {
			$where[] = "A.date_visit >= ?";
			$bind[] = $from;
		}
		if ($to !== '') {
			$where[] = "A.date_visit <= ?";
			$bind[] = $to;
		}
		if ($doctor !== '') {
			$where[] = "A.doctor_id = ?";
			$bind[] = $doctor;
		}
		if ($encounter === 'OPD' || $encounter === 'IPD') {
			$where[] = "A.patient_type = ?";
			$bind[] = $encounter;
		}

		if ($type === 'labs' && $hasLabs) {
			$where[] = "EXISTS(SELECT 1 FROM iop_laboratory L WHERE L.iop_id = A.IO_ID AND L.InActive = 0 AND (L.category_id IS NULL OR L.category_id <> 18))";
		}
		if ($type === 'imaging' && $hasLabs) {
			$where[] = "EXISTS(SELECT 1 FROM iop_laboratory L WHERE L.iop_id = A.IO_ID AND L.InActive = 0 AND L.category_id = 18)";
		}
		if ($type === 'diagnosis' && $hasDiag) {
			$where[] = "EXISTS(SELECT 1 FROM iop_diagnosis D WHERE D.iop_id = A.IO_ID AND D.InActive = 0)";
		}
		if ($type === 'prescriptions' && $hasMeds) {
			$where[] = "EXISTS(SELECT 1 FROM iop_medication M WHERE M.iop_id = A.IO_ID AND M.InActive = 0)";
		}
		if ($type === 'vitals' && $hasVitals) {
			$where[] = "EXISTS(SELECT 1 FROM iop_vital_parameters V WHERE V.iop_id = A.IO_ID AND V.InActive = 0)";
		}
		if ($type === 'billing' && $hasBilling) {
			$where[] = "EXISTS(SELECT 1 FROM iop_billing B WHERE B.iop_id = A.IO_ID AND B.InActive = 0)";
		}

		if ($q !== '') {
			$like = '%'.$q.'%';
			$pieces = array();
			$pieces[] = "A.provisional_diagnosis LIKE ?";
			$bind[] = $like;
			$pieces[] = "A.complaints LIKE ?";
			$bind[] = $like;

			if ($hasDiag) {
				if ($hasDiagnosisMaster) {
					$pieces[] = "EXISTS(SELECT 1 FROM iop_diagnosis DI LEFT JOIN diagnosis DX ON DX.diagnosis_id = DI.diagnosis_id WHERE DI.iop_id = A.IO_ID AND DI.InActive = 0 AND (DX.diagnosis_name LIKE ? OR DI.diagnosis_text LIKE ?))";
					$bind[] = $like;
					$bind[] = $like;
				} else {
					$pieces[] = "EXISTS(SELECT 1 FROM iop_diagnosis DI WHERE DI.iop_id = A.IO_ID AND DI.InActive = 0 AND (DI.diagnosis_text LIKE ?))";
					$bind[] = $like;
				}
			}

			if ($hasMeds) {
				if ($hasDrugMaster) {
					$pieces[] = "EXISTS(SELECT 1 FROM iop_medication MI LEFT JOIN medicine_drug_name DN ON DN.drug_id = MI.medicine_id WHERE MI.iop_id = A.IO_ID AND MI.InActive = 0 AND (DN.drug_name LIKE ? OR MI.medicine_text LIKE ?))";
					$bind[] = $like;
					$bind[] = $like;
				} else {
					$pieces[] = "EXISTS(SELECT 1 FROM iop_medication MI WHERE MI.iop_id = A.IO_ID AND MI.InActive = 0 AND (MI.medicine_text LIKE ?))";
					$bind[] = $like;
				}
			}

			if ($hasLabs) {
				$labPieces = array();
				if ($hasBillParticular) {
					$labPieces[] = "BP.particular_name LIKE ?";
					$bind[] = $like;
				}
				if ($hasSonoItems) {
					$labPieces[] = "SI.item_name LIKE ?";
					$bind[] = $like;
				}
				$labPieces[] = "LA.laboratory_text LIKE ?";
				$bind[] = $like;
				$pieces[] = "EXISTS(SELECT 1 FROM iop_laboratory LA ".($hasBillParticular ? "LEFT JOIN bill_particular BP ON BP.particular_id = LA.laboratory_id " : "").($hasSonoItems ? "LEFT JOIN sonography_items SI ON SI.item_id = LA.laboratory_id " : "")."WHERE LA.iop_id = A.IO_ID AND LA.InActive = 0 AND (".implode(' OR ', $labPieces)."))";
			}

			$where[] = "(".implode(' OR ', $pieces).")";
		}

		$w = implode(' AND ', $where);
		if ($countOnly) {
			$sql = "SELECT COUNT(*) AS c FROM patient_details_iop A WHERE ".$w;
			return array('sql' => $sql, 'bind' => $bind);
		}

		$selHasVitals = $hasVitals ? "CASE WHEN EXISTS(SELECT 1 FROM iop_vital_parameters V WHERE V.iop_id = A.IO_ID AND V.InActive = 0) THEN 1 ELSE 0 END AS has_vitals," : "0 AS has_vitals,";
		$selHasDiag = $hasDiag ? "CASE WHEN EXISTS(SELECT 1 FROM iop_diagnosis DI WHERE DI.iop_id = A.IO_ID AND DI.InActive = 0) THEN 1 ELSE 0 END AS has_diagnosis," : "0 AS has_diagnosis,";
		$selHasMeds = $hasMeds ? "CASE WHEN EXISTS(SELECT 1 FROM iop_medication MI WHERE MI.iop_id = A.IO_ID AND MI.InActive = 0) THEN 1 ELSE 0 END AS has_prescriptions," : "0 AS has_prescriptions,";
		$selHasBilling = $hasBilling ? "CASE WHEN EXISTS(SELECT 1 FROM iop_billing BI WHERE BI.iop_id = A.IO_ID AND BI.InActive = 0) THEN 1 ELSE 0 END AS has_billing," : "0 AS has_billing,";
		$selHasLabs = $hasLabs ? "CASE WHEN EXISTS(SELECT 1 FROM iop_laboratory L WHERE L.iop_id = A.IO_ID AND L.InActive = 0 AND (L.category_id IS NULL OR L.category_id <> 18)) THEN 1 ELSE 0 END AS has_labs, CASE WHEN EXISTS(SELECT 1 FROM iop_laboratory L2 WHERE L2.iop_id = A.IO_ID AND L2.InActive = 0 AND L2.category_id = 18) THEN 1 ELSE 0 END AS has_imaging," : "0 AS has_labs, 0 AS has_imaging,";

		$sql = "SELECT A.IO_ID AS iop_id,
			A.patient_type AS encounter_type,
			A.date_visit,
			A.time_visit,
			A.nStatus,
			A.provisional_diagnosis,
			A.complaints,
			A.doctor_id,
			A.department_id,
			D.dept_name,
			concat(F.cValue,' ',E.firstname,' ',E.middlename,' ',E.lastname) as doctor_name,
			".$selHasVitals."
			".$selHasDiag."
			".$selHasMeds."
			".$selHasLabs."
			".$selHasBilling."
			CONCAT(A.date_visit,' ',A.time_visit) AS visit_datetime
			FROM patient_details_iop A
			LEFT JOIN department D ON D.department_id = A.department_id
			LEFT JOIN users E ON (E.user_id = A.doctor_id OR CAST(E.user_id AS UNSIGNED) = CAST(A.doctor_id AS UNSIGNED))
			LEFT JOIN system_parameters F ON F.param_id = E.title
			WHERE ".$w."
			ORDER BY A.date_visit DESC, A.time_visit DESC, A.IO_ID DESC";
		return array('sql' => $sql, 'bind' => $bind);
	}

	/* ── Enhanced aggregation methods ────────────────────────── */

	/**
	 * Count all record types across every visit for a patient.
	 */
	public function get_patient_record_counts($patient_no){
		$patient_no = trim((string)$patient_no);
		$out = array(
			'vitals'        => 0,
			'labs'          => 0,
			'imaging'       => 0,
			'prescriptions' => 0,
			'diagnoses'     => 0,
			'invoices'      => 0
		);
		if ($patient_no === '' || !$this->table_exists('patient_details_iop')) {
			return $out;
		}

		$iop_sub = "(SELECT IO_ID FROM patient_details_iop WHERE patient_no = ".$this->db->escape($patient_no)." AND InActive = 0)";

		if ($this->table_exists('iop_vital_parameters')) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_vital_parameters WHERE iop_id IN ".$iop_sub." AND InActive = 0");
			$r = $q ? $q->row_array() : null;
			$out['vitals'] = ($r && isset($r['c'])) ? (int)$r['c'] : 0;
		}
		if ($this->table_exists('iop_laboratory')) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory WHERE iop_id IN ".$iop_sub." AND InActive = 0 AND (category_id IS NULL OR category_id <> 18)");
			$r = $q ? $q->row_array() : null;
			$out['labs'] = ($r && isset($r['c'])) ? (int)$r['c'] : 0;

			$q2 = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory WHERE iop_id IN ".$iop_sub." AND InActive = 0 AND category_id = 18");
			$r2 = $q2 ? $q2->row_array() : null;
			$out['imaging'] = ($r2 && isset($r2['c'])) ? (int)$r2['c'] : 0;
		}
		if ($this->table_exists('iop_medication')) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE iop_id IN ".$iop_sub." AND InActive = 0");
			$r = $q ? $q->row_array() : null;
			$out['prescriptions'] = ($r && isset($r['c'])) ? (int)$r['c'] : 0;
		}
		if ($this->table_exists('iop_diagnosis')) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_diagnosis WHERE iop_id IN ".$iop_sub." AND InActive = 0");
			$r = $q ? $q->row_array() : null;
			$out['diagnoses'] = ($r && isset($r['c'])) ? (int)$r['c'] : 0;
		}
		if ($this->table_exists('iop_billing')) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_billing WHERE iop_id IN ".$iop_sub." AND InActive = 0");
			$r = $q ? $q->row_array() : null;
			$out['invoices'] = ($r && isset($r['c'])) ? (int)$r['c'] : 0;
		}
		return $out;
	}

	/**
	 * Latest vitals reading for a patient (most recent across all visits).
	 */
	public function get_latest_vitals($patient_no){
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '' || !$this->table_exists('patient_details_iop') || !$this->table_exists('iop_vital_parameters')) {
			return null;
		}
		$q = $this->db->query(
			"SELECT V.*, P.date_visit, P.IO_ID AS iop_id
			 FROM iop_vital_parameters V
			 INNER JOIN patient_details_iop P ON P.IO_ID = V.iop_id AND P.InActive = 0
			 WHERE P.patient_no = ? AND V.InActive = 0
			 ORDER BY V.dDateTime DESC, V.vital_id DESC
			 LIMIT 1",
			array($patient_no)
		);
		return $q ? $q->row_array() : null;
	}

	/**
	 * Active / recent medications for a patient (last 30 days or last 10).
	 */
	public function get_active_medications($patient_no, $limit = 10){
		$patient_no = trim((string)$patient_no);
		$limit = (int)$limit;
		if ($limit <= 0) $limit = 10;
		if ($patient_no === '' || !$this->table_exists('patient_details_iop') || !$this->table_exists('iop_medication')) {
			return array();
		}
		$hasDrug = $this->table_exists('medicine_drug_name');
		$sql = "SELECT M.iop_med_id, M.dDate, M.instruction, M.dosage, M.days, M.total_qty, M.medicine_text,
				P.date_visit, P.IO_ID AS iop_id"
				.($hasDrug ? ", D.drug_name" : ", NULL AS drug_name")
				." FROM iop_medication M
				 INNER JOIN patient_details_iop P ON P.IO_ID = M.iop_id AND P.InActive = 0"
				.($hasDrug ? " LEFT JOIN medicine_drug_name D ON D.drug_id = M.medicine_id" : "")
				." WHERE P.patient_no = ? AND M.InActive = 0
				 ORDER BY M.dDate DESC, M.iop_med_id DESC
				 LIMIT ".$limit;
		$q = $this->db->query($sql, array($patient_no));
		return $q ? $q->result_array() : array();
	}

	/**
	 * Allergy and warning summary: collect from all visits.
	 */
	public function get_allergy_summary($patient_no){
		$patient_no = trim((string)$patient_no);
		$out = array('allergies' => array(), 'warnings' => array());
		if ($patient_no === '' || !$this->table_exists('patient_details_iop')) {
			return $out;
		}
		$q = $this->db->query(
			"SELECT allergies, warnings FROM (
			   SELECT allergies, warnings, MAX(date_visit) AS latest_visit
			   FROM patient_details_iop
			   WHERE patient_no = ? AND InActive = 0
			     AND (IFNULL(allergies,'') <> '' OR IFNULL(warnings,'') <> '')
			   GROUP BY allergies, warnings
			   ORDER BY latest_visit DESC
			   LIMIT 20
			 ) T",
			array($patient_no)
		);
		$rows = $q ? $q->result_array() : array();
		$seen_a = array();
		$seen_w = array();
		foreach ($rows as $r) {
			$a = isset($r['allergies']) ? trim((string)$r['allergies']) : '';
			$w = isset($r['warnings']) ? trim((string)$r['warnings']) : '';
			if ($a !== '' && !isset($seen_a[$a])) {
				$seen_a[$a] = true;
				$out['allergies'][] = $a;
			}
			if ($w !== '' && !isset($seen_w[$w])) {
				$seen_w[$w] = true;
				$out['warnings'][] = $w;
			}
		}
		return $out;
	}

	/**
	 * Medical history summary: social, family, personal, past medical from most recent visit.
	 */
	public function get_medical_history_summary($patient_no){
		$patient_no = trim((string)$patient_no);
		$out = array(
			'social_history'               => '',
			'family_history'               => '',
			'personal_history'             => '',
			'past_medical_history'         => '',
			'history_presenting_complaint' => '',
			'past_surgical_history'        => '',
			'drug_history'                 => '',
			'gynae_obstetric_history'      => '',
			'on_direct_questioning'        => '',
			'examination_findings'         => '',
			'examination_general'          => '',
			'examination_cardiovascular'   => '',
			'examination_respiratory'      => '',
			'examination_gastrointestinal' => '',
			'examination_neurological'     => '',
			'examination_musculoskeletal'  => '',
			'examination_other'            => '',
		);
		if ($patient_no === '' || !$this->table_exists('patient_details_iop')) {
			return $out;
		}
		// Get the most recent non-empty value for each history field
		$fields = array_keys($out);
		foreach ($fields as $f) {
			if (!$this->column_exists('patient_details_iop', $f)) {
				continue;
			}
			$q = $this->db->query(
				"SELECT `".$f."` AS val FROM patient_details_iop
				 WHERE patient_no = ? AND InActive = 0 AND IFNULL(`".$f."`,'')<> ''
				 ORDER BY date_visit DESC, IO_ID DESC LIMIT 1",
				array($patient_no)
			);
			$r = $q ? $q->row_array() : null;
			if ($r && isset($r['val'])) {
				$out[$f] = trim((string)$r['val']);
			}
		}
		return $out;
	}

	public function get_visit_details($iop_id){
		$hdr = $this->get_visit_header($iop_id);
		if (!$hdr) {
			return null;
		}
		$out = array(
			'visit' => $hdr,
			'vitals' => array(),
			'progress_notes' => array(),
			'nurse_notes' => array(),
			'diagnoses' => array(),
			'complaints' => array(),
			'prescriptions' => array(),
			'labs' => array(),
			'imaging' => array(),
			'billing' => array(
				'total' => 0.0,
				'paid' => 0.0,
				'outstanding' => 0.0,
				'invoices' => array()
			)
		);

		if ($this->table_exists('iop_vital_parameters')) {
			$this->db->order_by('dDateTime', 'DESC');
			$q = $this->db->get_where('iop_vital_parameters', array('iop_id' => $iop_id, 'InActive' => 0));
			$out['vitals'] = $q ? $q->result_array() : array();
		}

		if ($this->table_exists('iop_progress_note')) {
			$this->db->order_by('dDateTime', 'DESC');
			$q = $this->db->get_where('iop_progress_note', array('iop_id' => $iop_id, 'InActive' => 0));
			$out['progress_notes'] = $q ? $q->result_array() : array();
		}

		if ($this->table_exists('iop_nurse_notes')) {
			$this->db->order_by('dDateTime', 'DESC');
			$q = $this->db->get_where('iop_nurse_notes', array('iop_id' => $iop_id, 'InActive' => 0));
			$out['nurse_notes'] = $q ? $q->result_array() : array();
		}

		if ($this->table_exists('iop_diagnosis')) {
			$this->db->select('A.iop_diag_id, A.dDate, A.remarks, A.diagnosis_text, B.diagnosis_name', false);
			$this->db->from('iop_diagnosis A');
			$this->db->join('diagnosis B', 'B.diagnosis_id = A.diagnosis_id', 'left');
			$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
			$this->db->order_by('A.iop_diag_id', 'DESC');
			$q = $this->db->get();
			$out['diagnoses'] = $q ? $q->result_array() : array();
		}

		if ($this->table_exists('iop_complaints')) {
			$this->db->select('A.iop_comp_id, A.dDate, A.remarks, A.complain_text, B.complain_name', false);
			$this->db->from('iop_complaints A');
			$this->db->join('complain B', 'B.complain_id = A.complain_id', 'left');
			$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
			$this->db->order_by('A.iop_comp_id', 'DESC');
			$q = $this->db->get();
			$out['complaints'] = $q ? $q->result_array() : array();
		}

		if ($this->table_exists('iop_medication')) {
			$rxSel = 'A.iop_med_id, A.dDate, A.instruction, A.advice, A.days, A.total_qty, A.medicine_text, A.dosage, B.drug_name';
			if ($this->column_exists('iop_medication', 'frequency')) { $rxSel .= ", A.frequency"; }
			if ($this->column_exists('iop_medication', 'unit')) { $rxSel .= ", A.unit"; }
			if ($this->column_exists('iop_medication', 'route')) { $rxSel .= ", A.route"; }
			$this->db->select($rxSel, false);
			$this->db->from('iop_medication A');
			$this->db->join('medicine_drug_name B', 'B.drug_id = A.medicine_id', 'left');
			$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
			$this->db->order_by('A.iop_med_id', 'ASC');
			$q = $this->db->get();
			$out['prescriptions'] = $q ? $q->result_array() : array();
		}

		if ($this->table_exists('iop_laboratory')) {
			$hasWf = $this->table_exists('iop_laboratory_workflow');
			$hasSonoMeta = $this->table_exists('iop_sonography_request_meta');
			$hasSonoItems = $this->table_exists('sonography_items');
			$this->db->select("A.io_lab_id, A.category_id, A.dDateTime, A.findings, A.result, A.lab_result_upload, A.laboratory_text, A.laboratory_id,
				B.group_name,
				P.particular_name,
				SI.item_name AS sono_item_name".($hasWf ? ", WF.status AS wf_status, WF.requested_at, WF.performed_at, WF.reported_at, WF.verified_at" : "").
				($hasSonoMeta ? ", M.urgency, M.clinical_question, M.scan_item_id AS sono_scan_item_id" : "")
			, false);
			$this->db->from('iop_laboratory A');
			$this->db->join('bill_group_name B', 'B.group_id = A.category_id', 'left');
			$this->db->join('bill_particular P', 'P.particular_id = A.laboratory_id', 'left');
			if ($hasSonoItems) {
				$this->db->join('sonography_items SI', 'SI.item_id = A.laboratory_id', 'left');
			} else {
				$this->db->join('(SELECT NULL AS item_id, NULL AS item_name) SI', '1=0', 'left', false);
			}
			if ($hasWf) {
				$this->db->join('iop_laboratory_workflow WF', 'WF.io_lab_id = A.io_lab_id AND WF.InActive = 0', 'left', false);
			}
			if ($hasSonoMeta) {
				$this->db->join('iop_sonography_request_meta M', 'M.io_lab_id = A.io_lab_id AND M.InActive = 0', 'left', false);
			}
			$this->db->where(array('A.iop_id' => $iop_id, 'A.InActive' => 0));
			$this->db->order_by('A.io_lab_id', 'ASC');
			$q = $this->db->get();
			$rows = $q ? $q->result_array() : array();

			// Ghana HMS: classify imaging vs labs using SSOT category IDs + group_name heuristics.
			$sono_cat = 18;
			$rad_cat = 16;
			try {
				$this->load->model('app/laboratory_model');
				$sono_cat = (int)$this->laboratory_model->get_sonography_category_id();
				$rad_cat = (int)$this->laboratory_model->get_radiology_category_id();
			} catch (Exception $e) {
				$sono_cat = 18;
				$rad_cat = 16;
			}

			foreach ($rows as $r) {
				$cat = isset($r['category_id']) ? (int)$r['category_id'] : 0;
				$gname = isset($r['group_name']) ? strtolower(trim((string)$r['group_name'])) : '';
				$hasSonoMetaRow = ($hasSonoMeta && (isset($r['sono_scan_item_id']) && (int)$r['sono_scan_item_id'] > 0));
				$looksImaging = ($gname !== '' && (
					strpos($gname, 'x-ray') !== false
					|| strpos($gname, 'x rays') !== false
					|| strpos($gname, 'ct') !== false
					|| strpos($gname, 'scan') !== false
					|| strpos($gname, 'ultra') !== false
					|| strpos($gname, 'sonog') !== false
					|| strpos($gname, 'radiol') !== false
					|| strpos($gname, 'mri') !== false
					|| strpos($gname, 'imaging') !== false
				));
				$isImaging = ($cat === $sono_cat || $cat === $rad_cat || $looksImaging || $hasSonoMetaRow);

				// Display name SSOT: prefer what doctor saw/requested at time of ordering.
				$display = '';
				if (isset($r['laboratory_text']) && trim((string)$r['laboratory_text']) !== '') {
					$display = trim((string)$r['laboratory_text']);
				} elseif (isset($r['particular_name']) && trim((string)$r['particular_name']) !== '') {
					$display = trim((string)$r['particular_name']);
				} elseif (isset($r['sono_item_name']) && trim((string)$r['sono_item_name']) !== '') {
					$display = trim((string)$r['sono_item_name']);
				}
				$r['display_name'] = $display;

				if ($isImaging) {
					$out['imaging'][] = $r;
				} else {
					$out['labs'][] = $r;
				}
			}
		}

		// Billing: prefer SSOT billing_transactions; fall back to iop_billing.
		if ($this->table_exists('billing_transactions')) {
			try {
				$this->load->model('app/billing_transaction_model');
				$sum = $this->billing_transaction_model->get_encounter_summary($iop_id);
				$out['billing']['total'] = isset($sum->total_amount) ? (float)$sum->total_amount : 0.0;
				$out['billing']['paid'] = isset($sum->paid_amount) ? (float)$sum->paid_amount : 0.0;
				$out['billing']['outstanding'] = isset($sum->balance_amount) ? (float)$sum->balance_amount : max(0.0, $out['billing']['total'] - $out['billing']['paid']);
				$out['billing']['transactions'] = array();
				$this->db->select('invoice_no, department, item_name, item_type, payment_status, net_amount, paid_amount, balance_amount, quantity, created_at', false);
				$this->db->from('billing_transactions');
				$this->db->where(array('encounter_id' => $iop_id, 'InActive' => 0));
				$this->db->order_by('created_at', 'ASC');
				$qbt = $this->db->get();
				$btRows = $qbt ? $qbt->result_array() : array();
				$out['billing']['transactions'] = $btRows;
			} catch (Exception $e) {
				// Keep legacy fallback below
			}
		}

		if ($this->table_exists('iop_billing')) {
			$this->db->select('invoice_no, total_amount, payment_type, payer_type, dDate', false);
			$this->db->from('iop_billing');
			$this->db->where(array('iop_id' => $iop_id, 'InActive' => 0));
			$this->db->order_by('dDate', 'DESC');
			$q = $this->db->get();
			$invoices = $q ? $q->result_array() : array();
			$total = 0.0;
			$paid = 0.0;
			$paidCol = null;
			if ($this->table_exists('iop_receipt')) {
				if ($this->db->field_exists('amountPaid', 'iop_receipt')) {
					$paidCol = 'amountPaid';
				} elseif ($this->db->field_exists('total_amount', 'iop_receipt')) {
					$paidCol = 'total_amount';
				} elseif ($this->db->field_exists('amount', 'iop_receipt')) {
					$paidCol = 'amount';
				}
			}
			foreach ($invoices as $inv) {
				$invoice_no = isset($inv['invoice_no']) ? (string)$inv['invoice_no'] : '';
				$invTotal = isset($inv['total_amount']) ? (float)$inv['total_amount'] : 0.0;
				$total += $invTotal;
				$invPaid = 0.0;
				if ($invoice_no !== '' && $paidCol !== null) {
					$this->db->select('SUM(' . $paidCol . ') AS paid_amount', false);
					$this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
					$r = $this->db->get('iop_receipt')->row_array();
					$invPaid = ($r && isset($r['paid_amount'])) ? (float)$r['paid_amount'] : 0.0;
				}
				$paid += $invPaid;
				$inv['paid_amount'] = $invPaid;
				$inv['outstanding_amount'] = max(0.0, $invTotal - $invPaid);
				$out['billing']['invoices'][] = $inv;
			}
			// If SSOT summary was empty, populate totals from invoices
			if (!isset($out['billing']['total']) || (float)$out['billing']['total'] <= 0.0001) {
				$out['billing']['total'] = $total;
			}
			if (!isset($out['billing']['paid']) || (float)$out['billing']['paid'] <= 0.0001) {
				$out['billing']['paid'] = $paid;
			}
			if (!isset($out['billing']['outstanding']) || (float)$out['billing']['outstanding'] <= 0.0001) {
				$out['billing']['outstanding'] = max(0.0, (float)$out['billing']['total'] - (float)$out['billing']['paid']);
			}
		}

		return $out;
	}
}
