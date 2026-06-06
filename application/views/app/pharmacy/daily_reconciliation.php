<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Daily Pharmacy Reconciliation</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root { --danger:#dd4b39; --warning:#f39c12; --success:#00a65a; --shadow:0 2px 10px rgba(0,0,0,0.09); --radius:8px; }
        .rc-stat { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:16px 18px; display:flex; align-items:center; gap:14px; border-left:4px solid #ddd; margin-bottom:16px; }
        .rc-stat .rn { font-size:28px; font-weight:700; line-height:1; }
        .rc-stat .rl { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }
        .rc-filter { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); padding:14px 18px; margin-bottom:18px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .rc-filter input, .rc-filter select { height:34px; border:1.5px solid #ddd; border-radius:6px; padding:4px 10px; font-size:13px; color:#333; }
        .rc-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .rc-table-hdr { padding:14px 18px; border-bottom:1px solid #f0f0f0; display:flex; justify-content:space-between; align-items:center; }
        .rc-table-hdr h4 { margin:0; font-weight:700; font-size:15px; }
        .rc-table { width:100%; border-collapse:collapse; font-size:13px; }
        .rc-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700; text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .rc-table tbody tr { border-bottom:1px solid #f2f2f2; }
        .rc-table tbody tr:hover { background:#f7fbff; }
        .rc-table td { padding:10px 12px; vertical-align:middle; }
        .rc-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .rc-badge.OK { background:#d4edda; color:#155724; }
        .rc-badge.WARNING { background:#fff3cd; color:#856404; }
        .rc-badge.CRITICAL { background:#f8d7da; color:#721c24; }
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
            <h1><i class="fa fa-balance-scale"></i> Daily Pharmacy Reconciliation</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                <li class="active">Daily Reconciliation</li>
            </ol>
        </section>

        <section class="content">
            <?php echo isset($message) ? $message : ''; ?>

            <?php
                $date = isset($date) ? $date : date('Y-m-d', strtotime('-1 day'));
                $status = isset($status) ? $status : '';
                $rows = isset($rows) && is_array($rows) ? $rows : array();
                $critical = isset($critical) ? (int)$critical : 0;
                $warning = isset($warning) ? (int)$warning : 0;
                $ok_count = isset($ok_count) ? (int)$ok_count : 0;
            ?>

            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="rc-stat" style="border-left-color:var(--danger);">
                        <div>
                            <div class="rn" style="color:var(--danger);"><?php echo $critical; ?></div>
                            <div class="rl">Critical</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="rc-stat" style="border-left-color:var(--warning);">
                        <div>
                            <div class="rn" style="color:var(--warning);"><?php echo $warning; ?></div>
                            <div class="rl">Warning</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="rc-stat" style="border-left-color:var(--success);">
                        <div>
                            <div class="rn" style="color:var(--success);"><?php echo $ok_count; ?></div>
                            <div class="rl">OK</div>
                        </div>
                    </div>
                </div>
            </div>

            <form method="get" action="<?php echo base_url(); ?>app/pharmacy/daily_reconciliation">
                <div class="rc-filter">
                    <label style="margin:0; font-weight:700;">Date</label>
                    <input type="date" name="date" value="<?php echo htmlspecialchars($date); ?>" />
                    <label style="margin:0; font-weight:700;">Status</label>
                    <select name="status">
                        <option value="" <?php echo $status === '' ? 'selected' : ''; ?>>All</option>
                        <option value="CRITICAL" <?php echo strtoupper($status) === 'CRITICAL' ? 'selected' : ''; ?>>CRITICAL</option>
                        <option value="WARNING" <?php echo strtoupper($status) === 'WARNING' ? 'selected' : ''; ?>>WARNING</option>
                        <option value="OK" <?php echo strtoupper($status) === 'OK' ? 'selected' : ''; ?>>OK</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> View</button>
                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/daily_reconciliation?run=1" style="display:inline;">
                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>" />
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status); ?>" />
                        <button type="submit" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Generate</button>
                    </form>
                </div>
            </form>

            <div class="rc-table-wrap">
                <div class="rc-table-hdr">
                    <h4>Results for <?php echo htmlspecialchars($date); ?></h4>
                    <div style="font-size:12px; color:#777;">Rows: <?php echo count($rows); ?></div>
                </div>
                <div style="overflow:auto;">
                    <table class="rc-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Drug</th>
                                <th class="num">Opening</th>
                                <th class="num">Restocked</th>
                                <th class="num">Dispensed</th>
                                <th class="num">Billed</th>
                                <th class="num">Expected</th>
                                <th class="num">Actual</th>
                                <th class="num">Stock Diff</th>
                                <th class="num">Billing Diff</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (count($rows) === 0) { ?>
                            <tr>
                                <td colspan="10" style="padding:18px; color:#777;">No results for the selected date.</td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($rows as $r) {
                                $st = isset($r->status) ? strtoupper((string)$r->status) : 'OK';
                                $drugName = isset($r->drug_name) && $r->drug_name !== null ? (string)$r->drug_name : ('#' . (int)$r->drug_id);
                                $drugId = isset($r->drug_id) ? (int)$r->drug_id : 0;
                                $sd = isset($r->stock_diff) ? (float)$r->stock_diff : 0.0;
                                $bd = isset($r->billing_diff) ? (float)$r->billing_diff : 0.0;
                                $sdClass = $sd < 0 ? 'diff-neg' : ($sd > 0 ? 'diff-pos' : '');
                                $bdClass = $bd < 0 ? 'diff-neg' : ($bd > 0 ? 'diff-pos' : '');
                            ?>
                            <tr>
                                <td><span class="rc-badge <?php echo htmlspecialchars($st); ?>"><?php echo htmlspecialchars($st); ?></span></td>
                                <td>
                                    <a href="<?php echo base_url(); ?>app/pharmacy/daily_reconciliation_drug/<?php echo (int)$drugId; ?>?date=<?php echo urlencode($date); ?>" style="text-decoration:none;">
                                        <?php echo htmlspecialchars($drugName); ?>
                                    </a>
                                </td>
                                <td class="num"><?php echo number_format((float)$r->opening_stock, 2); ?></td>
                                <td class="num"><?php echo number_format((float)$r->restocked_qty, 2); ?></td>
                                <td class="num"><?php echo number_format((float)$r->dispensed_qty, 2); ?></td>
                                <td class="num"><?php echo number_format((float)$r->billed_qty, 2); ?></td>
                                <td class="num"><?php echo number_format((float)$r->expected_stock, 2); ?></td>
                                <td class="num"><?php echo number_format((float)$r->actual_stock, 2); ?></td>
                                <td class="num <?php echo $sdClass; ?>"><?php echo number_format($sd, 2); ?></td>
                                <td class="num <?php echo $bdClass; ?>"><?php echo number_format($bd, 2); ?></td>
                            </tr>
                            <?php } ?>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </aside>
</div>
</body>
</html>
