<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class OrderReconciliationEngine
{
	protected $CI;
	protected $severity_rank = array('LOW' => 1, 'MEDIUM' => 2, 'HIGH' => 3);
	protected $table = 'order_reconciliation_log';
	protected static $fingerprint_generated = null;

	private function build_resolution_summary($row, $action)
	{
		$first_seen = isset($row['first_seen']) ? (string)$row['first_seen'] : '';
		$last_seen = isset($row['last_seen']) ? (string)$row['last_seen'] : '';
		$occurrences = isset($row['occurrences']) ? (int)$row['occurrences'] : 0;
		$first_ts = $first_seen !== '' ? strtotime($first_seen) : false;
		$last_ts = $last_seen !== '' ? strtotime($last_seen) : false;
		$duration = 0;
		if ($first_ts && $last_ts) {
			$duration = max(0, (int)($last_ts - $first_ts));
		}
		return json_encode(array(
			'action' => (string)$action,
			'resolved_at' => date('Y-m-d H:i:s'),
			'duration_seconds' => $duration,
			'occurrences' => $occurrences,
		));
	}

	public function __construct()
	{
		$this->CI =& get_instance();
		$this->CI->load->database();
		$this->CI->load->model('app/Order_master_model');
		$this->ensure_table();
	}

	public function ensure_table()
	{
		$this->CI->db->query("CREATE TABLE IF NOT EXISTS `order_reconciliation_log` (
			`id` INT AUTO_INCREMENT PRIMARY KEY,
			`patient_no` VARCHAR(25),
			`visit_id` VARCHAR(25),
			`issue_type` VARCHAR(50),
			`severity` ENUM('LOW','MEDIUM','HIGH'),
			`clinical_status` VARCHAR(30),
			`financial_status` VARCHAR(30),
			`payment_status` VARCHAR(30),
			`detected_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
			`resolved` TINYINT(1) DEFAULT 0,
			`resolution_note` TEXT NULL,
			INDEX `idx_visit` (`visit_id`),
			INDEX `idx_resolved` (`resolved`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		if ($this->CI->db->field_exists('first_seen', $this->table) === false) {
			$this->CI->db->query("ALTER TABLE `order_reconciliation_log` ADD COLUMN `first_seen` DATETIME NULL AFTER `detected_at`");
		}
		if ($this->CI->db->field_exists('last_seen', $this->table) === false) {
			$this->CI->db->query("ALTER TABLE `order_reconciliation_log` ADD COLUMN `last_seen` DATETIME NULL AFTER `first_seen`");
		}
		if ($this->CI->db->field_exists('occurrences', $this->table) === false) {
			$this->CI->db->query("ALTER TABLE `order_reconciliation_log` ADD COLUMN `occurrences` INT DEFAULT 1 AFTER `last_seen`");
		}
		if ($this->CI->db->field_exists('fingerprint', $this->table) === false) {
			$ok = $this->CI->db->query("ALTER TABLE `order_reconciliation_log`
				ADD COLUMN `fingerprint` CHAR(32)
				GENERATED ALWAYS AS (
					MD5(CONCAT(
						COALESCE(`visit_id`,''),'|',
						COALESCE(`issue_type`,''),'|',
						COALESCE(`clinical_status`,''),'|',
						COALESCE(`financial_status`,''),'|',
						COALESCE(`payment_status`,'')
					))
				) STORED");
			if (!$ok) {
				$this->CI->db->query("ALTER TABLE `order_reconciliation_log` ADD COLUMN `fingerprint` CHAR(32) NULL");
			}
		}

		$this->ensure_index('idx_fingerprint', "CREATE INDEX `idx_fingerprint` ON `order_reconciliation_log` (`fingerprint`)");
		$this->ensure_index('idx_visit_issue_resolved', "CREATE INDEX `idx_visit_issue_resolved` ON `order_reconciliation_log` (`visit_id`, `issue_type`, `resolved`)");
		$this->ensure_index('uniq_fingerprint_resolved', "CREATE UNIQUE INDEX `uniq_fingerprint_resolved` ON `order_reconciliation_log` (`fingerprint`, `resolved`)");
	}

	private function index_exists($name)
	{
		$q = $this->CI->db->query("SHOW INDEX FROM `order_reconciliation_log` WHERE Key_name = ?", array((string)$name));
		$rows = $q ? $q->result_array() : array();
		return (is_array($rows) && count($rows) > 0);
	}

	private function ensure_index($name, $sql)
	{
		if ($this->index_exists($name)) return;
		$this->CI->db->query($sql);
	}

	private function is_fingerprint_generated()
	{
		if (self::$fingerprint_generated !== null) return (bool)self::$fingerprint_generated;
		self::$fingerprint_generated = true;
		$q = $this->CI->db->query("SELECT EXTRA FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?", array($this->table, 'fingerprint'));
		$row = $q ? $q->row_array() : null;
		if (!is_array($row) || !isset($row['EXTRA'])) {
			self::$fingerprint_generated = false;
			return false;
		}
		$extra = strtoupper((string)$row['EXTRA']);
		self::$fingerprint_generated = (strpos($extra, 'GENERATED') !== false);
		return (bool)self::$fingerprint_generated;
	}

	public function analyze($order)
	{
		$issues = array();
		if (!is_array($order)) return $issues;

		$clinical = isset($order['clinical_status']) ? strtoupper(trim((string)$order['clinical_status'])) : '';
		$financial = isset($order['financial_status']) ? strtoupper(trim((string)$order['financial_status'])) : '';
		$payment = isset($order['payment_status']) ? strtoupper(trim((string)$order['payment_status'])) : '';
		$payment_cleared = in_array($payment, array('PAID', 'DEPOSIT', 'COVERED', 'NO_BILL_REQUIRED'), true);

		if ($clinical === 'COMPLETED' && !$payment_cleared) {
			$issues[] = $this->issue('COMPLETED_NOT_PAID', 'HIGH', $order);
		}

		if ($payment === 'PAID' && $clinical !== 'COMPLETED') {
			$issues[] = $this->issue('PAID_NOT_COMPLETED', 'HIGH', $order);
		}

		if ($financial === 'NO_BILL_REQUIRED' && $payment === 'PAID') {
			$issues[] = $this->issue('INVALID_PAYMENT_STATE', 'MEDIUM', $order);
		}

		if ($financial === 'BILLED' && $payment === 'UNPAID') {
			$issues[] = $this->issue('BILLED_NOT_PAID', 'LOW', $order);
		}

		return $issues;
	}

	private function issue($type, $severity, $order)
	{
		return array(
			'patient_no' => isset($order['patient_no']) ? (string)$order['patient_no'] : null,
			'visit_id' => isset($order['visit_id']) ? (string)$order['visit_id'] : null,
			'issue_type' => (string)$type,
			'severity' => (string)$severity,
			'clinical_status' => isset($order['clinical_status']) ? (string)$order['clinical_status'] : null,
			'financial_status' => isset($order['financial_status']) ? (string)$order['financial_status'] : null,
			'payment_status' => isset($order['payment_status']) ? (string)$order['payment_status'] : null,
		);
	}

	public function log_issues($issues)
	{
		if (!is_array($issues) || count($issues) === 0) return;
		$this->ensure_table();

		$now = time();
		$cutoff = date('Y-m-d H:i:s', $now - 600);

		$fingerprint_generated = $this->is_fingerprint_generated();
		foreach ($issues as $issue) {
			if (!is_array($issue)) continue;
			$visit_id = isset($issue['visit_id']) ? (string)$issue['visit_id'] : '';
			$issue_type = isset($issue['issue_type']) ? (string)$issue['issue_type'] : '';
			if ($visit_id === '' || $issue_type === '') continue;

			$patient_no = isset($issue['patient_no']) ? (string)$issue['patient_no'] : '';
			$clinical = isset($issue['clinical_status']) ? (string)$issue['clinical_status'] : '';
			$financial = isset($issue['financial_status']) ? (string)$issue['financial_status'] : '';
			$payment = isset($issue['payment_status']) ? (string)$issue['payment_status'] : '';
			$meta = isset($issue['_meta_sources']) && is_array($issue['_meta_sources']) ? $issue['_meta_sources'] : array();
			$trace_id = isset($issue['_trace_id']) ? (string)$issue['_trace_id'] : null;
			$fingerprint = md5($visit_id.'|'.$issue_type.'|'.$clinical.'|'.$financial.'|'.$payment);

			$desired_sev = isset($issue['severity']) ? strtoupper(trim((string)$issue['severity'])) : 'LOW';
			if ($issue_type === 'COMPLETED_NOT_PAID') {
				$desired_sev = 'LOW';
			}

			$this->CI->db->order_by('first_seen', 'ASC');
			$this->CI->db->where('fingerprint', $fingerprint);
			$this->CI->db->where('resolved', 0);
			$exists = $this->CI->db->get('order_reconciliation_log', 1)->row_array();
			if ($exists) {
				$first = isset($exists['first_seen']) ? strtotime((string)$exists['first_seen']) : false;
				if (!$first && isset($exists['detected_at'])) {
					$first = strtotime((string)$exists['detected_at']);
				}
				$age = $first ? max(0, $now - $first) : 0;
				if ($issue_type === 'COMPLETED_NOT_PAID') {
					if ($age >= 1800) {
						$desired_sev = 'HIGH';
					} else if ($age >= 300) {
						$desired_sev = 'MEDIUM';
					} else {
						$desired_sev = 'LOW';
					}
				}

				$cur = isset($exists['severity']) ? strtoupper(trim((string)$exists['severity'])) : 'LOW';
				$curRank = isset($this->severity_rank[$cur]) ? (int)$this->severity_rank[$cur] : 1;
				$newRank = isset($this->severity_rank[$desired_sev]) ? (int)$this->severity_rank[$desired_sev] : 1;
				$upd = array(
					'last_seen' => date('Y-m-d H:i:s'),
				);
				if ($newRank > $curRank) {
					$upd['severity'] = $desired_sev;
				}
				$this->CI->db->set('occurrences', 'occurrences + 1', false);
				$this->CI->db->where('id', (int)$exists['id']);
				$this->CI->db->update('order_reconciliation_log', $upd);
				continue;
			}

			$confidence = 'MEDIUM';
			if ($desired_sev === 'HIGH') {
				$confidence = 'LOW';
			} else if ($desired_sev === 'MEDIUM') {
				$confidence = 'MEDIUM';
			} else if ($desired_sev === 'LOW') {
				$confidence = 'MEDIUM';
			}
			$note = json_encode(array('fp' => $fingerprint, 'meta' => $meta, 'trace_id' => $trace_id, 'confidence' => $confidence));
			$issue['severity'] = $desired_sev;
			$issue['resolution_note'] = $note;
			$issue['first_seen'] = date('Y-m-d H:i:s');
			$issue['last_seen'] = $issue['first_seen'];
			$issue['occurrences'] = 1;
			unset($issue['_meta_sources']);
			unset($issue['_trace_id']);
			if (!$fingerprint_generated) {
				$issue['fingerprint'] = $fingerprint;
			}

			$this->CI->db->insert('order_reconciliation_log', $issue);
			$err = $this->CI->db->error();
			if (is_array($err) && isset($err['code']) && (int)$err['code'] === 1062) {
				$this->CI->db->set('occurrences', 'occurrences + 1', false);
				$this->CI->db->where('fingerprint', $fingerprint);
				$this->CI->db->where('resolved', 0);
				$this->CI->db->update('order_reconciliation_log', array('last_seen' => date('Y-m-d H:i:s')));
			}
		}
	}

	public function auto_resolve_normalized($order, $trace_id = null)
	{
		if (!is_array($order)) return false;
		$this->ensure_table();
		$visit_id = isset($order['visit_id']) ? (string)$order['visit_id'] : '';
		if ($visit_id === '') return false;
		$this->CI->db->where('visit_id', $visit_id);
		$this->CI->db->where('resolved', 0);
		$rows = $this->CI->db->get('order_reconciliation_log')->result_array();
		if (!is_array($rows) || count($rows) === 0) return false;
		foreach ($rows as $r) {
			$note = $this->build_resolution_summary($r, 'AUTO: STATE_NORMALIZED');
			if ($trace_id !== null && (string)$trace_id !== '') {
				$note = json_encode(array(
					'action' => 'AUTO: STATE_NORMALIZED',
					'trace_id' => (string)$trace_id,
					'resolved_at' => date('Y-m-d H:i:s'),
					'duration_seconds' => isset($r['first_seen'], $r['last_seen']) ? max(0, (int)(strtotime((string)$r['last_seen']) - strtotime((string)$r['first_seen']))) : 0,
					'occurrences' => isset($r['occurrences']) ? (int)$r['occurrences'] : 0,
				));
			}
			$this->CI->db->where('id', (int)$r['id']);
			$this->CI->db->update('order_reconciliation_log', array('resolved' => 1, 'resolution_note' => $note));
		}
		return true;
	}

	public function attempt_repair($order)
	{
		if (!is_array($order)) return null;
		$this->CI->load->database();

		$financial = isset($order['financial_status']) ? strtoupper(trim((string)$order['financial_status'])) : '';
		$payment = isset($order['payment_status']) ? strtoupper(trim((string)$order['payment_status'])) : '';

		if ($financial === 'NO_BILL_REQUIRED' && $payment === 'PAID') {
			$order_id = isset($order['id']) ? (int)$order['id'] : 0;
			if ($order_id <= 0) {
				return null;
			}

			$fixed = false;
			$this->CI->db->trans_start();
			$nowTs = time();

			$row = null;
			$q = $this->CI->db->query("SELECT * FROM `order_master` WHERE `id` = ? AND `InActive` = 0 FOR UPDATE", array($order_id));
			if ($q) {
				$row = $q->row_array();
			}
			if (!$row) {
				$q2 = $this->CI->db->get_where('order_master', array('id' => $order_id, 'InActive' => 0), 1);
				$row = $q2 ? $q2->row_array() : null;
			}

			if ($row) {
				$updated_at = isset($row['updated_at']) ? strtotime((string)$row['updated_at']) : false;
				if ($updated_at && $updated_at > ($nowTs - 10)) {
					$this->CI->db->trans_complete();
					return null;
				}

				$fin = isset($row['financial_status']) ? strtoupper(trim((string)$row['financial_status'])) : '';
				$pay = isset($row['payment_status']) ? strtoupper(trim((string)$row['payment_status'])) : '';
				if ($fin === 'NO_BILL_REQUIRED' && $pay === 'PAID') {
					$now = date('Y-m-d H:i:s');
					$this->CI->db->where('id', (int)$row['id']);
					$this->CI->db->where('InActive', 0);
					$this->CI->db->update('order_master', array(
						'payment_status' => 'NO_BILL_REQUIRED',
						'updated_at' => $now,
						'last_sync_at' => $now,
					));
					$fixed = true;

					$this->ensure_table();
					$this->CI->db->where('visit_id', isset($row['visit_id']) ? (string)$row['visit_id'] : '');
					$this->CI->db->where('issue_type', 'INVALID_PAYMENT_STATE');
					$this->CI->db->where('resolved', 0);
					$to_resolve = $this->CI->db->get('order_reconciliation_log')->result_array();
					if (is_array($to_resolve)) {
						foreach ($to_resolve as $lr) {
							$note = $this->build_resolution_summary($lr, 'AUTO: FIXED_INVALID_PAYMENT_STATE');
							$this->CI->db->where('id', (int)$lr['id']);
							$this->CI->db->update('order_reconciliation_log', array(
								'resolved' => 1,
								'resolution_note' => $note
							));
						}
					}
				}
			}

			$this->CI->db->trans_complete();
			if ($this->CI->db->trans_status() === false) {
				return null;
			}
			return $fixed ? 'FIXED_INVALID_PAYMENT_STATE' : null;
		}

		return null;
	}
}
