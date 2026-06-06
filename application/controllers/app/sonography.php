<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

require APPPATH . 'controllers/general.php';

class Sonography extends General
{
	private $limit = 10;

	private function auth_debug_log($stage, $context = array()){
		return;
		try {
			$u = isset($this->data['userInfo']) ? $this->data['userInfo'] : null;
			$moduleKey = $this->current_user_module_key();
			$roleId = $this->current_user_role_id();
			$hasSono = (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) ? 1 : 0;
			$hasAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']) ? 1 : 0;
			$hasDoctor = (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) ? 1 : 0;
			$rawModule = ($u && isset($u->module)) ? (string)$u->module : '';
			$uri = method_exists($this->uri, 'uri_string') ? (string)$this->uri->uri_string() : '';
			$ctx = array_merge(array(
				'stage' => (string)$stage,
				'uri' => $uri,
				'user_id' => (string)$this->session->userdata('user_id'),
				'user_role' => (string)$roleId,
				'module_key' => (string)$moduleKey,
				'raw_module' => $rawModule,
				'has_sonography' => $hasSono,
				'has_admin' => $hasAdmin,
				'has_doctor' => $hasDoctor
			), is_array($context) ? $context : array());
			log_message('error', 'SONO_AUTH_DEBUG ' . json_encode($ctx));
		} catch (Exception $e) {
			log_message('error', 'SONO_AUTH_DEBUG stage='.(string)$stage.' exception='.(string)$e->getMessage());
		}
	}

	private function current_user_is_sonography_staff(){
		$module = $this->current_user_module_key();
		return ($module === 'sonography' || $module === 'sonographer' || $module === 'sonography module' || $module === 'sonography_module');
	}

	private function require_sonography_write_access(){
		$isAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		$isSono = $this->current_user_is_sonography_staff();
		if (!$isAdmin && !$isSono) {
			$this->auth_debug_log('DENY_WRITE_ACCESS', array('is_admin' => $isAdmin ? 1 : 0, 'is_sono' => $isSono ? 1 : 0));
			redirect(base_url() . 'access_denied');
			exit;
		}
	}

	private function ensure_private_lab_result_dir(){
		// Store uploads ABOVE the web root so they are not directly accessible via URL.
		$dir = rtrim(dirname(rtrim(FCPATH, '\\/')),'\\/')
			. DIRECTORY_SEPARATOR . 'hms_private_uploads'
			. DIRECTORY_SEPARATOR . 'patient_lab_result';
		if (!is_dir($dir)) {
			@mkdir($dir, 0750, true);
		}
		return $dir;
	}

	public function __construct()
	{
		parent::__construct();
		$this->load->model("app/laboratory_model");
		$this->load->model("app/billing_model");
		$this->load->model("app/diagnostic_safety_model");
		$this->load->model('app/service_gate_audit_model', 'service_gate_audit');
		if (General::is_logged_in() == FALSE) {
			redirect(base_url() . 'login');
		}
		General::variable();

		$seg3 = strtolower(trim((string)$this->uri->segment(3)));
		$seg4 = strtolower(trim((string)$this->uri->segment(4)));
		if ($seg3 === 'imaging_queue' && in_array($seg4, array('xray', 'ecg', 'ct'), true) && has_role(array('radiology', 'radiologist'))) {
			redirect(base_url() . 'app/radiology/' . $seg4 . '_queue');
			exit;
		}

		// Access controlled by sidebar visibility (hasAccesstoSonography) - no additional role check needed
		if (!(isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) && 
		    !(isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) &&
		    !$this->current_user_is_admin()) {
			$this->session->set_flashdata('message', '<div class="alert alert-danger"><i class="fa fa-ban"></i> Access denied. You do not have permission to view this page.</div>');
			redirect(base_url() . 'app/dashboard');
			exit;
		}
		if (!$this->session->userdata('_schema_sonography_ok')) {
			$this->laboratory_model->ensure_lab_schema();
			$this->diagnostic_safety_model->ensure_all_safety_schemas();
			$this->session->set_userdata('_schema_sonography_ok', 1);
		}
	}

	/**
	 * Enforce sonography billing gate - BLOCKS result entry if payment not complete
	 * @param int $io_lab_id
	 * @param bool $block_action If true, will redirect on failure; if false, just returns status
	 * @return array ['allowed' => bool, 'reason' => string, 'bypass' => bool]
	 */
	private function enforce_sonography_billing_gate($io_lab_id, $block_action = false, $action = ''){
		$io_lab_id = (int)$io_lab_id;
		$this->laboratory_model->install_imaging_tables();
		
		// SSOT-first gate check (prevents payment-status drift between queue badge and result-entry gate).
		// We keep the legacy check as a fallback for older deployments that may not yet have SSOT rows.
		$labRow = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		$iopId = $labRow && isset($labRow->iop_id) ? (string)$labRow->iop_id : '';
		$patientNo = $labRow && isset($labRow->patient_no) ? (string)$labRow->patient_no : null;

		$this->load->model('app/service_gate_model', 'service_gate');
		$ssotGate = isset($this->service_gate) && method_exists($this->service_gate, 'check_service')
			? $this->service_gate->check_service('SONOGRAPHY', (string)$io_lab_id, $iopId !== '' ? $iopId : null, $patientNo)
			: null;

		$gateCheck = null;
		if (is_array($ssotGate) && array_key_exists('allowed', $ssotGate)) {
			$gateCheck = array(
				'allowed' => (bool)$ssotGate['allowed'],
				'reason' => isset($ssotGate['reason']) ? (string)$ssotGate['reason'] : ((bool)$ssotGate['allowed'] ? 'Payment cleared' : 'Payment required'),
				'bypass' => (isset($ssotGate['status']) && (string)$ssotGate['status'] === 'BYPASSED'),
				'blocked_reason' => isset($ssotGate['blocked_reason']) ? (string)$ssotGate['blocked_reason'] : null,
				'source' => 'SSOT',
				'ssot' => $ssotGate,
			);
		} else {
			// Legacy fallback (charge/invoice based).
			$gateCheck = $this->laboratory_model->check_sonography_billing_gate($io_lab_id);
			if (is_array($gateCheck)) {
				$gateCheck['source'] = 'LEGACY';
			}
		}
		
		if (!$gateCheck['allowed']) {
			$userId = (string)$this->session->userdata('user_id');
			$method = isset($_SERVER['REQUEST_METHOD']) ? strtoupper((string)$_SERVER['REQUEST_METHOD']) : '';
			$uri = method_exists($this->uri, 'uri_string') ? (string)$this->uri->uri_string() : '';
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$action = trim((string)$action);
			$itemRef = null;
			$fallbackRef = true;
			$charge = $this->laboratory_model->get_sonography_charge_by_lab($io_lab_id);
			if ($charge && isset($charge->charge_id) && (int)$charge->charge_id > 0) {
				$itemRef = 'sono_charge_id:' . (int)$charge->charge_id;
				$fallbackRef = false;
			} else {
				$itemRef = 'sono_charge_id:0';
			}
			$blockedReason = isset($gateCheck['blocked_reason']) ? trim((string)$gateCheck['blocked_reason']) : '';
			if ($blockedReason === '') {
				$blockedReason = $fallbackRef ? 'NO_SSOT' : 'PAYMENT_PENDING';
			}

			try {
				if (isset($this->service_gate_audit) && method_exists($this->service_gate_audit, 'log_event')) {
					$this->service_gate_audit->log_event(array(
						'event_code' => 'SONO_GATE_BLOCKED',
						'module' => 'SONOGRAPHY',
						'item_ref' => $itemRef,
						'user_id' => $userId !== '' ? (int)$userId : null,
						'action' => $action !== '' ? $action : null,
						'blocked_reason' => $blockedReason,
						'allowed' => 0,
						'gate_version' => 'v1',
						'reason' => isset($gateCheck['reason']) ? (string)$gateCheck['reason'] : null,
						'payload' => array(
							'io_lab_id' => (int)$io_lab_id,
							'iop_id' => $iopId,
							'item_ref' => $itemRef,
							'fallback_ref' => $fallbackRef ? 1 : 0,
							'gate_version' => 'v1',
							'method' => $method,
							'uri' => $uri,
							'allowed' => false,
							'bypass' => !empty($gateCheck['bypass']) ? 1 : 0,
							'gate_source' => isset($gateCheck['source']) ? (string)$gateCheck['source'] : null,
							'gate' => $gateCheck,
						),
						'ip' => $ip,
					));
				}
			} catch (\Throwable $e) {
			}

			$this->maybe_sample_gate_parity_check($io_lab_id, $gateCheck);
			
			if ($block_action) {
				$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>ACCESS DENIED:</strong> ".$gateCheck['reason']."</div>");
			} else {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>BILLING REQUIRED:</strong> ".$gateCheck['reason']."</div>");
			}
		} else if ($gateCheck['bypass']) {
			// Show info that this was bypassed
			$this->session->set_flashdata('bypass_info', "<div class='alert alert-info alert-dismissable'><i class='fa fa-unlock'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>Admin Bypass Active:</strong> ".$gateCheck['reason']."</div>");
		}
		
		return $gateCheck;
	}

	private function maybe_sample_gate_parity_check($io_lab_id, $decision = null)
	{
		try {
			$io_lab_id = (int)$io_lab_id;
			if ($io_lab_id <= 0) {
				return;
			}
			$allowed = null;
			if (is_array($decision) && array_key_exists('allowed', $decision)) {
				$allowed = (bool)$decision['allowed'];
			}
			if ($allowed === true) {
				if (rand(1, 100) > 2) {
					return;
				}
			}

			$charge = $this->laboratory_model->get_sonography_charge_by_lab($io_lab_id);
			$charge_id = ($charge && isset($charge->charge_id)) ? (int)$charge->charge_id : 0;
			if ($charge_id <= 0) {
				return;
			}

			$this->load->model('app/unified_worklist_model', 'unified_worklist');
			$this->load->model('app/service_gate_model', 'service_gate');

			$item_ref = 'sono_charge_id:' . (int)$charge_id;
			$row = $this->unified_worklist->get_item_by_ref($item_ref);
			if (!$row) {
				return;
			}

			$iop_id = isset($row['iop_id']) ? $row['iop_id'] : null;
			$patient_no = isset($row['patient_no']) ? $row['patient_no'] : null;
			$backend = $this->service_gate->check_service('SONOGRAPHY', (string)(int)$charge_id, $iop_id, $patient_no);

			$sql_allowed = isset($row['can_proceed']) ? ((int)$row['can_proceed'] === 1 || $row['can_proceed'] === true) : false;
			$backend_allowed = isset($backend['allowed']) ? (bool)$backend['allowed'] : false;
			$sql_reason = $sql_allowed ? 'ALLOWED' : (isset($row['blocked_reason']) ? (string)$row['blocked_reason'] : null);
			$backend_reason = $backend_allowed ? 'ALLOWED' : (isset($backend['blocked_reason']) ? (string)$backend['blocked_reason'] : null);
			$mismatch = ($sql_allowed !== $backend_allowed) || ($sql_reason !== $backend_reason);
			if (!$mismatch) {
				return;
			}

			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$userId = (string)$this->session->userdata('user_id');
			if (isset($this->service_gate_audit) && method_exists($this->service_gate_audit, 'log_event')) {
				$this->service_gate_audit->log_event(array(
					'event_code' => 'GATE_PARITY_MISMATCH',
					'module' => 'SONOGRAPHY',
					'item_ref' => $item_ref,
					'user_id' => $userId !== '' ? (int)$userId : null,
					'action' => 'sonography.sample',
					'blocked_reason' => 'MISMATCH',
					'allowed' => null,
					'gate_version' => 'v1',
					'reason' => 'MISMATCH',
					'payload' => array(
						'io_lab_id' => (int)$io_lab_id,
						'charge_id' => (int)$charge_id,
						'gate_version' => 'v1',
						'sql' => array('can_proceed' => $sql_allowed, 'blocked_reason' => $sql_reason),
						'backend' => array('allowed' => $backend_allowed, 'blocked_reason' => $backend_reason, 'status' => isset($backend['status']) ? $backend['status'] : null),
					),
					'ip' => $ip,
				));
			}
		} catch (\Throwable $e) {
		}
	}

	private function audit_sonography_gate_decision($io_lab_id, $action, $gateCheck, $event_code)
	{
		try {
			$io_lab_id = (int)$io_lab_id;
			$action = trim((string)$action);
			$event_code = trim((string)$event_code);
			if ($io_lab_id <= 0 || $event_code === '') {
				return;
			}
			if (!isset($this->service_gate_audit) || !method_exists($this->service_gate_audit, 'log_event')) {
				return;
			}
			$userId = (string)$this->session->userdata('user_id');
			$ip = isset($_SERVER['REMOTE_ADDR']) ? (string)$_SERVER['REMOTE_ADDR'] : '';
			$charge = $this->laboratory_model->get_sonography_charge_by_lab($io_lab_id);
			$itemRef = null;
			$fallbackRef = true;
			$charge_id = null;
			if ($charge && isset($charge->charge_id) && (int)$charge->charge_id > 0) {
				$charge_id = (int)$charge->charge_id;
				$itemRef = 'sono_charge_id:' . (int)$charge_id;
				$fallbackRef = false;
			} else {
				$itemRef = 'sono_charge_id:0';
			}
			$blockedReason = isset($gateCheck['blocked_reason']) ? trim((string)$gateCheck['blocked_reason']) : '';
			if ($blockedReason === '') {
				$blockedReason = isset($gateCheck['allowed']) && $gateCheck['allowed'] ? 'ALLOWED' : ($fallbackRef ? 'NO_SSOT' : 'PAYMENT_PENDING');
			}
			$this->service_gate_audit->log_event(array(
				'event_code' => $event_code,
				'module' => 'SONOGRAPHY',
				'item_ref' => $itemRef,
				'user_id' => $userId !== '' ? (int)$userId : null,
				'action' => $action !== '' ? $action : null,
				'blocked_reason' => $blockedReason,
				'allowed' => isset($gateCheck['allowed']) ? ((int)(bool)$gateCheck['allowed']) : null,
				'gate_version' => 'v1',
				'reason' => isset($gateCheck['reason']) ? (string)$gateCheck['reason'] : null,
				'payload' => array(
					'io_lab_id' => (int)$io_lab_id,
					'charge_id' => $charge_id,
					'fallback_ref' => $fallbackRef ? 1 : 0,
					'gate_version' => 'v1',
					'allowed' => isset($gateCheck['allowed']) ? (bool)$gateCheck['allowed'] : null,
					'bypass' => !empty($gateCheck['bypass']) ? 1 : 0,
					'gate' => $gateCheck,
				),
				'ip' => $ip,
			));
		} catch (\Throwable $e) {
		}
	}
	
	/**
	 * Check if current user is admin
	 */
	private function is_admin_user(){
		return (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
	}

	private function require_sonography_access()
	{
		$userRole = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
		$module = $this->current_user_module_key();
		$roleKey = function_exists('get_role_key') ? (string)get_role_key() : $module;
		$isAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		$isSono = ($roleKey === 'sonographer' || $module === 'sonography' || $module === 'sonographer' || $module === 'sonography module' || $module === 'sonography_module');
		$this->auth_debug_log('REQUIRE_ACCESS_ENTER', array('is_admin' => $isAdmin ? 1 : 0, 'is_sono' => $isSono ? 1 : 0));

		$this->session->set_userdata('page_name', 'access_sonography_module');
		$page_id = $this->general_model->getPageID();
		if ($page_id && isset($page_id->page_id)) {
			if (General::has_rights_to_access($page_id->page_id, $userRole->user_role) == FALSE) {
				if ($isSono || $isAdmin) {
					$this->auth_debug_log('ALLOW_BYPASS_PAGE_RIGHTS', array('page_id' => (string)$page_id->page_id));
					return;
				}
				$this->auth_debug_log('DENY_PAGE_RIGHTS', array('page_id' => (string)$page_id->page_id, 'role_checked' => (string)$userRole->user_role));
				redirect(base_url() . 'access_denied');
				exit;
			}
			$this->auth_debug_log('ALLOW_PAGE_RIGHTS', array('page_id' => (string)$page_id->page_id, 'role_checked' => (string)$userRole->user_role));
			return;
		}

		if (!(isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography'])) {
			$this->auth_debug_log('DENY_HASACCESS_FALSE');
			if ($isSono || $isAdmin) {
				$this->auth_debug_log('ALLOW_FALLBACK_ROLEKEY');
				return;
			}
			redirect(base_url() . 'access_denied');
			exit;
		}
	}

	private function require_billing_pharmacy_access(){
		$isBilling = (isset($this->data['hasAccesstoBilling']) && $this->data['hasAccesstoBilling']);
		$isPharmacy = (isset($this->data['hasAccesstoPharmacy']) && $this->data['hasAccesstoPharmacy']);
		$isAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		if (!$isBilling && !$isPharmacy && !$isAdmin) {
			redirect(base_url() . 'access_denied');
			exit;
		}
	}

	private function require_sonography_or_billing_access(){
		$isAdmin = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		$isSono = (isset($this->data['hasAccesstoSonography']) && $this->data['hasAccesstoSonography']) || $this->current_user_is_sonography_staff();
		$isBilling = (isset($this->data['hasAccesstoBilling']) && $this->data['hasAccesstoBilling']);
		$isPharmacy = (isset($this->data['hasAccesstoPharmacy']) && $this->data['hasAccesstoPharmacy']);
		if (!$isAdmin && !$isSono && !$isBilling && !$isPharmacy) {
			redirect(base_url() . 'access_denied');
			exit;
		}
	}

	public function index($offset = 0)
	{
		$this->require_sonography_access();
		redirect('app/sonography/imaging_queue/sonography');
		return;

		$this->session->set_userdata(array(
			'tab' => 'sonography',
			'module' => 'sonography',
			'subtab' => '',
			'submodule' => ''
		));

		$uri_segment = 4;
		$offset = $this->uri->segment($uri_segment);
		$groupId = 18;
		$this->data['page_title'] = 'Sonography (Ultrasound)';
		$this->data['module_base'] = 'sonography';
		$filter = strtolower(trim((string)$this->input->get('filter')));
		$search = trim((string)$this->input->get('search'));
		if ($filter !== 'today' && $filter !== 'overdue') {
			$filter = '';
		}

		$laboratory_requests = $this->laboratory_model->pending_sonography_requests($groupId, $this->limit, $offset, $filter, $search);
		$total_rows = $this->laboratory_model->count_pending_sonography_requests($groupId, $filter, $search);

		$config['base_url'] = base_url() . 'app/sonography/index/';
		$config['total_rows'] = $total_rows;
		$config['per_page'] = $this->limit;
		$config['uri_segment'] = $uri_segment;
		$qs = '';
		if ($filter !== '' || $search !== '') {
			$parts = array();
			if ($filter !== '') { $parts[] = 'filter=' . urlencode($filter); }
			if ($search !== '') { $parts[] = 'search=' . urlencode($search); }
			$qs = '?' . implode('&', $parts);
			$config['suffix'] = $qs;
			$config['first_url'] = $config['base_url'] . '0' . $qs;
		}
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Urgency', 'IOP', 'Patient ID', 'Patient Name', 'Age', 'Payment', 'Requested', 'Action');
		foreach ($laboratory_requests as $r) {
			$urg = isset($r->urgency_rank) ? (int)$r->urgency_rank : 0;
			$urgLabel = '';
			if ($urg >= 3) {
				$urgLabel = '<span class="label label-danger">STAT</span>';
			} else if ($urg === 2) {
				$urgLabel = '<span class="label label-warning">Urgent</span>';
			} else {
				$urgLabel = '<span class="label label-default">Routine</span>';
			}

			$payBadge = '<span class="label label-default">UNBILLED</span>';
			$ssot_total = isset($r->ssot_total) ? (int)$r->ssot_total : 0;
			$ssot_missing = isset($r->ssot_missing) ? (int)$r->ssot_missing : 0;
			$ssot_partial = isset($r->ssot_partial) ? (int)$r->ssot_partial : 0;
			$ssot_zero_net = isset($r->ssot_zero_net) ? (int)$r->ssot_zero_net : 0;
			$ssot_auth_blocked = isset($r->ssot_auth_blocked) ? (int)$r->ssot_auth_blocked : 0;
			$ssot_uncleared = isset($r->ssot_uncleared) ? (int)$r->ssot_uncleared : 0;
			$ssot_cleared = isset($r->ssot_cleared) ? (int)$r->ssot_cleared : 0;
			$ssot_nhis = isset($r->ssot_nhis) ? (int)$r->ssot_nhis : 0;
			$ssot_paid = isset($r->ssot_paid) ? (int)$r->ssot_paid : 0;
			$ssot_covered = isset($r->ssot_covered) ? (int)$r->ssot_covered : 0;
			if ($ssot_total > 0) {
				if ($ssot_missing > 0) {
					$payBadge = '<span class="label label-default">UNBILLED</span>';
				} else if ($ssot_zero_net > 0) {
					$payBadge = '<span class="label label-danger">PRICE ERROR</span>';
				} else if ($ssot_auth_blocked > 0) {
					$payBadge = '<span class="label label-danger">AUTH REQUIRED</span>';
				} else if ($ssot_partial > 0) {
					$payBadge = '<span class="label label-warning">PARTIAL</span>';
				} else if ($ssot_uncleared > 0) {
					$payBadge = '<span class="label label-warning">PAYMENT PENDING</span>';
				} else if ($ssot_cleared >= $ssot_total) {
					if ($ssot_nhis > 0 && $ssot_paid === 0 && $ssot_covered === 0) {
						$payBadge = '<span class="label label-success">NHIS</span>';
					} else if ($ssot_covered > 0 && $ssot_paid === 0 && $ssot_nhis === 0) {
						$payBadge = '<span class="label label-success">COVERED</span>';
					} else {
						$payBadge = '<span class="label label-success">PAID</span>';
					}
				}
			}
			$reqAt = '';
			if (isset($r->requested_at) && trim((string)$r->requested_at) !== '' && (string)$r->requested_at !== '0000-00-00 00:00:00') {
				$reqAt = date('Y-m-d H:i', strtotime((string)$r->requested_at));
			} else if (isset($r->dDate) && trim((string)$r->dDate) !== '') {
				$reqAt = (string)$r->dDate;
			}
			$this->table->add_row(
				$urgLabel,
				anchor('app/sonography/request/' . $r->iop_id, $r->iop_id),
				$r->patient_no,
				$r->patient_name,
				$this->birth_day_age($r->birthday),
				$payBadge,
				$reqAt,
				''
			);
		}
		$nav = "<div style='margin-bottom:10px'>";
		$nav .= "<a class='btn btn-default' href='" . base_url() . "app/sonography/index'><i class='fa fa-refresh'></i> All</a> ";
		$nav .= "<a class='btn btn-default' href='" . base_url() . "app/sonography/index?filter=today'><i class='fa fa-calendar'></i> Today</a> ";
		$nav .= "<a class='btn btn-default' href='" . base_url() . "app/sonography/index?filter=overdue'><i class='fa fa-clock-o'></i> Overdue</a> ";
		$nav .= "<a class='btn btn-success' href='" . base_url() . "app/sonography/completed'><i class='fa fa-history'></i> Completed Scans</a> ";
		$nav .= "<a class='btn btn-primary' href='" . base_url() . "app/sonography/billing_map'><i class='fa fa-cog'></i> Billing Map</a>";
		$nav .= "</div>";
		$nav .= "<form method='get' action='" . base_url() . "app/sonography/index' style='margin-bottom:10px'>";
		if ($filter !== '') {
			$nav .= "<input type='hidden' name='filter' value='" . htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') . "'>";
		}
		$nav .= "<div class='input-group' style='max-width:360px'>";
		$nav .= "<input type='text' name='search' class='form-control input-sm' placeholder='Search IOP / Patient' value='" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "'>";
		$nav .= "<span class='input-group-btn'><button class='btn btn-sm btn-default' type='submit'><i class='fa fa-search'></i></button></span>";
		$nav .= "</div></form>";
		$this->data['message'] = $nav . (string)$this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->load->view('app/laboratory/index', $this->data);
	}

	/**
	 * Imaging queue for X-Ray, ECG, and Sonography
	 * Routes: /app/sonography/imaging_queue/xray, /app/sonography/imaging_queue/ecg, /app/sonography/imaging_queue/sonography
	 */
	public function imaging_queue($type = 'sonography', $offset = 0)
	{
		$type = strtolower(trim((string)$type));
		if (in_array($type, array('xray', 'ecg', 'ct'), true)) {
			$qs = (isset($_SERVER['QUERY_STRING']) && (string)$_SERVER['QUERY_STRING'] !== '')
			? ('?' . (string)$_SERVER['QUERY_STRING'])
			: '';
			redirect(base_url() . 'app/radiology/' . $type . '_queue' . $qs);
			return;
		}

		$this->require_sonography_access();

		$type = strtolower(trim((string)$type));
		$validTypes = array('sonography', 'xray', 'ecg');
		if (!in_array($type, $validTypes)) {
			$type = 'sonography';
		}

		$this->session->set_userdata(array(
			'tab' => 'sonography',
			'module' => 'sonography',
			'subtab' => 'imaging_' . $type,
			'submodule' => ''
		));

		$uri_segment = 5;
		$offset = (int)$this->uri->segment($uri_segment);

		$imagingTypes = $this->laboratory_model->get_imaging_types();
		$groupId = isset($imagingTypes[$type]['id']) ? (int)$imagingTypes[$type]['id'] : 18;
		$radCategory = isset($imagingTypes[$type]['rad_category']) ? (string)$imagingTypes[$type]['rad_category'] : '';

		$typeLabels = array(
			'sonography' => 'Sonography (Ultrasound)',
			'xray' => 'X-Ray Imaging',
			'ecg' => 'ECG'
		);
		$this->data['page_title'] = isset($typeLabels[$type]) ? $typeLabels[$type] : 'Imaging';
		$this->data['module_base'] = 'sonography';
		$this->data['imaging_type'] = $type;

		$filter = strtolower(trim((string)$this->input->get('filter')));
		$search = trim((string)$this->input->get('search'));
		if ($filter !== 'today' && $filter !== 'overdue') {
			$filter = '';
		}

		$laboratory_requests = array();
		$total_rows = 0;
		if ($type === 'sonography') {
			$laboratory_requests = $this->laboratory_model->pending_sonography_requests($groupId, $this->limit, $offset, $filter, $search);
			$total_rows = $this->laboratory_model->count_pending_sonography_requests($groupId, $filter, $search);
		} else {
			$laboratory_requests = $this->laboratory_model->pending_radiology_requests_grouped($groupId, $radCategory, $this->limit, $offset, $filter, $search);
			$total_rows = $this->laboratory_model->count_pending_radiology_requests_grouped($groupId, $radCategory, $filter, $search);
		}

		$config['base_url'] = base_url() . 'app/sonography/imaging_queue/' . $type . '/';
		$config['total_rows'] = $total_rows;
		$config['per_page'] = $this->limit;
		$config['uri_segment'] = $uri_segment;
		$qs = '';
		if ($filter !== '' || $search !== '') {
			$parts = array();
			if ($filter !== '') { $parts[] = 'filter=' . urlencode($filter); }
			if ($search !== '') { $parts[] = 'search=' . urlencode($search); }
			$qs = '?' . implode('&', $parts);
			$config['suffix'] = $qs;
			$config['first_url'] = $config['base_url'] . '0' . $qs;
		}
		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Urgency', 'IOP', 'Patient ID', 'Patient Name', 'Age', 'Payment', 'Requested', 'Action');
		foreach ($laboratory_requests as $r) {
			$urg = isset($r->urgency_rank) ? (int)$r->urgency_rank : 0;
			$urgLabel = '';
			if ($urg >= 3) {
				$urgLabel = '<span class="label label-danger">STAT</span>';
			} else if ($urg === 2) {
				$urgLabel = '<span class="label label-warning">Urgent</span>';
			} else {
				$urgLabel = '<span class="label label-default">Routine</span>';
			}

			$payBadge = '<span class="label label-default">N/A</span>';
			if ((int)$groupId === 18) {
				$payBadge = '<span class="label label-default">UNBILLED</span>';
				$ssot_total = isset($r->ssot_total) ? (int)$r->ssot_total : 0;
				$ssot_missing = isset($r->ssot_missing) ? (int)$r->ssot_missing : 0;
				$ssot_partial = isset($r->ssot_partial) ? (int)$r->ssot_partial : 0;
				$ssot_zero_net = isset($r->ssot_zero_net) ? (int)$r->ssot_zero_net : 0;
				$ssot_auth_blocked = isset($r->ssot_auth_blocked) ? (int)$r->ssot_auth_blocked : 0;
				$ssot_uncleared = isset($r->ssot_uncleared) ? (int)$r->ssot_uncleared : 0;
				$ssot_cleared = isset($r->ssot_cleared) ? (int)$r->ssot_cleared : 0;
				$ssot_nhis = isset($r->ssot_nhis) ? (int)$r->ssot_nhis : 0;
				$ssot_paid = isset($r->ssot_paid) ? (int)$r->ssot_paid : 0;
				$ssot_covered = isset($r->ssot_covered) ? (int)$r->ssot_covered : 0;
				if ($ssot_total > 0) {
					if ($ssot_missing > 0) {
						$payBadge = '<span class="label label-default">UNBILLED</span>';
					} else if ($ssot_zero_net > 0) {
						$payBadge = '<span class="label label-danger">PRICE ERROR</span>';
					} else if ($ssot_auth_blocked > 0) {
						$payBadge = '<span class="label label-danger">AUTH REQUIRED</span>';
					} else if ($ssot_partial > 0) {
						$payBadge = '<span class="label label-warning">PARTIAL</span>';
					} else if ($ssot_uncleared > 0) {
						$payBadge = '<span class="label label-warning">PAYMENT PENDING</span>';
					} else if ($ssot_cleared >= $ssot_total) {
						if ($ssot_nhis > 0 && $ssot_paid === 0 && $ssot_covered === 0) {
							$payBadge = '<span class="label label-success">NHIS</span>';
						} else if ($ssot_covered > 0 && $ssot_paid === 0 && $ssot_nhis === 0) {
							$payBadge = '<span class="label label-success">COVERED</span>';
						} else {
							$payBadge = '<span class="label label-success">PAID</span>';
						}
					}
				}
			}
			$reqAt = '';
			if (isset($r->requested_at) && trim((string)$r->requested_at) !== '' && (string)$r->requested_at !== '0000-00-00 00:00:00') {
				$reqAt = date('Y-m-d H:i', strtotime((string)$r->requested_at));
			} else if (isset($r->dDate) && trim((string)$r->dDate) !== '') {
				$reqAt = (string)$r->dDate;
			}
			$linkUrl = ($type === 'sonography')
				? ('app/sonography/request/' . $r->iop_id)
				: ('app/laboratory/imaging_request/' . $r->iop_id . '/' . $type);
			$this->table->add_row(
				$urgLabel,
				anchor($linkUrl, $r->iop_id),
				$r->patient_no,
				$r->patient_name,
				$this->birth_day_age($r->birthday),
				$payBadge,
				$reqAt,
				''
			);
		}

		$nav = "<div style='margin-bottom:10px'>";
		$nav .= "<a class='btn btn-default" . ($type === 'sonography' ? ' btn-primary' : '') . "' href='" . base_url() . "app/sonography/imaging_queue/sonography'><i class='fa fa-heartbeat'></i> Sonography</a> ";
		$nav .= "<a class='btn btn-default" . ($type === 'xray' ? ' btn-primary' : '') . "' href='" . base_url() . "app/sonography/imaging_queue/xray'><i class='fa fa-bolt'></i> X-Ray</a> ";
		$nav .= "<a class='btn btn-default" . ($type === 'ecg' ? ' btn-primary' : '') . "' href='" . base_url() . "app/sonography/imaging_queue/ecg'><i class='fa fa-area-chart'></i> ECG</a> ";
		$nav .= "</div>";
		$nav .= "<form method='get' action='" . base_url() . "app/sonography/imaging_queue/" . $type . "' style='margin-bottom:10px'>";
		if ($filter !== '') {
			$nav .= "<input type='hidden' name='filter' value='" . htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') . "'>";
		}
		$nav .= "<div class='input-group' style='max-width:360px'>";
		$nav .= "<input type='text' name='search' class='form-control input-sm' placeholder='Search IOP / Patient' value='" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "'>";
		$nav .= "<span class='input-group-btn'><button class='btn btn-sm btn-default' type='submit'><i class='fa fa-search'></i></button></span>";
		$nav .= "</div></form>";
		$this->data['message'] = $nav . (string)$this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->load->view('app/laboratory/index', $this->data);
	}

	public function request($offset = 0)
	{
		$this->require_sonography_or_billing_access();

		$uri_segment = 5;
		$offset = $this->uri->segment($uri_segment);
		// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
		$iop_id = $this->segment_decoded(4);
		$groupId = 18;
		$this->data['page_title'] = 'Sonography Requests';
		$this->data['module_base'] = 'sonography';
		$labs = $this->laboratory_model->get_sonography_requests($groupId, $iop_id, true);
		$ioIds = array();
		foreach ($labs as $l) {
			$ioIds[] = $l->io_lab_id;
		}
		$this->data['workflow_map'] = $this->laboratory_model->get_workflow_map($ioIds);

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Sonography', 'Urgency', 'Patient ID', 'Patient Name', 'Age', 'Status', 'Billing', 'Clinical Question', 'Findings', 'Results', 'Request Date', 'Action');
		$ioIds = array();
		foreach ($labs as $l) {
			$ioIds[] = $l->io_lab_id;
		}
		$chargeMap = $this->laboratory_model->get_ipd_sonography_charge_map($ioIds);
		$canPostCharges = (isset($this->data['hasAccesstoBilling']) && $this->data['hasAccesstoBilling']) || (isset($this->data['hasAccesstoPharmacy']) && $this->data['hasAccesstoPharmacy']) || (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']);
		$canCancel = (isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin']) || $this->current_user_is_sonography_staff();
		foreach ($labs as $laboratory) {
			$labsName = '';
			if (isset($laboratory->sono_meta_id) && (int)$laboratory->sono_meta_id > 0 && isset($laboratory->sono_item_name) && trim((string)$laboratory->sono_item_name) !== '') {
				$labsName = (string)$laboratory->sono_item_name;
			} else if (isset($laboratory->particular_name) && trim((string)$laboratory->particular_name) !== '') {
				$labsName = (string)$laboratory->particular_name;
			} else {
				$labsName = (string)$laboratory->laboratory_text;
			}
			$wf = isset($this->data['workflow_map'][(string)$laboratory->io_lab_id]) ? $this->data['workflow_map'][(string)$laboratory->io_lab_id] : null;
			$status = $wf && isset($wf->status) ? $wf->status : ((trim((string)$laboratory->result) === '') ? 'REQUESTED' : 'REPORTED');
			$st = strtoupper(trim((string)$status));
			if ($st === 'REQUESTED') {
				$status = '<span class="label label-info">Requested</span>';
			} else if ($st === 'IN_PROGRESS') {
				$status = '<span class="label label-warning">In Progress</span>';
			} else if ($st === 'CANCELLED') {
				$status = '<span class="label label-danger">Cancelled</span>';
			} else if ($st === 'REPORTED_TEXT' || $st === 'REPORTED_PDF' || $st === 'REPORTED_BOTH' || $st === 'REPORTED') {
				$status = '<span class="label label-success">Reported</span>';
			} else {
				$status = '<span class="label label-default">' . $st . '</span>';
			}
			$billingBadge = '';
			$charge = isset($chargeMap[(string)$laboratory->io_lab_id]) ? $chargeMap[(string)$laboratory->io_lab_id] : null;
			if ($charge) {
				$cst = isset($charge->status) ? strtoupper(trim((string)$charge->status)) : '';
				$inv = isset($charge->invoice_no) ? trim((string)$charge->invoice_no) : '';
				if ($cst === 'PAID') {
					$billingBadge = '<span class="label label-success">Paid</span>';
				} else if ($inv !== '' || $cst === 'INVOICED') {
					$billingBadge = '<span class="label label-primary">Billed</span>';
				} else {
					$billingBadge = '<span class="label label-primary">Posted</span>';
				}
			} else {
				$billingBadge = '<span class="label label-danger">Not Posted</span>';
			}
			$urgTxt = '';
			if (isset($laboratory->urgency) && trim((string)$laboratory->urgency) !== '') {
				$u = strtoupper(trim((string)$laboratory->urgency));
				if ($u === 'STAT') {
					$urgTxt = '<span class="label label-danger">STAT</span>';
				} else if ($u === 'URGENT') {
					$urgTxt = '<span class="label label-warning">Urgent</span>';
				} else {
					$urgTxt = '<span class="label label-default">' . htmlspecialchars($u, ENT_QUOTES, 'UTF-8') . '</span>';
				}
			}
			$cancelLink = '';
			$wfRow = $wf;
			$wfSt = $wfRow && isset($wfRow->status) ? strtoupper(trim((string)$wfRow->status)) : '';
			if ($canCancel && $wfSt !== 'CANCELLED' && $st !== 'REPORTED_TEXT' && $st !== 'REPORTED_PDF' && $st !== 'REPORTED_BOTH' && $st !== 'REPORTED') {
				$cancelLink = anchor('app/sonography/cancel/' . (int)$laboratory->io_lab_id, 'Cancel');
			}
			$action = '';
			if ($canPostCharges) {
				$action = anchor('app/sonography/charge/' . (int)$laboratory->io_lab_id, 'Post/Update Charge');
			}
			if ($cancelLink !== '') {
				$action = ($action !== '' ? ($action . ' | ') : '') . $cancelLink;
			}
			$reqAt = isset($laboratory->requested_at) && trim((string)$laboratory->requested_at) !== '' && (string)$laboratory->requested_at !== '0000-00-00 00:00:00' ? date('Y-m-d H:i', strtotime((string)$laboratory->requested_at)) : (string)$laboratory->dDate;
			$this->table->add_row(
				anchor('app/sonography/results/' . $laboratory->io_lab_id . '/' . $laboratory->iop_id, $labsName),
				$urgTxt,
				$laboratory->patient_no,
				$laboratory->patient_name,
				$this->birth_day_age($laboratory->birthday),
				$status,
				$billingBadge,
				(isset($laboratory->clinical_question) ? (string)$laboratory->clinical_question : ''),
				$laboratory->findings,
				$laboratory->result,
				$reqAt,
				$action
			);
		}
		$nav = "<div style='margin-bottom:10px'><a class='btn btn-default' href='" . base_url() . "app/sonography/index'><i class='fa fa-arrow-left'></i> Back</a> <a class='btn btn-primary' href='" . base_url() . "app/sonography/billing_map'><i class='fa fa-cog'></i> Billing Map</a></div>";
		$this->data['message'] = $nav . (string)$this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->data['pagination'] = '';
		$this->load->view('app/laboratory/request', $this->data);
	}

	public function charge($io_lab_id)
	{
		$this->require_billing_pharmacy_access();

		$this->laboratory_model->install_imaging_tables();
		$io_lab_id = (int)$io_lab_id;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			$this->auth_debug_log('SAVE_DENY_BAD_ROW', array('io_lab_id' => (int)$io_lab_id));
			redirect(base_url() . 'access_denied');
			return;
		}
		$meta = $this->laboratory_model->get_sonography_request_meta($io_lab_id);
		$enc = $meta && isset($meta->encounter_type) ? strtoupper(trim((string)$meta->encounter_type)) : '';
		$scanId = $meta && isset($meta->scan_item_id) ? (int)$meta->scan_item_id : 0;
		$clinicalQ = $meta && isset($meta->clinical_question) ? (string)$meta->clinical_question : '';
		$patientNo = $meta && isset($meta->patient_no) ? (string)$meta->patient_no : (isset($row->patient_no) ? (string)$row->patient_no : '');
		$iopId = isset($row->iop_id) ? (string)$row->iop_id : '';

		$existing = $this->laboratory_model->get_sonography_charge_by_lab($io_lab_id);
		if ($existing && isset($existing->invoice_no) && trim((string)$existing->invoice_no) !== '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>This sonography charge is already invoiced and cannot be edited.</div>");
			redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
			return;
		}
		$st = $existing && isset($existing->status) ? strtoupper(trim((string)$existing->status)) : '';
		if ($existing && $st !== '' && $st !== 'PENDING') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>This sonography charge cannot be edited (status: " . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . ").</div>");
			redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
			return;
		}

		$rate = $existing && isset($existing->rate_amount) ? (float)$existing->rate_amount : 0.0;
		$qty = $existing && isset($existing->quantity) ? (float)$existing->quantity : 1.0;
		$itemName = $existing && isset($existing->item_name) ? (string)$existing->item_name : '';

		if ($rate <= 0 && $scanId > 0 && $this->laboratory_model->table_exists('sonography_items')) {
			$this->db->select('bill_particular_id');
			$this->db->where(array('item_id' => $scanId, 'InActive' => 0));
			$this->db->limit(1);
			$si = $this->db->get('sonography_items')->row();
			$bpId = ($si && isset($si->bill_particular_id)) ? (int)$si->bill_particular_id : 0;
			if ($bpId > 0 && $this->laboratory_model->table_exists('bill_particular')) {
				$this->db->select('charge_amount, particular_name');
				$this->db->where(array('particular_id' => $bpId, 'InActive' => 0));
				$this->db->limit(1);
				$bp = $this->db->get('bill_particular')->row();
				if ($bp && isset($bp->charge_amount)) {
					$rate = (float)$bp->charge_amount;
				}
				if ($itemName === '' && $bp && isset($bp->particular_name)) {
					$itemName = (string)$bp->particular_name;
				}
			}
		}
		if ($itemName === '') {
			$itemName = ($clinicalQ !== '') ? ('Custom Sonography - '.trim($clinicalQ)) : 'Custom Sonography';
		}
		if ($qty <= 0) {
			$qty = 1.0;
		}

		$html = "<form method='post' action='" . base_url() . "app/sonography/save_charge'>";
		$html .= "<input type='hidden' name='io_lab_id' value='" . (int)$io_lab_id . "'>";
		$html .= "<input type='hidden' name='iop_id' value='" . htmlspecialchars((string)$iopId, ENT_QUOTES, 'UTF-8') . "'>";
		$html .= "<input type='hidden' name='patient_no' value='" . htmlspecialchars((string)$patientNo, ENT_QUOTES, 'UTF-8') . "'>";
		$html .= "<input type='hidden' name='encounter_type' value='" . htmlspecialchars((string)$enc, ENT_QUOTES, 'UTF-8') . "'>";
		$html .= "<input type='hidden' name='scan_item_id' value='" . (int)$scanId . "'>";
		$html .= "<input type='hidden' name='clinical_question' value='" . htmlspecialchars((string)$clinicalQ, ENT_QUOTES, 'UTF-8') . "'>";
		$html .= "<div class='box-body'>";
		$html .= "<div class='form-group'><label>Item</label><div>" . htmlspecialchars((string)$itemName, ENT_QUOTES, 'UTF-8') . "</div></div>";
		if ($clinicalQ !== '') {
			$html .= "<div class='form-group'><label>Clinical Question</label><div>" . htmlspecialchars((string)$clinicalQ, ENT_QUOTES, 'UTF-8') . "</div></div>";
		}
		$html .= "<div class='form-group'><label>Rate</label><input class='form-control' type='number' step='0.01' min='0' name='rate_amount' value='" . htmlspecialchars((string)$rate, ENT_QUOTES, 'UTF-8') . "' required></div>";
		$html .= "<div class='form-group'><label>Quantity</label><input class='form-control' type='number' step='0.01' min='0.01' name='quantity' value='" . htmlspecialchars((string)$qty, ENT_QUOTES, 'UTF-8') . "' required></div>";
		$html .= "</div>";
		$html .= "<div class='box-footer clearfix'><a class='btn btn-default' href='" . base_url() . "app/sonography/request/" . htmlspecialchars((string)$iopId, ENT_QUOTES, 'UTF-8') . "'><i class='fa fa-arrow-left'></i> Back</a> <button class='btn btn-primary' name='btnSubmit' id='btnSubmit' type='submit'><i class='fa fa-save'></i> Save Charge</button></div>";
		$html .= "</form>";

		$this->data['page_title'] = 'Post Sonography Charge';
		$this->data['module_base'] = 'sonography';
		$this->data['pagination'] = '';
		$this->data['message'] = (string)$this->session->flashdata('message');
		$this->data['table'] = $html;
		$this->load->view('app/laboratory/request', $this->data);
	}

	public function save_charge()
	{
		$this->require_billing_pharmacy_access();
		if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$iopId = (string)$this->input->post('iop_id');
		$patientNo = (string)$this->input->post('patient_no');
		$enc = (string)$this->input->post('encounter_type');
		$scanId = (int)$this->input->post('scan_item_id');
		$clinicalQ = (string)$this->input->post('clinical_question');
		$rate = (float)$this->input->post('rate_amount');
		$qty = (float)$this->input->post('quantity');
		if ($io_lab_id <= 0 || $iopId === '') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$this->laboratory_model->install_imaging_tables();
		$this->db->trans_begin();
		$this->laboratory_model->lock_sonography_request_for_update($io_lab_id);
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			$this->db->trans_rollback();
			redirect(base_url() . 'access_denied');
			return;
		}
		$existing = $this->laboratory_model->get_sonography_charge_by_lab($io_lab_id);
		if ($existing && isset($existing->invoice_no) && trim((string)$existing->invoice_no) !== '') {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>This sonography charge is already invoiced and cannot be edited.</div>");
			redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
			return;
		}
		$st = $existing && isset($existing->status) ? strtoupper(trim((string)$existing->status)) : '';
		if ($existing && $st !== '' && $st !== 'PENDING') {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>This sonography charge cannot be edited (status: " . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . ").</div>");
			redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
			return;
		}

		$isAdmin = $this->is_admin_user();
		if (!$isAdmin) {
			$derived = 0.0;
			$postedRate = $rate;
			if ($scanId > 0 && $this->laboratory_model->table_exists('sonography_items')) {
				$this->db->select('bill_particular_id, charge_amount');
				$this->db->where(array('item_id' => $scanId, 'InActive' => 0));
				$this->db->limit(1);
				$si = $this->db->get('sonography_items')->row();
				$bpId = ($si && isset($si->bill_particular_id)) ? (int)$si->bill_particular_id : 0;
				if ($bpId > 0 && $this->laboratory_model->table_exists('bill_particular')) {
					$this->db->select('charge_amount');
					$this->db->where(array('particular_id' => $bpId, 'InActive' => 0));
					$this->db->limit(1);
					$bp = $this->db->get('bill_particular')->row();
					$derived = ($bp && isset($bp->charge_amount)) ? (float)$bp->charge_amount : 0.0;
				} else {
					$derived = ($si && isset($si->charge_amount)) ? (float)$si->charge_amount : 0.0;
				}
			}
			if ($derived <= 0.0) {
				$this->db->trans_rollback();
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cannot post charge: no approved price found. Please contact an administrator to set the price.</div>");
				redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
				return;
			}
			if (abs(((float)$postedRate) - ((float)$derived)) > 0.009) {
				log_message('warning', 'SONO_PRICE_OVERRIDE_BLOCKED io_lab_id=' . (int)$io_lab_id . ' scan_item_id=' . (int)$scanId . ' user_id=' . (string)$this->session->userdata('user_id') . ' posted_rate=' . (float)$postedRate . ' approved_rate=' . (float)$derived);
			}
			$rate = $derived;
		}
		if ($rate < 0) {
			$rate = 0;
		}
		if ($qty <= 0) {
			$qty = 1;
		}
		$res = $this->laboratory_model->upsert_sonography_charge_from_request(
			$io_lab_id,
			$iopId,
			$patientNo !== '' ? $patientNo : null,
			$enc !== '' ? $enc : null,
			$scanId > 0 ? $scanId : null,
			$clinicalQ !== '' ? $clinicalQ : null,
			$rate,
			$qty,
			(string)$this->session->userdata('user_id')
		);
		if (!$res || !isset($res['ok']) || !$res['ok']) {
			$this->db->trans_rollback();
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to save sonography charge.</div>");
			redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
			return;
		}
		$this->db->trans_commit();
		General::logfile('Sonography', 'CHARGE', (string)$io_lab_id);
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Sonography charge posted.</div>");
		redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
	}

	public function billing_map()
	{
		$this->require_billing_pharmacy_access();
		if (!$this->is_admin_user()) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->laboratory_model->install_imaging_tables();
		if (!$this->laboratory_model->table_exists('sonography_items')) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->data['page_title'] = 'Sonography Billing Map';
		$this->data['module_base'] = 'sonography';
		$this->data['pagination'] = '';

		$this->db->select('item_id,item_name,bill_particular_id');
		$this->db->from('sonography_items');
		$this->db->where('InActive', 0);
		$this->db->order_by('item_name', 'ASC');
		$items = $this->db->get()->result();

		$this->db->select('particular_id,particular_name,charge_amount');
		$this->db->from('bill_particular');
		$this->db->where('InActive', 0);
		$this->db->order_by('particular_name', 'ASC');
		$parts = $this->db->get()->result();

		$partMap = array();
		foreach ($parts as $p) {
			$partMap[(string)$p->particular_id] = $p;
		}

		$html = "<form method='post' action='" . base_url() . "app/sonography/save_billing_map'>";
		$html .= "<div class='box-body table-responsive no-padding'>";
		$html .= "<table class='table table-hover table-striped'>";
		$html .= "<thead><tr><th>Scan Item</th><th>Bill Particular</th><th>Charge</th></tr></thead><tbody>";
		foreach ($items as $it) {
			$itemId = (int)$it->item_id;
			$selected = isset($it->bill_particular_id) ? (int)$it->bill_particular_id : 0;
			$charge = ($selected > 0 && isset($partMap[(string)$selected]) && isset($partMap[(string)$selected]->charge_amount)) ? $partMap[(string)$selected]->charge_amount : '';
			$html .= "<tr>";
			$html .= "<td>" . htmlspecialchars((string)$it->item_name, ENT_QUOTES, 'UTF-8') . "</td>";
			$html .= "<td><select class='form-control' name='map[" . $itemId . "]'>";
			$html .= "<option value='0'" . ($selected <= 0 ? " selected" : "") . ">-- Manual Pricing / None --</option>";
			foreach ($parts as $p) {
				$pid = (int)$p->particular_id;
				$sel = ($pid === $selected) ? " selected" : "";
				$label = (string)$p->particular_name;
				if (isset($p->charge_amount)) {
					$label .= " (" . $p->charge_amount . ")";
				}
				$html .= "<option value='" . $pid . "'" . $sel . ">" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</option>";
			}
			$html .= "</select></td>";
			$html .= "<td>" . htmlspecialchars((string)$charge, ENT_QUOTES, 'UTF-8') . "</td>";
			$html .= "</tr>";
		}
		$html .= "</tbody></table></div>";
		$html .= "<div class='box-footer clearfix'><a class='btn btn-default' href='" . base_url() . "app/sonography/index'><i class='fa fa-arrow-left'></i> Back</a> <button class='btn btn-primary' name='btnSubmit' id='btnSubmit' type='submit'><i class='fa fa-save'></i> Save</button></div>";
		$html .= "</form>";

		$this->data['message'] = (string)$this->session->flashdata('message');
		$this->data['table'] = $html;
		$this->load->view('app/laboratory/index', $this->data);
	}

	public function save_billing_map()
	{
		$this->require_billing_pharmacy_access();
		if (!$this->is_admin_user()) {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$this->laboratory_model->install_imaging_tables();
		if (!$this->laboratory_model->table_exists('sonography_items')) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$map = $this->input->post('map');
		if (!$map || !is_array($map)) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>No changes to save.</div>");
			redirect(base_url() . 'app/sonography/billing_map');
			return;
		}
		$this->db->trans_start();
		foreach ($map as $itemId => $pid) {
			$itemId = (int)$itemId;
			$pid = (int)$pid;
			if ($itemId <= 0) {
				continue;
			}
			$this->db->where('item_id', $itemId);
			$this->db->where('InActive', 0);
			$this->db->update('sonography_items', array('bill_particular_id' => ($pid > 0 ? $pid : null)));
		}
		$this->db->trans_complete();
		if ($this->db->trans_status() === false) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to save billing mappings.</div>");
			redirect(base_url() . 'app/sonography/billing_map');
			return;
		}
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Billing mappings saved.</div>");
		redirect(base_url() . 'app/sonography/billing_map');
	}

	public function results()
	{
		$this->require_sonography_access();
		$this->require_sonography_write_access();

		$io_lab_id = (int)$this->uri->segment(4);
		$this->laboratory_model->install_imaging_tables();
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$gateCheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'results');
		if (!$gateCheck['allowed'] && !has_role('admin')) {
			redirect(base_url() . 'app/sonography/request/' . (string)$row->iop_id, $this->data);
			return;
		}
		$this->laboratory_model->touch_in_progress_if_needed($io_lab_id, $this->session->userdata('user_id'));

		$this->data['lab'] = $this->uri->segment(4);
		// Use segment_decoded for automatic URL decoding (handles OP%20000002 -> OP 000002)
		$labPatient = $this->segment_decoded(5);
		if (!$labPatient && $row && isset($row->iop_id)) {
			$labPatient = (string)$row->iop_id;
		}
		$this->data['lab_patient'] = $labPatient;
		$meta = $this->laboratory_model->get_sonography_request_meta($io_lab_id);
		$labsName = '';
		if ($meta && isset($meta->scan_item_id) && (int)$meta->scan_item_id > 0 && $this->laboratory_model->table_exists('sonography_items')) {
			$this->db->select('item_name');
			$this->db->where(array('item_id' => (int)$meta->scan_item_id, 'InActive' => 0));
			$this->db->limit(1);
			$si = $this->db->get('sonography_items')->row();
			if ($si && isset($si->item_name) && trim((string)$si->item_name) !== '') {
				$labsName = (string)$si->item_name;
			}
		}
		if ($labsName === '' && isset($row->laboratory_text) && trim((string)$row->laboratory_text) !== '') {
			$labsName = (string)$row->laboratory_text;
		}
		$this->data['lab_request_name'] = ($labsName !== '') ? $labsName : 'Sonography';
		$this->data['lab_row'] = $row;
		$this->data['sonography_meta'] = $meta;
		$this->data['billing_status'] = $this->laboratory_model->get_sonography_billing_status($io_lab_id);
		$this->data['draft'] = $this->laboratory_model->get_sonography_report_draft($io_lab_id);
		$this->data['workflow'] = $this->laboratory_model->get_workflow_status($io_lab_id);
		$this->data['attachment_meta'] = $this->laboratory_model->get_latest_attachment_meta($io_lab_id);
		$this->data['is_read_only'] = false;
		$this->data['module_base'] = 'sonography';
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) {
			$this->laboratory_model->mark_delivered_if_needed($io_lab_id, $this->session->userdata('user_id'));
		}

		$this->load->view('app/laboratory/results', $this->data);
	}

	public function cancel($io_lab_id)
	{
		$this->require_sonography_access();
		$this->require_sonography_write_access();
		$io_lab_id = (int)$io_lab_id;
		$this->laboratory_model->install_imaging_tables();
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$wf = $this->laboratory_model->get_workflow_status($io_lab_id);
		$st = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		if ($st === 'CANCELLED') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Request already cancelled.</div>");
			redirect(base_url() . 'app/sonography/request/' . (string)$row->iop_id, $this->data);
			return;
		}
		$html = "<form method='post' action='" . base_url() . "app/sonography/save_cancel'>";
		$html .= "<input type='hidden' name='io_lab_id' value='" . (int)$io_lab_id . "'>";
		$html .= "<input type='hidden' name='iop_id' value='" . htmlspecialchars((string)$row->iop_id, ENT_QUOTES, 'UTF-8') . "'>";
		$html .= "<div class='box-body'>";
		$html .= "<div class='form-group'><label>Cancellation Reason <span style='color:#c00'>*</span></label><textarea class='form-control' name='reason' rows='3' required></textarea></div>";
		$html .= "</div>";
		$html .= "<div class='box-footer clearfix'><a class='btn btn-default' href='" . base_url() . "app/sonography/request/" . htmlspecialchars((string)$row->iop_id, ENT_QUOTES, 'UTF-8') . "'><i class='fa fa-arrow-left'></i> Back</a> <button class='btn btn-danger' name='btnSubmit' id='btnSubmit' type='submit' onclick=\"return confirm('Cancel this sonography request?')\"><i class='fa fa-times'></i> Cancel Request</button></div>";
		$html .= "</form>";

		$this->data['page_title'] = 'Cancel Sonography Request';
		$this->data['module_base'] = 'sonography';
		$this->data['pagination'] = '';
		$this->data['message'] = (string)$this->session->flashdata('message');
		$this->data['table'] = $html;
		$this->load->view('app/laboratory/request', $this->data);
	}

	public function save_cancel()
	{
		$this->require_sonography_access();
		$this->require_sonography_write_access();
		if (!isset($_SERVER['REQUEST_METHOD']) || strtoupper((string)$_SERVER['REQUEST_METHOD']) !== 'POST') {
			redirect(base_url() . 'access_denied');
			return;
		}
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$iopId = (string)$this->input->post('iop_id');
		$reason = (string)$this->input->post('reason');
		if ($io_lab_id <= 0 || trim($reason) === '') {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Cancellation reason is required.</div>");
			redirect(base_url() . 'app/sonography/cancel/' . $io_lab_id, $this->data);
			return;
		}
		$this->laboratory_model->install_imaging_tables();
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$ok = $this->laboratory_model->cancel_sonography_request($io_lab_id, $reason, (string)$this->session->userdata('user_id'));
		if (!$ok) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Failed to cancel request.</div>");
			redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
			return;
		}
		General::logfile('Sonography', 'CANCEL', (string)$io_lab_id);
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Request cancelled.</div>");
		redirect(base_url() . 'app/sonography/request/' . $iopId, $this->data);
	}

	public function upload_results($id)
	{
		$this->require_sonography_access();
		$this->require_sonography_write_access();

		$this->laboratory_model->install_imaging_tables();
		$io_lab_id = (int)$id;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$gateCheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'upload_results');
		if (!$gateCheck['allowed'] && !has_role('admin')) {
			redirect(base_url() . 'app/sonography/request/' . (string)$row->iop_id, $this->data);
			return;
		}
		$this->laboratory_model->touch_in_progress_if_needed($io_lab_id, $this->session->userdata('user_id'));
		$this->data['lab'] = $id;
		$this->data['title'] = 'Upload Results';
		$this->data['lab_row'] = $row;
		$this->data['attachment_meta'] = $this->laboratory_model->get_latest_attachment_meta($io_lab_id);
		$this->data['is_read_only'] = false;
		$this->data['module_base'] = 'sonography';
		$this->data['message'] = $this->session->flashdata('message');
		$this->load->view('app/laboratory/upload_results', $this->data);
	}

	public function upload_lab_result()
	{
		$this->auth_debug_log('UPLOAD_ENTER');
		$this->require_sonography_access();
		$this->require_sonography_write_access();

		$this->laboratory_model->install_imaging_tables();
		$io_lab_id = (int)$this->input->post('io_lab_id');
		if ($io_lab_id <= 0) {
			$io_lab_id = (int)$this->uri->segment(4);
		}
		if ($io_lab_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing request reference. Please reopen the upload screen.</div>");
			$this->auth_debug_log('UPLOAD_DENY_MISSING_IO_LAB_ID');
			redirect(base_url() . 'access_denied');
			return;
		}
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			$this->auth_debug_log('UPLOAD_DENY_BAD_ROW', array('io_lab_id' => (int)$io_lab_id));
			redirect(base_url() . 'access_denied');
			return;
		}
		$gateCheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'upload_lab_result');
		if (!$gateCheck['allowed'] && !has_role('admin')) {
			redirect(base_url() . 'app/sonography/upload_results/' . (int)$io_lab_id, $this->data);
			return;
		}

		$contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
		$postMaxSize = (string)ini_get('post_max_size');
		$uploadMaxFilesize = (string)ini_get('upload_max_filesize');
		$this->auth_debug_log('UPLOAD_FILES_SNAPSHOT', array(
			'io_lab_id' => (int)$io_lab_id,
			'has_FILES' => isset($_FILES) ? 1 : 0,
			'files_keys' => isset($_FILES) ? implode(',', array_keys((array)$_FILES)) : '',
			'content_length' => $contentLength,
			'post_max_size' => $postMaxSize,
			'upload_max_filesize' => $uploadMaxFilesize
		));

		if (!isset($_FILES['result_upload']) || !isset($_FILES['result_upload']['name']) || trim((string)$_FILES['result_upload']['name']) === '') {
			$fileErr = null;
			if (isset($_FILES['result_upload']) && is_array($_FILES['result_upload'])) {
				$fileErr = array(
					'name' => isset($_FILES['result_upload']['name']) ? (string)$_FILES['result_upload']['name'] : '',
					'type' => isset($_FILES['result_upload']['type']) ? (string)$_FILES['result_upload']['type'] : '',
					'error' => isset($_FILES['result_upload']['error']) ? (int)$_FILES['result_upload']['error'] : -1,
					'size' => isset($_FILES['result_upload']['size']) ? (int)$_FILES['result_upload']['size'] : -1,
					'tmp_name' => isset($_FILES['result_upload']['tmp_name']) ? (string)$_FILES['result_upload']['tmp_name'] : ''
				);
			}
			$this->auth_debug_log('UPLOAD_NO_FILE', array('io_lab_id' => (int)$io_lab_id, 'file' => $fileErr));
			if ($contentLength > 0 && (!isset($_FILES) || empty($_FILES))) {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Upload failed before the server received the file. The selected file may be larger than the server limit (post_max_size/upload_max_filesize). Please reduce the file size or increase PHP upload limits.</div>");
			} else {
				$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Please choose a PDF/JPG/PNG file to upload.</div>");
			}
			redirect(base_url() . 'app/sonography/upload_results/' . $io_lab_id, $this->data);
			return;
		}

		$privateDir = $this->ensure_private_lab_result_dir();
		if (!is_dir($privateDir)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Upload storage directory is missing. Please contact Admin.</div>");
			redirect(base_url() . 'app/sonography/upload_results/' . $io_lab_id, $this->data);
			return;
		}
		if (!is_writable($privateDir)) {
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Upload storage directory is not writable. Please contact Admin.</div>");
			redirect(base_url() . 'app/sonography/upload_results/' . $io_lab_id, $this->data);
			return;
		}
		$config = array(
			'allowed_types' => 'pdf|jpg|jpeg|png',
			'upload_path' => $privateDir,
			'max_size' => 10240,
			'file_ext_tolower' => true,
			'encrypt_name' => true,
			'remove_spaces' => true
		);

		$this->load->library('upload', $config);

		if (!$this->upload->do_upload('result_upload')) {
			$this->auth_debug_log('UPLOAD_CI_FAIL', array('io_lab_id' => (int)$io_lab_id, 'err' => strip_tags($this->upload->display_errors())));
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>" . $this->upload->display_errors() . "</div>");
			redirect(base_url() . 'app/sonography/upload_results/' . $io_lab_id, $this->data);
			return;
		}

		$lab_data = $this->upload->data();
		$this->auth_debug_log('UPLOAD_OK', array('io_lab_id' => (int)$io_lab_id, 'stored' => isset($lab_data['file_name']) ? (string)$lab_data['file_name'] : ''));
		$this->db->trans_begin();
		$this->laboratory_model->lock_sonography_request_for_update($io_lab_id);
		$gateRecheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'upload_lab_result_commit');
		if (!$gateRecheck['allowed'] && !has_role('admin')) {
			$this->db->trans_rollback();
			if (isset($lab_data['full_path']) && (string)$lab_data['full_path'] !== '' && file_exists((string)$lab_data['full_path'])) {
				@unlink((string)$lab_data['full_path']);
			}
			redirect(base_url() . 'app/sonography/upload_results/' . (int)$io_lab_id, $this->data);
			return;
		}
		$this->laboratory_model->upload_lab_result_pdf($lab_data, $io_lab_id, $this->session->userdata('user_id'));
		$this->db->trans_commit();
		$this->audit_sonography_gate_decision($io_lab_id, 'upload_lab_result_commit', $gateRecheck, 'SONO_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $gateRecheck);
		log_message('info', 'SONOGRAPHY_RESULT_PUBLISHED upload io_lab_id=' . $io_lab_id);
		General::logfile('Sonography', 'UPLOAD', (string)$io_lab_id);
		$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Result successfully Uploaded</div>");
		redirect(base_url() . 'app/sonography/upload_results/' . $io_lab_id, $this->data);
	}

	public function save_result($io_lab_id = null, $iop_id = null, $lab_request_name = null)
	{
		$this->auth_debug_log('SAVE_ENTER', array('submit_action' => (string)$this->input->post('submit_action')));
		$this->require_sonography_access();
		$this->require_sonography_write_access();

		$submitAction = strtolower(trim((string)$this->input->post('submit_action')));
		$isDraft = ($submitAction === 'draft' || isset($_POST['btnDraft']));
		$isPublish = ($submitAction === 'publish');
		if (!$isDraft && !$isPublish && !isset($_POST['btnSubmit'])) {
			$this->auth_debug_log('SAVE_DENY_NO_SUBMIT', array('is_draft' => $isDraft ? 1 : 0));
			redirect(base_url() . 'access_denied');
			return;
		}

		$this->laboratory_model->install_imaging_tables();
		$io_lab_id = (int)$this->input->post('io_lab_id');
		if ($io_lab_id <= 0) {
			$io_lab_id = (int)$this->uri->segment(4);
		}
		if ($io_lab_id <= 0) {
			$this->session->set_flashdata('message', "<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Missing request reference. Please reopen the result screen.</div>");
			$this->auth_debug_log('SAVE_DENY_MISSING_IO_LAB_ID');
			redirect(base_url() . 'access_denied');
			return;
		}
		$iop_id_post = trim((string)$this->input->post('iop_id'));
		if ($iop_id_post === '' || $iop_id_post === '0') {
			$iop_id_post = trim((string)$this->uri->segment(5));
		}
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			redirect(base_url() . 'access_denied');
			return;
		}
		if ($iop_id_post === '' || $iop_id_post === '0') {
			$iop_id_post = (isset($row->iop_id) && (string)$row->iop_id !== '') ? (string)$row->iop_id : '';
		}
		
		// ENFORCE BILLING GATE - Block result entry if payment not complete and no admin bypass
		$gateCheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'save_result');
		if (!$gateCheck['allowed']) {
			// Sonographer cannot process test without payment or admin bypass
			$this->session->set_flashdata('message', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-ban'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>RESULT ENTRY BLOCKED:</strong> ".$gateCheck['reason']." Contact admin to authorize or ensure payment is complete.</div>");
			redirect(base_url() . 'app/sonography/results/' . (int)$io_lab_id . '/' . $iop_id_post);
			return;
		}

		if ($isDraft) {
			$this->db->trans_begin();
			$this->laboratory_model->lock_sonography_request_for_update($io_lab_id);
			$gateRecheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'save_result_draft');
			if (!$gateRecheck['allowed'] && !has_role('admin')) {
				$this->db->trans_rollback();
				redirect(base_url() . 'app/sonography/results/' . (int)$io_lab_id . '/' . $iop_id_post, $this->data);
				return;
			}
			$this->laboratory_model->touch_in_progress_if_needed($io_lab_id, $this->session->userdata('user_id'));
			$this->laboratory_model->upsert_sonography_report_draft(
				$io_lab_id,
				$this->input->post('findings'),
				$this->input->post('result'),
				$this->session->userdata('user_id')
			);
			$this->db->trans_commit();
			$this->audit_sonography_gate_decision($io_lab_id, 'save_result_draft', $gateRecheck, 'SONO_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gateRecheck);
			General::logfile('Sonography', 'DRAFT', (string)$io_lab_id);
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Draft saved.</div>");
			redirect(base_url() . 'app/sonography/results/' . (int)$io_lab_id . '/' . $iop_id_post, $this->data);
			return;
		}

		$this->form_validation->set_rules("result", "Result", "trim|xss_clean|required");
		$this->form_validation->set_error_delimiters("<div class='alert alert-warning alert-dismissable'><i class='fa fa-warning'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>", "</div>");

		if ($this->form_validation->run()) {
			$this->db->trans_begin();
			$this->laboratory_model->lock_sonography_request_for_update($io_lab_id);
			$gateRecheck = $this->enforce_sonography_billing_gate($io_lab_id, true, 'save_result_publish');
			if (!$gateRecheck['allowed'] && !has_role('admin')) {
				$this->db->trans_rollback();
				redirect(base_url() . 'app/sonography/results/' . (int)$io_lab_id . '/' . $iop_id_post, $this->data);
				return;
			}
			$this->laboratory_model->edit_save();
			$this->laboratory_model->install_imaging_tables();

			$updatedRow = $this->db->get_where('iop_laboratory', array('io_lab_id' => (int)$io_lab_id))->row();
			$hasPdf = ($updatedRow && isset($updatedRow->lab_result_upload) && trim((string)$updatedRow->lab_result_upload) !== '');
			$status = $hasPdf ? 'REPORTED_BOTH' : 'REPORTED_TEXT';
			$this->laboratory_model->upsert_workflow_status($io_lab_id, $status, $this->session->userdata('user_id'));
			$this->laboratory_model->clear_sonography_report_draft($io_lab_id);
			log_message('info', 'SONOGRAPHY_RESULT_PUBLISHED text io_lab_id=' . (int)$io_lab_id);

			// Phase 4.5: Detect critical sonography alerts
			$findings_text = $this->input->post('findings');
			$result_text = $this->input->post('result');
			$detected = $this->diagnostic_safety_model->detect_sonography_critical_alerts($findings_text, $result_text);
			
			if (!empty($detected)) {
				$patient_no = isset($row->patient_no) ? $row->patient_no : null;
				$ordering_doctor = isset($row->requested_by) ? $row->requested_by : null;
				foreach ($detected as $alert_def) {
					$this->diagnostic_safety_model->create_sonography_critical_alert(
						$io_lab_id,
						$patient_no,
						$alert_def['definition_id'],
						$findings_text . ' ' . $result_text,
						$ordering_doctor
					);
				}
				$this->session->set_flashdata('warning', "<div class='alert alert-danger alert-dismissable'><i class='fa fa-exclamation-triangle'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button><strong>CRITICAL FINDING DETECTED:</strong> " . count($detected) . " critical finding(s) identified. Ordering physician has been notified.</div>");
			}

			General::logfile('Sonography', 'UPDATE', (string)$io_lab_id);
			$this->db->trans_commit();
			$this->audit_sonography_gate_decision($io_lab_id, 'save_result_publish', $gateRecheck, 'SONO_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gateRecheck);
			$this->session->set_flashdata('message', "<div class='alert alert-success alert-dismissable'><i class='fa fa-check'></i><button type='button' class='close' data-dismiss='alert' aria-hidden='true'>&times;</button>Result successfully Recorded!</div>");
			redirect(base_url() . 'app/sonography/request/' . $iop_id_post, $this->data);
			return;
		}

		redirect(base_url() . 'app/sonography/results/' . (int)$io_lab_id . '/' . $iop_id_post, $this->data);
	}

	public function download_result($io_lab_id)
	{
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) {
			redirect(base_url() . 'app/laboratory/download_result/' . (int)$io_lab_id);
			return;
		}
		$this->require_sonography_access();

		$io_lab_id = (int)$io_lab_id;
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row || (int)$row->category_id !== 18) {
			redirect(base_url() . 'access_denied');
			return;
		}
		$filename = isset($row->lab_result_upload) ? (string)$row->lab_result_upload : '';
		if (trim($filename) === '') {
			redirect(base_url() . 'access_denied');
			return;
		}
		if (isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor']) {
			$this->laboratory_model->mark_delivered_if_needed($io_lab_id, $this->session->userdata('user_id'));
		}

		$safeName = basename($filename);
		$privateDir = $this->ensure_private_lab_result_dir();
		$privateBase = realpath($privateDir);
		$privatePath = realpath($privateDir . DIRECTORY_SEPARATOR . $safeName);
		$publicBase = realpath('public/patient_lab_result');
		$publicPath = realpath('public/patient_lab_result/' . $safeName);
		$fullPath = null;
		if ($privateBase && $privatePath && strpos($privatePath, $privateBase) === 0 && file_exists($privatePath)) {
			$fullPath = $privatePath;
		} else if ($publicBase && $publicPath && strpos($publicPath, $publicBase) === 0 && file_exists($publicPath)) {
			$fullPath = $publicPath;
		}
		if (!$fullPath) {
			redirect(base_url() . 'access_denied');
			return;
		}

		$ext = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
		$contentType = 'application/octet-stream';
		if ($ext === 'pdf') {
			$contentType = 'application/pdf';
		} else if ($ext === 'jpg' || $ext === 'jpeg') {
			$contentType = 'image/jpeg';
		} else if ($ext === 'png') {
			$contentType = 'image/png';
		} else {
			redirect(base_url() . 'access_denied');
			return;
		}

		header('X-Content-Type-Options: nosniff');
		header('Content-Type: ' . $contentType);
		header('Content-Disposition: inline; filename="' . $safeName . '"');
		header('Content-Length: ' . filesize($fullPath));
		readfile($fullPath);
		exit;
	}

	/**
	 * View completed/previous scans with search functionality
	 */
	public function completed($offset = 0)
	{
		$this->require_sonography_access();

		$this->session->set_userdata(array(
			'tab' => 'sonography',
			'module' => 'sonography',
			'subtab' => 'completed',
			'submodule' => ''
		));

		$uri_segment = 4;
		$offset = (int)$this->uri->segment($uri_segment);
		$groupId = 18; // Sonography category
		$this->data['page_title'] = 'Completed Scans History';
		$this->data['module_base'] = 'sonography';

		$search = trim((string)$this->input->get('search'));
		$date_from = trim((string)$this->input->get('date_from'));
		$date_to = trim((string)$this->input->get('date_to'));

		// Default to last 30 days if no date filter
		if ($date_from === '' && $date_to === '') {
			$date_from = date('Y-m-d', strtotime('-30 days'));
			$date_to = date('Y-m-d');
		}

		$completed_scans = $this->laboratory_model->completed_sonography_scans($groupId, $this->limit, $offset, $search, $date_from, $date_to);

		$config['base_url'] = base_url() . 'app/sonography/completed/';
		$config['total_rows'] = $this->laboratory_model->count_completed_sonography_scans($groupId, $search, $date_from, $date_to);
		$config['per_page'] = $this->limit;
		$config['uri_segment'] = $uri_segment;

		$qs = '';
		$parts = array();
		if ($search !== '') { $parts[] = 'search=' . urlencode($search); }
		if ($date_from !== '') { $parts[] = 'date_from=' . urlencode($date_from); }
		if ($date_to !== '') { $parts[] = 'date_to=' . urlencode($date_to); }
		if (count($parts) > 0) {
			$qs = '?' . implode('&', $parts);
			$config['suffix'] = $qs;
			$config['first_url'] = $config['base_url'] . '0' . $qs;
		}

		$config['full_tag_open'] = '<ul class="pagination pagination no-margin pull-right">';
		$config['full_tag_close'] = '</ul><!--pagination-->';
		$config['first_link'] = '&laquo; First';
		$config['first_tag_open'] = '<li class="prev page">';
		$config['first_tag_close'] = '</li>';
		$config['last_link'] = 'Last &raquo;';
		$config['last_tag_open'] = '<li class="next page">';
		$config['last_tag_close'] = '</li>';
		$config['next_link'] = 'Next &rarr;';
		$config['next_tag_open'] = '<li class="next page">';
		$config['next_tag_close'] = '</li>';
		$config['prev_link'] = '&larr; Previous';
		$config['prev_tag_open'] = '<li class="prev page">';
		$config['prev_tag_close'] = '</li>';
		$config['cur_tag_open'] = '<li class="active"><a href="">';
		$config['cur_tag_close'] = '</a></li>';
		$config['num_tag_open'] = '<li class="page">';
		$config['num_tag_close'] = '</li>';
		$this->pagination->initialize($config);
		$this->data['pagination'] = $this->pagination->create_links();

		$tmpl = array('table_open' => '<table class="table table-hover table-striped">');
		$this->table->set_template($tmpl);
		$this->table->set_empty("&nbsp;");
		$this->table->set_heading('Scan Type', 'IOP', 'Patient ID', 'Patient Name', 'Age', 'Date', 'Result', 'Action');

		foreach ($completed_scans as $scan) {
			$scanName = '';
			if (isset($scan->sono_item_name) && trim((string)$scan->sono_item_name) !== '') {
				$scanName = (string)$scan->sono_item_name;
			} else if (isset($scan->particular_name) && trim((string)$scan->particular_name) !== '') {
				$scanName = (string)$scan->particular_name;
			} else {
				$scanName = (string)$scan->laboratory_text;
			}

			$resultPreview = '';
			if (isset($scan->result) && trim((string)$scan->result) !== '') {
				$resultPreview = substr(strip_tags((string)$scan->result), 0, 50);
				if (strlen((string)$scan->result) > 50) {
					$resultPreview .= '...';
				}
			}

			$scanDate = isset($scan->dDate) ? date('Y-m-d', strtotime((string)$scan->dDate)) : '';

			$viewLink = anchor('app/sonography/results/' . $scan->io_lab_id . '/' . $scan->iop_id, '<i class="fa fa-eye"></i> View', array('class' => 'btn btn-xs btn-info'));

			$this->table->add_row(
				$scanName,
				anchor('app/sonography/request/' . $scan->iop_id, $scan->iop_id),
				$scan->patient_no,
				$scan->patient_name,
				$this->birth_day_age($scan->birthday),
				$scanDate,
				$resultPreview,
				$viewLink
			);
		}

		// Build navigation and search form
		$nav = "<div style='margin-bottom:10px'>";
		$nav .= "<a class='btn btn-default' href='" . base_url() . "app/sonography/index'><i class='fa fa-arrow-left'></i> Pending Queue</a> ";
		$nav .= "<a class='btn btn-primary active' href='" . base_url() . "app/sonography/completed'><i class='fa fa-history'></i> Completed Scans</a>";
		$nav .= "</div>";

		$nav .= "<form method='get' action='" . base_url() . "app/sonography/completed' class='form-inline' style='margin-bottom:15px'>";
		$nav .= "<div class='form-group' style='margin-right:10px'>";
		$nav .= "<input type='text' name='search' class='form-control input-sm' placeholder='Search patient/IOP/result...' value='" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "' style='width:220px'>";
		$nav .= "</div>";
		$nav .= "<div class='form-group' style='margin-right:10px'>";
		$nav .= "<label style='margin-right:5px'>From:</label>";
		$nav .= "<input type='date' name='date_from' class='form-control input-sm' value='" . htmlspecialchars($date_from, ENT_QUOTES, 'UTF-8') . "'>";
		$nav .= "</div>";
		$nav .= "<div class='form-group' style='margin-right:10px'>";
		$nav .= "<label style='margin-right:5px'>To:</label>";
		$nav .= "<input type='date' name='date_to' class='form-control input-sm' value='" . htmlspecialchars($date_to, ENT_QUOTES, 'UTF-8') . "'>";
		$nav .= "</div>";
		$nav .= "<button class='btn btn-sm btn-primary' type='submit'><i class='fa fa-search'></i> Search</button>";
		$nav .= " <a class='btn btn-sm btn-default' href='" . base_url() . "app/sonography/completed'><i class='fa fa-refresh'></i> Reset</a>";
		$nav .= "</form>";

		$this->data['message'] = $nav . (string)$this->session->flashdata('message');
		$this->data['table'] = $this->table->generate();
		$this->load->view('app/laboratory/index', $this->data);
	}

	/* ================================================================== */
	/*  B3 ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Technician Assignment (AJAX POST)                            */
	/* ================================================================== */

	public function assign_technician()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'POST required'));
			return;
		}
		$this->require_sonography_write_access();

		$io_lab_id    = (int)$this->input->post('io_lab_id');
		$technician_id = trim((string)$this->input->post('technician_id'));
		$user_id       = (string)$this->session->userdata('user_id');

		if ($io_lab_id <= 0 || $technician_id === '') {
			echo json_encode(array('ok' => false, 'message' => 'Missing parameters'));
			return;
		}

		$this->db->trans_begin();
		$this->laboratory_model->lock_sonography_request_for_update($io_lab_id);
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}

		$gateCheck = $this->enforce_sonography_billing_gate($io_lab_id, false, 'assign_technician');
		if (!$gateCheck['allowed'] && !has_role('admin')) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Payment required: ' . (isset($gateCheck['reason']) ? $gateCheck['reason'] : 'Outstanding balance')));
			return;
		}

		$ok = $this->laboratory_model->save_technician($io_lab_id, $technician_id);
		if ($ok) {
			$this->db->trans_commit();
			$this->audit_sonography_gate_decision($io_lab_id, 'assign_technician', $gateCheck, 'SONO_GATE_ALLOWED');
			$this->maybe_sample_gate_parity_check($io_lab_id, $gateCheck);
			log_message('info', 'SONO_TECH_ASSIGNED io_lab_id='.$io_lab_id.' tech='.$technician_id.' by='.$user_id);
			echo json_encode(array('ok' => true, 'message' => 'Technician assigned'));
		} else {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Failed to assign technician'));
		}
	}

	/* ================================================================== */
	/*  B7 ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Supervisor Verification (AJAX POST)                          */
	/* ================================================================== */

	public function supervisor_verify()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'POST required'));
			return;
		}
		$isAdmin      = isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'];
		$isSupervisor = has_role(array('admin', 'lab_supervisor', 'pathologist', 'senior_sonographer', 'radiologist'));
		if (!$isAdmin && !$isSupervisor) {
			echo json_encode(array('ok' => false, 'message' => 'Supervisor role required'));
			return;
		}

		$io_lab_id = (int)$this->input->post('io_lab_id');
		$notes     = trim((string)$this->input->post('notes'));
		$user_id   = (string)$this->session->userdata('user_id');

		if ($io_lab_id <= 0) {
			echo json_encode(array('ok' => false, 'message' => 'Missing io_lab_id'));
			return;
		}

		$this->db->trans_begin();
		$this->laboratory_model->lock_sonography_request_for_update($io_lab_id);
		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}

		$gateCheck = $this->enforce_sonography_billing_gate($io_lab_id, false, 'supervisor_verify');
		if (!$gateCheck['allowed'] && !has_role('admin')) {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Payment required: ' . (isset($gateCheck['reason']) ? $gateCheck['reason'] : 'Outstanding balance')));
			return;
		}

		$wf = $this->laboratory_model->get_workflow_status($io_lab_id);
		$st = $wf && isset($wf->status) ? strtoupper(trim((string)$wf->status)) : '';
		$reportedStates = array('REPORTED_TEXT','REPORTED_PDF','REPORTED_BOTH','REPORTED');
		if (!in_array($st, $reportedStates) && $st !== 'VERIFIED') {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => false, 'message' => 'Result must be reported before supervisor verification'));
			return;
		}
		if ($st === 'VERIFIED') {
			$this->db->trans_rollback();
			echo json_encode(array('ok' => true, 'message' => 'Already verified'));
			return;
		}

		$this->laboratory_model->upsert_workflow_status($io_lab_id, 'VERIFIED', $user_id);

		if ($this->laboratory_model->column_exists('iop_laboratory_workflow', 'verified_by')) {
			$this->db->where('io_lab_id', $io_lab_id);
			$update = array('verified_by' => $user_id, 'verified_at' => date('Y-m-d H:i:s'));
			if ($notes !== '' && $this->laboratory_model->column_exists('iop_laboratory_workflow', 'supervisor_notes')) {
				$update['supervisor_notes'] = $notes;
			}
			$this->db->update('iop_laboratory_workflow', $update);
		}

		log_message('info', 'SONO_SUPERVISOR_VERIFIED io_lab_id='.$io_lab_id.' by='.$user_id);
		$this->db->trans_commit();
		$this->audit_sonography_gate_decision($io_lab_id, 'supervisor_verify', $gateCheck, 'SONO_GATE_ALLOWED');
		$this->maybe_sample_gate_parity_check($io_lab_id, $gateCheck);
		echo json_encode(array('ok' => true, 'message' => 'Result verified by supervisor'));
	}

	/* ================================================================== */
	/*  A7 ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¾Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€šÃ‚Â¦ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬ÃƒÂ¢Ã¢â‚¬Å¾Ã‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¦ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Â ÃƒÂ¢Ã¢â€šÂ¬Ã¢â€žÂ¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã¢â‚¬Â¦Ãƒâ€šÃ‚Â¡ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â Doctor Acknowledgement (AJAX POST)                           */
	/* ================================================================== */

	public function doctor_acknowledge()
	{
		if ($this->input->method(TRUE) !== 'POST') {
			echo json_encode(array('ok' => false, 'message' => 'POST required'));
			return;
		}
		$isDoctor = isset($this->data['hasAccesstoDoctor']) && $this->data['hasAccesstoDoctor'];
		$isAdmin  = isset($this->data['hasAccesstoAdmin']) && $this->data['hasAccesstoAdmin'];
		if (!$isDoctor && !$isAdmin) {
			echo json_encode(array('ok' => false, 'message' => 'Doctor role required'));
			return;
		}

		$io_lab_id = (int)$this->input->post('io_lab_id');
		$user_id   = (string)$this->session->userdata('user_id');

		if ($io_lab_id <= 0) {
			echo json_encode(array('ok' => false, 'message' => 'Missing io_lab_id'));
			return;
		}

		$row = $this->laboratory_model->get_lab_record_with_patient($io_lab_id);
		if (!$row) {
			echo json_encode(array('ok' => false, 'message' => 'Record not found'));
			return;
		}

		$this->laboratory_model->mark_delivered_if_needed($io_lab_id, $user_id);

		if ($this->laboratory_model->table_exists('iop_laboratory_workflow')) {
			$this->laboratory_model->ensure_lab_workflow_enhancements();
			if ($this->laboratory_model->column_exists('iop_laboratory_workflow', 'doctor_acknowledged_at')) {
				$existing = $this->db->get_where('iop_laboratory_workflow', array('io_lab_id' => $io_lab_id))->row();
				if ($existing) {
					$this->db->where('io_lab_id', $io_lab_id);
					$this->db->update('iop_laboratory_workflow', array(
						'doctor_acknowledged_at' => date('Y-m-d H:i:s'),
						'doctor_acknowledged_by' => $user_id
					));
				}
			}
		}

		log_message('info', 'SONO_DOCTOR_ACKNOWLEDGED io_lab_id='.$io_lab_id.' by='.$user_id);
		echo json_encode(array('ok' => true, 'message' => 'Result acknowledged'));
	}

	public function birth_day_age($dob)
	{
		$year = (date('Y') - date('Y', strtotime($dob)));
		return $year;
	}

	/* ================================================================== */
	/*  ADMIN BILLING BYPASS MANAGEMENT                                   */
	/* ================================================================== */

	/**
	 * Admin: Set billing bypass for a sonography test (AJAX)
	 * Allows test to proceed without payment
	 */
	public function admin_set_billing_bypass()
	{
		header('Content-Type: application/json');
		
		// Admin only
		if (!$this->is_admin_user()) {
			echo json_encode(array('success' => false, 'error' => 'Admin access required'));
			return;
		}
		
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$reason = trim((string)$this->input->post('reason'));
		$user_id = $this->session->userdata('user_id');
		
		if ($io_lab_id <= 0) {
			echo json_encode(array('success' => false, 'error' => 'Invalid test ID'));
			return;
		}
		
		if ($reason === '') {
			echo json_encode(array('success' => false, 'error' => 'Bypass reason is required'));
			return;
		}
		
		$result = $this->laboratory_model->set_billing_bypass($io_lab_id, $reason, $user_id);
		echo json_encode($result);
	}

	/**
	 * Admin: Remove billing bypass for a sonography test (AJAX)
	 */
	public function admin_remove_billing_bypass()
	{
		header('Content-Type: application/json');
		
		// Admin only
		if (!$this->is_admin_user()) {
			echo json_encode(array('success' => false, 'error' => 'Admin access required'));
			return;
		}
		
		$io_lab_id = (int)$this->input->post('io_lab_id');
		$user_id = $this->session->userdata('user_id');
		
		if ($io_lab_id <= 0) {
			echo json_encode(array('success' => false, 'error' => 'Invalid test ID'));
			return;
		}
		
		$result = $this->laboratory_model->remove_billing_bypass($io_lab_id, $user_id);
		echo json_encode(array('success' => $result));
	}

	/**
	 * Admin: Get billing bypass status for a test (AJAX)
	 */
	public function get_billing_bypass_status($io_lab_id = null)
	{
		header('Content-Type: application/json');
		
		$io_lab_id = $io_lab_id ? (int)$io_lab_id : (int)$this->input->get('io_lab_id');
		
		if ($io_lab_id <= 0) {
			echo json_encode(array('success' => false, 'error' => 'Invalid test ID'));
			return;
		}
		
		$gateCheck = $this->laboratory_model->check_sonography_billing_gate($io_lab_id);
		$bypass = $this->laboratory_model->get_billing_bypass($io_lab_id);
		
		echo json_encode(array(
			'success' => true,
			'allowed' => $gateCheck['allowed'],
			'reason' => $gateCheck['reason'],
			'has_bypass' => $gateCheck['bypass'],
			'bypass_details' => $bypass ? array(
				'bypass_id' => $bypass->bypass_id,
				'reason' => $bypass->bypass_reason,
				'bypassed_by' => $bypass->bypassed_by,
				'bypassed_at' => $bypass->bypassed_at
			) : null,
			'can_set_bypass' => $this->is_admin_user()
		));
	}
}
