<?php

class IntakeOutputPanelComposer
{
    public function compose(array $workspacePayload)
    {
        $io = isset($workspacePayload['io_summary']) && is_array($workspacePayload['io_summary']) ? $workspacePayload['io_summary'] : array();
        $recent = isset($io['recent_entries']) && is_array($io['recent_entries']) ? $io['recent_entries'] : array();
        return array(
            'title' => 'Intake / Output',
            'items' => array(
                'summary' => $io,
                'recent_entries' => $recent
            ),
            'meta' => array(
                'intake_total' => isset($io['intake_total']) ? $io['intake_total'] : 0,
                'output_total' => isset($io['output_total']) ? $io['output_total'] : 0,
                'balance' => isset($io['balance']) ? $io['balance'] : 0,
                'recent_count' => count($recent)
            ),
            'empty_state' => 'No intake/output records found.'
        );
    }
}
