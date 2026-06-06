<?php

interface SnapshotRepositoryInterface
{
    public function findSnapshot($iopId, $type, $key);

    public function saveSnapshot($iopId, $type, $data, $streamVersion): void;

    public function isFresh($snapshot, $currentStreamVersion): bool;
}
