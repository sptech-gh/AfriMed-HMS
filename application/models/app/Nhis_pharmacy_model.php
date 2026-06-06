<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Pharmacy Compliance Model
 * Phase 2 — NHIS Claim-It Protocol for Pharmacy Module
 * 
 * Implements:
 * 1. NHIS Drug Code Mapping
 * 2. Tariff Mapping
 * 3. Auto Claim Item Creation
 * 4. Membership Verification
 * 5. Claim Validation Layer
 * 
 * @author HMS Enterprise Architect
 * @version 2.0
 */
class Nhis_pharmacy_model extends CI_Model
{
	const VERIFICATION_VALID = 'VALID';
	const VERIFICATION_EXPIRED = 'EXPIRED';
	const VERIFICATION_INVALID = 'INVALID';
	const VERIFICATION_NOT_FOUND = 'NOT_FOUND';

	public function __construct()
	{
		parent::__construct();
		$this->load->helper('schema_guard');
		if (!schema_already_run('nhis_pharmacy_schema')) {
			$this->ensure_nhis_pharmacy_schema();
			mark_schema_run('nhis_pharmacy_schema');
		}
	}

	/**
	 * Check if a table exists
	 */
	public function table_exists($table_name)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($table_name));
		return ($q && $q->num_rows() > 0);
	}

	// =========================================================================
	// SCHEMA MIGRATION
	// =========================================================================

	/**
	 * Ensure all NHIS pharmacy compliance tables and columns exist
	 */
	public function ensure_nhis_pharmacy_schema()
	{
		$this->_add_nhis_drug_code_column();
		$this->_create_drug_tariff_mapping_table();
		$this->_create_nhis_drug_tariffs_table();
		$this->_add_nhis_claim_columns_to_dispensing();
		$this->_create_claim_validation_log_table();
		$this->_ensure_nhis_claims_schema();
		return true;
	}
	
	/**
	 * Ensure nhis_claims table has required columns
	 */
	private function _ensure_nhis_claims_schema()
	{
		if (!$this->table_exists('nhis_claims')) {
			// Create the table if it doesn't exist
			$this->db->query("
				CREATE TABLE IF NOT EXISTS `nhis_claims` (
					`id` INT AUTO_INCREMENT PRIMARY KEY,
					`claim_number` VARCHAR(50) NOT NULL,
					`patient_no` VARCHAR(20) NOT NULL,
					`visit_id` INT,
					`invoice_id` INT,
					`claim_date` DATE NOT NULL,
					`claim_month` VARCHAR(7),
					`service_date` DATE,
					`total_amount` DECIMAL(15,2) DEFAULT 0,
					`approved_amount` DECIMAL(15,2),
					`status` VARCHAR(30) DEFAULT 'DRAFT',
					`claimit_reference` VARCHAR(100),
					`submission_date` DATETIME,
					`approval_date` DATETIME,
					`rejection_reason` TEXT,
					`created_by` VARCHAR(25),
					`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
					`updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
					INDEX idx_patient (patient_no),
					INDEX idx_status (status),
					INDEX idx_claim_date (claim_date),
					INDEX idx_claim_month (claim_month)
				) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
			");
		} else {
			// Add missing columns if table exists
			if (!$this->_column_exists('nhis_claims', 'claim_date')) {
				$this->db->query("ALTER TABLE `nhis_claims` ADD COLUMN `claim_date` DATE NOT NULL DEFAULT '2024-01-01'");
			}
			if (!$this->_column_exists('nhis_claims', 'claim_month')) {
				$this->db->query("ALTER TABLE `nhis_claims` ADD COLUMN `claim_month` VARCHAR(7)");
			}
			if (!$this->_column_exists('nhis_claims', 'service_date')) {
				$this->db->query("ALTER TABLE `nhis_claims` ADD COLUMN `service_date` DATE");
			}
			if (!$this->_column_exists('nhis_claims', 'total_amount')) {
				$this->db->query("ALTER TABLE `nhis_claims` ADD COLUMN `total_amount` DECIMAL(15,2) DEFAULT 0");
			}
			if (!$this->_column_exists('nhis_claims', 'status')) {
				$this->db->query("ALTER TABLE `nhis_claims` ADD COLUMN `status` VARCHAR(30) DEFAULT 'DRAFT'");
			}
		}
	}

	/**
	 * Add nhis_drug_code column to medicine_drug_name
	 */
	private function _add_nhis_drug_code_column()
	{
		if (!$this->_column_exists('medicine_drug_name', 'nhis_drug_code')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_drug_code` VARCHAR(50) DEFAULT NULL");
		}
		if (!$this->_column_exists('medicine_drug_name', 'nhis_drug_name')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_drug_name` VARCHAR(255) DEFAULT NULL");
		}
		if (!$this->_column_exists('medicine_drug_name', 'nhis_tariff_id')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_tariff_id` INT(11) DEFAULT NULL");
		}
		if (!$this->_column_exists('medicine_drug_name', 'nhis_unit_tariff')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_unit_tariff` DECIMAL(15,2) DEFAULT 0");
		}
		if (!$this->_column_exists('medicine_drug_name', 'nhis_requires_auth')) {
			$this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `nhis_requires_auth` TINYINT(1) DEFAULT 0");
		}
	}

	/**
	 * Create drug tariff mapping table
	 */
	private function _create_drug_tariff_mapping_table()
	{
		if ($this->_table_exists('drug_tariff_mapping')) {
			return;
		}

		$sql = "CREATE TABLE `drug_tariff_mapping` (
			`mapping_id` INT(11) NOT NULL AUTO_INCREMENT,
			`drug_id` INT(11) NOT NULL,
			`nhis_tariff_id` INT(11) NOT NULL,
			`nhis_drug_code` VARCHAR(50) NOT NULL,
			`nhis_drug_name` VARCHAR(255) DEFAULT NULL,
			`unit_tariff` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`dosage_form` VARCHAR(100) DEFAULT NULL,
			`strength` VARCHAR(100) DEFAULT NULL,
			`pack_size` INT(11) DEFAULT 1,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`requires_authorization` TINYINT(1) NOT NULL DEFAULT 0,
			`max_quantity_per_visit` INT(11) DEFAULT NULL,
			`created_by` VARCHAR(25) DEFAULT NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (`mapping_id`),
			UNIQUE KEY `uk_drug_tariff` (`drug_id`, `nhis_tariff_id`),
			KEY `idx_drug_id` (`drug_id`),
			KEY `idx_nhis_tariff_id` (`nhis_tariff_id`),
			KEY `idx_nhis_drug_code` (`nhis_drug_code`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Create NHIS drug tariffs table (official NHIS drug price list)
	 */
	private function _create_nhis_drug_tariffs_table()
	{
		if ($this->_table_exists('nhis_drug_tariffs')) {
			return;
		}

		$sql = "CREATE TABLE `nhis_drug_tariffs` (
			`tariff_id` INT(11) NOT NULL AUTO_INCREMENT,
			`nhis_code` VARCHAR(50) NOT NULL,
			`drug_name` VARCHAR(255) NOT NULL,
			`generic_name` VARCHAR(255) DEFAULT NULL,
			`dosage_form` VARCHAR(100) DEFAULT NULL,
			`strength` VARCHAR(100) DEFAULT NULL,
			`unit` VARCHAR(50) DEFAULT NULL,
			`pack_size` INT(11) DEFAULT 1,
			`unit_price` DECIMAL(15,2) NOT NULL DEFAULT 0,
			`category` VARCHAR(100) DEFAULT NULL,
			`therapeutic_class` VARCHAR(100) DEFAULT NULL,
			`is_essential` TINYINT(1) DEFAULT 0,
			`requires_authorization` TINYINT(1) DEFAULT 0,
			`max_days_supply` INT(11) DEFAULT NULL,
			`effective_date` DATE DEFAULT NULL,
			`expiry_date` DATE DEFAULT NULL,
			`is_active` TINYINT(1) NOT NULL DEFAULT 1,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`tariff_id`),
			UNIQUE KEY `uk_nhis_code` (`nhis_code`),
			KEY `idx_drug_name` (`drug_name`),
			KEY `idx_generic_name` (`generic_name`),
			KEY `idx_category` (`category`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);

		// Seed with sample Ghana NHIS drug tariffs
		$this->_seed_nhis_drug_tariffs();
	}

	/**
	 * Add NHIS claim columns to dispensing table
	 */
	private function _add_nhis_claim_columns_to_dispensing()
	{
		if ($this->_table_exists('iop_medication_administration')) {
			if (!$this->_column_exists('iop_medication_administration', 'nhis_claim_id')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `nhis_claim_id` INT(11) DEFAULT NULL");
			}
			if (!$this->_column_exists('iop_medication_administration', 'nhis_claim_item_id')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `nhis_claim_item_id` INT(11) DEFAULT NULL");
			}
			if (!$this->_column_exists('iop_medication_administration', 'nhis_tariff_amount')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `nhis_tariff_amount` DECIMAL(15,2) DEFAULT 0");
			}
			if (!$this->_column_exists('iop_medication_administration', 'nhis_verification_status')) {
				$this->db->query("ALTER TABLE `iop_medication_administration` ADD COLUMN `nhis_verification_status` VARCHAR(30) DEFAULT NULL");
			}
		}
	}

	/**
	 * Create claim validation log table
	 */
	private function _create_claim_validation_log_table()
	{
		if ($this->_table_exists('nhis_claim_validation_log')) {
			return;
		}

		$sql = "CREATE TABLE `nhis_claim_validation_log` (
			`log_id` INT(11) NOT NULL AUTO_INCREMENT,
			`claim_id` INT(11) DEFAULT NULL,
			`claim_item_id` INT(11) DEFAULT NULL,
			`iop_med_id` INT(11) DEFAULT NULL,
			`validation_type` VARCHAR(50) NOT NULL,
			`validation_result` ENUM('PASS','FAIL','WARNING') NOT NULL,
			`error_code` VARCHAR(50) DEFAULT NULL,
			`error_message` TEXT DEFAULT NULL,
			`field_name` VARCHAR(100) DEFAULT NULL,
			`expected_value` VARCHAR(255) DEFAULT NULL,
			`actual_value` VARCHAR(255) DEFAULT NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`log_id`),
			KEY `idx_claim_id` (`claim_id`),
			KEY `idx_validation_type` (`validation_type`),
			KEY `idx_validation_result` (`validation_result`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$this->db->query($sql);
	}

	/**
	 * Seed NHIS drug tariffs with Ghana NHIS essential medicines
	 */
	private function _seed_nhis_drug_tariffs()
	{
		$tariffs = array(
			// Analgesics & Antipyretics
			array('nhis_code' => 'DRUG-001', 'drug_name' => 'Paracetamol 500mg Tablet', 'generic_name' => 'Paracetamol', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.10, 'category' => 'ANALGESICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-002', 'drug_name' => 'Ibuprofen 400mg Tablet', 'generic_name' => 'Ibuprofen', 'dosage_form' => 'Tablet', 'strength' => '400mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.15, 'category' => 'ANALGESICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-003', 'drug_name' => 'Diclofenac 50mg Tablet', 'generic_name' => 'Diclofenac', 'dosage_form' => 'Tablet', 'strength' => '50mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.20, 'category' => 'ANALGESICS', 'is_essential' => 1),

			// Antibiotics
			array('nhis_code' => 'DRUG-010', 'drug_name' => 'Amoxicillin 500mg Capsule', 'generic_name' => 'Amoxicillin', 'dosage_form' => 'Capsule', 'strength' => '500mg', 'unit' => 'Capsule', 'pack_size' => 1, 'unit_price' => 0.30, 'category' => 'ANTIBIOTICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-011', 'drug_name' => 'Amoxicillin 250mg/5ml Suspension', 'generic_name' => 'Amoxicillin', 'dosage_form' => 'Suspension', 'strength' => '250mg/5ml', 'unit' => 'Bottle', 'pack_size' => 100, 'unit_price' => 8.00, 'category' => 'ANTIBIOTICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-012', 'drug_name' => 'Metronidazole 400mg Tablet', 'generic_name' => 'Metronidazole', 'dosage_form' => 'Tablet', 'strength' => '400mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.15, 'category' => 'ANTIBIOTICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-013', 'drug_name' => 'Ciprofloxacin 500mg Tablet', 'generic_name' => 'Ciprofloxacin', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.50, 'category' => 'ANTIBIOTICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-014', 'drug_name' => 'Azithromycin 500mg Tablet', 'generic_name' => 'Azithromycin', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 1.50, 'category' => 'ANTIBIOTICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-015', 'drug_name' => 'Doxycycline 100mg Capsule', 'generic_name' => 'Doxycycline', 'dosage_form' => 'Capsule', 'strength' => '100mg', 'unit' => 'Capsule', 'pack_size' => 1, 'unit_price' => 0.25, 'category' => 'ANTIBIOTICS', 'is_essential' => 1),

			// Antimalarials
			array('nhis_code' => 'DRUG-020', 'drug_name' => 'Artemether-Lumefantrine 20/120mg Tablet', 'generic_name' => 'Artemether-Lumefantrine', 'dosage_form' => 'Tablet', 'strength' => '20/120mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.80, 'category' => 'ANTIMALARIALS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-021', 'drug_name' => 'Artesunate 50mg Tablet', 'generic_name' => 'Artesunate', 'dosage_form' => 'Tablet', 'strength' => '50mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.60, 'category' => 'ANTIMALARIALS', 'is_essential' => 1),

			// Antihypertensives
			array('nhis_code' => 'DRUG-030', 'drug_name' => 'Amlodipine 5mg Tablet', 'generic_name' => 'Amlodipine', 'dosage_form' => 'Tablet', 'strength' => '5mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.20, 'category' => 'CARDIOVASCULAR', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-031', 'drug_name' => 'Lisinopril 10mg Tablet', 'generic_name' => 'Lisinopril', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.25, 'category' => 'CARDIOVASCULAR', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-032', 'drug_name' => 'Atenolol 50mg Tablet', 'generic_name' => 'Atenolol', 'dosage_form' => 'Tablet', 'strength' => '50mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.15, 'category' => 'CARDIOVASCULAR', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-033', 'drug_name' => 'Hydrochlorothiazide 25mg Tablet', 'generic_name' => 'Hydrochlorothiazide', 'dosage_form' => 'Tablet', 'strength' => '25mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.10, 'category' => 'CARDIOVASCULAR', 'is_essential' => 1),

			// Antidiabetics
			array('nhis_code' => 'DRUG-040', 'drug_name' => 'Metformin 500mg Tablet', 'generic_name' => 'Metformin', 'dosage_form' => 'Tablet', 'strength' => '500mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.10, 'category' => 'ANTIDIABETICS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-041', 'drug_name' => 'Glibenclamide 5mg Tablet', 'generic_name' => 'Glibenclamide', 'dosage_form' => 'Tablet', 'strength' => '5mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.08, 'category' => 'ANTIDIABETICS', 'is_essential' => 1),

			// Gastrointestinal
			array('nhis_code' => 'DRUG-050', 'drug_name' => 'Omeprazole 20mg Capsule', 'generic_name' => 'Omeprazole', 'dosage_form' => 'Capsule', 'strength' => '20mg', 'unit' => 'Capsule', 'pack_size' => 1, 'unit_price' => 0.30, 'category' => 'GASTROINTESTINAL', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-051', 'drug_name' => 'ORS Sachet', 'generic_name' => 'Oral Rehydration Salts', 'dosage_form' => 'Powder', 'strength' => 'Standard', 'unit' => 'Sachet', 'pack_size' => 1, 'unit_price' => 0.50, 'category' => 'GASTROINTESTINAL', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-052', 'drug_name' => 'Loperamide 2mg Capsule', 'generic_name' => 'Loperamide', 'dosage_form' => 'Capsule', 'strength' => '2mg', 'unit' => 'Capsule', 'pack_size' => 1, 'unit_price' => 0.20, 'category' => 'GASTROINTESTINAL', 'is_essential' => 1),

			// Vitamins & Supplements
			array('nhis_code' => 'DRUG-060', 'drug_name' => 'Ferrous Sulphate 200mg Tablet', 'generic_name' => 'Ferrous Sulphate', 'dosage_form' => 'Tablet', 'strength' => '200mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.05, 'category' => 'VITAMINS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-061', 'drug_name' => 'Folic Acid 5mg Tablet', 'generic_name' => 'Folic Acid', 'dosage_form' => 'Tablet', 'strength' => '5mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.03, 'category' => 'VITAMINS', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-062', 'drug_name' => 'Vitamin B Complex Tablet', 'generic_name' => 'Vitamin B Complex', 'dosage_form' => 'Tablet', 'strength' => 'Standard', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.05, 'category' => 'VITAMINS', 'is_essential' => 1),

			// Respiratory
			array('nhis_code' => 'DRUG-070', 'drug_name' => 'Salbutamol 4mg Tablet', 'generic_name' => 'Salbutamol', 'dosage_form' => 'Tablet', 'strength' => '4mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.10, 'category' => 'RESPIRATORY', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-071', 'drug_name' => 'Salbutamol Inhaler 100mcg', 'generic_name' => 'Salbutamol', 'dosage_form' => 'Inhaler', 'strength' => '100mcg', 'unit' => 'Inhaler', 'pack_size' => 200, 'unit_price' => 15.00, 'category' => 'RESPIRATORY', 'is_essential' => 1),

			// Antihistamines
			array('nhis_code' => 'DRUG-080', 'drug_name' => 'Chlorpheniramine 4mg Tablet', 'generic_name' => 'Chlorpheniramine', 'dosage_form' => 'Tablet', 'strength' => '4mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.05, 'category' => 'ANTIHISTAMINES', 'is_essential' => 1),
			array('nhis_code' => 'DRUG-081', 'drug_name' => 'Cetirizine 10mg Tablet', 'generic_name' => 'Cetirizine', 'dosage_form' => 'Tablet', 'strength' => '10mg', 'unit' => 'Tablet', 'pack_size' => 1, 'unit_price' => 0.15, 'category' => 'ANTIHISTAMINES', 'is_essential' => 1)
		);

		foreach ($tariffs as $t) {
			$this->db->insert('nhis_drug_tariffs', $t);
		}
	}

	// =========================================================================
	// HELPER METHODS
	// =========================================================================

	private function _table_exists($table)
	{
		return $this->db->table_exists($table);
	}

	private function _column_exists($table, $column)
	{
		$fields = $this->db->field_data($table);
		foreach ($fields as $field) {
			if ($field->name === $column) return true;
		}
		return false;
	}

	// =========================================================================
	// 1. NHIS DRUG CODE MAPPING
	// =========================================================================

	/**
	 * Get all NHIS drug tariffs
	 */
	public function get_nhis_drug_tariffs($filters = array())
	{
		$this->db->from('nhis_drug_tariffs');

		if (!empty($filters['category'])) {
			$this->db->where('category', $filters['category']);
		}
		if (!empty($filters['search'])) {
			$this->db->group_start();
			$this->db->like('nhis_code', $filters['search']);
			$this->db->or_like('drug_name', $filters['search']);
			$this->db->or_like('generic_name', $filters['search']);
			$this->db->group_end();
		}
		if (isset($filters['is_active'])) {
			$this->db->where('is_active', $filters['is_active']);
		} else {
			$this->db->where('is_active', 1);
		}

		$this->db->order_by('drug_name', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Get NHIS drug tariff by code
	 */
	public function get_nhis_tariff_by_code($nhis_code)
	{
		return $this->db->get_where('nhis_drug_tariffs', array('nhis_code' => $nhis_code, 'is_active' => 1))->row();
	}

	/**
	 * Get NHIS drug tariff by ID
	 */
	public function get_nhis_tariff($tariff_id)
	{
		return $this->db->get_where('nhis_drug_tariffs', array('tariff_id' => (int)$tariff_id))->row();
	}

	/**
	 * Search NHIS drug tariffs
	 */
	public function search_nhis_drug_tariffs($term, $limit = 20)
	{
		$this->db->group_start();
		$this->db->like('nhis_code', $term);
		$this->db->or_like('drug_name', $term);
		$this->db->or_like('generic_name', $term);
		$this->db->group_end();
		$this->db->where('is_active', 1);
		$this->db->limit($limit);
		return $this->db->get('nhis_drug_tariffs')->result();
	}

	/**
	 * Map a drug to NHIS tariff
	 */
	public function map_drug_to_nhis($drug_id, $nhis_tariff_id, $user_id = null)
	{
		$tariff = $this->get_nhis_tariff($nhis_tariff_id);
		if (!$tariff) {
			return array('success' => false, 'error' => 'NHIS tariff not found');
		}

		// Check if mapping already exists
		$existing = $this->db->get_where('drug_tariff_mapping', array(
			'drug_id' => (int)$drug_id,
			'nhis_tariff_id' => (int)$nhis_tariff_id
		))->row();

		if ($existing) {
			// Update existing mapping
			$this->db->where('mapping_id', $existing->mapping_id);
			$this->db->update('drug_tariff_mapping', array(
				'nhis_drug_code' => $tariff->nhis_code,
				'nhis_drug_name' => $tariff->drug_name,
				'unit_tariff' => $tariff->unit_price,
				'dosage_form' => $tariff->dosage_form,
				'strength' => $tariff->strength,
				'pack_size' => $tariff->pack_size,
				'requires_authorization' => $tariff->requires_authorization,
				'is_active' => 1,
				'updated_at' => date('Y-m-d H:i:s')
			));
			$mapping_id = $existing->mapping_id;
		} else {
			// Create new mapping
			$this->db->insert('drug_tariff_mapping', array(
				'drug_id' => (int)$drug_id,
				'nhis_tariff_id' => (int)$nhis_tariff_id,
				'nhis_drug_code' => $tariff->nhis_code,
				'nhis_drug_name' => $tariff->drug_name,
				'unit_tariff' => $tariff->unit_price,
				'dosage_form' => $tariff->dosage_form,
				'strength' => $tariff->strength,
				'pack_size' => $tariff->pack_size,
				'requires_authorization' => $tariff->requires_authorization,
				'created_by' => $user_id
			));
			$mapping_id = $this->db->insert_id();
		}

		// Update medicine_drug_name with NHIS info
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->update('medicine_drug_name', array(
			'nhis_drug_code' => $tariff->nhis_code,
			'nhis_drug_name' => $tariff->drug_name,
			'nhis_tariff_id' => $nhis_tariff_id,
			'nhis_unit_tariff' => $tariff->unit_price,
			'nhis_requires_auth' => $tariff->requires_authorization
		));

		return array('success' => true, 'mapping_id' => $mapping_id);
	}

	/**
	 * Remove drug NHIS mapping
	 */
	public function unmap_drug_from_nhis($drug_id)
	{
		// Deactivate mapping
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->update('drug_tariff_mapping', array('is_active' => 0));

		// Clear NHIS info from drug
		$this->db->where('drug_id', (int)$drug_id);
		$this->db->update('medicine_drug_name', array(
			'nhis_drug_code' => null,
			'nhis_drug_name' => null,
			'nhis_tariff_id' => null,
			'nhis_unit_tariff' => 0,
			'nhis_requires_auth' => 0
		));

		return array('success' => true);
	}

	/**
	 * Get drug NHIS mapping
	 */
	public function get_drug_nhis_mapping($drug_id)
	{
		$this->db->select('m.*, t.category, t.therapeutic_class, t.is_essential, t.max_days_supply');
		$this->db->from('drug_tariff_mapping m');
		$this->db->join('nhis_drug_tariffs t', 't.tariff_id = m.nhis_tariff_id', 'left');
		$this->db->where('m.drug_id', (int)$drug_id);
		$this->db->where('m.is_active', 1);
		return $this->db->get()->row();
	}

	/**
	 * Get all drug mappings
	 */
	public function get_all_drug_mappings($filters = array())
	{
		$this->db->select('m.*, d.drug_name as hms_drug_name, d.nPrice, d.nStock, t.category, t.is_essential');
		$this->db->from('drug_tariff_mapping m');
		$this->db->join('medicine_drug_name d', 'd.drug_id = m.drug_id', 'left');
		$this->db->join('nhis_drug_tariffs t', 't.tariff_id = m.nhis_tariff_id', 'left');

		if (isset($filters['is_active'])) {
			$this->db->where('m.is_active', $filters['is_active']);
		}

		$this->db->order_by('d.drug_name', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Get unmapped drugs (drugs without NHIS mapping)
	 */
	public function get_unmapped_drugs()
	{
		$this->db->select('d.*');
		$this->db->from('medicine_drug_name d');
		$this->db->where('(d.nhis_drug_code IS NULL OR d.nhis_drug_code = "")');
		$this->db->order_by('d.drug_name', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Get NHIS tariff categories
	 */
	public function get_nhis_tariff_categories()
	{
		$this->db->distinct();
		$this->db->select('category');
		$this->db->from('nhis_drug_tariffs');
		$this->db->where('is_active', 1);
		$this->db->where('category IS NOT NULL');
		$this->db->order_by('category', 'ASC');
		return $this->db->get()->result();
	}

	// =========================================================================
	// 2. TARIFF MAPPING
	// =========================================================================

	/**
	 * Get tariff for a drug
	 */
	public function get_drug_tariff($drug_id)
	{
		$mapping = $this->get_drug_nhis_mapping($drug_id);
		if ($mapping) {
			return $mapping->unit_tariff;
		}

		// Fallback: check medicine_drug_name directly
		$drug = $this->db->get_where('medicine_drug_name', array('drug_id' => (int)$drug_id))->row();
		if ($drug && $drug->nhis_unit_tariff > 0) {
			return $drug->nhis_unit_tariff;
		}

		return 0;
	}

	/**
	 * Calculate NHIS claim amount for a prescription
	 */
	public function calculate_nhis_amount($drug_id, $quantity)
	{
		$tariff = $this->get_drug_tariff($drug_id);
		return $tariff * $quantity;
	}

	// =========================================================================
	// 3. AUTO CLAIM ITEM CREATION
	// =========================================================================

	/**
	 * Create NHIS claim item on dispense
	 * Called automatically when medication is dispensed to NHIS patient
	 */
	public function create_claim_item_on_dispense($dispense_data)
	{
		$patient_no = $dispense_data['patient_no'];
		$drug_id = $dispense_data['drug_id'];
		$quantity = $dispense_data['quantity'];
		$iop_med_id = isset($dispense_data['iop_med_id']) ? $dispense_data['iop_med_id'] : null;
		$admin_id = isset($dispense_data['admin_id']) ? $dispense_data['admin_id'] : null;

		// Check if patient is NHIS
		$verification = $this->verify_nhis_membership($patient_no);
		if ($verification['status'] !== self::VERIFICATION_VALID) {
			return array(
				'success' => false,
				'error' => 'NHIS membership not valid: ' . $verification['message'],
				'verification' => $verification
			);
		}

		// Get drug NHIS mapping
		$mapping = $this->get_drug_nhis_mapping($drug_id);
		if (!$mapping) {
			// Drug not mapped to NHIS
			return array(
				'success' => false,
				'error' => 'Drug not mapped to NHIS tariff',
				'drug_id' => $drug_id
			);
		}

		// Get or create claim for this patient/visit
		$claim_id = $this->_get_or_create_claim_for_patient($patient_no, $dispense_data);

		// Calculate tariff amount
		$tariff_amount = $mapping->unit_tariff * $quantity;

		// Insert claim item
		$item_data = array(
			'claim_id' => $claim_id,
			'service_code' => $mapping->nhis_drug_code,
			'service_name' => $mapping->nhis_drug_name,
			'category' => 'PHARMACY',
			'quantity' => $quantity,
			'tariff' => $mapping->unit_tariff,
			'amount' => $tariff_amount
		);

		$this->db->insert('nhis_claim_items', $item_data);
		$claim_item_id = $this->db->insert_id();

		// Update claim total
		$this->_recalculate_claim_total($claim_id);

		// Update dispensing record with claim info
		if ($admin_id) {
			$this->db->where('id', $admin_id);
			$this->db->update('iop_medication_administration', array(
				'nhis_claim_id' => $claim_id,
				'nhis_claim_item_id' => $claim_item_id,
				'nhis_tariff_amount' => $tariff_amount,
				'nhis_verification_status' => 'CLAIMED'
			));
		}

		return array(
			'success' => true,
			'claim_id' => $claim_id,
			'claim_item_id' => $claim_item_id,
			'tariff_amount' => $tariff_amount
		);
	}

	/**
	 * Get or create claim for patient
	 */
	private function _get_or_create_claim_for_patient($patient_no, $dispense_data)
	{
		$CI = get_instance();
		$router_class = isset($CI->router->class) ? $CI->router->class : null;
		$router_method = isset($CI->router->method) ? $CI->router->method : null;
		$uri = function_exists('uri_string') ? uri_string() : null;
		log_message('error', 'NHIS_CLAIM_CALL_TRACE: ' . json_encode(array(
			'method' => __METHOD__,
			'file' => __FILE__,
			'uri' => $uri,
			'router_class' => $router_class,
			'router_method' => $router_method,
			'patient_no' => isset($patient_no) ? $patient_no : null,
			'iop_id' => isset($dispense_data['visit_id']) ? $dispense_data['visit_id'] : null
		)));

		$today = date('Y-m-d');

		// Check for existing draft claim for today
		$this->db->where('patient_no', $patient_no);
		$this->db->where('claim_date', $today);
		$this->db->where_in('status', array('DRAFT', 'READY'));
		$existing = $this->db->get('nhis_claims')->row();

		if ($existing) {
			return $existing->id;
		}

		// Create new claim
		$claim_number = $this->_generate_claim_number();
		$trace = array(
			'method' => __METHOD__,
			'file' => __FILE__,
			'uri' => function_exists('uri_string') ? uri_string() : null,
			'patient_no' => $patient_no,
			'iop_id' => isset($dispense_data['visit_id']) ? $dispense_data['visit_id'] : null,
			'claim_number' => $claim_number
		);
		log_message('error', 'NHIS_CLAIM_INSERT_TRACE: ' . json_encode($trace));
		$this->db->insert('nhis_claims', array(
			'claim_number' => $claim_number,
			'patient_no' => $patient_no,
			'visit_id' => isset($dispense_data['visit_id']) ? $dispense_data['visit_id'] : null,
			'invoice_id' => isset($dispense_data['invoice_id']) ? $dispense_data['invoice_id'] : null,
			'claim_date' => $today,
			'claim_month' => date('Y-m'),
			'status' => 'DRAFT',
			'created_by' => $this->session->userdata('user_id')
		));

		return $this->db->insert_id();
	}

	/**
	 * Generate claim number
	 */
	private function _generate_claim_number()
	{
		$prefix = 'NHIS-' . date('Ym') . '-';
		$this->db->like('claim_number', $prefix, 'after');
		$this->db->order_by('claim_id', 'DESC');
		$last = $this->db->get('nhis_claims')->row();
		$num = $last ? ((int)substr($last->claim_number, -4) + 1) : 1;
		return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
	}

	/**
	 * Recalculate claim total
	 */
	private function _recalculate_claim_total($claim_id)
	{
		$total = $this->db->query("SELECT SUM(amount) as t FROM nhis_claim_items WHERE claim_id = ?", array($claim_id))->row()->t;
		$this->db->where('id', $claim_id);
		$this->db->update('nhis_claims', array('total_amount' => $total ?: 0));
	}

	// =========================================================================
	// 4. MEMBERSHIP VERIFICATION
	// =========================================================================

	/**
	 * Verify NHIS membership before dispense
	 * Returns verification status and details
	 */
	public function verify_nhis_membership($patient_no)
	{
		// Get patient NHIS info
		$membership = $this->db->get_where('nhis_memberships', array('patient_no' => $patient_no))->row();

		if (!$membership) {
			// Check patient_personal_info for NHIS number
			$patient = $this->db->select('nhis_no, nhis_expiry')
				->get_where('patient_personal_info', array('patient_no' => $patient_no))
				->row();

			if (!$patient || empty($patient->nhis_no)) {
				return array(
					'status' => self::VERIFICATION_NOT_FOUND,
					'message' => 'No NHIS membership found for this patient',
					'can_dispense' => false
				);
			}

			// Create membership record from patient info
			$expiry_date = !empty($patient->nhis_expiry) ? $patient->nhis_expiry : null;
			$this->db->insert('nhis_memberships', array(
				'patient_no' => $patient_no,
				'nhis_number' => $patient->nhis_no,
				'expiry_date' => $expiry_date,
				'status' => $expiry_date && strtotime($expiry_date) >= strtotime(date('Y-m-d')) ? 'ACTIVE' : 'EXPIRED'
			));

			$membership = $this->db->get_where('nhis_memberships', array('patient_no' => $patient_no))->row();
		}

		// Check membership status
		if ($membership->status === 'INVALID') {
			return array(
				'status' => self::VERIFICATION_INVALID,
				'message' => 'NHIS membership is invalid',
				'nhis_number' => $membership->nhis_number,
				'can_dispense' => false
			);
		}

		// Check expiry date
		if ($membership->expiry_date) {
			$expiry = strtotime($membership->expiry_date);
			$today = strtotime(date('Y-m-d'));

			if ($expiry < $today) {
				// Update status to expired
				$this->db->where('id', $membership->id);
				$this->db->update('nhis_memberships', array('status' => 'EXPIRED'));

				return array(
					'status' => self::VERIFICATION_EXPIRED,
					'message' => 'NHIS membership expired on ' . date('d M Y', $expiry),
					'nhis_number' => $membership->nhis_number,
					'expiry_date' => $membership->expiry_date,
					'can_dispense' => false
				);
			}
		}

		// Membership is valid
		return array(
			'status' => self::VERIFICATION_VALID,
			'message' => 'NHIS membership is active',
			'nhis_number' => $membership->nhis_number,
			'expiry_date' => $membership->expiry_date,
			'member_name' => $membership->member_name,
			'can_dispense' => true
		);
	}

	/**
	 * Check if patient can receive NHIS-covered dispensing
	 */
	public function can_dispense_nhis($patient_no, $drug_id = null)
	{
		$verification = $this->verify_nhis_membership($patient_no);

		if (!$verification['can_dispense']) {
			return $verification;
		}

		// If drug_id provided, check if drug is NHIS-covered
		if ($drug_id) {
			$mapping = $this->get_drug_nhis_mapping($drug_id);
			if (!$mapping) {
				return array(
					'status' => 'NOT_COVERED',
					'message' => 'Drug is not covered by NHIS',
					'can_dispense' => true, // Can still dispense, just not NHIS-covered
					'nhis_covered' => false
				);
			}

			// Check if drug requires authorization
			if ($mapping->requires_authorization) {
				// TODO: Check for valid authorization
				return array(
					'status' => 'REQUIRES_AUTH',
					'message' => 'Drug requires NHIS pre-authorization',
					'can_dispense' => false,
					'nhis_covered' => true,
					'requires_authorization' => true
				);
			}

			$verification['nhis_covered'] = true;
			$verification['tariff'] = $mapping->unit_tariff;
			$verification['nhis_drug_code'] = $mapping->nhis_drug_code;
		}

		return $verification;
	}

	// =========================================================================
	// 5. CLAIM VALIDATION LAYER
	// =========================================================================

	/**
	 * Validate a claim before submission
	 */
	public function validate_claim($claim_id)
	{
		$errors = array();
		$warnings = array();

		// Get claim
		$claim = $this->db->select('c.*, m.nhis_number, m.status as membership_status, m.expiry_date')
			->from('nhis_claims c')
			->join('nhis_memberships m', 'm.patient_no = c.patient_no', 'left')
			->where('c.id', $claim_id)
			->get()->row();

		if (!$claim) {
			return array('valid' => false, 'errors' => array('Claim not found'));
		}

		// 1. Validate membership
		if (!$claim->nhis_number) {
			$errors[] = array('code' => 'NO_MEMBERSHIP', 'message' => 'No NHIS membership found');
		} elseif ($claim->membership_status !== 'ACTIVE') {
			$errors[] = array('code' => 'INACTIVE_MEMBERSHIP', 'message' => 'NHIS membership is not active');
		} elseif ($claim->expiry_date && strtotime($claim->expiry_date) < strtotime($claim->claim_date)) {
			$errors[] = array('code' => 'EXPIRED_MEMBERSHIP', 'message' => 'NHIS membership was expired on claim date');
		}

		// 2. Validate diagnosis
		$diagnoses = $this->db->get_where('nhis_diagnosis', array('claim_id' => $claim_id))->result();
		if (empty($diagnoses)) {
			$errors[] = array('code' => 'NO_DIAGNOSIS', 'message' => 'No diagnosis attached to claim');
		} else {
			$has_primary = false;
			foreach ($diagnoses as $d) {
				if ($d->diagnosis_type === 'PRIMARY') $has_primary = true;

				// Validate ICD-10 code exists
				$icd = $this->db->get_where('icd10_codes', array('code' => $d->icd10_code, 'is_active' => 1))->row();
				if (!$icd) {
					$errors[] = array('code' => 'INVALID_ICD10', 'message' => 'Invalid ICD-10 code: ' . $d->icd10_code);
				}
			}
			if (!$has_primary) {
				$errors[] = array('code' => 'NO_PRIMARY_DIAGNOSIS', 'message' => 'No primary diagnosis specified');
			}
		}

		// 3. Validate claim items
		$items = $this->db->get_where('nhis_claim_items', array('claim_id' => $claim_id))->result();
		if (empty($items)) {
			$errors[] = array('code' => 'NO_ITEMS', 'message' => 'No services/drugs attached to claim');
		} else {
			foreach ($items as $item) {
				// Validate drug code
				$this->_validate_claim_item($claim_id, $item, $errors, $warnings);
			}
		}

		// 4. Validate amount
		if ($claim->total_amount <= 0) {
			$errors[] = array('code' => 'INVALID_AMOUNT', 'message' => 'Claim amount must be greater than zero');
		}

		// Log validation results
		$this->_log_validation($claim_id, $errors, $warnings);

		// Update claim status
		$valid = empty($errors);
		$this->db->where('id', $claim_id);
		$this->db->update('nhis_claims', array(
			'validation_errors' => $valid ? null : json_encode($errors),
			'status' => $valid ? 'READY' : 'DRAFT'
		));

		return array(
			'valid' => $valid,
			'errors' => $errors,
			'warnings' => $warnings,
			'claim' => $claim
		);
	}

	/**
	 * Validate a single claim item
	 */
	private function _validate_claim_item($claim_id, $item, &$errors, &$warnings)
	{
		// Check drug code exists in NHIS tariffs
		$tariff = $this->db->get_where('nhis_drug_tariffs', array('nhis_code' => $item->service_code, 'is_active' => 1))->row();

		if (!$tariff && $item->category === 'PHARMACY') {
			$errors[] = array(
				'code' => 'INVALID_DRUG_CODE',
				'message' => 'Invalid NHIS drug code: ' . $item->service_code,
				'item_id' => $item->id
			);
			return;
		}

		// Validate quantity
		if ($item->quantity <= 0) {
			$errors[] = array(
				'code' => 'INVALID_QUANTITY',
				'message' => 'Invalid quantity for ' . $item->service_name,
				'item_id' => $item->id
			);
		}

		// Check max quantity if applicable
		if ($tariff && $tariff->max_days_supply && $item->quantity > $tariff->max_days_supply) {
			$warnings[] = array(
				'code' => 'EXCEEDS_MAX_SUPPLY',
				'message' => 'Quantity exceeds max days supply for ' . $item->service_name,
				'item_id' => $item->id,
				'max_allowed' => $tariff->max_days_supply
			);
		}

		// Validate tariff amount
		if ($tariff && $item->tariff != $tariff->unit_price) {
			$warnings[] = array(
				'code' => 'TARIFF_MISMATCH',
				'message' => 'Tariff amount mismatch for ' . $item->service_name . '. Expected: ' . $tariff->unit_price . ', Got: ' . $item->tariff,
				'item_id' => $item->id
			);
		}

		// Validate amount calculation
		$expected_amount = $item->quantity * $item->tariff;
		if (abs($item->amount - $expected_amount) > 0.01) {
			$errors[] = array(
				'code' => 'AMOUNT_CALCULATION_ERROR',
				'message' => 'Amount calculation error for ' . $item->service_name,
				'item_id' => $item->id,
				'expected' => $expected_amount,
				'actual' => $item->amount
			);
		}
	}

	/**
	 * Log validation results
	 */
	private function _log_validation($claim_id, $errors, $warnings)
	{
		foreach ($errors as $e) {
			$this->db->insert('nhis_claim_validation_log', array(
				'claim_id' => $claim_id,
				'claim_item_id' => isset($e['item_id']) ? $e['item_id'] : null,
				'validation_type' => 'CLAIM_VALIDATION',
				'validation_result' => 'FAIL',
				'error_code' => $e['code'],
				'error_message' => $e['message']
			));
		}

		foreach ($warnings as $w) {
			$this->db->insert('nhis_claim_validation_log', array(
				'claim_id' => $claim_id,
				'claim_item_id' => isset($w['item_id']) ? $w['item_id'] : null,
				'validation_type' => 'CLAIM_VALIDATION',
				'validation_result' => 'WARNING',
				'error_code' => $w['code'],
				'error_message' => $w['message']
			));
		}
	}

	/**
	 * Validate dispense before processing
	 */
	public function validate_dispense($patient_no, $drug_id, $quantity)
	{
		$errors = array();

		// 1. Verify membership
		$verification = $this->verify_nhis_membership($patient_no);
		if (!$verification['can_dispense']) {
			$errors[] = array(
				'code' => 'MEMBERSHIP_' . $verification['status'],
				'message' => $verification['message']
			);
		}

		// 2. Check drug mapping
		$mapping = $this->get_drug_nhis_mapping($drug_id);
		if (!$mapping) {
			$errors[] = array(
				'code' => 'DRUG_NOT_MAPPED',
				'message' => 'Drug is not mapped to NHIS tariff'
			);
		} else {
			// 3. Check authorization requirement
			if ($mapping->requires_authorization) {
				$errors[] = array(
					'code' => 'REQUIRES_AUTHORIZATION',
					'message' => 'Drug requires NHIS pre-authorization'
				);
			}

			// 4. Check max quantity
			if ($mapping->max_quantity_per_visit && $quantity > $mapping->max_quantity_per_visit) {
				$errors[] = array(
					'code' => 'EXCEEDS_MAX_QUANTITY',
					'message' => 'Quantity exceeds maximum allowed per visit (' . $mapping->max_quantity_per_visit . ')'
				);
			}
		}

		return array(
			'valid' => empty($errors),
			'errors' => $errors,
			'verification' => $verification,
			'mapping' => $mapping
		);
	}

	// =========================================================================
	// STATISTICS & REPORTING
	// =========================================================================

	/**
	 * Get NHIS pharmacy statistics
	 */
	public function get_nhis_pharmacy_stats()
	{
		$stats = array(
			'mapped_drugs' => 0,
			'unmapped_drugs' => 0,
			'total_tariffs' => 0,
			'claims_today' => 0,
			'pending_claims' => 0,
			'monthly_claim_amount' => 0
		);

		// Total mapped drugs
		if ($this->table_exists('drug_tariff_mapping')) {
			$stats['mapped_drugs'] = $this->db->where('is_active', 1)->count_all_results('drug_tariff_mapping');
		}

		// Total unmapped drugs
		$stats['unmapped_drugs'] = count($this->get_unmapped_drugs());

		// Total NHIS tariffs
		if ($this->table_exists('nhis_drug_tariffs')) {
			$stats['total_tariffs'] = $this->db->where('is_active', 1)->count_all_results('nhis_drug_tariffs');
		}

		// Claims today - only if nhis_claims table exists with required columns
		if ($this->table_exists('nhis_claims') && $this->_column_exists('nhis_claims', 'claim_date')) {
			try {
				$stats['claims_today'] = $this->db->where('claim_date', date('Y-m-d'))->count_all_results('nhis_claims');

				// Pending claims
				if ($this->_column_exists('nhis_claims', 'status')) {
					$stats['pending_claims'] = $this->db->where_in('status', array('DRAFT', 'READY'))->count_all_results('nhis_claims');
				}

				// Total claim amount this month
				if ($this->_column_exists('nhis_claims', 'claim_month') && $this->_column_exists('nhis_claims', 'total_amount')) {
					$this->db->select_sum('total_amount', 'total');
					$this->db->where('claim_month', date('Y-m'));
					$result = $this->db->get('nhis_claims')->row();
					$stats['monthly_claim_amount'] = $result ? ($result->total ?: 0) : 0;
				}
			} catch (Exception $e) {
				// Silently fail if table schema is incomplete
				log_message('debug', 'NHIS claims stats error: ' . $e->getMessage());
			}
		}

		return $stats;
	}

	/**
	 * Get validation error summary
	 */
	public function get_validation_error_summary($date_from = null, $date_to = null)
	{
		$this->db->select('error_code, COUNT(*) as count');
		$this->db->from('nhis_claim_validation_log');
		$this->db->where('validation_result', 'FAIL');

		if ($date_from) {
			$this->db->where('created_at >=', $date_from);
		}
		if ($date_to) {
			$this->db->where('created_at <=', $date_to . ' 23:59:59');
		}

		$this->db->group_by('error_code');
		$this->db->order_by('count', 'DESC');

		return $this->db->get()->result();
	}

	// =========================================================================
	// NHIS DRUG MAPPING TOOL (Phase 1 Critical Fix)
	// =========================================================================

	/**
	 * Get unmapped drugs with filters and pagination
	 */
	public function get_unmapped_drugs_paginated($filters = array(), $limit = 50, $offset = 0)
	{
		$this->db->select('d.drug_id, d.drug_name, d.generic_name, d.strength, d.dosage_form, d.nPrice, d.nStock, d.category_id, c.category_name');
		$this->db->from('medicine_drug_name d');
		$this->db->join('medicine_category c', 'c.category_id = d.category_id', 'left');
		$this->db->where('(d.nhis_drug_code IS NULL OR d.nhis_drug_code = "")');
		$this->db->where('d.InActive', 0);

		if (!empty($filters['search'])) {
			$this->db->group_start();
			$this->db->like('d.drug_name', $filters['search']);
			$this->db->or_like('d.generic_name', $filters['search']);
			$this->db->group_end();
		}
		if (!empty($filters['category_id'])) {
			$this->db->where('d.category_id', $filters['category_id']);
		}

		$this->db->order_by('d.drug_name', 'ASC');
		$this->db->limit($limit, $offset);
		return $this->db->get()->result();
	}

	/**
	 * Count unmapped drugs
	 */
	public function count_unmapped_drugs($filters = array())
	{
		$this->db->from('medicine_drug_name d');
		$this->db->where('(d.nhis_drug_code IS NULL OR d.nhis_drug_code = "")');
		$this->db->where('d.InActive', 0);

		if (!empty($filters['search'])) {
			$this->db->group_start();
			$this->db->like('d.drug_name', $filters['search']);
			$this->db->or_like('d.generic_name', $filters['search']);
			$this->db->group_end();
		}
		if (!empty($filters['category_id'])) {
			$this->db->where('d.category_id', $filters['category_id']);
		}

		return $this->db->count_all_results();
	}

	/**
	 * Get mapped drugs with filters
	 */
	public function get_mapped_drugs_paginated($filters = array(), $limit = 50, $offset = 0)
	{
		$this->db->select('d.drug_id, d.drug_name, d.generic_name, d.strength, d.dosage_form, d.nPrice, d.nhis_drug_code, d.nhis_drug_name, d.nhis_unit_tariff, m.mapping_id, m.created_at as mapped_at, t.category as nhis_category');
		$this->db->from('medicine_drug_name d');
		$this->db->join('drug_tariff_mapping m', 'm.drug_id = d.drug_id AND m.is_active = 1', 'inner');
		$this->db->join('nhis_drug_tariffs t', 't.tariff_id = m.nhis_tariff_id', 'left');
		$this->db->where('d.InActive', 0);

		if (!empty($filters['search'])) {
			$this->db->group_start();
			$this->db->like('d.drug_name', $filters['search']);
			$this->db->or_like('d.nhis_drug_code', $filters['search']);
			$this->db->group_end();
		}

		$this->db->order_by('d.drug_name', 'ASC');
		$this->db->limit($limit, $offset);
		return $this->db->get()->result();
	}

	/**
	 * Count mapped drugs
	 */
	public function count_mapped_drugs($filters = array())
	{
		$this->db->from('medicine_drug_name d');
		$this->db->join('drug_tariff_mapping m', 'm.drug_id = d.drug_id AND m.is_active = 1', 'inner');
		$this->db->where('d.InActive', 0);

		if (!empty($filters['search'])) {
			$this->db->group_start();
			$this->db->like('d.drug_name', $filters['search']);
			$this->db->or_like('d.nhis_drug_code', $filters['search']);
			$this->db->group_end();
		}

		return $this->db->count_all_results();
	}

	/**
	 * Auto-match drugs to NHIS tariffs using intelligent matching
	 * Returns array of suggested matches
	 */
	public function auto_match_drugs($drug_ids = array())
	{
		$matches = array();

		// Get drugs to match
		if (empty($drug_ids)) {
			$drugs = $this->get_unmapped_drugs();
		} else {
			$this->db->select('drug_id, drug_name, generic_name, strength, dosage_form');
			$this->db->from('medicine_drug_name');
			$this->db->where_in('drug_id', $drug_ids);
			$this->db->where('(nhis_drug_code IS NULL OR nhis_drug_code = "")');
			$drugs = $this->db->get()->result();
		}

		// Get all active NHIS tariffs
		$tariffs = $this->db->get_where('nhis_drug_tariffs', array('is_active' => 1))->result();

		foreach ($drugs as $drug) {
			$best_match = null;
			$best_score = 0;

			foreach ($tariffs as $tariff) {
				$score = $this->_calculate_match_score($drug, $tariff);
				if ($score > $best_score && $score >= 50) {
					$best_score = $score;
					$best_match = $tariff;
				}
			}

			if ($best_match) {
				$matches[] = array(
					'drug_id' => $drug->drug_id,
					'drug_name' => $drug->drug_name,
					'generic_name' => $drug->generic_name,
					'strength' => $drug->strength,
					'dosage_form' => $drug->dosage_form,
					'suggested_tariff_id' => $best_match->tariff_id,
					'suggested_nhis_code' => $best_match->nhis_code,
					'suggested_nhis_name' => $best_match->drug_name,
					'suggested_tariff' => $best_match->unit_price,
					'match_score' => $best_score
				);
			}
		}

		// Sort by match score descending
		usort($matches, function($a, $b) {
			return $b['match_score'] - $a['match_score'];
		});

		return $matches;
	}

	/**
	 * Calculate match score between HMS drug and NHIS tariff
	 * Returns score 0-100
	 */
	private function _calculate_match_score($drug, $tariff)
	{
		$score = 0;

		$drug_name = strtolower(trim($drug->drug_name));
		$generic_name = strtolower(trim($drug->generic_name ?: ''));
		$strength = strtolower(trim($drug->strength ?: ''));
		$dosage_form = strtolower(trim($drug->dosage_form ?: ''));

		$tariff_name = strtolower(trim($tariff->drug_name));
		$tariff_generic = strtolower(trim($tariff->generic_name ?: ''));
		$tariff_strength = strtolower(trim($tariff->strength ?: ''));
		$tariff_form = strtolower(trim($tariff->dosage_form ?: ''));

		// Exact generic name match (40 points)
		if ($generic_name !== '' && $tariff_generic !== '' && $generic_name === $tariff_generic) {
			$score += 40;
		} elseif ($generic_name !== '' && $tariff_generic !== '' && strpos($tariff_generic, $generic_name) !== false) {
			$score += 30;
		} elseif ($generic_name !== '' && strpos($tariff_name, $generic_name) !== false) {
			$score += 25;
		}

		// Drug name similarity (30 points)
		if (strpos($tariff_name, $drug_name) !== false || strpos($drug_name, $tariff_name) !== false) {
			$score += 30;
		} else {
			// Partial word match
			$drug_words = explode(' ', $drug_name);
			$tariff_words = explode(' ', $tariff_name);
			$common_words = array_intersect($drug_words, $tariff_words);
			if (count($common_words) > 0) {
				$score += min(20, count($common_words) * 10);
			}
		}

		// Strength match (20 points)
		if ($strength !== '' && $tariff_strength !== '') {
			// Normalize strength (remove spaces)
			$norm_strength = str_replace(' ', '', $strength);
			$norm_tariff_strength = str_replace(' ', '', $tariff_strength);
			if ($norm_strength === $norm_tariff_strength) {
				$score += 20;
			} elseif (strpos($norm_tariff_strength, $norm_strength) !== false) {
				$score += 15;
			}
		}

		// Dosage form match (10 points)
		if ($dosage_form !== '' && $tariff_form !== '') {
			if ($dosage_form === $tariff_form) {
				$score += 10;
			} elseif (strpos($tariff_form, $dosage_form) !== false || strpos($dosage_form, $tariff_form) !== false) {
				$score += 5;
			}
		}

		return min(100, $score);
	}

	/**
	 * Bulk save drug mappings
	 */
	public function bulk_save_mapping($mappings, $user_id = null)
	{
		$success = 0;
		$failed = 0;
		$errors = array();

		foreach ($mappings as $mapping) {
			$drug_id = isset($mapping['drug_id']) ? (int)$mapping['drug_id'] : 0;
			$tariff_id = isset($mapping['tariff_id']) ? (int)$mapping['tariff_id'] : 0;

			if ($drug_id <= 0 || $tariff_id <= 0) {
				$failed++;
				$errors[] = "Invalid drug_id or tariff_id";
				continue;
			}

			$result = $this->map_drug_to_nhis($drug_id, $tariff_id, $user_id);
			if ($result['success']) {
				$success++;
				// Log audit
				$this->_log_mapping_audit($drug_id, $tariff_id, 'BULK_MAP', $user_id);
			} else {
				$failed++;
				$errors[] = $result['error'];
			}
		}

		return array(
			'success' => $success,
			'failed' => $failed,
			'errors' => $errors
		);
	}

	/**
	 * Log mapping audit entry
	 */
	private function _log_mapping_audit($drug_id, $tariff_id, $action, $user_id)
	{
		if (!$this->_table_exists('pharmacy_audit_log')) {
			return;
		}

		$drug = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
		$tariff = $this->db->get_where('nhis_drug_tariffs', array('tariff_id' => $tariff_id))->row();

		$this->db->insert('pharmacy_audit_log', array(
			'action_type' => 'NHIS_MAPPING',
			'action' => $action,
			'entity_type' => 'drug_tariff_mapping',
			'entity_id' => $drug_id,
			'details' => json_encode(array(
				'drug_id' => $drug_id,
				'drug_name' => $drug ? $drug->drug_name : '',
				'tariff_id' => $tariff_id,
				'nhis_code' => $tariff ? $tariff->nhis_code : '',
				'nhis_name' => $tariff ? $tariff->drug_name : ''
			)),
			'user_id' => $user_id,
			'created_at' => date('Y-m-d H:i:s')
		));
	}

	/**
	 * Export unmapped drugs as CSV data
	 */
	public function export_unmapped_drugs_csv()
	{
		$drugs = $this->get_unmapped_drugs();

		$csv_data = "Drug ID,Drug Name,Generic Name,Strength,Dosage Form,Category,Price,Stock\n";

		foreach ($drugs as $drug) {
			$csv_data .= sprintf(
				"%d,\"%s\",\"%s\",\"%s\",\"%s\",\"%s\",%.2f,%d\n",
				$drug->drug_id,
				str_replace('"', '""', $drug->drug_name),
				str_replace('"', '""', $drug->generic_name ?: ''),
				str_replace('"', '""', $drug->strength ?: ''),
				str_replace('"', '""', $drug->dosage_form ?: ''),
				'',
				$drug->nPrice ?: 0,
				$drug->nStock ?: 0
			);
		}

		return $csv_data;
	}

	/**
	 * Get drug categories for filter dropdown
	 */
	public function get_drug_categories()
	{
		$this->db->select('category_id, category_name');
		$this->db->from('medicine_category');
		$this->db->where('InActive', 0);
		$this->db->order_by('category_name', 'ASC');
		return $this->db->get()->result();
	}

	/**
	 * Log deprecated view access
	 */
	public function log_deprecated_view_access($view_name, $user_id)
	{
		if (!$this->_table_exists('pharmacy_audit_log')) {
			return;
		}

		$this->db->insert('pharmacy_audit_log', array(
			'action_type' => 'DEPRECATED_VIEW_ACCESS',
			'action' => 'VIEW_ACCESS',
			'entity_type' => 'view',
			'entity_id' => 0,
			'details' => json_encode(array(
				'view_name' => $view_name,
				'redirect_to' => 'pharmacy_dashboard'
			)),
			'user_id' => $user_id,
			'created_at' => date('Y-m-d H:i:s')
		));
	}
}
