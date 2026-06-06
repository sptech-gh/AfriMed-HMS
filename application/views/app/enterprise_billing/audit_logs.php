<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?php echo isset($companyInfo->site_title) ? $companyInfo->site_title : 'HMS'; ?> — Billing Audit Logs</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/datatables/dataTables.bootstrap.css" rel="stylesheet" type="text/css" />
    <style>
        .action-badge { padding: 3px 8px; border-radius: 3px; font-size: 11px; }
        .action-INVOICE_CREATED { background: #00a65a; color: white; }
        .action-INVOICE_UPDATED { background: #f39c12; color: white; }
        .action-INVOICE_DELETED { background: #dd4b39; color: white; }
        .action-PAYMENT_COLLECTED { background: #3c8dbc; color: white; }
        .action-PAYMENT_EDITED { background: #f39c12; color: white; }
        .action-REFUND_ISSUED { background: #605ca8; color: white; }
        .action-REFUND_APPROVED { background: #00a65a; color: white; }
        .action-REFUND_REJECTED { background: #dd4b39; color: white; }
        .action-DISCOUNT_APPLIED { background: #39cccc; color: white; }
        .action-RECONCILIATION_COMPLETED { background: #001f3f; color: white; }
        .action-GATE_BYPASSED { background: #ff851b; color: white; }
        .log-detail { font-size: 12px; color: #666; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
<div class="content-wrapper">
    <section class="content-header">
        <h1>Billing Audit Logs <small>Financial Activity Trail</small></h1>
        <ol class="breadcrumb">
            <li><a href="<?= base_url('app/ebilling') ?>"><i class="fa fa-dashboard"></i> Billing & Finance</a></li>
            <li class="active">Audit Logs</li>
        </ol>
    </section>

    <section class="content">
        <!-- Filters -->
        <div class="box box-default collapsed-box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <form method="get" action="" class="form-inline">
                    <div class="form-group">
                        <label>Action:</label>
                        <select name="action" class="form-control">
                            <option value="">All Actions</option>
                            <option value="INVOICE_CREATED" <?= ($filters['action'] ?? '') === 'INVOICE_CREATED' ? 'selected' : '' ?>>Invoice Created</option>
                            <option value="INVOICE_UPDATED" <?= ($filters['action'] ?? '') === 'INVOICE_UPDATED' ? 'selected' : '' ?>>Invoice Updated</option>
                            <option value="INVOICE_DELETED" <?= ($filters['action'] ?? '') === 'INVOICE_DELETED' ? 'selected' : '' ?>>Invoice Deleted</option>
                            <option value="PAYMENT_COLLECTED" <?= ($filters['action'] ?? '') === 'PAYMENT_COLLECTED' ? 'selected' : '' ?>>Payment Collected</option>
                            <option value="REFUND_ISSUED" <?= ($filters['action'] ?? '') === 'REFUND_ISSUED' ? 'selected' : '' ?>>Refund Issued</option>
                            <option value="REFUND_APPROVED" <?= ($filters['action'] ?? '') === 'REFUND_APPROVED' ? 'selected' : '' ?>>Refund Approved</option>
                            <option value="DISCOUNT_APPLIED" <?= ($filters['action'] ?? '') === 'DISCOUNT_APPLIED' ? 'selected' : '' ?>>Discount Applied</option>
                            <option value="RECONCILIATION_COMPLETED" <?= ($filters['action'] ?? '') === 'RECONCILIATION_COMPLETED' ? 'selected' : '' ?>>Reconciliation</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>From:</label>
                        <input type="date" name="date_from" class="form-control" value="<?= $filters['date_from'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>To:</label>
                        <input type="date" name="date_to" class="form-control" value="<?= $filters['date_to'] ?? '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Patient:</label>
                        <input type="text" name="patient_no" class="form-control" placeholder="Patient No" value="<?= $filters['patient_no'] ?? '' ?>">
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?= base_url('app/ebilling/audit_logs') ?>" class="btn btn-default">Clear</a>
                </form>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-list"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Logs</span>
                        <span class="info-box-number"><?= number_format($total_logs ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-green">
                    <span class="info-box-icon"><i class="fa fa-plus"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Invoices Created</span>
                        <span class="info-box-number"><?= number_format($stats['invoices_created'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-blue">
                    <span class="info-box-icon"><i class="fa fa-money"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Payments Collected</span>
                        <span class="info-box-number"><?= number_format($stats['payments_collected'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="info-box bg-purple">
                    <span class="info-box-icon"><i class="fa fa-undo"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Refunds</span>
                        <span class="info-box-number"><?= number_format($stats['refunds'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Logs Table -->
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-history"></i> Activity Log</h3>
                <div class="box-tools pull-right">
                    <a href="<?= base_url('app/ebilling/export_audit_logs?' . http_build_query($filters ?? [])) ?>" class="btn btn-sm btn-success">
                        <i class="fa fa-download"></i> Export
                    </a>
                </div>
            </div>
            <div class="box-body">
                <table id="audit_table" class="table table-bordered table-striped table-hover">
                    <thead>
                        <tr>
                            <th width="150">Timestamp</th>
                            <th width="120">Action</th>
                            <th>User</th>
                            <th>Details</th>
                            <th width="100">Amount</th>
                            <th width="100">Patient</th>
                            <th width="80">IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($logs)): ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td>
                                    <small><?= date('M d, Y', strtotime($log->created_at)) ?></small><br>
                                    <small class="text-muted"><?= date('H:i:s', strtotime($log->created_at)) ?></small>
                                </td>
                                <td>
                                    <span class="action-badge action-<?= $log->action ?>">
                                        <?= str_replace('_', ' ', $log->action) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($log->username ?? 'System') ?></strong>
                                    <?php if ($log->user_role): ?>
                                        <br><small class="text-muted"><?= htmlspecialchars($log->user_role) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log->invoice_no): ?>
                                        <span class="label label-default">INV: <?= htmlspecialchars($log->invoice_no) ?></span>
                                    <?php endif; ?>
                                    <?php if ($log->description): ?>
                                        <p class="log-detail"><?= htmlspecialchars($log->description) ?></p>
                                    <?php endif; ?>
                                    <?php if ($log->payment_method): ?>
                                        <small class="text-muted">Method: <?= htmlspecialchars($log->payment_method) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($log->amount > 0): ?>
                                        <strong><?= number_format($log->amount, 2) ?></strong>
                                        <?php if ($log->previous_amount !== null && $log->new_amount !== null): ?>
                                            <br><small class="text-muted">
                                                <?= number_format($log->previous_amount, 2) ?> → <?= number_format($log->new_amount, 2) ?>
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($log->patient_no): ?>
                                        <a href="<?= base_url('app/ebilling/patient/' . $log->patient_no) ?>">
                                            <?= htmlspecialchars($log->patient_no) ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?= htmlspecialchars($log->ip_address ?? '-') ?></small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">No audit logs found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (isset($pagination)): ?>
            <div class="box-footer">
                <?= $pagination ?>
            </div>
            <?php endif; ?>
        </div>
    </section>
</div>
        </aside>
    </div>
    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/jquery.dataTables.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/plugins/datatables/dataTables.bootstrap.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    $(document).ready(function() {
        $('#audit_table').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            searching: true,
            paging: true
        });
    });
    </script>
</body>
</html>
