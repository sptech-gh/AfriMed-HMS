<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/ActionRegistry.php';

class NHISActionRouter
{
    const MODE_AUTO_EXECUTE = 'AUTO_EXECUTE';
    const MODE_QUEUE_FOR_REVIEW = 'QUEUE_FOR_REVIEW';
    const MODE_ESCALATE_TO_ADMIN = 'ESCALATE_TO_ADMIN';
    const MODE_LOG_ONLY = 'LOG_ONLY';

    private $registry;

    public function __construct(ActionRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Route and (optionally) execute insights.
     *
     * IMPORTANT: Safe-by-default. If you do not pass allow_auto_execute=true, it will not auto-execute.
     */
    public function route(array $insights, array $options = array()): array
    {
        $results = array();
        $seen = array();

        foreach ($insights as $insight) {
            if (!is_array($insight)) {
                continue;
            }

            $action_key = isset($insight['action_key']) ? strtolower(trim((string)$insight['action_key'])) : '';
            $severity = isset($insight['severity']) ? strtoupper(trim((string)$insight['severity'])) : '';

            $idempotency_key = $this->_idempotency_key($insight);
            if ($idempotency_key !== '' && isset($seen[$idempotency_key])) {
                continue;
            }
            if ($idempotency_key !== '') {
                $seen[$idempotency_key] = 1;
            }

            if (!$this->registry->is_allowed($action_key)) {
                $results[] = $this->_build_router_result(
                    $insight,
                    null,
                    self::MODE_LOG_ONLY,
                    false,
                    'No handler registered for action_key',
                    array('idempotency_key' => $idempotency_key)
                );
                continue;
            }

            $handlerClass = $this->registry->resolve_handler_class($action_key);
            $mode = $this->_select_mode($severity, $options);

            $handler = $this->_instantiate_handler($handlerClass);
            if ($handler === null) {
                $results[] = $this->_build_router_result(
                    $insight,
                    $handlerClass,
                    self::MODE_LOG_ONLY,
                    false,
                    'Handler could not be loaded',
                    array('idempotency_key' => $idempotency_key)
                );
                continue;
            }

            $handlerOptions = $options;
            $handlerOptions['mode'] = $mode;

            $out = $handler->handle($insight, $handlerOptions);
            if (!is_array($out)) {
                $out = array('success' => false, 'message' => 'Handler returned invalid result');
            }

            $results[] = $this->_build_router_result(
                $insight,
                $handlerClass,
                isset($out['mode']) ? (string)$out['mode'] : $mode,
                !empty($out['success']),
                isset($out['message']) ? (string)$out['message'] : '',
                array_merge(
                    array('idempotency_key' => $idempotency_key),
                    $out
                )
            );
        }

        log_message('debug', '[NHIS_ACTION_ROUTER] routed=' . count($results));

        return $results;
    }

    private function _instantiate_handler($handlerClass)
    {
        $handlerClass = trim((string)$handlerClass);
        if ($handlerClass === '') {
            return null;
        }

        if (!class_exists($handlerClass)) {
            $file = APPPATH . 'libraries/' . $handlerClass . '.php';
            if (is_readable($file)) {
                require_once $file;
            }
        }

        if (!class_exists($handlerClass)) {
            return null;
        }

        $obj = new $handlerClass();
        if (!($obj instanceof ActionHandlerInterface)) {
            return null;
        }

        return $obj;
    }

    private function _select_mode($severity, array $options)
    {
        if (isset($options['mode']) && is_string($options['mode']) && trim($options['mode']) !== '') {
            return strtoupper(trim((string)$options['mode']));
        }

        $severity = strtoupper(trim((string)$severity));
        if ($severity === 'CRITICAL') {
            return self::MODE_ESCALATE_TO_ADMIN;
        }
        if ($severity === 'HIGH') {
            return self::MODE_QUEUE_FOR_REVIEW;
        }
        if ($severity === 'LOW') {
            return self::MODE_LOG_ONLY;
        }

        return self::MODE_LOG_ONLY;
    }

    private function _idempotency_key(array $insight)
    {
        $stable = array(
            'error_code' => isset($insight['error_code']) ? (string)$insight['error_code'] : '',
            'action_key' => isset($insight['action_key']) ? (string)$insight['action_key'] : '',
            'context' => isset($insight['context']) && is_array($insight['context']) ? $insight['context'] : array(),
        );

        $json = json_encode($stable);
        if (!is_string($json) || $json === '') {
            return '';
        }

        return sha1($json);
    }

    private function _build_router_result(array $insight, $handlerClass, $mode, $success, $message, array $data)
    {
        return array(
            'action_key' => isset($insight['action_key']) ? (string)$insight['action_key'] : '',
            'error_code' => isset($insight['error_code']) ? (string)$insight['error_code'] : 'UNKNOWN',
            'severity' => isset($insight['severity']) ? (string)$insight['severity'] : 'MEDIUM',
            'confidence' => isset($insight['confidence']) ? (float)$insight['confidence'] : null,
            'context' => isset($insight['context']) && is_array($insight['context']) ? $insight['context'] : array(),
            'handler' => $handlerClass !== null ? (string)$handlerClass : null,
            'mode' => (string)$mode,
            'success' => (bool)$success,
            'message' => (string)$message,
            'data' => $data,
        );
    }
}
