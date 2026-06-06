<!DOCTYPE html>
<html>
<head>
    <!-- DEPRECATED VIEW: This view is deprecated. Use pharmacy_dashboard.php instead. -->
    <!-- Auto-redirect to new dashboard after 3 seconds -->
    <meta http-equiv="refresh" content="3;url=<?php echo base_url(); ?>app/pharmacy">
    <meta charset="UTF-8">
    <title>Pharmacy Worklist - Hebrew Medical Center</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />
    <link href="<?php echo base_url();?>public/css/hms-enhanced.css?v=<?php echo time(); ?>" rel="stylesheet" type="text/css" />
    <style>
        /* Force white backgrounds on table rows - override AdminLTE completely */
        .table > tbody > tr,
        .table > tbody > tr.patient-card,
        .table-hover > tbody > tr,
        .table-hover > tbody > tr:hover,
        tr.patient-card,
        tbody tr,
        .box-body .table tbody tr { 
            background-color: #ffffff !important; 
            background: #ffffff !important;
        }
        .table > tbody > tr:nth-child(odd),
        .table > tbody > tr.patient-card:nth-child(odd) { 
            background-color: #fafafa !important; 
            background: #fafafa !important;
        }
        .table > tbody > tr:hover,
        .table > tbody > tr.patient-card:hover,
        .table-hover > tbody > tr:hover { 
            background-color: #e8f4fc !important; 
            background: #e8f4fc !important;
        }
        .table > tbody > tr > td,
        tr.patient-card > td { 
            background-color: transparent !important; 
            background: transparent !important;
            color: #212529 !important; 
        }
        
        .patient-card { cursor: pointer; transition: all 0.2s ease; border-left: 4px solid #ddd; background-color: #ffffff !important; background: #ffffff !important; }
        .patient-card:hover { background-color: #e8f4fc !important; background: #e8f4fc !important; border-left-color: #3c8dbc; }
        .patient-card.status-pending { border-left-color: #f39c12; background-color: #ffffff !important; background: #ffffff !important; }
        .patient-card.status-in_progress { border-left-color: #00c0ef; background-color: #ffffff !important; background: #ffffff !important; }
        .patient-card.status-completed { border-left-color: #00a65a; background-color: #ffffff !important; background: #ffffff !important; }
        .summary-row { margin-bottom: 20px; }
        .badge-count { font-size: 14px; padding: 4px 10px; }
        .filter-box { background: #f9f9f9; padding: 15px; border-radius: 4px; margin-bottom: 20px; }
        .status-filter .btn { margin-right: 5px; margin-bottom: 5px; }
        .patient-info { font-size: 13px; }
        .patient-info .label { margin-right: 5px; }
        .rx-summary { font-size: 13px; color: #333 !important; font-weight: 500; }
        
        /* Improved badge/label visibility */
        .label { font-size: 12px; font-weight: 600; padding: 5px 10px; border-radius: 4px; display: inline-block; }
        .label-success { background-color: #28a745 !important; color: #fff !important; }
        .label-warning { background-color: #ffc107 !important; color: #212529 !important; }
        .label-danger { background-color: #dc3545 !important; color: #fff !important; }
        .label-info { background-color: #17a2b8 !important; color: #fff !important; }
        .label-default { background-color: #6c757d !important; color: #fff !important; }
        .label-primary { background-color: #007bff !important; color: #fff !important; }
        
        /* Badge styles */
        .badge { font-size: 12px; font-weight: 600; padding: 5px 10px; border-radius: 4px; }
        .badge.bg-gray { background-color: #6c757d !important; color: #fff !important; }
        .badge.bg-blue { background-color: #007bff !important; color: #fff !important; }
        
        /* Table text visibility */
        .table > tbody > tr > td { vertical-align: middle; color: #212529 !important; font-size: 14px; }
        .table > thead > tr > th { color: #495057 !important; font-weight: 600; font-size: 13px; text-transform: uppercase; background-color: #f8f9fa !important; }
        
        /* Patient name emphasis */
        .table strong { color: #1a1a1a !important; font-weight: 600; }
        .table small.text-muted { color: #6c757d !important; font-size: 12px; }
        
        /* Prescription summary text */
        .rx-summary .text-success { color: #28a745 !important; font-weight: 500; }
        .rx-summary .text-warning { color: #d39e00 !important; font-weight: 500; }
        .rx-summary .text-muted { color: #6c757d !important; }
        .rx-summary span { color: #333 !important; }
        .rx-summary i { color: #666 !important; }
        
        /* Box styling */
        .box { border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); background-color: #ffffff !important; }
        .box-header { border-bottom: 1px solid #e9ecef; background-color: #ffffff !important; }
        .box-title { font-weight: 600; color: #212529 !important; }
        .box-body { background-color: #ffffff !important; }
        
        /* Small box improvements */
        .small-box h3 { font-size: 28px; font-weight: 700; }
        .small-box p { font-size: 14px; font-weight: 500; }
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
                <h1><i class="fa fa-medkit"></i> Pharmacy Worklist</h1>
                <ol class="breadcrumb">
                    <li><a href="<?php echo base_url()?>app/dashboard"><i class="fa fa-dashboard"></i> Home</a></li>
                    <li class="active">Pharmacy Worklist</li>
                </ol>
            </section>

            <section class="content">
                <!-- DEPRECATION NOTICE -->
                <div class="alert alert-warning" style="margin-bottom: 20px;">
                    <i class="fa fa-exclamation-triangle"></i> <strong>Deprecated View:</strong> 
                    This worklist view is deprecated and will be removed in a future update. 
                    You are being redirected to the new <a href="<?php echo base_url(); ?>app/pharmacy">Pharmacy Dashboard</a> in 3 seconds.
                    <a href="<?php echo base_url(); ?>app/pharmacy" class="btn btn-sm btn-primary pull-right">Go Now</a>
                </div>

                <?php echo isset($message) ? $message : ''; ?>

                <!-- Summary Cards -->
                <div class="row summary-row">
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?php echo isset($summary['pending']) ? (int)$summary['pending'] : 0; ?></h3>
                                <p>Pending Rx</p>
                            </div>
                            <div class="icon"><i class="fa fa-clock-o"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?php echo isset($summary['ready_to_dispense']) ? (int)$summary['ready_to_dispense'] : 0; ?></h3>
                                <p>Ready to Dispense</p>
                            </div>
                            <div class="icon"><i class="fa fa-check-circle"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?php echo isset($summary['awaiting_payment']) ? (int)$summary['awaiting_payment'] : 0; ?></h3>
                                <p>Awaiting Payment</p>
                            </div>
                            <div class="icon"><i class="fa fa-money"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="small-box bg-green">
                            <div class="inner">
                                <h3><?php echo isset($summary['dispensed_today']) ? (int)$summary['dispensed_today'] : 0; ?></h3>
                                <p>Dispensed Today</p>
                            </div>
                            <div class="icon"><i class="fa fa-check"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="small-box bg-purple">
                            <div class="inner">
                                <h3><?php echo isset($summary['deferred']) ? (int)$summary['deferred'] : 0; ?></h3>
                                <p>Deferred</p>
                            </div>
                            <div class="icon"><i class="fa fa-calendar"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2 col-sm-4 col-xs-6">
                        <div class="small-box bg-maroon">
                            <div class="inner">
                                <h3><?php echo isset($summary['low_stock']) ? (int)$summary['low_stock'] : 0; ?></h3>
                                <p>Low Stock</p>
                            </div>
                            <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
						<?php if ($canFullPharmacy): ?>
                            <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_low=1" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
						<?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-filter"></i> Filters</h3>
                        <div class="box-tools pull-right">
                            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                        </div>
                    </div>
                    <div class="box-body">
                        <form method="get" action="<?php echo base_url(); ?>app/pharmacy">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Search Patient</label>
                                        <input type="text" name="search" class="form-control" value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>" placeholder="Patient name, ID, or IOP number...">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status" class="form-control">
                                            <option value="">All</option>
                                            <option value="PENDING" <?php echo (isset($filters['status']) && $filters['status'] === 'PENDING') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="PARTIAL" <?php echo (isset($filters['status']) && $filters['status'] === 'PARTIAL') ? 'selected' : ''; ?>>In Progress</option>
                                            <option value="AWAITING_PAYMENT" <?php echo (isset($filters['status']) && $filters['status'] === 'AWAITING_PAYMENT') ? 'selected' : ''; ?>>Awaiting Payment</option>
                                            <option value="COMPLETED" <?php echo (isset($filters['status']) && $filters['status'] === 'COMPLETED') ? 'selected' : ''; ?>>Completed</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Date From</label>
                                        <input type="date" name="date_from" class="form-control" value="<?php echo isset($filters['date_from']) ? htmlspecialchars($filters['date_from']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Date To</label>
                                        <input type="date" name="date_to" class="form-control" value="<?php echo isset($filters['date_to']) ? htmlspecialchars($filters['date_to']) : ''; ?>">
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group" style="margin-top: 25px;">
                                        <button class="btn btn-primary" type="submit"><i class="fa fa-search"></i> Search</button>
                                        <a class="btn btn-default" href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-refresh"></i></a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Patient List -->
                <div class="box box-primary">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-users"></i> Patients with Prescriptions</h3>
                        <span class="badge bg-blue pull-right"><?php echo count($patient_worklist); ?> patients</span>
                    </div>
                    <div class="box-body">
                        <?php if (!isset($patient_worklist) || count($patient_worklist) === 0): ?>
                            <div class="alert alert-info">
                                <i class="fa fa-info-circle"></i> No patients with pending prescriptions found.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 120px;">Visit ID</th>
                                            <th>Patient</th>
                                            <th style="width: 100px;">Payer</th>
                                            <th style="width: 150px;">Prescriptions</th>
                                            <th style="width: 120px;">Payment</th>
                                            <th style="width: 120px;">Status</th>
                                            <th style="width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($patient_worklist as $pt): ?>
                                            <?php
                                                $statusClass = isset($pt->status_class) ? $pt->status_class : 'default';
                                                $paymentClass = isset($pt->payment_class) ? $pt->payment_class : 'default';
                                            ?>
                                            <tr class="patient-card status-<?php echo strtolower($pt->overall_status); ?>" onclick="window.location='<?php echo base_url(); ?>app/pharmacy/patient/<?php echo url_safe_id($pt->iop_id); ?>';" style="cursor:pointer;">
                                                <td>
                                                    <strong><?php echo htmlspecialchars($pt->iop_id); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($pt->date_visit); ?></small>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($pt->patient_name); ?></strong><br>
                                                    <small class="text-muted"><?php echo htmlspecialchars($pt->patient_no); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($pt->payer_type === 'NHIS'): ?>
                                                        <span class="label label-info"><i class="fa fa-shield"></i> NHIS</span>
                                                    <?php else: ?>
                                                        <span class="label label-default">CASH</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="rx-summary">
                                                    <span class="badge bg-gray"><?php echo (int)$pt->total_items; ?> items</span><br>
                                                    <?php if ((int)$pt->dispensed_count > 0): ?>
                                                        <span class="text-success"><i class="fa fa-check"></i> <?php echo (int)$pt->dispensed_count; ?> dispensed</span><br>
                                                    <?php endif; ?>
                                                    <?php if ((int)$pt->partial_count > 0): ?>
                                                        <span class="text-warning"><i class="fa fa-adjust"></i> <?php echo (int)$pt->partial_count; ?> partial</span><br>
                                                    <?php endif; ?>
                                                    <?php if ((int)$pt->pending_count > 0): ?>
                                                        <span class="text-muted"><i class="fa fa-clock-o"></i> <?php echo (int)$pt->pending_count; ?> pending</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="label label-<?php echo $paymentClass; ?>">
                                                        <?php if ($pt->payment_status === 'CLEARED'): ?>
                                                            <i class="fa fa-check"></i> CLEARED
                                                        <?php elseif ($pt->payment_status === 'PARTIAL'): ?>
                                                            <i class="fa fa-adjust"></i> PARTIAL
                                                        <?php else: ?>
                                                            <i class="fa fa-exclamation-circle"></i> AWAITING
                                                        <?php endif; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="label label-<?php echo $statusClass; ?>">
                                                        <?php echo htmlspecialchars($pt->overall_status); ?>
                                                    </span>
                                                </td>
                                                <td onclick="event.stopPropagation();">
                                                    <a href="<?php echo base_url(); ?>app/pharmacy/patient/<?php echo url_safe_id($pt->iop_id); ?>" class="btn btn-primary btn-sm">
                                                        <i class="fa fa-pills"></i> Dispense
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </section>
        </aside>
    </div>

    <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
    <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
    <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>
</body>
</html>
