<?php

class PatientHeaderComposer
{
    public function compose(array $workspacePayload)
    {
        $patient = isset($workspacePayload['patient']) && is_array($workspacePayload['patient']) ? $workspacePayload['patient'] : array();
        return array(
            'title' => isset($patient['name']) && trim((string)$patient['name']) !== '' ? (string)$patient['name'] : 'Patient',
            'items' => array(
                'patient_no' => isset($patient['patient_no']) ? (string)$patient['patient_no'] : '',
                'encounter_id' => isset($patient['encounter_id']) ? (string)$patient['encounter_id'] : '',
                'ward_name' => isset($patient['ward_name']) ? (string)$patient['ward_name'] : '',
                'bed_name' => isset($patient['bed_name']) ? (string)$patient['bed_name'] : ''
            ),
            'meta' => array(
                'patient' => $patient
            ),
            'empty_state' => 'Patient context unavailable.'
        );
    }
}
