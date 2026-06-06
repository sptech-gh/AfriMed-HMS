<?php
defined('BASEPATH') OR exit('No direct script access allowed');

interface ActionHandlerInterface
{
    /**
     * Handle a single NHIS insight.
     *
     * @param array $insight Structured insight from Nhis_error_intelligence_model
     * @param array $options Router-provided options (mode, safety flags, etc.)
     * @return array Result payload (pure data)
     */
    public function handle(array $insight, array $options = array()): array;
}
