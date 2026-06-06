<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Migration: NHIS Phase 1 Corrections
 *
 * - Rename nhis_version_info columns artifact/version_label -> component/version_value
 * - Add effective_from and seed known versions using new columns
 * - Create nhis_batches and nhis_batch_claims if they do not exist
 */
class Migration_Nhis_Phase1_Corrections extends CI_Migration
{
    public function up()
    {
        // 1) Fix nhis_version_info column names and reseed known versions
        if ($this->db->table_exists('nhis_version_info')) {
            // Rename artifact -> component
            if ($this->db->field_exists('artifact', 'nhis_version_info')) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` " .
                    "CHANGE COLUMN `artifact` `component` VARCHAR(50) NOT NULL"
                );
            }

            // Rename version_label -> version_value
            if ($this->db->field_exists('version_label', 'nhis_version_info')) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` " .
                    "CHANGE COLUMN `version_label` `version_value` VARCHAR(100) NOT NULL"
                );
            }

            // Add effective_from if missing
            if (!$this->db->field_exists('effective_from', 'nhis_version_info')) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` " .
                    "ADD COLUMN `effective_from` DATE NULL AFTER `version_value`"
                );
            }

            // Ensure a unique key on component for ON DUPLICATE KEY semantics
            $indexes = $this->db->query(
                "SHOW INDEX FROM `nhis_version_info` WHERE Key_name = 'uq_component'"
            )->result();
            if (empty($indexes)) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` " .
                    "ADD UNIQUE KEY `uq_component` (`component`)"
                );
            }

            // Reseed known versions (leave XMLFormatVersion unseeded)
            $this->db->query(
                "INSERT INTO `nhis_version_info` " .
                "(`component`, `version_value`, `is_current`, `effective_from`) VALUES " .
                "('MedicineVersion', 'May 2025', 1, '2025-05-01')," .
                "('TariffVersion',   'Feb 2023', 1, '2023-02-01')," .
                "('GDRGVersion',     'Feb 2023', 1, '2023-02-01')," .
                "('ICDVersion',      'Dec 2022', 1, '2022-12-01') " .
                "ON DUPLICATE KEY UPDATE `version_value` = VALUES(`version_value`), `is_current` = 1"
            );
        }

        // 2) Create nhis_batches if not exists
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `nhis_batches` (" .
            "  `id`                  INT AUTO_INCREMENT PRIMARY KEY," .
            "  `batch_number`        VARCHAR(100) NOT NULL UNIQUE," .
            "  `service_year`        CHAR(4)      NOT NULL," .
            "  `service_month`       CHAR(2)      NOT NULL," .
            "  `creation_date`       DATE         NOT NULL," .
            "  `batch_amount`        DECIMAL(10,2) NOT NULL DEFAULT 0.00," .
            "  `claims_count`        INT NOT NULL DEFAULT 0," .
            "  `batch_currency`      VARCHAR(10) DEFAULT 'GHS'," .
            "  `status`              ENUM('draft','exported','submitted_offline','submitted_online','accepted','partially_rejected','rejected') DEFAULT 'draft'," .
            "  `exported_xml_path`   VARCHAR(500) NULL," .
            "  `exported_xml_hash`   VARCHAR(64)  NULL," .
            "  `submitted_at`        DATETIME NULL," .
            "  `submission_method`   ENUM('online','offline') NULL," .
            "  `notes`               TEXT NULL," .
            "  `created_by`          INT NOT NULL," .
            "  `created_at`          DATETIME," .
            "  `updated_at`          DATETIME," .
            "  INDEX `idx_status` (`status`)," .
            "  INDEX `idx_period` (`service_year`, `service_month`)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        // 3) Create nhis_batch_claims if not exists
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `nhis_batch_claims` (" .
            "  `id`                          INT AUTO_INCREMENT PRIMARY KEY," .
            "  `batch_id`                    INT NOT NULL," .
            "  `visit_id`                    INT NOT NULL," .
            "  `claim_identification_number` VARCHAR(100) NOT NULL," .
            "  `out_patient_code`            CHAR(5)       NULL," .
            "  `in_patient_code`             VARCHAR(50)   NULL," .
            "  `investigation_code`          VARCHAR(50)   NULL," .
            "  `out_patient_tariff_amount`   DECIMAL(10,2) NULL," .
            "  `in_patient_tariff_amount`    DECIMAL(10,2) NULL," .
            "  `total_cost`                  DECIMAL(10,2) NOT NULL," .
            "  `treatments_count`            INT NOT NULL DEFAULT 0," .
            "  `medicines_count`             INT NOT NULL DEFAULT 0," .
            "  `validation_status`           ENUM('pending','passed','warning','failed') DEFAULT 'pending'," .
            "  `validation_errors`           JSON NULL," .
            "  `created_at`                  DATETIME," .
            "  `updated_at`                  DATETIME," .
            "  UNIQUE KEY `uq_batch_visit` (`batch_id`, `visit_id`)," .
            "  INDEX `idx_validation_status` (`validation_status`)," .
            "  CONSTRAINT `fk_nhis_batch_claims_batch` FOREIGN KEY (`batch_id`) REFERENCES `nhis_batches`(`id`)" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public function down()
    {
        // Drop child table first to satisfy foreign key constraints
        if ($this->db->table_exists('nhis_batch_claims')) {
            $this->dbforge->drop_table('nhis_batch_claims', true);
        }

        if ($this->db->table_exists('nhis_batches')) {
            $this->dbforge->drop_table('nhis_batches', true);
        }

        // Revert nhis_version_info column name changes where possible
        if ($this->db->table_exists('nhis_version_info')) {
            // Drop unique key on component if it exists
            $indexes = $this->db->query(
                "SHOW INDEX FROM `nhis_version_info` WHERE Key_name = 'uq_component'"
            )->result();
            if (!empty($indexes)) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` DROP INDEX `uq_component`"
                );
            }

            // Drop effective_from if present
            if ($this->db->field_exists('effective_from', 'nhis_version_info')) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` DROP COLUMN `effective_from`"
                );
            }

            // Rename component -> artifact
            if ($this->db->field_exists('component', 'nhis_version_info')) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` " .
                    "CHANGE COLUMN `component` `artifact` VARCHAR(50) NOT NULL"
                );
            }

            // Rename version_value -> version_label
            if ($this->db->field_exists('version_value', 'nhis_version_info')) {
                $this->db->query(
                    "ALTER TABLE `nhis_version_info` " .
                    "CHANGE COLUMN `version_value` `version_label` VARCHAR(100) NOT NULL"
                );
            }
        }
    }
}
