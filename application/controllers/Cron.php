<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Cron extends CI_Controller
{
	protected $_shadow_db_available = false;
	protected $_shadow_db_error = null;

	public function __construct()
	{
		parent::__construct();
		date_default_timezone_set('Africa/Accra');
		try {
			$this->load->database();
			$this->_shadow_db_available = (isset($this->db) && is_object($this->db));
		} catch (Exception $e) {
			$this->_shadow_db_available = false;
			$this->_shadow_db_error = $e->getMessage();
		}
		if ($this->_shadow_db_available) {
			$this->load->model('app/opd_model');
			$this->load->model('app/ipd_model');
			$this->load->model('app/billing_model');
			$this->load->model('general_model');
		}
	}

	protected function shadowEnsureDbOrExit($label)
	{
		if ($this->_shadow_db_available && isset($this->db) && is_object($this->db)) {
			return;
		}
		$host = getenv('DB_HOSTNAME');
		if ($host === false || $host === '') {
			$host = 'localhost';
		}
		$db = getenv('DB_DATABASE');
		if ($db === false || $db === '') {
			$db = 'hms_master';
		}
		$user = getenv('DB_USERNAME');
		if ($user === false || $user === '') {
			$user = 'root';
		}
		$msg = $this->_shadow_db_error !== null ? (string)$this->_shadow_db_error : 'unknown';
		echo "[{$label}][error] db_connection_failed host={$host} db={$db} user={$user} msg={$msg}\n";
		echo "[{$label}][hint] start_mysql_or_fix_DB_HOSTNAME_DB_USERNAME_DB_PASSWORD_DB_DATABASE\n";
		exit(2);
	}

	public function shadow_expectedset_validate($domain = '', $intent = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}

		$path = APPPATH.'libraries/ShadowExpectedSetValidator.php';
		if (!file_exists($path)) {
			echo "[shadow_expectedset_validate][error] validator_missing\n";
			exit(2);
		}
		require_once($path);
		$validator = new ShadowExpectedSetValidator();
		$domain = strtoupper(trim((string)$domain));
		$intent = strtoupper(trim((string)$intent));
		$result = ($domain !== '' || $intent !== '')
			? $validator->validateDomainIntent($domain, $intent)
			: $validator->validateAll();

		$status = is_array($result) && isset($result['status']) ? (string)$result['status'] : 'FAIL';
		echo "[shadow_expectedset_validate][".strtolower($status)."] ".json_encode($result)."\n";
		exit($status === 'PASS' ? 0 : 1);
	}

	public function shadow_governance_analytics($days = 1)
	{
		if (!is_cli()) {
			show_404();
			return;
		}

		$path = APPPATH.'libraries/ShadowGovernanceAnalytics.php';
		if (!file_exists($path)) {
			echo "[shadow_governance_analytics][error] analytics_missing\n";
			exit(2);
		}
		require_once($path);
		$analytics = new ShadowGovernanceAnalytics();
		$report = $analytics->analyze((int)$days);
		echo "[shadow_governance_analytics][pass] ".json_encode($report)."\n";
		exit(0);
	}

	public function shadow_coverage_convergence()
	{
		if (!is_cli()) {
			show_404();
			return;
		}

		$path = APPPATH.'libraries/ShadowCoverageConvergenceValidator.php';
		if (!file_exists($path)) {
			echo "[shadow_coverage_convergence][error] validator_missing\n";
			exit(2);
		}
		require_once($path);
		$validator = new ShadowCoverageConvergenceValidator();
		$result = $validator->validate();
		$status = is_array($result) && isset($result['status']) ? (string)$result['status'] : 'FAIL';
		echo "[shadow_coverage_convergence][".strtolower($status)."] ".json_encode($result)."\n";
		exit($status === 'FAIL' ? 1 : 0);
	}

	public function process_detention_conversion()
	{
		if (!is_cli()) {
			show_404();
			return;
		}

		$this->opd_model->ensure_detention_schema();

		$now = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
		$today = $now->format('Y-m-d');
		$nowStr = $now->format('Y-m-d H:i:s');

		$q = $this->db->query(
			"SELECT IO_ID, patient_no, doctor_id, department_id, room_id, detention_start_at, converted_to_admission_at, converted_ipd_iop_id\n".
			"FROM patient_details_iop\n".
			"WHERE InActive = 0\n".
			"  AND patient_type = 'OPD'\n".
			"  AND detention_start_at IS NOT NULL\n".
			"  AND detention_start_at != '0000-00-00 00:00:00'\n".
			"  AND (converted_to_admission_at IS NULL OR converted_to_admission_at = '0000-00-00 00:00:00')\n"
		);
		$rows = $q ? $q->result() : array();

		echo "[detention_conversion] now={$nowStr} tz=" . date_default_timezone_get() . " candidates=" . count($rows) . "\n";

		$converted = 0;
		$skipped = 0;
		$errors = 0;

		foreach ($rows as $r) {
			$opd_iop_id = isset($r->IO_ID) ? trim((string)$r->IO_ID) : '';
			$patient_no = isset($r->patient_no) ? trim((string)$r->patient_no) : '';
			$detStart = isset($r->detention_start_at) ? trim((string)$r->detention_start_at) : '';
			$bedId = isset($r->room_id) ? (int)$r->room_id : 0;

			if ($opd_iop_id === '' || $patient_no === '' || $detStart === '') {
				$skipped++;
				echo "[detention_conversion][skip] missing_data opd={$opd_iop_id} patient={$patient_no}\n";
				continue;
			}

			$detTs = strtotime($detStart);
			if ($detTs === false) {
				$skipped++;
				echo "[detention_conversion][skip] invalid_detention_start opd={$opd_iop_id} patient={$patient_no} start={$detStart}\n";
				continue;
			}

			$detDate = date('Y-m-d', $detTs);
			if ($detDate >= $today) {
				$skipped++;
				continue;
			}

			$nextMidnight = new DateTime($detDate . ' 00:00:00', new DateTimeZone(date_default_timezone_get()));
			$nextMidnight->modify('+1 day');
			if ($now < $nextMidnight) {
				$skipped++;
				continue;
			}

			$admitAt = $nextMidnight;
			$admitAtStr = $admitAt->format('Y-m-d H:i:s');
			$admitPlus24 = clone $admitAt;
			$admitPlus24->modify('+1 day');
			$untilStr = $admitPlus24->format('Y-m-d H:i:s');

			// Idempotency guard: if an IPD admission already exists linked to this OPD
			$this->db->where(array('patient_type' => 'IPD', 'source_opd_iop_id' => $opd_iop_id, 'InActive' => 0));
			$this->db->limit(1);
			$existing = $this->db->get('patient_details_iop')->row();
			if ($existing && isset($existing->IO_ID)) {
				$this->db->where(array('IO_ID' => $opd_iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
				$this->db->update('patient_details_iop', array(
					'converted_to_admission_at' => $nowStr,
					'converted_ipd_iop_id' => (string)$existing->IO_ID,
				));
				$skipped++;
				echo "[detention_conversion][skip] already_converted opd={$opd_iop_id} ipd=" . (string)$existing->IO_ID . "\n";
				continue;
			}

			if ($bedId <= 0) {
				$errors++;
				echo "[detention_conversion][error] no_bed_assigned opd={$opd_iop_id} patient={$patient_no}\n";
				continue;
			}

			// Compute new IPD number (atomic increment)
			$this->db->query("UPDATE system_option SET cValue = (cValue + 1) WHERE cCode = 'INPATIENTNO' AND InActive = 0");
			$opt = $this->db->query("SELECT cValue FROM system_option WHERE cCode = 'INPATIENTNO' AND InActive = 0 LIMIT 1")->row();
			$seq = ($opt && isset($opt->cValue)) ? (int)$opt->cValue : 0;
			if ($seq <= 0) {
				$errors++;
				echo "[detention_conversion][error] ipd_sequence_missing opd={$opd_iop_id}\n";
				continue;
			}
			$ipd_iop_id = 'IP-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);

			$doctor_id = isset($r->doctor_id) ? trim((string)$r->doctor_id) : '';
			$dept_id = isset($r->department_id) ? trim((string)$r->department_id) : '';
			$this->load->model('app/bed_occupancy_model');
			$res = $this->bed_occupancy_model->create_ipd_admission_from_detention(array(
				'ipd_iop_id' => $ipd_iop_id,
				'patient_no' => $patient_no,
				'doctor_id' => $doctor_id,
				'department_id' => $dept_id,
				'bed_id' => $bedId,
				'admitted_at' => $admitAtStr,
				'opd_iop_id' => $opd_iop_id,
			));

			if (!$res || empty($res['ok'])) {
				$errors++;
				$msg = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'Unknown error';
				echo "[detention_conversion][error] admit_failed opd={$opd_iop_id} ipd={$ipd_iop_id} err={$msg}\n";
				continue;
			}

			// Generate room charges for the first billable day (midnight->midnight)
			$this->billing_model->generate_ipd_room_charges($ipd_iop_id, $untilStr);

			$this->db->where(array('IO_ID' => $opd_iop_id, 'patient_no' => $patient_no, 'InActive' => 0));
			$this->db->update('patient_details_iop', array(
				'converted_to_admission_at' => $nowStr,
				'converted_ipd_iop_id' => $ipd_iop_id,
			));

			$converted++;
			echo "[detention_conversion][ok] opd={$opd_iop_id} ipd={$ipd_iop_id} admit_at={$admitAtStr} bed={$bedId}\n";
		}

		echo "[detention_conversion] done converted={$converted} skipped={$skipped} errors={$errors}\n";
	}

	public function bed_occupancy_invariant_scan($limit = 50)
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('bed_occupancy_invariant_scan');
		$this->load->model('app/bed_occupancy_diagnostics_model');
		$res = $this->bed_occupancy_diagnostics_model->scan((int)$limit);
		$status = (is_array($res) && isset($res['ok']) && $res['ok'] === true) ? 'ok' : 'fail';
		echo "[bed_occupancy_invariant_scan][{$status}] " . json_encode($res) . "\n";
		exit($status === 'ok' ? 0 : 1);
	}

	public function reconcile_pharmacy($date = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}

		$day = trim((string)$date);
		if ($day === '') {
			$day = date('Y-m-d', strtotime('-1 day'));
		}

		$this->load->model('app/pharmacy_reconciliation_model');
		$res = $this->pharmacy_reconciliation_model->reconcile_day($day);

		if (is_array($res) && !empty($res['ok'])) {
			echo "[pharmacy_reconcile][ok] date=" . (isset($res['date']) ? $res['date'] : $day) . " rows=" . (isset($res['rows']) ? $res['rows'] : 0) . " critical=" . (isset($res['critical']) ? $res['critical'] : 0) . " warning=" . (isset($res['warning']) ? $res['warning'] : 0) . "\n";
			return;
		}

		$err = is_array($res) && isset($res['error']) ? (string)$res['error'] : 'Unknown error';
		echo "[pharmacy_reconcile][error] date={$day} err={$err}\n";
	}

	public function shadow_exit_gate($days = 1)
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('shadow_exit_gate');

		if (isset($this->config)) {
			$this->config->load('shadow_governance_expectedset', true);
			$pkMap = $this->config->item('shadow_governance_primary_key_map', 'shadow_governance_expectedset');
			if (is_array($pkMap)) {
				$schemaRow = $this->db->query("SELECT DATABASE() AS db")->row();
				$schemaName = ($schemaRow && isset($schemaRow->db)) ? (string)$schemaRow->db : '';
				if ($schemaName !== '') {
					$needTables = array('iop_intake_record', 'iop_output_record');
					foreach ($needTables as $t) {
						if (!isset($pkMap[$t])) {
							continue;
						}
						$pkCol = (string)$pkMap[$t];
						if ($pkCol === '') {
							echo "[shadow_exit_gate][fail] pk_column_missing table={$t}\n";
							exit(1);
						}
						$colRow = $this->db->query(
							"SELECT EXTRA FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1",
							array($schemaName, $t, $pkCol)
						)->row();
						if (!$colRow || !isset($colRow->EXTRA)) {
							echo "[shadow_exit_gate][fail] pk_column_not_found table={$t} column={$pkCol}\n";
							exit(1);
						}
						$extra = (string)$colRow->EXTRA;
						if (stripos($extra, 'auto_increment') === false) {
							echo "[shadow_exit_gate][fail] pk_not_auto_increment table={$t} column={$pkCol} extra={$extra}\n";
							exit(1);
						}
					}
				}
			}
		}

		$nDays = (int)$days;
		if ($nDays <= 0) {
			$nDays = 1;
		}
		if ($nDays > 14) {
			$nDays = 14;
		}

		if (!isset($this->config)) {
			echo "[shadow_exit_gate][error] config_not_available\n";
			exit(2);
		}
		$this->config->load('shadow_audit', true);
		$table = (string)$this->config->item('shadow_audit_table', 'shadow_audit');
		if ($table === '') {
			$table = 'shadow_audit_log';
		}

		$this->config->load('shadow_exit_gate', true);
		$gov = $this->config->item('shadow_exit_gate_governed_endpoints', 'shadow_exit_gate');
		$allow = $this->config->item('shadow_exit_gate_unprovable_allowlist', 'shadow_exit_gate');
		$trendRequired = (bool)$this->config->item('shadow_exit_gate_trend_required', 'shadow_exit_gate');
		$trendDays = (int)$this->config->item('shadow_exit_gate_trend_days', 'shadow_exit_gate');
		$pkCodes = $this->config->item('shadow_exit_gate_pk_unprovable_codes', 'shadow_exit_gate');
		$pkAllowedMax = (int)$this->config->item('shadow_exit_gate_pk_unprovable_allowed_max', 'shadow_exit_gate');
		if (!is_array($gov)) {
			$gov = array();
		}
		if (!is_array($allow)) {
			$allow = array();
		}
		if (!is_array($pkCodes)) {
			$pkCodes = array();
		}
		if ($trendDays <= 0) {
			$trendDays = 3;
		}
		if ($trendDays > 14) {
			$trendDays = 14;
		}
		if ($pkAllowedMax < 0) {
			$pkAllowedMax = 0;
		}

		$govSet = array();
		foreach ($gov as $g) {
			if (!is_array($g) || !isset($g['controller'], $g['method'])) {
				continue;
			}
			$ctl = strtolower(trim((string)$g['controller']));
			$mtd = strtolower(trim((string)$g['method']));
			if ($ctl === '' || $mtd === '') {
				continue;
			}
			$govSet[$ctl . '/' . $mtd] = true;
		}

		$allowEntries = array();
		foreach ($allow as $a) {
			if (!is_array($a) || !isset($a['source'], $a['code'], $a['controller'], $a['method'])) {
				continue;
			}
			$src = strtoupper(trim((string)$a['source']));
			$code = strtoupper(trim((string)$a['code']));
			$ctl = strtolower(trim((string)$a['controller']));
			$mtd = strtolower(trim((string)$a['method']));
			if ($src === '' || $code === '' || $ctl === '' || $mtd === '') {
				continue;
			}
			$allowEntries[] = array(
				'source' => $src,
				'code' => $code,
				'controller' => $ctl,
				'method' => $mtd,
				'table' => isset($a['table']) ? strtolower(trim((string)$a['table'])) : '',
				'operation' => isset($a['operation']) ? strtoupper(trim((string)$a['operation'])) : '',
				'update_set_contains' => isset($a['update_set_contains']) ? (string)$a['update_set_contains'] : '',
				'reason_contains' => isset($a['reason_contains']) ? (string)$a['reason_contains'] : '',
			);
		}

		$matchAllowlist = function ($source, $code, $controller, $method, $writes) use ($allowEntries) {
			$source = strtoupper((string)$source);
			$code = strtoupper((string)$code);
			$controller = strtolower((string)$controller);
			$method = strtolower((string)$method);
			if (empty($allowEntries)) {
				return false;
			}
			if (!is_array($writes)) {
				$writes = array();
			}
			foreach ($allowEntries as $e) {
				if (!is_array($e)) {
					continue;
				}
				if (!isset($e['source'], $e['code'], $e['controller'], $e['method'])) {
					continue;
				}
				if ($e['source'] !== $source || $e['code'] !== $code || $e['controller'] !== $controller || $e['method'] !== $method) {
					continue;
				}
				$table = isset($e['table']) ? (string)$e['table'] : '';
				$op = isset($e['operation']) ? (string)$e['operation'] : '';
				$updateSetContains = isset($e['update_set_contains']) ? (string)$e['update_set_contains'] : '';
				$reasonContains = isset($e['reason_contains']) ? (string)$e['reason_contains'] : '';
				if ($table === '' && $op === '' && $updateSetContains === '' && $reasonContains === '') {
					return true;
				}
				foreach ($writes as $w) {
					if (!is_array($w)) {
						continue;
					}
					$wTable = isset($w['table']) ? strtolower((string)$w['table']) : '';
					$wOp = isset($w['operation']) ? strtoupper((string)$w['operation']) : '';
					$wUpdateSet = isset($w['update_set']) ? (string)$w['update_set'] : '';
					$wReason = isset($w['reason']) ? (string)$w['reason'] : '';
					if ($table !== '' && $table !== $wTable) {
						continue;
					}
					if ($op !== '' && $op !== $wOp) {
						continue;
					}
					if ($updateSetContains !== '' && ($wUpdateSet === '' || strpos($wUpdateSet, $updateSetContains) === false)) {
						continue;
					}
					if ($reasonContains !== '' && ($wReason === '' || strpos($wReason, $reasonContains) === false)) {
						continue;
					}
					return true;
				}
			}
			return false;
		};

		$since = new DateTime('now', new DateTimeZone(date_default_timezone_get()));
		$since->modify('-' . $nDays . ' day');
		$sinceStr = $since->format('Y-m-d H:i:s');

		$q = $this->db->query(
			"SELECT created_at, domain, intent, parity_status, parity_code, proof_status, proof_code, payload FROM {$table} WHERE created_at >= ? ORDER BY created_at ASC",
			array($sinceStr)
		);
		$rows = $q ? $q->result() : array();

		if (!class_exists('ShadowGovernanceSemantics', false)) {
			$sem_path = APPPATH.'libraries/ShadowGovernanceSemantics.php';
			if (file_exists($sem_path)) {
				require_once($sem_path);
			}
		}
		if (!class_exists('ShadowGovernanceSemantics', false)) {
			echo "[shadow_exit_gate][error] semantics_not_available\n";
			exit(2);
		}

		$counts = array(
			'DOMAIN_UNRESOLVED' => 0,
			'CAPTURE_GAP_DETECTED' => 0,
			'PROOF_SCOPE_UNKNOWN' => 0,
			'PARITY_VIOLATION' => 0,
			'PARITY_ERROR' => 0,
			'PROOF_VIOLATION' => 0,
			'PROOF_ERROR' => 0,
			'PK_UNPROVABLE_ALLOWLISTED' => 0,
			'PK_UNPROVABLE_NOT_ALLOWLISTED' => 0,
			'UNKNOWN_UNPROVABLE' => 0,
		);
		$samples = array(
			'DOMAIN_UNRESOLVED' => array(),
			'CAPTURE_GAP_DETECTED' => array(),
			'PROOF_SCOPE_UNKNOWN' => array(),
			'PARITY_VIOLATION' => array(),
			'PARITY_ERROR' => array(),
			'PROOF_VIOLATION' => array(),
			'PROOF_ERROR' => array(),
			'PK_UNPROVABLE_ALLOWLISTED' => array(),
			'PK_UNPROVABLE_NOT_ALLOWLISTED' => array(),
			'UNKNOWN_UNPROVABLE' => array(),
		);
		$daily = array();

		foreach ($rows as $r) {
			$created_at = isset($r->created_at) ? (string)$r->created_at : '';
			$day = $created_at !== '' ? substr($created_at, 0, 10) : 'unknown';
			if (!isset($daily[$day])) {
				$daily[$day] = array(
					'DOMAIN_UNRESOLVED' => 0,
					'CAPTURE_GAP_DETECTED' => 0,
					'PROOF_SCOPE_UNKNOWN' => 0,
					'PARITY_VIOLATION' => 0,
					'PARITY_ERROR' => 0,
					'PROOF_VIOLATION' => 0,
					'PROOF_ERROR' => 0,
					'PK_UNPROVABLE_ALLOWLISTED' => 0,
					'PK_UNPROVABLE_NOT_ALLOWLISTED' => 0,
					'UNKNOWN_UNPROVABLE' => 0,
				);
			}

			$payload = isset($r->payload) ? (string)$r->payload : '';
			$p = array();
			if ($payload !== '') {
				$tmp = json_decode($payload, true);
				if (is_array($tmp)) {
					$p = $tmp;
				}
			}
			$ev = (isset($p['event']) && is_array($p['event'])) ? $p['event'] : array();
			$ctl = isset($ev['controller']) ? strtolower((string)$ev['controller']) : '';
			$mtd = isset($ev['method']) ? strtolower((string)$ev['method']) : '';
			$writes = (isset($ev['tables_touched']) && is_array($ev['tables_touched'])) ? $ev['tables_touched'] : array();
			$endpoint = ($ctl !== '' && $mtd !== '') ? ($ctl . '/' . $mtd) : '';
			$isGov = ($endpoint !== '' && isset($govSet[$endpoint]));

			$capGap = !empty($ev['capture_gap_detected']);
			if ($capGap) {
				$missingRaw = isset($ev['capture_gap_missing']) ? $ev['capture_gap_missing'] : array();
				if (is_array($missingRaw) && !empty($missingRaw)) {
					$onlyInfraMissing = true;
					foreach ($missingRaw as $mk) {
						$mkStr = strtolower(trim((string)$mk));
						if ($mkStr === '' || strpos($mkStr, 'ci_sessions:') !== 0) {
							$onlyInfraMissing = false;
							break;
						}
					}
					if ($onlyInfraMissing) {
						$capGap = false;
					}
				}
			}
			if ($capGap) {
				$counts['CAPTURE_GAP_DETECTED']++;
				$daily[$day]['CAPTURE_GAP_DETECTED']++;
				if (count($samples['CAPTURE_GAP_DETECTED']) < 5) {
					$samples['CAPTURE_GAP_DETECTED'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'missing' => isset($ev['capture_gap_missing']) ? $ev['capture_gap_missing'] : null);
				}
			}

			$parity_status_raw = isset($r->parity_status) ? (string)$r->parity_status : '';
			$parity_status = ShadowGovernanceSemantics::normalizeStatus($parity_status_raw);
			$parity_code = isset($r->parity_code) ? strtoupper((string)$r->parity_code) : '';
			if ($parity_status === 'VIOLATION') {
				$ignoreParity = false;
				if ($parity_code === 'WRITE_NOT_ALLOWED') {
					$tables = is_array($writes) ? $writes : array();
					if (!empty($tables)) {
						$allowedClinical = array('iop_intake_record' => true, 'iop_output_record' => true);
						$hasInfra = false;
						$hasUnexpected = false;
						foreach ($tables as $w) {
							if (!is_array($w) || !isset($w['table'])) {
								$hasUnexpected = true;
								break;
							}
							$wTable = strtolower(trim((string)$w['table']));
							if ($wTable === '') {
								$hasUnexpected = true;
								break;
							}
							if ($wTable === 'ci_sessions') {
								$hasInfra = true;
								continue;
							}
							if (!isset($allowedClinical[$wTable])) {
								$hasUnexpected = true;
								break;
							}
						}
						if ($hasInfra && !$hasUnexpected) {
							$ignoreParity = true;
						}
					}
				}
				if (!$ignoreParity) {
					$counts['PARITY_VIOLATION']++;
					$daily[$day]['PARITY_VIOLATION']++;
					if (count($samples['PARITY_VIOLATION']) < 5) {
						$samples['PARITY_VIOLATION'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'code' => $parity_code);
					}
				}
			}
			if ($parity_status === 'ERROR') {
				$counts['PARITY_ERROR']++;
				$daily[$day]['PARITY_ERROR']++;
				if (count($samples['PARITY_ERROR']) < 5) {
					$samples['PARITY_ERROR'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'code' => $parity_code);
				}
			}
			if ($parity_status === 'UNPROVABLE') {
				if ($parity_code === 'DOMAIN_UNRESOLVED') {
					if ($isGov) {
						$counts['DOMAIN_UNRESOLVED']++;
						$daily[$day]['DOMAIN_UNRESOLVED']++;
						if (count($samples['DOMAIN_UNRESOLVED']) < 5) {
							$samples['DOMAIN_UNRESOLVED'][] = array('at' => $created_at, 'endpoint' => $endpoint);
						}
					}
				} else {
					$isPk = in_array($parity_code, $pkCodes, true);
					if ($isPk) {
						if ($matchAllowlist('PARITY', $parity_code, $ctl, $mtd, $writes)) {
							$counts['PK_UNPROVABLE_ALLOWLISTED']++;
							$daily[$day]['PK_UNPROVABLE_ALLOWLISTED']++;
							if (count($samples['PK_UNPROVABLE_ALLOWLISTED']) < 5) {
								$samples['PK_UNPROVABLE_ALLOWLISTED'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'source' => 'PARITY', 'code' => $parity_code);
							}
						} else {
							$counts['PK_UNPROVABLE_NOT_ALLOWLISTED']++;
							$daily[$day]['PK_UNPROVABLE_NOT_ALLOWLISTED']++;
							if (count($samples['PK_UNPROVABLE_NOT_ALLOWLISTED']) < 5) {
								$samples['PK_UNPROVABLE_NOT_ALLOWLISTED'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'source' => 'PARITY', 'code' => $parity_code);
							}
						}
					} else {
						$counts['UNKNOWN_UNPROVABLE']++;
						$daily[$day]['UNKNOWN_UNPROVABLE']++;
						if (count($samples['UNKNOWN_UNPROVABLE']) < 5) {
							$samples['UNKNOWN_UNPROVABLE'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'source' => 'PARITY', 'code' => $parity_code);
						}
					}
				}
			}

			$proof_status = '';
			$proof_code = '';
			if ($parity_status === 'PASS') {
				$proof_status_raw = isset($r->proof_status) ? (string)$r->proof_status : '';
				$proof_status = $proof_status_raw !== '' ? ShadowGovernanceSemantics::normalizeStatus($proof_status_raw) : 'UNPROVABLE';
				$proof_code = isset($r->proof_code) ? strtoupper((string)$r->proof_code) : '';
				if ($proof_code === '' && $proof_status === 'UNPROVABLE') {
					$proof_code = 'PROOF_MISSING';
				}

				if ($proof_status === 'VIOLATION') {
					$counts['PROOF_VIOLATION']++;
					$daily[$day]['PROOF_VIOLATION']++;
					if (count($samples['PROOF_VIOLATION']) < 5) {
						$samples['PROOF_VIOLATION'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'code' => $proof_code);
					}
				}
				if ($proof_status === 'ERROR') {
					$counts['PROOF_ERROR']++;
					$daily[$day]['PROOF_ERROR']++;
					if (count($samples['PROOF_ERROR']) < 5) {
						$samples['PROOF_ERROR'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'code' => $proof_code);
					}
				}
				if ($proof_status === 'UNPROVABLE') {
					if ($proof_code === 'PROOF_SCOPE_UNKNOWN') {
						$counts['PROOF_SCOPE_UNKNOWN']++;
						$daily[$day]['PROOF_SCOPE_UNKNOWN']++;
						if (count($samples['PROOF_SCOPE_UNKNOWN']) < 5) {
							$samples['PROOF_SCOPE_UNKNOWN'][] = array('at' => $created_at, 'endpoint' => $endpoint);
						}
					} else {
						$counts['UNKNOWN_UNPROVABLE']++;
						$daily[$day]['UNKNOWN_UNPROVABLE']++;
						if (count($samples['UNKNOWN_UNPROVABLE']) < 5) {
							$samples['UNKNOWN_UNPROVABLE'][] = array('at' => $created_at, 'endpoint' => $endpoint, 'source' => 'PROOF', 'code' => $proof_code);
						}
					}
				}
			}
		}

		$pkOverBudget = ($counts['PK_UNPROVABLE_ALLOWLISTED'] > $pkAllowedMax);
		$fail = (
			$counts['DOMAIN_UNRESOLVED'] > 0
			|| $counts['CAPTURE_GAP_DETECTED'] > 0
			|| $counts['PROOF_SCOPE_UNKNOWN'] > 0
			|| $counts['PARITY_VIOLATION'] > 0
			|| $counts['PARITY_ERROR'] > 0
			|| $counts['PROOF_VIOLATION'] > 0
			|| $counts['PROOF_ERROR'] > 0
			|| $counts['PK_UNPROVABLE_NOT_ALLOWLISTED'] > 0
			|| $pkOverBudget
			|| $counts['UNKNOWN_UNPROVABLE'] > 0
		);

		echo "[shadow_exit_gate] since={$sinceStr} rows=" . count($rows) . " governed=" . count($govSet) . " trend_required=" . ($trendRequired ? '1' : '0') . " trend_days={$trendDays} pk_allowed_max={$pkAllowedMax}\n";
		echo "[shadow_exit_gate] DOMAIN_UNRESOLVED=" . $counts['DOMAIN_UNRESOLVED'] . " CAPTURE_GAP_DETECTED=" . $counts['CAPTURE_GAP_DETECTED'] . " PROOF_SCOPE_UNKNOWN=" . $counts['PROOF_SCOPE_UNKNOWN'] . " PARITY_VIOLATION=" . $counts['PARITY_VIOLATION'] . " PARITY_ERROR=" . $counts['PARITY_ERROR'] . " PROOF_VIOLATION=" . $counts['PROOF_VIOLATION'] . " PROOF_ERROR=" . $counts['PROOF_ERROR'] . " PK_UNPROVABLE_ALLOWLISTED=" . $counts['PK_UNPROVABLE_ALLOWLISTED'] . " PK_UNPROVABLE_NOT_ALLOWLISTED=" . $counts['PK_UNPROVABLE_NOT_ALLOWLISTED'] . " UNKNOWN_UNPROVABLE=" . $counts['UNKNOWN_UNPROVABLE'] . "\n";

		ksort($daily);
		foreach ($daily as $d => $c) {
			$totUnprovable = (
				$c['DOMAIN_UNRESOLVED']
				+ $c['PROOF_SCOPE_UNKNOWN']
				+ $c['PK_UNPROVABLE_ALLOWLISTED']
				+ $c['PK_UNPROVABLE_NOT_ALLOWLISTED']
				+ $c['UNKNOWN_UNPROVABLE']
			);
			echo "[shadow_exit_gate][day] {$d} DOMAIN_UNRESOLVED=" . $c['DOMAIN_UNRESOLVED'] . " CAPTURE_GAP_DETECTED=" . $c['CAPTURE_GAP_DETECTED'] . " PROOF_SCOPE_UNKNOWN=" . $c['PROOF_SCOPE_UNKNOWN'] . " PARITY_VIOLATION=" . $c['PARITY_VIOLATION'] . " PARITY_ERROR=" . $c['PARITY_ERROR'] . " PROOF_VIOLATION=" . $c['PROOF_VIOLATION'] . " PROOF_ERROR=" . $c['PROOF_ERROR'] . " PK_UNPROVABLE_ALLOWLISTED=" . $c['PK_UNPROVABLE_ALLOWLISTED'] . " PK_UNPROVABLE_NOT_ALLOWLISTED=" . $c['PK_UNPROVABLE_NOT_ALLOWLISTED'] . " UNKNOWN_UNPROVABLE=" . $c['UNKNOWN_UNPROVABLE'] . " TOTAL_UNPROVABLE=" . $totUnprovable . "\n";
		}

		if ($trendRequired) {
			$trendKeys = array();
			foreach (array_keys($daily) as $k) {
				if ($k === 'unknown') {
					continue;
				}
				$trendKeys[] = $k;
			}
			$trendKeys = array_slice($trendKeys, max(0, count($trendKeys) - $trendDays));
			$prev = null;
			$trendOk = true;
			foreach ($trendKeys as $k) {
				$c = $daily[$k];
				$tot = (
					$c['DOMAIN_UNRESOLVED']
					+ $c['CAPTURE_GAP_DETECTED']
					+ $c['PROOF_SCOPE_UNKNOWN']
					+ $c['PK_UNPROVABLE_ALLOWLISTED']
					+ $c['PK_UNPROVABLE_NOT_ALLOWLISTED']
					+ $c['UNKNOWN_UNPROVABLE']
				);
				if ($prev !== null && $tot > $prev) {
					$trendOk = false;
				}
				$prev = $tot;
			}
			if (!$trendOk) {
				$fail = true;
				echo "[shadow_exit_gate][trend][FAIL] non_decreasing\n";
			}
		}

		if ($fail) {
			foreach ($samples as $k => $arr) {
				if (!empty($arr)) {
					echo "[shadow_exit_gate][sample] {$k}=" . json_encode($arr) . "\n";
				}
			}
			echo "[shadow_exit_gate][FAIL]\n";
			exit(1);
		}

		echo "[shadow_exit_gate][PASS]\n";
		exit(0);
	}

	public function shadow_audit_immutability_apply()
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('shadow_audit_immutability_apply');
		if (!isset($this->config)) {
			echo "[shadow_audit_immutability_apply][error] config_not_available\n";
			exit(2);
		}
		$this->config->load('shadow_audit', true);
		$table = (string)$this->config->item('shadow_audit_table', 'shadow_audit');
		if ($table === '') {
			$table = 'shadow_audit_log';
		}
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
			echo "[shadow_audit_immutability_apply][error] invalid_table_name\n";
			exit(2);
		}

		$createSql = "CREATE TABLE IF NOT EXISTS `{$table}` (\n"
			. "  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,\n"
			. "  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,\n"
			. "  `domain` VARCHAR(64) NOT NULL,\n"
			. "  `intent` VARCHAR(64) NOT NULL,\n"
			. "  `parity_status` VARCHAR(16) NOT NULL,\n"
			. "  `parity_code` VARCHAR(64) NULL,\n"
			. "  `proof_status` VARCHAR(16) NULL,\n"
			. "  `proof_code` VARCHAR(64) NULL,\n"
			. "  `severity` VARCHAR(16) NOT NULL,\n"
			. "  `request_id` VARCHAR(128) NULL,\n"
			. "  `user_id` VARCHAR(64) NULL,\n"
			. "  `payload` LONGTEXT NOT NULL,\n"
			. "  PRIMARY KEY (`id`),\n"
			. "  KEY `idx_created_at` (`created_at`),\n"
			. "  KEY `idx_domain_intent` (`domain`,`intent`),\n"
			. "  KEY `idx_severity` (`severity`)\n"
			. ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

		$ok = $this->db->query($createSql);
		if (!$ok) {
			echo "[shadow_audit_immutability_apply][error] create_table_failed\n";
			exit(1);
		}
		echo "[shadow_audit_immutability_apply][ok] table_ready={$table}\n";

		$schemaRow = $this->db->query("SELECT DATABASE() AS db")->row();
		$schemaName = ($schemaRow && isset($schemaRow->db)) ? (string)$schemaRow->db : '';
		if ($schemaName === '') {
			echo "[shadow_audit_immutability_apply][error] db_name_unavailable\n";
			exit(2);
		}

		$triggers = array(
			"trg_{$table}_no_update" => "CREATE TRIGGER `trg_{$table}_no_update` BEFORE UPDATE ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'",
			"trg_{$table}_no_delete" => "CREATE TRIGGER `trg_{$table}_no_delete` BEFORE DELETE ON `{$table}` FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$table} is append-only'",
		);

		$created = 0;
		$skipped = 0;
		$errors = 0;
		foreach ($triggers as $tName => $tSql) {
			$existsQ = $this->db->query(
				"SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? AND TRIGGER_NAME = ? LIMIT 1",
				array($schemaName, $tName)
			);
			$exists = $existsQ && $existsQ->row();
			if ($exists) {
				$skipped++;
				echo "[shadow_audit_immutability_apply][skip] trigger_exists={$tName}\n";
				continue;
			}
			$tOk = $this->db->query($tSql);
			if ($tOk) {
				$created++;
				echo "[shadow_audit_immutability_apply][ok] trigger_created={$tName}\n";
			} else {
				$errors++;
				echo "[shadow_audit_immutability_apply][error] trigger_create_failed={$tName}\n";
			}
		}

		echo "[shadow_audit_immutability_apply] created={$created} skipped={$skipped} errors={$errors}\n";
		exit($errors > 0 ? 1 : 0);
	}

	public function shadow_audit_immutability_verify()
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('shadow_audit_immutability_verify');
		if (!isset($this->config)) {
			echo "[shadow_audit_immutability_verify][error] config_not_available\n";
			exit(2);
		}
		$this->config->load('shadow_audit', true);
		$table = (string)$this->config->item('shadow_audit_table', 'shadow_audit');
		if ($table === '') {
			$table = 'shadow_audit_log';
		}
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
			echo "[shadow_audit_immutability_verify][error] invalid_table_name\n";
			exit(2);
		}

		$schemaRow = $this->db->query("SELECT DATABASE() AS db")->row();
		$schemaName = ($schemaRow && isset($schemaRow->db)) ? (string)$schemaRow->db : '';
		if ($schemaName === '') {
			echo "[shadow_audit_immutability_verify][error] db_name_unavailable\n";
			exit(2);
		}

		$existsQ = $this->db->query(
			"SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1",
			array($schemaName, $table)
		);
		if (!$existsQ || !$existsQ->row()) {
			echo "[shadow_audit_immutability_verify][fail] table_missing={$table}\n";
			exit(1);
		}

		$engineRow = $this->db->query(
			"SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1",
			array($schemaName, $table)
		)->row();
		$engine = ($engineRow && isset($engineRow->ENGINE)) ? (string)$engineRow->ENGINE : '';
		echo "[shadow_audit_immutability_verify] table={$table} engine={$engine}\n";

		$need = array(
			"trg_{$table}_no_update",
			"trg_{$table}_no_delete",
		);
		$missing = array();
		foreach ($need as $tName) {
			$q = $this->db->query(
				"SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = ? AND TRIGGER_NAME = ? LIMIT 1",
				array($schemaName, $tName)
			);
			if (!$q || !$q->row()) {
				$missing[] = $tName;
			}
		}

		if (!empty($missing)) {
			echo "[shadow_audit_immutability_verify][fail] missing_triggers=" . json_encode($missing) . "\n";
			exit(1);
		}

		echo "[shadow_audit_immutability_verify][pass]\n";
		exit(0);
	}

	public function clinical_replay_smoke($iopId = '', $mode = 'patient', $arg1 = '', $arg2 = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('clinical_replay_smoke');

		$iopId = trim((string)$iopId);
		$mode = strtolower(trim((string)$mode));
		if ($iopId === '') {
			echo "[clinical_replay_smoke][error] missing_iop_id\n";
			echo "[clinical_replay_smoke][usage] php index.php cron clinical_replay_smoke IP-000025 [patient|time|shift] [cutoff|shift_id] [shift_date]\n";
			exit(2);
		}

		$required = array('clinical_events');
		foreach ($required as $table) {
			if (!$this->clinicalReplayTableExists($table)) {
				echo "[clinical_replay_smoke][not_ready] table_missing={$table}\n";
				exit(1);
			}
		}

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			echo "[clinical_replay_smoke][error] factory_missing\n";
			exit(2);
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		$repo = new CiClinicalEventRepository();
		$hydrator = new CiDomainEventHydrator();
		$resolver = new CorrectionResolver();
		$builder = new EffectiveStateBuilder();
		$engine = new ClinicalReplayEngine($repo, $hydrator, $resolver, $builder);

		if ($mode === '') {
			$mode = 'patient';
		}

		if ($mode === 'time') {
			$cutoff = trim((string)$arg1);
			if ($cutoff === '') {
				echo "[clinical_replay_smoke][error] missing_cutoff\n";
				exit(2);
			}
			$result = $engine->replayAtTime($iopId, $cutoff);
			$raw = $repo->getStreamAtTime($iopId, $cutoff);
		} elseif ($mode === 'shift') {
			$shiftId = trim((string)$arg1);
			$shiftDate = trim((string)$arg2);
			if ($shiftId === '' || $shiftDate === '') {
				echo "[clinical_replay_smoke][error] missing_shift_args\n";
				exit(2);
			}
			$result = $engine->replayShift($iopId, $shiftId, $shiftDate);
			$raw = $repo->getStreamForShift($iopId, $shiftId, $shiftDate);
		} else {
			$mode = 'patient';
			$result = $engine->replayPatient($iopId);
			$raw = $repo->getStreamByIopId($iopId);
		}

		$hydratedRaw = $hydrator->hydrate($raw);
		$chains = $resolver->buildCorrectionChains($hydratedRaw);
		$anomalies = $this->clinicalReplayCollectAnomalies($hydratedRaw);
		$timeline = isset($result->timeline) && is_array($result->timeline) ? $result->timeline : array();
		$vitals = isset($result->vitals) && is_array($result->vitals) ? $result->vitals : array();
		$balance = isset($result->balance) ? $result->balance : 0;

		echo "[clinical_replay_smoke] iop_id={$iopId} mode={$mode}\n";
		echo "[clinical_replay_smoke] raw_events=" . count($raw) . " effective_events=" . count($timeline) . " vitals=" . count($vitals) . " correction_chains=" . count($chains) . " balance=" . $balance . "\n";
		echo "[clinical_replay_smoke] anomalies=" . json_encode($anomalies) . "\n";
		echo "[clinical_replay_smoke][done]\n";
		exit(empty($anomalies) ? 0 : 1);
	}

	public function medication_probe($iopId = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('medication_probe');

		$iopId = trim((string)$iopId);
		if ($iopId === '') {
			echo "[medication_probe][error] missing_iop_id\n";
			echo "[medication_probe][usage] php index.php cron medication_probe IP-000026\n";
			exit(2);
		}
		if (!$this->db->table_exists('iop_medication')) {
			echo "[medication_probe][not_ready] table_missing=iop_medication\n";
			exit(1);
		}

		$this->db->select('iop_med_id,iop_id,medicine_id,medicine_text,frequency,instruction,advice,days,total_qty,cPreparedBy,dDate', false);
		$this->db->where(array('iop_id' => $iopId, 'InActive' => 0));
		$this->db->order_by('iop_med_id', 'ASC');
		$rows = $this->db->get('iop_medication')->result_array();
		foreach ($rows as &$r) {
			$instr = array_key_exists('instruction', $r) && $r['instruction'] !== null ? (string)$r['instruction'] : '';
			$adv = array_key_exists('advice', $r) && $r['advice'] !== null ? (string)$r['advice'] : '';
			$r['instruction_len'] = strlen($instr);
			$r['advice_len'] = strlen($adv);
		}
		unset($r);

		echo "[medication_probe] iop_id={$iopId} rows=" . count($rows) . "\n";
		echo json_encode($rows) . "\n";
		exit(0);
	}

	public function table_schema_probe($table = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('table_schema_probe');

		$table = trim((string)$table);
		if ($table === '') {
			echo "[table_schema_probe][error] missing_table\n";
			echo "[table_schema_probe][usage] php index.php cron table_schema_probe iop_progress_note\n";
			exit(2);
		}
		if (!$this->db->table_exists($table)) {
			echo "[table_schema_probe][not_ready] table_missing={$table}\n";
			exit(1);
		}

		$q = $this->db->query("SHOW COLUMNS FROM `{$table}`");
		$cols = $q ? $q->result_array() : array();
		echo "[table_schema_probe] table={$table} cols=" . count($cols) . "\n";
		echo json_encode($cols) . "\n";
		exit(0);
	}

	public function clinical_replay_determinism($iopId = '', $mode = 'patient', $arg1 = '', $arg2 = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('clinical_replay_determinism');

		$iopId = trim((string)$iopId);
		$mode = strtolower(trim((string)$mode));
		if ($iopId === '') {
			echo "[clinical_replay_determinism][error] missing_iop_id\n";
			echo "[clinical_replay_determinism][usage] php index.php cron clinical_replay_determinism IP-000025 [patient|time|shift] [cutoff|shift_id] [shift_date]\n";
			exit(2);
		}

		if (!$this->clinicalReplayTableExists('clinical_events')) {
			echo "[clinical_replay_determinism][not_ready] table_missing=clinical_events\n";
			exit(1);
		}

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			echo "[clinical_replay_determinism][error] factory_missing\n";
			exit(2);
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		try {
			$first = $this->clinicalReplayRunPayload($iopId, $mode, $arg1, $arg2);
			$second = $this->clinicalReplayRunPayload($iopId, $mode, $arg1, $arg2);
		} catch (InvalidArgumentException $e) {
			echo "[clinical_replay_determinism][error] " . $e->getMessage() . "\n";
			exit(2);
		}
		$firstHash = hash('sha256', $this->clinicalReplayCanonicalJson($first));
		$secondHash = hash('sha256', $this->clinicalReplayCanonicalJson($second));
		$firstFingerprint = $this->clinicalReplayEventFingerprint($first);
		$secondFingerprint = $this->clinicalReplayEventFingerprint($second);
		$firstFingerprintHash = hash('sha256', $this->clinicalReplayCanonicalJson($firstFingerprint));
		$secondFingerprintHash = hash('sha256', $this->clinicalReplayCanonicalJson($secondFingerprint));
		$streamPass = hash_equals($firstFingerprintHash, $secondFingerprintHash);
		$outputPass = hash_equals($firstHash, $secondHash);
		$pass = $streamPass && $outputPass;

		echo "[clinical_replay_determinism] iop_id={$iopId} mode={$mode}\n";
		echo "[clinical_replay_determinism] first_stream_fingerprint={$firstFingerprintHash}\n";
		echo "[clinical_replay_determinism] second_stream_fingerprint={$secondFingerprintHash}\n";
		echo "[clinical_replay_determinism] first_hash={$firstHash}\n";
		echo "[clinical_replay_determinism] second_hash={$secondHash}\n";
		echo $streamPass ? "[clinical_replay_determinism][stream_integrity_pass]\n" : "[clinical_replay_determinism][stream_integrity_fail]\n";
		echo $outputPass ? "[clinical_replay_determinism][output_integrity_pass]\n" : "[clinical_replay_determinism][output_integrity_fail]\n";
		echo $pass ? "[clinical_replay_determinism][pass]\n" : "[clinical_replay_determinism][fail]\n";
		exit($pass ? 0 : 1);
	}

	public function clinical_intake_simulation($iopId = '', $patientNo = '', $idempotencyKey = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('clinical_intake_simulation');
		$this->config->load('clinical_runtime', true);

		$enabled = (bool)$this->config->item('clinical_ctm_enabled', 'clinical_runtime');
		if (!$enabled) {
			echo "[clinical_intake_simulation][disabled] clinical_ctm_enabled=false\n";
			exit(1);
		}

		$iopId = trim((string)$iopId);
		$patientNo = trim((string)$patientNo);
		$idempotencyKey = trim((string)$idempotencyKey);
		if ($iopId === '' || $idempotencyKey === '') {
			echo "[clinical_intake_simulation][error] missing_required_args\n";
			echo "[clinical_intake_simulation][usage] php index.php cron clinical_intake_simulation IP-000025 PAT-000001 test-key-001\n";
			exit(2);
		}

		$required = $this->config->item('clinical_required_write_tables', 'clinical_runtime');
		if (!is_array($required)) {
			$required = array('clinical_events', 'clinical_idempotency_records', 'clinical_stream_locks', 'nursing_intake');
		}
		foreach ($required as $table) {
			if (!$this->clinicalReplayTableExists($table)) {
				echo "[clinical_intake_simulation][not_ready] table_missing={$table}\n";
				exit(1);
			}
		}

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			echo "[clinical_intake_simulation][error] factory_missing\n";
			exit(2);
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		$leaseSeconds = (int)$this->config->item('clinical_ctm_lease_seconds', 'clinical_runtime');
		$prefix = (string)$this->config->item('clinical_ctm_lease_owner_prefix', 'clinical_runtime');
		if ($prefix === '') {
			$prefix = 'hms-clinical-cli';
		}
		$leaseOwner = $prefix . ':' . getmypid();

		try {
			$service = new IntakeService(
				new CiClinicalTransactionManager($this->db, $leaseSeconds, $leaseOwner),
				new CiClinicalEventWriter($this->db)
			);
			$result = $service->record(array(
				'iop_id' => $iopId,
				'patient_no' => $patientNo,
				'actor_user_id' => 'CLI',
				'idempotency_key' => $idempotencyKey,
				'particulars' => 'CLI intake simulation',
				'oral_ml' => 1,
				'iv_fluids_ml' => 0,
				'blood_ml' => 0,
				'recorded_at' => date('Y-m-d H:i:s'),
				'created_at' => date('Y-m-d H:i:s'),
			));
		} catch (Exception $e) {
			echo "[clinical_intake_simulation][fail] " . $e->getMessage() . "\n";
			exit(1);
		}

		$out = is_object($result) && method_exists($result, 'toArray') ? $result->toArray() : $result;
		echo "[clinical_intake_simulation][ok] " . json_encode($out) . "\n";
		exit(0);
	}

	public function clinical_ctm_validate($mode = 'gate', $iopId = '', $idempotencyKey = '')
	{
		if (!is_cli()) {
			show_404();
			return;
		}
		$this->shadowEnsureDbOrExit('clinical_ctm_validate');
		$this->config->load('clinical_runtime', true);

		$mode = strtolower(trim((string)$mode));
		if ($mode === '') {
			$mode = 'gate';
		}

		if ($mode === 'constraints') {
			$this->clinicalCtmValidateConstraints();
		}

		$enabled = (bool)$this->config->item('clinical_ctm_enabled', 'clinical_runtime');
		if (!$enabled) {
			echo "[clinical_ctm_validate][disabled] clinical_ctm_enabled=false\n";
			exit($mode === 'gate' ? 0 : 1);
		}

		$required = $this->config->item('clinical_required_write_tables', 'clinical_runtime');
		if (!is_array($required)) {
			$required = array('clinical_events', 'clinical_idempotency_records', 'clinical_stream_locks', 'nursing_intake');
		}
		foreach ($required as $table) {
			if (!$this->clinicalReplayTableExists($table)) {
				echo "[clinical_ctm_validate][not_ready] table_missing={$table}\n";
				exit(1);
			}
		}

		$factory = APPPATH . 'services/Clinical/Support/ClinicalReplayFactory.php';
		if (!file_exists($factory)) {
			echo "[clinical_ctm_validate][error] factory_missing\n";
			exit(2);
		}
		require_once($factory);
		ClinicalReplayFactory::loadDependencies();

		if ($mode === 'gate') {
			echo "[clinical_ctm_validate][gate_pass]\n";
			exit(0);
		}

		$iopId = trim((string)$iopId);
		$idempotencyKey = trim((string)$idempotencyKey);
		if ($iopId === '' || $idempotencyKey === '') {
			echo "[clinical_ctm_validate][error] missing_required_args\n";
			echo "[clinical_ctm_validate][usage] php index.php cron clinical_ctm_validate [gate|constraints|idempotency|rollback|lease_hold] IP-000025 test-key-001\n";
			exit(2);
		}

		$leaseSeconds = (int)$this->config->item('clinical_ctm_lease_seconds', 'clinical_runtime');
		$prefix = (string)$this->config->item('clinical_ctm_lease_owner_prefix', 'clinical_runtime');
		if ($prefix === '') {
			$prefix = 'hms-clinical-cli';
		}
		$ctm = new CiClinicalTransactionManager($this->db, $leaseSeconds, $prefix . ':' . getmypid());

		try {
			if ($mode === 'idempotency') {
				$first = $ctm->execute($iopId, $idempotencyKey, function () {
					return array('probe' => 'idempotency', 'value' => uniqid('ctm_', true));
				});
				$second = $ctm->execute($iopId, $idempotencyKey, function () {
					return array('probe' => 'idempotency', 'value' => uniqid('ctm_', true));
				});
				$pass = json_encode($first) === json_encode($second);
				echo "[clinical_ctm_validate] first=" . json_encode($first) . "\n";
				echo "[clinical_ctm_validate] second=" . json_encode($second) . "\n";
				echo $pass ? "[clinical_ctm_validate][idempotency_pass]\n" : "[clinical_ctm_validate][idempotency_fail]\n";
				exit($pass ? 0 : 1);
			}
			if ($mode === 'rollback') {
				try {
					$ctm->execute($iopId, $idempotencyKey, function () {
						throw new RuntimeException('forced_ctm_rollback_probe');
					});
				} catch (RuntimeException $e) {
					if ($e->getMessage() !== 'forced_ctm_rollback_probe') {
						throw $e;
					}
					echo "[clinical_ctm_validate][rollback_pass]\n";
					exit(0);
				}
				echo "[clinical_ctm_validate][rollback_fail]\n";
				exit(1);
			}
			if ($mode === 'lease_hold') {
				$holdSeconds = (int)$this->input->get('seconds');
				if ($holdSeconds <= 0) {
					$holdSeconds = 10;
				}
				if ($holdSeconds > 60) {
					$holdSeconds = 60;
				}
				$result = $ctm->execute($iopId, $idempotencyKey, function () use ($holdSeconds) {
					sleep($holdSeconds);
					return array('probe' => 'lease_hold', 'held_seconds' => $holdSeconds);
				});
				echo "[clinical_ctm_validate][lease_hold_ok] " . json_encode($result) . "\n";
				exit(0);
			}

			echo "[clinical_ctm_validate][error] unknown_mode={$mode}\n";
			exit(2);
		} catch (Exception $e) {
			echo "[clinical_ctm_validate][fail] " . $e->getMessage() . "\n";
			exit(1);
		}
	}

	protected function clinicalCtmValidateConstraints()
	{
		$pass = true;
		$requiredTables = $this->config->item('clinical_required_write_tables', 'clinical_runtime');
		if (!is_array($requiredTables)) {
			$requiredTables = array('clinical_events', 'clinical_idempotency_records', 'clinical_stream_locks', 'nursing_intake');
		}
		foreach ($requiredTables as $table) {
			$exists = $this->clinicalReplayTableExists($table);
			echo $exists ? "[clinical_ctm_validate][table_ok] {$table}\n" : "[clinical_ctm_validate][table_missing] {$table}\n";
			if (!$exists) {
				$pass = false;
			}
		}

		$uniqueConstraints = $this->config->item('clinical_required_unique_constraints', 'clinical_runtime');
		if (!is_array($uniqueConstraints)) {
			$uniqueConstraints = array();
		}
		foreach ($uniqueConstraints as $constraint) {
			$table = isset($constraint['table']) ? (string)$constraint['table'] : '';
			$columns = isset($constraint['columns']) && is_array($constraint['columns']) ? $constraint['columns'] : array();
			$exists = $this->clinicalCtmUniqueConstraintExists($table, $columns);
			echo $exists ? "[clinical_ctm_validate][unique_ok] {$table}(" . implode(',', $columns) . ")\n" : "[clinical_ctm_validate][unique_missing] {$table}(" . implode(',', $columns) . ")\n";
			if (!$exists) {
				$pass = false;
			}
		}

		$foreignKeys = $this->config->item('clinical_required_foreign_keys', 'clinical_runtime');
		if (!is_array($foreignKeys)) {
			$foreignKeys = array();
		}
		foreach ($foreignKeys as $fk) {
			$exists = $this->clinicalCtmForeignKeyExists($fk);
			$table = isset($fk['table']) ? (string)$fk['table'] : '';
			$column = isset($fk['column']) ? (string)$fk['column'] : '';
			$refTable = isset($fk['referenced_table']) ? (string)$fk['referenced_table'] : '';
			$refColumn = isset($fk['referenced_column']) ? (string)$fk['referenced_column'] : '';
			echo $exists ? "[clinical_ctm_validate][fk_ok] {$table}.{$column}->{$refTable}.{$refColumn}\n" : "[clinical_ctm_validate][fk_missing] {$table}.{$column}->{$refTable}.{$refColumn}\n";
			if (!$exists) {
				$pass = false;
			}
		}

		echo $pass ? "[clinical_ctm_validate][constraints_pass]\n" : "[clinical_ctm_validate][constraints_fail]\n";
		exit($pass ? 0 : 1);
	}

	protected function clinicalCtmUniqueConstraintExists($table, array $columns)
	{
		$table = (string)$table;
		$columns = array_values(array_map('strval', $columns));
		if ($table === '' || empty($columns)) {
			return false;
		}
		$schemaName = $this->clinicalCurrentDatabaseName();
		if ($schemaName === '') {
			return false;
		}
		$q = $this->db->query(
			"SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
			FROM information_schema.STATISTICS
			WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND NON_UNIQUE = 0
			GROUP BY INDEX_NAME",
			array($schemaName, $table)
		);
		if (!$q) {
			return false;
		}
		$expected = implode(',', $columns);
		foreach ($q->result_array() as $row) {
			$actual = isset($row['cols']) ? (string)$row['cols'] : '';
			if ($actual === $expected) {
				return true;
			}
		}
		return false;
	}

	protected function clinicalCtmForeignKeyExists(array $fk)
	{
		$table = isset($fk['table']) ? (string)$fk['table'] : '';
		$column = isset($fk['column']) ? (string)$fk['column'] : '';
		$refTable = isset($fk['referenced_table']) ? (string)$fk['referenced_table'] : '';
		$refColumn = isset($fk['referenced_column']) ? (string)$fk['referenced_column'] : '';
		if ($table === '' || $column === '' || $refTable === '' || $refColumn === '') {
			return false;
		}
		$schemaName = $this->clinicalCurrentDatabaseName();
		if ($schemaName === '') {
			return false;
		}
		$q = $this->db->query(
			"SELECT 1 AS ok
			FROM information_schema.KEY_COLUMN_USAGE
			WHERE TABLE_SCHEMA = ?
			AND TABLE_NAME = ?
			AND COLUMN_NAME = ?
			AND REFERENCED_TABLE_SCHEMA = ?
			AND REFERENCED_TABLE_NAME = ?
			AND REFERENCED_COLUMN_NAME = ?
			LIMIT 1",
			array($schemaName, $table, $column, $schemaName, $refTable, $refColumn)
		);
		return $q && $q->row();
	}

	protected function clinicalReplayRunPayload($iopId, $mode, $arg1, $arg2)
	{
		$repo = new CiClinicalEventRepository();
		$hydrator = new CiDomainEventHydrator();
		$resolver = new CorrectionResolver();
		$builder = new EffectiveStateBuilder();
		$engine = new ClinicalReplayEngine($repo, $hydrator, $resolver, $builder);
		$mode = strtolower(trim((string)$mode));

		if ($mode === 'time') {
			$cutoff = trim((string)$arg1);
			if ($cutoff === '') {
				throw new InvalidArgumentException('missing_cutoff');
			}
			$result = $engine->replayAtTime($iopId, $cutoff);
			$raw = $repo->getStreamAtTime($iopId, $cutoff);
		} elseif ($mode === 'shift') {
			$shiftId = trim((string)$arg1);
			$shiftDate = trim((string)$arg2);
			if ($shiftId === '' || $shiftDate === '') {
				throw new InvalidArgumentException('missing_shift_args');
			}
			$result = $engine->replayShift($iopId, $shiftId, $shiftDate);
			$raw = $repo->getStreamForShift($iopId, $shiftId, $shiftDate);
		} else {
			$mode = 'patient';
			$result = $engine->replayPatient($iopId);
			$raw = $repo->getStreamByIopId($iopId);
		}

		$hydratedRaw = $hydrator->hydrate($raw);
		return array(
			'mode' => $mode,
			'iop_id' => (string)$iopId,
			'raw_events' => $hydratedRaw,
			'effective_result' => method_exists($result, 'toArray') ? $result->toArray() : $result,
			'correction_chains' => $resolver->buildCorrectionChains($hydratedRaw),
			'anomalies' => $this->clinicalReplayCollectAnomalies($hydratedRaw),
		);
	}

	protected function clinicalReplayCanonicalJson($value)
	{
		$normalized = $this->clinicalReplayCanonicalize($value);
		return json_encode($normalized, JSON_UNESCAPED_SLASHES);
	}

	protected function clinicalReplayEventFingerprint(array $payload)
	{
		$events = isset($payload['raw_events']) && is_array($payload['raw_events']) ? $payload['raw_events'] : array();
		$sequence = array();
		foreach ($events as $event) {
			$sequence[] = array(
				'event_id' => isset($event['event_id']) ? (string)$event['event_id'] : '',
				'stream_version' => isset($event['stream_version']) ? (string)$event['stream_version'] : '',
				'domain' => isset($event['domain']) ? (string)$event['domain'] : '',
				'event_type' => isset($event['event_type']) ? (string)$event['event_type'] : '',
				'status' => isset($event['status']) ? (string)$event['status'] : '',
				'corrects_event_id' => isset($event['corrects_event_id']) ? (string)$event['corrects_event_id'] : '',
			);
		}

		return array(
			'sequence' => $sequence,
			'correction_chains' => isset($payload['correction_chains']) ? $payload['correction_chains'] : array(),
			'anomalies' => isset($payload['anomalies']) ? $payload['anomalies'] : array(),
		);
	}

	protected function clinicalReplayCanonicalize($value)
	{
		if (is_object($value)) {
			$value = get_object_vars($value);
		}
		if (!is_array($value)) {
			return $value;
		}

		$isList = empty($value) || array_keys($value) === range(0, count($value) - 1);
		if (!$isList) {
			ksort($value);
		}
		foreach ($value as $k => $v) {
			$value[$k] = $this->clinicalReplayCanonicalize($v);
		}
		return $value;
	}

	protected function clinicalReplayTableExists($table)
	{
		$schemaName = $this->clinicalCurrentDatabaseName();
		if ($schemaName === '') {
			return false;
		}
		$q = $this->db->query(
			"SELECT 1 AS ok FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1",
			array($schemaName, (string)$table)
		);
		return $q && $q->row();
	}

	protected function clinicalCurrentDatabaseName()
	{
		$schemaRow = $this->db->query("SELECT DATABASE() AS db")->row();
		return ($schemaRow && isset($schemaRow->db)) ? (string)$schemaRow->db : '';
	}

	protected function clinicalReplayCollectAnomalies(array $events)
	{
		$out = array();
		foreach ($events as $event) {
			if (!isset($event['replay_anomalies']) || !is_array($event['replay_anomalies'])) {
				continue;
			}
			$eventId = isset($event['event_id']) ? (string)$event['event_id'] : '';
			foreach ($event['replay_anomalies'] as $a) {
				$out[] = array('event_id' => $eventId, 'code' => (string)$a);
			}
		}
		return $out;
	}
}
