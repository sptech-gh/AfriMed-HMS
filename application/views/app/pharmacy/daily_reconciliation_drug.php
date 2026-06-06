<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Reconciliation - Drug Drilldown</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root { --danger:#dd4b39; --warning:#f39c12; --success:#00a65a; --shadow:0 2px 10px rgba(0,0,0,0.09); --radius:8px; }
        .boxx { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:14px 18px; margin-bottom:16px; }
        .rc-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .rc-badge.OK { background:#d4edda; color:#155724; }
        .rc-badge.WARNING { background:#fff3cd; color:#856404; }
        .rc-badge.CRITICAL { background:#f8d7da; color:#721c24; }
        .table { font-size:13px; }
        .num { text-align:right; font-variant-numeric: tabular-nums; }
        .diff-pos { color:var(--success); font-weight:700; }
        .diff-neg { color:var(--danger); font-weight:700; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-search"></i> Daily Reconciliation Drilldown</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                <li><a href="<?php echo base_url(); ?>app/pharmacy/daily_reconciliation?date=<?php echo urlencode(isset($date)?$date:''); ?>">Daily Reconciliation</a></li>
                <li class="active">Drug Detail</li>
            </ol>
        </section>

        <section class="content">
            <?php echo isset($message) ? $message : ''; ?>

            <?php
                $date = isset($date) ? $date : date('Y-m-d', strtotime('-1 day'));
                $drug_id = isset($drug_id) ? (int)$drug_id : 0;
                $summary = isset($summary) ? $summary : null;
                $dispenses = isset($dispenses) && is_array($dispenses) ? $dispenses : array();
                $billings = isset($billings) && is_array($billings) ? $billings : array();
                $adjustments = isset($adjustments) && is_array($adjustments) ? $adjustments : array();

                $drugName = ($summary && isset($summary->drug_name) && $summary->drug_name !== null) ? (string)$summary->drug_name : ('#' . $drug_id);
                $status = ($summary && isset($summary->status)) ? strtoupper((string)$summary->status) : 'OK';
                $sd = ($summary && isset($summary->stock_diff)) ? (float)$summary->stock_diff : 0.0;
                $bd = ($summary && isset($summary->billing_diff)) ? (float)$summary->billing_diff : 0.0;
                $sdClass = $sd < 0 ? 'diff-neg' : ($sd > 0 ? 'diff-pos' : '');
                $bdClass = $bd < 0 ? 'diff-neg' : ($bd > 0 ? 'diff-pos' : '');
            ?>

            <div class="boxx">
                <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
                    <div>
                        <div style="font-weight:700; font-size:16px;">Drug: <?php echo htmlspecialchars($drugName); ?></div>
                        <div style="color:#777; font-size:12px;">Date: <?php echo htmlspecialchars($date); ?> | Drug ID: <?php echo (int)$drug_id; ?></div>
                    </div>
                    <div>
                        <span class="rc-badge <?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status); ?></span>
                        <?php if ($summary && isset($summary->is_baseline) && (int)$summary->is_baseline === 1) { ?>
                            <span class="rc-badge WARNING">BASELINE</span>
                        <?php } ?>
                    </div>
                </div>

                <?php if ($summary) { ?>
                    <hr style="margin:12px 0;" />
                    <div class="row">
                        <div class="col-md-3"><b>Opening</b><div class="num"><?php echo number_format((float)$summary->opening_stock, 2); ?></div></div>
                        <div class="col-md-3"><b>Restocked</b><div class="num"><?php echo number_format((float)$summary->restocked_qty, 2); ?></div></div>
                        <div class="col-md-3"><b>Dispensed</b><div class="num"><?php echo number_format((float)$summary->dispensed_qty, 2); ?></div></div>
                        <div class="col-md-3"><b>Billed</b><div class="num"><?php echo number_format((float)$summary->billed_qty, 2); ?></div></div>
                    </div>
                    <div class="row" style="margin-top:10px;">
                        <div class="col-md-3"><b>Expected</b><div class="num"><?php echo number_format((float)$summary->expected_stock, 2); ?></div></div>
                        <div class="col-md-3"><b>Actual</b><div class="num"><?php echo number_format((float)$summary->actual_stock, 2); ?></div></div>
                        <div class="col-md-3"><b>Stock Diff</b><div class="num <?php echo $sdClass; ?>"><?php echo number_format($sd, 2); ?></div></div>
                        <div class="col-md-3"><b>Billing Diff</b><div class="num <?php echo $bdClass; ?>"><?php echo number_format($bd, 2); ?></div></div>
                    </div>
                <?php } else { ?>
                    <div style="margin-top:10px; color:#777;">No reconciliation summary found for this drug/date. Generate the day reconciliation first.</div>
                <?php } ?>

                <hr style="margin:12px 0;" />
                <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>app/pharmacy/daily_reconciliation?date=<?php echo urlencode($date); ?>"><i class="fa fa-arrow-left"></i> Back</a>
            </div>

            <div class="boxx">
                <h4 style="margin-top:0;">Dispense / Return rows</h4>
                <div style="overflow:auto;">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>DateTime</th>
                                <th>Status</th>
                                <th class="num">Dose Given</th>
                                <th>Patient</th>
                                <th>iop_med_id</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($dispenses) === 0) { ?>
                            <tr><td colspan="5" style="color:#777;">No dispense/return rows.</td></tr>
                        <?php } else { foreach ($dispenses as $d) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars(isset($d->dDateTime) ? (string)$d->dDateTime : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($d->status) ? (string)$d->status : ''); ?></td>
                                <td class="num"><?php echo number_format((float)(isset($d->dose_given) ? $d->dose_given : 0), 2); ?></td>
                                <td><?php echo htmlspecialchars(isset($d->patient_no) ? (string)$d->patient_no : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($d->iop_med_id) ? (string)$d->iop_med_id : ''); ?></td>
                            </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="boxx">
                <h4 style="margin-top:0;">Billing rows</h4>
                <div style="overflow:auto;">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Created</th>
                                <th>Txn Ref</th>
                                <th>Patient</th>
                                <th class="num">Qty</th>
                                <th>Order Status</th>
                                <th>Payment Status</th>
                                <th>Item Ref</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($billings) === 0) { ?>
                            <tr><td colspan="7" style="color:#777;">No billing rows.</td></tr>
                        <?php } else { foreach ($billings as $b) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars(isset($b->created_at) ? (string)$b->created_at : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($b->txn_ref) ? (string)$b->txn_ref : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($b->patient_no) ? (string)$b->patient_no : ''); ?></td>
                                <td class="num"><?php echo number_format((float)(isset($b->quantity) ? $b->quantity : 0), 2); ?></td>
                                <td><?php echo htmlspecialchars(isset($b->order_status) ? (string)$b->order_status : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($b->payment_status) ? (string)$b->payment_status : ''); ?></td>
                                <td><?php echo htmlspecialchars(isset($b->item_ref) ? (string)$b->item_ref : ''); ?></td>
                            </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="boxx">
                <h4 style="margin-top:0;">Stock adjustments (restock / corrections)</h4>
                <div style="overflow:auto;">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Created</th>
                                <th class="num">Qty Change</th>
                                <th>Reason</th>
                                <th>User</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($adjustments) === 0) { ?>
                            <tr><td colspan="4" style="color:#777;">No adjustment rows.</td></tr>
                        <?php } else { foreach ($adjustments as $a) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars(isset($a->created_at) ? (string)$a->created_at : ''); ?></td>
                                <td class="num"><?php echo number_format((float)(isset($a->qty_change) ? $a->qty_change : 0), 2); ?></td>
                                <td><?php echo htmlspecialchars(isset($a->reason) ? (string)$a->reason : (isset($a->notes) ? (string)$a->notes : '')); ?></td>
                                <td><?php echo htmlspecialchars(isset($a->created_by) ? (string)$a->created_by : ''); ?></td>
                            </tr>
                        <?php } } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </aside>
</div>
</body>
</html>
