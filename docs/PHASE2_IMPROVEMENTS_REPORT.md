# Phase 2 Improvements — Pre-Phase 3 Hardening Implementation Report

**Report Date:** April 5, 2026  
**Prepared By:** Senior Health Systems Architect  
**System:** Hebrew Medical Center HMS  
**Status:** 📋 AWAITING APPROVAL

---

## Executive Summary

This report outlines **8 critical improvements** to the Phase 2 implementation to ensure:

- ✅ Ghana Health Service (GHS) compliance
- ✅ NHIS Claim-IT protocol compliance
- ✅ Audit-safe architecture
- ✅ Single Source of Truth principle
- ✅ Enterprise-grade pharmacy operations

These improvements harden the existing Drug Returns Workflow and Diagnosis Code Integration before proceeding to Phase 3.

---

## Current State Analysis

### Existing Phase 2 Implementation

| Component | Status | Gap Identified |
|-----------|--------|----------------|
| `pharmacy_returns` table | ✅ Exists | Missing batch tracking, return type, financial flags |
| `pharmacy_return_audit` table | ✅ Exists | Adequate |
| `generate_return_number()` | ✅ Exists | Format: `RET-YYYYMMDD-XXXX` (4 digits) — needs 5 digits |
| `iop_medication.diagnosis_code` | ✅ Exists | Good |
| `iop_medication.diagnosis_description` | ⚠️ Exists | **DUPLICATION** — violates Single Source of Truth |
| Multi-diagnosis support | ❌ Missing | Single diagnosis only |
| Financial reversal | ❌ Missing | No billing/claim reversal on return |
| Return window validation | ❌ Missing | No time-based restrictions |

---

## Task 1 — Return Number Standardization

### Current Implementation
```
Format: RET-YYYYMMDD-XXXX (4 digits)
Example: RET-20260405-0001
```

### Proposed Change
```
Format: RET-YYYYMMDD-XXXXX (5 digits)
Example: RET-20260405-00001
```

### Implementation Details

| Item | Description |
|------|-------------|
| **File** | `pharmacy_returns_model.php` |
| **Method** | `generate_return_number()` |
| **Change** | Update `str_pad($seq, 4, ...)` → `str_pad($seq, 5, ...)` |
| **Thread Safety** | Add `SELECT ... FOR UPDATE` lock |
| **Unique Constraint** | Already exists on `return_number` column |

### Code Change
```php
// Current
return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);

// Proposed
return $prefix . str_pad($seq, 5, '0', STR_PAD_LEFT);
```

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Existing return numbers | LOW | 4-digit numbers remain valid |
| Sequence collision | LOW | Unique constraint prevents duplicates |

---

## Task 2 — Batch-Level Returns Support

### Current State
- Returns reference `drug_id` only
- Stock adjustment goes to global `medicine_drug_name.nStock`
- No batch tracking on returns

### Proposed Schema Changes

**Add columns to `pharmacy_returns`:**

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `batch_no` | VARCHAR(50) | NULL | Batch number being returned |
| `expiry_date` | DATE | NULL | Expiry date of returned batch |
| `stock_location` | VARCHAR(50) | 'MAIN' | Pharmacy location/store |

### Implementation Details

| Item | Description |
|------|-------------|
| **File** | `pharmacy_returns_model.php` |
| **Schema Method** | `ensure_returns_schema()` |
| **Stock Logic** | `adjust_stock_on_approval()` — update `medication_stock` table if batch exists |
| **Fallback** | If no batch specified, adjust global `nStock` |

### Stock Update Logic
```
IF batch_no IS NOT NULL AND EXISTS in medication_stock:
    UPDATE medication_stock 
    SET quantity_remaining = quantity_remaining + qty_returned
    WHERE batch_number = :batch_no AND medication_id = :drug_id
ELSE:
    UPDATE medicine_drug_name 
    SET nStock = nStock + qty_returned
    WHERE drug_id = :drug_id
```

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Batch not found | LOW | Fallback to global stock |
| Expired batch return | MEDIUM | Validate expiry_date before restocking |

---

## Task 3 — Return Type Classification

### Proposed Schema Change

**Add column to `pharmacy_returns`:**

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `return_type` | ENUM('PATIENT_RETURN','WARD_RETURN','INTERNAL_CORRECTION') | 'PATIENT_RETURN' | Classification for reporting |

### Return Types

| Type | Description | Use Case |
|------|-------------|----------|
| `PATIENT_RETURN` | Drug returned by patient | Over-dispensed, patient refused |
| `WARD_RETURN` | Drug returned from ward/unit | IPD unused drugs |
| `INTERNAL_CORRECTION` | Pharmacy internal correction | Dispensing error fix |

### Implementation Details

| Item | Description |
|------|-------------|
| **File** | `pharmacy_returns_model.php` |
| **Schema** | Add ENUM column with migration guard |
| **Views** | Add dropdown in `return_create.php` |
| **Reports** | Filter by return_type in `pharmacy_returns.php` |

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Existing records | NONE | Default value handles existing rows |

---

## Task 4 — Diagnosis Single Source of Truth

### Current Problem
```
iop_medication.diagnosis_code = 'B50'
iop_medication.diagnosis_description = 'Plasmodium falciparum malaria'
```

**Issue:** Description is duplicated from `icd10_codes.description`

### Proposed Solution

1. **STOP storing** `diagnosis_description` in new records
2. **Retrieve dynamically** from `icd10_codes` table
3. **Keep column** for backward compatibility (existing data)
4. **Update views** to JOIN for description

### Implementation Details

| File | Change |
|------|--------|
| `opd.php` → `save_medication()` | Remove `diagnosis_description` from INSERT |
| `medication.php` | Display description from autocomplete, store only code |
| `pharmacy_returns.php` | JOIN `icd10_codes` for display |
| `Nhis_claimit_model.php` | Use `icd10_codes.description` in claim generation |

### Helper Method
```php
public function get_diagnosis_description($code) {
    $row = $this->db->get_where('icd10_codes', ['code' => $code])->row();
    return $row ? $row->description : $code;
}
```

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Existing descriptions | NONE | Keep column, just stop populating |
| Missing ICD-10 code | LOW | Fallback to stored description or code itself |

---

## Task 5 — Multi-Diagnosis Support

### Proposed New Table

```sql
CREATE TABLE `iop_medication_diagnosis` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `iop_med_id` INT NOT NULL,
    `diagnosis_code` VARCHAR(20) NOT NULL,
    `diagnosis_type` ENUM('PRIMARY','SECONDARY') DEFAULT 'PRIMARY',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `created_by` VARCHAR(25) DEFAULT NULL,
    KEY `idx_iop_med` (`iop_med_id`),
    KEY `idx_code` (`diagnosis_code`),
    KEY `idx_type` (`diagnosis_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Implementation Details

| Item | Description |
|------|-------------|
| **File** | `opd_model.php` |
| **Method** | `ensure_multi_diagnosis_schema()` |
| **Backward Compat** | Migrate existing `iop_medication.diagnosis_code` to new table |
| **UI** | Add "Add Secondary Diagnosis" button in `medication.php` |

### Migration Logic
```php
// One-time migration of existing diagnosis_code
INSERT INTO iop_medication_diagnosis (iop_med_id, diagnosis_code, diagnosis_type)
SELECT iop_med_id, diagnosis_code, 'PRIMARY'
FROM iop_medication
WHERE diagnosis_code IS NOT NULL
AND NOT EXISTS (SELECT 1 FROM iop_medication_diagnosis WHERE iop_med_id = iop_medication.iop_med_id)
```

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Existing prescriptions | NONE | Migration preserves data |
| NHIS claims | LOW | Claims use PRIMARY diagnosis |

---

## Task 6 — Financial Reversal Handling

### Proposed Schema Changes

**Add columns to `pharmacy_returns`:**

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `billing_reversed` | TINYINT(1) | 0 | Pharmacy billing reversed |
| `claim_reversed` | TINYINT(1) | 0 | NHIS claim item reversed |
| `reversal_amount` | DECIMAL(18,2) | 0 | Amount reversed |
| `reversal_date` | DATETIME | NULL | When reversal occurred |

### Reversal Logic on Approval

```
1. Find billing line item for this dispense
2. Create credit note / reversal entry
3. Set billing_reversed = 1
4. If NHIS patient:
   a. Find claim item
   b. Mark as reversed / create adjustment
   c. Set claim_reversed = 1
5. Log reversal in audit
```

### Implementation Details

| File | Method | Change |
|------|--------|--------|
| `pharmacy_returns_model.php` | `approve_return()` | Add reversal logic |
| `billing_model.php` | `reverse_pharmacy_charge()` | NEW method |
| `Nhis_claimit_model.php` | `reverse_claim_item()` | NEW method |

### Audit Trail
```php
$this->log_return_audit($return_id, 'BILLING_REVERSED', $user_id, [
    'amount' => $reversal_amount,
    'invoice_no' => $invoice_no
]);
```

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Double reversal | HIGH | Check flags before reversing |
| Partial reversal | MEDIUM | Calculate proportional amount |
| Claim already submitted | HIGH | Block reversal if claim submitted to NHIS |

---

## Task 7 — Return Window Validation

### Proposed Implementation

**Add system configuration:**

| Config Key | Default | Purpose |
|------------|---------|---------|
| `return_window_hours` | 48 | Hours allowed for returns |
| `return_window_admin_override` | 1 | Allow admin override |

### Validation Logic
```php
public function validate_return_window($dispense_date, $user_role) {
    $window_hours = $this->get_config('return_window_hours', 48);
    $dispense_time = strtotime($dispense_date);
    $current_time = time();
    $hours_elapsed = ($current_time - $dispense_time) / 3600;
    
    if ($hours_elapsed > $window_hours) {
        if ($user_role === 'admin') {
            return ['allowed' => true, 'override' => true, 'message' => 'Admin override applied'];
        }
        return ['allowed' => false, 'message' => "Return window of {$window_hours} hours exceeded"];
    }
    return ['allowed' => true, 'override' => false];
}
```

### Implementation Details

| File | Change |
|------|--------|
| `pharmacy_returns_model.php` | Add `validate_return_window()` method |
| `pharmacy.php` | Check window in `save_return()` |
| `return_create.php` | Show warning if window exceeded |
| `nhis_billing_config` | Add `return_window_hours` config |

### Risk Assessment
| Risk | Level | Mitigation |
|------|-------|------------|
| Legitimate late returns | LOW | Admin override available |
| Clock skew | LOW | Use server time consistently |

---

## Task 8 — Performance Index Optimization

### Proposed Indexes

**`pharmacy_returns` table:**

| Index Name | Columns | Purpose |
|------------|---------|---------|
| `idx_return_number` | `return_number` | Already exists (UNIQUE) |
| `idx_batch` | `batch_no` | Batch-level queries |
| `idx_return_type` | `return_type` | Type filtering |
| `idx_created_at` | `created_at` | Date range queries |
| `idx_status_date` | `status, return_date` | Composite for dashboard |

**`iop_medication_diagnosis` table:**

| Index Name | Columns | Purpose |
|------------|---------|---------|
| `idx_iop_med` | `iop_med_id` | Join optimization |
| `idx_code` | `diagnosis_code` | Code lookups |
| `idx_type` | `diagnosis_type` | Primary/Secondary filter |

### Implementation
```sql
-- pharmacy_returns indexes
CREATE INDEX IF NOT EXISTS idx_batch ON pharmacy_returns(batch_no);
CREATE INDEX IF NOT EXISTS idx_return_type ON pharmacy_returns(return_type);
CREATE INDEX IF NOT EXISTS idx_created_at ON pharmacy_returns(created_at);
CREATE INDEX IF NOT EXISTS idx_status_date ON pharmacy_returns(status, return_date);
```

---

## Files to Modify

### Controllers

| File | Changes |
|------|---------|
| `pharmacy.php` | Return window validation, return type handling |
| `opd.php` | Remove diagnosis_description storage, multi-diagnosis |
| `nhis_claims.php` | Claim reversal integration |

### Models

| File | Changes |
|------|---------|
| `pharmacy_returns_model.php` | All 8 tasks |
| `opd_model.php` | Multi-diagnosis table, remove description storage |
| `Nhis_claimit_model.php` | Claim reversal method |
| `billing_model.php` | Billing reversal method |

### Views

| File | Changes |
|------|---------|
| `pharmacy_returns.php` | Return type filter, batch display |
| `return_create.php` | Return type dropdown, batch fields, window warning |
| `return_view.php` | Financial reversal status display |
| `medication.php` | Multi-diagnosis UI, remove description storage |

---

## Schema Changes Summary

### New Columns on `pharmacy_returns`

| Column | Type | Default |
|--------|------|---------|
| `batch_no` | VARCHAR(50) | NULL |
| `expiry_date` | DATE | NULL |
| `stock_location` | VARCHAR(50) | 'MAIN' |
| `return_type` | ENUM(...) | 'PATIENT_RETURN' |
| `billing_reversed` | TINYINT(1) | 0 |
| `claim_reversed` | TINYINT(1) | 0 |
| `reversal_amount` | DECIMAL(18,2) | 0 |
| `reversal_date` | DATETIME | NULL |

### New Table

| Table | Purpose |
|-------|---------|
| `iop_medication_diagnosis` | Multi-diagnosis support |

### New Config Keys

| Key | Default | Table |
|-----|---------|-------|
| `return_window_hours` | 48 | `nhis_billing_config` |

---

## Backward Compatibility

| Requirement | Status |
|-------------|--------|
| Existing prescriptions | ✅ Preserved |
| Existing dispenses | ✅ Preserved |
| Existing returns | ✅ Preserved (default values applied) |
| Existing NHIS claims | ✅ Preserved |
| Existing diagnosis codes | ✅ Migrated to new table |

### Migration Strategy

1. **Safe column additions** — All new columns have defaults
2. **No column removals** — `diagnosis_description` kept for existing data
3. **One-time migration** — Existing diagnosis codes copied to new table
4. **Feature flags** — None required (all backward compatible)

---

## Security & Audit Requirements

### Role-Based Access

| Action | Roles Allowed |
|--------|---------------|
| Create return | Pharmacist, Admin |
| View returns | Pharmacist, Admin |
| Approve return | Admin only |
| Reject return | Admin only |
| Override return window | Admin only |
| Add diagnosis | Doctor |

### Audit Logging

| Event | Logged |
|-------|--------|
| Return creation | ✅ |
| Batch adjustment | ✅ |
| Billing reversal | ✅ |
| Claim reversal | ✅ |
| Diagnosis update | ✅ |
| Window override | ✅ |

---

## Testing Checklist

| # | Test Case | Priority |
|---|-----------|----------|
| 1 | Return number generation (5 digits, daily reset) | HIGH |
| 2 | Batch-level return with stock update | HIGH |
| 3 | Return type selection and filtering | MEDIUM |
| 4 | Diagnosis stored without description | HIGH |
| 5 | Multi-diagnosis (primary + secondary) | MEDIUM |
| 6 | Financial reversal on approval | HIGH |
| 7 | Return window validation (48h default) | HIGH |
| 8 | Admin override for expired window | HIGH |
| 9 | NHIS claim integrity after reversal | HIGH |
| 10 | Performance with new indexes | MEDIUM |

---

## Implementation Order

| Phase | Tasks | Dependencies |
|-------|-------|--------------|
| **A** | Task 1 (Return Number), Task 3 (Return Type), Task 8 (Indexes) | None |
| **B** | Task 2 (Batch Returns), Task 7 (Return Window) | Phase A |
| **C** | Task 4 (Single Source of Truth), Task 5 (Multi-Diagnosis) | None |
| **D** | Task 6 (Financial Reversal) | Phase B |

---

## Risk Assessment Summary

| Task | Risk Level | Notes |
|------|------------|-------|
| Task 1 — Return Number | LOW | Minor format change |
| Task 2 — Batch Returns | MEDIUM | Stock logic complexity |
| Task 3 — Return Type | LOW | Simple ENUM addition |
| Task 4 — Single Source | LOW | Stop storing, keep column |
| Task 5 — Multi-Diagnosis | MEDIUM | New table, migration needed |
| Task 6 — Financial Reversal | HIGH | Billing/claim integrity critical |
| Task 7 — Return Window | LOW | Validation only |
| Task 8 — Indexes | LOW | Performance improvement |

---

## Estimated Effort

| Task | Lines of Code | Time Estimate |
|------|---------------|---------------|
| Task 1 | ~20 | 15 min |
| Task 2 | ~80 | 45 min |
| Task 3 | ~40 | 20 min |
| Task 4 | ~60 | 30 min |
| Task 5 | ~120 | 60 min |
| Task 6 | ~150 | 90 min |
| Task 7 | ~50 | 30 min |
| Task 8 | ~30 | 15 min |
| **Total** | **~550** | **~5 hours** |

---

## Expected Outcome

After Phase 2 Improvements:

| Attribute | Status |
|-----------|--------|
| Audit Safe | ✅ Complete audit trail |
| NHIS Compliant | ✅ Claim reversal, diagnosis codes |
| GHS Aligned | ✅ Batch tracking, return types |
| Enterprise Ready | ✅ Multi-store, financial controls |
| Single Source of Truth | ✅ No data duplication |

---

## Approval Required

**Please review and approve this implementation plan before coding begins.**

- [ ] Task 1 — Return Number Standardization
- [ ] Task 2 — Batch-Level Returns Support
- [ ] Task 3 — Return Type Classification
- [ ] Task 4 — Diagnosis Single Source of Truth
- [ ] Task 5 — Multi-Diagnosis Support
- [ ] Task 6 — Financial Reversal Handling
- [ ] Task 7 — Return Window Validation
- [ ] Task 8 — Performance Index Optimization

---

**Prepared by:** HMS Enterprise Architect  
**Date:** April 5, 2026  
**Version:** 1.0
