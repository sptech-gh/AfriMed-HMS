<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

/**
 * Demo Database Cleanup Controller
 * 
 * SAFE RESET for demo environment preparation
 * Clears all patient/transaction data while preserving:
 * - Users & Permissions
 * - System Configuration
 * - Master Data (drugs, tests, services, departments)
 * - Pricing & Inventory Setup
 * 
 * @author HMS System
 * @version 1.0
 */
class Demo_cleanup extends General
{
    private $tables_to_truncate = [];
    private $tables_to_keep = [];
    private $cleanup_log = [];

    public function __construct()
    {
        parent::__construct();
		if ((defined('ENVIRONMENT') && ENVIRONMENT === 'production') || strtolower((string)getenv('APP_ENV')) === 'production') {
			show_error('Demo cleanup is disabled in production.', 403);
		}
        
        if (General::is_logged_in() == FALSE) {
            redirect(base_url() . 'login');
        }
        
        // Only admin can access this
        if (!has_role('admin')) {
            redirect(base_url() . 'access_denied');
        }
        
        $this->_define_tables();
    }

	private function _get_scope_summary(array $tables)
	{
		$patients = false;
		$clinical = false;
		$billing = false;
		foreach ($tables as $t) {
			$t = (string)$t;
			if (strpos($t, 'patient_') === 0) {
				$patients = true;
			}
			if (strpos($t, 'iop_') === 0 || strpos($t, 'lab_') === 0 || strpos($t, 'pharmacy_') === 0 || strpos($t, 'radiology_') === 0) {
				$clinical = true;
			}
			if (strpos($t, 'billing_') === 0 || strpos($t, 'invoice') === 0 || strpos($t, 'payment_') === 0 || strpos($t, 'eb_') === 0 || $t === 'iop_billing' || $t === 'iop_billing_t' || $t === 'iop_receipt') {
				$billing = true;
			}
		}
		return array(
			'Patients' => $patients ? 'YES' : 'NO',
			'Clinical' => $clinical ? 'YES' : 'NO',
			'Billing' => $billing ? 'YES' : 'NO',
		);
	}

	private function _get_fk_ordered_tables(array $tables)
	{
		$tables = array_values(array_unique(array_filter($tables)));
		if (count($tables) <= 1) return $tables;
		if (!isset($this->db) || !isset($this->db->database)) return $tables;
		$db = (string)$this->db->database;
		if ($db === '') return $tables;

		$indeg = array();
		$graph = array();
		$present = array();
		foreach ($tables as $t) {
			$present[$t] = true;
			$indeg[$t] = 0;
			$graph[$t] = array();
		}

		try {
			$ph = implode(',', array_fill(0, count($tables), '?'));
			$sql = "SELECT TABLE_NAME, REFERENCED_TABLE_NAME\n				FROM information_schema.KEY_COLUMN_USAGE\n				WHERE TABLE_SCHEMA = ?\n				  AND REFERENCED_TABLE_NAME IS NOT NULL\n				  AND TABLE_NAME IN ($ph)\n				  AND REFERENCED_TABLE_NAME IN ($ph)";
			$params = array_merge(array($db), $tables, $tables);
			$q = $this->db->query($sql, $params);
			if ($q && $q->num_rows() > 0) {
				foreach ($q->result_array() as $row) {
					$child = isset($row['TABLE_NAME']) ? (string)$row['TABLE_NAME'] : '';
					$parent = isset($row['REFERENCED_TABLE_NAME']) ? (string)$row['REFERENCED_TABLE_NAME'] : '';
					if ($child === '' || $parent === '') continue;
					if (!isset($present[$child]) || !isset($present[$parent])) continue;
					if (!in_array($parent, $graph[$child], true)) {
						$graph[$child][] = $parent;
						$indeg[$parent] = isset($indeg[$parent]) ? ($indeg[$parent] + 1) : 1;
					}
				}
			}
		} catch (Throwable $e) {
			return $tables;
		}

		$queue = array();
		foreach ($tables as $t) {
			if ((int)$indeg[$t] === 0) {
				$queue[] = $t;
			}
		}

		$ordered = array();
		$seen = array();
		$qi = 0;
		while ($qi < count($queue)) {
			$n = $queue[$qi];
			$qi++;
			if (isset($seen[$n])) continue;
			$seen[$n] = true;
			$ordered[] = $n;
			if (isset($graph[$n])) {
				foreach ($graph[$n] as $m) {
					$indeg[$m] = isset($indeg[$m]) ? ((int)$indeg[$m] - 1) : 0;
					if ((int)$indeg[$m] === 0) {
						$queue[] = $m;
					}
				}
			}
		}

		if (count($ordered) < count($tables)) {
			foreach ($tables as $t) {
				if (!isset($seen[$t])) {
					$ordered[] = $t;
				}
			}
		}

		return $ordered;
	}

	private function _ensure_demo_cleanup_audit_schema()
	{
		$prev = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev !== null) { $this->db->db_debug = false; }
		try {
			$this->db->query("CREATE TABLE IF NOT EXISTS `demo_cleanup_audit` (
				`audit_id` bigint(20) NOT NULL AUTO_INCREMENT,
				`user_id` varchar(25) DEFAULT NULL,
				`username` varchar(100) DEFAULT NULL,
				`ip_address` varchar(64) DEFAULT NULL,
				`database_name` varchar(128) DEFAULT NULL,
				`status` varchar(40) NOT NULL,
				`started_at` datetime NOT NULL,
				`completed_at` datetime DEFAULT NULL,
				`cleaned_tables` int(11) DEFAULT 0,
				`skipped_tables` int(11) DEFAULT 0,
				`total_rows_deleted` bigint(20) DEFAULT 0,
				`tables_json` longtext DEFAULT NULL,
				`errors_json` longtext DEFAULT NULL,
				PRIMARY KEY (`audit_id`),
				KEY `idx_started_at` (`started_at`),
				KEY `idx_status` (`status`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		} catch (Throwable $e) {
		}
		if ($prev !== null) { $this->db->db_debug = $prev; }
	}

    /**
     * Define which tables to clean and which to keep
     */
    private function _define_tables()
    {
        // ============================================
        // TABLES TO TRUNCATE (Patient/Transaction Data)
        // ============================================
        $this->tables_to_truncate = [
            // Patient Core Data
            'patient_personal_info',
            'patient_details_iop',
            'patient_admissions',
            'patient_appointment',
            'patient_attachment',
            'patient_consent',
            'patient_encounters',
            'patient_allergies',
            'patient_risk_flags',
            'patient_financial_ledger',
            'patient_history_access_log',
            
            // OPD/IPD Clinical Data
            'iop_billing',
            'iop_billing_t',
            'iop_billing_line_meta',
            'iop_billable_item_lock',
            'iop_clearance_workflow',
            'iop_complaints',
            'iop_diagnosis',
            'iop_discharge_audit',
            'iop_discharge_summary',
            'iop_encounter_owner',
            'iop_encounter_owner_audit',
            'iop_intake_record',
            'iop_nurse_notes',
            'iop_opd_rr_state',
            'iop_opd_workflow',
            'iop_operation_theater',
            'iop_output_record',
            'iop_progress_note',
            'iop_receipt',
            'iop_room_charge',
            'iop_room_transfer',
            'iop_vital_parameters',
            'iop_vital_parameters_extra',
            'iop_bed_side_procedure',
            
            // Laboratory Data
            'iop_laboratory',
            'iop_laboratory_workflow',
            'iop_laboratory_attachment_meta',
            'iop_lab_billing',
            'lab_result_entries',
			'iop_laboratory_result_draft',
			'iop_laboratory_release_batch',
			'iop_laboratory_release_item',
			'iop_laboratory_release_snapshot',
			'iop_laboratory_release_event',
			'iop_laboratory_result_acknowledgement',
            
            // Medication/Pharmacy Transaction Data
            'iop_medication',
            'iop_medication_administration',
            'iop_medication_diagnosis',
            'iop_pharmacy_dispense_audit',
            'pharmacy_billing_queue',
            'pharmacy_returns',
            'pharmacy_return_audit',
            'pharmacy_audit_log',
            'pharmacy_reconciliations',
            'pharmacy_reconciliation_items',
            'pharmacy_reconciliation_discrepancies',
            'controlled_drug_dispensing',
            'controlled_drug_register',
            'prescription_workflow',
            'prescription_status_audit',
            'prescription_locks',
            'prescription_safety_alerts',
            
            // Sonography Data
            'iop_sonography_charge',
            'iop_sonography_report_draft',
            'iop_sonography_request_meta',
            
            // Radiology Data
            'radiology_orders',
            'radiology_results',
            
            // Billing/Financial Transaction Data
            'invoice',
            'invoice_item',
			'billing_queue',
			'billing_dispositions',
			'billing_transactions',
			'billing_reconciliation_log',
			'billing_price_override_log',
            'eb_invoices',
            'eb_payments',
            'eb_refunds',
            'eb_transactions',
            'eb_ledger',
            'eb_adjustments',
            'eb_nhis_claims',
            'eb_events_log',
            'payment_breakdown',
            'payment_transactions',
            'outstanding_balances',
            'financial_ledger',
            'financial_audit_log',
            'smart_billing_audit',
            'smart_billing_ledger',
            'waiver_requests',
            'cashier_payment_log',
            
            // NHIS Claims & Transaction Data
            'nhis_claims',
            'nhis_claim_items',
            'nhis_claim_lines',
            'nhis_claim_validation_log',
            'nhis_visits',
            'nhis_eligibility_cache',
            'nhis_reconciliation',
            'nhis_patient_audit',
            'nhis_memberships',
            'claimit_logs',
            
            // Clinical Decision Support Logs
            'clinical_decision_log',
            'clinical_decision_cache',
            'clinical_notes_audit',
            'clinical_override_audit',
            'consultation_locks',
            
            // Admission Queue
            'opd_admission_queue',
            'opd_registration_override_log',
            'opd_status_audit',
            
            // Nurse/Doctor Messages
            'nurse_doctor_message',
            'doctor_notifications',
            
            // Result Edit/Approval Data
            'result_edit_audit',
            'result_edit_requests',
            'result_locks',
            'supervisor_approval_audit',
            'supervisor_approval_queue',
            'verification_attempt_log',
            
            // Stock Adjustments (transaction data, not master)
            'pharmacy_stock_adjustment',
            'pharmacy_stock_requests',
            'pharmacy_stock_transfer',
            'stock_audit_log',
            
            // Emergency Overrides
            'emergency_overrides',
            
            // Recall Data
            'recall_affected_patients',
            'reconciliation_issues',
            
            // Session Data
            'ci_sessions',
        ];

        // ============================================
        // TABLES TO KEEP (Master/Config Data)
        // ============================================
        $this->tables_to_keep = [
            // Users & Access Control
            'users',
            'user_roles',
            'user_roles_pages',
            'user_privileges',
            'user_billing_roles',
            'user_verification_credentials',
            'privilege_audit_log',
            'privilege_refresh_tracker',
            'pages',
            'login_attempts',
            
            // System Configuration
            'company_info',
            'system_option',
            'system_parameters',
            'data_retention_policy',
            'smart_billing_config',
            'nhis_config',
            'nhis_api_config',
            'nhis_billing_config',
            'eb_service_gates',
            'eb_chart_of_accounts',
            'eb_price_versions',
            'chart_of_accounts',
            
            // Department/Location Masters
            'department',
            'designation',
            'floor',
            'room_master',
            'room_category',
            'room_beds',
            'insurance_comp',
            
            // Staff Masters
            'doctor',
            'doctors_fee',
            'nurse_shift',
            'nurse_shift_task',
            
            // Drug/Pharmacy Masters
            'medicine_drug_name',
            'medicine_category',
            'medicine_master',
            'medication_stock',
            'pharmacy_stores',
            'pharmacy_store_stock',
            'drug_classes',
            'drug_class_mapping',
            'drug_brand_mapping',
            'drug_generic_master',
            'drug_interactions',
            'drug_contraindications',
            'drug_dose_limits',
            'drug_duration_limits',
            'drug_pregnancy_category',
            'drug_renal_adjustments',
            'drug_tariff_mapping',
            'high_risk_drugs',
            'controlled_drug_schedules',
            
            // Lab/Test Masters
            'lab_test_master',
            'lab_result_templates',
            'radiology_test_master',
            'scan_master',
            'sonography_items',
            
            // Billing/Service Masters
            'bill_category',
            'bill_particular',
            'surgical_package',
            'surgical_package_t',
            'payment_methods',
            'price_history',
            
            // Diagnosis Masters
            'diagnosis',
            'icd10_codes',
            'nhis_diagnosis',
            'nhis_drug_diagnosis_rules',
            'nhis_coverage',
            'nhis_tariffs',
            'nhis_drug_tariffs',
            'nhis_service_mapping',
            'nhis_audit_log',
            
            // Prescription Templates
            'prescription_templates',
            'prescription_template_items',
            
            // Result Edit Permissions (config, not data)
            'result_edit_permissions',
            'verification_role_config',
            
            // System Logs (keep for audit)
            'logfile',
            'system_audit_log',
            'security_audit_log',
            
            // Complaints Master
            'complain',
            
            // Declaration
            'declaredor',
        ];
    }

    /**
     * Main index - show cleanup dashboard
     */
    public function index()
    {
        $this->session->set_userdata([
            'tab' => 'admin',
            'module' => 'demo_cleanup'
        ]);

        $data['title'] = 'Demo Database Cleanup';
        $data['tables_to_clean'] = $this->tables_to_truncate;
        $data['tables_to_keep'] = $this->tables_to_keep;
        $data['record_counts'] = $this->_get_record_counts();
        
        $this->load->view('app/admin/demo_cleanup', $data);
    }

	public function dry_run()
	{
		header('Content-Type: application/json');
		$ordered = $this->_get_fk_ordered_tables($this->tables_to_truncate);
		$counts = $this->_get_record_counts();
		$exists = array();
		$total = 0;
		foreach ($ordered as $t) {
			$exists[$t] = $this->db->table_exists($t) ? 1 : 0;
			if (isset($counts[$t]) && is_numeric($counts[$t])) {
				$total += (int)$counts[$t];
			}
		}
		$scope = $this->_get_scope_summary($ordered);
		echo json_encode(array(
			'status' => 'success',
			'database' => isset($this->db->database) ? (string)$this->db->database : '',
			'tables_to_clean' => $ordered,
			'tables_to_keep' => $this->tables_to_keep,
			'exists' => $exists,
			'record_counts' => $counts,
			'total_records_to_delete' => $total,
			'scope_summary' => $scope,
			'generated_at' => date('Y-m-d H:i:s'),
		));
	}

    /**
     * Get record counts for tables to be cleaned
     */
    private function _get_record_counts()
    {
        $counts = [];
        foreach ($this->tables_to_truncate as $table) {
            if ($this->db->table_exists($table)) {
                $counts[$table] = $this->db->count_all($table);
            } else {
                $counts[$table] = 'N/A';
            }
        }
        return $counts;
    }

    /**
     * Execute the cleanup (AJAX endpoint)
     */
    public function execute()
    {
        // Increase execution time for large databases
        set_time_limit(300);
        ini_set('memory_limit', '512M');
        
        header('Content-Type: application/json');

		// Prevent CodeIgniter from rendering HTML DB error pages for AJAX.
		$prev_db_debug = isset($this->db->db_debug) ? $this->db->db_debug : null;
		if ($prev_db_debug !== null) {
			$this->db->db_debug = false;
		}
        
        // Verify this is a POST request with confirmation
        if ($this->input->post('confirm') !== 'CLEAN_DEMO_DATABASE') {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid confirmation code. Type CLEAN_DEMO_DATABASE to confirm.'
            ]);
            return;
        }

        $this->cleanup_log = [];
        $errors = [];
        $cleaned_tables = 0;
        $skipped_tables = 0;
		$total_rows_deleted = 0;
		$this->_ensure_demo_cleanup_audit_schema();
		$ordered_tables = $this->_get_fk_ordered_tables($this->tables_to_truncate);
		$audit_id = null;
		try {
			$this->db->insert('demo_cleanup_audit', array(
				'user_id' => $this->session->userdata('user_id'),
				'username' => $this->session->userdata('username'),
				'ip_address' => $this->input->ip_address(),
				'database_name' => isset($this->db->database) ? (string)$this->db->database : '',
				'status' => 'RUNNING',
				'started_at' => date('Y-m-d H:i:s'),
				'tables_json' => json_encode($ordered_tables),
			));
			$audit_id = (int)$this->db->insert_id();
		} catch (Throwable $e) {
		}

        try {
            // Step 1: Disable foreign key checks
            $ok = $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
			if ($ok === false) {
				$err = $this->db->error();
				throw new Exception('Failed to disable foreign key checks: ' . (isset($err['message']) ? $err['message'] : 'unknown_db_error'));
			}
            $this->_log('Foreign key checks disabled');

            // Step 2: Truncate tables in order
            foreach ($ordered_tables as $table) {
                if ($this->db->table_exists($table)) {
				try {
					$count_before = 0;
					try {
						$count_before = $this->db->count_all($table);
					} catch (Throwable $e) {
						// Ignore count failures; still attempt truncate.
					}

					$ok = $this->db->query("TRUNCATE TABLE `{$table}`");
					if ($ok === false) {
						$err = $this->db->error();
						$msg = isset($err['message']) ? $err['message'] : 'unknown_db_error';
						throw new Exception($msg);
					}
					if (is_numeric($count_before)) {
						$total_rows_deleted += (int)$count_before;
					}
					$this->_log("Truncated: {$table} ({$count_before} records removed)");
					$cleaned_tables++;
				} catch (Throwable $e) {
					$errors[] = "Failed to truncate {$table}: " . $e->getMessage();
					$this->_log("ERROR truncating {$table}: " . $e->getMessage());
					log_message('error', 'DEMO_CLEANUP_TRUNCATE_FAILED table=' . $table . ' msg=' . $e->getMessage());
				}
                } else {
                    $skipped_tables++;
                    $this->_log("Skipped (not exists): {$table}");
                }
            }

            // Step 3: Re-enable foreign key checks
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
            $this->_log('Foreign key checks re-enabled');

            // Step 4: Log the cleanup action
            $this->_log_cleanup_action();

			if ($audit_id) {
				try {
					$status = 'COMPLETED';
					if (!empty($errors)) {
						$status = 'COMPLETED_WITH_ERRORS';
					}
					$this->db->where('audit_id', $audit_id)->update('demo_cleanup_audit', array(
						'status' => $status,
						'completed_at' => date('Y-m-d H:i:s'),
						'cleaned_tables' => (int)$cleaned_tables,
						'skipped_tables' => (int)$skipped_tables,
						'total_rows_deleted' => (int)$total_rows_deleted,
						'errors_json' => json_encode($errors),
					));
				} catch (Throwable $e) {
				}
			}

            echo json_encode([
                'status' => 'success',
                'message' => "Demo cleanup completed successfully!",
                'cleaned_tables' => $cleaned_tables,
                'skipped_tables' => $skipped_tables,
                'errors' => $errors,
                'log' => $this->cleanup_log
            ]);

        		} catch (Throwable $e) {
            // Re-enable foreign keys even on error
            $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
			log_message('error', 'DEMO_CLEANUP_FAILED msg=' . $e->getMessage());
			if ($audit_id) {
				try {
					$this->db->where('audit_id', $audit_id)->update('demo_cleanup_audit', array(
						'status' => 'FAILED',
						'completed_at' => date('Y-m-d H:i:s'),
						'cleaned_tables' => (int)$cleaned_tables,
						'skipped_tables' => (int)$skipped_tables,
						'total_rows_deleted' => (int)$total_rows_deleted,
						'errors_json' => json_encode(array_merge($errors, array('fatal' => $e->getMessage()))),
					));
				} catch (Throwable $ex) {
				}
			}
            
            echo json_encode([
                'status' => 'error',
                'message' => 'Cleanup failed: ' . $e->getMessage(),
                'log' => $this->cleanup_log
            ]);
		} finally {
			if ($prev_db_debug !== null) {
				$this->db->db_debug = $prev_db_debug;
			}
        }
    }

    /**
     * Generate SQL script for manual execution
     */
    public function generate_sql()
    {
		$ordered_tables = $this->_get_fk_ordered_tables($this->tables_to_truncate);
		$db_name = isset($this->db->database) ? (string)$this->db->database : '';
        $sql = "-- ============================================\n";
        $sql .= "-- HMS Demo Database Cleanup Script\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
		$sql .= "-- Database: " . ($db_name !== '' ? $db_name : 'unknown') . "\n";
        $sql .= "-- ============================================\n\n";
        
        $sql .= "-- IMPORTANT: This script will DELETE all patient and transaction data!\n";
        $sql .= "-- Make sure you have a backup before running this script.\n\n";
        
        $sql .= "-- Step 1: Disable Foreign Key Checks\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        $sql .= "-- Step 2: Truncate Patient/Transaction Tables\n";
		foreach ($ordered_tables as $table) {
            $sql .= "TRUNCATE TABLE `{$table}`;\n";
        }
        
        $sql .= "\n-- Step 3: Re-enable Foreign Key Checks\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n\n";
        
        $sql .= "-- Cleanup Complete!\n";
        $sql .= "-- Tables cleaned: " . count($this->tables_to_truncate) . "\n";

        // Output as downloadable file
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="hms_demo_cleanup_' . date('Y-m-d_His') . '.sql"');
        echo $sql;
    }

    /**
     * Create backup before cleanup using pure PHP SQL export
     */
    public function backup()
    {
        header('Content-Type: application/json');
        
        $backup_file = 'hms_backup_' . date('Y-m-d_His') . '.sql';
        $backup_path = FCPATH . 'backups/' . $backup_file;
        
        // Create backups directory if not exists
        if (!is_dir(FCPATH . 'backups')) {
            @mkdir(FCPATH . 'backups', 0755, true);
        }

        // Check if directory is writable
        if (!is_writable(FCPATH . 'backups')) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Backups directory is not writable. Please check permissions on: ' . FCPATH . 'backups'
            ]);
            return;
        }

        try {
            $sql = "-- HMS Database Backup\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Database: " . $this->db->database . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            // Get all tables
            $tables = $this->db->list_tables();
            $table_count = 0;
            $row_count = 0;
            
            foreach ($tables as $table) {
                // Get CREATE TABLE statement
                $query = $this->db->query("SHOW CREATE TABLE `{$table}`");
                $row = $query->row_array();
                
                $sql .= "-- Table: {$table}\n";
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
                
                // The key can be 'Create Table' or 'Create View' depending on table type
                $create_sql = isset($row['Create Table']) ? $row['Create Table'] : 
                              (isset($row['Create View']) ? $row['Create View'] : '');
                if ($create_sql) {
                    $sql .= $create_sql . ";\n\n";
                } else {
                    $sql .= "-- Could not get CREATE statement for {$table}\n\n";
                }
                
                // Get table data
                $data_query = $this->db->get($table);
                if ($data_query->num_rows() > 0) {
                    $fields = $this->db->list_fields($table);
                    
                    foreach ($data_query->result_array() as $data_row) {
                        $values = array();
                        foreach ($data_row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = $this->db->escape($value);
                            }
                        }
                        $sql .= "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) VALUES (" . implode(', ', $values) . ");\n";
                        $row_count++;
                    }
                    $sql .= "\n";
                }
                $table_count++;
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            $sql .= "-- Backup complete: {$table_count} tables, {$row_count} rows\n";
            
            // Write to file
            if (file_put_contents($backup_path, $sql) !== false) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Backup created successfully',
                    'file' => $backup_file,
                    'size' => filesize($backup_path),
                    'tables' => $table_count,
                    'rows' => $row_count
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to write backup file.'
                ]);
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Backup failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Add entry to cleanup log
     */
    private function _log($message)
    {
        $this->cleanup_log[] = [
            'time' => date('H:i:s'),
            'message' => $message
        ];
    }

    /**
     * Log cleanup action to system audit
     */
    private function _log_cleanup_action()
    {
        $user_id = $this->session->userdata('user_id');
        
        // Log to logfile table if exists (using correct column names)
        if ($this->db->table_exists('logfile')) {
            $this->db->insert('logfile', [
                'user_id' => $user_id,
                'module' => 'DEMO_CLEANUP',
                'event' => 'CLEANUP',
                'ipaddress' => $this->input->ip_address(),
                'value' => 'Demo database cleanup executed. ' . count($this->tables_to_truncate) . ' tables truncated.',
                'date_time' => date('Y-m-d H:i:s')
            ]);
        }
        
        // Log to system_audit_log if exists (using correct column names)
        if ($this->db->table_exists('system_audit_log')) {
            $this->db->insert('system_audit_log', [
                'audit_type' => 'SYSTEM',
                'module' => 'DEMO_CLEANUP',
                'action' => 'FULL_CLEANUP',
                'table_name' => 'MULTIPLE',
                'change_summary' => 'Demo database cleanup: ' . count($this->tables_to_truncate) . ' tables truncated',
                'user_id' => $user_id,
                'username' => $this->session->userdata('username'),
                'ip_address' => $this->input->ip_address(),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
}
