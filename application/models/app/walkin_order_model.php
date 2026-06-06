<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Walkin_order_model extends CI_Model
{
	public function __construct()
	{
		parent::__construct();
	}

	private function _generate_walkin_order_id()
	{
		$base = date('YmdHis') . '|' . microtime(true) . '|' . uniqid('', true);
		$id = 'WO' . date('YmdHis') . substr(sha1($base), 0, 20);
		return substr($id, 0, 40);
	}

	private function _walkin_transaction_type($items)
	{
		$has_pharmacy = false;
		$has_lab = false;
		$has_other = false;
		foreach ((array)$items as $it) {
			$dept = isset($it['department']) ? strtoupper(trim((string)$it['department'])) : '';
			if ($dept === 'PHARMACY') $has_pharmacy = true;
			elseif ($dept === 'LAB' || $dept === 'LABORATORY') $has_lab = true;
			else $has_other = true;
		}
		$count = ($has_pharmacy ? 1 : 0) + ($has_lab ? 1 : 0) + ($has_other ? 1 : 0);
		if ($count > 1) return 'WALKIN-MIXED';
		if ($has_pharmacy) return 'WALKIN-PHARMACY';
		if ($has_lab) return 'WALKIN-LAB';
		return 'WALKIN-SERVICE';
	}

	private function _initial_department_status($department)
	{
		$dept = strtoupper(trim((string)$department));
		if ($dept === 'PHARMACY') return 'UNPAID';
		if ($dept === 'LAB' || $dept === 'LABORATORY') return 'UNPAID';
		return 'UNPAID';
	}

	private function _paid_department_status($department)
	{
		$dept = strtoupper(trim((string)$department));
		if ($dept === 'PHARMACY') return 'PAID_UNFULFILLED';
		if ($dept === 'LAB' || $dept === 'LABORATORY') return 'PAID_PENDING_COLLECTION';
		return 'PAID_UNFULFILLED';
	}

	private function _queue_item_type_from_department($department, $fallback_item_type = null)
	{
		$dept = strtoupper(trim((string)$department));
		$it = strtoupper(trim((string)$fallback_item_type));
		if ($dept === 'PHARMACY') return 'PHARMACY';
		if ($dept === 'LAB' || $dept === 'LABORATORY') return 'LAB';
		if ($dept === 'IMAGING' || $dept === 'RADIOLOGY') return 'RADIOLOGY';
		if ($dept === 'SONOGRAPHY') return 'SONOGRAPHY';
		if ($it !== '') {
			$allowed = array('CONSULTATION','REGISTRATION','LAB','PHARMACY','SONOGRAPHY','RADIOLOGY','PROCEDURE','ADMISSION','SURGERY','ROOM','SUPPLY','OTHER');
			if (in_array($it, $allowed, true)) return $it;
		}
		return 'OTHER';
	}

	public function create_walkin_order_with_items($header, $items, $actor = null)
	{
		$actor = $actor === null ? null : trim((string)$actor);
		if (!is_array($header)) {
			return array('success' => false, 'error' => 'Invalid header');
		}
		if (!is_array($items) || count($items) === 0) {
			return array('success' => false, 'error' => 'No items');
		}

		$this->ensure_walkin_schema();
		$this->load->model('app/unified_billing_model');
		$this->unified_billing_model->ensure_unified_billing_schema();

		if (!$this->table_exists('walkin_orders') || !$this->table_exists('walkin_order_items') || !$this->table_exists('billing_queue')) {
			return array('success' => false, 'error' => 'Schema not ready');
		}
		if (!$this->column_exists('billing_queue', 'billing_subject_type') || !$this->column_exists('billing_queue', 'billing_subject_id')) {
			return array('success' => false, 'error' => 'Subject queue columns missing');
		}

		$payer_type = isset($header['payer_type']) ? strtoupper(trim((string)$header['payer_type'])) : 'CASH';
		if ($payer_type !== 'CASH' && $payer_type !== 'COMPANY') {
			$payer_type = 'CASH';
		}
		$company_id = isset($header['company_id']) ? (int)$header['company_id'] : null;
		if ($payer_type !== 'COMPANY') {
			$company_id = null;
		}

		$walkin_order_id = $this->_generate_walkin_order_id();
		$now = date('Y-m-d H:i:s');
		$gross_amount = 0.0;
		$discount_amount = 0.0;
		$net_amount = 0.0;

		$clean_items = array();
		foreach ($items as $it) {
			if (!is_array($it)) continue;
			$department = isset($it['department']) ? trim((string)$it['department']) : '';
			$item_type = isset($it['item_type']) ? trim((string)$it['item_type']) : '';
			$item_name = isset($it['item_name']) ? trim((string)$it['item_name']) : '';
			$catalog_ref = isset($it['catalog_ref']) ? trim((string)$it['catalog_ref']) : '';
			$quantity = isset($it['quantity']) ? (float)$it['quantity'] : 1.0;
			$unit_price = isset($it['unit_price']) ? (float)$it['unit_price'] : 0.0;
			$disc = isset($it['discount_amount']) ? (float)$it['discount_amount'] : 0.0;
			$pricing_source_type = isset($it['pricing_source_type']) ? strtoupper(trim((string)$it['pricing_source_type'])) : 'CATALOG';
			$pricing_reason = isset($it['pricing_reason']) ? (string)$it['pricing_reason'] : null;

			if ($department === '' || $item_name === '' || $quantity <= 0) continue;
			if ($unit_price < 0) $unit_price = 0;
			if ($disc < 0) $disc = 0;

			if (strtoupper($department) === 'PHARMACY') {
				if (!preg_match('/^drug_id\s*:\s*(\d+)$/i', $catalog_ref, $m)) {
					return array('success' => false, 'error' => 'Invalid catalog_ref (expected drug_id:<id>)');
				}
				$drug_id = (int)$m[1];
				if ($drug_id <= 0) {
					return array('success' => false, 'error' => 'Invalid catalog_ref (expected drug_id:<id>)');
				}
				$catalog_ref = 'drug_id:' . $drug_id;
			}

			$gross = round($quantity * $unit_price, 2);
			$net = round($gross - $disc, 2);
			if ($net < 0) $net = 0;

			$gross_amount += $gross;
			$discount_amount += $disc;
			$net_amount += $net;

			$clean_items[] = array(
				'department' => $department,
				'item_type' => $item_type,
				'catalog_ref' => $catalog_ref,
				'item_name' => $item_name,
				'quantity' => $quantity,
				'unit_price' => $unit_price,
				'gross_amount' => $gross,
				'discount_amount' => $disc,
				'net_amount' => $net,
				'pricing_source_type' => $pricing_source_type,
				'pricing_reason' => $pricing_reason,
			);
		}

		if (count($clean_items) === 0) {
			return array('success' => false, 'error' => 'No valid items');
		}

		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) {
			$this->db->db_debug = false;
		}
		try {
			$this->db->trans_begin();

			$order_insert = array(
				'walkin_order_id' => $walkin_order_id,
				'walkin_code' => isset($header['walkin_code']) && trim((string)$header['walkin_code']) !== '' ? trim((string)$header['walkin_code']) : $walkin_order_id,
				'walkin_client_id' => isset($header['walkin_client_id']) ? (int)$header['walkin_client_id'] : null,
				'customer_name' => isset($header['customer_name']) ? trim((string)$header['customer_name']) : null,
				'phone' => isset($header['phone']) ? trim((string)$header['phone']) : null,
				'gender' => isset($header['gender']) ? trim((string)$header['gender']) : null,
				'transaction_type' => isset($header['transaction_type']) && trim((string)$header['transaction_type']) !== '' ? trim((string)$header['transaction_type']) : $this->_walkin_transaction_type($clean_items),
				'payer_type' => $payer_type,
				'company_id' => $company_id,
				'company_authorization_note' => isset($header['company_authorization_note']) ? (string)$header['company_authorization_note'] : null,
				'company_authorized_by' => isset($header['company_authorized_by']) ? (string)$header['company_authorized_by'] : null,
				'company_payment_terms' => isset($header['company_payment_terms']) ? (string)$header['company_payment_terms'] : null,
				'currency' => isset($header['currency']) && trim((string)$header['currency']) !== '' ? trim((string)$header['currency']) : 'GHS',
				'gross_amount' => (float)$gross_amount,
				'discount_amount' => (float)$discount_amount,
				'net_amount' => (float)$net_amount,
				'paid_amount' => 0.0,
				'balance_amount' => (float)$net_amount,
				'payment_status' => 'DRAFT',
				'fulfillment_status' => 'UNFULFILLED',
				'invoice_no' => null,
				'receipt_no' => null,
				'notes' => isset($header['notes']) ? (string)$header['notes'] : null,
				'created_by' => $actor,
				'created_at' => $now,
				'InActive' => 0,
			);
			if ($this->column_exists('walkin_orders', 'billing_subject_type')) {
				$order_insert['billing_subject_type'] = 'WALKIN_ORDER';
			}
			if ($this->column_exists('walkin_orders', 'billing_subject_id')) {
				$order_insert['billing_subject_id'] = $walkin_order_id;
			}
			$this->db->insert('walkin_orders', $order_insert);
			if ((int)$this->db->affected_rows() <= 0) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Failed to create order');
			}

			$item_ids = array();
			$queue_ids = array();
			foreach ($clean_items as $ci) {
				$item_insert = array(
					'walkin_order_id' => $walkin_order_id,
					'department' => (string)$ci['department'],
					'item_type' => (string)$ci['item_type'],
					'catalog_ref' => (string)$ci['catalog_ref'],
					'item_ref' => null,
					'item_name' => (string)$ci['item_name'],
					'quantity' => (float)$ci['quantity'],
					'unit_price' => (float)$ci['unit_price'],
					'gross_amount' => (float)$ci['gross_amount'],
					'discount_amount' => (float)$ci['discount_amount'],
					'net_amount' => (float)$ci['net_amount'],
					'pricing_source_type' => (string)$ci['pricing_source_type'],
					'pricing_reason' => $ci['pricing_reason'],
					'fulfilled_qty' => 0.0,
					'remaining_qty' => (float)$ci['quantity'],
					'fulfillment_status' => 'UNFULFILLED',
					'department_status' => $this->_initial_department_status($ci['department']),
					'last_fulfilled_at' => null,
					'financially_locked' => 0,
					'financial_lock_reason' => null,
					'financial_lock_at' => null,
					'financial_lock_by' => null,
					'notes' => null,
					'created_by' => $actor,
					'created_at' => $now,
					'InActive' => 0,
				);
				$this->db->insert('walkin_order_items', $item_insert);
				$item_internal_id = (int)$this->db->insert_id();
				if ($item_internal_id <= 0) {
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'Failed to create order item');
				}
				$item_ref = 'walkin_order_item_id:' . $item_internal_id;
				$this->db->where('internal_id', $item_internal_id);
				$this->db->where('InActive', 0);
				$this->db->update('walkin_order_items', array('item_ref' => $item_ref));
				if ((int)$this->db->affected_rows() <= 0) {
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'Failed to stamp item_ref');
				}

				// Queue insertion (idempotent by source_module/source_ref)
				$existing_q = null;
				$this->db->where('source_module', 'WALKIN_ORDER_ITEM');
				$this->db->where('source_ref', $item_ref);
				$this->db->where('InActive', 0);
				$existing_q = $this->db->get('billing_queue')->row();
				if ($existing_q && isset($existing_q->queue_id)) {
					$existing_id = (int)$existing_q->queue_id;
					$existing_status = isset($existing_q->status) ? strtoupper(trim((string)$existing_q->status)) : '';
					if ($existing_id > 0 && ($existing_status === '' || $existing_status === 'PENDING')) {
						$item_ids[] = $item_internal_id;
						$queue_ids[] = $existing_id;
						continue;
					}
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'Item already in billing queue');
				}

				$queue_insert = array(
					'iop_id' => null,
					'patient_no' => null,
					'billing_subject_type' => 'WALKIN_ORDER',
					'billing_subject_id' => $walkin_order_id,
					'item_type' => $this->_queue_item_type_from_department($ci['department'], $ci['item_type']),
					'item_id' => (string)$item_internal_id,
					'item_name' => (string)$ci['item_name'],
					'quantity' => (float)$ci['quantity'],
					'unit_price' => (float)$ci['unit_price'],
					'total_amount' => (float)$ci['gross_amount'],
					'discount_amount' => (float)$ci['discount_amount'],
					'net_amount' => (float)$ci['net_amount'],
					'payer_type' => ($payer_type === 'COMPANY' ? 'COMPANY' : 'CASH'),
					'coverage_amount' => 0.0,
					'patient_amount' => (float)$ci['net_amount'],
					'status' => 'PENDING',
					'invoice_no' => null,
					'source_module' => 'WALKIN_ORDER_ITEM',
					'source_ref' => $item_ref,
					'requested_by' => $actor,
					'requested_at' => $now,
					'InActive' => 0,
				);
				if ($this->column_exists('billing_queue', 'created_at')) {
					$queue_insert['created_at'] = $now;
				}
				if ($this->column_exists('billing_queue', 'updated_at')) {
					$queue_insert['updated_at'] = $now;
				}
				$ok = $this->db->insert('billing_queue', $queue_insert);
				if (!$ok) {
					// Most likely a UNIQUE violation (uq_bq_source). Attempt idempotent recovery.
					$this->db->where('source_module', 'WALKIN_ORDER_ITEM');
					$this->db->where('source_ref', $item_ref);
					$this->db->where('InActive', 0);
					$existing2 = $this->db->get('billing_queue')->row();
					if ($existing2 && isset($existing2->queue_id)) {
						$existing_id2 = (int)$existing2->queue_id;
						$existing_status2 = isset($existing2->status) ? strtoupper(trim((string)$existing2->status)) : '';
						if ($existing_id2 > 0 && ($existing_status2 === '' || $existing_status2 === 'PENDING')) {
							$item_ids[] = $item_internal_id;
							$queue_ids[] = $existing_id2;
							continue;
						}
					}
					$err = $this->db->error();
					$msg = (isset($err['message']) && $err['message'] !== '') ? (string)$err['message'] : 'Failed to enqueue';
					$this->db->trans_rollback();
					return array('success' => false, 'error' => $msg);
				}
				$queue_id = (int)$this->db->insert_id();
				$item_ids[] = $item_internal_id;
				$queue_ids[] = $queue_id;
			}

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Create order failed');
			}
			$this->db->trans_commit();
			return array(
				'success' => true,
				'walkin_order_id' => $walkin_order_id,
				'item_internal_ids' => $item_ids,
				'queue_ids' => $queue_ids,
				'net_amount' => (float)$net_amount,
			);
		} catch (\Throwable $e) {
			try { $this->db->trans_rollback(); } catch (\Throwable $e2) {}
			return array('success' => false, 'error' => 'Create order failed');
		} finally {
			if ($prev_debug !== null) {
				$this->db->db_debug = $prev_debug;
			}
		}
	}

	private function _parse_int_from_ref($ref)
	{
		$ref = trim((string)$ref);
		if ($ref === '') return 0;
		if (ctype_digit($ref)) return (int)$ref;
		if (preg_match('/^(drug_id|medicine_id|medication_id|drug)\s*:\s*(\d+)$/i', $ref, $m)) {
			return (int)$m[2];
		}
		return 0;
	}

	public function fulfill_walkin_order_item($walkin_order_item_internal_id, $qty, $actor = null, $idempotency_key = null, $notes = null)
	{
		$walkin_order_item_internal_id = (int)$walkin_order_item_internal_id;
		$qty = (float)$qty;
		$actor = $actor === null ? null : trim((string)$actor);
		$idempotency_key = $idempotency_key === null ? null : trim((string)$idempotency_key);
		$notes = $notes === null ? null : (string)$notes;

		if ($walkin_order_item_internal_id <= 0) {
			return array('success' => false, 'error' => 'Invalid order item id');
		}
		if ($qty <= 0) {
			return array('success' => false, 'error' => 'Invalid quantity');
		}
		if (!$this->table_exists('walkin_order_items') || !$this->table_exists('walkin_fulfillment_events')) {
			return array('success' => false, 'error' => 'Walk-in fulfillment schema not ready');
		}

		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) {
			$this->db->db_debug = false;
		}
		try {
			$this->db->trans_begin();

			if ($idempotency_key !== null && $idempotency_key !== '') {
				$existing = $this->db->get_where('walkin_fulfillment_events', array('idempotency_key' => $idempotency_key, 'InActive' => 0))->row();
				if ($existing) {
					$item = $this->db->query("SELECT * FROM walkin_order_items WHERE internal_id = ? AND InActive = 0", array($walkin_order_item_internal_id))->row();
					$this->db->trans_rollback();
					return array('success' => true, 'replay' => true, 'event_id' => (int)$existing->event_id, 'item' => $item);
				}
			}

			$item = $this->db->query(
				"SELECT I.*, O.payment_status AS order_payment_status, O.receipt_no AS order_receipt_no
				 FROM walkin_order_items I
				 INNER JOIN walkin_orders O ON O.walkin_order_id = I.walkin_order_id AND O.InActive = 0
				 WHERE I.internal_id = ? AND I.InActive = 0 FOR UPDATE",
				array($walkin_order_item_internal_id)
			)->row();
			if (!$item) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Order item not found');
			}

			$st = isset($item->fulfillment_status) ? strtoupper(trim((string)$item->fulfillment_status)) : 'UNFULFILLED';
			if ($st === 'CANCELLED') {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Item is cancelled');
			}
			$orderPay = isset($item->order_payment_status) ? strtoupper(trim((string)$item->order_payment_status)) : '';
			if ($orderPay !== 'PAID') {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Payment authorization required before fulfillment', 'blocked_reason' => 'PAYMENT_PENDING');
			}

			$total_qty = isset($item->quantity) ? (float)$item->quantity : 0.0;
			$fulfilled_qty = isset($item->fulfilled_qty) ? (float)$item->fulfilled_qty : 0.0;
			$remaining_qty = max(0.0, $total_qty - $fulfilled_qty);
			if ($remaining_qty <= 0) {
				$this->db->trans_rollback();
				return array('success' => true, 'already_fulfilled' => true, 'item' => $item);
			}
			if ($qty > $remaining_qty) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Insufficient remaining qty');
			}

			$department = isset($item->department) ? trim((string)$item->department) : '';
			$item_type = isset($item->item_type) ? trim((string)$item->item_type) : '';
			$item_ref = isset($item->item_ref) ? trim((string)$item->item_ref) : '';
			$catalog_ref = isset($item->catalog_ref) ? trim((string)$item->catalog_ref) : '';

			if ($item_ref !== '') {
				$this->load->model('app/service_gate_model', 'service_gate');
				$gate = null;
				if (isset($this->service_gate) && method_exists($this->service_gate, 'check_service_by_item_ref_raw')) {
					$gate = $this->service_gate->check_service_by_item_ref_raw($item_ref);
				} elseif (isset($this->service_gate) && method_exists($this->service_gate, 'check_service_by_item_ref')) {
					$gate = $this->service_gate->check_service_by_item_ref($item_ref, true);
				}
				if (is_array($gate) && array_key_exists('allowed', $gate) && !$gate['allowed']) {
					$this->db->trans_rollback();
					$reason = isset($gate['reason']) ? (string)$gate['reason'] : 'Payment required';
					$blocked = isset($gate['blocked_reason']) ? (string)$gate['blocked_reason'] : 'PAYMENT_PENDING';
					return array('success' => false, 'error' => $reason, 'blocked_reason' => $blocked, 'gate' => $gate);
				}
			}

			$drug_id = 0;
			if ($department !== '' && strtoupper($department) === 'PHARMACY') {
				$drug_id = $this->_parse_int_from_ref($catalog_ref);
				if ($drug_id <= 0) {
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'Invalid catalog_ref (expected drug_id:<id>)');
				}

				$hasBatchTable = $this->table_exists('medication_stock');
				$hasQtyRemaining = $hasBatchTable && $this->db->field_exists('quantity_remaining', 'medication_stock');
				$hasQty = $hasBatchTable && $this->db->field_exists('quantity', 'medication_stock');
				$today = date('Y-m-d');

				if ($hasQtyRemaining || $hasQty) {
					$stock_before = null;
					if ($this->table_exists('medicine_drug_name')) {
						try {
							$r0 = $this->db->query("SELECT nStock FROM medicine_drug_name WHERE drug_id = ? AND InActive = 0 LIMIT 1", array($drug_id))->row();
							if ($r0 && isset($r0->nStock)) {
								$stock_before = (float)$r0->nStock;
							}
						} catch (\Throwable $e0) {
							$stock_before = null;
						}
					}

					$col = $hasQtyRemaining ? 'quantity_remaining' : 'quantity';
					$batches = $this->db->query(
						"SELECT stock_id, batch_number, expiry_date, {$col} AS qty FROM medication_stock " .
						"WHERE medication_id = ? AND InActive = 0 " .
						($hasQtyRemaining ? "AND status = 'ACTIVE' " : "") .
						"AND {$col} > 0 " .
						"AND (expiry_date IS NULL OR expiry_date >= ?) " .
						"ORDER BY expiry_date ASC, created_at ASC FOR UPDATE",
						array($drug_id, $today)
					)->result();

					$need = (float)$qty;
					foreach ($batches as $b) {
						if ($need <= 0) break;
						$avail = isset($b->qty) ? (float)$b->qty : 0.0;
						if ($avail <= 0) continue;
						$take = min($avail, $need);
						if ($take <= 0) continue;

						$this->db->set($col, $col . ' - ' . (float)$take, false);
						$this->db->where('stock_id', (int)$b->stock_id);
						$this->db->where($col . ' >=', (float)$take);
						$this->db->update('medication_stock');
						if ((int)$this->db->affected_rows() <= 0) {
							$this->db->trans_rollback();
							return array('success' => false, 'error' => 'Insufficient stock');
						}
						if ($this->table_exists('walkin_inventory_ledger')) {
							$this->db->insert('walkin_inventory_ledger', array(
								'walkin_order_id' => (string)$item->walkin_order_id,
								'walkin_order_item_internal_id' => (int)$walkin_order_item_internal_id,
								'drug_id' => (int)$drug_id,
								'stock_id' => isset($b->stock_id) ? (int)$b->stock_id : null,
								'batch_number' => isset($b->batch_number) ? (string)$b->batch_number : null,
								'expiry_date' => isset($b->expiry_date) ? $b->expiry_date : null,
								'movement_type' => 'DISPENSE',
								'quantity' => 0 - (float)$take,
								'stock_before' => (float)$avail,
								'stock_after' => (float)($avail - $take),
								'reference_type' => 'WALKIN_FULFILL',
								'reference_id' => 'walkin_order_item_id:' . (int)$walkin_order_item_internal_id,
								'created_by' => $actor,
								'created_at' => date('Y-m-d H:i:s'),
								'InActive' => 0,
							));
						}
						$need -= $take;
					}

					if ($need > 0) {
						$this->db->trans_rollback();
						return array('success' => false, 'error' => 'Insufficient stock');
					}

					if ($this->table_exists('medicine_drug_name')) {
						$sum = $this->db->query(
							"SELECT COALESCE(SUM({$col}),0) AS total FROM medication_stock " .
							"WHERE medication_id = ? AND InActive = 0 " .
							"AND ({$col} > 0) " .
							"AND (expiry_date IS NULL OR expiry_date >= ?)",
							array($drug_id, $today)
						)->row();
						$total = $sum ? (float)$sum->total : 0.0;
						$this->db->where('drug_id', (int)$drug_id);
						$this->db->update('medicine_drug_name', array('nStock' => $total));

						// Best-effort stock movement evidence
						if ($this->table_exists('pharmacy_stock_adjustment')) {
							try {
								$ins = array(
									'drug_id' => (int)$drug_id,
									'adjustment_type' => 'DISPENSE',
									'qty_change' => 0 - (float)$qty,
									'stock_before' => ($stock_before === null ? null : (float)$stock_before),
									'stock_after' => (float)$total,
									'reason' => 'Walk-in fulfillment (batch)',
									'reference_type' => 'WALKIN_FULFILL',
									'reference_id' => (int)$walkin_order_item_internal_id,
									'created_by' => $actor,
									'created_at' => date('Y-m-d H:i:s'),
									'InActive' => 0,
								);
								if (!$this->column_exists('pharmacy_stock_adjustment', 'stock_before')) {
									unset($ins['stock_before']);
								}
								if (!$this->column_exists('pharmacy_stock_adjustment', 'stock_after')) {
									unset($ins['stock_after']);
								}
								if (!$this->column_exists('pharmacy_stock_adjustment', 'reference_type')) {
									unset($ins['reference_type']);
								}
								if (!$this->column_exists('pharmacy_stock_adjustment', 'reference_id')) {
									unset($ins['reference_id']);
								}
								$this->db->insert('pharmacy_stock_adjustment', $ins);
							} catch (\Throwable $e2) {
							}
						}
					}
				} else {
					$this->load->model('app/pharmacy_model');
					$stock_before_simple = null;
					if ($this->table_exists('medicine_drug_name')) {
						$rSimple = $this->db->query("SELECT nStock FROM medicine_drug_name WHERE drug_id = ? AND InActive = 0 LIMIT 1 FOR UPDATE", array($drug_id))->row();
						if ($rSimple && isset($rSimple->nStock)) $stock_before_simple = (float)$rSimple->nStock;
					}
					$ok = $this->pharmacy_model->deduct_stock($drug_id, (float)$qty, $actor, 'WALKIN_FULFILL', $walkin_order_item_internal_id);
					if (!$ok) {
						$this->db->trans_rollback();
						return array('success' => false, 'error' => 'Insufficient stock');
					}
					if ($this->table_exists('walkin_inventory_ledger')) {
						$stock_after_simple = null;
						if ($this->table_exists('medicine_drug_name')) {
							$rAfter = $this->db->query("SELECT nStock FROM medicine_drug_name WHERE drug_id = ? AND InActive = 0 LIMIT 1", array($drug_id))->row();
							if ($rAfter && isset($rAfter->nStock)) $stock_after_simple = (float)$rAfter->nStock;
						}
						$this->db->insert('walkin_inventory_ledger', array(
							'walkin_order_id' => (string)$item->walkin_order_id,
							'walkin_order_item_internal_id' => (int)$walkin_order_item_internal_id,
							'drug_id' => (int)$drug_id,
							'stock_id' => null,
							'batch_number' => null,
							'expiry_date' => null,
							'movement_type' => 'DISPENSE',
							'quantity' => 0 - (float)$qty,
							'stock_before' => $stock_before_simple,
							'stock_after' => $stock_after_simple,
							'reference_type' => 'WALKIN_FULFILL',
							'reference_id' => 'walkin_order_item_id:' . (int)$walkin_order_item_internal_id,
							'created_by' => $actor,
							'created_at' => date('Y-m-d H:i:s'),
							'InActive' => 0,
						));
					}
				}
			}
			$dept_upper = strtoupper(trim((string)$department));
			if ($dept_upper === 'LAB' || $dept_upper === 'LABORATORY') {
				$labStatus = isset($item->department_status) ? strtoupper(trim((string)$item->department_status)) : '';
				if (!in_array($labStatus, array('PAID_PENDING_COLLECTION','SAMPLE_COLLECTED','IN_PROGRESS'), true)) {
					$this->db->trans_rollback();
					return array('success' => false, 'error' => 'Lab item is not ready for completion');
				}
			}

			$this->db->insert('walkin_fulfillment_events', array(
				'walkin_order_id' => (string)$item->walkin_order_id,
				'walkin_order_item_internal_id' => (int)$walkin_order_item_internal_id,
				'department' => (string)$department,
				'item_ref' => (string)$item_ref,
				'event_type' => 'FULFILL',
				'quantity' => (float)$qty,
				'idempotency_key' => $idempotency_key,
				'notes' => $notes,
				'created_by' => $actor,
				'created_at' => date('Y-m-d H:i:s'),
				'InActive' => 0,
			));
			$event_id = (int)$this->db->insert_id();
			if ($event_id <= 0) {
				$err = $this->db->error();
				if ($idempotency_key !== null && $idempotency_key !== '' && isset($err['code']) && (int)$err['code'] === 1062) {
					$current = $this->db->query("SELECT * FROM walkin_order_items WHERE internal_id = ? AND InActive = 0", array($walkin_order_item_internal_id))->row();
					$this->db->trans_rollback();
					return array('success' => true, 'replay' => true, 'event_id' => null, 'item' => $current);
				}
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Failed to record fulfillment event');
			}

			$new_fulfilled = $fulfilled_qty + (float)$qty;
			$new_remaining = max(0.0, $total_qty - $new_fulfilled);
			$new_status = 'UNFULFILLED';
			if ($new_remaining <= 0) {
				$new_status = 'FULFILLED';
			} elseif ($new_fulfilled > 0) {
				$new_status = 'PARTIALLY_FULFILLED';
			}

			$upd = array(
				'fulfilled_qty' => (float)$new_fulfilled,
				'remaining_qty' => (float)$new_remaining,
				'fulfillment_status' => (string)$new_status,
				'department_status' => ($dept_upper === 'PHARMACY'
					? ($new_status === 'FULFILLED' ? 'DISPENSED' : 'PARTIALLY_DISPENSED')
					: ($dept_upper === 'LAB' || $dept_upper === 'LABORATORY' ? 'COMPLETED' : $new_status)),
				'last_fulfilled_at' => date('Y-m-d H:i:s'),
			);
			$lock_now = isset($item->financially_locked) ? ((int)$item->financially_locked === 1) : false;
			if (!$lock_now && $new_fulfilled > 0) {
				$upd['financially_locked'] = 1;
				$upd['financial_lock_reason'] = 'FULFILLMENT_STARTED';
				$upd['financial_lock_at'] = date('Y-m-d H:i:s');
				$upd['financial_lock_by'] = $actor;
			}

			$this->db->where('internal_id', (int)$walkin_order_item_internal_id);
			$this->db->where('InActive', 0);
			$this->db->update('walkin_order_items', $upd);
			if ($this->db->affected_rows() <= 0) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Failed to update fulfillment snapshot');
			}

			if ($this->table_exists('walkin_orders')) {
				$oid = (string)$item->walkin_order_id;
				$stats = $this->db->query(
					"SELECT " .
					"SUM(CASE WHEN fulfillment_status = 'FULFILLED' THEN 1 ELSE 0 END) AS c_full, " .
					"SUM(CASE WHEN fulfillment_status = 'PARTIALLY_FULFILLED' THEN 1 ELSE 0 END) AS c_part, " .
					"SUM(CASE WHEN fulfillment_status = 'CANCELLED' THEN 1 ELSE 0 END) AS c_cancel, " .
					"COUNT(*) AS c_total " .
					"FROM walkin_order_items WHERE walkin_order_id = ? AND InActive = 0",
					array($oid)
				)->row();
				if ($stats) {
					$c_total = (int)$stats->c_total;
					$c_full = (int)$stats->c_full;
					$c_part = (int)$stats->c_part;
					$c_cancel = (int)$stats->c_cancel;
					$new_order_status = 'UNFULFILLED';
					if ($c_total > 0 && ($c_full + $c_cancel) >= $c_total && $c_part === 0) {
						$new_order_status = ($c_cancel >= $c_total) ? 'CANCELLED' : 'FULFILLED';
					} elseif ($c_full > 0 || $c_part > 0) {
						$new_order_status = 'PARTIALLY_FULFILLED';
					}
					$this->db->where('walkin_order_id', $oid);
					$this->db->where('InActive', 0);
					$this->db->update('walkin_orders', array('fulfillment_status' => $new_order_status));
				}
			}

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => 'Fulfillment failed');
			}
			$this->db->trans_commit();

			$updated_item = $this->db->query("SELECT * FROM walkin_order_items WHERE internal_id = ? AND InActive = 0", array($walkin_order_item_internal_id))->row();
			return array('success' => true, 'event_id' => $event_id, 'item' => $updated_item);
		} catch (\Throwable $e) {
			try {
				$this->db->trans_rollback();
			} catch (\Throwable $e2) {
			}
			return array('success' => false, 'error' => 'Fulfillment failed');
		} finally {
			if ($prev_debug !== null) {
				$this->db->db_debug = $prev_debug;
			}
		}
	}

	public function mark_order_paid_authorized($walkin_order_id, $receipt_no = null, $actor = null)
	{
		$this->ensure_walkin_schema();
		$walkin_order_id = trim((string)$walkin_order_id);
		if ($walkin_order_id === '') return array('success' => false, 'error' => 'Missing walk-in order');
		$this->db->trans_begin();
		$order = $this->db->query("SELECT * FROM walkin_orders WHERE walkin_order_id = ? AND InActive = 0 FOR UPDATE", array($walkin_order_id))->row();
		if (!$order) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Walk-in order not found');
		}
		$receipt_no = $receipt_no !== null && trim((string)$receipt_no) !== '' ? trim((string)$receipt_no) : (isset($order->receipt_no) ? (string)$order->receipt_no : null);
		$this->db->where('walkin_order_id', $walkin_order_id);
		$this->db->where('InActive', 0);
		$this->db->update('walkin_orders', array(
			'payment_status' => 'PAID',
			'paid_amount' => isset($order->net_amount) ? (float)$order->net_amount : (float)$order->paid_amount,
			'balance_amount' => 0.0,
			'receipt_no' => $receipt_no,
			'updated_at' => date('Y-m-d H:i:s'),
		));
		$items = $this->db->query("SELECT internal_id, department, fulfillment_status, department_status FROM walkin_order_items WHERE walkin_order_id = ? AND InActive = 0 FOR UPDATE", array($walkin_order_id))->result();
		foreach ($items as $it) {
			$fs = isset($it->fulfillment_status) ? strtoupper(trim((string)$it->fulfillment_status)) : 'UNFULFILLED';
			if ($fs === 'CANCELLED' || $fs === 'FULFILLED') continue;
			$ds = isset($it->department_status) ? strtoupper(trim((string)$it->department_status)) : '';
			if ($ds === '' || $ds === 'UNPAID') {
				$this->db->where('internal_id', (int)$it->internal_id);
				$this->db->where('InActive', 0);
				$this->db->update('walkin_order_items', array('department_status' => $this->_paid_department_status($it->department)));
			}
		}
		$idem = $receipt_no ? ('receipt:' . $receipt_no) : null;
		$alreadyLogged = false;
		if ($idem !== null) {
			$alreadyLogged = (bool)$this->db->get_where('walkin_fulfillment_events', array('idempotency_key' => $idem, 'InActive' => 0), 1)->row();
		}
		if (!$alreadyLogged) {
			$this->db->insert('walkin_fulfillment_events', array(
				'walkin_order_id' => $walkin_order_id,
				'walkin_order_item_internal_id' => null,
				'department' => 'FINANCE',
				'item_ref' => null,
				'event_type' => 'PAYMENT_AUTHORIZED',
				'quantity' => 0,
				'idempotency_key' => $idem,
				'notes' => 'Payment authorized fulfillment',
				'created_by' => $actor,
				'created_at' => date('Y-m-d H:i:s'),
				'InActive' => 0,
			));
		}
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Failed to authorize fulfillment');
		}
		$this->db->trans_commit();
		return array('success' => true);
	}

	public function sync_paid_order_from_billing($walkin_order_id, $actor = null)
	{
		$this->ensure_walkin_schema();
		$walkin_order_id = trim((string)$walkin_order_id);
		if ($walkin_order_id === '') return array('success' => false, 'error' => 'Missing walk-in order');
		if (!$this->db->table_exists('iop_billing')) return array('success' => false, 'error' => 'Billing table missing');

		$join_billing = "B.billing_subject_type = 'WALKIN_ORDER' AND CONVERT(B.billing_subject_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(O.walkin_order_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND B.InActive = 0";
		$this->db->select('O.walkin_order_id, O.payment_status AS order_payment_status, B.invoice_no, B.receipt_no AS invoice_receipt_no');
		if ($this->db->table_exists('iop_receipt')) {
			$this->db->select('R.receipt_no AS receipt_receipt_no');
		}
		$this->db->from('walkin_orders O');
		$this->db->join('iop_billing B', $join_billing, 'inner', false);
		if ($this->db->table_exists('iop_receipt')) {
			$this->db->join('(SELECT invoice_no, MAX(receipt_no) AS receipt_no FROM iop_receipt WHERE InActive = 0 GROUP BY invoice_no) R', 'R.invoice_no = B.invoice_no', 'left', false);
		}
		$this->db->where('O.walkin_order_id', $walkin_order_id);
		$this->db->where('O.InActive', 0);
		$this->db->where("UPPER(TRIM(COALESCE(B.payment_status, ''))) = 'PAID'", null, false);
		$this->db->order_by('B.iop_id', 'DESC');
		$this->db->limit(1);
		$row = $this->db->get()->row();
		if (!$row) return array('success' => true, 'synced' => 0);

		$receipt_no = '';
		if (isset($row->receipt_receipt_no) && trim((string)$row->receipt_receipt_no) !== '') {
			$receipt_no = trim((string)$row->receipt_receipt_no);
		} elseif (isset($row->invoice_receipt_no) && trim((string)$row->invoice_receipt_no) !== '') {
			$receipt_no = trim((string)$row->invoice_receipt_no);
		}
		$sync = $this->mark_order_paid_authorized($walkin_order_id, $receipt_no, $actor);
		if (is_array($sync) && !empty($sync['success'])) {
			$sync['synced'] = 1;
		}
		return $sync;
	}

	public function reconcile_paid_orders_from_billing($limit = 100, $actor = null)
	{
		$this->ensure_walkin_schema();
		if (!$this->db->table_exists('iop_billing')) return array('success' => false, 'error' => 'Billing table missing');
		$limit = max(1, min(500, (int)$limit));
		$join_billing = "B.billing_subject_type = 'WALKIN_ORDER' AND CONVERT(B.billing_subject_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(O.walkin_order_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND B.InActive = 0";
		$this->db->select('O.walkin_order_id, B.receipt_no AS invoice_receipt_no');
		if ($this->db->table_exists('iop_receipt')) {
			$this->db->select('R.receipt_no AS receipt_receipt_no');
		}
		$this->db->from('walkin_orders O');
		$this->db->join('iop_billing B', $join_billing, 'inner', false);
		if ($this->db->table_exists('iop_receipt')) {
			$this->db->join('(SELECT invoice_no, MAX(receipt_no) AS receipt_no FROM iop_receipt WHERE InActive = 0 GROUP BY invoice_no) R', 'R.invoice_no = B.invoice_no', 'left', false);
		}
		$this->db->where('O.InActive', 0);
		$this->db->where("UPPER(TRIM(COALESCE(B.payment_status, ''))) = 'PAID'", null, false);
		$this->db->where("UPPER(TRIM(COALESCE(O.payment_status, ''))) <> 'PAID'", null, false);
		$this->db->order_by('O.internal_id', 'DESC');
		$this->db->limit($limit);
		$rows = $this->db->get()->result();
		$synced = 0;
		$failed = 0;
		foreach ($rows as $row) {
			$receipt_no = '';
			if (isset($row->receipt_receipt_no) && trim((string)$row->receipt_receipt_no) !== '') {
				$receipt_no = trim((string)$row->receipt_receipt_no);
			} elseif (isset($row->invoice_receipt_no) && trim((string)$row->invoice_receipt_no) !== '') {
				$receipt_no = trim((string)$row->invoice_receipt_no);
			}
			$res = $this->mark_order_paid_authorized((string)$row->walkin_order_id, $receipt_no, $actor);
			if (is_array($res) && !empty($res['success'])) {
				$synced++;
			} else {
				$failed++;
				$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'unknown sync failure';
				log_message('error', 'reconcile_paid_orders_from_billing failed: order=' . (string)$row->walkin_order_id . ' receipt=' . $receipt_no . ' error=' . $err);
			}
		}
		return array('success' => true, 'checked' => count($rows), 'synced' => $synced, 'failed' => $failed);
	}

	private function _refresh_order_fulfillment_status($walkin_order_id)
	{
		$walkin_order_id = trim((string)$walkin_order_id);
		if ($walkin_order_id === '') return;
		$stats = $this->db->query(
			"SELECT
				SUM(CASE WHEN fulfillment_status = 'FULFILLED' THEN 1 ELSE 0 END) AS c_full,
				SUM(CASE WHEN fulfillment_status = 'PARTIALLY_FULFILLED' THEN 1 ELSE 0 END) AS c_part,
				SUM(CASE WHEN fulfillment_status = 'CANCELLED' THEN 1 ELSE 0 END) AS c_cancel,
				COUNT(*) AS c_total
			 FROM walkin_order_items WHERE walkin_order_id = ? AND InActive = 0",
			array($walkin_order_id)
		)->row();
		if (!$stats) return;
		$c_total = (int)$stats->c_total;
		$c_full = (int)$stats->c_full;
		$c_part = (int)$stats->c_part;
		$c_cancel = (int)$stats->c_cancel;
		$new_order_status = 'UNFULFILLED';
		if ($c_total > 0 && ($c_full + $c_cancel) >= $c_total && $c_part === 0) {
			$new_order_status = ($c_cancel >= $c_total) ? 'CANCELLED' : 'FULFILLED';
		} elseif ($c_full > 0 || $c_part > 0) {
			$new_order_status = 'PARTIALLY_FULFILLED';
		}
		$this->db->where('walkin_order_id', $walkin_order_id);
		$this->db->where('InActive', 0);
		$this->db->update('walkin_orders', array('fulfillment_status' => $new_order_status));
	}

	public function get_department_queue($department, $status = null, $limit = 100)
	{
		$this->ensure_walkin_schema();
		$department = strtoupper(trim((string)$department));
		$limit = max(1, min(500, (int)$limit));
		$this->db->select('I.*, O.walkin_code, O.customer_name, O.phone, O.gender, O.payment_status, O.fulfillment_status AS order_fulfillment_status, O.invoice_no, O.receipt_no, O.transaction_type, O.created_at AS order_created_at');
		$this->db->from('walkin_order_items I');
		$this->db->join('walkin_orders O', 'O.walkin_order_id = I.walkin_order_id AND O.InActive = 0', 'inner');
		$this->db->where('I.InActive', 0);
		if ($department === 'LAB') {
			$this->db->where("UPPER(I.department) IN ('LAB','LABORATORY')", null, false);
		} else {
			$this->db->where("UPPER(I.department) = " . $this->db->escape($department), null, false);
		}
		$this->db->where('O.payment_status', 'PAID');
		if ($status !== null && trim((string)$status) !== '') {
			$this->db->where('I.department_status', strtoupper(trim((string)$status)));
		} else {
			$this->db->where_not_in('I.fulfillment_status', array('FULFILLED','CANCELLED'));
		}
		$this->db->order_by('O.created_at', 'ASC');
		$this->db->limit($limit);
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function update_lab_status($walkin_order_item_internal_id, $status, $actor = null, $notes = null)
	{
		$this->ensure_walkin_schema();
		$id = (int)$walkin_order_item_internal_id;
		$status = strtoupper(trim((string)$status));
		$allowed = array('SAMPLE_COLLECTED','IN_PROGRESS','COMPLETED','CANCELLED');
		if ($id <= 0 || !in_array($status, $allowed, true)) {
			return array('success' => false, 'error' => 'Invalid lab status');
		}
		$this->db->trans_begin();
		$item = $this->db->query(
			"SELECT I.*, O.payment_status AS order_payment_status
			 FROM walkin_order_items I
			 INNER JOIN walkin_orders O ON O.walkin_order_id = I.walkin_order_id AND O.InActive = 0
			 WHERE I.internal_id = ? AND I.InActive = 0 FOR UPDATE",
			array($id)
		)->row();
		if (!$item) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Lab item not found');
		}
		$dept = strtoupper(trim((string)$item->department));
		if ($dept !== 'LAB' && $dept !== 'LABORATORY') {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Not a lab item');
		}
		if (strtoupper(trim((string)$item->order_payment_status)) !== 'PAID') {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Payment authorization required');
		}
		$upd = array('department_status' => $status, 'last_fulfilled_at' => date('Y-m-d H:i:s'));
		if ($status === 'COMPLETED') {
			$upd['fulfilled_qty'] = (float)$item->quantity;
			$upd['remaining_qty'] = 0.0;
			$upd['fulfillment_status'] = 'FULFILLED';
		} elseif ($status === 'CANCELLED') {
			$upd['fulfillment_status'] = 'CANCELLED';
		}
		$this->db->where('internal_id', $id);
		$this->db->where('InActive', 0);
		$this->db->update('walkin_order_items', $upd);
		$this->db->insert('walkin_fulfillment_events', array(
			'walkin_order_id' => (string)$item->walkin_order_id,
			'walkin_order_item_internal_id' => $id,
			'department' => 'LAB',
			'item_ref' => isset($item->item_ref) ? (string)$item->item_ref : null,
			'event_type' => $status,
			'quantity' => ($status === 'COMPLETED') ? (float)$item->quantity : 0,
			'idempotency_key' => null,
			'notes' => $notes,
			'created_by' => $actor,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive' => 0,
		));
		$this->_refresh_order_fulfillment_status((string)$item->walkin_order_id);
		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Lab update failed');
		}
		$this->db->trans_commit();
		return array('success' => true);
	}

	public function get_fulfillment_reconciliation($date_from = null, $date_to = null)
	{
		$this->ensure_walkin_schema();
		$date_from = $date_from ? date('Y-m-d', strtotime((string)$date_from)) : date('Y-m-d');
		$date_to = $date_to ? date('Y-m-d', strtotime((string)$date_to)) : $date_from;
		$q = $this->db->query(
			"SELECT O.walkin_order_id, O.walkin_code, O.customer_name, O.phone, O.transaction_type,
			        O.payment_status, O.fulfillment_status, O.invoice_no, O.receipt_no,
			        O.net_amount, O.paid_amount, O.created_at,
			        COUNT(I.internal_id) AS item_count,
			        SUM(CASE WHEN I.fulfillment_status = 'FULFILLED' THEN 1 ELSE 0 END) AS fulfilled_count,
			        SUM(CASE WHEN I.fulfillment_status NOT IN ('FULFILLED','CANCELLED') THEN 1 ELSE 0 END) AS pending_count
			 FROM walkin_orders O
			 LEFT JOIN walkin_order_items I ON I.walkin_order_id = O.walkin_order_id AND I.InActive = 0
			 WHERE O.InActive = 0 AND DATE(O.created_at) BETWEEN ? AND ?
			 GROUP BY O.walkin_order_id
			 ORDER BY O.created_at DESC",
			array($date_from, $date_to)
		);
		return $q ? $q->result() : array();
	}

	private function table_exists($t)
	{
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($t));
		return ($q && $q->num_rows() > 0);
	}

	private function column_exists($table, $column)
	{
		$q = $this->db->query("SHOW COLUMNS FROM `" . $table . "` LIKE " . $this->db->escape($column));
		return ($q && $q->num_rows() > 0);
	}

	private function index_exists($table, $index_name)
	{
		if (!$this->table_exists($table)) {
			return false;
		}
		$q = $this->db->query("SHOW INDEX FROM `" . $table . "` WHERE Key_name = " . $this->db->escape($index_name));
		return ($q && $q->num_rows() > 0);
	}

	public function ensure_walkin_schema()
	{
		$prev_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_debug !== null) {
			$this->db->db_debug = false;
		}
		try {
			$this->_ensure_walkin_orders();
			$this->_ensure_walkin_order_items();
			$this->_ensure_walkin_fulfillment_events();
			$this->_ensure_walkin_inventory_ledger();
			$this->_ensure_subject_columns();
			$this->_ensure_ssot_uniques();
		} catch (Throwable $e) {
		}
		if ($prev_debug !== null) {
			$this->db->db_debug = $prev_debug;
		}
		return true;
	}

	private function _ensure_walkin_orders()
	{
		if (!$this->table_exists('walkin_orders')) {
			$this->db->query("CREATE TABLE `walkin_orders` (
				`internal_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`walkin_order_id` VARCHAR(40) NOT NULL,
				`walkin_code` VARCHAR(50) DEFAULT NULL,
				`walkin_client_id` INT(11) DEFAULT NULL,
				`customer_name` VARCHAR(150) DEFAULT NULL,
				`phone` VARCHAR(30) DEFAULT NULL,
				`gender` VARCHAR(20) DEFAULT NULL,
				`transaction_type` VARCHAR(30) DEFAULT NULL,
				`billing_subject_type` VARCHAR(32) DEFAULT NULL,
				`billing_subject_id` VARCHAR(64) DEFAULT NULL,
				`payer_type` ENUM('CASH','COMPANY') NOT NULL DEFAULT 'CASH',
				`company_id` INT(11) DEFAULT NULL,
				`company_authorization_note` VARCHAR(255) DEFAULT NULL,
				`company_authorized_by` VARCHAR(50) DEFAULT NULL,
				`company_payment_terms` VARCHAR(50) DEFAULT NULL,
				`currency` VARCHAR(10) NOT NULL DEFAULT 'GHS',
				`gross_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`discount_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`net_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`paid_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`balance_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`payment_status` ENUM('DRAFT','INVOICED','PARTIALLY_PAID','PAID','VOIDED','REFUNDED') NOT NULL DEFAULT 'DRAFT',
				`fulfillment_status` ENUM('UNFULFILLED','PARTIALLY_FULFILLED','FULFILLED','CANCELLED') NOT NULL DEFAULT 'UNFULFILLED',
				`invoice_no` VARCHAR(50) DEFAULT NULL,
				`receipt_no` VARCHAR(50) DEFAULT NULL,
				`notes` TEXT,
				`created_by` VARCHAR(25) DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`InActive` TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`internal_id`),
				UNIQUE KEY `uq_walkin_order_id` (`walkin_order_id`),
				KEY `idx_walkin_payer` (`payer_type`),
				KEY `idx_walkin_company` (`company_id`),
				KEY `idx_walkin_payment_status` (`payment_status`),
				KEY `idx_walkin_fulfillment_status` (`fulfillment_status`),
				KEY `idx_walkin_created_at` (`created_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}

		if ($this->table_exists('walkin_orders')) {
			if (!$this->column_exists('walkin_orders', 'walkin_code')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `walkin_code` VARCHAR(50) DEFAULT NULL AFTER `walkin_order_id`");
			}
			if (!$this->column_exists('walkin_orders', 'walkin_client_id')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `walkin_client_id` INT(11) DEFAULT NULL AFTER `walkin_code`");
			}
			if (!$this->column_exists('walkin_orders', 'customer_name')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `customer_name` VARCHAR(150) DEFAULT NULL AFTER `walkin_client_id`");
			}
			if (!$this->column_exists('walkin_orders', 'phone')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `phone` VARCHAR(30) DEFAULT NULL AFTER `customer_name`");
			}
			if (!$this->column_exists('walkin_orders', 'gender')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `gender` VARCHAR(20) DEFAULT NULL AFTER `phone`");
			}
			if (!$this->column_exists('walkin_orders', 'transaction_type')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `transaction_type` VARCHAR(30) DEFAULT NULL AFTER `gender`");
			}
			if (!$this->column_exists('walkin_orders', 'billing_subject_type')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `billing_subject_type` VARCHAR(32) DEFAULT NULL AFTER `walkin_order_id`");
			}
			if (!$this->column_exists('walkin_orders', 'billing_subject_id')) {
				$this->db->query("ALTER TABLE `walkin_orders` ADD COLUMN `billing_subject_id` VARCHAR(64) DEFAULT NULL AFTER `billing_subject_type`");
			}
		}
		return true;
	}

	private function _ensure_walkin_order_items()
	{
		if (!$this->table_exists('walkin_order_items')) {
			$this->db->query("CREATE TABLE `walkin_order_items` (
				`internal_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`walkin_order_id` VARCHAR(40) NOT NULL,
				`department` VARCHAR(20) NOT NULL,
				`item_type` VARCHAR(30) NOT NULL,
				`catalog_ref` VARCHAR(50) DEFAULT NULL,
				`item_ref` VARCHAR(80) DEFAULT NULL,
				`item_name` VARCHAR(255) NOT NULL,
				`quantity` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
				`unit_price` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`gross_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`discount_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`net_amount` DECIMAL(18,2) NOT NULL DEFAULT 0.00,
				`pricing_source_type` ENUM('CATALOG','CUSTOM','OVERRIDE','COMPANY_RATE','CONTRACT_RATE') NOT NULL DEFAULT 'CATALOG',
				`pricing_reason` VARCHAR(255) DEFAULT NULL,
				`fulfilled_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				`remaining_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				`fulfillment_status` ENUM('UNFULFILLED','PARTIALLY_FULFILLED','FULFILLED','CANCELLED') NOT NULL DEFAULT 'UNFULFILLED',
				`department_status` VARCHAR(40) NOT NULL DEFAULT 'UNPAID',
				`last_fulfilled_at` DATETIME DEFAULT NULL,
				`financially_locked` TINYINT(1) NOT NULL DEFAULT 0,
				`financial_lock_reason` VARCHAR(50) DEFAULT NULL,
				`financial_lock_at` DATETIME DEFAULT NULL,
				`financial_lock_by` VARCHAR(25) DEFAULT NULL,
				`notes` TEXT,
				`created_by` VARCHAR(25) DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`InActive` TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`internal_id`),
				KEY `idx_woi_order` (`walkin_order_id`),
				KEY `idx_woi_dept` (`department`),
				KEY `idx_woi_item_ref` (`item_ref`),
				KEY `idx_woi_fulfillment_status` (`fulfillment_status`),
				KEY `idx_woi_order_fulfillment` (`walkin_order_id`, `fulfillment_status`),
				KEY `idx_woi_dept_fulfillment` (`department`, `fulfillment_status`),
				CONSTRAINT `fk_woi_order` FOREIGN KEY (`walkin_order_id`) REFERENCES `walkin_orders`(`walkin_order_id`) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}

		if ($this->table_exists('walkin_order_items')) {
			if (!$this->column_exists('walkin_order_items', 'fulfilled_qty')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `fulfilled_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `pricing_reason`");
			}
			if (!$this->column_exists('walkin_order_items', 'remaining_qty')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `remaining_qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `fulfilled_qty`");
			}
			if (!$this->column_exists('walkin_order_items', 'fulfillment_status')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `fulfillment_status` ENUM('UNFULFILLED','PARTIALLY_FULFILLED','FULFILLED','CANCELLED') NOT NULL DEFAULT 'UNFULFILLED' AFTER `remaining_qty`");
			}
			if (!$this->column_exists('walkin_order_items', 'department_status')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `department_status` VARCHAR(40) NOT NULL DEFAULT 'UNPAID' AFTER `fulfillment_status`");
			}
			if (!$this->column_exists('walkin_order_items', 'last_fulfilled_at')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `last_fulfilled_at` DATETIME DEFAULT NULL AFTER `department_status`");
			}
			if (!$this->column_exists('walkin_order_items', 'financially_locked')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `financially_locked` TINYINT(1) NOT NULL DEFAULT 0 AFTER `last_fulfilled_at`");
			}
			if (!$this->column_exists('walkin_order_items', 'financial_lock_reason')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `financial_lock_reason` VARCHAR(50) DEFAULT NULL AFTER `financially_locked`");
			}
			if (!$this->column_exists('walkin_order_items', 'financial_lock_at')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `financial_lock_at` DATETIME DEFAULT NULL AFTER `financial_lock_reason`");
			}
			if (!$this->column_exists('walkin_order_items', 'financial_lock_by')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD COLUMN `financial_lock_by` VARCHAR(25) DEFAULT NULL AFTER `financial_lock_at`");
			}

			if (!$this->index_exists('walkin_order_items', 'idx_woi_order_fulfillment')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD INDEX `idx_woi_order_fulfillment` (`walkin_order_id`, `fulfillment_status`)");
			}
			if (!$this->index_exists('walkin_order_items', 'idx_woi_dept_fulfillment')) {
				$this->db->query("ALTER TABLE `walkin_order_items` ADD INDEX `idx_woi_dept_fulfillment` (`department`, `fulfillment_status`)");
			}
		}
		return true;
	}

	private function _ensure_walkin_fulfillment_events()
	{
		if (!$this->table_exists('walkin_fulfillment_events')) {
			$this->db->query("CREATE TABLE `walkin_fulfillment_events` (
				`event_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`walkin_order_id` VARCHAR(40) NOT NULL,
				`walkin_order_item_internal_id` BIGINT(20) DEFAULT NULL,
				`department` VARCHAR(20) NOT NULL,
				`item_ref` VARCHAR(80) DEFAULT NULL,
				`event_type` VARCHAR(30) NOT NULL,
				`quantity` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
				`idempotency_key` VARCHAR(64) DEFAULT NULL,
				`notes` TEXT,
				`created_by` VARCHAR(25) DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`InActive` TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`event_id`),
				UNIQUE KEY `uq_wfe_idem` (`idempotency_key`),
				KEY `idx_wfe_order` (`walkin_order_id`),
				KEY `idx_wfe_item` (`walkin_order_item_internal_id`),
				KEY `idx_wfe_item_ref` (`item_ref`),
				KEY `idx_wfe_created_at` (`created_at`),
				CONSTRAINT `fk_wfe_order` FOREIGN KEY (`walkin_order_id`) REFERENCES `walkin_orders`(`walkin_order_id`) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		return true;
	}

	private function _ensure_walkin_inventory_ledger()
	{
		if (!$this->table_exists('walkin_inventory_ledger')) {
			$this->db->query("CREATE TABLE `walkin_inventory_ledger` (
				`ledger_id` BIGINT(20) NOT NULL AUTO_INCREMENT,
				`walkin_order_id` VARCHAR(40) NOT NULL,
				`walkin_order_item_internal_id` BIGINT(20) NOT NULL,
				`drug_id` INT(11) NOT NULL,
				`stock_id` INT(11) DEFAULT NULL,
				`batch_number` VARCHAR(50) DEFAULT NULL,
				`expiry_date` DATE DEFAULT NULL,
				`movement_type` VARCHAR(30) NOT NULL,
				`quantity` DECIMAL(10,2) NOT NULL,
				`stock_before` DECIMAL(12,2) DEFAULT NULL,
				`stock_after` DECIMAL(12,2) DEFAULT NULL,
				`reference_type` VARCHAR(40) NOT NULL DEFAULT 'WALKIN_FULFILL',
				`reference_id` VARCHAR(80) DEFAULT NULL,
				`created_by` VARCHAR(25) DEFAULT NULL,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`InActive` TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`ledger_id`),
				KEY `idx_wil_order` (`walkin_order_id`),
				KEY `idx_wil_item` (`walkin_order_item_internal_id`),
				KEY `idx_wil_drug` (`drug_id`),
				KEY `idx_wil_created_at` (`created_at`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}
		return true;
	}

	private function _ensure_subject_columns()
	{
		$targets = array(
			'billing_queue' => array(
				array('billing_subject_type', "ALTER TABLE `billing_queue` ADD COLUMN `billing_subject_type` VARCHAR(32) DEFAULT NULL AFTER `patient_no`"),
				array('billing_subject_id', "ALTER TABLE `billing_queue` ADD COLUMN `billing_subject_id` VARCHAR(64) DEFAULT NULL AFTER `billing_subject_type`"),
			),
			'iop_billing' => array(
				array('billing_subject_type', "ALTER TABLE `iop_billing` ADD COLUMN `billing_subject_type` VARCHAR(32) DEFAULT NULL"),
				array('billing_subject_id', "ALTER TABLE `iop_billing` ADD COLUMN `billing_subject_id` VARCHAR(64) DEFAULT NULL"),
			),
			'iop_receipt' => array(
				array('billing_subject_type', "ALTER TABLE `iop_receipt` ADD COLUMN `billing_subject_type` VARCHAR(32) DEFAULT NULL"),
				array('billing_subject_id', "ALTER TABLE `iop_receipt` ADD COLUMN `billing_subject_id` VARCHAR(64) DEFAULT NULL"),
			),
			'financial_ledger' => array(
				array('billing_subject_type', "ALTER TABLE `financial_ledger` ADD COLUMN `billing_subject_type` VARCHAR(32) DEFAULT NULL"),
				array('billing_subject_id', "ALTER TABLE `financial_ledger` ADD COLUMN `billing_subject_id` VARCHAR(64) DEFAULT NULL"),
			),
			'billing_transactions' => array(
				array('billing_subject_type', "ALTER TABLE `billing_transactions` ADD COLUMN `billing_subject_type` VARCHAR(32) DEFAULT NULL AFTER `encounter_type`"),
				array('billing_subject_id', "ALTER TABLE `billing_transactions` ADD COLUMN `billing_subject_id` VARCHAR(64) DEFAULT NULL AFTER `billing_subject_type`"),
			),
		);

		foreach ($targets as $table => $cols) {
			if (!$this->table_exists($table)) {
				continue;
			}
			foreach ($cols as $c) {
				$col = $c[0];
				$sql = $c[1];
				if (!$this->column_exists($table, $col)) {
					$this->db->query($sql);
				}
			}
		}
		return true;
	}

	private function _ensure_ssot_uniques()
	{
		if (!$this->table_exists('billing_transactions')) {
			return true;
		}
		if (!$this->column_exists('billing_transactions', 'billing_subject_type') || !$this->column_exists('billing_transactions', 'billing_subject_id')) {
			return true;
		}
		if ($this->index_exists('billing_transactions', 'uq_subject_item_ref')) {
			return true;
		}
		$this->db->query("ALTER TABLE `billing_transactions` ADD UNIQUE KEY `uq_subject_item_ref` (`billing_subject_type`, `billing_subject_id`, `item_ref`)");
		return true;
	}
}
