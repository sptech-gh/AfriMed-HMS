<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Critical Alerts Dashboard - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .alert-card { border-left: 4px solid; margin-bottom: 10px; }
        .alert-life-threatening { border-left-color: #dd4b39 !important; background: #fff5f5; }
        .alert-critical { border-left-color: #f39c12 !important; background: #fffbf0; }
        .alert-urgent { border-left-color: #00c0ef !important; background: #f0faff; }
        .severity-badge { font-size: 11px; padding: 3px 8px; border-radius: 3px; }
        .severity-life_threatening, .severity-panic { background: #dd4b39; color: #fff; }
        .severity-critical { background: #f39c12; color: #fff; }
        .severity-urgent { background: #00c0ef; color: #fff; }
        .pulse-animation { animation: pulse 1.5s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
        .stat-box { text-align: center; padding: 20px; border-radius: 5px; margin-bottom: 15px; }
        .stat-box h2 { margin: 0; font-size: 36px; font-weight: bold; }
        .stat-box p { margin: 5px 0 0; font-size: 14px; }
        .bg-life-threatening { background: linear-gradient(135deg, #dd4b39, #c0392b); color: #fff; }
        .bg-critical-orange { background: linear-gradient(135deg, #f39c12, #e67e22); color: #fff; }
        .bg-urgent-blue { background: linear-gradient(135deg, #00c0ef, #0073b7); color: #fff; }
        .bg-amendments { background: linear-gradient(135deg, #605ca8, #4b4898); color: #fff; }
        .alert-table th { background: #f5f5f5; }
        .btn-acknowledge { padding: 3px 10px; font-size: 12px; }
        .escalation-level { font-size: 10px; padding: 2px 6px; border-radius: 2px; background: #666; color: #fff; }
        .escalation-level-1 { background: #3c8dbc; }
        .escalation-level-2 { background: #f39c12; }
        .escalation-level-3 { background: #dd4b39; }
        .escalation-level-4 { background: #111; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-exclamation-triangle text-red"></i> Critical Alerts Dashboard</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Critical Alerts</li>
                </ol>
            </section>

            <section class="content">
                <!-- Summary Stats -->
                <div class="row">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-life-threatening <?php echo $stats['life_threatening'] ?? 0 > 0 ? 'pulse-animation' : ''; ?>">
                            <h2><?php echo $stats['life_threatening'] ?? 0; ?></h2>
                            <p><i class="fa fa-heartbeat"></i> Life-Threatening</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-critical-orange">
                            <h2><?php echo $stats['critical'] ?? 0; ?></h2>
                            <p><i class="fa fa-exclamation-circle"></i> Critical</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-urgent-blue">
                            <h2><?php echo $stats['urgent'] ?? 0; ?></h2>
                            <p><i class="fa fa-clock-o"></i> Urgent</p>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-box bg-amendments">
                            <h2><?php echo $stats['amendments_pending'] ?? 0; ?></h2>
                            <p><i class="fa fa-edit"></i> Pending Amendments</p>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="btn-group" style="margin-bottom: 15px;">
                            <a href="<?php echo base_url();?>app/critical_alerts/lab_alerts" class="btn btn-default">
                                <i class="fa fa-flask"></i> Lab Alerts <span class="badge bg-red"><?php echo $stats['lab_count']; ?></span>
                            </a>
                            <a href="<?php echo base_url();?>app/critical_alerts/radiology_alerts" class="btn btn-default">
                                <i class="fa fa-x-ray"></i> Radiology <span class="badge bg-red"><?php echo $stats['radiology_count']; ?></span>
                            </a>
                            <a href="<?php echo base_url();?>app/critical_alerts/sonography_alerts" class="btn btn-default">
                                <i class="fa fa-heartbeat"></i> Sonography <span class="badge bg-red"><?php echo $stats['sonography_count']; ?></span>
                            </a>
                            <a href="<?php echo base_url();?>app/critical_alerts/amendments" class="btn btn-default">
                                <i class="fa fa-edit"></i> Amendments <span class="badge bg-purple"><?php echo $stats['amendments_pending']; ?></span>
                            </a>
                            <a href="<?php echo base_url();?>app/critical_alerts/escalation_config" class="btn btn-default">
                                <i class="fa fa-cogs"></i> Escalation Config
                            </a>
                        </div>
                    </div>
                </div>

                <!-- All Pending Alerts Table -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-bell"></i> All Pending Critical Alerts (<?php echo $stats['total_pending']; ?>)</h3>
                                <div class="box-tools pull-right">
                                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                                </div>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <?php if (empty($all_alerts)): ?>
                                    <div class="alert alert-success" style="margin: 15px;">
                                        <i class="fa fa-check-circle"></i> No pending critical alerts. All alerts have been acknowledged.
                                    </div>
                                <?php else: ?>
                                    <table class="table table-hover table-striped alert-table">
                                        <thead>
                                            <tr>
                                                <th width="80">Type</th>
                                                <th width="100">Severity</th>
                                                <th>Patient</th>
                                                <th>Test/Finding</th>
                                                <th>Value/Description</th>
                                                <th width="120">Created</th>
                                                <th width="80">Escalation</th>
                                                <th width="120">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($all_alerts as $alert): ?>
                                                <?php 
                                                    $sev = strtolower($alert['severity'] ?? 'urgent');
                                                    $sev_class = ($sev === 'life_threatening' || $sev === 'panic') ? 'life_threatening' : $sev;
                                                    $esc_level = (int)($alert['escalation_level'] ?? 0);
                                                ?>
                                                <tr class="alert-<?php echo $sev_class; ?>">
                                                    <td>
                                                        <?php if ($alert['alert_type'] === 'LAB'): ?>
                                                            <span class="label label-primary"><i class="fa fa-flask"></i> Lab</span>
                                                        <?php elseif ($alert['alert_type'] === 'RADIOLOGY'): ?>
                                                            <span class="label label-info"><i class="fa fa-x-ray"></i> Radiology</span>
                                                        <?php else: ?>
                                                            <span class="label label-warning"><i class="fa fa-heartbeat"></i> Sono</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="severity-badge severity-<?php echo $sev_class; ?>">
                                                            <?php echo strtoupper(str_replace('_', ' ', $sev)); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($alert['patient_name'] ?? 'N/A'); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($alert['patient_no'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($alert['test_name'] ?? $alert['finding_name'] ?? $alert['alert_name'] ?? 'N/A'); ?></td>
                                                    <td>
                                                        <?php if (!empty($alert['result_value'])): ?>
                                                            <strong class="text-red"><?php echo htmlspecialchars($alert['result_value']); ?></strong>
                                                            <?php if (!empty($alert['normal_range'])): ?>
                                                                <br><small class="text-muted">Normal: <?php echo htmlspecialchars($alert['normal_range']); ?></small>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars(substr($alert['description'] ?? $alert['findings_text'] ?? '', 0, 80)); ?>...
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?>
                                                        <br><small class="text-muted"><?php echo $this->general->time_elapsed_string($alert['created_at']); ?></small>
                                                    </td>
                                                    <td>
                                                        <?php if ($esc_level > 0): ?>
                                                            <span class="escalation-level escalation-level-<?php echo min($esc_level, 4); ?>">
                                                                Level <?php echo $esc_level; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-success btn-acknowledge btn-xs" 
                                                                onclick="acknowledgeAlert('<?php echo $alert['alert_type']; ?>', <?php echo $alert['alert_id']; ?>)">
                                                            <i class="fa fa-check"></i> Acknowledge
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pending Amendments -->
                <?php if (!empty($pending_amendments)): ?>
                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-purple">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-edit"></i> Pending Result Amendments</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Type</th>
                                            <th>Patient</th>
                                            <th>Original Result</th>
                                            <th>Amended Result</th>
                                            <th>Reason</th>
                                            <th>Requested By</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_amendments as $amend): ?>
                                            <tr>
                                                <td><span class="label label-default"><?php echo htmlspecialchars($amend->diagnostic_type); ?></span></td>
                                                <td><?php echo htmlspecialchars($amend->patient_no ?? 'N/A'); ?></td>
                                                <td><code><?php echo htmlspecialchars(substr($amend->original_result, 0, 50)); ?></code></td>
                                                <td><code class="text-success"><?php echo htmlspecialchars(substr($amend->amended_result, 0, 50)); ?></code></td>
                                                <td><?php echo htmlspecialchars($amend->amendment_reason); ?></td>
                                                <td><?php echo htmlspecialchars($amend->requested_by_name ?? $amend->requested_by); ?></td>
                                                <td><?php echo date('M j, Y g:i A', strtotime($amend->requested_at)); ?></td>
                                                <td>
                                                    <button class="btn btn-success btn-xs" onclick="approveAmendment(<?php echo $amend->amendment_id; ?>)">
                                                        <i class="fa fa-check"></i> Approve
                                                    </button>
                                                    <button class="btn btn-danger btn-xs" onclick="rejectAmendment(<?php echo $amend->amendment_id; ?>)">
                                                        <i class="fa fa-times"></i> Reject
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </section>
        </aside>
    </div>

    <!-- Acknowledge Modal -->
    <div class="modal fade" id="acknowledgeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-check-circle"></i> Acknowledge Critical Alert</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ack_alert_type">
                    <input type="hidden" id="ack_alert_id">
                    <div class="form-group">
                        <label>Acknowledgment Notes (optional)</label>
                        <textarea id="ack_notes" class="form-control" rows="3" placeholder="Enter any notes about this acknowledgment..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i> By acknowledging this alert, you confirm that you have reviewed the critical finding and taken appropriate clinical action.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitAcknowledge()">
                        <i class="fa fa-check"></i> Confirm Acknowledgment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Amendment Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-red">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-times-circle"></i> Reject Amendment</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="reject_amendment_id">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-red">*</span></label>
                        <textarea id="reject_reason" class="form-control" rows="3" placeholder="Enter reason for rejection..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitReject()">
                        <i class="fa fa-times"></i> Reject Amendment
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js"></script>
    <script>
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        function acknowledgeAlert(type, alertId) {
            $('#ack_alert_type').val(type);
            $('#ack_alert_id').val(alertId);
            $('#ack_notes').val('');
            $('#acknowledgeModal').modal('show');
        }

        function submitAcknowledge() {
            var type = $('#ack_alert_type').val();
            var alertId = $('#ack_alert_id').val();
            var notes = $('#ack_notes').val();
            var url = '';

            if (type === 'LAB') {
                url = '<?php echo base_url();?>app/critical_alerts/acknowledge_lab';
            } else if (type === 'RADIOLOGY') {
                url = '<?php echo base_url();?>app/critical_alerts/acknowledge_radiology';
            } else {
                url = '<?php echo base_url();?>app/critical_alerts/acknowledge_sonography';
            }

            var ackData = { alert_id: alertId, notes: notes };
            ackData[csrfName] = csrfHash;
            $.post(url, ackData, function(response) {
                var res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.success) {
                    $('#acknowledgeModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            });
        }

        function approveAmendment(amendmentId) {
            if (!confirm('Are you sure you want to approve this amendment?')) return;
            
            var approveData = { amendment_id: amendmentId };
            approveData[csrfName] = csrfHash;
            $.post('<?php echo base_url();?>app/critical_alerts/approve_amendment', approveData, function(response) {
                var res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.success) {
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            });
        }

        function rejectAmendment(amendmentId) {
            $('#reject_amendment_id').val(amendmentId);
            $('#reject_reason').val('');
            $('#rejectModal').modal('show');
        }

        function submitReject() {
            var amendmentId = $('#reject_amendment_id').val();
            var reason = $('#reject_reason').val();
            
            if (!reason.trim()) {
                alert('Please enter a rejection reason');
                return;
            }

            var rejectData = { amendment_id: amendmentId, reason: reason };
            rejectData[csrfName] = csrfHash;
            $.post('<?php echo base_url();?>app/critical_alerts/reject_amendment', rejectData, function(response) {
                var res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.success) {
                    $('#rejectModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            });
        }

        // Auto-refresh every 60 seconds
        setTimeout(function() { location.reload(); }, 60000);
    </script>
</body>
</html>
