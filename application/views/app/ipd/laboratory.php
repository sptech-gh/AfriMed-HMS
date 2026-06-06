<!DOCTYPE html>
<html>

<head>

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

        <!------------ bootstrap timepicker ---------------------------------->
        <link href="<?php echo base_url(); ?>public/timepicker/bootstrap-timepicker.min.css" rel="stylesheet" />
        <!-------------------------------------------------------------------->

        <!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/css/select2.min.css" rel="stylesheet" /> -->

        <!-- jQuery UI CSS -->
        <link rel="stylesheet" href="<?php echo base_url(); ?>public/css/jQueryUI/jquery-ui-1.10.3.custom.min.css">

        <style>
            .ui-autocomplete {
                position: absolute;
                cursor: default;
                z-index: 999999999 !important;
            }
        </style>


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
    <?php $ipdBatchEnabled = !empty($ipd_diagnostics_batch_enabled); ?>
    <?php
    $_ipd_patient = (isset($patientInfo) && is_object($patientInfo)) ? $patientInfo : null;
    $_ipd_visit = (isset($getOPDPatient) && is_object($getOPDPatient)) ? $getOPDPatient : null;
    if (!$_ipd_patient || !$_ipd_visit) {
        echo "<div style='padding:16px'><div class='alert alert-warning'><i class='fa fa-warning'></i> Please open this page from an In-Patient record (missing visit context).</div></div>";
        echo "</body></html>";
        return;
    }
    ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">

        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <!-- Right side column. Contains the navbar and content of the page -->
        <aside class="right-side">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <?php if ($this->session->userdata('emr_viewing') == "ipd_emr_viewing") { ?>
                    <h1>IPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url() ?>app/emr/ipd">In-Patient</a></li>
                    </ol>
                <?php } else { ?>
                    <h1>IPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url() ?>app/doctor/ipd">In-Patient Master</a></li>
                        <li><a href="#">In-Patient Information</a></li>
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
                                        <li><a href="<?php echo base_url() ?>app/ipd/view/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> General Information</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/medication/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/complain/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/progress_note/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Progress Note</a></li>

                                        <li><a href="<?php echo base_url() ?>app/ipd/bed_side_procedure/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Bed Side Procedure</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/operation_theater/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Operation Theater</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/patientHistory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
                                        <li class="active"><a href="<?php echo base_url() ?>app/ipd/laboratory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/discharge_summary/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Laboratory</a></li>

                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
                                    <?php if (!$canEditClinical) { ?>
                                        <div class="alert alert-info">Read-only — Doctor access only</div>
                                    <?php } ?>
                                    <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                        <?php if ($getOPDPatient->nStatus == "Pending") { ?>
                                            <?php if ($canEditClinical) { ?>
                                                <?php if ($ipdBatchEnabled) { ?>
                                                    <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#multiLabModal"><i class="fa fa-plus"></i> Add Laboratory</a>
                                                <?php } else { ?>
                                                    <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#legacyLabModal"><i class="fa fa-plus"></i> Add Laboratory</a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($canEditClinical && isset($hasAccesstoDoctor) && $hasAccesstoDoctor) { ?>
                                                <?php if ($ipdBatchEnabled) { ?>
                                                    <a href="#" class="btn btn-info" data-toggle="modal" data-target="#multiSonoModal"><i class="fa fa-plus"></i> Sonography Request</a>
                                                <?php } else { ?>
                                                    <a href="#" class="btn btn-info" data-toggle="modal" data-target="#legacySonoModal"><i class="fa fa-plus"></i> Sonography Request</a>
                                                <?php } ?>
                                            <?php } ?>
                                            <?php if ($canEditClinical) { ?>
                                                <a href="#" class="btn btn-warning" data-toggle="modal" data-target="#multiRadiologyModal"><i class="fa fa-stethoscope"></i> Request Radiology</a>
                                            <?php } ?>
                                        <?php } ?>
                                    <?php } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_laboratory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_laboratory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Laboratory</th>
                                                <th title="Doctor's clinical indication / free-text notes for the request">Clinical Indication / Notes</th>
                                                <th>Doctor In-Charge</th>
                                                <th>Findings</th>
                                                <th>Results</th>
                                                <th>Results (PDF)</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patient_lab as $patient_lab) { ?>
                                                <?php
                                                $canViewLabResult = false;
                                                $userId = (string)$this->session->userdata('user_id');
                                                if (isset($hasAccesstoAdmin) && $hasAccesstoAdmin) { $canViewLabResult = true; }
                                                if (isset($hasAccesstoLaboratory) && $hasAccesstoLaboratory) { $canViewLabResult = true; }
                                                if (!$canViewLabResult && isset($hasAccesstoDoctor) && $hasAccesstoDoctor) {
                                                    $req = isset($patient_lab->doctor_user_id) ? (string)$patient_lab->doctor_user_id : '';
                                                    $ass = isset($patient_lab->assigned_doctor_id) ? (string)$patient_lab->assigned_doctor_id : '';
                                                    if (($req !== '' && $req === $userId) || ($ass !== '' && $ass === $userId)) {
                                                        $canViewLabResult = true;
                                                    }
                                                }
                                                ?>
                                                <tr>
                                                    <td><?php echo date("M d, Y h:i:s A", strtotime($patient_lab->dDateTime)); ?></td>
                                                    <td>
                                                        <?php
                                                        $labName = isset($patient_lab->particular_name) ? (string)$patient_lab->particular_name : '';
                                                        if (isset($patient_lab->category_id) && (int)$patient_lab->category_id === 18) {
                                                            if (isset($patient_lab->sono_meta_id) && (int)$patient_lab->sono_meta_id > 0 && isset($patient_lab->sono_scan_item_id) && (int)$patient_lab->sono_scan_item_id > 0 && isset($patient_lab->sono_item_name) && trim((string)$patient_lab->sono_item_name) !== '') {
                                                                $labName = (string)$patient_lab->sono_item_name;
                                                            }
                                                            if (trim($labName) === '' && isset($patient_lab->laboratory_text)) {
                                                                $labName = (string)$patient_lab->laboratory_text;
                                                            }
                                                        }
                                                        echo $labName;
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $txt = isset($patient_lab->laboratory_text) ? (string)$patient_lab->laboratory_text : '';
                                                        if (isset($patient_lab->category_id) && (int)$patient_lab->category_id === 18 && isset($patient_lab->clinical_question) && trim((string)$patient_lab->clinical_question) !== '') {
                                                            $txt = (string)$patient_lab->clinical_question;
                                                        }
                                                        echo $txt;
                                                        ?>
                                                    </td>
                                                    <td><?php echo $patient_lab->doctor ?></td>
                                                    <td>
                                                        <?php
                                                        $pilot = (isset($lab_release_snapshot_read_pilot) && $lab_release_snapshot_read_pilot);
                                                        $isAdminOrLab = ((isset($hasAccesstoAdmin) && $hasAccesstoAdmin) || (isset($hasAccesstoLaboratory) && $hasAccesstoLaboratory));
                                                        $ioLabId = isset($patient_lab->io_lab_id) ? (int)$patient_lab->io_lab_id : 0;
                                                        $snap = ($pilot && isset($release_snapshot_map) && $ioLabId > 0 && isset($release_snapshot_map[$ioLabId])) ? $release_snapshot_map[$ioLabId] : null;
                                                        if ($pilot && $canViewLabResult && !$isAdminOrLab) {
                                                            echo $snap ? (isset($snap['findings_snapshot']) ? (string)$snap['findings_snapshot'] : '') : '<span class="text-muted">PENDING CONSOLIDATED RELEASE</span>';
                                                        } else {
                                                            echo $canViewLabResult ? $patient_lab->findings : '***';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        if ($pilot && $canViewLabResult && !$isAdminOrLab) {
                                                            echo $snap ? (isset($snap['result_snapshot']) ? (string)$snap['result_snapshot'] : '') : '<span class="text-muted">PENDING CONSOLIDATED RELEASE</span>';
                                                        } else {
                                                            echo $canViewLabResult ? $patient_lab->result : '***';
                                                        }
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <?php
                                                        $pilot = (isset($lab_release_snapshot_read_pilot) && $lab_release_snapshot_read_pilot);
                                                        $isAdminOrLab = ((isset($hasAccesstoAdmin) && $hasAccesstoAdmin) || (isset($hasAccesstoLaboratory) && $hasAccesstoLaboratory));
                                                        $ioLabId = isset($patient_lab->io_lab_id) ? (int)$patient_lab->io_lab_id : 0;
                                                        $snap = ($pilot && isset($release_snapshot_map) && $ioLabId > 0 && isset($release_snapshot_map[$ioLabId])) ? $release_snapshot_map[$ioLabId] : null;
                                                        ?>
                                                        <?php if ($canViewLabResult && $patient_lab->lab_result_upload && (!$pilot || $isAdminOrLab || $snap)) { ?>
                                                            <a target="_blank" href="<?php echo base_url(); ?>app/laboratory/download_result/<?php echo $patient_lab->io_lab_id; ?>">View/Download</a>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                                            <?php if ($getOPDPatient->nStatus == "Pending") { ?>
                                                                <span class="text-muted">Removal disabled</span>
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




    <!-- Multi-Entry Laboratory Modal (modern batch; posts to IPD batch endpoint) -->
    <?php if ($ipdBatchEnabled) { ?>
        <div class="modal fade multi-entry-modal" id="multiLabModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: #fff; border-radius: 4px 4px 0 0;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                        <h4 class="modal-title"><i class="fa fa-flask"></i> Add Multiple Lab Tests</h4>
                    </div>
                    <div class="modal-body" style="background: #f5f7fa;">
                        <?php if (isset($this->security)): ?>
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <?php endif; ?>
                        <?php
                        $modalPayerType = isset($patient_payer_type) ? strtoupper(trim((string)$patient_payer_type)) : 'CASH';
                        if ($modalPayerType === 'NHIS') {
                        ?>
                            <div class="alert alert-info" style="margin-bottom:10px;padding:7px 12px;">
                                <i class="fa fa-shield"></i> <strong>NHIS Patient</strong> - NHIS-covered tests are marked with a <span class="label label-success" style="font-size:10px;">NHIS</span> badge. NHIS rates apply where available.
                            </div>
                        <?php } ?>
                        <div class="multi-entry-summary">
                            <div>
                                <span class="count" id="lab-entry-count">0</span>
                                <span class="label-text">lab test(s) to request</span>
                            </div>
                            <button type="button" class="btn btn-add-entry" id="btn-add-lab">
                                <i class="fa fa-plus"></i> Add Lab Test
                            </button>
                        </div>
                        <div id="lab-entry-container">
                            <div class="empty-state">
                                <i class="fa fa-plus-circle"></i>
                                <p>Click "Add Lab Test" button to add items</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f5f7fa;">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                        <button type="button" class="btn btn-primary btn-save-all" id="btn-save-labs" disabled>
                            <i class="fa fa-check-circle"></i> Save All Lab Tests
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>

    <!-- Multi-Entry Sonography Modal (modern batch; posts to IPD batch endpoint) -->
    <?php if ($ipdBatchEnabled) { ?>
        <div class="modal fade multi-entry-modal" id="multiSonoModal" tabindex="-1" role="dialog">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: #fff; border-radius: 4px 4px 0 0;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                        <h4 class="modal-title"><i class="fa fa-heartbeat"></i> Add Multiple Sonography Requests</h4>
                    </div>
                    <div class="modal-body" style="background: #f5f7fa;">
                        <?php if (isset($this->security)): ?>
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <?php endif; ?>
                        <div class="multi-entry-summary" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <div>
                                <span class="count" id="sono-entry-count">0</span>
                                <span class="label-text">scan(s) to request</span>
                            </div>
                            <button type="button" class="btn btn-add-entry" id="btn-add-sono" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <i class="fa fa-plus"></i> Add Scan
                            </button>
                        </div>
                        <div id="sono-entry-container">
                            <div class="empty-state">
                                <i class="fa fa-plus-circle"></i>
                                <p>Click "Add Scan" button to add items</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f5f7fa;">
                        <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                        <button type="button" class="btn btn-primary btn-save-all" id="btn-save-sonos" disabled>
                            <i class="fa fa-check-circle"></i> Save All Scans
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php } ?>


    <!-- Multi-Entry Radiology Modal -->
    <div class="modal fade multi-entry-modal" id="multiRadiologyModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#f39c12;color:#fff;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                    <h4 class="modal-title"><i class="fa fa-stethoscope"></i> Add Multiple Radiology Requests</h4>
                </div>
                <div class="modal-body" style="background:#f5f7fa;">
                    <?php if (isset($this->security)): ?>
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <?php endif; ?>
                    <div class="multi-entry-summary">
                        <div>
                            <span class="count" id="radiology-entry-count">0</span>
                            <span class="label-text">test(s) to request</span>
                        </div>
                        <button type="button" class="btn btn-add-entry" id="btn-add-radiology">
                            <i class="fa fa-plus"></i> Add Test
                        </button>
                    </div>
                    <div id="radiology-entry-container">
                        <div class="empty-state">
                            <i class="fa fa-plus-circle"></i>
                            <p>Click "Add Test" button to add items</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f5f7fa;">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                    <button type="button" class="btn btn-primary btn-save-all" id="btn-save-radiology" disabled>
                        <i class="fa fa-check-circle"></i> Save All Tests
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$ipdBatchEnabled) { ?>
    <div class="modal fade" id="legacyLabModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url(); ?>app/ipd/save_laboratory">
                    <div class="modal-header" style="background:#3c8dbc;color:#fff;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                        <h4 class="modal-title"><i class="fa fa-flask"></i> Add Laboratory</h4>
                    </div>
                    <div class="modal-body">
                        <?php if (isset($this->security)): ?>
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID); ?>">
                        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no; ?>">
                        <input type="hidden" name="doctor" value="<?php echo (string)$this->session->userdata('user_id'); ?>">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Category</label>
                                    <select class="form-control" name="category" id="legacy_lab_category">
                                        <option value="">- Select Category -</option>
                                        <?php if (isset($particular_cat) && is_array($particular_cat)) { foreach ($particular_cat as $c) { ?>
                                            <option value="<?php echo (int)$c->group_id; ?>"><?php echo htmlspecialchars((string)$c->group_name, ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php } } ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Test</label>
                                    <div id="legacyLabItemContainer">
                                        <select name="particular" id="legacy_lab_particular" class="form-control">
                                            <option value="">- Particular Item -</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" class="form-control" name="dDate" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Time</label>
                                    <input type="time" class="form-control" name="cTime" value="<?php echo date('H:i'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="legacySonoModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <form method="post" action="<?php echo base_url(); ?>app/ipd/save_laboratory">
                    <div class="modal-header" style="background:#00a65a;color:#fff;">
                        <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                        <h4 class="modal-title"><i class="fa fa-heartbeat"></i> Sonography Request</h4>
                    </div>
                    <div class="modal-body">
                        <?php if (isset($this->security)): ?>
                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <?php endif; ?>
                        <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID); ?>">
                        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no; ?>">
                        <input type="hidden" name="doctor" value="<?php echo (string)$this->session->userdata('user_id'); ?>">
                        <input type="hidden" name="category" value="18">
                        <div class="form-group">
                            <label>Scan (optional)</label>
                            <div id="legacySonoItemContainer">
                                <select name="particular" id="legacy_sono_particular" class="form-control">
                                    <option value="">- Particular Item -</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Clinical Question / Indication</label>
                            <textarea class="form-control" name="clinical_question" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Urgency</label>
                                    <select class="form-control" name="urgency">
                                        <option value="ROUTINE">ROUTINE</option>
                                        <option value="URGENT">URGENT</option>
                                        <option value="STAT">STAT</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" class="form-control" name="dDate" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Time</label>
                            <input type="time" class="form-control" name="cTime" value="<?php echo date('H:i'); ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php } ?>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/smart-medical-autocomplete.js"></script>
    <script src="<?php echo base_url(); ?>public/js/multi-entry-manager.js"></script>

    <script>
        $(document).ready(function() {
            var baseUrl = '<?php echo base_url(); ?>';
            var ipdNo = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
            var patientNo = '<?php echo $getOPDPatient->patient_no; ?>';
            var ipdBatchEnabled = <?php echo $ipdBatchEnabled ? 'true' : 'false'; ?>;
            window.getItemRate = window.getItemRate || function() {};

            if (!ipdBatchEnabled) {
                $('#legacy_lab_category').on('change', function() {
                    var catId = $(this).val();
                    if (!catId) {
                        $('#legacyLabItemContainer').html('<select name="particular" id="legacy_lab_particular" class="form-control"><option value="">- Particular Item -</option></select>');
                        return;
                    }
                    $('#legacyLabItemContainer').load(baseUrl + 'app/billing/getItem/' + catId + '?patient_no=' + encodeURIComponent(patientNo), function() {
                        $('#legacyLabItemContainer').find('select[name="particular"]').attr('id', 'legacy_lab_particular');
                    });
                });

                $('#legacySonoModal').on('shown.bs.modal', function() {
                    if ($('#legacySonoItemContainer').data('loaded')) return;
                    $('#legacySonoItemContainer').data('loaded', true);
                    $('#legacySonoItemContainer').load(baseUrl + 'app/billing/getSonoItem/18?patient_no=' + encodeURIComponent(patientNo), function() {
                        $('#legacySonoItemContainer').find('select[name="particular"]').attr('id', 'legacy_sono_particular');
                    });
                });
            }

            // Lab categories and tests data - SSOT: bill_particular
            var labCategories = <?php echo json_encode(isset($lab_categories) ? array_map(function($c) {
                return array('category_id' => $c->category_id, 'category_name' => $c->category_name);
            }, $lab_categories) : array()); ?>;

            var labTests = <?php echo json_encode(isset($lab_tests) ? array_map(function($t) {
                return array(
                    'laboratory_id' => isset($t->particular_id) ? (int)$t->particular_id : 0,
                    'particular_name' => isset($t->particular_name) ? $t->particular_name : '',
                    'test_code' => '',
                    'category_id' => isset($t->category_id) ? (int)$t->category_id : 0,
                    'is_nhis_covered' => isset($t->is_nhis_covered) ? (int)$t->is_nhis_covered : 0,
                    'nhis_code' => isset($t->nhis_code) ? $t->nhis_code : '',
                    'nhis_price' => isset($t->nhis_price) ? (float)$t->nhis_price : 0,
                    'charge_amount' => isset($t->charge_amount) ? (float)$t->charge_amount : 0,
                    'specimen_type' => '',
                    'particular_id' => isset($t->particular_id) ? (int)$t->particular_id : null,
                );
            }, $lab_tests) : array()); ?>;

            // Sonography items - GHS Standard Catalog (match OPD)
            var sonoItems = <?php echo json_encode(isset($sono_items) ? array_map(function($s) {
                return array(
                    'item_id' => $s->test_id,
                    'item_name' => $s->test_name,
                    'test_code' => isset($s->test_code) ? $s->test_code : '',
                    'category' => isset($s->category) ? $s->category : '',
                    'body_part' => isset($s->body_part) ? $s->body_part : '',
                    'price' => isset($s->price) ? (float)$s->price : 0,
                    'nhis_code' => isset($s->nhis_code) ? $s->nhis_code : '',
                    'nhis_price' => isset($s->nhis_price) ? (float)$s->nhis_price : 0,
                    'is_nhis_covered' => isset($s->is_nhis_covered) ? (int)$s->is_nhis_covered : 0,
                    'preparation' => isset($s->preparation) ? $s->preparation : '',
                    'particular_id' => isset($s->particular_id) ? $s->particular_id : null,
                );
            }, $sono_items) : array()); ?>;

            var sonoCategories = <?php echo json_encode(isset($sono_categories) ? $sono_categories : array()); ?>;

            // Radiology tests
            var radiologyTests = <?php echo json_encode(isset($radiology_tests) ? array_map(function($t) {
                return array(
                    'test_id' => isset($t->id) ? (int)$t->id : (isset($t->particular_id) ? (int)$t->particular_id : 0),
                    'test_name' => isset($t->test_name) ? $t->test_name : (isset($t->particular_name) ? $t->particular_name : ''),
                    'price' => isset($t->price) ? (float)$t->price : (isset($t->charge_amount) ? (float)$t->charge_amount : 0),
                    'is_nhis_covered' => isset($t->is_nhis_covered) ? (int)$t->is_nhis_covered : 0
                );
            }, $radiology_tests) : array()); ?>;

            if (ipdBatchEnabled) {
                // Lab Tests Multi-Entry (IPD)
                var labManager = new MultiEntryManager();
                labManager.init(baseUrl, {
                    module: 'laboratory',
                    containerId: 'lab-entry-container',
                    countId: 'lab-entry-count',
                    saveButtonId: 'btn-save-labs',
                    saveUrlPrefix: 'app/ipd/',
                    onSaveSuccess: function(response) {
                        if (response.redirect) window.location.href = response.redirect;
                    }
                });

                $('#btn-add-lab').on('click', function() {
                    labManager.addEntry('laboratory', { categories: labCategories, tests: labTests });
                    $('#lab-entry-count').text($('#lab-entry-container .multi-entry-row').length);
                    $('#btn-save-labs').prop('disabled', false);
                });

                $('#btn-save-labs').on('click', function() {
                    labManager.saveAll('laboratory', ipdNo, patientNo);
                });

                $('#multiLabModal').on('hidden.bs.modal', function() {
                    labManager.resetModal();
                    $('#lab-entry-count').text('0');
                    $('#btn-save-labs').prop('disabled', true);
                });

                $('#multiLabModal').on('shown.bs.modal', function() {
                    if ($('#lab-entry-container .multi-entry-row').length === 0) {
                        labManager.addEntry('laboratory', { categories: labCategories, tests: labTests });
                        $('#lab-entry-count').text('1');
                        $('#btn-save-labs').prop('disabled', false);
                    }
                });

                // Sonography Multi-Entry (IPD)
                var sonoManager = new MultiEntryManager();
                sonoManager.init(baseUrl, {
                    module: 'sonography',
                    containerId: 'sono-entry-container',
                    countId: 'sono-entry-count',
                    saveButtonId: 'btn-save-sonos',
                    saveUrlPrefix: 'app/ipd/',
                    onSaveSuccess: function(response) {
                        if (response.redirect) window.location.href = response.redirect;
                    }
                });

                $('#btn-add-sono').on('click', function() {
                    sonoManager.addEntry('sonography', { items: sonoItems });
                    $('#sono-entry-count').text($('#sono-entry-container .multi-entry-row').length);
                    $('#btn-save-sonos').prop('disabled', false);
                });

                $('#btn-save-sonos').on('click', function() {
                    sonoManager.saveAll('sonography', ipdNo, patientNo);
                });

                $('#multiSonoModal').on('hidden.bs.modal', function() {
                    sonoManager.resetModal();
                    $('#sono-entry-count').text('0');
                    $('#btn-save-sonos').prop('disabled', true);
                });

                $('#multiSonoModal').on('shown.bs.modal', function() {
                    if ($('#sono-entry-container .multi-entry-row').length === 0) {
                        sonoManager.addEntry('sonography', { items: sonoItems });
                        $('#sono-entry-count').text('1');
                        $('#btn-save-sonos').prop('disabled', false);
                    }
                });
            }

            // Radiology Multi-Entry (IPD) - already supported by IPD save_radiology_batch
            var radiologyManager = new MultiEntryManager();
            radiologyManager.init(baseUrl, {
                module: 'radiology',
                containerId: 'radiology-entry-container',
                countId: 'radiology-entry-count',
                saveButtonId: 'btn-save-radiology',
                saveUrlPrefix: 'app/ipd/',
                onSaveSuccess: function(response) {
                    if (response.redirect) window.location.href = response.redirect;
                }
            });

            $('#btn-add-radiology').on('click', function() {
                radiologyManager.addEntry('radiology', { tests: radiologyTests });
                $('#radiology-entry-count').text($('#radiology-entry-container .multi-entry-row').length);
                $('#btn-save-radiology').prop('disabled', false);
            });

            $('#btn-save-radiology').on('click', function() {
                radiologyManager.saveAll('radiology', ipdNo, patientNo);
            });

            $('#multiRadiologyModal').on('hidden.bs.modal', function() {
                radiologyManager.resetModal();
                $('#radiology-entry-count').text('0');
                $('#btn-save-radiology').prop('disabled', true);
            });

            $('#multiRadiologyModal').on('shown.bs.modal', function() {
                if ($('#radiology-entry-container .multi-entry-row').length === 0) {
                    radiologyManager.addEntry('radiology', { tests: radiologyTests });
                    $('#radiology-entry-count').text('1');
                    $('#btn-save-radiology').prop('disabled', false);
                }
            });

            // SmartMedical init (kept for parity with OPD; safe no-op if unused)
            try {
                SmartMedical.init(baseUrl);
                SmartMedical.injectStyles();
            } catch (e) {}
        });
    </script>


</body>

</html>
