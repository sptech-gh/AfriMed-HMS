<?php
// Define CodeIgniter constants to boot it
define('BASEPATH', 'C:/laragon/www/hms-master/system/');
define('APPPATH', 'C:/laragon/www/hms-master/application/');
define('VIEWPATH', 'C:/laragon/www/hms-master/application/views/');
define('ENVIRONMENT', 'development');
define('UTF8_ENABLED', TRUE);
define('ICONV_ENABLED', TRUE);
define('MB_ENABLED', TRUE);

// Load CI Bootstrap
require_once BASEPATH . 'core/Common.php';
require_once BASEPATH . 'core/Controller.php';

function &get_instance() {
    return CI_Dummy::get_instance();
}

// Mock/dummy session, config and other classes if needed
class Mock_Session {
    public function userdata($key) {
        if ($key === 'search_opd_From') return date('Y-m-d');
        if ($key === 'search_opd_cTo') return date('Y-m-d');
        return null;
    }
    public function flashdata($key) {
        return '';
    }
    public function set_userdata($arr) {}
}

class Mock_Security {
    public function get_csrf_token_name() { return 'csrf_test_name'; }
    public function get_csrf_hash() { return 'mock_hash'; }
}

class Mock_Router {
    public function fetch_method() { return 'index'; }
}

// Instantiate CI_Controller and load libraries
class CI_Dummy extends CI_Controller {
    public static $instance;
    public function __construct() {
        self::$instance = $this;
        $this->lang = load_class('Lang', 'core');
        $this->config = load_class('Config', 'core');
        $this->input = load_class('Input', 'core');
        $this->load = load_class('Loader', 'core');
        $this->load->initialize();
        $this->session = new Mock_Session();
        $this->security = new Mock_Security();
        $this->router = new Mock_Router();
        $this->db = $this->load->database('default', TRUE);
        $this->load->library('table');
        $this->load->model('app/opd_model');
        $this->load->model('app/smart_billing_model');
    }
    public static function &get_instance() {
        return self::$instance;
    }
}

$ci = new CI_Dummy();

// Now simulate the index method logic
$patients = $ci->opd_model->getAll(10, 0);
$activePatients = array();
foreach ($patients as $p) {
    $legacyCompleted = (isset($p->nStatus) && (string)$p->nStatus !== 'Pending');
    if (!$legacyCompleted) {
        $activePatients[] = $p;
    }
}

// Output sizes
echo "Total Active Patients found: " . count($activePatients) . "\n";

$tmpl = array(
    'table_open'    => '<table class="table table-hover table-striped opd-clickable-table">',
    'row_start'     => '<tr class="opd-row">',
    'row_alt_start' => '<tr class="opd-row">',
);
$ci->table->set_template($tmpl);
$ci->table->set_empty('&nbsp;');
$ci->table->set_heading('OPD No', 'Patient No', 'Patient Name', 'Age', 'Coverage', 'Visit Type', 'Visit Date Time', 'Department', 'Consultant Doctor', 'Status', '');

// Loop through active patients (if any) and add rows
// (omitted for brevity as we want to see the output structure)
// Let's print the generated table HTML
$table_html = $ci->table->generate();
echo "Generated Table HTML:\n";
echo $table_html . "\n";
?>
