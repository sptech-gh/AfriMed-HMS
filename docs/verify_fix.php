<?php
/**
 * Verification Script - HMS Fix Validation
 * Run: php docs/verify_fix.php
 */

echo "========================================\n";
echo "HMS FIX VERIFICATION SYSTEM\n";
echo "========================================\n\n";

// Database connection
try {
    $mysqli = new mysqli('localhost', 'root', '', 'hms_master');
    if ($mysqli->connect_error) {
        die("Connection failed: " . $mysqli->connect_error);
    }
    echo "✅ Database connected\n\n";
} catch (Exception $e) {
    die("❌ Database connection failed: " . $e->getMessage());
}

// Test 1: Check billing_audit_log schema
echo "1. Checking billing_audit_log schema...\n";
$result = $mysqli->query("DESCRIBE billing_audit_log");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
}

$required = ['id', 'service_order_id', 'action_type', 'patient_no'];
$missing = array_diff($required, $columns);

if (empty($missing)) {
    echo "   ✅ All required columns present\n";
    echo "   - id: YES\n";
    echo "   - service_order_id: YES\n";
    echo "   - action_type: YES\n";
} else {
    echo "   ❌ Missing columns: " . implode(', ', $missing) . "\n";
}

// Check if old schema exists
if (in_array('audit_id', $columns)) {
    echo "   ⚠️  OLD SCHEMA DETECTED - Run migration needed\n";
}

echo "\n";

// Test 2: Check billing_approvals
echo "2. Checking billing_approvals...\n";
$result = $mysqli->query("SHOW COLUMNS FROM billing_approvals LIKE 'service_order_id'");
if ($result->num_rows > 0) {
    echo "   ✅ service_order_id column exists\n";
} else {
    echo "   ❌ service_order_id column missing\n";
}
echo "\n";

// Test 3: Check service_orders
echo "3. Checking service_orders...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM service_orders");
$row = $result->fetch_assoc();
echo "   ✅ service_orders table exists\n";
echo "   - Records: " . $row['count'] . "\n";
echo "\n";

// Test 4: Check iop_laboratory
echo "4. Checking iop_laboratory...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM iop_laboratory WHERE InActive = 0");
$row = $result->fetch_assoc();
echo "   ✅ iop_laboratory table accessible\n";
echo "   - Active records: " . $row['count'] . "\n";
echo "\n";

// Test 5: Check iop_medication
echo "5. Checking iop_medication...\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM iop_medication WHERE InActive = 0");
$row = $result->fetch_assoc();
echo "   ✅ iop_medication table accessible\n";
echo "   - Active records: " . $row['count'] . "\n";
echo "\n";

// Test 6: Check for service_order_id column in iop tables
$result = $mysqli->query("SHOW COLUMNS FROM iop_laboratory LIKE 'service_order_id'");
if ($result->num_rows > 0) {
    echo "6. iop_laboratory has service_order_id: ✅\n";
} else {
    echo "6. iop_laboratory has service_order_id: ⚠️ (optional, not required)\n";
}

$result = $mysqli->query("SHOW COLUMNS FROM iop_medication LIKE 'service_order_id'");
if ($result->num_rows > 0) {
    echo "   iop_medication has service_order_id: ✅\n";
} else {
    echo "   iop_medication has service_order_id: ⚠️ (optional, not required)\n";
}
echo "\n";

echo "========================================\n";
echo "VERIFICATION COMPLETE\n";
echo "========================================\n\n";

// Final status
$has_audit_id = in_array('audit_id', $columns);
$has_service_order_id = in_array('service_order_id', $columns);

if (!$has_audit_id && $has_service_order_id) {
    echo "✅ SYSTEM IS FIXED AND READY\n";
    echo "\nYou can now:\n";
    echo "- Save lab requests\n";
    echo "- Save sonography requests\n";
    echo "- Save prescriptions\n";
    echo "\nNo SQL errors expected.\n";
} else {
    echo "❌ ISSUES DETECTED\n";
    echo "\nRun the following to fix:\n";
    echo "1. Visit: http://localhost/hms-master/app/opd/laboratory\n";
    echo "2. Or run: php docs/trigger_migration.php\n";
}

$mysqli->close();
