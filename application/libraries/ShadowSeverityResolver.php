<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowSeverityResolver
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function resolve($domain, $intent, $parity, $proof)
	{
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			$sem_path = APPPATH.'libraries/ShadowGovernanceSemantics.php';
			if (file_exists($sem_path)) {
				require_once($sem_path);
			}
		}
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			return 'CRITICAL';
		}

		$status = ShadowGovernanceSemantics::effectiveGovernanceStatus($parity, $proof);
		$default = 'INFO';
		if ($status === 'UNPROVABLE') {
			$default = 'CRITICAL';
		} elseif ($status === 'VIOLATION') {
			$default = 'CRITICAL';
		} elseif ($status === 'ERROR') {
			$default = 'HIGH';
		}

		$expected = $this->loadExpectedSet($domain, $intent);
		if (!is_array($expected)) {
			return $default;
		}

		$inv = $this->extractInvariant($parity, $proof);
		if ($inv === null || $inv === '') {
			return $default;
		}

		$mapped = $this->lookupInvariantSeverity($expected, $inv);
		if (is_string($mapped) && $mapped !== '') {
			return ShadowGovernanceSemantics::maxSeverity($default, $mapped);
		}

		return $default;
	}

	protected function loadExpectedSet($domain, $intent)
	{
		if (!isset($this->CI->config)) {
			return null;
		}
		$this->CI->config->load('shadow_governance_expectedset', true);
		$expectedset = $this->CI->config->item('shadow_governance_expectedset_v0', 'shadow_governance_expectedset');
		if (!is_array($expectedset) || !isset($expectedset[$domain]) || !is_array($expectedset[$domain])) {
			return null;
		}
		if (!isset($expectedset[$domain][$intent]) || !is_array($expectedset[$domain][$intent])) {
			return null;
		}
		return $expectedset[$domain][$intent];
	}

	protected function extractInvariant($parity, $proof)
	{
		if (is_array($proof) && isset($proof['data']) && is_array($proof['data']) && isset($proof['data']['invariant'])) {
			return (string)$proof['data']['invariant'];
		}
		if (is_array($parity) && isset($parity['data']) && is_array($parity['data']) && isset($parity['data']['invariant'])) {
			return (string)$parity['data']['invariant'];
		}
		return null;
	}

	protected function lookupInvariantSeverity($expected, $invariant)
	{
		if (!is_array($expected) || !isset($expected['invariants']) || !is_array($expected['invariants'])) {
			return null;
		}
		foreach ($expected['invariants'] as $inv) {
			if (!is_array($inv) || !isset($inv['name'])) {
				continue;
			}
			if ((string)$inv['name'] !== (string)$invariant) {
				continue;
			}
			if (isset($inv['severity']) && is_string($inv['severity']) && $inv['severity'] !== '') {
				return (string)$inv['severity'];
			}
		}
		return null;
	}
}
