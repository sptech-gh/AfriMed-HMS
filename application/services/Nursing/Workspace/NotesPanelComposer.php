<?php

class NotesPanelComposer
{
    public function compose(array $workspacePayload)
    {
        $notes = isset($workspacePayload['recent_notes']) && is_array($workspacePayload['recent_notes']) ? $workspacePayload['recent_notes'] : array();
        return array(
            'title' => 'Recent Notes',
            'items' => $notes,
            'meta' => array(
                'count' => count($notes)
            ),
            'empty_state' => 'No recent nurse notes.'
        );
    }
}
