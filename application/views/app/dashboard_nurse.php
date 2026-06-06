<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Nurse Dashboard</title>
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
                    <h1>Nurse Dashboard <small><?php echo date('l, M d, Y'); ?></small></h1>
                </section>
                <section class="content">

                    <!-- Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-4 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><?php echo isset($pending_vitals) ? $pending_vitals : 0; ?></h3>
                                    <p>Pending Vitals</p>
                                </div>
                                <div class="icon"><i class="ion ion-heart"></i></div>
                                <a href="<?php echo base_url();?>app/nurse_module" class="small-box-footer">Take Vitals <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-6">
                            <div class="small-box bg-aqua">
                                <div class="inner">
                                    <h3><?php echo isset($admitted_count) ? $admitted_count : 0; ?></h3>
                                    <p>Admitted Patients (IPD)</p>
                                </div>
                                <div class="icon"><i class="ion ion-ios-bed"></i></div>
                                <a href="<?php echo base_url();?>app/nurse_module" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-4 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo isset($today_opd) ? $today_opd : 0; ?></h3>
                                    <p>Today's OPD Visits</p>
                                </div>
                                <div class="icon"><i class="ion ion-medkit"></i></div>
                                <a href="<?php echo base_url();?>app/nurse_module" class="small-box-footer">View OPD <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- Admitted Patients Table -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-primary">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-primary btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-bed"></i>
                                    <h3 class="box-title">Admitted Patients — Pending Care</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Visit ID</th><th>Patient No.</th><th>Patient Name</th><th>Admitted</th><th>Department</th><th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($admitted_list) && is_array($admitted_list)) { foreach($admitted_list as $a) { ?>
                                            <tr>
                                                <td><?php echo $a->IO_ID;?></td>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $a->patient_no;?>"><?php echo $a->patient_no;?></a></td>
                                                <td><?php echo $a->patient_name;?></td>
                                                <td><?php echo date("M d, Y", strtotime($a->date_visit));?></td>
                                                <td><?php echo $a->dept_name;?></td>
                                                <td><a href="<?php echo base_url();?>app/nursing/workspace/<?php echo urlencode($a->IO_ID);?>" class="btn btn-xs btn-primary"><i class="fa fa-stethoscope"></i> Workspace</a></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($admitted_list) || !is_array($admitted_list) || count($admitted_list) == 0) { ?>
                                            <tr><td colspan="6" class="text-center text-muted">No admitted patients</td></tr>
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
