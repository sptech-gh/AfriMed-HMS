<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_title; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css">
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css">
    <style>
        .dept-row { padding: 15px; border-bottom: 1px solid #eee; }
        .dept-row:hover { background: #f5f5f5; }
        .dept-name { font-size: 16px; font-weight: bold; }
        .dept-amount { font-size: 20px; font-weight: bold; color: #00a65a; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <aside class="right-side">
        <section class="content-header">
            <h1><?php echo $page_title; ?> <small><?php echo date('M d, Y', strtotime($from)); ?> - <?php echo date('M d, Y', strtotime($to)); ?></small></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url(); ?>app/ebilling">Unified Billing</a></li>
                <li class="active">Department Revenue</li>
            </ol>
        </section>
        <section class="content">
            <div class="box box-primary">
                <div class="box-header">
                    <h3 class="box-title"><i class="fa fa-building"></i> Revenue by Department</h3>
                </div>
                <div class="box-body">
                    <?php if (empty($departments)): ?>
                        <div class="alert alert-info">No department revenue data found for this period.</div>
                    <?php else: ?>
                        <?php
                        $total = array_sum(array_column($departments, 'total'));
                        foreach ($departments as $dept):
                            $percentage = ($total > 0) ? round(($dept->total / $total) * 100, 1) : 0;
                        ?>
                            <div class="dept-row">
                                <div class="row">
                                    <div class="col-xs-6">
                                        <div class="dept-name"><?php echo $dept->department; ?></div>
                                        <div class="progress" style="margin: 10px 0;">
                                            <div class="progress-bar progress-bar-primary" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                    <div class="col-xs-3 text-center">
                                        <div class="dept-amount">GH₵<?php echo number_format($dept->total, 2); ?></div>
                                    </div>
                                    <div class="col-xs-3 text-right">
                                        <span class="badge"><?php echo $dept->count; ?> transactions</span>
                                        <div class="text-muted"><?php echo $percentage; ?>% of total</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="dept-row" style="background: #3c8dbc; color: white;">
                            <div class="row">
                                <div class="col-xs-6"><strong>TOTAL</strong></div>
                                <div class="col-xs-3 text-center"><strong>GH₵<?php echo number_format($total, 2); ?></strong></div>
                                <div class="col-xs-3 text-right"><strong><?php echo array_sum(array_column($departments, 'count')); ?> transactions</strong></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </aside>
</div>
<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
