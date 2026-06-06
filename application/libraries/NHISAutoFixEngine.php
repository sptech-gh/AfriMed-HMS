<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/AutoFixSafetyMap.php';
require_once APPPATH . 'libraries/AutoFixRegistry.php';

class NHISAutoFixEngine
{
    const RUN_DRY_RUN = 'DRY_RUN';
    const RUN_LIVE = 'LIVE';
    const RUN_SIMULATION = 'SIMULATION';

    private $registry;

    public function __construct(AutoFixRegistry $registry)
    {
        $this->registry = $registry;
    }

    /**
     * Process a single routed action (from NHISActionRouter).
     */
    public function process(array $action, array $options = array()): array
    {
        $action_key = isset($action['action_key']) ? strtolower(trim((string)$action['action_key'])) : '';
        $severity = isset($action['severity']) ? strtoupper(trim((string)$action['severity'])) : 'MEDIUM';
        $error_code = isset($action['error_code']) ? strtoupper(trim((string)$action['error_code'])) : 'UNKNOWN';
        $confidence = isset($action['confidence']) ? (float)$action['confidence'] : null;

        $zone = AutoFixSafetyMap::zone($action_key);

        $decision = $this->_decision($zone, false, true);

        if (!$this->_validate_action_schema($action)) {
            return $this->_result($action, $decision, 'blocked', false, 'Invalid action schema', array());
        }

        if (!$this->registry->is_allowed($action_key)) {
            return $this->_result($action, $decision, 'blocked', false, 'Unknown or unregistered action_key', array());
        }

        // Confidence gate: low confidence never runs as SAFE.
        $min = isset($options['min_confidence']) ? (float)$options['min_confidence'] : 0.7;
        if ($confidence !== null && $confidence < $min && $zone === AutoFixSafetyMap::ZONE_SAFE) {
            $zone = AutoFixSafetyMap::ZONE_CONTROLLED;
            $decision = $this->_decision($zone, false, true);
        }

        $run_mode = isset($options['run_mode']) ? strtoupper(trim((string)$options['run_mode'])) : self::RUN_DRY_RUN;
        if (!in_array($run_mode, array(self::RUN_DRY_RUN, self::RUN_LIVE, self::RUN_SIMULATION), true)) {
            $run_mode = self::RUN_DRY_RUN;
        }

        $action_hash = $this->_compute_action_hash($action);
        $action['action_hash'] = $action_hash;

        // Persisted idempotency: if already executed, skip.
        $this->_load_queue_model();
        if (isset($this->queue) && is_object($this->queue) && $this->queue->is_executed($action_hash)) {
            return $this->_result(
                $action,
                $this->_decision($zone, false, false),
                'skipped',
                true,
                'Already executed (idempotent skip)',
                array('action_hash' => $action_hash)
            );
        }

        if ($zone === AutoFixSafetyMap::ZONE_BLOCKED) {
            return $this->escalate($action, $this->_decision($zone, false, true), 'Blocked action (manual only)');
        }

        if ($zone === AutoFixSafetyMap::ZONE_CONTROLLED) {
            // Controlled actions ALWAYS queue unless explicitly approved/executable.
            return $this->queue($action, $this->_decision($zone, false, true), 'Controlled action queued for review');
        }

        // SAFE zone
        $canExecute = ($run_mode === self::RUN_LIVE) && !empty($options['allow_writes']);
        $decision = $this->_decision($zone, $canExecute, false);

        if (!$canExecute) {
            return $this->_result(
                $action,
                $decision,
                'planned',
                true,
                'Auto-fix planned (dry run / writes disabled)',
                array(
                    'run_mode' => $run_mode,
                    'severity' => $severity,
                    'error_code' => $error_code,
                    'action_hash' => $action_hash,
                )
            );
        }

        return $this->execute($action, $decision, $options);
    }

    public function execute(array $action, array $decision, array $options = array()): array
    {
        $action_key = isset($action['action_key']) ? strtolower(trim((string)$action['action_key'])) : '';
        $handlerClass = $this->registry->resolve_handler_class($action_key);

        $handler = $this->_instantiate_handler($handlerClass);
        if ($handler === null) {
            return $this->_result($action, $decision, 'failed', false, 'Handler could not be loaded', array());
        }

        $before = $this->_snapshot_before($action);

        $out = $handler->handle($action, $options);
        if (!is_array($out)) {
            $out = array('success' => false, 'message' => 'Handler returned invalid result');
        }

        $after = isset($out['after_state']) && is_array($out['after_state']) ? $out['after_state'] : $this->_snapshot_after($action);

        $verify = $this->_verify_before_after_state($action, $before, $after, $out);
        if (empty($verify['ok'])) {
            $audit = $this->_build_audit_payload($action, $decision['mode'], $before, $after, $out);
            return $this->_result(
                $action,
                $decision,
                'failed',
                false,
                isset($verify['message']) ? (string)$verify['message'] : 'Verification failed',
                array(
                    'handler' => $handlerClass,
                    'before_state' => $before,
                    'after_state' => $after,
                    'audit' => $audit,
                    'handler_result' => $out,
                    'verification' => $verify,
                )
            );
        }

        $audit = $this->_build_audit_payload($action, $decision['mode'], $before, $after, $out);

        // IMPORTANT: no DB writes here by default. Audit payload is returned for integration layer.
        log_message('debug', '[NHIS_AUTO_FIX] action_key=' . $action_key . ' zone=' . $decision['mode'] . ' success=' . (!empty($out['success']) ? '1' : '0'));

        // Mark executed for idempotency.
        $this->_load_queue_model();
        if (isset($this->queue) && is_object($this->queue)) {
            $row = $this->queue->get_by_hash(isset($action['action_hash']) ? (string)$action['action_hash'] : '');
            if ($row && isset($row->id)) {
                $this->queue->mark_executed((int)$row->id, $before, $after);
            }
        }

        return $this->_result(
            $action,
            $decision,
            !empty($out['success']) ? 'fixed' : 'failed',
            !empty($out['success']),
            isset($out['message']) ? (string)$out['message'] : '',
            array(
                'handler' => $handlerClass,
                'before_state' => $before,
                'after_state' => $after,
                'audit' => $audit,
                'handler_result' => $out,
                'verification' => $verify,
            )
        );
    }

    public function queue(array $action, array $decision, $reason = 'Queued'): array
    {
        $action_hash = $this->_compute_action_hash($action);
        $action['action_hash'] = $action_hash;

        $this->_load_queue_model();
        if (isset($this->queue) && is_object($this->queue)) {
            $q = $this->queue->enqueue($action, $action_hash, $decision['mode']);
            return $this->_result($action, $decision, 'queued', !empty($q['success']), (string)$reason, array('queue' => $q));
        }

        return $this->_result($action, $decision, 'queued', true, (string)$reason, array('action_hash' => $action_hash));
    }

    public function escalate(array $action, array $decision, $reason = 'Escalated'): array
    {
        return $this->_result($action, $decision, 'escalated', true, (string)$reason, array());
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
        if (!($obj instanceof AutoFixHandlerInterface)) {
            return null;
        }

        return $obj;
    }

    private function _snapshot_before(array $action)
    {
        // Integration will provide DB snapshots. For now we store stable inputs.
        return array(
            'action_key' => isset($action['action_key']) ? (string)$action['action_key'] : '',
            'error_code' => isset($action['error_code']) ? (string)$action['error_code'] : '',
            'severity' => isset($action['severity']) ? (string)$action['severity'] : '',
            'context' => isset($action['context']) && is_array($action['context']) ? $action['context'] : array(),
        );
    }

    private function _snapshot_after(array $action)
    {
        return $this->_snapshot_before($action);
    }

    private function _build_audit_payload(array $action, $mode, array $before, array $after, array $handlerOut)
    {
        return array(
            'action_key' => isset($action['action_key']) ? (string)$action['action_key'] : '',
            'error_code' => isset($action['error_code']) ? (string)$action['error_code'] : 'UNKNOWN',
            'severity' => isset($action['severity']) ? (string)$action['severity'] : 'MEDIUM',
            'zone' => (string)$mode,
            'action_hash' => isset($action['action_hash']) ? (string)$action['action_hash'] : null,
            'before_state' => $before,
            'after_state' => $after,
            'performed_by' => 'auto_fix_engine',
            'timestamp' => date('Y-m-d H:i:s'),
            'handler' => isset($handlerOut['handler']) ? (string)$handlerOut['handler'] : null,
        );
    }

    private function _result(array $action, array $decision, $status, $success, $message, array $data)
    {
        return array(
            'action_key' => isset($action['action_key']) ? (string)$action['action_key'] : '',
            'error_code' => isset($action['error_code']) ? (string)$action['error_code'] : 'UNKNOWN',
            'severity' => isset($action['severity']) ? (string)$action['severity'] : 'MEDIUM',
            'confidence' => isset($action['confidence']) ? (float)$action['confidence'] : null,
            'action_hash' => isset($action['action_hash']) ? (string)$action['action_hash'] : $this->_compute_action_hash($action),
            'decision' => $decision,
            'zone' => isset($decision['mode']) ? (string)$decision['mode'] : 'BLOCKED',
            'status' => (string)$status,
            'success' => (bool)$success,
            'message' => (string)$message,
            'data' => $data,
        );
    }

    private function _decision($mode, $can_execute, $requires_approval)
    {
        return array(
            'can_execute' => (bool)$can_execute,
            'mode' => (string)$mode,
            'requires_approval' => (bool)$requires_approval,
        );
    }

    private function _validate_action_schema(array $action)
    {
        if (empty($action['action_key'])) {
            return false;
        }
        if (!isset($action['context']) || !is_array($action['context'])) {
            return false;
        }
        return true;
    }

    private function _compute_action_hash(array $action)
    {
        $action_key = isset($action['action_key']) ? strtolower(trim((string)$action['action_key'])) : '';
        $ctx = isset($action['context']) && is_array($action['context']) ? $action['context'] : array();

        $context_id = '';
        if (isset($ctx['id'])) {
            $context_id = (string)(int)$ctx['id'];
        } elseif (isset($ctx['code'])) {
            $context_id = strtoupper(trim((string)$ctx['code']));
        }

        $nhis_code = isset($ctx['nhis_code']) ? strtoupper(trim((string)$ctx['nhis_code'])) : '';

        return md5($action_key . '|' . $context_id . '|' . $nhis_code);
    }

    private function _load_queue_model()
    {
        if (isset($this->queue) && is_object($this->queue)) {
            return;
        }
        $CI =& get_instance();
        if (isset($CI) && is_object($CI) && method_exists($CI, 'load')) {
            $CI->load->model('app/Nhis_auto_fix_queue_model', 'queue');
            if (isset($CI->queue)) {
                $this->queue = $CI->queue;
            }
        }
    }

    private function _verify_before_after_state(array $action, array $before, array $after, array $handlerOut)
    {
        $action_key = isset($action['action_key']) ? strtolower(trim((string)$action['action_key'])) : '';
        if ($action_key === 'create_mapping') {
            if (!empty($handlerOut['success']) && !empty($handlerOut['created'])) {
                return array('ok' => true);
            }
            if (!empty($handlerOut['success']) && !empty($handlerOut['no_op'])) {
                return array('ok' => true);
            }
            return array('ok' => false, 'message' => 'Mapping verification failed');
        }

        // Default: if handler says success, accept.
        if (!empty($handlerOut['success'])) {
            return array('ok' => true);
        }

        return array('ok' => false, 'message' => 'Handler reported failure');
    }
}
