# Pharmacy Module Comprehensive Audit Report
## Hospital Management System (HMS) - Hebrew Medical Center

**Report Date:** January 2025  
**Prepared By:** Senior Healthcare Systems Architect  
**Module Version:** V2/V3 (Enhanced with GHS Workflow)  
**Status:** AUDIT ONLY - No Implementation

---

## Executive Summary

This comprehensive audit reviews the Pharmacy Module of the HMS system, analyzing architecture, data structures, workflows, NHIS compliance, code quality, and integration points. The module has undergone significant enhancement to support Ghana Health Service (GHS) workflows and NHIS Claim-It protocol requirements.

### Key Findings Summary

| Category | Status | Risk Level | Issues Found |
|----------|--------|------------|--------------|
| Architecture | Good | 🟡 Medium | Multiple schema migrations, some redundancy |
| Data Structure | Good | 🟡 Medium | Multiple sources of truth being consolidated |
| GHS Workflow | Excellent | 🟢 Low | Well-implemented flexible workflow |
| NHIS Compliance | Good | 🟡 Medium | Partial integration, needs tariff mapping |
| UI/UX | Good | 🟡 Medium | Two worklist views (legacy + v2) |
| Code Quality | Good | 🟡 Medium | Large model file, some duplication |
| Integration | Good | 🟡 Medium | Multiple billing integration points |
| Performance | Acceptable | 🟡 Medium | Complex queries, needs optimization |

---

## 1. Feature Audit

### 1.1 Current Features Inventory

#### Core Dispensing Features
| Feature | Location | Status |
|---------|----------|--------|
| Prescription Worklist | `pharmacy.php::index()` | ✅ Active |
| Patient Detail View | `pharmacy.php::patient()` | ✅ Active |
| Single Dispense | `pharmacy.php::log_action()` | ✅ Active |
| Bulk Dispense | `pharmacy.php::bulk_dispense()` | ✅ Active |
| Partial Dispense | `pharmacy_model.php::dispense_medication()` | ✅ Active |
| Reserve Medication | `pharmacy.php::log_action()` | ✅ Active |
| Mark Unavailable | `pharmacy.php::mark_unavailable()` | ✅ Active |
| Mark Available | `pharmacy.php::mark_available()` | ✅ Active |

#### Stock Management Features
| Feature | Location | Status |
|---------|----------|--------|
| Stock List | `pharmacy.php::stock()` | ✅ Active |
| Stock Adjustment | `pharmacy.php::adjust_stock_action()` | ✅ Active |
| Stock History | `pharmacy.php::stock_history()` | ✅ Active |
| Batch Stock (FEFO) | `pharmacy_model.php::add_batch_stock()` | ✅ Active |
| Batch Restock | `pharmacy.php::batch_restock()` | ✅ Active |
| Expiry Tracking | `pharmacy_model.php::get_expiring_batches()` | ✅ Active |
| Remove Expired | `pharmacy.php::remove_expired()` | ✅ Active |

#### GHS Flexible Workflow Features
| Feature | Location | Status |
|---------|----------|--------|
| External Purchase | `pharmacy.php::mark_external_purchase()` | ✅ Active |
| Unable to Pay | `pharmacy.php::mark_unable_to_pay()` | ✅ Active |
| Deferred Payment | `pharmacy.php::mark_deferred_payment()` | ✅ Active |
| Emergency Override | `pharmacy.php::mark_emergency_override()` | ✅ Active |
| Waiver Request | `pharmacy.php::request_waiver()` | ✅ Active |
| Medication Clearance | `pharmacy.php::patient_clearance()` | ✅ Active |

#### Alerts & Monitoring
| Feature | Location | Status |
|---------|----------|--------|
| Pharmacy Alerts Dashboard | `pharmacy.php::alerts()` | ✅ Active |
| Low Stock Alerts | `pharmacy_model.php::count_low_stock()` | ✅ Active |
| Expiring Soon Alerts | `pharmacy_model.php::count_expiring_soon()` | ✅ Active |
| Expired Batches | `pharmacy_model.php::count_expired_batches()` | ✅ Active |

### 1.2 Duplicate Features Identified

#### 🔴 HIGH: Dual Worklist Views
- **Issue:** Two separate worklist views exist
- **Files:** 
  - `worklist.php` (legacy, prescription-based)
  - `worklist_v2.php` (modern, patient-based)
- **Risk:** User confusion, maintenance overhead
- **Recommendation:** Deprecate legacy view, migrate to V2

#### 🟡 MEDIUM: Multiple Payment Gate Checks
- **Issue:** Payment validation logic exists in multiple places
- **Files:**
  - `pharmacy_model.php::check_ghs_payment_gate()`
  - `pharmacy_model.php::check_flexible_dispense_gate()`
  - `pharmacy_model.php::check_unified_payment_gate()`
  - `pharmacy_model.php::check_prescription_payment_gate()`
- **Risk:** Inconsistent payment validation
- **Recommendation:** Consolidate into single `check_payment_gate()` method

#### 🟡 MEDIUM: Multiple Stock Deduction Methods
- **Issue:** Stock deduction has two paths
- **Files:**
  - `pharmacy_model.php::deduct_stock()` (simple)
  - `pharmacy_model.php::deduct_batch_stock_fefo()` (batch/FEFO)
- **Risk:** Inconsistent stock tracking
- **Recommendation:** Unify with automatic FEFO detection

### 1.3 Redundant Code Patterns

```
Location: pharmacy_model.php
Lines: ~2731 total

Redundant Patterns Found:
1. Multiple schema migration methods (5 different ensure_*_schema methods)
2. Repeated table_exists() and column_exists() checks
3. Similar worklist query patterns (get_worklist, get_enhanced_worklist, get_patient_worklist)
4. Duplicate status constant definitions
```

---

## 2. Architecture Review

### 2.1 Current Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      PHARMACY MODULE                             │
├─────────────────────────────────────────────────────────────────┤
│  Controller: application/controllers/app/pharmacy.php           │
│  (611 lines - 25 public methods)                                │
├─────────────────────────────────────────────────────────────────┤
│  Model: application/models/app/pharmacy_model.php               │
│  (2731 lines - 80+ methods)                                     │
├─────────────────────────────────────────────────────────────────┤
│  Views:                                                          │
│  ├── worklist.php (537 lines) - Legacy prescription view        │
│  ├── worklist_v2.php (323 lines) - Modern patient view          │
│  ├── patient_detail.php (613 lines) - Patient prescriptions     │
│  ├── stock.php - Stock management                               │
│  ├── stock_history.php - Stock audit trail                      │
│  ├── alerts.php - Pharmacy alerts dashboard                     │
│  └── pending_approvals.php - Stock request approvals            │
└─────────────────────────────────────────────────────────────────┘
```

### 2.2 Model Dependencies

```
pharmacy_model.php loads:
├── app/billing_model
├── app/nurse_enhancement_model
├── app/governance_model
└── app/billing_transaction_model

pharmacy.php controller loads:
├── app/pharmacy_model
├── app/nurse_enhancement_model
├── app/billing_model
├── app/governance_model
└── app/unified_billing_model
```

### 2.3 Architecture Issues

#### 🔴 HIGH: Model Size & Complexity
- **Issue:** `pharmacy_model.php` is 2731 lines with 80+ methods
- **Impact:** Difficult to maintain, test, and debug
- **Recommendation:** Split into focused sub-models:
  - `pharmacy_dispense_model.php` - Dispensing logic
  - `pharmacy_stock_model.php` - Stock management
  - `pharmacy_billing_model.php` - Billing integration
  - `pharmacy_workflow_model.php` - GHS workflow

#### 🟡 MEDIUM: Multiple Schema Migrations
- **Issue:** 5 different `ensure_*_schema()` methods run on every request
- **Methods:**
  - `ensure_pharmacy_enhancements()`
  - `ensure_pharmacy_v2_schema()`
  - `ensure_pharmacy_ghs_schema()`
  - `ensure_flexible_workflow_schema()`
  - `ensure_billing_transaction_schema()`
- **Impact:** Performance overhead, potential race conditions
- **Recommendation:** Consolidate into single versioned migration system

#### 🟡 MEDIUM: Tight Coupling
- **Issue:** Direct model-to-model calls within pharmacy_model
- **Example:** `$this->load->model('app/billing_model')` inside model methods
- **Impact:** Hard to unit test, circular dependency risk
- **Recommendation:** Use dependency injection or service layer

### 2.4 Separation of Concerns Analysis

| Layer | Current State | Issues |
|-------|---------------|--------|
| Controller | Good | Some business logic in controller |
| Model | Poor | Mixed concerns (data, business, schema) |
| View | Good | Clean separation, minimal logic |
| Database | Fair | Schema migrations in model |

---

## 3. Data & Database Review

### 3.1 Core Tables

#### Drug Master Table: `medicine_drug_name`
```sql
Columns:
- drug_id (PK)
- drug_name
- generic_name (V2)
- dosage_form (V2)
- strength (V2)
- med_cat_id (FK to medicine_category)
- cType
- drug_desc
- uom
- re_order_level
- nPrice
- nStock
- is_nhis_covered (NHIS)
- nhis_price (NHIS)
- cash_price (NHIS)
- InActive

Status: ✅ Well-structured
Issues: 
- nStock is denormalized (should be calculated from batch stock)
- No drug code field for NHIS tariff mapping
```

#### Prescription Table: `iop_medication`
```sql
Columns:
- iop_med_id (PK)
- iop_id (FK to patient_details_iop)
- medicine_id (FK to medicine_drug_name)
- medicine_text
- instruction
- advice
- days
- total_qty
- dDate
- dispensing_status (V2: PENDING|PARTIAL|DISPENSED|UNAVAILABLE|EXTERNAL)
- payment_status (GHS: PENDING|PAID|WAIVED|DEFERRED|etc)
- extended_status (GHS: EXTERNAL_PURCHASE|UNABLE_TO_PAY|DEFERRED|WAIVED|EMERGENCY)
- prescribed_by (V2)
- frequency (V2)
- emergency_flag (GHS)
- emergency_reason (GHS)
- emergency_by (GHS)
- emergency_at (GHS)
- InActive

Status: ✅ Well-structured with GHS extensions
Issues:
- Multiple status columns (dispensing_status, payment_status, extended_status)
- Status synchronization complexity
```

#### Administration Table: `iop_medication_administration`
```sql
Columns:
- admin_id (PK)
- iop_med_id (FK)
- iop_id
- status (DISPENSED|PARTIAL|RESERVED|AWAITING_PAYMENT)
- dose_given
- notes
- dDateTime
- pharmacist_id (V2)
- batch_no (V2)
- InActive

Status: ✅ Good audit trail
Issues: None significant
```

#### Batch Stock Table: `medication_stock` (V2)
```sql
Columns:
- stock_id (PK)
- medication_id (FK to medicine_drug_name)
- batch_number
- quantity
- expiry_date
- unit_cost
- selling_price
- received_date
- supplier
- created_at
- created_by
- InActive

Status: ✅ Proper FEFO support
Issues:
- Not all drugs have batch records (legacy data)
```

#### Stock Adjustment Table: `pharmacy_stock_adjustment`
```sql
Columns:
- id (PK)
- drug_id
- adjustment_type (RESTOCK|DISPENSE|WRITE_OFF|ADJUSTMENT|etc)
- qty_change
- stock_before
- stock_after
- reason
- reference_type
- reference_id
- created_at
- created_by

Status: ✅ Complete audit trail
Issues: None
```

#### Billing Queue Table: `pharmacy_billing_queue` (GHS)
```sql
Columns:
- id (PK)
- iop_med_id (FK)
- iop_id
- patient_no
- drug_id
- drug_name
- quantity
- unit_price
- total
- payment_status (PENDING|PAID|CANCELLED|WAIVED|DEFERRED|etc)
- dispense_status (WAITING|READY|UNAVAILABLE|EXTERNAL)
- extended_status (EXTERNAL_PURCHASE|UNABLE_TO_PAY|DEFERRED|WAIVED|EMERGENCY)
- billed_by
- paid_by
- paid_at
- created_at
- updated_at
- InActive
- (+ flexible workflow columns)

Status: ✅ Single source of truth for payment
Issues:
- Duplicates some data from iop_medication
- Synchronization required
```

### 3.2 Single Source of Truth Analysis

#### 🔴 CRITICAL: Multiple Payment Status Sources
| Source | Table | Column | Used By |
|--------|-------|--------|---------|
| Primary | `pharmacy_billing_queue` | `payment_status` | GHS workflow |
| Secondary | `iop_medication` | `payment_status` | Legacy code |
| Tertiary | `billing_transactions` | `payment_status` | Unified billing |

**Current Resolution:** Code prioritizes `pharmacy_billing_queue` as SSOT
**Risk:** Data drift between tables
**Recommendation:** 
1. Make `pharmacy_billing_queue` the definitive source
2. Remove `payment_status` from `iop_medication`
3. Sync to `billing_transactions` for reporting only

#### 🟡 MEDIUM: Multiple Dispensing Status Sources
| Source | Table | Column | Used By |
|--------|-------|--------|---------|
| Primary | `iop_medication_administration` | `status` + `dose_given` | Actual dispense records |
| Secondary | `iop_medication` | `dispensing_status` | Quick lookup |

**Current Resolution:** Code calculates from `iop_medication_administration` and updates `iop_medication.dispensing_status`
**Risk:** Status can become stale
**Recommendation:** Always calculate from administration records, cache in `dispensing_status`

#### 🟡 MEDIUM: Multiple Stock Sources
| Source | Table | Column | Used By |
|--------|-------|--------|---------|
| Primary | `medication_stock` | `quantity` (per batch) | FEFO dispensing |
| Secondary | `medicine_drug_name` | `nStock` | Quick lookup |

**Current Resolution:** `sync_master_stock()` updates `nStock` from batch totals
**Risk:** Stock drift if sync fails
**Recommendation:** Always calculate from `medication_stock`, treat `nStock` as cache

### 3.3 Database Indexing Review

#### Missing Indexes Identified
```sql
-- pharmacy_billing_queue
CREATE INDEX idx_pbq_patient ON pharmacy_billing_queue(patient_no);
CREATE INDEX idx_pbq_drug ON pharmacy_billing_queue(drug_id);

-- iop_medication
CREATE INDEX idx_iop_med_date ON iop_medication(dDate);
CREATE INDEX idx_iop_med_status ON iop_medication(dispensing_status, payment_status);

-- medication_stock
CREATE INDEX idx_med_stock_active ON medication_stock(medication_id, InActive, quantity);
```

### 3.4 Data Integrity Issues

#### 🟡 MEDIUM: Orphaned Records Risk
- Prescriptions may exist without billing queue entries
- Batch stock may not exist for all drugs
- Administration records may reference deleted prescriptions

**Recommendation:** Add foreign key constraints or implement cleanup jobs

---

## 4. GHS Workflow Alignment Review

### 4.1 Standard GHS Pharmacy Workflow

```
┌─────────────┐    ┌─────────────┐    ┌─────────────┐    ┌─────────────┐
│   Doctor    │───▶│  Pharmacy   │───▶│   Cashier   │───▶│  Pharmacy   │
│ Prescribes  │    │ Reviews Rx  │    │ Collects $  │    │  Dispenses  │
└─────────────┘    └─────────────┘    └─────────────┘    └─────────────┘
                          │                                     │
                          ▼                                     ▼
                   ┌─────────────┐                       ┌─────────────┐
                   │ Mark Status │                       │  Clearance  │
                   │ (if needed) │                       │   Check     │
                   └─────────────┘                       └─────────────┘
```

### 4.2 Implementation Status

| GHS Requirement | Implementation | Status |
|-----------------|----------------|--------|
| Prescription from Doctor | `opd.php::save_medication()` | ✅ Complete |
| Auto-create Billing Entry | `pharmacy_model::create_or_update_pharmacy_bill()` | ✅ Complete |
| Payment Gate Check | `check_ghs_payment_gate()` | ✅ Complete |
| NHIS Auto-Clear | Payer type check in payment gate | ✅ Complete |
| Cash Payment Required | Blocks dispense until paid | ✅ Complete |
| External Purchase | `mark_external_purchase()` | ✅ Complete |
| Unable to Pay | `mark_unable_to_pay()` + outstanding balance | ✅ Complete |
| Deferred Payment | `mark_deferred()` + due date | ✅ Complete |
| Emergency Override | `mark_emergency_override()` + audit | ✅ Complete |
| Waiver Request | `request_waiver()` + approval flow | ✅ Complete |
| Medication Clearance | `patient_medication_clearance()` | ✅ Complete |
| Audit Trail | `pharmacy_audit_log` table | ✅ Complete |

### 4.3 GHS Workflow Gaps

#### 🟢 LOW: Missing Workflow Features
1. **Prescription Modification** - No formal process for changing prescribed quantities
2. **Drug Substitution** - No generic substitution workflow
3. **Prescription Expiry** - No automatic expiry of old prescriptions

#### 🟡 MEDIUM: Reporting Gaps
1. **GHS Monthly Returns** - Not fully automated
2. **Controlled Substance Tracking** - No special handling
3. **Prescription Statistics** - Limited reporting

---

## 5. NHIS Claim-It Protocol Compliance Review

### 5.1 NHIS Integration Components

| Component | File | Status |
|-----------|------|--------|
| NHIS Model | `nhis_model.php` | ✅ Exists |
| Claim-It Model | `Nhis_claimit_model.php` | ✅ Exists |
| NHIS Service Library | `NHIS_Service.php` | ✅ Exists |
| NHIS Config | `config/nhis.php` | ✅ Exists |
| Claims Controller | `nhis_claims.php` | ✅ Exists |

### 5.2 Drug NHIS Coverage

| Feature | Implementation | Status |
|---------|----------------|--------|
| NHIS Coverage Flag | `medicine_drug_name.is_nhis_covered` | ✅ Complete |
| NHIS Price | `medicine_drug_name.nhis_price` | ✅ Complete |
| Cash Price | `medicine_drug_name.cash_price` | ✅ Complete |
| Price Selection | `billing_model::getNhisDrugRate()` | ✅ Complete |
| Coverage Warning | `opd.php::save_medication()` | ✅ Complete |

### 5.3 NHIS Compliance Gaps

#### 🔴 HIGH: Missing NHIS Drug Code Mapping
- **Issue:** No `nhis_drug_code` column in `medicine_drug_name`
- **Impact:** Cannot map drugs to NHIS tariff codes for claims
- **Recommendation:** Add `nhis_drug_code` column and mapping interface

#### 🔴 HIGH: Incomplete Tariff Integration
- **Issue:** `nhis_tariffs` table exists but not linked to drugs
- **Impact:** Manual tariff lookup required for claims
- **Recommendation:** Create drug-to-tariff mapping table

#### 🟡 MEDIUM: Claim Generation Gap
- **Issue:** Pharmacy dispensing doesn't auto-generate claim items
- **Impact:** Manual claim creation required
- **Recommendation:** Auto-add to `nhis_claim_items` on dispense

#### 🟡 MEDIUM: Membership Verification
- **Issue:** No real-time NHIS membership check before dispensing
- **Impact:** May dispense to expired NHIS members
- **Recommendation:** Add membership check to payment gate

### 5.4 Claim-It Protocol Requirements

| Requirement | Status | Notes |
|-------------|--------|-------|
| Facility Code | ✅ Configured | In `nhis_config` table |
| Member Verification | 🟡 Partial | Table exists, no real-time check |
| Visit Authorization | 🟡 Partial | `nhis_visits` table exists |
| Claim Submission | ✅ Exists | Mock API implemented |
| Claim Tracking | ✅ Exists | Status tracking in `nhis_claims` |
| Drug Tariff Codes | 🔴 Missing | No mapping to drugs |
| ICD-10 Diagnosis | ✅ Exists | `icd10_codes` table seeded |

---

## 6. UI/UX Review

### 6.1 Pharmacy Views Inventory

| View | Purpose | Lines | Status |
|------|---------|-------|--------|
| `worklist.php` | Legacy prescription list | 537 | 🟡 Deprecated |
| `worklist_v2.php` | Modern patient-based list | 323 | ✅ Active |
| `patient_detail.php` | Patient prescriptions | 613 | ✅ Active |
| `stock.php` | Stock management | ~300 | ✅ Active |
| `stock_history.php` | Stock audit trail | ~200 | ✅ Active |
| `alerts.php` | Pharmacy alerts | ~200 | ✅ Active |

### 6.2 UI/UX Issues

#### 🔴 HIGH: Dual Worklist Confusion
- **Issue:** Two different worklist views with different UX patterns
- **Impact:** User confusion, training overhead
- **Recommendation:** Deprecate `worklist.php`, use `worklist_v2.php` only

#### 🟡 MEDIUM: Click Depth for Common Actions
| Action | Current Clicks | Optimal |
|--------|----------------|---------|
| Dispense Single Item | 3 | 2 |
| View Patient Prescriptions | 2 | 1 |
| Check Stock Level | 3 | 2 |
| Mark External Purchase | 4 | 2 |

#### 🟡 MEDIUM: Missing Quick Actions
- No keyboard shortcuts for common actions
- No bulk action checkboxes in worklist
- No quick search autocomplete

#### 🟢 LOW: Visual Consistency
- Summary cards use different color schemes across views
- Badge styles vary between views
- Button placement inconsistent

### 6.3 Responsive Design

| View | Mobile | Tablet | Desktop |
|------|--------|--------|---------|
| worklist_v2.php | 🟡 Fair | ✅ Good | ✅ Good |
| patient_detail.php | 🟡 Fair | ✅ Good | ✅ Good |
| stock.php | 🟡 Fair | ✅ Good | ✅ Good |

### 6.4 Accessibility

- ✅ Proper heading hierarchy
- ✅ Form labels present
- 🟡 Limited ARIA attributes
- 🔴 No keyboard navigation for modals
- 🔴 Color-only status indicators (needs icons)

---

## 7. Code Quality Review

### 7.1 File Size Analysis

| File | Lines | Complexity | Assessment |
|------|-------|------------|------------|
| `pharmacy_model.php` | 2731 | Very High | 🔴 Needs refactoring |
| `pharmacy.php` | 611 | Medium | 🟡 Acceptable |
| `patient_detail.php` | 613 | Medium | 🟡 Acceptable |
| `worklist.php` | 537 | Medium | 🟡 Deprecated |

### 7.2 Code Duplication

#### 🔴 HIGH: Repeated Schema Checks
```php
// Pattern repeated 50+ times across pharmacy_model.php
if (!$this->table_exists('table_name')) return;
if (!$this->column_exists('table', 'column')) return;
```
**Recommendation:** Create schema validation helper class

#### 🟡 MEDIUM: Similar Query Patterns
```php
// Multiple worklist methods with similar logic
get_worklist()
get_enhanced_worklist()
get_patient_worklist()
get_unified_patient_worklist()
```
**Recommendation:** Create base worklist query builder

#### 🟡 MEDIUM: Status Constant Duplication
```php
// Status strings repeated across multiple methods
'DISPENSED', 'PARTIAL', 'PENDING', 'EXTERNAL_PURCHASE', etc.
```
**Recommendation:** Define constants at class level

### 7.3 Hardcoded Values

| Type | Location | Value | Issue |
|------|----------|-------|-------|
| Limit | Multiple | 200, 500 | Should be configurable |
| Days | `get_expiring_batches()` | 30 | Should be configurable |
| Status | Multiple | String literals | Should use constants |

### 7.4 Error Handling

| Pattern | Usage | Assessment |
|---------|-------|------------|
| Try-catch | Rare | 🔴 Needs improvement |
| Return arrays | Common | ✅ Good pattern |
| Flash messages | Common | ✅ Good UX |
| Logging | Partial | 🟡 Needs more |

### 7.5 Security Review

| Aspect | Status | Notes |
|--------|--------|-------|
| SQL Injection | ✅ Safe | Uses CodeIgniter query builder |
| XSS | ✅ Safe | Uses `htmlspecialchars()` |
| CSRF | ✅ Safe | CodeIgniter CSRF protection |
| Auth | ✅ Safe | Role-based access control |
| Input Validation | 🟡 Partial | Some inputs not validated |

### 7.6 Dead Code

```
Potentially unused methods in pharmacy_model.php:
- get_enhanced_worklist() - Superseded by get_patient_worklist()
- check_prescription_payment_gate() - Superseded by check_ghs_payment_gate()

Potentially unused views:
- worklist.php - Superseded by worklist_v2.php
```

---

## 8. Integration Review

### 8.1 Module Integration Map

```
                    ┌─────────────────┐
                    │    PHARMACY     │
                    └────────┬────────┘
                             │
        ┌────────────────────┼────────────────────┐
        │                    │                    │
        ▼                    ▼                    ▼
┌───────────────┐   ┌───────────────┐   ┌───────────────┐
│    BILLING    │   │      OPD      │   │   NURSING     │
│               │   │               │   │               │
│ - Bill Queue  │   │ - Prescribe   │   │ - Admin Log   │
│ - Payment     │   │ - View Rx     │   │ - MAR         │
│ - Clearance   │   │               │   │               │
└───────────────┘   └───────────────┘   └───────────────┘
        │                    │                    │
        └────────────────────┼────────────────────┘
                             │
                    ┌────────┴────────┐
                    │      NHIS       │
                    │                 │
                    │ - Claims        │
                    │ - Tariffs       │
                    └─────────────────┘
```

### 8.2 Integration Points

#### Billing Integration
| Integration | Method | Status |
|-------------|--------|--------|
| Create Bill | `create_or_update_pharmacy_bill()` | ✅ Active |
| Payment Gate | `check_ghs_payment_gate()` | ✅ Active |
| Mark Paid | `mark_pharmacy_bill_paid()` | ✅ Active |
| Clearance | `medication_clearance_requirements()` | ✅ Active |
| Unified Billing | `sync_medication_to_billing_transactions()` | ✅ Active |

#### OPD Integration
| Integration | Method | Status |
|-------------|--------|--------|
| Save Prescription | `opd.php::save_medication()` | ✅ Active |
| View Prescriptions | `opd.php::medication()` | ✅ Active |
| Billing Entry | Auto-creates on save | ✅ Active |

#### Nursing Integration
| Integration | Method | Status |
|-------------|--------|--------|
| Administration Log | `nurse_enhancement_model::save_medication_administration()` | ✅ Active |
| MAR View | `nurse_module.php` | ✅ Active |

### 8.3 Integration Gaps

#### 🟡 MEDIUM: IPD Integration
- **Issue:** Limited IPD medication workflow
- **Impact:** Inpatient prescriptions not fully tracked
- **Recommendation:** Extend pharmacy workflow for IPD

#### 🟡 MEDIUM: Laboratory Integration
- **Issue:** No drug-lab interaction checking
- **Impact:** Potential drug-lab conflicts
- **Recommendation:** Add interaction database

#### 🟢 LOW: Appointment Integration
- **Issue:** No prescription reminder for appointments
- **Impact:** Missed refill opportunities
- **Recommendation:** Add prescription expiry alerts

---

## 9. Performance Review

### 9.1 Query Analysis

#### Heavy Queries Identified

**1. Patient Worklist Query**
```sql
-- Location: get_patient_worklist()
-- Complexity: Multiple JOINs, GROUP BY, HAVING
-- Tables: patient_details_iop, iop_medication, pharmacy_billing_queue, 
--         patient_personal_info, system_parameters
-- Estimated Cost: HIGH
```
**Recommendation:** Add composite indexes, consider materialized view

**2. Billing Payment Status Map**
```sql
-- Location: _get_billing_payment_status_map()
-- Complexity: Multiple queries, in-memory processing
-- Tables: iop_billing, iop_receipt
-- Estimated Cost: MEDIUM-HIGH
```
**Recommendation:** Create summary table, update on payment

**3. Prescription Detail Query**
```sql
-- Location: get_patient_prescriptions()
-- Complexity: Multiple JOINs, conditional columns
-- Tables: iop_medication, medicine_drug_name, pharmacy_billing_queue
-- Estimated Cost: MEDIUM
```
**Recommendation:** Add covering indexes

### 9.2 N+1 Query Patterns

| Location | Pattern | Impact |
|----------|---------|--------|
| `get_payer_map()` | Loop with individual queries | 🔴 High |
| `bulk_dispense_patient()` | Loop with dispense calls | 🟡 Medium |
| `sync_encounter_medications()` | Loop with sync calls | 🟡 Medium |

### 9.3 Caching Opportunities

| Data | Current | Recommendation |
|------|---------|----------------|
| Stock Levels | No cache | Redis/Memcache with 5min TTL |
| Drug List | No cache | Session cache |
| Summary Counts | No cache | Redis with 1min TTL |
| NHIS Tariffs | No cache | File cache (daily) |

### 9.4 Schema Migration Performance

**Issue:** Multiple `ensure_*_schema()` methods run on every request
**Impact:** ~50-100ms overhead per request
**Recommendation:** 
1. Run migrations once via CLI
2. Store migration version in database
3. Check version before running migrations

---

## 10. Risk Assessment Summary

### Critical Risks (🔴)

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Multiple payment status sources | Data inconsistency | High | Consolidate to single source |
| Missing NHIS drug codes | Claims cannot be submitted | High | Add mapping table |
| Large model file | Maintenance difficulty | Medium | Split into sub-models |

### Medium Risks (🟡)

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Dual worklist views | User confusion | Medium | Deprecate legacy view |
| Multiple payment gates | Inconsistent validation | Medium | Consolidate methods |
| Schema migrations on every request | Performance | High | Move to CLI |
| Stock sync failures | Inventory mismatch | Low | Add reconciliation job |

### Low Risks (🟢)

| Risk | Impact | Likelihood | Mitigation |
|------|--------|------------|------------|
| Missing keyboard shortcuts | UX friction | Low | Add in future release |
| Limited mobile support | Field use difficulty | Low | Responsive redesign |
| Dead code | Maintenance overhead | Low | Code cleanup |

---

## 11. Recommendations Summary

### Immediate Actions (Phase 1)

1. **Consolidate Payment Status**
   - Make `pharmacy_billing_queue` the single source of truth
   - Remove `payment_status` from `iop_medication`
   - Add sync triggers

2. **Add NHIS Drug Code Mapping**
   - Add `nhis_drug_code` column to `medicine_drug_name`
   - Create mapping interface in admin
   - Link to `nhis_tariffs` table

3. **Deprecate Legacy Worklist**
   - Remove `worklist.php` from navigation
   - Redirect to `worklist_v2.php`
   - Update documentation

### Short-term Actions (Phase 2)

4. **Refactor Model**
   - Split `pharmacy_model.php` into focused sub-models
   - Create shared helper class for schema checks
   - Define status constants

5. **Optimize Queries**
   - Add missing indexes
   - Implement query caching
   - Create summary tables

6. **Consolidate Payment Gates**
   - Merge 4 payment gate methods into 1
   - Add NHIS membership verification
   - Improve error messages

### Long-term Actions (Phase 3)

7. **Complete NHIS Integration**
   - Auto-generate claim items on dispense
   - Real-time membership verification
   - Automated claim submission

8. **Performance Optimization**
   - Move schema migrations to CLI
   - Implement Redis caching
   - Add database connection pooling

9. **UI/UX Improvements**
   - Add keyboard shortcuts
   - Improve mobile responsiveness
   - Add bulk action support

---

## 12. Appendices

### A. File Inventory

| File | Path | Lines | Purpose |
|------|------|-------|---------|
| pharmacy.php | controllers/app/ | 611 | Main controller |
| pharmacy_model.php | models/app/ | 2731 | Core model |
| worklist.php | views/app/pharmacy/ | 537 | Legacy worklist |
| worklist_v2.php | views/app/pharmacy/ | 323 | Modern worklist |
| patient_detail.php | views/app/pharmacy/ | 613 | Patient view |
| stock.php | views/app/pharmacy/ | ~300 | Stock management |
| stock_history.php | views/app/pharmacy/ | ~200 | Stock audit |
| alerts.php | views/app/pharmacy/ | ~200 | Alerts dashboard |

### B. Database Tables

| Table | Purpose | Records (Est.) |
|-------|---------|----------------|
| medicine_drug_name | Drug master | 500-2000 |
| medicine_category | Drug categories | 20-50 |
| iop_medication | Prescriptions | 10000+ |
| iop_medication_administration | Dispense log | 10000+ |
| medication_stock | Batch stock | 1000-5000 |
| pharmacy_stock_adjustment | Stock audit | 5000+ |
| pharmacy_billing_queue | Billing queue | 10000+ |
| pharmacy_audit_log | Audit trail | 10000+ |

### C. API Endpoints

| Endpoint | Method | Purpose |
|----------|--------|---------|
| /app/pharmacy | GET | Worklist |
| /app/pharmacy/patient/{iop_id} | GET | Patient detail |
| /app/pharmacy/log_action | POST | Dispense/Reserve |
| /app/pharmacy/bulk_dispense | POST | Bulk dispense |
| /app/pharmacy/stock | GET | Stock list |
| /app/pharmacy/adjust_stock_action | POST | Stock adjustment |
| /app/pharmacy/mark_unavailable | POST | Mark unavailable |
| /app/pharmacy/mark_external_purchase | POST | External purchase |
| /app/pharmacy/drug_search_json | GET | Drug autocomplete |

### D. Status Codes Reference

| Status | Table | Meaning |
|--------|-------|---------|
| PENDING | iop_medication.dispensing_status | Not dispensed |
| PARTIAL | iop_medication.dispensing_status | Partially dispensed |
| DISPENSED | iop_medication.dispensing_status | Fully dispensed |
| UNAVAILABLE | iop_medication.dispensing_status | Drug unavailable |
| EXTERNAL | iop_medication.dispensing_status | External purchase |
| PAID | pharmacy_billing_queue.payment_status | Payment received |
| WAIVED | pharmacy_billing_queue.payment_status | Fee waived |
| DEFERRED | pharmacy_billing_queue.payment_status | Payment deferred |
| EXTERNAL_PURCHASE | pharmacy_billing_queue.extended_status | Patient buys outside |
| UNABLE_TO_PAY | pharmacy_billing_queue.extended_status | Cannot afford |
| EMERGENCY | pharmacy_billing_queue.extended_status | Emergency override |

---

## Report Sign-off

**Audit Completed:** January 2025  
**Next Review:** Recommended after Phase 1 implementation  
**Status:** Awaiting stakeholder review and implementation approval

---

*This report is for analysis purposes only. No code changes have been made.*
