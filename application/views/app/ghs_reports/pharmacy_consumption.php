<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — Pharmacy Consumption</title>
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
                    <h1><i class="fa fa-flask"></i> Pharmacy Consumption Report <small>Drug Accountability — GHS Standard</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/ghs_reports">GHS Reports</a></li>
                        <li class="active">Pharmacy Consumption</li>
                    </ol>
                </section>

                <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3></div>
            <form method="GET" action="<?php echo base_url(); ?>app/ghs_reports/pharmacy_consumption" class="box-body">
                <div class="row">
                    <div class="col-md-3"><label>From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                    <div class="col-md-3"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Generate</button></div>
                    <div class="col-md-3"><label>&nbsp;</label>
                        <div class="btn-group btn-group-justified">
                            <a href="<?php echo base_url(); ?>app/ghs_reports/export_csv?report=pharmacy_consumption&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</a>
                            <a href="javascript:window.print()" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">Pharmacy Consumption — <?php echo $date_from; ?> to <?php echo $date_to; ?></h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="tblPharm">
                    <thead>
                        <tr class="bg-yellow">
                            <th>#</th>
                            <th>Drug Name</th>
                            <th>Opening Balance</th>
                            <th>Received</th>
                            <th>Dispensed</th>
                            <th>Closing Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $idx = 0;
                        $totOpen = $totRec = $totDisp = $totClose = 0;
                        if (isset($drugs)) foreach ($drugs as $d) {
                            $idx++;
                            $totOpen += $d->opening;
                            $totRec += $d->received;
                            $totDisp += $d->dispensed;
                            $totClose += $d->closing;
                        ?>
                        <tr>
                            <td><?php echo $idx; ?></td>
                            <td><strong><?php echo htmlspecialchars($d->drug_name); ?></strong></td>
                            <td><?php echo number_format($d->opening); ?></td>
                            <td><span class="text-green"><?php echo number_format($d->received); ?></span></td>
                            <td><span class="text-red"><?php echo number_format($d->dispensed); ?></span></td>
                            <td><strong><?php echo number_format($d->closing); ?></strong></td>
                        </tr>
                        <?php } ?>
                        <?php if ($idx === 0) { ?>
                        <tr><td colspan="6" class="text-center text-muted">No pharmacy activity for this period.</td></tr>
                        <?php } ?>
                    </tbody>
                    <?php if ($idx > 0) { ?>
                    <tfoot>
                        <tr class="bg-yellow">
                            <th colspan="2">TOTALS</th>
                            <th><?php echo number_format($totOpen); ?></th>
                            <th><?php echo number_format($totRec); ?></th>
                            <th><?php echo number_format($totDisp); ?></th>
                            <th><?php echo number_format($totClose); ?></th>
                        </tr>
                    </tfoot>
                    <?php } ?>
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
        <script>$(function(){ if($.fn.DataTable){ $('#tblPharm').DataTable({"pageLength":50,"order":[[1,"asc"]]}); } });</script>
    </body>
</html>
