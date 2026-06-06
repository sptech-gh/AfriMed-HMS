<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['shadow_exit_gate_governed_endpoints'] = array(
	array('controller' => 'nurse_module', 'method' => 'save_intake'),
	array('controller' => 'nurse_module', 'method' => 'save_output'),
	array('controller' => 'nurse_module', 'method' => 'delete_intake'),
	array('controller' => 'nurse_module', 'method' => 'delete_output'),
);

$config['shadow_exit_gate_trend_required'] = false;
$config['shadow_exit_gate_trend_days'] = 3;

$config['shadow_exit_gate_pk_unprovable_codes'] = array(
	'PRIMARY_KEY_FIELD_MISSING',
	'PRIMARY_KEY_MISSING',
	'PRIMARY_KEY_INVALID',
);

$config['shadow_exit_gate_pk_unprovable_allowed_max'] = 0;

$config['shadow_exit_gate_unprovable_allowlist'] = array(
);
