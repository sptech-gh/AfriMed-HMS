<?php

interface ClinicalTransactionManagerInterface
{
    public function run($iopId, $idempotencyKey, callable $operation);

    public function execute($iopId, $idempotencyKey, callable $operation);
}
