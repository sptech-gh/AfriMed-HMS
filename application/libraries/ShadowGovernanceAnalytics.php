<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowGovernanceAnalytics
{
	protected $logDir;

	public function __construct($logDir = null)
	{
		$this->logDir = $logDir !== null ? (string)$logDir : APPPATH . 'logs';
	}

	public function analyze($days = 1)
	{
		$days = (int)$days;
		if ($days <= 0) {
			$days = 1;
		}
		if ($days > 31) {
			$days = 31;
		}

		$report = $this->emptyReport($days);
		$files = $this->resolveLogFiles($days);
		$report['files_scanned'] = array_values($files);

		foreach ($files as $file) {
			$this->scanFile($file, $report);
		}

		$this->finalizeReport($report);
		return $report;
	}

	protected function emptyReport($days)
	{
		return array(
			'status' => 'PASS',
			'mode' => 'READ_ONLY_LOG_ANALYTICS',
			'days' => (int)$days,
			'files_scanned' => array(),
			'events' => array(
				'lifecycle_bootstrap' => 0,
				'db_writes' => 0,
				'parity_results' => 0,
				'proof_results' => 0,
				'evaluations' => 0,
				'unprovable_metrics' => 0,
				'alerts' => 0
			),
			'controllers' => array(),
			'methods' => array(),
			'domains' => array(),
			'intents' => array(),
			'parity_statuses' => array(),
			'parity_codes' => array(),
			'proof_statuses' => array(),
			'proof_codes' => array(),
			'drift_classes' => array(),
			'severities' => array(),
			'unprovable_causes' => array(),
			'invariant_failures' => array(),
			'write_tables' => array(),
			'cross_domain_write_signals' => array(),
			'duplicate_write_signals' => array(),
			'coverage' => array(
				'lifecycle_count' => 0,
				'resolved_domain_count' => 0,
				'parity_count' => 0,
				'proof_count' => 0,
				'pass_count' => 0,
				'violation_count' => 0,
				'unprovable_count' => 0,
				'registry_match_percent' => 0,
				'proof_coverage_percent' => 0,
				'pass_percent' => 0,
				'violation_percent' => 0,
				'unprovable_percent' => 0
			),
			'top' => array()
		);
	}

	protected function resolveLogFiles($days)
	{
		$files = array();
		for ($i = 0; $i < $days; $i++) {
			$day = date('Y-m-d', strtotime('-' . $i . ' days'));
			$file = rtrim($this->logDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'log-' . $day . '.php';
			if (is_file($file) && is_readable($file)) {
				$files[] = $file;
			}
		}
		return $files;
	}

	protected function scanFile($file, &$report)
	{
		$handle = @fopen($file, 'r');
		if (!$handle) {
			return;
		}
		while (($line = fgets($handle)) !== false) {
			$this->scanLine($line, $report);
		}
		fclose($handle);
	}

	protected function scanLine($line, &$report)
	{
		if (strpos($line, '[SHADOW_GOV] bootstrap') !== false) {
			$report['events']['lifecycle_bootstrap']++;
			$report['coverage']['lifecycle_count']++;
			$kv = $this->parseKeyValues($line);
			$this->incrementIfSet($report['controllers'], $kv, 'controller');
			$this->incrementIfSet($report['methods'], $kv, 'method');
			return;
		}

		if (strpos($line, '[SHADOW_GOV][DB_WRITE]') !== false) {
			$report['events']['db_writes']++;
			$table = $this->extractWriteTable($line);
			if ($table !== '') {
				$this->increment($report['write_tables'], $table);
			}
			return;
		}

		if (strpos($line, '[SHADOW_PARITY]') !== false) {
			$payload = $this->parseJsonAfter($line, '[SHADOW_PARITY]');
			$this->ingestParity($payload, $report);
			return;
		}

		if (strpos($line, '[SHADOW_PROOF]') !== false) {
			$payload = $this->parseJsonAfter($line, '[SHADOW_PROOF]');
			$this->ingestProof($payload, $report);
			return;
		}

		if (strpos($line, '[SHADOW_EVAL]') !== false) {
			$payload = $this->parseJsonAfter($line, '[SHADOW_EVAL]');
			$this->ingestEvaluation($payload, $report);
			return;
		}

		if (strpos($line, '[SHADOW_METRIC][UNPROVABLE]') !== false) {
			$report['events']['unprovable_metrics']++;
			$kv = $this->parseKeyValues($line);
			$cause = isset($kv['source']) && isset($kv['category']) ? $kv['source'] . ':' . $kv['category'] : 'UNKNOWN';
			$this->increment($report['unprovable_causes'], $cause);
			$this->incrementIfSet($report['domains'], $kv, 'domain');
			$this->incrementIfSet($report['intents'], $kv, 'intent');
			return;
		}

		if (strpos($line, '[SHADOW_ALERT]') !== false) {
			$report['events']['alerts']++;
			$payload = $this->parseJsonAfter($line, '[SHADOW_ALERT]');
			if (is_array($payload) && isset($payload['severity'])) {
				$this->increment($report['severities'], (string)$payload['severity']);
			}
		}
	}

	protected function ingestParity($payload, &$report)
	{
		if (!is_array($payload)) {
			return;
		}
		foreach ($payload as $domain => $result) {
			if (!is_array($result)) {
				continue;
			}
			$report['events']['parity_results']++;
			$report['coverage']['parity_count']++;
			$domain = (string)$domain;
			$this->increment($report['domains'], $domain);
			if ($domain !== '' && $domain !== 'UNKNOWN') {
				$report['coverage']['resolved_domain_count']++;
			}
			$status = isset($result['status']) ? (string)$result['status'] : 'UNKNOWN';
			$code = isset($result['code']) ? (string)$result['code'] : 'NONE';
			$this->increment($report['parity_statuses'], $status);
			$this->increment($report['parity_codes'], $code);
			$this->ingestOutcomeStatus($status, $report);
			if ($status === 'UNPROVABLE') {
				$this->increment($report['unprovable_causes'], 'PARITY:' . $code);
			}
			if ($code === 'WRITE_NOT_ALLOWED') {
				$this->increment($report['cross_domain_write_signals'], $domain);
			}
			if (strpos($code, 'DUPLICATE') !== false) {
				$this->increment($report['duplicate_write_signals'], $domain . ':' . $code);
			}
		}
	}

	protected function ingestProof($payload, &$report)
	{
		if (!is_array($payload)) {
			return;
		}
		$report['events']['proof_results']++;
		$report['coverage']['proof_count']++;
		$status = isset($payload['status']) ? (string)$payload['status'] : 'UNKNOWN';
		$code = isset($payload['code']) ? (string)$payload['code'] : 'NONE';
		$this->increment($report['proof_statuses'], $status);
		$this->increment($report['proof_codes'], $code);
		$this->ingestOutcomeStatus($status, $report);
		if (isset($payload['drift_class'])) {
			$this->increment($report['drift_classes'], (string)$payload['drift_class']);
		}
		if ($status === 'UNPROVABLE') {
			$this->increment($report['unprovable_causes'], 'PROOF:' . $code);
		}
		if (isset($payload['proof_binding']) && is_array($payload['proof_binding'])) {
			if (isset($payload['proof_binding']['domain'])) {
				$this->increment($report['domains'], (string)$payload['proof_binding']['domain']);
			}
			if (isset($payload['proof_binding']['intent'])) {
				$this->increment($report['intents'], (string)$payload['proof_binding']['intent']);
			}
		}
		if (isset($payload['proof_results']) && is_array($payload['proof_results'])) {
			foreach ($payload['proof_results'] as $result) {
				$this->ingestProofResultItem($result, $report);
			}
		}
	}

	protected function ingestProofResultItem($result, &$report)
	{
		if (!is_array($result)) {
			return;
		}
		$status = isset($result['status']) ? (string)$result['status'] : 'UNKNOWN';
		if ($status === 'PASS') {
			return;
		}
		$code = isset($result['code']) ? (string)$result['code'] : 'NONE';
		$invariant = 'UNKNOWN';
		if (isset($result['data']) && is_array($result['data']) && isset($result['data']['invariant'])) {
			$invariant = (string)$result['data']['invariant'];
		}
		$this->increment($report['invariant_failures'], $invariant . ':' . $code);
	}

	protected function ingestEvaluation($payload, &$report)
	{
		if (!is_array($payload)) {
			return;
		}
		$report['events']['evaluations']++;
		if (isset($payload['domain'])) {
			$this->increment($report['domains'], (string)$payload['domain']);
		}
		if (isset($payload['intent'])) {
			$this->increment($report['intents'], (string)$payload['intent']);
		}
		if (isset($payload['severity'])) {
			$this->increment($report['severities'], (string)$payload['severity']);
		}
	}

	protected function ingestOutcomeStatus($status, &$report)
	{
		if ($status === 'PASS') {
			$report['coverage']['pass_count']++;
		} elseif ($status === 'VIOLATION') {
			$report['coverage']['violation_count']++;
		} elseif ($status === 'UNPROVABLE') {
			$report['coverage']['unprovable_count']++;
		}
	}

	protected function parseJsonAfter($line, $marker)
	{
		$pos = strpos($line, $marker);
		if ($pos === false) {
			return null;
		}
		$json = trim(substr($line, $pos + strlen($marker)));
		$start = strpos($json, '{');
		if ($start === false) {
			return null;
		}
		$json = substr($json, $start);
		$decoded = json_decode($json, true);
		return is_array($decoded) ? $decoded : null;
	}

	protected function parseKeyValues($line)
	{
		$out = array();
		if (preg_match_all('/([a-zA-Z0-9_]+)=([^\s]+)/', $line, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$out[$match[1]] = $match[2];
			}
		}
		return $out;
	}

	protected function extractWriteTable($line)
	{
		if (preg_match('/\bsql\s*=\s*(INSERT\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-zA-Z0-9_]+)`?/i', $line, $m)) {
			return strtolower((string)$m[2]);
		}
		return '';
	}

	protected function incrementIfSet(&$bucket, $kv, $key)
	{
		if (isset($kv[$key]) && (string)$kv[$key] !== '') {
			$this->increment($bucket, (string)$kv[$key]);
		}
	}

	protected function increment(&$bucket, $key, $amount = 1)
	{
		$key = (string)$key;
		if ($key === '') {
			$key = 'UNKNOWN';
		}
		if (!isset($bucket[$key])) {
			$bucket[$key] = 0;
		}
		$bucket[$key] += (int)$amount;
	}

	protected function finalizeReport(&$report)
	{
		$coverage = $report['coverage'];
		$report['coverage']['registry_match_percent'] = $this->percent($coverage['resolved_domain_count'], $coverage['parity_count']);
		$report['coverage']['proof_coverage_percent'] = $this->percent($coverage['proof_count'], $coverage['parity_count']);
		$total_outcomes = $coverage['pass_count'] + $coverage['violation_count'] + $coverage['unprovable_count'];
		$report['coverage']['pass_percent'] = $this->percent($coverage['pass_count'], $total_outcomes);
		$report['coverage']['violation_percent'] = $this->percent($coverage['violation_count'], $total_outcomes);
		$report['coverage']['unprovable_percent'] = $this->percent($coverage['unprovable_count'], $total_outcomes);
		$report['top'] = array(
			'controllers' => $this->top($report['controllers']),
			'methods' => $this->top($report['methods']),
			'domains' => $this->top($report['domains']),
			'parity_codes' => $this->top($report['parity_codes']),
			'proof_codes' => $this->top($report['proof_codes']),
			'unprovable_causes' => $this->top($report['unprovable_causes']),
			'invariant_failures' => $this->top($report['invariant_failures']),
			'write_tables' => $this->top($report['write_tables']),
			'cross_domain_write_signals' => $this->top($report['cross_domain_write_signals']),
			'duplicate_write_signals' => $this->top($report['duplicate_write_signals'])
		);
	}

	protected function percent($value, $total)
	{
		$value = (int)$value;
		$total = (int)$total;
		if ($total <= 0) {
			return 0;
		}
		return round(($value / $total) * 100, 2);
	}

	protected function top($bucket, $limit = 10)
	{
		if (!is_array($bucket) || empty($bucket)) {
			return array();
		}
		arsort($bucket);
		$out = array();
		$count = 0;
		foreach ($bucket as $key => $value) {
			$out[] = array('key' => (string)$key, 'count' => (int)$value);
			$count++;
			if ($count >= $limit) {
				break;
			}
		}
		return $out;
	}
}
