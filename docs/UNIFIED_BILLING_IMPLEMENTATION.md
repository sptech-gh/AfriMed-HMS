# Unified Billing Architecture - Implementation Guide

## Overview

This document describes the unified billing architecture implementation for SBMC Hospital Management System. The implementation follows the audit recommendations to create a single source of truth for all billing operations.

## Architecture Components

### 1. Unified Billing Model (`unified_billing_model.php`)
**Location:** `application/models/app/unified_billing_model.php`

**Core Tables:**
- `billing_queue` - Central queue for all billable items
- `chart_of_accounts` - Standard accounting structure
- `financial_ledger` - Double-entry accounting records

**Key Methods:**
- `ensure_unified_billing_schema()` - Auto-migrates all required tables
- `add_to_billing_queue()` - Add items to billing queue (single entry point)
- `generate_invoice()` - Create invoice from queue items (single entry point)
- `process_payment()` - Process payments with double-entry (single entry point)
- `check_service_gate()` - Check if service is released
- `release_service_gate()` - Release service after payment
- `finalize_invoice()` - Make invoice immutable

### 2. Service Gate Model (`service_gate_model.php`)
**Location:** `application/models/app/service_gate_model.php`

**Purpose:** Enforce payment-before-service rules across all clinical modules

**Integration Points:**
- Laboratory - check before processing lab requests
- Pharmacy - check before dispensing medications
- Sonography - check before imaging procedures
- Radiology - check before X-ray/CT scans

**Key Methods:**
- `check_service()` - Primary gate check with full result details
- `can_proceed()` - Quick boolean check
- `create_exception()` - Emergency/authorized bypass
- `check_lab_gate()` - Convenience method for lab module
- `check_pharmacy_gate()` - Convenience method for pharmacy module
- `check_sonography_gate()` - Convenience method for sonography module

### 3. Service Gate Helper (`service_gate_helper.php`)
**Location:** `application/helpers/service_gate_helper.php`

**Convenience Functions:**
- `check_service_gate()` - Check gate and auto-redirect if blocked
- `can_proceed_service()` - Quick boolean check
- `check_lab_gate()` - Lab module convenience function
- `check_pharmacy_gate()` - Pharmacy module convenience function
- `check_sonography_gate()` - Sonography module convenience function
- `service_gate_badge()` - Get HTML status badge
- `service_gate_action_button()` - Get payment action button

## Schema Changes

### New Tables

#### billing_queue
All billable items flow through this table before invoicing.

```sql
CREATE TABLE billing_queue (
    queue_id INT AUTO_INCREMENT PRIMARY KEY,
    iop_id VARCHAR(25) NOT NULL,
    patient_no VARCHAR(25) NOT NULL,
    item_type ENUM('CONSULTATION','REGISTRATION','LAB','PHARMACY','SONOGRAPHY','RADIOLOGY','PROCEDURE','ADMISSION','SURGERY','ROOM','SUPPLY','OTHER'),
    item_id VARCHAR(50) NOT NULL,
    item_name VARCHAR(255) NOT NULL,
    -- Service Gate Fields
    service_gate_status ENUM('BLOCKED','RELEASED','EXPIRED') DEFAULT 'BLOCKED',
    released_at DATETIME,
    released_by VARCHAR(25),
    -- Financial Fields
    quantity DECIMAL(10,2) DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(12,2) DEFAULT 0.00,
    discount_amount DECIMAL(12,2) DEFAULT 0.00,
    net_amount DECIMAL(12,2) DEFAULT 0.00,
    payer_type ENUM('CASH','NHIS','INSURANCE','COMPANY') DEFAULT 'CASH',
    coverage_amount DECIMAL(12,2) DEFAULT 0.00,
    patient_amount DECIMAL(12,2) DEFAULT 0.00,
    status ENUM('PENDING','BILLED','CANCELLED') DEFAULT 'PENDING',
    invoice_no VARCHAR(50),
    source_module VARCHAR(50),
    source_ref VARCHAR(100),
    requested_by VARCHAR(25),
    requested_at DATETIME,
    billed_by VARCHAR(25),
    billed_at DATETIME,
    notes TEXT,
    InActive TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_iop (iop_id),
    INDEX idx_patient (patient_no),
    INDEX idx_status (status),
    INDEX idx_invoice (invoice_no),
    UNIQUE KEY uq_source (source_module, source_ref)
);
```

#### chart_of_accounts
Standard accounting chart of accounts.

```sql
CREATE TABLE chart_of_accounts (
    account_id INT AUTO_INCREMENT PRIMARY KEY,
    account_code VARCHAR(20) NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type ENUM('ASSET','LIABILITY','EQUITY','REVENUE','EXPENSE'),
    parent_id INT,
    description VARCHAR(255),
    is_system TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_code (account_code)
);
```

#### financial_ledger
Double-entry accounting ledger.

```sql
CREATE TABLE financial_ledger (
    ledger_id BIGINT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) NOT NULL,
    transaction_date DATE NOT NULL,
    account_id INT NOT NULL,
    debit_amount DECIMAL(12,2) DEFAULT 0.00,
    credit_amount DECIMAL(12,2) DEFAULT 0.00,
    reference_type VARCHAR(50),
    reference_no VARCHAR(50),
    patient_no VARCHAR(25),
    description VARCHAR(255),
    created_by VARCHAR(25),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_txn (transaction_id),
    INDEX idx_date (transaction_date),
    INDEX idx_account (account_id),
    INDEX idx_ref (reference_type, reference_no)
);
```

#### service_gate_exceptions
Authorized bypasses for payment gate.

```sql
CREATE TABLE service_gate_exceptions (
    exception_id INT AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(50) NOT NULL,
    source_ref VARCHAR(50) NOT NULL,
    patient_no VARCHAR(25),
    iop_id VARCHAR(25),
    exception_type ENUM('EMERGENCY','WAIVER','NHIS','INSURANCE','STAFF','DEFERRED'),
    reason TEXT NOT NULL,
    authorized_by VARCHAR(25) NOT NULL,
    status ENUM('ACTIVE','EXPIRED','REVOKED') DEFAULT 'ACTIVE',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    INDEX idx_module_ref (module, source_ref),
    INDEX idx_patient (patient_no),
    INDEX idx_status (status)
);
```

### Enhanced Tables

#### iop_billing (New Fields)
```sql
ALTER TABLE iop_billing ADD COLUMN payment_status VARCHAR(20) DEFAULT 'PENDING';
ALTER TABLE iop_billing ADD COLUMN balance_due DECIMAL(12,2) DEFAULT 0.00;
ALTER TABLE iop_billing ADD COLUMN payer_type VARCHAR(20) DEFAULT 'CASH';
-- Invoice immutability fields
ALTER TABLE iop_billing ADD COLUMN is_locked TINYINT(1) DEFAULT 0;
ALTER TABLE iop_billing ADD COLUMN locked_at DATETIME;
ALTER TABLE iop_billing ADD COLUMN finalized_at DATETIME;
ALTER TABLE iop_billing ADD COLUMN invoice_status VARCHAR(20) DEFAULT 'DRAFT';
```

#### iop_receipt (New Field)
```sql
ALTER TABLE iop_receipt ADD COLUMN cashier_id VARCHAR(25);
```

## Implementation Workflow

### Phase 1: Foundation (COMPLETED)
1. ✅ Unified billing schema (billing_queue, chart_of_accounts, financial_ledger)
2. ✅ Service Gate Model with payment-before-service enforcement
3. ✅ Invoice immutability fields (is_locked, locked_at, finalized_at)
4. ✅ Helper functions for easy controller integration

### Phase 2: Central Billing Engine (COMPLETED)
1. ✅ Single entry point for invoice generation (`generate_invoice()`)
2. ✅ Single entry point for payment processing (`process_payment()`)
3. ✅ Double-entry accounting integration
4. ✅ Auto-release of service gates after payment

### Phase 3: Module Integration (IN PROGRESS)

#### Laboratory Module
**Files to update:**
- `controllers/app/laboratory.php` - Add gate check before processing
- `views/app/laboratory/request.php` - Show payment status badges

**Changes:**
```php
// In laboratory.php - before processing request
if (!check_lab_gate($lab_id, $iop_id)) {
    return; // Already redirected to payment page
}
```

#### Pharmacy Module
**Files to update:**
- `controllers/app/pharmacy.php` - Add gate check before dispensing
- `views/app/pharmacy/worklist.php` - Show payment status badges

**Changes:**
```php
// In pharmacy.php - before dispensing medication
if (!check_pharmacy_gate($med_id, $iop_id, $patient_no)) {
    return; // Already redirected to payment page
}
```

#### Sonography Module
**Files to update:**
- `controllers/app/sonography.php` - Add gate check
- `views/app/sonography/request.php` - Show payment status

### Phase 4: Legacy Migration

#### Data Migration
1. Migrate existing billing data to unified structure
2. Backfill billing_queue from existing lab/pharmacy requests
3. Verify data integrity after migration

#### Deprecation Plan
1. Mark legacy billing methods as deprecated
2. Add warnings to old billing functions
3. Document migration path for custom code

## Usage Examples

### Adding Item to Billing Queue
```php
$this->load->model('app/unified_billing_model', 'billing');

$result = $this->billing->add_to_billing_queue(array(
    'iop_id' => 'OP 000002',
    'patient_no' => '000046',
    'item_type' => 'LAB',
    'item_id' => $lab_request_id,
    'item_name' => 'Complete Blood Count',
    'unit_price' => 45.00,
    'payer_type' => 'CASH',
    'source_module' => 'LAB',
    'source_ref' => $lab_request_id,
    'requested_by' => $doctor_id
));

if ($result['success']) {
    // Item queued successfully
    $queue_id = $result['queue_id'];
}
```

### Checking Service Gate (Manual)
```php
$this->load->model('app/service_gate_model', 'gate');

$result = $this->gate->check_lab_gate($lab_id, $iop_id);

if (!$result['allowed']) {
    // Show payment required message
    echo $result['reason']; // "Payment pending - balance due: GHS 45.00"
    echo $result['action_required']; // "Collect payment before proceeding"
}
```

### Checking Service Gate (Auto-redirect)
```php
// Using helper function - auto-redirects if blocked
if (!check_lab_gate($lab_id, $iop_id)) {
    // Function already handled redirect
    return;
}

// Service is released, proceed with processing
```

### Creating Emergency Exception
```php
$result = create_gate_exception(
    'LAB',
    $lab_id,
    'EMERGENCY',
    'Critical patient - blood transfusion needed immediately',
    $patient_no,
    $iop_id
);

if ($result['success']) {
    // Service gate bypassed via emergency exception
    $exception_id = $result['exception_id'];
}
```

## Testing

### Unit Tests
Test cases for each model method should be created in:
- `tests/models/Unified_billing_model_test.php`
- `tests/models/Service_gate_model_test.php`

### Integration Tests
Test end-to-end workflows:
1. Create OPD visit → Add lab request → Check billing queue → Invoice → Pay → Service release
2. Create prescription → Check pharmacy gate → Pay → Dispense
3. Emergency exception flow

### Validation Checklist
- [ ] All schema migrations run successfully
- [ ] Billing queue captures items from all modules
- [ ] Service gates block unpaid services
- [ ] Service gates release after payment
- [ ] Double-entry accounting records correct entries
- [ ] Invoice immutability prevents modifications
- [ ] Emergency exceptions work for authorized users
- [ ] Legacy billing continues to work during transition

## Rollback Plan

If issues are encountered:
1. Disable service gate enforcement in Service_gate_model
2. Revert to legacy billing methods
3. Fix issues and redeploy

## Support

For questions or issues:
1. Check this documentation first
2. Review code comments in model files
3. Check audit log in `unified_billing_audit_log` table
4. Contact HMS Development Team

---

## Implementation Summary

### Files Created
1. `application/models/app/service_gate_model.php` - Service gate enforcement model
2. `application/helpers/service_gate_helper.php` - Helper functions for easy controller integration
3. `docs/UNIFIED_BILLING_IMPLEMENTATION.md` - This documentation

### Files Modified
1. `application/models/app/unified_billing_model.php` - Added service gate methods, invoice immutability
2. `application/models/app/billing_model.php` - Integrated billing queue in `create_service_order_for_request()`
3. `application/controllers/General.php` - Auto-loads unified billing models/helpers
4. `application/controllers/app/laboratory.php` - Uses unified service gate for payment verification
5. `application/controllers/app/pharmacy.php` - Uses unified service gate for dispense verification
6. `application/controllers/app/opd.php` - Adds medications to unified billing queue when prescribed

### Workflow Summary

```
1. Doctor orders Lab/Pharmacy → Item added to billing_queue (BLOCKED status)
2. Cashier invoices → Item status: BILLED
3. Patient pays → Service gate auto-released (RELEASED status)
4. Lab/Pharmacy processes → Gate check passes
```

### Backward Compatibility
- Unified gate works alongside existing GHS and NHIS payment gates
- Services allowed if ANY gate approves (unified OR legacy)
- No breaking changes to existing workflows

---

**Version:** 1.0  
**Last Updated:** April 2026  
**Status:** ✅ Phase 1, 2, 3 Complete - Unified Billing Architecture Implemented
