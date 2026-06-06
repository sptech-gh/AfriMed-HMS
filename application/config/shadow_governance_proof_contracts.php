<?php defined('BASEPATH') OR exit('No direct script access allowed');

$config['shadow_governance_proof_contracts_version'] = 'V1';

$shadow_governance_db_state_contract = function ($name, $filter_field, $filter_value, $tables, $operations) {
return array(
'name' => $name,
'type' => 'DB_STATE',
'proof_inputs' => array('primary_key'),
'proof_query' => array(
'source' => '{table}',
'filters' => array(
array('field' => $filter_field, 'equals' => $filter_value)
),
'required_state' => 'ROW_EXISTS'
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => $tables,
'operations' => $operations,
'expected_write_count' => 1
);
};

$shadow_governance_cardinality_contract = function ($tables, $operations) {
return array(
'name' => 'EXACTLY_ONE_TARGET_TABLE',
'type' => 'AUDIT_TRACE',
'proof_inputs' => array(),
'proof_query' => array(
'source' => 'OBSERVED_SET',
'required_state' => 'EXACTLY_ONE_TARGET_TABLE',
'tables' => $tables,
'operations' => $operations,
'expected_count' => 1
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => $tables,
'operations' => $operations
);
};

$shadow_governance_no_delete_contract = function ($tables) {
return array(
'name' => 'NO_PHYSICAL_DELETE_ALLOWED',
'type' => 'AUDIT_TRACE',
'proof_inputs' => array(),
'proof_query' => array(
'source' => 'OBSERVED_SET',
'required_state' => 'NO_OPERATION',
'tables' => $tables,
'operations' => array('DELETE')
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => $tables,
'operations' => array('DELETE')
);
};

$config['shadow_governance_proof_contracts_v1'] = array(
'PROCEDURE' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_bed_side_procedure'), array('INSERT'))
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_bed_side_procedure'), array('UPDATE')),
$shadow_governance_no_delete_contract(array('iop_bed_side_procedure'))
)
),
'NURSE_NOTES' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_nurse_notes'), array('INSERT')),
array(
'name' => 'NOTES_ARE_APPEND_ONLY_LOGICAL',
'type' => 'AUDIT_TRACE',
'proof_inputs' => array(),
'proof_query' => array(
'source' => 'OBSERVED_SET',
'required_state' => 'NO_OPERATION',
'tables' => array('iop_nurse_notes'),
'operations' => array('UPDATE', 'DELETE')
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => array('iop_nurse_notes'),
'operations' => array('UPDATE', 'DELETE')
)
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_nurse_notes'), array('UPDATE')),
$shadow_governance_no_delete_contract(array('iop_nurse_notes'))
)
),
'ROOM_TRANSFER' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_room_transfer'), array('INSERT')),
array(
'name' => 'ROOM_STATE_MUST_RECONCILE_PATIENT_LINK',
'type' => 'DB_REFERENCE',
'proof_inputs' => array('opd_no', 'bed_name'),
'proof_query' => array(
'source' => 'room_beds',
'filters' => array(
array('field' => 'room_bed_id', 'equals_input' => 'bed_name'),
array('field' => 'patient_no', 'equals_input' => 'opd_no'),
array('field' => 'nStatus', 'equals' => 'Occupied'),
array('field' => 'InActive', 'equals' => 0)
),
'required_state' => 'ROW_EXISTS'
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => array('room_beds'),
'operations' => array('UPDATE')
),
array(
'name' => 'ROOM_STATE_MUST_RECONCILE_PATIENT_LINK',
'type' => 'DB_REFERENCE',
'proof_inputs' => array('opd_no', 'bed_name'),
'proof_query' => array(
'source' => 'patient_details_iop',
'filters' => array(
array('field' => 'IO_ID', 'equals_input' => 'opd_no'),
array('field' => 'room_id', 'equals_input' => 'bed_name'),
array('field' => 'patient_type', 'equals' => 'IPD'),
array('field' => 'InActive', 'equals' => 0)
),
'required_state' => 'ROW_EXISTS'
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => array('patient_details_iop'),
'operations' => array('UPDATE')
)
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_room_transfer'), array('UPDATE')),
$shadow_governance_no_delete_contract(array('iop_room_transfer', 'room_beds', 'patient_details_iop'))
)
),
'MEDICATION' => array(
'CREATE' => array(
array_merge(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_medication'), array('INSERT')),
array('when' => "method is 'save_medication'")
),
array_merge(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_medication_administration'), array('INSERT')),
array('when' => "method is 'save_medication_admin'")
),
array(
'name' => 'ADMIN_EVENT_REQUIRES_ORDER_EXISTENCE',
'type' => 'DB_REFERENCE',
'when' => "method is 'save_medication_admin'",
'proof_inputs' => array('iop_med_id'),
'proof_query' => array(
'source' => 'iop_medication',
'filters' => array(
array('field' => 'iop_med_id', 'equals_input' => 'iop_med_id'),
array('field' => 'InActive', 'equals' => 0)
),
'required_state' => 'ROW_EXISTS'
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => array('iop_medication_administration'),
'operations' => array('INSERT')
)
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_medication'), array('UPDATE')),
$shadow_governance_no_delete_contract(array('iop_medication', 'iop_medication_administration'))
)
),
'VITALS' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_vital_parameters'), array('INSERT')),
array(
'name' => 'VITAL_ENTRY_MUST_UPDATE_SSOT_FLAG',
'type' => 'AUDIT_TRACE',
'proof_inputs' => array(),
'proof_query' => array(
'source' => 'OBSERVED_SET',
'required_state' => 'EXACTLY_ONE_TARGET_TABLE',
'tables' => array('patient_details_iop'),
'operations' => array('UPDATE'),
'expected_count' => 1
),
'evaluation_mode' => 'STRICT',
'failure_classification' => array(
'unprovable' => 'CRITICAL',
'false_state' => 'CRITICAL',
'missing_data' => 'LOGIC'
),
'applies_to' => array('patient_details_iop'),
'operations' => array('UPDATE')
)
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_vital_parameters'), array('UPDATE')),
$shadow_governance_no_delete_contract(array('iop_vital_parameters'))
)
),
'INTAKE_OUTPUT' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_intake_record', 'iop_output_record'), array('INSERT')),
$shadow_governance_cardinality_contract(array('iop_intake_record', 'iop_output_record'), array('INSERT')),
$shadow_governance_no_delete_contract(array('iop_intake_record', 'iop_output_record'))
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_intake_record', 'iop_output_record'), array('UPDATE')),
$shadow_governance_cardinality_contract(array('iop_intake_record', 'iop_output_record'), array('UPDATE')),
$shadow_governance_no_delete_contract(array('iop_intake_record', 'iop_output_record'))
)
),
'INTAKE' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_intake_record'), array('INSERT')),
$shadow_governance_cardinality_contract(array('iop_intake_record'), array('INSERT'))
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_intake_record'), array('UPDATE')),
$shadow_governance_cardinality_contract(array('iop_intake_record'), array('UPDATE'))
)
),
'OUTPUT' => array(
'CREATE' => array(
$shadow_governance_db_state_contract('RECORD_CREATED_ACTIVE', 'InActive', 0, array('iop_output_record'), array('INSERT')),
$shadow_governance_cardinality_contract(array('iop_output_record'), array('INSERT'))
),
'DELETE' => array(
$shadow_governance_db_state_contract('RECORD_SOFT_DELETED', 'InActive', 1, array('iop_output_record'), array('UPDATE')),
$shadow_governance_cardinality_contract(array('iop_output_record'), array('UPDATE'))
)
)
);

$config['shadow_governance_proof_contracts_v0'] = $config['shadow_governance_proof_contracts_v1'];
