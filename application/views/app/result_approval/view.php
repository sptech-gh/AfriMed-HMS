<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> | HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url() ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url() ?>public/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
    <style>
        .detail-label { font-weight: bold; color: #666; }
        .timeline-item { border-left: 3px solid #3c8dbc; padding-left: 15px; margin-bottom: 15px; }
        .timeline-item.approved { border-color: #00a65a; }
        .timeline-item.rejected { border-color: #dd4b39; }
        .timeline-item.escalated { border-color: #f39c12; }
        .data-box { background: #f9f9f9; padding: 10px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-file-text"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url() ?>app/result_approval">Result Approval</a></li>
                <li class="active">View Request</li>
            </ol>
        </section>

        <section class="content">
            <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('success'); ?>
            </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-danger alert-dismissible">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('error'); ?>
            </div>
            <?php endif; ?>

            <div class="row">
                <!-- Request Details -->
                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-info-circle"></i> Request Details</h3>
                            <div class="box-tools">
                                <?php if ($approval->status == 'PENDING'): ?>
                                <span class="label label-warning"><i class="fa fa-clock-o"></i> PENDING</span>
                                <?php elseif ($approval->status == 'APPROVED'): ?>
                                <span class="label label-success"><i class="fa fa-check"></i> APPROVED</span>
                                <?php elseif ($approval->status == 'REJECTED'): ?>
                                <span class="label label-danger"><i class="fa fa-times"></i> REJECTED</span>
                                <?php elseif ($approval->status == 'ESCALATED'): ?>
                                <span class="label label-info"><i class="fa fa-arrow-up"></i> ESCALATED</span>
                                <?php else: ?>
                                <span class="label label-default"><?php echo $approval->status; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="box-body">
                            <table class="table table-bordered">
                                <tr>
                                    <td class="detail-label" width="30%">Approval ID</td>
                                    <td>#<?php echo $approval->approval_id; ?></td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Type</td>
                                    <td>
                                        <span class="label label-<?php echo $approval->diagnostic_type == 'LAB' ? 'primary' : ($approval->diagnostic_type == 'RADIOLOGY' ? 'info' : 'success'); ?>">
                                            <?php echo $approval->diagnostic_type; ?>
                                        </span>
                                        - <?php echo str_replace('_', ' ', $approval->approval_type); ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Patient</td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($approval->firstname . ' ' . $approval->lastname); ?></strong>
                                        <br><small class="text-muted"><?php echo $approval->patient_no; ?></small>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Test/Exam</td>
                                    <td><?php echo htmlspecialchars($approval->test_name ?: 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Record ID</td>
                                    <td><?php echo $approval->record_id; ?></td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Urgency</td>
                                    <td>
                                        <?php if ($approval->urgency == 'STAT'): ?>
                                        <span class="label label-danger"><i class="fa fa-bolt"></i> STAT</span>
                                        <?php elseif ($approval->urgency == 'URGENT'): ?>
                                        <span class="label label-warning">URGENT</span>
                                        <?php else: ?>
                                        <span class="label label-default">Routine</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Description</td>
                                    <td><?php echo nl2br(htmlspecialchars($approval->action_description)); ?></td>
                                </tr>
                                <?php if ($approval->clinical_justification): ?>
                                <tr>
                                    <td class="detail-label">Clinical Justification</td>
                                    <td><?php echo nl2br(htmlspecialchars($approval->clinical_justification)); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td class="detail-label">Requested By</td>
                                    <td><?php echo htmlspecialchars($approval->requested_by_name ?: $approval->requested_by); ?></td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Requested At</td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($approval->requested_at)); ?></td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Expires At</td>
                                    <td>
                                        <?php 
                                        $expires = strtotime($approval->expires_at);
                                        $now = time();
                                        if ($expires < $now && $approval->status == 'PENDING') {
                                            echo '<span class="text-red"><i class="fa fa-warning"></i> EXPIRED</span>';
                                        } else {
                                            echo date('M d, Y H:i:s', $expires);
                                        }
                                        ?>
                                    </td>
                                </tr>
                                <?php if ($approval->reviewed_by): ?>
                                <tr>
                                    <td class="detail-label">Reviewed By</td>
                                    <td><?php echo htmlspecialchars($approval->reviewed_by_name ?: $approval->reviewed_by); ?></td>
                                </tr>
                                <tr>
                                    <td class="detail-label">Reviewed At</td>
                                    <td><?php echo date('M d, Y H:i:s', strtotime($approval->reviewed_at)); ?></td>
                                </tr>
                                <?php if ($approval->review_notes): ?>
                                <tr>
                                    <td class="detail-label">Review Notes</td>
                                    <td><?php echo nl2br(htmlspecialchars($approval->review_notes)); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php endif; ?>
                            </table>

                            <!-- Original and Proposed Data -->
                            <?php if ($approval->original_data || $approval->proposed_data): ?>
                            <h4><i class="fa fa-exchange"></i> Data Comparison</h4>
                            <div class="row">
                                <div class="col-md-6">
                                    <h5>Original Data</h5>
                                    <div class="data-box">
                                        <pre><?php echo htmlspecialchars(json_encode(json_decode($approval->original_data), JSON_PRETTY_PRINT)); ?></pre>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5>Proposed Data</h5>
                                    <div class="data-box">
                                        <pre><?php echo htmlspecialchars(json_encode(json_decode($approval->proposed_data), JSON_PRETTY_PRINT)); ?></pre>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if ($approval->status == 'PENDING'): ?>
                        <div class="box-footer">
                            <button type="button" class="btn btn-success btn-approve" data-id="<?php echo $approval->approval_id; ?>">
                                <i class="fa fa-check"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger btn-reject" data-id="<?php echo $approval->approval_id; ?>">
                                <i class="fa fa-times"></i> Reject
                            </button>
                            <button type="button" class="btn btn-warning btn-escalate" data-id="<?php echo $approval->approval_id; ?>">
                                <i class="fa fa-arrow-up"></i> Escalate
                            </button>
                            <a href="<?php echo base_url() ?>app/result_approval" class="btn btn-default pull-right">
                                <i class="fa fa-arrow-left"></i> Back to Queue
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="box-footer">
                            <a href="<?php echo base_url() ?>app/result_approval" class="btn btn-default">
                                <i class="fa fa-arrow-left"></i> Back to Queue
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Audit Trail -->
                <div class="col-md-4">
                    <div class="box box-info">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-history"></i> Audit Trail</h3>
                        </div>
                        <div class="box-body">
                            <?php if (empty($audit_trail)): ?>
                            <p class="text-muted">No audit entries.</p>
                            <?php else: ?>
                            <?php foreach ($audit_trail as $audit): ?>
                            <div class="timeline-item <?php echo strtolower($audit->action); ?>">
                                <strong><?php echo $audit->action; ?></strong>
                                <br>
                                <small class="text-muted">
                                    <?php echo date('M d, H:i', strtotime($audit->performed_at)); ?>
                                    by <?php echo htmlspecialchars($audit->username ?: $audit->performed_by); ?>
                                </small>
                                <?php if ($audit->notes): ?>
                                <br><small><?php echo htmlspecialchars($audit->notes); ?></small>
                                <?php endif; ?>
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

<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo base_url() ?>app/result_approval/approve" method="post">
                <div class="modal-header bg-green">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-check"></i> Approve Request</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="approval_id" id="approve_id">
                    <div class="form-group">
                        <label>Notes (optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="fa fa-check"></i> Approve</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo base_url() ?>app/result_approval/reject" method="post">
                <div class="modal-header bg-red">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-times"></i> Reject Request</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="approval_id" id="reject_id">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-red">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Provide reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="fa fa-times"></i> Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Escalate Modal -->
<div class="modal fade" id="escalateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="<?php echo base_url() ?>app/result_approval/escalate" method="post">
                <div class="modal-header bg-yellow">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-arrow-up"></i> Escalate Request</h4>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="approval_id" id="escalate_id">
                    <div class="form-group">
                        <label>Escalation Reason</label>
                        <textarea name="reason" class="form-control" rows="3" placeholder="Why is this being escalated?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="fa fa-arrow-up"></i> Escalate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url() ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url() ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/dist/js/app.min.js"></script>
<script>
$(function() {
    $('.btn-approve').click(function() {
        $('#approve_id').val($(this).data('id'));
        $('#approveModal').modal('show');
    });
    
    $('.btn-reject').click(function() {
        $('#reject_id').val($(this).data('id'));
        $('#rejectModal').modal('show');
    });
    
    $('.btn-escalate').click(function() {
        $('#escalate_id').val($(this).data('id'));
        $('#escalateModal').modal('show');
    });
});
</script>
</body>
</html>
