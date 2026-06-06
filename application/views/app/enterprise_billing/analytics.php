<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Revenue Analytics</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <style>
        .small-box { border-radius: 5px; }
        .small-box .icon { font-size: 70px; top: 10px; }
        .info-box { min-height: 90px; }
        .chart-container { position: relative; height: 300px; }
        .revenue-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .revenue-card .inner h3 { color: white; }
        .revenue-card .inner p { color: rgba(255,255,255,0.8); }
        .stat-card { border-left: 4px solid; padding: 15px; margin-bottom: 15px; background: #fff; }
        .stat-card.primary { border-color: #3c8dbc; }
        .stat-card.success { border-color: #00a65a; }
        .stat-card.warning { border-color: #f39c12; }
        .stat-card.danger { border-color: #dd4b39; }
        .trend-up { color: #00a65a; }
        .trend-down { color: #dd4b39; }
        .department-bar { height: 25px; background: #3c8dbc; border-radius: 3px; margin-bottom: 5px; }
        .payment-method-badge { display: inline-block; padding: 5px 15px; border-radius: 20px; margin: 2px; }
        .badge-cash { background: #00a65a; color: white; }
        .badge-momo { background: #f39c12; color: white; }
        .badge-card { background: #3c8dbc; color: white; }
        .badge-insurance { background: #605ca8; color: white; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Revenue Analytics <small>Financial Intelligence Dashboard</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Analytics</li>
        </ol>
    </section>

    <section class="content">
        <!-- Revenue Summary Cards -->
        <div class="row">
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3><?= number_format($summary['today']['total'], 2) ?></h3>
                        <p>Today's Revenue</p>
                    </div>
                    <div class="icon"><i class="fa fa-money"></i></div>
                    <a href="<?= base_url('app/ebilling/daily_report') ?>" class="small-box-footer">
                        <?= $summary['today']['count'] ?> transactions <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3><?= number_format($summary['weekly']['total'], 2) ?></h3>
                        <p>This Week</p>
                    </div>
                    <div class="icon"><i class="fa fa-calendar-o"></i></div>
                    <a href="#" class="small-box-footer">
                        <?= $summary['weekly']['count'] ?> transactions <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-purple">
                    <div class="inner">
                        <h3><?= number_format($summary['monthly']['total'], 2) ?></h3>
                        <p>This Month</p>
                    </div>
                    <div class="icon"><i class="fa fa-bar-chart"></i></div>
                    <a href="#" class="small-box-footer">
                        <?php 
                        $comp = $summary['monthly_comparison'];
                        $arrow = $comp['change_direction'] === 'up' ? 'fa-arrow-up trend-up' : 'fa-arrow-down trend-down';
                        ?>
                        <i class="fa <?= $arrow ?>"></i> <?= abs($comp['change_percent']) ?>% vs last month
                    </a>
                </div>
            </div>
            
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3><?= number_format($summary['outstanding'], 2) ?></h3>
                        <p>Outstanding Balance</p>
                    </div>
                    <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                    <a href="<?= base_url('app/ebilling/outstanding_report') ?>" class="small-box-footer">
                        View details <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Secondary Stats Row -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card primary">
                    <h4><?= $summary['invoices_today'] ?></h4>
                    <p class="text-muted">Invoices Today</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card success">
                    <h4><?= $summary['pending_payments']['count'] ?></h4>
                    <p class="text-muted">Pending Payments (<?= number_format($summary['pending_payments']['total'], 2) ?>)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card warning">
                    <h4><?= $summary['refunds_today']['count'] ?></h4>
                    <p class="text-muted">Refunds Today (<?= number_format($summary['refunds_today']['total'], 2) ?>)</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card danger">
                    <?php 
                    $cash_total = 0;
                    $insurance_total = 0;
                    foreach ($summary['payer_breakdown'] as $pb) {
                        if ($pb->payer_category === 'CASH') $cash_total = $pb->total;
                        if ($pb->payer_category === 'INSURANCE') $insurance_total = $pb->total;
                    }
                    $total_payer = $cash_total + $insurance_total;
                    $cash_pct = $total_payer > 0 ? round(($cash_total / $total_payer) * 100) : 0;
                    ?>
                    <h4><?= $cash_pct ?>% Cash</h4>
                    <p class="text-muted">Cash vs Insurance Split</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Department Revenue -->
            <div class="col-md-6">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-building"></i> Department Revenue (This Month)</h3>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($summary['top_departments'])): ?>
                            <?php 
                            $max_dept = !empty($summary['top_departments']) ? $summary['top_departments'][0]->total : 1;
                            foreach ($summary['top_departments'] as $dept): 
                                $pct = $max_dept > 0 ? ($dept->total / $max_dept) * 100 : 0;
                            ?>
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-xs-4">
                                    <strong><?= htmlspecialchars($dept->department ?? 'Unknown') ?></strong>
                                </div>
                                <div class="col-xs-5">
                                    <div class="progress" style="margin-bottom: 0;">
                                        <div class="progress-bar progress-bar-primary" style="width: <?= $pct ?>%"></div>
                                    </div>
                                </div>
                                <div class="col-xs-3 text-right">
                                    <span class="text-muted"><?= number_format($dept->total, 2) ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No department revenue data available</p>
                        <?php endif; ?>
                    </div>
                    <div class="box-footer">
                        <a href="<?= base_url('app/ebilling/department_report') ?>" class="btn btn-sm btn-default">
                            View Full Report <i class="fa fa-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="col-md-6">
                <div class="box box-success">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-credit-card"></i> Payment Methods (This Month)</h3>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($summary['payment_methods'])): ?>
                            <div class="row">
                                <?php foreach ($summary['payment_methods'] as $pm): 
                                    $badge_class = 'badge-cash';
                                    if (stripos($pm->payment_method, 'MOMO') !== false || stripos($pm->payment_method, 'MOBILE') !== false) {
                                        $badge_class = 'badge-momo';
                                    } elseif (stripos($pm->payment_method, 'CARD') !== false) {
                                        $badge_class = 'badge-card';
                                    } elseif (stripos($pm->payment_method, 'INSURANCE') !== false || stripos($pm->payment_method, 'NHIS') !== false) {
                                        $badge_class = 'badge-insurance';
                                    }
                                ?>
                                <div class="col-md-6" style="margin-bottom: 15px;">
                                    <div class="payment-method-badge <?= $badge_class ?>">
                                        <i class="fa fa-<?= $pm->payment_method === 'CASH' ? 'money' : 'credit-card' ?>"></i>
                                        <?= htmlspecialchars($pm->payment_method) ?>
                                    </div>
                                    <div style="margin-top: 5px;">
                                        <strong><?= number_format($pm->total, 2) ?></strong>
                                        <span class="text-muted">(<?= $pm->count ?> txns)</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No payment data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Revenue Trend Chart -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-info">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-line-chart"></i> Revenue Trend (Last 30 Days)</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <div class="chart-container">
                            <canvas id="revenueTrendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cashier Performance -->
        <?php if (!empty($cashier_performance)): ?>
        <div class="row">
            <div class="col-md-12">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-users"></i> Cashier Performance (This Month)</h3>
                    </div>
                    <div class="box-body">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Cashier</th>
                                    <th>Transactions</th>
                                    <th>Total Collected</th>
                                    <th>Avg. Transaction</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cashier_performance as $cp): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cp->full_name ?? $cp->username ?? 'Unknown') ?></td>
                                    <td><?= number_format($cp->transaction_count) ?></td>
                                    <td><strong><?= number_format($cp->total_collected, 2) ?></strong></td>
                                    <td><?= $cp->transaction_count > 0 ? number_format($cp->total_collected / $cp->transaction_count, 2) : '0.00' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Actions -->
        <div class="row">
            <div class="col-md-12">
                <div class="box">
                    <div class="box-header">
                        <h3 class="box-title"><i class="fa fa-bolt"></i> Quick Actions</h3>
                    </div>
                    <div class="box-body">
                        <a href="<?= base_url('app/ebilling/daily_report') ?>" class="btn btn-primary">
                            <i class="fa fa-file-text"></i> Daily Report
                        </a>
                        <a href="<?= base_url('app/ebilling/department_report') ?>" class="btn btn-success">
                            <i class="fa fa-building"></i> Department Revenue
                        </a>
                        <a href="<?= base_url('app/ebilling/outstanding_report') ?>" class="btn btn-warning">
                            <i class="fa fa-exclamation-circle"></i> Outstanding Balances
                        </a>
                        <a href="<?= base_url('app/ebilling/reconciliation_dashboard') ?>" class="btn btn-info">
                            <i class="fa fa-balance-scale"></i> Reconciliation
                        </a>
                        <a href="<?= base_url('app/ebilling/audit_logs') ?>" class="btn btn-default">
                            <i class="fa fa-history"></i> Audit Logs
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    $(document).ready(function() {
        // Revenue Trend Chart
        var ctx = document.getElementById('revenueTrendChart');
        if (ctx) {
            var trendData = <?= json_encode($revenue_trend ?? []) ?>;
            var labels = trendData.map(function(d) { return d.date; });
            var values = trendData.map(function(d) { return parseFloat(d.total); });
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Daily Revenue',
                        data: values,
                        borderColor: '#3c8dbc',
                        backgroundColor: 'rgba(60, 141, 188, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return value.toLocaleString();
                                }
                            }
                        }
                    }
                }
            });
        }
    });
    </script>
</body>
</html>
