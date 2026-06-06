<?php
// Minimal bootstrap for standalone PHPUnit running outside CI3 runtime.
// Define BASEPATH so helpers with the CI guard do not abort the test run.
if (!defined('BASEPATH')) {
    define('BASEPATH', __DIR__ . '/../');
}

// Define APPPATH to point to the application directory so any production
// code referencing APPPATH (e.g. for loading XSD files) works under PHPUnit.
if (!defined('APPPATH')) {
    define('APPPATH', __DIR__ . '/../application/');
}

// Minimal CI3 get_instance stub for tests.
if (!function_exists('get_instance')) {
    function get_instance() {
        static $ci = null;
        if ($ci === null) {
            $ci = new stdClass();
            // Very small stub for $this->load
            $ci->load = new class {
                public function model($name, $alias = null) { /* no-op in tests */ }
                public function helper($name) { /* no-op in tests */ }
            };
            // db will be injected/mocked as needed by specific tests
            $ci->db = null;
        }
        return $ci;
    }
}

require __DIR__ . '/../application/helpers/nhis_formatter_helper.php';
