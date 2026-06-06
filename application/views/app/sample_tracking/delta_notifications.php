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
        .notification-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: #fff; }
        .notification-card.unread { border-left: 4px solid #3498db; background: #f8f9ff; }
        .notification-card.critical { border-left-color: #e74c3c; }
        .notification-card.warning { border-left-color: #f39c12; }
        .severity-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; }
        .severity-CRITICAL { background: #e74c3c; color: #fff; }
        .severity-WARNING { background: #f39c12; color: #fff; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH . 'views/include/header.php'); ?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH . 'views/include/sidebar.php'); ?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-bell"></i> My Delta Check Notifications</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/sample_tracking"><i class="fa fa-flask"></i> Sample Tracking</a></li>
                <li class="active">My Notifications</li>
            </ol>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-12">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Delta Check Notifications</h3>
                            <div class="box-tools">
                                <button class="btn btn-sm btn-success" id="btnMarkAllRead">
                                    <i class="fa fa-check-double"></i> Mark All Read
                                </button>
                            </div>
                        </div>
                        <div class="box-body">
                            <?php if (empty($notifications)): ?>
                            <div class="alert alert-success">
                                <i class="fa fa-check-circle"></i> No pending notifications.
                            </div>
                            <?php else: ?>
                            <?php foreach ($notifications as $n): ?>
                            <div class="notification-card <?php echo $n->acknowledged_at ? '' : 'unread'; ?> <?php echo strtolower($n->delta_severity ?? ''); ?>" data-id="<?php echo $n->notification_id; ?>">
                                <div class="row">
                                    <div class="col-md-8">
                                        <h4 class="mt-0">
                                            <?php if (!$n->acknowledged_at): ?><i class="fa fa-circle text-primary" style="font-size: 10px;"></i><?php endif; ?>
                                            <?php echo htmlspecialchars($n->test_name ?? 'Unknown Test'); ?>
                                            <span class="severity-badge severity-<?php echo $n->delta_severity ?? 'WARNING'; ?>">
                                                <?php echo $n->delta_severity ?? 'WARNING'; ?>
                                            </span>
                                        </h4>
                                        <p class="mb-0">
                                            <strong>Patient:</strong> <?php echo htmlspecialchars(($n->firstname ?? '') . ' ' . ($n->lastname ?? '')); ?>
                                            (<?php echo htmlspecialchars($n->patient_no); ?>)
                                        </p>
                                        <p class="mb-0">
                                            <strong>Delta:</strong> 
                                            <?php echo htmlspecialchars($n->previous_value ?? '-'); ?> → 
                                            <?php echo htmlspecialchars($n->current_value ?? '-'); ?>
                                            (<strong><?php echo number_format($n->delta_percent, 1); ?>%</strong> change)
                                        </p>
                                        <?php if ($n->clinical_significance): ?>
                                        <p class="text-muted mb-0"><small><?php echo htmlspecialchars($n->clinical_significance); ?></small></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <small class="text-muted">
                                            <i class="fa fa-clock-o"></i> <?php echo date('M j, Y H:i', strtotime($n->notified_at)); ?>
                                        </small>
                                        <?php if ($n->acknowledged_at): ?>
                                        <br><small class="text-success">
                                            <i class="fa fa-check"></i> Acknowledged <?php echo date('M j H:i', strtotime($n->acknowledged_at)); ?>
                                        </small>
                                        <?php else: ?>
                                        <br>
                                        <button class="btn btn-sm btn-primary btn-acknowledge mt-2" data-id="<?php echo $n->notification_id; ?>">
                                            <i class="fa fa-check"></i> Acknowledge
                                        </button>
                                        <?php endif; ?>
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

<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.min.js"></script>
<script>
$(document).ready(function() {
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    $('.btn-acknowledge').click(function() {
        var btn = $(this);
        var id = btn.data('id');
        var postData = { notification_id: id };
        postData[csrfName] = csrfHash;
        
        $.post('<?php echo base_url(); ?>app/sample_tracking/acknowledge_delta_notification', postData, function(resp) {
            if (resp.ok) {
                btn.closest('.notification-card').removeClass('unread');
                btn.replaceWith('<small class="text-success"><i class="fa fa-check"></i> Acknowledged</small>');
            } else {
                alert('Error acknowledging notification');
            }
        }, 'json');
    });

    $('#btnMarkAllRead').click(function() {
        $('.btn-acknowledge').each(function() {
            $(this).click();
        });
    });
});
</script>
</body>
</html>
