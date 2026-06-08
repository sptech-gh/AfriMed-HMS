<?php
$conn = @new mysqli('localhost', 'root', '', 'hms_master');
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error . "\n";
} else {
    echo "Connected successfully to hms_master database!\n";
    $res = $conn->query("SHOW TABLES LIKE 'company_info'");
    if ($res && $res->num_rows > 0) {
        echo "company_info table exists!\n";
    } else {
        echo "company_info table does NOT exist!\n";
    }
    $res2 = $conn->query("SHOW TABLES LIKE 'facility_settings'");
    if ($res2 && $res2->num_rows > 0) {
        echo "facility_settings table exists!\n";
    } else {
        echo "facility_settings table does NOT exist!\n";
    }
}
