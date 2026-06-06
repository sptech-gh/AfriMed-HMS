<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['shadow_governance_expectedset_version'] = 'V0';

$config['shadow_governance_expectedset_v0'] = array(
	'PROCEDURE' => array(
		'version' => 'V0',
		'domain' => 'PROCEDURE',
		'intent_rules' => array(
			'create_method_regex' => '^(save_bed_side_procedure|bed_side_procedure|api_save_procedure)$',
			'delete_method_regex' => '^delete_bed_side$'
		),
		'primary_key_map' => array(
			'iop_bed_side_procedure' => 'bed_pro_id'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_bed_side_procedure',
					'required_ops' => array('INSERT'),
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_bed_side_procedure',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_operation_theater',
					'forbidden_ops' => array('INSERT', 'UPDATE', 'DELETE'),
					'violation' => 'LEGACY_REPLACEMENT_ENGINE_EXCLUDED',
					'severity' => 'INFO'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_bed_side_procedure', 'ops' => array('INSERT'))
			),
			'allowed_side_effects' => array(
				array('table' => 'clinical_idempotency_records', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CTM_IDEMPOTENCY_INFRA'),
				array('table' => 'clinical_stream_locks', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CTM_STREAM_LOCK_INFRA'),
				array('table' => 'billing_transactions', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'PROCEDURE_BILLING_PROJECTION'),
				array('table' => 'billing_master', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'PROCEDURE_BILLING_PROJECTION'),
				array('table' => 'billing_items', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'PROCEDURE_BILLING_PROJECTION'),
				array('table' => 'iop_billable_item_lock', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'PROCEDURE_BILLING_GATE_PROJECTION'),
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_bed_side_procedure:INSERT' => 'NOT_ALLOWED',
				'billing_transactions:INSERT' => 'ALLOWED',
				'billing_transactions:UPDATE' => 'ALLOWED',
				'billing_master:INSERT' => 'ALLOWED',
				'billing_master:UPDATE' => 'ALLOWED',
				'billing_items:INSERT' => 'ALLOWED',
				'billing_items:UPDATE' => 'ALLOWED',
				'iop_billable_item_lock:INSERT' => 'ALLOWED',
				'iop_billable_item_lock:UPDATE' => 'ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_bed_side_procedure')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_bed_side_procedure',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_bed_side_procedure',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_operation_theater',
					'forbidden_ops' => array('INSERT', 'UPDATE', 'DELETE'),
					'violation' => 'LEGACY_REPLACEMENT_ENGINE_EXCLUDED',
					'severity' => 'INFO'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_bed_side_procedure', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_bed_side_procedure',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_bed_side_procedure:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_bed_side_procedure')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_bed_side_procedure')
				)
			)
		)
	),
	'NURSE_NOTES' => array(
		'version' => 'V0',
		'domain' => 'NURSE_NOTES',
		'intent_rules' => array(
			'create_method_regex' => '^(save_nurse_progress_note|api_save_note)$',
			'delete_method_regex' => '^delete_nurse_progress$'
		),
		'primary_key_map' => array(
			'iop_nurse_notes' => 'nurse_notes_id'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_nurse_notes',
					'required_ops' => array('INSERT'),
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_nurse_notes',
					'forbidden_ops' => array('UPDATE'),
					'violation' => 'NOTES_ARE_APPEND_ONLY_LOGICAL',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_nurse_notes',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_nurse_notes', 'ops' => array('INSERT'))
			),
			'allowed_side_effects' => array(
				array('table' => 'clinical_idempotency_records', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CTM_IDEMPOTENCY_INFRA'),
				array('table' => 'clinical_stream_locks', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CTM_STREAM_LOCK_INFRA'),
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_nurse_notes:INSERT' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_nurse_notes')
				),
				array(
					'name' => 'NOTES_ARE_APPEND_ONLY_LOGICAL',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_nurse_notes')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_nurse_notes',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_nurse_notes',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_nurse_notes', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_nurse_notes',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_nurse_notes:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_nurse_notes')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_nurse_notes')
				)
			)
		)
	),
	'ROOM_TRANSFER' => array(
		'version' => 'V0',
		'domain' => 'ROOM_TRANSFER',
		'intent_rules' => array(
			'create_method_regex' => '^(save_room_transfer|room_transfer)$',
			'delete_method_regex' => 'delete_room_transfer'
		),
		'primary_key_map' => array(
			'iop_room_transfer' => 'transfer_id',
			'room_beds' => 'room_bed_id',
			'patient_details_iop' => 'IO_ID'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_room_transfer',
					'required_ops' => array('INSERT'),
					'severity_if_missing' => 'CRITICAL'
				),
				array(
					'table' => 'room_beds',
					'required_ops' => array('UPDATE'),
					'severity_if_missing' => 'CRITICAL'
				),
				array(
					'table' => 'patient_details_iop',
					'required_ops' => array('UPDATE'),
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_room_transfer',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'room_beds',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'patient_details_iop',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_room_transfer', 'ops' => array('INSERT')),
				array('table' => 'room_beds', 'ops' => array('UPDATE')),
				array('table' => 'patient_details_iop', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'iop_room_charge', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'IPD_ROOM_CHARGE_GENERATION'),
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_room_transfer:INSERT' => 'NOT_ALLOWED',
				'room_beds:UPDATE' => 'ALLOWED',
				'patient_details_iop:UPDATE' => 'NOT_ALLOWED',
				'iop_room_charge:INSERT' => 'ALLOWED',
				'iop_room_charge:UPDATE' => 'ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_room_transfer')
				),
				array(
					'name' => 'ROOM_STATE_MUST_RECONCILE_PATIENT_LINK',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('room_beds', 'patient_details_iop')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_room_transfer',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_room_transfer',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'room_beds',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'patient_details_iop',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_room_transfer', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_room_transfer',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_room_transfer:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_room_transfer')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_room_transfer', 'room_beds', 'patient_details_iop')
				)
			)
		)
	),
	'MEDICATION' => array(
		'version' => 'V0',
		'domain' => 'MEDICATION',
		'intent_rules' => array(
			'create_method_regex' => 'save_medication(_admin)?',
			'delete_method_regex' => 'delete_medication'
		),
		'primary_key_map' => array(
			'iop_medication' => 'iop_med_id',
			'iop_medication_administration' => 'admin_id'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_medication',
					'required_ops' => array('INSERT'),
					'when' => "method is 'save_medication'",
					'severity_if_missing' => 'CRITICAL'
				),
				array(
					'table' => 'iop_medication_administration',
					'required_ops' => array('INSERT'),
					'when' => "method is 'save_medication_admin'",
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_medication',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_medication_administration',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_medication', 'ops' => array('INSERT')),
				array('table' => 'iop_medication_administration', 'ops' => array('INSERT'))
			),
			'allowed_side_effects' => array(
				array('table' => 'pharmacy_billing_queue', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'PHARMACY_BILLING_QUEUE'),
				array('table' => 'billing_transactions', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'UNIFIED_BILLING_QUEUE'),
				array('table' => 'nhis_audit_log', 'ops' => array('INSERT'), 'reason' => 'NHIS_AUDIT'),
				array('table' => 'prescription_workflow', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CDS_WORKFLOW'),
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_medication:INSERT' => 'NOT_ALLOWED',
				'iop_medication_administration:INSERT' => 'NOT_ALLOWED',
				'pharmacy_billing_queue:INSERT' => 'ALLOWED',
				'pharmacy_billing_queue:UPDATE' => 'ALLOWED',
				'billing_transactions:INSERT' => 'ALLOWED',
				'billing_transactions:UPDATE' => 'ALLOWED',
				'nhis_audit_log:INSERT' => 'ALLOWED',
				'prescription_workflow:INSERT' => 'ALLOWED',
				'prescription_workflow:UPDATE' => 'ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_medication', 'iop_medication_administration')
				),
				array(
					'name' => 'ADMIN_EVENT_REQUIRES_ORDER_EXISTENCE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_medication_administration')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_medication',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_medication',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_medication_administration',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_medication', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_medication',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_medication:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_medication')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_medication', 'iop_medication_administration')
				)
			)
		)
	),
	'VITALS' => array(
		'version' => 'V0',
		'domain' => 'VITALS',
		'intent_rules' => array(
			'create_method_regex' => 'save_(vitalSign|opd_vitals)',
			'delete_method_regex' => 'delete_vital'
		),
		'primary_key_map' => array(
			'iop_vital_parameters' => 'vital_id',
			'patient_details_iop' => 'IO_ID'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_vital_parameters',
					'required_ops' => array('INSERT'),
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_vital_parameters',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_vital_parameters', 'ops' => array('INSERT')),
				array('table' => 'patient_details_iop', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'iop_vital_parameters_extra', 'ops' => array('INSERT'), 'reason' => 'OPTIONAL_VITALS_EXTENSIONS'),
				array('table' => 'clinical_idempotency_records', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CTM_IDEMPOTENCY_INFRA'),
				array('table' => 'clinical_stream_locks', 'ops' => array('INSERT', 'UPDATE'), 'reason' => 'CTM_STREAM_LOCK_INFRA'),
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_vital_parameters:INSERT' => 'NOT_ALLOWED',
				'patient_details_iop:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_vital_parameters')
				),
				array(
					'name' => 'VITAL_ENTRY_MUST_UPDATE_SSOT_FLAG',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('patient_details_iop')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_vital_parameters',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_vital_parameters',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_vital_parameters', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_vital_parameters',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_vital_parameters:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_vital_parameters')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_vital_parameters')
				)
			)
		)
	),
	'INTAKE_OUTPUT' => array(
		'version' => 'V0',
		'domain' => 'INTAKE_OUTPUT',
		'intent_rules' => array(
			'create_method_regex' => 'save_(intake|output)',
			'delete_method_regex' => 'delete_(intake|output)'
		),
		'primary_key_map' => array(
			'iop_intake_record' => 'intake_id',
			'iop_output_record' => 'output_id'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'required_ops' => array('INSERT'),
					'when' => "method contains 'intake'",
					'severity_if_missing' => 'CRITICAL'
				),
				array(
					'table' => 'iop_output_record',
					'required_ops' => array('INSERT'),
					'when' => "method contains 'output'",
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_output_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_intake_record', 'ops' => array('INSERT')),
				array('table' => 'iop_output_record', 'ops' => array('INSERT'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_intake_record:INSERT' => 'NOT_ALLOWED',
				'iop_output_record:INSERT' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_intake_record', 'iop_output_record')
				),
				array(
					'name' => 'EXACTLY_ONE_TARGET_TABLE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'type' => 'cardinality',
					'expected' => 1,
					'tables' => array('iop_intake_record', 'iop_output_record')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_intake_record', 'iop_output_record')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'when' => "method contains 'intake'",
					'severity_if_missing' => 'CRITICAL'
				),
				array(
					'table' => 'iop_output_record',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'when' => "method contains 'output'",
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_output_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_intake_record', 'ops' => array('UPDATE')),
				array('table' => 'iop_output_record', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_intake_record',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				),
				array(
					'table' => 'iop_output_record',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_intake_record:UPDATE' => 'NOT_ALLOWED',
				'iop_output_record:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_intake_record', 'iop_output_record')
				),
				array(
					'name' => 'EXACTLY_ONE_TARGET_TABLE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'type' => 'cardinality',
					'expected' => 1,
					'tables' => array('iop_intake_record', 'iop_output_record')
				),
				array(
					'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_intake_record', 'iop_output_record')
				)
			)
		)
	),
	'INTAKE' => array(
		'intent_rules' => array(
			'create_method_regex' => 'save_intake',
			'delete_method_regex' => 'delete_intake'
		),
		'primary_key_map' => array(
			'iop_intake_record' => 'intake_id'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'required_ops' => array('INSERT'),
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_intake_record', 'ops' => array('INSERT'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_intake_record:INSERT' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_intake_record')
				),
				array(
					'name' => 'EXACTLY_ONE_TARGET_TABLE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'type' => 'cardinality',
					'expected' => 1,
					'tables' => array('iop_intake_record')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_intake_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_intake_record', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_intake_record',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_intake_record:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_intake_record')
				),
				array(
					'name' => 'EXACTLY_ONE_TARGET_TABLE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'type' => 'cardinality',
					'expected' => 1,
					'tables' => array('iop_intake_record')
				)
			)
		)
	),
	'OUTPUT' => array(
		'intent_rules' => array(
			'create_method_regex' => 'save_output',
			'delete_method_regex' => 'delete_output'
		),
		'primary_key_map' => array(
			'iop_output_record' => 'output_id'
		),
		'CREATE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_output_record',
					'required_ops' => array('INSERT'),
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_output_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_output_record', 'ops' => array('INSERT'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'duplicate_policy' => array(
				'iop_output_record:INSERT' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_CREATED_ACTIVE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_output_record')
				),
				array(
					'name' => 'EXACTLY_ONE_TARGET_TABLE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'type' => 'cardinality',
					'expected' => 1,
					'tables' => array('iop_output_record')
				)
			)
		),
		'DELETE' => array(
			'required_writes' => array(
				array(
					'table' => 'iop_output_record',
					'required_ops' => array('UPDATE'),
					'required_update' => 'InActive = 1',
					'severity_if_missing' => 'CRITICAL'
				)
			),
			'forbidden_writes' => array(
				array(
					'table' => 'iop_output_record',
					'forbidden_ops' => array('DELETE'),
					'violation' => 'NO_PHYSICAL_DELETE_ALLOWED',
					'severity' => 'CRITICAL'
				)
			),
			'allowed_writes' => array(
				array('table' => 'iop_output_record', 'ops' => array('UPDATE'))
			),
			'allowed_side_effects' => array(
				array('table' => 'ci_sessions', 'ops' => array('INSERT', 'UPDATE', 'DELETE'), 'reason' => 'FRAMEWORK_SESSION')
			),
			'update_constraints' => array(
				array(
					'table' => 'iop_output_record',
					'allowed_update' => 'InActive = 1',
					'severity' => 'CRITICAL'
				)
			),
			'duplicate_policy' => array(
				'iop_output_record:UPDATE' => 'NOT_ALLOWED'
			),
			'invariants' => array(
				array(
					'name' => 'RECORD_SOFT_DELETED',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'applies_to' => array('iop_output_record')
				),
				array(
					'name' => 'EXACTLY_ONE_TARGET_TABLE',
					'parity_required' => true,
					'severity' => 'CRITICAL',
					'type' => 'cardinality',
					'expected' => 1,
					'tables' => array('iop_output_record')
				)
			)
		)
	)
);

$config['shadow_governance_primary_key_map'] = array(
	'iop_bed_side_procedure' => 'bed_pro_id',
	'iop_room_transfer' => 'transfer_id',
	'room_beds' => 'room_bed_id',
	'iop_nurse_notes' => 'nurse_notes_id',
	'iop_medication' => 'iop_med_id',
	'iop_medication_administration' => 'admin_id',
	'iop_vital_parameters' => 'vital_id',
	'patient_details_iop' => 'IO_ID',
	'iop_intake_record' => 'intake_id',
	'iop_output_record' => 'output_id'
);

$config['shadow_governance_expectedset_completeness_requirements'] = array(
	'required_writes',
	'forbidden_writes',
	'allowed_writes',
	'allowed_side_effects',
	'duplicate_policy',
	'invariants'
);
