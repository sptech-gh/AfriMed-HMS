<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Smart_billing_model extends CI_Model
{
	private $__sb_synced_dates = array();
	private $__sb_config_map = null;

    public function __construct()
    {
        parent::__construct();
		$this->load->model('app/patient_review_authorization_model');
    }

    /* ================================================================== */
    /*  SCHEMA MIGRATION                                                    */
    /* ================================================================== */

    public function ensure_smart_billing_schema()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `smart_billing_config` (
            `config_id`    int(11)      NOT NULL AUTO_INCREMENT,
            `config_key`   varchar(60)  NOT NULL,
            `config_value` varchar(255) NOT NULL DEFAULT '',
            `description`  varchar(255) DEFAULT NULL,
            `updated_by`   varchar(25)  DEFAULT NULL,
            `updated_at`   datetime     DEFAULT NULL,
            `InActive`     tinyint(1)   NOT NULL DEFAULT 0,
            PRIMARY KEY (`config_id`),
            UNIQUE KEY `uq_sb_config` (`config_key`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1");

        $this->db->query("CREATE TABLE IF NOT EXISTS `smart_billing_ledger` (
            `ledger_id`           int(11)        NOT NULL AUTO_INCREMENT,
            `iop_id`              varchar(20)    NOT NULL,
            `patient_no`          varchar(20)    NOT NULL,
            `visit_type`          varchar(20)    NOT NULL DEFAULT 'WALK_IN',
            `appointment_id`      int(11)        DEFAULT NULL,
            `registration_fee`    decimal(10,2)  NOT NULL DEFAULT 0.00,
            `consultation_fee`    decimal(10,2)  NOT NULL DEFAULT 0.00,
            `consultation_waived` tinyint(1)     NOT NULL DEFAULT 0,
            `waiver_reason`       varchar(255)   DEFAULT NULL,
            `status`              varchar(20)    NOT NULL DEFAULT 'PENDING',
            `billed_by`           varchar(25)    DEFAULT NULL,
            `billed_at`           datetime       DEFAULT NULL,
            `notes`               varchar(500)   DEFAULT NULL,
            `created_at`          datetime       NOT NULL,
            `InActive`            tinyint(1)     NOT NULL DEFAULT 0,
            PRIMARY KEY (`ledger_id`),
            UNIQUE KEY `uq_sbl_iop` (`iop_id`),
            KEY `idx_sbl_patient` (`patient_no`),
            KEY `idx_sbl_status`  (`status`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1");

        $this->db->query("CREATE TABLE IF NOT EXISTS `smart_billing_audit` (
            `audit_id`     int(11)     NOT NULL AUTO_INCREMENT,
            `iop_id`       varchar(20) NOT NULL,
            `patient_no`   varchar(20) NOT NULL,
            `action`       varchar(50) NOT NULL,
            `details`      varchar(500) DEFAULT NULL,
            `performed_by` varchar(25)  DEFAULT NULL,
            `performed_at` datetime     NOT NULL,
            PRIMARY KEY (`audit_id`),
            KEY `idx_sba_iop`     (`iop_id`),
            KEY `idx_sba_patient` (`patient_no`)
        ) ENGINE=MyISAM DEFAULT CHARSET=latin1");

        if (!$this->_column_exists('patient_details_iop', 'visit_type')) {
            $this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `visit_type` varchar(20) DEFAULT 'WALK_IN' AFTER `patient_type`");
        }
        if (!$this->_column_exists('patient_details_iop', 'appointment_id')) {
            $this->db->query("ALTER TABLE `patient_details_iop` ADD COLUMN `appointment_id` int(11) DEFAULT NULL AFTER `visit_type`");
        }

        $this->_seed_default_config();
        return true;
    }

    private function _seed_default_config()
    {
        $defaults = array(
            array('auto_bill_visit_fees',    '1',     'Enable auto-billing of OPD visit fees during registration (1 = enabled, 0 = disabled)'),
            array('enable_registration_fee', '1',     'Enable registration fee auto-billing when applicable (1 = enabled, 0 = disabled)'),
            array('enable_consultation_fee', '1',     'Enable consultation fee auto-billing when applicable (1 = enabled, 0 = disabled)'),
            array('registration_fee_item_id','0',     'bill_particular.particular_id mapped to Registration Fee (0 = use flat fee config)'),
            array('consultation_fee_item_id','0',     'bill_particular.particular_id mapped to Consultation Fee (0 = use flat fee config)'),
            array('registration_fee_cash',  '20.00', 'OPD registration fee for Cash patients'),
            array('registration_fee_nhis',  '0.00',  'OPD registration fee for NHIS patients (0 = free)'),
            array('consultation_fee_cash',  '30.00', 'OPD consultation fee for Cash patients'),
            array('consultation_fee_nhis',  '0.00',  'OPD consultation fee for NHIS patients (0 = free)'),
            array('review_window_days',     '7',     'Days within which a return visit is treated as a free follow-up (0 = disabled)'),
            array('missed_appt_grace_days', '1',     'Grace days after appointment date before it is treated as missed'),
        );
        $now = date('Y-m-d H:i:s');
        foreach ($defaults as $d) {
            $this->db->where('config_key', $d[0]);
            if (!$this->db->get('smart_billing_config')->row()) {
                $this->db->insert('smart_billing_config', array(
                    'config_key'   => $d[0],
                    'config_value' => $d[1],
                    'description'  => $d[2],
                    'updated_at'   => $now,
                    'InActive'     => 0,
                ));
            }
        }
    }

    /* ------------------------------------------------------------------ */
    /*  CONFIG ACCESSORS                                                    */
    /* ------------------------------------------------------------------ */

    public function get_config($key, $default = '0')
    {
        $key = trim((string)$key);
        if ($this->__sb_config_map === null) {
            $this->__sb_config_map = array();
            if ($this->_table_exists('smart_billing_config')) {
                $this->db->select('config_id, config_key, config_value');
                $this->db->order_by('config_id', 'DESC');
                $q = $this->db->get_where('smart_billing_config', array('InActive' => 0));
                $rows = $q ? $q->result() : array();
                foreach ($rows as $r) {
                    $k = isset($r->config_key) ? trim((string)$r->config_key) : '';
                    if ($k === '') {
                        continue;
                    }
                    if (!array_key_exists($k, $this->__sb_config_map)) {
                        $this->__sb_config_map[$k] = isset($r->config_value) ? (string)$r->config_value : '';
                    }
                }
            }
        }

        return array_key_exists($key, $this->__sb_config_map) ? (string)$this->__sb_config_map[$key] : (string)$default;
    }

    public function set_config($key, $value, $user_id = null)
    {
        $key = trim((string)$key);
        $this->db->where('config_key', (string)$key);
        $existing = $this->db->get('smart_billing_config')->row();
        $data = array(
            'config_value' => (string)$value,
            'updated_by'   => $user_id,
            'updated_at'   => date('Y-m-d H:i:s'),
            'InActive'     => 0,
        );
        if ($existing) {
            $this->db->where('config_key', (string)$key);
            $this->db->update('smart_billing_config', $data);
        } else {
            $data['config_key'] = (string)$key;
            $this->db->insert('smart_billing_config', $data);
        }
		if (is_array($this->__sb_config_map)) {
			$this->__sb_config_map[(string)$key] = (string)$value;
		}
    }

    public function get_all_config()
    {
        if (!$this->_table_exists('smart_billing_config')) return array();
        $q = $this->db->get_where('smart_billing_config', array('InActive' => 0));
        return $q ? $q->result() : array();
    }

	public function get_visit_fee_item_candidate_payload()
	{
		$out = array(
			'registration' => array(
				'candidates' => array(),
				'suggested_id' => 0,
			),
			'consultation' => array(
				'candidates' => array(),
				'suggested_id' => 0,
			),
		);

		if (!$this->_table_exists('bill_particular')) {
			return $out;
		}

		$reg = $this->_find_bill_particular_candidates(array('%REGISTRATION%'));
		if (empty($reg)) {
			$reg = $this->_find_bill_particular_candidates(array('%REGISTRATION%','%REG FEE%','%REGISTRATION FEE%'));
		}
		$con = $this->_find_bill_particular_candidates(array('%CONSULTATION%','%CONSULT%'));
		if (empty($con)) {
			$con = $this->_find_bill_particular_candidates(array('%CONSULT%'));
		}

		$out['registration']['candidates'] = $reg;
		$out['consultation']['candidates'] = $con;
		$out['registration']['suggested_id'] = (count($reg) === 1 && isset($reg[0]['id'])) ? (int)$reg[0]['id'] : 0;
		$out['consultation']['suggested_id'] = (count($con) === 1 && isset($con[0]['id'])) ? (int)$con[0]['id'] : 0;

		return $out;
	}

	private function _find_bill_particular_candidates($likePatterns)
	{
		$likePatterns = is_array($likePatterns) ? $likePatterns : array();
		$likePatterns = array_values(array_filter($likePatterns, function($v) { return trim((string)$v) !== ''; }));
		if (empty($likePatterns)) {
			return array();
		}

		$hasGroups = $this->_table_exists('bill_group_name') && $this->_column_exists('bill_particular', 'group_id');
		$select = 'BP.particular_id AS id, BP.particular_name AS name';
		$join = '';
		if ($hasGroups) {
			$select .= ', G.group_name AS group_name';
			$join = ' LEFT JOIN bill_group_name G ON G.group_id = BP.group_id ';
		}

		$clauses = array();
		$params = array();
		foreach ($likePatterns as $p) {
			$clauses[] = 'UPPER(BP.particular_name) LIKE ?';
			$params[] = strtoupper((string)$p);
		}

		$sql = 'SELECT ' . $select .
			' FROM bill_particular BP ' .
			$join .
			' WHERE BP.InActive = 0 AND (' . implode(' OR ', $clauses) . ')'
			. ' ORDER BY BP.particular_name ASC LIMIT 50';
		$q = $this->db->query($sql, $params);
		$rows = $q ? $q->result() : array();
		$out = array();
		foreach ($rows as $r) {
			$out[] = array(
				'id' => isset($r->id) ? (int)$r->id : 0,
				'name' => isset($r->name) ? (string)$r->name : '',
				'group_name' => isset($r->group_name) ? (string)$r->group_name : '',
			);
		}
		return $out;
	}

	private function _find_bill_particular_by_id($particular_id)
	{
		$particular_id = (int)$particular_id;
		if ($particular_id <= 0) {
			return array();
		}
		if (!$this->_table_exists('bill_particular')) {
			return array();
		}

		$hasGroups = $this->_table_exists('bill_group_name') && $this->_column_exists('bill_particular', 'group_id');
		$select = 'BP.particular_id AS id, BP.particular_name AS name';
		$join = '';
		if ($hasGroups) {
			$select .= ', G.group_name AS group_name';
			$join = ' LEFT JOIN bill_group_name G ON G.group_id = BP.group_id ';
		}

		$sql = 'SELECT ' . $select .
			' FROM bill_particular BP ' .
			$join .
			' WHERE BP.InActive = 0 AND BP.particular_id = ? LIMIT 1';
		$q = $this->db->query($sql, array($particular_id));
		$row = $q ? $q->row() : null;
		if (!$row) {
			return array();
		}

		return array(array(
			'id' => isset($row->id) ? (int)$row->id : 0,
			'name' => isset($row->name) ? (string)$row->name : '',
			'group_name' => isset($row->group_name) ? (string)$row->group_name : '',
		));
	}

    /* ================================================================== */
    /*  VISIT TYPE DETECTION (GHS Rules)                                   */
    /* ================================================================== */

    /**
     * Detect the visit type for a given OPD encounter.
     * GHS Private Hospital Rules:
     *   FIRST_VISIT       – no prior OPD records; ALWAYS bill registration + consultation
     *   REVIEW            – doctor-authorized review within scheduled window → waive consult
     *   FOLLOW_UP         – return within review_window_days WITH doctor authorization → waive consult
     *   MISSED_APPOINTMENT– has a past unattended appointment beyond grace → charge consult
     *   WALK_IN           – returning patient, no appointment/authorization → ALWAYS bill consultation
     */
    public function detect_visit_type($patient_no, $iop_id, $visit_date = null)
    {
        $patient_no = (string)$patient_no;
        $iop_id     = (string)$iop_id;
        $visit_date = $visit_date ? (string)$visit_date : date('Y-m-d');

        // Rule 1: First visit? → ALWAYS bill registration + consultation
        $this->db->select('IO_ID')->from('patient_details_iop')
                 ->where('patient_no', $patient_no)->where('InActive', 0)
                 ->where('IO_ID !=', $iop_id)->limit(1);
        $prior = $this->db->get()->row();
        if (!$prior) {
            return array(
                'visit_type'          => 'FIRST_VISIT',
                'appointment_id'      => null,
                'consultation_waived' => false,
                'waiver_reason'       => null,
            );
        }

		// Rule 2: Doctor-issued review authorization?
		if (isset($this->patient_review_authorization_model) && method_exists($this->patient_review_authorization_model, 'get_active_authorization_for_date')) {
			$auth = $this->patient_review_authorization_model->get_active_authorization_for_date($patient_no, $visit_date);
			if ($auth && isset($auth->id)) {
				return array(
					'visit_type'          => 'REVIEW',
					'appointment_id'      => null,
					'consultation_waived' => true,
					'waiver_reason'       => 'Doctor review authorization (ID: ' . (int)$auth->id . ')',
				);
			}
		}

        // Rule 3: Appointment review on/around today?
        $appt = $this->_get_todays_appointment($patient_no, $visit_date);
        if ($appt) {
            return array(
                'visit_type'          => 'REVIEW',
                'appointment_id'      => (int)$appt->appID,
                'consultation_waived' => true,
                'waiver_reason'       => 'Appointment review visit on ' . $appt->appointmentDate,
            );
        }

        // Rule 4: Follow-up within review window?
        // GHS: Only waive if there is a doctor review authorization for this period
        $reviewDays = (int)$this->get_config('review_window_days', '7');
        if ($reviewDays > 0) {
            $cutoff = date('Y-m-d', strtotime($visit_date . ' -' . $reviewDays . ' days'));
            $this->db->select('IO_ID')->from('patient_details_iop')
                     ->where('patient_no', $patient_no)->where('InActive', 0)
                     ->where('IO_ID !=', $iop_id)
                     ->where('date_visit >=', $cutoff)
                     ->where('date_visit <=', $visit_date)
                     ->limit(1);
            $recent = $this->db->get()->row();
            if ($recent) {
                // Only waive consultation if doctor has issued a review authorization
                $hasAuth = false;
                if (isset($this->patient_review_authorization_model) && method_exists($this->patient_review_authorization_model, 'get_active_authorization_for_date')) {
                    $authCheck = $this->patient_review_authorization_model->get_active_authorization_for_date($patient_no, $visit_date);
                    $hasAuth = ($authCheck && isset($authCheck->id));
                }
                if ($hasAuth) {
                    return array(
                        'visit_type'          => 'FOLLOW_UP',
                        'appointment_id'      => null,
                        'consultation_waived' => true,
                        'waiver_reason'       => 'Follow-up within ' . $reviewDays . '-day review window with doctor authorization',
                    );
                }
                // No doctor authorization → treat as walk-in, consultation is charged
            }
        }

        // Rule 5: Missed appointment?
        $missed = $this->_get_missed_appointment($patient_no, $visit_date);
        if ($missed) {
            return array(
                'visit_type'          => 'MISSED_APPOINTMENT',
                'appointment_id'      => (int)$missed->appID,
                'consultation_waived' => false,
                'waiver_reason'       => null,
            );
        }

        // Rule 6: Walk-in returning patient → ALWAYS bill consultation
        return array(
            'visit_type'          => 'WALK_IN',
            'appointment_id'      => null,
            'consultation_waived' => false,
            'waiver_reason'       => null,
        );
    }

    private function _get_todays_appointment($patient_no, $visit_date)
    {
        $graceDays = (int)$this->get_config('missed_appt_grace_days', '1');
        $earliest  = date('Y-m-d', strtotime($visit_date . ' -' . $graceDays . ' days'));
        $this->db->select('appID, appointmentDate, appointmentReason')
                 ->from('patient_appointment')
                 ->where('patient_no', (string)$patient_no)
                 ->where('appointmentStatus', 'A')
                 ->where('appointmentDate >=', $earliest)
                 ->where('appointmentDate <=', $visit_date)
                 ->order_by('appointmentDate', 'DESC')
                 ->limit(1);
        $q = $this->db->get();
        return $q ? $q->row() : null;
    }

    private function _get_missed_appointment($patient_no, $visit_date)
    {
        $graceDays = (int)$this->get_config('missed_appt_grace_days', '1');
        $cutoff    = date('Y-m-d', strtotime($visit_date . ' -' . $graceDays . ' days'));
        $this->db->select('appID, appointmentDate')
                 ->from('patient_appointment')
                 ->where('patient_no', (string)$patient_no)
                 ->where('appointmentStatus', 'A')
                 ->where('appointmentDate <', $cutoff)
                 ->order_by('appointmentDate', 'DESC')
                 ->limit(1);
        $q = $this->db->get();
        return $q ? $q->row() : null;
    }

    /* ================================================================== */
    /*  FEE CALCULATION                                                     */
    /* ================================================================== */

    public function calculate_fees($visit_info, $payer_type = 'CASH')
    {
        $visitType = isset($visit_info['visit_type'])          ? $visit_info['visit_type']          : 'WALK_IN';
        $waived    = isset($visit_info['consultation_waived'])  ? (bool)$visit_info['consultation_waived'] : false;

        // GHS: Registration fee applies ONLY for first-time patients
        $applyReg     = ($visitType === 'FIRST_VISIT');
        $applyConsult = !$waived;

        $payer_type = strtoupper(trim((string)$payer_type));
        if ($payer_type === '') { $payer_type = 'CASH'; }
        if ($payer_type === 'NHIS') {
            $applyConsult = false;
        }
        $auto = ((int)$this->get_config('auto_bill_visit_fees', '1')) === 1;
		$regEnabled = ((int)$this->get_config('enable_registration_fee', '1')) === 1;
		$consEnabled = ((int)$this->get_config('enable_consultation_fee', '1')) === 1;
		$regKey = ($payer_type === 'NHIS') ? 'registration_fee_nhis' : 'registration_fee_cash';
		$consKey = ($payer_type === 'NHIS') ? 'consultation_fee_nhis' : 'consultation_fee_cash';
		$regFee = ($auto && $regEnabled && $applyReg) ? round((float)$this->get_config($regKey, '0'), 2) : 0.00;
		$consFee = ($auto && $consEnabled && $applyConsult) ? round((float)$this->get_config($consKey, '0'), 2) : 0.00;
		$total = round($regFee + $consFee, 2);

        return array(
            'visit_type'          => $visitType,
            'payer_type'          => $payer_type,
            'apply_registration'  => $applyReg,
            'apply_consultation'  => $applyConsult,
            'registration_fee'    => $regFee,
            'consultation_fee'    => $consFee,
            'consultation_waived' => $waived,
            'waiver_reason'       => isset($visit_info['waiver_reason']) ? $visit_info['waiver_reason'] : null,
            'total'               => $total,
        );
    }

    /* ================================================================== */
    /*  LEDGER OPERATIONS                                                   */
    /* ================================================================== */

    public function upsert_ledger($iop_id, $patient_no, $visit_info)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return false;
        $iop_id   = (string)$iop_id;
        $patient_no = (string)$patient_no;
        if (!isset($visit_info['visit_date']) || trim((string)$visit_info['visit_date']) === '') {
            $visit_info['visit_date'] = $this->_get_visit_date($iop_id);
        }
        $existing = $this->db->get_where('smart_billing_ledger', array('iop_id' => $iop_id, 'InActive' => 0))->row();
        $payer = 'CASH';
		try {
			$this->load->model('app/billing_model');
			if (isset($this->billing_model) && method_exists($this->billing_model, 'determine_payer_type')) {
				$pt = strtoupper(trim((string)$this->billing_model->determine_payer_type($patient_no)));
				if ($pt === 'NHIS' || $pt === 'CASH') {
					$payer = $pt;
				}
			}
		} catch (Throwable $e) {
			$payer = 'CASH';
		}
        $fees = $this->_resolve_visit_fee_summary($iop_id, $patient_no, $visit_info, $payer);
        $status = ($existing && isset($existing->status) && strtoupper((string)$existing->status) === 'BILLED') ? 'BILLED' : 'PENDING';
        $data = array(
            'visit_type'          => isset($visit_info['visit_type'])          ? $visit_info['visit_type']          : 'WALK_IN',
            'appointment_id'      => isset($visit_info['appointment_id'])      ? $visit_info['appointment_id']      : null,
            'registration_fee'    => isset($fees['registration_fee']) ? (float)$fees['registration_fee'] : 0.00,
            'consultation_fee'    => isset($fees['consultation_fee']) ? (float)$fees['consultation_fee'] : 0.00,
            'consultation_waived' => isset($visit_info['consultation_waived']) ? ((bool)$visit_info['consultation_waived'] ? 1 : 0) : 0,
            'waiver_reason'       => isset($visit_info['waiver_reason'])       ? $visit_info['waiver_reason']       : null,
            'status'              => $status,
        );
        if ($existing) {
            $this->db->where('iop_id', $iop_id);
            $this->db->update('smart_billing_ledger', $data);
        } else {
            $data['iop_id']     = $iop_id;
            $data['patient_no'] = $patient_no;
            $data['created_at'] = date('Y-m-d H:i:s');
            $data['InActive']   = 0;
            $this->db->insert('smart_billing_ledger', $data);
        }
        return true;
    }

    public function get_ledger($iop_id)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return null;
        return $this->db->get_where('smart_billing_ledger', array('iop_id' => (string)$iop_id, 'InActive' => 0))->row();
    }

    /* ================================================================== */
    /*  BILLING PREVIEW (used by cashier before 1-click)                   */
    /* ================================================================== */

    public function get_billing_preview($iop_id, $patient_no)
    {
        $this->ensure_smart_billing_schema();
        $ledger = $this->get_ledger($iop_id);
        if ($ledger) {
            $visitInfo = array(
                'visit_type'          => $ledger->visit_type,
                'appointment_id'      => $ledger->appointment_id,
                'consultation_waived' => (bool)$ledger->consultation_waived,
                'waiver_reason'       => $ledger->waiver_reason,
            );
        } else {
            $visitInfo = $this->detect_visit_type($patient_no, $iop_id);
        }
        if (!isset($visitInfo['visit_date']) || trim((string)$visitInfo['visit_date']) === '') {
            $visitInfo['visit_date'] = $this->_get_visit_date((string)$iop_id);
        }
        $this->load->model('app/billing_model');
		$payer = $this->billing_model->determine_payer_type($patient_no);
		$fees = $this->_resolve_visit_fee_summary((string)$iop_id, (string)$patient_no, $visitInfo, $payer);
		return array('visit_info' => $visitInfo, 'fees' => $fees, 'ledger' => $ledger, 'payer' => $payer);
    }

    /* ================================================================== */
    /*  1-CLICK BILLING EXECUTION                                           */
    /* ================================================================== */

    public function execute_one_click_billing($iop_id, $patient_no, $user_id)
    {
        $this->ensure_smart_billing_schema();
        $this->db->trans_begin();
		$autoBill = array('ok' => true, 'created' => array(), 'skipped' => array(), 'errors' => array());
		$this->load->model('app/visit_billing_resolver_model');
		$CI =& get_instance();
		if (isset($CI->visit_billing_resolver_model) && is_object($CI->visit_billing_resolver_model) && method_exists($CI->visit_billing_resolver_model, 'auto_bill_visit_fees')) {
			$autoBill = $CI->visit_billing_resolver_model->auto_bill_visit_fees((string)$iop_id, (string)$patient_no, (string)$user_id);
			if (!is_array($autoBill) || empty($autoBill['ok'])) {
				$this->db->trans_rollback();
				return array('success' => false, 'error' => isset($autoBill['error']) ? $autoBill['error'] : 'Visit fee auto-billing failed');
			}
		}
		$this->load->model('app/unified_billing_model');
		$CI =& get_instance();
		if (isset($CI->unified_billing_model) && is_object($CI->unified_billing_model) && method_exists($CI->unified_billing_model, 'backfill_pending_transactions_to_queue')) {
			$CI->unified_billing_model->backfill_pending_transactions_to_queue((string)$iop_id, (string)$patient_no);
		}

        $preview   = $this->get_billing_preview($iop_id, $patient_no);
        $visitInfo = $preview['visit_info'];
        $fees      = $preview['fees'];

        // Integrity verification: SSOT must contain visit-fee transactions for required fees
        $this->load->library('BillingIntegrityService');
        $integrity = $this->billingintegrityservice;

        $missing = array();
        if (!empty($fees['apply_registration']) &&
            !$integrity->has_registration_transaction((string)$iop_id, (string)$patient_no)) {
            $missing[] = 'registration';
        }
        if (!empty($fees['apply_consultation']) &&
            !$integrity->has_consultation_transaction((string)$iop_id, (string)$patient_no)) {
            $missing[] = 'consultation';
        }
        if (!empty($missing)) {
            $this->db->trans_rollback();
            return array('success' => false, 'error' => 'Visit fee SSOT transactions missing for: ' . implode(', ', $missing));
        }

		// Ensure billing queue integrity for visit fees using the central orchestrator.
		$requiresQueue = (!empty($fees['apply_registration']) || !empty($fees['apply_consultation']));
		if ($requiresQueue) {
			$orchestratorResult = null;
			try {
				$this->load->library('VisitBillingOrchestrator');
				if (isset($this->visitbillingorchestrator)
					&& is_object($this->visitbillingorchestrator)
					&& method_exists($this->visitbillingorchestrator, 'ensureVisitBillable')) {
					$orchestratorResult = $this->visitbillingorchestrator->ensureVisitBillable((string)$iop_id, (string)$patient_no, (string)$user_id);
				}
			} catch (\Throwable $e) {
				$orchestratorResult = null;
			}
			if (is_array($orchestratorResult)) {
				$queueOk = (isset($orchestratorResult['queue_verified']) && $orchestratorResult['queue_verified']);
				if (!$queueOk) {
					$err = 'Unable to ensure billing queue for visit.';
					if (!empty($orchestratorResult['errors']) && is_array($orchestratorResult['errors'])) {
						$errDetails = array();
						foreach ($orchestratorResult['errors'] as $e) {
							$e = trim((string)$e);
							if ($e !== '') { $errDetails[] = $e; }
						}
						if (!empty($errDetails)) {
							$err .= ' ' . implode('; ', $errDetails);
						}
					}
					$this->db->trans_rollback();
					return array('success' => false, 'error' => $err);
				}
			}
		}

        $now       = date('Y-m-d H:i:s');

		$errors = (isset($autoBill['errors']) && is_array($autoBill['errors'])) ? $autoBill['errors'] : array();
		if (!empty($errors) && ((float)$fees['total'] <= 0.009) && (!empty($fees['apply_registration']) || !empty($fees['apply_consultation']))) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => implode('; ', $errors));
		}

        $existing = $this->db->get_where('smart_billing_ledger', array('iop_id' => (string)$iop_id, 'InActive' => 0))->row();
        $data = array(
            'visit_type'          => $visitInfo['visit_type'],
            'appointment_id'      => isset($visitInfo['appointment_id']) ? $visitInfo['appointment_id'] : null,
            'registration_fee'    => isset($fees['registration_fee']) ? (float)$fees['registration_fee'] : 0.00,
            'consultation_fee'    => isset($fees['consultation_fee']) ? (float)$fees['consultation_fee'] : 0.00,
            'consultation_waived' => !empty($visitInfo['consultation_waived']) ? 1 : 0,
            'waiver_reason'       => isset($visitInfo['waiver_reason']) ? $visitInfo['waiver_reason'] : null,
            'status'              => 'BILLED',
            'billed_by'           => (string)$user_id,
            'billed_at'           => $now,
        );
        if ($existing) {
            $this->db->where('iop_id', (string)$iop_id);
            $this->db->update('smart_billing_ledger', $data);
        } else {
            $data['iop_id']     = (string)$iop_id;
            $data['patient_no'] = (string)$patient_no;
            $data['created_at'] = $now;
            $data['InActive']   = 0;
            $this->db->insert('smart_billing_ledger', $data);
        }

        // Mark appointment as completed if applicable
        if (!empty($visitInfo['appointment_id'])) {
            $this->db->where('appID', (int)$visitInfo['appointment_id']);
            $this->db->update('patient_appointment', array('appointmentStatus' => 'D', 'dateVisit' => date('Y-m-d')));
        }

        // Tag visit_type on patient_details_iop
        if ($this->_column_exists('patient_details_iop', 'visit_type')) {
            $upd = array('visit_type' => $visitInfo['visit_type']);
            if ($this->_column_exists('patient_details_iop', 'appointment_id')) {
                $upd['appointment_id'] = isset($visitInfo['appointment_id']) ? $visitInfo['appointment_id'] : null;
            }
            $this->db->where('IO_ID', (string)$iop_id);
            $this->db->update('patient_details_iop', $upd);
        }

        $details = 'Visit: ' . $visitInfo['visit_type']
            . (!empty($visitInfo['consultation_waived']) ? ' [WAIVED: ' . (isset($visitInfo['waiver_reason']) ? $visitInfo['waiver_reason'] : '') . ']' : '');
        $this->log_audit($iop_id, $patient_no, 'ONE_CLICK_BILLING', $details, $user_id);

		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return array('success' => false, 'error' => 'Database error during one-click billing');
		}
		$this->db->trans_commit();

        return array('success' => true, 'fees' => $fees, 'visit_info' => $visitInfo, 'auto_bill' => $autoBill);
    }

    /* ================================================================== */
    /*  CASHIER QUEUE                                                       */
    /* ================================================================== */

    public function get_pending_billing_queue($date = null)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return array();
        $date = $date ? (string)$date : date('Y-m-d');
		$this->sync_visit_fee_ledgers_for_date($date);
        $q = $this->db->query(
            "SELECT L.ledger_id, L.iop_id, L.patient_no, L.visit_type,
                    L.appointment_id, L.consultation_waived, L.waiver_reason,
                    L.status, L.created_at,
                    CONCAT(P.lastname, ' ', P.firstname) AS patient_name,
                    I.date_visit, I.doctor_id
             FROM smart_billing_ledger L
             LEFT JOIN patient_personal_info P ON P.patient_no = L.patient_no
             LEFT JOIN patient_details_iop   I ON I.IO_ID = L.iop_id AND I.InActive = 0
             WHERE L.status = 'PENDING' AND L.InActive = 0
               AND DATE(L.created_at) = ?
             ORDER BY L.created_at ASC",
            array($date)
        );
        return $q ? $q->result() : array();
    }

    public function get_billed_queue($date = null)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return array();
        $date = $date ? (string)$date : date('Y-m-d');
        $q = $this->db->query(
            "SELECT L.ledger_id, L.iop_id, L.patient_no, L.visit_type,
                    L.registration_fee, L.consultation_fee,
                    L.consultation_waived, L.waiver_reason,
                    L.status, L.billed_at,
                    CONCAT(P.lastname, ' ', P.firstname) AS patient_name,
                    I.date_visit
             FROM smart_billing_ledger L
             LEFT JOIN patient_personal_info P ON P.patient_no = L.patient_no
             LEFT JOIN patient_details_iop   I ON I.IO_ID = L.iop_id AND I.InActive = 0
             WHERE L.status = 'BILLED' AND L.InActive = 0
               AND DATE(L.billed_at) = ?
             ORDER BY L.billed_at DESC",
            array($date)
        );
        return $q ? $q->result() : array();
    }

    public function count_pending_billing($date = null)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return 0;
        $date = $date ? (string)$date : date('Y-m-d');
		$this->sync_visit_fee_ledgers_for_date($date);
        $q = $this->db->query("SELECT COUNT(*) AS c FROM smart_billing_ledger WHERE status='PENDING' AND InActive=0 AND DATE(created_at)=?", array($date));
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->c : 0;
    }

    public function count_billed_today($date = null)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return 0;
        $date = $date ? (string)$date : date('Y-m-d');
        $q = $this->db->query("SELECT COUNT(*) AS c FROM smart_billing_ledger WHERE status='BILLED' AND InActive=0 AND DATE(billed_at)=?", array($date));
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->c : 0;
    }

    public function count_waivers_today($date = null)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return 0;
        $date = $date ? (string)$date : date('Y-m-d');
        $q = $this->db->query("SELECT COUNT(*) AS c FROM smart_billing_ledger WHERE consultation_waived=1 AND InActive=0 AND DATE(created_at)=?", array($date));
        $r = $q ? $q->row() : null;
        return $r ? (int)$r->c : 0;
    }

	public function sync_visit_fee_ledgers_for_date($date = null)
	{
		$date = $date ? date('Y-m-d', strtotime((string)$date)) : date('Y-m-d');
		if (isset($this->__sb_synced_dates[$date])) {
			return 0;
		}
		$this->__sb_synced_dates[$date] = true;
		try {
			$CI =& get_instance();
			if (isset($CI) && isset($CI->session) && is_object($CI->session)) {
				$key = '_sb_sync_ts_' . $date;
				$last = $CI->session->userdata($key);
				$last = $last !== null ? (int)$last : 0;
				if ($last > 0 && (time() - $last) < 60) {
					return 0;
				}
			}
		} catch (Throwable $e) {
		}
		$this->ensure_smart_billing_schema();
		if (!$this->_table_exists('patient_details_iop')) return 0;
		$q = $this->db->query(
			"SELECT I.IO_ID, I.patient_no, I.date_visit
			 FROM patient_details_iop I
			 LEFT JOIN smart_billing_ledger L ON L.iop_id = I.IO_ID AND L.InActive = 0
			 WHERE I.InActive = 0
			   AND DATE(I.date_visit) = ?
			   AND (L.ledger_id IS NULL OR UPPER(L.status) = 'PENDING')
			 ORDER BY I.IO_ID ASC",
			array($date)
		);
		$rows = $q ? $q->result() : array();
		$count = 0;
		foreach ($rows as $row) {
			$iop = isset($row->IO_ID) ? (string)$row->IO_ID : '';
			$patient = isset($row->patient_no) ? (string)$row->patient_no : '';
			if ($iop === '' || $patient === '') continue;
			$visitDate = isset($row->date_visit) ? (string)$row->date_visit : $date;
			$visitInfo = $this->detect_visit_type($patient, $iop, $visitDate);
			$visitInfo['visit_date'] = $visitDate;
			if ($this->upsert_ledger($iop, $patient, $visitInfo)) {
				$count++;
			}
		}
		try {
			$CI =& get_instance();
			if (isset($CI) && isset($CI->session) && is_object($CI->session)) {
				$CI->session->set_userdata('_sb_sync_ts_' . $date, time());
			}
		} catch (Throwable $e) {
		}
		return $count;
	}

	private function _resolve_visit_fee_summary($iop_id, $patient_no, $visit_info, $payer_type = 'CASH')
	{
		$visit_info = is_array($visit_info) ? $visit_info : array();
		$fees = $this->calculate_fees($visit_info, $payer_type);
		$decision = null;
		try {
			$this->load->model('app/visit_billing_resolver_model');
			$CI =& get_instance();
			if (isset($CI->visit_billing_resolver_model) && is_object($CI->visit_billing_resolver_model) && method_exists($CI->visit_billing_resolver_model, 'preview_visit_fee_decisions')) {
				$decisionDate = isset($visit_info['visit_date']) && trim((string)$visit_info['visit_date']) !== '' ? (string)$visit_info['visit_date'] : date('Y-m-d');
				$decision = $CI->visit_billing_resolver_model->preview_visit_fee_decisions((string)$patient_no, (string)$iop_id, $decisionDate);
			}
		} catch (Exception $e) {
			$decision = null;
		}
		if (is_array($decision) && !empty($decision['ok'])) {
			if (isset($decision['payer_type']) && trim((string)$decision['payer_type']) !== '') {
				$fees['payer_type'] = strtoupper(trim((string)$decision['payer_type']));
			}
			if (isset($decision['registration']) && is_array($decision['registration'])) {
				$reg = $decision['registration'];
				$fees['apply_registration'] = (isset($reg['decision']) && $reg['decision'] === 'APPLY');
				$fees['registration_fee'] = $fees['apply_registration'] && isset($reg['amount']) ? round((float)$reg['amount'], 2) : 0.00;
				$fees['registration_reason'] = isset($reg['reason']) ? $reg['reason'] : null;
				$fees['registration_item_id'] = isset($reg['item_id']) ? $reg['item_id'] : null;
			}
			if (isset($decision['consultation']) && is_array($decision['consultation'])) {
				$con = $decision['consultation'];
				$fees['apply_consultation'] = (isset($con['decision']) && $con['decision'] === 'APPLY');
				$fees['consultation_waived'] = (isset($con['decision']) && $con['decision'] === 'WAIVE');
				$fees['consultation_fee'] = $fees['apply_consultation'] && isset($con['amount']) ? round((float)$con['amount'], 2) : 0.00;
				$fees['waiver_reason'] = $fees['consultation_waived'] && isset($con['reason']) ? $con['reason'] : $fees['waiver_reason'];
				$fees['consultation_reason'] = isset($con['reason']) ? $con['reason'] : null;
				$fees['consultation_item_id'] = isset($con['item_id']) ? $con['item_id'] : null;
			}
		}

		$actual = $this->_get_actual_visit_fee_amounts($iop_id, $patient_no);
		if ($actual['registration_exists'] && !empty($fees['apply_registration'])) {
			$fees['apply_registration'] = true;
			$fees['registration_fee'] = round((float)$actual['registration_fee'], 2);
		}
		if ($actual['consultation_exists'] && !empty($fees['apply_consultation'])) {
			$fees['apply_consultation'] = true;
			$fees['consultation_waived'] = false;
			$fees['consultation_fee'] = round((float)$actual['consultation_fee'], 2);
		}
		$fees['total'] = round(((float)$fees['registration_fee'] + (float)$fees['consultation_fee']), 2);
		return $fees;
	}

	private function _get_actual_visit_fee_amounts($iop_id, $patient_no)
	{
		$out = array(
			'registration_exists' => false,
			'consultation_exists' => false,
			'registration_fee' => 0.0,
			'consultation_fee' => 0.0,
		);
		if (!$this->_table_exists('billing_transactions')) {
			return $out;
		}
		$q = $this->db->query(
			"SELECT item_ref, net_amount, gross_amount, unit_price, quantity
			 FROM billing_transactions
			 WHERE InActive = 0
			   AND encounter_id = ?
			   AND patient_no = ?
			   AND item_ref IN (?, ?)",
			array((string)$iop_id, (string)$patient_no, 'visit_registration:' . (string)$iop_id, 'visit_consultation:' . (string)$iop_id)
		);
		$rows = $q ? $q->result() : array();
		foreach ($rows as $row) {
			$ref = isset($row->item_ref) ? (string)$row->item_ref : '';
			$amount = isset($row->net_amount) ? (float)$row->net_amount : 0.0;
			if ($amount <= 0.009 && isset($row->gross_amount)) { $amount = (float)$row->gross_amount; }
			if ($amount <= 0.009) {
				$qty = isset($row->quantity) ? (float)$row->quantity : 1.0;
				if ($qty <= 0) { $qty = 1.0; }
				$amount = (isset($row->unit_price) ? (float)$row->unit_price : 0.0) * $qty;
			}
			if ($ref === 'visit_registration:' . (string)$iop_id) {
				$out['registration_exists'] = true;
				$out['registration_fee'] += $amount;
			} elseif ($ref === 'visit_consultation:' . (string)$iop_id) {
				$out['consultation_exists'] = true;
				$out['consultation_fee'] += $amount;
			}
		}
		return $out;
	}

	private function _get_visit_date($iop_id)
	{
		if (!$this->_table_exists('patient_details_iop')) {
			return date('Y-m-d');
		}
		$this->db->select('date_visit');
		$this->db->where('IO_ID', (string)$iop_id);
		$this->db->where('InActive', 0);
		$this->db->limit(1);
		$row = $this->db->get('patient_details_iop')->row();
		if ($row && isset($row->date_visit) && trim((string)$row->date_visit) !== '') {
			return date('Y-m-d', strtotime((string)$row->date_visit));
		}
		return date('Y-m-d');
	}

    /* ================================================================== */
    /*  PATIENT BILLING HISTORY                                             */
    /* ================================================================== */

    public function get_visit_type_map($iop_ids)
    {
        $map = array();
        if (!$this->_table_exists('smart_billing_ledger')) return $map;
        if (!is_array($iop_ids) || count($iop_ids) === 0) return $map;
        $this->db->select('iop_id, visit_type, consultation_waived, status')
                 ->where_in('iop_id', $iop_ids)
                 ->where('InActive', 0);
        $q = $this->db->get('smart_billing_ledger');
        if ($q) {
            foreach ($q->result() as $r) {
                $map[(string)$r->iop_id] = $r;
            }
        }
        return $map;
    }

    public function get_patient_history($patient_no, $limit = 30)
    {
        if (!$this->_table_exists('smart_billing_ledger')) return array();
        $q = $this->db->query(
            "SELECT L.*, I.date_visit
             FROM smart_billing_ledger L
             LEFT JOIN patient_details_iop I ON I.IO_ID = L.iop_id AND I.InActive = 0
             WHERE L.patient_no = ? AND L.InActive = 0
             ORDER BY L.created_at DESC
             LIMIT ?",
            array((string)$patient_no, (int)$limit)
        );
        return $q ? $q->result() : array();
    }

    /* ================================================================== */
    /*  AUDIT                                                               */
    /* ================================================================== */

    public function log_audit($iop_id, $patient_no, $action, $details = null, $user_id = null)
    {
        if (!$this->_table_exists('smart_billing_audit')) return;
        $this->db->insert('smart_billing_audit', array(
            'iop_id'       => (string)$iop_id,
            'patient_no'   => (string)$patient_no,
            'action'       => (string)$action,
            'details'      => $details ? substr((string)$details, 0, 500) : null,
            'performed_by' => $user_id ? (string)$user_id : null,
            'performed_at' => date('Y-m-d H:i:s'),
        ));
    }

    /* ================================================================== */
    /*  HELPERS                                                             */
    /* ================================================================== */

    public static function visit_type_label($type)
    {
        $map = array(
            'FIRST_VISIT'        => 'First Visit',
            'REVIEW'             => 'Review',
            'FOLLOW_UP'          => 'Follow-Up',
            'WALK_IN'            => 'Walk-In',
            'MISSED_APPOINTMENT' => 'Missed Appt.',
            'EMERGENCY'          => 'Emergency',
        );
        return isset($map[$type]) ? $map[$type] : ucfirst(strtolower(str_replace('_', ' ', $type)));
    }

    public static function visit_type_badge_class($type)
    {
        $map = array(
            'FIRST_VISIT'        => 'label-primary',
            'REVIEW'             => 'label-success',
            'FOLLOW_UP'          => 'label-info',
            'WALK_IN'            => 'label-default',
            'MISSED_APPOINTMENT' => 'label-warning',
            'EMERGENCY'          => 'label-danger',
        );
        return isset($map[$type]) ? $map[$type] : 'label-default';
    }

    private function _column_exists($table, $column)
    {
        $q = $this->db->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return ($q && $q->num_rows() > 0);
    }

    private function _table_exists($table)
    {
        return $this->db->table_exists($table);
    }
}
