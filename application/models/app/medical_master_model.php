<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Medical Master Model
 * 
 * Handles master tables for medications, diagnosis, lab tests, sonography, ECG, X-ray,
 * lab result templates, doctor notifications, and smart autocomplete search.
 * All schema changes are idempotent (safe to call repeatedly).
 */
class Medical_master_model extends CI_Model
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

	private function index_exists($table, $index_name)
	{
		if (!$this->table_exists($table)) return false;
		$q = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", array($index_name));
		return ($q && $q->num_rows() > 0);
	}

	/* ================================================================== */
	/*  MASTER SCHEMA MIGRATION (idempotent)                               */
	/* ================================================================== */

	public function ensure_all_master_tables()
	{
		if ($this->schema_checked) return;
		$this->schema_checked = true;

		$this->ensure_lab_test_master();
		$this->ensure_lab_result_templates();
		$this->ensure_doctor_notifications();
		$this->ensure_medication_enhancements();
		$this->ensure_diagnosis_enhancements();
		$this->ensure_scan_master_enhancements();
	}

	/* ================================================================== */
	/*  1. LAB TEST MASTER TABLE                                           */
	/* ================================================================== */

	private function ensure_lab_test_master()
	{
		if (!$this->table_exists('lab_test_master')) {
			$this->db->query("CREATE TABLE `lab_test_master` (
				`test_id` int(11) NOT NULL AUTO_INCREMENT,
				`test_name` varchar(255) NOT NULL,
				`test_code` varchar(50) DEFAULT NULL,
				`category` varchar(100) DEFAULT NULL,
				`sample_type` varchar(100) DEFAULT NULL,
				`department` varchar(100) DEFAULT 'Laboratory',
				`description` text,
				`turn_around_time` varchar(50) DEFAULT NULL,
				`particular_id` int(11) DEFAULT NULL,
				`is_active` tinyint(1) NOT NULL DEFAULT 1,
				`is_custom` tinyint(1) NOT NULL DEFAULT 0,
				`created_by` varchar(25) DEFAULT NULL,
				`created_at` datetime DEFAULT NULL,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`test_id`),
				KEY `idx_name` (`test_name`(191)),
				KEY `idx_code` (`test_code`),
				KEY `idx_category` (`category`),
				KEY `idx_active` (`is_active`, `InActive`),
				KEY `idx_particular` (`particular_id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$this->seed_ghana_lab_tests();
		}
	}

	private function seed_ghana_lab_tests()
	{
		$tests = array(
			// Haematology
			array('Full Blood Count (FBC)', 'FBC', 'Haematology', 'Blood (EDTA)'),
			array('Haemoglobin Estimation', 'HB', 'Haematology', 'Blood (EDTA)'),
			array('Erythrocyte Sedimentation Rate', 'ESR', 'Haematology', 'Blood (EDTA)'),
			array('Blood Group & Rh', 'BG', 'Haematology', 'Blood (EDTA)'),
			array('Sickling Test', 'SICK', 'Haematology', 'Blood (EDTA)'),
			array('Hb Electrophoresis', 'HBE', 'Haematology', 'Blood (EDTA)'),
			array('Peripheral Blood Film', 'PBF', 'Haematology', 'Blood (EDTA)'),
			array('Clotting Time', 'CT', 'Haematology', 'Blood'),
			array('Bleeding Time', 'BT', 'Haematology', 'Blood'),
			array('Platelet Count', 'PLT', 'Haematology', 'Blood (EDTA)'),
			array('Reticulocyte Count', 'RETIC', 'Haematology', 'Blood (EDTA)'),
			array('Prothrombin Time / INR', 'PT/INR', 'Haematology', 'Blood (Citrate)'),
			// Clinical Chemistry
			array('Blood Sugar (Fasting)', 'FBS', 'Clinical Chemistry', 'Blood (Fluoride)'),
			array('Blood Sugar (Random)', 'RBS', 'Clinical Chemistry', 'Blood (Fluoride)'),
			array('Blood Sugar (2hr PP)', '2HPP', 'Clinical Chemistry', 'Blood (Fluoride)'),
			array('Oral Glucose Tolerance Test', 'OGTT', 'Clinical Chemistry', 'Blood (Fluoride)'),
			array('HbA1c (Glycated Haemoglobin)', 'HBA1C', 'Clinical Chemistry', 'Blood (EDTA)'),
			array('Lipid Profile', 'LIP', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Liver Function Test', 'LFT', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Kidney Function Test', 'KFT', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Serum Electrolytes', 'ELEC', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Uric Acid', 'UA', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Serum Calcium', 'CA', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Serum Protein', 'TP', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Bilirubin (Total & Direct)', 'BIL', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Amylase', 'AMY', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Thyroid Function Test', 'TFT', 'Clinical Chemistry', 'Blood (Plain)'),
			array('Prostate Specific Antigen', 'PSA', 'Clinical Chemistry', 'Blood (Plain)'),
			// Microbiology
			array('Malaria Parasite (MP)', 'MP', 'Microbiology', 'Blood (EDTA)'),
			array('Malaria RDT', 'MRDT', 'Microbiology', 'Blood'),
			array('Widal Test', 'WIDAL', 'Microbiology', 'Blood (Plain)'),
			array('Blood Culture & Sensitivity', 'BC', 'Microbiology', 'Blood'),
			array('Urine Culture & Sensitivity', 'UC', 'Microbiology', 'Urine'),
			array('Wound Swab Culture', 'WSC', 'Microbiology', 'Swab'),
			array('Stool Culture & Sensitivity', 'SC', 'Microbiology', 'Stool'),
			array('High Vaginal Swab', 'HVS', 'Microbiology', 'Swab'),
			// Urinalysis
			array('Urine Routine & Microscopy', 'URE', 'Urinalysis', 'Urine'),
			array('Urine Pregnancy Test', 'UPT', 'Urinalysis', 'Urine'),
			// Stool
			array('Stool Routine & Microscopy', 'SRE', 'Parasitology', 'Stool'),
			array('Stool Occult Blood', 'SOB', 'Parasitology', 'Stool'),
			// Serology
			array('Hepatitis B Surface Antigen', 'HBSAG', 'Serology', 'Blood (Plain)'),
			array('Hepatitis C Antibody', 'HCV', 'Serology', 'Blood (Plain)'),
			array('HIV 1 & 2 Screening', 'HIV', 'Serology', 'Blood (Plain)'),
			array('VDRL / RPR', 'VDRL', 'Serology', 'Blood (Plain)'),
			array('Rheumatoid Factor', 'RF', 'Serology', 'Blood (Plain)'),
			array('ASO Titre', 'ASO', 'Serology', 'Blood (Plain)'),
			array('CRP (C-Reactive Protein)', 'CRP', 'Serology', 'Blood (Plain)'),
			// CSF
			array('CSF Analysis', 'CSF', 'Clinical Chemistry', 'CSF'),
			// Semen
			array('Semen Analysis', 'SFA', 'Andrology', 'Semen'),
		);

		foreach ($tests as $t) {
			$this->db->insert('lab_test_master', array(
				'test_name'   => $t[0],
				'test_code'   => $t[1],
				'category'    => $t[2],
				'sample_type' => $t[3],
				'department'  => 'Laboratory',
				'is_active'   => 1,
				'is_custom'   => 0,
				'created_at'  => date('Y-m-d H:i:s'),
				'InActive'    => 0
			));
		}
	}

	/* ================================================================== */
	/*  2. LAB RESULT TEMPLATES                                            */
	/* ================================================================== */

	private function ensure_lab_result_templates()
	{
		if (!$this->table_exists('lab_result_templates')) {
			$this->db->query("CREATE TABLE `lab_result_templates` (
				`template_id` int(11) NOT NULL AUTO_INCREMENT,
				`test_name` varchar(255) NOT NULL,
				`parameter_name` varchar(255) NOT NULL,
				`unit` varchar(50) DEFAULT NULL,
				`normal_range_text` varchar(255) DEFAULT NULL,
				`normal_min` decimal(10,4) DEFAULT NULL,
				`normal_max` decimal(10,4) DEFAULT NULL,
				`result_options` text DEFAULT NULL,
				`color_rules` text DEFAULT NULL,
				`sort_order` int(3) NOT NULL DEFAULT 0,
				`is_active` tinyint(1) NOT NULL DEFAULT 1,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`template_id`),
				KEY `idx_test_name` (`test_name`(191)),
				KEY `idx_active` (`is_active`, `InActive`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

			$this->seed_ghana_lab_templates();
		}

		// Lab result entries table for structured results
		if (!$this->table_exists('lab_result_entries')) {
			$this->db->query("CREATE TABLE `lab_result_entries` (
				`entry_id` bigint(11) NOT NULL AUTO_INCREMENT,
				`io_lab_id` int(11) NOT NULL,
				`template_id` int(11) DEFAULT NULL,
				`parameter_name` varchar(255) NOT NULL,
				`result_value` varchar(255) DEFAULT NULL,
				`result_flag` varchar(20) DEFAULT NULL,
				`color_code` varchar(20) DEFAULT NULL,
				`unit` varchar(50) DEFAULT NULL,
				`normal_range` varchar(255) DEFAULT NULL,
				`notes` text DEFAULT NULL,
				`entered_by` varchar(25) DEFAULT NULL,
				`entered_at` datetime DEFAULT NULL,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`entry_id`),
				KEY `idx_io_lab` (`io_lab_id`),
				KEY `idx_template` (`template_id`),
				KEY `idx_flag_date` (`result_flag`, `entered_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}

		// Ensure index exists for flag+date queries
		if ($this->table_exists('lab_result_entries') && !$this->index_exists('lab_result_entries', 'idx_flag_date')) {
			$this->db->query("ALTER TABLE `lab_result_entries` ADD KEY `idx_flag_date` (`result_flag`, `entered_at`)");
		}

		// Add updated_by and updated_at columns for audit trail
		if ($this->table_exists('lab_result_entries') && !$this->column_exists('lab_result_entries', 'updated_by')) {
			$this->db->query("ALTER TABLE `lab_result_entries` ADD COLUMN `updated_by` varchar(25) DEFAULT NULL AFTER `entered_at`");
		}
		if ($this->table_exists('lab_result_entries') && !$this->column_exists('lab_result_entries', 'updated_at')) {
			$this->db->query("ALTER TABLE `lab_result_entries` ADD COLUMN `updated_at` datetime DEFAULT NULL AFTER `updated_by`");
		}

		// Remove problematic unique constraint if it exists (was causing duplicate key errors)
		if ($this->table_exists('lab_result_entries') && $this->index_exists('lab_result_entries', 'idx_unique_lab_param')) {
			$this->db->query("ALTER TABLE `lab_result_entries` DROP INDEX `idx_unique_lab_param`");
		}
		// Clean up any existing duplicates
		$this->cleanup_duplicate_structured_results();
	}

	private function cleanup_duplicate_structured_results()
	{
		// Find and archive duplicates - keep the latest entry_id for each io_lab_id + parameter_name
		$sql = "UPDATE lab_result_entries e1
				INNER JOIN (
					SELECT io_lab_id, parameter_name, MAX(entry_id) as keep_id
					FROM lab_result_entries
					WHERE InActive = 0
					GROUP BY io_lab_id, parameter_name
					HAVING COUNT(*) > 1
				) dups ON e1.io_lab_id = dups.io_lab_id 
					  AND e1.parameter_name = dups.parameter_name 
					  AND e1.entry_id != dups.keep_id
				SET e1.InActive = 1";
		$this->db->query($sql);
		log_message('info', 'LAB_STRUCTURED_RESULTS_DUPLICATES_CLEANED');
	}

	private function seed_ghana_lab_templates()
	{
		$templates = array(
			// MALARIA PARASITE
			array('Malaria Parasite (MP)', 'Malaria Parasite', '', '', null, null,
				'Negative,+,++,+++,++++',
				'{"Negative":"green","+":"yellow","++":"orange","+++":"red","++++":"red"}', 1),
			array('Malaria Parasite (MP)', 'Species', '', '', null, null,
				'P. falciparum,P. vivax,P. malariae,P. ovale,Mixed',
				'{}', 2),
			// BLOOD SUGAR (FBS)
			array('Blood Sugar (Fasting)', 'Fasting Blood Sugar', 'mmol/L', '4.0 - 6.0', 4.0, 6.0,
				'',
				'{"low":"blue","normal":"green","high":"orange","critical":"red"}', 1),
			// BLOOD SUGAR (RBS)
			array('Blood Sugar (Random)', 'Random Blood Sugar', 'mmol/L', '4.0 - 7.8', 4.0, 7.8,
				'',
				'{"low":"blue","normal":"green","high":"orange","critical":"red"}', 1),
			// FBC
			array('Full Blood Count (FBC)', 'WBC', 'x10^9/L', '4.0 - 11.0', 4.0, 11.0,
				'',
				'{"low":"blue","normal":"green","high":"orange","critical":"red"}', 1),
			array('Full Blood Count (FBC)', 'RBC', 'x10^12/L', '4.5 - 5.5 (M) / 4.0 - 5.0 (F)', 4.0, 5.5,
				'',
				'{"low":"blue","normal":"green","high":"orange"}', 2),
			array('Full Blood Count (FBC)', 'Haemoglobin', 'g/dL', '12.0 - 17.0 (M) / 11.0 - 15.0 (F)', 11.0, 17.0,
				'',
				'{"low":"blue","normal":"green","high":"orange","critical":"red"}', 3),
			array('Full Blood Count (FBC)', 'Haematocrit (PCV)', '%', '36 - 54 (M) / 33 - 47 (F)', 33, 54,
				'',
				'{"low":"blue","normal":"green","high":"orange"}', 4),
			array('Full Blood Count (FBC)', 'MCV', 'fL', '80 - 100', 80, 100,
				'',
				'{"low":"blue","normal":"green","high":"orange"}', 5),
			array('Full Blood Count (FBC)', 'MCH', 'pg', '27 - 33', 27, 33,
				'',
				'{"low":"blue","normal":"green","high":"orange"}', 6),
			array('Full Blood Count (FBC)', 'MCHC', 'g/dL', '32 - 36', 32, 36,
				'',
				'{"low":"blue","normal":"green","high":"orange"}', 7),
			array('Full Blood Count (FBC)', 'Platelet Count', 'x10^9/L', '150 - 400', 150, 400,
				'',
				'{"low":"blue","normal":"green","high":"orange","critical":"red"}', 8),
			array('Full Blood Count (FBC)', 'Neutrophils', '%', '40 - 70', 40, 70, '', '{"low":"blue","normal":"green","high":"orange"}', 9),
			array('Full Blood Count (FBC)', 'Lymphocytes', '%', '20 - 40', 20, 40, '', '{"low":"blue","normal":"green","high":"orange"}', 10),
			array('Full Blood Count (FBC)', 'Monocytes', '%', '2 - 8', 2, 8, '', '{"low":"blue","normal":"green","high":"orange"}', 11),
			array('Full Blood Count (FBC)', 'Eosinophils', '%', '1 - 4', 1, 4, '', '{"low":"blue","normal":"green","high":"orange"}', 12),
			array('Full Blood Count (FBC)', 'Basophils', '%', '0 - 1', 0, 1, '', '{"normal":"green","high":"orange"}', 13),
			// URINE R/E
			array('Urine Routine & Microscopy', 'Appearance', '', '', null, null,
				'Clear,Slightly Turbid,Turbid,Bloody',
				'{"Clear":"green","Slightly Turbid":"yellow","Turbid":"orange","Bloody":"red"}', 1),
			array('Urine Routine & Microscopy', 'Colour', '', '', null, null,
				'Pale Yellow,Yellow,Dark Yellow,Amber,Red/Brown',
				'{"Pale Yellow":"green","Yellow":"green","Dark Yellow":"yellow","Amber":"orange","Red/Brown":"red"}', 2),
			array('Urine Routine & Microscopy', 'pH', '', '5.0 - 8.0', 5.0, 8.0, '', '{"normal":"green","high":"orange","low":"blue"}', 3),
			array('Urine Routine & Microscopy', 'Specific Gravity', '', '1.005 - 1.030', 1.005, 1.030, '', '{"normal":"green","high":"orange","low":"blue"}', 4),
			array('Urine Routine & Microscopy', 'Protein', '', '', null, null,
				'Negative,Trace,+,++,+++',
				'{"Negative":"green","Trace":"yellow","+":"orange","++":"red","+++":"red"}', 5),
			array('Urine Routine & Microscopy', 'Glucose', '', '', null, null,
				'Negative,Trace,+,++,+++',
				'{"Negative":"green","Trace":"yellow","+":"orange","++":"red","+++":"red"}', 6),
			array('Urine Routine & Microscopy', 'Blood', '', '', null, null,
				'Negative,Trace,+,++,+++',
				'{"Negative":"green","Trace":"yellow","+":"orange","++":"red","+++":"red"}', 7),
			array('Urine Routine & Microscopy', 'Leucocytes', '', '', null, null,
				'Negative,Trace,+,++,+++',
				'{"Negative":"green","Trace":"yellow","+":"orange","++":"red","+++":"red"}', 8),
			array('Urine Routine & Microscopy', 'Nitrite', '', '', null, null,
				'Negative,Positive',
				'{"Negative":"green","Positive":"red"}', 9),
			array('Urine Routine & Microscopy', 'Pus Cells', '/HPF', '0 - 5', 0, 5, '', '{"normal":"green","high":"orange","critical":"red"}', 10),
			array('Urine Routine & Microscopy', 'RBCs', '/HPF', '0 - 2', 0, 2, '', '{"normal":"green","high":"orange","critical":"red"}', 11),
			array('Urine Routine & Microscopy', 'Epithelial Cells', '/HPF', '', null, null, 'Few,Moderate,Many', '{"Few":"green","Moderate":"yellow","Many":"orange"}', 12),
			array('Urine Routine & Microscopy', 'Casts', '', '', null, null, 'Nil,Hyaline,Granular,Cellular', '{"Nil":"green","Hyaline":"yellow","Granular":"orange","Cellular":"red"}', 13),
			array('Urine Routine & Microscopy', 'Crystals', '', '', null, null, 'Nil,Urate,Oxalate,Phosphate', '{"Nil":"green"}', 14),
			// STOOL R/E
			array('Stool Routine & Microscopy', 'Appearance', '', '', null, null,
				'Formed,Semi-formed,Loose,Watery,Mucoid,Bloody',
				'{"Formed":"green","Semi-formed":"yellow","Loose":"orange","Watery":"orange","Mucoid":"red","Bloody":"red"}', 1),
			array('Stool Routine & Microscopy', 'Colour', '', '', null, null,
				'Brown,Yellow,Green,Black,Red,Clay',
				'{"Brown":"green","Yellow":"yellow","Green":"orange","Black":"red","Red":"red","Clay":"orange"}', 2),
			array('Stool Routine & Microscopy', 'Ova', '', '', null, null,
				'Not Seen,Hookworm,Ascaris,Trichuris,Schistosoma',
				'{"Not Seen":"green"}', 3),
			array('Stool Routine & Microscopy', 'Cysts', '', '', null, null,
				'Not Seen,E. histolytica,Giardia,E. coli',
				'{"Not Seen":"green"}', 4),
			array('Stool Routine & Microscopy', 'Parasites', '', '', null, null,
				'Not Seen,Hookworm,Strongyloides,Tapeworm',
				'{"Not Seen":"green"}', 5),
			array('Stool Routine & Microscopy', 'Occult Blood', '', '', null, null,
				'Negative,Positive',
				'{"Negative":"green","Positive":"red"}', 6),
			array('Stool Routine & Microscopy', 'Pus Cells', '/HPF', '', null, null,
				'Nil,Few,Moderate,Many',
				'{"Nil":"green","Few":"yellow","Moderate":"orange","Many":"red"}', 7),
			array('Stool Routine & Microscopy', 'RBCs', '/HPF', '', null, null,
				'Nil,Few,Moderate,Many',
				'{"Nil":"green","Few":"yellow","Moderate":"orange","Many":"red"}', 8),
			// LIPID PROFILE
			array('Lipid Profile', 'Total Cholesterol', 'mmol/L', '< 5.2', null, 5.2, '', '{"normal":"green","high":"orange","critical":"red"}', 1),
			array('Lipid Profile', 'Triglycerides', 'mmol/L', '< 1.7', null, 1.7, '', '{"normal":"green","high":"orange","critical":"red"}', 2),
			array('Lipid Profile', 'HDL Cholesterol', 'mmol/L', '> 1.0 (M) / > 1.2 (F)', 1.0, null, '', '{"low":"blue","normal":"green"}', 3),
			array('Lipid Profile', 'LDL Cholesterol', 'mmol/L', '< 3.4', null, 3.4, '', '{"normal":"green","high":"orange","critical":"red"}', 4),
			// LFT
			array('Liver Function Test', 'Total Bilirubin', 'umol/L', '5 - 21', 5, 21, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 1),
			array('Liver Function Test', 'Direct Bilirubin', 'umol/L', '0 - 5', 0, 5, '', '{"normal":"green","high":"orange","critical":"red"}', 2),
			array('Liver Function Test', 'AST (SGOT)', 'U/L', '10 - 40', 10, 40, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 3),
			array('Liver Function Test', 'ALT (SGPT)', 'U/L', '7 - 56', 7, 56, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 4),
			array('Liver Function Test', 'ALP', 'U/L', '44 - 147', 44, 147, '', '{"low":"blue","normal":"green","high":"orange"}', 5),
			array('Liver Function Test', 'GGT', 'U/L', '9 - 48', 9, 48, '', '{"low":"blue","normal":"green","high":"orange"}', 6),
			array('Liver Function Test', 'Total Protein', 'g/L', '60 - 83', 60, 83, '', '{"low":"blue","normal":"green","high":"orange"}', 7),
			array('Liver Function Test', 'Albumin', 'g/L', '35 - 52', 35, 52, '', '{"low":"blue","normal":"green","high":"orange"}', 8),
			// KFT
			array('Kidney Function Test', 'Urea', 'mmol/L', '2.5 - 6.7', 2.5, 6.7, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 1),
			array('Kidney Function Test', 'Creatinine', 'umol/L', '62 - 106 (M) / 44 - 80 (F)', 44, 106, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 2),
			array('Kidney Function Test', 'eGFR', 'mL/min', '> 90', 90, null, '', '{"low":"red","normal":"green"}', 3),
			array('Kidney Function Test', 'Sodium', 'mmol/L', '136 - 145', 136, 145, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 4),
			array('Kidney Function Test', 'Potassium', 'mmol/L', '3.5 - 5.0', 3.5, 5.0, '', '{"low":"blue","normal":"green","high":"orange","critical":"red"}', 5),
			array('Kidney Function Test', 'Chloride', 'mmol/L', '98 - 106', 98, 106, '', '{"low":"blue","normal":"green","high":"orange"}', 6),
			array('Kidney Function Test', 'Bicarbonate', 'mmol/L', '22 - 29', 22, 29, '', '{"low":"blue","normal":"green","high":"orange"}', 7),
			// WIDAL
			array('Widal Test', 'Salmonella Typhi O', '', '', null, null,
				'Negative,1/20,1/40,1/80,1/160,1/320',
				'{"Negative":"green","1/20":"green","1/40":"yellow","1/80":"orange","1/160":"red","1/320":"red"}', 1),
			array('Widal Test', 'Salmonella Typhi H', '', '', null, null,
				'Negative,1/20,1/40,1/80,1/160,1/320',
				'{"Negative":"green","1/20":"green","1/40":"yellow","1/80":"orange","1/160":"red","1/320":"red"}', 2),
			array('Widal Test', 'Salmonella Paratyphi A-H', '', '', null, null,
				'Negative,1/20,1/40,1/80,1/160,1/320',
				'{"Negative":"green","1/20":"green","1/40":"yellow","1/80":"orange","1/160":"red","1/320":"red"}', 3),
			array('Widal Test', 'Salmonella Paratyphi B-H', '', '', null, null,
				'Negative,1/20,1/40,1/80,1/160,1/320',
				'{"Negative":"green","1/20":"green","1/40":"yellow","1/80":"orange","1/160":"red","1/320":"red"}', 4),
			// HIV
			array('HIV 1 & 2 Screening', 'HIV 1 & 2', '', '', null, null,
				'Non-Reactive,Reactive,Indeterminate',
				'{"Non-Reactive":"green","Reactive":"red","Indeterminate":"orange"}', 1),
			// HBsAg
			array('Hepatitis B Surface Antigen', 'HBsAg', '', '', null, null,
				'Negative,Positive',
				'{"Negative":"green","Positive":"red"}', 1),
			// HCV
			array('Hepatitis C Antibody', 'HCV Ab', '', '', null, null,
				'Negative,Positive',
				'{"Negative":"green","Positive":"red"}', 1),
			// VDRL
			array('VDRL / RPR', 'VDRL', '', '', null, null,
				'Non-Reactive,Reactive (Weak),Reactive',
				'{"Non-Reactive":"green","Reactive (Weak)":"orange","Reactive":"red"}', 1),
			// BLOOD GROUP
			array('Blood Group & Rh', 'ABO Group', '', '', null, null,
				'A,B,AB,O',
				'{}', 1),
			array('Blood Group & Rh', 'Rh Factor', '', '', null, null,
				'Positive,Negative',
				'{}', 2),
			// PREGNANCY TEST
			array('Urine Pregnancy Test', 'hCG', '', '', null, null,
				'Negative,Positive',
				'{"Negative":"green","Positive":"blue"}', 1),
			// HbA1c
			array('HbA1c (Glycated Haemoglobin)', 'HbA1c', '%', '4.0 - 5.6 (Normal)', 4.0, 5.6,
				'',
				'{"normal":"green","high":"orange","critical":"red"}', 1),
		);

		foreach ($templates as $t) {
			$this->db->insert('lab_result_templates', array(
				'test_name'        => $t[0],
				'parameter_name'   => $t[1],
				'unit'             => $t[2],
				'normal_range_text'=> $t[3],
				'normal_min'       => $t[4],
				'normal_max'       => $t[5],
				'result_options'   => $t[6],
				'color_rules'      => $t[7],
				'sort_order'       => $t[8],
				'is_active'        => 1,
				'InActive'         => 0
			));
		}
	}

	/* ================================================================== */
	/*  3. DOCTOR NOTIFICATIONS TABLE                                      */
	/* ================================================================== */

	private function ensure_doctor_notifications()
	{
		if (!$this->table_exists('doctor_notifications')) {
			$this->db->query("CREATE TABLE `doctor_notifications` (
				`notif_id` bigint(11) NOT NULL AUTO_INCREMENT,
				`doctor_id` varchar(25) NOT NULL,
				`patient_no` varchar(25) DEFAULT NULL,
				`iop_id` varchar(25) DEFAULT NULL,
				`io_lab_id` int(11) DEFAULT NULL,
				`notif_type` varchar(30) NOT NULL DEFAULT 'LAB_RESULT',
				`title` varchar(255) NOT NULL,
				`message` text,
				`is_read` tinyint(1) NOT NULL DEFAULT 0,
				`created_at` datetime NOT NULL,
				`read_at` datetime DEFAULT NULL,
				`InActive` int(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`notif_id`),
				KEY `idx_doctor` (`doctor_id`),
				KEY `idx_read` (`doctor_id`, `is_read`),
				KEY `idx_created` (`created_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
	}

	/* ================================================================== */
	/*  4. MEDICATION ENHANCEMENTS                                         */
	/* ================================================================== */

	private function ensure_medication_enhancements()
	{
		if (!$this->table_exists('medicine_drug_name')) return;

		if (!$this->column_exists('medicine_drug_name', 'is_custom')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `is_custom` tinyint(1) NOT NULL DEFAULT 0");
		}
		if (!$this->column_exists('medicine_drug_name', 'created_by')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `created_by` varchar(25) DEFAULT NULL");
		}

		// Seed Ghana medications if not enough exist
		$q = $this->db->query("SELECT COUNT(*) AS c FROM medicine_drug_name WHERE InActive = 0");
		$r = $q ? $q->row() : null;
		if ($r && (int)$r->c < 20) {
			$this->seed_ghana_medications();
		}
	}

	private function seed_ghana_medications()
	{
		$catField = null;
		if ($this->column_exists('medicine_drug_name', 'med_cat_id')) {
			$catField = 'med_cat_id';
		} elseif ($this->column_exists('medicine_drug_name', 'cat_id')) {
			$catField = 'cat_id';
		}
		$hasCType = $this->column_exists('medicine_drug_name', 'cType');
		$cTypeDefault = '';
		if ($hasCType) {
			$colQ = $this->db->query("SHOW COLUMNS FROM `medicine_drug_name` LIKE 'cType'");
			$col = ($colQ && $colQ->row()) ? $colQ->row() : null;
			$colType = ($col && isset($col->Type)) ? strtolower((string)$col->Type) : '';
			if ($colType !== '' && (strpos($colType, 'int') !== false || strpos($colType, 'decimal') !== false || strpos($colType, 'float') !== false || strpos($colType, 'double') !== false)) {
				$cTypeDefault = 0;
			}
		}
		$hasUom = $this->column_exists('medicine_drug_name', 'uom');
		$uomDefault = 'PCS';
		if ($hasUom) {
			$uomQ = $this->db->query("SHOW COLUMNS FROM `medicine_drug_name` LIKE 'uom'");
			$uomCol = ($uomQ && $uomQ->row()) ? $uomQ->row() : null;
			$uomType = ($uomCol && isset($uomCol->Type)) ? strtolower((string)$uomCol->Type) : '';
			if ($uomType !== '' && (strpos($uomType, 'int') !== false || strpos($uomType, 'decimal') !== false || strpos($uomType, 'float') !== false || strpos($uomType, 'double') !== false)) {
				$uomDefault = 0;
			}
		}
		$hasReorder = $this->column_exists('medicine_drug_name', 're_order_level');
		$reorderDefault = 0;
		if ($hasReorder) {
			$reQ = $this->db->query("SHOW COLUMNS FROM `medicine_drug_name` LIKE 're_order_level'");
			$reCol = ($reQ && $reQ->row()) ? $reQ->row() : null;
			$reType = ($reCol && isset($reCol->Type)) ? strtolower((string)$reCol->Type) : '';
			if ($reType !== '' && !(strpos($reType, 'int') !== false || strpos($reType, 'decimal') !== false || strpos($reType, 'float') !== false || strpos($reType, 'double') !== false)) {
				$reorderDefault = '';
			}
		}

		$meds = array(
			// name, generic, strength, form, category
			array('Paracetamol 500mg', 'Paracetamol', '500mg', 'Tablet', 'Analgesics'),
			array('Paracetamol Syrup', 'Paracetamol', '120mg/5ml', 'Syrup', 'Analgesics'),
			array('Paracetamol Injection', 'Paracetamol', '1g/100ml', 'Injection', 'Analgesics'),
			array('Ibuprofen 400mg', 'Ibuprofen', '400mg', 'Tablet', 'NSAIDs'),
			array('Diclofenac 50mg', 'Diclofenac', '50mg', 'Tablet', 'NSAIDs'),
			array('Diclofenac Injection', 'Diclofenac', '75mg/3ml', 'Injection', 'NSAIDs'),
			array('Artemether-Lumefantrine (Coartem)', 'Artemether-Lumefantrine', '20/120mg', 'Tablet', 'Antimalarials'),
			array('Artesunate Injection', 'Artesunate', '60mg', 'Injection', 'Antimalarials'),
			array('Artesunate-Amodiaquine', 'Artesunate-Amodiaquine', '100/270mg', 'Tablet', 'Antimalarials'),
			array('Amoxicillin 500mg', 'Amoxicillin', '500mg', 'Capsule', 'Antibiotics'),
			array('Amoxicillin Syrup', 'Amoxicillin', '250mg/5ml', 'Syrup', 'Antibiotics'),
			array('Amoxicillin-Clavulanate (Augmentin)', 'Amoxicillin-Clavulanate', '625mg', 'Tablet', 'Antibiotics'),
			array('Ciprofloxacin 500mg', 'Ciprofloxacin', '500mg', 'Tablet', 'Antibiotics'),
			array('Metronidazole 400mg', 'Metronidazole', '400mg', 'Tablet', 'Antibiotics'),
			array('Metronidazole Infusion', 'Metronidazole', '500mg/100ml', 'Infusion', 'Antibiotics'),
			array('Ceftriaxone 1g', 'Ceftriaxone', '1g', 'Injection', 'Antibiotics'),
			array('Gentamicin 80mg', 'Gentamicin', '80mg/2ml', 'Injection', 'Antibiotics'),
			array('Azithromycin 500mg', 'Azithromycin', '500mg', 'Tablet', 'Antibiotics'),
			array('Doxycycline 100mg', 'Doxycycline', '100mg', 'Capsule', 'Antibiotics'),
			array('Erythromycin 500mg', 'Erythromycin', '500mg', 'Tablet', 'Antibiotics'),
			array('Omeprazole 20mg', 'Omeprazole', '20mg', 'Capsule', 'GI Drugs'),
			array('Ranitidine 150mg', 'Ranitidine', '150mg', 'Tablet', 'GI Drugs'),
			array('Metformin 500mg', 'Metformin', '500mg', 'Tablet', 'Antidiabetics'),
			array('Metformin 850mg', 'Metformin', '850mg', 'Tablet', 'Antidiabetics'),
			array('Glibenclamide 5mg', 'Glibenclamide', '5mg', 'Tablet', 'Antidiabetics'),
			array('Insulin (Mixtard)', 'Insulin Mixtard', '100IU/ml', 'Injection', 'Antidiabetics'),
			array('Insulin (Actrapid)', 'Insulin Actrapid', '100IU/ml', 'Injection', 'Antidiabetics'),
			array('Amlodipine 5mg', 'Amlodipine', '5mg', 'Tablet', 'Antihypertensives'),
			array('Amlodipine 10mg', 'Amlodipine', '10mg', 'Tablet', 'Antihypertensives'),
			array('Lisinopril 10mg', 'Lisinopril', '10mg', 'Tablet', 'Antihypertensives'),
			array('Losartan 50mg', 'Losartan', '50mg', 'Tablet', 'Antihypertensives'),
			array('Hydrochlorothiazide 25mg', 'Hydrochlorothiazide', '25mg', 'Tablet', 'Antihypertensives'),
			array('Atenolol 50mg', 'Atenolol', '50mg', 'Tablet', 'Antihypertensives'),
			array('Nifedipine 20mg', 'Nifedipine', '20mg', 'Tablet', 'Antihypertensives'),
			array('Salbutamol Inhaler', 'Salbutamol', '100mcg', 'Inhaler', 'Respiratory'),
			array('Salbutamol Nebules', 'Salbutamol', '2.5mg/2.5ml', 'Nebule', 'Respiratory'),
			array('Prednisolone 5mg', 'Prednisolone', '5mg', 'Tablet', 'Corticosteroids'),
			array('Hydrocortisone 100mg', 'Hydrocortisone', '100mg', 'Injection', 'Corticosteroids'),
			array('Dexamethasone 4mg', 'Dexamethasone', '4mg/ml', 'Injection', 'Corticosteroids'),
			array('ORS (Oral Rehydration Salts)', 'ORS', '20.5g/L', 'Sachet', 'Rehydration'),
			array('Zinc Sulphate 20mg', 'Zinc Sulphate', '20mg', 'Tablet', 'Micronutrients'),
			array('Ferrous Sulphate 200mg', 'Ferrous Sulphate', '200mg', 'Tablet', 'Micronutrients'),
			array('Folic Acid 5mg', 'Folic Acid', '5mg', 'Tablet', 'Micronutrients'),
			array('Vitamin B Complex', 'Vitamin B Complex', '', 'Tablet', 'Micronutrients'),
			array('IV Normal Saline 0.9%', 'Sodium Chloride', '0.9%', 'IV Fluid', 'IV Fluids'),
			array('IV Ringers Lactate', 'Ringers Lactate', '500ml', 'IV Fluid', 'IV Fluids'),
			array('IV Dextrose 5%', 'Dextrose', '5%', 'IV Fluid', 'IV Fluids'),
			array('IV Dextrose Saline', 'Dextrose Saline', '500ml', 'IV Fluid', 'IV Fluids'),
			array('Tramadol 50mg', 'Tramadol', '50mg', 'Capsule', 'Analgesics'),
			array('Promethazine 25mg', 'Promethazine', '25mg', 'Tablet', 'Antihistamines'),
			array('Chlorpheniramine 4mg', 'Chlorpheniramine', '4mg', 'Tablet', 'Antihistamines'),
			array('Hyoscine Butylbromide (Buscopan)', 'Hyoscine Butylbromide', '10mg', 'Tablet', 'Antispasmodics'),
		);

		// Get category mapping
		$catMap = array();
		$q = $this->db->query("SELECT cat_id, med_category_name FROM medicine_category WHERE InActive = 0");
		if ($q) {
			foreach ($q->result() as $c) {
				$catMap[strtolower(trim($c->med_category_name))] = $c->cat_id;
			}
		}

		// Ensure categories exist (some installs require a non-null med_cat_id)
		foreach ($meds as $m) {
			$catKey = strtolower(trim($m[4]));
			if ($catKey === '') continue;
			if (!isset($catMap[$catKey]) && $this->table_exists('medicine_category')) {
				$this->db->insert('medicine_category', array(
					'med_category_name' => $m[4],
					'InActive' => 0
				));
				$newId = $this->db->insert_id();
				if ($newId) {
					$catMap[$catKey] = $newId;
				}
			}
		}

		$defaultCatId = null;
		if (count($catMap) > 0) {
			$vals = array_values($catMap);
			$defaultCatId = (int)$vals[0];
		}

		foreach ($meds as $m) {
			// Skip if already exists
			$chk = $this->db->query("SELECT drug_id FROM medicine_drug_name WHERE drug_name = ? AND InActive = 0 LIMIT 1", array($m[0]));
			if ($chk && $chk->num_rows() > 0) continue;

			$catId = null;
			$catKey = strtolower(trim($m[4]));
			if (isset($catMap[$catKey])) {
				$catId = $catMap[$catKey];
			}

			$data = array(
				'drug_name'    => $m[0],
				'generic_name' => $m[1],
				'strength'     => $m[2],
				'dosage_form'  => $m[3],
				'nPrice'       => 0,
				'nStock'       => 0,
				'InActive'     => 0,
				'is_custom'    => 0
			);
			if ($hasCType) {
				$data['cType'] = $cTypeDefault;
			}
			if ($hasUom) {
				$data['uom'] = $uomDefault;
			}
			if ($hasReorder) {
				$data['re_order_level'] = $reorderDefault;
			}
			if ($catField !== null) {
				if ($catId) {
					$data[$catField] = $catId;
				} elseif ($defaultCatId) {
					$data[$catField] = $defaultCatId;
				}
			}
			$this->db->insert('medicine_drug_name', $data);
		}
	}

	/* ================================================================== */
	/*  5. DIAGNOSIS ENHANCEMENTS                                          */
	/* ================================================================== */

	private function ensure_diagnosis_enhancements()
	{
		if (!$this->table_exists('diagnosis')) return;

		if (!$this->column_exists('diagnosis', 'is_custom')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `is_custom` tinyint(1) NOT NULL DEFAULT 0");
		}
		if (!$this->column_exists('diagnosis', 'created_by')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `created_by` varchar(25) DEFAULT NULL");
		}
		if (!$this->column_exists('diagnosis', 'icd_code')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `icd_code` varchar(20) DEFAULT NULL");
		}
		if (!$this->column_exists('diagnosis', 'category')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `category` varchar(100) DEFAULT NULL");
		}
		if (!$this->column_exists('diagnosis', 'is_active')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `is_active` tinyint(1) NOT NULL DEFAULT 1");
		}
		if (!$this->column_exists('diagnosis', 'common_treatment')) {
			$this->db->query("ALTER TABLE `diagnosis` ADD COLUMN `common_treatment` text DEFAULT NULL");
		}

		// Add icd_code to iop_diagnosis for record-level storage
		if ($this->table_exists('iop_diagnosis') && !$this->column_exists('iop_diagnosis', 'icd_code')) {
			$this->db->query("ALTER TABLE `iop_diagnosis` ADD COLUMN `icd_code` varchar(20) DEFAULT NULL AFTER `diagnosis_id`");
		}

		// Add FULLTEXT index for fast search if not yet added
		if (!$this->index_exists('diagnosis', 'ft_diag_search')) {
			$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
			if ($old_debug !== null) $this->db->db_debug = false;
			$this->db->query("ALTER TABLE `diagnosis` ADD FULLTEXT KEY `ft_diag_search` (`diagnosis_name`, `icd_code`, `category`)");
			if ($old_debug !== null) $this->db->db_debug = $old_debug;
		}

		// Seed Ghana NHIS ICD-10 diagnoses if fewer than 150 exist
		$q = $this->db->query("SELECT COUNT(*) c FROM diagnosis WHERE InActive=0");
		$cnt = $q ? (int)$q->row()->c : 0;
		if ($cnt < 150) {
			$this->seed_ghana_icd10_diagnoses();
		}
	}

	private function seed_ghana_icd10_diagnoses()
	{
		// Comprehensive Ghana Health Service / NHIS aligned ICD-10 diagnoses
		// Format: [icd_code, diagnosis_name, category]
		$diagnoses = array(
			// Infectious & Parasitic
			array('A00',  'Cholera',                                       'Infectious & Parasitic'),
			array('A01.0','Typhoid Fever',                                  'Infectious & Parasitic'),
			array('A02',  'Salmonella Infection',                           'Infectious & Parasitic'),
			array('A06',  'Amoebiasis',                                     'Infectious & Parasitic'),
			array('A06.0','Acute Amoebic Dysentery',                        'Infectious & Parasitic'),
			array('A09',  'Diarrhoea & Gastroenteritis',                    'Infectious & Parasitic'),
			array('A15',  'Respiratory Tuberculosis (TB)',                   'Infectious & Parasitic'),
			array('A16',  'Pulmonary Tuberculosis (Unconfirmed)',            'Infectious & Parasitic'),
			array('A17',  'Tuberculosis of Nervous System',                  'Infectious & Parasitic'),
			array('A18',  'Tuberculosis - Other Organs',                    'Infectious & Parasitic'),
			array('A20',  'Plague',                                         'Infectious & Parasitic'),
			array('A27',  'Leptospirosis',                                  'Infectious & Parasitic'),
			array('A33',  'Neonatal Tetanus',                               'Infectious & Parasitic'),
			array('A34',  'Obstetrical Tetanus',                            'Infectious & Parasitic'),
			array('A35',  'Other Tetanus',                                  'Infectious & Parasitic'),
			array('A36',  'Diphtheria',                                     'Infectious & Parasitic'),
			array('A37',  'Whooping Cough (Pertussis)',                     'Infectious & Parasitic'),
			array('A40',  'Streptococcal Sepsis',                           'Infectious & Parasitic'),
			array('A41',  'Septicaemia',                                    'Infectious & Parasitic'),
			array('A46',  'Erysipelas',                                     'Infectious & Parasitic'),
			array('A48.3','Toxic Shock Syndrome',                           'Infectious & Parasitic'),
			array('A49',  'Bacterial Infection (Unspecified)',               'Infectious & Parasitic'),
			array('A50',  'Congenital Syphilis',                            'Infectious & Parasitic'),
			array('A51',  'Early Syphilis',                                 'Infectious & Parasitic'),
			array('A53',  'Other and Unspecified Syphilis',                 'Infectious & Parasitic'),
			array('A54',  'Gonococcal Infection',                           'Infectious & Parasitic'),
			array('A59',  'Trichomoniasis',                                 'Infectious & Parasitic'),
			array('A60',  'Anogenital Herpesviral Infection',               'Infectious & Parasitic'),
			array('A63',  'Other Predominantly Sexually Transmitted Diseases','Infectious & Parasitic'),
			array('A64',  'Sexually Transmitted Disease (Unspecified)',      'Infectious & Parasitic'),
			array('A69',  'Other Spirochaetal Infections',                  'Infectious & Parasitic'),
			array('A74',  'Other Diseases Caused by Chlamydiae',            'Infectious & Parasitic'),
			array('A77',  'Spotted Fever (Tick-borne Rickettsioses)',       'Infectious & Parasitic'),
			array('A80',  'Acute Poliomyelitis',                            'Infectious & Parasitic'),
			array('A82',  'Rabies',                                         'Infectious & Parasitic'),
			array('A84',  'Tick-borne Viral Encephalitis',                  'Infectious & Parasitic'),
			array('A90',  'Dengue Fever',                                   'Infectious & Parasitic'),
			array('A91',  'Dengue Haemorrhagic Fever',                      'Infectious & Parasitic'),
			array('A96',  'Arenaviral Haemorrhagic Fever',                  'Infectious & Parasitic'),
			array('B00',  'Herpes Simplex Infection',                       'Infectious & Parasitic'),
			array('B01',  'Varicella (Chickenpox)',                         'Infectious & Parasitic'),
			array('B02',  'Zoster (Herpes Zoster / Shingles)',              'Infectious & Parasitic'),
			array('B05',  'Measles',                                        'Infectious & Parasitic'),
			array('B06',  'Rubella',                                        'Infectious & Parasitic'),
			array('B07',  'Viral Warts',                                    'Infectious & Parasitic'),
			array('B15',  'Acute Hepatitis A',                              'Infectious & Parasitic'),
			array('B16',  'Acute Hepatitis B',                              'Infectious & Parasitic'),
			array('B18',  'Chronic Viral Hepatitis',                        'Infectious & Parasitic'),
			array('B19',  'Viral Hepatitis (Unspecified)',                   'Infectious & Parasitic'),
			array('B20',  'HIV Disease with Infectious & Parasitic Disease', 'Infectious & Parasitic'),
			array('B24',  'HIV Disease (Unspecified)',                       'Infectious & Parasitic'),
			array('B34',  'Viral Infection (Unspecified)',                   'Infectious & Parasitic'),
			array('B37',  'Candidiasis',                                    'Infectious & Parasitic'),
			array('B44',  'Aspergillosis',                                  'Infectious & Parasitic'),
			array('B50',  'Plasmodium Falciparum Malaria',                  'Infectious & Parasitic'),
			array('B51',  'Plasmodium Vivax Malaria',                       'Infectious & Parasitic'),
			array('B53',  'Other Parasitologically Confirmed Malaria',      'Infectious & Parasitic'),
			array('B54',  'Malaria (Unspecified)',                           'Infectious & Parasitic'),
			array('B55',  'Leishmaniasis',                                  'Infectious & Parasitic'),
			array('B56',  'African Trypanosomiasis (Sleeping Sickness)',    'Infectious & Parasitic'),
			array('B57',  'Chagas Disease',                                 'Infectious & Parasitic'),
			array('B65',  'Schistosomiasis (Bilharzia)',                    'Infectious & Parasitic'),
			array('B66',  'Other Fluke Infections',                         'Infectious & Parasitic'),
			array('B73',  'Onchocerciasis (River Blindness)',               'Infectious & Parasitic'),
			array('B74',  'Filariasis',                                     'Infectious & Parasitic'),
			array('B76',  'Hookworm Disease (Ancylostomiasis)',             'Infectious & Parasitic'),
			array('B77',  'Ascariasis (Roundworm)',                         'Infectious & Parasitic'),
			array('B79',  'Trichuriasis (Whipworm)',                        'Infectious & Parasitic'),
			array('B80',  'Enterobiasis (Pinworm)',                         'Infectious & Parasitic'),
			array('B87',  'Myiasis',                                        'Infectious & Parasitic'),
			// Neoplasms
			array('C00',  'Malignant Neoplasm of Lip',                      'Neoplasms'),
			array('C15',  'Malignant Neoplasm of Oesophagus',               'Neoplasms'),
			array('C16',  'Malignant Neoplasm of Stomach',                  'Neoplasms'),
			array('C18',  'Malignant Neoplasm of Colon',                    'Neoplasms'),
			array('C20',  'Malignant Neoplasm of Rectum',                   'Neoplasms'),
			array('C22',  'Malignant Neoplasm of Liver',                    'Neoplasms'),
			array('C34',  'Malignant Neoplasm of Bronchus & Lung',          'Neoplasms'),
			array('C43',  'Malignant Melanoma of Skin',                     'Neoplasms'),
			array('C50',  'Malignant Neoplasm of Breast',                   'Neoplasms'),
			array('C53',  'Malignant Neoplasm of Cervix Uteri',             'Neoplasms'),
			array('C54',  'Malignant Neoplasm of Corpus Uteri',             'Neoplasms'),
			array('C56',  'Malignant Neoplasm of Ovary',                    'Neoplasms'),
			array('C61',  'Malignant Neoplasm of Prostate',                 'Neoplasms'),
			array('C67',  'Malignant Neoplasm of Bladder',                  'Neoplasms'),
			array('C71',  'Malignant Neoplasm of Brain',                    'Neoplasms'),
			array('C73',  'Malignant Neoplasm of Thyroid',                  'Neoplasms'),
			array('C80',  'Malignant Neoplasm (Unspecified)',                'Neoplasms'),
			array('C91',  'Lymphoid Leukaemia',                             'Neoplasms'),
			array('C92',  'Myeloid Leukaemia',                              'Neoplasms'),
			array('D25',  'Uterine Fibroids (Leiomyoma)',                   'Neoplasms'),
			array('D50',  'Iron Deficiency Anaemia',                        'Blood & Blood-forming Organs'),
			array('D51',  'Vitamin B12 Deficiency Anaemia',                 'Blood & Blood-forming Organs'),
			array('D52',  'Folate Deficiency Anaemia',                      'Blood & Blood-forming Organs'),
			array('D57',  'Sickle-Cell Disorders',                          'Blood & Blood-forming Organs'),
			array('D64',  'Other Anaemias',                                 'Blood & Blood-forming Organs'),
			// Endocrine
			array('E10',  'Type 1 Diabetes Mellitus',                       'Endocrine & Metabolic'),
			array('E11',  'Type 2 Diabetes Mellitus',                       'Endocrine & Metabolic'),
			array('E11.2','Type 2 Diabetes with Renal Complications',       'Endocrine & Metabolic'),
			array('E11.3','Type 2 Diabetes with Ophthalmic Complications',  'Endocrine & Metabolic'),
			array('E11.4','Type 2 Diabetes with Neurological Complications','Endocrine & Metabolic'),
			array('E13',  'Other Specified Diabetes Mellitus',               'Endocrine & Metabolic'),
			array('E14',  'Diabetes Mellitus (Unspecified)',                 'Endocrine & Metabolic'),
			array('E03',  'Hypothyroidism',                                 'Endocrine & Metabolic'),
			array('E05',  'Thyrotoxicosis (Hyperthyroidism)',               'Endocrine & Metabolic'),
			array('E21',  'Hyperparathyroidism',                            'Endocrine & Metabolic'),
			array('E27',  'Adrenal Disorders',                              'Endocrine & Metabolic'),
			array('E40',  'Kwashiorkor',                                    'Endocrine & Metabolic'),
			array('E41',  'Nutritional Marasmus',                           'Endocrine & Metabolic'),
			array('E43',  'Severe Protein-Energy Malnutrition',             'Endocrine & Metabolic'),
			array('E46',  'Protein-Energy Malnutrition (Unspecified)',      'Endocrine & Metabolic'),
			array('E50',  'Vitamin A Deficiency',                           'Endocrine & Metabolic'),
			array('E55',  'Vitamin D Deficiency (Rickets)',                 'Endocrine & Metabolic'),
			array('E66',  'Obesity',                                        'Endocrine & Metabolic'),
			array('E78',  'Hyperlipidaemia / Dyslipidaemia',               'Endocrine & Metabolic'),
			array('E83',  'Disorders of Mineral Metabolism',               'Endocrine & Metabolic'),
			array('E86',  'Dehydration',                                    'Endocrine & Metabolic'),
			array('E87',  'Electrolyte Imbalance',                          'Endocrine & Metabolic'),
			// Mental & Behavioural
			array('F00',  'Dementia in Alzheimer Disease',                  'Mental & Behavioural'),
			array('F05',  'Delirium',                                       'Mental & Behavioural'),
			array('F10',  'Alcohol Use Disorder',                           'Mental & Behavioural'),
			array('F19',  'Mental Disorders due to Substance Use',          'Mental & Behavioural'),
			array('F20',  'Schizophrenia',                                  'Mental & Behavioural'),
			array('F25',  'Schizoaffective Disorder',                       'Mental & Behavioural'),
			array('F31',  'Bipolar Affective Disorder',                     'Mental & Behavioural'),
			array('F32',  'Depressive Episode',                             'Mental & Behavioural'),
			array('F33',  'Recurrent Depressive Disorder',                  'Mental & Behavioural'),
			array('F40',  'Phobic Anxiety Disorders',                       'Mental & Behavioural'),
			array('F41',  'Anxiety Disorder',                               'Mental & Behavioural'),
			array('F43',  'Adjustment Disorder / Stress Reaction',          'Mental & Behavioural'),
			array('F51',  'Sleep Disorder',                                 'Mental & Behavioural'),
			array('F70',  'Mild Intellectual Disability',                   'Mental & Behavioural'),
			array('F84',  'Autism Spectrum Disorder',                       'Mental & Behavioural'),
			// Nervous System
			array('G00',  'Bacterial Meningitis',                           'Nervous System'),
			array('G03',  'Meningitis (Other & Unspecified)',               'Nervous System'),
			array('G20',  'Parkinson Disease',                              'Nervous System'),
			array('G35',  'Multiple Sclerosis',                             'Nervous System'),
			array('G40',  'Epilepsy',                                       'Nervous System'),
			array('G43',  'Migraine',                                       'Nervous System'),
			array('G44',  'Headache Syndrome',                              'Nervous System'),
			array('G45',  'Transient Cerebral Ischaemic Attack (TIA)',      'Nervous System'),
			array('G47',  'Sleep Disorders',                                'Nervous System'),
			array('G51',  'Facial Nerve Palsy (Bell Palsy)',               'Nervous System'),
			array('G54',  'Nerve Root & Plexus Disorders',                 'Nervous System'),
			array('G62',  'Peripheral Neuropathy',                          'Nervous System'),
			// Eye & Adnexa
			array('H10',  'Conjunctivitis',                                 'Eye & Adnexa'),
			array('H26',  'Cataract',                                       'Eye & Adnexa'),
			array('H35',  'Retinal Disorders',                              'Eye & Adnexa'),
			array('H40',  'Glaucoma',                                       'Eye & Adnexa'),
			array('H54',  'Blindness & Low Vision',                         'Eye & Adnexa'),
			// Ear
			array('H60',  'Otitis Externa',                                 'Ear & Mastoid'),
			array('H65',  'Otitis Media (Non-Suppurative)',                 'Ear & Mastoid'),
			array('H66',  'Suppurative & Unspecified Otitis Media',        'Ear & Mastoid'),
			array('H72',  'Perforated Eardrum',                             'Ear & Mastoid'),
			array('H81',  'Vestibular Dysfunction (Vertigo)',               'Ear & Mastoid'),
			// Circulatory
			array('I05',  'Rheumatic Mitral Valve Disease',                 'Circulatory'),
			array('I10',  'Hypertension (Essential)',                       'Circulatory'),
			array('I11',  'Hypertensive Heart Disease',                     'Circulatory'),
			array('I12',  'Hypertensive Renal Disease',                     'Circulatory'),
			array('I13',  'Hypertensive Heart and Renal Disease',           'Circulatory'),
			array('I20',  'Angina Pectoris',                                'Circulatory'),
			array('I21',  'Acute Myocardial Infarction',                   'Circulatory'),
			array('I25',  'Chronic Ischaemic Heart Disease',                'Circulatory'),
			array('I26',  'Pulmonary Embolism',                             'Circulatory'),
			array('I27',  'Pulmonary Heart Disease',                        'Circulatory'),
			array('I33',  'Acute Endocarditis',                             'Circulatory'),
			array('I38',  'Rheumatic Endocarditis',                         'Circulatory'),
			array('I42',  'Cardiomyopathy',                                 'Circulatory'),
			array('I48',  'Atrial Fibrillation & Flutter',                  'Circulatory'),
			array('I50',  'Heart Failure',                                  'Circulatory'),
			array('I60',  'Subarachnoid Haemorrhage',                      'Circulatory'),
			array('I61',  'Intracerebral Haemorrhage',                      'Circulatory'),
			array('I63',  'Cerebral Infarction (Ischaemic Stroke)',        'Circulatory'),
			array('I64',  'Stroke (Not Specified as Haemorrhage/Infarction)','Circulatory'),
			array('I70',  'Atherosclerosis',                                'Circulatory'),
			array('I80',  'Deep Vein Thrombosis (DVT)',                    'Circulatory'),
			array('I83',  'Varicose Veins of Lower Extremities',           'Circulatory'),
			// Respiratory
			array('J00',  'Acute Nasopharyngitis (Common Cold)',            'Respiratory'),
			array('J01',  'Acute Sinusitis',                                'Respiratory'),
			array('J02',  'Acute Pharyngitis (Sore Throat)',               'Respiratory'),
			array('J03',  'Acute Tonsillitis',                              'Respiratory'),
			array('J04',  'Acute Laryngitis & Tracheitis',                 'Respiratory'),
			array('J06',  'Acute Upper Respiratory Infection',              'Respiratory'),
			array('J10',  'Influenza (Seasonal)',                           'Respiratory'),
			array('J11',  'Influenza (Unspecified)',                        'Respiratory'),
			array('J12',  'Viral Pneumonia',                                'Respiratory'),
			array('J13',  'Pneumococcal Pneumonia',                        'Respiratory'),
			array('J14',  'Haemophilus Influenzae Pneumonia',              'Respiratory'),
			array('J15',  'Bacterial Pneumonia',                            'Respiratory'),
			array('J18',  'Pneumonia (Unspecified)',                        'Respiratory'),
			array('J20',  'Acute Bronchitis',                               'Respiratory'),
			array('J21',  'Acute Bronchiolitis',                            'Respiratory'),
			array('J22',  'Acute Lower Respiratory Infection',              'Respiratory'),
			array('J30',  'Vasomotor & Allergic Rhinitis',                  'Respiratory'),
			array('J32',  'Chronic Sinusitis',                              'Respiratory'),
			array('J35',  'Chronic Diseases of Tonsils & Adenoids',       'Respiratory'),
			array('J38',  'Diseases of Vocal Cords & Larynx',             'Respiratory'),
			array('J40',  'Bronchitis (Unspecified)',                       'Respiratory'),
			array('J41',  'Simple & Mucopurulent Chronic Bronchitis',     'Respiratory'),
			array('J43',  'Emphysema',                                      'Respiratory'),
			array('J44',  'Chronic Obstructive Pulmonary Disease (COPD)', 'Respiratory'),
			array('J45',  'Asthma',                                         'Respiratory'),
			array('J46',  'Status Asthmaticus',                             'Respiratory'),
			// Digestive
			array('K02',  'Dental Caries',                                  'Digestive'),
			array('K05',  'Gingivitis & Periodontal Disease',              'Digestive'),
			array('K21',  'Gastro-Oesophageal Reflux Disease (GERD)',     'Digestive'),
			array('K25',  'Gastric Ulcer',                                  'Digestive'),
			array('K26',  'Duodenal Ulcer',                                 'Digestive'),
			array('K29',  'Gastritis & Duodenitis',                        'Digestive'),
			array('K35',  'Acute Appendicitis',                             'Digestive'),
			array('K40',  'Inguinal Hernia',                                'Digestive'),
			array('K42',  'Umbilical Hernia',                               'Digestive'),
			array('K43',  'Ventral Hernia',                                 'Digestive'),
			array('K52',  'Colitis & Gastroenteritis (Non-Infective)',     'Digestive'),
			array('K56',  'Paralytic Ileus & Intestinal Obstruction',     'Digestive'),
			array('K57',  'Diverticular Disease of Intestine',              'Digestive'),
			array('K59',  'Constipation',                                   'Digestive'),
			array('K60',  'Fissure & Fistula of Anal Region',             'Digestive'),
			array('K61',  'Abscess of Anal & Rectal Region',              'Digestive'),
			array('K64',  'Haemorrhoids (Piles)',                           'Digestive'),
			array('K70',  'Alcoholic Liver Disease',                        'Digestive'),
			array('K72',  'Hepatic Failure',                                'Digestive'),
			array('K74',  'Fibrosis & Cirrhosis of Liver',                 'Digestive'),
			array('K80',  'Cholelithiasis (Gallstones)',                   'Digestive'),
			array('K81',  'Cholecystitis',                                  'Digestive'),
			array('K85',  'Acute Pancreatitis',                             'Digestive'),
			array('K86',  'Chronic Pancreatitis',                           'Digestive'),
			array('K92',  'Gastrointestinal Haemorrhage',                  'Digestive'),
			// Skin
			array('L00',  'Staphylococcal Scalded Skin Syndrome',          'Skin & Subcutaneous Tissue'),
			array('L02',  'Cutaneous Abscess, Furuncle & Carbuncle',      'Skin & Subcutaneous Tissue'),
			array('L03',  'Cellulitis',                                     'Skin & Subcutaneous Tissue'),
			array('L08',  'Infected Wound / Skin Infection',               'Skin & Subcutaneous Tissue'),
			array('L20',  'Atopic Dermatitis (Eczema)',                    'Skin & Subcutaneous Tissue'),
			array('L23',  'Allergic Contact Dermatitis',                    'Skin & Subcutaneous Tissue'),
			array('L27',  'Drug-Induced Dermatitis',                       'Skin & Subcutaneous Tissue'),
			array('L30',  'Dermatitis (Other)',                             'Skin & Subcutaneous Tissue'),
			array('L40',  'Psoriasis',                                      'Skin & Subcutaneous Tissue'),
			array('L50',  'Urticaria (Hives)',                              'Skin & Subcutaneous Tissue'),
			array('L60',  'Nail Disorders',                                 'Skin & Subcutaneous Tissue'),
			array('L70',  'Acne',                                           'Skin & Subcutaneous Tissue'),
			array('L72',  'Follicular Cysts',                               'Skin & Subcutaneous Tissue'),
			array('L89',  'Pressure Ulcer (Decubitus)',                    'Skin & Subcutaneous Tissue'),
			// Musculoskeletal
			array('M00',  'Pyogenic Arthritis',                             'Musculoskeletal'),
			array('M05',  'Rheumatoid Arthritis',                           'Musculoskeletal'),
			array('M10',  'Gout',                                           'Musculoskeletal'),
			array('M15',  'Polyarthrosis',                                  'Musculoskeletal'),
			array('M16',  'Hip Osteoarthritis (Coxarthrosis)',             'Musculoskeletal'),
			array('M17',  'Knee Osteoarthritis (Gonarthrosis)',            'Musculoskeletal'),
			array('M19',  'Osteoarthritis (Other)',                         'Musculoskeletal'),
			array('M40',  'Kyphosis & Lordosis',                            'Musculoskeletal'),
			array('M41',  'Scoliosis',                                      'Musculoskeletal'),
			array('M47',  'Spondylosis',                                    'Musculoskeletal'),
			array('M48',  'Spinal Stenosis',                               'Musculoskeletal'),
			array('M51',  'Intervertebral Disc Disorders',                 'Musculoskeletal'),
			array('M54',  'Dorsalgia / Back Pain',                         'Musculoskeletal'),
			array('M75',  'Shoulder Lesions',                               'Musculoskeletal'),
			array('M79',  'Soft Tissue Disorders',                          'Musculoskeletal'),
			array('M80',  'Osteoporosis with Pathological Fracture',       'Musculoskeletal'),
			array('M81',  'Osteoporosis',                                   'Musculoskeletal'),
			// Genitourinary
			array('N00',  'Acute Nephritic Syndrome',                       'Genitourinary'),
			array('N03',  'Chronic Nephritic Syndrome',                    'Genitourinary'),
			array('N04',  'Nephrotic Syndrome',                             'Genitourinary'),
			array('N17',  'Acute Kidney Injury (AKI)',                     'Genitourinary'),
			array('N18',  'Chronic Kidney Disease (CKD)',                  'Genitourinary'),
			array('N20',  'Urolithiasis (Kidney Stone)',                   'Genitourinary'),
			array('N30',  'Cystitis',                                       'Genitourinary'),
			array('N39',  'Urinary Tract Infection (UTI)',                 'Genitourinary'),
			array('N40',  'Benign Prostatic Hyperplasia (BPH)',            'Genitourinary'),
			array('N43',  'Hydrocele',                                      'Genitourinary'),
			array('N45',  'Orchitis & Epididymitis',                        'Genitourinary'),
			array('N48',  'Other Disorders of Penis',                      'Genitourinary'),
			array('N70',  'Salpingitis & Oophoritis (PID)',               'Genitourinary'),
			array('N71',  'Inflammatory Disease of Uterus',                'Genitourinary'),
			array('N75',  'Bartholin Gland Cyst',                           'Genitourinary'),
			array('N80',  'Endometriosis',                                  'Genitourinary'),
			array('N83',  'Ovarian Cyst',                                   'Genitourinary'),
			array('N91',  'Amenorrhoea',                                    'Genitourinary'),
			array('N92',  'Menorrhagia / Heavy Menstrual Bleeding',        'Genitourinary'),
			array('N94',  'Dysmenorrhoea (Painful Periods)',               'Genitourinary'),
			// Pregnancy & Childbirth
			array('O03',  'Abortion (Spontaneous / Miscarriage)',           'Pregnancy & Childbirth'),
			array('O10',  'Pre-existing Hypertension in Pregnancy',        'Pregnancy & Childbirth'),
			array('O11',  'Pre-existing Hypertension with Pre-eclampsia',  'Pregnancy & Childbirth'),
			array('O13',  'Gestational Hypertension',                       'Pregnancy & Childbirth'),
			array('O14',  'Pre-eclampsia',                                  'Pregnancy & Childbirth'),
			array('O15',  'Eclampsia',                                      'Pregnancy & Childbirth'),
			array('O20',  'Antepartum Haemorrhage',                        'Pregnancy & Childbirth'),
			array('O21',  'Hyperemesis Gravidarum (Vomiting in Pregnancy)','Pregnancy & Childbirth'),
			array('O24',  'Gestational Diabetes Mellitus',                  'Pregnancy & Childbirth'),
			array('O30',  'Multiple Gestation',                             'Pregnancy & Childbirth'),
			array('O36',  'Maternal Care for Foetal Complications',        'Pregnancy & Childbirth'),
			array('O42',  'Premature Rupture of Membranes (PROM)',        'Pregnancy & Childbirth'),
			array('O60',  'Preterm Labour',                                 'Pregnancy & Childbirth'),
			array('O62',  'Abnormalities of Forces of Labour',             'Pregnancy & Childbirth'),
			array('O72',  'Postpartum Haemorrhage (PPH)',                  'Pregnancy & Childbirth'),
			array('O80',  'Normal Delivery',                                'Pregnancy & Childbirth'),
			array('O82',  'Delivery by Caesarean Section',                 'Pregnancy & Childbirth'),
			// Perinatal
			array('P07',  'Preterm / Low Birth Weight',                    'Perinatal'),
			array('P20',  'Intrauterine Hypoxia',                           'Perinatal'),
			array('P21',  'Birth Asphyxia',                                 'Perinatal'),
			array('P22',  'Respiratory Distress of Newborn',               'Perinatal'),
			array('P36',  'Neonatal Sepsis',                                'Perinatal'),
			array('P38',  'Omphalitis of Newborn',                          'Perinatal'),
			array('P59',  'Neonatal Jaundice',                              'Perinatal'),
			// Congenital
			array('Q21',  'Congenital Heart Defects',                       'Congenital Malformations'),
			array('Q35',  'Cleft Palate',                                   'Congenital Malformations'),
			array('Q36',  'Cleft Lip',                                      'Congenital Malformations'),
			array('Q65',  'Congenital Hip Dysplasia',                       'Congenital Malformations'),
			array('Q90',  'Down Syndrome',                                  'Congenital Malformations'),
			// Symptoms & Signs
			array('R00',  'Heart Palpitations / Abnormal Heart Rate',      'Symptoms & Signs'),
			array('R05',  'Cough',                                          'Symptoms & Signs'),
			array('R06',  'Dyspnoea / Shortness of Breath',               'Symptoms & Signs'),
			array('R07',  'Chest Pain',                                     'Symptoms & Signs'),
			array('R10',  'Abdominal Pain',                                 'Symptoms & Signs'),
			array('R11',  'Nausea & Vomiting',                              'Symptoms & Signs'),
			array('R17',  'Jaundice',                                       'Symptoms & Signs'),
			array('R19',  'Other Symptoms of Digestive System',            'Symptoms & Signs'),
			array('R20',  'Skin Sensation Disturbance',                    'Symptoms & Signs'),
			array('R25',  'Abnormal Involuntary Movements',               'Symptoms & Signs'),
			array('R42',  'Dizziness & Giddiness',                         'Symptoms & Signs'),
			array('R50',  'Fever (Unspecified)',                            'Symptoms & Signs'),
			array('R51',  'Headache',                                       'Symptoms & Signs'),
			array('R52',  'Pain (Not Elsewhere Classified)',               'Symptoms & Signs'),
			array('R53',  'Malaise & Fatigue',                             'Symptoms & Signs'),
			array('R55',  'Syncope (Fainting)',                             'Symptoms & Signs'),
			array('R56',  'Convulsions',                                    'Symptoms & Signs'),
			array('R57',  'Shock',                                          'Symptoms & Signs'),
			array('R60',  'Oedema',                                         'Symptoms & Signs'),
			array('R62',  'Failure to Thrive (Paediatric)',                'Symptoms & Signs'),
			array('R63',  'Anorexia / Weight Loss',                        'Symptoms & Signs'),
			array('R68',  'Other General Symptoms & Signs',               'Symptoms & Signs'),
			// Injuries
			array('S00',  'Superficial Injury of Head',                     'Injury & Poisoning'),
			array('S06',  'Head Injury (Intracranial)',                    'Injury & Poisoning'),
			array('S09',  'Head Injury (Other)',                            'Injury & Poisoning'),
			array('S20',  'Superficial Injury of Thorax',                  'Injury & Poisoning'),
			array('S30',  'Superficial Injury of Abdomen',                 'Injury & Poisoning'),
			array('S40',  'Shoulder Injury',                                'Injury & Poisoning'),
			array('S50',  'Forearm Injury',                                 'Injury & Poisoning'),
			array('S60',  'Wrist & Hand Injury',                           'Injury & Poisoning'),
			array('S70',  'Hip & Thigh Injury',                            'Injury & Poisoning'),
			array('S80',  'Knee & Lower Leg Injury',                       'Injury & Poisoning'),
			array('S90',  'Ankle & Foot Injury',                           'Injury & Poisoning'),
			array('T07',  'Multiple Injuries',                              'Injury & Poisoning'),
			array('T14',  'Injury (Unspecified)',                           'Injury & Poisoning'),
			array('T40',  'Poisoning by Narcotics',                        'Injury & Poisoning'),
			array('T42',  'Poisoning by Antiepileptics & Sedatives',      'Injury & Poisoning'),
			array('T50',  'Poisoning by Diuretics',                        'Injury & Poisoning'),
			array('T65',  'Effects of Other Toxic Substances',             'Injury & Poisoning'),
			array('T78',  'Anaphylaxis / Allergic Reactions',             'Injury & Poisoning'),
			// External Causes
			array('X40',  'Accidental Poisoning',                          'External Causes'),
			array('X70',  'Intentional Self-Harm',                         'External Causes'),
			array('X99',  'Assault (Sharp Object)',                        'External Causes'),
			array('Y35',  'Legal Intervention',                            'External Causes'),
		);

		foreach ($diagnoses as $d) {
			// Skip if already exists by icd_code or name
			$chk = $this->db->query(
				"SELECT diagnosis_id FROM diagnosis WHERE (icd_code = ? OR diagnosis_name = ?) AND InActive = 0 LIMIT 1",
				array($d[0], $d[1])
			);
			if ($chk && $chk->num_rows() > 0) continue;

			$this->db->insert('diagnosis', array(
				'icd_code'       => $d[0],
				'diagnosis_name' => $d[1],
				'category'       => $d[2],
				'is_active'      => 1,
				'is_custom'      => 0,
				'InActive'       => 0,
			));
		}
	}

	/* ================================================================== */
	/*  6. SCAN MASTER ENHANCEMENTS                                        */
	/* ================================================================== */

	private function ensure_scan_master_enhancements()
	{
		if (!$this->table_exists('scan_master')) return;

		if (!$this->column_exists('scan_master', 'is_custom')) {
			$this->db->query("ALTER TABLE `scan_master` ADD COLUMN `is_custom` tinyint(1) NOT NULL DEFAULT 0");
		}
		if (!$this->column_exists('scan_master', 'created_by')) {
			$this->db->query("ALTER TABLE `scan_master` ADD COLUMN `created_by` varchar(25) DEFAULT NULL");
		}

		// Seed additional scans if not enough
		$q = $this->db->query("SELECT COUNT(*) AS c FROM scan_master WHERE InActive = 0");
		$r = $q ? $q->row() : null;
		if ($r && (int)$r->c < 25) {
			$this->seed_additional_scans();
		}
	}

	private function seed_additional_scans()
	{
		$scans = array(
			// Sonography
			array('Prostate Ultrasound', 'Ultrasound', 'Sonography', 'Radiology'),
			array('Renal Ultrasound', 'Ultrasound', 'Sonography', 'Radiology'),
			array('Doppler Ultrasound (Lower Limb)', 'Ultrasound', 'Sonography', 'Radiology'),
			array('Testicular Ultrasound', 'Ultrasound', 'Sonography', 'Radiology'),
			array('Transvaginal Ultrasound', 'Ultrasound', 'Sonography', 'Radiology'),
			array('Musculoskeletal Ultrasound', 'Ultrasound', 'Sonography', 'Radiology'),
			// X-Ray
			array('Pelvis X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Hand X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Foot X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Knee X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Shoulder X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Hip X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Cervical Spine X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Lumbar Spine X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Thoraco-Lumbar Spine X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Abdomen (Erect) X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('KUB X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Sinus X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			array('Dental X-Ray', 'X-Ray', 'Imaging', 'Radiology'),
			// ECG
			array('12-Lead ECG', 'ECG', 'Cardiac', 'Cardiology'),
			array('Holter ECG (24hr)', 'ECG', 'Cardiac', 'Cardiology'),
			array('Stress ECG (Exercise)', 'ECG', 'Cardiac', 'Cardiology'),
			// Echocardiogram
			array('Echocardiogram', 'Echo', 'Cardiac', 'Cardiology'),
		);

		foreach ($scans as $s) {
			$chk = $this->db->query("SELECT id FROM scan_master WHERE scan_name = ? AND InActive = 0 LIMIT 1", array($s[0]));
			if ($chk && $chk->num_rows() > 0) continue;

			$this->db->insert('scan_master', array(
				'scan_name'   => $s[0],
				'category'    => $s[1],
				'description' => $s[2],
				'department'  => $s[3],
				'is_active'   => 1,
				'is_custom'   => 0,
				'InActive'    => 0
			));
		}
	}

	/* ================================================================== */
	/*  SMART AUTOCOMPLETE SEARCH METHODS                                  */
	/* ================================================================== */

	public function search_medications_smart($term, $limit = 20)
	{
		$term = trim((string)$term);
		if (strlen($term) < 2) return array();

		$like = '%' . $term . '%';
		$sql = "SELECT drug_id AS id, drug_name AS label, drug_name AS value,
				       COALESCE(generic_name,'') AS generic_name,
				       COALESCE(dosage_form,'') AS dosage_form,
				       COALESCE(strength,'') AS strength,
				       COALESCE(standard_dosage,'') AS standard_dosage,
				       COALESCE(route,'') AS route,
				       nPrice AS price, nStock AS stock,
				       COALESCE(is_nhis_covered,0) AS is_nhis_covered,
				       COALESCE(nhis_drug_code,'') AS nhis_drug_code,
				       med_cat_id AS category_id,
				       COALESCE(is_custom, 0) AS is_custom
				FROM medicine_drug_name
				WHERE InActive = 0
				  AND (drug_name LIKE ? OR generic_name LIKE ? OR strength LIKE ?)
				ORDER BY
				  CASE WHEN drug_name LIKE ? THEN 0 ELSE 1 END,
				  drug_name ASC
				LIMIT ?";
		$starts = $term . '%';
		$q = $this->db->query($sql, array($like, $like, $like, $starts, (int)$limit));
		return $q ? $q->result() : array();
	}

	public function search_diagnoses_smart($term, $limit = 20)
	{
		$term = trim((string)$term);
		if (strlen($term) < 2) return array();

		$like = '%' . $term . '%';
		$starts = $term . '%';
		$sql = "SELECT diagnosis_id AS id, diagnosis_name AS label, diagnosis_name AS value,
				       COALESCE(icd_code,'') AS icd_code,
				       COALESCE(category,'') AS category,
				       COALESCE(common_treatment,'') AS common_treatment,
				       COALESCE(is_custom, 0) AS is_custom
				FROM diagnosis
				WHERE InActive = 0 AND (is_active = 1 OR is_active IS NULL)
				  AND (diagnosis_name LIKE ? OR icd_code LIKE ? OR category LIKE ?)
				ORDER BY
				  CASE WHEN diagnosis_name LIKE ? THEN 0 ELSE 1 END,
				  diagnosis_name ASC
				LIMIT ?";
		$q = $this->db->query($sql, array($like, $like, $like, $starts, (int)$limit));
		return $q ? $q->result() : array();
	}

	public function search_lab_tests_smart($term, $limit = 20)
	{
		$term = trim((string)$term);
		if (strlen($term) < 2) return array();

		$like = '%' . $term . '%';
		$starts = $term . '%';
		$sql = "SELECT test_id AS id, test_name AS label, test_name AS value,
				       COALESCE(test_code,'') AS test_code,
				       COALESCE(category,'') AS category,
				       COALESCE(sample_type,'') AS sample_type,
				       COALESCE(is_custom, 0) AS is_custom
				FROM lab_test_master
				WHERE InActive = 0 AND is_active = 1
				  AND (test_name LIKE ? OR test_code LIKE ? OR category LIKE ?)
				ORDER BY
				  CASE WHEN test_name LIKE ? THEN 0 WHEN test_code LIKE ? THEN 0 ELSE 1 END,
				  test_name ASC
				LIMIT ?";
		$q = $this->db->query($sql, array($like, $like, $like, $starts, $starts, (int)$limit));
		return $q ? $q->result() : array();
	}

	public function search_scans_smart($term, $category_filter = '', $limit = 20)
	{
		$term = trim((string)$term);
		if (strlen($term) < 2) return array();

		$like = '%' . $term . '%';
		$starts = $term . '%';
		$params = array($like, $like);
		$catWhere = '';
		if ($category_filter !== '') {
			$catWhere = " AND category = ?";
			$params[] = $category_filter;
		}
		$params[] = $starts;
		$params[] = (int)$limit;

		$sql = "SELECT id, scan_name AS label, scan_name AS value,
				       COALESCE(category,'') AS category,
				       COALESCE(department,'') AS department,
				       COALESCE(is_custom, 0) AS is_custom
				FROM scan_master
				WHERE InActive = 0 AND is_active = 1
				  AND (scan_name LIKE ? OR category LIKE ?)
				  {$catWhere}
				ORDER BY
				  CASE WHEN scan_name LIKE ? THEN 0 ELSE 1 END,
				  scan_name ASC
				LIMIT ?";
		$q = $this->db->query($sql, $params);
		return $q ? $q->result() : array();
	}

	/* ================================================================== */
	/*  CUSTOM ENTRY SAVE METHODS                                          */
	/* ================================================================== */

	public function save_custom_medication($name, $user_id)
	{
		$name = trim((string)$name);
		if ($name === '') return null;

		// Check if already exists
		$chk = $this->db->query("SELECT drug_id FROM medicine_drug_name WHERE drug_name = ? AND InActive = 0 LIMIT 1", array($name));
		if ($chk && $chk->num_rows() > 0) {
			return (int)$chk->row()->drug_id;
		}

		$this->db->insert('medicine_drug_name', array(
			'drug_name'    => $name,
			'nPrice'       => 0,
			'nStock'       => 0,
			'is_custom'    => 1,
			'created_by'   => (string)$user_id,
			'InActive'     => 0
		));
		return $this->db->insert_id();
	}

	public function save_custom_diagnosis($name, $user_id)
	{
		$name = trim((string)$name);
		if ($name === '') return null;

		$chk = $this->db->query("SELECT diagnosis_id FROM diagnosis WHERE diagnosis_name = ? AND InActive = 0 LIMIT 1", array($name));
		if ($chk && $chk->num_rows() > 0) {
			return (int)$chk->row()->diagnosis_id;
		}

		$this->db->insert('diagnosis', array(
			'diagnosis_name' => $name,
			'is_active'      => 1,
			'is_custom'      => 1,
			'created_by'     => (string)$user_id,
			'InActive'       => 0
		));
		return $this->db->insert_id();
	}

	public function save_custom_lab_test($name, $user_id)
	{
		$name = trim((string)$name);
		if ($name === '') return null;

		$chk = $this->db->query("SELECT test_id FROM lab_test_master WHERE test_name = ? AND InActive = 0 LIMIT 1", array($name));
		if ($chk && $chk->num_rows() > 0) {
			return (int)$chk->row()->test_id;
		}

		$this->db->insert('lab_test_master', array(
			'test_name'  => $name,
			'category'   => 'Custom',
			'is_active'  => 1,
			'is_custom'  => 1,
			'created_by' => (string)$user_id,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive'   => 0
		));
		return $this->db->insert_id();
	}

	public function save_custom_scan($name, $category, $user_id)
	{
		$name = trim((string)$name);
		if ($name === '') return null;

		$chk = $this->db->query("SELECT id FROM scan_master WHERE scan_name = ? AND InActive = 0 LIMIT 1", array($name));
		if ($chk && $chk->num_rows() > 0) {
			return (int)$chk->row()->id;
		}

		$this->db->insert('scan_master', array(
			'scan_name'   => $name,
			'category'    => $category ? $category : 'Custom',
			'is_active'   => 1,
			'is_custom'   => 1,
			'created_by'  => (string)$user_id,
			'InActive'    => 0
		));
		return $this->db->insert_id();
	}

	/* ================================================================== */
	/*  LAB RESULT TEMPLATE METHODS                                        */
	/* ================================================================== */

	public function get_templates_for_test($test_name)
	{
		$this->ensure_all_master_tables();
		$sql = "SELECT * FROM lab_result_templates
				WHERE test_name = ? AND InActive = 0 AND is_active = 1
				ORDER BY sort_order ASC";
		$q = $this->db->query($sql, array((string)$test_name));
		return $q ? $q->result() : array();
	}

	public function get_all_template_test_names()
	{
		$this->ensure_all_master_tables();
		$sql = "SELECT DISTINCT test_name FROM lab_result_templates
				WHERE InActive = 0 AND is_active = 1
				ORDER BY test_name ASC";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	public function find_template_by_test_name($partial_name)
	{
		$this->ensure_all_master_tables();
		// Try exact match first
		$templates = $this->get_templates_for_test($partial_name);
		if (!empty($templates)) return $templates;

		// Try partial match
		$sql = "SELECT DISTINCT test_name FROM lab_result_templates
				WHERE InActive = 0 AND is_active = 1
				  AND test_name LIKE ?
				ORDER BY test_name ASC LIMIT 1";
		$q = $this->db->query($sql, array('%' . $partial_name . '%'));
		if ($q && $q->num_rows() > 0) {
			return $this->get_templates_for_test($q->row()->test_name);
		}
		return array();
	}

	public function save_structured_result($io_lab_id, $entries, $user_id)
	{
		$this->ensure_all_master_tables();
		$saved = 0;
		$io_lab_id = (int)$io_lab_id;
		$now = date('Y-m-d H:i:s');
		
		foreach ($entries as $entry) {
			$param = isset($entry['parameter_name']) ? trim($entry['parameter_name']) : '';
			$val = isset($entry['result_value']) ? trim($entry['result_value']) : '';
			if ($param === '' || $val === '') continue;

			// Determine flag and color
			$flag = 'normal';
			$color = 'green';
			$template_id = isset($entry['template_id']) ? (int)$entry['template_id'] : null;

			if ($template_id) {
				$tpl = $this->db->get_where('lab_result_templates', array('template_id' => $template_id))->row();
				if ($tpl) {
					$flagResult = $this->determine_result_flag($val, $tpl);
					$flag = $flagResult['flag'];
					$color = $flagResult['color'];
				}
			}

			// Check for existing active entry
			$existing = $this->db->get_where('lab_result_entries', array(
				'io_lab_id' => $io_lab_id,
				'parameter_name' => $param,
				'InActive' => 0
			))->row();

			$data = array(
				'template_id'    => $template_id,
				'result_value'   => $val,
				'result_flag'    => $flag,
				'color_code'     => $color,
				'unit'           => isset($entry['unit']) ? $entry['unit'] : null,
				'normal_range'   => isset($entry['normal_range']) ? $entry['normal_range'] : null,
				'notes'          => isset($entry['notes']) ? $entry['notes'] : null,
				'updated_by'     => (string)$user_id,
				'updated_at'     => $now
			);

			if ($existing) {
				// Update existing entry
				$this->db->where('entry_id', $existing->entry_id);
				$this->db->update('lab_result_entries', $data);
				log_message('info', 'LAB_STRUCTURED_RESULT_UPDATED io_lab_id='.$io_lab_id.' param='.$param.' entry_id='.$existing->entry_id);
			} else {
				// Insert new entry
				$data['io_lab_id'] = $io_lab_id;
				$data['parameter_name'] = $param;
				$data['entered_by'] = (string)$user_id;
				$data['entered_at'] = $now;
				$data['InActive'] = 0;
				$this->db->insert('lab_result_entries', $data);
				log_message('info', 'LAB_STRUCTURED_RESULT_CREATED io_lab_id='.$io_lab_id.' param='.$param);
			}
			$saved++;
		}
		return $saved;
	}

	public function get_structured_results($io_lab_id)
	{
		$this->ensure_all_master_tables();
		$sql = "SELECT re.*, COALESCE(rt.result_options,'') AS template_options,
				       COALESCE(rt.color_rules,'{}') AS template_color_rules
				FROM lab_result_entries re
				LEFT JOIN lab_result_templates rt ON rt.template_id = re.template_id
				WHERE re.io_lab_id = ? AND re.InActive = 0
				ORDER BY re.entry_id ASC";
		$q = $this->db->query($sql, array((int)$io_lab_id));
		return $q ? $q->result() : array();
	}

	private function determine_result_flag($value, $template)
	{
		$flag = 'normal';
		$color = 'green';

		// Check dropdown options first
		$options = trim((string)$template->result_options);
		$colorRules = json_decode((string)$template->color_rules, true);
		if (!is_array($colorRules)) $colorRules = array();

		if ($options !== '' && isset($colorRules[$value])) {
			$color = $colorRules[$value];
			if ($color === 'red') $flag = 'critical';
			elseif ($color === 'orange') $flag = 'high';
			elseif ($color === 'yellow') $flag = 'abnormal';
			elseif ($color === 'blue') $flag = 'low';
			else $flag = 'normal';
			return array('flag' => $flag, 'color' => $color);
		}

		// Numeric range check
		if (is_numeric($value)) {
			$numVal = (float)$value;
			$hasMin = ($template->normal_min !== null && $template->normal_min !== '');
			$hasMax = ($template->normal_max !== null && $template->normal_max !== '');

			if ($hasMin && $hasMax) {
				$min = (float)$template->normal_min;
				$max = (float)$template->normal_max;
				if ($numVal < $min) {
					$flag = 'low'; $color = isset($colorRules['low']) ? $colorRules['low'] : 'blue';
				} elseif ($numVal > $max) {
					$critThreshold = $max * 1.5;
					if ($numVal > $critThreshold) {
						$flag = 'critical'; $color = isset($colorRules['critical']) ? $colorRules['critical'] : 'red';
					} else {
						$flag = 'high'; $color = isset($colorRules['high']) ? $colorRules['high'] : 'orange';
					}
				} else {
					$flag = 'normal'; $color = isset($colorRules['normal']) ? $colorRules['normal'] : 'green';
				}
			} elseif ($hasMax) {
				$max = (float)$template->normal_max;
				if ($numVal > $max) {
					$flag = 'high'; $color = isset($colorRules['high']) ? $colorRules['high'] : 'orange';
				}
			} elseif ($hasMin) {
				$min = (float)$template->normal_min;
				if ($numVal < $min) {
					$flag = 'low'; $color = isset($colorRules['low']) ? $colorRules['low'] : 'blue';
				}
			}
		}

		return array('flag' => $flag, 'color' => $color);
	}

	/* ================================================================== */
	/*  DOCTOR NOTIFICATION METHODS                                        */
	/* ================================================================== */

	public function create_lab_notification($doctor_id, $patient_no, $iop_id, $io_lab_id, $test_name)
	{
		$this->ensure_all_master_tables();
		$this->db->insert('doctor_notifications', array(
			'doctor_id'  => (string)$doctor_id,
			'patient_no' => (string)$patient_no,
			'iop_id'     => (string)$iop_id,
			'io_lab_id'  => (int)$io_lab_id,
			'notif_type' => 'LAB_RESULT',
			'title'      => 'Lab Result Ready: ' . $test_name,
			'message'    => 'Lab result for ' . $test_name . ' (Patient: ' . $patient_no . ') has been uploaded.',
			'is_read'    => 0,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive'   => 0
		));
		return $this->db->insert_id();
	}

	public function get_unread_notifications($doctor_id, $limit = 20)
	{
		$this->ensure_all_master_tables();
		$sql = "SELECT * FROM doctor_notifications
				WHERE doctor_id = ? AND is_read = 0 AND InActive = 0
				ORDER BY created_at DESC LIMIT ?";
		$q = $this->db->query($sql, array((string)$doctor_id, (int)$limit));
		return $q ? $q->result() : array();
	}

	public function count_unread_notifications($doctor_id)
	{
		$this->ensure_all_master_tables();
		$q = $this->db->query("SELECT COUNT(*) AS c FROM doctor_notifications
				WHERE doctor_id = ? AND is_read = 0 AND InActive = 0",
			array((string)$doctor_id));
		$r = $q ? $q->row() : null;
		return $r ? (int)$r->c : 0;
	}

	public function mark_notification_read($notif_id, $doctor_id)
	{
		$this->db->where(array('notif_id' => (int)$notif_id, 'doctor_id' => (string)$doctor_id));
		$this->db->update('doctor_notifications', array('is_read' => 1, 'read_at' => date('Y-m-d H:i:s')));
	}

	public function mark_all_read($doctor_id)
	{
		$this->db->where(array('doctor_id' => (string)$doctor_id, 'is_read' => 0));
		$this->db->update('doctor_notifications', array('is_read' => 1, 'read_at' => date('Y-m-d H:i:s')));
	}

	/* ================================================================== */
	/*  PATIENT HISTORY TIMELINE                                           */
	/* ================================================================== */

	public function get_patient_timeline($patient_no, $limit = 50)
	{
		$timeline = array();
		$patient_no = trim((string)$patient_no);
		if ($patient_no === '') {
			return $timeline;
		}

		// Suppress DB errors temporarily
		$old_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($old_debug !== null) { $this->db->db_debug = false; }

		try {
			// Diagnoses
			if ($this->table_exists('iop_diagnosis') && $this->table_exists('diagnosis')) {
				$sql = "SELECT 'diagnosis' AS type, COALESCE(d.diagnosis_name, id.diagnosis_text, 'Diagnosis') AS title,
						       id.remarks AS detail, id.dDate AS event_date, id.iop_id AS visit_id,
						       COALESCE(d.icd_code,'') AS extra
						FROM iop_diagnosis id
						LEFT JOIN diagnosis d ON d.diagnosis_id = id.diagnosis_id
						WHERE id.InActive = 0
						  AND id.iop_id IN (SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND InActive = 0)
						ORDER BY id.dDate DESC LIMIT ?";
				$q = $this->db->query($sql, array($patient_no, (int)$limit));
				if ($q && $q->num_rows() > 0) {
					foreach ($q->result() as $r) { $r->icon = 'fa-stethoscope'; $r->color = '#00a65a'; $timeline[] = $r; }
				}
			}

			// Medications
			if ($this->table_exists('iop_medication')) {
				$sql = "SELECT 'medication' AS type,
						       COALESCE(dn.drug_name, im.medicine_text, 'Medication') AS title,
						       CONCAT(COALESCE(im.dosage,''),' ',COALESCE(im.instruction,'')) AS detail,
						       im.dDate AS event_date, im.iop_id AS visit_id,
						       CONCAT('Qty: ', COALESCE(im.total_qty,'')) AS extra
						FROM iop_medication im
						LEFT JOIN medicine_drug_name dn ON dn.drug_id = im.medicine_id
						WHERE im.InActive = 0
						  AND im.iop_id IN (SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND InActive = 0)
						ORDER BY im.dDate DESC LIMIT ?";
				$q = $this->db->query($sql, array($patient_no, (int)$limit));
				if ($q && $q->num_rows() > 0) {
					foreach ($q->result() as $r) { $r->icon = 'fa-medkit'; $r->color = '#f39c12'; $timeline[] = $r; }
				}
			}

			// Lab results
			if ($this->table_exists('iop_laboratory')) {
				$sql = "SELECT 'lab_result' AS type,
						       COALESCE(bp.particular_name, il.laboratory_text, 'Lab Test') AS title,
						       il.result AS detail, il.dDate AS event_date, il.iop_id AS visit_id,
						       COALESCE(il.findings,'') AS extra
						FROM iop_laboratory il
						LEFT JOIN bill_particular bp ON bp.particular_id = il.laboratory_id
						WHERE il.InActive = 0
						  AND il.iop_id IN (SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND InActive = 0)
						ORDER BY il.dDate DESC LIMIT ?";
				$q = $this->db->query($sql, array($patient_no, (int)$limit));
				if ($q && $q->num_rows() > 0) {
					foreach ($q->result() as $r) { $r->icon = 'fa-flask'; $r->color = '#3c8dbc'; $timeline[] = $r; }
				}
			}

			// Vitals
			if ($this->table_exists('iop_vital_parameters')) {
				$sql = "SELECT 'vitals' AS type, 'Vital Signs' AS title,
						       CONCAT('BP: ',COALESCE(iv.blood_pressure,'--'),' | T: ',COALESCE(iv.temperature,'--'),' | P: ',COALESCE(iv.pulse,'--'),' | W: ',COALESCE(iv.weight,'--')) AS detail,
						       iv.dDate AS event_date, iv.iop_id AS visit_id, '' AS extra
						FROM iop_vital_parameters iv
						WHERE iv.InActive = 0
						  AND iv.iop_id IN (SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND InActive = 0)
						ORDER BY iv.dDate DESC LIMIT ?";
				$q = $this->db->query($sql, array($patient_no, (int)$limit));
				if ($q && $q->num_rows() > 0) {
					foreach ($q->result() as $r) { $r->icon = 'fa-heartbeat'; $r->color = '#dd4b39'; $timeline[] = $r; }
				}
			}

			// Visits (OPD records)
			if ($this->table_exists('patient_details_iop')) {
				$sql = "SELECT 'visit' AS type, 
						       CONCAT(p.patient_type, ' Visit') AS title,
						       COALESCE(p.complaints, p.provisional_diagnosis, '') AS detail,
						       p.date_visit AS event_date, p.IO_ID AS visit_id,
						       CONCAT('Status: ', COALESCE(p.nStatus,'')) AS extra
						FROM patient_details_iop p
						WHERE p.InActive = 0 AND p.patient_no = ?
						ORDER BY p.date_visit DESC, p.time_visit DESC LIMIT ?";
				$q = $this->db->query($sql, array($patient_no, (int)$limit));
				if ($q && $q->num_rows() > 0) {
					foreach ($q->result() as $r) { $r->icon = 'fa-calendar-check-o'; $r->color = '#605ca8'; $timeline[] = $r; }
				}
			}

		} catch (Exception $e) {
			// Silently fail - return empty timeline
		}

		// Restore DB debug
		if ($old_debug !== null) { $this->db->db_debug = $old_debug; }

		// Sort by date descending
		if (count($timeline) > 0) {
			usort($timeline, function($a, $b) {
				$dateA = isset($a->event_date) ? (string)$a->event_date : '';
				$dateB = isset($b->event_date) ? (string)$b->event_date : '';
				return strcmp($dateB, $dateA);
			});
		}

		return array_slice($timeline, 0, $limit);
	}

	/* ================================================================== */
	/*  LAB CATEGORIES (for filtering)                                     */
	/* ================================================================== */

	public function get_lab_categories()
	{
		$this->ensure_all_master_tables();
		$sql = "SELECT DISTINCT category FROM lab_test_master WHERE InActive = 0 AND is_active = 1 ORDER BY category ASC";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}

	public function get_scan_categories()
	{
		$sql = "SELECT DISTINCT category FROM scan_master WHERE InActive = 0 AND is_active = 1 ORDER BY category ASC";
		$q = $this->db->query($sql);
		return $q ? $q->result() : array();
	}
}
