<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowExpectedSetValidator
{
	public function validateAll()
	{
		$CI =& get_instance();
		if (!isset($CI->config)) {
			return $this->fail('CONFIG_UNAVAILABLE');
		}

		$CI->config->load('shadow_governance_expectedset', true);
		$expectedSet = $CI->config->item('shadow_governance_expectedset_v0', 'shadow_governance_expectedset');
		$requirements = $CI->config->item('shadow_governance_expectedset_completeness_requirements', 'shadow_governance_expectedset');
		if (!is_array($expectedSet)) {
			return $this->fail('EXPECTEDSET_MISSING');
		}
		if (!is_array($requirements) || empty($requirements)) {
			return $this->fail('COMPLETENESS_REQUIREMENTS_MISSING');
		}

		$results = array();
		foreach ($expectedSet as $domain => $contract) {
			$results[$domain] = $this->validateDomain($domain, $contract, $requirements);
		}

		foreach ($results as $result) {
			if (!is_array($result) || !isset($result['status']) || $result['status'] !== 'PASS') {
				return array('status' => 'FAIL', 'results' => $results);
			}
		}
		return array('status' => 'PASS', 'results' => $results);
	}

	public function validateDomainIntent($domain, $intent)
	{
		$CI =& get_instance();
		if (!isset($CI->config)) {
			return $this->fail('CONFIG_UNAVAILABLE');
		}

		$domain = strtoupper(trim((string)$domain));
		$intent = strtoupper(trim((string)$intent));
		$CI->config->load('shadow_governance_expectedset', true);
		$expectedSet = $CI->config->item('shadow_governance_expectedset_v0', 'shadow_governance_expectedset');
		$requirements = $CI->config->item('shadow_governance_expectedset_completeness_requirements', 'shadow_governance_expectedset');
		if (!is_array($expectedSet) || !isset($expectedSet[$domain]) || !is_array($expectedSet[$domain])) {
			return $this->fail('DOMAIN_CONTRACT_MISSING', array('domain' => $domain));
		}
		if (!isset($expectedSet[$domain][$intent]) || !is_array($expectedSet[$domain][$intent])) {
			return $this->fail('INTENT_CONTRACT_MISSING', array('domain' => $domain, 'intent' => $intent));
		}
		if (!is_array($requirements) || empty($requirements)) {
			return $this->fail('COMPLETENESS_REQUIREMENTS_MISSING');
		}
		return $this->validateIntent($domain, $intent, $expectedSet[$domain][$intent], $requirements);
	}

	private function validateDomain($domain, $contract, $requirements)
	{
		if (!is_array($contract)) {
			return $this->fail('DOMAIN_CONTRACT_INVALID', array('domain' => $domain));
		}
		$intents = array('CREATE', 'DELETE');
		$results = array();
		foreach ($intents as $intent) {
			if (!isset($contract[$intent]) || !is_array($contract[$intent])) {
				$results[$intent] = $this->fail('INTENT_CONTRACT_MISSING', array('domain' => $domain, 'intent' => $intent));
				continue;
			}
			$results[$intent] = $this->validateIntent($domain, $intent, $contract[$intent], $requirements);
		}
		foreach ($results as $result) {
			if (!is_array($result) || !isset($result['status']) || $result['status'] !== 'PASS') {
				return array('status' => 'FAIL', 'intents' => $results);
			}
		}
		return array('status' => 'PASS', 'intents' => $results);
	}

	private function validateIntent($domain, $intent, $contract, $requirements)
	{
		foreach ($requirements as $field) {
			$field = (string)$field;
			if ($field === '') {
				continue;
			}
			if (!array_key_exists($field, $contract)) {
				return $this->fail('EXPECTEDSET_FIELD_MISSING', array('domain' => $domain, 'intent' => $intent, 'field' => $field));
			}
			if (!is_array($contract[$field])) {
				return $this->fail('EXPECTEDSET_FIELD_INVALID', array('domain' => $domain, 'intent' => $intent, 'field' => $field));
			}
		}

		$rules = array('required_writes', 'forbidden_writes', 'allowed_writes', 'allowed_side_effects');
		foreach ($rules as $field) {
			if (!$this->validWriteRules($contract[$field])) {
				return $this->fail('WRITE_RULE_INVALID', array('domain' => $domain, 'intent' => $intent, 'field' => $field));
			}
		}
		if (!$this->validDuplicatePolicy($contract['duplicate_policy'])) {
			return $this->fail('DUPLICATE_POLICY_INVALID', array('domain' => $domain, 'intent' => $intent));
		}
		if (!$this->validInvariants($contract['invariants'])) {
			return $this->fail('INVARIANT_INVALID', array('domain' => $domain, 'intent' => $intent));
		}
		return array('status' => 'PASS');
	}

	private function validWriteRules($rules)
	{
		if (!is_array($rules)) {
			return false;
		}
		foreach ($rules as $rule) {
			if (!is_array($rule) || !isset($rule['table']) || (string)$rule['table'] === '') {
				return false;
			}
			$ops = array();
			if (isset($rule['ops']) && is_array($rule['ops'])) {
				$ops = $rule['ops'];
			} elseif (isset($rule['required_ops']) && is_array($rule['required_ops'])) {
				$ops = $rule['required_ops'];
			} elseif (isset($rule['forbidden_ops']) && is_array($rule['forbidden_ops'])) {
				$ops = $rule['forbidden_ops'];
			}
			if (empty($ops)) {
				return false;
			}
			foreach ($ops as $op) {
				if (!in_array((string)$op, array('INSERT', 'UPDATE', 'DELETE'), true)) {
					return false;
				}
			}
		}
		return true;
	}

	private function validDuplicatePolicy($policy)
	{
		if (!is_array($policy)) {
			return false;
		}
		foreach ($policy as $key => $value) {
			if (!is_string($key) || strpos($key, ':') === false || (string)$value === '') {
				return false;
			}
		}
		return true;
	}

	private function validInvariants($invariants)
	{
		if (!is_array($invariants) || empty($invariants)) {
			return false;
		}
		foreach ($invariants as $invariant) {
			if (!is_array($invariant) || !isset($invariant['name']) || (string)$invariant['name'] === '') {
				return false;
			}
		}
		return true;
	}

	private function fail($code, $data = array())
	{
		return array('status' => 'FAIL', 'code' => $code, 'data' => $data);
	}
}
