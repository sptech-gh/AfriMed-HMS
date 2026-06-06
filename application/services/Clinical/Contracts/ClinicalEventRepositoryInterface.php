<?php

interface ClinicalEventRepositoryInterface
{
    public function getStreamByIopId($iopId): array;

    public function getStreamAtTime($iopId, $cutoff): array;

    public function getStreamForShift($iopId, $shiftId, $shiftDate): array;
}
