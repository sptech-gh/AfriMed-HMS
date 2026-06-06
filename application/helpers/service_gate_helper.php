<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Service Gate Helper
 * 
 * Convenience functions for enforcing payment-before-service in controllers.
 * These functions automatically handle gate checking, redirecting to payment pages,
 * and showing appropriate messages.
 * 
 * Usage in controllers:
 *   if (!check_service_gate('LAB', $lab_id, $iop_id)) {
 *       return; // Function already handled redirect
 *   }
 * 
 * @author HMS Unified Billing Team
 */

/**
 * Check service gate and handle blocked status automatically
 * 
 * @param string $module Module code: LAB, PHARMACY, SONOGRAPHY, RADIOLOGY
 * @param string $source_ref Reference ID from source module
 * @param string $iop_id Visit ID (for generating payment URL)
 * @param string $patient_no Patient number (optional)
 * @param CI_Controller $ci CI instance (if not in controller context)
 * @return bool True if service can proceed, false if redirect occurred
 */
function check_service_gate($module, $source_ref, $iop_id = null, $patient_no = null, $ci = null)
{
	if ($ci === null) {
		$ci = &get_instance();
	}
	
	$ci->load->model('app/service_gate_model', 'gate');
	
	$result = $ci->gate->check_service($module, $source_ref, $iop_id, $patient_no);
	
	if ($result['allowed']) {
		return true;
	}
	
	// Service is blocked - set flash message and redirect
	$message = '<div class="alert alert-warning">';
	$message .= '<i class="fa fa-lock"></i> <strong>Payment Required</strong><br>';
	$message .= htmlspecialchars($result['reason']) . '<br>';
	
	if ($result['action_required']) {
		$message .= '<small>' . htmlspecialchars($result['action_required']) . '</small>';
	}
	
	$message .= '</div>';
	
	$ci->session->set_flashdata('message', $message);
	
	// Redirect to payment page if available
	if ($result['payment_url']) {
		redirect($result['payment_url']);
	} elseif ($iop_id) {
		// Fallback to general billing
		redirect(base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id));
	} else {
		// Last resort - go to dashboard
		redirect(base_url() . 'app/dashboard');
	}
	
	return false;
}

/**
 * Quick check - returns boolean without handling redirect
 * Use when you want to handle the blocked status manually
 * 
 * @param string $module Module code
 * @param string $source_ref Reference ID
 * @param CI_Controller $ci CI instance
 * @return bool True if service can proceed
 */
function can_proceed_service($module, $source_ref, $ci = null)
{
	if ($ci === null) {
		$ci = &get_instance();
	}
	
	$ci->load->model('app/service_gate_model', 'gate');
	return $ci->gate->can_proceed($module, $source_ref);
}

/**
 * Get detailed gate information without redirect
 * Use for displaying status on worklists/views
 * 
 * @param string $module Module code
 * @param string $source_ref Reference ID
 * @param string $iop_id Visit ID (optional)
 * @param string $patient_no Patient number (optional)
 * @param CI_Controller $ci CI instance
 * @return array Gate check result
 */
function get_service_gate_info($module, $source_ref, $iop_id = null, $patient_no = null, $ci = null)
{
	if ($ci === null) {
		$ci = &get_instance();
	}
	
	$ci->load->model('app/service_gate_model', 'gate');
	return $ci->gate->check_service($module, $source_ref, $iop_id, $patient_no);
}

/**
 * Check laboratory gate and auto-redirect if blocked
 * Convenience function for laboratory controller
 * 
 * @param int $lab_request_id Lab request ID
 * @param string $iop_id Visit ID
 * @param CI_Controller $ci CI instance
 * @return bool True if can proceed
 */
function check_lab_gate($lab_request_id, $iop_id, $ci = null)
{
	return check_service_gate('LAB', (string)$lab_request_id, $iop_id, null, $ci);
}

/**
 * Check pharmacy gate and auto-redirect if blocked
 * Convenience function for pharmacy controller
 * 
 * @param int $medication_id Medication ID
 * @param string $iop_id Visit ID
 * @param string $patient_no Patient number
 * @param CI_Controller $ci CI instance
 * @return bool True if can proceed
 */
function check_pharmacy_gate($medication_id, $iop_id, $patient_no = null, $ci = null)
{
	return check_service_gate('PHARMACY', (string)$medication_id, $iop_id, $patient_no, $ci);
}

/**
 * Check sonography gate and auto-redirect if blocked
 * Convenience function for sonography controller
 * 
 * @param int $sono_request_id Sonography request ID
 * @param string $iop_id Visit ID
 * @param CI_Controller $ci CI instance
 * @return bool True if can proceed
 */
function check_sonography_gate($sono_request_id, $iop_id, $ci = null)
{
	return check_service_gate('SONOGRAPHY', (string)$sono_request_id, $iop_id, null, $ci);
}

/**
 * Create a gate exception (emergency bypass)
 * Only doctors and admins can create exceptions
 * 
 * @param string $module Module code
 * @param string $source_ref Reference ID
 * @param string $exception_type EMERGENCY, WAIVER, NHIS, INSURANCE, STAFF, DEFERRED
 * @param string $reason Justification
 * @param string $patient_no Patient number (optional)
 * @param string $iop_id Visit ID (optional)
 * @param CI_Controller $ci CI instance
 * @return array Result with success status
 */
function create_gate_exception($module, $source_ref, $exception_type, $reason, $patient_no = null, $iop_id = null, $ci = null)
{
	if ($ci === null) {
		$ci = &get_instance();
	}
	
	$ci->load->model('app/service_gate_model', 'gate');
	
	$user_id = $ci->session->userdata('user_id');
	
	return $ci->gate->create_exception($module, $source_ref, $exception_type, $reason, $user_id, $patient_no, $iop_id);
}

/**
 * Get HTML badge for service gate status
 * Use in worklists to show payment status
 * 
 * @param string $status Gate status: BLOCKED, RELEASED, EXPIRED
 * @return string HTML badge
 */
function service_gate_badge($status)
{
	switch ($status) {
		case 'RELEASED':
			return '<span class="label label-success"><i class="fa fa-unlock"></i> Ready</span>';
		case 'BLOCKED':
			return '<span class="label label-warning"><i class="fa fa-lock"></i> Payment Required</span>';
		case 'EXPIRED':
			return '<span class="label label-danger"><i class="fa fa-clock-o"></i> Expired</span>';
		case 'BYPASSED':
			return '<span class="label label-info"><i class="fa fa-exclamation-circle"></i> Exception</span>';
		default:
			return '<span class="label label-default">Unknown</span>';
	}
}

/**
 * Get payment action button HTML
 * Generates appropriate button for worklists
 * 
 * @param string $iop_id Visit ID
 * @param string $status Gate status
 * @return string HTML button
 */
function service_gate_action_button($iop_id, $status = 'BLOCKED')
{
	if ($status === 'RELEASED' || $status === 'BYPASSED') {
		return '<span class="text-success"><i class="fa fa-check"></i> Ready to Process</span>';
	}
	
	$url = base_url() . 'app/pos/pos_visit/' . url_safe_id($iop_id);
	return '<a href="' . $url . '" class="btn btn-warning btn-xs">' .
		   '<i class="fa fa-credit-card"></i> Bill Now</a>';
}
