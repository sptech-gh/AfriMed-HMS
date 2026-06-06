<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Consumable_order_model
 *
 * Manages consumable & supply ordering for IPD patients.
 * Integrates with existing billing_queue, Price_engine_model,
 * Billing_master_model, and pharmacy_stock_model.
 *
 * Strictly additive — no modifications to existing tables.
 */
class Consumable_order_model extends CI_Model
{
	private static $_schema_done = false;

	public function __construct()
	{
		parent::__construct();
	}

	// =========================================================================
	// SCHEMA
	// =========================================================================

	public function ensure_schema()
	{
		if (self::$_schema_done) return;
		self::$_schema_done = true;

		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) $this->db->db_debug = false;

		try {
			$this->_create_orders_table();
			$this->_create_order_items_table();
			$this->_seed_bill_groups();
		} catch (\Throwable $e) {
			log_message('error', 'Consumable_order_model schema: ' . $e->getMessage());
		}

		if ($prev !== null) $this->db->db_debug = $prev;
	}

	private function _create_orders_table()
	{
		if ($this->db->table_exists('consumable_orders')) return;
		$this->db->query("
			CREATE TABLE `consumable_orders` (
				`order_id` INT AUTO_INCREMENT PRIMARY KEY,
				`order_no` VARCHAR(40) NOT NULL,
				`iop_id` VARCHAR(25) NOT NULL,
				`patient_no` VARCHAR(25) NOT NULL,
				`order_status` VARCHAR(30) NOT NULL DEFAULT 'PENDING_BILLING',
				`gross_amount` DECIMAL(18,2) DEFAULT 0.00,
				`discount_amount` DECIMAL(18,2) DEFAULT 0.00,
				`net_amount` DECIMAL(18,2) DEFAULT 0.00,
				`payer_type` VARCHAR(20) DEFAULT 'CASH',
				`notes` TEXT,
				`ordered_by` VARCHAR(25) NOT NULL,
				`ordered_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
				`InActive` TINYINT(1) DEFAULT 0,
				`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				UNIQUE KEY `uq_co_no` (`order_no`),
				INDEX `idx_co_iop` (`iop_id`),
				INDEX `idx_co_patient` (`patient_no`),
				INDEX `idx_co_status` (`order_status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}

	private function _create_order_items_table()
	{
		if ($this->db->table_exists('consumable_order_items')) return;
		$this->db->query("
			CREATE TABLE `consumable_order_items` (
				`item_id` INT AUTO_INCREMENT PRIMARY KEY,
				`order_id` INT NOT NULL,
				`item_source` VARCHAR(20) NOT NULL DEFAULT 'PARTICULAR' COMMENT 'PARTICULAR or DRUG',
				`catalog_id` INT NOT NULL,
				`item_name` VARCHAR(255) NOT NULL,
				`quantity` DECIMAL(10,2) NOT NULL DEFAULT 1,
				`unit_price` DECIMAL(18,2) NOT NULL,
				`gross_amount` DECIMAL(18,2) NOT NULL,
				`discount_amount` DECIMAL(18,2) DEFAULT 0.00,
				`net_amount` DECIMAL(18,2) NOT NULL,
				`price_source` VARCHAR(30) DEFAULT NULL,
				`is_stock_backed` TINYINT(1) DEFAULT 0,
				`stock_drug_id` INT DEFAULT NULL,
				`fulfillment_status` VARCHAR(20) DEFAULT 'PENDING',
				`fulfilled_qty` DECIMAL(10,2) DEFAULT 0,
				`fulfilled_at` DATETIME DEFAULT NULL,
				`fulfilled_by` VARCHAR(25) DEFAULT NULL,
				`queue_id` INT DEFAULT NULL COMMENT 'FK billing_queue.queue_id',
				`InActive` TINYINT(1) DEFAULT 0,
				`created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
				INDEX `idx_coi_order` (`order_id`),
				INDEX `idx_coi_status` (`fulfillment_status`),
				INDEX `idx_coi_catalog` (`item_source`, `catalog_id`),
				FOREIGN KEY (`order_id`) REFERENCES `consumable_orders`(`order_id`) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
		");
	}

	private function _seed_bill_groups()
	{
		if (!$this->db->table_exists('bill_group_name')) return;
		if (!$this->db->table_exists('bill_particular')) return;

		// Ensure NHIS columns exist on bill_particular
		$this->load->model('app/billing_model');
		if (method_exists($this->billing_model, 'ensure_nhis_service_columns')) {
			$this->billing_model->ensure_nhis_service_columns();
		}

		$has_nhis = $this->db->field_exists('is_nhis_covered', 'bill_particular');
		$has_nhis_amt = $this->db->field_exists('nhis_charge_amount', 'bill_particular');

		/*
		 * GHS / Claim-IT aligned consumable categories and items.
		 * Prices in GHS at standard private-hospital rates (2024-2026).
		 * NHIS coverage flags set per NHIS Medicines List & G-DRG tariff schedule.
		 */
		$catalog = array(
			'NURSING CONSUMABLES' => array(
				array('name'=>'Examination Gloves (pair)','price'=>3.00,'nhis'=>1,'nhis_price'=>2.50),
				array('name'=>'Sterile Surgical Gloves (pair)','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Disposable Syringe 2ml','price'=>2.00,'nhis'=>1,'nhis_price'=>1.50),
				array('name'=>'Disposable Syringe 5ml','price'=>2.50,'nhis'=>1,'nhis_price'=>2.00),
				array('name'=>'Disposable Syringe 10ml','price'=>3.50,'nhis'=>1,'nhis_price'=>3.00),
				array('name'=>'Disposable Syringe 20ml','price'=>5.00,'nhis'=>1,'nhis_price'=>4.00),
				array('name'=>'Disposable Syringe 50ml','price'=>8.00,'nhis'=>1,'nhis_price'=>6.50),
				array('name'=>'Hypodermic Needle 21G','price'=>1.50,'nhis'=>1,'nhis_price'=>1.00),
				array('name'=>'Hypodermic Needle 23G','price'=>1.50,'nhis'=>1,'nhis_price'=>1.00),
				array('name'=>'Butterfly Needle 23G','price'=>5.00,'nhis'=>1,'nhis_price'=>4.00),
				array('name'=>'Cotton Wool Roll (500g)','price'=>25.00,'nhis'=>1,'nhis_price'=>20.00),
				array('name'=>'Cotton Wool Balls (pack)','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Methylated Spirit 100ml','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
				array('name'=>'Surgical Spirit 100ml','price'=>12.00,'nhis'=>1,'nhis_price'=>10.00),
				array('name'=>'Povidone Iodine (Betadine) 100ml','price'=>18.00,'nhis'=>1,'nhis_price'=>15.00),
				array('name'=>'Chlorhexidine Solution 100ml','price'=>15.00,'nhis'=>1,'nhis_price'=>12.00),
				array('name'=>'Hydrogen Peroxide 100ml','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Adhesive Plaster/Tape Roll','price'=>12.00,'nhis'=>1,'nhis_price'=>10.00),
				array('name'=>'Micropore Tape Roll','price'=>15.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Tourniquet (reusable)','price'=>10.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Sharps Container','price'=>35.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Specimen Container (sterile)','price'=>5.00,'nhis'=>1,'nhis_price'=>4.00),
				array('name'=>'Urine Collection Bag','price'=>12.00,'nhis'=>1,'nhis_price'=>10.00),
				array('name'=>'Thermometer Cover (disposable)','price'=>1.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Tongue Depressor (pack of 10)','price'=>5.00,'nhis'=>0,'nhis_price'=>0),
			),
			'WOUND CARE SUPPLIES' => array(
				array('name'=>'Sterile Gauze Pad 10x10cm','price'=>3.00,'nhis'=>1,'nhis_price'=>2.50),
				array('name'=>'Sterile Gauze Roll','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Elastic/Crepe Bandage 10cm','price'=>12.00,'nhis'=>1,'nhis_price'=>10.00),
				array('name'=>'Elastic/Crepe Bandage 15cm','price'=>15.00,'nhis'=>1,'nhis_price'=>12.00),
				array('name'=>'Triangular Bandage','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
				array('name'=>'Dressing Pack (sterile)','price'=>20.00,'nhis'=>1,'nhis_price'=>15.00),
				array('name'=>'Wound Closure Strip (Steri-Strip)','price'=>15.00,'nhis'=>1,'nhis_price'=>12.00),
				array('name'=>'Suture Kit (disposable)','price'=>35.00,'nhis'=>1,'nhis_price'=>28.00),
				array('name'=>'Suture Material - Nylon','price'=>25.00,'nhis'=>1,'nhis_price'=>20.00),
				array('name'=>'Suture Material - Chromic Catgut','price'=>30.00,'nhis'=>1,'nhis_price'=>25.00),
				array('name'=>'Suture Material - Vicryl','price'=>45.00,'nhis'=>1,'nhis_price'=>35.00),
				array('name'=>'Surgical Blade No. 11','price'=>3.00,'nhis'=>1,'nhis_price'=>2.00),
				array('name'=>'Surgical Blade No. 15','price'=>3.00,'nhis'=>1,'nhis_price'=>2.00),
				array('name'=>'Absorbent Pad (ABD Pad)','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
			),
			'IV & INFUSION SUPPLIES' => array(
				array('name'=>'IV Cannula 18G','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'IV Cannula 20G','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'IV Cannula 22G','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'IV Cannula 24G (Paediatric)','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
				array('name'=>'IV Giving Set (Adult)','price'=>12.00,'nhis'=>1,'nhis_price'=>10.00),
				array('name'=>'IV Giving Set (Paediatric/Micro)','price'=>18.00,'nhis'=>1,'nhis_price'=>15.00),
				array('name'=>'Blood Giving Set','price'=>20.00,'nhis'=>1,'nhis_price'=>16.00),
				array('name'=>'IV Extension Tube','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Three-Way Stopcock','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
				array('name'=>'Scalp Vein Set 25G','price'=>5.00,'nhis'=>1,'nhis_price'=>4.00),
				array('name'=>'Heparin Cap/Injection Port','price'=>5.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Tegaderm/IV Dressing','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
			),
			'CATHETER & DRAINAGE' => array(
				array('name'=>'Foley Catheter 14Fr','price'=>20.00,'nhis'=>1,'nhis_price'=>16.00),
				array('name'=>'Foley Catheter 16Fr','price'=>20.00,'nhis'=>1,'nhis_price'=>16.00),
				array('name'=>'Foley Catheter 18Fr','price'=>20.00,'nhis'=>1,'nhis_price'=>16.00),
				array('name'=>'Urine Bag (2L Drainage)','price'=>15.00,'nhis'=>1,'nhis_price'=>12.00),
				array('name'=>'Nasogastric Tube (NG Tube)','price'=>15.00,'nhis'=>1,'nhis_price'=>12.00),
				array('name'=>'Suction Catheter','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Rectal Tube','price'=>10.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Colostomy Bag','price'=>35.00,'nhis'=>1,'nhis_price'=>28.00),
			),
			'RESPIRATORY SUPPLIES' => array(
				array('name'=>'Oxygen Mask (Adult)','price'=>15.00,'nhis'=>1,'nhis_price'=>12.00),
				array('name'=>'Oxygen Mask (Paediatric)','price'=>18.00,'nhis'=>1,'nhis_price'=>15.00),
				array('name'=>'Nasal Cannula (Prong)','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
				array('name'=>'Nebulizer Mask + Chamber','price'=>25.00,'nhis'=>1,'nhis_price'=>20.00),
				array('name'=>'Oxygen Tubing','price'=>8.00,'nhis'=>1,'nhis_price'=>6.00),
				array('name'=>'Ambu Bag (Disposable)','price'=>80.00,'nhis'=>1,'nhis_price'=>65.00),
				array('name'=>'Endotracheal Tube (ETT)','price'=>25.00,'nhis'=>1,'nhis_price'=>20.00),
				array('name'=>'Suction Tubing','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
			),
			'WARD SERVICES' => array(
				array('name'=>'Wound Dressing Service','price'=>30.00,'nhis'=>1,'nhis_price'=>25.00),
				array('name'=>'Wound Re-dressing (Complex)','price'=>50.00,'nhis'=>1,'nhis_price'=>40.00),
				array('name'=>'IV Cannulation Service','price'=>20.00,'nhis'=>1,'nhis_price'=>15.00),
				array('name'=>'Catheterization Service','price'=>40.00,'nhis'=>1,'nhis_price'=>30.00),
				array('name'=>'Nebulization Session','price'=>25.00,'nhis'=>1,'nhis_price'=>20.00),
				array('name'=>'Oxygen Therapy (per hour)','price'=>20.00,'nhis'=>1,'nhis_price'=>15.00),
				array('name'=>'Blood Glucose Monitoring (per test)','price'=>10.00,'nhis'=>1,'nhis_price'=>8.00),
				array('name'=>'ECG Monitoring (Ward)','price'=>50.00,'nhis'=>1,'nhis_price'=>40.00),
				array('name'=>'Enema Administration','price'=>30.00,'nhis'=>1,'nhis_price'=>25.00),
				array('name'=>'Nasogastric Tube Insertion','price'=>35.00,'nhis'=>1,'nhis_price'=>28.00),
				array('name'=>'Sitz Bath','price'=>15.00,'nhis'=>0,'nhis_price'=>0),
				array('name'=>'Ear/Eye Irrigation','price'=>20.00,'nhis'=>1,'nhis_price'=>15.00),
			),
		);

		foreach ($catalog as $group_name => $items) {
			// Ensure group exists
			$this->db->where('group_name', $group_name);
			$this->db->where('InActive', 0);
			$grp = $this->db->get('bill_group_name')->row();
			if (!$grp) {
				$this->db->insert('bill_group_name', array('group_name' => $group_name, 'InActive' => 0));
				$group_id = (int)$this->db->insert_id();
			} else {
				$group_id = (int)$grp->group_id;
			}
			if ($group_id <= 0) continue;

			// Seed items (skip if any exist for this group already)
			$this->db->where('group_id', $group_id);
			$this->db->where('InActive', 0);
			$existing_count = $this->db->count_all_results('bill_particular');
			if ($existing_count > 0) continue; // Don't re-seed if items already exist

			foreach ($items as $item) {
				$row = array(
					'group_id'       => $group_id,
					'particular_name'=> $item['name'],
					'charge_amount'  => (float)$item['price'],
					'InActive'       => 0,
				);
				if ($has_nhis) $row['is_nhis_covered'] = (int)$item['nhis'];
				if ($has_nhis_amt) $row['nhis_charge_amount'] = (float)$item['nhis_price'];
				$this->db->insert('bill_particular', $row);
			}
		}
	}

	// =========================================================================
	// ORDER CREATION
	// =========================================================================

	/**
	 * @param string $iop_id
	 * @param string $patient_no
	 * @param array  $items [{item_source, catalog_id, quantity}, ...]
	 * @param string $actor user_id
	 * @param string|null $notes
	 * @return array {ok, order_no, order_id, error}
	 */
	public function create_order($iop_id, $patient_no, $items, $actor, $notes = null)
	{
		$this->ensure_schema();
		$iop_id = trim((string)$iop_id);
		$patient_no = trim((string)$patient_no);
		$actor = trim((string)$actor);

		if ($iop_id === '' || $patient_no === '' || $actor === '') {
			return array('ok' => false, 'error' => 'Missing required fields');
		}
		if (!is_array($items) || count($items) === 0) {
			return array('ok' => false, 'error' => 'No items provided');
		}

		// Validate encounter exists
		$enc = $this->db->get_where('patient_details_iop', array('IO_ID' => $iop_id, 'patient_no' => $patient_no, 'InActive' => 0), 1)->row();
		if (!$enc) {
			return array('ok' => false, 'error' => 'Encounter not found');
		}

		// Load dependencies
		$this->load->model('app/Price_engine_model');
		$this->load->model('app/billing_model');
		$this->load->model('app/unified_billing_model');
		$this->unified_billing_model->ensure_unified_billing_schema();

		$payer = $this->billing_model->determine_payer_type($patient_no);
		$order_no = $this->_generate_order_no();
		$now = date('Y-m-d H:i:s');

		// Resolve prices and validate
		$resolved_items = array();
		$gross_total = 0.0;

		foreach ($items as $it) {
			if (!is_array($it)) continue;
			$source = isset($it['item_source']) ? strtoupper(trim((string)$it['item_source'])) : 'PARTICULAR';
			$catalog_id = isset($it['catalog_id']) ? (int)$it['catalog_id'] : 0;
			$qty = isset($it['quantity']) ? (float)$it['quantity'] : 1.0;
			if ($catalog_id <= 0 || $qty <= 0) continue;
			if (!in_array($source, array('PARTICULAR', 'DRUG'), true)) $source = 'PARTICULAR';

			$item_type = ($source === 'DRUG') ? 'DRUG' : 'SERVICE';
			$price = $this->Price_engine_model->resolve(array(
				'item_type'  => $item_type,
				'item_id'    => $catalog_id,
				'patient_no' => $patient_no,
				'payer_type' => $payer,
				'quantity'   => $qty,
			));

			if (empty($price['ok'])) {
				return array('ok' => false, 'error' => 'Price lookup failed: ' . (isset($price['error']) ? $price['error'] : 'item ' . $catalog_id));
			}

			$is_stock = ($source === 'DRUG') ? 1 : 0;
			$stock_drug_id = ($source === 'DRUG') ? $catalog_id : null;
			$gross = round($qty * $price['unit_price'], 2);

			$resolved_items[] = array(
				'item_source'    => $source,
				'catalog_id'     => $catalog_id,
				'item_name'      => $price['item_name'],
				'quantity'       => $qty,
				'unit_price'     => $price['unit_price'],
				'gross_amount'   => $gross,
				'net_amount'     => $gross,
				'price_source'   => $price['price_source'],
				'is_stock_backed'=> $is_stock,
				'stock_drug_id'  => $stock_drug_id,
			);
			$gross_total += $gross;
		}

		if (count($resolved_items) === 0) {
			return array('ok' => false, 'error' => 'No valid items after price resolution');
		}

		// Transactional insert
		$this->db->trans_begin();
		try {
			// Header
			$this->db->insert('consumable_orders', array(
				'order_no'     => $order_no,
				'iop_id'       => $iop_id,
				'patient_no'   => $patient_no,
				'order_status' => 'PENDING_BILLING',
				'gross_amount' => $gross_total,
				'net_amount'   => $gross_total,
				'payer_type'   => $payer,
				'notes'        => $notes,
				'ordered_by'   => $actor,
				'ordered_at'   => $now,
			));
			$order_id = (int)$this->db->insert_id();
			if ($order_id <= 0) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Failed to create order');
			}

			// Lines + billing queue
			foreach ($resolved_items as $ri) {
				$this->db->insert('consumable_order_items', array(
					'order_id'       => $order_id,
					'item_source'    => $ri['item_source'],
					'catalog_id'     => $ri['catalog_id'],
					'item_name'      => $ri['item_name'],
					'quantity'       => $ri['quantity'],
					'unit_price'     => $ri['unit_price'],
					'gross_amount'   => $ri['gross_amount'],
					'net_amount'     => $ri['net_amount'],
					'price_source'   => $ri['price_source'],
					'is_stock_backed'=> $ri['is_stock_backed'],
					'stock_drug_id'  => $ri['stock_drug_id'],
				));
				$item_id = (int)$this->db->insert_id();

				// Enqueue into billing_queue
				$source_ref = 'consumable_order_item_id:' . $item_id;
				$queue_data = array(
					'iop_id'       => $iop_id,
					'patient_no'   => $patient_no,
					'item_type'    => 'SUPPLY',
					'item_id'      => (string)$item_id,
					'item_name'    => $ri['item_name'],
					'quantity'     => $ri['quantity'],
					'unit_price'   => $ri['unit_price'],
					'total_amount' => $ri['gross_amount'],
					'net_amount'   => $ri['net_amount'],
					'payer_type'   => $payer,
					'patient_amount' => $ri['net_amount'],
					'status'       => 'PENDING',
					'source_module'=> 'CONSUMABLE_ORDER',
					'source_ref'   => $source_ref,
					'requested_by' => $actor,
					'requested_at' => $now,
					'InActive'     => 0,
				);
				if ($this->db->field_exists('billing_subject_type', 'billing_queue')) {
					$queue_data['billing_subject_type'] = 'PATIENT_VISIT';
					$queue_data['billing_subject_id'] = $iop_id;
				}
				$this->db->insert('billing_queue', $queue_data);
				$queue_id = (int)$this->db->insert_id();

				// Link queue_id back to item
				if ($queue_id > 0) {
					$this->db->where('item_id', $item_id);
					$this->db->update('consumable_order_items', array('queue_id' => $queue_id));
				}
			}

			// Update status
			$this->db->where('order_id', $order_id);
			$this->db->update('consumable_orders', array('order_status' => 'BILLED'));

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Database error');
			}
			$this->db->trans_commit();

			return array('ok' => true, 'order_no' => $order_no, 'order_id' => $order_id, 'net_amount' => $gross_total);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => $e->getMessage());
		}
	}

	// =========================================================================
	// FULFILLMENT
	// =========================================================================

	public function fulfill_order_item($item_id, $qty, $actor)
	{
		$this->ensure_schema();
		$item_id = (int)$item_id;
		$qty = (float)$qty;
		$actor = trim((string)$actor);

		if ($item_id <= 0 || $qty <= 0 || $actor === '') {
			return array('ok' => false, 'error' => 'Invalid parameters');
		}

		$this->db->trans_begin();
		try {
			// Fetch item with lock
			$item = $this->db->query(
				"SELECT ci.*, co.iop_id, co.patient_no, co.payer_type
				 FROM consumable_order_items ci
				 INNER JOIN consumable_orders co ON co.order_id = ci.order_id AND co.InActive = 0
				 WHERE ci.item_id = ? AND ci.InActive = 0 FOR UPDATE",
				array($item_id)
			)->row();

			if (!$item) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Item not found');
			}

			$status = strtoupper(trim((string)$item->fulfillment_status));
			if ($status === 'FULFILLED' || $status === 'CANCELLED') {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Item already ' . $status);
			}

			// Check payment gate via billing_queue
			if ((int)$item->queue_id > 0 && $this->db->table_exists('billing_queue')) {
				$qrow = $this->db->get_where('billing_queue', array('queue_id' => (int)$item->queue_id, 'InActive' => 0))->row();
				if ($qrow && isset($qrow->service_gate_status)) {
					$gate = strtoupper(trim((string)$qrow->service_gate_status));
					if ($gate !== 'RELEASED') {
						$this->db->trans_rollback();
						return array('ok' => false, 'error' => 'Payment required before fulfillment (gate: ' . $gate . ')');
					}
				}
			}

			$remaining = (float)$item->quantity - (float)$item->fulfilled_qty;
			if ($qty > $remaining) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Qty exceeds remaining (' . $remaining . ')');
			}

			// Stock deduction for stock-backed items
			if ((int)$item->is_stock_backed === 1 && (int)$item->stock_drug_id > 0) {
				$this->load->model('app/pharmacy_stock_model');
				$this->pharmacy_stock_model->ensure_stock_schema();

				if ($this->db->table_exists('medication_stock')) {
					$result = $this->pharmacy_stock_model->deduct_batch_stock_fefo(
						(int)$item->stock_drug_id, $qty, $actor, 'CONSUMABLE_FULFILL', $item_id
					);
					if (empty($result['success'])) {
						// Fallback to simple deduction
						$this->pharmacy_stock_model->deduct_stock(
							(int)$item->stock_drug_id, $qty, $actor, 'CONSUMABLE_FULFILL', $item_id
						);
					}
				} else {
					$this->pharmacy_stock_model->deduct_stock(
						(int)$item->stock_drug_id, $qty, $actor, 'CONSUMABLE_FULFILL', $item_id
					);
				}
			}

			// Update fulfillment
			$new_fulfilled = (float)$item->fulfilled_qty + $qty;
			$new_status = ($new_fulfilled >= (float)$item->quantity) ? 'FULFILLED' : 'PENDING';

			$this->db->where('item_id', $item_id);
			$this->db->update('consumable_order_items', array(
				'fulfilled_qty'      => $new_fulfilled,
				'fulfillment_status' => $new_status,
				'fulfilled_at'       => date('Y-m-d H:i:s'),
				'fulfilled_by'       => $actor,
			));

			// Refresh order header status
			$this->_refresh_order_status((int)$item->order_id);

			if ($this->db->trans_status() === false) {
				$this->db->trans_rollback();
				return array('ok' => false, 'error' => 'Database error');
			}
			$this->db->trans_commit();
			return array('ok' => true, 'new_status' => $new_status, 'fulfilled_qty' => $new_fulfilled);
		} catch (\Throwable $e) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => $e->getMessage());
		}
	}

	// =========================================================================
	// CANCEL
	// =========================================================================

	public function cancel_order($order_id, $actor, $reason = '')
	{
		$this->ensure_schema();
		$order_id = (int)$order_id;

		$order = $this->db->get_where('consumable_orders', array('order_id' => $order_id, 'InActive' => 0))->row();
		if (!$order) return array('ok' => false, 'error' => 'Order not found');

		// Check no items fulfilled
		$this->db->where('order_id', $order_id);
		$this->db->where('fulfillment_status', 'FULFILLED');
		$this->db->where('InActive', 0);
		if ($this->db->count_all_results('consumable_order_items') > 0) {
			return array('ok' => false, 'error' => 'Cannot cancel: some items already fulfilled');
		}

		$this->db->trans_begin();
		// Cancel items
		$this->db->where('order_id', $order_id);
		$this->db->where('InActive', 0);
		$this->db->update('consumable_order_items', array('fulfillment_status' => 'CANCELLED'));

		// Cancel billing queue entries
		$items = $this->db->get_where('consumable_order_items', array('order_id' => $order_id))->result();
		foreach ($items as $it) {
			if ((int)$it->queue_id > 0) {
				$this->db->where('queue_id', (int)$it->queue_id);
				$this->db->update('billing_queue', array('status' => 'CANCELLED'));
			}
		}

		// Cancel header
		$this->db->where('order_id', $order_id);
		$this->db->update('consumable_orders', array(
			'order_status' => 'CANCELLED',
			'notes' => trim($order->notes . "\nCancelled by {$actor}: {$reason}"),
		));

		if ($this->db->trans_status() === false) {
			$this->db->trans_rollback();
			return array('ok' => false, 'error' => 'Database error');
		}
		$this->db->trans_commit();
		return array('ok' => true);
	}

	// =========================================================================
	// QUERIES
	// =========================================================================

	public function get_orders_for_encounter($iop_id)
	{
		$this->ensure_schema();
		$this->db->where('iop_id', trim((string)$iop_id));
		$this->db->where('InActive', 0);
		$this->db->order_by('created_at', 'DESC');
		return $this->db->get('consumable_orders')->result();
	}

	public function get_order($order_id)
	{
		$this->ensure_schema();
		return $this->db->get_where('consumable_orders', array('order_id' => (int)$order_id, 'InActive' => 0))->row();
	}

	public function get_order_by_no($order_no)
	{
		$this->ensure_schema();
		return $this->db->get_where('consumable_orders', array('order_no' => trim((string)$order_no), 'InActive' => 0))->row();
	}

	public function get_order_items($order_id)
	{
		$this->ensure_schema();
		$this->db->where('order_id', (int)$order_id);
		$this->db->where('InActive', 0);
		$this->db->order_by('item_id', 'ASC');
		return $this->db->get('consumable_order_items')->result();
	}

	public function get_pending_fulfillment($iop_id)
	{
		$this->ensure_schema();
		$this->db->select('ci.*, co.order_no, co.patient_no, co.payer_type');
		$this->db->from('consumable_order_items ci');
		$this->db->join('consumable_orders co', 'co.order_id = ci.order_id AND co.InActive = 0', 'inner');
		$this->db->where('co.iop_id', trim((string)$iop_id));
		$this->db->where('ci.fulfillment_status', 'PENDING');
		$this->db->where('ci.InActive', 0);
		$this->db->order_by('ci.created_at', 'ASC');
		return $this->db->get()->result();
	}

	public function search_consumable_catalog($term, $limit = 20)
	{
		$this->ensure_schema();
		$term = trim((string)$term);
		if (strlen($term) < 2) return array();

		$results = array();

		// Search bill_particular in consumable groups
		if ($this->db->table_exists('bill_particular') && $this->db->table_exists('bill_group_name')) {
			$this->db->select('bp.particular_id AS catalog_id, bp.particular_name AS item_name, bp.charge_amount AS unit_price, bg.group_name');
			$this->db->from('bill_particular bp');
			$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'inner');
			$this->db->where('bp.InActive', 0);
			$this->db->where_in('bg.group_name', array('NURSING CONSUMABLES', 'WOUND CARE SUPPLIES', 'IV & INFUSION SUPPLIES', 'CATHETER & DRAINAGE', 'RESPIRATORY SUPPLIES', 'WARD SERVICES', 'CONSUMABLES', 'WARD SUPPLIES'));
			$this->db->group_start();
			$this->db->like('bp.particular_name', $term);
			$this->db->group_end();
			$this->db->limit($limit);
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$results[] = array(
					'item_source' => 'PARTICULAR',
					'catalog_id'  => (int)$r->catalog_id,
					'item_name'   => (string)$r->item_name,
					'unit_price'  => (float)$r->unit_price,
					'group_name'  => (string)$r->group_name,
					'is_stock_backed' => 0,
				);
			}
		}

		// Search medicine_drug_name for stock consumables
		if ($this->db->table_exists('medicine_drug_name') && count($results) < $limit) {
			$remaining = $limit - count($results);
			$this->db->select('drug_id AS catalog_id, drug_name AS item_name, nPrice AS unit_price, nStock');
			$this->db->from('medicine_drug_name');
			$this->db->where('InActive', 0);
			$this->db->like('drug_name', $term);
			$this->db->limit($remaining);
			$rows = $this->db->get()->result();
			foreach ($rows as $r) {
				$results[] = array(
					'item_source' => 'DRUG',
					'catalog_id'  => (int)$r->catalog_id,
					'item_name'   => (string)$r->item_name,
					'unit_price'  => (float)$r->unit_price,
					'group_name'  => 'PHARMACY STOCK',
					'is_stock_backed' => 1,
					'stock_available' => (float)$r->nStock,
				);
			}
		}

		return $results;
	}

	// =========================================================================
	// HELPERS
	// =========================================================================

	private function _generate_order_no()
	{
		return 'CO' . date('YmdHis') . str_pad((string)mt_rand(0, 9999), 4, '0', STR_PAD_LEFT);
	}

	private function _refresh_order_status($order_id)
	{
		$stats = $this->db->query(
			"SELECT
				COUNT(*) AS total,
				SUM(CASE WHEN fulfillment_status = 'FULFILLED' THEN 1 ELSE 0 END) AS fulfilled,
				SUM(CASE WHEN fulfillment_status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled
			 FROM consumable_order_items WHERE order_id = ? AND InActive = 0",
			array($order_id)
		)->row();

		if (!$stats || (int)$stats->total === 0) return;

		$total = (int)$stats->total;
		$done = (int)$stats->fulfilled + (int)$stats->cancelled;
		$status = 'BILLED';
		if ($done >= $total) {
			$status = ((int)$stats->cancelled >= $total) ? 'CANCELLED' : 'FULFILLED';
		} elseif ((int)$stats->fulfilled > 0) {
			$status = 'PARTIALLY_FULFILLED';
		}

		$this->db->where('order_id', $order_id);
		$this->db->update('consumable_orders', array('order_status' => $status));
	}
}
