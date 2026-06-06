<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>NHIS Audit Log - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

    <style>
        .action-badge { font-size: 10px; }
        .table td { vertical-align: middle !important; }
        pre.json-display { max-height: 200px; overflow: auto; font-size: 11px; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1>NHIS Audit Log</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/nhis">NHIS</a></li>
                    <li class="active">Audit Log</li>
                </ol>
            </section>

            <section class="content">
                <!-- Filters -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                    </div>
                    <div class="box-body">
                        <form method="get" class="form-inline">
                            <div class="form-group">
                                <label>Action:</label>
                                <select name="action" class="form-control">
                                    <option value="">All Actions</option>
                                    <option value="eligibility_check" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'eligibility_check') ? 'selected' : ''; ?>>Eligibility Check</option>
                                    <option value="coverage_applied" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'coverage_applied') ? 'selected' : ''; ?>>Coverage Applied</option>
                                    <option value="claim_created" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'claim_created') ? 'selected' : ''; ?>>Claim Created</option>
                                    <option value="claim_submitted" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'claim_submitted') ? 'selected' : ''; ?>>Claim Submitted</option>
                                    <option value="claim_approved" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'claim_approved') ? 'selected' : ''; ?>>Claim Approved</option>
                                    <option value="claim_rejected" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'claim_rejected') ? 'selected' : ''; ?>>Claim Rejected</option>
                                    <option value="claim_status_check" <?php echo (isset($filters['action_type']) && $filters['action_type'] == 'claim_status_check') ? 'selected' : ''; ?>>Status Check</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Patient:</label>
                                <input type="text" name="patient_no" class="form-control" placeholder="Patient No"
                                       value="<?php echo isset($filters['patient_no']) ? $filters['patient_no'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>From:</label>
                                <input type="date" name="from_date" class="form-control"
                                       value="<?php echo isset($filters['from_date']) ? $filters['from_date'] : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>To:</label>
                                <input type="date" name="to_date" class="form-control"
                                       value="<?php echo isset($filters['to_date']) ? $filters['to_date'] : ''; ?>">
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                            <a href="<?php echo base_url('app/nhis/audit_log'); ?>" class="btn btn-default">Reset</a>
                        </form>
                    </div>
                </div>

                <!-- Audit Log Table -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-history"></i> Audit Log Entries</h3>
                    </div>
                    <div class="box-body table-responsive no-padding">
                        <table class="table table-bordered table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Action</th>
                                    <th>Reference</th>
                                    <th>Patient</th>
                                    <th>Status</th>
                                    <th>User</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td>
                                                <small>
                                                    <?php echo date('d M Y', strtotime($log->created_at)); ?>
                                                    <br><?php echo date('H:i:s', strtotime($log->created_at)); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php
                                                    $actionClass = 'default';
                                                    $eventType = isset($log->event_type) ? $log->event_type : '';
                                                    switch ($eventType) {
                                                        case 'eligibility_check': $actionClass = 'info'; break;
                                                        case 'claim_created': $actionClass = 'primary'; break;
                                                        case 'claim_submitted': $actionClass = 'warning'; break;
                                                        case 'claim_approved': $actionClass = 'success'; break;
                                                        case 'claim_rejected': $actionClass = 'danger'; break;
                                                    }
                                                ?>
                                                <span class="label label-<?php echo $actionClass; ?> action-badge">
                                                    <?php echo ucwords(str_replace('_', ' ', $eventType)); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small>
                                                    <?php echo isset($log->table_name) ? ucfirst($log->table_name) : '-'; ?>: 
                                                    <strong><?php echo isset($log->record_id) ? $log->record_id : '-'; ?></strong>
                                                </small>
                                            </td>
                                            <td><?php echo isset($log->patient_no) ? $log->patient_no : '-'; ?></td>
                                            <td>
                                                <span class="label label-info">Logged</span>
                                            </td>
                                            <td><small><?php echo isset($log->user_id) ? $log->user_id : 'System'; ?></small></td>
                                            <td>
                                                <?php if ((isset($log->old_value) && $log->old_value) || (isset($log->new_value) && $log->new_value)): ?>
                                                    <button type="button" class="btn btn-xs btn-default view-details"
                                                            data-request="<?php echo htmlspecialchars(isset($log->old_value) ? $log->old_value : ''); ?>"
                                                            data-response="<?php echo htmlspecialchars(isset($log->new_value) ? $log->new_value : ''); ?>"
                                                            data-error="">
                                                        <i class="fa fa-eye"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No audit log entries found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-info-circle"></i> Log Details</h4>
                </div>
                <div class="modal-body">
                    <div id="errorSection" style="display:none;">
                        <h5>Error Message</h5>
                        <div class="alert alert-danger" id="errorMessage"></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <h5>API Request</h5>
                            <pre class="json-display bg-info" id="requestData">-</pre>
                        </div>
                        <div class="col-md-6">
                            <h5>API Response</h5>
                            <pre class="json-display bg-success" id="responseData">-</pre>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
        $(document).ready(function() {
            $('.view-details').click(function() {
                var request = $(this).data('request');
                var response = $(this).data('response');
                var error = $(this).data('error');

                try {
                    request = request ? JSON.stringify(JSON.parse(request), null, 2) : '-';
                } catch(e) { request = request || '-'; }

                try {
                    response = response ? JSON.stringify(JSON.parse(response), null, 2) : '-';
                } catch(e) { response = response || '-'; }

                $('#requestData').text(request);
                $('#responseData').text(response);

                if (error) {
                    $('#errorMessage').text(error);
                    $('#errorSection').show();
                } else {
                    $('#errorSection').hide();
                }

                $('#detailsModal').modal('show');
            });
        });
    </script>
</body>
</html>
