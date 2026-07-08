<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Radiology Model
 * Handles radiology tests, orders, and results
 */
class Radiology_model extends CI_Model
{
    public function __construct()
    {
        parent::__construct();
        $this->load->database();
        $this->align_radiology_collation();
    }

    private function align_radiology_collation()
    {
        if ($this->table_exists('radiology_orders')) {
            $col_info = $this->db->query("
                SELECT COLLATION_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'radiology_orders' 
                  AND COLUMN_NAME = 'patient_no'
            ")->row();
            if ($col_info && isset($col_info->COLLATION_NAME) && $col_info->COLLATION_NAME !== 'utf8mb4_unicode_ci') {
                $this->db->query("ALTER TABLE radiology_orders CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        if ($this->table_exists('radiology_test_master')) {
            $col_info = $this->db->query("
                SELECT COLLATION_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'radiology_test_master' 
                  AND COLUMN_NAME = 'test_name'
            ")->row();
            if ($col_info && isset($col_info->COLLATION_NAME) && $col_info->COLLATION_NAME !== 'utf8mb4_unicode_ci') {
                $this->db->query("ALTER TABLE radiology_test_master CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
        if ($this->table_exists('radiology_results')) {
            $col_info = $this->db->query("
                SELECT COLLATION_NAME 
                FROM information_schema.COLUMNS 
                WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'radiology_results' 
                  AND COLUMN_NAME = 'findings'
            ")->row();
            if ($col_info && isset($col_info->COLLATION_NAME) && $col_info->COLLATION_NAME !== 'utf8mb4_unicode_ci') {
                $this->db->query("ALTER TABLE radiology_results CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            }
        }
    }

	public function lock_radiology_request_for_update($order_id)
	{
		$order_id = (int)$order_id;
		if ($order_id <= 0) {
			return false;
		}

		if ($this->table_exists('radiology_orders')) {
			$this->db->query('SELECT id FROM radiology_orders WHERE id = ? LIMIT 1 FOR UPDATE', array($order_id));
		}
		if ($this->table_exists('billing_transactions')) {
			$item_ref = 'radiology_order_id:' . (int)$order_id;
			$this->db->query("SELECT txn_id FROM billing_transactions WHERE InActive = 0 AND department = 'IMAGING' AND item_ref = ? LIMIT 1 FOR UPDATE", array($item_ref));
		}
		return true;
	}
    
    private function table_exists($table)
    {
        return $this->db->table_exists($table);
    }
    
    private function column_exists($table, $column)
    {
        if (!$this->table_exists($table)) return false;
        $fields = $this->db->list_fields($table);
        return in_array($column, $fields);
    }
    
    /**
     * Ensure radiology schema exists
     */
    public function ensure_radiology_schema()
    {
        // Create radiology_test_master
        if (!$this->table_exists('radiology_test_master')) {
            $this->db->query("CREATE TABLE `radiology_test_master` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `test_name` VARCHAR(255) NOT NULL,
                `test_code` VARCHAR(50),
                `nhis_code` VARCHAR(50),
                `price` DECIMAL(18,2) DEFAULT 0,
                `nhis_price` DECIMAL(18,2) DEFAULT 0,
                `department` VARCHAR(100) DEFAULT 'Radiology',
                `category` VARCHAR(100),
                `is_nhis_covered` TINYINT(1) DEFAULT 0,
                `status` ENUM('active','inactive') DEFAULT 'active',
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0,
                KEY `idx_nhis_code` (`nhis_code`),
                KEY `idx_status` (`status`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
            
            $this->seed_radiology_tests();
        }
        
        // Create radiology_orders
        if (!$this->table_exists('radiology_orders')) {
            $this->db->query("CREATE TABLE `radiology_orders` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_no` VARCHAR(50) NOT NULL,
                `iop_id` VARCHAR(25),
                `patient_no` VARCHAR(25),
                `test_id` INT NOT NULL,
                `priority` ENUM('normal','urgent','stat') DEFAULT 'normal',
                `clinical_notes` TEXT,
                `status` ENUM('pending','in_progress','completed','cancelled') DEFAULT 'pending',
                `ordered_by` INT,
                `ordered_at` DATETIME,
                `completed_at` DATETIME,
                `billed` TINYINT(1) DEFAULT 0,
                `invoice_no` VARCHAR(50),
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0,
                KEY `idx_iop` (`iop_id`),
                KEY `idx_patient` (`patient_no`),
                KEY `idx_status` (`status`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
        }
        
        // Create radiology_results
        if (!$this->table_exists('radiology_results')) {
            $this->db->query("CREATE TABLE `radiology_results` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `order_id` INT NOT NULL,
                `findings` TEXT,
                `impression` TEXT,
                `recommendations` TEXT,
                `images` TEXT,
                `performed_by` INT,
                `verified_by` INT,
                `performed_at` DATETIME,
                `verified_at` DATETIME,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `InActive` INT(1) DEFAULT 0,
                KEY `idx_order` (`order_id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4");
        }
        
        return true;
    }
    
    /**
     * Seed radiology tests
     */
    private function seed_radiology_tests()
    {
        $tests = [
            ['X-Ray Chest PA', 'XRAY-001', 'RAD001', 50.00, 35.00, 'Radiology', 'X-Ray', 1],
            ['X-Ray Abdomen', 'XRAY-002', 'RAD002', 60.00, 40.00, 'Radiology', 'X-Ray', 1],
            ['X-Ray Spine', 'XRAY-003', 'RAD003', 70.00, 50.00, 'Radiology', 'X-Ray', 1],
            ['X-Ray Pelvis', 'XRAY-004', 'RAD004', 60.00, 40.00, 'Radiology', 'X-Ray', 1],
            ['X-Ray Skull', 'XRAY-005', 'RAD005', 55.00, 38.00, 'Radiology', 'X-Ray', 1],
            ['Ultrasound Abdomen', 'USS-001', 'RAD010', 120.00, 80.00, 'Radiology', 'Ultrasound', 1],
            ['Ultrasound Pelvis', 'USS-002', 'RAD011', 100.00, 70.00, 'Radiology', 'Ultrasound', 1],
            ['Ultrasound Obstetric', 'USS-003', 'RAD012', 150.00, 100.00, 'Radiology', 'Ultrasound', 1],
            ['Ultrasound Thyroid', 'USS-004', 'RAD013', 100.00, 70.00, 'Radiology', 'Ultrasound', 0],
            ['CT Scan Head', 'CT-001', 'RAD020', 500.00, 350.00, 'Radiology', 'CT Scan', 1],
            ['CT Scan Chest', 'CT-002', 'RAD021', 550.00, 380.00, 'Radiology', 'CT Scan', 1],
            ['CT Scan Abdomen', 'CT-003', 'RAD022', 600.00, 400.00, 'Radiology', 'CT Scan', 1],
            ['MRI Brain', 'MRI-001', 'RAD030', 1200.00, 800.00, 'Radiology', 'MRI', 0],
            ['MRI Spine', 'MRI-002', 'RAD031', 1300.00, 900.00, 'Radiology', 'MRI', 0],
            ['ECG', 'ECG-001', 'RAD040', 30.00, 20.00, 'Cardiology', 'Cardiac', 1],
            ['Echocardiogram', 'ECHO-001', 'RAD041', 200.00, 150.00, 'Cardiology', 'Cardiac', 1],
            ['Mammography', 'MAM-001', 'RAD050', 180.00, 120.00, 'Radiology', 'Mammography', 1]
        ];
        
        foreach ($tests as $t) {
            $this->db->insert('radiology_test_master', [
                'test_name' => $t[0],
                'test_code' => $t[1],
                'nhis_code' => $t[2],
                'price' => $t[3],
                'nhis_price' => $t[4],
                'department' => $t[5],
                'category' => $t[6],
                'is_nhis_covered' => $t[7]
            ]);
        }
    }
    
    /**
     * Get all tests
     */
    public function get_all_tests()
    {
        $this->db->where('InActive', 0);
        $this->db->order_by('test_name', 'ASC');
        return $this->db->get('radiology_test_master')->result();
    }
    
    /**
     * Get active tests
     */
    public function get_active_tests()
    {
        $this->db->where(['InActive' => 0, 'status' => 'active']);
        $this->db->order_by('test_name', 'ASC');
        return $this->db->get('radiology_test_master')->result();
    }
    
    /**
     * Get single test
     */
    public function get_test($id)
    {
        return $this->db->get_where('radiology_test_master', ['id' => $id])->row();
    }
    
    /**
     * Search tests
     */
    public function search_tests($term)
    {
        $this->db->like('test_name', $term);
        $this->db->or_like('test_code', $term);
        $this->db->or_like('nhis_code', $term);
        $this->db->where('InActive', 0);
        $this->db->limit(20);
        return $this->db->get('radiology_test_master')->result();
    }
    
    /**
     * Add new test
     */
    public function add_test($data)
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['InActive'] = 0;
        return $this->db->insert('radiology_test_master', $data);
    }
    
    /**
     * Update test
     */
    public function update_test($id, $data)
    {
        $this->db->where('id', $id);
        return $this->db->update('radiology_test_master', $data);
    }
    
    /**
     * Delete test (soft delete)
     */
    public function delete_test($id)
    {
        $this->db->where('id', $id);
        return $this->db->update('radiology_test_master', ['InActive' => 1]);
    }
    
    /**
     * Generate order number
     */
    private function generate_order_no()
    {
        $prefix = 'RAD' . date('Ymd');
        $this->db->select('MAX(id) as max_id');
        $result = $this->db->get('radiology_orders')->row();
        $next = ($result && $result->max_id) ? $result->max_id + 1 : 1;
        return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
    }
    
    /**
     * Create radiology order
     */
    public function create_order($data)
    {
        $this->ensure_radiology_schema();
        
        $data['order_no'] = $this->generate_order_no();
        $data['ordered_at'] = date('Y-m-d H:i:s');
        $data['status'] = 'pending';
        $data['InActive'] = 0;
        
        $ok = $this->db->insert('radiology_orders', $data);
        if ($ok) {
            $order_id = (int)$this->db->insert_id();
            // Best-effort: create SSOT transaction so payment status / gating is deterministic
            $this->load->model('app/billing_transaction_model');
            if (isset($this->billing_transaction_model) && method_exists($this->billing_transaction_model, 'sync_radiology_order')) {
                $created_by = isset($data['ordered_by']) ? $data['ordered_by'] : null;
                $this->billing_transaction_model->sync_radiology_order($order_id, $created_by);
            }
            return $order_id;
        }
        return false;
    }
    
    /**
     * Get order
     */
    public function get_order($id)
    {
        $this->db->select('o.*, t.test_name, t.test_code, t.nhis_code, t.price, t.nhis_price, t.is_nhis_covered,
            p.firstname, p.lastname, p.patient_no as pat_no');
        $this->db->from('radiology_orders o');
        $this->db->join('radiology_test_master t', 't.id = o.test_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = o.patient_no', 'left');
        $this->db->where('o.id', $id);
        return $this->db->get()->row();
    }
    
    /**
     * Get order with result
     */
    public function get_order_with_result($order_id)
    {
        $order = $this->get_order($order_id);
        if ($order) {
            $result = $this->db->get_where('radiology_results', ['order_id' => $order_id, 'InActive' => 0])->row();
            $order->result = $result;
        }
        return $order;
    }
    
    /**
     * Get pending orders
     */
    public function get_pending_orders()
    {
        if (!$this->table_exists('radiology_orders')) {
            return [];
        }

        $ssotCols = '';
        if ($this->table_exists('billing_transactions') && $this->column_exists('billing_transactions', 'item_ref') && $this->column_exists('billing_transactions', 'department')) {
            $hasPay = $this->column_exists('billing_transactions', 'payment_status');
            $hasPayer = $this->column_exists('billing_transactions', 'payer_type');
            $hasNet = $this->column_exists('billing_transactions', 'net_amount');
            $hasPaid = $this->column_exists('billing_transactions', 'paid_amount');
            $hasBal = $this->column_exists('billing_transactions', 'balance_amount');
            $ssotCols = ',
                (SELECT ' . ($hasPay ? 'bt.payment_status' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_payment_status,
                (SELECT ' . ($hasPayer ? 'bt.payer_type' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_payer_type,
                (SELECT ' . ($hasNet ? 'bt.net_amount' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_net_amount,
                (SELECT ' . ($hasPaid ? 'bt.paid_amount' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_paid_amount,
                (SELECT ' . ($hasBal ? 'bt.balance_amount' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_balance_amount';
        }
        
        $this->db->select('o.*, t.test_name, t.test_code, t.category,
            p.firstname, p.lastname, p.patient_no as pat_no' . $ssotCols);
        $this->db->from('radiology_orders o');
        $this->db->join('radiology_test_master t', 't.id = o.test_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = o.patient_no', 'left');
        $this->db->where('o.status', 'pending');
        $this->db->where('o.InActive', 0);
        $this->db->order_by('o.priority', 'DESC');
        $this->db->order_by('o.ordered_at', 'ASC');
        return $this->db->get()->result();
    }

    public function get_pending_orders_for_queue($queue)
    {
        $queue = strtolower(trim((string)$queue));
        if ($queue !== 'xray' && $queue !== 'ecg' && $queue !== 'ct') {
            return $this->get_pending_orders();
        }

        if (!$this->table_exists('radiology_orders')) {
            return [];
        }

        $ssotCols = '';
        if ($this->table_exists('billing_transactions') && $this->column_exists('billing_transactions', 'item_ref') && $this->column_exists('billing_transactions', 'department')) {
            $hasPay = $this->column_exists('billing_transactions', 'payment_status');
            $hasPayer = $this->column_exists('billing_transactions', 'payer_type');
            $hasNet = $this->column_exists('billing_transactions', 'net_amount');
            $hasPaid = $this->column_exists('billing_transactions', 'paid_amount');
            $hasBal = $this->column_exists('billing_transactions', 'balance_amount');
            $ssotCols = ',
                (SELECT ' . ($hasPay ? 'bt.payment_status' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_payment_status,
                (SELECT ' . ($hasPayer ? 'bt.payer_type' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_payer_type,
                (SELECT ' . ($hasNet ? 'bt.net_amount' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_net_amount,
                (SELECT ' . ($hasPaid ? 'bt.paid_amount' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_paid_amount,
                (SELECT ' . ($hasBal ? 'bt.balance_amount' : 'NULL') . ' FROM billing_transactions bt WHERE bt.InActive = 0 AND bt.department = \'IMAGING\' AND bt.item_ref = CONCAT(\'radiology_order_id:\', o.id) LIMIT 1) AS ssot_balance_amount';
        }

        $this->db->select('o.*, t.test_name, t.test_code, t.category,
            p.firstname, p.lastname, p.patient_no as pat_no' . $ssotCols);
        $this->db->from('radiology_orders o');
        $this->db->join('radiology_test_master t', 't.id = o.test_id', 'left');
        $this->db->join('patient_personal_info p', 'p.patient_no = o.patient_no', 'left');
        $this->db->where('o.status', 'pending');
        $this->db->where('o.InActive', 0);

        $this->db->group_start();
        if ($queue === 'xray') {
            $this->db->like('t.test_code', 'XRAY-', 'after');
            $this->db->or_like('t.category', 'X-Ray');
        } elseif ($queue === 'ct') {
            $this->db->like('t.test_code', 'CT-', 'after');
            $this->db->or_like('t.category', 'CT');
        } elseif ($queue === 'ecg') {
            $this->db->like('t.test_code', 'ECG-', 'after');
            $this->db->or_where('t.test_name', 'ECG');
            $this->db->or_where('t.category', 'Cardiac');
            $this->db->or_where('t.department', 'Cardiology');
        }
        $this->db->group_end();

        $this->db->order_by('o.priority', 'DESC');
        $this->db->order_by('o.ordered_at', 'ASC');
        return $this->db->get()->result();
    }
    
    /**
     * Count pending orders
     */
    public function count_pending_orders()
    {
        if (!$this->table_exists('radiology_orders')) return 0;
        return $this->db->where(['status' => 'pending', 'InActive' => 0])->count_all_results('radiology_orders');
    }
    
    /**
     * Count completed today
     */
    public function count_completed_today()
    {
        if (!$this->table_exists('radiology_orders')) return 0;
        $this->db->where('status', 'completed');
        $this->db->where('DATE(completed_at)', date('Y-m-d'));
        $this->db->where('InActive', 0);
        return $this->db->count_all_results('radiology_orders');
    }
    
    /**
     * Save result
     */
    public function save_result($data)
    {
        $order_id = $data['order_id'];
        unset($data['order_id']);

		// Payment gate (defense-in-depth): do not allow completing an order
		// when payment policy blocks the service (admins bypass).
		if (!(function_exists('is_admin_role') && is_admin_role())) {
			$order = $this->db->get_where('radiology_orders', ['id' => $order_id, 'InActive' => 0])->row();
			if ($order) {
				$iop_id = isset($order->iop_id) ? $order->iop_id : null;
				$this->load->model('app/service_gate_model', 'service_gate_model');
				$gate = $this->service_gate_model->check_radiology_gate((int)$order_id, $iop_id);
				if (is_array($gate) && isset($gate['allowed']) && !$gate['allowed']) {
					return false;
				}
			}
		}
        
        $data['performed_at'] = date('Y-m-d H:i:s');
        $data['InActive'] = 0;
        
        // Check if result exists
        $existing = $this->db->get_where('radiology_results', ['order_id' => $order_id, 'InActive' => 0])->row();
        
        if ($existing) {
            $this->db->where('id', $existing->id);
            $this->db->update('radiology_results', $data);
        } else {
            $data['order_id'] = $order_id;
            $this->db->insert('radiology_results', $data);
        }
        
        // Update order status
        $this->db->where('id', $order_id);
        $this->db->update('radiology_orders', [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s')
        ]);
        
        return true;
    }
    
    /**
     * Get NHIS coverage for test
     */
    public function get_nhis_coverage($test_id, $patient_no = null)
    {
        $test = $this->get_test($test_id);
        if (!$test) return null;
        
        $is_nhis_patient = false;
        if ($patient_no) {
            $this->load->model('app/billing_model');
            $payer = $this->billing_model->determine_payer_type($patient_no);
            $is_nhis_patient = ($payer === 'NHIS');
        }

        $price = 0.0;
        $this->load->model('app/Price_engine_model', 'price_engine_model');
        $resolved = $this->price_engine_model->resolve(array(
            'item_type'  => $this->price_engine_model::ITEM_IMAGING,
            'item_id'    => (int)$test_id,
            'patient_no' => (string)$patient_no,
            'payer_type' => $is_nhis_patient ? 'NHIS' : 'CASH',
            'quantity'   => 1,
        ));
        if (!empty($resolved) && !empty($resolved['ok'])) {
            $price = isset($resolved['unit_price']) ? (float)$resolved['unit_price'] : 0.0;
        } else {
            $err = isset($resolved['error']) ? (string)$resolved['error'] : 'Unknown price engine error';
            log_message('error', 'Radiology_model: Price engine resolve failed for test_id=' . (int)$test_id . ' patient_no=' . (string)$patient_no . ' error=' . $err);
        }
        
        return [
            'test_name' => $test->test_name,
            'is_nhis_covered' => $test->is_nhis_covered,
            'price' => $price,
            'nhis_code' => $test->nhis_code,
            'patient_is_nhis' => $is_nhis_patient
        ];
    }

    /**
     * Convert radiology tables from MyISAM to InnoDB for better integrity
     */
    public function convert_to_innodb()
    {
        $tables = ['radiology_test_master', 'radiology_orders', 'radiology_results'];
        $converted = [];
        
        foreach ($tables as $table) {
            if (!$this->table_exists($table)) continue;
            
            $q = $this->db->query("SHOW TABLE STATUS WHERE Name = ?", [$table]);
            $row = $q ? $q->row() : null;
            if ($row && strtolower($row->Engine) !== 'innodb') {
                $this->db->query("ALTER TABLE `{$table}` ENGINE=InnoDB");
                $converted[] = $table;
            }
        }
        
        return $converted;
    }

    /**
     * Add foreign key constraints for data integrity
     */
    public function add_foreign_keys()
    {
        // Add diagnostic_type column for unified workflow
        if ($this->table_exists('radiology_orders') && !$this->column_exists('radiology_orders', 'diagnostic_type')) {
            $this->db->query("ALTER TABLE `radiology_orders` ADD COLUMN `diagnostic_type` VARCHAR(20) DEFAULT 'RADIOLOGY'");
        }
        
        // Add priority column if missing
        if ($this->table_exists('radiology_orders') && !$this->column_exists('radiology_orders', 'urgency')) {
            $this->db->query("ALTER TABLE `radiology_orders` ADD COLUMN `urgency` ENUM('ROUTINE','URGENT','STAT') DEFAULT 'ROUTINE'");
        }
        
        // Add sample tracking support
        if ($this->table_exists('radiology_orders') && !$this->column_exists('radiology_orders', 'sample_id')) {
            $this->db->query("ALTER TABLE `radiology_orders` ADD COLUMN `sample_id` BIGINT(11) DEFAULT NULL");
        }
        
        // Add verification columns
        if ($this->table_exists('radiology_results')) {
            if (!$this->column_exists('radiology_results', 'verified_level_1_by')) {
                $this->db->query("ALTER TABLE `radiology_results` ADD COLUMN `verified_level_1_by` VARCHAR(25) DEFAULT NULL");
            }
            if (!$this->column_exists('radiology_results', 'verified_level_1_at')) {
                $this->db->query("ALTER TABLE `radiology_results` ADD COLUMN `verified_level_1_at` DATETIME DEFAULT NULL");
            }
            if (!$this->column_exists('radiology_results', 'verified_level_2_by')) {
                $this->db->query("ALTER TABLE `radiology_results` ADD COLUMN `verified_level_2_by` VARCHAR(25) DEFAULT NULL");
            }
            if (!$this->column_exists('radiology_results', 'verified_level_2_at')) {
                $this->db->query("ALTER TABLE `radiology_results` ADD COLUMN `verified_level_2_at` DATETIME DEFAULT NULL");
            }
        }
        
        return true;
    }

    /**
     * Full radiology hardening - convert to InnoDB and add integrity features
     */
    public function harden_radiology_schema()
    {
        $this->ensure_radiology_schema();
        $this->convert_to_innodb();
        $this->add_foreign_keys();
        return true;
    }
}
