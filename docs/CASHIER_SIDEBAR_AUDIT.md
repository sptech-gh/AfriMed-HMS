# Cashier Sidebar Audit Report

## Date: April 2026
## Status: REFACTORING COMPLETE

---

## Implementation Summary

### BEFORE (15 menu items - confusing, duplicates)
```
POS
Billing List
Search Patient / Bill
Pharmacy Bills
Smart Billing
Service Queue
Payment Collection
Daily Collection
Billing Queue
Daily Reconciliation
Financial Ledger (admin)
Billing Statistics (admin)
Audit Log (admin)
Discrepancies (admin)
Reconciliation (admin)
```

### AFTER (11 menu items - clean, organized)
```
📊 CASHIER
├── [Billing]
│   ├── Billing Queue (unified)
│   ├── Create Bill
│   └── Search Bills
├── [Payments]
│   ├── Collect Payment
│   └── Payment History
├── [Reports]
│   └── Daily Summary
└── [Admin Tools] (admin only)
    ├── Statistics
    ├── Financial Ledger
    ├── Audit Log
    └── Discrepancies
```

### Modules Removed/Merged
| Old Module | Action | New Location |
|------------|--------|--------------|
| POS | REMOVED | Use Create Bill |
| Service Queue | REDIRECTED | Billing Queue |
| Pharmacy Bills | MERGED | Billing Queue (filter) |
| Daily Collection | MERGED | Daily Summary |
| Reconciliation (old) | REDIRECTED | Daily Summary |

---

## Files Modified

### Controllers
| File | Changes |
|------|---------|
| `cashier.php` | Added unified dashboard with consolidated metrics |
| `service_queue.php` | Deprecated - redirects to billing_queue |
| `billing_reconciliation.php` | Deprecated - redirects to cashier/reconciliation |

### Views
| File | Changes |
|------|---------|
| `sidebar.php` | Consolidated menu with section headers |
| `cashier/dashboard.php` | NEW - Unified cashier dashboard |

### Models
| File | Changes |
|------|---------|
| `cashier_model.php` | Added get_today_payments() |
| `unified_billing_model.php` | Added performance indexes |

---

## Accounting Integrity Maintained

### Single Source of Truth
- **Invoices**: `iop_billing` table
- **Line Items**: `iop_billing_t` table
- **Payments**: `iop_receipt` table
- **Audit Trail**: `billing_audit_log` table

### Duplicate Prevention
- `iop_billable_item_lock` prevents double-billing
- Payment balance calculated at transaction time
- Overpayment protection in `process_payment()`

### Financial Ledger
- Double-entry accounting via `financial_ledger`
- Chart of accounts in `chart_of_accounts`
- All payments recorded to ledger

---

## Standard Cashier Workflow

```
1. Service Created (Lab/Pharmacy/Procedure)
         ↓
2. Appears in Billing Queue
         ↓
3. Cashier Creates Bill (Create Bill)
         ↓
4. Invoice Generated
         ↓
5. Payment Collected (Collect Payment)
         ↓
6. Receipt Issued
         ↓
7. Recorded in Financial Ledger
         ↓
8. Visible in Daily Summary
```

---

## Performance Optimizations

### Database Indexes Added
- `idx_billing_date` on `iop_billing.dDate`
- `idx_billing_patient` on `iop_billing.patient_no`
- `idx_billing_status` on `iop_billing.payment_status`
- `idx_receipt_date` on `iop_receipt.dDate`
- `idx_receipt_invoice` on `iop_receipt.invoice_no`
- `idx_billingt_invoice` on `iop_billing_t.invoice_no`

---

## Phase 1: Current Sidebar Items Mapped

### Current Billing Menu Structure

| # | Menu Item | URL | Controller | Module Key | Role |
|---|-----------|-----|------------|------------|------|
| 1 | POS | `/app/pos/` | pos.php | - | cashier |
| 2 | Billing List | `/app/billing_history` | billing_history.php | bill_history_mod | cashier |
| 3 | Search Patient / Bill | `/app/billing/searchPatient` | billing.php | billing_search | cashier |
| 4 | Pharmacy Bills | `/app/billing/pharmacy_bills` | billing.php | pharmacy_bills | cashier |
| 5 | Smart Billing | `/app/billing/smart_billing` | billing.php | smart_billing | cashier |
| 6 | Service Queue | `/app/service_queue` | service_queue.php | service_queue | cashier/admin |
| 7 | Payment Collection | `/app/cashier/payments` | cashier.php | payment_collection | cashier/admin |
| 8 | Daily Collection | `/app/cashier/daily_collection` | cashier.php | daily_collection | cashier/admin |
| 9 | Billing Queue | `/app/cashier/billing_queue` | cashier.php | billing_queue | cashier/admin |
| 10 | Daily Reconciliation | `/app/cashier/reconciliation` | cashier.php | reconciliation | cashier/admin |
| 11 | Financial Ledger | `/app/cashier/ledger` | cashier.php | ledger | admin |
| 12 | Billing Statistics | `/app/cashier/statistics` | cashier.php | statistics | admin |
| 13 | Audit Log | `/app/cashier/audit_log` | cashier.php | audit_log | admin |
| 14 | Discrepancies | `/app/cashier/discrepancies` | cashier.php | discrepancies | admin |
| 15 | Reconciliation | `/app/billing_reconciliation` | billing_reconciliation.php | billing_reconciliation | admin |

---

## Phase 2: Duplicate Functional Analysis

### DUPLICATES IDENTIFIED

#### 1. Invoice Generation (DUPLICATES)
| Module | Function | Creates Invoice? |
|--------|----------|------------------|
| POS | pos.php::save_payment() | YES |
| Smart Billing | billing.php::smart_billing() | YES |
| Billing List | billing_history.php | VIEW ONLY |

**VERDICT**: POS and Smart Billing both create invoices → MERGE

#### 2. Queue Systems (DUPLICATES)
| Module | Function | Shows Queue? |
|--------|----------|--------------|
| Service Queue | service_queue.php | YES - service orders |
| Billing Queue | cashier.php::billing_queue() | YES - pending bills |

**VERDICT**: Both show pending items → MERGE into Unified Queue

#### 3. Payment Collection (DUPLICATES)
| Module | Function | Collects Payment? |
|--------|----------|-------------------|
| POS | pos.php::save_payment() | YES |
| Payment Collection | cashier.php::payments() | YES |

**VERDICT**: Both collect payments → MERGE

#### 4. Reconciliation (DUPLICATES)
| Module | Function | Reconciles? |
|--------|----------|-------------|
| Daily Reconciliation | cashier.php::reconciliation() | YES |
| Reconciliation | billing_reconciliation.php | YES |

**VERDICT**: Both reconcile → MERGE

#### 5. Collection Reports (DUPLICATES)
| Module | Function | Shows Collections? |
|--------|----------|-------------------|
| Daily Collection | cashier.php::daily_collection() | YES |
| Billing Statistics | cashier.php::statistics() | YES |

**VERDICT**: Overlapping data → CONSOLIDATE

---

## Phase 3: Recommended Consolidated Structure

### NEW CASHIER SIDEBAR (Clean Architecture)

```
📊 Cashier Dashboard
├── 📋 Billing Queue (Unified)
│   └── Shows: Lab, Pharmacy, Procedures, Consultations pending billing
├── 💳 Create Bill / POS
│   └── Single unified billing interface
├── 🔍 Search Bills
│   └── Search invoices and patients
├── 💰 Collect Payment
│   └── Single payment collection interface
├── 📜 Payment History
│   └── View all payments made
├── 📊 Daily Summary
│   └── Today's collections, pending, paid
└── ⚙️ Admin Tools (admin only)
    ├── 📈 Statistics
    ├── 🔄 Reconciliation
    ├── 📖 Financial Ledger
    ├── 📝 Audit Log
    └── ⚠️ Discrepancies
```

---

## Phase 4: Modules to REMOVE

| Module | Reason | Action |
|--------|--------|--------|
| POS (separate) | Duplicate of Smart Billing | MERGE into Unified Billing |
| Service Queue | Duplicate of Billing Queue | MERGE into Billing Queue |
| Payment Collection | Duplicate of POS payment | MERGE into Collect Payment |
| Daily Collection | Overlaps with Statistics | MERGE into Daily Summary |
| Reconciliation (old) | Duplicate | REMOVE, keep Daily Reconciliation |
| Pharmacy Bills | Can be filtered in Billing Queue | MERGE into Billing Queue |

---

## Phase 5: Modules to KEEP

| Module | New Name | Purpose |
|--------|----------|---------|
| Billing Queue | Billing Queue | Unified pending items |
| Smart Billing | Create Bill | Single billing interface |
| Search Patient/Bill | Search Bills | Invoice lookup |
| cashier/payments | Collect Payment | Payment processing |
| Billing List | Payment History | Invoice/receipt history |
| Daily Reconciliation | Daily Summary | Combined summary |
| Statistics | Statistics | Admin analytics |
| Ledger | Financial Ledger | Admin ledger |
| Audit Log | Audit Log | Admin audit |
| Discrepancies | Discrepancies | Admin issues |

---

## Phase 6: Implementation Plan

### Step 1: Consolidate Sidebar Menu
- Remove duplicate menu items
- Rename for clarity
- Group logically

### Step 2: Redirect Deprecated URLs
- `/app/pos/` → `/app/billing/smart_billing`
- `/app/service_queue` → `/app/cashier/billing_queue`
- `/app/billing_reconciliation` → `/app/cashier/reconciliation`

### Step 3: Update Controllers
- Mark deprecated functions
- Add redirects for backward compatibility

### Step 4: Clean UI
- Modern icons
- Clear grouping
- Badge counts for pending items

