<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Walk-In Registration Model
 *
 * Manages walk-in clients and their transactions.
 * Walk-in clients are INDEPENDENT — they do NOT appear in OPD, IPD, or patient statistics.
 *
 * Tables managed:
 *   walkin_clients       — client registration
 *   walkin_transactions  — per-service billing records
 */
class Walkin_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
    }

    private function column_exists($table, $column)
    {
        $q = $this->db->query("SHOW COLUMNS FROM `" . $table . "` LIKE " . $this->db->escape($column));
        return ($q && $q->num_rows() > 0);
    }

    /* ═══════════════════════════════════════════════════════
     * SCHEMA MANAGEMENT
     * ═══════════════════════════════════════════════════════ */

    private function table_exists($t)
    {
        $q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape($t));
        return ($q && $q->num_rows() > 0);
    }

    public function ensure_schema()
    {
        // walkin_clients
        if (!$this->table_exists('walkin_clients')) {
            $this->db->query("CREATE TABLE `walkin_clients` (
                `id`           INT(11) NOT NULL AUTO_INCREMENT,
                `client_name`  VARCHAR(150) NOT NULL,
                `phone`        VARCHAR(20)  DEFAULT NULL,
                `gender`       ENUM('Male','Female','Other') DEFAULT NULL,
                `referral`     VARCHAR(200) DEFAULT NULL,
                `created_by`   VARCHAR(50)  DEFAULT NULL,
                `created_at`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_wc_name` (`client_name`),
                KEY `idx_wc_date` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

        // walkin_transactions
        if (!$this->table_exists('walkin_transactions')) {
            $this->db->query("CREATE TABLE `walkin_transactions` (
                `id`               INT(11) NOT NULL AUTO_INCREMENT,
                `walkin_client_id` INT(11) NOT NULL,
                `service_type`     ENUM('Laboratory','Sonography','Radiology','Pharmacy','Procedure','Consultation','Other') NOT NULL DEFAULT 'Other',
                `description`      VARCHAR(500) NOT NULL,
                `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `payment_method`   ENUM('Cash','NHIS','MoMo','Card','Cheque','Other') NOT NULL DEFAULT 'Cash',
                `payment_status`   ENUM('Paid','Pending','Cancelled') NOT NULL DEFAULT 'Paid',
                `receipt_number`   VARCHAR(30) DEFAULT NULL,
                `transaction_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `cashier_id`       VARCHAR(50) DEFAULT NULL,
                `cashier_name`     VARCHAR(150) DEFAULT NULL,
                `notes`            TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_wt_client`  (`walkin_client_id`),
                KEY `idx_wt_date`    (`transaction_date`),
                KEY `idx_wt_receipt` (`receipt_number`),
                CONSTRAINT `fk_wt_client` FOREIGN KEY (`walkin_client_id`) REFERENCES `walkin_clients`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

		if ($this->table_exists('walkin_transactions')) {
			$col = $this->db->query("SHOW COLUMNS FROM `walkin_transactions` LIKE 'service_type'")->row();
			if ($col && isset($col->Type) && strpos((string)$col->Type, 'Radiology') === false) {
				$this->db->query("ALTER TABLE `walkin_transactions` MODIFY `service_type` ENUM('Laboratory','Sonography','Radiology','Pharmacy','Procedure','Consultation','Other') NOT NULL DEFAULT 'Other'");
			}
		}

        // walkin_transactions stock flags (safe to call multiple times)
        if ($this->table_exists('walkin_transactions')) {
            if (!$this->column_exists('walkin_transactions', 'stock_deducted')) {
                $this->db->query("ALTER TABLE `walkin_transactions` ADD COLUMN `stock_deducted` TINYINT(1) NOT NULL DEFAULT 0");
            }
            if (!$this->column_exists('walkin_transactions', 'stock_deducted_at')) {
                $this->db->query("ALTER TABLE `walkin_transactions` ADD COLUMN `stock_deducted_at` DATETIME DEFAULT NULL");
            }
            if (!$this->column_exists('walkin_transactions', 'stock_deducted_by')) {
                $this->db->query("ALTER TABLE `walkin_transactions` ADD COLUMN `stock_deducted_by` VARCHAR(50) DEFAULT NULL");
            }
            if (!$this->column_exists('walkin_transactions', 'stock_reversed_at')) {
                $this->db->query("ALTER TABLE `walkin_transactions` ADD COLUMN `stock_reversed_at` DATETIME DEFAULT NULL");
            }
            if (!$this->column_exists('walkin_transactions', 'stock_reversed_by')) {
                $this->db->query("ALTER TABLE `walkin_transactions` ADD COLUMN `stock_reversed_by` VARCHAR(50) DEFAULT NULL");
            }
        }

        // Itemized pharmacy sales
        if (!$this->table_exists('walkin_transaction_items')) {
            $this->db->query("CREATE TABLE `walkin_transaction_items` (
                `item_id` INT(11) NOT NULL AUTO_INCREMENT,
                `txn_id` INT(11) NOT NULL,
                `drug_id` INT(11) NOT NULL,
                `drug_name` VARCHAR(255) DEFAULT NULL,
                `qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`item_id`),
                KEY `idx_wti_txn` (`txn_id`),
                KEY `idx_wti_drug` (`drug_id`),
                CONSTRAINT `fk_wti_txn` FOREIGN KEY (`txn_id`) REFERENCES `walkin_transactions`(`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }

		if (!$this->table_exists('walkin_transaction_service_items')) {
			$this->db->query("CREATE TABLE `walkin_transaction_service_items` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`txn_id` INT(11) NOT NULL,
				`service_type` VARCHAR(30) NOT NULL,
				`catalog_type` VARCHAR(30) NOT NULL,
				`catalog_item_id` INT(11) NOT NULL,
				`item_name` VARCHAR(255) NOT NULL,
				`qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
				`unit_price` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
				`line_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `idx_wtsi_txn` (`txn_id`),
				CONSTRAINT `fk_wtsi_txn` FOREIGN KEY (`txn_id`) REFERENCES `walkin_transactions`(`id`) ON DELETE CASCADE
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}

		if (!$this->table_exists('walkin_consultation_types')) {
			$this->db->query("CREATE TABLE `walkin_consultation_types` (
				`id` INT(11) NOT NULL AUTO_INCREMENT,
				`name` VARCHAR(150) NOT NULL,
				`price_cash` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
				`price_nhis` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME DEFAULT NULL,
				`created_by` VARCHAR(50) DEFAULT NULL,
				`updated_by` VARCHAR(50) DEFAULT NULL,
				`InActive` TINYINT(1) NOT NULL DEFAULT 0,
				PRIMARY KEY (`id`),
				KEY `idx_wct_name` (`name`),
				KEY `idx_wct_active` (`InActive`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
		}

		if ($this->table_exists('walkin_consultation_types')) {
			$hasAny = $this->db->query("SELECT id FROM walkin_consultation_types WHERE InActive = 0 LIMIT 1")->row();
			if (!$hasAny) {
				$now = date('Y-m-d H:i:s');
				$seed = array(
					array('General Consultation', 30.00, 0.00),
					array('Specialist Consultation', 50.00, 0.00),
					array('Follow-up Consultation', 20.00, 0.00),
					array('Emergency Consultation', 60.00, 0.00),
				);
				foreach ($seed as $s) {
					$this->db->insert('walkin_consultation_types', array(
						'name' => (string)$s[0],
						'price_cash' => (float)$s[1],
						'price_nhis' => (float)$s[2],
						'created_at' => $now,
						'InActive' => 0,
					));
				}
			}
		}

        // Batch-level trace for FEFO deductions (optional but critical for reversals)
        if (!$this->table_exists('walkin_transaction_item_batches')) {
            $this->db->query("CREATE TABLE `walkin_transaction_item_batches` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `txn_item_id` INT(11) NOT NULL,
                `stock_id` INT(11) DEFAULT NULL,
                `batch_number` VARCHAR(50) DEFAULT NULL,
                `expiry_date` DATE DEFAULT NULL,
                `qty` DECIMAL(10,2) NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_wtib_item` (`txn_item_id`),
                KEY `idx_wtib_stock` (`stock_id`),
                CONSTRAINT `fk_wtib_item` FOREIGN KEY (`txn_item_id`) REFERENCES `walkin_transaction_items`(`item_id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
        return true;
    }

    /* ═══════════════════════════════════════════════════════
     * RECEIPT NUMBER GENERATION
     * ═══════════════════════════════════════════════════════ */

    public function generate_receipt_number()
    {
        $prefix = 'WLK-' . date('Ymd') . '-';
        $q = $this->db->query(
            "SELECT MAX(CAST(SUBSTRING_INDEX(receipt_number, '-', -1) AS UNSIGNED)) AS last_seq
             FROM walkin_transactions
             WHERE receipt_number LIKE " . $this->db->escape($prefix . '%') . "
             AND DATE(transaction_date) = CURDATE()"
        );
        $row = $q ? $q->row() : null;
        $seq = ($row && $row->last_seq) ? ((int)$row->last_seq + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    /* ═══════════════════════════════════════════════════════
     * CLIENT MANAGEMENT
     * ═══════════════════════════════════════════════════════ */

    public function register_client($data)
    {
        $insert = array(
            'client_name' => trim((string)$data['client_name']),
            'phone'       => trim((string)($data['phone'] ?? '')),
            'gender'      => in_array($data['gender'] ?? '', ['Male','Female','Other']) ? $data['gender'] : null,
            'referral'    => trim((string)($data['referral'] ?? '')),
            'created_by'  => trim((string)($data['created_by'] ?? '')),
            'created_at'  => date('Y-m-d H:i:s'),
        );
        $this->db->insert('walkin_clients', $insert);
        return $this->db->insert_id();
    }

    public function get_client($id)
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        $q = $this->db->get_where('walkin_clients', ['id' => $id]);
        return $q ? $q->row() : null;
    }

    public function search_clients($term = '', $limit = 30)
    {
        $limit = max(1, (int)$limit);
        $this->db->select('id, client_name, phone, gender, referral, created_at');
        $this->db->from('walkin_clients');
        if ($term !== '') {
            $t = $this->db->escape_like_str($term);
            $this->db->where("(client_name LIKE '%{$t}%' OR phone LIKE '%{$t}%')", null, false);
        }
        $this->db->order_by('created_at', 'DESC');
        $this->db->limit($limit);
        $q = $this->db->get();
        return $q ? $q->result() : [];
    }

	public function get_consultation_types($include_inactive = false)
	{
		$this->ensure_schema();
		if (!$this->table_exists('walkin_consultation_types')) return array();
		$this->db->from('walkin_consultation_types');
		if (!$include_inactive) {
			$this->db->where('InActive', 0);
		}
		$this->db->order_by('name', 'ASC');
		$q = $this->db->get();
		return $q ? $q->result() : array();
	}

	public function get_consultation_type($id)
	{
		$this->ensure_schema();
		$id = (int)$id;
		if ($id <= 0 || !$this->table_exists('walkin_consultation_types')) return null;
		$q = $this->db->get_where('walkin_consultation_types', array('id' => $id));
		return $q ? $q->row() : null;
	}

	public function save_consultation_type($data, $actor_user_id = null)
	{
		$this->ensure_schema();
		if (!$this->table_exists('walkin_consultation_types')) return false;

		$id = isset($data['id']) ? (int)$data['id'] : 0;
		$name = isset($data['name']) ? trim((string)$data['name']) : '';
		$price_cash = isset($data['price_cash']) ? (float)$data['price_cash'] : 0.0;
		$price_nhis = isset($data['price_nhis']) ? (float)$data['price_nhis'] : 0.0;
		$inactive = isset($data['InActive']) ? ((int)$data['InActive'] ? 1 : 0) : 0;

		if ($name === '') return false;
		if ($price_cash < 0) $price_cash = 0.0;
		if ($price_nhis < 0) $price_nhis = 0.0;

		$now = date('Y-m-d H:i:s');
		$payload = array(
			'name' => $name,
			'price_cash' => $price_cash,
			'price_nhis' => $price_nhis,
			'InActive' => $inactive,
			'updated_at' => $now,
			'updated_by' => $actor_user_id !== null ? (string)$actor_user_id : null,
		);
		if ($id > 0) {
			$this->db->where('id', $id);
			return $this->db->update('walkin_consultation_types', $payload);
		}
		$payload['created_at'] = $now;
		$payload['created_by'] = $actor_user_id !== null ? (string)$actor_user_id : null;
		$payload['updated_at'] = null;
		$payload['updated_by'] = null;
		return $this->db->insert('walkin_consultation_types', $payload);
	}

	public function search_consultation_types($term = '', $payment_method = 'Cash', $limit = 20)
	{
		$this->ensure_schema();
		$limit = max(1, (int)$limit);
		if (!$this->table_exists('walkin_consultation_types')) return array();
		$pm = strtoupper(trim((string)$payment_method));
		if ($pm === '') $pm = 'CASH';
		$useNhis = ($pm === 'NHIS');

		$this->db->select('id, name, price_cash, price_nhis');
		$this->db->from('walkin_consultation_types');
		$this->db->where('InActive', 0);
		if ($term !== '') {
			$t = $this->db->escape_like_str($term);
			$this->db->where("name LIKE '%{$t}%'", null, false);
		}
		$this->db->order_by('name', 'ASC');
		$this->db->limit($limit);
		$q = $this->db->get();
		$rows = $q ? $q->result() : array();
		$out = array();
		foreach ($rows as $r) {
			$price = $useNhis ? (float)$r->price_nhis : (float)$r->price_cash;
			$out[] = array(
				'item_id' => (int)$r->id,
				'label' => (string)$r->name,
				'value' => (string)$r->name,
				'unit_price' => $price,
			);
		}
		return $out;
	}

    /* ═══════════════════════════════════════════════════════
     * TRANSACTION MANAGEMENT
     * ═══════════════════════════════════════════════════════ */

    public function add_transaction($data)
    {
        $allowed_services  = ['Laboratory','Sonography','Radiology','Pharmacy','Procedure','Consultation','Other'];
        $allowed_payments  = ['Cash','NHIS','MoMo','Card','Cheque','Other'];
        $allowed_statuses  = ['Paid','Pending','Cancelled'];

        $receipt = $this->generate_receipt_number();

        $insert = array(
            'walkin_client_id' => (int)$data['walkin_client_id'],
            'service_type'     => in_array($data['service_type'] ?? '', $allowed_services) ? $data['service_type'] : 'Other',
            'description'      => trim((string)($data['description'] ?? '')),
            'amount'           => round((float)($data['amount'] ?? 0), 2),
            'payment_method'   => in_array($data['payment_method'] ?? '', $allowed_payments) ? $data['payment_method'] : 'Cash',
            'payment_status'   => in_array($data['payment_status'] ?? '', $allowed_statuses) ? $data['payment_status'] : 'Paid',
            'receipt_number'   => $receipt,
            'transaction_date' => date('Y-m-d H:i:s'),
            'cashier_id'       => trim((string)($data['cashier_id'] ?? '')),
            'cashier_name'     => trim((string)($data['cashier_name'] ?? '')),
            'notes'            => trim((string)($data['notes'] ?? '')),
        );

        $this->db->insert('walkin_transactions', $insert);
        $txn_id = $this->db->insert_id();
        if (!$txn_id) return false;
        return array('id' => $txn_id, 'receipt_number' => $receipt);
    }

    public function add_pharmacy_transaction($data, $items)
    {
        $this->ensure_schema();

        $allowed_payments = ['Cash','MoMo','Card','Cheque','Other'];
        $allowed_statuses = ['Paid','Pending','Cancelled'];

        $payment_method = trim((string)($data['payment_method'] ?? 'Cash'));
        if (!in_array($payment_method, $allowed_payments, true)) {
            return array('success' => false, 'error' => 'Invalid payment method for Pharmacy walk-in.');
        }

        $payment_status = trim((string)($data['payment_status'] ?? 'Paid'));
        if (!in_array($payment_status, $allowed_statuses, true)) {
            $payment_status = 'Paid';
        }

        if (!is_array($items) || count($items) === 0) {
            return array('success' => false, 'error' => 'Please add at least one pharmacy item.');
        }

        $cleanItems = array();
        $total = 0.00;

        foreach ($items as $it) {
            $drug_id = isset($it['drug_id']) ? (int)$it['drug_id'] : 0;
            $qty = isset($it['qty']) ? (float)$it['qty'] : 0;
            $unit_price = isset($it['unit_price']) ? (float)$it['unit_price'] : 0;
            if ($drug_id <= 0 || $qty <= 0 || $unit_price < 0) {
                continue;
            }
            $line_total = round($qty * $unit_price, 2);
            $total += $line_total;
            $cleanItems[] = array(
                'drug_id' => $drug_id,
                'qty' => $qty,
                'unit_price' => round($unit_price, 2),
                'line_total' => $line_total
            );
        }

        if (count($cleanItems) === 0 || $total <= 0) {
            return array('success' => false, 'error' => 'Invalid pharmacy item list.');
        }

        $receipt = $this->generate_receipt_number();

        $descParts = array();
        foreach ($cleanItems as $ci) {
            $drow = $this->db->get_where('medicine_drug_name', array('drug_id' => (int)$ci['drug_id'], 'InActive' => 0))->row();
            $dname = $drow && isset($drow->drug_name) ? (string)$drow->drug_name : ('Drug #' . (int)$ci['drug_id']);
            $descParts[] = $dname . ' x' . rtrim(rtrim(number_format((float)$ci['qty'], 2, '.', ''), '0'), '.');
        }
        $description = 'Pharmacy: ' . implode(', ', $descParts);
        if (strlen($description) > 500) {
            $description = substr($description, 0, 500);
        }

        $this->db->trans_start();

        $insert = array(
            'walkin_client_id' => (int)$data['walkin_client_id'],
            'service_type' => 'Pharmacy',
            'description' => $description,
            'amount' => round($total, 2),
            'payment_method' => $payment_method,
            'payment_status' => $payment_status,
            'receipt_number' => $receipt,
            'transaction_date' => date('Y-m-d H:i:s'),
            'cashier_id' => trim((string)($data['cashier_id'] ?? '')),
            'cashier_name' => trim((string)($data['cashier_name'] ?? '')),
            'notes' => trim((string)($data['notes'] ?? '')),
        );

        $this->db->insert('walkin_transactions', $insert);
        $txn_id = (int)$this->db->insert_id();
        if ($txn_id <= 0) {
            $this->db->trans_complete();
            return array('success' => false, 'error' => 'Failed to create transaction.');
        }

        $itemIdMap = array();
        foreach ($cleanItems as $ci) {
            $drow = $this->db->get_where('medicine_drug_name', array('drug_id' => (int)$ci['drug_id']))->row();
            $dname = $drow && isset($drow->drug_name) ? (string)$drow->drug_name : null;
            $this->db->insert('walkin_transaction_items', array(
                'txn_id' => $txn_id,
                'drug_id' => (int)$ci['drug_id'],
                'drug_name' => $dname,
                'qty' => (float)$ci['qty'],
                'unit_price' => (float)$ci['unit_price'],
                'line_total' => (float)$ci['line_total'],
                'created_at' => date('Y-m-d H:i:s'),
            ));
            $itemId = (int)$this->db->insert_id();
            $itemIdMap[] = array('item_id' => $itemId, 'drug_id' => (int)$ci['drug_id'], 'qty' => (float)$ci['qty']);
        }

        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            return array('success' => false, 'error' => 'Transaction failed.');
        }

        return array('success' => true, 'id' => $txn_id, 'receipt_number' => $receipt);
    }

	public function add_catalog_service_transaction($data, $items)
	{
		$this->ensure_schema();
		$allowed_services = array('Laboratory', 'Sonography', 'Radiology', 'Procedure', 'Consultation', 'Other');
		$allowed_payments = array('Cash','NHIS','MoMo','Card','Cheque','Other');
		$allowed_statuses = array('Paid','Pending','Cancelled');
		$service_type = isset($data['service_type']) ? trim((string)$data['service_type']) : 'Other';
		if (!in_array($service_type, $allowed_services, true)) {
			return array('success' => false, 'error' => 'Invalid service type');
		}
		$payment_method = isset($data['payment_method']) ? trim((string)$data['payment_method']) : 'Cash';
		if (!in_array($payment_method, $allowed_payments, true)) {
			$payment_method = 'Cash';
		}
		$payment_status = isset($data['payment_status']) ? trim((string)$data['payment_status']) : 'Paid';
		if (!in_array($payment_status, $allowed_statuses, true)) {
			$payment_status = 'Paid';
		}
		$walkin_client_id = isset($data['walkin_client_id']) ? (int)$data['walkin_client_id'] : 0;
		if ($walkin_client_id <= 0) {
			return array('success' => false, 'error' => 'Invalid client');
		}
		if (!is_array($items) || count($items) === 0) {
			return array('success' => false, 'error' => 'Please add at least one item');
		}

		$catalog_items = array();
		$total = 0.0;
		foreach ($items as $it) {
			$item_id = isset($it['item_id']) ? (int)$it['item_id'] : 0;
			$qty = isset($it['qty']) ? (float)$it['qty'] : (isset($it['qty']) ? (float)$it['qty'] : (isset($it['qty']) ? (float)$it['qty'] : 0.0));
			if ($qty <= 0 && isset($it['qty']) === false && isset($it['qty']) === false) {
				$qty = isset($it['qty']) ? (float)$it['qty'] : 0.0;
			}
			if ($qty <= 0) {
				$qty = isset($it['qty']) ? (float)$it['qty'] : (isset($it['qty']) ? (float)$it['qty'] : 0.0);
			}
			if ($item_id <= 0) {
				$item_id = isset($it['item_id']) ? (int)$it['item_id'] : 0;
			}
			$qty = isset($it['qty']) ? (float)$it['qty'] : (isset($it['qty']) ? (float)$it['qty'] : 0.0);
			if ($item_id <= 0 || $qty <= 0) {
				continue;
			}

			$name = '';
			$unit = 0.0;
			$catalog_type = '';

			if ($service_type === 'Sonography') {
				$this->load->model('app/Ghana_test_catalog_model');
				if (isset($this->Ghana_test_catalog_model) && method_exists($this->Ghana_test_catalog_model, 'ensure_catalog_tables')) {
					$this->Ghana_test_catalog_model->ensure_catalog_tables();
				}
				$row = $this->db->get_where('ghs_sonography_tests', array('test_id' => $item_id, 'InActive' => 0))->row();
				$name = ($row && isset($row->test_name)) ? (string)$row->test_name : '';
				$unit = ($row && isset($row->price)) ? (float)$row->price : 0.0;
				$catalog_type = 'SONOGRAPHY_TEST';
			} elseif ($service_type === 'Radiology') {
				$this->load->model('app/radiology_model');
				if (isset($this->radiology_model) && method_exists($this->radiology_model, 'ensure_radiology_schema')) {
					$this->radiology_model->ensure_radiology_schema();
				}
				$row = $this->db->get_where('radiology_test_master', array('id' => $item_id, 'InActive' => 0))->row();
				$name = ($row && isset($row->test_name)) ? (string)$row->test_name : '';
				$unit = ($row && isset($row->price)) ? (float)$row->price : 0.0;
				$catalog_type = 'RADIOLOGY_TEST';
			} elseif ($service_type === 'Consultation') {
				if (!$this->table_exists('walkin_consultation_types')) {
					return array('success' => false, 'error' => 'Consultation types not configured');
				}
				$row = $this->db->get_where('walkin_consultation_types', array('id' => $item_id, 'InActive' => 0))->row();
				$name = ($row && isset($row->name)) ? (string)$row->name : '';
				$isNhis = (strtoupper(trim((string)$payment_method)) === 'NHIS');
				$unit = $row ? ($isNhis ? (float)$row->price_nhis : (float)$row->price_cash) : 0.0;
				$catalog_type = 'CONSULTATION_TYPE';
			} else {
				$this->load->model('app/billing_model');
				$unitCol = $this->column_exists('bill_particular', 'charge_amount') ? 'charge_amount' : null;
				if (!$unitCol) {
					$unitCol = '0';
				}
				$this->db->select('bp.particular_name, bp.charge_amount, bg.group_name', false);
				$this->db->from('bill_particular bp');
				if ($this->table_exists('bill_group_name')) {
					$this->db->join('bill_group_name bg', 'bg.group_id = bp.group_id', 'left');
				}
				$this->db->where('bp.particular_id', $item_id);
				$this->db->where('bp.InActive', 0);
				if ($service_type === 'Laboratory') {
					$lab_groups = array('HAEMATOLOGY', 'BIOCHEMISTRY', 'CLINICAL PATHOLOGY', 'MICROBIOLOGY', 'SEROLOGY', 'SPECIAL TESTS', 'HISTOPATHOLOGY', 'TRANSFUSION MEDICINE');
					$this->db->where_in('bg.group_name', $lab_groups);
				} elseif ($service_type === 'Procedure') {
					$this->db->where('bg.group_name', 'PROCEDURES');
				}
				$row = $this->db->get()->row();
				$name = ($row && isset($row->particular_name)) ? (string)$row->particular_name : '';
				$unit = ($row && isset($row->charge_amount)) ? (float)$row->charge_amount : 0.0;
				$catalog_type = 'BILL_PARTICULAR';
			}
			if ($name === '' || $unit <= 0) {
				return array('success' => false, 'error' => 'Invalid item selection');
			}
			$line = round($qty * $unit, 2);
			$total += $line;
			$catalog_items[] = array(
				'catalog_type' => $catalog_type,
				'catalog_item_id' => $item_id,
				'item_name' => $name,
				'qty' => $qty,
				'unit_price' => $unit,
				'line_total' => $line,
			);
		}

		if (count($catalog_items) === 0) {
			return array('success' => false, 'error' => 'No valid items selected');
		}

		$descParts = array();
		foreach ($catalog_items as $ci) {
			$descParts[] = $ci['item_name'] . ' x' . rtrim(rtrim(number_format((float)$ci['qty'], 2, '.', ''), '0'), '.');
		}
		$autoDesc = implode(', ', $descParts);
		$extra = isset($data['extra_description']) ? trim((string)$data['extra_description']) : '';
		$description = $extra !== '' ? $extra : $autoDesc;
		$description = substr($description, 0, 500);

		$receipt = $this->generate_receipt_number();
		$this->db->trans_start();
		$this->db->insert('walkin_transactions', array(
			'walkin_client_id' => $walkin_client_id,
			'service_type' => $service_type,
			'description' => $description,
			'amount' => round((float)$total, 2),
			'payment_method' => $payment_method,
			'payment_status' => $payment_status,
			'receipt_number' => $receipt,
			'transaction_date' => date('Y-m-d H:i:s'),
			'cashier_id' => isset($data['cashier_id']) ? (string)$data['cashier_id'] : null,
			'cashier_name' => isset($data['cashier_name']) ? (string)$data['cashier_name'] : null,
			'notes' => isset($data['notes']) ? (string)$data['notes'] : null,
		));
		$txn_id = (int)$this->db->insert_id();
		if ($txn_id > 0) {
			foreach ($catalog_items as $ci) {
				$this->db->insert('walkin_transaction_service_items', array(
					'txn_id' => $txn_id,
					'service_type' => $service_type,
					'catalog_type' => $ci['catalog_type'],
					'catalog_item_id' => (int)$ci['catalog_item_id'],
					'item_name' => $ci['item_name'],
					'qty' => (float)$ci['qty'],
					'unit_price' => (float)$ci['unit_price'],
					'line_total' => (float)$ci['line_total'],
				));
			}
		}
		$this->db->trans_complete();
		if (!$txn_id) {
			return array('success' => false, 'error' => 'Failed to save transaction');
		}
		return array('success' => true, 'id' => $txn_id, 'receipt_number' => $receipt);
	}

    private function deduct_pharmacy_stock_for_transaction($txn_id, $itemIdMap, $user_id)
    {
        $txn_id = (int)$txn_id;
        if ($txn_id <= 0) return array('success' => false, 'error' => 'Invalid transaction id');
        if (!is_array($itemIdMap) || count($itemIdMap) === 0) return array('success' => false, 'error' => 'No items');

        $this->load->model('app/pharmacy_model');
        $this->load->model('app/pharmacy_stock_model');

        $hasBatchTable = $this->table_exists('medication_stock');
        $hasQtyRemaining = $hasBatchTable && $this->db->field_exists('quantity_remaining', 'medication_stock');
        $hasQty = $hasBatchTable && $this->db->field_exists('quantity', 'medication_stock');

        foreach ($itemIdMap as $row) {
            $txn_item_id = isset($row['item_id']) ? (int)$row['item_id'] : 0;
            $drug_id = isset($row['drug_id']) ? (int)$row['drug_id'] : 0;
            $qty = isset($row['qty']) ? (float)$row['qty'] : 0;
            if ($txn_item_id <= 0 || $drug_id <= 0 || $qty <= 0) {
                return array('success' => false, 'error' => 'Invalid stock item data');
            }

            $stockRow = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id, 'InActive' => 0))->row();
            if (!$stockRow) {
                return array('success' => false, 'error' => 'Drug not found: #' . $drug_id);
            }

            $available = (float)$stockRow->nStock;

            // If batch tracking exists, validate against batch totals where possible.
            if ($hasQtyRemaining) {
                $sum = $this->db->query("SELECT COALESCE(SUM(quantity_remaining),0) AS total FROM medication_stock WHERE medication_id = ? AND InActive = 0 AND quantity_remaining > 0", array($drug_id))->row();
                $available = $sum ? (float)$sum->total : $available;
            } elseif ($hasQty) {
                $sum = $this->db->query("SELECT COALESCE(SUM(quantity),0) AS total FROM medication_stock WHERE medication_id = ? AND InActive = 0 AND quantity > 0", array($drug_id))->row();
                $available = $sum ? (float)$sum->total : $available;
            }

            if ($available < $qty) {
                return array('success' => false, 'error' => 'Insufficient stock for ' . (isset($stockRow->drug_name) ? $stockRow->drug_name : ('Drug #' . $drug_id)) . '. Available: ' . $available . ', Requested: ' . $qty);
            }

            // Decide whether to use FEFO
            $hasBatchesForDrug = false;
            if ($hasQtyRemaining) {
                $c = $this->db->query("SELECT COUNT(*) AS c FROM medication_stock WHERE medication_id = ? AND InActive = 0 AND quantity_remaining > 0", array($drug_id))->row();
                $hasBatchesForDrug = $c ? ((int)$c->c > 0) : false;
            } elseif ($hasQty) {
                $c = $this->db->query("SELECT COUNT(*) AS c FROM medication_stock WHERE medication_id = ? AND InActive = 0 AND quantity > 0", array($drug_id))->row();
                $hasBatchesForDrug = $c ? ((int)$c->c > 0) : false;
            }

            if ($hasBatchesForDrug && $hasQtyRemaining) {
                $res = $this->pharmacy_stock_model->deduct_batch_stock_fefo($drug_id, $qty, $user_id, 'WALKIN_SALE', $txn_id);
                if (!$res || !isset($res['success']) || !$res['success']) {
                    $sf = isset($res['shortfall']) ? (float)$res['shortfall'] : 0;
                    return array('success' => false, 'error' => 'Batch deduction failed. Shortfall: ' . $sf);
                }
                if (isset($res['deducted']) && is_array($res['deducted'])) {
                    foreach ($res['deducted'] as $d) {
                        $stock_id = isset($d['stock_id']) ? (int)$d['stock_id'] : null;
                        $batch_no = isset($d['batch_number']) ? $d['batch_number'] : null;
                        $dqty = isset($d['qty']) ? (float)$d['qty'] : 0;
                        $exp = null;
                        if ($stock_id) {
                            $brow = $this->db->get_where('medication_stock', array('stock_id' => $stock_id))->row();
                            if ($brow && isset($brow->expiry_date)) {
                                $exp = $brow->expiry_date;
                            }
                        }
                        $this->db->insert('walkin_transaction_item_batches', array(
                            'txn_item_id' => $txn_item_id,
                            'stock_id' => $stock_id,
                            'batch_number' => $batch_no,
                            'expiry_date' => $exp,
                            'qty' => $dqty,
                            'created_at' => date('Y-m-d H:i:s'),
                        ));
                    }
                }
            } elseif ($hasBatchesForDrug && $hasQty) {
                $remaining = $qty;
                $batches = $this->db->query(
                    "SELECT stock_id, batch_number, expiry_date, quantity FROM medication_stock " .
                    "WHERE medication_id = ? AND InActive = 0 AND quantity > 0 " .
                    "AND (expiry_date IS NULL OR expiry_date >= ?) " .
                    "ORDER BY expiry_date ASC, created_at ASC FOR UPDATE",
                    array($drug_id, date('Y-m-d'))
                )->result();

                foreach ($batches as $b) {
                    if ($remaining <= 0) break;
                    $availableQty = (float)$b->quantity;
                    $take = min($availableQty, $remaining);
                    $this->db->set('quantity', 'quantity - ' . (float)$take, false);
                    $this->db->where('stock_id', (int)$b->stock_id);
                    $this->db->where('quantity >=', (float)$take);
                    $this->db->update('medication_stock');
                    if ((int)$this->db->affected_rows() <= 0) {
                        return array('success' => false, 'error' => 'Batch deduction failed. Shortfall: ' . $remaining);
                    }

                    $this->db->insert('walkin_transaction_item_batches', array(
                        'txn_item_id' => $txn_item_id,
                        'stock_id' => (int)$b->stock_id,
                        'batch_number' => isset($b->batch_number) ? $b->batch_number : null,
                        'expiry_date' => isset($b->expiry_date) ? $b->expiry_date : null,
                        'qty' => (float)$take,
                        'created_at' => date('Y-m-d H:i:s'),
                    ));

                    $remaining -= $take;
                }

                if ($remaining > 0) {
                    return array('success' => false, 'error' => 'Batch deduction failed. Shortfall: ' . $remaining);
                }

                $this->sync_master_stock_from_batches($drug_id);

                if ($this->table_exists('pharmacy_stock_adjustment')) {
                    $after = $this->get_master_stock($drug_id);
                    $before = $after + $qty;
                    $this->db->insert('pharmacy_stock_adjustment', array(
                        'drug_id' => $drug_id,
                        'adjustment_type' => 'BATCH_DISPENSE',
                        'qty_change' => -$qty,
                        'stock_before' => $before,
                        'stock_after' => $after,
                        'reason' => 'Walk-in pharmacy sale (batch FEFO)',
                        'reference_type' => 'WALKIN_SALE',
                        'reference_id' => $txn_id,
                        'created_at' => date('Y-m-d H:i:s'),
                        'created_by' => $user_id
                    ));
                }
            } else {
                // Master stock only
                $ok = $this->pharmacy_model->deduct_stock($drug_id, $qty, $user_id, 'WALKIN_SALE', $txn_id);
                if (!$ok) {
                    return array('success' => false, 'error' => 'Stock deduction failed for Drug #' . $drug_id);
                }
            }
        }

        return array('success' => true, 'error' => '');
    }

    private function get_master_stock($drug_id)
    {
        $drug_id = (int)$drug_id;
        if ($drug_id <= 0) return 0;
        $r = $this->db->get_where('medicine_drug_name', array('drug_id' => $drug_id))->row();
        return ($r && isset($r->nStock)) ? (float)$r->nStock : 0;
    }

    private function sync_master_stock_from_batches($drug_id)
    {
        $drug_id = (int)$drug_id;
        if ($drug_id <= 0) return;
        if (!$this->table_exists('medication_stock')) return;

        if ($this->db->field_exists('quantity_remaining', 'medication_stock')) {
            $sum = $this->db->query(
                "SELECT COALESCE(SUM(quantity_remaining),0) AS total FROM medication_stock " .
                "WHERE medication_id = ? AND InActive = 0 AND quantity_remaining > 0 " .
                "AND (expiry_date IS NULL OR expiry_date >= ?)",
                array($drug_id, date('Y-m-d'))
            )->row();
            $total = $sum ? (float)$sum->total : 0;
            $this->db->where('drug_id', $drug_id);
            $this->db->update('medicine_drug_name', array('nStock' => $total));
            return;
        }

        if ($this->db->field_exists('quantity', 'medication_stock')) {
            $sum = $this->db->query(
                "SELECT COALESCE(SUM(quantity),0) AS total FROM medication_stock " .
                "WHERE medication_id = ? AND InActive = 0 AND quantity > 0 " .
                "AND (expiry_date IS NULL OR expiry_date >= ?)",
                array($drug_id, date('Y-m-d'))
            )->row();
            $total = $sum ? (float)$sum->total : 0;
            $this->db->where('drug_id', $drug_id);
            $this->db->update('medicine_drug_name', array('nStock' => $total));
        }
    }

    public function get_transaction_items($txn_id)
    {
        $txn_id = (int)$txn_id;
        if ($txn_id <= 0 || !$this->table_exists('walkin_transaction_items')) return array();
        $this->db->where('txn_id', $txn_id);
        $this->db->order_by('item_id', 'ASC');
        $q = $this->db->get('walkin_transaction_items');
        return $q ? $q->result() : array();
    }

	public function get_transaction_service_items($txn_id)
	{
		$txn_id = (int)$txn_id;
		if ($txn_id <= 0 || !$this->table_exists('walkin_transaction_service_items')) return array();
		$this->db->where('txn_id', $txn_id);
		$this->db->order_by('id', 'ASC');
		$q = $this->db->get('walkin_transaction_service_items');
		return $q ? $q->result() : array();
	}

    public function get_transaction($id)
    {
        $id = (int)$id;
        if ($id <= 0) return null;
        $this->db->select('wt.*, wc.client_name, wc.phone, wc.gender, wc.referral');
        $this->db->from('walkin_transactions wt');
        $this->db->join('walkin_clients wc', 'wc.id = wt.walkin_client_id', 'left');
        $this->db->where('wt.id', $id);
        $q = $this->db->get();
        return $q ? $q->row() : null;
    }

    public function get_transaction_by_receipt($receipt)
    {
        $receipt = trim((string)$receipt);
        if ($receipt === '') return null;
        $this->db->select('wt.*, wc.client_name, wc.phone, wc.gender, wc.referral');
        $this->db->from('walkin_transactions wt');
        $this->db->join('walkin_clients wc', 'wc.id = wt.walkin_client_id', 'left');
        $this->db->where('wt.receipt_number', $receipt);
        $q = $this->db->get();
        return $q ? $q->row() : null;
    }

    public function get_client_transactions($walkin_client_id)
    {
        $id = (int)$walkin_client_id;
        $this->db->where('walkin_client_id', $id);
        $this->db->order_by('transaction_date', 'DESC');
        $q = $this->db->get('walkin_transactions');
        return $q ? $q->result() : [];
    }

    public function cancel_transaction($id, $cashier_id)
    {
        $id = (int)$id;
        if ($id <= 0) return false;

        $this->ensure_schema();
        $txn = $this->get_transaction($id);
        if (!$txn) return false;

        if (isset($txn->payment_status) && $txn->payment_status === 'Cancelled') {
            return false;
        }

        $this->db->trans_start();

        $txn_lock = $this->db->query(
            "SELECT service_type, payment_status, stock_deducted FROM walkin_transactions WHERE id = ? FOR UPDATE",
            array($id)
        )->row();
        if (!$txn_lock) {
            $this->db->trans_complete();
            return false;
        }
        if (isset($txn_lock->payment_status) && $txn_lock->payment_status === 'Cancelled') {
            $this->db->trans_complete();
            return false;
        }

        // Reverse stock only if it was deducted
        if (isset($txn_lock->service_type) && $txn_lock->service_type === 'Pharmacy' && isset($txn_lock->stock_deducted) && (int)$txn_lock->stock_deducted === 1) {
            $revOk = $this->reverse_pharmacy_stock_for_transaction($id, $cashier_id);
            if (!$revOk['success']) {
                $this->db->trans_complete();
                return false;
            }
            $this->db->where('id', $id);
            $this->db->update('walkin_transactions', array(
                'stock_reversed_at' => date('Y-m-d H:i:s'),
                'stock_reversed_by' => (string)$cashier_id,
                'stock_deducted' => 0
            ));
        }

        $this->db->where('id', $id);
        $this->db->update('walkin_transactions', array(
            'payment_status' => 'Cancelled',
            'notes' => 'Cancelled by user_id:' . $cashier_id . ' at ' . date('Y-m-d H:i:s')
        ));

        $this->db->trans_complete();
        return $this->db->trans_status() !== FALSE;
    }

    public function mark_transaction_paid($txn_id, $cashier_id)
    {
        $txn_id = (int)$txn_id;
        if ($txn_id <= 0) {
            return array('success' => false, 'error' => 'Invalid transaction.');
        }

        $this->ensure_schema();
        $txn = $this->get_transaction($txn_id);
        if (!$txn) {
            return array('success' => false, 'error' => 'Transaction not found.');
        }

        if (isset($txn->payment_status) && $txn->payment_status === 'Cancelled') {
            return array('success' => false, 'error' => 'Transaction is cancelled.');
        }

        if (isset($txn->payment_status) && $txn->payment_status === 'Paid') {
            return array('success' => true, 'error' => '');
        }

        if (isset($txn->service_type) && $txn->service_type === 'Pharmacy') {
            $pm = isset($txn->payment_method) ? trim((string)$txn->payment_method) : '';
            if (strtoupper($pm) === 'NHIS') {
                return array('success' => false, 'error' => 'NHIS is not allowed for Walk-In Pharmacy.');
            }
        }

        $this->db->trans_start();

        $txn_lock = $this->db->query(
            "SELECT id, service_type, payment_method, payment_status, stock_deducted, notes FROM walkin_transactions WHERE id = ? FOR UPDATE",
            array($txn_id)
        )->row();
        if (!$txn_lock) {
            $this->db->trans_complete();
            return array('success' => false, 'error' => 'Transaction not found.');
        }
        if (isset($txn_lock->payment_status) && $txn_lock->payment_status === 'Cancelled') {
            $this->db->trans_complete();
            return array('success' => false, 'error' => 'Transaction is cancelled.');
        }

        $note = 'Marked Paid by user_id:' . $cashier_id . ' at ' . date('Y-m-d H:i:s');
        $newNotes = $note;
        if (isset($txn_lock->notes) && trim((string)$txn_lock->notes) !== '') {
            $newNotes = trim((string)$txn_lock->notes) . ' | ' . $note;
        }

        $this->db->where('id', $txn_id);
        $this->db->update('walkin_transactions', array(
            'payment_status' => 'Paid',
            'notes' => $newNotes
        ));

        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            return array('success' => false, 'error' => 'Transaction update failed.');
        }

        return array('success' => true, 'error' => '');
    }

    private function reverse_pharmacy_stock_for_transaction($txn_id, $user_id)
    {
        $txn_id = (int)$txn_id;
        if ($txn_id <= 0) return array('success' => false, 'error' => 'Invalid transaction id');

        $this->load->model('app/pharmacy_model');

        $hasBatchTable = $this->table_exists('medication_stock');
        $hasQtyRemaining = $hasBatchTable && $this->db->field_exists('quantity_remaining', 'medication_stock');
        $hasQty = $hasBatchTable && $this->db->field_exists('quantity', 'medication_stock');

        $items = $this->get_transaction_items($txn_id);
        if (empty($items)) {
            return array('success' => true, 'error' => '');
        }

        // Re-add by recorded batch deductions where available
        if ($this->table_exists('walkin_transaction_item_batches') && ($hasQtyRemaining || $hasQty)) {
            foreach ($items as $it) {
                $itemId = isset($it->item_id) ? (int)$it->item_id : 0;
                if ($itemId <= 0) continue;

                $batches = $this->db->get_where('walkin_transaction_item_batches', array('txn_item_id' => $itemId))->result();
                if (!empty($batches)) {
                    foreach ($batches as $b) {
                        $stockId = isset($b->stock_id) ? (int)$b->stock_id : 0;
                        $qty = isset($b->qty) ? (float)$b->qty : 0;
                        if ($stockId <= 0 || $qty <= 0) continue;
                        if ($hasQtyRemaining) {
                            $this->db->set('quantity_remaining', 'quantity_remaining + ' . (float)$qty, false);
                            $this->db->where('stock_id', $stockId);
                            $this->db->update('medication_stock');
                        } elseif ($hasQty) {
                            $this->db->set('quantity', 'quantity + ' . (float)$qty, false);
                            $this->db->where('stock_id', $stockId);
                            $this->db->update('medication_stock');
                        }
                    }

                    // Sync master stock from batches
                    $drugId = isset($it->drug_id) ? (int)$it->drug_id : 0;
                    if ($drugId > 0) {
                        $this->sync_master_stock_from_batches($drugId);

                        // Stock adjustment audit (do NOT call deduct_stock here; it would double-change nStock)
                        $qtyTotal = isset($it->qty) ? (float)$it->qty : 0;
                        if ($qtyTotal > 0 && $this->table_exists('pharmacy_stock_adjustment')) {
                            $after = $this->get_master_stock($drugId);
                            $before = $after - $qtyTotal;
                            $this->db->insert('pharmacy_stock_adjustment', array(
                                'drug_id' => $drugId,
                                'adjustment_type' => 'RESTOCK',
                                'qty_change' => $qtyTotal,
                                'stock_before' => $before,
                                'stock_after' => $after,
                                'reason' => 'Walk-in pharmacy cancellation',
                                'reference_type' => 'WALKIN_CANCEL',
                                'reference_id' => $txn_id,
                                'created_at' => date('Y-m-d H:i:s'),
                                'created_by' => $user_id
                            ));
                        }
                    }
                } else {
                    // No batch trace: restock master only
                    $drugId = isset($it->drug_id) ? (int)$it->drug_id : 0;
                    $qty = isset($it->qty) ? (float)$it->qty : 0;
                    if ($drugId > 0 && $qty > 0) {
                        $this->pharmacy_model->deduct_stock($drugId, -$qty, $user_id, 'WALKIN_CANCEL', $txn_id);
                    }
                }
            }
            return array('success' => true, 'error' => '');
        }

        // No batch table: master restock only
        foreach ($items as $it) {
            $drugId = isset($it->drug_id) ? (int)$it->drug_id : 0;
            $qty = isset($it->qty) ? (float)$it->qty : 0;
            if ($drugId > 0 && $qty > 0) {
                $this->pharmacy_model->deduct_stock($drugId, -$qty, $user_id, 'WALKIN_CANCEL', $txn_id);
            }
        }

        return array('success' => true, 'error' => '');
    }

    /* ═══════════════════════════════════════════════════════
     * DASHBOARD & REPORTING
     * ═══════════════════════════════════════════════════════ */

    private function walkin_reporting_union_sql()
    {
        $parts = array();
        if ($this->table_exists('walkin_transactions')) {
            $parts[] = "
                SELECT
                    wt.id,
                    wt.receipt_number,
                    wt.walkin_client_id,
                    wt.service_type,
                    wt.description,
                    wt.amount,
                    wt.payment_method,
                    wt.payment_status,
                    wt.transaction_date,
                    wt.cashier_id,
                    wt.cashier_name,
                    COALESCE(wc.client_name, 'Walk-in Client') AS client_name,
                    COALESCE(wc.phone, '') AS phone,
                    COALESCE(wc.gender, '') AS gender,
                    NULL AS invoice_no,
                    NULL AS walkin_order_id,
                    'legacy' AS source_type,
                    CONCAT('C:', COALESCE(wt.walkin_client_id, wt.id)) AS client_key
                FROM walkin_transactions wt
                LEFT JOIN walkin_clients wc ON wc.id = wt.walkin_client_id
            ";
        }

        if ($this->table_exists('walkin_orders') && $this->table_exists('walkin_order_items') && $this->table_exists('iop_billing')) {
            $join_billing = "B.billing_subject_type = 'WALKIN_ORDER' AND CONVERT(B.billing_subject_id USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(O.walkin_order_id USING utf8mb4) COLLATE utf8mb4_unicode_ci AND B.InActive = 0";
            $receipt_join = $this->table_exists('iop_receipt') ? "LEFT JOIN (SELECT invoice_no, MAX(receipt_no) AS receipt_no, MAX(payment_type) AS payment_type FROM iop_receipt WHERE InActive = 0 GROUP BY invoice_no) R ON R.invoice_no = B.invoice_no" : "";
            $receipt_no = $this->table_exists('iop_receipt') ? "COALESCE(O.receipt_no, B.receipt_no, R.receipt_no, O.invoice_no, O.walkin_code)" : "COALESCE(O.receipt_no, B.receipt_no, O.invoice_no, O.walkin_code)";
            $payment_method = $this->table_exists('iop_receipt') ? "COALESCE(R.payment_type, 'Cash')" : "'Cash'";
            $parts[] = "
                SELECT
                    O.internal_id AS id,
                    {$receipt_no} AS receipt_number,
                    O.walkin_client_id,
                    CASE WHEN UPPER(TRIM(COALESCE(O.transaction_type, ''))) = 'WALKIN-PHARMACY' THEN 'Pharmacy' ELSE COALESCE(O.transaction_type, 'Walk-In') END AS service_type,
                    COALESCE(IA.description, O.transaction_type, 'Walk-In Service') AS description,
                    O.net_amount AS amount,
                    {$payment_method} AS payment_method,
                    CASE
                        WHEN UPPER(TRIM(COALESCE(O.payment_status, ''))) = 'PAID' OR UPPER(TRIM(COALESCE(B.payment_status, ''))) = 'PAID' THEN 'Paid'
                        WHEN UPPER(TRIM(COALESCE(O.payment_status, ''))) IN ('VOIDED','REFUNDED','CANCELLED') THEN 'Cancelled'
                        ELSE 'Pending'
                    END AS payment_status,
                    O.created_at AS transaction_date,
                    O.created_by AS cashier_id,
                    O.created_by AS cashier_name,
                    COALESCE(O.customer_name, wc.client_name, 'Walk-in Client') AS client_name,
                    COALESCE(O.phone, wc.phone, '') AS phone,
                    COALESCE(O.gender, wc.gender, '') AS gender,
                    COALESCE(O.invoice_no, B.invoice_no) AS invoice_no,
                    O.walkin_order_id,
                    'order' AS source_type,
                    CASE WHEN O.walkin_client_id IS NULL THEN CONCAT('O:', O.walkin_order_id) ELSE CONCAT('C:', O.walkin_client_id) END AS client_key
                FROM walkin_orders O
                LEFT JOIN walkin_clients wc ON wc.id = O.walkin_client_id
                LEFT JOIN iop_billing B ON {$join_billing}
                {$receipt_join}
                LEFT JOIN (
                    SELECT walkin_order_id, GROUP_CONCAT(CONCAT(item_name, ' x', quantity) ORDER BY internal_id SEPARATOR ', ') AS description
                    FROM walkin_order_items
                    WHERE InActive = 0
                    GROUP BY walkin_order_id
                ) IA ON IA.walkin_order_id = O.walkin_order_id
                WHERE O.InActive = 0 AND UPPER(TRIM(COALESCE(O.transaction_type, ''))) = 'WALKIN-PHARMACY'
            ";
        }

        return empty($parts) ? '' : implode(" UNION ALL ", $parts);
    }

    private function walkin_reporting_filter_sql($filters)
    {
        $where = array('1=1');
        if (!empty($filters['date_from'])) {
            $where[] = 'DATE(transaction_date) >= ' . $this->db->escape($filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'DATE(transaction_date) <= ' . $this->db->escape($filters['date_to']);
        }
        if (!empty($filters['service_type'])) {
            $where[] = 'service_type = ' . $this->db->escape($filters['service_type']);
        }
        if (!empty($filters['payment_status'])) {
            $where[] = 'payment_status = ' . $this->db->escape($filters['payment_status']);
        }
        if (!empty($filters['search'])) {
            $s = $this->db->escape('%' . $this->db->escape_like_str($filters['search']) . '%');
            $where[] = "(client_name LIKE {$s} ESCAPE '!' OR receipt_number LIKE {$s} ESCAPE '!' OR description LIKE {$s} ESCAPE '!')";
        }
        return implode(' AND ', $where);
    }

    public function get_today_stats()
    {
        $stats = ['total_clients' => 0, 'total_revenue' => 0.00, 'total_transactions' => 0, 'pending_count' => 0];
        $union = $this->walkin_reporting_union_sql();
        if ($union === '') return $stats;

        $q = $this->db->query(
            "SELECT
                COUNT(DISTINCT client_key) AS total_clients,
                COUNT(*) AS total_transactions,
                COALESCE(SUM(CASE WHEN payment_status='Paid' THEN amount ELSE 0 END), 0) AS total_revenue,
                COALESCE(SUM(CASE WHEN payment_status='Pending' THEN 1 ELSE 0 END), 0) AS pending_count
             FROM ({$union}) W
             WHERE DATE(transaction_date) = CURDATE()"
        );
        if ($q && $q->num_rows() > 0) {
            $row = $q->row();
            $stats['total_clients']      = (int)$row->total_clients;
            $stats['total_transactions'] = (int)$row->total_transactions;
            $stats['total_revenue']      = (float)$row->total_revenue;
            $stats['pending_count']      = (int)$row->pending_count;
        }
        return $stats;
    }

    public function get_today_transactions($limit = 50)
    {
        $union = $this->walkin_reporting_union_sql();
        if ($union === '') return [];
        $limit = max(1, min(200, (int)$limit));
        $q = $this->db->query(
            "SELECT * FROM ({$union}) W
             WHERE DATE(transaction_date) = CURDATE()
             ORDER BY transaction_date DESC
             LIMIT {$limit}"
        );
        return $q ? $q->result() : [];
    }

    public function get_revenue_by_service_today()
    {
        $union = $this->walkin_reporting_union_sql();
        if ($union === '') return [];
        $q = $this->db->query(
            "SELECT service_type,
                    COUNT(*) AS count,
                    COALESCE(SUM(CASE WHEN payment_status='Paid' THEN amount ELSE 0 END), 0) AS revenue
             FROM ({$union}) W
             WHERE DATE(transaction_date) = CURDATE() AND payment_status != 'Cancelled'
             GROUP BY service_type ORDER BY revenue DESC"
        );
        return $q ? $q->result() : [];
    }

    public function get_recent_walkins($limit = 10)
    {
        if (!$this->table_exists('walkin_clients')) return [];
        $union = $this->walkin_reporting_union_sql();
        if ($union === '') {
            $this->db->from('walkin_clients c');
            $this->db->where('DATE(c.created_at)', date('Y-m-d'));
            $this->db->order_by('c.created_at', 'DESC');
            $this->db->limit((int)$limit);
            $q = $this->db->get();
            return $q ? $q->result() : [];
        }
        $limit = max(1, min(100, (int)$limit));
        $q = $this->db->query(
            "SELECT c.*,
                    COALESCE(SUM(CASE WHEN W.id IS NOT NULL THEN 1 ELSE 0 END), 0) AS txn_count,
                    COALESCE(SUM(CASE WHEN W.payment_status='Paid' THEN W.amount ELSE 0 END), 0) AS total_paid
             FROM walkin_clients c
             LEFT JOIN ({$union}) W ON W.walkin_client_id = c.id
             WHERE DATE(c.created_at) = CURDATE()
             GROUP BY c.id
             ORDER BY c.created_at DESC
             LIMIT {$limit}"
        );
        return $q ? $q->result() : [];
    }

    public function get_transactions_paginated($filters = [], $limit = 25, $offset = 0)
    {
        $union = $this->walkin_reporting_union_sql();
        if ($union === '') return ['rows' => [], 'total' => 0];
        $where = $this->walkin_reporting_filter_sql($filters);
        $limit = max(1, min(200, (int)$limit));
        $offset = max(0, (int)$offset);

        // COUNT query — separate, clean query builder state
        $count_q = $this->db->query("SELECT COUNT(*) AS total FROM ({$union}) W WHERE {$where}");
        $total   = ($count_q && $count_q->num_rows() > 0) ? (int)$count_q->row()->total : 0;

        // DATA query — fresh query builder state
        $q = $this->db->query(
            "SELECT * FROM ({$union}) W
             WHERE {$where}
             ORDER BY transaction_date DESC
             LIMIT {$limit} OFFSET {$offset}"
        );

        return ['rows' => $q ? $q->result() : [], 'total' => $total];
    }

    /* ═══════════════════════════════════════════════════════
     * RECEIPT DATA
     * ═══════════════════════════════════════════════════════ */

    public function get_receipt_data($txn_id)
    {
        $txn = $this->get_transaction($txn_id);
        if ($txn && isset($txn->service_type) && $txn->service_type === 'Pharmacy') {
            $txn->items = $this->get_transaction_items((int)$txn_id);
        } elseif ($txn) {
			$txn->service_items = $this->get_transaction_service_items((int)$txn_id);
        }
        return $txn;
    }
}
