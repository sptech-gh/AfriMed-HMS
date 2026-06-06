<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Pharmacy Dashboard - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <style>
        /* ===== MODERN PHARMACY DASHBOARD STYLES ===== */
        :root {
            --primary: #3c8dbc;
            --success: #00a65a;
            --warning: #f39c12;
            --danger: #dd4b39;
            --info: #00c0ef;
            --purple: #605ca8;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --radius: 8px;
        }

        /* Quick Search Bar */
        .quick-search-bar {
            background: linear-gradient(135deg, #3c8dbc 0%, #2c6d9c 100%);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }
        .quick-search-bar .form-control {
            height: 50px;
            font-size: 18px;
            border: none;
            border-radius: var(--radius);
            padding-left: 50px;
        }
        .quick-search-bar .search-icon {
            position: absolute;
            left: 30px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 20px;
            color: #999;
        }
        .quick-search-bar .search-wrapper {
            position: relative;
        }

        /* Summary Cards - Modern Style */
        .stat-card {
            background: #fff;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            border-left: 4px solid #ddd;
            margin-bottom: 15px;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .stat-card.pending { border-left-color: var(--warning); }
        .stat-card.ready { border-left-color: var(--success); }
        .stat-card.partial { border-left-color: var(--info); }
        .stat-card.external { border-left-color: var(--purple); }
        .stat-card.awaiting { border-left-color: var(--danger); }
        .stat-card.completed { border-left-color: var(--success); }
        .stat-card .stat-number {
            font-size: 36px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 5px;
        }
        .stat-card .stat-label {
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .stat-card .stat-icon {
            font-size: 40px;
            opacity: 0.3;
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
        }

        /* Tab Navigation - Modern Pills */
        .nav-tabs-modern {
            background: #fff;
            border-radius: var(--radius);
            padding: 10px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .nav-tabs-modern .tab-btn {
            flex: 1;
            min-width: 120px;
            padding: 15px 20px;
            border: none;
            background: #f5f5f5;
            border-radius: var(--radius);
            font-weight: 600;
            color: #666;
            transition: all 0.3s ease;
            text-align: center;
            cursor: pointer;
        }
        .nav-tabs-modern .tab-btn:hover {
            background: #e8e8e8;
        }
        .nav-tabs-modern .tab-btn.active {
            background: var(--primary);
            color: #fff;
        }
        .nav-tabs-modern .tab-btn .badge {
            margin-left: 8px;
            font-size: 12px;
            padding: 4px 8px;
            border-radius: 20px;
        }
        .nav-tabs-modern .tab-btn.active .badge {
            background: rgba(255,255,255,0.3);
        }

        /* Patient Cards */
        .patient-card-modern {
            background: #fff;
            border-radius: var(--radius);
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 1px 5px rgba(0,0,0,0.08);
            transition: all 0.2s ease;
            border-left: 4px solid #ddd;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .patient-card-modern:hover {
            box-shadow: var(--shadow);
            border-left-color: var(--primary);
        }
        .patient-card-modern.selected {
            background: #e3f2fd;
            border-left-color: var(--primary);
        }
        .patient-card-modern .patient-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3c8dbc 0%, #2c6d9c 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 20px;
            font-weight: 600;
            flex-shrink: 0;
        }
        .patient-card-modern .patient-info {
            flex: 1;
            min-width: 0;
        }
        .patient-card-modern .patient-name {
            font-weight: 600;
            font-size: 16px;
            color: #333;
            margin-bottom: 3px;
        }
        .patient-card-modern .patient-meta {
            font-size: 13px;
            color: #888;
        }
        .patient-card-modern .rx-badges {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        .patient-card-modern .rx-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .patient-card-modern .actions {
            display: flex;
            gap: 8px;
            flex-shrink: 0;
        }

        /* Quick Action Buttons */
        .quick-action-btn {
            padding: 10px 20px;
            border-radius: var(--radius);
            font-weight: 600;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .quick-action-btn:hover {
            transform: scale(1.02);
        }
        .quick-action-btn .kbd {
            background: rgba(0,0,0,0.1);
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            margin-left: 5px;
        }

        /* Keyboard Shortcuts Help */
        .shortcuts-help {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #333;
            color: #fff;
            padding: 15px 20px;
            border-radius: var(--radius);
            font-size: 13px;
            z-index: 1000;
            box-shadow: var(--shadow);
        }
        .shortcuts-help .shortcut {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        .shortcuts-help .shortcut:last-child {
            margin-bottom: 0;
        }
        .shortcuts-help kbd {
            background: #555;
            padding: 2px 8px;
            border-radius: 4px;
            margin-left: 15px;
        }

        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .stat-card {
                padding: 15px;
            }
            .stat-card .stat-number {
                font-size: 28px;
            }
            .stat-card .stat-icon {
                font-size: 30px;
            }
            .nav-tabs-modern .tab-btn {
                padding: 12px 15px;
                font-size: 13px;
            }
            .patient-card-modern {
                flex-wrap: wrap;
            }
            .patient-card-modern .actions {
                width: 100%;
                margin-top: 10px;
                justify-content: flex-end;
            }
            .quick-search-bar .form-control {
                height: 45px;
                font-size: 16px;
            }
            .shortcuts-help {
                display: none;
            }
            /* Touch-friendly buttons */
            .btn {
                min-height: 44px;
                min-width: 44px;
            }
            .quick-action-btn {
                padding: 12px 16px;
            }
        }

        /* Bulk Actions Bar */
        .bulk-actions-bar {
            background: var(--primary);
            color: #fff;
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 15px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        .bulk-actions-bar.show {
            display: flex;
        }
        .bulk-actions-bar .selected-count {
            font-weight: 600;
        }
        .bulk-actions-bar .bulk-btns {
            display: flex;
            gap: 10px;
        }

        /* ===== PHASE 6A — RX QUEUE STYLES ===== */
        .rx-queue-bar {
            background: #fff;
            border-radius: var(--radius);
            padding: 14px 18px;
            box-shadow: var(--shadow);
            margin-bottom: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .rx-queue-bar .rq-filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            border: 2px solid #ddd;
            background: #f5f5f5;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            color: #555;
        }
        .rx-queue-bar .rq-filter-btn:hover { background: #e0e0e0; }
        .rx-queue-bar .rq-filter-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .rx-queue-bar .rq-filter-btn.nhis.active   { background: var(--purple); border-color: var(--purple); }
        .rx-queue-bar .rq-filter-btn.urgent.active  { background: var(--danger); border-color: var(--danger); }
        .rx-queue-bar .rq-filter-btn.dispensed.active { background: var(--success); border-color: var(--success); }
        .rx-queue-bar .rq-search { flex: 1; min-width: 160px; }
        .rx-queue-bar .rq-date   { width: 136px; }
        #rxQueueTable { width: 100%; border-collapse: collapse; font-size: 13px; }
        #rxQueueTable thead th {
            background: #f5f7fa;
            padding: 10px 12px;
            font-weight: 700;
            text-align: left;
            border-bottom: 2px solid #ddd;
            white-space: nowrap;
            cursor: pointer;
            user-select: none;
        }
        #rxQueueTable thead th:hover { background: #eaedf1; }
        #rxQueueTable thead th .sort-icon { margin-left: 4px; opacity: 0.4; font-size: 11px; }
        #rxQueueTable thead th.sorted-asc .sort-icon::after { content: '▲'; opacity: 1; }
        #rxQueueTable thead th.sorted-desc .sort-icon::after { content: '▼'; opacity: 1; }
        #rxQueueTable thead th:not(.sorted-asc):not(.sorted-desc) .sort-icon::after { content: '⇅'; }
        #rxQueueTable tbody tr { border-bottom: 1px solid #f0f0f0; transition: background 0.15s; }
        #rxQueueTable tbody tr:hover { background: #f0f7ff; }
        #rxQueueTable tbody tr.urgent-row { border-left: 4px solid var(--danger);  background: #fff5f5; }
        #rxQueueTable tbody tr.nhis-row   { border-left: 4px solid var(--purple); background: #f5f3ff; }
        #rxQueueTable tbody tr.stat-row   { border-left: 4px solid var(--warning); background: #fffbf0; }
        #rxQueueTable td { padding: 10px 12px; vertical-align: middle; }
        .rq-badge {
            display: inline-block; padding: 3px 9px; border-radius: 12px;
            font-size: 11px; font-weight: 700; white-space: nowrap;
        }
        .rq-badge.nhis     { background: #e2d5f1; color: #4a3875; }
        .rq-badge.cash     { background: #e9ecef; color: #495057; }
        .rq-badge.urgent   { background: #f8d7da; color: #721c24; }
        .rq-badge.stat     { background: #fff3cd; color: #856404; }
        .rq-badge.prn      { background: #d1ecf1; color: #0c5460; }
        .rq-badge.routine  { background: #f0f0f0; color: #666; }
        .rq-badge.pending  { background: #fff3cd; color: #856404; }
        .rq-badge.dispensed{ background: #d4edda; color: #155724; }
        .rq-badge.partial  { background: #d1ecf1; color: #0c5460; }
        .rq-badge.stock-out   { background: #f8d7da; color: #721c24; }
        .rq-badge.stock-low   { background: #fff3cd; color: #856404; }
        .rq-badge.stock-reorder { background: #d1ecf1; color: #0c5460; }
        .rq-badge.stock-ok    { background: #d4edda; color: #155724; }
        .rx-queue-footer {
            background: #fff; border-radius: var(--radius); padding: 12px 18px;
            box-shadow: var(--shadow); margin-top: 10px;
            display: flex; justify-content: space-between; align-items: center;
            font-size: 13px; color: #666;
        }
        .rx-queue-footer .pagination-btns button {
            padding: 5px 12px; margin-left: 5px; border-radius: 6px;
            border: 1px solid #ddd; background: #f5f5f5; cursor: pointer;
            font-size: 13px;
        }
        .rx-queue-footer .pagination-btns button:disabled { opacity: 0.4; cursor: not-allowed; }
        .rx-queue-footer .pagination-btns button.active { background: var(--primary); color: #fff; border-color: var(--primary); }
        #rxQueueTableWrap { overflow-x: auto; }
        .rq-empty { text-align: center; padding: 40px; color: #aaa; }
        .rq-loading { text-align: center; padding: 30px; color: #3c8dbc; }

        /* View toggle */
        .view-toggle-bar {
            background: #fff; border-radius: var(--radius);
            padding: 10px 16px; box-shadow: var(--shadow);
            margin-bottom: 16px; display: flex; gap: 10px; align-items: center;
        }
        .view-toggle-bar .vtb-btn {
            padding: 8px 20px; border-radius: 8px; border: 2px solid #ddd;
            background: #f5f5f5; font-weight: 600; font-size: 13px;
            cursor: pointer; transition: all 0.2s; color: #555;
        }
        .view-toggle-bar .vtb-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; }
        .view-panel { display: none; }
        .view-panel.active { display: block; }

        /* Status Labels */
        .status-pill {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .status-pill.pending { background: #fff3cd; color: #856404; }
        .status-pill.ready { background: #d4edda; color: #155724; }
        .status-pill.partial { background: #d1ecf1; color: #0c5460; }
        .status-pill.dispensed { background: #d4edda; color: #155724; }
        .status-pill.external { background: #e2d5f1; color: #4a3875; }
        .status-pill.awaiting { background: #f8d7da; color: #721c24; }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #888;
        }
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        .empty-state h4 {
            margin-bottom: 10px;
        }

        /* Loading Overlay */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .loading-overlay.show {
            display: flex;
        }
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
        }
        .toast-notification {
            background: #333;
            color: #fff;
            padding: 15px 20px;
            border-radius: var(--radius);
            margin-bottom: 10px;
            box-shadow: var(--shadow);
            animation: slideIn 0.3s ease;
        }
        .toast-notification.success { background: var(--success); }
        .toast-notification.error { background: var(--danger); }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
    </style>
</head>
<body class="skin-blue">
    <?php require_once(APPPATH.'views/include/header.php');?>
	<?php
		$canFullPharmacy = (function_exists('has_privilege') && has_privilege('pharmacy_access'))
			|| (function_exists('has_role') && (has_role('admin') || has_role('pharmacist')));
	?>

    <div class="wrapper row-offcanvas row-offcanvas-left">
        <?php require_once(APPPATH.'views/include/sidebar.php');?>

        <aside class="right-side">
            <section class="content-header">
                <h1><i class="fa fa-medkit"></i> Pharmacy Dashboard</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Pharmacy</li>
                </ol>
            </section>

            <section class="content">
                <?php echo isset($message) ? $message : ''; ?>

                <!-- ===== PHASE 6A/6C: View Toggle ===== -->
                <div class="view-toggle-bar">
                    <button class="vtb-btn active" onclick="switchView('patients')" id="btnViewPatients">
                        <i class="fa fa-users"></i> Patient Worklist
                    </button>
                    <button class="vtb-btn" onclick="switchView('rxqueue')" id="btnViewRxQueue">
                        <i class="fa fa-list-alt"></i> RX Queue
                        <span class="rq-badge pending" id="rxQueueBadge" style="margin-left:6px;">—</span>
                    </button>
					<?php if ($canFullPharmacy): ?>
                    <a class="vtb-btn" href="<?php echo base_url(); ?>app/pharmacy/stock" id="btnViewStock" style="text-decoration:none;">
                        <i class="fa fa-cubes"></i> Stock
                        <span class="rq-badge" id="stockAlertBadge" style="margin-left:6px;display:none;background:#dd4b39;">!</span>
                    </a>
					<?php endif; ?>
                </div>

                <!-- ===== Patient Worklist View ===== -->
                <div class="view-panel active" id="viewPatients">

                <!-- Quick Search Bar -->
                <div class="quick-search-bar">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="search-wrapper">
                                <i class="fa fa-search search-icon"></i>
                                <input type="text" id="quickSearch" class="form-control" placeholder="Quick search: Patient name, ID, or drug... (Press / to focus)" autofocus>
                            </div>
                        </div>
                        <div class="col-md-4 text-right">
							<?php if ($canFullPharmacy): ?>
                            <button class="btn btn-success quick-action-btn" id="btnBulkDispense" disabled>
                                <i class="fa fa-check-double"></i> Bulk Dispense <span class="kbd">B</span>
                            </button>
							<?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Summary Stats (Item Counts - individual prescriptions) -->
                <?php 
                $pc = isset($patient_counts) ? $patient_counts : array();
                $itemPending = isset($summary['pending']) ? (int)$summary['pending'] : 0;
                $itemReady = isset($summary['ready_to_dispense']) ? (int)$summary['ready_to_dispense'] : 0;
                $itemPartial = isset($summary['partial']) ? (int)$summary['partial'] : 0;
                $itemExternal = isset($summary['external']) ? (int)$summary['external'] : 0;
                $itemAwaiting = isset($summary['awaiting_payment']) ? (int)$summary['awaiting_payment'] : 0;
                $itemToday = isset($summary['dispensed_today']) ? (int)$summary['dispensed_today'] : 0;
                
                $patPending = isset($pc['pending']) ? (int)$pc['pending'] : 0;
                $patReady = isset($pc['ready']) ? (int)$pc['ready'] : 0;
                $patInProgress = isset($pc['in_progress']) ? (int)$pc['in_progress'] : 0;
                $patExternal = isset($pc['external']) ? (int)$pc['external'] : 0;
                $patAwaiting = isset($pc['awaiting_payment']) ? (int)$pc['awaiting_payment'] : 0;
                $patTotal = isset($pc['total']) ? (int)$pc['total'] : count($patient_worklist);
                ?>
                <div class="row">
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="stat-card pending" onclick="filterByStatus('PENDING')">
                            <div class="stat-number text-warning"><?php echo $itemPending; ?></div>
                            <div class="stat-label">PENDING</div>
                            <i class="fa fa-clock-o stat-icon text-warning"></i>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="stat-card ready" onclick="filterByStatus('READY')">
                            <div class="stat-number text-success"><?php echo $itemReady; ?></div>
                            <div class="stat-label">READY</div>
                            <i class="fa fa-check-circle stat-icon text-success"></i>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="stat-card partial" onclick="filterByStatus('PARTIAL')">
                            <div class="stat-number text-info"><?php echo $itemPartial; ?></div>
                            <div class="stat-label">PARTIAL</div>
                            <i class="fa fa-adjust stat-icon text-info"></i>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="stat-card external" onclick="filterByStatus('EXTERNAL')">
                            <div class="stat-number text-purple"><?php echo $itemExternal; ?></div>
                            <div class="stat-label">EXTERNAL</div>
                            <i class="fa fa-external-link stat-icon text-purple"></i>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="stat-card awaiting" onclick="filterByStatus('AWAITING')">
                            <div class="stat-number text-danger"><?php echo $itemAwaiting; ?></div>
                            <div class="stat-label">AWAITING PAY</div>
                            <i class="fa fa-money stat-icon text-danger"></i>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-sm-6">
                        <div class="stat-card completed" onclick="filterByStatus('COMPLETED')">
                            <div class="stat-number text-success"><?php echo $itemToday; ?></div>
                            <div class="stat-label">TODAY</div>
                            <i class="fa fa-calendar-check-o stat-icon text-success"></i>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation (Patient Counts - matches worklist) -->
                <div class="nav-tabs-modern">
                    <button class="tab-btn active" data-tab="all" onclick="switchTab('all')">
                        <i class="fa fa-list"></i> All
                        <span class="badge"><?php echo $patTotal; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="pending" onclick="switchTab('pending')">
                        <i class="fa fa-clock-o"></i> Pending
                        <span class="badge bg-yellow"><?php echo $patAwaiting; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="ready" onclick="switchTab('ready')">
                        <i class="fa fa-check"></i> Ready
                        <span class="badge bg-green"><?php echo $patReady; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="partial" onclick="switchTab('partial')">
                        <i class="fa fa-adjust"></i> Partial
                        <span class="badge bg-aqua"><?php echo $patInProgress; ?></span>
                    </button>
                    <button class="tab-btn" data-tab="external" onclick="switchTab('external')">
                        <i class="fa fa-external-link"></i> External
                        <span class="badge bg-purple"><?php echo $patExternal; ?></span>
                    </button>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar" id="bulkActionsBar">
                    <div>
                        <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                        <span class="selected-count"><span id="selectedCount">0</span> patients selected</span>
                    </div>
                    <div class="bulk-btns">
                        <button class="btn btn-success btn-sm" onclick="bulkDispense()">
                            <i class="fa fa-check-double"></i> Dispense All Eligible
                        </button>
                        <button class="btn btn-default btn-sm" onclick="clearSelection()">
                            <i class="fa fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                <!-- Patient List -->
                <div id="patientList" class="view-panel-inner">
                    <?php if (!isset($patient_worklist) || count($patient_worklist) === 0): ?>
                        <div class="empty-state">
                            <i class="fa fa-inbox"></i>
                            <h4>No Prescriptions</h4>
                            <p>There are no pending prescriptions at this time.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($patient_worklist as $index => $pt): ?>
                            <?php
                                $statusLower = strtolower($pt->overall_status);
                                $initials = '';
                                $nameParts = explode(' ', $pt->patient_name);
                                foreach ($nameParts as $part) {
                                    if (!empty($part)) $initials .= strtoupper($part[0]);
                                    if (strlen($initials) >= 2) break;
                                }
                            ?>
                            <div class="patient-card-modern" 
                                 data-iop="<?php echo htmlspecialchars($pt->iop_id); ?>"
                                 data-status="<?php echo htmlspecialchars($statusLower); ?>"
                                 data-patient="<?php echo htmlspecialchars(strtolower($pt->patient_name . ' ' . $pt->patient_no . ' ' . $pt->iop_id)); ?>"
                                 data-index="<?php echo $index; ?>">
                                
                                <input type="checkbox" class="patient-checkbox" data-iop="<?php echo htmlspecialchars($pt->iop_id); ?>" onclick="event.stopPropagation(); updateSelection();">
                                
                                <div class="patient-avatar"><?php echo $initials; ?></div>
                                
                                <div class="patient-info">
                                    <div class="patient-name"><?php echo htmlspecialchars($pt->patient_name); ?></div>
                                    <div class="patient-meta">
                                        <span><i class="fa fa-id-card"></i> <?php echo htmlspecialchars($pt->patient_no); ?></span>
                                        &nbsp;|&nbsp;
                                        <span><i class="fa fa-ticket"></i> <?php echo htmlspecialchars($pt->iop_id); ?></span>
                                        &nbsp;|&nbsp;
                                        <span><i class="fa fa-calendar"></i> <?php echo htmlspecialchars($pt->date_visit); ?></span>
                                    </div>
                                </div>

                                <div class="rx-badges">
                                    <?php if ($pt->payer_type === 'NHIS'): ?>
                                        <span class="rx-badge" style="background:#d1ecf1;color:#0c5460;"><i class="fa fa-shield"></i> NHIS</span>
                                    <?php else: ?>
                                        <span class="rx-badge" style="background:#e9ecef;color:#495057;">CASH</span>
                                    <?php endif; ?>
                                    
                                    <span class="rx-badge" style="background:#f8f9fa;color:#212529;">
                                        <?php echo (int)$pt->total_items; ?> items
                                    </span>
                                    
                                    <?php if ((int)$pt->dispensed_count > 0): ?>
                                        <span class="rx-badge" style="background:#d4edda;color:#155724;">
                                            <i class="fa fa-check"></i> <?php echo (int)$pt->dispensed_count; ?>
                                        </span>
                                    <?php endif; ?>
                                    
                                    <?php if ((int)$pt->pending_count > 0): ?>
                                        <span class="rx-badge" style="background:#fff3cd;color:#856404;">
                                            <i class="fa fa-clock-o"></i> <?php echo (int)$pt->pending_count; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div class="status-pill <?php echo $statusLower; ?>">
                                    <?php echo htmlspecialchars($pt->overall_status); ?>
                                </div>

                                <div class="actions">
                                    <?php
                                        $st = isset($pt->overall_status) ? strtoupper(trim((string)$pt->overall_status)) : '';
                                        $actionUrl = base_url() . 'app/pharmacy/patient/' . url_safe_id($pt->iop_id);
                                        $isCleared = (isset($pt->medication_cleared) && $pt->medication_cleared);
                                        $canDispense = (!$isCleared && in_array($st, array('READY','IN_PROGRESS','PARTIAL_PAID'), true));
                                        $btnClass = $canDispense ? 'btn btn-primary btn-sm quick-action-btn' : 'btn btn-default btn-sm quick-action-btn';
                                        $btnLabel = $canDispense ? 'Dispense' : 'View';
                                        $btnIcon = $canDispense ? 'fa-pills' : 'fa-eye';
                                        $btnTitle = $canDispense ? 'Dispense (D)' : 'View details';
                                        $disableClick = ($isCleared && $st === 'COMPLETED');
                                        if ($disableClick) {
                                            $btnLabel = 'Cleared';
                                            $btnIcon = 'fa-check-circle';
                                            $btnTitle = 'Medication clearance recorded';
                                        }
                                    ?>
                                    <a href="<?php echo htmlspecialchars($actionUrl); ?>"
                                       class="<?php echo $btnClass; ?>"
                                       onclick="event.stopPropagation();<?php echo $disableClick ? 'return false;' : ''; ?>"
                                       title="<?php echo htmlspecialchars($btnTitle); ?>">
                                        <i class="fa <?php echo $btnIcon; ?>"></i> <?php echo htmlspecialchars($btnLabel); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                </div><!-- /viewPatients -->

                <!-- ===== Phase 6A: RX Queue View ===== -->
                <div class="view-panel" id="viewRxQueue">

                    <!-- Filter & Search Bar -->
                    <div class="rx-queue-bar">
                        <button class="rq-filter-btn active" data-rqfilter="ALL"      onclick="rxqSetFilter('ALL')">All</button>
                        <button class="rq-filter-btn"        data-rqfilter="PENDING"  onclick="rxqSetFilter('PENDING')"><i class="fa fa-clock-o"></i> Pending</button>
                        <button class="rq-filter-btn nhis"   data-rqfilter="NHIS"     onclick="rxqSetFilter('NHIS')"><i class="fa fa-shield"></i> NHIS</button>
                        <button class="rq-filter-btn urgent" data-rqfilter="URGENT"   onclick="rxqSetFilter('URGENT')"><i class="fa fa-exclamation-triangle"></i> Urgent</button>
                        <button class="rq-filter-btn"        data-rqfilter="CASH"     onclick="rxqSetFilter('CASH')">Cash</button>
                        <button class="rq-filter-btn dispensed" data-rqfilter="DISPENSED" onclick="rxqSetFilter('DISPENSED')"><i class="fa fa-check"></i> Dispensed</button>
                        <button class="btn btn-default btn-sm" onclick="rxqSetToday()" title="Reset to today" style="margin-left:4px;"><i class="fa fa-calendar-check-o"></i> Today</button>
                        <input  type="text"  id="rxqSearch"  class="form-control rq-search" placeholder="Search RX no, patient, drug, doctor...">
                        <input  type="date"  id="rxqDateFrom" class="form-control rq-date" value="<?php echo date('Y-m-d'); ?>">
                        <span style="color:#aaa;font-size:12px;">to</span>
                        <input  type="date"  id="rxqDateTo"   class="form-control rq-date" value="<?php echo date('Y-m-d'); ?>">
                        <button class="btn btn-default btn-sm" onclick="rxqLoad()"><i class="fa fa-refresh"></i></button>
                    </div>

                    <!-- Table -->
                    <div style="background:#fff;border-radius:var(--radius);box-shadow:var(--shadow);">
                        <div id="rxQueueTableWrap">
                            <table id="rxQueueTable">
                                <thead>
                                    <tr>
                                        <th onclick="rxqSort('prescription_no')" data-col="prescription_no">RX No <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('patient_name')"    data-col="patient_name">Patient <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('patient_no')"      data-col="patient_no">Hosp No <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('doctor_id')"       data-col="doctor_id">Doctor <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('visit_type')"      data-col="visit_type">Dept <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('prescribed_at')"   data-col="prescribed_at">Time <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('drug_name')"       data-col="drug_name">Drug <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('total_qty')"       data-col="total_qty">Qty <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('payer_type')"      data-col="payer_type">Payment <span class="sort-icon"></span></th>
                                        <th onclick="rxqSort('priority')"        data-col="priority">Priority <span class="sort-icon"></span></th>
                                        <th>Stock</th>
                                        <th onclick="rxqSort('dispensing_status')" data-col="dispensing_status">Status <span class="sort-icon"></span></th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="rxQueueTbody">
                                    <tr><td colspan="13" class="rq-loading"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="rx-queue-footer">
                            <span id="rxqInfo">—</span>
                            <div class="pagination-btns">
                                <button id="rxqPrevBtn" onclick="rxqPrev()" disabled><i class="fa fa-chevron-left"></i> Prev</button>
                                <span id="rxqPageNums"></span>
                                <button id="rxqNextBtn" onclick="rxqNext()"><i class="fa fa-chevron-right"></i> Next</button>
                            </div>
                        </div>
                    </div>

                </div><!-- /viewRxQueue -->

            </section>
        </aside>
    </div>

    <!-- Keyboard Shortcuts Help -->
    <div class="shortcuts-help" id="shortcutsHelp">
        <div style="font-weight:600;margin-bottom:10px;"><i class="fa fa-keyboard-o"></i> Shortcuts</div>
        <div class="shortcut"><span>Focus Search</span><kbd>/</kbd></div>
        <div class="shortcut"><span>Dispense Selected</span><kbd>D</kbd></div>
        <div class="shortcut"><span>Reserve Selected</span><kbd>R</kbd></div>
        <div class="shortcut"><span>Mark Unavailable</span><kbd>U</kbd></div>
        <div class="shortcut"><span>Bulk Dispense</span><kbd>B</kbd></div>
        <div class="shortcut"><span>Toggle Shortcuts</span><kbd>?</kbd></div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
    
    <script>
    var csrfName = '<?php echo $this->security->get_csrf_token_name(); ?>';
    var csrfHash = '<?php echo $this->security->get_csrf_hash(); ?>';
    (function() {
        'use strict';

        /* =====================================================================
         * PHASE 6A — RX Queue logic
         * ===================================================================== */
        var RXQ = {
            filter    : 'ALL',
            search    : '',
            dateFrom  : '<?php echo date('Y-m-d'); ?>',
            dateTo    : '<?php echo date('Y-m-d'); ?>',
            limit     : 50,
            offset    : 0,
            total     : 0,
            sortCol   : 'prescribed_at',
            sortDir   : 'asc',
            allRows   : [],    /* local copy for client sort */
            baseUrl   : '<?php echo base_url(); ?>app/pharmacy/rx_queue_json',
        };

        window.switchView = function(view) {
            if (view === 'patients') {
                $('#viewPatients').addClass('active');
                $('#viewRxQueue').removeClass('active');
                $('#btnViewPatients').addClass('active');
                $('#btnViewRxQueue').removeClass('active');
            } else {
                $('#viewRxQueue').addClass('active');
                $('#viewPatients').removeClass('active');
                $('#btnViewRxQueue').addClass('active');
                $('#btnViewPatients').removeClass('active');
                if (RXQ.allRows.length === 0) rxqLoad();
            }
        };

        window.rxqSetFilter = function(f) {
            RXQ.filter = f;
            RXQ.offset = 0;
            $('.rq-filter-btn').removeClass('active');
            $('[data-rqfilter="' + f + '"]').addClass('active');
            rxqLoad();
        };

        window.rxqSetToday = function() {
            var today = new Date().toISOString().substring(0, 10);
            $('#rxqDateFrom').val(today);
            $('#rxqDateTo').val(today);
            RXQ.offset = 0;
            rxqLoad();
        };

        window.rxqLoad = function() {
            RXQ.search   = $('#rxqSearch').val();
            RXQ.dateFrom = $('#rxqDateFrom').val();
            RXQ.dateTo   = $('#rxqDateTo').val();

            $('#rxQueueTbody').html('<tr><td colspan="13" class="rq-loading"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');

            $.getJSON(RXQ.baseUrl, {
                filter   : RXQ.filter,
                search   : RXQ.search,
                date_from: RXQ.dateFrom,
                date_to  : RXQ.dateTo,
                limit    : RXQ.limit,
                offset   : RXQ.offset
            }, function(resp) {
                if (!resp.success) {
                    $('#rxQueueTbody').html('<tr><td colspan="13" class="rq-empty">Error loading queue.</td></tr>');
                    return;
                }
                RXQ.total   = resp.total;
                RXQ.allRows = resp.rows;
                rxqRender(RXQ.allRows);
                rxqUpdatePager();
                $('#rxQueueBadge').text(resp.total);
            }).fail(function() {
                $('#rxQueueTbody').html('<tr><td colspan="13" class="rq-empty">Failed to load. Retry.</td></tr>');
            });
        };

        function rxqRender(rows) {
            if (!rows || rows.length === 0) {
                $('#rxQueueTbody').html('<tr><td colspan="13" class="rq-empty"><i class="fa fa-inbox"></i><br>No prescriptions match this filter.</td></tr>');
                return;
            }
            var html = '';
            $.each(rows, function(i, r) {
                var rowClass = '';
                if (r.is_urgent)                      rowClass = 'urgent-row';
                else if (r.payer_type === 'NHIS')     rowClass = 'nhis-row';
                else if (r.freq_code === 'STAT')      rowClass = 'stat-row';

                var rxBadge = r.prescription_no
                    ? '<span class="rq-badge pending" style="font-size:12px;background:#e3f0ff;color:#1a5276;">'
                      + rxEsc(r.prescription_no) + '</span>'
                    : '<span style="color:#bbb;font-size:11px;">—</span>';

                var payBadge = r.payer_type === 'NHIS'
                    ? '<span class="rq-badge nhis"><i class="fa fa-shield"></i> NHIS</span>'
                    : '<span class="rq-badge cash">Cash</span>';

                var priClass = r.priority.toLowerCase();
                var priBadge = '<span class="rq-badge ' + priClass + '">' + rxEsc(r.priority) + '</span>';

                var stockBadge;
                if (r.stock_alert === 'OUT')     stockBadge = '<span class="rq-badge stock-out">&#10060; Out</span>';
                else if (r.stock_alert === 'LOW')stockBadge = '<span class="rq-badge stock-low">&#9888; Low (' + r.stock_qty + ')</span>';
                else if (r.stock_alert === 'REORDER') stockBadge = '<span class="rq-badge stock-reorder">Reorder</span>';
                else stockBadge = '<span class="rq-badge stock-ok">' + r.stock_qty + '</span>';

                var dispStatus = r.dispensing_status.toLowerCase();
                var dispBadge  = '<span class="rq-badge ' + dispStatus + '">' + rxEsc(r.dispensing_status) + '</span>';

                var time = r.prescribed_at ? r.prescribed_at.substring(11, 16) : '—';
                var drug = rxEsc(r.drug_name || '—');
                if (r.strength) drug += ' <small class="text-muted">' + rxEsc(r.strength) + '</small>';
                if (r.drug_form) drug += ' <small class="text-muted">' + rxEsc(r.drug_form) + '</small>';

                var actionUrl = '<?php echo base_url(); ?>app/pharmacy/patient/' + encodeURIComponent(r.iop_id);
                var action = '<a href="' + actionUrl + '" class="btn btn-primary btn-xs" title="Open patient dispense page"><i class="fa fa-pills"></i> Dispense</a>';

                html += '<tr class="' + rowClass + '">';
                html += '<td>' + rxBadge + '</td>';
                html += '<td><strong>' + rxEsc(r.patient_name) + '</strong></td>';
                html += '<td>' + rxEsc(r.patient_no) + '</td>';
                html += '<td><small>' + rxEsc((r.doctor_name && r.doctor_name.trim()) ? r.doctor_name : (r.doctor_id || '—')) + '</small></td>';
                html += '<td><small>' + rxEsc((r.department_name && r.department_name.trim()) ? r.department_name : (r.visit_type || 'OPD')) + '</small></td>';
                html += '<td><small>' + time + '</small></td>';
                html += '<td>' + drug + '</td>';
                html += '<td><strong>' + r.total_qty + '</strong>';
                if (r.freq_code) html += ' <small class="text-muted">' + rxEsc(r.freq_code) + ' × ' + r.days + 'd</small>';
                html += '</td>';
                html += '<td>' + payBadge + '</td>';
                html += '<td>' + priBadge + '</td>';
                html += '<td>' + stockBadge + '</td>';
                html += '<td>' + dispBadge + '</td>';
                html += '<td>' + action + '</td>';
                html += '</tr>';
            });
            $('#rxQueueTbody').html(html);
        }

        function rxqUpdatePager() {
            var page    = Math.floor(RXQ.offset / RXQ.limit) + 1;
            var pages   = Math.max(1, Math.ceil(RXQ.total / RXQ.limit));
            var showing = Math.min(RXQ.offset + RXQ.limit, RXQ.total);
            $('#rxqInfo').text('Showing ' + (RXQ.offset + 1) + '–' + showing + ' of ' + RXQ.total + ' prescriptions');
            $('#rxqPrevBtn').prop('disabled', RXQ.offset === 0);
            $('#rxqNextBtn').prop('disabled', showing >= RXQ.total);

            var pnums = '';
            for (var p = 1; p <= Math.min(pages, 7); p++) {
                var active = p === page ? ' active' : '';
                pnums += '<button class="' + active + '" onclick="rxqGoPage(' + p + ')">' + p + '</button>';
            }
            if (pages > 7) pnums += '<button disabled>…</button>';
            $('#rxqPageNums').html(pnums);
        }

        window.rxqPrev = function() {
            if (RXQ.offset > 0) { RXQ.offset = Math.max(0, RXQ.offset - RXQ.limit); rxqLoad(); }
        };
        window.rxqNext = function() {
            if (RXQ.offset + RXQ.limit < RXQ.total) { RXQ.offset += RXQ.limit; rxqLoad(); }
        };
        window.rxqGoPage = function(p) {
            RXQ.offset = (p - 1) * RXQ.limit;
            rxqLoad();
        };

        /* Client-side column sort (operates on cached allRows) */
        window.rxqSort = function(col) {
            var th = $('[data-col="' + col + '"]');
            if (RXQ.sortCol === col) {
                RXQ.sortDir = (RXQ.sortDir === 'asc') ? 'desc' : 'asc';
            } else {
                RXQ.sortCol = col;
                RXQ.sortDir = 'asc';
            }
            $('#rxQueueTable thead th').removeClass('sorted-asc sorted-desc');
            th.addClass(RXQ.sortDir === 'asc' ? 'sorted-asc' : 'sorted-desc');

            var sorted = RXQ.allRows.slice().sort(function(a, b) {
                var va = (a[col] || '').toString().toLowerCase();
                var vb = (b[col] || '').toString().toLowerCase();
                var n  = isNaN(va) ? va.localeCompare(vb) : parseFloat(va) - parseFloat(vb);
                return RXQ.sortDir === 'asc' ? n : -n;
            });
            rxqRender(sorted);
        };

        /* Live search debounce */
        var rxqDebounce;
        $(document).on('input', '#rxqSearch', function() {
            clearTimeout(rxqDebounce);
            rxqDebounce = setTimeout(function() { RXQ.offset = 0; rxqLoad(); }, 350);
        });
        $(document).on('change', '#rxqDateFrom, #rxqDateTo', function() {
            RXQ.offset = 0; rxqLoad();
        });

        function rxEsc(s) {
            return String(s)
                .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        /* =====================================================================
         * EXISTING patient worklist logic (unchanged)
         * ===================================================================== */
        var selectedPatients = [];
        var currentTab = 'all';
        var selectedIndex = -1;
        
        // ===== TAB SWITCHING =====
        window.switchTab = function(tab) {
            currentTab = tab;
            $('.tab-btn').removeClass('active');
            $('.tab-btn[data-tab="' + tab + '"]').addClass('active');
            
            $('.patient-card-modern').each(function() {
                var status = $(this).data('status');
                if (tab === 'all') {
                    $(this).show();
                } else if (tab === 'pending' && (status === 'pending' || status === 'awaiting')) {
                    $(this).show();
                } else if (tab === 'ready' && status === 'ready') {
                    $(this).show();
                } else if (tab === 'partial' && (status === 'partial' || status === 'in_progress')) {
                    $(this).show();
                } else if (tab === 'external' && status === 'external') {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        };
        
        // ===== QUICK SEARCH =====
        $('#quickSearch').on('input', function() {
            var query = $(this).val().toLowerCase();
            $('.patient-card-modern').each(function() {
                var text = $(this).data('patient');
                if (text.indexOf(query) > -1) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // ===== FILTER BY STATUS =====
        window.filterByStatus = function(status) {
            window.location.href = '<?php echo base_url(); ?>app/pharmacy?status=' + status;
        };
        
        // ===== SELECTION MANAGEMENT =====
        window.updateSelection = function() {
            selectedPatients = [];
            $('.patient-checkbox:checked').each(function() {
                selectedPatients.push($(this).data('iop'));
            });
            
            $('#selectedCount').text(selectedPatients.length);
            
            <?php if ($canFullPharmacy): ?>
            if (selectedPatients.length > 0) {
                $('#bulkActionsBar').addClass('show');
                $('#btnBulkDispense').prop('disabled', false);
            } else {
                $('#bulkActionsBar').removeClass('show');
                $('#btnBulkDispense').prop('disabled', true);
            }
            <?php endif; ?>
        };
        
        window.toggleSelectAll = function() {
            var checked = $('#selectAll').is(':checked');
            $('.patient-checkbox:visible').prop('checked', checked);
            updateSelection();
        };
        
        window.clearSelection = function() {
            $('.patient-checkbox').prop('checked', false);
            $('#selectAll').prop('checked', false);
            updateSelection();
        };
        
        <?php if ($canFullPharmacy): ?>
        // ===== BULK DISPENSE =====
        window.bulkDispense = function() {
            if (selectedPatients.length === 0) {
                showToast('No patients selected', 'error');
                return;
            }
            
            if (!confirm('Dispense all eligible medications for ' + selectedPatients.length + ' patient(s)?')) {
                return;
            }
            
            showLoading();
            
            // Process each patient sequentially
            var processed = 0;
            var errors = 0;
            
            function processNext() {
                if (processed >= selectedPatients.length) {
                    hideLoading();
                    if (errors === 0) {
                        showToast('Successfully dispensed ' + processed + ' patient(s)', 'success');
                    } else {
                        showToast('Completed with ' + errors + ' error(s)', 'error');
                    }
                    setTimeout(function() { location.reload(); }, 1500);
                    return;
                }
                
                var iop = selectedPatients[processed];
                var dispenseData = { iop_id: iop };
                dispenseData[csrfName] = csrfHash;
                $.post('<?php echo base_url(); ?>app/pharmacy/bulk_dispense', dispenseData)
                    .done(function() {
                        processed++;
                        processNext();
                    })
                    .fail(function() {
                        errors++;
                        processed++;
                        processNext();
                    });
            }
            
            processNext();
        };
        <?php endif; ?>
        
        // ===== KEYBOARD NAVIGATION =====
        $(document).on('keydown', function(e) {
            // Ignore if typing in input
            if ($(e.target).is('input, textarea, select')) {
                if (e.key === 'Escape') {
                    $(e.target).blur();
                }
                return;
            }
            
            var cards = $('.patient-card-modern:visible');
            
            switch(e.key) {
                case '/':
                    e.preventDefault();
                    $('#quickSearch').focus();
                    break;
                    
                case 'd':
                case 'D':
                    e.preventDefault();
                    if (selectedIndex >= 0 && selectedIndex < cards.length) {
                        var iop = $(cards[selectedIndex]).data('iop');
                        window.location.href = '<?php echo base_url(); ?>app/pharmacy/patient/' + iop;
                    } else if (selectedPatients.length === 1) {
                        window.location.href = '<?php echo base_url(); ?>app/pharmacy/patient/' + selectedPatients[0];
                    }
                    break;
                    
                case 'r':
                case 'R':
                    e.preventDefault();
                    showToast('Reserve: Select patient first', 'info');
                    break;
                    
                case 'u':
                case 'U':
                    e.preventDefault();
                    showToast('Unavailable: Select patient first', 'info');
                    break;
                    
                case 'b':
                case 'B':
                    e.preventDefault();
                    if (typeof bulkDispense === 'function') {
                        bulkDispense();
                    }
                    break;
                    
                case '?':
                    e.preventDefault();
                    $('#shortcutsHelp').toggle();
                    break;
                    
                case 'ArrowDown':
                    e.preventDefault();
                    if (selectedIndex < cards.length - 1) {
                        selectedIndex++;
                        highlightCard(cards, selectedIndex);
                    }
                    break;
                    
                case 'ArrowUp':
                    e.preventDefault();
                    if (selectedIndex > 0) {
                        selectedIndex--;
                        highlightCard(cards, selectedIndex);
                    }
                    break;
                    
                case 'Enter':
                    e.preventDefault();
                    if (selectedIndex >= 0 && selectedIndex < cards.length) {
                        var iop = $(cards[selectedIndex]).data('iop');
                        window.location.href = '<?php echo base_url(); ?>app/pharmacy/patient/' + iop;
                    }
                    break;
                    
                case 'Escape':
                    selectedIndex = -1;
                    $('.patient-card-modern').removeClass('selected');
                    break;
            }
        });
        
        function highlightCard(cards, index) {
            $('.patient-card-modern').removeClass('selected');
            $(cards[index]).addClass('selected');
            cards[index].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // ===== CLICK TO SELECT =====
        $(document).on('click', '.patient-card-modern', function(e) {
            if ($(e.target).is('input, button, a') || $(e.target).closest('button, a').length) {
                return;
            }
            var iop = $(this).data('iop');
            window.location.href = '<?php echo base_url(); ?>app/pharmacy/patient/' + iop;
        });
        
        // ===== UTILITIES =====
        function showLoading() {
            $('#loadingOverlay').addClass('show');
        }
        
        function hideLoading() {
            $('#loadingOverlay').removeClass('show');
        }
        
        window.showToast = function(message, type) {
            type = type || 'info';
            var toast = $('<div class="toast-notification ' + type + '">' + message + '</div>');
            $('#toastContainer').append(toast);
            setTimeout(function() {
                toast.fadeOut(function() { $(this).remove(); });
            }, 3000);
        };
        
        // ===== INIT =====
        $(function() {
            // Auto-hide shortcuts help after 5 seconds
            setTimeout(function() {
                $('#shortcutsHelp').fadeOut();
            }, 5000);
            
            // Check URL params for status filter
            var urlParams = new URLSearchParams(window.location.search);
            var statusParam = urlParams.get('status');
            if (statusParam) {
                switchTab(statusParam.toLowerCase());
            }

            <?php if ($canFullPharmacy): ?>
            // ===== PHASE 6C: Stock Alert Badge polling =====
            function pollStockAlerts() {
                $.getJSON('<?php echo base_url(); ?>app/pharmacy/stock_alerts_json', function(resp) {
                    if (!resp.success) return;
                    var a = resp.alerts;
                    var critical = (a.out_of_stock || 0) + (a.expired || 0);
                    var warn     = (a.low_stock   || 0) + (a.expiring_soon || 0);
                    var total    = critical + warn;
                    var $badge   = $('#stockAlertBadge');
                    if (total > 0) {
                        $badge.text(total).show();
                        $badge.css('background', critical > 0 ? '#dd4b39' : '#f39c12');
                    } else {
                        $badge.hide();
                    }
                });
            }
            pollStockAlerts();
            setInterval(pollStockAlerts, 120000); /* refresh every 2 min */
            <?php endif; ?>
        });
        
    })();
    </script>
</body>
</html>
