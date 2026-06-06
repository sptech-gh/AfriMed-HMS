<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .stat-box { border-radius: 5px; padding: 20px; margin-bottom: 15px; color: #fff; }
        .stat-box .number { font-size: 32px; font-weight: bold; }
        .stat-box .label-text { font-size: 14px; opacity: 0.9; }
        .stat-box.blue { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-box.green { background: linear-gradient(135deg, #27ae60, #1e8449); }
        .stat-box.orange { background: linear-gradient(135deg, #e67e22, #d35400); }
        .stat-box.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-box.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .status-REQUESTED { background: #3498db; color: #fff; }
        .status-COLLECTED { background: #f39c12; color: #fff; }
        .status-RECEIVED_LAB { background: #9b59b6; color: #fff; }
        .status-IN_PROCESS { background: #1abc9c; color: #fff; }
        .status-RESULT_READY { background: #27ae60; color: #fff; }
        .status-VERIFIED { background: #2ecc71; color: #fff; }
        .status-REJECTED { background: #e74c3c; color: #fff; }
        .priority-STAT { background: #e74c3c; color: #fff; }
        .priority-URGENT { background: #f39c12; color: #fff; }
        .priority-ROUTINE { background: #95a5a6; color: #fff; }
        .severity-CRITICAL { background: #e74c3c; color: #fff; }
        .severity-WARNING { background: #f39c12; color: #fff; }
        .severity-NORMAL { background: #27ae60; color: #fff; }
        .quick-link { display: block; padding: 15px; background: #f8f9fa; border-radius: 5px; text-align: center; margin-bottom: 10px; transition: all 0.3s; }
        .quick-link:hover { background: #e9ecef; text-decoration: none; }
        .quick-link i { font-size: 24px; display: block; margin-bottom: 8px; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-flask"></i> Sample Tracking Dashboard</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li class="active">Sample Tracking</li>
            </ol>
        </section>

        <section class="content">
            <!-- Statistics Row -->
            <div class="row">
                <div class="col-md-2 col-sm-4">
                    <div class="stat-box blue">
                        <div class="number"><?php echo $pending_samples; ?></div>
                        <div class="label-text">Pending Collection</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-box green">
                        <div class="number"><?php echo $in_transit; ?></div>
                        <div class="label-text">In Transit/Process</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-box orange">
                        <div class="number"><?php echo $pending_recollections; ?></div>
                        <div class="label-text">Pending Recollection</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-box red">
                        <div class="number"><?php echo $temperature_breaches; ?></div>
                        <div class="label-text">Temp Breaches</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <div class="stat-box purple">
                        <div class="number"><?php echo $pending_delta_reviews; ?></div>
                        <div class="label-text">Delta Reviews</div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-4">
                    <a href="<?php echo base_url(); ?>app/sample_tracking/custody" class="quick-link text-primary">
                        <i class="fa fa-barcode"></i> Scan Sample
                    </a>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-link"></i> Quick Actions</h3>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <div class="col-md-2 col-sm-4">
                                    <a href="<?php echo base_url(); ?>app/sample_tracking/custody" class="quick-link">
                                        <i class="fa fa-exchange text-blue"></i>
                                        Chain of Custody
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4">
                                    <a href="<?php echo base_url(); ?>app/sample_tracking/recollections" class="quick-link">
                                        <i class="fa fa-refresh text-orange"></i>
                                        Recollections
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4">
                                    <a href="<?php echo base_url(); ?>app/sample_tracking/delta_checks" class="quick-link">
                                        <i class="fa fa-line-chart text-purple"></i>
                                        Delta Checks
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4">
                                    <a href="<?php echo base_url(); ?>app/sample_tracking/delta_thresholds" class="quick-link">
                                        <i class="fa fa-sliders text-green"></i>
                                        Delta Thresholds
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4">
                                    <a href="<?php echo base_url(); ?>app/sample_tracking/locations" class="quick-link">
                                        <i class="fa fa-map-marker text-red"></i>
                                        Locations
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4">
                                    <a href="<?php echo base_url(); ?>app/sample_tracking/my_delta_notifications" class="quick-link">
                                        <i class="fa fa-bell text-yellow"></i>
                                        My Notifications
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Samples -->
                <div class="col-md-6">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-flask"></i> Recent Samples</h3>
                            <div class="box-tools">
                                <a href="<?php echo base_url(); ?>app/sample_tracking/custody" class="btn btn-xs btn-primary">View All</a>
                            </div>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Barcode</th>
                                        <th>Patient</th>
                                        <th>Test</th>
                                        <th>Status</th>
                                        <th>Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recent_samples)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No samples found</td></tr>
                                    <?php else: foreach ($recent_samples as $s): ?>
                                    <tr>
                                        <td><code><?php echo htmlspecialchars($s->sample_barcode); ?></code></td>
                                        <td><?php echo htmlspecialchars(($s->firstname ?? '') . ' ' . ($s->lastname ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($s->test_name ?? '-'); ?></td>
                                        <td><span class="status-badge status-<?php echo $s->sample_status; ?>"><?php echo $s->sample_status; ?></span></td>
                                        <td><?php echo htmlspecialchars($s->current_location_code ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Pending Recollections -->
                <div class="col-md-6">
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-refresh"></i> Pending Recollections</h3>
                            <div class="box-tools">
                                <a href="<?php echo base_url(); ?>app/sample_tracking/recollections" class="btn btn-xs btn-warning">View All</a>
                            </div>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Test</th>
                                        <th>Reason</th>
                                        <th>Priority</th>
                                        <th>Requested</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($recollections)): ?>
                                    <tr><td colspan="5" class="text-center text-muted">No pending recollections</td></tr>
                                    <?php else: foreach ($recollections as $r): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')); ?></td>
                                        <td><?php echo htmlspecialchars($r->test_name ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($r->rejection_reason_text ?? '-'); ?></td>
                                        <td><span class="status-badge priority-<?php echo $r->priority; ?>"><?php echo $r->priority; ?></span></td>
                                        <td><?php echo date('M j, H:i', strtotime($r->requested_at)); ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Delta Check Reviews -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-danger">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-line-chart"></i> Flagged Delta Checks Requiring Review</h3>
                            <div class="box-tools">
                                <a href="<?php echo base_url(); ?>app/sample_tracking/delta_checks" class="btn btn-xs btn-danger">View All</a>
                            </div>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Test</th>
                                        <th>Previous</th>
                                        <th>Current</th>
                                        <th>Delta %</th>
                                        <th>Severity</th>
                                        <th>Clinical Significance</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($delta_reviews)): ?>
                                    <tr><td colspan="8" class="text-center text-muted">No pending delta reviews</td></tr>
                                    <?php else: foreach ($delta_reviews as $d): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(($d->firstname ?? '') . ' ' . ($d->lastname ?? '')); ?></td>
                                        <td><strong><?php echo htmlspecialchars($d->test_name ?? '-'); ?></strong></td>
                                        <td><?php echo htmlspecialchars($d->previous_value ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($d->current_value ?? '-'); ?></td>
                                        <td><strong><?php echo number_format($d->delta_percent, 1); ?>%</strong></td>
                                        <td><span class="status-badge severity-<?php echo $d->delta_severity ?? 'NORMAL'; ?>"><?php echo $d->delta_severity ?? 'NORMAL'; ?></span></td>
                                        <td><small><?php echo htmlspecialchars($d->clinical_significance ?? '-'); ?></small></td>
                                        <td><?php echo date('M j, H:i', strtotime($d->created_at)); ?></td>
                                    </tr>
                                    <?php endforeach; endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
</body>
</html>
