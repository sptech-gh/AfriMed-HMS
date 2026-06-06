<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowProofExecutor
{
	protected $CI;

	public function __construct()
	{
		$this->CI =& get_instance();
	}

	public function execute($domain, $intent, $writes, $lifecycle = array())
	{
		$binding = $this->resolveBinding($domain, $intent, $lifecycle);
		$contracts = $this->getContracts($binding['domain'], $binding['intent']);
		if (!$contracts) {
			return $this->attachProofSet($this->unprovable('NO_PROOF_CONTRACT'), $binding, array(), array());
		}

		$proofset = $this->buildProofSet($contracts);
		$writes = $this->normalizeWrites($writes);
		$results = array();
		foreach ($contracts as $entry) {
			$contract = $this->normalizeContract($entry);
			if (!is_array($contract)) {
				$results[] = $this->unprovable('CONTRACT_INVALID', array('contract' => $entry));
				continue;
			}
			$results[] = $this->executeContract($binding['domain'], $binding['intent'], $contract, $writes, $lifecycle);
		}

		return $this->attachProofSet($this->aggregate($results), $binding, $proofset, $results);
	}

	private function resolveBinding($domain, $intent, $lifecycle)
	{
		$out = array('domain' => (string)$domain, 'intent' => (string)$intent, 'key' => (string)$domain . '.' . (string)$intent, 'status' => 'BOUND_BY_ARGUMENT');
		if (is_array($lifecycle) && !empty($lifecycle['expectedset_contract_bound'])) {
			$contract_domain = isset($lifecycle['expectedset_contract_domain']) ? (string)$lifecycle['expectedset_contract_domain'] : '';
			$contract_intent = isset($lifecycle['expectedset_contract_intent']) ? (string)$lifecycle['expectedset_contract_intent'] : '';
			if ($contract_domain !== '' && $contract_intent !== '') {
				$out['domain'] = $contract_domain;
				$out['intent'] = $contract_intent;
				$out['key'] = isset($lifecycle['expectedset_contract_key']) ? (string)$lifecycle['expectedset_contract_key'] : ($contract_domain . '.' . $contract_intent);
				$out['status'] = isset($lifecycle['expectedset_binding_status']) ? (string)$lifecycle['expectedset_binding_status'] : 'BOUND';
			}
		}
		return $out;
	}

	private function getContracts($domain, $intent)
	{
		if ($domain === null || $domain === '' || $intent === null || $intent === '') {
			return null;
		}
		if (!isset($this->CI->config)) {
			return null;
		}
		$this->CI->config->load('shadow_governance_proof_contracts', true);
		$version = $this->CI->config->item('shadow_governance_proof_contracts_version', 'shadow_governance_proof_contracts');
		$key = strtolower((string)$version);
		$config = $this->CI->config->item('shadow_governance_proof_contracts_' . $key, 'shadow_governance_proof_contracts');
		if (!is_array($config)) {
			$config = $this->CI->config->item('shadow_governance_proof_contracts_v0', 'shadow_governance_proof_contracts');
		}
		if (!is_array($config) || !isset($config[$domain]) || !isset($config[$domain][$intent]) || !is_array($config[$domain][$intent])) {
			return null;
		}
		return $config[$domain][$intent];
	}

	private function buildProofSet($contracts)
	{
		$out = array();
		foreach ($contracts as $entry) {
			$contract = $this->normalizeContract($entry);
			if (!is_array($contract)) {
				continue;
			}
			$out[] = array(
			'name' => $contract['name'],
			'type' => $contract['type'],
			'proof_inputs' => $contract['proof_inputs'],
			'evaluation_mode' => $contract['evaluation_mode'],
			'failure_classification' => $contract['failure_classification']
			);
		}
		return $out;
	}

	private function normalizeContract($entry)
	{
		if (!is_array($entry)) {
			return null;
		}
		if (isset($entry['name'], $entry['type'], $entry['proof_query']) && is_array($entry['proof_query'])) {
			$entry['type'] = strtoupper((string)$entry['type']);
			$entry['proof_inputs'] = isset($entry['proof_inputs']) && is_array($entry['proof_inputs']) ? $entry['proof_inputs'] : array();
			$entry['evaluation_mode'] = isset($entry['evaluation_mode']) ? (string)$entry['evaluation_mode'] : 'STRICT';
			$entry['failure_classification'] = isset($entry['failure_classification']) && is_array($entry['failure_classification']) ? $entry['failure_classification'] : array('unprovable' => 'CRITICAL', 'false_state' => 'CRITICAL', 'missing_data' => 'LOGIC');
			return $entry;
		}
		if (isset($entry['invariant'], $entry['proof_query_contract']) && is_array($entry['proof_query_contract'])) {
			$legacy = $entry['proof_query_contract'];
			return array(
			'name' => (string)$entry['invariant'],
			'type' => 'DB_STATE',
			'proof_inputs' => isset($legacy['unprovable_if_missing']) && is_array($legacy['unprovable_if_missing']) ? $legacy['unprovable_if_missing'] : array('primary_key'),
			'proof_query' => array(
			'source' => isset($legacy['source_table']) ? $legacy['source_table'] : '{table}',
			'filters' => isset($legacy['filter']) ? $legacy['filter'] : array(),
			'required_state' => !empty($legacy['must_exist']) ? 'ROW_EXISTS' : 'ROW_ABSENT'
			),
			'evaluation_mode' => 'STRICT',
			'failure_classification' => array('unprovable' => 'CRITICAL', 'false_state' => 'CRITICAL', 'missing_data' => 'LOGIC'),
			'applies_to' => isset($legacy['applies_to']) && is_array($legacy['applies_to']) ? $legacy['applies_to'] : array(),
			'operations' => isset($legacy['operations']) && is_array($legacy['operations']) ? $legacy['operations'] : array(),
			'expected_write_count' => isset($entry['expected_write_count']) ? (int)$entry['expected_write_count'] : null
			);
		}
		return null;
	}

	private function executeContract($domain, $intent, $contract, $writes, $lifecycle)
	{
		$applies = $this->contractApplies($contract, $lifecycle);
		if ($applies === false) {
			return $this->proven();
		}
		if ($applies === null) {
			return $this->unprovableForContract($contract, 'PROOF_CONDITION_UNPROVABLE', array('invariant' => $contract['name'], 'when' => isset($contract['when']) ? $contract['when'] : null));
		}
		$input_check = $this->validateProofInputs($contract, $lifecycle);
		if (is_array($input_check)) {
			return $input_check;
		}
		if ($contract['type'] === 'DB_STATE') {
			return $this->executeDbStateContract($domain, $intent, $contract, $writes);
		}
		if ($contract['type'] === 'DB_REFERENCE') {
			return $this->executeDbReferenceContract($domain, $intent, $contract, $lifecycle);
		}
		if ($contract['type'] === 'AUDIT_TRACE') {
			return $this->executeAuditTraceContract($contract, $writes);
		}
		if ($contract['type'] === 'CROSS_MODULE') {
			return $this->unprovableForContract($contract, 'CROSS_MODULE_UNSUPPORTED', array('invariant' => $contract['name']));
		}
		return $this->unprovableForContract($contract, 'PROOF_TYPE_UNSUPPORTED', array('invariant' => $contract['name'], 'type' => $contract['type']));
	}

	private function contractApplies($contract, $lifecycle)
	{
		if (!isset($contract['when']) || trim((string)$contract['when']) === '') {
			return true;
		}
		return $this->matchCondition((string)$contract['when'], $lifecycle);
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
		return null;
	}

	private function validateProofInputs($contract, $lifecycle)
	{
		foreach ($contract['proof_inputs'] as $input) {
			$input = (string)$input;
			if ($input === '' || $input === 'primary_key') {
				continue;
			}
			if (!$this->hasLifecycleInput($lifecycle, $input)) {
				return $this->unprovableForContract($contract, 'PROOF_INPUT_MISSING', array('invariant' => $contract['name'], 'input' => $input));
			}
		}
		return null;
	}

	private function hasLifecycleInput($lifecycle, $input)
	{
		if (!is_array($lifecycle)) {
			return false;
		}
		if (array_key_exists($input, $lifecycle) && $lifecycle[$input] !== null && $lifecycle[$input] !== '') {
			return true;
		}
		if (isset($lifecycle['context']) && is_array($lifecycle['context']) && array_key_exists($input, $lifecycle['context']) && $lifecycle['context'][$input] !== null && $lifecycle['context'][$input] !== '') {
			return true;
		}
		return false;
	}

	private function lifecycleInput($lifecycle, $input)
	{
		if (!is_array($lifecycle)) {
			return null;
		}
		if (array_key_exists($input, $lifecycle)) {
			return $lifecycle[$input];
		}
		if (isset($lifecycle['context']) && is_array($lifecycle['context']) && array_key_exists($input, $lifecycle['context'])) {
			return $lifecycle['context'][$input];
		}
		return null;
	}

	private function executeDbStateContract($domain, $intent, $contract, $writes)
	{
		$targetWrites = $this->filterWrites($writes, $contract);
		$targetWrites = $this->sortWritesDeterministically($targetWrites);
		if (empty($targetWrites)) {
			return $this->unprovableForContract($contract, 'NO_TARGET_WRITES', array('invariant' => $contract['name']));
		}
		if (isset($contract['expected_write_count']) && (int)$contract['expected_write_count'] > 0 && count($targetWrites) !== (int)$contract['expected_write_count']) {
			return $this->violationForContract($contract, 'WRITE_COUNT_MISMATCH', array('invariant' => $contract['name'], 'expected' => (int)$contract['expected_write_count'], 'observed' => count($targetWrites)));
		}
		$outcomes = array();
		foreach ($targetWrites as $write) {
			$outcomes[] = $this->evaluateDbWrite($domain, $intent, $contract, $write);
		}
		return $this->aggregate($outcomes);
	}

	private function executeDbReferenceContract($domain, $intent, $contract, $lifecycle)
	{
		if (!isset($this->CI->db) || !is_object($this->CI->db)) {
			return $this->unprovableForContract($contract, 'DB_NOT_AVAILABLE', array('invariant' => $contract['name']));
		}
		$query = isset($contract['proof_query']) && is_array($contract['proof_query']) ? $contract['proof_query'] : array();
		$source = isset($query['source']) ? (string)$query['source'] : '';
		if (!$this->isIdentifierSafe($source)) {
			return $this->unprovableForContract($contract, 'SOURCE_TABLE_INVALID', array('invariant' => $contract['name'], 'source' => $source));
		}
		$filters = isset($query['filters']) && is_array($query['filters']) ? $query['filters'] : array();
		if (empty($filters)) {
			return $this->unprovableForContract($contract, 'REFERENCE_FILTER_MISSING', array('invariant' => $contract['name']));
		}
		if (method_exists($this->CI->db, 'reset_query')) {
			$this->CI->db->reset_query();
		}
		$this->CI->db->from($source);
		foreach ($filters as $filter) {
			if (!is_array($filter) || !isset($filter['field'])) {
				if (method_exists($this->CI->db, 'reset_query')) {
					$this->CI->db->reset_query();
				}
				return $this->unprovableForContract($contract, 'REFERENCE_FILTER_INVALID', array('invariant' => $contract['name']));
			}
			$field = (string)$filter['field'];
			if (!$this->isIdentifierSafe($field)) {
				if (method_exists($this->CI->db, 'reset_query')) {
					$this->CI->db->reset_query();
				}
				return $this->unprovableForContract($contract, 'REFERENCE_FILTER_INVALID', array('invariant' => $contract['name'], 'field' => $field));
			}
			if (array_key_exists('equals', $filter)) {
				$this->CI->db->where($field, $filter['equals']);
				continue;
			}
			if (isset($filter['equals_input'])) {
				$input = (string)$filter['equals_input'];
				$value = $this->lifecycleInput($lifecycle, $input);
				if ($value === null || $value === '') {
					if (method_exists($this->CI->db, 'reset_query')) {
						$this->CI->db->reset_query();
					}
					return $this->unprovableForContract($contract, 'PROOF_INPUT_MISSING', array('invariant' => $contract['name'], 'input' => $input));
				}
				$this->CI->db->where($field, $value);
				continue;
			}
			if (method_exists($this->CI->db, 'reset_query')) {
				$this->CI->db->reset_query();
			}
			return $this->unprovableForContract($contract, 'REFERENCE_FILTER_INVALID', array('invariant' => $contract['name']));
		}
		$db_result = $this->CI->db->get();
		if (method_exists($this->CI->db, 'reset_query')) {
			$this->CI->db->reset_query();
		}
		if (!$db_result) {
			return $this->unprovableForContract($contract, 'QUERY_EXECUTION_FAILED', array('invariant' => $contract['name']));
		}
		$exists = ($db_result->num_rows() > 0);
		$required = isset($query['required_state']) ? (string)$query['required_state'] : 'ROW_EXISTS';
		if ($required === 'ROW_EXISTS' && !$exists) {
			return $this->violationForContract($contract, 'PROOF_FALSE_STATE', array('domain' => $domain, 'intent' => $intent, 'invariant' => $contract['name']));
		}
		if ($required === 'ROW_ABSENT' && $exists) {
			return $this->violationForContract($contract, 'PROOF_FALSE_STATE', array('domain' => $domain, 'intent' => $intent, 'invariant' => $contract['name']));
		}
		return $this->proven();
	}

	private function evaluateDbWrite($domain, $intent, $contract, $write)
	{
		if (in_array('primary_key', $contract['proof_inputs'], true)) {
			if (!array_key_exists('primary_key', $write) || $write['primary_key'] === null || $write['primary_key'] === '') {
				return $this->unprovableForContract($contract, 'PROOF_INPUT_MISSING', array('invariant' => $contract['name'], 'input' => 'primary_key', 'write' => $this->sanitizeWrite($write)));
			}
			if (!is_numeric($write['primary_key']) || (int)$write['primary_key'] <= 0) {
				return $this->unprovableForContract($contract, 'PROOF_INPUT_INVALID', array('invariant' => $contract['name'], 'input' => 'primary_key', 'write' => $this->sanitizeWrite($write)));
			}
		}
		return $this->runDbStateQuery($domain, $intent, $contract, $write);
	}

	private function executeAuditTraceContract($contract, $writes)
	{
		$query = $contract['proof_query'];
		$state = isset($query['required_state']) ? (string)$query['required_state'] : '';
		$tables = isset($query['tables']) && is_array($query['tables']) ? $query['tables'] : (isset($contract['applies_to']) && is_array($contract['applies_to']) ? $contract['applies_to'] : array());
		$operations = isset($query['operations']) && is_array($query['operations']) ? $query['operations'] : (isset($contract['operations']) && is_array($contract['operations']) ? $contract['operations'] : array());
		$matched = $this->filterWrites($writes, array('applies_to' => $tables, 'operations' => $operations));
		if ($state === 'EXACTLY_ONE_TARGET_TABLE') {
			$unique = array();
			foreach ($matched as $write) {
				if (isset($write['table'])) {
					$unique[(string)$write['table']] = true;
				}
			}
			$expected = isset($query['expected_count']) ? (int)$query['expected_count'] : 1;
			if (count($unique) !== $expected) {
				return $this->violationForContract($contract, 'AUDIT_TRACE_CARDINALITY_FAILED', array('invariant' => $contract['name'], 'expected' => $expected, 'observed' => count($unique)));
			}
			return $this->proven();
		}
		if ($state === 'NO_OPERATION') {
			if (!empty($matched)) {
				return $this->violationForContract($contract, 'AUDIT_TRACE_FORBIDDEN_OPERATION', array('invariant' => $contract['name'], 'observed' => $this->sanitizeWrites($matched)));
			}
			return $this->proven();
		}
		return $this->unprovableForContract($contract, 'AUDIT_TRACE_STATE_UNSUPPORTED', array('invariant' => $contract['name'], 'required_state' => $state));
	}

	private function runDbStateQuery($domain, $intent, $contract, $write)
	{
		if (!isset($this->CI->db) || !is_object($this->CI->db)) {
			return $this->unprovableForContract($contract, 'DB_NOT_AVAILABLE', array('invariant' => $contract['name']));
		}
		if (!isset($write['table'], $write['primary_key'])) {
			return $this->unprovableForContract($contract, 'WRITE_MISSING_TABLE_OR_PK', array('invariant' => $contract['name'], 'write' => $this->sanitizeWrite($write)));
		}
		$pk_col = $this->getPrimaryKeyColumn((string)$write['table']);
		if ($pk_col === null) {
			return $this->unprovableForContract($contract, 'PRIMARY_KEY_MAP_MISSING', array('invariant' => $contract['name'], 'table' => (string)$write['table']));
		}
		$query = $contract['proof_query'];
		$source = isset($query['source']) ? (string)$query['source'] : '';
		if ($source === '{table}') {
			$source = (string)$write['table'];
		}
		if (!$this->isIdentifierSafe($source)) {
			return $this->unprovableForContract($contract, 'SOURCE_TABLE_INVALID', array('invariant' => $contract['name'], 'source' => $source));
		}
		$filters = isset($query['filters']) ? $query['filters'] : array();
		$db_result = $this->buildAndRunExistsQuery($source, $pk_col, (int)$write['primary_key'], $filters);
		if ($db_result === null) {
			return $this->unprovableForContract($contract, 'FILTER_CONTRACT_INVALID', array('invariant' => $contract['name']));
		}
		if ($db_result === false) {
			return $this->unprovableForContract($contract, 'QUERY_EXECUTION_FAILED', array('invariant' => $contract['name']));
		}
		$exists = ($db_result->num_rows() > 0);
		$required = isset($query['required_state']) ? (string)$query['required_state'] : 'ROW_EXISTS';
		if ($required === 'ROW_EXISTS' && !$exists) {
			return $this->violationForContract($contract, 'PROOF_FALSE_STATE', array('domain' => $domain, 'intent' => $intent, 'invariant' => $contract['name'], 'write' => $this->sanitizeWrite($write)));
		}
		if ($required === 'ROW_ABSENT' && $exists) {
			return $this->violationForContract($contract, 'PROOF_FALSE_STATE', array('domain' => $domain, 'intent' => $intent, 'invariant' => $contract['name'], 'write' => $this->sanitizeWrite($write)));
		}
		return $this->proven();
	}

	private function buildAndRunExistsQuery($table, $pk_col, $pk, $filters = array())
	{
		if (method_exists($this->CI->db, 'reset_query')) {
			$this->CI->db->reset_query();
		}
		$this->CI->db->from((string)$table);
		$this->CI->db->where((string)$pk_col, $pk);
		if (!$this->applyFilters($filters)) {
			if (method_exists($this->CI->db, 'reset_query')) {
				$this->CI->db->reset_query();
			}
			return null;
		}
		$query = $this->CI->db->get();
		if (method_exists($this->CI->db, 'reset_query')) {
			$this->CI->db->reset_query();
		}
		return $query ? $query : false;
	}

	private function applyFilters($filters)
	{
		if ($filters === null || $filters === array()) {
			return true;
		}
		if (!is_array($filters)) {
			return false;
		}
		foreach ($filters as $filter) {
			if (!is_array($filter) || !isset($filter['field']) || !array_key_exists('equals', $filter)) {
				return false;
			}
			$field = (string)$filter['field'];
			if (!$this->isIdentifierSafe($field)) {
				return false;
			}
			$this->CI->db->where($field, $filter['equals']);
		}
		return true;
	}

	private function filterWrites($writes, $contract)
	{
		if (!is_array($writes)) {
			return array();
		}
		$tables = isset($contract['applies_to']) && is_array($contract['applies_to']) ? array_map('strtolower', $contract['applies_to']) : array();
		$operations = isset($contract['operations']) && is_array($contract['operations']) ? array_map('strtoupper', $contract['operations']) : array();
		$out = array();
		foreach ($writes as $write) {
			if (!is_array($write) || !isset($write['table'], $write['operation'])) {
				continue;
			}
			$table = strtolower((string)$write['table']);
			$operation = strtoupper((string)$write['operation']);
			if (!empty($tables) && !in_array($table, $tables, true)) {
				continue;
			}
			if (!empty($operations) && !in_array($operation, $operations, true)) {
				continue;
			}
			$out[] = $write;
		}
		return $out;
	}

	private function normalizeWrites($writes)
	{
		if (!is_array($writes)) {
			return array();
		}
		$out = array();
		foreach ($writes as $write) {
			if (!is_array($write)) {
				continue;
			}
			if (isset($write['table'])) {
				$write['table'] = strtolower(trim((string)$write['table']));
			}
			if (isset($write['operation'])) {
				$write['operation'] = strtoupper(trim((string)$write['operation']));
			}
			$out[] = $write;
		}
		return $out;
	}

	private function sortWritesDeterministically($writes)
	{
		if (!is_array($writes)) {
			return array();
		}
		usort($writes, function ($a, $b) {
			$ak = $this->writeSortKey($a);
			$bk = $this->writeSortKey($b);
			return strcmp($ak, $bk);
		});
		return $writes;
	}

	private function writeSortKey($write)
	{
		if (!is_array($write)) {
			return '';
		}
		return (isset($write['table']) ? (string)$write['table'] : '') . ':' . (isset($write['operation']) ? (string)$write['operation'] : '') . ':' . (array_key_exists('primary_key', $write) ? (string)$write['primary_key'] : '');
	}

	private function getPrimaryKeyColumn($table)
	{
		if (!isset($this->CI->config)) {
			return null;
		}
		$this->CI->config->load('shadow_governance_expectedset', true);
		$pk_map = $this->CI->config->item('shadow_governance_primary_key_map', 'shadow_governance_expectedset');
		if (!is_array($pk_map) || !isset($pk_map[$table]) || (string)$pk_map[$table] === '') {
			return null;
		}
		return (string)$pk_map[$table];
	}

	private function isIdentifierSafe($identifier)
	{
		return is_string($identifier) && $identifier !== '' && (bool)preg_match('/^[a-zA-Z0-9_]+$/', $identifier);
	}

	private function sanitizeWrite($write)
	{
		if (!is_array($write)) {
			return null;
		}
		return array(
		'table' => isset($write['table']) ? $write['table'] : null,
		'operation' => isset($write['operation']) ? $write['operation'] : null,
		'primary_key' => array_key_exists('primary_key', $write) ? $write['primary_key'] : null,
		'pk_status' => isset($write['pk_status']) ? $write['pk_status'] : null,
		'reason' => isset($write['reason']) ? $write['reason'] : null
		);
	}

	private function sanitizeWrites($writes)
	{
		$out = array();
		foreach ($writes as $write) {
			$out[] = $this->sanitizeWrite($write);
		}
		return $out;
	}

	private function attachProofSet($result, $binding, $proofset, $results)
	{
		if (!is_array($result)) {
			$result = $this->unprovable('INVALID_PROOF_RESULT');
		}
		$result['proof_binding'] = $binding;
		$result['proofset'] = $proofset;
		$result['proof_results'] = $results;
		if (!isset($result['drift_class'])) {
			$result['drift_class'] = $this->defaultDriftClass($result);
		}
		return $result;
	}

	private function aggregate($results)
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
		return ShadowGovernanceSemantics::worstResult($results, 'INVALID_RESULT_ITEM');
	}

	private function proven()
	{
		return array('status' => 'PASS', 'drift_class' => 'NONE');
	}

	private function violationForContract($contract, $code, $data = array())
	{
		$result = $this->violation($code, $data);
		$result['drift_class'] = isset($contract['failure_classification']['false_state']) ? (string)$contract['failure_classification']['false_state'] : 'CRITICAL';
		return $result;
	}

	private function unprovableForContract($contract, $code, $data = array())
	{
		$result = $this->unprovable($code, $data);
		$key = ($code === 'PROOF_INPUT_MISSING' || $code === 'PROOF_INPUT_INVALID') ? 'missing_data' : 'unprovable';
		$result['drift_class'] = isset($contract['failure_classification'][$key]) ? (string)$contract['failure_classification'][$key] : 'CRITICAL';
		return $result;
	}

	private function violation($code, $data = array())
	{
		return array('status' => 'VIOLATION', 'code' => $code, 'data' => $data);
	}

	private function unprovable($code, $data = array())
	{
		return array('status' => 'UNPROVABLE', 'code' => $code, 'data' => $data, 'drift_class' => 'CRITICAL');
	}

	private function defaultDriftClass($result)
	{
		$status = isset($result['status']) ? (string)$result['status'] : '';
		if ($status === 'PASS') {
			return 'NONE';
		}
		if ($status === 'VIOLATION' || $status === 'UNPROVABLE') {
			return 'CRITICAL';
		}
		return 'LOGIC';
	}
}
