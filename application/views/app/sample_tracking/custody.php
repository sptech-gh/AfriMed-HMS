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
        .scan-box { background: #f8f9fa; border: 2px dashed #dee2e6; border-radius: 10px; padding: 30px; text-align: center; margin-bottom: 20px; }
        .scan-box input { font-size: 24px; text-align: center; max-width: 400px; }
        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .status-REQUESTED { background: #3498db; color: #fff; }
        .status-COLLECTED { background: #f39c12; color: #fff; }
        .status-RECEIVED_LAB { background: #9b59b6; color: #fff; }
        .status-IN_PROCESS { background: #1abc9c; color: #fff; }
        .status-RESULT_READY { background: #27ae60; color: #fff; }
        .status-VERIFIED { background: #2ecc71; color: #fff; }
        .status-REJECTED { background: #e74c3c; color: #fff; }
        .temp-normal { color: #27ae60; }
        .temp-warning { color: #f39c12; }
        .temp-critical { color: #e74c3c; }
        .sample-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; }
        .sample-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .sample-card .barcode { font-family: monospace; font-size: 16px; font-weight: bold; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-exchange"></i> Chain of Custody</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li class="active">Chain of Custody</li>
            </ol>
        </section>

        <section class="content">
            <!-- Barcode Scanner -->
            <div class="row">
                <div class="col-md-12">
                    <div class="scan-box">
                        <h4><i class="fa fa-barcode"></i> Scan Sample Barcode</h4>
                        <input type="text" id="barcode_input" class="form-control" placeholder="Scan or enter barcode..." autofocus>
                        <p class="text-muted mt-2"><small>Scan barcode or type and press Enter</small></p>
                        <div id="scan_result" class="mt-3"></div>
                    </div>
                </div>
            </div>

            <!-- Active Samples -->
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-flask"></i> Active Samples</h3>
                            <div class="box-tools">
                                <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#handoffModal">
                                    <i class="fa fa-exchange"></i> Record Handoff
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <div class="table-responsive">
                                <table class="table table-hover table-striped" id="samplesTable">
                                    <thead>
                                        <tr>
                                            <th>Barcode</th>
                                            <th>Patient</th>
                                            <th>Test</th>
                                            <th>Status</th>
                                            <th>Location</th>
                                            <th>Temp</th>
                                            <th>Handoffs</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($samples)): ?>
                                        <tr><td colspan="8" class="text-center text-muted">No active samples</td></tr>
                                        <?php else: foreach ($samples as $s): ?>
                                        <tr data-sample-id="<?php echo $s->sample_id; ?>" data-barcode="<?php echo htmlspecialchars($s->sample_barcode); ?>">
                                            <td><code class="barcode"><?php echo htmlspecialchars($s->sample_barcode); ?></code></td>
                                            <td><?php echo htmlspecialchars(($s->firstname ?? '') . ' ' . ($s->lastname ?? '')); ?><br><small class="text-muted"><?php echo htmlspecialchars($s->patient_no); ?></small></td>
                                            <td><?php echo htmlspecialchars($s->test_name ?? '-'); ?></td>
                                            <td><span class="status-badge status-<?php echo $s->sample_status; ?>"><?php echo $s->sample_status; ?></span></td>
                                            <td><?php echo htmlspecialchars($s->location_name ?? $s->current_location_code ?? '-'); ?></td>
                                            <td>
                                                <?php if ($s->last_temperature !== null): ?>
                                                <span class="<?php echo $s->temperature_breach_flag ? 'temp-critical' : 'temp-normal'; ?>">
                                                    <?php echo number_format($s->last_temperature, 1); ?>°C
                                                    <?php if ($s->temperature_breach_flag): ?><i class="fa fa-exclamation-triangle"></i><?php endif; ?>
                                                </span>
                                                <?php else: ?>-<?php endif; ?>
                                            </td>
                                            <td><?php echo $s->total_handoffs ?? 0; ?></td>
                                            <td>
                                                <a href="<?php echo base_url(); ?>app/sample_tracking/custody_chain/<?php echo $s->sample_id; ?>" class="btn btn-xs btn-info" title="View Chain">
                                                    <i class="fa fa-link"></i>
                                                </a>
                                                <button class="btn btn-xs btn-success btn-handoff" data-sample-id="<?php echo $s->sample_id; ?>" data-barcode="<?php echo htmlspecialchars($s->sample_barcode); ?>" title="Handoff">
                                                    <i class="fa fa-exchange"></i>
                                                </button>
                                                <button class="btn btn-xs btn-warning btn-temp" data-sample-id="<?php echo $s->sample_id; ?>" data-barcode="<?php echo htmlspecialchars($s->sample_barcode); ?>" title="Log Temperature">
                                                    <i class="fa fa-thermometer-half"></i>
                                                </button>
                                                <button class="btn btn-xs btn-danger btn-reject" data-sample-id="<?php echo $s->sample_id; ?>" title="Reject">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Handoff Modal -->
<div class="modal fade" id="handoffModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-green">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-exchange"></i> Record Sample Handoff</h4>
            </div>
            <div class="modal-body">
                <form id="handoffForm">
                    <input type="hidden" id="handoff_sample_id" name="sample_id">
                    <div class="form-group">
                        <label>Sample Barcode</label>
                        <input type="text" id="handoff_barcode" class="form-control" readonly>
                    </div>
                    <div class="form-group">
                        <label>Handoff Type <span class="text-danger">*</span></label>
                        <select name="handoff_type" id="handoff_type" class="form-control" required>
                            <option value="">-- Select --</option>
                            <option value="COLLECTION">Collection</option>
                            <option value="TRANSPORT">Transport</option>
                            <option value="RECEIVE">Receive at Lab</option>
                            <option value="PROCESS">Processing</option>
                            <option value="STORAGE">Storage</option>
                            <option value="DISPOSAL">Disposal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Receiving User <span class="text-danger">*</span></label>
                        <select name="to_user_id" id="to_user_id" class="form-control" required>
                            <option value="">-- Select User --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Destination Location <span class="text-danger">*</span></label>
                        <select name="to_location" id="to_location" class="form-control" required>
                            <option value="">-- Select Location --</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc->location_code); ?>"><?php echo htmlspecialchars($loc->location_name); ?> (<?php echo $loc->location_type; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Temperature (°C)</label>
                        <input type="number" step="0.1" name="temperature" id="handoff_temperature" class="form-control" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" id="handoff_notes" class="form-control" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="btnSaveHandoff"><i class="fa fa-check"></i> Record Handoff</button>
            </div>
        </div>
    </div>
</div>

<!-- Temperature Modal -->
<div class="modal fade" id="tempModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-yellow">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-thermometer-half"></i> Log Temperature</h4>
            </div>
            <div class="modal-body">
                <form id="tempForm">
                    <input type="hidden" id="temp_sample_id" name="sample_id">
                    <input type="hidden" id="temp_barcode" name="barcode">
                    <div class="form-group">
                        <label>Temperature (°C) <span class="text-danger">*</span></label>
                        <input type="number" step="0.1" name="temperature" id="temp_value" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location" id="temp_location" class="form-control">
                            <option value="">-- Select --</option>
                            <?php foreach ($locations as $loc): ?>
                            <option value="<?php echo htmlspecialchars($loc->location_code); ?>"><?php echo htmlspecialchars($loc->location_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Humidity (%)</label>
                        <input type="number" step="0.1" name="humidity" id="temp_humidity" class="form-control">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="btnSaveTemp"><i class="fa fa-check"></i> Log</button>
            </div>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-red">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-times"></i> Reject Sample</h4>
            </div>
            <form action="<?php echo base_url(); ?>app/sample_tracking/reject_sample" method="post">
                <div class="modal-body">
                    <input type="hidden" id="reject_sample_id" name="sample_id">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-danger">*</span></label>
                        <select name="reason_id" class="form-control" required>
                            <option value="">-- Select Reason --</option>
                            <?php 
                            $reasons = isset($this->diagnostic_safety_model) ? $this->diagnostic_safety_model->get_rejection_reasons() : [];
                            foreach ($reasons as $r): ?>
                            <option value="<?php echo $r->reason_id; ?>"><?php echo htmlspecialchars($r->reason_name); ?> (<?php echo $r->reason_category; ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority for Recollection</label>
                        <select name="priority" class="form-control">
                            <option value="ROUTINE">Routine</option>
                            <option value="URGENT">Urgent</option>
                            <option value="STAT">STAT</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Reject Sample</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script>
$(document).ready(function() {
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    // Load users for handoff
    $.get('<?php echo base_url(); ?>app/sample_tracking/get_users', function(resp) {
        if (resp.ok && resp.users) {
            var opts = '<option value="">-- Select User --</option>';
            resp.users.forEach(function(u) {
                opts += '<option value="' + u.user_id + '">' + u.username + ' (' + (u.firstname || '') + ' ' + (u.lastname || '') + ')</option>';
            });
            $('#to_user_id').html(opts);
        }
    }, 'json');

    // Barcode scanner
    $('#barcode_input').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            var barcode = $(this).val().trim();
            if (barcode) {
                scanBarcode(barcode);
            }
        }
    });

    function scanBarcode(barcode) {
        var postData = {barcode: barcode};
        postData[csrfName] = csrfHash;
        $.post('<?php echo base_url(); ?>app/sample_tracking/scan_barcode', postData, function(resp) {
            if (resp.ok) {
                var s = resp.sample;
                var html = '<div class="alert alert-success">';
                html += '<strong>Sample Found:</strong> ' + s.sample_barcode + '<br>';
                html += '<strong>Patient:</strong> ' + resp.patient_name + '<br>';
                html += '<strong>Test:</strong> ' + (s.test_name || '-') + '<br>';
                html += '<strong>Status:</strong> ' + s.sample_status + '<br>';
                html += '<a href="<?php echo base_url(); ?>app/sample_tracking/custody_chain/' + s.sample_id + '" class="btn btn-sm btn-info mt-2">View Chain</a> ';
                html += '<button class="btn btn-sm btn-success mt-2" onclick="openHandoff(' + s.sample_id + ', \'' + s.sample_barcode + '\')">Handoff</button>';
                html += '</div>';
                $('#scan_result').html(html);
            } else {
                $('#scan_result').html('<div class="alert alert-danger">' + resp.error + '</div>');
            }
            $('#barcode_input').val('').focus();
        }, 'json');
    }

    // Handoff button
    $('.btn-handoff').click(function() {
        var sampleId = $(this).data('sample-id');
        var barcode = $(this).data('barcode');
        openHandoff(sampleId, barcode);
    });

    window.openHandoff = function(sampleId, barcode) {
        $('#handoff_sample_id').val(sampleId);
        $('#handoff_barcode').val(barcode);
        $('#handoffModal').modal('show');
    };

    // Save handoff
    $('#btnSaveHandoff').click(function() {
        var data = {
            sample_id: $('#handoff_sample_id').val(),
            to_user_id: $('#to_user_id').val(),
            to_location: $('#to_location').val(),
            handoff_type: $('#handoff_type').val(),
            temperature: $('#handoff_temperature').val(),
            notes: $('#handoff_notes').val()
        };

        if (!data.to_user_id || !data.to_location || !data.handoff_type) {
            alert('Please fill all required fields');
            return;
        }
        data[csrfName] = csrfHash;

        $.post('<?php echo base_url(); ?>app/sample_tracking/record_handoff', data, function(resp) {
            if (resp.ok) {
                alert('Handoff recorded successfully! Sequence #' + resp.sequence);
                location.reload();
            } else {
                alert('Error: ' + resp.error);
            }
        }, 'json');
    });

    // Temperature button
    $('.btn-temp').click(function() {
        var sampleId = $(this).data('sample-id');
        var barcode = $(this).data('barcode');
        $('#temp_sample_id').val(sampleId);
        $('#temp_barcode').val(barcode);
        $('#tempModal').modal('show');
    });

    // Save temperature
    $('#btnSaveTemp').click(function() {
        var data = {
            sample_id: $('#temp_sample_id').val(),
            barcode: $('#temp_barcode').val(),
            location: $('#temp_location').val(),
            temperature: $('#temp_value').val(),
            humidity: $('#temp_humidity').val()
        };

        if (!data.temperature) {
            alert('Temperature is required');
            return;
        }
        data[csrfName] = csrfHash;

        $.post('<?php echo base_url(); ?>app/sample_tracking/log_temperature', data, function(resp) {
            if (resp.ok) {
                var msg = 'Temperature logged. Status: ' + resp.status;
                if (resp.status === 'CRITICAL' || resp.status === 'BREACH') {
                    msg = 'WARNING: ' + msg;
                }
                alert(msg);
                location.reload();
            } else {
                alert('Error: ' + resp.error);
            }
        }, 'json');
    });

    // Reject button
    $('.btn-reject').click(function() {
        var sampleId = $(this).data('sample-id');
        $('#reject_sample_id').val(sampleId);
        $('#rejectModal').modal('show');
    });
});
</script>
</body>
</html>
