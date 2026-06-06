<!DOCTYPE html>
<html>

<head>

        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">



        <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

        <!----------BOOTSTRAP DATEPICKER----------------------------->
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">
        <!---------------------------------------------------------->


        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

    </head>

<body class="skin-blue">
    <!-- header logo: style can be found in header.less -->
    <?php require_once(APPPATH . 'views/include/header.php'); ?>
	<?php $canEditClinical = ((isset($userInfo) && isset($userInfo->module) && strtolower((string)$userInfo->module) === 'doctor') || (isset($hasAccesstoAdmin) && $hasAccesstoAdmin)); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">

        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <!-- Right side column. Contains the navbar and content of the page -->
        <aside class="right-side">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <?php if ($this->session->userdata('emr_viewing') == "opd_emr_viewing") { ?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url() ?>app/emr/opd">Out-Patient Master</a></li>
                    </ol>
                <?php } else if (!isset($hasAccesstoDoctor) || !$hasAccesstoDoctor) { ?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url() ?>app/opd/index">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                <?php } else { ?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url() ?>app/doctor/opd">Out-Patient Master</a></li>
                        <li class="active">OPD Patient Information</li>
                    </ol>
                <?php } ?>
            </section>

            <!-- Main content -->
            <section class="content">





                <div class="row">

                    <div class="col-md-3">
                        <div class="box">
                            <div class="box-header"></div>
                            <div class="box-body table-responsive no-padding">
                                <table width="100%" cellpadding="3" cellspacing="3">
                                    <tr>
                                        <td width="15%" valign="top" align="center">
                                            <?php
                                            if (!$patientInfo->picture) {
                                                $picture = "avatar.png";
                                            } else {
                                                $picture = $patientInfo->picture;
                                            }
                                            ?>
                                            <img src="<?php echo base_url(); ?>public/patient_picture/<?php echo $picture; ?>" class="img-rounded" width="86" height="81">
                                        </td>
                                        <td>
                                            <table width="100%">
                                                <tr>
                                                    <td><u>Patient No.</u></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo $patientInfo->patient_no ?></td>
                                                </tr>
                                                <tr>
                                                    <td><u>Patient Name</u></td>
                                                </tr>
                                                <tr>
                                                    <td><?php echo $patientInfo->name ?></td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="box-footer clearfix">
                                <div style="margin-top: 15px;">
                                    <ul class="nav nav-pills nav-stacked">
                                        <li><a href="<?php echo base_url() ?>app/opd/view/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> General Information</a></li>

                                        <li><a href="<?php echo base_url() ?>app/opd/diagnosis/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>

                                        <li><a href="<?php echo base_url() ?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                        <li class="active"><a href="<?php echo base_url() ?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Vital Sign</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/procedures/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Procedures</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/discharge_summary/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                        <?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>
                                        <!--<li><a href="<?php echo base_url() ?>app/opd/billing/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Admission Billing</a></li>-->
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Complain</a></li>

                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
									<?php if (!empty($message)) echo $message; ?>
									<?php if (!$canEditClinical) { ?>
										<div class="alert alert-info">Read-only — Doctor access only</div>
									<?php } ?>
                                    <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                        <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                            <a href="#" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#complaintsModal"><i class="fa fa-plus-circle"></i> Add Complaints</a>
                                    <?php }
                                    } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
                                    <table class="table table-hover table-striped" style="font-size:13px;">
                                        <thead>
                                            <tr>
                                                <th>Date &amp; Time</th>
                                                <th>Complaint</th>
                                                <th>Severity</th>
                                                <th>Duration</th>
                                                <th>Onset</th>
                                                <th>Remarks</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patientComplain as $patientComplain) { ?>
                                                <tr>
                                                    <td style="white-space:nowrap;"><?php echo $patientComplain->dDate ? date("d M Y H:i", strtotime($patientComplain->dDate)) : '—'; ?></td>
                                                    <td>
                                                        <?php
                                                        $display = $patientComplain->complain_name
                                                            ? htmlspecialchars($patientComplain->complain_name)
                                                            : '';
                                                        $ct = isset($patientComplain->complain_text) ? trim((string)$patientComplain->complain_text) : '';
                                                        if ($ct !== '') {
                                                            echo $display ? $display . ' <span style="color:#888;">(' . htmlspecialchars($ct) . ')</span>' : htmlspecialchars($ct);
                                                        } else {
                                                            echo $display ?: '<span style="color:#aaa;">—</span>';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $sev = isset($patientComplain->severity) ? trim((string)$patientComplain->severity) : '';
                                                        if ($sev !== '') {
                                                            $sev_class = ($sev === 'Severe') ? 'danger' : (($sev === 'Moderate') ? 'warning' : 'success');
                                                            echo '<span class="label label-' . $sev_class . '">' . htmlspecialchars($sev) . '</span>';
                                                        } else { echo '<span style="color:#ccc;">—</span>'; }
                                                        ?>
                                                    </td>
                                                    <td><?php echo isset($patientComplain->duration) && $patientComplain->duration !== '' ? htmlspecialchars($patientComplain->duration) : '<span style="color:#ccc;">—</span>'; ?></td>
                                                    <td><?php echo isset($patientComplain->onset) && $patientComplain->onset !== '' ? htmlspecialchars($patientComplain->onset) : '<span style="color:#ccc;">—</span>'; ?></td>
                                                    <td><?php echo $patientComplain->remarks ? htmlspecialchars($patientComplain->remarks) : '<span style="color:#ccc;">—</span>'; ?></td>
                                                    <td>
                                                        <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                                            <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                                                <form method="post" action="<?php echo base_url() ?>app/opd/delete_complain/<?php echo $patientComplain->iop_comp_id ?>/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove?');">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <button type="submit" class="btn btn-link text-danger" style="padding:0;"><i class="fa fa-times"></i> Remove</button>
                                                                </form>
                                                        <?php }
                                                        } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>

                                    <br><br><br><br><br><br><br>
                                    <br><br><br><br><br><br><br>
                                    <br><br><br><br><br><br><br>
                                </div>
                            </div>
                            <div class="box-footer clearfix">

                            </div>
                        </div>
                    </div>
                </div>

            </section><!-- /.content -->
        </aside><!-- /.right-side -->
    </div><!-- ./wrapper -->


    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <?php require_once(APPPATH.'views/app/opd/_detain_admit_modals.php'); ?>

<!-- ═══════════════════════════════════════════════════════════════════
     COMPLAINTS MODAL — Phase 2 GHS Clinical UI
     No CDN dependencies. Pure Bootstrap + jQuery (already loaded above).
     Backend: POST to save_complaint_batch (unchanged).
═══════════════════════════════════════════════════════════════════ -->
<style>
/* ── Modal shell ─────────────────────────────────────────────────── */
#complaintsModal .modal-dialog { max-width: 860px; width: 96%; }
#complaintsModal .modal-header {
    background: linear-gradient(135deg, #1a73e8 0%, #0d47a1 100%);
    color: #fff; border-radius: 4px 4px 0 0; padding: 14px 20px;
}
#complaintsModal .modal-header .close { color: #fff; opacity: .8; font-size: 22px; margin-top: -2px; }
#complaintsModal .modal-body { background: #f4f6fb; padding: 18px 20px; max-height: 72vh; overflow-y: auto; }
#complaintsModal .modal-footer { background: #f4f6fb; border-top: 1px solid #dde3f0; padding: 12px 20px; }

/* ── Search bar ──────────────────────────────────────────────────── */
#cm-search-wrap { position: relative; margin-bottom: 14px; }
#cm-search { width: 100%; padding: 9px 14px 9px 38px; border: 1.5px solid #c5cfe8;
    border-radius: 6px; font-size: 14px; background: #fff; outline: none; }
#cm-search:focus { border-color: #1a73e8; box-shadow: 0 0 0 3px rgba(26,115,232,.12); }
#cm-search-wrap .fa-search { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #8fa3c0; font-size: 14px; }

/* ── Common chips strip ──────────────────────────────────────────── */
.cm-common-label { font-size: 11px; font-weight: 600; color: #5a6a8a; text-transform: uppercase;
    letter-spacing: .6px; margin-bottom: 6px; }
.cm-quick-chips { display: flex; flex-wrap: wrap; gap: 7px; margin-bottom: 14px; }
.cm-quick-chip {
    background: #fff; border: 1.5px solid #c5cfe8; border-radius: 20px;
    padding: 5px 13px; font-size: 13px; cursor: pointer; color: #2c3e6b;
    transition: all .15s ease; user-select: none; white-space: nowrap;
}
.cm-quick-chip:hover { border-color: #1a73e8; background: #e8f0fe; color: #1a73e8; }
.cm-quick-chip.selected { background: #1a73e8; border-color: #1a73e8; color: #fff; font-weight: 500; }

/* ── Category tabs ───────────────────────────────────────────────── */
.cm-cat-tabs { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 12px; }
.cm-cat-tab {
    background: #fff; border: 1.5px solid #d0d9ee; border-radius: 5px;
    padding: 4px 11px; font-size: 12px; cursor: pointer; color: #4a5a82;
    transition: all .15s; user-select: none;
}
.cm-cat-tab:hover { border-color: #1a73e8; color: #1a73e8; }
.cm-cat-tab.active { background: #1a73e8; border-color: #1a73e8; color: #fff; font-weight: 500; }

/* ── Complaint chip grid ─────────────────────────────────────────── */
#cm-chip-grid { display: flex; flex-wrap: wrap; gap: 7px; min-height: 48px;
    padding: 10px 0 4px; }
#cm-chip-grid .cm-chip {
    background: #fff; border: 1.5px solid #c5cfe8; border-radius: 20px;
    padding: 5px 13px; font-size: 13px; cursor: pointer; color: #2c3e6b;
    transition: all .15s ease; user-select: none;
}
#cm-chip-grid .cm-chip:hover { border-color: #1a73e8; background: #e8f0fe; }
#cm-chip-grid .cm-chip.selected { background: #0d47a1; border-color: #0d47a1; color: #fff; }
#cm-chip-grid .cm-chip.hidden { display: none; }
#cm-no-results { font-size: 13px; color: #9aacca; padding: 10px 0; display: none; }

/* ── Selected panel ──────────────────────────────────────────────── */
#cm-selected-panel { background: #fff; border: 1.5px solid #c5cfe8; border-radius: 8px;
    padding: 12px 14px; margin-top: 14px; min-height: 52px; }
.cm-selected-label { font-size: 11px; font-weight: 600; color: #5a6a8a; text-transform: uppercase;
    letter-spacing: .6px; margin-bottom: 8px; }
#cm-selected-tags { display: flex; flex-wrap: wrap; gap: 6px; }
.cm-sel-tag {
    background: #e8f0fe; border: 1px solid #b3c8f7; border-radius: 20px;
    padding: 4px 10px 4px 12px; font-size: 13px; color: #1a3a7a;
    display: inline-flex; align-items: center; gap: 6px;
}
.cm-sel-tag .cm-remove { cursor: pointer; color: #5a7acc; font-size: 15px; line-height: 1;
    font-weight: 700; }
.cm-sel-tag .cm-remove:hover { color: #c62828; }
#cm-empty-sel { font-size: 13px; color: #b0bcd4; font-style: italic; }

/* ── Clinical fields ─────────────────────────────────────────────── */
.cm-clinical-row { display: flex; gap: 12px; margin-top: 14px; flex-wrap: wrap; }
.cm-clinical-row .cm-field { flex: 1; min-width: 140px; }
.cm-clinical-row label { font-size: 11px; font-weight: 600; color: #5a6a8a;
    text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 4px; }
.cm-clinical-row .form-control { font-size: 13px; height: 34px; border-color: #c5cfe8; border-radius: 5px; }
.cm-clinical-row .form-control:focus { border-color: #1a73e8; box-shadow: 0 0 0 2px rgba(26,115,232,.1); }

/* ── Custom entry ────────────────────────────────────────────────── */
.cm-custom-row { display: flex; gap: 8px; margin-top: 14px; align-items: flex-end; }
.cm-custom-row input { flex: 1; font-size: 13px; height: 34px; padding: 6px 12px;
    border: 1.5px solid #c5cfe8; border-radius: 5px; }
.cm-custom-row input:focus { border-color: #1a73e8; outline: none;
    box-shadow: 0 0 0 2px rgba(26,115,232,.1); }
.cm-custom-row .btn-custom-add { height: 34px; padding: 0 16px; font-size: 13px;
    white-space: nowrap; border-radius: 5px; }

/* ── Save button ─────────────────────────────────────────────────── */
/* My Top Complaints panel */
#cm-top-panel { margin-bottom: 10px; display: none; }
#cm-top-panel.has-data { display: block; }
.cm-top-label {
    font-size: 11px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .6px; color: #27ae60; margin-bottom: 6px;
}
.cm-top-chip {
    display: inline-flex; align-items: center; gap: 5px;
    background: #eafaf1; border: 1.5px solid #27ae60; color: #1a7a42;
    border-radius: 20px; padding: 4px 12px 4px 10px;
    font-size: 12px; font-weight: 600; cursor: pointer;
    margin: 0 4px 5px 0; transition: background .15s;
    white-space: nowrap;
}
.cm-top-chip:hover  { background: #27ae60; color: #fff; }
.cm-top-chip.selected { background: #27ae60; color: #fff; border-color: #1a7a42; }
.cm-top-chip .cm-usage-badge {
    background: rgba(0,0,0,.12); border-radius: 10px;
    padding: 0 5px; font-size: 10px; font-weight: 700;
    min-width: 18px; text-align: center;
}
#cm-btn-save { min-width: 180px; font-size: 14px; font-weight: 500;
    border-radius: 6px; padding: 8px 20px; }
#cm-btn-save:disabled { opacity: .5; cursor: not-allowed; }

/* ── Saving spinner overlay ──────────────────────────────────────── */
#cm-saving-overlay {
    display: none; position: absolute; inset: 0; background: rgba(255,255,255,.88);
    z-index: 10; align-items: center; justify-content: center; flex-direction: column;
    border-radius: 0 0 4px 4px;
}
#cm-saving-overlay i { font-size: 32px; color: #1a73e8; margin-bottom: 8px; }
#cm-saving-overlay span { font-size: 14px; color: #2c3e6b; }
#complaintsModal .modal-content { position: relative; }

@media (max-width: 600px) {
    #complaintsModal .modal-dialog { width: 99%; margin: 4px auto; }
    .cm-clinical-row { flex-direction: column; }
    .cm-cat-tabs .cm-cat-tab { font-size: 11px; padding: 3px 8px; }
}
</style>

<!-- ── COMPLAINTS MODAL ──────────────────────────────────────────── -->
<div class="modal fade" id="complaintsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">

            <!-- Saving overlay -->
            <div id="cm-saving-overlay">
                <i class="fa fa-spinner fa-spin"></i>
                <span>Saving complaints…</span>
            </div>

            <!-- Header -->
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title">
                    <i class="fa fa-stethoscope"></i>&nbsp; Add Complaints
                </h4>
            </div>

            <!-- Body -->
            <div class="modal-body">

                <!-- Search -->
                <div id="cm-search-wrap">
                    <i class="fa fa-search"></i>
                    <input type="text" id="cm-search" placeholder="Search complaints…" autocomplete="off">
                </div>

                <!-- My Top Complaints (per-doctor, hidden until data exists) -->
                <?php if (!empty($doctorTopComplaints)): ?>
                <div id="cm-top-panel" class="has-data">
                    <div class="cm-top-label"><i class="fa fa-star"></i> My Most Used</div>
                    <div id="cm-top-chips">
                        <?php foreach ($doctorTopComplaints as $tc): ?>
                        <span class="cm-top-chip"
                              data-id="<?php echo htmlspecialchars($tc->complain_id); ?>"
                              data-name="<?php echo htmlspecialchars(ucwords(strtolower($tc->complain_name))); ?>">
                            <?php echo htmlspecialchars(ucwords(strtolower($tc->complain_name))); ?>
                            <span class="cm-usage-badge"><?php echo (int)$tc->usage_count; ?></span>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Ghana-Optimised Quick Select (top 8 OPD complaints) -->
                <div class="cm-common-label">⚡ Quick Select</div>
                <div class="cm-quick-chips" id="cm-quick-chips">
                    <?php
                    $quickComplaints = array(
                        'Fever', 'Headache', 'Malaria Symptoms', 'Cough',
                        'Diarrhea', 'Vomiting', 'Body Weakness', 'Abdominal Pain'
                    );
                    foreach ($quickComplaints as $qc): ?>
                        <span class="cm-quick-chip" data-name="<?php echo htmlspecialchars($qc); ?>">
                            <?php echo htmlspecialchars($qc); ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <!-- Category tabs -->
                <div class="cm-cat-tabs" id="cm-cat-tabs">
                    <span class="cm-cat-tab active" data-cat="ALL">All</span>
                    <span class="cm-cat-tab" data-cat="GENERAL">General</span>
                    <span class="cm-cat-tab" data-cat="RESPIRATORY">Respiratory</span>
                    <span class="cm-cat-tab" data-cat="GI">Gastrointestinal</span>
                    <span class="cm-cat-tab" data-cat="NEURO">Neurology</span>
                    <span class="cm-cat-tab" data-cat="PAEDIATRIC">Paediatrics</span>
                    <span class="cm-cat-tab" data-cat="MATERNAL">Maternal / ANC</span>
                    <span class="cm-cat-tab" data-cat="CHRONIC">Chronic / NCD</span>
                    <span class="cm-cat-tab" data-cat="ENT">ENT / Eye</span>
                    <span class="cm-cat-tab" data-cat="MSK">Musculoskeletal</span>
                    <span class="cm-cat-tab" data-cat="OTHER">Other</span>
                </div>

                <!-- Complaint chips from master list -->
                <div id="cm-chip-grid">
                    <?php foreach ($ComplainList as $cl): ?>
                        <span class="cm-chip"
                              data-id="<?php echo (int)$cl->complain_id; ?>"
                              data-name="<?php echo htmlspecialchars(ucwords(strtolower($cl->complain_name))); ?>"
                              data-cat="<?php echo htmlspecialchars(isset($cl->category) ? $cl->category : 'GENERAL'); ?>">
                            <?php echo htmlspecialchars(ucwords(strtolower($cl->complain_name))); ?>
                        </span>
                    <?php endforeach; ?>
                    <span id="cm-no-results">No complaints match your search.</span>
                </div>

                <!-- Selected complaints panel -->
                <div id="cm-selected-panel">
                    <div class="cm-selected-label">Selected Complaints</div>
                    <div id="cm-selected-tags">
                        <span id="cm-empty-sel">None selected yet — tap a complaint above.</span>
                    </div>
                </div>

                <!-- Clinical fields (applies to whole batch) -->
                <div class="cm-clinical-row">
                    <div class="cm-field">
                        <label>Duration</label>
                        <input type="text" id="cm-duration" class="form-control"
                               placeholder="e.g. 3 days, 1 week">
                    </div>
                    <div class="cm-field">
                        <label>Severity</label>
                        <select id="cm-severity" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="Mild">Mild</option>
                            <option value="Moderate">Moderate</option>
                            <option value="Severe">Severe</option>
                        </select>
                    </div>
                    <div class="cm-field">
                        <label>Onset</label>
                        <select id="cm-onset" class="form-control">
                            <option value="">-- Select --</option>
                            <option value="Acute">Acute</option>
                            <option value="Chronic">Chronic</option>
                            <option value="Recurrent">Recurrent</option>
                        </select>
                    </div>
                </div>

                <!-- Custom complaint entry -->
                <div class="cm-custom-row">
                    <input type="text" id="cm-custom-input"
                           placeholder="Add custom complaint not listed above…" maxlength="200">
                    <button type="button" class="btn btn-default btn-custom-add" id="cm-custom-add">
                        <i class="fa fa-plus"></i> Add
                    </button>
                </div>

            </div><!-- /.modal-body -->

            <!-- Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">
                    <i class="fa fa-times"></i> Cancel
                </button>
                <button type="button" class="btn btn-primary" id="cm-btn-save" disabled>
                    <i class="fa fa-check-circle"></i>
                    <span id="cm-save-label">Save Complaints (0)</span>
                </button>
            </div>

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /#complaintsModal -->
<!-- ── END COMPLAINTS MODAL ──────────────────────────────────────── -->

<script>
(function($) {
    'use strict';

    /* ── State ──────────────────────────────────────────────────── */
    var selected = [];   // array of { id, name, isCustom }
    var BASE_URL = '<?php echo base_url(); ?>';
    var OPD_NO   = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
    var PAT_NO   = '<?php echo $getOPDPatient->patient_no; ?>';
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

    /* ── Helpers ────────────────────────────────────────────────── */
    function isSelected(name) {
        return selected.some(function(s) {
            return s.name.toLowerCase() === name.toLowerCase();
        });
    }

    function refreshSelectedPanel() {
        var $tags = $('#cm-selected-tags');
        $tags.empty();
        if (selected.length === 0) {
            $tags.html('<span id="cm-empty-sel">None selected yet — tap a complaint above.</span>');
        } else {
            selected.forEach(function(item) {
                var $tag = $('<span class="cm-sel-tag"></span>');
                $tag.append(document.createTextNode(item.name + ' '));
                var $x = $('<span class="cm-remove" title="Remove">&times;</span>');
                $x.on('click', function() { deselect(item.name); });
                $tag.append($x);
                $tags.append($tag);
            });
        }
        var count = selected.length;
        $('#cm-save-label').text('Save Complaints (' + count + ')');
        $('#cm-btn-save').prop('disabled', count === 0);
    }

    function select(id, name, isCustom) {
        if (isSelected(name)) return;
        selected.push({ id: id, name: name, isCustom: !!isCustom });
        /* mark chip as selected wherever it appears */
        var nameLower = name.toLowerCase();
        $('#cm-chip-grid .cm-chip').filter(function() {
            return $(this).data('name').toLowerCase() === nameLower;
        }).addClass('selected');
        $('#cm-quick-chips .cm-quick-chip').filter(function() {
            return $(this).data('name').toLowerCase() === nameLower;
        }).addClass('selected');
        $('#cm-top-chips .cm-top-chip').filter(function() {
            return $(this).data('name').toLowerCase() === nameLower;
        }).addClass('selected');
        refreshSelectedPanel();
    }

    function deselect(name) {
        var nameLower = name.toLowerCase();
        selected = selected.filter(function(s) {
            return s.name.toLowerCase() !== nameLower;
        });
        $('#cm-chip-grid .cm-chip').filter(function() {
            return $(this).data('name').toLowerCase() === nameLower;
        }).removeClass('selected');
        $('#cm-quick-chips .cm-quick-chip').filter(function() {
            return $(this).data('name').toLowerCase() === nameLower;
        }).removeClass('selected');
        $('#cm-top-chips .cm-top-chip').filter(function() {
            return $(this).data('name').toLowerCase() === nameLower;
        }).removeClass('selected');
        refreshSelectedPanel();
    }

    function resetModal() {
        selected = [];
        $('#cm-search').val('');
        $('#cm-duration').val('');
        $('#cm-severity').val('');
        $('#cm-onset').val('');
        $('#cm-custom-input').val('');
        $('#cm-chip-grid .cm-chip').removeClass('selected hidden');
        $('#cm-no-results').hide();
        $('#cm-quick-chips .cm-quick-chip').removeClass('selected');
        $('#cm-top-chips .cm-top-chip').removeClass('selected');
        /* reset category to ALL */
        $('#cm-cat-tabs .cm-cat-tab').removeClass('active');
        $('#cm-cat-tabs .cm-cat-tab[data-cat="ALL"]').addClass('active');
        refreshSelectedPanel();
    }

    /* ── Category filter ────────────────────────────────────────── */
    function applyCategory(cat) {
        var search = $('#cm-search').val().trim().toLowerCase();
        var $chips = $('#cm-chip-grid .cm-chip');
        var visible = 0;
        $chips.each(function() {
            var $c = $(this);
            var matchCat  = (cat === 'ALL') || ($c.data('cat') === cat);
            var matchSearch = (search === '') ||
                              ($c.data('name').toLowerCase().indexOf(search) > -1);
            if (matchCat && matchSearch) { $c.removeClass('hidden'); visible++; }
            else { $c.addClass('hidden'); }
        });
        $('#cm-no-results').toggle(visible === 0);
    }

    /* ── Search ─────────────────────────────────────────────────── */
    $('#cm-search').on('input', function() {
        var activeCat = $('#cm-cat-tabs .cm-cat-tab.active').data('cat') || 'ALL';
        applyCategory(activeCat);
    });

    /* ── Category tabs ──────────────────────────────────────────── */
    $('#cm-cat-tabs').on('click', '.cm-cat-tab', function() {
        $('#cm-cat-tabs .cm-cat-tab').removeClass('active');
        $(this).addClass('active');
        applyCategory($(this).data('cat'));
    });

    /* ── My Top Complaints chip click ─────────────────────────── */
    $('#cm-top-chips').on('click', '.cm-top-chip', function() {
        var name = $(this).data('name');
        var id   = $(this).data('id');
        /* resolve against master grid to get correct complain_id */
        var $master = $('#cm-chip-grid .cm-chip').filter(function() {
            return $(this).data('name').toLowerCase() === name.toLowerCase();
        });
        var resolvedId = $master.length ? $master.first().data('id') : (id || 0);
        var isCustom   = !resolvedId || resolvedId === 'others';
        if (isSelected(name)) { deselect(name); }
        else { select(resolvedId, name, isCustom); }
    });

    /* ── Master list chip click ─────────────────────────────────── */
    $('#cm-chip-grid').on('click', '.cm-chip', function() {
        var name = $(this).data('name');
        var id   = $(this).data('id');
        if (isSelected(name)) { deselect(name); }
        else { select(id, name, false); }
    });

    /* ── Quick-select chip click ────────────────────────────────── */
    $('#cm-quick-chips').on('click', '.cm-quick-chip', function() {
        var name = $(this).data('name');
        /* try to find matching master chip for its ID */
        var $master = $('#cm-chip-grid .cm-chip').filter(function() {
            return $(this).data('name').toLowerCase() === name.toLowerCase();
        });
        var id = $master.length ? $master.first().data('id') : 0;
        if (isSelected(name)) { deselect(name); }
        else { select(id, name, id === 0); }
    });

    /* ── Custom complaint ────────────────────────────────────────── */
    function addCustom() {
        var val = $.trim($('#cm-custom-input').val());
        if (!val) { $('#cm-custom-input').focus(); return; }
        if (isSelected(val)) {
            alert('"' + val + '" is already in your selection.');
            return;
        }
        select('others', val, true);
        $('#cm-custom-input').val('').focus();
    }

    $('#cm-custom-add').on('click', addCustom);
    $('#cm-custom-input').on('keydown', function(e) {
        if (e.which === 13) { e.preventDefault(); addCustom(); }
    });

    /* ── Save ────────────────────────────────────────────────────── */
    $('#cm-btn-save').on('click', function() {
        if (selected.length === 0) return;

        var duration = $.trim($('#cm-duration').val());
        var severity = $('#cm-severity').val();
        var onset    = $('#cm-onset').val();

        var entries = selected.map(function(s) {
            return { complain: s.name, duration: duration, severity: severity, onset: onset };
        });

        $('#cm-saving-overlay').css('display', 'flex');
        $('#cm-btn-save').prop('disabled', true);

        var postData = {
                opd_no:     OPD_NO,
                patient_no: PAT_NO,
                entries:    JSON.stringify(entries)
            };
        postData[csrfName] = csrfHash;
        $.ajax({
            url: BASE_URL + 'app/opd/save_complaint_batch',
            type: 'POST',
            data: postData,
            dataType: 'json',
            success: function(resp) {
                $('#cm-saving-overlay').hide();
                if (resp && resp.success) {
                    $('#complaintsModal').modal('hide');
                    if (resp.redirect) {
                        window.location.href = resp.redirect;
                    } else {
                        window.location.reload();
                    }
                } else if (resp && resp.status === 'warning') {
                    /* All-duplicate: stay open, re-enable save, show inline message */
                    $('#cm-btn-save').prop('disabled', false);
                    var $warn = $('<div class="alert alert-warning alert-dismissable" style="margin-top:10px;">'
                        + '<button type="button" class="close" data-dismiss="alert">&times;</button>'
                        + '<i class="fa fa-exclamation-triangle"></i> '
                        + (resp.message || 'These complaints are already recorded today.')
                        + '</div>');
                    $('#cm-selected-panel').after($warn);
                    setTimeout(function(){ $warn.fadeOut(400, function(){ $warn.remove(); }); }, 5000);
                } else {
                    $('#cm-btn-save').prop('disabled', false);
                    alert((resp && resp.message) ? resp.message : 'Error saving complaints. Please try again.');
                }
            },
            error: function(xhr) {
                $('#cm-saving-overlay').hide();
                $('#cm-btn-save').prop('disabled', false);
                var msg = 'Network error. Please try again.';
                if (xhr.responseText) {
                    try {
                        var r = JSON.parse(xhr.responseText);
                        if (r.message) msg = r.message;
                    } catch(e) {}
                }
                alert(msg);
            }
        });
    });

    /* ── Lifecycle ───────────────────────────────────────────────── */
    $('#complaintsModal').on('hidden.bs.modal', function() {
        resetModal();
    });

    /* trigger "Add Complaints" button → new modal */
    $(document).on('click', '[data-target="#complaintsModal"]', function() {
        /* ensure clean state */
        resetModal();
    });

}(jQuery));
</script>

</body>

</html>