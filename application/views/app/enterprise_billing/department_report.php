<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Department Revenue</title>
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
        <h1>Department Revenue Report</h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Department Report</li>
        </ol>
    </section>

    <section class="content">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">Date Range</h3>
            </div>
            <div class="box-body">
                <form method="get" class="form-inline">
                    <div class="form-group">
                        <label>From:</label>
                        <input type="date" name="from" class="form-control" value="<?= $date_from ?>">
                    </div>
                    <div class="form-group">
                        <label>To:</label>
                        <input type="date" name="to" class="form-control" value="<?= $date_to ?>">
                    </div>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </form>
            </div>
        </div>

        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Revenue by Department</h3>
            </div>
            <div class="box-body table-responsive">
                <table class="table table-striped" id="revenue_table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Charge Type</th>
                            <th>Transactions</th>
                            <th>Gross Revenue</th>
                            <th>Covered</th>
                            <th>Patient Revenue</th>
                            <th>Collected</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php 
                    $totals = ['txn' => 0, 'gross' => 0, 'covered' => 0, 'patient' => 0, 'collected' => 0];
                    foreach ($revenue as $r): 
                        $totals['txn'] += $r->transaction_count;
                        $totals['gross'] += $r->gross_revenue;
                        $totals['covered'] += $r->covered_revenue;
                        $totals['patient'] += $r->patient_revenue;
                        $totals['collected'] += $r->collected;
                    ?>
                    <tr>
                        <td><?= $r->department ?></td>
                        <td><span class="label label-default"><?= $r->charge_type ?></span></td>
                        <td><?= $r->transaction_count ?></td>
                        <td>GHS <?= number_format($r->gross_revenue, 2) ?></td>
                        <td>GHS <?= number_format($r->covered_revenue, 2) ?></td>
                        <td>GHS <?= number_format($r->patient_revenue, 2) ?></td>
                        <td>GHS <?= number_format($r->collected, 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="info">
                            <th colspan="2">TOTAL</th>
                            <th><?= $totals['txn'] ?></th>
                            <th>GHS <?= number_format($totals['gross'], 2) ?></th>
                            <th>GHS <?= number_format($totals['covered'], 2) ?></th>
                            <th>GHS <?= number_format($totals['patient'], 2) ?></th>
                            <th>GHS <?= number_format($totals['collected'], 2) ?></th>
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
    $('#revenue_table').DataTable({pageLength: 25});
});
</script>
</body>
</html>
