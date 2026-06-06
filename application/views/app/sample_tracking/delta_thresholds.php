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
        .threshold-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; }
        .threshold-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .threshold-card.inactive { opacity: 0.6; background: #f5f5f5; }
        .threshold-values { display: flex; gap: 20px; margin-top: 10px; }
        .threshold-values .item { text-align: center; }
        .threshold-values .value { font-size: 18px; font-weight: bold; }
        .threshold-values .label-text { font-size: 11px; color: #7f8c8d; }
        .warning-color { color: #f39c12; }
        .critical-color { color: #e74c3c; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-sliders"></i> Delta Check Thresholds</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li class="active">Delta Thresholds</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Test-Specific Delta Thresholds</h3>
                            <div class="box-tools">
                                <button class="btn btn-sm btn-success" id="btnAddThreshold">
                                    <i class="fa fa-plus"></i> Add Threshold
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="row">
                                <?php if (empty($thresholds)): ?>
                                <div class="col-md-12">
                                    <div class="alert alert-info">
                                        <i class="fa fa-info-circle"></i> No delta thresholds configured. Click "Add Threshold" to create one.
                                    </div>
                                </div>
                                <?php else: foreach ($thresholds as $t): ?>
                                <div class="col-md-4">
                                    <div class="threshold-card <?php echo $t->is_active ? '' : 'inactive'; ?>">
                                        <div class="row">
                                            <div class="col-xs-8">
                                                <h4 class="mt-0 mb-0"><?php echo htmlspecialchars($t->test_name); ?></h4>
                                                <small class="text-muted"><?php echo htmlspecialchars($t->test_code ?? ''); ?></small>
                                            </div>
                                            <div class="col-xs-4 text-right">
                                                <?php if ($t->is_active): ?>
                                                <span class="label label-success">Active</span>
                                                <?php else: ?>
                                                <span class="label label-default">Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="threshold-values">
                                            <div class="item">
                                                <div class="value warning-color"><?php echo $t->delta_percent_warning; ?>%</div>
                                                <div class="label-text">Warning %</div>
                                            </div>
                                            <div class="item">
                                                <div class="value critical-color"><?php echo $t->delta_percent_critical; ?>%</div>
                                                <div class="label-text">Critical %</div>
                                            </div>
                                            <?php if ($t->delta_absolute_warning): ?>
                                            <div class="item">
                                                <div class="value warning-color"><?php echo $t->delta_absolute_warning; ?></div>
                                                <div class="label-text">Abs Warning</div>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($t->delta_absolute_critical): ?>
                                            <div class="item">
                                                <div class="value critical-color"><?php echo $t->delta_absolute_critical; ?></div>
                                                <div class="label-text">Abs Critical</div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <hr>
                                        <div class="row">
                                            <div class="col-xs-6">
                                                <small>
                                                    <i class="fa fa-clock-o"></i> <?php echo $t->time_window_hours; ?>h window<br>
                                                    <?php if ($t->unit): ?><i class="fa fa-tag"></i> <?php echo htmlspecialchars($t->unit); ?><?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="col-xs-6 text-right">
                                                <?php if ($t->auto_notify_doctor): ?>
                                                <span class="label label-info" title="Auto-notify doctor"><i class="fa fa-bell"></i></span>
                                                <?php endif; ?>
                                                <?php if ($t->requires_review): ?>
                                                <span class="label label-warning" title="Requires review"><i class="fa fa-eye"></i></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <?php if ($t->clinical_significance): ?>
                                        <div class="mt-2">
                                            <small class="text-muted"><?php echo htmlspecialchars($t->clinical_significance); ?></small>
                                        </div>
                                        <?php endif; ?>
                                        <div class="mt-3">
                                            <button class="btn btn-xs btn-primary btn-edit" 
                                                    data-threshold='<?php echo json_encode($t); ?>'>
                                                <i class="fa fa-edit"></i> Edit
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Threshold Modal -->
<div class="modal fade" id="thresholdModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-sliders"></i> <span id="modalTitle">Add Delta Threshold</span></h4>
            </div>
            <div class="modal-body">
                <form id="thresholdForm">
                    <input type="hidden" id="threshold_id" name="threshold_id">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Test Name <span class="text-danger">*</span></label>
                                <input type="text" id="test_name" name="test_name" class="form-control" required placeholder="e.g., Hemoglobin">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Test Code</label>
                                <input type="text" id="test_code" name="test_code" class="form-control" placeholder="e.g., HGB">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Unit</label>
                                <input type="text" id="unit" name="unit" class="form-control" placeholder="e.g., g/dL">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Warning % <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" id="delta_percent_warning" name="delta_percent_warning" class="form-control" required placeholder="e.g., 20">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Critical % <span class="text-danger">*</span></label>
                                <input type="number" step="0.1" id="delta_percent_critical" name="delta_percent_critical" class="form-control" required placeholder="e.g., 50">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Absolute Warning</label>
                                <input type="number" step="0.01" id="delta_absolute_warning" name="delta_absolute_warning" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label>Absolute Critical</label>
                                <input type="number" step="0.01" id="delta_absolute_critical" name="delta_absolute_critical" class="form-control" placeholder="Optional">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Time Window (Hours)</label>
                                <input type="number" id="time_window_hours" name="time_window_hours" class="form-control" value="72">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="form-group">
                                <label>Clinical Significance</label>
                                <input type="text" id="clinical_significance" name="clinical_significance" class="form-control" placeholder="e.g., May indicate acute blood loss">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" id="auto_notify_doctor" name="auto_notify_doctor" value="1"> Auto-notify ordering doctor
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" id="requires_review" name="requires_review" value="1" checked> Requires manual review
                                </label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="checkbox">
                                <label>
                                    <input type="checkbox" id="is_active" name="is_active" value="1" checked> Active
                                </label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="btnSaveThreshold"><i class="fa fa-save"></i> Save Threshold</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script>
$(document).ready(function() {
    function resetForm() {
        $('#thresholdForm')[0].reset();
        $('#threshold_id').val('');
        $('#modalTitle').text('Add Delta Threshold');
        $('#requires_review').prop('checked', true);
        $('#is_active').prop('checked', true);
    }

    $('#btnAddThreshold').click(function() {
        resetForm();
        $('#thresholdModal').modal('show');
    });

    $('.btn-edit').click(function() {
        var t = $(this).data('threshold');
        $('#modalTitle').text('Edit Delta Threshold');
        $('#threshold_id').val(t.threshold_id);
        $('#test_name').val(t.test_name);
        $('#test_code').val(t.test_code);
        $('#unit').val(t.unit);
        $('#delta_percent_warning').val(t.delta_percent_warning);
        $('#delta_percent_critical').val(t.delta_percent_critical);
        $('#delta_absolute_warning').val(t.delta_absolute_warning);
        $('#delta_absolute_critical').val(t.delta_absolute_critical);
        $('#time_window_hours').val(t.time_window_hours);
        $('#clinical_significance').val(t.clinical_significance);
        $('#auto_notify_doctor').prop('checked', t.auto_notify_doctor == 1);
        $('#requires_review').prop('checked', t.requires_review == 1);
        $('#is_active').prop('checked', t.is_active == 1);
        $('#thresholdModal').modal('show');
    });

    $('#btnSaveThreshold').click(function() {
        var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
        var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
        var data = {
            threshold_id: $('#threshold_id').val(),
            test_name: $('#test_name').val(),
            test_code: $('#test_code').val(),
            unit: $('#unit').val(),
            delta_percent_warning: $('#delta_percent_warning').val(),
            delta_percent_critical: $('#delta_percent_critical').val(),
            delta_absolute_warning: $('#delta_absolute_warning').val(),
            delta_absolute_critical: $('#delta_absolute_critical').val(),
            time_window_hours: $('#time_window_hours').val(),
            clinical_significance: $('#clinical_significance').val(),
            auto_notify_doctor: $('#auto_notify_doctor').is(':checked') ? 1 : 0,
            requires_review: $('#requires_review').is(':checked') ? 1 : 0,
            is_active: $('#is_active').is(':checked') ? 1 : 0
        };
        data[csrfName] = csrfHash;

        if (!data.test_name || !data.delta_percent_warning || !data.delta_percent_critical) {
            alert('Please fill all required fields');
            return;
        }

        $.post('<?php echo base_url(); ?>app/sample_tracking/save_threshold', data, function(resp) {
            if (resp.ok) {
                alert('Threshold saved successfully');
                location.reload();
            } else {
                alert('Error saving threshold');
            }
        }, 'json');
    });
});
</script>
</body>
</html>
