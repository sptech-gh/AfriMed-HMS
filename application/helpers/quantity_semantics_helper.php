<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

function qs_flag_enabled($key, $default = false)
{
	$CI = function_exists('get_instance') ? get_instance() : null;
	if (!$CI || !isset($CI->config) || !method_exists($CI->config, 'item')) {
		return (bool)$default;
	}
	$v = $CI->config->item($key);
	if ($v === null) {
		return (bool)$default;
	}
	return (bool)$v;
}

function qs_default_semantics_version()
{
	$CI = function_exists('get_instance') ? get_instance() : null;
	if (!$CI || !isset($CI->config) || !method_exists($CI->config, 'item')) {
		return 1;
	}
	$v = $CI->config->item('QUANTITY_SEMANTICS_VERSION_DEFAULT');
	$v = ($v === null) ? 1 : (int)$v;
	return ($v > 0) ? $v : 1;
}

function qs_decimal_semantics_version()
{
	$CI = function_exists('get_instance') ? get_instance() : null;
	if (!$CI || !isset($CI->config) || !method_exists($CI->config, 'item')) {
		return 2;
	}
	$v = $CI->config->item('QUANTITY_SEMANTICS_VERSION_DECIMAL');
	$v = ($v === null) ? 2 : (int)$v;
	return ($v > 0) ? $v : 2;
}

function qs_table_has_column($table, $column)
{
	$CI = function_exists('get_instance') ? get_instance() : null;
	if (!$CI || !isset($CI->db) || !method_exists($CI->db, 'field_exists')) {
		return false;
	}
	return (bool)$CI->db->field_exists($column, $table);
}

function qs_row_value($row, $key)
{
	if (is_array($row)) {
		return array_key_exists($key, $row) ? $row[$key] : null;
	}
	if (is_object($row)) {
		return isset($row->{$key}) ? $row->{$key} : null;
	}
	return null;
}

function qs_pick_prescribed_qty($med_row, $fallback = 1.0)
{
	$useDecimal = qs_flag_enabled('ENABLE_DECIMAL_PRESCRIBED_QTY', false) || qs_flag_enabled('ENABLE_DECIMAL_PRESCRIPTIONS', false);
	$pres = qs_row_value($med_row, 'prescribed_qty');
	if ($useDecimal && $pres !== null && $pres !== '') {
		$q = (float)$pres;
		return ($q > 0) ? $q : (float)$fallback;
	}
	$tot = qs_row_value($med_row, 'total_qty');
	if ($tot !== null && $tot !== '') {
		$q = (float)$tot;
		return ($q > 0) ? $q : (float)$fallback;
	}
	return (float)$fallback;
}

function qs_parse_decimal_strict($raw, $scale = 3)
{
	$raw = ($raw === null) ? '' : trim((string)$raw);
	if ($raw === '') {
		return array('ok' => false, 'value' => null, 'error' => 'empty');
	}
	if (!preg_match('/^[0-9]+(?:\.[0-9]{1,' . (int)$scale . '})?$/', $raw)) {
		return array('ok' => false, 'value' => null, 'error' => 'invalid_format');
	}
	return array('ok' => true, 'value' => (float)$raw, 'error' => null);
}
