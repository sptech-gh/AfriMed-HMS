<?php

class ReplayOrdering
{
    public static function sort(array $events): array
    {
        usort($events, function ($a, $b) {
            $aVersion = isset($a['stream_version']) ? (int)$a['stream_version'] : 0;
            $bVersion = isset($b['stream_version']) ? (int)$b['stream_version'] : 0;

            if ($aVersion === $bVersion) {
                $aId = isset($a['event_id']) ? (int)$a['event_id'] : 0;
                $bId = isset($b['event_id']) ? (int)$b['event_id'] : 0;

                if ($aId === $bId) {
                    return 0;
                }

                return ($aId < $bId) ? -1 : 1;
            }

            return ($aVersion < $bVersion) ? -1 : 1;
        });

        return $events;
    }
}
