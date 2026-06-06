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
        .patient-header { background: #3c8dbc; color: white; padding: 20px; margin-bottom: 20px; }
        .status-badge { padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .status-PAID { background: #00a65a; color: white; }
        .status-PENDING { background: #f39c12; color: white; }
        .status-PARTIAL { background: #f56954; color: white; }
        .status-CANCELLED { background: #999; color: white; }
        .balance-box { text-align: center; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .balance-due { background: #f56954; color: white; }
        .balance-paid { background: #00a65a; color: white; }
        .balance-box h3 { margin: 0; font-size: 28px; }
        .balance-box p { margin: 5px 0 0 0; }
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
                <li class="active">Patient History</li>
            </ol>
        </section>

        <section class="content">
            <!-- Patient Info Header -->
            <div class="patient-header">
                <div class="row">
                    <div class="col-md-6">
                        <h3><i class="fa fa-user"></i> <?php echo $patient->lastname . ' ' . $patient->firstname; ?></h3>
                        <p>Patient No: <?php echo $patient->patient_no; ?> | Gender: <?php echo $patient->gender; ?></p>
                    </div>
                    <div class="col-md-6 text-right">
                        <a href="<?php echo base_url(); ?>app/patient/view/<?php echo $patient->patient_no; ?>" class="btn btn-default">
                            <i class="fa fa-eye"></i> View Patient Profile
                        </a>
                    </div>
                </div>
            </div>

            <!-- Summary Stats -->
            <?php
            $total_bills = count($bills);
            $total_outstanding = 0;
            $paid_bills = 0;
            $pending_bills = 0;
            foreach ($bills as $bill) {
                if ($bill->payment_status == 'PAID') {
                    $paid_bills++;
                } else {
                    $pending_bills++;
                    $total_outstanding += $bill->balance_due;
                }
            }
            ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="balance-box bg-aqua">
                        <h3><?php echo $total_bills; ?></h3>
                        <p>Total Bills</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="balance-box balance-paid">
                        <h3><?php echo $paid_bills; ?></h3>
                        <p>Paid Bills</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="balance-box bg-yellow">
                        <h3><?php echo $pending_bills; ?></h3>
                        <p>Pending Bills</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="balance-box balance-due">
                        <h3>GHS <?php echo number_format($total_outstanding, 2); ?></h3>
                        <p>Total Outstanding</p>
                    </div>
                </div>
            </div>

            <!-- Bills List -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-info">
                        <div class="box-header">
                            <h3 class="box-title"><i class="fa fa-list"></i> Billing History</h3>
                        </div>
                        <div class="box-body table-responsive">
                            <?php if(!empty($bills)): ?>
                            <table class="table table-hover table-bordered">
                                <thead>
                                    <tr>
                                        <th>Bill No</th>
                                        <th>Visit Type</th>
                                        <th>Total Amount</th>
                                        <th>Amount Paid</th>
                                        <th>Balance</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($bills as $bill): ?>
                                    <tr>
                                        <td><strong><?php echo $bill->bill_no; ?></strong></td>
                                        <td><?php echo $bill->visit_type; ?></td>
                                        <td>GHS <?php echo number_format($bill->net_amount, 2); ?></td>
                                        <td>GHS <?php echo number_format($bill->paid_amount, 2); ?></td>
                                        <td>
                                            <strong class="<?php echo $bill->balance_due > 0 ? 'text-danger' : 'text-success'; ?>">
                                                GHS <?php echo number_format($bill->balance_due, 2); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $bill->payment_status; ?>">
                                                <?php echo $bill->payment_status; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($bill->created_at)); ?></td>
                                        <td>
                                            <a href="<?php echo base_url(); ?>app/unified_billing/view_bill/<?php echo $bill->bill_id; ?>" class="btn btn-xs btn-info">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                            <?php if($can_collect && $bill->balance_due > 0): ?>
                                            <a href="<?php echo base_url(); ?>app/unified_billing/view_bill/<?php echo $bill->bill_id; ?>" class="btn btn-xs btn-success">
                                                <i class="fa fa-money"></i> Pay
                                            </a>
                                            <?php endif; ?>
                                            <a href="<?php echo base_url(); ?>app/unified_billing/print_bill/<?php echo $bill->bill_id; ?>" class="btn btn-xs btn-default" target="_blank">
                                                <i class="fa fa-print"></i> Print
                                            </a>
								<?php
								$lp = null;
								if (isset($latest_payments) && is_array($latest_payments) && isset($latest_payments[(string)$bill->bill_id])) {
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
                                <i class="fa fa-info-circle"></i> No billing history found for this patient.
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
