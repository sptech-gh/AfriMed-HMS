<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowParityEngine
{
	private $trace = array();
	private $debug_enabled = null;

	public function evaluate($domain_map)
	{
		$this->trace = array();
		$results = array();
		if (!is_array($domain_map)) {
			return $results;
		}

		foreach ($domain_map as $domain => $payload) {
			if (!is_array($payload)) {
				$results[$domain] = $this->fail('INVALID_DOMAIN_PAYLOAD');
				continue;
			}

			$intent = isset($payload['intent']) ? $payload['intent'] : null;
			$writes = $this->normalizeObservedSet(isset($payload['tables_touched']) ? $payload['tables_touched'] : array());
			$binding = $this->resolveExpectedSetBinding($domain, $intent, $payload);

			if (empty($binding['bound'])) {
				$results[$domain] = $this->attachParityMetadata($this->fail('NO_EXPECTED_SET'), $binding, $writes);
				continue;
			}

			$expected = $this->getExpectedSet($binding['domain'], $binding['intent']);
			if (!$expected) {
				$results[$domain] = $this->attachParityMetadata($this->fail('NO_EXPECTED_SET'), $binding, $writes);
				continue;
			}

			$results[$domain] = $this->attachParityMetadata($this->evaluateIntent($expected, $writes, $payload), $binding, $writes);
		}

		return $results;
	}

	private function getExpectedSet($domain, $intent)
	{
		if ($domain === null || $domain === '' || $intent === null || $intent === '') {
			return null;
		}

		$CI =& get_instance();
		if (!isset($CI->config)) {
			return null;
		}

		$CI->config->load('shadow_governance_expectedset', true);
		$expectedset = $CI->config->item('shadow_governance_expectedset_v0', 'shadow_governance_expectedset');
		if (!is_array($expectedset) || !isset($expectedset[$domain]) || !is_array($expectedset[$domain])) {
			return null;
		}
		if (!isset($expectedset[$domain][$intent]) || !is_array($expectedset[$domain][$intent])) {
			return null;
		}

		return $expectedset[$domain][$intent];
	}

	private function resolveExpectedSetBinding($domain, $intent, $payload)
	{
		$out = array('bound' => false, 'status' => 'NOT_BOUND', 'domain' => null, 'intent' => null, 'key' => null);
		if (is_array($payload) && !empty($payload['expectedset_contract_bound'])) {
			$contract_domain = isset($payload['expectedset_contract_domain']) ? (string)$payload['expectedset_contract_domain'] : '';
			$contract_intent = isset($payload['expectedset_contract_intent']) ? (string)$payload['expectedset_contract_intent'] : '';
			if ($contract_domain !== '' && $contract_intent !== '') {
				$out['bound'] = true;
				$out['status'] = isset($payload['expectedset_binding_status']) ? (string)$payload['expectedset_binding_status'] : 'BOUND';
				$out['domain'] = $contract_domain;
				$out['intent'] = $contract_intent;
				$out['key'] = isset($payload['expectedset_contract_key']) ? (string)$payload['expectedset_contract_key'] : ($contract_domain . '.' . $contract_intent);
				return $out;
			}
		}
		$domain = (string)$domain;
		$intent = (string)$intent;
		if ($domain !== '' && $intent !== '' && $this->getExpectedSet($domain, $intent)) {
			$out['bound'] = true;
			$out['status'] = 'BOUND_BY_DOMAIN_INTENT';
			$out['domain'] = $domain;
			$out['intent'] = $intent;
			$out['key'] = $domain . '.' . $intent;
			return $out;
		}
		if (is_array($payload) && isset($payload['expectedset_binding_status'])) {
			$out['status'] = (string)$payload['expectedset_binding_status'];
		}
		return $out;
	}

	private function normalizeObservedSet($writes)
	{
		if (!is_array($writes)) {
			return array();
		}
		$out = array();
		foreach ($writes as $w) {
			if (!is_array($w)) {
				continue;
			}
			if (isset($w['table'])) {
				$w['table'] = strtolower(trim((string)$w['table']));
			}
			if (isset($w['operation'])) {
				$w['operation'] = strtoupper(trim((string)$w['operation']));
			}
			$out[] = $w;
		}
		return $out;
	}

	private function attachParityMetadata($result, $binding, $writes)
	{
		if (!is_array($result)) {
			$result = $this->fail('INVALID_PARITY_RESULT');
		}
		$result['expectedset_binding'] = array(
			'bound' => isset($binding['bound']) ? (bool)$binding['bound'] : false,
			'status' => isset($binding['status']) ? (string)$binding['status'] : 'UNKNOWN',
			'domain' => isset($binding['domain']) ? $binding['domain'] : null,
			'intent' => isset($binding['intent']) ? $binding['intent'] : null,
			'key' => isset($binding['key']) ? $binding['key'] : null
		);
		$result['observedset'] = $this->summarizeObservedSet($writes);
		return $result;
	}

	private function summarizeObservedSet($writes)
	{
		$summary = array('count' => 0, 'keys' => array());
		if (!is_array($writes)) {
			return $summary;
		}
		foreach ($writes as $w) {
			if (!is_array($w) || !isset($w['table'], $w['operation'])) {
				continue;
			}
			$key = (string)$w['table'] . ':' . (string)$w['operation'];
			$summary['count']++;
			$summary['keys'][$key] = isset($summary['keys'][$key]) ? ($summary['keys'][$key] + 1) : 1;
		}
		ksort($summary['keys']);
		return $summary;
	}

	private function evaluateIntent($expected, $writes, $payload)
	{
		$checks = array();
		$checks[] = $this->checkExpectedSetCompleteness($expected);

		$checks[] = $this->checkWhenDeterminism($expected, $payload);
		$checks[] = $this->checkPrimaryKeyPresence($expected, $writes, $payload);
		$checks[] = $this->checkUpdateConstraints($expected, $writes);
		$checks[] = $this->checkForbiddenWrites($expected, $writes);
		$checks[] = $this->checkAllowedWrites($expected, $writes);
		$checks[] = $this->checkDuplicateWrites($expected, $writes);
		$checks[] = $this->checkRequiredWrites($expected, $writes, $payload);
		$checks[] = $this->checkInvariants($expected, $writes);

		return $this->aggregate($checks);
	}

	private function checkExpectedSetCompleteness($expected)
	{
		$this->trace[] = 'checkExpectedSetCompleteness';
		$CI =& get_instance();
		if (!isset($CI->config)) {
			return $this->unprovable('EXPECTEDSET_CONFIG_UNAVAILABLE', array(), array(
				'step' => 'checkExpectedSetCompleteness'
			));
		}
		$CI->config->load('shadow_governance_expectedset', true);
		$req = $CI->config->item('shadow_governance_expectedset_completeness_requirements', 'shadow_governance_expectedset');
		if (!is_array($req) || empty($req)) {
			return $this->unprovable('EXPECTEDSET_COMPLETENESS_REQUIREMENTS_MISSING', array(), array(
				'step' => 'checkExpectedSetCompleteness'
			));
		}
		if (!is_array($expected)) {
			return $this->unprovable('EXPECTEDSET_INVALID', array('expected' => $expected), array(
				'step' => 'checkExpectedSetCompleteness'
			));
		}

		foreach ($req as $k) {
			$k = (string)$k;
			if ($k === '') {
				continue;
			}
			if (!array_key_exists($k, $expected)) {
				return $this->unprovable('EXPECTEDSET_INCOMPLETE', array('missing' => $k), array(
					'step' => 'checkExpectedSetCompleteness',
					'missing' => $k
				));
			}
			if (!is_array($expected[$k])) {
				return $this->unprovable('EXPECTEDSET_FIELD_INVALID', array('field' => $k), array(
					'step' => 'checkExpectedSetCompleteness',
					'field' => $k
				));
			}
		}

		return $this->pass();
	}

	private function checkUpdateConstraints($expected, $writes)
	{
		$this->trace[] = 'checkUpdateConstraints';
		if (!is_array($writes) || count($writes) === 0) {
			return $this->pass();
		}
		if (!isset($expected['update_constraints']) || !is_array($expected['update_constraints']) || count($expected['update_constraints']) === 0) {
			return $this->pass();
		}

		// Build allowed update map: table => [col => required_value]
		$allowed_map = array();
		foreach ($expected['update_constraints'] as $c) {
			if (!is_array($c) || !isset($c['table']) || !isset($c['allowed_update'])) {
				return $this->unprovable('UPDATE_CONSTRAINT_INVALID', $c, array(
					'step' => 'checkUpdateConstraints'
				));
			}
			$table = (string)$c['table'];
			$expr = trim((string)$c['allowed_update']);
			$kv = $this->parseSimpleEqualityExpr($expr);
			if ($table === '' || $kv === null) {
				return $this->unprovable('UPDATE_CONSTRAINT_UNPARSABLE', array('table' => $table, 'expr' => $expr), array(
					'step' => 'checkUpdateConstraints'
				));
			}
			if (!isset($allowed_map[$table])) {
				$allowed_map[$table] = array();
			}
			$allowed_map[$table][$kv['col']] = $kv['val'];
		}

		foreach ($writes as $w) {
			if (!is_array($w) || !isset($w['table'], $w['operation'])) {
				continue;
			}
			if ((string)$w['operation'] !== 'UPDATE') {
				continue;
			}
			$table = (string)$w['table'];
			if (!isset($allowed_map[$table])) {
				continue;
			}
			if (!array_key_exists('update_set', $w) || !is_array($w['update_set'])) {
				return $this->unprovable('UPDATE_SET_MISSING', array(
					'table' => $table,
					'write' => $this->sanitize_write($w)
				), array(
					'step' => 'checkUpdateConstraints'
				));
			}
			$update_set = $w['update_set'];

			// Enforce: only allowed columns may be mutated; and the allowed column must be set to the required value.
			foreach ($update_set as $col => $val) {
				if (!array_key_exists($col, $allowed_map[$table])) {
					return $this->violation('DISALLOWED_UPDATE_FIELD', array(
						'table' => $table,
						'field' => $col,
						'value' => $val
					), array(
						'step' => 'checkUpdateConstraints',
						'write' => $this->sanitize_write($w)
					));
				}
			}

			foreach ($allowed_map[$table] as $col => $required_val) {
				if (!array_key_exists($col, $update_set)) {
					return $this->violation('REQUIRED_UPDATE_FIELD_MISSING', array(
						'table' => $table,
						'field' => $col,
						'required' => $required_val
					), array(
						'step' => 'checkUpdateConstraints',
						'write' => $this->sanitize_write($w)
					));
				}
				if ((string)$update_set[$col] !== (string)$required_val) {
					return $this->violation('REQUIRED_UPDATE_VALUE_MISMATCH', array(
						'table' => $table,
						'field' => $col,
						'required' => $required_val,
						'observed' => $update_set[$col]
					), array(
						'step' => 'checkUpdateConstraints',
						'write' => $this->sanitize_write($w)
					));
				}
			}
		}

		return $this->pass();
	}

	private function parseSimpleEqualityExpr($expr)
	{
		$expr = trim((string)$expr);
		if ($expr === '') {
			return null;
		}
		$m = array();
		if (!preg_match('/^([a-zA-Z0-9_]+)\s*=\s*(\d+)$/', $expr, $m)) {
			return null;
		}
		return array('col' => $m[1], 'val' => $m[2]);
	}

	private function checkPrimaryKeyPresence($expected, $writes, $payload)
	{
		$this->trace[] = 'checkPrimaryKeyPresence';
		if (!is_array($writes)) {
			return $this->unprovable('WRITES_NOT_ARRAY', array(), array(
				'step' => 'checkPrimaryKeyPresence'
			));
		}

		$required_keys = $this->getRequiredWriteKeys($expected, $payload);
		if (count($required_keys) === 0) {
			return $this->pass();
		}

		foreach ($writes as $w) {
			if (!is_array($w)) {
				continue;
			}
			if (!isset($w['table'], $w['operation'])) {
				continue;
			}
			$key = $w['table'].':'.$w['operation'];
			if (!in_array($key, $required_keys, true)) {
				continue;
			}
			if (!array_key_exists('primary_key', $w)) {
				return $this->unprovable('PRIMARY_KEY_FIELD_MISSING', array(
					'table' => isset($w['table']) ? $w['table'] : null,
					'operation' => isset($w['operation']) ? $w['operation'] : null,
					'pk_status' => isset($w['pk_status']) ? $w['pk_status'] : null,
					'reason' => isset($w['reason']) ? $w['reason'] : 'unknown'
				), array(
					'step' => 'checkPrimaryKeyPresence',
					'write' => $this->sanitize_write($w)
				));
			}
			if ($w['primary_key'] === null) {
				return $this->unprovable('PRIMARY_KEY_MISSING', array(
					'table' => isset($w['table']) ? $w['table'] : null,
					'operation' => isset($w['operation']) ? $w['operation'] : null,
					'pk_status' => isset($w['pk_status']) ? $w['pk_status'] : null,
					'reason' => isset($w['reason']) ? $w['reason'] : 'unknown'
				), array(
					'step' => 'checkPrimaryKeyPresence',
					'write' => $this->sanitize_write($w)
				));
			}
			$pk_raw = $w['primary_key'];
			if (!is_numeric($pk_raw) || (int)$pk_raw <= 0) {
				return $this->unprovable('PRIMARY_KEY_INVALID', array(
					'table' => isset($w['table']) ? $w['table'] : null,
					'operation' => isset($w['operation']) ? $w['operation'] : null,
					'primary_key' => $pk_raw,
					'pk_status' => isset($w['pk_status']) ? $w['pk_status'] : null,
					'reason' => isset($w['reason']) ? $w['reason'] : 'unknown'
				), array(
					'step' => 'checkPrimaryKeyPresence',
					'write' => $this->sanitize_write($w)
				));
			}
		}

		return $this->pass();
	}

	private function debugEnabled()
	{
		if ($this->debug_enabled !== null) {
			return $this->debug_enabled;
		}
		$CI =& get_instance();
		$this->debug_enabled = false;
		if (isset($CI->config)) {
			$CI->config->load('shadow_parity', true);
			$this->debug_enabled = (bool)$CI->config->item('shadow_parity_debug', 'shadow_parity');
		}
		return $this->debug_enabled;
	}

	private function sanitize_payload($payload)
	{
		if (!is_array($payload)) {
			return array();
		}
		return array(
			'controller' => isset($payload['controller']) ? $payload['controller'] : null,
			'method' => isset($payload['method']) ? $payload['method'] : null,
			'intent' => isset($payload['intent']) ? $payload['intent'] : null,
			'domain' => isset($payload['domain']) ? $payload['domain'] : null
		);
	}

	private function sanitize_write($w)
	{
		if (!is_array($w)) {
			return array();
		}
		return array(
			'table' => isset($w['table']) ? $w['table'] : null,
			'operation' => isset($w['operation']) ? $w['operation'] : null,
			'primary_key' => array_key_exists('primary_key', $w) ? $w['primary_key'] : null,
			'pk_status' => isset($w['pk_status']) ? $w['pk_status'] : null,
			'reason' => isset($w['reason']) ? $w['reason'] : null
		);
	}

	private function sanitize_writes($writes)
	{
		if (!is_array($writes)) {
			return array();
		}
		$out = array();
		foreach ($writes as $w) {
			$out[] = $this->sanitize_write($w);
		}
		return $out;
	}

	private function getRequiredWriteKeys($expected, $payload)
	{
		$keys = array();
		if (!is_array($expected)) {
			return $keys;
		}
		if (!isset($expected['required_writes']) || !is_array($expected['required_writes'])) {
			return $keys;
		}

		foreach ($expected['required_writes'] as $rule) {
			if (!is_array($rule)) {
				continue;
			}
			$table = isset($rule['table']) ? (string)$rule['table'] : '';
			if ($table === '') {
				continue;
			}
			$when = isset($rule['when']) ? (string)$rule['when'] : '';
			$applies = $this->matchCondition($when, $payload);
			if ($applies !== true) {
				continue;
			}
			$ops = array();
			if (isset($rule['required_ops']) && is_array($rule['required_ops'])) {
				$ops = $rule['required_ops'];
			} elseif (isset($rule['operation']) && is_string($rule['operation'])) {
				$ops = array($rule['operation']);
			}
			foreach ($ops as $op) {
				$op = (string)$op;
				if ($op === '') {
					continue;
				}
				$keys[] = $table.':'.$op;
			}
		}

		return array_values(array_unique($keys));
	}

	private function checkDuplicateWrites($expected, $writes)
	{
		$this->trace[] = 'checkDuplicateWrites';
		if (!is_array($writes) || count($writes) === 0) {
			return $this->pass();
		}

		$counts = array();
		foreach ($writes as $w) {
			if (!is_array($w) || !isset($w['table'], $w['operation'])) {
				continue;
			}
			$key = $w['table'].':'.$w['operation'];
			$counts[$key] = isset($counts[$key]) ? ($counts[$key] + 1) : 1;
		}

		$dupes = array();
		foreach ($counts as $key => $n) {
			if ($n > 1) {
				$dupes[$key] = $n;
			}
		}
		if (count($dupes) === 0) {
			return $this->pass();
		}

		if (!isset($expected['duplicate_policy']) || !is_array($expected['duplicate_policy'])) {
			return $this->unprovable('DUPLICATE_POLICY_MISSING', array('duplicates' => $dupes), array(
				'step' => 'checkDuplicateWrites',
				'duplicates' => $dupes
			));
		}

		foreach ($dupes as $key => $n) {
			if (!array_key_exists($key, $expected['duplicate_policy'])) {
				return $this->unprovable('DUPLICATE_POLICY_UNKNOWN', array('duplicate' => array('key' => $key, 'count' => $n)), array(
					'step' => 'checkDuplicateWrites',
					'duplicate' => array('key' => $key, 'count' => $n)
				));
			}

			$policy = (string)$expected['duplicate_policy'][$key];
			if ($policy === 'NOT_ALLOWED') {
				$parts = explode(':', $key, 2);
				return $this->violation('DUPLICATE_WRITE', array(
					'table' => isset($parts[0]) ? $parts[0] : null,
					'operation' => isset($parts[1]) ? $parts[1] : null,
					'count' => $n,
					'policy' => $policy
				), array(
					'step' => 'checkDuplicateWrites',
					'counts' => $dupes
				));
			}
		}

		return $this->pass();
	}

	private function checkWhenDeterminism($expected, $payload)
	{
		$this->trace[] = 'checkWhenDeterminism';
		if (!isset($expected['required_writes']) || !is_array($expected['required_writes']) || count($expected['required_writes']) === 0) {
			return $this->pass();
		}

		foreach ($expected['required_writes'] as $rule) {
			if (!is_array($rule)) {
				return $this->unprovable('REQUIRED_WRITE_RULE_INVALID', $rule);
			}

			$when = isset($rule['when']) ? trim((string)$rule['when']) : '';
			if ($when === '') {
				continue;
			}

			$applies = $this->matchCondition($when, $payload);
			if ($applies === null) {
				return $this->unprovable('WHEN_EVALUATION_FAILED', array(
					'when' => $when,
					'rule' => $rule
				), array(
					'step' => 'checkWhenDeterminism',
					'when' => $when,
					'rule' => $rule,
					'payload' => $this->sanitize_payload($payload)
				));
			}
		}

		return $this->pass();
	}

	private function checkRequiredWrites($expected, $writes, $payload)
	{
		$this->trace[] = 'checkRequiredWrites';
		if (!isset($expected['required_writes']) || !is_array($expected['required_writes']) || count($expected['required_writes']) === 0) {
			return $this->pass();
		}

		foreach ($expected['required_writes'] as $rule) {
			if (!is_array($rule)) {
				return $this->unprovable('REQUIRED_WRITE_RULE_INVALID', $rule);
			}

			$when = isset($rule['when']) ? (string)$rule['when'] : '';
			$applies = $this->matchCondition($when, $payload);
			if ($applies === false) {
				continue;
			}
			if ($applies === null) {
				return $this->unprovable('WHEN_EVALUATION_FAILED', array(
					'when' => $when,
					'rule' => $rule
				));
			}

			$table = isset($rule['table']) ? (string)$rule['table'] : '';
			$ops = array();
			if (isset($rule['required_ops']) && is_array($rule['required_ops'])) {
				$ops = $rule['required_ops'];
			} elseif (isset($rule['operation']) && is_string($rule['operation'])) {
				$ops = array($rule['operation']);
			}

			if ($table === '' || count($ops) === 0) {
				return $this->unprovable('REQUIRED_WRITE_RULE_MISSING_FIELDS', $rule);
			}

			foreach ($ops as $op) {
				$op = (string)$op;
				$matched = false;
				$matched_write = null;
				foreach ($writes as $w) {
					if (!is_array($w)) {
						continue;
					}
					if (isset($w['table'], $w['operation']) && $w['table'] === $table && $w['operation'] === $op) {
						$matched = true;
						$matched_write = $w;
						break;
					}
				}

				if (!$matched) {
					return $this->violation('REQUIRED_WRITE_MISSING', array('rule' => $rule, 'required_op' => $op), array(
						'step' => 'checkRequiredWrites',
						'rule' => $rule,
						'required_op' => $op,
						'writes_seen' => $this->sanitize_writes($writes)
					));
				}

				if ($op === 'UPDATE' && isset($rule['required_update']) && is_string($rule['required_update']) && trim($rule['required_update']) !== '') {
					$expr = trim((string)$rule['required_update']);
					$kv = $this->parseSimpleEqualityExpr($expr);
					if ($kv === null) {
						return $this->unprovable('REQUIRED_UPDATE_UNPARSABLE', array('expr' => $expr, 'rule' => $rule), array(
							'step' => 'checkRequiredWrites',
							'rule' => $rule,
							'required_op' => $op
						));
					}
					if (!is_array($matched_write) || !array_key_exists('update_set', $matched_write) || !is_array($matched_write['update_set'])) {
						return $this->unprovable('REQUIRED_UPDATE_UNPROVABLE', array(
							'table' => $table,
							'operation' => $op,
							'expr' => $expr
						), array(
							'step' => 'checkRequiredWrites',
							'write' => $this->sanitize_write($matched_write)
						));
					}
					$set = $matched_write['update_set'];
					if (!array_key_exists($kv['col'], $set)) {
						return $this->violation('REQUIRED_UPDATE_FIELD_MISSING', array(
							'table' => $table,
							'field' => $kv['col'],
							'required' => $kv['val']
						), array(
							'step' => 'checkRequiredWrites',
							'write' => $this->sanitize_write($matched_write)
						));
					}
					if ((string)$set[$kv['col']] !== (string)$kv['val']) {
						return $this->violation('REQUIRED_UPDATE_VALUE_MISMATCH', array(
							'table' => $table,
							'field' => $kv['col'],
							'required' => $kv['val'],
							'observed' => $set[$kv['col']]
						), array(
							'step' => 'checkRequiredWrites',
							'write' => $this->sanitize_write($matched_write)
						));
					}
				}
			}
		}

		return $this->pass();
	}

	private function checkForbiddenWrites($expected, $writes)
	{
		$this->trace[] = 'checkForbiddenWrites';
		if (!isset($expected['forbidden_writes']) || !is_array($expected['forbidden_writes']) || count($expected['forbidden_writes']) === 0) {
			return $this->pass();
		}

		foreach ($writes as $w) {
			if (!is_array($w)) {
				continue;
			}
			foreach ($expected['forbidden_writes'] as $rule) {
				if (!is_array($rule)) {
					return $this->unprovable('FORBIDDEN_WRITE_RULE_INVALID', $rule);
				}

				$table = isset($rule['table']) ? (string)$rule['table'] : '';
				$ops = array();
				if (isset($rule['forbidden_ops']) && is_array($rule['forbidden_ops'])) {
					$ops = $rule['forbidden_ops'];
				} elseif (isset($rule['operation']) && is_string($rule['operation'])) {
					$ops = array($rule['operation']);
				}

				if ($table === '' || count($ops) === 0) {
					return $this->unprovable('FORBIDDEN_WRITE_RULE_MISSING_FIELDS', $rule);
				}

				if (isset($w['table'], $w['operation']) && $w['table'] === $table && in_array($w['operation'], $ops, true)) {
					$code = 'FORBIDDEN_WRITE';
					if (isset($rule['violation']) && is_string($rule['violation']) && $rule['violation'] !== '') {
						$code = $rule['violation'];
					}
					return $this->violation($code, array('write' => $w, 'rule' => $rule), array(
						'step' => 'checkForbiddenWrites',
						'write' => $this->sanitize_write($w),
						'rule' => $rule
					));
				}
			}
		}

		return $this->pass();
	}

	private function checkAllowedWrites($expected, $writes)
	{
		$this->trace[] = 'checkAllowedWrites';
		if (!isset($expected['allowed_writes']) || !is_array($expected['allowed_writes']) || count($expected['allowed_writes']) === 0) {
			return $this->pass();
		}

		foreach ($writes as $w) {
			if (!is_array($w)) {
				return $this->unprovable('WRITE_EVENT_INVALID', $w);
			}
			if (!isset($w['table'], $w['operation'])) {
				return $this->unprovable('WRITE_EVENT_MISSING_FIELDS', $w);
			}

			$allowed = $this->writeMatchesRuleSet($w, isset($expected['allowed_writes']) ? $expected['allowed_writes'] : array());
			if ($allowed !== true && isset($expected['allowed_side_effects']) && is_array($expected['allowed_side_effects'])) {
				$allowed = $this->writeMatchesRuleSet($w, $expected['allowed_side_effects']);
			}

			if ($allowed !== true) {
				return $this->violation('WRITE_NOT_ALLOWED', $w, array(
					'step' => 'checkAllowedWrites',
					'write' => $this->sanitize_write($w)
				));
			}
		}

		return $this->pass();
	}

	private function writeMatchesRuleSet($write, $rules)
	{
		if (!is_array($rules)) {
			return false;
		}
		foreach ($rules as $rule) {
			if (!is_array($rule)) {
				return false;
			}
			$table = isset($rule['table']) ? strtolower(trim((string)$rule['table'])) : '';
			$ops = array();
			if (isset($rule['ops']) && is_array($rule['ops'])) {
				$ops = $rule['ops'];
			} elseif (isset($rule['operation']) && is_string($rule['operation'])) {
				$ops = array($rule['operation']);
			}
			$ops = array_map(function ($op) {
				return strtoupper(trim((string)$op));
			}, $ops);
			if ($table === '' || count($ops) === 0) {
				return false;
			}
			if ($write['table'] === $table && in_array($write['operation'], $ops, true)) {
				return true;
			}
		}
		return false;
	}

	private function checkInvariants($expected, $writes)
	{
		$this->trace[] = 'checkInvariants';
		if (!isset($expected['invariants']) || !is_array($expected['invariants']) || count($expected['invariants']) === 0) {
			return $this->pass();
		}

		foreach ($expected['invariants'] as $inv) {
			if (!is_array($inv)) {
				return $this->unprovable('INVARIANT_INVALID', $inv);
			}

			$type = isset($inv['type']) ? (string)$inv['type'] : '';
			if ($type === 'cardinality') {
				$expected_count = isset($inv['expected']) ? (int)$inv['expected'] : null;
				$tables = isset($inv['tables']) && is_array($inv['tables']) ? $inv['tables'] : null;
				if ($expected_count === null || $tables === null) {
					return $this->unprovable('INVARIANT_MISSING_FIELDS', $inv);
				}

				$unique = array();
				foreach ($writes as $w) {
					if (!is_array($w) || !isset($w['table'])) {
						continue;
					}
					if (in_array($w['table'], $tables, true)) {
						$unique[$w['table']] = true;
					}
				}
				$actual = count($unique);
				if ($actual !== $expected_count) {
					return $this->violation('CARDINALITY_FAILED', array(
						'invariant' => $inv,
						'expected' => $expected_count,
						'actual' => $actual
					), array(
						'step' => 'checkInvariants',
						'invariant' => $inv,
						'writes_seen' => $this->sanitize_writes($writes)
					));
				}
			}
		}

		return $this->pass();
	}

	private function getRelevantTables($expected)
	{
		$set = array();
		if (!is_array($expected)) {
			return array();
		}

		if (isset($expected['required_writes']) && is_array($expected['required_writes'])) {
			foreach ($expected['required_writes'] as $r) {
				if (is_array($r) && isset($r['table']) && $r['table'] !== '') {
					$set[(string)$r['table']] = true;
				}
			}
		}
		if (isset($expected['forbidden_writes']) && is_array($expected['forbidden_writes'])) {
			foreach ($expected['forbidden_writes'] as $r) {
				if (is_array($r) && isset($r['table']) && $r['table'] !== '') {
					$set[(string)$r['table']] = true;
				}
			}
		}
		if (isset($expected['allowed_writes']) && is_array($expected['allowed_writes'])) {
			foreach ($expected['allowed_writes'] as $r) {
				if (is_array($r) && isset($r['table']) && $r['table'] !== '') {
					$set[(string)$r['table']] = true;
				}
			}
		}
		if (isset($expected['invariants']) && is_array($expected['invariants'])) {
			foreach ($expected['invariants'] as $inv) {
				if (is_array($inv) && isset($inv['tables']) && is_array($inv['tables'])) {
					foreach ($inv['tables'] as $t) {
						if (is_string($t) && $t !== '') {
							$set[$t] = true;
						}
					}
				}
			}
		}

		return array_keys($set);
	}

	private function matchCondition($expr, $payload)
	{
		$expr = trim((string)$expr);
		if ($expr === '') {
			return true;
		}
		if (!is_array($payload)) {
			return null;
		}

		$m = array();
		if (preg_match("/^method\s+contains\s+'([^']+)'$/i", $expr, $m)) {
			$needle = strtolower($m[1]);
			$method = isset($payload['method']) ? strtolower((string)$payload['method']) : '';
			return ($needle !== '' && $method !== '') ? (strpos($method, $needle) !== false) : null;
		}
		if (preg_match("/^method\s+is\s+'([^']+)'$/i", $expr, $m)) {
			$needle = strtolower($m[1]);
			$method = isset($payload['method']) ? strtolower((string)$payload['method']) : '';
			return ($needle !== '' && $method !== '') ? ($method === $needle) : null;
		}
		if (preg_match("/^controller\s+contains\s+'([^']+)'$/i", $expr, $m)) {
			$needle = strtolower($m[1]);
			$controller = isset($payload['controller']) ? strtolower((string)$payload['controller']) : '';
			return ($needle !== '' && $controller !== '') ? (strpos($controller, $needle) !== false) : null;
		}

		return null;
	}

	private function aggregate($checks)
	{
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			$sem_path = APPPATH.'libraries/ShadowGovernanceSemantics.php';
			if (file_exists($sem_path)) {
				require_once($sem_path);
			}
		}
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			return $this->unprovable('SEMANTICS_NOT_AVAILABLE');
		}

		return ShadowGovernanceSemantics::worstResult($checks, 'INVALID_CHECK_RESULT');
	}

	private function pass()
	{
		return array('status' => 'PASS');
	}

	private function violation($code, $data = array(), $debug = null)
	{
		$res = array(
			'status' => 'VIOLATION',
			'code' => $code,
			'data' => $data
		);
		if ($this->debugEnabled()) {
			if (is_array($debug)) {
				$debug['trace'] = $this->trace;
			} else {
				$debug = array('trace' => $this->trace);
			}
			$res['debug'] = $debug;
		}
		return $res;
	}

	private function unprovable($code, $data = array(), $debug = null)
	{
		$res = array(
			'status' => 'UNPROVABLE',
			'code' => $code,
			'data' => $data
		);
		if ($this->debugEnabled()) {
			if (is_array($debug)) {
				$debug['trace'] = $this->trace;
			} else {
				$debug = array('trace' => $this->trace);
			}
			$res['debug'] = $debug;
		}
		return $res;
	}

	private function fail($code)
	{
		return array(
			'status' => 'ERROR',
			'code' => $code
		);
	}
}
