<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['shadow_audit_enabled'] = true;
$config['shadow_audit_table'] = 'shadow_audit_log';

$config['shadow_alert_enabled'] = true;
$config['shadow_alert_severity_threshold'] = 'CRITICAL';
$config['shadow_alert_rate_limit_seconds'] = 60;
