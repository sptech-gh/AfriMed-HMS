<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class VisitBillingOrchestrator
{
    /** @var CI_Controller */
    protected $CI;

    public function __construct()
    {
        $this->CI =& get_instance();
        if (!isset($this->CI->db)) {
            $this->CI->load->database();
        }
    }

    /**
     * Ensure that visit-fee SSOT billing records exist for a visit and are
     * present in the billing_queue. This method is safe to call repeatedly
     * and only uses existing models/engines.
     *
     * @param string $iop_id
     * @param string $patient_no
     * @param string $user_id
     * @return array
     */
    public function ensureVisitBillable($iop_id, $patient_no, $user_id)
    {
        $iop_id     = trim((string)$iop_id);
        $patient_no = trim((string)$patient_no);
        $user_id    = trim((string)$user_id);

        $result = array(
            'success'               => false,
            'transactions_verified' => false,
            'queue_verified'        => false,
            'auto_billed'           => false,
            'queue_backfilled'      => false,
            'details'               => array(
                'has_registration_txn'  => false,
                'has_consultation_txn'  => false,
                'has_queue'             => false,
            ),
            'errors'                => array(),
        );

        if ($iop_id === '' || $patient_no === '') {
            $result['errors'][] = 'Missing iop_id or patient_no';
            return $result;
        }

        try {
            $this->CI->load->model('app/unified_billing_model');
        } catch (Throwable $e) {
            $result['errors'][] = 'Unable to load unified_billing_model: ' . $e->getMessage();
            return $result;
        }

        if (isset($this->CI->unified_billing_model) && method_exists($this->CI->unified_billing_model, 'ensure_unified_billing_schema')) {
            try {
                $this->CI->unified_billing_model->ensure_unified_billing_schema();
            } catch (Throwable $e) {
                $result['errors'][] = 'ensure_unified_billing_schema failed: ' . $e->getMessage();
            }
        }

        $integrity = $this->getIntegrityService();

        // --- Visit-fee transactions -------------------------------------------------
        $hasReg = $integrity->has_registration_transaction($iop_id, $patient_no);
        $hasCon = $integrity->has_consultation_transaction($iop_id, $patient_no);
        $result['details']['has_registration_txn'] = $hasReg;
        $result['details']['has_consultation_txn'] = $hasCon;

        if (!$hasReg || !$hasCon) {
            try {
                $this->CI->load->model('app/visit_billing_resolver_model');
                if (isset($this->CI->visit_billing_resolver_model)
                    && is_object($this->CI->visit_billing_resolver_model)
                    && method_exists($this->CI->visit_billing_resolver_model, 'auto_bill_visit_fees')) {
                    $ab = $this->CI->visit_billing_resolver_model->auto_bill_visit_fees($iop_id, $patient_no, $user_id);
                    if (is_array($ab)) {
                        if (isset($ab['created']) && is_array($ab['created']) && count($ab['created']) > 0) {
                            $result['auto_billed'] = true;
                        }
                        if (isset($ab['ok']) && !$ab['ok'] && isset($ab['error']) && $ab['error'] !== '') {
                            $result['errors'][] = (string)$ab['error'];
                        }
                    }
                }
            } catch (Throwable $e) {
                $result['errors'][] = 'auto_bill_visit_fees failed: ' . $e->getMessage();
            }

            // Re-check after auto-billing attempt
            $hasReg = $integrity->has_registration_transaction($iop_id, $patient_no);
            $hasCon = $integrity->has_consultation_transaction($iop_id, $patient_no);
            $result['details']['has_registration_txn'] = $hasReg;
            $result['details']['has_consultation_txn'] = $hasCon;
        }

        $result['transactions_verified'] = (bool)($hasReg || $hasCon);

        // --- Billing queue ---------------------------------------------------------
        $hasQueue = $integrity->has_queue_entries($iop_id, $patient_no);
        $result['details']['has_queue'] = $hasQueue;

        if (!$hasQueue && isset($this->CI->unified_billing_model) && is_object($this->CI->unified_billing_model)) {
            try {
                if (method_exists($this->CI->unified_billing_model, 'backfill_visit_fees_for_queue')) {
                    $c1 = (int)$this->CI->unified_billing_model->backfill_visit_fees_for_queue($iop_id, $patient_no);
                    if ($c1 > 0) {
                        $result['queue_backfilled'] = true;
                    }
                }
                if (method_exists($this->CI->unified_billing_model, 'backfill_pending_transactions_to_queue')) {
                    $c2 = (int)$this->CI->unified_billing_model->backfill_pending_transactions_to_queue($iop_id, $patient_no);
                    if ($c2 > 0) {
                        $result['queue_backfilled'] = true;
                    }
                }
            } catch (Throwable $e) {
                $result['errors'][] = 'Queue backfill failed: ' . $e->getMessage();
            }

            $hasQueue = $integrity->has_queue_entries($iop_id, $patient_no);
            $result['details']['has_queue'] = $hasQueue;
        }

        $result['queue_verified'] = (bool)$hasQueue;
        $result['success'] = empty($result['errors']);

        return $result;
    }

    /**
     * Ensure that an invoice exists for a billable visit. If an invoice is
     * already present in iop_billing, it is returned. Otherwise an invoice is
     * generated via Unified_billing_model::generate_invoice_by_subject using
     * billing_subject_type = PATIENT_VISIT.
     *
     * @param string $iop_id
     * @param string $patient_no
     * @param string $user_id
     * @return array
     */
    public function ensureVisitInvoice($iop_id, $patient_no, $user_id)
    {
        $iop_id     = trim((string)$iop_id);
        $patient_no = trim((string)$patient_no);
        $user_id    = trim((string)$user_id);

        $out = array(
            'success'          => false,
            'invoice_exists'   => false,
            'invoice_generated'=> false,
            'invoice'          => null,
            'error'            => null,
        );

        if ($iop_id === '' || $patient_no === '') {
            $out['error'] = 'Missing iop_id or patient_no';
            return $out;
        }

        try {
            $this->CI->load->model('app/unified_billing_model');
        } catch (Throwable $e) {
            $out['error'] = 'Unable to load unified_billing_model: ' . $e->getMessage();
            return $out;
        }

        if (!isset($this->CI->unified_billing_model) || !is_object($this->CI->unified_billing_model)) {
            $out['error'] = 'unified_billing_model not available';
            return $out;
        }

        try {
            if (method_exists($this->CI->unified_billing_model, 'ensure_unified_billing_schema')) {
                $this->CI->unified_billing_model->ensure_unified_billing_schema();
            }
            if (method_exists($this->CI->unified_billing_model, 'ensure_billing_performance_indexes')) {
                $this->CI->unified_billing_model->ensure_billing_performance_indexes();
            }
        } catch (Throwable $e) {
            // Schema/index ensure failures should not abort invoice lookup completely
        }

        // Step 1/2: check for existing invoice
        try {
            if (method_exists($this->CI->unified_billing_model, 'has_invoice')
                && $this->CI->unified_billing_model->has_invoice($iop_id)) {
                if (method_exists($this->CI->unified_billing_model, 'get_visit_invoice')) {
                    $row = $this->CI->unified_billing_model->get_visit_invoice($iop_id);
                    if ($row) {
                        $out['invoice_exists'] = true;
                        $out['invoice'] = $this->normaliseInvoiceRow($row);
                        $out['success'] = true;
                        return $out;
                    }
                }
            }
        } catch (Throwable $e) {
            // Fall through to generation path
        }

        // Step 3: generate invoice via subject-based engine
        $subjectId = $iop_id . '|' . $patient_no;
        $gen = null;
        try {
            if (method_exists($this->CI->unified_billing_model, 'generate_invoice_by_subject')) {
                $gen = $this->CI->unified_billing_model->generate_invoice_by_subject(
                    'PATIENT_VISIT',
                    $subjectId,
                    null,
                    $user_id
                );
            } else {
                $out['error'] = 'generate_invoice_by_subject not available on unified_billing_model';
                return $out;
            }
        } catch (Throwable $e) {
            $out['error'] = 'generate_invoice_by_subject failed: ' . $e->getMessage();
        }

        if (is_array($gen) && isset($gen['success']) && $gen['success']) {
            $row = null;
            try {
                if (method_exists($this->CI->unified_billing_model, 'get_visit_invoice')) {
                    $row = $this->CI->unified_billing_model->get_visit_invoice($iop_id);
                }
            } catch (Throwable $e) {
                $row = null;
            }
            if ($row) {
                $out['invoice_exists']    = true;
                $out['invoice_generated'] = true;
                $out['invoice']           = $this->normaliseInvoiceRow($row);
                $out['success']           = true;
                return $out;
            }

            $out['error'] = 'Invoice engine reported success but invoice header could not be found.';
            return $out;
        }

        // If generation failed, re-check in case another process created the invoice
        try {
            if (method_exists($this->CI->unified_billing_model, 'has_invoice')
                && $this->CI->unified_billing_model->has_invoice($iop_id)
                && method_exists($this->CI->unified_billing_model, 'get_visit_invoice')) {
                $row = $this->CI->unified_billing_model->get_visit_invoice($iop_id);
                if ($row) {
                    $out['invoice_exists'] = true;
                    $out['invoice']        = $this->normaliseInvoiceRow($row);
                    $out['success']        = true;
                    return $out;
                }
            }
        } catch (Throwable $e) {
            // Ignore and fall through to error reporting
        }

        $out['error'] = isset($gen['error']) ? (string)$gen['error'] : 'Unable to generate invoice for visit.';
        return $out;
    }

    /**
     * @return BillingIntegrityService
     */
    protected function getIntegrityService()
    {
        $this->CI->load->library('BillingIntegrityService');
        if (isset($this->CI->billingintegrityservice) && $this->CI->billingintegrityservice instanceof BillingIntegrityService) {
            return $this->CI->billingintegrityservice;
        }
        return new BillingIntegrityService();
    }

    /**
     * Normalise an iop_billing row into a lightweight associative array.
     *
     * @param object $row
     * @return array
     */
    protected function normaliseInvoiceRow($row)
    {
        return array(
            'invoice_no'     => isset($row->invoice_no) ? (string)$row->invoice_no : null,
            'iop_id'         => isset($row->iop_id) ? (string)$row->iop_id : null,
            'patient_no'     => isset($row->patient_no) ? (string)$row->patient_no : null,
            'total_amount'   => isset($row->total_amount) ? (float)$row->total_amount : 0.0,
            'payment_status' => isset($row->payment_status) ? (string)$row->payment_status : '',
            'balance_due'    => isset($row->balance_due) ? (float)$row->balance_due : 0.0,
            'payer_type'     => isset($row->payer_type) ? (string)$row->payer_type : null,
            'dDate'          => isset($row->dDate) ? (string)$row->dDate : null,
        );
    }
}
