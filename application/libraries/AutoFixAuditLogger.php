<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * AutoFixAuditLogger
 *
 * Minimal, safe audit logger abstraction.
 *
 * Default implementation: logs to CI log only.
 * Integration can extend this to write to nhis_audit_log once the canonical schema is confirmed.
 */
class AutoFixAuditLogger
{
    public function log(array $audit)
    {
        log_message('info', '[NHIS_AUTO_FIX_AUDIT] ' . json_encode($audit));
        return array('success' => true);
    }
}
