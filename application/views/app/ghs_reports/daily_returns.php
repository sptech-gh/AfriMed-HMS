<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — Daily Returns</title>
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
                    <h1><i class="fa fa-calendar-check-o"></i> Daily Returns Report <small>GHS Standard Summary</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/ghs_reports">GHS Reports</a></li>
                        <li class="active">Daily Returns</li>
                    </ol>
                </section>

                <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3></div>
            <form method="GET" action="<?php echo base_url(); ?>app/ghs_reports/daily_returns" class="box-body">
                <div class="row">
                    <div class="col-md-3"><label>From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                    <div class="col-md-3"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Generate</button></div>
                    <div class="col-md-3"><label>&nbsp;</label>
                        <div class="btn-group btn-group-justified">
                            <a href="<?php echo base_url(); ?>app/ghs_reports/export_csv?report=daily_returns&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</a>
                            <a href="javascript:window.print()" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title">Daily Returns — <?php echo $date_from; ?> to <?php echo $date_to; ?></h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="tblReturns">
                    <thead>
                        <tr class="bg-teal">
                            <th>Date</th>
                            <th>OPD Attendance</th>
                            <th>Admissions</th>
                            <th>Discharges</th>
                            <th>Deaths</th>
                            <th>Revenue (GH&cent;)</th>
                            <th>Payments (GH&cent;)</th>
                            <th>Prescriptions</th>
                            <th>Lab Tests</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $tOPD = $tAdm = $tDis = $tDeath = $tRev = $tPay = $tRx = $tLab = 0;
                        if (isset($returns)) foreach ($returns as $r) {
                            $tOPD   += $r['opd_attendance'];
                            $tAdm   += $r['admissions'];
                            $tDis   += $r['discharges'];
                            $tDeath += $r['deaths'];
                            $tRev   += $r['revenue'];
                            $tPay   += $r['payments'];
                            $tRx    += $r['prescriptions'];
                            $tLab   += $r['lab_tests'];
                        ?>
                        <tr>
                            <td><?php echo date('d M Y (D)', strtotime($r['date'])); ?></td>
                            <td><?php echo $r['opd_attendance']; ?></td>
                            <td><?php echo $r['admissions']; ?></td>
                            <td><?php echo $r['discharges']; ?></td>
                            <td><?php echo $r['deaths'] > 0 ? '<span class="text-red"><strong>' . $r['deaths'] . '</strong></span>' : '0'; ?></td>
                            <td>GH&cent; <?php echo number_format($r['revenue'], 2); ?></td>
                            <td>GH&cent; <?php echo number_format($r['payments'], 2); ?></td>
                            <td><?php echo $r['prescriptions']; ?></td>
                            <td><?php echo $r['lab_tests']; ?></td>
                        </tr>
                        <?php } ?>
                    </tbody>
                    <tfoot>
                        <tr class="bg-teal">
                            <th>TOTALS</th>
                            <th><?php echo number_format($tOPD); ?></th>
                            <th><?php echo number_format($tAdm); ?></th>
                            <th><?php echo number_format($tDis); ?></th>
                            <th><?php echo number_format($tDeath); ?></th>
                            <th>GH&cent; <?php echo number_format($tRev, 2); ?></th>
                            <th>GH&cent; <?php echo number_format($tPay, 2); ?></th>
                            <th><?php echo number_format($tRx); ?></th>
                            <th><?php echo number_format($tLab); ?></th>
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
        <script>$(function(){ if($.fn.DataTable){ $('#tblReturns').DataTable({"pageLength":31,"order":[[0,"asc"]]}); } });</script>
    </body>
</html>
