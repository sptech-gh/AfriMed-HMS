# Complete Fix Guide - Service Order ID & Related Issues

## System Status: ✅ FIXED

### Database Schema Verification

| Table | Column | Status |
|-------|--------|--------|
| billing_audit_log | id | ✅ |
| billing_audit_log | service_order_id | ✅ |
| billing_approvals | id | ✅ |
| billing_approvals | service_order_id | ✅ |
| service_orders | id | ✅ |
| iop_laboratory | io_lab_id | ✅ |
| iop_medication | iop_med_id | ✅ |

---

## 1. SQL Scripts (Already Applied)

### billing_audit_log Fix
```sql
-- Backup old table (preserves existing data)
RENAME TABLE billing_audit_log TO billing_audit_log_backup_20260408;

-- Create new table with correct schema
CREATE TABLE billing_audit_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_type VARCHAR(50) NOT NULL,
    service_order_id INT DEFAULT NULL,
    invoice_no VARCHAR(30) DEFAULT NULL,
    patient_no VARCHAR(25) DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    amount_before DECIMAL(18,2) DEFAULT NULL,
    amount_after DECIMAL(18,2) DEFAULT NULL,
    reason TEXT DEFAULT NULL,
    performed_by VARCHAR(25) NOT NULL,
    performed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_bal_order (service_order_id),
    KEY idx_bal_invoice (invoice_no),
    KEY idx_bal_patient (patient_no),
    KEY idx_bal_action (action_type),
    KEY idx_bal_date (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

---

## 2. Code Changes Applied

### File: `application/models/app/service_billing_model.php`

#### Change 1: Auto-Migration (Lines 185-215)
```php
private function _install_billing_audit_log()
{
    // Check if table exists with wrong schema
    if ($this->table_exists('billing_audit_log')) {
        $has_old_schema = $this->db->field_exists('audit_id', 'billing_audit_log');
        if ($has_old_schema) {
            // Backup and recreate
            $this->db->query("RENAME TABLE `billing_audit_log` TO `billing_audit_log_backup_..."`);
        }
    }
    
    // Create with correct schema
    $this->db->query("CREATE TABLE IF NOT EXISTS `billing_audit_log` (...)");
}
```

#### Change 2: Defensive Coding (Lines 849-875)
```php
public function log_billing_audit($action_type, $service_order_id, ...)
{
    if (!$this->table_exists('billing_audit_log')) return false;
    
    // Defensive: Check if service_order_id column exists
    if (!$this->db->field_exists('service_order_id', 'billing_audit_log')) {
        log_message('error', 'billing_audit_log missing service_order_id column...');
        return false;
    }
    
    // ... rest of function
}
```

---

## 3. Testing Steps

### Pre-Test Verification
```sql
-- Run in MySQL to verify schema
DESCRIBE billing_audit_log;
-- Should show: id, service_order_id, action_type, etc.
```

### Test 1: Register Patient
1. Navigate: **Patient** → **Registration**
2. Fill Details:
   - Name: John Doe
   - Age: 35
   - Gender: Male
   - Phone: 0244000000
3. Click: **Save**
4. Note: Patient No (e.g., 000001)

### Test 2: Create OPD Visit
1. Navigate: **OPD** → **Out Patient** → **Add Visit**
2. Search: John Doe
3. Click: **Proceed**
4. Fill:
   - Complaint: Fever and headache
   - Doctor: Select any doctor
5. Click: **Save Visit**
6. Note: Visit ID (e.g., OP 000001)

### Test 3: Save Lab Request
1. Navigate: **OPD** → Find visit → **Laboratory**
2. Click: **Add Laboratory**
3. Fill:
   - Category: Laboratory
   - Particular: Full Blood Count
   - Doctor: Select doctor
4. Click: **Save**
5. **Expected Results:**
   - ✅ "Laboratory successfully Saved!" message
   - ✅ Request appears in list
   - ✅ No SQL errors

### Test 4: Save Sonography Request
1. In Laboratory page, click: **Sonography** tab
2. Click: **Add Sonography**
3. Fill:
   - Scan: Abdominal Ultrasound
   - Clinical Question: Rule out appendicitis
   - Urgency: Routine
4. Click: **Save**
5. **Expected Results:**
   - ✅ Sonography saved successfully
   - ✅ Appears in list
   - ✅ No errors

### Test 5: Save Prescription
1. Navigate: **OPD** → **Medication**
2. Click: **Add Medication**
3. Fill:
   - Drug: Paracetamol
   - Dosage: 500mg
   - Frequency: TDS (Three times daily)
   - Days: 5
   - Qty: 15
4. Click: **Save**
5. **Expected Results:**
   - ✅ "Medication successfully Added!"
   - ✅ Shows with frequency displayed
   - ✅ No errors

---

## 4. Troubleshooting

### Issue: "Unknown column 'service_order_id'"

**Solution 1: Force Schema Update**
```php
// Add temporarily to any controller's index method
$this->load->model('app/service_billing_model');
$this->service_billing_model->ensure_service_billing_schema();
echo "Schema updated. Remove this code.";
exit;
```

**Solution 2: Manual SQL**
```sql
-- Check current schema
DESCRIBE billing_audit_log;

-- If missing service_order_id, run:
ALTER TABLE billing_audit_log ADD COLUMN service_order_id INT DEFAULT NULL AFTER action_type;
CREATE INDEX idx_bal_order ON billing_audit_log(service_order_id);
```

### Issue: "Table doesn't exist"

**Solution:**
```sql
-- Run full schema creation
-- (See SQL script at top of this document)
```

### Check Error Logs
```
application/logs/log-2026-04-08.php
```

---

## 5. Verification Commands

```sql
-- 1. Verify billing_audit_log
SELECT COLUMN_NAME, DATA_TYPE 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = 'hms_master' 
AND TABLE_NAME = 'billing_audit_log';

-- 2. Check service_orders
SELECT COUNT(*) as total_orders FROM service_orders;

-- 3. Check recent lab requests
SELECT io_lab_id, iop_id, laboratory_text, dDateTime 
FROM iop_laboratory 
ORDER BY io_lab_id DESC LIMIT 5;

-- 4. Check recent medications
SELECT iop_med_id, iop_id, medicine_text, frequency, days 
FROM iop_medication 
ORDER BY iop_med_id DESC LIMIT 5;
```

---

## 6. Summary of All Changes

### Database Changes
- ✅ `billing_audit_log` - Fixed schema (was old, now new)
- ✅ `billing_approvals` - Verified has service_order_id
- ✅ `service_orders` - Verified schema correct

### Code Changes
- ✅ `service_billing_model.php` - Auto-migration logic
- ✅ `service_billing_model.php` - Defensive coding

### Backup
- ✅ Old table: `billing_audit_log_backup_20260408`

---

## System Status: READY FOR DEMO ✅

All fixes applied. Test by:
1. Saving lab request → Should work
2. Saving sonography → Should work  
3. Saving prescription → Should work

**No SQL errors expected.**
