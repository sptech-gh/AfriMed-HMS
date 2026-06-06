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
        .outstanding-card { border-left: 4px solid #f56954; padding: 15px; margin-bottom: 15px; background: #fff; border-radius: 4px; }
        .balance-amount { font-size: 22px; font-weight: bold; color: #f56954; }
        .patient-name { font-size: 16px; font-weight: bold; }
        .contact-info { color: #666; font-size: 12px; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <aside class="right-side">
        <section class="content-header">
            <h1><?php echo $page_title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url(); ?>app/ebilling">Unified Billing</a></li>
                <li class="active">Outstanding</li>
            </ol>
        </section>
        <section class="content">
            <!-- Total Summary -->
            <div class="row">
                <div class="col-md-12">
                    <div class="callout callout-danger">
                        <h4><i class="fa fa-exclamation-triangle"></i> Total Outstanding: GH₵<?php echo number_format($total_outstanding, 2); ?></h4>
                        <p><?php echo count($outstanding); ?> bills with pending payments</p>
                    </div>
                </div>
            </div>

            <div class="box box-danger">
                <div class="box-header">
                    <h3 class="box-title"><i class="fa fa-money"></i> Outstanding Bills</h3>
                </div>
                <div class="box-body">
                    <?php if (empty($outstanding)): ?>
                        <div class="alert alert-success">
                            <i class="fa fa-check-circle"></i> Great! No outstanding balances. All bills have been paid.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($outstanding as $bill): ?>
                                <div class="col-md-6">
                                    <div class="outstanding-card">
                                        <div class="row">
                                            <div class="col-xs-8">
                                                <div class="patient-name">
                                                    <i class="fa fa-user"></i> <?php echo $bill->patient_name ?? 'Unknown'; ?>
                                                </div>
                                                <div class="contact-info">
                                                    <i class="fa fa-phone"></i> <?php echo $bill->mobile_no ?? 'N/A'; ?><br>
                                                    <i class="fa fa-file-text-o"></i> Bill #<?php echo $bill->bill_no; ?>
                                                </div>
                                            </div>
                                            <div class="col-xs-4 text-right">
                                                <div class="balance-amount">GH₵<?php echo number_format($bill->balance_due, 2); ?></div>
                                                <span class="badge badge-<?php echo strtolower($bill->payment_status); ?>"><?php echo $bill->payment_status; ?></span>
                                            </div>
                                        </div>
                                        <div class="row" style="margin-top: 10px;">
                                            <div class="col-xs-12 text-right">
                                                <a href="<?php echo base_url(); ?>app/ebilling/view_bill/<?php echo $bill->bill_id; ?>" class="btn btn-primary btn-sm">
                                                    <i class="fa fa-eye"></i> View Bill
                                                </a>
                                                <?php if ($bill->payment_status !== 'PAID'): ?>
                                                    <a href="<?php echo base_url(); ?>app/ebilling/collect_payment" class="btn btn-success btn-sm">
                                                        <i class="fa fa-money"></i> Collect
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
