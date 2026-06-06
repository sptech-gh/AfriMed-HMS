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
        .timeline { position: relative; padding: 20px 0; }
        .timeline::before { content: ''; position: absolute; left: 30px; top: 0; bottom: 0; width: 4px; background: #3498db; }
        .timeline-item { position: relative; padding-left: 70px; margin-bottom: 25px; }
        .timeline-item::before { content: ''; position: absolute; left: 22px; top: 5px; width: 20px; height: 20px; border-radius: 50%; background: #3498db; border: 4px solid #fff; box-shadow: 0 0 0 2px #3498db; }
        .timeline-item.collection::before { background: #27ae60; box-shadow: 0 0 0 2px #27ae60; }
        .timeline-item.transport::before { background: #f39c12; box-shadow: 0 0 0 2px #f39c12; }
        .timeline-item.receive::before { background: #9b59b6; box-shadow: 0 0 0 2px #9b59b6; }
        .timeline-item.process::before { background: #1abc9c; box-shadow: 0 0 0 2px #1abc9c; }
        .timeline-item.storage::before { background: #3498db; box-shadow: 0 0 0 2px #3498db; }
        .timeline-item.disposal::before { background: #e74c3c; box-shadow: 0 0 0 2px #e74c3c; }
        .timeline-content { background: #fff; padding: 15px; border-radius: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .timeline-content h5 { margin: 0 0 10px 0; }
        .timeline-meta { font-size: 12px; color: #7f8c8d; }
        .sample-info { background: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 20px; }
        .sample-info .barcode { font-family: monospace; font-size: 24px; font-weight: bold; }
        .verification-box { padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .verification-valid { background: #d4edda; border: 1px solid #c3e6cb; }
        .verification-invalid { background: #f8d7da; border: 1px solid #f5c6cb; }
        .temp-badge { padding: 2px 6px; border-radius: 3px; font-size: 11px; }
        .temp-NORMAL { background: #27ae60; color: #fff; }
        .temp-WARNING { background: #f39c12; color: #fff; }
        .temp-CRITICAL { background: #e74c3c; color: #fff; }
        .temp-BREACH { background: #c0392b; color: #fff; }
        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .status-REQUESTED { background: #3498db; color: #fff; }
        .status-COLLECTED { background: #f39c12; color: #fff; }
        .status-RECEIVED_LAB { background: #9b59b6; color: #fff; }
        .status-IN_PROCESS { background: #1abc9c; color: #fff; }
        .status-RESULT_READY { background: #27ae60; color: #fff; }
        .status-VERIFIED { background: #2ecc71; color: #fff; }
        .status-REJECTED { background: #e74c3c; color: #fff; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-link"></i> Sample Custody Chain</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li><a href="<?php echo base_url(); ?>app/sample_tracking/custody">Chain of Custody</a></li>
                <li class="active"><?php echo htmlspecialchars($sample->sample_barcode); ?></li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <!-- Sample Info -->
                <div class="col-md-4">
                    <div class="sample-info">
                        <div class="barcode text-center"><?php echo htmlspecialchars($sample->sample_barcode); ?></div>
                        <hr>
                        <table class="table table-condensed">
                            <tr><th>Patient:</th><td><?php echo htmlspecialchars($sample->patient_no); ?></td></tr>
                            <tr><th>Test:</th><td><?php echo htmlspecialchars($sample->test_name ?? '-'); ?></td></tr>
                            <tr><th>Sample Type:</th><td><?php echo htmlspecialchars($sample->sample_type ?? '-'); ?></td></tr>
                            <tr><th>Status:</th><td><span class="status-badge status-<?php echo $sample->sample_status; ?>"><?php echo $sample->sample_status; ?></span></td></tr>
                            <tr><th>Current Location:</th><td><?php echo htmlspecialchars($sample->current_location_code ?? '-'); ?></td></tr>
                            <tr><th>Current Custodian:</th><td><?php echo htmlspecialchars($sample->current_custodian_id ?? '-'); ?></td></tr>
                            <tr><th>Total Handoffs:</th><td><?php echo $sample->total_handoffs ?? 0; ?></td></tr>
                            <tr><th>Created:</th><td><?php echo date('M j, Y H:i', strtotime($sample->created_at)); ?></td></tr>
                            <?php if ($sample->last_temperature !== null): ?>
                            <tr><th>Last Temp:</th><td>
                                <?php echo number_format($sample->last_temperature, 1); ?>°C
                                <?php if ($sample->temperature_breach_flag): ?>
                                <span class="label label-danger"><i class="fa fa-exclamation-triangle"></i> BREACH</span>
                                <?php endif; ?>
                            </td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <!-- Chain Verification -->
                    <div class="verification-box <?php echo $verification['valid'] ? 'verification-valid' : 'verification-invalid'; ?>">
                        <h5>
                            <i class="fa fa-<?php echo $verification['valid'] ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                            Chain Integrity
                        </h5>
                        <?php if ($verification['valid']): ?>
                        <p class="text-success mb-0"><strong>VERIFIED</strong> - Chain of custody is intact.</p>
                        <small>Hash: <?php echo substr($verification['computed_hash'], 0, 16); ?>...</small>
                        <?php else: ?>
                        <p class="text-danger mb-0"><strong>WARNING</strong> - <?php echo $verification['error'] ?? 'Chain integrity could not be verified.'; ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Temperature Log -->
                    <?php if (!empty($temperature_log)): ?>
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-thermometer-half"></i> Temperature Log</h3>
                        </div>
                        <div class="box-body table-responsive no-padding" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-condensed table-striped">
                                <thead>
                                    <tr><th>Time</th><th>Temp</th><th>Location</th><th>Status</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($temperature_log as $t): ?>
                                    <tr>
                                        <td><small><?php echo date('M j H:i', strtotime($t->recorded_at)); ?></small></td>
                                        <td><?php echo number_format($t->temperature_celsius, 1); ?>°C</td>
                                        <td><small><?php echo htmlspecialchars($t->location_code ?? '-'); ?></small></td>
                                        <td><span class="temp-badge temp-<?php echo $t->status; ?>"><?php echo $t->status; ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Custody Chain Timeline -->
                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-history"></i> Custody Chain Timeline</h3>
                            <span class="pull-right badge bg-blue"><?php echo count($chain); ?> handoffs</span>
                        </div>
                        <div class="box-body">
                            <?php if (empty($chain)): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No custody records found for this sample.
                            </div>
                            <?php else: ?>
                            <div class="timeline">
                                <?php foreach ($chain as $c): ?>
                                <div class="timeline-item <?php echo strtolower($c->handoff_type); ?>">
                                    <div class="timeline-content">
                                        <h5>
                                            <span class="label label-default">#<?php echo $c->handoff_sequence; ?></span>
                                            <?php echo $c->handoff_type; ?>
                                        </h5>
                                        <div class="row">
                                            <div class="col-xs-6">
                                                <strong>From:</strong><br>
                                                <?php echo htmlspecialchars($c->from_user_name ?? $c->from_user_id ?? 'N/A'); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($c->from_location ?? 'N/A'); ?></small>
                                            </div>
                                            <div class="col-xs-6">
                                                <strong>To:</strong><br>
                                                <?php echo htmlspecialchars($c->to_user_name ?? $c->to_user_id); ?><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($c->to_location); ?></small>
                                            </div>
                                        </div>
                                        <?php if ($c->temperature_celsius !== null): ?>
                                        <div class="mt-2">
                                            <i class="fa fa-thermometer-half"></i> 
                                            <?php echo number_format($c->temperature_celsius, 1); ?>°C
                                            <?php if ($c->temperature_status): ?>
                                            <span class="temp-badge temp-<?php echo $c->temperature_status; ?>"><?php echo $c->temperature_status; ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($c->condition_notes): ?>
                                        <div class="mt-2">
                                            <small><i class="fa fa-comment"></i> <?php echo htmlspecialchars($c->condition_notes); ?></small>
                                        </div>
                                        <?php endif; ?>
                                        <div class="timeline-meta mt-2">
                                            <i class="fa fa-clock-o"></i> <?php echo date('M j, Y H:i:s', strtotime($c->handoff_at)); ?>
                                            <?php if ($c->signature_hash): ?>
                                            <br><i class="fa fa-key"></i> <code><?php echo substr($c->signature_hash, 0, 12); ?>...</code>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Movement Audit Log -->
                    <?php if (!empty($movement_history)): ?>
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Movement Audit Log</h3>
                        </div>
                        <div class="box-body table-responsive no-padding">
                            <table class="table table-hover table-striped">
                                <thead>
                                    <tr>
                                        <th>Time</th>
                                        <th>Type</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>By</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($movement_history as $m): ?>
                                    <tr>
                                        <td><small><?php echo date('M j H:i', strtotime($m->performed_at)); ?></small></td>
                                        <td><span class="label label-info"><?php echo $m->movement_type; ?></span></td>
                                        <td><?php echo htmlspecialchars($m->from_location ?? $m->from_status ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($m->to_location ?? $m->to_status ?? '-'); ?></td>
                                        <td><?php echo htmlspecialchars($m->performed_by); ?></td>
                                        <td><small><?php echo htmlspecialchars($m->notes ?? '-'); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
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
