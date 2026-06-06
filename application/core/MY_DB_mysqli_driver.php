<?php defined('BASEPATH') OR exit('No direct script access allowed');

class MY_DB_mysqli_driver extends CI_DB_mysqli_driver
{
	protected $_shadow_where_kv = array();
	protected $_shadow_suppress_query_record = false;
	protected $_shadow_suppress_collector_record = false;
	protected $_shadow_audit_table_name = null;

	protected function shadow_pk_from_insert_payload_info($table, $set)
	{
		$map = $this->shadow_primary_key_map();
		$table_norm = $this->shadow_normalize_table($table);
		$pkcol = isset($map[$table_norm]) ? (string)$map[$table_norm] : '';
		if ($pkcol === '') {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'no_primary_key_map');
		}
		if (!is_array($set) || !array_key_exists($pkcol, $set)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_payload_missing_pk');
		}
		$pk_raw = $set[$pkcol];
		if (!is_numeric($pk_raw)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_payload_pk_not_numeric');
		}
		$pk = (int)$pk_raw;
		if ($pk <= 0) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_payload_pk_invalid');
		}
		return array('pk' => $pk, 'pk_status' => 'PROVABLE', 'reason' => 'insert_payload_pk');
	}

	protected function shadow_pk_from_insert_binds_info($table, $sql, $binds)
	{
		if ($binds === FALSE || $binds === NULL) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'no_binds');
		}
		if (!is_array($binds) || empty($binds)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'binds_not_array');
		}

		$map = $this->shadow_primary_key_map();
		$table_norm = $this->shadow_normalize_table($table);
		$pkcol = isset($map[$table_norm]) ? (string)$map[$table_norm] : '';
		if ($pkcol === '') {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'no_primary_key_map');
		}

		// Strict recognizer:
		// INSERT INTO <table> (`c1`,`c2`,...) VALUES (?, ?, ...)
		// Only supports a single VALUES tuple and placeholder-only values.
		$m = array();
		$pattern = '/^\s*INSERT\s+INTO\s+`?'.preg_quote((string)$table, '/').'`?\s*\(([^\)]+)\)\s*VALUES\s*\(([^\)]+)\)\s*;?\s*$/i';
		if (!preg_match($pattern, (string)$sql, $m)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_sql_unrecognized');
		}
		$cols_raw = trim((string)$m[1]);
		$vals_raw = trim((string)$m[2]);
		if ($cols_raw === '' || $vals_raw === '') {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_sql_unrecognized');
		}

		$cols = array_map(function ($x) {
			$x = trim((string)$x);
			$x = trim($x, "`\" ");
			return $x;
		}, explode(',', $cols_raw));
		$vals = array_map('trim', explode(',', $vals_raw));
		if (count($cols) !== count($vals) || count($cols) !== count($binds)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_sql_arity_mismatch');
		}
		foreach ($vals as $v) {
			if ($v !== '?') {
				return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_sql_non_placeholder');
			}
		}

		$idx = array_search($pkcol, $cols, true);
		if ($idx === false) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_sql_missing_pk_column');
		}
		$pk_raw = $binds[(int)$idx];
		if (!is_numeric($pk_raw)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'pk_not_numeric');
		}
		$pk = (int)$pk_raw;
		if ($pk <= 0) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'pk_invalid');
		}
		return array('pk' => $pk, 'pk_status' => 'PROVABLE', 'reason' => 'insert_sql_binds_pk');
	}

	protected function shadow_pk_from_insert_id_info()
	{
		$id = (int)$this->insert_id();
		if ($id > 0) {
			return array('pk' => $id, 'pk_status' => 'PROVABLE', 'reason' => null);
		}
		return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'insert_id_empty');
	}

	protected function shadow_pk_from_where_info($table)
	{
		if (isset($this->_shadow_where_kv['__ambiguous__'])) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'ambiguous_where');
		}
		if (count($this->_shadow_where_kv) !== 1) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'where_not_single_kv');
		}
		$map = $this->shadow_primary_key_map();
		$table_norm = $this->shadow_normalize_table($table);
		$pkcol = isset($map[$table_norm]) ? (string)$map[$table_norm] : '';
		if ($pkcol === '') {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'no_primary_key_map');
		}
		if (!array_key_exists($pkcol, $this->_shadow_where_kv)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'where_key_not_pk');
		}
		$pk_raw = $this->_shadow_where_kv[$pkcol];
		if (!is_numeric($pk_raw)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'pk_not_numeric');
		}
		$pk = (int)$pk_raw;
		if ($pk <= 0) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'pk_invalid');
		}
		return array('pk' => $pk, 'pk_status' => 'PROVABLE', 'reason' => null);
	}

	protected function shadow_pk_from_binds_info($table, $sql, $binds)
	{
		if ($binds === FALSE || $binds === NULL) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'no_binds');
		}
		if (!is_array($binds)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'binds_not_array');
		}
		if (count($binds) !== 1) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'binds_not_single');
		}
		$map = $this->shadow_primary_key_map();
		$table_norm = $this->shadow_normalize_table($table);
		$pkcol = isset($map[$table_norm]) ? (string)$map[$table_norm] : '';
		if ($pkcol === '') {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'no_primary_key_map');
		}
		$pattern = '/\\bWHERE\\s+`?'.preg_quote($pkcol, '/').'`?\\s*=\\s*\\?/i';
		if (!preg_match($pattern, (string)$sql)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'binds_not_pk_equality');
		}
		$pk_raw = $binds[0];
		if (!is_numeric($pk_raw)) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'pk_not_numeric');
		}
		$pk = (int)$pk_raw;
		if ($pk <= 0) {
			return array('pk' => null, 'pk_status' => 'UNPROVABLE', 'reason' => 'pk_invalid');
		}
		return array('pk' => $pk, 'pk_status' => 'PROVABLE', 'reason' => null);
	}

	protected function shadow_governance_enabled()
	{
		static $enabled = null;
		if ($enabled !== null) {
			return $enabled;
		}
		$enabled = false;
		if (function_exists('get_instance')) {
			$CI =& get_instance();
			if (isset($CI->config)) {
				$enabled = (bool)$CI->config->item('SHADOW_GOVERNANCE_ENABLED')
					&& (bool)$CI->config->item('SHADOW_GOVERNANCE_LOG_DB_WRITES');
			}
		}
		return $enabled;
	}

	protected function shadow_audit_table_name()
	{
		if ($this->_shadow_audit_table_name !== null) {
			return $this->_shadow_audit_table_name;
		}
		$name = 'shadow_audit_log';
		if (function_exists('get_instance')) {
			$CI =& get_instance();
			if (isset($CI->config)) {
				$CI->config->load('shadow_audit', true);
				$v = $CI->config->item('shadow_audit_table', 'shadow_audit');
				if (is_string($v) && $v !== '') {
					$name = $v;
				}
			}
		}
		$this->_shadow_audit_table_name = $this->shadow_normalize_table($name);
		return $this->_shadow_audit_table_name;
	}

	protected function shadow_should_record_table($table_norm)
	{
		if ($this->_shadow_suppress_collector_record) {
			return false;
		}
		if ($table_norm === '') {
			return false;
		}
		if ($table_norm === 'ci_sessions') {
			return false;
		}
		return $table_norm !== $this->shadow_audit_table_name();
	}

	public function shadow_begin_internal_write()
	{
		$this->_shadow_suppress_collector_record = true;
		$this->_shadow_suppress_query_record = true;
	}

	public function shadow_end_internal_write()
	{
		$this->_shadow_suppress_collector_record = false;
		$this->_shadow_suppress_query_record = false;
	}

	protected function shadow_collector_ready()
	{
		if ( ! class_exists('ShadowWriteCollector', false)) {
			$path = APPPATH.'libraries/ShadowWriteCollector.php';
			if (file_exists($path)) {
				require_once($path);
			}
		}
		return class_exists('ShadowWriteCollector', false);
	}

	public function shadow_normalize_table($table)
	{
		$t = trim((string)$table);
		$t = trim($t, "`\" ");
		if ($this->dbprefix !== '' && strpos($t, $this->dbprefix) === 0) {
			$t = substr($t, strlen($this->dbprefix));
		}
		return $t;
	}

	protected function shadow_primary_key_map()
	{
		$CI =& get_instance();
		if (isset($CI->config)) {
			$CI->config->load('shadow_governance_expectedset', true);
			$map = $CI->config->item('shadow_governance_primary_key_map', 'shadow_governance_expectedset');
			if (is_array($map)) {
				return $map;
			}
		}
		return array();
	}

	public function where($key, $value = NULL, $escape = NULL)
	{
		$ret = parent::where($key, $value, $escape);
		if ($this->shadow_governance_enabled()) {
			$this->shadow_track_where($key, $value);
		}
		return $ret;
	}

	public function or_where($key, $value = NULL, $escape = NULL)
	{
		$ret = parent::or_where($key, $value, $escape);
		if ($this->shadow_governance_enabled()) {
			// Treat OR as ambiguous for PK extraction.
			$this->_shadow_where_kv = array('__ambiguous__' => true);
		}
		return $ret;
	}

	protected function shadow_track_where($key, $value)
	{
		if (isset($this->_shadow_where_kv['__ambiguous__'])) {
			return;
		}
		if (is_array($key)) {
			foreach ($key as $k => $v) {
				$this->shadow_track_where($k, $v);
			}
			return;
		}
		if ($value === NULL) {
			return;
		}
		$k = (string)$key;
		if ($k === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $k)) {
			$this->_shadow_where_kv = array('__ambiguous__' => true);
			return;
		}
		if (is_array($value)) {
			$this->_shadow_where_kv = array('__ambiguous__' => true);
			return;
		}
		$this->_shadow_where_kv[$k] = $value;
		if (count($this->_shadow_where_kv) > 1) {
			$this->_shadow_where_kv = array('__ambiguous__' => true);
		}
	}

	protected function shadow_extract_pk_from_where($table)
	{
		if (isset($this->_shadow_where_kv['__ambiguous__'])) {
			return null;
		}
		if (count($this->_shadow_where_kv) !== 1) {
			return null;
		}
		$map = $this->shadow_primary_key_map();
		$table = $this->shadow_normalize_table($table);
		$pkcol = isset($map[$table]) ? (string)$map[$table] : '';
		if ($pkcol === '') {
			return null;
		}
		if (!array_key_exists($pkcol, $this->_shadow_where_kv)) {
			return null;
		}
		return $this->_shadow_where_kv[$pkcol];
	}

	protected function shadow_extract_table_from_sql($sql)
	{
		$s = trim((string)$sql);
		$m = array();
		if (preg_match('/^\s*INSERT\s+INTO\s+`?([a-zA-Z0-9_]+)`?/i', $s, $m)) {
			return $m[1];
		}
		if (preg_match('/^\s*UPDATE\s+`?([a-zA-Z0-9_]+)`?/i', $s, $m)) {
			return $m[1];
		}
		if (preg_match('/^\s*DELETE\s+FROM\s+`?([a-zA-Z0-9_]+)`?/i', $s, $m)) {
			return $m[1];
		}
		return '';
	}

	protected function shadow_extract_op_from_sql($sql)
	{
		$s = ltrim((string)$sql);
		$u = strtoupper(substr($s, 0, 12));
		if (strpos($u, 'INSERT') === 0) {
			return 'INSERT';
		}
		if (strpos($u, 'UPDATE') === 0) {
			return 'UPDATE';
		}
		if (strpos($u, 'DELETE') === 0) {
			return 'DELETE';
		}
		return '';
	}

	protected function shadow_extract_pk_from_binds($table, $sql, $binds)
	{
		if (!is_array($binds) || count($binds) !== 1) {
			return null;
		}
		$map = $this->shadow_primary_key_map();
		$table = $this->shadow_normalize_table($table);
		$pkcol = isset($map[$table]) ? (string)$map[$table] : '';
		if ($pkcol === '') {
			return null;
		}
		// Deterministic: only accept single-key where on the configured PK column.
		$pattern = '/\\bWHERE\\s+`?'.preg_quote($pkcol, '/').'`?\\s*=\\s*\\?/i';
		if (!preg_match($pattern, (string)$sql)) {
			return null;
		}
		return $binds[0];
	}

	public function query($sql, $binds = FALSE, $return_object = NULL)
	{
		$should_record = $this->shadow_governance_enabled() && !$this->_shadow_suppress_query_record && !$this->_shadow_suppress_collector_record;
		$op = $should_record ? $this->shadow_extract_op_from_sql($sql) : '';
		$table = '';
		if ($should_record && $op !== '' && ($op === 'INSERT' || $op === 'UPDATE' || $op === 'DELETE')) {
			$table = $this->shadow_extract_table_from_sql($sql);
		}

		$res = parent::query($sql, $binds, $return_object);

		if ($should_record && $res === TRUE && $op !== '' && $table !== '' && $this->shadow_collector_ready()) {
			$table_norm = $this->shadow_normalize_table($table);
			if (!$this->shadow_should_record_table($table_norm)) {
				return $res;
			}
			$pk = null;
			$wk = null;
			$update_set = null;
			$pk_status = 'UNPROVABLE';
			$reason = 'unknown';
			if ($op === 'INSERT') {
				$info = $this->shadow_pk_from_insert_id_info();
				$pk = $info['pk'];
				$pk_status = $info['pk_status'];
				$reason = $info['reason'];
				if ($pk_status === 'UNPROVABLE' && $reason === 'insert_id_empty') {
					$info2 = $this->shadow_pk_from_insert_binds_info($table_norm, $sql, $binds);
					if ($info2['pk_status'] === 'PROVABLE') {
						$pk = $info2['pk'];
						$pk_status = $info2['pk_status'];
						$reason = $info2['reason'];
					}
				}
			} elseif ($op === 'UPDATE' || $op === 'DELETE') {
				$info = $this->shadow_pk_from_binds_info($table_norm, $sql, $binds);
				$pk = $info['pk'];
				$pk_status = $info['pk_status'];
				$reason = $info['reason'];
				if ($pk !== null) {
					$map = $this->shadow_primary_key_map();
					$pkcol = isset($map[$table_norm]) ? (string)$map[$table_norm] : '';
					if ($pkcol !== '') {
						$wk = array($pkcol => $pk);
					}

					// Strict update_set recognizer for raw query() UPDATE statements:
					// UPDATE <table> SET <single_column> = <literal_int> WHERE <pk_column> = ?
					if ($op === 'UPDATE' && $pk_status === 'PROVABLE' && $pkcol !== '') {
						$m = array();
						$pattern = '/^\s*UPDATE\s+`?'.preg_quote((string)$table, '/').'`?\s+SET\s+`?([a-zA-Z0-9_]+)`?\s*=\s*(\d+)\s+WHERE\s+`?'.preg_quote($pkcol, '/').'`?\s*=\s*\?\s*;?\s*$/i';
						if (preg_match($pattern, (string)$sql, $m)) {
							$update_set = array($m[1] => (int)$m[2]);
						}
					}
				}
			}
			ShadowWriteCollector::record(array(
				'table' => $table_norm,
				'operation' => $op,
				'primary_key' => $pk,
				'pk_status' => $pk_status,
				'reason' => $reason,
				'where_keys' => $wk,
				'update_set' => $update_set,
				'timestamp' => microtime(true)
			));
		}

		return $res;
	}

	public function insert($table = '', $set = NULL, $escape = NULL)
	{
		$this->_shadow_suppress_query_record = true;
		$result = parent::insert($table, $set, $escape);
		$this->_shadow_suppress_query_record = false;
		if ($result && $this->shadow_governance_enabled() && !$this->_shadow_suppress_collector_record && $this->shadow_collector_ready()) {
			$info = $this->shadow_pk_from_insert_id_info();
			$table_norm = $this->shadow_normalize_table($table);
			if (!$this->shadow_should_record_table($table_norm)) {
				return $result;
			}
			if ($info['pk_status'] === 'UNPROVABLE' && $info['reason'] === 'insert_id_empty') {
				$info2 = $this->shadow_pk_from_insert_payload_info($table_norm, $set);
				if ($info2['pk_status'] === 'PROVABLE') {
					$info = $info2;
				}
			}
			ShadowWriteCollector::record(array(
				'table' => $table_norm,
				'operation' => 'INSERT',
				'primary_key' => $info['pk'],
				'pk_status' => $info['pk_status'],
				'reason' => $info['reason'],
				'where_keys' => null,
				'timestamp' => microtime(true)
			));
		}
		return $result;
	}

	public function update($table = '', $set = NULL, $where = NULL, $limit = NULL)
	{
		// If $where is explicitly provided to update(), treat it as the authoritative
		// filter for this statement (and reset any previously tracked chain state).
		// If $where is NULL (typical chained where()->update() usage), keep the
		// already tracked where() keys.
		if ($this->shadow_governance_enabled() && $where !== NULL) {
			$this->_shadow_where_kv = array();
			if (is_array($where)) {
				$this->shadow_track_where($where, null);
			} elseif ($where !== '' && $where !== FALSE) {
				$this->_shadow_where_kv = array('__ambiguous__' => true);
			}
		}

		$this->_shadow_suppress_query_record = true;
		$result = parent::update($table, $set, $where, $limit);
		$this->_shadow_suppress_query_record = false;

		if ($result && $this->shadow_governance_enabled() && !$this->_shadow_suppress_collector_record && $this->shadow_collector_ready()) {
			$table_norm = $this->shadow_normalize_table($table);
			if (!$this->shadow_should_record_table($table_norm)) {
				$this->_shadow_where_kv = array();
				return $result;
			}
			$info = $this->shadow_pk_from_where_info($table);
			$pk = $info['pk'];
			$pk_status = $info['pk_status'];
			$reason = $info['reason'];
			$update_set = null;
			if (is_array($set)) {
				$update_set = array();
				foreach ($set as $k => $v) {
					$key = (string)$k;
					if ($key === '') {
						continue;
					}
					if ($v === null || is_scalar($v)) {
						$update_set[$key] = $v;
					} else {
						$update_set[$key] = '__non_scalar__';
					}
				}
			}
			$wk = null;
			if (!isset($this->_shadow_where_kv['__ambiguous__']) && count($this->_shadow_where_kv) === 1) {
				$wk = $this->_shadow_where_kv;
			}
			ShadowWriteCollector::record(array(
				'table' => $table_norm,
				'operation' => 'UPDATE',
				'primary_key' => $pk,
				'pk_status' => $pk_status,
				'reason' => $reason,
				'where_keys' => $wk,
				'update_set' => $update_set,
				'timestamp' => microtime(true)
			));
		}

		$this->_shadow_where_kv = array();
		return $result;
	}

	public function delete($table = '', $where = '', $limit = NULL, $reset_data = TRUE)
	{
		if ($this->shadow_governance_enabled() && $where !== '') {
			// delete() will call where($where) internally; ensure any non-array where
			// is treated as ambiguous for deterministic PK extraction.
			if ( ! is_array($where)) {
				$this->_shadow_where_kv = array('__ambiguous__' => true);
			} else {
				$this->_shadow_where_kv = array();
				$this->shadow_track_where($where, null);
			}
		}

		$this->_shadow_suppress_query_record = true;
		$result = parent::delete($table, $where, $limit, $reset_data);
		$this->_shadow_suppress_query_record = false;

		if ($result && $this->shadow_governance_enabled() && !$this->_shadow_suppress_collector_record && $this->shadow_collector_ready()) {
			$table_norm = $this->shadow_normalize_table($table);
			if (!$this->shadow_should_record_table($table_norm)) {
				$this->_shadow_where_kv = array();
				return $result;
			}
			$info = $this->shadow_pk_from_where_info($table);
			$pk = $info['pk'];
			$pk_status = $info['pk_status'];
			$reason = $info['reason'];
			$wk = null;
			if (!isset($this->_shadow_where_kv['__ambiguous__']) && count($this->_shadow_where_kv) === 1) {
				$wk = $this->_shadow_where_kv;
			}
			ShadowWriteCollector::record(array(
				'table' => $table_norm,
				'operation' => 'DELETE',
				'primary_key' => $pk,
				'pk_status' => $pk_status,
				'reason' => $reason,
				'where_keys' => $wk,
				'timestamp' => microtime(true)
			));
		}

		$this->_shadow_where_kv = array();
		return $result;
	}
}
