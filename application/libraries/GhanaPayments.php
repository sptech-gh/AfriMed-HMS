<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class GhanaPayments
{
	public function __construct()
	{
	}

	public function is_enabled()
	{
		$CI = get_instance();
		$cfg = $CI->config->item('ghana_payments');
		return (is_array($cfg) && isset($cfg['enabled']) && $cfg['enabled'] === true);
	}

	public function create_payment_intent($invoice_no, $amount, $currency = 'GHS')
	{
		return array(
			'success' => false,
			'message' => 'Ghana payment integrations are not activated.'
		);
	}

	public function verify_payment($reference)
	{
		return array(
			'success' => false,
			'message' => 'Ghana payment integrations are not activated.'
		);
	}
}
