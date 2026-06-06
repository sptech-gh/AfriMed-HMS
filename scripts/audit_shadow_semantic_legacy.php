<?php

declare(strict_types=1);

define('BASEPATH', __DIR__);

if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'development');
}

define('SHADOW_AUDIT_SCRIPT_ROOT', realpath(__DIR__ . DIRECTORY_SEPARATOR . '..'));
if (SHADOW_AUDIT_SCRIPT_ROOT === false) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] cannot_resolve_project_root\n");
	exit(1);
}

define('SHADOW_AUDIT_APP_PATH', SHADOW_AUDIT_SCRIPT_ROOT . DIRECTORY_SEPARATOR . 'application');

define('SHADOW_AUDIT_CONFIG_PATH', SHADOW_AUDIT_APP_PATH . DIRECTORY_SEPARATOR . 'config');

define('SHADOW_AUDIT_LIB_PATH', SHADOW_AUDIT_APP_PATH . DIRECTORY_SEPARATOR . 'libraries');

$canonicalStatuses = array('PASS', 'VIOLATION', 'ERROR', 'UNPROVABLE');
$legacyStatuses = array('PROVEN', 'OK', 'SKIPPED');

$opts = array(
	'days' => null,
	'since' => null,
	'all' => false,
);
foreach ($argv as $i => $arg) {
	if ($i === 0) {
		continue;
	}
	if ($arg === '--all') {
		$opts['all'] = true;
		continue;
	}
	if (strpos($arg, '--days=') === 0) {
		$opts['days'] = (int)substr($arg, 7);
		continue;
	}
	if (strpos($arg, '--since=') === 0) {
		$opts['since'] = (string)substr($arg, 8);
		continue;
	}
}

$sinceClause = '';
$sinceParam = null;
if (!$opts['all']) {
	if (is_int($opts['days']) && $opts['days'] > 0) {
		$dt = new DateTime('now');
		$dt->modify('-' . $opts['days'] . ' day');
		$sinceParam = $dt->format('Y-m-d H:i:s');
	} elseif (is_string($opts['since']) && $opts['since'] !== '') {
		$sinceParam = $opts['since'] . ' 00:00:00';
	}
}
if (is_string($sinceParam) && $sinceParam !== '') {
	$sinceClause = ' WHERE created_at >= ? ';
}

$databasePath = SHADOW_AUDIT_CONFIG_PATH . DIRECTORY_SEPARATOR . 'database.php';
if (!file_exists($databasePath)) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] database_config_missing path={$databasePath}\n");
	exit(1);
}

$db = array();
require($databasePath);
if (!isset($db['default']) || !is_array($db['default'])) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] database_default_missing\n");
	exit(1);
}

$cfg = $db['default'];
$host = isset($cfg['hostname']) ? (string)$cfg['hostname'] : '';
$user = isset($cfg['username']) ? (string)$cfg['username'] : '';
$pass = isset($cfg['password']) ? (string)$cfg['password'] : '';
$name = isset($cfg['database']) ? (string)$cfg['database'] : '';

if ($host === '' || $user === '' || $name === '') {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] database_config_incomplete\n");
	exit(1);
}

$shadowAuditConfigPath = SHADOW_AUDIT_CONFIG_PATH . DIRECTORY_SEPARATOR . 'shadow_audit.php';
if (!file_exists($shadowAuditConfigPath)) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] shadow_audit_config_missing path={$shadowAuditConfigPath}\n");
	exit(1);
}

$config = array();
require($shadowAuditConfigPath);
$table = isset($config['shadow_audit_table']) ? (string)$config['shadow_audit_table'] : 'shadow_audit_log';
if ($table === '') {
	$table = 'shadow_audit_log';
}
if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] invalid_table_name\n");
	exit(1);
}

$semPath = SHADOW_AUDIT_LIB_PATH . DIRECTORY_SEPARATOR . 'ShadowGovernanceSemantics.php';
if (file_exists($semPath)) {
	require_once($semPath);
}
if (!class_exists('ShadowGovernanceSemantics', false)) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] semantics_not_available\n");
	exit(1);
}

$mysqli = @new mysqli($host, $user, $pass, $name);
if ($mysqli->connect_errno) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] db_connect_failed errno={$mysqli->connect_errno} msg={$mysqli->connect_error}\n");
	exit(1);
}
$mysqli->set_charset('utf8mb4');

$escTable = '`' . $table . '`';

$existsQ = $mysqli->prepare('SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1');
if (!$existsQ) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] audit_table_exists_prepare_failed\n");
	exit(1);
}
$existsQ->bind_param('s', $table);
$existsQ->execute();
$existsRes = $existsQ->get_result();
$existsRow = $existsRes ? $existsRes->fetch_row() : null;
$existsQ->close();
if (!$existsRow) {
	fwrite(STDERR, "[audit_shadow_semantic_legacy][error] audit_table_not_found table={$table}\n");
	exit(1);
}

function stmt_scalar(mysqli $mysqli, string $sql, array $params = array())
{
	$stmt = $mysqli->prepare($sql);
	if (!$stmt) {
		return null;
	}
	if (!empty($params)) {
		$types = '';
		$bind = array();
		foreach ($params as $p) {
			$types .= 's';
			$bind[] = (string)$p;
		}
		$stmt->bind_param($types, ...$bind);
	}
	$stmt->execute();
	$res = $stmt->get_result();
	$row = $res ? $res->fetch_row() : null;
	$stmt->close();
	return $row ? $row[0] : null;
}

function stmt_kv_counts(mysqli $mysqli, string $sql, array $params = array())
{
	$out = array();
	$stmt = $mysqli->prepare($sql);
	if (!$stmt) {
		return $out;
	}
	if (!empty($params)) {
		$types = '';
		$bind = array();
		foreach ($params as $p) {
			$types .= 's';
			$bind[] = (string)$p;
		}
		$stmt->bind_param($types, ...$bind);
	}
	$stmt->execute();
	$res = $stmt->get_result();
	while ($res && ($row = $res->fetch_assoc())) {
		$k = isset($row['k']) ? (string)$row['k'] : '';
		$v = isset($row['c']) ? (int)$row['c'] : 0;
		$out[$k] = $v;
	}
	$stmt->close();
	return $out;
}

$params = array();
if ($sinceClause !== '') {
	$params[] = $sinceParam;
}

$total = stmt_scalar($mysqli, "SELECT COUNT(*) FROM {$escTable}{$sinceClause}", $params);
$minCreated = stmt_scalar($mysqli, "SELECT MIN(created_at) FROM {$escTable}{$sinceClause}", $params);
$maxCreated = stmt_scalar($mysqli, "SELECT MAX(created_at) FROM {$escTable}{$sinceClause}", $params);

$rangeNote = ($sinceClause !== '') ? ("filter_since=" . (string)$sinceParam) : 'filter=ALL_ROWS';

echo "[audit_shadow_semantic_legacy] table={$table} {$rangeNote}\n";

echo "[audit_shadow_semantic_legacy] total_rows=" . (string)$total . " min_created_at=" . (string)$minCreated . " max_created_at=" . (string)$maxCreated . "\n";

$parityCounts = stmt_kv_counts(
	$mysqli,
	"SELECT CASE WHEN parity_status IS NULL THEN '(NULL)' WHEN parity_status = '' THEN '(EMPTY)' ELSE parity_status END AS k, COUNT(*) AS c FROM {$escTable}{$sinceClause} GROUP BY k ORDER BY c DESC",
	$params
);
$proofCounts = stmt_kv_counts(
	$mysqli,
	"SELECT CASE WHEN proof_status IS NULL THEN '(NULL)' WHEN proof_status = '' THEN '(EMPTY)' ELSE proof_status END AS k, COUNT(*) AS c FROM {$escTable}{$sinceClause} GROUP BY k ORDER BY c DESC",
	$params
);
$severityCounts = stmt_kv_counts(
	$mysqli,
	"SELECT CASE WHEN severity IS NULL THEN '(NULL)' WHEN severity = '' THEN '(EMPTY)' ELSE severity END AS k, COUNT(*) AS c FROM {$escTable}{$sinceClause} GROUP BY k ORDER BY c DESC",
	$params
);

echo "[audit_shadow_semantic_legacy] parity_status_counts=" . json_encode($parityCounts) . "\n";

echo "[audit_shadow_semantic_legacy] proof_status_counts=" . json_encode($proofCounts) . "\n";

echo "[audit_shadow_semantic_legacy] severity_counts=" . json_encode($severityCounts) . "\n";

$legacyCounts = array();
foreach ($legacyStatuses as $ls) {
	$legacyCounts[$ls] = (int)stmt_scalar($mysqli, "SELECT COUNT(*) FROM {$escTable}{$sinceClause}" . ($sinceClause !== '' ? ' AND ' : ' WHERE ') . "(UPPER(COALESCE(parity_status,'')) = ? OR UPPER(COALESCE(proof_status,'')) = ?)", array_merge($params, array($ls, $ls)));
}
$nullProof = (int)stmt_scalar($mysqli, "SELECT COUNT(*) FROM {$escTable}{$sinceClause}" . ($sinceClause !== '' ? ' AND ' : ' WHERE ') . "(proof_status IS NULL OR proof_status = '')", $params);
$nullParity = (int)stmt_scalar($mysqli, "SELECT COUNT(*) FROM {$escTable}{$sinceClause}" . ($sinceClause !== '' ? ' AND ' : ' WHERE ') . "(parity_status IS NULL OR parity_status = '')", $params);

echo "[audit_shadow_semantic_legacy] legacy_status_presence_counts=" . json_encode($legacyCounts) . "\n";

echo "[audit_shadow_semantic_legacy] null_or_empty parity_status={$nullParity} proof_status={$nullProof}\n";

$scan = array(
	'unknown_parity_status' => 0,
	'unknown_proof_status' => 0,
	'unknown_severity' => 0,
	'severity_below_min' => 0,
	'proof_non_unprovable_when_parity_not_pass' => 0,
);
$samples = array(
	'unknown_parity_status' => array(),
	'unknown_proof_status' => array(),
	'unknown_severity' => array(),
	'severity_below_min' => array(),
	'proof_non_unprovable_when_parity_not_pass' => array(),
);

$chunk = 2000;
$lastId = 0;

$requiredMinSeverityByEffective = array(
	'UNPROVABLE' => 'CRITICAL',
	'VIOLATION' => 'CRITICAL',
	'ERROR' => 'HIGH',
	'PASS' => 'INFO',
);

while (true) {
	$sql = "SELECT id, parity_status, proof_status, severity FROM {$escTable}";
	$where = array();
	$params2 = array();
	$types = '';

	$where[] = 'id > ?';
	$params2[] = $lastId;
	$types .= 'i';

	if ($sinceClause !== '') {
		$where[] = 'created_at >= ?';
		$params2[] = (string)$sinceParam;
		$types .= 's';
	}

	$sql .= ' WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC LIMIT ' . (int)$chunk;
	$stmt = $mysqli->prepare($sql);
	if (!$stmt) {
		fwrite(STDERR, "[audit_shadow_semantic_legacy][error] scan_prepare_failed\n");
		exit(1);
	}
	$stmt->bind_param($types, ...$params2);
	$stmt->execute();
	$res = $stmt->get_result();
	$rows = 0;
	while ($res && ($row = $res->fetch_assoc())) {
		$rows++;
		$id = isset($row['id']) ? (int)$row['id'] : 0;
		if ($id > $lastId) {
			$lastId = $id;
		}

		$parityRaw = isset($row['parity_status']) ? (string)$row['parity_status'] : '';
		$proofRaw = isset($row['proof_status']) ? (string)$row['proof_status'] : '';
		$sevRaw = isset($row['severity']) ? (string)$row['severity'] : '';

		$parityNorm = $parityRaw !== '' ? ShadowGovernanceSemantics::normalizeStatus($parityRaw) : '';
		$proofNorm = $proofRaw !== '' ? ShadowGovernanceSemantics::normalizeStatus($proofRaw) : '';

		$parityRank = $parityNorm !== '' ? ShadowGovernanceSemantics::statusRank($parityNorm) : null;
		$proofRank = $proofNorm !== '' ? ShadowGovernanceSemantics::statusRank($proofNorm) : null;

		if ($parityNorm !== '' && $parityRank === null) {
			$scan['unknown_parity_status']++;
			if (count($samples['unknown_parity_status']) < 10) {
				$samples['unknown_parity_status'][] = array('id' => $id, 'parity_status' => $parityRaw);
			}
		}
		if ($proofNorm !== '' && $proofRank === null) {
			$scan['unknown_proof_status']++;
			if (count($samples['unknown_proof_status']) < 10) {
				$samples['unknown_proof_status'][] = array('id' => $id, 'proof_status' => $proofRaw);
			}
		}

		$effective = '';
		if ($parityNorm === '') {
			$effective = 'UNPROVABLE';
		} elseif ($parityNorm !== 'PASS') {
			$effective = $parityNorm;
		} else {
			$effective = ($proofNorm !== '') ? $proofNorm : 'UNPROVABLE';
		}

		$minSev = isset($requiredMinSeverityByEffective[$effective]) ? $requiredMinSeverityByEffective[$effective] : 'CRITICAL';
		$minRank = ShadowGovernanceSemantics::severityRank($minSev);
		$sevRank = ShadowGovernanceSemantics::severityRank($sevRaw);
		if ($sevRank === null) {
			$scan['unknown_severity']++;
			if (count($samples['unknown_severity']) < 10) {
				$samples['unknown_severity'][] = array('id' => $id, 'severity' => $sevRaw);
			}
		} elseif ($minRank !== null && $sevRank < $minRank) {
			$scan['severity_below_min']++;
			if (count($samples['severity_below_min']) < 10) {
				$samples['severity_below_min'][] = array(
					'id' => $id,
					'parity_status' => $parityRaw,
					'proof_status' => $proofRaw,
					'effective_status' => $effective,
					'severity' => $sevRaw,
					'min_expected_severity' => $minSev,
				);
			}
		}

		if ($parityNorm !== '' && $parityNorm !== 'PASS') {
			if ($proofNorm !== '' && $proofNorm !== 'UNPROVABLE') {
				$scan['proof_non_unprovable_when_parity_not_pass']++;
				if (count($samples['proof_non_unprovable_when_parity_not_pass']) < 10) {
					$samples['proof_non_unprovable_when_parity_not_pass'][] = array(
						'id' => $id,
						'parity_status' => $parityRaw,
						'proof_status' => $proofRaw,
					);
				}
			}
		}
	}
	$stmt->close();
	if ($rows === 0) {
		break;
	}
}

echo "[audit_shadow_semantic_legacy] scan_summary=" . json_encode($scan) . "\n";
foreach ($samples as $k => $arr) {
	if (!empty($arr)) {
		echo "[audit_shadow_semantic_legacy] sample {$k}=" . json_encode($arr) . "\n";
	}
}

$mysqli->close();

echo "[audit_shadow_semantic_legacy] done\n";
