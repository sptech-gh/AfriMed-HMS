<?php

if (php_sapi_name() !== 'cli') {
	echo "CLI only\n";
	exit(1);
}

$opts = getopt('', array('host::', 'user::', 'pass::', 'db::', 'out::'));
$host = isset($opts['host']) ? (string)$opts['host'] : 'localhost';
$user = isset($opts['user']) ? (string)$opts['user'] : 'root';
$pass = isset($opts['pass']) ? (string)$opts['pass'] : '';
$dbName = isset($opts['db']) ? (string)$opts['db'] : 'hms_master';
$outPath = isset($opts['out']) ? (string)$opts['out'] : '';

$mysqli = @new mysqli($host, $user, $pass, $dbName);
if ($mysqli->connect_error) {
	fwrite(STDERR, "DB connect error: " . $mysqli->connect_error . "\n");
	exit(2);
}
$mysqli->set_charset('utf8');

function scalar_query($mysqli, $sql)
{
	$res = $mysqli->query($sql);
	if (!$res) {
		return array('ok' => false, 'error' => $mysqli->error);
	}
	$row = $res->fetch_row();
	$res->free();
	return array('ok' => true, 'value' => ($row ? $row[0] : null));
}

function has_column($mysqli, $table, $column)
{
	$table = $mysqli->real_escape_string($table);
	$column = $mysqli->real_escape_string($column);
	$db = $mysqli->real_escape_string($mysqli->query('SELECT DATABASE()')->fetch_row()[0]);
	$sql = "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = '$db' AND TABLE_NAME = '$table' AND COLUMN_NAME = '$column'";
	$r = scalar_query($mysqli, $sql);
	return ($r['ok'] && (int)$r['value'] > 0);
}

$snapshot = array(
	'ts' => date('c'),
	'db' => $dbName,
	'checks' => array(),
	'errors' => array(),
);

$checks = array();

$checks['invoice_lines_total'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_billing_t WHERE InActive = 0");
$checks['invoice_lines_fractional_qty'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_billing_t WHERE InActive = 0 AND qty IS NOT NULL AND qty <> FLOOR(qty)");
$checks['invoice_lines_amount_mismatch'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_billing_t WHERE InActive = 0 AND qty IS NOT NULL AND rate IS NOT NULL AND amount IS NOT NULL AND ABS((qty * rate) - amount) > 0.01");

if ($mysqli->query("SHOW TABLES LIKE 'billing_queue'")->num_rows > 0) {
	$checks['billing_queue_total'] = scalar_query($mysqli, "SELECT COUNT(*) FROM billing_queue WHERE InActive = 0");
	$checks['billing_queue_fractional_qty'] = scalar_query($mysqli, "SELECT COUNT(*) FROM billing_queue WHERE InActive = 0 AND quantity IS NOT NULL AND quantity <> FLOOR(quantity)");
	if (has_column($mysqli, 'billing_queue', 'quantity_semantics_version')) {
		$checks['billing_queue_semver_null'] = scalar_query($mysqli, "SELECT COUNT(*) FROM billing_queue WHERE InActive = 0 AND quantity_semantics_version IS NULL");
	}
}

if ($mysqli->query("SHOW TABLES LIKE 'pharmacy_billing_queue'")->num_rows > 0) {
	$checks['pharmacy_billing_queue_total'] = scalar_query($mysqli, "SELECT COUNT(*) FROM pharmacy_billing_queue WHERE InActive = 0");
	$checks['pharmacy_billing_queue_fractional_qty'] = scalar_query($mysqli, "SELECT COUNT(*) FROM pharmacy_billing_queue WHERE InActive = 0 AND quantity IS NOT NULL AND quantity <> FLOOR(quantity)");
	if (has_column($mysqli, 'pharmacy_billing_queue', 'quantity_semantics_version')) {
		$checks['pharmacy_billing_queue_semver_null'] = scalar_query($mysqli, "SELECT COUNT(*) FROM pharmacy_billing_queue WHERE InActive = 0 AND quantity_semantics_version IS NULL");
	}
}

if ($mysqli->query("SHOW TABLES LIKE 'iop_medication'")->num_rows > 0) {
	$checks['iop_medication_total'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication WHERE InActive = 0");
	if (has_column($mysqli, 'iop_medication', 'prescribed_qty')) {
		$checks['iop_medication_prescribed_qty_null'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication WHERE InActive = 0 AND prescribed_qty IS NULL");
	}
	if (has_column($mysqli, 'iop_medication', 'quantity_semantics_version')) {
		$checks['iop_medication_semver_null'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication WHERE InActive = 0 AND quantity_semantics_version IS NULL");
	}
}

if ($mysqli->query("SHOW TABLES LIKE 'iop_medication_administration'")->num_rows > 0) {
	$checks['med_admin_total'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication_administration WHERE InActive = 0");
	$checks['med_admin_dose_given_non_numeric'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication_administration WHERE InActive = 0 AND dose_given IS NOT NULL AND TRIM(dose_given) <> '' AND dose_given NOT REGEXP '^[0-9]+([.][0-9]+)?$'");
	if (has_column($mysqli, 'iop_medication_administration', 'dose_given_qty')) {
		$checks['med_admin_dose_given_qty_null'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication_administration WHERE InActive = 0 AND dose_given_qty IS NULL");
	}
	if (has_column($mysqli, 'iop_medication_administration', 'quantity_semantics_version')) {
		$checks['med_admin_semver_null'] = scalar_query($mysqli, "SELECT COUNT(*) FROM iop_medication_administration WHERE InActive = 0 AND quantity_semantics_version IS NULL");
	}
}

foreach ($checks as $k => $r) {
	if (is_array($r) && isset($r['ok']) && !$r['ok']) {
		$snapshot['errors'][$k] = $r['error'];
		$snapshot['checks'][$k] = null;
	} else {
		$snapshot['checks'][$k] = is_array($r) ? $r['value'] : $r;
	}
}

$json = json_encode($snapshot, JSON_PRETTY_PRINT);
if ($json === false) {
	fwrite(STDERR, "JSON encode error\n");
	exit(3);
}

if ($outPath !== '') {
	$dir = dirname($outPath);
	if (!is_dir($dir)) {
		@mkdir($dir, 0777, true);
	}
	if (@file_put_contents($outPath, $json . "\n") === false) {
		fwrite(STDERR, "Failed to write: $outPath\n");
		exit(4);
	}
}

echo $json . "\n";
