<?php

class VitalsPanelComposer
{
    public function compose(array $workspacePayload)
    {
        $summary = isset($workspacePayload['summary']) && is_array($workspacePayload['summary']) ? $workspacePayload['summary'] : array();
        $latest = isset($summary['latest_vitals']) && is_array($summary['latest_vitals']) ? $summary['latest_vitals'] : array();
        $history = isset($workspacePayload['vitals_history']) && is_array($workspacePayload['vitals_history']) ? $workspacePayload['vitals_history'] : array();
        return array(
            'title' => 'Vitals',
            'items' => array(
                'latest' => $latest,
                'history' => $history
            ),
            'meta' => array(
                'history_count' => count($history),
                'status' => isset($latest['status']) ? $latest['status'] : 'unknown'
            ),
            'empty_state' => 'No vitals history found.'
        );
    }
}
