<?php
/**
 * Standalone Cron Script: update_patient_ages.php
 * ----------------------------------------------
 * - Connects directly to MySQL
 * - Updates patient ages based on birthday
 * - Logs results to cron_log.txt
 * - No dependency on CodeIgniter
 *
 * Run: php update_patient_ages.php
 */

// 1️⃣ --- Safety check: Must run from CLI ---
if (php_sapi_name() !== 'cli') {
    die("❌ This script can only be run from the command line.\n");
}

// 2️⃣ --- Database connection settings (set these manually) ---
$host     = "localhost";   // your MySQL server IP
$username = "cron_user";        // user with SELECT & UPDATE access
$password = "YourStrongPassword123!"; // your password
$database = "hms_master";       // confirmed DB name

// 3️⃣ --- Connect to MySQL ---
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("❌ Failed to connect to MySQL: " . $conn->connect_error . "\n");
}

// 4️⃣ --- Run the age update query ---
$sql = "
    UPDATE patient_personal_info
    SET age = TIMESTAMPDIFF(YEAR, birthday, CURDATE())
    WHERE birthday IS NOT NULL
";

$logFile   = __DIR__ . '/cron_log.txt';
$timestamp = date("Y-m-d H:i:s");

if ($conn->query($sql) === TRUE) {
    $updated = $conn->affected_rows;
    $msg = "✅ [$timestamp] - $updated patient ages updated successfully.\n";
    echo $msg;
    file_put_contents($logFile, $msg, FILE_APPEND);
} else {
    $msg = "❌ [$timestamp] - SQL Error: " . $conn->error . "\n";
    echo $msg;
    file_put_contents($logFile, $msg, FILE_APPEND);
}

$conn->close();
?>
