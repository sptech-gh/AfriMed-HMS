# Hospital Management System (HMS)
# User Training Manual

**Version:** 1.0  
**Last Updated:** March 2026  
**Audience:** Doctors, Nurses, Receptionists, Cashiers, Pharmacists, Lab Technicians, Sonographers

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Getting Started](#2-getting-started)
3. [Receptionist Guide](#3-receptionist-guide)
4. [Doctor Guide](#4-doctor-guide)
5. [Nurse Guide](#5-nurse-guide)
6. [Cashier Guide](#6-cashier-guide)
7. [Pharmacist Guide](#7-pharmacist-guide)
8. [Laboratory Technician Guide](#8-laboratory-technician-guide)
9. [Sonographer Guide](#9-sonographer-guide)
10. [Common Functions](#10-common-functions)
11. [Troubleshooting](#11-troubleshooting)

---

## 1. Introduction

### 1.1 About This Manual

This manual provides step-by-step instructions for using the Hospital Management System (HMS). Each section is tailored to your specific role in the hospital.

### 1.2 Your Role in the System

| Role | Primary Responsibilities |
|------|-------------------------|
| **Receptionist** | Patient registration, appointments, OPD check-in |
| **Doctor** | Patient consultation, diagnosis, prescriptions |
| **Nurse** | Vitals, patient care, medication administration |
| **Cashier** | Billing, payment collection, receipts |
| **Pharmacist** | Drug dispensing, stock management |
| **Lab Technician** | Lab tests, results entry |
| **Sonographer** | Imaging scans, reports |

### 1.3 Important Notes

- Always log out when leaving your workstation
- Never share your login credentials
- Report any system issues to IT support
- Patient information is confidential

---

## 2. Getting Started

### 2.1 Logging In

**Step 1:** Open your web browser (Chrome recommended)

**Step 2:** Go to the HMS website address

**Step 3:** Enter your credentials:
- **Username:** Your assigned username
- **Password:** Your password

**Step 4:** Click **Login**

### 2.2 Your Dashboard

After logging in, you'll see your role-specific dashboard with:
- Quick action buttons
- Today's summary
- Pending tasks
- Navigation menu (left sidebar)

### 2.3 Navigation

The **sidebar menu** on the left shows options available to your role:
- Click any menu item to open that section
- Click the **☰** icon to collapse/expand the menu
- Your current location is highlighted

### 2.4 Logging Out

**Always log out when done:**
1. Click your name in the top-right corner
2. Select **Logout**
3. Confirm logout

---

## 3. Receptionist Guide

### 3.1 Your Dashboard

As a Receptionist, your dashboard shows:
- Today's appointments
- Patients waiting
- Quick registration button
- Recent registrations

### 3.2 Registering a New Patient

**Navigation:** Patient Management → Add Patient

**Step-by-Step:**

1. Click **Patient Management** in the sidebar
2. Click **Add Patient**
3. Fill in patient details:

| Field | What to Enter | Required? |
|-------|---------------|-----------|
| First Name | Patient's first name | ✓ Yes |
| Last Name | Patient's surname | ✓ Yes |
| Middle Name | Middle name (if any) | No |
| Date of Birth | Birth date | ✓ Yes |
| Gender | Select Male/Female | ✓ Yes |
| Phone | Mobile number | ✓ Yes |
| Address | Home address | No |
| NHIS Number | Insurance number | No |
| Emergency Contact | Contact person | No |

4. Click **Save**
5. Note the **Patient ID** generated

> **Tip:** If patient already exists, search first before creating new record!

### 3.3 Searching for Existing Patients

**Navigation:** Patient Management → Patient List

1. Click **Patient Management**
2. In the search box, enter:
   - Patient ID, OR
   - Patient name, OR
   - Phone number
3. Press **Enter** or click **Search**
4. Select the patient from results

### 3.4 OPD Registration (Check-In)

**Navigation:** OPD → Registration

**When a patient arrives for OPD:**

1. Click **OPD** → **Registration**
2. Search for the patient
3. Select the patient
4. Fill in visit details:

| Field | What to Select |
|-------|----------------|
| Visit Date | Today (auto-filled) |
| Department | Patient's department |
| Doctor | Consulting doctor |
| Visit Type | New Visit or Follow-up |
| Payment Type | Cash, NHIS, or Insurance |
| Complaint | Patient's main complaint |

5. Click **Register**
6. Give patient their **queue number**

### 3.5 Managing Appointments

**Navigation:** Appointments

**Booking an Appointment:**

1. Click **Appointments**
2. Click **New Appointment**
3. Search and select patient
4. Choose:
   - Date
   - Time slot
   - Doctor
   - Department
5. Click **Book**

**Viewing Today's Appointments:**

1. Click **Appointments**
2. View the calendar/list
3. Status shows: Scheduled, Arrived, Completed, Cancelled

### 3.6 Printing Patient Cards

1. Find the patient
2. Click **Print Card** button
3. Card prints with patient details and barcode

---

## 4. Doctor Guide

### 4.1 Your Dashboard

As a Doctor, your dashboard shows:
- Patients waiting for you
- Today's consultations
- Pending lab results
- Quick access to EMR

### 4.2 Viewing Your Patient Queue

**Navigation:** OPD → Queue

Your queue shows patients assigned to you:

| Column | Meaning |
|--------|---------|
| Queue # | Patient's position |
| Patient Name | Who to see |
| Time | Registration time |
| Status | Waiting/In Consultation |
| Action | Call/View buttons |

**To call a patient:**
1. Click **Call** next to the patient
2. Patient status changes to "In Consultation"

### 4.3 Patient Consultation (EMR)

**Navigation:** EMR → OPD

**Opening a Patient's Record:**

1. Click **EMR** → **OPD**
2. Find your patient
3. Click **View** or **Consult**

**The EMR Screen Contains:**

| Tab | Contents |
|-----|----------|
| **Summary** | Patient overview, vitals, allergies |
| **History** | Past visits and diagnoses |
| **Vitals** | Current vital signs |
| **Diagnosis** | Add/view diagnoses |
| **Prescription** | Write prescriptions |
| **Lab Orders** | Order lab tests |
| **Imaging** | Order scans |
| **Notes** | Clinical notes |

### 4.4 Recording Vitals

**In the EMR → Vitals tab:**

1. Click **Add Vitals**
2. Enter measurements:

| Vital | Unit |
|-------|------|
| Blood Pressure | mmHg (e.g., 120/80) |
| Pulse | bpm |
| Temperature | °C |
| Respiratory Rate | breaths/min |
| SpO2 | % |
| Weight | kg |
| Height | cm |

3. Click **Save**

### 4.5 Adding Diagnosis

**In the EMR → Diagnosis tab:**

1. Click **Add Diagnosis**
2. Search for diagnosis (ICD-10 codes)
3. Select the diagnosis
4. Add remarks if needed
5. Click **Save**

> **Tip:** You can add multiple diagnoses

### 4.6 Writing Prescriptions

**In the EMR → Prescription tab:**

1. Click **Add Medication**
2. Search for the drug
3. Fill in details:

| Field | Example |
|-------|---------|
| Drug | Paracetamol 500mg |
| Dosage | 1 tablet |
| Frequency | 3 times daily |
| Duration | 5 days |
| Quantity | 15 |
| Instructions | After meals |

4. Click **Add**
5. Repeat for more medications
6. Click **Save Prescription**

### 4.7 Ordering Lab Tests

**In the EMR → Lab Orders tab:**

1. Click **Order Lab Test**
2. Select test category (e.g., Haematology)
3. Select specific test (e.g., Full Blood Count)
4. Add clinical notes if needed
5. Mark as **Urgent** if needed
6. Click **Order**

### 4.8 Ordering Imaging/Scans

**In the EMR → Imaging tab:**

1. Click **Order Scan**
2. Select scan type (e.g., Ultrasound, X-Ray)
3. Select specific examination
4. Add clinical indication
5. Click **Order**

### 4.9 Completing Consultation

After finishing with a patient:

1. Ensure all notes are saved
2. Click **Complete Consultation**
3. Patient moves to billing queue

### 4.10 Viewing Lab Results

**Navigation:** Laboratory → Results

1. Click **Laboratory** → **Results**
2. Find your patient
3. Click **View** to see results
4. Results show with normal ranges

---

## 5. Nurse Guide

### 5.1 Your Dashboard

As a Nurse, your dashboard shows:
- Patients requiring vitals
- Medication schedules
- Pending tasks
- Ward overview (for IPD)

### 5.2 Recording Patient Vitals

**Navigation:** Nursing → Vitals

1. Click **Nursing** → **Vitals**
2. Search for patient
3. Click **Add Vitals**
4. Enter measurements:
   - Blood Pressure
   - Pulse
   - Temperature
   - Respiratory Rate
   - SpO2
   - Weight/Height
5. Click **Save**

### 5.3 Medication Administration (IPD)

**Navigation:** Nursing → Medication

**For inpatients:**

1. Click **Nursing** → **Medication**
2. Select ward
3. View medication schedule
4. For each medication due:
   - Click **Administer**
   - Confirm dose given
   - Add notes if needed
   - Click **Save**

### 5.4 Patient Monitoring

**Navigation:** Nursing → Monitoring

Track patient status:
- View vital trends
- Add nursing notes
- Report concerns to doctor

### 5.5 Ward Management (IPD)

**Navigation:** IPD → Ward

View ward status:
- Bed occupancy
- Patient list
- Pending tasks

---

## 6. Cashier Guide

### 6.1 Your Dashboard

As a Cashier, your dashboard shows:
- Today's collections
- Pending payments
- Unpaid invoices
- Quick stats

### 6.2 Creating an Invoice (POS)

**Navigation:** Billing → POS

1. Click **Billing** → **POS**
2. Search and select patient
3. Add billing items:
   - Select category
   - Select item
   - Enter quantity
   - Price auto-fills
4. Add more items as needed
5. Apply discount if authorized
6. Click **Save Invoice**
7. Invoice number is generated

### 6.3 Collecting Payments

**Navigation:** Billing → Payment Collection

**Step-by-Step:**

1. Click **Billing** → **Payment Collection**
2. Search for invoice (by invoice #, patient name, or ID)
3. Click **Pay** button
4. Enter payment details:

| Field | What to Enter |
|-------|---------------|
| Amount | Payment amount |
| Method | Cash/MoMo/Card/Bank |
| Reference | Transaction ID (for electronic) |
| Notes | Any notes |

5. Click **Record Payment**
6. Print receipt for patient

### 6.4 Viewing Invoice Details

1. Find the invoice
2. Click **View** (eye icon)
3. See:
   - Line items
   - Total amount
   - Amount paid
   - Balance due
   - Payment history

### 6.5 Printing Receipts

After payment:
1. Click **Print Receipt**
2. Receipt opens in new window
3. Click **Print** button

### 6.6 Daily Collection Report

**Navigation:** Billing → Daily Collection

View your daily summary:
- Total collections
- Breakdown by payment method
- Transaction list

**To view report:**
1. Select date
2. Click **View Report**
3. Export to PDF if needed

### 6.7 Billing List

**Navigation:** Billing → Billing List

View all invoices:
- Filter by date range
- Filter by status (Paid/Unpaid)
- Search by patient

### 6.8 Pharmacy Bills

**Navigation:** Billing → Pharmacy Bills

Handle pharmacy payments:
1. View pending pharmacy bills
2. Click **Collect Payment**
3. Process payment
4. Print receipt

### 6.9 Smart Billing

**Navigation:** Billing → Smart Billing

For quick billing of common services:
1. Select patient
2. Choose service template
3. Review items
4. Generate invoice

---

## 7. Pharmacist Guide

### 7.1 Your Dashboard

As a Pharmacist, your dashboard shows:
- Pending prescriptions
- Low stock alerts
- Expiring medications
- Today's dispensing count

### 7.2 Viewing Prescription Worklist

**Navigation:** Pharmacy → Worklist

Your worklist shows all pending prescriptions:

| Column | Meaning |
|--------|---------|
| Patient | Patient name |
| Drug | Medication prescribed |
| Qty | Quantity to dispense |
| Frequency | How often to take |
| Status | Pending/Partial/Dispensed |
| Payer | Cash/NHIS |
| Stock | Available stock |

### 7.3 Dispensing Medications

**Step-by-Step:**

1. Click **Pharmacy** → **Worklist**
2. Find the prescription
3. Check:
   - Payment status (must be paid for Cash patients)
   - Stock availability
4. Click **Dispense** button
5. Enter:
   - Quantity dispensing
   - Batch number
6. Click **Confirm**
7. Status changes to "Dispensed"

**For Partial Dispensing:**
1. Click **Partial** button
2. Enter quantity available
3. Click **Confirm**
4. Remaining shows for later

### 7.4 Marking Medication Unavailable

If a drug is out of stock:
1. Click **Unavailable** button
2. Enter reason
3. Click **Confirm**
4. Doctor will be notified

### 7.5 Stock Management

**Navigation:** Pharmacy → Stock Management

**Viewing Stock:**
- See all drugs with quantities
- Filter by category
- Search by name
- View low stock items

**Restocking:**
1. Find the drug
2. Click **Restock**
3. Enter:
   - Quantity
   - Batch number
   - Expiry date
   - Unit cost
   - Supplier
4. Click **Save**

### 7.6 Stock Adjustments

For corrections or write-offs:
1. Find the drug
2. Click **Adjust**
3. Select type:
   - Restock
   - Write-off
   - Correction
4. Enter quantity and reason
5. Click **Save**

### 7.7 Pharmacy Alerts

**Navigation:** Pharmacy → Alerts

View critical alerts:
- **Low Stock** - Below reorder level
- **Expiring Soon** - Within 30 days
- **Expired** - Past expiry date

**Actions:**
- Click **Restock** for low items
- Click **Remove** for expired items

### 7.8 Drug Search

When dispensing:
1. Type drug name in search
2. System shows matching drugs
3. See stock level and price
4. Select the correct drug

---

## 8. Laboratory Technician Guide

### 8.1 Your Dashboard

As a Lab Technician, your dashboard shows:
- Pending tests
- Urgent tests (highlighted)
- Completed today
- Pending results entry

### 8.2 Viewing Lab Requests

**Navigation:** Laboratory → Requests

View all pending lab requests:

| Column | Meaning |
|--------|---------|
| Patient | Patient name |
| Test | Test ordered |
| Doctor | Ordering doctor |
| Date | Order date |
| Priority | Normal/Urgent |
| Status | Pending/In Progress/Completed |

### 8.3 Processing Lab Tests

**Step-by-Step:**

1. Click **Laboratory** → **Requests**
2. Find the test request
3. Click **Process**
4. Collect sample (if not done)
5. Run the test
6. Enter results

### 8.4 Entering Lab Results

1. Find the completed test
2. Click **Enter Results**
3. Fill in result values:

| Field | Example |
|-------|---------|
| Test Parameter | Hemoglobin |
| Result | 12.5 |
| Unit | g/dL |
| Normal Range | 12-16 |
| Flag | Normal/High/Low |

4. Add comments if needed
5. Click **Save Results**

### 8.5 Validating Results

Before releasing:
1. Review all values
2. Check for errors
3. Click **Validate**
4. Results become visible to doctor

### 8.6 Printing Lab Reports

1. Find the completed test
2. Click **Print Report**
3. Report shows:
   - Patient details
   - Test results
   - Normal ranges
   - Lab technician name

### 8.7 Urgent Tests

Urgent tests are highlighted in **red**:
- Process these first
- Notify doctor immediately when done

---

## 9. Sonographer Guide

### 9.1 Your Dashboard

As a Sonographer, your dashboard shows:
- Pending scan requests
- Urgent scans
- Completed today
- Quick access to worklist

### 9.2 Viewing Scan Requests

**Navigation:** Sonography → Pending Scans

View all pending requests:

| Column | Meaning |
|--------|---------|
| Patient | Patient name |
| Scan Type | Type of scan |
| Indication | Clinical reason |
| Doctor | Requesting doctor |
| Priority | Normal/Urgent |
| Date | Request date |

### 9.3 Processing Scan Requests

**Step-by-Step:**

1. Click **Sonography** → **Pending Scans**
2. Find the request
3. Click **Start Scan**
4. Perform the examination
5. Enter findings

### 9.4 Entering Scan Results

1. After completing scan
2. Click **Enter Report**
3. Fill in findings:

| Section | What to Enter |
|---------|---------------|
| Findings | Detailed observations |
| Measurements | Size, dimensions |
| Impression | Summary/diagnosis |
| Recommendations | Follow-up if needed |

4. Click **Save Report**

### 9.5 Viewing Completed Scans

**Navigation:** Sonography → Completed Scans

View history of completed scans:
- Search by patient
- Filter by date range
- View previous reports

### 9.6 Printing Scan Reports

1. Find the completed scan
2. Click **Print Report**
3. Report includes:
   - Patient details
   - Clinical indication
   - Findings
   - Impression
   - Sonographer signature

---

## 10. Common Functions

### 10.1 Searching for Patients

Available to all roles:
1. Use the search box
2. Enter patient ID, name, or phone
3. Press Enter
4. Select from results

### 10.2 Viewing Patient Information

1. Find the patient
2. Click **View** button
3. See patient details:
   - Personal information
   - Visit history
   - Current status

### 10.3 Printing Documents

Most screens have print options:
1. Click **Print** button
2. Document opens in new window
3. Click browser's Print button
4. Select printer and print

### 10.4 Changing Your Password

1. Click your name (top-right)
2. Select **Profile**
3. Click **Change Password**
4. Enter current password
5. Enter new password (twice)
6. Click **Save**

### 10.5 Understanding Status Colors

| Color | Meaning |
|-------|---------|
| 🟢 Green | Completed/Paid/Available |
| 🟡 Yellow | Pending/Waiting |
| 🔴 Red | Urgent/Unpaid/Low Stock |
| 🔵 Blue | In Progress |
| ⚪ Gray | Inactive/Cancelled |

---

## 11. Troubleshooting

### 11.1 Common Problems

| Problem | Solution |
|---------|----------|
| Can't log in | Check username/password, caps lock |
| Page won't load | Refresh page, check internet |
| Can't save data | Check required fields (marked *) |
| Button not working | Refresh page, try again |
| Printer not working | Check printer connection |

### 11.2 Error Messages

| Error | What It Means | What to Do |
|-------|---------------|------------|
| "Access Denied" | No permission | Contact admin |
| "Session Expired" | Logged out | Log in again |
| "Required Field" | Missing data | Fill in the field |
| "Duplicate Entry" | Already exists | Search for existing |

### 11.3 Getting Help

If you need help:
1. Note the error message
2. Note what you were doing
3. Contact IT support
4. Provide screenshots if possible

### 11.4 Do's and Don'ts

**DO:**
- ✓ Log out when leaving
- ✓ Double-check patient identity
- ✓ Save work frequently
- ✓ Report problems immediately
- ✓ Keep passwords secure

**DON'T:**
- ✗ Share your login
- ✗ Leave screen unlocked
- ✗ Ignore error messages
- ✗ Skip required fields
- ✗ Access unauthorized areas

---

## Quick Reference Cards

### Receptionist Quick Reference

| Task | Navigation |
|------|------------|
| Register Patient | Patient Management → Add Patient |
| OPD Check-in | OPD → Registration |
| Search Patient | Patient Management → Search |
| Book Appointment | Appointments → New |

### Doctor Quick Reference

| Task | Navigation |
|------|------------|
| View Queue | OPD → Queue |
| Open EMR | EMR → OPD → Select Patient |
| Add Diagnosis | EMR → Diagnosis Tab |
| Write Prescription | EMR → Prescription Tab |
| Order Lab Test | EMR → Lab Orders Tab |

### Cashier Quick Reference

| Task | Navigation |
|------|------------|
| Create Invoice | Billing → POS |
| Collect Payment | Billing → Payment Collection |
| View Invoices | Billing → Billing List |
| Daily Report | Billing → Daily Collection |

### Pharmacist Quick Reference

| Task | Navigation |
|------|------------|
| View Prescriptions | Pharmacy → Worklist |
| Dispense Drug | Worklist → Dispense Button |
| Check Stock | Pharmacy → Stock Management |
| View Alerts | Pharmacy → Alerts |

### Lab Technician Quick Reference

| Task | Navigation |
|------|------------|
| View Requests | Laboratory → Requests |
| Enter Results | Request → Enter Results |
| Print Report | Results → Print |

### Sonographer Quick Reference

| Task | Navigation |
|------|------------|
| View Requests | Sonography → Pending Scans |
| Enter Report | Request → Enter Report |
| View History | Sonography → Completed Scans |

---

## Glossary

| Term | Definition |
|------|------------|
| **OPD** | Outpatient Department - patients who visit and leave same day |
| **IPD** | Inpatient Department - patients who are admitted |
| **EMR** | Electronic Medical Records - digital patient records |
| **NHIS** | National Health Insurance Scheme |
| **IOP ID** | In/Out Patient ID - unique visit identifier |
| **Queue** | List of patients waiting |
| **Worklist** | List of pending tasks |
| **Dispense** | Give medication to patient |
| **Vitals** | Basic health measurements (BP, pulse, etc.) |

---

*This manual is confidential and intended for authorized hospital staff only.*

**© 2026 Hospital Management System**
