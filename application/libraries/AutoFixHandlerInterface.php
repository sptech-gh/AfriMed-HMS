<?php
defined('BASEPATH') OR exit('No direct script access allowed');

interface AutoFixHandlerInterface
{
    /**
     * Execute an auto-fix for a routed action.
     *
     * Contract:
     * - Must be safe and deterministic.
     * - Must return before/after snapshots for audit + rollback.
     * - Must NOT perform writes unless options['allow_writes'] is true.
     */
    public function handle(array $action, array $options = array()): array;
}
