<?php

interface ClinicalEventWriterInterface
{
    public function nextStreamVersion($iopId);

    public function appendEvent(array $event);

    public function insertDomainRow($table, array $row);
}
