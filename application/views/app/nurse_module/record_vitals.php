<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center - Record Vitals</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>Record Vitals <small>OPD Patient</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <?php
                    $baseSegments = explode('/', trim(parse_url(current_url(), PHP_URL_PATH), '/'));
                    $controller = isset($baseSegments[2]) ? $baseSegments[2] : 'nurse_module';
                    $vitalsBase = base_url() . 'app/' . $controller;
                    ?>
                    <li><a href="<?php echo $vitalsBase; ?>/vitals_queue">Vitals Queue</a></li>
                    <li class="active">Record Vitals</li>
                </ol>
            </section>

            <section class="content">
                <?php if (isset($message) && $message != '') { echo $message; } ?>

                <div class="row">
                    <!-- Patient Info Panel -->
                    <div class="col-md-4">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-user"></i> Patient Information</h3>
                            </div>
                            <div class="box-body">
                                <?php if (isset($patientInfo) && $patientInfo) { ?>
                                <table class="table table-condensed">
                                    <tr>
                                        <td><strong>Patient No</strong></td>
                                        <td><?php echo htmlspecialchars($patientInfo->patient_no); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Name</strong></td>
                                        <td><?php echo htmlspecialchars((isset($patientInfo->title) ? $patientInfo->title.' ' : '').
                                            $patientInfo->firstname.' '.$patientInfo->lastname); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Age</strong></td>
                                        <td><?php echo htmlspecialchars(isset($patientInfo->age) ? $patientInfo->age : ''); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Gender</strong></td>
                                        <td><?php echo htmlspecialchars(isset($patientInfo->gender) ? $patientInfo->gender : ''); ?></td>
                                    </tr>
                                </table>
                                <?php } ?>

                                <?php if (isset($visit) && $visit) { ?>
                                <hr style="margin: 8px 0;">
                                <table class="table table-condensed">
                                    <tr>
                                        <td><strong>OPD No</strong></td>
                                        <td><?php echo htmlspecialchars($visit->IO_ID); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Visit Date</strong></td>
                                        <td><?php echo htmlspecialchars($visit->date_visit); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Visit Time</strong></td>
                                        <td><?php echo htmlspecialchars($visit->time_visit); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Vitals Status</strong></td>
                                        <td>
                                            <?php if (isset($visit->vitals_status) && $visit->vitals_status === 'DONE') { ?>
                                                <span class="label label-success"><i class="fa fa-check"></i> Done</span>
                                            <?php } else { ?>
                                                <span class="label label-warning"><i class="fa fa-clock-o"></i> Pending</span>
                                            <?php } ?>
                                        </td>
                                    </tr>
                                </table>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Vitals Form -->
                    <div class="col-md-8">
                        <div class="box box-success">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-heartbeat"></i> Vital Signs</h3>
                            </div>
                            <form method="post" action="<?php echo $vitalsBase; ?>/save_opd_vitals" id="vitalsForm">
                                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($visit->IO_ID); ?>">
                                <input type="hidden" name="patient_no" value="<?php echo htmlspecialchars($visit->patient_no); ?>">

                                <div class="box-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="bp"><i class="fa fa-tachometer"></i> Blood Pressure (mmHg) <span class="text-red">*</span></label>
                                                <input type="text" class="form-control" id="bp" name="bp" placeholder="e.g. 120/80"
                                                    value="<?php echo htmlspecialchars(isset($visit->bp) && $visit->vitals_status === 'DONE' ? $visit->bp : ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="temperature"><i class="fa fa-thermometer-half"></i> Temperature (&deg;C) <span class="text-red">*</span></label>
                                                <input type="text" class="form-control" id="temperature" name="temperature" placeholder="e.g. 36.5"
                                                    value="<?php echo htmlspecialchars(isset($visit->temperature) && $visit->vitals_status === 'DONE' ? $visit->temperature : ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="pulse_rate"><i class="fa fa-heart"></i> Pulse Rate (bpm) <span class="text-red">*</span></label>
                                                <input type="text" class="form-control" id="pulse_rate" name="pulse_rate" placeholder="e.g. 72"
                                                    value="<?php echo htmlspecialchars(isset($visit->pulse_rate) && $visit->vitals_status === 'DONE' ? $visit->pulse_rate : ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="weight"><i class="fa fa-balance-scale"></i> Weight (kg) <span class="text-red">*</span></label>
                                                <input type="text" class="form-control" id="weight" name="weight" placeholder="e.g. 70"
                                                    value="<?php echo htmlspecialchars(isset($visit->weight) && $visit->vitals_status === 'DONE' ? $visit->weight : ''); ?>">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="height"><i class="fa fa-arrows-v"></i> Height (cm)</label>
                                                <input type="text" class="form-control" id="height" name="height" placeholder="e.g. 170"
                                                    value="<?php echo htmlspecialchars(isset($visit->height) && $visit->vitals_status === 'DONE' ? $visit->height : ''); ?>">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="respiration"><i class="fa fa-lungs"></i> Respiration (breaths/min)</label>
                                                <input type="text" class="form-control" id="respiration" name="respiration" placeholder="e.g. 18"
                                                    value="<?php echo htmlspecialchars(isset($visit->respiration) && $visit->vitals_status === 'DONE' ? $visit->respiration : ''); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="box-footer">
                                    <a href="<?php echo $vitalsBase; ?>/vitals_queue" class="btn btn-default">
                                        <i class="fa fa-arrow-left"></i> Back to Queue
                                    </a>
                                    <button type="submit" class="btn btn-success pull-right" id="btnSaveVitals">
                                        <i class="fa fa-check"></i> Save Vitals &amp; Mark Complete
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script type="text/javascript">
    $(document).ready(function(){
        $('#vitalsForm').on('submit', function(e){
            var bp = $.trim($('#bp').val());
            var temp = $.trim($('#temperature').val());
            var pulse = $.trim($('#pulse_rate').val());
            var weight = $.trim($('#weight').val());
            if (bp === '' && temp === '' && pulse === '' && weight === '') {
                alert('Please enter at least one vital sign (BP, Temperature, Pulse, or Weight).');
                e.preventDefault();
                return false;
            }
            $('#btnSaveVitals').prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Saving...');
        });
    });
    </script>
</body>
</html>
