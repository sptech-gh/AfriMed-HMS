<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = getenv('DB_HOSTNAME') !== false ? getenv('DB_HOSTNAME') : 'localhost';
$user = getenv('DB_USERNAME') !== false ? getenv('DB_USERNAME') : 'root';
$pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '';
$db   = getenv('DB_DATABASE') !== false ? getenv('DB_DATABASE') : 'hms_master';

$patient_no = isset($_GET['patient_no']) ? trim((string)$_GET['patient_no']) : '';
$iop_id = isset($_GET['iop_id']) ? trim((string)$_GET['iop_id']) : '';

$m = new mysqli($host, $user, $pass, $db);
if ($m->connect_error) {
	header('Content-Type: text/plain');
	echo 'DB connect error: ' . $m->connect_error;
	exit;
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function fetch_all($m, $sql, $params = []) {
	$stmt = $m->prepare($sql);
	if (!$stmt) return [false, 'prepare_failed', $m->error];
	if ($params) {
		$types = '';
		$vals = [];
		foreach ($params as $p) {
			if (is_int($p)) $types .= 'i';
			elseif (is_float($p)) $types .= 'd';
			else $types .= 's';
			$vals[] = $p;
		}
		$stmt->bind_param($types, ...$vals);
	}
	if (!$stmt->execute()) return [false, 'execute_failed', $stmt->error];
	$res = $stmt->get_result();
	if (!$res) return [false, 'no_result', $stmt->error];
	$rows = [];
	while ($row = $res->fetch_assoc()) $rows[] = $row;
	return [true, $rows, null];
}

function has_table($m, $db, $table) {
	list($ok, $rows,) = fetch_all($m, 'SELECT COUNT(1) c FROM information_schema.tables WHERE table_schema=? AND table_name=?', [$db, $table]);
	if (!$ok) return false;
	return ((int)$rows[0]['c']) > 0;
}

function dump_table($title, $rows) {
	echo '<h3>' . h($title) . '</h3>';
	if (!is_array($rows) || count($rows) === 0) {
		echo '<pre>(no rows)</pre>';
		return;
	}
	echo '<pre>' . h(json_encode($rows, JSON_PRETTY_PRINT)) . '</pre>';
}

?><!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title>Billing Queue Debug</title>
	<style>
		body{font-family:Arial, sans-serif; margin:20px;}
		pre{background:#f5f5f5; padding:10px; overflow:auto;}
		.bad{color:#b00020;}
		.ok{color:#0b7a0b;}
	</style>
</head>
<body>
<h2>Billing Queue / Sonography Debug</h2>
<p><strong>DB:</strong> <?php echo h($db); ?> | <strong>patient_no:</strong> <?php echo h($patient_no); ?> | <strong>iop_id:</strong> <?php echo h($iop_id); ?></p>

<?php
list($okCols, $subCols,) = fetch_all(
	$m,
	'SELECT table_name, column_name, is_nullable, column_default, column_type FROM information_schema.columns WHERE table_schema=? AND column_name IN ("sub_total","transaction_type") ORDER BY table_name, column_name',
	[$db]
);
dump_table('Schema scan for required columns (sub_total / transaction_type)', $okCols ? $subCols : []);

if ($patient_no !== '' && $iop_id !== '') {
	if (has_table($m, $db, 'billing_queue')) {
		list($okQ, $rowsQ,) = fetch_all(
			$m,
			'SELECT queue_id,iop_id,patient_no,item_type,item_id,item_name,quantity,unit_price,total_amount,discount_amount,net_amount,payer_type,status,source_module,source_ref,requested_by,requested_at,created_at,invoice_no,InActive FROM billing_queue WHERE InActive=0 AND patient_no=? AND iop_id=? ORDER BY queue_id DESC LIMIT 50',
			[$patient_no, $iop_id]
		);
		dump_table('billing_queue rows for patient/visit (last 50)', $okQ ? $rowsQ : []);
	} else {
		echo '<p class="bad">billing_queue table not found</p>';
	}

	if (has_table($m, $db, 'iop_laboratory')) {
		list($okL, $rowsL,) = fetch_all(
			$m,
			'SELECT io_lab_id,iop_id,laboratory_id,category_id,laboratory_text,doctor,requested_by,payer_type,nhis_flag,dDateTime,InActive FROM iop_laboratory WHERE InActive=0 AND iop_id=? ORDER BY io_lab_id DESC LIMIT 30',
			[$iop_id]
		);
		dump_table('iop_laboratory rows for visit (last 30)', $okL ? $rowsL : []);
	} else {
		echo '<p class="bad">iop_laboratory table not found</p>';
	}

	if (has_table($m, $db, 'iop_sonography_charge')) {
		list($okS, $rowsS,) = fetch_all(
			$m,
			'SELECT charge_id,io_lab_id,iop_id,patient_no,encounter_type,scan_item_id,bill_particular_id,item_name,rate_amount,quantity,status,invoice_no,detail_id,created_at,created_by,InActive FROM iop_sonography_charge WHERE InActive=0 AND iop_id=? ORDER BY charge_id DESC LIMIT 30',
			[$iop_id]
		);
		dump_table('iop_sonography_charge rows for visit (last 30)', $okS ? $rowsS : []);
	} else {
		echo '<p class="bad">iop_sonography_charge table not found</p>';
	}
} else {
	echo '<p>Provide <code>?patient_no=000065&iop_id=OP000014</code> to see visit-specific data.</p>';
}
?>
</body>
</html>
