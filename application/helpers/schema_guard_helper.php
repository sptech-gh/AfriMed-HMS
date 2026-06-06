<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('schema_already_run')) {
    function schema_already_run($flag_key)
    {
        static $schema_flags_cache = array();
        $CI =& get_instance();
        $flag_key = trim((string)$flag_key);
        if ($flag_key === '' || !isset($CI->db)) {
            return false;
        }
        if (array_key_exists($flag_key, $schema_flags_cache)) {
            return $schema_flags_cache[$flag_key];
        }

        $prev = isset($CI->db->db_debug) ? $CI->db->db_debug : null;
        if ($prev !== null) { $CI->db->db_debug = false; }
        $row = null;
        $query = $CI->db->get_where('schema_run_flags', array('flag_key' => $flag_key), 1);
        if ($query) {
            $row = $query->row();
        }
        if ($prev !== null) { $CI->db->db_debug = $prev; }

        $schema_flags_cache[$flag_key] = ($row !== null);
        return $schema_flags_cache[$flag_key];
    }
}

if (!function_exists('mark_schema_run')) {
    function mark_schema_run($flag_key, $schema_hash = null)
    {
        $CI =& get_instance();
        $flag_key = trim((string)$flag_key);
        if ($flag_key === '' || !isset($CI->db)) {
            return false;
        }

        $data = array(
            'flag_key' => $flag_key,
            'run_at' => date('Y-m-d H:i:s')
        );
        if ($schema_hash !== null) {
            $data['schema_hash'] = $schema_hash;
        }

        $prev = isset($CI->db->db_debug) ? $CI->db->db_debug : null;
        if ($prev !== null) { $CI->db->db_debug = false; }
        $ok = $CI->db->replace('schema_run_flags', $data);
        if ($prev !== null) { $CI->db->db_debug = $prev; }

        return (bool)$ok;
    }
}
