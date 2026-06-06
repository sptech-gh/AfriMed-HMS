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
        .refund-card { border-left: 4px solid #f39c12; padding: 15px; margin-bottom: 15px; background: #fff; border-radius: 4px; }
        .refund-card.approved { border-left-color: #00a65a; }
        .refund-card.rejected { border-left-color: #dc3545; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
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
                <li class="active">Refunds</li>
            </ol>
        </section>
        <section class="content">
            <?php if($this->session->flashdata('message')): ?>
                <div class="alert alert-success alert-dismissable">
                    <i class="fa fa-check"></i> <?php echo $this->session->flashdata('message');?>
                </div>
            <?php endif; ?>
            <div class="box box-warning">
                <div class="box-header">
                    <h3 class="box-title"><i class="fa fa-undo"></i> Refund Requests</h3>
                </div>
                <div class="box-body">
                    <?php if (empty($refunds)): ?>
                        <div class="alert alert-info">No refund requests found.</div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($refunds as $refund): ?>
                                <div class="col-md-6">
                                    <div class="refund-card <?php echo strtolower($refund->status); ?>">
                                        <h4><?php echo $refund->patient_name ?? 'Unknown'; ?></h4>
                                        <p>Bill: <?php echo $refund->bill_no; ?> | Amount: GH₵<?php echo number_format($refund->amount, 2); ?></p>
                                        <span class="status-badge"><?php echo $refund->status; ?></span>
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
