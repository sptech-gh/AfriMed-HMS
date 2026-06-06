<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Walk-In Registration</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/font-awesome.min.css" rel="stylesheet">
    <link href="<?php echo base_url()?>public/css/AdminLTE.css" rel="stylesheet">
    <style>
        .walkin-stat-card{border-radius:8px;padding:20px 24px;color:#fff;display:flex;align-items:center;gap:18px;box-shadow:0 2px 8px rgba(0,0,0,.12);}
        .walkin-stat-card .stat-icon{font-size:36px;opacity:.85;}
        .walkin-stat-card .stat-val{font-size:30px;font-weight:700;line-height:1;}
        .walkin-stat-card .stat-lbl{font-size:13px;opacity:.9;margin-top:4px;}
        .card-blue{background:linear-gradient(135deg,#1a6fa5,#1e90cc);}
        .card-green{background:linear-gradient(135deg,#27a063,#2dba74);}
        .card-orange{background:linear-gradient(135deg,#e07b00,#f5a623);}
        .card-red{background:linear-gradient(135deg,#c0392b,#e74c3c);}
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
        .action-btn-group a{margin-right:4px;}
        .walkin-quick-actions{display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
        .walkin-quick-actions .btn{border-radius:6px;font-weight:600;padding:10px 22px;}
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php'); ?>

<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php'); ?>

    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-street-view"></i> Walk-In Registration
                <small>Independent service billing — not linked to OPD/IPD</small>
            </h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                <li class="active">Walk-In</li>
            </ol>
        </section>

        <section class="content">
            <?php if(isset($message)) echo $message; ?>

            <!-- STAT CARDS -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="walkin-stat-card card-blue">
                        <div class="stat-icon"><i class="fa fa-users"></i></div>
                        <div>
                            <div class="stat-val"><?php echo (int)$stats['total_clients']; ?></div>
                            <div class="stat-lbl">Walk-Ins Today</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="walkin-stat-card card-green" style="margin-top:0;">
                        <div class="stat-icon"><i class="fa fa-money"></i></div>
                        <div>
                            <div class="stat-val">GHS <?php echo number_format((float)$stats['total_revenue'], 2); ?></div>
                            <div class="stat-lbl">Revenue Today</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="walkin-stat-card card-orange" style="margin-top:0;">
                        <div class="stat-icon"><i class="fa fa-list-alt"></i></div>
                        <div>
                            <div class="stat-val"><?php echo (int)$stats['total_transactions']; ?></div>
                            <div class="stat-lbl">Transactions Today</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="walkin-stat-card card-red" style="margin-top:0;">
                        <div class="stat-icon"><i class="fa fa-clock-o"></i></div>
                        <div>
                            <div class="stat-val"><?php echo (int)$stats['pending_count']; ?></div>
                            <div class="stat-lbl">Pending Payments</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;"></div>

            <!-- QUICK ACTIONS -->
            <div class="walkin-quick-actions">
                <a href="<?php echo base_url()?>app/walkin/register" class="btn btn-primary btn-lg">
                    <i class="fa fa-user-plus"></i> New Walk-In
                </a>
                <a href="<?php echo base_url()?>app/walkin/history" class="btn btn-default btn-lg">
                    <i class="fa fa-history"></i> Transaction History
                </a>
            </div>

            <div class="row">
                <!-- TODAY'S TRANSACTIONS -->
                <div class="col-md-8">
                    <div class="box box-primary">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-list"></i> Today's Transactions</h3>
                        </div>
                        <div class="box-body no-padding">
                            <table class="table table-hover table-condensed" style="margin:0;">
                                <thead>
                                    <tr style="background:#f8f9fa;">
                                        <th>Receipt</th>
                                        <th>Client</th>
                                        <th>Service</th>
                                        <th>Description</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Time</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($today_transactions)): ?>
                                    <tr><td colspan="8" class="text-center text-muted" style="padding:24px;">No walk-in transactions today.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($today_transactions as $t): ?>
                                    <?php
                                        $is_order_row = isset($t->source_type) && $t->source_type === 'order';
                                        $receipt_url = ($is_order_row && !empty($t->invoice_no))
                                            ? base_url() . 'app/cashier/invoice/' . urlencode($t->invoice_no)
                                            : base_url() . 'app/walkin/receipt/' . (int)$t->id;
                                    ?>
                                    <tr>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($t->receipt_number); ?></small></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($t->client_name); ?></strong>
                                            <?php if($t->phone): ?><br><small class="text-muted"><?php echo htmlspecialchars($t->phone); ?></small><?php endif; ?>
                                        </td>
                                        <td><span class="badge-service svc-<?php echo $t->service_type; ?>"><?php echo $t->service_type; ?></span></td>
                                        <td style="max-width:160px;"><small><?php echo htmlspecialchars($t->description); ?></small></td>
                                        <td><strong>GHS <?php echo number_format((float)$t->amount, 2); ?></strong></td>
                                        <td><span class="status-<?php echo $t->payment_status; ?>"><?php echo $t->payment_status; ?></span></td>
                                        <td><small><?php echo date('H:i', strtotime($t->transaction_date)); ?></small></td>
                                        <td class="action-btn-group">
                                            <a href="<?php echo $receipt_url; ?>" class="btn btn-xs btn-info" title="Receipt"><i class="fa fa-file-text-o"></i></a>
                                            <?php if(!$is_order_row && $t->payment_status === 'Pending'): ?>
                                            <a href="<?php echo base_url()?>app/walkin/mark_paid/<?php echo $t->id; ?>" class="btn btn-xs btn-success" title="Mark Paid" onclick="return confirm('Mark this transaction as PAID? Stock will be deducted for Pharmacy transactions.');"><i class="fa fa-check"></i></a>
                                            <?php endif; ?>
                                            <a href="<?php echo base_url()?>app/walkin/add_transaction/<?php echo $t->walkin_client_id; ?>" class="btn btn-xs btn-success" title="Add Service"><i class="fa fa-plus"></i></a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- SERVICE BREAKDOWN + RECENT WALK-INS -->
                <div class="col-md-4">
                    <!-- Service Breakdown -->
                    <div class="box box-success">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-pie-chart"></i> Revenue by Service</h3>
                        </div>
                        <div class="box-body no-padding">
                            <?php if (empty($service_breakdown)): ?>
                                <p class="text-muted text-center" style="padding:16px 0;">No data yet.</p>
                            <?php else: ?>
                            <table class="table table-condensed" style="margin:0;">
                                <thead><tr><th>Service</th><th>Count</th><th>Revenue</th></tr></thead>
                                <tbody>
                                <?php foreach ($service_breakdown as $s): ?>
                                <tr>
                                    <td><span class="badge-service svc-<?php echo $s->service_type; ?>"><?php echo $s->service_type; ?></span></td>
                                    <td><?php echo (int)$s->count; ?></td>
                                    <td><strong>GHS <?php echo number_format((float)$s->revenue, 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent Walk-ins -->
                    <div class="box box-warning">
                        <div class="box-header with-border">
                            <h3 class="box-title"><i class="fa fa-user-circle"></i> Recent Walk-Ins</h3>
                        </div>
                        <div class="box-body no-padding">
                            <?php if (empty($recent_walkins)): ?>
                                <p class="text-muted text-center" style="padding:16px 0;">No walk-ins today.</p>
                            <?php else: ?>
                            <ul class="list-group list-group-flush" style="margin:0;">
                                <?php foreach ($recent_walkins as $w): ?>
                                <li class="list-group-item" style="padding:8px 14px;border-left:none;border-right:none;">
                                    <strong><?php echo htmlspecialchars($w->client_name); ?></strong>
                                    <span class="pull-right text-muted" style="font-size:11px;"><?php echo date('H:i', strtotime($w->created_at)); ?></span>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($w->phone ?? ''); ?></small>
                                    <span class="pull-right">
                                        <a href="<?php echo base_url()?>app/walkin/add_transaction/<?php echo $w->id; ?>" class="btn btn-xs btn-primary"><i class="fa fa-plus"></i> Add Service</a>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </aside>
</div>

<script src="<?php echo base_url()?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url()?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url()?>public/js/AdminLTE/app.js"></script>
</body>
</html>
