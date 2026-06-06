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

                                        <li class="active"><a href="<?php echo base_url() ?>app/opd/medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/complain/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/vitalSign/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Vital Sign</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/patientHistory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>

                                        <li><a href="<?php echo base_url() ?>app/opd/laboratory/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/procedures/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Procedures</a></li>
                                        <li><a href="<?php echo base_url() ?>app/opd/discharge_summary/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                        <?php require_once(APPPATH.'views/app/opd/_detain_admit_menu.php'); ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Medication</a></li>

                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">

                                    <?php echo $message; ?>
											<?php if (!$canEditClinical) { ?>
												<div class="alert alert-info">Read-only — Doctor access only</div>
											<?php } ?>
                                    <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                        <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                             <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#unifiedMedModal-opd"><i class="fa fa-plus"></i> Add Medication</a>
                                    <?php }
                                    } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_medication/<?php echo url_safe_id($getOPDPatient->IO_ID); ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>Medicine Name</th>
                                                <th>Medicine Name (Others / Text)</th>
                                                <th>NHIS</th>
                                                <th>Frequency</th>
                                                <th>Instruction</th>
                                                <th>Days</th>
                                                <th>Qty</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patientMedication as $rows) {
                                                $dispStatus = isset($rows->dispensing_status) ? strtoupper(trim((string)$rows->dispensing_status)) : 'PENDING';
                                                $statusBadge = '<span class="label label-default">PENDING</span>';
                                                if ($dispStatus === 'DISPENSED') $statusBadge = '<span class="label label-success">DISPENSED</span>';
                                                elseif ($dispStatus === 'PARTIAL') $statusBadge = '<span class="label label-warning">PARTIAL</span>';
                                                elseif ($dispStatus === 'RESERVED') $statusBadge = '<span class="label label-info">RESERVED</span>';
                                                elseif ($dispStatus === 'UNAVAILABLE') $statusBadge = '<span class="label label-danger">UNAVAILABLE</span>';
                                                
                                                // NHIS Coverage Badge
                                                $nhisCode = isset($rows->nhis_drug_code) ? trim((string)$rows->nhis_drug_code) : '';
                                                $nhisBadge = (!empty($nhisCode)) 
                                                    ? '<span class="label label-success" title="NHIS Mapped: ' . htmlspecialchars($nhisCode) . '"><i class="fa fa-check-circle"></i> NHIS</span>'
                                                    : '<span class="label label-danger" title="Not mapped to NHIS"><i class="fa fa-times-circle"></i> No NHIS</span>';
                                            ?>
                                                <tr>
                                                    <td><?php echo $rows->drug_name ?></td>
                                                    <td><?php echo $rows->medicine_text; ?></td>
                                                    <td><?php echo $nhisBadge; ?></td>
                                                    <td><?php echo isset($rows->frequency) ? htmlspecialchars((string)$rows->frequency) : ''; ?></td>
                                                    <td><?php echo $rows->instruction ?></td>
                                                    <td><?php echo $rows->days ?></td>
                                                    <td><?php echo $rows->total_qty ?></td>
                                                    <td><?php echo $statusBadge; ?></td>
                                                    <td>
                                                        <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                                            <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                                                <form method="post" action="<?php echo base_url() ?>app/opd/delete_medication/<?php echo $rows->iop_med_id ?>/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove?');">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <button type="submit" class="btn btn-xs btn-danger">Remove</button>
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

    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    <!-- jQuery UI -->
    <script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>

    <!-- BDAY -->
    <script src="<?php echo base_url(); ?>public/datepicker/js/bootstrap-datepicker.js"></script>
    <script type="text/javascript">
        // When the document is ready
        $(document).ready(function() {

            $('#cFrom').datepicker({
                //format: "dd/mm/yyyy"
                format: "yyyy-mm-dd"
            });

            $('#cTo').datepicker({
                //format: "dd/mm/yyyy"
                format: "yyyy-mm-dd"
            });

        });
    </script>
    <!-- END BDAY -->

    <?php require_once(APPPATH.'views/app/opd/_detain_admit_modals.php'); ?>

    <!-- Legacy single-entry modal retired: all prescriptions now go through #multiMedicationModal -->
    <!-- Retained hidden form placeholder for potential future single-entry integration -->
    <div style="display:none" id="legacy-modal-placeholder">
        <input type="hidden" name="opd_no" value="<?php echo url_safe_id($getOPDPatient->IO_ID) ?>">
        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no ?>">
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="myModalLabel">Medication</h4>
                    </div>

                    <script language="javascript">
                        function showDrugName(category_id) {
                            if (window.XMLHttpRequest) {
                                xmlhttp = new XMLHttpRequest();
                            } else { // code for IE6, IE5
                                xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
                            }
                            xmlhttp.onreadystatechange = function() {
                                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                                    console.log(xmlhttp.responseText);
                                    document.getElementById("showCategories").innerHTML = xmlhttp.responseText;
                                }
                            }
                            var supp;

                            xmlhttp.open("GET", "<?php echo base_url(); ?>app/opd/getDrugName/" + category_id, true);
                            xmlhttp.send();

                        }
                    </script>
                    <div class="modal-body">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <td>Drug Name</td>
                                    <td>
                                        <input type="text" id="drug_search_input" name="medicine_text" placeholder="Type to search or enter drug name..." class="form-control input-sm" style="width: 100%;" autocomplete="off" required>
                                        <input type="hidden" name="drug_name" id="drug_name" value="">
                                        <input type="hidden" name="category" id="category" value="others">
                                        <small class="text-muted"><i class="fa fa-info-circle"></i> Search existing drugs or type a new name</small>
                                        <div id="drug_search_info" class="smart-search-info" style="display:none; margin-top:4px;"></div>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Days</td>
                                    <td><input type="text" name="nDays" placeholder="Days" class="form-control input-sm" style="width: 250px;" required></td>
                                </tr>
                                <tr>
                                    <td>Qty</td>
                                    <td><input type="text" name="qty" placeholder="Qty" class="form-control input-sm" style="width: 250px;"></td>
                                </tr>
                                <tr>
                                    <td>Dosage</td>
                                    <td><input type="text" name="dosage" placeholder="Dosage" class="form-control input-sm" style="width: 250px;"></td>
                                </tr>
                                <tr>
                                    <td>Frequency</td>
                                    <td>
                                        <select name="frequency" class="form-control input-sm" style="width: 250px;">
                                            <option value="">- Select Frequency -</option>
                                            <option value="OD (Once daily)">OD (Once daily)</option>
                                            <option value="BD (Twice daily)">BD (Twice daily)</option>
                                            <option value="TDS (Three times daily)">TDS (Three times daily)</option>
                                            <option value="QDS (Four times daily)">QDS (Four times daily)</option>
                                            <option value="Stat (Immediately)">Stat (Immediately)</option>
                                            <option value="PRN (As needed)">PRN (As needed)</option>
                                            <option value="Nocte (At night)">Nocte (At night)</option>
                                            <option value="Mane (Morning)">Mane (Morning)</option>
                                            <option value="Q4H (Every 4 hours)">Q4H (Every 4 hours)</option>
                                            <option value="Q6H (Every 6 hours)">Q6H (Every 6 hours)</option>
                                            <option value="Q8H (Every 8 hours)">Q8H (Every 8 hours)</option>
                                            <option value="Q12H (Every 12 hours)">Q12H (Every 12 hours)</option>
                                            <option value="Weekly">Weekly</option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Instruction</td>
                                    <td><textarea name="instruction" placeholder="Instruction" class="form-control input-sm" style="width: 250px;"></textarea></td>
                                </tr>
                                <tr>
                                    <td>Diagnosis <span class="text-danger" id="diagnosis_required_star" style="display:none;">*</span></td>
                                    <td>
                                        <input type="text" id="diagnosis_search" name="diagnosis_search" placeholder="Search ICD-10 code or description..." class="form-control input-sm" style="width: 100%;" autocomplete="off">
                                        <input type="hidden" name="diagnosis_code" id="diagnosis_code" value="">
                                        <input type="hidden" name="diagnosis_description" id="diagnosis_description" value="">
                                        <div id="diagnosis_display" style="margin-top: 5px;"></div>
                                        <small class="text-muted"><i class="fa fa-info-circle"></i> Required for NHIS patients</small>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Advice</td>
                                    <td><textarea name="advice" placeholder="Advice" class="form-control input-sm" style="width: 250px;"></textarea></td>
                                </tr>
                            </tbody>
                        </table>
                        
                        <!-- Phase 3.1: Enhanced Clinical Safety Alerts Container -->
                        <div id="prescription_safety_container" style="display:none; margin-top:15px;">
                            <!-- Pediatric Weight Alert -->
                            <div class="alert alert-info" id="pediatric_weight_alert" style="display:none;">
                                <i class="fa fa-child"></i> <strong>Pediatric Patient:</strong> Weight required for safe dosing.
                                <div class="input-group input-group-sm" style="width:200px; margin-top:8px;">
                                    <input type="number" id="patient_weight_input" class="form-control" placeholder="Weight (kg)" step="0.1" min="0.5" max="150">
                                    <span class="input-group-btn">
                                        <button type="button" class="btn btn-info" id="btn_save_weight"><i class="fa fa-save"></i> Save</button>
                                    </span>
                                </div>
                            </div>
                            <!-- Safe Dose Range Display -->
                            <div class="alert alert-success" id="safe_dose_range" style="display:none;">
                                <i class="fa fa-check-circle"></i> <strong>Safe Pediatric Dose Range:</strong> <span id="safe_range_text"></span>
                            </div>
                            <!-- Black Box Warning -->
                            <div class="panel panel-default" id="black_box_panel" style="display:none; border-color:#000;">
                                <div class="panel-heading" style="background:#000; color:#fff;">
                                    <h4 class="panel-title"><i class="fa fa-warning"></i> BLACK BOX WARNING</h4>
                                </div>
                                <div class="panel-body" id="black_box_content" style="background:#fff3cd;"></div>
                                <div class="panel-footer">
                                    <label><input type="checkbox" id="black_box_ack"> I acknowledge this warning and accept responsibility</label>
                                </div>
                            </div>
                            <!-- Warning Alerts -->
                            <div class="panel panel-warning" id="safety_warning_panel" style="display:none;">
                                <div class="panel-heading">
                                    <h4 class="panel-title"><i class="fa fa-exclamation-triangle"></i> Prescription Safety Alerts</h4>
                                </div>
                                <div class="panel-body" id="safety_alerts_list"></div>
                            </div>
                            <!-- Blocked Alerts -->
                            <div class="panel panel-danger" id="safety_blocked_panel" style="display:none;">
                                <div class="panel-heading">
                                    <h4 class="panel-title"><i class="fa fa-ban"></i> Prescription Blocked - Patient Safety Risk</h4>
                                </div>
                                <div class="panel-body" id="safety_blocked_list"></div>
                                <div class="panel-footer">
                                    <strong>This prescription cannot be saved.</strong>
                                    <div id="override_section" style="display:none; margin-top:10px;">
                                        <p>Supervisor override available:</p>
                                        <textarea id="override_reason" class="form-control" rows="2" placeholder="Enter clinical justification for override..."></textarea>
                                        <button type="button" class="btn btn-warning btn-sm" id="btn_request_override" style="margin-top:5px;"><i class="fa fa-unlock"></i> Request Override</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- End Safety Alerts Container -->
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary" id="btn_save_medication">Save</button>
                        <span id="safety_check_status" style="display:none; margin-left:10px;"><i class="fa fa-spinner fa-spin"></i> Checking safety...</span>
                    </div>

                </div>
                <!-- /.modal-content -->
            </div>
            <!-- /.modal-dialog -->
        </div>
    </div><!-- #legacy-modal-placeholder -->












    <!-- Smart Medical Autocomplete -->
    <script src="<?php echo base_url(); ?>public/js/smart-medical-autocomplete.js"></script>
    <script type='text/javascript'>
        $(document).ready(function() {
            SmartMedical.init('<?= base_url() ?>');
            SmartMedical.injectStyles();

            // Unified drug search with autocomplete
            SmartMedical.initMedicationAutocomplete(
                '#drug_search_input',
                '#drug_name',
                '#drug_search_info',
                {
                    minLength: 2,
                    allowCustom: true,
                    onSelect: function(item) {
                        if (item && item.id && item.id !== 0) {
                            $('#drug_name').val(item.id);
                            $('#category').val(item.category_id || 'others');
                        } else {
                            $('#drug_name').val('');
                            $('#category').val('others');
                        }
                    }
                }
            );

            // Diagnosis ICD-10 autocomplete
            var diagnosisTimeout = null;
            $('#diagnosis_search').on('input', function() {
                var term = $(this).val();
                clearTimeout(diagnosisTimeout);
                
                if (term.length < 2) {
                    $('#diagnosis_display').html('');
                    return;
                }
                
                diagnosisTimeout = setTimeout(function() {
                    $.ajax({
                        url: '<?= base_url() ?>app/opd/search_diagnosis_json',
                        type: 'GET',
                        data: { term: term },
                        dataType: 'json',
                        success: function(data) {
                            if (data && data.length > 0) {
                                var html = '<div class="list-group" style="position:absolute; z-index:1000; max-height:200px; overflow-y:auto; width:100%; background:#fff; border:1px solid #ddd; border-radius:4px;">';
                                $.each(data, function(i, item) {
                                    html += '<a href="#" class="list-group-item diagnosis-item" data-code="' + item.code + '" data-desc="' + item.description + '">';
                                    html += '<strong>' + item.code + '</strong> - ' + item.description;
                                    if (item.category) {
                                        html += ' <span class="label label-default">' + item.category + '</span>';
                                    }
                                    html += '</a>';
                                });
                                html += '</div>';
                                $('#diagnosis_display').html(html);
                            } else {
                                $('#diagnosis_display').html('<small class="text-muted">No matching ICD-10 codes found</small>');
                            }
                        }
                    });
                }, 300);
            });

            // Select diagnosis from dropdown
            $(document).on('click', '.diagnosis-item', function(e) {
                e.preventDefault();
                var code = $(this).data('code');
                var desc = $(this).data('desc');
                $('#diagnosis_code').val(code);
                $('#diagnosis_description').val(desc);
                $('#diagnosis_search').val(code + ' - ' + desc);
                $('#diagnosis_display').html('<span class="label label-success"><i class="fa fa-check"></i> ' + code + '</span> ' + desc);
            });

            // Check if NHIS patient - show required indicator
            <?php 
            $isNhis = false;
            if (isset($patientInfo->nhis_status) && strtoupper($patientInfo->nhis_status) == 'ACTIVE') {
                $isNhis = true;
            }
            ?>
            <?php if ($isNhis): ?>
            $('#diagnosis_required_star').show();
            <?php endif; ?>

            // Phase 3.1: Enhanced Clinical Safety Checking
            var safetyCheckTimeout = null;
            var prescriptionBlocked = false;
            var requiresSupervisor = false;
            var requiresAcknowledgement = false;
            var blackBoxAcknowledged = false;
            var currentAlerts = [];
            var patientNo = '<?= htmlspecialchars($getOPDPatient->patient_no) ?>';
            var iopId = '<?= htmlspecialchars($getOPDPatient->IO_ID) ?>';
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

            function resetSafetyUI() {
                $('#prescription_safety_container').hide();
                $('#safety_warning_panel').hide();
                $('#safety_blocked_panel').hide();
                prescriptionBlocked = false;
                $('#btn_save_medication').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary');
            }

            function updateSaveButtonState() {
                if (prescriptionBlocked) {
                    $('#btn_save_medication').prop('disabled', true);
                } else if (requiresAcknowledgement && !blackBoxAcknowledged) {
                    $('#btn_save_medication').prop('disabled', true);
                } else {
                    $('#btn_save_medication').prop('disabled', false);
                }
            }

            // Black box acknowledgement
            $('#black_box_ack').on('change', function() {
                blackBoxAcknowledged = $(this).is(':checked');
                updateSaveButtonState();
            });

            function checkPrescriptionSafety() {
                var drugId = $('#drug_name').val();
                var dose = $('input[name="dosage"]').val();
                var frequency = $('select[name="frequency"]').val();

                resetSafetyUI();

                if (!drugId || drugId === '' || drugId === '0') {
                    return;
                }

                $('#safety_check_status').show();

                // Use Phase 3.1 enhanced safety check
                $.ajax({
                    url: '<?= base_url() ?>app/opd/check_phase31_safety_ajax',
                    type: 'POST',
                    data: {
                        drug_id: drugId,
                        patient_no: patientNo,
                        iop_id: iopId,
                        dose: dose,
                        frequency: frequency
                    },
                    dataType: 'json',
                    success: function(response) {
                        $('#safety_check_status').hide();

                        if (!response.success || response.alert_count === 0) {
                            return;
                        }

                        currentAlerts = response.alerts;
                        $('#prescription_safety_container').show();

                        // Process alerts by type
                        var blockedAlerts = [];
                        var warningAlerts = [];
                        var blackBoxAlert = null;
                        var pediatricWeightNeeded = false;
                        var safeRange = null;

                        $.each(response.alerts, function(i, alert) {
                            // Check for pediatric weight requirement
                            if (alert.type === 'PEDIATRIC_DOSE' && alert.requires_weight) {
                                pediatricWeightNeeded = true;
                            }
                            // Check for safe range info
                            if (alert.safe_range) {
                                safeRange = alert.safe_range;
                            }
                            // Black box warning
                            if (alert.type === 'BLACK_BOX') {
                                blackBoxAlert = alert;
                            }
                            // Categorize by severity
                            if (alert.severity === 'BLOCKED') {
                                blockedAlerts.push(alert);
                            } else if (alert.type !== 'BLACK_BOX') {
                                warningAlerts.push(alert);
                            }
                        });

                        // Show pediatric weight input if needed
                        if (pediatricWeightNeeded) {
                            $('#pediatric_weight_alert').show();
                        }

                        // Show safe dose range if available
                        if (safeRange && safeRange.min && safeRange.max) {
                            $('#safe_range_text').text(safeRange.min + safeRange.unit + ' - ' + safeRange.max + safeRange.unit);
                            $('#safe_dose_range').show();
                        }

                        // Show black box warning
                        if (blackBoxAlert) {
                            requiresAcknowledgement = true;
                            $('#black_box_content').html('<p><strong>' + blackBoxAlert.message + '</strong></p>');
                            $('#black_box_panel').show();
                        }

                        // Show blocked alerts
                        if (blockedAlerts.length > 0 || response.is_blocked) {
                            prescriptionBlocked = true;
                            requiresSupervisor = response.requires_supervisor || false;
                            $('#btn_save_medication').prop('disabled', true).removeClass('btn-primary').addClass('btn-danger');
                            
                            var blockedHtml = '<ul class="list-unstyled">';
                            $.each(blockedAlerts, function(i, alert) {
                                var icon = alert.type === 'ALLERGY_BLOCK' ? 'fa-allergies' : 'fa-ban';
                                blockedHtml += '<li style="margin-bottom:8px;"><i class="fa ' + icon + ' text-danger"></i> <strong>' + alert.type + ':</strong> ' + alert.message + '</li>';
                            });
                            blockedHtml += '</ul>';
                            $('#safety_blocked_list').html(blockedHtml);
                            $('#safety_blocked_panel').show();

                            // Show override section if supervisor override is possible
                            if (requiresSupervisor) {
                                $('#override_section').show();
                            }
                        }

                        // Show warning alerts
                        if (warningAlerts.length > 0) {
                            var warningHtml = '<ul class="list-unstyled">';
                            $.each(warningAlerts, function(i, alert) {
                                var iconClass = 'fa-info-circle text-info';
                                if (alert.severity === 'CRITICAL') {
                                    iconClass = 'fa-exclamation-triangle text-danger';
                                } else if (alert.severity === 'WARNING') {
                                    iconClass = 'fa-warning text-warning';
                                }
                                warningHtml += '<li style="margin-bottom:8px;"><i class="fa ' + iconClass + '"></i> <strong>' + alert.type + ':</strong> ' + alert.message;
                                if (alert.recommendation) {
                                    warningHtml += '<br><small class="text-muted"><i class="fa fa-lightbulb-o"></i> ' + alert.recommendation + '</small>';
                                }
                                warningHtml += '</li>';
                            });
                            warningHtml += '</ul>';
                            $('#safety_alerts_list').html(warningHtml);
                            $('#safety_warning_panel').show();
                        }
                    },
                    error: function() {
                        $('#safety_check_status').hide();
                    }
                });
            }

            // Save patient weight
            $('#btn_save_weight').on('click', function() {
                var weight = parseFloat($('#patient_weight_input').val());
                if (isNaN(weight) || weight <= 0) {
                    alert('Please enter a valid weight');
                    return;
                }
                var weightData = {
                    patient_no: patientNo,
                    weight_kg: weight
                };
                weightData[csrfName] = csrfHash;
                $.post('<?= base_url() ?>app/opd/update_patient_weight_ajax', weightData, function(response) {
                    if (response.success) {
                        $('#pediatric_weight_alert').html('<i class="fa fa-check text-success"></i> Weight saved: ' + weight + ' kg. Re-checking safety...');
                        setTimeout(checkPrescriptionSafety, 500);
                    } else {
                        alert('Failed to save weight: ' + response.message);
                    }
                }, 'json');
            });

            // Override request
            $('#btn_request_override').on('click', function() {
                var reason = $('#override_reason').val().trim();
                if (reason.length < 10) {
                    alert('Please provide a detailed clinical justification (at least 10 characters)');
                    return;
                }
                var drugName = $('#drug_search_input').val();
                var overrideData = {
                    patient_no: patientNo,
                    iop_id: iopId,
                    drug_id: $('#drug_name').val(),
                    drug_name: drugName,
                    alert_type: currentAlerts.length > 0 ? currentAlerts[0].type : 'OTHER',
                    severity: 'BLOCKED',
                    alert_message: currentAlerts.length > 0 ? currentAlerts[0].message : '',
                    override_reason: reason
                };
                overrideData[csrfName] = csrfHash;
                $.post('<?= base_url() ?>app/opd/log_safety_override_ajax', overrideData, function(response) {
                    if (response.success) {
                        prescriptionBlocked = false;
                        $('#btn_save_medication').prop('disabled', false).removeClass('btn-danger').addClass('btn-warning');
                        $('#override_section').html('<div class="alert alert-warning"><i class="fa fa-unlock"></i> Override granted. Proceed with caution.</div>');
                    } else {
                        alert('Override request failed: ' + response.message);
                    }
                }, 'json');
            });

            // Trigger safety check when drug is selected
            $('#drug_name').on('change', function() {
                clearTimeout(safetyCheckTimeout);
                safetyCheckTimeout = setTimeout(checkPrescriptionSafety, 500);
            });

            // Also check when dose, frequency, or duration changes
            $('input[name="dosage"], select[name="frequency"], input[name="nDays"]').on('change', function() {
                if ($('#drug_name').val()) {
                    clearTimeout(safetyCheckTimeout);
                    safetyCheckTimeout = setTimeout(checkPrescriptionSafety, 500);
                }
            });

            // Prevent form submission if blocked
            $('form').on('submit', function(e) {
                if (prescriptionBlocked) {
                    e.preventDefault();
                    alert('This prescription is blocked due to patient safety concerns. Please select a different medication or consult with a senior physician.');
                    return false;
                }
                return confirm('Are you sure you want to save?');
            });

            // Reset safety alerts when modal is closed
            $('#myModal').on('hidden.bs.modal', function() {
                $('#prescription_safety_container').hide();
                $('#safety_warning_panel').hide();
                $('#safety_blocked_panel').hide();
                prescriptionBlocked = false;
                $('#btn_save_medication').prop('disabled', false).removeClass('btn-danger').addClass('btn-primary');
            });
        });
    </script>

    <script>
        // $(document).ready(function() {
        //     $('select').select2({
        //         // closeOnSelect: false
        //         allowClear: true,
        //     });
        // });
    </script>

<?php if (false): // Legacy multi-entry modal disabled (use unified Phase-3 modal below). ?>
<!-- Multi-Entry Medication Modal (DISABLED) -->
<div class="modal fade multi-entry-modal" id="multiMedicationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" style="width:95%;max-width:1100px;" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: #fff; border-radius: 4px 4px 0 0;">
                <button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:0.8;"><span>&times;</span></button>
                <h4 class="modal-title"><i class="fa fa-medkit"></i> Add Multiple Medications</h4>
            </div>
            <div class="modal-body" style="background: #f5f7fa;">
                <?php if (isset($this->security)): ?>
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <?php endif; ?>
                <div class="multi-entry-summary">
                    <div>
                        <span class="count" id="med-entry-count">0</span>
                        <span class="label-text">medication(s) to prescribe</span>
                    </div>
                    <button type="button" class="btn btn-add-entry" id="btn-add-med">
                        <i class="fa fa-plus"></i> Add Medication
                    </button>
                </div>
                <div id="med-entry-container">
                    <div class="empty-state">
                        <i class="fa fa-plus-circle"></i>
                        <p>Click "Add Medication" button to add items</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f5f7fa;">
                <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                <button type="button" class="btn btn-primary btn-save-all" id="btn-save-meds" disabled>
                    <i class="fa fa-check-circle"></i> Save All Medications
                </button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/medication-modal.js"></script>
<script src="<?php echo base_url(); ?>public/js/multi-entry-manager.js"></script>
<script>
$(document).ready(function() {
    // Initialize MedicationModal for Phase 3 features (freq/route/form/unit masters)
    if (typeof MedicationModal !== 'undefined') {
        MedicationModal.init('<?php echo base_url(); ?>');
    }
    var opdNo = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
    var patientNo = '<?php echo $getOPDPatient->patient_no; ?>';

    // Drug categories
    var drugCategories = <?php echo json_encode(isset($drug_categories) ? array_map(function($c) {
        return array('drug_cat_id' => isset($c->cat_id) ? $c->cat_id : (isset($c->drug_cat_id) ? $c->drug_cat_id : ''), 'drug_category' => isset($c->med_category_name) ? $c->med_category_name : (isset($c->drug_category) ? $c->drug_category : ''));
    }, $drug_categories) : array()); ?>;

    // Medication Multi-Entry
    var medManager = new MultiEntryManager();
    medManager.init('<?php echo base_url(); ?>', {
        module: 'medication',
        containerId: 'med-entry-container',
        onSaveSuccess: function(response) {
            if (response.blocked) {
                var details = '';
                if (response.details && response.details.length) {
                    $.each(response.details, function(i, d) {
                        details += '<li><strong>' + d.type + ':</strong> ' + d.message + '</li>';
                    });
                }
                var html = '<div class="alert alert-danger alert-dismissable"><i class="fa fa-ban"></i><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>Prescription Blocked — Patient Safety Risk:</strong><ul>' + details + '</ul>Cannot save. Please review and consult a senior physician.</div>';
                $('#multiMedicationModal .modal-body').prepend(html);
                return;
            }
            if (response.nhis_block) {
                var html = '<div class="alert alert-danger alert-dismissable"><i class="fa fa-ban"></i><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>Payment Required:</strong> ' + (response.message || '') + '</div>';
                $('#multiMedicationModal .modal-body').prepend(html);
                return;
            }
            if (response.warnings && response.warnings.length) {
                var warnHtml = '<div class="alert alert-warning alert-dismissable"><i class="fa fa-exclamation-triangle"></i><button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button><strong>Saved with alerts:</strong><ul>';
                $.each(response.warnings, function(i, w) { warnHtml += '<li>' + w + '</li>'; });
                warnHtml += '</ul></div>';
                $('body').append(warnHtml);
            }
            if (response.redirect) window.location.href = response.redirect;
        }
    });

    $('#btn-add-med').on('click', function() {
        medManager.addEntry('medication', { categories: drugCategories });
        $('#med-entry-count').text($('#med-entry-container .multi-entry-row').length);
        $('#btn-save-meds').prop('disabled', false);
    });

    $('#btn-save-meds').on('click', function() {
        medManager.saveAll('medication', opdNo, patientNo);
    });

    $('#multiMedicationModal').on('hidden.bs.modal', function() {
        medManager.resetModal();
        $('#med-entry-count').text('0');
        $('#btn-save-meds').prop('disabled', true);
    });

    $('#multiMedicationModal').on('shown.bs.modal', function() {
        if ($('#med-entry-container .multi-entry-row').length === 0) {
            medManager.addEntry('medication', { categories: drugCategories });
            $('#med-entry-count').text('1');
            $('#btn-save-meds').prop('disabled', false);
        }
    });
});
</script>
<?php endif; ?>

<!-- Unified Medication Modal — Phase 3 (single modal + single API contract for OPD/IPD) -->
<script src="<?php echo base_url(); ?>public/js/medication-modal.js"></script>
<?php
    $mm_module      = 'opd';
    $mm_opd_no      = url_safe_id($getOPDPatient->IO_ID);
    $mm_patient_no  = $getOPDPatient->patient_no;
    $mm_save_url    = base_url() . 'app/opd/save_medication_batch';
    $mm_is_nhis     = (isset($patientInfo->nhis_status) && strtoupper($patientInfo->nhis_status) === 'ACTIVE');
    $mm_diagnosis_code = '';
    $mm_diagnosis_text = '';
    require_once(APPPATH . 'views/app/components/medication_modal.php');
?>

</body>

</html>
