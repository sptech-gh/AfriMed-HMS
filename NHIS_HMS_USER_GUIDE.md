# Hebrew Medical Center — HMS + NHIS Integration
## Complete System Guide: Installation to Daily Usage

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Installation & Setup](#2-installation--setup)
3. [First Login & Initial Configuration](#3-first-login--initial-configuration)
4. [Role-Based Usage Guide](#4-role-based-usage-guide)
   - [Administrator](#41-administrator)
   - [Receptionist](#42-receptionist)
   - [Doctor](#43-doctor)
   - [Nurse](#44-nurse)
   - [Billing / Accounts Officer](#45-billing--accounts-officer)
   - [Laboratory Technician](#46-laboratory-technician)
   - [Pharmacist](#47-pharmacist)
5. [NHIS-Specific Workflows](#5-nhis-specific-workflows)
   - [Patient NHIS Registration](#51-patient-nhis-registration)
   - [NHIS Billing & Co-Payment](#52-nhis-billing--co-payment)
   - [NHIS Drug & Service Coverage](#53-nhis-drug--service-coverage)
   - [NHIS Claims Lifecycle](#54-nhis-claims-lifecycle)
   - [Claims Dashboard & Reconciliation](#55-claims-dashboard--reconciliation)
   - [NHIS API Settings (MOCK/LIVE)](#56-nhis-api-settings-mocklive)
   - [NHIS Alerts & Notifications](#57-nhis-alerts--notifications)
6. [Common Workflows (Step-by-Step)](#6-common-workflows-step-by-step)
7. [Troubleshooting & FAQ](#7-troubleshooting--faq)
8. [Appendix: Database Tables & Config Keys](#8-appendix)

---

## 1. System Overview

**Hebrew Medical Center HMS** is a web-based Hospital Management System built on PHP (CodeIgniter 2), MySQL/MariaDB, and AdminLTE. It manages the full patient lifecycle:

- Patient registration & records
- OPD (Out-Patient) and IPD (In-Patient) visits
- Doctor consultations, diagnosis, prescriptions
- Laboratory orders & results
- Pharmacy / medication dispensing
- Billing, invoicing & payments
- Nursing care (vitals, progress notes, medication)
- Room / bed management
- Reporting & audit trails

**NHIS Integration** adds national health insurance support:

- NHIS member validation (card number, expiry)
- Dual pricing (NHIS rate vs. cash rate) for drugs, services, and imaging
- Automatic co-payment calculation (NHIS covers X%, patient pays remainder)
- Claims auto-generation on patient discharge
- Mock NHIS API for testing (simulates approval, underpayment, rejection)
- Claims dashboard with charts, filters, reconciliation
- Alerts for underpaid/rejected claims

### Technology Stack

| Component | Technology |
|-----------|-----------|
| Backend | PHP 7.x / CodeIgniter 2 |
| Database | MySQL 5.7+ / MariaDB 10.x |
| Frontend | AdminLTE + Bootstrap 3 + jQuery |
| Charts | Chart.js 4.x |
| Server | Apache (Laragon / XAMPP / WAMP) |

---

## 2. Installation & Setup

### Prerequisites

- **PHP 7.2+** with extensions: `mysqli`, `mbstring`, `json`, `gd`
- **MySQL 5.7+** or **MariaDB 10.x**
- **Apache** with `mod_rewrite` enabled
- A local server stack: **Laragon** (recommended), XAMPP, or WAMP

### Step-by-Step Installation

#### Step 1: Place the Project Files

Copy the `hms-master` folder into your web server's document root:

| Server | Path |
|--------|------|
| Laragon | `C:\laragon\www\hms-master\` |
| XAMPP | `C:\xampp\htdocs\hms-master\` |
| WAMP | `C:\wamp64\www\hms-master\` |
| Linux | `/var/www/html/hms-master/` |

#### Step 2: Create the Database

1. Open **phpMyAdmin** (usually at `http://localhost/phpmyadmin`)
2. Click **"New"** in the left sidebar
3. Database name: **`hms_master`**
4. Collation: **`utf8mb4_unicode_ci`**
5. Click **Create**

#### Step 3: Import the SQL File

1. Select the `hms_master` database
2. Click the **Import** tab
3. Choose file: `hms-master/hms_master.sql`
4. Click **Go** — wait for import to finish

#### Step 4: Configure Database Connection

Edit the file `application/config/database.php`:

```php
$db['default']['hostname'] = 'localhost';
$db['default']['username'] = 'root';        // your DB username
$db['default']['password'] = '';             // your DB password (empty for Laragon/XAMPP default)
$db['default']['database'] = 'hms_master';   // must match the database you created
```

#### Step 5: Configure Base URL

Edit `application/config/config.php`:

```php
$config['base_url'] = 'http://localhost/hms-master/';
```

> **Note:** Include the trailing slash. Adjust if your folder name differs.

#### Step 6: URL Rewriting (Apache)

If a `.htaccess` file does not exist in the project root, create one:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php/$1 [L]
```

Ensure Apache's `mod_rewrite` is enabled.

#### Step 7: Open in Browser

Navigate to: **`http://localhost/hms-master/`**

You should see the login page.

---

## 3. First Login & Initial Configuration

### Default User Accounts

| Role | Username | Password | Name |
|------|----------|----------|------|
| **Administrator** | `demo-hmsh` | `hospital` | Nana Kwesi Asante |
| **Doctor** | `doctor1` | `doctor1` | Kofi Agyemang Owusu |
| **Nurse** | `nurse1` | `nurse1` | Abena Darko Appiah |
| **Receptionist** | `receptionist1` | `receptionist1` | Efua Tetteh Osei |
| **Receptionist** | `reception` | *(unchanged)* | Ama Gyamfi Ankrah |
| **Pharmacy** | `pharmacy` | *(unchanged)* | Edwin Kofi Mensah |
| **Laboratory** | `lab` | *(unchanged)* | Kwabena Adu Nkrumah |

> **IMPORTANT:** Change all default passwords immediately after first login via **User Profile → Edit Profile**.

### First-Time Admin Tasks (Do These First)

1. **Log in as Administrator** (`demo-hmsh` / `hospital`)
2. Go to **Administrator → Company Information**
   - Set hospital name, address, phone, email
   - Upload hospital logo (main logo + header logo)
   - Save
3. Go to **Administrator → Department Master**
   - Add your hospital departments (General Medicine, Surgery, Pediatrics, etc.)
4. Go to **Administrator → Designation Master**
   - Add staff designations (Consultant, Resident, Registrar, etc.)
5. Go to **Administrator → Particular Bill Master**
   - Add your billing service items (Consultation, Lab Tests, Procedures)
   - For NHIS: check "NHIS Covered" and set NHIS price for applicable services
6. Go to **Administrator → Medicine Mgmt → Drug Name Master**
   - Add drugs to the formulary
   - For NHIS: check "NHIS Covered" and set NHIS price for covered drugs
7. Go to **Administrator → Insurance Company**
   - Add NHIS as an insurance company
8. Go to **Administrator → NHIS Claims**
   - Click **API Settings** (gear icon)
   - Review mock API rates (default: 70% approval, 15% underpay, 15% reject)
   - Leave mode as **MOCK** for testing

### Auto-Migration (No Manual SQL Needed)

The NHIS integration uses **automatic schema migration**. The first time you access any NHIS-related page, the system will:

- Add NHIS columns to existing tables (`patient_personal_info`, `iop_billing`, etc.)
- Create new tables (`nhis_claims`, `nhis_claim_lines`, `nhis_audit_log`, `nhis_api_config`, etc.)
- Insert default configuration values

**No manual SQL scripts are required.** Everything self-installs on first access.

---

## 4. Role-Based Usage Guide

### 4.1 Administrator

**Menu Access:** All modules

The Administrator has full access to the entire system. Key responsibilities:

#### Dashboard
- View hospital-wide statistics
- Monitor NHIS claims alerts (red badge in header)

#### User Management
- **Add New User:** Create accounts for doctors, nurses, billing staff, lab techs
- **User Masterlist:** View, edit, deactivate users
- **User Roles:** Define which pages/modules each role can access

#### Administrator Menu
| Menu Item | Purpose |
|-----------|---------|
| Company Information | Hospital name, logo, address, theme |
| Department Master | Manage hospital departments |
| Designation Master | Staff title/designations |
| Bill Group Name Master | Billing group categories |
| Particular Bill Master | Service items & prices (+ NHIS pricing) |
| Complain Master | Patient complaint categories |
| Diagnosis Master | ICD/diagnosis codes |
| Surgical Package | Pre-built surgical cost packages |
| Insurance Company | Insurance company master list |
| **NHIS Claims** | **Claims dashboard, submission, reconciliation** |
| Medicine Mgmt → Category Master | Drug categories |
| Medicine Mgmt → Drug Name Master | Drugs & NHIS pricing |
| Acknowledge Receipt | Payment acknowledgments |
| System Parameters | Global system settings |
| Backup Database | Database backup utility |
| System Pages | Page access configuration |

#### NHIS-Specific Admin Tasks
- Configure NHIS API mode (MOCK vs. LIVE) in **NHIS Claims → API Settings**
- Set mock simulation rates (approval %, underpay %, reject %)
- Review and reconcile claims
- Monitor underpaid/rejected claims via dashboard
- Mark drugs and services as NHIS-covered with dual pricing

---

### 4.2 Receptionist

**Menu Access:** Patient Management, Appointments

The receptionist is the first point of contact. They register patients and manage OPD visits.

#### Daily Workflow

1. **Register New Patient**
   - Go to **Patient Management → Add New Patient**
   - Fill in demographics (name, DOB, gender, phone, address)
   - Under **"Other Information"** tab:
     - Enter **NHIS Number** (e.g., `NHIS-123456789`)
     - Enter **NHIS Expiry Date**
   - Save — system auto-computes NHIS status (Active/Expired)

2. **Register OPD Visit**
   - Go to **Patient Management → OPD → OPD Registration**
   - Search for patient
   - Select attending doctor, department
   - Click **Save**
   - If the patient has an **expired NHIS card**, a yellow warning appears:
     > ⚠ NHIS card expired on [date]. Patient may not be covered.
   - If it's a **review visit** (within 14 days of last visit), a blue info message appears:
     > ℹ NHIS review visit — consultation fee waived per NHIS policy.

3. **Manage Appointments**
   - Go to **Patient Appointment → Add New Appointment**
   - Schedule date, time, doctor, patient
   - View all appointments in **Manage Appointment**

#### NHIS Tips for Receptionists
- Always verify NHIS number and expiry date at registration
- If NHIS card is expired, inform the patient — they may need to pay cash
- The NHIS badge appears on the patient profile and OPD views:
  - 🟢 **Active** — NHIS coverage valid
  - 🔴 **Expired** — card expired, needs renewal
  - 🟡 **Invalid** — NHIS number format issue

---

### 4.3 Doctor

**Menu Access:** Doctor Module (OPD/IPD), Patient views

Doctors manage clinical consultations, diagnosis, prescriptions, and lab orders.

#### Daily Workflow

1. **View Patient Queue**
   - Go to **Doctor Module → Out-Patient** (or In-Patient for IPD)
   - See list of patients assigned to you

2. **Open Patient Record**
   - Click on a patient to view their details
   - The top sidebar shows:
     - Patient demographics
     - **NHIS Badge** (Active/Expired/None)
     - NHIS Number
     - Current billing status

3. **Clinical Actions** (within patient view)

   | Tab | What You Do |
   |-----|-------------|
   | Complains | Record patient's presenting complaints |
   | Diagnosis | Add diagnoses (ICD codes) |
   | Medication | Prescribe drugs — **NHIS-covered drugs show green badge** |
   | Laboratory | Order lab tests — **NHIS-covered tests auto-priced** |
   | Sonography | Order imaging — NHIS rate applied if covered |
   | Treatment | Add treatments and procedures |
   | Referral | Refer patient to specialist |

4. **Prescribing Medication (NHIS)**
   - When you select a drug, the system checks NHIS coverage:
     - **Covered drugs** → NHIS price applied automatically
     - **Uncovered drugs** → Warning shown: "This drug is NOT covered by NHIS"
   - You can still prescribe uncovered drugs — patient will pay full cash price

5. **Ordering Lab Tests (NHIS)**
   - Lab tests marked as NHIS-covered in Particular Bill Master are auto-priced at NHIS rate
   - Payment gate: if patient hasn't paid and test isn't NHIS-covered, the order is blocked

6. **Discharge Patient**
   - Click **Discharge** button on the patient view
   - This triggers:
     - Visit marked as COMPLETED
     - **NHIS Claim auto-generated** (if patient is NHIS)
     - A green confirmation: "NHIS Claim Generated: CLM-20260321-0001"

---

### 4.4 Nurse

**Menu Access:** Nurse Module

Nurses handle bedside care, vitals, medication administration, and patient monitoring.

#### Available Pages

| Page | Purpose |
|------|---------|
| Patient Medication | View/administer prescribed medications |
| Intake/Output Record | Track fluid intake and output |
| Nurse Progress Note | Document patient progress notes |
| Vital Sign | Record vitals (BP, temp, pulse, SpO2, etc.) |
| Bed Side Procedure | Record bedside procedures |
| IP Room Transfer | Transfer inpatients between rooms |
| Patient History | View historical patient records |
| Discharge Summary | Prepare discharge summaries |
| Messages | Receive messages from doctors |
| Shift Tasks | View and manage shift tasks |

#### NHIS Relevance for Nurses
- The NHIS badge is visible on patient records — green for active, red for expired
- Medication lists show which drugs are NHIS-covered
- No direct NHIS claims interaction — claims are handled automatically by billing

---

### 4.5 Billing / Accounts Officer

**Menu Access:** Billing, NHIS Claims (via Admin menu)

The billing officer is central to NHIS operations — creating invoices, managing payments, and processing claims.

#### Daily Workflow

1. **Create Invoice**
   - Go to **Billing → Billing List** → Find the patient visit
   - Click **Create Invoice** or open existing invoice
   - The system automatically:
     - Detects if patient is NHIS or Cash
     - Shows **"NHIS Patient"** badge (green) or **"Cash Patient"** badge
     - Applies NHIS pricing for covered services
     - Calculates split: **NHIS Covered Amount** (green) vs **Patient Pays** (red)
   - Add line items (services, drugs, lab tests)
   - Each line automatically splits into NHIS portion and patient portion
   - Save/update invoice

2. **Invoice Display**
   - Footer shows:
     - **Total Amount:** Full invoice total
     - **NHIS Covered:** Amount NHIS will pay (green)
     - **Patient Pays:** Amount patient owes (red)
   - Print invoice shows the same breakdown

3. **NHIS Claims Dashboard** ⭐
   - Go to **Administrator → NHIS Claims**
   - This is your main claims management hub (see Section 5.5 for details)

4. **Submit Claims**
   - From the claims dashboard, click **Submit All Pending** to batch-submit
   - Or click the upload icon on individual claims
   - The mock API simulates NHIS responses (approval, partial payment, rejection)

5. **Reconcile Claims**
   - Click **Reconcile All** to compare submitted vs approved amounts
   - Review: MATCHED (✅), UNDERPAID (⚠️), REJECTED (❌)

---

### 4.6 Laboratory Technician

**Menu Access:** Laboratory Module

Lab technicians process lab orders and enter results.

#### Daily Workflow

1. **View Lab Orders**
   - Go to **Laboratory Module → Labs**
   - See all pending lab orders

2. **Process Lab Order**
   - Click on an order to view details
   - Enter lab results
   - Save and finalize

3. **Lab Enquiry**
   - Go to **Laboratory Module → Lab Enquiry**
   - Search for past results by patient, date, or test type

#### NHIS Relevance for Lab
- Lab tests that are NHIS-covered are auto-priced at NHIS rate when ordered
- No direct claims interaction needed
- If a lab test is ordered for a non-paying patient without NHIS coverage, the system blocks it with a warning

---

### 4.7 Pharmacist

**Menu Access:** Pharmacy

Pharmacists dispense medications prescribed by doctors.

#### Daily Workflow

1. **View Pending Prescriptions**
   - Go to **Pharmacy**
   - See all pending medication orders

2. **Dispense Medication**
   - Click on a prescription
   - Verify drug, dosage, and quantity
   - Mark as dispensed

#### NHIS Relevance for Pharmacy
- NHIS-covered drugs are clearly marked in prescriptions
- Drug prices auto-adjust: NHIS patients get NHIS rate, cash patients get standard rate
- Uncovered drugs show a warning at prescription time
- Payment gating: the system checks if the patient has paid before allowing dispensing (unless the drug is NHIS-covered)

---

## 5. NHIS-Specific Workflows

### 5.1 Patient NHIS Registration

**Who:** Receptionist or Admin

1. Go to **Patient Management → Add New Patient** (or edit existing)
2. Click the **"Other Information"** tab
3. Fill in:
   - **NHIS Number:** e.g., `NHIS-123456789`
   - **NHIS Expiry Date:** Select date using datepicker
4. Save

**What happens automatically:**
- System computes status: **ACTIVE** (not expired) or **EXPIRED** (past expiry)
- NHIS badge appears on all patient views
- All billing for this patient auto-detects NHIS status
- Changes are audited in `nhis_patient_audit` table

---

### 5.2 NHIS Billing & Co-Payment

**Who:** Billing Officer

The billing system automatically handles NHIS co-payment:

| Config Key | Default | Meaning |
|-----------|---------|---------|
| `nhis_subsidy_percent` | 100 | NHIS covers 100% of NHIS-priced items |
| `nhis_covers_lab` | 1 (Yes) | Lab tests covered by NHIS |
| `nhis_covers_pharmacy` | 1 (Yes) | Pharmacy drugs covered by NHIS |
| `consultation_fee_nhis` | 0.00 | NHIS consultation fee (0 = free) |
| `registration_fee_nhis` | 0.00 | NHIS registration fee (0 = free) |
| `nhis_review_days` | 14 | Days within which a return visit is "review" (no consult fee) |

**How it works:**
1. Service has cash price (e.g., GHS 50) and NHIS price (e.g., GHS 35)
2. NHIS patient → NHIS price is used (GHS 35)
3. Subsidy at 100% → NHIS pays GHS 35, patient pays GHS 0
4. If subsidy were 80% → NHIS pays GHS 28, patient pays GHS 7

---

### 5.3 NHIS Drug & Service Coverage

**Who:** Administrator (setup), Doctor/Pharmacist (usage)

#### Setting Up NHIS Coverage for Drugs

1. Go to **Administrator → Medicine Mgmt → Drug Name Master**
2. Click **Add** or **Edit** a drug
3. In the **NHIS Pricing** section:
   - Check ✅ **"NHIS Covered"**
   - Enter **NHIS Price** (the NHIS tariff rate)
   - **Cash Price** auto-fills from the standard price
4. Save

#### Setting Up NHIS Coverage for Services

1. Go to **Administrator → Particular Bill Master**
2. Click **Add** or **Edit** a billing item
3. In the **NHIS** section:
   - Check ✅ **"NHIS Covered"**
   - Enter **NHIS Charge Amount**
4. Save

#### How Coverage Is Applied

- When a doctor prescribes a drug or orders a service:
  - System checks if the item is NHIS-covered
  - If YES → NHIS price used for NHIS patients
  - If NO → standard cash price used, warning displayed
- Coverage badges appear:
  - 🟢 **"NHIS Covered"** — item is on the NHIS formulary
  - 🔴 **"Not NHIS Covered"** — patient pays full price

---

### 5.4 NHIS Claims Lifecycle

**Who:** System (auto) + Billing Officer (manual actions)

```
Patient Visit → Discharge → Claim Generated (PENDING)
                                    ↓
                            Submit to NHIS API
                                    ↓
                    ┌───────────────┼───────────────┐
                    ↓               ↓               ↓
               APPROVED        APPROVED         REJECTED
              (Full Pay)     (Partial Pay)    (With Reason)
                    ↓               ↓               ↓
               Reconcile       Reconcile       Reconcile
                    ↓               ↓               ↓
               MATCHED         UNDERPAID       REJECTED
                 (✅)            (⚠️)            (❌)
```

#### Detailed Steps:

1. **Claim Generation** (Automatic)
   - When a doctor discharges an NHIS patient, a claim is auto-generated
   - Claim includes: patient info, NHIS number, invoice lines, amounts
   - Status: **PENDING**
   - Claim reference format: `CLM-YYYYMMDD-NNNN`

2. **Claim Submission** (Manual trigger)
   - Billing officer goes to NHIS Claims dashboard
   - Clicks **"Submit All Pending"** or submits individual claims
   - The system calls the NHIS API (mock or live)
   - Mock API simulates: 70% full approval, 15% partial payment, 15% rejection

3. **API Response**
   - **Approved (Full):** Approved amount = claimed amount
   - **Approved (Partial):** Approved amount < claimed amount (underpayment)
   - **Rejected:** Reason given (expired membership, service not covered, etc.)

4. **Reconciliation** (Automatic or manual)
   - After submission, reconciliation runs automatically
   - Compares claimed amount vs. approved amount
   - Sets reconciliation status:
     - **MATCHED** — amounts match
     - **UNDERPAID** — approved < claimed (shortfall calculated)
     - **REJECTED** — claim was rejected
     - **OVERPAID** — approved > claimed (rare)

---

### 5.5 Claims Dashboard & Reconciliation

**Who:** Billing Officer, Administrator

**URL:** `http://localhost/hms-master/app/nhis_claims`

**Navigation:** Sidebar → Administrator → **NHIS Claims**

#### Dashboard Sections

| Section | What It Shows |
|---------|--------------|
| **Alert Banner** | Yellow bar with count of underpaid + rejected claims |
| **API Mode** | Current mode: MOCK (testing) or LIVE (production) |
| **Summary Stats** | 4 boxes: Total Claims, Total Claimed (GHS), Total Approved (GHS), Shortfall (GHS) |
| **Status Breakdown** | 4 boxes: Pending, Submitted, Approved, Rejected counts |
| **Pie Chart** | Visual distribution of claim statuses |
| **Line Chart** | Claims count and amount over the last 30 days |
| **Reconciliation Summary** | Matched, Underpaid, Rejected, Pending Recon counts |
| **Filter Panel** | Filter by: date range, claim status, recon status, amount range |
| **Claims Table** | Sortable, searchable table of all claims with actions |

#### Action Buttons

| Button | Effect |
|--------|--------|
| **Submit All Pending (N)** | Submits all PENDING claims to NHIS API in bulk |
| **Reconcile All** | Runs reconciliation on all unreconciled claims |
| **API Settings** | Opens NHIS API configuration page |

#### Claims Table Columns

| Column | Description |
|--------|-------------|
| Claim Ref | Unique claim reference (clickable → detail view) |
| Patient | Patient name |
| NHIS # | Patient's NHIS number |
| Total | Total invoice amount |
| NHIS Amt | Amount claimed from NHIS |
| Approved | Amount NHIS approved (or "—" if pending) |
| Status | PENDING / SUBMITTED / APPROVED / REJECTED |
| Recon | MATCHED / UNDERPAID / REJECTED / — |
| Date | Claim creation date |
| Actions | View (👁), Submit (⬆) buttons |

#### Claim Detail View

Click any claim reference to see:
- Full claim information (patient, visit, invoice, dates)
- Financial summary (claimed vs. approved vs. shortfall)
- Reconciliation status and notes
- Rejection reason (if rejected)
- Line items (each service/drug with NHIS covered amount)
- Raw API response (collapsible, for debugging)
- Action buttons: Submit, Re-Reconcile, Back

---

### 5.6 NHIS API Settings (MOCK/LIVE)

**Who:** Administrator only

**URL:** `http://localhost/hms-master/app/nhis_claims/settings`

**Navigation:** NHIS Claims Dashboard → **API Settings** (gear icon)

#### API Mode

| Mode | Purpose |
|------|---------|
| **MOCK** | Simulated NHIS responses for testing and training. No real API calls. |
| **LIVE** | Connects to real NHIS API. Requires valid API URL and key. |

> **Start with MOCK mode.** Switch to LIVE only when you have real NHIS API credentials.

#### Mock Simulation Settings

| Setting | Default | Description |
|---------|---------|-------------|
| Approval Rate | 70% | Percentage of claims fully approved |
| Underpayment Rate | 15% | Percentage of claims partially paid |
| Rejection Rate | 15% | Percentage of claims rejected |
| Simulated Delay | 500ms | Artificial delay to mimic real API latency |

> Rates must sum to 100%. The visual progress bar shows the distribution.

#### Mock Rejection Reasons (Random)

When a claim is rejected in MOCK mode, one of these reasons is randomly selected:
- NHIS membership expired
- Service not covered under NHIS
- Duplicate claim submission
- Incomplete patient information
- Authorization not obtained
- Provider not accredited for this service

#### Live API Configuration

| Field | Description |
|-------|-------------|
| API Base URL | The NHIS API endpoint (e.g., `https://api.nhis.gov.gh/v1`) |
| API Key | Authentication key from NHIS |

> **Note:** The LIVE API is a placeholder — when your facility receives real NHIS API credentials, enter them here and switch to LIVE mode. The system will then make real HTTP calls to the NHIS API.

#### Switching Modes

- Switching between MOCK and LIVE **does not affect existing claims**
- Only new submissions use the selected mode
- Each claim records which mode was used (`api_mode` column)
- You can freely switch back and forth for testing vs. production

---

### 5.7 NHIS Alerts & Notifications

**Who:** All users (visible), Billing Officer (actionable)

#### Header Badge
- A red **medkit icon** with a number appears in the top navigation bar
- Shows total count of underpaid + rejected claims
- Click it to go directly to the NHIS Claims dashboard
- Only appears when there are alerts (hidden when zero)

#### Sidebar Badge
- The **NHIS Claims** menu item in the Administrator sidebar shows a red badge count
- Same count as the header badge

#### Dashboard Alert Banner
- Yellow warning bar at the top of the claims dashboard
- Shows: "NHIS Alerts: **3 Underpaid** **2 Rejected** — Review claims requiring attention."

#### What to Do About Alerts

| Alert Type | Action |
|------------|--------|
| **Underpaid** | Review the shortfall amount. Contact NHIS or adjust patient billing. |
| **Rejected** | Read the rejection reason. Fix the issue (e.g., renew NHIS card) and resubmit. |

---

## 6. Common Workflows (Step-by-Step)

### Workflow A: New NHIS Patient — Full Visit

```
RECEPTIONIST                 DOCTOR                    BILLING OFFICER
     │                          │                            │
     ├─ Register patient         │                            │
     │  (enter NHIS # + expiry)  │                            │
     │                          │                            │
     ├─ Register OPD visit       │                            │
     │  (system checks NHIS)    │                            │
     │                          │                            │
     │                     ├─ Open patient record              │
     │                     │  (sees NHIS Active badge)        │
     │                     │                                  │
     │                     ├─ Add diagnosis                    │
     │                     ├─ Prescribe drugs                  │
     │                     │  (NHIS prices applied)           │
     │                     ├─ Order lab tests                  │
     │                     │  (NHIS prices applied)           │
     │                     │                                  │
     │                     ├─ Discharge patient                │
     │                     │  (claim auto-generated) ─────────┤
     │                          │                       │
     │                          │                  ├─ View invoice
     │                          │                  │  (NHIS split shown)
     │                          │                  │
     │                          │                  ├─ Submit claim to NHIS
     │                          │                  │  (from Claims Dashboard)
     │                          │                  │
     │                          │                  ├─ Reconcile claim
     │                          │                  │  (auto or manual)
     │                          │                  │
     │                          │                  └─ Review result
     │                          │                     (Matched/Underpaid/Rejected)
```

### Workflow B: Submitting Claims in Bulk (End of Day)

1. Log in as **Billing Officer** or **Administrator**
2. Go to **Administrator → NHIS Claims**
3. Review the dashboard:
   - Check **Pending** count
   - Review any alerts from previous submissions
4. Click **"Submit All Pending (N)"** — confirm when prompted
5. Wait for bulk submission to complete
6. System shows: "Bulk submission complete: X submitted, Y failed"
7. Review results:
   - Green labels = APPROVED
   - Yellow labels = UNDERPAID (check amounts)
   - Red labels = REJECTED (read reasons)
8. Click **"Reconcile All"** to finalize reconciliation
9. Address underpaid/rejected claims as needed

### Workflow C: Handling a Rejected Claim

1. See rejected claim on dashboard (red label)
2. Click claim reference to view details
3. Read **Rejection Reason** (red box)
4. Take corrective action:
   - **"NHIS membership expired"** → Ask patient to renew card, update in system
   - **"Service not covered"** → Convert to cash billing
   - **"Incomplete patient info"** → Update patient record, resubmit
   - **"Duplicate claim"** → No action needed (already processed)
5. If applicable, click **"Re-Reconcile"** after corrections

### Workflow D: Setting Up NHIS Drug Pricing

1. Log in as **Administrator**
2. Go to **Administrator → Medicine Mgmt → Drug Name Master**
3. Click **Edit** on a drug (e.g., "Amoxicillin 500mg")
4. Scroll to **NHIS Pricing** section
5. Check ✅ **"NHIS Covered"**
6. Enter **NHIS Price**: e.g., `5.00` (GHS)
7. **Cash Price** auto-fills from standard price: e.g., `8.00` (GHS)
8. Save
9. Now when a doctor prescribes Amoxicillin to an NHIS patient:
   - Price used = GHS 5.00 (NHIS rate)
   - NHIS pays GHS 5.00, patient pays GHS 0.00 (at 100% subsidy)

---

## 7. Troubleshooting & FAQ

### Installation Issues

| Problem | Solution |
|---------|----------|
| Blank white page | Enable PHP error reporting: edit `index.php`, set `error_reporting(E_ALL)` |
| 404 on all pages | Enable `mod_rewrite` in Apache. Add `.htaccess` file (see Step 6). |
| Database error | Check `application/config/database.php` credentials. Ensure DB exists and is imported. |
| CSS/JS not loading | Check `base_url` in `application/config/config.php`. Must match your actual URL. |
| "Unable to connect to database" | Verify MySQL is running. Check hostname, username, password. |

### NHIS Issues

| Problem | Solution |
|---------|----------|
| NHIS badge not showing | Ensure NHIS Number and Expiry Date are filled in patient record |
| Prices not changing for NHIS patients | Mark items as NHIS Covered in Drug Name Master / Particular Bill Master |
| Claim not generated on discharge | Patient must have payer_type = NHIS (valid NHIS info) and active invoice |
| "No claims found" on dashboard | Claims only generate when NHIS patients are discharged. Try a test discharge. |
| API Settings page blocked | Only Administrators can access API Settings |
| Reconciliation shows all "Pending" | Claims must be submitted first, then reconciled |
| Alert badge not showing | Alerts only appear when there are underpaid or rejected claims |
| Mock API always approves | Check mock rates in API Settings — approval rate may be set to 100% |

### General FAQ

**Q: Can I use the system without NHIS?**
A: Yes. NHIS features are additive. Cash patients are handled normally. NHIS features only activate for patients with valid NHIS data.

**Q: Will MOCK mode affect real patient data?**
A: No. MOCK mode simulates API responses but all claims are stored as real records. The `api_mode` column tracks whether a claim was processed in MOCK or LIVE mode.

**Q: Can I switch from MOCK to LIVE mid-operation?**
A: Yes. Existing claims keep their original mode. Only new submissions use the current mode.

**Q: How do I add more users?**
A: Go to **User Management → Add New User**. Select role, department, and module. The user's sidebar and permissions are determined by their role.

**Q: How do I back up the database?**
A: Go to **Administrator → Backup Database** and click the backup button. Alternatively, use phpMyAdmin to export `hms_master`.

**Q: Is there an audit trail?**
A: Yes. All NHIS-related changes are logged in `nhis_audit_log` and `nhis_patient_audit` tables. This includes claim generation, status changes, API submissions, and settings changes.

---

## 8. Appendix

### NHIS Database Tables

| Table | Purpose |
|-------|---------|
| `nhis_claims` | Main claims table (one row per patient visit) |
| `nhis_claim_lines` | Claim line items (services, drugs) |
| `nhis_audit_log` | Audit trail for all NHIS events |
| `nhis_patient_audit` | Audit trail for patient NHIS info changes |
| `nhis_billing_config` | Billing configuration (subsidy %, review days, etc.) |
| `nhis_api_config` | API configuration (mode, rates, credentials) |

### NHIS Columns on Existing Tables

| Table | Added Columns |
|-------|--------------|
| `patient_personal_info` | `nhis_number`, `nhis_status`, `nhis_expiry_date` |
| `iop_billing` | `payer_type`, `nhis_covered_amount`, `patient_payable_amount` |
| `iop_billing_t` | `payer_type`, `nhis_covered_amount`, `patient_payable_amount` |
| `medicine_drug_name` | `is_nhis_covered`, `nhis_price`, `cash_price` |
| `bill_particular` | `is_nhis_covered`, `nhis_charge_amount` |
| `sonography_items` | `is_nhis_covered`, `nhis_rate` |

### NHIS Configuration Keys

#### Billing Config (`nhis_billing_config`)

| Key | Default | Description |
|-----|---------|-------------|
| `registration_fee_nhis` | 0.00 | Registration fee for NHIS patients |
| `consultation_fee_nhis` | 0.00 | Consultation fee for NHIS patients |
| `nhis_subsidy_percent` | 100 | % of NHIS-priced items covered by NHIS |
| `nhis_review_days` | 14 | Days for review visit window |
| `nhis_covers_lab` | 1 | Whether NHIS covers lab tests |
| `nhis_covers_pharmacy` | 1 | Whether NHIS covers pharmacy |

#### API Config (`nhis_api_config`)

| Key | Default | Description |
|-----|---------|-------------|
| `api_mode` | MOCK | Current API mode |
| `api_base_url` | (empty) | Live API endpoint URL |
| `api_key` | (empty) | Live API authentication key |
| `mock_approval_rate` | 70 | % of mock claims fully approved |
| `mock_underpay_rate` | 15 | % of mock claims partially paid |
| `mock_reject_rate` | 15 | % of mock claims rejected |
| `mock_delay_ms` | 500 | Simulated API response delay |

### Claim Status Values

| Status | Meaning |
|--------|---------|
| `PENDING` | Claim generated, not yet submitted |
| `SUBMITTED` | Sent to NHIS API, awaiting response |
| `APPROVED` | NHIS accepted the claim |
| `REJECTED` | NHIS rejected the claim |

### Reconciliation Status Values

| Status | Meaning |
|--------|---------|
| `MATCHED` | Approved amount equals claimed amount |
| `UNDERPAID` | Approved amount is less than claimed |
| `REJECTED` | Claim was rejected by NHIS |
| `OVERPAID` | Approved amount exceeds claimed (rare) |
| `PENDING` | Not yet reconciled |

---

*Document generated: March 21, 2026*
*System version: HMS + NHIS Integration (Days 1–5)*
*For technical support, contact the system administrator.*
