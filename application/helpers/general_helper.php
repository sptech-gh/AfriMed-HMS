<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * General Helper
 * 
 * Common utility functions used across the HMS application.
 * 
 * @package HMS
 * @subpackage Helpers
 */

/**
 * Format currency amount (GHS)
 * 
 * @param float $amount
 * @param int $decimals
 * @return string
 */
if (!function_exists('format_currency')) {
    function format_currency($amount, $decimals = 2) {
        return 'GHS ' . number_format((float)$amount, $decimals);
    }
}

/**
 * Format date for display
 * 
 * @param string $date
 * @param string $format
 * @return string
 */
if (!function_exists('format_date')) {
    function format_date($date, $format = 'd M Y') {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($date));
    }
}

/**
 * Format datetime for display
 * 
 * @param string $datetime
 * @param string $format
 * @return string
 */
if (!function_exists('format_datetime')) {
    function format_datetime($datetime, $format = 'd M Y h:i A') {
        if (empty($datetime) || $datetime === '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($datetime));
    }
}

/**
 * Get payment status badge HTML
 * 
 * @param string $status
 * @return string HTML badge
 */
if (!function_exists('payment_status_badge')) {
    function payment_status_badge($status) {
        $status = strtoupper(trim((string)$status));
        $badges = array(
            'PAID'      => '<span class="label label-success">PAID</span>',
            'PARTIAL'   => '<span class="label label-warning">PARTIAL</span>',
            'PENDING'   => '<span class="label label-danger">PENDING</span>',
            'UNPAID'    => '<span class="label label-danger">UNPAID</span>',
            'CANCELLED' => '<span class="label label-default">CANCELLED</span>',
            'WAIVED'    => '<span class="label label-info">WAIVED</span>',
        );
        return isset($badges[$status]) ? $badges[$status] : '<span class="label label-default">' . htmlspecialchars($status) . '</span>';
    }
}

/**
 * Truncate text to specified length
 * 
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
if (!function_exists('truncate_text')) {
    function truncate_text($text, $length = 50, $suffix = '...') {
        $text = (string)$text;
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length) . $suffix;
    }
}
