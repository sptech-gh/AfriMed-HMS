<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Invoice</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
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
<div class="content-wrapper">
    <section class="content-header">
        <h1>Invoice <?= $invoice->invoice_no ?></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Invoice</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">Invoice Details</h3>
                        <div class="box-tools">
                            <a href="<?= base_url('app/ebilling/print_invoice/' . $invoice->invoice_id) ?>" 
                               class="btn btn-default" target="_blank"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Patient:</strong> <?= $patient->firstname ?> <?= $patient->lastname ?></p>
                                <p><strong>Patient No:</strong> <?= $patient->patient_no ?></p>
                            </div>
                            <div class="col-md-6 text-right">
                                <p><strong>Invoice No:</strong> <?= $invoice->invoice_no ?></p>
                                <p><strong>Date:</strong> <?= date('d/m/Y H:i', strtotime($invoice->created_at)) ?></p>
                                <p><strong>Status:</strong> 
                                    <span class="label label-<?= $invoice->payment_status == 'PAID' ? 'success' : ($invoice->payment_status == 'PARTIAL' ? 'warning' : 'danger') ?>">
                                        <?= $invoice->payment_status ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        <hr>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Unit Price</th>
                                    <th>Covered</th>
                                    <th>Patient Pays</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invoice->items as $item): ?>
                            <tr>
                                <td><?= $item->charge_name ?><br><small class="text-muted"><?= $item->charge_type ?></small></td>
                                <td><?= $item->quantity ?></td>
                                <td>GHS <?= number_format($item->unit_price, 2) ?></td>
                                <td>GHS <?= number_format($item->covered_amount, 2) ?></td>
                                <td>GHS <?= number_format($item->patient_amount, 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr><th colspan="4" class="text-right">Subtotal:</th><td>GHS <?= number_format($invoice->subtotal, 2) ?></td></tr>
                                <?php if ($invoice->total_discount > 0): ?>
                                <tr><th colspan="4" class="text-right">Discount:</th><td>- GHS <?= number_format($invoice->total_discount, 2) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($invoice->nhis_amount > 0): ?>
                                <tr class="success"><th colspan="4" class="text-right">NHIS Coverage:</th><td>- GHS <?= number_format($invoice->nhis_amount, 2) ?></td></tr>
                                <?php endif; ?>
                                <?php if ($invoice->insurance_amount > 0): ?>
                                <tr class="info"><th colspan="4" class="text-right">Insurance Coverage:</th><td>- GHS <?= number_format($invoice->insurance_amount, 2) ?></td></tr>
                                <?php endif; ?>
                                <tr class="warning"><th colspan="4" class="text-right">Patient Responsibility:</th><td><strong>GHS <?= number_format($invoice->patient_amount, 2) ?></strong></td></tr>
                                <tr><th colspan="4" class="text-right">Amount Paid:</th><td>GHS <?= number_format($invoice->amount_paid, 2) ?></td></tr>
                                <tr class="danger"><th colspan="4" class="text-right">Balance Due:</th><td><strong>GHS <?= number_format($invoice->amount_outstanding, 2) ?></strong></td></tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php if ($invoice->payment_status !== 'PAID'): ?>
                    <div class="box-footer">
                        <a href="<?= base_url('app/ebilling/collect_payment/' . $invoice->invoice_id) ?>" 
                           class="btn btn-success btn-lg"><i class="fa fa-money"></i> Collect Payment</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-4">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title">Payment History</h3>
                    </div>
                    <div class="box-body">
                        <?php if (empty($invoice->payments)): ?>
                        <p class="text-muted">No payments recorded</p>
                        <?php else: ?>
                        <ul class="timeline timeline-inverse">
                            <?php foreach ($invoice->payments as $pay): ?>
                            <li>
                                <i class="fa fa-money bg-green"></i>
                                <div class="timeline-item">
                                    <span class="time"><i class="fa fa-clock-o"></i> <?= date('d/m H:i', strtotime($pay->payment_date)) ?></span>
                                    <h3 class="timeline-header"><?= $pay->receipt_no ?></h3>
                                    <div class="timeline-body">
                                        <strong>GHS <?= number_format($pay->payment_amount, 2) ?></strong> via <?= $pay->payment_method ?>
                                    </div>
                                    <div class="timeline-footer">
                                        <a href="<?= base_url('app/ebilling/print_receipt/' . $pay->payment_id) ?>" 
                                           class="btn btn-xs btn-default" target="_blank">Print Receipt</a>
                                    </div>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
