<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Laboratory Dashboard</title>
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
                    <h1>Laboratory Dashboard <small><?php echo date('l, M d, Y'); ?></small></h1>
                </section>
                <section class="content">

                    <!-- Stat Boxes -->
                    <div class="row">
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-aqua">
                                <div class="inner">
                                    <h3><?php echo isset($tests_today) ? $tests_today : 0; ?></h3>
                                    <p>Tests Today</p>
                                </div>
                                <div class="icon"><i class="ion ion-erlenmeyer-flask"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory" class="small-box-footer">View All <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-red">
                                <div class="inner">
                                    <h3><?php echo isset($pending_labs) ? $pending_labs : 0; ?></h3>
                                    <p>Pending Tests</p>
                                </div>
                                <div class="icon"><i class="ion ion-flask"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory" class="small-box-footer">Go to Lab <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-green">
                                <div class="inner">
                                    <h3><?php echo isset($completed_today) ? $completed_today : 0; ?></h3>
                                    <p>Completed Today</p>
                                </div>
                                <div class="icon"><i class="ion ion-checkmark-circled"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory" class="small-box-footer">View Results <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-lg-3 col-xs-6">
                            <div class="small-box bg-orange">
                                <div class="inner">
                                    <h3><?php echo isset($urgent_tests) ? $urgent_tests : 0; ?></h3>
                                    <p>Urgent Tests</p>
                                </div>
                                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                                <a href="<?php echo base_url();?>app/laboratory" class="small-box-footer">View Urgent <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                    </div>

                    <!-- Lab Worklist Table -->
                    <div class="row">
                        <section class="col-lg-12">
                            <div class="box box-danger">
                                <div class="box-header">
                                    <div class="pull-right box-tools">
                                        <button class="btn btn-danger btn-sm" data-widget='collapse' data-toggle="tooltip" title="Collapse"><i class="fa fa-minus"></i></button>
                                    </div>
                                    <i class="fa fa-flask"></i>
                                    <h3 class="box-title">Lab Worklist — Pending Requests</h3>
                                </div>
                                <div class="box-body no-padding">
                                    <div class="table-responsive">
                                        <table class="table table-hover table-striped">
                                        <thead><tr>
                                            <th>Patient Name</th><th>Patient No.</th><th>Test</th><th>Doctor</th><th>Priority</th><th>Status</th><th>Payment</th><th>Requested</th><th>Action</th>
                                        </tr></thead>
                                        <tbody>
                                        <?php if (isset($lab_worklist) && is_array($lab_worklist)) { foreach($lab_worklist as $lab) {
                                            $pri = isset($lab->priority) ? strtoupper(trim($lab->priority)) : 'ROUTINE';
                                            if ($pri === 'STAT') { $priLabel = '<span class="label label-danger">STAT</span>'; }
                                            elseif ($pri === 'URGENT') { $priLabel = '<span class="label label-warning">URGENT</span>'; }
                                            else { $priLabel = '<span class="label label-default">ROUTINE</span>'; }
                                            $st = isset($lab->status) ? $lab->status : 'pending';
                                            $stLabel = ($st === 'completed') ? '<span class="label label-success">Completed</span>' : '<span class="label label-info">Pending</span>';

                                            $ssotPay = isset($lab->ssot_payment_status) ? strtoupper(trim((string)$lab->ssot_payment_status)) : '';
                                            $ssotPayer = isset($lab->ssot_payer_type) ? strtoupper(trim((string)$lab->ssot_payer_type)) : '';
                                            $payLabel = '';
                                            if ($ssotPay === '' && $ssotPayer === '') {
                                                $payLabel = '<span class="label label-default">UNBILLED</span>';
                                            } elseif ($ssotPayer === 'NHIS' || $ssotPay === 'NHIS') {
                                                $payLabel = '<span class="label label-success">NHIS</span>';
                                            } elseif ($ssotPay === 'PAID' || $ssotPay === 'WAIVED') {
                                                $payLabel = '<span class="label label-success">PAID</span>';
                                            } else {
                                                $payLabel = '<span class="label label-warning">PAYMENT PENDING</span>';
                                            }
                                        ?>
                                            <tr>
                                                <td><?php echo isset($lab->patient_name) ? $lab->patient_name : '';?> </td>
                                                <td><a href="<?php echo base_url();?>app/patient/view/<?php echo $lab->patient_no;?>"><?php echo $lab->patient_no;?></a></td>
                                                <td><?php echo isset($lab->test_name) ? $lab->test_name : '';?> </td>
                                                <td><?php echo isset($lab->doctor_name) ? trim($lab->doctor_name) : '';?> </td>
                                                <td><?php echo $priLabel;?> </td>
                                                <td><?php echo $stLabel;?> </td>
                                                <td><?php echo $payLabel;?> </td>
                                                <td><?php echo isset($lab->dDate) ? date("M d, Y", strtotime($lab->dDate)) : '';?> </td>
                                                <td><a href="<?php echo base_url();?>app/laboratory/request/<?php echo url_safe_id($lab->iop_id);?>" class="btn btn-xs btn-primary"><i class="fa fa-pencil"></i> Process</a></td>
                                            </tr>
                                        <?php } } ?>
                                        <?php if (!isset($lab_worklist) || !is_array($lab_worklist) || count($lab_worklist) == 0) { ?>
                                            <tr><td colspan="9" class="text-center text-muted">No pending lab requests</td></tr>
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
