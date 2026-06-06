<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_reference_model extends CI_Model
{
    private $tables = [
        'tariffs' => 'nhis_ref_tariffs',
        'icd10' => 'nhis_ref_icd10',
        'gdrg' => 'nhis_ref_gdrg',
        'medicines' => 'nhis_ref_medicines',
        'service_codes' => 'nhis_ref_service_codes',
    ];

    private $service_mapping_table = 'nhis_ref_service_mapping';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    private function _table_exists($table)
    {
        return $this->db->table_exists($table);
    }

    private function ensure_schema()
    {
        $this->_create_reference_table($this->tables['tariffs']);
        $this->_create_reference_table($this->tables['icd10']);
        $this->_create_reference_table($this->tables['gdrg']);
        $this->_create_reference_table($this->tables['medicines']);
        $this->_create_reference_table($this->tables['service_codes']);
    }

    public function ensure_reference_schema()
    {
        $this->ensure_schema();
        return true;
    }

    public function ensure_service_mapping_schema()
    {
        $this->_create_service_mapping_table();
        return true;
    }

    private function _create_service_mapping_table()
    {
        $table = $this->service_mapping_table;
        if ($this->_table_exists($table)) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `module` VARCHAR(20) NOT NULL,
            `local_service_id` INT(11) NOT NULL,
            `nhis_code` VARCHAR(50) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_module_service` (`module`, `local_service_id`),
            KEY `idx_nhis_code` (`nhis_code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->query($sql);
    }

    private function _create_reference_table($table)
    {
        if ($this->_table_exists($table)) {
            return;
        }

        $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
            `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `nhis_code` VARCHAR(50) NOT NULL,
            `description` TEXT NOT NULL,
            `tariff_amount` DECIMAL(15,2) DEFAULT NULL,
            `version` VARCHAR(60) NOT NULL,
            `effective_date` DATE NOT NULL,
            `expiry_date` DATE DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_nhis_code` (`nhis_code`),
            KEY `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->query($sql);
    }

    public function get_active_tariff($nhis_code)
    {
        $table = $this->tables['tariffs'];
        if (!$this->_table_exists($table)) {
            return null;
        }

        return $this->db->get_where($table, [
            'nhis_code' => (string)$nhis_code,
            'is_active' => 1,
        ])->row();
    }

    public function get_icd10($code)
    {
        $table = $this->tables['icd10'];
        if (!$this->_table_exists($table)) {
            return null;
        }

        return $this->db->get_where($table, [
            'nhis_code' => (string)$code,
            'is_active' => 1,
        ])->row();
    }

    public function get_gdrg($code)
    {
        $table = $this->tables['gdrg'];
        if (!$this->_table_exists($table)) {
            return null;
        }

        return $this->db->get_where($table, [
            'nhis_code' => (string)$code,
            'is_active' => 1,
        ])->row();
    }

    public function get_medicine($code)
    {
        $table = $this->tables['medicines'];
        if (!$this->_table_exists($table)) {
            return null;
        }

        return $this->db->get_where($table, [
            'nhis_code' => (string)$code,
            'is_active' => 1,
        ])->row();
    }

    public function get_service_code($code)
    {
        $table = $this->tables['service_codes'];
        if (!$this->_table_exists($table)) {
            return null;
        }

        return $this->db->get_where($table, [
            'nhis_code' => (string)$code,
            'is_active' => 1,
        ])->row();
    }

    public function search_icd10($term, $limit = 20)
    {
        $table = $this->tables['icd10'];
        if (!$this->_table_exists($table)) {
            return [];
        }

        $term = trim((string)$term);

        $this->db->from($table);
        if ($term !== '') {
            $this->db->group_start();
            $this->db->like('nhis_code', $term);
            $this->db->or_like('description', $term);
            $this->db->group_end();
        }
        $this->db->where('is_active', 1);
        $this->db->limit((int)$limit);

        $q = $this->db->get();
        return $q ? $q->result() : [];
    }

    public function search_tariffs($term, $limit = 20, $serviceType = null, $facilityLevel = null)
    {
        $table = $this->tables['tariffs'];
        if (!$this->_table_exists($table)) {
            return [];
        }

        $term = trim((string)$term);

        $this->db->from($table);
        if ($term !== '') {
            $this->db->group_start();
            $this->db->like('nhis_code', $term);
            $this->db->or_like('description', $term);
            $this->db->group_end();
        }
        $this->db->where('is_active', 1);

        if ($serviceType !== null && $this->db->field_exists('service_type', $table)) {
            $this->db->where('service_type', $serviceType);
        }

        if ($facilityLevel !== null && $this->db->field_exists('facility_level', $table)) {
            $this->db->where('facility_level', $facilityLevel);
        }

        $this->db->limit((int)$limit);

        $q = $this->db->get();
        return $q ? $q->result() : [];
    }

    public function import_tariffs($file_path, $version)
    {
        return $this->_import_dataset('tariffs', $file_path, $version);
    }

    public function import_icd10($file_path, $version)
    {
        return $this->_import_dataset('icd10', $file_path, $version);
    }

    public function import_gdrg($file_path, $version)
    {
        return $this->_import_dataset('gdrg', $file_path, $version);
    }

    public function import_medicines($file_path, $version)
    {
        return $this->_import_dataset('medicines', $file_path, $version);
    }

    public function import_service_codes($file_path, $version)
    {
        return $this->_import_dataset('service_codes', $file_path, $version);
    }

    private function _import_dataset($type, $file_or_rows, $version)
    {
        $type = (string)$type;
        if (!isset($this->tables[$type])) {
            return ['success' => false, 'error' => 'Unknown dataset type'];
        }

        $version = trim((string)$version);
        if ($version === '') {
            return ['success' => false, 'error' => 'Version is required'];
        }

        $this->ensure_schema();

        $table = $this->tables[$type];
        if (!$this->_table_exists($table)) {
            return ['success' => false, 'error' => 'Reference table not available'];
        }

        $parse = $this->_parse_import_input($file_or_rows);
        if (empty($parse['success'])) {
            return $parse;
        }

        $rows = $parse['rows'];
        if (!$rows) {
            return ['success' => false, 'error' => 'No rows to import'];
        }

        $effectiveDate = date('Y-m-d');
        $createdAt = date('Y-m-d H:i:s');

        $oldDebug = $this->db->db_debug;
        $this->db->db_debug = false;
        $this->db->trans_begin();

        try {
            $this->db->where('is_active', 1);
            $this->db->update($table, [
                'is_active' => 0,
                'expiry_date' => $effectiveDate,
            ]);

            foreach ($rows as $r) {
                $data = [
                    'nhis_code' => (string)$r['nhis_code'],
                    'description' => (string)$r['description'],
                    'tariff_amount' => array_key_exists('tariff_amount', $r) && $r['tariff_amount'] !== '' && $r['tariff_amount'] !== null
                        ? (float)$r['tariff_amount']
                        : null,
                    'version' => $version,
                    'effective_date' => $effectiveDate,
                    'expiry_date' => null,
                    'is_active' => 1,
                    'created_at' => $createdAt,
                ];

                $this->db->insert($table, $data);
                if ($this->db->affected_rows() !== 1) {
                    throw new Exception('Insert failed');
                }
            }

            if ($this->db->trans_status() === false) {
                $err = $this->db->error();
                throw new Exception(isset($err['message']) ? (string)$err['message'] : 'Transaction error');
            }

            $this->db->trans_commit();
            log_message('info', '[NHIS_REF_IMPORT] type=' . $type . ' version=' . $version . ' rows=' . count($rows));

            return ['success' => true, 'inserted' => count($rows)];
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        } finally {
            $this->db->db_debug = $oldDebug;
        }
    }

    private function _parse_import_input($file_or_rows)
    {
        if (is_array($file_or_rows)) {
            $rows = $file_or_rows;
            if (isset($rows['data']) && is_array($rows['data'])) {
                $rows = $rows['data'];
            }

            return $this->_normalize_rows($rows);
        }

        $file_path = (string)$file_or_rows;
        if ($file_path === '') {
            return ['success' => false, 'error' => 'File path is required'];
        }

        if (!is_readable($file_path)) {
            return ['success' => false, 'error' => 'File not readable'];
        }

        $fh = fopen($file_path, 'r');
        if (!$fh) {
            return ['success' => false, 'error' => 'Failed to open file'];
        }

        $header = fgetcsv($fh);
        if (!$header) {
            fclose($fh);
            return ['success' => false, 'error' => 'Empty CSV'];
        }

        $map = [];
        foreach ($header as $idx => $h) {
            $key = strtolower(trim((string)$h));
            if ($key !== '') {
                $map[$key] = $idx;
            }
        }

        if (!isset($map['nhis_code']) || !isset($map['description'])) {
            fclose($fh);
            return ['success' => false, 'error' => 'Missing required columns: nhis_code, description'];
        }

        $out = [];
        $rowNum = 1;
        while (($row = fgetcsv($fh)) !== false) {
            $rowNum++;

            $code = isset($row[$map['nhis_code']]) ? trim((string)$row[$map['nhis_code']]) : '';
            $desc = isset($row[$map['description']]) ? trim((string)$row[$map['description']]) : '';
            $tariff = null;

            if (isset($map['tariff_amount'])) {
                $tariff = trim((string)$row[$map['tariff_amount']]);
            } elseif (isset($map['tariff'])) {
                $tariff = trim((string)$row[$map['tariff']]);
            }

            if ($code === '' || $desc === '') {
                fclose($fh);
                return ['success' => false, 'error' => 'Invalid row at line ' . $rowNum . ': nhis_code and description are required'];
            }

            $out[] = [
                'nhis_code' => $code,
                'description' => $desc,
                'tariff_amount' => ($tariff !== null && $tariff !== '') ? $tariff : null,
            ];
        }

        fclose($fh);
        return $this->_normalize_rows($out);
    }

    private function _normalize_rows($rows)
    {
        if (!is_array($rows)) {
            return ['success' => false, 'error' => 'Invalid rows'];
        }

        $seen = [];
        $out = [];
        foreach ($rows as $idx => $r) {
            if (!is_array($r)) {
                return ['success' => false, 'error' => 'Invalid row at index ' . $idx];
            }

            if (!array_key_exists('nhis_code', $r) || !array_key_exists('description', $r)) {
                return ['success' => false, 'error' => 'Missing required keys: nhis_code, description'];
            }

            $code = trim((string)$r['nhis_code']);
            $desc = trim((string)$r['description']);

            if ($code === '' || $desc === '') {
                return ['success' => false, 'error' => 'Invalid row at index ' . $idx . ': nhis_code and description are required'];
            }

            $key = strtoupper($code);
            if (isset($seen[$key])) {
                return ['success' => false, 'error' => 'Duplicate nhis_code found: ' . $code];
            }
            $seen[$key] = true;

            $out[] = [
                'nhis_code' => $code,
                'description' => $desc,
                'tariff_amount' => array_key_exists('tariff_amount', $r) ? $r['tariff_amount'] : null,
            ];
        }

        return ['success' => true, 'rows' => $out];
    }
}
