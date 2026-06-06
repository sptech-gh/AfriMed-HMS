<?php

if (php_sapi_name() !== 'cli') {
	http_response_code(403);
	echo "CLI only\n";
	exit(1);
}

$opts = getopt('', array('host::', 'user::', 'pass::', 'db::'));
$host = isset($opts['host']) ? (string)$opts['host'] : 'localhost';
$user = isset($opts['user']) ? (string)$opts['user'] : 'root';
$pass = isset($opts['pass']) ? (string)$opts['pass'] : '';
$dbName = isset($opts['db']) ? (string)$opts['db'] : 'hms_master';

$db = @new mysqli($host, $user, $pass, $dbName);
if ($db->connect_error) {
	fwrite(STDERR, "DB connect failed: {$db->connect_error}\n");
	exit(2);
}
$db->set_charset('utf8mb4');

function col_info(mysqli $db, $table, $column)
{
	$t = $db->real_escape_string($table);
	$c = $db->real_escape_string($column);
	$sql = "SELECT COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA, COLUMN_KEY, CHARACTER_SET_NAME, COLLATION_NAME, NUMERIC_PRECISION, NUMERIC_SCALE " .
		"FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'";
	$res = $db->query($sql);
	$row = $res ? $res->fetch_assoc() : null;
	return $row ?: null;
}

function idx_info(mysqli $db, $table, $column)
{
	$t = $db->real_escape_string($table);
	$c = $db->real_escape_string($column);
	$sql = "SELECT INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}' ORDER BY INDEX_NAME, SEQ_IN_INDEX";
	$res = $db->query($sql);
	$out = array();
	if ($res) {
		while ($r = $res->fetch_assoc()) {
			$out[] = $r;
		}
		$res->free();
	}
	return $out;
}

$cols = array('qty', 'rate', 'amount');
$okAll = true;

foreach ($cols as $col) {
	$info = col_info($db, 'iop_billing_t', $col);
	if (!$info) {
		$okAll = false;
		echo "MISSING iop_billing_t.$col\n";
		continue;
	}

	$idx = idx_info($db, 'iop_billing_t', $col);

	echo "COLUMN iop_billing_t.$col\n";
	echo "  COLUMN_TYPE=" . $info['COLUMN_TYPE'] . "\n";
	echo "  IS_NULLABLE=" . $info['IS_NULLABLE'] . "\n";
	echo "  COLUMN_DEFAULT=" . (is_null($info['COLUMN_DEFAULT']) ? 'NULL' : $info['COLUMN_DEFAULT']) . "\n";
	echo "  EXTRA=" . $info['EXTRA'] . "\n";
	echo "  COLUMN_KEY=" . $info['COLUMN_KEY'] . "\n";
	if (!is_null($info['NUMERIC_PRECISION'])) {
		echo "  NUMERIC_PRECISION=" . $info['NUMERIC_PRECISION'] . "\n";
	}
	if (!is_null($info['NUMERIC_SCALE'])) {
		echo "  NUMERIC_SCALE=" . $info['NUMERIC_SCALE'] . "\n";
	}
	if (!empty($idx)) {
		echo "  INDEXES=" . json_encode($idx) . "\n";
	} else {
		echo "  INDEXES=[]\n";
	}
}

exit($okAll ? 0 : 3);
