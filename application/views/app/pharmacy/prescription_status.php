<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prescription Status Management</title>
    <meta content='width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no' name='viewport'>
    <link href="<?php echo base_url(); ?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url(); ?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        :root {
            --danger:  #dd4b39;
            --warning: #f39c12;
            --success: #00a65a;
            --primary: #3c8dbc;
            --purple:  #605ca8;
            --shadow:  0 2px 10px rgba(0,0,0,0.09);
            --radius:  8px;
        }
        /* Stat cards row */
        .ps-stat { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:16px 18px; display:flex; align-items:center; gap:14px;
            border-left:4px solid #ddd; margin-bottom:16px; }
        .ps-stat .ps-num { font-size:28px; font-weight:700; line-height:1; }
        .ps-stat .ps-lbl { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; margin-top:3px; }
        .ps-stat .ps-icon { font-size:32px; opacity:.18; margin-left:auto; }
        /* Table wrap */
        .ps-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .ps-table-header { padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center; }
        .ps-table-header h4 { margin:0; font-weight:700; font-size:15px; }
        .ps-table { width:100%; border-collapse:collapse; font-size:13px; }
        .ps-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .ps-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .ps-table tbody tr:hover { background:#f7fbff; }
        .ps-table td { padding:10px 12px; vertical-align:middle; }
        .ps-table tbody tr.locked-row { border-left:4px solid var(--danger); }
        .ps-table tbody tr.hold-row   { border-left:4px solid var(--warning); }
        /* Status badges */
        .ps-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .ps-badge.PENDING     { background:#fff3cd; color:#856404; }
        .ps-badge.VERIFIED    { background:#d1ecf1; color:#0c5460; }
        .ps-badge.IN_PROGRESS { background:#cce5ff; color:#004085; }
        .ps-badge.PARTIAL     { background:#e2d5f1; color:#4a3875; }
        .ps-badge.DISPENSED   { background:#d4edda; color:#155724; }
        .ps-badge.CANCELLED   { background:#f8d7da; color:#721c24; }
        .ps-badge.ON_HOLD     { background:#f5c6cb; color:#721c24; }
        .ps-badge.EXPIRED     { background:#e9ecef; color:#495057; }
        /* Workflow legend */
        .ps-legend { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:0; margin-bottom:18px; overflow:hidden; }
        .ps-legend-header { padding:12px 18px; border-bottom:1px solid #f0f0f0; cursor:pointer;
            display:flex; justify-content:space-between; align-items:center; user-select:none; }
        .ps-legend-header h4 { margin:0; font-size:14px; font-weight:700; }
        .ps-legend-body { padding:18px; display:none; }
        .ps-flow { font-family:monospace; font-size:12px; background:#f8f9fa;
            padding:14px 18px; border-radius:6px; color:#333; line-height:1.8; }
        /* Nav buttons */
        .ps-nav { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
        .ps-nav a { flex:1; min-width:160px; }
        .empty-state { text-align:center; padding:36px; color:#aaa; }
        .empty-state i { font-size:36px; display:block; margin-bottom:10px; }
    </style>
</head>
<body class="skin-blue">
<?php require_once(APPPATH.'views/include/header.php');?>
<div class="wrapper row-offcanvas row-offcanvas-left">
    <?php require_once(APPPATH.'views/include/sidebar.php');?>
    <aside class="right-side">
        <section class="content-header">
            <h1><i class="fa fa-lock"></i> Prescription Status Management</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                <li class="active">Prescription Status</li>
            </ol>
        </section>

        <section class="content">
            <?php echo isset($message) ? $message : ''; ?>

            <?php
                $statuses      = isset($statuses)      ? $statuses      : array();
                $status_counts = isset($status_counts) ? $status_counts : array();
                $locked        = isset($locked)  && is_array($locked)  ? $locked  : array();
                $on_hold       = isset($on_hold) && is_array($on_hold) ? $on_hold : array();
                $user_id       = $this->session->userdata('user_id');
                $user_role     = $this->session->userdata('role');
                $descriptions  = array(
                    'PENDING'     => 'New prescription awaiting pharmacist review',
                    'VERIFIED'    => 'Verified by pharmacist, ready for dispensing',
                    'IN_PROGRESS' => 'Dispensing currently in progress',
                    'PARTIAL'     => 'Partially dispensed — more stock needed',
                    'DISPENSED'   => 'Fully dispensed (terminal)',
                    'CANCELLED'   => 'Prescription cancelled (terminal)',
                    'ON_HOLD'     => 'Temporarily paused',
                    'EXPIRED'     => 'Prescription expired (terminal)',
                );
            ?>

            <!-- Status Summary Cards -->
            <div class="row">
                <?php foreach ($statuses as $code => $info):
                    $cnt = isset($status_counts[$code]) ? (int)$status_counts[$code] : 0;
                    $borderColourMap = [
                        'PENDING'     => '#f39c12',
                        'VERIFIED'    => '#3c8dbc',
                        'IN_PROGRESS' => '#3c8dbc',
                        'PARTIAL'     => '#605ca8',
                        'DISPENSED'   => '#00a65a',
                        'CANCELLED'   => '#dd4b39',
                        'ON_HOLD'     => '#dd4b39',
                        'EXPIRED'     => '#aaa',
                    ];
                    $bc = isset($borderColourMap[$code]) ? $borderColourMap[$code] : '#ddd';
                ?>
                <div class="col-lg-3 col-md-4 col-sm-6">
                    <div class="ps-stat" style="border-left-color:<?php echo $bc; ?>;">
                        <div>
                            <div class="ps-num" style="color:<?php echo $bc; ?>;"><?php echo $cnt; ?></div>
                            <div class="ps-lbl"><?php echo htmlspecialchars($info['label']); ?></div>
                        </div>
                        <i class="fa fa-<?php echo $info['icon']; ?> ps-icon" style="color:<?php echo $bc; ?>;"></i>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Locked Prescriptions -->
            <div class="ps-table-wrap">
                <div class="ps-table-header">
                    <h4><i class="fa fa-lock text-danger"></i> Currently Locked Prescriptions
                        <span style="background:#f8d7da;color:#721c24;border-radius:12px;padding:2px 9px;font-size:12px;margin-left:6px;"><?php echo count($locked); ?></span>
                    </h4>
                    <small class="text-muted">Locks prevent concurrent edits</small>
                </div>
                <?php if (count($locked) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="ps-table">
                        <thead><tr>
                            <th>Patient</th>
                            <th>Drug</th>
                            <th>Locked By</th>
                            <th>Locked At</th>
                            <th style="width:130px;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($locked as $rx): ?>
                        <tr class="locked-row">
                            <td>
                                <strong><?php echo htmlspecialchars($rx->firstname . ' ' . $rx->lastname); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($rx->patient_no); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($rx->drug_name); ?></td>
                            <td><small><?php echo htmlspecialchars($rx->locked_by); ?></small></td>
                            <td><small><?php echo date('d M Y H:i', strtotime($rx->locked_at)); ?></small></td>
                            <td>
                                <?php if ($rx->locked_by === $user_id || $user_role === 'admin'): ?>
                                <form method="POST" action="<?php echo base_url(); ?>app/pharmacy/unlock_prescription/<?php echo (int)$rx->iop_med_id; ?>" style="display:inline;">
                                    <button type="submit" class="btn btn-xs btn-warning"><i class="fa fa-unlock"></i> Unlock</button>
                                </form>
                                <?php else: ?>
                                <span class="text-muted" style="font-size:12px;">Another user</span>
                                <?php endif; ?>
                                <a href="<?php echo base_url(); ?>app/pharmacy/prescription_audit/<?php echo (int)$rx->iop_med_id; ?>" class="btn btn-xs btn-info">
                                    <i class="fa fa-history"></i> Audit
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-check-circle text-success"></i>
                        No prescriptions are currently locked.
                    </div>
                <?php endif; ?>
            </div>

            <!-- On Hold Prescriptions -->
            <div class="ps-table-wrap">
                <div class="ps-table-header">
                    <h4><i class="fa fa-pause text-warning"></i> Prescriptions On Hold
                        <span style="background:#fff3cd;color:#856404;border-radius:12px;padding:2px 9px;font-size:12px;margin-left:6px;"><?php echo count($on_hold); ?></span>
                    </h4>
                    <small class="text-muted">Resume to restore to PENDING</small>
                </div>
                <?php if (count($on_hold) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="ps-table">
                        <thead><tr>
                            <th>Patient</th>
                            <th>Drug</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th style="width:130px;">Actions</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($on_hold as $rx): ?>
                        <tr class="hold-row">
                            <td>
                                <strong><?php echo htmlspecialchars($rx->firstname . ' ' . $rx->lastname); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($rx->patient_no); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($rx->drug_name); ?></td>
                            <td><?php echo number_format($rx->quantity, 2); ?></td>
                            <td><span class="ps-badge ON_HOLD"><i class="fa fa-pause"></i> On Hold</span></td>
                            <td>
                                <form method="POST" action="<?php echo base_url(); ?>app/pharmacy/resume_prescription/<?php echo (int)$rx->iop_med_id; ?>" style="display:inline;">
                                    <button type="submit" class="btn btn-xs btn-success"><i class="fa fa-play"></i> Resume</button>
                                </form>
                                <a href="<?php echo base_url(); ?>app/pharmacy/prescription_audit/<?php echo (int)$rx->iop_med_id; ?>" class="btn btn-xs btn-info">
                                    <i class="fa fa-history"></i> Audit
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-check-circle text-success"></i>
                        No prescriptions are currently on hold.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Status Workflow Legend (collapsible) -->
            <div class="ps-legend">
                <div class="ps-legend-header" onclick="psToggleLegend()">
                    <h4><i class="fa fa-sitemap"></i> Status Workflow &amp; Definitions</h4>
                    <i class="fa fa-chevron-down" id="psLegendChevron"></i>
                </div>
                <div class="ps-legend-body" id="psLegendBody">
                    <div class="row">
                        <div class="col-md-5">
                            <?php foreach ($statuses as $code => $info): ?>
                            <div style="display:flex;align-items:baseline;gap:10px;margin-bottom:8px;">
                                <span class="ps-badge <?php echo $code; ?>" style="white-space:nowrap;min-width:90px;text-align:center;">
                                    <i class="fa fa-<?php echo $info['icon']; ?>"></i> <?php echo $info['label']; ?>
                                </span>
                                <small class="text-muted"><?php echo isset($descriptions[$code]) ? $descriptions[$code] : ''; ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="col-md-7">
                            <div class="ps-flow">
PENDING → VERIFIED → IN_PROGRESS → PARTIAL → DISPENSED
   ↓          ↓            ↓           ↓
   └──────────┴────────────┴───────────┴──→ CANCELLED
   ↓          ↓            ↓
   └──────────┴────────────┴──→ ON_HOLD → (resume)

Terminal states: DISPENSED · CANCELLED · EXPIRED</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <div class="ps-nav">
                <a href="<?php echo base_url(); ?>app/pharmacy" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Worklist
                </a>
                <a href="<?php echo base_url(); ?>app/pharmacy/controlled_drugs" class="btn btn-danger">
                    <i class="fa fa-shield"></i> Controlled Drugs
                </a>
                <a href="<?php echo base_url(); ?>app/pharmacy/pending_approvals" class="btn btn-warning">
                    <i class="fa fa-check-square-o"></i> Pending Approvals
                </a>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
<script>
window.psToggleLegend = function() {
    var $body = $('#psLegendBody');
    var $icon = $('#psLegendChevron');
    if ($body.is(':visible')) {
        $body.slideUp(200);
        $icon.removeClass('fa-chevron-up').addClass('fa-chevron-down');
    } else {
        $body.slideDown(200);
        $icon.removeClass('fa-chevron-down').addClass('fa-chevron-up');
    }
};
</script>
</body>
</html>
