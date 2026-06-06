<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * BillingIntegrityService
 *
 * Centralised helper for checking SSOT billing state for an encounter.
 * This library performs lightweight, indexed existence checks against
 * billing_transactions, billing_queue, iop_billing and iop_receipt and
 * exposes a small API used by Smart Billing and other modules to ensure
 * that ledger state never diverges from SSOT.
 */
class BillingIntegrityService
{
    const STATE_NOT_BILLED       = 'NOT_BILLED';
    const STATE_PARTIALLY_BILLED = 'PARTIALLY_BILLED';
    const STATE_BILLED           = 'BILLED';
    const STATE_INVOICED         = 'INVOICED';
    const STATE_PAID             = 'PAID';

    /** @var CI_Controller */
    protected $CI;

    /**
     * Per-request cache: ["iop_id:patient_no"] => ['flags' => [...]]
     * This avoids duplicated EXISTS queries within the same request.
     */
    protected $cache = array();

    public function __construct()
    {
        $this->CI =& get_instance();
        if (!isset($this->CI->db)) {
            $this->CI->load->database();
        }
    }

    /**
     * Returns true if either registration or consultation visit fee
     * transaction exists for this encounter in billing_transactions.
     */
    public function has_visit_fee_transactions($iop_id, $patient_no = null)
    {
        return ($this->has_registration_transaction($iop_id, $patient_no)
            || $this->has_consultation_transaction($iop_id, $patient_no));
    }

    public function has_registration_transaction($iop_id, $patient_no = null)
    {
        return $this->check_flag($iop_id, $patient_no, 'reg_txn', function ($iop_id) {
            $itemRef = 'visit_registration:' . $iop_id;
            $sql = "SELECT 1 AS x FROM billing_transactions
                    WHERE InActive = 0
                      AND encounter_id = ?
                      AND department   = 'OPD'
                      AND item_ref     = ?
                    LIMIT 1";
            $q = $this->CI->db->query($sql, array((string)$iop_id, (string)$itemRef));
            return $q && $q->num_rows() > 0;
        });
    }

    public function has_consultation_transaction($iop_id, $patient_no = null)
    {
        return $this->check_flag($iop_id, $patient_no, 'con_txn', function ($iop_id) {
            $itemRef = 'visit_consultation:' . $iop_id;
            $sql = "SELECT 1 AS x FROM billing_transactions
                    WHERE InActive = 0
                      AND encounter_id = ?
                      AND department   = 'OPD'
                      AND item_ref     = ?
                    LIMIT 1";
            $q = $this->CI->db->query($sql, array((string)$iop_id, (string)$itemRef));
            return $q && $q->num_rows() > 0;
        });
    }

    public function has_queue_entries($iop_id, $patient_no = null)
    {
        return $this->check_flag($iop_id, $patient_no, 'queue', function ($iop_id) use ($patient_no) {
            if (!$this->CI->db->table_exists('billing_queue')) {
                return false;
            }
            $params = array((string)$iop_id);
            $wherePatient = '';
            if ($patient_no !== null && $patient_no !== '') {
                $wherePatient = ' AND patient_no = ?';
                $params[] = (string)$patient_no;
            }
            $sql = "SELECT 1 AS x FROM billing_queue
                    WHERE InActive = 0
                      AND iop_id    = ?" . $wherePatient . "
                    LIMIT 1";
            $q = $this->CI->db->query($sql, $params);
            return $q && $q->num_rows() > 0;
        });
    }

    public function has_invoice($iop_id, $patient_no = null)
    {
        return $this->check_flag($iop_id, $patient_no, 'invoice', function ($iop_id) use ($patient_no) {
            if (!$this->CI->db->table_exists('iop_billing')) {
                return false;
            }
            $params = array((string)$iop_id);
            $wherePatient = '';
            if ($patient_no !== null && $patient_no !== '') {
                $wherePatient = ' AND patient_no = ?';
                $params[] = (string)$patient_no;
            }
            $sql = "SELECT 1 AS x FROM iop_billing
                    WHERE InActive = 0
                      AND iop_id   = ?" . $wherePatient . "
                    LIMIT 1";
            $q = $this->CI->db->query($sql, $params);
            return $q && $q->num_rows() > 0;
        });
    }

    /**
     * True when at least one invoice exists for the encounter and all such
     * invoices have payment_status = 'PAID' (case-insensitive).
     */
    public function is_fully_paid($iop_id, $patient_no = null)
    {
        return $this->check_flag($iop_id, $patient_no, 'paid', function ($iop_id) use ($patient_no) {
            if (!$this->CI->db->table_exists('iop_billing')) {
                return false;
            }
            $params = array((string)$iop_id);
            $wherePatient = '';
            if ($patient_no !== null && $patient_no !== '') {
                $wherePatient = ' AND patient_no = ?';
                $params[] = (string)$patient_no;
            }
            // Any non-PAID invoice makes the encounter not fully paid.
            $sqlAny = "SELECT UPPER(COALESCE(payment_status,'')) AS st
                       FROM iop_billing
                       WHERE InActive = 0
                         AND iop_id   = ?" . $wherePatient . "
                       LIMIT 1";
            $qAny = $this->CI->db->query($sqlAny, $params);
            if (!$qAny || $qAny->num_rows() === 0) {
                return false;
            }
            $sql = "SELECT 1 AS x FROM iop_billing
                    WHERE InActive = 0
                      AND iop_id   = ?" . $wherePatient . "
                      AND UPPER(COALESCE(payment_status,'')) <> 'PAID'
                    LIMIT 1";
            $q = $this->CI->db->query($sql, $params);
            if ($q && $q->num_rows() > 0) {
                return false;
            }
            return true;
        });
    }

    /**
     * Determine high-level billing state for an encounter from SSOT only.
     *
     * Order:
     *   - NOT_BILLED: no visit-fee transactions
     *   - PARTIALLY_BILLED: transactions exist but no queue
     *   - BILLED: queue exists but no invoice
     *   - INVOICED: invoice exists but not fully paid
     *   - PAID: invoice(s) exist and are fully paid
     */
    public function determine_billing_state($iop_id, $patient_no = null)
    {
        $hasTxn   = $this->has_visit_fee_transactions($iop_id, $patient_no);
        $hasQueue = $this->has_queue_entries($iop_id, $patient_no);
        $hasInv   = $this->has_invoice($iop_id, $patient_no);
        $isPaid   = $hasInv ? $this->is_fully_paid($iop_id, $patient_no) : false;

        if (!$hasTxn) {
            return self::STATE_NOT_BILLED;
        }
        if (!$hasQueue) {
            return self::STATE_PARTIALLY_BILLED;
        }
        if (!$hasInv) {
            return self::STATE_BILLED;
        }
        if ($isPaid) {
            return self::STATE_PAID;
        }
        return self::STATE_INVOICED;
    }

    /**
     * Basic health-check summary for future use by repair tools and UI.
     *
     * Returns array { healthy: bool, state: string, issues: [] }
     */
    public function validate_encounter($iop_id, $patient_no = null)
    {
        $issues = array();
        $state  = $this->determine_billing_state($iop_id, $patient_no);

        $hasReg = $this->has_registration_transaction($iop_id, $patient_no);
        $hasCon = $this->has_consultation_transaction($iop_id, $patient_no);
        $hasTxn = ($hasReg || $hasCon);
        $hasQueue = $this->has_queue_entries($iop_id, $patient_no);
        $hasInv   = $this->has_invoice($iop_id, $patient_no);
        $isPaid   = $hasInv ? $this->is_fully_paid($iop_id, $patient_no) : false;

        if ($state === self::STATE_NOT_BILLED && $this->has_ledger_billed($iop_id, $patient_no)) {
            $issues[] = 'ledger_billed_but_no_transactions';
        }
        if ($hasTxn && !$hasQueue) {
            $issues[] = 'transactions_without_queue';
        }
        if ($hasQueue && !$hasInv) {
            $issues[] = 'queue_without_invoice';
        }
        if ($hasInv && !$this->has_ledger_row($iop_id, $patient_no)) {
            $issues[] = 'invoice_without_ledger_row';
        }
        if ($hasInv && $isPaid && !$this->has_ledger_billed($iop_id, $patient_no)) {
            $issues[] = 'paid_invoice_without_billed_ledger';
        }

        $healthy = empty($issues);
        return array(
            'healthy' => $healthy,
            'state'   => $state,
            'issues'  => $issues,
        );
    }

    /**
     * Internal helper for per-request caching of boolean flags.
     */
    protected function check_flag($iop_id, $patient_no, $flagKey, $fn)
    {
        $iop_id = (string)$iop_id;
        $patient_no = $patient_no !== null ? (string)$patient_no : '';
        $encKey = $iop_id . ':' . $patient_no;
        if (!isset($this->cache[$encKey])) {
            $this->cache[$encKey] = array('flags' => array());
        }
        if (isset($this->cache[$encKey]['flags'][$flagKey])) {
            return $this->cache[$encKey]['flags'][$flagKey];
        }
        $val = (bool)call_user_func($fn, $iop_id);
        $this->cache[$encKey]['flags'][$flagKey] = $val;
        return $val;
    }

    protected function has_ledger_row($iop_id, $patient_no = null)
    {
        if (!$this->CI->db->table_exists('smart_billing_ledger')) {
            return false;
        }
        $params = array((string)$iop_id);
        $wherePatient = '';
        if ($patient_no !== null && $patient_no !== '') {
            $wherePatient = ' AND patient_no = ?';
            $params[] = (string)$patient_no;
        }
        $sql = "SELECT 1 AS x FROM smart_billing_ledger
                WHERE InActive = 0
                  AND iop_id   = ?" . $wherePatient . "
                LIMIT 1";
        $q = $this->CI->db->query($sql, $params);
        return $q && $q->num_rows() > 0;
    }

    protected function has_ledger_billed($iop_id, $patient_no = null)
    {
        if (!$this->CI->db->table_exists('smart_billing_ledger')) {
            return false;
        }
        $params = array((string)$iop_id);
        $wherePatient = '';
        if ($patient_no !== null && $patient_no !== '') {
            $wherePatient = ' AND patient_no = ?';
            $params[] = (string)$patient_no;
        }
        $sql = "SELECT 1 AS x FROM smart_billing_ledger
                WHERE InActive = 0
                  AND iop_id   = ?" . $wherePatient . "
                  AND UPPER(COALESCE(status,'')) = 'BILLED'
                LIMIT 1";
        $q = $this->CI->db->query($sql, $params);
        return $q && $q->num_rows() > 0;
    }
}
