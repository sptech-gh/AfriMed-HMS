<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Price Override Log - HMS</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/bootstrap/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/font-awesome/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/dist/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-money"></i> Price Override Audit Log</h1>
        </section>
        <section class="content">
            <div class="box box-warning">
                <div class="box-header with-border">
                    <h3 class="box-title">Recent Price Overrides</h3>
                </div>
                <div class="box-body">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoice</th>
                                <th>Item</th>
                                <th>Original</th>
                                <th>Override</th>
                                <th>Difference</th>
                                <th>Reason</th>
                                <th>User</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($overrides)): foreach ($overrides as $o): ?>
                            <tr class="<?php echo $o->requires_approval && !$o->approved ? 'warning' : ''; ?>">
                                <td><?php echo date('Y-m-d H:i', strtotime($o->created_at)); ?></td>
                                <td><code><?php echo $o->invoice_no; ?></code></td>
                                <td><?php echo htmlspecialchars($o->item_name); ?></td>
                                <td class="text-right">GHS <?php echo number_format($o->original_price, 2); ?></td>
                                <td class="text-right">GHS <?php echo number_format($o->override_price, 2); ?></td>
                                <td class="text-right <?php echo $o->price_difference > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo $o->override_price > $o->original_price ? '+' : '-'; ?>
                                    GHS <?php echo number_format($o->price_difference, 2); ?>
                                </td>
                                <td><small><?php echo htmlspecialchars($o->override_reason); ?></small></td>
                                <td><?php echo $o->created_by; ?></td>
                                <td>
                                    <?php if ($o->requires_approval): ?>
                                        <?php if ($o->approved): ?>
                                            <span class="label label-success">Approved</span>
                                        <?php else: ?>
                                            <span class="label label-warning">Pending Approval</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="label label-default">Auto</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </aside>
</div>
<script src="<?php echo base_url(); ?>public/jquery/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/bootstrap/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/dist/js/app.js"></script>
</body>
</html>
