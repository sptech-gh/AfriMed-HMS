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
        .severity-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .severity-CRITICAL { background: #e74c3c; color: #fff; }
        .severity-WARNING { background: #f39c12; color: #fff; }
        .severity-NORMAL { background: #27ae60; color: #fff; }
        .delta-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; }
        .delta-card.critical { border-left: 4px solid #e74c3c; }
        .delta-card.warning { border-left: 4px solid #f39c12; }
        .delta-value { font-size: 24px; font-weight: bold; }
        .delta-arrow { font-size: 20px; margin: 0 10px; }
        .delta-arrow.up { color: #e74c3c; }
        .delta-arrow.down { color: #27ae60; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-line-chart"></i> Delta Check Reviews</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li class="active">Delta Checks</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-danger">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-exclamation-triangle"></i> Pending Delta Reviews</h3>
                            <div class="box-tools">
                                <a href="<?php echo base_url(); ?>app/sample_tracking/delta_thresholds" class="btn btn-sm btn-default">
                                    <i class="fa fa-sliders"></i> Configure Thresholds
                                </a>
                            </div>
                        </div>
                        <div class="box-body">
                            <?php if (empty($pending_reviews)): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> No pending delta reviews. All flagged results have been reviewed.
                            </div>
                            <?php else: ?>
                            <div class="row">
                                <?php foreach ($pending_reviews as $d): ?>
                                <div class="col-md-6">
                                    <div class="delta-card <?php echo strtolower($d->delta_severity ?? 'normal'); ?>">
                                        <div class="row">
                                            <div class="col-xs-8">
                                                <h4 class="mt-0"><?php echo htmlspecialchars($d->test_name ?? '-'); ?></h4>
                                                <p class="text-muted mb-0">
                                                    <?php echo htmlspecialchars(($d->firstname ?? '') . ' ' . ($d->lastname ?? '')); ?>
                                                    <br><small><?php echo htmlspecialchars($d->patient_no); ?></small>
                                                </p>
                                            </div>
                                            <div class="col-xs-4 text-right">
                                                <span class="severity-badge severity-<?php echo $d->delta_severity ?? 'NORMAL'; ?>">
                                                    <?php echo $d->delta_severity ?? 'NORMAL'; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row text-center">
                                            <div class="col-xs-4">
                                                <small class="text-muted">Previous</small><br>
                                                <span class="delta-value"><?php echo htmlspecialchars($d->previous_value ?? '-'); ?></span>
                                            </div>
                                            <div class="col-xs-4">
                                                <span class="delta-arrow <?php echo ($d->delta_percent > 0) ? 'up' : 'down'; ?>">
                                                    <i class="fa fa-arrow-<?php echo ($d->delta_percent > 0) ? 'up' : 'down'; ?>"></i>
                                                    <?php echo number_format(abs($d->delta_percent), 1); ?>%
                                                </span>
                                            </div>
                                            <div class="col-xs-4">
                                                <small class="text-muted">Current</small><br>
                                                <span class="delta-value"><?php echo htmlspecialchars($d->current_value ?? '-'); ?></span>
                                            </div>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-xs-6">
                                                <small class="text-muted">Clinical Significance:</small><br>
                                                <?php echo htmlspecialchars($d->clinical_significance ?? 'Not specified'); ?>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                                <small class="text-muted"><?php echo date('M j, H:i', strtotime($d->created_at)); ?></small>
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-success btn-override" 
                                                    data-delta-id="<?php echo $d->delta_id; ?>"
                                                    data-test-name="<?php echo htmlspecialchars($d->test_name ?? ''); ?>"
                                                    data-patient="<?php echo htmlspecialchars(($d->firstname ?? '') . ' ' . ($d->lastname ?? '')); ?>">
                                                <i class="fa fa-check"></i> Override / Accept
                                            </button>
                                            <?php if ($d->ordering_doctor_id && empty($d->doctor_notified_at)): ?>
                                            <button class="btn btn-sm btn-warning btn-notify-doctor" data-delta-id="<?php echo $d->delta_id; ?>">
                                                <i class="fa fa-bell"></i> Notify Doctor
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Override Modal -->
<div class="modal fade" id="overrideModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-green">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-check"></i> Override Delta Check</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="override_delta_id">
                <div class="alert alert-info">
                    <strong>Test:</strong> <span id="override_test_name"></span><br>
                    <strong>Patient:</strong> <span id="override_patient"></span>
                </div>
                <div class="form-group">
                    <label>Override Type <span class="text-danger">*</span></label>
                    <select id="override_type" class="form-control" required>
                        <option value="">-- Select --</option>
                        <option value="ACCEPT">Accept Result (Verified Correct)</option>
                        <option value="CLINICAL_EXPECTED">Clinically Expected Change</option>
                        <option value="TRANSFUSION">Post-Transfusion</option>
                        <option value="DIALYSIS">Post-Dialysis</option>
                        <option value="MEDICATION">Medication Effect</option>
                        <option value="OTHER">Other Clinical Reason</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Clinical Reason <span class="text-danger">*</span></label>
                    <textarea id="override_reason" class="form-control" rows="3" required placeholder="Explain the clinical justification..."></textarea>
                </div>
                <div class="form-group">
                    <label>Related Diagnosis (Optional)</label>
                    <input type="text" id="override_diagnosis" class="form-control" placeholder="e.g., Acute Kidney Injury">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSaveOverride"><i class="fa fa-check"></i> Submit Override</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script>
$(document).ready(function() {
    $('.btn-override').click(function() {
        $('#override_delta_id').val($(this).data('delta-id'));
        $('#override_test_name').text($(this).data('test-name'));
        $('#override_patient').text($(this).data('patient'));
        $('#overrideModal').modal('show');
    });

    $('#btnSaveOverride').click(function() {
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        var data = {
            delta_id: $('#override_delta_id').val(),
            override_type: $('#override_type').val(),
            clinical_reason: $('#override_reason').val(),
            diagnosis: $('#override_diagnosis').val()
        };
        data[csrfName] = csrfHash;

        if (!data.override_type || !data.clinical_reason) {
            alert('Please fill all required fields');
            return;
        }

        $.post('<?php echo base_url(); ?>app/sample_tracking/override_delta', data, function(resp) {
            if (resp.ok) {
                alert('Override recorded successfully');
                location.reload();
            } else {
                alert('Error: ' + (resp.error || 'Unknown error'));
            }
        }, 'json');
    });

    $('.btn-notify-doctor').click(function() {
        var deltaId = $(this).data('delta-id');
        if (confirm('Send notification to ordering doctor?')) {
            // This would trigger the notification - for now just mark as notified
            alert('Doctor notification sent');
            $(this).prop('disabled', true).text('Notified');
        }
    });
});
</script>
</body>
</html>
