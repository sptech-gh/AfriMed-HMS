<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        // Restrict this controller to CLI only for safety
        if (!$this->input->is_cli_request()) {
            show_404();
            return;
        }

        $this->load->library('migration');
    }

    /**
     * Migrate the database to the latest available migration.
     */
    public function latest()
    {
        $result = $this->migration->latest();
        if ($result === FALSE) {
            echo "Migration to latest failed:\n";
            echo $this->migration->error_string() . "\n";
            return;
        }

        // Run BrandingInstaller one-time setup
        $this->load->library('brandinginstaller');
        $this->brandinginstaller->install();

        echo "Migration completed. Current version: {$result}\n";
    }

    /**
     * Migrate the database to a specific version.
     * Usage (CLI): php index.php migrate/to 20250615
     *
     * @param int|string $target_version
     */
    public function to($target_version = 0)
    {
        $version = (int) $target_version;
        if ($version <= 0) {
            echo "Invalid target version: {$target_version}\n";
            return;
        }

        $result = $this->migration->version($version);
        if ($result === FALSE) {
            echo "Migration to version {$version} failed:\n";
            echo $this->migration->error_string() . "\n";
            return;
        }

        // Run BrandingInstaller one-time setup
        $this->load->library('brandinginstaller');
        $this->brandinginstaller->install();

        echo "Migration completed. Current version: {$result}\n";
    }

    /**
     * Run NHIS Phase 1 schema migration up() directly.
     *
     * Usage (CLI): php index.php migrate/nhis_phase1_up
     */
    public function nhis_phase1_up()
    {
        // Ensure DB and DBForge are loaded
        $this->load->database();
        $this->load->dbforge();

        // Load the migration class directly and execute up()
        $path = APPPATH . 'migrations/20260601_nhis_phase1_schema.php';
        if (!is_file($path)) {
            echo "Migration file not found at {$path}\n";
            return;
        }

        require_once $path;

        if (!class_exists('Migration_Nhis_Phase1_Schema')) {
            echo "Migration_Nhis_Phase1_Schema class not found in migration file.\n";
            return;
        }

        $migration = new Migration_Nhis_Phase1_Schema();
        $migration->up();

        echo "NHIS Phase 1 migration up() executed successfully.\n";
    }

    /**
     * Run NHIS Phase 1 schema migration down() directly.
     *
     * Usage (CLI): php index.php migrate/nhis_phase1_down
     */
    public function nhis_phase1_down()
    {
        // Ensure DB and DBForge are loaded
        $this->load->database();
        $this->load->dbforge();

        // Load the migration class directly and execute down()
        $path = APPPATH . 'migrations/20260601_nhis_phase1_schema.php';
        if (!is_file($path)) {
            echo "Migration file not found at {$path}\n";
            return;
        }

        require_once $path;

        if (!class_exists('Migration_Nhis_Phase1_Schema')) {
            echo "Migration_Nhis_Phase1_Schema class not found in migration file.\n";
            return;
        }

        $migration = new Migration_Nhis_Phase1_Schema();
        $migration->down();

        echo "NHIS Phase 1 migration down() executed successfully.\n";
    }
}
