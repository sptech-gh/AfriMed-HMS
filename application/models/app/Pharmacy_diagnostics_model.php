<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Pharmacy_diagnostics_model extends CI_Model
{
	private $_enabled_cache = null;
	private $_enabled_source = null;
	private $_activation_logged = false;

    public function __construct()
    {
        parent::__construct();
    }

    public function is_enabled()
    {
		if ($this->_enabled_cache !== null) {
			return (bool)$this->_enabled_cache;
		}

		$env = getenv('PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED');
		if ($env !== false && trim((string)$env) !== '') {
			$parsed = $this->_parse_bool($env, null);
			if ($parsed !== null) {
				$this->_enabled_cache = (bool)$parsed;
				$this->_enabled_source = 'env';
				$this->_log_activation_once();
				return (bool)$this->_enabled_cache;
			}
		}

		$cfg = $this->config->item('PHARMACY_BILLING_PARITY_DIAGNOSTICS_ENABLED');
		$this->_enabled_cache = (bool)$cfg;
		$this->_enabled_source = 'config';
		$this->_log_activation_once();
		return (bool)$this->_enabled_cache;
    }

	private function _parse_bool($raw, $default)
	{
		if ($raw === null) return $default;
		$raw = strtolower(trim((string)$raw));
		if ($raw === '') return $default;
		if (in_array($raw, array('1','true','on','yes','y'), true)) return true;
		if (in_array($raw, array('0','false','off','no','n'), true)) return false;
		$val = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
		if ($val === null) return $default;
		return (bool)$val;
	}

	private function _log_activation_once()
	{
		if ($this->_activation_logged) return;
		$this->_activation_logged = true;
		if (!$this->_enabled_cache) return;
		if (!function_exists('log_message')) return;
		$env = defined('ENVIRONMENT') ? (string)ENVIRONMENT : '';
		$host = function_exists('gethostname') ? (string)gethostname() : (string)php_uname('n');
		log_message('info', 'PHARMACY_PHASE1_DIAGNOSTICS_ENABLED source=' . (string)$this->_enabled_source . ' env=' . $env . ' host=' . $host . ' at=' . date('c'));
	}

    public function log_invoice_shadow($invoice_no, $user_id = null, $patient_no = null, $iop_no = null, $action = 'SAVE')
    {
        if (!$this->is_enabled()) return;
        if (!$this->table_exists('iop_billing_t') || !$this->table_exists('iop_billing_line_meta')) return;

        $invoice_no = trim((string)$invoice_no);
        if ($invoice_no === '') return;

        $this->db->select('T.id, T.invoice_no, T.iop_id, T.bill_name, T.qty, T.rate, T.amount, M.source_module, M.source_ref');
        $this->db->from('iop_billing_t T');
        $this->db->join('iop_billing_line_meta M', 'M.invoice_no = T.invoice_no AND M.detail_id = T.id AND M.InActive = 0', 'left');
        $this->db->where('T.invoice_no', $invoice_no);
        $this->db->where('T.InActive', 0);
        $this->db->group_start();
        $this->db->where('M.source_module', 'PHARMACY');
        $this->db->or_like('M.source_ref', 'iop_medication:', 'after');
        $this->db->group_end();
        $lines = $this->db->get()->result();

        foreach ($lines as $line) {
            $med_id = $this->parse_iop_med_id(isset($line->source_ref) ? $line->source_ref : '');
            if ($med_id <= 0) continue;
            $this->diagnose_invoice_line($line, $med_id, $invoice_no, $user_id, $patient_no, $iop_no, $action);
        }
    }

    public function log_dispense_shadow($iop_med_id, $dispense_qty, $status, $user_id = null, $mode = '', $admin_id = 0)
    {
        if (!$this->is_enabled()) return;
        $iop_med_id = (int)$iop_med_id;
        if ($iop_med_id <= 0 || !$this->table_exists('iop_medication')) return;

        $med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
        if (!$med) return;

        $authorized = isset($med->total_qty) ? (float)$med->total_qty : 0.0;
        $dispensed = $this->get_dispensed_qty($iop_med_id);
        $paid = $this->get_paid_qty_total($iop_med_id, $authorized);
        $mode = strtoupper(trim((string)$mode));
        $exception_modes = array('EXTERNAL_PURCHASE','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','ADMITTED');

        $context = array(
            'iop_med_id' => $iop_med_id,
            'admin_id' => (int)$admin_id,
            'dispense_qty' => (float)$dispense_qty,
            'dispense_status' => (string)$status,
            'mode' => $mode,
            'authorized_qty_total' => $authorized,
            'dispensed_qty_total' => $dispensed,
            'paid_qty_total' => $paid,
            'shadow_only' => true,
        );

        if ($authorized > 0 && $dispensed > $authorized + 0.0001) {
            $this->log_reconciliation('PHARMACY_DISPENSE_EXCEEDS_RX', 'iop_med_id:' . $iop_med_id, null, isset($med->iop_id) ? $med->iop_id : null, $context, $user_id);
        }

        if (!in_array($mode, $exception_modes, true) && $dispensed > $paid + 0.0001) {
            $this->log_reconciliation('PHARMACY_DISPENSE_PAYMENT_MISMATCH', 'iop_med_id:' . $iop_med_id, null, isset($med->iop_id) ? $med->iop_id : null, $context, $user_id);
        }

        if (in_array($mode, $exception_modes, true)) {
            $this->log_reconciliation('PHARMACY_DISPENSE_EXCEPTION_MODE_USED', 'iop_med_id:' . $iop_med_id, null, isset($med->iop_id) ? $med->iop_id : null, $context, $user_id);
        }
    }

    public function log_stock_adjustment_shadow($drug_id, $type, $qty_change, $before, $after, $reason, $user_id = null)
    {
        if (!$this->is_enabled()) return;
        $context = array(
            'drug_id' => (int)$drug_id,
            'adjustment_type' => (string)$type,
            'qty_change' => (float)$qty_change,
            'stock_before' => (float)$before,
            'stock_after' => (float)$after,
            'reason' => (string)$reason,
            'shadow_only' => true,
        );
        $issue = ((float)$after < 0) ? 'PHARMACY_STOCK_NEGATIVE_SHADOW' : 'PHARMACY_STOCK_ADJUSTMENT_SHADOW';
        $this->log_reconciliation($issue, 'drug_id:' . (int)$drug_id, null, null, $context, $user_id);
    }

    public function log_expiry_removal_shadow($stock_id, $drug_id, $qty, $batch_number, $reason, $user_id = null)
    {
        if (!$this->is_enabled()) return;
        $context = array(
            'stock_id' => (int)$stock_id,
            'drug_id' => (int)$drug_id,
            'qty_removed' => (float)$qty,
            'batch_number' => (string)$batch_number,
            'reason' => (string)$reason,
            'shadow_only' => true,
        );
        $this->log_reconciliation('PHARMACY_EXPIRED_BATCH_REMOVAL_SHADOW', 'stock_id:' . (int)$stock_id, null, null, $context, $user_id);
    }

    private function diagnose_invoice_line($line, $iop_med_id, $invoice_no, $user_id, $patient_no, $iop_no, $action)
    {
        $med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
        if (!$med) return;

        $qty = isset($line->qty) ? (float)$line->qty : 0.0;
        $rate = isset($line->rate) ? (float)$line->rate : 0.0;
        $authorized = isset($med->total_qty) ? (float)$med->total_qty : 0.0;
        $rx_status = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : '';

        $context = array(
            'action' => (string)$action,
            'invoice_no' => $invoice_no,
            'line_id' => isset($line->id) ? (int)$line->id : 0,
            'source_ref' => isset($line->source_ref) ? (string)$line->source_ref : '',
            'iop_med_id' => $iop_med_id,
            'medicine_id' => isset($med->medicine_id) ? (int)$med->medicine_id : 0,
            'rx_status' => $rx_status,
            'invoice_qty' => $qty,
            'authorized_qty_total' => $authorized,
            'invoice_rate' => $rate,
            'shadow_only' => true,
        );

        if (strtoupper((string)$action) === 'UPDATE' && in_array($rx_status, array('VERIFIED','DISPENSED'), true)) {
            $this->log_reconciliation('PHARMACY_POST_VERIFICATION_INVOICE_EDIT', 'invoice:' . $invoice_no . ':line:' . (int)$line->id, $patient_no, $iop_no, $context, $user_id);
        }

        if ($authorized > 0 && $qty > $authorized + 0.0001) {
            $this->log_reconciliation('PHARMACY_INVOICE_QTY_EXCEEDS_RX', 'invoice:' . $invoice_no . ':line:' . (int)$line->id, $patient_no, $iop_no, $context, $user_id);
        }

        if (!empty($context['medicine_id'])) {
            $this->diagnose_price($context, $patient_no, $iop_no, $user_id);
        }
    }

    private function diagnose_price(array $context, $patient_no, $iop_no, $user_id)
    {
        try {
            $this->load->model('app/Price_engine_model', 'price_engine_model');
            $res = $this->price_engine_model->resolve(array(
                'item_type' => 'DRUG',
                'item_id' => (int)$context['medicine_id'],
                'patient_no' => (string)$patient_no,
                'payer_type' => null,
                'quantity' => (float)$context['invoice_qty'],
                'submitted_unit_price' => (float)$context['invoice_rate'],
                'encounter_id' => (string)$iop_no,
                'user_id' => (string)$user_id,
                'context' => array(
                    'source' => 'PHARMACY_PHASE1_DIAGNOSTICS',
                    'invoice_no' => $context['invoice_no'],
                    'line_id' => $context['line_id'],
                    'source_ref' => $context['source_ref'],
                ),
            ));
            if (is_array($res) && !empty($res['ok'])) {
                $authoritative = round((float)$res['unit_price'], 2);
                $submitted = round((float)$context['invoice_rate'], 2);
                if (abs($submitted - $authoritative) > 0.01) {
                    $context['authoritative_rate'] = $authoritative;
                    $context['price_source'] = isset($res['price_source']) ? $res['price_source'] : null;
                    $context['rate_diff'] = round($submitted - $authoritative, 2);
                    $this->log_reconciliation('PHARMACY_INVOICE_PRICE_DRIFT', 'invoice:' . $context['invoice_no'] . ':line:' . $context['line_id'], $patient_no, $iop_no, $context, $user_id);
                }
            }
        } catch (Exception $e) {
            return;
        }
    }

    private function get_dispensed_qty($iop_med_id)
    {
        if (!$this->table_exists('iop_medication_administration')) return 0.0;
        $this->db->select('COALESCE(SUM(dose_given),0) AS total', false);
        $this->db->where('iop_med_id', (int)$iop_med_id);
        $this->db->where('InActive', 0);
        $this->db->where_in('status', array('DISPENSED','PARTIAL'));
        $row = $this->db->get('iop_medication_administration')->row();
        return $row && isset($row->total) ? (float)$row->total : 0.0;
    }

    private function get_paid_qty_total($iop_med_id, $prescribed_qty)
    {
        $txn = null;
        if ($this->table_exists('billing_transactions')) {
            $this->db->where('InActive', 0);
            $this->db->where('department', 'PHARMACY');
            $this->db->where('item_ref', 'iop_med_id:' . (int)$iop_med_id);
            $this->db->limit(1);
            $txn = $this->db->get('billing_transactions')->row();
        }
        if (!$txn) {
            return $this->get_paid_qty_total_from_queue($iop_med_id, $prescribed_qty);
        }

        $payer = isset($txn->payer_type) ? strtoupper(trim((string)$txn->payer_type)) : 'CASH';
        $pay = isset($txn->payment_status) ? strtoupper(trim((string)$txn->payment_status)) : 'PENDING';
        $net = isset($txn->net_amount) ? (float)$txn->net_amount : 0.0;
        $paid = isset($txn->paid_amount) ? (float)$txn->paid_amount : 0.0;
        $bal = isset($txn->balance_amount) ? (float)$txn->balance_amount : max(0.0, $net - $paid);

        if ($prescribed_qty <= 0) return 0.0;
        if ($payer === 'NHIS' || $payer === 'INSURANCE' || ($payer !== '' && $payer !== 'CASH')) return (float)$prescribed_qty;
        if (in_array($pay, array('PAID','WAIVED'), true) || $bal <= 0.0001) return (float)$prescribed_qty;
        if ($pay === 'PARTIAL') {
            if ($net <= 0.0001) return (float)$prescribed_qty;
            $fraction = $paid / $net;
            if ($fraction < 0) $fraction = 0;
            if ($fraction > 1) $fraction = 1;
            return floor(((float)$prescribed_qty * $fraction) * 100.0) / 100.0;
        }
        return 0.0;
    }

    private function get_paid_qty_total_from_queue($iop_med_id, $prescribed_qty)
    {
        if (!$this->table_exists('pharmacy_billing_queue')) return 0.0;
        $bill = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => (int)$iop_med_id, 'InActive' => 0))->row();
        if (!$bill) return 0.0;

        $pay_status = isset($bill->payment_status) ? strtoupper(trim((string)$bill->payment_status)) : 'PENDING';
        $ext_status = isset($bill->extended_status) ? strtoupper(trim((string)$bill->extended_status)) : '';
        $payer = isset($bill->payer_type) ? strtoupper(trim((string)$bill->payer_type)) : 'CASH';
        if ($prescribed_qty <= 0 && isset($bill->quantity)) {
            $prescribed_qty = (float)$bill->quantity;
        }
        $exceptions = array('EXTERNAL_PURCHASE','EXTERNAL','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','WAIVER_APPROVED','WAIVER_REQUESTED');
        if ($payer === 'NHIS' || $pay_status === 'PAID' || $pay_status === 'WAIVED' || in_array($ext_status, $exceptions, true) || in_array($pay_status, $exceptions, true)) {
            return max(0.0, (float)$prescribed_qty);
        }
        return 0.0;
    }

    private function parse_iop_med_id($source_ref)
    {
        $source_ref = trim((string)$source_ref);
        if (strpos($source_ref, 'iop_medication:') !== 0) return 0;
        return (int)substr($source_ref, strlen('iop_medication:'));
    }

    private function log_reconciliation($issue_type, $record_ref, $patient_no, $encounter_id, array $details, $user_id = null)
    {
        if (!$this->table_exists('billing_reconciliation_log')) return;
		if (!isset($details['schema_version'])) {
			$details['schema_version'] = 1;
		}
		$details = $this->_sanitize_details($details);
        $row = array();
        if ($this->column_exists('billing_reconciliation_log', 'recon_date')) $row['recon_date'] = date('Y-m-d');
        if ($this->column_exists('billing_reconciliation_log', 'department')) $row['department'] = 'PHARMACY';
        if ($this->column_exists('billing_reconciliation_log', 'issue_type')) $row['issue_type'] = (string)$issue_type;
        if ($this->column_exists('billing_reconciliation_log', 'record_ref')) $row['record_ref'] = (string)$record_ref;
        if ($this->column_exists('billing_reconciliation_log', 'patient_no')) $row['patient_no'] = $patient_no !== null ? (string)$patient_no : null;
        if ($this->column_exists('billing_reconciliation_log', 'encounter_id')) $row['encounter_id'] = $encounter_id !== null ? (string)$encounter_id : null;
        if ($this->column_exists('billing_reconciliation_log', 'details')) $row['details'] = json_encode($details);
        if ($this->column_exists('billing_reconciliation_log', 'resolved')) $row['resolved'] = 0;
        if ($this->column_exists('billing_reconciliation_log', 'created_at')) $row['created_at'] = date('Y-m-d H:i:s');
        if (empty($row)) return;
        if ($this->db->field_exists('performed_by', 'billing_reconciliation_log')) {
            $row['performed_by'] = $user_id !== null ? (string)$user_id : null;
        }
        $prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($prev !== null) $this->db->db_debug = false;
        try {
            $this->db->insert('billing_reconciliation_log', $row);
        } catch (Exception $e) {
        }
        if ($prev !== null) $this->db->db_debug = $prev;
    }

    private function table_exists($table_name)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$table_name));
        return ($q && $q->num_rows() > 0);
    }

    private function column_exists($table_name, $column_name)
    {
        if (!$this->table_exists($table_name)) return false;
        return $this->db->field_exists((string)$column_name, (string)$table_name);
    }

	private function _sanitize_details($value, $depth = 0)
	{
		if ($depth > 3) return null;

		if (is_array($value)) {
			$out = array();
			$deny = array('notes','note','instruction','instructions','clinical_notes','clinical_note','remarks','remark');
			$limit = 0;
			foreach ($value as $k => $v) {
				$limit++;
				if ($limit > 60) break;
				$key = is_string($k) ? strtolower(trim($k)) : $k;
				if (is_string($key) && in_array($key, $deny, true)) continue;
				$out[$k] = $this->_sanitize_details($v, $depth + 1);
			}
			return $out;
		}

		if (is_string($value)) {
			$v = trim($value);
			if (strlen($v) > 256) {
				$v = substr($v, 0, 256);
			}
			return $v;
		}

		if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
			return $value;
		}

		return null;
	}
}
