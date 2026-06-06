<?php
if (!defined('BASEPATH')) exit('No direct script access allowed');

if (!function_exists('shadow_governance_registry_v0')) {
	function shadow_governance_registry_v0()
	{
		$CI =& get_instance();
		if (isset($CI->config)) {
			$CI->config->load('shadow_governance_registry', true);
			$reg = $CI->config->item('shadow_governance_registry_v0', 'shadow_governance_registry');
			if (is_array($reg)) {
				return $reg;
			}
		}
		return array();
	}
}

if (!function_exists('shadow_governance_registry_version')) {
	function shadow_governance_registry_version()
	{
		$CI =& get_instance();
		if (isset($CI->config)) {
			$CI->config->load('shadow_governance_registry', true);
			$v = $CI->config->item('shadow_governance_registry_version', 'shadow_governance_registry');
			if (is_string($v) && $v !== '') {
				return $v;
			}
		}
		return 'V0';
	}
}

if (!function_exists('shadow_governance_resolve_intent')) {
	function shadow_governance_resolve_intent($controller, $method)
	{
		$m = strtolower((string)$method);
		$ctl = strtolower((string)$controller);

		// Deterministic: consult expectedset intent rules when available.
		$CI =& get_instance();
		if (isset($CI->config)) {
			$CI->config->load('shadow_governance_expectedset', true);
			$expectedset = $CI->config->item('shadow_governance_expectedset_v0', 'shadow_governance_expectedset');
			if (is_array($expectedset)) {
				foreach ($expectedset as $domain => $cfg) {
					if (!is_array($cfg) || !isset($cfg['intent_rules']) || !is_array($cfg['intent_rules'])) {
						continue;
					}
					$rules = $cfg['intent_rules'];
					$create_rx = isset($rules['create_method_regex']) ? (string)$rules['create_method_regex'] : '';
					$delete_rx = isset($rules['delete_method_regex']) ? (string)$rules['delete_method_regex'] : '';
					if ($delete_rx !== '' && @preg_match('/' . $delete_rx . '/i', $m)) {
						return 'DELETE';
					}
					if ($create_rx !== '' && @preg_match('/' . $create_rx . '/i', $m)) {
						return 'CREATE';
					}
				}
			}
		}

		$CI =& get_instance();
		if (isset($CI->config)) {
			$CI->config->load('shadow_governance_endpoint_expectations', true);
			$rules = $CI->config->item('shadow_governance_endpoint_expectations_v0', 'shadow_governance_endpoint_expectations');
			if (is_array($rules)) {
				foreach ($rules as $rule) {
					if (!is_array($rule)) {
						continue;
					}
					$controller = isset($rule['controller']) ? strtolower(trim((string)$rule['controller'])) : '';
					$method_regex = isset($rule['method_regex']) ? (string)$rule['method_regex'] : '';
					$rule_intent = isset($rule['intent']) ? strtoupper(trim((string)$rule['intent'])) : '';
					if ($controller === '' || $method_regex === '' || $rule_intent === '') {
						continue;
					}
					if ($controller === $ctl && @preg_match('/' . $method_regex . '/i', $m)) {
						return in_array($rule_intent, array('CREATE', 'DELETE'), true) ? $rule_intent : 'UNKNOWN';
					}
				}
			}
		}

		// Strict fallback heuristics.
		if (strpos($m, 'delete') !== false) {
			return 'DELETE';
		}
		if (strpos($m, 'save') !== false) {
			return 'CREATE';
		}
		if ($ctl === 'nurse_module' && (strpos($m, 'intake') !== false || strpos($m, 'output') !== false)) {
			return 'CREATE';
		}
		return 'UNKNOWN';
	}
}

if (!function_exists('shadow_governance_resolve_endpoint_write_expectation')) {
	function shadow_governance_resolve_endpoint_write_expectation($controller, $method)
	{
		$ctl = strtolower(trim((string)$controller));
		$mtd = strtolower(trim((string)$method));
		$CI =& get_instance();
		if (isset($CI->config)) {
			$CI->config->load('shadow_governance_endpoint_expectations', true);
			$rules = $CI->config->item('shadow_governance_endpoint_expectations_v0', 'shadow_governance_endpoint_expectations');
			if (is_array($rules)) {
				foreach ($rules as $rule) {
					if (!is_array($rule)) {
						continue;
					}
					$controller = isset($rule['controller']) ? strtolower(trim((string)$rule['controller'])) : '';
					$method_regex = isset($rule['method_regex']) ? (string)$rule['method_regex'] : '';
					$expectation = isset($rule['endpoint_write_expectation']) ? strtoupper(trim((string)$rule['endpoint_write_expectation'])) : '';
					if ($controller === '' || $method_regex === '' || $expectation === '') {
						continue;
					}
					if ($controller === $ctl && @preg_match('/' . $method_regex . '/i', $mtd)) {
						return $expectation;
					}
				}
			}
		}
		return 'UNKNOWN';
	}
}

if (!function_exists('shadow_governance_resolve_expectedset_binding')) {
    function shadow_governance_resolve_expectedset_binding($domain, $intent, $controller, $method)
    {
        $out = array(
            'expectedset_loaded' => false,
            'expectedset_version' => null,
            'expectedset_contract_bound' => false,
            'expectedset_binding_status' => 'NOT_BOUND',
            'expectedset_contract_domain' => null,
            'expectedset_contract_intent' => null,
            'expectedset_contract_key' => null,
            'expectedset_invariants' => array()
        );
        $intent = strtoupper(trim((string)$intent));
        if (!in_array($intent, array('CREATE', 'DELETE'), true)) {
            $out['expectedset_binding_status'] = 'INTENT_NOT_BINDABLE';
            return $out;
        }
        $CI =& get_instance();
        if (!isset($CI->config)) {
            $out['expectedset_binding_status'] = 'CONFIG_UNAVAILABLE';
            return $out;
        }
        $CI->config->load('shadow_governance_expectedset', true);
        $out['expectedset_version'] = $CI->config->item('shadow_governance_expectedset_version', 'shadow_governance_expectedset');
        $expectedset = $CI->config->item('shadow_governance_expectedset_v0', 'shadow_governance_expectedset');
        if (!is_array($expectedset)) {
            $out['expectedset_binding_status'] = 'EXPECTEDSET_UNAVAILABLE';
            return $out;
        }
        $out['expectedset_loaded'] = true;
        $method = strtolower(trim((string)$method));
        foreach ($expectedset as $candidate_domain => $cfg) {
            if (!is_array($cfg) || !isset($cfg[$intent]) || !is_array($cfg[$intent])) {
                continue;
            }
            $rules = (isset($cfg['intent_rules']) && is_array($cfg['intent_rules'])) ? $cfg['intent_rules'] : array();
            $regex_key = ($intent === 'DELETE') ? 'delete_method_regex' : 'create_method_regex';
            $rx = isset($rules[$regex_key]) ? (string)$rules[$regex_key] : '';
            if ($rx !== '' && @preg_match('/' . $rx . '/i', $method)) {
                $out['expectedset_contract_bound'] = true;
                $out['expectedset_binding_status'] = 'BOUND';
                $out['expectedset_contract_domain'] = (string)$candidate_domain;
                $out['expectedset_contract_intent'] = $intent;
                $out['expectedset_contract_key'] = (string)$candidate_domain . '.' . $intent;
                if (isset($cfg[$intent]['invariants']) && is_array($cfg[$intent]['invariants'])) {
                    foreach ($cfg[$intent]['invariants'] as $inv) {
                        if (is_array($inv) && isset($inv['name'])) {
                            $out['expectedset_invariants'][] = (string)$inv['name'];
                        }
                    }
                }
                return $out;
            }
        }
        if (isset($expectedset[$domain]) && is_array($expectedset[$domain]) && isset($expectedset[$domain][$intent]) && is_array($expectedset[$domain][$intent])) {
            $out['expectedset_contract_bound'] = true;
            $out['expectedset_binding_status'] = 'BOUND';
            $out['expectedset_contract_domain'] = (string)$domain;
            $out['expectedset_contract_intent'] = $intent;
            $out['expectedset_contract_key'] = (string)$domain . '.' . $intent;
            if (isset($expectedset[$domain][$intent]['invariants']) && is_array($expectedset[$domain][$intent]['invariants'])) {
                foreach ($expectedset[$domain][$intent]['invariants'] as $inv) {
                    if (is_array($inv) && isset($inv['name'])) {
                        $out['expectedset_invariants'][] = (string)$inv['name'];
                    }
                }
            }
            return $out;
        }
        $out['expectedset_binding_status'] = 'CONTRACT_NOT_FOUND';
        return $out;
    }
}

if (!function_exists('shadow_governance_capture_context')) {
	function shadow_governance_capture_context()
	{
		$CI =& get_instance();
		if (!isset($CI->config) || !isset($CI->input)) {
			return array();
		}
		$CI->config->load('shadow_governance_registry', true);
		$keys = $CI->config->item('shadow_governance_context_keys', 'shadow_governance_registry');
		if (!is_array($keys) || count($keys) === 0) {
			return array();
		}
		$out = array();
		foreach ($keys as $k) {
			$key = (string)$k;
			if ($key === '') {
				continue;
			}
			$v = $CI->input->post($key, true);
			if ($v === null || $v === '') {
				$v = $CI->input->get($key, true);
			}
			if ($v === null || $v === '') {
				continue;
			}
			if (is_array($v)) {
				continue;
			}
			$out[$key] = (string)$v;
		}
		return $out;
	}
}

if (!function_exists('shadow_governance_registry_derive_domain')) {
	function shadow_governance_registry_derive_domain($controller, $method, $tables_detected)
	{
		$registry = shadow_governance_registry_v0();
		$ctl = strtolower(trim((string)$controller));
		$mtd = strtolower(trim((string)$method));
		$tables = is_array($tables_detected) ? $tables_detected : array();

		$candidates = array();
		foreach ($registry as $key => $def) {
			$controllers = isset($def['controllers']) && is_array($def['controllers']) ? $def['controllers'] : array();
			$method_patterns = isset($def['method_patterns']) && is_array($def['method_patterns']) ? $def['method_patterns'] : array();
			$tables_owned = isset($def['tables_owned']) && is_array($def['tables_owned']) ? $def['tables_owned'] : array();

			$controller_ok = in_array($ctl, array_map('strtolower', $controllers), true);
			if (!$controller_ok) {
				continue;
			}

			$method_ok = false;
			foreach ($method_patterns as $pat) {
				$pat = (string)$pat;
				if ($pat === '') {
					continue;
				}
				if (@preg_match('/' . $pat . '/i', $mtd)) {
					$method_ok = true;
					break;
				}
			}
			if (!$method_ok) {
				continue;
			}

			// Prefer candidates that actually touch at least one owned table.
			$table_ok = false;
			foreach ($tables_owned as $t) {
				if (in_array($t, $tables, true)) {
					$table_ok = true;
					break;
				}
			}
			if (!$table_ok) {
				continue;
			}

			$candidates[] = (string)$key;
		}

		$registry_domain = 'UNKNOWN';
		if (count($candidates) === 1) {
			$registry_domain = $candidates[0];
		} elseif (count($candidates) > 1) {
			$registry_domain = 'AMBIGUOUS';
		}

		return array(
			'registry_domain' => $registry_domain,
			'candidates' => $candidates
		);
	}
}

if (!function_exists('shadow_governance_registry_validate')) {
	function shadow_governance_registry_validate($domain_detected, $controller, $method, $tables_detected)
	{
		$registry = shadow_governance_registry_v0();
		$derived = shadow_governance_registry_derive_domain($controller, $method, $tables_detected);
		$registry_domain = (string)$derived['registry_domain'];
		$candidates = isset($derived['candidates']) ? $derived['candidates'] : array();

		$drift_flags = array();
		$det = strtoupper(trim((string)$domain_detected));
		$ctl = strtolower(trim((string)$controller));
		$mtd = strtolower(trim((string)$method));
		$tables = is_array($tables_detected) ? $tables_detected : array();

		$meta = array(
			'transaction_class' => null,
			'enforcement_tier' => null,
			'audit_required' => null,
			'compensation_required' => null
		);

		if ($registry_domain === 'UNKNOWN') {
			$drift_flags[] = 'REGISTRY_NO_MATCH';
		} elseif ($registry_domain === 'AMBIGUOUS') {
			$drift_flags[] = 'REGISTRY_AMBIGUOUS';
		} else {
			if (!isset($registry[$registry_domain])) {
				$drift_flags[] = 'REGISTRY_DOMAIN_MISSING';
			} else {
				$def = $registry[$registry_domain];
				$meta['transaction_class'] = isset($def['transaction_class']) ? (string)$def['transaction_class'] : null;
				$meta['enforcement_tier'] = isset($def['enforcement_tier']) ? (int)$def['enforcement_tier'] : null;
				$meta['audit_required'] = isset($def['audit_required']) ? (bool)$def['audit_required'] : null;
				$meta['compensation_required'] = isset($def['compensation_required']) ? (bool)$def['compensation_required'] : null;

				$controllers = isset($def['controllers']) && is_array($def['controllers']) ? $def['controllers'] : array();
				if (!in_array($ctl, array_map('strtolower', $controllers), true)) {
					$drift_flags[] = 'CONTROLLER_NOT_ALLOWED';
				}

				$method_patterns = isset($def['method_patterns']) && is_array($def['method_patterns']) ? $def['method_patterns'] : array();
				$method_ok = false;
				foreach ($method_patterns as $pat) {
					$pat = (string)$pat;
					if ($pat === '') {
						continue;
					}
					if (@preg_match('/' . $pat . '/i', $mtd)) {
						$method_ok = true;
						break;
					}
				}
				if (!$method_ok) {
					$drift_flags[] = 'METHOD_PATTERN_MISMATCH';
				}

				$tables_owned = isset($def['tables_owned']) && is_array($def['tables_owned']) ? $def['tables_owned'] : array();
				$owned_hit = false;
				foreach ($tables_owned as $t) {
					if (in_array($t, $tables, true)) {
						$owned_hit = true;
						break;
					}
				}
				if (!$owned_hit) {
					$drift_flags[] = 'NO_OWNED_TABLE_TOUCHED';
				}

				foreach ($tables as $t) {
					if (!in_array($t, $tables_owned, true)) {
						$drift_flags[] = 'TABLE_NOT_OWNED:' . (string)$t;
					}
				}
			}
		}

		if ($registry_domain !== $det) {
			$drift_flags[] = 'DOMAIN_MISMATCH';
		}

		$match_status = 'DRIFT';
		if ($registry_domain === $det && count($drift_flags) === 0) {
			$match_status = 'MATCH';
		}

		return array(
			'registry_domain' => $registry_domain,
			'match_status' => $match_status,
			'drift_flags' => array_values(array_unique($drift_flags)),
			'registry_candidates' => $candidates,
			'meta' => $meta
		);
	}
}

if (!function_exists('shadow_governance_bootstrap')) {
	function shadow_governance_bootstrap()
	{
		$CI =& get_instance();
		if (!isset($CI->config)) {
			return;
		}

		$enabled = (bool)$CI->config->item('SHADOW_GOVERNANCE_ENABLED');
		if (!$enabled) {
			return;
		}

		// Create a request-scoped lifecycle ID.
		if (!function_exists('shadow_governance_lifecycle_id')) {
			function shadow_governance_lifecycle_id()
			{
				static $id = null;
				if ($id !== null) {
					return $id;
				}
				$id = 'lc_' . date('YmdHis') . '_' . substr(md5(uniqid('', true)), 0, 10);
				return $id;
			}
		}

		// Capture controller + method for shadow logs.
		$controller = isset($CI->router) ? (string)$CI->router->fetch_class() : '';
		$method = isset($CI->router) ? (string)$CI->router->fetch_method() : '';
		$intent = shadow_governance_resolve_intent($controller, $method);
		$endpoint_write_expectation = shadow_governance_resolve_endpoint_write_expectation($controller, $method);
		$registry_version = (string)$CI->config->item('SHADOW_GOVERNANCE_REGISTRY_VERSION');
		$registry_contract_version = shadow_governance_registry_version();

		log_message(
			'debug',
			'[SHADOW_GOV] bootstrap lifecycle_id=' . shadow_governance_lifecycle_id()
				. ' registry=' . $registry_version
				. ' registry_contract=' . $registry_contract_version
				. ' controller=' . $controller
				. ' method=' . $method
				. ' intent=' . $intent
				. ' endpoint_write_expectation=' . $endpoint_write_expectation
		);

		// NOTE: Phase-1 is non-invasive; we do not intercept DB writes yet.
		// We only emit a lifecycle marker and rely on CI DB profiler for query capture.
		// Query capture is finalized in shadow_governance_finalize().
		if ($CI->config->item('SHADOW_GOVERNANCE_LOG_DB_WRITES')) {
			// Ensure profiler is enabled for this request so we can read executed queries.
			if (isset($CI->db) && is_object($CI->db)) {
				$CI->db->save_queries = true;
			}
		}

		static $shadow_shutdown_registered = false;
		if (!$shadow_shutdown_registered) {
			$shadow_shutdown_registered = true;
			if (function_exists('shadow_governance_finalize')) {
				register_shutdown_function('shadow_governance_finalize');
			}
		}
	}
}

if (!function_exists('shadow_governance_finalize')) {
	function shadow_governance_finalize()
	{
		$CI =& get_instance();
		if (!isset($CI->config)) {
			return;
		}
		$enabled = (bool)$CI->config->item('SHADOW_GOVERNANCE_ENABLED');
		if (!$enabled) {
			return;
		}
		if (!function_exists('shadow_governance_lifecycle_id')) {
			return;
		}
		if (!$CI->config->item('SHADOW_GOVERNANCE_LOG_DB_WRITES')) {
			return;
		}
		if (!isset($CI->db) || !is_object($CI->db)) {
			return;
		}

		static $shadow_finalized = false;
		if ($shadow_finalized) {
			return;
		}
		$shadow_finalized = true;

		// Deterministic activation proof: are we actually running the instrumented DB driver?
		$db_driver_class = get_class($CI->db);
		$db_driver_instrumented = false;
		$driver_path = APPPATH.'core/MY_DB_mysqli_driver.php';
		if (!class_exists('MY_DB_mysqli_driver', false) && file_exists($driver_path)) {
			require_once($driver_path);
		}
		if (is_a($CI->db, 'MY_DB_mysqli_driver')) {
			$db_driver_instrumented = true;
		}

		$collector_writes = array();
		if (!class_exists('ShadowWriteCollector', false)) {
			$collector_path = APPPATH.'libraries/ShadowWriteCollector.php';
			if (file_exists($collector_path)) {
				require_once($collector_path);
			}
		}
		if (class_exists('ShadowWriteCollector', false)) {
			$collector_writes = ShadowWriteCollector::flush();
			if (!is_array($collector_writes)) {
				$collector_writes = array();
			}
		}

		// Best-effort DB write observation: parse executed SQL for INSERT/UPDATE/DELETE.
		// This is a SHADOW-only observer; it does not block or mutate behavior.
		$queries = array();
		if (isset($CI->db->queries) && is_array($CI->db->queries)) {
			$queries = $CI->db->queries;
		}

		$lifecycle_id = shadow_governance_lifecycle_id();
		$registry_version = (string)$CI->config->item('SHADOW_GOVERNANCE_REGISTRY_VERSION');
		$registry_contract_version = shadow_governance_registry_version();
		$controller = isset($CI->router) ? (string)$CI->router->fetch_class() : '';
		$method = isset($CI->router) ? (string)$CI->router->fetch_method() : '';
		$enforcement_mode = (bool)$CI->config->item('SHADOW_GOVERNANCE_ENFORCEMENT_MODE');
		$governance_unsafe = false;
		$intent = shadow_governance_resolve_intent($controller, $method);
		$endpoint_write_expectation = shadow_governance_resolve_endpoint_write_expectation($controller, $method);
		$context = shadow_governance_capture_context();
		$audit_table = null;
		if (isset($CI->config)) {
			$CI->config->load('shadow_audit', true);
			$audit_table = $CI->config->item('shadow_audit_table', 'shadow_audit');
			if (!is_string($audit_table) || $audit_table === '') {
				$audit_table = null;
			}
			if ($audit_table !== null && isset($CI->db) && is_object($CI->db) && method_exists($CI->db, 'shadow_normalize_table')) {
				$audit_table = (string)$CI->db->shadow_normalize_table($audit_table);
			}
		}

		static $shadow_audit_immutability_checked = false;
		if (!$shadow_audit_immutability_checked) {
			$shadow_audit_immutability_checked = true;
			if ($audit_table !== null && preg_match('/^[a-zA-Z0-9_]+$/', $audit_table)) {
				try {
					$schemaRow = $CI->db->query("SELECT DATABASE() AS db")->row();
					$schemaName = ($schemaRow && isset($schemaRow->db)) ? (string)$schemaRow->db : '';
					if ($schemaName !== '') {
						$need = array(
							"trg_{$audit_table}_no_update",
							"trg_{$audit_table}_no_delete",
						);
						$missing = array();
						foreach ($need as $tName) {
							$q = $CI->db->query(
								"SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? AND TRIGGER_NAME = ? LIMIT 1",
								array($schemaName, $tName)
							);
							if (!$q || !$q->row()) {
								$missing[] = $tName;
							}
						}
						if (!empty($missing)) {
							log_message('error', '[SHADOW_AUDIT_IMMUTABILITY_MISSING] lifecycle_id=' . $lifecycle_id
								. ' audit_table=' . $audit_table
								. ' missing_triggers=' . json_encode($missing)
							);
							if ($enforcement_mode) {
								$governance_unsafe = true;
								log_message('error', '[SHADOW_GOV][GOVERNANCE_UNSAFE] lifecycle_id=' . $lifecycle_id
									. ' reason=AUDIT_IMMUTABILITY_MISSING'
									. ' missing_triggers=' . json_encode($missing)
								);
							}
							if (!class_exists('ShadowAlertService', false)) {
								$alert_path = APPPATH.'libraries/ShadowAlertService.php';
								if (file_exists($alert_path)) {
									require_once($alert_path);
								}
							}
							if (class_exists('ShadowAlertService', false)) {
								$alerter = new ShadowAlertService();
								$alerter->send(array(
									'domain' => 'SHADOW_SYSTEM',
									'intent' => 'AUDIT_IMMUTABILITY',
									'severity' => 'CRITICAL',
									'request_id' => $lifecycle_id,
									'parity' => array('status' => 'ERROR', 'code' => 'AUDIT_IMMUTABILITY_MISSING', 'data' => array('audit_table' => $audit_table, 'missing_triggers' => $missing)),
									'proof' => array('status' => 'UNPROVABLE', 'code' => 'PROOF_SKIPPED', 'data' => array('reason' => 'AUDIT_IMMUTABILITY_CHECK')),
									'event' => array('controller' => $controller, 'method' => $method)
								));
							}
						}
					}
				} catch (Exception $e) {
					log_message('error', '[SHADOW_AUDIT_IMMUTABILITY_CHECK_FAIL] lifecycle_id=' . $lifecycle_id . ' err=' . $e->getMessage());
				}
			}
		}

		$capture_gap_detected = false;
		$capture_gap_missing = array();
		$sql_write_keys = array();
		$collector_write_keys = array();
		foreach ($collector_writes as $w) {
			if (!is_array($w) || !isset($w['table'], $w['operation'])) {
				continue;
			}
			$table = (string)$w['table'];
			$op = (string)$w['operation'];
			if ($table === '' || $op === '') {
				continue;
			}
			if (isset($CI->db) && is_object($CI->db) && method_exists($CI->db, 'shadow_normalize_table')) {
				$table = (string)$CI->db->shadow_normalize_table($table);
			}
			if ($audit_table !== null && $table === $audit_table) {
				continue;
			}
			$key = $table . ':' . $op;
			$collector_write_keys[$key] = true;
		}

		foreach ($queries as $sql) {
			$s = trim((string)$sql);
			$u = strtoupper(substr($s, 0, 12));
			$op = '';
			if (strpos($u, 'INSERT') === 0) {
				$op = 'INSERT';
			} elseif (strpos($u, 'UPDATE') === 0) {
				$op = 'UPDATE';
			} elseif (strpos($u, 'DELETE') === 0) {
				$op = 'DELETE';
			}
			if ($op === '') {
				continue;
			}

			$table = '';
			$m = array();
			if (preg_match('/^\s*INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $s, $m)) {
				$table = $m[1];
			} elseif (preg_match('/^\s*UPDATE\s+`?([a-zA-Z0-9_]+)`?/i', $s, $m)) {
				$table = $m[1];
			} elseif (preg_match('/^\s*DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i', $s, $m)) {
				$table = $m[1];
			}
			if ($table !== '' && isset($CI->db) && is_object($CI->db) && method_exists($CI->db, 'shadow_normalize_table')) {
				$table = (string)$CI->db->shadow_normalize_table($table);
			}
			if ($table === 'ci_sessions') {
				continue;
			}
			if ($audit_table !== null && $table === $audit_table) {
				$table = '';
			}
			if ($table !== '') {
				$sql_write_keys[$table . ':' . $op] = true;
			}

			log_message(
				'debug',
				'[SHADOW_GOV][DB_WRITE] lifecycle_id=' . $lifecycle_id
					. ' registry=' . $registry_version
					. ' controller=' . $controller
					. ' method=' . $method
					. ' op=' . $op
					. ' sql=' . preg_replace('/\s+/', ' ', $s)
			);
		}

		if ($db_driver_instrumented && !empty($sql_write_keys)) {
			$missing = array_diff(array_keys($sql_write_keys), array_keys($collector_write_keys));
			if (!empty($missing)) {
				$capture_gap_detected = true;
				$capture_gap_missing = array_values($missing);
				log_message('error', '[SHADOW_GOV][CAPTURE_GAP_DETECTED] lifecycle_id=' . $lifecycle_id
					. ' sql_writes=' . count($sql_write_keys)
					. ' collector_writes=' . count($collector_write_keys)
					. ' missing=' . json_encode($capture_gap_missing)
				);
			}
		}

		if (empty($sql_write_keys) && empty($collector_write_keys) && !$capture_gap_detected) {
			return;
		}

		// Phase 1A: Domain mapping (shadow-only tagging; no enforcement).
		if ($CI->config->item('SHADOW_GOVERNANCE_LOG_DOMAIN_MAP')) {
			$tables_detected = array();
			$sql_ops_seen = array();
			foreach ($collector_writes as $w) {
				if (!is_array($w)) {
					continue;
				}
				$op = isset($w['operation']) ? (string)$w['operation'] : '';
				if ($op !== '' && !in_array($op, $sql_ops_seen, true)) {
					$sql_ops_seen[] = $op;
				}
				$table = isset($w['table']) ? (string)$w['table'] : '';
				if ($table === '') {
					continue;
				}
				// Normalize again using driver logic if available (dbprefix stripping).
				if (isset($CI->db) && is_object($CI->db) && method_exists($CI->db, 'shadow_normalize_table')) {
					$table = (string)$CI->db->shadow_normalize_table($table);
				}
				if ($audit_table !== null && $table === $audit_table) {
					continue;
				}
				if (!in_array($table, $tables_detected, true)) {
					$tables_detected[] = $table;
				}
			}

			$domain = 'UNKNOWN';
			$confidence = 'LOW';
			$ctl = strtolower((string)$controller);
			$mtd = strtolower((string)$method);

			$has_table = function ($t) use ($tables_detected) {
				return in_array($t, $tables_detected, true);
			};
			$method_has = function ($needle) use ($mtd) {
				return (strpos($mtd, $needle) !== false);
			};
			$controller_is = function ($name) use ($ctl) {
				return $ctl === $name;
			};

			$has_intake_table = $has_table('iop_intake_record');
			$has_output_table = $has_table('iop_output_record');
			if ($has_intake_table && $has_output_table) {
				$domain = 'AMBIGUOUS';
			} elseif ($has_intake_table) {
				$domain = 'INTAKE';
			} elseif ($has_output_table) {
				$domain = 'OUTPUT';
			} else {
				$match = array(
					'MEDICATION' => (
						$has_table('iop_medication_administration')
						|| $has_table('iop_medication')
					),
					'PROCEDURE' => (
						$has_table('iop_bed_side_procedure')
					),
					'VITALS' => (
						$has_table('iop_vital_parameters')
						|| $has_table('patient_details_iop')
					),
					'ROOM_TRANSFER' => (
						$has_table('iop_room_transfer')
					),
					'NURSE_NOTES' => (
						$has_table('iop_nurse_notes')
					)
				);

				$priority = array('MEDICATION', 'PROCEDURE', 'VITALS', 'ROOM_TRANSFER', 'NURSE_NOTES');
				foreach ($priority as $d) {
					if (!empty($match[$d])) {
						$domain = $d;
						break;
					}
				}
			}

			// Deterministic confidence:
			// HIGH: direct table match + method match
			// MEDIUM: method match only
			// LOW: controller match only
			if ($domain === 'AMBIGUOUS') {
				$confidence = 'HIGH';
			} elseif ($domain === 'INTAKE') {
				if ($has_table('iop_intake_record') && $method_has('intake')) {
					$confidence = 'HIGH';
				} elseif ($method_has('intake')) {
					$confidence = 'MEDIUM';
				} elseif ($controller_is('nurse_module')) {
					$confidence = 'LOW';
				}
			} elseif ($domain === 'OUTPUT') {
				if ($has_table('iop_output_record') && $method_has('output')) {
					$confidence = 'HIGH';
				} elseif ($method_has('output')) {
					$confidence = 'MEDIUM';
				} elseif ($controller_is('nurse_module')) {
					$confidence = 'LOW';
				}
			} elseif ($domain === 'NURSE_NOTES') {
				if ($has_table('iop_nurse_notes') && ($method_has('note') || $method_has('progress'))) {
					$confidence = 'HIGH';
				} elseif ($method_has('note') || $method_has('progress')) {
					$confidence = 'MEDIUM';
				} elseif ($controller_is('nurse_module')) {
					$confidence = 'LOW';
				}
			} elseif ($domain === 'ROOM_TRANSFER') {
				if ($has_table('iop_room_transfer') && ($method_has('transfer') || ($method_has('room') && $method_has('transfer')))) {
					$confidence = 'HIGH';
				} elseif ($method_has('transfer') || ($method_has('room') && $method_has('transfer'))) {
					$confidence = 'MEDIUM';
				} elseif ($controller_is('nurse_module')) {
					$confidence = 'LOW';
				}
			} elseif ($domain === 'VITALS') {
				if ($has_table('iop_vital_parameters') && $method_has('vital')) {
					$confidence = 'HIGH';
				} elseif ($method_has('vital')) {
					$confidence = 'MEDIUM';
				} elseif ($controller_is('nurse_module') || $controller_is('opd') || $controller_is('ipd')) {
					$confidence = 'LOW';
				}
			} elseif ($domain === 'MEDICATION') {
				if (($has_table('iop_medication_administration') || $has_table('iop_medication')) && ($method_has('medication') || $method_has('drug') || $method_has('admin'))) {
					$confidence = 'HIGH';
				} elseif ($method_has('medication') || $method_has('drug') || $method_has('admin')) {
					$confidence = 'MEDIUM';
				} else {
					$confidence = 'LOW';
				}
			} elseif ($domain === 'PROCEDURE') {
				if ($has_table('iop_bed_side_procedure') && ($method_has('procedure') || $controller_is('ipd'))) {
					$confidence = 'HIGH';
				} elseif ($method_has('procedure')) {
					$confidence = 'MEDIUM';
				} else {
					$confidence = 'LOW';
				}
			}

			$expectedset_binding = shadow_governance_resolve_expectedset_binding($domain, $intent, $controller, $method);
			$expectedset_loaded = (bool)$expectedset_binding['expectedset_loaded'];
			$expectedset_version = $expectedset_binding['expectedset_version'];
			$expectedset_invariants = $expectedset_binding['expectedset_invariants'];
			$event = array(
				'lifecycle_id' => $lifecycle_id,
				'controller' => (string)$controller,
				'method' => (string)$method,
				'intent' => $intent,
				'endpoint_write_expectation' => $endpoint_write_expectation,
				'context' => $context,
				'tables_touched' => $collector_writes,
				'capture_gap_detected' => $capture_gap_detected,
				'capture_gap_missing' => $capture_gap_missing,
				'db_driver_class' => $db_driver_class,
				'db_driver_instrumented' => $db_driver_instrumented,
				'expectedset_loaded' => $expectedset_loaded,
				'expectedset_version' => $expectedset_version,
				'expectedset_contract_bound' => (bool)$expectedset_binding['expectedset_contract_bound'],
				'expectedset_binding_status' => (string)$expectedset_binding['expectedset_binding_status'],
				'expectedset_contract_domain' => $expectedset_binding['expectedset_contract_domain'],
				'expectedset_contract_intent' => $expectedset_binding['expectedset_contract_intent'],
				'expectedset_contract_key' => $expectedset_binding['expectedset_contract_key'],
				'expectedset_invariants' => $expectedset_invariants,
				'domain' => $domain,
				'domain_detected' => $domain,
				'confidence' => $confidence,
				'tables_detected' => $tables_detected,
				'sql_operations_seen' => $sql_ops_seen,
				'registry_version' => $registry_version,
				'registry_contract_version' => $registry_contract_version
			);

			$reg_validation = shadow_governance_registry_validate($domain, $controller, $method, $tables_detected);
			$event['registry_domain'] = (string)$reg_validation['registry_domain'];
			$event['match_status'] = (string)$reg_validation['match_status'];
			$event['transaction_class'] = $reg_validation['meta']['transaction_class'];
			$event['enforcement_tier'] = $reg_validation['meta']['enforcement_tier'];
			$event['audit_required'] = $reg_validation['meta']['audit_required'];
			$event['compensation_required'] = $reg_validation['meta']['compensation_required'];
			$event['drift_flags'] = $reg_validation['drift_flags'];
			$event['registry_candidates'] = $reg_validation['registry_candidates'];

			log_message('debug', '[SHADOW_GOV][DOMAIN_MAP] ' . json_encode($event));

			if (!class_exists('ShadowParityEngine', false)) {
				$engine_path = APPPATH.'libraries/ShadowParityEngine.php';
				if (file_exists($engine_path)) {
					require_once($engine_path);
				}
			}
			if (class_exists('ShadowParityEngine', false)) {
				$parity = null;
				$proof = null;
				$parity_status = null;
				$results = array();
				if (!$db_driver_instrumented) {
					$parity = array(
						'status' => 'UNPROVABLE',
						'code' => 'DB_DRIVER_NOT_INSTRUMENTED',
						'data' => array(
							'db_driver_class' => $db_driver_class
						)
					);
					$results[$domain] = $parity;
					log_message('debug', '[SHADOW_PARITY] ' . json_encode($results));
					$parity_status = 'UNPROVABLE';
				} elseif ($domain === 'AMBIGUOUS') {
					$parity = array(
						'status' => 'UNPROVABLE',
						'code' => 'AMBIGUOUS_DOMAIN',
						'data' => array(
							'tables_detected' => $tables_detected
						)
					);
					$results[$domain] = $parity;
					log_message('debug', '[SHADOW_PARITY] ' . json_encode($results));
					$parity_status = 'UNPROVABLE';
				} elseif ($domain === 'UNKNOWN') {
					$parity = array(
						'status' => 'UNPROVABLE',
						'code' => 'DOMAIN_UNRESOLVED',
						'data' => array(
							'tables_detected' => $tables_detected,
							'sql_operations_seen' => $sql_ops_seen
						)
					);
					$results[$domain] = $parity;
					log_message('debug', '[SHADOW_PARITY] ' . json_encode($results));
					$parity_status = 'UNPROVABLE';
				} elseif ($governance_unsafe) {
					$parity = array(
						'status' => 'ERROR',
						'code' => 'SYSTEM_GOVERNANCE_UNSAFE',
						'data' => array(
							'reason' => 'AUDIT_IMMUTABILITY_MISSING'
						)
					);
					$results[$domain] = $parity;
					log_message('debug', '[SHADOW_PARITY] ' . json_encode($results));
					$parity_status = 'ERROR';
				} else {
					$engine = new ShadowParityEngine();
					$domain_map = array($domain => $event);
					$results = $engine->evaluate($domain_map);
					log_message('debug', '[SHADOW_PARITY] ' . json_encode($results));

					if (is_array($results) && isset($results[$domain]) && is_array($results[$domain]) && isset($results[$domain]['status'])) {
						$parity_status = (string)$results[$domain]['status'];
					}
					$parity = (is_array($results) && isset($results[$domain]) && is_array($results[$domain])) ? $results[$domain] : array('status' => 'ERROR', 'code' => 'PARITY_RESULT_MISSING');

					if (!empty($event['expectedset_contract_bound'])) {
						if (!class_exists('ShadowProofExecutor', false)) {
							$executor_path = APPPATH.'libraries/ShadowProofExecutor.php';
							if (file_exists($executor_path)) {
								require_once($executor_path);
							}
						}
						if (class_exists('ShadowProofExecutor', false)) {
							$executor = new ShadowProofExecutor();
							$proof = $executor->execute($domain, $intent, $collector_writes, $event);
							log_message('debug', '[SHADOW_PROOF] ' . json_encode($proof));
						}
					}
				}

				$metric_category = function ($source, $status, $code) {
					$source = (string)$source;
					$status = (string)$status;
					$code = (string)$code;
					if ($status !== 'UNPROVABLE') {
						return null;
					}
					if ($code === 'DOMAIN_UNRESOLVED') {
						return 'DOMAIN_UNRESOLVED';
					}
					if ($code === 'PROOF_SCOPE_UNKNOWN') {
						return 'PROOF_SCOPE_UNKNOWN';
					}
					if (strpos($code, 'PRIMARY_KEY_') === 0) {
						return 'PK_UNPROVABLE';
					}
					return 'OTHER';
				};
				$parity_code = (is_array($parity) && isset($parity['code'])) ? (string)$parity['code'] : '';
				$parity_cat = $metric_category('PARITY', $parity_status, $parity_code);
				if ($parity_cat !== null) {
					log_message('info', '[SHADOW_METRIC][UNPROVABLE] source=PARITY category=' . $parity_cat
						. ' code=' . $parity_code
						. ' domain=' . $domain
						. ' intent=' . $intent
						. ' lifecycle_id=' . $lifecycle_id
					);
				}
				$proof_status = (is_array($proof) && isset($proof['status'])) ? (string)$proof['status'] : '';
				$proof_code = (is_array($proof) && isset($proof['code'])) ? (string)$proof['code'] : '';
				$proof_cat = $metric_category('PROOF', $proof_status, $proof_code);
				if ($proof_cat !== null) {
					log_message('info', '[SHADOW_METRIC][UNPROVABLE] source=PROOF category=' . $proof_cat
						. ' code=' . $proof_code
						. ' domain=' . $domain
						. ' intent=' . $intent
						. ' lifecycle_id=' . $lifecycle_id
					);
				}

				$severity = 'INFO';
				if (!class_exists('ShadowSeverityResolver', false)) {
					$sev_path = APPPATH.'libraries/ShadowSeverityResolver.php';
					if (file_exists($sev_path)) {
						require_once($sev_path);
					}
				}
				if (class_exists('ShadowSeverityResolver', false)) {
					$resolver = new ShadowSeverityResolver();
					$severity = $resolver->resolve($domain, $intent, $parity, $proof);
				}

				$auditData = array(
					'domain' => $domain,
					'intent' => $intent,
					'severity' => $severity,
					'request_id' => $lifecycle_id,
					'parity' => $parity,
					'proof' => $proof,
					'event' => $event
				);

				if (!class_exists('ShadowAuditLogger', false)) {
					$audit_path = APPPATH.'libraries/ShadowAuditLogger.php';
					if (file_exists($audit_path)) {
						require_once($audit_path);
					}
				}
				if (class_exists('ShadowAuditLogger', false)) {
					$audit = new ShadowAuditLogger();
					$audit->log($auditData);
				}

				if (!class_exists('ShadowAlertService', false)) {
					$alert_path = APPPATH.'libraries/ShadowAlertService.php';
					if (file_exists($alert_path)) {
						require_once($alert_path);
					}
				}
				if (class_exists('ShadowAlertService', false)) {
					$alerter = new ShadowAlertService();
					$alerter->send($auditData);
				}

				if (isset($CI->config)) {
					$CI->config->load('shadow_parity', true);
					$unified = (bool)$CI->config->item('shadow_proof_debug', 'shadow_parity');
					if ($unified) {
						$env = array(
							'domain' => $domain,
							'intent' => $intent,
							'parity' => (is_array($results) && isset($results[$domain])) ? $results[$domain] : $results,
							'proof' => $proof,
							'severity' => $severity
						);
						if ($parity_status !== 'PASS' && $proof === null) {
							$env['proof'] = array('status' => 'UNPROVABLE', 'code' => 'PROOF_SKIPPED', 'data' => array('reason' => 'PARITY_NOT_PASS'));
						}
						log_message('debug', '[SHADOW_EVAL] ' . json_encode($env));
					}
				}
			}
		}
	}
}
