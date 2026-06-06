<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/AutoFixHandlerInterface.php';

class AutoFixImportTariffHandler implements AutoFixHandlerInterface
{
    public function handle(array $action, array $options = array()): array
    {
        $ctx = isset($action['context']) && is_array($action['context']) ? $action['context'] : array();
        $code = isset($ctx['code']) ? strtoupper(trim((string)$ctx['code'])) : '';

        // This is CONTROLLED because it can affect prices.
        // This handler intentionally does not alter prices. It only produces an execution plan.

        return array(
            'success' => true,
            'message' => 'Tariff import/update requires controlled review',
            'before_state' => array('service_code' => $code),
            'after_state' => array('service_code' => $code),
            'planned' => true,
            'requires' => array('reference_import'),
        );
    }
}
