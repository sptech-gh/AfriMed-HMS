<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class AutoFixRollbackService
{
    /**
     * Placeholder rollback service.
     *
     * NOTE:
     * - Rollback requires durable audit persistence (DB table) with before_state.
     * - This method is intentionally non-operative until integration chooses a storage backend.
     */
    public function restore($auditId)
    {
        return array(
            'success' => false,
            'error' => 'ROLLBACK_NOT_IMPLEMENTED',
            'message' => 'Rollback requires persisted audit snapshots and an approved restore plan.',
            'audit_id' => $auditId,
        );
    }
}
