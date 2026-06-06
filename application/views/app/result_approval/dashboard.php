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
    <link href="<?php echo base_url() ?>public/plugins/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <style>
        .stat-box { border-left: 4px solid; padding: 15px; margin-bottom: 15px; background: #fff; }
        .stat-box.pending { border-color: #f39c12; }
        .stat-box.approved { border-color: #00a65a; }
        .stat-box.rejected { border-color: #dd4b39; }
        .stat-box.escalated { border-color: #3c8dbc; }
        .stat-box h3 { margin: 0 0 5px 0; font-size: 28px; }
        .stat-box p { margin: 0; color: #666; }
        .urgency-stat { background: #f0ad4e; color: #fff; }
        .urgency-urgent { background: #d9534f; color: #fff; }
        .approval-row:hover { background-color: #f5f5f5; }
    </style>
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-check-circle"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li class="active">Result Approval</li>
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

            <!-- Statistics Row -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-box pending">
                        <h3><?php echo $approval_stats->pending ?? 0; ?></h3>
                        <p><i class="fa fa-clock-o"></i> Pending Approvals</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box approved">
                        <h3><?php echo $approval_stats->approved ?? 0; ?></h3>
                        <p><i class="fa fa-check"></i> Approved (30 days)</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box rejected">
                        <h3><?php echo $approval_stats->rejected ?? 0; ?></h3>
                        <p><i class="fa fa-times"></i> Rejected (30 days)</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box escalated">
                        <h3><?php echo $approval_stats->escalated ?? 0; ?></h3>
                        <p><i class="fa fa-arrow-up"></i> Escalated</p>
                    </div>
                </div>
            </div>

            <!-- Verification Stats -->
            <div class="row">
                <div class="col-md-6">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-shield"></i> Verification Statistics (30 days)</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-condensed">
                                <tr>
                                    <td>Total Verification Attempts</td>
                                    <td class="text-right"><strong><?php echo $verification_stats->total_attempts ?? 0; ?></strong></td>
                                </tr>
                                <tr class="success">
                                    <td>Successful</td>
                                    <td class="text-right"><strong><?php echo $verification_stats->successful ?? 0; ?></strong></td>
                                </tr>
                                <tr class="danger">
                                    <td>Denied - Role</td>
                                    <td class="text-right"><?php echo $verification_stats->denied_role ?? 0; ?></td>
                                </tr>
                                <tr class="warning">
                                    <td>Denied - Credential</td>
                                    <td class="text-right"><?php echo $verification_stats->denied_credential ?? 0; ?></td>
                                </tr>
                                <tr class="info">
                                    <td>Denied - Same User</td>
                                    <td class="text-right"><?php echo $verification_stats->denied_same_user ?? 0; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-bolt"></i> Urgency Breakdown</h3>
                        </div>
                        <div class="box-body">
                            <table class="table table-condensed">
                                <tr>
                                    <td>STAT Requests</td>
                                    <td class="text-right"><span class="label label-danger"><?php echo $approval_stats->stat_requests ?? 0; ?></span></td>
                                </tr>
                                <tr>
                                    <td>Urgent Requests</td>
                                    <td class="text-right"><span class="label label-warning"><?php echo $approval_stats->urgent_requests ?? 0; ?></span></td>
                                </tr>
                                <tr>
                                    <td>Avg Response Time</td>
                                    <td class="text-right"><strong><?php echo round($approval_stats->avg_response_minutes ?? 0); ?> min</strong></td>
                                </tr>
                                <tr>
                                    <td>Expired Requests</td>
                                    <td class="text-right"><span class="label label-default"><?php echo $approval_stats->expired ?? 0; ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pending Approvals Table -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-list"></i> Pending Approval Requests</h3>
                    <div class="box-tools">
                        <a href="<?php echo base_url() ?>app/result_approval/credentials" class="btn btn-sm btn-default">
                            <i class="fa fa-id-card"></i> Credentials
                        </a>
                        <a href="<?php echo base_url() ?>app/result_approval/permissions" class="btn btn-sm btn-default">
                            <i class="fa fa-cog"></i> Permissions
                        </a>
                        <a href="<?php echo base_url() ?>app/result_approval/reports" class="btn btn-sm btn-info">
                            <i class="fa fa-bar-chart"></i> Reports
                        </a>
                    </div>
                </div>
                <div class="box-body">
                    <?php if (empty($pending_approvals)): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No pending approval requests.
                    </div>
                    <?php else: ?>
                    <table id="approvalTable" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Patient</th>
                                <th>Test</th>
                                <th>Description</th>
                                <th>Urgency</th>
                                <th>Requested By</th>
                                <th>Requested At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_approvals as $a): ?>
                            <tr class="approval-row">
                                <td><?php echo $a->approval_id; ?></td>
                                <td>
                                    <span class="label label-<?php echo $a->diagnostic_type == 'LAB' ? 'primary' : ($a->diagnostic_type == 'RADIOLOGY' ? 'info' : 'success'); ?>">
                                        <?php echo $a->diagnostic_type; ?>
                                    </span>
                                    <br><small><?php echo str_replace('_', ' ', $a->approval_type); ?></small>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($a->firstname . ' ' . $a->lastname); ?>
                                    <br><small class="text-muted"><?php echo $a->patient_no; ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($a->test_name ?: '-'); ?></td>
                                <td><?php echo htmlspecialchars(substr($a->action_description, 0, 50)); ?>...</td>
                                <td>
                                    <?php if ($a->urgency == 'STAT'): ?>
                                    <span class="label label-danger"><i class="fa fa-bolt"></i> STAT</span>
                                    <?php elseif ($a->urgency == 'URGENT'): ?>
                                    <span class="label label-warning">URGENT</span>
                                    <?php else: ?>
                                    <span class="label label-default">Routine</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($a->requested_by_name ?: $a->requested_by); ?></td>
                                <td><?php echo date('M d, H:i', strtotime($a->requested_at)); ?></td>
                                <td>
                                    <a href="<?php echo base_url() ?>app/result_approval/view/<?php echo $a->approval_id; ?>" class="btn btn-xs btn-info" title="View">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn btn-xs btn-success btn-approve" data-id="<?php echo $a->approval_id; ?>" title="Approve">
                                        <i class="fa fa-check"></i>
                                    </button>
                                    <button type="button" class="btn btn-xs btn-danger btn-reject" data-id="<?php echo $a->approval_id; ?>" title="Reject">
                                        <i class="fa fa-times"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
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

<script src="<?php echo base_url() ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url() ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datatables/jquery.dataTables.min.js"></script>
<script src="<?php echo base_url() ?>public/plugins/datatables/dataTables.bootstrap.min.js"></script>
<script src="<?php echo base_url() ?>public/dist/js/app.min.js"></script>
<script>
$(function() {
    $('#approvalTable').DataTable({
        order: [[7, 'asc']],
        pageLength: 25
    });
    
    $('.btn-approve').click(function() {
        $('#approve_id').val($(this).data('id'));
        $('#approveModal').modal('show');
    });
    
    $('.btn-reject').click(function() {
        $('#reject_id').val($(this).data('id'));
        $('#rejectModal').modal('show');
    });
});
</script>
</body>
</html>
