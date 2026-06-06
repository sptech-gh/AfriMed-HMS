# Enterprise Billing Architecture

**Date:** April 3, 2026  
**Version:** 1.0  
**Status:** Implementation Roadmap

---

## Executive Summary

This document outlines the enterprise-grade billing architecture to address the critical risks identified in the current HMS billing system. The goal is to achieve **100% financial accuracy** through automated billing, single source of truth, and immutable financial records.

### Current State: 70-80% Enterprise-Ready

| ✅ Already Excellent | ❌ Critical Gaps |
|---------------------|------------------|
| Role-based workflow | Manual billing item entry |
| Clear patient journey | Loosely coupled pharmacy/billing |
| Queue management | Lab billing not auto-generated |
| EMR integration | No single source of truth |
| Lab workflow | Multiple pricing logic paths |
| Pharmacy workflow | Mutable financial records |
| Stock deduction on dispensing | No event-based billing |
| Payment verification before dispensing | |

---

## Part 1: Critical Risks Analysis

### Risk 1: Manual Billing Item Entry (VERY DANGEROUS)

**Current Flow:**
```
Cashier manually adds:
├── Consultation Fee
├── Lab Tests
└── Medications
```

**Problems:**
- Human error
- Duplicate charges
- Missing charges
- Pricing inconsistency
- Fraud risk

**Example Revenue Leakage:**
```
Doctor orders: FBC, Malaria
Cashier adds:  FBC only
Result:        Hospital loses 30 GHS
```

### Risk 2: Pharmacy & Billing Loosely Coupled

**Current:**
```
Billing adds medications → Pharmacy dispenses medications
(No guarantee both match)
```

**Example Mismatch:**
```
Billing:   Paracetamol x 30 tablets
Pharmacy:  Dispenses 20 tablets
Result:    Financial mismatch (10 tablets unaccounted)
```

### Risk 3: Lab Billing Not Auto-Generated

**Current (WRONG):**
```
Doctor → Orders → Lab Processes → Cashier manually bills
```

**Correct:**
```
Doctor Order → Auto Bill → Payment → Lab Process
```

### Risk 4: No Single Source of Truth

**Current billing items originate from:**
- Doctor consultation
- Lab module
- Pharmacy module
- Cashier manual input

**Violation:** All charges must originate from ONE billing engine.

---

## Part 2: Enterprise-Grade Architecture

### Gold Standard Financial Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    DOCTOR ACTIONS                            │
│         (Consultation, Lab Orders, Prescriptions)           │
└─────────────────────┬───────────────────────────────────────┘
                      │ AUTO-GENERATE
                      ▼
┌─────────────────────────────────────────────────────────────┐
│              CENTRAL BILLING ENGINE                          │
│           billing_transactions (Single Table)                │
│                                                              │
│  Event Triggers:                                             │
│  ├── CONSULTATION_STARTED → Add consultation fee            │
│  ├── LAB_ORDERED → Add lab charges                          │
│  ├── PRESCRIPTION_CREATED → Add medication charges          │
│  ├── PROCEDURE_ORDERED → Add procedure charges              │
│  └── ADMISSION_CREATED → Add admission charges              │
└─────────────────────┬───────────────────────────────────────┘
                      │ AUTO-CALCULATE
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                  INVOICE (Auto-Generated)                    │
│                                                              │
│  Cashier Role:                                               │
│  ├── Review charges (NO manual entry)                       │
│  ├── Apply discount (if authorized)                         │
│  └── Collect payment                                         │
└─────────────────────┬───────────────────────────────────────┘
                      │ PAYMENT VERIFIED
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                SERVICE EXECUTION                             │
│                                                              │
│  ├── Lab: Process samples (payment verified)                │
│  ├── Pharmacy: Dispense medications (payment verified)      │
│  └── Radiology: Perform scan (payment verified)             │
└─────────────────────────────────────────────────────────────┘
```

---

## Part 3: Database Schema Design

### Core Table: `billing_transactions`

This is the **Single Source of Truth** for all charges.

```sql
CREATE TABLE billing_transactions (
    -- Primary Key
    txn_id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    txn_uuid            CHAR(36) NOT NULL UNIQUE,
    
    -- Visit Context
    visit_id            VARCHAR(30) NOT NULL COMMENT 'IO_ID / iop_id',
    patient_no          VARCHAR(25) NOT NULL,
    encounter_type      ENUM('OPD', 'IPD', 'EMERGENCY') NOT NULL DEFAULT 'OPD',
    
    -- Charge Details
    charge_type         ENUM('CONSULTATION', 'REGISTRATION', 'LAB', 'PHARMACY', 
                             'RADIOLOGY', 'PROCEDURE', 'ADMISSION', 'ROOM', 
                             'SURGERY', 'CONSUMABLE', 'OTHER') NOT NULL,
    charge_code         VARCHAR(50) NOT NULL COMMENT 'Reference to service master',
    charge_name         VARCHAR(255) NOT NULL,
    department          VARCHAR(50) DEFAULT NULL,
    
    -- Quantity & Pricing
    quantity            DECIMAL(10,2) NOT NULL DEFAULT 1,
    unit_price          DECIMAL(18,2) NOT NULL DEFAULT 0,
    gross_amount        DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'quantity * unit_price',
    discount_percent    DECIMAL(5,2) DEFAULT 0,
    discount_amount     DECIMAL(18,2) DEFAULT 0,
    net_amount          DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'gross - discount',
    
    -- Payer Split
    payer_type          ENUM('CASH', 'NHIS', 'INSURANCE', 'COMPANY') NOT NULL DEFAULT 'CASH',
    coverage_percent    DECIMAL(5,2) DEFAULT 0,
    covered_amount      DECIMAL(18,2) DEFAULT 0 COMMENT 'Amount covered by payer',
    patient_amount      DECIMAL(18,2) NOT NULL DEFAULT 0 COMMENT 'Amount patient pays',
    
    -- Source Tracking (Immutable)
    source_module       VARCHAR(50) NOT NULL COMMENT 'OPD, LAB, PHARMACY, etc.',
    source_ref_id       VARCHAR(50) NOT NULL COMMENT 'FK to source record',
    source_ref_table    VARCHAR(50) NOT NULL COMMENT 'Source table name',
    
    -- Event Tracking
    triggered_by_event  VARCHAR(50) NOT NULL COMMENT 'Event that created this charge',
    triggered_by_user   VARCHAR(25) NOT NULL COMMENT 'User who triggered the event',
    triggered_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Billing Status
    billing_status      ENUM('PENDING', 'INVOICED', 'PAID', 'CANCELLED', 'WAIVED') 
                        NOT NULL DEFAULT 'PENDING',
    invoice_no          VARCHAR(30) DEFAULT NULL,
    receipt_no          VARCHAR(30) DEFAULT NULL,
    
    -- Service Status
    service_status      ENUM('PENDING', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED') 
                        NOT NULL DEFAULT 'PENDING',
    service_completed_at DATETIME DEFAULT NULL,
    service_completed_by VARCHAR(25) DEFAULT NULL,
    
    -- Immutability Control
    is_locked           TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = Cannot be modified',
    locked_at           DATETIME DEFAULT NULL,
    locked_reason       VARCHAR(100) DEFAULT NULL,
    
    -- Audit Trail
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          VARCHAR(25) NOT NULL,
    updated_at          DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by          VARCHAR(25) DEFAULT NULL,
    
    -- Soft Delete
    is_active           TINYINT(1) NOT NULL DEFAULT 1,
    cancelled_at        DATETIME DEFAULT NULL,
    cancelled_by        VARCHAR(25) DEFAULT NULL,
    cancel_reason       VARCHAR(255) DEFAULT NULL,
    
    -- Indexes
    INDEX idx_bt_visit (visit_id),
    INDEX idx_bt_patient (patient_no),
    INDEX idx_bt_status (billing_status),
    INDEX idx_bt_invoice (invoice_no),
    INDEX idx_bt_type (charge_type),
    INDEX idx_bt_date (created_at),
    INDEX idx_bt_source (source_module, source_ref_id),
    
    -- Unique constraint to prevent duplicate charges
    UNIQUE KEY uq_bt_source (source_module, source_ref_id, source_ref_table)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Financial Adjustments Table

For corrections without modifying original records:

```sql
CREATE TABLE billing_adjustments (
    adj_id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    adj_uuid            CHAR(36) NOT NULL UNIQUE,
    
    -- Reference to Original
    original_txn_id     BIGINT UNSIGNED NOT NULL,
    invoice_no          VARCHAR(30) NOT NULL,
    patient_no          VARCHAR(25) NOT NULL,
    
    -- Adjustment Details
    adj_type            ENUM('DISCOUNT', 'REFUND', 'CREDIT_NOTE', 'WRITE_OFF', 
                             'PRICE_CORRECTION', 'QUANTITY_CORRECTION') NOT NULL,
    adj_amount          DECIMAL(18,2) NOT NULL,
    adj_reason          VARCHAR(255) NOT NULL,
    
    -- Authorization
    authorized_by       VARCHAR(25) NOT NULL,
    authorized_at       DATETIME NOT NULL,
    authorization_code  VARCHAR(50) DEFAULT NULL,
    
    -- Audit
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by          VARCHAR(25) NOT NULL,
    
    -- Immutable
    is_locked           TINYINT(1) NOT NULL DEFAULT 1,
    
    INDEX idx_ba_txn (original_txn_id),
    INDEX idx_ba_invoice (invoice_no),
    INDEX idx_ba_patient (patient_no),
    
    FOREIGN KEY (original_txn_id) REFERENCES billing_transactions(txn_id)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### Billing Events Log (Immutable Audit)

```sql
CREATE TABLE billing_events_log (
    event_id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_uuid          CHAR(36) NOT NULL UNIQUE,
    
    -- Event Details
    event_type          VARCHAR(50) NOT NULL,
    event_source        VARCHAR(50) NOT NULL,
    event_data          JSON NOT NULL,
    
    -- Context
    visit_id            VARCHAR(30) DEFAULT NULL,
    patient_no          VARCHAR(25) DEFAULT NULL,
    txn_id              BIGINT UNSIGNED DEFAULT NULL,
    invoice_no          VARCHAR(30) DEFAULT NULL,
    
    -- Audit (Immutable)
    triggered_by        VARCHAR(25) NOT NULL,
    triggered_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address          VARCHAR(45) DEFAULT NULL,
    user_agent          VARCHAR(255) DEFAULT NULL,
    
    INDEX idx_bel_type (event_type),
    INDEX idx_bel_visit (visit_id),
    INDEX idx_bel_patient (patient_no),
    INDEX idx_bel_date (triggered_at)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Part 4: Event-Based Auto-Billing

### Event Triggers

| Event | Trigger Point | Auto-Generated Charge |
|-------|---------------|----------------------|
| `CONSULTATION_STARTED` | Doctor opens patient EMR | Consultation Fee |
| `REGISTRATION_CREATED` | New patient registered | Registration Fee |
| `LAB_ORDERED` | Doctor orders lab test | Lab Test Fee |
| `PRESCRIPTION_CREATED` | Doctor prescribes medication | Medication Charges |
| `RADIOLOGY_ORDERED` | Doctor orders scan | Radiology Fee |
| `PROCEDURE_ORDERED` | Doctor orders procedure | Procedure Fee |
| `ADMISSION_CREATED` | Patient admitted to ward | Admission Fee |
| `ROOM_ASSIGNED` | Room assigned to patient | Daily Room Charge |

### Event Handler Implementation

```php
/**
 * Central Billing Event Handler
 * All billing events flow through this single point
 */
class BillingEventHandler
{
    /**
     * Process billing event and auto-generate charge
     */
    public function handle($event_type, $event_data, $user_id)
    {
        // Log event (immutable)
        $this->log_event($event_type, $event_data, $user_id);
        
        // Route to appropriate handler
        switch ($event_type) {
            case 'CONSULTATION_STARTED':
                return $this->handle_consultation($event_data, $user_id);
            case 'LAB_ORDERED':
                return $this->handle_lab_order($event_data, $user_id);
            case 'PRESCRIPTION_CREATED':
                return $this->handle_prescription($event_data, $user_id);
            case 'RADIOLOGY_ORDERED':
                return $this->handle_radiology($event_data, $user_id);
            case 'PROCEDURE_ORDERED':
                return $this->handle_procedure($event_data, $user_id);
            default:
                throw new Exception("Unknown billing event: $event_type");
        }
    }
    
    /**
     * Auto-generate consultation charge
     */
    private function handle_consultation($data, $user_id)
    {
        // Check if consultation fee already exists for this visit
        if ($this->charge_exists('CONSULTATION', $data['visit_id'])) {
            return ['success' => false, 'error' => 'Consultation fee already charged'];
        }
        
        // Get consultation fee based on patient type
        $fee = $this->get_consultation_fee($data['patient_no']);
        
        // Create billing transaction
        return $this->create_charge([
            'visit_id'          => $data['visit_id'],
            'patient_no'        => $data['patient_no'],
            'charge_type'       => 'CONSULTATION',
            'charge_code'       => 'CONSULT-001',
            'charge_name'       => 'Consultation Fee',
            'quantity'          => 1,
            'unit_price'        => $fee,
            'source_module'     => 'OPD',
            'source_ref_id'     => $data['visit_id'],
            'source_ref_table'  => 'patient_details_iop',
            'triggered_by_event'=> 'CONSULTATION_STARTED',
            'triggered_by_user' => $user_id
        ]);
    }
    
    /**
     * Auto-generate lab charges
     */
    private function handle_lab_order($data, $user_id)
    {
        $charges = [];
        
        foreach ($data['tests'] as $test) {
            // Check for duplicate
            if ($this->charge_exists('LAB', $data['visit_id'], $test['test_id'])) {
                continue;
            }
            
            // Get test price
            $price = $this->get_lab_price($test['test_id'], $data['patient_no']);
            
            // Create charge
            $charges[] = $this->create_charge([
                'visit_id'          => $data['visit_id'],
                'patient_no'        => $data['patient_no'],
                'charge_type'       => 'LAB',
                'charge_code'       => $test['test_code'],
                'charge_name'       => $test['test_name'],
                'quantity'          => 1,
                'unit_price'        => $price,
                'source_module'     => 'LABORATORY',
                'source_ref_id'     => $test['order_id'],
                'source_ref_table'  => 'io_lab',
                'triggered_by_event'=> 'LAB_ORDERED',
                'triggered_by_user' => $user_id
            ]);
        }
        
        return ['success' => true, 'charges' => $charges];
    }
    
    /**
     * Auto-generate pharmacy charges
     */
    private function handle_prescription($data, $user_id)
    {
        $charges = [];
        
        foreach ($data['medications'] as $med) {
            // Check for duplicate
            if ($this->charge_exists('PHARMACY', $data['visit_id'], $med['prescription_id'])) {
                continue;
            }
            
            // Get medication price
            $price = $this->get_medication_price($med['drug_id'], $data['patient_no']);
            
            // Create charge
            $charges[] = $this->create_charge([
                'visit_id'          => $data['visit_id'],
                'patient_no'        => $data['patient_no'],
                'charge_type'       => 'PHARMACY',
                'charge_code'       => $med['drug_code'],
                'charge_name'       => $med['drug_name'],
                'quantity'          => $med['quantity'],
                'unit_price'        => $price,
                'source_module'     => 'PHARMACY',
                'source_ref_id'     => $med['prescription_id'],
                'source_ref_table'  => 'iop_medication',
                'triggered_by_event'=> 'PRESCRIPTION_CREATED',
                'triggered_by_user' => $user_id
            ]);
        }
        
        return ['success' => true, 'charges' => $charges];
    }
}
```

---

## Part 5: Accounting Principles Enforcement

### Principle 1: Single Source of Truth

```
ALL charges originate from: billing_transactions
NO other table creates charges
```

**Implementation:**
- Remove direct inserts to `iop_billing_t` from all modules
- All modules call `BillingEventHandler->handle()`
- `billing_transactions` is the ONLY charge table

### Principle 2: Immutable Financial Records

```
Once created:
├── Invoice → Cannot be edited (only adjusted)
├── Payment → Cannot be edited (only refunded)
└── Receipt → Cannot be edited (only voided)
```

**Implementation:**
```sql
-- Lock transaction after invoicing
UPDATE billing_transactions 
SET is_locked = 1, 
    locked_at = NOW(), 
    locked_reason = 'INVOICED'
WHERE invoice_no = ?;

-- All corrections via billing_adjustments table
INSERT INTO billing_adjustments (
    original_txn_id, adj_type, adj_amount, adj_reason, authorized_by
) VALUES (?, 'PRICE_CORRECTION', ?, 'Price was incorrect', ?);
```

### Principle 3: Payment Reconciliation

```
Every payment MUST link to:
├── Invoice Number
├── Patient Number
├── Visit ID
└── Receipt Number
```

**Validation:**
```php
public function validate_payment($payment_data)
{
    // Must have invoice
    if (empty($payment_data['invoice_no'])) {
        throw new Exception('Payment must reference an invoice');
    }
    
    // Invoice must exist and be unpaid
    $invoice = $this->get_invoice($payment_data['invoice_no']);
    if (!$invoice) {
        throw new Exception('Invoice not found');
    }
    if ($invoice->payment_status === 'PAID') {
        throw new Exception('Invoice already fully paid');
    }
    
    // Amount cannot exceed balance
    $balance = $this->get_invoice_balance($payment_data['invoice_no']);
    if ($payment_data['amount'] > $balance) {
        throw new Exception('Payment exceeds invoice balance');
    }
    
    return true;
}
```

### Principle 4: Department Revenue Tracking

```sql
-- Revenue by Department View
CREATE VIEW v_department_revenue AS
SELECT 
    DATE(created_at) as revenue_date,
    charge_type as department,
    COUNT(*) as transaction_count,
    SUM(net_amount) as gross_revenue,
    SUM(covered_amount) as insurance_revenue,
    SUM(patient_amount) as cash_revenue,
    SUM(CASE WHEN billing_status = 'PAID' THEN net_amount ELSE 0 END) as collected_revenue,
    SUM(CASE WHEN billing_status = 'PENDING' THEN net_amount ELSE 0 END) as pending_revenue
FROM billing_transactions
WHERE is_active = 1
GROUP BY DATE(created_at), charge_type;
```

---

## Part 6: Cashier Dashboard Redesign

### Current Problems
- Manual item entry
- Multiple screens
- No auto-calculation
- Prone to errors

### Proposed Dashboard

```
┌─────────────────────────────────────────────────────────────────┐
│  CASHIER BILLING DASHBOARD                        [Patient: 000045] │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Patient: John Doe                    Visit: OPD-2026040300001  │
│  Type: NHIS                           Doctor: Dr. Smith         │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│  AUTO-GENERATED CHARGES                              [Read-Only] │
├─────────────────────────────────────────────────────────────────┤
│  # │ Item                    │ Qty │ Price   │ Amount  │ Status │
│────┼─────────────────────────┼─────┼─────────┼─────────┼────────│
│  1 │ Consultation Fee        │  1  │  30.00  │   30.00 │ ✓ Auto │
│  2 │ FBC                     │  1  │  80.00  │   80.00 │ ✓ Auto │
│  3 │ Malaria RDT             │  1  │  30.00  │   30.00 │ ✓ Auto │
│  4 │ Paracetamol 500mg       │ 30  │   0.50  │   15.00 │ ✓ Auto │
│  5 │ Amoxicillin 500mg       │ 21  │   1.00  │   21.00 │ ✓ Auto │
├─────────────────────────────────────────────────────────────────┤
│                                          Subtotal:      176.00  │
│                                          NHIS Cover:    -140.80 │
│                                          Patient Pays:   35.20  │
├─────────────────────────────────────────────────────────────────┤
│  PAYMENT                                                         │
├─────────────────────────────────────────────────────────────────┤
│  Amount Due:    GHS 35.20                                        │
│  Payment Type:  [Cash ▼]                                         │
│  Amount Paid:   [35.20    ]                                      │
│  Change:        GHS 0.00                                         │
│                                                                  │
│  [Apply Discount]  [Waive Charges]  [COLLECT PAYMENT]           │
└─────────────────────────────────────────────────────────────────┘
```

### Key UI Principles

| Principle | Implementation |
|-----------|----------------|
| **No Manual Entry** | Charges auto-generated, read-only display |
| **Single Screen** | All charges visible on one page |
| **Auto-Calculate** | Totals computed automatically |
| **Clear Status** | Visual indicators for charge status |
| **Quick Actions** | One-click payment collection |

---

## Part 7: Implementation Roadmap

### Phase 1: Foundation (Week 1-2)

| Task | Priority | Effort |
|------|----------|--------|
| Create `billing_transactions` table | Critical | 1 day |
| Create `billing_adjustments` table | Critical | 1 day |
| Create `billing_events_log` table | Critical | 1 day |
| Create `BillingEventHandler` class | Critical | 2 days |
| Create `BillingTransactionModel` | Critical | 2 days |
| Unit tests for billing engine | High | 2 days |

### Phase 2: Event Integration (Week 3-4)

| Task | Priority | Effort |
|------|----------|--------|
| Integrate consultation auto-billing | Critical | 2 days |
| Integrate lab order auto-billing | Critical | 2 days |
| Integrate prescription auto-billing | Critical | 2 days |
| Integrate radiology auto-billing | High | 1 day |
| Integrate procedure auto-billing | High | 1 day |
| Integration tests | High | 2 days |

### Phase 3: Cashier UI (Week 5-6)

| Task | Priority | Effort |
|------|----------|--------|
| New cashier dashboard view | Critical | 3 days |
| Invoice review interface | Critical | 2 days |
| Payment collection interface | Critical | 2 days |
| Receipt generation | High | 1 day |
| User acceptance testing | High | 2 days |

### Phase 4: Migration & Cleanup (Week 7-8)

| Task | Priority | Effort |
|------|----------|--------|
| Migrate existing data | Critical | 3 days |
| Remove old billing code paths | High | 2 days |
| Update all modules to use new engine | High | 3 days |
| Performance optimization | Medium | 2 days |
| Documentation | Medium | 2 days |

---

## Part 8: Success Metrics

### Financial Accuracy

| Metric | Current | Target |
|--------|---------|--------|
| Missing charges | ~5-10% | 0% |
| Duplicate charges | ~2-3% | 0% |
| Pricing errors | ~3-5% | 0% |
| Revenue leakage | Unknown | 0% |

### Operational Efficiency

| Metric | Current | Target |
|--------|---------|--------|
| Time to bill patient | 3-5 minutes | < 1 minute |
| Cashier clicks per transaction | 15-20 | < 5 |
| Manual data entry | 100% | 0% |
| Reconciliation time | 2-3 hours/day | 15 minutes/day |

### System Health

| Metric | Current | Target |
|--------|---------|--------|
| Billing code paths | 5+ | 1 |
| Payment code paths | 4+ | 1 |
| Billing tables | 8+ | 3 |
| Single source of truth | No | Yes |

---

## Appendix A: Pricing Engine

### Price Resolution Order

```
1. Company-specific price (if corporate patient)
2. Insurance-specific price (if insured)
3. NHIS price (if NHIS patient)
4. Default price (cash patient)
```

### Price Lookup Function

```php
public function get_service_price($service_type, $service_id, $patient_no)
{
    $patient = $this->get_patient($patient_no);
    
    // 1. Check company pricing
    if ($patient->company_id) {
        $price = $this->get_company_price($service_type, $service_id, $patient->company_id);
        if ($price !== null) return $price;
    }
    
    // 2. Check insurance pricing
    if ($patient->insurance_id) {
        $price = $this->get_insurance_price($service_type, $service_id, $patient->insurance_id);
        if ($price !== null) return $price;
    }
    
    // 3. Check NHIS pricing
    if ($patient->is_nhis) {
        $price = $this->get_nhis_price($service_type, $service_id);
        if ($price !== null) return $price;
    }
    
    // 4. Default price
    return $this->get_default_price($service_type, $service_id);
}
```

---

## Appendix B: Reconciliation Queries

### Daily Revenue Reconciliation

```sql
-- Total charges created today
SELECT 
    charge_type,
    COUNT(*) as count,
    SUM(net_amount) as total
FROM billing_transactions
WHERE DATE(created_at) = CURDATE()
AND is_active = 1
GROUP BY charge_type;

-- Total payments received today
SELECT 
    payment_type,
    COUNT(*) as count,
    SUM(total_amount) as total
FROM iop_receipt
WHERE DATE(dDate) = CURDATE()
AND InActive = 0
GROUP BY payment_type;

-- Outstanding balance
SELECT 
    SUM(bt.patient_amount) as total_due,
    SUM(COALESCE(r.paid, 0)) as total_paid,
    SUM(bt.patient_amount) - SUM(COALESCE(r.paid, 0)) as outstanding
FROM billing_transactions bt
LEFT JOIN (
    SELECT invoice_no, SUM(total_amount) as paid
    FROM iop_receipt
    WHERE InActive = 0
    GROUP BY invoice_no
) r ON bt.invoice_no = r.invoice_no
WHERE bt.is_active = 1
AND bt.billing_status != 'CANCELLED';
```

---

## Appendix C: Migration Script Outline

```sql
-- Step 1: Create new tables
-- (See Part 3 for DDL)

-- Step 2: Migrate existing billing items
INSERT INTO billing_transactions (
    visit_id, patient_no, charge_type, charge_code, charge_name,
    quantity, unit_price, gross_amount, net_amount,
    source_module, source_ref_id, source_ref_table,
    triggered_by_event, triggered_by_user, created_at, created_by,
    billing_status, invoice_no
)
SELECT 
    b.iop_id,
    b.patient_no,
    'OTHER',
    'LEGACY',
    bt.bill_name,
    bt.qty,
    bt.rate,
    bt.amount,
    bt.amount - COALESCE(bt.discount, 0),
    'LEGACY_MIGRATION',
    bt.billing_t_id,
    'iop_billing_t',
    'LEGACY_MIGRATION',
    'SYSTEM',
    b.dDate,
    'SYSTEM',
    CASE 
        WHEN b.payment_status = 'PAID' THEN 'PAID'
        WHEN b.payment_status = 'PARTIAL' THEN 'INVOICED'
        ELSE 'INVOICED'
    END,
    b.invoice_no
FROM iop_billing_t bt
JOIN iop_billing b ON bt.invoice_no = b.invoice_no
WHERE b.InActive = 0;

-- Step 3: Verify migration
SELECT 
    (SELECT COUNT(*) FROM iop_billing_t WHERE InActive = 0) as old_count,
    (SELECT COUNT(*) FROM billing_transactions WHERE source_module = 'LEGACY_MIGRATION') as new_count;
```

---

## Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-04-03 | HMS Architect | Initial document |

---

**END OF DOCUMENT**
