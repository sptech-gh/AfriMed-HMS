<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo $title ?? 'Radiology Queue'; ?> | HMS</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/plugins/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php'); ?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-bolt"></i> <?php echo $title ?? 'Radiology Queue'; ?></h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url('app/dashboard'); ?>"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url('app/radiology'); ?>">Radiology</a></li>
                    <li class="active">Queue</li>
                </ol>
            </section>

            <section class="content">
                <?php if($this->session->flashdata('success')): ?>
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $this->session->flashdata('success'); ?>
                </div>
                <?php endif; ?>

                <?php if($this->session->flashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo $this->session->flashdata('error'); ?>
                </div>
                <?php endif; ?>

                <!-- Cleared Patients Notification Banner -->
                <?php if (isset($dispatch_notifications) && !empty($dispatch_notifications)): ?>
                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-md-12">
                        <div class="box box-solid box-success">
                            <div class="box-header">
                                <h3 class="box-title"><i class="fa fa-bell"></i> Cleared Patients — Awaiting Radiology Services</h3>
                            </div>
                            <div class="box-body no-padding">
                                <table class="table table-striped table-condensed table-hover" style="margin-bottom:0;">
                                    <thead>
                                        <tr>
                                            <th>Patient Name</th>
                                            <th>Patient ID</th>
                                            <th>Billed Items (Cleared)</th>
                                            <th>Cleared At</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($dispatch_notifications as $notif): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($notif->patient_name); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($notif->patient_no); ?></code></td>
                                            <td><?php echo htmlspecialchars($notif->item_details); ?></td>
                                            <td><?php echo date('M d, Y H:i', strtotime($notif->created_at)); ?></td>
                                            <td>
                                                <form method="post" action="<?php echo base_url(); ?>app/cashier/mark_dispatched" style="display:inline;">
                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notif->notification_id; ?>">
                                                    <input type="hidden" name="redirect_url" value="<?php echo current_url(); ?>">
                                                    <button type="submit" class="btn btn-xs btn-success"><i class="fa fa-check"></i> Process &amp; Mark Completed</button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="box box-warning">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-clock-o"></i> Pending Orders</h3>
                    </div>
                    <div class="box-body">
                        <table id="queueTable" class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Patient</th>
                                    <th>Test</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Payment</th>
                                    <th>Ordered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($pending_orders)): ?>
                                <?php foreach($pending_orders as $order): ?>
                                <tr>
                                    <td><?php echo $order->order_no; ?></td>
                                    <td>
                                        <?php echo ($order->firstname ?? '') . ' ' . ($order->lastname ?? ''); ?>
                                        <br><small class="text-muted"><?php echo $order->pat_no ?? $order->patient_no; ?></small>
                                    </td>
                                    <td><?php echo $order->test_name ?? 'N/A'; ?></td>
                                    <td><?php echo $order->category ?? 'General'; ?></td>
                                    <td>
                                        <?php
                                        $priority_class = 'default';
                                        if($order->priority == 'urgent') $priority_class = 'warning';
                                        if($order->priority == 'stat') $priority_class = 'danger';
                                        ?>
                                        <span class="label label-<?php echo $priority_class; ?>">
                                            <?php echo strtoupper($order->priority); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $ssotPay = isset($order->ssot_payment_status) ? strtoupper(trim((string)$order->ssot_payment_status)) : '';
                                        $ssotPayer = isset($order->ssot_payer_type) ? strtoupper(trim((string)$order->ssot_payer_type)) : '';
                                        if ($ssotPay === '' && $ssotPayer === '') {
                                            echo '<span class="label label-default">UNBILLED</span>';
                                        } elseif ($ssotPayer === 'NHIS' || $ssotPay === 'NHIS') {
                                            echo '<span class="label label-success">NHIS</span>';
                                        } elseif ($ssotPay === 'PAID' || $ssotPay === 'WAIVED') {
                                            echo '<span class="label label-success">PAID</span>';
                                        } else {
                                            echo '<span class="label label-warning">PAYMENT PENDING</span>';
                                        }
                                        ?>
                                    </td>
                                    <td><?php echo date('d M Y H:i', strtotime($order->ordered_at)); ?></td>
                                    <td>
                                        <a href="<?php echo base_url('app/radiology/result_entry/'.$order->id); ?>"
                                           class="btn btn-sm btn-success" title="Enter Result">
                                            <i class="fa fa-edit"></i> Result
                                        </a>
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

    <script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/plugins/datatables/jquery.dataTables.min.js"></script>
    <script src="<?php echo base_url(); ?>public/plugins/datatables/dataTables.bootstrap.min.js"></script>
    <script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
    <script>
        $(function () {
            $('#queueTable').dataTable({
                "order": [[6, "asc"]]
            });
        });
    </script>
</body>
</html>
