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

			.opd-patient-sidebar .box { box-shadow:0 1px 3px rgba(0,0,0,0.08); border:1px solid rgba(0,0,0,0.04); }
			.opd-patient-card { border-top:3px solid #3c8dbc; }
			.opd-patient-card .box-body { padding:15px; }
			.opd-patient-card .media { display:flex; gap:12px; align-items:center; }
			.opd-patient-card .media-left img { width:64px; height:64px; object-fit:cover; }
			.opd-patient-card .media-heading { margin:0; font-size:14px; font-weight:700; }
			.opd-patient-meta { margin-top:2px; font-size:12px; color:#777; }
			.opd-patient-meta strong { color:#333; }

			.opd-side-nav { margin:0; }
			.opd-side-nav > li > a { padding:10px 12px; border-radius:4px; font-size:13px; }
			.opd-side-nav > li.active > a,
			.opd-side-nav > li.active > a:hover { background:#3c8dbc; color:#fff; }
			.opd-side-nav > li > a:hover { background:#f5f7fa; }

			.opd-tabs-custom { border-top:3px solid #3c8dbc; box-shadow:0 1px 3px rgba(0,0,0,0.08); }
			.opd-tabs-custom > .nav-tabs > li > a { font-weight:600; font-size:13px; padding:10px 14px; }
			.opd-tabs-custom > .tab-content { padding:12px; }

			.opd-history-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; margin-bottom:10px; }

			@media (min-width: 992px) {
				.opd-patient-sidebar { position:sticky; top:70px; }
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

    <div class="wrapper row-offcanvas row-offcanvas-left">

        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <!-- Right side column. Contains the navbar and content of the page -->
        <aside class="right-side">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <?php if($this->session->userdata('emr_viewing') == "opd_emr_viewing"){?>	
                   <h1>OPD Patient Information</h1>
                   <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">EMR sheet</a></li>
                        <li><a href="<?php echo base_url() ?>app/emr/opd">Out-Patient Master</a></li>
                    </ol>
                    <?php }else if(!isset($hasAccesstoDoctor) || !$hasAccesstoDoctor){?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Patient Management</a></li>
                        <li><a href="<?php echo base_url() ?>app/opd/index">Out-Patient Master</a></li>
                        <li><a href="#">Out-Patient Information</a></li>
                    </ol>
                <?php }else{?>
                    <h1>OPD Patient Information</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="#">Doctor Module</a></li>
                        <li><a href="<?php echo base_url() ?>app/doctor/opd">Out-Patient Master</a></li>
                        <li><a href="#">Out-Patient Information</a></li>
                    </ol>
                <?php } ?>
            </section>

            <!-- Main content -->
            <section class="content">





                <div class="row">

					<div class="col-md-3 opd-patient-sidebar">
						<div class="box opd-patient-card">
							<div class="box-body">
								<?php
								if (!$patientInfo->picture) {
									$picture = "avatar.png";
								} else {
									$picture = $patientInfo->picture;
								}
								?>
								<div class="media">
									<div class="media-left">
										<img src="<?php echo base_url(); ?>public/patient_picture/<?php echo $picture; ?>" class="img-circle" alt="Patient" />
									</div>
									<div class="media-body">
										<h4 class="media-heading"><?php echo htmlspecialchars((string)$patientInfo->name); ?></h4>
										<div class="opd-patient-meta">
											Patient No: <strong><?php echo htmlspecialchars((string)$patientInfo->patient_no); ?></strong>
										</div>
									</div>
								</div>
							</div>
							<div class="box-footer clearfix" style="padding:12px;">
								<ul class="nav nav-pills nav-stacked opd-side-nav">

									<li><a href="<?php echo base_url() ?>app/opd/view/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> General Information</a></li>

									<li><a href="<?php echo base_url() ?>app/opd/diagnosis/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>

									<li><a href="<?php echo base_url() ?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
									<li><a href="<?php echo base_url() ?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
									<li><a href="<?php echo base_url() ?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Vital Sign</a></li>
									<li><a href="<?php echo base_url() ?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
									<li class="active"><a href="<?php echo base_url() ?>app/opd/laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
									<li><a href="<?php echo base_url() ?>app/opd/procedures/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Procedures</a></li>
									<li><a href="<?php echo base_url() ?>app/opd/discharge_summary/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
									<!--<li><a href="<?php echo base_url() ?>app/opd/billing/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Admission Billing</a></li>-->
									<?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>

								</ul>
							</div>
						</div>
					</div>

                    <div class="col-md-9">
                        <div class="nav-tabs-custom opd-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Laboratory</a></li>

                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
										<?php if (!$canEditClinical) { ?>
											<div class="alert alert-info">Read-only — Doctor access only</div>
										<?php } ?>
										<div class="opd-history-actions">
                                    <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                        <?php if ($getOPDPatient->nStatus == "Pending") { ?>
												<?php if ($canEditClinical) { ?>
													<a href="#" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#multiLabModal"><i class="fa fa-plus-circle"></i> Add Lab Tests</a>
												<?php } ?>
												<?php if ($canEditClinical && isset($hasAccesstoDoctor) && $hasAccesstoDoctor) { ?>
													<a href="#" class="btn btn-info btn-lg" data-toggle="modal" data-target="#multiSonoModal"><i class="fa fa-plus-circle"></i> Sonography Requests</a>
												<?php } ?>
												<?php if ($canEditClinical) { ?>
													<a href="#" class="btn btn-warning btn-lg" data-toggle="modal" data-target="#multiRadiologyModal"><i class="fa fa-stethoscope"></i> Request Radiology</a>
												<?php } ?>

                                    <?php }
                                    } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
										</div>
										<div class="table-responsive">
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
                                                        $labName = isset($patient_lab->particular_name) ? trim((string)$patient_lab->particular_name) : '';
                                                        if ($labName === '' && isset($patient_lab->laboratory_text) && trim((string)$patient_lab->laboratory_text) !== '') {
                                                            $labName = trim((string)$patient_lab->laboratory_text);
                                                        }
                                                        if ($labName === '' && isset($patient_lab->category_id) && (int)$patient_lab->category_id === 18) {
                                                            if (isset($patient_lab->sono_meta_id) && (int)$patient_lab->sono_meta_id > 0 && isset($patient_lab->sono_scan_item_id) && (int)$patient_lab->sono_scan_item_id > 0 && isset($patient_lab->sono_item_name) && trim((string)$patient_lab->sono_item_name) !== '') {
                                                                $labName = (string)$patient_lab->sono_item_name;
                                                            }
                                                        }
                                                        // Last resort: show category/group name so the row isn't blank
                                                        if ($labName === '' && isset($patient_lab->group_name) && trim((string)$patient_lab->group_name) !== '') {
                                                            $labName = trim((string)$patient_lab->group_name) . ' Test #' . (isset($patient_lab->io_lab_id) ? $patient_lab->io_lab_id : '?');
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
                                                        <?php if ($this->session->userdata('emr_viewing') == "" && $canEditClinical) { ?>
                                                            <?php if ($getOPDPatient->nStatus == "Pending") { ?>
                                                                <?php
                                                                $labId = isset($patient_lab->io_lab_id) ? (int)$patient_lab->io_lab_id : 0;
                                                                $delInfo = (isset($lab_delete_map) && isset($lab_delete_map[$labId])) ? $lab_delete_map[$labId] : array('allowed' => false, 'reason' => '');
                                                                $delUserId = (string)$this->session->userdata('user_id');
                                                                $delIsOwner = (isset($patient_lab->doctor_user_id) && (string)$patient_lab->doctor_user_id === $delUserId);
                                                                $delIsAdmin = (isset($hasAccesstoAdmin) && $hasAccesstoAdmin);
                                                                ?>
                                                                <?php if ($delInfo['allowed'] && ($delIsOwner || $delIsAdmin)) { ?>
											<form method="post" action="<?php echo base_url(); ?>app/opd/delete_lab/<?php echo $labId; ?>/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove this test request? This action cannot be undone.');">
												<input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
												<button type="submit" class="btn btn-xs btn-danger"><i class="fa fa-trash"></i> Remove</button>
											</form>
                                                                <?php } elseif ($delIsOwner || $delIsAdmin) { ?>
                                                                    <span class="text-muted" title="<?php echo htmlspecialchars($delInfo['reason']); ?>"><i class="fa fa-lock"></i> <?php echo htmlspecialchars($delInfo['reason']); ?></span>
                                                                <?php } ?>
                                                            <?php }
                                                        } ?>
                                                    </td>
                                                </tr>
                                            <?php } ?>
                                        </tbody>
                                    </table>
										</div>

										<div style="height:40px;"></div>
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




    <!-- Modal -->
    <form method="post" action="<?php echo base_url() ?>app/opd/save_laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" onSubmit="return confirm('Are you sure you want to save?');">
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID) ?>">
        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no ?>">
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="myModalLabel">Laboratory</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <td>Lab Test</td>
                                    <td>
                                        <input type="text" id="lab_test_search" name="laboratory_text" placeholder="Search or type lab test name (e.g. FBC, Malaria, Blood Sugar...)" class="form-control input-sm" style="width: 100%;" autocomplete="off" required>
                                        <input type="hidden" name="category" id="category" value="others">
                                        <input type="hidden" name="item" id="item" value="">
                                        <input type="hidden" id="lab_test_master_id" value="">
                                        <small class="text-muted"><i class="fa fa-info-circle"></i> Search existing tests or type a custom request</small>
                                        <div id="lab_test_search_info" class="smart-search-info" style="display:none; margin-top:4px;"></div>
                                    </td>
                                </tr>
                                <tr id="sono_question" style="display: none;">
                                    <td></td>
                                    <td><textarea id="clinical_question" name="clinical_question" placeholder="clinical question / other sonography request" class="form-control input-sm" style="width: 100%;" rows="3"></textarea></td>
                                <tr id="sono_urgency" style="display: none;">
								<td>Urgency</td>
								<td>
									<select name="urgency" id="urgency" class="form-control input-sm" style="width: 100%;">
										<option value="ROUTINE" selected>Routine</option>
										<option value="URGENT">Urgent</option>
										<option value="STAT">STAT</option>
									</select>
								</td>
							</tr>
                                <tr>
                                    <td>Priority</td>
                                    <td>
                                        <label style="font-weight:normal;">
                                            <input type="checkbox" name="is_urgent" value="1" id="lab_is_urgent">
                                            <span class="text-danger"><i class="fa fa-exclamation-triangle"></i> <strong>Mark as Urgent</strong></span>
                                        </label>
                                        <input type="hidden" name="is_urgent" value="0">
                                    </td>
                                </tr>
                                <tr>
                                    <td>Specimen Type</td>
                                    <td>
                                        <select name="specimen_type" id="specimen_type" class="form-control input-sm" style="width:100%;">
                                            <option value="">- Select Specimen -</option>
                                            <option value="Blood (Venous)">Blood (Venous)</option>
                                            <option value="Blood (Capillary)">Blood (Capillary)</option>
                                            <option value="Urine (Mid-stream)">Urine (Mid-stream)</option>
                                            <option value="Stool">Stool</option>
                                            <option value="Swab (HVS)">Swab (HVS)</option>
                                            <option value="Swab (Throat)">Swab (Throat)</option>
                                            <option value="Sputum">Sputum</option>
                                            <option value="CSF">CSF</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Date</td>
                                    <td><input type="text" name="dDate" id="dDate" value="<?php echo date("Y-m-d"); ?>" placeholder="Date" class="form-control input-sm" style="width: 100%;" required></td>
                                </tr>
                                <tr>
                                    <td>Time</td>
                                    <td>
                                        <div class="bootstrap-timepicker">
                                            <div class="form-group">
                                                <div class="input-group">
                                                    <input type="text" class="form-control timepicker" name="cTime" id="cTime" />
                                                    <div class="input-group-addon">
                                                        <i class="fa fa-clock-o"></i>
                                                    </div>
                                                </div><!-- /.input group -->
                                            </div><!-- /.form group -->
                                        </div>

                                    </td>
                                </tr>
                                <tr>
                                    <td>Doctor</td>
                                    <td>
                                        <select name="doctor" id="doctor" class="form-control input-sm" style="width: 100%;" required>
                                            <option value="">- Consultant Doctor -</option>
                                            <?php
                                            foreach ($doctorList2 as $doctorList2) {
                                            ?>
                                                <option value="<?php echo $doctorList2->user_id; ?>" <?php if ($userInfo->firstname . " " . $userInfo->lastname = $doctorList2->name) {
                                                                                                            echo "selected";
                                                                                                        } ?>><?php echo $doctorList2->name; ?></option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr style="display: none;">
                                    <td>Findings</td>
                                    <td><textarea name="findings" placeholder="Findings" class="form-control input-sm" style="width: 100%;" rows="3"></textarea></td>
                                </tr>
                                <tr style="display: none;">
                                    <td>Results</td>
                                    <td><textarea name="results" placeholder="Results" class="form-control input-sm" style="width: 100%;" rows="3"></textarea></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button name="btnSubmit" class="btn btn-primary" id="btnSubmit" type="submit" style="font-size:12px;">Save</button>
                    </div>

                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </form>
    <!-- /.modal -->

    <!-- Sonography Modal (Category-based only) -->
    <form method="post" action="<?php echo base_url() ?>app/opd/save_laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" onSubmit="return confirm('Are you sure you want to save?');">
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID) ?>">
        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no ?>">
        <input type="hidden" name="category" value="<?php echo isset($sonography_category_id) ? (int)$sonography_category_id : 18; ?>">
        <div class="modal fade" id="sonoModal" tabindex="-1" role="dialog" aria-labelledby="sonoModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="sonoModalLabel"><i class="fa fa-video-camera"></i> Sonography Request</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <td>Scan Type</td>
                                    <td>
                                        <select name="item" id="sono_item" class="form-control input-sm" style="width: 100%;" required>
                                            <option value="">- Select Sonography Type -</option>
                                            <?php
                                            if (isset($sono_items) && is_array($sono_items)) {
                                                foreach ($sono_items as $si) {
                                                    $sono_id = isset($si->test_id) ? $si->test_id : (isset($si->scan_item_id) ? $si->scan_item_id : '');
                                                    $sono_name = isset($si->test_name) ? $si->test_name : (isset($si->item_name) ? $si->item_name : '');
                                                    if ($sono_id && $sono_name) { ?>
                                                    <option value="<?php echo $sono_id; ?>"><?php echo htmlspecialchars($sono_name); ?></option>
                                            <?php }
                                                }
                                            } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Clinical Question</td>
                                    <td><textarea name="clinical_question" placeholder="Clinical question or indication for scan..." class="form-control input-sm" style="width: 100%;" rows="3"></textarea></td>
                                </tr>
                                <tr>
                                    <td>Urgency</td>
                                    <td>
                                        <select name="urgency" class="form-control input-sm" style="width: 100%;">
                                            <option value="ROUTINE" selected>Routine</option>
                                            <option value="URGENT">Urgent</option>
                                            <option value="STAT">STAT</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Date</td>
                                    <td><input type="text" name="dDate" id="sono_dDate" value="<?php echo date("Y-m-d"); ?>" class="form-control input-sm datepicker" style="width: 100%;" required></td>
                                </tr>
                                <tr>
                                    <td>Time</td>
                                    <td>
                                        <div class="input-group">
                                            <input type="text" class="form-control timepicker" name="cTime" id="sono_cTime" />
                                            <div class="input-group-addon"><i class="fa fa-clock-o"></i></div>
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Doctor</td>
                                    <td>
                                        <select name="doctor" class="form-control input-sm" style="width: 100%;" required>
                                            <option value="">- Requesting Doctor -</option>
                                            <?php
                                            if (isset($doctorList) && is_array($doctorList)) {
                                                foreach ($doctorList as $doc) { ?>
                                                    <option value="<?php echo $doc->user_id; ?>" <?php if (isset($userInfo) && $userInfo->user_id == $doc->user_id) echo "selected"; ?>><?php echo $doc->name; ?></option>
                                            <?php }
                                            } ?>
                                        </select>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button name="btnSubmit" class="btn btn-info" type="submit"><i class="fa fa-save"></i> Save Request</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <!-- /.sono modal -->





    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <?php require_once(APPPATH.'views/app/opd/_detain_admit_modals.php'); ?>
    <!-- <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script> -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    <!-- jQuery UI -->
    <script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>


    <!-- bootstrap time picker -->
    <script src="<?php echo base_url(); ?>public/timepicker/js/plugins/timepicker/bootstrap-timepicker.min.js" type="text/javascript"></script>
    <!-- AdminLTE App -->
    <script src="<?php echo base_url(); ?>public/timepicker/js/AdminLTE/app.js" type="text/javascript"></script>

    <script type="text/javascript">
        $(function() {

            //Timepicker
            $(".timepicker").timepicker({
                showInputs: false
            });
        });

        function preselectSonography() {
            var categoryId = '18';
            try {
                $('#category').val(categoryId);
            } catch (e) {
                return true;
            }
            try {
                otherOptions(categoryId);
            } catch (e) {}
            try {
                showDrugName(categoryId);
            } catch (e) {}
            return true;
        }
    </script>

    <!-- DATE -->
    <script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>
    <script type="text/javascript">
        // When the document is ready
        $(document).ready(function() {

            $('#dDate').datepicker({
                //format: "dd/mm/yyyy"
                format: "yyyy-mm-dd"
            });

        });
    </script>
    <!-- END DATE -->


    <script language="javascript">
        function showDrugList(category_id) {
            if (window.XMLHttpRequest) {
                xmlhttp3 = new XMLHttpRequest();
            } else { // code for IE6, IE5
                xmlhttp3 = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp3.onreadystatechange = function() {
                if (xmlhttp3.readyState == 4 && xmlhttp3.status == 200) {

                    document.getElementById("showDrugListItem").innerHTML = xmlhttp3.responseText;
                }
            }
            var supp;
            xmlhttp3.open("GET", "<?php echo base_url(); ?>app/billing/drug_list/" + category_id, true);
            xmlhttp3.send();

        }

        function getDrugRate(category_id) {
            if (window.XMLHttpRequest) {
                xmlhttp5 = new XMLHttpRequest();
            } else { // code for IE6, IE5
                xmlhttp5 = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp5.onreadystatechange = function() {
                if (xmlhttp5.readyState == 4 && xmlhttp5.status == 200) {

                    document.getElementById("showDrugRate").innerHTML = xmlhttp5.responseText;
                }
            }

            xmlhttp5.open("GET", "<?php echo base_url(); ?>app/billing/getDrugRate/" + category_id, true);
            xmlhttp5.send();

        }


        function showDrugName(category_id) {
            if (window.XMLHttpRequest) {
                xmlhttp = new XMLHttpRequest();
            } else { // code for IE6, IE5
                xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp.onreadystatechange = function() {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {

                    document.getElementById("showCategories").innerHTML = xmlhttp.responseText;
                }
            }
            var supp;

			var url = "<?php echo base_url(); ?>app/billing/getItem/" + category_id;
			if (String(category_id) === '18') {
				url = "<?php echo base_url(); ?>app/billing/getSonoItem/" + category_id;
			}
			xmlhttp.open("GET", url, true);
            xmlhttp.send();

        }

        function getItemRate(category_id) {
			try {
				if (String($('#category').val()) === '18') {
					return;
				}
			} catch (e) {}
            if (window.XMLHttpRequest) {
                xmlhttp2 = new XMLHttpRequest();
            } else { // code for IE6, IE5
                xmlhttp2 = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp2.onreadystatechange = function() {
                if (xmlhttp2.readyState == 4 && xmlhttp2.status == 200) {

                    document.getElementById("showRate").innerHTML = xmlhttp2.responseText;
                }
            }

            xmlhttp2.open("GET", "<?php echo base_url(); ?>app/billing/getRate/" + category_id, true);
            xmlhttp2.send();

        }



        function showBills(val) {
            if (val == "particular") {
                document.getElementById("particular").style.display = "inline";
                document.getElementById("particular_item").style.display = "inline";
                document.getElementById("category").style.display = "inline";
                document.getElementById("showCategories").style.display = "inline";
                document.getElementById("showRate").style.display = "inline";
                document.getElementById("medicine").style.display = "none";
                document.getElementById("drug_name").style.display = "none";
                document.getElementById("medicine_cat").style.display = "none";
                document.getElementById("showDrugListItem").style.display = "none";
                document.getElementById("showDrugRate").style.display = "none";
                document.getElementById("buttonMedication").style.display = "none";
            } else if (val == "medicine") {
                document.getElementById("particular").style.display = "none";
                document.getElementById("particular_item").style.display = "none";
                document.getElementById("category").style.display = "none";
                document.getElementById("showCategories").style.display = "none";
                document.getElementById("showRate").style.display = "none";
                document.getElementById("medicine").style.display = "inline";
                document.getElementById("drug_name").style.display = "inline";
                document.getElementById("medicine_cat").style.display = "inline";
                document.getElementById("showDrugListItem").style.display = "inline";
                document.getElementById("showDrugRate").style.display = "inline";
                document.getElementById("buttonMedication").style.display = "inline";
            }
        }
    </script>


    <script>
        function otherOptions(val) {
            // Sonography-specific fields
            if (val == '18') {
                $('#sono_question').show();
                $('#sono_urgency').show();
            } else {
                $('#sono_question').hide();
                $('#sono_urgency').hide();
            }
        }
    </script>


    <!-- Smart Medical Autocomplete -->
    <script src="<?php echo base_url(); ?>public/js/smart-medical-autocomplete.js"></script>
    <script type='text/javascript'>
        $(document).ready(function() {
            SmartMedical.init('<?= base_url() ?>');
            SmartMedical.injectStyles();

            // Unified lab test search with custom entry support
            SmartMedical.initLabTestAutocomplete(
                '#lab_test_search',
                '#lab_test_master_id',
                '#lab_test_search_info',
                {
                    minLength: 2,
                    allowCustom: true,
                    onSelect: function(item) {
                        if (item && item.id && item.id !== 0 && !item.isCustom) {
                            $('#item').val(item.particular_id || item.id);
                            $('#category').val(item.category_id || 'others');
                        } else {
                            $('#item').val('');
                            $('#category').val('others');
                        }
                    }
                }
            );
        });
    </script>

<!-- Multi-Entry Laboratory Modal -->
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
                    <i class="fa fa-shield"></i> <strong>NHIS Patient</strong> — NHIS-covered tests are marked with a <span class="label label-success" style="font-size:10px;">NHIS</span> badge. NHIS rates apply where available.
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

<!-- Multi-Entry Sonography Modal -->
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
                <div class="multi-entry-summary">
                    <div>
                        <span class="count" id="sono-entry-count">0</span>
                        <span class="label-text">scan(s) to request</span>
                    </div>
                    <button type="button" class="btn btn-add-entry" id="btn-add-sono">
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

<!-- Multi-Entry Radiology Modal -->
<div class="modal fade multi-entry-modal" id="multiRadiologyModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: #fff; border-radius: 4px 4px 0 0;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-stethoscope"></i> Add Multiple Radiology Requests</h4>
            </div>
            <div class="modal-body" style="background: #f5f7fa;">
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
            <div class="modal-footer" style="background: #f5f7fa;">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-primary btn-save-all" id="btn-save-radiology" disabled>
                    <i class="fa fa-check-circle"></i> Save All Tests
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/multi-entry-manager.js"></script>
<script>
$(document).ready(function() {
    var opdNo = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
    var patientNo = '<?php echo $getOPDPatient->patient_no; ?>';

    // Lab categories and tests data - SSOT: bill_particular
    var labCategories = <?php echo json_encode(isset($lab_categories) ? array_map(function($c) {
        return array('category_id' => $c->category_id, 'category_name' => $c->category_name);
    }, $lab_categories) : array()); ?>;
    
    var labTests = <?php echo json_encode(isset($lab_tests) ? array_map(function($t) {
        return array(
            'laboratory_id'   => isset($t->particular_id) ? (int)$t->particular_id : 0,
            'particular_name' => isset($t->particular_name) ? $t->particular_name : '',
            'test_code'       => '',
            'category_id'     => isset($t->category_id) ? (int)$t->category_id : 0,
            'is_nhis_covered' => isset($t->is_nhis_covered) ? (int)$t->is_nhis_covered : 0,
            'nhis_code'       => isset($t->nhis_code) ? $t->nhis_code : '',
            'nhis_price'      => isset($t->nhis_price) ? (float)$t->nhis_price : 0,
            'charge_amount'   => isset($t->charge_amount) ? (float)$t->charge_amount : 0,
            'specimen_type'   => '',
            'particular_id'   => isset($t->particular_id) ? (int)$t->particular_id : null,
        );
    }, $lab_tests) : array()); ?>;
    
    // DEBUG: Log data availability
    console.log('=== LAB DATA DEBUG ===');
    console.log('labCategories count:', labCategories.length);
    console.log('labCategories:', labCategories);
    console.log('labTests count:', labTests.length);
    console.log('labTests sample (first 3):', labTests.slice(0, 3));
    
    // Sonography items - GHS Standard Catalog
    var sonoItems = <?php echo json_encode(isset($sono_items) ? array_map(function($s) {
        return array(
            'item_id'         => $s->test_id, 
            'item_name'       => $s->test_name,
            'test_code'       => isset($s->test_code) ? $s->test_code : '',
            'category'        => isset($s->category) ? $s->category : '',
            'body_part'       => isset($s->body_part) ? $s->body_part : '',
            'price'           => isset($s->price) ? (float)$s->price : 0,
            'nhis_code'       => isset($s->nhis_code) ? $s->nhis_code : '',
            'nhis_price'      => isset($s->nhis_price) ? (float)$s->nhis_price : 0,
            'is_nhis_covered' => isset($s->is_nhis_covered) ? (int)$s->is_nhis_covered : 0,
            'preparation'     => isset($s->preparation) ? $s->preparation : '',
            'particular_id'   => isset($s->particular_id) ? $s->particular_id : null,
        );
    }, $sono_items) : array()); ?>;
    
    console.log('sonoItems count:', sonoItems.length);
    console.log('sonoItems sample (first 3):', sonoItems.slice(0, 3));
    
    var sonoCategories = <?php echo json_encode(isset($sono_categories) ? $sono_categories : array()); ?>;

    // Lab Tests Multi-Entry
    var labManager = new MultiEntryManager();
    labManager.init('<?php echo base_url(); ?>', {
        module: 'laboratory',
        containerId: 'lab-entry-container',
        countId: 'lab-entry-count',
        saveButtonId: 'btn-save-labs',
        onSaveSuccess: function(response) {
            if (response.redirect) window.location.href = response.redirect;
        }
    });

    var patientIsNhis = <?php echo (isset($patient_payer_type) && strtoupper(trim((string)$patient_payer_type)) === 'NHIS') ? 'true' : 'false'; ?>;

    var labTestsMap = {};
    for (var _i = 0; _i < labTests.length; _i++) {
        labTestsMap[labTests[_i].laboratory_id] = labTests[_i];
    }

    function refreshNhisBadges() {
        if (!patientIsNhis) return;
        $('#lab-entry-container .multi-entry-row').each(function() {
            var $row = $(this);
            var testId = $row.find('select[name*="laboratory_id"], select.test-select').val();
            $row.find('.nhis-lab-badge').remove();
            if (testId && labTestsMap[testId] && labTestsMap[testId].is_nhis_covered) {
                var price = labTestsMap[testId].nhis_price > 0 ? labTestsMap[testId].nhis_price : labTestsMap[testId].charge_amount;
                var priceStr = price > 0 ? ' (GHS ' + parseFloat(price).toFixed(2) + ')' : '';
                $row.find('select[name*="laboratory_id"], select.test-select').after(
                    '<span class="nhis-lab-badge label label-success" style="margin-left:6px;vertical-align:middle;"><i class="fa fa-shield"></i> NHIS' + priceStr + '</span>'
                );
            }
        });
    }

    $(document).on('change', '#lab-entry-container select[name*="laboratory_id"], #lab-entry-container select.test-select', function() {
        setTimeout(refreshNhisBadges, 50);
    });

    $('#btn-add-lab').on('click', function() {
        labManager.addEntry('laboratory', { categories: labCategories, tests: labTests });
        $('#lab-entry-count').text($('#lab-entry-container .multi-entry-row').length);
        $('#btn-save-labs').prop('disabled', false);
        setTimeout(refreshNhisBadges, 100);
    });

    $('#btn-save-labs').on('click', function() {
        labManager.saveAll('laboratory', opdNo, patientNo);
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
        setTimeout(refreshNhisBadges, 150);
    });

    // Sonography Multi-Entry
    var sonoManager = new MultiEntryManager();
    sonoManager.init('<?php echo base_url(); ?>', {
        module: 'sonography',
        containerId: 'sono-entry-container',
        countId: 'sono-entry-count',
        saveButtonId: 'btn-save-sonos',
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
        sonoManager.saveAll('sonography', opdNo, patientNo);
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

    // Radiology Multi-Entry
    var radiologyTests = <?php echo json_encode(isset($radiology_tests) ? array_map(function($t) {
        return array(
            'test_id' => $t->id,
            'test_name' => $t->test_name,
            'price' => isset($t->price) ? (float)$t->price : 0,
            'is_nhis_covered' => isset($t->is_nhis_covered) ? (int)$t->is_nhis_covered : 0
        );
    }, $radiology_tests) : array()); ?>;

    var radiologyManager = new MultiEntryManager();
    radiologyManager.init('<?php echo base_url(); ?>', {
        module: 'radiology',
        containerId: 'radiology-entry-container',
        countId: 'radiology-entry-count',
        saveButtonId: 'btn-save-radiology',
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
        radiologyManager.saveAll('radiology', opdNo, patientNo);
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
});
</script>

</body>

</html>