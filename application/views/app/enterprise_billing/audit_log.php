<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing Audit Log</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Billing Audit Log <small>Billing & Finance</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/dashboard') ?>"><i class="fa fa-dashboard"></i> Home</a></li>
            <li><a href="<?= base_url('app/ebilling') ?>">Billing & Finance</a></li>
            <li class="active">Audit Log</li>
        </ol>
    </section>

    <section class="content">
        <!-- Filters -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filter</h3>
            </div>
            <div class="box-body">
                <form method="get" class="form-inline">
                    <div class="form-group">
                        <label>Action:</label>
                        <select name="action" class="form-control">
                            <option value="">All Actions</option>
                            <option value="INVOICE_CREATED" <?= $filters['action_type'] === 'INVOICE_CREATED' ? 'selected' : '' ?>>Invoice Created</option>
                            <option value="PAYMENT_RECEIVED" <?= $filters['action_type'] === 'PAYMENT_RECEIVED' ? 'selected' : '' ?>>Payment Received</option>
                            <option value="REFUND_ISSUED" <?= $filters['action_type'] === 'REFUND_ISSUED' ? 'selected' : '' ?>>Refund Issued</option>
                            <option value="INVOICE_VOIDED" <?= $filters['action_type'] === 'INVOICE_VOIDED' ? 'selected' : '' ?>>Invoice Voided</option>
                            <option value="GATE_BYPASSED" <?= $filters['action_type'] === 'GATE_BYPASSED' ? 'selected' : '' ?>>Gate Bypassed</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-left: 15px;">
                        <label>Invoice:</label>
                        <input type="text" name="invoice" class="form-control" placeholder="Invoice No" value="<?= htmlspecialchars($filters['invoice_no']) ?>">
                    </div>
                    <div class="form-group" style="margin-left: 15px;">
                        <label>From:</label>
                        <input type="date" name="from" class="form-control" value="<?= htmlspecialchars($filters['date_from']) ?>">
                    </div>
                    <div class="form-group" style="margin-left: 15px;">
                        <label>To:</label>
                        <input type="date" name="to" class="form-control" value="<?= htmlspecialchars($filters['date_to']) ?>">
                    </div>
                    <button type="submit" class="btn btn-primary" style="margin-left: 15px;">
                        <i class="fa fa-search"></i> Filter
                    </button>
                    <a href="<?= base_url('app/ebilling/audit_log') ?>" class="btn btn-default" style="margin-left: 5px;">
                        <i class="fa fa-refresh"></i> Reset
                    </a>
                </form>
            </div>
        </div>

        <!-- Audit Entries -->
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> Audit Trail</h3>
                <div class="box-tools pull-right">
                    <span class="label label-primary"><?= count($entries) ?> entries</span>
                </div>
            </div>
            <div class="box-body table-responsive">
                <?php if (empty($entries)): ?>
                    <div class="alert alert-info">
                        <i class="fa fa-info-circle"></i> No audit entries found for the selected filters.
                    </div>
                <?php else: ?>
                    <table class="table table-bordered table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Action</th>
                                <th>Reference</th>
                                <th>Patient</th>
                                <th>User</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $e): ?>
                            <tr>
                                <td><?= isset($e->created_at) ? date('d M Y H:i:s', strtotime($e->created_at)) : '-' ?></td>
                                <td>
                                    <?php
                                    $action = isset($e->action_type) ? $e->action_type : '';
                                    $badge_class = 'label-default';
                                    if (strpos($action, 'CREATED') !== false || strpos($action, 'RECEIVED') !== false) $badge_class = 'label-success';
                                    if (strpos($action, 'VOIDED') !== false || strpos($action, 'REFUND') !== false) $badge_class = 'label-danger';
                                    if (strpos($action, 'BYPASSED') !== false) $badge_class = 'label-warning';
                                    ?>
                                    <span class="label <?= $badge_class ?>"><?= htmlspecialchars($action) ?></span>
                                </td>
                                <td><?= isset($e->reference_no) ? htmlspecialchars($e->reference_no) : '-' ?></td>
                                <td><?= isset($e->patient_no) ? htmlspecialchars($e->patient_no) : '-' ?></td>
                                <td><?= isset($e->user_name) ? htmlspecialchars($e->user_name) : (isset($e->user_id) ? $e->user_id : '-') ?></td>
                                <td>
                                    <?php if (isset($e->details) && !empty($e->details)): ?>
                                        <button type="button" class="btn btn-xs btn-info" data-toggle="popover" data-trigger="click" data-placement="left" data-html="true" data-content="<pre style='max-width:300px;overflow:auto;'><?= htmlspecialchars($e->details) ?></pre>">
                                            <i class="fa fa-eye"></i> View
                                        </button>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><small><?= isset($e->ip_address) ? htmlspecialchars($e->ip_address) : '-' ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
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
    $(function(){
        $('[data-toggle="popover"]').popover();
    });
    </script>
</body>
</html>
