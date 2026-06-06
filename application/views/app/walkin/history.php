<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Walk-In Transaction History</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .badge-service{display:inline-block;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600;}
        .svc-Laboratory{background:#dbeafe;color:#1e40af;}
        .svc-Sonography{background:#ede9fe;color:#6d28d9;}
        .svc-Pharmacy{background:#dcfce7;color:#166534;}
        .svc-Procedure{background:#fef3c7;color:#92400e;}
        .svc-Consultation{background:#e0f2fe;color:#0369a1;}
        .svc-Other{background:#f3f4f6;color:#374151;}
        .status-Paid{color:#16a34a;font-weight:600;}
        .status-Pending{color:#d97706;font-weight:600;}
        .status-Cancelled{color:#dc2626;font-weight:600;text-decoration:line-through;}
        .filter-bar{background:#fff;border:1px solid #e9ecef;border-radius:8px;padding:16px 20px;margin-bottom:20px;}
        .filter-bar .form-control{border-radius:6px;height:36px;font-size:13px;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-history"></i> Walk-In Transaction History</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li><a href="<?php echo base_url()?>app/walkin">Walk-In</a></li>
                <li class="active">History</li>
            </ol>
        </section>

        <section class="content">
            <?php if(isset($message)) echo $message; ?>

            <!-- Filter Bar -->
            <form method="get" action="<?php echo base_url()?>app/walkin/history" id="filterForm">
            <div class="filter-bar">
                <div class="row">
                    <div class="col-md-2 col-sm-4">
                        <label style="font-size:12px;font-weight:600;color:#6c757d;">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <label style="font-size:12px;font-weight:600;color:#6c757d;">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <label style="font-size:12px;font-weight:600;color:#6c757d;">Service</label>
                        <select name="service_type" class="form-control">
                            <option value="">All Services</option>
                            <?php foreach(['Laboratory','Sonography','Pharmacy','Procedure','Consultation','Other'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo (($filters['service_type'] ?? '') === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 col-sm-4">
                        <label style="font-size:12px;font-weight:600;color:#6c757d;">Status</label>
                        <select name="payment_status" class="form-control">
                            <option value="">All Status</option>
                            <option value="Paid" <?php echo (($filters['payment_status'] ?? '') === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                            <option value="Pending" <?php echo (($filters['payment_status'] ?? '') === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Cancelled" <?php echo (($filters['payment_status'] ?? '') === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <label style="font-size:12px;font-weight:600;color:#6c757d;">Search</label>
                        <input type="text" name="q" class="form-control" placeholder="Client name, receipt no..." value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                    </div>
                    <div class="col-md-1 col-sm-2" style="padding-top:19px;">
                        <button type="submit" class="btn btn-primary btn-block" style="height:36px;"><i class="fa fa-search"></i></button>
                    </div>
                </div>
            </div>
            </form>

            <!-- Results Table -->
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-list"></i> Transactions
                        <small class="text-muted">(<?php echo number_format($total); ?> records)</small>
                    </h3>
                    <div class="box-tools pull-right">
                        <a href="<?php echo base_url()?>app/walkin/register" class="btn btn-sm btn-primary">
                            <i class="fa fa-user-plus"></i> New Walk-In
                        </a>
                    </div>
                </div>
                <div class="box-body no-padding">
                    <table class="table table-hover table-condensed" style="margin:0;">
                        <thead>
                            <tr style="background:#f8f9fa;">
                                <th>Receipt No.</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Description</th>
                                <th>Amount</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Cashier</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="10" class="text-center text-muted" style="padding:30px;">No transactions found for the selected filters.</td></tr>
                        <?php else: ?>
                            <?php foreach($rows as $r): ?>
                            <?php
                                $is_order_row = isset($r->source_type) && $r->source_type === 'order';
                                $view_url = ($is_order_row && !empty($r->invoice_no))
                                    ? base_url() . 'app/cashier/invoice/' . urlencode($r->invoice_no)
                                    : base_url() . 'app/walkin/receipt/' . (int)$r->id;
                                $can_print_order_receipt = $is_order_row && !empty($r->receipt_number) && strpos((string)$r->receipt_number, 'OR-') === 0;
                            ?>
                            <tr class="<?php echo $r->payment_status === 'Cancelled' ? 'text-muted' : ''; ?>">
                                <td><code style="font-size:11px;"><?php echo htmlspecialchars($r->receipt_number); ?></code></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($r->client_name); ?></strong>
                                    <?php if($r->phone): ?><br><small class="text-muted"><?php echo htmlspecialchars($r->phone); ?></small><?php endif; ?>
                                </td>
                                <td><span class="badge-service svc-<?php echo $r->service_type; ?>"><?php echo $r->service_type; ?></span></td>
                                <td style="max-width:180px;"><small><?php echo htmlspecialchars($r->description); ?></small></td>
                                <td><strong>GHS <?php echo number_format((float)$r->amount, 2); ?></strong></td>
                                <td><small><?php echo htmlspecialchars($r->payment_method); ?></small></td>
                                <td><span class="status-<?php echo $r->payment_status; ?>"><?php echo $r->payment_status; ?></span></td>
                                <td><small><?php echo date('d/m/Y H:i', strtotime($r->transaction_date)); ?></small></td>
                                <td><small><?php echo htmlspecialchars($r->cashier_name ?: $r->cashier_id); ?></small></td>
                                <td>
                                    <a href="<?php echo $view_url; ?>" class="btn btn-xs btn-info" title="View Receipt"><i class="fa fa-file-text-o"></i></a>
                                    <?php if($is_order_row && $can_print_order_receipt): ?>
                                    <a href="<?php echo base_url()?>app/cashier/print_receipt/<?php echo urlencode($r->receipt_number); ?>" class="btn btn-xs btn-default" target="_blank" title="Print"><i class="fa fa-print"></i></a>
                                    <?php elseif(!$is_order_row): ?>
                                    <a href="<?php echo base_url()?>app/walkin/print_receipt/<?php echo $r->id; ?>" class="btn btn-xs btn-default" target="_blank" title="Print"><i class="fa fa-print"></i></a>
                                    <?php endif; ?>
                                    <?php if(!$is_order_row && $r->payment_status === 'Pending'): ?>
                                    <a href="<?php echo base_url()?>app/walkin/mark_paid/<?php echo $r->id; ?>" class="btn btn-xs btn-success" title="Mark Paid" onclick="return confirm('Mark this transaction as PAID? Stock will be deducted for Pharmacy transactions.');"><i class="fa fa-check"></i></a>
                                    <?php endif; ?>
                                    <?php if(!$is_order_row && $r->payment_status !== 'Cancelled'): ?>
                                    <a href="<?php echo base_url()?>app/walkin/cancel_transaction/<?php echo $r->id; ?>"
                                       class="btn btn-xs btn-danger" title="Cancel"
                                       onclick="return confirm('Cancel this transaction?');"><i class="fa fa-ban"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if($total > $limit): ?>
                <div class="box-footer clearfix">
                    <ul class="pagination pagination-sm no-margin pull-right">
                        <?php
                        $pages = ceil($total / $limit);
                        $qString = http_build_query(array_merge($filters, ['page' => 1]));
                        for($p = 1; $p <= $pages; $p++):
                            $qp = http_build_query(array_merge($filters, ['page' => $p]));
                        ?>
                        <li class="<?php echo $page == $p ? 'active' : ''; ?>">
                            <a href="<?php echo base_url()?>app/walkin/history?<?php echo $qp; ?>"><?php echo $p; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
</body>
</html>
