<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Branding Service Class
 * 
 * Centralizes all platform (Reddy HMS) and facility branding settings
 * and encapsulates the fallback resolution logic.
 */
class BrandingService
{
    private static $settings = null;
    private static $initialized = false;

    /**
     * Initialize branding settings from database
     */
    private static function init()
    {
        if (self::$initialized) {
            return;
        }

        $CI =& get_instance();
        
        // 1) Set defaults
        self::$settings = array(
            'facility_name' => 'Healthcare Facility',
            'facility_short_name' => '',
            'facility_tagline' => '',
            'logo_path' => '',
            'logo_dark' => '',
            'logo_light' => '',
            'address' => '',
            'phone' => '',
            'email' => '',
            'website' => '',
            'tin' => '',
            'registration_number' => '',
            'footer_note' => '',
        );

        // 2) Try loading from facility_settings
        if ($CI->db->table_exists('facility_settings')) {
            $query = $CI->db->get('facility_settings');
            $row = $query ? $query->row_array() : null;
            if ($row) {
                foreach ($row as $key => $val) {
                    if ($key !== 'id') {
                        self::$settings[$key] = $val;
                    }
                }
                self::$initialized = true;
                return;
            }
        }

        // 3) Fall back to company_info if facility_settings is empty or doesn't exist
        if ($CI->db->table_exists('company_info')) {
            $query = $CI->db->get('company_info');
            $company = $query ? $query->row() : null;
            if ($company) {
                self::$settings['facility_name'] = !empty($company->company_name) ? $company->company_name : 'Healthcare Facility';
                self::$settings['facility_short_name'] = !empty($company->site_title) ? $company->site_title : '';
                self::$settings['facility_tagline'] = !empty($company->hospital_tagline) ? $company->hospital_tagline : '';
                self::$settings['logo_path'] = (!empty($company->logo) && $company->logo !== 'sample.jpg') ? $company->logo : '';
                self::$settings['logo_dark'] = !empty($company->login_logo) ? $company->login_logo : '';
                self::$settings['logo_light'] = !empty($company->header_logo) ? $company->header_logo : '';
                self::$settings['address'] = !empty($company->company_address) ? $company->company_address : '';
                self::$settings['phone'] = !empty($company->company_contactNo) ? $company->company_contactNo : '';
                self::$settings['email'] = !empty($company->company_email) ? $company->company_email : '';
                self::$settings['tin'] = !empty($company->TIN) ? $company->TIN : '';
            }
        }

        self::$initialized = true;
    }

    /**
     * Get the facility name with proper fallback order
     * 
     * @return string
     */
    public static function name()
    {
        self::init();
        return !empty(self::$settings['facility_name']) ? self::$settings['facility_name'] : 'Healthcare Facility';
    }

    /**
     * Get the facility short name / browser tab title
     * 
     * @return string
     */
    public static function shortName()
    {
        self::init();
        return !empty(self::$settings['facility_short_name']) ? self::$settings['facility_short_name'] : self::name();
    }

    /**
     * Get the facility tagline
     * 
     * @return string
     */
    public static function tagline()
    {
        self::init();
        return !empty(self::$settings['facility_tagline']) ? self::$settings['facility_tagline'] : '';
    }

    /**
     * Get the main facility logo URL (with fallback to platform logo)
     * 
     * @return string
     */
    public static function logo()
    {
        self::init();
        if (!empty(self::$settings['logo_path'])) {
            return base_url() . 'uploads/facility_logos/default/' . self::$settings['logo_path'];
        }
        return self::platformLogo();
    }

    /**
     * Get the facility dark logo URL
     * 
     * @return string
     */
    public static function logoDark()
    {
        self::init();
        if (!empty(self::$settings['logo_dark'])) {
            return base_url() . 'uploads/facility_logos/default/' . self::$settings['logo_dark'];
        }
        return self::logo();
    }

    /**
     * Get the facility light logo URL
     * 
     * @return string
     */
    public static function logoLight()
    {
        self::init();
        if (!empty(self::$settings['logo_light'])) {
            return base_url() . 'uploads/facility_logos/default/' . self::$settings['logo_light'];
        }
        return self::logo();
    }

    /**
     * Get the platform (Reddy HMS) logo URL
     * 
     * @return string
     */
    public static function platformLogo()
    {
        return base_url() . 'public/assets/reddy/logo.png';
    }

    /**
     * Get the platform wordmark URL
     * 
     * @return string
     */
    public static function platformLogoWordmark()
    {
        return base_url() . 'public/assets/reddy/logo-wordmark.png';
    }

    /**
     * Get all raw branding settings
     * 
     * @return array
     */
    public static function settings()
    {
        self::init();
        return self::$settings;
    }
}
