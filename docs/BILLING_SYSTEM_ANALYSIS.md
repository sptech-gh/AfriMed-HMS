# HMS Billing System Analysis Report

**Date:** April 3, 2026  
**Author:** Healthcare Financial Systems Architect  
**Version:** 1.0

---

## Table of Contents

1. [Current System Overview](#1-current-system-overview)
2. [Major Issues Identified](#2-major-issues-identified)
3. [Duplicate Functionality Analysis](#3-duplicate-functionality-analysis)
4. [Accounting Weaknesses](#4-accounting-weaknesses)
5. [Single Source of Truth Violations](#5-single-source-of-truth-violations)
6. [UX / Workflow Issues](#6-ux--workflow-issues)
7. [Risk Assessment](#7-risk-assessment)
8. [Recommendations](#8-recommendations)
9. [Proposed Clean Architecture](#9-proposed-clean-architecture)
10. [Production Readiness Assessment](#10-production-readiness-assessment)

---

## 1. Current System Overview

### Billing Architecture Summary

The HMS billing system has evolved organically, resulting in **multiple overlapping billing modules** that create complexity and risk:

| Component | Purpose | Status |
|-----------|---------|--------|
| **POS Controller** (`pos.php`) | Point of Sale billing | Active - Primary |
| **Billing Controller** (`billing.php`) | Patient search, Smart Billing | Active |
| **Cashier Controller** (`cashier.php`) | Dashboard, Payments, Reconciliation | Active |
| **Billing History** (`billing_history.php`) | Invoice history | Active |
| **Service Queue** (`service_queue.php`) | Service-based billing | Active |

### Billing Models (5 Total)

| Model | Lines | Purpose |
|-------|-------|---------|
| `billing_model.php` | 3,909 | Legacy billing operations |
| `unified_billing_model.php` | 1,930 | Intended single source of truth |
| `smart_billing_model.php` | 569 | Registration/consultation fees |
| `service_billing_model.php` | 1,036 | Service orders billing |
| `cashier_model.php` | 734 | Cashier-specific operations |

### Core Database Tables

| Table | Purpose | Records Source |
|-------|---------|----------------|
| `iop_billing` | Invoice headers | Multiple entry points |
| `iop_billing_t` | Invoice line items | Multiple entry points |
| `iop_receipt` | Payment records | Multiple entry points |
| `billing_queue` | Pending billable items | Unified model |
| `iop_lab_billing` | Lab-specific billing | Laboratory module |
| `pharmacy_billing_queue` | Pharmacy billing | Pharmacy module |
| `smart_billing_ledger` | Registration/consultation | Smart billing |
| `cashier_payment_log` | Payment audit | Cashier model |

---

## 2. Major Issues Identified

### CRITICAL Issues

| # | Issue | Impact | Risk Level |
|---|-------|--------|------------|
| 1 | **Multiple Invoice Generation Points** | Invoices created from POS, Billing, Smart Billing, Service Queue | **HIGH** |
| 2 | **Multiple Payment Processing Functions** | `pos.save_payment()`, `cashier_model.process_payment()`, `unified_billing_model.process_payment()` | **HIGH** |
| 3 | **Inconsistent Receipt Field Usage** | `total_amount` vs `amountPaid` discrepancy causes dashboard mismatches | **HIGH** |
| 4 | **No Transaction Locking** | Race conditions possible during concurrent payments | **HIGH** |

### HIGH Issues

| # | Issue | Impact |
|---|-------|--------|
| 5 | **Fragmented Billing Queues** | `billing_queue`, `pharmacy_billing_queue`, `iop_lab_billing` operate independently |
| 6 | **Missing Foreign Key Constraints** | No referential integrity between billing tables |
| 7 | **Inconsistent Payment Status Updates** | Multiple functions update `payment_status` with different logic |
| 8 | **Schema Migration Chaos** | 5+ `ensure_*_schema()` functions run on every request |

### MEDIUM Issues

| # | Issue | Impact |
|---|-------|--------|
| 9 | **Duplicate Dashboard Widgets** | Same metrics calculated differently across dashboards |
| 10 | **No Centralized Pricing Engine** | Prices fetched from multiple sources |
| 11 | **Missing Audit Trail Consistency** | Multiple audit tables with different schemas |

---

## 3. Duplicate Functionality Analysis

### Duplicate Billing Flows

```
FLOW 1: POS → pos.php → billing_model → iop_billing
FLOW 2: Smart Billing → billing.php → smart_billing_model → iop_billing
FLOW 3: Service Queue → service_queue.php → service_billing_model → service_orders
FLOW 4: Pharmacy → pharmacy.php → pharmacy_model → pharmacy_billing_queue
FLOW 5: Laboratory → laboratory.php → laboratory_model → iop_lab_billing
```

### Duplicate Payment Processing

| Function | Location | Creates Receipt In |
|----------|----------|-------------------|
| `save_payment()` | `pos.php` | `iop_receipt` |
| `process_payment()` | `cashier_model.php` | `iop_receipt` + `cashier_payment_log` |
| `process_payment()` | `unified_billing_model.php` | `iop_receipt` |
| `record_payment()` | `billing_model.php` | `iop_receipt` |

### Duplicate Dashboards

| Dashboard | Location | Overlapping Metrics |
|-----------|----------|---------------------|
| Cashier Dashboard | `cashier/dashboard.php` | Today's revenue, outstanding |
| POS Dashboard | `pos/index.php` | Today's billing |
| Smart Billing | `billing/smart_billing.php` | Pending queue, billed today |
| Billing History | `billing_history/index.php` | Invoice totals |

---

## 4. Accounting Weaknesses

### Missing Accounting Controls

| Control | Status | Risk |
|---------|--------|------|
| Double-entry accounting | Partially implemented in `unified_billing_model` | Revenue recognition gaps |
| Trial balance validation | **Missing** | Cannot verify books balance |
| Period closing | **Missing** | No month-end close process |
| Void/reversal workflow | **Missing** | Cannot properly reverse transactions |
| Credit note system | **Missing** | Overpayments handled incorrectly |

### Missing Reconciliation Features

| Feature | Status |
|---------|--------|
| Bank reconciliation | **Missing** |
| Daily cash count verification | **Missing** |
| Shift handover reconciliation | **Missing** |
| Insurance claims reconciliation | **Missing** |

### Audit Trail Gaps

| Gap | Impact |
|-----|--------|
| No immutable audit log | Records can be modified without trace |
| Multiple audit tables | `billing_audit_log`, `smart_billing_audit`, `cashier_payment_log` |
| Inconsistent audit fields | Different schemas across audit tables |

---

## 5. Single Source of Truth Violations

### Invoice Generation (CRITICAL)

```
VIOLATION: 4+ different code paths create invoices

1. pos.php → Direct insert to iop_billing
2. billing_model.php → Multiple invoice functions
3. unified_billing_model.php → generate_invoice()
4. smart_billing_model.php → execute_one_click_billing()
```

### Payment Recording (CRITICAL)

```
VIOLATION: 4+ different code paths record payments

1. pos.php:save_payment() → Direct insert to iop_receipt
2. cashier_model.php:process_payment() → iop_receipt + cashier_payment_log
3. unified_billing_model.php:process_payment() → iop_receipt
4. billing_model.php → Various payment functions
```

### Balance Calculation (HIGH)

```
VIOLATION: Balance calculated differently

1. Dashboard: Uses amountPaid column
2. Cashier: Uses SUM(total_amount) from iop_receipt
3. POS: Uses balance_due column from iop_billing
4. Discrepancy detector: Uses total_amount from iop_receipt
```

---

## 6. UX / Workflow Issues

### Cashier Sidebar Analysis

| Current Item | Recommendation |
|--------------|----------------|
| POS | **KEEP** - Primary billing interface |
| Billing List | **MERGE** with POS |
| Search Patient / Bill | **MERGE** with POS |
| Pharmacy Bills | **KEEP** - Specialized workflow |
| Smart Billing | **EVALUATE** - Overlaps with POS |
| Service Queue | **MERGE** with Billing Queue |
| Payment Collection | **MERGE** with POS |
| Daily Collection | **KEEP** - Reporting |
| Billing Queue | **KEEP** - Pending items |
| Daily Reconciliation | **KEEP** - End of day |

### Workflow Inefficiencies

| Issue | Impact |
|-------|--------|
| 3+ clicks to bill a patient | Slow cashier workflow |
| Popup windows for billing items | Disruptive UX |
| No keyboard shortcuts | Reduced efficiency |
| Multiple screens for same task | Confusion |
| No batch billing | One patient at a time |

### Confusing Navigation

| Problem | Example |
|---------|---------|
| Similar menu items | "Payment Collection" vs "Collect Payment" |
| Unclear module boundaries | Smart Billing vs POS vs Service Queue |
| Inconsistent naming | "Bill" vs "Invoice" vs "Receipt" |

---

## 7. Risk Assessment

### HIGH RISK

| Risk | Probability | Impact | Mitigation Priority |
|------|-------------|--------|---------------------|
| Duplicate payments | Medium | Financial loss | **IMMEDIATE** |
| Revenue leakage | High | Financial loss | **IMMEDIATE** |
| Data inconsistency | High | Audit failure | **IMMEDIATE** |
| Overpayment mishandling | Medium | Patient complaints | **HIGH** |

### MEDIUM RISK

| Risk | Probability | Impact |
|------|-------------|--------|
| Dashboard showing wrong totals | High | Management decisions |
| Missing audit trail | Medium | Compliance issues |
| Performance degradation | Medium | User frustration |

### LOW RISK

| Risk | Probability | Impact |
|------|-------------|--------|
| UI confusion | High | Training overhead |
| Report inaccuracies | Medium | Manual corrections |

---

## 8. Recommendations

### Architecture Recommendations

| Priority | Recommendation | Effort |
|----------|----------------|--------|
| **1** | Consolidate all invoice creation into `unified_billing_model.generate_invoice()` | High |
| **2** | Consolidate all payment processing into `unified_billing_model.process_payment()` | High |
| **3** | Implement single `billing_queue` for ALL billable items | Medium |
| **4** | Standardize receipt fields (`total_amount` = `amountPaid` always) | Low |
| **5** | Add database foreign key constraints | Medium |

### Workflow Recommendations

| Priority | Recommendation |
|----------|----------------|
| **1** | Create unified POS interface that handles all billing scenarios |
| **2** | Eliminate popup windows - use inline/modal components |
| **3** | Implement keyboard shortcuts for common actions |
| **4** | Add batch billing capability |
| **5** | Consolidate sidebar to 5-6 essential items |

### Consolidation Recommendations

| Current | Consolidate Into |
|---------|------------------|
| `billing_model.php` | `unified_billing_model.php` |
| `smart_billing_model.php` | `unified_billing_model.php` |
| `service_billing_model.php` | `unified_billing_model.php` |
| `cashier_model.php` (payment functions) | `unified_billing_model.php` |
| `iop_lab_billing` | `billing_queue` |
| `pharmacy_billing_queue` | `billing_queue` |

---

## 9. Proposed Clean Architecture

### Simplified Billing Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    SERVICE MODULES                           │
│  (Lab, Pharmacy, Sonography, Procedures, Consultation)      │
└─────────────────────┬───────────────────────────────────────┘
                      │ add_to_billing_queue()
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                   BILLING QUEUE                              │
│              (Single Source of Truth)                        │
│                   billing_queue table                        │
└─────────────────────┬───────────────────────────────────────┘
                      │ generate_invoice()
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                     INVOICE                                  │
│                 iop_billing + iop_billing_t                  │
└─────────────────────┬───────────────────────────────────────┘
                      │ process_payment()
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                     RECEIPT                                  │
│                    iop_receipt                               │
└─────────────────────┬───────────────────────────────────────┘
                      │ record_to_ledger()
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                 FINANCIAL LEDGER                             │
│              (Double-Entry Accounting)                       │
└─────────────────────────────────────────────────────────────┘
```

### Proposed Cashier Sidebar (Simplified)

```
CASHIER MODULE
├── Dashboard (unified metrics)
├── Billing Queue (all pending items)
├── POS / Collect Payment (unified interface)
├── Invoice History
├── Daily Collection Report
└── Reconciliation
```

### Proposed Model Structure

```
unified_billing_model.php (SINGLE SOURCE OF TRUTH)
├── add_to_billing_queue()      # All services use this
├── generate_invoice()          # Only invoice generator
├── process_payment()           # Only payment processor
├── void_invoice()              # Proper void workflow
├── issue_credit_note()         # Handle overpayments
├── get_invoice_balance()       # Single balance calculation
├── record_to_ledger()          # Double-entry accounting
└── reconcile_daily()           # End of day reconciliation
```

---

## 10. Production Readiness Assessment

| Dimension | Current Score | Target Score | Gap |
|-----------|---------------|--------------|-----|
| **Financial Integrity** | 4/10 | 9/10 | Critical |
| **Data Consistency** | 5/10 | 9/10 | High |
| **Audit Compliance** | 3/10 | 9/10 | Critical |
| **Workflow Efficiency** | 5/10 | 8/10 | Medium |
| **Code Maintainability** | 4/10 | 8/10 | High |
| **Performance** | 6/10 | 8/10 | Medium |

### Overall Production Readiness: **4.5/10** (NOT RECOMMENDED)

---

## Summary

The HMS billing system requires significant architectural consolidation before it can be considered production-ready for financial operations. The primary concerns are:

1. **Multiple entry points** for invoices and payments create data inconsistency
2. **No single source of truth** for financial calculations
3. **Missing accounting controls** for proper financial management
4. **Fragmented billing queues** across modules
5. **Duplicate functionality** increases maintenance burden and bug risk

### Immediate Actions Required

1. **Standardize receipt fields** - Ensure `total_amount` = `amountPaid` in all records
2. **Route all payments** through `unified_billing_model.process_payment()`
3. **Route all invoices** through `unified_billing_model.generate_invoice()`
4. **Consolidate billing queues** into single `billing_queue` table
5. **Implement proper void/credit note workflow** for corrections

---

## Appendix: File References

### Controllers
- `application/controllers/app/pos.php`
- `application/controllers/app/billing.php`
- `application/controllers/app/cashier.php`
- `application/controllers/app/billing_history.php`
- `application/controllers/app/service_queue.php`

### Models
- `application/models/app/billing_model.php`
- `application/models/app/unified_billing_model.php`
- `application/models/app/smart_billing_model.php`
- `application/models/app/service_billing_model.php`
- `application/models/app/cashier_model.php`

### Views
- `application/views/app/pos/`
- `application/views/app/billing/`
- `application/views/app/cashier/`

### Database Tables
- `iop_billing` - Invoice headers
- `iop_billing_t` - Invoice line items
- `iop_receipt` - Payment records
- `billing_queue` - Pending billable items
- `financial_ledger` - Double-entry accounting
- `chart_of_accounts` - Account definitions
