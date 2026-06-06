<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Hebrew Medical Center — NHIS Claims Dashboard</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="<?php echo base_url(); ?>public/datepicker/css/datepicker.css">
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-medkit"></i> NHIS Claims Dashboard <small>Monitor &amp; Reconcile</small></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                    <li class="active">NHIS Claims</li>
                </ol>
            </section>

            <section class="content">
                <?php if(isset($message) && $message){ echo $message; } ?>

                <?php
                    $s = isset($stats) ? $stats : array();
                    $totalClaims = isset($s['total_claims']) ? (int)$s['total_claims'] : 0;
                    $totalNhisAmt = isset($s['total_nhis_amount']) ? (float)$s['total_nhis_amount'] : 0;
                    $totalApproved = isset($s['total_approved_amount']) ? (float)$s['total_approved_amount'] : 0;
                    $totalShortfall = isset($s['total_shortfall']) ? (float)$s['total_shortfall'] : 0;
                    $pending = isset($s['pending']) ? (int)$s['pending'] : 0;
                    $submitted = isset($s['submitted']) ? (int)$s['submitted'] : 0;
                    $approved = isset($s['approved']) ? (int)$s['approved'] : 0;
                    $rejected = isset($s['rejected']) ? (int)$s['rejected'] : 0;
                    $matched = isset($s['matched']) ? (int)$s['matched'] : 0;
                    $underpaid = isset($s['underpaid']) ? (int)$s['underpaid'] : 0;
                    $reconRejected = isset($s['recon_rejected']) ? (int)$s['recon_rejected'] : 0;
                    $alertCounts = isset($alerts) ? $alerts : array('underpaid'=>0,'rejected'=>0,'total_alerts'=>0);
                    $apiCfg = isset($api_config) ? $api_config : array();
                    $apiMode = isset($apiCfg['api_mode']) ? $apiCfg['api_mode'] : 'MOCK';
                ?>

                <!-- Alerts Banner -->
                <?php if($alertCounts['total_alerts'] > 0): ?>
                <div class="alert alert-warning alert-dismissable" style="border-left: 4px solid #f39c12;">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="fa fa-exclamation-triangle"></i>
                    <strong>NHIS Alerts:</strong>
                    <?php if($alertCounts['underpaid'] > 0): ?>
                        <span class="label label-warning"><?php echo $alertCounts['underpaid']; ?> Underpaid</span>
                    <?php endif; ?>
                    <?php if($alertCounts['rejected'] > 0): ?>
                        <span class="label label-danger"><?php echo $alertCounts['rejected']; ?> Rejected</span>
                    <?php endif; ?>
                    — Review claims requiring attention.
                </div>
                <?php endif; ?>

                <!-- API Mode Indicator -->
                <div class="callout callout-<?php echo ($apiMode === 'MOCK') ? 'info' : 'success'; ?>" style="padding:10px 15px; margin-bottom:15px;">
                    <i class="fa fa-<?php echo ($apiMode === 'MOCK') ? 'flask' : 'globe'; ?>"></i>
                    <strong>API Mode:</strong>
                    <span class="label label-<?php echo ($apiMode === 'MOCK') ? 'info' : 'success'; ?>"><?php echo $apiMode; ?></span>
                    <?php if($apiMode === 'MOCK'): ?>
                        — Using simulated NHIS responses. <a href="<?php echo base_url(); ?>app/nhis_claims/settings">Configure &raquo;</a>
                    <?php else: ?>
                        — Connected to live NHIS API.
                    <?php endif; ?>
                </div>

                <!-- Summary Stats -->
                <div class="row">
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo $totalClaims; ?></h3>
                                <p>Total Claims</p>
                            </div>
                            <div class="icon"><i class="fa fa-file-text-o"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3>GHS <?php echo number_format($totalNhisAmt, 2); ?></h3>
                                <p>Total Claimed</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3>GHS <?php echo number_format($totalApproved, 2); ?></h3>
                                <p>Total Approved</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3>GHS <?php echo number_format(max(0, $totalShortfall), 2); ?></h3>
                                <p>Shortfall</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-circle"></i></div>
                        </div>
                    </div>
                </div>

                <!-- Status Breakdown -->
                <div class="row">
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-yellow"><i class="fa fa-clock-o"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Pending</span>
                                <span class="info-box-number"><?php echo $pending; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-aqua"><i class="fa fa-upload"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Submitted</span>
                                <span class="info-box-number"><?php echo $submitted; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Approved</span>
                                <span class="info-box-number"><?php echo $approved; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6 col-xs-12">
                        <div class="info-box">
                            <span class="info-box-icon bg-red"><i class="fa fa-times"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Rejected</span>
                                <span class="info-box-number"><?php echo $rejected; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Charts Row -->
                <div class="row">
                    <div class="col-md-5">
                        <div class="box box-primary">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-pie-chart"></i> Status Distribution</h3>
                            </div>
                            <div class="box-body">
                                <canvas id="statusPieChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-7">
                        <div class="box box-info">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-line-chart"></i> Claims Over Time (30 Days)</h3>
                            </div>
                            <div class="box-body">
                                <canvas id="timelineChart" height="260"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reconciliation Summary -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-warning">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-balance-scale"></i> Reconciliation Summary</h3>
                                <div class="box-tools pull-right">
                                    <a href="<?php echo base_url(); ?>app/nhis_claims/reconcile_all" class="btn btn-sm btn-warning" onclick="return confirm('Reconcile all pending claims?');">
                                        <i class="fa fa-refresh"></i> Reconcile All
                                    </a>
                                </div>
                            </div>
                            <div class="box-body">
                                <div class="row">
                                    <div class="col-md-3 text-center">
                                        <h2 class="text-green"><?php echo $matched; ?></h2>
                                        <p><i class="fa fa-check-circle text-green"></i> Matched</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h2 class="text-yellow"><?php echo $underpaid; ?></h2>
                                        <p><i class="fa fa-exclamation-triangle text-yellow"></i> Underpaid</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h2 class="text-red"><?php echo $reconRejected; ?></h2>
                                        <p><i class="fa fa-times-circle text-red"></i> Rejected</p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <h2 class="text-aqua"><?php echo $totalClaims - $matched - $underpaid - $reconRejected; ?></h2>
                                        <p><i class="fa fa-clock-o text-aqua"></i> Pending Recon</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?php echo base_url(); ?>app/nhis_claims">
                            <div class="row">
                                <div class="col-md-2">
                                    <label>Date From</label>
                                    <input type="date" name="date_from" class="form-control input-sm" value="<?php echo isset($filters['date_from']) ? htmlspecialchars($filters['date_from']) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label>Date To</label>
                                    <input type="date" name="date_to" class="form-control input-sm" value="<?php echo isset($filters['date_to']) ? htmlspecialchars($filters['date_to']) : ''; ?>">
                                </div>
                                <div class="col-md-2">
                                    <label>Claim Status</label>
                                    <select name="status" class="form-control input-sm">
                                        <option value="">All</option>
                                        <?php foreach(array('PENDING','SUBMITTED','APPROVED','REJECTED') as $st): ?>
                                        <option value="<?php echo $st; ?>" <?php echo (isset($filters['status']) && $filters['status']===$st)?'selected':''; ?>><?php echo $st; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label>Recon Status</label>
                                    <select name="recon_status" class="form-control input-sm">
                                        <option value="">All</option>
                                        <?php foreach(array('MATCHED','UNDERPAID','REJECTED','OVERPAID') as $rs): ?>
                                        <option value="<?php echo $rs; ?>" <?php echo (isset($filters['recon_status']) && $filters['recon_status']===$rs)?'selected':''; ?>><?php echo $rs; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-1">
                                    <label>Min Amt</label>
                                    <input type="number" step="0.01" name="amount_min" class="form-control input-sm" value="<?php echo isset($filters['amount_min']) ? htmlspecialchars($filters['amount_min']) : ''; ?>" placeholder="0.00">
                                </div>
                                <div class="col-md-1">
                                    <label>Max Amt</label>
                                    <input type="number" step="0.01" name="amount_max" class="form-control input-sm" value="<?php echo isset($filters['amount_max']) ? htmlspecialchars($filters['amount_max']) : ''; ?>" placeholder="9999">
                                </div>
                                <div class="col-md-2">
                                    <label>&nbsp;</label><br>
                                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
                                    <a href="<?php echo base_url(); ?>app/nhis_claims" class="btn btn-default btn-sm"><i class="fa fa-times"></i> Clear</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div style="margin-bottom: 15px;">
                    <a href="<?php echo base_url(); ?>app/nhis_claims/submit_all_pending" class="btn btn-info" onclick="return confirm('Submit all <?php echo $pending; ?> pending claims to NHIS?');">
                        <i class="fa fa-upload"></i> Submit All Pending (<?php echo $pending; ?>)
                    </a>
                    <a href="<?php echo base_url(); ?>app/nhis_claims/reconcile_all" class="btn btn-warning" onclick="return confirm('Reconcile all unreconciled claims?');">
                        <i class="fa fa-balance-scale"></i> Reconcile All
                    </a>
                    <a href="<?php echo base_url(); ?>app/nhis_claims/settings" class="btn btn-default pull-right">
                        <i class="fa fa-cog"></i> API Settings
                    </a>
                </div>

                <!-- Claims Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Claims List</h3>
                        <div class="box-tools pull-right">
                            <span class="label label-primary"><?php echo count($claims); ?> claims</span>
                        </div>
                    </div>
                    <div class="box-body table-responsive">
                        <table id="claimsTable" class="table table-bordered table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Claim Ref</th>
                                    <th>Patient</th>
                                    <th>NHIS #</th>
                                    <th>Total</th>
                                    <th>NHIS Amt</th>
                                    <th>Approved</th>
                                    <th>Status</th>
                                    <th>Recon</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if(isset($claims) && is_array($claims)): foreach($claims as $c):
                                $statusClass = 'label-default';
                                if($c->status==='APPROVED') $statusClass = 'label-success';
                                elseif($c->status==='REJECTED') $statusClass = 'label-danger';
                                elseif($c->status==='SUBMITTED') $statusClass = 'label-info';
                                elseif($c->status==='PENDING') $statusClass = 'label-warning';

                                $reconClass = 'label-default';
                                $reconLabel = isset($c->recon_status) && $c->recon_status ? $c->recon_status : '—';
                                if(isset($c->recon_status)){
                                    if($c->recon_status==='MATCHED') $reconClass = 'label-success';
                                    elseif($c->recon_status==='UNDERPAID') $reconClass = 'label-warning';
                                    elseif($c->recon_status==='REJECTED') $reconClass = 'label-danger';
                                    elseif($c->recon_status==='OVERPAID') $reconClass = 'label-info';
                                }

                                $approvedAmt = isset($c->approved_amount) && $c->approved_amount !== null ? number_format((float)$c->approved_amount, 2) : '—';
                            ?>
                                <tr>
                                    <td><a href="<?php echo base_url(); ?>app/nhis_claims/view/<?php echo $c->claim_id; ?>"><?php echo htmlspecialchars($c->claim_ref); ?></a></td>
                                    <td><?php echo htmlspecialchars(isset($c->patient_name) ? $c->patient_name : $c->patient_no); ?></td>
                                    <td><small><?php echo htmlspecialchars($c->nhis_number); ?></small></td>
                                    <td class="text-right"><?php echo number_format((float)$c->total_amount, 2); ?></td>
                                    <td class="text-right"><?php echo number_format((float)$c->nhis_amount, 2); ?></td>
                                    <td class="text-right"><?php echo $approvedAmt; ?></td>
                                    <td><span class="label <?php echo $statusClass; ?>"><?php echo $c->status; ?></span></td>
                                    <td><span class="label <?php echo $reconClass; ?>"><?php echo $reconLabel; ?></span></td>
                                    <td><small><?php echo date('M d, Y', strtotime($c->created_at)); ?></small></td>
                                    <td>
                                        <a href="<?php echo base_url(); ?>app/nhis_claims/view/<?php echo $c->claim_id; ?>" class="btn btn-xs btn-default" title="View"><i class="fa fa-eye"></i></a>
                                        <?php if($c->status === 'PENDING'): ?>
                                        <a href="<?php echo base_url(); ?>app/nhis_claims/submit_claim/<?php echo $c->claim_id; ?>?redirect=dashboard" class="btn btn-xs btn-info" title="Submit" onclick="return confirm('Submit this claim?');"><i class="fa fa-upload"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.1/jquery.min.js"></script>
    <script src="<?php echo base_url()?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url()?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script src="<?php echo base_url()?>public/js/datatables/jquery.dataTables.js" type="text/javascript"></script>
    <script src="<?php echo base_url()?>public/js/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <script>
    $(function(){
        // DataTable
        $('#claimsTable').DataTable({
            "order": [[8, "desc"]],
            "pageLength": 25,
            "language": { "search": "Search claims:" }
        });

        // Pie Chart — Status Distribution
        var distData = <?php echo json_encode(isset($distribution) ? $distribution : array()); ?>;
        var pieLabels = [], pieCounts = [], pieColors = [];
        var colorMap = {'PENDING':'#f39c12','SUBMITTED':'#00c0ef','APPROVED':'#00a65a','REJECTED':'#dd4b39'};
        for(var i=0; i<distData.length; i++){
            pieLabels.push(distData[i].status);
            pieCounts.push(parseInt(distData[i].cnt));
            pieColors.push(colorMap[distData[i].status] || '#777');
        }
        if(pieLabels.length > 0){
            new Chart(document.getElementById('statusPieChart'), {
                type: 'doughnut',
                data: {
                    labels: pieLabels,
                    datasets: [{
                        data: pieCounts,
                        backgroundColor: pieColors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 15 } }
                    }
                }
            });
        }

        // Line Chart — Claims Over Time
        var tlData = <?php echo json_encode(isset($timeline) ? $timeline : array()); ?>;
        var tlLabels = [], tlCounts = [], tlAmounts = [];
        for(var i=0; i<tlData.length; i++){
            tlLabels.push(tlData[i].claim_date);
            tlCounts.push(parseInt(tlData[i].claim_count));
            tlAmounts.push(parseFloat(tlData[i].claim_amount));
        }
        if(tlLabels.length > 0){
            new Chart(document.getElementById('timelineChart'), {
                type: 'line',
                data: {
                    labels: tlLabels,
                    datasets: [
                        {
                            label: 'Claims Count',
                            data: tlCounts,
                            borderColor: '#00c0ef',
                            backgroundColor: 'rgba(0,192,239,0.1)',
                            fill: true,
                            tension: 0.3,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Amount (GHS)',
                            data: tlAmounts,
                            borderColor: '#00a65a',
                            backgroundColor: 'rgba(0,166,90,0.05)',
                            fill: false,
                            tension: 0.3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { type: 'linear', display: true, position: 'left', title: { display: true, text: 'Count' } },
                        y1: { type: 'linear', display: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'GHS' } }
                    },
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        }
    });
    </script>
</body>
</html>
