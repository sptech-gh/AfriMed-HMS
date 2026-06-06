<?php

class EffectiveStateBuilder
{
    public function build(array $events)
    {
        $events = ReplayOrdering::sort($events);

        return new EffectiveClinicalState(
            $events,
            $this->calculateBalance($events),
            $this->extractVitals($events),
            $this->buildSummary($events)
        );
    }

    protected function calculateBalance(array $events)
    {
        $in = 0.0;
        $out = 0.0;

        foreach ($events as $event) {
            $domain = isset($event['domain']) ? strtoupper((string)$event['domain']) : '';
            $payload = isset($event['payload']) && is_array($event['payload']) ? $event['payload'] : array();
            $volume = isset($payload['volume_ml']) ? (float)$payload['volume_ml'] : 0.0;

            if ($domain === 'INTAKE') {
                $in += $volume;
            }

            if ($domain === 'OUTPUT') {
                $category = isset($payload['output_category']) ? strtoupper((string)$payload['output_category']) : '';
                if ($category !== 'STOOL' && $category !== 'NO_OUTPUT_OBSERVATION') {
                    $out += $volume;
                }
            }
        }

        return $in - $out;
    }

    protected function extractVitals(array $events): array
    {
        return array_values(array_filter($events, function ($event) {
            $domain = isset($event['domain']) ? strtoupper((string)$event['domain']) : '';
            return $domain === 'VITALS';
        }));
    }

    protected function buildSummary(array $events): array
    {
        $summary = array();
        foreach ($events as $event) {
            $domain = isset($event['domain']) ? strtoupper((string)$event['domain']) : 'UNKNOWN';
            if (!isset($summary[$domain])) {
                $summary[$domain] = 0;
            }
            $summary[$domain]++;
        }

        return $summary;
    }
}
