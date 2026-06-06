# Phase 2 — Medication Data Model Hardening Report

**Date:** 2026-04-13  
**Scope:** Data Model Hardening + Single Source of Truth  
**Phase:** 2 of 4 (Phase 1 = Backend Safety Fixes; Phase 3 = Unified Modal; Phase 4 = Prescription Engine)  
**Migration scripts:** `docs/sql/phase2_migration.sql` | `docs/sql/phase2_rollback.sql`  
**Model:** `application/models/app/Medication_dictionary_model.php`

---

## 0. Pre-Implementation Audit Gap Analysis

| Audit Finding | Already Present (backup) | Phase 2 Action |
|--------------|--------------------------|----------------|
| ENGINE = InnoDB | ✅ All 4 tables already InnoDB | `_ensure_innodb()` guard added — safe no-op |
| `iop_medication` indexes (`idx_iop_id`, `idx_medicine_id`, `idx_active_date`) | ✅ Already in backup schema | Existing preserved; 5 new added |
| `medicine_id` VARCHAR(50) | ✅ Confirmed — still VARCHAR | `run_medicine_id_migration()` provided (manual trigger) |
| `days` INT(2) | ✅ Confirmed | Expanded to INT(3) |
| No `route` column on `iop_medication` | ✅ Confirmed missing | Added `route`, `route_id` |
| No `drug_form` column | ✅ Confirmed missing | Added `drug_form`, `form_id`, `medication_form` table |
| Dual doctor fields (`cPreparedBy` vs `prescribed_by`) | ✅ Confirmed both present | `doctor_id` unified field added + back-filled |
| No audit timestamps | ✅ Only `dDate` present | `created_at`, `updated_at`, `created_by`, `updated_by` added |
| No quantity calc fields | ✅ Confirmed missing | `dose_per_unit`, `frequency_per_day`, `calculated_qty` added |
| No GHS-coded frequency | ✅ Free-text only | `frequency_code`, `frequency_label` + `medication_frequency` master |
| No NHIS fields on prescription row | ✅ Confirmed | `is_nhis_covered`, `nhis_price` added |

---

## 1. Investigation Findings

### 1.1 Medication Data Sources Audit

| Source | Table | Used Where | Active | Conflicts |
|--------|-------|-----------|--------|-----------|
| **Drug catalogue** | `medicine_drug_name` | OPD/IPD/Nurse prescription, pharmacy worklist, billing, CDS, stock | ✅ YES | Primary source — names inconsistent (see §1.2) |
| **Generic drug master** | `drug_generic_master` | Pharmacy Architecture module, brand→generic mapping | ✅ YES | Exists but not linked to `iop_medication` |
| **Brand→Generic mapping** | `drug_brand_mapping` | Pharmacy Architecture UI only | ✅ YES | Not referenced by prescription save paths |
| **NHIS drug tariffs** | `nhis_drug_tariffs` | NHIS billing, claim submission, formulary checks | ✅ YES | Duplicates names from `medicine_drug_name` — no hard FK |
| **Batch-level stock** | `medication_stock` | Recall management, batch tracking | ✅ YES | `medication_id` points to `medicine_drug_name.drug_id` |
| **Prescription record** | `iop_medication` | OPD/IPD/Nurse save, pharmacy worklist, billing | ✅ YES | `medicine_id` is `varchar(50)` (should be `int`) |
| **Free-text prescriptions** | `iop_medication.medicine_text` | All prescription paths when no drug_id selected | ✅ YES | Unstructured; no synonym resolution |
| **Custom doctor drugs** | `medicine_drug_name.is_custom = 1` | Doctor-created ad-hoc drugs | ✅ YES | May duplicate existing entries |

**No separate `pharmacy`, `item`, or `stock` tables** were found acting as an independent medication source. `medication_stock` is batch stock only and correctly references `medicine_drug_name`.

---

### 1.2 Duplicate Drug Name Analysis (from backup data)

Confirmed structural duplicates in `medicine_drug_name`:

| Generic Name | Variants Found | Example Names |
|-------------|---------------|---------------|
| Paracetamol | 3 | `Paracetamol 500mg`, `Paracetamol Syrup`, `Paracetamol Injection` |
| Diclofenac | 2 | `Diclofenac 50mg`, `Diclofenac Injection` |
| Amoxicillin | — | (in NHIS tariffs only, not yet in drug catalogue) |

These are **intentional variants** (different formulations/strengths) — they are **not** true duplicates. However, inconsistent naming (`PARACETAMOL` vs `Paracetamol 500mg`) across modules creates fuzzy-match failures.

**Free-text frequency examples found in `iop_medication.frequency`:**

```
OD (Once daily)
TDS (Three times daily)
BD (Twice daily)
Stat (Immediately)
3 times daily
```
These are inconsistent string variations — the new `medication_frequency` master table standardises them.

---

### 1.3 Missing Structured Fields Audit

| Field | `medicine_drug_name` | `iop_medication` | Required | Action |
|-------|---------------------|------------------|---------|--------|
| Generic Name | ✅ `generic_name` | ❌ | YES | Added `medication_id` FK to master |
| Brand Name | ✅ `drug_name` | partial (`medicine_text`) | YES | `medication_master.brand_name` |
| Strength | ✅ `strength` | ❌ | YES | In `medication_master` |
| Form / Dosage Form | ✅ `dosage_form` | ❌ | YES | In `medication_master.formulation` |
| Route | ❌ | ❌ | YES | Added `route` to `medicine_drug_name`; `route_id` FK to `medication_route` in `iop_medication` |
| Frequency (coded) | ❌ | ✅ free-text | YES | New `medication_frequency` master + `frequency_id` FK |
| Duration (days) | ❌ | ✅ `days` | YES | Already present |
| NHIS Code | ✅ `nhis_code`, `nhis_drug_code` | ❌ | YES | Linked via `medication_master.nhis_code` |
| ATC Code | ❌ | ❌ | Optional | Added `atc_code` to `medicine_drug_name`; in `medication_master` |
| Pregnancy Category | ❌ | ❌ | Optional | Added `pregnancy_category` to `medicine_drug_name`; in `medication_master` |
| Paediatric Safe | ❌ | ❌ | Optional | Added `pediatric_safe` to `medicine_drug_name`; in `medication_master` |
| Dosage Unit (coded) | partial (`uom` → `system_parameters`) | free-text in `dosage` | YES | New `medication_unit` master + `unit_id` FK |

---

## 2. Implementation Summary

### 2.1 New Tables Created (via `Medication_dictionary_model`)

All tables created with `CREATE TABLE IF NOT EXISTS` — **safe to run multiple times**.

#### `medication_master`
Central drug dictionary linking generic name, brand, strength, formulation, route, NHIS code, ATC code, pregnancy category, and back-references to `medicine_drug_name.drug_id`.

```sql
CREATE TABLE medication_master (
  id                  INT AUTO_INCREMENT PRIMARY KEY,
  generic_name        VARCHAR(255) NOT NULL,
  brand_name          VARCHAR(255),
  strength            VARCHAR(50),
  formulation         VARCHAR(50),
  route               VARCHAR(50),
  nhis_code           VARCHAR(50),
  nhis_covered        TINYINT(1) DEFAULT 1,
  atc_code            VARCHAR(20),
  pregnancy_category  VARCHAR(10),
  pediatric_safe      TINYINT(1) DEFAULT 1,
  drug_id_ref         INT,          -- → medicine_drug_name.drug_id
  generic_id_ref      INT,          -- → drug_generic_master.generic_id
  is_active           TINYINT(1) DEFAULT 1,
  created_at          DATETIME,
  updated_at          DATETIME
);
```

#### `medication_synonyms`
Doctor free-text aliases per drug (PCM, Para, Paracetamol → same master entry).

```sql
CREATE TABLE medication_synonyms (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  medication_id  INT NOT NULL,   -- → medication_master.id
  synonym        VARCHAR(255) NOT NULL,
  synonym_upper  VARCHAR(255) NOT NULL,  -- indexed for fast lookup
  source         VARCHAR(50) DEFAULT 'MANUAL'
);
```

#### `medication_frequency`
17 seeded rows. Codes: `OD`, `BD`, `TDS`, `QID`, `Q6H`, `Q8H`, `Q12H`, `Q4H`, `ON`, `MN`, `STAT`, `PRN`, `OW`, `BIW`, `OM`, `AC`, `PC`.

#### `medication_route`
15 seeded rows. Codes: `PO`, `IV`, `IM`, `SC`, `TOP`, `INH`, `SL`, `PR`, `TD`, `IN`, `OPH`, `OTC`, `ITH`, `IO`, `NEB`.

#### `medication_unit`
17 seeded rows covering weight (`mg`, `g`, `mcg`), volume (`ml`, `L`), count (`tablet`, `capsule`, `drop`, `puff`, `unit`, `IU`, `sachet`, `suppository`, `patch`), concentration (`mg/ml`, `mg/5ml`, `%`).

---

### 2.2 Modified Tables (additive-only)

#### `iop_medication` — 4 new nullable FK columns

```sql
ALTER TABLE iop_medication
  ADD COLUMN medication_id  INT DEFAULT NULL,  -- → medication_master.id
  ADD COLUMN frequency_id   INT DEFAULT NULL,  -- → medication_frequency.id
  ADD COLUMN route_id       INT DEFAULT NULL,  -- → medication_route.id
  ADD COLUMN unit_id        INT DEFAULT NULL;  -- → medication_unit.id
```

**All existing rows remain valid** — columns are nullable, no constraints added.

#### `medicine_drug_name` — 5 new nullable columns

```sql
ALTER TABLE medicine_drug_name
  ADD COLUMN route               VARCHAR(50),
  ADD COLUMN pregnancy_category  VARCHAR(10),
  ADD COLUMN pediatric_safe      TINYINT(1) DEFAULT 1,
  ADD COLUMN atc_code            VARCHAR(20),
  ADD COLUMN medication_master_id INT;        -- back-link to medication_master
```

---

### 2.3 Indexes Added

| Table | Index | Columns |
|-------|-------|---------|
| `medication_master` | `idx_generic_name` | `generic_name(191)` |
| `medication_master` | `idx_brand_name` | `brand_name(191)` |
| `medication_master` | `idx_nhis_code` | `nhis_code` |
| `medication_master` | `idx_drug_id_ref` | `drug_id_ref` |
| `medication_synonyms` | `idx_synonym` | `synonym(191)` |
| `medication_synonyms` | `idx_syn_upper` | `synonym_upper(191)` |
| `iop_medication` | `idx_med_master_id` | `medication_id` |
| `iop_medication` | `idx_freq_id` | `frequency_id` |
| `iop_medication` | `idx_route_id` | `route_id` |
| `medicine_drug_name` | `idx_med_master_ref` | `medication_master_id` |
| `medicine_drug_name` | `idx_atc_code` | `atc_code` |

---

### 2.4 Migration Methods (available but not auto-run)

Two one-time migration methods are available on `Medication_dictionary_model`:

| Method | What it does |
|--------|-------------|
| `seed_from_medicine_drug_name()` | Reads all active `medicine_drug_name` rows → inserts into `medication_master` → back-links `medication_master_id` → seeds synonyms. Skips already-linked rows. |
| `seed_from_nhis_tariffs()` | Reads `nhis_drug_tariffs` → inserts NHIS-coded drugs not yet in master. |

**These must be triggered manually** (e.g., from an admin panel endpoint or one-time migration script). They are not called on every request to avoid performance impact.

---

### 2.5 Smart Match API

```php
$this->Medication_dictionary_model->smart_match('para 500', 10);
// Returns medication_master rows, ordered: exact match → synonym match → LIKE match
```

Match order:
1. `UPPER(generic_name)` or `UPPER(brand_name)` exact match
2. `medication_synonyms.synonym_upper` exact match
3. `generic_name LIKE %term%` or `brand_name LIKE %term%`
4. `medication_synonyms.synonym LIKE %term%`

---

## 3. Duplicate Resolution Report

No destructive deduplication was performed. Structural duplicates (different formulations of same generic) are correctly stored as separate `medication_master` entries distinguished by `formulation` + `strength`.

Name-level duplicates (e.g., `PARACETAMOL` vs `Paracetamol 500mg`) are unified via `medication_synonyms` — both resolve to the same `medication_master.id` on smart-match lookup.

To generate a live duplicate report, call:
```php
$this->Medication_dictionary_model->find_duplicate_drug_names();
$this->Medication_dictionary_model->find_structural_duplicates();
```

---

## 4. Risk Analysis

| Risk | Severity | Mitigation |
|------|---------|-----------|
| **Billing mismatch** — new `medication_id` FK column not yet populated | LOW | Column is nullable; billing logic still reads `medicine_id` → `medicine_drug_name` as before. No billing query was changed. |
| **Stock mismatch** — `medication_stock` still references `medicine_drug_name.drug_id` | LOW | `medication_master.drug_id_ref` bridges the two. No stock queries changed. |
| **Pharmacy worklist mismatch** — worklist joins `iop_medication` to `medicine_drug_name` via `medicine_id` | LOW | That join is unchanged. `medication_id` FK is additive and not yet used by worklist. |
| **CDS model references `medicine_drug_name.drug_id`** | LOW | CDS model unchanged; it still uses `drug_id` directly. Phase 3 will wire smart-match into CDS. |
| **`iop_medication.medicine_id` is `varchar(50)` not `int`** | MEDIUM | Not changed in Phase 2 (schema change risk). Noted for Phase 3. |
| **Old free-text frequencies not linked to `frequency_id`** | LOW | Existing rows keep free-text; new prescriptions can optionally populate `frequency_id`. |

---

## 5. Schema Changes Summary

### Tables Modified

| Table | Columns Added | Notes |
|-------|--------------|-------|
| `iop_medication` | `route`, `drug_form`, `frequency_code`, `frequency_label`, `is_nhis_covered`, `nhis_price`, `form_id`, `doctor_id`, `created_at`, `updated_at`, `created_by`, `updated_by`, `dose_per_unit`, `frequency_per_day`, `calculated_qty`, `medication_id`, `frequency_id`, `route_id`, `unit_id` | All nullable; existing rows unchanged |
| `iop_medication` | `days` widened INT(2)→INT(3) | Supports chronic/365-day prescriptions |
| `medicine_drug_name` | `route`, `pregnancy_category`, `pediatric_safe`, `atc_code`, `medication_master_id` | All nullable |

### Tables Created

| Table | Rows Seeded | Purpose |
|-------|------------|---------|
| `medication_master` | 0 (needs `seed_from_medicine_drug_name()`) | Canonical drug dictionary |
| `medication_synonyms` | 0 (populated by seed) | Alias/fuzzy-match table |
| `medication_frequency` | 17 | Coded frequency master |
| `medication_route` | 15 | Route-of-administration master |
| `medication_unit` | 17 | Dosage unit master |
| `medication_form` | 15 | Dosage form master |

### Performance Indexes Added

| Table | Index | Column(s) | Pre-existing? |
|-------|-------|-----------|--------------|
| `iop_medication` | `idx_iop_id` | `iop_id` | ✅ Yes |
| `iop_medication` | `idx_medicine_id` | `medicine_id` | ✅ Yes |
| `iop_medication` | `idx_active_date` | `InActive, dDate` | ✅ Yes |
| `iop_medication` | `idx_disp_status` | `dispensing_status` | ✅ Yes |
| `iop_medication` | `idx_doctor` | `doctor_id` | 🆕 New |
| `iop_medication` | `idx_form_id` | `form_id` | 🆕 New |
| `iop_medication` | `idx_created_at` | `created_at` | 🆕 New |
| `iop_medication` | `idx_freq_code` | `frequency_code` | 🆕 New |
| `iop_medication` | `idx_route_text` | `route` | 🆕 New |
| `iop_medication` | `idx_med_master_id` | `medication_id` | 🆕 New |
| `iop_medication` | `idx_freq_id` | `frequency_id` | 🆕 New |
| `iop_medication` | `idx_route_id` | `route_id` | 🆕 New |

---

## 6. Data Migration Report

| Old Field | New Field | Migration Method |
|-----------|-----------|-----------------|
| `prescribed_by` / `cPreparedBy` (dual) | `doctor_id` (unified) | `COALESCE(prescribed_by, cPreparedBy)` — auto-run |
| `dDate` | `created_at` | Back-filled: `SET created_at = dDate WHERE created_at IS NULL` — auto-run |
| `medicine_id` VARCHAR(50) | `medicine_id` INT(11) | **Manual** — call `run_medicine_id_migration()` after `get_medicine_id_migration_status()` check |
| Free-text `frequency` | `frequency_code` + `frequency_id` | Pending Phase 3 mapping |
| Free-text `medicine_text` | `medication_id` (via smart-match) | Pending `seed_from_medicine_drug_name()` + Phase 3 |

---

## 7. Rollback Plan

Full rollback script: `docs/sql/phase2_rollback.sql`

All changes are **additive only**. Rollback procedure:

1. **Run** `docs/sql/phase2_rollback.sql` in MySQL
2. **Delete** `application/models/app/Medication_dictionary_model.php`
3. **Revert** two lines in each of OPD / IPD / Nurse constructors:
```php
// Remove these two lines from each constructor:
$this->load->model("app/Medication_dictionary_model");
$this->Medication_dictionary_model->ensure_dictionary_schema();
```

**No existing prescriptions, billing records, or stock data are affected by rollback.**

---

## 8. Verification Checklist

Run after deployment. All should pass ✅.

### Schema checks (via `docs/sql/phase2_migration.sql` verification queries)

```sql
-- InnoDB confirmation
SELECT TABLE_NAME, ENGINE FROM INFORMATION_SCHEMA.TABLES
 WHERE TABLE_SCHEMA = DATABASE()
   AND TABLE_NAME IN ('iop_medication','pharmacy_billing_queue',
                      'iop_medication_administration','pharmacy_audit_log');
-- Expected: ENGINE = InnoDB for all 4

-- New columns on iop_medication
SELECT COLUMN_NAME, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iop_medication'
   AND COLUMN_NAME IN ('route','drug_form','frequency_code','frequency_label',
                       'is_nhis_covered','nhis_price','form_id','doctor_id',
                       'created_at','updated_at','dose_per_unit','calculated_qty');
-- Expected: 12 rows returned

-- days column widened
SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'iop_medication'
   AND COLUMN_NAME = 'days';
-- Expected: int(3)

-- New tables
SHOW TABLES LIKE 'medication_%';
-- Expected: medication_master, medication_synonyms, medication_frequency,
--           medication_route, medication_unit, medication_form

-- Seeded rows
SELECT COUNT(*) FROM medication_frequency;  -- Expected: 17
SELECT COUNT(*) FROM medication_route;      -- Expected: 15
SELECT COUNT(*) FROM medication_unit;       -- Expected: 17
SELECT COUNT(*) FROM medication_form;       -- Expected: 15

-- doctor_id back-fill
SELECT COUNT(*) FROM iop_medication WHERE doctor_id IS NOT NULL;
-- Expected: >= count of rows with prescribed_by or cPreparedBy populated

-- created_at back-fill
SELECT COUNT(*) FROM iop_medication WHERE created_at IS NOT NULL AND dDate IS NOT NULL;
-- Expected: equals count of rows where dDate is not null
```

### PHP API checks

```php
// In any controller after model is loaded:
$status = $this->Medication_dictionary_model->get_medicine_id_migration_status();
// Check $status['current_type'] — should be 'varchar(50)' before migration
// Check $status['safe_to_migrate'] — should be true if no non-numeric values

$freqs = $this->Medication_dictionary_model->get_frequencies();
// Expected: 17 objects

$routes = $this->Medication_dictionary_model->get_routes();
// Expected: 15 objects

$forms = $this->Medication_dictionary_model->get_forms();
// Expected: 15 objects

$matches = $this->Medication_dictionary_model->smart_match('paracetamol');
// Expected: array (empty until seed_from_medicine_drug_name() is run)
```

---

## 9. Success Criteria

| Criterion | Status |
|-----------|--------|
| ✅ InnoDB enforced on all 4 pharmacy tables | DONE (guard added; already InnoDB) |
| ✅ `medicine_id` VARCHAR→INT migration path ready | DONE (`run_medicine_id_migration()`) |
| ✅ GHS/NHIS required fields added to prescriptions | DONE |
| ✅ `days` expanded to INT(3) for chronic medications | DONE |
| ✅ Doctor field unified (`doctor_id`) + back-filled | DONE |
| ✅ Audit timestamp fields added + back-filled from `dDate` | DONE |
| ✅ Quantity calculation fields added | DONE |
| ✅ `medication_form` master table created + seeded (15 forms) | DONE |
| ✅ `medication_route` master table created + seeded (15 routes) | DONE |
| ✅ `medication_frequency` master table created + seeded (17 codes) | DONE |
| ✅ `medication_unit` master table created + seeded (17 units) | DONE |
| ✅ `medication_master` + `medication_synonyms` created | DONE |
| ✅ Performance indexes added (5 new) | DONE |
| ✅ FK-link columns on `iop_medication` (4 cols) | DONE |
| ✅ Backward compatibility preserved (all additive/nullable) | DONE |
| ✅ No UI changes | DONE |
| ✅ No billing logic changes | DONE |
| ✅ No controller save logic changes | DONE |
| ✅ Migration SQL + Rollback SQL scripts provided | DONE |
| ⏳ `medicine_id` converted to INT (data migration) | Pending — call `run_medicine_id_migration()` after pre-check |
| ⏳ `medication_master` populated from drug catalogue | Pending — call `seed_from_medicine_drug_name()` once |
| ⏳ Free-text `frequency` resolved to `frequency_id` | Pending Phase 3 |

---

## 10. Next Steps (Phase 3 — Unified Medication Modal)

1. **Run data seed** — trigger `seed_from_medicine_drug_name()` from an admin panel endpoint
2. **Run medicine_id migration** — check status then call `run_medicine_id_migration()`
3. **Prescription form smart-match** — wire `smart_match()` into drug autocomplete (Phase 3 UI)
4. **Frequency dropdown** — replace free-text input with `medication_frequency` coded list
5. **Route dropdown** — add route selection backed by `medication_route`
6. **Drug form dropdown** — add form selection backed by `medication_form`
7. **Dosage unit dropdown** — replace free-text unit with `medication_unit`
8. **Auto-calculate qty** — `dose_per_unit × frequency_per_day × days → calculated_qty`
