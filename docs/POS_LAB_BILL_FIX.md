# POS Lab Bill - Patient Data Fix

## Problem
When clicking "Bill" button on a lab request, the POS billing page loads with:
- ❌ Patient No: -
- ❌ IOP No: -
- ❌ Patient Name: -
- ❌ Empty billing list

## Root Cause
The `pos_lab_bill()` method didn't set the `direct` flag that the view expects to display patient information directly.

## Solution Applied

### 1. Controller Fix: `application/controllers/app/pos.php`

**Added (Lines 154-164):**
```php
// Set direct flag so view displays patient info properly
$this->data['direct'] = true;
$this->data['patient_rows'] = $patient_info;

// Also set OPD/IO info for display
$iop_id = isset($lab_bill->iop_id) ? $lab_bill->iop_id : '';
if ($iop_id !== '') {
    $this->load->model('app/opd_model');
    $iop_info = $this->opd_model->getPatientDetails($iop_id);
    $this->data['iop_info'] = $iop_info;
}
```

### 2. View Fix: `application/views/app/pos/index.php`

**Added IOP No Display (Lines 99-102):**
```php
<tr>
    <td><strong>IOP No.</strong></td>
    <td><?php echo (isset($iop_info) && isset($iop_info->IO_ID)) ? $iop_info->IO_ID : ((isset($auto_load_io_id) && $auto_load_io_id !== '') ? $auto_load_io_id : '-'); ?></td>
</tr>
```

**Fixed Hidden Field (Line 113):**
```php
<input type="hidden" name="opd_no" id="opd_no" value="<?php echo (isset($iop_info) && isset($iop_info->IO_ID)) ? $iop_info->IO_ID : ((isset($auto_load_io_id) && $auto_load_io_id !== '') ? $auto_load_io_id : '0'); ?>">
<input type="hidden" name="patient_no" id="patient_no" value="<?php echo $patient_rows->patient_no ?>">
```

## Result

✅ **Before:**
```
Patient No.  -
IOP No.      -
Patient Name. -
```

✅ **After:**
```
Patient No.  000001
IOP No.      OP 000002
Patient Name. John Doe
Billing List: [Lab Test automatically added]
```

## Testing Steps

1. **Doctor requests lab:**
   - OPD → Laboratory → Add test → Save

2. **Click "Bill" button:**
   - Lab queue → Click "Bill" on pending request

3. **Verify POS page shows:**
   - ✅ Patient No (e.g., 000001)
   - ✅ IOP No (e.g., OP 000002)
   - ✅ Patient Name (e.g., John Doe)
   - ✅ Lab test auto-added to billing list

4. **Complete billing:**
   - Save invoice
   - Process payment

## Files Modified

| File | Changes |
|------|---------|
| `application/controllers/app/pos.php` | Added `direct` flag, `iop_info` loading |
| `application/views/app/pos/index.php` | Added IOP No display, fixed hidden fields |

## Technical Notes

- The `direct` flag tells the view to display patient info directly from `patient_rows`
- Without this flag, the view shows empty placeholders and expects AJAX loading
- The auto-load JavaScript still adds the lab item to the billing table
- IOP No is now displayed and passed correctly in the form
