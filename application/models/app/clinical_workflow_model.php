<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Clinical Workflow Model
 * 
 * Handles master tables, patient encounters, and clinical workflow queries.
 * All schema changes are idempotent (safe to call repeatedly).
 */
class Clinical_workflow_model extends CI_Model
{
	private $schema_checked = false;

	public function __construct()
	{
		parent::__construct();
	}

	/* ================================================================== */
	/*  SCHEMA HELPERS                                                     */
	/* ================================================================== */

	private function table_exists($t)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$t));
		return ($q && $q->num_rows() > 0);
	}

	private function column_exists($table, $col)
	{
		if (!$this->table_exists($table)) return false;
		$q = $this->db->query("SHOW COLUMNS FROM `" . (string)$table . "` LIKE " . $this->db->escape((string)$col));
		return ($q && $q->num_rows() > 0);
	}

	/* ================================================================== */
	/*  MASTER SCHEMA MIGRATION (idempotent)                               */
	/* ================================================================== */

	public function ensure_clinical_schema()
	{
		if ($this->schema_checked) return;
		$this->schema_checked = true;

		$this->ensure_diagnosis_master();
		$this->ensure_medication_master_columns();
		$this->ensure_scan_master();
		$this->ensure_patient_encounters();
		$this->ensure_lab_request_columns();
	}

	/* ---------- DIAGNOSIS MASTER ---------- */

	private function ensure_diagnosis_master()
	{
		// The existing 'diagnosis' table is the master. Add missing columns.
		if (!$this->table_exists('diagnosis')) {
			$this->db->query("CREATE TABLE `diagnosis` (
				`diagnosis_id` int(11) NOT NULL AUTO_INCREMENT,
				`diagnosis_name` varchar(255) NOT NULL,
				`icd_code` varchar(20) DEFAULT NULL,
				`category` varchar(100) DEFAULT NULL,
				`description` text,
				`common_treatment` text,
				`is_active` tinyint(1) NOT NULL DEFAULT 1,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`diagnosis_id`),
				KEY `idx_name` (`diagnosis_name`(191)),
				KEY `idx_icd` (`icd_code`),
				KEY `idx_active` (`is_active`, `InActive`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		if (!$this->column_exists('diagnosis', 'icd_code')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `icd_code` varchar(20) DEFAULT NULL");
		}
		if (!$this->column_exists('diagnosis', 'category')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `category` varchar(100) DEFAULT NULL");
		}
		if (!$this->column_exists('diagnosis', 'description')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `description` text");
		}
		if (!$this->column_exists('diagnosis', 'common_treatment')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `common_treatment` text");
		}
		if (!$this->column_exists('diagnosis', 'is_active')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1");
		}

		// Seed common Ghana diagnoses if table is empty
		$q = $this->db->query("SELECT COUNT(*) AS c FROM `diagnosis` WHERE InActive = 0");
		$r = $q ? $q->row() : null;
		if ($r && (int)$r->c === 0) {
			$this->seed_ghana_diagnoses();
		}
	}

	private function seed_ghana_diagnoses()
	{
		$diagnoses = array(
			array('Malaria (Uncomplicated)', 'B54', 'Infectious Disease', 'ACT therapy'),
			array('Malaria (Severe)', 'B50.9', 'Infectious Disease', 'IV Artesunate'),
			array('Upper Respiratory Tract Infection', 'J06.9', 'Respiratory', 'Antibiotics, rest'),
			array('Pneumonia', 'J18.9', 'Respiratory', 'Antibiotics, oxygen therapy'),
			array('Hypertension', 'I10', 'Cardiovascular', 'Antihypertensives'),
			array('Diabetes Mellitus Type 2', 'E11', 'Endocrine', 'Metformin, lifestyle'),
			array('Gastroenteritis', 'A09', 'Gastrointestinal', 'ORS, antibiotics if bacterial'),
			array('Urinary Tract Infection', 'N39.0', 'Genitourinary', 'Antibiotics'),
			array('Typhoid Fever', 'A01.0', 'Infectious Disease', 'Ciprofloxacin/Ceftriaxone'),
			array('Anaemia', 'D64.9', 'Haematology', 'Iron supplements, treat cause'),
			array('Skin Infection', 'L08.9', 'Dermatology', 'Antibiotics, wound care'),
			array('Acute Watery Diarrhoea', 'A09.9', 'Gastrointestinal', 'ORS, zinc'),
			array('Asthma', 'J45.9', 'Respiratory', 'Bronchodilators, steroids'),
			array('Pregnancy (Normal)', 'Z34.9', 'Obstetrics', 'ANC monitoring'),
			array('Peptic Ulcer Disease', 'K27.9', 'Gastrointestinal', 'PPIs, H. pylori treatment'),
			array('Rheumatoid Arthritis', 'M06.9', 'Musculoskeletal', 'NSAIDs, DMARDs'),
			array('Sickle Cell Disease', 'D57', 'Haematology', 'Hydroxyurea, pain management'),
			array('HIV/AIDS', 'B24', 'Infectious Disease', 'ART'),
			array('Tuberculosis', 'A15.9', 'Infectious Disease', 'DOTS regimen'),
			array('Otitis Media', 'H66.9', 'ENT', 'Antibiotics, analgesics'),
			array('Conjunctivitis', 'H10.9', 'Ophthalmology', 'Eye drops'),
			array('Fracture', 'T14.2', 'Orthopaedics', 'Immobilisation, surgery'),
			array('Hernia', 'K40.9', 'Surgery', 'Surgical repair'),
			array('Appendicitis', 'K35.9', 'Surgery', 'Appendectomy'),
			array('Cerebrovascular Accident', 'I64', 'Neurology', 'Thrombolytics, rehabilitation'),
		);
		foreach ($diagnoses as $d) {
			$this->db->insert('diagnosis', array(
				'diagnosis_name'   => $d[0],
				'icd_code'         => $d[1],
				'category'         => $d[2],
				'common_treatment' => $d[3],
				'is_active'        => 1,
				'InActive'         => 0
			));
		}
	}

	/* ---------- MEDICATION MASTER (columns on existing medicine_drug_name) ---------- */

	private function ensure_medication_master_columns()
	{
		if (!$this->table_exists('medicine_drug_name')) return;

		// These may already exist from pharmacy V2
		if (!$this->column_exists('medicine_drug_name', 'generic_name')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `generic_name` varchar(255) DEFAULT NULL");
		}
		if (!$this->column_exists('medicine_drug_name', 'dosage_form')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `dosage_form` varchar(100) DEFAULT NULL");
		}
		if (!$this->column_exists('medicine_drug_name', 'strength')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `strength` varchar(100) DEFAULT NULL");
		}
		if (!$this->column_exists('medicine_drug_name', 'standard_dosage')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `standard_dosage` varchar(255) DEFAULT NULL");
		}
		if (!$this->column_exists('medicine_drug_name', 'notes')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `notes` text");
		}
	}

	/* ---------- SCAN MASTER ---------- */

	private function ensure_scan_master()
	{
		if (!$this->table_exists('scan_master')) {
			$this->db->query("CREATE TABLE `scan_master` (
				`id` int(11) NOT NULL AUTO_INCREMENT,
				`scan_name` varchar(255) NOT NULL,
				`category` varchar(100) DEFAULT NULL,
				`description` text,
				`department` varchar(100) DEFAULT NULL,
				`is_active` tinyint(1) NOT NULL DEFAULT 1,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_name` (`scan_name`(191)),
				KEY `idx_cat` (`category`),
				KEY `idx_active` (`is_active`, `InActive`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			// Seed default scans
			$scans = array(
				array('Abdominal Ultrasound', 'Ultrasound', 'Imaging', 'Radiology'),
				array('Pelvic Ultrasound', 'Ultrasound', 'Imaging', 'Radiology'),
				array('Obstetric Ultrasound', 'Ultrasound', 'Imaging', 'Radiology'),
				array('Breast Ultrasound', 'Ultrasound', 'Imaging', 'Radiology'),
				array('Thyroid Ultrasound', 'Ultrasound', 'Imaging', 'Radiology'),
				array('KUB Ultrasound', 'Ultrasound', 'Imaging', 'Radiology'),
				array('Carotid Doppler', 'Ultrasound', 'Imaging', 'Radiology'),
				array('Chest X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
				array('Abdominal X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
				array('Limb X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
				array('Skull X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
				array('Spine X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
				array('ECG (Electrocardiogram)', 'ECG', 'Cardiac', 'Cardiology'),
				array('CT Scan - Head', 'CT Scan', 'Imaging', 'Radiology'),
				array('CT Scan - Abdomen', 'CT Scan', 'Imaging', 'Radiology'),
				array('CT Scan - Chest', 'CT Scan', 'Imaging', 'Radiology'),
			);
			foreach ($scans as $s) {
				$this->db->insert('scan_master', array(
					'scan_name'   => $s[0],
					'category'    => $s[1],
					'description' => $s[2],
					'department'  => $s[3],
					'is_active'   => 1,
					'InActive'    => 0
				));
			}
		}
	}

	/* ---------- PATIENT ENCOUNTERS ---------- */

	private function ensure_patient_encounters()
	{
		if (!$this->table_exists('patient_encounters')) {
			$this->db->query("CREATE TABLE `patient_encounters` (
				`id` bigint(11) NOT NULL AUTO_INCREMENT,
				`patient_id` varchar(25) NOT NULL,
				`visit_id` varchar(25) NOT NULL,
				`complaints` text,
				`diagnosis` text,
				`medications` text,
				`lab_orders` text,
				`scans` text,
				`notes` text,
				`doctor_id` varchar(25) DEFAULT NULL,
				`encounter_type` varchar(10) DEFAULT 'OPD',
				`created_at` datetime NOT NULL,
				`updated_at` datetime DEFAULT NULL,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_patient` (`patient_id`),
				KEY `idx_visit` (`visit_id`),
				KEY `idx_doctor` (`doctor_id`),
				KEY `idx_date` (`created_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
	}

	/* ---------- LAB REQUEST ENHANCEMENTS ---------- */

	private function ensure_lab_request_columns()
	{
		if (!$this->table_exists('iop_laboratory')) return;

		if (!$this->column_exists('iop_laboratory', 'priority')) {
			$this->db->query("ALTER TABLE `iop_laboratory` ADD COLUMN `priority` varchar(20) DEFAULT 'ROUTINE'");
		}
		if (!$this->column_exists('iop_laboratory', 'requested_by')) {
			$this->db->query("ALTER TABLE `iop_laboratory` ADD COLUMN `requested_by` varchar(25) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_laboratory', 'status')) {
			$this->db->query("ALTER TABLE `iop_laboratory` ADD COLUMN `status` varchar(20) DEFAULT 'pending'");
		}
		if (!$this->column_exists('iop_laboratory', 'notes')) {
			$this->db->query("ALTER TABLE `iop_laboratory` ADD COLUMN `notes` text");
		}
	}

	/* ================================================================== */
	/*  DIAGNOSIS SEARCH (AJAX)                                            */
	/* ================================================================== */

	public function search_diagnoses($term, $limit = 15)
	{
		$term = trim((string)$term);
		if ($term === '') return array();
		$this->ensure_clinical_schema();
		// Query diagnosis table - use is_active if column exists, otherwise just InActive
		$hasIsActive = $this->column_exists('diagnosis', 'is_active');
		$sql = "SELECT diagnosis_id AS id, 
		               COALESCE(icd_code, '') AS code,
		               diagnosis_name AS description,
		               diagnosis_name AS label, 
		               diagnosis_name AS value,
		               COALESCE(category, '') AS category
		        FROM diagnosis
		        WHERE InActive = 0" . ($hasIsActive ? " AND is_active = 1" : "") . "
		          AND (diagnosis_name LIKE ? OR icd_code LIKE ?)
		        ORDER BY diagnosis_name ASC
		        LIMIT ?";
		$q = $this->db->query($sql, array('%'.$term.'%', '%'.$term.'%', (int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  MEDICATION SEARCH (AJAX)                                           */
	/* ================================================================== */

	public function search_medications($term, $limit = 15)
	{
		$term = trim((string)$term);
		if ($term === '') return array();
		$sql = "SELECT drug_id AS id, drug_name AS label, drug_name AS value,
		               generic_name, dosage_form, strength, standard_dosage
		        FROM medicine_drug_name
		        WHERE InActive = 0
		          AND (drug_name LIKE ? OR generic_name LIKE ?)
		        ORDER BY drug_name ASC
		        LIMIT ?";
		$q = $this->db->query($sql, array('%'.$term.'%', '%'.$term.'%', (int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  SCAN SEARCH (AJAX)                                                 */
	/* ================================================================== */

	public function search_scans($term, $limit = 15)
	{
		$term = trim((string)$term);
		if ($term === '') return array();
		$this->ensure_clinical_schema();
		$sql = "SELECT id, scan_name AS label, scan_name AS value, category, department
		        FROM scan_master
		        WHERE InActive = 0 AND is_active = 1
		          AND (scan_name LIKE ? OR category LIKE ?)
		        ORDER BY scan_name ASC
		        LIMIT ?";
		$q = $this->db->query($sql, array('%'.$term.'%', '%'.$term.'%', (int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  LAB TEST SEARCH (AJAX)                                             */
	/* ================================================================== */

	public function search_lab_tests($term, $limit = 15)
	{
		$term = trim((string)$term);
		if ($term === '') return array();
		$starts = $term . '%';
		$like = '%' . $term . '%';
		$sql = "SELECT bp.particular_id AS id, bp.particular_name AS label, bp.particular_name AS value,
				       bg.group_name AS category
			FROM bill_particular bp
			LEFT JOIN bill_group_name bg ON bg.group_id = bp.group_id
			WHERE bp.InActive = 0
			  AND (bp.particular_name LIKE ? OR bp.particular_name LIKE ?)
			ORDER BY
			  CASE WHEN bp.particular_name LIKE ? THEN 0 ELSE 1 END,
			  bp.particular_name ASC
			LIMIT ?";
		$q = $this->db->query($sql, array($starts, $like, $starts, (int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  PATIENT ENCOUNTERS CRUD                                            */
	/* ================================================================== */

	public function save_encounter($data)
	{
		$this->ensure_clinical_schema();
		$data['created_at'] = date('Y-m-d H:i:s');
		$data['InActive'] = 0;
		$this->db->insert('patient_encounters', $data);
		return $this->db->insert_id();
	}

	public function update_encounter($id, $data)
	{
		$data['updated_at'] = date('Y-m-d H:i:s');
		$this->db->where('id', (int)$id);
		$this->db->update('patient_encounters', $data);
	}

	public function get_encounter($visit_id)
	{
		$this->ensure_clinical_schema();
		$q = $this->db->get_where('patient_encounters', array('visit_id' => (string)$visit_id, 'InActive' => 0), 1);
		return $q ? $q->row() : null;
	}

	/* ================================================================== */
	/*  PATIENT CLINICAL HISTORY                                           */
	/* ================================================================== */

	public function get_patient_history($patient_no, $limit = 20)
	{
		$this->ensure_clinical_schema();

		// Pull from patient_encounters if available, else fall back to legacy tables
		$sql = "SELECT
					pe.id, pe.visit_id, pe.complaints, pe.diagnosis, pe.medications,
					pe.lab_orders, pe.scans, pe.notes, pe.created_at,
					pe.encounter_type,
					CONCAT(COALESCE(sp.cValue,''),' ',u.firstname,' ',u.lastname) AS doctor_name
				FROM patient_encounters pe
				LEFT JOIN users u ON u.user_id = pe.doctor_id
				LEFT JOIN system_parameters sp ON sp.param_id = u.title
				WHERE pe.patient_id = ? AND pe.InActive = 0
				ORDER BY pe.created_at DESC
				LIMIT ?";
		$q = $this->db->query($sql, array((string)$patient_no, (int)$limit));
		$encounters = $q ? $q->result() : array();

		// If no encounter records, build history from legacy tables
		if (empty($encounters)) {
			$encounters = $this->build_legacy_history($patient_no, $limit);
		}

		return $encounters;
	}

	private function build_legacy_history($patient_no, $limit = 20)
	{
		$sql = "SELECT
					v.IO_ID AS visit_id,
					v.date_visit AS created_at,
					v.patient_type AS encounter_type,
					CONCAT(COALESCE(sp.cValue,''),' ',u.firstname,' ',u.lastname) AS doctor_name,
					GROUP_CONCAT(DISTINCT COALESCE(d.diagnosis_name, id.diagnosis_text) SEPARATOR ', ') AS diagnosis,
					GROUP_CONCAT(DISTINCT m.drug_name SEPARATOR ', ') AS medications,
					GROUP_CONCAT(DISTINCT bp.particular_name SEPARATOR ', ') AS lab_orders,
					v.complaints
				FROM patient_details_iop v
				LEFT JOIN users u ON u.user_id = v.doctor_id
				LEFT JOIN system_parameters sp ON sp.param_id = u.title
				LEFT JOIN iop_diagnosis id ON id.iop_id = v.IO_ID AND id.InActive = 0
				LEFT JOIN diagnosis d ON d.diagnosis_id = id.diagnosis_id
				LEFT JOIN iop_medication im ON im.iop_id = v.IO_ID AND im.InActive = 0
				LEFT JOIN medicine_drug_name m ON m.drug_id = im.medicine_id
				LEFT JOIN iop_laboratory il ON il.iop_id = v.IO_ID AND il.InActive = 0
				LEFT JOIN bill_particular bp ON bp.particular_id = il.laboratory_id
				WHERE v.patient_no = ? AND v.InActive = 0
				GROUP BY v.IO_ID, v.date_visit, v.patient_type, doctor_name, v.complaints
				ORDER BY v.date_visit DESC
				LIMIT ?";
		$q = $this->db->query($sql, array((string)$patient_no, (int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  LAB DASHBOARD QUERIES (enhanced)                                   */
	/* ================================================================== */

	public function count_tests_today()
	{
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory
			WHERE DATE(dDate) = ? AND InActive = 0 AND (category_id IS NULL OR category_id != '18')",
			array(date('Y-m-d')));
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function count_pending_tests()
	{
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory
			WHERE (result = '' OR result IS NULL) AND InActive = 0 AND (category_id IS NULL OR category_id != '18')");
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function count_completed_tests_today()
	{
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory
			WHERE result != '' AND result IS NOT NULL AND DATE(dDate) = ? AND InActive = 0
			AND (category_id IS NULL OR category_id != '18')", array(date('Y-m-d')));
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function count_urgent_tests()
	{
		$hasPriority = $this->column_exists('iop_laboratory', 'priority');
		if (!$hasPriority) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_laboratory
			WHERE (result = '' OR result IS NULL) AND InActive = 0
			AND (category_id IS NULL OR category_id != '18')
			AND UPPER(priority) IN ('URGENT','STAT')");
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function get_lab_worklist($limit = 50)
	{
		$hasPriority = $this->column_exists('iop_laboratory', 'priority');
		$priorityCol = $hasPriority ? "l.priority" : "'ROUTINE' AS priority";

		$ssotCols = '';
		if ($this->table_exists('billing_transactions') && $this->column_exists('billing_transactions', 'item_ref') && $this->column_exists('billing_transactions', 'department')) {
			$hasPay = $this->column_exists('billing_transactions', 'payment_status');
			$hasPayer = $this->column_exists('billing_transactions', 'payer_type');
			$hasNet = $this->column_exists('billing_transactions', 'net_amount');
			$hasPaid = $this->column_exists('billing_transactions', 'paid_amount');
			$hasBal = $this->column_exists('billing_transactions', 'balance_amount');
			$ssotCols = ",\n\t\t\t\t       " .
				"(SELECT " . ($hasPay ? "bt.payment_status" : "NULL") . "\n" .
				"\t\t\t\t\tFROM billing_transactions bt\n" .
				"\t\t\t\t\tWHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bt.item_ref = CONCAT('io_lab_id:', l.io_lab_id)\n" .
				"\t\t\t\t\tLIMIT 1) AS ssot_payment_status,\n" .
				"\t\t\t\t       (SELECT " . ($hasPayer ? "bt.payer_type" : "NULL") . " FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bt.item_ref = CONCAT('io_lab_id:', l.io_lab_id) LIMIT 1) AS ssot_payer_type,\n" .
				"\t\t\t\t       (SELECT " . ($hasNet ? "bt.net_amount" : "NULL") . " FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bt.item_ref = CONCAT('io_lab_id:', l.io_lab_id) LIMIT 1) AS ssot_net_amount,\n" .
				"\t\t\t\t       (SELECT " . ($hasPaid ? "bt.paid_amount" : "NULL") . " FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bt.item_ref = CONCAT('io_lab_id:', l.io_lab_id) LIMIT 1) AS ssot_paid_amount,\n" .
				"\t\t\t\t       (SELECT " . ($hasBal ? "bt.balance_amount" : "NULL") . " FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bt.item_ref = CONCAT('io_lab_id:', l.io_lab_id) LIMIT 1) AS ssot_balance_amount";
		}

		$sql = "SELECT l.io_lab_id, l.iop_id, l.dDate,
				       COALESCE(bp.particular_name, l.laboratory_text, 'Unknown') AS test_name,
				       CONCAT(ppi.firstname,' ',ppi.lastname) AS patient_name,
				       ppi.patient_no,
				       CONCAT(COALESCE(sp.cValue,''),' ',COALESCE(doc.firstname,''),' ',COALESCE(doc.lastname,'')) AS doctor_name,
				       CASE
				         WHEN l.result != '' AND l.result IS NOT NULL THEN 'completed'
				         ELSE 'pending'
				       END AS status,
				       {$priorityCol}{$ssotCols}
			FROM iop_laboratory l
			JOIN patient_details_iop pd ON pd.IO_ID = l.iop_id AND pd.InActive = 0
			JOIN patient_personal_info ppi ON ppi.patient_no = pd.patient_no
			LEFT JOIN bill_particular bp ON bp.particular_id = l.laboratory_id
			LEFT JOIN users doc ON doc.user_id = l.doctor
			LEFT JOIN system_parameters sp ON sp.param_id = doc.title
			WHERE l.InActive = 0 AND (l.category_id IS NULL OR l.category_id != '18')
			  AND (l.result = '' OR l.result IS NULL)
			ORDER BY " . ($hasPriority ? "FIELD(UPPER(l.priority),'STAT','URGENT','ROUTINE') ASC, " : "") . "l.dDate DESC
			LIMIT ?";
		$q = $this->db->query($sql, array((int)$limit));
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  PHARMACY DASHBOARD QUERIES (enhanced)                              */
	/* ================================================================== */

	public function count_pharmacy_pending()
	{
		$hasStatus = $this->column_exists('iop_medication', 'dispensing_status');
		if ($hasStatus) {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE InActive = 0 AND dispensing_status = 'PENDING'");
		} else {
			$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE InActive = 0 AND DATE(dDate) = ?", array(date('Y-m-d')));
		}
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function count_pharmacy_reserved()
	{
		if (!$this->column_exists('iop_medication', 'dispensing_status')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE InActive = 0 AND dispensing_status = 'RESERVED'");
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function count_pharmacy_partial()
	{
		if (!$this->column_exists('iop_medication', 'dispensing_status')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE InActive = 0 AND dispensing_status = 'PARTIAL'");
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function count_pharmacy_dispensed_today()
	{
		if (!$this->column_exists('iop_medication', 'dispensing_status')) return 0;
		$q = $this->db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE InActive = 0 AND dispensing_status = 'DISPENSED' AND DATE(dDate) = ?", array(date('Y-m-d')));
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}
}
