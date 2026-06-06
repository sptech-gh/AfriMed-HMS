<?php

class ShiftReplayBuilder
{
    protected $stateBuilder;

    public function __construct(EffectiveStateBuilder $stateBuilder)
    {
        $this->stateBuilder = $stateBuilder;
    }

    public function build(array $events)
    {
        $state = $this->stateBuilder->build($events);
        return new ShiftReplayResult($state->timeline, $state->balance, $state->vitals, $state->summary);
    }
}
