<?php
// Standalone script to test companyInfo loading in CI
define('BASEPATH', 'dummy');
define('ENVIRONMENT', 'development');
define('APPPATH', dirname(__DIR__) . '/application/');
define('FCPATH', dirname(__DIR__) . '/');

// Set request URI to index/login to trigger standard route loading
$_SERVER['PATH_INFO'] = '/login';

ob_start();
require_once FCPATH . 'index.php';
$output = ob_get_clean();

$CI =& get_instance();
$CI->load->model('general_model');
$info = $CI->general_model->companyInfo();

echo "Seeded Company Name: " . $info->company_name . "\n";
echo "Theme Default: " . $info->theme_default . "\n";
echo "Logo Path: " . $info->logo_path . "\n";
echo "Address: " . $info->address . "\n";
echo "Phone: " . $info->phone . "\n";
echo "TIN: " . $info->tin . "\n";
echo "Footer Note: " . $info->footer_note . "\n";
echo "SUCCESS!\n";
