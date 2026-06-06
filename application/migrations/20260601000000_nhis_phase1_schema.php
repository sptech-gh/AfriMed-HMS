<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Migration: NHIS Phase 1 Schema
 *
 * - Extend existing nhis_audit_log table with additional audit fields
 * - Extend nhis_ref_* reference tables with NHIS-specific columns
 * - Create nhis_facility_credentials and nhis_version_info tables
 */
class Migration_Nhis_Phase1_Schema extends CI_Migration
{
    public function up()
    {
        // 1) Extend existing nhis_audit_log, do NOT create a new table
        $this->_extend_nhis_audit_log();

        // 2) Extend reference tables (nhis_ref_*)
        $this->_extend_reference_tables();

        // 3) Create facility credentials and seed initial row
        $this->_create_facility_credentials();

        // 4) Create version info table and seed initial versions
        $this->_create_version_info();
    }

    public function down()
    {
        // Reverse reference table extensions
        $this->_rollback_reference_tables();

        // Roll back nhis_audit_log extension (leave original columns intact)
        $this->_rollback_nhis_audit_log();

        // Drop newly created tables
        if ($this->db->table_exists('nhis_facility_credentials')) {
            $this->dbforge->drop_table('nhis_facility_credentials', true);
        }
        if ($this->db->table_exists('nhis_version_info')) {
            $this->dbforge->drop_table('nhis_version_info', true);
        }
    }

    private function _extend_nhis_audit_log()
    {
        if (!$this->db->table_exists('nhis_audit_log')) {
            return;
        }

        if (!$this->db->field_exists('user_id', 'nhis_audit_log')) {
            $this->db->query(
                "ALTER TABLE `nhis_audit_log` " .
                "ADD COLUMN `user_id` INT(11) DEFAULT NULL AFTER `patient_no`"
            );
        }

        if (!$this->db->field_exists('action', 'nhis_audit_log')) {
            $this->db->query(
                "ALTER TABLE `nhis_audit_log` " .
                "ADD COLUMN `action` VARCHAR(50) DEFAULT NULL AFTER `action_type`"
            );
        }

        if (!$this->db->field_exists('batch_id', 'nhis_audit_log')) {
            $this->db->query(
                "ALTER TABLE `nhis_audit_log` " .
                "ADD COLUMN `batch_id` INT(11) DEFAULT NULL AFTER `reference_id`"
            );
        }

        if (!$this->db->field_exists('visit_id', 'nhis_audit_log')) {
            $this->db->query(
                "ALTER TABLE `nhis_audit_log` " .
                "ADD COLUMN `visit_id` VARCHAR(50) DEFAULT NULL AFTER `batch_id`"
            );
        }

        if (!$this->db->field_exists('payload', 'nhis_audit_log')) {
            $this->db->query(
                "ALTER TABLE `nhis_audit_log` " .
                "ADD COLUMN `payload` LONGTEXT DEFAULT NULL AFTER `error_message`"
            );
        }
    }

    private function _rollback_nhis_audit_log()
    {
        if (!$this->db->table_exists('nhis_audit_log')) {
            return;
        }

        $this->_drop_column_if_exists('nhis_audit_log', 'user_id');
        $this->_drop_column_if_exists('nhis_audit_log', 'action');
        $this->_drop_column_if_exists('nhis_audit_log', 'batch_id');
        $this->_drop_column_if_exists('nhis_audit_log', 'visit_id');
        $this->_drop_column_if_exists('nhis_audit_log', 'payload');
    }

    private function _extend_reference_tables()
    {
        if ($this->db->table_exists('nhis_ref_gdrg') &&
            !$this->db->field_exists('service_type', 'nhis_ref_gdrg')) {
            $this->db->query(
                "ALTER TABLE `nhis_ref_gdrg` " .
                "ADD COLUMN `service_type` ENUM('OUT','INP','DIA','CAP') " .
                "NOT NULL DEFAULT 'OUT' AFTER `nhis_code`"
            );
        }

        if ($this->db->table_exists('nhis_ref_medicines') &&
            !$this->db->field_exists('requires_lab_result', 'nhis_ref_medicines')) {
            $this->db->query(
                "ALTER TABLE `nhis_ref_medicines` " .
                "ADD COLUMN `requires_lab_result` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tariff_amount`"
            );
        }

        if ($this->db->table_exists('nhis_ref_tariffs')) {
            if (!$this->db->field_exists('service_type', 'nhis_ref_tariffs')) {
                $this->db->query(
                    "ALTER TABLE `nhis_ref_tariffs` " .
                    "ADD COLUMN `service_type` ENUM('OUT','INP','DIA','CAP') " .
                    "NOT NULL DEFAULT 'OUT' AFTER `nhis_code`"
                );
            }
            if (!$this->db->field_exists('facility_level', 'nhis_ref_tariffs')) {
                $this->db->query(
                    "ALTER TABLE `nhis_ref_tariffs` " .
                    "ADD COLUMN `facility_level` VARCHAR(20) DEFAULT NULL AFTER `service_type`"
                );
            }
        }
    }

    private function _rollback_reference_tables()
    {
        if ($this->db->table_exists('nhis_ref_gdrg')) {
            $this->_drop_column_if_exists('nhis_ref_gdrg', 'service_type');
        }

        if ($this->db->table_exists('nhis_ref_medicines')) {
            $this->_drop_column_if_exists('nhis_ref_medicines', 'requires_lab_result');
        }

        if ($this->db->table_exists('nhis_ref_tariffs')) {
            $this->_drop_column_if_exists('nhis_ref_tariffs', 'service_type');
            $this->_drop_column_if_exists('nhis_ref_tariffs', 'facility_level');
        }
    }

    private function _create_facility_credentials()
    {
        if (!$this->db->table_exists('nhis_facility_credentials')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'provider_accreditation_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => false,
                ],
                'facility_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => false,
                ],
                'facility_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => false,
                ],
                'prescribing_level' => [
                    'type' => 'VARCHAR',
                    'constraint' => 10,
                    'null' => false,
                ],
                'region' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => false,
                ],
                'credential_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => false,
                ],
                'eclaim_authorization_number' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
                'effective_from' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'effective_to' => [
                    'type' => 'DATE',
                    'null' => false,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('provider_accreditation_number');
            $this->dbforge->create_table('nhis_facility_credentials', true);
        }

        $exists = $this->db
            ->where('provider_accreditation_number', '03-05-12437')
            ->get('nhis_facility_credentials')
            ->row();

        if (!$exists) {
            $this->db->insert('nhis_facility_credentials', [
                'provider_accreditation_number' => '03-05-12437',
                'facility_name' => 'HEBREW MEDICAL CENTRE',
                'facility_type' => 'Private Clinic',
                'prescribing_level' => 'B2',
                'region' => 'GREATER ACCRA',
                'credential_code' => '03-05-004-02-12437-06-B2-2-010824',
                'eclaim_authorization_number' => null,
                'effective_from' => '2024-08-01',
                'effective_to' => '2028-08-01',
                'is_active' => 1,
            ]);
        }
    }

    private function _create_version_info()
    {
        if (!$this->db->table_exists('nhis_version_info')) {
            $this->dbforge->add_field([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'artifact' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => false,
                ],
                'version_label' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => true,
                ],
                'is_current' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => 'CURRENT_TIMESTAMP',
                ],
            ]);

            $this->dbforge->add_key('id', true);
            $this->dbforge->add_key('artifact');
            $this->dbforge->create_table('nhis_version_info', true);
        }

        $this->_upsert_version('MedicineVersion', 'May 2025');
        $this->_upsert_version('TariffVersion', 'Feb 2023');
        $this->_upsert_version('GDRGVersion', 'Feb 2023');
        $this->_upsert_version('ICDVersion', 'Dec 2022');
    }

    private function _upsert_version($artifact, $label)
    {
        $artifact = (string)$artifact;
        $label = (string)$label;

        $row = $this->db
            ->where('artifact', $artifact)
            ->get('nhis_version_info')
            ->row();

        $data = [
            'artifact' => $artifact,
            'version_label' => $label,
            'is_current' => 1,
        ];

        if ($row) {
            $this->db
                ->where('id', (int)$row->id)
                ->update('nhis_version_info', $data);
        } else {
            $this->db->insert('nhis_version_info', $data);
        }
    }

    private function _drop_column_if_exists($table, $column)
    {
        if ($this->db->field_exists($column, $table)) {
            $this->dbforge->drop_column($table, $column);
        }
    }
}
