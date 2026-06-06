# Testing Steps - Lab/Sonography/Prescription Save

## Quick Fix Verification

### Step 1: Run Migration (One-time)

**Option A - Automatic (Recommended):**
1. Visit: `http://localhost/hms-master/app/opd/laboratory`
2. The system auto-detects and fixes schema
3. Check logs: `application/logs/log-2026-04-08.php`

**Option B - Manual SQL:**
```sql
-- Run in MySQL (Laragon > MySQL > Run SQL)
RENAME TABLE billing_audit_log TO billing_audit_log_backup;
-- Then visit any page to auto-create new table
```

**Option C - PHP Script:**
```bash
# Run from hms-master directory
php docs/trigger_migration.php
```

---

## End-to-End Testing

### Test 1: Register Patient
1. Go to: **Patient** → **Registration**
2. Fill:
   - Name: Test Patient
   - Age: 30
   - Gender: Male
3. Click: **Save**
4. Note the **Patient No** (e.g., 000123)

### Test 2: Create OPD Visit
1. Go to: **OPD** → **Out Patient** → **New Visit**
2. Search: Test Patient
3. Click: **Proceed**
4. Fill:
   - Complaint: Fever
   - Doctor: Select any
5. Click: **Save**
6. Note the **Visit ID** (e.g., OP-000002)

### Test 3: Save Lab Request
1. Go to: **OPD** → **Out Patient** → Find your visit
2. Click: **Laboratory**
3. Click: **Add Laboratory**
4. Fill:
   - Category: Laboratory
   - Particular: Full Blood Count (or any test)
5. Click: **Save**
6. **Expected Result:**
   - ✅ "Laboratory successfully Saved!" message
   - ✅ Lab request appears in list
   - ❌ NO SQL errors

### Test 4: Save Sonography Request
1. In Laboratory page, click: **Sonography** tab
2. Click: **Add Sonography**
3. Fill:
   - Select Scan: Abdominal Scan (or enter clinical question)
   - Urgency: Routine
4. Click: **Save**
5. **Expected Result:**
   - ✅ Sonography saved
   - ✅ Appears in list
   - ❌ NO SQL errors

### Test 5: Save Prescription
1. Go to: **OPD** → **Medication**
2. Click: **Add Medication**
3. Fill:
   - Drug: Paracetamol
   - Dosage: 500mg
   - Frequency: TDS (Three times daily)
   - Days: 5
   - Qty: 15
4. Click: **Save**
5. **Expected Result:**
   - ✅ "Medication successfully Added!" message
   - ✅ Medication appears with **Frequency** displayed
   - ❌ NO SQL errors

---

## Troubleshooting

### If You Still See "Unknown column 'service_order_id'"

**Check 1: Verify migration ran**
```sql
-- In MySQL
DESCRIBE billing_audit_log;
-- Should show: id, service_order_id, action_type...
-- NOT: audit_id, entity_type, entity_id...
```

**Check 2: Force migration via PHP**
```php
// Add this to top of controller temporarily
$this->load->model('app/service_billing_model');
$this->service_billing_model->ensure_service_billing_schema();
die('Migration triggered. Refresh page.');
```

**Check 3: Manual fix**
```sql
-- Backup old data
RENAME TABLE billing_audit_log TO billing_audit_log_old;

-- Visit any page to auto-create new table
-- Test lab save
```

### Check Error Logs

```
application/logs/log-2026-04-08.php
```

Look for:
- "billing_audit_log missing service_order_id column"
- "SERVICE_ORDER_CREATED"
- "LAB_REQUEST_CREATED"

### Common Issues

| Issue | Solution |
|-------|----------|
| Table still has old schema | Run `docs/trigger_migration.php` |
| Backup table exists | Old data preserved - new table created |
| Foreign key errors | Check `service_orders` table exists |
| Permission errors | Run as root or database admin |

---

## Success Criteria

✅ All tests pass:
- [ ] Patient registered
- [ ] Visit created
- [ ] Lab request saved and displayed
- [ ] Sonography request saved and displayed
- [ ] Prescription saved with frequency displayed
- [ ] No SQL errors in logs

**Demo Ready!** 🎉
