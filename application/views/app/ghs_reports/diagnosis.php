<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Hebrew Medical Center — Diagnosis Report</title>
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
                    <h1><i class="fa fa-medkit"></i> Diagnosis Report <small>Top Morbidity — GHS Standard</small></h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                        <li><a href="<?php echo base_url(); ?>app/ghs_reports">GHS Reports</a></li>
                        <li class="active">Diagnosis</li>
                    </ol>
                </section>

                <section class="content">
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3></div>
            <form method="GET" action="<?php echo base_url(); ?>app/ghs_reports/diagnosis" class="box-body">
                <div class="row">
                    <div class="col-md-3"><label>From</label><input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>"></div>
                    <div class="col-md-3"><label>To</label><input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>"></div>
                    <div class="col-md-3"><label>&nbsp;</label><button type="submit" class="btn btn-primary btn-block"><i class="fa fa-search"></i> Generate</button></div>
                    <div class="col-md-3"><label>&nbsp;</label>
                        <div class="btn-group btn-group-justified">
                            <a href="<?php echo base_url(); ?>app/ghs_reports/export_csv?report=diagnosis&date_from=<?php echo $date_from; ?>&date_to=<?php echo $date_to; ?>" class="btn btn-success"><i class="fa fa-file-excel-o"></i> Export</a>
                            <a href="javascript:window.print()" class="btn btn-default"><i class="fa fa-print"></i> Print</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <div class="box box-success">
            <div class="box-header with-border"><h3 class="box-title">Top Diagnoses — <?php echo $date_from; ?> to <?php echo $date_to; ?></h3></div>
            <div class="box-body table-responsive">
                <table class="table table-bordered table-striped" id="tblDiag">
                    <thead>
                        <tr class="bg-green">
                            <th>#</th>
                            <th>Diagnosis</th>
                            <th>Total Cases</th>
                            <th>Male</th>
                            <th>Female</th>
                            <th>Under 5</th>
                            <th>Age 5-17</th>
                            <th>18+</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $idx = 0; if (isset($diagnoses)) foreach ($diagnoses as $d) { $idx++; ?>
                        <tr>
                            <td><?php echo $idx; ?></td>
                            <td><strong><?php echo htmlspecialchars($d->diagnosis_name); ?></strong></td>
                            <td><span class="badge bg-green"><?php echo (int)$d->total_cases; ?></span></td>
                            <td><?php echo (int)$d->male; ?></td>
                            <td><?php echo (int)$d->female; ?></td>
                            <td><?php echo (int)$d->under_5; ?></td>
                            <td><?php echo (int)$d->age_5_17; ?></td>
                            <td><?php echo (int)$d->age_18_plus; ?></td>
                        </tr>
                        <?php } ?>
                        <?php if ($idx === 0) { ?>
                        <tr><td colspan="8" class="text-center text-muted">No diagnosis data for this period.</td></tr>
                        <?php } ?>
                    </tbody>
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
        <script>$(function(){ if($.fn.DataTable){ $('#tblDiag').DataTable({"pageLength":30,"order":[[2,"desc"]]}); } });</script>
    </body>
</html>
