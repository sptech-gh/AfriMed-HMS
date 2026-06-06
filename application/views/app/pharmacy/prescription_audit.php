<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Prescription Audit Trail</title>
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
        /* Prescription info card */
        .pat-card { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow);
            padding:20px 24px; margin-bottom:18px; border-left:5px solid var(--primary);
            display:flex; flex-wrap:wrap; gap:24px; align-items:center; }
        .pat-card-field { min-width:120px; }
        .pat-card-field .pcf-label { font-size:11px; color:#888; text-transform:uppercase; letter-spacing:.4px; }
        .pat-card-field .pcf-val   { font-size:15px; font-weight:700; margin-top:2px; }
        .pat-card-field .pcf-sub   { font-size:12px; color:#777; margin-top:1px; }
        .pat-divider { width:1px; height:50px; background:#e9ecef; }
        /* Status badges */
        .aud-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .aud-badge.PENDING     { background:#fff3cd; color:#856404; }
        .aud-badge.VERIFIED    { background:#d1ecf1; color:#0c5460; }
        .aud-badge.IN_PROGRESS { background:#cce5ff; color:#004085; }
        .aud-badge.PARTIAL     { background:#e2d5f1; color:#4a3875; }
        .aud-badge.DISPENSED   { background:#d4edda; color:#155724; }
        .aud-badge.CANCELLED   { background:#f8d7da; color:#721c24; }
        .aud-badge.ON_HOLD     { background:#f5c6cb; color:#721c24; }
        .aud-badge.EXPIRED     { background:#e9ecef; color:#495057; }
        .aud-badge.default     { background:#e9ecef; color:#495057; }
        /* Action badges */
        .act-badge { display:inline-block; padding:3px 10px; border-radius:12px; font-size:11px; font-weight:700; }
        .act-badge.LOCK          { background:#f8d7da; color:#721c24; }
        .act-badge.UNLOCK        { background:#d4edda; color:#155724; }
        .act-badge.STATUS_CHANGE { background:#cce5ff; color:#004085; }
        .act-badge.VERIFY        { background:#d1ecf1; color:#0c5460; }
        .act-badge.CANCEL        { background:#f8d7da; color:#721c24; }
        .act-badge.HOLD          { background:#fff3cd; color:#856404; }
        .act-badge.RESUME        { background:#d4edda; color:#155724; }
        .act-badge.other         { background:#e9ecef; color:#495057; }
        /* Timeline table */
        .aud-table-wrap { background:#fff; border-radius:var(--radius); box-shadow:var(--shadow); overflow:hidden; margin-bottom:18px; }
        .aud-table-header { padding:14px 18px; border-bottom:1px solid #f0f0f0;
            display:flex; justify-content:space-between; align-items:center; }
        .aud-table-header h4 { margin:0; font-weight:700; font-size:15px; }
        .aud-table { width:100%; border-collapse:collapse; font-size:13px; }
        .aud-table thead th { background:#f5f7fa; padding:10px 12px; font-weight:700;
            text-align:left; border-bottom:2px solid #e0e0e0; white-space:nowrap; }
        .aud-table tbody tr { border-bottom:1px solid #f2f2f2; transition:background .15s; }
        .aud-table tbody tr:hover { background:#f7fbff; }
        .aud-table td { padding:10px 12px; vertical-align:middle; }
        /* Status arrow */
        .st-arrow { display:inline-flex; align-items:center; gap:6px; flex-wrap:wrap; }
        /* Lock indicator */
        .lock-indicator { display:inline-flex; align-items:center; gap:6px;
            padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600; }
        .lock-indicator.locked   { background:#f8d7da; color:#721c24; }
        .lock-indicator.unlocked { background:#d4edda; color:#155724; }
        /* Nav */
        .aud-nav { display:flex; gap:10px; flex-wrap:wrap; margin-top:4px; }
        .aud-nav a { flex:1; min-width:160px; }
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
            <h1><i class="fa fa-history"></i> Prescription Audit Trail</h1>
            <ol class="breadcrumb">
                <li><a href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-medkit"></i> Pharmacy</a></li>
                <li><a href="<?php echo base_url(); ?>app/pharmacy/prescription_status">Prescription Status</a></li>
                <li class="active">Audit</li>
            </ol>
        </section>

        <section class="content">
            <?php
                $rx       = isset($prescription) ? $prescription : null;
                $audit    = isset($audit) && is_array($audit) ? $audit : array();
                $statuses = isset($statuses) ? $statuses : array();
                $knownActions = ['LOCK','UNLOCK','STATUS_CHANGE','VERIFY','CANCEL','HOLD','RESUME'];
            ?>

            <!-- Prescription info card -->
            <?php if ($rx): ?>
            <?php
                $rxStatus = isset($rx->prescription_status) ? (string)$rx->prescription_status : 'PENDING';
                $rxInfo   = isset($statuses[$rxStatus]) ? $statuses[$rxStatus] : array('label' => $rxStatus, 'icon' => 'question');
                $isLocked = !empty($rx->is_locked);
            ?>
            <div class="pat-card">
                <div class="pat-card-field">
                    <div class="pcf-label">Patient</div>
                    <div class="pcf-val"><?php echo htmlspecialchars($rx->firstname . ' ' . $rx->lastname); ?></div>
                    <div class="pcf-sub"><?php echo htmlspecialchars($rx->patient_no); ?></div>
                </div>
                <div class="pat-divider"></div>
                <div class="pat-card-field">
                    <div class="pcf-label">Drug</div>
                    <div class="pcf-val"><?php echo htmlspecialchars($rx->drug_name); ?></div>
                </div>
                <div class="pat-divider"></div>
                <div class="pat-card-field">
                    <div class="pcf-label">Quantity</div>
                    <div class="pcf-val"><?php echo number_format((float)$rx->quantity, 2); ?></div>
                </div>
                <div class="pat-divider"></div>
                <div class="pat-card-field">
                    <div class="pcf-label">Current Status</div>
                    <div style="margin-top:4px;">
                        <span class="aud-badge <?php echo $rxStatus; ?>">
                            <i class="fa fa-<?php echo $rxInfo['icon']; ?>"></i>
                            <?php echo htmlspecialchars($rxInfo['label']); ?>
                        </span>
                    </div>
                </div>
                <div class="pat-divider"></div>
                <div class="pat-card-field">
                    <div class="pcf-label">Lock Status</div>
                    <div style="margin-top:4px;">
                        <?php if ($isLocked): ?>
                            <span class="lock-indicator locked">
                                <i class="fa fa-lock"></i> Locked
                            </span>
                            <div class="pcf-sub" style="margin-top:4px;">by <?php echo htmlspecialchars($rx->locked_by ?? ''); ?></div>
                        <?php else: ?>
                            <span class="lock-indicator unlocked">
                                <i class="fa fa-unlock"></i> Unlocked
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="margin-left:auto;">
                    <a href="<?php echo base_url(); ?>app/pharmacy/prescription_status" class="btn btn-default btn-sm">
                        <i class="fa fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Audit Timeline Table -->
            <div class="aud-table-wrap">
                <div class="aud-table-header">
                    <h4><i class="fa fa-list text-primary"></i> Audit History
                        <small class="text-muted" style="font-weight:400;font-size:12px;margin-left:8px;">
                            <?php echo count($audit); ?> events
                        </small>
                    </h4>
                </div>
                <?php if (count($audit) > 0): ?>
                <div style="overflow-x:auto;">
                    <table class="aud-table">
                        <thead><tr>
                            <th>Date / Time</th>
                            <th>Action</th>
                            <th>Status Change</th>
                            <th>By</th>
                            <th>Notes</th>
                            <th>IP</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($audit as $a):
                            $action    = isset($a->action)    ? strtoupper(trim((string)$a->action)) : 'OTHER';
                            $actCls    = in_array($action, $knownActions) ? $action : 'other';
                            $oldSt     = isset($a->old_status) ? (string)$a->old_status : '';
                            $newSt     = isset($a->new_status) ? (string)$a->new_status : '';
                            $oldInfo   = isset($statuses[$oldSt]) ? $statuses[$oldSt] : array('label' => ($oldSt ?: 'N/A'));
                            $newInfo   = isset($statuses[$newSt]) ? $statuses[$newSt] : array('label' => $newSt);
                            $actionAt  = isset($a->action_at) ? (string)$a->action_at : '';
                        ?>
                        <tr>
                            <td>
                                <span style="font-size:13px;"><?php echo $actionAt ? date('d M Y', strtotime($actionAt)) : '—'; ?></span>
                                <br><small class="text-muted"><?php echo $actionAt ? date('H:i:s', strtotime($actionAt)) : ''; ?></small>
                            </td>
                            <td>
                                <span class="act-badge <?php echo $actCls; ?>"><?php echo htmlspecialchars($action); ?></span>
                            </td>
                            <td>
                                <?php if ($oldSt !== $newSt && ($oldSt !== '' || $newSt !== '')): ?>
                                    <span class="st-arrow">
                                        <span class="aud-badge <?php echo $oldSt ?: 'default'; ?>"><?php echo htmlspecialchars($oldInfo['label']); ?></span>
                                        <i class="fa fa-arrow-right" style="color:#aaa;font-size:11px;"></i>
                                        <span class="aud-badge <?php echo $newSt ?: 'default'; ?>"><strong><?php echo htmlspecialchars($newInfo['label']); ?></strong></span>
                                    </span>
                                <?php else: ?>
                                    <small class="text-muted">No change</small>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo htmlspecialchars((string)($a->action_by ?? '')); ?></small></td>
                            <td>
                                <?php $notes = isset($a->notes) ? trim((string)$a->notes) : ''; ?>
                                <?php if ($notes !== ''): ?>
                                    <small><?php echo htmlspecialchars($notes); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>
                            <td><small class="text-muted"><?php echo htmlspecialchars((string)($a->ip_address ?? '—')); ?></small></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-info-circle text-primary"></i>
                        No audit history available for this prescription.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Navigation -->
            <div class="aud-nav">
                <a href="<?php echo base_url(); ?>app/pharmacy/prescription_status" class="btn btn-default">
                    <i class="fa fa-arrow-left"></i> Back to Status Management
                </a>
                <a href="<?php echo base_url(); ?>app/pharmacy" class="btn btn-default">
                    <i class="fa fa-medkit"></i> Pharmacy Worklist
                </a>
            </div>

        </section>
    </aside>
</div>

<script src="<?php echo base_url(); ?>public/js/jquery.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/bootstrap.min.js"></script>
<script src="<?php echo base_url(); ?>public/js/AdminLTE/app.js"></script>
</body>
</html>
