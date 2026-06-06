<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "CLI only\n";
    exit(1);
}

$db = new mysqli('localhost', 'root', '', 'hms_master');
if ($db->connect_error) {
    fwrite(STDERR, "DB connect failed: {$db->connect_error}\n");
    exit(2);
}
$db->set_charset('utf8mb4');

function col_type(mysqli $db, $table, $column)
{
    $t = $db->real_escape_string($table);
    $c = $db->real_escape_string($column);
    $sql = "SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$t}' AND COLUMN_NAME = '{$c}'";
    $res = $db->query($sql);
    $row = $res ? $res->fetch_assoc() : null;
    return $row ? (string)$row['COLUMN_TYPE'] : null;
}

function require_col(mysqli $db, $table, $column)
{
    $t = col_type($db, $table, $column);
    if ($t === null) {
        return array('ok' => false, 'type' => null);
    }
    return array('ok' => true, 'type' => $t);
}

$checks = array(
    array('table' => 'iop_billing_t', 'col' => 'qty', 'expect' => 'decimal'),
    array('table' => 'iop_billing_t', 'col' => 'rate', 'expect' => 'decimal'),
    array('table' => 'iop_billing_t', 'col' => 'amount', 'expect' => 'decimal'),
    array('table' => 'iop_medication_administration', 'col' => 'dose_given_qty', 'expect' => 'decimal'),
    array('table' => 'iop_medication_administration', 'col' => 'dose_given_text', 'expect' => 'varchar'),
    array('table' => 'iop_medication', 'col' => 'prescribed_qty', 'expect' => 'decimal'),
    array('table' => 'medicine_drug_name', 'col' => 'dispense_unit_id', 'expect' => 'int'),
    array('table' => 'medicine_drug_name', 'col' => 'stock_base_unit_id', 'expect' => 'int'),
    array('table' => 'medicine_drug_name', 'col' => 'billable_unit_id', 'expect' => 'int'),
    array('table' => 'medicine_drug_name', 'col' => 'pack_to_base_factor', 'expect' => 'decimal'),
    array('table' => 'billing_queue', 'col' => 'quantity_semantics_version', 'expect' => 'tinyint'),
    array('table' => 'pharmacy_billing_queue', 'col' => 'quantity_semantics_version', 'expect' => 'tinyint'),
    array('table' => 'iop_billing_t', 'col' => 'quantity_semantics_version', 'expect' => 'tinyint'),
    array('table' => 'iop_medication_administration', 'col' => 'quantity_semantics_version', 'expect' => 'tinyint'),
    array('table' => 'iop_medication', 'col' => 'quantity_semantics_version', 'expect' => 'tinyint'),
);

$okAll = true;
foreach ($checks as $ch) {
    $r = require_col($db, $ch['table'], $ch['col']);
    if (!$r['ok']) {
        $okAll = false;
        echo "MISSING {$ch['table']}.{$ch['col']}\n";
        continue;
    }
    $type = strtolower((string)$r['type']);
    $expect = strtolower((string)$ch['expect']);
    $ok = (strpos($type, $expect) === 0);
    if (!$ok) {
        $okAll = false;
        echo "TYPE_MISMATCH {$ch['table']}.{$ch['col']} actual={$r['type']} expected_prefix={$ch['expect']}\n";
    } else {
        echo "OK {$ch['table']}.{$ch['col']} type={$r['type']}\n";
    }
}

exit($okAll ? 0 : 3);
