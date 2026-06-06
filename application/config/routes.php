<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| URI ROUTING
| -------------------------------------------------------------------------
| This file lets you re-map URI requests to specific controller functions.
|
| Typically there is a one-to-one relationship between a URL string
| and its corresponding controller class/method. The segments in a
| URL normally follow this pattern:
|
|	example.com/class/method/id/
|
| In some instances, however, you may want to remap this relationship
| so that a different class/function is called than the one
| corresponding to the URL.
|
| Please see the user guide for complete details:
|
|	http://codeigniter.com/user_guide/general/routing.html
|
| -------------------------------------------------------------------------
| RESERVED ROUTES
| -------------------------------------------------------------------------
|
| There area two reserved routes:
|
|	$route['default_controller'] = 'welcome';
|
| This route indicates which controller class should be loaded if the
| URI contains no data. In the above example, the "welcome" class
| would be loaded.
|
|	$route['404_override'] = 'errors/page_missing';
|
| This route will tell the Router what URI segments to use if those provided
| in the URL cannot be matched to a valid route.
|
*/

$route['default_controller'] = "login";
$route['login/validate_login'] = 'login/validate_login';
$route['404_override'] = '';

/* Unified Billing Routes - Single Source of Truth */
$route['app/unified_billing'] = 'app/Unified_billing';
$route['app/unified_billing/(:any)'] = 'app/Unified_billing/$1';
$route['app/unified_billing/(:any)/(:any)'] = 'app/Unified_billing/$1/$2';

/* Legacy Billing (production path) */
$route['app/billing'] = 'app/Billing';
$route['app/billing/(:any)'] = 'app/Billing/$1';
$route['app/billing/(:any)/(:any)'] = 'app/Billing/$1/$2';

$route['app/cashier'] = 'app/Cashier';
$route['app/cashier/(:any)'] = 'app/Cashier/$1';
$route['app/cashier/(:any)/(:any)'] = 'app/Cashier/$1/$2';

$route['app/ebilling'] = 'app/Ebilling';
$route['app/ebilling/(:any)'] = 'app/Ebilling/$1';
$route['app/ebilling/(:any)/(:any)'] = 'app/Ebilling/$1/$2';

$route['app/smart_billing'] = 'app/Billing/smart_billing';
$route['app/billing_engine'] = 'app/Billing';

/* GHS/NHIS Test Catalog Routes */
$route['app/test_catalog'] = 'app/Test_catalog/lab_tests';
$route['app/test_catalog/lab'] = 'app/Test_catalog/lab_tests';
$route['app/test_catalog/sonography'] = 'app/Test_catalog/sonography_tests';
$route['app/test_catalog/(:any)'] = 'app/Test_catalog/$1';
$route['app/test_catalog/(:any)/(:any)'] = 'app/Test_catalog/$1/$2';

/* Billing Reports Routes */
$route['app/billing_reports'] = 'app/Billing_reports';
$route['app/billing_reports/(:any)'] = 'app/Billing_reports/$1';
$route['app/billing_reports/(:any)/(:any)'] = 'app/Billing_reports/$1/$2';

/* NHIS Integration Routes */
$route['app/nhis'] = 'app/nhis';
$route['app/nhis/(:any)'] = 'app/nhis/$1';
$route['app/nhis/(:any)/(:any)'] = 'app/nhis/$1/$2';
$route['app/nhis_claims'] = 'app/nhis_claims';
$route['app/nhis_claims/(:any)'] = 'app/nhis_claims/$1';
$route['app/nhis_claims/(:any)/(:any)'] = 'app/nhis_claims/$1/$2';

/* Unified Worklist Routes */
$route['app/worklist'] = 'app/worklist';
$route['app/worklist/(:any)'] = 'app/worklist/$1';
$route['app/worklist/(:any)/(:any)'] = 'app/worklist/$1/$2';

/* Nursing Cockpit Routes */
$route['app/nursing'] = 'app/nursing';
$route['app/nursing/(:any)'] = 'app/nursing/$1';
$route['app/nursing/(:any)/(:any)'] = 'app/nursing/$1/$2';
$route['api/nursing/dashboard'] = 'app/nursing/api_dashboard';
$route['api/nursing/patients'] = 'app/nursing/api_patients';
$route['api/nursing/patient/(:any)/summary'] = 'app/nursing/api_patient_summary/$1';
$route['api/nursing/patient/(:any)/vitals'] = 'app/nursing/api_save_vitalSign/$1';
$route['api/nursing/patient/(:any)/notes'] = 'app/nursing/api_save_note/$1';
$route['api/nursing/patient/(:any)/procedures'] = 'app/nursing/api_save_procedure/$1';
$route['api/nursing/alerts'] = 'app/nursing/api_alerts';

/* User Management Routes */
$route['app/user'] = 'app/user';
$route['app/user/(:any)'] = 'app/user/$1';
$route['app/user/(:any)/(:any)'] = 'app/user/$1/$2';

$route['app/patient_history/(:num)'] = 'app/Patient_history/index/$1';

/* End of file routes.php */
/* Location: ./application/config/routes.php */