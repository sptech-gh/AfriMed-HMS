<?php

class MedicationPanelComposer
{
    public function compose(array $workspacePayload)
    {
        $due = isset($workspacePayload['pending_medications']) && is_array($workspacePayload['pending_medications']) ? $workspacePayload['pending_medications'] : array();
        $recent = isset($workspacePayload['recent_medication_administrations']) && is_array($workspacePayload['recent_medication_administrations']) ? $workspacePayload['recent_medication_administrations'] : array();
        return array(
            'title' => 'Medication',
            'items' => array(
                'due' => $due,
                'recent_administrations' => $recent
            ),
            'meta' => array(
                'due_count' => count($due),
                'recent_count' => count($recent)
            ),
            'empty_state' => 'No due or recently administered medications found.'
        );
    }
}
