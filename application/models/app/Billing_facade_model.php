<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Billing_facade_model
 *
 * Single chokepoint for all billing write operations going forward.
 *
 * Phase 4, Step 2: this class is intentionally a thin orchestrator.
 * It owns the EXACT sequence of side-effects that the legacy
 * `Billing::save_invoice()` and `Billing::update_invoice()`
 * controllers used to inline. No business behavior changes here.
 *
 * Subsequent steps in the SSOT consolidation plan will:
 *   - Step 3: route remaining duplicates through this facade.
 *   - Step 4: consolidate the four payment-recording sites into
 *             `record_payment()` here.
 *   - Step 5: rebuild iop_billing header from billing_transactions
 *             on every invoice save (rejecting on mismatch instead
 *             of post-hoc `sync_invoice_total`).
 *   - Step 7: move NHIS claim total reads to billing_transactions.
 *   - Step 10: enforce invoice immutability (is_locked /
 *              finalized_at) inside `void_invoice` /
 *              `adjust_invoice` / `waive_charge`.
 *
 * Out of scope for Step 2 / present file:
 *   - Anything that would change user-visible behavior.
 *   - The receipt path (still uses cashier_model / billing_model
 *     direct inserts).
 *   - The unified_billing_model::generate_invoice() path.
 *
 * The legacy controller methods now delegate to:
 *   - save_invoice_from_post()
 *   - update_invoice_from_post()
 *
 * Both methods read directly from `$this->input->post(...)` because
 * the underlying `billing_model::saveHeader()` / `saveDetails()`
 * read from `$_POST`. Step 5 will introduce a structured array
 * input and stop relying on the request scope.
 */
class Billing_facade_model extends CI_Model
{
    private $phase2_env_flag_cache = array();

    public function __construct()
    {
        parent::__construct();
        $this->load->model('app/billing_model');
        $this->load->model('app/billing_transaction_model');
    }

    /* ============================================================
     * INVOICE SAVE (NEW)
     * Mirrors the original Billing::save_invoice() side-effect
     * sequence verbatim. Returns metadata for the caller to use
     * for redirects and flash messages.
     * ============================================================ */

    /**
     * @param string|int $user_id
     * @return array {
     *   ok:         bool,
     *   invoice_no: string,
     *   patient_no: string,
     *   iop_no:     string,
     *   payer_type: string,
     *   total:      float|null,
     *   error:      string|null
     * }
     */
    public function save_invoice_from_post($user_id)
    {
        $invNo = (string)$this->input->post('invoiceno');
        $patNo = (string)$this->input->post('patient_no');
        $iopNo = (string)$this->input->post('opd_no');

        $this->db->trans_begin();
        try {
            $this->billing_model->ensure_nhis_billing_columns();
            $payer = $this->billing_model->determine_payer_type($patNo);
            $_POST['payer_type'] = $payer;
            $_POST['created_by']  = (string)$user_id;

            // Header
            $this->billing_model->saveHeader();
            $this->billing_model->install_billing_meta_tables();

            // Lines
            $num = (int)$this->input->post('hdnrowcnt');
            for ($i = 1; $i <= $num; $i++) {
                $this->billing_model->saveDetails($i);
            }

			$enf = $this->_enforce_pharmacy_invoice_integrity($invNo, $user_id, $patNo, $iopNo, $payer, 'CREATE');
			if (!$enf['ok']) {
				$this->db->trans_rollback();
				return array(
					'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
					'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
					'error' => $enf['error'],
				);
			}

            // Invoice series increment
            $this->billing_model->updateInvoiceNo();

            // Step 9 — Apply company pricing sweep for COMPANY-payer invoices.
            // No-op when flag is OFF or when payer is not COMPANY.
            $this->_apply_company_pricing_sweep($invNo, $patNo, $payer, $user_id, $iopNo);

            // Step 5: Header-from-SSOT invariant. Replaces post-hoc sync_invoice_total().
            $check = $this->_verify_header_against_lines($invNo, $user_id, $patNo, $iopNo, 'CREATE');
            if (!$check['ok']) {
                $this->db->trans_rollback();
                return array(
                    'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
                    'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
                    'error' => $check['error'],
                );
            }

            // NHIS audit
            $this->billing_model->log_nhis_audit(
                'INVOICE_CREATED', 'iop_billing', $invNo,
                null,
                json_encode(array(
                    'payer' => $payer,
                    'total' => $check['final_total']
                )),
                $user_id, $patNo, $iopNo
            );

            // Payment status compute (PENDING/PARTIAL/PAID)
            $this->billing_model->update_payment_status($invNo, $user_id);

            // SSOT linkage so receipt distribution works correctly
            if (method_exists($this->billing_transaction_model, 'link_transactions_to_invoice')) {
                $this->billing_transaction_model->link_transactions_to_invoice($invNo, $user_id);
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array(
                    'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
                    'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
                    'error' => 'Database error during invoice save',
                );
            }
            $this->db->trans_commit();
            $this->_run_pharmacy_invoice_diagnostics($invNo, $user_id, $patNo, $iopNo, 'CREATE');

            return array(
                'ok'         => true,
                'invoice_no' => $invNo,
                'patient_no' => $patNo,
                'iop_no'     => $iopNo,
                'payer_type' => $payer,
                'total'      => (float)$check['final_total'],
                'error'      => null,
            );
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return array(
                'ok'         => false,
                'invoice_no' => $invNo,
                'patient_no' => $patNo,
                'iop_no'     => $iopNo,
                'payer_type' => null,
                'total'      => null,
                'error'      => $e->getMessage(),
            );
        }
    }

    /* ============================================================
     * INVOICE UPDATE (REPLACE)
     * Mirrors the original Billing::update_invoice() sequence.
     * Performs non-destructive archival of the previous invoice
     * version then re-saves header + lines.
     * ============================================================ */

    /**
     * @param string|int $user_id
     * @return array same shape as save_invoice_from_post()
     */
    public function update_invoice_from_post($user_id)
    {
        $invNo = (string)$this->input->post('invoiceno');
        $patNo = (string)$this->input->post('patient_no');
        $iopNo = (string)$this->input->post('opd_no');

        // Step 10 — Invoice immutability after first receipt.
        // Audits every attempt; rejects when flag is ON and receipts exist.
        $lock = $this->_check_invoice_editability($invNo, $user_id, $patNo, $iopNo);
        if (!$lock['ok']) {
            return array(
                'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
                'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
                'error' => $lock['error'],
            );
        }

		$rx = $this->_check_verified_rx_invoice_editability($invNo, $user_id, $patNo, $iopNo);
		if (!$rx['ok']) {
			return array(
				'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
				'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
				'error' => $rx['error'],
			);
		}

        $this->db->trans_begin();
        try {
            $this->billing_model->ensure_nhis_billing_columns();
            $payer = $this->billing_model->determine_payer_type($patNo);
            $_POST['payer_type'] = $payer;
            $_POST['updated_by'] = (string)$user_id;

            // Archive existing rows (non-destructive — InActive=1).
            $this->db->where(array('invoice_no' => $invNo, 'InActive' => 0));
            $this->db->update('iop_billing', array('InActive' => 1));
            $this->db->where(array('invoice_no' => $invNo, 'InActive' => 0));
            $this->db->update('iop_billing_t', array('InActive' => 1));

            $this->billing_model->install_billing_meta_tables();
            if ($this->db->table_exists('iop_billing_line_meta')) {
                $this->db->where(array('invoice_no' => $invNo, 'InActive' => 0));
                $this->db->update('iop_billing_line_meta', array('InActive' => 1));
            }
            if ($this->db->table_exists('iop_billable_item_lock')) {
                $this->db->where(array('invoice_no' => $invNo, 'InActive' => 0));
                $this->db->update('iop_billable_item_lock', array('InActive' => 1));
            }
            if ($this->db->table_exists('iop_room_charge')) {
                $this->db->where(array(
                    'invoice_no' => $invNo,
                    'InActive'   => 0,
                    'status'     => 'INVOICED'
                ));
                $this->db->update('iop_room_charge', array(
                    'status'     => 'PENDING',
                    'invoice_no' => null,
                    'detail_id'  => null
                ));
            }

            // Re-save header + lines
            $this->billing_model->saveHeader();
            $num = (int)$this->input->post('hdnrowcnt');
            for ($i = 1; $i <= $num; $i++) {
                $this->billing_model->saveDetails($i);
            }

			$this->_enforce_pharmacy_invoice_integrity($invNo, $user_id, $patNo, $iopNo, $payer, 'UPDATE');

            // Step 9 — Company-pricing sweep (no-op when flag OFF / non-COMPANY payer).
            $this->_apply_company_pricing_sweep($invNo, $patNo, $payer, $user_id, $iopNo);

            // Step 5: Header-from-SSOT invariant.
            $check = $this->_verify_header_against_lines($invNo, $user_id, $patNo, $iopNo, 'UPDATE');
            if (!$check['ok']) {
                $this->db->trans_rollback();
                return array(
                    'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
                    'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
                    'error' => $check['error'],
                );
            }

            $this->billing_model->update_payment_status($invNo, $user_id);

            if (method_exists($this->billing_transaction_model, 'link_transactions_to_invoice')) {
                $this->billing_transaction_model->link_transactions_to_invoice($invNo, $user_id);
            }

            $this->billing_model->log_nhis_audit(
                'INVOICE_UPDATED', 'iop_billing', $invNo,
                null,
                json_encode(array(
                    'payer' => $payer,
                    'total' => $check['final_total']
                )),
                $user_id, $patNo, $iopNo
            );

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return array(
                    'ok' => false, 'invoice_no' => $invNo, 'patient_no' => $patNo,
                    'iop_no' => $iopNo, 'payer_type' => null, 'total' => null,
                    'error' => 'Database error during invoice update',
                );
            }
            $this->db->trans_commit();
            $this->_run_pharmacy_invoice_diagnostics($invNo, $user_id, $patNo, $iopNo, 'UPDATE');

            return array(
                'ok'         => true,
                'invoice_no' => $invNo,
                'patient_no' => $patNo,
                'iop_no'     => $iopNo,
                'payer_type' => $payer,
                'total'      => (float)$check['final_total'],
                'error'      => null,
            );
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return array(
                'ok'         => false,
                'invoice_no' => $invNo,
                'patient_no' => $patNo,
                'iop_no'     => $iopNo,
                'payer_type' => null,
                'total'      => null,
                'error'      => $e->getMessage(),
            );
        }
    }

    /* ============================================================
     * STEP 5 — HEADER/LINE INVARIANT
     *
     * Compares the submitted header total to SUM(iop_billing_t.amount)
     * for the saved lines. Always audits to billing_audit_log
     * (HEADER_LINE_CHECK on match, HEADER_LINE_MISMATCH on mismatch).
     *
     * Behavior on mismatch is gated by `BILLING_FACADE_STRICT_HEADER`
     * (config flag, default FALSE):
     *   FALSE → silently correct (today's behavior via sync_invoice_total).
     *   TRUE  → caller must roll back; we return ok=false.
     *
     * Returns: array{ ok: bool, error: string|null,
     *   submitted: float, computed: float, diff: float, final_total: float,
     *   strict: bool, mismatch: bool }
     * ============================================================ */
    private function _verify_header_against_lines($invoice_no, $user_id, $patient_no, $iop_no, $action)
    {
        $strict = $this->is_strict_header_enabled();

        // Compute SSOT line sum.
        $this->db->select('COALESCE(SUM(amount),0) AS line_total');
        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $row = $this->db->get('iop_billing_t')->row();
        $line_total = $row ? round((float)$row->line_total, 2) : 0.0;

        // Submitted header total (from $_POST, what the caller intended).
        $submitted = round((float)$this->input->post('total_amount'), 2);

        $diff = round($submitted - $line_total, 2);
        $mismatch = abs($diff) > 0.01;
        $final_total = $line_total; // SSOT wins.

        if ($mismatch) {
            // Always overwrite header to SSOT value (idempotent w/ today's behavior).
            $this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
            $this->db->update('iop_billing', array('total_amount' => $line_total));

            // Audit the mismatch with full context.
            $payload = json_encode(array(
                'action'        => $action,
                'submitted'     => $submitted,
                'computed'      => $line_total,
                'diff'          => $diff,
                'strict_mode'   => $strict,
                'corrected'     => !$strict,
                'rejected'      => $strict,
            ));
            $this->billing_model->log_nhis_audit(
                'HEADER_LINE_MISMATCH', 'iop_billing', $invoice_no,
                $submitted, $line_total, $user_id, $patient_no, $iop_no
            );
            $this->billing_model->log_nhis_audit(
                'HEADER_LINE_MISMATCH_DETAIL', 'iop_billing', $invoice_no,
                null, $payload, $user_id, $patient_no, $iop_no
            );

            if ($strict) {
                return array(
                    'ok'          => false,
                    'error'       => sprintf(
                        'Invoice header total (GHS %s) does not match line items sum (GHS %s). '
                        . 'Diff: GHS %s. Strict mode is enabled — invoice rejected.',
                        number_format($submitted, 2),
                        number_format($line_total, 2),
                        number_format($diff, 2)
                    ),
                    'submitted'   => $submitted,
                    'computed'    => $line_total,
                    'diff'        => $diff,
                    'final_total' => $line_total,
                    'strict'      => true,
                    'mismatch'    => true,
                );
            }
        } else {
            // Match — lightweight audit so we can quantify good runs.
            $this->billing_model->log_nhis_audit(
                'HEADER_LINE_CHECK', 'iop_billing', $invoice_no,
                null, json_encode(array('action' => $action, 'total' => $line_total)),
                $user_id, $patient_no, $iop_no
            );
        }

        return array(
            'ok'          => true,
            'error'       => null,
            'submitted'   => $submitted,
            'computed'    => $line_total,
            'diff'        => $diff,
            'final_total' => $line_total,
            'strict'      => $strict,
            'mismatch'    => $mismatch,
        );
    }

    /**
     * Strict header-invariant flag. When TRUE, any header/line
     * mismatch causes the save to be rejected. Default FALSE.
     */
    public function is_strict_header_enabled()
    {
        $cfg = $this->config->item('BILLING_FACADE_STRICT_HEADER');
        if ($cfg === null) {
            $env = getenv('BILLING_FACADE_STRICT_HEADER');
            if ($env !== false) {
                $cfg = filter_var($env, FILTER_VALIDATE_BOOLEAN);
            }
        }
        return (bool)$cfg;
    }

    /* ============================================================
     * RESERVED SLOTS — implemented in later steps. Stubs documented
     * so consumers know the canonical names.
     * ============================================================ */

    /* ============================================================
     * RECORD PAYMENT (Step 4)
     * Canonical receipt-recording sequence. The union of all
     * side-effects performed today by:
     *   - cashier_model::process_payment()
     *   - billing_model (legacy receipt insert path)
     *   - unified_billing_model::process_payment()
     *   - controllers/app/pos.php::save_payment()
     *
     * Activated per-site by the feature flag
     *   $config['BILLING_FACADE_RECEIPT_ROUTE'] (default FALSE)
     * Each legacy site checks `is_receipt_route_enabled()` and
     * delegates only when ON. This lets operators flip the route
     * in `application/config/config.php` without code changes,
     * and roll back instantly.
     * ============================================================ */

    /**
     * @param array $data {
     *   invoice_no:     string  (required)
     *   amount:         float   (required, > 0)
     *   payment_method: string  (default 'CASH')
     *   reference:      string|null
     *   notes:          string|null
     *   cashier_id:     int|string|null  (defaults to current session user_id)
     *   receipt_no:     string|null  (auto-generated if absent)
     *   source:         string  caller tag for audit (e.g. 'CASHIER', 'POS', 'BILLING', 'UNIFIED')
     *   total_purchased:int|null  (POS leg)
     *   discount:       float|null
     *   subtotal:       float|null
     *   change:         float|null
     * }
     * @return array {
     *   ok:             bool,
     *   receipt_no:     string|null,
     *   balance_before: float,
     *   balance_after:  float,
     *   payment_status: string,
     *   ssot_sync:      mixed,
     *   error:          string|null
     * }
     */
    public function record_payment(array $data)
    {
        $invoice_no     = isset($data['invoice_no'])     ? (string)$data['invoice_no']     : '';
        $amount         = isset($data['amount'])         ? (float)$data['amount']          : 0.0;
        $payment_method = isset($data['payment_method']) ? strtoupper((string)$data['payment_method']) : 'CASH';
        $reference      = isset($data['reference'])      ? (string)$data['reference']      : null;
        $notes          = isset($data['notes'])          ? (string)$data['notes']          : null;
        $cashier_id     = isset($data['cashier_id'])     ? $data['cashier_id']             : $this->session->userdata('user_id');
        $receipt_no     = isset($data['receipt_no']) && trim((string)$data['receipt_no']) !== ''
                          ? (string)$data['receipt_no'] : null;
        $source         = isset($data['source'])         ? strtoupper((string)$data['source']) : 'FACADE';

        if ($invoice_no === '') {
            return $this->_pay_err('invoice_no is required', $invoice_no);
        }
        if ($amount <= 0) {
            return $this->_pay_err('Payment amount must be greater than 0', $invoice_no);
        }

        // Load invoice header
        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $invoice = $this->db->get('iop_billing')->row();
        if (!$invoice) {
            return $this->_pay_err('Invoice not found: ' . $invoice_no, $invoice_no);
        }

        // Compute balance from iop_receipt SUM (race-safe)
        $inv_disc = 0.0;
        if (isset($invoice->discount)) {
            $inv_disc = (float)$invoice->discount;
        } elseif (isset($invoice->discount_amount)) {
            $inv_disc = (float)$invoice->discount_amount;
        } elseif (isset($invoice->discountAmount)) {
            $inv_disc = (float)$invoice->discountAmount;
        }
        $invoice_total = (float)$invoice->total_amount - (float)$inv_disc;
        $this->db->select('COALESCE(SUM(amountPaid),0) AS total_paid');
        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $paid_row    = $this->db->get('iop_receipt')->row();
        $total_paid  = $paid_row ? (float)$paid_row->total_paid : 0.0;
        $balance     = round($invoice_total - $total_paid, 2);

        if ($balance <= 0.0) {
            return $this->_pay_err('Invoice is already fully paid', $invoice_no);
        }
        if ($amount > $balance + 0.005) {
            return $this->_pay_err(
                'Payment amount (GHS ' . number_format($amount, 2) . ') exceeds balance of GHS ' . number_format($balance, 2),
                $invoice_no
            );
        }

        // Capability detection (idempotent helpers)
        $this->billing_model->ensure_billing_enhancements();
        $hasBalanceDue   = $this->billing_model->column_exists('iop_billing', 'balance_due');
        $hasPaymentStat  = $this->billing_model->column_exists('iop_billing', 'payment_status');
        $hasCashierIdRcp = $this->billing_model->column_exists('iop_receipt', 'cashier_id');
        $hasNotesRcp     = $this->billing_model->column_exists('iop_receipt', 'notes');
        $hasMethodRcp    = $this->billing_model->column_exists('iop_receipt', 'payment_method');
        $hasReceiptOnInv = $this->billing_model->column_exists('iop_billing', 'receipt_no');
		$hasSubjectTypeInv = $this->billing_model->column_exists('iop_billing', 'billing_subject_type');
		$hasSubjectIdInv   = $this->billing_model->column_exists('iop_billing', 'billing_subject_id');
		$hasSubjectTypeRcp = $this->billing_model->column_exists('iop_receipt', 'billing_subject_type');
		$hasSubjectIdRcp   = $this->billing_model->column_exists('iop_receipt', 'billing_subject_id');

		$bst = isset($data['billing_subject_type']) ? trim((string)$data['billing_subject_type']) : '';
		$bsid = isset($data['billing_subject_id']) ? trim((string)$data['billing_subject_id']) : '';
		if ($bst === '' && $hasSubjectTypeInv && isset($invoice->billing_subject_type)) {
			$bst = trim((string)$invoice->billing_subject_type);
		}
		if ($bsid === '' && $hasSubjectIdInv && isset($invoice->billing_subject_id)) {
			$bsid = trim((string)$invoice->billing_subject_id);
		}

        $this->db->trans_begin();
        try {
            // Receipt number — caller-provided or auto-generated.
            if ($receipt_no === null) {
                $receipt_no = $this->_generate_receipt_no();
            }

            // Collision check (defense-in-depth)
            $this->db->where('receipt_no', $receipt_no);
            if ($this->db->count_all_results('iop_receipt') > 0) {
                $this->db->trans_rollback();
                return $this->_pay_err('Receipt number collision: ' . $receipt_no, $invoice_no);
            }

            // Build receipt row (legacy convention: total_amount = invoice total, amountPaid = this payment)
            $receiptData = array(
                'receipt_no'        => $receipt_no,
                'invoice_no'        => $invoice_no,
                'dDate'             => date('Y-m-d H:i:s'),
                'iop_id'            => isset($invoice->iop_id) ? $invoice->iop_id : null,
                'patient_no'        => isset($invoice->patient_no) ? $invoice->patient_no : null,
                'payment_type'      => $payment_method,
                'total_amount'      => round($invoice_total, 2),
                'change'            => isset($data['change']) ? round((float)$data['change'], 2) : 0,
                'amountPaid'        => round($amount, 2),
                'total_purchased'   => isset($data['total_purchased']) ? $data['total_purchased'] : (isset($invoice->total_purchased) ? $invoice->total_purchased : 0),
                'discount'          => isset($data['discount']) ? $data['discount'] : (float)$inv_disc,
                'subtotal'          => isset($data['subtotal']) ? $data['subtotal'] : (isset($invoice->sub_total) ? $invoice->sub_total : 0),
                'creditCardNo'      => '',
                'creditCardHolder'  => '',
                'insurance_company' => '',
                'remarks'           => '',
                'InActive'          => 0,
            );
            if ($hasMethodRcp)    { $receiptData['payment_method'] = $payment_method; }
            if ($hasCashierIdRcp && $cashier_id !== null) { $receiptData['cashier_id'] = (string)$cashier_id; }
            if ($hasNotesRcp && $notes !== null)          { $receiptData['notes']      = (string)$notes; }
			if ($hasSubjectTypeRcp && $bst !== '')            { $receiptData['billing_subject_type'] = $bst; }
			if ($hasSubjectIdRcp && $bsid !== '')             { $receiptData['billing_subject_id']   = $bsid; }

            $this->db->insert('iop_receipt', $receiptData);

            // Update header balance/status
            $new_balance = round(max(0, $balance - $amount), 2);
            $new_status  = ($new_balance <= 0.01) ? 'PAID' : 'PARTIAL';
            $upd = array();
            if ($hasBalanceDue)  { $upd['balance_due']    = $new_balance; }
            if ($hasPaymentStat) { $upd['payment_status'] = $new_status; }
            if ($hasReceiptOnInv) { $upd['receipt_no']    = $receipt_no; }
			if ($hasSubjectTypeInv && $bst !== '' && (!isset($invoice->billing_subject_type) || trim((string)$invoice->billing_subject_type) === '')) {
				$upd['billing_subject_type'] = $bst;
			}
			if ($hasSubjectIdInv && $bsid !== '' && (!isset($invoice->billing_subject_id) || trim((string)$invoice->billing_subject_id) === '')) {
				$upd['billing_subject_id'] = $bsid;
			}
            if (!empty($upd)) {
                $this->db->where('invoice_no', $invoice_no);
                $this->db->update('iop_billing', $upd);
            }

            // cashier_payment_log (audit)
            if ($this->db->table_exists('cashier_payment_log')) {
                $this->db->insert('cashier_payment_log', array(
                    'receipt_no'     => $receipt_no,
                    'invoice_no'     => $invoice_no,
                    'patient_no'     => isset($invoice->patient_no) ? $invoice->patient_no : null,
                    'amount'         => round($amount, 2),
                    'payment_method' => $payment_method,
                    'reference_no'   => $reference,
                    'notes'          => $notes !== null ? $notes : ('Source: ' . $source),
                    'cashier_id'     => $cashier_id,
                    'payment_date'   => date('Y-m-d H:i:s'),
                ));
            }

            // Financial audit trail (cashier_model parity)
            if (method_exists($this->cashier_model_optional(), 'log_financial_audit')) {
                $this->cashier_model->log_financial_audit(
                    'PAYMENT', $receipt_no, $invoice_no,
                    isset($invoice->patient_no) ? $invoice->patient_no : null,
                    $amount, $balance, $new_balance,
                    'Payment received via ' . $payment_method . ($reference ? ' (Ref: ' . $reference . ')' : '') . ' [src=' . $source . ']',
                    $cashier_id
                );
            }

            // Double-entry ledger (unified_billing_model)
            $CI =& get_instance();
            if (!isset($CI->unified_billing_model)) {
                $this->load->model('app/unified_billing_model');
            }
            if (isset($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'record_payment_to_ledger')) {
                $CI->unified_billing_model->record_payment_to_ledger(
                    $receipt_no, $invoice_no,
                    isset($invoice->patient_no) ? $invoice->patient_no : null,
                    $amount, $payment_method, $cashier_id
                );
            }

            // SSOT distribution into billing_transactions
            $ssot_sync = null;
            if (method_exists($this->billing_transaction_model, 'sync_receipt_payment')) {
                $ssot_sync = $this->billing_transaction_model->sync_receipt_payment($receipt_no, $cashier_id);
            }

            // 🔁 SSOT sync for Sonography (immediate post-payment)
            $receipt_trace_id = uniqid('receipt_sync_', true);
            log_message('info', 'Receipt SSOT sync triggered: ' . json_encode(array(
                'trace_id' => $receipt_trace_id,
                'invoice_no' => $invoice_no,
                'receipt_no' => $receipt_no,
            )));
            try {
                if ($invoice_no !== ''
                    && $this->db->table_exists('iop_sonography_charge'))
                {
                    $this->load->model('app/laboratory_model');
                    if (isset($this->laboratory_model)
                        && method_exists($this->laboratory_model, 'sync_order_master_from_sonography_charge'))
                    {
                        $charges = $this->db
                            ->select('charge_id')
                            ->where('invoice_no', $invoice_no)
                            ->where('InActive', 0)
                            ->get('iop_sonography_charge')
                            ->result_array();

                        $charge_ids = array_column($charges, 'charge_id');
                        foreach ($charge_ids as $charge_id) {
                            $charge_id = (int)$charge_id;
                            if ($charge_id > 0) {
                                $this->laboratory_model->sync_order_master_from_sonography_charge($charge_id, $cashier_id, null, false, $receipt_trace_id);
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                log_message('error', 'Sonography SSOT sync failed: ' . $e->getMessage() . ' trace_id=' . $receipt_trace_id . ' invoice_no=' . $invoice_no . ' receipt_no=' . $receipt_no);
            }

            // Auto-release service gates when fully paid
            if ($new_status === 'PAID'
                && isset($CI->unified_billing_model)
                && method_exists($CI->unified_billing_model, 'auto_release_gates_for_invoice'))
            {
                $CI->unified_billing_model->auto_release_gates_for_invoice($invoice_no);
            }

            if ($this->db->trans_status() === FALSE) {
                $this->db->trans_rollback();
                return $this->_pay_err('Database error during payment recording', $invoice_no);
            }
            $this->db->trans_commit();

            return array(
                'ok'             => true,
                'receipt_no'     => $receipt_no,
                'balance_before' => $balance,
                'balance_after'  => $new_balance,
                'payment_status' => $new_status,
                'ssot_sync'      => $ssot_sync,
                'error'          => null,
            );
        } catch (Exception $e) {
            $this->db->trans_rollback();
            return $this->_pay_err($e->getMessage(), $invoice_no);
        }
    }

    /**
     * Feature-flag check. Reads $config['BILLING_FACADE_RECEIPT_ROUTE'].
     * Default FALSE so Step 4 ships dark and behavior is unchanged
     * until the operator flips the flag.
     */
    public function is_receipt_route_enabled()
    {
        $cfg = $this->config->item('BILLING_FACADE_RECEIPT_ROUTE');
        if ($cfg === null) {
            // Allow env override for ops who can't edit config.
            $env = getenv('BILLING_FACADE_RECEIPT_ROUTE');
            if ($env !== false) {
                $cfg = filter_var($env, FILTER_VALIDATE_BOOLEAN);
            }
        }
        return (bool)$cfg;
    }

    private function _generate_receipt_no()
    {
		$tries = 0;
		while ($tries < 5) {
			$tries++;
			$rnVal = null;
			if ($this->db->table_exists('system_option')) {
				$q = $this->db->query("SELECT (cValue + 1) AS receipt_no FROM system_option WHERE cCode = 'receipt_no' LIMIT 1");
				$row = $q ? $q->row() : null;
				if ($row && isset($row->receipt_no)) {
					$rnVal = (int)$row->receipt_no;
				}
			}
			if ($rnVal === null || $rnVal <= 0) {
				return 'RCP' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
			}
			$receipt_no = 'OR-' . str_pad((string)$rnVal, 6, '0', STR_PAD_LEFT);
			$this->db->where('receipt_no', $receipt_no);
			if ($this->db->count_all_results('iop_receipt') > 0) {
				$this->db->query("UPDATE system_option SET cValue = ? WHERE cCode = 'receipt_no'", array($rnVal));
				continue;
			}
			$this->db->query("UPDATE system_option SET cValue = ? WHERE cCode = 'receipt_no'", array($rnVal));
			return $receipt_no;
		}
		return 'RCP' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    /** Lazy-load cashier_model only when needed. Returns instance or null. */
    private function cashier_model_optional()
    {
        $CI =& get_instance();
        if (!isset($CI->cashier_model)) {
            // Don't fail on absence; some controllers may not load it.
            try { $this->load->model('app/cashier_model'); } catch (Exception $e) { return null; }
        }
        return isset($CI->cashier_model) ? $CI->cashier_model : null;
    }

    private function _pay_err($message, $invoice_no)
    {
        return array(
            'ok'             => false,
            'receipt_no'     => null,
            'balance_before' => 0.0,
            'balance_after'  => 0.0,
            'payment_status' => null,
            'ssot_sync'      => null,
            'error'          => $message,
        );
    }

    /* ============================================================
     * STEP 7 — NHIS CLAIM TOTAL FROM SSOT
     *
     * Computes the authoritative NHIS-claimable total for an
     * encounter directly from `billing_transactions` (the SSOT).
     * Used to cross-check claim header totals produced by
     * `nhis_model::generate_claim()` so we never submit a claim
     * with a total that disagrees with the invoice.
     *
     * Returns array {
     *   ok:            bool,
     *   has_ssot_data: bool   (false if no billing_transactions rows for encounter),
     *   nhis_total:    float,
     *   patient_total: float,
     *   gross_total:   float,
     *   item_count:    int
     * }
     * ============================================================ */
    public function compute_nhis_claim_totals_from_ssot($encounter_id)
    {
        $encounter_id = (string)$encounter_id;
        if ($encounter_id === '' || !$this->db->table_exists('billing_transactions')) {
            return array('ok' => false, 'has_ssot_data' => false, 'source' => null,
                         'nhis_total' => 0.0, 'patient_total' => 0.0,
                         'gross_total' => 0.0, 'item_count' => 0);
        }

        // Be permissive about column names — schema has evolved.
        $col_nhis    = $this->db->field_exists('nhis_covered_amount', 'billing_transactions')
                       ? 'nhis_covered_amount'
                       : ($this->db->field_exists('nhis_amount', 'billing_transactions') ? 'nhis_amount' : null);
        $col_patient = $this->db->field_exists('patient_amount', 'billing_transactions')
                       ? 'patient_amount'
                       : ($this->db->field_exists('patient_payable_amount', 'billing_transactions') ? 'patient_payable_amount' : null);
        $col_gross   = $this->db->field_exists('net_amount', 'billing_transactions')
                       ? 'net_amount'
                       : ($this->db->field_exists('total_amount', 'billing_transactions') ? 'total_amount' : null);

        if (!$col_nhis || !$col_patient || !$col_gross) {
            return array('ok' => false, 'has_ssot_data' => false, 'source' => null,
                         'nhis_total' => 0.0, 'patient_total' => 0.0,
                         'gross_total' => 0.0, 'item_count' => 0);
        }

        $sql = "SELECT COUNT(*) AS n,
                       COALESCE(SUM(`{$col_nhis}`),0)    AS nhis_total,
                       COALESCE(SUM(`{$col_patient}`),0) AS patient_total,
                       COALESCE(SUM(`{$col_gross}`),0)   AS gross_total
                FROM billing_transactions
                WHERE encounter_id = ? AND InActive = 0";
        $r = $this->db->query($sql, array($encounter_id))->row();
        $count = $r ? (int)$r->n : 0;

        if ($count > 0) {
            return array(
                'ok'            => true,
                'has_ssot_data' => true,
                'source'        => 'billing_transactions',
                'nhis_total'    => round((float)$r->nhis_total,    2),
                'patient_total' => round((float)$r->patient_total, 2),
                'gross_total'   => round((float)$r->gross_total,   2),
                'item_count'    => $count,
            );
        }

        // ----- Fallback: derive from iop_billing for this encounter.
        // Used when billing_transactions hasn't been populated for this
        // visit (legacy data, or modules whose sync_* hooks weren't yet
        // wired). The invoice header columns are the next-best SSOT
        // because Step 5's verifier guarantees iop_billing.total_amount
        // == SUM(iop_billing_t.amount).
        if ($this->db->table_exists('iop_billing')
            && $this->db->field_exists('nhis_covered_amount', 'iop_billing')
            && $this->db->field_exists('patient_payable_amount', 'iop_billing')) {
            $sql2 = "SELECT COUNT(*) AS n,
                            COALESCE(SUM(nhis_covered_amount),    0) AS nhis_total,
                            COALESCE(SUM(patient_payable_amount), 0) AS patient_total,
                            COALESCE(SUM(total_amount),           0) AS gross_total
                     FROM iop_billing
                     WHERE iop_id = ? AND InActive = 0";
            $r2 = $this->db->query($sql2, array($encounter_id))->row();
            $count2 = $r2 ? (int)$r2->n : 0;
            if ($count2 > 0 && (float)$r2->gross_total > 0) {
                return array(
                    'ok'            => true,
                    'has_ssot_data' => true,
                    'source'        => 'iop_billing_fallback',
                    'nhis_total'    => round((float)$r2->nhis_total,    2),
                    'patient_total' => round((float)$r2->patient_total, 2),
                    'gross_total'   => round((float)$r2->gross_total,   2),
                    'item_count'    => $count2,
                );
            }
        }

        return array(
            'ok'            => true,
            'has_ssot_data' => false,
            'source'        => null,
            'nhis_total'    => 0.0,
            'patient_total' => 0.0,
            'gross_total'   => 0.0,
            'item_count'    => 0,
        );
    }

    /**
     * Step 7 verifier — call from `nhis_model::generate_claim()`
     * after totals are computed. Audits HEADER vs SSOT difference
     * and (in strict mode) returns an error so the caller can
     * roll back / refuse to submit the claim.
     *
     * @param string $encounter_id
     * @param float  $claim_nhis_total      what generate_claim wants to put in nhis_claims.total_amount
     * @param float  $claim_patient_total   what it wants to put in nhis_claims.patient_amount
     * @param int|string $user_id
     * @param string $patient_no
     * @return array { ok: bool, action: 'pass'|'corrected'|'rejected'|'no_ssot',
     *                ssot: array|null, claim: array, diff_nhis: float, diff_patient: float,
     *                strict: bool, error: string|null }
     */
    public function verify_nhis_claim_against_ssot($encounter_id, $claim_nhis_total, $claim_patient_total, $user_id = null, $patient_no = null)
    {
        $strict = $this->is_nhis_claim_ssot_enabled();
        $ssot   = $this->compute_nhis_claim_totals_from_ssot($encounter_id);

        if (!$ssot['ok'] || !$ssot['has_ssot_data']) {
            // No SSOT data anywhere — emit observable audit so operators see
            // which encounters fall outside the invariant's coverage.
            $this->billing_model->log_nhis_audit(
                'NHIS_CLAIM_NO_SSOT', 'nhis_claims', $encounter_id,
                null, json_encode(array(
                    'encounter_id'  => $encounter_id,
                    'claim_nhis'    => round((float)$claim_nhis_total, 2),
                    'claim_patient' => round((float)$claim_patient_total, 2),
                    'reason'        => 'no_billing_transactions_and_no_iop_billing',
                )),
                $user_id, $patient_no, $encounter_id
            );
            return array('ok' => true, 'action' => 'no_ssot', 'ssot' => null,
                         'claim' => array('nhis' => $claim_nhis_total, 'patient' => $claim_patient_total),
                         'diff_nhis' => 0.0, 'diff_patient' => 0.0,
                         'strict' => $strict, 'error' => null);
        }

        $diff_nhis    = round((float)$claim_nhis_total    - $ssot['nhis_total'],    2);
        $diff_patient = round((float)$claim_patient_total - $ssot['patient_total'], 2);
        $mismatch     = (abs($diff_nhis) > 0.01) || (abs($diff_patient) > 0.01);

        if ($mismatch) {
            $payload = json_encode(array(
                'encounter_id'   => $encounter_id,
                'claim_nhis'     => round((float)$claim_nhis_total, 2),
                'claim_patient'  => round((float)$claim_patient_total, 2),
                'ssot_nhis'      => $ssot['nhis_total'],
                'ssot_patient'   => $ssot['patient_total'],
                'ssot_gross'     => $ssot['gross_total'],
                'diff_nhis'      => $diff_nhis,
                'diff_patient'   => $diff_patient,
                'item_count'     => $ssot['item_count'],
                'strict_mode'    => $strict,
            ));
            $this->billing_model->log_nhis_audit(
                'NHIS_CLAIM_TOTAL_MISMATCH', 'nhis_claims', $encounter_id,
                round((float)$claim_nhis_total, 2), $ssot['nhis_total'],
                $user_id, $patient_no, $encounter_id
            );
            $this->billing_model->log_nhis_audit(
                'NHIS_CLAIM_TOTAL_MISMATCH_DETAIL', 'nhis_claims', $encounter_id,
                null, $payload, $user_id, $patient_no, $encounter_id
            );

            if ($strict) {
                return array('ok' => false, 'action' => 'rejected', 'ssot' => $ssot,
                             'claim' => array('nhis' => $claim_nhis_total, 'patient' => $claim_patient_total),
                             'diff_nhis' => $diff_nhis, 'diff_patient' => $diff_patient,
                             'strict' => true,
                             'error' => sprintf(
                                 'NHIS claim total disagrees with SSOT for encounter %s. '
                                 . 'Claim NHIS=GHS %s, SSOT NHIS=GHS %s (diff %s). '
                                 . 'Strict mode is enabled — claim rejected.',
                                 $encounter_id,
                                 number_format((float)$claim_nhis_total, 2),
                                 number_format($ssot['nhis_total'], 2),
                                 number_format($diff_nhis, 2)
                             ));
            }
            return array('ok' => true, 'action' => 'corrected', 'ssot' => $ssot,
                         'claim' => array('nhis' => $claim_nhis_total, 'patient' => $claim_patient_total),
                         'diff_nhis' => $diff_nhis, 'diff_patient' => $diff_patient,
                         'strict' => false, 'error' => null);
        }

        // Match — light audit so we can quantify good runs.
        $this->billing_model->log_nhis_audit(
            'NHIS_CLAIM_TOTAL_CHECK', 'nhis_claims', $encounter_id,
            null, json_encode(array('nhis' => $ssot['nhis_total'], 'patient' => $ssot['patient_total'])),
            $user_id, $patient_no, $encounter_id
        );
        return array('ok' => true, 'action' => 'pass', 'ssot' => $ssot,
                     'claim' => array('nhis' => $claim_nhis_total, 'patient' => $claim_patient_total),
                     'diff_nhis' => 0.0, 'diff_patient' => 0.0,
                     'strict' => $strict, 'error' => null);
    }

    /* ============================================================
     * STEP 8 — INVOICE FROM QUEUE (consolidated wrapper)
     *
     * Thin wrapper around `unified_billing_model::generate_invoice()`
     * that adds:
     *   - Header/line invariant verification (Step 5 reuse).
     *   - Optional company-pricing sweep (Step 9 reuse).
     *   - Single audit row tagged INVOICE_GENERATED_FROM_QUEUE.
     *
     * Activated by feature flag (default OFF). When OFF, callers
     * still call unified_billing_model directly and behavior is
     * unchanged.
     * ============================================================ */
    public function generate_invoice_from_queue($iop_id, $patient_no, $queue_ids = null, $user_id = null)
    {
        $this->load->model('app/unified_billing_model');
        if ($this->db->table_exists('billing_queue')) {
            $this->db->where('iop_id', (string)$iop_id);
            $this->db->where('patient_no', (string)$patient_no);
            $this->db->where('item_type', 'PHARMACY');
            $this->db->where('InActive', 0);
            $this->db->where('unit_price <=', 0.009);
            if ($queue_ids && is_array($queue_ids)) {
                $this->db->where_in('queue_id', $queue_ids);
            } else {
                $this->db->where('status', 'PENDING');
            }
            $zero = $this->db->count_all_results('billing_queue');
            if ((int)$zero > 0) {
                return array('success' => false, 'error' => 'Pharmacy pricing resolution failed. Workflow blocked to prevent zero-value billing.');
            }
        }
        $res = $this->unified_billing_model->generate_invoice($iop_id, $patient_no, $queue_ids, $user_id);
        if (empty($res['success'])) {
            return $res;
        }

        $invNo = isset($res['invoice_no']) ? (string)$res['invoice_no'] : '';
        $payer = $this->billing_model->determine_payer_type((string)$patient_no);

        // Step 9 sweep (no-op unless COMPANY + flag).
        $this->_apply_company_pricing_sweep($invNo, $patient_no, $payer, $user_id, $iop_id);

        // Step 5-style verification — recompute header from lines and audit.
        // Note: we don't have a "submitted" total here (caller passed nothing),
        // so verifier compares persisted header to lines and corrects/rejects.
        $verify = $this->_verify_persisted_header($invNo, $user_id, $patient_no, $iop_id, 'GENERATE_FROM_QUEUE');
        if (!$verify['ok']) {
            return array('success' => false, 'invoice_no' => $invNo,
                         'error' => $verify['error']);
        }

        $this->billing_model->log_nhis_audit(
            'INVOICE_GENERATED_FROM_QUEUE', 'iop_billing', $invNo,
            null, json_encode(array(
                'iop_id'      => $iop_id,
                'patient_no'  => $patient_no,
                'queue_ids'   => $queue_ids,
                'final_total' => $verify['final_total'],
                'payer'       => $payer,
            )),
            $user_id, $patient_no, $iop_id
        );

        $res['total']       = $verify['final_total'];
        $res['payer_type']  = $payer;
        return $res;
    }

    /**
     * Variant of the Step 5 verifier used when there is no submitted
     * $_POST total (e.g. queue-driven generate_invoice). Compares
     * persisted iop_billing.total_amount to SUM(iop_billing_t.amount)
     * and corrects / rejects identically.
     */
    private function _verify_persisted_header($invoice_no, $user_id, $patient_no, $iop_no, $action)
    {
        $strict = $this->is_strict_header_enabled();

        $this->db->select('COALESCE(SUM(amount),0) AS line_total');
        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $row = $this->db->get('iop_billing_t')->row();
        $line_total = $row ? round((float)$row->line_total, 2) : 0.0;

        $this->db->select('total_amount');
        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $hdr = $this->db->get('iop_billing')->row();
        $hdr_total = $hdr ? round((float)$hdr->total_amount, 2) : 0.0;

        $diff = round($hdr_total - $line_total, 2);
        $mismatch = abs($diff) > 0.01;

        if ($mismatch) {
            $this->db->where(array('invoice_no' => $invoice_no, 'InActive' => 0));
            $this->db->update('iop_billing', array('total_amount' => $line_total));

            $this->billing_model->log_nhis_audit(
                'HEADER_LINE_MISMATCH', 'iop_billing', $invoice_no,
                $hdr_total, $line_total, $user_id, $patient_no, $iop_no
            );
            $this->billing_model->log_nhis_audit(
                'HEADER_LINE_MISMATCH_DETAIL', 'iop_billing', $invoice_no,
                null, json_encode(array(
                    'action' => $action, 'persisted_header' => $hdr_total,
                    'computed' => $line_total, 'diff' => $diff,
                    'strict_mode' => $strict, 'corrected' => !$strict, 'rejected' => $strict,
                )),
                $user_id, $patient_no, $iop_no
            );

            if ($strict) {
                return array('ok' => false, 'final_total' => $line_total,
                             'error' => sprintf(
                                 'Generated invoice %s header (GHS %s) disagrees with line items (GHS %s).',
                                 $invoice_no, number_format($hdr_total, 2), number_format($line_total, 2)
                             ));
            }
        } else {
            $this->billing_model->log_nhis_audit(
                'HEADER_LINE_CHECK', 'iop_billing', $invoice_no,
                null, json_encode(array('action' => $action, 'total' => $line_total)),
                $user_id, $patient_no, $iop_no
            );
        }
        return array('ok' => true, 'final_total' => $line_total, 'error' => null);
    }

    /* ============================================================
     * STEP 9 — COMPANY PRICING SWEEP
     *
     * For COMPANY-payer invoices, recomputes each iop_billing_t
     * line through Price_engine_model::resolve() so the
     * `pricing_percentage` configured on the company applies to
     * every item type — not just pharmacy.
     *
     * Default behavior: NO-OP. Activate with
     * $config['BILLING_FACADE_COMPANY_PRICING_SWEEP'] = TRUE;
     *
     * Strategy:
     *   - Lookup each line's source by `bill_name` against
     *     `bill_particular` (services / lab / imaging / IPD) and
     *     `medicine_drug_name` (drugs).
     *   - Call Price_engine::resolve() with payer_type COMPANY.
     *   - If the engine produces a different unit price, update
     *     the line and log COMPANY_PRICING_ADJUSTMENT in the audit.
     *
     * Lines whose source cannot be resolved (e.g. ad-hoc charges)
     * are left untouched — engine resolve only INCREASES correctness.
     * ============================================================ */
    private function _apply_company_pricing_sweep($invoice_no, $patient_no, $payer_type, $user_id, $iop_no)
    {
        if (!$this->is_company_pricing_sweep_enabled()) { return; }
        if (strtoupper((string)$payer_type) !== 'COMPANY') { return; }
        if (trim((string)$invoice_no) === '') { return; }

        $this->load->model('app/Price_engine_model', 'price_engine_model');

        // ----- Build a name -> item_type hint map from the SSOT.
        // billing_transactions.item_type is one of DRUG / LAB / SERVICE / IMAGING / ROOM /
        // CONSULTATION. We collapse non-DRUG types to SERVICE for Price_engine purposes,
        // but keep the hint to disambiguate drug-vs-service name collisions.
        $hint = array();   // lowercased bill_name => 'DRUG' | 'SERVICE'
        if ($this->db->table_exists('billing_transactions')) {
            $sql = "SELECT item_name, item_type FROM billing_transactions
                    WHERE invoice_no = ? AND InActive = 0";
            $bt_rows = $this->db->query($sql, array((string)$invoice_no))->result();
            foreach ($bt_rows as $bt) {
                $key = strtolower(trim((string)$bt->item_name));
                if ($key === '') { continue; }
                $type = strtoupper((string)$bt->item_type);
                $hint[$key] = ($type === 'DRUG') ? 'DRUG' : 'SERVICE';
            }
        }

        // Pull active lines.
        $this->db->select('id, invoice_no, bill_name, qty, rate, amount');
        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $lines = $this->db->get('iop_billing_t')->result();
        if (!$lines) { return; }

        $adjustments = array();
        $skipped     = array();

        foreach ($lines as $line) {
            $name = trim((string)$line->bill_name);
            if ($name === '') {
                $skipped[] = array('line_id' => (int)$line->id, 'reason' => 'empty_name');
                continue;
            }
            $key       = strtolower($name);
            $type_hint = isset($hint[$key]) ? $hint[$key] : null;

            // Resolve to (item_type, item_id) using the SSOT hint when present.
            $item_type = null; $item_id = 0;

            $try_drug = function() use ($name, &$item_type, &$item_id) {
                $drg = $this->db->get_where('medicine_drug_name', array('drug_name' => $name, 'InActive' => 0))->row();
                if ($drg && isset($drg->drug_id)) { $item_type = 'DRUG'; $item_id = (int)$drg->drug_id; return true; }
                return false;
            };
            $try_service = function() use ($name, &$item_type, &$item_id) {
                $svc = $this->db->get_where('bill_particular', array('particular_name' => $name, 'InActive' => 0))->row();
                if ($svc && isset($svc->particular_id)) { $item_type = 'SERVICE'; $item_id = (int)$svc->particular_id; return true; }
                return false;
            };

            if ($type_hint === 'DRUG') {
                $try_drug() || $try_service();
            } elseif ($type_hint === 'SERVICE') {
                $try_service() || $try_drug();
            } else {
                $try_service() || $try_drug();
            }

            if (!$item_type || $item_id <= 0) {
                $skipped[] = array(
                    'line_id'   => (int)$line->id,
                    'item_name' => $name,
                    'qty'       => (float)$line->qty,
                    'rate'      => (float)$line->rate,
                    'amount'    => (float)$line->amount,
                    'type_hint' => $type_hint,
                    'reason'    => 'no_catalog_match',
                );
                continue;
            }

            $res = $this->price_engine_model->resolve(array(
                'item_type'  => $item_type,
                'item_id'    => $item_id,
                'patient_no' => $patient_no,
                'payer_type' => 'COMPANY',
                'quantity'   => (float)$line->qty,
            ));
            if (empty($res['ok'])) {
                $skipped[] = array(
                    'line_id'   => (int)$line->id,
                    'item_name' => $name,
                    'item_type' => $item_type,
                    'item_id'   => $item_id,
                    'reason'    => 'price_engine_failed',
                );
                continue;
            }

            $new_unit   = round((float)$res['unit_price'], 2);
            $old_unit   = round((float)$line->rate, 2);
            $qty        = (float)$line->qty;
            $new_amount = round($new_unit * $qty, 2);
            $old_amount = round((float)$line->amount, 2);

            if (abs($new_amount - $old_amount) <= 0.01) { continue; }

            $this->db->where('id', (int)$line->id);
            $this->db->update('iop_billing_t', array(
                'rate'   => $new_unit,
                'amount' => $new_amount,
            ));

            $adjustments[] = array(
                'line_id'    => (int)$line->id,
                'item_type'  => $item_type,
                'item_id'    => $item_id,
                'item_name'  => $name,
                'old_rate'   => $old_unit,
                'new_rate'   => $new_unit,
                'old_amount' => $old_amount,
                'new_amount' => $new_amount,
                'qty'        => $qty,
                'type_hint'  => $type_hint,
            );
        }

        if ($adjustments) {
            $this->billing_model->log_nhis_audit(
                'COMPANY_PRICING_ADJUSTMENT', 'iop_billing_t', $invoice_no,
                null, json_encode(array('adjustments' => $adjustments, 'count' => count($adjustments))),
                $user_id, $patient_no, $iop_no
            );
        }
        if ($skipped) {
            // Always emit so operators can see what the sweep couldn't touch.
            $this->billing_model->log_nhis_audit(
                'COMPANY_PRICING_SKIPPED', 'iop_billing_t', $invoice_no,
                null, json_encode(array('skipped' => $skipped, 'count' => count($skipped))),
                $user_id, $patient_no, $iop_no
            );
        }
    }

    /* ============================================================
     * STEP 10 — INVOICE IMMUTABILITY AFTER FIRST RECEIPT
     *
     * Counts active receipts for the invoice. Audits every check.
     * When the flag is ON and at least one active receipt exists,
     * rejects the update with a clear error.
     *
     * Default: flag OFF, returns ok=true regardless.
     *
     * Note: voided receipts (InActive=1) do not count, so a clean
     * void+reissue workflow is unaffected.
     * ============================================================ */
    private function _check_invoice_editability($invoice_no, $user_id, $patient_no, $iop_no)
    {
        if ($this->is_phase2_kill_switch_enabled()) {
            $this->_audit_enforcement(
                'PHASE2_KILL_SWITCH_BYPASS',
                'INVOICE', (string)$invoice_no,
                (string)$invoice_no, (string)$patient_no,
                array('iop_no' => (string)$iop_no, 'action' => 'INVOICE_EDITABILITY', 'flag' => 'PHARMACY_PHASE2_ENFORCEMENT_KILL_SWITCH', 'decision' => 'BYPASS'),
                $user_id
            );
            return array('ok' => true, 'error' => null);
        }

        $strict = $this->is_immutable_after_receipt_enabled();
        $final_lock = $this->_enforce_flag('BILLING_ENFORCE_INVOICE_FINALIZATION_LOCK');
        $invoice_no = (string)$invoice_no;
        if ($invoice_no === '') {
            return array('ok' => true, 'error' => null);
        }

        $settlement = $this->billing_model->get_invoice_settlement($invoice_no);
        if (!empty($settlement['is_settled'])) {
            return array('ok' => false, 'error' => 'Invoice is financially settled and cannot be modified');
        }

        $this->db->where('invoice_no', $invoice_no);
        $this->db->where('InActive', 0);
        $receipt_count = (int)$this->db->count_all_results('iop_receipt');

        if ($receipt_count > 0) {
            $payload = json_encode(array(
                'invoice_no'    => $invoice_no,
                'receipt_count' => $receipt_count,
                'strict_mode'   => $strict,
                'final_lock'    => $final_lock,
                'rejected'      => ($strict || $final_lock),
            ));
            $this->billing_model->log_nhis_audit(
                ($strict || $final_lock) ? 'INVOICE_EDIT_BLOCKED' : 'INVOICE_EDIT_AFTER_RECEIPT',
                'iop_billing', $invoice_no,
                null, $payload, $user_id, $patient_no, $iop_no
            );
            if ($strict || $final_lock) {
                return array('ok' => false, 'error' => sprintf(
                    'Invoice %s cannot be edited because %d active receipt(s) exist for it. '
                    . 'Void the receipt(s) first or use a separate adjustment workflow.',
                    $invoice_no, $receipt_count
                ));
            }
        }
        return array('ok' => true, 'error' => null);
    }

    /**
     * PUBLIC guard — call from any code that wants to mutate an invoice
     * (line edits, header changes, deletes). Honors the same
     * BILLING_FACADE_IMMUTABLE_AFTER_RECEIPT flag and audits the same way.
     *
     * Returns the same shape as _check_invoice_editability:
     *   array{ ok: bool, error: string|null }
     *
     * Recommended call:
     *   $g = $this->billing_facade_model->assert_invoice_editable($invoice_no, $user_id);
     *   if (!$g['ok']) { $this->session->set_flashdata('error', $g['error']); return; }
     */
    public function assert_invoice_editable($invoice_no, $user_id = null, $patient_no = null, $iop_no = null)
    {
        return $this->_check_invoice_editability(
            (string)$invoice_no, $user_id,
            (string)$patient_no, (string)$iop_no
        );
    }

    /* ============================================================
     * Feature flag accessors (Steps 7 / 9 / 10).
     * Step 8 has no flag — generate_invoice_from_queue() is opt-in
     * by being a NEW method; existing callers are unchanged.
     * ============================================================ */
    public function is_nhis_claim_ssot_enabled()
    {
        return $this->_flag('BILLING_FACADE_NHIS_CLAIM_SSOT');
    }
    public function is_company_pricing_sweep_enabled()
    {
        return $this->_flag('BILLING_FACADE_COMPANY_PRICING_SWEEP');
    }
    public function is_immutable_after_receipt_enabled()
    {
        return $this->_enforce_flag('BILLING_FACADE_IMMUTABLE_AFTER_RECEIPT');
    }

    public function is_phase2_kill_switch_enabled()
    {
        return $this->_env_flag('PHARMACY_PHASE2_ENFORCEMENT_KILL_SWITCH');
    }

    private function _enforce_flag($name)
    {
        if ($this->is_phase2_kill_switch_enabled()) {
            return false;
        }
        return $this->_env_flag($name);
    }

    private function _env_flag($name)
    {
        $name = (string)$name;
        if ($name === '') return false;
        if (array_key_exists($name, $this->phase2_env_flag_cache)) {
            return (bool)$this->phase2_env_flag_cache[$name];
        }
        $env = getenv($name);
        if ($env === false) {
            $this->phase2_env_flag_cache[$name] = false;
            return false;
        }
        $val = filter_var($env, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($val === null) {
            $val = (trim((string)$env) !== '' && trim((string)$env) !== '0');
        }
        $this->phase2_env_flag_cache[$name] = (bool)$val;
        return (bool)$val;
    }

    private function _override_ctx($category, $user_id)
    {
        $ctx = array(
            'override_category' => (string)$category,
            'override_reason' => (string)$this->input->post('override_reason'),
            'override_requested_by' => (string)$user_id,
            'override_approved_by' => (string)$this->input->post('override_approved_by'),
            'override_at' => date('c'),
        );
        $ok = (trim($ctx['override_reason']) !== '' && trim($ctx['override_approved_by']) !== '');
        return array('ok' => $ok, 'ctx' => $ctx);
    }

    private function _audit_enforcement($action_type, $entity_type, $entity_id, $invoice_no, $patient_no, $details, $user_id)
    {
        try {
            $this->load->model('app/unified_billing_model');
            $this->unified_billing_model->log_billing_audit($action_type, array(
                'entity_type' => (string)$entity_type,
                'entity_id' => (string)$entity_id,
                'invoice_no' => (string)$invoice_no,
                'patient_no' => $patient_no !== null ? (string)$patient_no : null,
                'old_value' => null,
                'new_value' => null,
                'description' => is_string($details) ? $details : json_encode($details),
            ));
        } catch (Exception $e) {
            return;
        }
    }

    private function _check_verified_rx_invoice_editability($invoice_no, $user_id, $patient_no, $iop_no)
    {
        if (!$this->_enforce_flag('PHARMACY_BLOCK_INVOICE_EDIT_WHEN_RX_VERIFIED')) {
            return array('ok' => true, 'error' => null);
        }
        if (!$this->db->table_exists('iop_billing_line_meta')) {
            return array('ok' => true, 'error' => null);
        }

        $this->db->select('source_ref');
        $this->db->where('invoice_no', (string)$invoice_no);
        $this->db->where('InActive', 0);
        $this->db->group_start();
        $this->db->where('source_module', 'PHARMACY');
        $this->db->or_like('source_ref', 'iop_medication:', 'after');
        $this->db->group_end();
        $rows = $this->db->get('iop_billing_line_meta')->result();

        $iop_med_ids = array();
        foreach ($rows as $r) {
            $ref = isset($r->source_ref) ? trim((string)$r->source_ref) : '';
            if (strpos($ref, 'iop_medication:') !== 0) continue;
            $mid = (int)substr($ref, strlen('iop_medication:'));
            if ($mid > 0) $iop_med_ids[$mid] = $mid;
        }

        if (!$iop_med_ids) {
            return array('ok' => true, 'error' => null);
        }

        $this->db->select('iop_med_id, prescription_status');
        $this->db->where('InActive', 0);
        $this->db->where_in('iop_med_id', array_values($iop_med_ids));
        $med_rows = $this->db->get('iop_medication')->result();
        $verified = array();
        foreach ($med_rows as $m) {
            $st = isset($m->prescription_status) ? strtoupper(trim((string)$m->prescription_status)) : '';
            if ($st === 'VERIFIED') {
                $verified[] = (int)$m->iop_med_id;
            }
        }

        if (!$verified) {
            return array('ok' => true, 'error' => null);
        }

        if (!$this->_enforce_flag('PHARMACY_OVERRIDE_AUDIT_REQUIRED')) {
            $this->_audit_enforcement(
                'PHASE2_OVERRIDE_SYSTEM_NOT_AVAILABLE',
                'INVOICE', (string)$invoice_no,
                (string)$invoice_no, (string)$patient_no,
                array('verified_iop_med_ids' => $verified, 'action' => 'INVOICE_UPDATE', 'flag' => 'PHARMACY_OVERRIDE_AUDIT_REQUIRED', 'decision' => 'ALLOWED_FAIL_OPEN'),
                $user_id
            );
            return array('ok' => true, 'error' => null);
        }

        $ov = $this->_override_ctx('RX_VERIFIED_INVOICE_EDIT', $user_id);
        if ($ov['ok']) {
            $this->_audit_enforcement(
                'PHASE2_RX_VERIFIED_EDIT_OVERRIDE_USED',
                'INVOICE', (string)$invoice_no,
                (string)$invoice_no, (string)$patient_no,
                array('verified_iop_med_ids' => $verified, 'decision' => 'ALLOWED', 'override_used' => true, 'override' => $ov['ctx']),
                $user_id
            );
            return array('ok' => true, 'error' => null);
        }

        $this->_audit_enforcement(
            'PHASE2_RX_VERIFIED_EDIT_OVERRIDE_MISSING',
            'INVOICE', (string)$invoice_no,
            (string)$invoice_no, (string)$patient_no,
            array('verified_iop_med_ids' => $verified, 'decision' => 'ALLOWED_FAIL_OPEN', 'override_used' => false, 'error' => 'override_required_but_missing'),
            $user_id
        );
        return array('ok' => true, 'error' => null);
    }

    private function _enforce_pharmacy_invoice_integrity($invoice_no, $user_id, $patient_no, $iop_no, $payer, $action)
    {
        $invoice_no = (string)$invoice_no;
        $action = strtoupper(trim((string)$action));

        if ($this->is_phase2_kill_switch_enabled()) {
            return array('ok' => true, 'error' => null);
        }

        $enforce_price = $this->_enforce_flag('PHARMACY_STRICT_PRICE_ON_LEGACY_INVOICE_LINES');
        $enforce_qty = $this->_enforce_flag('PHARMACY_STRICT_QTY_MATCH_RX_ON_LEGACY_INVOICE_LINES');
        if (!$enforce_price && !$enforce_qty) {
            return array('ok' => true, 'error' => null);
        }

        $block = ($action === 'CREATE');

        if (!$this->db->table_exists('iop_billing_t') || !$this->db->table_exists('iop_billing_line_meta')) {
            return array('ok' => true, 'error' => null);
        }

        $this->db->select('T.id, T.qty, T.rate, M.source_ref');
        $this->db->from('iop_billing_t T');
        $this->db->join('iop_billing_line_meta M', 'M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0', 'inner');
        $this->db->where('T.invoice_no', $invoice_no);
        $this->db->where('T.InActive', 0);
        $this->db->where('M.source_module', 'PHARMACY');
        $lines = $this->db->get()->result();

        $iop_med_ids = array();
        foreach ($lines as $line) {
            $ref = isset($line->source_ref) ? trim((string)$line->source_ref) : '';
            if (strpos($ref, 'iop_medication:') !== 0) continue;
            $mid = (int)substr($ref, strlen('iop_medication:'));
            if ($mid > 0) $iop_med_ids[$mid] = $mid;
        }

        $med_map = array();
        if ($iop_med_ids) {
            $this->db->select('iop_med_id, medicine_id, total_qty');
            $this->db->where('InActive', 0);
            $this->db->where_in('iop_med_id', array_values($iop_med_ids));
            $med_rows = $this->db->get('iop_medication')->result();
            foreach ($med_rows as $m) {
                $med_map[(int)$m->iop_med_id] = $m;
            }
        }

        $violations = array();
        foreach ($lines as $line) {
            $ref = isset($line->source_ref) ? trim((string)$line->source_ref) : '';
            if (strpos($ref, 'iop_medication:') !== 0) continue;
            $mid = (int)substr($ref, strlen('iop_medication:'));
            if ($mid <= 0 || !isset($med_map[$mid])) continue;
            $med = $med_map[$mid];

            $auth_qty = isset($med->total_qty) ? (float)$med->total_qty : 0.0;
            $line_qty = isset($line->qty) ? (float)$line->qty : 0.0;

            if ($enforce_qty && $auth_qty > 0 && $line_qty > $auth_qty + 0.0001) {
                $violations[] = array(
                    'type' => 'QTY_EXCEEDS_AUTHORIZED',
                    'line_id' => isset($line->id) ? (int)$line->id : 0,
                    'iop_med_id' => $mid,
                    'invoice_qty' => $line_qty,
                    'authorized_qty_total' => $auth_qty,
                );
            }
        }

        if (!$violations) {
            return array('ok' => true, 'error' => null);
        }

        if ($block && !$this->_enforce_flag('PHARMACY_OVERRIDE_AUDIT_REQUIRED')) {
            $this->_audit_enforcement(
                'PHASE2_OVERRIDE_SYSTEM_NOT_AVAILABLE',
                'INVOICE', $invoice_no,
                $invoice_no, (string)$patient_no,
                array('action' => $action, 'violations' => $violations, 'flag' => 'PHARMACY_OVERRIDE_AUDIT_REQUIRED', 'decision' => 'ALLOWED_FAIL_OPEN'),
                $user_id
            );
            return array('ok' => true, 'error' => null);
        }

        if ($block) {
            $ov = $this->_override_ctx('PHARMACY_INVOICE_INTEGRITY', $user_id);
            if ($ov['ok']) {
                $this->_audit_enforcement(
                    'PHASE2_INVOICE_INTEGRITY_OVERRIDE_USED',
                    'INVOICE', $invoice_no,
                    $invoice_no, (string)$patient_no,
                    array('action' => $action, 'violations' => $violations, 'decision' => 'ALLOWED', 'override_used' => true, 'override' => $ov['ctx']),
                    $user_id
                );
                return array('ok' => true, 'error' => null);
            }

            $this->_audit_enforcement(
                'PHASE2_INVOICE_INTEGRITY_OVERRIDE_MISSING',
                'INVOICE', $invoice_no,
                $invoice_no, (string)$patient_no,
                array('action' => $action, 'violations' => $violations, 'decision' => 'ALLOWED_FAIL_OPEN', 'override_used' => false, 'error' => 'override_required_but_missing'),
                $user_id
            );

            return array('ok' => true, 'error' => null);
        }

        $this->_audit_enforcement(
            'PHASE2_INVOICE_INTEGRITY_OBSERVED',
            'INVOICE', $invoice_no,
            $invoice_no, (string)$patient_no,
            array('action' => $action, 'violations' => $violations),
            $user_id
        );

        return array('ok' => true, 'error' => null);
    }

    private function _run_pharmacy_invoice_diagnostics($invoice_no, $user_id, $patient_no, $iop_no, $action)
    {
        try {
            $this->load->model('app/Pharmacy_diagnostics_model', 'pharmacy_diagnostics_model');
            $this->pharmacy_diagnostics_model->log_invoice_shadow($invoice_no, $user_id, $patient_no, $iop_no, $action);
        } catch (Exception $e) {
            return;
        }
    }

    private function _flag($name)
    {
        $cfg = $this->config->item($name);
        if ($cfg === null) {
            $env = getenv($name);
            if ($env !== false) { $cfg = filter_var($env, FILTER_VALIDATE_BOOLEAN); }
        }
        return (bool)$cfg;
    }

    /* ============================================================
     * Reserved write endpoints (still placeholders for the
     * forthcoming admin-driven void/adjust/waive flows).
     * ============================================================ */
    public function void_invoice(/* string $invoice_no, string $reason, $user_id */)
    {
        throw new Exception('Billing_facade_model::void_invoice() is reserved for the admin void workflow.');
    }
    public function adjust_invoice(/* string $invoice_no, array $adjustments, $user_id */)
    {
        throw new Exception('Billing_facade_model::adjust_invoice() is reserved for the admin adjustment workflow.');
    }
    public function waive_charge(/* int $txn_id, string $reason, $user_id */)
    {
        throw new Exception('Billing_facade_model::waive_charge() is reserved for the admin waiver workflow.');
    }
}
