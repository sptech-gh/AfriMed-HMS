<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Sonographer Dashboard</title>
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
                    <h1>Sonographer Dashboard <small><?php echo date('l, M d, Y'); ?></small></h1>
                </section>
                <section class="content">

                    <!-- Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-6 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><?php echo isset($pending_scans) ? $pending_scans : 0; ?></h3>
                                    <p>Scans Pending</p>
                                </div>
                                <div class="icon"><i class="fa fa-heartbeat"></i></div>
                                <a href="<?php echo base_url();?>app/sonography" class="small-box-footer">Go to Sonography <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-6 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo isset($completed_today) ? $completed_today : 0; ?></h3>
                                    <p>Completed Today</p>
                                </div>
                                <div class="icon"><i class="ion ion-checkmark-circled"></i></div>
                                <a href="<?php echo base_url();?>app/sonography" class="small-box-footer">View Results <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Sonography Requests Table -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-danger">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-danger btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-heartbeat"></i>
                                    <h3 class="box-title">Pending Scan Requests</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Patient Name</th><th>Patient No.</th><th>Scan</th><th>Requesting Doctor</th><th>Requested</th><th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($pending_scan_list) && is_array($pending_scan_list)) { foreach($pending_scan_list as $scan) { ?>
                                            <tr>
                                                <td><?php echo isset($scan->patient_name) ? $scan->patient_name : '';?></td>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $scan->patient_no;?>"><?php echo $scan->patient_no;?></a></td>
                                                <td><?php echo isset($scan->scan_name) ? $scan->scan_name : '';?></td>
                                                <td><?php echo isset($scan->doctor_name) ? trim($scan->doctor_name) : '';?></td>
                                                <td><?php echo isset($scan->dDate) ? date("M d, Y", strtotime($scan->dDate)) : '';?></td>
                                                <td><a href="<?php echo base_url();?>app/sonography/request/<?php echo url_safe_id($scan->iop_id);?>" class="btn btn-xs btn-primary"><i class="fa fa-upload"></i> Upload Result</a></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($pending_scan_list) || !is_array($pending_scan_list) || count($pending_scan_list) == 0) { ?>
                                            <tr><td colspan="6" class="text-center text-muted">No pending scan requests</td></tr>
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
