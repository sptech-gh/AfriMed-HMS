# Complaints Module — Full Audit & Investigation Report

**System:** Hospital Management System (HMS)  
**Standard:** Ghana Health Service (GHS) / NHIS Alignment  
**Prepared:** April 2026  
**Status:** Pre-Implementation — Awaiting Approval  
**Priority:** HIGH — Core Clinical Documentation Module  

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [End-to-End Workflow Analysis](#2-end-to-end-workflow-analysis)
3. [UI/UX Audit](#3-uiux-audit)
4. [Database Structure Review](#4-database-structure-review)
5. [Complaints Master Data Analysis](#5-complaints-master-data-analysis-ghs-alignment)
6. [NHIS / GHS Documentation Compliance](#6-nhis--ghs-documentation-compliance)
7. [Performance Analysis](#7-performance-analysis)
8. [Security & Data Integrity](#8-security--data-integrity)
9. [Risk Analysis](#9-risk-analysis)
10. [Recommended Architecture](#10-recommended-architecture)
11. [Implementation Plan](#11-implementation-plan)
12. [Summary of All Identified Issues](#12-summary-of-all-identified-issues)

---

## 1. Executive Summary

The Complaints module is one of the **most clinically critical** modules in the OPD workflow — it is the first structured clinical entry a doctor makes. The current implementation is **functional but severely outdated**, carrying a legacy design from an older system generation that is incompatible with modern GHS/NHIS documentation standards, real-world doctor workflow speed requirements, and current HMS architecture patterns already adopted in the Diagnosis and Medication modules.

**Overall Health Rating: 4 / 10**

| Dimension | Score | Notes |
|-----------|-------|-------|
| UI/UX | 3/10 | Two conflicting modals; no search; no tag-select |
| Database Schema | 4/10 | Missing columns, wrong types, no indexes |
| GHS/NHIS Compliance | 3/10 | No doctor linkage, no time audit, missing complaints |
| Backend Logic | 5/10 | Bug fixed today; batch save works but fragile |
| Performance | 5/10 | No indexes; CDN dependency; no bulk insert transaction |
| Security | 6/10 | Basic XSS/CSRF in place; no duplicate prevention |

---

## 2. End-to-End Workflow Analysis

### 2.1 Current Flow

```
Doctor opens /app/opd/complain/{iop}/{patient_no}
    → Page loads full list from `complain` master table (46 entries)
    → Doctor clicks "Add Complaints" button
    → Legacy modal (#myModal) OR new Multi-Entry modal (#multiComplaintModal) appears
    → Doctor selects ONE complaint from a <select> dropdown
    → If "Others" selected → text field appears
    → Doctor clicks Save → Full page POST to save_complain()
    → Page reloads
    → Repeat for each additional complaint (one at a time)
```

### 2.2 Critical Flow Problems

| # | Problem | Severity |
|---|---------|----------|
| 1 | **Two modals coexist** — old `#myModal` form and new `#multiComplaintModal` are both in the DOM. The "Add Complaints" button triggers `#multiComplaintModal`, but the old form posting to `save_complain` is also present as dead code that could accidentally fire | CRITICAL |
| 2 | One complaint at a time in legacy modal — full page reload per entry | HIGH |
| 3 | Multi-entry modal was broken (bug fixed April 2026 — wrong column name `complain` vs `complain_text`) | HIGH |
| 4 | No AJAX feedback after save — page redirect wipes context | MEDIUM |
| 5 | No duplicate complaint prevention at any layer | MEDIUM |

### 2.3 Downstream Integration

Complaints feed into the following downstream areas:

| Area | Integration | Status |
|------|------------|--------|
| Print Report (`/app/ipd_print/print_complain`) | Renders `complain_name` + `remarks` | ⚠ Missing `complain_text` (free-text not shown) |
| PDF Report (`/app/ipd_print/pdf_complain`) | Same as print | ⚠ Same gap |
| Discharge Summary | Navigation link only | ✗ No complaints data pulled into summary |
| Diagnosis module | Separate page | ✗ No cross-reference/linkage |
| Visit Summary / EMR view | Navigation link only | ✗ Not surfaced on main view |

---

## 3. UI/UX Audit

### 3.1 Legacy Modal (`#myModal`) — Still in the DOM

```
- Bootstrap 2-era table-inside-modal layout
- Single <select> dropdown — doctor scrolls through 46 ALL-CAPS entries
- "Others" toggle shows a plain text input
- One field for "Remarks"
- Full page POST on Save → full reload
- No search, no multi-select, no tags
```

### 3.2 Multi-Entry Modal (`#multiComplaintModal`) — The "New" System

```
- Added as an overlay on top of legacy system
- Each entry row = one plain text input (free text only)
- No connection to complain master table at all
- Duration + Severity fields exist but are not displayed in the complaints table
- Save → AJAX → was broken until April 2026
- No inline validation
- Save All button disabled until entries added (good)
```

### 3.3 Audit Findings

| Area | Finding | Rating |
|------|---------|--------|
| Modal design | Gradient header (pink/red), functional but generic | 4/10 |
| Consultation speed | Slow — must manually type each complaint | 2/10 |
| Search | **Zero** — no search in either modal | 0/10 |
| Multi-select | New modal adds rows; old modal is single-only | 3/10 |
| Structured select | Old modal has master list; new modal has **none** | 2/10 |
| Mobile responsiveness | Bootstrap modal is responsive but inputs are cramped | 4/10 |
| Visual feedback | No success/fail indicator in modal — only page reload | 3/10 |
| Dead code | Old `#myModal` form still in DOM with CSRF and hidden inputs | CRITICAL |

> **Root UX Problem:** The new multi-entry modal **abandoned the structured complaint master list entirely** — a doctor can only type free text. The old modal had the master list but is single-entry and uses a full page POST. Neither modal gives a doctor the full workflow.

---

## 4. Database Structure Review

### 4.1 Table: `complain` — Master Complaints List

```sql
CREATE TABLE `complain` (
  `complain_id`   INT(11) NOT NULL AUTO_INCREMENT,
  `complain_name` VARCHAR(999) DEFAULT NULL,   -- VARCHAR(999)! Wasteful
  `complain_desc` TEXT,                         -- Never displayed in UI
  `InActive`      INT(1) NOT NULL,
  PRIMARY KEY (`complain_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;        -- MyISAM, no FK support
```

**Issues identified:**

| Issue | Detail | Severity |
|-------|--------|----------|
| `VARCHAR(999)` for name | Should be `VARCHAR(150)` | LOW |
| `complain_desc` never rendered | Exists in DB but shown nowhere in UI or reports | LOW |
| `MyISAM` engine | No transactions, no foreign keys, no full-text index | MEDIUM |
| `latin1` charset | No Unicode support | MEDIUM |
| No `category` column | All 46 complaints are in a flat undifferentiated list | HIGH |
| No `sort_order` column | Sorted alphabetically, not by clinical frequency | MEDIUM |
| Missing IDs 1 & 2 | `complain_id` starts at 3 — data integrity gap | LOW |
| No indexes | Only PRIMARY KEY — no category/name index | MEDIUM |

### 4.2 Table: `iop_complaints` — Patient Complaint Records

```sql
-- Baseline schema (hms_master.sql):
CREATE TABLE `iop_complaints` (
  `iop_comp_id` INT(11) NOT NULL AUTO_INCREMENT,
  `iop_id`      VARCHAR(25) NOT NULL,
  `complain_id` VARCHAR(50) DEFAULT NULL,   -- VARCHAR! FK to INT table
  `remarks`     TEXT,
  `dDate`       DATE DEFAULT NULL,          -- DATE only, no time
  `InActive`    INT(1) NOT NULL,
  PRIMARY KEY (`iop_comp_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Live database has additional column (added ad-hoc):
-- `complain_text` TEXT
```

**Issues identified:**

| Issue | Detail | Severity |
|-------|--------|----------|
| `complain_id` is `VARCHAR(50)` | FK to an INT primary key stored as string — type mismatch, prevents FK constraint | HIGH |
| `dDate` is `DATE` not `DATETIME` | No timestamp — only date stored; cannot audit time of entry | HIGH |
| No `recorded_by` / `doctor_id` column | Cannot link complaint to the recording doctor for NHIS audit | CRITICAL |
| No index on `iop_id` | Every `patientComplain()` query does a full table scan as table grows | HIGH |
| `complain_text` missing from baseline SQL | Added ad-hoc — not in install SQL; new installs break silently | CRITICAL |
| `MyISAM` engine | No transaction safety | MEDIUM |
| `latin1` charset | No Unicode support | MEDIUM |
| No `severity` column | Severity captured in modal but crammed into `remarks` as a string | MEDIUM |
| No `duration` column | Duration captured in modal but crammed into `remarks` as a string | MEDIUM |
| No `onset` column | GHS standard — acute/chronic/recurrent onset not captured | LOW |
| `complain_id = 'others'` sentinel | Free-text entries use the string literal `'others'` as the FK value — not a real FK | MEDIUM |

### 4.3 Missing Tables

| Table | Purpose | Status |
|-------|---------|--------|
| `complain_category` | Group complaints by body system (General, Paediatric, Maternal, Chronic) | **MISSING** |
| `complain_suggestion` | Store custom free-text complaints for auto-suggestion learning | **MISSING** |
| `opd_complaints_audit` | Audit trail for edits and deletions | **MISSING** |

---

## 5. Complaints Master Data Analysis (GHS Alignment)

### 5.1 Current 46 Complaints — Inventory

The existing master list covers:

> Abdominal & Pelvic Pain, Abnormal Uterine Bleeding, Anxiety/Depression, Back Pain, Chest Pain, Cough, Cough/Dyspnea (Infant), Crying Infant (Inconsolable), Delirium, Dementia/Memory Loss, Diarrhea, Dysphagia, Dyspnea/Tachypnea, Ear Pain/Otalgia, Edema (Leg), Facial Flushing, Facial Pain, Fever (Acute), Flank Pain, Genital Skin Lesion, Headache, Hearing Loss, Hematuria, Hypotension/Shock, Leg/Bone/Extremity Pain, Limp in Child, Lymphadenopathy, Mental Status Change, Muscle Cramps, Myalgias/Arthralgias, Nausea/Vomiting, Numbness/Sensory Loss, Pruritus, Rash (Generalized), Red Eye, Scrotal Pain, Seizure, Shoulder Pain, Sinus Tachycardia, Syncope, Tinnitus, Tremor, Urinary Symptoms, Vertigo, Weakness/Fatigue/Malaise, Weight Loss

### 5.2 GHS Coverage Gaps

**Present and adequate (✓):**
Abdominal Pain, Back Pain, Chest Pain, Cough, Diarrhea, Fever, Headache, Nausea/Vomiting, Seizure, Weight Loss, Urinary Symptoms, Red Eye, Rash

**Missing — Common in Ghanaian OPD practice:**

| Missing Complaint | Category | Clinical Frequency |
|------------------|----------|--------------------|
| Malaria symptoms (fever + chills) | General | **Very High** |
| General body weakness / malaise (standalone) | General | **Very High** |
| Catarrh / Nasal discharge | General | High |
| Sore throat / Throat pain | ENT | High |
| Boils / Skin infections / Wound | Dermatology | High |
| Poor feeding (infant) | Paediatric | High |
| Convulsions (child) | Paediatric | High |
| Reduced fetal movement | Maternal/ANC | High |
| Vaginal discharge | Maternal/GYN | High |
| Swelling of feet / Oedema in pregnancy | Maternal/ANC | High |
| Hypertension follow-up | Chronic/NCD | High |
| Diabetes mellitus follow-up | Chronic/NCD | High |
| Difficulty breathing / Dyspnoea | Respiratory | High |
| Burning sensation (urinary/chest) | General | Medium |
| Jaundice / Yellow eyes | Hepatic | Medium |
| Mouth ulcers / Oral pain | ENT/Dental | Medium |
| Watery / Itchy eyes | Ophthalmology | Medium |
| Toothache | Dental | Medium |

### 5.3 Format Issues

- All entries in **ALL CAPS** — clinically acceptable but reduces readability and scan speed
- Very long compound names (e.g. `MENTAL STATUS, ACUTE CHANGE (COMA, LETHARGY)`) are hard to scan in a dropdown
- No grouping by body system — doctor scrolls 46 alphabetical entries to find a specific complaint
- No frequency-based ordering — rare complaints appear before common ones alphabetically

---

## 6. NHIS / GHS Documentation Compliance

| NHIS Requirement | Status | Gap |
|-----------------|--------|-----|
| Complaint linked to visit (`iop_id`) | ✅ Present | — |
| Complaint timestamped | ⚠️ Date only, no time | Cannot audit intra-day entry time |
| Complaint linked to recording doctor | ❌ Missing | No `recorded_by` column |
| Structured complaint code | ⚠️ Partial | `complain_id` FK exists but stored as VARCHAR string |
| Free-text complaints capturable | ✅ Present | — |
| Complaint exportable in reports | ⚠️ Partial | Print/PDF exists but free-text (`complain_text`) not shown in print report |
| Complaint editable with audit trail | ❌ No edit | Only soft-delete — no edit history |
| Complaint linked to diagnosis | ❌ Missing | No cross-reference between `iop_complaints` and `iop_diagnosis` |
| Severity documented | ❌ Hidden | Crammed into `remarks` string, not a proper column |
| Duration documented | ❌ Hidden | Crammed into `remarks` string, not a proper column |
| Onset type captured | ❌ Missing | Acute / chronic / recurrent not captured |

**NHIS Compliance Score: 3 / 10**

---

## 7. Performance Analysis

### 7.1 Page Load

| Factor | Finding | Impact |
|--------|---------|--------|
| `ComplainList()` | Loads all 46 master records on every page view — no caching | LOW now, scales poorly |
| `patientComplain()` JOIN | No index on `iop_id` in `iop_complaints` — full table scan | HIGH as data grows |
| jQuery UI CDN | Loaded from `ajax.googleapis.com` on every page load | Network dependent — fails offline |

### 7.2 AJAX / Save Performance

| Factor | Finding | Impact |
|--------|---------|--------|
| Batch insert | Loops individual `INSERT` per complaint — no bulk insert | MEDIUM |
| Transaction wrapping | No `START TRANSACTION` / `COMMIT` around batch loop | Partial saves possible on error |
| Schema check | No `ensure_complaints_schema()` call — fragile on new installs | HIGH |

### 7.3 CDN Risk Assessment

The `complain.php` view loads jQuery UI 1.12.1 from Google CDN:

```html
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/...">
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
```

> **Risk:** Hospital internet connectivity is often intermittent in Ghana. A CDN failure completely disables the complaints modal. The search autocomplete in the multi-entry manager relies on jQuery UI but is not actually used in the complaint module — the dependency is unnecessary and should be removed.

---

## 8. Security & Data Integrity

| Check | Status | Notes |
|-------|--------|-------|
| XSS protection | ✅ `trim\|xss_clean` in legacy form validation | Multi-entry modal relies on controller sanitization |
| CSRF | ✅ `/app/opd/*` excluded in config | Intentional — CSRF token still injected in modal |
| SQL injection | ✅ CodeIgniter query builder + parameterized queries | — |
| Permission check on save | ✅ `doctor_write_allowed_or_redirect()` | Present on all endpoints |
| Permission check on delete | ✅ Same | — |
| Duplicate complaint prevention | ❌ None | Neither UI nor DB prevents duplicate entries |
| Empty complaint validation | ⚠️ Partial | Legacy form: server-side. Multi-entry: JS only, bypassable |
| Audit trail on delete | ⚠️ Soft-delete only | `InActive = 1` set but no log of who deleted or when |
| `complain_id = 'others'` FK value | ⚠️ Brittle | JOIN on `complain B` returns NULL row — `complain_name` renders as blank in table |

---

## 9. Risk Analysis

### 9.1 Breaking Change Assessment

| Change | Risk Level | Mitigation |
|--------|-----------|------------|
| Adding `recorded_by` column to `iop_complaints` | LOW | `ALTER TABLE ADD COLUMN ... DEFAULT NULL` — additive only |
| Adding `severity`, `duration`, `onset` columns | LOW | Same — additive, existing rows get NULL |
| Changing `dDate` to `DATETIME` | LOW | `ALTER TABLE MODIFY COLUMN` — existing date values preserved |
| Adding index on `iop_id` | ZERO | Pure performance gain, no data change |
| Adding `complain_text` to install SQL | LOW | Column already exists in live DB; `ensure_schema` handles it |
| Adding `complain_category` table | ZERO | New table, no impact on existing data |
| Adding `category`, `sort_order`, `is_common` to `complain` | LOW | Additive columns |
| Retiring old `#myModal` form | MEDIUM | Must confirm no other code path triggers `save_complain()` directly |
| Changing `complain_id` VARCHAR → INT FK | HIGH | Would break existing rows with `'others'` sentinel value |

### 9.2 Data Migration Needs

| Migration | Approach |
|-----------|---------|
| `complain_text` column on fresh installs | Add to `ensure_complaints_schema()` self-heal method |
| `complain_id` type change | **Do not change** — keep VARCHAR, add `is_custom TINYINT` column instead |
| Category assignment for 46 existing complaints | One-time `UPDATE complain SET category = '...' WHERE complain_id IN (...)` migration |
| New GHS complaints seeding | `INSERT IGNORE INTO complain ...` — safe to re-run |
| Existing `remarks` containing `Duration: X | Severity: Y` strings | Leave as-is; new columns capture forward going only |

### 9.3 Backward Compatibility

- Legacy `save_complain()` endpoint — keep as-is; it still works correctly
- `patientComplain()` query — extending with new columns is backward-safe (NULL fallback)
- Print/PDF report — can be enhanced without breaking existing output

---

## 10. Recommended Architecture

### 10.1 Database Changes

**`complain` master table additions:**

```sql
ALTER TABLE `complain`
  MODIFY  `complain_name` VARCHAR(150) NOT NULL,
  ADD COLUMN `category`   VARCHAR(50)  DEFAULT 'GENERAL'  AFTER `complain_name`,
  ADD COLUMN `sort_order` INT(3)       DEFAULT 99          AFTER `category`,
  ADD COLUMN `is_common`  TINYINT(1)   DEFAULT 0           AFTER `sort_order`,
  ADD INDEX  `idx_category` (`category`, `InActive`);
```

**`iop_complaints` patient records additions:**

```sql
ALTER TABLE `iop_complaints`
  ADD COLUMN `complain_text` TEXT        DEFAULT NULL,   -- if not exists
  ADD COLUMN `severity`      VARCHAR(20) DEFAULT NULL,
  ADD COLUMN `duration`      VARCHAR(50) DEFAULT NULL,
  ADD COLUMN `onset`         VARCHAR(20) DEFAULT NULL,
  ADD COLUMN `recorded_by`   VARCHAR(25) DEFAULT NULL,
  MODIFY COLUMN `dDate`      DATETIME    DEFAULT NULL,
  ADD INDEX  `idx_iop_id`    (`iop_id`),
  ADD INDEX  `idx_recorded`  (`recorded_by`);
```

**New `complain_category` table:**

```sql
CREATE TABLE `complain_category` (
  `cat_id`     INT(11) NOT NULL AUTO_INCREMENT,
  `cat_code`   VARCHAR(20) NOT NULL,
  `cat_name`   VARCHAR(100) NOT NULL,
  `sort_order` INT(3) DEFAULT 99,
  PRIMARY KEY (`cat_id`),
  UNIQUE KEY `uq_code` (`cat_code`)
);

INSERT IGNORE INTO `complain_category` VALUES
  (1,  'GENERAL',     'General / Systemic',       1),
  (2,  'RESPIRATORY', 'Respiratory',               2),
  (3,  'GI',          'Gastrointestinal',          3),
  (4,  'NEURO',       'Neurological',              4),
  (5,  'MATERNAL',    'Maternal / ANC',            5),
  (6,  'PAEDIATRIC',  'Paediatric',                6),
  (7,  'CHRONIC',     'Chronic / NCD Follow-Up',   7),
  (8,  'ENT',         'ENT / Eye',                 8),
  (9,  'MSK',         'Musculoskeletal',            9),
  (10, 'OTHER',       'Other',                     10);
```

### 10.2 UI Design Approach

**Recommended: Tag-Based Quick-Select + Category Tabs + Search + Custom Entry**

```
┌─────────────────────────────────────────────────────────────────────┐
│  🩺  Add Complaints                                          [×]    │
├─────────────────────────────────────────────────────────────────────┤
│  [ 🔍  Search complaints...                                 ]       │
│                                                                     │
│  ⚡ Common:                                                         │
│  [Fever] [Headache] [Cough] [Abdominal Pain] [Diarrhea]            │
│  [Body Pains] [Vomiting] [Weakness] [Malaria Symptoms]             │
│                                                                     │
│  📂 General  📂 Respiratory  📂 GI  📂 Paediatric  📂 Maternal    │
│                                                                     │
│  Selected (3):                                                      │
│  ╔═══════════╗  ╔════════════╗  ╔════════════════╗                 │
│  ║ Fever  [✕]║  ║ Cough  [✕]║  ║ Headache   [✕] ║                 │
│  ╚═══════════╝  ╚════════════╝  ╚════════════════╝                 │
│                                                                     │
│  Duration: [ 3 days    ]  Severity: [ Moderate ▾ ]  Onset: [Acute] │
│                                                                     │
│  + Add Custom:  [                                    ]  [+ Add]     │
├─────────────────────────────────────────────────────────────────────┤
│                [Cancel]        [💾 Save 3 Complaints]               │
└─────────────────────────────────────────────────────────────────────┘
```

**Key design decisions:**

| Decision | Rationale |
|----------|-----------|
| Quick-select tag buttons for top 8–10 common Ghanaian OPD complaints | One-click selection — fastest possible doctor workflow |
| Category filter tabs | Organised browsing without scrolling a long list |
| Live client-side search filter | No AJAX needed — only 46–60 records; instant response |
| Multi-select with tag chips | Visual confirmation; doctor can see all selected at once |
| Duration / Severity / Onset fields | Saved to proper DB columns — NHIS compliant |
| Custom entry with `+Add` | Free text saved to `complain_text`; optionally pooled as suggestion |
| No CDN jQuery UI dependency | Use simple vanilla JS input filtering — works fully offline |

### 10.3 Backend Logic Changes

| Component | Change |
|-----------|--------|
| `ensure_complaints_schema()` | New method in `opd_model` — self-heals missing columns on any install |
| `save_complaint_batch()` | Enhanced to save `severity`, `duration`, `onset`, `recorded_by`; add DB transaction wrapper; add duplicate guard |
| `patientComplain()` | Update query to return `severity`, `duration`, `recorded_by` |
| `complain.php` display table | Add Severity / Duration columns to the complaints list table |
| `print_complain.php` | Include `complain_text`, `severity`, `duration` in print/PDF output |
| `search_complain_json()` | New optional endpoint — returns complaints by category/search term (or handle client-side) |
| Remove `#myModal` | Delete the legacy form from `complain.php` DOM |

---

## 11. Implementation Plan

### Phase 1 — Database Hardening *(No UI change; safe to deploy independently)*

- [ ] Add `ensure_complaints_schema()` to `opd_model` — self-heals missing columns
- [ ] Add performance index on `iop_complaints.iop_id`
- [ ] Create `complain_category` table and seed data
- [ ] Add `category`, `sort_order`, `is_common` columns to `complain` master
- [ ] Tag all 46 existing complaints with GHS categories and `is_common` flags
- [ ] Seed missing GHS-critical complaints (malaria, catarrh, poor feeding, hypertension FU, etc.)

### Phase 2 — UI Redesign

- [ ] Remove dead `#myModal` legacy form from `complain.php`
- [ ] Build new tag-based complaint modal (pure Bootstrap + vanilla JS — no CDN)
- [ ] Implement quick-select common complaint tag buttons
- [ ] Implement category filter tabs
- [ ] Implement live client-side search filter
- [ ] Implement tag chip display for selected complaints
- [ ] Add per-entry Duration / Severity / Onset fields

### Phase 3 — Backend Logic

- [ ] Update `save_complaint_batch()` — save severity, duration, onset, recorded_by
- [ ] Wrap batch save in a DB transaction
- [ ] Add duplicate complaint guard (same `complain_id` + same `iop_id`)
- [ ] Update `patientComplain()` query — return new fields
- [ ] Update `complain.php` complaints table — show severity / duration columns
- [ ] Update print/PDF report — include free text, severity, duration
- [ ] Add `search_complain_json()` AJAX endpoint if client-side filter is insufficient

### Phase 4 — Testing

- [ ] Save single structured complaint from master list
- [ ] Save multiple complaints in one batch
- [ ] Save custom free-text complaint
- [ ] Verify severity / duration saved correctly in DB
- [ ] Verify print/PDF report shows all fields including free text
- [ ] Test as doctor role (write allowed)
- [ ] Test as non-doctor (read-only view enforced)
- [ ] Test with clinically-cleared visit (Add button locked)
- [ ] Test duplicate complaint prevention
- [ ] Test on fresh install (ensure schema self-heal works)
- [ ] Verify no regression on legacy `save_complain()` POST path

---

## 12. Summary of All Identified Issues

| # | Issue | Layer | Severity |
|---|-------|-------|----------|
| 1 | Two modals coexist — dead `#myModal` form still in DOM | UI | **CRITICAL** |
| 2 | `complain_text` column missing from install SQL | DB | **CRITICAL** |
| 3 | No `recorded_by` (doctor_id) on complaint records | DB | **CRITICAL** |
| 4 | `dDate` is `DATE` not `DATETIME` — no time audit trail | DB | HIGH |
| 5 | No index on `iop_complaints.iop_id` — full table scan | DB | HIGH |
| 6 | `complain_id` is `VARCHAR` FK to `INT` — type mismatch | DB | HIGH |
| 7 | No category grouping in master list | DB/UI | HIGH |
| 8 | Multi-entry modal has no structured master list — free text only | UI | HIGH |
| 9 | Free-text only in new modal — structured complaints inaccessible | UI | HIGH |
| 10 | CDN jQuery UI dependency — fails in offline/poor-internet environments | UI | HIGH |
| 11 | Severity / duration hidden in `remarks` string — not queryable | DB | MEDIUM |
| 12 | Print/PDF report omits `complain_text` (free text invisible in output) | Report | MEDIUM |
| 13 | No duplicate complaint prevention at any layer | Logic | MEDIUM |
| 14 | No complaint → diagnosis cross-reference | Schema | MEDIUM |
| 15 | Batch save has no DB transaction wrapper — partial saves possible | Logic | MEDIUM |
| 16 | Missing GHS-specific complaints (malaria, ANC, chronic follow-up, etc.) | Data | MEDIUM |
| 17 | `complain_id = 'others'` sentinel causes NULL `complain_name` in table | Logic | MEDIUM |
| 18 | ALL CAPS names — reduced readability and scan speed | Data | LOW |
| 19 | `complain_desc` field exists but is never rendered anywhere | DB/UI | LOW |
| 20 | No auto-suggestion learning for custom free-text complaints | Feature | LOW |
| 21 | `MyISAM` engine on both tables — no FK constraints, no transactions | DB | LOW |
| 22 | `latin1` charset — no Unicode / multilingual support | DB | LOW |

---

*Document prepared as part of the HMS Clinical Module Modernisation Programme.*  
*This report covers investigation and audit only. No implementation has been performed.*  
*Awaiting stakeholder review and approval before Phase 1 begins.*
