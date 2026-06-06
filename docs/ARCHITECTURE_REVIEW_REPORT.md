# Enterprise Billing Architecture Review Report

**Date:** April 3, 2026  
**Reviewer:** Senior Hospital Financial Systems Architect  
**Document Under Review:** ENTERPRISE_BILLING_ARCHITECTURE.md v1.0  
**Review Type:** Production Readiness Assessment

---

## Executive Summary

### Overall Assessment: **APPROVED WITH ENHANCEMENTS**

The Enterprise Billing Architecture document presents a **solid foundation** for achieving financial integrity and single source of truth. However, several **critical enhancements** are required before production deployment, particularly for:

- Ghana-specific NHIS/Insurance workflows
- Migration safety and rollback strategy
- Race condition prevention
- IPD-specific billing scenarios

| Category | Score | Status |
|----------|-------|--------|
| Core Architecture | 8/10 | ✅ Approved |
| Ghana Billing Compliance | 6/10 | ⚠️ Needs Enhancement |
| Migration Strategy | 5/10 | ⚠️ Needs Enhancement |
| Performance Design | 7/10 | ✅ Approved |
| Event System | 7/10 | ⚠️ Minor Gaps |
| Audit Trail | 8/10 | ✅ Approved |

---

## Part 1: Architecture Review

### 1.1 Schema Analysis: `billing_transactions`

#### ✅ Strengths

| Feature | Assessment |
|---------|------------|
| UUID for external references | Excellent for API integration |
| Immutability control (`is_locked`) | Critical for audit compliance |
| Source tracking fields | Enables full traceability |
| Soft delete pattern | Preserves data integrity |
| Composite unique key | Prevents duplicate charges |

#### ❌ Missing Fields (CRITICAL)

```sql
-- Add these fields to billing_transactions:

-- NHIS-Specific Fields
nhis_code               VARCHAR(20) DEFAULT NULL COMMENT 'NHIS Tariff Code',
nhis_category           VARCHAR(50) DEFAULT NULL COMMENT 'NHIS Service Category',
nhis_claim_id           VARCHAR(50) DEFAULT NULL COMMENT 'Claim-IT Reference',
nhis_claim_status       ENUM('PENDING', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PAID') DEFAULT NULL,
nhis_approval_code      VARCHAR(50) DEFAULT NULL COMMENT 'Pre-authorization code',

-- Insurance-Specific Fields
insurance_id            INT UNSIGNED DEFAULT NULL COMMENT 'FK to insurance_companies',
insurance_policy_no     VARCHAR(50) DEFAULT NULL,
insurance_approval_no   VARCHAR(50) DEFAULT NULL,
insurance_claim_status  ENUM('PENDING', 'SUBMITTED', 'APPROVED', 'REJECTED', 'PAID') DEFAULT NULL,

-- Company/Corporate Fields
company_id              INT UNSIGNED DEFAULT NULL COMMENT 'FK to corporate_companies',
company_contract_id     INT UNSIGNED DEFAULT NULL COMMENT 'FK to company_contracts',
company_po_number       VARCHAR(50) DEFAULT NULL COMMENT 'Purchase Order Number',

-- Waiver & Deferral
is_waived               TINYINT(1) NOT NULL DEFAULT 0,
waived_by               VARCHAR(25) DEFAULT NULL,
waived_at               DATETIME DEFAULT NULL,
waiver_reason           VARCHAR(255) DEFAULT NULL,
waiver_approval_id      INT UNSIGNED DEFAULT NULL,

is_deferred             TINYINT(1) NOT NULL DEFAULT 0,
deferred_by             VARCHAR(25) DEFAULT NULL,
deferred_at             DATETIME DEFAULT NULL,
deferred_until          DATE DEFAULT NULL,
deferral_reason         VARCHAR(255) DEFAULT NULL,

-- Partial Payment Tracking
amount_paid             DECIMAL(18,2) NOT NULL DEFAULT 0,
amount_outstanding      DECIMAL(18,2) NOT NULL DEFAULT 0,

-- Service Execution Link
service_gate_status     ENUM('BLOCKED', 'RELEASED', 'BYPASSED') DEFAULT 'BLOCKED',
service_released_at     DATETIME DEFAULT NULL,
service_released_by     VARCHAR(25) DEFAULT NULL,

-- Tax (for future compliance)
tax_rate                DECIMAL(5,2) DEFAULT 0,
tax_amount              DECIMAL(18,2) DEFAULT 0,
```

#### ❌ Missing Indexes (PERFORMANCE)

```sql
-- Add these indexes:
INDEX idx_bt_nhis_claim (nhis_claim_id),
INDEX idx_bt_insurance (insurance_id, insurance_claim_status),
INDEX idx_bt_company (company_id),
INDEX idx_bt_waived (is_waived, waived_at),
INDEX idx_bt_deferred (is_deferred, deferred_until),
INDEX idx_bt_service_gate (service_gate_status),
INDEX idx_bt_created_date (DATE(created_at)),  -- For daily reports
INDEX idx_bt_encounter (encounter_type, created_at),
```

### 1.2 Schema Analysis: `billing_adjustments`

#### ✅ Approved

The adjustments table design is sound. Minor enhancement needed:

```sql
-- Add these fields:
visit_id            VARCHAR(30) DEFAULT NULL,
adj_status          ENUM('PENDING', 'APPROVED', 'REJECTED', 'APPLIED') NOT NULL DEFAULT 'PENDING',
approved_by         VARCHAR(25) DEFAULT NULL,
approved_at         DATETIME DEFAULT NULL,
applied_at          DATETIME DEFAULT NULL,
reversal_of_adj_id  BIGINT UNSIGNED DEFAULT NULL COMMENT 'If this reverses another adjustment',
```

### 1.3 Schema Analysis: `billing_events_log`

#### ✅ Approved

The events log is well-designed. Add:

```sql
-- Add these fields:
event_result        ENUM('SUCCESS', 'FAILED', 'SKIPPED') NOT NULL DEFAULT 'SUCCESS',
error_message       TEXT DEFAULT NULL,
processing_time_ms  INT UNSIGNED DEFAULT NULL COMMENT 'Performance tracking',
```

---

## Part 2: Missing Components (CRITICAL)

### 2.1 Missing Tables

#### Table 1: `billing_invoices` (Invoice Header)

```sql
CREATE TABLE billing_invoices (
    invoice_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_no          VARCHAR(30) NOT NULL UNIQUE,
    invoice_uuid        CHAR(36) NOT NULL UNIQUE,
    
    -- Context
    visit_id            VARCHAR(30) NOT NULL,
    patient_no          VARCHAR(25) NOT NULL,
    encounter_type      ENUM('OPD', 'IPD', 'EMERGENCY') NOT NULL,
    
    -- Totals (Calculated from billing_transactions)
    subtotal            DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_discount      DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_tax           DECIMAL(18,2) NOT NULL DEFAULT 0,
    grand_total         DECIMAL(18,2) NOT NULL DEFAULT 0,
    
    -- Payer Split
    nhis_amount         DECIMAL(18,2) NOT NULL DEFAULT 0,
    insurance_amount    DECIMAL(18,2) NOT NULL DEFAULT 0,
    company_amount      DECIMAL(18,2) NOT NULL DEFAULT 0,
    patient_amount      DECIMAL(18,2) NOT NULL DEFAULT 0,
    
    -- Payment Status
    amount_paid         DECIMAL(18,2) NOT NULL DEFAULT 0,
    amount_outstanding  DECIMAL(18,2) NOT NULL DEFAULT 0,
    payment_status      ENUM('UNPAID', 'PARTIAL', 'PAID', 'OVERPAID', 'WAIVED') NOT NULL DEFAULT 'UNPAID',
    
    -- Invoice Status
    invoice_status      ENUM('DRAFT', 'FINALIZED', 'VOID', 'CANCELLED') NOT NULL DEFAULT 'DRAFT',
    finalized_at        DATETIME DEFAULT NULL,
    finalized_by        VARCHAR(25) DEFAULT NULL,
    
    -- Immutability
    is_locked           TINYINT(1) NOT NULL DEFAULT 0,
    locked_at           DATETIME DEFAULT NULL,
    
    -- Audit
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          VARCHAR(25) NOT NULL,
    updated_at          DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_bi_visit (visit_id),
    INDEX idx_bi_patient (patient_no),
    INDEX idx_bi_status (payment_status),
    INDEX idx_bi_date (created_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 2: `billing_payments` (Payment Records)

```sql
CREATE TABLE billing_payments (
    payment_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    receipt_no          VARCHAR(30) NOT NULL UNIQUE,
    payment_uuid        CHAR(36) NOT NULL UNIQUE,
    
    -- Reference
    invoice_id          BIGINT UNSIGNED NOT NULL,
    invoice_no          VARCHAR(30) NOT NULL,
    visit_id            VARCHAR(30) NOT NULL,
    patient_no          VARCHAR(25) NOT NULL,
    
    -- Payment Details
    payment_method      ENUM('CASH', 'MOMO', 'CARD', 'BANK', 'CHEQUE') NOT NULL,
    payment_amount      DECIMAL(18,2) NOT NULL,
    payment_reference   VARCHAR(100) DEFAULT NULL COMMENT 'Transaction ID for electronic payments',
    
    -- For split payments
    payer_type          ENUM('PATIENT', 'NHIS', 'INSURANCE', 'COMPANY') NOT NULL DEFAULT 'PATIENT',
    payer_reference     VARCHAR(50) DEFAULT NULL,
    
    -- Status
    payment_status      ENUM('PENDING', 'CONFIRMED', 'FAILED', 'REVERSED', 'REFUNDED') NOT NULL DEFAULT 'CONFIRMED',
    
    -- Cashier
    cashier_id          VARCHAR(25) NOT NULL,
    shift_id            INT UNSIGNED DEFAULT NULL,
    terminal_id         VARCHAR(20) DEFAULT NULL,
    
    -- Immutability
    is_locked           TINYINT(1) NOT NULL DEFAULT 1,
    
    -- Audit
    payment_date        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_bp_invoice (invoice_id),
    INDEX idx_bp_patient (patient_no),
    INDEX idx_bp_date (payment_date),
    INDEX idx_bp_cashier (cashier_id, payment_date),
    INDEX idx_bp_method (payment_method),
    
    FOREIGN KEY (invoice_id) REFERENCES billing_invoices(invoice_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 3: `billing_refunds` (Refund Workflow)

```sql
CREATE TABLE billing_refunds (
    refund_id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    refund_no           VARCHAR(30) NOT NULL UNIQUE,
    refund_uuid         CHAR(36) NOT NULL UNIQUE,
    
    -- Reference
    original_payment_id BIGINT UNSIGNED NOT NULL,
    original_receipt_no VARCHAR(30) NOT NULL,
    invoice_no          VARCHAR(30) NOT NULL,
    patient_no          VARCHAR(25) NOT NULL,
    
    -- Refund Details
    refund_amount       DECIMAL(18,2) NOT NULL,
    refund_reason       VARCHAR(255) NOT NULL,
    refund_method       ENUM('CASH', 'MOMO', 'BANK', 'ORIGINAL_METHOD') NOT NULL,
    refund_reference    VARCHAR(100) DEFAULT NULL,
    
    -- Approval Workflow
    requested_by        VARCHAR(25) NOT NULL,
    requested_at        DATETIME NOT NULL,
    approval_status     ENUM('PENDING', 'APPROVED', 'REJECTED') NOT NULL DEFAULT 'PENDING',
    approved_by         VARCHAR(25) DEFAULT NULL,
    approved_at         DATETIME DEFAULT NULL,
    rejection_reason    VARCHAR(255) DEFAULT NULL,
    
    -- Execution
    processed_by        VARCHAR(25) DEFAULT NULL,
    processed_at        DATETIME DEFAULT NULL,
    refund_status       ENUM('PENDING', 'PROCESSING', 'COMPLETED', 'FAILED') NOT NULL DEFAULT 'PENDING',
    
    -- Audit
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_br_payment (original_payment_id),
    INDEX idx_br_patient (patient_no),
    INDEX idx_br_status (approval_status, refund_status),
    
    FOREIGN KEY (original_payment_id) REFERENCES billing_payments(payment_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 4: `nhis_claims` (NHIS Claims Management)

```sql
CREATE TABLE nhis_claims (
    claim_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    claim_no            VARCHAR(50) NOT NULL UNIQUE,
    claim_uuid          CHAR(36) NOT NULL UNIQUE,
    
    -- Batch Info
    batch_id            INT UNSIGNED DEFAULT NULL,
    batch_month         VARCHAR(7) NOT NULL COMMENT 'YYYY-MM',
    
    -- Patient/Visit
    visit_id            VARCHAR(30) NOT NULL,
    patient_no          VARCHAR(25) NOT NULL,
    nhis_member_id      VARCHAR(50) NOT NULL,
    
    -- Claim Details
    claim_amount        DECIMAL(18,2) NOT NULL,
    approved_amount     DECIMAL(18,2) DEFAULT NULL,
    
    -- Status
    claim_status        ENUM('DRAFT', 'SUBMITTED', 'ACKNOWLEDGED', 'APPROVED', 
                             'PARTIAL', 'REJECTED', 'PAID', 'DISPUTED') NOT NULL DEFAULT 'DRAFT',
    
    -- Claim-IT Integration
    claimit_reference   VARCHAR(100) DEFAULT NULL,
    claimit_submitted_at DATETIME DEFAULT NULL,
    claimit_response    JSON DEFAULT NULL,
    
    -- Rejection Handling
    rejection_code      VARCHAR(20) DEFAULT NULL,
    rejection_reason    VARCHAR(255) DEFAULT NULL,
    resubmission_of     BIGINT UNSIGNED DEFAULT NULL,
    
    -- Audit
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          VARCHAR(25) NOT NULL,
    submitted_at        DATETIME DEFAULT NULL,
    submitted_by        VARCHAR(25) DEFAULT NULL,
    
    INDEX idx_nc_batch (batch_id),
    INDEX idx_nc_month (batch_month),
    INDEX idx_nc_patient (patient_no),
    INDEX idx_nc_status (claim_status),
    INDEX idx_nc_nhis (nhis_member_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Table 5: `service_gates` (Payment Verification Before Service)

```sql
CREATE TABLE service_gates (
    gate_id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    
    -- Reference
    txn_id              BIGINT UNSIGNED NOT NULL,
    visit_id            VARCHAR(30) NOT NULL,
    patient_no          VARCHAR(25) NOT NULL,
    
    -- Service Details
    service_type        VARCHAR(30) NOT NULL,
    service_ref_id      VARCHAR(50) NOT NULL,
    service_ref_table   VARCHAR(50) NOT NULL,
    
    -- Gate Status
    gate_status         ENUM('BLOCKED', 'RELEASED', 'BYPASSED', 'EXPIRED') NOT NULL DEFAULT 'BLOCKED',
    
    -- Release Info
    released_at         DATETIME DEFAULT NULL,
    released_by         VARCHAR(25) DEFAULT NULL,
    release_type        ENUM('PAYMENT', 'WAIVER', 'DEFERRAL', 'ADMIN_OVERRIDE') DEFAULT NULL,
    release_reference   VARCHAR(50) DEFAULT NULL COMMENT 'Receipt/Waiver/Deferral ID',
    
    -- Bypass (Emergency)
    bypassed_at         DATETIME DEFAULT NULL,
    bypassed_by         VARCHAR(25) DEFAULT NULL,
    bypass_reason       VARCHAR(255) DEFAULT NULL,
    bypass_approval_id  INT UNSIGNED DEFAULT NULL,
    
    -- Audit
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_sg_txn (txn_id),
    INDEX idx_sg_visit (visit_id),
    INDEX idx_sg_status (gate_status),
    INDEX idx_sg_service (service_type, service_ref_id),
    
    FOREIGN KEY (txn_id) REFERENCES billing_transactions(txn_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2.2 Missing Events

| Event | Trigger Point | Missing From Document |
|-------|---------------|----------------------|
| `SONOGRAPHY_ORDERED` | Doctor orders ultrasound | ❌ Missing |
| `SURGERY_SCHEDULED` | Surgery scheduled | ❌ Missing |
| `CONSUMABLE_USED` | Theatre/Ward consumables | ❌ Missing |
| `ROOM_DAILY_CHARGE` | Daily IPD room charge | ❌ Missing |
| `NURSING_SERVICE` | Nursing procedures | ❌ Missing |
| `EMERGENCY_ADMISSION` | Emergency registration | ❌ Missing |
| `PRESCRIPTION_MODIFIED` | Quantity changed | ❌ Missing |
| `LAB_ORDER_CANCELLED` | Test cancelled | ❌ Missing |
| `SERVICE_WAIVED` | Charge waived | ❌ Missing |
| `SERVICE_DEFERRED` | Payment deferred | ❌ Missing |

---

## Part 3: Risk Analysis

### 3.1 HIGH RISK

| # | Risk | Impact | Mitigation |
|---|------|--------|------------|
| 1 | **Race Condition on Duplicate Check** | Duplicate charges in concurrent requests | Use database-level locking with `SELECT ... FOR UPDATE` |
| 2 | **Migration Data Loss** | Historical billing data corrupted | Implement parallel-run period, keep legacy tables read-only |
| 3 | **NHIS Claim Mismatch** | Claims rejected, revenue loss | Add NHIS tariff validation before charge creation |
| 4 | **Event Handler Failure** | Silent billing failures | Implement dead-letter queue and retry mechanism |
| 5 | **No Rollback Strategy** | Cannot revert if issues found | Define explicit rollback procedures and feature flags |

### 3.2 MEDIUM RISK

| # | Risk | Impact | Mitigation |
|---|------|--------|------------|
| 6 | **IPD Daily Charges Missing** | Room charges not auto-generated | Add scheduled job for daily IPD billing |
| 7 | **Pharmacy Quantity Mismatch** | Billed qty ≠ dispensed qty | Add reconciliation check at dispense time |
| 8 | **Insurance Pre-auth Not Validated** | Services without approval | Add pre-auth check in service gate |
| 9 | **Company Credit Limit Exceeded** | Unbillable services rendered | Add credit limit check before charge creation |
| 10 | **Partial Payment Complexity** | Balance tracking errors | Use calculated fields, not stored values |

### 3.3 LOW RISK

| # | Risk | Impact | Mitigation |
|---|------|--------|------------|
| 11 | **Report Performance** | Slow dashboard loading | Pre-aggregate daily summaries |
| 12 | **Audit Log Volume** | Storage growth | Implement log rotation and archival |
| 13 | **UUID Generation Collision** | Duplicate UUIDs | Use UUID v4 with proper library |

---

## Part 4: Workflow Validation

### 4.1 End-to-End OPD Workflow

```
✅ Patient Registration
   └── Event: REGISTRATION_CREATED
       └── Auto-Charge: Registration Fee
       
✅ Consultation Start
   └── Event: CONSULTATION_STARTED
       └── Auto-Charge: Consultation Fee
       └── Service Gate: BLOCKED (awaiting payment)
       
✅ Lab Order
   └── Event: LAB_ORDERED
       └── Auto-Charge: Lab Test Fees (per test)
       └── Service Gate: BLOCKED per test
       
✅ Prescription
   └── Event: PRESCRIPTION_CREATED
       └── Auto-Charge: Medication Fees
       └── Service Gate: BLOCKED per medication
       
✅ Billing Review
   └── Cashier sees all auto-generated charges
   └── No manual entry required
   └── Apply discount if authorized
   
✅ Payment Collection
   └── Payment recorded
   └── Service Gates: RELEASED
   
✅ Service Execution
   └── Lab: Processes samples (gate verified)
   └── Pharmacy: Dispenses medications (gate verified)
   
✅ Checkout
   └── All services completed
   └── All payments collected
   └── Visit closed
```

### 4.2 IPD Workflow Gaps

| Gap | Current Status | Required Enhancement |
|-----|----------------|---------------------|
| Daily Room Charges | ❌ Not covered | Add scheduled job to create daily charges |
| Nursing Services | ❌ Not covered | Add NURSING_SERVICE event |
| Theatre Consumables | ❌ Not covered | Add CONSUMABLE_USED event |
| Surgery Packages | ❌ Not covered | Add package billing logic |
| Discharge Billing | ❌ Not covered | Add DISCHARGE_INITIATED event |

### 4.3 Emergency Workflow

| Scenario | Handling | Status |
|----------|----------|--------|
| Service before payment | Bypass with approval | ⚠️ Needs explicit workflow |
| Retrospective billing | Create charges after service | ⚠️ Needs explicit workflow |
| Emergency admission | Auto-charge admission fee | ❌ Missing event |

---

## Part 5: Ghana Billing Compliance

### 5.1 NHIS Compliance

| Requirement | Status | Gap |
|-------------|--------|-----|
| NHIS Tariff Codes | ❌ Missing | Add `nhis_code` field |
| Service Categories | ❌ Missing | Add `nhis_category` field |
| Covered vs Non-Covered | ⚠️ Partial | Add coverage validation |
| Co-payment Calculation | ⚠️ Partial | Add co-payment rules engine |
| Claim-IT Integration | ❌ Missing | Add `nhis_claims` table |
| Claim Batching | ❌ Missing | Add batch management |
| Rejection Handling | ❌ Missing | Add resubmission workflow |
| Pre-authorization | ❌ Missing | Add approval workflow |

#### Required NHIS Enhancement

```php
class NHISBillingService
{
    /**
     * Validate NHIS coverage before creating charge
     */
    public function validate_nhis_coverage($service_type, $service_code, $nhis_member_id)
    {
        // 1. Verify NHIS membership is active
        $member = $this->verify_nhis_membership($nhis_member_id);
        if (!$member->is_active) {
            return ['covered' => false, 'reason' => 'NHIS membership expired'];
        }
        
        // 2. Check if service is covered
        $tariff = $this->get_nhis_tariff($service_type, $service_code);
        if (!$tariff) {
            return ['covered' => false, 'reason' => 'Service not on NHIS tariff'];
        }
        
        // 3. Check if pre-authorization required
        if ($tariff->requires_preauth) {
            return ['covered' => true, 'requires_preauth' => true, 'tariff' => $tariff];
        }
        
        return ['covered' => true, 'tariff' => $tariff, 'nhis_price' => $tariff->price];
    }
    
    /**
     * Calculate patient co-payment
     */
    public function calculate_copayment($service_type, $service_code, $nhis_price)
    {
        // NHIS covers 100% of tariff price
        // Patient pays difference between hospital price and NHIS price
        $hospital_price = $this->get_hospital_price($service_type, $service_code);
        $copayment = max(0, $hospital_price - $nhis_price);
        
        return [
            'hospital_price' => $hospital_price,
            'nhis_covers' => $nhis_price,
            'patient_pays' => $copayment
        ];
    }
}
```

### 5.2 Private Insurance Compliance

| Requirement | Status | Gap |
|-------------|--------|-----|
| Coverage Percentage | ✅ Present | `coverage_percent` field exists |
| Pre-authorization | ❌ Missing | Add approval workflow |
| Claim Batching | ❌ Missing | Add insurance claims table |
| Exclusions List | ❌ Missing | Add coverage rules |
| Credit Period | ❌ Missing | Add payment terms |

### 5.3 Company/Corporate Billing

| Requirement | Status | Gap |
|-------------|--------|-----|
| Contract Pricing | ❌ Missing | Add company contracts table |
| Credit Limit | ❌ Missing | Add credit limit check |
| Monthly Invoicing | ❌ Missing | Add batch invoice generation |
| Purchase Orders | ❌ Missing | Add PO reference field |
| Employee Verification | ❌ Missing | Add employee validation |

#### Required Company Billing Tables

```sql
CREATE TABLE company_contracts (
    contract_id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id          INT UNSIGNED NOT NULL,
    contract_no         VARCHAR(50) NOT NULL,
    
    -- Terms
    start_date          DATE NOT NULL,
    end_date            DATE DEFAULT NULL,
    credit_limit        DECIMAL(18,2) NOT NULL DEFAULT 0,
    payment_terms_days  INT NOT NULL DEFAULT 30,
    discount_percent    DECIMAL(5,2) DEFAULT 0,
    
    -- Billing
    billing_cycle       ENUM('IMMEDIATE', 'WEEKLY', 'MONTHLY') NOT NULL DEFAULT 'MONTHLY',
    billing_contact     VARCHAR(100) DEFAULT NULL,
    billing_email       VARCHAR(100) DEFAULT NULL,
    
    -- Status
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    
    INDEX idx_cc_company (company_id),
    INDEX idx_cc_active (is_active, end_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_credit_usage (
    usage_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_id          INT UNSIGNED NOT NULL,
    contract_id         INT UNSIGNED NOT NULL,
    
    -- Usage
    invoice_no          VARCHAR(30) NOT NULL,
    amount              DECIMAL(18,2) NOT NULL,
    usage_date          DATE NOT NULL,
    
    -- Running Balance
    credit_used         DECIMAL(18,2) NOT NULL,
    credit_remaining    DECIMAL(18,2) NOT NULL,
    
    INDEX idx_ccu_company (company_id),
    INDEX idx_ccu_date (usage_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Part 6: Performance Recommendations

### 6.1 Query Optimization

#### Problem: Daily Dashboard Queries

```sql
-- SLOW: Calculates on every request
SELECT SUM(net_amount) FROM billing_transactions 
WHERE DATE(created_at) = CURDATE();
```

#### Solution: Pre-aggregated Summary Table

```sql
CREATE TABLE billing_daily_summary (
    summary_id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    summary_date        DATE NOT NULL,
    
    -- Counts
    total_transactions  INT NOT NULL DEFAULT 0,
    total_invoices      INT NOT NULL DEFAULT 0,
    total_payments      INT NOT NULL DEFAULT 0,
    
    -- Amounts by Type
    consultation_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
    lab_amount          DECIMAL(18,2) NOT NULL DEFAULT 0,
    pharmacy_amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    radiology_amount    DECIMAL(18,2) NOT NULL DEFAULT 0,
    other_amount        DECIMAL(18,2) NOT NULL DEFAULT 0,
    
    -- Totals
    gross_amount        DECIMAL(18,2) NOT NULL DEFAULT 0,
    discount_amount     DECIMAL(18,2) NOT NULL DEFAULT 0,
    net_amount          DECIMAL(18,2) NOT NULL DEFAULT 0,
    
    -- Payments
    cash_collected      DECIMAL(18,2) NOT NULL DEFAULT 0,
    momo_collected      DECIMAL(18,2) NOT NULL DEFAULT 0,
    card_collected      DECIMAL(18,2) NOT NULL DEFAULT 0,
    total_collected     DECIMAL(18,2) NOT NULL DEFAULT 0,
    
    -- Payer Split
    nhis_amount         DECIMAL(18,2) NOT NULL DEFAULT 0,
    insurance_amount    DECIMAL(18,2) NOT NULL DEFAULT 0,
    company_amount      DECIMAL(18,2) NOT NULL DEFAULT 0,
    patient_amount      DECIMAL(18,2) NOT NULL DEFAULT 0,
    
    -- Metadata
    last_updated        DATETIME NOT NULL,
    
    UNIQUE KEY uq_bds_date (summary_date)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6.2 Indexing Strategy

```sql
-- Composite indexes for common queries
CREATE INDEX idx_bt_daily_report ON billing_transactions(DATE(created_at), charge_type, billing_status);
CREATE INDEX idx_bt_patient_visit ON billing_transactions(patient_no, visit_id, created_at);
CREATE INDEX idx_bt_cashier_daily ON billing_transactions(created_by, DATE(created_at));

-- Covering index for invoice lookup
CREATE INDEX idx_bt_invoice_cover ON billing_transactions(invoice_no, billing_status, net_amount, patient_amount);
```

### 6.3 Caching Strategy

```php
// Cache frequently accessed data
class BillingCache
{
    const TTL_PRICES = 3600;      // 1 hour
    const TTL_NHIS_TARIFF = 86400; // 24 hours
    const TTL_DAILY_SUMMARY = 300; // 5 minutes
    
    public function get_service_price($service_type, $service_id)
    {
        $key = "price:{$service_type}:{$service_id}";
        $cached = $this->cache->get($key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $price = $this->pricing_model->get_price($service_type, $service_id);
        $this->cache->set($key, $price, self::TTL_PRICES);
        
        return $price;
    }
}
```

---

## Part 7: Migration Strategy

### 7.1 Safe Rollout Plan

#### Phase 0: Preparation (Week -1)

```
□ Create all new tables (empty)
□ Deploy new models (inactive)
□ Add feature flags
□ Set up monitoring
□ Create rollback scripts
□ Backup all billing data
```

#### Phase 1: Shadow Mode (Week 1-2)

```
□ New system runs in parallel
□ Both systems create records
□ Compare outputs daily
□ Fix discrepancies
□ NO user-facing changes
```

```php
// Shadow mode implementation
class BillingEventHandler
{
    public function handle($event_type, $event_data, $user_id)
    {
        // Always run legacy system
        $legacy_result = $this->legacy_billing->process($event_type, $event_data);
        
        // Run new system in shadow mode
        if ($this->feature_flag->is_enabled('new_billing_shadow')) {
            try {
                $new_result = $this->new_billing->process($event_type, $event_data);
                $this->compare_and_log($legacy_result, $new_result);
            } catch (Exception $e) {
                $this->log_shadow_error($e);
                // Don't fail - legacy system is still primary
            }
        }
        
        return $legacy_result;
    }
}
```

#### Phase 2: Dual-Write Mode (Week 3-4)

```
□ New system becomes primary for WRITES
□ Legacy system receives copies
□ UI still reads from legacy
□ Monitor for issues
□ Quick rollback available
```

#### Phase 3: New System Primary (Week 5-6)

```
□ UI reads from new system
□ Legacy system read-only
□ Full monitoring active
□ User training complete
```

#### Phase 4: Legacy Deprecation (Week 7-8)

```
□ Stop writes to legacy
□ Archive legacy tables
□ Remove legacy code paths
□ Full production mode
```

### 7.2 Rollback Procedures

```sql
-- Rollback Script: Revert to Legacy System

-- Step 1: Disable new system
UPDATE system_config SET config_value = '0' WHERE config_key = 'new_billing_enabled';

-- Step 2: Restore legacy as primary
UPDATE system_config SET config_value = '1' WHERE config_key = 'legacy_billing_enabled';

-- Step 3: Mark new transactions for review
UPDATE billing_transactions 
SET billing_status = 'PENDING_REVIEW'
WHERE created_at > '[CUTOVER_DATE]'
AND source_module != 'LEGACY_MIGRATION';

-- Step 4: Notify administrators
INSERT INTO system_alerts (alert_type, message, created_at)
VALUES ('BILLING_ROLLBACK', 'New billing system rolled back. Review required.', NOW());
```

### 7.3 Data Migration Script (Enhanced)

```sql
-- Enhanced Migration with Validation

-- Step 1: Create migration tracking table
CREATE TABLE billing_migration_log (
    log_id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_table        VARCHAR(50) NOT NULL,
    source_id           VARCHAR(50) NOT NULL,
    target_table        VARCHAR(50) NOT NULL,
    target_id           BIGINT UNSIGNED NOT NULL,
    migration_status    ENUM('SUCCESS', 'FAILED', 'SKIPPED') NOT NULL,
    error_message       TEXT DEFAULT NULL,
    migrated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_bml_source (source_table, source_id),
    INDEX idx_bml_status (migration_status)
);

-- Step 2: Migrate with validation
DELIMITER //
CREATE PROCEDURE migrate_billing_transactions()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_billing_t_id INT;
    DECLARE v_invoice_no VARCHAR(30);
    DECLARE cur CURSOR FOR 
        SELECT bt.billing_t_id, b.invoice_no
        FROM iop_billing_t bt
        JOIN iop_billing b ON bt.invoice_no = b.invoice_no
        WHERE b.InActive = 0
        AND NOT EXISTS (
            SELECT 1 FROM billing_migration_log 
            WHERE source_table = 'iop_billing_t' 
            AND source_id = bt.billing_t_id
        );
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO v_billing_t_id, v_invoice_no;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- Migrate single record with error handling
        BEGIN
            DECLARE EXIT HANDLER FOR SQLEXCEPTION
            BEGIN
                INSERT INTO billing_migration_log 
                (source_table, source_id, target_table, target_id, migration_status, error_message)
                VALUES ('iop_billing_t', v_billing_t_id, 'billing_transactions', 0, 'FAILED', 
                        CONCAT('SQL Error migrating billing_t_id: ', v_billing_t_id));
            END;
            
            -- Actual migration
            INSERT INTO billing_transactions (...)
            SELECT ... FROM iop_billing_t WHERE billing_t_id = v_billing_t_id;
            
            -- Log success
            INSERT INTO billing_migration_log 
            (source_table, source_id, target_table, target_id, migration_status)
            VALUES ('iop_billing_t', v_billing_t_id, 'billing_transactions', LAST_INSERT_ID(), 'SUCCESS');
        END;
    END LOOP;
    
    CLOSE cur;
END //
DELIMITER ;
```

---

## Part 8: Implementation Priority

### Phase 1: Foundation (CRITICAL - Week 1-2)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| P0 | Create `billing_transactions` table (enhanced) | 1 day | None |
| P0 | Create `billing_invoices` table | 1 day | billing_transactions |
| P0 | Create `billing_payments` table | 1 day | billing_invoices |
| P0 | Create `service_gates` table | 1 day | billing_transactions |
| P0 | Create `BillingEventHandler` class | 2 days | All tables |
| P0 | Create `BillingTransactionModel` | 2 days | All tables |
| P1 | Add NHIS fields to schema | 0.5 day | billing_transactions |
| P1 | Add Insurance fields to schema | 0.5 day | billing_transactions |
| P1 | Unit tests | 2 days | All models |

### Phase 2: Event Integration (HIGH - Week 3-4)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| P0 | CONSULTATION_STARTED event | 1 day | Phase 1 |
| P0 | LAB_ORDERED event | 1 day | Phase 1 |
| P0 | PRESCRIPTION_CREATED event | 1 day | Phase 1 |
| P1 | RADIOLOGY_ORDERED event | 0.5 day | Phase 1 |
| P1 | PROCEDURE_ORDERED event | 0.5 day | Phase 1 |
| P1 | Service gate integration | 2 days | All events |
| P1 | Shadow mode testing | 3 days | All events |

### Phase 3: Ghana Compliance (HIGH - Week 5-6)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| P0 | NHIS tariff validation | 2 days | Phase 2 |
| P0 | NHIS claims table | 1 day | Phase 2 |
| P0 | Co-payment calculation | 1 day | NHIS validation |
| P1 | Insurance pre-auth workflow | 2 days | Phase 2 |
| P1 | Company credit limit check | 1 day | Phase 2 |
| P2 | Claim-IT integration prep | 2 days | NHIS claims |

### Phase 4: UI & Migration (MEDIUM - Week 7-8)

| Priority | Task | Effort | Dependencies |
|----------|------|--------|--------------|
| P0 | New cashier dashboard | 3 days | Phase 2 |
| P0 | Data migration scripts | 2 days | Phase 1 |
| P0 | Parallel run testing | 3 days | All phases |
| P1 | Legacy code deprecation | 2 days | Parallel run |
| P2 | Performance optimization | 2 days | All phases |
| P2 | Documentation | 2 days | All phases |

---

## Part 9: Recommendations Summary

### MUST DO (Before Go-Live)

1. **Add missing NHIS fields** to `billing_transactions`
2. **Create `service_gates` table** for payment verification
3. **Implement race condition prevention** with database locking
4. **Add `billing_invoices` table** for proper invoice management
5. **Create shadow mode** for safe parallel testing
6. **Define explicit rollback procedures**

### SHOULD DO (Within 30 Days)

1. Add `nhis_claims` table for claims management
2. Implement company credit limit checking
3. Add IPD daily charge scheduler
4. Create pre-aggregated summary tables
5. Implement waiver/deferral approval workflow

### NICE TO HAVE (Within 90 Days)

1. Claim-IT API integration
2. Insurance batch claiming
3. Company monthly invoicing automation
4. Advanced analytics dashboard
5. Mobile cashier interface

---

## Part 10: Final Assessment

### Production Readiness Checklist

| Category | Ready | Notes |
|----------|-------|-------|
| Core Schema | ⚠️ | Needs NHIS/Insurance fields |
| Event System | ⚠️ | Missing IPD events |
| Payment Flow | ✅ | Well designed |
| Service Gates | ❌ | Table missing |
| NHIS Compliance | ❌ | Major gaps |
| Insurance Compliance | ⚠️ | Partial |
| Company Billing | ❌ | Tables missing |
| Migration Plan | ⚠️ | Needs rollback detail |
| Performance | ✅ | Good indexing strategy |
| Audit Trail | ✅ | Comprehensive |

### Final Verdict

**APPROVED FOR DEVELOPMENT** with the following conditions:

1. Implement all **MUST DO** items before production deployment
2. Complete **shadow mode testing** for minimum 2 weeks
3. Obtain **sign-off from Finance department** on NHIS workflow
4. Complete **user acceptance testing** with cashiers
5. Prepare **rollback procedures** and test them

### Estimated Timeline to Production

| Milestone | Target Date |
|-----------|-------------|
| Schema Complete | Week 2 |
| Events Integrated | Week 4 |
| Ghana Compliance | Week 6 |
| Shadow Testing Complete | Week 8 |
| Production Go-Live | Week 10 |

---

## Document Control

| Version | Date | Reviewer | Status |
|---------|------|----------|--------|
| 1.0 | 2026-04-03 | Senior Hospital Financial Systems Architect | Initial Review |

---

**END OF REVIEW REPORT**
