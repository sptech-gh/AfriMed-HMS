<?php
// Test which dashboard view exists and is accessible
define('APPPATH', 'application/');

echo "<h2>Enhanced UI Test</h2>";

$enhanced_view = APPPATH . 'views/app/dashboard_enhanced.php';
$original_view = APPPATH . 'views/app/dashboard.php';

echo "<p><strong>Enhanced view path:</strong> " . $enhanced_view . "</p>";
echo "<p><strong>Enhanced view exists:</strong> " . (file_exists($enhanced_view) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Enhanced view size:</strong> " . (file_exists($enhanced_view) ? filesize($enhanced_view) . ' bytes' : 'N/A') . "</p>";

echo "<p><strong>Original view path:</strong> " . $original_view . "</p>";
echo "<p><strong>Original view exists:</strong> " . (file_exists($original_view) ? 'YES' : 'NO') . "</p>";

echo "<hr>";
echo "<p><strong>Controller file:</strong> application/controllers/app/dashboard.php</p>";
$controller = file_get_contents('application/controllers/app/dashboard.php');
if (strpos($controller, 'dashboard_enhanced') !== false) {
    echo "<p style='color: green;'><strong>✓ Controller IS loading enhanced view</strong></p>";
} else {
    echo "<p style='color: red;'><strong>✗ Controller NOT loading enhanced view</strong></p>";
}

echo "<hr>";
echo "<h3>PHP Info:</h3>";
echo "<p>PHP Version: " . PHP_VERSION . "</p>";
echo "<p>OPcache Enabled: " . (function_exists('opcache_get_status') && opcache_get_status() ? 'YES' : 'NO') . "</p>";

if (function_exists('opcache_get_status')) {
    $status = opcache_get_status();
    if ($status) {
        echo "<p>OPcache Memory Used: " . round($status['memory_usage']['used_memory'] / 1024 / 1024, 2) . " MB</p>";
        echo "<p><strong>Action:</strong> <a href='?reset=1'>Reset OPcache</a></p>";
        
        if (isset($_GET['reset'])) {
            opcache_reset();
            echo "<p style='color: green;'><strong>✓ OPcache has been reset! Refresh the dashboard now.</strong></p>";
        }
    }
}
?>
