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

                                        <li><a href="<?php echo base_url() ?>app/ipd/diagnosis/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Diagnosis</a></li>

                                        <li class="active"><a href="<?php echo base_url() ?>app/ipd/medication/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Medication</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/complain/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Complain</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/progress_note/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Progress Note</a></li>

                                        <li><a href="<?php echo base_url() ?>app/ipd/bed_side_procedure/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Bed Side Procedure</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/operation_theater/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Operation Theater</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/patientHistory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Patient History</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/laboratory/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Laboratory</a></li>
                                        <li><a href="<?php echo base_url() ?>app/ipd/discharge_summary/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Discharge Summary</a></li>
                                        <!--<li><a href="<?php echo base_url() ?>app/opd/billing/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>"> Billing</a></li>-->
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
                                            <a href="#" class="btn btn-primary" data-toggle="modal" data-target="#unifiedMedModal-ipd"><i class="fa fa-plus"></i> Add Medication</a>
                                    <?php }
                                    } ?>
                                    <a href="<?php echo base_url() ?>app/ipd_print/print_medication/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                                    <a href="<?php echo base_url() ?>app/ipd_print/pdf_medication/<?php echo $getOPDPatient->IO_ID; ?>/<?php echo $getOPDPatient->patient_no; ?>" class="btn btn-success" target="_blank"><i class="fa fa-print"></i> PDF</a>
                                    <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>Medicine Name</th>
                                                <th>Medicine Name (Others / Text)</th>
                                                <th>Instruction</th>
                                                <th>Advice</th>
                                                <th>Days</th>
                                                <th>Qty</th>
                                                <th>Prepared by</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($patientMedication as $rows) { ?>
                                                <tr>
                                                    <td><?php echo $rows->drug_name ?></td>
                                                    <td><?php echo $rows->medicine_text; ?></td>
                                                    <td><?php echo $rows->instruction ?></td>
                                                    <td><?php echo $rows->advice ?></td>
                                                    <td><?php echo $rows->days ?></td>
                                                    <td><?php echo $rows->total_qty ?></td>
                                                    <td><?php echo $rows->name ?></td>
                                                    <td>
                                                        <?php if ($this->session->userdata('emr_viewing') == "") { ?>
                                                            <?php if ($canEditClinical && $getOPDPatient->nStatus == "Pending") { ?>
                                                                <form method="post" action="<?php echo base_url() ?>app/ipd/delete_medication/<?php echo $rows->iop_med_id ?>/<?php echo url_safe_id($getOPDPatient->IO_ID) ?>/<?php echo $getOPDPatient->patient_no ?>" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove?');">
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
                                    </div>

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
    <script src="<?php echo base_url(); ?>public/datepicker/js/jquery-1.9.1.min.js"></script>
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

    <!-- Unified Medication Modal — Phase 3 -->
    <script src="<?php echo base_url(); ?>public/js/medication-modal.js"></script>
    <?php
        $mm_module      = 'ipd';
        // Use URL-safe ID for consistency across OPD/IPD and to match save_*_batch decoding.
        $mm_opd_no      = url_safe_id($getOPDPatient->IO_ID);
        $mm_patient_no  = $getOPDPatient->patient_no;
        $mm_save_url    = base_url() . 'app/ipd/save_medication_batch';
        $mm_is_nhis     = (isset($patientInfo->nhis_status) && strtoupper($patientInfo->nhis_status) === 'ACTIVE');
        $mm_diagnosis_code = '';
        $mm_diagnosis_text = '';
        require_once(APPPATH . 'views/app/components/medication_modal.php');
    ?>







</body>

</html>
