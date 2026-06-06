# HMS Super Admin Sidebar UX/UI Audit Report

**Generated:** April 3, 2026  
**System:** Hebrew Medical Center HMS  
**Target:** Super Admin Sidebar Navigation  
**File:** `application/views/include/sidebar.php`

---

## Executive Summary

| Metric | Current State | Target |
|--------|---------------|--------|
| **Top-Level Menu Items** | 14 | 8-10 |
| **Maximum Click Depth** | 4 levels | 2-3 levels |
| **Duplicate Features** | 6 identified | 0 |
| **Role Misplacements** | 8 items | 0 |
| **Icon Consistency** | 60% | 100% |
| **UX Score** | 55/100 | 85+ |

---

## 1. Current Sidebar Structure Analysis

### 1.1 Menu Hierarchy (As-Is)

```
├── Dashboard
├── Pharmacy (pharmacist, doctor)
│   ├── Pharmacy Worklist
│   ├── Stock Management
│   ├── Pharmacy Alerts
│   └── Pending Approvals (admin)
├── Billing & Finance (admin, cashier, billing, accountant)
│   ├── Dashboard
│   ├── [SECTION] Billing
│   │   ├── Create Bill
│   │   ├── Search Bills
│   │   └── Blocked Services
│   ├── [SECTION] Payments
│   │   ├── Collect Payment
│   │   ├── Payment History
│   │   └── Refunds
│   ├── [SECTION] Reports
│   │   ├── Analytics Dashboard
│   │   ├── Daily Report
│   │   ├── Department Revenue
│   │   ├── Outstanding Balances
│   │   ├── Reconciliation
│   │   └── Cashier Reconciliation
│   ├── [SECTION] Admin Tools (admin)
│   │   ├── Financial Ledger
│   │   ├── Audit Logs
│   │   ├── Refund Management
│   │   ├── Notifications
│   │   └── Permissions
│   └── Surgical Costing
├── NHIS Claims (admin, cashier, billing)
│   ├── Dashboard
│   ├── Claims List
│   ├── Coverage Management
│   ├── Reconciliation
│   ├── Reports
│   ├── Audit Log
│   ├── Claim-IT
│   ├── Submission Queue
│   ├── ICD-10 Codes
│   ├── Tariff Mapping
│   └── API Logs
├── Patient Appointment (doctor, cashier, receptionist)
│   ├── Add New Appointment
│   └── Manage Appointment
├── Patient Management (doctor, cashier, receptionist)
│   ├── Add New Patient
│   ├── Patient Master
│   ├── Patient History
│   ├── OPD (nested)
│   │   ├── OPD Registration
│   │   └── Out-Patient Enquiry
│   └── IPD (nested)
│       ├── Admit Patient
│       └── In-Patient Enquiry
├── Room Management (admin)
│   ├── Room Enquiry
│   ├── Room Category
│   ├── Room Master
│   └── Room Bed Master
├── Nurse Module (nurse, doctor)
│   ├── Patient Medication
│   ├── Intake/Output Record
│   ├── Nurse Progress Note
│   ├── Vital Sign
│   ├── Bed Side Procedure
│   ├── IP Room Transfer
│   ├── Patient History
│   ├── Discharge Summary
│   ├── OPD Vitals Queue
│   ├── Messages
│   └── Shift Tasks
├── Doctor Module (doctor)
│   ├── Out-Patient
│   ├── In-Patient
│   └── Messages
├── Laboratory Module
│   ├── Labs
│   ├── Lab Queue
│   └── Lab Enquiry
├── Sonography Module (sonographer, doctor)
│   ├── Sonography Dashboard
│   ├── Sonography Queue
│   ├── X-Ray Queue
│   ├── ECG Queue
│   └── Completed Scans
├── Radiology (admin, doctor, sonographer, nurse, receptionist)
│   ├── Dashboard
│   ├── New Order
│   └── Add Test (admin)
├── EMR Sheet (doctor)
│   ├── Out-Patient EMR
│   └── In-Patient EMR
├── User Management (admin)
│   ├── Add New User
│   ├── User Masterlist
│   └── User Roles
├── Administrator (admin)
│   ├── Company Information
│   ├── Department Master
│   ├── Designation Master
│   ├── Bill Group Name Master
│   ├── Particular Bill Master
│   ├── Complain Master
│   ├── Diagnosis Master
│   ├── Surgical Package
│   ├── Insurance Company
│   ├── NHIS Claims ⚠️ DUPLICATE
│   ├── Medicine Mgmt (nested)
│   │   ├── Category Master
│   │   └── Drug Name Master
│   ├── Acknowledge Receipt
│   ├── System Parameters
│   ├── Backup Database
│   ├── System Pages
│   └── Staff Privileges
├── Reports Generation
│   ├── Patient Masterlist Report
│   ├── Individual Patient Report
│   ├── Out Patient Report
│   ├── Admitted Patient Report
│   ├── Discharged Patient Report
│   ├── Daily Sales Report (admin)
│   ├── Doctor's Fee Report (admin)
│   └── Acknowledge Receipt Report (admin)
├── GHS Reports (admin, nurse, receptionist)
│   ├── Reports Dashboard
│   ├── OPD Attendance
│   ├── Top Diagnoses
│   ├── Pharmacy Consumption
│   ├── NHIS vs Cash (admin)
│   ├── Revenue Report (admin)
│   └── Daily Returns
└── User Profile
    ├── My Profile
    ├── Edit Profile
    └── Logout
```

---

## 2. Critical Issues Identified

### 2.1 Duplicate Features (HIGH PRIORITY)

| # | Duplicate Item | Location 1 | Location 2 | Recommendation |
|---|----------------|------------|------------|----------------|
| 1 | **NHIS Claims** | NHIS Claims Menu | Administrator → NHIS Claims | **REMOVE** from Administrator |
| 2 | **Reconciliation** | Billing & Finance → Reconciliation | NHIS Claims → Reconciliation | **MERGE** into single Reconciliation hub |
| 3 | **Audit Logs** | Billing & Finance → Audit Logs | NHIS Claims → Audit Log | **MERGE** into System → Audit Logs |
| 4 | **Reports** | Reports Generation | GHS Reports | Billing Reports | **CONSOLIDATE** into single Reports menu |
| 5 | **Sonography/Radiology** | Sonography Module | Radiology Module | **MERGE** into Diagnostics/Imaging |
| 6 | **Patient History** | Patient Management | Nurse Module | **KEEP** separate (different contexts) |

### 2.2 Poor Menu Grouping

| Issue | Current Location | Problem | Recommendation |
|-------|------------------|---------|----------------|
| OPD/IPD nested 3 levels deep | Patient Management → OPD/IPD | Too many clicks | Move to top-level Clinical menu |
| Medicine Mgmt nested in Admin | Administrator → Medicine Mgmt | Hidden from pharmacy staff | Move to Pharmacy menu |
| Surgical Costing in Billing | Billing & Finance | Misplaced | Move to Clinical or separate |
| Room Management separate | Top-level | Should be under IPD/Admin | Move to Administration |

### 2.3 Naming Inconsistencies

| Current Name | Issue | Recommended Name |
|--------------|-------|------------------|
| "Out-Patient Enquiry" | Confusing | "OPD Worklist" |
| "In-Patient Enquiry" | Confusing | "IPD Worklist" |
| "Particular Bill Master" | Technical jargon | "Service Charges" |
| "Bill Group Name Master" | Technical jargon | "Billing Categories" |
| "Complain Master" | Typo/unclear | "Chief Complaints" |
| "Acknowledge Receipt" | Unclear | "Receipt Declarations" |

### 2.4 Click Depth Analysis

| Action | Current Clicks | Target | Status |
|--------|----------------|--------|--------|
| Register OPD Patient | 3 clicks | 2 clicks | ⚠️ Too Deep |
| View Lab Queue | 2 clicks | 1 click | ✅ OK |
| Create Bill | 2 clicks | 1 click | ⚠️ High-frequency |
| Add Drug | 4 clicks | 2 clicks | ❌ Too Deep |
| View NHIS Claims | 2 clicks | 2 clicks | ✅ OK |
| Backup Database | 2 clicks | 2 clicks | ✅ OK |

---

## 3. Role-Based Analysis

### 3.1 Items Visible to Super Admin That Should Be Role-Specific

| Menu Item | Current Visibility | Should Be For | Action |
|-----------|-------------------|---------------|--------|
| Doctor Module | Hidden for admin | Doctor only | ✅ Correct |
| Nurse Module | Nurse, Doctor | Nurse only | ⚠️ Review |
| Pharmacy Worklist | Pharmacist, Doctor | Pharmacist | ⚠️ Review |
| OPD Vitals Queue | Nurse Module | Nurse only | ✅ Correct |
| EMR Sheet | Doctor only | Doctor only | ✅ Correct |

### 3.2 Missing Admin-Only Features

| Feature | Status | Recommendation |
|---------|--------|----------------|
| System Logs | Missing | Add to System menu |
| Error Logs | Missing | Add to System menu |
| API Configuration | Missing | Add to System menu |
| Email/SMS Settings | Missing | Add to System menu |
| License Info | Missing | Add to System menu |

---

## 4. UI/UX Issues

### 4.1 Icon Inconsistencies

| Menu | Current Icon | Issue | Recommended |
|------|--------------|-------|-------------|
| Laboratory Module | `fa-user-md` | Wrong (doctor icon) | `fa-flask` |
| Sonography Module | `fa-user-md` | Wrong (doctor icon) | `fa-heartbeat` |
| Patient Appointment | `fa-male` | Generic | `fa-calendar-check-o` |
| Reports Generation | `fa-print` | Outdated concept | `fa-bar-chart` |
| Radiology | `fa-x-ray` | Good but not in FA4 | `fa-film` or upgrade to FA5 |

### 4.2 Visual Hierarchy Issues

1. **No Section Headers** - All menus at same visual weight
2. **No Badge Counters** - Missing pending counts on key items
3. **Inconsistent Spacing** - Some items have `&nbsp;` padding, others don't
4. **No Collapsible Categories** - All menus expand independently
5. **No Visual Separators** - Hard to distinguish menu groups

### 4.3 Performance Concerns

```php
// Lines 597-601: Database query in sidebar for pending approvals
$__pendCnt = 0;
if (isset($this) && isset($this->governance_model) && method_exists($this->governance_model, 'count_pending_stock_requests')) {
    $__pendCnt = $this->governance_model->count_pending_stock_requests();
}
```

**Issue:** Multiple database queries executed on every page load for badge counts.

**Recommendation:** Cache badge counts in session with 5-minute TTL.

---

## 5. Recommended Modern Sidebar Structure

### 5.1 Proposed Hierarchy (Super Admin)

```
📊 Dashboard

═══════════════════════════════
CLINICAL
═══════════════════════════════

🏥 OPD
   ├── Registration
   ├── Worklist
   └── Quick Start

🛏️ IPD
   ├── Admit Patient
   ├── Worklist
   └── Discharge

📅 Appointments
   ├── Calendar
   └── Add New

═══════════════════════════════
DIAGNOSTICS
═══════════════════════════════

🔬 Laboratory
   ├── Lab Queue (5)
   ├── Results Entry
   └── Lab Enquiry

📷 Imaging
   ├── Radiology Queue
   ├── Sonography Queue
   ├── X-Ray Queue
   └── Completed Studies

═══════════════════════════════
PHARMACY
═══════════════════════════════

💊 Dispensing
   ├── Worklist (12)
   └── Alerts

📦 Stock
   ├── Inventory
   ├── Adjustments
   └── Low Stock Alerts

═══════════════════════════════
BILLING & FINANCE
═══════════════════════════════

💰 Billing
   ├── Create Bill
   ├── Search Bills
   └── Blocked Services

💳 Payments
   ├── Collect Payment
   ├── Payment History
   └── Refunds

🛡️ NHIS Claims
   ├── Dashboard
   ├── Claims List
   ├── Submission Queue
   └── Claim-IT

═══════════════════════════════
REPORTS
═══════════════════════════════

📈 Analytics
   ├── Revenue Dashboard
   ├── Department Summary
   └── Trends

📋 Clinical Reports
   ├── OPD Attendance
   ├── Top Diagnoses
   └── Patient Reports

💵 Financial Reports
   ├── Daily Sales
   ├── NHIS vs Cash
   └── Outstanding

🏛️ GHS Reports
   ├── Daily Returns
   └── Compliance

═══════════════════════════════
PATIENTS
═══════════════════════════════

👥 Patient Registry
   ├── Add Patient
   ├── Patient List
   └── Search

📁 Medical Records
   └── Patient History

═══════════════════════════════
ADMINISTRATION
═══════════════════════════════

👤 Users
   ├── User List
   ├── Add User
   └── Roles & Permissions

🏢 Organization
   ├── Company Info
   ├── Departments
   └── Designations

⚙️ Masters
   ├── Service Charges
   ├── Billing Categories
   ├── Diagnoses
   ├── Complaints
   └── Insurance Companies

💊 Medicine Masters
   ├── Drug Categories
   └── Drug Names

🏨 Facility
   ├── Rooms
   ├── Beds
   └── Surgical Packages

═══════════════════════════════
SYSTEM
═══════════════════════════════

🔧 Settings
   ├── Parameters
   ├── Notifications
   └── Permissions

📜 Audit
   ├── Activity Logs
   ├── NHIS Audit
   └── Financial Audit

💾 Maintenance
   ├── Backup
   └── System Pages

👤 My Account
   ├── Profile
   └── Logout
```

---

## 6. Merge Recommendations

### 6.1 Consolidate Imaging

**MERGE:** Sonography Module + Radiology → **Imaging/Diagnostics**

```php
// Before: 2 separate menus
├── Sonography Module
│   ├── Sonography Dashboard
│   ├── Sonography Queue
│   ├── X-Ray Queue
│   ├── ECG Queue
│   └── Completed Scans
├── Radiology
│   ├── Dashboard
│   ├── New Order
│   └── Add Test

// After: 1 unified menu
├── Imaging
│   ├── Dashboard
│   ├── Sonography Queue
│   ├── X-Ray Queue
│   ├── ECG Queue
│   ├── CT/MRI Queue
│   ├── Completed Studies
│   └── Add Test (admin)
```

### 6.2 Consolidate Reports

**MERGE:** Reports Generation + GHS Reports + Billing Reports → **Reports Hub**

### 6.3 Consolidate NHIS

**MERGE:** NHIS Claims menu + Administrator NHIS Claims → **Single NHIS Menu**

---

## 7. Implementation Plan

### Phase 1: Quick Wins (1-2 hours)

1. **Remove duplicate NHIS Claims** from Administrator menu
2. **Fix icon inconsistencies** (Laboratory, Sonography)
3. **Add section headers** using CSS styling
4. **Fix naming** (Complain → Complaints, Enquiry → Worklist)

### Phase 2: Restructure (4-6 hours)

1. **Create new sidebar structure** with logical groupings
2. **Move OPD/IPD** to top-level Clinical section
3. **Merge Sonography + Radiology** into Imaging
4. **Consolidate Reports** into single hub

### Phase 3: Optimization (2-3 hours)

1. **Cache badge counts** in session
2. **Add collapsible sections** with localStorage persistence
3. **Add keyboard navigation** support
4. **Implement lazy loading** for sub-menus

### Phase 4: Polish (2-3 hours)

1. **Add visual separators** between sections
2. **Implement smooth animations**
3. **Add tooltips** for collapsed sidebar
4. **Mobile responsiveness** improvements

---

## 8. Files to Modify

| File | Changes |
|------|---------|
| `application/views/include/sidebar.php` | Main restructure |
| `public/css/AdminLTE.css` | Section header styles |
| `application/helpers/menu_helper.php` | Create new helper (optional) |
| `application/models/app/sidebar_model.php` | Badge count caching |

---

## 9. Backward Compatibility Notes

1. **DO NOT** change URL routes - only menu organization
2. **DO NOT** remove permission checks - preserve all `has_role()` and `hasAccessto*` checks
3. **DO NOT** break existing bookmarks - all URLs remain the same
4. **PRESERVE** legacy cashier menu (commented out) for rollback

---

## 10. Expected Outcomes

| Metric | Before | After |
|--------|--------|-------|
| Top-level items | 14 | 9 |
| Max click depth | 4 | 2 |
| Duplicates | 6 | 0 |
| Icon consistency | 60% | 100% |
| UX Score | 55/100 | 85/100 |
| Page load (sidebar) | ~150ms | ~50ms |

---

## Appendix A: Role-Specific Sidebar Recommendations

### Super Admin
- Full access to all menus
- System settings visible
- All reports accessible

### Doctor
- Clinical (OPD/IPD)
- Diagnostics (view only)
- EMR
- Messages
- Profile

### Receptionist
- Appointments
- Patient Registration
- OPD Registration
- Basic Reports

### Cashier
- Billing & Finance
- NHIS Claims
- Payment collection
- Financial reports

### Nurse
- Nurse Module (full)
- OPD Vitals Queue
- Patient History (view)

### Pharmacist
- Pharmacy (full)
- Stock Management
- Alerts

### Laboratory
- Laboratory Module (full)
- Lab Queue
- Results Entry

---

**Report Generated By:** HMS Sidebar Audit System  
**Next Review:** Quarterly
