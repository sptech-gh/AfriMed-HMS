# Hospital Management System (HMS)
# Administrator Training Manual

**Version:** 1.0  
**Last Updated:** March 2026  
**Audience:** System Administrators, Super Admins

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Dashboard Overview](#3-dashboard-overview)
4. [Patient Management](#4-patient-management)
5. [OPD Management](#5-opd-management)
6. [IPD Management](#6-ipd-management)
7. [Billing & Finance](#7-billing--finance)
8. [Pharmacy Management](#8-pharmacy-management)
9. [Laboratory Management](#9-laboratory-management)
10. [User Management](#10-user-management)
11. [System Configuration](#11-system-configuration)
12. [Reports & Analytics](#12-reports--analytics)
13. [NHIS Claims Management](#13-nhis-claims-management)
14. [GHS Reports](#14-ghs-reports)
15. [Troubleshooting](#15-troubleshooting)

---

## 1. Introduction

### 1.1 What is HMS?

The Hospital Management System (HMS) is a comprehensive healthcare management solution designed to streamline hospital operations. It manages patient records, appointments, billing, pharmacy, laboratory, and more.

### 1.2 Administrator Role

As an Administrator, you have full access to all system functions including:
- Managing all users and their permissions
- Configuring system settings
- Viewing all reports and analytics
- Managing billing and financial operations
- Overseeing all departments

### 1.3 System Requirements

- **Browser:** Google Chrome (recommended), Firefox, or Edge
- **Internet:** Stable internet connection
- **Screen:** Minimum 1024x768 resolution

---

## 2. Getting Started

### 2.1 Logging In

1. Open your web browser
2. Navigate to the HMS URL (e.g., `http://your-hospital-domain/hms-master`)
3. Enter your **Username** and **Password**
4. Click the **Login** button

![Login Screen]

> **Security Tip:** Never share your login credentials. Change your password regularly.

### 2.2 First-Time Login

On your first login:
1. You may be prompted to change your default password
2. Choose a strong password (minimum 8 characters, mix of letters, numbers, symbols)
3. Update your profile information if needed

### 2.3 Logging Out

Always log out when leaving your workstation:
1. Click your username in the top-right corner
2. Select **Logout**
3. Confirm the logout

---

## 3. Dashboard Overview

### 3.1 Admin Dashboard Elements

When you log in, you'll see the Admin Dashboard with:

| Element | Description |
|---------|-------------|
| **Today's OPD** | Number of outpatients registered today |
| **Today's IPD** | Number of inpatients admitted today |
| **Total Revenue** | Today's total revenue collected |
| **Pending Bills** | Number of unpaid invoices |
| **Quick Stats** | Summary boxes with key metrics |
| **Recent Activity** | Latest system activities |

### 3.2 Navigation Sidebar

The left sidebar contains all menu options:

- **Dashboard** - Main overview page
- **Patient Management** - Register and manage patients
- **OPD** - Outpatient department
- **IPD** - Inpatient department
- **Billing** - Financial operations
- **Pharmacy** - Drug management
- **Laboratory** - Lab tests and results
- **NHIS Claims** - Insurance claims
- **Reports** - Generate reports
- **Configuration** - System settings
- **User Management** - Manage users

### 3.3 Top Navigation Bar

| Icon/Button | Function |
|-------------|----------|
| **☰ Menu Toggle** | Collapse/expand sidebar |
| **🔔 Notifications** | View system alerts |
| **👤 User Profile** | Access profile and logout |

---

## 4. Patient Management

### 4.1 Registering a New Patient

**Navigation:** Patient Management → Add Patient

**Step-by-Step:**

1. Click **Patient Management** in the sidebar
2. Click **Add Patient** button
3. Fill in the required fields:

| Field | Description | Required |
|-------|-------------|----------|
| **Patient ID** | Auto-generated or manual | Yes |
| **First Name** | Patient's first name | Yes |
| **Last Name** | Patient's surname | Yes |
| **Middle Name** | Patient's middle name | No |
| **Date of Birth** | Birth date (YYYY-MM-DD) | Yes |
| **Gender** | Male/Female | Yes |
| **Phone** | Contact number | Yes |
| **Address** | Residential address | No |
| **Emergency Contact** | Emergency contact person | No |
| **NHIS Number** | Insurance number (if applicable) | No |

4. Click **Save** to register the patient

### 4.2 Searching for Patients

**Navigation:** Patient Management → Patient List

**Search Options:**
- **Patient ID** - Search by unique patient number
- **Name** - Search by first or last name
- **Phone** - Search by phone number
- **NHIS Number** - Search by insurance number

**How to Search:**
1. Enter search term in the search box
2. Press Enter or click the Search button
3. Results will display in the table below

### 4.3 Editing Patient Information

1. Find the patient using search
2. Click the **Edit** button (pencil icon) next to the patient
3. Modify the required fields
4. Click **Update** to save changes

### 4.4 Viewing Patient History

1. Find the patient using search
2. Click the **View** button (eye icon)
3. You'll see:
   - Personal information
   - Visit history
   - Billing history
   - Lab results
   - Prescriptions

---

## 5. OPD Management

### 5.1 OPD Registration

**Navigation:** OPD → Registration

**Registering an OPD Visit:**

1. Click **OPD** → **Registration**
2. Search for existing patient OR register new patient
3. Select the patient
4. Fill in visit details:

| Field | Description |
|-------|-------------|
| **Visit Date** | Date of visit (defaults to today) |
| **Department** | Select department |
| **Doctor** | Assign consulting doctor |
| **Visit Type** | New/Follow-up |
| **Payment Type** | Cash/NHIS/Insurance |
| **Symptoms** | Initial complaint |

5. Click **Register** to create the visit

### 5.2 OPD Queue Management

**Navigation:** OPD → Queue

The queue shows all patients waiting:

| Status | Meaning |
|--------|---------|
| **Waiting** | Patient registered, waiting for doctor |
| **In Consultation** | Currently with doctor |
| **Completed** | Consultation finished |
| **Pending Payment** | Awaiting billing |

**Actions:**
- **Call Patient** - Move to consultation
- **View Details** - See patient information
- **Cancel Visit** - Cancel the registration

### 5.3 EMR (Electronic Medical Records)

**Navigation:** EMR → OPD

The EMR view shows all OPD patients with their records:

| Column | Description |
|--------|-------------|
| **IOP ID** | Visit identifier |
| **Patient No** | Patient ID |
| **Patient Name** | Full name |
| **Date** | Visit date |
| **Status** | Current status |
| **Actions** | View/Edit options |

---

## 6. IPD Management

### 6.1 IPD Admission

**Navigation:** IPD → Admission

**Admitting a Patient:**

1. Click **IPD** → **Admission**
2. Search for the patient
3. Fill in admission details:

| Field | Description |
|-------|-------------|
| **Admission Date** | Date of admission |
| **Ward** | Select ward |
| **Bed** | Assign bed number |
| **Admitting Doctor** | Select doctor |
| **Diagnosis** | Initial diagnosis |
| **Payment Type** | Cash/NHIS/Insurance |

4. Click **Admit** to complete

### 6.2 Ward Management

**Navigation:** Configuration → Rooms/Wards

**Managing Wards:**
- View all wards and beds
- See occupancy status
- Add new wards/beds
- Edit ward details

**Bed Status:**
| Status | Color | Meaning |
|--------|-------|---------|
| Available | Green | Bed is free |
| Occupied | Red | Patient assigned |
| Reserved | Yellow | Reserved for incoming patient |
| Maintenance | Gray | Under maintenance |

### 6.3 Patient Transfer

1. Find the patient in IPD list
2. Click **Transfer** button
3. Select new ward and bed
4. Add transfer reason
5. Click **Confirm Transfer**

### 6.4 Discharge Process

1. Find the patient in IPD list
2. Click **Discharge** button
3. Complete discharge summary:
   - Final diagnosis
   - Treatment summary
   - Discharge instructions
   - Follow-up date
4. Ensure all bills are settled
5. Click **Complete Discharge**

---

## 7. Billing & Finance

### 7.1 Point of Sale (POS)

**Navigation:** Billing → POS

The POS is used for creating invoices:

**Creating an Invoice:**

1. Click **Billing** → **POS**
2. Search and select patient
3. Add billing items:
   - Select category (Consultation, Lab, Pharmacy, etc.)
   - Select specific item
   - Enter quantity
   - Price auto-fills (can be adjusted)
4. Apply discount if applicable
5. Select payment type (Cash/NHIS/Card/MoMo)
6. Click **Save Invoice**

### 7.2 Billing List

**Navigation:** Billing → Billing List

View all invoices with filters:
- **Date Range** - Filter by date
- **Status** - Paid/Unpaid/Partial
- **Patient** - Search by patient

**Actions:**
- **View** - See invoice details
- **Print** - Print invoice
- **Edit** - Modify invoice (if unpaid)
- **Receive Payment** - Record payment

### 7.3 Payment Collection

**Navigation:** Billing → Payment Collection

**Collecting Payment:**

1. Search for the invoice
2. Click **Pay** button
3. Enter payment details:
   - Amount (can be partial)
   - Payment method
   - Reference number (for electronic payments)
4. Click **Record Payment**
5. Print receipt

### 7.4 Daily Collection Report

**Navigation:** Billing → Daily Collection

View daily financial summary:
- Total collections by payment method
- Transaction list
- Cashier-wise breakdown (Admin only)

**Export Options:**
- Print report
- Export to PDF

### 7.5 Smart Billing

**Navigation:** Billing → Smart Billing

One-click billing for standard procedures:
1. Select patient
2. Choose billing template
3. Review items
4. Click **Generate Invoice**

### 7.6 Pharmacy Bills

**Navigation:** Billing → Pharmacy Bills

Manage pharmacy-related billing:
- View pending pharmacy payments
- Process payments
- Handle waivers (Admin only)

### 7.7 Reconciliation (Admin Only)

**Navigation:** Billing → Reconciliation

Daily financial reconciliation:
- Compare expected vs actual collections
- Identify discrepancies
- Generate reconciliation reports

---

## 8. Pharmacy Management

### 8.1 Drug Inventory

**Navigation:** Pharmacy → Stock Management

**Viewing Stock:**
- See all drugs with current stock levels
- Filter by category
- Search by drug name
- View low stock alerts

**Stock Columns:**
| Column | Description |
|--------|-------------|
| **Drug Name** | Name of medication |
| **Category** | Drug category |
| **Stock** | Current quantity |
| **Reorder Level** | Minimum stock threshold |
| **Price** | Selling price |
| **NHIS Price** | Insurance price |

### 8.2 Adding Stock

**Navigation:** Pharmacy → Stock Management → Restock

1. Find the drug
2. Click **Restock** button
3. Enter details:
   - Quantity to add
   - Batch number
   - Expiry date
   - Unit cost
   - Supplier
4. Click **Save**

### 8.3 Stock Adjustments

For corrections or write-offs:

1. Find the drug
2. Click **Adjust** button
3. Select adjustment type:
   - **Restock** - Add stock
   - **Write-off** - Remove damaged/expired
   - **Correction** - Fix count errors
4. Enter quantity and reason
5. Click **Save**

### 8.4 Pharmacy Alerts

**Navigation:** Pharmacy → Alerts

View critical alerts:
- **Low Stock** - Drugs below reorder level
- **Expiring Soon** - Drugs expiring within 30 days
- **Expired** - Drugs past expiry date

**Actions:**
- Restock low items
- Remove expired batches

### 8.5 Drug Management

**Navigation:** Configuration → Drug Name

**Adding a New Drug:**

1. Click **Add Drug**
2. Fill in details:
   - Drug name
   - Generic name
   - Category
   - Dosage form
   - Strength
   - Price
   - NHIS price
   - Reorder level
3. Click **Save**

---

## 9. Laboratory Management

### 9.1 Lab Test Categories

**Navigation:** Configuration → Bill Group

Lab tests are organized by category:
- Haematology
- Biochemistry
- Microbiology
- Serology
- Clinical Pathology
- And more...

### 9.2 Managing Lab Tests

**Navigation:** Configuration → Particular Bill

**Adding a Lab Test:**

1. Click **Add Particular**
2. Select category (e.g., Haematology)
3. Enter test details:
   - Test name
   - Price
   - NHIS price
4. Click **Save**

### 9.3 Lab Results

**Navigation:** Laboratory → Results

View and manage lab results:
- See pending tests
- Enter results
- Print reports
- View history

---

## 10. User Management

### 10.1 User Roles

The system has the following roles:

| Role | Access Level |
|------|--------------|
| **Admin** | Full system access |
| **Doctor** | Patient care, prescriptions, EMR |
| **Nurse** | Patient care, vitals, medications |
| **Receptionist** | Registration, appointments |
| **Cashier** | Billing, payments |
| **Pharmacist** | Pharmacy operations |
| **Laboratory** | Lab tests and results |
| **Sonographer** | Imaging services |

### 10.2 Creating a New User

**Navigation:** User Management → Add User

1. Click **Add User**
2. Fill in user details:

| Field | Description |
|-------|-------------|
| **Username** | Login username (unique) |
| **Password** | Initial password |
| **Full Name** | User's full name |
| **Email** | Email address |
| **Phone** | Contact number |
| **Role** | Select user role |
| **Department** | Assign department |

3. Click **Save**

### 10.3 Editing Users

1. Find user in the list
2. Click **Edit** button
3. Modify details
4. Click **Update**

### 10.4 Resetting Passwords

1. Find user in the list
2. Click **Reset Password**
3. Enter new password
4. Click **Save**

> **Note:** User will need to change password on next login

### 10.5 Deactivating Users

1. Find user in the list
2. Click **Deactivate**
3. Confirm the action

> **Note:** Deactivated users cannot log in but their records are preserved

---

## 11. System Configuration

### 11.1 Company Information

**Navigation:** Configuration → Company Info

Set up hospital details:
- Hospital name
- Address
- Phone numbers
- Email
- Logo
- Website

### 11.2 Departments

**Navigation:** Configuration → Department

Manage hospital departments:
- Add new departments
- Edit department names
- Deactivate departments

### 11.3 Designations

**Navigation:** Configuration → Designation

Manage staff designations:
- Doctor
- Nurse
- Technician
- etc.

### 11.4 Bill Groups

**Navigation:** Configuration → Bill Group

Categories for billing items:
- Consultation
- Laboratory
- Pharmacy
- Radiology
- etc.

### 11.5 Particular Bills

**Navigation:** Configuration → Particular Bill

Individual billable items:
- Consultation fees
- Lab tests
- Procedures
- etc.

### 11.6 Insurance Companies

**Navigation:** Configuration → Insurance Company

Manage insurance providers:
- Add insurance companies
- Set discount rates
- Configure billing rules

### 11.7 System Parameters

**Navigation:** Configuration → Parameters

Configure system-wide settings:
- Gender options
- Marital status
- Blood groups
- etc.

---

## 12. Reports & Analytics

### 12.1 Available Reports

**Navigation:** Reports

| Report | Description |
|--------|-------------|
| **Daily Revenue** | Daily income summary |
| **Monthly Revenue** | Monthly financial report |
| **Patient Statistics** | Patient visit analytics |
| **Department Wise** | Revenue by department |
| **Doctor Wise** | Revenue by doctor |
| **Lab Reports** | Laboratory statistics |
| **Pharmacy Reports** | Drug dispensing reports |

### 12.2 Generating Reports

1. Select report type
2. Set date range
3. Apply filters (if any)
4. Click **Generate**
5. View on screen or export

### 12.3 Export Options

Reports can be exported as:
- **PDF** - For printing
- **Excel** - For further analysis
- **Print** - Direct printing

---

## 13. NHIS Claims Management

### 13.1 NHIS Overview

The National Health Insurance Scheme (NHIS) module manages:
- Patient eligibility verification
- Claims submission
- Claims tracking
- Reconciliation

### 13.2 Claims Dashboard

**Navigation:** NHIS Claims → Dashboard

View:
- Pending claims
- Submitted claims
- Approved claims
- Rejected claims

### 13.3 Submitting Claims

1. Go to NHIS Claims
2. Select pending claims
3. Review claim details
4. Click **Submit**

### 13.4 Claims Reconciliation

**Navigation:** NHIS Claims → Reconciliation

Match submitted claims with payments:
1. Upload NHIS payment file
2. System matches claims
3. Review discrepancies
4. Mark as reconciled

---

## 14. GHS Reports

### 14.1 GHS Reporting Dashboard

**Navigation:** GHS Reports → Dashboard

Ghana Health Service required reports:

| Report | Description |
|--------|-------------|
| **OPD Attendance** | Daily/monthly OPD statistics |
| **Diagnosis Report** | Disease statistics |
| **Pharmacy Consumption** | Drug usage report |
| **NHIS vs Cash** | Payment type analysis |
| **Revenue Report** | Financial summary |
| **Daily Returns** | Daily activity summary |

### 14.2 Generating GHS Reports

1. Select report type
2. Choose date range
3. Click **Generate**
4. Review data
5. Export or print

---

## 15. Troubleshooting

### 15.1 Common Issues

| Problem | Solution |
|---------|----------|
| **Cannot login** | Check username/password, clear browser cache |
| **Page not loading** | Refresh page, check internet connection |
| **Data not saving** | Check required fields, try again |
| **Report not generating** | Check date range, try smaller range |
| **Printer not working** | Check printer connection, browser settings |

### 15.2 Getting Help

If you encounter issues:
1. Note the error message
2. Note what you were doing
3. Contact IT support
4. Provide screenshots if possible

### 15.3 Best Practices

- **Log out** when leaving your workstation
- **Back up** data regularly
- **Update** passwords periodically
- **Report** any suspicious activity
- **Train** new users properly

---

## Appendix A: Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl + S` | Save |
| `Ctrl + P` | Print |
| `Ctrl + F` | Find/Search |
| `Esc` | Close modal/dialog |
| `Enter` | Submit form |

---

## Appendix B: Glossary

| Term | Definition |
|------|------------|
| **OPD** | Outpatient Department |
| **IPD** | Inpatient Department |
| **EMR** | Electronic Medical Records |
| **NHIS** | National Health Insurance Scheme |
| **IOP ID** | In/Out Patient Identifier |
| **POS** | Point of Sale |
| **GHS** | Ghana Health Service |

---

## Appendix C: Contact Information

**Technical Support:**
- Email: support@hospital.com
- Phone: +233 XX XXX XXXX
- Hours: 8:00 AM - 5:00 PM

---

*This manual is confidential and intended for authorized personnel only.*

**© 2026 Hospital Management System**
