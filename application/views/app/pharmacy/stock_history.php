<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock History<?php if (isset($drug) && $drug) echo ' — ' . htmlspecialchars($drug->drug_name); ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --danger:  #dd4b39;
            --warning: #f39c12;
            --success: #00a65a;
            --purple:  #605ca8;
            --primary: #3c8dbc;
            --shadow:  0 2px 10px rgba(0,0,0,0.09);
            --radius:  8px;
        }
        /* Drug header card */
        .sh-drug-card {
            background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:20px 24px; margin-bottom:18px;
            display:flex; flex-wrap:wrap; align-items:center; gap:24px;
            border-left:5px solid var(--primary);
        }
        .sh-drug-name { font-size:20px; font-weight:700; margin:0; }
        .sh-drug-meta { font-size:13px; color:#666; margin-top:4px; }
        .sh-stat { text-align:center; min-width:90px; }
        .sh-stat .sh-val { font-size:24px; font-weight:700; line-height:1; }
        .sh-stat .sh-lbl { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:2px; }
        .sh-divider { width:1px; height:50px; background:#e9ecef; }
        /* Table wrap */
        .sh-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
        .sh-table-header {
            padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center;
        }
        .sh-table-header h4 { margin:0; font-weight:700; font-size:15px; }
        .sh-table { width:100%; border-collapse:collapse; font-size:13px; }
        .sh-table thead th {
            background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap;
        }
        .sh-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .sh-table tbody tr:hover { background:#f7fbff; }
        .sh-table td { padding:10px 12px; vertical-align:middle; }
        .sh-table tbody tr.row-in  { border-left:4px solid var(--success); }
        .sh-table tbody tr.row-out { border-left:4px solid var(--danger); }
        .sh-table tbody tr.row-adj { border-left:4px solid var(--warning); }
        /* Type badges */
        .sh-type {
            display:inline-block; padding:3px 10px; border-radius:12px;
            font-size:11px; font-weight:700; white-space:nowrap;
        }
        .sh-type.DISPENSE  { background:#d1ecf1; color:#0c5460; }
        .sh-type.RESTOCK   { background:#d4edda; color:#155724; }
        .sh-type.WRITE_OFF { background:#f8d7da; color:#721c24; }
        .sh-type.BATCH_IN  { background:#d4edda; color:#155724; }
        .sh-type.ADJUSTMENT{ background:#fff3cd; color:#856404; }
        .sh-type.OTHER     { background:#e9ecef; color:#495057; }
        /* Delta */
        .sh-delta { font-weight:700; font-size:14px; }
        .sh-delta.pos { color:var(--success); }
        .sh-delta.neg { color:var(--danger);  }
        /* Arrow indicator */
        .sh-arrow { display:inline-flex; align-items:center; gap:4px; font-size:12px; color:#aaa; }
        /* Stock bar */
        .sh-bar-wrap { width:70px; background:#e9ecef; border-radius:6px; height:8px;
            display:inline-block; vertical-align:middle; overflow:hidden; margin-left:6px; }
        .sh-bar-fill { height:100%; border-radius:6px; }
        .sh-bar-fill.ok  { background:var(--success); }
        .sh-bar-fill.low { background:var(--warning); }
        .sh-bar-fill.out { background:var(--danger);  }
        /* Filter bar */
        .sh-filter-bar { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:0; padding:12px 18px; background:#f8f9fa; border-bottom:1px solid #e9ecef; }
        .sh-filter-btn { padding:5px 14px; border-radius:16px; border:2px solid #ddd;
            background:#fff; font-size:12px; font-weight:600; cursor:pointer; color:#555; transition:all .2s; }
        .sh-filter-btn:hover { background:#e9ecef; }
        .sh-filter-btn.active { background:var(--primary); border-color:var(--primary); color:#fff; }
        .table td { vertical-align:middle !important; }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>
        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-history"></i> Stock History
                    <?php if (isset($drug) && $drug): ?>
                        <small>&mdash; <?php echo htmlspecialchars($drug->drug_name); ?></small>
                    <?php endif; ?>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy/stock">Stock</a></li>
                    <li class="active">History</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <?php
                    $drug    = isset($drug)    ? $drug    : null;
                    $history = isset($history) && is_array($history) ? $history : array();
                    $batches = isset($batch_stock) && is_array($batch_stock) ? $batch_stock : array();
                ?>

                <!-- Drug Header Card -->
                <?php if ($drug): ?>
                <?php
                    $curStock  = (float)$drug->nStock;
                    $reorder   = (float)$drug->re_order_level;
                    $isOut     = ($curStock <= 0);
                    $isLow     = (!$isOut && $reorder > 0 && $curStock <= $reorder);
                    $stockCls  = $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success');
                    $barPct    = ($reorder > 0) ? min(100, round($curStock / ($reorder * 2) * 100)) : ($curStock > 0 ? 100 : 0);
                    $barFill   = $isOut ? 'out' : ($isLow ? 'low' : 'ok');
                    $nhis      = isset($drug->is_nhis_covered) ? (int)$drug->is_nhis_covered : 0;
                    /* Compute net movement from history */
                    $netIn = 0; $netOut = 0;
                    foreach ($history as $h) {
                        $qc = isset($h->qty_change) ? (float)$h->qty_change : 0;
                        if ($qc > 0) $netIn  += $qc;
                        else         $netOut += abs($qc);
                    }
                ?>
                <div class="sh-drug-card">
                    <div style="flex:1;min-width:200px;">
                        <div class="sh-drug-name"><?php echo htmlspecialchars($drug->drug_name); ?></div>
                        <div class="sh-drug-meta">
                            ID: <?php echo (int)$drug->drug_id; ?>
                            &nbsp;·&nbsp; Price: GHS <?php echo number_format((float)$drug->nPrice, 2); ?>
                            &nbsp;·&nbsp;
                            <?php if ($nhis): ?>
                                <span style="color:#605ca8;font-weight:600;"><i class="fa fa-shield"></i> NHIS: GHS <?php echo number_format((float)$drug->nhis_price, 2); ?></span>
                            <?php else: ?>
                                <span>Cash only</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="sh-divider"></div>
                    <div class="sh-stat">
                        <div class="sh-val <?php echo $stockCls; ?>"><?php echo number_format($curStock, 0); ?>
                            <span class="sh-bar-wrap"><span class="sh-bar-fill <?php echo $barFill; ?>" style="width:<?php echo $barPct; ?>%;"></span></span>
                        </div>
                        <div class="sh-lbl">Current Stock</div>
                    </div>
                    <div class="sh-divider"></div>
                    <div class="sh-stat">
                        <div class="sh-val text-muted"><?php echo number_format($reorder, 0); ?></div>
                        <div class="sh-lbl">Reorder Level</div>
                    </div>
                    <div class="sh-divider"></div>
                    <div class="sh-stat">
                        <div class="sh-val text-success">+<?php echo number_format($netIn, 0); ?></div>
                        <div class="sh-lbl">Total In</div>
                    </div>
                    <div class="sh-divider"></div>
                    <div class="sh-stat">
                        <div class="sh-val text-danger">-<?php echo number_format($netOut, 0); ?></div>
                        <div class="sh-lbl">Total Out</div>
                    </div>
                    <div style="margin-left:auto;">
                        <a href="<?php echo base_url(); ?>app/pharmacy/stock" class="btn btn-default btn-sm">
                            <i class="fa fa-arrow-left"></i> Back to Stock
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Active Batches (if any) -->
                <?php if (count($batches) > 0): ?>
                <div class="sh-table-wrap" style="margin-bottom:18px;">
                    <div class="sh-table-header">
                        <h4><i class="fa fa-archive text-primary"></i> Active Batch Stock (FEFO)</h4>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="sh-table">
                            <thead><tr>
                                <th>Batch No.</th>
                                <th>Qty Remaining</th>
                                <th>Expiry Date</th>
                                <th>Days Left</th>
                                <th>Unit Cost</th>
                                <th>Selling Price</th>
                                <th>Supplier</th>
                            </tr></thead>
                            <tbody>
                            <?php foreach ($batches as $b):
                                $expDate  = isset($b->expiry_date) ? (string)$b->expiry_date : '';
                                $daysLeft = null;
                                $dCls     = '';
                                if ($expDate !== '' && $expDate !== '0000-00-00') {
                                    $daysLeft = (int)((strtotime($expDate) - time()) / 86400);
                                    $dCls = $daysLeft <= 0 ? 'text-danger' : ($daysLeft <= 14 ? 'text-warning' : 'text-success');
                                }
                            ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($b->batch_number ?? ''); ?></code></td>
                                <td><strong><?php echo number_format((float)($b->quantity_remaining ?? 0), 0); ?></strong></td>
                                <td><?php echo htmlspecialchars($expDate); ?></td>
                                <td>
                                    <?php if ($daysLeft !== null): ?>
                                        <span class="<?php echo $dCls; ?>"><?php echo $daysLeft; ?>d</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo number_format((float)($b->unit_cost ?? 0), 2); ?></td>
                                <td><?php echo number_format((float)($b->selling_price ?? 0), 2); ?></td>
                                <td><?php echo htmlspecialchars($b->supplier ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Adjustment History -->
                <div class="sh-table-wrap">
                    <div class="sh-table-header">
                        <h4><i class="fa fa-history text-primary"></i> Adjustment History
                            <small class="text-muted" style="font-weight:400;font-size:12px;margin-left:8px;">
                                <?php echo count($history); ?> records
                            </small>
                        </h4>
                    </div>
                    <!-- Filter buttons -->
                    <div class="sh-filter-bar">
                        <button class="sh-filter-btn active" onclick="shFilter('ALL')">All</button>
                        <button class="sh-filter-btn" onclick="shFilter('DISPENSE')"><i class="fa fa-medkit"></i> Dispense</button>
                        <button class="sh-filter-btn" onclick="shFilter('RESTOCK')"><i class="fa fa-plus"></i> Restock</button>
                        <button class="sh-filter-btn" onclick="shFilter('WRITE_OFF')"><i class="fa fa-trash"></i> Write-off</button>
                        <button class="sh-filter-btn" onclick="shFilter('BATCH_IN')"><i class="fa fa-archive"></i> Batch In</button>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="sh-table" id="historyTable">
                            <thead><tr>
                                <th>#</th>
                                <th>Type</th>
                                <th>Change</th>
                                <th>Before → After</th>
                                <th>Reason</th>
                                <th>Reference</th>
                                <th>By</th>
                                <th>Date</th>
                            </tr></thead>
                            <tbody>
                            <?php if (count($history) === 0): ?>
                                <tr><td colspan="8" style="text-align:center;padding:40px;color:#aaa;">
                                    <i class="fa fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                    No history records found.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($history as $h):
                                    $adjType   = isset($h->adjustment_type) ? strtoupper(trim((string)$h->adjustment_type)) : 'OTHER';
                                    $qtyChange = isset($h->qty_change) ? (float)$h->qty_change : 0;
                                    $before    = isset($h->stock_before) ? (float)$h->stock_before : null;
                                    $after     = isset($h->stock_after)  ? (float)$h->stock_after  : null;
                                    $isIn      = ($qtyChange > 0);
                                    $rowCls    = $isIn ? 'row-in' : (in_array($adjType, ['WRITE_OFF','EXPIRED']) ? 'row-out' : ($qtyChange < 0 ? 'row-out' : 'row-adj'));
                                    $deltaCls  = $isIn ? 'pos' : 'neg';
                                    $prefix    = $isIn ? '+' : '';
                                    $typeCls   = array_key_exists($adjType, ['DISPENSE'=>1,'RESTOCK'=>1,'WRITE_OFF'=>1,'BATCH_IN'=>1,'ADJUSTMENT'=>1]) ? $adjType : 'OTHER';
                                    $knownTypes = ['DISPENSE','RESTOCK','WRITE_OFF','BATCH_IN','ADJUSTMENT'];
                                    if (!in_array($adjType, $knownTypes)) $typeCls = 'OTHER';
                                    else $typeCls = $adjType;
                                ?>
                                <tr class="<?php echo $rowCls; ?>" data-type="<?php echo $adjType; ?>">
                                    <td><small class="text-muted"><?php echo (int)$h->adj_id; ?></small></td>
                                    <td>
                                        <span class="sh-type <?php echo $typeCls; ?>"><?php echo htmlspecialchars($adjType); ?></span>
                                    </td>
                                    <td>
                                        <span class="sh-delta <?php echo $deltaCls; ?>"><?php echo $prefix . number_format($qtyChange, 0); ?></span>
                                    </td>
                                    <td>
                                        <?php if ($before !== null && $after !== null): ?>
                                            <span class="sh-arrow">
                                                <?php echo number_format($before, 0); ?>
                                                <i class="fa fa-arrow-right" style="font-size:10px;"></i>
                                                <strong><?php echo number_format($after, 0); ?></strong>
                                            </span>
                                        <?php else: ?>
                                            <small class="text-muted">—</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars((string)(isset($h->reason) ? $h->reason : '')); ?></td>
                                    <td>
                                        <?php
                                            $refType = isset($h->reference_type) ? (string)$h->reference_type : '';
                                            $refId   = isset($h->reference_id)   ? (int)$h->reference_id      : 0;
                                            echo htmlspecialchars($refType);
                                            if ($refId > 0) echo ' <small class="text-muted">#' . $refId . '</small>';
                                        ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars((string)(isset($h->created_by) ? $h->created_by : '')); ?></small></td>
                                    <td><small><?php echo htmlspecialchars((string)(isset($h->created_at) ? $h->created_at : '')); ?></small></td>
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

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    <script>
    (function($) {
        'use strict';
        window.shFilter = function(type) {
            $('.sh-filter-btn').removeClass('active');
            $('.sh-filter-btn').filter(function() {
                return $(this).text().trim().toUpperCase().indexOf(type === 'ALL' ? '' : type) >= 0 || type === 'ALL';
            }).first().addClass('active');
            /* simpler: mark by onclick */
            var $btns = $('.sh-filter-btn');
            $btns.each(function() {
                if ($(this).attr('onclick') && $(this).attr('onclick').indexOf("'" + type + "'") >= 0) {
                    $(this).addClass('active');
                } else {
                    $(this).removeClass('active');
                }
            });
            $('#historyTable tbody tr').each(function() {
                if (type === 'ALL') { $(this).show(); return; }
                $(this).toggle($(this).data('type') === type);
            });
        };
    })(jQuery);
    </script>
</body>
</html>
