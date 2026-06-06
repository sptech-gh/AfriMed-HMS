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

$check = $db->query("SHOW COLUMNS FROM iop_medication LIKE 'prescribed_qty'");
if (!$check || $check->num_rows === 0) {
    fwrite(STDERR, "Missing column iop_medication.prescribed_qty. Run migration 20260815_003 first.\n");
    exit(4);
}

$rows = $db->query("SELECT COUNT(*) AS c FROM iop_medication WHERE InActive=0 AND prescribed_qty IS NULL");
$r = $rows ? $rows->fetch_assoc() : null;
$pending = $r ? (int)$r['c'] : 0;

echo "Pending rows to backfill: {$pending}\n";

if (!$apply) {
    echo "Dry-run only. Re-run with --apply --confirm=YES to execute.\n";
    exit(0);
}

$db->begin_transaction();
try {
    $ok = $db->query("UPDATE iop_medication SET prescribed_qty = total_qty WHERE InActive=0 AND prescribed_qty IS NULL");
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
    exit(5);
}
