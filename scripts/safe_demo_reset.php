<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from CLI.\n");
    exit(1);
}

function arg_value(string $name, ?string $default = null): ?string
{
    global $argv;

    foreach ($argv as $a) {
        if (strpos($a, $name . '=') === 0) {
            return substr($a, strlen($name) + 1);
        }
    }

    return $default;
}

function has_flag(string $flag): bool
{
    global $argv;
    return in_array($flag, $argv, true);
}

function fail(string $msg, int $code = 1): void
{
    fwrite(STDERR, $msg . "\n");
    exit($code);
}

function table_exists(mysqli $db, string $table): bool
{
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?"
    );
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $table);
    if (!$stmt->execute()) {
        $stmt->close();
        return false;
    }

    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();

    return ((int)$cnt > 0);
}

function query_scalar_int(mysqli $db, string $sql): int
{
    $res = $db->query($sql);
    if (!$res) {
        throw new RuntimeException("Query failed: {$db->error}");
    }
    $row = $res->fetch_row();
    $res->free();
    return (int)($row[0] ?? 0);
}

function exec_sql(mysqli $db, string $sql): void
{
    if (!$db->query($sql)) {
        throw new RuntimeException("SQL failed: {$db->error} | {$sql}");
    }
}

function parse_table_list(string $list): array
{
    $rows = preg_split('/\R/', trim($list));
    $out = [];
    foreach ($rows as $r) {
        $t = trim($r);
        if ($t === '' || strpos($t, '#') === 0) {
            continue;
        }
        $out[] = $t;
    }
    return array_values(array_unique($out));
}

$confirm = (string)arg_value('--confirm', '');
$dryRun = has_flag('--dry-run');
$reportPath = (string)arg_value('--report', '');

if ($confirm !== 'I_UNDERSTAND_DEMO_RESET_WILL_DELETE_DATA') {
    fail(
        "Refusing to run without explicit confirmation.\n" .
        "Re-run with: --confirm=I_UNDERSTAND_DEMO_RESET_WILL_DELETE_DATA\n" .
        "Optional: --dry-run --report=C:/path/to/report.txt"
    );
}

$dbHost = getenv('DB_HOSTNAME') !== false ? (string)getenv('DB_HOSTNAME') : 'localhost';
$dbUser = getenv('DB_USERNAME') !== false ? (string)getenv('DB_USERNAME') : 'root';
$dbPass = getenv('DB_PASSWORD') !== false ? (string)getenv('DB_PASSWORD') : '';
$dbName = getenv('DB_DATABASE') !== false ? (string)getenv('DB_DATABASE') : 'hms_master';

$mysqli = mysqli_init();
if (!$mysqli) {
    fail('Failed to init mysqli.');
}

$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);
if (!$mysqli->real_connect($dbHost, $dbUser, $dbPass, $dbName)) {
    fail('DB connect failed: ' . $mysqli->connect_error);
}

if (!$mysqli->set_charset('utf8mb4')) {
    fail('Failed to set charset utf8mb4.');
}

$sentinelTables = [
    'patient_personal_info',
    'patient_details_iop',
    'billing_master',
    'billing_transactions',
    'iop_laboratory',
    'iop_medication',
    'radiology_orders',
    'ci_sessions',
    'shadow_audit_log',
];

$cascadeParentList = <<<'TXT'
# FK parent tables (DELETE triggers ON DELETE CASCADE)
billing_master
walkin_clients
walkin_orders
consumable_orders
clinical_events
nhis_claims
TXT;

$truncateList = <<<'TXT'
# Safe-to-truncate transactional tables (no FK constraints referencing them)
ci_sessions

# Patient core / encounter
authentication_audit
patient_personal_info
patient_details_iop
patient_admissions
patient_appointment
patient_attachment
patient_consent
patient_encounters
patient_allergies
patient_risk_flags
patient_financial_ledger
patient_history_access_log
patient_clinical_history
patient_clinical_history_audit

# OPD/IPD workflow
iop_billing
iop_billing_t
iop_receipt
iop_room_charge
iop_room_transfer
iop_progress_note
iop_complaints
iop_diagnosis
iop_vital_parameters
iop_vital_parameters_extra
iop_discharge_summary
iop_discharge_audit
iop_intake_record
iop_output_record
iop_nurse_notes
opd_admission_queue
opd_registration_override_log
opd_status_audit

# Billing (SSOT/legacy)
billing_transactions
billing_queue
billing_audit_log
cashier_payment_log
cashier_refund_log
payment_breakdown
payment_transactions
outstanding_balances
financial_ledger
financial_audit_log
smart_billing_audit
smart_billing_ledger
waiver_requests

# Lab / imaging / pharmacy
iop_laboratory
iop_laboratory_workflow
iop_lab_billing
iop_sonography_charge
iop_sonography_report_draft
iop_medication
iop_medication_administration
iop_medication_diagnosis
radiology_orders
radiology_results
pharmacy_billing_queue
pharmacy_audit_log
pharmacy_stock_adjustment
pharmacy_stock_requests
pharmacy_reconciliation_log
prescription_locks
prescription_safety_alerts
prescription_workflow
prescription_status_audit

# Governance / audit / logs
shadow_audit_log
service_gate_audit
system_audit_log
security_audit_log
privilege_audit_log
privilege_refresh_tracker
login_attempts
logfile
order_master
order_reconciliation_log
order_state_audit
TXT;

$updateStatements = [
    "UPDATE room_beds SET nStatus = 'Vacant', patient_no = NULL WHERE InActive = 0",
];

$cascadeParents = parse_table_list($cascadeParentList);
$truncateTables = parse_table_list($truncateList);

$report = [];
$report[] = 'HMS Safe Demo Reset';
$report[] = 'DB_HOSTNAME=' . $dbHost;
$report[] = 'DB_DATABASE=' . $dbName;
$report[] = 'DRY_RUN=' . ($dryRun ? 'YES' : 'NO');
$report[] = 'START=' . gmdate('c');

try {
    if (!table_exists($mysqli, 'users')) {
        throw new RuntimeException('Sanity check failed: users table not found in target database.');
    }

    $report[] = '';
    $report[] = '[Pre-counts]';
    foreach ($sentinelTables as $t) {
        if (!table_exists($mysqli, $t)) {
            $report[] = $t . '=MISSING';
            continue;
        }
        $cnt = query_scalar_int($mysqli, "SELECT COUNT(*) FROM `{$t}`");
        $report[] = $t . '=' . $cnt;
    }

    $ops = [];

    foreach ($cascadeParents as $t) {
        if (!table_exists($mysqli, $t)) {
            $ops[] = "SKIP DELETE (missing): {$t}";
            continue;
        }
        $ops[] = "DELETE: {$t}";
        if (!$dryRun) {
            exec_sql($mysqli, "DELETE FROM `{$t}`");
        }
    }

    foreach ($truncateTables as $t) {
        if (in_array($t, $cascadeParents, true)) {
            continue;
        }
        if (!table_exists($mysqli, $t)) {
            $ops[] = "SKIP TRUNCATE (missing): {$t}";
            continue;
        }
        $ops[] = "TRUNCATE: {$t}";
        if (!$dryRun) {
            exec_sql($mysqli, "TRUNCATE TABLE `{$t}`");
        }
    }

    foreach ($updateStatements as $sql) {
        $ops[] = "UPDATE: {$sql}";
        if (!$dryRun) {
            exec_sql($mysqli, $sql);
        }
    }

    $report[] = '';
    $report[] = '[Operations]';
    foreach ($ops as $op) {
        $report[] = $op;
    }

    $report[] = '';
    $report[] = '[Post-counts]';
    foreach ($sentinelTables as $t) {
        if (!table_exists($mysqli, $t)) {
            $report[] = $t . '=MISSING';
            continue;
        }
        $cnt = query_scalar_int($mysqli, "SELECT COUNT(*) FROM `{$t}`");
        $report[] = $t . '=' . $cnt;
    }

    $report[] = '';
    $report[] = 'END=' . gmdate('c');

    $text = implode("\n", $report) . "\n";
    echo $text;

    if ($reportPath !== '') {
        file_put_contents($reportPath, $text);
    }

    exit(0);
} catch (Throwable $e) {
    $report[] = '';
    $report[] = '[ERROR]';
    $report[] = $e->getMessage();
    $text = implode("\n", $report) . "\n";
    echo $text;

    if ($reportPath !== '') {
        file_put_contents($reportPath, $text);
    }

    exit(2);
}


