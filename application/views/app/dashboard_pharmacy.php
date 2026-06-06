<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Pharmacy Dashboard</title>
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
                    <h1>Pharmacy Dashboard <small><?php echo date('l, M d, Y'); ?></small></h1>
                </section>
                <section class="content">

                    <!-- Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><?php echo isset($pending_rx) ? $pending_rx : 0; ?></h3>
                                    <p>Pending</p>
                                </div>
                                <div class="icon"><i class="ion ion-medkit"></i></div>
                                <a href="<?php echo base_url();?>app/pharmacy" class="small-box-footer">Go to Pharmacy <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-orange">
                                <div class="inner">
                                    <h3><?php echo isset($reserved_rx) ? $reserved_rx : 0; ?></h3>
                                    <p>Reserved</p>
                                </div>
                                <div class="icon"><i class="fa fa-bookmark"></i></div>
                                <a href="<?php echo base_url();?>app/pharmacy" class="small-box-footer">View Reserved <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-purple">
                                <div class="inner">
                                    <h3><?php echo isset($partial_rx) ? $partial_rx : 0; ?></h3>
                                    <p>Partial</p>
                                </div>
                                <div class="icon"><i class="fa fa-adjust"></i></div>
                                <a href="<?php echo base_url();?>app/pharmacy" class="small-box-footer">View Partial <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo isset($dispensed_today) ? $dispensed_today : 0; ?></h3>
                                    <p>Dispensed Today</p>
                                </div>
                                <div class="icon"><i class="ion ion-checkmark-circled"></i></div>
                                <a href="<?php echo base_url();?>app/pharmacy" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Prescriptions Table -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-purple" style="border-top-color:#605ca8;">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-sm" style="background:#605ca8;color:#fff;" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-medkit"></i>
                                    <h3 class="box-title">Today's Prescriptions</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Rx ID</th><th>Patient No.</th><th>Patient Name</th><th>Drug</th><th>Qty</th><th>Date</th><th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($pending_rx_list) && is_array($pending_rx_list)) { foreach($pending_rx_list as $rx) { ?>
                                            <tr>
                                                <td><?php echo $rx->iop_med_id;?></td>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $rx->patient_no;?>"><?php echo $rx->patient_no;?></a></td>
                                                <td><?php echo $rx->patient_name;?></td>
                                                <td><?php echo $rx->drug_name;?></td>
                                                <td><?php echo $rx->total_qty;?></td>
                                                <td><?php echo date("M d, Y", strtotime($rx->dDate));?></td>
                                                <td><a href="<?php echo base_url();?>app/pharmacy" class="btn btn-xs btn-primary"><i class="fa fa-pills"></i> Dispense</a></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($pending_rx_list) || !is_array($pending_rx_list) || count($pending_rx_list) == 0) { ?>
                                            <tr><td colspan="7" class="text-center text-muted">No pending prescriptions</td></tr>
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
