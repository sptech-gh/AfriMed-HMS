<?php

class CorrectionResolver
{
    public function resolve(array $events): array
    {
        $events = ReplayOrdering::sort($events);
        $supersededIds = array();

        foreach ($events as $event) {
            if (!empty($event['parent_event_id'])) {
                $supersededIds[(string)$event['parent_event_id']] = true;
            }
        }

        $effective = array();
        foreach ($events as $event) {
            $eventId = isset($event['event_id']) ? (string)$event['event_id'] : '';
            $status = isset($event['status']) ? strtoupper((string)$event['status']) : '';

            if ($status === 'VOIDED') {
                continue;
            }

            if ($status === 'SUPERSEDED') {
                continue;
            }

            if ($eventId !== '' && isset($supersededIds[$eventId])) {
                continue;
            }

            $effective[] = $event;
        }

        return ReplayOrdering::sort($effective);
    }

    public function buildCorrectionChains(array $events): array
    {
        $chains = array();
        foreach ($events as $event) {
            if (!empty($event['parent_event_id'])) {
                $parent = (string)$event['parent_event_id'];
                if (!isset($chains[$parent])) {
                    $chains[$parent] = array();
                }
                $chains[$parent][] = $event;
            }
        }

        foreach ($chains as $parent => $children) {
            $chains[$parent] = ReplayOrdering::sort($children);
        }

        return $chains;
    }
}
