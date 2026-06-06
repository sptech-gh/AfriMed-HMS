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
        .status-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .status-PENDING { background: #f39c12; color: #fff; }
        .status-NOTIFIED { background: #3498db; color: #fff; }
        .status-SCHEDULED { background: #9b59b6; color: #fff; }
        .status-COLLECTED { background: #27ae60; color: #fff; }
        .status-CANCELLED { background: #95a5a6; color: #fff; }
        .priority-STAT { background: #e74c3c; color: #fff; }
        .priority-URGENT { background: #f39c12; color: #fff; }
        .priority-ROUTINE { background: #95a5a6; color: #fff; }
        .filter-tabs { margin-bottom: 20px; }
        .filter-tabs .btn { margin-right: 5px; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-refresh"></i> Recollection Requests</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li class="active">Recollections</li>
            </ol>
        </section>

        <section class="content">
            <?php if ($this->session->flashdata('success')): ?>
            <div class="alert alert-success alert-dismissable">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('success'); ?>
            </div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('error')): ?>
            <div class="alert alert-danger alert-dismissable">
                <button type="button" class="close" data-dismiss="alert">&times;</button>
                <?php echo $this->session->flashdata('error'); ?>
            </div>
            <?php endif; ?>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <a href="?status=PENDING" class="btn btn-<?php echo $current_status === 'PENDING' ? 'warning' : 'default'; ?>">
                    <i class="fa fa-clock-o"></i> Pending
                </a>
                <a href="?status=NOTIFIED" class="btn btn-<?php echo $current_status === 'NOTIFIED' ? 'info' : 'default'; ?>">
                    <i class="fa fa-bell"></i> Notified
                </a>
                <a href="?status=SCHEDULED" class="btn btn-<?php echo $current_status === 'SCHEDULED' ? 'primary' : 'default'; ?>">
                    <i class="fa fa-calendar"></i> Scheduled
                </a>
                <a href="?status=COLLECTED" class="btn btn-<?php echo $current_status === 'COLLECTED' ? 'success' : 'default'; ?>">
                    <i class="fa fa-check"></i> Collected
                </a>
                <a href="?status=CANCELLED" class="btn btn-<?php echo $current_status === 'CANCELLED' ? 'danger' : 'default'; ?>">
                    <i class="fa fa-times"></i> Cancelled
                </a>
                <a href="?status=ALL" class="btn btn-<?php echo $current_status === 'ALL' ? 'primary' : 'default'; ?>">
                    <i class="fa fa-list"></i> All
                </a>
            </div>

            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title"><i class="fa fa-list"></i> Recollection Requests - <?php echo $current_status; ?></h3>
                    <span class="pull-right badge bg-blue"><?php echo count($recollections); ?> requests</span>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Rejection Reason</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Requested</th>
                                    <th>Phone</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recollections)): ?>
                                <tr><td colspan="9" class="text-center text-muted">No recollection requests found</td></tr>
                                <?php else: foreach ($recollections as $r): ?>
                                <tr>
                                    <td>#<?php echo $r->recollection_id; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars(($r->firstname ?? '') . ' ' . ($r->lastname ?? '')); ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($r->patient_no); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($r->test_name ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($r->rejection_reason_text ?? '-'); ?></td>
                                    <td><span class="status-badge priority-<?php echo $r->priority; ?>"><?php echo $r->priority; ?></span></td>
                                    <td><span class="status-badge status-<?php echo $r->status; ?>"><?php echo $r->status; ?></span></td>
                                    <td><?php echo date('M j, H:i', strtotime($r->requested_at)); ?></td>
                                    <td><?php echo htmlspecialchars($r->phone ?? '-'); ?></td>
                                    <td>
                                        <?php if ($r->status === 'PENDING'): ?>
                                        <button class="btn btn-xs btn-info btn-notify" data-id="<?php echo $r->recollection_id; ?>" title="Mark Notified">
                                            <i class="fa fa-bell"></i>
                                        </button>
                                        <button class="btn btn-xs btn-primary btn-schedule" data-id="<?php echo $r->recollection_id; ?>" title="Schedule">
                                            <i class="fa fa-calendar"></i>
                                        </button>
                                        <button class="btn btn-xs btn-danger btn-cancel" data-id="<?php echo $r->recollection_id; ?>" title="Cancel">
                                            <i class="fa fa-times"></i>
                                        </button>
                                        <?php elseif ($r->status === 'NOTIFIED' || $r->status === 'SCHEDULED'): ?>
                                        <button class="btn btn-xs btn-success btn-collect" data-id="<?php echo $r->recollection_id; ?>" title="Mark Collected">
                                            <i class="fa fa-check"></i>
                                        </button>
                                        <button class="btn btn-xs btn-danger btn-cancel" data-id="<?php echo $r->recollection_id; ?>" title="Cancel">
                                            <i class="fa fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<!-- Cancel Modal -->
<div class="modal fade" id="cancelModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-red">
                <button type="button" class="close" data-dismiss="modal">&times;</button>
                <h4 class="modal-title"><i class="fa fa-times"></i> Cancel Recollection</h4>
            </div>
            <div class="modal-body">
                <input type="hidden" id="cancel_id">
                <div class="form-group">
                    <label>Cancellation Reason</label>
                    <textarea id="cancel_reason" class="form-control" rows="3" placeholder="Enter reason..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="btnConfirmCancel"><i class="fa fa-times"></i> Cancel Request</button>
            </div>
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
    function updateStatus(id, status, notes) {
        var postData = {
            recollection_id: id,
            status: status,
            notes: notes || ''
        };
        postData[csrfName] = csrfHash;
        $.post('<?php echo base_url(); ?>app/sample_tracking/update_recollection', postData, function(resp) {
            if (resp.ok) {
                location.reload();
            } else {
                alert('Error updating status');
            }
        }, 'json');
    }

    $('.btn-notify').click(function() {
        if (confirm('Mark this request as notified?')) {
            updateStatus($(this).data('id'), 'NOTIFIED');
        }
    });

    $('.btn-schedule').click(function() {
        if (confirm('Mark this request as scheduled?')) {
            updateStatus($(this).data('id'), 'SCHEDULED');
        }
    });

    $('.btn-collect').click(function() {
        if (confirm('Mark this sample as collected?')) {
            updateStatus($(this).data('id'), 'COLLECTED');
        }
    });

    $('.btn-cancel').click(function() {
        $('#cancel_id').val($(this).data('id'));
        $('#cancelModal').modal('show');
    });

    $('#btnConfirmCancel').click(function() {
        var id = $('#cancel_id').val();
        var reason = $('#cancel_reason').val();
        updateStatus(id, 'CANCELLED', reason);
    });
});
</script>
</body>
</html>
