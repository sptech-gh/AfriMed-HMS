<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title; ?> - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/skins/skin-blue.min.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue sidebar-mini">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
<?php require_once(APPPATH.'views/include/sidebar.php');?>
<aside class="right-side">
<section class="content-header">
    <h1><i class="fa fa-bolt"></i> STAT Test Queue</h1>
    <ol class="breadcrumb">
        <li><a href="<?php echo base_url();?>app/dashboard"><i class="fa fa-dashboard"></i> Dashboard</a></li>
        <li><a href="<?php echo base_url();?>app/diagnostic_safety">Diagnostic Safety</a></li>
        <li class="active">STAT Queue</li>
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

    <!-- Pending Approvals -->
    <?php if (!empty($pending_approvals)): ?>
    <div class="box box-warning">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending STAT Approvals</h3>
            <div class="box-tools pull-right">
                <span class="badge bg-yellow"><?php echo count($pending_approvals); ?></span>
            </div>
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Clinical Indication</th>
                        <th>Requested By</th>
                        <th>Requested At</th>
                        <th width="150">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_approvals as $req): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($req->firstname . ' ' . $req->lastname); ?></strong><br>
                            <small class="text-muted"><?php echo $req->patient_no; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($req->test_name); ?></td>
                        <td><?php echo htmlspecialchars($req->clinical_indication ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($req->requested_by_name ?? '-'); ?></td>
                        <td><?php echo date('M d, H:i', strtotime($req->requested_at)); ?></td>
                        <td>
                            <form method="post" action="<?php echo base_url('app/diagnostic_safety/approve_stat'); ?>" style="display:inline;">
                                <input type="hidden" name="stat_id" value="<?php echo $req->stat_id; ?>">
                                <button type="submit" class="btn btn-xs btn-success"><i class="fa fa-check"></i> Approve</button>
                            </form>
                            <button class="btn btn-xs btn-danger btn-reject" data-id="<?php echo $req->stat_id; ?>">
                                <i class="fa fa-times"></i> Reject
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Active STAT Tests -->
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title"><i class="fa fa-bolt"></i> Active STAT Tests</h3>
            <div class="box-tools pull-right">
                <span class="badge bg-blue"><?php echo count($active_stat_tests); ?></span>
            </div>
        </div>
        <div class="box-body table-responsive no-padding">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Test</th>
                        <th>Status</th>
                        <th>Elapsed</th>
                        <th>Time Remaining</th>
                        <th>Target</th>
                        <th>Escalation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($active_stat_tests)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No active STAT tests</td></tr>
                    <?php else: ?>
                    <?php foreach ($active_stat_tests as $stat): ?>
                    <?php $is_overdue = $stat->minutes_remaining < 0; ?>
                    <tr class="<?php echo $is_overdue ? 'danger' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($stat->firstname . ' ' . $stat->lastname); ?></strong><br>
                            <small class="text-muted"><?php echo $stat->patient_no; ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($stat->test_name); ?></td>
                        <td>
                            <?php 
                            $status = $stat->tracking_status ?? 'ORDERED';
                            $badge = 'default';
                            if ($status == 'PROCESSING') $badge = 'info';
                            elseif ($status == 'RESULTED') $badge = 'primary';
                            elseif ($status == 'VERIFIED') $badge = 'success';
                            ?>
                            <span class="label label-<?php echo $badge; ?>"><?php echo $status; ?></span>
                        </td>
                        <td><?php echo $stat->elapsed_minutes; ?> min</td>
                        <td>
                            <?php if ($is_overdue): ?>
                            <span class="text-red"><strong>OVERDUE <?php echo abs($stat->minutes_remaining); ?> min</strong></span>
                            <?php else: ?>
                            <span class="text-green"><?php echo $stat->minutes_remaining; ?> min</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('H:i', strtotime($stat->target_completion_at)); ?></td>
                        <td>
                            <?php if ($stat->escalation_level > 0): ?>
                            <span class="label label-danger">Level <?php echo $stat->escalation_level; ?></span>
                            <?php else: ?>
                            <span class="label label-default">None</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</section>
</aside>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title">Reject STAT Request</h4>
            </div>
            <form method="post" action="<?php echo base_url('app/diagnostic_safety/reject_stat'); ?>">
                <div class="modal-body">
                    <input type="hidden" name="stat_id" id="reject_stat_id">
                    <div class="form-group">
                        <label>Rejection Reason <span class="text-red">*</span></label>
                        <textarea name="reason" class="form-control" rows="3" required placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/plugins/jQuery/jQuery-2.1.4.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script>
$(function() {
    $('.btn-reject').click(function() {
        $('#reject_stat_id').val($(this).data('id'));
        $('#rejectModal').modal('show');
    });

    // Auto-refresh every 30 seconds
    setTimeout(function() { location.reload(); }, 30000);
});
</script>
</body>
</html>
