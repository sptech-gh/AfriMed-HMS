<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config['lab_group_ids'] = array(8, 9, 10, 11, 12, 13, 14, 15);

$config['order_states'] = array(
	'REQUESTED',
	'BILLED',
	'PAID',
	'AUTHORIZED',
	'IN_PROGRESS',
	'COMPLETED',
	'VERIFIED',
	'CANCELLED'
);

$config['order_state_transitions'] = array(
	'REQUESTED' => array('BILLED', 'CANCELLED'),
	'BILLED' => array('PAID', 'AUTHORIZED', 'CANCELLED'),
	'PAID' => array('IN_PROGRESS', 'CANCELLED'),
	'AUTHORIZED' => array('IN_PROGRESS'),
	'IN_PROGRESS' => array('COMPLETED'),
	'COMPLETED' => array('VERIFIED'),
	'VERIFIED' => array(),
	'CANCELLED' => array(),
);

$config['order_state_aliases'] = array(
	'REPORTED_TEXT' => 'COMPLETED',
	'REPORTED_PDF'  => 'COMPLETED',
	'REPORTED_BOTH' => 'COMPLETED',
	'REPORTED'      => 'COMPLETED',
	'SCHEDULED'     => 'IN_PROGRESS',
	'PERFORMED'     => 'IN_PROGRESS',
	'NO_SHOW'       => 'CANCELLED',
	'RESCHEDULED'   => 'REQUESTED',
	'FAILED_SCAN'   => 'IN_PROGRESS',
);

$config['lab_consolidated_release_mode'] = false;
$config['lab_release_shadow_mode'] = true;
$config['lab_release_diagnostics_enabled'] = true;
$config['lab_release_batch_mode_enabled'] = true;
$config['lab_release_snapshot_read_enabled'] = true;
$config['lab_release_enforce_no_partial'] = true;

$config['PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED'] = false;
$config['PHARMACY_OVERRIDE_AUDIT_REQUIRED'] = false;
$config['PHARMACY_ENFORCE_DISPENSE_PAYMENT_MATCH'] = false;
$config['PHARMACY_BLOCK_INVOICE_EDIT_WHEN_RX_VERIFIED'] = false;
$config['PHARMACY_STRICT_PRICE_ON_LEGACY_INVOICE_LINES'] = false;
$config['PHARMACY_STRICT_QTY_MATCH_RX_ON_LEGACY_INVOICE_LINES'] = false;
$config['BILLING_ENFORCE_INVOICE_FINALIZATION_LOCK'] = false;

$config['BILLING_FACADE_IMMUTABLE_AFTER_RECEIPT'] = false;
$config['PHARMACY_PHASE2_ENFORCEMENT_KILL_SWITCH'] = false;

$config['BILLING_DISPOSITION_SHADOW'] = true;
$config['BILLING_DISPOSITION_ENFORCEMENT'] = false;

$config['QUANTITY_SEMANTICS_VERSION_DEFAULT'] = 1;
$config['QUANTITY_SEMANTICS_VERSION_DECIMAL'] = 2;

$config['ENABLE_DECIMAL_PRESCRIBED_QTY'] = false;
$config['ENABLE_DECIMAL_INVOICE_QTY'] = false;
$config['ENABLE_DECIMAL_DISPENSE_QTY'] = false;

$config['BILLING_STRICT_NUMERIC_INPUT'] = false;

$config['ENABLE_UOM_NORMALIZATION'] = false;
$config['ENABLE_IMMUTABLE_DISPENSE_LEDGER'] = false;

$config['ENABLE_DECIMAL_PRESCRIPTIONS'] = false;
$config['ENABLE_STRUCTURED_STRENGTH_CALCULATION'] = false;
