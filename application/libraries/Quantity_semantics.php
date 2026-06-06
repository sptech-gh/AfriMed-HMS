<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Quantity_semantics
{
	public function __construct()
	{
		$CI = &get_instance();
		$CI->load->helper('quantity_semantics');
	}

	public function structured_strength_enabled()
	{
		return qs_flag_enabled('ENABLE_STRUCTURED_STRENGTH_CALCULATION', false);
	}

	public function resolve_quantity_semantics_version($row = null, $default = null)
	{
		if ($default === null) {
			$default = qs_default_semantics_version();
		}
		$v = qs_row_value($row, 'quantity_semantics_version');
		if ($v === null || $v === '') {
			return (int)$default;
		}
		$v = (int)$v;
		return ($v > 0) ? $v : (int)$default;
	}

	public function get_prescribed_qty($med_row, $fallback = 1.0)
	{
		return qs_pick_prescribed_qty($med_row, $fallback);
	}

	public function validate_qty_input($raw, $scale = 3)
	{
		$strict = qs_flag_enabled('BILLING_STRICT_NUMERIC_INPUT', false);
		$raw = ($raw === null) ? '' : trim((string)$raw);
		if ($raw === '') {
			return array('ok' => false, 'value' => null, 'error' => 'empty');
		}
		if ($strict) {
			return qs_parse_decimal_strict($raw, $scale);
		}
		$clean = preg_replace('/[^0-9.]/', '', $raw);
		if ($clean === '' || $clean === '.') {
			return array('ok' => false, 'value' => null, 'error' => 'invalid_format');
		}
		if (substr_count($clean, '.') > 1) {
			return array('ok' => false, 'value' => null, 'error' => 'invalid_format');
		}
		return array('ok' => true, 'value' => (float)$clean, 'error' => null);
	}

	public function get_dispensed_qty($admin_rows)
	{
		$useDecimal = qs_flag_enabled('ENABLE_DECIMAL_DISPENSE_QTY', false);
		$total = 0.0;
		if (!is_array($admin_rows)) {
			return 0.0;
		}
		foreach ($admin_rows as $r) {
			if ($useDecimal) {
				$v = qs_row_value($r, 'dose_given_qty');
				if ($v === null || $v === '') {
					continue;
				}
				$total += (float)$v;
			} else {
				$v = qs_row_value($r, 'dose_given');
				if ($v === null || trim((string)$v) === '') {
					continue;
				}
				$parsed = $this->validate_qty_input($v, 3);
				if (!empty($parsed['ok'])) {
					$total += (float)$parsed['value'];
				}
			}
		}
		return (float)$total;
	}

	public function get_billable_qty($source_row, $fallback = 1.0)
	{
		$useDecimal = qs_flag_enabled('ENABLE_DECIMAL_INVOICE_QTY', false);
		$q = qs_row_value($source_row, 'qty');
		if ($q === null) {
			$q = qs_row_value($source_row, 'quantity');
		}
		if ($q === null || $q === '') {
			return (float)$fallback;
		}
		if ($useDecimal) {
			$qf = (float)$q;
			return ($qf > 0) ? $qf : (float)$fallback;
		}
		$qi = (int)round((float)$q);
		return ($qi > 0) ? (float)$qi : (float)$fallback;
	}

	public function calculate_total_active_mass($prescribed_dose_value, $doses_per_day, $duration_days, $dose_unit)
	{
		$dose = (float)$prescribed_dose_value;
		$dpd = (float)$doses_per_day;
		$days = (float)$duration_days;
		$unit = trim((string)$dose_unit);
		if ($dose <= 0 || $dpd <= 0 || $days <= 0 || $unit === '') {
			return array('ok' => false, 'value' => null, 'unit' => $unit, 'error' => 'invalid_inputs');
		}
		return array('ok' => true, 'value' => ($dose * $dpd * $days), 'unit' => $unit, 'error' => null);
	}

	public function calculate_required_units($prescribed_dose_value, $strength_per_unit_value, $doses_per_day, $duration_days, $dose_unit, $strength_unit)
	{
		$dose = (float)$prescribed_dose_value;
		$str = (float)$strength_per_unit_value;
		$dpd = (float)$doses_per_day;
		$days = (float)$duration_days;
		$du = strtolower(trim((string)$dose_unit));
		$su = strtolower(trim((string)$strength_unit));
		if ($dose <= 0 || $str <= 0 || $dpd <= 0 || $days <= 0 || $du === '' || $su === '') {
			return array('ok' => false, 'value' => null, 'error' => 'invalid_inputs');
		}
		if ($du !== $su) {
			return array('ok' => false, 'value' => null, 'error' => 'unit_mismatch');
		}
		return array('ok' => true, 'value' => (($dose / $str) * $dpd * $days), 'error' => null);
	}

	public function normalize_stock_qty($qty, $pack_to_base_factor = null)
	{
		$qty = (float)$qty;
		if ($pack_to_base_factor === null || $pack_to_base_factor === '' || (float)$pack_to_base_factor <= 0) {
			return $qty;
		}
		return $qty * (float)$pack_to_base_factor;
	}
}
