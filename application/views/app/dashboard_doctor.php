<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Doctor Dashboard</title>
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
                    <h1>Doctor Dashboard <small><?php echo date('l, M d, Y'); ?></small></h1>
                </section>
                <section class="content">

                    <!-- Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-4 col-xs-6">
                            <div class="small-box bg-aqua">
                                <div class="inner">
                                    <h3><?php echo isset($opd_waiting) ? $opd_waiting : 0; ?></h3>
                                    <p>OPD Patients Waiting</p>
                                </div>
                                <div class="icon"><i class="ion ion-person-stalker"></i></div>
                                <a href="<?php echo base_url();?>app/opd" class="small-box-footer">Go to OPD <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo isset($ipd_count) ? $ipd_count : 0; ?></h3>
                                    <p>IPD Admitted Patients</p>
                                </div>
                                <div class="icon"><i class="ion ion-ios-bed"></i></div>
                                <a href="<?php echo base_url();?>app/ipd" class="small-box-footer">Go to IPD <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-6">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3><?php echo isset($today_appointments) ? $today_appointments : 0; ?></h3>
                                    <p>Today's Appointments</p>
                                </div>
                                <div class="icon"><i class="ion ion-calendar"></i></div>
                                <a href="<?php echo base_url();?>app/appointment" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- OPD Waiting List -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-primary">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-clock-o"></i>
                                    <h3 class="box-title">OPD Waiting List</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Visit ID</th><th>Patient No.</th><th>Patient Name</th><th>Time</th><th>Department</th><th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($opd_waiting_list) && is_array($opd_waiting_list)) { foreach($opd_waiting_list as $w) { ?>
                                            <tr>
                                                <td><?php echo $w->IO_ID;?></td>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $w->patient_no;?>"><?php echo $w->patient_no;?></a></td>
                                                <td><?php echo $w->patient_name;?></td>
                                                <td><?php echo $w->time_visit;?></td>
                                                <td><?php echo $w->dept_name;?></td>
                                                <td><a href="<?php echo base_url();?>app/opd/view/<?php echo $w->IO_ID;?>" class="btn btn-xs btn-primary"><i class="fa fa-eye"></i> View</a></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($opd_waiting_list) || !is_array($opd_waiting_list) || count($opd_waiting_list) == 0) { ?>
                                            <tr><td colspan="6" class="text-center text-muted">No patients waiting</td></tr>
                                        <?php } ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                    <div class="row">
                        <!-- IPD Patients -->
                        <section class="col-lg-6">
                            <div class="box box-success">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-success btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-bed"></i>
                                    <h3 class="box-title">My IPD Patients</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                        <thead><tr><th>Visit ID</th><th>Patient</th><th>Admitted</th><th>Dept</th></tr></thead>
                                        <tbody>
                                        <?php if (isset($ipd_patients_list) && is_array($ipd_patients_list)) { foreach($ipd_patients_list as $ip) { ?>
                                            <tr>
                                                <td><a href="<?php echo base_url();?>app/ipd/view/<?php echo $ip->IO_ID;?>"><?php echo $ip->IO_ID;?></a></td>
                                                <td><?php echo $ip->patient_name;?></td>
                                                <td><?php echo date("M d", strtotime($ip->date_visit));?></td>
                                                <td><?php echo $ip->dept_name;?></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($ipd_patients_list) || !is_array($ipd_patients_list) || count($ipd_patients_list) == 0) { ?>
                                            <tr><td colspan="4" class="text-center text-muted">No admitted patients</td></tr>
                                        <?php } ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <!-- Today's Appointments -->
                        <section class="col-lg-6">
                            <div class="box box-warning">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-warning btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-calendar"></i>
                                    <h3 class="box-title">My Appointments Today</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                        <thead><tr><th>Patient</th><th>Time</th><th>Reason</th></tr></thead>
                                        <tbody>
                                        <?php if (isset($doctor_appointments) && is_array($doctor_appointments)) { foreach($doctor_appointments as $da) { ?>
                                            <tr>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $da->patient_no;?>"><?php echo $da->patient_name;?></a></td>
                                                <td><?php echo $da->appHour.':'.$da->appMinutes.' '.$da->appAMPM;?></td>
                                                <td><?php echo $da->appointmentReason;?></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($doctor_appointments) || !is_array($doctor_appointments) || count($doctor_appointments) == 0) { ?>
                                            <tr><td colspan="3" class="text-center text-muted">No appointments today</td></tr>
                                        <?php } ?>
                                        </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>

                </section>
            </aside>
        </div>
        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    </body>
</html>
