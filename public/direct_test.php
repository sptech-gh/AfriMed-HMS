<?php
// Direct test - bypass CodeIgniter completely
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Direct View Test</h2>";

$enhanced = '../application/views/app/dashboard_enhanced.php';
$original = '../application/views/app/dashboard.php';

echo "<p><strong>Enhanced view:</strong> " . realpath($enhanced) . "</p>";
echo "<p><strong>Exists:</strong> " . (file_exists($enhanced) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Readable:</strong> " . (is_readable($enhanced) ? 'YES' : 'NO') . "</p>";
echo "<p><strong>Size:</strong> " . (file_exists($enhanced) ? filesize($enhanced) . ' bytes' : 'N/A') . "</p>";

echo "<hr>";

$controller = file_get_contents('../application/controllers/app/dashboard.php');
echo "<p><strong>Controller loads enhanced view:</strong> " . (strpos($controller, "dashboard_enhanced") !== false ? 'YES' : 'NO') . "</p>";

echo "<hr>";
echo "<h3>First 500 characters of enhanced view:</h3>";
echo "<pre>" . htmlspecialchars(substr(file_get_contents($enhanced), 0, 500)) . "</pre>";

echo "<hr>";
echo "<h3>Controller dashboard() method:</h3>";
preg_match('/public function dashboard\(\).*?\n\t\}/s', $controller, $matches);
echo "<pre>" . htmlspecialchars($matches[0]) . "</pre>";
?>
