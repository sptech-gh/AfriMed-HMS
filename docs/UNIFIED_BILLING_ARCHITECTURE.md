# Unified Billing Architecture - Single Source of Truth

## Executive Summary

The HMS now implements a **Single Billing Architecture** that consolidates 7 legacy billing systems into one unified platform. This eliminates duplicate dashboards, conflicting payment states, and revenue reporting inaccuracies.

---

## Architecture Overview

### BEFORE: 7 Conflicting Billing Systems

| System | Status | Tables Used | Controller |
|--------|--------|-------------|------------|
| Cashier Dashboard | DEPRECATED | `iop_billing`, `iop_receipt` | `cashier.php` |
| Billing & Finance (ebilling) | DEPRECATED | `eb_transactions` | `ebilling.php` |
| Smart Billing | DEPRECATED | `smart_billing_ledger` | `billing.php` |
| Unified Billing | DEPRECATED | `billing_queue` | `unified_billing_model` |
| Billing Engine | DEPRECATED | `eb_transactions` | `billing_engine_model` |
| Enterprise Billing | DEPRECATED | `eb_transactions`, `eb_invoices` | `enterprise_billing_model` |
| Billing History | DEPRECATED | `iop_billing` | `billing_history.php` |

### AFTER: Single Unified Billing System

| System | Status | Tables Used | Controller |
|--------|--------|-------------|------------|
| **Unified Billing Dashboard** | ACTIVE | `billing_master`, `billing_items`, `billing_payments` | `Unified_billing.php` |

---

## Unified Database Schema

### Core Tables (Single Source of Truth)

#### 1. billing_master (Invoice Headers)
```sql
- bill_id (PK)
- bill_no (Unique) - Format: BILL-YYYYMMDD-NNNN
- patient_no (FK → patients)
- visit_id (FK → visits)
- visit_type (OPD/IPD/EMERGENCY/PHARMACY)
- total_amount, discount_amount, tax_amount
- net_amount, paid_amount, balance_due
- payment_status (Standardized enum)
- payer_type (CASH/NHIS/INSURANCE/COMPANY/STAFF)
- created_by, created_at, billed_by, billed_at
```

#### 2. billing_items (Line Items)
```sql
- item_id (PK)
- bill_id (FK → billing_master)
- service_type (REGISTRATION/CONSULTATION/LABORATORY/RADIOLOGY/SONOGRAPHY/PHARMACY/PROCEDURE)
- service_id, service_code, service_name
- department, requested_by, requested_at
- quantity, unit_price, gross_amount, discount_amount, net_amount
- gate_status (BLOCKED/RELEASED/EXPIRED) - Service access control
- source_ref_id, source_ref_table - Audit trail
```

#### 3. billing_payments (Unified Payments)
```sql
- payment_id (PK)
- bill_id (FK → billing_master)
- payment_no (Unique) - Format: RCP-YYYYMMDD-NNNN
- amount, payment_method, reference_no, allocated_amount
- collected_by, collected_at
- is_reconciled, reconciled_at, reconciled_by
```

#### 4. billing_audit_log (Complete Audit Trail)
```sql
- log_id (PK)
- bill_id, item_id, payment_id
- action (CREATE/UPDATE/DELETE/PAYMENT/REFUND/DISCOUNT/GATE_RELEASE/CANCEL/RESTORE)
- description, old_values (JSON), new_values (JSON)
- performed_by, performed_at, ip_address
```

---

## Unified Workflow

```
┌─────────────────────────────────────────────────────────────────┐
│                        SERVICE REQUEST FLOW                       │
└─────────────────────────────────────────────────────────────────┘

  DOCTOR ORDERS SERVICE
           ↓
    ┌──────────────┐
    │ Service Saved │  → Lab/Rad/Sono/Pharm table
    │ to Module DB  │
    └──────────────┘
           ↓
  BILLING MASTER MODEL
  Creates Bill Item with
  gate_status = 'BLOCKED'
           ↓
    ┌──────────────┐
    │ Bill Added   │
    │ to Patient's │  → billing_items
    │ Bill         │
    └──────────────┘
           ↓
  ┌───────────────────────────────────┐
  │ DEPARTMENT CAN SEE REQUEST         │
  │ BUT CANNOT PROCESS (BLOCKED GATE)   │
  └───────────────────────────────────┘
           ↓
  ┌───────────────────────────────────┐
  │ CASHIER OPENS UNIFIED DASHBOARD    │
  │ Sees Pending Bill                  │
  └───────────────────────────────────┘
           ↓
  PATIENT MAKES PAYMENT
           ↓
    ┌──────────────┐
    │ Payment      │
    │ Recorded     │  → billing_payments
    └──────────────┘
           ↓
  Gate Status CHANGED to 'RELEASED'
           ↓
  ┌───────────────────────────────────┐
  │ DEPARTMENT CAN NOW PROCESS         │
  │ Lab runs test / Rad takes X-ray    │
  └───────────────────────────────────┘
```

---

## Role-Based Access Control

| Role | Permissions |
|------|-------------|
| **Doctor** | Add service requests, View billing status |
| **Cashier** | Record payments, View all bills, Print receipts |
| **Admin/Supervisor** | Apply discounts, Override bills, Issue refunds, Cancel bills |
| **Department Staff** | View requests (BLOCKED), Process requests (RELEASED only) |

---

## Payment Status Standardization

### Standard Statuses (All Systems)
```
PENDING         → Bill created, no payment
PARTIAL         → Some payment received
PAID            → Full payment received
OVERPAID        → Excess payment (adds to credit)
CANCELLED       → Bill voided (admin only)
REFUNDED        → Full refund issued
PARTIAL_REFUND  → Partial refund issued
CREDIT          → Added to patient credit balance
INSURANCE_PENDING → Awaiting insurance approval
NHIS_PENDING    → Awaiting NHIS approval
```

### Deprecated Statuses (Removed)
```
✗ UNPAID (replaced with PENDING)
✗ PAID (Admin Only) - confusing duplicate
✗ PAID (Cashier Only) - confusing duplicate
✗ Approved (ambiguous)
✗ Cleared (ambiguous)
```

---

## Service Gate System

### Purpose
Controls when departments can process services based on payment status.

### Gate States
```
BLOCKED  → Service ordered but not paid. Department can SEE request but CANNOT process.
RELEASED → Payment received. Department CAN process service.
EXPIRED  → Request too old or cancelled.
```

### Auto-Release Rules
```
1. Full payment recorded → All BLOCKED items → RELEASED
2. NHIS patient → Services auto-released (government billing)
3. Emergency → Services auto-released (life-threatening)
4. Admin override → Manual release
```

---

## URLs and Navigation

### New Unified Billing URLs
```
Dashboard:      /app/unified_billing
                /app/billing (alias)

Today's Bills:  /app/unified_billing/today
Pending Bills:  /app/unified_billing/pending
Search:         /app/unified_billing/search
Bill Detail:    /app/unified_billing/view_bill/{id}
Patient Bills:  /app/unified_billing/patient_bills/{patient_no}
Print Invoice:  /app/unified_billing/print_bill/{bill_id}
Print Receipt:  /app/unified_billing/print_receipt/{payment_id}

AJAX Endpoints:
Record Payment: /app/unified_billing/record_payment (POST)
Apply Discount: /app/unified_billing/apply_discount (POST)
Get Balance:    /app/unified_billing/get_patient_balance/{patient_no}
```

### Legacy URLs (Redirected)
```
/app/cashier/*      → /app/unified_billing/*
/app/ebilling/*     → /app/unified_billing/*
/app/smart_billing  → /app/unified_billing
/app/billing_engine → /app/unified_billing
```

---

## Migration Strategy

### Phase 1: Deploy New Tables ✅ COMPLETE
- [x] Create billing_master
- [x] Create billing_items  
- [x] Create billing_payments
- [x] Create billing_audit_log

### Phase 2: Dual Run Period ⏳ NEXT
- [ ] Enable new billing alongside legacy
- [ ] Sync data from legacy to new tables
- [ ] Validate reports match

### Phase 3: Cutover ⏳
- [ ] Update all department workflows
- [ ] Redirect all legacy URLs
- [ ] Train staff on new dashboard

### Phase 4: Legacy Cleanup ⏳
- [ ] Archive old tables
- [ ] Remove deprecated controllers
- [ ] Update documentation

---

## Implementation Files

### Core Model
```
application/models/app/Billing_master_model.php
  - Single Source of Truth for all billing logic
  - Handles: create_bill(), record_payment(), apply_discount()
  - Manages: service gates, audit logging, status calculations
```

### Controller
```
application/controllers/app/Unified_billing.php
  - Main dashboard and all billing operations
  - Role-based access control
  - AJAX endpoints for payments
```

### Views
```
application/views/app/unified_billing/
  ├── dashboard.php        Main billing dashboard
  ├── bills_list.php       List view (today, pending, all)
  ├── bill_detail.php      Single bill view
  ├── search.php           Advanced search
  ├── receipt_print.php    Receipt template
  └── invoice_print.php    Invoice template
```

### Migration
```
application/migrations/20250615_unified_billing_schema.php
  - Creates all unified billing tables
  - Can be rolled back if needed
```

---

## GHS/NHIS Compliance

### Required Features Implemented
1. ✅ Patient liability tracking (patient_liability field)
2. ✅ Insurance coverage tracking (coverage_amount field)
3. ✅ NHIS-specific status (NHIS_PENDING)
4. ✅ Audit trail for all transactions
5. ✅ Receipt printing with unique numbers
6. ✅ Invoice printing with itemized breakdown

---

## Reporting

### Available Reports (Unified Data)
```
Revenue Summary       → From billing_master daily totals
Department Revenue    → From billing_items by department
Cashier Performance   → From billing_payments by collector
NHIS Claims           → From billing_master.status = 'NHIS_PENDING'
Outstanding Payments  → From billing_master.balance_due > 0
Refund Report         → From billing_master.status = 'REFUNDED'
```

---

## Contact & Support

For issues with the Unified Billing System:
- Check audit log: `billing_audit_log` table
- Verify gate status: `billing_items.gate_status`
- Confirm payment: `billing_payments` table

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 3.0 | 2025-06-15 | Initial unified billing implementation |
