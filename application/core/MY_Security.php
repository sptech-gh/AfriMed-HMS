<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * HMS Security Extensions
 *
 * Adds high-signal logging for CSRF failures so "The action you have requested
 * is not allowed." can be diagnosed with facts (missing cookie, missing POST token,
 * expired token, cross-host cookie mismatch, etc.) instead of guesswork.
 */
class MY_Security extends CI_Security
{
	/**
	 * Override CSRF failure handler to log the real reason context.
	 *
	 * NOTE: We intentionally do NOT weaken CSRF protection here.
	 */
	public function csrf_show_error()
	{
		$tokenName = config_item('csrf_token_name');
		$cookieName = config_item('cookie_prefix').config_item('csrf_cookie_name');

		$method = isset($_SERVER['REQUEST_METHOD']) ? (string)$_SERVER['REQUEST_METHOD'] : '';
		$uri = '';
		try {
			$uriObj = load_class('URI', 'core');
			$uri = $uriObj ? (string)$uriObj->uri_string() : '';
		} catch (Exception $e) {
			$uri = '';
		}

		$userId = '';
		try {
			if (class_exists('CI_Controller', false)) {
				$CI =& get_instance();
				if ($CI && isset($CI->session)) {
					$userId = (string)$CI->session->userdata('user_id');
				}
			}
		} catch (Throwable $e) {
			$userId = '';
		}

		$hasPostToken = isset($_POST[$tokenName]) ? 1 : 0;
		$hasCookieToken = isset($_COOKIE[$cookieName]) ? 1 : 0;
		$postTokenPreview = $hasPostToken ? substr((string)$_POST[$tokenName], 0, 8) : '';
		$cookieTokenPreview = $hasCookieToken ? substr((string)$_COOKIE[$cookieName], 0, 8) : '';

		log_message(
			'error',
			'CSRF_FAIL method='.$method
			.' uri='.$uri
			.' user_id='.$userId
			.' post_token_present='.$hasPostToken
			.' cookie_token_present='.$hasCookieToken
			.' post_token_prefix='.$postTokenPreview
			.' cookie_token_prefix='.$cookieTokenPreview
		);

		parent::csrf_show_error();
	}
}
