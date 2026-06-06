<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Billing Reports - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url() ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <style>
        .report-card { border-radius: 5px; padding: 20px; margin-bottom: 15px; color: #fff; text-align: center; }
        .report-card h2 { margin: 0 0 5px 0; font-size: 32px; }
        .report-card p { margin: 0; font-size: 14px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH . 'views/include/header.php'); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-bar-chart"></i> Billing Reports <small>Service Billing Summary</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url() ?>app/service_queue">Service Queue</a></li>
                    <li class="active">Reports</li>
                </ol>
            </section>

            <section class="content">
                <!-- Date Filter -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-default">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-filter"></i> Date Range</h3>
                            </div>
                            <div class="box-body">
                                <form method="get" class="form-inline">
                                    <div class="form-group">
                                        <label>From:</label>
                                        <input type="date" name="date_from" class="form-control" value="<?php echo isset($date_from) ? $date_from : date('Y-m-01'); ?>">
                                    </div>
                                    <div class="form-group" style="margin-left: 15px;">
                                        <label>To:</label>
                                        <input type="date" name="date_to" class="form-control" value="<?php echo isset($date_to) ? $date_to : date('Y-m-d'); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="margin-left: 15px;"><i class="fa fa-search"></i> Filter</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Summary by Coverage Type -->
                <div class="row">
                    <?php
                    $coverageColors = array(
                        'CASH' => '#f39c12',
                        'NHIS' => '#00a65a',
                        'INSURANCE' => '#3c8dbc',
                        'COMPANY' => '#605ca8'
                    );
                    $totalBilled = 0;
                    $totalPatient = 0;
                    $totalCovered = 0;
                    if (isset($summary) && is_array($summary)) {
                        foreach ($summary as $s) {
                            $totalBilled += (float)$s->total;
                            $totalPatient += (float)$s->patient_total;
                            $totalCovered += (float)$s->covered_total;
                        }
                    }
                    ?>
                    <?php if (isset($summary) && count($summary) > 0) { ?>
                        <?php foreach ($summary as $s) { 
                            $color = isset($coverageColors[$s->coverage_type]) ? $coverageColors[$s->coverage_type] : '#777';
                        ?>
                        <div class="col-md-3">
                            <div class="report-card" style="background: <?php echo $color; ?>;">
                                <h2><?php echo (int)$s->count; ?></h2>
                                <p><?php echo $s->coverage_type; ?> Orders</p>
                                <hr style="border-color: rgba(255,255,255,0.3); margin: 10px 0;">
                                <p>Total: GHS <?php echo number_format($s->total, 2); ?></p>
                                <p>Patient: GHS <?php echo number_format($s->patient_total, 2); ?></p>
                                <p>Covered: GHS <?php echo number_format($s->covered_total, 2); ?></p>
                            </div>
                        </div>
                        <?php } ?>
                    <?php } else { ?>
                        <div class="col-md-12">
                            <div class="alert alert-info">No billing data for selected period</div>
                        </div>
                    <?php } ?>
                </div>

                <!-- Totals Row -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-box bg-aqua">
                            <span class="info-box-icon"><i class="fa fa-calculator"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total Billed</span>
                                <span class="info-box-number">GHS <?php echo number_format($totalBilled, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box bg-yellow">
                            <span class="info-box-icon"><i class="fa fa-user"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Patient Payments</span>
                                <span class="info-box-number">GHS <?php echo number_format($totalPatient, 2); ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-shield"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Covered Amount</span>
                                <span class="info-box-number">GHS <?php echo number_format($totalCovered, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Outstanding Bills -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Outstanding Bills</h3>
                            </div>
                            <div class="box-body table-responsive">
                                <table id="tblOutstanding" class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Order #</th>
                                            <th>Patient</th>
                                            <th>Service</th>
                                            <th>Type</th>
                                            <th>Coverage</th>
                                            <th>Amount Due</th>
                                            <th>Date</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (isset($outstanding) && count($outstanding) > 0) { ?>
                                            <?php foreach ($outstanding as $o) { ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($o->order_no); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars(trim($o->firstname . ' ' . $o->lastname)); ?>
                                                    <br><small class="text-muted"><?php echo $o->patient_no; ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($o->service_name); ?></td>
                                                <td><span class="label label-default"><?php echo $o->service_type; ?></span></td>
                                                <td><?php echo $o->coverage_type; ?></td>
                                                <td class="text-right"><strong>GHS <?php echo number_format($o->patient_amount, 2); ?></strong></td>
                                                <td><?php echo date('M d, Y', strtotime($o->created_at)); ?></td>
                                            </tr>
                                            <?php } ?>
                                        <?php } else { ?>
                                            <tr><td colspan="7" class="text-center text-success"><i class="fa fa-check"></i> No outstanding bills</td></tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
    <script src="<?php echo base_url(); ?>public/js/datatables/jquery.dataTables.js"></script>
    <script src="<?php echo base_url(); ?>public/js/datatables/dataTables.bootstrap.js"></script>
    <script>
    $(function() {
        $('#tblOutstanding').DataTable({"pageLength": 25, "order": [[6, "asc"]]});
    });
    </script>
</body>
</html>
