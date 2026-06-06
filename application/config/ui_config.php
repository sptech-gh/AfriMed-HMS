<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HMS UI/UX Configuration
 * Controls enhanced UI features with backward compatibility
 */

// Enable enhanced UI globally (set to FALSE to use old UI)
$config['ui_enhanced_mode'] = TRUE;

// Per-module UI mode override (optional)
// Set specific modules to use old UI even when enhanced mode is enabled
$config['ui_legacy_modules'] = array(
    // Example: 'billing' => TRUE,  // Force billing module to use old UI
);

// UI Enhancement Features (can be toggled individually)
$config['ui_features'] = array(
    'auto_save'           => TRUE,  // Form auto-save to localStorage
    'loading_states'      => TRUE,  // Loading overlays and button states
    'inline_validation'   => TRUE,  // Real-time form validation
    'offline_detection'   => TRUE,  // Offline mode detection
    'enhanced_tables'     => TRUE,  // Improved table styling and interactions
    'role_dashboards'     => TRUE,  // Role-specific dashboard widgets
    'notifications'       => TRUE,  // Enhanced notification system
    'accessibility'       => TRUE,  // Accessibility improvements
);

// Auto-save configuration
$config['autosave_interval'] = 30000; // milliseconds (30 seconds)
$config['autosave_exclude_forms'] = array('login-form', 'password-form');

// Notification configuration
$config['notification_duration'] = 5000; // milliseconds (5 seconds)
$config['notification_position'] = 'top-right'; // top-right, top-left, bottom-right, bottom-left

// Loading state configuration
$config['loading_delay'] = 300; // milliseconds - show loading after this delay

// Table configuration
$config['table_rows_per_page'] = 25;
$config['table_enable_sorting'] = TRUE;
$config['table_enable_search'] = TRUE;

// Dashboard refresh interval (for real-time widgets)
$config['dashboard_refresh_interval'] = 300000; // milliseconds (5 minutes)

// Mobile/Tablet breakpoints
$config['breakpoint_mobile'] = 768;
$config['breakpoint_tablet'] = 1024;

// Ghana-specific settings
$config['ghana_phone_format'] = '/^0[0-9]{9}$/'; // 10 digits starting with 0
$config['nhis_number_format'] = '/^[0-9]{10}$/'; // 10 digits
$config['ghana_card_format'] = '/^GHA-[0-9]{9}-[0-9]$/'; // GHA-XXXXXXXXX-X

// Color scheme (can be customized per hospital)
$config['ui_colors'] = array(
    'primary'   => '#3c8dbc',
    'success'   => '#00a65a',
    'info'      => '#00c0ef',
    'warning'   => '#f39c12',
    'danger'    => '#dd4b39',
    'secondary' => '#6c757d',
);

// Status color mapping
$config['status_colors'] = array(
    'pending'   => '#ffc107',
    'active'    => '#28a745',
    'completed' => '#6c757d',
    'critical'  => '#dc3545',
    'urgent'    => '#fd7e14',
);
