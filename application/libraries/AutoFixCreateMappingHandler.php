<?php
defined('BASEPATH') OR exit('No direct script access allowed');

require_once APPPATH . 'libraries/AutoFixHandlerInterface.php';

class AutoFixCreateMappingHandler implements AutoFixHandlerInterface
{
    public function handle(array $action, array $options = array()): array
    {
        $ctx = isset($action['context']) && is_array($action['context']) ? $action['context'] : array();
        $module = isset($ctx['module']) ? strtoupper(trim((string)$ctx['module'])) : '';
        $local_service_id = isset($ctx['id']) ? (int)$ctx['id'] : 0;

        if ($module === '' || $local_service_id <= 0) {
            return array(
                'handler' => __CLASS__,
                'success' => false,
                'message' => 'Invalid context for create_mapping',
                'created' => false,
                'no_op' => false,
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id),
            );
        }

        // SAFE handler: does not guess NHIS code.
        // It can only create a placeholder mapping when an explicit nhis_code is supplied.
        $nhis_code = isset($ctx['nhis_code']) ? strtoupper(trim((string)$ctx['nhis_code'])) : '';
        if ($nhis_code === '') {
            return array(
                'handler' => __CLASS__,
                'success' => false,
                'message' => 'Missing nhis_code; cannot auto-create mapping without explicit code',
                'created' => false,
                'no_op' => false,
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
                'requires' => array('nhis_code'),
            );
        }

        // Writes are controlled by engine option.
        if (empty($options['allow_writes'])) {
            return array(
                'handler' => __CLASS__,
                'success' => true,
                'message' => 'Mapping creation planned (writes disabled)',
                'created' => false,
                'no_op' => false,
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => $nhis_code),
                'planned' => true,
            );
        }

        // Implementation intentionally minimal and safe: do not create tables, do not alter schema.
        $CI =& get_instance();
        $CI->load->database();

        // Verify table exists
        if (!method_exists($CI->db, 'table_exists') || !$CI->db->table_exists('nhis_ref_service_mapping')) {
            return array(
                'handler' => __CLASS__,
                'success' => false,
                'message' => 'Mapping table not available',
                'created' => false,
                'no_op' => false,
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
            );
        }

        // Idempotency: check existing
        $existing = $CI->db->select('id, nhis_code')
            ->from('nhis_ref_service_mapping')
            ->where(array('module' => $module, 'local_service_id' => $local_service_id))
            ->limit(2)
            ->get()->result();

        if (is_array($existing) && count($existing) === 1) {
            return array(
                'handler' => __CLASS__,
                'success' => true,
                'message' => 'Mapping already exists',
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => (string)$existing[0]->nhis_code),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => (string)$existing[0]->nhis_code),
                'created' => false,
                'no_op' => true,
            );
        }

        if (is_array($existing) && count($existing) > 1) {
            return array(
                'handler' => __CLASS__,
                'success' => false,
                'message' => 'Ambiguous mapping exists; manual cleanup required',
                'created' => false,
                'no_op' => false,
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id),
            );
        }

        $CI->db->insert('nhis_ref_service_mapping', array(
            'module' => $module,
            'local_service_id' => $local_service_id,
            'nhis_code' => $nhis_code,
            'created_at' => date('Y-m-d H:i:s'),
        ));

        if ($CI->db->affected_rows() !== 1) {
            return array(
                'handler' => __CLASS__,
                'success' => false,
                'message' => 'Failed to create mapping',
                'created' => false,
                'no_op' => false,
                'before_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
                'after_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
            );
        }

        return array(
            'handler' => __CLASS__,
            'success' => true,
            'message' => 'Mapping created',
            'before_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => null),
            'after_state' => array('module' => $module, 'local_service_id' => $local_service_id, 'nhis_code' => $nhis_code),
            'created' => true,
            'no_op' => false,
        );
    }
}
