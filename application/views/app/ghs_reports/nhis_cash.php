<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — NHIS vs Cash Report</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
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
                <section class="content-header">
                    <h1><i class="fa fa-shield"></i> NHIS vs Cash Report <small>Payer Analysis</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/ghs_reports">GHS Reports</a></li>
                        <li class="active">NHIS vs Cash</li>
                    </ol>
                </section>

                <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3></div>
            <form method="GET" action="<?php echo base_url(); ?>app/ghs_reports/nhis_cash" class="box-body">
                <div class="row">
                    <div class="col-md-3"><label>From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                    <div class="col-md-3"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Generate</button></div>
                    <div class="col-md-3"><label>&nbsp;</label>
                        <div class="btn-group btn-group-justified">
                            <a href="<?php echo base_url(); ?>app/ghs_reports/export_csv?report=nhis_cash&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</a>
                            <a href="javascript:window.print()" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <?php $r = isset($report) ? $report : array('nhis_patients'=>0,'cash_patients'=>0,'nhis_revenue'=>0,'cash_revenue'=>0,'total_revenue'=>0,'nhis_visits'=>0,'cash_visits'=>0,'monthly'=>array()); ?>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-3">
                <div class="small-box bg-green">
                    <div class="inner"><h3><?php echo number_format($r['nhis_patients']); ?></h3><p>NHIS Patients</p></div>
                    <div class="icon"><i class="fa fa-shield"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-blue">
                    <div class="inner"><h3><?php echo number_format($r['cash_patients']); ?></h3><p>Cash Patients</p></div>
                    <div class="icon"><i class="fa fa-money"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-purple">
                    <div class="inner"><h3>GH&cent; <?php echo number_format($r['nhis_revenue'], 2); ?></h3><p>NHIS Revenue</p></div>
                    <div class="icon"><i class="fa fa-medkit"></i></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="small-box bg-orange">
                    <div class="inner"><h3>GH&cent; <?php echo number_format($r['cash_revenue'], 2); ?></h3><p>Cash Revenue</p></div>
                    <div class="icon"><i class="fa fa-money"></i></div>
                </div>
            </div>
        </div>

        <!-- Summary Table -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border"><h3 class="box-title">Summary</h3></div>
                    <div class="box-body">
                        <table class="table table-bordered">
                            <thead><tr class="bg-purple"><th>Metric</th><th>NHIS</th><th>Cash</th><th>Total</th></tr></thead>
                            <tbody>
                                <tr>
                                    <td><strong>Patients</strong></td>
                                    <td><?php echo number_format($r['nhis_patients']); ?></td>
                                    <td><?php echo number_format($r['cash_patients']); ?></td>
                                    <td><strong><?php echo number_format($r['nhis_patients'] + $r['cash_patients']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Visits/Invoices</strong></td>
                                    <td><?php echo number_format($r['nhis_visits']); ?></td>
                                    <td><?php echo number_format($r['cash_visits']); ?></td>
                                    <td><strong><?php echo number_format($r['nhis_visits'] + $r['cash_visits']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><strong>Revenue</strong></td>
                                    <td>GH&cent; <?php echo number_format($r['nhis_revenue'], 2); ?></td>
                                    <td>GH&cent; <?php echo number_format($r['cash_revenue'], 2); ?></td>
                                    <td><strong>GH&cent; <?php echo number_format($r['total_revenue'], 2); ?></strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-info">
                    <div class="box-header with-border"><h3 class="box-title">Revenue Split</h3></div>
                    <div class="box-body">
                        <?php
                        $total = $r['total_revenue'] > 0 ? $r['total_revenue'] : 1;
                        $nhisPct = round(($r['nhis_revenue'] / $total) * 100, 1);
                        $cashPct = round(($r['cash_revenue'] / $total) * 100, 1);
                        ?>
                        <div class="progress-group">
                            <span class="progress-text"><i class="fa fa-shield text-green"></i> NHIS</span>
                            <span class="progress-number"><strong><?php echo $nhisPct; ?>%</strong></span>
                            <div class="progress"><div class="progress-bar progress-bar-green" style="width:<?php echo $nhisPct; ?>%"></div></div>
                        </div>
                        <div class="progress-group">
                            <span class="progress-text"><i class="fa fa-money text-blue"></i> Cash</span>
                            <span class="progress-number"><strong><?php echo $cashPct; ?>%</strong></span>
                            <div class="progress"><div class="progress-bar progress-bar-primary" style="width:<?php echo $cashPct; ?>%"></div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Breakdown -->
        <?php if (isset($r['monthly']) && count($r['monthly']) > 0) { ?>
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Monthly Breakdown</h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr class="bg-purple"><th>Month</th><th>NHIS Invoices</th><th>NHIS Revenue</th><th>Cash Invoices</th><th>Cash Revenue</th></tr></thead>
                    <tbody>
                        <?php foreach ($r['monthly'] as $m) { ?>
                        <tr>
                            <td><?php echo $m->month; ?></td>
                            <td><?php echo number_format($m->nhis_invoices); ?></td>
                            <td>GH&cent; <?php echo number_format($m->nhis_revenue, 2); ?></td>
                            <td><?php echo number_format($m->cash_invoices); ?></td>
                            <td>GH&cent; <?php echo number_format($m->cash_revenue, 2); ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php } ?>

                </section>
            </aside>
        </div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    </body>
</html>
