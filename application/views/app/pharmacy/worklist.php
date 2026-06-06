<!DOCTYPE html>
<html>
    <head>
<head>
        <!-- DEPRECATED VIEW: This view is deprecated. Use pharmacy_dashboard.php instead. -->
        <!-- Auto-redirect to new dashboard after 3 seconds -->
        <meta http-equiv="refresh" content="3;url=<?php echo base_url(); ?>app/pharmacy">
        <meta charset="UTF-8">
        <title>Hebrew Medical Center</title>
        <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

        <link href="<?php echo base_url()?>public/css/bootstrap.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/font-awesome.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/ionicons.min.css" rel="stylesheet" type="text/css" />
        <link href="<?php echo base_url();?>public/css/AdminLTE.css" rel="stylesheet" type="text/css" />

        <!-- jQuery UI CSS -->
        <link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">

        <style>
            .ui-autocomplete { position: absolute; cursor: default;z-index:999999999 !important;}
            .badge-status { font-size: 12px; padding: 6px 10px; }
            .table td { vertical-align: middle !important; }
            .worklist-actions form { display: inline-block; margin: 0 2px; }
        </style>

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.3.0/respond.min.js"></script>
        <![endif]-->

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
                    <h1>Pharmacy Worklist</h1>
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

                    <!-- Summary Boxes -->
                    <div class="row">
                        <div class="col-md-2">
                            <div class="small-box bg-yellow">
                                <div class="inner"><h3><?php echo isset($summary['pending']) ? (int)$summary['pending'] : 0; ?></h3><p>Pending Rx</p></div>
                                <div class="icon"><i class="fa fa-clock-o"></i></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-red">
                                <div class="inner"><h3><?php echo isset($summary['awaiting_payment']) ? (int)$summary['awaiting_payment'] : 0; ?></h3><p>Awaiting Payment</p></div>
                                <div class="icon"><i class="fa fa-money"></i></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-blue">
                                <div class="inner"><h3><?php echo isset($summary['ready_to_dispense']) ? (int)$summary['ready_to_dispense'] : 0; ?></h3><p>Ready to Dispense</p></div>
                                <div class="icon"><i class="fa fa-check"></i></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-green">
                                <div class="inner"><h3><?php echo isset($summary['dispensed_today']) ? (int)$summary['dispensed_today'] : 0; ?></h3><p>Dispensed Today</p></div>
                                <div class="icon"><i class="fa fa-check-circle"></i></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-maroon">
                                <div class="inner"><h3><?php echo isset($summary['unavailable']) ? (int)$summary['unavailable'] : 0; ?></h3><p>Unavailable</p></div>
                                <div class="icon"><i class="fa fa-ban"></i></div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="small-box bg-red">
                                <div class="inner"><h3><?php echo isset($summary['low_stock']) ? (int)$summary['low_stock'] : 0; ?></h3><p>Low Stock</p></div>
                                <div class="icon"><i class="fa fa-exclamation-triangle"></i></div>
								<?php if ($canFullPharmacy): ?>
                                <a href="<?php echo base_url(); ?>app/pharmacy/stock?show_low=1" class="small-box-footer">View <i class="fa fa-arrow-circle-right"></i></a>
							<?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <!-- GHS Flexible Workflow Summary Row -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="small-box bg-purple">
                                <div class="inner"><h3><?php echo isset($summary['deferred']) ? (int)$summary['deferred'] : 0; ?></h3><p>Deferred / Unable to Pay</p></div>
                                <div class="icon"><i class="fa fa-calendar-times-o"></i></div>
                                <a href="<?php echo base_url(); ?>app/billing/pharmacy_bills?status=DEFERRED" class="small-box-footer">View at Cashier <i class="fa fa-arrow-circle-right"></i></a>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="small-box bg-teal">
                                <div class="inner"><h3><?php echo isset($summary['external']) ? (int)$summary['external'] : 0; ?></h3><p>External Purchase</p></div>
                                <div class="icon"><i class="fa fa-external-link"></i></div>
                            </div>
                        </div>
                    </div>

                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title">Filters</h3>
                        </div>
                        <div class="box-body">
                            <form method="get" action="<?php echo base_url(); ?>app/pharmacy">
                                <div class="row">
                                    <div class="col-md-5">
                                        <div class="form-group">
                                            <label>Search</label>
                                            <input type="text" name="search" class="form-control input-sm" value="<?php echo isset($filters['search']) ? htmlspecialchars($filters['search']) : ''; ?>" placeholder="Patient / IOP / Drug">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Date From</label>
                                            <input type="date" name="date_from" class="form-control input-sm" value="<?php echo isset($filters['date_from']) ? htmlspecialchars($filters['date_from']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group">
                                            <label>Date To</label>
                                            <input type="date" name="date_to" class="form-control input-sm" value="<?php echo isset($filters['date_to']) ? htmlspecialchars($filters['date_to']) : ''; ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="form-group">
                                            <label>Limit</label>
                                            <input type="number" name="limit" class="form-control input-sm" value="<?php echo isset($filters['limit']) && (int)$filters['limit'] > 0 ? (int)$filters['limit'] : 200; ?>" min="1" max="500">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-group" style="margin-top: 25px;">
                                            <button class="btn btn-primary btn-sm" type="submit"><i class="fa fa-filter"></i> Apply</button>
                                            <a class="btn btn-default btn-sm" href="<?php echo base_url(); ?>app/pharmacy"><i class="fa fa-refresh"></i> Reset</a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="box box-primary">
                        <div class="box-header">
                            <h3 class="box-title">Prescriptions</h3>
                        </div>
                        <div class="box-body table-responsive">

                            <table class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th>IOP</th>
                                        <th>Patient</th>
                                        <th>Drug</th>
                                        <th>Dosage</th>
                                        <th>Frequency</th>
                                        <th>Days</th>
                                        <th>Qty</th>
                                        <th>Disp</th>
                                        <th>Stock</th>
                                        <th>Payer</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Instruction</th>
                                        <th style="width: 280px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!isset($worklist) || !is_array($worklist) || count($worklist) === 0) { ?>
                                        <tr>
                                            <td colspan="14"><em>No items found.</em></td>
                                        </tr>
                                    <?php } else { ?>
                                        <?php foreach ($worklist as $row) { ?>
                                            <?php
                                                $dispStatus = isset($row->dispensing_status) ? strtoupper(trim((string)$row->dispensing_status)) : '';
                                                $status = ($dispStatus !== '') ? $dispStatus : (isset($row->pharmacy_status) ? (string)$row->pharmacy_status : 'PENDING');
                                                $payStatus = isset($row->payment_status) ? strtoupper(trim((string)$row->payment_status)) : 'PENDING';
                                                $extStatus = isset($row->extended_status) ? strtoupper(trim((string)$row->extended_status)) : '';
                                                $payerType = isset($row->payer_type) ? strtoupper((string)$row->payer_type) : 'CASH';
                                                /* Exception statuses — clinical care may proceed */
                                                $exceptionStatuses = array('EXTERNAL_PURCHASE','EXTERNAL','UNABLE_TO_PAY','DEFERRED','WAIVED','EMERGENCY','ADMITTED','WAIVER_REQUESTED');
                                                $isException = in_array($extStatus, $exceptionStatuses) || in_array($payStatus, $exceptionStatuses);
                                                /* Payment is cleared if: PAID, CANCELLED, exception, DISPENSED, or NHIS patient */
                                                $isPaid = ($payStatus === 'PAID' || $payStatus === 'CANCELLED') || $isException || ($status === 'DISPENSED') || ($payerType === 'NHIS');
                                                $labelClass = 'label label-default';
                                                if ($status === 'DISPENSED' || $status === 'EXTERNAL') {
                                                    $labelClass = 'label label-success';
                                                } elseif ($status === 'PARTIAL') {
                                                    $labelClass = 'label label-warning';
                                                } elseif ($status === 'RESERVED') {
                                                    $labelClass = 'label label-primary';
                                                } elseif ($status === 'UNAVAILABLE') {
                                                    $labelClass = 'label label-danger';
                                                } else {
                                                    $labelClass = 'label label-default';
                                                }
                                                $iop_med_id = isset($row->iop_med_id) ? (int)$row->iop_med_id : 0;
                                                $iop_id = isset($row->iop_id) ? (string)$row->iop_id : '';
                                                $total = isset($row->total_qty) ? (float)$row->total_qty : 0.0;
                                                $dispensed = isset($row->dispensed_qty) ? (float)$row->dispensed_qty : 0.0;
                                                $remaining = $total - $dispensed;
                                                if ($remaining < 0) { $remaining = 0; }
                                                $isUnavailable = ($status === 'UNAVAILABLE');
                                            ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($iop_id); ?></td>
                                                <td><?php echo htmlspecialchars(isset($row->patient_name) ? (string)$row->patient_name : ''); ?><br><small class="text-muted"><?php echo htmlspecialchars(isset($row->patient_no) ? (string)$row->patient_no : ''); ?></small></td>
                                                <td><?php echo htmlspecialchars(isset($row->drug_name) ? (string)$row->drug_name : (string)$row->medicine_text); ?></td>
                                                <td><?php echo htmlspecialchars(isset($row->dosage) ? (string)$row->dosage : ''); ?></td>
                                                <td><?php echo htmlspecialchars(isset($row->frequency) ? (string)$row->frequency : ''); ?></td>
                                                <td><?php echo isset($row->days) && (int)$row->days > 0 ? (int)$row->days : '-'; ?></td>
                                                <td><?php echo htmlspecialchars((string)$total); ?></td>
                                                <td><?php echo htmlspecialchars((string)$dispensed); ?></td>
                                                <td>
                                                    <?php
                                                        $curStock = isset($row->current_stock) ? (float)$row->current_stock : 0;
                                                        $stockLow = isset($row->stock_low) ? (bool)$row->stock_low : false;
                                                        $stockClass = $stockLow ? 'text-danger' : 'text-success';
                                                    ?>
                                                    <span class="<?php echo $stockClass; ?>">
                                                        <?php echo htmlspecialchars((string)$curStock); ?>
                                                        <?php if ($stockLow) { ?><i class="fa fa-exclamation-triangle"></i><?php } ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                        $payerType = isset($row->payer_type) ? strtoupper((string)$row->payer_type) : 'CASH';
                                                        $nhisFlag = isset($row->is_nhis_covered) ? (bool)$row->is_nhis_covered : false;
                                                        if ($payerType === 'NHIS') {
                                                            echo '<span class="label label-info">NHIS</span>';
                                                            if ($nhisFlag) { echo ' <span class="label label-success" title="Drug covered by NHIS"><i class="fa fa-shield"></i></span>'; }
                                                        } else {
                                                            echo '<span class="label label-default">CASH</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    // Determine payment status display
                                                    $isExternalItem = in_array($extStatus, array('EXTERNAL_PURCHASE', 'EXTERNAL')) || $status === 'EXTERNAL';
                                                    $isDispensedItem = ($status === 'DISPENSED');
                                                    $isNhisPatient = ($payerType === 'NHIS');
                                                    $isWaivedItem = in_array($extStatus, array('WAIVED', 'WAIVER_APPROVED')) || $payStatus === 'WAIVED';
                                                    
                                                    if ($isExternalItem): ?>
                                                        <span class="label label-info badge-status"><i class="fa fa-external-link"></i> EXTERNAL</span>
                                                    <?php elseif ($payStatus === 'PAID' || $isDispensedItem): ?>
                                                        <span class="label label-success badge-status"><i class="fa fa-check"></i> CLEARED</span>
                                                    <?php elseif ($isWaivedItem): ?>
                                                        <span class="label label-success badge-status"><i class="fa fa-gift"></i> WAIVED</span>
                                                    <?php elseif ($isNhisPatient): ?>
                                                        <span class="label label-success badge-status"><i class="fa fa-shield"></i> NHIS</span>
                                                    <?php elseif ($payStatus === 'CANCELLED'): ?>
                                                        <span class="label label-default badge-status">CANCELLED</span>
                                                    <?php elseif ($extStatus === 'UNABLE_TO_PAY'): ?>
                                                        <span class="label label-warning badge-status"><i class="fa fa-exclamation-triangle"></i> UNABLE TO PAY</span>
                                                    <?php elseif ($extStatus === 'DEFERRED'): ?>
                                                        <span class="label label-warning badge-status"><i class="fa fa-calendar"></i> DEFERRED</span>
                                                    <?php elseif ($extStatus === 'EMERGENCY'): ?>
                                                        <span class="label label-danger badge-status"><i class="fa fa-ambulance"></i> EMERGENCY</span>
                                                    <?php elseif ($extStatus === 'WAIVER_REQUESTED'): ?>
                                                        <span class="label label-info badge-status"><i class="fa fa-hourglass-half"></i> WAIVER PENDING</span>
                                                    <?php else: ?>
                                                        <span class="label label-danger badge-status"><i class="fa fa-exclamation-circle"></i> PAYMENT REQUIRED</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="<?php echo $labelClass; ?> badge-status"><?php echo htmlspecialchars($status); ?></span></td>
                                                <td><small><?php echo htmlspecialchars(isset($row->instruction) ? (string)$row->instruction : ''); ?><?php if (isset($row->advice) && trim((string)$row->advice) !== '') { echo '<br><em>' . htmlspecialchars((string)$row->advice) . '</em>'; } ?></small></td>
                                                <td class="worklist-actions">

                                                    <?php if (!$isPaid && !$isUnavailable && $status !== 'DISPENSED' && $status !== 'EXTERNAL' && !$isException): ?>
                                                        <div class="alert alert-danger" style="padding:4px 8px;margin-bottom:4px;font-size:11px;">
                                                            <i class="fa fa-lock"></i> <strong>Payment Required</strong> — Direct patient to cashier before dispensing.
                                                        </div>
                                                    <?php elseif ($isException && $extStatus !== 'WAIVED' && $status !== 'EXTERNAL'): ?>
                                                        <div class="alert alert-info" style="padding:4px 8px;margin-bottom:4px;font-size:11px;">
                                                            <i class="fa fa-info-circle"></i> <strong><?php echo htmlspecialchars($extStatus ?: $payStatus); ?></strong> — Dispensing allowed.
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if ($canFullPharmacy): ?>
                                                    <a class="btn btn-default btn-xs" href="<?php echo base_url(); ?>app/pharmacy/medication_clearance/<?php echo url_safe_id($iop_id); ?>/<?php echo url_safe_id(isset($row->patient_no) ? (string)$row->patient_no : ''); ?>" onclick="return confirm('Mark Medication Clearance for this IOP? This will validate that all drugs are billed and fully dispensed.');"><i class="fa fa-flag-checkered"></i> Clearance</a>
                                                    <?php endif; ?>

                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/log_action">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                        <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                                                        <input type="hidden" name="status" value="RESERVED">
                                                        <input type="hidden" name="qty" value="">
                                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(current_url() . '?' . http_build_query($_GET)); ?>">
                                                        <button type="submit" class="btn btn-primary btn-xs" <?php echo ($status === 'DISPENSED' || !$isPaid) ? 'disabled title="Cashier payment required"' : ''; ?>><i class="fa fa-bookmark"></i> Reserve</button>
                                                    </form>

                                                    <?php $canDispense = ($status !== 'DISPENSED' && $status !== 'EXTERNAL' && $curStock > 0 && ($isPaid || $isException)); ?>
                                                    <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#partialModal<?php echo (int)$iop_med_id; ?>" <?php echo !$canDispense ? 'disabled title="Cashier payment required"' : ''; ?>><i class="fa fa-adjust"></i> Partial</button>
                                                    <button type="button" class="btn btn-success btn-xs" data-toggle="modal" data-target="#dispenseModal<?php echo (int)$iop_med_id; ?>" <?php echo !$canDispense ? 'disabled title="Cashier payment required"' : ''; ?>><i class="fa fa-check"></i> Dispense</button>

                                                    <?php if ($canFullPharmacy && !$isUnavailable && $status !== 'DISPENSED' && $status !== 'EXTERNAL' && $dispensed <= 0) { ?>
                                                    <button type="button" class="btn btn-danger btn-xs" data-toggle="modal" data-target="#unavailableModal<?php echo (int)$iop_med_id; ?>"><i class="fa fa-ban"></i> Unavailable</button>
                                                    <?php } ?>

                                                    <?php if ($canFullPharmacy && $status !== 'DISPENSED' && $extStatus !== 'EXTERNAL_PURCHASE' && $extStatus !== 'WAIVED'): ?>
                                                    <!-- GHS Flexible Workflow Buttons -->
                                                    <div style="margin-top:4px;">
                                                        <button type="button" class="btn btn-info btn-xs" data-toggle="modal" data-target="#extPurchaseModal<?php echo (int)$iop_med_id; ?>"><i class="fa fa-external-link"></i> External</button>
                                                        <button type="button" class="btn btn-warning btn-xs" data-toggle="modal" data-target="#unableToPayModal<?php echo (int)$iop_med_id; ?>"><i class="fa fa-user-times"></i> Unable to Pay</button>
                                                        <button type="button" class="btn btn-default btn-xs" data-toggle="modal" data-target="#deferredModal<?php echo (int)$iop_med_id; ?>"><i class="fa fa-calendar"></i> Defer</button>
                                                        <button type="button" class="btn btn-danger btn-xs" data-toggle="modal" data-target="#emergencyModal<?php echo (int)$iop_med_id; ?>"><i class="fa fa-ambulance"></i> Emergency</button>
                                                        <button type="button" class="btn btn-primary btn-xs" data-toggle="modal" data-target="#waiverModal<?php echo (int)$iop_med_id; ?>"><i class="fa fa-gift"></i> Waiver</button>
                                                    </div>
                                                    <?php endif; ?>
                                                    <?php if ($isUnavailable) { ?>
                                                    <?php if ($canFullPharmacy): ?>
                                                    <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_available">
                                                        <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(current_url() . '?' . http_build_query($_GET)); ?>">
                                                        <button type="submit" class="btn btn-info btn-xs" onclick="return confirm('Restore this medication to PENDING?');"><i class="fa fa-undo"></i> Mark Available</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    <?php } ?>

                                                    <!-- External Purchase Modal -->
                                                    <?php if ($canFullPharmacy): ?>
                                                    <div class="modal fade" id="extPurchaseModal<?php echo (int)$iop_med_id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_external_purchase">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-external-link"></i> External Purchase</h4></div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <p class="text-info"><i class="fa fa-info-circle"></i> Patient will purchase this medication externally. No payment required from cashier. This drug will be excluded from the hospital bill.</p>
                                                                        <div class="form-group"><label>Reason / Referral Note</label><textarea class="form-control" name="reason" rows="2" placeholder="e.g. Not in stock, patient referred to external pharmacy..."></textarea></div>
                                                                    </div>
                                                                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-info">Confirm External Purchase</button></div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Unable to Pay Modal -->
                                                    <?php if ($canFullPharmacy): ?>
                                                    <div class="modal fade" id="unableToPayModal<?php echo (int)$iop_med_id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_unable_to_pay">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-user-times"></i> Unable to Pay</h4></div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <div class="alert alert-warning"><i class="fa fa-exclamation-triangle"></i> Patient is unable to pay. Medication will be dispensed on humanitarian grounds. An outstanding balance will be recorded for follow-up by the cashier.</div>
                                                                        <div class="form-group"><label>Reason</label><textarea class="form-control" name="reason" rows="2" placeholder="e.g. Patient indigent, social welfare case..."></textarea></div>
                                                                    </div>
                                                                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning">Confirm Unable to Pay</button></div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Deferred Payment Modal -->
                                                    <?php if ($canFullPharmacy): ?>
                                                    <div class="modal fade" id="deferredModal<?php echo (int)$iop_med_id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_deferred_payment">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-calendar"></i> Deferred Payment</h4></div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <p class="text-info">Payment is deferred to a future date. Medication can be dispensed now. Cashier will follow up on payment.</p>
                                                                        <div class="form-group"><label>Reason</label><textarea class="form-control" name="reason" rows="2" placeholder="e.g. Patient promised to pay on return visit..."></textarea></div>
                                                                        <div class="form-group"><label>Defer Until <small class="text-muted">(optional)</small></label><input type="date" class="form-control" name="defer_until" min="<?php echo date('Y-m-d'); ?>"></div>
                                                                    </div>
                                                                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-default">Confirm Deferred</button></div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Emergency Override Modal -->
                                                    <?php if ($canFullPharmacy): ?>
                                                    <div class="modal fade" id="emergencyModal<?php echo (int)$iop_med_id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_emergency_override">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title text-danger"><i class="fa fa-ambulance"></i> Emergency Override</h4></div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <div class="alert alert-danger"><strong><i class="fa fa-ambulance"></i> Emergency Override</strong><br>This will allow immediate dispensing without payment. All actions are logged for audit. Use ONLY in genuine emergencies.</div>
                                                                        <div class="form-group"><label>Emergency Reason <span class="text-danger">*</span></label><textarea class="form-control" name="reason" rows="3" placeholder="Clinical justification for emergency override..." required></textarea></div>
                                                                    </div>
                                                                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger">Confirm Emergency Override</button></div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Waiver Request Modal -->
                                                    <?php if ($canFullPharmacy): ?>
                                                    <div class="modal fade" id="waiverModal<?php echo (int)$iop_med_id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/request_waiver">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <div class="modal-header"><button type="button" class="close" data-dismiss="modal">&times;</button><h4 class="modal-title"><i class="fa fa-gift"></i> Request Waiver</h4></div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <div class="alert alert-info"><i class="fa fa-info-circle"></i> A waiver request will be sent to Admin/Super-Admin for approval. The prescription will be held pending approval.</div>
                                                                        <div class="form-group"><label>Waiver Reason <span class="text-danger">*</span></label><textarea class="form-control" name="reason" rows="3" placeholder="Reason for requesting fee waiver (financial hardship, social welfare, etc.)..." required></textarea></div>
                                                                    </div>
                                                                    <div class="modal-footer"><button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Submit Waiver Request</button></div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>

                                                    <!-- Unavailable Modal -->
                                                    <?php if ($canFullPharmacy): ?>
                                                    <div class="modal fade" id="unavailableModal<?php echo (int)$iop_med_id; ?>" tabindex="-1" role="dialog" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="post" action="<?php echo base_url(); ?>app/pharmacy/mark_unavailable">
                                                                    <input type="hidden" name="<?php echo $this->security->get_csrf_token_name(); ?>" value="<?php echo $this->security->get_csrf_hash(); ?>">
                                                                    <div class="modal-header">
                                                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                                                        <h4 class="modal-title">Mark Medication Unavailable</h4>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(current_url() . '?' . http_build_query($_GET)); ?>">
                                                                        <p class="text-danger"><i class="fa fa-warning"></i> This medication will be marked as <strong>UNAVAILABLE</strong> and will <strong>NOT</strong> appear on the patient's bill.</p>
                                                                        <div class="form-group">
                                                                            <label>Reason / Notes</label>
                                                                            <textarea class="form-control" name="notes" rows="3" placeholder="e.g. Out of stock, alternative prescribed..."></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                                                                        <button type="submit" class="btn btn-danger">Confirm Unavailable</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                                    <div class="modal-body">
                                                                        <input type="hidden" name="iop_med_id" value="<?php echo (int)$iop_med_id; ?>">
                                                                        <input type="hidden" name="iop_id" value="<?php echo htmlspecialchars($iop_id); ?>">
                                                                        <input type="hidden" name="status" value="DISPENSED">
                                                                        <input type="hidden" name="return_url" value="<?php echo htmlspecialchars(current_url() . '?' . http_build_query($_GET)); ?>">

                                                                        <div class="form-group">
                                                                            <label>Quantity (remaining: <?php echo htmlspecialchars((string)$remaining); ?>, stock: <?php echo htmlspecialchars((string)$curStock); ?>)</label>
                                                                            <input type="number" step="0.01" min="0" max="<?php echo htmlspecialchars((string)min($remaining, $curStock)); ?>" class="form-control" name="qty" value="<?php echo htmlspecialchars((string)min($remaining, $curStock)); ?>" required>
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Batch No. <small class="text-muted">(optional)</small></label>
                                                                            <input type="text" class="form-control" name="batch_no" placeholder="e.g. BN-2025-001">
                                                                        </div>
                                                                        <div class="form-group">
                                                                            <label>Notes</label>
                                                                            <textarea class="form-control" name="notes" rows="3"></textarea>
                                                                        </div>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                                                                        <button type="submit" class="btn btn-success">Save</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>

                                                </td>
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

         <script src="<?php echo base_url();?>public/js/jquery.min.js"></script>
         <script src="<?php echo base_url();?>public/js/bootstrap.min.js" type="text/javascript"></script>
         <script src="<?php echo base_url();?>public/js/AdminLTE/app.js" type="text/javascript"></script>

    </body>
</html>
