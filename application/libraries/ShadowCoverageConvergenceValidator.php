<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class ShadowCoverageConvergenceValidator
{
	public function validate()
	{
		$CI =& get_instance();
		if (!isset($CI->config)) {
			return $this->fail('CONFIG_UNAVAILABLE');
		}

		$CI->config->load('shadow_governance_registry', true);
		$CI->config->load('shadow_governance_expectedset', true);
		$CI->config->load('shadow_governance_proof_contracts', true);

		$registryVersion = (string)$CI->config->item('shadow_governance_registry_version', 'shadow_governance_registry');
		$expectedSetVersion = (string)$CI->config->item('shadow_governance_expectedset_version', 'shadow_governance_expectedset');
		$proofVersion = (string)$CI->config->item('shadow_governance_proof_contracts_version', 'shadow_governance_proof_contracts');
		$registry = $CI->config->item('shadow_governance_registry_' . strtolower($registryVersion), 'shadow_governance_registry');
		$contextKeys = $CI->config->item('shadow_governance_context_keys', 'shadow_governance_registry');
		$expectedSet = $CI->config->item('shadow_governance_expectedset_' . strtolower($expectedSetVersion), 'shadow_governance_expectedset');
		$primaryKeyMap = $CI->config->item('shadow_governance_primary_key_map', 'shadow_governance_expectedset');
		$proofContracts = $CI->config->item('shadow_governance_proof_contracts_' . strtolower($proofVersion), 'shadow_governance_proof_contracts');

		if (!is_array($registry)) {
			return $this->fail('REGISTRY_MISSING');
		}
		if (!is_array($expectedSet)) {
			return $this->fail('EXPECTEDSET_MISSING');
		}
		if (!is_array($proofContracts)) {
			return $this->fail('PROOF_CONTRACTS_MISSING');
		}
		if (!is_array($contextKeys)) {
			$contextKeys = array();
		}
		if (!is_array($primaryKeyMap)) {
			$primaryKeyMap = array();
		}

		$domains = array();
		foreach ($registry as $domain => $definition) {
			$domains[$domain] = $this->validateDomain($domain, $definition, $expectedSet, $proofContracts, $primaryKeyMap, $contextKeys);
		}

		$summary = $this->summarize($domains, $registry, $expectedSet, $proofContracts);
		$status = ($summary['critical_gap_count'] > 0) ? 'GAP' : 'PASS';
		return array(
			'status' => $status,
			'mode' => 'READ_ONLY_COVERAGE_CONVERGENCE',
			'versions' => array(
				'registry' => $registryVersion,
				'expectedset' => $expectedSetVersion,
				'proof_contracts' => $proofVersion
			),
			'summary' => $summary,
			'domains' => $domains
		);
	}

	private function validateDomain($domain, $definition, $expectedSet, $proofContracts, $primaryKeyMap, $contextKeys)
	{
		$domain = (string)$domain;
		$ownedTables = (is_array($definition) && isset($definition['tables_owned']) && is_array($definition['tables_owned'])) ? $definition['tables_owned'] : array();
		$registryInvariants = (is_array($definition) && isset($definition['invariants']) && is_array($definition['invariants'])) ? $definition['invariants'] : array();
		$expectedDomains = $this->matchingExpectedDomains($domain, $ownedTables, $expectedSet);
		$missingPkTables = array();
		foreach ($ownedTables as $table) {
			$table = (string)$table;
			if ($table !== '' && !isset($primaryKeyMap[$table])) {
				$missingPkTables[] = $table;
			}
		}

		$intents = array();
		foreach (array('CREATE', 'DELETE') as $intent) {
			$intents[$intent] = $this->validateIntentCoverage($intent, $expectedDomains, $expectedSet, $proofContracts, $contextKeys);
		}
		$registryInvariantCoverage = $this->validateRegistryInvariantCoverage($expectedDomains, $expectedSet, $proofContracts, $registryInvariants);

		$gaps = array();
		if (empty($expectedDomains)) {
			$gaps[] = 'EXPECTEDSET_DOMAIN_MISSING';
		}
		if (!empty($missingPkTables)) {
			$gaps[] = 'PRIMARY_KEY_MAP_INCOMPLETE';
		}
		foreach ($intents as $intent => $result) {
			if (isset($result['gaps']) && is_array($result['gaps'])) {
				foreach ($result['gaps'] as $gap) {
					$gaps[] = $intent . ':' . $gap;
				}
			}
		}
		foreach ($registryInvariantCoverage as $invariant => $coverage) {
			if (!$coverage['expectedset']) {
				$gaps[] = 'REGISTRY_INVARIANT_NOT_IN_EXPECTEDSET:' . $invariant;
			}
			if (!$coverage['proof']) {
				$gaps[] = 'REGISTRY_INVARIANT_NOT_IN_PROOFSET:' . $invariant;
			}
		}

		return array(
			'status' => empty($gaps) ? 'PASS' : 'GAP',
			'owned_tables' => array_values($ownedTables),
			'registry_invariants' => array_values($registryInvariants),
			'registry_invariant_coverage' => $registryInvariantCoverage,
			'expectedset_domains' => array_values($expectedDomains),
			'missing_primary_key_tables' => $missingPkTables,
			'intents' => $intents,
			'gaps' => $gaps
		);
	}

	private function matchingExpectedDomains($domain, $ownedTables, $expectedSet)
	{
		$out = array();
		$hasDirect = false;
		if (isset($expectedSet[$domain]) && is_array($expectedSet[$domain])) {
			$out[] = $domain;
			$hasDirect = true;
		}
		foreach ($expectedSet as $expectedDomain => $contract) {
			if ($expectedDomain === $domain || !is_array($contract)) {
				continue;
			}
			if ($hasDirect && !$this->isCompositeDomainMatch($domain, $expectedDomain)) {
				continue;
			}
			$tables = $this->expectedSetTables($contract);
			if ($this->intersects($ownedTables, $tables)) {
				$out[] = (string)$expectedDomain;
			}
		}
		return array_values(array_unique($out));
	}

	private function isCompositeDomainMatch($domain, $expectedDomain)
	{
		$domain = strtoupper(trim((string)$domain));
		$expectedDomain = strtoupper(trim((string)$expectedDomain));
		if ($domain === '' || $expectedDomain === '') {
			return false;
		}
		return (strpos($expectedDomain, $domain) !== false || strpos($domain, $expectedDomain) !== false);
	}

	private function validateIntentCoverage($intent, $expectedDomains, $expectedSet, $proofContracts, $contextKeys)
	{
		$expectedInvariantNames = array();
		$proofInvariantNames = array();
		$proofInputs = array();
		$gaps = array();
		$hasExpectedIntent = false;
		$hasProofIntent = false;

		foreach ($expectedDomains as $expectedDomain) {
			if (isset($expectedSet[$expectedDomain][$intent]) && is_array($expectedSet[$expectedDomain][$intent])) {
				$hasExpectedIntent = true;
				$expectedInvariantNames = array_merge($expectedInvariantNames, $this->invariantNames($expectedSet[$expectedDomain][$intent]));
			}
			if (isset($proofContracts[$expectedDomain][$intent]) && is_array($proofContracts[$expectedDomain][$intent])) {
				$hasProofIntent = true;
				foreach ($proofContracts[$expectedDomain][$intent] as $contract) {
					if (is_array($contract) && isset($contract['name'])) {
						$proofInvariantNames[] = (string)$contract['name'];
					}
					if (is_array($contract) && isset($contract['proof_inputs']) && is_array($contract['proof_inputs'])) {
						foreach ($contract['proof_inputs'] as $input) {
							$proofInputs[] = (string)$input;
						}
					}
				}
			}
		}

		$expectedInvariantNames = array_values(array_unique($expectedInvariantNames));
		$proofInvariantNames = array_values(array_unique($proofInvariantNames));
		$proofInputs = array_values(array_unique($proofInputs));
		if (!$hasExpectedIntent) {
			$gaps[] = 'EXPECTEDSET_INTENT_MISSING';
		}
		if (!$hasProofIntent) {
			$gaps[] = 'PROOF_INTENT_MISSING';
		}

		foreach ($expectedInvariantNames as $invariant) {
			if (!in_array($invariant, $proofInvariantNames, true)) {
				$gaps[] = 'EXPECTEDSET_INVARIANT_NOT_IN_PROOFSET:' . $invariant;
			}
		}

		$missingInputs = array();
		foreach ($proofInputs as $input) {
			if ($input === '' || $input === 'primary_key') {
				continue;
			}
			if (!in_array($input, $contextKeys, true)) {
				$missingInputs[] = $input;
			}
		}
		if (!empty($missingInputs)) {
			$gaps[] = 'PROOF_INPUT_CONTEXT_KEY_MISSING';
		}

		return array(
			'status' => empty($gaps) ? 'PASS' : 'GAP',
			'expectedset_present' => $hasExpectedIntent,
			'proof_present' => $hasProofIntent,
			'expectedset_invariants' => $expectedInvariantNames,
			'proof_invariants' => $proofInvariantNames,
			'proof_inputs' => $proofInputs,
			'missing_context_inputs' => $missingInputs,
			'gaps' => array_values(array_unique($gaps))
		);
	}

	private function validateRegistryInvariantCoverage($expectedDomains, $expectedSet, $proofContracts, $registryInvariants)
	{
		$expectedInvariantNames = array();
		$proofInvariantNames = array();
		foreach ($expectedDomains as $expectedDomain) {
			foreach (array('CREATE', 'DELETE') as $intent) {
				if (isset($expectedSet[$expectedDomain][$intent]) && is_array($expectedSet[$expectedDomain][$intent])) {
					$expectedInvariantNames = array_merge($expectedInvariantNames, $this->invariantNames($expectedSet[$expectedDomain][$intent]));
				}
				if (isset($proofContracts[$expectedDomain][$intent]) && is_array($proofContracts[$expectedDomain][$intent])) {
					foreach ($proofContracts[$expectedDomain][$intent] as $contract) {
						if (is_array($contract) && isset($contract['name'])) {
							$proofInvariantNames[] = (string)$contract['name'];
						}
					}
				}
			}
		}
		$expectedInvariantNames = array_values(array_unique($expectedInvariantNames));
		$proofInvariantNames = array_values(array_unique($proofInvariantNames));
		$out = array();
		foreach ($registryInvariants as $invariant) {
			$invariant = (string)$invariant;
			$out[$invariant] = array(
				'expectedset' => in_array($invariant, $expectedInvariantNames, true),
				'proof' => in_array($invariant, $proofInvariantNames, true)
			);
		}
		return $out;
	}

	private function expectedSetTables($contract)
	{
		$tables = array();
		foreach (array('CREATE', 'DELETE') as $intent) {
			if (!isset($contract[$intent]) || !is_array($contract[$intent])) {
				continue;
			}
			foreach (array('required_writes', 'forbidden_writes', 'allowed_writes', 'allowed_side_effects') as $section) {
				if (!isset($contract[$intent][$section]) || !is_array($contract[$intent][$section])) {
					continue;
				}
				foreach ($contract[$intent][$section] as $rule) {
					if (is_array($rule) && isset($rule['table'])) {
						$tables[] = (string)$rule['table'];
					}
				}
			}
		}
		return array_values(array_unique($tables));
	}

	private function invariantNames($intentContract)
	{
		$out = array();
		if (!isset($intentContract['invariants']) || !is_array($intentContract['invariants'])) {
			return $out;
		}
		foreach ($intentContract['invariants'] as $invariant) {
			if (is_array($invariant) && isset($invariant['name'])) {
				$out[] = (string)$invariant['name'];
			}
		}
		return array_values(array_unique($out));
	}

	private function intersects($a, $b)
	{
		foreach ($a as $item) {
			if (in_array($item, $b, true)) {
				return true;
			}
		}
		return false;
	}

	private function summarize($domains, $registry, $expectedSet, $proofContracts)
	{
		$domainCount = count($registry);
		$covered = 0;
		$gapCount = 0;
		$gapTypes = array();
		foreach ($domains as $result) {
			if (is_array($result) && isset($result['status']) && $result['status'] === 'PASS') {
				$covered++;
			} else {
				$gapCount++;
			}
			if (isset($result['gaps']) && is_array($result['gaps'])) {
				foreach ($result['gaps'] as $gap) {
					$key = preg_replace('/:.+$/', '', (string)$gap);
					if (!isset($gapTypes[$key])) {
						$gapTypes[$key] = 0;
					}
					$gapTypes[$key]++;
				}
			}
		}
		arsort($gapTypes);
		return array(
			'registry_domain_count' => $domainCount,
			'expectedset_domain_count' => count($expectedSet),
			'proof_domain_count' => count($proofContracts),
			'fully_covered_registry_domains' => $covered,
			'critical_gap_count' => $gapCount,
			'registry_domain_coverage_percent' => $this->percent($covered, $domainCount),
			'gap_types' => $gapTypes
		);
	}

	private function percent($value, $total)
	{
		$value = (int)$value;
		$total = (int)$total;
		if ($total <= 0) {
			return 0;
		}
		return round(($value / $total) * 100, 2);
	}

	private function fail($code, $data = array())
	{
		return array('status' => 'FAIL', 'code' => $code, 'data' => $data);
	}
}
