<?php

class PatientReplayResult
{
    public $timeline;
    public $balance;
    public $vitals;
    public $summary;

    public function __construct(array $timeline, $balance, array $vitals, array $summary = array())
    {
        $this->timeline = $timeline;
        $this->balance = $balance;
        $this->vitals = $vitals;
        $this->summary = $summary;
    }

    public function toArray(): array
    {
        return array(
            'timeline' => $this->timeline,
            'balance' => $this->balance,
            'vitals' => $this->vitals,
            'summary' => $this->summary,
        );
    }
}
