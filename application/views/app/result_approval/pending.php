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
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH . 'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-list"></i> <?php echo $title; ?></h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url() ?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
                <li><a href="<?php echo base_url() ?>app/result_approval">Result Approval</a></li>
                <li class="active">Pending</li>
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

            <!-- Filter Tabs -->
            <div class="nav-tabs-custom">
                <ul class="nav nav-tabs">
                    <li class="<?php echo !$diagnostic_type ? 'active' : ''; ?>">
                        <a href="<?php echo base_url() ?>app/result_approval/pending">All Types</a>
                    </li>
                    <li class="<?php echo $diagnostic_type == 'LAB' ? 'active' : ''; ?>">
                        <a href="<?php echo base_url() ?>app/result_approval/pending/lab">
                            <i class="fa fa-flask"></i> Laboratory
                        </a>
                    </li>
                    <li class="<?php echo $diagnostic_type == 'RADIOLOGY' ? 'active' : ''; ?>">
                        <a href="<?php echo base_url() ?>app/result_approval/pending/radiology">
                            <i class="fa fa-x-ray"></i> Radiology
                        </a>
                    </li>
                    <li class="<?php echo $diagnostic_type == 'SONOGRAPHY' ? 'active' : ''; ?>">
                        <a href="<?php echo base_url() ?>app/result_approval/pending/sonography">
                            <i class="fa fa-heartbeat"></i> Sonography
                        </a>
                    </li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane active">
                        <div class="box-body">
                            <?php if (empty($pending_approvals)): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No pending approval requests
                                <?php echo $diagnostic_type ? "for {$diagnostic_type}" : ''; ?>.
                            </div>
                            <?php else: ?>
                            <table id="pendingTable" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Type</th>
                                        <th>Patient</th>
                                        <th>Test</th>
                                        <th>Description</th>
                                        <th>Urgency</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pending_approvals as $a): ?>
                                    <tr>
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
                                        <td><?php echo htmlspecialchars(substr($a->action_description, 0, 40)); ?>...</td>
                                        <td>
                                            <?php if ($a->urgency == 'STAT'): ?>
                                            <span class="label label-danger"><i class="fa fa-bolt"></i> STAT</span>
                                            <?php elseif ($a->urgency == 'URGENT'): ?>
                                            <span class="label label-warning">URGENT</span>
                                            <?php else: ?>
                                            <span class="label label-default">Routine</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo date('M d, H:i', strtotime($a->requested_at)); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($a->requested_by_name ?: $a->requested_by); ?></small>
                                        </td>
                                        <td>
                                            <a href="<?php echo base_url() ?>app/result_approval/view/<?php echo $a->approval_id; ?>" class="btn btn-xs btn-info">
                                                <i class="fa fa-eye"></i> View
                                            </a>
                                            <button type="button" class="btn btn-xs btn-success btn-approve" data-id="<?php echo $a->approval_id; ?>">
                                                <i class="fa fa-check"></i>
                                            </button>
                                            <button type="button" class="btn btn-xs btn-danger btn-reject" data-id="<?php echo $a->approval_id; ?>">
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
                        <textarea name="notes" class="form-control" rows="3"></textarea>
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
                        <textarea name="reason" class="form-control" rows="3" required></textarea>
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
    $('#pendingTable').DataTable({ order: [[6, 'asc']], pageLength: 25 });
    
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
