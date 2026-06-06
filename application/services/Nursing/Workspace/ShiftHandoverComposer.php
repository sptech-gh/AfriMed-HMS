<?php

class ShiftHandoverComposer
{
    public function compose(array $workspacePayload)
    {
        $summary = isset($workspacePayload['summary']) && is_array($workspacePayload['summary']) ? $workspacePayload['summary'] : array();
        $latestVitals = isset($summary['latest_vitals']) && is_array($summary['latest_vitals']) ? $summary['latest_vitals'] : array();
        $alerts = isset($workspacePayload['active_alerts']) && is_array($workspacePayload['active_alerts']) ? $workspacePayload['active_alerts'] : array();
        $pendingMedications = isset($workspacePayload['pending_medications']) && is_array($workspacePayload['pending_medications']) ? $workspacePayload['pending_medications'] : array();
        $recentNotes = isset($workspacePayload['recent_notes']) && is_array($workspacePayload['recent_notes']) ? $workspacePayload['recent_notes'] : array();
        $ioSummary = isset($workspacePayload['io_summary']) && is_array($workspacePayload['io_summary']) ? $workspacePayload['io_summary'] : array();

        $attention = array();
        foreach ($alerts as $alert) {
            $attention[] = array(
                'severity' => isset($alert['severity']) ? $alert['severity'] : 'info',
                'label' => isset($alert['label']) ? $alert['label'] : 'Clinical alert'
            );
        }
        if (isset($latestVitals['status']) && in_array($latestVitals['status'], array('missing', 'overdue', 'critical'), true)) {
            $attention[] = array('severity' => $latestVitals['status'] === 'critical' ? 'critical' : 'warning', 'label' => 'Vitals status: ' . $latestVitals['status']);
        }
        if (count($pendingMedications) > 0) {
            $attention[] = array('severity' => 'warning', 'label' => count($pendingMedications) . ' pending medication(s)');
        }
        if (isset($ioSummary['balance']) && (int)$ioSummary['balance'] < 0) {
            $attention[] = array('severity' => 'warning', 'label' => 'Negative intake/output balance');
        }

        return array(
            'title' => 'Shift Handover Summary',
            'items' => array(
                'latest_vitals' => $latestVitals,
                'pending_medications' => array_slice($pendingMedications, 0, 5),
                'recent_notes' => array_slice($recentNotes, 0, 3),
                'attention_indicators' => $attention,
                'io_summary' => $ioSummary
            ),
            'meta' => array(
                'attention_count' => count($attention),
                'pending_medication_count' => count($pendingMedications),
                'recent_note_count' => count($recentNotes)
            ),
            'empty_state' => 'No handover concerns found from current workspace data.'
        );
    }
}
