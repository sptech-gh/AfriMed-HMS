<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/ActionHandlerInterface.php';

class FixTariffHandler implements ActionHandlerInterface
{
    public function handle(array $insight, array $options = array()): array
    {
        $mode = isset($options['mode']) ? strtoupper(trim((string)$options['mode'])) : 'LOG_ONLY';

        $ctx = isset($insight['context']) && is_array($insight['context']) ? $insight['context'] : array();
        $service_code = '';

        if (isset($ctx['code'])) {
            $service_code = strtoupper(trim((string)$ctx['code']));
        }

        $plan = array(
            'service_code' => $service_code,
        );

        $canExecute = !empty($options['allow_auto_execute']) && $mode === 'AUTO_EXECUTE';
        if ($canExecute) {
            // Safe-by-default: actual tariff imports/updates must be provided explicitly by integration layer.
            return array(
                'mode' => $mode,
                'success' => false,
                'executed' => false,
                'message' => 'AUTO_EXECUTE not enabled (no executor bound)',
                'plan' => $plan,
            );
        }

        return array(
            'mode' => $mode,
            'success' => true,
            'executed' => false,
            'message' => 'Tariff import/update suggested',
            'plan' => $plan,
        );
    }
}
