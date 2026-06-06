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
        .stat-box { padding: 20px; border-radius: 8px; text-align: center; margin-bottom: 20px; color: white; }
        .stat-box.primary { background: #3c8dbc; }
        .stat-box.success { background: #00a65a; }
        .stat-box.warning { background: #f39c12; }
        .stat-box.danger { background: #f56954; }
        .stat-box h3 { margin: 0; font-size: 28px; font-weight: bold; }
        .stat-box p { margin: 5px 0 0 0; }
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
                <li class="active">Analytics</li>
            </ol>
        </section>
        <section class="content">
            <!-- Summary Stats -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box primary">
                        <h3>GH₵<?php echo number_format($summary['total_revenue'], 2); ?></h3>
                        <p><i class="fa fa-money"></i> Total Revenue</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box success">
                        <h3><?php echo $summary['transaction_count']; ?></h3>
                        <p><i class="fa fa-exchange"></i> Transactions</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box warning">
                        <h3>GH₵<?php echo number_format($summary['total_billed'], 2); ?></h3>
                        <p><i class="fa fa-file-text"></i> Total Billed</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box danger">
                        <h3><?php echo $summary['collection_rate']; ?>%</h3>
                        <p><i class="fa fa-percent"></i> Collection Rate</p>
                    </div>
                </div>
            </div>

            <!-- Outstanding -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-info">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Outstanding Balances</h3>
                        </div>
                        <div class="box-body">
                            <h2 class="text-center text-red">GH₵<?php echo number_format($summary['total_outstanding'], 2); ?></h2>
                            <p class="text-center">Total outstanding amount yet to be collected</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- By Payment Method -->
            <div class="row">
                <div class="col-md-6">
                    <div class="box box-success">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-credit-card"></i> Revenue by Payment Method</h3>
                        </div>
                        <div class="box-body">
                            <?php if (empty($by_payment_method)): ?>
                                <div class="alert alert-info">No payment data found for this period.</div>
                            <?php else: ?>
                                <table class="table table-striped">
                                    <tr><th>Method</th><th>Amount</th><th>Count</th></tr>
                                    <?php foreach ($by_payment_method as $row): ?>
                                        <tr>
                                            <td><?php echo $row->payment_method; ?></td>
                                            <td>GH₵<?php echo number_format($row->total, 2); ?></td>
                                            <td><?php echo $row->count; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-building"></i> Revenue by Department</h3>
                        </div>
                        <div class="box-body">
                            <?php if (empty($by_department)): ?>
                                <div class="alert alert-info">No department data found for this period.</div>
                            <?php else: ?>
                                <table class="table table-striped">
                                    <tr><th>Department</th><th>Amount</th><th>Count</th></tr>
                                    <?php foreach ($by_department as $row): ?>
                                        <tr>
                                            <td><?php echo $row->department; ?></td>
                                            <td>GH₵<?php echo number_format($row->total, 2); ?></td>
                                            <td><?php echo $row->count; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>
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
