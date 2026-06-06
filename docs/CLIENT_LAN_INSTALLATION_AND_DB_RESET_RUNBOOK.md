# HMS — Client LAN Installation + NHIS/Claim‑IT Runbook

This document describes how to install HMS on a **Windows LAN server** (Laragon) and connect it to **NHIS/Claim‑IT** when Claim‑IT is installed on the **same server PC**.

It also includes the approved procedure to **reset the database** to clear all **patient and transactional records** while preserving:

- Users and roles
- Master catalogs (billing items, drugs, tests, departments)
- System configuration

---

## 0) Deployment Topology (Assumed)

- **HMS Server PC**
  - Laragon (Apache + MySQL/MariaDB + PHP)
  - HMS codebase
  - Claim‑IT desktop app
- **Workstations**
  - Access HMS via browser over the LAN

Record these values before starting:

- **Server IP (static recommended)**: `192.168.0.136` (example)
- **HMS URL**: `http://<SERVER_IP>/hms-master/`
- **Claim‑IT Port**: `31719`

---

## 1) Install Prerequisites (HMS Server PC)

### 1.1 Install Laragon

- Install **Laragon Full** from https://laragon.org/download/
- Install to:

```
C:\laragon
```

### 1.2 Start Services

- Open Laragon
- Click **Start All**

---

## 2) Deploy the HMS Source Code

Copy the HMS project folder to:

```
C:\laragon\www\hms-master
```

Confirm you have:

- `C:\laragon\www\hms-master\index.php`
- `C:\laragon\www\hms-master\application\`
- `C:\laragon\www\hms-master\system\`
- `C:\laragon\www\hms-master\.htaccess`

---

## 3) Database Setup (Import baseline, then wipe patient data)

### 3.1 Create Database

In phpMyAdmin (Laragon menu → phpMyAdmin):

```sql
CREATE DATABASE hms_prod CHARACTER SET utf8 COLLATE utf8_general_ci;
```

### 3.2 Import Baseline Database

Import your baseline SQL (recommended if available):

- `C:\laragon\www\hms-master\hms_master.sql`

phpMyAdmin steps:

- Select database `hms_prod`
- Import → Choose file → Go

### 3.3 Reset/Wipe Patient and Transaction Data (Preserve users + masters)

Use the approved cleanup script:

- `C:\laragon\www\hms-master\sql\demo_cleanup.sql`

phpMyAdmin steps:

- Select database `hms_prod`
- Import → Choose `sql/demo_cleanup.sql` → Go

This script is intended to clear patient + transactional tables while preserving users and master catalogs.

### 3.4 Verify Reset

Run:

```sql
SELECT COUNT(*) AS patients FROM patient_personal_info;
SELECT COUNT(*) AS visits FROM patient_details_iop;
SELECT COUNT(*) AS invoices FROM invoice;
SELECT COUNT(*) AS lab_requests FROM iop_laboratory;
```

Expected:

- `patients = 0`
- `visits = 0`
- `invoices = 0`
- `lab_requests = 0`

---

## 4) File-System Patient Data Wipe (Attachments/PDFs)

After DB reset, also remove any stored patient files on disk.

Delete the **contents** (do not delete the folder itself) of:

- `C:\laragon\www\hms-master\public\patient_attachment\`
- `C:\laragon\www\hms-master\public\lab_results\`

---

## 5) Configure HMS for LAN Base URL + Claim‑IT

### 5.1 Create/Update `.env`

Create or edit:

```
C:\laragon\www\hms-master\.env
```

Example configuration:

```bash
# LAN base URL
APP_BASE_URL=http://192.168.0.136/hms-master/

# NHIS / Claim-IT
NHIS_MODE=live
CLAIMIT_HOST=192.168.0.136
CLAIMIT_PORT=31719
CLAIMIT_USERNAME=YOUR_USERNAME
CLAIMIT_PASSWORD=YOUR_PASSWORD
NHIS_FACILITY_CODE=YOUR_FACILITY_CODE
NHIS_API_TIMEOUT=30
NHIS_API_CONNECT_TIMEOUT=10
```

Notes:

- Because Claim‑IT is installed on the **same server PC**, use the **server IP** for `CLAIMIT_HOST`.
- Keep `.env` private (do not share publicly).

### 5.2 Configure Database Connection

Edit:

- `C:\laragon\www\hms-master\application\config\database.php`

Ensure:

- Host: `localhost`
- Database: `hms_prod`
- Username/password: match MySQL credentials

---

## 6) Firewall / LAN Access

### 6.1 Allow HTTP (HMS)

Allow inbound:

- TCP **80** (Apache)

### 6.2 Allow Claim‑IT API

Allow inbound:

- TCP **31719**

Do this on the HMS server PC in **Windows Defender Firewall** (Private network).

---

## 7) LAN Validation & Claim‑IT Connectivity Tests

### 7.1 Test URLs

From the server PC and at least one workstation:

- HMS:

```
http://192.168.0.136/hms-master/
```

- LAN test page:

```
http://192.168.0.136/hms-master/public/lan_test.php
```

- Claim‑IT test page:

```
http://192.168.0.136/hms-master/claimit_lan_test.php
```

### 7.2 What “PASS” looks like

- Workstations can reach the login page
- CSS/JS loads correctly (no broken styling)
- Login works
- Claim‑IT test reports connectivity

---

## 8) First Login / Operational Setup

- Login to HMS
- Change default admin password immediately
- Create staff users (cashier, doctor, lab, nurse, admin)
- Confirm master catalogs exist (billing particulars, drugs, tests)

---

## 9) Repeatable “Go‑Live Reset” Procedure (Preserve users + masters)

When preparing for a clean launch (wipe patient records again):

1. (Recommended) Take a DB backup
2. Run `sql/demo_cleanup.sql` on `hms_prod`
3. Clear:
   - `public/patient_attachment/*`
   - `public/lab_results/*`

---

## References

- `LAN_DEPLOYMENT_GUIDE.md`
- `DEPLOYMENT_GUIDE.md`
- `sql/demo_cleanup.sql`
- `database_init.sql`
