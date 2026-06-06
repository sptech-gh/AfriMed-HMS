<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing Notifications</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        .notification-item { border-left: 4px solid #ddd; padding: 15px; margin-bottom: 10px; background: #fff; }
        .notification-item.severity-INFO { border-color: #3c8dbc; }
        .notification-item.severity-WARNING { border-color: #f39c12; }
        .notification-item.severity-CRITICAL { border-color: #dd4b39; }
        .notification-item.is-read { opacity: 0.6; }
        .notification-time { font-size: 12px; color: #999; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Billing Notifications <small>Alerts & Updates</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Notifications</li>
        </ol>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-md-8">
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-bell"></i> Notifications</h3>
                        <span class="badge bg-red"><?= count($notifications ?? []) ?> unread</span>
                    </div>
                    <div class="box-body">
                        <?php if (!empty($notifications)): ?>
                            <?php foreach ($notifications as $n): ?>
                            <div class="notification-item severity-<?= $n->severity ?> <?= $n->is_read ? 'is-read' : '' ?>" id="notif_<?= $n->id ?>">
                                <div class="pull-right">
                                    <?php if (!$n->is_read): ?>
                                    <button class="btn btn-xs btn-default btn-mark-read" data-id="<?= $n->id ?>">
                                        <i class="fa fa-check"></i> Mark Read
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <h4>
                                    <?php if ($n->severity === 'CRITICAL'): ?>
                                        <i class="fa fa-exclamation-circle text-danger"></i>
                                    <?php elseif ($n->severity === 'WARNING'): ?>
                                        <i class="fa fa-warning text-warning"></i>
                                    <?php else: ?>
                                        <i class="fa fa-info-circle text-info"></i>
                                    <?php endif; ?>
                                    <?= htmlspecialchars($n->title) ?>
                                </h4>
                                <p><?= htmlspecialchars($n->message) ?></p>
                                <div class="notification-time">
                                    <i class="fa fa-clock-o"></i> <?= date('M d, Y H:i', strtotime($n->created_at)) ?>
                                    <?php if ($n->notification_type): ?>
                                        <span class="label label-default"><?= $n->notification_type ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center text-muted" style="padding: 40px;">
                                <i class="fa fa-bell-slash fa-3x"></i>
                                <p style="margin-top: 15px;">No notifications</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <!-- Notification Types Legend -->
                <div class="box box-default">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-info"></i> Notification Types</h3>
                    </div>
                    <div class="box-body">
                        <ul class="list-unstyled">
                            <li><i class="fa fa-exclamation-circle text-danger"></i> <strong>Critical</strong> - Requires immediate attention</li>
                            <li><i class="fa fa-warning text-warning"></i> <strong>Warning</strong> - Review recommended</li>
                            <li><i class="fa fa-info-circle text-info"></i> <strong>Info</strong> - General updates</li>
                        </ul>
                        <hr>
                        <p class="text-muted small">
                            Notifications are generated automatically for:
                        </p>
                        <ul class="text-muted small">
                            <li>Outstanding payments</li>
                            <li>Large discounts</li>
                            <li>Refund requests</li>
                            <li>Cashier shortages</li>
                            <li>Reconciliation issues</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(document).ready(function() {
        $('.btn-mark-read').click(function() {
            var btn = $(this);
            var id = btn.data('id');
            
            $.post('<?= base_url('app/ebilling/mark_notification_read') ?>', {
                notification_id: id,
                '<?= $this->security->get_csrf_token_name() ?>': '<?= $this->security->get_csrf_hash() ?>'
            }, function(resp) {
                if (resp.success) {
                    $('#notif_' + id).addClass('is-read');
                    btn.remove();
                }
            }, 'json');
        });
    });
    </script>
</body>
</html>
