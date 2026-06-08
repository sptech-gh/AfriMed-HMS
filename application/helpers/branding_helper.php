<?php
defined('BASEPATH') OR exit('No direct script access allowed');

// Explicitly require the BrandingService library to ensure its static methods are available
require_once APPPATH . 'libraries/BrandingService.php';

/**
 * Branding Helper
 * 
 * Provides global functions for views and controllers to access
 * Reddy HMS platform branding and facility-specific settings.
 */

if (!function_exists('getPlatformName')) {
    function getPlatformName() {
        return 'Reddy HMS';
    }
}

if (!function_exists('getPlatformTagline')) {
    function getPlatformTagline() {
        return 'The Healthcare Operating System';
    }
}

if (!function_exists('getPlatformLogo')) {
    function getPlatformLogo($type = 'default') {
        if ($type === 'wordmark' || $type === 'logo-wordmark') {
            return BrandingService::platformLogoWordmark();
        }
        return BrandingService::platformLogo();
    }
}

if (!function_exists('getFacilityName')) {
    function getFacilityName() {
        return BrandingService::name();
    }
}

if (!function_exists('getFacilityLogo')) {
    function getFacilityLogo($type = 'default') {
        if ($type === 'dark' || $type === 'logo_dark') {
            return BrandingService::logoDark();
        } elseif ($type === 'light' || $type === 'logo_light') {
            return BrandingService::logoLight();
        }
        return BrandingService::logo();
    }
}

if (!function_exists('getFacilitySettings')) {
    function getFacilitySettings() {
        return BrandingService::settings();
    }
}

if (!function_exists('wrap_email_in_platform_template')) {
    /**
     * Wrap email content in a clean, facility-branded container with Reddy HMS footer
     */
    function wrap_email_in_platform_template($subject, $body) {
        $facility_name = getFacilityName();
        $facility_logo = getFacilityLogo();
        $platform_name = getPlatformName();
        $platform_tagline = getPlatformTagline();
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>' . html_escape($subject) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f6f9;
            color: #333333;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border: 1px solid #e1e4e8;
        }
        .header {
            background-color: #ffffff;
            padding: 20px;
            text-align: center;
            border-bottom: 2px solid #3366cc;
        }
        .header img {
            max-height: 50px;
            display: inline-block;
            margin-bottom: 10px;
        }
        .header h1 {
            font-size: 20px;
            margin: 0;
            color: #1a1a1a;
        }
        .content {
            padding: 30px;
            line-height: 1.6;
            font-size: 15px;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #777777;
            border-top: 1px solid #e1e4e8;
        }
        .platform-badge {
            margin-top: 10px;
            display: inline-block;
            font-weight: bold;
            color: #3366cc;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            ' . (!empty($facility_logo) ? '<img src="' . $facility_logo . '" alt="' . html_escape($facility_name) . '">' : '') . '
            <h1>' . html_escape($facility_name) . '</h1>
        </div>
        <div class="content">
            ' . $body . '
        </div>
        <div class="footer">
            <p>This is an automated notification from ' . html_escape($facility_name) . '.</p>
            <hr style="border: 0; border-top: 1px solid #e1e4e8; margin: 15px 0;">
            <p class="platform-badge">Powered by ' . html_escape($platform_name) . '</p>
            <p>' . html_escape($platform_tagline) . '</p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }
}
