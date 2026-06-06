<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Financial Reconciliation</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --danger:  #dd4b39; --warning: #f39c12; --success: #00a65a;
            --primary: #3c8dbc; --purple: #605ca8;
            --shadow: 0 2px 10px rgba(0,0,0,0.09); --radius: 8px;
        }
        .rc-stat { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:16px 18px; display:flex; align-items:center; gap:14px;
            border-left:4px solid #ddd; margin-bottom:16px; }
        .rc-stat .rn { font-size:28px; font-weight:700; line-height:1; }
        .rc-stat .rl { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }
        .rc-stat .ri { font-size:32px; opacity:.15; margin-left:auto; }
        .rc-filter { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:14px 18px; margin-bottom:18px; display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        .rc-filter input, .rc-filter select { height:34px; border:1.5px solid #ddd; border-radius:6px;
            padding:4px 10px; font-size:13px; color:#333; }
        .rc-filter input:focus, .rc-filter select:focus { outline:none; border-color:var(--primary); }
        .rc-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .rc-table-hdr  { padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center; }
        .rc-table-hdr h4 { margin:0; font-weight:700; font-size:15px; }
        .rc-table { width:100%; border-collapse:collapse; font-size:13px; }
        .rc-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .rc-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .rc-table tbody tr:hover { background:#f7fbff; }
        .rc-table td { padding:10px 12px; vertical-align:middle; }
        .rc-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .rc-badge.PENDING  { background:#fff3cd; color:#856404; }
        .rc-badge.APPROVED { background:#d4edda; color:#155724; }
        .rc-badge.REJECTED { background:#f8d7da; color:#721c24; }
        .rc-badge.DRAFT    { background:#e9ecef; color:#495057; }
        .rc-badge.default  { background:#e9ecef; color:#495057; }
        .variance-pos { color:var(--success); font-weight:700; }
        .variance-neg { color:var(--danger);  font-weight:700; }
        .rc-nav { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
        .rc-nav a { flex:1; min-width:160px; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-calculator"></i> Financial Reconciliation</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                <li class="active">Reconciliations</li>
            </ol>
        </section>

        <section class="content">
            <?php echo isset($message) ? $message : ''; ?>

            <?php
                $reconciliations = isset($reconciliations) && is_array($reconciliations) ? $reconciliations : array();
                $recon_statuses  = isset($recon_statuses)  ? $recon_statuses  : array();
                $recon_types     = isset($recon_types)     ? $recon_types     : array();
                $stores          = isset($stores)          && is_array($stores)  ? $stores  : array();
                $filters         = isset($filters)         ? $filters         : array('status' => '', 'store_id' => '', 'date_from' => '', 'date_to' => '');
                $pending_count   = isset($pending_count)   ? (int)$pending_count : 0;
            ?>

            <!-- Stat cards -->
            <div class="row">
                <div class="col-md-4 col-sm-6">
                    <div class="rc-stat" style="border-left-color:var(--warning);">
                        <div>
                            <div class="rn text-warning"><?php echo $pending_count; ?></div>
                            <div class="rl">Pending Approval</div>
                        </div>
                        <i class="fa fa-clock-o ri text-warning"></i>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="rc-stat" style="border-left-color:var(--primary);">
                        <div>
                            <div class="rn text-primary"><?php echo count($reconciliations); ?></div>
                            <div class="rl">Total Reconciliations</div>
                        </div>
                        <i class="fa fa-list ri text-primary"></i>
                    </div>
                </div>
                <div class="col-md-4 col-sm-6">
                    <div class="rc-stat" style="border-left-color:var(--success);">
                        <div>
                            <div class="rn" style="font-size:18px;color:var(--success);"><?php echo date('d M Y'); ?></div>
                            <div class="rl">Today</div>
                        </div>
                        <i class="fa fa-calendar ri text-success"></i>
                    </div>
                </div>
            </div>

            <!-- Filter bar -->
            <form method="GET" action="">
                <div class="rc-filter">
                    <select name="status">
                        <option value="">All Statuses</option>
                        <?php foreach ($recon_statuses as $code => $info): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>"
                            <?php echo (($filters['status'] ?? '') === $code) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($info['label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="store_id">
                        <option value="">All Stores</option>
                        <?php foreach ($stores as $s): ?>
                        <option value="<?php echo (int)$s->store_id; ?>"
                            <?php echo (($filters['store_id'] ?? '') == $s->store_id) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($s->store_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>" title="Period from">
                    <input type="date" name="date_to"   value="<?php echo htmlspecialchars($filters['date_to']   ?? ''); ?>" title="Period to">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-search"></i> Filter</button>
                    <a href="<?php echo base_url(); ?>app/pharmacy/reconciliations" class="btn btn-default btn-sm"><i class="fa fa-refresh"></i> Clear</a>
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#createReconModal" style="margin-left:auto;">
                        <i class="fa fa-plus"></i> New Reconciliation
                    </button>
                </div>
            </form>

            <!-- Reconciliations table -->
            <div class="rc-table-wrap">
                <div class="rc-table-hdr">
                    <h4><i class="fa fa-calculator text-primary"></i> Reconciliations
                        <small class="text-muted" style="font-weight:400;font-size:12px;margin-left:8px;"><?php echo count($reconciliations); ?> records</small>
                    </h4>
                </div>
                <div style="overflow-x:auto;">
                    <table class="rc-table">
                        <thead><tr>
                            <th>Period</th>
                            <th>Type</th>
                            <th>Store</th>
                            <th>Sales</th>
                            <th>Profit</th>
                            <th>Variance</th>
                            <th>Status</th>
                            <th style="width:80px;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php if (count($reconciliations) === 0): ?>
                            <tr><td colspan="8" style="text-align:center;padding:36px;color:#aaa;">
                                <i class="fa fa-inbox" style="font-size:32px;display:block;margin-bottom:8px;"></i>
                                No reconciliations found. Click "New Reconciliation" to create one.
                            </td></tr>
                        <?php else: ?>
                        <?php foreach ($reconciliations as $r):
                            $rStat    = strtoupper(isset($r->status) ? (string)$r->status : '');
                            $statInfo = isset($recon_statuses[$rStat]) ? $recon_statuses[$rStat] : array('label' => $rStat);
                            $variance = isset($r->variance) ? (float)$r->variance : 0;
                            $varCls   = $variance < 0 ? 'variance-neg' : ($variance > 0 ? 'variance-pos' : '');
                            $discr    = isset($r->discrepancies_count) ? (int)$r->discrepancies_count : 0;
                        ?>
                        <tr>
                            <td>
                                <span style="font-weight:600;"><?php echo date('d M', strtotime($r->period_start)); ?></span>
                                <span class="text-muted">→</span>
                                <span style="font-weight:600;"><?php echo date('d M Y', strtotime($r->period_end)); ?></span>
                            </td>
                            <td><small><?php echo htmlspecialchars(isset($recon_types[$r->reconciliation_type]) ? $recon_types[$r->reconciliation_type] : (string)$r->reconciliation_type); ?></small></td>
                            <td><small><?php echo htmlspecialchars($r->store_name ?: 'All Stores'); ?></small></td>
                            <td><strong>GHS <?php echo number_format((float)($r->total_sales ?? 0), 2); ?></strong></td>
                            <td><small class="variance-pos">GHS <?php echo number_format((float)($r->gross_profit ?? 0), 2); ?></small></td>
                            <td>
                                <?php if ($variance != 0): ?>
                                    <span class="<?php echo $varCls; ?>">
                                        <?php echo ($variance > 0 ? '+' : '') . 'GHS ' . number_format($variance, 2); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                                <?php if ($discr > 0): ?>
                                    <br><span style="background:#fff3cd;color:#856404;border-radius:10px;padding:1px 7px;font-size:11px;font-weight:700;">
                                        <?php echo $discr; ?> issue<?php echo $discr !== 1 ? 's' : ''; ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="rc-badge <?php echo $rStat ?: 'default'; ?>">
                                    <?php echo htmlspecialchars($statInfo['label']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="<?php echo base_url(); ?>app/pharmacy/reconciliation_details/<?php echo (int)$r->reconciliation_id; ?>"
                                   class="btn btn-xs btn-primary"><i class="fa fa-eye"></i> View</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Navigation -->
            <div class="rc-nav">
                <a href="<?php echo base_url(); ?>app/pharmacy" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
                <a href="<?php echo base_url(); ?>app/pharmacy/stock" class="btn btn-info">
                    <i class="fa fa-cubes"></i> Stock Management
                </a>
            </div>

        </section>
    </aside>
</div>

<!-- Create Reconciliation Modal -->
<div class="modal fade" id="createReconModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="<?php echo base_url(); ?>app/pharmacy/create_reconciliation">
            <div class="modal-content">
                <div class="modal-header" style="background:var(--primary);color:#fff;">
                    <button type="button" class="close" data-dismiss="modal" style="color:#fff;">&times;</button>
                    <h4 class="modal-title"><i class="fa fa-calculator"></i> New Reconciliation</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label style="font-size:12px;color:#888;text-transform:uppercase;">Type <span class="text-danger">*</span></label>
                        <select name="reconciliation_type" class="form-control" required>
                            <?php foreach ($recon_types as $code => $label): ?>
                            <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $code === 'DAILY' ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label style="font-size:12px;color:#888;text-transform:uppercase;">Store (Optional)</label>
                        <select name="store_id" class="form-control">
                            <option value="">All Stores</option>
                            <?php foreach ($stores as $s): ?>
                            <option value="<?php echo (int)$s->store_id; ?>"><?php echo htmlspecialchars($s->store_name); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:12px;color:#888;text-transform:uppercase;">Period Start <span class="text-danger">*</span></label>
                                <input type="date" name="period_start" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label style="font-size:12px;color:#888;text-transform:uppercase;">Period End <span class="text-danger">*</span></label>
                                <input type="date" name="period_end" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-calculator"></i> Create &amp; Calculate</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
