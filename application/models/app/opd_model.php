<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Opd_model extends CI_Model
{

	public function __construct()
	{
		parent::__construct();
	}

	public function table_exists($table_name){
		$table_name = (string) $table_name;
		$q = $this->db->query("SHOW TABLES LIKE ".$this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	public function column_exists($table_name, $column_name){
		$table_name = (string) $table_name;
		$column_name = (string) $column_name;
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW COLUMNS FROM `".$table_name."` LIKE ".$this->db->escape($column_name));
		return ($q && $q->num_rows() > 0);
	}

	public function index_exists($table_name, $index_name){
		$table_name = (string) $table_name;
		$index_name = (string) $index_name;
		if (!$this->table_exists($table_name)) {
			return false;
		}
		$q = $this->db->query("SHOW INDEX FROM `".$table_name."` WHERE Key_name = ".$this->db->escape($index_name));
		return ($q && $q->num_rows() > 0);
	}

	public function ensure_opd_vitals_schema(){
		if ($this->table_exists('iop_vital_parameters') && !$this->column_exists('iop_vital_parameters', 'spo2')) {
			$this->db->query("ALTER TABLE `iop_vital_parameters` ADD COLUMN `spo2` varchar(10) DEFAULT NULL");
		}
		if ($this->table_exists('patient_details_iop') && !$this->column_exists('patient_details_iop', 'spo2')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `spo2` varchar(10) DEFAULT NULL");
		}
		if ($this->table_exists('patient_details_iop') && !$this->column_exists('patient_details_iop', 'insurance_cover_id')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `insurance_cover_id` int(11) DEFAULT NULL");
		}
		if ($this->table_exists('patient_details_iop') && !$this->column_exists('patient_details_iop', 'insurance_billing_type')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `insurance_billing_type` varchar(30) DEFAULT NULL");
		}
	}

	public function ensure_detention_schema()
	{
		if (!$this->table_exists('patient_details_iop')) {
			return false;
		}
		if (!$this->column_exists('patient_details_iop', 'detention_start_at')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `detention_start_at` datetime DEFAULT NULL");
		}
		if (!$this->column_exists('patient_details_iop', 'converted_to_admission_at')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `converted_to_admission_at` datetime DEFAULT NULL");
		}
		if (!$this->column_exists('patient_details_iop', 'converted_ipd_iop_id')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `converted_ipd_iop_id` varchar(15) DEFAULT NULL");
		}
		if (!$this->column_exists('patient_details_iop', 'source_opd_iop_id')) {
			$this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `source_opd_iop_id` varchar(15) DEFAULT NULL");
		}
		return true;
	}

	public function mark_detention_start($opd_iop_id, $patient_no)
	{
		$this->ensure_detention_schema();
		$opd_iop_id = trim((string)$opd_iop_id);
		$patient_no = trim((string)$patient_no);
		if ($opd_iop_id === '' || $patient_no === '') {
			return array('ok' => false, 'error' => 'Missing iop_id or patient_no');
		}

		$this->db->where(array('IO_ID' => $opd_iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
		$this->db->limit(1);
		$row = $this->db->get('patient_details_iop')->row();
		if (!$row) {
			return array('ok' => false, 'error' => 'OPD visit not found');
		}
		if (isset($row->patient_type) && strtoupper(trim((string)$row->patient_type)) !== 'OPD') {
			return array('ok' => false, 'error' => 'Only OPD visits can be detained');
		}
		if (!empty($row->converted_to_admission_at) && (string)$row->converted_to_admission_at !== '0000-00-00 00:00:00') {
			return array('ok' => false, 'error' => 'Already converted to IPD admission');
		}
		if (!empty($row->detention_start_at) && (string)$row->detention_start_at !== '0000-00-00 00:00:00') {
			return array('ok' => true, 'already_detained' => true, 'detention_start_at' => (string)$row->detention_start_at);
		}

		// Cannot detain if there is already an active IPD admission for this patient
		$this->db->where(array('patient_no' => $patient_no, 'patient_type' => 'IPD', 'nStatus' => 'Pending', 'InActive' => 0));
		$this->db->limit(1);
		$ipd = $this->db->get('patient_details_iop')->row();
		if ($ipd) {
			return array('ok' => false, 'error' => 'Patient already has an active IPD admission');
		}

		$now = date('Y-m-d H:i:s');
		$this->db->where(array('IO_ID' => $opd_iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
		$this->db->where('(detention_start_at IS NULL OR detention_start_at = "0000-00-00 00:00:00")', null, false);
		$this->db->update('patient_details_iop', array('detention_start_at' => $now));
		if ((int)$this->db->affected_rows() <= 0) {
			return array('ok' => true, 'already_detained' => true, 'detention_start_at' => $now);
		}

		// Queue detention billing as a one-time non-gating service using Smart Billing configuration
		$CI = get_instance();
		try {
			$CI->load->model('app/unified_billing_model', 'unified_billing');
			if (isset($CI->unified_billing) && method_exists($CI->unified_billing, 'queue_detention')) {
				$res = $CI->unified_billing->queue_detention($opd_iop_id, $patient_no, $now);
				if (!is_array($res) || (isset($res['success']) && !$res['success'])) {
					log_message('error', 'OPD_DETENTION_BILLING_FAIL iop_id=' . $opd_iop_id . ' patient_no=' . $patient_no . ' err=' . (is_array($res) && isset($res['error']) ? (string)$res['error'] : 'unknown'));
				}
			}
		} catch (Throwable $e) {
			log_message('error', 'OPD_DETENTION_BILLING_EXCEPTION iop_id=' . $opd_iop_id . ' patient_no=' . $patient_no . ' err=' . $e->getMessage());
		}
		return array('ok' => true, 'detention_start_at' => $now);
	}

	public function assign_detention_bed($opd_iop_id, $patient_no, $bed_id)
	{
		$this->ensure_detention_schema();
		$opd_iop_id = trim((string)$opd_iop_id);
		$patient_no = trim((string)$patient_no);
		$bed_id = (int)$bed_id;
		if ($opd_iop_id === '' || $patient_no === '' || $bed_id <= 0) {
			return array('ok' => false, 'error' => 'Missing iop_id, patient_no, or bed_id');
		}

		$this->load->model('app/bed_occupancy_model');
		$res = $this->bed_occupancy_model->assign_detention_bed($opd_iop_id, $patient_no, $bed_id);
		if (is_array($res) && isset($res['ok']) && $res['ok'] === true) {
			return array('ok' => true);
		}
		$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'unknown';
		$msg = ($err === 'bed_not_found')
			? 'Bed not found'
			: (($err === 'bed_occupied') ? 'Bed is already occupied' : (($err === 'visit_not_found') ? 'OPD visit not found' : (($err === 'not_opd_visit') ? 'Only OPD visits can be assigned a detention bed' : (($err === 'already_converted') ? 'Already converted to IPD admission' : 'Detention bed assignment failed'))));
		return array('ok' => false, 'error' => $msg);
	}

	public function ensure_medication_schema(){
		if (!$this->table_exists('iop_medication')) return;

		// ── Core clinical fields ──────────────────────────────────────────────
		if (!$this->column_exists('iop_medication', 'dosage')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `dosage` VARCHAR(100) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'medicine_text')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `medicine_text` VARCHAR(255) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'frequency')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `frequency` VARCHAR(50) DEFAULT NULL");
		}
		// Route of administration — GHS required field
		if (!$this->column_exists('iop_medication', 'route')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `route` VARCHAR(30) DEFAULT NULL");
		}
		// Drug form — GHS required field
		if (!$this->column_exists('iop_medication', 'drug_form')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `drug_form` VARCHAR(30) DEFAULT NULL");
		}

		// ── ICD-10 diagnosis linkage (NHIS requirement) ───────────────────────
		if (!$this->column_exists('iop_medication', 'diagnosis_code')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `diagnosis_code` VARCHAR(10) DEFAULT NULL");
		}
		if (!$this->column_exists('iop_medication', 'diagnosis_description')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `diagnosis_description` VARCHAR(255) DEFAULT NULL");
		}

		// ── Audit trail fields ────────────────────────────────────────────────
		// prescribed_by: canonical doctor linkage (replaces legacy cPreparedBy)
		if (!$this->column_exists('iop_medication', 'prescribed_by')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `prescribed_by` VARCHAR(25) DEFAULT NULL");
		}

		// ── Status fields ─────────────────────────────────────────────────────
		if (!$this->column_exists('iop_medication', 'dispensing_status')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `dispensing_status` VARCHAR(20) NOT NULL DEFAULT 'PENDING'");
		}
		if (!$this->column_exists('iop_medication', 'payment_status')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `payment_status` VARCHAR(20) NOT NULL DEFAULT 'PENDING'");
		}

		// ── Performance indexes ───────────────────────────────────────────────
		// iop_id: every patient medication list load does a full scan without this
		if (!$this->index_exists('iop_medication', 'idx_med_iop_id')) {
			$this->db->query("CREATE INDEX `idx_med_iop_id` ON `iop_medication` (`iop_id`)");
		}
		if (!$this->index_exists('iop_medication', 'idx_med_prescribed_by')) {
			$this->db->query("CREATE INDEX `idx_med_prescribed_by` ON `iop_medication` (`prescribed_by`)");
		}
		if (!$this->index_exists('iop_medication', 'idx_med_disp_status')) {
			$this->db->query("CREATE INDEX `idx_med_disp_status` ON `iop_medication` (`dispensing_status`)");
		}
		if (!$this->index_exists('iop_medication', 'idx_med_inactive')) {
			$this->db->query("CREATE INDEX `idx_med_inactive` ON `iop_medication` (`InActive`)");
		}
	}

	/**
	 * Phase 1: Complaints Module Database Hardening
	 * GHS / NHIS Compliance Preparation
	 * Safe, additive-only schema changes — backward compatible
	 */
	public function ensure_complaints_schema()
	{
		// ── 1. complain MASTER TABLE ─────────────────────────────────────────
		// Add category column for GHS body-system grouping
		if ($this->table_exists('complain') && !$this->column_exists('complain', 'category')) {
			$this->db->query("ALTER TABLE `complain` ADD COLUMN `category` VARCHAR(50) NOT NULL DEFAULT 'GENERAL' AFTER `complain_name`");
		}
		// Add sort_order for frequency-based ordering within categories
		if ($this->table_exists('complain') && !$this->column_exists('complain', 'sort_order')) {
			$this->db->query("ALTER TABLE `complain` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 99 AFTER `category`");
		}
		// Add is_common flag for quick-select panel (top complaints)
		if ($this->table_exists('complain') && !$this->column_exists('complain', 'is_common')) {
			$this->db->query("ALTER TABLE `complain` ADD COLUMN `is_common` TINYINT(1) NOT NULL DEFAULT 0 AFTER `sort_order`");
		}
		// Add composite index for category + InActive lookups
		if ($this->table_exists('complain') && !$this->index_exists('complain', 'idx_category')) {
			$this->db->query("CREATE INDEX `idx_category` ON `complain` (`category`, `InActive`)");
		}

		// ── 2. iop_complaints PATIENT RECORDS ────────────────────────────────
		// complain_text: free-text complaint (was missing from install SQL)
		if ($this->table_exists('iop_complaints') && !$this->column_exists('iop_complaints', 'complain_text')) {
			$this->db->query("ALTER TABLE `iop_complaints` ADD COLUMN `complain_text` TEXT DEFAULT NULL AFTER `complain_id`");
		}
		// severity: Mild / Moderate / Severe — NHIS clinical documentation
		if ($this->table_exists('iop_complaints') && !$this->column_exists('iop_complaints', 'severity')) {
			$this->db->query("ALTER TABLE `iop_complaints` ADD COLUMN `severity` VARCHAR(20) DEFAULT NULL AFTER `complain_text`");
		}
		// duration: e.g. "3 days", "2 weeks" — GHS standard
		if ($this->table_exists('iop_complaints') && !$this->column_exists('iop_complaints', 'duration')) {
			$this->db->query("ALTER TABLE `iop_complaints` ADD COLUMN `duration` VARCHAR(50) DEFAULT NULL AFTER `severity`");
		}
		// onset: Acute / Chronic / Recurrent — GHS standard
		if ($this->table_exists('iop_complaints') && !$this->column_exists('iop_complaints', 'onset')) {
			$this->db->query("ALTER TABLE `iop_complaints` ADD COLUMN `onset` VARCHAR(20) DEFAULT NULL AFTER `duration`");
		}
		// recorded_by: doctor/user who entered complaint — NHIS audit requirement
		if ($this->table_exists('iop_complaints') && !$this->column_exists('iop_complaints', 'recorded_by')) {
			$this->db->query("ALTER TABLE `iop_complaints` ADD COLUMN `recorded_by` VARCHAR(25) DEFAULT NULL AFTER `onset`");
		}
		// Upgrade dDate from DATE to DATETIME for full timestamp audit trail
		// Only modify if it is still a DATE type (not already DATETIME)
		if ($this->table_exists('iop_complaints')) {
			$col_q = $this->db->query("SELECT DATA_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iop_complaints' AND COLUMN_NAME = 'dDate'");
			if ($col_q && $col_q->num_rows() > 0 && strtolower($col_q->row()->DATA_TYPE) === 'date') {
				$this->db->query("ALTER TABLE `iop_complaints` MODIFY COLUMN `dDate` DATETIME DEFAULT NULL");
			}
		}

		// ── 3. PERFORMANCE INDEXES on iop_complaints ─────────────────────────
		if ($this->table_exists('iop_complaints') && !$this->index_exists('iop_complaints', 'idx_iop_id')) {
			$this->db->query("CREATE INDEX `idx_iop_id` ON `iop_complaints` (`iop_id`)");
		}
		if ($this->table_exists('iop_complaints') && !$this->index_exists('iop_complaints', 'idx_recorded_by')) {
			$this->db->query("CREATE INDEX `idx_recorded_by` ON `iop_complaints` (`recorded_by`)");
		}

		// ── 4. complain_category TABLE ────────────────────────────────────────
		if (!$this->table_exists('complain_category')) {
			$this->db->query("
				CREATE TABLE `complain_category` (
					`cat_id`     INT(11)      NOT NULL AUTO_INCREMENT,
					`cat_code`   VARCHAR(20)  NOT NULL,
					`cat_name`   VARCHAR(100) NOT NULL,
					`sort_order` INT(3)       NOT NULL DEFAULT 99,
					PRIMARY KEY (`cat_id`),
					UNIQUE KEY `uq_code` (`cat_code`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		}

		// ── 5. SEED GHS STANDARD CATEGORIES (INSERT IGNORE — safe to re-run) ─
		if ($this->table_exists('complain_category')) {
			$categories = array(
				array('GENERAL',     'General / Systemic',      1),
				array('RESPIRATORY', 'Respiratory',             2),
				array('GI',          'Gastrointestinal',        3),
				array('NEURO',       'Neurological',            4),
				array('MATERNAL',    'Maternal / ANC',          5),
				array('PAEDIATRIC',  'Paediatric',              6),
				array('CHRONIC',     'Chronic / NCD Follow-Up', 7),
				array('ENT',         'ENT / Eye',               8),
				array('MSK',         'Musculoskeletal',         9),
				array('OTHER',       'Other',                  10),
			);
			foreach ($categories as $cat) {
				$this->db->query(
					"INSERT IGNORE INTO `complain_category` (`cat_code`, `cat_name`, `sort_order`) VALUES (?, ?, ?)",
					$cat
				);
			}
		}

		// ── 6. TAG EXISTING 46 COMPLAINTS WITH GHS CATEGORIES ────────────────
		// Only update rows where category is still the default 'GENERAL'
		// to avoid overwriting any manually set values on future runs
		if ($this->table_exists('complain') && $this->column_exists('complain', 'category')) {
			$mappings = array(
				// GENERAL / SYSTEMIC
				'GENERAL' => array(
					'%FEVER%', '%WEAKNESS%', '%FATIGUE%', '%MALAISE%',
					'%WEIGHT LOSS%', '%WEIGHT GAIN%', '%EDEMA%', '%FACIAL FLUSHING%',
					'%HYPOTENSION%', '%SHOCK%', '%SYNCOPE%', '%LYMPHADENOPATHY%',
					'%PRURITUS%', '%RASH%',
				),
				// RESPIRATORY
				'RESPIRATORY' => array(
					'%COUGH%', '%DYSPNEA%', '%TACHYPNEA%',
				),
				// GASTROINTESTINAL
				'GI' => array(
					'%ABDOMINAL%', '%NAUSEA%', '%VOMITING%', '%DIARRHEA%',
					'%DYSPHAGIA%', '%FLANK PAIN%',
				),
				// NEUROLOGICAL
				'NEURO' => array(
					'%HEADACHE%', '%SEIZURE%', '%DELIRIUM%', '%DEMENTIA%',
					'%MEMORY LOSS%', '%MENTAL STATUS%', '%NUMBNESS%',
					'%SENSORY LOSS%', '%TREMOR%', '%VERTIGO%', '%TINNITUS%',
					'%DIZZINESS%',
				),
				// MATERNAL / ANC
				'MATERNAL' => array(
					'%UTERINE BLEEDING%', '%ABNORMAL UTERINE%',
					'%GENITAL SKIN%', '%GENITAL ULCER%', '%SCROTAL%',
				),
				// PAEDIATRIC
				'PAEDIATRIC' => array(
					'%INFANT%', '%NEWBORN%', '%CRYING INFANT%', '%LIMP IN CHILD%',
				),
				// CHRONIC / NCD
				'CHRONIC' => array(
					'%SINUS TACHYCARDIA%',
				),
				// ENT / EYE
				'ENT' => array(
					'%EAR PAIN%', '%OTALGIA%', '%HEARING LOSS%', '%DEAFNESS%',
					'%FACIAL PAIN%', '%RED EYE%', '%TINNITUS%',
				),
				// MUSCULOSKELETAL
				'MSK' => array(
					'%BACK PAIN%', '%CHEST PAIN%', '%SHOULDER PAIN%',
					'%LEG PAIN%', '%BONE PAIN%', '%EXTREMITY PAIN%',
					'%MUSCLE CRAMPS%', '%MYALGIAS%', '%ARTHRALGIAS%',
					'%ANXIETY%', '%DEPRESSION%',
				),
				// URINARY (filed as OTHER until dedicated category added)
				'OTHER' => array(
					'%URINARY%', '%DYSURIA%', '%HEMATURIA%',
				),
			);

			foreach ($mappings as $category => $patterns) {
				foreach ($patterns as $pattern) {
					$this->db->query(
						"UPDATE `complain` SET `category` = ? WHERE `complain_name` LIKE ? AND `category` = 'GENERAL'",
						array($category, $pattern)
					);
				}
			}

			// ── 7. MARK is_common FOR TOP GHANAIAN OPD COMPLAINTS ─────────────
			// Reset first, then set — ensures idempotent runs
			$this->db->query("UPDATE `complain` SET `is_common` = 0");
			$common_patterns = array(
				'%FEVER%', '%HEADACHE%', '%COUGH%', '%ABDOMINAL%',
				'%DIARRHEA%', '%NAUSEA%', '%VOMITING%', '%WEAKNESS%',
				'%BACK PAIN%', '%CHEST PAIN%',
				'%MALARIA%', '%ANAEMIA%', '%HYPERTENSION%', '%DIABETES%',
				'%TYPHOID%', '%BODY PAIN%',
			);
			foreach ($common_patterns as $pattern) {
				$this->db->query(
					"UPDATE `complain` SET `is_common` = 1 WHERE `complain_name` LIKE ? AND `InActive` = 0",
					array($pattern)
				);
			}
		}

		// ── 8. GHS / NHIS GHANA-SPECIFIC COMPLAINT SEEDS ─────────────────────
		// INSERT IGNORE on complain_name — fully idempotent, safe to re-run
		// No hardcoded IDs — AUTO_INCREMENT assigned by DB
		if ($this->table_exists('complain')) {
			$has_cat    = $this->column_exists('complain', 'category');
			$has_sort   = $this->column_exists('complain', 'sort_order');
			$has_common = $this->column_exists('complain', 'is_common');

			// Build column list dynamically based on what exists
			$col_base = '`complain_name`, `complain_desc`, `InActive`';
			$col_ext  = '';
			if ($has_cat)    { $col_ext .= ', `category`'; }
			if ($has_sort)   { $col_ext .= ', `sort_order`'; }
			if ($has_common) { $col_ext .= ', `is_common`'; }

			// Seeds: [complain_name, complain_desc, InActive, category, sort_order, is_common]
			$seeds = array(
				// ── GENERAL / SYSTEMIC ──────────────────────────────────────────
				array('MALARIA SYMPTOMS',           'Malaria symptoms (fever, chills, rigors)',                   0, 'GENERAL',     1, 1),
				array('MALARIA (CONFIRMED)',         'Confirmed malaria diagnosis',                                0, 'GENERAL',     2, 1),
				array('TYPHOID FEVER',              'Typhoid fever / enteric fever',                              0, 'GENERAL',     3, 1),
				array('ANAEMIA',                    'Anaemia (pallor, fatigue, breathlessness)',                  0, 'GENERAL',     4, 1),
				array('BODY PAINS (GENERALISED)',   'Generalised body pains / aches',                             0, 'GENERAL',     5, 1),
				array('NIGHT SWEATS',               'Night sweats',                                               0, 'GENERAL',    10, 0),
				array('EXCESSIVE SWEATING',         'Excessive sweating / diaphoresis',                          0, 'GENERAL',    11, 0),
				array('PALLOR',                     'Pallor (skin, conjunctiva)',                                 0, 'GENERAL',    12, 0),
				array('JAUNDICE',                   'Jaundice / yellowish discolouration of skin/eyes',          0, 'GENERAL',    13, 0),
				array('SWELLING (GENERALISED)',     'Generalised swelling / anasarca',                            0, 'GENERAL',    14, 0),
				array('LOSS OF APPETITE',           'Loss of appetite / anorexia',                                0, 'GENERAL',    15, 0),
				array('WEIGHT GAIN',                'Unexplained weight gain',                                    0, 'GENERAL',    16, 0),
				array('DEHYDRATION',                'Dehydration',                                                0, 'GENERAL',    17, 0),
				// ── RESPIRATORY ─────────────────────────────────────────────────
				array('CATARRH / RHINORRHOEA',      'Catarrh / nasal discharge / runny nose',                    0, 'RESPIRATORY',  1, 1),
				array('NASAL CONGESTION',           'Nasal congestion / blocked nose',                            0, 'RESPIRATORY',  2, 0),
				array('SORE THROAT',                'Sore throat / pharyngitis',                                  0, 'RESPIRATORY',  3, 1),
				array('UPPER RESPIRATORY INFECTION','Upper respiratory tract infection (URTI) symptoms',          0, 'RESPIRATORY',  4, 1),
				array('SHORTNESS OF BREATH',        'Shortness of breath / breathlessness',                       0, 'RESPIRATORY',  5, 1),
				array('WHEEZING',                   'Wheezing / bronchospasm',                                    0, 'RESPIRATORY',  6, 0),
				array('HAEMOPTYSIS',                'Coughing up blood / haemoptysis',                            0, 'RESPIRATORY',  7, 0),
				array('SNEEZING',                   'Frequent sneezing',                                          0, 'RESPIRATORY',  8, 0),
				// ── GASTROINTESTINAL ────────────────────────────────────────────
				array('VOMITING',                   'Vomiting',                                                   0, 'GI',           1, 1),
				array('CONSTIPATION',               'Constipation',                                               0, 'GI',           2, 0),
				array('BLOATING / FLATULENCE',      'Abdominal bloating / flatulence / gas',                     0, 'GI',           3, 0),
				array('HEARTBURN / EPIGASTRIC PAIN','Heartburn / epigastric pain / acid reflux',                  0, 'GI',           4, 0),
				array('BLOODY STOOL',               'Blood in stool / haematochezia / melaena',                  0, 'GI',           5, 0),
				array('GASTROENTERITIS',            'Gastroenteritis symptoms (vomiting + diarrhoea)',            0, 'GI',           6, 1),
				array('PEPTIC ULCER SYMPTOMS',      'Peptic ulcer disease symptoms',                              0, 'GI',           7, 0),
				// ── NEUROLOGICAL ────────────────────────────────────────────────
				array('DIZZINESS / VERTIGO',        'Dizziness or vertigo',                                       0, 'NEURO',        1, 0),
				array('FAINTING / LOSS OF CONSCIOUSNESS', 'Fainting / syncope / loss of consciousness',          0, 'NEURO',        2, 0),
				array('STROKE SYMPTOMS',            'Stroke / CVA symptoms (face drooping, arm weakness, speech)',0, 'NEURO',        3, 0),
				// ── MATERNAL / ANC ───────────────────────────────────────────────
				array('ANTENATAL VISIT (ANC)',      'Routine antenatal care visit',                               0, 'MATERNAL',     1, 1),
				array('PREGNANCY COMPLICATION',     'Pregnancy-related complication',                             0, 'MATERNAL',     2, 0),
				array('LABOUR PAINS',               'Labour pains / contractions',                                0, 'MATERNAL',     3, 0),
				array('VAGINAL DISCHARGE',          'Abnormal vaginal discharge',                                 0, 'MATERNAL',     4, 0),
				array('MISSED PERIOD',              'Missed period / amenorrhoea',                                0, 'MATERNAL',     5, 0),
				array('BREAST LUMP / PAIN',         'Breast lump or breast pain',                                 0, 'MATERNAL',     6, 0),
				array('POSTPARTUM COMPLAINT',       'Postpartum complaint / postnatal visit',                     0, 'MATERNAL',     7, 0),
				array('FAMILY PLANNING VISIT',      'Family planning consultation',                               0, 'MATERNAL',     8, 0),
				// ── PAEDIATRIC ───────────────────────────────────────────────────
				array('CHILD WELFARE CLINIC (CWC)', 'Child welfare clinic / growth monitoring visit',             0, 'PAEDIATRIC',   1, 1),
				array('CHILDHOOD FEVER',            'Fever in a child under 5',                                   0, 'PAEDIATRIC',   2, 1),
				array('CHILDHOOD DIARRHOEA',        'Diarrhoea in a child (possible dehydration)',                0, 'PAEDIATRIC',   3, 1),
				array('CHILDHOOD MALNUTRITION',     'Malnutrition / failure to thrive',                           0, 'PAEDIATRIC',   4, 0),
				array('CONVULSION IN CHILD',        'Convulsion / febrile seizure in a child',                    0, 'PAEDIATRIC',   5, 0),
				array('EAR DISCHARGE (CHILD)',      'Ear discharge in a child / otitis media',                    0, 'PAEDIATRIC',   6, 0),
				array('IMMUNISATION VISIT',         'Immunisation / vaccination visit',                           0, 'PAEDIATRIC',   7, 0),
				// ── CHRONIC / NCD ────────────────────────────────────────────────
				array('HYPERTENSION FOLLOW-UP',     'Hypertension / high blood pressure follow-up',               0, 'CHRONIC',      1, 1),
				array('DIABETES FOLLOW-UP',         'Diabetes mellitus follow-up / blood sugar review',           0, 'CHRONIC',      2, 1),
				array('ASTHMA REVIEW',              'Asthma review / inhaler management',                         0, 'CHRONIC',      3, 0),
				array('SICKLE CELL REVIEW',         'Sickle cell disease review / crisis',                        0, 'CHRONIC',      4, 0),
				array('EPILEPSY REVIEW',            'Epilepsy / seizure disorder review',                         0, 'CHRONIC',      5, 0),
				array('HIV / ART REVIEW',           'HIV positive / ART medication review',                       0, 'CHRONIC',      6, 0),
				array('TUBERCULOSIS (TB) REVIEW',   'Tuberculosis treatment review / cough + weight loss',        0, 'CHRONIC',      7, 0),
				array('CHRONIC KIDNEY DISEASE',     'Chronic kidney disease / renal failure review',              0, 'CHRONIC',      8, 0),
				array('HEART FAILURE REVIEW',       'Heart failure / cardiac review',                             0, 'CHRONIC',      9, 0),
				// ── ENT / EYE ───────────────────────────────────────────────────
				array('EYE PAIN / IRRITATION',      'Eye pain, irritation or discharge',                          0, 'ENT',          1, 0),
				array('BLURRED VISION',             'Blurred or reduced vision',                                  0, 'ENT',          2, 0),
				array('NASAL BLEEDING (EPISTAXIS)', 'Nasal bleeding / epistaxis',                                 0, 'ENT',          3, 0),
				array('TOOTHACHE / DENTAL PAIN',    'Toothache or dental/jaw pain',                               0, 'ENT',          4, 0),
				// ── MUSCULOSKELETAL ──────────────────────────────────────────────
				array('JOINT SWELLING',             'Swollen joint(s) / arthritis',                              0, 'MSK',          1, 0),
				array('NECK PAIN',                  'Neck pain / cervical pain',                                  0, 'MSK',          2, 0),
				array('KNEE PAIN',                  'Knee pain',                                                  0, 'MSK',          3, 0),
				// ── SKIN ─────────────────────────────────────────────────────────
				array('SKIN RASH / LESION',         'Skin rash, lesion or discolouration',                        0, 'GENERAL',      6, 0),
				array('WOUND / LACERATION',         'Wound, cut or laceration requiring attention',               0, 'GENERAL',      7, 0),
				array('ABSCESS / BOIL',             'Abscess, boil or skin infection',                            0, 'GENERAL',      8, 0),
				array('SCABIES / SKIN INFESTATION', 'Scabies or other skin infestation',                          0, 'GENERAL',      9, 0),
				// ── OTHER ────────────────────────────────────────────────────────
				array('URINARY TRACT INFECTION',    'Urinary tract infection (UTI) symptoms',                     0, 'OTHER',        1, 0),
				array('PAINFUL URINATION',          'Painful urination / dysuria',                                0, 'OTHER',        2, 0),
				array('BLOOD IN URINE',             'Blood in urine / haematuria',                                0, 'OTHER',        3, 0),
				array('GENERAL REVIEW / FOLLOW-UP', 'General medical review or follow-up visit',                  0, 'OTHER',        4, 0),
				array('REFERRAL / SECOND OPINION',  'Patient referred for second opinion or specialist review',   0, 'OTHER',        5, 0),
			);

			foreach ($seeds as $row) {
				list($name, $desc, $inactive, $category, $sort, $common) = $row;

				// Check existence by name first — avoid duplicate even on re-run
				$exists_q = $this->db->query(
					"SELECT complain_id FROM `complain` WHERE UPPER(`complain_name`) = ? LIMIT 1",
					array(strtoupper($name))
				);
				if ($exists_q && $exists_q->num_rows() > 0) {
					continue; // Already present — skip
				}

				// Build values list to match column list
				$vals = "'" . $this->db->escape_str($name) . "', '"
					. $this->db->escape_str($desc) . "', " . (int)$inactive;
				if ($has_cat)    { $vals .= ", '" . $this->db->escape_str($category) . "'"; }
				if ($has_sort)   { $vals .= ", " . (int)$sort; }
				if ($has_common) { $vals .= ", " . (int)$common; }

				$this->db->query(
					"INSERT INTO `complain` ({$col_base}{$col_ext}) VALUES ({$vals})"
				);
			}
		}

		// ── 9. COMPLAINT USAGE TRACKING TABLE ────────────────────────────────
		// Tracks per-doctor complaint frequency to power "My Top Complaints" panel
		if (!$this->table_exists('complaint_usage')) {
			$this->db->query("
				CREATE TABLE `complaint_usage` (
					`id`          INT(11)      NOT NULL AUTO_INCREMENT,
					`doctor_id`   VARCHAR(25)  NOT NULL,
					`complain_id` VARCHAR(25)  NOT NULL,
					`complain_name` VARCHAR(255) NOT NULL DEFAULT '',
					`usage_count` INT(11)      NOT NULL DEFAULT 1,
					`last_used`   DATETIME     NOT NULL,
					PRIMARY KEY (`id`),
					UNIQUE KEY `uq_doctor_complaint` (`doctor_id`, `complain_id`),
					KEY `idx_usage_doctor` (`doctor_id`, `usage_count`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8
			");
		}
	}

	/**
	 * Increment complaint usage count for a doctor.
	 * Uses ON DUPLICATE KEY UPDATE for atomic upsert — no race condition.
	 */
	public function increment_complaint_usage($doctor_id, $complain_id, $complain_name)
	{
		$doctor_id    = (string)$doctor_id;
		$complain_id  = (string)$complain_id;
		$complain_name = (string)$complain_name;
		$now = date('Y-m-d H:i:s');

		$this->db->query(
			"INSERT INTO `complaint_usage`
				(`doctor_id`, `complain_id`, `complain_name`, `usage_count`, `last_used`)
			VALUES (?, ?, ?, 1, ?)
			ON DUPLICATE KEY UPDATE
				`usage_count`   = `usage_count` + 1,
				`complain_name` = VALUES(`complain_name`),
				`last_used`     = VALUES(`last_used`)",
			array($doctor_id, $complain_id, $complain_name, $now)
		);
	}

	/**
	 * Return top N complaints for a doctor, ordered by usage_count DESC.
	 * Returns array of objects: complain_id, complain_name, usage_count.
	 */
	public function getDoctorTopComplaints($doctor_id, $limit = 8)
	{
		$doctor_id = (string)$doctor_id;
		$limit     = max(1, min(20, (int)$limit));
		$q = $this->db->query(
			"SELECT `complain_id`, `complain_name`, `usage_count`
			FROM `complaint_usage`
			WHERE `doctor_id` = ?
			ORDER BY `usage_count` DESC, `last_used` DESC
			LIMIT {$limit}",
			array($doctor_id)
		);
		return ($q && $q->num_rows() > 0) ? $q->result() : array();
	}

	/**
	 * Ensure diagnosis columns exist on iop_medication table
	 * Phase 2: Clinical Safety - Diagnosis Code Integration
	 * Phase 2 Hardening: Task 5 - Multi-Diagnosis Support
	 */
	public function ensure_diagnosis_schema(){
		// Ensure diagnosis table has is_active column (needed by clinical workflow)
		if ($this->table_exists('diagnosis') && !$this->column_exists('diagnosis', 'is_active')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1");
		}
		
		if (!$this->table_exists('iop_medication')) {
			return;
		}
		
		// Add diagnosis_code column
		if (!$this->column_exists('iop_medication', 'diagnosis_code')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `diagnosis_code` VARCHAR(20) DEFAULT NULL AFTER `instruction`");
		}
		
		// Add diagnosis_description column (kept for backward compatibility - Task 4)
		if (!$this->column_exists('iop_medication', 'diagnosis_description')) {
			$this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `diagnosis_description` VARCHAR(255) DEFAULT NULL AFTER `diagnosis_code`");
		}
		
		// Add index for diagnosis lookups (MySQL 5.x compatible)
		if (!$this->index_exists('iop_medication', 'idx_diagnosis_code')) {
			$this->db->query("CREATE INDEX `idx_diagnosis_code` ON `iop_medication` (`diagnosis_code`)");
		}
		
		// Task 6: Multi-diagnosis support table
		$this->ensure_multi_diagnosis_schema();
	}
	
	/**
	 * Phase 2 Hardening: Task 6 - Multi-Diagnosis Support
	 * Create iop_medication_diagnosis table for multiple diagnoses per prescription
	 */
	public function ensure_multi_diagnosis_schema()
	{
		if (!$this->table_exists('iop_medication_diagnosis')) {
			$this->db->query("
				CREATE TABLE `iop_medication_diagnosis` (
					`id` INT AUTO_INCREMENT PRIMARY KEY,
					`iop_med_id` INT NOT NULL,
					`diagnosis_code` VARCHAR(20) NOT NULL,
					`diagnosis_type` ENUM('PRIMARY','SECONDARY') DEFAULT 'PRIMARY',
					`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
					`created_by` VARCHAR(25) DEFAULT NULL,
					KEY `idx_iop_med` (`iop_med_id`),
					KEY `idx_code` (`diagnosis_code`),
					KEY `idx_type` (`diagnosis_type`)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
			
			// Migrate existing diagnosis_code to new table
			$this->migrate_existing_diagnoses();
		}
	}
	
	/**
	 * Migrate existing diagnosis_code from iop_medication to new table
	 */
	private function migrate_existing_diagnoses()
	{
		if (!$this->table_exists('iop_medication') || !$this->table_exists('iop_medication_diagnosis')) {
			return;
		}
		
		// One-time migration of existing diagnosis_code
		$this->db->query("
			INSERT INTO iop_medication_diagnosis (iop_med_id, diagnosis_code, diagnosis_type, created_at)
			SELECT iop_med_id, diagnosis_code, 'PRIMARY', NOW()
			FROM iop_medication
			WHERE diagnosis_code IS NOT NULL AND diagnosis_code != ''
			AND NOT EXISTS (
				SELECT 1 FROM iop_medication_diagnosis 
				WHERE iop_medication_diagnosis.iop_med_id = iop_medication.iop_med_id
			)
		");
	}

	/**
	 * Validate diagnosis code exists in ICD-10 table
	 */
	public function validate_diagnosis_code($code)
	{
		if (empty($code)) {
			return false;
		}
		
		$code = trim(strtoupper($code));
		
		// Check in icd10_codes table
		if ($this->table_exists('icd10_codes')) {
			$this->db->where('code', $code);
			$this->db->where('is_active', 1);
			$q = $this->db->get('icd10_codes');
			if ($q && $q->num_rows() > 0) {
				return true;
			}
		}
		
		// Also check legacy diagnosis table
		if ($this->table_exists('diagnosis')) {
			$this->db->where('diagnosis_name', $code);
			$this->db->where('InActive', 0);
			$q = $this->db->get('diagnosis');
			if ($q && $q->num_rows() > 0) {
				return true;
			}
		}
		
		return false;
	}
	
	/**
	 * Get diagnosis description from ICD-10 table (Single Source of Truth)
	 * Phase 2 Hardening: Task 4
	 */
	public function get_diagnosis_description($code)
	{
		if (empty($code)) {
			return '';
		}
		
		$code = trim(strtoupper($code));
		
		// Primary source: icd10_codes table
		if ($this->table_exists('icd10_codes')) {
			$q = $this->db->get_where('icd10_codes', array('code' => $code));
			if ($q && $q->num_rows() > 0) {
				return $q->row()->description;
			}
		}
		
		// Fallback: legacy diagnosis table
		if ($this->table_exists('diagnosis')) {
			$q = $this->db->get_where('diagnosis', array('diagnosis_name' => $code, 'InActive' => 0));
			if ($q && $q->num_rows() > 0) {
				return $q->row()->diagnosis_name;
			}
		}
		
		// Last resort: return the code itself
		return $code;
	}
	
	/**
	 * Add diagnosis to medication (supports multi-diagnosis)
	 * Phase 2 Hardening: Task 6
	 */
	public function add_medication_diagnosis($iop_med_id, $diagnosis_code, $diagnosis_type = 'PRIMARY', $user_id = null)
	{
		if (empty($iop_med_id) || empty($diagnosis_code)) {
			return false;
		}
		
		// Validate diagnosis code
		if (!$this->validate_diagnosis_code($diagnosis_code)) {
			return false;
		}
		
		// Check if already exists
		$exists = $this->db->get_where('iop_medication_diagnosis', array(
			'iop_med_id' => $iop_med_id,
			'diagnosis_code' => $diagnosis_code
		))->num_rows();
		
		if ($exists > 0) {
			return true; // Already exists
		}
		
		// If adding PRIMARY, demote existing PRIMARY to SECONDARY
		if ($diagnosis_type === 'PRIMARY') {
			$this->db->where('iop_med_id', $iop_med_id);
			$this->db->where('diagnosis_type', 'PRIMARY');
			$this->db->update('iop_medication_diagnosis', array('diagnosis_type' => 'SECONDARY'));
		}
		
		return $this->db->insert('iop_medication_diagnosis', array(
			'iop_med_id' => $iop_med_id,
			'diagnosis_code' => trim(strtoupper($diagnosis_code)),
			'diagnosis_type' => $diagnosis_type,
			'created_at' => date('Y-m-d H:i:s'),
			'created_by' => $user_id
		));
	}
	
	/**
	 * Get all diagnoses for a medication
	 * Phase 2 Hardening: Task 6
	 */
	public function get_medication_diagnoses($iop_med_id)
	{
		if (!$this->table_exists('iop_medication_diagnosis')) {
			// Fallback to single diagnosis from iop_medication
			$med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id))->row();
			if ($med && !empty($med->diagnosis_code)) {
				return array((object) array(
					'diagnosis_code' => $med->diagnosis_code,
					'diagnosis_type' => 'PRIMARY',
					'description' => $this->get_diagnosis_description($med->diagnosis_code)
				));
			}
			return array();
		}
		
		$this->db->select('md.*, COALESCE(i.description, md.diagnosis_code) as description');
		$this->db->from('iop_medication_diagnosis md');
		$this->db->join('icd10_codes i', 'i.code = md.diagnosis_code', 'left');
		$this->db->where('md.iop_med_id', $iop_med_id);
		$this->db->order_by("FIELD(md.diagnosis_type, 'PRIMARY', 'SECONDARY')");
		
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}
	
	/**
	 * Get primary diagnosis for a medication
	 */
	public function get_primary_diagnosis($iop_med_id)
	{
		$diagnoses = $this->get_medication_diagnoses($iop_med_id);
		foreach ($diagnoses as $d) {
			if ($d->diagnosis_type === 'PRIMARY') {
				return $d;
			}
		}
		return !empty($diagnoses) ? $diagnoses[0] : null;
	}
	
	/**
	 * Remove diagnosis from medication
	 */
	public function remove_medication_diagnosis($iop_med_id, $diagnosis_code)
	{
		return $this->db->delete('iop_medication_diagnosis', array(
			'iop_med_id' => $iop_med_id,
			'diagnosis_code' => $diagnosis_code
		));
	}

	public function install_opd_workflow_table(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_opd_workflow` (\n".
			"  `wf_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `iop_id` varchar(11) NOT NULL,\n".
			"  `status` varchar(30) NOT NULL,\n".
			"  `prev_status` varchar(30) DEFAULT NULL,\n".
			"  `waiting_at` datetime DEFAULT NULL,\n".
			"  `in_consultation_at` datetime DEFAULT NULL,\n".
			"  `clinically_cleared_at` datetime DEFAULT NULL,\n".
			"  `pending_lab_at` datetime DEFAULT NULL,\n".
			"  `pending_pharmacy_at` datetime DEFAULT NULL,\n".
			"  `completed_at` datetime DEFAULT NULL,\n".
			"  `updated_at` datetime NOT NULL,\n".
			"  `updated_by` varchar(25) DEFAULT NULL,\n".
			"  `InActive` int(1) NOT NULL,\n".
			"  PRIMARY KEY (`wf_id`),\n".
			"  UNIQUE KEY `uq_iop` (`iop_id`),\n".
			"  KEY `idx_status` (`status`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");
		return true;
	}

	public function ensure_workflow_schema(){
		if (!$this->table_exists('iop_opd_workflow')) {
			$this->install_opd_workflow_table();
		}
		$newCols = array(
			'prev_status'           => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `prev_status` varchar(30) DEFAULT NULL",
			'clinically_cleared_at' => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `clinically_cleared_at` datetime DEFAULT NULL",
			'pending_lab_at'        => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `pending_lab_at` datetime DEFAULT NULL",
			'pending_pharmacy_at'   => "ALTER TABLE `iop_opd_workflow` ADD COLUMN `pending_pharmacy_at` datetime DEFAULT NULL",
		);
		foreach ($newCols as $col => $sql) {
			if (!$this->column_exists('iop_opd_workflow', $col)) {
				$this->db->query($sql);
			}
		}
		if (!$this->table_exists('opd_status_audit')) {
			$this->db->query(
				"CREATE TABLE IF NOT EXISTS `opd_status_audit` (" .
				"  `audit_id` bigint(20) NOT NULL AUTO_INCREMENT," .
				"  `iop_id` varchar(11) NOT NULL," .
				"  `patient_no` varchar(20) DEFAULT NULL," .
				"  `old_status` varchar(30) DEFAULT NULL," .
				"  `new_status` varchar(30) NOT NULL," .
				"  `changed_by` varchar(25) DEFAULT NULL," .
				"  `changed_at` datetime NOT NULL," .
				"  `notes` varchar(255) DEFAULT NULL," .
				"  PRIMARY KEY (`audit_id`)," .
				"  KEY `idx_audit_iop` (`iop_id`)," .
				"  KEY `idx_audit_at` (`changed_at`)" .
				") ENGINE=MyISAM DEFAULT CHARSET=latin1"
			);
		}
	}

	/* ================================================================== */
	/*  CLEARANCE / OVERRIDE / ADMISSION QUEUE SCHEMA                    */
	/* ================================================================== */

	public function ensure_clearance_schema(){
		if (!$this->table_exists('opd_registration_override_log')) {
			$this->db->query(
				"CREATE TABLE IF NOT EXISTS `opd_registration_override_log` (" .
				"  `override_id` bigint(20) NOT NULL AUTO_INCREMENT," .
				"  `iop_id` varchar(11) NOT NULL," .
				"  `patient_no` varchar(20) NOT NULL," .
				"  `blocked_by_iop_id` varchar(11) DEFAULT NULL," .
				"  `override_by` varchar(25) NOT NULL," .
				"  `override_at` datetime NOT NULL," .
				"  `reason` text DEFAULT NULL," .
				"  PRIMARY KEY (`override_id`)," .
				"  KEY `idx_ovr_patient` (`patient_no`)," .
				"  KEY `idx_ovr_at` (`override_at`)" .
				") ENGINE=MyISAM DEFAULT CHARSET=latin1"
			);
		}
		if (!$this->table_exists('opd_admission_queue')) {
			$this->db->query(
				"CREATE TABLE IF NOT EXISTS `opd_admission_queue` (" .
				"  `queue_id` bigint(20) NOT NULL AUTO_INCREMENT," .
				"  `iop_id` varchar(11) NOT NULL," .
				"  `patient_no` varchar(20) NOT NULL," .
				"  `admission_reason` text DEFAULT NULL," .
				"  `doctor_id` varchar(25) DEFAULT NULL," .
				"  `admitted_by` varchar(25) DEFAULT NULL," .
				"  `admission_status` varchar(20) NOT NULL DEFAULT 'PENDING_ASSIGNMENT'," .
				"  `admitted_at` datetime DEFAULT NULL," .
				"  `created_at` datetime NOT NULL," .
				"  `InActive` tinyint(1) NOT NULL DEFAULT 0," .
				"  PRIMARY KEY (`queue_id`)," .
				"  KEY `idx_aq_patient` (`patient_no`)," .
				"  KEY `idx_aq_status` (`admission_status`)" .
				") ENGINE=MyISAM DEFAULT CHARSET=latin1"
			);
		}
	}

	/* ================================================================== */
	/*  PERFORMANCE INDEXES                                                */
	/* ================================================================== */

	public function ensure_performance_indexes()
	{
		$indexes = array(
			array('table' => 'patient_details_iop',   'name' => 'idx_iop_date_type',    'cols' => '(date_visit, patient_type, InActive)'),
			array('table' => 'patient_details_iop',   'name' => 'idx_iop_patient_type', 'cols' => '(patient_no, patient_type)'),
			array('table' => 'patient_details_iop',   'name' => 'idx_iop_nstatus',      'cols' => '(nStatus, date_visit)'),
			array('table' => 'iop_opd_workflow',      'name' => 'idx_wf_status_date',   'cols' => '(status, InActive)'),
			array('table' => 'opd_admission_queue',   'name' => 'idx_aq_status_active', 'cols' => '(admission_status, InActive)'),
			array('table' => 'opd_status_audit',      'name' => 'idx_audit_patient',    'cols' => '(patient_no, changed_at)'),
		);
		foreach ($indexes as $idx) {
			if (!$this->table_exists($idx['table'])) continue;
			$exists = $this->db->query(
				"SELECT COUNT(*) AS c FROM information_schema.STATISTICS
				 WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?",
				array($idx['table'], $idx['name'])
			)->row();
			if ($exists && (int)$exists->c === 0) {
				$this->db->query("ALTER TABLE `{$idx['table']}` ADD INDEX `{$idx['name']}` {$idx['cols']}");
			}
		}
	}

	/* ================================================================== */
	/*  CLEARANCE CHECK                                                    */
	/* ================================================================== */

	public function check_patient_clearance($patient_no)
	{
		$patient_no = (string)$patient_no;
		$result = array(
			'blocked'       => false,
			'pending_items' => array(),
			'blocking_iop'  => null,
		);

		$wfUncleared = array('WAITING','IN_CONSULTATION','PENDING_LAB','PENDING_PHARMACY');

		$cutoff = date('Y-m-d', strtotime('-30 days'));
		$latestOPD = $this->db->query(
			"SELECT IO_ID, date_visit, nStatus FROM patient_details_iop
			 WHERE patient_no = ? AND patient_type = 'OPD' AND InActive = 0
			 AND date_visit >= ?
			 ORDER BY date_visit DESC, time_visit DESC LIMIT 1",
			array($patient_no, $cutoff)
		)->row();

		if (!$latestOPD) {
			return $result;
		}
		$prevIop = (string)$latestOPD->IO_ID;
		$prevStatus = (string)$latestOPD->nStatus;

		if ($prevStatus !== 'Pending') {
			return $result;
		}

		$wfStatus = null;
		if ($this->table_exists('iop_opd_workflow')) {
			$wfRow = $this->db->get_where('iop_opd_workflow', array('iop_id' => $prevIop, 'InActive' => 0))->row();
			$wfStatus = $wfRow ? strtoupper(trim((string)$wfRow->status)) : null;
		}
		$isUncleared = ($wfStatus === null || in_array($wfStatus, $wfUncleared, true));
		if (!$isUncleared) {
			return $result;
		}

		$result['blocked']      = true;
		$result['blocking_iop'] = $prevIop;
		$result['pending_items'] = $this->get_pending_items($prevIop, $patient_no);
		return $result;
	}

	public function get_pending_items($iop_id, $patient_no)
	{
		$items = array();
		$iop_id     = (string)$iop_id;
		$patient_no = (string)$patient_no;

		if ($this->table_exists('iop_billing')) {
			$r = $this->db->query(
				"SELECT COUNT(*) AS c FROM iop_billing
				 WHERE iop_id = ? AND InActive = 0
				 AND total_amount > IFNULL((
				   SELECT SUM(amountPaid) FROM iop_receipt WHERE invoice_no = iop_billing.invoice_no AND InActive = 0
				 ), 0)",
				array($iop_id)
			)->row();
			if ($r && (int)$r->c > 0) {
				$items[] = array('type' => 'bill', 'label' => 'Pending Bills', 'count' => (int)$r->c, 'icon' => 'fa-money');
			}
		}
		if ($this->table_exists('iop_laboratory')) {
			$r = $this->db->query(
				"SELECT COUNT(*) AS c FROM iop_laboratory
				 WHERE iop_id = ? AND InActive = 0 AND (result = '' OR result IS NULL)",
				array($iop_id)
			)->row();
			if ($r && (int)$r->c > 0) {
				$items[] = array('type' => 'lab', 'label' => 'Pending Lab Results', 'count' => (int)$r->c, 'icon' => 'fa-flask');
			}
		}
		if ($this->table_exists('iop_medication')) {
			$r = $this->db->query(
				"SELECT COUNT(*) AS c FROM iop_medication
				 WHERE iop_id = ? AND InActive = 0
				 AND (dispensing_status IS NULL OR dispensing_status NOT IN ('DISPENSED','UNAVAILABLE'))",
				array($iop_id)
			)->row();
			if (!$r) {
				$r = $this->db->query(
					"SELECT COUNT(*) AS c FROM iop_medication WHERE iop_id = ? AND InActive = 0",
					array($iop_id)
				)->row();
			}
			if ($r && (int)$r->c > 0) {
				$items[] = array('type' => 'pharmacy', 'label' => 'Pending Pharmacy', 'count' => (int)$r->c, 'icon' => 'fa-medkit');
			}
		}
		if ($this->table_exists('opd_admission_queue')) {
			$r = $this->db->query(
				"SELECT COUNT(*) AS c FROM opd_admission_queue
				 WHERE patient_no = ? AND admission_status = 'PENDING_ASSIGNMENT' AND InActive = 0",
				array($patient_no)
			)->row();
			if ($r && (int)$r->c > 0) {
				$items[] = array('type' => 'admission', 'label' => 'Pending Admission', 'count' => (int)$r->c, 'icon' => 'fa-hospital-o');
			}
		}
		if ($this->table_exists('smart_billing_ledger')) {
			$r = $this->db->query(
				"SELECT COUNT(*) AS c FROM smart_billing_ledger
				 WHERE iop_id = ? AND status = 'PENDING' AND InActive = 0",
				array($iop_id)
			)->row();
			if ($r && (int)$r->c > 0) {
				$items[] = array('type' => 'smart_bill', 'label' => 'Pending Smart Bill', 'count' => (int)$r->c, 'icon' => 'fa-bolt');
			}
		}
		if ($this->table_exists('patient_details_iop')) {
			$r = $this->db->query(
				"SELECT COUNT(*) AS c FROM patient_details_iop
				 WHERE patient_no = ? AND patient_type = 'IPD' AND nStatus = 'Pending' AND InActive = 0",
				array($patient_no)
			)->row();
			if ($r && (int)$r->c > 0) {
				$items[] = array('type' => 'ipd', 'label' => 'Currently Admitted (IPD)', 'count' => (int)$r->c, 'icon' => 'fa-bed');
			}
		}
		return $items;
	}

	public function log_registration_override($iop_id, $patient_no, $blocking_iop, $user_id, $reason)
	{
		if (!$this->table_exists('opd_registration_override_log')) {
			return false;
		}
		$this->db->insert('opd_registration_override_log', array(
			'iop_id'           => (string)$iop_id,
			'patient_no'       => (string)$patient_no,
			'blocked_by_iop_id'=> (string)$blocking_iop,
			'override_by'      => (string)$user_id,
			'override_at'      => date('Y-m-d H:i:s'),
			'reason'           => (string)$reason,
		));
		return true;
	}

	/* ================================================================== */
	/*  IPD ADMISSION QUEUE                                                */
	/* ================================================================== */

	public function create_admission_queue($iop_id, $patient_no, $reason, $doctor_id, $admitted_by)
	{
		if (!$this->table_exists('opd_admission_queue')) {
			$this->ensure_clearance_schema();
		}
		$existing = $this->db->get_where('opd_admission_queue', array(
			'iop_id' => (string)$iop_id, 'InActive' => 0,
			'admission_status' => 'PENDING_ASSIGNMENT'
		))->row();
		if ($existing) {
			$this->db->where('queue_id', $existing->queue_id);
			$this->db->update('opd_admission_queue', array(
				'admission_reason' => (string)$reason,
				'doctor_id'        => (string)$doctor_id,
				'admitted_by'      => (string)$admitted_by,
			));
			return (int)$existing->queue_id;
		}
		$this->db->insert('opd_admission_queue', array(
			'iop_id'           => (string)$iop_id,
			'patient_no'       => (string)$patient_no,
			'admission_reason' => (string)$reason,
			'doctor_id'        => (string)$doctor_id,
			'admitted_by'      => (string)$admitted_by,
			'admission_status' => 'PENDING_ASSIGNMENT',
			'created_at'       => date('Y-m-d H:i:s'),
			'InActive'         => 0,
		));
		return (int)$this->db->insert_id();
	}

	public function get_admission_queue($limit = 50)
	{
		if (!$this->table_exists('opd_admission_queue')) return array();
		$sql = "SELECT Q.queue_id, Q.iop_id, Q.patient_no, Q.admission_reason,
				Q.admission_status, Q.created_at,
				CONCAT(COALESCE(PT.cValue,''),' ',P.firstname,' ',P.lastname) AS patient_name,
				P.age,
				CONCAT(COALESCE(DT.cValue,''),' ',COALESCE(D.firstname,''),' ',COALESCE(D.lastname,'')) AS doctor_name
				FROM opd_admission_queue Q
				JOIN patient_personal_info P ON P.patient_no = Q.patient_no
				LEFT JOIN system_parameters PT ON PT.param_id = P.title
				LEFT JOIN users D ON D.user_id = Q.doctor_id
				LEFT JOIN system_parameters DT ON DT.param_id = D.title
				WHERE Q.admission_status = 'PENDING_ASSIGNMENT' AND Q.InActive = 0
				ORDER BY Q.created_at DESC
				LIMIT " . (int)$limit;
		return $this->db->query($sql)->result();
	}

	public function mark_admission_assigned($queue_id, $user_id)
	{
		if (!$this->table_exists('opd_admission_queue')) return false;
		$this->db->where('queue_id', (int)$queue_id);
		$this->db->update('opd_admission_queue', array(
			'admission_status' => 'ADMITTED',
			'admitted_at'      => date('Y-m-d H:i:s'),
			'admitted_by'      => (string)$user_id,
		));
		return true;
	}

	/* ================================================================== */
	/*  DASHBOARD COUNTS (workflow-aware)                                  */
	/* ================================================================== */

	public function count_opd_status_today($status, $doctor_id = null)
	{
		$status = strtoupper(trim((string)$status));
		if ($this->table_exists('iop_opd_workflow')) {
			$sql = "SELECT COUNT(*) AS c FROM iop_opd_workflow W
					JOIN patient_details_iop P ON P.IO_ID = W.iop_id
					WHERE W.status = ? AND W.InActive = 0
					AND P.patient_type = 'OPD' AND P.InActive = 0
					AND P.date_visit = '" . date('Y-m-d') . "'";
			$params = array($status);
			if ($doctor_id) {
				$sql .= " AND P.doctor_id = ?";
				$params[] = (string)$doctor_id;
			}
			$r = $this->db->query($sql, $params)->row();
			return $r ? (int)$r->c : 0;
		}
		$fallback_status = ($status === 'WAITING' || $status === 'IN_CONSULTATION') ? 'Pending' : 'Discharged';
		$sql = "SELECT COUNT(*) AS c FROM patient_details_iop
				WHERE date_visit = '".date('Y-m-d')."' AND patient_type = 'OPD' AND nStatus = ? AND InActive = 0";
		$params = array($fallback_status);
		if ($doctor_id) {
			$sql .= " AND doctor_id = ?";
			$params[] = (string)$doctor_id;
		}
		$r = $this->db->query($sql, $params)->row();
		return $r ? (int)$r->c : 0;
	}

	public function get_all_valid_statuses(){
		return array('WAITING','IN_CONSULTATION','CLINICALLY_CLEARED','PENDING_LAB','PENDING_PHARMACY','COMPLETED','ADMITTED','CANCELLED');
	}

	public function get_status_label($status){
		$labels = array(
			'WAITING'            => 'Waiting',
			'IN_CONSULTATION'    => 'In Consultation',
			'CLINICALLY_CLEARED' => 'Clinically Cleared',
			'PENDING_LAB'        => 'Pending Lab',
			'PENDING_PHARMACY'   => 'Pending Pharmacy',
			'COMPLETED'          => 'Completed',
			'ADMITTED'           => 'Admitted',
			'CANCELLED'          => 'Cancelled',
		);
		$s = strtoupper(trim((string)$status));
		return isset($labels[$s]) ? $labels[$s] : ucwords(strtolower(str_replace('_', ' ', $s)));
	}

	public function get_status_badge($status){
		$classes = array(
			'WAITING'            => 'label-info',
			'IN_CONSULTATION'    => 'label-warning',
			'CLINICALLY_CLEARED' => 'label-success',
			'PENDING_LAB'        => 'label-danger',
			'PENDING_PHARMACY'   => 'label-primary',
			'COMPLETED'          => 'label-default',
			'ADMITTED'           => 'label-danger',
			'CANCELLED'          => 'label-default',
		);
		$s = strtoupper(trim((string)$status));
		$cls = isset($classes[$s]) ? $classes[$s] : 'label-default';
		return '<span class="label ' . $cls . '">' . htmlspecialchars($this->get_status_label($s)) . '</span>';
	}

	public function get_allowed_transitions($status){
		$status = strtoupper(trim((string)$status));
		$map = array(
			'WAITING'            => array('IN_CONSULTATION','CANCELLED'),
			'IN_CONSULTATION'    => array('CLINICALLY_CLEARED','ADMITTED','CANCELLED'),
			'CLINICALLY_CLEARED' => array('PENDING_LAB','PENDING_PHARMACY','COMPLETED','ADMITTED'),
			'PENDING_LAB'        => array('IN_CONSULTATION','CLINICALLY_CLEARED','PENDING_PHARMACY','COMPLETED'),
			'PENDING_PHARMACY'   => array('CLINICALLY_CLEARED','PENDING_LAB','COMPLETED'),
			'COMPLETED'          => array(),
			'ADMITTED'           => array(),
			'CANCELLED'          => array(),
		);
		return isset($map[$status]) ? $map[$status] : array();
	}

	public function is_valid_transition($from, $to){
		$from = strtoupper(trim((string)$from));
		$to   = strtoupper(trim((string)$to));
		if ($from === $to) return false;
		return in_array($to, $this->get_allowed_transitions($from), true);
	}

	public function get_current_workflow_status($iop_id){
		if (!$this->table_exists('iop_opd_workflow')) return null;
		$row = $this->db->get_where('iop_opd_workflow', array('iop_id' => (string)$iop_id, 'InActive' => 0))->row();
		return $row ? strtoupper(trim((string)$row->status)) : null;
	}

	/**
	 * Returns the active IN_CONSULTATION visit row for a doctor today (excluding $exclude_iop_id).
	 * Returns null if no active consultation found.
	 */
	public function get_doctor_active_consultation($doctor_id, $exclude_iop_id = null)
	{
		if (!$this->table_exists('iop_opd_workflow')) return null;
		$doctor_id = (string)$doctor_id;
		if ($doctor_id === '') return null;
		$sql = "SELECT W.iop_id, V.patient_no,
		               CONCAT(COALESCE(PI.firstname,''),' ',COALESCE(PI.lastname,'')) AS patient_name
		        FROM iop_opd_workflow W
		        INNER JOIN patient_details_iop V ON V.IO_ID = W.iop_id AND V.InActive = 0
		        INNER JOIN patient_personal_info PI ON PI.patient_no = V.patient_no
		        WHERE V.doctor_id = ? AND W.status = 'IN_CONSULTATION' AND W.InActive = 0
		          AND V.date_visit = ?";
		$params = array($doctor_id, date('Y-m-d'));
		if ($exclude_iop_id !== null && (string)$exclude_iop_id !== '') {
			$sql .= " AND W.iop_id != ?";
			$params[] = (string)$exclude_iop_id;
		}
		$sql .= " LIMIT 1";
		$q = $this->db->query($sql, $params);
		return ($q && $q->num_rows() > 0) ? $q->row() : null;
	}

	/**
	 * Returns array of doctor_ids that currently have an IN_CONSULTATION patient today.
	 * Used by the queue view to render Start buttons as disabled.
	 */
	public function get_busy_doctor_ids()
	{
		if (!$this->table_exists('iop_opd_workflow')) return array();
		$sql = "SELECT DISTINCT V.doctor_id
		        FROM iop_opd_workflow W
		        INNER JOIN patient_details_iop V ON V.IO_ID = W.iop_id AND V.InActive = 0
		        WHERE W.status = 'IN_CONSULTATION' AND W.InActive = 0
		          AND V.date_visit = ?
		          AND V.doctor_id IS NOT NULL AND V.doctor_id != ''";
		$q = $this->db->query($sql, array(date('Y-m-d')));
		if (!$q || $q->num_rows() === 0) return array();
		$ids = array();
		foreach ($q->result() as $r) {
			$ids[] = (string)$r->doctor_id;
		}
		return $ids;
	}

	public function log_status_transition($iop_id, $patient_no, $old_status, $new_status, $user_id, $notes = null){
		if (!$this->table_exists('opd_status_audit')) return;
		$this->db->insert('opd_status_audit', array(
			'iop_id'     => (string)$iop_id,
			'patient_no' => (string)$patient_no,
			'old_status' => $old_status !== null ? (string)$old_status : null,
			'new_status' => (string)$new_status,
			'changed_by' => $user_id !== null ? (string)$user_id : null,
			'changed_at' => date('Y-m-d H:i:s'),
			'notes'      => $notes ? substr((string)$notes, 0, 255) : null,
		));
	}

	public function install_opd_rr_table(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `iop_opd_rr_state` (\n".
			"  `rr_id` bigint(11) NOT NULL AUTO_INCREMENT,\n".
			"  `scope` varchar(50) NOT NULL,\n".
			"  `last_doctor_id` varchar(25) DEFAULT NULL,\n".
			"  `updated_at` datetime NOT NULL,\n".
			"  PRIMARY KEY (`rr_id`),\n".
			"  UNIQUE KEY `uq_scope` (`scope`)\n".
			") ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$q = $this->db->query("SHOW COLUMNS FROM `iop_opd_rr_state` LIKE 'last_doctor_id'");
		$r = $q ? $q->row() : null;
		if ($r && isset($r->Type) && stripos((string)$r->Type, 'int') === 0) {
			$this->db->query("ALTER TABLE `iop_opd_rr_state` MODIFY COLUMN `last_doctor_id` varchar(25) DEFAULT NULL");
		}
		return true;
	}

	public function get_next_doctor_round_robin($doctor_ids, $scope = 'OPD_ANY'){
		$scope = (string)$scope;
		if (!is_array($doctor_ids) || count($doctor_ids) === 0) {
			return null;
		}
		$clean = array();
		foreach ($doctor_ids as $id) {
			$id = trim((string)$id);
			if ($id !== '') {
				$clean[$id] = $id;
			}
		}
		$doctor_ids = array_values($clean);
		sort($doctor_ids, SORT_STRING);
		if (count($doctor_ids) === 0) {
			return null;
		}
		if (!$this->table_exists('iop_opd_rr_state')) {
			$this->install_opd_rr_table();
		}
		$state = $this->db->get_where('iop_opd_rr_state', array('scope' => $scope))->row();
		$last = $state && isset($state->last_doctor_id) ? trim((string)$state->last_doctor_id) : '';
		$next = $doctor_ids[0];
		if ($last !== '') {
			$pos = array_search($last, $doctor_ids, true);
			if ($pos !== false && count($doctor_ids) > 0) {
				$next = $doctor_ids[($pos + 1) % count($doctor_ids)];
			}
		}
		$data = array(
			'scope' => $scope,
			'last_doctor_id' => $next,
			'updated_at' => date('Y-m-d H:i:s')
		);
		if ($state) {
			$this->db->where('scope', $scope);
			$this->db->update('iop_opd_rr_state', $data);
			return $next;
		}
		$this->db->insert('iop_opd_rr_state', $data);
		return $next;
	}

	public function upsert_opd_workflow_status($iop_id, $status, $updated_by = null){
		$iop_id = trim((string) $iop_id);
		$status = strtoupper(trim((string) $status));
		if ($iop_id === '' || $status === '') {
			return false;
		}
		$this->load->model('app/opd_status_engine');
		if (!isset($this->opd_status_engine) || !$this->opd_status_engine->is_valid_status($status)) {
			return false;
		}

		$source = 'opd_model::upsert_opd_workflow_status';
		$current = $this->opd_status_engine->get_status($iop_id);
		if ($current === null) {
			$result = $this->opd_status_engine->initialize_visit($iop_id, $status, $updated_by, $source);
			return is_array($result) && !empty($result['success']);
		}
		if ($current === $status) {
			return true;
		}

		$result = $this->opd_status_engine->transition($iop_id, $status, $updated_by, 'Legacy workflow status compatibility wrapper', $source, true);
		return is_array($result) && !empty($result['success']);
	}

	public function get_workflow_map($iop_ids){
		$map = array();
		if (!$this->table_exists('iop_opd_workflow')) {
			return $map;
		}
		if (!is_array($iop_ids) || count($iop_ids) === 0) {
			return $map;
		}
		$this->db->where_in('iop_id', $iop_ids);
		$this->db->where('InActive', 0);
		$q = $this->db->get('iop_opd_workflow');
		$rows = $q ? $q->result() : array();
		foreach ($rows as $r) {
			$map[(string)$r->iop_id] = $r;
		}
		return $map;
	}

	public function update_doctor_assignment($iop_id, $patient_no, $doctor_id, $updated_by = null){
		$iop_id = (string) $iop_id;
		$patient_no = (string) $patient_no;
		$this->db->where('IO_ID', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->update('patient_details_iop', array('doctor_id' => trim((string)$doctor_id)));
		if ($this->table_exists('iop_opd_workflow')) {
			$this->load->model('app/opd_status_engine');
			$this->opd_status_engine->sync_assignment($iop_id, $updated_by);
		}
		return true;
	}

	/**
	 * Check if a doctor is currently engaged (has a patient IN_CONSULTATION today)
	 */
	public function is_doctor_engaged($doctor_id){
		$doctor_id = trim((string)$doctor_id);
		if ($doctor_id === '') return false;
		if ($this->table_exists('iop_opd_workflow')) {
			$sql = "SELECT COUNT(*) AS c FROM iop_opd_workflow W
					JOIN patient_details_iop P ON P.IO_ID = W.iop_id
					WHERE W.status = 'IN_CONSULTATION' AND W.InActive = 0
					AND P.doctor_id = ? AND P.date_visit = ? AND P.InActive = 0";
			$r = $this->db->query($sql, array($doctor_id, date('Y-m-d')))->row();
			return ($r && (int)$r->c > 0);
		}
		return false;
	}

	/**
	 * Get first available (non-engaged) doctor from list
	 */
	public function get_available_doctor($doctor_ids){
		if (!is_array($doctor_ids) || count($doctor_ids) === 0) return null;
		foreach ($doctor_ids as $doc_id) {
			if (!$this->is_doctor_engaged($doc_id)) {
				return $doc_id;
			}
		}
		return null;
	}

	/**
	 * Quick start OPD - creates OPD record with minimal data, auto-assigns doctor, sets queue status
	 * Returns array with iop_id, doctor_id, status, message
	 */
	public function quick_start_opd($patient_no, $user_id = null){
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') {
			return array('success' => false, 'message' => 'Patient number is required.');
		}

		// Check patient exists
		$patient = $this->db->get_where('patient_personal_info', array('patient_no' => $patient_no, 'InActive' => 0))->row();
		if (!$patient) {
			return array('success' => false, 'message' => 'Patient not found.');
		}

		// Check clearance
		$clearance = $this->check_patient_clearance($patient_no);
		if ($clearance['blocked']) {
			return array('success' => false, 'message' => 'Patient has uncleared previous visit. Please resolve pending items first.', 'blocking_iop' => $clearance['blocking_iop']);
		}

		$this->load->model('app/billing_model');
		if (isset($this->billing_model) && method_exists($this->billing_model, 'check_patient_outstanding_balance_for_registration')) {
			$ob = $this->billing_model->check_patient_outstanding_balance_for_registration($patient_no, 5);
			if ($ob && isset($ob['blocked']) && $ob['blocked']) {
				return array('success' => false, 'message' => 'Patient has an outstanding balance from previous visit(s). Please settle at the cashier before creating a new OPD visit.', 'outstanding' => $ob);
			}
		}

		// Get next OPD number - NO SPACE for URL-safe IDs
		$this->load->model('general_model');
		$lastOPD = $this->general_model->lastOPDNo();
		$nextOPDNum = ($lastOPD && isset($lastOPD->opdNo)) ? (int)$lastOPD->opdNo : 1;
		$opdNo = 'OP' . str_pad($nextOPDNum, 6, '0', STR_PAD_LEFT);

		// Get available doctors (prefer available/IN status)
		$available = $this->general_model->getDoctorAvailability('IN');
		$doctorIds = array();
		foreach ($available as $d) {
			$doctorIds[] = (string)$d->user_id;
		}
		if (count($doctorIds) === 0) {
			$all = $this->general_model->doctorList();
			foreach ($all as $d) {
				$doctorIds[] = (string)$d->user_id;
			}
		}

		// Try to find non-engaged doctor first
		$assignedDoctor = $this->get_available_doctor($doctorIds);
		if (!$assignedDoctor && count($doctorIds) > 0) {
			// All doctors engaged, use round-robin
			$assignedDoctor = $this->get_next_doctor_round_robin($doctorIds, 'OPD_ANY');
		}

		$initialStatus = 'WAITING';

		// Get default department (first one)
		$deptList = $this->general_model->departmentList();
		$deptId = (count($deptList) > 0 && isset($deptList[0]->department_id)) ? $deptList[0]->department_id : null;

		// Create OPD record
		$data = array(
			'IO_ID'           => $opdNo,
			'patient_no'      => $patient_no,
			'patient_type'    => 'OPD',
			'date_visit'      => date('Y-m-d'),
			'time_visit'      => date('H:i:s'),
			'doctor_id'       => $assignedDoctor,
			'room_id'         => 0,
			'department_id'   => $deptId,
			'nStatus'         => 'Pending',
			'InActive'        => 0
		);
		$this->db->insert('patient_details_iop', $data);

		// Update system OPD counter
		$this->db->where(array('cCode' => 'OUTPATIENTNO', 'InActive' => 0));
		$this->db->update('system_option', array('cValue' => $nextOPDNum));

		// Create workflow entry
		if (!$this->table_exists('iop_opd_workflow')) {
			$this->install_opd_workflow_table();
		}
		$this->load->model('app/opd_status_engine');
		$this->opd_status_engine->initialize_visit($opdNo, $initialStatus, $user_id, 'opd_model::quick_start_opd');

		// Get doctor name for message
		$doctorName = 'Unassigned';
		if ($assignedDoctor) {
			$doc = $this->db->select("CONCAT_WS(' ', firstname, lastname) AS name")->get_where('users', array('user_id' => $assignedDoctor))->row();
			if ($doc) $doctorName = $doc->name;
		}

		return array(
			'success'    => true,
			'iop_id'     => $opdNo,
			'patient_no' => $patient_no,
			'doctor_id'  => $assignedDoctor,
			'doctor_name'=> $doctorName,
			'status'     => $initialStatus,
			'message'    => "Patient queued for vitals and consultation with Dr. $doctorName."
		);
	}

	public function getAll($limit = 10, $offset = 0)
	{
		// var_dump($this->input->post("insurance"));
		$limit = (int)$limit;
		$offset = (int)$offset;
		$cFrom = trim((string)$this->input->post('cFrom'));
		$cTo = trim((string)$this->input->post('cTo'));
		if ($cFrom === '') {
			$cFrom = trim((string)$this->session->userdata('search_opd_From'));
		}
		if ($cTo === '') {
			$cTo = trim((string)$this->session->userdata('search_opd_cTo'));
		}
		if ($cFrom === '') {
			$cFrom = date('Y-m-d');
		}
		if ($cTo === '') {
			$cTo = date('Y-m-d');
		}

		$insStatusSelect = "'ACTIVE'";
		if ($this->column_exists('patient_personal_info', 'insurance_card_status')) {
			$insStatusSelect = 'B.insurance_card_status';
		}

		$this->db->select("
			A.IO_ID,
			COALESCE(B.patient_no, A.patient_no) as patient_no,
			CONCAT_WS(' ', C.cValue, B.firstname, B.middlename, B.lastname) as 'name',
			B.age,
			B.Insurance_comp,
			".$insStatusSelect." as insurance_card_status,
			A.date_visit,
			A.time_visit,
			D.dept_name,
			CONCAT_WS(' ', F.cValue, E.firstname, E.middlename, E.lastname) as 'doctor',
			A.nStatus,
			A.clinical_clearance_status
			", false);
		$search = trim((string)$this->input->post('search'));
		$insurance = trim((string)$this->input->post('insurance'));
		$department = trim((string)$this->input->post('department'));
		$doctor = trim((string)$this->input->post('doctor'));
		if ($search === '') {
			$search = trim((string)$this->session->userdata('search_opd_master'));
		}
		if ($insurance === '') {
			$insurance = trim((string)$this->session->userdata('search_opd_insurance'));
		}
		if ($department === '') {
			$department = trim((string)$this->session->userdata('search_opd_department'));
		}
		if ($doctor === '') {
			$doctor = trim((string)$this->session->userdata('search_opd_doctor'));
		}
		
		$this->db->where('A.patient_type', 'OPD');
		$this->db->where('A.InActive', 0);
		$this->db->where("A.date_visit >=", $cFrom);
		$this->db->where("A.date_visit <=", $cTo);

		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('B.lastname', $search);
			$this->db->or_like('B.firstname', $search);
			$this->db->or_like('B.patient_no', $search);
			$this->db->or_like('A.IO_ID', $search);
			$this->db->group_end();
		}
		if ($insurance !== '') {
			$this->db->where('B.Insurance_comp', $insurance);
		}
		if ($department !== '') {
			$this->db->where('A.department_id', $department);
		}
		if ($doctor !== '') {
			$this->db->where('A.doctor_id', $doctor);
		}

		$this->db->order_by("(A.nStatus='Pending')", 'DESC', false);
		$this->db->order_by('A.date_visit', 'DESC');
		$this->db->order_by('A.time_visit', 'DESC');
		$this->db->join("patient_personal_info B", "B.patient_no = A.patient_no", "left outer");
		$this->db->join("system_parameters C", "C.param_id = B.title", "left outer");
		$this->db->join("department D", "D.department_id = A.department_id", "left outer");
		$this->db->join("users E", "CAST(E.user_id AS UNSIGNED) = CAST(A.doctor_id AS UNSIGNED)", "left outer", false);
		$this->db->join("system_parameters F", "F.param_id = E.title", "left outer");
		// GROUP BY removed - not needed without aggregate functions, and causes MySQL 8 ONLY_FULL_GROUP_BY errors
		// Using DISTINCT instead to avoid duplicates from JOINs
		$this->db->distinct();
		$query = $this->db->get("patient_details_iop A", $limit, $offset);

		return $query->result();
	}

	public function count_all()
	{
		$cFrom = trim((string)$this->input->post('cFrom'));
		$cTo = trim((string)$this->input->post('cTo'));
		if ($cFrom === '') {
			$cFrom = trim((string)$this->session->userdata('search_opd_From'));
		}
		if ($cTo === '') {
			$cTo = trim((string)$this->session->userdata('search_opd_cTo'));
		}
		if ($cFrom === '') {
			$cFrom = date('Y-m-d');
		}
		if ($cTo === '') {
			$cTo = date('Y-m-d');
		}

		$search = trim((string)$this->input->post('search'));
		$insurance = trim((string)$this->input->post('insurance'));
		$department = trim((string)$this->input->post('department'));
		$doctor = trim((string)$this->input->post('doctor'));
		if ($search === '') {
			$search = trim((string)$this->session->userdata('search_opd_master'));
		}
		if ($insurance === '') {
			$insurance = trim((string)$this->session->userdata('search_opd_insurance'));
		}
		if ($department === '') {
			$department = trim((string)$this->session->userdata('search_opd_department'));
		}
		if ($doctor === '') {
			$doctor = trim((string)$this->session->userdata('search_opd_doctor'));
		}

		$this->db->from('patient_details_iop A');
		$this->db->join("patient_personal_info B", "B.patient_no = A.patient_no", "left outer");
		$this->db->join("system_parameters C", "C.param_id = B.title", "left outer");
		$this->db->join("department D", "D.department_id = A.department_id", "left outer");
		$this->db->join("users E", "(E.user_id = A.doctor_id OR CAST(E.user_id AS UNSIGNED) = CAST(A.doctor_id AS UNSIGNED))", "left outer", false);
		$this->db->join("system_parameters F", "F.param_id = E.title", "left outer");

		$this->db->where('A.patient_type', 'OPD');
		$this->db->where('A.InActive', 0);
		$this->db->where("A.date_visit >=", $cFrom);
		$this->db->where("A.date_visit <=", $cTo);

		if ($search !== '') {
			$this->db->group_start();
			$this->db->like('B.lastname', $search);
			$this->db->or_like('B.firstname', $search);
			$this->db->or_like('B.patient_no', $search);
			$this->db->or_like('A.IO_ID', $search);
			$this->db->group_end();
		}
		if ($insurance !== '') {
			$this->db->where('B.Insurance_comp', $insurance);
		}
		if ($department !== '') {
			$this->db->where('A.department_id', $department);
		}
		if ($doctor !== '') {
			$this->db->where('A.doctor_id', $doctor);
		}

		return (int)$this->db->count_all_results();
	}





	public function getAll_search($limit = 10, $offset = 0)
	{
		// "Pending" visits are used as the legacy indicator that a patient has an
		// open OPD encounter. Clinically-cleared visits can still have nStatus=Pending
		// (see opd_status_engine legacy sync), so we must treat clinically-cleared
		// visits as non-blocking for new OPD registration.
		$has_visit_status = $this->column_exists('patient_details_iop', 'visit_status');
		$has_clin_clear  = $this->column_exists('patient_details_iop', 'clinical_clearance_status');
		$has_patient_type = $this->column_exists('patient_details_iop', 'patient_type');
		$sub = "nStatus = 'Pending' AND InActive = 0";
		if ($has_patient_type) {
			$sub .= " AND patient_type = 'OPD'";
		}
		if ($has_visit_status && $has_clin_clear) {
			$sub .= " AND (COALESCE(visit_status,'active') = 'active' OR COALESCE(clinical_clearance_status,0) = 0)";
		} elseif ($has_visit_status) {
			$sub .= " AND COALESCE(visit_status,'active') = 'active'";
		} elseif ($has_clin_clear) {
			$sub .= " AND COALESCE(clinical_clearance_status,0) = 0";
		}

		if ($this->input->post('insurance')) {
			$this->db->select("
			A.patient_no,
			A.Insurance_comp,
			concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
			C.cValue as 'gender',
			D.cValue as 'civil_status',
			A.age,
			A.date_entry
		", false);
			$search = $this->db->escape_like_str($this->input->post('search'));
			$ins = $this->db->escape_like_str($this->input->post('insurance'));
			$where = "(
			
			A.Insurance_comp LIKE '%{$ins}%' ESCAPE '!' OR
			A.lastname LIKE '%{$search}%' ESCAPE '!' OR 
			A.firstname LIKE '%{$search}%' ESCAPE '!' OR 
			A.patient_no LIKE '%{$search}%' ESCAPE '!'
				) 
				AND A.patient_no NOT IN(SELECT patient_no FROM patient_details_iop WHERE {$sub})
				AND A.InActive = 0 ";
			$this->db->where($where);
			$this->db->order_by('lastname', 'asc');
			$this->db->join("system_parameters B", "B.param_id = A.title", "left outer");
			$this->db->join("system_parameters C", "C.param_id = A.gender", "left outer");
			$this->db->join("system_parameters D", "D.param_id = A.civil_status", "left outer");
			$query = $this->db->get("patient_personal_info A", $limit, $offset);
			return $query->result();
		} else {
			$this->db->select("
			A.patient_no,
			concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
			C.cValue as 'gender',
			D.cValue as 'civil_status',
			A.age,
			A.date_entry
		", false);
			$search = $this->db->escape_like_str($this->input->post('search'));
			$where = "(
			A.lastname LIKE '%{$search}%' ESCAPE '!' OR 
			A.firstname LIKE '%{$search}%' ESCAPE '!' OR 
			A.patient_no LIKE '%{$search}%' ESCAPE '!'
				) 
				AND A.patient_no NOT IN(SELECT patient_no FROM patient_details_iop WHERE {$sub})
				AND A.InActive = 0 ";
			$this->db->where($where);
			$this->db->order_by('lastname', 'asc');
			$this->db->join("system_parameters B", "B.param_id = A.title", "left outer");
			$this->db->join("system_parameters C", "C.param_id = A.gender", "left outer");
			$this->db->join("system_parameters D", "D.param_id = A.civil_status", "left outer");
			$query = $this->db->get("patient_personal_info A", $limit, $offset);
			return $query->result();
		}
	}

	public function getInsurance($id)
	{
		$this->db->select("company_name");
		$this->db->where("in_com_id", $id);
		$this->db->order_by('in_com_id', 'desc');
		$query = $this->db->get("insurance_comp");
		return $query->row();
	}


	public function count_all_search()
	{
		$has_visit_status = $this->column_exists('patient_details_iop', 'visit_status');
		$has_clin_clear  = $this->column_exists('patient_details_iop', 'clinical_clearance_status');
		$has_patient_type = $this->column_exists('patient_details_iop', 'patient_type');
		$sub = "nStatus = 'Pending' AND InActive = 0";
		if ($has_patient_type) {
			$sub .= " AND patient_type = 'OPD'";
		}
		if ($has_visit_status && $has_clin_clear) {
			$sub .= " AND (COALESCE(visit_status,'active') = 'active' OR COALESCE(clinical_clearance_status,0) = 0)";
		} elseif ($has_visit_status) {
			$sub .= " AND COALESCE(visit_status,'active') = 'active'";
		} elseif ($has_clin_clear) {
			$sub .= " AND COALESCE(clinical_clearance_status,0) = 0";
		}

		$this->db->select("
			A.patient_no,
			concat(B.cValue,' ',A.firstname,' ',A.middlename,' ',A.lastname) as 'name',
			C.cValue,
			D.cValue,
			A.age,
			A.date_entry
		", false);
		$search = $this->db->escape_like_str($this->input->post('search'));
		$where = "(
				A.lastname LIKE '%{$search}%' ESCAPE '!' OR 
				A.firstname LIKE '%{$search}%' ESCAPE '!' OR 
				A.patient_no LIKE '%{$search}%' ESCAPE '!'
				)           
				AND A.patient_no NOT IN(SELECT patient_no FROM patient_details_iop WHERE {$sub}) 
				AND A.InActive = 0";
		$this->db->where($where);
		$this->db->order_by('lastname', 'asc');
		$this->db->join("system_parameters B", "B.param_id = A.title", "left outer");
		$this->db->join("system_parameters C", "C.param_id = A.gender", "left outer");
		$this->db->join("system_parameters D", "D.param_id = A.civil_status", "left outer");
		$query = $this->db->get("patient_personal_info A");
		return $query->num_rows();
	}

	public function validate_opd()
	{
		$patient_no = (string)$this->input->post('patient_no');
		$this->db->where(array(
			'patient_no' => $patient_no,
			'nStatus'    => 'Pending',
			'InActive'   => 0
		));
		if ($this->column_exists('patient_details_iop', 'patient_type')) {
			$this->db->where('patient_type', 'OPD');
		}
		// Treat clinically cleared visits as "not active" for the purpose of creating
		// a new OPD registration.
		$has_visit_status = $this->column_exists('patient_details_iop', 'visit_status');
		$has_clin_clear  = $this->column_exists('patient_details_iop', 'clinical_clearance_status');
		if ($has_visit_status && $has_clin_clear) {
			$this->db->where("(COALESCE(visit_status,'active') = 'active' OR COALESCE(clinical_clearance_status,0) = 0)", null, false);
		} elseif ($has_visit_status) {
			$this->db->where("COALESCE(visit_status,'active') = 'active'", null, false);
		} elseif ($has_clin_clear) {
			$this->db->where("COALESCE(clinical_clearance_status,0) = 0", null, false);
		}
		$query = $this->db->get("patient_details_iop");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function save()
	{
		$this->data = array(
			'IO_ID'						=>		$this->input->post('opdNo'),
			'patient_no'				=>		$this->input->post('patient_no'),
			'patient_type'				=>		'OPD',
			'date_visit'				=>		date('Y-m-d'),
			'time_visit'				=>		date('h:i:s'),
			'doctor_id'					=>		$this->input->post('doctor'),
			'refferal_doctor'			=>		$this->input->post('refdoctor'),
			'room_id'					=>		0,
			'department_id'				=>		$this->input->post('department'),
			'provisional_diagnosis'		=>		$this->input->post('diagnosis'),
			'complaints'				=>		$this->input->post('complaints'),
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
		if ($this->column_exists('patient_details_iop', 'spo2')) {
			$this->data['spo2'] = $this->input->post('spo2');
		}
		if ($this->column_exists('patient_details_iop', 'insurance_cover_id')) {
			$ins_cover_id = $this->input->post('insurance_cover_id');
			// Convert empty string to NULL for integer column
			$this->data['insurance_cover_id'] = ($ins_cover_id !== '' && $ins_cover_id !== null) ? (int)$ins_cover_id : null;
		}
		if ($this->column_exists('patient_details_iop', 'insurance_billing_type')) {
			$this->data['insurance_billing_type'] = $this->input->post('insurance_billing_type');
		}

		$this->db->insert("patient_details_iop", $this->data);
	}

	public function save_vital()
	{
		$this->data = array(
			'iop_id'					=>		$this->input->post('opdNo'),
			'dDate'						=>		date("Y-m-d"),
			'dDateTime'					=>		date("Y-m-d h:i:s"),
			'pulse_rate'				=>		$this->input->post('pulse_rate'),
			'temperature'				=>		$this->input->post('temperature'),
			'height'					=>		$this->input->post('height'),
			'bp'						=>		$this->input->post('bp'),
			'respiration'				=>		$this->input->post('respiration'),
			'weight'					=>		$this->input->post('weight'),
			'InActive'					=>		0
		);
		if ($this->column_exists('iop_vital_parameters', 'spo2')) {
			$this->data['spo2'] = $this->input->post('spo2');
		}

		$this->db->insert("iop_vital_parameters", $this->data);
	}

	public function diagnosisList()
	{
		$this->db->select("diagnosis_id, diagnosis_name, icd_code, category, common_treatment", false);
		$this->db->where("InActive", 0);
		$this->db->where("(is_active = 1 OR is_active IS NULL)", null, false);
		$this->db->order_by("category", "ASC");
		$this->db->order_by("icd_code", "ASC");
		$this->db->order_by("diagnosis_name", "ASC");
		$query = $this->db->get("diagnosis");
		return $query->result();
	}

	public function ComplainList()
	{
		$has_sort     = $this->column_exists('complain', 'sort_order');
		$has_common   = $this->column_exists('complain', 'is_common');
		$has_category = $this->column_exists('complain', 'category');

		if ($has_common && $has_category && $has_sort) {
			$this->db->order_by('is_common', 'DESC');
			$this->db->order_by('category',  'ASC');
			$this->db->order_by('sort_order', 'ASC');
			$this->db->order_by('complain_name', 'ASC');
		} else {
			$this->db->order_by('complain_name', 'ASC');
		}
		$query = $this->db->get_where('complain', array('InActive' => '0'));
		return $query->result();
	}

	public function getOPDPatient($iop_no)
	{
		$hasDetStart = $this->column_exists('patient_details_iop', 'detention_start_at');
		$hasConvAt = $this->column_exists('patient_details_iop', 'converted_to_admission_at');
		$hasConvIpd = $this->column_exists('patient_details_iop', 'converted_ipd_iop_id');
		$detSelect = ($hasDetStart ? "A.detention_start_at" : "NULL AS detention_start_at");
		$convAtSelect = ($hasConvAt ? "A.converted_to_admission_at" : "NULL AS converted_to_admission_at");
		$convIpdSelect = ($hasConvIpd ? "A.converted_ipd_iop_id" : "NULL AS converted_ipd_iop_id");

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
				A.room_id,
				{$detSelect},
				{$convAtSelect},
				{$convIpdSelect}
		", false);
		$this->db->where("A.IO_ID", $iop_no);
		$this->db->join("users B", "B.user_id = A.refferal_doctor", "left outer");
		$this->db->join("users C", "C.user_id = A.doctor_id", "left outer");
		$this->db->join("system_parameters D", "D.param_id = B.title", "left outer");
		$this->db->join("system_parameters E", "E.param_id = C.title", "left outer");
		$this->db->join("department F", "F.department_id = A.department_id", "left outer");
		$query = $this->db->get("patient_details_iop A");
		return $query->row();
	}

	public function validate_diagnosis()
	{
		$this->db->where(array(
			'iop_id'				=>		$this->input->post('opd_no'),
			'diagnosis_id'			=>		$this->input->post('diagnosis'),
			'InActive'				=>		0
		));
		$query = $this->db->get("iop_diagnosis");
		if ($query->num_rows() > 0) {
			return true;
		} else {
			return false;
		}
	}

	public function save_complain()
	{
		// CRITICAL: Decode URL-safe ID back to original format (OP-000002 -> OP 000002)
		$iop_id = url_decode_id($this->input->post('opd_no'));
		
		$this->data = array(
			'iop_id'		=>		$iop_id,
			'complain_id'	=>		$this->input->post('complain'),
			'complain_text'	=>		$this->input->post('complain_text'),
			'remarks'		=>		$this->input->post('remarks'),
			'dDate'			=>		date("Y-m-d h:i:s"),
			'InActive'		=>		0
		);
		$this->db->insert("iop_complaints", $this->data);
	}

	public function save_diagnosis()
	{
		// CRITICAL: Decode URL-safe ID back to original format (OP-000002 -> OP 000002)
		$iop_id = url_decode_id($this->input->post('opd_no'));
		$diag_id = $this->input->post('diagnosis');

		// Lookup icd_code from diagnosis master
		$icd_code = null;
		if ($diag_id && is_numeric($diag_id)) {
			$dq = $this->db->query("SELECT icd_code FROM diagnosis WHERE diagnosis_id = ? AND InActive = 0 LIMIT 1", array((int)$diag_id));
			if ($dq && $dq->num_rows() > 0) {
				$icd_code = $dq->row()->icd_code;
			}
		}

		$this->data = array(
			'iop_id'			=>		$iop_id,
			'diagnosis_id'		=>		$diag_id,
			'icd_code'			=>		$icd_code,
			'diagnosis_text'	=>		$this->input->post('diagnosis_text'),
			'remarks'			=>		$this->input->post('remarks'),
			'dDate'				=>		date("Y-m-d h:i:s"),
			'InActive'			=>		0
		);
		// icd_code column may not exist on older installs — gracefully omit
		$col_q = $this->db->query("SHOW COLUMNS FROM `iop_diagnosis` LIKE 'icd_code'");
		if (!$col_q || $col_q->num_rows() === 0) {
			unset($this->data['icd_code']);
		}
		$this->db->insert("iop_diagnosis", $this->data);
	}


	public function patientDiagnosis($iop_no)
	{
		$this->db->select("
			A.iop_diag_id,
			B.diagnosis_name,
			COALESCE(A.icd_code, B.icd_code) AS icd_code,
			COALESCE(B.category, '') AS category,
			A.remarks,
			A.diagnosis_text,
			A.dDate
		", false);
		$this->db->order_by("A.iop_diag_id", "DESC");
		$this->db->where(array(
			'A.iop_id'		=>		$iop_no,
			'A.InActive'	=>		0
		));
		$this->db->join("diagnosis B", "B.diagnosis_id = A.diagnosis_id", "left outer");
		$query = $this->db->get("iop_diagnosis A");
		return $query->result();
	}

	public function patientComplain($iop_no)
	{
		$has_severity    = $this->column_exists('iop_complaints', 'severity');
		$has_duration    = $this->column_exists('iop_complaints', 'duration');
		$has_onset       = $this->column_exists('iop_complaints', 'onset');
		$has_recorded_by = $this->column_exists('iop_complaints', 'recorded_by');
		$has_text        = $this->column_exists('iop_complaints', 'complain_text');

		$select = "A.iop_comp_id, B.complain_name, A.remarks, A.dDate";
		if ($has_text)        { $select .= ", A.complain_text"; }
		if ($has_severity)    { $select .= ", A.severity"; }
		if ($has_duration)    { $select .= ", A.duration"; }
		if ($has_onset)       { $select .= ", A.onset"; }
		if ($has_recorded_by) {
			$select .= ", A.recorded_by"
			        .  ", CONCAT(COALESCE(C.firstname,''),' ',COALESCE(C.lastname,'')) AS recorded_by_name";
		}

		$this->db->select($select, false);
		$this->db->order_by("A.iop_comp_id", "DESC");
		$this->db->where(array(
			'A.iop_id'   => $iop_no,
			'A.InActive' => 0
		));
		$this->db->join("complain B", "B.complain_id = A.complain_id", "left outer");
		if ($has_recorded_by) {
			// CAST recorded_by (VARCHAR) to UNSIGNED to match user_id (INT) — avoids full scan
			$this->db->join("users C", "C.user_id = CAST(A.recorded_by AS UNSIGNED)", "left outer");
		}
		$query = $this->db->get("iop_complaints A");
		return $query->result();
	}

	public function medicineCategory()
	{
		$this->db->order_by("med_category_name", "ASC");
		$this->db->where("InActive", "0");
		$query = $this->db->get("medicine_category");
		return $query->result();
	}

	public function drug_name_lists($id)
	{
		$this->db->order_by("drug_name", "ASC");
		$this->db->where(array(
			'med_cat_id'	=>		$id,
			'InActive'		=>		0
		));
		$query = $this->db->get("medicine_drug_name");
		return $query->result();
	}

	public function patientMedication($iop_no)
	{
		$this->db->select("
					A.dDate,
						A.iop_med_id,
						B.drug_name,
						A.instruction,
						A.advice,
						A.days,
						A.total_qty,
						A.medicine_text,
						A.dosage,
						A.frequency,
						A.dispensing_status,
						B.nhis_drug_code,
						concat(D.cValue,' ',C.firstname,' ',C.middlename,' ',C.lastname) as name
						", false);
		$this->db->order_by("A.iop_med_id", "asc");
		$this->db->where(array(
			'A.iop_id'		=>	$iop_no,
			'A.InActive'	=>	0
		));
		$this->db->join("medicine_drug_name B", "B.drug_id = A.medicine_id", "left outer");
		$this->db->join("users C", "C.user_id = A.cPreparedBy", "left outer");
		$this->db->join("system_parameters D", "D.param_id = C.title", "left outer");
		$query = $this->db->get("iop_medication A");
		return $query->result();
	}

	public function get_discharge_summary($iop_no)
	{
		$query = $this->db->get_where("iop_discharge_summary", array(
			'iop_id'	=>		$iop_no,
			'InActive'	=>		0
		));
		return $query->row();
	}

	public function getProgressNote($iop_no)
	{
		$this->db->order_by("dDateTime", "DESC");
		$query = $this->db->get_where("iop_progress_note", array(
			'iop_id'	=>		$iop_no,
			'InActive'	=>		0
		));

		return $query->result();
	}

	public function getVital($iop_no)
	{
		$this->db->order_by("dDateTime", "DESC");
		$query = $this->db->get_where("iop_vital_parameters", array(
			'iop_id'	=>		$iop_no,
			'InActive'	=>		0
		));

		return $query->result();
	}

	public function getNurseProgressNote($iop_no)
	{
		$this->db->order_by("dDateTime", "DESC");
		$query = $this->db->get_where("iop_nurse_notes", array(
			'iop_id'	=>		$iop_no,
			'InActive'	=>		0
		));

		return $query->result();
	}

	public function getOperationTheater($iop_no)
	{
		$this->db->select("A.*,B.surgery_name");
		$this->db->join("surgical_package B", "B.surgery_id = A.operation_name", "left outer");
		$query = $this->db->get_where("iop_operation_theater A", array('A.iop_id' => $iop_no));
		return $query->row();
	}

	public function getServices($iop_no)
	{
		$this->db->select("
				A.dDateTime,
				A.bed_pro_id,
				B.particular_name,
				A.qty,
				A.notes,
				concat(D.cValue,' ',C.firstname,' ',C.middlename,C.lastname) as name
				", false);
		$this->db->order_by("A.dDateTime", "DESC");
		$this->db->join("bill_particular B", "B.particular_id = A.cItem_id", "left outer");
		$this->db->join("users C", "C.user_id = A.cPreparedBy", "left outer");
		$this->db->join("system_parameters D", "D.param_id = C.title", "left outer");
		$query = $this->db->get_where("iop_bed_side_procedure A", array('A.iop_id' => $iop_no, 'A.InActive' => '0'));
		return $query->result();
	}

	public function patient_lab($iop_no)
	{
		$this->load->model('app/laboratory_model');
		$sonography_cat = (isset($this->laboratory_model) && method_exists($this->laboratory_model, 'get_sonography_category_id'))
			? (int)$this->laboratory_model->get_sonography_category_id()
			: 18;
		$radiology_cat = (isset($this->laboratory_model) && method_exists($this->laboratory_model, 'get_radiology_category_id'))
			? (int)$this->laboratory_model->get_radiology_category_id()
			: 16;
		$hasSonoMeta = $this->table_exists('iop_sonography_request_meta');
		$hasSonoItems = $this->table_exists('sonography_items');
		$this->db->select("
						A.io_lab_id,
						A.category_id,
						A.dDateTime,
						CASE
							WHEN A.category_id = {$radiology_cat} THEN COALESCE(NULLIF(TRIM(A.laboratory_text),''), ".($hasSonoItems ? "SI.item_name, " : "")." 'Unknown Test')
							WHEN A.category_id = {$sonography_cat} THEN COALESCE(NULLIF(TRIM(A.laboratory_text),''), ".($hasSonoItems ? "SI.item_name, " : "")." 'Unknown Test')
							ELSE COALESCE(NULLIF(TRIM(C.particular_name),''), NULLIF(TRIM(A.laboratory_text),''), ".($hasSonoItems ? "SI.item_name, " : "")." 'Unknown Test')
						END AS particular_name,
						".($hasSonoItems ? "SI.item_name AS sono_item_name,\n" : "NULL AS sono_item_name,\n").
						"B.group_name,
						A.doctor AS doctor_user_id,
						PD.doctor_id AS assigned_doctor_id,
						A.findings,
						A.result,
						A.laboratory_text,
						".($hasSonoMeta ? "M.meta_id AS sono_meta_id, M.scan_item_id AS sono_scan_item_id, M.clinical_question AS clinical_question,\n" : "NULL AS sono_meta_id, NULL AS sono_scan_item_id, NULL AS clinical_question,\n").
						"A.lab_result_upload,
						concat(E.cValue,' ',D.firstname,' ',D.middlename,' ',D.lastname) as doctor
						", false);
		$this->db->order_by("A.io_lab_id", "asc");
		$this->db->where(array(
			'A.iop_id'		=>	$iop_no,
			'A.InActive'	=>	0
		));
		$this->db->join("patient_details_iop PD", "PD.IO_ID = A.iop_id", "left outer");
		$this->db->join("bill_group_name B", "B.group_id = A.category_id", "left outer");
		$this->db->join(
			"bill_particular C",
			"C.particular_id = A.laboratory_id AND A.category_id != {$radiology_cat} AND A.category_id != {$sonography_cat}",
			"left outer",
			false
		);
		if ($hasSonoItems) {
			$this->db->join("sonography_items SI", "SI.item_id = A.laboratory_id", "left outer");
		}
		if ($hasSonoMeta) {
			$this->db->join("iop_sonography_request_meta M", "M.io_lab_id = A.io_lab_id AND M.InActive = 0", "left outer", false);
		}
		$this->db->join("users D", "D.user_id = A.doctor", "left outer");
		$this->db->join("system_parameters E", "E.param_id = D.title", "left outer");
		$query = $this->db->get("iop_laboratory A");
		return $query->result();
	}


	public function getLabRequests($postData)
	{

		$response = array();

		if (isset($postData['search'])) {
			// Select record
			$this->db->select('*');
			$this->db->where("labs like '%" . $postData['search'] . "%' ");

			$records = $this->db->get('labs_ext')->result();

			foreach ($records as $row) {
				$response[] = array("value" => $row->id, "label" => $row->labs);
			}
		}

		return $response;
	}


	public function getDiagnosis($postData)
	{

		$response = array();

		if (isset($postData['search'])) {
			// Select record
			$this->db->select('*');
			$this->db->where("diagnosis like '%" . $postData['search'] . "%' ");

			$records = $this->db->get('diagnosis_ext')->result();

			foreach ($records as $row) {
				$response[] = array("value" => $row->id, "label" => $row->diagnosis);
			}
		}

		return $response;
	}


	public function getMeds($postData)
	{

		$response = array();

		if (isset($postData['search']) && $this->table_exists('meds_ext')) {
			// Select record
			$this->db->select('*');
			$this->db->where("meds like '%" . $postData['search'] . "%' ");

			$records = $this->db->get('meds_ext')->result();

			foreach ($records as $row) {
				$response[] = array("value" => $row->id, "label" => $row->meds);
			}
		}

		return $response;
	}

	/**
	 * Ensure visit_closure_log table exists for audit trail
	 */
	public function ensure_visit_closure_schema()
	{
		if (!$this->table_exists('visit_closure_log')) {
			$sql = "CREATE TABLE visit_closure_log (
				id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
				iop_id VARCHAR(25) NOT NULL,
				patient_no VARCHAR(25) NOT NULL,
				patient_type VARCHAR(10) DEFAULT 'OPD',
				action VARCHAR(20) NOT NULL COMMENT 'CLOSED, REOPENED',
				reason VARCHAR(100) NOT NULL,
				notes TEXT,
				previous_status VARCHAR(20),
				new_status VARCHAR(20),
				performed_by VARCHAR(25) NOT NULL,
				performed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_iop_id (iop_id),
				INDEX idx_patient_no (patient_no),
				INDEX idx_action (action),
				INDEX idx_performed_at (performed_at)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
			$this->db->query($sql);
		}
	}

	/**
	 * Close an OPD visit with audit trail
	 */
	public function close_opd_visit($iop_id, $patient_no, $reason, $notes, $user_id)
	{
		$this->ensure_visit_closure_schema();
		
		// Get current status
		$current = $this->db->select('nStatus, patient_type')
			->where('IO_ID', $iop_id)
			->where('patient_no', $patient_no)
			->get('patient_details_iop')
			->row();
		
		if (!$current) {
			return false;
		}

		$this->db->trans_start();

		// Update status to Discharged
		$this->db->where('IO_ID', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->update('patient_details_iop', array(
			'nStatus' => 'Discharged'
		));

		// Log the closure
		$this->db->insert('visit_closure_log', array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'patient_type' => isset($current->patient_type) ? $current->patient_type : 'OPD',
			'action' => 'CLOSED',
			'reason' => $reason,
			'notes' => $notes,
			'previous_status' => $current->nStatus,
			'new_status' => 'Discharged',
			'performed_by' => $user_id,
			'performed_at' => date('Y-m-d H:i:s')
		));

		$this->db->trans_complete();
		return $this->db->trans_status();
	}

	/**
	 * Bulk close old pending visits
	 */
	public function bulk_close_old_visits($days_old, $reason, $user_id)
	{
		$this->ensure_visit_closure_schema();
		
		$cutoff_date = date('Y-m-d', strtotime("-{$days_old} days"));
		
		// Get all pending visits older than cutoff
		$old_visits = $this->db->select('IO_ID, patient_no, nStatus, patient_type')
			->where('nStatus', 'Pending')
			->where('date_visit <', $cutoff_date)
			->where('InActive', 0)
			->get('patient_details_iop')
			->result();

		$count = 0;
		foreach ($old_visits as $visit) {
			$this->db->trans_start();

			// Update status
			$this->db->where('IO_ID', $visit->IO_ID);
			$this->db->where('patient_no', $visit->patient_no);
			$this->db->update('patient_details_iop', array('nStatus' => 'Discharged'));

			// Log closure
			$this->db->insert('visit_closure_log', array(
				'iop_id' => $visit->IO_ID,
				'patient_no' => $visit->patient_no,
				'patient_type' => isset($visit->patient_type) ? $visit->patient_type : 'OPD',
				'action' => 'CLOSED',
				'reason' => $reason,
				'notes' => 'Bulk closure - visit date: ' . $cutoff_date,
				'previous_status' => $visit->nStatus,
				'new_status' => 'Discharged',
				'performed_by' => $user_id,
				'performed_at' => date('Y-m-d H:i:s')
			));

			$this->db->trans_complete();
			if ($this->db->trans_status()) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * Reopen a closed visit
	 */
	public function reopen_visit($iop_id, $patient_no, $user_id)
	{
		$this->ensure_visit_closure_schema();
		
		// Get current status
		$current = $this->db->select('nStatus, patient_type')
			->where('IO_ID', $iop_id)
			->where('patient_no', $patient_no)
			->get('patient_details_iop')
			->row();
		
		if (!$current || $current->nStatus !== 'Discharged') {
			return false;
		}

		$this->db->trans_start();

		// Update status back to Pending
		$this->db->where('IO_ID', $iop_id);
		$this->db->where('patient_no', $patient_no);
		$this->db->update('patient_details_iop', array(
			'nStatus' => 'Pending'
		));

		// Log the reopening
		$this->db->insert('visit_closure_log', array(
			'iop_id' => $iop_id,
			'patient_no' => $patient_no,
			'patient_type' => isset($current->patient_type) ? $current->patient_type : 'OPD',
			'action' => 'REOPENED',
			'reason' => 'Visit reopened by administrator',
			'notes' => '',
			'previous_status' => 'Discharged',
			'new_status' => 'Pending',
			'performed_by' => $user_id,
			'performed_at' => date('Y-m-d H:i:s')
		));

		$this->db->trans_complete();
		return $this->db->trans_status();
	}

	/**
	 * Get visit statistics for dashboard
	 */
	public function get_visit_statistics()
	{
		$today = date('Y-m-d');
		$week_ago = date('Y-m-d', strtotime('-7 days'));
		$month_ago = date('Y-m-d', strtotime('-30 days'));

		// Today's pending
		$today_pending = $this->db->where('nStatus', 'Pending')
			->where('date_visit', $today)
			->where('InActive', 0)
			->count_all_results('patient_details_iop');

		// Total pending
		$total_pending = $this->db->where('nStatus', 'Pending')
			->where('InActive', 0)
			->count_all_results('patient_details_iop');

		// Old pending (> 7 days)
		$old_pending = $this->db->where('nStatus', 'Pending')
			->where('date_visit <', $week_ago)
			->where('InActive', 0)
			->count_all_results('patient_details_iop');

		// Very old pending (> 30 days)
		$very_old_pending = $this->db->where('nStatus', 'Pending')
			->where('date_visit <', $month_ago)
			->where('InActive', 0)
			->count_all_results('patient_details_iop');

		// Today's discharged
		$today_discharged = $this->db->where('nStatus', 'Discharged')
			->where('date_visit', $today)
			->where('InActive', 0)
			->count_all_results('patient_details_iop');

		return array(
			'today_pending' => $today_pending,
			'total_pending' => $total_pending,
			'old_pending_7d' => $old_pending,
			'old_pending_30d' => $very_old_pending,
			'today_discharged' => $today_discharged
		);
	}

	/**
	 * Get closure reasons for dropdown
	 */
	public function get_closure_reasons()
	{
		return array(
			'completed' => 'Visit Completed - Patient Left',
			'abandoned' => 'Patient Abandoned Visit',
			'no_show' => 'Patient Did Not Show Up',
			'transferred' => 'Transferred to Another Facility',
			'deceased' => 'Patient Deceased',
			'duplicate' => 'Duplicate Entry - Error',
			'billing_cleared' => 'Billing Cleared - Auto Discharge',
			'admin_close' => 'Administrative Closure',
			'other' => 'Other (See Notes)'
		);
	}

	/**
	 * Get visit closure history
	 */
	public function get_closure_history($iop_id = null, $limit = 50)
	{
		$this->ensure_visit_closure_schema();
		
		$this->db->select('v.*, u.firstname, u.lastname')
			->from('visit_closure_log v')
			->join('users u', 'u.user_id = v.performed_by', 'left')
			->order_by('v.performed_at', 'DESC')
			->limit($limit);
		
		if ($iop_id !== null) {
			$this->db->where('v.iop_id', $iop_id);
		}
		
		return $this->db->get()->result();
	}

	public function getPatientDetailsByIopId($iop_id){
		$iop_id = (string)$iop_id;
		if ($iop_id === '') return null;
		$q = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'InActive' => 0));
		return $q ? $q->row() : null;
	}
}
