# Hebrew Medical Center HMS - Deployment Guide

## Client Local Server Installation (Laragon)

---

## Prerequisites

### 1. Laragon Installation
- Download Laragon Full (https://laragon.org/download/)
- Install Laragon to `C:\laragon`
- Laragon includes: Apache/Nginx, MySQL/MariaDB, PHP, phpMyAdmin

### 2. Required PHP Extensions (Laragon Default)
- mysqli
- gd
- mbstring
- openssl
- curl
- json
- session

---

## Step-by-Step Deployment

### STEP 1: Prepare Source Files

1. **Copy HMS files to Laragon www folder:**
   ```
   Source: Your hms-master folder
   Destination: C:\laragon\www\hms-master
   ```

2. **Verify folder structure:**
   ```
   C:\laragon\www\hms-master\
   ├── application\
   ├── docs\
   ├── public\
   ├── system\
   ├── index.php
   └── .htaccess
   ```

---

### STEP 2: Database Setup (Fresh Database)

#### Option A: Using Laragon's MySQL

1. **Start Laragon services:**
   - Open Laragon
   - Click "Start All"

2. **Open phpMyAdmin:**
   - Right-click Laragon tray icon
   - phpMyAdmin → http://localhost/phpmyadmin
   - Login: root (no password by default)

3. **Create Database:**
   ```sql
   CREATE DATABASE hms_prod CHARACTER SET utf8 COLLATE utf8_general_ci;
   ```

4. **Create Application User (Recommended for security):**
   ```sql
   CREATE USER 'hms_user'@'localhost' IDENTIFIED BY 'YourSecurePassword123';
   GRANT ALL PRIVILEGES ON hms_prod.* TO 'hms_user'@'localhost';
   FLUSH PRIVILEGES;
   ```

#### Option B: Using HeidiSQL (Included with Laragon)

1. Right-click Laragon → HeidiSQL
2. Session: localhost
3. User: root
4. Create database `hms_prod`

---

### STEP 3: Configure Database Connection

**File to edit:** `C:\laragon\www\hms-master\application\config\database.php`

```php
$active_group = 'default';
$active_record = TRUE;

$db['default']['hostname'] = 'localhost';
$db['default']['username'] = 'hms_user';        // or 'root' for simple setup
$db['default']['password'] = 'YourSecurePassword123';  // or '' for root
$db['default']['database'] = 'hms_prod';
$db['default']['dbdriver'] = 'mysqli';
$db['default']['dbprefix'] = '';
$db['default']['pconnect'] = FALSE;
$db['default']['db_debug'] = FALSE;  // Set to FALSE for production
$db['default']['cache_on'] = FALSE;
$db['default']['cachedir'] = '';
$db['default']['char_set'] = 'utf8';
$db['default']['dbcollat'] = 'utf8_general_ci';
$db['default']['swap_pre'] = '';
$db['default']['autoinit'] = TRUE;
$db['default']['stricton'] = FALSE;
```

---

### STEP 4: Configure Base URL

**File to edit:** `C:\laragon\www\hms-master\application\config\config.php`

```php
$config['base_url'] = 'http://localhost/hms-master/';

// If using Laragon's pretty URLs:
// $config['base_url'] = 'http://hms-master.test/';
```

**For Production/Client Site:**
```php
$config['base_url'] = 'http://192.168.1.100/hms-master/';  // Replace with actual IP
// OR
$config['base_url'] = 'http://hms.clienthospital.local/';
```

---

### STEP 5: Configure CSRF & Security

**File to edit:** `C:\laragon\www\hms-master\application\config\config.php`

```php
// Enable CSRF protection for production
$config['csrf_protection'] = TRUE;
$config['csrf_token_name'] = 'hms_csrf_token';
$config['csrf_cookie_name'] = 'hms_csrf_cookie';
$config['csrf_expire'] = 7200;
$config['csrf_regenerate'] = TRUE;

// Exclude specific URIs from CSRF (if needed)
$config['csrf_exclude_uris'] = array(
    'nhis_mock_api/.*',
    'login',
    'login/validate_login'
);

// Encryption key (CHANGE THIS for production)
$config['encryption_key'] = 'YourRandom32CharKeyHere12345678!';
```

---

### STEP 6: Configure Session

**File to edit:** `C:\laragon\www\hms-master\application\config\config.php`

```php
$config['sess_cookie_name']     = 'hms_session';
$config['sess_expiration']      = 7200;  // 2 hours
$config['sess_expire_on_close'] = FALSE;
$config['sess_encrypt_cookie']  = TRUE;
$config['sess_use_database']    = TRUE;  // Store sessions in DB
$config['sess_table_name']      = 'ci_sessions';
$config['sess_match_ip']        = FALSE;
$config['sess_match_useragent'] = TRUE;
$config['sess_time_to_update']  = 300;
```

---

### STEP 7: Configure Error Logging (Production)

**File to edit:** `C:\laragon\www\hms-master\index.php`

```php
// Line 1: Disable error display for production
define('ENVIRONMENT', 'production');

// Or for initial setup/debugging:
// define('ENVIRONMENT', 'development');
```

**Production settings:**
```php
error_reporting(0);
ini_set('display_errors', 0);
```

---

### STEP 8: Initialize Database Schema

The system uses auto-migration - tables are created on first run. However, you need a minimal schema:

**Create base tables manually** or run this SQL first:

```sql
-- Users table (required for login)
CREATE TABLE IF NOT EXISTS `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `firstname` varchar(50) DEFAULT NULL,
  `lastname` varchar(50) DEFAULT NULL,
  `user_role` varchar(50) DEFAULT 'cashier',
  `InActive` tinyint(1) DEFAULT 0,
  `module` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- Insert default admin user
INSERT INTO `users` (`username`, `password`, `firstname`, `lastname`, `user_role`, `module`) 
VALUES ('admin', MD5('admin123'), 'System', 'Administrator', 'Super Admin', 'super_admin');

-- Session table (for CodeIgniter sessions)
CREATE TABLE IF NOT EXISTS `ci_sessions` (
  `session_id` varchar(40) NOT NULL DEFAULT '0',
  `ip_address` varchar(45) NOT NULL DEFAULT '0',
  `user_agent` varchar(120) NOT NULL,
  `last_activity` int(10) unsigned NOT NULL DEFAULT 0,
  `user_data` text NOT NULL,
  PRIMARY KEY (`session_id`),
  KEY `last_activity_idx` (`last_activity`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
```

---

### STEP 9: Configure .htaccess (Apache)

**File:** `C:\laragon\www\hms-master\.htaccess`

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /hms-master/
    
    # Redirect Trailing Slashes...
    RewriteRule ^(.*)/$ /$1 [L,R=301]
    
    # Handle Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>

# Protect sensitive files
<FilesMatch "^\.">
    Order allow,deny
    Deny from all
</FilesMatch>

# Disable directory browsing
Options -Indexes

# PHP settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
```

---

### STEP 10: Folder Permissions

**Set write permissions for:**

```
C:\laragon\www\hms-master\application\cache        --> Writable
C:\laragon\www\hms-master\application\logs         --> Writable
C:\laragon\www\hms-master\public\patient_attachment --> Writable
C:\laragon\www\hms-master\public\lab_results         --> Writable
```

**Windows (CMD as Administrator):**
```cmd
cd C:\laragon\www\hms-master
icacls application\cache /grant Everyone:F
icacls application\logs /grant Everyone:F
icacls public\patient_attachment /grant Everyone:F
icacls public\lab_results /grant Everyone:F
```

---

### STEP 11: Access the Application

1. **Start Laragon services** (if not already running)

2. **Access HMS:**
   ```
   http://localhost/hms-master/
   ```

3. **Login with default credentials:**
   ```
   Username: admin
   Password: admin123
   ```

4. **Change default password immediately after first login!**

---

## Post-Installation Configuration

### 1. System Settings (First Login)

Navigate to: **Administration → System Settings**

Configure:
- Hospital Name
- Address & Contact
- Currency (GHS for Ghana)
- Date Format
- Time Zone (Africa/Accra)

### 2. Create User Accounts

Navigate to: **Administration → User Management**

Create at minimum:
- 1 Admin user
- 1 Cashier user
- 1 Doctor user (for testing)

### 3. Configure Departments

Navigate to: **Administration → Departments**

Add:
- OPD
- IPD
- Laboratory
- Pharmacy
- Radiology
- etc.

### 4. Configure Billing Particulars

Navigate to: **Administration → Billing → Particular Bills**

Add service charges with:
- NHIS prices (if applicable)
- Cash prices

### 5. Configure Drug List

Navigate to: **Pharmacy → Drug Names**

Add medications with:
- NHIS coverage status
- NHIS price
- Cash price

### 6. NHIS Configuration (If Applicable)

Navigate to: **NHIS → Coverage Management**

- Configure G-DRG codes
- Map services to NHIS tariffs
- Set up ICD-10 codes

---

## Verification Checklist

### Core Functionality
- [ ] Login/Logout works
- [ ] Password change works
- [ ] Session timeout works
- [ ] CSRF protection active (try form submission)

### Patient Module
- [ ] Register new patient
- [ ] Search patient
- [ ] Update patient info
- [ ] Upload patient attachment

### OPD Module
- [ ] OPD registration
- [ ] Doctor consultation
- [ ] Order lab tests
- [ ] Prescribe medication
- [ ] Diagnosis entry

### Billing Module
- [ ] Create bill
- [ ] Search bills
- [ ] Collect payment
- [ ] Generate receipt
- [ ] View billing history

### Laboratory Module
- [ ] View lab queue
- [ ] Enter lab results
- [ ] Verify results
- [ ] View lab reports

### Pharmacy Module
- [ ] View prescriptions
- [ ] Dispense medication
- [ ] Stock management

### NHIS Integration (If Enabled)
- [ ] Check NHIS member eligibility
- [ ] Generate NHIS claim
- [ ] Submit to Claim-IT

---

## Troubleshooting

### Issue: "404 Not Found"
**Solution:**
- Check `.htaccess` exists
- Verify Apache mod_rewrite is enabled
- Check `base_url` in config.php

### Issue: "Database Connection Error"
**Solution:**
- Verify database.php credentials
- Check MySQL is running (Laragon)
- Verify database exists

### Issue: "CSRF Token Error"
**Solution:**
- Clear browser cookies
- Refresh page
- Check `$config['csrf_protection']` setting

### Issue: "500 Internal Server Error"
**Solution:**
- Check `application/logs/` for errors
- Verify PHP version compatibility (5.6+)
- Check file permissions

### Issue: Sessions Not Working
**Solution:**
- Verify `ci_sessions` table exists
- Check `$config['sess_use_database']` = TRUE
- Clear browser cookies

---

## Backup Strategy

### Database Backup (Daily)
```cmd
C:\laragon\bin\mysql\mysql-8.0.30-winx64\bin\mysqldump -u root hms_prod > C:\backups\hms_backup_%date:~-4,4%%date:~-10,2%%date:~-7,2%.sql
```

### Files Backup
- Backup entire `C:\laragon\www\hms-master\` folder
- Exclude: `application/cache/*`, `application/logs/*`

---

## Security Hardening

### 1. Change Default Passwords
- Admin account
- Database root password
- Any test accounts

### 2. Disable Debug Mode
```php
// index.php
define('ENVIRONMENT', 'production');
```

### 3. Secure Database
```sql
-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Remove test database
DROP DATABASE IF EXISTS test;
FLUSH PRIVILEGES;
```

### 4. Enable HTTPS (If Available)
```php
// config.php
$config['base_url'] = 'https://your-domain.com/';
```

---

## Support & Documentation

### System Documentation
- See `docs/` folder in hms-master
- NHIS Integration Guide: `docs/NHIS_INTEGRATION.md`
- User Manual: Contact developer

### Emergency Contacts
- System Developer: [Your Contact]
- Database Admin: [Client IT Contact]

---

## License & Warranty

This HMS is licensed for use at [Client Hospital Name].

**Deployment Date:** _______________
**Deployed By:** _______________
**Verified By:** _______________

---

**END OF DEPLOYMENT GUIDE**
