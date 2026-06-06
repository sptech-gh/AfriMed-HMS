<?php
// Clear all PHP caches
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "✓ OPcache cleared<br>";
}

if (function_exists('apc_clear_cache')) {
    apc_clear_cache();
    echo "✓ APC cache cleared<br>";
}

// Clear CodeIgniter cache
$cache_dir = 'application/cache/';
if (is_dir($cache_dir)) {
    $files = glob($cache_dir . '*');
    foreach($files as $file) {
        if(is_file($file) && basename($file) != 'index.html') {
            unlink($file);
        }
    }
    echo "✓ CodeIgniter cache cleared<br>";
}

echo "<hr>";
echo "<h3>Cache Cleared Successfully!</h3>";
echo "<p><a href='app/dashboard'>Go to Dashboard</a></p>";
?>
