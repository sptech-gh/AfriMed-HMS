<?php

class AuditReplayBuilder
{
    protected $correctionResolver;

    public function __construct(CorrectionResolver $correctionResolver)
    {
        $this->correctionResolver = $correctionResolver;
    }

    public function build(array $events): array
    {
        $ordered = ReplayOrdering::sort($events);

        return array(
            'raw_timeline' => $ordered,
            'correction_chains' => $this->correctionResolver->buildCorrectionChains($ordered),
            'nurse_activity' => $this->buildNurseActivity($ordered),
        );
    }

    protected function buildNurseActivity(array $events): array
    {
        $activity = array();
        foreach ($events as $event) {
            $user = isset($event['entered_by']) ? (string)$event['entered_by'] : 'UNKNOWN';
            if (!isset($activity[$user])) {
                $activity[$user] = 0;
            }
            $activity[$user]++;
        }

        return $activity;
    }
}
