<?php

/**
 * Bootstrap missing LABORATORY billing dispositions
 *
 * Usage:
 *   php scripts/bootstrap_lab_dispositions.php [limit]
 *
 * Notes:
 * - Inserts a BOOTSTRAP -> NORMAL_BILLABLE disposition for any LABORATORY billing_transaction
 *   that has no billing_dispositions history.
 * - Uses a direct, idempotent SQL insert (INSERT...WHERE NOT EXISTS). Runtime correctness does not
 *   depend on this script; it's a historical repair tool.
 */

$limit = 500;
if (isset($argv[1])) {
	$limit = (int)$argv[1];
	if ($limit <= 0) $limit = 500;
}

define('BASEPATH', __DIR__);
if (!defined('ENVIRONMENT')) {
	define('ENVIRONMENT', 'development');
}

$root = realpath(__DIR__ . DIRECTORY_SEPARATOR . '..');
if ($root === false) {
	fwrite(STDERR, "[bootstrap_lab_dispositions][error] cannot_resolve_project_root\n");
	exit(1);
}

$databasePath = $root . DIRECTORY_SEPARATOR . 'application' . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
if (!file_exists($databasePath)) {
	fwrite(STDERR, "[bootstrap_lab_dispositions][error] database_config_missing path={$databasePath}\n");
	exit(1);
}

$db = array();
require($databasePath);
if (!isset($db['default']) || !is_array($db['default'])) {
	fwrite(STDERR, "[bootstrap_lab_dispositions][error] database_default_missing\n");
	exit(1);
}

$cfg = $db['default'];
$host = isset($cfg['hostname']) ? (string)$cfg['hostname'] : '';
$user = isset($cfg['username']) ? (string)$cfg['username'] : '';
$pass = isset($cfg['password']) ? (string)$cfg['password'] : '';
$name = isset($cfg['database']) ? (string)$cfg['database'] : '';

if ($host === '' || $user === '' || $name === '') {
	fwrite(STDERR, "[bootstrap_lab_dispositions][error] database_config_incomplete\n");
	exit(1);
}

$mysqli = @new mysqli($host, $user, $pass, $name);
if ($mysqli->connect_errno) {
	fwrite(STDERR, "[bootstrap_lab_dispositions][error] db_connect_failed errno={$mysqli->connect_errno} msg={$mysqli->connect_error}\n");
	exit(1);
}
$mysqli->set_charset('utf8mb4');

$mysqli->query("CREATE TABLE IF NOT EXISTS `billing_dispositions` (
	`disp_id` bigint(20) NOT NULL AUTO_INCREMENT,
	`txn_id` bigint(20) NOT NULL,
	`from_state` varchar(50) DEFAULT NULL,
	`to_state` varchar(50) NOT NULL,
	`reason` varchar(255) DEFAULT NULL,
	`actor_user_id` varchar(25) DEFAULT NULL,
	`source_module` varchar(50) DEFAULT NULL,
	`source_ref` varchar(120) DEFAULT NULL,
	`correlation_id` varchar(80) DEFAULT NULL,
	`approved_by` varchar(25) DEFAULT NULL,
	`approved_at` datetime DEFAULT NULL,
	`created_at` datetime NOT NULL,
	`InActive` tinyint(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (`disp_id`),
	KEY `idx_txn_created` (`txn_id`,`created_at`),
	KEY `idx_txn_disp` (`txn_id`,`disp_id`),
	KEY `idx_to_state` (`to_state`),
	KEY `idx_correlation` (`correlation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$res = array('ok' => true, 'scanned' => 0, 'bootstrapped' => 0, 'errors' => array());

$selSql = "SELECT bt.txn_id
	FROM billing_transactions bt
	LEFT JOIN billing_dispositions bd ON bd.txn_id = bt.txn_id AND bd.InActive = 0
	WHERE bt.InActive = 0 AND bt.department = 'LABORATORY' AND bd.disp_id IS NULL
	ORDER BY bt.txn_id ASC
	LIMIT " . (int)$limit;

$q = $mysqli->query($selSql);
if (!$q) {
	$res['ok'] = false;
	$res['errors'][] = 'select_failed:' . $mysqli->error;
} else {
	$txnIds = array();
	while ($row = $q->fetch_assoc()) {
		$txnIds[] = isset($row['txn_id']) ? (int)$row['txn_id'] : 0;
	}
	$res['scanned'] = count($txnIds);

	$insSql = "INSERT INTO billing_dispositions (txn_id, from_state, to_state, reason, actor_user_id, source_module, source_ref, correlation_id, created_at, InActive)
		SELECT ?, 'BOOTSTRAP', 'NORMAL_BILLABLE', 'AUTO_INITIALIZATION', ?, 'SYSTEM_BOOTSTRAP', 'AUTO_INIT', ?, ?, 0
		FROM DUAL
		WHERE NOT EXISTS (SELECT 1 FROM billing_dispositions WHERE txn_id = ? AND InActive = 0 LIMIT 1)";
	$stmt = $mysqli->prepare($insSql);
	if (!$stmt) {
		$res['ok'] = false;
		$res['errors'][] = 'prepare_failed:' . $mysqli->error;
	} else {
		$actor = 'SYSTEM';
		foreach ($txnIds as $txn_id) {
			if ($txn_id <= 0) continue;
			$cid = 'bootstrap:' . $txn_id . ':' . date('YmdHis') . ':' . mt_rand(1000, 9999);
			$now = date('Y-m-d H:i:s');
			$stmt->bind_param('isssi', $txn_id, $actor, $cid, $now, $txn_id);
			if (!$stmt->execute()) {
				$res['errors'][] = 'txn_id:' . $txn_id . ':insert_failed:' . $stmt->error;
				continue;
			}
			if ($stmt->affected_rows > 0) {
				$res['bootstrapped']++;
			}
		}
		$stmt->close();
	}
}

echo "[bootstrap_lab_dispositions] ok=" . ($res['ok'] ? '1' : '0') . PHP_EOL;
echo "[bootstrap_lab_dispositions] scanned=" . (int)$res['scanned'] . PHP_EOL;
echo "[bootstrap_lab_dispositions] bootstrapped=" . (int)$res['bootstrapped'] . PHP_EOL;

if (is_array($res['errors']) && count($res['errors']) > 0) {
	echo "[bootstrap_lab_dispositions] errors=" . count($res['errors']) . PHP_EOL;
	foreach ($res['errors'] as $e) {
		echo "  - " . $e . PHP_EOL;
	}
}

$mysqli->close();
