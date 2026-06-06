<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content-header">
    <h1><i class="fa fa-tachometer"></i> TAT Monitoring Dashboard</h1>
    <ol class="breadcrumb">
        <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="<?php echo base_url();?>app/diagnostic_safety">Diagnostic Safety</a></li>
        <li class="active">TAT Dashboard</li>
    </ol>
</section>

<section class="content">
    <!-- Summary Cards -->
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?php echo $dashboard->total_tests ?? 0; ?></h3>
                    <p>Tests Today</p>
                </div>
                <div class="icon"><i class="fa fa-flask"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3><?php echo $dashboard->completed ?? 0; ?></h3>
                    <p>Completed</p>
                </div>
                <div class="icon"><i class="fa fa-check"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3><?php echo $dashboard->in_progress ?? 0; ?></h3>
                    <p>In Progress</p>
                </div>
                <div class="icon"><i class="fa fa-spinner"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3><?php echo $dashboard->breached ?? 0; ?></h3>
                    <p>TAT Breached</p>
                </div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-purple">
                <div class="inner">
                    <h3><?php echo $dashboard->stat_tests ?? 0; ?></h3>
                    <p>STAT Tests</p>
                </div>
                <div class="icon"><i class="fa fa-bolt"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-orange">
                <div class="inner">
                    <h3><?php echo $dashboard->stat_pending ?? 0; ?></h3>
                    <p>STAT Pending</p>
                </div>
                <div class="icon"><i class="fa fa-clock-o"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-teal">
                <div class="inner">
                    <h3><?php echo number_format($dashboard->avg_tat ?? 0, 0); ?> min</h3>
                    <p>Avg TAT</p>
                </div>
                <div class="icon"><i class="fa fa-clock-o"></i></div>
            </div>
        </div>
        <div class="col-lg-3 col-xs-6">
            <div class="small-box <?php echo ($dashboard->compliance_rate ?? 0) >= 90 ? 'bg-green' : (($dashboard->compliance_rate ?? 0) >= 70 ? 'bg-yellow' : 'bg-red'); ?>">
                <div class="inner">
                    <h3><?php echo number_format($dashboard->compliance_rate ?? 0, 1); ?>%</h3>
                    <p>Compliance Rate</p>
                </div>
                <div class="icon"><i class="fa fa-pie-chart"></i></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- At Risk Tests -->
        <div class="col-md-8">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Tests At Risk of TAT Breach</h3>
                    <div class="box-tools pull-right">
                        <span class="badge bg-red"><?php echo count($at_risk_tests); ?></span>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Elapsed</th>
                                <th>Target</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($at_risk_tests)): ?>
                            <tr><td colspan="7" class="text-center text-muted">No tests at risk</td></tr>
                            <?php else: ?>
                            <?php foreach ($at_risk_tests as $test): ?>
                            <tr class="<?php echo $test->pct_elapsed >= 100 ? 'danger' : ''; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($test->firstname . ' ' . $test->lastname); ?></strong><br>
                                    <small class="text-muted"><?php echo $test->patient_no; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($test->test_name); ?></td>
                                <td>
                                    <?php
                                    $pri_colors = ['ROUTINE' => 'default', 'URGENT' => 'warning', 'STAT' => 'danger', 'CRITICAL' => 'danger'];
                                    $pri_color = $pri_colors[$test->priority_level] ?? 'default';
                                    ?>
                                    <span class="label label-<?php echo $pri_color; ?>"><?php echo $test->priority_level; ?></span>
                                </td>
                                <td><span class="label label-info"><?php echo $test->status; ?></span></td>
                                <td><strong><?php echo $test->elapsed_minutes; ?></strong> min</td>
                                <td><?php echo $test->target_tat_minutes; ?> min</td>
                                <td style="width: 150px;">
                                    <?php 
                                    $pct = min($test->pct_elapsed, 150);
                                    $class = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'info');
                                    ?>
                                    <div class="progress progress-sm" style="margin-bottom: 0;">
                                        <div class="progress-bar progress-bar-<?php echo $class; ?>" style="width: <?php echo min($pct, 100); ?>%"></div>
                                    </div>
                                    <small class="text-<?php echo $class; ?>"><?php echo number_format($test->pct_elapsed, 0); ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Unacknowledged Breaches -->
        <div class="col-md-4">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-warning"></i> Unacknowledged Breaches</h3>
                    <div class="box-tools pull-right">
                        <span class="badge bg-yellow"><?php echo count($unacknowledged_breaches); ?></span>
                    </div>
                </div>
                <div class="box-body no-padding">
                    <ul class="list-group">
                        <?php if (empty($unacknowledged_breaches)): ?>
                        <li class="list-group-item text-center text-muted">No unacknowledged breaches</li>
                        <?php else: ?>
                        <?php foreach (array_slice($unacknowledged_breaches, 0, 10) as $breach): ?>
                        <li class="list-group-item">
                            <div class="pull-right">
                                <button class="btn btn-xs btn-warning btn-acknowledge" data-id="<?php echo $breach->breach_id; ?>">
                                    <i class="fa fa-check"></i>
                                </button>
                            </div>
                            <strong><?php echo htmlspecialchars($breach->test_name); ?></strong><br>
                            <small>
                                <?php echo htmlspecialchars($breach->firstname . ' ' . $breach->lastname); ?> |
                                <span class="text-red">+<?php echo $breach->breach_minutes; ?> min</span> |
                                <span class="label label-<?php echo $breach->breach_severity == 'SEVERE' ? 'danger' : ($breach->breach_severity == 'CRITICAL' ? 'warning' : 'default'); ?>">
                                    <?php echo $breach->breach_severity; ?>
                                </span>
                            </small>
                        </li>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Trend -->
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-line-chart"></i> TAT Compliance Trend (30 Days)</h3>
                </div>
                <div class="box-body">
                    <canvas id="complianceChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

</section>
</aside>
</div>

<!-- Acknowledge Modal -->
<div class="modal fade" id="acknowledgeModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Acknowledge TAT Breach</h4>
            </div>
            <form id="acknowledgeForm">
                <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                <div class="modal-body">
                    <input type="hidden" name="breach_id" id="ack_breach_id">
                    <div class="form-group">
                        <label>Root Cause</label>
                        <select name="root_cause" class="form-control" required>
                            <option value="">Select root cause...</option>
                            <option value="High Volume">High Volume</option>
                            <option value="Equipment Issue">Equipment Issue</option>
                            <option value="Staffing">Staffing Shortage</option>
                            <option value="Sample Issue">Sample Issue</option>
                            <option value="Reagent Issue">Reagent Issue</option>
                            <option value="System Downtime">System Downtime</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">Acknowledge</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
$(function() {
    // Acknowledge button
    $('.btn-acknowledge').click(function() {
        $('#ack_breach_id').val($(this).data('id'));
        $('#acknowledgeModal').modal('show');
    });

    // Acknowledge form submit
    $('#acknowledgeForm').submit(function(e) {
        e.preventDefault();
        $.post('<?php echo base_url("app/diagnostic_safety/acknowledge_breach"); ?>', $(this).serialize(), function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.error || 'Error acknowledging breach');
            }
        }, 'json');
    });

    // Compliance Chart
    var trendData = <?php echo json_encode($performance_trend ?? []); ?>;
    if (trendData.length > 0) {
        var labels = trendData.map(function(d) { return d.report_date; });
        var compliance = trendData.map(function(d) { return d.compliance_rate || 0; });
        var avgTat = trendData.map(function(d) { return d.avg_tat_minutes || 0; });

        new Chart(document.getElementById('complianceChart'), {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Compliance %',
                    data: compliance,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    yAxisID: 'y',
                    fill: true
                }, {
                    label: 'Avg TAT (min)',
                    data: avgTat,
                    borderColor: 'rgb(255, 99, 132)',
                    backgroundColor: 'transparent',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        type: 'linear',
                        position: 'left',
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Compliance %' }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        title: { display: true, text: 'Avg TAT (min)' },
                        grid: { drawOnChartArea: false }
                    }
                }
            }
        });
    }

    // Auto-refresh every 60 seconds
    setTimeout(function() { location.reload(); }, 60000);
});
</script>
</body>
</html>
