# HMS LAN Deployment Guide

## 🚀 Quick Start

### 1. Access URL
```
http://192.168.0.136/hms-master/
```

### 2. Test Pages
- **LAN Test**: `http://192.168.0.136/hms-master/public/lan_test.php`
- **Claim-It Test**: `http://192.168.0.136/hms-master/claimit_lan_test.php`

## 📋 Configuration Summary

### Environment Variables (.env file)
```bash
# HMS Base URL Configuration for LAN Access
APP_BASE_URL=http://192.168.0.136/hms-master/

# Claim-It HMS Configuration
NHIS_MODE=live
CLAIMIT_HOST=192.168.0.136
CLAIMIT_PORT=31719
CLAIMIT_USERNAME=HPAPI13913
CLAIMIT_PASSWORD=oMuDz@hSHnGPj8BY?Qd^bfv7C
NHIS_FACILITY_CODE=HPAPI13913
NHIS_API_TIMEOUT=30
NHIS_API_CONNECT_TIMEOUT=10
```

### Key Changes Made

#### 1. Environment Variable Support
- Added `.env` file loading in `index.php`
- CodeIgniter now reads environment variables for configuration
- Base URL dynamically configured via `APP_BASE_URL`

#### 2. Base URL Configuration
- **Before**: Hardcoded `http://localhost/hms-master/`
- **After**: Dynamic `http://192.168.0.136/hms-master/`
- All assets, forms, and AJAX calls now use IP-based URLs

#### 3. Claim-It Integration
- **Before**: `CLAIMIT_HOST=localhost`
- **After**: `CLAIMIT_HOST=192.168.0.136`
- NHIS integration works across LAN

## 🔧 Technical Implementation

### Files Modified

1. **index.php**
   - Added `.env` file loading logic
   - Parses KEY=VALUE format
   - Sets environment variables for PHP

2. **application/config/config.php**
   - Base URL now reads from `APP_BASE_URL` environment variable
   - Falls back to localhost if not set
   - Fixed double slash issue in URL generation

3. **.env**
   - Added `APP_BASE_URL=http://192.168.0.136/hms-master/`
   - Updated `CLAIMIT_HOST=192.168.0.136`

### Test Files Created

1. **test_base_url.php** - CLI testing script
2. **public/lan_test.php** - Web-based LAN access test
3. **claimit_lan_test.php** - Claim-It connectivity test

## 🌐 Network Architecture

### Current Setup
```
Host Machine: 192.168.0.136
├── HMS Web Server (Apache/Laragon)
│   ├── Port: 80 (HTTP)
│   ├── Path: /hms-master/
│   └── Base URL: http://192.168.0.136/hms-master/
├── Claim-It Desktop App
│   ├── Port: 31719
│   ├── Host: 192.168.0.136
│   └── API: http://192.168.0.136:31719/api/v1/
└── Database (MySQL/MariaDB)
    └── Host: localhost (local only)
```

### Access Points
- **Main Application**: `http://192.168.0.136/hms-master/`
- **Login**: `http://192.168.0.136/hms-master/login`
- **Admin Dashboard**: `http://192.168.0.136/hms-master/app/dashboard`

## 📱 Multi-Device Testing

### Testing Checklist

#### ✅ Basic Access
- [ ] Access main URL from different devices
- [ ] Test from mobile phones
- [ ] Test from tablets
- [ ] Test from other computers

#### ✅ Functionality Testing
- [ ] Login page loads and works
- [ ] Dashboard displays correctly
- [ ] All menu items work
- [ ] Forms submit correctly
- [ ] AJAX calls work
- [ ] Asset loading (CSS, JS, images)

#### ✅ Module Testing
- [ ] Patient registration
- [ ] OPD workflow
- [ ] Billing system
- [ ] Pharmacy module
- [ ] Laboratory module
- [ ] NHIS integration

### Troubleshooting Common Issues

#### Issue: Assets Not Loading
**Symptoms**: Broken CSS, missing images, JavaScript errors
**Causes**: Base URL mismatch, firewall blocking
**Solutions**:
1. Verify `APP_BASE_URL` is set correctly
2. Check firewall settings on host machine
3. Test asset URLs directly

#### Issue: Login Not Working
**Symptoms**: Login page loads but authentication fails
**Causes**: Session configuration, CSRF tokens
**Solutions**:
1. Check cookie domain settings
2. Verify session storage
3. Test CSRF token generation

#### Issue: Forms Not Submitting
**Symptoms**: Form submissions fail or redirect incorrectly
**Causes**: Action URLs pointing to localhost
**Solutions**:
1. Verify form actions use `base_url()`
2. Check CSRF token configuration
3. Test form URLs directly

## 🔒 Security Considerations

### Current Security Settings
- **CSRF Protection**: Enabled
- **Session Security**: IP matching enabled
- **Cookie Security**: HTTP Only enabled
- **Environment**: Development (debug mode on)

### Production Recommendations

#### 1. Environment Changes
```php
// In index.php
define('ENVIRONMENT', 'production');

// In config.php
$config['global_xss_filtering'] = TRUE;
$config['csrf_protection'] = TRUE;
$config['cookie_secure'] = TRUE;  // Requires HTTPS
```

#### 2. Session Security
```php
$config['sess_match_ip'] = FALSE;  // May cause issues with mobile clients
$config['sess_expire_on_close'] = FALSE;
$config['sess_expiration'] = 7200;
```

#### 3. Network Security
- Configure firewall to allow only necessary ports
- Use HTTPS in production (SSL certificate)
- Consider VPN for remote access
- Regular security updates

## 🚀 Production Deployment

### Step 1: Environment Preparation
1. Set `ENVIRONMENT=production` in index.php
2. Configure HTTPS with SSL certificate
3. Update firewall rules
4. Set up backup system

### Step 2: Domain Configuration
```bash
# Update .env for production
APP_BASE_URL=https://hms.yourdomain.com/
```

### Step 3: Security Hardening
1. Disable error display in production
2. Configure proper file permissions
3. Set up intrusion detection
4. Regular security audits

### Step 4: Monitoring
1. Set up application monitoring
2. Configure log rotation
3. Set up alerts for downtime
4. Regular performance testing

## 📞 Support

### Test Results Expected
- ✅ Base URL resolves to IP address
- ✅ All assets load correctly
- ✅ Login functionality works
- ✅ All modules operational
- ✅ Claim-It integration functional

### Common Issues & Solutions
| Issue | Solution |
|-------|----------|
| Can't access via IP | Check firewall, verify Apache running |
| Assets not loading | Verify APP_BASE_URL setting |
| Login fails | Check session configuration |
| Claim-It not working | Verify Claim-It service running |

### Next Steps
1. Test from multiple devices on network
2. Verify all functionality works
3. Document any issues found
4. Plan production deployment

---

**Status**: ✅ LAN Access Configuration Complete  
**Next**: 🔄 Multi-Device Testing Required  
**Final**: 🚀 Ready for Production Deployment
