# Medication Module — Full Pre-Implementation Audit Report

**System:** HMS (hms-master)
**Audit Phase:** Pre-Redesign Investigation Only
**Date:** April 2026
**Auditor:** Cascade AI — Senior HIS Architecture Audit
**Constraint:** ❌ No code changes. Analysis, documentation, and recommendations only.

---

## 1. EXECUTIVE SUMMARY

| Rating Dimension         | Score | Verdict                        |
|--------------------------|-------|--------------------------------|
| **Overall System Health**    | 4.5/10 | Fragmented — multiple parallel systems |
| **Clinical Safety**          | 5.5/10 | CDS exists but batch path bypasses it |
| **Billing Reliability**      | 4.0/10 | Dual billing engines — data conflict risk |
| **NHIS Compliance**          | 5.0/10 | Partial — single-entry only; batch unchecked |
| **Workflow Efficiency**      | 4.5/10 | Two modals, two save paths, legacy code alive |

### Major Risks

- **CRITICAL:** `save_medication_batch()` executes `SHOW COLUMNS` 3× per drug inside a loop — N×3 schema queries on every batch save. No transaction wrapper.
- **CRITICAL:** `save_medication_batch()` does NOT call the NHIS payment gate, CDS safety check, or the billing audit log — all bypassed silently.
- **CRITICAL:** `save_medication()` (single-entry) and `save_medication_batch()` (batch) call two different billing engines: `pharmacy_model->create_or_update_pharmacy_bill()` vs `unified_billing->add_to_billing_queue()`. A drug prescribed via batch may not enter the unified billing queue.
- **HIGH:** `medicine_id` stored as `VARCHAR(50)` in `iop_medication` but is an `INT` primary key in `medicine_drug_name`. Implicit cast on JOIN — full table scans possible on large datasets.
- **HIGH:** IPD `save_medication()` (in `ipd.php`) writes raw `$this->input->post('opd_no')` without URL-decode directly into `iop_id`. No safety checks, no billing trigger, no CDS.
- **HIGH:** Nurse `save_medication()` (admin path) in `nurse_module.php` also writes directly to `iop_medication` with no NHIS check, no billing trigger, no doctor linkage.
- **MEDIUM:** `iop_medication` base schema is `MyISAM` — no foreign key support, no rollback on failure.
- **MEDIUM:** Drug price is read from `nPrice` in `medicine_drug_name` for billing queue but from `selling_price` in `pharmacy_stock_batches` for batch stock — two different price fields, divergent billing amounts possible.

---

## 2. END-TO-END WORKFLOW ANALYSIS

### Confirmed Prescription Flows

```
FLOW A — OPD Single Entry (Legacy)
────────────────────────────────────────────────────────────────────
Doctor opens app/opd/medication/{iop}/{patient}
  → Clicks "Add Single" → #myModal (Bootstrap form)
  → Submits POST → app/opd/save_medication()
      ├── check_nhis_payment_gate()           ✅ NHIS gate enforced
      ├── check_drug_nhis_coverage()          ✅ NHIS formulary checked
      ├── CDS: check_prescription_safety_full() ✅ Safety alerts shown
      ├── INSERT INTO iop_medication           ✅ Doctor linkage (prescribed_by)
      ├── billing_model->log_nhis_audit()     ✅ Audit logged
      ├── pharmacy_model->create_or_update_pharmacy_bill()  ✅ pharmacy_billing_queue
      └── unified_billing->add_to_billing_queue()          ✅ Unified queue
  → Redirect with flashdata

FLOW B — OPD Batch Entry (Multi-Entry Modal)
────────────────────────────────────────────────────────────────────
Doctor clicks "Add Medications" → #multiMedicationModal
  → multi-entry-manager.js collects entries
  → AJAX POST → app/opd/save_medication_batch()
      ├── doctor_write_allowed_or_redirect()  ✅ Permission checked
      ├── NO NHIS payment gate check          ❌ BYPASSED
      ├── NO NHIS formulary check             ❌ BYPASSED
      ├── NO CDS safety check                 ❌ BYPASSED
      ├── SHOW COLUMNS x3 per drug inside loop ❌ N×3 schema queries
      ├── NO database transaction             ❌ Partial save risk
      ├── INSERT INTO iop_medication (loop)   ✅ But no rollback
      ├── create_or_update_pharmacy_bill()    ✅ pharmacy_billing_queue only
      └── NO unified_billing queue entry      ❌ BYPASSED
  → JSON redirect response

FLOW C — IPD Medication (Legacy Path)
────────────────────────────────────────────────────────────────────
app/ipd/save_medication()
  → No URL-decode of iop_id                  ❌ Stores URL-safe string in DB
  → No NHIS check                            ❌ BYPASSED
  → No CDS check                             ❌ BYPASSED
  → No billing trigger                       ❌ BYPASSED
  → No prescribed_by linkage                 ❌ Uses cPreparedBy only
  → Uses date("Y-m-d h:i:s") [lowercase h]  ❌ 12-hour clock without AM/PM

FLOW D — Nurse Medication Admin (Nurse Module)
────────────────────────────────────────────────────────────────────
app/nurse_module/save_medication()           [admin-only path]
  → Admin-only guard (not doctor)
  → Writes directly to iop_medication        ❌ No NHIS, no CDS, no billing
  → No prescribed_by                         ❌ Uses cPreparedBy only

FLOW E — Nurse Administration Logging
────────────────────────────────────────────────────────────────────
app/nurse_module/save_medication_admin()
  → Records administration events (given/held/deferred)
  → Writes to iop_medication_administration  ✅ Separate table, correct
  → Links to iop_med_id                      ✅ Correct foreign key concept
```

### Data Origin and Transformation

| Stage              | Table                        | Populated By               |
|--------------------|------------------------------|----------------------------|
| Drug master        | `medicine_drug_name`         | Admin (drug_name module)   |
| Drug stock         | `pharmacy_stock_batches`     | Pharmacy stock receive     |
| Prescription       | `iop_medication`             | OPD/IPD/Nurse controller   |
| Pharmacy queue     | `pharmacy_billing_queue`     | post-save trigger (Flow A/B)|
| Unified billing    | `unified_billing_queue`      | Flow A only                |
| Admin log          | `iop_medication_administration`| Nurse module              |
| Audit log          | `pharmacy_audit_log`         | pharmacy_model only        |

---

## 3. UI / MODAL AUDIT

### Modals Present

| Modal ID              | Trigger Button        | Save Endpoint             | Status    |
|-----------------------|-----------------------|---------------------------|-----------|
| `#myModal`            | "Add Single"          | `app/opd/save_medication` | Legacy — full safety checks |
| `#multiMedicationModal`| "Add Medications"    | `app/opd/save_medication_batch` | Modern — safety bypassed |

### Dual Modal Problem

Both modals exist simultaneously on the **same page** (`medication.php`). There is no UI indication to doctors which one they should use. Doctors who choose "Add Medications" (the visually prominent button) bypass all NHIS and safety enforcement silently.

### Single Modal Field Comparison

| Field           | #myModal (Single) | #multiMedicationModal (Batch) |
|-----------------|-------------------|-------------------------------|
| Drug search     | Autocomplete (SmartMedical.js) ✅ | multi-entry-manager.js (category dropdown + text) |
| Dosage          | ✅ Free text        | ✅                             |
| Frequency       | ✅ Dropdown (13 options) | ✅                        |
| Duration (days) | ✅                  | ✅                             |
| Quantity        | ✅                  | ✅                             |
| Instruction     | ✅                  | ✅                             |
| Advice          | ✅                  | ✅                             |
| Diagnosis (ICD-10) | ✅ Search + hidden field | ❌ Not present          |
| NHIS warning    | ✅ Shown on load    | ❌ Not shown                  |
| CDS alerts      | ✅ Live AJAX check  | ❌ Not present                |
| Black box warning | ✅               | ❌ Not present                |
| Route of admin  | ❌ Not in either modal | ❌ Not present            |

### UI Issues

1. Two "Add" buttons on the same page with different capabilities — confusing to doctors.
2. `#multiMedicationModal` uses a **CDN-loaded jQuery UI** (Google APIs), creating an external dependency risk.
3. Embedded `<script language="javascript">` inside modal body (legacy, non-standard).
4. Seven blank `<br>` spacer blocks below the medications table (legacy layout debt).
5. The "NHIS" badge column in the medication list reads `nhis_drug_code` — this field is only populated from `patientMedication()` query if `pharmacy_billing_queue` has it; it is **not in `iop_medication` baseline schema**.
6. Dispensing status badge reads from `dispensing_status` on `iop_medication`, but the canonical status lives in `pharmacy_billing_queue.dispense_status` — these can diverge.

---

## 4. DATABASE STRUCTURE REVIEW

### Core Tables

| Table                        | Engine   | Charset  | FK Support | Purpose                          |
|------------------------------|----------|----------|------------|----------------------------------|
| `iop_medication`             | MyISAM   | latin1   | ❌ No      | Primary prescription store        |
| `medicine_drug_name`         | MyISAM   | latin1   | ❌ No      | Drug master / formulary           |
| `medicine_category`          | MyISAM   | latin1   | ❌ No      | Drug categories                   |
| `pharmacy_billing_queue`     | MyISAM   | latin1   | ❌ No      | Billing trigger queue             |
| `pharmacy_audit_log`         | MyISAM   | latin1   | ❌ No      | Dispensing audit trail            |
| `iop_medication_administration`| MyISAM | latin1   | ❌ No      | Nurse administration log          |
| `pharmacy_stock_batches`     | InnoDB   | varies   | ✅ Partial | Batch stock tracking              |

### `iop_medication` Base Schema (from `hms_master.sql`)

```sql
iop_med_id    INT(11) AUTO_INCREMENT  PK
iop_id        VARCHAR(25)             ← no index, no FK
medicine_id   VARCHAR(50)             ← should be INT; stores drug_id or null
instruction   TEXT
advice        TEXT
days          INT(2)
total_qty     INT(5)
InActive      INT(1)
dDate         DATETIME
cPreparedBy   VARCHAR(25)             ← legacy doctor field
```

### Columns Added by Runtime Migrations (not in base schema)

| Column              | Added By                          | Notes                              |
|---------------------|-----------------------------------|------------------------------------|
| `dosage`            | `ensure_medication_schema()`      | TEXT — no length constraint        |
| `medicine_text`     | Legacy code path                  | Free-text drug name                |
| `frequency`         | pharmacy model / CDS model        | VARCHAR — checked via SHOW COLUMNS |
| `prescribed_by`     | pharmacy model                    | VARCHAR(25) doctor ID              |
| `dispensing_status` | pharmacy model                    | VARCHAR(20), mirrors billing queue |
| `payment_status`    | `ensure_pharmacy_ghs_schema()`    | VARCHAR(20)                        |
| `diagnosis_code`    | CDS model Phase 2                 | ICD-10 linkage                     |
| `diagnosis_description` | CDS model Phase 2             | Text description                   |

### Critical Schema Defects

1. **`medicine_id` is VARCHAR(50)** — joins to `medicine_drug_name.drug_id` (INT). No implicit index match. Every medication list query does an implicit type cast, preventing index use.
2. **No index on `iop_medication.iop_id`** — every call to `patientMedication($iop_no)` does a full table scan.
3. **No index on `iop_medication.InActive`** — filter always hits full table.
4. **`iop_medication` is MyISAM** — no transaction support. Partial batch inserts cannot be rolled back.
5. **`pharmacy_billing_queue.iop_id` is VARCHAR(11)** — but `iop_medication.iop_id` is VARCHAR(25). Potential truncation on join for longer visit IDs.
6. **`dDate` uses lowercase `h:i:s`** in IPD save — 12-hour clock with no AM/PM marker. Audit trail ambiguity.
7. **`ensure_medication_schema()`** only adds `dosage`. No other migration covers `iop_id` indexing, `medicine_id` type fix, or `prescribed_by` in the base model — they are spread across 4+ different models.

---

## 5. PRESCRIPTION DATA MODEL ANALYSIS

### How Dose/Frequency/Duration Are Stored

| Field       | Storage Format      | Validation         | Risk                              |
|-------------|---------------------|--------------------|-----------------------------------|
| `dosage`    | Free TEXT           | None               | "500mg", "1 tab", "2 tsp" — inconsistent |
| `frequency` | Full string: "BD (Twice daily)" | Whitelist in single-entry only | Stored as long string — not codeable |
| `days`      | INT(2)              | None               | Max 99 days — chronic prescriptions impossible |
| `total_qty` | INT(5)              | None               | Not auto-calculated from days × frequency |
| `instruction` | Free TEXT         | None               | Unstructured                      |
| `advice`    | Free TEXT           | None               | Unstructured                      |
| Route of admin | **Not stored** | **Not in schema**  | GHS standard field — missing entirely |
| Drug form   | **Not stored**      | **Not in schema**  | Tablet/Syrup/Injection — missing  |
| Diagnosis link | `diagnosis_code` (ICD-10) | NHIS patients only | Only via single-entry path   |

### Quantity Calculation

**There is no automatic quantity calculation.** The doctor manually enters both `days` and `total_qty`. There is no formula enforced: `qty = dose × frequency_per_day × days`. This creates:
- Over-prescription risk (too many units issued)
- Under-prescription risk (too few dispensed)
- Billing discrepancy (qty billed ≠ qty clinically needed)

### Free Text Risk

`medicine_text` allows completely free drug name entry. There is no validation against a formulary. A doctor can type "panadol", "Panadol", "PANADOL", or "PCM" for the same drug — creating de-duplicated billing issues and NHIS rejection risk.

---

## 6. PHARMACY WORKFLOW ANALYSIS

### Prescription → Pharmacy Pipeline

```
iop_medication (prescription saved)
        │
        ├─── [Flow A: Single save_medication()] ─────────────────────────────┐
        │    pharmacy_model->create_or_update_pharmacy_bill()                 │
        │    → INSERT pharmacy_billing_queue (payment_status=PENDING)         │
        │                                                                     │
        ├─── [Flow B: Batch save_medication_batch()] ────────────────────────┤
        │    Same: create_or_update_pharmacy_bill()                           │
        │    → INSERT pharmacy_billing_queue (payment_status=PENDING)         │
        │                                                                     │
        ▼                                                                     │
pharmacy_billing_queue                                                        │
        │                                                                     │
        ▼                                                                     │
Pharmacist opens app/pharmacy/patient/{iop_id}                               │
        │    ensure_billing_queue_for_visit() — sync missing entries ◄────────┘
        │
        ├─── Individual: log_action() → RESERVED / DISPENSED / PARTIAL
        │    → UPDATE pharmacy_billing_queue.dispense_status
        │    → UPDATE iop_medication.dispensing_status
        │    → INSERT iop_medication_administration
        │
        └─── Bulk: bulk_dispense() → all PENDING → DISPENSED
```

### Identified Pharmacy Gaps

1. **No stock deduction on dispense.** When a pharmacist marks a drug as DISPENSED, `nStock` in `medicine_drug_name` is **not decremented**. The `pharmacy_stock_batches` table exists but is not updated in the dispense flow.
2. **`ensure_billing_queue_for_visit()`** is a corrective sync — it runs every time the pharmacist opens the patient page. This is a self-healing workaround for broken triggers, not a reliable pipeline.
3. **Partial dispensing** is supported in the status enum but there is no UI to specify what partial quantity was given — the nurse administration log (`dose_given`) is the only granular record.
4. **No "external pharmacy" routing.** NHIS patients who purchase externally have no structured flag — the `EXTERNAL` status in the worklist filter exists but no prescription workflow sets it.
5. **Duplicate dispensing risk.** There is no UNIQUE constraint preventing two DISPENSED records for the same `iop_med_id` in `iop_medication_administration`. A pharmacist can click DISPENSE twice.

---

## 7. BILLING & NHIS INTEGRATION AUDIT

### Two Billing Engines — Root Conflict

| Engine                           | Triggered By             | Table Written To             |
|----------------------------------|--------------------------|------------------------------|
| `pharmacy_model->create_or_update_pharmacy_bill()` | Flow A + B | `pharmacy_billing_queue` |
| `unified_billing->add_to_billing_queue()` | Flow A only    | `unified_billing_queue`      |

**Flow B (batch) only writes to `pharmacy_billing_queue` — not to `unified_billing_queue`.**
This means drugs prescribed via the batch modal may not appear in the unified billing system. If the cashier or billing engine reads from `unified_billing_queue` as the source of truth, batch-prescribed drugs are invisible.

### Price Field Inconsistency

| Context                              | Price Field Used       |
|--------------------------------------|------------------------|
| `pharmacy_model->create_or_update_pharmacy_bill()` | `medicine_drug_name.nPrice` |
| `unified_billing->add_to_billing_queue()` | `medicine_drug_name.selling_price` |
| `pharmacy_stock_batches` (stock receive) | `selling_price` |

`nPrice` and `selling_price` are two different columns on `medicine_drug_name`. The base schema only defines `nPrice`. `selling_price` is added by stock model migrations. **A drug could be billed at two different prices depending on which system calculates it.**

### NHIS Gate Enforcement

| Prescription Path    | NHIS Payment Gate | NHIS Formulary Check | Diagnosis Requirement |
|---------------------|-------------------|-----------------------|-----------------------|
| OPD single (`save_medication`) | ✅ Enforced | ✅ Checked | ✅ Warning shown |
| OPD batch (`save_medication_batch`) | ❌ Not checked | ❌ Not checked | ❌ Not captured |
| IPD (`ipd/save_medication`) | ❌ Not checked | ❌ Not checked | ❌ Not captured |
| Nurse (`nurse_module/save_medication`) | ❌ Not checked | ❌ Not checked | ❌ Not captured |

### NHIS Billing Risk

An NHIS patient prescribed drugs via the batch modal will:
1. Have the drug appear in `pharmacy_billing_queue` as PENDING
2. Never enter `unified_billing_queue`
3. Never have diagnosis code linked to the prescription
4. Be billed at `nPrice` instead of the potentially different `nhis_price`
5. Have no NHIS audit log entry

---

## 8. CLINICAL SAFETY & PRESCRIPTION ACCURACY

### CDS (Clinical Decision Support) Coverage

| Safety Check                        | Single Entry | Batch Entry | IPD | Nurse |
|-------------------------------------|-------------|-------------|-----|-------|
| Drug allergy check                  | ✅          | ❌          | ❌  | ❌    |
| Duplicate drug check                | ✅          | ❌          | ❌  | ❌    |
| Dose range validation               | ✅          | ❌          | ❌  | ❌    |
| Paediatric weight-based dosing      | ✅          | ❌          | ❌  | ❌    |
| Black box warning                   | ✅          | ❌          | ❌  | ❌    |
| Drug-drug interaction               | ✅          | ❌          | ❌  | ❌    |
| Pregnancy safety class              | ✅          | ❌          | ❌  | ❌    |
| NHIS formulary coverage             | ✅          | ❌          | ❌  | ❌    |
| Supervisor override logging         | ✅          | ❌          | ❌  | ❌    |

**The CDS system is sophisticated and well-designed for the single-entry path. However, it is entirely absent from the batch path — the more commonly used one for multi-drug prescriptions.**

### Route of Administration — Missing
GHS prescription standards require route of administration (Oral, IV, IM, Topical, etc.). This field **does not exist** in `iop_medication` anywhere in the codebase.

### Maximum Duration — Missing
`days INT(2)` enforces a maximum of 99 days. Chronic disease prescriptions (e.g., hypertension: 90-day supply) are at the limit. There is no minimum enforcement — a doctor can enter 0 days.

---

## 9. PERFORMANCE ANALYSIS

### Confirmed Performance Issues

#### `save_medication_batch()` — N×3 SHOW COLUMNS Per Drug

```php
// Inside the foreach loop — runs for EVERY drug in the batch:
$col_q  = $this->db->query("SHOW COLUMNS FROM `iop_medication` LIKE 'frequency'");
$col_q2 = $this->db->query("SHOW COLUMNS FROM `iop_medication` LIKE 'prescribed_by'");
$col_q3 = $this->db->query("SHOW COLUMNS FROM `iop_medication` LIKE 'dispensing_status'");
```

A batch of 10 drugs = **30 schema inspection queries** before any inserts run. On a busy OPD with concurrent saves, this creates unnecessary lock contention on the information schema.

#### `patientMedication()` — No Index on `iop_id`

```sql
SELECT ... FROM iop_medication A
LEFT JOIN medicine_drug_name B ON B.drug_id = A.medicine_id  -- VARCHAR vs INT cast
LEFT JOIN users C ON C.user_id = A.cPreparedBy               -- no index on cPreparedBy
WHERE A.iop_id = ? AND A.InActive = 0                        -- no index on iop_id
```

Three unindexed fields in one query. As `iop_medication` grows, this becomes a full table scan on every page load.

#### `ensure_billing_queue_for_visit()` — Called Every Pharmacy Page Load

This method fetches all prescriptions for a visit, then checks each against `pharmacy_billing_queue` one by one. For a patient with 15 drugs: 1 bulk fetch + 15 individual lookups = 16 queries per pharmacy page load.

---

## 10. SECURITY & AUDIT TRAIL

### Doctor Linkage Inconsistency

| Field        | Table             | Path               | Populated?         |
|--------------|-------------------|--------------------|--------------------|
| `prescribed_by` | `iop_medication` | OPD single save   | ✅ When column exists |
| `prescribed_by` | `iop_medication` | OPD batch save    | ✅ When column exists |
| `cPreparedBy`   | `iop_medication` | IPD save          | ✅ (legacy field) |
| `cPreparedBy`   | `iop_medication` | Nurse save        | ✅ (but this is the nurse, not the doctor) |
| `prescribed_by` | `iop_medication` | IPD save          | ❌ Not populated   |
| `prescribed_by` | `iop_medication` | Nurse save        | ❌ Not populated   |

Two different fields (`prescribed_by` and `cPreparedBy`) represent the prescribing clinician. They are not consistently populated. In `patientMedication()`, the query JOINs on `cPreparedBy` for the displayed name — meaning drugs prescribed via the modern OPD path show the correct doctor, but drugs saved via IPD or nurse module may show nothing or the wrong user.

### Edit / Delete Controls

- OPD delete (`delete_medication`) requires `doctor_write_allowed_or_redirect()` and `nStatus == "Pending"` guard in the UI. ✅
- IPD delete sets `InActive = 1` with permission check. ✅
- Nurse delete requires admin role. ✅
- **No soft-delete audit record is written on deletion.** The record's `InActive` flips to 1 but no "DELETED BY user X at time Y" entry exists in `pharmacy_audit_log` or any log table.

### XSS / Input Validation

- Single-entry path: `strip_tags()` not applied — raw POST input goes to DB.
- Batch path: `trim()` applied but no `strip_tags()` or `htmlspecialchars()` on server side for `medicine_text`.
- The drug name displayed in the medication table uses `$rows->drug_name` without `htmlspecialchars()` in the view.

---

## 11. SYSTEM DUPLICATION & CONFLICT ANALYSIS

### ⚠️ VERDICT: Single Source of Truth Does NOT Exist

**Multiple parallel systems exist for prescription saving:**

| Dimension          | System 1 (OPD Single)     | System 2 (OPD Batch)       | System 3 (IPD)        | System 4 (Nurse) |
|--------------------|---------------------------|-----------------------------|-----------------------|------------------|
| **Controller**         | `opd.php::save_medication` | `opd.php::save_medication_batch` | `ipd.php::save_medication` | `nurse_module.php::save_medication` |
| **NHIS Gate**          | ✅                         | ❌                          | ❌                    | ❌               |
| **CDS Check**          | ✅                         | ❌                          | ❌                    | ❌               |
| **Transaction**        | ❌ (single insert)         | ❌ (loop, no trans)         | ❌                    | ❌               |
| **Billing: pharmacy queue** | ✅                   | ✅                          | ❌                    | ❌               |
| **Billing: unified queue**  | ✅                   | ❌                          | ❌                    | ❌               |
| **Audit log**          | ✅                         | ❌                          | ❌                    | ❌               |
| **prescribed_by**      | ✅                         | ✅                          | ❌                    | ❌               |
| **iop_id decode**      | ✅ url_decode_id()         | ✅                          | ❌ Raw POST           | ❌ Raw POST      |

### All Duplicated Sub-Systems

1. **Two prescription entry UIs** on same page (single vs batch modal)
2. **Two billing engines** (`pharmacy_billing_queue` vs `unified_billing_queue`)
3. **Two price sources** (`nPrice` vs `selling_price`)
4. **Two doctor linkage fields** (`prescribed_by` vs `cPreparedBy`)
5. **Four save endpoints** writing to the same `iop_medication` table with different validation levels
6. **Billing queue recovery mechanism** (`ensure_billing_queue_for_visit`) exists because the primary trigger is unreliable

---

## 12. GHS / NHIS COMPLIANCE CHECK

| GHS/NHIS Requirement                    | Status   | Notes                                       |
|-----------------------------------------|----------|---------------------------------------------|
| Doctor identification on prescription   | ⚠️ Partial | `prescribed_by` missing on IPD/Nurse paths |
| Standard frequency notation (OD/BD/TDS) | ⚠️ Partial | Single entry only; batch is free text       |
| ICD-10 diagnosis linkage               | ⚠️ Partial | Single entry only; batch has no field       |
| Route of administration                 | ❌ Missing | Not in schema at all                        |
| Drug form (tablet/syrup/injection)      | ❌ Missing | Not in schema at all                        |
| Dispensing accountability (pharmacist ID) | ✅      | `iop_medication_administration.admin_id`    |
| Dispensing timestamp                    | ✅        | `dDateTime` in admin table                  |
| NHIS formulary enforcement              | ⚠️ Partial | Single entry only                          |
| Billing traceability                    | ⚠️ Partial | Batch path misses unified billing queue    |
| Prescription audit trail (full lifecycle) | ⚠️ Partial | `pharmacy_audit_log` exists but not all paths write to it |
| Generic/brand substitution flag         | ❌ Missing | No substitution logic anywhere             |
| Maximum/minimum dose enforcement        | ⚠️ Partial | CDS checks exist in single entry only      |

---

## 13. RISK ANALYSIS

| # | Issue | Severity | Category |
|---|-------|----------|----------|
| 1 | Batch modal bypasses CDS, NHIS gate, billing audit | **CRITICAL** | Clinical Safety + Billing |
| 2 | Two billing engines — unified queue never receives batch drugs | **CRITICAL** | Financial / Billing |
| 3 | IPD `save_medication` stores URL-safe `iop_id` raw (no decode) | **CRITICAL** | Data Integrity |
| 4 | No transaction on batch save — partial inserts unrecoverable | **CRITICAL** | Data Integrity |
| 5 | SHOW COLUMNS ×3 per drug inside batch loop | **HIGH** | Performance |
| 6 | `medicine_id` VARCHAR vs INT — implicit cast kills index | **HIGH** | Performance |
| 7 | No index on `iop_medication.iop_id` | **HIGH** | Performance |
| 8 | `nPrice` vs `selling_price` — different billing amounts | **HIGH** | Financial |
| 9 | No stock deduction on dispense | **HIGH** | Inventory / Financial |
| 10 | `cPreparedBy` vs `prescribed_by` — doctor identity ambiguity | **HIGH** | Audit Trail / NHIS |
| 11 | Route of administration missing from schema | **HIGH** | GHS Compliance |
| 12 | No quantity calculation formula | **HIGH** | Clinical Safety |
| 13 | Drug name XSS risk in view without `htmlspecialchars()` | **HIGH** | Security |
| 14 | No soft-delete audit record on medication removal | **MEDIUM** | Audit Trail |
| 15 | `days INT(2)` — max 99 days for chronic disease | **MEDIUM** | Clinical Workflow |
| 16 | Duplicate dispensing possible in `iop_medication_administration` | **MEDIUM** | Data Integrity |
| 17 | `pharmacy_billing_queue.iop_id` VARCHAR(11) vs VARCHAR(25) | **MEDIUM** | Data Integrity |
| 18 | Legacy CDN jQuery UI dependency in batch modal | **LOW** | Reliability |
| 19 | MyISAM engine on all medication tables | **MEDIUM** | Reliability |
| 20 | Free-text drug names not normalised against formulary | **MEDIUM** | NHIS Compliance |

---

## 14. RECOMMENDED ARCHITECTURE (No Implementation — Design Only)

### Ideal Unified Prescription Flow

```
Doctor prescribes (single or multi-drug)
        │
        ▼
[SINGLE UNIFIED ENDPOINT: app/opd/save_prescription_batch]
        │
        ├── 1. Validate session + permissions (doctor_write_allowed)
        ├── 2. Decode iop_id (url_decode_id)
        ├── 3. Validate iop_id exists + visit is open
        ├── 4. Check NHIS payment gate (once per batch, not per drug)
        ├── 5. For each drug:
        │      ├── CDS safety check (allergy, interaction, dose, black box)
        │      ├── NHIS formulary check (if NHIS patient)
        │      ├── Duplicate prescription check (same drug, same visit)
        │      └── Build insert row with all required fields
        ├── 6. db->trans_start()
        │      └── INSERT all rows into iop_medication
        ├── 7. db->trans_complete()
        ├── 8. [POST-COMMIT, non-blocking]
        │      ├── Create pharmacy_billing_queue entries (ONE engine only)
        │      ├── Write unified_billing_queue entries
        │      ├── Log NHIS audit (all drugs)
        │      └── Log prescribed_by for all rows
        └── 9. Return structured JSON (saved/ignored/blocked counts)
```

### Unified Prescription Data Model (Recommended Schema)

```sql
iop_medication (unified) should include:
  iop_med_id        INT AUTO_INCREMENT PK
  iop_id            VARCHAR(25)  NOT NULL  -- INDEX required
  medicine_id       INT(11)      NULL      -- FK to medicine_drug_name.drug_id (INT)
  medicine_text     VARCHAR(255) NULL      -- free-text fallback
  dosage            VARCHAR(100) NULL      -- "500mg", "1 tab"
  frequency         VARCHAR(10)  NULL      -- "OD","BD","TDS","QDS","STAT" (code only)
  frequency_label   VARCHAR(50)  NULL      -- human label stored separately
  route             VARCHAR(30)  NULL      -- "ORAL","IV","IM","TOPICAL"
  drug_form         VARCHAR(30)  NULL      -- "TABLET","CAPSULE","SYRUP","INJECTION"
  days              INT(3)       NULL      -- up to 999 days
  total_qty         DECIMAL(8,2) NULL      -- calculated or manual
  instruction       TEXT         NULL
  advice            TEXT         NULL
  diagnosis_code    VARCHAR(10)  NULL      -- ICD-10
  diagnosis_desc    VARCHAR(255) NULL
  prescribed_by     VARCHAR(25)  NOT NULL  -- ALWAYS required, doctor session user_id
  dispensing_status VARCHAR(20)  DEFAULT 'PENDING'
  payment_status    VARCHAR(20)  DEFAULT 'PENDING'
  is_nhis_covered   TINYINT(1)   DEFAULT 0
  nhis_price        DECIMAL(10,2) DEFAULT 0
  dDate             DATETIME     NOT NULL  -- always uppercase H:i:s
  InActive          TINYINT(1)   DEFAULT 0
  INDEX (iop_id), INDEX (prescribed_by), INDEX (dispensing_status)
  ENGINE = InnoDB  -- required for FK and transaction support
```

### Single Billing Source of Truth

- **One billing engine** — `pharmacy_billing_queue` as the authoritative store.
- **`unified_billing_queue` to sync FROM `pharmacy_billing_queue`**, not receive direct writes.
- **One price field** — `medicine_drug_name.selling_price` (add it to base schema; deprecate `nPrice`).

### Clean Pharmacy Flow

```
pharmacy_billing_queue (PENDING)
        │
        ├── Pharmacist views worklist (no corrective sync needed)
        ├── Pharmacist marks DISPENSED → stock deducted from pharmacy_stock_batches
        ├── pharmacy_audit_log entry written (who, when, qty)
        └── iop_medication.dispensing_status updated (mirror only, not source of truth)
```

---

## 15. WORKFLOW DIAGRAM (Text)

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    CURRENT STATE (FRAGMENTED)                           │
│                                                                         │
│  Doctor                                                                 │
│    │                                                                    │
│    ├──[Single Drug]──► #myModal ──► save_medication() ─────────────────┤
│    │                                    │ NHIS ✅ CDS ✅ Billing ✅    │
│    │                                    ▼                              │
│    │                              pharmacy_billing_queue               │
│    │                              unified_billing_queue ◄──────────────┤
│    │                                                                    │
│    ├──[Multi Drug]───► #multiMedicationModal                           │
│    │                      │                                            │
│    │                      ▼                                            │
│    │               save_medication_batch()                             │
│    │               NHIS ❌ CDS ❌ Audit ❌                             │
│    │                      │                                            │
│    │                      ▼                                            │
│    │               pharmacy_billing_queue ONLY                         │
│    │                                                                    │
│    ├──[IPD]──────────► ipd/save_medication() ─► iop_medication         │
│    │                   NHIS ❌ CDS ❌ Billing ❌ iop_id raw ❌         │
│    │                                                                    │
│    └──[Nurse]─────────► nurse_module/save_medication()                 │
│                         NHIS ❌ CDS ❌ Billing ❌                      │
│                                                                         │
│  Pharmacist                                                             │
│    │                                                                    │
│    └──► app/pharmacy/patient/{iop_id}                                  │
│            ensure_billing_queue_for_visit() ← corrective sync          │
│            DISPENSE → iop_medication_administration                    │
│                     → pharmacy_billing_queue (status update)           │
│                     ❌ No stock deduction                              │
└─────────────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────────────┐
│                    RECOMMENDED STATE (UNIFIED)                          │
│                                                                         │
│  Doctor                                                                 │
│    │                                                                    │
│    └──[Any drug count]──► Single unified modal                         │
│                              ▼                                          │
│                   save_prescription_batch() (unified endpoint)          │
│                   NHIS ✅ CDS ✅ Transaction ✅ Audit ✅               │
│                              │                                          │
│                              ▼                                          │
│                       iop_medication (InnoDB)                           │
│                              │                                          │
│                              ▼ [post-commit]                            │
│                       pharmacy_billing_queue (single engine)            │
│                              │                                          │
│                              ▼ [sync]                                   │
│                       unified_billing_queue                             │
│                                                                         │
│  Pharmacist                                                             │
│    └──► app/pharmacy/patient/{iop_id}                                  │
│            View billing queue (no corrective sync needed)               │
│            DISPENSE → deduct pharmacy_stock_batches                    │
│                     → pharmacy_audit_log ✅                            │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## 16. RECOMMENDATION ROADMAP (No Code)

### Phase A — Critical Safety Fixes (Before Any New Feature)
1. Merge single-entry and batch save into one backend endpoint with full NHIS + CDS enforcement
2. Add transaction wrapper to all batch medication saves
3. Hoist `SHOW COLUMNS` checks outside loops in `save_medication_batch()`
4. Fix IPD `save_medication()` to `url_decode_id()` the `iop_id`
5. Enforce NHIS payment gate and formulary check on all prescription paths

### Phase B — Data Model Hardening
1. Add index on `iop_medication.iop_id`
2. Migrate `medicine_id` from VARCHAR(50) to INT(11) with a safe migration script
3. Unify doctor linkage into `prescribed_by` — deprecate `cPreparedBy`
4. Add `route` and `drug_form` columns to `iop_medication`
5. Convert `iop_medication` engine from MyISAM to InnoDB
6. Standardise `dDate` to uppercase `H:i:s` (24-hour) across all paths

### Phase C — Billing Unification
1. Designate `pharmacy_billing_queue` as the single billing source of truth
2. Refactor `unified_billing_queue` to sync FROM `pharmacy_billing_queue`
3. Unify price field: `selling_price` only — remove `nPrice` from billing calculations
4. Wire stock deduction into the dispense confirmation flow

### Phase D — UI Consolidation
1. Remove `#myModal` (legacy single-entry form) — consolidate into one modern modal
2. Add Route of Administration field to the prescription modal
3. Add real-time quantity calculator (days × frequency × dose)
4. Add NHIS coverage badge at drug selection time (not just on save)
5. Bring CDS safety alerts into the batch modal

### Phase E — Compliance & Audit
1. Write deletion audit records to `pharmacy_audit_log` on every soft-delete
2. Add UNIQUE constraint to `iop_medication_administration (iop_med_id, status)` to prevent duplicate dispense
3. Ensure `diagnosis_code` is required for NHIS patients across all paths
4. Add `generic_name` and substitution flag to drug master for GHS generics policy

---

## FINAL VERDICT

```
╔══════════════════════════════════════════════════════════════╗
║                                                              ║
║   ⚠️  NOT PRODUCTION-READY FOR NHIS-REGULATED ENVIRONMENTS  ║
║                                                              ║
║   The Medication Module is FRAGMENTED, not unified.          ║
║                                                              ║
║   The OPD single-entry path is clinically sound.            ║
║   The batch, IPD, and nurse paths are unsafe.               ║
║                                                              ║
║   Dual billing engines create financial reconciliation risk. ║
║   CDS and NHIS gate are bypassed on the most-used path.     ║
║   MyISAM tables prevent atomic transaction guarantees.       ║
║                                                              ║
║   Modernization is feasible and well-structured.            ║
║   The CDS model, pharmacy audit system, and NHIS            ║
║   formulary logic are all high-quality foundations.         ║
║   They simply need to be applied uniformly.                 ║
║                                                              ║
╚══════════════════════════════════════════════════════════════╝
```

---

*End of Medication Module Pre-Implementation Audit Report*
*File: `docs/MEDICATION_MODULE_AUDIT_REPORT.md`*
*Next step: Phase A — Critical Safety Fixes (implementation, not yet begun)*
