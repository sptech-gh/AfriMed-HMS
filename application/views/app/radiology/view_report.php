<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title ?? 'View Report'; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php'); ?>
    
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php'); ?>
        
        <aside class="right-side">
            <section class="content-header">
            <h1><i class="fa fa-file-text"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url('app/radiology'); ?>">Radiology</a></li>
                <li class="active">View Report</li>
            </ol>
        </section>
        
        <section class="content">
            <?php if(isset($order) && $order): ?>
            <div class="row">
                <div class="col-md-10">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title">Radiology Report - <?php echo $order->order_no; ?></h3>
                            <div class="box-tools">
                                <button onclick="window.print()" class="btn btn-sm btn-default">
                                    <i class="fa fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <!-- Patient Info -->
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-condensed">
                                        <tr><th width="40%">Patient Name:</th><td><?php echo ($order->firstname ?? '') . ' ' . ($order->lastname ?? ''); ?></td></tr>
                                        <tr><th>Patient No:</th><td><?php echo $order->pat_no ?? $order->patient_no; ?></td></tr>
                                        <tr><th>Order Date:</th><td><?php echo date('d M Y H:i', strtotime($order->ordered_at)); ?></td></tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-condensed">
                                        <tr><th width="40%">Test:</th><td><?php echo $order->test_name; ?></td></tr>
                                        <tr><th>NHIS Code:</th><td><?php echo $order->nhis_code ?? 'N/A'; ?></td></tr>
                                        <tr><th>Status:</th><td><span class="label label-<?php echo $order->status == 'completed' ? 'success' : 'warning'; ?>"><?php echo strtoupper($order->status); ?></span></td></tr>
                                    </table>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <?php if(isset($order->result) && $order->result): ?>
                            <!-- Findings -->
                            <div class="form-group">
                                <label><strong>FINDINGS:</strong></label>
                                <div class="well well-sm"><?php echo nl2br(htmlspecialchars($order->result->findings ?? '')); ?></div>
                            </div>
                            
                            <!-- Impression -->
                            <div class="form-group">
                                <label><strong>IMPRESSION:</strong></label>
                                <div class="well well-sm"><?php echo nl2br(htmlspecialchars($order->result->impression ?? '')); ?></div>
                            </div>
                            
                            <!-- Recommendations -->
                            <?php if(!empty($order->result->recommendations)): ?>
                            <div class="form-group">
                                <label><strong>RECOMMENDATIONS:</strong></label>
                                <div class="well well-sm"><?php echo nl2br(htmlspecialchars($order->result->recommendations)); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            <p class="text-muted">
                                <small>
                                    Performed: <?php echo date('d M Y H:i', strtotime($order->result->performed_at ?? $order->completed_at)); ?>
                                </small>
                            </p>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="fa fa-exclamation-triangle"></i> Result not yet available.
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="box-footer">
                            <a href="<?php echo base_url('app/radiology'); ?>" class="btn btn-default">
                                <i class="fa fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="alert alert-danger">
                <i class="fa fa-exclamation-circle"></i> Report not found.
            </div>
            <?php endif; ?>
            </section>
        </aside>
    </div>
    
    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
