<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['shadow_governance_registry_version'] = 'V0';

$config['shadow_governance_context_keys'] = array(
	'iop_id',
	'opd_no',
	'patient_no',
	'patient_id',
	'visit_id',
	'iop_med_id',
	'bed_name',
	'procedure_id',
	'item_ref'
);

$config['shadow_governance_registry_v0'] = array(
	'VITALS' => array(
		'domain_name' => 'VITALS',
		'controllers' => array('nurse_module', 'opd', 'ipd'),
		'method_patterns' => array('.*vital.*'),
		'tables_owned' => array('iop_vital_parameters', 'patient_details_iop'),
		'transaction_class' => 'ATOMIC',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => false,
		'invariants' => array('VITAL_ENTRY_MUST_UPDATE_SSOT_FLAG')
	),
	'MEDICATION' => array(
		'domain_name' => 'MEDICATION',
		'controllers' => array('nurse_module'),
		'method_patterns' => array('.*medication.*', '.*admin.*', '.*drug.*'),
		'tables_owned' => array('iop_medication', 'iop_medication_administration'),
		'transaction_class' => 'RECOVERABLE',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => true,
		'invariants' => array('ADMIN_EVENT_REQUIRES_ORDER_EXISTENCE')
	),
	'INTAKE' => array(
		'domain_name' => 'INTAKE',
		'controllers' => array('nurse_module'),
		'method_patterns' => array('.*intake.*'),
		'tables_owned' => array('iop_intake_record'),
		'transaction_class' => 'ATOMIC',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => false,
		'invariants' => array('NO_PHYSICAL_DELETE_ALLOWED')
	),
	'OUTPUT' => array(
		'domain_name' => 'OUTPUT',
		'controllers' => array('nurse_module'),
		'method_patterns' => array('.*output.*'),
		'tables_owned' => array('iop_output_record'),
		'transaction_class' => 'ATOMIC',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => false,
		'invariants' => array('NO_PHYSICAL_DELETE_ALLOWED')
	),
	'NURSE_NOTES' => array(
		'domain_name' => 'NURSE_NOTES',
		'controllers' => array('nurse_module', 'ipd'),
		'method_patterns' => array('.*note.*', '.*progress.*'),
		'tables_owned' => array('iop_nurse_notes'),
		'transaction_class' => 'EVENTUAL',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => false,
		'invariants' => array('RECORD_CREATED_ACTIVE', 'RECORD_SOFT_DELETED', 'NO_PHYSICAL_DELETE_ALLOWED', 'NOTES_ARE_APPEND_ONLY_LOGICAL')
	),
	'ROOM_TRANSFER' => array(
		'domain_name' => 'ROOM_TRANSFER',
		'controllers' => array('nurse_module', 'ipd'),
		'method_patterns' => array('.*room.*', '.*transfer.*'),
		'tables_owned' => array('iop_room_transfer', 'room_beds', 'patient_details_iop'),
		'transaction_class' => 'RECOVERABLE',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => true,
		'invariants' => array('ROOM_STATE_MUST_RECONCILE_PATIENT_LINK')
	),
	'PROCEDURE' => array(
		'domain_name' => 'PROCEDURE',
		'controllers' => array('nurse_module', 'ipd', 'nursing'),
		'method_patterns' => array('.*bed.*side.*procedure.*', '.*bed_side.*', 'api_save_procedure'),
		'tables_owned' => array('iop_bed_side_procedure'),
		'transaction_class' => 'RECOVERABLE',
		'enforcement_tier' => 1,
		'audit_required' => true,
		'compensation_required' => true,
		'invariants' => array('RECORD_CREATED_ACTIVE', 'RECORD_SOFT_DELETED', 'NO_PHYSICAL_DELETE_ALLOWED'),
		'observed_only_tables' => array('iop_operation_theater'),
		'observed_only_reason' => 'LEGACY_REPLACEMENT_ENGINE'
	)
);
