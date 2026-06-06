<?php
/**
 * HMS Modern Sidebar - Super Admin Optimized
 * 
 * This is the modernized sidebar with:
 * - Logical grouping
 * - Reduced click depth
 * - Consistent icons
 * - Section headers
 * - Badge counters with caching
 * - No duplicates
 * 
 * To use: Replace sidebar.php include with sidebar_modern.php
 * Backward compatible: All URLs and permissions preserved
 */

// ============================================
// INITIALIZATION - Same as original
// ============================================
$admin = "";
$configuration = "";
$profile = "";
$patient = "";
$appointmentTab = "";
$opd = "";
$ipd = "";
$billing = "";
$reports = "";
$doctor = "";
$laboratory = "";
$sonography = "";
$user_mgnmt = "";
$ipd_doctor = "";
$emr = "";
$opd_emr = "";
$ipd_emr = "";
$patient_visited_report = "";
$nurse_module = "";
$nurse_medication = "";
$nurse_room_transfer = "";
$nurse_patientHistory = "";
$nurse_discharge_summary = "";
$nurse_bed_side = "";
$inpatient_report = "";
$receipt_mod = "";
$company_information = "";
$nhis_claims_mod = "";
$surgical_package = "";
$declared_receipt_mod = "";
$nurse_diagnosis = "";
$opd_reports = "";
$opd_diagnosis_reports = "";
$opd_medication_reports = "";
$nurse_progress_note = "";
$nurse_messages = "";
$nurse_shift_tasks = "";
$nurse_vitals_queue = "";
$doctor_messages = "";
$sonography_mod = "";
$pharmacy = "";
$pharmacy_worklist = "";
$pharmacy_stock = "";
$pharmacy_alerts = "";
$pending_approvals = "";
$ghs_reports = "";
$ghs_dashboard = "";
$ghs_opd_attendance = "";
$ghs_diagnosis = "";
$ghs_pharmacy = "";
$ghs_nhis_cash = "";
$ghs_revenue = "";
$ghs_daily_returns = "";
$staff_privileges_mod = "";
$nhis_mod = "";
$nhis_dashboard = "";
$nhis_claims = "";
$nhis_coverage = "";
$nhis_reconciliation = "";
$nhis_reports = "";
$nhis_audit = "";
$pos_mod = "";
$OR_history_mod = "";
$editprofile_mod = "";
$change_pwd_mod = "";
$daily_reports_mod = "";
$patient_list_report_mod = "";
$opd_doctor = "";
$add_user = "";
$user_index = "";
$room_m = "";
$nurse_vital_sign = "";
$outpatient_report = "";
$discharged_patient_report = "";
$surgical_costing = "";
$ipd_registration = "";
$ipd_master = "";
$roles_mod = "";
$pages_mod = "";
$user_mod = "";
$myprofile_mod = "";
$designation_mod = "";
$department_mod = "";
$subtree_room = "";
$sub_room_category_mod = "";
$sub_room_master_mod = "";
$sub_room_bed_mod = "";
$addNew_patient_mode = "";
$patient_master_mode = "";
$patient_history_mode = "";
$appointmentList = "";
$appointmentAdd = "";
$opd_registration = "";
$opd_master = "";
$param_mod = "";
$backup = "";
$group_name_mod = "";
$particular_bill_mod = "";
$diagnosis_mod = "";
$insurance_company_mod = "";
$complain_mod = "";
$med_cat_mod = "";
$medicine = "";
$drug_mod = "";
$bill_history_mod = "";
$room_enquiry = "";
$nurse_intake_output = "";
$appAdd = "";
$clinical_tab = "";
$diagnostics_tab = "";
$imaging_tab = "";
$reports_hub = "";

// User info fallback
if (!isset($userInfo) || !is_object($userInfo)) {
    if (isset($this->data) && isset($this->data['userInfo']) && is_object($this->data['userInfo'])) {
        $userInfo = $this->data['userInfo'];
    } else if (isset($this->general_model)) {
        $userInfo = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
    }
}

// Access flags initialization
$__accessFlags = array(
    'hasAccesstoDoctorAvail',
    'hasAccesstoBilling','hasAccesstoPOS','hasAccesstoSurgical','hasAccesstoPharmacy',
    'hasAccesstoAppointment','hasAccesstoAddAppointment',
    'hasAccesstoPatient','hasAccesstoAddPatient','hasAccesstoOPDRegistration','hasAccesstoOPDEnquiry','hasAccesstoIPDRegistration','hasAccesstoIPDEnquiry',
    'hasAccesstoRooms','hasAccesstoRoomsEnquiry',
    'hasAccesstoNurse','hasAccesstoNurseBedSide','hasAccesstoNurseInOutTake','hasAccesstoNurseIPRoomTransfer','hasAccesstoNurseDiagnosis','hasAccesstoNurseProgressNote','hasAccesstoNurseDischarge','hasAccesstoNursePatientHistory','hasAccesstoNurseMedication','hasAccesstoNurseVitalSign',
    'hasAccesstoDoctor','hasAccesstoDoctorIPD','hasAccesstoDoctorOPD',
    'hasAccesstoLaboratory',
    'hasAccesstoSonography',
    'hasAccesstoEMR','hasAccesstoEMRIPD','hasAccesstoEMROPD',
    'hasAccesstoUsers','hasAccesstoAddUsers',
    'hasAccesstoAdmin','hasAccesstoAdminCompanyInfo','hasAccesstoAdminDepartment','hasAccesstoAdminDesignation','hasAccesstoAdminBillGroupName','hasAccesstoAdminParticularBill','hasAccesstoAdminComplain','hasAccesstoAdminDiagnosis','hasAccesstoAdminSurgicalPack','hasAccesstoAdminInsuranceCompany','hasAccesstoAdminMedicineCategory','hasAccesstoAdminDrugName','hasAccesstoAdminAckReceipt','hasAccesstoAdminParameters','hasAccesstoAdminBackup','hasAccesstoAdminPages',
    'hasAccesstoReport','hasAccesstoReportPatient','hasAccesstoReportIndividualPatient','hasAccesstoReportOPD','hasAccesstoReportAdmitted','hasAccesstoReportDischarge','hasAccesstoReportDailySales','hasAccesstoReportDoctorsFee','hasAccesstoReportAR'
);
foreach ($__accessFlags as $__k) {
    if (!isset($$__k)) { $$__k = false; }
}
if (!isset($doctor_message_unread_count)) { $doctor_message_unread_count = 0; }

// Role detection
$isReception = false;
if (isset($userInfo) && isset($userInfo->module)) {
    $mod = strtolower(trim((string)$userInfo->module));
    $isReception = ($mod === 'receptionist' || $mod === 'reception');
}
if (!$isReception) {
    $isReception = ((int)$this->session->userdata('user_role') === 3);
}

$isLaboratory = false;
if (isset($userInfo) && isset($userInfo->module)) {
    $mod = strtolower(trim((string)$userInfo->module));
    $isLaboratory = ($mod === 'laboratory');
}

$isNurse = false;
if (isset($userInfo) && isset($userInfo->module)) {
    $mod = strtolower(trim((string)$userInfo->module));
    $isNurse = ($mod === 'nurse');
}
if (!$isNurse) {
    $isNurse = ((int)$this->session->userdata('user_role') === 7);
}

// Check PRIMARY role (not access permissions) to properly filter menus
// Admin should NOT be flagged as doctor/pharmacist just because they have access
$_primaryRole = function_exists('get_role_key') ? get_role_key() : '';
$isAdmin = ($_primaryRole === 'admin');
$isDoctor = ($_primaryRole === 'doctor');
$isCashier = ($_primaryRole === 'cashier');
$isPharmacist = ($_primaryRole === 'pharmacist');

// Get cashier role flag from controller data (for sidebar restrictions)
$isCashierRole = isset($this->data['isCashierRole']) ? $this->data['isCashierRole'] : $isCashier;

$__nurseOnlySidebar = ($isNurse && !$isAdmin && !$isDoctor && !$isCashierRole && !$isPharmacist && !$isReception && !$isLaboratory);

// ============================================
// BADGE COUNTS WITH CACHING
// ============================================
$badgeCounts = $this->session->userdata('sidebar_badge_counts');
$badgeCacheTime = $this->session->userdata('sidebar_badge_cache_time');
$cacheExpired = (!$badgeCacheTime || (time() - $badgeCacheTime) > 300); // 5 min cache

if (!$badgeCounts || $cacheExpired) {
    $badgeCounts = array(
        'pending_approvals' => 0,
        'lab_pending' => 0,
        'pharmacy_pending' => 0,
        'nhis_alerts' => 0
    );
    
    // Pending stock approvals
    if ($isAdmin && isset($this->governance_model) && method_exists($this->governance_model, 'count_pending_stock_requests')) {
        $badgeCounts['pending_approvals'] = (int)$this->governance_model->count_pending_stock_requests();
    }
    
    // NHIS alerts
    if (isset($this->billing_model) && method_exists($this->billing_model, 'get_nhis_alert_counts')) {
        $nhisSidebarData = $this->billing_model->get_nhis_alert_counts();
        $badgeCounts['nhis_alerts'] = isset($nhisSidebarData['total_alerts']) ? (int)$nhisSidebarData['total_alerts'] : 0;
    }
    
    // Cache the counts
    $this->session->set_userdata('sidebar_badge_counts', $badgeCounts);
    $this->session->set_userdata('sidebar_badge_cache_time', time());
}

// ============================================
// ACTIVE STATE DETECTION
// ============================================
$currentTab = $this->session->userdata('tab');
$currentModule = $this->session->userdata('module');
$currentSubtab = $this->session->userdata('subtab');
$currentSubmodule = $this->session->userdata('submodule');

// Clinical section active
$clinicalActive = in_array($currentSubtab, ['opd', 'ipd']) || in_array($currentModule, ['opd_registration', 'opd_master', 'ipd_registration', 'ipd_master']);

// Diagnostics active
$diagnosticsActive = in_array($currentTab, ['laboratory', 'sonography']) || in_array($currentModule, ['iopd_laboratory', 'sonography']);

// Imaging active
$imagingActive = $currentTab === 'sonography' || strpos($currentModule, 'sonography') !== false || strpos($currentModule, 'radiology') !== false;

// Reports active
$reportsActive = in_array($currentTab, ['reports', 'ghs_reports']);

// Admin active
$adminActive = $currentTab === 'admin';

// System active
$systemActive = in_array($currentModule, ['backup_database', 'pages', 'parameters']);
?>

<!-- Left side column. contains the logo and sidebar -->
<aside class="left-side sidebar-offcanvas">
    <section class="sidebar">
        
        <!-- Sidebar Search -->
        <div class="hms-sidebar-search">
            <input type="text" id="hms-sidebar-search" class="form-control input-sm" placeholder="Search menu..." autocomplete="off" />
        </div>

        <ul class="sidebar-menu">

            <!-- ============================================== -->
            <!-- DASHBOARD -->
            <!-- ============================================== -->
            <li>
                <a href="<?php echo base_url() ?>app/dashboard">
                    <i class="fa fa-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>

            <?php if (!$__nurseOnlySidebar) { ?>

            <!-- ============================================== -->
            <!-- SECTION: QUICK ACTIONS -->
            <!-- ============================================== -->
            <li class="header"><i class="fa fa-bolt"></i> QUICK ACTIONS</li>
            
            <?php if ($hasAccesstoAddPatient && !$isCashierRole) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/patient/addPatient">
                    <i class="fa fa-user-plus text-green"></i> <span>Register Patient</span>
                </a>
            </li>
            <?php } ?>
            
            <?php if ($hasAccesstoOPDRegistration && !$isCashierRole) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/opd/registration">
                    <i class="fa fa-stethoscope text-aqua"></i> <span>OPD Registration</span>
                </a>
            </li>
            <?php } ?>
            
            <?php if ($hasAccesstoIPDRegistration && !$isCashierRole) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/ipd/registration">
                    <i class="fa fa-bed text-purple"></i> <span>Admit Patient</span>
                </a>
            </li>
            <?php } ?>
            
            <?php if (!$isDoctor && !$isCashierRole && ((isset($hasAccesstoPharmacy) && $hasAccesstoPharmacy) || has_role('pharmacist') || (has_role('nurse') && has_privilege('pharmacy_dispense_access')))) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/pharmacy">
                    <i class="fa fa-medkit text-red"></i> <span>Dispense Medication</span>
                </a>
            </li>
            <?php } ?>
            
            <?php if (!$isPharmacist && !$isDoctor && has_role(array('admin', 'cashier', 'billing'))) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/nhis_claims/claimit">
                    <i class="fa fa-shield text-blue"></i> <span>Submit NHIS Claim</span>
                </a>
            </li>
            <?php } ?>
            
            <?php if ($hasAccesstoAddAppointment && !$isCashierRole) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/appointment/addAppointmentList">
                    <i class="fa fa-calendar-plus-o text-olive"></i> <span>Book Appointment</span>
                </a>
            </li>
            <?php } ?>
            
            <?php if (has_role('doctor')) { ?>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/opd/index">
                    <i class="fa fa-list text-aqua"></i> <span>My Patients</span>
                </a>
            </li>
            <li class="quick-action">
                <a href="<?php echo base_url() ?>app/appointment">
                    <i class="fa fa-calendar text-purple"></i> <span>My Appointments</span>
                </a>
            </li>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: CLINICAL (Hidden for Cashiers by default) -->
            <!-- ============================================== -->
            <?php if (!$isCashierRole && ($hasAccesstoOPDRegistration || $hasAccesstoOPDEnquiry || $hasAccesstoIPDRegistration || $hasAccesstoIPDEnquiry || $hasAccesstoAppointment)) { ?>
            <li class="header"><i class="fa fa-stethoscope"></i> CLINICAL</li>
            
            <!-- OPD -->
            <?php if ($hasAccesstoOPDRegistration || $hasAccesstoOPDEnquiry) { ?>
            <li class="treeview <?php echo ($currentSubtab === 'opd') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-user-md"></i> <span>OPD</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoOPDRegistration) { ?>
                    <li><a href="<?php echo base_url() ?>app/opd/registration"><i class="fa fa-plus-circle"></i> New Registration</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoOPDEnquiry) { ?>
                    <li><a href="<?php echo base_url() ?>app/opd/index"><i class="fa fa-list"></i> OPD Worklist</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>

            <!-- IPD -->
            <?php if ($hasAccesstoIPDRegistration || $hasAccesstoIPDEnquiry) { ?>
            <li class="treeview <?php echo ($currentSubtab === 'ipd') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-bed"></i> <span>IPD</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoIPDRegistration) { ?>
                    <li><a href="<?php echo base_url() ?>app/ipd/registration"><i class="fa fa-plus-circle"></i> Admit Patient</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoIPDEnquiry) { ?>
                    <li><a href="<?php echo base_url() ?>app/ipd/index"><i class="fa fa-list"></i> IPD Worklist</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>

            <!-- Appointments -->
            <?php if ($hasAccesstoAppointment) { ?>
            <li class="treeview <?php echo ($currentTab === 'appointment') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-calendar-check-o"></i> <span>Appointments</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAddAppointment) { ?>
                    <li><a href="<?php echo base_url() ?>app/appointment/addAppointmentList"><i class="fa fa-plus"></i> Add Appointment</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/appointment/index"><i class="fa fa-list"></i> Manage Appointments</a></li>
                </ul>
            </li>
            <?php } ?>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: DIAGNOSTICS (Hidden for Cashiers by default) -->
            <!-- ============================================== -->
            <?php if (!$isCashierRole && (
                $hasAccesstoLaboratory
                || (isset($hasAccesstoSonography) && $hasAccesstoSonography)
                || has_role(array('admin', 'doctor', 'sonographer', 'radiology', 'radiologist'))
                || has_privilege('radiology_access')
                || has_privilege('radiologist_access')
            )) { ?>
            <li class="header"><i class="fa fa-flask"></i> DIAGNOSTICS</li>
            
            <!-- Laboratory -->
            <?php if ($hasAccesstoLaboratory) { ?>
            <li class="treeview <?php echo ($currentTab === 'laboratory') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-flask"></i> <span>Laboratory</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/laboratory/lab_queue"><i class="fa fa-list-ol"></i> Lab Queue</a></li>
                    <li><a href="<?php echo base_url() ?>app/laboratory/index"><i class="fa fa-vials"></i> Lab Tests</a></li>
                    <li><a href="<?php echo base_url() ?>app/laboratory/lab_enquiry"><i class="fa fa-search"></i> Lab Enquiry</a></li>
                </ul>
            </li>
            <?php } ?>

            <!-- Sonography Module (Distinct from Radiology) -->
            <?php if ((isset($hasAccesstoSonography) && $hasAccesstoSonography) || has_role(array('admin', 'doctor', 'sonographer'))) { ?>
            <li class="treeview <?php echo ($currentTab === 'sonography' || strpos($currentModule, 'sonography') !== false) ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-heartbeat text-purple"></i> <span>Sonography</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/sonography/imaging_queue/sonography"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url() ?>app/sonography/imaging_queue/sonography"><i class="fa fa-heartbeat"></i> Pending Scans</a></li>
                    <li><a href="<?php echo base_url() ?>app/sonography/completed"><i class="fa fa-check-circle"></i> Completed Scans</a></li>
                    <?php if ($isAdmin) { ?>
                    <li class="divider"></li>
                    <li><a href="<?php echo base_url() ?>app/test_catalog/sonography_tests"><i class="fa fa-list text-aqua"></i> Test Catalog</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>
            
            <!-- Radiology Module (X-Ray, CT, ECG - Distinct from Sonography) -->
            <?php if (
                $isAdmin
                || has_role(array('doctor', 'radiologist', 'radiology'))
                || has_privilege('radiology_access')
                || has_privilege('radiologist_access')
            ) { ?>
            <li class="treeview <?php echo ($currentTab === 'radiology' || strpos($currentModule, 'radiology') !== false) ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-bolt text-yellow"></i> <span>Radiology</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/radiology"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url() ?>app/radiology/xray_queue"><i class="fa fa-bolt"></i> X-Ray Queue</a></li>
                    <li><a href="<?php echo base_url() ?>app/radiology/ecg_queue"><i class="fa fa-area-chart"></i> ECG Queue</a></li>
                    <li><a href="<?php echo base_url() ?>app/radiology/ct_queue"><i class="fa fa-circle-o-notch"></i> CT Scan Queue</a></li>
                    <?php if ($isAdmin) { ?>
                    <li class="divider"></li>
                    <li><a href="<?php echo base_url() ?>app/radiology/add_test"><i class="fa fa-plus-circle"></i> Add Test</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>
            
            <!-- Critical Alerts Dashboard -->
            <?php if ($hasAccesstoLaboratory || (isset($hasAccesstoSonography) && $hasAccesstoSonography) || has_role(array('admin', 'doctor'))) { ?>
            <li class="<?php echo ($currentTab === 'critical_alerts') ? 'active' : ''; ?>">
                <a href="<?php echo base_url() ?>app/critical_alerts">
                    <i class="fa fa-exclamation-triangle text-red"></i> <span>Critical Alerts</span>
                    <?php 
                    // Get pending critical alerts count
                    $critical_count = 0;
                    if (isset($this->diagnostic_safety_model)) {
                        $all_alerts = $this->diagnostic_safety_model->get_all_pending_critical_alerts(100);
                        $critical_count = is_array($all_alerts) ? count($all_alerts) : 0;
                    }
                    if ($critical_count > 0) { ?>
                    <span class="label label-danger pull-right"><?php echo $critical_count; ?></span>
                    <?php } ?>
                </a>
            </li>
            <?php } ?>
            
            <!-- Sample Tracking -->
            <?php if ($hasAccesstoLaboratory || has_role(array('admin', 'lab_tech', 'phlebotomist'))) { ?>
            <li class="treeview <?php echo ($currentTab === 'sample_tracking') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-barcode text-purple"></i> <span>Sample Tracking</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/sample_tracking"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url() ?>app/sample_tracking/custody"><i class="fa fa-exchange"></i> Chain of Custody</a></li>
                    <li><a href="<?php echo base_url() ?>app/sample_tracking/recollections"><i class="fa fa-refresh"></i> Recollections</a></li>
                    <li><a href="<?php echo base_url() ?>app/sample_tracking/delta_checks"><i class="fa fa-line-chart"></i> Delta Checks</a></li>
                    <li><a href="<?php echo base_url() ?>app/sample_tracking/delta_thresholds"><i class="fa fa-sliders"></i> Delta Thresholds</a></li>
                    <li><a href="<?php echo base_url() ?>app/sample_tracking/locations"><i class="fa fa-map-marker"></i> Locations</a></li>
                </ul>
            </li>
            <?php } ?>
            
            <!-- Diagnostic Safety -->
            <?php if ($hasAccesstoLaboratory || has_role(array('admin', 'lab_supervisor', 'quality_manager'))) { ?>
	            <li class="treeview <?php echo ($currentTab === 'diagnostic_safety') ? 'active' : ''; ?>">
	                <a href="#">
	                    <i class="fa fa-shield text-green"></i> <span>Diagnostic Safety</span>
	                    <i class="fa fa-angle-left pull-right"></i>
	                </a>
	                <ul class="treeview-menu">
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety"><i class="fa fa-dashboard"></i> Safety Dashboard</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/tat_dashboard"><i class="fa fa-tachometer"></i> TAT Monitoring</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/stat_queue"><i class="fa fa-bolt text-yellow"></i> STAT Queue</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/notifications"><i class="fa fa-bell"></i> Notifications</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/audit_log"><i class="fa fa-history"></i> Audit Trail</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/tat_targets"><i class="fa fa-cog"></i> TAT Targets</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/chain_status"><i class="fa fa-link"></i> Chain Status</a></li>
	                    <li><a href="<?php echo base_url() ?>app/diagnostic_safety/compliance_report"><i class="fa fa-file-text"></i> Compliance Report</a></li>
	                </ul>
	            </li>
	            <?php } ?>
	            
	            <!-- Result Approval (Supervisors) -->
	            <?php if (has_role(array('admin', 'lab_supervisor', 'pathologist', 'radiologist', 'senior_radiologist', 'senior_sonographer'))) { ?>
	            <li class="treeview <?php echo ($currentTab === 'result_approval') ? 'active' : ''; ?>">
	                <a href="#">
	                    <i class="fa fa-check-circle text-orange"></i> <span>Result Approval</span>
	                    <i class="fa fa-angle-left pull-right"></i>
	                </a>
	                <ul class="treeview-menu">
	                    <li><a href="<?php echo base_url() ?>app/result_approval"><i class="fa fa-dashboard"></i> Approval Queue</a></li>
	                    <li><a href="<?php echo base_url() ?>app/result_approval/pending/lab"><i class="fa fa-flask"></i> Lab Approvals</a></li>
	                    <li><a href="<?php echo base_url() ?>app/result_approval/pending/radiology"><i class="fa fa-x-ray"></i> Radiology Approvals</a></li>
	                    <li><a href="<?php echo base_url() ?>app/result_approval/pending/sonography"><i class="fa fa-heartbeat"></i> Sonography Approvals</a></li>
	                    <li><a href="<?php echo base_url() ?>app/result_approval/credentials"><i class="fa fa-id-card"></i> Credentials</a></li>
	                    <li><a href="<?php echo base_url() ?>app/result_approval/permissions"><i class="fa fa-cog"></i> Permissions</a></li>
	                    <li><a href="<?php echo base_url() ?>app/result_approval/reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
	                </ul>
	            </li>
	            <?php } ?>
	            <?php } ?>
	            
	            <?php if (has_role(array('admin', 'doctor', 'nurse', 'procedure_unit'))) { ?>
	            <li class="header"><i class="fa fa-scissors"></i> PROCEDURE UNIT</li>
            <li class="<?php echo ($currentTab === 'procedure_unit') ? 'active' : ''; ?>">
                <a href="<?php echo base_url() ?>app/procedure_unit">
                    <i class="fa fa-scissors text-purple"></i> <span>Procedure Worklist</span>
                </a>
            </li>
            <?php } ?>
            
            <!-- ============================================== -->
            <!-- SECTION: PHARMACY (Hidden for Cashiers by default) -->
            <!-- ============================================== -->
            <?php if (!$isDoctor && !$isCashierRole && ((isset($hasAccesstoPharmacy) && $hasAccesstoPharmacy) || has_privilege('pharmacy_access'))) { ?>
            <li class="header"><i class="fa fa-medkit"></i> PHARMACY</li>
            
            <li class="treeview <?php echo ($currentTab === 'pharmacy') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-medkit"></i> <span>Pharmacy</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/pharmacy"><i class="fa fa-list"></i> Dispensing Worklist</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/stock"><i class="fa fa-cubes"></i> Stock Management</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/stores"><i class="fa fa-building"></i> Pharmacy Stores</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/transfers"><i class="fa fa-exchange"></i> Stock Transfers</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/low_stock_report"><i class="fa fa-warning text-yellow"></i> Low Stock Report</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/controlled_drugs"><i class="fa fa-shield text-red"></i> Controlled Drugs</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/pending_authorizations"><i class="fa fa-clock-o text-yellow"></i> Pending Auth</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/controlled_register"><i class="fa fa-book"></i> Drug Register</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/generic_drugs"><i class="fa fa-flask"></i> Generic Drugs</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/unmapped_drugs"><i class="fa fa-unlink text-yellow"></i> Unmapped Brands</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/prescription_status"><i class="fa fa-lock"></i> Prescription Status</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/batch_recalls"><i class="fa fa-exclamation-triangle text-red"></i> Batch Recalls</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/reconciliations"><i class="fa fa-calculator"></i> Reconciliation</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/nhis_drug_mapping"><i class="fa fa-link text-green"></i> NHIS Drug Mapping</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/nhis_mapping_tool"><i class="fa fa-magic text-blue"></i> NHIS Mapping Tool</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/alerts"><i class="fa fa-bell"></i> Alerts</a></li>
                    <li><a href="<?php echo base_url() ?>app/pharmacy/pharmacy_returns"><i class="fa fa-undo text-orange"></i> Drug Returns</a></li>
                    <?php if ($isAdmin) { ?>
                    <li><a href="<?php echo base_url() ?>app/stock_approval"><i class="fa fa-check-square-o"></i> Pending Approvals
                        <?php if ($badgeCounts['pending_approvals'] > 0) { ?>
                        <span class="label label-danger pull-right"><?php echo $badgeCounts['pending_approvals']; ?></span>
                        <?php } ?>
                    </a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } elseif (!$isDoctor && !$isCashierRole && (has_role('nurse') && has_privilege('pharmacy_dispense_access'))) { ?>
            <li class="header"><i class="fa fa-medkit"></i> PHARMACY</li>
            <li class="<?php echo ($currentTab === 'pharmacy') ? 'active' : ''; ?>">
                <a href="<?php echo base_url() ?>app/pharmacy">
                    <i class="fa fa-medkit"></i> <span>Dispensing Worklist</span>
                </a>
            </li>
            <?php } ?>
            
            <!-- ============================================== -->
            <!-- SECTION: WALK-IN REGISTRATION -->
            <!-- ============================================== -->
			<?php $canWalkin = (!$isDoctor) && (has_role(array('admin', 'cashier')) || (has_role('nurse') && has_privilege('walkin_access'))); ?>
			<?php if ($canWalkin) { ?>
            <li class="header"><i class="fa fa-street-view"></i> WALK-IN SERVICES</li>
            <li class="<?php echo ($currentTab === 'walkin') ? 'active' : ''; ?>">
                <a href="<?php echo base_url() ?>app/walkin">
                    <i class="fa fa-street-view text-aqua"></i> <span>Walk-In Registration</span>
                    <small class="label pull-right bg-aqua" style="font-size:9px;padding:2px 5px;">NEW</small>
                </a>
            </li>
            <li>
                <a href="<?php echo base_url() ?>app/walkin/register">
                    <i class="fa fa-user-plus text-green"></i> <span>Register Walk-In</span>
                </a>
            </li>
            <li>
                <a href="<?php echo base_url() ?>app/walkin/history">
                    <i class="fa fa-history"></i> <span>Walk-In History</span>
                </a>
            </li>
			<?php } ?>


            <?php if (!$isPharmacist && !$isDoctor && (has_role(array('admin', 'cashier', 'billing', 'accountant')) || $hasAccesstoBilling || has_privilege('billing_access'))) { ?>
            <li class="header"><i class="fa fa-money"></i> BILLING & FINANCE</li>
            
            <!-- Billing -->
            <li class="treeview <?php echo ($currentTab === 'billing' || $currentTab === 'ebilling' || $currentTab === 'unified_billing') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-file-text-o"></i> <span>Billing</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/cashier/payments"><i class="fa fa-dashboard"></i> Unified Billing</a></li>
                    <li><a href="<?php echo base_url() ?>app/billing/smart_billing"><i class="fa fa-plus-circle text-green"></i> Create Bill</a></li>
                    <?php if (has_role('admin')) { ?>
                    <li><a href="<?php echo base_url() ?>app/billing/smart_billing"><i class="fa fa-cog"></i> Smart Billing Config</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/billing/searchPatient"><i class="fa fa-search"></i> Search Bills</a></li>
                    <li><a href="<?php echo base_url() ?>app/cashier/billing_queue"><i class="fa fa-lock text-red"></i> Blocked Services</a></li>
                </ul>
            </li>

            <!-- Payments -->
            <li class="treeview">
                <a href="#">
                    <i class="fa fa-credit-card"></i> <span>Payments</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/cashier/payments"><i class="fa fa-money text-green"></i> Collect Payment</a></li>
                    <li><a href="<?php echo base_url() ?>app/billing_history"><i class="fa fa-history"></i> Payment History</a></li>
                    <li><a href="<?php echo base_url() ?>app/ebilling/refunds"><i class="fa fa-undo text-orange"></i> Refunds</a></li>
                </ul>
            </li>

            <!-- NHIS Claims (Single Location - No Duplicate) -->
            <?php if (has_role(array('admin', 'cashier', 'billing'))) { ?>
            <li class="treeview <?php echo ($currentTab === 'nhis') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-shield"></i> <span>NHIS Claims</span>
                    <?php if ($badgeCounts['nhis_alerts'] > 0) { ?>
                    <span class="label label-danger pull-right"><?php echo $badgeCounts['nhis_alerts']; ?></span>
                    <?php } else { ?>
                    <i class="fa fa-angle-left pull-right"></i>
                    <?php } ?>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/nhis"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis/claims"><i class="fa fa-file-text"></i> Claims List</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis_claims/submission_queue"><i class="fa fa-cloud-upload"></i> Submission Queue</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis_claims/claimit"><i class="fa fa-external-link text-green"></i> <strong>Claim-IT</strong></a></li>
                    <li class="divider"></li>
                    <li><a href="<?php echo base_url() ?>app/nhis/coverage"><i class="fa fa-check-circle"></i> Coverage Management</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis_claims/tariff_mapping"><i class="fa fa-tags"></i> Tariff Mapping</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis_claims/icd10_mapping"><i class="fa fa-stethoscope"></i> ICD-10 Codes</a></li>
                    <li class="divider"></li>
                    <li><a href="<?php echo base_url() ?>app/nhis/reconciliation"><i class="fa fa-balance-scale"></i> Reconciliation</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis/reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis/audit_log"><i class="fa fa-history"></i> Audit Log</a></li>
                </ul>
            </li>
            <?php } ?>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: REPORTS (Role-based content) -->
            <!-- ============================================== -->
            <?php if ($hasAccesstoReport || has_role(array('admin', 'nurse', 'receptionist', 'cashier'))) { ?>
            <li class="header"><i class="fa fa-bar-chart"></i> REPORTS</li>
            
            <li class="treeview <?php echo $reportsActive ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-line-chart"></i> <span>Reports Hub</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if (!$isDoctor) { ?>
                    <!-- Financial Reports (Hidden from Doctors) -->
                    <li class="header" style="padding:8px 15px;color:#8aa4af;font-size:10px;text-transform:uppercase;">Financial</li>
                    <li><a href="<?php echo base_url() ?>app/ebilling/analytics"><i class="fa fa-line-chart text-purple"></i> Revenue Analytics</a></li>
                    <li><a href="<?php echo base_url() ?>app/ebilling/department_report"><i class="fa fa-building"></i> Department Revenue</a></li>
                    <li><a href="<?php echo base_url() ?>app/ebilling/outstanding_report"><i class="fa fa-exclamation-triangle text-yellow"></i> Outstanding Balances</a></li>
                    <?php if ($hasAccesstoReportDailySales) { ?>
                    <li><a href="<?php echo base_url() ?>app/reports/daily_sales"><i class="fa fa-calendar"></i> Daily Sales</a></li>
                    <?php } ?>
                    <?php if (has_role('admin')) { ?>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports/nhis_cash"><i class="fa fa-shield"></i> NHIS vs Cash</a></li>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports/revenue"><i class="fa fa-money"></i> Revenue Report</a></li>
                    <?php } ?>
                    <?php } ?>
                    
                    <?php if (!$isCashierRole) { ?>
                    <!-- Analytics (Non-cashier) -->
                    <?php if (has_role('admin')) { ?>
                    <li class="header" style="padding:8px 15px;color:#8aa4af;font-size:10px;text-transform:uppercase;">Analytics</li>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports"><i class="fa fa-dashboard"></i> GHS Dashboard</a></li>
                    <?php } ?>
                    
                    <!-- Clinical Reports (Non-cashier) -->
                    <li class="header" style="padding:8px 15px;color:#8aa4af;font-size:10px;text-transform:uppercase;">Clinical</li>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports/opd_attendance"><i class="fa fa-stethoscope"></i> OPD Attendance</a></li>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports/diagnosis"><i class="fa fa-medkit"></i> Top Diagnoses</a></li>
                    <?php if ($hasAccesstoReportOPD) { ?>
                    <li><a href="<?php echo base_url() ?>app/reports/outpatient"><i class="fa fa-user-md"></i> Out Patient Report</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoReportAdmitted) { ?>
                    <li><a href="<?php echo base_url() ?>app/reports/inpatient"><i class="fa fa-bed"></i> Admitted Patients</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoReportDischarge) { ?>
                    <li><a href="<?php echo base_url() ?>app/reports/discharged_patient"><i class="fa fa-sign-out"></i> Discharged Patients</a></li>
                    <?php } ?>
                    
                    <!-- Patient Reports (Non-cashier) -->
                    <li class="header" style="padding:8px 15px;color:#8aa4af;font-size:10px;text-transform:uppercase;">Patients</li>
                    <?php if ($hasAccesstoReportPatient) { ?>
                    <li><a href="<?php echo base_url() ?>app/reports/patient_list"><i class="fa fa-users"></i> Patient Masterlist</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoReportIndividualPatient) { ?>
                    <li><a href="<?php echo base_url() ?>app/reports/patient_visited"><i class="fa fa-user"></i> Individual Patient</a></li>
                    <?php } ?>
                    
                    <?php if (!$isDoctor) { ?>
                    <!-- GHS/Compliance (Hidden from Doctors) -->
                    <li class="header" style="padding:8px 15px;color:#8aa4af;font-size:10px;text-transform:uppercase;">GHS Compliance</li>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports/daily_returns"><i class="fa fa-calendar-check-o"></i> Daily Returns</a></li>
                    <li><a href="<?php echo base_url() ?>app/ghs_reports/pharmacy_consumption"><i class="fa fa-flask"></i> Pharmacy Consumption</a></li>
                    <?php } ?>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: PATIENTS (Limited for Cashiers) -->
            <!-- ============================================== -->
            <?php if ($hasAccesstoPatient) { ?>
            <li class="header"><i class="fa fa-users"></i> PATIENTS</li>
            
            <li class="treeview <?php echo ($currentTab === 'patient') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-users"></i> <span><?php echo $isCashierRole ? 'Patient Search' : 'Patient Management'; ?></span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAddPatient && !$isCashierRole) { ?>
                    <li><a href="<?php echo base_url() ?>app/patient/addPatient"><i class="fa fa-user-plus"></i> Register Patient</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/patient"><i class="fa fa-search"></i> Search Patients</a></li>
                    <?php if (!$isCashierRole) { ?>
                    <li><a href="<?php echo base_url() ?>app/patient/masterlist"><i class="fa fa-list"></i> Patient Masterlist</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: NURSE MODULE (Role-specific) -->
            <!-- ============================================== -->

            <?php } ?>
            <?php if ($hasAccesstoNurse && ($isNurse || $isAdmin)) { ?>
            <li class="header"><i class="fa fa-plus-square"></i> NURSING</li>
            
            <li class="treeview <?php echo ($currentTab === 'nurse_module') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-plus-square"></i> <span>Nursing Workspace</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/nursing/dashboard"><i class="fa fa-dashboard"></i> Workspace Dashboard</a></li>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/vitals_queue"><i class="fa fa-heartbeat"></i> OPD Vitals Queue</a></li>
                    <?php if ($hasAccesstoNurseMedication) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/medication"><i class="fa fa-medkit"></i> Patient Medication</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoNurseVitalSign) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/vitalSign"><i class="fa fa-thermometer"></i> Vital Signs</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoNurseInOutTake) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/intake_output"><i class="fa fa-tint"></i> Intake/Output</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoNurseProgressNote) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/nurse_progress_note"><i class="fa fa-file-text"></i> Progress Notes</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoNurseBedSide) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/bed_side_procedure"><i class="fa fa-bed"></i> Bed Side Procedure</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoNurseIPRoomTransfer) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/room_transfer"><i class="fa fa-exchange"></i> Room Transfer</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoNurseDischarge) { ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/discharge_summary"><i class="fa fa-sign-out"></i> Discharge Summary</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/messages"><i class="fa fa-envelope"></i> Messages</a></li>
                    <li><a href="<?php echo base_url() ?>app/shift_task_controller"><i class="fa fa-tasks"></i> Shift Tasks</a></li>
                    <li><a href="<?php echo base_url() ?>app/nurse_module/consumable_order"><i class="fa fa-cubes text-teal"></i> Consumable Orders
                        <small class="label pull-right bg-teal" style="font-size:9px;padding:2px 5px;">NEW</small>
                    </a></li>
                </ul>
            </li>
            <?php } ?>

            <?php if (!$__nurseOnlySidebar) { ?>

            <!-- ============================================== -->
            <!-- SECTION: DOCTOR MODULE (Role-specific) -->
            <!-- ============================================== -->
            <?php if ($hasAccesstoDoctor && $isDoctor) { ?>
            <li class="header"><i class="fa fa-user-md"></i> DOCTOR</li>
            
            <li class="treeview <?php echo ($currentTab === 'doctor') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-user-md"></i> <span>Doctor Module</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoDoctorOPD) { ?>
                    <li><a href="<?php echo base_url() ?>app/doctor/opd"><i class="fa fa-stethoscope"></i> Out-Patient</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoDoctorIPD) { ?>
                    <li><a href="<?php echo base_url() ?>app/doctor/ipd"><i class="fa fa-bed"></i> In-Patient</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/doctor_messages/inbox"><i class="fa fa-envelope"></i> Messages
                        <?php if ((int)$doctor_message_unread_count > 0) { ?>
                        <span class="label label-danger"><?php echo (int)$doctor_message_unread_count; ?></span>
                        <?php } ?>
                    </a></li>
                    <li class="<?php echo ($currentModule === 'doctor_report_hub') ? 'active' : ''; ?>">
                        <a href="<?php echo base_url() ?>app/doctor/report_hub">
                            <i class="fa fa-bar-chart"></i> Report Hub
                            <span style="font-size:9px;padding:1px 5px;background:#2e7d32;color:#fff;border-radius:3px;margin-left:4px;vertical-align:middle;">GHS</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- EMR -->
            <?php if ($hasAccesstoEMR) { ?>
            <li class="treeview <?php echo ($currentTab === 'emr') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-book"></i> <span>EMR Sheet</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoEMROPD) { ?>
                    <li><a href="<?php echo base_url() ?>app/emr/opd"><i class="fa fa-file-text-o"></i> Out-Patient EMR</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoEMRIPD) { ?>
                    <li><a href="<?php echo base_url() ?>app/emr/ipd"><i class="fa fa-file-text"></i> In-Patient EMR</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: ADMINISTRATION (Admin Only) -->
            <!-- ============================================== -->
            <?php if ($hasAccesstoAdmin && $isAdmin) { ?>
            <li class="header"><i class="fa fa-cogs"></i> ADMINISTRATION</li>
            
            <!-- User Management -->
            <?php if ($hasAccesstoUsers) { ?>
            <li class="treeview <?php echo ($currentTab === 'user_mgnmt') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-users"></i> <span>Users</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAddUsers) { ?>
                    <li><a href="<?php echo base_url() ?>app/user/add"><i class="fa fa-plus"></i> Add User</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/user"><i class="fa fa-list"></i> User List</a></li>
                    <li><a href="<?php echo base_url() ?>app/roles"><i class="fa fa-key"></i> Roles</a></li>
                    <li><a href="<?php echo base_url() ?>app/staff_privileges"><i class="fa fa-shield"></i> Staff Privileges</a></li>
                </ul>
            </li>
            <?php } ?>

            <!-- Organization -->
            <li class="treeview">
                <a href="#">
                    <i class="fa fa-building"></i> <span>Organization</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAdminCompanyInfo) { ?>
                    <li><a href="<?php echo base_url() ?>app/company_information"><i class="fa fa-info-circle"></i> Company Info</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminDepartment) { ?>
                    <li><a href="<?php echo base_url() ?>app/department"><i class="fa fa-sitemap"></i> Departments</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminDesignation) { ?>
                    <li><a href="<?php echo base_url() ?>app/designation"><i class="fa fa-id-badge"></i> Designations</a></li>
                    <?php } ?>
                </ul>
            </li>

            <!-- Masters -->
            <li class="treeview <?php echo $adminActive ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-database"></i> <span>Masters</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAdminBillGroupName) { ?>
                    <li><a href="<?php echo base_url() ?>app/bill_group_name"><i class="fa fa-folder"></i> Billing Categories</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/price_list"><i class="fa fa-tags text-green"></i> <strong>Price List</strong></a></li>
                    <?php if ($hasAccesstoAdminParticularBill) { ?>
                    <li><a href="<?php echo base_url() ?>app/particular_bill"><i class="fa fa-list-alt"></i> Service Charges</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminDiagnosis) { ?>
                    <li><a href="<?php echo base_url() ?>app/diagnosis"><i class="fa fa-stethoscope"></i> Diagnoses</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminComplain) { ?>
                    <li><a href="<?php echo base_url() ?>app/complain"><i class="fa fa-comment"></i> Chief Complaints</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminInsuranceCompany) { ?>
                    <li><a href="<?php echo base_url() ?>app/insurance_company"><i class="fa fa-umbrella"></i> Insurance Companies</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminSurgicalPack) { ?>
                    <li><a href="<?php echo base_url() ?>app/surgical_package"><i class="fa fa-medkit"></i> Surgical Packages</a></li>
                    <?php } ?>
                    <li class="divider"></li>
                    <li><a href="<?php echo base_url() ?>app/test_catalog/lab_tests"><i class="fa fa-flask text-aqua"></i> Lab Test Catalog</a></li>
                    <li><a href="<?php echo base_url() ?>app/test_catalog/sonography_tests"><i class="fa fa-heartbeat text-purple"></i> Sonography Catalog</a></li>
                </ul>
            </li>

            <!-- Medicine Masters -->
            <?php if ($hasAccesstoAdminMedicineCategory || $hasAccesstoAdminDrugName || $isPharmacist) { ?>
            <li class="treeview <?php echo ($currentSubtab === 'medicine_master') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-pills"></i> <span>Medicine Masters</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAdminMedicineCategory) { ?>
                    <li><a href="<?php echo base_url() ?>app/medicine_category"><i class="fa fa-folder-open"></i> Categories</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminDrugName || $isPharmacist) { ?>
                    <li><a href="<?php echo base_url() ?>app/drug_name"><i class="fa fa-capsules"></i> Drug Names</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>

            <!-- Facility -->
            <?php if ($hasAccesstoRooms) { ?>
            <li class="treeview <?php echo ($currentTab === 'room_m') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-hospital-o"></i> <span>Facility</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoRoomsEnquiry) { ?>
                    <li><a href="<?php echo base_url() ?>app/room_enquiry"><i class="fa fa-search"></i> Room Enquiry</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/room_category"><i class="fa fa-tags"></i> Room Categories</a></li>
                    <li><a href="<?php echo base_url() ?>app/room_master"><i class="fa fa-door-open"></i> Rooms</a></li>
                    <li><a href="<?php echo base_url() ?>app/room_bed"><i class="fa fa-bed"></i> Beds</a></li>
                </ul>
            </li>
            <?php } ?>
            <?php } ?>

            <!-- ============================================== -->
            <!-- SECTION: SYSTEM (Admin Only) -->
            <!-- ============================================== -->
            <?php if ($isAdmin) { ?>
            <li class="header"><i class="fa fa-server"></i> SYSTEM</li>
            
            <li class="treeview <?php echo $systemActive ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-cog"></i> <span>Settings</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAdminParameters) { ?>
                    <li><a href="<?php echo base_url() ?>app/parameters"><i class="fa fa-sliders"></i> Parameters</a></li>
                    <?php } ?>
                    <li><a href="<?php echo base_url() ?>app/ebilling/notifications"><i class="fa fa-bell"></i> Notifications</a></li>
                    <li><a href="<?php echo base_url() ?>app/ebilling/permissions"><i class="fa fa-lock"></i> Permissions</a></li>
                </ul>
            </li>

            <li class="treeview">
                <a href="#">
                    <i class="fa fa-history"></i> <span>Audit</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/ebilling/audit_logs"><i class="fa fa-list"></i> Activity Logs</a></li>
                    <li><a href="<?php echo base_url() ?>app/nhis/audit_log"><i class="fa fa-shield"></i> NHIS Audit</a></li>
                    <li><a href="<?php echo base_url() ?>app/ebilling/reconciliation_dashboard"><i class="fa fa-balance-scale"></i> Financial Audit</a></li>
                </ul>
            </li>

            <li class="treeview">
                <a href="#">
                    <i class="fa fa-balance-scale"></i> <span>Reconciliation</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>app/billing_reconciliation"><i class="fa fa-dashboard"></i> Reconciliation Dashboard</a></li>
                    <li><a href="<?php echo base_url() ?>app/billing_reconciliation/legacy_procedure_locks"><i class="fa fa-history text-yellow"></i> Legacy Procedure Locks</a></li>
                </ul>
            </li>

            <li class="treeview">
                <a href="#">
                    <i class="fa fa-wrench"></i> <span>Maintenance</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <?php if ($hasAccesstoAdminBackup) { ?>
                    <li><a href="<?php echo base_url() ?>app/backup"><i class="fa fa-database"></i> Backup Database</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminPages) { ?>
                    <li><a href="<?php echo base_url() ?>app/pages"><i class="fa fa-file-code-o"></i> System Pages</a></li>
                    <?php } ?>
                    <?php if ($hasAccesstoAdminAckReceipt) { ?>
                    <li><a href="<?php echo base_url() ?>app/declared_receipt"><i class="fa fa-check-square"></i> Receipt Declarations</a></li>
                    <?php } ?>
                </ul>
            </li>
            <?php } ?>

            <?php } ?>

            <!-- ============================================== -->
            <!-- USER PROFILE (Always visible) -->
            <!-- ============================================== -->
            <li class="header"><i class="fa fa-user"></i> ACCOUNT</li>
            
            <li class="treeview <?php echo ($currentTab === 'profile') ? 'active' : ''; ?>">
                <a href="#">
                    <i class="fa fa-user-circle"></i> <span>My Account</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li><a href="<?php echo base_url() ?>myprofile"><i class="fa fa-id-card"></i> My Profile</a></li>
                    <li><a href="<?php echo base_url() ?>myprofile/editprofile"><i class="fa fa-edit"></i> Edit Profile</a></li>
                    <li><a href="<?php echo base_url() ?>login/logout"><i class="fa fa-sign-out text-red"></i> Logout</a></li>
                </ul>
            </li>

        </ul>

        <!-- Sidebar Search Script -->
        <script type="text/javascript">
        (function(){
            var input = document.getElementById('hms-sidebar-search');
            if (!input) return;
            var menu = document.querySelector('ul.sidebar-menu');
            if (!menu) return;
            function textOf(el){
                return (el.textContent || el.innerText || '').replace(/\s+/g, ' ').trim().toLowerCase();
            }
            function setDisplay(el, show){
                el.style.display = show ? '' : 'none';
            }
            input.addEventListener('input', function(){
                var q = (input.value || '').trim().toLowerCase();
                var items = menu.children;
                for (var i = 0; i < items.length; i++) {
                    var li = items[i];
                    if (!li || li.tagName.toLowerCase() !== 'li') continue;
                    // Skip section headers
                    if (li.classList.contains('header')) {
                        setDisplay(li, q === '');
                        continue;
                    }
                    if (q === '') {
                        setDisplay(li, true);
                        var sub = li.querySelector('.treeview-menu');
                        if (sub) sub.style.display = '';
                        continue;
                    }
                    var hit = textOf(li).indexOf(q) !== -1;
                    if (!hit) {
                        var subMenu = li.querySelector('.treeview-menu');
                        if (subMenu) {
                            var subLis = subMenu.querySelectorAll('li');
                            var anySubHit = false;
                            for (var j = 0; j < subLis.length; j++) {
                                if (textOf(subLis[j]).indexOf(q) !== -1) { anySubHit = true; break; }
                            }
                            hit = anySubHit;
                            subMenu.style.display = anySubHit ? 'block' : '';
                        }
                    }
                    setDisplay(li, hit);
                }
            });
        })();
        </script>
    </section>
</aside>

<style type="text/css">
/* Modern Sidebar Styles */
.sidebar-menu > li.header {
    padding: 12px 15px 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #8aa4af;
    letter-spacing: 0.5px;
    border-top: 1px solid rgba(255,255,255,0.05);
    margin-top: 8px;
}

.sidebar-menu > li.header:first-child {
    border-top: none;
    margin-top: 0;
}

.sidebar-menu > li.header i {
    margin-right: 6px;
    font-size: 10px;
}

.sidebar-menu .treeview-menu > li.header {
    padding: 8px 15px;
    font-size: 10px;
    color: #6c8793;
    border-top: 1px solid rgba(255,255,255,0.03);
    margin-top: 5px;
}

.sidebar-menu .treeview-menu > li.divider {
    height: 1px;
    margin: 8px 15px;
    background-color: rgba(255,255,255,0.05);
}

.sidebar-menu > li > a {
    padding: 10px 15px;
    transition: all 0.2s ease;
}

.sidebar-menu > li > a:hover {
    background-color: rgba(255,255,255,0.05);
}

.sidebar-menu > li.active > a {
    border-left: 3px solid #3c8dbc;
    background-color: rgba(60,141,188,0.1);
}

.hms-sidebar-search {
    padding: 10px 15px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}

.hms-sidebar-search input {
    background: rgba(255,255,255,0.1);
    border: none;
    color: #fff;
    border-radius: 4px;
}

.hms-sidebar-search input::placeholder {
    color: rgba(255,255,255,0.5);
}

.hms-sidebar-search input:focus {
    background: rgba(255,255,255,0.15);
    outline: none;
}

/* Badge styling */
.sidebar-menu .label {
    font-size: 10px;
    padding: 2px 6px;
}

/* Quick Actions styling */
.sidebar-menu > li.quick-action > a {
    background: linear-gradient(135deg, rgba(60,141,188,0.08) 0%, rgba(60,141,188,0.02) 100%);
    border-left: 2px solid #3c8dbc;
    padding-left: 13px;
}

.sidebar-menu > li.quick-action > a:hover {
    background: linear-gradient(135deg, rgba(60,141,188,0.15) 0%, rgba(60,141,188,0.05) 100%);
    padding-left: 16px;
}

.sidebar-menu > li.quick-action > a > i {
    width: 20px;
    text-align: center;
}

/* Smooth transitions */
.treeview-menu {
    transition: all 0.3s ease;
}

/* Collapsible sidebar support */
.sidebar-collapse .sidebar-menu > li.header {
    display: none;
}

.sidebar-collapse .sidebar-menu > li > a > span {
    display: none;
}

.sidebar-collapse .sidebar-menu > li > a {
    text-align: center;
    padding: 12px 5px;
}

.sidebar-collapse .sidebar-menu > li > a > i {
    font-size: 18px;
    margin-right: 0;
}

.sidebar-collapse .sidebar-menu > li.quick-action > a {
    border-left: none;
    border-bottom: 2px solid #3c8dbc;
}

/* Tooltip for collapsed sidebar */
.sidebar-collapse .sidebar-menu > li > a[title] {
    position: relative;
}

/* Keyboard focus styles */
.sidebar-menu > li > a:focus {
    outline: 2px solid #3c8dbc;
    outline-offset: -2px;
}

/* Mobile responsiveness */
@media (max-width: 767px) {
    .sidebar-menu > li > a {
        padding: 12px 15px;
    }
    
    .sidebar-menu > li.quick-action > a {
        padding: 12px 15px 12px 13px;
    }
}

/* Footer styling */
#footerLO {
    background-color: #1f1c1c;
    height: 35px;
    color: #f8d756;
    bottom: 0;
    position: fixed;
    width: 100%;
    padding: 10px;
    margin-bottom: 0px;
    z-index: 99999;
}

.linkFooter {
    color: #f8d756 !important;
}

.linkFooter:hover {
    color: #f8d756 !important;
    text-decoration: underline !important;
}
</style>

<!-- Modal for POS Patient Search (preserved for backward compatibility) -->
<script language="javascript">
    function showInvoice(patient_id) {
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                document.getElementById("showInvoice").innerHTML = xmlhttp.responseText;
            }
        }
        xmlhttp.open("GET", "<?php echo base_url(); ?>app/billing/getItem/" + patient_id, true);
        xmlhttp.send();
    }
</script>

<form method="post">
    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
    <div class="modal fade" id="posPatient" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                    <h4 class="modal-title" id="myModalLabel">Search Patient</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group" id="credit">
                        <label for="exampleInputEmail1">Patient Name</label>
                        <select name="patient" id="patient" class="form-control input-sm" style="width:250px;" onChange="showInvoice(this.value);">
                            <option value="">- Select Patient -</option>
                            <?php if (isset($patientListRows) && is_array($patientListRows)) { foreach ($patientListRows as $patientListRows) { ?>
                                <option value="<?php echo $patientListRows->patient_no ?>"><?php echo $patientListRows->name ?></option>
                            <?php } } ?>
                        </select>
                    </div>
                    <div class="form-group" id="credit">
                        <label for="exampleInputEmail1">Invoice No.</label>
                        <span id="showInvoice">
                            <select name="invoice" id="invoice" class="form-control input-sm" style="width: 250px;" required>
                                <option value="">- Invoice No. -</option>
                            </select>
                        </span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Continue</button>
                </div>
            </div>
        </div>
    </div>
</form>
