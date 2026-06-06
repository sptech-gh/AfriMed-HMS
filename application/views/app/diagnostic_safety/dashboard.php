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
    <h1><i class="fa fa-shield"></i> Diagnostic Safety Dashboard</h1>
    <ol class="breadcrumb">
        <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li class="active">Diagnostic Safety</li>
    </ol>
</section>

<section class="content">
    <!-- Summary Cards Row -->
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-aqua">
                <div class="inner">
                    <h3><?php echo $tat_dashboard->total_tests ?? 0; ?></h3>
                    <p>Tests Today</p>
                </div>
                <div class="icon"><i class="fa fa-flask"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/tat_dashboard'); ?>" class="small-box-footer">
                    TAT Dashboard <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-red">
                <div class="inner">
                    <h3><?php echo $at_risk_count ?? 0; ?></h3>
                    <p>At Risk (TAT)</p>
                </div>
                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/tat_dashboard'); ?>" class="small-box-footer">
                    View Details <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-yellow">
                <div class="inner">
                    <h3><?php echo $stat_pending_count ?? 0; ?></h3>
                    <p>STAT Pending</p>
                </div>
                <div class="icon"><i class="fa fa-bolt"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/stat_queue'); ?>" class="small-box-footer">
                    STAT Queue <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-green">
                <div class="inner">
                    <h3><?php echo number_format($tat_dashboard->compliance_rate ?? 0, 1); ?>%</h3>
                    <p>TAT Compliance</p>
                </div>
                <div class="icon"><i class="fa fa-check-circle"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/tat_dashboard'); ?>" class="small-box-footer">
                    View Trend <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Second Row -->
    <div class="row">
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-purple">
                <div class="inner">
                    <h3><?php echo $tat_dashboard->stat_pending ?? 0; ?></h3>
                    <p>Active STAT Tests</p>
                </div>
                <div class="icon"><i class="fa fa-clock-o"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/stat_queue'); ?>" class="small-box-footer">
                    Monitor <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-orange">
                <div class="inner">
                    <h3><?php echo $breach_count ?? 0; ?></h3>
                    <p>Unacknowledged Breaches</p>
                </div>
                <div class="icon"><i class="fa fa-warning"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/tat_dashboard'); ?>" class="small-box-footer">
                    Review <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-teal">
                <div class="inner">
                    <h3><?php echo $audit_stats->total_events ?? 0; ?></h3>
                    <p>Audit Events (30d)</p>
                </div>
                <div class="icon"><i class="fa fa-history"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/audit_log'); ?>" class="small-box-footer">
                    Audit Log <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
        
        <div class="col-lg-3 col-xs-6">
            <div class="small-box bg-maroon">
                <div class="inner">
                    <h3><?php echo $audit_stats->security_events ?? 0; ?></h3>
                    <p>Security Events</p>
                </div>
                <div class="icon"><i class="fa fa-lock"></i></div>
                <a href="<?php echo base_url('app/diagnostic_safety/audit_log?category=SECURITY'); ?>" class="small-box-footer">
                    View <i class="fa fa-arrow-circle-right"></i>
                </a>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- At Risk Tests -->
        <div class="col-md-6">
            <div class="box box-danger">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Tests At Risk</h3>
                    <div class="box-tools pull-right">
                        <span class="badge bg-red"><?php echo count($at_risk_tests); ?></span>
                    </div>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Elapsed</th>
                                <th>% of TAT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($at_risk_tests)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No tests at risk</td></tr>
                            <?php else: ?>
                            <?php foreach ($at_risk_tests as $test): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($test->firstname . ' ' . $test->lastname); ?></td>
                                <td><?php echo htmlspecialchars($test->test_name); ?></td>
                                <td><?php echo $test->elapsed_minutes; ?> min</td>
                                <td>
                                    <?php 
                                    $pct = $test->pct_elapsed;
                                    $class = $pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'info');
                                    ?>
                                    <div class="progress progress-xs">
                                        <div class="progress-bar progress-bar-<?php echo $class; ?>" style="width: <?php echo min($pct, 100); ?>%"></div>
                                    </div>
                                    <small><?php echo number_format($pct, 0); ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="box-footer text-center">
                    <a href="<?php echo base_url('app/diagnostic_safety/tat_dashboard'); ?>">View All</a>
                </div>
            </div>
        </div>

        <!-- Active STAT Tests -->
        <div class="col-md-6">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-bolt"></i> Active STAT Tests</h3>
                    <div class="box-tools pull-right">
                        <span class="badge bg-yellow"><?php echo count($active_stat_tests); ?></span>
                    </div>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-striped table-condensed">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Time Left</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_stat_tests)): ?>
                            <tr><td colspan="4" class="text-center text-muted">No active STAT tests</td></tr>
                            <?php else: ?>
                            <?php foreach ($active_stat_tests as $stat): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($stat->firstname . ' ' . $stat->lastname); ?></td>
                                <td><?php echo htmlspecialchars($stat->test_name); ?></td>
                                <td>
                                    <?php if ($stat->minutes_remaining < 0): ?>
                                    <span class="text-red"><strong>OVERDUE <?php echo abs($stat->minutes_remaining); ?>m</strong></span>
                                    <?php else: ?>
                                    <?php echo $stat->minutes_remaining; ?> min
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $status = $stat->tracking_status ?? 'ORDERED';
                                    $badge = 'default';
                                    if ($status == 'PROCESSING') $badge = 'info';
                                    elseif ($status == 'RESULTED') $badge = 'primary';
                                    elseif ($status == 'VERIFIED') $badge = 'success';
                                    ?>
                                    <span class="label label-<?php echo $badge; ?>"><?php echo $status; ?></span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="box-footer text-center">
                    <a href="<?php echo base_url('app/diagnostic_safety/stat_queue'); ?>">View Queue</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Audit Chain Status -->
        <div class="col-md-6">
            <div class="box box-info">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-link"></i> Audit Chain Integrity</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-6">
                            <div class="description-block border-right">
                                <span class="description-percentage text-green">
                                    <i class="fa fa-check"></i> <?php echo $chain_status->verified_chains ?? 0; ?>
                                </span>
                                <h5 class="description-header"><?php echo $chain_status->sealed_chains ?? 0; ?></h5>
                                <span class="description-text">SEALED CHAINS</span>
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="description-block">
                                <?php if (($chain_status->failed_chains ?? 0) > 0): ?>
                                <span class="description-percentage text-red">
                                    <i class="fa fa-warning"></i> <?php echo $chain_status->failed_chains; ?> FAILED
                                </span>
                                <?php else: ?>
                                <span class="description-percentage text-green">
                                    <i class="fa fa-shield"></i> VERIFIED
                                </span>
                                <?php endif; ?>
                                <h5 class="description-header"><?php echo $audit_stats->unverified_records ?? 0; ?></h5>
                                <span class="description-text">UNVERIFIED RECORDS</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="box-footer text-center">
                    <a href="<?php echo base_url('app/diagnostic_safety/chain_status'); ?>">Chain Status</a> |
                    <a href="<?php echo base_url('app/diagnostic_safety/compliance_report'); ?>">Compliance Report</a>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="col-md-6">
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-tasks"></i> Quick Actions</h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-xs-6">
                            <a href="<?php echo base_url('app/diagnostic_safety/tat_dashboard'); ?>" class="btn btn-block btn-default">
                                <i class="fa fa-tachometer"></i> TAT Dashboard
                            </a>
                        </div>
                        <div class="col-xs-6">
                            <a href="<?php echo base_url('app/diagnostic_safety/stat_queue'); ?>" class="btn btn-block btn-warning">
                                <i class="fa fa-bolt"></i> STAT Queue
                            </a>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-xs-6">
                            <a href="<?php echo base_url('app/diagnostic_safety/notifications'); ?>" class="btn btn-block btn-info">
                                <i class="fa fa-bell"></i> Notifications
                            </a>
                        </div>
                        <div class="col-xs-6">
                            <a href="<?php echo base_url('app/diagnostic_safety/audit_log'); ?>" class="btn btn-block btn-success">
                                <i class="fa fa-history"></i> Audit Log
                            </a>
                        </div>
                    </div>
                    <br>
                    <div class="row">
                        <div class="col-xs-6">
                            <a href="<?php echo base_url('app/diagnostic_safety/tat_targets'); ?>" class="btn btn-block btn-default">
                                <i class="fa fa-cog"></i> TAT Targets
                            </a>
                        </div>
                        <div class="col-xs-6">
                            <a href="<?php echo base_url('app/diagnostic_safety/compliance_report'); ?>" class="btn btn-block btn-primary">
                                <i class="fa fa-file-text"></i> Compliance Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
</aside>
</div>

<script src="<?php echo base_url(); ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
</body>
</html>
