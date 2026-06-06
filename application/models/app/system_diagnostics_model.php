<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class System_diagnostics_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	public function walkin_fulfillment_reconciliation_report($limit = 50)
	{
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 50; }
		if ($limit > 500) { $limit = 500; }

		$out = array(
			'ok' => true,
			'counts' => array(),
			'samples' => array(),
		);

		$has_items = $this->table_exists('walkin_order_items');
		$has_events = $this->table_exists('walkin_fulfillment_events');
		if (!$has_items) {
			$out['ok'] = false;
			$out['error'] = 'walkin_order_items_missing';
			return $out;
		}

		try {
			$sql = "
				SELECT internal_id, walkin_order_id, department, item_ref, quantity, fulfilled_qty, remaining_qty, fulfillment_status
				FROM walkin_order_items
				WHERE InActive = 0
				AND fulfilled_qty > quantity
				ORDER BY internal_id DESC
				LIMIT ?";
			$rows = $this->db->query($sql, array($limit))->result_array();
			$out['counts']['fulfilled_gt_ordered'] = count($rows);
			$out['samples']['fulfilled_gt_ordered'] = $rows;
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'fulfilled_gt_ordered_failed';
		}

		try {
			$sql = "
				SELECT internal_id, walkin_order_id, department, item_ref, quantity, fulfilled_qty, remaining_qty, fulfillment_status
				FROM walkin_order_items
				WHERE InActive = 0
				AND remaining_qty < 0
				ORDER BY internal_id DESC
				LIMIT ?";
			$rows = $this->db->query($sql, array($limit))->result_array();
			$out['counts']['remaining_lt_zero'] = count($rows);
			$out['samples']['remaining_lt_zero'] = $rows;
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'remaining_lt_zero_failed';
		}

		try {
			$sql = "
				SELECT internal_id, walkin_order_id, department, item_ref, quantity, fulfilled_qty, financially_locked, financial_lock_reason
				FROM walkin_order_items
				WHERE InActive = 0
				AND fulfilled_qty > 0
				AND (financially_locked = 0 OR financially_locked IS NULL)
				ORDER BY internal_id DESC
				LIMIT ?";
			$rows = $this->db->query($sql, array($limit))->result_array();
			$out['counts']['fulfilled_but_not_locked'] = count($rows);
			$out['samples']['fulfilled_but_not_locked'] = $rows;
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'fulfilled_but_not_locked_failed';
		}

		try {
			$sql = "
				SELECT internal_id, walkin_order_id, department, item_ref, catalog_ref
				FROM walkin_order_items
				WHERE InActive = 0
				AND UPPER(TRIM(department)) = 'PHARMACY'
				AND (
					catalog_ref IS NULL
					OR TRIM(catalog_ref) = ''
					OR catalog_ref NOT REGEXP '^drug_id:[0-9]+$'
				)
				ORDER BY internal_id DESC
				LIMIT ?";
			$rows = $this->db->query($sql, array($limit))->result_array();
			$out['counts']['pharmacy_invalid_catalog_ref'] = count($rows);
			$out['samples']['pharmacy_invalid_catalog_ref'] = $rows;
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'pharmacy_invalid_catalog_ref_failed';
		}

		if ($has_events) {
			try {
				$sql = "
					SELECT i.internal_id, i.walkin_order_id, i.department, i.item_ref,
						i.quantity, i.fulfilled_qty,
						COALESCE(e.sum_qty,0) AS event_fulfilled_qty
					FROM walkin_order_items i
					LEFT JOIN (
						SELECT walkin_order_item_internal_id, SUM(quantity) AS sum_qty
						FROM walkin_fulfillment_events
						WHERE InActive = 0 AND event_type = 'FULFILL'
						GROUP BY walkin_order_item_internal_id
					) e ON e.walkin_order_item_internal_id = i.internal_id
					WHERE i.InActive = 0
					AND ABS(COALESCE(e.sum_qty,0) - COALESCE(i.fulfilled_qty,0)) > 0.0001
					ORDER BY i.internal_id DESC
					LIMIT ?";
				$rows = $this->db->query($sql, array($limit))->result_array();
				$out['counts']['snapshot_vs_events_mismatch'] = count($rows);
				$out['samples']['snapshot_vs_events_mismatch'] = $rows;
			} catch (Throwable $e) {
				$out['ok'] = false;
				$out['error'] = 'snapshot_vs_events_mismatch_failed';
			}
		}

		// Stock movement evidence (best-effort): requires pharmacy_stock_adjustment and correct reference fields
		if ($this->table_exists('pharmacy_stock_adjustment')) {
			try {
				$sql = "
					SELECT i.internal_id, i.walkin_order_id, i.item_ref, i.catalog_ref, i.fulfilled_qty
					FROM walkin_order_items i
					LEFT JOIN pharmacy_stock_adjustment a
						ON a.InActive = 0
						AND a.reference_type = 'WALKIN_FULFILL'
						AND a.reference_id = i.internal_id
					WHERE i.InActive = 0
					AND UPPER(TRIM(i.department)) = 'PHARMACY'
					AND i.fulfilled_qty > 0
					AND a.adjustment_id IS NULL
					ORDER BY i.internal_id DESC
					LIMIT ?";
				$rows = $this->db->query($sql, array($limit))->result_array();
				$out['counts']['fulfilled_missing_stock_adjustment_evidence'] = count($rows);
				$out['samples']['fulfilled_missing_stock_adjustment_evidence'] = $rows;
			} catch (Throwable $e) {
				$out['ok'] = false;
				$out['error'] = 'fulfilled_missing_stock_adjustment_evidence_failed';
			}
		}

		return $out;
	}

	public function phase2_readiness_report()
	{
		$flags = $this->phase2_flags();

		$tables = array(
			'billing_queue',
			'iop_billing',
			'iop_receipt',
			'financial_ledger',
			'billing_transactions',
			'walkin_orders',
			'walkin_order_items',
		);

		$schema = array();
		foreach ($tables as $t) {
			$schema[$t] = array(
				'exists' => $this->table_exists($t),
				'columns' => array(),
				'indexes' => array(),
			);
		}

		$schema['billing_queue']['columns']['iop_id'] = $this->column_meta('billing_queue', 'iop_id');
		$schema['billing_queue']['columns']['patient_no'] = $this->column_meta('billing_queue', 'patient_no');
		$schema['billing_queue']['columns']['billing_subject_type'] = $this->column_meta('billing_queue', 'billing_subject_type');
		$schema['billing_queue']['columns']['billing_subject_id'] = $this->column_meta('billing_queue', 'billing_subject_id');
		$schema['billing_queue']['columns']['status'] = $this->column_meta('billing_queue', 'status');
		$schema['billing_queue']['columns']['claim_token'] = $this->column_meta('billing_queue', 'claim_token');
		$schema['billing_queue']['columns']['idempotency_key'] = $this->column_meta('billing_queue', 'idempotency_key');
		$schema['billing_queue']['indexes']['idx_bq_subject_status'] = $this->index_exists('billing_queue', 'idx_bq_subject_status');
		$schema['billing_queue']['indexes']['idx_bq_claim_token'] = $this->index_exists('billing_queue', 'idx_bq_claim_token');
		$schema['billing_queue']['indexes']['uq_bq_idem'] = $this->index_exists('billing_queue', 'uq_bq_idem');

		$schema['iop_billing']['columns']['billing_subject_type'] = $this->column_meta('iop_billing', 'billing_subject_type');
		$schema['iop_billing']['columns']['billing_subject_id'] = $this->column_meta('iop_billing', 'billing_subject_id');
		$schema['iop_billing']['columns']['iop_id'] = $this->column_meta('iop_billing', 'iop_id');
		$schema['iop_billing']['columns']['patient_no'] = $this->column_meta('iop_billing', 'patient_no');

		$schema['iop_receipt']['columns']['billing_subject_type'] = $this->column_meta('iop_receipt', 'billing_subject_type');
		$schema['iop_receipt']['columns']['billing_subject_id'] = $this->column_meta('iop_receipt', 'billing_subject_id');
		$schema['iop_receipt']['columns']['iop_id'] = $this->column_meta('iop_receipt', 'iop_id');
		$schema['iop_receipt']['columns']['patient_no'] = $this->column_meta('iop_receipt', 'patient_no');

		$schema['financial_ledger']['columns']['billing_subject_type'] = $this->column_meta('financial_ledger', 'billing_subject_type');
		$schema['financial_ledger']['columns']['billing_subject_id'] = $this->column_meta('financial_ledger', 'billing_subject_id');
		$schema['financial_ledger']['columns']['patient_no'] = $this->column_meta('financial_ledger', 'patient_no');
		$schema['financial_ledger']['indexes']['idx_fl_subject'] = $this->index_exists('financial_ledger', 'idx_fl_subject');

		$schema['billing_transactions']['columns']['patient_no'] = $this->column_meta('billing_transactions', 'patient_no');
		$schema['billing_transactions']['columns']['encounter_id'] = $this->column_meta('billing_transactions', 'encounter_id');
		$schema['billing_transactions']['columns']['billing_subject_type'] = $this->column_meta('billing_transactions', 'billing_subject_type');
		$schema['billing_transactions']['columns']['billing_subject_id'] = $this->column_meta('billing_transactions', 'billing_subject_id');
		$schema['billing_transactions']['indexes']['idx_item_ref_department'] = $this->index_exists('billing_transactions', 'idx_item_ref_department');
		$schema['billing_transactions']['indexes']['idx_subject'] = $this->index_exists('billing_transactions', 'idx_subject');

		$schema['walkin_orders']['columns']['walkin_order_id'] = $this->column_meta('walkin_orders', 'walkin_order_id');
		$schema['walkin_orders']['columns']['billing_subject_type'] = $this->column_meta('walkin_orders', 'billing_subject_type');
		$schema['walkin_orders']['columns']['billing_subject_id'] = $this->column_meta('walkin_orders', 'billing_subject_id');

		$schema['walkin_order_items']['columns']['walkin_order_id'] = $this->column_meta('walkin_order_items', 'walkin_order_id');
		$schema['walkin_order_items']['columns']['fulfillment_status'] = $this->column_meta('walkin_order_items', 'fulfillment_status');
		$schema['walkin_order_items']['columns']['financially_locked'] = $this->column_meta('walkin_order_items', 'financially_locked');
		$schema['walkin_order_items']['columns']['financial_lock_reason'] = $this->column_meta('walkin_order_items', 'financial_lock_reason');
		$schema['walkin_order_items']['columns']['financial_lock_at'] = $this->column_meta('walkin_order_items', 'financial_lock_at');
		$schema['walkin_order_items']['columns']['financial_lock_by'] = $this->column_meta('walkin_order_items', 'financial_lock_by');
		$schema['walkin_order_items']['indexes']['idx_woi_order_fulfillment'] = $this->index_exists('walkin_order_items', 'idx_woi_order_fulfillment');
		$schema['walkin_order_items']['indexes']['idx_woi_dept_fulfillment'] = $this->index_exists('walkin_order_items', 'idx_woi_dept_fulfillment');

		$misc_indexes = array(
			'billing_queue' => array('uq_subject_item_ref'),
			'billing_transactions' => array('uq_subject_item_ref'),
		);
		$optional_indexes = array();
		foreach ($misc_indexes as $t => $names) {
			foreach ($names as $n) {
				$optional_indexes[$t . '.' . $n] = $this->index_exists($t, $n);
			}
		}

		$schema_grade = $this->grade_schema($schema);
		$effective_on = $this->any_phase2_flags_on($flags);
		$overall = $schema_grade;
		if ($effective_on && $schema_grade !== 'GREEN') {
			$overall = 'RED';
		}

		$subject_recon = $this->subject_propagation_report();

		return array(
			'ok' => true,
			'status' => $overall,
			'schema_status' => $schema_grade,
			'flags_on' => $effective_on,
			'flags' => $flags,
			'schema' => $schema,
			'optional_indexes' => $optional_indexes,
			'subject_propagation' => $subject_recon,
		);
	}

	public function phase2_flags()
	{
		$keys = array(
			'WALKIN_SUBJECT_BILLING',
			'WALKIN_ITEMREF_GATE',
			'WALKIN_PHARMACY_QUEUE',
			'WALKIN_LAB_QUEUE',
			'WALKIN_CUSTOM_PRICING',
			'WALKIN_COMPANY_CREDIT_POLICY',
		);
		$out = array();
		foreach ($keys as $k) {
			$v = $this->config->item($k);
			$out[$k] = (bool)$v;
		}
		return $out;
	}

	private function any_phase2_flags_on($flags)
	{
		foreach ($flags as $v) {
			if ($v) return true;
		}
		return false;
	}

	private function grade_schema($schema)
	{
		$critical_missing = array();
		$warn_missing = array();

		$need_tables = array('billing_queue', 'iop_billing', 'iop_receipt', 'billing_transactions', 'walkin_orders', 'walkin_order_items');
		foreach ($need_tables as $t) {
			if (empty($schema[$t]['exists'])) {
				$critical_missing[] = $t;
			}
		}

		if (!empty($critical_missing)) {
			return 'RED';
		}

		$bq = $schema['billing_queue']['columns'];
		$need_bq_cols = array('iop_id', 'patient_no', 'billing_subject_type', 'billing_subject_id', 'status', 'claim_token', 'idempotency_key');
		foreach ($need_bq_cols as $c) {
			if (empty($bq[$c]['exists'])) {
				$critical_missing[] = 'billing_queue.' . $c;
			}
		}
		if (!empty($critical_missing)) {
			return 'RED';
		}

		if (empty($bq['iop_id']['nullable']) || empty($bq['patient_no']['nullable'])) {
			$critical_missing[] = 'billing_queue.nullability';
		}
		if (!empty($critical_missing)) {
			return 'RED';
		}

		$inv = $schema['iop_billing']['columns'];
		if (empty($inv['billing_subject_type']['exists']) || empty($inv['billing_subject_id']['exists'])) {
			$warn_missing[] = 'iop_billing.subject';
		}

		$rcp = $schema['iop_receipt']['columns'];
		if (empty($rcp['billing_subject_type']['exists']) || empty($rcp['billing_subject_id']['exists'])) {
			$warn_missing[] = 'iop_receipt.subject';
		}

		$fl = $schema['financial_ledger']['columns'];
		if (empty($fl['billing_subject_type']['exists']) || empty($fl['billing_subject_id']['exists'])) {
			$warn_missing[] = 'financial_ledger.subject';
		}

		$bt = $schema['billing_transactions']['columns'];
		if (!empty($bt['patient_no']['exists']) && empty($bt['patient_no']['nullable'])) {
			$warn_missing[] = 'billing_transactions.patient_no_not_nullable';
		}
		if (!empty($bt['encounter_id']['exists']) && empty($bt['encounter_id']['nullable'])) {
			$warn_missing[] = 'billing_transactions.encounter_id_not_nullable';
		}

		$woi = $schema['walkin_order_items']['columns'];
		$need_woi_cols = array('financially_locked', 'financial_lock_reason', 'financial_lock_at', 'financial_lock_by');
		foreach ($need_woi_cols as $c) {
			if (empty($woi[$c]['exists'])) {
				$warn_missing[] = 'walkin_order_items.' . $c;
			}
		}

		$need_idx = array(
			'billing_queue' => array('idx_bq_subject_status', 'idx_bq_claim_token', 'uq_bq_idem'),
			'financial_ledger' => array('idx_fl_subject'),
			'billing_transactions' => array('idx_item_ref_department', 'idx_subject'),
			'walkin_order_items' => array('idx_woi_order_fulfillment', 'idx_woi_dept_fulfillment'),
		);
		foreach ($need_idx as $t => $idxs) {
			foreach ($idxs as $idx) {
				if (empty($schema[$t]['indexes'][$idx])) {
					$warn_missing[] = $t . '.' . $idx;
				}
			}
		}

		return empty($warn_missing) ? 'GREEN' : 'YELLOW';
	}

	private function subject_propagation_report($limit = 25)
	{
		$limit = (int)$limit;
		if ($limit <= 0) { $limit = 25; }

		$out = array(
			'ok' => true,
			'counts' => array(),
			'samples' => array(),
		);

		$can_invoice = $this->table_exists('iop_billing')
			&& $this->column_meta('iop_billing', 'billing_subject_type')['exists']
			&& $this->column_meta('iop_billing', 'billing_subject_id')['exists'];
		$can_receipt = $this->table_exists('iop_receipt')
			&& $this->column_meta('iop_receipt', 'billing_subject_type')['exists']
			&& $this->column_meta('iop_receipt', 'billing_subject_id')['exists'];
		$can_ledger = $this->table_exists('financial_ledger')
			&& $this->column_meta('financial_ledger', 'billing_subject_type')['exists']
			&& $this->column_meta('financial_ledger', 'billing_subject_id')['exists'];
		$can_ssot = $this->table_exists('billing_transactions')
			&& $this->column_meta('billing_transactions', 'billing_subject_type')['exists']
			&& $this->column_meta('billing_transactions', 'billing_subject_id')['exists'];

		$out['capabilities'] = array(
			'iop_billing' => $can_invoice,
			'iop_receipt' => $can_receipt,
			'financial_ledger' => $can_ledger,
			'billing_transactions' => $can_ssot,
		);

		if (!$can_invoice) {
			return $out;
		}

		try {
			if ($can_ledger) {
				$sql = "
					SELECT b.invoice_no, b.billing_subject_type, b.billing_subject_id,
						COUNT(fl.ledger_id) AS ledger_rows,
						SUM(CASE
							WHEN fl.ledger_id IS NULL THEN 1
							WHEN COALESCE(fl.billing_subject_type,'') <> COALESCE(b.billing_subject_type,'') THEN 1
							WHEN COALESCE(fl.billing_subject_id,'') <> COALESCE(b.billing_subject_id,'') THEN 1
							ELSE 0 END
						) AS bad_rows
					FROM iop_billing b
					LEFT JOIN financial_ledger fl
						ON fl.reference_type = 'INVOICE'
						AND fl.reference_no = b.invoice_no
					WHERE b.InActive = 0
					AND COALESCE(b.billing_subject_type,'') <> ''
					AND COALESCE(b.billing_subject_id,'') <> ''
					GROUP BY b.invoice_no, b.billing_subject_type, b.billing_subject_id
					HAVING ledger_rows = 0 OR bad_rows > 0
					ORDER BY b.invoice_no DESC
					LIMIT ?";
				$rows = $this->db->query($sql, array($limit))->result_array();
				$out['counts']['invoice_subject_missing_or_mismatch_ledger'] = count($rows);
				$out['samples']['invoice_subject_missing_or_mismatch_ledger'] = $rows;
			}
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'ledger_recon_failed';
		}

		try {
			if ($can_receipt) {
				$sql = "
					SELECT r.receipt_no, r.invoice_no, r.billing_subject_type, r.billing_subject_id
					FROM iop_receipt r
					JOIN iop_billing b ON b.invoice_no = r.invoice_no AND b.InActive = 0
					WHERE r.InActive = 0
					AND COALESCE(b.billing_subject_type,'') <> ''
					AND COALESCE(b.billing_subject_id,'') <> ''
					AND (
						COALESCE(r.billing_subject_type,'') = ''
						OR COALESCE(r.billing_subject_id,'') = ''
						OR COALESCE(r.billing_subject_type,'') <> COALESCE(b.billing_subject_type,'')
						OR COALESCE(r.billing_subject_id,'') <> COALESCE(b.billing_subject_id,'')
					)
					ORDER BY r.receipt_no DESC
					LIMIT ?";
				$rows = $this->db->query($sql, array($limit))->result_array();
				$out['counts']['invoice_subject_mismatch_receipt_subject'] = count($rows);
				$out['samples']['invoice_subject_mismatch_receipt_subject'] = $rows;
			}
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'receipt_recon_failed';
		}

		try {
			if ($can_ssot && $can_receipt) {
				$sql = "
					SELECT r.receipt_no, r.invoice_no, r.billing_subject_type, r.billing_subject_id,
						COUNT(bt.txn_id) AS ssot_rows,
						SUM(CASE
							WHEN bt.txn_id IS NULL THEN 1
							WHEN COALESCE(bt.billing_subject_type,'') = '' THEN 1
							WHEN COALESCE(bt.billing_subject_id,'') = '' THEN 1
							WHEN COALESCE(bt.billing_subject_type,'') <> COALESCE(r.billing_subject_type,'') THEN 1
							WHEN COALESCE(bt.billing_subject_id,'') <> COALESCE(r.billing_subject_id,'') THEN 1
							ELSE 0 END
						) AS bad_rows
					FROM iop_receipt r
					LEFT JOIN billing_transactions bt
						ON bt.invoice_no = r.invoice_no
						AND bt.InActive = 0
					WHERE r.InActive = 0
					AND COALESCE(r.billing_subject_type,'') <> ''
					AND COALESCE(r.billing_subject_id,'') <> ''
					GROUP BY r.receipt_no, r.invoice_no, r.billing_subject_type, r.billing_subject_id
					HAVING ssot_rows = 0 OR bad_rows > 0
					ORDER BY r.receipt_no DESC
					LIMIT ?";
				$rows = $this->db->query($sql, array($limit))->result_array();
				$out['counts']['receipt_subject_missing_or_mismatch_ssot'] = count($rows);
				$out['samples']['receipt_subject_missing_or_mismatch_ssot'] = $rows;
			}
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'ssot_receipt_recon_failed';
		}

		try {
			if ($can_ssot) {
				$sql = "
					SELECT bt.invoice_no,
						MAX(bt.billing_subject_type) AS billing_subject_type,
						MAX(bt.billing_subject_id) AS billing_subject_id
					FROM billing_transactions bt
					LEFT JOIN iop_billing b ON b.invoice_no = bt.invoice_no AND b.InActive = 0
					WHERE bt.InActive = 0
					AND COALESCE(bt.invoice_no,'') <> ''
					AND COALESCE(bt.billing_subject_type,'') <> ''
					AND COALESCE(bt.billing_subject_id,'') <> ''
					AND (b.invoice_no IS NULL OR COALESCE(b.billing_subject_type,'') = '' OR COALESCE(b.billing_subject_id,'') = '')
					GROUP BY bt.invoice_no
					ORDER BY bt.invoice_no DESC
					LIMIT ?";
				$rows = $this->db->query($sql, array($limit))->result_array();
				$out['counts']['ssot_subject_without_invoice_subject'] = count($rows);
				$out['samples']['ssot_subject_without_invoice_subject'] = $rows;
			}
		} catch (Throwable $e) {
			$out['ok'] = false;
			$out['error'] = 'ssot_invoice_recon_failed';
		}

		return $out;
	}

	private function table_exists($table)
	{
		$table = trim((string)$table);
		if ($table === '') return false;
		try {
			return (bool)$this->db->table_exists($table);
		} catch (Throwable $e) {
			return false;
		}
	}

	private function column_meta($table, $column)
	{
		$table = trim((string)$table);
		$column = trim((string)$column);
		if ($table === '' || $column === '' || !$this->table_exists($table)) {
			return array('exists' => false, 'nullable' => null, 'type' => null, 'default' => null);
		}

		try {
			$q = $this->db->query("SHOW COLUMNS FROM `" . $this->db->escape_str($table) . "` LIKE " . $this->db->escape($column));
			$row = $q ? $q->row() : null;
			if (!$row) {
				return array('exists' => false, 'nullable' => null, 'type' => null, 'default' => null);
			}
			$nullable = isset($row->Null) ? (strtoupper((string)$row->Null) === 'YES') : null;
			$type = isset($row->Type) ? (string)$row->Type : null;
			$def = isset($row->Default) ? $row->Default : null;
			return array('exists' => true, 'nullable' => $nullable, 'type' => $type, 'default' => $def);
		} catch (Throwable $e) {
			return array('exists' => false, 'nullable' => null, 'type' => null, 'default' => null);
		}
	}

	private function index_exists($table, $index)
	{
		$table = trim((string)$table);
		$index = trim((string)$index);
		if ($table === '' || $index === '' || !$this->table_exists($table)) {
			return false;
		}
		try {
			$q = $this->db->query("SHOW INDEX FROM `" . $this->db->escape_str($table) . "` WHERE Key_name = " . $this->db->escape($index));
			return ($q && $q->num_rows() > 0);
		} catch (Throwable $e) {
			return false;
		}
	}
}
