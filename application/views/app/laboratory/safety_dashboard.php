<?php $this->load->view('includes/header'); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-shield text-primary"></i> Laboratory Safety Dashboard
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/laboratory"><i class="fa fa-flask"></i> Laboratory</a></li>
            <li class="active">Safety Dashboard</li>
        </ol>
    </section>

    <section class="content">
        <?php if (isset($message) && $message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <!-- Summary Boxes -->
        <div class="row">
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-red">
                    <div class="inner">
                        <h3><?php echo isset($critical_alert_count) ? $critical_alert_count : 0; ?></h3>
                        <p>Critical Alerts</p>
                    </div>
                    <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
                    <a href="<?php echo base_url(); ?>app/laboratory/critical_alerts" class="small-box-footer">
                        View Alerts <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-yellow">
                    <div class="inner">
                        <h3><?php echo isset($delta_flags) ? count($delta_flags) : 0; ?></h3>
                        <p>Delta Flags</p>
                    </div>
                    <div class="icon"><i class="fa fa-exchange"></i></div>
                    <a href="<?php echo base_url(); ?>app/laboratory/delta_flags" class="small-box-footer">
                        Review Flags <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-aqua">
                    <div class="inner">
                        <h3><i class="fa fa-barcode"></i></h3>
                        <p>Sample Tracking</p>
                    </div>
                    <div class="icon"><i class="fa fa-flask"></i></div>
                    <a href="<?php echo base_url(); ?>app/laboratory/sample_tracking" class="small-box-footer">
                        Track Samples <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-xs-6">
                <div class="small-box bg-green">
                    <div class="inner">
                        <h3><i class="fa fa-check-circle"></i></h3>
                        <p>Verification Queue</p>
                    </div>
                    <div class="icon"><i class="fa fa-check-double"></i></div>
                    <a href="<?php echo base_url(); ?>app/laboratory/lab_queue" class="small-box-footer">
                        View Queue <i class="fa fa-arrow-circle-right"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Critical Alerts -->
            <div class="col-md-6">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Recent Critical Alerts</h3>
                        <div class="box-tools pull-right">
                            <a href="<?php echo base_url(); ?>app/laboratory/critical_alerts" class="btn btn-box-tool">
                                View All <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="box-body no-padding">
                        <?php if (empty($pending_alerts)): ?>
                            <div class="callout callout-success" style="margin:10px;">
                                <i class="fa fa-check"></i> No pending critical alerts
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-unbordered">
                                <?php foreach (array_slice($pending_alerts, 0, 5) as $alert): ?>
                                    <?php
                                    $severity_class = 'default';
                                    if (in_array($alert->alert_severity, ['PANIC', 'CRITICAL'])) $severity_class = 'danger';
                                    elseif ($alert->alert_severity === 'HIGH') $severity_class = 'warning';
                                    ?>
                                    <li class="list-group-item">
                                        <span class="label label-<?php echo $severity_class; ?> pull-right">
                                            <?php echo $alert->alert_level; ?>
                                        </span>
                                        <strong><?php echo htmlspecialchars($alert->test_name ?? ''); ?></strong><br>
                                        <small class="text-muted">
                                            Patient: <?php echo htmlspecialchars($alert->patient_no); ?> |
                                            Result: <span class="text-danger"><?php echo htmlspecialchars($alert->result_value); ?></span> |
                                            <?php echo date('d-M H:i', strtotime($alert->created_at)); ?>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Delta Flags -->
            <div class="col-md-6">
                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-exchange"></i> Recent Delta Flags</h3>
                        <div class="box-tools pull-right">
                            <a href="<?php echo base_url(); ?>app/laboratory/delta_flags" class="btn btn-box-tool">
                                View All <i class="fa fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <div class="box-body no-padding">
                        <?php if (empty($delta_flags)): ?>
                            <div class="callout callout-success" style="margin:10px;">
                                <i class="fa fa-check"></i> No pending delta flags
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-unbordered">
                                <?php foreach (array_slice($delta_flags, 0, 5) as $flag): ?>
                                    <li class="list-group-item">
                                        <span class="label label-warning pull-right">
                                            <?php echo round($flag->delta_percent, 1); ?>% change
                                        </span>
                                        <strong><?php echo htmlspecialchars($flag->test_name ?? ''); ?></strong><br>
                                        <small class="text-muted">
                                            Previous: <?php echo htmlspecialchars($flag->previous_value ?? ''); ?> →
                                            Current: <span class="text-warning"><?php echo htmlspecialchars($flag->current_value); ?></span>
                                        </small>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safety Features Overview -->
        <div class="row">
            <div class="col-md-12">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-info-circle"></i> Patient Safety Features</h3>
                    </div>
                    <div class="box-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-box bg-red">
                                    <span class="info-box-icon"><i class="fa fa-exclamation-triangle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Critical Value Alerts</span>
                                        <span class="info-box-number">Automatic Detection</span>
                                        <span class="progress-description">
                                            Panic and critical values trigger immediate alerts
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-green">
                                    <span class="info-box-icon"><i class="fa fa-check-double"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Dual Verification</span>
                                        <span class="info-box-number">Two-Level Review</span>
                                        <span class="progress-description">
                                            Critical results require dual verification
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-yellow">
                                    <span class="info-box-icon"><i class="fa fa-exchange"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Delta Checks</span>
                                        <span class="info-box-number">Result Comparison</span>
                                        <span class="progress-description">
                                            Flags significant changes from previous results
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="info-box bg-aqua">
                                    <span class="info-box-icon"><i class="fa fa-barcode"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Sample Tracking</span>
                                        <span class="info-box-number">Full Lifecycle</span>
                                        <span class="progress-description">
                                            Track samples from collection to disposal
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-purple">
                                    <span class="info-box-icon"><i class="fa fa-copy"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Duplicate Detection</span>
                                        <span class="info-box-number">Order Validation</span>
                                        <span class="progress-description">
                                            Warns when same test ordered within 24 hours
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box bg-navy">
                                    <span class="info-box-icon"><i class="fa fa-history"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Audit Trail</span>
                                        <span class="info-box-number">Complete Logging</span>
                                        <span class="progress-description">
                                            All actions logged with user and timestamp
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    // Auto-refresh dashboard every 60 seconds
    setTimeout(function() {
        location.reload();
    }, 60000);
});
</script>

<?php $this->load->view('includes/footer'); ?>
