<?php

require_once(APPPATH . 'services/Nursing/Workspace/PatientHeaderComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/VitalsPanelComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/NotesPanelComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/TimelinePanelComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/MedicationPanelComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/IntakeOutputPanelComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/ShiftHandoverComposer.php');
require_once(APPPATH . 'services/Nursing/Workspace/ClinicalContextPanelComposer.php');

class WorkspacePayloadComposer
{
    public function compose(array $workspacePayload)
    {
        $headerComposer = new PatientHeaderComposer();
        $vitalsComposer = new VitalsPanelComposer();
        $notesComposer = new NotesPanelComposer();
        $timelineComposer = new TimelinePanelComposer();
        $medicationComposer = new MedicationPanelComposer();
        $intakeOutputComposer = new IntakeOutputPanelComposer();
        $shiftHandoverComposer = new ShiftHandoverComposer();
        $clinicalContextComposer = new ClinicalContextPanelComposer();

        return array(
            'patient_header' => $headerComposer->compose($workspacePayload),
            'vitals' => $vitalsComposer->compose($workspacePayload),
            'notes' => $notesComposer->compose($workspacePayload),
            'timeline' => $timelineComposer->compose($workspacePayload),
            'medication' => $medicationComposer->compose($workspacePayload),
            'intake_output' => $intakeOutputComposer->compose($workspacePayload),
            'shift_handover' => $shiftHandoverComposer->compose($workspacePayload),
            'clinical_context' => $clinicalContextComposer->compose($workspacePayload)
        );
    }
}
