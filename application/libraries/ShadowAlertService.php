<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowAlertService
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function send($data)
	{
		if (!is_array($data)) {
			return false;
		}
		if (!isset($this->CI->config)) {
			return false;
		}

		$this->CI->config->load('shadow_audit', true);
		$enabled = (bool)$this->CI->config->item('shadow_alert_enabled', 'shadow_audit');
		if (!$enabled) {
			return false;
		}

		$threshold = (string)$this->CI->config->item('shadow_alert_severity_threshold', 'shadow_audit');
		if ($threshold === '') {
			$threshold = 'CRITICAL';
		}

		$severity = isset($data['severity']) ? (string)$data['severity'] : '';
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			$sem_path = APPPATH.'libraries/ShadowGovernanceSemantics.php';
			if (file_exists($sem_path)) {
				require_once($sem_path);
			}
		}
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			return false;
		}
		if (!ShadowGovernanceSemantics::meetsSeverityThreshold($severity, $threshold)) {
			return false;
		}

		if ($this->recentDuplicate($data)) {
			return false;
		}

		log_message('error', '[SHADOW_ALERT] ' . json_encode($data));
		return true;
	}

	protected function recentDuplicate($data)
	{
		if (!isset($this->CI->cache) || !is_object($this->CI->cache)) {
			return false;
		}

		$domain = isset($data['domain']) ? (string)$data['domain'] : '';
		$intent = isset($data['intent']) ? (string)$data['intent'] : '';
		$parity_code = (isset($data['parity']) && is_array($data['parity']) && isset($data['parity']['code'])) ? (string)$data['parity']['code'] : '';
		$proof_code = (isset($data['proof']) && is_array($data['proof']) && isset($data['proof']['code'])) ? (string)$data['proof']['code'] : '';
		$hash = sha1($domain . '|' . $intent . '|' . $parity_code . '|' . $proof_code);
		$key = 'shadow_alert_rl_' . $hash;

		$this->CI->config->load('shadow_audit', true);
		$ttl = (int)$this->CI->config->item('shadow_alert_rate_limit_seconds', 'shadow_audit');
		if ($ttl <= 0) {
			$ttl = 60;
		}

		$hit = $this->CI->cache->get($key);
		if ($hit) {
			return true;
		}
		$this->CI->cache->save($key, 1, $ttl);
		return false;
	}
}
