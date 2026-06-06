<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_mapping_model extends CI_Model
{
    private $table = 'nhis_ref_service_mapping';

    public function __construct()
    {
        parent::__construct();
        $this->load->database();
    }

    private function _table_exists()
    {
        return $this->db->table_exists($this->table);
    }

    public function get_nhis_code($module, $local_service_id)
    {
        $module = strtoupper(trim((string)$module));
        $local_service_id = (int)$local_service_id;

        if ($module === '' || $local_service_id <= 0) {
            log_message('error', '[NHIS_MAP_MISSING] module=' . $module . ' id=' . $local_service_id);
            return null;
        }

        if (!$this->_table_exists()) {
            log_message('error', '[NHIS_MAP_MISSING] module=' . $module . ' id=' . $local_service_id);
            return null;
        }

        $rows = $this->db->select('nhis_code')
            ->from($this->table)
            ->where('module', $module)
            ->where('local_service_id', $local_service_id)
            ->limit(2)
            ->get()->result();

        $count = is_array($rows) ? count($rows) : 0;

        if ($count === 1 && isset($rows[0]->nhis_code)) {
            return (string)$rows[0]->nhis_code;
        }

        if ($count === 0) {
            log_message('error', '[NHIS_MAP_MISSING] module=' . $module . ' id=' . $local_service_id);
            return null;
        }

        log_message('error', '[NHIS_MAP_AMBIGUOUS] module=' . $module . ' id=' . $local_service_id . ' count=' . $count);
        return null;
    }

    public function get_mapping_record($module, $local_service_id)
    {
        $module = strtoupper(trim((string)$module));
        $local_service_id = (int)$local_service_id;

        if ($module === '' || $local_service_id <= 0) {
            return null;
        }

        if (!$this->_table_exists()) {
            return null;
        }

        $rows = $this->db->from($this->table)
            ->where('module', $module)
            ->where('local_service_id', $local_service_id)
            ->limit(2)
            ->get()->result();

        $count = is_array($rows) ? count($rows) : 0;

        if ($count === 1) {
            return $rows[0];
        }

        if ($count === 0) {
            return null;
        }

        log_message('error', '[NHIS_MAP_AMBIGUOUS] module=' . $module . ' id=' . $local_service_id . ' count=' . $count);
        return null;
    }

    public function has_mapping($module, $local_service_id)
    {
        return $this->get_nhis_code($module, $local_service_id) !== null;
    }

    public function validate_mapping_integrity($module)
    {
        $module = strtoupper(trim((string)$module));

        $out = [
            'duplicate_count' => 0,
            'missing_count' => null,
            'ambiguous_entries' => [],
        ];

        if ($module === '') {
            return $out;
        }

        if (!$this->_table_exists()) {
            return $out;
        }

        $q = $this->db->query(
            "SELECT module, local_service_id, COUNT(*) AS cnt
             FROM `{$this->table}`
             WHERE module = ?
             GROUP BY module, local_service_id
             HAVING COUNT(*) > 1",
            [$module]
        );

        $rows = $q ? $q->result() : [];
        $out['duplicate_count'] = is_array($rows) ? count($rows) : 0;

        if (is_array($rows) && $rows) {
            foreach ($rows as $r) {
                $entry = [
                    'module' => isset($r->module) ? (string)$r->module : $module,
                    'local_service_id' => isset($r->local_service_id) ? (int)$r->local_service_id : null,
                    'count' => isset($r->cnt) ? (int)$r->cnt : null,
                ];
                $out['ambiguous_entries'][] = $entry;

                log_message('error', '[NHIS_MAP_AMBIGUOUS] module=' . $module . ' id=' . (int)$entry['local_service_id'] . ' count=' . (int)$entry['count']);
            }
        }

        return $out;
    }
}
