<?php

class TimelinePanelComposer
{
    public function compose(array $workspacePayload)
    {
        $timeline = isset($workspacePayload['timeline']) && is_array($workspacePayload['timeline']) ? $workspacePayload['timeline'] : array();
        return array(
            'title' => 'Patient Timeline',
            'items' => $timeline,
            'meta' => array(
                'count' => count($timeline)
            ),
            'empty_state' => 'No timeline events found.'
        );
    }
}
