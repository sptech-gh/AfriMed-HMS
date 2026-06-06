# Laboratory Module Payment Gate Audit

## Date: April 2026

## Issue Identified
Lab staff were able to enter and save lab results even when payment status showed "PENDING PAYMENT". This was a critical security/billing compliance issue.

---

## Root Causes Found

### 1. View Not Blocking Save Button
**File:** `application/views/app/laboratory/results.php`

The Save button was only hidden based on `$isReadOnly` (user permissions), NOT based on payment status. The payment status badge was displayed but was purely informational.

### 2. Overly Permissive Payment Status Fallbacks
**File:** `application/models/app/laboratory_model.php` - `get_lab_payment_status()`

The method had fallback cases that incorrectly returned `paid = true`:
- If `iop_lab_billing` record existed with unknown status → allowed
- If no `iop_lab_billing` record → allowed as "Legacy"
- If `iop_lab_billing` table doesn't exist → allowed

### 3. Missing Payment Gate in Structured Results Endpoint
**File:** `application/controllers/app/medical_data.php` - `save_structured_result()`

The AJAX endpoint for saving structured lab results had NO payment verification at all, allowing bypass of the payment gate.

---

## Fixes Applied

### Fix 1: View Payment Block (results.php)
```php
$paymentBlocked = isset($payment_blocked) && $payment_blocked;

// Alert banner when blocked
<?php if ($paymentBlocked) { ?>
<div class="alert alert-danger">
    <i class="fa fa-ban fa-lg"></i> <strong>PAYMENT REQUIRED:</strong> 
    Lab results cannot be saved until payment is verified.
</div>
<?php } ?>

// Save button now checks payment status
<?php if (!$isReadOnly && !$paymentBlocked) { ?>
    <button class="btn btn-primary">Save</button>
<?php } else if (!$isReadOnly && $paymentBlocked) { ?>
    <span class="btn btn-danger disabled">Payment Required - Cannot Save</span>
<?php } ?>
```

### Fix 2: Controller Payment Block Flag (laboratory.php)
```php
// Pass payment block flag to view
$this->data['payment_blocked'] = !$canProceed && $this->user_can_write_lab_row($row);
```

### Fix 3: Strict Payment Status (laboratory_model.php)
Changed fallback behavior:
- Unknown status → `paid = false`, label "Pending Payment"
- No billing record → `paid = false`, label "Not Billed"
- No billing table → Falls through to legacy invoice check

### Fix 4: Payment Gate on Structured Results (medical_data.php)
```php
// PAYMENT GATE: Block structured result save if payment not verified
$this->load->model('app/laboratory_model');
$this->load->model('app/service_gate_model', 'service_gate');
$gate = $this->service_gate->check_lab_gate($io_lab_id, $iop_id);
$payStatus = $this->laboratory_model->get_lab_payment_status($io_lab_id);
$isAdmin = has_role('admin');
$canProceed = $gate['allowed'] || $payStatus['paid'] || $isAdmin;

if (!$canProceed) {
    $this->_json(array('success' => false, 'message' => 'Payment Required'));
    return;
}
```

---

## Complete Entry Point Audit

| Entry Point | File | Method | Payment Gate |
|------------|------|--------|--------------|
| View Results | laboratory.php | results() | ✅ Blocks view with redirect OR payment_blocked flag |
| Save Text Result | laboratory.php | edit_save() | ✅ Checks gate + payStatus, redirects if blocked |
| View Upload Page | laboratory.php | upload_results() | ✅ Redirects to payment pending view |
| Upload PDF Result | laboratory.php | upload_lab_result() | ✅ Checks payStatus, redirects if not paid |
| Save Structured | medical_data.php | save_structured_result() | ✅ FIXED - Now checks gate + payStatus |

---

## Admin Override

Admins can bypass payment gate in all cases. The check is:
```php
$canProceed = $gate['allowed'] || $payStatus['paid'] || $this->current_user_is_admin();
```

---

## Payment Status Logic

### Paid Status (allows processing):
- `iop_lab_billing.payment_status = 'PAID'`
- `iop_lab_billing.billing_generated = 0` (No billing required)
- NHIS patient (payer_type = 'NHIS')
- Insurance patient (payment_type = 'INSURANCE COMPANY')
- Legacy invoice fully paid (total_paid >= total_amount)

### Blocked Status:
- `iop_lab_billing.payment_status = 'PENDING'`
- Any other status (BILLED, etc)
- No billing record
- Partial payment

---

## Files Modified

1. `application/controllers/app/laboratory.php` - Added payment_blocked flag
2. `application/models/app/laboratory_model.php` - Fixed get_lab_payment_status() fallbacks
3. `application/views/app/laboratory/results.php` - Added payment blocked UI + disabled Save
4. `application/controllers/app/medical_data.php` - Added payment gate to save_structured_result()

---

## PHP Syntax Validation
All 4 files pass `php -l` with zero errors.

---

## Related Systems
- Service Gate Model (`service_gate_model.php`) - Unified payment gate
- Unified Billing Model (`unified_billing_model.php`) - Billing queue system
- Lab Billing Table (`iop_lab_billing`) - Per-test payment tracking
