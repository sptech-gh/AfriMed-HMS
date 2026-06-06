<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Result Amendments - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .status-pending { background: #f39c12; color: #fff; }
        .status-approved { background: #00a65a; color: #fff; }
        .status-rejected { background: #dd4b39; color: #fff; }
        .result-box { background: #f5f5f5; padding: 10px; border-radius: 4px; font-family: monospace; max-height: 100px; overflow-y: auto; }
        .result-original { border-left: 3px solid #dd4b39; }
        .result-amended { border-left: 3px solid #00a65a; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-edit text-purple"></i> Result Amendments</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/critical_alerts">Critical Alerts</a></li>
                    <li class="active">Amendments</li>
                </ol>
            </section>

            <section class="content">
                <div class="row">
                    <div class="col-md-12">
                        <a href="<?php echo base_url();?>app/critical_alerts" class="btn btn-default" style="margin-bottom: 15px;">
                            <i class="fa fa-arrow-left"></i> Back to Dashboard
                        </a>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12">
                        <div class="box box-purple">
                            <div class="box-header with-border">
                                <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Result Amendments (<?php echo count($pending_amendments); ?>)</h3>
                            </div>
                            <div class="box-body">
                                <?php if (empty($pending_amendments)): ?>
                                    <div class="alert alert-success">
                                        <i class="fa fa-check-circle"></i> No pending amendments to review.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($pending_amendments as $amend): ?>
                                        <div class="panel panel-default">
                                            <div class="panel-heading">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <strong><?php echo htmlspecialchars($amend->diagnostic_type); ?></strong> - 
                                                        Patient: <?php echo htmlspecialchars($amend->patient_no ?? 'N/A'); ?>
                                                    </div>
                                                    <div class="col-md-6 text-right">
                                                        <span class="label status-pending">PENDING</span>
                                                        <small class="text-muted">Requested: <?php echo date('M j, Y g:i A', strtotime($amend->requested_at)); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="panel-body">
                                                <div class="row">
                                                    <div class="col-md-5">
                                                        <label>Original Result:</label>
                                                        <div class="result-box result-original">
                                                            <?php echo nl2br(htmlspecialchars($amend->original_result)); ?>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-2 text-center" style="padding-top: 30px;">
                                                        <i class="fa fa-arrow-right fa-2x text-muted"></i>
                                                    </div>
                                                    <div class="col-md-5">
                                                        <label>Amended Result:</label>
                                                        <div class="result-box result-amended">
                                                            <?php echo nl2br(htmlspecialchars($amend->amended_result)); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr>
                                                <div class="row">
                                                    <div class="col-md-8">
                                                        <strong>Reason for Amendment:</strong><br>
                                                        <?php echo htmlspecialchars($amend->amendment_reason); ?>
                                                        <br><br>
                                                        <small class="text-muted">
                                                            Requested by: <?php echo htmlspecialchars($amend->requested_by_name ?? $amend->requested_by); ?>
                                                        </small>
                                                    </div>
                                                    <div class="col-md-4 text-right">
                                                        <button class="btn btn-success" onclick="approveAmendment(<?php echo $amend->amendment_id; ?>)">
                                                            <i class="fa fa-check"></i> Approve
                                                        </button>
                                                        <button class="btn btn-danger" onclick="rejectAmendment(<?php echo $amend->amendment_id; ?>)">
                                                            <i class="fa fa-times"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </aside>
    </div>

    <!-- Reject Modal -->
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
        function approveAmendment(amendmentId) {
            if (!confirm('Are you sure you want to approve this amendment? This will update the original result.')) return;
            
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
    </script>
</body>
</html>
