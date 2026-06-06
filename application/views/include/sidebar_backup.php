<?php
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
$editprofile_mod = "";
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

if (!isset($userInfo) || !is_object($userInfo)) {
	if (isset($this->data) && isset($this->data['userInfo']) && is_object($this->data['userInfo'])) {
		$userInfo = $this->data['userInfo'];
	} else if (isset($this->general_model)) {
		$userInfo = $this->general_model->getUserLoggedIn($this->session->userdata('username'));
	}
}

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

if ($this->session->userdata('module') == "room_category") {
    $sub_room_category_mod = "class='active'";
}
if ($this->session->userdata('module') == "room_master") {
    $sub_room_master_mod = "class='active'";
}
if ($this->session->userdata('module') == "room_bed_master") {
    $sub_room_bed_mod = "class='active'";
}
if ($this->session->userdata('module') == "room_enquiry") {
    $room_enquiry = "class='active'";
}


if ($this->session->userdata('tab') == "admin") {
    $admin = "active";
}
if ($this->session->userdata('tab') == "nurse_module") {
    $nurse_module = "active";
}
if ($this->session->userdata('module') == "nurse_vitals_queue") {
    $nurse_vitals_queue = "class='active'";
}
if ($this->session->userdata('tab') == "configuration") {
    $configuration = "active";
}
if ($this->session->userdata('tab') == "profile") {
    $profile = "active";
}
if ($this->session->userdata('tab') == "patient") {
    $patient = "active";
}
if ($this->session->userdata('tab') == "doctor") {
    $doctor = "active";
}
if ($this->session->userdata('tab') == "laboratory") {
    $laboratory = "active";
}
if ($this->session->userdata('tab') == "sonography") {
    $sonography = "active";
}
if ($this->session->userdata('tab') == "emr") {
    $emr = "active";
}
if ($this->session->userdata('tab') == "billing") {
    $billing = "active";
}
if ($this->session->userdata('tab') == "pharmacy") {
    $pharmacy = "active";
}
if ($this->session->userdata('tab') == "user_mgnmt") {
    $user_mgnmt = "active";
}
if ($this->session->userdata('tab') == "reports") {
    $reports = "active";
}
if ($this->session->userdata('tab') == "ghs_reports") {
    $ghs_reports = "active";
}
if ($this->session->userdata('tab') == "room_m") {
    $room_m = "active";
}
if ($this->session->userdata('tab') == "appointment") {
    $appointmentTab = "active";
}

//OPD

//NURSE
if ($this->session->userdata('module') == "nurse_medication") {
    $nurse_medication = "class='active'";
}
if ($this->session->userdata('module') == "nurse_intake_output") {
    $nurse_intake_output = "class='active'";
}
if ($this->session->userdata('module') == "nurse_progress_note") {
    $nurse_progress_note = "class='active'";
}
if ($this->session->userdata('module') == "nurse_messages") {
    $nurse_messages = "class='active'";
}
if ($this->session->userdata('module') == "nurse_shift_tasks") {
    $nurse_shift_tasks = "class='active'";
}
if ($this->session->userdata('module') == "nurse_vital_sign") {
    $nurse_vital_sign = "class='active'";
}
if ($this->session->userdata('module') == "nurse_room_transfer") {
    $nurse_room_transfer = "class='active'";
}
if ($this->session->userdata('module') == "nurse_patientHistory") {
    $nurse_patientHistory = "class='active'";
}
if ($this->session->userdata('module') == "nurse_discharge_summary") {
    $nurse_discharge_summary = "class='active'";
}
if ($this->session->userdata('module') == "nurse_bed_side") {
    $nurse_bed_side = "class='active'";
}
if ($this->session->userdata('module') == "nurse_diagnosis") {
    $nurse_diagnosis = "class='active'";
}

//APPOINTMENT PATIENT
if ($this->session->userdata('module') == "add_appointment") {
    $appointmentList = "class='active'";
}
if ($this->session->userdata('module') == "add_appointment") {
    $appointmentAdd = "class='active'";
}

//PATIENT MANAGEMENT
if ($this->session->userdata('module') == "add_new_patient") {
    $addNew_patient_mode = "class='active'";
}
if ($this->session->userdata('module') == "patient_master") {
    $patient_master_mode = "class='active'";
}
if ($this->session->userdata('module') == "patient_history") {
    $patient_history_mode = "class='active'";
}
if ($this->session->userdata('module') == "bill_history") {
    $bill_history_mod = "class='active'";
}
if ($this->session->userdata('module') == "OR_history") {
    $OR_history_mod = "class='active'";
}

//DOCTOR
if ($this->session->userdata('module') == "opd_doctor") {
    $opd_doctor = "class='active'";
}
if ($this->session->userdata('module') == "ipd_doctor") {
    $ipd_doctor = "class='active'";
}
if ($this->session->userdata('module') == "doctor_messages") {
    $doctor_messages = "class='active'";
}

//LABORATORY
if ($this->session->userdata('module') == "iopd_laboratory") {
    $opd_doctor = "class='active'";
}
if ($this->session->userdata('module') == "sonography") {
    $sonography_mod = "class='active'";
}
// if($this->session->userdata('module') == "ipd_doctor"){$ipd_doctor = "class='active'";} 

//EMR 
if ($this->session->userdata('module') == "opd_emr") {
    $opd_emr = "class='active'";
}
if ($this->session->userdata('module') == "ipd_emr") {
    $ipd_emr = "class='active'";
}

//USER
if ($this->session->userdata('module') == "add_user") {
    $add_user = "class='active'";
}
if ($this->session->userdata('module') == "user_index") {
    $user_index = "class='active'";
}

//SUBTAB
if ($this->session->userdata('subtab') == "room_mangement") {
    $subtree_room = "active";
}
if ($this->session->userdata('subtab') == "opd") {
    $opd = "active";
}
if ($this->session->userdata('subtab') == "ipd") {
    $ipd = "active";
}
if ($this->session->userdata('subtab') == "medicine_master") {
    $medicine = "active";
}
if ($this->session->userdata('subtab') == "opd_reports") {
    $opd_reports = "active";
}

//SUBMODULE
if ($this->session->userdata('submodule') == "opd_registration") {
    $opd_registration = "class='active'";
}
if ($this->session->userdata('submodule') == "opd_master") {
    $opd_master = "class='active'";
}
if ($this->session->userdata('submodule') == "medicine_category") {
    $med_cat_mod = "class='active'";
}
if ($this->session->userdata('submodule') == "drug_master") {
    $drug_mod = "class='active'";
}
if ($this->session->userdata('submodule') == "ipd_registration") {
    $ipd_registration = "class='active'";
}
if ($this->session->userdata('submodule') == "ipd_master") {
    $ipd_master = "class='active'";
}
if ($this->session->userdata('submodule') == "opd_diagnosis_reports") {
    $opd_diagnosis_reports = "class='active'";
}

//BILLING
if ($this->session->userdata('pos') == "user") {
    $pos_mod = "class='active'";
}
if ($this->session->userdata('module') == "pharmacy_worklist") {
    $pharmacy_worklist = "class='active'";
}
if ($this->session->userdata('module') == "pharmacy_stock") {
    $pharmacy_stock = "class='active'";
}
if ($this->session->userdata('module') == "pharmacy_alerts") {
    $pharmacy_alerts = "class='active'";
}
if ($this->session->userdata('module') == "pending_approvals") {
    $pending_approvals = "class='active'";
}
if ($this->session->userdata('module') == "staff_privileges") {
    $staff_privileges_mod = "class='active'";
}
if ($this->session->userdata('module') == "ghs_dashboard") {
    $ghs_dashboard = "class='active'";
}
if ($this->session->userdata('module') == "ghs_opd_attendance") {
    $ghs_opd_attendance = "class='active'";
}
if ($this->session->userdata('module') == "ghs_diagnosis") {
    $ghs_diagnosis = "class='active'";
}
if ($this->session->userdata('module') == "ghs_pharmacy") {
    $ghs_pharmacy = "class='active'";
}
if ($this->session->userdata('module') == "ghs_nhis_cash") {
    $ghs_nhis_cash = "class='active'";
}
if ($this->session->userdata('module') == "ghs_revenue") {
    $ghs_revenue = "class='active'";
}
if ($this->session->userdata('module') == "ghs_daily_returns") {
    $ghs_daily_returns = "class='active'";
}
if ($this->session->userdata('tab') == "nhis") {
    $nhis_mod = "active";
}
if ($this->session->userdata('module') == "nhis_dashboard") {
    $nhis_dashboard = "class='active'";
}
if ($this->session->userdata('module') == "nhis_claims") {
    $nhis_claims = "class='active'";
}
if ($this->session->userdata('module') == "nhis_coverage") {
    $nhis_coverage = "class='active'";
}
if ($this->session->userdata('module') == "nhis_reconciliation") {
    $nhis_reconciliation = "class='active'";
}
if ($this->session->userdata('module') == "nhis_reports") {
    $nhis_reports = "class='active'";
}
if ($this->session->userdata('module') == "nhis_audit") {
    $nhis_audit = "class='active'";
}
if ($this->session->userdata('module') == "receipt_lists") {
    $receipt_mod = "class='active'";
}
if ($this->session->userdata('module') == "surgical_costing") {
    $surgical_costing = "class='active'";
}

//ADMIN MODULE
if ($this->session->userdata('module') == "user") {
    $user_mod = "class='active'";
}
if ($this->session->userdata('module') == "designation") {
    $designation_mod = "class='active'";
}
if ($this->session->userdata('module') == "department") {
    $department_mod = "class='active'";
}
if ($this->session->userdata('module') == "group_name") {
    $group_name_mod = "class='active'";
}
if ($this->session->userdata('module') == "particular_bill") {
    $particular_bill_mod = "class='active'";
}
if ($this->session->userdata('module') == "complain") {
    $complain_mod = "class='active'";
}
if ($this->session->userdata('module') == "diagnosis") {
    $diagnosis_mod = "class='active'";
}
if ($this->session->userdata('module') == "insurance_company") {
    $insurance_company_mod = "class='active'";
}
if ($this->session->userdata('module') == "company_information") {
    $company_information = "class='active'";
}
if ($this->session->userdata('module') == "nhis_claims") {
    $nhis_claims_mod = "class='active'";
}
if ($this->session->userdata('module') == "surgical_package") {
    $surgical_package = "class='active'";
}
if ($this->session->userdata('module') == "backup_database") {
    $backup = "class='active'";
}
if ($this->session->userdata('module') == "declared_receipt") {
    $declared_receipt_mod = "class='active'";
}

//REPORTS
if ($this->session->userdata('module') == "daily_reports") {
    $daily_reports_mod = "class='active'";
}
if ($this->session->userdata('module') == "patient_list_report") {
    $patient_list_report_mod = "class='active'";
}
if ($this->session->userdata('module') == "patient_visited_report") {
    $patient_visited_report = "class='active'";
}
if ($this->session->userdata('module') == "outpatient_report") {
    $outpatient_report = "class='active'";
}
if ($this->session->userdata('module') == "inpatient_report") {
    $inpatient_report = "class='active'";
}
if ($this->session->userdata('module') == "discharged_patient_report") {
    $discharged_patient_report = "class='active'";
}
if ($this->session->userdata('module') == "declared_receipt") {
    $declared_receipt_mod = "class='active'";
}

//CONFIGURATION MODULE
if ($this->session->userdata('module') == "pages") {
    $pages_mod = "class='active'";
}
if ($this->session->userdata('module') == "roles") {
    $roles_mod = "class='active'";
}


//MY PROFILE MODULE
if ($this->session->userdata('module') == "myprofile") {
    $myprofile_mod = "class='active'";
}
if ($this->session->userdata('module') == "editprofile") {
    $editprofile_mod = "class='active'";
}
if ($this->session->userdata('module') == "parameters") {
    $param_mod = "class='active'";
}
if ($this->session->userdata('module') == "change_pwd") {
    $change_pwd_mod = "class='active'";
}
?>


<!-- Left side column. contains the logo and sidebar -->
<aside class="left-side sidebar-offcanvas">
    <!-- sidebar: style can be found in sidebar.less -->
    <section class="sidebar">
        <!-- User panel removed — profile is now in the header dropdown -->

		<div class="hms-sidebar-search">
			<input type="text" id="hms-sidebar-search" class="form-control input-sm" placeholder="Search menu..." autocomplete="off" />
		</div>

        <!-- <form action="#" method="get" class="sidebar-form">
                        <div class="input-group">
                            <input type="text" name="q" class="form-control" placeholder="Control No"/>
                            <span class="input-group-btn">
                                <button type='submit' name='seach' id='search-btn' class="btn btn-flat"><i class="fa fa-search"></i></button>
                                
                            </span>
                        </div>
                    </form> -->
        <!-- /.search form -->
        <!-- sidebar menu: : style can be found in sidebar.less -->
        <ul class="sidebar-menu">

            <li>
                <a href="<?php echo base_url() ?>app/dashboard">
                    <i class="fa fa-dashboard"></i> <span>Dashboard</span>
                </a>
            </li>

            <!-- ============================================== -->
            <!-- OLD CASHIER MENU - DEPRECATED (Redirects to Billing & Finance) -->
            <!-- This menu is hidden. All cashier functions now in Billing & Finance -->
            <!-- ============================================== -->
            <?php /* LEGACY CASHIER MENU DISABLED - DO NOT DELETE FOR BACKWARD COMPATIBILITY
            <?php 
            $showBillingMenu = $hasAccesstoBilling || has_role('cashier') || has_privilege('cashier_access') || has_privilege('billing_access');
            $pendingBillingCount = 0;
            ?>
            <?php if ($showBillingMenu) { ?>
                <li class="treeview <?php echo $billing; ?>">
                    <a href="#">
                        <i class="fa fa-credit-card"></i> <span>Legacy Cashier</span>
                        <span class="label label-danger pull-right">OLD</span>
                    </a>
                    <ul class="treeview-menu">
                        <li><a href="<?php echo base_url() ?>app/ebilling"><i class="fa fa-arrow-right"></i> Go to Billing & Finance</a></li>
                    </ul>
                </li>
            <?php } ?>
            END LEGACY CASHIER MENU */ ?>

            <?php if ((isset($hasAccesstoPharmacy) && $hasAccesstoPharmacy && has_role(array('pharmacist', 'doctor'))) || has_privilege('pharmacy_access')) { ?>
                <li class="treeview <?php echo $pharmacy; ?>">
                    <a href="#">
                        <i class="fa fa-medkit"></i> <span>Pharmacy</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <li <?php echo $pharmacy_worklist; ?>><a href="<?php echo base_url() ?>app/pharmacy"><i class="fa fa-angle-double-right"></i>Pharmacy Worklist</a></li>
                        <li <?php echo $pharmacy_stock; ?>><a href="<?php echo base_url() ?>app/pharmacy/stock"><i class="fa fa-cubes"></i> Stock Management</a></li>
                        <li <?php echo $pharmacy_alerts; ?>><a href="<?php echo base_url() ?>app/pharmacy/alerts"><i class="fa fa-bell"></i> Pharmacy Alerts</a></li>
                        <?php if (has_role('admin')) { ?>
                        <li <?php echo $pending_approvals; ?>><a href="<?php echo base_url() ?>app/stock_approval"><i class="fa fa-check-square-o"></i> Pending Approvals<?php
                            $__pendCnt = 0;
                            if (isset($this) && isset($this->governance_model) && method_exists($this->governance_model, 'count_pending_stock_requests')) {
                                $__pendCnt = $this->governance_model->count_pending_stock_requests();
                            }
                            if ($__pendCnt > 0) { echo ' <span class="label label-danger pull-right">'.$__pendCnt.'</span>'; }
                        ?></a></li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>

            <!-- ============================================== -->
            <!-- BILLING & FINANCE (Primary Financial System) -->
            <!-- ============================================== -->
            <?php if (has_role(array('admin', 'cashier', 'billing', 'accountant')) || $hasAccesstoBilling || has_privilege('cashier_access') || has_privilege('billing_access')) { ?>
                <li class="treeview <?php echo ($this->session->userdata('tab') == 'ebilling' || $billing == 'active') ? 'active' : ''; ?>">
                    <a href="#">
                        <i class="fa fa-university"></i> <span>Billing & Finance</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <!-- Dashboard -->
                        <li><a href="<?php echo base_url() ?>app/ebilling"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        
                        <!-- Billing Section -->
                        <li class="header" style="padding:10px 15px;color:#8aa4af;font-size:11px;text-transform:uppercase;font-weight:600;">
                            <i class="fa fa-file-text-o"></i> Billing
                        </li>
                        <li><a href="<?php echo base_url() ?>app/billing/smart_billing"><i class="fa fa-plus-circle text-green"></i> Create Bill</a></li>
                        <li><a href="<?php echo base_url() ?>app/billing/searchPatient"><i class="fa fa-search text-aqua"></i> Search Bills</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/blocked_services"><i class="fa fa-lock text-red"></i> Blocked Services</a></li>
                        
                        <!-- Payments Section -->
                        <li class="header" style="padding:10px 15px;color:#8aa4af;font-size:11px;text-transform:uppercase;font-weight:600;">
                            <i class="fa fa-money"></i> Payments
                        </li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/collect_payment"><i class="fa fa-credit-card text-green"></i> Collect Payment</a></li>
                        <li><a href="<?php echo base_url() ?>app/billing_history"><i class="fa fa-list text-aqua"></i> Payment History</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/refunds"><i class="fa fa-undo text-orange"></i> Refunds</a></li>
                        
                        <!-- Reports Section -->
                        <li class="header" style="padding:10px 15px;color:#8aa4af;font-size:11px;text-transform:uppercase;font-weight:600;">
                            <i class="fa fa-bar-chart"></i> Reports
                        </li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/analytics"><i class="fa fa-line-chart text-purple"></i> Analytics Dashboard</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/daily_report"><i class="fa fa-calendar"></i> Daily Report</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/department_report"><i class="fa fa-building"></i> Department Revenue</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/outstanding_report"><i class="fa fa-exclamation-triangle text-yellow"></i> Outstanding Balances</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/reconciliation_dashboard"><i class="fa fa-balance-scale"></i> Reconciliation</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/cashier_reconciliation"><i class="fa fa-calculator text-aqua"></i> Cashier Reconciliation</a></li>
                        
                        <?php if (has_role('admin')) { ?>
                        <!-- Admin Tools -->
                        <li class="header" style="padding:10px 15px;color:#8aa4af;font-size:11px;text-transform:uppercase;font-weight:600;">
                            <i class="fa fa-cog"></i> Admin Tools
                        </li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/ledger"><i class="fa fa-book text-olive"></i> Financial Ledger</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/audit_logs"><i class="fa fa-history text-gray"></i> Audit Logs</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/refund_management"><i class="fa fa-undo text-orange"></i> Refund Management</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/notifications"><i class="fa fa-bell text-yellow"></i> Notifications</a></li>
                        <li><a href="<?php echo base_url() ?>app/ebilling/permissions"><i class="fa fa-shield text-red"></i> Permissions</a></li>
                        <?php } ?>
                        
                        <?php if ($hasAccesstoSurgical) { ?>
                        <li><a href="<?php echo base_url() ?>app/surgical_costing"><i class="fa fa-calculator"></i> Surgical Costing</a></li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>

            <!-- NHIS Claims Management -->
            <?php if (has_role(array('admin', 'cashier', 'billing'))) { ?>
                <li class="treeview <?php echo $nhis_mod; ?>">
                    <a href="#">
                        <i class="fa fa-shield"></i> <span>NHIS Claims</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <li <?php echo $nhis_dashboard; ?>><a href="<?php echo base_url() ?>app/nhis"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li <?php echo $nhis_claims; ?>><a href="<?php echo base_url() ?>app/nhis/claims"><i class="fa fa-file-text"></i> Claims List</a></li>
                        <li <?php echo $nhis_coverage; ?>><a href="<?php echo base_url() ?>app/nhis/coverage"><i class="fa fa-check-circle"></i> Coverage Management</a></li>
                        <li <?php echo $nhis_reconciliation; ?>><a href="<?php echo base_url() ?>app/nhis/reconciliation"><i class="fa fa-balance-scale"></i> Reconciliation</a></li>
                        <li <?php echo $nhis_reports; ?>><a href="<?php echo base_url() ?>app/nhis/reports"><i class="fa fa-bar-chart"></i> Reports</a></li>
                        <li <?php echo $nhis_audit; ?>><a href="<?php echo base_url() ?>app/nhis/audit_log"><i class="fa fa-history"></i> Audit Log</a></li>
                        <li class="divider"></li>
                        <li><a href="<?php echo base_url() ?>app/nhis_claims/claimit"><i class="fa fa-cloud-upload text-green"></i> <strong>Claim-IT</strong></a></li>
                        <li><a href="<?php echo base_url() ?>app/nhis_claims/submission_queue"><i class="fa fa-list"></i> Submission Queue</a></li>
                        <li><a href="<?php echo base_url() ?>app/nhis_claims/icd10_mapping"><i class="fa fa-stethoscope"></i> ICD-10 Codes</a></li>
                        <li><a href="<?php echo base_url() ?>app/nhis_claims/tariff_mapping"><i class="fa fa-tags"></i> Tariff Mapping</a></li>
                        <li><a href="<?php echo base_url() ?>app/nhis_claims/api_logs"><i class="fa fa-history"></i> API Logs</a></li>
                    </ul>
                </li>
            <?php } ?>

            <!--START OF Appointment-->
            <?php if ($hasAccesstoAppointment && has_role(array('doctor', 'cashier', 'receptionist'))) { ?>
                <li class="treeview <?php echo $appointmentTab; ?>">
                    <a href="#">
                        <i class="fa fa fa-male"></i> <span>Patient Appointment</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoAddAppointment) { ?><li <?php echo $appointmentAdd; ?>><a href="<?php echo base_url() ?>app/appointment/addAppointmentList"><i class="fa fa-angle-double-right"></i>Add New Appointment</a></li> <?php } ?>
                        <?php if ($hasAccesstoAppointment) { ?><li <?php echo $appointmentList; ?>><a href="<?php echo base_url() ?>app/appointment/index"><i class="fa fa-angle-double-right"></i>Manage Appointment</a></li><?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Appointment-->


            <!--START OF Patient Management-->
            <?php if ($hasAccesstoPatient && has_role(array('doctor', 'cashier', 'receptionist'))) { ?>
                <li class="treeview <?php echo $patient; ?>">
                    <a href="#">
                        <i class="fa fa fa-wheelchair"></i> <span>Patient Management</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoAddPatient) { ?><li <?php echo $addNew_patient_mode; ?>><a href="<?php echo base_url() ?>app/patient/addPatient"><i class="fa fa-angle-double-right"></i>Add New Patient</a></li> <?php } ?>
                        <?php if ($hasAccesstoPatient) { ?><li <?php echo $patient_master_mode; ?>><a href="<?php echo base_url() ?>app/patient/index"><i class="fa fa-angle-double-right"></i>Patient Master</a></li><?php } ?>
                        <li <?php echo $patient_history_mode; ?>><a href="<?php echo base_url() ?>app/patient"><i class="fa fa-history"></i> Patient History</a></li>
                        <?php if ($hasAccesstoOPDRegistration == TRUE && $hasAccesstoOPDEnquiry == TRUE) { ?>
                            <li class="treeview <?php echo $opd; ?>">
                                <a href="#">
                                    <i class="fa fa-angle-double-right"></i><span>OPD</span>
                                    <i class="fa fa-angle-left pull-right"></i>
                                </a>
                                <ul class="treeview-menu">
                                    <?php if ($hasAccesstoOPDRegistration) { ?><li <?php echo $opd_registration; ?>><a href="<?php echo base_url() ?>app/opd/registration"><i class="fa fa-angle-double-right"></i>OPD Registration</a></li><?php } ?>
                                    <?php if ($hasAccesstoOPDEnquiry) { ?><li <?php echo $opd_master; ?>><a href="<?php echo base_url() ?>app/opd/index"><i class="fa fa-angle-double-right"></i>Out-Patient Enquiry</a></li> <?php } ?>
                                </ul>
                            </li>
                        <?php } ?>
                        <?php if ($hasAccesstoIPDRegistration == TRUE && $hasAccesstoIPDEnquiry == TRUE) { ?>
                            <li class="treeview <?php echo $ipd; ?>">
                                <a href="#">
                                    <i class="fa fa-angle-double-right"></i><span>IPD</span>
                                    <i class="fa fa-angle-left pull-right"></i>
                                </a>
                                <ul class="treeview-menu">
                                    <?php if ($hasAccesstoIPDRegistration) { ?><li <?php echo $ipd_registration; ?>><a href="<?php echo base_url() ?>app/ipd/registration"><i class="fa fa-angle-double-right"></i>Admit Patient</a></li> <?php } ?>
                                    <?php if ($hasAccesstoIPDEnquiry) { ?><li <?php echo $ipd_master; ?>><a href="<?php echo base_url() ?>app/ipd/index"><i class="fa fa-angle-double-right"></i>In-Patient Enquiry</a></li> <?php } ?>
                                </ul>
                            </li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>




            <!--START OF Ward-->
            <?php if ($hasAccesstoRooms && has_role('admin')) { ?>
                <li class="treeview <?php echo $room_m; ?>">
                    <a href="#">
                        <i class="fa fa-hospital-o"></i> <span> Room Management</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoRoomsEnquiry) { ?><li <?php echo $room_enquiry; ?>><a href="<?php echo base_url() ?>app/room_enquiry">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Room Enquiry</a></li><?php } ?>
                        <?php if ($hasAccesstoRooms) { ?><li <?php echo $sub_room_category_mod; ?>><a href="<?php echo base_url() ?>app/room_category">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Room Category</a></li><?php } ?>
                        <?php if ($hasAccesstoRooms) { ?><li <?php echo $sub_room_master_mod; ?>><a href="<?php echo base_url() ?>app/room_master">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Room Master</a></li><?php } ?>
                        <?php if ($hasAccesstoRooms) { ?><li <?php echo $sub_room_bed_mod; ?>><a href="<?php echo base_url() ?>app/room_bed">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Room Bed Master</a></li><?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!--START OF Ward-->

            <!--START OF Nurse Module-->
            <?php if ($hasAccesstoNurse && has_role(array('nurse', 'doctor'))) { ?>
                <li class="treeview <?php echo $nurse_module; ?>">
                    <a href="#">
                        <i class="fa fa-plus-square"></i> <span> Nurse Module</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <!--<li <?php echo $nurse_diagnosis; ?>><a href="<?php echo base_url() ?>app/nurse_module/diagnosis">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Diagnosis</a></li>-->
                        <?php if ($hasAccesstoNurseMedication) { ?><li <?php echo $nurse_medication; ?>><a href="<?php echo base_url() ?>app/nurse_module/medication">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Patient Medication</a></li><?php } ?>
                        <?php if ($hasAccesstoNurseInOutTake) { ?><li <?php echo $nurse_intake_output; ?>><a href="<?php echo base_url() ?>app/nurse_module/intake_output">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Intake/Output Record</a></li><?php } ?>
                        <?php if ($hasAccesstoNurseProgressNote) { ?><li <?php echo $nurse_progress_note; ?>><a href="<?php echo base_url() ?>app/nurse_module/nurse_progress_note">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Nurse Progress Note</a></li><?php } ?>
                        <?php if ($hasAccesstoNurseVitalSign) { ?><li <?php echo $nurse_vital_sign; ?>><a href="<?php echo base_url() ?>app/nurse_module/vitalSign">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Vital Sign</a></li><?php } ?>
                        <?php if ($hasAccesstoNurseBedSide) { ?><li <?php echo $nurse_bed_side ?>><a href="<?php echo base_url() ?>app/nurse_module/bed_side_procedure">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Bed Side Procedure</a></li><?php } ?>
                        <?php if ($hasAccesstoNurseIPRoomTransfer) { ?><li <?php echo $nurse_room_transfer ?>><a href="<?php echo base_url() ?>app/nurse_module/room_transfer">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>IP Room Transfer</a></li><?php } ?>
                        <?php if ($hasAccesstoNursePatientHistory) { ?><li <?php echo $nurse_patientHistory ?>><a href="<?php echo base_url() ?>app/nurse_module/patientHistory">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Patient History</a></li><?php } ?>
                        <?php if ($hasAccesstoNurseDischarge) { ?><li <?php echo $nurse_discharge_summary ?>><a href="<?php echo base_url() ?>app/nurse_module/discharge_summary">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Discharge Summary</a></li><?php } ?>
                        <li <?php echo $nurse_vitals_queue; ?>><a href="<?php echo base_url() ?>app/nurse_module/vitals_queue">&nbsp;&nbsp;&nbsp;<i class="fa fa-heartbeat"></i> OPD Vitals Queue</a></li>
                        <li <?php echo $nurse_messages; ?>><a href="<?php echo base_url() ?>app/nurse_module/messages">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Messages</a></li>
                        <li <?php echo $nurse_shift_tasks; ?>><a href="<?php echo base_url() ?>app/nurse_module/shift_tasks">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Shift Tasks</a></li>
                    </ul>
                </li>
            <?php } ?>
            <!--START OF Nurse Module-->

            <!--START OF Doctor-->
            <?php if ($hasAccesstoDoctor && has_role('doctor')) { ?>
                <li class="treeview <?php echo $doctor; ?>" style="display: <?php echo ($this->session->userdata('user_role') == 1) ? "none" : "block"; ?>">
                    <a href="#">
                        <i class="fa fa-user-md"></i> <span> Doctor Module</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoDoctorOPD) { ?><li <?php echo $opd_doctor; ?>><a href="<?php echo base_url() ?>app/doctor/opd"><i class="fa fa-angle-double-right"></i>Out-Patient</a></li><?php } ?>
                        <?php if ($hasAccesstoDoctorIPD) { ?><li <?php echo $ipd_doctor; ?>><a href="<?php echo base_url() ?>app/doctor/ipd"><i class="fa fa-angle-double-right"></i>In-Patient</a></li><?php } ?>
                        <li <?php echo $doctor_messages; ?>><a href="<?php echo base_url() ?>app/doctor_messages/inbox"><i class="fa fa-angle-double-right"></i>Messages<?php if (isset($doctor_message_unread_count) && (int)$doctor_message_unread_count > 0): ?> <span class="label label-danger" style="margin-left:6px;"><?php echo (int)$doctor_message_unread_count; ?></span><?php endif; ?></a></li>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Doctor-->


            <!--START OF Laboratory-->
            <?php if ($hasAccesstoLaboratory) { ?>
                <li class="treeview <?php echo $laboratory; ?>" style="display: <?php echo ($this->session->userdata('user_role') == 1) ? "none" : "block"; ?>">
                    <a href="#">
                        <i class="fa fa-user-md"></i> <span> Laboratory Module</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoLaboratory) { ?><li <?php echo $opd_doctor; ?>><a href="<?php echo base_url() ?>app/laboratory/index"><i class="fa fa-angle-double-right"></i>Labs</a></li><?php } ?>
                        <?php if ($hasAccesstoLaboratory) { ?><li><a href="<?php echo base_url() ?>app/laboratory/lab_queue"><i class="fa fa-flask"></i> Lab Queue</a></li><?php } ?>
                        <?php if ($hasAccesstoLaboratory) { ?><li <?php echo $opd_doctor; ?>><a href="<?php echo base_url() ?>app/laboratory/lab_enquiry"><i class="fa fa-angle-double-right"></i>Lab Enquiry</a></li><?php } ?>
                        <!-- <?php #if($hasAccesstoDoctorIPD){
                                ?><li <?php #echo $ipd_doctor;
                                                                    ?>><a href="<?php #echo base_url()
                                                                                                        ?>app/doctor/ipd"><i class="fa fa-angle-double-right"></i>In-Patient</a></li><?php #}
                                                                                                                                                                                                           ?> -->
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Laboratory-->

            <!--START OF Sonography-->
            <?php if (isset($hasAccesstoSonography) && $hasAccesstoSonography && has_role(array('sonographer', 'doctor'))) { ?>
                <li class="treeview <?php echo $sonography; ?>">
                    <a href="#">
                        <i class="fa fa-user-md"></i> <span> Sonography Module</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <li <?php echo $sonography_mod; ?>><a href="<?php echo base_url() ?>app/sonography"><i class="fa fa-angle-double-right"></i>Sonography Dashboard</a></li>
                        <li><a href="<?php echo base_url() ?>app/sonography/imaging_queue/sonography"><i class="fa fa-heartbeat"></i> Sonography Queue</a></li>
                        <li><a href="<?php echo base_url() ?>app/sonography/imaging_queue/xray"><i class="fa fa-bolt"></i> X-Ray Queue</a></li>
                        <li><a href="<?php echo base_url() ?>app/sonography/imaging_queue/ecg"><i class="fa fa-area-chart"></i> ECG Queue</a></li>
                        <li><a href="<?php echo base_url() ?>app/sonography/completed"><i class="fa fa-history"></i> Completed Scans</a></li>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Sonography-->

            <!--START OF Radiology-->
            <?php if (has_role(array('admin', 'doctor', 'sonographer', 'nurse', 'receptionist'))) { ?>
                <li class="treeview <?php echo (isset($radiology) ? $radiology : ''); ?>">
                    <a href="#">
                        <i class="fa fa-x-ray"></i> <span>Radiology</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <li><a href="<?php echo base_url() ?>app/radiology"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url() ?>app/radiology/order_test"><i class="fa fa-plus"></i> New Order</a></li>
                        <?php if (has_role('admin')) { ?>
                        <li><a href="<?php echo base_url() ?>app/radiology/add_test"><i class="fa fa-plus-circle"></i> Add Test</a></li>
                        <?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Radiology-->

            <?php if ($hasAccesstoEMR && has_role('doctor')) { ?>
                <li class="treeview <?php echo $emr; ?>">
                    <a href="#">
                        <i class="fa fa-book"></i> <span> EMR Sheet</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoEMROPD) { ?><li <?php echo $opd_emr; ?>><a href="<?php echo base_url() ?>app/emr/opd"><i class="fa fa-angle-double-right"></i>Out-Patient EMR</a></li> <?php } ?>
                        <?php if ($hasAccesstoEMRIPD) { ?><li <?php echo $ipd_emr; ?>><a href="<?php echo base_url() ?>app/emr/ipd"><i class="fa fa-angle-double-right"></i>In-Patient EMR</a></li><?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Consultant-->


            <!--START OF User Management-->
            <?php if ($hasAccesstoUsers && has_role('admin')) { ?>
                <li class="treeview <?php echo $user_mgnmt; ?>">
                    <a href="#">
                        <i class="fa fa-group "></i> <span>User Management</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoAddUsers) { ?><li <?php echo $add_user; ?>><a href="<?php echo base_url() ?>app/user/add"><i class="fa fa-angle-double-right"></i>Add New User</a></li><?php } ?>
                        <?php if ($hasAccesstoUsers) { ?><li <?php echo $user_index; ?>><a href="<?php echo base_url() ?>app/user"><i class="fa fa-angle-double-right"></i>User Masterlist</a></li> <?php } ?>
                        <?php if ($hasAccesstoUsers) { ?><li <?php echo $roles_mod; ?>><a href="<?php echo base_url() ?>app/roles"><i class="fa fa-angle-double-right"></i>User Roles</a></li><?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF User Management-->

            <?php if ($hasAccesstoAdmin && has_role('admin')) { ?>
                <li class="treeview <?php echo $admin; ?>">
                    <a href="#">
                        <i class="fa fa-gear"></i> <span>Administrator</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoAdminCompanyInfo) { ?><li <?php echo $company_information; ?>><a href="<?php echo base_url() ?>app/company_information"><i class="fa fa-angle-double-right"></i>Company Information</a></li> <?php } ?>
                        <?php if ($hasAccesstoAdminDepartment) { ?><li <?php echo $department_mod; ?>><a href="<?php echo base_url() ?>app/department"><i class="fa fa-angle-double-right"></i>Department Master</a></li> <?php } ?>
                        <?php if ($hasAccesstoAdminDesignation) { ?><li <?php echo $designation_mod; ?>><a href="<?php echo base_url() ?>app/designation"><i class="fa fa-angle-double-right"></i>Designation Master</a></li> <?php } ?>
                        <?php if ($hasAccesstoAdminBillGroupName) { ?><li <?php echo $group_name_mod; ?>><a href="<?php echo base_url() ?>app/bill_group_name"><i class="fa fa-angle-double-right"></i>Bill Group Name Master</a></li> <?php } ?>
                        <?php if ($hasAccesstoAdminParticularBill) { ?><li <?php echo $particular_bill_mod; ?>><a href="<?php echo base_url() ?>app/particular_bill"><i class="fa fa-angle-double-right"></i>Particular Bill Master</a></li> <?php } ?>
                        <?php if ($hasAccesstoAdminComplain) { ?><li <?php echo $complain_mod; ?>><a href="<?php echo base_url() ?>app/complain"><i class="fa fa-angle-double-right"></i>Complain Master</a></li><?php } ?>
                        <?php if ($hasAccesstoAdminDiagnosis) { ?><li <?php echo $diagnosis_mod; ?>><a href="<?php echo base_url() ?>app/diagnosis"><i class="fa fa-angle-double-right"></i>Diagnosis Master</a></li><?php } ?>
                        <?php if ($hasAccesstoAdminSurgicalPack) { ?><li <?php echo $surgical_package; ?>><a href="<?php echo base_url() ?>app/surgical_package"><i class="fa fa-angle-double-right"></i>Surgical Package</a></li><?php } ?>
                        <?php if ($hasAccesstoAdminInsuranceCompany) { ?><li <?php echo $insurance_company_mod; ?>><a href="<?php echo base_url() ?>app/insurance_company"><i class="fa fa-angle-double-right"></i>Insurance Company</a></li><?php } ?>
                        <li <?php echo $nhis_claims_mod; ?>><a href="<?php echo base_url() ?>app/nhis_claims"><i class="fa fa-medkit"></i> NHIS Claims<?php
                            $nhisSidebarAlerts = 0;
                            if (isset($this) && isset($this->billing_model) && method_exists($this->billing_model, 'get_nhis_alert_counts')) {
                                $nhisSidebarData = $this->billing_model->get_nhis_alert_counts();
                                $nhisSidebarAlerts = isset($nhisSidebarData['total_alerts']) ? (int)$nhisSidebarData['total_alerts'] : 0;
                            }
                            if ($nhisSidebarAlerts > 0) { echo ' <span class="label label-danger pull-right">'.$nhisSidebarAlerts.'</span>'; }
                        ?></a></li>

                        <?php if ($hasAccesstoAdminMedicineCategory == TRUE && $hasAccesstoAdminDrugName == TRUE) { ?>
                            <li class="treeview <?php echo $medicine; ?>">
                                <a href="#">
                                    <i class="fa fa-angle-double-right"></i><span>Medicine Mgmt</span>
                                    <i class="fa fa-angle-left pull-right"></i>
                                </a>
                                <ul class="treeview-menu">
                                    <?php if ($hasAccesstoAdminMedicineCategory) { ?><li <?php echo $med_cat_mod; ?>><a href="<?php echo base_url() ?>app/medicine_category">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Category Master</a></li><?php } ?>
                                    <?php if ($hasAccesstoAdminDrugName) { ?><li <?php echo $drug_mod; ?>><a href="<?php echo base_url() ?>app/drug_name">&nbsp;&nbsp;&nbsp;<i class="fa fa-angle-double-right"></i>Drug Name Master</a></li><?php } ?>
                                </ul>
                            </li>
                        <?php } ?>

                        <?php if ($hasAccesstoAdminAckReceipt) { ?><li <?php echo $declared_receipt_mod; ?>><a href="<?php echo base_url() ?>app/declared_receipt"><i class="fa fa-angle-double-right"></i>Acknowledge Receipt</a></li><?php } ?>
                        <?php if ($hasAccesstoAdminParameters) { ?><li <?php echo $param_mod; ?>><a href="<?php echo base_url() ?>app/parameters"><i class="fa fa-angle-double-right"></i>System Parameters</a></li><?php } ?>
                        <?php if ($hasAccesstoAdminBackup) { ?><li <?php echo $backup; ?>><a href="<?php echo base_url() ?>app/backup"><i class="fa fa-angle-double-right"></i>Backup Database</a></li><?php } ?>
                        <?php if ($hasAccesstoAdminPages) { ?><li <?php echo $pages_mod; ?>><a href="<?php echo base_url() ?>app/pages"><i class="fa fa-angle-double-right"></i>System Pages</a></li> <?php } ?>
                        <li <?php echo $staff_privileges_mod; ?>><a href="<?php echo base_url() ?>app/staff_privileges"><i class="fa fa-key"></i> Staff Privileges</a></li>
                    </ul>
                </li>
            <?php } ?>




            <!--START OF Billing Module-->
            <?php if ($hasAccesstoReport && has_role(array('admin', 'doctor', 'nurse', 'receptionist', 'cashier', 'laboratory', 'pharmacist', 'sonographer'))) { ?>
                <li class="treeview <?php echo $reports; ?>">
                    <a href="#">
                        <i class="fa fa-print"></i> <span> Reports Generation</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if ($hasAccesstoReportPatient) { ?><li <?php echo $patient_list_report_mod; ?>><a href="<?php echo base_url() ?>app/reports/patient_list"><i class="fa fa-angle-double-right"></i>Patient Masterlist Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportIndividualPatient) { ?><li <?php echo $patient_visited_report; ?>><a href="<?php echo base_url() ?>app/reports/patient_visited"><i class="fa fa-angle-double-right"></i>Individual Patient Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportOPD) { ?><li <?php echo $outpatient_report; ?>><a href="<?php echo base_url() ?>app/reports/outpatient"><i class="fa fa-angle-double-right"></i>Out Patient Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportAdmitted) { ?><li <?php echo $inpatient_report; ?>><a href="<?php echo base_url() ?>app/reports/inpatient"><i class="fa fa-angle-double-right"></i>Admitted Patient Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportDischarge) { ?><li <?php echo $discharged_patient_report ?>><a href="<?php echo base_url() ?>app/reports/discharged_patient"><i class="fa fa-angle-double-right"></i>Discharged Patient Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportDailySales && has_role('admin')) { ?><li <?php echo $daily_reports_mod; ?>><a href="<?php echo base_url() ?>app/reports/daily_sales"><i class="fa fa-angle-double-right"></i>Daily Sales Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportDoctorsFee && has_role('admin')) { ?><li <?php echo $daily_reports_mod; ?>><a href="<?php echo base_url() ?>app/reports/doctorFeeReport"><i class="fa fa-angle-double-right"></i>Doctor's Fee Report</a></li><?php } ?>
                        <?php if ($hasAccesstoReportAR && has_role('admin')) { ?><li <?php echo $declared_receipt_mod; ?>><a href="<?php echo base_url() ?>app/reports/declared_receipt"><i class="fa fa-angle-double-right"></i>Acknowledge Receipt Report</a></li><?php } ?>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF Billing Module-->

            <!--START OF GHS Reports-->
            <?php if (has_role(array('admin', 'nurse', 'receptionist'))) { ?>
                <li class="treeview <?php echo $ghs_reports; ?>">
                    <a href="#">
                        <i class="fa fa-bar-chart"></i> <span>GHS Reports</span>
                        <i class="fa fa-angle-left pull-right"></i>
                    </a>
                    <ul class="treeview-menu">
                        <?php if (!has_role('receptionist')): ?>
                        <li <?php echo $ghs_dashboard; ?>><a href="<?php echo base_url() ?>app/ghs_reports"><i class="fa fa-dashboard"></i> Reports Dashboard</a></li>
                        <?php endif; ?>
                        <li <?php echo $ghs_opd_attendance; ?>><a href="<?php echo base_url() ?>app/ghs_reports/opd_attendance"><i class="fa fa-stethoscope"></i> OPD Attendance</a></li>
                        <?php if (!has_role('receptionist')): ?>
                        <li <?php echo $ghs_diagnosis; ?>><a href="<?php echo base_url() ?>app/ghs_reports/diagnosis"><i class="fa fa-medkit"></i> Top Diagnoses</a></li>
                        <li <?php echo $ghs_pharmacy; ?>><a href="<?php echo base_url() ?>app/ghs_reports/pharmacy_consumption"><i class="fa fa-flask"></i> Pharmacy Consumption</a></li>
                        <?php endif; ?>
                        <?php if (has_role('admin')): ?><li <?php echo $ghs_nhis_cash; ?>><a href="<?php echo base_url() ?>app/ghs_reports/nhis_cash"><i class="fa fa-shield"></i> NHIS vs Cash</a></li><?php endif; ?>
                        <?php if (has_role('admin')): ?><li <?php echo $ghs_revenue; ?>><a href="<?php echo base_url() ?>app/ghs_reports/revenue"><i class="fa fa-money"></i> Revenue Report</a></li><?php endif; ?>
                        <li <?php echo $ghs_daily_returns; ?>><a href="<?php echo base_url() ?>app/ghs_reports/daily_returns"><i class="fa fa-calendar-check-o"></i> Daily Returns</a></li>
                    </ul>
                </li>
            <?php } ?>
            <!--END OF GHS Reports-->

            <!--START OF USER PROFILE-->
            <li class="treeview <?php echo $profile; ?>">
                <a href="#">
                    <i class="fa fa-user"></i> <span>User Profile</span>
                    <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                    <li <?php echo $myprofile_mod; ?>><a href="<?php echo base_url() ?>myprofile"><i class="fa fa-angle-double-right"></i>My Profile</a></li>
                    <li <?php echo $editprofile_mod; ?>><a href="<?php echo base_url() ?>myprofile/editprofile"><i class="fa fa-angle-double-right"></i>Edit Profile</a></li>
                    <!-- <li <?php echo $change_pwd_mod; ?>><a href="<?php echo base_url() ?>myprofile/changepwd"><i class="fa fa-angle-double-right"></i>Change Password</a></li> -->
                    <li><a href="<?php echo base_url() ?>login/logout"><i class="fa fa-angle-double-right"></i>Logout</a></li>
                </ul>
            </li>
            <!--END OF USER PROFILE-->
        </ul>

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
    <!-- /.sidebar -->
</aside>





<style type="text/css">
    #footerLO {
        background-color: #1f1c1c;
        height: 35px;
        color: #f8d756;
        bottom: 0;
        position: fixed;
        bottom: 0;
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


<!-- Modal -->
<script language="javascript">
    function showInvoice(patient_id) {
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else { // code for IE6, IE5
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function() {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {

                document.getElementById("showInvoice").innerHTML = xmlhttp.responseText;
            }
        }
        var supp;

        xmlhttp.open("GET", "<?php echo base_url(); ?>app/billing/getItem/" + patient_id, true);
        xmlhttp.send();

    }
</script>


<form method="post">

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
                            <?php foreach ($patientListRows as $patientListRows) { ?>
                                <option value="<?php echo $patientListRows->patient_no ?>"><?php echo $patientListRows->name ?></option>
                            <?php } ?>
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
            <!-- /.modal-content -->
        </div>
    </div>
</form>
<!-- /.modal-dialog -->