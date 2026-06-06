<!DOCTYPE html>
<html>
<head>
        <meta charset="UTF-8">
        <title>Pharmacy Alerts</title>
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
            /* Stat cards */
            .al-stat { background:#fff; border-radius:var(--radius); padding:18px 20px;
                box-shadow:var(--shadow); display:flex; align-items:center; gap:16px;
                border-left:4px solid #ddd; margin-bottom:16px; cursor:pointer;
                text-decoration:none; color:inherit; transition:all .25s; }
            .al-stat:hover { transform:translateY(-2px); box-shadow:0 5px 18px rgba(0,0,0,.13); color:inherit; text-decoration:none; }
            .al-stat.out    { border-left-color:var(--danger);  }
            .al-stat.low    { border-left-color:var(--warning); }
            .al-stat.expiry { border-left-color:var(--purple);  }
            .al-stat.expire2{ border-left-color:#8B0000;        }
            .al-stat.pending{ border-left-color:var(--primary); }
            .al-stat .al-num  { font-size:32px; font-weight:700; line-height:1; }
            .al-stat .al-lbl  { font-size:12px; color:#777; text-transform:uppercase; letter-spacing:.5px; margin-top:3px; }
            .al-stat .al-icon { font-size:36px; opacity:.2; margin-left:auto; }
            /* Tab nav */
            .al-tabs { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:16px; }
            .al-tab { padding:8px 18px; border-radius:20px; border:2px solid #ddd;
                background:#f5f5f5; font-size:13px; font-weight:600; cursor:pointer;
                transition:all .2s; color:#555; }
            .al-tab:hover { background:#e0e0e0; }
            .al-tab.active { color:#fff; border-color:transparent; }
            .al-tab.t-out.active     { background:var(--danger);  }
            .al-tab.t-low.active     { background:var(--warning); }
            .al-tab.t-expiring.active{ background:var(--purple);  }
            .al-tab.t-expired.active { background:#8B0000;        }
            .al-tab.t-pending.active { background:var(--primary); }
            /* Section panels */
            .al-panel { display:none; }
            .al-panel.active { display:block; }
            /* Table */
            .al-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; }
            .al-table-header { padding:14px 18px; border-bottom:1px solid #f0f0f0;
                display:flex; justify-content:space-between; align-items:center; }
            .al-table-header h4 { margin:0; font-weight:700; font-size:15px; }
            .al-table { width:100%; border-collapse:collapse; font-size:13px; }
            .al-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
                text-align:left; border-bottom:2px solid #e0e0e0; }
            .al-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
            .al-table tbody tr:hover { background:#f7fbff; }
            .al-table td { padding:10px 12px; vertical-align:middle; }
            .al-table tbody tr.row-critical { border-left:4px solid var(--danger); }
            .al-table tbody tr.row-warn     { border-left:4px solid var(--warning); }
            .al-table tbody tr.row-ok       { border-left:4px solid var(--success); }
            /* Badges */
            .al-badge { display:inline-block; padding:3px 9px; border-radius:12px;
                font-size:11px; font-weight:700; }
            .al-badge.out     { background:#f8d7da; color:#721c24; }
            .al-badge.low     { background:#fff3cd; color:#856404; }
            .al-badge.expiry  { background:#e2d5f1; color:#4a3875; }
            .al-badge.expired { background:#f8d7da; color:#721c24; }
            /* Days pill */
            .days-pill { display:inline-block; padding:3px 9px; border-radius:12px;
                font-size:12px; font-weight:700; }
            .days-pill.d-critical { background:#f8d7da; color:#721c24; }
            .days-pill.d-warn     { background:#fff3cd; color:#856404; }
            .days-pill.d-ok       { background:#d4edda; color:#155724; }
            /* Stock bar */
            .al-bar-wrap { width:80px; background:#e9ecef; border-radius:6px;
                height:8px; display:inline-block; vertical-align:middle; overflow:hidden; }
            .al-bar-fill { height:100%; border-radius:6px; }
            .al-bar-fill.ok  { background:var(--success); }
            .al-bar-fill.low { background:var(--warning); }
            .al-bar-fill.out { background:var(--danger);  }
            /* Toast */
            .al-toast-wrap { position:fixed; top:70px; right:20px; z-index:9999; min-width:260px; }
            .al-toast { padding:12px 18px; border-radius:8px; margin-bottom:8px;
                font-size:13px; font-weight:600; box-shadow:0 4px 12px rgba(0,0,0,.15);
                animation:alSlide .3s ease; color:#fff; }
            .al-toast.success { background:var(--success); }
            .al-toast.error   { background:var(--danger);  }
            @keyframes alSlide { from{transform:translateX(120%);opacity:0;} to{transform:translateX(0);opacity:1;} }
            .table td { vertical-align:middle !important; }
        </style>
    </head>
    <body class="skin-blue">
        <?php require_once(APPPATH.'views/include/header.php');?>
        <div class="wrapper row-offcanvas row-offcanvas-left">
            <?php require_once(APPPATH.'views/include/sidebar.php');?>
            <aside class="right-side">
                <section class="content-header">
                    <h1><i class="fa fa-bell"></i> Pharmacy Alerts</h1>
                    <ol class="breadcrumb">
                        <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                        <li><a href="<?php echo base_url()?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                        <li class="active">Alerts</li>
                    </ol>
                </section>

                <section class="content">
                    <?php echo isset($message) ? $message : ''; ?>

                    <?php
                        $alertData     = isset($alerts) ? $alerts : array();
                        $outCount      = isset($alertData['out_of_stock'])        ? (int)$alertData['out_of_stock']        : 0;
                        $lowCount      = isset($alertData['low_stock'])            ? (int)$alertData['low_stock']            : 0;
                        $expiringCount = isset($alertData['expiring_soon'])        ? (int)$alertData['expiring_soon']        : 0;
                        $expiredCount  = isset($alertData['expired'])              ? (int)$alertData['expired']              : 0;
                        $pendingCount  = isset($alertData['pending_prescriptions'])? (int)$alertData['pending_prescriptions']: 0;
                        $lowDrugs      = isset($low_stock_drugs)  && is_array($low_stock_drugs)  ? $low_stock_drugs  : array();
                        $outDrugs      = isset($out_stock_drugs)  && is_array($out_stock_drugs)  ? $out_stock_drugs  : array();
                        $expiringList  = isset($expiring_batches) && is_array($expiring_batches) ? $expiring_batches : array();
                        $expiredList   = isset($expired_batches)  && is_array($expired_batches)  ? $expired_batches  : array();
                    ?>

                    <!-- Stat Cards -->
                    <div class="row">
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <a href="javascript:void(0)" onclick="alTab('out')" class="al-stat out">
                                <div><div class="al-num text-danger"><?php echo $outCount; ?></div><div class="al-lbl">Out of Stock</div></div>
                                <i class="fa fa-ban al-icon text-danger"></i>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <a href="javascript:void(0)" onclick="alTab('low')" class="al-stat low">
                                <div><div class="al-num text-warning"><?php echo $lowCount; ?></div><div class="al-lbl">Low Stock</div></div>
                                <i class="fa fa-exclamation-triangle al-icon text-warning"></i>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <a href="javascript:void(0)" onclick="alTab('expiring')" class="al-stat expiry">
                                <div><div class="al-num" style="color:var(--purple);"><?php echo $expiringCount; ?></div><div class="al-lbl">Expiring ≤30d</div></div>
                                <i class="fa fa-clock-o al-icon" style="color:var(--purple);"></i>
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-4 col-sm-6">
                            <a href="javascript:void(0)" onclick="alTab('expired')" class="al-stat expire2">
                                <div><div class="al-num text-danger"><?php echo $expiredCount; ?></div><div class="al-lbl">Expired Batches</div></div>
                                <i class="fa fa-times-circle al-icon text-danger"></i>
                            </a>
                        </div>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <a href="<?php echo base_url(); ?>app/pharmacy" class="al-stat pending">
                                <div><div class="al-num text-primary"><?php echo $pendingCount; ?></div><div class="al-lbl">Pending Rx</div></div>
                                <i class="fa fa-list al-icon text-primary"></i>
                            </a>
                        </div>
                    </div>

                    <!-- Tab Nav -->
                    <div class="al-tabs">
                        <button class="al-tab t-out active" onclick="alTab('out')">
                            <i class="fa fa-ban"></i> Out of Stock
                            <?php if ($outCount > 0): ?><span class="badge" style="background:var(--danger);color:#fff;"><?php echo $outCount; ?></span><?php endif; ?>
                        </button>
                        <button class="al-tab t-low" onclick="alTab('low')">
                            <i class="fa fa-exclamation-triangle"></i> Low Stock
                            <?php if ($lowCount > 0): ?><span class="badge" style="background:var(--warning);color:#fff;"><?php echo $lowCount; ?></span><?php endif; ?>
                        </button>
                        <button class="al-tab t-expiring" onclick="alTab('expiring')">
                            <i class="fa fa-clock-o"></i> Expiring Soon
                            <?php if ($expiringCount > 0): ?><span class="badge" style="background:var(--purple);color:#fff;"><?php echo $expiringCount; ?></span><?php endif; ?>
                        </button>
                        <button class="al-tab t-expired" onclick="alTab('expired')">
                            <i class="fa fa-times-circle"></i> Expired Batches
                            <?php if ($expiredCount > 0): ?><span class="badge" style="background:#8B0000;color:#fff;"><?php echo $expiredCount; ?></span><?php endif; ?>
                        </button>
                    </div>

                    <!-- OUT OF STOCK panel -->
                    <div class="al-panel active" id="panel-out">
                        <div class="al-table-wrap">
                            <div class="al-table-header">
                                <h4><i class="fa fa-ban text-danger"></i> Out of Stock Drugs</h4>
                                <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_out=1" class="btn btn-xs btn-default">
                                    <i class="fa fa-external-link"></i> Stock Management
                                </a>
                            </div>
                            <div style="overflow-x:auto;">
                                <table class="al-table">
                                    <thead><tr>
                                        <th>Drug Name</th>
                                        <th>Generic</th>
                                        <th>Stock</th>
                                        <th>Reorder</th>
                                        <th>Action</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php if (count($outDrugs) === 0): ?>
                                        <tr><td colspan="5" style="text-align:center;padding:30px;color:#aaa;">
                                            <i class="fa fa-check-circle" style="font-size:24px;"></i><br>No out-of-stock drugs
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($outDrugs as $drug): ?>
                                        <tr class="row-critical">
                                            <td><strong><?php echo htmlspecialchars($drug->drug_name); ?></strong></td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars($drug->generic_name ?? ''); ?></small></td>
                                            <td><strong class="text-danger">0</strong>
                                                <span class="al-bar-wrap"><span class="al-bar-fill out" style="width:0%;"></span></span>
                                            </td>
                                            <td><?php echo number_format((float)$drug->re_order_level, 0); ?></td>
                                            <td>
                                                <a href="<?php echo base_url(); ?>app/pharmacy/stock" class="btn btn-success btn-xs">
                                                    <i class="fa fa-plus"></i> Restock
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- LOW STOCK panel -->
                    <div class="al-panel" id="panel-low">
                        <div class="al-table-wrap">
                            <div class="al-table-header">
                                <h4><i class="fa fa-exclamation-triangle text-warning"></i> Low Stock Drugs</h4>
                                <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_low=1" class="btn btn-xs btn-default">
                                    <i class="fa fa-external-link"></i> Stock Management
                                </a>
                            </div>
                            <div style="overflow-x:auto;">
                                <table class="al-table">
                                    <thead><tr>
                                        <th>Drug Name</th>
                                        <th>Generic</th>
                                        <th>Stock</th>
                                        <th>Reorder Level</th>
                                        <th>Gap</th>
                                        <th>Action</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php if (count($lowDrugs) === 0): ?>
                                        <tr><td colspan="6" style="text-align:center;padding:30px;color:#aaa;">
                                            <i class="fa fa-check-circle" style="font-size:24px;"></i><br>No low stock items
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($lowDrugs as $drug):
                                            $st = (float)$drug->nStock;
                                            $ro = (float)$drug->re_order_level;
                                            $barPct = ($ro > 0) ? min(100, round($st / $ro * 100)) : 0;
                                            $gap = max(0, $ro - $st);
                                        ?>
                                        <tr class="row-warn">
                                            <td><strong><?php echo htmlspecialchars($drug->drug_name); ?></strong></td>
                                            <td><small class="text-muted"><?php echo htmlspecialchars($drug->generic_name ?? ''); ?></small></td>
                                            <td>
                                                <strong class="text-warning"><?php echo number_format($st, 0); ?></strong>
                                                <span class="al-bar-wrap"><span class="al-bar-fill low" style="width:<?php echo $barPct; ?>%;"></span></span>
                                            </td>
                                            <td><?php echo number_format($ro, 0); ?></td>
                                            <td><span class="al-badge low">Need <?php echo number_format($gap, 0); ?></span></td>
                                            <td>
                                                <a href="<?php echo base_url(); ?>app/pharmacy/stock" class="btn btn-success btn-xs">
                                                    <i class="fa fa-plus"></i> Restock
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- EXPIRING SOON panel -->
                    <div class="al-panel" id="panel-expiring">
                        <div class="al-table-wrap">
                            <div class="al-table-header">
                                <h4><i class="fa fa-clock-o" style="color:var(--purple);"></i> Expiring Soon (Next 30 Days)</h4>
                            </div>
                            <div style="overflow-x:auto;">
                                <table class="al-table">
                                    <thead><tr>
                                        <th>Drug Name</th>
                                        <th>Batch No.</th>
                                        <th>Qty Remaining</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                        <th>Supplier</th>
                                    </tr></thead>
                                    <tbody>
                                    <?php if (count($expiringList) === 0): ?>
                                        <tr><td colspan="6" style="text-align:center;padding:30px;color:#aaa;">
                                            <i class="fa fa-check-circle" style="font-size:24px;"></i><br>No batches expiring soon
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($expiringList as $batch):
                                            $expDate  = isset($batch->expiry_date) ? (string)$batch->expiry_date : '';
                                            $daysLeft = '';
                                            $dPillCls = 'd-ok';
                                            $rowCls   = '';
                                            if ($expDate !== '' && $expDate !== '0000-00-00') {
                                                $diff = (int)((strtotime($expDate) - time()) / 86400);
                                                $daysLeft = $diff;
                                                if ($diff <= 7)       { $dPillCls = 'd-critical'; $rowCls = 'row-critical'; }
                                                elseif ($diff <= 14)  { $dPillCls = 'd-warn';     $rowCls = 'row-warn'; }
                                            }
                                        ?>
                                        <tr class="<?php echo $rowCls; ?>">
                                            <td><strong><?php echo htmlspecialchars($batch->drug_name ?? ''); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($batch->batch_number ?? ''); ?></code></td>
                                            <td><?php echo htmlspecialchars($batch->quantity_remaining ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($expDate); ?></td>
                                            <td>
                                                <?php if ($daysLeft !== ''): ?>
                                                    <span class="days-pill <?php echo $dPillCls; ?>"><?php echo (int)$daysLeft; ?>d</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($batch->supplier ?? ''); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- EXPIRED BATCHES panel -->
                    <div class="al-panel" id="panel-expired">
                        <div class="al-table-wrap">
                            <div class="al-table-header">
                                <h4><i class="fa fa-times-circle text-danger"></i> Expired Batches</h4>
                                <small class="text-muted">Removing a batch writes off remaining qty from stock</small>
                            </div>
                            <div style="overflow-x:auto;">
                                <table class="al-table">
                                    <thead><tr>
                                        <th>Drug Name</th>
                                        <th>Batch No.</th>
                                        <th>Qty Remaining</th>
                                        <th>Expiry Date</th>
                                        <th>Supplier</th>
                                        <th style="width:100px;">Action</th>
                                    </tr></thead>
                                    <tbody id="expiredTbody">
                                    <?php if (count($expiredList) === 0): ?>
                                        <tr id="expiredEmpty"><td colspan="6" style="text-align:center;padding:30px;color:#aaa;">
                                            <i class="fa fa-check-circle" style="font-size:24px;"></i><br>No expired batches
                                        </td></tr>
                                    <?php else: ?>
                                        <?php foreach ($expiredList as $batch):
                                            $stockId = isset($batch->stock_id) ? (int)$batch->stock_id : 0;
                                        ?>
                                        <tr class="row-critical" id="expiredRow<?php echo $stockId; ?>">
                                            <td><strong><?php echo htmlspecialchars($batch->drug_name ?? ''); ?></strong></td>
                                            <td><code><?php echo htmlspecialchars($batch->batch_number ?? ''); ?></code></td>
                                            <td><?php echo htmlspecialchars($batch->quantity_remaining ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($batch->expiry_date ?? ''); ?></td>
                                            <td><?php echo htmlspecialchars($batch->supplier ?? ''); ?></td>
                                            <td>
                                                <button type="button" class="btn btn-danger btn-xs ajax-remove-expired"
                                                    data-stock-id="<?php echo $stockId; ?>">
                                                    <i class="fa fa-trash"></i>
                                                    <i class="fa fa-spinner fa-spin" style="display:none;"></i>
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </section>
            </aside>
        </div>

        <!-- Toast container -->
        <div class="al-toast-wrap" id="alToastWrap"></div>

        <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
        <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
        <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
        <script>
        (function($) {
            'use strict';

            var REMOVE_URL = '<?php echo base_url(); ?>app/pharmacy/remove_expired';
            var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
            var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';

            /* ── Tab switching ── */
            window.alTab = function(name) {
                $('.al-panel').removeClass('active');
                $('.al-tab').removeClass('active');
                $('#panel-' + name).addClass('active');
                $('.al-tab.t-' + name).addClass('active');
            };

            /* ── Toast ── */
            function alToast(msg, type) {
                var t = $('<div class="al-toast ' + (type||'success') + '">' + $('<span>').text(msg).html() + '</div>');
                $('#alToastWrap').append(t);
                setTimeout(function() { t.fadeOut(400, function() { $(this).remove(); }); }, 4000);
            }

            /* ── AJAX remove expired batch ── */
            $(document).on('click', '.ajax-remove-expired', function() {
                var $btn     = $(this);
                var stockId  = parseInt($btn.data('stock-id'), 10);
                var $row     = $btn.closest('tr');

                if (!confirm('Remove this expired batch? This writes off the remaining qty from stock.')) return;

                $btn.prop('disabled', true).find('.fa-trash').hide();
                $btn.find('.fa-spinner').show();

                var removeData = { stock_id: stockId, reason: 'Expired' };
                removeData[csrfName] = csrfHash;
                $.post(REMOVE_URL, removeData)
                    .done(function() {
                        $row.fadeOut(400, function() {
                            $(this).remove();
                            if ($('#expiredTbody tr:visible').length === 0) {
                                $('#expiredTbody').html('<tr id="expiredEmpty"><td colspan="6" style="text-align:center;padding:30px;color:#aaa;"><i class="fa fa-check-circle" style="font-size:24px;"></i><br>No expired batches</td></tr>');
                            }
                        });
                        alToast('Expired batch removed and stock written off.', 'success');
                    })
                    .fail(function() {
                        $btn.prop('disabled', false).find('.fa-trash').show();
                        $btn.find('.fa-spinner').hide();
                        alToast('Failed to remove batch — please try again.', 'error');
                    });
            });

        })(jQuery);
        </script>
    </body>
</html>
