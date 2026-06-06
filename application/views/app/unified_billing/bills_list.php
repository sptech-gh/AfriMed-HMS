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
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-PAID { background: #00a65a; color: white; }
        .status-PENDING { background: #f39c12; color: white; }
        .status-PARTIAL { background: #f56954; color: white; }
        .status-CANCELLED { background: #999; color: white; }
        .bill-row:hover { background: #f5f5f5; cursor: pointer; }
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
                <li><a href="<?php echo base_url(); ?>app/unified_billing">Billing</a></li>
                <li class="active"><?php echo $page_title; ?></li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-info">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-list"></i> Bills</h3>
                            <div class="box-tools pull-right">
                                <a href="<?php echo base_url(); ?>app/unified_billing" class="btn btn-sm btn-default">
                                    <i class="fa fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if(!empty($bills)): ?>
                            <table class="table table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Bill No</th>
                                        <th>Patient</th>
                                        <th>Visit Type</th>
                                        <th>Total</th>
                                        <th>Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($bills as $bill): ?>
                                    <tr class="bill-row" onclick="window.location.href='<?php echo base_url(); ?>app/unified_billing/view_bill/<?php echo $bill->bill_id; ?>'">
                                        <td><strong><?php echo $bill->bill_no; ?></strong></td>
                                        <td>
                                            <?php echo $bill->patient_name; ?><br>
                                            <small class="text-muted"><?php echo $bill->patient_no; ?></small>
                                        </td>
                                        <td><?php echo $bill->visit_type; ?></td>
                                        <td>GHS <?php echo number_format($bill->net_amount, 2); ?></td>
                                        <td>GHS <?php echo number_format($bill->paid_amount, 2); ?></td>
                                        <td><strong class="<?php echo $bill->balance_due > 0 ? 'text-danger' : 'text-success'; ?>">GHS <?php echo number_format($bill->balance_due, 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $bill->payment_status; ?>">
                                                <?php echo $bill->payment_status; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y H:i', strtotime($bill->created_at)); ?></td>
                                        <td onclick="event.stopPropagation()">
                                            <a href="<?php echo base_url(); ?>app/unified_billing/view_bill/<?php echo $bill->bill_id; ?>" class="btn btn-xs btn-info">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                            <a href="<?php echo base_url(); ?>app/unified_billing/print_bill/<?php echo $bill->bill_id; ?>" class="btn btn-xs btn-default" target="_blank">
                                                <i class="fa fa-print"></i> Print
                                            </a>
									<?php
									$lp = null;
									if (isset($bill->bill_id) && isset($latest_payments) && is_array($latest_payments) && isset($latest_payments[(string)$bill->bill_id])) {
										$lp = $latest_payments[(string)$bill->bill_id];
									}
									?>
									<?php if ($lp && isset($lp->legacy_receipt_no) && trim((string)$lp->legacy_receipt_no) !== '') { ?>
										<span class="label label-success" style="margin-left:6px;">OR: <?php echo htmlspecialchars((string)$lp->legacy_receipt_no); ?></span>
										<a href="<?php echo base_url(); ?>app/unified_billing/print_official_receipt/<?php echo $lp->payment_id; ?>" class="btn btn-xs btn-success" target="_blank" style="margin-left:6px;">
											<i class="fa fa-print"></i> Print OR
										</a>
										<a href="<?php echo base_url(); ?>app/unified_billing/print_official_receipt_pdf/<?php echo $lp->payment_id; ?>" class="btn btn-xs btn-info" target="_blank" style="margin-left:4px;">
											<i class="fa fa-file-pdf-o"></i> OR PDF
										</a>
									<?php } ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No bills found.
                            </div>
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
