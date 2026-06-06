<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HMS UI Helper Functions
 * Provides utility functions for enhanced UI/UX
 */

if (!function_exists('is_enhanced_ui')) {
    /**
     * Check if enhanced UI mode is enabled
     * @return bool
     */
    function is_enhanced_ui() {
        $CI =& get_instance();
        $CI->config->load('ui_config', TRUE);
        return $CI->config->item('ui_enhanced_mode', 'ui_config') === TRUE;
    }
}

if (!function_exists('ui_feature_enabled')) {
    /**
     * Check if a specific UI feature is enabled
     * @param string $feature Feature name
     * @return bool
     */
    function ui_feature_enabled($feature) {
        $CI =& get_instance();
        $CI->config->load('ui_config', TRUE);
        $features = $CI->config->item('ui_features', 'ui_config');
        return isset($features[$feature]) && $features[$feature] === TRUE;
    }
}

if (!function_exists('load_view_enhanced')) {
    /**
     * Load view with enhanced UI support
     * Automatically switches between enhanced and legacy views
     * @param string $view View name
     * @param array $data Data to pass to view
     * @param bool $return Return as string
     * @return mixed
     */
    function load_view_enhanced($view, $data = array(), $return = FALSE) {
        $CI =& get_instance();
        
        // Check if enhanced mode is enabled
        if (is_enhanced_ui()) {
            // Check if enhanced version exists
            $enhanced_view = $view . '_enhanced';
            $view_path = APPPATH . 'views/' . str_replace('.', '/', $enhanced_view) . '.php';
            
            if (file_exists($view_path)) {
                return $CI->load->view($enhanced_view, $data, $return);
            }
        }
        
        // Fall back to original view
        return $CI->load->view($view, $data, $return);
    }
}

if (!function_exists('get_header_view')) {
    /**
     * Get the appropriate header view based on UI mode
     * @return string
     */
    function get_header_view() {
        return is_enhanced_ui() ? 'include/header_enhanced' : 'include/header';
    }
}

if (!function_exists('get_footer_view')) {
    /**
     * Get the appropriate footer view based on UI mode
     * @return string
     */
    function get_footer_view() {
        return is_enhanced_ui() ? 'include/footer_enhanced' : 'include/footer';
    }
}

if (!function_exists('render_status_badge')) {
    /**
     * Render a status badge with appropriate styling
     * @param string $status Status value
     * @param string $label Display label (optional)
     * @return string HTML for status badge
     */
    function render_status_badge($status, $label = '') {
        $CI =& get_instance();
        $CI->config->load('ui_config', TRUE);
        $colors = $CI->config->item('status_colors', 'ui_config');
        
        $status_lower = strtolower($status);
        $class = 'status-' . $status_lower;
        $display = $label ?: ucfirst($status);
        
        return '<span class="label ' . $class . '">' . htmlspecialchars($display) . '</span>';
    }
}

if (!function_exists('render_nhis_badge')) {
    /**
     * Render NHIS status badge
     * @param string $nhis_number NHIS number
     * @param string $expiry_date Expiry date
     * @return string HTML for NHIS badge
     */
    function render_nhis_badge($nhis_number, $expiry_date = '') {
        if (empty($nhis_number)) {
            return '<span class="label label-default">No NHIS</span>';
        }
        
        $is_expired = false;
        if (!empty($expiry_date)) {
            $is_expired = strtotime($expiry_date) < time();
        }
        
        $class = $is_expired ? 'nhis-badge nhis-expired' : 'nhis-badge';
        $text = $is_expired ? 'NHIS Expired' : 'NHIS Active';
        
        return '<span class="' . $class . '" title="' . htmlspecialchars($nhis_number) . '">' . $text . '</span>';
    }
}

if (!function_exists('get_branding')) {
    /**
     * Get branding values from companyInfo with safe fallbacks.
     * Centralized source of truth — use this instead of accessing $companyInfo directly.
     * @param string $key Optional key to get a single value
     * @return mixed Array of all branding values, or single value if $key specified
     */
    function get_branding($key = null) {
        $CI =& get_instance();
        $info = isset($CI->data['companyInfo']) ? $CI->data['companyInfo'] : null;
        if (!$info && isset($CI->general_model)) {
            $info = $CI->general_model->companyInfo();
        }

        $branding = array(
            'hospital_name'   => ($info && isset($info->company_name) && trim((string)$info->company_name) !== '') ? trim((string)$info->company_name) : 'Hebrew Medical Center',
            'site_title'      => ($info && isset($info->site_title) && trim((string)$info->site_title) !== '') ? trim((string)$info->site_title) : '',
            'tagline'         => ($info && isset($info->hospital_tagline) && trim((string)$info->hospital_tagline) !== '') ? trim((string)$info->hospital_tagline) : '',
            'address'         => ($info && isset($info->company_address)) ? trim((string)$info->company_address) : '',
            'phone'           => ($info && isset($info->company_contactNo)) ? trim((string)$info->company_contactNo) : '',
            'email'           => ($info && isset($info->company_email)) ? trim((string)$info->company_email) : '',
            'logo'            => ($info && isset($info->logo) && trim((string)$info->logo) !== '' && trim((string)$info->logo) !== 'sample.jpg') ? 'public/company_logo/' . trim((string)$info->logo) : '',
            'header_logo'     => ($info && isset($info->header_logo) && trim((string)$info->header_logo) !== '') ? 'public/company_logo/' . trim((string)$info->header_logo) : '',
            'login_logo'      => ($info && isset($info->login_logo) && trim((string)$info->login_logo) !== '') ? 'public/company_logo/' . trim((string)$info->login_logo) : '',
            'theme_default'   => ($info && isset($info->theme_default) && in_array(trim((string)$info->theme_default), array('light','dark'))) ? trim((string)$info->theme_default) : 'light',
        );

        // Fallback chains
        if ($branding['site_title'] === '') { $branding['site_title'] = $branding['hospital_name']; }
        if ($branding['header_logo'] === '') { $branding['header_logo'] = $branding['logo']; }
        if ($branding['login_logo'] === '') { $branding['login_logo'] = $branding['logo']; }
        if ($branding['login_logo'] === '') { $branding['login_logo'] = 'public/img/new/hms_logo.png'; }
        if ($branding['header_logo'] === '') { $branding['header_logo'] = 'public/company_logo/sample.jpg'; }

        if ($key !== null) {
            return isset($branding[$key]) ? $branding[$key] : '';
        }
        return $branding;
    }
}

if (!function_exists('format_ghana_phone')) {
    /**
     * Format Ghana phone number for display
     * @param string $phone Phone number
     * @return string Formatted phone number
     */
    function format_ghana_phone($phone) {
        // Remove any non-digit characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Format as 0XX XXX XXXX
        if (strlen($phone) == 10) {
            return substr($phone, 0, 3) . ' ' . substr($phone, 3, 3) . ' ' . substr($phone, 6);
        }
        
        return $phone;
    }
}

if (!function_exists('render_loading_overlay')) {
    /**
     * Render loading overlay HTML
     * @param string $message Optional loading message
     * @return string HTML for loading overlay
     */
    function render_loading_overlay($message = '') {
        $html = '<div id="hms-loading-overlay" class="loading-overlay">';
        $html .= '<div class="loading-spinner"></div>';
        if ($message) {
            $html .= '<p style="margin-top: 20px; font-size: 16px; color: #333;">' . htmlspecialchars($message) . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('render_notification')) {
    /**
     * Render notification/alert HTML
     * @param string $message Message text
     * @param string $type Type: success, danger, warning, info
     * @param bool $dismissible Can be dismissed
     * @return string HTML for notification
     */
    function render_notification($message, $type = 'info', $dismissible = true) {
        $class = 'alert alert-' . $type;
        if ($dismissible) {
            $class .= ' alert-dismissible';
        }
        
        $html = '<div class="' . $class . '" role="alert">';
        
        if ($dismissible) {
            $html .= '<button type="button" class="close" data-dismiss="alert" aria-label="Close">';
            $html .= '<span aria-hidden="true">&times;</span>';
            $html .= '</button>';
        }
        
        $icons = array(
            'success' => 'fa-check-circle',
            'danger' => 'fa-exclamation-circle',
            'warning' => 'fa-exclamation-triangle',
            'info' => 'fa-info-circle'
        );
        
        $icon = isset($icons[$type]) ? $icons[$type] : $icons['info'];
        $html .= '<i class="fa ' . $icon . '"></i> ';
        $html .= htmlspecialchars($message);
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('render_dashboard_stat')) {
    /**
     * Render dashboard stat widget
     * @param string $value Stat value
     * @param string $label Stat label
     * @param string $type Color type: primary, success, warning, danger, info
     * @param string $icon Font Awesome icon class
     * @return string HTML for dashboard stat
     */
    function render_dashboard_stat($value, $label, $type = 'primary', $icon = '') {
        $html = '<div class="dashboard-stat stat-' . $type . '">';
        
        if ($icon) {
            $html .= '<div class="dashboard-stat-icon"><i class="fa ' . $icon . '"></i></div>';
        }
        
        $html .= '<div class="dashboard-stat-value" data-count="' . htmlspecialchars($value) . '">' . htmlspecialchars($value) . '</div>';
        $html .= '<div class="dashboard-stat-label">' . htmlspecialchars($label) . '</div>';
        $html .= '</div>';
        
        return $html;
    }
}

if (!function_exists('render_breadcrumb')) {
    /**
     * Render breadcrumb navigation
     * @param array $items Array of breadcrumb items [['label' => 'Home', 'url' => '...'], ...]
     * @return string HTML for breadcrumb
     */
    function render_breadcrumb($items) {
        if (empty($items)) {
            return '';
        }
        
        $html = '<ol class="breadcrumb">';
        
        foreach ($items as $index => $item) {
            $is_last = ($index === count($items) - 1);
            
            if ($is_last) {
                $html .= '<li class="active">' . htmlspecialchars($item['label']) . '</li>';
            } else {
                $html .= '<li>';
                if (isset($item['url'])) {
                    $html .= '<a href="' . htmlspecialchars($item['url']) . '">';
                }
                if (isset($item['icon'])) {
                    $html .= '<i class="fa ' . $item['icon'] . '"></i> ';
                }
                $html .= htmlspecialchars($item['label']);
                if (isset($item['url'])) {
                    $html .= '</a>';
                }
                $html .= '</li>';
            }
        }
        
        $html .= '</ol>';
        return $html;
    }
}

if (!function_exists('render_quick_action_button')) {
    /**
     * Render quick action button
     * @param string $url Button URL
     * @param string $label Button label
     * @param string $icon Font Awesome icon class
     * @param string $color Button color: primary, success, warning, danger, info
     * @return string HTML for quick action button
     */
    function render_quick_action_button($url, $label, $icon, $color = 'primary') {
        $html = '<a href="' . htmlspecialchars($url) . '" class="btn btn-block btn-' . $color . ' btn-lg touch-friendly">';
        $html .= '<i class="fa ' . $icon . '"></i><br>';
        $html .= htmlspecialchars($label);
        $html .= '</a>';
        return $html;
    }
}

if (!function_exists('get_ui_config')) {
    /**
     * Get UI configuration value
     * @param string $key Config key
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function get_ui_config($key, $default = null) {
        $CI =& get_instance();
        $CI->config->load('ui_config', TRUE);
        $value = $CI->config->item($key, 'ui_config');
        return $value !== FALSE ? $value : $default;
    }
}

if (!function_exists('inject_ui_assets')) {
    /**
     * Inject enhanced UI CSS and JS assets
     * @return string HTML for asset links
     */
    function inject_ui_assets() {
        if (!is_enhanced_ui()) {
            return '';
        }
        
        $CI =& get_instance();
        $base_url = base_url();
        
        $html = '<!-- Enhanced UI/UX Assets -->' . "\n";
        $html .= '<link href="' . $base_url . 'public/css/hms-enhanced.css" rel="stylesheet" type="text/css" />' . "\n";
        $html .= '<script src="' . $base_url . 'public/js/hms-enhanced.js"></script>' . "\n";
        
        return $html;
    }
}

if (!function_exists('render_form_group')) {
    /**
     * Render form group with label and input
     * @param array $config Configuration array
     * @return string HTML for form group
     */
    function render_form_group($config) {
        $required = isset($config['required']) && $config['required'];
        $error = isset($config['error']) ? $config['error'] : '';
        $help = isset($config['help']) ? $config['help'] : '';
        $value = isset($config['value']) ? $config['value'] : '';
        
        $group_class = 'form-group';
        if ($required) {
            $group_class .= ' required';
        }
        if ($error) {
            $group_class .= ' has-error';
        }
        
        $html = '<div class="' . $group_class . '">';
        
        // Label
        if (isset($config['label'])) {
            $html .= '<label for="' . $config['id'] . '">' . htmlspecialchars($config['label']) . '</label>';
        }
        
        // Input
        $input_class = 'form-control';
        if (isset($config['input_class'])) {
            $input_class .= ' ' . $config['input_class'];
        }
        
        $input_attrs = 'class="' . $input_class . '" ';
        $input_attrs .= 'id="' . $config['id'] . '" ';
        $input_attrs .= 'name="' . $config['name'] . '" ';
        
        if ($required) {
            $input_attrs .= 'required ';
        }
        
        if (isset($config['placeholder'])) {
            $input_attrs .= 'placeholder="' . htmlspecialchars($config['placeholder']) . '" ';
        }
        
        $type = isset($config['type']) ? $config['type'] : 'text';
        
        if ($type === 'textarea') {
            $html .= '<textarea ' . $input_attrs . '>' . htmlspecialchars($value) . '</textarea>';
        } elseif ($type === 'select') {
            $html .= '<select ' . $input_attrs . '>';
            if (isset($config['options'])) {
                foreach ($config['options'] as $opt_value => $opt_label) {
                    $selected = ($opt_value == $value) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($opt_value) . '"' . $selected . '>';
                    $html .= htmlspecialchars($opt_label);
                    $html .= '</option>';
                }
            }
            $html .= '</select>';
        } else {
            $input_attrs .= 'type="' . $type . '" ';
            $input_attrs .= 'value="' . htmlspecialchars($value) . '" ';
            $html .= '<input ' . $input_attrs . '/>';
        }
        
        // Help text
        if ($help) {
            $html .= '<span class="help-block">' . htmlspecialchars($help) . '</span>';
        }
        
        // Error message
        if ($error) {
            $html .= '<span class="help-block error">' . htmlspecialchars($error) . '</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}
