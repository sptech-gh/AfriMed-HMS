<?php
/**
 * MedicationController — Phase 4
 * Unified Prescription Engine
 *
 * Routes:
 *   POST   app/MedicationController/savePrescription
 *   POST   app/MedicationController/updatePrescription
 *   POST   app/MedicationController/cancelPrescription
 *   GET    app/MedicationController/getPrescription/<iop_med_id>
 *   GET    app/MedicationController/getVisitPrescriptions/<iop_id>
 *   POST   app/MedicationController/dispense_ajax
 *   GET    app/MedicationController/queue_json
 *
 * All responses are JSON (Content-Type: application/json).
 * No HTML views rendered here — this is a pure API controller.
 */
class MedicationController extends General
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/Prescription_engine_model');
        $this->load->model('app/pharmacy_model');
        $this->load->model('app/opd_model');

        /* Boot Phase 4 schema on every request (idempotent, cached with static) */
        $this->Prescription_engine_model->ensure_phase4_schema();

        if (!$this->is_logged_in()) {
            $this->_json(array('success' => false, 'message' => 'Not authenticated'), 401);
            exit;
        }
    }

    /* =========================================================================
     * savePrescription()
     *
     * Unified entry point replacing OPD, IPD and Nurse separate saves.
     * Accepts a JSON-encoded "entries" array from the Phase 3 modal.
     *
     * POST params:
     *   visit_id    (iop_id, URL-safe or raw)
     *   patient_id  (patient_no)
     *   entries     (JSON array — see Phase 3 MedicationModal.collectEntries())
     *   module      (opd|ipd|nurse — for audit; does not change save logic)
     * ======================================================================= */
    public function savePrescription()
    {
        if ($this->input->method(true) !== 'POST') {
            $this->_json(array('success' => false, 'message' => 'POST required'), 405);
            return;
        }

		if (!$this->current_user_is_doctor() && !$this->current_user_is_nurse() && !$this->current_user_is_admin() && !$this->current_user_is_super_admin()) {
			$this->_json(array('success' => false, 'message' => 'Access denied'), 403);
			return;
		}

        $raw_visit  = trim((string)$this->input->post('visit_id'));
        $patient_no = trim((string)$this->input->post('patient_id'));
        $module     = strtolower(trim((string)$this->input->post('module'))) ?: 'opd';
        $entries    = json_decode((string)$this->input->post('entries'), true);
        $user_id    = (string)$this->session->userdata('user_id');

        if ($raw_visit === '' || $patient_no === '') {
            $this->_json(array('success' => false, 'message' => 'visit_id and patient_id are required'));
            return;
        }
        if (empty($entries) || !is_array($entries)) {
            $this->_json(array('success' => false, 'message' => 'No medication entries provided'));
            return;
        }

        /* Decode URL-safe visit ID if needed */
        $iop_id = function_exists('url_decode_id') ? url_decode_id($raw_visit) : $raw_visit;

        /* Schema: check which optional columns exist */
        $schema = $this->_medication_schema();

        $this->db->trans_start();

        $saved       = array();
        $skipped     = 0;
        $now         = date('Y-m-d H:i:s');

        foreach ($entries as $entry) {
            $drug_id       = isset($entry['drug_name'])     ? (int)$entry['drug_name']                      : 0;
            $medicine_text = isset($entry['medicine_text']) ? strip_tags(trim($entry['medicine_text']))      : '';

            if (!$drug_id && $medicine_text === '') {
                $skipped++;
                continue;
            }

            /* Core insert data — always present in iop_medication */
            $data = array(
                'iop_id'        => $iop_id,
                'medicine_id'   => $drug_id ?: null,
                'medicine_text' => $medicine_text,
                'dosage'        => isset($entry['dosage'])      ? strip_tags(trim($entry['dosage']))      : '',
                'instruction'   => isset($entry['instruction']) ? strip_tags(trim($entry['instruction'])) : '',
                'advice'        => isset($entry['advice'])      ? strip_tags(trim($entry['advice']))      : '',
                'days'          => isset($entry['days'])        ? max(1, (int)$entry['days'])             : 1,
                'total_qty'     => isset($entry['total_qty'])   ? max(1, (int)$entry['total_qty'])        : 1,
                'cPreparedBy'   => $user_id,
                'dDate'         => $now,
                'InActive'      => 0,
            );

            /* Optional columns — guarded by schema check */
            if ($schema['frequency'])          $data['frequency']          = isset($entry['frequency'])     ? strip_tags(trim($entry['frequency']))     : (isset($entry['freq_code']) ? $entry['freq_code'] : '');
            if ($schema['prescribed_by'])      $data['prescribed_by']      = $user_id;
            if ($schema['dispensing_status'])  $data['dispensing_status']  = 'PENDING';
            if ($schema['payment_status'])     $data['payment_status']     = 'PENDING';
            if ($schema['route'])              $data['route']              = isset($entry['route'])        ? strip_tags(trim($entry['route']))         : null;
            if ($schema['drug_form'])          $data['drug_form']          = isset($entry['drug_form'])    ? strip_tags(trim($entry['drug_form']))     : null;
            if ($schema['diagnosis_code'])     $data['diagnosis_code']     = isset($entry['diagnosis_code']) ? strip_tags(trim($entry['diagnosis_code'])) : null;

            /* Phase 4 columns */
            if ($schema['unit'])               $data['unit']               = isset($entry['unit'])          ? strip_tags(trim($entry['unit']))          : null;
            if ($schema['freq_code'])          $data['freq_code']          = isset($entry['freq_code'])     ? strip_tags(trim($entry['freq_code']))     : null;
            if ($schema['is_nhis_covered'])    $data['is_nhis_covered']    = isset($entry['is_nhis_covered']) ? (int)$entry['is_nhis_covered']          : 0;
            if ($schema['is_prn'])             $data['is_prn']             = isset($entry['is_prn'])        ? (int)$entry['is_prn']                    : 0;
            if ($schema['is_urgent'])          $data['is_urgent']          = isset($entry['is_urgent'])     ? (int)$entry['is_urgent']                 : 0;

            if (!$this->db->insert('iop_medication', $data)) {
                $skipped++;
                continue;
            }

            $iop_med_id = (int)$this->db->insert_id();

            /* Generate & stamp prescription number */
            $rx_no = $this->Prescription_engine_model->generate_prescription_no();
            $this->Prescription_engine_model->stamp_prescription_no($iop_med_id, $rx_no);

            $saved[] = array(
                'iop_med_id'      => $iop_med_id,
                'prescription_no' => $rx_no,
                'drug_id'         => $drug_id,
                'drug_name'       => $medicine_text,
                'qty'             => $data['total_qty'],
                'is_nhis'         => !empty($data['is_nhis_covered']) ? (int)$data['is_nhis_covered'] : 0,
            );
        }

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            log_message('error', '[MedicationController::savePrescription] TRANSACTION FAILED iop=' . $iop_id . ' patient=' . $patient_no);
            $this->_json(array('success' => false, 'message' => 'Database error — no prescriptions were saved. Please retry.'));
            return;
        }

        /* ── Post-commit: billing queue + NHIS queue + audit (non-blocking) ── */
        foreach ($saved as $s) {
            $unit_price = $this->_get_drug_price($s['drug_id']);

            /* Route NHIS to nhis_claim_queue */
            if ($s['is_nhis']) {
                $this->Prescription_engine_model->push_to_nhis_queue(array(
                    'iop_med_id'      => $s['iop_med_id'],
                    'prescription_no' => $s['prescription_no'],
                    'patient_no'      => $patient_no,
                    'iop_id'          => $iop_id,
                    'drug_id'         => $s['drug_id'],
                    'drug_name'       => $s['drug_name'],
                    'quantity'        => $s['qty'],
                    'unit_price'      => $unit_price,
                ));
            }

            /* Audit: PRESCRIBED */
            $this->Prescription_engine_model->audit_log('PRESCRIBED', array(
                'iop_med_id'      => $s['iop_med_id'],
                'prescription_no' => $s['prescription_no'],
                'iop_id'          => $iop_id,
                'patient_no'      => $patient_no,
                'new_status'      => 'PENDING',
                'notes'           => 'Saved via MedicationController (' . $module . ')',
                'user_id'         => $user_id,
            ));
        }

        $this->_json(array(
            'success'       => true,
            'saved'         => count($saved),
            'skipped'       => $skipped,
            'prescriptions' => $saved,
            'message'       => count($saved) . ' prescription(s) saved successfully.',
        ));
    }

    /* =========================================================================
     * updatePrescription()
     *
     * POST params: iop_med_id, [dosage, unit, freq_code, frequency, route,
     *              drug_form, days, total_qty, instruction, advice,
     *              diagnosis_code, is_prn, is_urgent, is_nhis_covered]
     * ======================================================================= */
    public function updatePrescription()
    {
        if ($this->input->method(true) !== 'POST') {
            $this->_json(array('success' => false, 'message' => 'POST required'), 405);
            return;
        }

		if (!$this->current_user_is_doctor() && !$this->current_user_is_admin() && !$this->current_user_is_super_admin()) {
			$this->_json(array('success' => false, 'message' => 'Access denied'), 403);
			return;
		}

        $iop_med_id = (int)$this->input->post('iop_med_id');
        $user_id    = (string)$this->session->userdata('user_id');

        if ($iop_med_id <= 0) {
            $this->_json(array('success' => false, 'message' => 'iop_med_id is required'));
            return;
        }

        $fields = array();
        $allowed = array('dosage', 'unit', 'freq_code', 'frequency', 'route', 'drug_form',
                         'days', 'total_qty', 'instruction', 'advice', 'diagnosis_code',
                         'is_prn', 'is_urgent', 'is_nhis_covered');
        foreach ($allowed as $f) {
            $val = $this->input->post($f);
            if ($val !== null && $val !== false) {
                $fields[$f] = in_array($f, array('is_prn', 'is_urgent', 'is_nhis_covered'))
                    ? (int)$val
                    : strip_tags(trim((string)$val));
            }
        }

        $result = $this->Prescription_engine_model->update_prescription($iop_med_id, $fields, $user_id);
        $this->_json($result + array('success' => $result['ok']));
    }

    /* =========================================================================
     * cancelPrescription()
     *
     * POST params: iop_med_id, reason (optional)
     * ======================================================================= */
    public function cancelPrescription()
    {
        if ($this->input->method(true) !== 'POST') {
            $this->_json(array('success' => false, 'message' => 'POST required'), 405);
            return;
        }

		if (!$this->current_user_is_doctor() && !$this->current_user_is_admin() && !$this->current_user_is_super_admin()) {
			$this->_json(array('success' => false, 'message' => 'Access denied'), 403);
			return;
		}

        $iop_med_id = (int)$this->input->post('iop_med_id');
        $reason     = strip_tags(trim((string)$this->input->post('reason')));
        $user_id    = (string)$this->session->userdata('user_id');

        if ($iop_med_id <= 0) {
            $this->_json(array('success' => false, 'message' => 'iop_med_id is required'));
            return;
        }

        $result = $this->Prescription_engine_model->cancel_prescription($iop_med_id, $user_id, $reason);
        $this->_json($result + array('success' => $result['ok']));
    }

    /* =========================================================================
     * getPrescription()
     *
     * GET  app/MedicationController/getPrescription/<iop_med_id>
     * ======================================================================= */
    public function getPrescription($iop_med_id = 0)
    {
        $iop_med_id = (int)$iop_med_id;
        if ($iop_med_id <= 0) {
            $this->_json(array('success' => false, 'message' => 'iop_med_id required'));
            return;
        }

        $rx = $this->Prescription_engine_model->get_prescription($iop_med_id);
        if (!$rx) {
            $this->_json(array('success' => false, 'message' => 'Prescription not found'));
            return;
        }

        $this->_json(array('success' => true, 'prescription' => $rx));
    }

    /* =========================================================================
     * getVisitPrescriptions()
     *
     * GET  app/MedicationController/getVisitPrescriptions/<iop_id>
     * ======================================================================= */
    public function getVisitPrescriptions($iop_id = '')
    {
        $iop_id = trim((string)$iop_id);
        if ($iop_id === '') {
            $this->_json(array('success' => false, 'message' => 'visit_id required'));
            return;
        }

        $decoded  = function_exists('url_decode_id') ? url_decode_id($iop_id) : $iop_id;
        $inc_canc = (bool)$this->input->get('include_cancelled');
        $list     = $this->Prescription_engine_model->get_visit_prescriptions($decoded, $inc_canc);

        $this->_json(array(
            'success' => true,
            'visit_id' => $decoded,
            'count'    => count($list),
            'prescriptions' => $list,
        ));
    }

    /* =========================================================================
     * dispense_ajax()
     *
     * POST params: iop_med_id, qty, status (DISPENSED|PARTIAL|HELD), notes
     * Delegates to pharmacy_model->dispense_medication() (existing Phase logic).
     * ======================================================================= */
    public function dispense_ajax()
    {
        if ($this->input->method(true) !== 'POST') {
            $this->_json(array('success' => false, 'message' => 'POST required'), 405);
            return;
        }

        $module = $this->current_user_module_key();
        $allowed_dispense = array('pharmacist', 'pharmacy', 'administrator', 'super_admin');
        $hasNurseDispense = ($this->current_user_is_nurse() && function_exists('has_privilege') && has_privilege('pharmacy_dispense_access'));
        if (!in_array($module, $allowed_dispense) && !$this->current_user_is_super_admin() && !$hasNurseDispense) {
            $this->_json(array('success' => false, 'message' => 'Access denied — pharmacist role required'), 403);
            return;
        }

        $iop_med_id = (int)$this->input->post('iop_med_id');
        $qty        = (float)$this->input->post('qty');
        $status     = strtoupper(trim((string)$this->input->post('status')));
        $notes      = strip_tags(trim((string)$this->input->post('notes')));
        $user_id    = (string)$this->session->userdata('user_id');
        $batch_no   = strip_tags(trim((string)$this->input->post('batch_no')));

        if ($iop_med_id <= 0 || $qty <= 0 || !in_array($status, array('DISPENSED','PARTIAL','HELD'))) {
            $this->_json(array('success' => false, 'message' => 'iop_med_id, qty and valid status are required'));
            return;
        }

        $result = $this->pharmacy_model->dispense_medication($iop_med_id, $qty, $status, $notes, $user_id, $batch_no);

        if (!$result['ok']) {
            $this->_json(array('success' => false, 'message' => implode('; ', $result['errors'])));
            return;
        }

        /* Fetch prescription_no for audit */
        $med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id))->row();
        $rx_no  = ($med && isset($med->prescription_no)) ? $med->prescription_no : '';
        $iop_id = ($med && isset($med->iop_id)) ? $med->iop_id : '';

        /* Update billing queue dispense_status */
        $this->load->model('app/pharmacy_billing_model');
        $pbq_status = ($status === 'DISPENSED') ? 'DISPENSED' : 'PARTIAL';
        $pbqOk = $this->pharmacy_billing_model->sync_dispense_status($iop_med_id, $pbq_status, $user_id);
        if (!$pbqOk) {
            $this->_json(array('success' => false, 'message' => 'Failed to sync dispense status to billing queue.'));
            return;
        }

        /* Audit: DISPENSED / PARTIAL / HELD */
        $this->Prescription_engine_model->audit_log($status, array(
            'iop_med_id'      => $iop_med_id,
            'prescription_no' => $rx_no,
            'iop_id'          => $iop_id,
            'old_status'      => 'PENDING',
            'new_status'      => $status,
            'notes'           => $notes,
            'user_id'         => $user_id,
        ));

        $this->_json(array(
            'success'         => true,
            'message'         => 'Medication ' . strtolower($status) . ' successfully.',
            'prescription_no' => $rx_no,
            'status'          => $status,
        ));
    }

    /* =========================================================================
     * queue_json()
     *
     * GET  app/MedicationController/queue_json?status=PENDING&date=2026-04-13
     * Returns pharmacy queue data for the dispense screen.
     * ======================================================================= */
    public function queue_json()
    {
        if (!$this->is_logged_in()) {
            $this->_json(array('success' => false, 'message' => 'Not authenticated'), 401);
            return;
        }

        $status    = strtoupper(trim((string)$this->input->get('status'))) ?: 'PENDING';
        $date_from = trim((string)$this->input->get('date'))               ?: date('Y-m-d');
        $limit     = min((int)$this->input->get('limit'), 500)             ?: 200;

        $queue = $this->pharmacy_model->get_pending_pharmacy_bills(array(
            'status'    => $status,
            'date_from' => $date_from,
            'date_to'   => $date_from,
            'limit'     => $limit,
        ));

        /* Enrich with prescription_no from iop_medication */
        if (!empty($queue) && $this->db->field_exists('prescription_no', 'iop_medication')) {
            $ids = array_map(function($q) { return (int)$q->iop_med_id; }, $queue);
            $rxMap = array();
            $rows = $this->db->select('iop_med_id, prescription_no')
                ->where_in('iop_med_id', $ids)
                ->get('iop_medication')->result();
            foreach ($rows as $r) {
                $rxMap[(int)$r->iop_med_id] = $r->prescription_no;
            }
            foreach ($queue as &$item) {
                $item->prescription_no = isset($rxMap[(int)$item->iop_med_id])
                    ? $rxMap[(int)$item->iop_med_id] : '';
            }
            unset($item);
        }

        header('Content-Type: application/json');
        echo json_encode(array(
            'success' => true,
            'status'  => $status,
            'date'    => $date_from,
            'count'   => count($queue),
            'queue'   => $queue,
        ));
    }

    /* =========================================================================
     * PRIVATE HELPERS
     * ======================================================================= */

    private function _json($data, $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function _medication_schema()
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cols = array(
            'frequency', 'prescribed_by', 'dispensing_status', 'payment_status',
            'route', 'drug_form', 'diagnosis_code', 'diagnosis_description',
            'unit', 'freq_code', 'is_nhis_covered', 'is_prn', 'is_urgent',
        );
        $cache = array();
        foreach ($cols as $c) {
            $cache[$c] = $this->db->field_exists($c, 'iop_medication');
        }
        return $cache;
    }

    private function _get_drug_price($drug_id)
    {
        $drug_id = (int)$drug_id;
        if ($drug_id <= 0) return 0.00;
        $row = $this->db->select('nPrice')->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        return ($row && isset($row->nPrice)) ? (float)$row->nPrice : 0.00;
    }
}
