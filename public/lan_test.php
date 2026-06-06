<?php
/**
 * LAN Access Test Page
 * 
 * This page tests whether HMS is accessible via LAN IP address
 * and whether all assets load correctly.
 */
?>
<!DOCTYPE html>
<html>
<head>
    <title>HMS LAN Access Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>HMS LAN Access Test</h1>
    
    <div class="test-section">
        <h2>Current Access Information</h2>
        <p><strong>URL:</strong> <?php echo "http://" . ($_SERVER['HTTP_HOST'] ?? 'unknown') . $_SERVER['REQUEST_URI']; ?></p>
        <p><strong>Host:</strong> <?php echo $_SERVER['HTTP_HOST'] ?? 'unknown'; ?></p>
        <p><strong>Remote IP:</strong> <?php echo $_SERVER['REMOTE_ADDR'] ?? 'unknown'; ?></p>
        <p><strong>User Agent:</strong> <?php echo $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'; ?></p>
    </div>

    <div class="test-section">
        <h2>Environment Variables</h2>
        <p><strong>APP_BASE_URL:</strong> <?php echo getenv('APP_BASE_URL') ?: 'NOT SET'; ?></p>
        <p><strong>CLAIMIT_HOST:</strong> <?php echo getenv('CLAIMIT_HOST') ?: 'NOT SET'; ?></p>
        <p><strong>NHIS_MODE:</strong> <?php echo getenv('NHIS_MODE') ?: 'NOT SET'; ?></p>
    </div>

    <div class="test-section">
        <h2>Asset Loading Test</h2>
        <p>Testing Bootstrap CSS load:</p>
        <link rel="stylesheet" href="<?php echo getenv('APP_BASE_URL') ?: 'http://localhost/hms-master/'; ?>public/css/bootstrap.min.css">
        <div class="alert alert-info">
            If you see this styled with Bootstrap (blue background, good padding), CSS is loading correctly.
        </div>
        
        <p>Testing JavaScript load:</p>
        <script src="<?php echo getenv('APP_BASE_URL') ?: 'http://localhost/hms-master/'; ?>public/js/jquery.min.js"></script>
        <script>
            if (typeof $ !== 'undefined') {
                document.write('<p class="success">✓ jQuery loaded successfully</p>');
            } else {
                document.write('<p class="error">✗ jQuery failed to load</p>');
            }
        </script>
    </div>

    <div class="test-section">
        <h2>CodeIgniter Base URL Test</h2>
        <?php
        // Load CodeIgniter base URL
        define('ENVIRONMENT', 'development');
        define('BASEPATH', __DIR__ . '/../system/');
        define('APPPATH', __DIR__ . '/../application/');
        
        // Load .env like index.php does
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    putenv(trim($key) . '=' . trim($value));
                }
            }
        }
        
        // Simulate base_url()
        $base_url = getenv('APP_BASE_URL') !== false
            ? rtrim(getenv('APP_BASE_URL'), '/') . '/'
            : 'http://localhost/hms-master/';
        ?>
        <p><strong>Generated Base URL:</strong> <?php echo $base_url; ?></p>
        <p><strong>Test Asset URL:</strong> <a href="<?php echo $base_url; ?>public/css/bootstrap.min.css" target="_blank"><?php echo $base_url; ?>public/css/bootstrap.min.css</a></p>
    </div>

    <div class="test-section">
        <h2>Access Method Analysis</h2>
        <?php
        $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
        $is_ip = preg_match('/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/', $host);
        $is_localhost = in_array($host, ['localhost', '127.0.0.1']);
        
        if ($is_ip) {
            echo '<p class="success"><strong>✓ ACCESSING VIA IP ADDRESS</strong> - LAN access should work</p>';
        } elseif ($is_localhost) {
            echo '<p class="warning"><strong>⚠ ACCESSING VIA LOCALHOST</strong> - Try IP address for LAN testing</p>';
        } else {
            echo '<p class="success"><strong>✓ ACCESSING VIA HOSTNAME</strong> - DNS resolution working</p>';
        }
        ?>
    </div>

    <div class="test-section">
        <h2>Next Steps</h2>
        <ol>
            <li>If all tests pass, try accessing the main HMS application: <a href="<?php echo $base_url; ?>" target="_blank">Open HMS</a></li>
            <li>Test login functionality</li>
            <li>Test from other devices on the network</li>
            <li>Verify all pages load correctly</li>
        </ol>
    </div>

    <div class="test-section">
        <p><small>Test completed at: <?php echo date('Y-m-d H:i:s'); ?></small></p>
    </div>
</body>
</html>
