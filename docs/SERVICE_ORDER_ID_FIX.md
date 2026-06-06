# Service Order ID Fix - Complete Solution

## Problem Summary

When saving laboratory requests (and potentially sonography/prescriptions), the system throws:
```
mysqli_sql_exception: Unknown column 'service_order_id' in 'field list'
```

## Root Cause

The `billing_audit_log` table exists with an **OLD schema** (from a previous migration):
- Has columns: `audit_id`, `entity_type`, `entity_id`, `action`, `field_name`, etc.

But the **code expects NEW schema**:
- Needs columns: `id`, `service_order_id`, `action_type`, `amount_before`, etc.

## Files Modified

### 1. `application/models/app/service_billing_model.php`

#### Change A: `_install_billing_audit_log()` - Auto-migration
- Lines 185-215: Added schema migration logic
- If table exists with old schema (has `audit_id`), it backs up and recreates

#### Change B: `log_billing_audit()` - Defensive coding  
- Lines 849-875: Added column existence check before insert
- Logs helpful error message if schema is wrong

## Immediate Fix (Run This SQL)

```sql
-- STEP 1: Backup and fix billing_audit_log
RENAME TABLE billing_audit_log TO billing_audit_log_backup;

CREATE TABLE billing_audit_log (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_type VARCHAR(50) NOT NULL COMMENT 'PRICE_CHANGE, DISCOUNT, WAIVE, VOID, etc',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Billing audit trail';
```

## Alternative Fix (PHP Auto-Migration)

The code now auto-fixes itself! Simply:
1. Visit any page that loads `billing_model` or `service_billing_model`
2. The `ensure_service_billing_schema()` method runs automatically
3. It detects the old schema and migrates it

## Testing Steps

### 1. Register a Patient
- Go to Patient Registration
- Create new patient
- Note the Patient ID

### 2. Create OPD Visit
- Go to OPD → Add Visit
- Select the patient
- Create visit
- Note the Visit ID (e.g., OP-000002)

### 3. Save Lab Request
- Go to OPD → Laboratory
- Add a lab test:
  - Category: Laboratory
  - Particular: Full Blood Count (or any)
- Click Save
- **Expected**: Success message, no SQL errors
- **Verify**: Lab request appears in list

### 4. Save Sonography Request
- Go to OPD → Laboratory → Sonography tab
- Add sonography:
  - Category: Sonography (ID 18)
  - Select scan type or enter clinical question
- Click Save
- **Expected**: Success message
- **Verify**: Sonography appears in list

### 5. Save Prescription
- Go to OPD → Medication
- Add medication:
  - Drug: Paracetamol
  - Dose: 500mg
  - Frequency: TDS
  - Days: 5
- Click Save
- **Expected**: Success message
- **Verify**: Medication appears with frequency

## Verification Queries

```sql
-- Check billing_audit_log has correct schema
DESCRIBE billing_audit_log;
-- Should show: id, service_order_id, action_type, etc.

-- Check service_orders is working
SELECT COUNT(*) FROM service_orders;

-- Check lab requests are linked
SELECT io_lab_id, iop_id, service_order_id 
FROM iop_laboratory 
ORDER BY io_lab_id DESC LIMIT 5;
```

## Related Tables Status

| Table | Status | Fix Applied |
|-------|--------|-------------|
| billing_audit_log | ✅ Fixed | Auto-migration added |
| billing_approvals | ✅ OK | Has service_order_id |
| service_orders | ✅ OK | Schema correct |
| iop_laboratory | ✅ OK | Can add service_order_id if needed |
| iop_medication | ✅ OK | Can add service_order_id if needed |

## If You Still See Errors

1. **Check log file**: `application/logs/log-YYYY-MM-DD.php`
2. **Run SQL fix manually**: Execute the SQL in "Immediate Fix" section above
3. **Clear cache**: Delete `application/cache` folder contents
4. **Reload page**: Visit `/app/opd/laboratory` to trigger schema check

## Backup Note

Old table is backed up as: `billing_audit_log_backup_YYYYMMDD_HHMMSS`

To restore old data if needed:
```sql
-- View old data
SELECT * FROM billing_audit_log_backup;

-- If needed, restore (only if new table is empty!)
-- INSERT INTO billing_audit_log (...) SELECT ... FROM billing_audit_log_backup;
```

## Summary

✅ **Code Fix**: Auto-migration in `service_billing_model.php`
✅ **Defensive Coding**: `log_billing_audit()` checks column exists
✅ **SQL Script**: Manual fix available in `docs/fix_billing_tables.sql`
✅ **Testing**: Step-by-step verification guide provided

The system will now:
1. Auto-detect schema mismatches
2. Backup old tables safely
3. Create correct schema automatically
4. Log all billing activities properly
