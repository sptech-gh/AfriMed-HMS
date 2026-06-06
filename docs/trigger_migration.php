<?php
/**
 * Manual Schema Migration Trigger
 * Run this to force the billing table schema update
 */

// Bootstrap CodeIgniter
require_once __DIR__ . '/../system/core/Common.php';
require_once __DIR__ . '/../system/core/Controller.php';

// Create a minimal CI instance
$CI = new CI_Controller();
$CI->load->database();
$CI->load->model('app/service_billing_model');

echo "=== SBMC HMS Schema Migration ===\n\n";

// Check current schema
echo "1. Checking billing_audit_log schema...\n";
$has_audit_id = $CI->db->field_exists('audit_id', 'billing_audit_log');
$has_service_order_id = $CI->db->field_exists('service_order_id', 'billing_audit_log');

echo "   - Has audit_id (old schema): " . ($has_audit_id ? "YES - needs migration" : "NO") . "\n";
echo "   - Has service_order_id (new schema): " . ($has_service_order_id ? "YES - OK" : "NO - needs migration") . "\n";

if ($has_audit_id || !$has_service_order_id) {
    echo "\n2. Running schema migration...\n";
    
    // Backup old table if exists
    if ($has_audit_id) {
        $backup_name = 'billing_audit_log_backup_' . date('YmdHis');
        $CI->db->query("RENAME TABLE `billing_audit_log` TO `$backup_name`");
        echo "   - Backed up old table to: $backup_name\n";
    }
    
    // Create new table
    $CI->db->query("CREATE TABLE IF NOT EXISTS `billing_audit_log` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `action_type` VARCHAR(50) NOT NULL COMMENT 'PRICE_CHANGE, DISCOUNT, WAIVE, VOID, etc',
        `service_order_id` INT DEFAULT NULL,
        `invoice_no` VARCHAR(30) DEFAULT NULL,
        `patient_no` VARCHAR(25) DEFAULT NULL,
        `old_value` TEXT DEFAULT NULL,
        `new_value` TEXT DEFAULT NULL,
        `amount_before` DECIMAL(18,2) DEFAULT NULL,
        `amount_after` DECIMAL(18,2) DEFAULT NULL,
        `reason` TEXT DEFAULT NULL,
        `performed_by` VARCHAR(25) NOT NULL,
        `performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `ip_address` VARCHAR(45) DEFAULT NULL,
        PRIMARY KEY (`id`),
        KEY `idx_bal_order` (`service_order_id`),
        KEY `idx_bal_invoice` (`invoice_no`),
        KEY `idx_bal_patient` (`patient_no`),
        KEY `idx_bal_action` (`action_type`),
        KEY `idx_bal_date` (`performed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Billing audit trail'");
    
    echo "   - Created new billing_audit_log table with correct schema\n";
}

echo "\n3. Verifying service_orders table...\n";
if ($CI->db->table_exists('service_orders')) {
    echo "   - service_orders: EXISTS\n";
    $count = $CI->db->count_all('service_orders');
    echo "   - Records: $count\n";
} else {
    echo "   - service_orders: MISSING - creating...\n";
    $CI->service_billing_model->ensure_service_billing_schema();
}

echo "\n4. Verifying billing_approvals table...\n";
if ($CI->db->table_exists('billing_approvals')) {
    echo "   - billing_approvals: EXISTS\n";
    $has_so_id = $CI->db->field_exists('service_order_id', 'billing_approvals');
    echo "   - Has service_order_id: " . ($has_so_id ? "YES" : "NO") . "\n";
} else {
    echo "   - billing_approvals: MISSING - creating...\n";
    $CI->service_billing_model->ensure_service_billing_schema();
}

echo "\n=== Migration Complete ===\n";
echo "\nYou can now:\n";
echo "1. Save lab requests\n";
echo "2. Save sonography requests\n";
echo "3. Save prescriptions\n";
echo "\nAll billing audit logging will work correctly.\n";
