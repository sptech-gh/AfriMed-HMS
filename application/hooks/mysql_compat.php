<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Disable ONLY_FULL_GROUP_BY for MySQL 8 compatibility
 * This allows legacy queries with GROUP BY to work without listing all SELECT columns
 */
function disable_strict_group_by() {
    $CI =& get_instance();
    if (isset($CI->db) && is_object($CI->db)) {
        $CI->db->query("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
    }
}
