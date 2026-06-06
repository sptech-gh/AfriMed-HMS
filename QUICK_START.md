# HMS Quick Start - Client Installation

## 5-Minute Setup Summary

### 1. Copy Files
```
Copy hms-master folder to C:\laragon\www\hms-master
```

### 2. Create Database
```
Open Laragon → phpMyAdmin → Import → database_init.sql
```

### 3. Edit Config File
**File:** `application/config/database.php`
```php
$db['default']['hostname'] = 'localhost';
$db['default']['username'] = 'root';
$db['default']['password'] = '';  // or your MySQL password
$db['default']['database'] = 'hms_prod';
```

### 4. Edit Base URL
**File:** `application/config/config.php`
```php
$config['base_url'] = 'http://localhost/hms-master/';
```

### 5. Access System
```
http://localhost/hms-master/
Login: admin / admin123
```

---

## Post-Setup Checklist

- [ ] Login and change admin password
- [ ] Create user accounts (cashier, doctor, nurse)
- [ ] Add hospital info in System Settings
- [ ] Test patient registration
- [ ] Test billing workflow

---

## Common Issues

| Issue | Fix |
|-------|-----|
| 404 Error | Check `.htaccess` exists, restart Laragon |
| DB Error | Verify database.php credentials |
| CSRF Error | Clear browser cookies, refresh page |
| Upload Error | Check `public/` folder permissions |

---

## Support
- Full Guide: `DEPLOYMENT_GUIDE.md`
- DB Script: `database_init.sql`

**Ready for deployment!**
