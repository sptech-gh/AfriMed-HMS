<?php
/**
 * Check all tables that have InActive columns without defaults
 */

$mysqli = new mysqli('localhost', 'root', '', 'hms_master');

echo "Checking all tables with InActive columns...\n\n";

// Get all tables
$tables = $mysqli->query("SHOW TABLES");
$problem_tables = [];

while ($table = $tables->fetch_array()) {
    $table_name = $table[0];
    
    // Check if table has InActive column
    $result = $mysqli->query("SHOW COLUMNS FROM `$table_name` WHERE Field = 'InActive'");
    
    if ($result->num_rows > 0) {
        $column = $result->fetch_assoc();
        
        // Check if it has a default value
        if ($column['Default'] === null || $column['Default'] === '') {
            $problem_tables[] = $table_name;
            echo "⚠ $table_name.InActive - NO DEFAULT VALUE (Null: {$column['Null']})\n";
        } else {
            echo "✓ $table_name.InActive - Default: {$column['Default']}\n";
        }
    }
}

echo "\n==========================================\n";
echo "Found " . count($problem_tables) . " tables with InActive column missing default:\n";
foreach ($problem_tables as $table) {
    echo "  - $table\n";
}

// Specifically check price_history table
echo "\n==========================================\n";
echo "price_history table columns:\n";
$result = $mysqli->query("SHOW COLUMNS FROM price_history");
while ($column = $result->fetch_assoc()) {
    $default = $column['Default'] ?? 'NULL';
    echo sprintf("  %-20s %-15s Default: %s\n", $column['Field'], $column['Type'], $default);
}

// Check if price_history has InActive
$result = $mysqli->query("SHOW COLUMNS FROM price_history WHERE Field = 'InActive'");
if ($result->num_rows > 0) {
    echo "\n⚠ price_history has InActive column!\n";
}
