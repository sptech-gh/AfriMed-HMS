<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Sonography Critical Alerts - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .severity-badge { font-size: 11px; padding: 3px 8px; border-radius: 3px; }
        .severity-life_threatening { background: #dd4b39; color: #fff; }
        .severity-critical { background: #f39c12; color: #fff; }
        .severity-urgent { background: #00c0ef; color: #fff; }
        .finding-text { max-width: 300px; word-wrap: break-word; }
        .escalation-badge { font-size: 10px; padding: 2px 6px; border-radius: 2px; }
        .esc-1 { background: #3c8dbc; color: #fff; }
        .esc-2 { background: #f39c12; color: #fff; }
        .esc-3 { background: #dd4b39; color: #fff; }
        .esc-4 { background: #111; color: #fff; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-heartbeat text-red"></i> Sonography Critical Alerts</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/critical_alerts">Critical Alerts</a></li>
                    <li class="active">Sonography</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <a href="<?php echo base_url();?>app/critical_alerts" class="btn btn-default" style="margin-bottom: 15px;">
                            <i class="fa fa-arrow-left"></i> Back to Dashboard
                        </a>
                        <a href="<?php echo base_url();?>app/critical_alerts/sonography_definitions" class="btn btn-info" style="margin-bottom: 15px;">
                            <i class="fa fa-cog"></i> Manage Alert Definitions
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-danger">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-bell"></i> Pending Sonography Critical Alerts (<?php echo count($alerts); ?>)</h3>
                            </div>
                            <div class="box-body table-responsive no-padding">
                                <?php if (empty($alerts)): ?>
                                    <div class="alert alert-success" style="margin: 15px;">
                                        <i class="fa fa-check-circle"></i> No pending sonography critical alerts.
                                    </div>
                                <?php else: ?>
                                    <table class="table table-hover table-striped">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Severity</th>
                                                <th>Patient</th>
                                                <th>Alert Type</th>
                                                <th>Findings</th>
                                                <th>Sonographer</th>
                                                <th>Created</th>
                                                <th>Escalation</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $i = 1; foreach ($alerts as $alert): ?>
                                                <?php $sev = strtolower($alert['severity'] ?? 'critical'); ?>
                                                <tr>
                                                    <td><?php echo $i++; ?></td>
                                                    <td>
                                                        <span class="severity-badge severity-<?php echo $sev; ?>">
                                                            <?php echo strtoupper(str_replace('_', ' ', $sev)); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($alert['patient_name'] ?? 'N/A'); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($alert['patient_no'] ?? ''); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($alert['alert_name'] ?? 'N/A'); ?></td>
                                                    <td class="finding-text">
                                                        <?php echo htmlspecialchars(substr($alert['findings_text'] ?? '', 0, 150)); ?>
                                                        <?php if (strlen($alert['findings_text'] ?? '') > 150): ?>...<?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($alert['reported_by_name'] ?? $alert['reported_by'] ?? '-'); ?></td>
                                                    <td>
                                                        <?php echo date('M j, g:i A', strtotime($alert['created_at'])); ?>
                                                    </td>
                                                    <td>
                                                        <?php $esc = (int)($alert['escalation_level'] ?? 0); ?>
                                                        <?php if ($esc > 0): ?>
                                                            <span class="escalation-badge esc-<?php echo min($esc, 4); ?>">Level <?php echo $esc; ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <button type="button" class="btn btn-success btn-xs" 
                                                                onclick="acknowledgeAlert(<?php echo $alert['alert_id']; ?>)">
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
            </section>
        </aside>
    </div>

    <!-- Acknowledge Modal -->
    <div class="modal fade" id="acknowledgeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-check-circle"></i> Acknowledge Sonography Critical Alert</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="ack_alert_id">
                    <div class="form-group">
                        <label>Acknowledgment Notes (optional)</label>
                        <textarea id="ack_notes" class="form-control" rows="3" placeholder="Enter any notes..."></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fa fa-info-circle"></i> By acknowledging, you confirm review of this critical sonography finding.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="submitAcknowledge()">
                        <i class="fa fa-check"></i> Confirm
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
        function acknowledgeAlert(alertId) {
            $('#ack_alert_id').val(alertId);
            $('#ack_notes').val('');
            $('#acknowledgeModal').modal('show');
        }

        function submitAcknowledge() {
            var alertId = $('#ack_alert_id').val();
            var notes = $('#ack_notes').val();

            var ackData = { alert_id: alertId, notes: notes };
            ackData[csrfName] = csrfHash;
            $.post('<?php echo base_url();?>app/critical_alerts/acknowledge_sonography', ackData, function(response) {
                var res = typeof response === 'string' ? JSON.parse(response) : response;
                if (res.success) {
                    $('#acknowledgeModal').modal('hide');
                    location.reload();
                } else {
                    alert('Error: ' + res.message);
                }
            });
        }

        setTimeout(function() { location.reload(); }, 60000);
    </script>
</body>
</html>
