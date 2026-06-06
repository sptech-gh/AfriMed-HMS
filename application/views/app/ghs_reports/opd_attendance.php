<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — OPD Attendance Report</title>
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
                    <h1><i class="fa fa-stethoscope"></i> OPD Attendance Report <small>GHS Standard</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/ghs_reports">GHS Reports</a></li>
                        <li class="active">OPD Attendance</li>
                    </ol>
                </section>

                <section class="content">
        <!-- Date Filter -->
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3></div>
            <form method="GET" action="<?php echo base_url(); ?>app/ghs_reports/opd_attendance" class="box-body">
                <div class="row">
                    <div class="col-md-3">
                        <label>From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label>To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Generate</button>
                    </div>
                    <div class="col-md-3">
                        <label>&nbsp;</label>
                        <div class="btn-group btn-group-justified">
                            <a href="<?php echo base_url(); ?>app/ghs_reports/export_csv?report=opd_attendance&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</a>
                            <a href="javascript:window.print()" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary Boxes -->
        <div class="row">
            <?php $s = isset($summary) ? $summary : array('total'=>0,'male'=>0,'female'=>0,'children'=>0,'adults'=>0); ?>
            <div class="col-md-2 col-sm-4"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fa fa-users"></i></span><div class="info-box-content"><span class="info-box-text">Total</span><span class="info-box-number"><?php echo number_format($s['total']); ?></span></div></div></div>
            <div class="col-md-2 col-sm-4"><div class="info-box"><span class="info-box-icon bg-blue"><i class="fa fa-male"></i></span><div class="info-box-content"><span class="info-box-text">Male</span><span class="info-box-number"><?php echo number_format($s['male']); ?></span></div></div></div>
            <div class="col-md-2 col-sm-4"><div class="info-box"><span class="info-box-icon bg-fuchsia"><i class="fa fa-female"></i></span><div class="info-box-content"><span class="info-box-text">Female</span><span class="info-box-number"><?php echo number_format($s['female']); ?></span></div></div></div>
            <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-child"></i></span><div class="info-box-content"><span class="info-box-text">Children (&lt;18)</span><span class="info-box-number"><?php echo number_format($s['children']); ?></span></div></div></div>
            <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-user"></i></span><div class="info-box-content"><span class="info-box-text">Adults (18+)</span><span class="info-box-number"><?php echo number_format($s['adults']); ?></span></div></div></div>
        </div>

        <!-- Data Table -->
        <div class="box box-success" id="printArea">
            <div class="box-header with-border"><h3 class="box-title">OPD Attendance — <?php echo $date_from; ?> to <?php echo $date_to; ?></h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="tblOPD">
                    <thead>
                        <tr class="bg-primary">
                            <th>Date</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Children (&lt;18)</th>
                            <th>Adults (18+)</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($rows)) foreach ($rows as $r) { ?>
                        <tr>
                            <td><?php echo date('d M Y (D)', strtotime($r->visit_date)); ?></td>
                            <td><?php echo (int)$r->male; ?></td>
                            <td><?php echo (int)$r->female; ?></td>
                            <td><?php echo (int)$r->children; ?></td>
                            <td><?php echo (int)$r->adults; ?></td>
                            <td><strong><?php echo (int)$r->total; ?></strong></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-primary">
                            <th>TOTALS</th>
                            <th><?php echo number_format($s['male']); ?></th>
                            <th><?php echo number_format($s['female']); ?></th>
                            <th><?php echo number_format($s['children']); ?></th>
                            <th><?php echo number_format($s['adults']); ?></th>
                            <th><?php echo number_format($s['total']); ?></th>
                        </tr>
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
        <script>$(function(){ if($.fn.DataTable){ $('#tblOPD').DataTable({"pageLength":31,"order":[[0,"asc"]]}); } });</script>
    </body>
</html>
