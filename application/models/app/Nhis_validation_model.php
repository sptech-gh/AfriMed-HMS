<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Nhis_validation_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->load->model('app/Nhis_mapping_model', 'mapping_model');
        $this->load->model('app/Nhis_reference_model', 'ref_model');
    }

    public function _get_enforcement_mode()
    {
        $mode = null;

        if (defined('NHIS_ENFORCEMENT_MODE')) {
            $mode = NHIS_ENFORCEMENT_MODE;
        } elseif (isset($this->config)) {
            $mode = $this->config->item('NHIS_ENFORCEMENT_MODE');
        }

        $mode = (int)$mode;
        if ($mode < 0 || $mode > 3) {
            $mode = 0;
        }

        return $mode;
    }

    private function _resolve_item_ref($module, $local_service_id)
    {
        $module = strtoupper(trim((string)$module));
        $local_service_id = (int)$local_service_id;
        if ($module === '' || $local_service_id <= 0) {
            return null;
        }

        if ($module === 'PHARMACY') {
            return 'iop_med_id:' . $local_service_id;
        }
        if ($module === 'LAB' || $module === 'LABORATORY') {
            return 'io_lab_id:' . $local_service_id;
        }
        if ($module === 'SONO' || $module === 'SONOGRAPHY') {
            if (method_exists($this->db, 'table_exists') && $this->db->table_exists('iop_sonography_charge')) {
                $this->db->select('charge_id');
                $this->db->from('iop_sonography_charge');
                $this->db->where(array('InActive' => 0, 'charge_id' => $local_service_id));
                $this->db->limit(1);
                $ch = $this->db->get()->row();
                if ($ch && isset($ch->charge_id) && (int)$ch->charge_id > 0) {
                    return 'sono_charge_id:' . (int)$ch->charge_id;
                }

                $this->db->select('charge_id');
                $this->db->from('iop_sonography_charge');
                $this->db->where(array('InActive' => 0, 'io_lab_id' => $local_service_id));
                $this->db->order_by('charge_id', 'DESC');
                $this->db->limit(1);
                $ch = $this->db->get()->row();
                if ($ch && isset($ch->charge_id) && (int)$ch->charge_id > 0) {
                    return 'sono_charge_id:' . (int)$ch->charge_id;
                }
            }

            return 'sono_req_io_lab_id:' . $local_service_id;
        }
        if ($module === 'RAD' || $module === 'RADIOLOGY') {
            return 'radiology_order_id:' . $local_service_id;
        }

        return null;
    }

    public function validate_service($module, $local_service_id)
    {
        $module = strtoupper(trim((string)$module));
        $local_service_id = (int)$local_service_id;

        $mode = $this->_get_enforcement_mode();
        log_message('debug', '[NHIS_ENFORCEMENT] mode=' . $mode . ' context=SERVICE module=' . $module . ' id=' . $local_service_id);

        $nhis_code = $this->mapping_model->get_nhis_code($module, $local_service_id);
        if ($nhis_code === null) {
            log_message('error', '[NHIS_VALIDATION_FAIL] type=MAPPING module=' . $module . ' id=' . $local_service_id);
            return [
                'valid' => false,
                'error' => 'MAPPING_MISSING'
            ];
        }

        $service = $this->ref_model->get_service_code($nhis_code);
        if ($service === null) {
            log_message('error', '[NHIS_VALIDATION_FAIL] type=SERVICE_CODE_INVALID code=' . $nhis_code);
            return [
                'valid' => false,
                'error' => 'INVALID_SERVICE_CODE',
                'nhis_code' => $nhis_code
            ];
        }

        $tariff = $this->ref_model->get_active_tariff($nhis_code);
        if ($tariff === null) {
            log_message('error', '[NHIS_VALIDATION_FAIL] type=TARIFF_MISSING code=' . $nhis_code);
            return [
                'valid' => false,
                'error' => 'TARIFF_MISSING',
                'nhis_code' => $nhis_code
            ];
        }

        $resolved_item_ref = $this->_resolve_item_ref($module, $local_service_id);

        return [
            'valid' => true,
            'nhis_code' => $nhis_code,
            'resolved_item_ref' => $resolved_item_ref,
            'tariff' => $tariff
        ];
    }

    public function validate_diagnosis($icd10_code)
    {
        $icd10_code = strtoupper(trim((string)$icd10_code));

        $dx = $this->ref_model->get_icd10($icd10_code);
        if ($dx === null) {
            log_message('error', '[NHIS_VALIDATION_FAIL] type=ICD10_INVALID code=' . $icd10_code);
            return ['valid' => false, 'error' => 'INVALID_ICD10'];
        }

        return ['valid' => true];
    }

    public function validate_procedure($gdrg_code)
    {
        $gdrg_code = strtoupper(trim((string)$gdrg_code));

        $proc = $this->ref_model->get_gdrg($gdrg_code);
        if ($proc === null) {
            log_message('error', '[NHIS_VALIDATION_FAIL] type=GDRG_INVALID code=' . $gdrg_code);
            return ['valid' => false, 'error' => 'INVALID_GDRG'];
        }

        return ['valid' => true];
    }

    public function validate_claim_payload($payload)
    {
        $mode = $this->_get_enforcement_mode();
        log_message('debug', '[NHIS_ENFORCEMENT] mode=' . $mode . ' context=CLAIM_PAYLOAD');

        $original_errors = $this->_collect_claim_payload_errors($payload);
        $errors = $original_errors;
        $fix_results = array();

        $auto_fix_enabled = false;
        if (isset($this->config) && is_object($this->config) && method_exists($this->config, 'item')) {
            $auto_fix_enabled = !empty($this->config->item('nhis_auto_fix_on_validation'));
        }

        if ($auto_fix_enabled && !empty($errors)) {
            try {
                $fix_results = $this->_run_auto_fix_pipeline($errors);

                $revalidate = !empty($this->config->item('nhis_auto_fix_revalidate_after'));
                if ($revalidate) {
                    $errors = $this->revalidate_after_fixes($payload);
                }
            } catch (Exception $e) {
                log_message('error', '[NHIS_AUTO_FIX_VALIDATION_FAIL] err=' . $e->getMessage());
            }
        }

        if (!empty($errors)) {
            log_message('error', '[NHIS_VALIDATION_BLOCKED] errors=' . json_encode($errors));
        }

        return array(
            'valid' => empty($errors),
            'errors' => $errors,
            'auto_fix_results' => $fix_results,
            'original_errors' => $original_errors,
        );
    }

    private function _collect_claim_payload_errors($payload)
    {
        $errors = array();

        $services = array();
        $diagnoses = array();
        $procedures = array();

        if (is_array($payload)) {
            if (isset($payload['services']) && is_array($payload['services'])) {
                $services = $payload['services'];
            }
            if (isset($payload['diagnoses']) && is_array($payload['diagnoses'])) {
                $diagnoses = $payload['diagnoses'];
            }
            if (isset($payload['procedures']) && is_array($payload['procedures'])) {
                $procedures = $payload['procedures'];
            }
        }

        foreach ($services as $s) {
            $module = is_array($s) && isset($s['module']) ? (string)$s['module'] : null;
            $id = is_array($s) && isset($s['id']) ? (int)$s['id'] : null;

            $res = $this->validate_service($module, $id);
            if (empty($res['valid'])) {
                $row = array(
                    'type' => 'SERVICE',
                    'module' => strtoupper(trim((string)$module)),
                    'id' => (int)$id,
                    'error' => isset($res['error']) ? (string)$res['error'] : 'UNKNOWN'
                );
                if (isset($res['nhis_code']) && trim((string)$res['nhis_code']) !== '') {
                    $row['nhis_code'] = strtoupper(trim((string)$res['nhis_code']));
                }
                $errors[] = $row;
            }
        }

        foreach ($diagnoses as $code) {
            $res = $this->validate_diagnosis($code);
            if (empty($res['valid'])) {
                $errors[] = array(
                    'type' => 'DIAGNOSIS',
                    'code' => strtoupper(trim((string)$code)),
                    'error' => isset($res['error']) ? (string)$res['error'] : 'UNKNOWN'
                );
            }
        }

        foreach ($procedures as $code) {
            $res = $this->validate_procedure($code);
            if (empty($res['valid'])) {
                $errors[] = array(
                    'type' => 'PROCEDURE',
                    'code' => strtoupper(trim((string)$code)),
                    'error' => isset($res['error']) ? (string)$res['error'] : 'UNKNOWN'
                );
            }
        }

        return $errors;
    }

    private function _run_auto_fix_pipeline(array $errors)
    {
        $this->load->model('app/Nhis_error_intelligence_model', 'nhis_error_intel');
        $insights = $this->nhis_error_intel->analyze_errors($errors);

        require_once APPPATH . 'libraries/ActionRegistry.php';
        require_once APPPATH . 'libraries/NHISActionRouter.php';
        require_once APPPATH . 'libraries/AutoFixRegistry.php';
        require_once APPPATH . 'libraries/NHISAutoFixEngine.php';

        $actionRegistry = new ActionRegistry();
        $actionRegistry->register('create_mapping', 'CreateMappingHandler');
        $actionRegistry->register('import_tariff', 'FixTariffHandler');

        $router = new NHISActionRouter($actionRegistry);
        $routed = $router->route($insights, array(
            'mode' => NHISActionRouter::MODE_LOG_ONLY,
            'allow_auto_execute' => false,
        ));

        $autoFixRegistry = new AutoFixRegistry();
        $autoFixRegistry->register('create_mapping', 'AutoFixCreateMappingHandler');
        $autoFixRegistry->register('import_tariff', 'AutoFixImportTariffHandler');

        $engine = new NHISAutoFixEngine($autoFixRegistry);

        $minConfidence = 0.7;
        if (isset($this->config) && is_object($this->config) && method_exists($this->config, 'item')) {
            $min = $this->config->item('nhis_auto_fix_min_confidence');
            if (is_numeric($min)) {
                $minConfidence = (float)$min;
            }
        }

        $fixResults = array();
        foreach ($routed as $action) {
            if (!is_array($action)) {
                continue;
            }
            $k = isset($action['action_key']) ? (string)$action['action_key'] : '';
            if (AutoFixSafetyMap::zone($k) !== AutoFixSafetyMap::ZONE_SAFE) {
                continue;
            }
            $fixResults[] = $engine->process($action, array(
                'run_mode' => NHISAutoFixEngine::RUN_DRY_RUN,
                'allow_writes' => false,
                'min_confidence' => $minConfidence,
            ));
        }

        return $fixResults;
    }

    private function revalidate_after_fixes($payload)
    {
        return $this->_collect_claim_payload_errors($payload);
    }
}
