<?php $this->load->view('includes/header'); ?>

<div class="content-wrapper">
    <section class="content-header">
        <h1>
            <i class="fa fa-exclamation-triangle text-danger"></i> Critical Value Alerts
            <?php if (isset($alert_count) && $alert_count > 0): ?>
                <span class="badge bg-red"><?php echo $alert_count; ?> Pending</span>
            <?php endif; ?>
        </h1>
        <ol class="breadcrumb">
            <li><a href="<?php echo base_url(); ?>app/laboratory"><i class="fa fa-flask"></i> Laboratory</a></li>
            <li class="active">Critical Alerts</li>
        </ol>
    </section>

    <section class="content">
        <?php if (isset($message) && $message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-12">
                <div class="box box-danger">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-bell"></i> Pending Critical Alerts</h3>
                        <div class="box-tools pull-right">
                            <a href="<?php echo base_url(); ?>app/laboratory/safety_dashboard" class="btn btn-sm btn-default">
                                <i class="fa fa-dashboard"></i> Safety Dashboard
                            </a>
                        </div>
                    </div>
                    <div class="box-body">
                        <?php if (empty($pending_alerts)): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> No pending critical alerts. All alerts have been acknowledged.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped table-hover" id="alertsTable">
                                    <thead>
                                        <tr class="bg-danger">
                                            <th width="5%">ID</th>
                                            <th width="12%">Patient</th>
                                            <th width="15%">Test</th>
                                            <th width="10%">Result</th>
                                            <th width="10%">Alert Level</th>
                                            <th width="10%">Severity</th>
                                            <th width="12%">Ordering Doctor</th>
                                            <th width="12%">Created</th>
                                            <th width="8%">Escalated</th>
                                            <th width="6%">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_alerts as $alert): ?>
                                            <?php
                                            $severity_class = 'default';
                                            if ($alert->alert_severity === 'PANIC') $severity_class = 'danger';
                                            elseif ($alert->alert_severity === 'CRITICAL') $severity_class = 'danger';
                                            elseif ($alert->alert_severity === 'HIGH') $severity_class = 'warning';
                                            elseif ($alert->alert_severity === 'MEDIUM') $severity_class = 'info';

                                            $patient_name = trim(($alert->firstname ?? '') . ' ' . ($alert->lastname ?? ''));
                                            if (!$patient_name) $patient_name = $alert->patient_no;
                                            ?>
                                            <tr class="<?php echo $alert->escalated_flag ? 'bg-red-active' : ''; ?>">
                                                <td><?php echo $alert->alert_id; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($patient_name); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($alert->patient_no); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($alert->test_name ?? ''); ?></td>
                                                <td>
                                                    <strong class="text-danger"><?php echo htmlspecialchars($alert->result_value); ?></strong>
                                                    <?php if ($alert->unit): ?>
                                                        <small><?php echo htmlspecialchars($alert->unit); ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($alert->reference_range): ?>
                                                        <br><small class="text-muted">Ref: <?php echo htmlspecialchars($alert->reference_range); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="label label-<?php echo $severity_class; ?>">
                                                        <?php echo $alert->alert_level; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="label label-<?php echo $severity_class; ?>">
                                                        <?php echo $alert->alert_severity; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($alert->ordering_doctor_name ?? $alert->ordering_doctor_id ?? 'N/A'); ?></td>
                                                <td>
                                                    <?php echo date('d-M-Y H:i', strtotime($alert->created_at)); ?>
                                                    <br><small class="text-muted"><?php echo $this->general_model->time_elapsed_string($alert->created_at); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($alert->escalated_flag): ?>
                                                        <span class="label label-danger"><i class="fa fa-arrow-up"></i> YES</span>
                                                        <br><small>Level <?php echo $alert->escalation_level; ?></small>
                                                    <?php else: ?>
                                                        <span class="label label-default">No</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            data-toggle="modal" 
                                                            data-target="#ackModal<?php echo $alert->alert_id; ?>">
                                                        <i class="fa fa-check"></i> Ack
                                                    </button>
                                                </td>
                                            </tr>

                                            <!-- Acknowledge Modal -->
                                            <div class="modal fade" id="ackModal<?php echo $alert->alert_id; ?>" tabindex="-1">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="<?php echo base_url(); ?>app/laboratory/acknowledge_alert/<?php echo $alert->alert_id; ?>">
                                                            <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                            <div class="modal-header bg-danger">
                                                                <button type="button" class="close" data-dismiss="modal">&times;</button>
                                                                <h4 class="modal-title">
                                                                    <i class="fa fa-exclamation-triangle"></i> Acknowledge Critical Alert
                                                                </h4>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="alert alert-danger">
                                                                    <strong><?php echo htmlspecialchars($alert->alert_message ?? ''); ?></strong>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Patient:</label>
                                                                    <p class="form-control-static"><strong><?php echo htmlspecialchars($patient_name); ?></strong></p>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Test:</label>
                                                                    <p class="form-control-static"><?php echo htmlspecialchars($alert->test_name ?? ''); ?></p>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label>Result:</label>
                                                                    <p class="form-control-static text-danger"><strong><?php echo htmlspecialchars($alert->result_value); ?> <?php echo htmlspecialchars($alert->unit ?? ''); ?></strong></p>
                                                                </div>
                                                                <div class="form-group">
                                                                    <label for="notes">Acknowledgment Notes:</label>
                                                                    <textarea name="notes" class="form-control" rows="3" placeholder="Enter any notes about actions taken..."></textarea>
                                                                </div>
                                                                <div class="alert alert-warning">
                                                                    <i class="fa fa-info-circle"></i> By acknowledging this alert, you confirm that you have reviewed the critical value and taken appropriate clinical action.
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                                <button type="submit" class="btn btn-success">
                                                                    <i class="fa fa-check"></i> Acknowledge Alert
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Legend -->
        <div class="row">
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Alert Severity Legend</h3>
                    </div>
                    <div class="box-body">
                        <span class="label label-danger">PANIC</span> - Life-threatening value requiring immediate intervention<br><br>
                        <span class="label label-danger">CRITICAL</span> - Significantly abnormal value requiring urgent attention<br><br>
                        <span class="label label-warning">HIGH</span> - Abnormal value requiring prompt review<br><br>
                        <span class="label label-info">MEDIUM</span> - Mildly abnormal value for awareness
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title">Quick Actions</h3>
                    </div>
                    <div class="box-body">
                        <a href="<?php echo base_url(); ?>app/laboratory/lab_queue" class="btn btn-primary btn-block">
                            <i class="fa fa-list"></i> Lab Queue
                        </a>
                        <a href="<?php echo base_url(); ?>app/laboratory/delta_flags" class="btn btn-warning btn-block">
                            <i class="fa fa-exchange"></i> Delta Check Flags
                        </a>
                        <a href="<?php echo base_url(); ?>app/laboratory/sample_tracking" class="btn btn-info btn-block">
                            <i class="fa fa-barcode"></i> Sample Tracking
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
$(document).ready(function() {
    $('#alertsTable').DataTable({
        "order": [[7, "desc"]],
        "pageLength": 25,
        "language": {
            "emptyTable": "No pending critical alerts"
        }
    });

    // Auto-refresh every 30 seconds
    setTimeout(function() {
        location.reload();
    }, 30000);
});
</script>

<?php $this->load->view('includes/footer'); ?>
