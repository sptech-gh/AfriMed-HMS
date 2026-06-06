<?php
/**
 * HMS Enhanced UI/UX Deployment Script
 * Safely deploys enhanced UI with zero downtime
 * 
 * Usage: php scripts/deploy_enhanced_ui.php [--enable|--disable|--status]
 */

define('BASEPATH', dirname(__FILE__) . '/../');
require_once BASEPATH . 'application/config/database.php';

class UIDeployment {
    
    private $config_file;
    private $backup_dir;
    
    public function __construct() {
        $this->config_file = BASEPATH . 'application/config/ui_config.php';
        $this->backup_dir = BASEPATH . 'backups/ui_config/';
        
        // Create backup directory if it doesn't exist
        if (!is_dir($this->backup_dir)) {
            mkdir($this->backup_dir, 0755, true);
        }
    }
    
    /**
     * Enable enhanced UI mode
     */
    public function enable() {
        echo "Enabling Enhanced UI/UX...\n";
        
        // Backup current config
        $this->backup();
        
        // Update config
        $this->updateConfig('ui_enhanced_mode', TRUE);
        
        // Verify deployment
        if ($this->verify()) {
            echo "✓ Enhanced UI enabled successfully!\n";
            echo "  All users will now see the improved interface.\n";
            echo "  To revert, run: php scripts/deploy_enhanced_ui.php --disable\n";
            return true;
        } else {
            echo "✗ Deployment verification failed. Rolling back...\n";
            $this->restore();
            return false;
        }
    }
    
    /**
     * Disable enhanced UI mode (revert to legacy)
     */
    public function disable() {
        echo "Disabling Enhanced UI (reverting to legacy)...\n";
        
        // Backup current config
        $this->backup();
        
        // Update config
        $this->updateConfig('ui_enhanced_mode', FALSE);
        
        echo "✓ Reverted to legacy UI successfully!\n";
        echo "  To re-enable enhanced UI, run: php scripts/deploy_enhanced_ui.php --enable\n";
        return true;
    }
    
    /**
     * Show current UI status
     */
    public function status() {
        if (!file_exists($this->config_file)) {
            echo "✗ UI config file not found!\n";
            echo "  Expected: {$this->config_file}\n";
            return false;
        }
        
        include $this->config_file;
        
        echo "=== HMS UI/UX Status ===\n\n";
        echo "Mode: " . ($config['ui_enhanced_mode'] ? "Enhanced UI ✓" : "Legacy UI") . "\n";
        echo "Config file: {$this->config_file}\n\n";
        
        echo "Features:\n";
        foreach ($config['ui_features'] as $feature => $enabled) {
            $status = $enabled ? '✓ Enabled' : '✗ Disabled';
            echo "  - " . ucwords(str_replace('_', ' ', $feature)) . ": {$status}\n";
        }
        
        echo "\nAssets:\n";
        $css_file = BASEPATH . 'public/css/hms-enhanced.css';
        $js_file = BASEPATH . 'public/js/hms-enhanced.js';
        
        echo "  - CSS: " . (file_exists($css_file) ? "✓ Found" : "✗ Missing") . "\n";
        echo "  - JavaScript: " . (file_exists($js_file) ? "✓ Found" : "✗ Missing") . "\n";
        
        echo "\nViews:\n";
        $views = ['header_enhanced', 'footer_enhanced', 'dashboard_enhanced'];
        foreach ($views as $view) {
            $view_file = BASEPATH . 'application/views/include/' . $view . '.php';
            if (!file_exists($view_file)) {
                $view_file = BASEPATH . 'application/views/app/' . $view . '.php';
            }
            echo "  - {$view}: " . (file_exists($view_file) ? "✓ Found" : "✗ Missing") . "\n";
        }
        
        return true;
    }
    
    /**
     * Backup current configuration
     */
    private function backup() {
        if (!file_exists($this->config_file)) {
            echo "  No existing config to backup.\n";
            return;
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backup_file = $this->backup_dir . "ui_config_{$timestamp}.php";
        
        if (copy($this->config_file, $backup_file)) {
            echo "  ✓ Config backed up to: {$backup_file}\n";
        } else {
            echo "  ✗ Warning: Could not create backup!\n";
        }
    }
    
    /**
     * Restore from latest backup
     */
    private function restore() {
        $backups = glob($this->backup_dir . 'ui_config_*.php');
        
        if (empty($backups)) {
            echo "  ✗ No backups found to restore!\n";
            return false;
        }
        
        // Get latest backup
        rsort($backups);
        $latest_backup = $backups[0];
        
        if (copy($latest_backup, $this->config_file)) {
            echo "  ✓ Restored from: {$latest_backup}\n";
            return true;
        } else {
            echo "  ✗ Failed to restore backup!\n";
            return false;
        }
    }
    
    /**
     * Update configuration value
     */
    private function updateConfig($key, $value) {
        $content = file_get_contents($this->config_file);
        
        $value_str = $value ? 'TRUE' : 'FALSE';
        $pattern = "/\\\$config\['{$key}'\]\s*=\s*(TRUE|FALSE);/";
        $replacement = "\$config['{$key}'] = {$value_str};";
        
        $content = preg_replace($pattern, $replacement, $content);
        
        if (file_put_contents($this->config_file, $content)) {
            echo "  ✓ Updated {$key} = {$value_str}\n";
            return true;
        } else {
            echo "  ✗ Failed to update config!\n";
            return false;
        }
    }
    
    /**
     * Verify deployment
     */
    private function verify() {
        echo "  Verifying deployment...\n";
        
        // Check config file
        if (!file_exists($this->config_file)) {
            echo "    ✗ Config file missing\n";
            return false;
        }
        echo "    ✓ Config file exists\n";
        
        // Check CSS file
        $css_file = BASEPATH . 'public/css/hms-enhanced.css';
        if (!file_exists($css_file)) {
            echo "    ✗ Enhanced CSS file missing\n";
            return false;
        }
        echo "    ✓ Enhanced CSS file exists\n";
        
        // Check JS file
        $js_file = BASEPATH . 'public/js/hms-enhanced.js';
        if (!file_exists($js_file)) {
            echo "    ✗ Enhanced JS file missing\n";
            return false;
        }
        echo "    ✓ Enhanced JS file exists\n";
        
        // Check UI helper
        $helper_file = BASEPATH . 'application/helpers/ui_helper.php';
        if (!file_exists($helper_file)) {
            echo "    ✗ UI helper missing\n";
            return false;
        }
        echo "    ✓ UI helper exists\n";
        
        return true;
    }
    
    /**
     * Run pre-deployment checks
     */
    public function preCheck() {
        echo "=== Pre-Deployment Checks ===\n\n";
        
        $checks_passed = true;
        
        // Check PHP version
        echo "PHP Version: " . PHP_VERSION;
        if (version_compare(PHP_VERSION, '5.3.0', '>=')) {
            echo " ✓\n";
        } else {
            echo " ✗ (Requires PHP 5.3+)\n";
            $checks_passed = false;
        }
        
        // Check write permissions
        $dirs_to_check = [
            BASEPATH . 'application/config/',
            BASEPATH . 'public/css/',
            BASEPATH . 'public/js/',
            $this->backup_dir
        ];
        
        echo "\nWrite Permissions:\n";
        foreach ($dirs_to_check as $dir) {
            echo "  {$dir}: ";
            if (is_writable($dir)) {
                echo "✓\n";
            } else {
                echo "✗ (Not writable)\n";
                $checks_passed = false;
            }
        }
        
        // Check required files
        echo "\nRequired Files:\n";
        $required_files = [
            'public/css/hms-enhanced.css',
            'public/js/hms-enhanced.js',
            'application/helpers/ui_helper.php',
            'application/config/ui_config.php',
            'application/views/include/header_enhanced.php',
            'application/views/include/footer_enhanced.php'
        ];
        
        foreach ($required_files as $file) {
            $full_path = BASEPATH . $file;
            echo "  {$file}: ";
            if (file_exists($full_path)) {
                echo "✓\n";
            } else {
                echo "✗ (Missing)\n";
                $checks_passed = false;
            }
        }
        
        echo "\n";
        if ($checks_passed) {
            echo "✓ All pre-deployment checks passed!\n";
            echo "  Ready to deploy enhanced UI.\n";
        } else {
            echo "✗ Some checks failed. Please fix issues before deploying.\n";
        }
        
        return $checks_passed;
    }
}

// CLI execution
if (php_sapi_name() === 'cli') {
    $deployment = new UIDeployment();
    
    $command = isset($argv[1]) ? $argv[1] : '--status';
    
    switch ($command) {
        case '--enable':
            $deployment->enable();
            break;
            
        case '--disable':
            $deployment->disable();
            break;
            
        case '--status':
            $deployment->status();
            break;
            
        case '--check':
            $deployment->preCheck();
            break;
            
        default:
            echo "HMS Enhanced UI/UX Deployment Tool\n\n";
            echo "Usage: php scripts/deploy_enhanced_ui.php [command]\n\n";
            echo "Commands:\n";
            echo "  --enable    Enable enhanced UI mode\n";
            echo "  --disable   Disable enhanced UI (revert to legacy)\n";
            echo "  --status    Show current UI status\n";
            echo "  --check     Run pre-deployment checks\n\n";
            echo "Examples:\n";
            echo "  php scripts/deploy_enhanced_ui.php --check\n";
            echo "  php scripts/deploy_enhanced_ui.php --enable\n";
            echo "  php scripts/deploy_enhanced_ui.php --status\n";
            break;
    }
}
