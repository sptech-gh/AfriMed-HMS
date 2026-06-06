<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');
/*
| -------------------------------------------------------------------------
| Hooks
| -------------------------------------------------------------------------
| This file lets you define "hooks" to extend CI without hacking the core
| files.  Please see the user guide for info:
|
|	http://codeigniter.com/user_guide/general/hooks.html
|
*/

// Disable ONLY_FULL_GROUP_BY for MySQL 8 compatibility
$hook['post_controller_constructor'][] = array(
    'class'    => '',
    'function' => 'disable_strict_group_by',
    'filename' => 'mysql_compat.php',
    'filepath' => 'hooks'
);

$hook['post_controller_constructor'][] = array(
    'class'    => '',
    'function' => 'shadow_governance_bootstrap',
    'filename' => 'shadow_governance.php',
    'filepath' => 'hooks'
);

$hook['post_controller_constructor'][] = array(
    'class'    => '',
    'function' => 'perf_trace_bootstrap',
    'filename' => 'perf_trace.php',
    'filepath' => 'hooks'
);

$hook['post_system'][] = array(
    'class'    => '',
    'function' => 'shadow_governance_finalize',
    'filename' => 'shadow_governance.php',
    'filepath' => 'hooks'
);

$hook['post_system'][] = array(
    'class'    => '',
    'function' => 'perf_trace_finalize',
    'filename' => 'perf_trace.php',
    'filepath' => 'hooks'
);

/* End of file hooks.php */
/* Location: ./application/config/hooks.php */