<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Reddy HMS — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/login/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/hms-enhanced.css" rel="stylesheet">
    <style>
        /* Modernized CSS and aesthetic styles */
        *, *::before, *::after {
            box-sizing: border-box;
        }
        .hms-login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f4f6;
            padding: 20px;
            position: relative;
            overflow: hidden;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            transition: background 0.3s ease;
        }
        .theme-dark .hms-login-wrapper {
            background: #0f172a;
        }
        
        /* Smooth blurred background blobs */
        .blob-1 {
            position: absolute;
            top: -10%;
            left: -10%;
            width: 45%;
            height: 45%;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.12) 0%, rgba(99, 102, 241, 0) 70%);
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }
        .blob-2 {
            position: absolute;
            bottom: -10%;
            right: -10%;
            width: 45%;
            height: 45%;
            background: radial-gradient(circle, rgba(59, 130, 246, 0.12) 0%, rgba(59, 130, 246, 0) 70%);
            filter: blur(80px);
            pointer-events: none;
            z-index: 0;
        }

        .hms-login-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
            padding: 40px 36px 30px;
            position: relative;
            z-index: 1;
            transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .theme-dark .hms-login-card {
            background: #1e293b;
            border-color: #334155;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.2);
        }

        .hms-login-brand {
            text-align: center;
            margin-bottom: 28px;
        }
        .hms-login-brand img {
            width: 56px;
            height: 56px;
            object-fit: contain;
            margin-bottom: 12px;
        }
        .hms-login-brand h1 {
            font-size: 24px;
            font-weight: 700;
            color: #1f2937;
            margin: 0 0 4px;
            letter-spacing: -0.5px;
            transition: color 0.3s ease;
        }
        .theme-dark .hms-login-brand h1 {
            color: #f9fafb;
        }
        .hms-login-brand p {
            font-size: 14px;
            color: #6b7280;
            margin: 0;
            transition: color 0.3s ease;
        }
        .theme-dark .hms-login-brand p {
            color: #9ca3af;
        }

        .form-group {
            margin-bottom: 18px;
        }
        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            display: block;
            transition: color 0.3s ease;
        }
        .theme-dark .form-group label {
            color: #d1d5db;
        }

        .form-control {
            box-sizing: border-box;
            display: block;
            width: 100%;
            height: 42px;
            font-size: 14px;
            border-radius: 6px;
            border: 1px solid #d1d5db;
            background-color: #ffffff;
            color: #1f2937;
            padding: 8px 12px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out, background-color 0.3s ease, color 0.3s ease;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
            outline: none;
        }
        .theme-dark .form-control {
            background-color: #0f172a;
            border-color: #334155;
            color: #f9fafb;
        }
        .theme-dark .form-control:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.2);
        }

        .btn-login {
            width: 100%;
            height: 44px;
            font-size: 15px;
            font-weight: 600;
            border-radius: 6px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            cursor: pointer;
            transition: background 0.2s ease, box-shadow 0.2s ease;
            margin-top: 8px;
        }
        .btn-login:hover {
            background: #1d4ed8;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .theme-dark .btn-login {
            background: #3b82f6;
        }
        .theme-dark .btn-login:hover {
            background: #2563eb;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .hms-login-errors {
            background: #fee2e2;
            border-left: 3px solid #ef4444;
            color: #b91c1c;
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .theme-dark .hms-login-errors {
            background: rgba(239, 68, 68, 0.15);
            color: #fca5a5;
        }

        .hms-login-footer {
            text-align: center;
            margin-top: 30px;
            border-top: 1px solid #e5e7eb;
            padding-top: 20px;
            transition: border-color 0.3s ease;
        }
        .theme-dark .hms-login-footer {
            border-color: #334155;
        }

        .hms-theme-toggle-login {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: #4b5563;
            z-index: 10;
            transition: all 0.2s ease;
        }
        .theme-dark .hms-theme-toggle-login {
            background: #1e293b;
            border-color: #334155;
            color: #9ca3af;
        }
        .hms-theme-toggle-login:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            color: #111827;
        }
        .theme-dark .hms-theme-toggle-login:hover {
            color: #f9fafb;
        }

        .compliance-badges {
            margin: 20px 0 10px;
            padding: 12px;
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-around;
            font-size: 11px;
            color: #4b5563;
            transition: background 0.3s, border-color 0.3s, color 0.3s;
        }
        .theme-dark .compliance-badges {
            background: #1e293b;
            border-color: #334155;
            color: #9ca3af;
        }

        @media (max-width: 480px) {
            .hms-login-card { padding: 30px 24px 24px; }
            .hms-login-brand h1 { font-size: 21px; }
        }
    </style>
    <script>
        /* Theme: apply before paint to prevent flicker */
        (function(){
            var t = localStorage.getItem('hms_ui_theme');
            if (!t) t = 'light';
            document.documentElement.className = 'theme-' + t;
        })();
    </script>
</head>

<body>
<div class="blob-1"></div>
<div class="blob-2"></div>

<div class="hms-login-wrapper">
    <div class="hms-login-card">
        <div class="hms-login-brand">
            <img src="<?php echo BrandingService::platformLogo(); ?>" alt="Reddy HMS Logo">
            <h1><?php echo getPlatformName(); ?></h1>
            <p><?php echo getPlatformTagline(); ?></p>
        </div>

        <?php 
            $usernamelogin = isset($usernamelogin) ? $usernamelogin : '';
            $passwordlogin = isset($passwordlogin) ? $passwordlogin : '';
            $validation_errors = validation_errors();
        ?>

        <?php if (!empty($validation_errors)): ?>
            <div class="hms-login-errors"><?php echo $validation_errors; ?></div>
        <?php endif; ?>

        <form action="<?php echo base_url()?>login/validate_login" method="post" id="frmLogin" name="frmLogin" autocomplete="on">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
            
            <!-- Facility ID (Optional, Visual-only) -->
            <div class="form-group">
                <label for="facility_id">Facility ID <span style="font-weight: normal; color: #9ca3af;">(Optional)</span></label>
                <input type="text" name="facility_id" id="facility_id" class="form-control" placeholder="e.g. HMC-ACCRA">
            </div>

            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required value="<?php echo htmlspecialchars($usernamelogin); ?>" autofocus>
            </div>

            <div class="form-group" style="margin-bottom: 8px;">
                <label for="password">Password</label>
                <div style="position: relative; width: 100%;">
                    <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required value="<?php echo htmlspecialchars($passwordlogin); ?>" style="padding-right: 40px;">
                    <button type="button" id="toggle-password" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #6b7280; font-size: 16px; outline: none; padding: 0;">
                        👁️
                    </button>
                </div>
            </div>

            <div style="margin-bottom: 20px; display: flex; justify-content: flex-end;">
                <a href="#" id="forgot-password-link" style="font-size: 12px; color: #2563eb; text-decoration: none; font-weight: 500;">Forgot Password?</a>
            </div>

            <button type="submit" class="btn btn-login">Sign In</button>
        </form>

        <!-- Compliance Badges -->
        <div class="compliance-badges">
            <span style="display: flex; align-items: center; gap: 4px;">🛡️ Data Security Certified</span>
            <span style="display: flex; align-items: center; gap: 4px;">🇬🇭 NHIS Supported</span>
        </div>

        <!-- Reddy Platform Footer -->
        <div class="hms-login-footer">
            <div style="font-weight: 600; font-size: 13px; color: #374151;" class="platform-footer-name">Reddy HMS</div>
            <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">The Healthcare Operating System</div>
            <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;">Version 1.0.0</div>
        </div>
    </div>

    <button id="hms-login-theme-toggle" class="hms-theme-toggle-login" title="Toggle dark mode">&#9790;</button>
</div>

<script src="<?php echo base_url()?>public/login/js/jquery-1.7.2.min.js"></script>
<script>
    $(document).ready(function(){
        // Apply saved theme
        var theme = localStorage.getItem('hms_ui_theme') || 'light';
        document.body.className = 'theme-' + theme;
        updateToggleIcon(theme);

        // Theme toggle action
        $('#hms-login-theme-toggle').on('click', function(){
            var current = document.body.classList.contains('theme-dark') ? 'dark' : 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            document.body.className = 'theme-' + next;
            document.documentElement.className = 'theme-' + next;
            localStorage.setItem('hms_ui_theme', next);
            updateToggleIcon(next);
        });

        function updateToggleIcon(t) {
            $('#hms-login-theme-toggle').html(t === 'dark' ? '&#9788;' : '&#9790;');
        }

        // Show/Hide password toggle
        $('#toggle-password').on('click', function() {
            var pwdInput = document.getElementById('password');
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                $(this).text('🙈');
            } else {
                pwdInput.type = 'password';
                $(this).text('👁️');
            }
        });

        // Forgot Password handling
        $('#forgot-password-link').on('click', function(e) {
            e.preventDefault();
            alert('Please contact the local Reddy HMS system administrator or IT helpdesk for password assistance.');
        });
    });
</script>
</body>
</html>
