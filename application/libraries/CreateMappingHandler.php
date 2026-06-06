<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/ActionHandlerInterface.php';

class CreateMappingHandler implements ActionHandlerInterface
{
    public function handle(array $insight, array $options = array()): array
    {
        $mode = isset($options['mode']) ? strtoupper(trim((string)$options['mode'])) : 'LOG_ONLY';

        $ctx = isset($insight['context']) && is_array($insight['context']) ? $insight['context'] : array();
        $module = isset($ctx['module']) ? strtoupper(trim((string)$ctx['module'])) : '';
        $id = isset($ctx['id']) ? (int)$ctx['id'] : 0;

        $plan = array(
            'module' => $module,
            'local_service_id' => $id,
        );

        $canExecute = !empty($options['allow_auto_execute']) && $mode === 'AUTO_EXECUTE';
        if ($canExecute) {
            // Safe-by-default: actual DB writes must be provided explicitly by integration layer.
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
            'message' => 'Mapping creation suggested',
            'plan' => $plan,
        );
    }
}
