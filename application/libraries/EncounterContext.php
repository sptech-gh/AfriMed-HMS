<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class EncounterContext
{
	/** @var CI_Controller */
	protected $CI;

	public function __construct()
	{
		$this->CI = &get_instance();
	}

	protected function normalize_id($value)
	{
		$value = (string)$value;
		if (function_exists('sanitize_id_for_db')) {
			return sanitize_id_for_db($value);
		}
		return trim(urldecode($value));
	}

	protected function ensure_model($model)
	{
		$prop = basename(str_replace('\\', '/', $model));
		$prop = strtolower($prop);
		if (!isset($this->CI->$prop)) {
			$this->CI->load->model($model);
		}
	}

	protected function current_user_module_key_safe()
	{
		$module = '';
		if (isset($this->CI->data) && isset($this->CI->data['userInfo']) && isset($this->CI->data['userInfo']->module)) {
			$module = (string)$this->CI->data['userInfo']->module;
		}
		if ($module === '' && isset($this->CI->general_model) && method_exists($this->CI->general_model, 'getUserLoggedIn')) {
			$u = $this->CI->general_model->getUserLoggedIn($this->CI->session->userdata('username'));
			$module = ($u && isset($u->module)) ? (string)$u->module : '';
		}
		$module = strtolower(trim((string)$module));
		if ($module === 'super admin') {
			$module = 'super_admin';
		}
		return $module;
	}

	protected function current_user_role_id_safe($roleId)
	{
		if ($roleId !== '') {
			return (int)$roleId;
		}
		if (isset($this->CI->data) && isset($this->CI->data['userInfo']) && isset($this->CI->data['userInfo']->user_role)) {
			return (int)$this->CI->data['userInfo']->user_role;
		}
		return (int)$this->CI->session->userdata('user_role');
	}

	protected function current_user_is_admin_safe($roleId)
	{
		$rid = $this->current_user_role_id_safe($roleId);
		$module = $this->current_user_module_key_safe();
		return ($module === 'administrator' || $module === 'super_admin' || $rid === 1);
	}

	protected function doctor_override_key($encounter_type, $iop_id)
	{
		return strtoupper(trim((string)$encounter_type)) . '|' . (string)$iop_id;
	}

	/**
	 * Determine whether the current user has an active "doctor override" for this encounter.
	 *
	 * This duplicates the controller-local logic in app/ipd.php and app/opd.php so this
	 * library does not need to call private/protected controller methods.
	 */
	protected function doctor_has_active_override($encounter_type, $iop_id)
	{
		$k = $this->doctor_override_key($encounter_type, $iop_id);
		$overrides = $this->CI->session->userdata('doctor_overrides');
		if (!is_array($overrides) || !isset($overrides[$k])) {
			return false;
		}
		$ts = (int)$overrides[$k];
		// Overrides are valid for 15 minutes (900 seconds)
		return ($ts > 0 && (time() - $ts) <= 900);
	}

	public function resolve($encounterType, $iop_no, $patient_no, $opts = array())
	{
		$encounterType = strtoupper(trim((string)$encounterType));
		$iop_no = $this->normalize_id($iop_no);
		$patient_no = $this->normalize_id($patient_no);

		$includeBilling = isset($opts['include_billing']) ? (bool)$opts['include_billing'] : false;
		$includeVitals = isset($opts['include_vitals']) ? (bool)$opts['include_vitals'] : false;
		$includeTimeline = isset($opts['include_timeline']) ? (bool)$opts['include_timeline'] : false;
		$timelineLimit = isset($opts['timeline_limit']) ? (int)$opts['timeline_limit'] : 30;
		if ($timelineLimit <= 0) {
			$timelineLimit = 30;
		}
		$logNonOwnerView = isset($opts['log_non_owner_view']) ? (bool)$opts['log_non_owner_view'] : true;
		$hasAccesstoDoctor = isset($opts['hasAccesstoDoctor']) ? (bool)$opts['hasAccesstoDoctor'] : false;
		$roleId = isset($opts['role_id']) ? (string)$opts['role_id'] : '';

		if ($encounterType === 'OPD') {
			return $this->resolve_opd($iop_no, $patient_no, $includeBilling, $includeVitals, $includeTimeline, $timelineLimit, $logNonOwnerView, $hasAccesstoDoctor, $roleId);
		}
		if ($encounterType === 'IPD') {
			return $this->resolve_ipd($iop_no, $patient_no, $includeBilling, $includeVitals, $includeTimeline, $timelineLimit, $logNonOwnerView, $hasAccesstoDoctor, $roleId);
		}

		return array('ok' => false, 'error' => 'Unknown encounter type');
	}

	protected function resolve_opd($iop_no, $patient_no, $includeBilling, $includeVitals, $includeTimeline, $timelineLimit, $logNonOwnerView, $hasAccesstoDoctor, $roleId)
	{
		$this->ensure_model('app/opd_model');
		$this->ensure_model('app/patient_model');
		$this->ensure_model('app/encounter_owner_model');
		$req_iop_no = (string)$iop_no;
		$req_patient_no = (string)$patient_no;
		$needsCanonicalRedirect = false;
		if ($includeBilling) {
			$this->ensure_model('app/billing_model');
		}
		if ($includeTimeline) {
			$this->ensure_model('app/encounter_timeline_model');
		}

		$opd = $this->CI->opd_model->getOPDPatient($iop_no);
		if (!$opd && $patient_no !== '') {
			$rec = $this->CI->db->query(
				"SELECT IO_ID FROM patient_details_iop WHERE patient_no = ? AND InActive = 0 ORDER BY date_visit DESC LIMIT 1",
				array($patient_no)
			);
			if ($rec && $rec->num_rows() > 0) {
				$real_iop = (string)$rec->row()->IO_ID;
				$opd = $this->CI->opd_model->getOPDPatient($real_iop);
				if ($opd) {
					$iop_no = $real_iop;
					if ((string)$iop_no !== $req_iop_no) {
						$needsCanonicalRedirect = true;
					}
				}
			}
		}

		$info = $this->CI->patient_model->getPatientInfo($patient_no);
		if (!$info && $opd) {
			$patient_no = isset($opd->patient_no) ? (string)$opd->patient_no : $patient_no;
			if ($req_patient_no === '' || (string)$patient_no !== $req_patient_no) {
				$needsCanonicalRedirect = true;
			}
			$info = $this->CI->patient_model->getPatientInfo($patient_no);
		}

		if (!$opd) {
			return array('ok' => false, 'error_code' => 'VISIT_NOT_FOUND', 'error' => 'Visit record not found');
		}
		if (!$info) {
			return array('ok' => false, 'error_code' => 'PATIENT_NOT_FOUND', 'error' => 'Patient information not found');
		}

		$canonicalUrl = null;
		if ($needsCanonicalRedirect) {
			// IMPORTANT: Do not call controller-protected helpers from here.
			// ID encoding/decoding is centralized in application/helpers/url_safe_helper.php
			// (autoloaded by default, but we defensively load it if needed).
			if (!function_exists('url_safe_id') && isset($this->CI->load)) {
				$this->CI->load->helper('url_safe');
			}
			if (function_exists('url_safe_id')) {
				$canonicalUrl = base_url() . 'app/opd/view/' . url_safe_id($iop_no) . '/' . url_safe_id($patient_no);
			} else {
				$canonicalUrl = base_url() . 'app/opd/view/' . urlencode((string)$iop_no) . '/' . urlencode((string)$patient_no);
			}
		}

		$encType = isset($opd->patient_type) ? (string)$opd->patient_type : 'OPD';
		$encType = strtoupper(trim((string)$encType));

		$this->CI->encounter_owner_model->install_tables();
		$this->CI->encounter_owner_model->ensure_owner_from_patient_details($iop_no, $patient_no);
		$ownerId = $this->CI->encounter_owner_model->get_owner_doctor_id($iop_no, $encType);
		$me = (string)$this->CI->session->userdata('user_id');
		$isOwner = ($ownerId !== '' && $ownerId === $me);

		$hasOverride = false;
		$hasOverride = $this->doctor_has_active_override($encType, $iop_no);

		$canOverride = false;
		if ($roleId === '' && isset($this->CI->data['userInfo']) && isset($this->CI->data['userInfo']->user_role)) {
			$roleId = (string)$this->CI->data['userInfo']->user_role;
		}
		$this->ensure_model('general_model');
		$isAdmin = $this->current_user_is_admin_safe($roleId);
		$canOverride = ($isAdmin || $this->CI->encounter_owner_model->role_can_override($roleId));

		if ($logNonOwnerView && $hasAccesstoDoctor && !$isOwner) {
			$this->CI->encounter_owner_model->logfile('DoctorEncounter', 'VIEW_NON_OWNER', 'iop:'.$iop_no.'|type:'.$encType, $me);
			$this->CI->encounter_owner_model->record_event($iop_no, $patient_no, $encType, 'VIEW_NON_OWNER', $ownerId, $me, null, $me);
		}

		$data = array(
			'getOPDPatient' => $opd,
			'patientInfo' => $info,
			'encounter_owner_doctor_id' => $ownerId,
			'encounter_is_owner' => $isOwner,
			'encounter_has_override' => $hasOverride,
			'encounter_can_override' => $canOverride,
			'encounter_type' => $encType,
		);

		if ($includeBilling) {
			$data['nhis_payer_type'] = $this->CI->billing_model->determine_payer_type($patient_no);
			$data['nhis_is_review'] = $this->CI->billing_model->is_nhis_review_visit($patient_no);
			$data['nhis_claim'] = $this->CI->billing_model->get_claim_by_iop($iop_no);

			$this->CI->db->select('invoice_no, total_amount, payment_type, payer_type, nhis_covered_amount, patient_payable_amount');
			$this->CI->db->where(array('iop_id' => $iop_no, 'InActive' => 0));
			$this->CI->db->order_by('invoice_no', 'DESC');
			$this->CI->db->limit(1);
			$data['nhis_invoice'] = $this->CI->db->get('iop_billing')->row();
		}

		if ($includeVitals) {
			$vitalsDone = false;
			if ($this->CI->db->field_exists('vitals_status', 'patient_details_iop')) {
				$vr = $this->CI->db->select('vitals_status')
					->get_where('patient_details_iop', array('IO_ID' => (string)$iop_no, 'InActive' => 0), 1)
					->row();
				$st = ($vr && isset($vr->vitals_status)) ? strtoupper(trim((string)$vr->vitals_status)) : '';
				if ($st === 'DONE') {
					$vitalsDone = true;
				}
			}
			if (!$vitalsDone && $this->CI->db->table_exists('iop_vital_parameters')) {
				$qv = $this->CI->db->query(
					"SELECT 1 FROM iop_vital_parameters WHERE iop_id = ? AND InActive = 0 LIMIT 1",
					array((string)$iop_no)
				);
				if ($qv && $qv->num_rows() > 0) {
					$vitalsDone = true;
				}
			}
			$data['vitals_done'] = $vitalsDone;
			$data['vitals_record_url'] = base_url() . 'app/vitals/record_vitals/' . urlencode((string)$iop_no) . '/' . urlencode((string)$patient_no);
		}
		if ($includeTimeline && isset($this->CI->encounter_timeline_model)) {
			$data['encounter_timeline_events'] = $this->CI->encounter_timeline_model->get_events($iop_no, $encType, $timelineLimit);
		}

		return array(
			'ok' => true,
			'iop_no' => $iop_no,
			'patient_no' => $patient_no,
			'data' => $data,
			'canonical_redirect_url' => $canonicalUrl,
		);
	}

	protected function resolve_ipd($iop_no, $patient_no, $includeBilling, $includeVitals, $includeTimeline, $timelineLimit, $logNonOwnerView, $hasAccesstoDoctor, $roleId)
	{
		$this->ensure_model('app/ipd_model');
		$this->ensure_model('app/patient_model');
		$this->ensure_model('app/encounter_owner_model');
		if ($includeTimeline) {
			$this->ensure_model('app/encounter_timeline_model');
		}

		$ipd = $this->CI->ipd_model->getIPDPatient($iop_no);
		if (!$ipd) {
			return array('ok' => false, 'error_code' => 'VISIT_NOT_FOUND', 'error' => 'IPD encounter not found');
		}

		$enc_patient_no = isset($ipd->patient_no) ? (string)$ipd->patient_no : '';
		$needsCanonicalRedirect = false;
		if ($patient_no === '' && $enc_patient_no !== '') {
			$patient_no = $enc_patient_no;
			$needsCanonicalRedirect = true;
		} elseif ($patient_no !== '' && $enc_patient_no !== '' && $patient_no !== $enc_patient_no) {
			$patient_no = $enc_patient_no;
			$needsCanonicalRedirect = true;
		}

		$canonicalUrl = null;
		if ($needsCanonicalRedirect) {
			// IMPORTANT: Do not call controller-protected helpers from here.
			// ID encoding/decoding is centralized in application/helpers/url_safe_helper.php
			// (autoloaded by default, but we defensively load it if needed).
			if (!function_exists('url_safe_id') && isset($this->CI->load)) {
				$this->CI->load->helper('url_safe');
			}
			if (function_exists('url_safe_id')) {
				$canonicalUrl = base_url() . 'app/ipd/view/' . url_safe_id($iop_no) . '/' . url_safe_id($patient_no);
			} else {
				// Safe fallback: raw values. (Should never happen in normal app runtime.)
				$canonicalUrl = base_url() . 'app/ipd/view/' . urlencode((string)$iop_no) . '/' . urlencode((string)$patient_no);
			}
		}

		$info = $this->CI->patient_model->getPatientInfo($patient_no);
		if (!$info) {
			return array(
				'ok' => false,
				'error_code' => 'PATIENT_NOT_FOUND',
				'error' => 'Patient record not found for this encounter',
				'canonical_redirect_url' => $canonicalUrl,
			);
		}

		$encType = isset($ipd->patient_type) ? (string)$ipd->patient_type : 'IPD';
		$encType = strtoupper(trim((string)$encType));

		$this->CI->encounter_owner_model->install_tables();
		$this->CI->encounter_owner_model->ensure_owner_from_patient_details($iop_no, $patient_no);
		$ownerId = $this->CI->encounter_owner_model->get_owner_doctor_id($iop_no, $encType);
		$me = (string)$this->CI->session->userdata('user_id');
		$isOwner = ($ownerId !== '' && $ownerId === $me);

		$hasOverride = false;
		$hasOverride = $this->doctor_has_active_override($encType, $iop_no);

		$canOverride = false;
		if ($roleId === '' && isset($this->CI->data['userInfo']) && isset($this->CI->data['userInfo']->user_role)) {
			$roleId = (string)$this->CI->data['userInfo']->user_role;
		}
		$this->ensure_model('general_model');
		$isAdmin = $this->current_user_is_admin_safe($roleId);
		$canOverride = ($isAdmin || $this->CI->encounter_owner_model->role_can_override($roleId));

		if ($logNonOwnerView && $hasAccesstoDoctor && !$isOwner) {
			$this->CI->encounter_owner_model->logfile('DoctorEncounter', 'VIEW_NON_OWNER', 'iop:'.$iop_no.'|type:'.$encType, $me);
			$this->CI->encounter_owner_model->record_event($iop_no, $patient_no, $encType, 'VIEW_NON_OWNER', $ownerId, $me, null, $me);
		}

		$data = array(
			'getOPDPatient' => $ipd,
			'patientInfo' => $info,
			'encounter_owner_doctor_id' => $ownerId,
			'encounter_is_owner' => $isOwner,
			'encounter_has_override' => $hasOverride,
			'encounter_can_override' => $canOverride,
			'encounter_type' => $encType,
		);
		if ($includeTimeline && isset($this->CI->encounter_timeline_model)) {
			$data['encounter_timeline_events'] = $this->CI->encounter_timeline_model->get_events($iop_no, $encType, $timelineLimit);
		}

		return array(
			'ok' => true,
			'iop_no' => $iop_no,
			'patient_no' => $patient_no,
			'data' => $data,
			'canonical_redirect_url' => $canonicalUrl,
		);
	}
}
