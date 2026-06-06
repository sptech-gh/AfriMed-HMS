<?php
/**
 * HMS Database Reset Script for Fresh Demo
 * 
 * This script:
 * 1. Creates a backup of the current database
 * 2. Clears all patient/transactional data
 * 3. Preserves system configuration, users, rates, masters
 * 
 * Usage: php scripts/reset_for_demo.php
 * Or access via browser (with confirmation)
 */

// Prevent accidental execution
if (php_sapi_name() !== 'cli') {
    if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'YES_RESET_ALL_DATA') {
        echo "<html><head><title>HMS Demo Reset</title></head><body>";
        echo "<h1>⚠️ WARNING: Database Reset</h1>";
        echo "<p>This will <strong>DELETE ALL PATIENT DATA</strong> from the database.</p>";
        echo "<p>The following will be preserved:</p>";
        echo "<ul>";
        echo "<li>One admin user account and roles</li>";
        echo "<li>Billing rates and particulars</li>";
        echo "<li>Medication, Lab, Radiology masters</li>";
        echo "<li>Room/Ward/Floor configuration</li>";
        echo "<li>System settings</li>";
        echo "</ul>";
        echo "<p><a href='?confirm=YES_RESET_ALL_DATA' style='background:red;color:white;padding:10px 20px;text-decoration:none;font-weight:bold;'>CONFIRM RESET</a></p>";
        echo "</body></html>";
        exit;
    }
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'hms_master';

// Backup directory
$backup_dir = dirname(__DIR__) . '/backups';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

echo "========================================\n";
echo "HMS DATABASE RESET FOR DEMO\n";
echo "========================================\n\n";

// Step 1: Create backup
$backup_file = $backup_dir . '/pre_reset_backup_' . date('Y-m-d_His') . '.sql';
echo "[1/3] Creating backup: " . basename($backup_file) . "\n";

$mysqldump = 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe';
if (!file_exists($mysqldump)) {
    // Try to find mysqldump
    $possible_paths = glob('C:\\laragon\\bin\\mysql\\*\\bin\\mysqldump.exe');
    if (!empty($possible_paths)) {
        $mysqldump = $possible_paths[0];
    }
}

$cmd = sprintf(
    '"%s" -u %s %s --single-transaction --routines --triggers > "%s" 2>&1',
    $mysqldump,
    $db_user,
    $db_name,
    $backup_file
);

exec($cmd, $output, $return_code);

if ($return_code !== 0 || !file_exists($backup_file) || filesize($backup_file) < 1000) {
    echo "WARNING: Backup may have failed. Proceeding anyway...\n";
    echo implode("\n", $output) . "\n";
} else {
    echo "Backup created: " . number_format(filesize($backup_file)) . " bytes\n";
}

// Step 2: Connect to database
echo "\n[2/3] Connecting to database...\n";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully.\n";
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage() . "\n");
}

// Step 3: Run reset
echo "\n[3/3] Resetting database...\n";

// Read and execute the SQL script
$sql_file = __DIR__ . '/reset_demo_data.sql';
if (!file_exists($sql_file)) {
    die("Error: SQL script not found at $sql_file\n");
}

$sql = file_get_contents($sql_file);

// Split by statements (simple split, works for our script)
$statements = array_filter(array_map('trim', explode(';', $sql)));

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
$pdo->exec('SET SQL_SAFE_UPDATES = 0');

$success = 0;
$errors = [];

foreach ($statements as $stmt) {
    $clean = (string)$stmt;
    $clean = preg_replace('/^\s*--.*$/m', '', $clean);
    $clean = preg_replace('#/\*.*?\*/#s', '', $clean);
    $clean = trim($clean);

    // Skip empty statements
    if ($clean === '') {
        continue;
    }

    // Skip SET statements (already done)
    if (stripos($clean, 'SET ') === 0) {
        continue;
    }
    
    // Skip SELECT statements
    if (stripos($clean, 'SELECT') === 0) {
        continue;
    }
    
    try {
        $pdo->exec($clean);
        $success++;
        
        // Extract table name for progress
        if (preg_match('/TRUNCATE\s+TABLE\s+`?(\w+)`?/i', $clean, $m)) {
            echo "  Cleared: {$m[1]}\n";
        }
    } catch (PDOException $e) {
        // Table might not exist - that's OK
        if (strpos($e->getMessage(), "doesn't exist") === false) {
            $errors[] = $e->getMessage();
        }
    }
}

echo "\nPruning users (keeping 1 admin user)...\n";

$keepUserId = null;
$keepUsername = getenv('DEMO_KEEP_ADMIN_USERNAME');
if ($keepUsername !== false && trim((string)$keepUsername) !== '') {
    try {
        $q = $pdo->prepare('SELECT user_id FROM users WHERE username = ? AND InActive = 0 LIMIT 1');
        $q->execute(array(trim((string)$keepUsername)));
        $keepUserId = $q->fetchColumn();
    } catch (PDOException $e) {
    }
}

if (!$keepUserId) {
    try {
        $keepUserId = $pdo->query("SELECT u.user_id FROM users u JOIN user_roles r ON r.role_id = u.user_role WHERE u.InActive = 0 AND r.InActive = 0 AND (LOWER(r.module) IN ('super_admin','administrator','admin') OR LOWER(r.role_name) LIKE '%admin%') ORDER BY CAST(u.user_id AS UNSIGNED) ASC LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
    }
}

if (!$keepUserId) {
    try {
        $keepUserId = $pdo->query('SELECT user_id FROM users WHERE InActive = 0 AND user_role IN (1,2) ORDER BY CAST(user_id AS UNSIGNED) ASC LIMIT 1')->fetchColumn();
    } catch (PDOException $e) {
    }
}

if (!$keepUserId) {
    try {
        $keepUserId = $pdo->query('SELECT user_id FROM users WHERE InActive = 0 ORDER BY CAST(user_id AS UNSIGNED) ASC LIMIT 1')->fetchColumn();
    } catch (PDOException $e) {
    }
}

if ($keepUserId) {
    $adminRoleId = null;
    try {
        $adminRoleId = $pdo->query("SELECT role_id FROM user_roles WHERE InActive = 0 AND (LOWER(module) IN ('super_admin','administrator','admin') OR LOWER(role_name) LIKE '%admin%') ORDER BY role_id ASC LIMIT 1")->fetchColumn();
    } catch (PDOException $e) {
    }

    if ($adminRoleId) {
        try {
            $q = $pdo->prepare('UPDATE users SET user_role = ? WHERE user_id = ?');
            $q->execute(array((int)$adminRoleId, (string)$keepUserId));
        } catch (PDOException $e) {
        }
    }

    try {
        $q = $pdo->prepare('DELETE FROM user_privileges WHERE user_id <> ?');
        $q->execute(array((string)$keepUserId));
    } catch (PDOException $e) {
    }

    try {
        $q = $pdo->prepare('DELETE FROM privilege_refresh_tracker WHERE user_id <> ?');
        $q->execute(array((string)$keepUserId));
    } catch (PDOException $e) {
    }

    try {
        $q = $pdo->prepare('DELETE FROM users WHERE user_id <> ?');
        $q->execute(array((string)$keepUserId));
        $q = $pdo->prepare('UPDATE users SET InActive = 0 WHERE user_id = ?');
        $q->execute(array((string)$keepUserId));
        echo "  Kept user_id: $keepUserId\n";
    } catch (PDOException $e) {
        echo "  WARNING: Could not prune users: " . $e->getMessage() . "\n";
    }
} else {
    echo "  WARNING: No active users found to keep.\n";
}

$pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
$pdo->exec('SET SQL_SAFE_UPDATES = 1');

echo "\n========================================\n";
echo "RESET COMPLETE!\n";
echo "========================================\n";
echo "Tables cleared: $success\n";

if (!empty($errors)) {
    echo "\nWarnings (" . count($errors) . "):\n";
    foreach (array_slice($errors, 0, 5) as $err) {
        echo "  - $err\n";
    }
}

// Verify counts
echo "\nVerification:\n";
$counts = [
    'Patients' => 'SELECT COUNT(*) FROM patient_personal_info',
    'Invoices' => 'SELECT COUNT(*) FROM invoice',
    'IPD Bills' => 'SELECT COUNT(*) FROM iop_billing',
    'NHIS Claims' => 'SELECT COUNT(*) FROM nhis_claims',
    'Lab Results' => 'SELECT COUNT(*) FROM lab_result_entries',
    'Prescriptions' => 'SELECT COUNT(*) FROM doctor_prescription',
    'Active Users' => 'SELECT COUNT(*) FROM users WHERE InActive = 0',
    'SSOT Rows' => 'SELECT COUNT(*) FROM billing_transactions',
    'Billing Queue Rows' => 'SELECT COUNT(*) FROM billing_queue',
];

foreach ($counts as $label => $query) {
    try {
        $count = $pdo->query($query)->fetchColumn();
        echo "  $label: $count\n";
    } catch (PDOException $e) {
        echo "  $label: (table not found)\n";
    }
}

echo "\n✅ System is ready for fresh demo!\n";
echo "Backup saved to: $backup_file\n";

if (php_sapi_name() !== 'cli') {
    echo "</pre>";
    echo "<p><a href='/hms-master/'>Go to HMS Dashboard</a></p>";
}
