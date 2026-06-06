<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * NHIS Claim-IT Integration Configuration
 * 
 * This configuration file controls the NHIS integration mode and settings.
 * Switch between 'mock' and 'live' modes for development/testing vs production.
 * 
 * @package     HMS
 * @subpackage  Config
 * @category    NHIS Integration
 */

/*
|--------------------------------------------------------------------------
| NHIS Integration Mode
|--------------------------------------------------------------------------
| 'mock' = Development/Testing mode using local mock API
| 'live' = Production mode using real NHIS Claim-IT API
*/
// M-NHIS-1: Read mode from environment variable for deploy-time switching
$config['nhis_mode'] = getenv('NHIS_MODE') !== false ? strtolower(getenv('NHIS_MODE')) : 'mock';

/*
|--------------------------------------------------------------------------
| NHIS API Endpoints
|--------------------------------------------------------------------------
*/
$config['nhis_mock_base_url'] = base_url() . 'app/nhis_mock_api';
$config['nhis_live_base_url'] = 'https://claimit.nhis.gov.gh/api';

/*
|--------------------------------------------------------------------------
| Claim-It Local Desktop API (used in LIVE mode)
|--------------------------------------------------------------------------
| The Claim-It desktop application runs locally and exposes a REST API.
| Default: http://localhost:31719
*/
$config['claimit_local_host'] = getenv('CLAIMIT_HOST') !== false ? getenv('CLAIMIT_HOST') : 'localhost';
$config['claimit_local_port'] = getenv('CLAIMIT_PORT') !== false ? getenv('CLAIMIT_PORT') : '31719';

/*
|--------------------------------------------------------------------------
| Claim-It Desktop App Login Credentials
|--------------------------------------------------------------------------
| The Claim-It local API uses session-based authentication.
| HMS must POST {username, password} to api/v1/login to get a session cookie.
| These are the Claim-It app credentials, NOT the NHIA portal credentials.
| Set via environment variables or configure here for your installation.
*/
$config['claimit_username'] = getenv('CLAIMIT_USERNAME') !== false ? getenv('CLAIMIT_USERNAME') : 'admin';
$config['claimit_password'] = getenv('CLAIMIT_PASSWORD') !== false ? getenv('CLAIMIT_PASSWORD') : 'Admin123';

/*
|--------------------------------------------------------------------------
| NHIS API Credentials (Live Mode)
|--------------------------------------------------------------------------
*/
// Read credentials from environment variables (set via Apache SetEnv / nginx fastcgi_param).
// NEVER commit real credentials to source control.
$config['nhis_facility_code'] = getenv('NHIS_FACILITY_CODE') !== false ? getenv('NHIS_FACILITY_CODE') : '';
$config['nhis_api_key']       = getenv('NHIS_API_KEY')       !== false ? getenv('NHIS_API_KEY')       : '';
$config['nhis_api_secret']    = getenv('NHIS_API_SECRET')    !== false ? getenv('NHIS_API_SECRET')    : '';

/*
|--------------------------------------------------------------------------
| NHIS Default Coverage Settings
|--------------------------------------------------------------------------
*/
$config['nhis_default_coverage_percent'] = 100;  // Default coverage percentage
$config['nhis_consultation_coverage'] = 100;     // Consultation fee coverage
$config['nhis_drug_coverage'] = 100;             // Drug coverage (formulary drugs)
$config['nhis_lab_coverage'] = 100;              // Laboratory test coverage
$config['nhis_radiology_coverage'] = 100;        // Radiology/scan coverage
$config['nhis_procedure_coverage'] = 80;         // Procedure coverage

/*
|--------------------------------------------------------------------------
| NHIS Card Validation Settings
|--------------------------------------------------------------------------
*/
$config['nhis_card_expiry_grace_days'] = 0;      // Grace period for expired cards
$config['nhis_require_photo_verification'] = false;
$config['nhis_cache_eligibility_hours'] = 24;   // Cache eligibility check for X hours

/*
|--------------------------------------------------------------------------
| NHIS Claim Settings
|--------------------------------------------------------------------------
*/
$config['nhis_auto_generate_claims'] = true;     // Auto-generate claims on encounter completion
$config['nhis_auto_submit_claims'] = false;      // Auto-submit claims (requires review if false)
$config['nhis_claim_submission_batch_size'] = 50; // Max claims per batch submission
$config['nhis_claim_resubmit_max_attempts'] = 3; // Max resubmission attempts for rejected claims

$config['nhis_auto_fix_on_validation'] = false;
$config['nhis_auto_fix_run_mode'] = 'DRY_RUN';
$config['nhis_auto_fix_allow_writes'] = false;
$config['nhis_auto_fix_min_confidence'] = 0.7;
$config['nhis_auto_fix_revalidate_after'] = true;

/*
|--------------------------------------------------------------------------
| NHIS Billing Split Settings
|--------------------------------------------------------------------------
*/
$config['nhis_split_billing_enabled'] = true;    // Enable automatic billing split
$config['nhis_patient_copay_enabled'] = true;    // Enable patient co-payment for partial coverage
$config['nhis_show_coverage_breakdown'] = true;  // Show coverage breakdown on invoices

/*
|--------------------------------------------------------------------------
| NHIS Item Types
|--------------------------------------------------------------------------
*/
$config['nhis_item_types'] = array(
    'drug'       => 'Medication/Drug',
    'lab'        => 'Laboratory Test',
    'radiology'  => 'Radiology/Imaging',
    'procedure'  => 'Medical Procedure',
    'service'    => 'Medical Service',
    'consumable' => 'Consumable/Supply',
    'consultation' => 'Consultation Fee'
);

/*
|--------------------------------------------------------------------------
| NHIS Claim Statuses
|--------------------------------------------------------------------------
*/
$config['nhis_claim_statuses'] = array(
    'draft'      => 'Draft',
    'pending'    => 'Pending Review',
    'submitted'  => 'Submitted to NHIS',
    'processing' => 'Processing',
    'approved'   => 'Approved',
    'partial'    => 'Partially Approved',
    'rejected'   => 'Rejected',
    'paid'       => 'Paid',
    'cancelled'  => 'Cancelled'
);

/*
|--------------------------------------------------------------------------
| NHIS Coverage Statuses for UI
|--------------------------------------------------------------------------
*/
$config['nhis_coverage_indicators'] = array(
    'covered'     => array('label' => 'Covered', 'class' => 'success', 'icon' => 'fa-check-circle'),
    'partial'     => array('label' => 'Partial', 'class' => 'warning', 'icon' => 'fa-exclamation-circle'),
    'not_covered' => array('label' => 'Not Covered', 'class' => 'danger', 'icon' => 'fa-times-circle')
);

/*
|--------------------------------------------------------------------------
| NHIS Audit Log Settings
|--------------------------------------------------------------------------
*/
$config['nhis_audit_enabled'] = true;
$config['nhis_audit_retention_days'] = 365;      // Keep audit logs for 1 year

/*
|--------------------------------------------------------------------------
| NHIS Reconciliation Settings
|--------------------------------------------------------------------------
*/
$config['nhis_reconciliation_enabled'] = true;
$config['nhis_reconciliation_auto_fix'] = false; // Auto-fix discrepancies (requires review if false)

/*
|--------------------------------------------------------------------------
| NHIS Timeout Settings (seconds)
|--------------------------------------------------------------------------
*/
$config['nhis_api_timeout'] = 30;
$config['nhis_api_connect_timeout'] = 10;

/*
|--------------------------------------------------------------------------
| NHIS Debug Mode
|--------------------------------------------------------------------------
*/
// C-NHIS-4: Only enable debug logging in non-production environments to prevent PHI leakage
$config['nhis_debug'] = (ENVIRONMENT !== 'production');
