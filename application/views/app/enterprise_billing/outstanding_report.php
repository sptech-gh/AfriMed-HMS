<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Outstanding Balances</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Outstanding Balances</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Outstanding</li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Unpaid Invoices</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped" id="outstanding_table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Patient</th>
                            <th>Phone</th>
                            <th>Invoice Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $total_outstanding = 0; foreach ($outstanding as $o): $total_outstanding += $o->amount_outstanding; ?>
                    <tr>
                        <td><a href="<?= base_url('app/ebilling/invoice/' . $o->invoice_id) ?>"><?= $o->invoice_no ?></a></td>
                        <td><?= $o->firstname ?> <?= $o->lastname ?></td>
                        <td><?= $o->phone ?></td>
                        <td><?= date('d/m/Y', strtotime($o->created_at)) ?></td>
                        <td>GHS <?= number_format($o->patient_amount, 2) ?></td>
                        <td>GHS <?= number_format($o->amount_paid, 2) ?></td>
                        <td><strong class="text-danger">GHS <?= number_format($o->amount_outstanding, 2) ?></strong></td>
                        <td>
                            <a href="<?= base_url('app/ebilling/collect_payment/' . $o->invoice_id) ?>" 
                               class="btn btn-xs btn-success"><i class="fa fa-money"></i> Collect</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="danger">
                            <th colspan="6">TOTAL OUTSTANDING</th>
                            <th colspan="2">GHS <?= number_format($total_outstanding, 2) ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
<script>
$(document).ready(function() {
    $('#outstanding_table').DataTable({order: [[6, 'desc']], pageLength: 25});
});
</script>
</body>
</html>
