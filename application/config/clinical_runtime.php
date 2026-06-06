<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['clinical_ctm_enabled'] = false;
$config['clinical_intake_ctm_dual_write_enabled'] = false;
$config['clinical_failure_injection_enabled'] = false;
$config['clinical_ctm_lease_seconds'] = 60;
$config['clinical_ctm_lease_owner_prefix'] = 'hms-clinical-cli';

$config['clinical_required_write_tables'] = array(
    'clinical_events',
    'clinical_idempotency_records',
    'clinical_stream_locks',
    'nursing_intake',
);

$config['clinical_required_unique_constraints'] = array(
    array(
        'table' => 'clinical_idempotency_records',
        'columns' => array('idempotency_key'),
    ),
    array(
        'table' => 'clinical_events',
        'columns' => array('iop_id', 'stream_version'),
    ),
);

$config['clinical_required_foreign_keys'] = array(
    array(
        'table' => 'nursing_intake',
        'column' => 'event_id',
        'referenced_table' => 'clinical_events',
        'referenced_column' => 'event_id',
    ),
);
