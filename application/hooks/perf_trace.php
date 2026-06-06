<?php
defined('BASEPATH') OR exit('No direct script access allowed');

if (!function_exists('perf_trace_bootstrap')) {
	function perf_trace_bootstrap()
	{
		if (getenv('PERF_LOG') !== '1') {
			return;
		}
		$CI =& get_instance();
		if (!isset($CI)) {
			return;
		}
		$GLOBALS['__perf_trace_start_ts'] = microtime(true);
		$GLOBALS['__perf_trace_start_mem'] = function_exists('memory_get_usage') ? memory_get_usage(true) : 0;
	}
}

if (!function_exists('perf_trace_finalize')) {
	function perf_trace_finalize()
	{
		if (getenv('PERF_LOG') !== '1') {
			return;
		}

		$CI =& get_instance();
		if (!isset($CI)) {
			return;
		}

		$start = isset($GLOBALS['__perf_trace_start_ts']) ? (float)$GLOBALS['__perf_trace_start_ts'] : null;
		if ($start === null) {
			return;
		}

		$elapsed_ms = (microtime(true) - $start) * 1000.0;
		$peak_mem = function_exists('memory_get_peak_usage') ? memory_get_peak_usage(true) : 0;

		$uri = '';
		if (isset($CI->uri) && is_object($CI->uri)) {
			$uri = (string)$CI->uri->uri_string();
		}
		if ($uri === '') {
			$uri = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
		}

		$controller = isset($CI->router) && is_object($CI->router) ? (string)$CI->router->fetch_class() : '';
		$method = isset($CI->router) && is_object($CI->router) ? (string)$CI->router->fetch_method() : '';

		$qcount = null;
		if (isset($CI->db) && is_object($CI->db) && isset($CI->db->queries) && is_array($CI->db->queries)) {
			$qcount = count($CI->db->queries);
		}

		$qsum_ms = null;
		$qmax_ms = null;
		if (isset($CI->db) && is_object($CI->db) && isset($CI->db->query_times) && is_array($CI->db->query_times)) {
			$qt = $CI->db->query_times;
			if (!empty($qt)) {
				$sum = 0.0;
				$max = 0.0;
				foreach ($qt as $t) {
					$ms = ((float)$t) * 1000.0;
					$sum += $ms;
					if ($ms > $max) { $max = $ms; }
				}
				$qsum_ms = $sum;
				$qmax_ms = $max;
			}
		}

		$code = function_exists('http_response_code') ? (int)http_response_code() : 0;

		log_message(
			'debug',
			'[PERF_TRACE] ms=' . number_format($elapsed_ms, 1, '.', '')
			. ' code=' . $code
			. ' q=' . ($qcount === null ? 'na' : (string)$qcount)
			. ' q_ms=' . ($qsum_ms === null ? 'na' : number_format($qsum_ms, 1, '.', ''))
			. ' q_max_ms=' . ($qmax_ms === null ? 'na' : number_format($qmax_ms, 1, '.', ''))
			. ' peak_mem=' . (string)$peak_mem
			. ' ctl=' . $controller
			. ' m=' . $method
			. ' uri=' . $uri
		);
	}
}
