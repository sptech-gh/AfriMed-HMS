<?php
// A standalone test script to check database and migrations configuration
define('BASEPATH', 'dummy');
define('APPPATH', dirname(__DIR__) . '/application/');
define('FCPATH', dirname(__DIR__) . '/');

echo "APPPATH: " . APPPATH . "\n";
echo "FCPATH: " . FCPATH . "\n";

// Let's run a CI instance and inspect the migration object
// We will call index.php but inspect from inside
$_SERVER['PATH_INFO'] = '/migrate/latest';
require_once FCPATH . 'index.php';
