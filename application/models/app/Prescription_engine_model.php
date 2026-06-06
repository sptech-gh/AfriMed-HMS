<?php
/**
 * Prescription_engine_model — Phase 4
 *
 * Responsibilities:
 *  1. Ensure all Phase 4 DB columns exist (idempotent, safe for every request)
 *  2. Generate unique sequential prescription numbers (RX-000001 …)
 *  3. Route NHIS-covered prescriptions to nhis_claim_queue
 *  4. Push billing entries to pharmacy_billing_queue with prescription_no
 *  5. Write structured rows to pharmacy_audit_log
 *  6. Full prescription lifecycle: cancel, update, get
 */
class Prescription_engine_model extends CI_Model
{
    /* -------------------------------------------------------------------------
     * Schema boot
     * ----------------------------------------------------------------------- */

    public function ensure_phase4_schema()
    {
        static $done = false;
        if ($done) return;
        $done = true;

        $dbg = isset($this->db->db_debug) ? $this->db->db_debug : null;
        if ($dbg !== null) $this->db->db_debug = false;

        /* ── iop_medication Phase 4 columns ─────────────────────────────── */
        $medCols = array(
            'prescription_no'  => "ALTER TABLE `iop_medication` ADD COLUMN `prescription_no` VARCHAR(20) DEFAULT NULL AFTER `iop_med_id`",
            'unit'             => "ALTER TABLE `iop_medication` ADD COLUMN `unit` VARCHAR(20) DEFAULT NULL",
            'freq_code'        => "ALTER TABLE `iop_medication` ADD COLUMN `freq_code` VARCHAR(10) DEFAULT NULL",
            'is_nhis_covered'  => "ALTER TABLE `iop_medication` ADD COLUMN `is_nhis_covered` TINYINT(1) NOT NULL DEFAULT 0",
            'is_prn'           => "ALTER TABLE `iop_medication` ADD COLUMN `is_prn` TINYINT(1) NOT NULL DEFAULT 0",
            'is_urgent'        => "ALTER TABLE `iop_medication` ADD COLUMN `is_urgent` TINYINT(1) NOT NULL DEFAULT 0",
            'cancelled_at'     => "ALTER TABLE `iop_medication` ADD COLUMN `cancelled_at` DATETIME DEFAULT NULL",
            'cancelled_by'     => "ALTER TABLE `iop_medication` ADD COLUMN `cancelled_by` VARCHAR(25) DEFAULT NULL",
            'cancel_reason'    => "ALTER TABLE `iop_medication` ADD COLUMN `cancel_reason` VARCHAR(255) DEFAULT NULL",
            'updated_at'       => "ALTER TABLE `iop_medication` ADD COLUMN `updated_at` DATETIME DEFAULT NULL",
        );
        if ($this->db->table_exists('iop_medication')) {
            foreach ($medCols as $col => $ddl) {
                if (!$this->db->field_exists($col, 'iop_medication')) {
                    $this->db->query($ddl);
                }
            }
            /* Index on prescription_no */
            $this->_ensure_index('iop_medication', 'idx_rx_no', 'prescription_no');
            $this->_ensure_index('iop_medication', 'idx_nhis_cov', 'is_nhis_covered');
        }

        /* ── prescription_sequence table (auto-increment RX counter) ─────── */
        $this->db->query("CREATE TABLE IF NOT EXISTS `prescription_sequence` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `prefix` varchar(5) NOT NULL DEFAULT 'RX',
            `last_no` int(11) NOT NULL DEFAULT 0,
            `updated_at` datetime DEFAULT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_prefix` (`prefix`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8");

        /* Seed if empty */
        $row = $this->db->get_where('prescription_sequence', array('prefix' => 'RX'))->row();
        if (!$row) {
            $this->db->insert('prescription_sequence', array(
                'prefix'     => 'RX',
                'last_no'    => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ));
        }

        /* ── nhis_claim_queue ─────────────────────────────────────────────── */
        $this->db->query("CREATE TABLE IF NOT EXISTS `nhis_claim_queue` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `prescription_no` varchar(20) NOT NULL,
            `iop_med_id` int(11) NOT NULL,
            `patient_id` varchar(25) NOT NULL,
            `visit_id` varchar(11) NOT NULL,
            `drug_name` varchar(255) DEFAULT NULL,
            `drug_id` int(11) DEFAULT NULL,
            `quantity` decimal(11,2) NOT NULL DEFAULT 0,
            `unit_price` decimal(18,2) NOT NULL DEFAULT 0,
            `status` varchar(20) NOT NULL DEFAULT 'PENDING',
            `claim_ref` varchar(50) DEFAULT NULL,
            `submitted_at` datetime DEFAULT NULL,
            `approved_at` datetime DEFAULT NULL,
            `rejected_at` datetime DEFAULT NULL,
            `rejection_reason` varchar(255) DEFAULT NULL,
            `created_at` datetime NOT NULL,
            `updated_at` datetime DEFAULT NULL,
            `InActive` int(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_nhis_iop_med` (`iop_med_id`),
            KEY `idx_nhis_rx`       (`prescription_no`),
            KEY `idx_nhis_patient`  (`patient_id`),
            KEY `idx_nhis_status`   (`status`),
            KEY `idx_nhis_created`  (`created_at`)
        ) ENGINE=MyISAM DEFAULT CHARSET=utf8");

        /* ── pharmacy_billing_queue: add prescription_no column if missing ── */
        if ($this->db->table_exists('pharmacy_billing_queue')) {
            if (!$this->db->field_exists('prescription_no', 'pharmacy_billing_queue')) {
                $this->db->query("ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `prescription_no` VARCHAR(20) DEFAULT NULL AFTER `iop_med_id`");
                $this->db->query("ALTER TABLE `pharmacy_billing_queue` ADD INDEX `idx_pbq_rx_no` (`prescription_no`)");
            }
            if (!$this->db->field_exists('is_nhis_covered', 'pharmacy_billing_queue')) {
                $this->db->query("ALTER TABLE `pharmacy_billing_queue` ADD COLUMN `is_nhis_covered` TINYINT(1) NOT NULL DEFAULT 0");
            }
        }

        /* ── pharmacy_audit_log: add prescription_no column if missing ────── */
        if ($this->db->table_exists('pharmacy_audit_log')) {
            if (!$this->db->field_exists('prescription_no', 'pharmacy_audit_log')) {
                $this->db->query("ALTER TABLE `pharmacy_audit_log` ADD COLUMN `prescription_no` VARCHAR(20) DEFAULT NULL AFTER `iop_med_id`");
            }
        }

        if ($dbg !== null) $this->db->db_debug = $dbg;
    }

    /* -------------------------------------------------------------------------
     * Prescription Number Generator
     * Thread-safe via InnoDB row-lock (SELECT … FOR UPDATE)
     * Returns: 'RX-000001'
     * ----------------------------------------------------------------------- */

    public function generate_prescription_no()
    {
        $this->db->trans_start();

        /* Lock the row */
        $q = $this->db->query(
            "SELECT last_no FROM `prescription_sequence` WHERE prefix = 'RX' FOR UPDATE"
        );
        $row = $q ? $q->row() : null;
        $next = $row ? ((int)$row->last_no + 1) : 1;

        $this->db->query(
            "UPDATE `prescription_sequence` SET last_no = ?, updated_at = ? WHERE prefix = 'RX'",
            array($next, date('Y-m-d H:i:s'))
        );

        $this->db->trans_complete();

        if ($this->db->trans_status() === false) {
            /* Fallback: use timestamp-based unique number */
            return 'RX-' . strtoupper(base_convert(time(), 10, 36));
        }

        return 'RX-' . str_pad($next, 6, '0', STR_PAD_LEFT);
    }

    /* -------------------------------------------------------------------------
     * Stamp prescription_no onto iop_medication after insert
     * ----------------------------------------------------------------------- */

    public function stamp_prescription_no($iop_med_id, $prescription_no)
    {
        $this->db->where('iop_med_id', (int)$iop_med_id);
        return $this->db->update('iop_medication', array(
            'prescription_no' => $prescription_no,
            'updated_at'      => date('Y-m-d H:i:s'),
        ));
    }

    /* -------------------------------------------------------------------------
     * Push to pharmacy_billing_queue (upsert pattern)
     * ----------------------------------------------------------------------- */

    public function push_to_billing_queue(array $args)
    {
        if (!$this->db->table_exists('pharmacy_billing_queue')) return false;

        $iop_med_id     = (int)($args['iop_med_id']     ?? 0);
		if ($iop_med_id > 0 && $this->db->field_exists('prescription_status', 'iop_medication')) {
			$row = $this->db->select('prescription_status')->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
			$st = $row && isset($row->prescription_status) ? strtoupper(trim((string)$row->prescription_status)) : 'PENDING';
			if ($st !== 'VERIFIED') {
				return false;
			}
		}
        $iop_id         = (string)($args['iop_id']      ?? '');
        $patient_no     = (string)($args['patient_no']  ?? '');
        $drug_id        = (int)($args['drug_id']        ?? 0);
        $drug_name      = (string)($args['drug_name']   ?? '');
        $quantity       = (float)($args['quantity']     ?? 1);
        $unit_price     = (float)($args['unit_price']   ?? 0);
        $prescription_no = (string)($args['prescription_no'] ?? '');
        $is_nhis        = (int)($args['is_nhis_covered'] ?? 0);
        $now            = date('Y-m-d H:i:s');

        $existing = $this->db->get_where('pharmacy_billing_queue', array('iop_med_id' => $iop_med_id))->row();

        if ($existing) {
            $upd = array('updated_at' => $now);
            if ($prescription_no) $upd['prescription_no'] = $prescription_no;
            if ($is_nhis)         $upd['is_nhis_covered'] = $is_nhis;
            $this->db->where('iop_med_id', $iop_med_id);
            return $this->db->update('pharmacy_billing_queue', $upd);
        }

        $data = array(
            'iop_med_id'      => $iop_med_id,
            'prescription_no' => $prescription_no,
            'iop_id'          => $iop_id,
            'patient_no'      => $patient_no,
            'drug_id'         => $drug_id ?: null,
            'drug_name'       => $drug_name,
            'quantity'        => $quantity,
            'unit_price'      => $unit_price,
            'payment_status'  => 'PENDING',
            'dispense_status' => 'WAITING',
            'is_nhis_covered' => $is_nhis,
            'created_at'      => $now,
        );

        $total = round($unit_price * $quantity, 2);
        if ($this->db->field_exists('total_amount', 'pharmacy_billing_queue')) {
            $data['total_amount'] = $total;
        }
        if ($this->db->field_exists('total', 'pharmacy_billing_queue')) {
            $data['total'] = $total;
        }

        return $this->db->insert('pharmacy_billing_queue', $data);
    }

    /* -------------------------------------------------------------------------
     * Push NHIS-covered drug to nhis_claim_queue
     * ----------------------------------------------------------------------- */

    public function push_to_nhis_queue(array $args)
    {
        if (!$this->db->table_exists('nhis_claim_queue')) return false;

        $iop_med_id     = (int)($args['iop_med_id']     ?? 0);
        $prescription_no = (string)($args['prescription_no'] ?? '');
        $patient_no     = (string)($args['patient_no']  ?? '');
        $iop_id         = (string)($args['iop_id']      ?? '');
        $drug_id        = (int)($args['drug_id']        ?? 0);
        $drug_name      = (string)($args['drug_name']   ?? '');
        $quantity       = (float)($args['quantity']     ?? 1);
        $unit_price     = (float)($args['unit_price']   ?? 0);
        $now            = date('Y-m-d H:i:s');

        /* Upsert — one row per iop_med_id */
        $existing = $this->db->get_where('nhis_claim_queue', array('iop_med_id' => $iop_med_id))->row();
        if ($existing) return true;

        return $this->db->insert('nhis_claim_queue', array(
            'prescription_no' => $prescription_no,
            'iop_med_id'      => $iop_med_id,
            'patient_id'      => $patient_no,
            'visit_id'        => $iop_id,
            'drug_name'       => $drug_name,
            'drug_id'         => $drug_id ?: null,
            'quantity'        => $quantity,
            'unit_price'      => $unit_price,
            'status'          => 'PENDING',
            'created_at'      => $now,
        ));
    }

    /* -------------------------------------------------------------------------
     * Audit log writer
     * ----------------------------------------------------------------------- */

    public function audit_log($event_type, array $args)
    {
        if (!$this->db->table_exists('pharmacy_audit_log')) return;

        $this->db->insert('pharmacy_audit_log', array(
            'iop_med_id'      => (int)($args['iop_med_id']      ?? 0),
            'prescription_no' => (string)($args['prescription_no'] ?? ''),
            'iop_id'          => (string)($args['iop_id']        ?? ''),
            'patient_no'      => (string)($args['patient_no']    ?? ''),
            'event_type'      => strtoupper($event_type),
            'old_status'      => isset($args['old_status']) ? (string)$args['old_status'] : null,
            'new_status'      => isset($args['new_status']) ? (string)$args['new_status'] : null,
            'notes'           => isset($args['notes'])       ? substr((string)$args['notes'], 0, 255) : null,
            'performed_by'    => (string)($args['user_id']       ?? ''),
            'performed_at'    => date('Y-m-d H:i:s'),
        ));
    }

    /* -------------------------------------------------------------------------
     * Prescription lifecycle: cancel
     * ----------------------------------------------------------------------- */

    public function cancel_prescription($iop_med_id, $user_id, $reason = '')
    {
        $iop_med_id = (int)$iop_med_id;
        if ($iop_med_id <= 0) return array('ok' => false, 'error' => 'Invalid ID');

        $med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
        if (!$med) return array('ok' => false, 'error' => 'Prescription not found');

		if ($this->db->field_exists('prescription_status', 'iop_medication')) {
			$rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
			if ($rxStatus !== 'PENDING') {
				return array('ok' => false, 'error' => 'Cannot cancel — prescription is locked');
			}
		}

        $dispStatus = isset($med->dispensing_status) ? strtoupper(trim($med->dispensing_status)) : '';
        if ($dispStatus === 'DISPENSED') {
            return array('ok' => false, 'error' => 'Cannot cancel — already dispensed');
        }

        $now = date('Y-m-d H:i:s');
        $this->db->where('iop_med_id', $iop_med_id);
        $this->db->update('iop_medication', array(
            'InActive'         => 1,
            'dispensing_status' => 'CANCELLED',
            'cancelled_at'     => $now,
            'cancelled_by'     => $user_id,
            'cancel_reason'    => substr($reason, 0, 255),
            'updated_at'       => $now,
        ));

        /* Soft-delete from billing queue */
        if ($this->db->table_exists('pharmacy_billing_queue')) {
            $this->db->where('iop_med_id', $iop_med_id);
            $this->db->update('pharmacy_billing_queue', array(
                'payment_status'  => 'CANCELLED',
                'dispense_status' => 'CANCELLED',
                'extended_status' => 'CANCELLED',
                'InActive'        => 1,
                'updated_at'      => $now,
            ));
        }

        /* Cancel NHIS entry if present */
        if ($this->db->table_exists('nhis_claim_queue')) {
            $this->db->where('iop_med_id', $iop_med_id);
            $this->db->update('nhis_claim_queue', array(
                'status'     => 'CANCELLED',
                'InActive'   => 1,
                'updated_at' => $now,
            ));
        }

        $rx_no  = isset($med->prescription_no) ? $med->prescription_no : '';
        $iop_id = isset($med->iop_id)          ? $med->iop_id          : '';
        /* Resolve patient_no from available columns */
        $pat_no = '';
        if (isset($med->patient_no) && $med->patient_no !== '') {
            $pat_no = $med->patient_no;
        } elseif ($iop_id !== '') {
            $visit = $this->db->select('patient_no')->get_where('patient_details_iop', array('IO_ID' => $iop_id))->row();
            if ($visit) $pat_no = $visit->patient_no;
        }
        $this->audit_log('CANCELLED', array(
            'iop_med_id'      => $iop_med_id,
            'prescription_no' => $rx_no,
            'iop_id'          => $iop_id,
            'patient_no'      => $pat_no,
            'old_status'      => $dispStatus ?: 'PENDING',
            'new_status'      => 'CANCELLED',
            'notes'           => $reason,
            'user_id'         => $user_id,
        ));

        return array('ok' => true, 'prescription_no' => $rx_no);
    }

    /* -------------------------------------------------------------------------
     * Prescription lifecycle: update (dose / frequency / days / qty)
     * ----------------------------------------------------------------------- */

    public function update_prescription($iop_med_id, array $fields, $user_id)
    {
        $iop_med_id = (int)$iop_med_id;
        if ($iop_med_id <= 0) return array('ok' => false, 'error' => 'Invalid ID');

        if (function_exists('has_role') && !has_role('doctor')) {
            return array('ok' => false, 'error' => 'Access denied');
        }

        $med = $this->db->get_where('iop_medication', array('iop_med_id' => $iop_med_id, 'InActive' => 0))->row();
        if (!$med) return array('ok' => false, 'error' => 'Prescription not found');

        if ($this->db->field_exists('prescription_status', 'iop_medication')) {
            $rxStatus = isset($med->prescription_status) ? strtoupper(trim((string)$med->prescription_status)) : 'PENDING';
            if ($rxStatus !== 'PENDING') {
                return array('ok' => false, 'error' => 'Cannot edit — prescription is locked');
            }
        }

        $dispStatus = isset($med->dispensing_status) ? strtoupper(trim($med->dispensing_status)) : '';
        if ($dispStatus === 'DISPENSED') {
            return array('ok' => false, 'error' => 'Cannot edit — already dispensed');
        }

        $allowed = array('dosage', 'unit', 'freq_code', 'frequency', 'route', 'drug_form',
                         'days', 'total_qty', 'instruction', 'advice', 'diagnosis_code',
                         'is_prn', 'is_urgent', 'is_nhis_covered');
        $upd = array('updated_at' => date('Y-m-d H:i:s'));
        foreach ($allowed as $f) {
            if (array_key_exists($f, $fields)) {
                $upd[$f] = $fields[$f];
            }
        }

        $this->db->where('iop_med_id', $iop_med_id);
        $this->db->update('iop_medication', $upd);

        /* Re-sync billing queue qty */
        if (isset($upd['total_qty']) && $this->db->table_exists('pharmacy_billing_queue')) {
            $this->db->where('iop_med_id', $iop_med_id);
            $this->db->update('pharmacy_billing_queue', array(
                'quantity'   => (float)$upd['total_qty'],
                'updated_at' => $upd['updated_at'],
            ));
        }

        $rx_no = isset($med->prescription_no) ? $med->prescription_no : '';
        $this->audit_log('UPDATED', array(
            'iop_med_id'      => $iop_med_id,
            'prescription_no' => $rx_no,
            'iop_id'          => isset($med->iop_id) ? $med->iop_id : '',
            'old_status'      => $dispStatus,
            'new_status'      => $dispStatus,
            'notes'           => 'Fields updated: ' . implode(', ', array_keys($fields)),
            'user_id'         => $user_id,
        ));

        return array('ok' => true, 'prescription_no' => $rx_no);
    }

    /* -------------------------------------------------------------------------
     * Get single prescription detail
     * ----------------------------------------------------------------------- */

    public function get_prescription($iop_med_id)
    {
        $iop_med_id = (int)$iop_med_id;
        $this->db->select(
            'M.iop_med_id, M.prescription_no, M.iop_id, M.medicine_id, M.medicine_text, ' .
            'M.dosage, M.unit, M.freq_code, M.frequency, M.route, M.drug_form, ' .
            'M.days, M.total_qty, M.instruction, M.advice, M.diagnosis_code, ' .
            'M.dispensing_status, M.payment_status, M.is_nhis_covered, M.is_prn, M.is_urgent, ' .
            'M.prescribed_by, M.dDate, M.cancelled_at, M.cancel_reason, M.updated_at, ' .
            'D.drug_name, Q.dispense_status, Q.payment_status AS bill_payment_status, ' .
            'Q.unit_price, Q.total AS bill_total', false
        );
        $this->db->from('iop_medication M');
        $this->db->join('medicine_drug_name D', 'D.drug_id = M.medicine_id', 'left');
        $this->db->join('pharmacy_billing_queue Q', 'Q.iop_med_id = M.iop_med_id AND Q.InActive = 0', 'left');
        $this->db->where('M.iop_med_id', $iop_med_id);
        return $this->db->get()->row();
    }

    /* -------------------------------------------------------------------------
     * Get all prescriptions for a visit
     * ----------------------------------------------------------------------- */

    public function get_visit_prescriptions($iop_id, $include_cancelled = false)
    {
        $iop_id = (string)$iop_id;
        $this->db->select(
            'M.iop_med_id, M.prescription_no, M.medicine_id, M.medicine_text, ' .
            'M.dosage, M.unit, M.freq_code, M.frequency, M.route, M.drug_form, ' .
            'M.days, M.total_qty, M.instruction, M.dispensing_status, M.payment_status, ' .
            'M.is_nhis_covered, M.is_urgent, M.is_prn, M.dDate, M.InActive, ' .
            'D.drug_name, Q.dispense_status, Q.payment_status AS bill_pay_status, ' .
            'Q.unit_price, Q.total AS bill_total', false
        );
        $this->db->from('iop_medication M');
        $this->db->join('medicine_drug_name D', 'D.drug_id = M.medicine_id', 'left');
        $this->db->join('pharmacy_billing_queue Q', 'Q.iop_med_id = M.iop_med_id AND Q.InActive = 0', 'left');
        $this->db->where('M.iop_id', $iop_id);
        if (!$include_cancelled) {
            $this->db->where('M.InActive', 0);
        }
        $this->db->order_by('M.dDate', 'DESC');
        return $this->db->get()->result();
    }

    /* -------------------------------------------------------------------------
     * Private helpers
     * ----------------------------------------------------------------------- */

    private function _ensure_index($table, $index_name, $column)
    {
        $existing = $this->db->query(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            array($index_name)
        );
        if ($existing && $existing->num_rows() === 0) {
            $this->db->query("ALTER TABLE `{$table}` ADD INDEX `{$index_name}` (`{$column}`)");
        }
    }
}
