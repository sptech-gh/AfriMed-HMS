# Doctor Module QA Audit Report

**Date:** April 19, 2026  
**Purpose:** Fix sidebar anomalies and access denied errors for doctor role  
**Status:** RESOLVED (Updated)

## Root Cause Found
**The doctor role_id was incorrectly mapped in the RBAC system:**
- Database has doctor as `role_id = 5`
- RBAC helper was mapping `role_id 5 → cashier` (WRONG!)
- General.php was checking for `role_id 2` as doctor (WRONG!)

---

## Issues Identified

### 1. Access Denied on Patient Search
**Symptom:** Doctors clicking "Search Patients" redirected to `/access_denied`  
**Root Cause:** Page-based access check in `patient.php` index() method blocked doctors who didn't have page 49 in `user_roles_pages`  
**Fix:** Added bypass for doctors, nurses, receptionists, cashiers

### 2. Missing Sidebar Sections
**Symptom:** Doctor sidebar missing Clinical, Diagnostics, and other sections  
**Root Cause:** Access flags like `hasAccesstoOPDEnquiry`, `hasAccesstoLaboratory` not set for doctors  
**Fix:** Added comprehensive doctor role overrides in `General.php`

### 3. Billing/Pharmacy Items Showing for Doctors
**Symptom:** Doctors seeing billing and pharmacy menu items  
**Root Cause:** Conditional checks didn't explicitly exclude doctors  
**Fix:** Added `!$isDoctor` checks to billing-related sidebar sections

---

## Files Modified

### Controllers

#### `application/controllers/General.php`
- **Line 207:** Removed `$isDoctor` from pharmacy access (doctors prescribe, don't dispense)
- **Lines 227-236:** Added explicit doctor role restrictions:
  - `hasAccesstoBilling = FALSE`
  - `hasAccesstoPOS = FALSE`
  - `hasAccesstoPharmacy = FALSE`
  - `hasAccesstoAdmin = FALSE`
  - `hasAccesstoUsers = FALSE`
  - `hasAccesstoAddUsers = FALSE`
- **Lines 293-315:** Added doctor access overrides:
  - OPD/IPD Enquiry access
  - Patient access
  - EMR access (OPD + IPD)
  - Appointment access
  - Clinical reports access
- **Line 330:** Added `$isDoctor` to laboratory access for viewing patient results

#### `application/controllers/app/patient.php`
- **Line 17:** Updated `require_role` to include admin, doctor, nurse, receptionist, cashier
- **Lines 30-36:** Added role-based bypass for page access check

#### `application/controllers/app/emr.php`
- **Lines 36-42:** Added doctor bypass for OPD EMR page access
- **Lines 162-168:** Added doctor bypass for IPD EMR page access

### Views

#### `application/views/include/sidebar.php`
- **Line 303:** Added `!$isDoctor` to billing quick actions
- **Line 329:** Added `!$isDoctor` to NHIS claim quick action
- **Line 620:** Added `!$isDoctor` to Billing & Finance section header
- **Lines 691-704:** Wrapped financial reports in `!$isDoctor` block
- **Lines 736-741:** Wrapped GHS Compliance reports in `!$isDoctor` block

---

## Doctor Sidebar Structure (After Fix)

### Quick Actions
- My Patients ✓
- My Appointments ✓

### Clinical
- OPD (Worklist) ✓
- IPD (Worklist) ✓
- Appointments ✓

### Diagnostics
- Laboratory (Lab Queue, Lab Tests, Lab Enquiry) ✓
- Sonography ✓
- Radiology ✓

### Reports
- Clinical Reports (OPD Attendance, Top Diagnoses, Patient Reports) ✓
- Patient Reports (Masterlist, Individual Patient) ✓

### Patients
- Search Patients ✓
- Patient Masterlist ✓

### Nursing (Read-only access)
- OPD Vitals Queue ✓
- (View patient vitals only)

### Doctor Module
- Out-Patient ✓
- In-Patient ✓
- Messages ✓
- Report Hub (GHS) ✓

### EMR Sheet
- Out-Patient EMR ✓
- In-Patient EMR ✓

### Account
- Profile ✓
- Settings ✓

---

## Items NOT Shown to Doctors

- Billing Dashboard
- Create Bill
- Collect Payment
- NHIS Claims Submission
- Pharmacy (Stock, Dispensing, etc.)
- Financial Reports (Revenue Analytics, Department Revenue, Outstanding Balances)
- GHS Compliance Reports (Daily Returns, Pharmacy Consumption)
- Walk-In Services
- Administration (Users, Organization, Masters)

---

## Access Control Summary

| Module | Doctor Access | Notes |
|--------|--------------|-------|
| OPD Enquiry | ✓ | View patient worklist |
| IPD Enquiry | ✓ | View admitted patients |
| Patient Search | ✓ | Search all patients |
| EMR | ✓ | Full access (OPD + IPD) |
| Laboratory | ✓ | Read-only (view results) |
| Sonography | ✓ | View imaging results |
| Radiology | ✓ | View imaging results |
| Appointments | ✓ | Manage appointments |
| Doctor Module | ✓ | Full access |
| Nursing | ✓ | View vitals only |
| Billing | ✗ | Restricted |
| Pharmacy | ✗ | Restricted |
| Administration | ✗ | Restricted |
| Financial Reports | ✗ | Restricted |

---

## Verification Commands

```bash
# Check PHP syntax for all modified files
php -l application/controllers/General.php
php -l application/controllers/app/patient.php
php -l application/controllers/app/emr.php
php -l application/views/include/sidebar.php
```

## Test Scenarios

1. **Login as Doctor** → Should see correct sidebar
2. **Click "Search Patients"** → Should load patient list (no access denied)
3. **Click "My Patients"** → Should load doctor's patient queue
4. **Click "Laboratory"** → Should load lab queue (view only)
5. **Click "EMR Sheet"** → Should load EMR (OPD/IPD)
6. **Verify NO billing items** → Should not see any billing menu items
7. **Verify NO pharmacy items** → Should not see any pharmacy menu items

---

## Notes

- All changes preserve backward compatibility
- Role-based bypasses added where page-based checks were blocking legitimate access
- `has_role()` helper used consistently for role detection
- Access flags in `General.php` provide single source of truth
- Sidebar conditionals now properly exclude doctors from financial/administrative sections
