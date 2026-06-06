<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Claim-It Local API Adapter
 *
 * Communicates with the real Ghana NHIS Claim-It Desktop Application
 * (SIDRID NHIAClaimIt) running on IIS Express locally.
 *
 * Discovered from actual installation at C:\ProgramData\SIDRID\NHIAClaimIt:
 *   - Slim PHP app on IIS Express, port 31719 (default)
 *   - Own MariaDB on port 3307 (db_claims_doctrine)
 *   - Restangular SPA frontend (AngularJS)
 *   - Session-based authentication (POST api/v1/login)
 *   - Claim schema per NHIA XSD (xml.xsd)
 *
 * API base: http://localhost:31719/sclaims/api/v1/
 *
 * Real endpoints (from Restangular sourcePaths + route analysis):
 *   POST   api/v1/login                   — session auth {username, password}
 *   GET    api/v1/login/isalive           — keepalive / health check
 *   GET    api/v1/claims                  — list claims
 *   POST   api/v1/claims                  — create/save a claim
 *   GET    api/v1/claims/{id}             — get claim details
 *   POST   api/v1/credentials/nhis        — verify NHIS membership
 *   GET    api/v1/diseases                — search ICD-10 / diseases
 *   POST   api/v1/options/import          — HMS bulk import endpoint
 *   GET    api/v1/options/import/reports   — import status reports
 *   GET    api/v1/export                  — claim export
 *   GET    api/v1/accreditation           — facility accreditation info
 *   GET    api/v1/options/settings         — Claim-It settings
 *
 * Claim payload follows NHIA XSD schema with fields:
 *   claimID, memberNo, hospitalRecNo, physicianID, typeOfService,
 *   typeOfAttendance, serviceOutcome, dateOfService[], specialtyAttended[],
 *   diagnosisEntries[{gdrgCode, icd10, diagnosis}],
 *   medicineEntries[{medicineCode, dispensedQty, serviceDate, prescription}],
 *   investigationEntries[{serviceDate, gdrgCode}],
 *   procedureEntries[{serviceDate, gdrgCode, icd10, diagnosis}]
 *
 * @package     HMS
 * @subpackage  Libraries
 * @category    NHIS Claim-It Integration
 */
class ClaimItLocalApi
{
    /** @var object CodeIgniter instance */
    private $CI;

    /** @var string Base URL of Claim-It local server (e.g. http://localhost:31719) */
    private $base_url;

    /** @var string Facility code registered with NHIS */
    private $facility_code;

    /** @var int HTTP timeout in seconds */
    private $timeout;

    /** @var int Connect timeout in seconds */
    private $connect_timeout;

    /** @var bool Whether to log API calls */
    private $log_enabled;

    /** @var string Path to cookie jar file for session persistence */
    private $cookie_jar;

    /** @var bool Whether we have an active authenticated session */
    private $authenticated = false;

    /** @var string Claim-It login username */
    private $username;

    /** @var string Claim-It login password */
    private $password;

    public function __construct($params = array())
    {
        $this->CI =& get_instance();

        if (file_exists(APPPATH . 'config/nhis.php')) {
            $this->CI->load->config('nhis');
        }

        $port = isset($params['port'])
            ? $params['port']
            : ($this->CI->config->item('claimit_local_port')
                ?: (getenv('CLAIMIT_PORT') !== false ? getenv('CLAIMIT_PORT') : '31719'));

        $host = isset($params['host'])
            ? $params['host']
            : ($this->CI->config->item('claimit_local_host')
                ?: (getenv('CLAIMIT_HOST') !== false ? getenv('CLAIMIT_HOST') : 'localhost'));

        $this->base_url        = "http://{$host}:{$port}/sclaims";
        $this->facility_code   = $this->CI->config->item('nhis_facility_code') ?: '';
        $this->timeout         = $this->CI->config->item('nhis_api_timeout') ?: 30;
        $this->connect_timeout = $this->CI->config->item('nhis_api_connect_timeout') ?: 10;
        $this->log_enabled     = true;

        // Claim-It credentials (for session auth)
        $this->username = isset($params['username'])
            ? $params['username']
            : ($this->CI->config->item('claimit_username')
                ?: (getenv('CLAIMIT_USERNAME') !== false ? getenv('CLAIMIT_USERNAME') : ''));
        $this->password = isset($params['password'])
            ? $params['password']
            : ($this->CI->config->item('claimit_password')
                ?: (getenv('CLAIMIT_PASSWORD') !== false ? getenv('CLAIMIT_PASSWORD') : ''));

        // Cookie jar for session persistence across requests
        $this->cookie_jar = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'claimit_session_' . md5($this->base_url) . '.txt';

        if (!isset($this->CI->nhis_claimit_model)) {
            $this->CI->load->model('app/Nhis_claimit_model', 'nhis_claimit_model');
        }
    }

    // =========================================================================
    // SESSION / AUTH
    // =========================================================================

    /**
     * Authenticate with Claim-It via POST api/v1/login
     * Stores session cookie in cookie jar for subsequent requests.
     *
     * @param string|null $username Override username
     * @param string|null $password Override password
     * @return array {success, data, message}
     */
    public function login($username = null, $password = null)
    {
        $user = $username ?: $this->username;
        $pass = $password ?: $this->password;

        if (empty($user) || empty($pass)) {
            return array(
                'success'    => false,
                'message'    => 'Claim-It credentials not configured. Set claimit_username/claimit_password in nhis config or CLAIMIT_USERNAME/CLAIMIT_PASSWORD env vars.',
                'error_code' => 'NO_CREDENTIALS'
            );
        }

        $result = $this->_request('POST', 'api/v1/login', array(
            'username' => $user,
            'password' => $pass
        ));

        if ($result['success']) {
            $this->authenticated = true;
        }

        $this->_log('api/v1/login', array('username' => $user), $result,
            $result['success'] ? 'SUCCESS' : 'ERROR');

        return $result;
    }

    /**
     * Ensure we have an active session, login if needed
     *
     * @return bool
     */
    private function _ensure_auth()
    {
        if ($this->authenticated) {
            return true;
        }

        // Try a keepalive first — maybe cookie jar has a valid session
        $alive = $this->_request('GET', 'api/v1/login/isalive');
        if ($alive['success']) {
            $this->authenticated = true;
            return true;
        }

        // Need to login
        $login = $this->login();
        return $login['success'];
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Check if Claim-It Desktop App is running and reachable.
     * Uses the keepalive endpoint; does NOT require auth.
     *
     * @return array {success, data, message}
     */
    public function health_check()
    {
        $result = $this->_request('GET', 'api/v1/login/isalive');

        // If 403, the app is running but we're not authenticated — still "alive"
        if (!$result['success'] && isset($result['http_code']) && $result['http_code'] == 403) {
            return array(
                'success' => true,
                'data'    => array('status' => 'running', 'authenticated' => false),
                'message' => 'Claim-It is running but session is not authenticated.'
            );
        }

        return $result;
    }

    /**
     * Verify NHIS membership via Claim-It's credential check
     *
     * @param string $nhis_number Patient's NHIS membership number
     * @return array {success, data}
     */
    public function check_eligibility($nhis_number)
    {
        if (!$this->_ensure_auth()) {
            return array('success' => false, 'message' => 'Claim-It authentication failed.', 'error_code' => 'AUTH_FAILED');
        }

        $payload = array('memberNo' => $nhis_number);

        $result = $this->_request('POST', 'api/v1/credentials/nhis', $payload);

        $this->_log('api/v1/credentials/nhis', $payload, $result,
            $result['success'] ? 'SUCCESS' : 'ERROR');

        // Normalize response to match what nhis_claims controller expects
        if ($result['success'] && isset($result['data'])) {
            $d = $result['data'];
            $result['data'] = array(
                'nhis_number' => $nhis_number,
                'member_name' => isset($d['surname']) ? trim($d['surname'] . ' ' . (isset($d['otherNames']) ? $d['otherNames'] : '')) : '',
                'status'      => isset($d['status']) ? $d['status'] : 'UNKNOWN',
                'expiry_date' => isset($d['expiryDate']) ? $d['expiryDate'] : null,
                'raw'         => $d
            );
        }

        return $result;
    }

    /**
     * Submit a single claim to Claim-It
     *
     * @param array $claim_data HMS claim data (from nhis_claims + nhis_claim_items)
     * @return array {success, data: {claimit_reference, ...}}
     */
    public function submit_claim($claim_data)
    {
        if (!$this->_ensure_auth()) {
            return array('success' => false, 'message' => 'Claim-It authentication failed.', 'error_code' => 'AUTH_FAILED');
        }

        $payload = $this->build_claim_payload($claim_data);

        $result = $this->_request('POST', 'api/v1/claims', $payload);

        $this->_log('api/v1/claims', $payload, $result,
            $result['success'] ? 'SUCCESS' : 'ERROR');

        // Normalize: extract claimit_reference from response
        if ($result['success'] && isset($result['data'])) {
            $d = $result['data'];
            if (!isset($d['claimit_reference'])) {
                $d['claimit_reference'] = isset($d['id']) ? $d['id'] : (isset($d['claimID']) ? $d['claimID'] : '');
            }
            $result['data'] = $d;
        }

        return $result;
    }

    /**
     * Submit claims via the HMS import endpoint
     * This is the primary integration path for HMS mode.
     *
     * @param array $claims Array of HMS claim data arrays
     * @return array {success, data}
     */
    public function import_claims($claims)
    {
        if (!$this->_ensure_auth()) {
            return array('success' => false, 'message' => 'Claim-It authentication failed.', 'error_code' => 'AUTH_FAILED');
        }

        $payloads = array();
        foreach ($claims as $claim) {
            $payloads[] = $this->build_claim_payload($claim);
        }

        $result = $this->_request('POST', 'api/v1/options/import', array(
            'claims' => $payloads
        ));

        $this->_log('api/v1/options/import', array('count' => count($payloads)), $result,
            $result['success'] ? 'SUCCESS' : 'ERROR');

        return $result;
    }

    /**
     * Submit a batch of claims (wraps import_claims for backward compat)
     *
     * @param array $claims Array of claim data arrays
     * @return array
     */
    public function submit_batch($claims)
    {
        return $this->import_claims($claims);
    }

    /**
     * Get a specific claim from Claim-It by ID
     *
     * @param string $claim_id The Claim-It internal claim ID
     * @return array {success, data}
     */
    public function get_claim($claim_id)
    {
        if (!$this->_ensure_auth()) {
            return array('success' => false, 'message' => 'Claim-It authentication failed.', 'error_code' => 'AUTH_FAILED');
        }

        $result = $this->_request('GET', 'api/v1/claims/' . urlencode($claim_id));

        $this->_log('api/v1/claims/' . $claim_id,
            array('claim_id' => $claim_id),
            $result, $result['success'] ? 'SUCCESS' : 'ERROR');

        return $result;
    }

    /**
     * Check status of a previously submitted claim (alias for get_claim)
     *
     * @param string $claimit_reference
     * @return array
     */
    public function check_claim_status($claimit_reference)
    {
        return $this->get_claim($claimit_reference);
    }

    /**
     * Search diseases / ICD-10 codes in Claim-It's local database
     *
     * @param string $term Search term
     * @return array {success, data}
     */
    public function search_diseases($term)
    {
        if (!$this->_ensure_auth()) {
            return array('success' => false, 'message' => 'Claim-It authentication failed.', 'error_code' => 'AUTH_FAILED');
        }

        return $this->_request('GET', 'api/v1/diseases?q=' . urlencode($term));
    }

    /**
     * Get import status reports
     *
     * @return array {success, data}
     */
    public function get_import_reports()
    {
        if (!$this->_ensure_auth()) {
            return array('success' => false, 'message' => 'Claim-It authentication failed.', 'error_code' => 'AUTH_FAILED');
        }

        return $this->_request('GET', 'api/v1/options/import/reports');
    }

    // =========================================================================
    // PAYLOAD BUILDER — Converts HMS data to NHIA XSD-compliant JSON
    // =========================================================================

    /**
     * Build a Claim-It compliant payload from HMS claim data.
     *
     * Output matches the NHIA XSD schema (xml.xsd) with fields:
     *   claimID, memberNo, surname, otherNames, dateOfBirth, gender,
     *   hospitalRecNo, isDependant, typeOfService, typeOfAttendance,
     *   serviceOutcome, physicianID, claimCheckCode, dateOfService[],
     *   specialtyAttended[], diagnosisEntries[], medicineEntries[],
     *   investigationEntries[], procedureEntries[]
     *
     * @param array $claim_data HMS claim data
     * @return array NHIA-schema payload
     */
    public function build_claim_payload($claim_data)
    {
        $d = (array)$claim_data;

        // Build visit dates array
        $visit_dates = array();
        if (isset($d['visit_date'])) {
            $visit_dates[] = $d['visit_date'];
        } elseif (isset($d['created_at'])) {
            $visit_dates[] = date('Y-m-d', strtotime($d['created_at']));
        } else {
            $visit_dates[] = date('Y-m-d');
        }
        if (isset($d['discharge_date'])) {
            $visit_dates[] = $d['discharge_date'];
        }

        // Separate items by type
        $items           = isset($d['items']) ? $d['items'] : array();
        $diagnoses_raw   = isset($d['diagnoses']) ? $d['diagnoses'] : array();
        $medicines       = array();
        $investigations  = array();
        $procedures      = array();
        $other_items     = array();

        foreach ($items as $item) {
            $item = (array)$item;
            $type = strtoupper(isset($item['item_type']) ? $item['item_type'] : (isset($item['type']) ? $item['type'] : 'SERVICE'));
            if (in_array($type, array('DRUG', 'MEDICATION'))) {
                $medicines[] = $item;
            } elseif (in_array($type, array('LAB', 'LABORATORY', 'INVESTIGATION'))) {
                $investigations[] = $item;
            } elseif (in_array($type, array('PROCEDURE', 'SURGERY'))) {
                $procedures[] = $item;
            } else {
                $other_items[] = $item;
            }
        }

        // Map investigations (IMAGING, RADIOLOGY, SONOGRAPHY, SCAN) from other_items
        $remaining = array();
        foreach ($other_items as $item) {
            $type = strtoupper(isset($item['item_type']) ? $item['item_type'] : (isset($item['type']) ? $item['type'] : ''));
            if (in_array($type, array('IMAGING', 'RADIOLOGY', 'SONOGRAPHY', 'SCAN'))) {
                $investigations[] = $item;
            } else {
                $remaining[] = $item;
            }
        }

        $payload = array(
            'claimID'               => $this->_val($d, 'claim_number', $this->_val($d, 'claim_ref', '')),
            'memberNo'              => $this->_val($d, 'nhis_number', $this->_val($d, 'nhis_member_id', '')),
            'surname'               => $this->_val($d, 'surname', $this->_val($d, 'patient_surname', '')),
            'otherNames'            => $this->_val($d, 'other_names', $this->_val($d, 'patient_name', '')),
            'dateOfBirth'           => $this->_val($d, 'date_of_birth', $this->_val($d, 'dob', '')),
            'gender'                => $this->_val($d, 'gender', $this->_val($d, 'sex', '')),
            'hospitalRecNo'         => $this->_val($d, 'patient_no', $this->_val($d, 'hospital_rec_no', '')),
            'isDependant'           => $this->_val($d, 'is_dependant', '0'),
            'typeOfService'         => $this->_normalize_type_of_service(
                $this->_val($d, 'encounter_type', $this->_val($d, 'visit_type', 'OPD'))
            ),
            'isUnbundled'           => $this->_val($d, 'is_unbundled', '0'),
            'includesPharmacy'      => count($medicines) > 0 ? '1' : '0',
            'typeOfAttendance'      => $this->_val($d, 'type_of_attendance', 'New Attendance'),
            'serviceOutcome'        => $this->_val($d, 'service_outcome', 'Discharged'),
            'physicianID'           => $this->_val($d, 'attending_doctor', $this->_val($d, 'doctor_name', '')),
            'claimCheckCode'        => $this->_val($d, 'claim_check_code', ''),
            'preAuthorizationCodes' => $this->_val($d, 'pre_auth_codes', ''),
            'dateOfService'         => $visit_dates,
            'specialtyAttended'     => $this->_build_specialties($d),
            'diagnosisEntries'      => $this->_build_diagnosis_entries($diagnoses_raw, $d),
            'medicineEntries'       => $this->_build_medicine_entries($medicines),
            'investigationEntries'  => $this->_build_investigation_entries($investigations),
            'procedureEntries'      => $this->_build_procedure_entries($procedures),
        );

        // Add referral info if present
        if (!empty($d['referral_facility_id']) || !empty($d['referral_facility_name'])) {
            $payload['referralInfo'] = array(
                'claimCheckCode' => $this->_val($d, 'referral_ccc', ''),
                'facilityID'     => $this->_val($d, 'referral_facility_id', ''),
                'facilityName'   => $this->_val($d, 'referral_facility_name', '')
            );
        }

        return $payload;
    }

    // =========================================================================
    // PAYLOAD BUILDERS — Sub-entry arrays per NHIA XSD
    // =========================================================================

    /**
     * Build specialtyAttended array
     */
    private function _build_specialties($d)
    {
        if (!empty($d['specialties_attended'])) {
            $val = $d['specialties_attended'];
            if (is_string($val)) {
                $decoded = json_decode($val, true);
                return is_array($decoded) ? $decoded : explode(',', $val);
            }
            return (array)$val;
        }
        return array('GP');
    }

    /**
     * Build diagnosisEntries per XSD: [{gdrgCode, icd10, diagnosis}]
     */
    private function _build_diagnosis_entries($diagnoses, $claim_data = array())
    {
        $result = array();

        if (!empty($diagnoses)) {
            foreach ($diagnoses as $d) {
                $d = (array)$d;
                $entry = array(
                    'gdrgCode'  => $this->_val($d, 'gdrg_code', $this->_val($d, 'gdrgCode', '')),
                    'icd10'     => $this->_val($d, 'icd10_code', $this->_val($d, 'icd_code', $this->_val($d, 'code', $this->_val($d, 'icd10', '')))),
                    'diagnosis' => $this->_val($d, 'diagnosis_name', $this->_val($d, 'description', $this->_val($d, 'name', $this->_val($d, 'diagnosis', ''))))
                );
                if (!empty($entry['icd10']) || !empty($entry['diagnosis'])) {
                    $result[] = $entry;
                }
            }
        }

        // Fallback: parse diagnosis_codes JSON from nhis_claims
        if (empty($result) && !empty($claim_data['diagnosis_codes'])) {
            $decoded = is_string($claim_data['diagnosis_codes'])
                ? json_decode($claim_data['diagnosis_codes'], true)
                : $claim_data['diagnosis_codes'];
            if (is_array($decoded)) {
                foreach ($decoded as $d) {
                    $result[] = array(
                        'gdrgCode'  => isset($d['gdrg_code']) ? $d['gdrg_code'] : '',
                        'icd10'     => isset($d['code']) ? $d['code'] : '',
                        'diagnosis' => isset($d['name']) ? $d['name'] : ''
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Build medicineEntries per XSD: [{medicineCode, dispensedQty, serviceDate, prescription{dose,frequency,duration,unparsed}}]
     */
    private function _build_medicine_entries($medicines)
    {
        $result = array();
        foreach ($medicines as $med) {
            $med = (array)$med;
            $entry = array(
                'medicineCode' => $this->_val($med, 'nhis_code', $this->_val($med, 'nhis_drug_code', $this->_val($med, 'service_code', $this->_val($med, 'medicineCode', '')))),
                'dispensedQty' => (string)($this->_val($med, 'quantity', $this->_val($med, 'dispensed_qty', '1'))),
                'serviceDate'  => $this->_val($med, 'service_date', $this->_val($med, 'created_at', date('Y-m-d'))),
                'prescription' => array(
                    'dose'      => $this->_val($med, 'dosage', $this->_val($med, 'dose', '')),
                    'frequency' => $this->_val($med, 'frequency', ''),
                    'duration'  => $this->_val($med, 'duration', ''),
                    'unparsed'  => $this->_val($med, 'prescription_text', $this->_val($med, 'unparsed', ''))
                )
            );
            $result[] = $entry;
        }
        return $result;
    }

    /**
     * Build investigationEntries per XSD: [{serviceDate, gdrgCode}]
     */
    private function _build_investigation_entries($investigations)
    {
        $result = array();
        foreach ($investigations as $inv) {
            $inv = (array)$inv;
            $result[] = array(
                'serviceDate' => $this->_val($inv, 'service_date', $this->_val($inv, 'created_at', date('Y-m-d'))),
                'gdrgCode'    => $this->_val($inv, 'gdrg_code', $this->_val($inv, 'nhis_code', $this->_val($inv, 'gdrgCode', '')))
            );
        }
        return $result;
    }

    /**
     * Build procedureEntries per XSD: [{serviceDate, gdrgCode, icd10, diagnosis}]
     */
    private function _build_procedure_entries($procedures)
    {
        $result = array();
        foreach ($procedures as $proc) {
            $proc = (array)$proc;
            $result[] = array(
                'serviceDate' => $this->_val($proc, 'service_date', $this->_val($proc, 'created_at', date('Y-m-d'))),
                'gdrgCode'    => $this->_val($proc, 'gdrg_code', $this->_val($proc, 'nhis_code', $this->_val($proc, 'gdrgCode', ''))),
                'icd10'       => $this->_val($proc, 'icd10_code', $this->_val($proc, 'icd_code', '')),
                'diagnosis'   => $this->_val($proc, 'diagnosis_name', $this->_val($proc, 'diagnosis', ''))
            );
        }
        return $result;
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Safe value accessor with fallback
     */
    private function _val($arr, $key, $default = '')
    {
        return (isset($arr[$key]) && $arr[$key] !== '' && $arr[$key] !== null)
            ? $arr[$key] : $default;
    }

    /**
     * Normalize typeOfService to Claim-It expected values
     */
    private function _normalize_type_of_service($type)
    {
        $type = strtoupper(trim($type));
        $map = array(
            'OPD' => 'OPD', 'OP' => 'OPD', 'OUTPATIENT' => 'OPD', 'O' => 'OPD',
            'IPD' => 'IPD', 'IP' => 'IPD', 'INPATIENT'  => 'IPD', 'I' => 'IPD',
        );
        return isset($map[$type]) ? $map[$type] : 'OPD';
    }

    /**
     * Execute an HTTP request to the Claim-It local server.
     * Uses a cookie jar for session persistence.
     *
     * @param string $method GET or POST
     * @param string $path   API path relative to base (e.g., api/v1/claims)
     * @param array  $data   Request body (for POST)
     * @return array {success: bool, data: array|null, message: string, http_code: int}
     */
    private function _request($method, $path, $data = null)
    {
        $url = rtrim($this->base_url, '/') . '/' . ltrim($path, '/');

        $ch = curl_init();
        $opts = array(
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connect_timeout,
            CURLOPT_HTTPHEADER     => array(
                'Content-Type: application/json',
                'Accept: application/json'
            ),
            CURLOPT_COOKIEJAR      => $this->cookie_jar,
            CURLOPT_COOKIEFILE     => $this->cookie_jar,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
        );

        if ($method === 'POST' && $data !== null) {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($data);
        }

        curl_setopt_array($ch, $opts);

        $response  = curl_exec($ch);
        $error     = curl_error($ch);
        $errno     = curl_errno($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $msg = 'Claim-It connection failed: ' . $error;
            if ($errno === CURLE_COULDNT_CONNECT || $errno === CURLE_OPERATION_TIMEDOUT) {
                $msg = 'Claim-It Desktop App is not reachable at ' . $this->base_url
                    . '. Please ensure the Claim-It application is running.';
            }
            return array(
                'success'    => false,
                'message'    => $msg,
                'error_code' => 'CONNECTION_FAILED'
            );
        }

        $decoded = json_decode($response, true);

        // 403 = not authenticated (session expired or missing)
        if ($http_code === 403) {
            $this->authenticated = false;
            return array(
                'success'    => false,
                'message'    => 'Claim-It session expired or unauthorized. Re-login required.',
                'http_code'  => 403,
                'error_code' => 'AUTH_REQUIRED'
            );
        }

        if ($http_code >= 200 && $http_code < 300) {
            // Claim-It wraps responses in {success: bool, data: ...}
            if (is_array($decoded) && isset($decoded['success'])) {
                return array(
                    'success' => (bool)$decoded['success'],
                    'data'    => isset($decoded['data']) ? $decoded['data'] : $decoded,
                    'message' => isset($decoded['message']) ? $decoded['message'] : 'OK'
                );
            }
            return array(
                'success' => true,
                'data'    => $decoded,
                'message' => 'OK'
            );
        }

        // Error response
        $error_msg = 'Claim-It API error (HTTP ' . $http_code . ')';
        if (is_array($decoded)) {
            if (isset($decoded['message'])) {
                $error_msg = $decoded['message'];
            } elseif (isset($decoded['error'])) {
                $error_msg = is_string($decoded['error']) ? $decoded['error'] : json_encode($decoded['error']);
            }
        }

        return array(
            'success'    => false,
            'message'    => $error_msg,
            'http_code'  => $http_code,
            'error_code' => 'API_ERROR',
            'raw'        => $response
        );
    }

    /**
     * Log an API call to claimit_logs
     */
    private function _log($endpoint, $request, $response, $status)
    {
        if (!$this->log_enabled) return;

        try {
            if (isset($this->CI->nhis_claimit_model)
                && method_exists($this->CI->nhis_claimit_model, 'log_api_call')) {
                $this->CI->nhis_claimit_model->log_api_call(
                    $endpoint, $request, $response, $status
                );
            }
        } catch (Exception $e) {
            log_message('error', 'ClaimItLocalApi log failed: ' . $e->getMessage());
        }
    }

    /**
     * Get the base URL (for display/debugging)
     * @return string
     */
    public function get_base_url()
    {
        return $this->base_url;
    }

    /**
     * Check if currently authenticated
     * @return bool
     */
    public function is_authenticated()
    {
        return $this->authenticated;
    }
}
