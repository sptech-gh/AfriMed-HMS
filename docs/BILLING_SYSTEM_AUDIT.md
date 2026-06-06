# HMS Billing System Audit Report

## Date: April 2026
## Last Updated: ALL PHASES COMPLETE

### Implementation Status
| Phase | Description | Status |
|-------|-------------|--------|
| 1 | Audit billing entry points | ✅ COMPLETE |
| 2 | Identify duplicate functions | ✅ COMPLETE |
| 3 | Design unified architecture | ✅ COMPLETE |
| 4 | Create single source of truth tables | ✅ COMPLETE |
| 5-6 | Implement accounting structure | ✅ COMPLETE |
| 7-8 | Simplify cashier dashboard | ✅ COMPLETE |
| 9-11 | Unify payment logic | ✅ COMPLETE |
| 12-15 | Service-based billing model | ✅ COMPLETE |
| 16-20 | UI modernization & audit logs | ✅ COMPLETE |

---

## Phase 1: Billing Entry Points Identified

### Controllers (Billing-Related)
| Controller | Purpose | Status |
|------------|---------|--------|
| `billing.php` | OPD/IPD billing, rate lookups | ACTIVE - Primary |
| `pos.php` | Point of Sale billing | ACTIVE - Primary |
| `cashier.php` | Payment collection | ACTIVE - Primary |
| `billing_history.php` | Invoice history | ACTIVE |
| `billing_reconciliation.php` | Financial reconciliation | ACTIVE |
| `service_queue.php` | Service order queue | ACTIVE |
| `receipt.php` | Receipt printing | ACTIVE |
| `laboratory.php` | Lab billing integration | ACTIVE |
| `pharmacy.php` | Pharmacy billing integration | ACTIVE |
| `sonography.php` | Sonography billing | ACTIVE |

### Models (Billing-Related)
| Model | Purpose | Status |
|-------|---------|--------|
| `billing_model.php` | Core billing logic (~3800 lines) | PRIMARY |
| `cashier_model.php` | Payment processing (~700 lines) | PRIMARY |
| `smart_billing_model.php` | Visit fees, registration | SECONDARY |
| `service_billing_model.php` | Service order billing | SECONDARY |
| `billing_transaction_model.php` | Transaction logging | SECONDARY |
| `bill_history_model.php` | Invoice history queries | SECONDARY |

---

## Phase 2: Duplicate Functions Identified

### Invoice Generation (DUPLICATES FOUND)
1. **billing_model.php::saveHeader()** - Main invoice creation
2. **pos.php::save_payment()** - Creates receipts, updates billing
3. **cashier_model.php::process_payment()** - Payment processing
4. **service_billing_model.php::create_service_order()** - Service orders

### Payment Processing (DUPLICATES FOUND)
1. **pos.php::save_payment()** - POS payment
2. **pos.php::record_partial_payment()** - Partial payments
3. **cashier_model.php::process_payment()** - Cashier payment
4. **billing_model.php::record_payment()** - Generic payment

### Receipt Generation (DUPLICATES FOUND)
1. **billing_model.php::receipt_no()** - Receipt number generation
2. **cashier_model.php::generate_receipt_no()** - Different format

---

## Phase 3: Current Database Tables

### Core Billing Tables
| Table | Purpose | Records |
|-------|---------|---------|
| `iop_billing` | Invoice headers | PRIMARY |
| `iop_billing_t` | Invoice line items | PRIMARY |
| `iop_receipt` | Payment receipts | PRIMARY |

### Supporting Tables
| Table | Purpose |
|-------|---------|
| `iop_billable_item_lock` | Prevents duplicate billing |
| `iop_billing_line_meta` | Line item metadata |
| `cashier_payment_log` | Payment audit trail |
| `financial_audit_log` | Financial audit |
| `smart_billing_ledger` | Visit fee tracking |
| `smart_billing_config` | Fee configuration |
| `service_orders` | Service order queue |

---

## Phase 4: Issues Identified

### Critical Issues
1. **Multiple invoice generation paths** - billing.php, pos.php, cashier.php
2. **Inconsistent receipt numbering** - Two different formats
3. **Duplicate payment logic** - 4+ places process payments
4. **No unified billing queue** - Services billed directly
5. **Missing financial ledger** - No double-entry accounting

### Data Integrity Issues
1. **Duplicate invoices possible** - Same visit can be billed multiple times
2. **Payment reconciliation gaps** - iop_receipt vs cashier_payment_log
3. **Missing audit trail** - Not all actions logged

---

## Phase 5: Recommended Architecture

### Single Source of Truth
```
┌─────────────────────────────────────────────────────────────┐
│                    UNIFIED BILLING ENGINE                    │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  Service Request → Billing Queue → Invoice → Payment → Receipt
│                                                             │
│  Tables:                                                    │
│  - billing_queue (pending items)                            │
│  - invoices (unified invoice header)                        │
│  - invoice_items (line items)                               │
│  - payments (all payments)                                  │
│  - receipts (issued receipts)                               │
│  - financial_ledger (double-entry)                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Billing Status Flow
```
PENDING → BILLED → PARTIAL → PAID → CLOSED
                     ↓
                  CANCELLED
                     ↓
                  REFUNDED
```

---

## Phase 6: Implementation Plan

### Step 1: Create Unified Billing Tables (Non-Breaking)
- Create new tables alongside existing ones
- Add migration functions

### Step 2: Create Unified Billing Model
- Single `unified_billing_model.php`
- Consolidate all billing logic

### Step 3: Create Unified Payment Processing
- Single payment entry point
- All payments through one function

### Step 4: Deprecate Duplicate Functions
- Mark old functions as deprecated
- Redirect to unified functions

### Step 5: Update Controllers
- Point all controllers to unified model

### Step 6: Add Financial Ledger
- Double-entry accounting
- Chart of accounts

---

## Files to Modify

### High Priority
1. `billing_model.php` - Add unified functions
2. `cashier_model.php` - Consolidate with billing
3. `pos.php` - Use unified billing
4. `cashier.php` - Use unified billing

### Medium Priority
5. `billing.php` - Standardize
6. `service_queue.php` - Integrate
7. `dashboard_model.php` - Fix calculations

### Low Priority
8. Views - UI improvements
9. Reports - Use unified data

---

## Backward Compatibility

### Preserved
- All existing `iop_billing` records
- All existing `iop_receipt` records
- All existing `iop_billing_t` records

### Migration Strategy
- New tables created alongside old
- Gradual migration of logic
- No data loss

---

## Implementation Details (Completed)

### New Files Created
| File | Purpose |
|------|---------|
| `unified_billing_model.php` | Single source of truth for all billing operations |
| `cashier/reconciliation.php` | Daily reconciliation view |
| `cashier/billing_queue.php` | Pending billable items view |
| `cashier/ledger.php` | Financial ledger view (admin only) |

### New Database Tables (Auto-Created)
| Table | Purpose |
|-------|---------|
| `billing_queue` | Pending billable items queue |
| `chart_of_accounts` | Standard accounting chart |
| `financial_ledger` | Double-entry ledger entries |
| `iop_billable_item_lock` | Prevents duplicate billing |

### Unified Billing Model Functions
- `add_to_billing_queue()` - Queue items for billing
- `generate_invoice()` - Single invoice generation
- `process_payment()` - Single payment processing
- `queue_lab_test()` - Auto-queue lab tests
- `queue_medication()` - Auto-queue medications
- `queue_sonography()` - Auto-queue imaging
- `queue_procedure()` - Auto-queue procedures
- `is_item_billed()` - Duplicate prevention
- `lock_billed_item()` - Lock billed items

### Controllers Updated
- `cashier.php` - Loads unified_billing_model, new endpoints
- `pos.php` - Loads unified_billing_model
- `billing.php` - Loads unified_billing_model

### Sidebar Updates
- Added "Billing Queue" link
- Added "Daily Reconciliation" link
- Added "Financial Ledger" link (admin only)

