<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — Revenue Report</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
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
                <section class="content-header">
                    <h1><i class="fa fa-money"></i> Revenue Report <small>By Department &amp; Date</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/ghs_reports">GHS Reports</a></li>
                        <li class="active">Revenue</li>
                    </ol>
                </section>

                <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3></div>
            <form method="GET" action="<?php echo base_url(); ?>app/ghs_reports/revenue" class="box-body">
                <div class="row">
                    <div class="col-md-3"><label>From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                    <div class="col-md-3"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Generate</button></div>
                    <div class="col-md-3"><label>&nbsp;</label>
                        <div class="btn-group btn-group-justified">
                            <a href="<?php echo base_url(); ?>app/ghs_reports/export_csv?report=revenue&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</a>
                            <a href="javascript:window.print()" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php $t = isset($totals) ? $totals : array('total_revenue' => 0, 'total_invoices' => 0); ?>

        <!-- Summary -->
        <div class="row">
            <div class="col-md-6">
                <div class="small-box bg-green">
                    <div class="inner"><h3>GH&cent; <?php echo number_format($t['total_revenue'], 2); ?></h3><p>Total Revenue</p></div>
                    <div class="icon"><i class="fa fa-money"></i></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="small-box bg-aqua">
                    <div class="inner"><h3><?php echo number_format($t['total_invoices']); ?></h3><p>Total Invoices</p></div>
                    <div class="icon"><i class="fa fa-file-text-o"></i></div>
                </div>
            </div>
        </div>

        <!-- Revenue by Category -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border"><h3 class="box-title">Revenue by Department</h3></div>
                    <div class="box-body">
                        <table class="table table-bordered">
                            <thead><tr class="bg-green"><th>Department</th><th>Revenue (GH&cent;)</th><th>%</th></tr></thead>
                            <tbody>
                                <?php
                                $cats = isset($by_category) ? $by_category : array();
                                $grandTotal = $t['total_revenue'] > 0 ? $t['total_revenue'] : 1;
                                $icons = array('Consultation' => 'fa-stethoscope', 'Pharmacy' => 'fa-flask', 'Laboratory' => 'fa-flask', 'Imaging' => 'fa-film', 'Other' => 'fa-ellipsis-h');
                                $colors = array('Consultation' => 'bg-aqua', 'Pharmacy' => 'bg-yellow', 'Laboratory' => 'bg-green', 'Imaging' => 'bg-purple', 'Other' => 'bg-gray');
                                foreach ($cats as $cat => $amt) {
                                    $pct = round(($amt / $grandTotal) * 100, 1);
                                ?>
                                <tr>
                                    <td><i class="fa <?php echo isset($icons[$cat]) ? $icons[$cat] : 'fa-circle'; ?>"></i> <strong><?php echo $cat; ?></strong></td>
                                    <td>GH&cent; <?php echo number_format($amt, 2); ?></td>
                                    <td>
                                        <div class="progress progress-xs" style="margin-bottom:0">
                                            <div class="progress-bar progress-bar-success" style="width:<?php echo $pct; ?>%"></div>
                                        </div>
                                        <small><?php echo $pct; ?>%</small>
                                    </td>
                                </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-info">
                    <div class="box-header with-border"><h3 class="box-title">Department Breakdown</h3></div>
                    <div class="box-body">
                        <?php foreach ($cats as $cat => $amt) {
                            $pct = round(($amt / $grandTotal) * 100, 1);
                            $col = isset($colors[$cat]) ? $colors[$cat] : 'bg-gray';
                        ?>
                        <div class="progress-group">
                            <span class="progress-text"><?php echo $cat; ?></span>
                            <span class="progress-number">GH&cent; <?php echo number_format($amt, 2); ?></span>
                            <div class="progress"><div class="progress-bar <?php echo str_replace('bg-', 'progress-bar-', $col); ?>" style="width:<?php echo $pct; ?>%"></div></div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Daily Revenue -->
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Daily Revenue — <?php echo $date_from; ?> to <?php echo $date_to; ?></h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="tblRevenue">
                    <thead><tr class="bg-red"><th>Date</th><th>Revenue (GH&cent;)</th><th>Invoices</th></tr></thead>
                    <tbody>
                        <?php if (isset($daily)) foreach ($daily as $d) { ?>
                        <tr>
                            <td><?php echo date('d M Y (D)', strtotime($d->bill_date)); ?></td>
                            <td>GH&cent; <?php echo number_format((float)$d->total, 2); ?></td>
                            <td><?php echo (int)$d->invoices; ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-red"><th>TOTAL</th><th>GH&cent; <?php echo number_format($t['total_revenue'], 2); ?></th><th><?php echo number_format($t['total_invoices']); ?></th></tr>
                    </tfoot>
                </table>
            </div>
        </div>
                </section>
            </aside>
        </div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
        <script>$(function(){ if($.fn.DataTable){ $('#tblRevenue').DataTable({"pageLength":31,"order":[[0,"asc"]]}); } });</script>
    </body>
</html>
