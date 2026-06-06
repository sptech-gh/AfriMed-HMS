<?php

class ClinicalWriteResult
{
    public $eventId;
    public $streamVersion;
    public $domainId;
    public $domain;
    public $eventType;

    public function __construct($eventId, $streamVersion, $domainId, $domain, $eventType)
    {
        $this->eventId = $eventId;
        $this->streamVersion = $streamVersion;
        $this->domainId = $domainId;
        $this->domain = $domain;
        $this->eventType = $eventType;
    }

    public function toArray(): array
    {
        return array(
            'event_id' => $this->eventId,
            'stream_version' => $this->streamVersion,
            'domain_id' => $this->domainId,
            'domain' => $this->domain,
            'event_type' => $this->eventType,
        );
    }
}
