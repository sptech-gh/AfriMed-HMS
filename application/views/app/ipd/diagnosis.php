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
                                        <li class="active"><a href="<?php echo base_url() ?>app/ipd/diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/medication/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/complain/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/progress_note/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Progress Note</a></li>

                                        <li><a href="<?php echo base_url() ?>app/ipd/bed_side_procedure/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Bed Side Procedure</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/operation_theater/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Operation Theater</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/patientHistory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/laboratory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/discharge_summary/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <div class="nav-tabs-custom">
                            <ul class="nav nav-tabs">
                                <li class="active"><a href="#tab_1" data-toggle="tab">Diagnosis</a></li>

                            </ul>
                            <div class="tab-content">
                                <div class="tab-pane active" id="tab_1">
											<?php if (!$canEditClinical) { ?>
												<div class="alert alert-info">Read-only — Doctor access only</div>
											<?php } ?>
                                    <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                        <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                            <a href="#" class="btn btn-primary btn-lg" data-toggle="modal" data-target="#multiDiagnosisModal"><i class="fa fa-plus-circle"></i> Add Diagnoses</a>

                                    <?php }
                                    } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>Diagnosis</th>
                                                <th>Diagnosis (Others)</th>
                                                <th>Remarks</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patientDiagnosis as $diagnosisList4) { ?>
                                                <tr>
                                                    <td><?php echo $diagnosisList4->diagnosis_name ?></td>
                                                    <td><?php echo $diagnosisList4->diagnosis_text ?></td>
                                                    <td><?php echo $diagnosisList4->remarks ?></td>
                                                    <td>
                                                        <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                                            <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                                                <form method="post" action="<?php echo base_url() ?>app/ipd/delete_diagnos/<?php echo $diagnosisList4->iop_diag_id ?>/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove?');">
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




    <!-- Modal -->
    <form method="post" action="<?php echo base_url() ?>app/ipd/save_diagnosis" onSubmit="return confirm('Are you sure you want to save?');">
        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
        <input type="hidden" name="opd_no" value="<?php echo $getOPDPatient->IO_ID ?>">
        <input type="hidden" name="patient_no" value="<?php echo $getOPDPatient->patient_no ?>">
        <div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                        <h4 class="modal-title" id="myModalLabel">Diagnosis</h4>
                    </div>
                    <div class="modal-body">
                        <table class="table table-hover">
                            <tbody>
                                <tr>
                                    <td>Diagnosis</td>
                                    <td>
                                        <select name="diagnosis" id="diagnosis" style="width: 100%;" required class="form-control input-sm" onchange="otherOptions(this.value)">
                                            <option value="">- Diagnosis -</option>
                                            <option value="others">Others</option>
                                            <?php
                                            foreach ($diagnosisList as $diagnosisList2) {
                                                $icd = isset($diagnosisList2->icd_code) ? trim((string)$diagnosisList2->icd_code) : '';
                                                $cat = isset($diagnosisList2->category) ? trim((string)$diagnosisList2->category) : '';
                                                $label = ($icd !== '' ? '['.$icd.'] ' : '') . (string)$diagnosisList2->diagnosis_name;
                                                if ($cat !== '') $label .= ' ('.$cat.')';
                                            ?>
                                                <option value="<?php echo (int)$diagnosisList2->diagnosis_id; ?>">
                                                    <?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?>
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                </tr>
                                <tr id="diagnosis_txt" style="display: none;">
                                    <td></td>
                                    <td><input id="autouser" name="diagnosis_text" placeholder="type diagnosis here" class="form-control input-sm" style="width: 100%;" /></td>
                                </tr>
                                <tr>
                                    <td>Remarks</td>
                                    <td><textarea name="remarks" placeholder="Remarks" class="form-control input-sm" style="width: 100%;" rows="3"></textarea></td>
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

    <div class="modal fade multi-entry-modal" id="multiDiagnosisModal" tabindex="-1" role="dialog" aria-labelledby="multiDiagnosisModalLabel">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background:#3c8dbc;color:#fff;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color:#fff;opacity:0.8;"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title" id="multiDiagnosisModalLabel"><i class="fa fa-stethoscope"></i> Add Multiple Diagnoses</h4>
                </div>
                <div class="modal-body" style="background:#f5f7fa;">
                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                    <div class="multi-entry-summary">
                        <div>
                            <span class="count" id="entry-count">0</span>
                            <span class="label-text">diagnosis(es) to add</span>
                        </div>
                        <button type="button" class="btn btn-add-entry" id="btn-add-diagnosis">
                            <i class="fa fa-plus"></i> Add Diagnosis
                        </button>
                    </div>
                    <div id="multi-entry-container">
                        <div class="empty-state">
                            <i class="fa fa-plus-circle"></i>
                            <p>Click "Add Diagnosis" button to add items</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background:#f5f7fa;">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> Cancel</button>
                    <button type="button" class="btn btn-primary btn-save-all" id="btn-save-all" disabled>
                        <i class="fa fa-check-circle"></i> Save All Diagnoses
                    </button>
                </div>
            </div>
        </div>
    </div>







    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script src="<?php echo base_url(); ?>public/js/smart-medical-autocomplete.js"></script>
    <script src="<?php echo base_url(); ?>public/js/multi-entry-manager.js"></script>

    <!-- BDAY -->
    <!-- Do NOT load a second jQuery copy here; it breaks Bootstrap plugins and modal JS. -->
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

    <script>
        // SmartMedical powers diagnosis autocomplete for the multi-entry modal.
        if (typeof SmartMedical !== 'undefined') {
            SmartMedical.init('<?php echo base_url(); ?>');
        }
    </script>


    <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.3/js/select2.min.js"></script>
    <!-- jQuery UI -->
    <script src="<?php echo base_url(); ?>public/js/jquery-ui-1.10.3.min.js"></script>


    <script>
        function otherOptions(val) {
            if (val == 'others') {
                $('#diagnosis_txt').show();
            } else {
                $('#diagnosis_txt').hide();
            }
        }
    </script>


    <script type='text/javascript'>
        $(document).ready(function() {

            // Initialize 
            // Legacy custom-text autocomplete: align with OPD and the SmartMedical catalog.
            $("#autouser").autocomplete({
                source: function(request, response) {
                    // Fetch data
                    $.ajax({
                        url: "<?= base_url() ?>app/medical_data/search_diagnoses",
                        type: 'get',
                        dataType: "json",
                        data: {
                            term: request.term
                        },
                        success: function(data) {
                            // medical_data/search_diagnoses returns {id,label,icd_code,category,...}
                            response($.map(data || [], function(item) {
                                var desc = item.label || '';
                                if (item.icd_code) desc += ' [' + item.icd_code + ']';
                                return { label: desc, value: item.label || item.value || '', id: item.id || 0 };
                            }));
                        }
                    });
                },
                minLength: 2,
                select: function(event, ui) {
                    // Set selection
                    $('#autouser').val(ui.item.label); // display the selected text
                    //   $('#userid').val(ui.item.value); // save selected id to input
                    return false;
                }
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

    <script>
        $(document).ready(function() {
            var diagnosisList = <?php echo json_encode(array_map(function($d) {
                return array(
                    'diagnosis_id' => $d->diagnosis_id,
                    'diagnosis_name' => $d->diagnosis_name,
                    'icd_code' => isset($d->icd_code) ? $d->icd_code : '',
                    'category' => isset($d->category) ? $d->category : ''
                );
            }, $diagnosisList)); ?>;

            var ipdNo = '<?php echo url_safe_id($getOPDPatient->IO_ID); ?>';
            var patientNo = '<?php echo $getOPDPatient->patient_no; ?>';
            var diagManager = new MultiEntryManager();
            diagManager.init('<?php echo base_url(); ?>', {
                module: 'diagnosis',
                containerId: 'multi-entry-container',
                saveUrlPrefix: 'app/ipd/',
                onSaveSuccess: function(response) {
                    if (response.redirect) window.location.href = response.redirect;
                }
            });

            $('#btn-add-diagnosis').on('click', function() {
                diagManager.addEntry('diagnosis', { diagnosisList: diagnosisList });
            });
            $('#btn-save-all').on('click', function() {
                diagManager.saveAll('diagnosis', ipdNo, patientNo);
            });
            $('#multiDiagnosisModal').on('hidden.bs.modal', function() {
                diagManager.resetModal();
            });
            $('#multiDiagnosisModal').on('shown.bs.modal', function() {
                if ($('#multi-entry-container .multi-entry-row').length === 0) {
                    diagManager.addEntry('diagnosis', { diagnosisList: diagnosisList });
                }
            });
        });
    </script>


</body>

</html>
