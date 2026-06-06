<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Sms_model extends CI_Model
{
	public function __construct(){
		parent::__construct();
	}

	private function table_exists($table_name){
		$q = $this->db->query("SHOW TABLES LIKE " . $this->db->escape((string)$table_name));
		return ($q && $q->num_rows() > 0);
	}

	/* ── Table Installation ──────────────────────────── */

	public function install_sms_tables(){
		$this->db->query("CREATE TABLE IF NOT EXISTS `sms_templates` (
			`template_id` int(11) NOT NULL AUTO_INCREMENT,
			`template_key` varchar(50) NOT NULL,
			`template_body` text NOT NULL,
			`description` varchar(255) DEFAULT NULL,
			`is_active` tinyint(1) NOT NULL DEFAULT 1,
			`updated_at` datetime DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`template_id`),
			UNIQUE KEY `uq_key` (`template_key`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `sms_queue` (
			`sms_id` bigint(11) NOT NULL AUTO_INCREMENT,
			`recipient_phone` varchar(30) NOT NULL,
			`patient_no` varchar(25) DEFAULT NULL,
			`iop_id` varchar(25) DEFAULT NULL,
			`template_key` varchar(50) DEFAULT NULL,
			`message_body` text NOT NULL,
			`status` varchar(20) NOT NULL DEFAULT 'QUEUED',
			`attempts` int(3) NOT NULL DEFAULT 0,
			`last_attempt_at` datetime DEFAULT NULL,
			`sent_at` datetime DEFAULT NULL,
			`error_message` text DEFAULT NULL,
			`created_at` datetime NOT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`sms_id`),
			KEY `idx_status` (`status`),
			KEY `idx_patient` (`patient_no`),
			KEY `idx_created` (`created_at`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->db->query("CREATE TABLE IF NOT EXISTS `sms_config` (
			`config_id` int(11) NOT NULL AUTO_INCREMENT,
			`config_key` varchar(50) NOT NULL,
			`config_value` varchar(500) NOT NULL,
			`description` varchar(255) DEFAULT NULL,
			`InActive` int(1) NOT NULL DEFAULT 0,
			PRIMARY KEY (`config_id`),
			UNIQUE KEY `uq_sms_key` (`config_key`)
		) ENGINE=MyISAM DEFAULT CHARSET=latin1");

		$this->seed_defaults();
		return true;
	}

	private function seed_defaults(){
		$this->db->where(array('template_key' => 'post_visit', 'InActive' => 0));
		if (!$this->db->get('sms_templates')->row()) {
			$now = date('Y-m-d H:i:s');
			$templates = array(
				array('post_visit', 'Dear {PATIENT_NAME}, thank you for visiting {HOSPITAL_NAME}. We wish you a speedy recovery. For enquiries call {HOSPITAL_PHONE}.', 'Post-visit appreciation SMS'),
				array('birthday', 'Happy Birthday {PATIENT_NAME}! {HOSPITAL_NAME} wishes you good health and many happy returns. Call us at {HOSPITAL_PHONE}.', 'Birthday greeting SMS'),
				array('nhis_claim_approved', 'Dear {PATIENT_NAME}, your NHIS claim {CLAIM_REF} has been approved. Thank you for choosing {HOSPITAL_NAME}.', 'NHIS claim approval notification'),
				array('appointment_reminder', 'Dear {PATIENT_NAME}, this is a reminder of your appointment at {HOSPITAL_NAME} on {APPOINTMENT_DATE}. Call {HOSPITAL_PHONE} to reschedule.', 'Appointment reminder SMS')
			);
			foreach ($templates as $t) {
				$this->db->insert('sms_templates', array(
					'template_key' => $t[0], 'template_body' => $t[1],
					'description' => $t[2], 'is_active' => 1, 'updated_at' => $now, 'InActive' => 0
				));
			}
		}

		$this->db->where(array('config_key' => 'sms_gateway_url', 'InActive' => 0));
		if (!$this->db->get('sms_config')->row()) {
			$configs = array(
				array('sms_gateway_url', '', 'SMS gateway API URL'),
				array('sms_api_key', '', 'SMS gateway API key'),
				array('sms_sender_id', 'HMS', 'SMS sender ID / name'),
				array('hospital_name', 'Hebrew Medical Center', 'Hospital name for SMS templates'),
				array('hospital_phone', '', 'Hospital phone for SMS templates'),
				array('sms_enabled', '0', 'Enable SMS sending (1=yes, 0=no/queue only)')
			);
			foreach ($configs as $c) {
				$this->db->insert('sms_config', array(
					'config_key' => $c[0], 'config_value' => $c[1],
					'description' => $c[2], 'InActive' => 0
				));
			}
		}
	}

	/* ── Config Helpers ──────────────────────────────── */

	public function get_sms_config($key, $default = ''){
		$this->install_sms_tables();
		$this->db->select('config_value');
		$r = $this->db->get_where('sms_config', array('config_key' => (string)$key, 'InActive' => 0), 1)->row();
		return ($r && isset($r->config_value)) ? (string)$r->config_value : $default;
	}

	public function set_sms_config($key, $value){
		$this->install_sms_tables();
		$this->db->where(array('config_key' => (string)$key, 'InActive' => 0));
		$ex = $this->db->get('sms_config')->row();
		if ($ex) {
			$this->db->where(array('config_key' => (string)$key, 'InActive' => 0));
			$this->db->update('sms_config', array('config_value' => (string)$value));
		} else {
			$this->db->insert('sms_config', array('config_key' => (string)$key, 'config_value' => (string)$value, 'InActive' => 0));
		}
	}

	/* ── Template Helpers ────────────────────────────── */

	public function get_template($key){
		$this->install_sms_tables();
		$this->db->where(array('template_key' => (string)$key, 'is_active' => 1, 'InActive' => 0));
		return $this->db->get('sms_templates')->row();
	}

	public function render_template($template_key, $vars = array()){
		$tpl = $this->get_template($template_key);
		if (!$tpl) { return null; }
		$body = (string)$tpl->template_body;
		$vars['HOSPITAL_NAME'] = isset($vars['HOSPITAL_NAME']) ? $vars['HOSPITAL_NAME'] : $this->get_sms_config('hospital_name', 'Hospital');
		$vars['HOSPITAL_PHONE'] = isset($vars['HOSPITAL_PHONE']) ? $vars['HOSPITAL_PHONE'] : $this->get_sms_config('hospital_phone', '');
		foreach ($vars as $k => $v) {
			$body = str_replace('{' . $k . '}', (string)$v, $body);
		}
		return $body;
	}

	/* ── Queue Operations ────────────────────────────── */

	public function queue_sms($phone, $message, $patient_no = null, $iop_id = null, $template_key = null){
		$this->install_sms_tables();
		$phone = trim((string)$phone);
		if ($phone === '') { return false; }

		$row = array(
			'recipient_phone' => $phone,
			'patient_no' => $patient_no !== null ? (string)$patient_no : null,
			'iop_id' => $iop_id !== null ? (string)$iop_id : null,
			'template_key' => $template_key !== null ? (string)$template_key : null,
			'message_body' => (string)$message,
			'status' => 'QUEUED',
			'attempts' => 0,
			'created_at' => date('Y-m-d H:i:s'),
			'InActive' => 0
		);

		try {
			$ok = $this->db->insert('sms_queue', $row);
			$id = (int)$this->db->insert_id();
			if (!$ok || $id <= 0) {
				log_message('error', '[SMS_QUEUE_FAIL] insert returned falsy. phone=' . $phone
					. ' patient=' . $patient_no . ' db_error=' . json_encode($this->db->error()));
				return false;
			}
			return $id;
		} catch (Exception $e) {
			log_message('error', '[SMS_QUEUE_EXCEPTION] ' . $e->getMessage()
				. ' phone=' . $phone . ' patient=' . $patient_no);
			return false;
		}
	}

	/**
	 * Queue a post-visit appreciation SMS for a patient.
	 */
	public function queue_post_visit_sms($patient_no, $iop_id = null){
		$this->load->model('app/patient_model');
		$pat = $this->patient_model->getPatientInfo((string)$patient_no);
		if (!$pat) {
			log_message('error', '[SMS_POST_VISIT_SKIP] patient not found. patient_no=' . $patient_no);
			return false;
		}
		$phone = isset($pat->contact_no) ? trim((string)$pat->contact_no) : '';
		if ($phone === '') {
			log_message('error', '[SMS_POST_VISIT_SKIP] no phone number. patient_no=' . $patient_no);
			return false;
		}
		$name = trim((isset($pat->firstname) ? $pat->firstname : '') . ' ' . (isset($pat->lastname) ? $pat->lastname : ''));
		$body = $this->render_template('post_visit', array('PATIENT_NAME' => $name));
		if ($body === null) {
			log_message('error', '[SMS_POST_VISIT_SKIP] template not found or inactive. patient_no=' . $patient_no);
			return false;
		}
		return $this->queue_sms($phone, $body, $patient_no, $iop_id, 'post_visit');
	}

	/**
	 * Queue birthday SMS for all patients whose birthday is today.
	 * Designed to be called by a daily cron/scheduled task.
	 */
	public function queue_birthday_sms_batch(){
		$this->install_sms_tables();
		$today = date('m-d');
		$this->db->select('patient_no, firstname, lastname, contact_no');
		$this->db->where("DATE_FORMAT(dob, '%m-%d') = '" . $today . "'", null, false);
		$this->db->where('InActive', 0);
		$patients = $this->db->get('patient_personal_info')->result();
		$queued = 0;
		foreach ($patients as $p) {
			$phone = isset($p->contact_no) ? trim((string)$p->contact_no) : '';
			if ($phone === '') { continue; }
			$this->db->where(array('patient_no' => $p->patient_no, 'template_key' => 'birthday', 'InActive' => 0));
			$this->db->where("DATE(created_at) = '" . date('Y-m-d') . "'", null, false);
			if ($this->db->get('sms_queue')->row()) { continue; }
			$name = trim($p->firstname . ' ' . $p->lastname);
			$body = $this->render_template('birthday', array('PATIENT_NAME' => $name));
			if ($body === null) { continue; }
			$result = $this->queue_sms($phone, $body, $p->patient_no, null, 'birthday');
			if ($result !== false && $result > 0) {
				$queued++;
			} else {
				log_message('error', '[BIRTHDAY_SMS_QUEUE_FAIL] patient=' . $p->patient_no);
			}
		}
		return $queued;
	}

	/**
	 * Process queued SMS messages. Non-blocking batch processor.
	 * Sends up to $limit messages per call. Returns count sent.
	 * Requires sms_enabled=1 and a configured gateway.
	 */
	public function process_sms_queue($limit = 20){
		$this->install_sms_tables();
		$enabled = (int)$this->get_sms_config('sms_enabled', '0');
		if ($enabled !== 1) { return 0; }
		$gatewayUrl = trim($this->get_sms_config('sms_gateway_url', ''));
		if ($gatewayUrl === '') { return 0; }

		$this->db->where(array('status' => 'QUEUED', 'InActive' => 0));
		$this->db->where('attempts <', 3);
		$this->db->order_by('created_at', 'ASC');
		$this->db->limit($limit);
		$messages = $this->db->get('sms_queue')->result();
		$sent = 0;

		foreach ($messages as $msg) {
			$this->db->where('sms_id', $msg->sms_id);
			$this->db->update('sms_queue', array(
				'attempts' => (int)$msg->attempts + 1,
				'last_attempt_at' => date('Y-m-d H:i:s')
			));

			$result = $this->send_sms_via_gateway($msg->recipient_phone, $msg->message_body);
			if ($result['success']) {
				$this->db->where('sms_id', $msg->sms_id);
				$this->db->update('sms_queue', array('status' => 'SENT', 'sent_at' => date('Y-m-d H:i:s')));
				$sent++;
			} else {
				$newAttempts = (int)$msg->attempts + 1;
				$newStatus = ($newAttempts >= 3) ? 'FAILED' : 'QUEUED';
				$this->db->where('sms_id', $msg->sms_id);
				$this->db->update('sms_queue', array('status' => $newStatus, 'error_message' => $result['error']));
			}
		}
		return $sent;
	}

	/**
	 * Send a single SMS via configured gateway. Returns array('success' => bool, 'error' => string).
	 * This is a pluggable method — adapt to your SMS provider's API.
	 */
	private function send_sms_via_gateway($phone, $message){
		$url = trim($this->get_sms_config('sms_gateway_url', ''));
		$apiKey = trim($this->get_sms_config('sms_api_key', ''));
		$senderId = trim($this->get_sms_config('sms_sender_id', 'HMS'));

		if ($url === '') {
			return array('success' => false, 'error' => 'No gateway URL configured');
		}

		$postData = array(
			'api_key' => $apiKey,
			'sender_id' => $senderId,
			'phone' => $phone,
			'message' => $message
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);

		if ($err !== '') {
			return array('success' => false, 'error' => 'cURL error: ' . $err);
		}
		if ($httpCode >= 200 && $httpCode < 300) {
			return array('success' => true, 'error' => '');
		}
		return array('success' => false, 'error' => 'HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 200));
	}

	/* ── Queue Stats ─────────────────────────────────── */

	public function get_queue_stats(){
		$this->install_sms_tables();
		$stats = array('queued' => 0, 'sent' => 0, 'failed' => 0);
		$this->db->select("status, COUNT(*) as cnt", false);
		$this->db->where('InActive', 0);
		$this->db->group_by('status');
		$rows = $this->db->get('sms_queue')->result();
		foreach ($rows as $r) {
			$s = strtoupper(trim((string)$r->status));
			if ($s === 'QUEUED') { $stats['queued'] = (int)$r->cnt; }
			elseif ($s === 'SENT') { $stats['sent'] = (int)$r->cnt; }
			elseif ($s === 'FAILED') { $stats['failed'] = (int)$r->cnt; }
		}
		return $stats;
	}
}
