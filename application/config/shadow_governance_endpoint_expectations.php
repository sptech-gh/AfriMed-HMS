<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['shadow_governance_endpoint_expectations_version'] = 'V0';

$config['shadow_governance_endpoint_expectations_v0'] = array(
	array(
		'controller' => 'nursing',
		'method_regex' => 'api_save_vitalSign',
		'endpoint_write_expectation' => 'WRITE_EXPECTED',
		'intent' => 'CREATE'
	),
	array(
		'controller' => 'nursing',
		'method_regex' => 'api_save_note',
		'endpoint_write_expectation' => 'WRITE_EXPECTED',
		'intent' => 'CREATE'
	),
	array(
		'controller' => 'nursing',
		'method_regex' => 'api_save_procedure',
		'endpoint_write_expectation' => 'WRITE_EXPECTED',
		'intent' => 'CREATE'
	),
	array(
		'controller' => 'nurse_module',
		'method_regex' => 'save_(intake|output)',
		'endpoint_write_expectation' => 'WRITE_EXPECTED',
		'intent' => 'CREATE'
	),
	array(
		'controller' => 'nurse_module',
		'method_regex' => 'delete_(intake|output)',
		'endpoint_write_expectation' => 'WRITE_EXPECTED',
		'intent' => 'DELETE'
	),
	array(
		'controller' => 'nurse_module',
		'method_regex' => '(intake_output|intake_history|intake_detail|io_balance_history|io_balance_detail)',
		'endpoint_write_expectation' => 'READ_ONLY_EXPECTED',
		'intent' => 'READ'
	)
);
