<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Reports - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .report-card { cursor: pointer; transition: all 0.3s; }
        .report-card:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.2); }
        .table td { vertical-align: middle !important; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>NHIS Reports</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis">NHIS</a></li>
                    <li class="active">Reports</li>
                </ol>
            </section>

            <section class="content">
                <!-- Date Range Filter -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-calendar"></i> Report Period</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" class="form-inline">
                            <input type="hidden" name="type" value="<?php echo isset($report_type) ? $report_type : 'claims_summary'; ?>">
                            <div class="form-group">
                                <label>From:</label>
                                <input type="date" name="from_date" class="form-control" 
                                       value="<?php echo isset($from_date) ? $from_date : date('Y-m-01'); ?>">
                            </div>
                            <div class="form-group">
                                <label>To:</label>
                                <input type="date" name="to_date" class="form-control"
                                       value="<?php echo isset($to_date) ? $to_date : date('Y-m-d'); ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-refresh"></i> Update</button>
                        </form>
                    </div>
                </div>

                <!-- Report Type Selection -->
                <div class="row">
                    <div class="col-md-3">
                        <a href="<?php echo base_url('app/nhis/reports?type=claims_summary&from_date=' . $from_date . '&to_date=' . $to_date); ?>" 
                           class="text-decoration-none">
                            <div class="small-box <?php echo $report_type == 'claims_summary' ? 'bg-blue' : 'bg-gray'; ?> report-card">
                                <div class="inner">
                                    <h4>Claims Summary</h4>
                                    <p>Daily claims overview</p>
                                </div>
                                <div class="icon"><i class="fa fa-file-text"></i></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('app/nhis/reports?type=coverage_analysis&from_date=' . $from_date . '&to_date=' . $to_date); ?>"
                           class="text-decoration-none">
                            <div class="small-box <?php echo $report_type == 'coverage_analysis' ? 'bg-green' : 'bg-gray'; ?> report-card">
                                <div class="inner">
                                    <h4>Coverage Analysis</h4>
                                    <p>By item type</p>
                                </div>
                                <div class="icon"><i class="fa fa-shield"></i></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('app/nhis/reports?type=rejection_analysis&from_date=' . $from_date . '&to_date=' . $to_date); ?>"
                           class="text-decoration-none">
                            <div class="small-box <?php echo $report_type == 'rejection_analysis' ? 'bg-red' : 'bg-gray'; ?> report-card">
                                <div class="inner">
                                    <h4>Rejection Analysis</h4>
                                    <p>Rejection reasons</p>
                                </div>
                                <div class="icon"><i class="fa fa-times-circle"></i></div>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3">
                        <a href="<?php echo base_url('app/nhis/reports?type=revenue_breakdown&from_date=' . $from_date . '&to_date=' . $to_date); ?>"
                           class="text-decoration-none">
                            <div class="small-box <?php echo $report_type == 'revenue_breakdown' ? 'bg-yellow' : 'bg-gray'; ?> report-card">
                                <div class="inner">
                                    <h4>Revenue Breakdown</h4>
                                    <p>NHIS vs Patient</p>
                                </div>
                                <div class="icon"><i class="fa fa-money"></i></div>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Report Content -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title">
                            <i class="fa fa-bar-chart"></i> 
                            <?php 
                                $titles = array(
                                    'claims_summary' => 'Claims Summary Report',
                                    'coverage_analysis' => 'Coverage Analysis Report',
                                    'rejection_analysis' => 'Rejection Analysis Report',
                                    'revenue_breakdown' => 'Revenue Breakdown Report'
                                );
                                echo isset($titles[$report_type]) ? $titles[$report_type] : 'Report';
                            ?>
                        </h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-sm btn-success" onclick="window.print()">
                                <i class="fa fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    <div class="box-body">
                        <?php if ($report_type == 'claims_summary'): ?>
                            <!-- Claims Summary Report -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th class="text-center">Total Claims</th>
                                            <th class="text-center">Approved</th>
                                            <th class="text-center">Rejected</th>
                                            <th class="text-center">Pending</th>
                                            <th class="text-right">Total Amount</th>
                                            <th class="text-right">Approved Amount</th>
                                            <th class="text-right">Co-pay</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $grandTotal = 0; $grandApproved = 0; $grandCopay = 0;
                                            if (!empty($report_data)): 
                                                foreach ($report_data as $row): 
                                                    $grandTotal += $row->total_amount;
                                                    $grandApproved += $row->approved_amount;
                                                    $grandCopay += $row->copay_amount;
                                        ?>
                                            <tr>
                                                <td><?php echo date('d M Y', strtotime($row->date)); ?></td>
                                                <td class="text-center"><?php echo $row->total_claims; ?></td>
                                                <td class="text-center text-success"><?php echo $row->approved; ?></td>
                                                <td class="text-center text-danger"><?php echo $row->rejected; ?></td>
                                                <td class="text-center text-warning"><?php echo $row->pending; ?></td>
                                                <td class="text-right">GHS <?php echo number_format($row->total_amount, 2); ?></td>
                                                <td class="text-right text-success">GHS <?php echo number_format($row->approved_amount, 2); ?></td>
                                                <td class="text-right">GHS <?php echo number_format($row->copay_amount, 2); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="active">
                                            <th colspan="5" class="text-right">Totals:</th>
                                            <th class="text-right">GHS <?php echo number_format($grandTotal, 2); ?></th>
                                            <th class="text-right text-success">GHS <?php echo number_format($grandApproved, 2); ?></th>
                                            <th class="text-right">GHS <?php echo number_format($grandCopay, 2); ?></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                        <?php elseif ($report_type == 'coverage_analysis'): ?>
                            <!-- Coverage Analysis Report -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Item Type</th>
                                            <th class="text-center">Item Count</th>
                                            <th class="text-right">Total Amount</th>
                                            <th class="text-right">NHIS Amount</th>
                                            <th class="text-right">Patient Amount</th>
                                            <th class="text-center">Avg Coverage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($report_data)): foreach ($report_data as $row): ?>
                                            <tr>
                                                <td>
                                                    <span class="label label-primary"><?php echo strtoupper($row->item_type); ?></span>
                                                </td>
                                                <td class="text-center"><?php echo $row->item_count; ?></td>
                                                <td class="text-right">GHS <?php echo number_format($row->total_amount, 2); ?></td>
                                                <td class="text-right text-success">GHS <?php echo number_format($row->nhis_amount, 2); ?></td>
                                                <td class="text-right text-warning">GHS <?php echo number_format($row->patient_amount, 2); ?></td>
                                                <td class="text-center">
                                                    <span class="label label-info"><?php echo number_format($row->avg_coverage, 1); ?>%</span>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>

                        <?php elseif ($report_type == 'rejection_analysis'): ?>
                            <!-- Rejection Analysis Report -->
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Rejection Reason</th>
                                            <th class="text-center">Count</th>
                                            <th>Percentage</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php 
                                            $totalRejections = 0;
                                            if (!empty($report_data)) {
                                                foreach ($report_data as $row) {
                                                    $totalRejections += $row->count;
                                                }
                                            }
                                            if (!empty($report_data)): foreach ($report_data as $row): 
                                                $pct = $totalRejections > 0 ? ($row->count / $totalRejections * 100) : 0;
                                        ?>
                                            <tr>
                                                <td><?php echo $row->rejection_reason; ?></td>
                                                <td class="text-center"><?php echo $row->count; ?></td>
                                                <td>
                                                    <div class="progress" style="margin-bottom: 0;">
                                                        <div class="progress-bar progress-bar-danger" style="width: <?php echo $pct; ?>%">
                                                            <?php echo number_format($pct, 1); ?>%
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="active">
                                            <th>Total Rejections</th>
                                            <th class="text-center"><?php echo $totalRejections; ?></th>
                                            <th></th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                        <?php elseif ($report_type == 'revenue_breakdown'): ?>
                            <!-- Revenue Breakdown Report -->
                            <?php $totals = isset($report_data['totals']) ? $report_data['totals'] : null; ?>
                            <?php $byType = isset($report_data['by_type']) ? $report_data['by_type'] : array(); ?>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="info-box bg-blue">
                                        <span class="info-box-icon"><i class="fa fa-money"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Total Revenue</span>
                                            <span class="info-box-number">
                                                GHS <?php echo $totals ? number_format($totals->total, 2) : '0.00'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-box bg-green">
                                        <span class="info-box-icon"><i class="fa fa-hospital-o"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">NHIS Revenue</span>
                                            <span class="info-box-number">
                                                GHS <?php echo $totals ? number_format($totals->nhis_revenue, 2) : '0.00'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="info-box bg-yellow">
                                        <span class="info-box-icon"><i class="fa fa-user"></i></span>
                                        <div class="info-box-content">
                                            <span class="info-box-text">Patient Revenue</span>
                                            <span class="info-box-number">
                                                GHS <?php echo $totals ? number_format($totals->patient_revenue, 2) : '0.00'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <h4>By Encounter Type</h4>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Encounter Type</th>
                                            <th class="text-center">Claims</th>
                                            <th class="text-right">Total</th>
                                            <th class="text-right">NHIS</th>
                                            <th class="text-right">Patient</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($byType)): foreach ($byType as $row): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    // Handle both encounter_type and status fields (model returns status)
                                                    $enc_type = isset($row->encounter_type) ? $row->encounter_type : (isset($row->status) ? $row->status : 'OPD');
                                                    $label_class = 'default';
                                                    if ($enc_type == 'IPD') $label_class = 'info';
                                                    elseif ($enc_type == 'approved' || $enc_type == 'paid') $label_class = 'success';
                                                    elseif ($enc_type == 'pending' || $enc_type == 'submitted') $label_class = 'warning';
                                                    elseif ($enc_type == 'rejected' || $enc_type == 'cancelled') $label_class = 'danger';
                                                    ?>
                                                    <span class="label label-<?php echo $label_class; ?>">
                                                        <?php echo ucfirst($enc_type); ?>
                                                    </span>
                                                </td>
                                                <td class="text-center"><?php echo isset($row->claims) ? $row->claims : 0; ?></td>
                                                <td class="text-right">GHS <?php echo number_format(isset($row->total) ? $row->total : 0, 2); ?></td>
                                                <td class="text-right text-success">GHS <?php echo number_format(isset($row->nhis) ? $row->nhis : 0, 2); ?></td>
                                                <td class="text-right text-warning">GHS <?php echo number_format(isset($row->patient) ? $row->patient : 0, 2); ?></td>
                                            </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($report_data) || (is_array($report_data) && count($report_data) == 0)): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No data available for the selected period.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
