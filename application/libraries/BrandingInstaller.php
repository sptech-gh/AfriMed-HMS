<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Branding Installer Library
 * 
 * Manages one-time filesystem initialization for the branding refactor.
 * Creates logo directories and copies platform assets without blocking 
 * standard database migrations.
 */
class BrandingInstaller
{
    /**
     * Set up directories and copy platform assets
     */
    public function install()
    {
        $CI =& get_instance();
        
        // 1) Define directories
        $facility_dir = FCPATH . 'uploads/facility_logos/default/';
        $platform_dir = FCPATH . 'public/assets/reddy/';
        
        // 2) Create facility logo directory if missing
        if (!is_dir($facility_dir)) {
            if (!mkdir($facility_dir, 0755, true)) {
                log_message('error', 'BrandingInstaller: Failed to create facility logo directory: ' . $facility_dir);
            }
        }
        
        // 3) Create platform logo directory if missing
        if (!is_dir($platform_dir)) {
            if (!mkdir($platform_dir, 0755, true)) {
                log_message('error', 'BrandingInstaller: Failed to create platform logo directory: ' . $platform_dir);
            }
        }
        
        // 4) Copy platform assets from Reddy Logos/ to public/assets/reddy/
        $src_logo = FCPATH . 'Reddy Logos/Reddy HMS app-logo.png';
        $src_wordmark = FCPATH . 'Reddy Logos/Reddy HMS logo-plain.png';
        
        $tgt_logo = $platform_dir . 'logo.png';
        $tgt_wordmark = $platform_dir . 'logo-wordmark.png';
        
        if (is_file($src_logo) && !is_file($tgt_logo)) {
            if (!copy($src_logo, $tgt_logo)) {
                log_message('error', 'BrandingInstaller: Failed to copy platform logo from ' . $src_logo);
            }
        }
        
        if (is_file($src_wordmark) && !is_file($tgt_wordmark)) {
            if (!copy($src_wordmark, $tgt_wordmark)) {
                log_message('error', 'BrandingInstaller: Failed to copy platform wordmark from ' . $src_wordmark);
            }
        }
        
        // 5) Copy existing facility logos from public/company_logo/ to uploads/facility_logos/default/ for backward compatibility
        if ($CI->db->table_exists('company_info')) {
            $query = $CI->db->get('company_info');
            $company = $query ? $query->row() : null;
            if ($company) {
                $logos = array('logo', 'login_logo', 'header_logo');
                foreach ($logos as $field) {
                    if (!empty($company->$field) && $company->$field !== 'sample.jpg') {
                        $src_file = FCPATH . 'public/company_logo/' . $company->$field;
                        $tgt_file = $facility_dir . $company->$field;
                        if (is_file($src_file) && !is_file($tgt_file)) {
                            if (!copy($src_file, $tgt_file)) {
                                log_message('error', 'BrandingInstaller: Failed to copy legacy logo ' . $src_file . ' to ' . $tgt_file);
                            }
                        }
                    }
                }
            }
        }
    }
}
