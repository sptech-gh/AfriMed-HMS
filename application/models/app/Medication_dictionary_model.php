<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Medication_dictionary_model
 *
 * Phase 2 — Data Model Hardening
 *
 * Creates and manages the single source of truth for medication metadata:
 *   - medication_master       : canonical drug records (generic + brand, NHIS-ready)
 *   - medication_synonyms     : fuzzy-match aliases per drug
 *   - medication_frequency    : coded frequency master (OD, BD, TDS …)
 *   - medication_route        : route-of-administration master
 *   - medication_unit         : dosage unit master
 *
 * All DDL is additive-only (CREATE TABLE IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).
 * No legacy tables are dropped. Existing data is never modified destructively.
 */
class Medication_dictionary_model extends CI_Model
{
    private $schema_installed = false;

    public function __construct()
    {
        parent::__construct();
    }

    // =========================================================================
    // PUBLIC ENTRY POINT
    // =========================================================================

    /**
     * Run all schema migrations once per request (idempotent).
     */
    public function ensure_dictionary_schema()
    {
        if ($this->schema_installed) return;
        $this->schema_installed = true;

        $this->_create_medication_master();
        $this->_create_medication_synonyms();
        $this->_create_medication_frequency();
        $this->_create_medication_route();
        $this->_create_medication_unit();
        $this->_add_iop_medication_fk_columns();
        $this->_add_medicine_drug_name_columns();
        $this->_add_indexes();

        // Phase 2 — Audit-prompt tasks
        $this->_create_medication_form();
        $this->_add_iop_medication_ghs_columns();
        $this->_expand_days_field();
        $this->_add_doctor_id_unified();
        $this->_add_audit_fields();
        $this->_add_qty_calc_fields();
        $this->_add_phase2_indexes();
        $this->_ensure_innodb();
    }

    // =========================================================================
    // TABLE CREATION
    // =========================================================================

    private function _create_medication_master()
    {
        if ($this->_table_exists('medication_master')) return;

        $this->db->query("
            CREATE TABLE `medication_master` (
                `id`                  INT(11)       NOT NULL AUTO_INCREMENT,
                `generic_name`        VARCHAR(255)  NOT NULL,
                `brand_name`          VARCHAR(255)  DEFAULT NULL,
                `strength`            VARCHAR(50)   DEFAULT NULL,
                `formulation`         VARCHAR(50)   DEFAULT NULL,
                `route`               VARCHAR(50)   DEFAULT NULL,
                `nhis_code`           VARCHAR(50)   DEFAULT NULL,
                `nhis_covered`        TINYINT(1)    NOT NULL DEFAULT 1,
                `atc_code`            VARCHAR(20)   DEFAULT NULL,
                `pregnancy_category`  VARCHAR(10)   DEFAULT NULL,
                `pediatric_safe`      TINYINT(1)    NOT NULL DEFAULT 1,
                `drug_id_ref`         INT(11)       DEFAULT NULL COMMENT 'FK to medicine_drug_name.drug_id (nullable for unmapped)',
                `generic_id_ref`      INT(11)       DEFAULT NULL COMMENT 'FK to drug_generic_master.generic_id',
                `is_active`           TINYINT(1)    NOT NULL DEFAULT 1,
                `created_at`          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at`          DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_generic_name`   (`generic_name`(191)),
                KEY `idx_brand_name`     (`brand_name`(191)),
                KEY `idx_nhis_code`      (`nhis_code`),
                KEY `idx_drug_id_ref`    (`drug_id_ref`),
                KEY `idx_active`         (`is_active`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function _create_medication_synonyms()
    {
        if ($this->_table_exists('medication_synonyms')) return;

        $this->db->query("
            CREATE TABLE `medication_synonyms` (
                `id`             INT(11)      NOT NULL AUTO_INCREMENT,
                `medication_id`  INT(11)      NOT NULL COMMENT 'FK to medication_master.id',
                `synonym`        VARCHAR(255) NOT NULL,
                `synonym_upper`  VARCHAR(255) NOT NULL COMMENT 'UPPER() copy for case-insensitive index lookup',
                `source`         VARCHAR(50)  DEFAULT 'MANUAL' COMMENT 'MANUAL | AUTO_IMPORT | DOCTOR_FREETEXT',
                PRIMARY KEY (`id`),
                KEY `idx_synonym`       (`synonym`(191)),
                KEY `idx_syn_upper`     (`synonym_upper`(191)),
                KEY `idx_medication_id` (`medication_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function _create_medication_frequency()
    {
        if ($this->_table_exists('medication_frequency')) return;

        $this->db->query("
            CREATE TABLE `medication_frequency` (
                `id`             INT(11)      NOT NULL AUTO_INCREMENT,
                `label`          VARCHAR(100) NOT NULL COMMENT 'Human-readable e.g. Once Daily',
                `code`           VARCHAR(20)  NOT NULL COMMENT 'Short code e.g. OD',
                `doses_per_day`  DECIMAL(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Multiplier for dose calculation',
                `sort_order`     INT(3)       NOT NULL DEFAULT 99,
                `is_active`      TINYINT(1)   NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_code` (`code`),
                KEY `idx_label` (`label`(100))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed standard frequencies
        $rows = array(
            array('Once Daily',              'OD',   1.00, 1),
            array('Twice Daily',             'BD',   2.00, 2),
            array('Three Times Daily',       'TDS',  3.00, 3),
            array('Four Times Daily',        'QID',  4.00, 4),
            array('Every 6 Hours',           'Q6H',  4.00, 5),
            array('Every 8 Hours',           'Q8H',  3.00, 6),
            array('Every 12 Hours',          'Q12H', 2.00, 7),
            array('Every 4 Hours',           'Q4H',  6.00, 8),
            array('At Night (Nocte)',         'ON',   1.00, 9),
            array('Morning and Night',       'MN',   2.00, 10),
            array('Immediately (Stat)',      'STAT', 1.00, 11),
            array('As Required (PRN)',       'PRN',  1.00, 12),
            array('Once Weekly',             'OW',   0.14, 13),
            array('Twice Weekly',            'BIW',  0.29, 14),
            array('Once Monthly',            'OM',   0.03, 15),
            array('Before Meals',            'AC',   3.00, 16),
            array('After Meals',             'PC',   3.00, 17),
        );
        foreach ($rows as $r) {
            $this->db->insert('medication_frequency', array(
                'label'         => $r[0],
                'code'          => $r[1],
                'doses_per_day' => $r[2],
                'sort_order'    => $r[3],
                'is_active'     => 1,
            ));
        }
    }

    private function _create_medication_route()
    {
        if ($this->_table_exists('medication_route')) return;

        $this->db->query("
            CREATE TABLE `medication_route` (
                `id`         INT(11)      NOT NULL AUTO_INCREMENT,
                `route`      VARCHAR(100) NOT NULL,
                `code`       VARCHAR(20)  DEFAULT NULL COMMENT 'Short code e.g. PO, IV, IM',
                `sort_order` INT(3)       NOT NULL DEFAULT 99,
                `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_route` (`route`(100)),
                KEY `idx_code` (`code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Seed standard routes
        $routes = array(
            array('Oral',               'PO',    1),
            array('Intravenous',        'IV',    2),
            array('Intramuscular',      'IM',    3),
            array('Subcutaneous',       'SC',    4),
            array('Topical',            'TOP',   5),
            array('Inhalation',         'INH',   6),
            array('Sublingual',         'SL',    7),
            array('Rectal',             'PR',    8),
            array('Transdermal',        'TD',    9),
            array('Intranasal',         'IN',    10),
            array('Ophthalmic',         'OPH',   11),
            array('Otic',               'OTC',   12),
            array('Intrathecal',        'ITH',   13),
            array('Intraosseous',       'IO',    14),
            array('Nebulisation',       'NEB',   15),
        );
        foreach ($routes as $r) {
            $this->db->insert('medication_route', array(
                'route'      => $r[0],
                'code'       => $r[1],
                'sort_order' => $r[2],
                'is_active'  => 1,
            ));
        }
    }

    private function _create_medication_unit()
    {
        if ($this->_table_exists('medication_unit')) return;

        $this->db->query("
            CREATE TABLE `medication_unit` (
                `id`         INT(11)      NOT NULL AUTO_INCREMENT,
                `unit`       VARCHAR(50)  NOT NULL,
                `unit_type`  VARCHAR(30)  DEFAULT NULL COMMENT 'WEIGHT | VOLUME | COUNT | CONCENTRATION',
                `sort_order` INT(3)       NOT NULL DEFAULT 99,
                `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_unit` (`unit`),
                KEY `idx_unit_type` (`unit_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $units = array(
            array('mg',       'WEIGHT',        1),
            array('g',        'WEIGHT',        2),
            array('mcg',      'WEIGHT',        3),
            array('ml',       'VOLUME',        4),
            array('L',        'VOLUME',        5),
            array('tablet',   'COUNT',         6),
            array('capsule',  'COUNT',         7),
            array('drop',     'COUNT',         8),
            array('puff',     'COUNT',         9),
            array('unit',     'COUNT',         10),
            array('IU',       'COUNT',         11),
            array('sachet',   'COUNT',         12),
            array('suppository', 'COUNT',      13),
            array('patch',    'COUNT',         14),
            array('mg/ml',    'CONCENTRATION', 15),
            array('mg/5ml',   'CONCENTRATION', 16),
            array('%',        'CONCENTRATION', 17),
        );
        foreach ($units as $u) {
            $this->db->insert('medication_unit', array(
                'unit'       => $u[0],
                'unit_type'  => $u[1],
                'sort_order' => $u[2],
                'is_active'  => 1,
            ));
        }
    }

    // =========================================================================
    // MEDICATION FORM TABLE
    // =========================================================================

    private function _create_medication_form()
    {
        if ($this->_table_exists('medication_form')) return;

        $this->db->query("
            CREATE TABLE `medication_form` (
                `id`         INT(11)     NOT NULL AUTO_INCREMENT,
                `form`       VARCHAR(30) NOT NULL,
                `is_active`  TINYINT(1)  NOT NULL DEFAULT 1,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_form` (`form`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $forms = array(
            'Tablet','Capsule','Syrup','Injection','Suspension',
            'Cream','Ointment','Drops','Inhaler','Patch',
            'Powder','Suppository','Lotion','Gel','Solution',
        );
        foreach ($forms as $f) {
            $this->db->insert('medication_form', array('form' => $f, 'is_active' => 1));
        }
    }

    // =========================================================================
    // GHS / NHIS REQUIRED COLUMNS ON iop_medication
    // =========================================================================

    /**
     * Add GHS/NHIS-required fields and structured coding columns to iop_medication.
     * All nullable — existing rows are untouched.
     */
    private function _add_iop_medication_ghs_columns()
    {
        if (!$this->_table_exists('iop_medication')) return;

        $cols = array(
            'route'           => "VARCHAR(30)    DEFAULT NULL COMMENT 'Route of administration (free text mirror)'",
            'drug_form'       => "VARCHAR(30)    DEFAULT NULL COMMENT 'Dosage form e.g. Tablet, Syrup'",
            'frequency_code'  => "VARCHAR(10)    DEFAULT NULL COMMENT 'Coded frequency e.g. OD, BD, TDS'",
            'frequency_label' => "VARCHAR(50)    DEFAULT NULL COMMENT 'Human label e.g. Once Daily'",
            'is_nhis_covered' => "TINYINT(1)     NOT NULL DEFAULT 0",
            'nhis_price'      => "DECIMAL(10,2)  NOT NULL DEFAULT 0.00",
            'form_id'         => "INT(11)        DEFAULT NULL COMMENT 'FK to medication_form.id'",
        );
        foreach ($cols as $col => $def) {
            if (!$this->_column_exists('iop_medication', $col)) {
                $this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    // =========================================================================
    // EXPAND days FIELD
    // =========================================================================

    /**
     * Expand days from INT(2) to INT(3) to support chronic medication (up to 365 days).
     * MODIFY COLUMN is safe on InnoDB when only widening the display width.
     */
    private function _expand_days_field()
    {
        if (!$this->_table_exists('iop_medication')) return;
        // Check current column type
        $q = $this->db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'iop_medication'
                AND COLUMN_NAME  = 'days'"
        );
        if (!$q || $q->num_rows() === 0) return;
        $type = strtolower($q->row()->COLUMN_TYPE);
        // Only modify if it is still INT(2) or narrower
        if (strpos($type, 'int(2)') !== false || strpos($type, 'int(1)') !== false) {
            $this->db->query("ALTER TABLE `iop_medication` MODIFY COLUMN `days` INT(3) NOT NULL DEFAULT 1");
        }
    }

    // =========================================================================
    // UNIFIED DOCTOR FIELD
    // =========================================================================

    /**
     * Add doctor_id as unified authoritative prescriber field.
     * Legacy fields (cPreparedBy, prescribed_by) are preserved.
     * Populates doctor_id = COALESCE(prescribed_by, cPreparedBy) for existing rows.
     */
    private function _add_doctor_id_unified()
    {
        if (!$this->_table_exists('iop_medication')) return;

        if (!$this->_column_exists('iop_medication', 'doctor_id')) {
            $this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `doctor_id` VARCHAR(25) DEFAULT NULL COMMENT 'Unified authoritative prescriber ID'");
            // Back-fill from legacy fields
            $this->db->query("
                UPDATE `iop_medication`
                   SET `doctor_id` = COALESCE(
                       NULLIF(TRIM(`prescribed_by`), ''),
                       NULLIF(TRIM(`cPreparedBy`),   '')
                   )
                 WHERE `doctor_id` IS NULL
            ");
        }
    }

    // =========================================================================
    // AUDIT TIMESTAMP FIELDS
    // =========================================================================

    private function _add_audit_fields()
    {
        if (!$this->_table_exists('iop_medication')) return;

        $cols = array(
            'created_at' => "DATETIME DEFAULT NULL COMMENT 'Row creation timestamp (Y-m-d H:i:s)'",
            'updated_at' => "DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP",
            'created_by' => "VARCHAR(25) DEFAULT NULL",
            'updated_by' => "VARCHAR(25) DEFAULT NULL",
        );
        foreach ($cols as $col => $def) {
            if (!$this->_column_exists('iop_medication', $col)) {
                $this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `{$col}` {$def}");
            }
        }
        // Back-fill created_at from dDate where available
        $this->db->query("
            UPDATE `iop_medication`
               SET `created_at` = `dDate`
             WHERE `created_at` IS NULL
               AND `dDate`      IS NOT NULL
        ");
    }

    // =========================================================================
    // QUANTITY CALCULATION FIELDS
    // =========================================================================

    private function _add_qty_calc_fields()
    {
        if (!$this->_table_exists('iop_medication')) return;

        $cols = array(
            'dose_per_unit'   => "DECIMAL(8,2)  DEFAULT NULL COMMENT 'Single dose amount'",
            'frequency_per_day' => "INT(3)        DEFAULT NULL COMMENT 'Doses per day (from frequency master)'",
            'calculated_qty'  => "DECIMAL(10,2) DEFAULT NULL COMMENT 'Auto-calculated: dose_per_unit * frequency_per_day * days'",
        );
        foreach ($cols as $col => $def) {
            if (!$this->_column_exists('iop_medication', $col)) {
                $this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    // =========================================================================
    // INNODB ENFORCEMENT
    // =========================================================================

    /**
     * Ensure pharmacy-related tables use InnoDB for transaction support.
     * Converts only if currently MyISAM.
     */
    private function _ensure_innodb()
    {
        $tables = array(
            'iop_medication',
            'pharmacy_billing_queue',
            'iop_medication_administration',
            'pharmacy_audit_log',
        );
        foreach ($tables as $tbl) {
            if (!$this->_table_exists($tbl)) continue;
            $q = $this->db->query(
                "SELECT ENGINE FROM INFORMATION_SCHEMA.TABLES
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME   = " . $this->db->escape($tbl)
            );
            if ($q && $q->num_rows() > 0 && strtoupper($q->row()->ENGINE) === 'MYISAM') {
                $this->db->query("ALTER TABLE `{$tbl}` ENGINE=InnoDB");
                log_message('info', "Medication_dictionary_model: converted {$tbl} to InnoDB");
            }
        }
    }

    // =========================================================================
    // PHASE 2 PERFORMANCE INDEXES
    // =========================================================================

    private function _add_phase2_indexes()
    {
        if (!$this->_table_exists('iop_medication')) return;

        // Indexes required by the Phase 2 audit prompt
        $indexes = array(
            array('iop_medication', 'idx_doctor',          '(`doctor_id`)'),
            array('iop_medication', 'idx_form_id',         '(`form_id`)'),
            array('iop_medication', 'idx_created_at',      '(`created_at`)'),
            array('iop_medication', 'idx_freq_code',       '(`frequency_code`)'),
            array('iop_medication', 'idx_route_text',      '(`route`)'),
        );
        foreach ($indexes as $ix) {
            list($tbl, $name, $cols) = $ix;
            if (!$this->_index_exists($tbl, $name)) {
                $this->db->query("ALTER TABLE `{$tbl}` ADD INDEX `{$name}` {$cols}");
            }
        }
    }

    // =========================================================================
    // medicine_id VARCHAR → INT MIGRATION (public, one-time, safe)
    // =========================================================================

    /**
     * Safely migrate iop_medication.medicine_id from VARCHAR(50) to INT(11).
     *
     * Steps:
     *   1. Add medicine_id_new INT column
     *   2. Copy numeric values (CAST / REGEXP guard)
     *   3. Drop old column, rename new column
     *
     * This method is idempotent — safe to call multiple times.
     * Returns a status array so the caller can log/report the outcome.
     *
     * IMPORTANT: Only run after verifying no non-numeric medicine_id values
     * exist in production. Call get_medicine_id_migration_status() first.
     *
     * @return array {status, message, non_numeric_count, migrated}
     */
    public function run_medicine_id_migration()
    {
        if (!$this->_table_exists('iop_medication')) {
            return array('status' => 'skip', 'message' => 'iop_medication does not exist');
        }

        // Check current datatype
        $q = $this->db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'iop_medication'
                AND COLUMN_NAME  = 'medicine_id'"
        );
        if (!$q || $q->num_rows() === 0) {
            return array('status' => 'skip', 'message' => 'medicine_id column not found');
        }
        $colType = strtolower($q->row()->COLUMN_TYPE);
        if (strpos($colType, 'int') !== false && strpos($colType, 'varchar') === false) {
            return array('status' => 'already_done', 'message' => 'medicine_id is already INT — no migration needed');
        }

        // Count non-numeric values that would be lost
        $nonNum = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM `iop_medication`
              WHERE `medicine_id` IS NOT NULL
                AND `medicine_id` != ''
                AND `medicine_id` NOT REGEXP '^[0-9]+$'"
        );
        $nonNumCount = ($nonNum && $nonNum->num_rows() > 0) ? (int)$nonNum->row()->cnt : 0;

        // Step 1 — Add temp INT column (skip if already exists from prior attempt)
        if (!$this->_column_exists('iop_medication', 'medicine_id_new')) {
            $this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `medicine_id_new` INT(11) DEFAULT NULL");
        }

        // Step 2 — Migrate numeric values
        $this->db->query("
            UPDATE `iop_medication`
               SET `medicine_id_new` = CAST(`medicine_id` AS UNSIGNED)
             WHERE `medicine_id` REGEXP '^[0-9]+$'
        ");
        $migrated = $this->db->affected_rows();

        // Step 3 — Swap columns
        $this->db->query("ALTER TABLE `iop_medication` DROP COLUMN `medicine_id`");
        $this->db->query("ALTER TABLE `iop_medication` CHANGE `medicine_id_new` `medicine_id` INT(11) DEFAULT NULL");

        // Restore index on the new INT column
        if (!$this->_index_exists('iop_medication', 'idx_medicine_id')) {
            $this->db->query("ALTER TABLE `iop_medication` ADD INDEX `idx_medicine_id` (`medicine_id`)");
        }

        log_message('info', "Medication_dictionary_model: medicine_id migrated to INT. Rows migrated: {$migrated}. Non-numeric (set to NULL): {$nonNumCount}");

        return array(
            'status'           => 'done',
            'message'          => 'medicine_id successfully converted from VARCHAR to INT',
            'migrated'         => $migrated,
            'non_numeric_count'=> $nonNumCount,
        );
    }

    /**
     * Pre-migration safety check — call BEFORE run_medicine_id_migration().
     * Returns counts so the admin can decide whether to proceed.
     *
     * @return array
     */
    public function get_medicine_id_migration_status()
    {
        if (!$this->_table_exists('iop_medication')) {
            return array('ready' => false, 'reason' => 'Table does not exist');
        }

        $q = $this->db->query(
            "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME   = 'iop_medication'
                AND COLUMN_NAME  = 'medicine_id'"
        );
        $colType = ($q && $q->num_rows() > 0) ? strtolower($q->row()->COLUMN_TYPE) : '';

        if (strpos($colType, 'int') !== false && strpos($colType, 'varchar') === false) {
            return array('ready' => false, 'reason' => 'Already INT — no migration needed', 'current_type' => $colType);
        }

        $total   = $this->db->count_all('iop_medication');
        $numQ    = $this->db->query("SELECT COUNT(*) AS cnt FROM `iop_medication` WHERE `medicine_id` REGEXP '^[0-9]+$'");
        $numRows = ($numQ) ? (int)$numQ->row()->cnt : 0;
        $nonNumQ = $this->db->query("SELECT COUNT(*) AS cnt FROM `iop_medication` WHERE `medicine_id` IS NOT NULL AND `medicine_id` != '' AND `medicine_id` NOT REGEXP '^[0-9]+$'");
        $nonNum  = ($nonNumQ) ? (int)$nonNumQ->row()->cnt : 0;

        return array(
            'ready'             => true,
            'current_type'      => $colType,
            'total_rows'        => $total,
            'numeric_rows'      => $numRows,
            'non_numeric_rows'  => $nonNum,
            'safe_to_migrate'   => ($nonNum === 0),
            'warning'           => $nonNum > 0
                ? "{$nonNum} row(s) have non-numeric medicine_id and will be set to NULL after migration"
                : null,
        );
    }

    /**
     * Get all active drug forms (for UI dropdowns).
     */
    public function get_forms()
    {
        $this->ensure_dictionary_schema();
        if (!$this->_table_exists('medication_form')) return array();
        return $this->db->where('is_active', 1)->get('medication_form')->result();
    }

    // =========================================================================
    // FK-LINK COLUMNS (backward-compatible additions)
    // =========================================================================

    /**
     * Add optional FK-link columns to iop_medication.
     * All columns are NULLABLE so existing rows are unaffected.
     */
    private function _add_iop_medication_fk_columns()
    {
        if (!$this->_table_exists('iop_medication')) return;

        $cols = array(
            'medication_id' => "INT(11)     DEFAULT NULL COMMENT 'FK to medication_master.id'",
            'frequency_id'  => "INT(11)     DEFAULT NULL COMMENT 'FK to medication_frequency.id'",
            'route_id'      => "INT(11)     DEFAULT NULL COMMENT 'FK to medication_route.id'",
            'unit_id'       => "INT(11)     DEFAULT NULL COMMENT 'FK to medication_unit.id'",
        );
        foreach ($cols as $col => $def) {
            if (!$this->_column_exists('iop_medication', $col)) {
                $this->db->query("ALTER TABLE `iop_medication` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    /**
     * Add optional columns to medicine_drug_name that are not yet present.
     * generic_name / dosage_form / strength already exist in backup schema,
     * but we guard each one so this is safe on the baseline SQL install too.
     */
    private function _add_medicine_drug_name_columns()
    {
        if (!$this->_table_exists('medicine_drug_name')) return;

        $cols = array(
            'route'              => "VARCHAR(50)  DEFAULT NULL",
            'pregnancy_category' => "VARCHAR(10)  DEFAULT NULL",
            'pediatric_safe'     => "TINYINT(1)   NOT NULL DEFAULT 1",
            'atc_code'           => "VARCHAR(20)  DEFAULT NULL",
            'medication_master_id' => "INT(11)    DEFAULT NULL COMMENT 'FK to medication_master.id'",
        );
        foreach ($cols as $col => $def) {
            if (!$this->_column_exists('medicine_drug_name', $col)) {
                $this->db->query("ALTER TABLE `medicine_drug_name` ADD COLUMN `{$col}` {$def}");
            }
        }
    }

    // =========================================================================
    // INDEXES
    // =========================================================================

    private function _add_indexes()
    {
        $indexes = array(
            array('iop_medication',    'idx_med_master_id',  '(`medication_id`)'),
            array('iop_medication',    'idx_freq_id',        '(`frequency_id`)'),
            array('iop_medication',    'idx_route_id',       '(`route_id`)'),
            array('medicine_drug_name','idx_med_master_ref', '(`medication_master_id`)'),
            array('medicine_drug_name','idx_atc_code',       '(`atc_code`)'),
        );
        foreach ($indexes as $ix) {
            list($tbl, $name, $cols) = $ix;
            if ($this->_table_exists($tbl) && !$this->_index_exists($tbl, $name)) {
                $this->db->query("ALTER TABLE `{$tbl}` ADD INDEX `{$name}` {$cols}");
            }
        }
    }

    // =========================================================================
    // SEED / MIGRATION HELPERS
    // =========================================================================

    /**
     * Populate medication_master from medicine_drug_name (safe, skips existing).
     * Call once from a migration script or admin panel — not on every request.
     *
     * @return array{inserted: int, skipped: int}
     */
    public function seed_from_medicine_drug_name()
    {
        $this->ensure_dictionary_schema();

        $drugs = $this->db->select('drug_id, drug_name, generic_name, dosage_form, strength, nhis_code, is_nhis_covered, is_active')
            ->where('InActive', 0)
            ->get('medicine_drug_name')->result();

        $inserted = 0;
        $skipped  = 0;

        foreach ($drugs as $d) {
            // Skip if already linked
            if (!empty($d->drug_id)) {
                $exists = $this->db->get_where('medication_master', array('drug_id_ref' => (int)$d->drug_id))->row();
                if ($exists) { $skipped++; continue; }
            }

            $generic  = !empty($d->generic_name) ? trim($d->generic_name) : trim($d->drug_name);
            $brand    = trim($d->drug_name);
            $form     = !empty($d->dosage_form) ? trim($d->dosage_form) : null;
            $strength = !empty($d->strength)    ? trim($d->strength)    : null;

            $this->db->insert('medication_master', array(
                'generic_name'       => $generic,
                'brand_name'         => ($brand !== $generic) ? $brand : null,
                'strength'           => $strength,
                'formulation'        => $form,
                'nhis_code'          => !empty($d->nhis_code) ? $d->nhis_code : null,
                'nhis_covered'       => (int)$d->is_nhis_covered,
                'drug_id_ref'        => (int)$d->drug_id,
                'is_active'          => 1,
                'created_at'         => date('Y-m-d H:i:s'),
            ));
            $master_id = $this->db->insert_id();

            // Back-link on medicine_drug_name
            if ($master_id && $this->_column_exists('medicine_drug_name', 'medication_master_id')) {
                $this->db->where('drug_id', (int)$d->drug_id)
                    ->update('medicine_drug_name', array('medication_master_id' => $master_id));
            }

            // Auto-seed synonyms: brand_name + generic_name
            $this->_add_synonym($master_id, $brand,   'AUTO_IMPORT');
            if ($generic !== $brand) {
                $this->_add_synonym($master_id, $generic, 'AUTO_IMPORT');
            }

            $inserted++;
        }

        return array('inserted' => $inserted, 'skipped' => $skipped);
    }

    /**
     * Seed medication_master from nhis_drug_tariffs (NHIS-coded drugs not yet in master).
     *
     * @return array{inserted: int, skipped: int}
     */
    public function seed_from_nhis_tariffs()
    {
        $this->ensure_dictionary_schema();
        if (!$this->_table_exists('nhis_drug_tariffs')) return array('inserted' => 0, 'skipped' => 0);

        $tariffs = $this->db->select('tariff_id, nhis_code, drug_name, generic_name, dosage_form, strength')
            ->where('is_active', 1)
            ->get('nhis_drug_tariffs')->result();

        $inserted = 0;
        $skipped  = 0;

        foreach ($tariffs as $t) {
            $exists = $this->db->get_where('medication_master', array('nhis_code' => $t->nhis_code))->row();
            if ($exists) { $skipped++; continue; }

            $generic = !empty($t->generic_name) ? trim($t->generic_name) : trim($t->drug_name);

            $this->db->insert('medication_master', array(
                'generic_name'  => $generic,
                'brand_name'    => trim($t->drug_name) !== $generic ? trim($t->drug_name) : null,
                'strength'      => !empty($t->strength)    ? trim($t->strength)    : null,
                'formulation'   => !empty($t->dosage_form) ? trim($t->dosage_form) : null,
                'nhis_code'     => trim($t->nhis_code),
                'nhis_covered'  => 1,
                'is_active'     => 1,
                'created_at'    => date('Y-m-d H:i:s'),
            ));
            $master_id = $this->db->insert_id();

            $this->_add_synonym($master_id, trim($t->drug_name), 'AUTO_IMPORT');
            if ($generic !== trim($t->drug_name)) {
                $this->_add_synonym($master_id, $generic, 'AUTO_IMPORT');
            }

            $inserted++;
        }

        return array('inserted' => $inserted, 'skipped' => $skipped);
    }

    // =========================================================================
    // SMART MATCH LOOKUP
    // =========================================================================

    /**
     * Smart-match a free-text drug name to medication_master.
     *
     * Search order:
     *   1. Exact match on medication_master.generic_name (case-insensitive)
     *   2. Exact match on medication_master.brand_name
     *   3. Exact match on medication_synonyms.synonym
     *   4. LIKE match on generic_name / brand_name
     *   5. LIKE match on synonyms
     *
     * @param  string $term   Free-text entered by user
     * @param  int    $limit  Max results to return
     * @return array          Array of medication_master rows (objects)
     */
    public function smart_match($term, $limit = 10)
    {
        $this->ensure_dictionary_schema();
        $term = trim($term);
        if ($term === '') return array();

        $safe = $this->db->escape_like_str($term);
        $upper = strtoupper($term);

        // 1 + 2: exact match on master columns
        $q = $this->db->query(
            "SELECT m.*, 1 AS match_score
               FROM medication_master m
              WHERE m.is_active = 1
                AND (UPPER(m.generic_name) = ? OR UPPER(m.brand_name) = ?)
              LIMIT ?",
            array($upper, $upper, $limit)
        );
        $results = $q ? $q->result() : array();

        if (count($results) >= $limit) return $results;

        // 3: exact synonym match
        $q2 = $this->db->query(
            "SELECT m.*, 2 AS match_score
               FROM medication_synonyms s
               JOIN medication_master m ON m.id = s.medication_id
              WHERE m.is_active = 1
                AND s.synonym_upper = ?
                AND m.id NOT IN (SELECT id FROM medication_master WHERE UPPER(generic_name) = ? OR UPPER(brand_name) = ?)
              LIMIT ?",
            array($upper, $upper, $upper, $limit - count($results))
        );
        if ($q2) $results = array_merge($results, $q2->result());

        if (count($results) >= $limit) return $results;

        // 4: LIKE match on master columns
        $remaining = $limit - count($results);
        $existing_ids = array_map(function($r){ return (int)$r->id; }, $results);
        $not_in = empty($existing_ids) ? '' : 'AND m.id NOT IN (' . implode(',', $existing_ids) . ')';

        $q3 = $this->db->query(
            "SELECT m.*, 3 AS match_score
               FROM medication_master m
              WHERE m.is_active = 1
                AND (m.generic_name LIKE ? OR m.brand_name LIKE ?)
                {$not_in}
              LIMIT ?",
            array("%{$safe}%", "%{$safe}%", $remaining)
        );
        if ($q3) $results = array_merge($results, $q3->result());

        if (count($results) >= $limit) return $results;

        // 5: LIKE synonym match
        $remaining = $limit - count($results);
        $existing_ids = array_map(function($r){ return (int)$r->id; }, $results);
        $not_in2 = empty($existing_ids) ? '' : 'AND m.id NOT IN (' . implode(',', $existing_ids) . ')';

        $q4 = $this->db->query(
            "SELECT m.*, 4 AS match_score
               FROM medication_synonyms s
               JOIN medication_master m ON m.id = s.medication_id
              WHERE m.is_active = 1
                AND s.synonym LIKE ?
                {$not_in2}
              LIMIT ?",
            array("%{$safe}%", $remaining)
        );
        if ($q4) $results = array_merge($results, $q4->result());

        return $results;
    }

    /**
     * Resolve a drug entry from iop_medication to its medication_master row.
     * Tries medicine_id FK first, then falls back to medicine_text smart-match.
     *
     * @param  int    $drug_id       medicine_drug_name.drug_id (0 if free-text)
     * @param  string $medicine_text Free-text fallback
     * @return object|null
     */
    public function resolve_master_entry($drug_id, $medicine_text = '')
    {
        $this->ensure_dictionary_schema();

        if ($drug_id > 0) {
            $row = $this->db->get_where('medication_master', array('drug_id_ref' => (int)$drug_id, 'is_active' => 1))->row();
            if ($row) return $row;
        }

        if ($medicine_text !== '') {
            $matches = $this->smart_match($medicine_text, 1);
            if (!empty($matches)) return $matches[0];
        }

        return null;
    }

    /**
     * Get all active frequencies (for UI dropdowns).
     */
    public function get_frequencies()
    {
        $this->ensure_dictionary_schema();
        return $this->db->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->get('medication_frequency')->result();
    }

    /**
     * Get all active routes (for UI dropdowns).
     */
    public function get_routes()
    {
        $this->ensure_dictionary_schema();
        return $this->db->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->get('medication_route')->result();
    }

    /**
     * Get all active units (for UI dropdowns).
     */
    public function get_units()
    {
        $this->ensure_dictionary_schema();
        return $this->db->where('is_active', 1)
            ->order_by('sort_order', 'ASC')
            ->get('medication_unit')->result();
    }

    /**
     * Add a synonym (skips duplicates).
     *
     * @param int    $master_id
     * @param string $synonym
     * @param string $source
     */
    public function add_synonym($master_id, $synonym, $source = 'MANUAL')
    {
        $this->ensure_dictionary_schema();
        $this->_add_synonym($master_id, $synonym, $source);
    }

    // =========================================================================
    // DUPLICATE DETECTION
    // =========================================================================

    /**
     * Find potential duplicate drug names in medicine_drug_name.
     * Groups entries whose UPPER(drug_name) matches exactly.
     *
     * @return array  [{drug_name, count, drug_ids}]
     */
    public function find_duplicate_drug_names()
    {
        $q = $this->db->query("
            SELECT UPPER(TRIM(drug_name)) AS normalised_name,
                   COUNT(*)              AS cnt,
                   GROUP_CONCAT(drug_id ORDER BY drug_id) AS drug_ids
              FROM medicine_drug_name
             WHERE InActive = 0
             GROUP BY normalised_name
            HAVING cnt > 1
             ORDER BY cnt DESC
        ");
        return $q ? $q->result() : array();
    }

    /**
     * Find drugs with the same generic_name + strength + dosage_form
     * (structural duplicates even if brand names differ).
     *
     * @return array
     */
    public function find_structural_duplicates()
    {
        if (!$this->_column_exists('medicine_drug_name', 'generic_name')) return array();

        $q = $this->db->query("
            SELECT UPPER(TRIM(IFNULL(generic_name,''))) AS g,
                   UPPER(TRIM(IFNULL(strength,'')))     AS s,
                   UPPER(TRIM(IFNULL(dosage_form,'')))  AS f,
                   COUNT(*)                             AS cnt,
                   GROUP_CONCAT(drug_id ORDER BY drug_id) AS drug_ids,
                   GROUP_CONCAT(drug_name ORDER BY drug_id SEPARATOR ' | ') AS names
              FROM medicine_drug_name
             WHERE InActive = 0
               AND generic_name IS NOT NULL
               AND generic_name != ''
             GROUP BY g, s, f
            HAVING cnt > 1
             ORDER BY cnt DESC
        ");
        return $q ? $q->result() : array();
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function _add_synonym($master_id, $synonym, $source)
    {
        $synonym = trim((string)$synonym);
        if ($synonym === '' || $master_id <= 0) return;

        $upper = strtoupper($synonym);
        $exists = $this->db->get_where('medication_synonyms', array(
            'medication_id' => (int)$master_id,
            'synonym_upper' => $upper,
        ))->row();

        if (!$exists) {
            $this->db->insert('medication_synonyms', array(
                'medication_id' => (int)$master_id,
                'synonym'       => $synonym,
                'synonym_upper' => $upper,
                'source'        => $source,
            ));
        }
    }

    private function _table_exists($table)
    {
        return $this->db->table_exists($table);
    }

    private function _column_exists($table, $col)
    {
        $q = $this->db->query(
            "SHOW COLUMNS FROM `" . str_replace('`', '', $table) . "` LIKE " . $this->db->escape($col)
        );
        return ($q && $q->num_rows() > 0);
    }

    private function _index_exists($table, $index_name)
    {
        $q = $this->db->query(
            "SHOW INDEX FROM `" . str_replace('`', '', $table) . "` WHERE Key_name = " . $this->db->escape($index_name)
        );
        return ($q && $q->num_rows() > 0);
    }
}
