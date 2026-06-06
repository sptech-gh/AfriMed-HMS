<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Stock Management - Pharmacy</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --primary: #3c8dbc;
            --success: #00a65a;
            --warning: #f39c12;
            --danger:  #dd4b39;
            --purple:  #605ca8;
            --shadow:  0 2px 10px rgba(0,0,0,0.09);
            --radius:  8px;
        }

        /* ── Stat Cards ── */
        .sk-stat {
            background: #fff; border-radius: var(--radius);
            padding: 18px 20px; box-shadow: var(--shadow);
            display: flex; align-items: center; gap: 16px;
            border-left: 4px solid #ddd; margin-bottom: 16px;
            cursor: pointer; transition: all .25s;
            text-decoration: none; color: inherit;
        }
        .sk-stat:hover { transform: translateY(-2px); box-shadow: 0 5px 18px rgba(0,0,0,.13); color: inherit; text-decoration: none; }
        .sk-stat.total  { border-left-color: var(--primary); }
        .sk-stat.low    { border-left-color: var(--warning); }
        .sk-stat.out    { border-left-color: var(--danger); }
        .sk-stat.expiry { border-left-color: var(--purple); }
        .sk-stat .sk-num  { font-size: 32px; font-weight: 700; line-height: 1; }
        .sk-stat .sk-lbl  { font-size: 12px; color: #777; text-transform: uppercase; letter-spacing: .5px; margin-top: 3px; }
        .sk-stat .sk-icon { font-size: 36px; opacity: .2; margin-left: auto; }

        /* ── Filter Bar ── */
        .sk-filter-bar {
            background: #fff; border-radius: var(--radius);
            padding: 14px 18px; box-shadow: var(--shadow);
            margin-bottom: 16px; display: flex; flex-wrap: wrap; gap: 10px; align-items: flex-end;
        }
        .sk-filter-bar .form-group { margin: 0; }
        .sk-filter-bar .fg-search { flex: 1; min-width: 200px; }
        .sk-filter-btn {
            padding: 7px 16px; border-radius: 20px; border: 2px solid #ddd;
            background: #f5f5f5; font-size: 13px; font-weight: 600; cursor: pointer;
            transition: all .2s; color: #555; white-space: nowrap;
        }
        .sk-filter-btn:hover  { background: #e0e0e0; }
        .sk-filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .sk-filter-btn.low.active  { background: var(--warning); border-color: var(--warning); color: #fff; }
        .sk-filter-btn.out.active  { background: var(--danger);  border-color: var(--danger);  color: #fff; }
        .sk-filter-btn.expiry.active { background: var(--purple); border-color: var(--purple); color: #fff; }

        /* ── Table ── */
        .sk-table-wrap { background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; }
        .sk-table-header { padding: 14px 18px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .sk-table-header h4 { margin: 0; font-weight: 700; font-size: 15px; }
        #stockTable { width: 100%; border-collapse: collapse; font-size: 13px; }
        #stockTable thead th {
            background: #f5f7fa; padding: 10px 12px; font-weight: 700;
            text-align: left; border-bottom: 2px solid #e0e0e0; white-space: nowrap;
        }
        #stockTable tbody tr { border-bottom: 1px solid #f2f2f2; transition: background .15s; }
        #stockTable tbody tr:hover { background: #f7fbff; }
        #stockTable td { padding: 10px 12px; vertical-align: middle; }
        #stockTable tbody tr.row-out  { border-left: 4px solid var(--danger); }
        #stockTable tbody tr.row-low  { border-left: 4px solid var(--warning); }
        #stockTable tbody tr.row-ok   { border-left: 4px solid var(--success); }

        /* Stock level bar */
        .sk-bar-wrap { width: 100px; background: #e9ecef; border-radius: 6px; height: 10px; display: inline-block; vertical-align: middle; overflow: hidden; }
        .sk-bar-fill { height: 100%; border-radius: 6px; }
        .sk-bar-fill.ok      { background: var(--success); }
        .sk-bar-fill.low     { background: var(--warning); }
        .sk-bar-fill.out     { background: var(--danger);  }

        /* Badges */
        .sk-badge {
            display: inline-block; padding: 3px 9px; border-radius: 12px;
            font-size: 11px; font-weight: 700; white-space: nowrap;
        }
        .sk-badge.ok      { background: #d4edda; color: #155724; }
        .sk-badge.low     { background: #fff3cd; color: #856404; }
        .sk-badge.out     { background: #f8d7da; color: #721c24; }
        .sk-badge.nhis    { background: #e2d5f1; color: #4a3875; }
        .sk-badge.cash    { background: #e9ecef; color: #495057; }
        .sk-badge.expiry  { background: #f8d7da; color: #721c24; }

        /* Footer */
        .sk-footer {
            padding: 12px 18px; background: #f8f9fa; border-top: 1px solid #e9ecef;
            display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: #666;
        }
        .sk-footer .pagination-btns button {
            padding: 5px 12px; margin-left: 5px; border-radius: 6px;
            border: 1px solid #ddd; background: #fff; cursor: pointer; font-size: 13px;
        }
        .sk-footer .pagination-btns button:disabled { opacity: .4; cursor: not-allowed; }
        .sk-footer .pagination-btns button.active { background: var(--primary); color: #fff; border-color: var(--primary); }

        /* AJAX restock inline feedback */
        .restock-result { font-size: 12px; margin-top: 6px; }
        .ajax-modal-error { margin-top: 8px; }

        /* Toast */
        .sk-toast-wrap { position: fixed; top: 70px; right: 20px; z-index: 9999; min-width: 260px; }
        .sk-toast { padding: 12px 18px; border-radius: 8px; margin-bottom: 8px; font-size: 13px;
            font-weight: 600; box-shadow: 0 4px 12px rgba(0,0,0,.15); animation: skSlide .3s ease; color: #fff; }
        .sk-toast.success { background: var(--success); }
        .sk-toast.error   { background: var(--danger);  }
        @keyframes skSlide { from { transform: translateX(120%); opacity:0; } to { transform: translateX(0); opacity:1; } }

        @media (max-width: 768px) {
            .sk-filter-bar { flex-direction: column; }
            .sk-bar-wrap { width: 60px; }
        }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-cubes"></i> Stock Management</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li><a href="<?php echo base_url()?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                    <li class="active">Stock</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <?php
                    $totalCount   = isset($total_count)        ? (int)$total_count        : 0;
                    $lowCount     = isset($low_stock_count)    ? (int)$low_stock_count    : 0;
                    $outCount     = isset($out_of_stock_count) ? (int)$out_of_stock_count : 0;
                    $expireCount  = isset($expiring_count)     ? (int)$expiring_count     : 0;
                    $expiredCount = isset($expired_count)      ? (int)$expired_count      : 0;
                    $filteredCount= isset($filtered_count)     ? (int)$filtered_count     : 0;
                    $pageLimit    = (!empty($filters['limit']) && (int)$filters['limit'] > 0) ? (int)$filters['limit'] : 50;
                    $pageOffset   = (!empty($filters['offset'])) ? (int)$filters['offset'] : 0;
                    $activeFilter = '';
                    if (!empty($filters['show_out']))  $activeFilter = 'out';
                    elseif (!empty($filters['show_low'])) $activeFilter = 'low';
                    elseif (!empty($filters['show_expiring'])) $activeFilter = 'expiring';
                    elseif (!empty($filters['show_expired'])) $activeFilter = 'expired';
                ?>

                <!-- Summary Cards -->
                <div class="row">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <a href="<?php echo base_url(); ?>app/pharmacy/stock" class="sk-stat total">
                            <div><div class="sk-num text-primary"><?php echo $totalCount; ?></div><div class="sk-lbl">Total Drugs</div></div>
                            <i class="fa fa-cubes sk-icon text-primary"></i>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_low=1" class="sk-stat low">
                            <div><div class="sk-num text-warning"><?php echo $lowCount; ?></div><div class="sk-lbl">Low Stock</div></div>
                            <i class="fa fa-exclamation-triangle sk-icon text-warning"></i>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_out=1" class="sk-stat out">
                            <div><div class="sk-num text-danger"><?php echo $outCount; ?></div><div class="sk-lbl">Out of Stock</div></div>
                            <i class="fa fa-ban sk-icon text-danger"></i>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_expiring=1" class="sk-stat expiry">
                            <div><div class="sk-num" style="color:var(--purple);"><?php echo $expireCount; ?></div><div class="sk-lbl">Expiring Soon</div></div>
                            <i class="fa fa-calendar-times-o sk-icon" style="color:var(--purple);"></i>
                        </a>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_expired=1" class="sk-stat out">
                            <div><div class="sk-num text-danger"><?php echo $expiredCount; ?></div><div class="sk-lbl">Expired</div></div>
                            <i class="fa fa-calendar-times-o sk-icon text-danger"></i>
                        </a>
                    </div>
                </div>

                <!-- Filter Bar -->
                <div class="sk-filter-bar">
                    <form method="get" action="<?php echo base_url(); ?>app/pharmacy/stock" id="stockFilterForm" style="display:contents;">
                        <div class="form-group fg-search">
                            <label style="font-size:12px;color:#777;margin-bottom:4px;">Search</label>
                            <input type="text" name="search" id="stockSearch" class="form-control input-sm"
                                value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>"
                                placeholder="Drug name or generic name...">
                        </div>
                        <div class="form-group">
                            <label style="font-size:12px;color:#777;margin-bottom:4px;">Per page</label>
                            <select name="limit" class="form-control input-sm" style="width:80px;">
                                <?php foreach([25,50,100,200] as $l): ?>
                                    <option value="<?php echo $l; ?>" <?php echo $pageLimit == $l ? 'selected' : ''; ?>><?php echo $l; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="show_low" id="hShowLow" value="<?php echo !empty($filters['show_low']) ? '1' : '0'; ?>">
                        <input type="hidden" name="show_out" id="hShowOut" value="<?php echo !empty($filters['show_out']) ? '1' : '0'; ?>">
                        <input type="hidden" name="show_expiring" id="hShowExpiring" value="<?php echo !empty($filters['show_expiring']) ? '1' : '0'; ?>">
                        <input type="hidden" name="show_expired" id="hShowExpired" value="<?php echo !empty($filters['show_expired']) ? '1' : '0'; ?>">
                        <input type="hidden" name="offset" value="0">
                    </form>
                    <?php
                        $activeFilter = '';
                        if (!empty($filters['show_out'])) $activeFilter = 'out';
                        elseif (!empty($filters['show_low'])) $activeFilter = 'low';
                        elseif (!empty($filters['show_expiring'])) $activeFilter = 'expiring';
                        elseif (!empty($filters['show_expired'])) $activeFilter = 'expired';
                    ?>
                    <button type="button" class="sk-filter-btn <?php echo $activeFilter==='' ? 'active' : ''; ?>"
                        onclick="skSetFilter('')">All</button>
                    <button type="button" class="sk-filter-btn low <?php echo $activeFilter==='low' ? 'active' : ''; ?>"
                        onclick="skSetFilter('low')"><i class="fa fa-exclamation-triangle"></i> Low Stock</button>
                    <button type="button" class="sk-filter-btn out <?php echo $activeFilter==='out' ? 'active' : ''; ?>"
                        onclick="skSetFilter('out')"><i class="fa fa-ban"></i> Out of Stock</button>
                    <button type="button" class="sk-filter-btn expiry <?php echo $activeFilter==='expiring' ? 'active' : ''; ?>"
                        onclick="skSetFilter('expiring')"><i class="fa fa-calendar-times-o"></i> Expiring</button>
                    <button type="button" class="sk-filter-btn out <?php echo $activeFilter==='expired' ? 'active' : ''; ?>"
                        onclick="skSetFilter('expired')"><i class="fa fa-calendar-times-o"></i> Expired</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="$('#stockFilterForm').submit();">
                        <i class="fa fa-filter"></i> Apply
                    </button>
                    <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>app/pharmacy/stock">
                        <i class="fa fa-refresh"></i> Reset
                    </a>
                </div>

                <!-- Stock Table -->
                <div class="sk-table-wrap">
                    <div class="sk-table-header">
                        <h4><i class="fa fa-table"></i> Drug Stock
                            <small class="text-muted" style="font-size:12px;font-weight:400;margin-left:8px;">
                                <?php echo $filteredCount; ?> result<?php echo $filteredCount !== 1 ? 's' : ''; ?>
                            </small>
                        </h4>
                        <a href="<?php echo base_url(); ?>app/pharmacy/low_stock_report" class="btn btn-xs btn-default">
                            <i class="fa fa-file-text-o"></i> Low Stock Report
                        </a>
                    </div>

                    <div style="overflow-x:auto;">
                        <table id="stockTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Drug Name</th>
                                    <th>Generic / Form</th>
                                    <th>Stock Level</th>
                                    <th>Reorder</th>
                                    <th>Price</th>
                                    <th>NHIS</th>
                                    <th>Status</th>
                                    <th style="width:220px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (!isset($stock_list) || count($stock_list) === 0): ?>
                                <tr><td colspan="9" style="text-align:center;padding:40px;color:#aaa;">
                                    <i class="fa fa-inbox" style="font-size:36px;display:block;margin-bottom:10px;"></i>
                                    No drugs match this filter.
                                </td></tr>
                            <?php else: ?>
                                <?php foreach ($stock_list as $drug):
                                    $drugId  = (int)$drug->drug_id;
                                    $stock   = (float)$drug->nStock;
                                    $reorder = (float)$drug->re_order_level;
                                    $isOut   = ($stock <= 0);
                                    $isLow   = (!$isOut && $reorder > 0 && $stock <= $reorder);
                                    $nhis    = isset($drug->is_nhis_covered) ? (int)$drug->is_nhis_covered : 0;
                                    $rowClass= $isOut ? 'row-out' : ($isLow ? 'row-low' : 'row-ok');
                                    $barPct  = ($reorder > 0) ? min(100, round($stock / ($reorder * 2) * 100)) : ($stock > 0 ? 100 : 0);
                                    $barClass= $isOut ? 'out' : ($isLow ? 'low' : 'ok');
                                    $badgeClass = $isOut ? 'out' : ($isLow ? 'low' : 'ok');
                                ?>
                                <tr class="<?php echo $rowClass; ?>" id="stockRow<?php echo $drugId; ?>">
                                    <td><small class="text-muted"><?php echo $drugId; ?></small></td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($drug->drug_name); ?></strong>
                                        <?php if (!empty($drug->strength)): ?>
                                            <small class="text-muted"> <?php echo htmlspecialchars($drug->strength); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo htmlspecialchars($drug->generic_name ?? ''); ?>
                                            <?php if (!empty($drug->dosage_form)): ?>
                                                · <?php echo htmlspecialchars($drug->dosage_form); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div style="display:flex;align-items:center;gap:8px;">
                                            <strong class="<?php echo $isOut ? 'text-danger' : ($isLow ? 'text-warning' : 'text-success'); ?>" id="stockVal<?php echo $drugId; ?>">
                                                <?php echo number_format($stock, 0); ?>
                                            </strong>
                                            <span class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($drug->uom ?? ''); ?></span>
                                            <span class="sk-bar-wrap">
                                                <span class="sk-bar-fill <?php echo $barClass; ?>" style="width:<?php echo $barPct; ?>%;"></span>
                                            </span>
                                        </div>
                                    </td>
                                    <td><small><?php echo number_format($reorder, 0); ?></small></td>
                                    <td>
                                        <small>GHS <?php echo number_format((float)$drug->nPrice, 2); ?></small>
                                        <?php if ($nhis && !empty($drug->nhis_price)): ?>
                                            <br><small class="text-muted">NHIS: <?php echo number_format((float)$drug->nhis_price, 2); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($nhis): ?>
                                            <span class="sk-badge nhis"><i class="fa fa-shield"></i> NHIS</span>
                                        <?php else: ?>
                                            <span class="sk-badge cash">Cash</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="sk-badge <?php echo $badgeClass; ?>" id="stockBadge<?php echo $drugId; ?>">
                                            <?php if ($isOut): ?>
                                                <i class="fa fa-ban"></i> OUT
                                            <?php elseif ($isLow): ?>
                                                <i class="fa fa-exclamation-triangle"></i> LOW
                                            <?php else: ?>
                                                <i class="fa fa-check"></i> OK
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-xs"
                                            data-toggle="modal" data-target="#restockModal<?php echo $drugId; ?>">
                                            <i class="fa fa-plus"></i> Restock
                                        </button>
                                        <button type="button" class="btn btn-warning btn-xs"
                                            data-toggle="modal" data-target="#adjustModal<?php echo $drugId; ?>">
                                            <i class="fa fa-pencil"></i> Adjust
                                        </button>
                                        <a class="btn btn-info btn-xs"
                                            href="<?php echo base_url(); ?>app/pharmacy/stock_history/<?php echo $drugId; ?>">
                                            <i class="fa fa-history"></i>
                                        </a>

                                        <!-- Restock Modal (AJAX) -->
                                        <div class="modal fade" id="restockModal<?php echo $drugId; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form class="ajax-stock-form">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header" style="background:#00a65a;color:#fff;">
                                                            <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-plus"></i> Restock: <?php echo htmlspecialchars($drug->drug_name); ?></h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="drug_id" value="<?php echo $drugId; ?>">
                                                            <input type="hidden" name="action" value="restock">
                                                            <p>Current stock: <strong id="curStock<?php echo $drugId; ?>"><?php echo number_format($stock, 0); ?></strong> <?php echo htmlspecialchars($drug->uom ?? ''); ?></p>
                                                            <div class="form-group">
                                                                <label>Quantity to Add <span class="text-danger">*</span></label>
                                                                <input type="number" step="0.01" min="0.01" class="form-control" name="qty_change" required placeholder="e.g. 100">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Reason <span class="text-danger">*</span></label>
                                                                <textarea class="form-control" name="reason" rows="2" required placeholder="e.g. New supply received"></textarea>
                                                            </div>
                                                            <div class="ajax-modal-error alert alert-danger" style="display:none;"></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-success ajax-sk-btn">
                                                                <i class="fa fa-plus"></i>
                                                                <i class="fa fa-spinner fa-spin" style="display:none;"></i>
                                                                Restock
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Adjust Modal (AJAX) -->
                                        <div class="modal fade" id="adjustModal<?php echo $drugId; ?>" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form class="ajax-stock-form">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <div class="modal-header" style="background:#f39c12;color:#fff;">
                                                            <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                                                            <h4 class="modal-title"><i class="fa fa-pencil"></i> Adjust: <?php echo htmlspecialchars($drug->drug_name); ?></h4>
                                                        </div>
                                                        <div class="modal-body">
                                                            <input type="hidden" name="drug_id" value="<?php echo $drugId; ?>">
                                                            <input type="hidden" name="action" value="adjust">
                                                            <p>Current stock: <strong><?php echo number_format($stock, 0); ?></strong> <?php echo htmlspecialchars($drug->uom ?? ''); ?></p>
                                                            <div class="form-group">
                                                                <label>Quantity Change <small class="text-muted">(negative to remove)</small> <span class="text-danger">*</span></label>
                                                                <input type="number" step="0.01" class="form-control" name="qty_change" required placeholder="e.g. -5 or +10">
                                                            </div>
                                                            <div class="form-group">
                                                                <label>Reason <span class="text-danger">*</span></label>
                                                                <textarea class="form-control" name="reason" rows="2" required placeholder="e.g. Expired, Damaged, Correction"></textarea>
                                                            </div>
                                                            <div class="ajax-modal-error alert alert-danger" style="display:none;"></div>
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                            <button type="submit" class="btn btn-warning ajax-sk-btn">
                                                                <i class="fa fa-pencil"></i>
                                                                <i class="fa fa-spinner fa-spin" style="display:none;"></i>
                                                                Adjust
                                                            </button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Footer -->
                    <div class="sk-footer">
                        <span>
                            Showing
                            <?php $showing = min($pageOffset + $pageLimit, $filteredCount); ?>
                            <?php echo $filteredCount > 0 ? ($pageOffset + 1) : 0; ?>–<?php echo $showing; ?>
                            of <?php echo $filteredCount; ?> items
                        </span>
                        <div class="pagination-btns">
                            <?php $prevOffset = max(0, $pageOffset - $pageLimit); ?>
                            <?php $nextOffset = $pageOffset + $pageLimit; ?>
                            <form method="get" action="<?php echo base_url(); ?>app/pharmacy/stock" style="display:inline;">
                                <input type="hidden" name="search"   value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                                <input type="hidden" name="show_low" value="<?php echo !empty($filters['show_low']) ? '1' : '0'; ?>">
                                <input type="hidden" name="show_out" value="<?php echo !empty($filters['show_out']) ? '1' : '0'; ?>">
                                <input type="hidden" name="limit"    value="<?php echo $pageLimit; ?>">
                                <input type="hidden" name="offset"   value="<?php echo $prevOffset; ?>">
                                <button type="submit" <?php echo $pageOffset <= 0 ? 'disabled' : ''; ?>>
                                    <i class="fa fa-chevron-left"></i> Prev
                                </button>
                            </form>
                            <form method="get" action="<?php echo base_url(); ?>app/pharmacy/stock" style="display:inline;">
                                <input type="hidden" name="search"   value="<?php echo htmlspecialchars($filters['search'] ?? ''); ?>">
                                <input type="hidden" name="show_low" value="<?php echo !empty($filters['show_low']) ? '1' : '0'; ?>">
                                <input type="hidden" name="show_out" value="<?php echo !empty($filters['show_out']) ? '1' : '0'; ?>">
                                <input type="hidden" name="limit"    value="<?php echo $pageLimit; ?>">
                                <input type="hidden" name="offset"   value="<?php echo $nextOffset; ?>">
                                <button type="submit" <?php echo $nextOffset >= $filteredCount ? 'disabled' : ''; ?>>
                                    Next <i class="fa fa-chevron-right"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <!-- Toast container -->
    <div class="sk-toast-wrap" id="skToastWrap"></div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>

    <script>
    (function($) {
        'use strict';

        var ADJUST_AJAX_URL = '<?php echo base_url(); ?>app/pharmacy/adjust_stock_ajax';

        /* ── Toast ── */
        function skToast(msg, type) {
            type = type || 'success';
            var t = $('<div class="sk-toast ' + type + '">' + $('<span>').text(msg).html() + '</div>');
            $('#skToastWrap').append(t);
            setTimeout(function() { t.fadeOut(400, function() { $(this).remove(); }); }, 4000);
        }

        /* ── Filter toggle ── */
        window.skSetFilter = function(mode) {
            if (mode === 'low') {
                $('#hShowLow').val('1'); $('#hShowOut').val('0'); $('#hShowExpiring').val('0'); $('#hShowExpired').val('0');
            } else if (mode === 'out') {
                $('#hShowLow').val('0'); $('#hShowOut').val('1'); $('#hShowExpiring').val('0'); $('#hShowExpired').val('0');
            } else if (mode === 'expiring') {
                $('#hShowLow').val('0'); $('#hShowOut').val('0'); $('#hShowExpiring').val('1'); $('#hShowExpired').val('0');
            } else if (mode === 'expired') {
                $('#hShowLow').val('0'); $('#hShowOut').val('0'); $('#hShowExpiring').val('0'); $('#hShowExpired').val('1');
            } else {
                $('#hShowLow').val('0'); $('#hShowOut').val('0'); $('#hShowExpiring').val('0'); $('#hShowExpired').val('0');
            }
            $('[name="offset"]', '#stockFilterForm').val(0);
            $('#stockFilterForm').submit();
        };

        /* ── Live search debounce ── */
        var skDebounce;
        var skPageReady = false;
        // Delay enabling live search so browser's autofill/restore doesn't trigger a submit loop
        setTimeout(function() { skPageReady = true; }, 800);
        $('#stockSearch').on('input', function() {
            if (!skPageReady) return;
            clearTimeout(skDebounce);
            skDebounce = setTimeout(function() { $('#stockFilterForm').submit(); }, 600);
        });

        /* ── AJAX stock adjust / restock ── */
        $(document).on('submit', '.ajax-stock-form', function(e) {
            e.preventDefault();
            var $form  = $(this);
            var $btn   = $form.find('.ajax-sk-btn');
            var $err   = $form.find('.ajax-modal-error');
            var $modal = $form.closest('.modal');
            var drugId = $form.find('[name="drug_id"]').val();
            var action = $form.find('[name="action"]').val();

            $err.hide();
            $btn.prop('disabled', true).find('.fa-plus,.fa-pencil').hide();
            $btn.find('.fa-spinner').show();

            $.post(ADJUST_AJAX_URL, $form.serialize(), null, 'json')
                .done(function(resp) {
                    if (resp.ok) {
                        $modal.modal('hide');
                        var msg = resp.message || (action === 'restock' ? 'Restock submitted.' : 'Adjustment submitted.');
                        skToast(msg, 'success');
                        // Update stock display if auto-approved
                        if (resp.auto_approved && resp.new_stock !== undefined) {
                            $('#stockVal' + drugId).text(Math.round(resp.new_stock));
                            $('#curStock' + drugId).text(Math.round(resp.new_stock));
                        }
                        // Navigate to stock page directly (avoids reload loop from live-search)
                        setTimeout(function() {
                            window.location.href = '<?php echo base_url(); ?>app/pharmacy/stock';
                        }, 1500);
                    } else {
                        $err.text(resp.error || 'Unknown error.').show();
                        skToast(resp.error || 'Error', 'error');
                    }
                })
                .fail(function(jqXHR, textStatus, errorThrown) {
                    var errMsg = 'Server error';
                    if (jqXHR.status === 403) {
                        errMsg = 'Access denied (CSRF token may have expired). Please refresh the page and try again.';
                    } else if (jqXHR.status === 500) {
                        errMsg = 'Internal server error. Please try again.';
                    } else if (textStatus === 'parsererror') {
                        // Server returned non-JSON (likely a PHP error page or redirect)
                        errMsg = 'Unexpected server response. The action may have succeeded — please refresh the page.';
                    } else {
                        errMsg = 'Server error (' + (jqXHR.status || textStatus) + '). Please try again.';
                    }
                    $err.text(errMsg).show();
                    skToast(errMsg, 'error');
                })
                .always(function() {
                    $btn.prop('disabled', false).find('.fa-plus,.fa-pencil').show();
                    $btn.find('.fa-spinner').hide();
                });
        });

    })(jQuery);
    </script>
</body>
</html>
