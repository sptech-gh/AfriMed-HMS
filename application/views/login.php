<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <?php
        $hospitalName = (isset($companyInfo) && isset($companyInfo->company_name) && trim((string)$companyInfo->company_name) !== '') ? trim((string)$companyInfo->company_name) : 'Hebrew Medical Center';
        $loginLogo = '';
        if (isset($companyInfo) && isset($companyInfo->login_logo) && trim((string)$companyInfo->login_logo) !== '') {
            $loginLogo = 'public/company_logo/' . trim((string)$companyInfo->login_logo);
        } elseif (isset($companyInfo) && isset($companyInfo->logo) && trim((string)$companyInfo->logo) !== '' && trim((string)$companyInfo->logo) !== 'sample.jpg') {
            $loginLogo = 'public/company_logo/' . trim((string)$companyInfo->logo);
        } else {
            $loginLogo = 'public/img/new/hms_logo.png';
        }
        $siteTitle = (isset($companyInfo) && isset($companyInfo->site_title) && trim((string)$companyInfo->site_title) !== '') ? trim((string)$companyInfo->site_title) : $hospitalName;
        $tagline = (isset($companyInfo) && isset($companyInfo->hospital_tagline) && trim((string)$companyInfo->hospital_tagline) !== '') ? trim((string)$companyInfo->hospital_tagline) : 'Hospital Management System';
    ?>
    <title><?php echo htmlspecialchars($siteTitle); ?> — Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/login/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/hms-enhanced.css" rel="stylesheet">
    <style>
        /* Login-specific styles using design tokens */
        .hms-login-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--hms-bg);
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        .hms-login-wrapper::before {
            content: '';
            position: absolute;
            top: -120px;
            right: -120px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: rgba(26,111,165,0.06);
            pointer-events: none;
        }
        .hms-login-wrapper::after {
            content: '';
            position: absolute;
            bottom: -80px;
            left: -80px;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            background: rgba(26,111,165,0.04);
            pointer-events: none;
        }
        .hms-login-card {
            width: 100%;
            max-width: 420px;
            background: var(--hms-surface);
            border: 1px solid var(--hms-border);
            border-radius: var(--hms-card-radius);
            box-shadow: var(--hms-shadow-lg);
            padding: 40px 36px 36px;
            position: relative;
            z-index: 1;
        }
        .hms-login-brand {
            text-align: center;
            margin-bottom: 32px;
        }
        .hms-login-brand img {
            width: 64px;
            height: 64px;
            object-fit: contain;
            margin-bottom: 14px;
        }
        .hms-login-brand h1 {
            font-size: 22px;
            font-weight: 700;
            color: var(--hms-text);
            margin: 0 0 4px;
            letter-spacing: -0.3px;
        }
        .hms-login-brand p {
            font-size: 14px;
            color: var(--hms-text-muted);
            margin: 0;
        }
        .hms-login-card .form-group {
            margin-bottom: 18px;
        }
        .hms-login-card label {
            font-size: 13px;
            font-weight: 600;
            color: var(--hms-text);
            margin-bottom: 6px;
        }
        .hms-login-card .form-control {
            height: 44px;
            font-size: 15px;
            border-radius: 6px;
            padding: 10px 14px;
        }
        .hms-login-card .btn-login {
            width: 100%;
            height: 46px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 6px;
            background: var(--hms-primary);
            color: var(--hms-primary-contrast);
            border: none;
            cursor: pointer;
            transition: background 0.2s ease, box-shadow 0.2s ease;
            margin-top: 6px;
        }
        .hms-login-card .btn-login:hover {
            background: var(--hms-primary-hover);
            box-shadow: 0 4px 12px rgba(26,111,165,0.25);
        }
        .hms-login-errors {
            background: var(--hms-danger-bg);
            border-left: 3px solid var(--hms-danger);
            color: var(--hms-danger);
            border-radius: 6px;
            padding: 10px 14px;
            font-size: 13px;
            margin-bottom: 18px;
        }
        .hms-login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: var(--hms-text-muted);
        }
        .hms-theme-toggle-login {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--hms-surface);
            border: 1px solid var(--hms-border);
            box-shadow: var(--hms-shadow);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: var(--hms-text-muted);
            z-index: 10;
            transition: all 0.2s ease;
        }
        .hms-theme-toggle-login:hover {
            box-shadow: var(--hms-shadow-lg);
            color: var(--hms-text);
        }
        @media (max-width: 480px) {
            .hms-login-card { padding: 28px 22px 24px; }
            .hms-login-brand h1 { font-size: 20px; }
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

<body class="theme-light">
<script src="<?php echo base_url()?>public/login/js/jquery-1.7.2.min.js"></script>
<script>
    $(document).ready(function(){
        // Apply saved theme to body
        var theme = localStorage.getItem('hms_ui_theme') || 'light';
        document.body.className = 'theme-' + theme;
        updateToggleIcon(theme);

        // Theme toggle
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
    });
</script>

<?php
    $usernamelogin = isset($usernamelogin) ? $usernamelogin : '';
    $passwordlogin = isset($passwordlogin) ? $passwordlogin : '';
    $validation_errors = validation_errors();
?>

<div class="hms-login-wrapper">
    <div class="hms-login-card">
        <div class="hms-login-brand">
            <img src="<?php echo base_url() . htmlspecialchars($loginLogo); ?>" alt="<?php echo htmlspecialchars($hospitalName); ?>">
            <h1><?php echo htmlspecialchars($hospitalName); ?></h1>
            <p><?php echo htmlspecialchars($tagline); ?></p>
        </div>

        <?php if (!empty($validation_errors)): ?>
            <div class="hms-login-errors"><?php echo $validation_errors; ?></div>
        <?php endif; ?>

        <form action="<?php echo base_url()?>login/validate_login" method="post" id="frmLogin" name="frmLogin" autocomplete="on">
            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required value="<?php echo htmlspecialchars($usernamelogin); ?>" autofocus>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required value="<?php echo htmlspecialchars($passwordlogin); ?>">
            </div>

            <button type="submit" class="btn btn-login">Sign In</button>
        </form>

        <div class="hms-login-footer">
            &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($hospitalName); ?>
        </div>
    </div>

    <button id="hms-login-theme-toggle" class="hms-theme-toggle-login" title="Toggle dark mode">&#9790;</button>
</div>

</body>
</html>
