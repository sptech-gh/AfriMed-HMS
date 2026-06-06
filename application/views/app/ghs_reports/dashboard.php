<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — GHS Reports</title>
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
                    <h1><i class="fa fa-bar-chart"></i> GHS Reports Dashboard <small>Ghana Health Service Compliant Reports</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li class="active">GHS Reports</li>
                    </ol>
                </section>

                <section class="content">
                    <?php if (isset($message) && $message) { echo $message; } ?>

                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="small-box bg-aqua">
                                <div class="inner">
                                    <h3><i class="fa fa-stethoscope"></i></h3>
                                    <p>OPD Attendance Report</p>
                                </div>
                                <div class="icon"><i class="fa fa-hospital-o"></i></div>
                                <a href="<?php echo base_url(); ?>app/ghs_reports/opd_attendance" class="small-box-footer">
                                    View Report <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><i class="fa fa-medkit"></i></h3>
                                    <p>Top Diagnoses (Morbidity)</p>
                                </div>
                                <div class="icon"><i class="fa fa-heartbeat"></i></div>
                                <a href="<?php echo base_url(); ?>app/ghs_reports/diagnosis" class="small-box-footer">
                                    View Report <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="small-box bg-yellow">
                                <div class="inner">
                                    <h3><i class="fa fa-flask"></i></h3>
                                    <p>Pharmacy Consumption</p>
                                </div>
                                <div class="icon"><i class="fa fa-cubes"></i></div>
                                <a href="<?php echo base_url(); ?>app/ghs_reports/pharmacy_consumption" class="small-box-footer">
                                    View Report <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 col-sm-6">
                            <div class="small-box bg-purple">
                                <div class="inner">
                                    <h3><i class="fa fa-shield"></i></h3>
                                    <p>NHIS vs Cash Report</p>
                                </div>
                                <div class="icon"><i class="fa fa-balance-scale"></i></div>
                                <a href="<?php echo base_url(); ?>app/ghs_reports/nhis_cash" class="small-box-footer">
                                    View Report <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><i class="fa fa-money"></i></h3>
                                    <p>Revenue Report</p>
                                </div>
                                <div class="icon"><i class="fa fa-line-chart"></i></div>
                                <a href="<?php echo base_url(); ?>app/ghs_reports/revenue" class="small-box-footer">
                                    View Report <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <div class="small-box bg-teal">
                                <div class="inner">
                                    <h3><i class="fa fa-calendar-check-o"></i></h3>
                                    <p>Daily Returns (GHS Standard)</p>
                                </div>
                                <div class="icon"><i class="fa fa-file-text"></i></div>
                                <a href="<?php echo base_url(); ?>app/ghs_reports/daily_returns" class="small-box-footer">
                                    View Report <i class="fa fa-arrow-circle-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                </section>
            </aside>
        </div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    </body>
</html>
