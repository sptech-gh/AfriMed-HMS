<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * URL Safe Helper
 * 
 * Provides functions for URL-safe ID handling across the HMS system.
 * Single source of truth for ID encoding/decoding.
 * 
 * @package HMS
 * @subpackage Helpers
 */

/**
 * Encode an ID for use in URLs
 * Replaces spaces with dashes for URL safety
 * 
 * @param string $id The ID to encode
 * @return string URL-safe ID
 */
if (!function_exists('url_safe_id')) {
    function url_safe_id($id) {
        $id = (string)$id;
        // Replace spaces with dashes (for legacy IDs that contain spaces)
        $id = str_replace(' ', '-', $id);
        // Keep dashes, alphanumerics and underscores only
        $id = preg_replace('/[^A-Za-z0-9\-_]/', '', $id);
        return $id;
    }
}

/**
 * Decode a URL-safe ID back to original format
 * Replaces dashes with spaces (for legacy data compatibility)
 * 
 * @param string $id The URL-safe ID to decode
 * @return string Original ID format
 */
if (!function_exists('url_decode_id')) {
    function url_decode_id($id) {
        $id = (string)$id;
        // urldecode handles %20, %2D etc
        $id = urldecode($id);
        // Do NOT replace dashes — IDs use dashes natively (OP-000004)
        return $id;
    }
}

/**
 * Generate a clean ID without spaces
 * Use this for NEW ID generation going forward
 * 
 * @param string $prefix The prefix (OP, IP, LAB, etc.)
 * @param int $number The sequential number
 * @param int $padding Number of digits to pad (default 6)
 * @return string Clean ID without spaces
 */
if (!function_exists('generate_clean_id')) {
    function generate_clean_id($prefix, $number, $padding = 6) {
        $prefix = strtoupper(trim((string)$prefix));
        $number = (int)$number;
        // No space between prefix and number
        return $prefix . str_pad($number, $padding, '0', STR_PAD_LEFT);
    }
}

/**
 * Sanitize an existing ID for database lookup
 * Handles both old format (with spaces) and new format (without spaces)
 * 
 * @param string $id The ID to sanitize
 * @return string Sanitized ID ready for DB lookup
 */
if (!function_exists('sanitize_id_for_db')) {
    function sanitize_id_for_db($id) {
        $id = (string)$id;
        // First urldecode in case of %2D etc
        $id = urldecode($id);
        // Do NOT convert dashes to spaces — IDs in this system use dashes natively (e.g. OP-000004)
        // Only convert %20 / + (encoded spaces) which urldecode already handles
        return trim($id);
    }
}

/**
 * Check if an ID contains problematic characters
 * 
 * @param string $id The ID to check
 * @return bool True if ID has problematic characters
 */
if (!function_exists('id_has_encoding_issues')) {
    function id_has_encoding_issues($id) {
        $id = (string)$id;
        // Check for spaces, %, or other URL-problematic characters
        return (strpos($id, ' ') !== false || 
                strpos($id, '%') !== false ||
                strpos($id, '&') !== false ||
                strpos($id, '#') !== false ||
                strpos($id, '+') !== false);
    }
}

/**
 * Build a safe URL with properly encoded IDs
 * 
 * @param string $path The base path
 * @param array $segments Array of URL segments (IDs)
 * @return string Complete URL with safe IDs
 */
if (!function_exists('build_safe_url')) {
    function build_safe_url($path, $segments = array()) {
        $CI =& get_instance();
        $url = base_url() . ltrim($path, '/');
        foreach ($segments as $segment) {
            $url .= '/' . url_safe_id($segment);
        }
        return $url;
    }
}
