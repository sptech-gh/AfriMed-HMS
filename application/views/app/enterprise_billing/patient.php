<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Patient Billing</title>
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
        <h1>
            Patient Billing 
            <small><?= $patient->firstname ?> <?= $patient->lastname ?> (<?= $patient->patient_no ?>)</small>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Patient</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <!-- Patient Info -->
            <div class="col-md-4">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-user"></i> Patient Information</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-condensed">
                            <tr><th>Patient No:</th><td><?= $patient->patient_no ?></td></tr>
                            <tr><th>Name:</th><td><?= $patient->firstname ?> <?= $patient->lastname ?></td></tr>
                            <tr><th>Phone:</th><td><?= $patient->phone ?></td></tr>
                            <tr><th>Type:</th><td>
                                <span class="label label-<?= ($patient->patient_type ?? '') == 'NHIS' ? 'success' : 'primary' ?>">
                                    <?= $patient->patient_type ?? 'CASH' ?>
                                </span>
                            </td></tr>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Blocked Services -->
            <?php if (!empty($blocked_services)): ?>
            <div class="col-md-8">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-lock"></i> Blocked Services</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <table class="table table-striped">
                            <thead><tr><th>Service</th><th>Amount</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($blocked_services as $svc): ?>
                            <tr>
                                <td><?= $svc->charge_name ?></td>
                                <td>GHS <?= number_format($svc->patient_amount, 2) ?></td>
                                <td>
                                    <button class="btn btn-xs btn-success btn-pay" data-gate="<?= $svc->gate_id ?>">
                                        <i class="fa fa-money"></i> Pay
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pending Transactions -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Transactions</h3>
                        <?php if ($active_visit && !empty($pending_transactions)): ?>
                        <div class="box-tools">
                            <button class="btn btn-success" id="btn_generate_invoice">
                                <i class="fa fa-file-text-o"></i> Generate Invoice
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="box-body table-responsive">
                        <?php if (empty($pending_transactions)): ?>
                        <p class="text-muted">No pending transactions</p>
                        <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Gross</th>
                                    <th>Covered</th>
                                    <th>Patient Pays</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php $total = 0; foreach ($pending_transactions as $txn): $total += $txn->patient_amount; ?>
                            <tr>
                                <td><?= date('d/m/Y H:i', strtotime($txn->created_at)) ?></td>
                                <td><span class="label label-default"><?= $txn->charge_type ?></span></td>
                                <td><?= $txn->charge_name ?></td>
                                <td>GHS <?= number_format($txn->gross_amount, 2) ?></td>
                                <td>GHS <?= number_format($txn->covered_amount, 2) ?></td>
                                <td><strong>GHS <?= number_format($txn->patient_amount, 2) ?></strong></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr class="info">
                                    <th colspan="5" class="text-right">Total Patient Responsibility:</th>
                                    <th>GHS <?= number_format($total, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invoices -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-file-text"></i> Invoices</h3>
                    </div>
                    <div class="box-body table-responsive">
                        <?php if (empty($invoices)): ?>
                        <p class="text-muted">No invoices found</p>
                        <?php else: ?>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Invoice No</th>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Paid</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><a href="<?= base_url('app/ebilling/invoice/' . $inv->invoice_id) ?>"><?= $inv->invoice_no ?></a></td>
                                <td><?= date('d/m/Y', strtotime($inv->created_at)) ?></td>
                                <td>GHS <?= number_format($inv->patient_amount, 2) ?></td>
                                <td>GHS <?= number_format($inv->amount_paid, 2) ?></td>
                                <td>GHS <?= number_format($inv->amount_outstanding, 2) ?></td>
                                <td>
                                    <?php
                                    $status_class = ['UNPAID' => 'danger', 'PARTIAL' => 'warning', 'PAID' => 'success'];
                                    ?>
                                    <span class="label label-<?= $status_class[$inv->payment_status] ?? 'default' ?>">
                                        <?= $inv->payment_status ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($inv->payment_status !== 'PAID'): ?>
                                    <a href="<?= base_url('app/ebilling/collect_payment/' . $inv->invoice_id) ?>" 
                                       class="btn btn-xs btn-success"><i class="fa fa-money"></i> Pay</a>
                                    <?php endif; ?>
                                    <a href="<?= base_url('app/ebilling/print_invoice/' . $inv->invoice_id) ?>" 
                                       class="btn btn-xs btn-default" target="_blank"><i class="fa fa-print"></i></a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
$(document).ready(function() {
    $('#btn_generate_invoice').click(function() {
        var visit_id = '<?= $active_visit->IO_ID ?? '' ?>';
        if (!visit_id) {
            alert('No active visit found');
            return;
        }
        var invoiceData = {visit_id: visit_id};
        invoiceData[csrfName] = csrfHash;
        $.post('<?= base_url('app/ebilling/generate_invoice') ?>', invoiceData, function(resp) {
            var data = JSON.parse(resp);
            if (data.success) {
                alert('Invoice ' + data.invoice_no + ' generated successfully');
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        });
    });
});
</script>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
