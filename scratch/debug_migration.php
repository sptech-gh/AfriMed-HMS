<?php
// Define constants to boot CodeIgniter in CLI mode
define('BASEPATH', 'dummy'); // We won't use it directly, but let's see how CI boots
define('APPPATH', realpath(__DIR__ . '/../application') . '/');
define('FCPATH', realpath(__DIR__ . '/../') . '/');

// Boot CI by requiring the bootstrap
// We intercept execution by loading the migration library manually or seeing the error
$_SERVER['PATH_INFO'] = '/migrate/latest';

ob_start();
require_once FCPATH . 'index.php';
$output = ob_get_clean();

echo "Boot output:\n";
echo $output;
echo "\nDone!\n";
