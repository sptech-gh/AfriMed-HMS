<?php

class ClinicalReplayEngine
{
    protected $events;
    protected $hydrator;
    protected $correctionResolver;
    protected $stateBuilder;

    public function __construct(
        ClinicalEventRepositoryInterface $events,
        DomainEventHydratorInterface $hydrator,
        CorrectionResolver $correctionResolver,
        EffectiveStateBuilder $stateBuilder
    ) {
        $this->events = $events;
        $this->hydrator = $hydrator;
        $this->correctionResolver = $correctionResolver;
        $this->stateBuilder = $stateBuilder;
    }

    public function replayPatient($iopId)
    {
        $stream = $this->events->getStreamByIopId($iopId);
        $hydrated = $this->hydrator->hydrate($stream);
        $effective = $this->correctionResolver->resolve($hydrated);
        $state = $this->stateBuilder->build($effective);

        return new PatientReplayResult($state->timeline, $state->balance, $state->vitals, $state->summary);
    }

    public function replayAtTime($iopId, $cutoff)
    {
        $stream = $this->events->getStreamAtTime($iopId, $cutoff);
        $hydrated = $this->hydrator->hydrate($stream);
        $effective = $this->correctionResolver->resolve($hydrated);
        $state = $this->stateBuilder->build($effective);

        return new TimeSliceReplayResult($state->timeline, $state->balance, $state->vitals, $state->summary);
    }

    public function replayShift($iopId, $shiftId, $shiftDate)
    {
        $stream = $this->events->getStreamForShift($iopId, $shiftId, $shiftDate);
        $hydrated = $this->hydrator->hydrate($stream);
        $effective = $this->correctionResolver->resolve($hydrated);
        $state = $this->stateBuilder->build($effective);

        return new ShiftReplayResult($state->timeline, $state->balance, $state->vitals, $state->summary);
    }
}
