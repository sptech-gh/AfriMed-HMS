<?php

class CiDomainEventHydrator implements DomainEventHydratorInterface
{
    protected $db;

    protected $domainTables = array(
        'INTAKE' => 'nursing_intake',
        'OUTPUT' => 'nursing_output',
        'VITALS' => 'nursing_vitals',
        'NOTES' => 'nursing_notes',
        'HANDOVER' => 'nursing_handover',
    );

    public function __construct($db = null)
    {
        if ($db !== null) {
            $this->db = $db;
            return;
        }

        $CI = &get_instance();
        $this->db = $CI->db;
    }

    public function hydrate(array $events): array
    {
        $hydrated = array();
        foreach ($events as $event) {
            $hydrated[] = $this->hydrateOne($event);
        }

        return $hydrated;
    }

    public function hydrateOne(array $event): array
    {
        $domain = isset($event['domain']) ? strtoupper((string)$event['domain']) : '';
        $eventId = isset($event['event_id']) ? (int)$event['event_id'] : 0;
        $payload = $this->decodePayload(isset($event['payload_json']) ? $event['payload_json'] : null);

        if ($eventId > 0 && isset($this->domainTables[$domain])) {
            $row = $this->db
                ->where('event_id', $eventId)
                ->get($this->domainTables[$domain])
                ->row_array();

            if (is_array($row)) {
                $payload = array_merge($payload, $row);
            } else {
                if (!isset($event['replay_anomalies']) || !is_array($event['replay_anomalies'])) {
                    $event['replay_anomalies'] = array();
                }
                $event['replay_anomalies'][] = 'DOMAIN_ROW_MISSING';
            }
        }

        $event['payload'] = $payload;

        return $event;
    }

    protected function decodePayload($payload): array
    {
        if (!is_string($payload) || $payload === '') {
            return array();
        }

        $decoded = json_decode($payload, true);
        return is_array($decoded) ? $decoded : array();
    }
}
