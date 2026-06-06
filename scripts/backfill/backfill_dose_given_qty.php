<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$apply = false;
$confirm = null;
foreach ($argv as $a) {
    if ($a === '--apply') $apply = true;
    if (strpos($a, '--confirm=') === 0) $confirm = substr($a, strlen('--confirm='));
}

if ($apply && $confirm !== 'YES') {
    fwrite(STDERR, "Refusing to apply without --confirm=YES\n");
    exit(2);
}

$db = new mysqli('localhost', 'root', '', 'hms_master');
if ($db->connect_error) {
    fwrite(STDERR, "DB connect failed: {$db->connect_error}\n");
    exit(3);
}

$db->set_charset('utf8mb4');

$checkQty = $db->query("SHOW COLUMNS FROM iop_medication_administration LIKE 'dose_given_qty'");
$checkTxt = $db->query("SHOW COLUMNS FROM iop_medication_administration LIKE 'dose_given_text'");
if (!$checkQty || $checkQty->num_rows === 0 || !$checkTxt || $checkTxt->num_rows === 0) {
    fwrite(STDERR, "Missing columns iop_medication_administration.dose_given_qty/dose_given_text. Run migration 20260815_002 first.\n");
    exit(4);
}

$sqlPending = "
    SELECT
        COUNT(*) AS total,
        SUM(CASE WHEN InActive=0 THEN 1 ELSE 0 END) AS active,
        SUM(CASE WHEN InActive=0 AND dose_given IS NOT NULL AND TRIM(dose_given)<>'' AND dose_given_qty IS NULL THEN 1 ELSE 0 END) AS active_needs_backfill,
        SUM(CASE WHEN InActive=0 AND dose_given IS NOT NULL AND TRIM(dose_given)<>'' AND dose_given_qty IS NULL AND TRIM(dose_given) REGEXP '^[0-9]+([.][0-9]+)?$' THEN 1 ELSE 0 END) AS active_numeric_clean
    FROM iop_medication_administration
";

$res = $db->query($sqlPending);
$row = $res ? $res->fetch_assoc() : null;
if (!$row) {
    fwrite(STDERR, "Failed to read counts: {$db->error}\n");
    exit(5);
}

echo "Total rows: {$row['total']}\n";
echo "Active rows: {$row['active']}\n";
echo "Active needing backfill: {$row['active_needs_backfill']}\n";
echo "Active numeric-clean candidates: {$row['active_numeric_clean']}\n";

if (!$apply) {
    echo "Dry-run only. Re-run with --apply --confirm=YES to execute.\n";
    exit(0);
}

$db->begin_transaction();
try {
    $sql = "
        UPDATE iop_medication_administration
        SET
            dose_given_text = dose_given,
            dose_given_qty = CAST(dose_given AS DECIMAL(10,3))
        WHERE
            InActive=0
            AND dose_given_qty IS NULL
            AND dose_given IS NOT NULL
            AND TRIM(dose_given)<>''
            AND TRIM(dose_given) REGEXP '^[0-9]+([.][0-9]+)?$'
    ";
    $ok = $db->query($sql);
    if (!$ok) {
        throw new Exception($db->error);
    }
    $affected = $db->affected_rows;
    $db->commit();
    echo "Backfill complete. Rows updated: {$affected}\n";
    exit(0);
} catch (Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "Backfill failed, rolled back: {$e->getMessage()}\n");
    exit(6);
}
